<?php

declare(strict_types=1);

use OCA\FlzPermissionMatrix\Adapter\PermissionMatrixAccessAdapter;
use OCA\FlzPermissionMatrix\Service\BrMembershipService;
use OCA\FlzPermissionMatrix\Service\InventoryService;

class MatrixAccessFakeInventory extends InventoryService {
    public function __construct(private bool $withPrivacyOfficer = true, private bool $withBrGroup = true) {}
    public function isAppEnabled(string $appId): bool { return $appId === 'flz_permission_matrix'; }
    public function groups(): array {
        return array_values(array_filter(
            ['Andere', 'Datenschutzbeauftragte', 'BR-Kanonisch', 'admin'],
            fn(string $group): bool => ($this->withPrivacyOfficer || $group !== 'Datenschutzbeauftragte')
                && ($this->withBrGroup || $group !== 'BR-Kanonisch'),
        ));
    }
}

class MatrixAccessFakeBrMembership extends BrMembershipService {
    public function __construct(private ?string $groupId = 'BR-Kanonisch') {}
    public function memberGroupId(): ?string { return $this->groupId; }
}

$result = (new PermissionMatrixAccessAdapter(new MatrixAccessFakeInventory(), new MatrixAccessFakeBrMembership()))->collect();
$rows = [];
foreach ($result->rows() as $row) $rows[$row->objectName()] = $row;

assertSameValue(6, count($rows), 'The matrix must expose the six independently assigned matrix permissions.');
foreach (['Matrix ansehen', 'Exporte erzeugen', 'Manuelle Scans starten'] as $permission) {
    assertSameValue('X', $rows[$permission]->cells()['BR-Kanonisch'], $permission . ' must be granted to the canonical BR member group.');
    assertSameValue('X', $rows[$permission]->cells()['Datenschutzbeauftragte'], $permission . ' must be granted to the fixed DPO group.');
    assertSameValue('-', $rows[$permission]->cells()['Andere'], $permission . ' must deny unrelated groups.');
}
foreach (['Baseline festlegen', 'App-Konfiguration ändern'] as $permission) {
    assertSameValue('-', $rows[$permission]->cells()['BR-Kanonisch'], $permission . ' must not be granted through BR identity.');
    assertSameValue('A', $rows[$permission]->cells()['Datenschutzbeauftragte'], $permission . ' must be granted to the fixed DPO group.');
}
assertSameValue('A', $rows['Temporären Admin-Vollzugriff verwalten']->cells()['Datenschutzbeauftragte'], 'Only the DPO group should manage temporary app-admin grants.');
assertSameValue('-', $rows['Temporären Admin-Vollzugriff verwalten']->cells()['admin'], 'Native admin membership must not manage temporary app-admin grants.');

$missingProvider = (new PermissionMatrixAccessAdapter(new MatrixAccessFakeInventory(), new MatrixAccessFakeBrMembership(null)))->collect();
assertSameValue('UNKNOWN', $missingProvider->rows()[0]->status(), 'A missing canonical BR provider must keep BR-derived permissions visibly fail-closed.');
assertContainsText('BR-Mitgliedschaft', implode(' ', $missingProvider->warnings()), 'A missing BR provider needs a precise warning.');

$missingBrGroup = (new PermissionMatrixAccessAdapter(new MatrixAccessFakeInventory(true, false), new MatrixAccessFakeBrMembership()))->collect();
assertSameValue('UNKNOWN', $missingBrGroup->rows()[0]->status(), 'A canonical BR group missing from the native inventory must stay fail-closed.');

$missingPrivacyOfficer = (new PermissionMatrixAccessAdapter(new MatrixAccessFakeInventory(false), new MatrixAccessFakeBrMembership()))->collect();
$missingDpoRows = [];
foreach ($missingPrivacyOfficer->rows() as $row) $missingDpoRows[$row->objectName()] = $row;
assertSameValue('UNKNOWN', $missingDpoRows['App-Konfiguration ändern']->status(), 'A missing canonical DPO group must keep administration fail-closed.');
assertSameValue('UNKNOWN', $missingDpoRows['Temporären Admin-Vollzugriff verwalten']->status(), 'A missing canonical DPO group must keep grant management fail-closed.');

echo 'PermissionMatrixAccessAdapter tests passed' . PHP_EOL;
