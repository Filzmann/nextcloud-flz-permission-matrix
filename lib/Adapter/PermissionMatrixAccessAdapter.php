<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Adapter;

use OCA\FlzPermissionMatrix\Model\AccessCondition;
use OCA\FlzPermissionMatrix\Model\AccessRule;
use OCA\FlzPermissionMatrix\Model\MatrixRow;
use OCA\FlzPermissionMatrix\Service\BrMembershipService;
use OCA\FlzPermissionMatrix\Service\InventoryService;

/** Projiziert die sechs festen App-Rechte aus BR-Identität, DPO-Rolle und temporärer Adminfreigabe. */
final class PermissionMatrixAccessAdapter implements PermissionAdapterInterface {
    private const PRIVACY_OFFICER_GROUP = 'Datenschutzbeauftragte';

    public function __construct(
        private InventoryService $inventory,
        private BrMembershipService $brMembership,
    ) {
    }

    public function supports(string $appId): bool {
        return $appId === 'flz_permission_matrix';
    }

    public function collect(): AdapterResult {
        if (!$this->inventory->isAppEnabled('flz_permission_matrix')) {
            return AdapterResult::empty();
        }

        $groups = $this->inventory->groups();
        $brGroupId = $this->brMembership->memberGroupId();
        $hasBrGroup = $brGroupId !== null && in_array($brGroupId, $groups, true);
        $hasPrivacyOfficer = in_array(self::PRIVACY_OFFICER_GROUP, $groups, true);
        $warnings = [];
        if ($brGroupId === null) {
            $warnings[] = 'Die kanonische BR-Mitgliedschaft ist nicht verfügbar; BR-Rechte bleiben UNKNOWN.';
        } elseif (!$hasBrGroup) {
            $warnings[] = 'Die kanonische BR-Mitgliedergruppe fehlt im Gruppeninventar; BR-Rechte bleiben UNKNOWN.';
        }
        if (!$hasPrivacyOfficer) {
            $warnings[] = 'Die kanonische Gruppe Datenschutzbeauftragte fehlt im Gruppeninventar.';
        }

        $temporaryAdmin = AccessCondition::all([
            AccessCondition::nextcloudAdmin(),
            AccessCondition::temporaryAppAdminGrant(),
        ]);
        $viewConditions = [$temporaryAdmin];
        if ($hasBrGroup) $viewConditions[] = AccessCondition::group($brGroupId);
        if ($hasPrivacyOfficer) $viewConditions[] = AccessCondition::group(self::PRIVACY_OFFICER_GROUP);
        $manageConditions = [$temporaryAdmin];
        if ($hasPrivacyOfficer) $manageConditions[] = AccessCondition::group(self::PRIVACY_OFFICER_GROUP);

        $viewComplete = $hasBrGroup && $hasPrivacyOfficer;
        $manageComplete = $hasPrivacyOfficer;
        $rows = [
            $this->row('Matrix ansehen', 'matrix.view', 'Lesen', false, $groups, $brGroupId, $hasBrGroup, $hasPrivacyOfficer, $viewConditions, $viewComplete, 'AccessService::canViewUserId'),
            $this->row('Exporte erzeugen', 'matrix.export', 'Exportieren', false, $groups, $brGroupId, $hasBrGroup, $hasPrivacyOfficer, $viewConditions, $viewComplete, 'AccessService::canViewUserId'),
            $this->row('Manuelle Scans starten', 'matrix.scan', 'Ausführen', false, $groups, $brGroupId, $hasBrGroup, $hasPrivacyOfficer, $viewConditions, $viewComplete, 'AccessService::canScanUserId'),
            $this->row('Baseline festlegen', 'matrix.baseline.manage', 'Administrieren', true, $groups, null, false, $hasPrivacyOfficer, $manageConditions, $manageComplete, 'AccessService::canManageUserId'),
            $this->row('App-Konfiguration ändern', 'matrix.config.manage', 'Administrieren', true, $groups, null, false, $hasPrivacyOfficer, $manageConditions, $manageComplete, 'AccessService::canManageUserId'),
            $this->grantManagementRow($groups, $hasPrivacyOfficer),
        ];

        return new AdapterResult($rows, $warnings, [], [[
            'app_id' => 'flz_permission_matrix',
            'adapter' => self::class,
            'status' => $warnings === [] ? 'IMPLEMENTED' : 'PARTIAL',
            'confidence' => $warnings === [] ? 'high' : 'low',
            'warnings' => $warnings,
        ]]);
    }

    /** @param string[] $groups @param AccessCondition[] $conditions */
    private function row(
        string $label,
        string $permission,
        string $permissionType,
        bool $administrative,
        array $groups,
        ?string $brGroupId,
        bool $hasBrGroup,
        bool $hasPrivacyOfficer,
        array $conditions,
        bool $complete,
        string $sourceMethod,
    ): MatrixRow {
        $cells = array_fill_keys($groups, '-');
        if (!$administrative && $hasBrGroup && $brGroupId !== null) $cells[$brGroupId] = 'X';
        if ($hasPrivacyOfficer) $cells[self::PRIVACY_OFFICER_GROUP] = $administrative ? 'A' : 'X';
        $source = 'flz_permission_matrix:' . $sourceMethod;
        $warning = $complete ? [] : ['Mindestens eine kanonische Berechtigungsidentität ist nicht auflösbar.'];

        return new MatrixRow(
            'AppPermission',
            'flz_permission_matrix',
            $label,
            $administrative
                ? 'Datenschutzbeauftragte; Nextcloud-Admins nur mit aktiver app-lokaler Freigabe'
                : 'Kanonische BR-Mitglieder und Datenschutzbeauftragte; Nextcloud-Admins nur mit aktiver app-lokaler Freigabe',
            $permissionType,
            $complete ? 'NEW' : 'UNKNOWN',
            $source,
            $complete ? 'high' : 'low',
            $cells,
            $warning,
            [new AccessRule(
                $permission,
                'allow',
                'app:flz_permission_matrix',
                AccessCondition::any($conditions),
                $source,
                $complete ? 'high' : 'low',
            )],
        );
    }

    /** @param string[] $groups */
    private function grantManagementRow(array $groups, bool $hasPrivacyOfficer): MatrixRow {
        $cells = array_fill_keys($groups, '-');
        if ($hasPrivacyOfficer) $cells[self::PRIVACY_OFFICER_GROUP] = 'A';

        return new MatrixRow(
            'AppPermission',
            'flz_permission_matrix',
            'Temporären Admin-Vollzugriff verwalten',
            'Ausschließlich Datenschutzbeauftragte erteilen, lesen und widerrufen Freigaben',
            'Administrieren',
            $hasPrivacyOfficer ? 'NEW' : 'UNKNOWN',
            'flz_permission_matrix:TemporaryAdminAccessService::canManage',
            $hasPrivacyOfficer ? 'high' : 'low',
            $cells,
            $hasPrivacyOfficer ? [] : ['Die kanonische Gruppe Datenschutzbeauftragte fehlt.'],
            [new AccessRule(
                'matrix.temporary-admin-access.manage',
                'allow',
                'app:flz_permission_matrix',
                AccessCondition::group(self::PRIVACY_OFFICER_GROUP),
                'flz_permission_matrix:TemporaryAdminAccessService::canManage',
                $hasPrivacyOfficer ? 'high' : 'low',
            )],
        );
    }

    public function getConfidence(): string { return 'high'; }
    public function getWarnings(): array { return []; }
}
