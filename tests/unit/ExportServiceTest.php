<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

use OCA\BrPermissionMatrix\Model\MatrixRow;
use OCA\BrPermissionMatrix\Model\Snapshot;
use OCA\BrPermissionMatrix\Exception\ExportFormatNotAllowedException;
use OCA\BrPermissionMatrix\Service\ConfigService;
use OCA\BrPermissionMatrix\Service\ExportService;

class ExportTestConfig extends ConfigService {
    public function __construct(private array $formats) {
    }

    public function exportFormats(): array {
        return $this->formats;
    }
}

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

$service = new ExportService(new ExportTestConfig(['md', 'csv', 'json', 'html']));
$json = $service->export($snapshot, 'json');
$csv = $service->export($snapshot, 'csv');
$md = $service->export($snapshot, 'md');
$html = $service->export($snapshot, 'html');

assertContainsText('"snapshot_id": "pm-test"', $json['content'], 'json export should contain snapshot id');
assertContainsText('Objekttyp,App-ID', $csv['content'], 'csv export should contain header');
assertContainsText('# Berechtigungsmatrix Nextcloud', $md['content'], 'markdown export should contain title');
assertContainsText('Keine Dateiinhalte', $md['content'], 'markdown export should contain security note');
assertContainsText('<table>', $html['content'], 'html export should contain table');

$restrictedService = new ExportService(new ExportTestConfig(['md']));
assertSameValue(['md'], $restrictedService->allowedFormats(), 'The UI/API contract should expose only configured export formats.');
assertContainsText('# Berechtigungsmatrix Nextcloud', $restrictedService->export($snapshot, 'markdown')['content'], 'markdown alias should honor the canonical md policy.');
try {
    $restrictedService->export($snapshot, 'json');
    throw new RuntimeException('Disabled export formats must not be generated.');
} catch (ExportFormatNotAllowedException $e) {
    assertSameValue('Exportformat ist nicht freigegeben.', $e->getMessage(), 'Disabled formats need a safe error message.');
}

try {
    $service->export($snapshot, 'pdf');
    throw new RuntimeException('Unimplemented export formats must not be accepted.');
} catch (InvalidArgumentException $e) {
    assertSameValue('Exportformat nicht unterstuetzt.', $e->getMessage(), 'Unimplemented formats need a distinct validation error.');
}

echo 'ExportService tests passed' . PHP_EOL;
