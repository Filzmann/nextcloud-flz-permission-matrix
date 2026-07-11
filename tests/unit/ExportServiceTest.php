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
    ['Betriebsrat', 'IKT-Ausschuss', 'ad-ASN-Ada', 'ad-ASN-Berta'],
    [['app_id' => 'files', 'display_name' => 'Dateien', 'version' => '1.0', 'source' => 'shipped', 'restricted' => false, 'groups' => []]],
    [
        new MatrixRow('App', 'files', 'Dateien', 'App-Nutzung', 'App-Verfuegbarkeit', 'APPROVED', 'core-app-config', 'high', [
            'Betriebsrat' => 'X',
            'IKT-Ausschuss' => 'X',
            'ad-ASN-Ada' => 'X',
            'ad-ASN-Berta' => '-',
        ]),
        new MatrixRow('Policy', 'core', 'Teilen', 'Dateioperation', 'Sharing', 'UNKNOWN', 'core-sharing', 'medium', [
            'Betriebsrat' => 'S',
            'IKT-Ausschuss' => 'S',
            'ad-ASN-Ada' => 'S',
            'ad-ASN-Berta' => '?',
        ]),
    ],
    [],
    [],
    [],
    ['redacted' => true],
    ['compliance_status' => 'green', 'baseline_snapshot' => 'base'],
    [],
    [[
        'key' => 'family:adplaner_assistance_teams',
        'label' => 'AdPlaner · Assistenznehmer-Teams',
        'type' => 'family',
        'source_app' => 'adplaner',
        'family' => 'adplaner_assistance_teams',
        'groups' => ['ad-ASN-Ada', 'ad-ASN-Berta'],
        'count' => 2,
    ], [
        'key' => 'Betriebsrat', 'label' => 'Betriebsrat', 'type' => 'group',
        'source_app' => null, 'family' => null, 'groups' => ['Betriebsrat'], 'count' => 1,
    ], [
        'key' => 'IKT-Ausschuss', 'label' => 'IKT-Ausschuss', 'type' => 'group',
        'source_app' => null, 'family' => null, 'groups' => ['IKT-Ausschuss'], 'count' => 1,
    ]]
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
assertContainsText('## Hauptmatrix (Gruppenfamilien)', $md['content'], 'markdown should lead with the summarized group-family matrix.');
assertContainsText('AdPlaner · Assistenznehmer-Teams (2 Gruppen)', $md['content'], 'markdown should identify summarized group families.');
assertContainsText('X (1/2)', $md['content'], 'markdown should make partial family permissions explicit.');
assertContainsText('gemischt (2/2)', $md['content'], 'markdown should expose conflicting values within a family.');
assertContainsText('## Rohmatrix', $md['content'], 'markdown should retain the complete auditable raw matrix.');
assertContainsText('ad-ASN-Ada', $md['content'], 'markdown should name raw family members.');
assertContainsText('<table>', $html['content'], 'html export should contain table');
assertContainsText('Hauptmatrix (Gruppenfamilien)', $html['content'], 'html should lead with the summarized group-family matrix.');
assertContainsText('title="ad-ASN-Ada: X; ad-ASN-Berta: -"', $html['content'], 'html should retain raw values on aggregated cells.');
assertContainsText('<h2>Rohmatrix</h2>', $html['content'], 'html should retain the complete auditable raw matrix.');

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
