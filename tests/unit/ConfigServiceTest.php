<?php

declare(strict_types=1);

namespace OCP {
    interface IAppConfig {
        public function getValueArray(string $appId, string $key, array $default = [], bool $lazy = false): array;
        public function getValueString(string $appId, string $key, string $default = '', bool $lazy = false): string;
        public function getValueBool(string $appId, string $key, bool $default = false, bool $lazy = false): bool;
        public function getValueInt(string $appId, string $key, int $default = 0, bool $lazy = false): int;
        public function setValueArray(string $appId, string $key, array $value, bool $lazy = false): void;
        public function setValueString(string $appId, string $key, string $value, bool $lazy = false): void;
        public function setValueBool(string $appId, string $key, bool $value, bool $lazy = false): void;
        public function setValueInt(string $appId, string $key, int $value, bool $lazy = false): void;
    }
}

namespace OCA\FlzPermissionMatrix\AppInfo {
    final class Application { public const APP_ID = 'flz_permission_matrix'; }
}

namespace {
    use OCA\FlzPermissionMatrix\Exception\ConfigValidationException;
    use OCA\FlzPermissionMatrix\Service\ConfigService;
    use OCP\IAppConfig;

    final class ConfigTestAppConfig implements IAppConfig {
        public array $values = [];
        public array $writes = [];

        public function getValueArray(string $appId, string $key, array $default = [], bool $lazy = false): array { return $this->values[$key] ?? $default; }
        public function getValueString(string $appId, string $key, string $default = '', bool $lazy = false): string { return $this->values[$key] ?? $default; }
        public function getValueBool(string $appId, string $key, bool $default = false, bool $lazy = false): bool { return $this->values[$key] ?? $default; }
        public function getValueInt(string $appId, string $key, int $default = 0, bool $lazy = false): int { return $this->values[$key] ?? $default; }
        public function setValueArray(string $appId, string $key, array $value, bool $lazy = false): void { $this->write($key, $value); }
        public function setValueString(string $appId, string $key, string $value, bool $lazy = false): void { $this->write($key, $value); }
        public function setValueBool(string $appId, string $key, bool $value, bool $lazy = false): void { $this->write($key, $value); }
        public function setValueInt(string $appId, string $key, int $value, bool $lazy = false): void { $this->write($key, $value); }
        private function write(string $key, mixed $value): void { $this->writes[] = $key; $this->values[$key] = $value; }
    }

    $defaultService = new ConfigService(new ConfigTestAppConfig());
    $defaults = $defaultService->toArray();
    assertSameValue(false, array_key_exists('viewer_groups', $defaults), 'Identity groups must not be stored as configurable app permissions.');
    assertSameValue(false, array_key_exists('admin_groups', $defaults), 'Administrative identity must not be stored as a configurable app permission.');
    assertSameValue(180, $defaultService->exportMetadataRetentionDays(), 'Export metadata should default to a 180-day review period.');
    assertSameValue(180, $defaultService->auditRetentionDays(), 'Audit records should default to a 180-day review period.');

    $store = new ConfigTestAppConfig();
    $service = new ConfigService($store);
    $saved = $service->save([
        'viewer_groups' => ['Manipulierte-Gruppe'],
        'admin_groups' => ['Manipulierte-Admin-Gruppe'],
        'scan_interval' => 'weekly',
        'strict_mode' => '1',
        'include_users' => '0',
        'redact_paths' => '1',
        'include_share_metadata' => '0',
        'export_formats' => 'HTML,md',
        'retention' => '75',
        'export_metadata_retention_days' => '120',
        'audit_retention_days' => '240',
    ]);

    assertSameValue(false, in_array('viewer_groups', $store->writes, true), 'Manipulated group input must never alter the fixed BR/DPO permission mapping.');
    assertSameValue(false, in_array('admin_groups', $store->writes, true), 'Manipulated admin-group input must never alter the fixed DPO mapping.');
    assertSameValue(['html', 'md'], $saved['export_formats'], 'Valid export formats should be normalized.');
    assertSameValue(75, $saved['retention'], 'Valid retention should be persisted as an integer.');
    assertSameValue(120, $saved['export_metadata_retention_days'], 'The export review period should be independently configurable.');
    assertSameValue(240, $saved['audit_retention_days'], 'The audit review period should be independently configurable.');

    foreach ([
        ['scan_interval' => 'sometimes'],
        ['export_formats' => 'md,pdf'],
        ['retention' => '501'],
        ['retention' => '7.5'],
        ['export_metadata_retention_days' => '0'],
        ['export_metadata_retention_days' => '3651'],
        ['audit_retention_days' => '7.5'],
        ['audit_retention_days' => '3651'],
    ] as $invalid) {
        $store->writes = [];
        try {
            $service->save(array_merge([
                'scan_interval' => 'daily',
                'export_formats' => 'md',
                'retention' => '50',
                'export_metadata_retention_days' => '180',
                'audit_retention_days' => '180',
            ], $invalid));
            throw new RuntimeException('Each invalid configuration boundary must be rejected.');
        } catch (ConfigValidationException) {
        }
        assertSameValue([], $store->writes, 'Validation must finish before any configuration key is changed.');
    }

    echo 'ConfigService tests passed' . PHP_EOL;
}
