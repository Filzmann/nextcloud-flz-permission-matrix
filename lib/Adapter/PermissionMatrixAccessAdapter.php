<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Adapter;

use OCA\FlzPermissionMatrix\Model\AccessCondition;
use OCA\FlzPermissionMatrix\Model\AccessRule;
use OCA\FlzPermissionMatrix\Model\MatrixRow;
use OCA\FlzPermissionMatrix\Service\ConfigService;
use OCA\FlzPermissionMatrix\Service\InventoryService;

/**
 * Zweck: Liest die gruppenbezogenen View-/Manage-Rechte der Berechtigungsmatrix aus ihrer kanonischen Konfiguration.
 *
 * Vertrag:
 * - Verwaltung umfasst Lesen, Viewer erhalten jedoch keine Verwaltungsrechte.
 * - Native Nextcloud-Admins benoetigen zusaetzlich eine aktive app-lokale Adminfreigabe.
 * - Veraltete konfigurierte Gruppen fuehren zu UNKNOWN statt zu einer erfundenen Freigabe.
 */
final class PermissionMatrixAccessAdapter implements PermissionAdapterInterface {
    private const PRIVACY_OFFICER_GROUP = 'Datenschutzbeauftragte';

    public function __construct(
        private InventoryService $inventory,
        private ConfigService $config
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
        $configuredView = array_values(array_unique([
            ...$this->config->adminGroups(),
            ...$this->config->viewerGroups(),
        ]));
        $configuredManage = array_values(array_unique($this->config->adminGroups()));
        $missingView = array_values(array_diff($configuredView, $groups));
        $missingManage = array_values(array_diff($configuredManage, $groups));
        $missingPrivacyOfficer = !in_array(self::PRIVACY_OFFICER_GROUP, $groups, true);
        $viewGroups = array_values(array_intersect($configuredView, $groups));
        $manageGroups = array_values(array_intersect($configuredManage, $groups));
        $warnings = [];
        if ($missingView !== []) {
            $warnings[] = 'Mindestens eine konfigurierte View-/Admin-Gruppe ist nicht im Gruppeninventar vorhanden.';
        }
        if ($missingManage !== []) {
            $warnings[] = 'Mindestens eine konfigurierte Admin-Gruppe ist nicht im Gruppeninventar vorhanden.';
        }
        if ($missingPrivacyOfficer) {
            $warnings[] = 'Die kanonische Gruppe Datenschutzbeauftragte ist nicht im Gruppeninventar vorhanden.';
        }

        $viewCells = array_fill_keys($groups, '-');
        foreach ($viewGroups as $group) {
            $viewCells[$group] = 'X';
        }
        $manageCells = array_fill_keys($groups, '-');
        foreach ($manageGroups as $group) {
            $manageCells[$group] = 'A';
        }
        $adminAccessCells = array_fill_keys($groups, '-');
        if (!$missingPrivacyOfficer) {
            $adminAccessCells[self::PRIVACY_OFFICER_GROUP] = 'A';
        }

        $temporaryAdminCondition = AccessCondition::all([
            AccessCondition::nextcloudAdmin(),
            AccessCondition::temporaryAppAdminGrant(),
        ]);
        $viewConditions = [
            ...array_map(static fn(string $group): AccessCondition => AccessCondition::group($group), $viewGroups),
            $temporaryAdminCondition,
        ];
        $manageConditions = [
            ...array_map(static fn(string $group): AccessCondition => AccessCondition::group($group), $manageGroups),
            $temporaryAdminCondition,
        ];

        $viewRules = [new AccessRule(
            'matrix.view',
            'allow',
            'app:flz_permission_matrix',
            AccessCondition::any($viewConditions),
            'flz_permission_matrix:AccessService::canViewUserId',
            $missingView === [] ? 'high' : 'low'
        )];
        $manageRules = [new AccessRule(
            'matrix.manage',
            'allow',
            'app:flz_permission_matrix',
            AccessCondition::any($manageConditions),
            'flz_permission_matrix:AccessService::canManageUserId',
            $missingManage === [] ? 'high' : 'low'
        )];
        $adminAccessRules = [new AccessRule(
            'matrix.temporary-admin-access.manage',
            'allow',
            'app:flz_permission_matrix',
            AccessCondition::group(self::PRIVACY_OFFICER_GROUP),
            'flz_permission_matrix:TemporaryAdminAccessService::canManage',
            $missingPrivacyOfficer ? 'low' : 'high'
        )];

        return new AdapterResult([
            new MatrixRow(
                'AppPermission',
                'flz_permission_matrix',
                'Matrix ansehen',
                'Konfigurierte Viewer-/Admin-Gruppen; Nextcloud-Admins nur mit aktiver app-lokaler Freigabe',
                'Lesen',
                $missingView === [] ? 'NEW' : 'UNKNOWN',
                'flz_permission_matrix:AccessService::canViewUserId',
                $missingView === [] ? 'high' : 'low',
                $viewCells,
                $missingView === [] ? [] : [$warnings[0]],
                $viewRules
            ),
            new MatrixRow(
                'AppPermission',
                'flz_permission_matrix',
                'Matrix verwalten',
                'Konfigurierte Admin-Gruppen; Nextcloud-Admins nur mit aktiver app-lokaler Freigabe',
                'Administrieren',
                $missingManage === [] ? 'NEW' : 'UNKNOWN',
                'flz_permission_matrix:AccessService::canManageUserId',
                $missingManage === [] ? 'high' : 'low',
                $manageCells,
                $missingManage === [] ? [] : ['Mindestens eine konfigurierte Admin-Gruppe fehlt.'],
                $manageRules
            ),
            new MatrixRow(
                'AppPermission',
                'flz_permission_matrix',
                'Temporären Admin-Vollzugriff verwalten',
                'Ausschließlich die kanonische Datenschutzgruppe erteilt, liest und widerruft Freigaben für aktuelle Nextcloud-Administrationskonten',
                'Administrieren',
                $missingPrivacyOfficer ? 'UNKNOWN' : 'NEW',
                'flz_permission_matrix:TemporaryAdminAccessService::canManage',
                $missingPrivacyOfficer ? 'low' : 'high',
                $adminAccessCells,
                $missingPrivacyOfficer ? ['Die kanonische Gruppe Datenschutzbeauftragte fehlt.'] : [],
                $adminAccessRules
            ),
        ], $warnings, [], [[
            'app_id' => 'flz_permission_matrix',
            'adapter' => self::class,
            'status' => $warnings === [] ? 'IMPLEMENTED' : 'PARTIAL',
            'confidence' => $warnings === [] ? 'high' : 'low',
            'warnings' => $warnings,
        ]]);
    }

    public function getConfidence(): string {
        return 'high';
    }

    public function getWarnings(): array {
        return [];
    }
}
