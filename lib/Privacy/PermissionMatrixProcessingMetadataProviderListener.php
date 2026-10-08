<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Privacy;

use OCA\FlzDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

final class PermissionMatrixProcessingMetadataProviderListener implements IEventListener {
    public function __construct(private PermissionMatrixProcessingMetadataProvider $provider) {}

    public function handle(Event $event): void {
        if ($event instanceof RegisterProcessingMetadataProvidersEvent) {
            $event->register($this->provider);
        }
    }
}
