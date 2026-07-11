<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

use OCA\BrPermissionMatrix\Model\MatrixRow;

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
]);

assertSameValue('App', $row->objectType(), 'object type should hydrate');
assertSameValue('UNSUPPORTED', $row->status(), 'status should hydrate');
assertSameValue(['Alle' => '-', 'Betriebsrat' => 'X'], $row->cells(), 'cells should be sorted');
assertSameValue($row->key(), MatrixRow::get($row->toArray())->key(), 'row key should be stable across serialization');

echo 'MatrixRow tests passed' . PHP_EOL;
