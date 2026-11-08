<?php
declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
}

$frameworkHelper = dirname(__DIR__) . '/vendor/topthink/framework/src/helper.php';

if (!function_exists('runtime_path') && is_file($frameworkHelper)) {
    require $frameworkHelper;
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Youvision\\ThinkVerifying\\Tests\\' => dirname(__DIR__) . '/tests/',
        'Youvision\\ThinkVerifying\\' => dirname(__DIR__) . '/src/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (str_starts_with($class, $prefix) === false) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $file = $directory . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file)) {
            require $file;
        }

        return;
    }
});