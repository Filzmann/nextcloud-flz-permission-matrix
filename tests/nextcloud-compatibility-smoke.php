<?php

declare(strict_types=1);

use OCA\FilzmannPermissionMatrix\Service\OrganizationSnapshotService;
use OCA\FilzmannPermissionMatrix\Service\TemporaryAdminAccessService;

return [
    'providerRegistrations' => [
        'filzmann_data_protection' => [
            OCA\FilzmannDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent::class,
            OCA\FilzmannDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent::class,
            OCA\FilzmannDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent::class,
        ],
    ],
    'uiPath' => '/index.php/apps/filzmann_permission_matrix/',
    'preGrantUiStatuses' => [403],
    'postGrantUiStatuses' => [200],
    'grantService' => TemporaryAdminAccessService::class,
    'permissionProbe' => static function(string $uid): bool {
        $organization = OCP\Server::get(OrganizationSnapshotService::class)->snapshot();
        if (!in_array($organization['status'], ['MISSING', 'INVALID', 'INCOMPATIBLE', 'UNAVAILABLE', 'VALID'], true)) {
            throw new RuntimeException('LocalBase organization contract did not resolve to a controlled runtime state.');
        }
        if ($organization['status'] !== 'VALID'
            && ($organization['roles'] !== [] || $organization['areas'] !== [])) {
            throw new RuntimeException('A non-valid LocalBase organization contract exposed semantic mappings.');
        }

        return OCP\Server::get(TemporaryAdminAccessService::class)->hasActiveGrant($uid);
    },
    'apiSmokes' => [
        ['/index.php/apps/filzmann_permission_matrix/api/state', [200]],
    ],
];
