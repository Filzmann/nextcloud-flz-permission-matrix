<?php

declare(strict_types=1);

use OCA\BrPermissionMatrix\Adapter\AdPlanerAdapter;
use OCA\BrPermissionMatrix\Service\InventoryService;

class AdPlanerAdapterFakeInventory extends InventoryService {
    public function __construct() {
    }

    public function isAppEnabled(string $appId): bool {
        return $appId === 'adplaner';
    }

    public function groups(): array {
        return ['Andere-Gruppe', 'ad-ASN-A1', 'ad-ASN-B2', 'ad-EB-Koordination'];
    }
}

$result = (new AdPlanerAdapter(new AdPlanerAdapterFakeInventory()))->collect();
$rows = $result->rows();

assertSameValue(4, count($rows), 'each synthetic team should expose view and coordinate permissions.');
$coordinate = array_values(array_filter($rows, static fn($row): bool => $row->objectName() === 'Team koordinieren · A1'))[0];
assertSameValue('AND', $coordinate->cells()['ad-ASN-A1'], 'team membership should be marked as one side of the condition.');
assertSameValue('AND', $coordinate->cells()['ad-EB-Koordination'], 'EB membership should be marked as the other side of the condition.');
assertSameValue(
    '(Gruppe ad-ASN-A1 UND (Gruppe ad-EB-Koordination))',
    $coordinate->accessRules()[0]->conditionText(),
    'AdPlaner coordination must mirror the server-side team AND EB rule.'
);
assertSameValue('PARTIAL', $result->adapterStatus()[0]['status'], 'known AdPlaner rules should not claim complete app coverage.');

echo 'AdPlanerAdapter tests passed' . PHP_EOL;
