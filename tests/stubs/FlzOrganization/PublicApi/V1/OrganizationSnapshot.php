<?php

declare(strict_types=1);

namespace OCA\FlzOrganization\PublicApi\V1;

/** App-local consumer double; provider behavior is verified in flz_organization. */
final class OrganizationSnapshot {
    public const CONTRACT_VERSION = '1.0';

    public function __construct(
        private bool $valid,
        private int $definitionVersion,
        private array $roles,
        private array $areas,
        private array $identityGroups = [],
    ) {
        if (!$valid) {
            $this->roles = [];
            $this->areas = [];
            $this->identityGroups = [];
        }
    }

    public function contractVersion(): string { return self::CONTRACT_VERSION; }
    public function isValid(): bool { return $this->valid; }
    public function definitionVersion(): int { return $this->definitionVersion; }
    public function roles(): array { return $this->roles; }
    public function areas(): array { return $this->areas; }
    public function identityGroups(): array { return $this->identityGroups; }

    public function checksum(): string {
        return hash('sha256', json_encode([
            'contractVersion' => self::CONTRACT_VERSION,
            'valid' => $this->valid,
            'definitionVersion' => $this->definitionVersion,
            'roles' => $this->roles,
            'areas' => $this->areas,
            'identityGroups' => $this->identityGroups,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
