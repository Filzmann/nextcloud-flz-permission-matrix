<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Adapter;

use OCA\FilzmannPermissionMatrix\Model\MatrixRow;
use OCA\FilzmannPermissionMatrix\Service\InventoryService;

class GroupFoldersAdapter implements PermissionAdapterInterface {
    public function __construct(
        private InventoryService $inventory
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

        $warning = 'Team-/Group-Folders sind aktiv; konkrete Folder-Rechte werden in Phase 3 adaptergestuetzt ausgelesen.';

        return new AdapterResult([
            new MatrixRow(
                'TeamFolder',
                $appId,
                'Team-/Group-Folders',
                'Detailrechte',
                'Dateirechte R/W/C/D/S',
                'UNKNOWN',
                'adapter',
                'low',
                array_fill_keys($this->inventory->groups(), '?'),
                [$warning]
            ),
        ], [$warning], [], [[
            'app_id' => $appId,
            'adapter' => self::class,
            'status' => 'PARTIAL',
            'confidence' => 'low',
            'warnings' => [$warning],
        ]]);
    }

    public function getConfidence(): string {
        return 'low';
    }

    public function getWarnings(): array {
        return [];
    }
}
