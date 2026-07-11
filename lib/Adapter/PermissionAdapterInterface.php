<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Adapter;

interface PermissionAdapterInterface {
    public function supports(string $appId): bool;

    public function collect(): AdapterResult;

    public function getConfidence(): string;

    public function getWarnings(): array;
}
