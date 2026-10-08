<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Service;

use OCA\FlzPermissionMatrix\Exception\AccessDeniedException;
use OCP\IGroupManager;
use OCP\IUserSession;

/**
 * Zweck: Zentrale serverseitige Autoritaet fuer Viewer- und Verwaltungsrechte der App.
 *
 * Zusammenspiel:
 * - Controller pruefen hier vor jedem Datenzugriff; Navigation nutzt dieselbe Regel nur fuer
 *   die Sichtbarkeit. ConfigService liefert die konfigurierten Gruppen.
 *
 * Vertrag:
 * - Deny by default: anonyme und nicht zugeordnete Nutzer*innen erhalten keinen Zugriff.
 * - Konfigurierte App-Admins duerfen verwalten; Verwaltung umfasst Lesen.
 * - Native Nextcloud-Admins benoetigen zusaetzlich eine aktive app-lokale Freigabe.
 */
class AccessService {
    public function __construct(
        private IUserSession $userSession,
        private IGroupManager $groups,
        private ConfigService $config,
        private TemporaryAdminAccessChecker $temporaryAdminAccess,
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
        return ($this->groups->isAdmin($uid) && $this->temporaryAdminAccess->hasActiveGrant($uid)) || $this->isInAnyGroup($uid, $this->config->adminGroups());
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
