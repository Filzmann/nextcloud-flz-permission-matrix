<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Service;

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
            throw new RuntimeException('Der Team-Folders-Laufzeitdienst ist nicht verfügbar.');
        }

        $manager = Server::get(self::MANAGER_CLASS);
        if (!is_object($manager)) {
            throw new RuntimeException('Der Team-Folders-Laufzeitdienst konnte nicht aufgelöst werden.');
        }

        return $manager;
    }
}
