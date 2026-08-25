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
            ...$this->adminAccessRecords($uid, $limit, $upperBound),
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

    private function adminAccessRecords(string $uid, int $limit, DateTimeImmutable $upperBound): array {
        $qb = $this->db->getQueryBuilder();
        $rows = $qb->select(
            'id',
            'target_uid',
            'granted_by',
            'starts_at',
            'ends_at',
            'revoked_at',
            'revoked_by',
            'created_at',
        )
            ->from('pm_admin_access')
            ->where($qb->expr()->orX(
                $qb->expr()->eq('target_uid', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)),
                $qb->expr()->eq('granted_by', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)),
                $qb->expr()->eq('revoked_by', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)),
            ))
            ->andWhere($qb->expr()->lte(
                'created_at',
                $qb->createNamedParameter($upperBound, IQueryBuilder::PARAM_DATETIME_IMMUTABLE),
            ))
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAll();

        return array_map(fn(array $row): array => [
            'source' => 'admin_access',
            'id' => (string)$row['id'],
            'createdAt' => $this->dateValue($row['created_at']),
            'targetUid' => (string)$row['target_uid'],
            'grantedBy' => (string)$row['granted_by'],
            'startsAt' => $this->dateValue($row['starts_at']),
            'endsAt' => $this->dateValue($row['ends_at']),
            'revokedAt' => $row['revoked_at'] === null ? null : $this->dateValue($row['revoked_at']),
            'revokedBy' => $row['revoked_by'] === null ? null : (string)$row['revoked_by'],
        ], $rows);
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
