<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Service;

use OCA\BrPermissionMatrix\Model\MatrixRow;
use OCA\BrPermissionMatrix\Model\Snapshot;

class DiffService {
    /**
     * @return array{0: MatrixRow[], 1: array}
     */
    public function applyBaseline(Snapshot $snapshot, ?Snapshot $baseline, bool $strictMode): array {
        if ($baseline === null) {
            return [$snapshot->matrix(), [[
                'type' => 'UNKNOWN_PERMISSION_SOURCE',
                'severity' => 'warning',
                'row_key' => null,
                'group' => null,
                'old' => null,
                'new' => null,
                'message' => 'Kein Baseline-Snapshot gesetzt; aktueller Stand ist noch keine bestaetigte Positivliste.',
            ]]];
        }

        $baselineRows = $this->indexRows($baseline->matrix());
        $currentRows = $this->indexRows($snapshot->matrix());
        $diffs = [];
        $rows = [];

        foreach ($snapshot->matrix() as $row) {
            $base = $baselineRows[$row->key()] ?? null;
            if ($base === null) {
                $status = in_array($row->status(), ['UNSUPPORTED', 'UNKNOWN'], true)
                    ? $row->status()
                    : ($strictMode ? 'NOT_APPROVED' : 'NEW');
                $diffs[] = $this->rowDiff($row, $row->objectType() === 'App' ? 'NEW_APP' : 'PERMISSION_ADDED', 'critical', null, null);
                $rows[] = $row->withStatus($status);
                continue;
            }

            $cellDiffs = $this->cellDiffs($base, $row);
            if ($cellDiffs === []) {
                $rows[] = in_array($row->status(), ['UNSUPPORTED', 'UNKNOWN'], true) ? $row : $row->withStatus('APPROVED');
                continue;
            }

            $critical = false;
            foreach ($cellDiffs as $cellDiff) {
                $expanded = $this->isExpansion((string)$cellDiff['old'], (string)$cellDiff['new']);
                $critical = $critical || $expanded;
                $diffs[] = [
                    'type' => $row->objectType() === 'App' && $expanded ? 'APP_GROUP_EXPANDED' : 'PERMISSION_CHANGED',
                    'severity' => $expanded ? 'critical' : 'warning',
                    'row_key' => $row->key(),
                    'group' => $cellDiff['group'],
                    'old' => $cellDiff['old'],
                    'new' => $cellDiff['new'],
                    'message' => $this->messageForCellDiff($row, $cellDiff, $expanded),
                ];
            }

            $rows[] = $row->withStatus($strictMode && $critical ? 'NOT_APPROVED' : 'CHANGED');
        }

        foreach ($baseline->matrix() as $baseRow) {
            if (!isset($currentRows[$baseRow->key()])) {
                $diffs[] = $this->rowDiff($baseRow, $baseRow->objectType() === 'App' ? 'DISABLED_APP' : 'PERMISSION_REMOVED', 'info', 'present', 'removed');
            }
        }

        return [$rows, $diffs];
    }

    public function compareSnapshots(Snapshot $from, Snapshot $to): array {
        [, $diffs] = $this->applyBaseline($to, $from, true);

        return $diffs;
    }

    /**
     * @param MatrixRow[] $rows
     * @return array<string, MatrixRow>
     */
    private function indexRows(array $rows): array {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->key()] = $row;
        }

        return $indexed;
    }

    private function cellDiffs(MatrixRow $base, MatrixRow $current): array {
        $groups = array_values(array_unique([...array_keys($base->cells()), ...array_keys($current->cells())]));
        sort($groups, SORT_NATURAL | SORT_FLAG_CASE);
        $diffs = [];

        foreach ($groups as $group) {
            $old = (string)($base->cells()[$group] ?? '-');
            $new = (string)($current->cells()[$group] ?? '-');
            if ($old !== $new) {
                $diffs[] = [
                    'group' => $group,
                    'old' => $old,
                    'new' => $new,
                ];
            }
        }

        return $diffs;
    }

    private function rowDiff(MatrixRow $row, string $type, string $severity, ?string $old, ?string $new): array {
        return [
            'type' => $type,
            'severity' => $severity,
            'row_key' => $row->key(),
            'group' => null,
            'old' => $old,
            'new' => $new,
            'message' => $row->objectType() . ' ' . $row->appId() . ' / ' . $row->objectName() . ': ' . $type,
        ];
    }

    private function messageForCellDiff(MatrixRow $row, array $diff, bool $expanded): string {
        $direction = $expanded ? 'erweitert' : 'geaendert';

        return sprintf(
            '%s %s / %s fuer Gruppe %s von %s auf %s %s.',
            $row->objectType(),
            $row->appId(),
            $row->objectName(),
            (string)$diff['group'],
            (string)$diff['old'],
            (string)$diff['new'],
            $direction
        );
    }

    private function isExpansion(string $old, string $new): bool {
        if (in_array($new, ['?', 'UNKNOWN', 'UNSUPPORTED', 'n/a'], true)) {
            return false;
        }

        return $this->permissionScore($new) > $this->permissionScore($old);
    }

    private function permissionScore(string $value): int {
        $value = trim($value);
        if (in_array($value, ['-', 'deny', 'n/a', '?', 'UNKNOWN', 'UNSUPPORTED'], true)) {
            return 0;
        }

        if (in_array($value, ['allow', 'inherited', 'X'], true)) {
            return 1;
        }

        $score = 0;
        foreach (['R', 'W', 'C', 'D', 'S', 'A'] as $right) {
            if (str_contains($value, $right)) {
                $score++;
            }
        }

        return max(1, $score);
    }
}
