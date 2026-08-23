<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Model;

use InvalidArgumentException;

/**
 * Zweck: Belegt ein konkretes Funktions- oder Ordnerrecht mit Wirkung, Geltungsbereich und Bedingung.
 *
 * Zusammenspiel:
 * - MatrixRow traegt null bis mehrere Regeln; Adapter erzeugen sie aus App-Logik, UI und Exporte
 *   zeigen Bedingung und technische Quelle getrennt von der BR-Bewertung.
 *
 * Vertrag:
 * - Die Regel beschreibt technische Berechtigung, nicht automatisch ein berechtigtes Interesse.
 */
class AccessRule {
    public function __construct(
        private string $permission,
        private string $effect,
        private string $scope,
        private AccessCondition $condition,
        private string $source,
        private string $confidence
    ) {
        if (!in_array($effect, ['allow', 'deny'], true)) {
            throw new InvalidArgumentException('Regelwirkungen muessen allow oder deny sein.');
        }
    }

    public static function get(?array $payload): ?self {
        if ($payload === null) {
            return null;
        }
        $condition = AccessCondition::get(is_array($payload['condition'] ?? null) ? $payload['condition'] : null);
        if ($condition === null) {
            throw new InvalidArgumentException('Zugriffsregeln benoetigen eine Bedingung.');
        }

        return new self(
            (string)($payload['permission'] ?? ''),
            (string)($payload['effect'] ?? 'allow'),
            (string)($payload['scope'] ?? ''),
            $condition,
            (string)($payload['source'] ?? ''),
            (string)($payload['confidence'] ?? 'low')
        );
    }

    /**
     * @return self[]
     */
    public static function get_all(array $payloads): array {
        $rules = [];
        foreach ($payloads as $payload) {
            $rule = $payload instanceof self ? $payload : self::get(is_array($payload) ? $payload : null);
            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    public function conditionText(): string {
        return $this->condition->describe();
    }

    public function source(): string {
        return $this->source;
    }

    public function confidence(): string {
        return $this->confidence;
    }

    public function toArray(): array {
        return [
            'permission' => $this->permission,
            'effect' => $this->effect,
            'scope' => $this->scope,
            'condition' => $this->condition->toArray(),
            'condition_text' => $this->conditionText(),
            'source' => $this->source,
            'confidence' => $this->confidence,
        ];
    }
}
