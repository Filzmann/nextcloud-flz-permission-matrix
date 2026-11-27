<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$catalogs = [];
foreach (['en', 'en_GB'] as $locale) {
    $file = "{$root}/l10n/{$locale}.json";
    if (!is_file($file)) {
        throw new RuntimeException("L10N-Katalog fehlt: l10n/{$locale}.json");
    }
    $decoded = json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $catalogs[$locale] = $decoded['translations'] ?? [];
}

$keys = [];
$collect = static function (string $source, string $file, string $pattern) use (&$keys): void {
    if (preg_match_all($pattern, $source, $matches) === false) {
        throw new RuntimeException("L10N-Quelltexte konnten nicht gelesen werden: {$file}");
    }
    foreach ($matches[1] as $key) {
        $keys[$key] = $file;
    }
};

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/lib", FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    $source = (string)file_get_contents($file->getPathname());
    $collect($source, $file->getPathname(), '/->t\(\'([^\']+)\'/');
    $collect($source, $file->getPathname(), '/(?:InvalidArgumentException|DomainException|RuntimeException)\(\'([^\']+)\'/');
}
foreach (glob("{$root}/templates/*.php") ?: [] as $file) {
    $collect((string)file_get_contents($file), $file, '/\$l->t\(\'([^\']+)\'/');
}
$jsIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/js", FilesystemIterator::SKIP_DOTS));
foreach ($jsIterator as $jsFile) {
    if ($jsFile->getExtension() !== 'js') continue;
    $file = $jsFile->getPathname();
    if (basename($file) === 'l10n.js') continue;
    $source = (string)file_get_contents($file);
    $collect($source, $file, '/translate\(\'' . 'flz_permission_matrix' . '\',\s*\'([^\']+)\'/');
    $collect($source, $file, '/\bl10n\.t\(\'([^\']+)\'/');
}
$info = (string)file_get_contents("{$root}/appinfo/info.xml");
foreach (['name', 'summary', 'description'] as $tag) {
    if (preg_match('/<' . $tag . '>([^<]+)<\/' . $tag . '>/', $info, $m) === 1) {
        $keys[$m[1]] = 'appinfo/info.xml';
    }
}

if ($keys === []) {
    throw new RuntimeException('Keine L10N-Quelltexte gefunden — die t()-Markierung fehlt vollständig.');
}
foreach ($catalogs as $locale => $translations) {
    foreach ($keys as $key => $file) {
        $found = array_key_exists($key, $translations);
        if (!$found) {
            foreach ($translations as $catalogKey => $_) {
                if ($catalogKey !== '' && str_starts_with($key, $catalogKey)) { $found = true; break; }
            }
        }
        if (!$found) {
            throw new RuntimeException("L10N-Schlüssel fehlt in {$locale} ({$file}): {$key}");
        }
    }
}

echo 'L10nCatalogContractTest: OK (' . count($keys) . " keys)\n";
