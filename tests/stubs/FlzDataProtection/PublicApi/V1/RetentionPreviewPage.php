<?php
declare(strict_types=1);
namespace OCA\FlzDataProtection\PublicApi\V1;
final class RetentionPreviewPage {
    public function __construct(private string $status, private array $candidates = [], private array $warnings = [], private ?string $nextCursor = null) {}
    public function status(): string { return $this->status; }
    public function candidates(): array { return $this->candidates; }
    public function nextCursor(): ?string { return $this->nextCursor; }
}
