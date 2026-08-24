<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Db;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Read-only, subject-bound projection of the app's own personal references. */
class PersonalDataProjectionRepository {
    public function __construct(private IDBConnection $db) {
    }

    /** @return list<array<string, scalar|null>> */
    public function collectForSubject(string $uid, int $limit, string $asOf): array {
        if (trim($uid) === '' || $limit < 1 || $limit > 1000201) {
            throw new InvalidArgumentException('Invalid personal-data projection request.');
        }

        try {
            $upperBound = new DateTimeImmutable($asOf);
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid personal-data projection timestamp.');
        }

        return [
            ...$this->snapshots($uid, $limit, $upperBound),
            ...$this->exports($uid, $limit, $upperBound),
            ...$this->auditRecords($uid, $limit, $upperBound),
        ];
    }

    /** @return list<array<string, scalar|null>> */
    private function snapshots(string $uid, int $limit, DateTimeImmutable $upperBound): array {
        $qb = $this->subjectQuery(
            'permission_matrix_snapshots',
            'created_by_uid',
            $uid,
            $limit,
            $upperBound,
        );
        $qb->select(
            'id',
            'snapshot_uuid',
            'created_at',
            'nextcloud_version',
            'group_count',
            'app_count',
            'object_count',
            'compliance_status',
        );

        return array_map(fn(array $row): array => [
            'source' => 'snapshot',
            'id' => (string)$row['id'],
            'createdAt' => $this->dateValue($row['created_at']),
            'snapshotId' => (string)$row['snapshot_uuid'],
            'nextcloudVersion' => (string)$row['nextcloud_version'],
            'groupCount' => (int)$row['group_count'],
            'appCount' => (int)$row['app_count'],
            'objectCount' => (int)$row['object_count'],
            'complianceStatus' => (string)$row['compliance_status'],
        ], $qb->executeQuery()->fetchAll());
    }

    /** @return list<array<string, scalar|null>> */
    private function exports(string $uid, int $limit, DateTimeImmutable $upperBound): array {
        $qb = $this->subjectQuery(
            'permission_matrix_exports',
            'created_by_uid',
            $uid,
            $limit,
            $upperBound,
        );
        $qb->select('id', 'snapshot_uuid', 'created_at', 'format', 'size_bytes');

        return array_map(fn(array $row): array => [
            'source' => 'export',
            'id' => (string)$row['id'],
            'createdAt' => $this->dateValue($row['created_at']),
            'snapshotId' => (string)$row['snapshot_uuid'],
            'format' => (string)$row['format'],
            'sizeBytes' => (int)$row['size_bytes'],
        ], $qb->executeQuery()->fetchAll());
    }

    /** @return list<array<string, scalar|null>> */
    private function auditRecords(string $uid, int $limit, DateTimeImmutable $upperBound): array {
        $qb = $this->subjectQuery(
            'permission_matrix_audit_log',
            'user_id',
            $uid,
            $limit,
            $upperBound,
        );
        $qb->select('id', 'created_at', 'action', 'export_generated', 'snapshot_uuid');

        return array_map(fn(array $row): array => [
            'source' => 'audit',
            'id' => (string)$row['id'],
            'createdAt' => $this->dateValue($row['created_at']),
            'snapshotId' => $row['snapshot_uuid'] === null ? null : (string)$row['snapshot_uuid'],
            'action' => (string)$row['action'],
            'exportGenerated' => (bool)$row['export_generated'],
        ], $qb->executeQuery()->fetchAll());
    }

    private function subjectQuery(
        string $table,
        string $subjectColumn,
        string $uid,
        int $limit,
        DateTimeImmutable $upperBound,
    ): IQueryBuilder {
        $qb = $this->db->getQueryBuilder();
        $qb->from($table)
            ->where($qb->expr()->eq($subjectColumn, $qb->createNamedParameter($uid)))
            ->andWhere($qb->expr()->lte(
                'created_at',
                $qb->createNamedParameter($upperBound, IQueryBuilder::PARAM_DATE),
            ))
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setMaxResults($limit);

        return $qb;
    }

    private function dateValue(mixed $value): string {
        return $value instanceof DateTimeInterface ? $value->format(DATE_ATOM) : (string)$value;
    }
}
