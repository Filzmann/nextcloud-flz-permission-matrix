<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../lib/Service/GroupCatalogService.php';

use OCA\BrPermissionMatrix\Service\GroupCatalogService;

$catalog = (new GroupCatalogService())->catalog([
    'Betriebsrat',
    'ad-ASN-ThoJa',
    'ad-ASN-HaMü',
    'ad-ASN-ThoJa-Urlaub',
    'ad-EB-Nord',
    'ad-EB-Süd-Team',
    'ad-ASN-Team-mit-Bindestrich',
]);

$byKey = [];
foreach ($catalog as $entry) {
    $byKey[$entry['key']] = $entry;
}

assertSameValue(
    ['ad-ASN-HaMü', 'ad-ASN-ThoJa'],
    $byKey['family:adplaner_assistance_teams']['groups'],
    'AdPlaner assistance teams should be summarized as one explicit family.'
);
assertSameValue(1, $byKey['family:adplaner_vacation_visibility']['count'], 'Vacation visibility groups need their own family.');
assertSameValue(2, $byKey['family:adplaner_eb_roles']['count'], 'EB groups should be summarized independently.');
assertSameValue(
    ['ad-EB-Nord', 'ad-EB-Süd-Team'],
    $byKey['family:adplaner_eb_roles']['groups'],
    'All group names accepted by AdPlaner EB detection should share the EB family.'
);
assertSameValue(
    ['ad-ASN-Team-mit-Bindestrich'],
    $byKey['ad-ASN-Team-mit-Bindestrich']['groups'],
    'Unknown variants must stay visible as individual raw groups.'
);
assertSameValue(['Betriebsrat'], $byKey['Betriebsrat']['groups'], 'Unrelated groups must remain individual.');

echo 'GroupCatalogService tests passed' . PHP_EOL;
