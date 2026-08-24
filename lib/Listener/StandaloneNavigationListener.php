<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Listener;

use OCA\FilzmannPermissionMatrix\Service\AccessService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

/** @template-implements IEventListener<LoadAdditionalEntriesEvent> */
final class StandaloneNavigationListener implements IEventListener {
    private const APP_ID = 'filzmann_permission_matrix';

    public function __construct(
        private AccessService $access,
        private INavigationManager $navigation,
        private IURLGenerator $url,
    ) {
    }

    public function handle(Event $event): void {
        if (!$event instanceof LoadAdditionalEntriesEvent || !$this->access->canViewCurrentUser()) {
            return;
        }

        $this->navigation->add(fn(): array => [
            'id' => self::APP_ID,
            'type' => INavigationManager::TYPE_APPS,
            'app' => self::APP_ID,
            'href' => $this->url->linkToRoute(self::APP_ID . '.page.index'),
            'icon' => $this->url->imagePath(self::APP_ID, 'app.svg'),
            'name' => 'Berechtigungsmatrix',
            'order' => 85,
        ]);
    }
}
