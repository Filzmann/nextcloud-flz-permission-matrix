<?php
declare(strict_types=1);
namespace OCA\FilzmannDataProtection\PublicApi\V1;
final class RegisterRetentionProvidersEvent extends \OCP\EventDispatcher\Event {
    private array $providers = [];
    public function register(RetentionProvider $provider): void { $this->providers[$provider->descriptor()->appId()] = $provider; }
    public function providers(): array { return $this->providers; }
}
