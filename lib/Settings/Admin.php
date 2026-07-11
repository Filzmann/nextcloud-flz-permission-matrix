<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Settings;

use OCA\BrPermissionMatrix\AppInfo\Application;
use OCA\BrPermissionMatrix\Service\ConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class Admin implements ISettings {
    public function __construct(
        private ConfigService $config
    ) {
    }

    public function getForm(): TemplateResponse {
        return new TemplateResponse(Application::APP_ID, 'admin', [
            'config' => $this->config->toArray(),
        ]);
    }

    public function getSection(): string {
        return Application::APP_ID;
    }

    public function getPriority(): int {
        return 20;
    }
}
