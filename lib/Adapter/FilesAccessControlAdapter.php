<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Adapter;

use OCA\FilzmannPermissionMatrix\Model\MatrixRow;
use OCA\FilzmannPermissionMatrix\Service\InventoryService;

class FilesAccessControlAdapter implements PermissionAdapterInterface {
    public function __construct(
        private InventoryService $inventory
    ) {
    }

    public function supports(string $appId): bool {
        return $appId === 'files_accesscontrol';
    }

    public function collect(): AdapterResult {
        if (!$this->inventory->isAppEnabled('files_accesscontrol')) {
            return AdapterResult::empty();
        }

        $warning = 'Files Access Control ist negativlogisch; konkrete Regelgruppen werden in Phase 3 ausgelesen und bis dahin nicht als erlaubt dargestellt.';

        return new AdapterResult([
            new MatrixRow(
                'FilesAccessControl',
                'files_accesscontrol',
                'Files Access Control',
                'Regelgruppen',
                'deny / Upload-Block',
                'UNKNOWN',
                'adapter',
                'low',
                array_fill_keys($this->inventory->groups(), '?'),
                [$warning]
            ),
        ], [$warning], [], [[
            'app_id' => 'files_accesscontrol',
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
