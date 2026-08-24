<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Settings;

use OCA\FilzmannPermissionMatrix\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
    public function __construct(
        private IURLGenerator $url
    ) {
    }

    public function getIcon(): string {
        return $this->url->imagePath(Application::APP_ID, 'app.svg');
    }

    public function getID(): string {
        return Application::APP_ID;
    }

    public function getName(): string {
        return 'Berechtigungsmatrix';
    }

    public function getPriority(): int {
        return 61;
    }
}
