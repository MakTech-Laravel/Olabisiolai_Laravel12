<?php

declare(strict_types=1);

return [
    'max_upload_size_mb' => (int) env('MAX_UPLOAD_SIZE_MB', 20),
    'disk' => env('MEDIA_DISK', 'public'),
    'path_prefix' => 'uploads',

    /*
    |--------------------------------------------------------------------------
    | Image optimization (OptimizeImage job)
    |--------------------------------------------------------------------------
    | Primary compression uses Intervention Image (no external binaries).
    | Spatie jpegoptim/optipng still run afterward when installed on the server.
    | Large phone photos need more than PHP's default 128M while decoding/scaling.
    */
    'optimize' => [
        /** Longest edge in pixels; larger images are scaled down. 0 = no resize. */
        'max_edge' => (int) env('MEDIA_OPTIMIZE_MAX_EDGE', 2560),
        'jpeg_quality' => (int) env('MEDIA_OPTIMIZE_JPEG_QUALITY', 82),
        'webp_quality' => (int) env('MEDIA_OPTIMIZE_WEBP_QUALITY', 82),
        /** Raised only for the duration of OptimizeImage / optimizePath. */
        'memory_limit' => env('MEDIA_OPTIMIZE_MEMORY_LIMIT', '512M'),
    ],
];
