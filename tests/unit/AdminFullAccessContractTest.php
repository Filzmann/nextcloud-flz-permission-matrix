<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$template = (string)file_get_contents($root . '/templates/admin.php');
$script = (string)file_get_contents($root . '/js/admin.js');
$routes = (string)file_get_contents($root . '/appinfo/routes.php');

foreach (['pm-full-access-form','pm-full-access-enabled','pm-full-access-history','Maximal 24 Stunden'] as $contract) {
    if (!str_contains($template, $contract)) throw new RuntimeException("Zugängliche Vollzugriffsadministration fehlt: {$contract}");
}
foreach (['/api/admin/full-access','durationMinutes','targetUid','Widerrufen'] as $contract) {
    if (!str_contains($script . $routes, $contract)) throw new RuntimeException("Vollzugriffs-UI/API-Vertrag fehlt: {$contract}");
}
if (!str_contains($routes, "'verb' => 'DELETE'")) throw new RuntimeException('Widerrufroute fehlt.');

echo "Permission Matrix admin full access UI contract tests passed\n";

