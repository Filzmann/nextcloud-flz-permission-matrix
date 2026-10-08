<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Privacy;

use OCA\FlzDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

final class PermissionMatrixRetentionProviderListener implements IEventListener {
    public function __construct(private PermissionMatrixRetentionProvider $provider) {}
    public function handle(Event $event): void {
        if ($event instanceof RegisterRetentionProvidersEvent) $event->register($this->provider);
    }
}
