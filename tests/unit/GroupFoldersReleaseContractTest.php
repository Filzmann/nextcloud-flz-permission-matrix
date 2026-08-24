<?php

declare(strict_types=1);

$root = sys_get_temp_dir() . '/permission-matrix-groupfolders-' . bin2hex(random_bytes(6));
$folderDir = $root . '/lib/Folder';
mkdir($folderDir, 0770, true);
mkdir($root . '/appinfo', 0770, true);

$writeValidFixture = static function() use ($root, $folderDir): void {
    file_put_contents($root . '/appinfo/info.xml', <<<'XML'
<info>
  <id>groupfolders</id>
  <version>22.0.6</version>
  <dependencies><nextcloud min-version="34" max-version="34" /></dependencies>
</info>
XML);
    file_put_contents($folderDir . '/FolderManager.php', <<<'PHP'
<?php
class FolderManager {
    /** @return array<int, FolderDefinitionWithMappings> */
    public function getAllFolders(): array {
        return [FolderDefinitionWithMappings::fromFolder($folder, $groups, $manage)];
    }
}
PHP);
    file_put_contents($folderDir . '/FolderDefinition.php', <<<'PHP'
<?php
class FolderDefinition {
    public function __construct(
        public readonly int $id,
        public readonly string $mountPoint,
        public readonly bool $acl,
    ) {}
}
PHP);
    file_put_contents($folderDir . '/FolderDefinitionWithMappings.php', <<<'PHP'
<?php
class FolderDefinitionWithMappings extends FolderDefinition {
    public function __construct(
        public readonly array $groups,
    ) {}
    public static function fromFolder($folder, $groups, $manage): self {}
}
PHP);
};

$run = static function(string $checkout) use ($appRoot): array {
    $command = 'bash ' . escapeshellarg($appRoot . '/scripts/check-groupfolders-source-compatibility') . ' ' . escapeshellarg($checkout) . ' 2>&1';
    exec($command, $output, $status);

    return [$status, implode("\n", $output)];
};

$writeValidFixture();
[$status, $output] = $run($root);
assertSameValue(0, $status, 'The release gate must accept the pinned official 22.x/Nextcloud 34 source contract. ' . $output);

file_put_contents($root . '/appinfo/info.xml', str_replace('22.0.6', '23.0.0', file_get_contents($root . '/appinfo/info.xml')));
[$status] = $run($root);
assertSameValue(1, $status, 'A future Groupfolders major must block until the adapter contract is deliberately updated.');

$writeValidFixture();
file_put_contents($folderDir . '/FolderDefinitionWithMappings.php', str_replace('public readonly array $groups', 'private array $groups', file_get_contents($folderDir . '/FolderDefinitionWithMappings.php')));
[$status] = $run($root);
assertSameValue(1, $status, 'A changed upstream DTO shape must block the release compatibility gate.');

$files = [
    $folderDir . '/FolderDefinitionWithMappings.php',
    $folderDir . '/FolderDefinition.php',
    $folderDir . '/FolderManager.php',
    $root . '/appinfo/info.xml',
];
foreach ($files as $file) {
    @unlink($file);
}
@rmdir($folderDir);
@rmdir($root . '/lib');
@rmdir($root . '/appinfo');
@rmdir($root);

echo 'Groupfolders release contract tests passed' . PHP_EOL;
