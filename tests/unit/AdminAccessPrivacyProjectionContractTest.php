<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$repository = (string)file_get_contents($root . '/lib/Db/PersonalDataProjectionRepository.php');
$provider = (string)file_get_contents($root . '/lib/Privacy/PermissionMatrixPersonalDataProvider.php');

foreach (['pm_admin_access', 'target_uid', 'granted_by', 'revoked_by', "'source' => 'admin_access'", 'createNamedParameter'] as $contract) {
    if (!str_contains($repository, $contract)) {
        throw new RuntimeException('Subject-gebundene Adminfreigabe-Projektion fehlt: ' . $contract);
    }
}

foreach (['Zeitlich begrenzter Admin-Vollzugriff', 'Eigene Rolle im Vorgang', 'Kennungen anderer beteiligter', 'Datenschutzbeauftragte', 'Fachapp'] as $contract) {
    if (!str_contains($provider, $contract)) {
        throw new RuntimeException('Datensparsame Adminfreigabe-Auskunft fehlt: ' . $contract);
    }
}

if (str_contains($provider, "'Freigegeben von' =>") || str_contains($provider, "'Ziel-Admin' =>")) {
    throw new RuntimeException('Kennungen anderer Administrator*innen dürfen nicht direkt ausgegeben werden.');
}

echo "Permission Matrix admin access privacy projection contract tests passed\n";
