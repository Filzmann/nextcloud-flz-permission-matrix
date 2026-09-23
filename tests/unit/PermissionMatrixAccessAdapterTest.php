<?php

declare(strict_types=1);

use OCA\FilzmannPermissionMatrix\Adapter\PermissionMatrixAccessAdapter;
use OCA\FilzmannPermissionMatrix\Service\ConfigService;
use OCA\FilzmannPermissionMatrix\Service\InventoryService;

class MatrixAccessFakeInventory extends InventoryService {
    public function __construct(private bool $withPrivacyOfficer = true) {
    }

    public function isAppEnabled(string $appId): bool {
        return $appId === 'filzmann_permission_matrix';
    }

    public function groups(): array {
        return array_values(array_filter(
            ['Andere', 'Datenschutzbeauftragte', 'Matrix-Admin', 'Matrix-Viewer', 'admin'],
            fn(string $group): bool => $this->withPrivacyOfficer || $group !== 'Datenschutzbeauftragte',
        ));
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
assertSameValue(3, count($rows), 'The matrix should expose view, management, and DPO grant-management permissions.');
assertSameValue('X', $rows[0]->cells()['Matrix-Viewer'], 'Configured viewer groups should be able to read.');
assertSameValue('X', $rows[0]->cells()['Matrix-Admin'], 'Management must imply view access.');
assertSameValue('-', $rows[0]->cells()['admin'], 'Native admin group membership alone must not imply view access.');
assertSameValue('-', $rows[0]->cells()['Andere'], 'Unconfigured groups must remain denied.');
assertSameValue('A', $rows[1]->cells()['Matrix-Admin'], 'Configured app admins should be able to manage.');
assertSameValue('-', $rows[1]->cells()['admin'], 'Native admin group membership alone must not imply management access.');
assertSameValue('-', $rows[1]->cells()['Matrix-Viewer'], 'Viewer groups must not gain management access.');
assertContainsText('(Gruppe Matrix-Admin ODER Gruppe Matrix-Viewer ODER (Nextcloud-Administration UND Aktive zeitlich begrenzte App-Adminfreigabe))', $rows[0]->accessRules()[0]->conditionText(), 'The own view rule should expose the configured groups and the compound temporary admin grant.');
assertContainsText('(Gruppe Matrix-Admin ODER (Nextcloud-Administration UND Aktive zeitlich begrenzte App-Adminfreigabe))', $rows[1]->accessRules()[0]->conditionText(), 'The own management rule should never describe native admin status as sufficient.');
assertSameValue('A', $rows[2]->cells()['Datenschutzbeauftragte'], 'Only the DPO group should manage temporary app-admin grants.');
assertSameValue('-', $rows[2]->cells()['admin'], 'Native admin membership must not manage temporary app-admin grants.');
assertContainsText('Gruppe Datenschutzbeauftragte', $rows[2]->accessRules()[0]->conditionText(), 'The grant-management projection must expose the canonical DPO group.');

$missing = (new PermissionMatrixAccessAdapter(
    new MatrixAccessFakeInventory(),
    new MatrixAccessFakeConfig(true)
))->collect();
assertSameValue('UNKNOWN', $missing->rows()[0]->status(), 'A configured group missing from the inventory must stay fail-closed.');
assertContainsText('nicht im Gruppeninventar', implode(' ', $missing->warnings()), 'A stale own access configuration needs a precise warning.');

$missingPrivacyOfficer = (new PermissionMatrixAccessAdapter(
    new MatrixAccessFakeInventory(false),
    new MatrixAccessFakeConfig()
))->collect();
assertSameValue('UNKNOWN', $missingPrivacyOfficer->rows()[2]->status(), 'A missing canonical DPO group must keep grant management fail-closed.');
assertContainsText('Datenschutzbeauftragte', implode(' ', $missingPrivacyOfficer->warnings()), 'A missing DPO group needs a precise warning.');

echo 'PermissionMatrixAccessAdapter tests passed' . PHP_EOL;
