<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Service;

use OCP\Server;
use RuntimeException;

/**
 * Resolves the optional foreign runtime service only after the source adapter
 * has accepted the installed Groupfolders version.
 */
class NextcloudGroupFoldersManagerProvider implements GroupFoldersManagerProviderInterface {
    private const MANAGER_CLASS = 'OCA\\GroupFolders\\Folder\\FolderManager';

    public function manager(): object {
        if (!class_exists(self::MANAGER_CLASS)) {
            throw new RuntimeException('The Team Folders runtime service is unavailable.');
        }

        $manager = Server::get(self::MANAGER_CLASS);
        if (!is_object($manager)) {
            throw new RuntimeException('The Team Folders runtime service could not be resolved.');
        }

        return $manager;
    }
}
