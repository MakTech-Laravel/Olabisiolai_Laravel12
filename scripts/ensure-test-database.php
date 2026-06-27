<?php

declare(strict_types=1);

$host = '127.0.0.1';
$port = 3307;
$user = 'root';
$pass = 'root';
$db = 'olabisiolai_testing';

$pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}`");

echo "Created or verified database: {$db}\n";
