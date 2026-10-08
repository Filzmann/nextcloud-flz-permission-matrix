<?php

declare(strict_types=1);

$migrationDirectory = dirname(__DIR__, 2) . '/lib/Migration';
$migrationFiles = glob($migrationDirectory . '/*.php');
if ($migrationFiles === false || $migrationFiles === []) {
    throw new RuntimeException('Keine Migrationen für den Indexnamen-Vertrag gefunden.');
}

foreach ($migrationFiles as $migrationFile) {
    $migration = (string)file_get_contents($migrationFile);
    if (preg_match('/setPrimaryKey\(\s*\[[^\]]+\]\s*\)/', $migration) === 1) {
        throw new RuntimeException(
            basename($migrationFile) . ': Primärschlüssel benötigt einen expliziten kurzen Indexnamen für Nextcloud 31/32.'
        );
    }

    preg_match_all(
        '/(?:setPrimaryKey|addUniqueIndex|addIndex)\(\s*\[[^\]]+\]\s*,\s*[\'\"]([^\'\"]+)[\'\"]\s*\)/',
        $migration,
        $matches
    );
    foreach ($matches[1] as $indexName) {
        if (!str_starts_with($indexName, 'flz_pm_')) {
            throw new RuntimeException(
                basename($migrationFile) . ": Index {$indexName} verwendet nicht das kanonische FLZ-Prefix flz_pm_."
            );
        }
        if (strlen($indexName) > 30) {
            throw new RuntimeException(
                basename($migrationFile) . ": Index {$indexName} überschreitet 30 Zeichen."
            );
        }
    }
}

echo "Migration index-name contract tests passed\n";
