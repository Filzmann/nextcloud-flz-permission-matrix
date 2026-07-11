<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Service;

use OCA\BrPermissionMatrix\Db\AuditLogMapper;
use Psr\Log\LoggerInterface;

/**
 * Zweck: Protokolliert erlaubte, verweigerte und fehlgeschlagene App-Zugriffe datensparsam.
 *
 * Zusammenspiel:
 * - Controller liefern Aktion und optionalen Snapshot-Kontext; AuditLogMapper schreibt nur
 *   in die app-eigene Audit-Tabelle.
 *
 * Vertrag:
 * - Auditfehler duerfen die angeforderte Fachaktion nicht blockieren.
 * - Potenziell geheime oder strukturierte Detailwerte werden nicht unveraendert gespeichert.
 */
class AuditLogService {
    public function __construct(
        private AccessService $access,
        private AuditLogMapper $mapper,
        private LoggerInterface $logger
    ) {
    }

    public function record(string $action, bool $exportGenerated = false, ?string $snapshotId = null, array $details = []): void {
        $uid = $this->access->currentUserId() ?? '';
        $safeDetails = $this->sanitizeDetails($details);

        try {
            $this->mapper->insert($uid, $action, $exportGenerated, $snapshotId, $safeDetails);
        } catch (\Throwable $e) {
            $this->logger->warning('Permission matrix audit log write failed', [
                'app' => 'br_permission_matrix',
                'action' => $action,
                'exception' => $e,
            ]);
        }
    }

    private function sanitizeDetails(array $details): array {
        $blocked = ['password', 'token', 'secret', 'key', 'private'];
        $safe = [];

        foreach ($details as $name => $value) {
            $lower = strtolower((string)$name);
            foreach ($blocked as $needle) {
                if (str_contains($lower, $needle)) {
                    continue 2;
                }
            }
            $safe[(string)$name] = is_scalar($value) || $value === null ? $value : '[structured]';
        }

        return $safe;
    }
}
