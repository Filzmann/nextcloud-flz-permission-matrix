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
 * - Controller prüfen hier vor jedem Datenzugriff; Navigation nutzt dieselbe Regel nur für
 *   die Sichtbarkeit. flz_organization liefert ausschließlich die kanonische BR-Identität.
 *
 * Vertrag:
 * - Deny by default: anonyme und nicht zugeordnete Nutzer*innen erhalten keinen Zugriff.
 * - Datenschutzbeauftragte verwalten; BR-Mitglieder lesen, exportieren und scannen manuell.
 * - Native Nextcloud-Admins benötigen zusätzlich eine aktive app-lokale Freigabe.
 */
class AccessService {
    private const PRIVACY_OFFICER_GROUP = 'Datenschutzbeauftragte';

    public function __construct(
        private IUserSession $userSession,
        private IGroupManager $groups,
        private BrMembershipService $brMembership,
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

    public function canScanCurrentUser(): bool {
        $uid = $this->currentUserId();

        return $uid !== null && $this->canScanUserId($uid);
    }

    public function canViewUserId(string $uid): bool {
        return $this->canScanUserId($uid);
    }

    public function canScanUserId(string $uid): bool {
        $brGroupId = $this->brMembership->memberGroupId();

        return $this->canManageUserId($uid)
            || ($brGroupId !== null && $this->groups->isInGroup($uid, $brGroupId));
    }

    public function canManageUserId(string $uid): bool {
        return $this->groups->isInGroup($uid, self::PRIVACY_OFFICER_GROUP)
            || ($this->groups->isAdmin($uid) && $this->temporaryAdminAccess->hasActiveGrant($uid));
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

    public function assertCanScan(): void {
        if (!$this->canScanCurrentUser()) {
            throw new AccessDeniedException('Zugriff verweigert.');
        }
    }
}
