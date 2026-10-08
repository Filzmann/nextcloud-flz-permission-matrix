<?php

declare(strict_types=1);

use OCA\FlzPermissionMatrix\Model\AccessCondition;
use OCA\FlzPermissionMatrix\Model\AccessRule;

$rule = new AccessRule(
    'team.coordinate',
    'allow',
    'team:A1',
    AccessCondition::all([
        AccessCondition::group('flz-ASN-A1'),
        AccessCondition::any([
            AccessCondition::group('flz-EB-Koordination'),
            AccessCondition::group('flz-EB-Vertretung'),
        ]),
    ]),
    'flzplaner:test-policy',
    'high'
);
$payload = $rule->toArray();

assertSameValue(
    '(Gruppe flz-ASN-A1 UND (Gruppe flz-EB-Koordination ODER Gruppe flz-EB-Vertretung))',
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
