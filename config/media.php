<?php

declare(strict_types=1);

return [
    'max_upload_size_mb' => (int) env('MAX_UPLOAD_SIZE_MB', 20),
    'disk' => env('MEDIA_DISK', 'public'),
    'path_prefix' => 'uploads',
];
