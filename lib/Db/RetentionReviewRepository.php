<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Db;

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
            'export' => 'permission_matrix_exports',
            'audit' => 'permission_matrix_audit_log',
            default => throw new InvalidArgumentException('Invalid retention review source.'),
        };
        if ($limit < 1 || $limit > 1000201) throw new InvalidArgumentException('Invalid retention review limit.');
        try {
            $cutoffDate = new DateTimeImmutable($cutoff);
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid retention review cutoff.');
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
