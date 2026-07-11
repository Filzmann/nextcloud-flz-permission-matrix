<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Model;

/**
 * Zweck: Kanonische, unveraenderliche Zeile einer Berechtigungsmatrix.
 *
 * Zusammenspiel:
 * - Adapter erzeugen Zeilen, DiffService ordnet sie ueber key() zwischen Snapshots zu,
 *   Snapshot und Mapper serialisieren bzw. persistieren sie.
 *
 * Vertrag:
 * - Die Identitaet umfasst das fachliche Objekt, aber weder Status noch Gruppenwerte;
 *   dadurch bleibt dieselbe Berechtigung auch nach einer Rechteaenderung vergleichbar.
 */
class MatrixRow {
    public function __construct(
        private string $objectType,
        private string $appId,
        private string $objectName,
        private string $detail,
        private string $permissionType,
        private string $status,
        private string $source,
        private string $confidence,
        private array $cells,
        private array $warnings = [],
        private array $accessRules = []
    ) {
        ksort($this->cells);
        $this->warnings = array_values(array_unique(array_map('strval', $warnings)));
        $this->accessRules = AccessRule::get_all($accessRules);
    }

    public static function get(?array $payload): ?self {
        if ($payload === null) {
            return null;
        }

        return new self(
            (string)($payload['object_type'] ?? $payload['objectType'] ?? 'Other'),
            (string)($payload['app_id'] ?? $payload['appId'] ?? ''),
            (string)($payload['object'] ?? $payload['object_name'] ?? $payload['objectName'] ?? ''),
            (string)($payload['detail'] ?? ''),
            (string)($payload['permission_type'] ?? $payload['permissionType'] ?? ''),
            (string)($payload['status'] ?? 'UNKNOWN'),
            (string)($payload['source'] ?? 'adapter'),
            (string)($payload['confidence'] ?? 'low'),
            is_array($payload['cells'] ?? null) ? $payload['cells'] : [],
            is_array($payload['warnings'] ?? null) ? $payload['warnings'] : [],
            is_array($payload['access_rules'] ?? null) ? $payload['access_rules'] : []
        );
    }

    /**
     * @return self[]
     */
    public static function get_all(array $payloads): array {
        $rows = [];
        foreach ($payloads as $payload) {
            $row = self::get(is_array($payload) ? $payload : null);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function withStatus(string $status): self {
        return new self(
            $this->objectType,
            $this->appId,
            $this->objectName,
            $this->detail,
            $this->permissionType,
            $status,
            $this->source,
            $this->confidence,
            $this->cells,
            $this->warnings,
            $this->accessRules
        );
    }

    public function key(): string {
        return sha1(implode('|', [
            $this->objectType,
            $this->appId,
            $this->objectName,
            $this->detail,
            $this->permissionType,
        ]));
    }

    public function objectType(): string {
        return $this->objectType;
    }

    public function appId(): string {
        return $this->appId;
    }

    public function objectName(): string {
        return $this->objectName;
    }

    public function detail(): string {
        return $this->detail;
    }

    public function permissionType(): string {
        return $this->permissionType;
    }

    public function status(): string {
        return $this->status;
    }

    public function source(): string {
        return $this->source;
    }

    public function confidence(): string {
        return $this->confidence;
    }

    public function cells(): array {
        return $this->cells;
    }

    public function warnings(): array {
        return $this->warnings;
    }

    /**
     * @return AccessRule[]
     */
    public function accessRules(): array {
        return $this->accessRules;
    }

    public function toArray(): array {
        return [
            'row_key' => $this->key(),
            'object_type' => $this->objectType,
            'app_id' => $this->appId,
            'object' => $this->objectName,
            'detail' => $this->detail,
            'permission_type' => $this->permissionType,
            'status' => $this->status,
            'source' => $this->source,
            'confidence' => $this->confidence,
            'cells' => $this->cells,
            'warnings' => $this->warnings,
            'access_rules' => array_map(static fn(AccessRule $rule): array => $rule->toArray(), $this->accessRules),
        ];
    }
}
