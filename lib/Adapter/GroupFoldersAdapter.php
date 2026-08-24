<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Adapter;

use OCA\FilzmannPermissionMatrix\Model\AccessCondition;
use OCA\FilzmannPermissionMatrix\Model\AccessRule;
use OCA\FilzmannPermissionMatrix\Model\MatrixRow;
use OCA\FilzmannPermissionMatrix\Service\GroupFoldersSourceInterface;
use OCA\FilzmannPermissionMatrix\Service\InventoryService;

class GroupFoldersAdapter implements PermissionAdapterInterface {
    private const KNOWN_PERMISSION_MASK = 31;

    public function __construct(
        private InventoryService $inventory,
        private GroupFoldersSourceInterface $source
    ) {
    }

    public function supports(string $appId): bool {
        return in_array($appId, ['groupfolders', 'files_groupfolders'], true);
    }

    public function collect(): AdapterResult {
        $appId = $this->inventory->isAppEnabled('groupfolders') ? 'groupfolders' : ($this->inventory->isAppEnabled('files_groupfolders') ? 'files_groupfolders' : '');
        if ($appId === '') {
            return AdapterResult::empty();
        }

        $summary = $this->inventory->appSummary($appId);
        $version = (string)($summary['version'] ?? 'unknown');
        try {
            $sourceResult = $this->source->folders($appId, $version);
        } catch (\Throwable) {
            $sourceResult = [
                'folders' => [],
                'complete' => false,
                'warnings' => ['Team-Folder-Rechte konnten nicht über die read-only Quelle gelesen werden.'],
                'source' => 'nextcloud-groupfolders:unavailable',
            ];
        }

        $groups = $this->inventory->groups();
        $complete = ($sourceResult['complete'] ?? false) === true;
        $warnings = is_array($sourceResult['warnings'] ?? null) ? array_map('strval', $sourceResult['warnings']) : [];
        $source = (string)($sourceResult['source'] ?? 'nextcloud-groupfolders:unavailable');
        $foldersValue = $sourceResult['folders'] ?? null;
        if (!is_array($foldersValue)) {
            $complete = false;
            $warnings[] = 'Die Team-Folder-Quelle lieferte keine auswertbare Ordnerliste.';
        }
        $folders = is_array($foldersValue) ? $foldersValue : [];
        $validated = [];
        foreach ($folders as $folder) {
            if (!is_array($folder)) {
                $complete = false;
                $warnings[] = 'Die Team-Folder-Quelle lieferte einen unbekannten Datensatztyp.';
                continue;
            }
            $reference = (string)($folder['reference'] ?? '');
            $mappings = is_array($folder['groups'] ?? null) ? $folder['groups'] : [];
            if (preg_match('/^[a-f0-9]{12}$/', $reference) !== 1) {
                $complete = false;
                $warnings[] = 'Mindestens eine Team-Folder-Referenz war ungültig.';
                continue;
            }
            $advancedPermissions = $folder['advanced_permissions'] ?? null;
            if (!is_bool($advancedPermissions)) {
                $complete = false;
                $warnings[] = 'Mindestens ein Team-Folder enthielt keinen eindeutigen ACL-Status.';
            } elseif ($advancedPermissions) {
                $complete = false;
                $warnings[] = 'Erweiterte ACLs innerhalb mindestens eines Team-Folders sind nicht abgebildet.';
            }
            $validMappings = [];
            foreach ($mappings as $groupId => $permissions) {
                $groupId = (string)$groupId;
                if (!in_array($groupId, $groups, true)) {
                    $complete = false;
                    $warnings[] = 'Mindestens eine Team-Folder-Zielgruppe fehlt im aktuellen Gruppeninventar.';
                    continue;
                }
                if (!is_int($permissions) || $permissions < 0 || ($permissions & ~self::KNOWN_PERMISSION_MASK) !== 0) {
                    $complete = false;
                    $warnings[] = 'Mindestens eine Team-Folder-Zuordnung enthielt unbekannte Permission-Bits.';
                    continue;
                }
                $validMappings[$groupId] = $permissions;
            }
            $validated[] = ['reference' => $reference, 'groups' => $validMappings];
        }

        $warnings = array_values(array_unique($warnings));
        $rows = [];
        foreach ($validated as $folder) {
            $rows = [...$rows, ...$this->permissionRows($appId, $groups, $folder, $complete, $source, $warnings)];
        }
        if ($rows === []) {
            $rows[] = new MatrixRow(
                'TeamFolder',
                $appId,
                $complete ? 'Keine Team-Folders gefunden' : 'Team-Folder-Rechte nicht auslesbar',
                'Pfade und Dateiinhalte ausgeschlossen',
                'Bestandsstatus',
                $complete ? 'NEW' : 'UNKNOWN',
                $source,
                $complete ? 'high' : 'low',
                array_fill_keys($groups, $complete ? 'n/a' : '?'),
                $complete ? [] : $warnings
            );
        }

        return new AdapterResult($rows, $warnings, [], [[
            'app_id' => $appId,
            'adapter' => self::class,
            'status' => $complete ? 'IMPLEMENTED' : 'PARTIAL',
            'confidence' => $complete ? 'high' : 'low',
            'warnings' => $warnings,
        ]]);
    }

    private function permissionRows(string $appId, array $inventoryGroups, array $folder, bool $complete, string $source, array $warnings): array {
        $definitions = [
            ['Lesen', 1, 'R', 'files.read'],
            ['Ändern', 2, 'W', 'files.update'],
            ['Ausführen', 0, 'n/a', 'files.execute'],
            ['Löschen', 8, 'D', 'files.delete'],
            ['Erstellen', 4, 'C', 'files.create'],
            ['Teilen', 16, 'S', 'files.share'],
        ];
        $rows = [];
        foreach ($definitions as [$label, $bit, $symbol, $permission]) {
            $cells = array_fill_keys($inventoryGroups, '-');
            $rules = [];
            foreach ($folder['groups'] as $groupId => $permissions) {
                $granted = $bit !== 0 && ($permissions & $bit) === $bit;
                $cells[$groupId] = $bit === 0 ? 'n/a' : ($granted ? $symbol : '-');
                if ($granted) {
                    $rules[] = new AccessRule(
                        $permission,
                        'allow',
                        'team-folder:' . $folder['reference'],
                        AccessCondition::group($groupId),
                        $source,
                        $complete ? 'high' : 'low'
                    );
                }
            }
            if ($bit === 0) {
                $cells = array_fill_keys($inventoryGroups, 'n/a');
            }
            $rows[] = new MatrixRow(
                'TeamFolder',
                $appId,
                'Team-Folder ' . $folder['reference'],
                'Root-Rechte · Pfad und Dateiinhalte ausgeschlossen',
                $label,
                $complete ? 'NEW' : 'UNKNOWN',
                $source,
                $complete ? 'high' : 'low',
                $cells,
                $complete ? [] : $warnings,
                $rules
            );
        }

        return $rows;
    }

    public function getConfidence(): string {
        return 'low';
    }

    public function getWarnings(): array {
        return [];
    }
}
