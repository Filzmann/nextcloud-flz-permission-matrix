<?php

declare(strict_types=1);

namespace OCP\EventDispatcher {
    class Event {}
    interface IEventListener { public function handle(Event $event): void; }
}
namespace OCP\Navigation\Events {
    class LoadAdditionalEntriesEvent extends \OCP\EventDispatcher\Event {}
}
namespace OCP {
    interface IGroupManager {}
    interface IUserSession {}
    interface INavigationManager {
        public const TYPE_APPS = 'link';
        public function add(callable $entry): void;
    }
    interface IURLGenerator {
        public function linkToRoute(string $routeName, array $arguments = []): string;
        public function imagePath(string $appName, string $file): string;
    }
}

namespace {
    use OCA\FilzmannPermissionMatrix\Listener\StandaloneNavigationListener;
    use OCA\FilzmannPermissionMatrix\Service\AccessService;
    use OCP\INavigationManager;
    use OCP\IURLGenerator;
    use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

    $navigation = new class implements INavigationManager {
        public array $entries = [];
        public function add(callable $entry): void { $this->entries[] = $entry; }
    };
    $url = new class implements IURLGenerator {
        public function linkToRoute(string $routeName, array $arguments = []): string { return '/route/' . $routeName; }
        public function imagePath(string $appName, string $file): string { return '/image/' . $appName . '/' . $file; }
    };
    $allowed = new class extends AccessService {
        public function __construct() {}
        public function canViewCurrentUser(): bool { return true; }
    };

    $listener = new StandaloneNavigationListener($allowed, $navigation, $url);
    $listener->handle(new \OCP\EventDispatcher\Event());
    if ($navigation->entries !== []) throw new RuntimeException('Fremdes Event erzeugt einen Matrix-Einstieg.');

    $listener->handle(new LoadAdditionalEntriesEvent());
    $entry = ($navigation->entries[0] ?? static fn(): array => [])();
    if (($entry['id'] ?? null) !== 'filzmann_permission_matrix'
        || ($entry['href'] ?? null) !== '/route/filzmann_permission_matrix.page.index'
        || ($entry['icon'] ?? null) !== '/image/filzmann_permission_matrix/app.svg') {
        throw new RuntimeException('Der neutrale Standalone-Einstieg fehlt.');
    }

    $deniedNavigation = new class implements INavigationManager {
        public array $entries = [];
        public function add(callable $entry): void { $this->entries[] = $entry; }
    };
    $denied = new class extends AccessService {
        public function __construct() {}
        public function canViewCurrentUser(): bool { return false; }
    };
    (new StandaloneNavigationListener($denied, $deniedNavigation, $url))->handle(new LoadAdditionalEntriesEvent());
    if ($deniedNavigation->entries !== []) throw new RuntimeException('Nicht berechtigte Person erhält einen Matrix-Einstieg.');

    echo "Permission Matrix standalone navigation test passed\n";
}
