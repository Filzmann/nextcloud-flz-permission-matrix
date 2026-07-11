<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Service;

use OCA\BrPermissionMatrix\Adapter\AdapterResult;
use OCA\BrPermissionMatrix\Adapter\CoreAdapter;
use OCA\BrPermissionMatrix\Adapter\FilesAccessControlAdapter;
use OCA\BrPermissionMatrix\Adapter\FilesAdapter;
use OCA\BrPermissionMatrix\Adapter\GenericAppAdapter;
use OCA\BrPermissionMatrix\Adapter\GroupFoldersAdapter;
use OCA\BrPermissionMatrix\Adapter\SharingAdapter;

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
        private GroupCatalogService $groupCatalog
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

        $groups = $this->inventory->groups();

        return [
            'groups' => $groups,
            'group_catalog' => $this->groupCatalog->catalog($groups),
            'apps' => $this->inventory->enabledApps(),
            'rows' => $result->rows(),
            'warnings' => $result->warnings(),
            'unsupported_apps' => $result->unsupportedApps(),
            'adapter_status' => $result->adapterStatus(),
        ];
    }
}
