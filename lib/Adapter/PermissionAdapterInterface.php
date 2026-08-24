<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Adapter;

/**
 * Zweck: Gemeinsamer read-only Vertrag fuer auslesbare Berechtigungsquellen.
 *
 * Vertrag:
 * - Adapter duerfen keine Nextcloud- oder Fremd-App-Berechtigungen veraendern.
 * - Nicht sicher auslesbare Zustaende werden als Warnung, UNKNOWN oder UNSUPPORTED geliefert.
 */
interface PermissionAdapterInterface {
    public function supports(string $appId): bool;

    public function collect(): AdapterResult;

    public function getConfidence(): string;

    public function getWarnings(): array;
}
