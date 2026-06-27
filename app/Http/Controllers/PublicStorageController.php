<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PublicStorageController extends Controller
{
    /**
     * Serve files from the public disk when the public/storage symlink is missing or stale.
     */
    public function show(Request $request, string $path): Response
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path);
    }
}
