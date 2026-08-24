<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Service;

use OCA\FilzmannPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;
use OCP\EventDispatcher\IEventDispatcher;
use Throwable;

final class NextcloudPermissionProviderSource implements PermissionProviderSourceInterface {
    private ?RegisterPermissionProvidersEvent $event = null;
    private bool $discoveryFailed = false;

    public function __construct(private IEventDispatcher $dispatcher) {}

    public function providers(): array {
        $this->discover();
        return $this->event?->providers() ?? [];
    }

    public function registrationFailures(): array {
        $this->discover();
        if ($this->discoveryFailed) {
            return ['discovery' => 'Provider discovery failed.'];
        }
        return $this->event?->registrationFailures() ?? [];
    }

    public function hasProvider(string $appId): bool {
        return isset($this->providers()[$appId]);
    }

    private function discover(): void {
        if ($this->event !== null || $this->discoveryFailed) {
            return;
        }
        $event = new RegisterPermissionProvidersEvent();
        try {
            $this->dispatcher->dispatchTyped($event);
            $this->event = $event;
        } catch (Throwable) {
            $this->discoveryFailed = true;
        }
    }
}
