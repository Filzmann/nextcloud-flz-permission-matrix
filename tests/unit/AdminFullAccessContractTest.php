<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$adminTemplate = (string)file_get_contents($root . '/templates/admin.php');
$appTemplate = (string)file_get_contents($root . '/templates/index.php');
$script = is_file($root . '/js/admin-access.js') ? (string)file_get_contents($root . '/js/admin-access.js') : '';
$routes = (string)file_get_contents($root . '/appinfo/routes.php');
$page = (string)file_get_contents($root . '/lib/Controller/PageController.php');

foreach (['pm-full-access-form','pm-full-access-enabled','pm-full-access-history','Maximal 24 Stunden'] as $contract) {
    if (!str_contains($appTemplate, $contract)) throw new RuntimeException("Rollenabhängige Fachapp-Steuerung fehlt: {$contract}");
    if (str_contains($adminTemplate, $contract)) throw new RuntimeException("Freigabesteuerung liegt noch im technischen Adminbereich: {$contract}");
}
foreach (['/api/admin/full-access','durationMinutes','targetUid','Widerrufen'] as $contract) {
    if (!str_contains($script . $routes, $contract)) throw new RuntimeException("Vollzugriffs-UI/API-Vertrag fehlt: {$contract}");
}
if (!str_contains($routes, "'verb' => 'DELETE'")) throw new RuntimeException('Widerrufroute fehlt.');
foreach (['canManageAdminAccess','showMissingAdminGrant','showAdminAccessLink','hasMatrixAccess'] as $contract) {
    if (!str_contains($appTemplate.$page,$contract)) throw new RuntimeException('Sichere Rollenprojektion fehlt: '.$contract);
}
if (!str_contains($page,'$canManageAdminAccess && $showMissingAdminGrant')) throw new RuntimeException('Direktlink ist nicht auf dasselbe kombinierte Admin- und Datenschutzkonto begrenzt.');
if (!str_contains($page,'$showMissingAdminGrant = $this->temporaryAdminAccess->currentAdminNeedsGrant()')) throw new RuntimeException('Der fehlende-Freigabe-Hinweis wird nicht unabhängig von der Datenschutzrolle für das betroffene Administrationskonto projiziert.');
if (!str_contains($page,'!$showMissingAdminGrant')) throw new RuntimeException('Das betroffene Administrationskonto kann die sichere Eintrittswarnung nicht erreichen.');
foreach (['pm-admin-access-warning', '<details', '<summary', 'Datenschutzbeauftragte', 'target="_blank"', 'rel="noopener noreferrer"'] as $contract) if (!str_contains($appTemplate, $contract)) throw new RuntimeException("Kompakte Vollzugriffswarnung am App-Titel fehlt: {$contract}");

echo "Permission Matrix admin full access UI contract tests passed\n";
