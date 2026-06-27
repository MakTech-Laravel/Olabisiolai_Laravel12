<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageServeTest extends TestCase
{
    public function test_storage_route_serves_public_disk_files(): void
    {
        $path = 'test-media/sample.txt';
        Storage::disk('public')->put($path, 'hello verification');

        try {
            $response = $this->get('/storage/'.$path);

            $response->assertOk();
        } finally {
            Storage::disk('public')->delete($path);
        }
    }

    public function test_storage_route_returns_not_found_for_missing_files(): void
    {
        $response = $this->get('/storage/does-not-exist/sample.txt');

        $response->assertNotFound();
    }
}
