<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Service;

interface GroupFoldersManagerProviderInterface {
    public function manager(): object;
}
