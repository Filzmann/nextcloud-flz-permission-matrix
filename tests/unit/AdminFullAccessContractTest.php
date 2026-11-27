<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$adminTemplate = (string)file_get_contents($root . '/templates/admin.php');
$appTemplate = (string)file_get_contents($root . '/templates/index.php');
$script = is_file($root . '/js/admin-access.js') ? (string)file_get_contents($root . '/js/admin-access.js') : '';
$bundleOverlay = is_file($root . '/js/shared/overlay.js') ? (string)file_get_contents($root . '/js/shared/overlay.js') : '';
$routes = (string)file_get_contents($root . '/appinfo/routes.php');
$page = (string)file_get_contents($root . '/lib/Controller/PageController.php');

foreach (['pm-full-access-form','pm-full-access-enabled','pm-full-access-history','Maximal 24 Stunden'] as $contract) {
    if (!str_contains($appTemplate, $contract)) throw new RuntimeException("Rollenabhängige Fachapp-Steuerung fehlt: {$contract}");
    if (str_contains($adminTemplate, $contract)) throw new RuntimeException("Freigabesteuerung liegt noch im technischen Adminbereich: {$contract}");
}

// Overlay-Vertrag: Das Warn-Icon am App-Titel ist ein Button, der ein modales Overlay öffnet.
$overlayId = 'pm-admin-access-overlay';
foreach ([
    'id="' . $overlayId . '"',
    'role="dialog"',
    'aria-modal="true"',
    'pm-admin-access-overlay-close',
    'id="pm-admin-access-warning"',
    'aria-haspopup="dialog"',
    'aria-controls="' . $overlayId . '"',
    'aria-expanded',
] as $contract) {
    if (!str_contains($appTemplate, $contract)) throw new RuntimeException("Overlay-Vertrag fehlt in der Vorlage: {$contract}");
}
if (str_contains($appTemplate, '<details class="pm-admin-access-warning"')) throw new RuntimeException('Warn-Icon ist noch als aufklappbares details-Element umgesetzt.');
if (str_contains($appTemplate, 'target="_blank"')) throw new RuntimeException('Veralteter Verweis "Freigabesteuerung in neuem Tab öffnen" ist noch vorhanden.');
$overlayPos = strpos($appTemplate, 'id="' . $overlayId . '"');
$sectionPos = strpos($appTemplate, 'pm-full-access-form');
$warningPos = strpos($appTemplate, 'Kein zeitlich begrenzter fachlicher Vollzugriff aktiv.');
if ($overlayPos === false || $sectionPos === false || $warningPos === false || $overlayPos >= $sectionPos || $overlayPos >= $warningPos) throw new RuntimeException('Warnung und Freigabesteuerung liegen nicht innerhalb des Overlays.');

foreach (['adminFullAccess','activateAdminFullAccess','revokeAdminFullAccess',$overlayId] as $contract) {
    if (!str_contains($script, $contract)) throw new RuntimeException("Vollzugriffs-UI-Vertrag fehlt im Bootstrap: {$contract}");
}
foreach (['/api/admin/full-access','durationMinutes','targetUid'] as $contract) {
    if (!str_contains($routes . (string)file_get_contents($root . '/js/modules/api.js'), $contract)) throw new RuntimeException("Vollzugriffs-API-Vertrag fehlt: {$contract}");
}
foreach (['AdminAccessOverlay','Overlay','Escape','aria-expanded','flz-shared-overlay','flz-shared-warn-trigger','Widerrufen','hidden'] as $contract) {
    if (!str_contains($bundleOverlay, $contract)) throw new RuntimeException("Geteiltes Overlay-Bundle fehlt: {$contract}");
}
foreach (["script('flz_permission_matrix', 'shared/overlay')", 'class="flz-shared-overlay"', 'class="flz-shared-warn-trigger"'] as $contract) {
    if (!str_contains($appTemplate, $contract)) throw new RuntimeException("Overlay-Vertrag fehlt in der Vorlage: {$contract}");
}
if (!str_contains($routes, "'verb' => 'DELETE'")) throw new RuntimeException('Widerrufroute fehlt.');
foreach (['canManageAdminAccess','showMissingAdminGrant','showAdminAccessLink','hasMatrixAccess'] as $contract) {
    if (!str_contains($appTemplate.$page,$contract)) throw new RuntimeException('Sichere Rollenprojektion fehlt: '.$contract);
}
if (!str_contains($page,'$canManageAdminAccess && $showMissingAdminGrant')) throw new RuntimeException('Direktlink ist nicht auf dasselbe kombinierte Admin- und Datenschutzkonto begrenzt.');
if (!str_contains($page,'$showMissingAdminGrant = $this->temporaryAdminAccess->currentAdminNeedsGrant()')) throw new RuntimeException('Der fehlende-Freigabe-Hinweis wird nicht unabhängig von der Datenschutzrolle für das betroffene Administrationskonto projiziert.');
if (!str_contains($page,'!$showMissingAdminGrant')) throw new RuntimeException('Das betroffene Administrationskonto kann die sichere Eintrittswarnung nicht erreichen.');
foreach (['Datenschutzbeauftragte'] as $contract) {
    if (!str_contains($appTemplate, $contract)) throw new RuntimeException("Warntext fehlt: {$contract}");
}

echo "Permission Matrix admin full access overlay contract tests passed\n";
