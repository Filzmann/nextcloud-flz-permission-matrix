<?php

declare(strict_types=1);

namespace OCA\FilzmannDataProtection\PublicApi\V1;

final class DataSubjectRef {
    public function __construct(private string $subjectType, private string $subjectId) {}
    public function subjectType(): string { return $this->subjectType; }
    public function subjectId(): string { return $this->subjectId; }
}
