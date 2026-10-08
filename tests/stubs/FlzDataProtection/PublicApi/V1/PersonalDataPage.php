<?php

declare(strict_types=1);

namespace OCA\FlzDataProtection\PublicApi\V1;

final class PersonalDataPage {
    public function __construct(
        private string $status,
        private array $entries = [],
        private array $restrictions = [],
        private ?string $nextCursor = null,
    ) {}
    public function status(): string { return $this->status; }
    public function entries(): array { return $this->entries; }
    public function restrictions(): array { return $this->restrictions; }
    public function nextCursor(): ?string { return $this->nextCursor; }
}
