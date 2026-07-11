<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Db;

use DateTimeImmutable;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class AuditLogMapper {
    private const TABLE = 'permission_matrix_audit_log';

    public function __construct(
        private IDBConnection $db,
        private JsonCodec $json
    ) {
    }

    public function insert(string $userId, string $action, bool $exportGenerated, ?string $snapshotId, array $details): void {
        $qb = $this->db->getQueryBuilder();
        $qb->insert(self::TABLE)
            ->values([
                'created_at' => $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATE),
                'user_id' => $qb->createNamedParameter($userId),
                'action' => $qb->createNamedParameter($action),
                'export_generated' => $qb->createNamedParameter($exportGenerated, IQueryBuilder::PARAM_BOOL),
                'snapshot_uuid' => $qb->createNamedParameter($snapshotId),
                'details_json' => $qb->createNamedParameter($this->json->encode($details)),
            ]);
        $qb->executeStatement();
    }
}
