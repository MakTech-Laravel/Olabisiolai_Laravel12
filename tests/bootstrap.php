<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

require $basePath.'/vendor/autoload.php';

$privateKeyPath = $basePath.'/storage/oauth-private.key';

if (! is_file($privateKeyPath)) {
    chdir($basePath);
    $php = PHP_BINARY;
    $artisan = $basePath.DIRECTORY_SEPARATOR.'artisan';
    passthru($php.' '.escapeshellarg($artisan).' passport:keys --force --no-interaction', $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, "Failed to generate Passport keys (exit {$exitCode}).\n");
    }
}
