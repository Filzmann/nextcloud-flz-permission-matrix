<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Adapter;

use OCA\BrPermissionMatrix\Model\AccessCondition;
use OCA\BrPermissionMatrix\Model\AccessRule;
use OCA\BrPermissionMatrix\Model\MatrixRow;
use OCA\BrPermissionMatrix\Service\ConfigService;
use OCA\BrPermissionMatrix\Service\InventoryService;

/**
 * Zweck: Liest die gruppenbezogenen View-/Manage-Rechte der Berechtigungsmatrix aus ihrer kanonischen Konfiguration.
 *
 * Vertrag:
 * - Verwaltung umfasst Lesen, Viewer erhalten jedoch keine Verwaltungsrechte.
 * - Nextcloud-Admins bleiben entsprechend AccessService fuer beide Funktionen berechtigt.
 * - Veraltete konfigurierte Gruppen fuehren zu UNKNOWN statt zu einer erfundenen Freigabe.
 */
final class PermissionMatrixAccessAdapter implements PermissionAdapterInterface {
    public function __construct(
        private InventoryService $inventory,
        private ConfigService $config
    ) {
    }

    public function supports(string $appId): bool {
        return $appId === 'br_permission_matrix';
    }

    public function collect(): AdapterResult {
        if (!$this->inventory->isAppEnabled('br_permission_matrix')) {
            return AdapterResult::empty();
        }

        $groups = $this->inventory->groups();
        $nativeAdminGroups = in_array('admin', $groups, true) ? ['admin'] : [];
        $configuredView = array_values(array_unique([
            ...$this->config->adminGroups(),
            ...$this->config->viewerGroups(),
        ]));
        $configuredManage = array_values(array_unique($this->config->adminGroups()));
        $missingView = array_values(array_diff($configuredView, $groups));
        $missingManage = array_values(array_diff($configuredManage, $groups));
        $viewGroups = array_values(array_intersect([...$configuredView, ...$nativeAdminGroups], $groups));
        $manageGroups = array_values(array_intersect([...$configuredManage, ...$nativeAdminGroups], $groups));
        $warnings = [];
        if ($missingView !== []) {
            $warnings[] = 'Mindestens eine konfigurierte View-/Admin-Gruppe ist nicht im Gruppeninventar vorhanden.';
        }
        if ($missingManage !== []) {
            $warnings[] = 'Mindestens eine konfigurierte Admin-Gruppe ist nicht im Gruppeninventar vorhanden.';
        }

        $viewCells = array_fill_keys($groups, '-');
        foreach ($viewGroups as $group) {
            $viewCells[$group] = 'X';
        }
        $manageCells = array_fill_keys($groups, '-');
        foreach ($manageGroups as $group) {
            $manageCells[$group] = 'A';
        }

        $viewRules = $viewGroups === [] ? [] : [new AccessRule(
            'matrix.view',
            'allow',
            'app:br_permission_matrix',
            AccessCondition::any(array_map(static fn(string $group): AccessCondition => AccessCondition::group($group), $viewGroups)),
            'br_permission_matrix:AccessService::canViewUserId',
            $missingView === [] ? 'high' : 'low'
        )];
        $manageRules = $manageGroups === [] ? [] : [new AccessRule(
            'matrix.manage',
            'allow',
            'app:br_permission_matrix',
            AccessCondition::any(array_map(static fn(string $group): AccessCondition => AccessCondition::group($group), $manageGroups)),
            'br_permission_matrix:AccessService::canManageUserId',
            $missingManage === [] ? 'high' : 'low'
        )];

        return new AdapterResult([
            new MatrixRow(
                'AppPermission',
                'br_permission_matrix',
                'Matrix ansehen',
                'Konfigurierte Viewer- und Admin-Gruppen; Nextcloud-Admins',
                'Lesen',
                $missingView === [] ? 'NEW' : 'UNKNOWN',
                'br_permission_matrix:AccessService::canViewUserId',
                $missingView === [] ? 'high' : 'low',
                $viewCells,
                $missingView === [] ? [] : [$warnings[0]],
                $viewRules
            ),
            new MatrixRow(
                'AppPermission',
                'br_permission_matrix',
                'Matrix verwalten',
                'Konfigurierte Admin-Gruppen; Nextcloud-Admins',
                'Administrieren',
                $missingManage === [] ? 'NEW' : 'UNKNOWN',
                'br_permission_matrix:AccessService::canManageUserId',
                $missingManage === [] ? 'high' : 'low',
                $manageCells,
                $missingManage === [] ? [] : ['Mindestens eine konfigurierte Admin-Gruppe fehlt.'],
                $manageRules
            ),
        ], $warnings, [], [[
            'app_id' => 'br_permission_matrix',
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
