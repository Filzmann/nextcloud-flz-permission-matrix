<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use OCA\FlzPermissionMatrix\Tests\Support\PhpTestRunner;

PhpTestRunner::run(
    root: dirname(__DIR__),
    lintDirectories: ['appinfo', 'lib', 'templates', 'tests'],
    testDirectories: ['tests/unit'],
    testSuffixes: ['Test.php'],
    successMessage: 'Filzmann Permission Matrix PHP tests passed',
    prependBootstrap: true,
);
