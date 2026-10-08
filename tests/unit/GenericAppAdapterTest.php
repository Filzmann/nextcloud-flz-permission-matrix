<?php

declare(strict_types=1);

use OCA\FlzPermissionMatrix\Adapter\GenericAppAdapter;
use OCA\FlzPermissionMatrix\Service\AdapterCatalogService;
use OCA\FlzPermissionMatrix\Service\InventoryService;
use OCA\FlzPermissionMatrix\Service\PermissionProviderSourceInterface;

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
            [
                'app_id' => 'notes',
                'display_name' => 'Notes',
                'version' => '1.0',
                'enabled' => true,
                'restricted' => false,
                'groups' => [],
                'source' => 'appstore',
            ],
            [
                'app_id' => 'calendar',
                'display_name' => 'Kalender',
                'version' => '1.0',
                'enabled' => true,
                'restricted' => false,
                'groups' => [],
                'source' => 'shipped',
            ],
            [
                'app_id' => 'flzplaner',
                'display_name' => 'Filzmann Assistenzplanung',
                'version' => '1.0',
                'enabled' => true,
                'restricted' => false,
                'groups' => [],
                'source' => 'custom',
            ],
        ];
    }
}

$emptyProviders = new class implements PermissionProviderSourceInterface {
    public function providers(): array { return []; }
    public function registrationFailures(): array { return []; }
    public function hasProvider(string $appId): bool { return false; }
};
$result = (new GenericAppAdapter(new GenericAdapterFakeInventory(), new AdapterCatalogService($emptyProviders)))->collect();
$rows = $result->rows();

assertSameValue(5, count($rows), 'generic adapter should create one app availability row per app');
assertSameValue('NEW', $rows[0]->status(), 'known app visibility should remain concrete even without a detail adapter');
assertSameValue('-', $rows[0]->cells()['Betriebsrat'], 'restricted app should deny non-listed group');
assertSameValue('X', $rows[0]->cells()['IKT-Ausschuss'], 'restricted app should allow listed group');
assertSameValue('(Gruppe IKT-Ausschuss)', $rows[0]->accessRules()[0]->conditionText(), 'restricted app visibility should expose its OR condition as a machine-readable rule');
assertSameValue('NEW', $rows[1]->status(), 'implemented adapter app availability should be concrete before baseline comparison');
assertSameValue('NEW', $rows[2]->status(), 'global app visibility should be separate from unsupported detail coverage');
assertSameValue('X', $rows[2]->cells()['Betriebsrat'], 'unsupported status must not hide a globally available app from any scanned group');
assertSameValue('X', $rows[2]->cells()['IKT-Ausschuss'], 'global app availability should be independent of detail-adapter support');
assertSameValue('X', $rows[3]->cells()['Betriebsrat'], 'an unrestricted shipped Calendar app must be shown as enabled for every scanned group');
assertSameValue('X', $rows[3]->cells()['IKT-Ausschuss'], 'Calendar availability must not depend on its missing detail adapter');
assertSameValue(['deck', 'notes', 'calendar', 'flzplaner'], $result->unsupportedApps(), 'Apps without a public detail contract must remain unsupported, including native and custom apps with internal permission services.');
assertSameValue('UNSUPPORTED', $result->adapterStatus()[0]['status'], 'detail coverage should remain unsupported independently of row access status');
assertContainsText('öffentliche versionierte read-only Schnittstelle', implode(' ', $result->adapterStatus()[4]['warnings']), 'Custom apps should explain the missing public provider contract precisely.');

echo 'GenericAppAdapter tests passed' . PHP_EOL;
