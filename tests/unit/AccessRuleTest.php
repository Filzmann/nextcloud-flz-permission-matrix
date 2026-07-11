<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

use OCA\BrPermissionMatrix\Model\AccessCondition;
use OCA\BrPermissionMatrix\Model\AccessRule;

$rule = new AccessRule(
    'team.coordinate',
    'allow',
    'team:A1',
    AccessCondition::all([
        AccessCondition::group('ad-ASN-A1'),
        AccessCondition::any([
            AccessCondition::group('ad-EB-Koordination'),
            AccessCondition::group('ad-EB-Vertretung'),
        ]),
    ]),
    'adplaner:test-policy',
    'high'
);
$payload = $rule->toArray();

assertSameValue(
    '(Gruppe ad-ASN-A1 UND (Gruppe ad-EB-Koordination ODER Gruppe ad-EB-Vertretung))',
    $rule->conditionText(),
    'Nested AND/OR conditions should remain understandable without losing raw group IDs.'
);
assertSameValue($payload, AccessRule::get($payload)->toArray(), 'access rules should round-trip through their public payload.');

try {
    AccessCondition::all([]);
    throw new RuntimeException('Empty condition groups must be rejected.');
} catch (InvalidArgumentException $e) {
    assertSameValue('Verknuepfte Bedingungen duerfen nicht leer sein.', $e->getMessage(), 'empty conditions need a safe validation error.');
}

echo 'AccessRule tests passed' . PHP_EOL;
