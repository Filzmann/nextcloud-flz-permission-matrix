<?php

declare(strict_types=1);

spl_autoload_register(function(string $class): void {
    $prefix = 'OCA\\BrPermissionMatrix\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__, 2) . '/lib/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

function assertSameValue(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assertContainsText(string $needle, string $haystack, string $message): void {
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . ' Missing: ' . $needle);
    }
}
