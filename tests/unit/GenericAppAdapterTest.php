<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

use OCA\BrPermissionMatrix\Adapter\GenericAppAdapter;
use OCA\BrPermissionMatrix\Service\AdapterCatalogService;
use OCA\BrPermissionMatrix\Service\InventoryService;

class GenericAdapterFakeInventory extends InventoryService {
    public function __construct() {
    }

    public function groups(): array {
        return ['Betriebsrat', 'IKT-Ausschuss'];
    }

    public function enabledApps(): array {
        return [
            [
                'app_id' => 'deck',
                'display_name' => 'Deck',
                'version' => '1.0',
                'enabled' => true,
                'restricted' => true,
                'groups' => ['IKT-Ausschuss'],
                'source' => 'appstore',
            ],
            [
                'app_id' => 'files',
                'display_name' => 'Dateien',
                'version' => '1.0',
                'enabled' => true,
                'restricted' => false,
                'groups' => [],
                'source' => 'shipped',
            ],
        ];
    }
}

$result = (new GenericAppAdapter(new GenericAdapterFakeInventory(), new AdapterCatalogService()))->collect();
$rows = $result->rows();

assertSameValue(2, count($rows), 'generic adapter should create one app availability row per app');
assertSameValue('UNSUPPORTED', $rows[0]->status(), 'app without implemented adapter should be unsupported');
assertSameValue('-', $rows[0]->cells()['Betriebsrat'], 'restricted app should deny non-listed group');
assertSameValue('X', $rows[0]->cells()['IKT-Ausschuss'], 'restricted app should allow listed group');
assertSameValue('NEW', $rows[1]->status(), 'implemented adapter app availability should be concrete before baseline comparison');
assertSameValue(['deck'], $result->unsupportedApps(), 'unsupported app list should contain app without adapter');

echo 'GenericAppAdapter tests passed' . PHP_EOL;
