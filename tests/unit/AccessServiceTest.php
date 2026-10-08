<?php

declare(strict_types=1);

namespace OCP {
    interface IUser {
        public function getUID(): string;
    }

    interface IUserSession {
        public function getUser(): ?IUser;
    }

    interface IGroupManager {
        public function isAdmin(string $uid): bool;
        public function isInGroup(string $uid, string $groupId): bool;
    }
}
namespace OCA\FlzPermissionMatrix\Service { interface TemporaryAdminAccessChecker { public function hasActiveGrant(string $uid): bool; } }

namespace {
    use OCA\FlzPermissionMatrix\Exception\AccessDeniedException;
    use OCA\FlzPermissionMatrix\Service\AccessService;
    use OCA\FlzPermissionMatrix\Service\ConfigService;
    use OCA\FlzPermissionMatrix\Service\TemporaryAdminAccessChecker;
    use OCP\IGroupManager;
    use OCP\IUser;
    use OCP\IUserSession;

    class AccessTestUser implements IUser {
        public function __construct(private string $uid) {
        }

        public function getUID(): string {
            return $this->uid;
        }
    }

    class AccessTestSession implements IUserSession {
        public function __construct(private ?IUser $user) {
        }

        public function getUser(): ?IUser {
            return $this->user;
        }
    }

    class AccessTestGroups implements IGroupManager {
        public function __construct(private array $admins, private array $memberships) {
        }

        public function isAdmin(string $uid): bool {
            return in_array($uid, $this->admins, true);
        }

        public function isInGroup(string $uid, string $groupId): bool {
            return in_array($groupId, $this->memberships[$uid] ?? [], true);
        }
    }

    class AccessTestConfig extends ConfigService {
        public function __construct() {
        }

        public function viewerGroups(): array {
            return ['Matrix-Viewer'];
        }

        public function adminGroups(): array {
            return ['Matrix-Admin'];
        }
    }

    function accessServiceFor(?string $uid, bool $activeGrant = false): AccessService {
        return new AccessService(
            new AccessTestSession($uid === null ? null : new AccessTestUser($uid)),
            new AccessTestGroups(
                ['cloud-admin'],
                [
                    'viewer' => ['Matrix-Viewer'],
                    'app-admin' => ['Matrix-Admin'],
                    'other' => ['Unrelated'],
                ]
            ),
            new AccessTestConfig(),
            new class($activeGrant) implements TemporaryAdminAccessChecker { public function __construct(private bool $active) {} public function hasActiveGrant(string $uid): bool { return $this->active; } }
        );
    }

    assertSameValue(false, accessServiceFor(null)->canViewCurrentUser(), 'Anonymous sessions must be denied.');
    assertSameValue(true, accessServiceFor('viewer')->canViewCurrentUser(), 'Configured viewer groups may read the matrix.');
    assertSameValue(false, accessServiceFor('viewer')->canManageCurrentUser(), 'Viewer groups must not gain management rights.');
    assertSameValue(true, accessServiceFor('app-admin')->canManageCurrentUser(), 'Configured app admins may manage the matrix.');
    assertSameValue(true, accessServiceFor('app-admin')->canViewCurrentUser(), 'Management rights include read access.');
    assertSameValue(false, accessServiceFor('cloud-admin')->canManageCurrentUser(), 'Nextcloud admins must not manage the matrix without an app-local grant.');
    assertSameValue(true, accessServiceFor('cloud-admin', true)->canManageCurrentUser(), 'An active app-local grant enables matrix management for a Nextcloud admin.');
    assertSameValue(false, accessServiceFor('other')->canViewCurrentUser(), 'Unrelated group membership must be denied.');

    try {
        accessServiceFor('other')->assertCanView();
        throw new RuntimeException('Denied viewers must trigger AccessDeniedException.');
    } catch (AccessDeniedException $e) {
        assertSameValue('Zugriff verweigert.', $e->getMessage(), 'Denied access needs a safe public message.');
    }

    echo 'AccessService tests passed' . PHP_EOL;
}
