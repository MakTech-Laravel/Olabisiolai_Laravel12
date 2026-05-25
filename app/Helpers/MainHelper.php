<?php

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

if (! function_exists('sendResponse')) {
    function sendResponse($status, $message, $data = null, $statusCode = 200, $additional = null)
    {
        $responseData = [
            'success' => $status,
            'message' => $message,
        ];

        if ($data instanceof ResourceCollection || $data instanceof JsonResource) {
            if ($data->resource instanceof AbstractPaginator) {
                $paginatedData = $data->response()->getData(true);

                $responseData = array_merge($responseData, [
                    'data' => $paginatedData['data'],
                    'links' => $paginatedData['links'],
                    'meta' => $paginatedData['meta'],
                ]);
            } else {
                $responseData['data'] = $data->toArray(request());
            }
        } else {
            $responseData['data'] = $data;
        }

        if (! empty($additional) && is_array($additional)) {
            $responseData = array_merge($responseData, $additional);
        }

        return response()->json($responseData, $statusCode);
    }
}

if (! function_exists('humanDateTime')) {
    function humanDateTime(mixed $value, string $format = 'd M Y, h:i A'): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->format($format);
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value)->format($format);
        }

        return null;
    }
}

if (! function_exists('adminAuthCheck')) {
    function adminAuthCheck(Request $request): ?Admin
    {
        $admin = $request->user('admin_api');

        if (! $admin instanceof Admin) {
            return null;
        }

        return $admin;
    }
}

if (! function_exists('storage_url')) {
    /**
     * URL for a path on the `public` disk (storage/app/public → /storage/...).
     * Pass through already-absolute http(s) URLs.
     */
    function storage_url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $trimmed = ltrim($path, '/');

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $path;
        }

        $url = Storage::disk('public')->url($trimmed);

        if ($url === '' || str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url !== '' ? $url : null;
        }

        return url($url);
    }
}

if (! function_exists('public_media_url')) {
    /**
     * Absolute URL for logos, cover photos, or static public assets.
     *
     * @param  string|null  $default  Fallback when $path is empty; pass null to return null instead.
     */
    function public_media_url(?string $path, ?string $default = '/images/default.jpg'): ?string
    {
        $normalized = is_string($path) ? trim($path) : '';

        if ($normalized === '') {
            if ($default === null || $default === '') {
                return null;
            }

            return public_media_url($default, null);
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return $normalized;
        }

        if (str_starts_with($normalized, '/')) {
            return url($normalized);
        }

        $storagePath = storage_url($normalized);
        if ($storagePath === null || $storagePath === '') {
            return null;
        }

        if (str_starts_with($storagePath, 'http://') || str_starts_with($storagePath, 'https://')) {
            return $storagePath;
        }

        return url($storagePath);
    }
}
