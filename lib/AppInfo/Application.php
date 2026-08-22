<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\AppInfo;

use OCA\BrPermissionMatrix\Service\NativeSharingSourceInterface;
use OCA\BrPermissionMatrix\Service\NextcloudSharingSource;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'br_permission_matrix';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerServiceAlias(NativeSharingSourceInterface::class, NextcloudSharingSource::class);
    }

    public function boot(IBootContext $context): void {
    }
}
