<?php

declare(strict_types=1);

namespace OCA\FilzmannDataProtection\PublicApi\V1;

final class ProviderDescriptor {
    public function __construct(
        private string $appId,
        private string $displayName,
        private string $contractVersion,
        private array $subjectTypes,
        private array $capabilities,
        private int $maxPageSize,
    ) {}
    public function appId(): string { return $this->appId; }
    public function displayName(): string { return $this->displayName; }
    public function contractVersion(): string { return $this->contractVersion; }
    public function subjectTypes(): array { return $this->subjectTypes; }
    public function capabilities(): array { return $this->capabilities; }
    public function maxPageSize(): int { return $this->maxPageSize; }
}
