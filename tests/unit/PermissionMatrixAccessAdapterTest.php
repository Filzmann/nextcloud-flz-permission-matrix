<?php

declare(strict_types=1);

use OCA\FilzmannPermissionMatrix\Adapter\PermissionMatrixAccessAdapter;
use OCA\FilzmannPermissionMatrix\Service\ConfigService;
use OCA\FilzmannPermissionMatrix\Service\InventoryService;

class MatrixAccessFakeInventory extends InventoryService {
    public function __construct() {
    }

    public function isAppEnabled(string $appId): bool {
        return $appId === 'filzmann_permission_matrix';
    }

    public function groups(): array {
        return ['Andere', 'Matrix-Admin', 'Matrix-Viewer', 'admin'];
    }
}

class MatrixAccessFakeConfig extends ConfigService {
    public function __construct(private bool $missing = false) {
    }

    public function viewerGroups(): array {
        return $this->missing ? ['Nicht-Vorhanden'] : ['Matrix-Viewer'];
    }

    public function adminGroups(): array {
        return ['Matrix-Admin'];
    }
}

$result = (new PermissionMatrixAccessAdapter(
    new MatrixAccessFakeInventory(),
    new MatrixAccessFakeConfig()
))->collect();
$rows = $result->rows();
assertSameValue(2, count($rows), 'The matrix should expose its own view and management permissions.');
assertSameValue('X', $rows[0]->cells()['Matrix-Viewer'], 'Configured viewer groups should be able to read.');
assertSameValue('X', $rows[0]->cells()['Matrix-Admin'], 'Management must imply view access.');
assertSameValue('X', $rows[0]->cells()['admin'], 'Native Nextcloud admins should retain view access.');
assertSameValue('-', $rows[0]->cells()['Andere'], 'Unconfigured groups must remain denied.');
assertSameValue('A', $rows[1]->cells()['Matrix-Admin'], 'Configured app admins should be able to manage.');
assertSameValue('A', $rows[1]->cells()['admin'], 'Native Nextcloud admins should retain management access.');
assertSameValue('-', $rows[1]->cells()['Matrix-Viewer'], 'Viewer groups must not gain management access.');
assertContainsText('(Gruppe Matrix-Admin ODER Gruppe Matrix-Viewer ODER Gruppe admin)', $rows[0]->accessRules()[0]->conditionText(), 'The own view rule should be machine-readable from canonical configured groups.');

$missing = (new PermissionMatrixAccessAdapter(
    new MatrixAccessFakeInventory(),
    new MatrixAccessFakeConfig(true)
))->collect();
assertSameValue('UNKNOWN', $missing->rows()[0]->status(), 'A configured group missing from the inventory must stay fail-closed.');
assertContainsText('nicht im Gruppeninventar', implode(' ', $missing->warnings()), 'A stale own access configuration needs a precise warning.');

echo 'PermissionMatrixAccessAdapter tests passed' . PHP_EOL;
