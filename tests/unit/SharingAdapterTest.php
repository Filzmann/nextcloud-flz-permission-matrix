<?php

declare(strict_types=1);

use OCA\FilzmannPermissionMatrix\Adapter\SharingAdapter;
use OCA\FilzmannPermissionMatrix\Service\ConfigService;
use OCA\FilzmannPermissionMatrix\Service\InventoryService;
use OCA\FilzmannPermissionMatrix\Service\NativeSharingSourceInterface;

class SharingAdapterFakeInventory extends InventoryService {
    public function __construct(private bool $enabled = true) {
    }

    public function groups(): array {
        return ['Betriebsrat', 'IKT-Ausschuss'];
    }

    public function isAppEnabled(string $appId): bool {
        return $this->enabled && $appId === 'files_sharing';
    }
}

class SharingAdapterFakeConfig extends ConfigService {
    public function __construct(private bool $includeShares) {
    }

    public function includeShareMetadata(): bool {
        return $this->includeShares;
    }
}

class SharingAdapterFakeSource implements NativeSharingSourceInterface {
    public int $policyCalls = 0;
    public int $shareCalls = 0;

    public function __construct(
        private array $shares = [],
        private bool $complete = true,
        private bool $throwOnShares = false
    ) {
    }

    public function policies(): array {
        $this->policyCalls++;

        return [
            'values' => [
                'sharing' => ['value' => true, 'source' => 'nextcloud:OCP\\Share\\IManager::shareApiEnabled', 'confidence' => 'high'],
                'group_sharing' => ['value' => true, 'source' => 'nextcloud:OCP\\Share\\IManager::allowGroupSharing', 'confidence' => 'high'],
                'link_sharing' => ['value' => false, 'source' => 'nextcloud:OCP\\Share\\IManager::shareApiAllowLinks', 'confidence' => 'high'],
            ],
            'warnings' => [],
        ];
    }

    public function groupShares(): array {
        $this->shareCalls++;
        if ($this->throwOnShares) {
            throw new RuntimeException('sensitive provider failure');
        }

        return [
            'shares' => $this->shares,
            'complete' => $this->complete,
            'warnings' => $this->complete ? [] : ['Mindestens eine Share-Quelle konnte nicht vollständig gelesen werden.'],
        ];
    }
}

$disabledSource = new SharingAdapterFakeSource();
$disabled = (new SharingAdapter(
    new SharingAdapterFakeInventory(false),
    new SharingAdapterFakeConfig(true),
    $disabledSource
))->collect();
assertSameValue([], $disabled->rows(), 'A disabled sharing app must not produce synthetic deny policies.');
assertSameValue(0, $disabledSource->policyCalls, 'A disabled sharing app must not call the native source.');

$defaultSource = new SharingAdapterFakeSource();
$default = (new SharingAdapter(
    new SharingAdapterFakeInventory(),
    new SharingAdapterFakeConfig(false),
    $defaultSource
))->collect();
assertSameValue(1, $defaultSource->policyCalls, 'Global native sharing policies should be read once.');
assertSameValue(0, $defaultSource->shareCalls, 'Individual shares must not be enumerated without explicit opt-in.');
assertSameValue(0, count(array_filter($default->rows(), static fn($row): bool => $row->objectType() === 'SharedFolder')), 'Default scans must not persist share paths.');

$source = new SharingAdapterFakeSource([[
    'reference' => 'share-a1',
    'group_id' => 'Betriebsrat',
    'target' => '/BR/Freigaben',
    'node_type' => 'folder',
    'permissions' => 31,
    'inherited' => true,
]]);
$result = (new SharingAdapter(
    new SharingAdapterFakeInventory(),
    new SharingAdapterFakeConfig(true),
    $source
))->collect();
$folderRows = array_values(array_filter($result->rows(), static fn($row): bool => $row->objectType() === 'SharedFolder'));
assertSameValue(6, count($folderRows), 'A group folder share should expose read, update, execute, delete, create and share separately.');
$byPermission = [];
foreach ($folderRows as $row) {
    $byPermission[$row->permissionType()] = $row;
}
assertSameValue('R', $byPermission['Lesen']->cells()['Betriebsrat'], 'The native read bit should be visible for the target group.');
assertSameValue('W', $byPermission['Ändern']->cells()['Betriebsrat'], 'The native update bit should be visible for the target group.');
assertSameValue('C', $byPermission['Erstellen']->cells()['Betriebsrat'], 'The native create bit should remain distinct.');
assertSameValue('D', $byPermission['Löschen']->cells()['Betriebsrat'], 'The native delete bit should remain distinct.');
assertSameValue('S', $byPermission['Teilen']->cells()['Betriebsrat'], 'The native share bit should remain distinct.');
assertSameValue('n/a', $byPermission['Ausführen']->cells()['Betriebsrat'], 'Execute must be explicitly not applicable when Nextcloud does not provide that bit.');
assertSameValue('-', $byPermission['Lesen']->cells()['IKT-Ausschuss'], 'A separate share must not grant unrelated groups access.');
assertSameValue('/BR/Freigaben', $byPermission['Lesen']->objectName(), 'The protected matrix should retain the normalized concrete target path.');
assertContainsText('Weiterfreigabe', $byPermission['Lesen']->detail(), 'Reshare inheritance should remain explicit without exposing a parent id.');
assertContainsText('Gruppe Betriebsrat', $byPermission['Lesen']->accessRules()[0]->conditionText(), 'Granted share rights should retain a machine-readable group condition.');

