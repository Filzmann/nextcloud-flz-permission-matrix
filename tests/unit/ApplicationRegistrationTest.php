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
    }
}

namespace {
    use OCA\BrPermissionMatrix\AppInfo\Application;
    use OCA\BrPermissionMatrix\Service\NativeSharingSourceInterface;
    use OCA\BrPermissionMatrix\Service\NextcloudSharingSource;
    use OCP\AppFramework\Bootstrap\IRegistrationContext;

    $context = new class implements IRegistrationContext {
        public array $aliases = [];

        public function registerServiceAlias(string $alias, string $target): void {
            $this->aliases[$alias] = $target;
        }
    };

    (new Application())->register($context);

    assertSameValue(
        NextcloudSharingSource::class,
        $context->aliases[NativeSharingSourceInterface::class] ?? null,
        'The narrow read-only source contract must resolve to the native Nextcloud implementation.'
    );

    echo 'Application registration tests passed' . PHP_EOL;
}
