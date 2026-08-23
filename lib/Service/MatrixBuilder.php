<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Service;

use OCA\FilzmannPermissionMatrix\Adapter\AdapterResult;
use OCA\FilzmannPermissionMatrix\Adapter\CoreAdapter;
use OCA\FilzmannPermissionMatrix\Adapter\FilesAccessControlAdapter;
use OCA\FilzmannPermissionMatrix\Adapter\FilesAdapter;
use OCA\FilzmannPermissionMatrix\Adapter\GenericAppAdapter;
use OCA\FilzmannPermissionMatrix\Adapter\GroupFoldersAdapter;
use OCA\FilzmannPermissionMatrix\Adapter\PermissionMatrixAccessAdapter;
use OCA\FilzmannPermissionMatrix\Adapter\SharingAdapter;

/**
 * Zweck: Orchestriert alle Berechtigungsadapter zu einem noch unbewerteten Matrix-Rohbau.
 *
 * Zusammenspiel:
 * - InventoryService liefert Gruppen und Apps, AdapterResult vereinigt die Adapterdaten und
 *   GroupCatalogService ergaenzt die verlustfreie Gruppendarstellung fuer UI und Exporte.
 * - ScannerService fuegt anschliessend Baseline-Bewertung, Summary und Persistenz hinzu.
 */
class MatrixBuilder {
    public function __construct(
        private InventoryService $inventory,
        private GenericAppAdapter $genericApps,
        private CoreAdapter $core,
        private FilesAdapter $files,
        private SharingAdapter $sharing,
        private GroupFoldersAdapter $groupFolders,
        private FilesAccessControlAdapter $filesAccessControl,
        private PermissionMatrixAccessAdapter $permissionMatrixAccess,
        private GroupCatalogService $groupCatalog,
        private OrganizationSnapshotService $organization
    ) {
    }

    public function build(): array {
        $result = AdapterResult::empty()
            ->merge($this->genericApps->collect())
            ->merge($this->core->collect())
            ->merge($this->files->collect())
            ->merge($this->sharing->collect())
            ->merge($this->groupFolders->collect())
            ->merge($this->filesAccessControl->collect());
        $result = $result->merge($this->permissionMatrixAccess->collect());

        $groups = $this->inventory->groups();
        $organization = $this->organization->snapshot();
        $warnings = $result->warnings();
        if (is_string($organization['warning'] ?? null) && $organization['warning'] !== '') {
            $warnings[] = $organization['warning'];
        }

        return [
            'groups' => $groups,
            'group_catalog' => $this->groupCatalog->catalog($groups, $organization),
            'apps' => $this->inventory->enabledApps(),
            'rows' => $result->rows(),
            'warnings' => array_values(array_unique($warnings)),
            'unsupported_apps' => $result->unsupportedApps(),
            'adapter_status' => $result->adapterStatus(),
            'organization_snapshot' => $organization,
        ];
    }
}
