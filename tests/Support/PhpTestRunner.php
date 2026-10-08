<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Runs the dependency-free PHP test suite in isolated processes. */
final class PhpTestRunner {
    /** @param list<string> $lintDirectories @param list<string> $testDirectories @param list<string> $testSuffixes */
    public static function run(
        string $root,
        array $lintDirectories,
        array $testDirectories,
        array $testSuffixes,
        string $successMessage,
        bool $prependBootstrap = false,
    ): void {
        foreach (self::collect($root, $lintDirectories, static fn(string $path): bool => str_ends_with($path, '.php')) as $file) {
            self::execute($root, [PHP_BINARY, '-l', self::relativePath($root, $file)]);
        }
        foreach (self::collect($root, $testDirectories, static fn(string $path): bool => self::hasSuffix($path, $testSuffixes)) as $file) {
            $command = [PHP_BINARY];
            $bootstrap = $prependBootstrap ? $root . '/tests/bootstrap.php' : dirname(__DIR__) . '/bootstrap.php';
            if (is_file($bootstrap)) {
                $command[] = '-d';
                $command[] = 'auto_prepend_file=' . $bootstrap;
            }
            $command[] = self::relativePath($root, $file);
            self::execute($root, $command);
        }
        echo $successMessage . PHP_EOL;
    }

    /** @return list<string> */
    public static function collect(string $root, array $directories, callable $accept): array {
        $files = [];
        foreach ($directories as $directory) {
            $path = $root . '/' . trim((string)$directory, '/');
            if (!is_dir($path)) continue;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (str_contains(str_replace('\\', '/', $file->getPathname()), '/vendor/')) continue;
                if ($file->isFile() && $accept($file->getPathname())) $files[] = $file->getPathname();
            }
        }
        sort($files);
        return array_values(array_unique($files));
    }

    private static function hasSuffix(string $path, array $suffixes): bool {
        foreach ($suffixes as $suffix) if (str_ends_with($path, (string)$suffix)) return true;
        return false;
    }

    private static function relativePath(string $root, string $path): string {
        return str_replace(rtrim($root, '/') . '/', '', $path);
    }

    private static function execute(string $root, array $command): void {
        $command = self::withOptionalCoverage($root, $command);
        $display = implode(' ', array_map('escapeshellarg', $command));
        echo '> ' . $display . PHP_EOL;
        $previousDirectory = getcwd();
        if (!chdir($root)) throw new \RuntimeException("Testwurzel ist nicht erreichbar: {$root}");
        try {
            passthru($display, $exitCode);
        } finally {
            if ($previousDirectory !== false) chdir($previousDirectory);
        }
        if ($exitCode !== 0) exit($exitCode);
    }

    /** @param list<string> $command @return list<string> */
    public static function withOptionalCoverage(string $root, array $command): array {
        $tool = trim((string)getenv('PHP_COVERAGE_COMMAND'));
        $outputDirectory = trim((string)getenv('PHP_COVERAGE_OUTPUT_DIR'));
        if ($tool === '' || $outputDirectory === '' || ($command[0] ?? '') !== PHP_BINARY || ($command[1] ?? '') === '-l') return $command;
        if (!is_file($tool) || !is_executable($tool)) throw new \RuntimeException("Coverage-Werkzeug ist nicht ausführbar: {$tool}");
        if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
            throw new \RuntimeException("Coverage-Ausgabe konnte nicht angelegt werden: {$outputDirectory}");
        }
        $script = (string)($command[array_key_last($command)] ?? 'test');
        $scriptPath = str_starts_with($script, '/') ? $script : rtrim($root, '/') . '/' . ltrim($script, '/');
        $identifier = hash('sha256', $scriptPath);
        $report = rtrim($outputDirectory, '/') . '/' . $identifier . '.xml';
        $wrapper = rtrim($outputDirectory, '/') . '/run-' . $identifier . '.php';
        $bootstrapOption = array_values(array_filter($command, static fn(string $argument): bool => str_starts_with($argument, 'auto_prepend_file=')));
        $bootstrap = isset($bootstrapOption[0]) ? substr($bootstrapOption[0], strlen('auto_prepend_file=')) : '';
        $bootstrapCode = $bootstrap !== '' && is_file($bootstrap) ? '    require_once ' . var_export($bootstrap, true) . ";\n" : '';
        $wrapperCode = "<?php\n\ndeclare(strict_types=1);\n\n(static function (): void {\n" . $bootstrapCode
            . '    require ' . var_export($scriptPath, true) . ";\n})();\n";
        if (file_put_contents($wrapper, $wrapperCode) === false) throw new \RuntimeException("Coverage-Testwrapper konnte nicht angelegt werden: {$wrapper}");
        return [$tool, 'execute', '--clover', $report, '--include', $root . '/lib', '--add-uncovered', $wrapper];
    }
}
