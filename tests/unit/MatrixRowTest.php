<?php

declare(strict_types=1);

use OCA\FilzmannPermissionMatrix\Model\MatrixRow;
use OCA\FilzmannPermissionMatrix\Model\AccessCondition;
use OCA\FilzmannPermissionMatrix\Model\AccessRule;

$row = MatrixRow::get([
    'object_type' => 'App',
    'app_id' => 'deck',
    'object' => 'Deck',
    'detail' => 'App-Nutzung',
    'permission_type' => 'App-Verfuegbarkeit',
    'status' => 'UNSUPPORTED',
    'source' => 'core-app-config',
    'confidence' => 'medium',
    'cells' => ['Betriebsrat' => 'X', 'Alle' => '-'],
    'warnings' => ['Keine Detailrechte'],
    'access_rules' => [new AccessRule(
        'team.view',
        'allow',
        'team:A1',
        AccessCondition::group('ad-ASN-A1'),
        'test:policy',
        'high'
    )],
]);

assertSameValue('App', $row->objectType(), 'object type should hydrate');
assertSameValue('UNSUPPORTED', $row->status(), 'status should hydrate');
assertSameValue(['Alle' => '-', 'Betriebsrat' => 'X'], $row->cells(), 'cells should be sorted');
assertSameValue('Gruppe ad-ASN-A1', $row->accessRules()[0]->conditionText(), 'access rules should preserve their machine-readable condition');
assertSameValue($row->key(), MatrixRow::get($row->toArray())->key(), 'row key should be stable across serialization');

echo 'MatrixRow tests passed' . PHP_EOL;
