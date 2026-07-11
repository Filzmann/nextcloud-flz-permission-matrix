<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Service;

use OCA\BrPermissionMatrix\Exception\AccessDeniedException;
use OCP\IGroupManager;
use OCP\IUserSession;

class AccessService {
    public function __construct(
        private IUserSession $userSession,
        private IGroupManager $groups,
        private ConfigService $config
    ) {
    }

    public function currentUserId(): ?string {
        $user = $this->userSession->getUser();

        return $user?->getUID();
    }

    public function canViewCurrentUser(): bool {
        $uid = $this->currentUserId();

        return $uid !== null && $this->canViewUserId($uid);
    }

    public function canManageCurrentUser(): bool {
        $uid = $this->currentUserId();

        return $uid !== null && $this->canManageUserId($uid);
    }

    public function canViewUserId(string $uid): bool {
        return $this->canManageUserId($uid) || $this->isInAnyGroup($uid, $this->config->viewerGroups());
    }

    public function canManageUserId(string $uid): bool {
        return $this->groups->isAdmin($uid) || $this->isInAnyGroup($uid, $this->config->adminGroups());
    }

    public function assertCanView(): void {
        if (!$this->canViewCurrentUser()) {
            throw new AccessDeniedException('Zugriff verweigert.');
        }
    }

    public function assertCanManage(): void {
        if (!$this->canManageCurrentUser()) {
            throw new AccessDeniedException('Zugriff verweigert.');
        }
    }

    private function isInAnyGroup(string $uid, array $groupIds): bool {
        foreach ($groupIds as $groupId) {
            if ($this->groups->isInGroup($uid, $groupId)) {
                return true;
            }
        }

        return false;
    }
}
