<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Adapter;

use OCA\BrPermissionMatrix\Model\MatrixRow;
use OCA\BrPermissionMatrix\Service\InventoryService;
use OCP\IAppConfig;

class SharingAdapter implements PermissionAdapterInterface {
    public function __construct(
        private InventoryService $inventory,
        private IAppConfig $appConfig
    ) {
    }

    public function supports(string $appId): bool {
        return $appId === 'files_sharing';
    }

    public function collect(): AdapterResult {
        $groups = $this->inventory->groups();
        $rows = [];
        $enabled = $this->inventory->isAppEnabled('files_sharing');

        $policies = [
            ['Dateifreigaben global', 'shareapi_enabled', true, 'Sharing insgesamt'],
            ['Gruppen-Sharing', 'shareapi_allow_group_sharing', true, 'Freigabe an Gruppen'],
            ['Link-Sharing', 'shareapi_allow_links', true, 'Oeffentliche Links'],
            ['Resharing', 'shareapi_allow_resharing', true, 'Weiterfreigabe'],
            ['Passwortpflicht fuer Links', 'shareapi_enforce_links_password', false, 'Schutzpflicht'],
            ['Ablaufdatum fuer Links', 'shareapi_default_expire_date', false, 'Ablaufdatum'],
            ['Ablaufdatum erzwungen', 'shareapi_enforce_expire_date', false, 'Ablaufdatum'],
        ];

        foreach ($policies as [$label, $key, $default, $type]) {
            $allowed = $enabled && $this->appConfig->getValueBool('core', $key, $default);
            $value = $allowed ? 'allow' : 'deny';
            $rows[] = new MatrixRow(
                'SharingPolicy',
                'files_sharing',
                $label,
                'Globale Policy',
                $type,
                'NEW',
                'core-app-config',
                'high',
                array_fill_keys($groups, $value)
            );
        }

        $metadataValue = 'n/a';
        $rows[] = new MatrixRow(
            'SharingPolicy',
            'files_sharing',
            'Einzelne Share-Metadaten',
            'Standardmodus',
            'Exportgrenze',
            'NEW',
            'app-config',
            'high',
            array_fill_keys($groups, $metadataValue)
        );

        return new AdapterResult($rows, [], [], [[
            'app_id' => 'files_sharing',
            'adapter' => self::class,
            'status' => 'IMPLEMENTED',
            'confidence' => 'high',
            'warnings' => [],
        ]]);
    }

    public function getConfidence(): string {
        return 'high';
    }

    public function getWarnings(): array {
        return [];
    }
}
