<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Service;

/**
 * App-local, read-only boundary for an optional Team Folders runtime.
 *
 * No mount point, file path or file content may cross this boundary.
 */
interface GroupFoldersSourceInterface {
    public function folders(string $appId, string $appVersion): array;
}
