<?php
declare(strict_types=1);
namespace OCA\FilzmannDataProtection\PublicApi\V1;
final class RetentionPreviewRequest {
    public function __construct(private string $policyId, private string $evaluatedAt, private int $limit, private ?string $cursor = null) {}
    public function policyId(): string { return $this->policyId; }
    public function evaluatedAt(): string { return $this->evaluatedAt; }
    public function limit(): int { return $this->limit; }
    public function cursor(): ?string { return $this->cursor; }
}
