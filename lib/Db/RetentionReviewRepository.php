<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Db;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Read-only projection of due app-owned metadata; no subject or free-detail columns are selected. */
class RetentionReviewRepository {
    public function __construct(private IDBConnection $db) {}

    public function collectDue(string $source, string $cutoff, int $limit): array {
        $table = match ($source) {
            'export' => 'flz_pm_exports',
            'audit' => 'flz_pm_audit_log',
            default => throw new InvalidArgumentException('Ungültige Retention-Prüfungsquelle.'),
        };
        if ($limit < 1 || $limit > 1000201) throw new InvalidArgumentException('Ungültiges Retention-Prüfungslimit.');
        try {
            $cutoffDate = new DateTimeImmutable($cutoff);
        } catch (\Throwable) {
            throw new InvalidArgumentException('Ungültiger Retention-Prüfungsstichtag.');
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('id', 'created_at')
            ->from($table)
            ->where($qb->expr()->lte('created_at', $qb->createNamedParameter($cutoffDate, IQueryBuilder::PARAM_DATE)))
            ->orderBy('created_at', 'ASC')
            ->addOrderBy('id', 'ASC')
            ->setMaxResults($limit);

        return array_map(fn(array $row): array => [
            'id' => (string)$row['id'],
            'createdAt' => $row['created_at'] instanceof DateTimeInterface ? $row['created_at']->format(DATE_ATOM) : (string)$row['created_at'],
        ], $qb->executeQuery()->fetchAll());
    }
}
