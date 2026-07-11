<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Model;

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
        private array $warnings = []
    ) {
        ksort($this->cells);
        $this->warnings = array_values(array_unique(array_map('strval', $warnings)));
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
            is_array($payload['warnings'] ?? null) ? $payload['warnings'] : []
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
            $this->warnings
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
        ];
    }
}
