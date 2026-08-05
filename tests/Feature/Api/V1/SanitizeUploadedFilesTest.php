<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\SanitizeUploadedFiles;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class SanitizeUploadedFilesTest extends TestCase
{
    public function test_oversized_upload_returns_422_instead_of_silent_drop(): void
    {
        $file = new UploadedFile(
            path: sys_get_temp_dir().DIRECTORY_SEPARATOR.'sanitize-oversized.jpg',
            originalName: 'huge.jpg',
            mimeType: 'image/jpeg',
            error: UPLOAD_ERR_INI_SIZE,
            test: true,
        );

        $request = Request::create('/api/v1/vendor/business/update', 'POST');
        $request->files->set('cover_photos', [$file]);

        $response = (new SanitizeUploadedFiles())->handle(
            $request,
            static fn () => response()->json(['success' => true]),
        );

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertFalse($payload['success'] ?? true);
        $this->assertStringContainsString('larger than the server allows', (string) ($payload['message'] ?? ''));
        $this->assertSame([], $request->file('cover_photos', []));
    }

    public function test_readable_upload_passes_through(): void
    {
        $file = UploadedFile::fake()->image('cover.jpg', 100, 100);

        $request = Request::create('/api/v1/vendor/business/update', 'POST');
        $request->files->set('cover_photos', [$file]);

        $called = false;
        $response = (new SanitizeUploadedFiles())->handle(
            $request,
            static function (Request $req) use (&$called) {
                $called = true;
                $photos = $req->file('cover_photos', []);

                return response()->json([
                    'success' => true,
                    'count' => is_array($photos) ? count($photos) : 0,
                ]);
            },
        );

        $this->assertTrue($called);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(1, $payload['count'] ?? 0);
    }

    public function test_empty_file_slot_is_dropped_without_failing(): void
    {
        $file = new UploadedFile(
            path: '',
            originalName: '',
            mimeType: null,
            error: UPLOAD_ERR_NO_FILE,
            test: true,
        );

        $request = Request::create('/api/v1/ping', 'POST');
        $request->files->set('logo', $file);

        $response = (new SanitizeUploadedFiles())->handle(
            $request,
            static fn () => response()->json(['success' => true]),
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertNull($request->file('logo'));
    }
}
