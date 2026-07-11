<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Model;

use InvalidArgumentException;

/**
 * Zweck: Maschinenlesbare, rekursive Gruppenbedingung fuer effektive Zugriffsregeln.
 *
 * Zusammenspiel:
 * - App-Adapter bauen Bedingungen aus group/all/any; AccessRule verbindet sie mit Funktion,
 *   Wirkung und technischer Quelle. UI und Exporte koennen dieselbe Struktur erklaeren.
 *
 * Vertrag:
 * - group referenziert genau eine rohe Nextcloud-Gruppen-ID.
 * - all und any enthalten mindestens eine gueltige Kindbedingung; leere Bedingungen sind verboten.
 */
class AccessCondition {
    private function __construct(
        private string $operator,
        private ?string $groupId,
        private array $children
    ) {
        if (!in_array($operator, ['group', 'all', 'any'], true)) {
            throw new InvalidArgumentException('Unbekannter Bedingungsoperator.');
        }
        if ($operator === 'group' && ($groupId === null || trim($groupId) === '')) {
            throw new InvalidArgumentException('Gruppenbedingungen benoetigen eine Gruppen-ID.');
        }
        if ($operator !== 'group' && $children === []) {
            throw new InvalidArgumentException('Verknuepfte Bedingungen duerfen nicht leer sein.');
        }
    }

    public static function group(string $groupId): self {
        return new self('group', trim($groupId), []);
    }

    public static function all(array $children): self {
        return new self('all', null, self::get_all($children));
    }

    public static function any(array $children): self {
        return new self('any', null, self::get_all($children));
    }

    public static function get(?array $payload): ?self {
        if ($payload === null) {
            return null;
        }
        $operator = (string)($payload['operator'] ?? '');

        return $operator === 'group'
            ? self::group((string)($payload['group_id'] ?? ''))
            : new self($operator, null, self::get_all(is_array($payload['children'] ?? null) ? $payload['children'] : []));
    }

    /**
     * @return self[]
     */
    public static function get_all(array $payloads): array {
        $conditions = [];
        foreach ($payloads as $payload) {
            $condition = $payload instanceof self ? $payload : self::get(is_array($payload) ? $payload : null);
            if ($condition !== null) {
                $conditions[] = $condition;
            }
        }

        return $conditions;
    }

    public function describe(): string {
        if ($this->operator === 'group') {
            return 'Gruppe ' . $this->groupId;
        }
        $separator = $this->operator === 'all' ? ' UND ' : ' ODER ';

        return '(' . implode($separator, array_map(static fn(self $child): string => $child->describe(), $this->children)) . ')';
    }

    public function toArray(): array {
        if ($this->operator === 'group') {
            return ['operator' => 'group', 'group_id' => $this->groupId];
        }

        return [
            'operator' => $this->operator,
            'children' => array_map(static fn(self $child): array => $child->toArray(), $this->children),
        ];
    }
}
