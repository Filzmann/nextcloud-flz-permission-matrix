<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Service;

use OCA\BrPermissionMatrix\Db\SnapshotMapper;
use OCA\BrPermissionMatrix\Model\MatrixRow;
use OCA\BrPermissionMatrix\Model\Snapshot;
use Psr\Log\LoggerInterface;

class ScannerService {
    public function __construct(
        private MatrixBuilder $matrixBuilder,
        private InventoryService $inventory,
        private ConfigService $config,
        private BaselineService $baseline,
        private DiffService $diffs,
        private SnapshotMapper $snapshots,
        private LoggerInterface $logger
    ) {
    }

    public function scan(?string $createdBy = null, bool $persist = true): Snapshot {
        $build = $this->matrixBuilder->build();
        $warnings = $build['warnings'];
        $baseline = $this->baseline->currentBaseline();
        if ($baseline === null) {
            $warnings[] = 'Kein Baseline-Snapshot gesetzt.';
        }

        $snapshot = new Snapshot(
            Snapshot::freshId(),
            Snapshot::now(),
            $this->inventory->nextcloudVersion(),
            $build['groups'],
            $build['apps'],
            $build['rows'],
            $warnings,
            $build['unsupported_apps'],
            [],
            $this->config->metadata(),
            [],
            $build['adapter_status']
        );

        [$matrix, $diffs] = $this->diffs->applyBaseline($snapshot, $baseline, $this->config->strictMode());
        $summary = $this->summary($snapshot, $matrix, $diffs, $baseline);
        $snapshot = $snapshot->withMatrixAndDiff($matrix, $diffs, $summary);

        if ($persist) {
            $this->snapshots->insert($snapshot, $createdBy, $baseline?->snapshotId());
            $this->snapshots->purgeOlderThanLatest($this->config->retention());
            $this->logger->info('Permission matrix scan completed', [
                'app' => 'br_permission_matrix',
                'snapshot' => $snapshot->snapshotId(),
                'compliance' => $summary['compliance_status'] ?? 'UNKNOWN',
            ]);
        }

        return $snapshot;
    }

    /**
     * @param MatrixRow[] $matrix
     */
    private function summary(Snapshot $snapshot, array $matrix, array $diffs, ?Snapshot $baseline): array {
        $statuses = array_count_values(array_map(fn(MatrixRow $row): string => $row->status(), $matrix));
        $criticalDiffs = array_filter($diffs, static fn(array $diff): bool => ($diff['severity'] ?? '') === 'critical');
        $unknownRows = array_filter($matrix, static fn(MatrixRow $row): bool => in_array($row->status(), ['UNKNOWN', 'UNSUPPORTED'], true));
        $restrictedApps = array_filter($snapshot->apps(), static fn(array $app): bool => (bool)($app['restricted'] ?? false));

        $compliance = 'green';
        if ($baseline === null || $criticalDiffs !== [] || ($statuses['NOT_APPROVED'] ?? 0) > 0) {
            $compliance = 'red';
        } elseif ($snapshot->warnings() !== [] || $unknownRows !== []) {
            $compliance = 'yellow';
        }

        return [
            'group_count' => count($snapshot->groups()),
            'app_count' => count($snapshot->apps()),
            'restricted_app_count' => count($restrictedApps),
            'object_count' => count($matrix),
            'warning_count' => count($snapshot->warnings()) + count($unknownRows),
            'unsupported_count' => count($snapshot->unsupportedApps()),
            'diff_count' => count($diffs),
            'critical_diff_count' => count($criticalDiffs),
            'status_counts' => $statuses,
            'compliance_status' => $compliance,
            'baseline_snapshot' => $baseline?->snapshotId(),
        ];
    }
}
