<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Service;

use OCP\IAppConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\Share\IShare;

/**
 * Zweck: Liest globale Sharing-Policies und konkrete Gruppenfreigaben aus oeffentlichen Nextcloud-APIs.
 *
 * Sicherheitsvertrag:
 * - getSharesBy() wird mit onlyValid=false aufgerufen, weil true laut Nextcloud-Vertrag
 *   ungueltige Shares loeschen darf und damit keine reine Leseoperation waere.
 * - Benutzer-IDs werden nur zur Owner-Enumeration verwendet und nie zurueckgegeben.
 * - Share-IDs werden vor der Rueckgabe irreversibel gehasht.
 */
final class NextcloudSharingSource implements NativeSharingSourceInterface {
    private const PAGE_SIZE = 500;
    private const MAX_USERS = 100000;

    public function __construct(
        private IManager $shares,
        private IUserManager $users,
        private IAppConfig $appConfig
    ) {
    }

    public function policies(): array {
        $definitions = [
            'sharing' => [fn(): bool => $this->shares->shareApiEnabled(), 'nextcloud:OCP\\Share\\IManager::shareApiEnabled', 'high'],
            'group_sharing' => [fn(): bool => $this->shares->allowGroupSharing(), 'nextcloud:OCP\\Share\\IManager::allowGroupSharing', 'high'],
            'link_sharing' => [fn(): bool => $this->shares->shareApiAllowLinks(), 'nextcloud:OCP\\Share\\IManager::shareApiAllowLinks', 'high'],
            'resharing' => [fn(): bool => $this->appConfig->getValueBool('core', 'shareapi_allow_resharing', true, true), 'nextcloud:IAppConfig(core/shareapi_allow_resharing)', 'medium'],
            'link_password' => [fn(): bool => $this->shares->shareApiLinkEnforcePassword(), 'nextcloud:OCP\\Share\\IManager::shareApiLinkEnforcePassword', 'high'],
            'link_expiry' => [fn(): bool => $this->shares->shareApiLinkDefaultExpireDate(), 'nextcloud:OCP\\Share\\IManager::shareApiLinkDefaultExpireDate', 'high'],
            'link_expiry_enforced' => [fn(): bool => $this->shares->shareApiLinkDefaultExpireDateEnforced(), 'nextcloud:OCP\\Share\\IManager::shareApiLinkDefaultExpireDateEnforced', 'high'],
            'public_upload' => [fn(): bool => $this->shares->shareApiLinkAllowPublicUpload(), 'nextcloud:OCP\\Share\\IManager::shareApiLinkAllowPublicUpload', 'high'],
            'group_members_only' => [fn(): bool => $this->shares->shareWithGroupMembersOnly(), 'nextcloud:OCP\\Share\\IManager::shareWithGroupMembersOnly', 'high'],
            'internal_expiry' => [fn(): bool => $this->shares->shareApiInternalDefaultExpireDate(), 'nextcloud:OCP\\Share\\IManager::shareApiInternalDefaultExpireDate', 'high'],
            'remote_expiry' => [fn(): bool => $this->shares->shareApiRemoteDefaultExpireDate(), 'nextcloud:OCP\\Share\\IManager::shareApiRemoteDefaultExpireDate', 'high'],
        ];
        $values = [];
        $warnings = [];

        foreach ($definitions as $key => [$reader, $source, $confidence]) {
            try {
                $value = $reader();
            } catch (\Throwable) {
                $value = null;
                $confidence = 'low';
                $warnings[] = 'Eine native Nextcloud-Sharing-Policy konnte nicht gelesen werden: ' . $key . '.';
            }
            $values[$key] = ['value' => $value, 'source' => $source, 'confidence' => $confidence];
        }

        return ['values' => $values, 'warnings' => array_values(array_unique($warnings))];
    }

    public function groupShares(): array {
        try {
            [$userIds, $usersComplete] = $this->userIds();
        } catch (\Throwable) {
            return [
                'shares' => [],
                'complete' => false,
                'warnings' => ['Die Nextcloud-Benutzerbasis fuer Gruppenfreigaben konnte nicht gelesen werden.'],
            ];
        }

        $result = [];
        $warnings = [];
        $complete = $usersComplete;
        foreach ($userIds as $userId) {
            $offset = 0;
            do {
                try {
                    $page = $this->shares->getSharesBy(
                        $userId,
                        IShare::TYPE_GROUP,
                        null,
                        true,
                        self::PAGE_SIZE,
                        $offset,
                        false
                    );
                } catch (\Throwable) {
                    $complete = false;
                    $warnings[] = 'Mindestens ein Owner-Bestand an Gruppenfreigaben konnte nicht gelesen werden.';
                    break;
                }

                foreach ($page as $share) {
                    if (!$share instanceof IShare) {
                        $complete = false;
                        $warnings[] = 'Die native Share-Quelle lieferte einen unbekannten Datensatztyp.';
                        continue;
                    }
                    try {
                        $reference = substr(hash('sha256', $share->getFullId()), 0, 24);
                        $result[$reference] = [
                            'reference' => $reference,
                            'group_id' => (string)$share->getSharedWith(),
                            'target' => (string)$share->getTarget(),
                            'node_type' => strtolower((string)$share->getNodeType()),
                            'permissions' => (int)$share->getPermissions(),
                            'inherited' => $share->getParent() !== null,
                        ];
                    } catch (\Throwable) {
                        $complete = false;
                        $warnings[] = 'Mindestens eine Gruppenfreigabe enthielt nicht auslesbare Metadaten.';
                    }
                }

                $count = count($page);
                $offset += $count;
            } while ($count === self::PAGE_SIZE);
        }

        $shares = array_values($result);
        usort($shares, static fn(array $a, array $b): int => strnatcasecmp(
            $a['group_id'] . ' ' . $a['target'] . ' ' . $a['reference'],
            $b['group_id'] . ' ' . $b['target'] . ' ' . $b['reference']
        ));

        return ['shares' => $shares, 'complete' => $complete, 'warnings' => array_values(array_unique($warnings))];
    }

    /** @return array{0: string[], 1: bool} */
    private function userIds(): array {
        $ids = [];
        $complete = true;
        foreach (['active', 'disabled'] as $kind) {
            $offset = 0;
            do {
                $page = $kind === 'active'
                    ? $this->users->search('', self::PAGE_SIZE, $offset)
                    : $this->users->getDisabledUsers(self::PAGE_SIZE, $offset, '');
                if (!is_array($page)) {
                    throw new \UnexpectedValueException('User source must return an array.');
                }
                foreach ($page as $user) {
                    if (!$user instanceof IUser) {
                        $complete = false;
                        continue;
                    }
                    $userId = trim((string)$user->getUID());
                    if ($userId !== '') {
                        $ids[$userId] = true;
                    }
                }
                $count = count($page);
                $offset += $count;
                if (count($ids) >= self::MAX_USERS) {
                    $complete = false;
                    break;
                }
            } while ($count === self::PAGE_SIZE);
        }

        return [array_keys($ids), $complete];
    }
}
