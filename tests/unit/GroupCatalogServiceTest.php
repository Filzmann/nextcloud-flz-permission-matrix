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
    'ad-PFK-Team1',
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
assertSameValue(1, $byKey['family:adplaner_pfk_roles']['count'], 'PFK team mappings should have their own family.');
assertSameValue(
    ['group' => 'ad-PFK-Team1', 'label' => 'Rolle PFK · Team1', 'team' => null, 'role' => 'pfk'],
    $byKey['family:adplaner_pfk_roles']['members'][0],
    'Role metadata must not invent a team assignment from the role-group suffix.'
);
assertSameValue(
    'Team HaMü · Assistenz',
    $byKey['family:adplaner_assistance_teams']['members'][0]['label'],
    'Assistance groups should expose readable team labels.'
);
assertSameValue(
    ['ad-ASN-Team-mit-Bindestrich'],
    $byKey['ad-ASN-Team-mit-Bindestrich']['groups'],
    'Unknown variants must stay visible as individual raw groups.'
);
assertSameValue(['Betriebsrat'], $byKey['Betriebsrat']['groups'], 'Unrelated groups must remain individual.');

echo 'GroupCatalogService tests passed' . PHP_EOL;
