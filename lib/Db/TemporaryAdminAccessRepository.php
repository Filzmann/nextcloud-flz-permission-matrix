<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Db;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Throwable;

/** Gebundener Datenzugriff auf aktive und historische Admin-Freigaben. */
final class TemporaryAdminAccessRepository implements TemporaryAdminAccessRepositoryInterface {
    public function __construct(private IDBConnection $db) {}

    public function replaceActive(string $targetUid, string $grantedBy, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): array {
        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->update('pm_admin_access')
                ->set('revoked_at', $qb->createNamedParameter($startsAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->set('revoked_by', $qb->createNamedParameter($grantedBy, IQueryBuilder::PARAM_STR))
                ->where($qb->expr()->eq('target_uid', $qb->createNamedParameter($targetUid, IQueryBuilder::PARAM_STR)))
                ->andWhere($qb->expr()->isNull('revoked_at'))
                ->andWhere($qb->expr()->lte('starts_at', $qb->createNamedParameter($startsAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
                ->andWhere($qb->expr()->gt('ends_at', $qb->createNamedParameter($startsAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
                ->executeStatement();

            $qb = $this->db->getQueryBuilder();
            $qb->insert('pm_admin_access')
                ->setValue('target_uid', $qb->createNamedParameter($targetUid, IQueryBuilder::PARAM_STR))
                ->setValue('granted_by', $qb->createNamedParameter($grantedBy, IQueryBuilder::PARAM_STR))
                ->setValue('starts_at', $qb->createNamedParameter($startsAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->setValue('ends_at', $qb->createNamedParameter($endsAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->setValue('revoked_at', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
                ->setValue('revoked_by', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
                ->setValue('created_at', $qb->createNamedParameter($startsAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->executeStatement();
            $id = $qb->getLastInsertId();
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }

        return ['id'=>$id,'targetUid'=>$targetUid,'grantedBy'=>$grantedBy,'startsAt'=>$startsAt,'endsAt'=>$endsAt,'revokedAt'=>null,'revokedBy'=>null];
    }

    public function revokeActive(string $targetUid, string $revokedBy, DateTimeImmutable $revokedAt): bool {
        $qb = $this->db->getQueryBuilder();
        return $qb->update('pm_admin_access')
            ->set('revoked_at', $qb->createNamedParameter($revokedAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->set('revoked_by', $qb->createNamedParameter($revokedBy, IQueryBuilder::PARAM_STR))
            ->where($qb->expr()->eq('target_uid', $qb->createNamedParameter($targetUid, IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->isNull('revoked_at'))
            ->andWhere($qb->expr()->lte('starts_at', $qb->createNamedParameter($revokedAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->andWhere($qb->expr()->gt('ends_at', $qb->createNamedParameter($revokedAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->executeStatement() > 0;
    }

    public function activeFor(string $targetUid, DateTimeImmutable $at): ?array {
        $qb = $this->db->getQueryBuilder();
        $row = $qb->select('id','target_uid','granted_by','starts_at','ends_at','revoked_at','revoked_by')
            ->from('pm_admin_access')
            ->where($qb->expr()->eq('target_uid', $qb->createNamedParameter($targetUid, IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->isNull('revoked_at'))
            ->andWhere($qb->expr()->lte('starts_at', $qb->createNamedParameter($at, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->andWhere($qb->expr()->gt('ends_at', $qb->createNamedParameter($at, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->orderBy('starts_at','DESC')->setMaxResults(1)->executeQuery()->fetchAssociative();
        return $row === false ? null : $this->mapRow($row);
    }

    public function history(): array {
        $qb = $this->db->getQueryBuilder();
        $rows = $qb->select('id','target_uid','granted_by','starts_at','ends_at','revoked_at','revoked_by')
            ->from('pm_admin_access')->orderBy('starts_at','DESC')->setMaxResults(200)->executeQuery()->fetchAllAssociative();
        return array_map([$this,'mapRow'],$rows);
    }

    public function historyForUid(string $uid, int $limit): array {
        $qb = $this->db->getQueryBuilder();
        $rows = $qb->select('id','target_uid','granted_by','starts_at','ends_at','revoked_at','revoked_by')
            ->from('pm_admin_access')
            ->where($qb->expr()->orX(
                $qb->expr()->eq('target_uid', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)),
                $qb->expr()->eq('granted_by', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)),
                $qb->expr()->eq('revoked_by', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)),
            ))
            ->orderBy('starts_at','DESC')->setMaxResults($limit)->executeQuery()->fetchAllAssociative();
        return array_map([$this,'mapRow'],$rows);
    }

    private function mapRow(array $row): array {
        return ['id'=>(int)$row['id'],'targetUid'=>(string)$row['target_uid'],'grantedBy'=>(string)$row['granted_by'],'startsAt'=>$this->date($row['starts_at']),'endsAt'=>$this->date($row['ends_at']),'revokedAt'=>$row['revoked_at']===null?null:$this->date($row['revoked_at']),'revokedBy'=>$row['revoked_by']===null?null:(string)$row['revoked_by']];
    }

    private function date(mixed $value): DateTimeImmutable {
        return $value instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($value) : new DateTimeImmutable((string)$value,new DateTimeZone('UTC'));
    }
}


