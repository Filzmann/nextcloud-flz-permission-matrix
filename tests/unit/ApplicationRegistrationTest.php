<?php

declare(strict_types=1);

namespace OCP\AppFramework {
    class App { public function __construct(string $appId, array $urlParams = []) {} }
}
namespace OCP\AppFramework\Bootstrap {
    interface IBootstrap {}
    interface IBootContext {}
    interface IRegistrationContext {
        public function registerServiceAlias(string $alias, string $target): void;
        public function registerEventListener(string $event, string $listener): void;
    }
}

namespace {
    use OCA\FilzmannPermissionMatrix\AppInfo\Application;
    use OCA\FilzmannPermissionMatrix\Service\NativeSharingSourceInterface;
    use OCA\FilzmannPermissionMatrix\Service\NextcloudSharingSource;
    use OCP\AppFramework\Bootstrap\IRegistrationContext;

    $context = new class implements IRegistrationContext {
        public array $aliases = [];
        public array $listeners = [];

        public function registerServiceAlias(string $alias, string $target): void {
            $this->aliases[$alias] = $target;
        }

        public function registerEventListener(string $event, string $listener): void {
            $this->listeners[$event] = $listener;
        }
    };

    (new Application())->register($context);

    assertSameValue(
        NextcloudSharingSource::class,
        $context->aliases[NativeSharingSourceInterface::class] ?? null,
        'The narrow read-only source contract must resolve to the native Nextcloud implementation.'
    );
    assertSameValue(
        \OCA\FilzmannPermissionMatrix\Listener\StandaloneNavigationListener::class,
        $context->listeners[\OCP\Navigation\Events\LoadAdditionalEntriesEvent::class] ?? null,
        'The standalone app must register its own native navigation entry.'
    );
    assertSameValue(
        \OCA\FilzmannPermissionMatrix\Privacy\PermissionMatrixPersonalDataProviderListener::class,
        $context->listeners[\OCA\FilzmannDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent::class] ?? null,
        'The app must register its optional V1 privacy provider lazily.'
    );
    assertSameValue(
        \OCA\FilzmannPermissionMatrix\Privacy\PermissionMatrixRetentionProviderListener::class,
        $context->listeners[\OCA\FilzmannDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent::class] ?? null,
        'The app must register its optional V1 retention preview provider lazily.'
    );

    echo 'Application registration tests passed' . PHP_EOL;
}
