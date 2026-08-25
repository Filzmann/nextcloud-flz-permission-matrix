<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Service;

use InvalidArgumentException;
use OCA\FilzmannPermissionMatrix\Db\TemporaryAdminAccessRepositoryInterface;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroupManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

/** App-lokale Autorität für höchstens 24 Stunden gültigen Admin-Vollzugriff. */
final class TemporaryAdminAccessService implements TemporaryAdminAccessChecker {
    public const MAX_DURATION_MINUTES = 1440;

    public function __construct(
        private IUserSession $session,
        private IGroupManager $groups,
        private TemporaryAdminAccessRepositoryInterface $repository,
        private ITimeFactory $clock,
        private LoggerInterface $logger,
    ) {}

    public function activate(string $targetUid, int $durationMinutes): array {
        $actorUid = $this->requireCurrentAdmin();
        $targetUid = trim($targetUid);
        if ($targetUid === '' || !$this->groups->isAdmin($targetUid)) {
            throw new InvalidArgumentException('Zielkonto ist keine aktuelle Nextcloud-Administration.');
        }
        if ($durationMinutes < 1 || $durationMinutes > self::MAX_DURATION_MINUTES) {
            throw new InvalidArgumentException('Die Freigabedauer muss zwischen 1 und 1440 Minuten liegen.');
        }

        $startsAt = $this->clock->now();
        $endsAt = $startsAt->modify('+' . $durationMinutes . ' minutes');
        $grant = $this->repository->replaceActive($targetUid, $actorUid, $startsAt, $endsAt);
        $this->logger->info('Temporary app admin access granted.', ['grant_id' => $grant['id'] ?? null]);
        return $grant;
    }

    public function revoke(string $targetUid): bool {
        $actorUid = $this->requireCurrentAdmin();
        $revoked = $this->repository->revokeActive(trim($targetUid), $actorUid, $this->clock->now());
        if ($revoked) {
            $this->logger->info('Temporary app admin access revoked.');
        }
        return $revoked;
    }

    public function hasActiveGrant(string $uid): bool {
        $uid = trim($uid);
        if ($uid === '' || !$this->groups->isAdmin($uid)) {
            return false;
        }
        try {
            return $this->repository->activeFor($uid, $this->clock->now()) !== null;
        } catch (Throwable) {
            $this->logger->error('Temporary app admin access check failed.');
            return false;
        }
    }

    public function state(): array {
        $this->requireCurrentAdmin();
        return ['maxDurationMinutes' => self::MAX_DURATION_MINUTES, 'history' => $this->repository->history()];
    }

    private function requireCurrentAdmin(): string {
        $uid = $this->session->getUser()?->getUID() ?? '';
        if ($uid === '' || !$this->groups->isAdmin($uid)) {
            throw new TemporaryAdminAccessDeniedException('Zugriff verweigert.');
        }
        return $uid;
    }
}


