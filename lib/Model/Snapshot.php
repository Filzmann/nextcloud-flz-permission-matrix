<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Model;

use DateTimeImmutable;

/**
 * Zweck: Unveraenderlicher Gesamtstand eines Matrix-Scans inklusive Bewertung und Anzeige-Metadaten.
 *
 * Zusammenspiel:
 * - ScannerService baut den Snapshot, DiffService bewertet seine Matrix und SnapshotMapper
 *   persistiert die vollstaendige serialisierte Fassung.
 *
 * Vertrag:
 * - groupCatalog ist nur eine verlustfreie Praesentationsschicht; groups und Matrixzellen
 *   behalten immer die vollstaendigen Rohgruppen fuer Baselines, Diffs und Exporte.
 */
class Snapshot {
    /**
     * @param MatrixRow[] $matrix
     * @param array<int, array{key: string, label: string, type: string, groups: string[], count: int}> $groupCatalog
     */
    public function __construct(
        private string $snapshotId,
        private string $createdAt,
        private string $nextcloudVersion,
        private array $groups,
        private array $apps,
        private array $matrix,
        private array $warnings = [],
        private array $unsupportedApps = [],
        private array $diffToBaseline = [],
        private array $metadata = [],
        private array $summary = [],
        private array $adapterStatus = [],
        private array $groupCatalog = []
    ) {
        $this->groups = array_values(array_unique(array_map('strval', $groups)));
        sort($this->groups, SORT_NATURAL | SORT_FLAG_CASE);
        $this->apps = array_values($apps);
        $this->warnings = array_values(array_unique(array_map('strval', $warnings)));
        $this->unsupportedApps = array_values(array_unique(array_map('strval', $unsupportedApps)));
        sort($this->unsupportedApps, SORT_NATURAL | SORT_FLAG_CASE);
    }

    public static function freshId(): string {
        return 'pm-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
    }

    public static function now(): string {
        return (new DateTimeImmutable())->format(DATE_ATOM);
    }

    public static function get(?array $payload): ?self {
        if ($payload === null) {
            return null;
        }

        return new self(
            (string)($payload['snapshot_id'] ?? $payload['snapshotId'] ?? ''),
            (string)($payload['created_at'] ?? $payload['createdAt'] ?? self::now()),
            (string)($payload['nextcloud_version'] ?? $payload['nextcloudVersion'] ?? 'unknown'),
            is_array($payload['groups'] ?? null) ? $payload['groups'] : [],
            is_array($payload['apps'] ?? null) ? $payload['apps'] : [],
            MatrixRow::get_all(is_array($payload['matrix'] ?? null) ? $payload['matrix'] : []),
            is_array($payload['warnings'] ?? null) ? $payload['warnings'] : [],
            is_array($payload['unsupported_apps'] ?? null) ? $payload['unsupported_apps'] : [],
            is_array($payload['diff_to_baseline'] ?? null) ? $payload['diff_to_baseline'] : [],
            is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
            is_array($payload['summary'] ?? null) ? $payload['summary'] : [],
            is_array($payload['adapter_status'] ?? null) ? $payload['adapter_status'] : [],
            is_array($payload['group_catalog'] ?? null) ? $payload['group_catalog'] : []
        );
    }

    public function withMatrixAndDiff(array $matrix, array $diffToBaseline, array $summary): self {
        return new self(
            $this->snapshotId,
            $this->createdAt,
            $this->nextcloudVersion,
            $this->groups,
            $this->apps,
            $matrix,
            $this->warnings,
            $this->unsupportedApps,
            $diffToBaseline,
            $this->metadata,
            $summary,
            $this->adapterStatus,
            $this->groupCatalog
        );
    }

    public function snapshotId(): string {
        return $this->snapshotId;
    }

    public function createdAt(): string {
        return $this->createdAt;
    }

    public function nextcloudVersion(): string {
        return $this->nextcloudVersion;
    }

    public function groups(): array {
        return $this->groups;
    }

    public function apps(): array {
        return $this->apps;
    }

    /**
     * @return MatrixRow[]
     */
    public function matrix(): array {
        return $this->matrix;
    }

    public function warnings(): array {
        return $this->warnings;
    }

    public function unsupportedApps(): array {
        return $this->unsupportedApps;
    }

    public function diffToBaseline(): array {
        return $this->diffToBaseline;
    }

    public function metadata(): array {
        return $this->metadata;
    }

    public function summary(): array {
        return $this->summary;
    }

    public function adapterStatus(): array {
        return $this->adapterStatus;
    }

    public function groupCatalog(): array {
        return $this->groupCatalog;
    }

    public function toArray(): array {
        return [
            'snapshot_id' => $this->snapshotId,
            'created_at' => $this->createdAt,
            'nextcloud_version' => $this->nextcloudVersion,
            'groups' => $this->groups,
            'apps' => $this->apps,
            'matrix' => array_map(fn(MatrixRow $row): array => $row->toArray(), $this->matrix),
            'warnings' => $this->warnings,
            'unsupported_apps' => $this->unsupportedApps,
            'diff_to_baseline' => $this->diffToBaseline,
            'metadata' => $this->metadata,
            'summary' => $this->summary,
            'adapter_status' => $this->adapterStatus,
            'group_catalog' => $this->groupCatalog,
        ];
    }
}
