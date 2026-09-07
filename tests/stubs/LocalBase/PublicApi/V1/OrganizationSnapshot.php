<?php

declare(strict_types=1);

namespace OCA\LocalBase\PublicApi\V1;

/** App-lokaler Test-Double; der reale Vertrag wird im Parent gegen LocalBase geprüft. */
final class OrganizationSnapshot {
    public const CONTRACT_VERSION = '1.0';

    public function __construct(
        private bool $valid,
        private int $definitionVersion,
        private array $roles,
        private array $areas,
    ) {
        if (!$valid) {
            $this->roles = [];
            $this->areas = [];
        }
    }

    public function contractVersion(): string { return self::CONTRACT_VERSION; }
    public function isValid(): bool { return $this->valid; }
    public function definitionVersion(): int { return $this->definitionVersion; }
    public function roles(): array { return $this->roles; }
    public function areas(): array { return $this->areas; }

    public function checksum(): string {
        return hash('sha256', json_encode([
            'contractVersion' => self::CONTRACT_VERSION,
            'valid' => $this->valid,
            'definitionVersion' => $this->definitionVersion,
            'roles' => $this->roles,
            'areas' => $this->areas,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
