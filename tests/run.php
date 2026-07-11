<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function collect_php_files(string $root, array $directories): array {
    $files = [];
    foreach ($directories as $directory) {
        $path = $root . '/' . $directory;
        if (!is_dir($path)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }
    sort($files);

    return $files;
}

function relative_test_path(string $root, string $path): string {
    return str_replace($root . '/', '', $path);
}

function run_test_command(string $root, array $command): void {
    $display = implode(' ', array_map('escapeshellarg', $command));
    echo '> ' . $display . PHP_EOL;
    $previousDirectory = getcwd();
    chdir($root);
    passthru($display, $exitCode);
    chdir($previousDirectory);
    if ($exitCode !== 0) {
        exit($exitCode);
    }
}

$lintFiles = collect_php_files($root, ['appinfo', 'lib', 'templates', 'tests']);
foreach ($lintFiles as $file) {
    run_test_command($root, ['php', '-l', relative_test_path($root, $file)]);
}

foreach ([
    'tests/unit/AccessServiceTest.php',
    'tests/unit/ApiControllerAccessTest.php',
    'tests/unit/ConfigServiceTest.php',
    'tests/unit/MatrixRowTest.php',
    'tests/unit/GenericAppAdapterTest.php',
    'tests/unit/GroupCatalogServiceTest.php',
    'tests/unit/DiffServiceTest.php',
    'tests/unit/ScannerServiceTest.php',
    'tests/unit/ExportServiceTest.php',
] as $file) {
    run_test_command($root, ['php', $file]);
}

echo 'BR Permission Matrix PHP tests passed' . PHP_EOL;
