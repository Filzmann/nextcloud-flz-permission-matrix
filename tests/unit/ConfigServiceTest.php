<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

use OCA\BrPermissionMatrix\Service\ConfigService;

$config = new class extends ConfigService {
    public function __construct() {
    }
};
$normalizeFormats = (new ReflectionClass(ConfigService::class))->getMethod('normalizeFormats');

assertSameValue(
    ['html', 'md'],
    $normalizeFormats->invoke($config, ['PDF', 'MD', 'html', 'md']),
    'Export configuration should normalize case, remove duplicates and reject unimplemented formats.'
);
assertSameValue(
    ['md', 'csv', 'json', 'html'],
    $normalizeFormats->invoke($config, []),
    'An empty export configuration should retain the safe documented default set.'
);

echo 'ConfigService tests passed' . PHP_EOL;
