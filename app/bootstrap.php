<?php
declare(strict_types=1);

if (!defined('DIGISANGAM_ROOT')) define('DIGISANGAM_ROOT', dirname(__DIR__));
if (!defined('DIGISANGAM_STORAGE_ROOT')) {
    $configured=trim((string)getenv('DIGISANGAM_STORAGE_ROOT'));
    define('DIGISANGAM_STORAGE_ROOT', $configured!=='' ? rtrim($configured,'/\\') : DIGISANGAM_ROOT . '/storage');
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'DigiSangam\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) require $path;
});
