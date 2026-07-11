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

class MatrixBuilder {
    public function __construct(
        private InventoryService $inventory,
        private GenericAppAdapter $genericApps,
        private CoreAdapter $core,
        private FilesAdapter $files,
        private SharingAdapter $sharing,
        private GroupFoldersAdapter $groupFolders,
        private FilesAccessControlAdapter $filesAccessControl
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

        return [
            'groups' => $this->inventory->groups(),
            'apps' => $this->inventory->enabledApps(),
            'rows' => $result->rows(),
            'warnings' => $result->warnings(),
            'unsupported_apps' => $result->unsupportedApps(),
            'adapter_status' => $result->adapterStatus(),
        ];
    }
}
