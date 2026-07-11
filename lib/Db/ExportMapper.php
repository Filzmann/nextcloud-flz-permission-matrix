<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Db;

use DateTimeImmutable;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ExportMapper {
    private const TABLE = 'permission_matrix_exports';

    public function __construct(
        private IDBConnection $db
    ) {
    }

    public function insert(string $snapshotId, ?string $createdBy, string $format, string $filename, int $sizeBytes): void {
        $qb = $this->db->getQueryBuilder();
        $qb->insert(self::TABLE)
            ->values([
                'snapshot_uuid' => $qb->createNamedParameter($snapshotId),
                'created_at' => $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATE),
                'created_by_uid' => $qb->createNamedParameter($createdBy),
                'format' => $qb->createNamedParameter($format),
                'filename' => $qb->createNamedParameter($filename),
                'size_bytes' => $qb->createNamedParameter($sizeBytes, IQueryBuilder::PARAM_INT),
            ]);
        $qb->executeStatement();
    }
}
