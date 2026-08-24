<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\PublicApi\V1;

use InvalidArgumentException;

final class PermissionProviderResult {
    public function __construct(
        private array $rules,
        private bool $complete = true,
        private array $warnings = [],
    ) {
        foreach ($rules as $rule) {
            if (!$rule instanceof PermissionRule) {
                throw new InvalidArgumentException('Invalid permission provider rule.');
            }
        }
        $this->rules = array_values($rules);
        $this->warnings = array_values(array_unique(array_map('strval', $warnings)));
    }

    /** @return list<PermissionRule> */
    public function rules(): array { return $this->rules; }
    public function complete(): bool { return $this->complete; }
    /** @return list<string> */
    public function warnings(): array { return $this->warnings; }
}
