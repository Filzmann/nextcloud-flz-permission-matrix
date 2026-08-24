<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\PublicApi\V1;

use InvalidArgumentException;

final class PermissionRule {
    public function __construct(
        private string $objectType,
        private string $objectName,
        private string $detail,
        private string $permission,
        private string $permissionLabel,
        private string $effect,
        private string $scope,
        private PermissionCondition $condition,
        private string $source,
        private string $confidence,
    ) {
        foreach ([$objectType, $objectName, $permission, $permissionLabel, $scope, $source] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Permission rule fields must not be empty.');
            }
        }
        if (!in_array($effect, ['allow', 'deny'], true) || !in_array($confidence, ['high', 'medium', 'low'], true)) {
            throw new InvalidArgumentException('Invalid permission rule effect or confidence.');
        }
    }

    public function objectType(): string { return $this->objectType; }
    public function objectName(): string { return $this->objectName; }
    public function detail(): string { return $this->detail; }
    public function permission(): string { return $this->permission; }
    public function permissionLabel(): string { return $this->permissionLabel; }
    public function effect(): string { return $this->effect; }
    public function scope(): string { return $this->scope; }
    public function condition(): PermissionCondition { return $this->condition; }
    public function source(): string { return $this->source; }
    public function confidence(): string { return $this->confidence; }
}
