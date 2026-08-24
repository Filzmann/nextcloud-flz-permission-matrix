<?php
declare(strict_types=1);
namespace OCA\FilzmannDataProtection\PublicApi\V1;
final class RetentionPolicy {
    public function __construct(private string $policyId, private string $dataClass, private string $purpose, private string $trigger, private int $durationDays, private string $action, private string $version) {}
    public function policyId(): string { return $this->policyId; }
    public function durationDays(): int { return $this->durationDays; }
    public function action(): string { return $this->action; }
}
