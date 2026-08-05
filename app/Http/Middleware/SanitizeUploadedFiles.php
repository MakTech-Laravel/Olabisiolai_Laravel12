<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drop invalid uploads before Passport converts the request to PSR-7.
 * Invalid/empty tmp_name files make Symfony try fopen() on a directory (cwd/public)
 * and leak "Permission denied" to the client.
 *
 * When the client sent files that we must discard (too large for PHP, corrupt tmp, etc.),
 * abort with 422 instead of silently succeeding without those uploads.
 */
final class SanitizeUploadedFiles
{
    public function handle(Request $request, Closure $next): Response
    {
        $discardReasons = [];
        $cleaned = $this->clean($request->files->all(), $discardReasons);
        $request->files->replace($cleaned);

        if ($discardReasons !== []) {
            $message = $this->messageForDiscards($discardReasons);

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => [
                    'errors' => [
                        'file' => [$message],
                    ],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $files
     * @param  list<string>  $discardReasons
     * @return array<string, mixed>
     */
    private function clean(array $files, array &$discardReasons, string $prefix = ''): array
    {
        $out = [];

        foreach ($files as $key => $value) {
            $field = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if ($value instanceof UploadedFile) {
                // Empty multipart slots — drop quietly (do not fail the request).
                if ($value->getError() === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $reason = $this->discardReason($value);
                if ($reason === null) {
                    $out[$key] = $value;
                } else {
                    $discardReasons[] = $reason;
                }

                continue;
            }

            if (is_array($value)) {
                $nested = $this->clean($value, $discardReasons, $field);
                if ($nested !== []) {
                    $out[$key] = $nested;
                }
            }
        }

        return $out;
    }

    private function discardReason(UploadedFile $file): ?string
    {
        if ($file->getError() === UPLOAD_ERR_INI_SIZE || $file->getError() === UPLOAD_ERR_FORM_SIZE) {
            $limit = ini_get('upload_max_filesize') ?: 'the server limit';

            return "An uploaded file is larger than the server allows (max {$limit}). Please use a smaller JPG, PNG, or WebP.";
        }

        if (! $file->isValid()) {
            $detail = trim((string) $file->getErrorMessage());

            return $detail !== ''
                ? $detail
                : 'An uploaded file was invalid. Please try again with a JPG, PNG, or WebP under 10MB.';
        }

        $realPath = $file->getRealPath();
        if ($realPath === false || $realPath === '' || ! is_file($realPath) || ! is_readable($realPath)) {
            // Passport/PSR-7 would fopen() a bad path (often the public/ cwd) and 500.
            return 'Unable to read an uploaded file. Please try again.';
        }

        return null;
    }

    /**
     * @param  list<string>  $discardReasons
     */
    private function messageForDiscards(array $discardReasons): string
    {
        $unique = array_values(array_unique(array_filter($discardReasons)));

        return $unique[0] ?? 'One or more uploaded files could not be processed. Please try again.';
    }
}
