<?php

declare(strict_types=1);

use OCA\BrPermissionMatrix\Model\MatrixRow;
use OCA\BrPermissionMatrix\Model\AccessCondition;
use OCA\BrPermissionMatrix\Model\AccessRule;
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
    ['Betriebsrat', 'IKT-Ausschuss', 'ad-ASN-Ada', 'ad-ASN-Berta', 'ad-EB-Ada'],
    [['app_id' => 'files', 'display_name' => 'Dateien', 'version' => '1.0', 'source' => 'shipped', 'restricted' => false, 'groups' => []]],
    [
        new MatrixRow('App', 'files', 'Dateien', 'App-Nutzung', 'App-Verfuegbarkeit', 'APPROVED', 'core-app-config', 'high', [
            'Betriebsrat' => 'X',
            'IKT-Ausschuss' => 'X',
            'ad-ASN-Ada' => 'X',
            'ad-ASN-Berta' => '-',
            'ad-EB-Ada' => 'X',
        ], [], [new AccessRule(
            'app.use',
            'allow',
            'app:files',
            AccessCondition::all([
                AccessCondition::group('ad-ASN-Ada'),
                AccessCondition::group('ad-EB-Ada'),
            ]),
            'test:files-policy',
            'high'
        )]),
        new MatrixRow('Policy', 'core', 'Teilen', 'Dateioperation', 'Sharing', 'UNKNOWN', 'core-sharing', 'medium', [
            'Betriebsrat' => 'S',
            'IKT-Ausschuss' => 'S',
            'ad-ASN-Ada' => 'S',
            'ad-ASN-Berta' => '?',
            'ad-EB-Ada' => 'S',
        ]),
    ],
    [],
    [],
    [],
    ['redacted' => true, 'organization_snapshot' => [
        'status' => 'VALID',
        'contract_version' => 1,
        'definition_version' => 4,
        'checksum' => str_repeat('a', 64),
    ]],
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
        'key' => 'family:adplaner_eb_roles',
        'label' => 'AdPlaner · Einsatzbegleitung',
        'type' => 'family',
        'source_app' => 'adplaner',
        'family' => 'adplaner_eb_roles',
        'groups' => ['ad-EB-Ada'],
        'count' => 1,
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
assertContainsText('"checksum": "' . str_repeat('a', 64) . '"', $json['content'], 'json should record the organization source checksum.');
assertContainsText('Objekttyp,App-ID', $csv['content'], 'csv export should contain header');
assertContainsText(str_repeat('a', 64), $csv['content'], 'csv should record the organization source checksum.');
assertContainsText('Bedingung,"Technische Quelle",Aussagesicherheit', $csv['content'], 'csv should expose the evidence columns separately from status.');
assertContainsText('"(Gruppe ad-ASN-Ada UND Gruppe ad-EB-Ada)",test:files-policy,high', $csv['content'], 'csv should preserve composite access conditions and their source.');
assertContainsText('# Berechtigungsmatrix Nextcloud', $md['content'], 'markdown export should contain title');
assertContainsText('Organisationsvertrag: VALID · Vertrag 1 · Definition 4 · Prüfsumme ' . str_repeat('a', 64), $md['content'], 'markdown should identify its canonical organization source.');
assertContainsText('Keine Dateiinhalte', $md['content'], 'markdown export should contain security note');
assertContainsText('AND = markierte Gruppenbedingungen muessen gemeinsam erfuellt sein', $md['content'], 'markdown should explain composite group cells.');
assertSameValue(1, substr_count($md['content'], 'AND = markierte Gruppenbedingungen muessen gemeinsam erfuellt sein'), 'The export legend must not duplicate the same contract line.');
assertContainsText('Technische Quelle', $md['content'], 'markdown should expose the evidence source.');
assertContainsText('(Gruppe ad-ASN-Ada UND Gruppe ad-EB-Ada)', $md['content'], 'markdown should preserve composite access conditions.');
assertContainsText('## Hauptmatrix (Gruppenfamilien)', $md['content'], 'markdown should lead with the summarized group-family matrix.');
assertContainsText('AdPlaner · Assistenznehmer-Teams (2 Gruppen)', $md['content'], 'markdown should identify summarized group families.');
assertContainsText('AdPlaner · Einsatzbegleitung (1 Gruppe)', $md['content'], 'markdown should use the singular label for one-member families.');
assertContainsText('X (1/2)', $md['content'], 'markdown should make partial family permissions explicit.');
assertContainsText('gemischt (2/2)', $md['content'], 'markdown should expose conflicting values within a family.');
assertContainsText('## Rohmatrix', $md['content'], 'markdown should retain the complete auditable raw matrix.');
assertContainsText('ad-ASN-Ada', $md['content'], 'markdown should name raw family members.');
assertContainsText('<table>', $html['content'], 'html export should contain table');
assertContainsText('Organisationsvertrag: VALID', $html['content'], 'html should identify its canonical organization source.');
assertContainsText('<th scope="col">Bedingung</th>', $html['content'], 'html should expose access conditions as their own column.');
assertSameValue(false, str_contains($html['content'], '<td>test:files-policy</td><td>test:files-policy</td>'), 'HTML rows must align one-to-one with their declared evidence columns.');
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
