<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Adapter;

use OCA\BrPermissionMatrix\Model\MatrixRow;
use OCA\BrPermissionMatrix\Service\InventoryService;

class FilesAdapter implements PermissionAdapterInterface {
    public function __construct(
        private InventoryService $inventory
    ) {
    }

    public function supports(string $appId): bool {
        return in_array($appId, ['files', 'files_external'], true);
    }

    public function collect(): AdapterResult {
        $groups = $this->inventory->groups();
        $rows = [];
        $warnings = [];
        $status = [[
            'app_id' => 'files',
            'adapter' => self::class,
            'status' => 'IMPLEMENTED',
            'confidence' => 'medium',
            'warnings' => [],
        ]];

        $rows[] = new MatrixRow(
            'Other',
            'files',
            'Dateiinhalte',
            'Keine Dateiliste im Standardexport',
            'Datenschutzgrenze',
            'NEW',
            'adapter',
            'high',
            array_fill_keys($groups, 'n/a')
        );

        if ($this->inventory->isAppEnabled('files_external')) {
            $warning = 'Externe Speicher sind aktiv; konkrete Mounts und Credentials werden im MVP nicht exportiert.';
            $warnings[] = $warning;
            $rows[] = new MatrixRow(
                'ExternalStorage',
                'files_external',
                'Externe Speicher',
                'Mount- und Backend-Rechte',
                'Dateizugriff',
                'UNKNOWN',
                'adapter',
                'low',
                array_fill_keys($groups, '?'),
                [$warning]
            );
            $status[] = [
                'app_id' => 'files_external',
                'adapter' => self::class,
                'status' => 'PARTIAL',
                'confidence' => 'low',
                'warnings' => [$warning],
            ];
        }

        return new AdapterResult($rows, $warnings, [], $status);
    }

    public function getConfidence(): string {
        return 'medium';
    }

    public function getWarnings(): array {
        return [];
    }
}
