<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\PublicApi\V1;

use InvalidArgumentException;

final class PermissionCondition {
    private function __construct(
        private string $operator,
        private ?string $groupId = null,
        private array $children = [],
    ) {
        if (!in_array($operator, ['group', 'all', 'any', 'self', 'authenticated', 'nextcloud-admin'], true)) {
            throw new InvalidArgumentException('Invalid permission condition operator.');
        }
        if ($operator === 'group' && ($groupId === null || trim($groupId) === '')) {
            throw new InvalidArgumentException('Group conditions require a group ID.');
        }
        if (in_array($operator, ['all', 'any'], true) && $children === []) {
            throw new InvalidArgumentException('Composite permission conditions must not be empty.');
        }
        foreach ($children as $child) {
            if (!$child instanceof self) {
                throw new InvalidArgumentException('Invalid child permission condition.');
            }
        }
    }

    public static function group(string $groupId): self { return new self('group', trim($groupId)); }
    public static function all(array $children): self { return new self('all', null, array_values($children)); }
    public static function any(array $children): self { return new self('any', null, array_values($children)); }
    public static function self(): self { return new self('self'); }
    public static function authenticated(): self { return new self('authenticated'); }
    public static function nextcloudAdmin(): self { return new self('nextcloud-admin'); }

    public function operator(): string { return $this->operator; }
    public function groupId(): ?string { return $this->groupId; }
    /** @return list<self> */
    public function children(): array { return $this->children; }

    public function toArray(): array {
        if ($this->operator === 'group') {
            return ['operator' => 'group', 'group_id' => $this->groupId];
        }
        if (in_array($this->operator, ['all', 'any'], true)) {
            return [
                'operator' => $this->operator,
                'children' => array_map(static fn(self $child): array => $child->toArray(), $this->children),
            ];
        }
        return ['operator' => $this->operator];
    }
}
