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

    echo 'Application registration tests passed' . PHP_EOL;
}
