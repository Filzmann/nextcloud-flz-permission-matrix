<?php

declare(strict_types=1);

namespace OCA\FlzDataProtection\PublicApi\V1;

final class ProcessingMetadataProviderDescriptor {
    public function __construct(
        private string $appId,
        private string $displayName,
        private string $contractVersion,
    ) {}

    public function appId(): string { return $this->appId; }
    public function displayName(): string { return $this->displayName; }
    public function contractVersion(): string { return $this->contractVersion; }
}
