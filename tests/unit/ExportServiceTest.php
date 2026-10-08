<?php

declare(strict_types=1);

use OCA\FlzPermissionMatrix\Model\MatrixRow;
use OCA\FlzPermissionMatrix\Model\AccessCondition;
use OCA\FlzPermissionMatrix\Model\AccessRule;
use OCA\FlzPermissionMatrix\Model\Snapshot;
use OCA\FlzPermissionMatrix\Exception\ExportFormatNotAllowedException;
use OCA\FlzPermissionMatrix\Service\ConfigService;
use OCA\FlzPermissionMatrix\Service\ExportService;

class ExportTestConfig extends ConfigService {
    public function __construct(private array $formats, private bool $redact = true) {
    }

    public function exportFormats(): array {
        return $this->formats;
    }

    public function redactPaths(): bool {
        return $this->redact;
    }
}

$snapshot = new Snapshot(
    'pm-test',
    '2026-07-05T12:00:00+00:00',
    '34.0.0',
    ['Betriebsrat', 'IKT-Ausschuss', 'flz-ASN-Ada', 'flz-ASN-Berta', 'flz-EB-Ada'],
    [['app_id' => 'files', 'display_name' => 'Dateien', 'version' => '1.0', 'source' => 'shipped', 'restricted' => false, 'groups' => []]],
    [
        new MatrixRow('App', 'files', 'Dateien', 'App-Nutzung', 'App-Verfuegbarkeit', 'APPROVED', 'core-app-config', 'high', [
            'Betriebsrat' => 'X',
            'IKT-Ausschuss' => 'X',
            'flz-ASN-Ada' => 'X',
            'flz-ASN-Berta' => '-',
            'flz-EB-Ada' => 'X',
        ], [], [new AccessRule(
            'app.use',
            'allow',
            'app:files',
            AccessCondition::all([
                AccessCondition::group('flz-ASN-Ada'),
                AccessCondition::group('flz-EB-Ada'),
            ]),
            'test:files-policy',
            'high'
        )]),
        new MatrixRow('Policy', 'core', 'Teilen', 'Dateioperation', 'Sharing', 'UNKNOWN', 'core-sharing', 'medium', [
            'Betriebsrat' => 'S',
            'IKT-Ausschuss' => 'S',
            'flz-ASN-Ada' => 'S',
            'flz-ASN-Berta' => '?',
            'flz-EB-Ada' => 'S',
        ]),
        new MatrixRow('SharedFolder', 'files_sharing', '/Personal/Akte', 'Gruppenfreigabe · Referenz test', 'Lesen', 'NEW', 'nextcloud:OCP\\Share\\IManager::getSharesBy', 'high', [
            'Betriebsrat' => 'R',
            'IKT-Ausschuss' => '-',
            'flz-ASN-Ada' => '-',
            'flz-ASN-Berta' => '-',
            'flz-EB-Ada' => '-',
        ]),
    ],
    [],
    [],
    [[
        'type' => 'PERMISSION_REMOVED',
        'severity' => 'info',
        'row_key' => 'removed-path-key',
        'group' => null,
        'old' => 'present',
        'new' => 'removed',
        'object_type' => 'SharedFolder',
        'app_id' => 'files_sharing',
        'object' => '/Removed/Folder',
        'message' => 'SharedFolder files_sharing / /Removed/Folder: PERMISSION_REMOVED',
    ]],
    ['redacted' => true, 'organization_snapshot' => [
        'status' => 'VALID',
        'contract_version' => 1,
        'definition_version' => 4,
        'checksum' => str_repeat('a', 64),
    ]],
    ['compliance_status' => 'green', 'baseline_snapshot' => 'base'],
    [],
    [[
        'key' => 'family:flzplaner_assistance_teams',
        'label' => 'Filzmann Assistenzplanung · Assistenznehmer-Teams',
        'type' => 'family',
        'source_app' => 'flzplaner',
        'family' => 'flzplaner_assistance_teams',
        'groups' => ['flz-ASN-Ada', 'flz-ASN-Berta'],
        'count' => 2,
    ], [
        'key' => 'family:flzplaner_eb_roles',
        'label' => 'Filzmann Assistenzplanung · Einsatzbegleitung',
        'type' => 'family',
        'source_app' => 'flzplaner',
        'family' => 'flzplaner_eb_roles',
        'groups' => ['flz-EB-Ada'],
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
assertContainsText('"(Gruppe flz-ASN-Ada UND Gruppe flz-EB-Ada)",test:files-policy,high', $csv['content'], 'csv should preserve composite access conditions and their source.');
assertContainsText('# Berechtigungsmatrix Nextcloud', $md['content'], 'markdown export should contain title');
assertContainsText('Organisationsvertrag: VALID · Vertrag 1 · Definition 4 · Prüfsumme ' . str_repeat('a', 64), $md['content'], 'markdown should identify its canonical organization source.');
assertContainsText('Keine Dateiinhalte', $md['content'], 'markdown export should contain security note');
assertContainsText('AND = markierte Gruppenbedingungen muessen gemeinsam erfuellt sein', $md['content'], 'markdown should explain composite group cells.');
assertSameValue(1, substr_count($md['content'], 'AND = markierte Gruppenbedingungen muessen gemeinsam erfuellt sein'), 'The export legend must not duplicate the same contract line.');
assertContainsText('Technische Quelle', $md['content'], 'markdown should expose the evidence source.');
assertContainsText('(Gruppe flz-ASN-Ada UND Gruppe flz-EB-Ada)', $md['content'], 'markdown should preserve composite access conditions.');
assertContainsText('## Hauptmatrix (Gruppenfamilien)', $md['content'], 'markdown should lead with the summarized group-family matrix.');
assertContainsText('Filzmann Assistenzplanung · Assistenznehmer-Teams (2 Gruppen)', $md['content'], 'markdown should identify summarized group families.');
assertContainsText('Filzmann Assistenzplanung · Einsatzbegleitung (1 Gruppe)', $md['content'], 'markdown should use the singular label for one-member families.');
assertContainsText('X (1/2)', $md['content'], 'markdown should make partial family permissions explicit.');
assertContainsText('gemischt (2/2)', $md['content'], 'markdown should expose conflicting values within a family.');
assertContainsText('## Rohmatrix', $md['content'], 'markdown should retain the complete auditable raw matrix.');
assertContainsText('flz-ASN-Ada', $md['content'], 'markdown should name raw family members.');
assertContainsText('<table>', $html['content'], 'html export should contain table');
assertContainsText('Organisationsvertrag: VALID', $html['content'], 'html should identify its canonical organization source.');
assertContainsText('<th scope="col">Bedingung</th>', $html['content'], 'html should expose access conditions as their own column.');
assertSameValue(false, str_contains($html['content'], '<td>test:files-policy</td><td>test:files-policy</td>'), 'HTML rows must align one-to-one with their declared evidence columns.');
assertContainsText('Hauptmatrix (Gruppenfamilien)', $html['content'], 'html should lead with the summarized group-family matrix.');
assertContainsText('title="flz-ASN-Ada: X; flz-ASN-Berta: -"', $html['content'], 'html should retain raw values on aggregated cells.');
assertContainsText('<h2>Rohmatrix</h2>', $html['content'], 'html should retain the complete auditable raw matrix.');
foreach ([$json['content'], $csv['content'], $md['content'], $html['content']] as $content) {
    assertSameValue(false, str_contains($content, '/Personal/Akte'), 'Default exports must redact concrete shared paths in every format.');
    assertContainsText('[Pfad redigiert 1]', $content, 'Redacted exports should keep distinct auditable placeholders.');
}
assertSameValue(false, str_contains($json['content'], '/Removed/Folder'), 'JSON must also redact paths that occur only in a removed baseline diff.');
assertSameValue(false, str_contains($md['content'], '/Removed/Folder'), 'Markdown must also redact paths that occur only in a removed baseline diff.');
assertContainsText('[Pfad redigiert 2]', $json['content'], 'Removed path-only diffs should receive a neutral export-local alias.');

$unredactedService = new ExportService(new ExportTestConfig(['json'], false));
assertContainsText('/Personal/Akte', $unredactedService->export($snapshot, 'json')['content'], 'An explicit administrative opt-out should retain paths in the protected export.');
assertContainsText('/Removed/Folder', $unredactedService->export($snapshot, 'json')['content'], 'An explicit opt-out should also retain path-only diff details.');

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
