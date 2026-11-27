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
    use OCA\FlzPermissionMatrix\Service\BrMembershipService;
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

    class AccessTestBrMembership extends BrMembershipService {
        public function __construct(private ?string $memberGroupId) {}
        public function memberGroupId(): ?string { return $this->memberGroupId; }
    }

    function accessServiceFor(?string $uid, bool $activeGrant = false, ?string $brGroupId = 'BR-Kanonisch'): AccessService {
        return new AccessService(
            new AccessTestSession($uid === null ? null : new AccessTestUser($uid)),
            new AccessTestGroups(
                ['cloud-admin'],
                [
                    'br-member' => ['BR-Kanonisch'],
                    'dpo' => ['Datenschutzbeauftragte'],
                    'other' => ['Unrelated'],
                ]
            ),
            new AccessTestBrMembership($brGroupId),
            new class($activeGrant) implements TemporaryAdminAccessChecker { public function __construct(private bool $active) {} public function hasActiveGrant(string $uid): bool { return $this->active; } }
        );
    }

    assertSameValue(false, accessServiceFor(null)->canViewCurrentUser(), 'Anonymous sessions must be denied.');
    assertSameValue(true, accessServiceFor('br-member')->canViewCurrentUser(), 'Canonical BR members must be able to read the matrix.');
    assertSameValue(true, accessServiceFor('br-member')->canScanCurrentUser(), 'Canonical BR members must be able to start a manual scan.');
    assertSameValue(false, accessServiceFor('br-member')->canManageCurrentUser(), 'BR identity must not imply baseline or configuration rights.');
    assertSameValue(true, accessServiceFor('dpo')->canManageCurrentUser(), 'The fixed DPO group must have administrative matrix rights.');
    assertSameValue(true, accessServiceFor('dpo')->canViewCurrentUser(), 'DPO administration rights include read access.');
    assertSameValue(true, accessServiceFor('dpo')->canScanCurrentUser(), 'DPO administration rights include manual scans.');
    assertSameValue(false, accessServiceFor('cloud-admin')->canManageCurrentUser(), 'Nextcloud admins must not manage the matrix without an app-local grant.');
    assertSameValue(true, accessServiceFor('cloud-admin', true)->canManageCurrentUser(), 'An active app-local grant enables matrix management for a Nextcloud admin.');
    assertSameValue(false, accessServiceFor('br-member', false, null)->canViewCurrentUser(), 'Missing BR provider data must deny the BR-derived grant.');
    assertSameValue(true, accessServiceFor('dpo', false, null)->canManageCurrentUser(), 'A missing BR provider must not remove the independent fixed DPO grant.');
    assertSameValue(false, accessServiceFor('other')->canViewCurrentUser(), 'Unrelated group membership must be denied.');

    try {
        accessServiceFor('other')->assertCanView();
        throw new RuntimeException('Denied viewers must trigger AccessDeniedException.');
    } catch (AccessDeniedException $e) {
        assertSameValue('Zugriff verweigert.', $e->getMessage(), 'Denied access needs a safe public message.');
    }

    echo 'AccessService tests passed' . PHP_EOL;
}
