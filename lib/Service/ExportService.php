<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Service;

use InvalidArgumentException;
use OCA\BrPermissionMatrix\Exception\ExportFormatNotAllowedException;
use OCA\BrPermissionMatrix\Model\MatrixRow;
use OCA\BrPermissionMatrix\Model\Snapshot;

class ExportService {
    private const IMPLEMENTED_FORMATS = ['json', 'csv', 'md', 'html'];

    public function __construct(
        private ConfigService $config
    ) {
    }

    public function allowedFormats(): array {
        return $this->config->exportFormats();
    }

    public function export(Snapshot $snapshot, string $format): array {
        $format = strtolower($format);
        $format = $format === 'markdown' ? 'md' : $format;
        if (!in_array($format, self::IMPLEMENTED_FORMATS, true)) {
            throw new InvalidArgumentException('Exportformat nicht unterstuetzt.');
        }
        if (!in_array($format, $this->allowedFormats(), true)) {
            throw new ExportFormatNotAllowedException('Exportformat ist nicht freigegeben.');
        }

        return match ($format) {
            'json' => $this->json($snapshot),
            'csv' => $this->csv($snapshot),
            'md' => $this->markdown($snapshot),
            'html' => $this->html($snapshot),
        };
    }

    private function json(Snapshot $snapshot): array {
        return [
            'content' => json_encode($snapshot->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'filename' => $snapshot->snapshotId() . '.json',
            'content_type' => 'application/json',
        ];
    }

    private function csv(Snapshot $snapshot): array {
        $handle = fopen('php://temp', 'w+');
        $header = ['Objekttyp', 'App-ID', 'Objekt/Funktion', 'Berechtigungsart', 'Status', ...$snapshot->groups()];
        fputcsv($handle, $header);

        foreach ($snapshot->matrix() as $row) {
            fputcsv($handle, [
                $row->objectType(),
                $row->appId(),
                $row->objectName(),
                $row->permissionType(),
                $row->status(),
                ...array_map(fn(string $group): string => (string)($row->cells()[$group] ?? '-'), $snapshot->groups()),
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return [
            'content' => $content === false ? '' : $content,
            'filename' => $snapshot->snapshotId() . '.csv',
            'content_type' => 'text/csv; charset=utf-8',
        ];
    }

    private function markdown(Snapshot $snapshot): array {
        $summary = $snapshot->summary();
        $lines = [
            '# Berechtigungsmatrix Nextcloud',
            '',
            'Stand: ' . $snapshot->createdAt(),
            'Instanz: Nextcloud',
            'Nextcloud-Version: ' . $snapshot->nextcloudVersion(),
            'Erstellt durch: br_permission_matrix',
            'Baseline: ' . (string)($summary['baseline_snapshot'] ?? 'nicht gesetzt'),
            'Scan-ID: ' . $snapshot->snapshotId(),
            '',
            '## Zweck',
            '',
            'Diese Matrix dokumentiert den gruppenbezogenen Berechtigungsstand der Nextcloud-Instanz. Sie dient als Positivliste. Berechtigungen, die nicht aufgefuehrt oder als nicht freigegeben markiert sind, gelten nicht als durch diese Matrix freigegeben.',
            '',
            '## Legende',
            '',
            'X = App oder Funktion nutzbar / sichtbar; R = Lesen; W = Schreiben / Bearbeiten; C = Erstellen / Hochladen; D = Loeschen; S = Teilen / Freigeben; A = Administrieren / Verwalten; - = kein Recht; ? = technisch nicht eindeutig auslesbar; UNSUPPORTED = App oder Berechtigungsmodell noch nicht unterstuetzt; NOT_APPROVED = nicht in der Positivliste freigegeben; n/a = nicht anwendbar.',
            '',
            '## Zusammenfassung',
            '',
            '- Gruppen: ' . (string)($summary['group_count'] ?? count($snapshot->groups())),
            '- Aktivierte Apps: ' . (string)($summary['app_count'] ?? count($snapshot->apps())),
            '- Gruppenbeschraenkte Apps: ' . (string)($summary['restricted_app_count'] ?? 0),
            '- Berechtigungsobjekte: ' . (string)($summary['object_count'] ?? count($snapshot->matrix())),
            '- Warnungen: ' . (string)($summary['warning_count'] ?? count($snapshot->warnings())),
            '- Nicht unterstuetzte Apps: ' . (string)($summary['unsupported_count'] ?? count($snapshot->unsupportedApps())),
            '',
            '## Compliance-Status',
            '',
            strtoupper((string)($summary['compliance_status'] ?? 'UNKNOWN')),
            '',
            '## Hauptmatrix',
            '',
            $this->markdownTable($snapshot),
            '',
            '## App-spezifische Detailtabellen',
            '',
            $this->appDetailsMarkdown($snapshot),
            '',
            '## Aenderungen gegenueber Baseline',
            '',
            $this->diffMarkdown($snapshot->diffToBaseline()),
            '',
            '## Nicht unterstuetzte oder nicht eindeutig auslesbare Bereiche',
            '',
            $this->warningsMarkdown($snapshot),
            '',
            '## Pruefhinweise fuer IKT-Ausschuss und Betriebsrat',
            '',
            '- Bereiche mit `?`, `UNKNOWN`, `UNSUPPORTED` oder `NOT_APPROVED` fachlich klaeren.',
            '- Neue oder erweiterte Rechte erst nach bewusster Freigabe in die Baseline uebernehmen.',
            '',
            '## Technische Hinweise',
            '',
            'Keine Dateiinhalte, Passwoerter, Tokens oder privaten Schluessel enthalten.',
        ];

        return [
            'content' => implode("\n", $lines) . "\n",
            'filename' => $snapshot->snapshotId() . '.md',
            'content_type' => 'text/markdown; charset=utf-8',
        ];
    }

    private function html(Snapshot $snapshot): array {
        $title = 'Berechtigungsmatrix Nextcloud';
        $body = '<h1>' . $this->esc($title) . '</h1>'
            . '<p>Stand: ' . $this->esc($snapshot->createdAt()) . '<br>Scan-ID: ' . $this->esc($snapshot->snapshotId()) . '</p>'
            . '<h2>Compliance-Status</h2><p>' . $this->esc(strtoupper((string)($snapshot->summary()['compliance_status'] ?? 'UNKNOWN'))) . '</p>'
            . '<h2>Hauptmatrix</h2>' . $this->htmlTable($snapshot)
            . '<h2>Technische Hinweise</h2><p>Keine Dateiinhalte, Passwoerter, Tokens oder privaten Schluessel enthalten.</p>';

        return [
            'content' => '<!doctype html><meta charset="utf-8"><title>' . $this->esc($title) . '</title>' . $body,
            'filename' => $snapshot->snapshotId() . '.html',
            'content_type' => 'text/html; charset=utf-8',
        ];
    }

    private function markdownTable(Snapshot $snapshot): string {
        $header = ['Objekttyp', 'App-ID', 'Objekt/Funktion', 'Berechtigungsart', 'Status', ...$snapshot->groups()];
        $lines = [
            '| ' . implode(' | ', array_map([$this, 'mdCell'], $header)) . ' |',
            '| ' . implode(' | ', array_fill(0, count($header), '---')) . ' |',
        ];

        foreach ($snapshot->matrix() as $row) {
            $lines[] = '| ' . implode(' | ', array_map([$this, 'mdCell'], [
                $row->objectType(),
                $row->appId(),
                $row->objectName(),
                $row->permissionType(),
                $row->status(),
                ...array_map(fn(string $group): string => (string)($row->cells()[$group] ?? '-'), $snapshot->groups()),
            ])) . ' |';
        }

        return implode("\n", $lines);
    }

    private function appDetailsMarkdown(Snapshot $snapshot): string {
        $lines = [];
        foreach ($snapshot->apps() as $app) {
            $groups = $app['groups'] === [] ? 'global' : implode(', ', $app['groups']);
            $lines[] = '- ' . $app['app_id'] . ' (' . $app['display_name'] . '), Version ' . $app['version'] . ', Quelle ' . $app['source'] . ', Gruppen: ' . $groups;
        }

        return $lines === [] ? 'Keine Apps erkannt.' : implode("\n", $lines);
    }

    private function diffMarkdown(array $diffs): string {
        if ($diffs === []) {
            return 'Keine Abweichungen.';
        }

        return implode("\n", array_map(static function(array $diff): string {
            return '- [' . ($diff['severity'] ?? 'info') . '] ' . ($diff['type'] ?? 'PERMISSION_CHANGED') . ': ' . ($diff['message'] ?? '');
        }, $diffs));
    }

    private function warningsMarkdown(Snapshot $snapshot): string {
        $items = array_values(array_unique([...$snapshot->warnings(), ...array_map(static fn(string $app): string => 'Nicht unterstuetzt: ' . $app, $snapshot->unsupportedApps())]));
        if ($items === []) {
            return 'Keine.';
        }

        return implode("\n", array_map(static fn(string $warning): string => '- ' . $warning, $items));
    }

    private function htmlTable(Snapshot $snapshot): string {
        $header = ['Objekttyp', 'App-ID', 'Objekt/Funktion', 'Berechtigungsart', 'Status', ...$snapshot->groups()];
        $html = '<table><thead><tr>';
        foreach ($header as $cell) {
            $html .= '<th>' . $this->esc($cell) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($snapshot->matrix() as $row) {
            $html .= '<tr>';
            foreach ([
                $row->objectType(),
                $row->appId(),
                $row->objectName(),
                $row->permissionType(),
                $row->status(),
                ...array_map(fn(string $group): string => (string)($row->cells()[$group] ?? '-'), $snapshot->groups()),
            ] as $cell) {
                $html .= '<td>' . $this->esc($cell) . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function mdCell(string $value): string {
        return str_replace(["\n", '|'], [' ', '\\|'], $value);
    }

    private function esc(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
