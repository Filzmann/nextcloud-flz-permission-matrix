<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\AppInfo;

use OCA\FlzPermissionMatrix\Listener\StandaloneNavigationListener;
use OCA\FlzPermissionMatrix\Privacy\PermissionMatrixPersonalDataProviderListener;
use OCA\FlzPermissionMatrix\Privacy\PermissionMatrixProcessingMetadataProviderListener;
use OCA\FlzPermissionMatrix\Privacy\PermissionMatrixRetentionProviderListener;
use OCA\FlzPermissionMatrix\Service\GroupFoldersManagerProviderInterface;
use OCA\FlzPermissionMatrix\Service\GroupFoldersSourceInterface;
use OCA\FlzPermissionMatrix\Service\NativeSharingSourceInterface;
use OCA\FlzPermissionMatrix\Service\NextcloudGroupFoldersManagerProvider;
use OCA\FlzPermissionMatrix\Service\NextcloudGroupFoldersSource;
use OCA\FlzPermissionMatrix\Service\NextcloudSharingSource;
use OCA\FlzPermissionMatrix\Service\NextcloudPermissionProviderSource;
use OCA\FlzPermissionMatrix\Service\PermissionProviderSourceInterface;
use OCA\FlzPermissionMatrix\Db\TemporaryAdminAccessRepository;
use OCA\FlzPermissionMatrix\Db\TemporaryAdminAccessRepositoryInterface;
use OCA\FlzPermissionMatrix\Service\TemporaryAdminAccessChecker;
use OCA\FlzPermissionMatrix\Service\TemporaryAdminAccessService;
use OCA\FlzDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;
use OCA\FlzDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent;
use OCA\FlzDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent;
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
        $context->registerEventListener(RegisterProcessingMetadataProvidersEvent::class, PermissionMatrixProcessingMetadataProviderListener::class);
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
