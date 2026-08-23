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

    interface IGroupManager {
        public function groupExists(string $gid): bool;
    }
}

namespace OCA\FilzmannPermissionMatrix\AppInfo {
    final class Application {
        public const APP_ID = 'filzmann_permission_matrix';
    }
}

namespace {
    use OCA\FilzmannPermissionMatrix\Exception\ConfigValidationException;
    use OCA\FilzmannPermissionMatrix\Service\ConfigService;
    use OCP\IAppConfig;
    use OCP\IGroupManager;

    final class ConfigTestAppConfig implements IAppConfig {
        public array $values = [];
        public array $writes = [];

        public function getValueArray(string $appId, string $key, array $default = [], bool $lazy = false): array {
            return $this->values[$key] ?? $default;
        }

        public function getValueString(string $appId, string $key, string $default = '', bool $lazy = false): string {
            return $this->values[$key] ?? $default;
        }

        public function getValueBool(string $appId, string $key, bool $default = false, bool $lazy = false): bool {
            return $this->values[$key] ?? $default;
        }

        public function getValueInt(string $appId, string $key, int $default = 0, bool $lazy = false): int {
            return $this->values[$key] ?? $default;
        }

        public function setValueArray(string $appId, string $key, array $value, bool $lazy = false): void {
            $this->write($key, $value);
        }

        public function setValueString(string $appId, string $key, string $value, bool $lazy = false): void {
            $this->write($key, $value);
        }

        public function setValueBool(string $appId, string $key, bool $value, bool $lazy = false): void {
            $this->write($key, $value);
        }

        public function setValueInt(string $appId, string $key, int $value, bool $lazy = false): void {
            $this->write($key, $value);
        }

        private function write(string $key, mixed $value): void {
            $this->writes[] = $key;
            $this->values[$key] = $value;
        }
    }

    final class ConfigTestGroups implements IGroupManager {
        public function __construct(private array $known) {
        }

        public function groupExists(string $gid): bool {
            return in_array($gid, $this->known, true);
        }
    }

    $store = new ConfigTestAppConfig();
    $service = new ConfigService($store, new ConfigTestGroups(['Matrix-Viewer', 'Matrix-Admin']));
    $saved = $service->save([
        'viewer_groups' => "Matrix-Viewer\nMatrix-Viewer",
        'admin_groups' => ['Matrix-Admin'],
        'scan_interval' => 'weekly',
        'strict_mode' => '1',
        'include_users' => '0',
        'redact_paths' => '1',
        'include_share_metadata' => '0',
        'export_formats' => 'HTML,md',
        'retention' => '75',
    ]);

    assertSameValue(['Matrix-Viewer'], $saved['viewer_groups'], 'Known viewer groups should be normalized and saved.');
    assertSameValue(['html', 'md'], $saved['export_formats'], 'Valid export formats should be normalized.');
    assertSameValue(75, $saved['retention'], 'Valid retention should be persisted as an integer.');

    $store->writes = [];
    try {
        $service->save([
            'viewer_groups' => ['Matrix-Viewer', 'Nicht vorhanden'],
            'admin_groups' => ['Matrix-Admin'],
            'scan_interval' => 'sometimes',
            'export_formats' => 'md,pdf',
            'retention' => '0',
        ]);
        throw new RuntimeException('Invalid configuration must be rejected.');
    } catch (ConfigValidationException $e) {
        assertContainsText('Viewer-Gruppe', $e->getMessage(), 'The first concrete validation problem should be understandable.');
    }
    assertSameValue([], $store->writes, 'Validation must finish before any configuration key is changed.');

    foreach ([
        ['scan_interval' => 'sometimes'],
        ['export_formats' => 'md,pdf'],
        ['retention' => '501'],
        ['retention' => '7.5'],
    ] as $invalid) {
        try {
            $service->save(array_merge([
                'viewer_groups' => ['Matrix-Viewer'],
                'admin_groups' => ['Matrix-Admin'],
                'scan_interval' => 'daily',
                'export_formats' => 'md',
                'retention' => '50',
            ], $invalid));
            throw new RuntimeException('Each invalid configuration boundary must be rejected.');
        } catch (ConfigValidationException) {
        }
    }

    echo 'ConfigService tests passed' . PHP_EOL;
}
