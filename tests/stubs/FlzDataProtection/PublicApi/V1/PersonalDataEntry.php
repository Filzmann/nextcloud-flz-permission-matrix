<?php

declare(strict_types=1);

namespace OCA\FlzDataProtection\PublicApi\V1;

final class PersonalDataEntry {
    public function __construct(
        private string $categoryId,
        private string $categoryLabel,
        private string $reference,
        private string $summary,
        private string $purpose,
        private string $source,
        private array $recipientCategories,
        private string $retention,
        private string $thirdCountryTransfer,
        private string $automatedDecision,
        private ?string $thirdPartyContentNotice,
        private array $attributes,
    ) {}
    public function toArray(): array {
        return [
            'categoryId' => $this->categoryId,
            'categoryLabel' => $this->categoryLabel,
            'reference' => $this->reference,
            'summary' => $this->summary,
            'purpose' => $this->purpose,
            'source' => $this->source,
            'recipientCategories' => $this->recipientCategories,
            'retention' => $this->retention,
            'thirdCountryTransfer' => $this->thirdCountryTransfer,
            'automatedDecision' => $this->automatedDecision,
            'thirdPartyContentNotice' => $this->thirdPartyContentNotice,
            'attributes' => $this->attributes,
        ];
    }
}
