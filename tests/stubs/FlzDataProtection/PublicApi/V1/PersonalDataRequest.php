<?php

declare(strict_types=1);

namespace OCA\FlzDataProtection\PublicApi\V1;

final class PersonalDataRequest {
    public function __construct(
        private DataSubjectRef $subject,
        private string $language,
        private string $purpose,
        private int $pageLimit,
        private array $providerCursors,
    ) {}
    public function subject(): DataSubjectRef { return $this->subject; }
    public function language(): string { return $this->language; }
    public function purpose(): string { return $this->purpose; }
    public function pageLimit(): int { return $this->pageLimit; }
    public function cursor(): ?string { return $this->providerCursors['flz_permission_matrix'] ?? null; }
}
