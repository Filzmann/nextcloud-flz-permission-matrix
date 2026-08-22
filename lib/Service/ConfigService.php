<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Service;

use OCA\BrPermissionMatrix\AppInfo\Application;
use OCA\BrPermissionMatrix\Exception\ConfigValidationException;
use OCP\IAppConfig;
use OCP\IGroupManager;

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
        private IAppConfig $config,
        private IGroupManager $groups
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
        $validated = $this->validatePayload($payload);

        $this->setStringList('viewer_groups', $validated['viewer_groups']);
        $this->setStringList('admin_groups', $validated['admin_groups']);
        $this->config->setValueString(Application::APP_ID, 'scan_interval', $validated['scan_interval'], true);
        $this->config->setValueBool(Application::APP_ID, 'strict_mode', $validated['strict_mode'], true);
        $this->config->setValueBool(Application::APP_ID, 'include_users', $validated['include_users'], true);
        $this->config->setValueBool(Application::APP_ID, 'redact_paths', $validated['redact_paths'], true);
        $this->config->setValueBool(Application::APP_ID, 'include_share_metadata', $validated['include_share_metadata'], true);
        $this->setStringList('export_formats', $validated['export_formats']);
        $this->config->setValueInt(Application::APP_ID, 'retention', $validated['retention'], true);

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

    private function validatePayload(array $payload): array {
        $viewerGroups = $this->normalizeStringList($payload['viewer_groups'] ?? self::DEFAULT_VIEWER_GROUPS);
        $adminGroups = $this->normalizeStringList($payload['admin_groups'] ?? self::DEFAULT_ADMIN_GROUPS);
        $this->assertKnownGroups($viewerGroups, 'Viewer-Gruppe');
        $this->assertKnownGroups($adminGroups, 'Admin-Gruppe');

        $interval = $payload['scan_interval'] ?? 'daily';
        if (!is_string($interval) || !in_array($interval, ['hourly', 'daily', 'weekly'], true)) {
            throw new ConfigValidationException('Scan-Intervall muss hourly, daily oder weekly sein.');
        }

        $formats = $this->normalizeStringList($payload['export_formats'] ?? self::DEFAULT_EXPORT_FORMATS);
        $formats = array_values(array_unique(array_map('strtolower', $formats)));
        $unknownFormats = array_values(array_diff($formats, ['md', 'csv', 'json', 'html']));
        if ($formats === [] || $unknownFormats !== []) {
            throw new ConfigValidationException('Exportformate dürfen nur md, csv, json oder html enthalten.');
        }
        sort($formats, SORT_NATURAL | SORT_FLAG_CASE);

        $retention = $payload['retention'] ?? 50;
        if (!(is_int($retention) || (is_string($retention) && preg_match('/^\d+$/D', $retention) === 1))) {
            throw new ConfigValidationException('Retention muss eine ganze Zahl zwischen 1 und 500 sein.');
        }
        $retention = (int)$retention;
        if ($retention < 1 || $retention > 500) {
            throw new ConfigValidationException('Retention muss zwischen 1 und 500 liegen.');
        }

        return [
            'viewer_groups' => $viewerGroups,
            'admin_groups' => $adminGroups,
            'scan_interval' => $interval,
            'strict_mode' => $this->toBool($payload['strict_mode'] ?? true),
            'include_users' => $this->toBool($payload['include_users'] ?? false),
            'redact_paths' => $this->toBool($payload['redact_paths'] ?? true),
            'include_share_metadata' => $this->toBool($payload['include_share_metadata'] ?? false),
            'export_formats' => $formats,
            'retention' => $retention,
        ];
    }

    private function assertKnownGroups(array $groupIds, string $label): void {
        foreach ($groupIds as $groupId) {
            if (!$this->groups->groupExists($groupId)) {
                throw new ConfigValidationException($label . ' „' . $groupId . '“ existiert nicht.');
            }
        }
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
