<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Service;

interface GroupFoldersManagerProviderInterface {
    public function manager(): object;
}
