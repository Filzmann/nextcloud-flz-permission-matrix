<?php

declare(strict_types=1);

namespace OCA\FlzDataProtection\PublicApi\V1;

use OCP\EventDispatcher\Event;

final class RegisterPersonalDataProvidersEvent extends Event {
    private array $providers = [];
    public function register(PersonalDataProvider $provider): void {
        $this->providers[$provider->descriptor()->appId()] = $provider;
    }
    public function providers(): array { return $this->providers; }
}
