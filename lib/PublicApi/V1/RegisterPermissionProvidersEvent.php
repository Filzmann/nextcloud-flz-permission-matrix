<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\PublicApi\V1;

use OCP\EventDispatcher\Event;
use Throwable;

final class RegisterPermissionProvidersEvent extends Event {
    public const CONTRACT_VERSION = '1.0';

    /** @var array<string, PermissionProvider> */
    private array $providers = [];
    /** @var array<string, string> */
    private array $registrationFailures = [];

    public function register(PermissionProvider $provider): void {
        try {
            $descriptor = $provider->descriptor();
            $appId = $descriptor->appId();
            if (
                $descriptor->contractVersion() !== self::CONTRACT_VERSION
                || !in_array('permissions', $descriptor->capabilities(), true)
                || isset($this->providers[$appId])
                || isset($this->registrationFailures[$appId])
            ) {
                $this->registrationFailures[$appId] = 'Provider incompatible.';
                unset($this->providers[$appId]);
                return;
            }
            $this->providers[$appId] = $provider;
        } catch (Throwable) {
            $this->registrationFailures[$provider::class] = 'Provider incompatible.';
        }
    }

    /** @return array<string, PermissionProvider> */
    public function providers(): array { return $this->providers; }
    /** @return array<string, string> */
    public function registrationFailures(): array { return $this->registrationFailures; }
}
