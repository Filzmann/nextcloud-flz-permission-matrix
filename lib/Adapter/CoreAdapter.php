<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Adapter;

use OCA\BrPermissionMatrix\Model\MatrixRow;
use OCA\BrPermissionMatrix\Service\InventoryService;

class CoreAdapter implements PermissionAdapterInterface {
    public function __construct(
        private InventoryService $inventory
    ) {
    }

    public function supports(string $appId): bool {
        return $appId === 'core';
    }

    public function collect(): AdapterResult {
        $groups = $this->inventory->groups();
        $adminCells = [];
        foreach ($groups as $group) {
            $adminCells[$group] = $group === 'admin' ? 'A' : '-';
        }

        $subAdminCells = array_fill_keys($groups, '?');
        $warnings = [
            'Gruppenadministratoren sind personenbezogene Delegationen und werden im Standardmodus nicht als erlaubte Gruppenrechte ausgewiesen.',
        ];

        return new AdapterResult([
            new MatrixRow('Admin', 'core', 'Superadmin-Gruppe', 'Globale Administration', 'Adminrecht', 'NEW', 'core-group-manager', 'high', $adminCells),
            new MatrixRow('Admin', 'core', 'Gruppenadministratoren', 'Delegierte Gruppenadministration', 'Adminrecht', 'UNKNOWN', 'core-subadmin', 'low', $subAdminCells, $warnings),
        ], $warnings, [], [[
            'app_id' => 'core',
            'adapter' => self::class,
            'status' => 'IMPLEMENTED',
            'confidence' => 'medium',
            'warnings' => $warnings,
        ]]);
    }

    public function getConfidence(): string {
        return 'medium';
    }

    public function getWarnings(): array {
        return [];
    }
}
