<?php
declare(strict_types=1);
namespace OCA\FilzmannDataProtection\PublicApi\V1;
final class RetentionProviderDescriptor {
    public function __construct(private string $appId, private string $displayName, private string $contractVersion, private int $maxPageSize) {}
    public function appId(): string { return $this->appId; }
    public function contractVersion(): string { return $this->contractVersion; }
}
