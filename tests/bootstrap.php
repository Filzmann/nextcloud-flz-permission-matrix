<?php

declare(strict_types=1);

require_once __DIR__ . '/../../localbase/tests/bootstrap.php';

$appRoot = dirname(__DIR__);

spl_autoload_register(static function(string $class) use ($appRoot): void {
    $prefix = 'OCA\\BrPermissionMatrix\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = $appRoot . '/lib/' . $relative . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

function assertSameValue(mixed $expected, mixed $actual, string $message): void {
    \OCA\LocalBase\Tests\Support\assertSameValue($expected, $actual, $message);
}

function assertContainsText(string $needle, string $haystack, string $message): void {
    \OCA\LocalBase\Tests\Support\assertContainsString($needle, $haystack, $message);
}
