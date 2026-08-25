<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\AppInfo;

use OCA\FilzmannPermissionMatrix\Listener\StandaloneNavigationListener;
use OCA\FilzmannPermissionMatrix\Privacy\PermissionMatrixPersonalDataProviderListener;
use OCA\FilzmannPermissionMatrix\Privacy\PermissionMatrixRetentionProviderListener;
use OCA\FilzmannPermissionMatrix\Service\GroupFoldersManagerProviderInterface;
use OCA\FilzmannPermissionMatrix\Service\GroupFoldersSourceInterface;
use OCA\FilzmannPermissionMatrix\Service\NativeSharingSourceInterface;
use OCA\FilzmannPermissionMatrix\Service\NextcloudGroupFoldersManagerProvider;
use OCA\FilzmannPermissionMatrix\Service\NextcloudGroupFoldersSource;
use OCA\FilzmannPermissionMatrix\Service\NextcloudSharingSource;
use OCA\FilzmannPermissionMatrix\Service\NextcloudPermissionProviderSource;
use OCA\FilzmannPermissionMatrix\Service\PermissionProviderSourceInterface;
use OCA\FilzmannPermissionMatrix\Db\TemporaryAdminAccessRepository;
use OCA\FilzmannPermissionMatrix\Db\TemporaryAdminAccessRepositoryInterface;
use OCA\FilzmannPermissionMatrix\Service\TemporaryAdminAccessChecker;
use OCA\FilzmannPermissionMatrix\Service\TemporaryAdminAccessService;
use OCA\FilzmannDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;
use OCA\FilzmannDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

class Application extends App implements IBootstrap {
    public const APP_ID = AppId::VALUE;

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(LoadAdditionalEntriesEvent::class, StandaloneNavigationListener::class);
        $context->registerEventListener(RegisterPersonalDataProvidersEvent::class, PermissionMatrixPersonalDataProviderListener::class);
        $context->registerEventListener(RegisterRetentionProvidersEvent::class, PermissionMatrixRetentionProviderListener::class);
        $context->registerServiceAlias(NativeSharingSourceInterface::class, NextcloudSharingSource::class);
        $context->registerServiceAlias(GroupFoldersSourceInterface::class, NextcloudGroupFoldersSource::class);
        $context->registerServiceAlias(GroupFoldersManagerProviderInterface::class, NextcloudGroupFoldersManagerProvider::class);
        $context->registerServiceAlias(PermissionProviderSourceInterface::class, NextcloudPermissionProviderSource::class);
        $context->registerServiceAlias(TemporaryAdminAccessChecker::class, TemporaryAdminAccessService::class);
        $context->registerServiceAlias(TemporaryAdminAccessRepositoryInterface::class, TemporaryAdminAccessRepository::class);
    }

    public function boot(IBootContext $context): void {
    }
}
