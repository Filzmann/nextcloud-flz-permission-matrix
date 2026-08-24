<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Db;

use OCP\IDBConnection;

class AdapterStatusMapper {
    private const TABLE = 'permission_matrix_adapter_status';

    public function __construct(
        private IDBConnection $db,
        private JsonCodec $json
    ) {
    }

    public function saveStatus(string $snapshotId, array $statusRows): void {
        foreach ($statusRows as $status) {
            $qb = $this->db->getQueryBuilder();
            $qb->insert(self::TABLE)
                ->values([
                    'snapshot_uuid' => $qb->createNamedParameter($snapshotId),
                    'app_id' => $qb->createNamedParameter((string)($status['app_id'] ?? '')),
                    'adapter' => $qb->createNamedParameter((string)($status['adapter'] ?? 'GenericAppAdapter')),
                    'status' => $qb->createNamedParameter((string)($status['status'] ?? 'UNKNOWN')),
                    'confidence' => $qb->createNamedParameter((string)($status['confidence'] ?? 'low')),
                    'warnings_json' => $qb->createNamedParameter($this->json->encode(is_array($status['warnings'] ?? null) ? $status['warnings'] : [])),
                ]);
            $qb->executeStatement();
        }
    }
}
