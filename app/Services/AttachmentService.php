<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Messaging\AttachmentScannerInterface;
use App\Enums\AttachmentType;
use App\Exceptions\AttachmentUploadException;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final class AttachmentService
{
    public function __construct(
        private readonly AttachmentScannerInterface $scanner,
    ) {}

    public function validate(UploadedFile $file): void
    {
        $maxBytes = config('messaging.max_attachment_size_mb', 50) * 1024 * 1024;

        if ($file->getSize() > $maxBytes) {
            throw AttachmentUploadException::processingFailed('File exceeds maximum allowed size.');
        }

        $mime = $this->detectMime($file);

        if (! in_array($mime, config('messaging.allowed_mime_types', []), true)) {
            throw AttachmentUploadException::invalidMime($mime);
        }

        if ($mime !== $file->getMimeType() && ! $this->mimeCompatible($mime, (string) $file->getMimeType())) {
            throw AttachmentUploadException::invalidMime($mime);
        }
    }

    public function upload(UploadedFile $file, User $uploader, ?int $messageId = null): Attachment
    {
        $this->validate($file);

        $disk = $this->disk();
        $mime = $this->detectMime($file);
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $uuid = (string) Str::uuid();
        $year = now()->format('Y');
        $month = now()->format('m');
        $path = sprintf('attachments/%s/%s/%s.%s', $year, $month, $uuid, $extension);

        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        $attachment = new Attachment([
            'uuid' => $uuid,
            'message_id' => $messageId,
            'uploader_id' => $uploader->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => (int) $file->getSize(),
            'mime_type' => $mime,
            'type' => $this->mapMimeToType($mime),
            'metadata' => [
                'client_mime' => $file->getMimeType(),
            ],
        ]);

        $attachment->save();

        $this->scanner->assertClean($attachment);

        return $attachment;
    }

    public function generateThumbnail(Attachment $attachment): void
    {
        if ($attachment->type !== AttachmentType::Image) {
            return;
        }

        $disk = $this->disk();
        $source = Storage::disk($disk)->path($attachment->file_path);

        if (! is_file($source)) {
            return;
        }

        if (! extension_loaded('gd')) {
            return;
        }

        $thumbRelative = str_replace(
            '.'.$this->extensionFromPath($attachment->file_path),
            '_thumb.jpg',
            $attachment->file_path
        );

        $imageInfo = @getimagesize($source);

        if ($imageInfo === false) {
            return;
        }

        $src = match ($imageInfo[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($source),
            IMAGETYPE_PNG => imagecreatefrompng($source),
            IMAGETYPE_GIF => imagecreatefromgif($source),
            default => null,
        };

        if (! $src instanceof \GdImage) {
            return;
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $max = 320;
        $scale = min($max / max($width, 1), $max / max($height, 1), 1.0);
        $tw = (int) max(1, $width * $scale);
        $th = (int) max(1, $height * $scale);
        $dst = imagecreatetruecolor($tw, $th);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $width, $height);
        $tmp = tempnam(sys_get_temp_dir(), 'thumb');
        imagejpeg($dst, $tmp, 85);
        Storage::disk($disk)->put($thumbRelative, file_get_contents($tmp));
        @unlink($tmp);
        imagedestroy($src);
        imagedestroy($dst);

        $attachment->forceFill(['thumbnail_path' => $thumbRelative])->save();
    }

    public function delete(string $uuid, User $user): bool
    {
        $attachment = Attachment::query()->where('uuid', $uuid)->first();

        if ($attachment === null) {
            return false;
        }

        if ($attachment->uploader_id !== $user->id) {
            return false;
        }

        $disk = $this->disk();

        Storage::disk($disk)->delete($attachment->file_path);

        if ($attachment->thumbnail_path) {
            Storage::disk($disk)->delete($attachment->thumbnail_path);
        }

        return (bool) $attachment->delete();
    }

    public function temporaryUrl(Attachment $attachment, ?string $relativePath = null): string
    {
        $disk = $this->disk();
        $path = $relativePath ?? $attachment->file_path;
        $ttl = now()->addMinutes((int) config('messaging.signed_url_ttl_minutes', 60));

        if ($disk === 'local') {
            // Storage serve route validates relative signatures (hasValidRelativeSignature).
            $relative = URL::temporarySignedRoute(
                'storage.'.$disk,
                $ttl,
                ['path' => $path],
                absolute: false,
            );

            return rtrim((string) config('app.url'), '/').$relative;
        }

        return Storage::disk($disk)->temporaryUrl($path, $ttl);
    }

    public function temporaryThumbnailUrl(Attachment $attachment): ?string
    {
        if ($attachment->thumbnail_path === null || $attachment->thumbnail_path === '') {
            return null;
        }

        return $this->temporaryUrl($attachment, $attachment->thumbnail_path);
    }

    private function disk(): string
    {
        return (string) config('filesystems.default', 'local');
    }

    private function detectMime(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        $client = (string) $file->getMimeType();
        $allowed = config('messaging.allowed_mime_types', []);

        if ($path === false) {
            return $client;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($path);
        $detected = is_string($detected) && $detected !== '' ? $detected : $client;

        if (in_array($detected, ['application/x-empty', 'application/octet-stream', 'inode/x-empty'], true)
            && in_array($client, $allowed, true)) {
            return $client;
        }

        return $detected;
    }

    private function mimeCompatible(string $detected, string $client): bool
    {
        return str_starts_with($detected, 'image/')
            && str_starts_with($client, 'image/');
    }

    private function mapMimeToType(string $mime): AttachmentType
    {
        return match (true) {
            str_starts_with($mime, 'image/') => AttachmentType::Image,
            str_starts_with($mime, 'video/') => AttachmentType::Video,
            str_starts_with($mime, 'audio/') => AttachmentType::Audio,
            default => AttachmentType::Document,
        };
    }

    private function extensionFromPath(string $path): string
    {
        $parts = explode('.', $path);

        return strtolower($parts[count($parts) - 1] ?? '');
    }
}
