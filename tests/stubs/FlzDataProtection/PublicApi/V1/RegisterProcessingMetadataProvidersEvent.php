<?php

declare(strict_types=1);

namespace OCA\FlzDataProtection\PublicApi\V1;

use OCP\EventDispatcher\Event;

final class RegisterProcessingMetadataProvidersEvent extends Event {
    /** @var array<string, ProcessingMetadataProvider> */
    private array $providers = [];

    public function register(ProcessingMetadataProvider $provider): void {
        $this->providers[$provider->descriptor()->appId()] = $provider;
    }

    /** @return array<string, ProcessingMetadataProvider> */
    public function providers(): array { return $this->providers; }
}
