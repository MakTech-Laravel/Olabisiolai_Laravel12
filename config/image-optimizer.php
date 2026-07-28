<?php

use Spatie\ImageOptimizer\Optimizers\Cwebp;
use Spatie\ImageOptimizer\Optimizers\Gifsicle;
use Spatie\ImageOptimizer\Optimizers\Jpegoptim;
use Spatie\ImageOptimizer\Optimizers\Optipng;
use Spatie\ImageOptimizer\Optimizers\Svgo;

return [
    /*
     * When calling `optimize` the package will automatically determine which optimizers
     * should run for the given image.
     *
     * Pngquant is intentionally omitted — it is lossy.
     */
    'optimizers' => [

        Jpegoptim::class => [
            '--strip-all',
            '--all-progressive',
        ],

        Optipng::class => [
            '-i0',
            '-o2',
            '-quiet',
        ],

        Svgo::class => [
            '--disable=cleanupIDs',
        ],

        Gifsicle::class => [
            '-b',
            '-O3',
        ],

        Cwebp::class => [
            '-lossless',
            '-q 100',
        ],
    ],

    /*
    * The directory where your binaries are stored.
    * Only use this when you binaries are not accessible in the global environment.
    */
    'binary_path' => '',

    /*
     * The maximum time in seconds each optimizer is allowed to run separately.
     */
    'timeout' => 60,

    /*
     * If set to `true` all output of the optimizer binaries will be appended to the default log.
     * You can also set this to a class that implements `Psr\Log\LoggerInterface`.
     */
    'log_optimizer_activity' => false,
];
