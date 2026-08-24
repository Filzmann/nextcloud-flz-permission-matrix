<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Db;

use OCP\IDBConnection;

class DiffMapper {
    private const TABLE = 'permission_matrix_diffs';

    public function __construct(
        private IDBConnection $db
    ) {
    }

    public function saveDiffs(?string $baselineId, string $snapshotId, array $diffs): void {
        foreach ($diffs as $diff) {
            $qb = $this->db->getQueryBuilder();
            $qb->insert(self::TABLE)
                ->values([
                    'baseline_uuid' => $qb->createNamedParameter($baselineId),
                    'snapshot_uuid' => $qb->createNamedParameter($snapshotId),
                    'diff_type' => $qb->createNamedParameter((string)($diff['type'] ?? 'PERMISSION_CHANGED')),
                    'severity' => $qb->createNamedParameter((string)($diff['severity'] ?? 'info')),
                    'row_key' => $qb->createNamedParameter($diff['row_key'] ?? null),
                    'group_id' => $qb->createNamedParameter($diff['group'] ?? null),
                    'old_value' => $qb->createNamedParameter($diff['old'] ?? null),
                    'new_value' => $qb->createNamedParameter($diff['new'] ?? null),
                    'message' => $qb->createNamedParameter($diff['message'] ?? null),
                ]);
            $qb->executeStatement();
        }
    }
}
