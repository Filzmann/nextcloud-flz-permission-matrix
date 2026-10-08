<?php

declare(strict_types=1);

use OCA\FlzPermissionMatrix\Service\GroupCatalogService;

$groups = [
    'Betriebsrat',
    'flz-ASN-ThoJa',
    'flz-ASN-HaMü',
    'flz-ASN-ThoJa-Urlaub',
    'custom-eb',
    'custom-area-north',
    'flz-ASN-Team-mit-Bindestrich',
];
$organization = [
    'status' => 'VALID',
    'roles' => ['eb' => ['groupId' => 'custom-eb', 'label' => 'Einsatzbegleitung']],
    'areas' => ['north' => ['groupId' => 'custom-area-north', 'label' => 'Büro Nord']],
];
$catalog = (new GroupCatalogService())->catalog($groups, $organization);

$byKey = [];
foreach ($catalog as $entry) {
    $byKey[$entry['key']] = $entry;
}

assertSameValue(
    ['flz-ASN-HaMü', 'flz-ASN-ThoJa'],
    $byKey['family:flzplaner_assistance_teams']['groups'],
    'Known Filzmann Assistenzplanung assistance teams should remain a lossless presentation family when organization data is valid.'
);
assertSameValue(1, $byKey['family:flzplaner_vacation_visibility']['count'], 'Vacation visibility groups need their own family.');
assertSameValue('Rolle · Einsatzbegleitung', $byKey['custom-eb']['label'], 'Role labels must follow the canonical organization snapshot.');
assertSameValue('role', $byKey['custom-eb']['semantic_type'], 'Canonical role meaning must be explicit.');
assertSameValue('eb', $byKey['custom-eb']['semantic_key'], 'Technical group IDs must not replace semantic role keys.');
assertSameValue('Bereich · Büro Nord', $byKey['custom-area-north']['label'], 'Area labels must follow the canonical organization snapshot.');
assertSameValue('KNOWN', $byKey['custom-area-north']['meaning_status'], 'Provider-backed group meaning must be marked as known.');
assertSameValue(
    ['flz-ASN-Team-mit-Bindestrich'],
    $byKey['flz-ASN-Team-mit-Bindestrich']['groups'],
    'Unknown variants must stay visible as individual raw groups.'
);

$unknownCatalog = (new GroupCatalogService())->catalog($groups, ['status' => 'INVALID', 'roles' => [], 'areas' => []]);
$unknownByKey = [];
foreach ($unknownCatalog as $entry) {
    $unknownByKey[$entry['key']] = $entry;
}
assertSameValue(count($groups), count($unknownCatalog), 'An invalid provider must leave every raw group individually visible.');
assertSameValue(false, isset($unknownByKey['family:flzplaner_assistance_teams']), 'No family meaning may be inferred from an invalid provider.');
assertSameValue('UNKNOWN', $unknownByKey['custom-eb']['meaning_status'], 'Missing canonical meaning must be explicit.');
assertSameValue('custom-eb', $unknownByKey['custom-eb']['label'], 'Unknown meaning must not invent a semantic label.');

echo 'GroupCatalogService tests passed' . PHP_EOL;
