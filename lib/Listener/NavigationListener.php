<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Listener;

use OCA\BrPermissionMatrix\AppInfo\Application;
use OCA\BrPermissionMatrix\Service\AccessService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

/**
 * @template-extends IEventListener<LoadAdditionalEntriesEvent>
 */
class NavigationListener implements IEventListener {
    public function __construct(
        private IUserSession $userSession,
        private AccessService $access,
        private INavigationManager $navigation,
        private IURLGenerator $url
    ) {
    }

    public function handle(Event $event): void {
        if (!$event instanceof LoadAdditionalEntriesEvent) {
            return;
        }

        $user = $this->userSession->getUser();
        if ($user === null || !$this->access->canViewUserId($user->getUID())) {
            return;
        }

        $this->navigation->add(fn(): array => [
            'id' => Application::APP_ID,
            'type' => INavigationManager::TYPE_APPS,
            'app' => Application::APP_ID,
            'href' => $this->url->linkToRoute(Application::APP_ID . '.page.index'),
            'icon' => $this->url->imagePath(Application::APP_ID, 'app.svg'),
            'name' => 'Berechtigungsmatrix',
            'order' => 83,
        ]);
    }
}
