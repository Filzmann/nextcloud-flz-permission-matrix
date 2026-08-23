<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\AppInfo;

use OCA\FilzmannPermissionMatrix\Listener\StandaloneNavigationListener;
use OCA\FilzmannPermissionMatrix\Service\NativeSharingSourceInterface;
use OCA\FilzmannPermissionMatrix\Service\NextcloudSharingSource;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

class Application extends App implements IBootstrap {
    public const APP_ID = 'filzmann_permission_matrix';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(LoadAdditionalEntriesEvent::class, StandaloneNavigationListener::class);
        $context->registerServiceAlias(NativeSharingSourceInterface::class, NextcloudSharingSource::class);
    }

    public function boot(IBootContext $context): void {
    }
}
