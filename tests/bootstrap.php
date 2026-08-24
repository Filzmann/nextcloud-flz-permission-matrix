<?php

declare(strict_types=1);

$appRoot = dirname(__DIR__);
$workspaceRoot = dirname($appRoot);

spl_autoload_register(static function(string $class) use ($appRoot, $workspaceRoot): void {
    $prefixes = [
        'OCA\\FilzmannPermissionMatrix\\Tests\\' => $appRoot . '/tests/',
        'OCA\\FilzmannPermissionMatrix\\' => $appRoot . '/lib/',
        'OCA\\FilzmannDataProtection\\' => $appRoot . '/tests/stubs/FilzmannDataProtection/',
        'OCA\\LocalBase\\' => $workspaceRoot . '/localbase/lib/',
    ];
    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $path = $directory . $relative . '.php';
        if (is_file($path)) {
            require_once $path;
        }
        return;
    }
});

function assertSameValue(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

function assertContainsText(string $needle, string $haystack, string $message): void {
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . '\nMissing: ' . $needle);
    }
}
