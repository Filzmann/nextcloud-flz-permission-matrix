<?php

declare(strict_types=1);

namespace OCA\FlzDataProtection\PublicApi\V1;

final class ProcessingMetadataCatalog {
    /** @param array<string, mixed> $payload */
    private function __construct(private array $payload) {}

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self { return new self($payload); }
    public function appId(): string { return (string)($this->payload['app_id'] ?? ''); }

    /** @return list<string> */
    public function processingIds(): array {
        return array_map(
            static fn(array $processing): string => (string)($processing['processing_id'] ?? ''),
            $this->payload['processings'] ?? [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array { return $this->payload; }
}
