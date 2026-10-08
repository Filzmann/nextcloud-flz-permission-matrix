<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Service;

interface PermissionProviderSourceInterface {
    public function providers(): array;
    public function registrationFailures(): array;
    public function hasProvider(string $appId): bool;
}
