<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Model;

use InvalidArgumentException;

/**
 * Zweck: Maschinenlesbare, rekursive Gruppenbedingung fuer effektive Zugriffsregeln.
 *
 * Zusammenspiel:
 * - App-Adapter bauen Gruppen- und Akteursbedingungen; AccessRule verbindet sie mit Funktion,
 *   Wirkung und technischer Quelle. UI und Exporte koennen dieselbe Struktur erklaeren.
 *
 * Vertrag:
 * - group referenziert genau eine rohe Nextcloud-Gruppen-ID.
 * - all und any enthalten mindestens eine gueltige Kindbedingung; leere Bedingungen sind verboten.
 * - self, authenticated, nextcloud-admin und app-admin-grant bleiben ausdrueckliche Akteursbedingungen und
 *   duerfen von der Gruppenmatrix nicht als Gruppenfreigabe interpretiert werden.
 */
class AccessCondition {
    private function __construct(
        private string $operator,
        private ?string $groupId,
        private array $children
    ) {
        if (!in_array($operator, ['group', 'all', 'any', 'self', 'authenticated', 'nextcloud-admin', 'app-admin-grant'], true)) {
            throw new InvalidArgumentException('Unbekannter Bedingungsoperator.');
        }
        if ($operator === 'group' && ($groupId === null || trim($groupId) === '')) {
            throw new InvalidArgumentException('Gruppenbedingungen benoetigen eine Gruppen-ID.');
        }
        if (in_array($operator, ['all', 'any'], true) && $children === []) {
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

    public static function self(): self { return new self('self', null, []); }

    public static function authenticated(): self { return new self('authenticated', null, []); }

    public static function nextcloudAdmin(): self { return new self('nextcloud-admin', null, []); }

    public static function temporaryAppAdminGrant(): self { return new self('app-admin-grant', null, []); }

    public static function get(?array $payload): ?self {
        if ($payload === null) {
            return null;
        }
        $operator = (string)($payload['operator'] ?? '');

        if ($operator === 'group') {
            return self::group((string)($payload['group_id'] ?? ''));
        }
        if (in_array($operator, ['self', 'authenticated', 'nextcloud-admin', 'app-admin-grant'], true)) {
            return new self($operator, null, []);
        }
        return new self($operator, null, self::get_all(is_array($payload['children'] ?? null) ? $payload['children'] : []));
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
        if ($this->operator === 'self') {
            return 'Eigene Person oder eigenes Objekt';
        }
        if ($this->operator === 'authenticated') {
            return 'Angemeldete Person';
        }
        if ($this->operator === 'nextcloud-admin') {
            return 'Nextcloud-Administration';
        }
        if ($this->operator === 'app-admin-grant') {
            return 'Aktive zeitlich begrenzte App-Adminfreigabe';
        }
        $separator = $this->operator === 'all' ? ' UND ' : ' ODER ';

        return '(' . implode($separator, array_map(static fn(self $child): string => $child->describe(), $this->children)) . ')';
    }

    public function toArray(): array {
        if ($this->operator === 'group') {
            return ['operator' => 'group', 'group_id' => $this->groupId];
        }

        if (in_array($this->operator, ['self', 'authenticated', 'nextcloud-admin', 'app-admin-grant'], true)) {
            return ['operator' => $this->operator];
        }

        return [
            'operator' => $this->operator,
            'children' => array_map(static fn(self $child): array => $child->toArray(), $this->children),
        ];
    }
}
