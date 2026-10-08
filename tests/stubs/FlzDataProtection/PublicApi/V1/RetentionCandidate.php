<?php
declare(strict_types=1);
namespace OCA\FlzDataProtection\PublicApi\V1;
final class RetentionCandidate {
    public function __construct(private string $policyId, private string $reference, private string $occurredAt, private string $action, private string $reviewReason, private array $attributes = []) {}
    public function toArray(): array { return ['policyId' => $this->policyId, 'reference' => $this->reference, 'occurredAt' => $this->occurredAt, 'action' => $this->action, 'reviewReason' => $this->reviewReason, 'attributes' => $this->attributes]; }
}
