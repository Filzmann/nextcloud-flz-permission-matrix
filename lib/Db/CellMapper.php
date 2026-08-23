<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Db;

use OCA\FilzmannPermissionMatrix\Model\MatrixRow;
use OCP\IDBConnection;

class CellMapper {
    private const TABLE = 'permission_matrix_cells';

    public function __construct(
        private IDBConnection $db
    ) {
    }

    /**
     * @param MatrixRow[] $rows
     */
    public function saveCells(string $snapshotId, array $rows): void {
        foreach ($rows as $row) {
            foreach ($row->cells() as $groupId => $value) {
                $qb = $this->db->getQueryBuilder();
                $qb->insert(self::TABLE)
                    ->values([
                        'snapshot_uuid' => $qb->createNamedParameter($snapshotId),
                        'row_key' => $qb->createNamedParameter($row->key()),
                        'group_id' => $qb->createNamedParameter((string)$groupId),
                        'cell_value' => $qb->createNamedParameter((string)$value),
                    ]);
                $qb->executeStatement();
            }
        }
    }
}
