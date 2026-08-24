<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Service;

/**
 * Version-bound, read-only projection of the official Groupfolders 22.x DTOs.
 *
 * Groupfolders exposes this through an OCA namespace rather than a public OCP
 * API. The narrow source boundary, strict version gate and release contract
 * check therefore form one inseparable compatibility control.
 */
class NextcloudGroupFoldersSource implements GroupFoldersSourceInterface {
    private const KNOWN_PERMISSION_MASK = 31;
    private const SUPPORTED_MAJOR = 22;

    public function __construct(private GroupFoldersManagerProviderInterface $managerProvider) {
    }

    public function folders(string $appId, string $appVersion): array {
        if ($appId !== 'groupfolders') {
            return $this->unknown('Die historische App files_groupfolders besitzt keinen freigegebenen Quellvertrag.');
        }
        if (!$this->supportsVersion($appVersion)) {
            return $this->unknown(sprintf(
                'Groupfolders-Version %s ist nicht durch den read-only Adapter freigegeben.',
                $this->safeVersion($appVersion)
            ));
        }

        try {
            $manager = $this->managerProvider->manager();
            if (!method_exists($manager, 'getAllFolders')) {
                return $this->unknown('Der freigegebene Groupfolders-Quellvertrag ist zur Laufzeit nicht verfügbar.');
            }
            $nativeFolders = $manager->getAllFolders();
        } catch (\Throwable) {
            return $this->unknown('Team-Folder-Rechte konnten nicht über den freigegebenen read-only Quellvertrag gelesen werden.');
        }
        if (!is_array($nativeFolders)) {
            return $this->unknown('Der Groupfolders-Quellvertrag lieferte einen unbekannten Ergebnistyp.');
        }

        $folders = [];
        $warnings = [];
        $complete = true;
        $references = [];
        foreach ($nativeFolders as $nativeFolder) {
            $normalized = $this->normalizeFolder($nativeFolder, $appId, $warnings, $complete);
            if ($normalized === null) {
                continue;
            }
            if (isset($references[$normalized['reference']])) {
                $complete = false;
                $warnings[] = 'Die Team-Folder-Quelle lieferte eine mehrdeutige Ordnerreferenz.';
                continue;
            }
            $references[$normalized['reference']] = true;
            $folders[] = $normalized;
        }

        return [
            'folders' => $folders,
            'complete' => $complete,
            'warnings' => array_values(array_unique($warnings)),
            'source' => 'nextcloud-groupfolders:22:FolderManager::getAllFolders',
        ];
    }

    private function supportsVersion(string $version): bool {
        return preg_match('/^' . self::SUPPORTED_MAJOR . '\\.\d+\.\d+(?:[-+].*)?$/', trim($version)) === 1;
    }

    private function normalizeFolder(mixed $nativeFolder, string $appId, array &$warnings, bool &$complete): ?array {
        if (!is_object($nativeFolder)) {
            $complete = false;
            $warnings[] = 'Die Team-Folder-Quelle lieferte einen unbekannten Ordnerdatensatz.';

            return null;
        }

        try {
            $id = $nativeFolder->id;
            $acl = $nativeFolder->acl;
            $mappings = $nativeFolder->groups;
        } catch (\Throwable) {
            $complete = false;
            $warnings[] = 'Ein Team-Folder entsprach nicht dem freigegebenen Quellvertrag.';

            return null;
        }
        if ((!is_int($id) && !is_string($id)) || trim((string)$id) === '' || !is_bool($acl) || !is_array($mappings)) {
            $complete = false;
            $warnings[] = 'Ein Team-Folder enthielt unplausible Vertragswerte.';

            return null;
        }

        $groups = [];
        foreach ($mappings as $mappingId => $mapping) {
            if (!is_array($mapping)) {
                $complete = false;
                $warnings[] = 'Eine Team-Folder-Zuordnung entsprach nicht dem freigegebenen Quellvertrag.';
                continue;
            }
            if (($mapping['type'] ?? null) !== 'group') {
                $complete = false;
                $warnings[] = 'Mindestens eine Team-/Circle-Zuordnung kann nicht als Nextcloud-Gruppe abgebildet werden.';
                continue;
            }
            $groupId = trim((string)$mappingId);
            $permissions = $mapping['permissions'] ?? null;
            if ($groupId === '' || !is_int($permissions) || $permissions < 0 || ($permissions & ~self::KNOWN_PERMISSION_MASK) !== 0) {
                $complete = false;
                $warnings[] = 'Eine Team-Folder-Gruppenzuordnung enthielt unbekannte Permission-Werte.';
                continue;
            }
            if (array_key_exists($groupId, $groups) && $groups[$groupId] !== $permissions) {
                $complete = false;
                $warnings[] = 'Die Team-Folder-Quelle lieferte widersprüchliche Rechte für dieselbe Gruppe.';
                continue;
            }
            $groups[$groupId] = $permissions;
        }
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        if ($acl) {
            $complete = false;
            $warnings[] = 'Erweiterte ACLs innerhalb mindestens eines Team-Folders sind nicht abgebildet.';
        }

        return [
            'reference' => substr(hash('sha256', $appId . ':' . (string)$id), 0, 12),
            'groups' => $groups,
            'advanced_permissions' => $acl,
        ];
    }

    private function safeVersion(string $version): string {
        $version = trim($version);

        return preg_match('/^[0-9A-Za-z.+_-]{1,64}$/', $version) === 1 ? $version : 'unbekannt';
    }

    private function unknown(string $warning): array {
        return [
            'folders' => [],
            'complete' => false,
            'warnings' => [$warning],
            'source' => 'nextcloud-groupfolders:unsupported',
        ];
    }
}
