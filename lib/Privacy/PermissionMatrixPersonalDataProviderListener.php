<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Privacy;

use OCA\FlzDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

final class PermissionMatrixPersonalDataProviderListener implements IEventListener {
    public function __construct(private PermissionMatrixPersonalDataProvider $provider) {
    }

    public function handle(Event $event): void {
        if ($event instanceof RegisterPersonalDataProvidersEvent) {
            $event->register($this->provider);
        }
    }
}
