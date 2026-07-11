<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

use OCA\BrPermissionMatrix\Model\MatrixRow;
use OCA\BrPermissionMatrix\Model\Snapshot;
use OCA\BrPermissionMatrix\Service\ExportService;

$snapshot = new Snapshot(
    'pm-test',
    '2026-07-05T12:00:00+00:00',
    '34.0.0',
    ['Betriebsrat', 'IKT-Ausschuss'],
    [['app_id' => 'files', 'display_name' => 'Dateien', 'version' => '1.0', 'source' => 'shipped', 'restricted' => false, 'groups' => []]],
    [new MatrixRow('App', 'files', 'Dateien', 'App-Nutzung', 'App-Verfuegbarkeit', 'APPROVED', 'core-app-config', 'high', [
        'Betriebsrat' => 'X',
        'IKT-Ausschuss' => 'X',
    ])],
    [],
    [],
    [],
    ['redacted' => true],
    ['compliance_status' => 'green', 'baseline_snapshot' => 'base']
);

$service = new ExportService();
$json = $service->export($snapshot, 'json');
$csv = $service->export($snapshot, 'csv');
$md = $service->export($snapshot, 'md');
$html = $service->export($snapshot, 'html');

assertContainsText('"snapshot_id": "pm-test"', $json['content'], 'json export should contain snapshot id');
assertContainsText('Objekttyp,App-ID', $csv['content'], 'csv export should contain header');
assertContainsText('# Berechtigungsmatrix Nextcloud', $md['content'], 'markdown export should contain title');
assertContainsText('Keine Dateiinhalte', $md['content'], 'markdown export should contain security note');
assertContainsText('<table>', $html['content'], 'html export should contain table');

echo 'ExportService tests passed' . PHP_EOL;
