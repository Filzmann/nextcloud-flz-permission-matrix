<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\PublicApi\V1;

use InvalidArgumentException;

final class PermissionProviderDescriptor {
    public function __construct(
        private string $appId,
        private string $displayName,
        private string $contractVersion,
        private array $capabilities,
    ) {
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/', $appId)) {
            throw new InvalidArgumentException('Ungültige Provider-App-ID.');
        }
        if (trim($displayName) === '' || !preg_match('/^[1-9][0-9]*\.[0-9]+$/', $contractVersion)) {
            throw new InvalidArgumentException('Ungültiger Provider-Deskriptor.');
        }
        if ($capabilities === [] || count($capabilities) !== count(array_unique($capabilities))) {
            throw new InvalidArgumentException('Ungültige Provider-Fähigkeiten.');
        }
        foreach ($capabilities as $capability) {
            if (!is_string($capability) || !preg_match('/^[a-z][a-z0-9-]{1,63}$/', $capability)) {
                throw new InvalidArgumentException('Ungültige Provider-Fähigkeit.');
            }
        }
    }

    public function appId(): string { return $this->appId; }
    public function displayName(): string { return $this->displayName; }
    public function contractVersion(): string { return $this->contractVersion; }
    public function capabilities(): array { return $this->capabilities; }
}
