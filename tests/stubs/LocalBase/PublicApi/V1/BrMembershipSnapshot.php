<?php

declare(strict_types=1);

namespace OCA\LocalBase\PublicApi\V1;

/** App-lokaler Test-Double; der reale Vertrag wird im Parent gegen LocalBase geprüft. */
final class BrMembershipSnapshot {
    public const CONTRACT_VERSION = '1.0';

    public function __construct(
        private bool $valid,
        private int $definitionVersion,
        private int $definitionRevision,
        private ?string $memberGroupId,
    ) {
    }

    public function contractVersion(): string { return self::CONTRACT_VERSION; }
    public function isValid(): bool { return $this->valid; }
    public function definitionVersion(): int { return $this->definitionVersion; }
    public function definitionRevision(): int { return $this->definitionRevision; }
    public function memberGroupId(): ?string { return $this->memberGroupId; }
    public function checksum(): string { return 'test-br-checksum'; }
}