$invalidSource = new SharingAdapterFakeSource([[
    'reference' => 'share-invalid',
    'group_id' => 'Betriebsrat',
    'target' => '/BR/../Personalakte',
    'node_type' => 'folder',
    'permissions' => 31,
]]);
$invalid = (new SharingAdapter(
    new SharingAdapterFakeInventory(),
    new SharingAdapterFakeConfig(true),
    $invalidSource
))->collect();
assertSameValue(0, count(array_filter($invalid->rows(), static fn($row): bool => $row->objectName() === '/BR/../Personalakte')), 'Traversal-like targets must never enter a snapshot.');
assertSameValue(1, count(array_filter($invalid->rows(), static fn($row): bool => $row->objectName() === 'Gruppenfreigaben nicht auslesbar')), 'A rejected target should leave a controlled unknown coverage row.');
assertContainsText('ungültigen oder mehrdeutigen Zielpfad', implode(' ', $invalid->warnings()), 'Rejected paths need a safe diagnostic without echoing the path.');

$partialSource = new SharingAdapterFakeSource([[
    'reference' => 'share-partial',
    'group_id' => 'Betriebsrat',
    'target' => '/BR/Teilstand',
    'node_type' => 'folder',
    'permissions' => 1,
]], false);
$partial = (new SharingAdapter(
    new SharingAdapterFakeInventory(),
    new SharingAdapterFakeConfig(true),
    $partialSource
))->collect();
$partialRows = array_values(array_filter($partial->rows(), static fn($row): bool => $row->objectType() === 'SharedFolder'));
assertSameValue('UNKNOWN', $partialRows[0]->status(), 'Incomplete enumeration must not present even observed rights as fully known.');
$partialByPermission = [];
foreach ($partialRows as $row) {
    $partialByPermission[$row->permissionType()] = $row;
}
assertSameValue('-', $partialByPermission['Ändern']->cells()['Betriebsrat'], 'An absent native bit should be represented as a concrete deny for that share.');

$conflictSource = new SharingAdapterFakeSource([[
    'reference' => 'share-conflict-a',
    'group_id' => 'Betriebsrat',
    'target' => '/BR/Konflikt',
    'node_type' => 'folder',
    'permissions' => 1,
    'inherited' => false,
], [
    'reference' => 'share-conflict-b',
    'group_id' => 'Betriebsrat',
    'target' => '/BR/Konflikt',
    'node_type' => 'folder',
    'permissions' => 3,
    'inherited' => true,
]]);
$conflict = (new SharingAdapter(
    new SharingAdapterFakeInventory(),
    new SharingAdapterFakeConfig(true),
    $conflictSource
))->collect();
$conflictRows = array_values(array_filter($conflict->rows(), static fn($row): bool => $row->objectName() === '/BR/Konflikt'));
assertSameValue('UNKNOWN', $conflictRows[0]->status(), 'Conflicting permission masks for one group target must stay fail-closed.');
assertContainsText('widersprüchliche', implode(' ', $conflict->warnings()), 'Conflicting native records need an explicit diagnostic.');

$mixedSource = new SharingAdapterFakeSource([[
    'reference' => 'share-valid-before-invalid',
    'group_id' => 'Betriebsrat',
    'target' => '/BR/Valid',
    'node_type' => 'folder',
    'permissions' => 1,
], [
    'reference' => 'share-invalid-after-valid',
    'group_id' => 'Betriebsrat',
    'target' => '/BR/../Invalid',
    'node_type' => 'folder',
    'permissions' => 31,
]]);
$mixed = (new SharingAdapter(
    new SharingAdapterFakeInventory(),
    new SharingAdapterFakeConfig(true),
    $mixedSource
))->collect();
$mixedRows = array_values(array_filter($mixed->rows(), static fn($row): bool => $row->objectName() === '/BR/Valid'));
assertSameValue('UNKNOWN', $mixedRows[0]->status(), 'A later invalid record must downgrade every row from the same enumeration, independent of source order.');

$failingSource = new SharingAdapterFakeSource([], true, true);
$failed = (new SharingAdapter(
    new SharingAdapterFakeInventory(),
    new SharingAdapterFakeConfig(true),
    $failingSource
))->collect();
assertSameValue(1, count(array_filter($failed->rows(), static fn($row): bool => $row->objectName() === 'Gruppenfreigaben nicht auslesbar')), 'A provider failure must become one controlled unknown row.');
assertSameValue(false, str_contains(implode(' ', $failed->warnings()), 'sensitive provider failure'), 'Provider exception details must not leak into scan diagnostics.');

echo 'SharingAdapter tests passed' . PHP_EOL;
