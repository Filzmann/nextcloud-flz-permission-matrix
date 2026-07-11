<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Service;

use OCA\BrPermissionMatrix\AppInfo\Application;
use OCP\IAppConfig;

/**
 * Zweck: Zentralisiert sichere Defaults, Normalisierung und Persistenz der App-Konfiguration.
 *
 * Zusammenspiel:
 * - AccessService nutzt Gruppenrollen, ScannerService Scan-/Retention-Regeln und ExportService
 *   die serverseitig erzwungene Format-Allowlist.
 */
class ConfigService {
    private const DEFAULT_VIEWER_GROUPS = ['Betriebsrat', 'IKT-Ausschuss', 'Datenschutz', 'IT-Administration'];
    private const DEFAULT_ADMIN_GROUPS = ['IT-Administration'];
    private const DEFAULT_EXPORT_FORMATS = ['md', 'csv', 'json', 'html'];

    public function __construct(
        private IAppConfig $config
    ) {
    }

    public function viewerGroups(): array {
        return $this->stringList('viewer_groups', self::DEFAULT_VIEWER_GROUPS);
    }

    public function adminGroups(): array {
        return $this->stringList('admin_groups', self::DEFAULT_ADMIN_GROUPS);
    }

    public function scanInterval(): string {
        $interval = $this->config->getValueString(Application::APP_ID, 'scan_interval', 'daily', true);

        return in_array($interval, ['hourly', 'daily', 'weekly'], true) ? $interval : 'daily';
    }

    public function baselineSnapshot(): string {
        return $this->config->getValueString(Application::APP_ID, 'baseline_snapshot', '', true);
    }

    public function setBaselineSnapshot(string $snapshotId): void {
        $this->config->setValueString(Application::APP_ID, 'baseline_snapshot', $snapshotId, true);
    }

    public function strictMode(): bool {
        return $this->config->getValueBool(Application::APP_ID, 'strict_mode', true, true);
    }

    public function includeUsers(): bool {
        return $this->config->getValueBool(Application::APP_ID, 'include_users', false, true);
    }

    public function redactPaths(): bool {
        return $this->config->getValueBool(Application::APP_ID, 'redact_paths', true, true);
    }

    public function includeShareMetadata(): bool {
        return $this->config->getValueBool(Application::APP_ID, 'include_share_metadata', false, true);
    }

    public function exportFormats(): array {
        return $this->normalizeFormats(
            $this->config->getValueArray(Application::APP_ID, 'export_formats', self::DEFAULT_EXPORT_FORMATS, true)
        );
    }

    public function retention(): int {
        $retention = $this->config->getValueInt(Application::APP_ID, 'retention', 50, true);

        return max(1, min(500, $retention));
    }

    public function toArray(): array {
        return [
            'viewer_groups' => $this->viewerGroups(),
            'admin_groups' => $this->adminGroups(),
            'scan_interval' => $this->scanInterval(),
            'baseline_snapshot' => $this->baselineSnapshot(),
            'strict_mode' => $this->strictMode(),
            'include_users' => $this->includeUsers(),
            'redact_paths' => $this->redactPaths(),
            'include_share_metadata' => $this->includeShareMetadata(),
            'export_formats' => $this->exportFormats(),
            'retention' => $this->retention(),
        ];
    }

    public function save(array $payload): array {
        $this->setStringList('viewer_groups', $payload['viewer_groups'] ?? self::DEFAULT_VIEWER_GROUPS);
        $this->setStringList('admin_groups', $payload['admin_groups'] ?? self::DEFAULT_ADMIN_GROUPS);
        $this->config->setValueString(Application::APP_ID, 'scan_interval', $this->normalizeInterval($payload['scan_interval'] ?? 'daily'), true);
        $this->config->setValueBool(Application::APP_ID, 'strict_mode', $this->toBool($payload['strict_mode'] ?? true), true);
        $this->config->setValueBool(Application::APP_ID, 'include_users', $this->toBool($payload['include_users'] ?? false), true);
        $this->config->setValueBool(Application::APP_ID, 'redact_paths', $this->toBool($payload['redact_paths'] ?? true), true);
        $this->config->setValueBool(Application::APP_ID, 'include_share_metadata', $this->toBool($payload['include_share_metadata'] ?? false), true);
        $this->setStringList('export_formats', $this->normalizeFormats($payload['export_formats'] ?? self::DEFAULT_EXPORT_FORMATS));
        $this->config->setValueInt(Application::APP_ID, 'retention', max(1, min(500, (int)($payload['retention'] ?? 50))), true);

        return $this->toArray();
    }

    public function metadata(): array {
        return [
            'redacted' => $this->redactPaths(),
            'include_users' => $this->includeUsers(),
            'include_share_metadata' => $this->includeShareMetadata(),
            'strict_mode' => $this->strictMode(),
        ];
    }

    private function stringList(string $key, array $default): array {
        $value = $this->config->getValueArray(Application::APP_ID, $key, $default, true);

        return $this->normalizeStringList($value);
    }

    private function setStringList(string $key, array|string $value): void {
        $this->config->setValueArray(Application::APP_ID, $key, $this->normalizeStringList($value), true);
    }

    private function normalizeStringList(array|string $value): array {
        if (is_string($value)) {
            $value = preg_split('/[,\n\r]+/', $value) ?: [];
        }

        $items = array_map(static fn($item): string => trim((string)$item), $value);
        $items = array_values(array_unique(array_filter($items, static fn(string $item): bool => $item !== '')));
        sort($items, SORT_NATURAL | SORT_FLAG_CASE);

        return $items;
    }

    private function normalizeFormats(array|string $value): array {
        $formats = array_map('strtolower', $this->normalizeStringList($value));
        $formats = array_values(array_unique($formats));
        sort($formats, SORT_NATURAL | SORT_FLAG_CASE);
        $allowed = ['md', 'csv', 'json', 'html'];
        $formats = array_values(array_filter($formats, static fn(string $format): bool => in_array($format, $allowed, true)));

        return $formats === [] ? self::DEFAULT_EXPORT_FORMATS : $formats;
    }

    private function normalizeInterval(string $interval): string {
        return in_array($interval, ['hourly', 'daily', 'weekly'], true) ? $interval : 'daily';
    }

    private function toBool(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool)$value;
    }
}
