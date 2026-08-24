<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Service;

/**
 * Zweck: Trennt tatsaechlich implementierte Detailadapter vom fachlich geforderten Mindestumfang.
 *
 * Zusammenspiel:
 * - GenericAppAdapter nutzt den Katalog, um aktivierte Apps ohne belastbare Detailauswertung
 *   sichtbar als UNSUPPORTED statt versehentlich als freigegeben zu markieren.
 */
class AdapterCatalogService {
    private const IMPLEMENTED_APP_IDS = [
        'core',
        'files',
        'files_external',
        'files_sharing',
        'files_accesscontrol',
        'groupfolders',
        'files_groupfolders',
        'filzmann_permission_matrix',
    ];

    private const MINIMUM_TARGET_APP_IDS = [
        'core',
        'files',
        'files_sharing',
        'files_accesscontrol',
        'groupfolders',
        'files_groupfolders',
        'activity',
        'notifications',
        'spreed',
        'deck',
        'collectives',
        'tables',
        'calendar',
        'contacts',
        'forms',
        'notes',
        'richdocuments',
        'onlyoffice',
        'external',
        'files_external',
        'user_ldap',
        'twofactor_totp',
    ];

    public function hasImplementedAdapter(string $appId): bool {
        return in_array($appId, self::IMPLEMENTED_APP_IDS, true);
    }

    public function isMinimumTarget(string $appId): bool {
        return in_array($appId, self::MINIMUM_TARGET_APP_IDS, true);
    }

    public function implementedAppIds(): array {
        return self::IMPLEMENTED_APP_IDS;
    }

    public function minimumTargetAppIds(): array {
        return self::MINIMUM_TARGET_APP_IDS;
    }
}
