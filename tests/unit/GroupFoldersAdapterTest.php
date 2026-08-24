<?php

declare(strict_types=1);

use OCA\FilzmannPermissionMatrix\Adapter\GroupFoldersAdapter;
use OCA\FilzmannPermissionMatrix\Service\GroupFoldersSourceInterface;
use OCA\FilzmannPermissionMatrix\Service\InventoryService;

class GroupFoldersAdapterInventory extends InventoryService {
    public function __construct(private string $appId = 'groupfolders', private string $version = '22.0.6') {
    }

    public function groups(): array {
        return ['Betriebsrat', 'IKT-Ausschuss'];
    }

    public function isAppEnabled(string $appId): bool {
        return $appId === $this->appId;
    }

    public function appSummary(string $appId): array {
        return ['app_id' => $appId, 'version' => $this->version];
    }
}

class GroupFoldersAdapterSource implements GroupFoldersSourceInterface {
    public array $calls = [];

    public function __construct(private array $result) {
    }

    public function folders(string $appId, string $appVersion): array {
        $this->calls[] = [$appId, $appVersion];

        return $this->result;
    }
}

$source = new GroupFoldersAdapterSource([
    'folders' => [[
        'reference' => 'a1b2c3d4e5f6',
        'groups' => ['Betriebsrat' => 31],
        'advanced_permissions' => false,
    ]],
    'complete' => true,
    'warnings' => [],
    'source' => 'nextcloud-groupfolders:22:FolderManager::getAllFolders',
]);
$result = (new GroupFoldersAdapter(new GroupFoldersAdapterInventory(), $source))->collect();
assertSameValue([['groupfolders', '22.0.6']], $source->calls, 'The adapter must bind the read to the installed app id and version.');
assertSameValue(6, count($result->rows()), 'A Team Folder must expose every native permission bit and execute as not applicable.');
$rows = [];
foreach ($result->rows() as $row) {
    $rows[$row->permissionType()] = $row;
}
assertSameValue('R', $rows['Lesen']->cells()['Betriebsrat'], 'The native read bit must be visible.');
assertSameValue('W', $rows['Ändern']->cells()['Betriebsrat'], 'The native update bit must be visible.');
assertSameValue('C', $rows['Erstellen']->cells()['Betriebsrat'], 'The native create bit must remain distinct.');
assertSameValue('D', $rows['Löschen']->cells()['Betriebsrat'], 'The native delete bit must remain distinct.');
assertSameValue('S', $rows['Teilen']->cells()['Betriebsrat'], 'The native share bit must remain distinct.');
assertSameValue('n/a', $rows['Ausführen']->cells()['Betriebsrat'], 'Nextcloud has no execute bit for Team Folders.');
assertSameValue('-', $rows['Lesen']->cells()['IKT-Ausschuss'], 'Unassigned inventory groups must not receive access.');
assertSameValue('Team-Folder a1b2c3d4e5f6', $rows['Lesen']->objectName(), 'The matrix must use a pseudonymous reference instead of the mount path.');
assertSameValue('NEW', $rows['Lesen']->status(), 'A complete, validated root permission read may be known.');
assertContainsText('Gruppe Betriebsrat', $rows['Lesen']->accessRules()[0]->conditionText(), 'Granted rights need a machine-readable group condition.');

$advanced = new GroupFoldersAdapterSource([
    'folders' => [[
        'reference' => 'abcdef000001',
        'groups' => ['Betriebsrat' => 1],
        'advanced_permissions' => true,
    ]],
    'complete' => true,
    'warnings' => [],
    'source' => 'nextcloud-groupfolders:22:FolderManager::getAllFolders',
]);
$advancedResult = (new GroupFoldersAdapter(new GroupFoldersAdapterInventory(), $advanced))->collect();
assertSameValue('UNKNOWN', $advancedResult->rows()[0]->status(), 'The adapter must independently keep root rights UNKNOWN when nested advanced ACLs can change effective access.');
assertSameValue('R', $advancedResult->rows()[0]->cells()['Betriebsrat'], 'Observed root rights may remain visible while completeness is unknown.');
assertContainsText('Erweiterte ACLs', implode(' ', $advancedResult->warnings()), 'The missing nested ACL coverage must be explicit.');

$unsupported = new GroupFoldersAdapterSource([
    'folders' => [],
    'complete' => false,
    'warnings' => ['Groupfolders-Version 23.0.0 ist nicht durch den read-only Adapter freigegeben.'],
    'source' => 'nextcloud-groupfolders:unsupported',
]);
$unsupportedResult = (new GroupFoldersAdapter(new GroupFoldersAdapterInventory('groupfolders', '23.0.0'), $unsupported))->collect();
assertSameValue(1, count($unsupportedResult->rows()), 'An incompatible provider must remain visibly represented.');
assertSameValue('UNKNOWN', $unsupportedResult->rows()[0]->status(), 'An incompatible version must fail closed.');
assertSameValue(['Betriebsrat' => '?', 'IKT-Ausschuss' => '?'], $unsupportedResult->rows()[0]->cells(), 'Unknown coverage must not synthesize deny values.');

$legacySource = new GroupFoldersAdapterSource([
    'folders' => [],
    'complete' => false,
    'warnings' => ['Die historische App files_groupfolders besitzt keinen freigegebenen Quellvertrag.'],
    'source' => 'nextcloud-groupfolders:unsupported',
]);
$legacy = (new GroupFoldersAdapter(new GroupFoldersAdapterInventory('files_groupfolders', '1.0.0'), $legacySource))->collect();
assertSameValue([['files_groupfolders', '1.0.0']], $legacySource->calls, 'Legacy installations should still be analysed through the soft-fail source boundary.');
assertSameValue('UNKNOWN', $legacy->rows()[0]->status(), 'Legacy installations must remain visible rather than being silently ignored.');

echo 'Groupfolders adapter tests passed' . PHP_EOL;
