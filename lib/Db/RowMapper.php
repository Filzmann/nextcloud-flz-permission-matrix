<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Db;

use OCA\BrPermissionMatrix\Model\MatrixRow;
use OCP\IDBConnection;

class RowMapper {
    private const TABLE = 'permission_matrix_rows';

    public function __construct(
        private IDBConnection $db,
        private JsonCodec $json
    ) {
    }

    /**
     * @param MatrixRow[] $rows
     */
    public function saveRows(string $snapshotId, array $rows): void {
        foreach ($rows as $row) {
            $qb = $this->db->getQueryBuilder();
            $qb->insert(self::TABLE)
                ->values([
                    'snapshot_uuid' => $qb->createNamedParameter($snapshotId),
                    'row_key' => $qb->createNamedParameter($row->key()),
                    'object_type' => $qb->createNamedParameter($row->objectType()),
                    'app_id' => $qb->createNamedParameter($row->appId()),
                    'object_name' => $qb->createNamedParameter($row->objectName()),
                    'detail' => $qb->createNamedParameter($row->detail()),
                    'permission_type' => $qb->createNamedParameter($row->permissionType()),
                    'status' => $qb->createNamedParameter($row->status()),
                    'source' => $qb->createNamedParameter($row->source()),
                    'confidence' => $qb->createNamedParameter($row->confidence()),
                    'warnings_json' => $qb->createNamedParameter($this->json->encode($row->warnings())),
                ]);
            $qb->executeStatement();
        }
    }
}
