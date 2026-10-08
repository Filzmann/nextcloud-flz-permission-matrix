<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Service;

/** App-lokale, read-only Grenze für einen aktuell gültigen Admin-Vollzugriff. */
interface TemporaryAdminAccessChecker {
    public function hasActiveGrant(string $uid): bool;
}


