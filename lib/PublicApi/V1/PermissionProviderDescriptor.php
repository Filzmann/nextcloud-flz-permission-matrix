<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\PublicApi\V1;

use InvalidArgumentException;

final class PermissionProviderDescriptor {
    public function __construct(
        private string $appId,
        private string $displayName,
        private string $contractVersion,
        private array $capabilities,
    ) {
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/', $appId)) {
            throw new InvalidArgumentException('Invalid provider app ID.');
        }
        if (trim($displayName) === '' || !preg_match('/^[1-9][0-9]*\.[0-9]+$/', $contractVersion)) {
            throw new InvalidArgumentException('Invalid provider descriptor.');
        }
        if ($capabilities === [] || count($capabilities) !== count(array_unique($capabilities))) {
            throw new InvalidArgumentException('Invalid provider capabilities.');
        }
        foreach ($capabilities as $capability) {
            if (!is_string($capability) || !preg_match('/^[a-z][a-z0-9-]{1,63}$/', $capability)) {
                throw new InvalidArgumentException('Invalid provider capability.');
            }
        }
    }

    public function appId(): string { return $this->appId; }
    public function displayName(): string { return $this->displayName; }
    public function contractVersion(): string { return $this->contractVersion; }
    public function capabilities(): array { return $this->capabilities; }
}
