<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Db;

use DateTimeImmutable;
use OCA\FilzmannPermissionMatrix\Model\Snapshot;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class SnapshotMapper {
    private const TABLE = 'permission_matrix_snapshots';

    public function __construct(
        private IDBConnection $db,
        private JsonCodec $json,
        private RowMapper $rows,
        private CellMapper $cells,
        private DiffMapper $diffs,
        private AdapterStatusMapper $adapterStatus
    ) {
    }

    public function insert(Snapshot $snapshot, ?string $createdBy, ?string $baselineId): void {
        $summary = $snapshot->summary();
        $qb = $this->db->getQueryBuilder();
        $qb->insert(self::TABLE)
            ->values([
                'snapshot_uuid' => $qb->createNamedParameter($snapshot->snapshotId()),
                'created_at' => $qb->createNamedParameter(new DateTimeImmutable($snapshot->createdAt()), IQueryBuilder::PARAM_DATE),
                'created_by_uid' => $qb->createNamedParameter($createdBy),
                'nextcloud_version' => $qb->createNamedParameter($snapshot->nextcloudVersion()),
                'group_count' => $qb->createNamedParameter((int)($summary['group_count'] ?? count($snapshot->groups())), IQueryBuilder::PARAM_INT),
                'app_count' => $qb->createNamedParameter((int)($summary['app_count'] ?? count($snapshot->apps())), IQueryBuilder::PARAM_INT),
                'object_count' => $qb->createNamedParameter((int)($summary['object_count'] ?? count($snapshot->matrix())), IQueryBuilder::PARAM_INT),
                'warning_count' => $qb->createNamedParameter((int)($summary['warning_count'] ?? count($snapshot->warnings())), IQueryBuilder::PARAM_INT),
                'unsupported_count' => $qb->createNamedParameter((int)($summary['unsupported_count'] ?? count($snapshot->unsupportedApps())), IQueryBuilder::PARAM_INT),
                'compliance_status' => $qb->createNamedParameter((string)($summary['compliance_status'] ?? 'UNKNOWN')),
                'summary_json' => $qb->createNamedParameter($this->json->encode($summary)),
                'snapshot_json' => $qb->createNamedParameter($this->json->encode($snapshot->toArray())),
            ]);
        $qb->executeStatement();

        $this->rows->saveRows($snapshot->snapshotId(), $snapshot->matrix());
        $this->cells->saveCells($snapshot->snapshotId(), $snapshot->matrix());
        $this->diffs->saveDiffs($baselineId, $snapshot->snapshotId(), $snapshot->diffToBaseline());
        $this->adapterStatus->saveStatus($snapshot->snapshotId(), $snapshot->adapterStatus());
    }

    public function latest(): ?Snapshot {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from(self::TABLE)
            ->orderBy('created_at', 'DESC')
            ->setMaxResults(1);

        $row = $qb->executeQuery()->fetch();

        return $row === false ? null : $this->snapshotFromRow($row);
    }

    public function find(string $snapshotId): ?Snapshot {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('snapshot_uuid', $qb->createNamedParameter($snapshotId)));

        $row = $qb->executeQuery()->fetch();

        return $row === false ? null : $this->snapshotFromRow($row);
    }

    public function list(int $limit = 50): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('snapshot_uuid', 'created_at', 'nextcloud_version', 'group_count', 'app_count', 'object_count', 'warning_count', 'unsupported_count', 'compliance_status')
            ->from(self::TABLE)
            ->orderBy('created_at', 'DESC')
            ->setMaxResults($limit);

        return array_map(function(array $row): array {
            return [
                'snapshot_id' => (string)$row['snapshot_uuid'],
                'created_at' => (string)$row['created_at'],
                'nextcloud_version' => (string)$row['nextcloud_version'],
                'group_count' => (int)$row['group_count'],
                'app_count' => (int)$row['app_count'],
                'object_count' => (int)$row['object_count'],
                'warning_count' => (int)$row['warning_count'],
                'unsupported_count' => (int)$row['unsupported_count'],
                'compliance_status' => (string)$row['compliance_status'],
            ];
        }, $qb->executeQuery()->fetchAll());
    }

    public function purgeOlderThanLatest(int $keep): void {
        $rows = $this->list(1000);
        if (count($rows) <= $keep) {
            return;
        }

        $deleteIds = array_slice(array_column($rows, 'snapshot_id'), $keep);
        foreach ($deleteIds as $snapshotId) {
            foreach (['permission_matrix_cells', 'permission_matrix_rows', 'permission_matrix_diffs', 'permission_matrix_adapter_status'] as $table) {
                $qb = $this->db->getQueryBuilder();
                $qb->delete($table)
                    ->where($qb->expr()->eq('snapshot_uuid', $qb->createNamedParameter($snapshotId)));
                $qb->executeStatement();
            }
            $qb = $this->db->getQueryBuilder();
            $qb->delete(self::TABLE)
                ->where($qb->expr()->eq('snapshot_uuid', $qb->createNamedParameter($snapshotId)));
            $qb->executeStatement();
        }
    }

    private function snapshotFromRow(array $row): ?Snapshot {
        return Snapshot::get($this->json->decode((string)$row['snapshot_json']));
    }
}
