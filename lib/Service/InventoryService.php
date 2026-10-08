<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Service;

use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IGroupManager;

/**
 * Zweck: Liefert das normalisierte read-only Inventar der aktuellen Nextcloud-Instanz.
 *
 * Zusammenspiel:
 * - MatrixBuilder und Adapter verwenden dieselbe Quelle fuer Gruppen, aktivierte Apps,
 *   App-Gruppenbeschraenkungen, Versionen und Installationsquelle.
 */
class InventoryService {
    public function __construct(
        private IGroupManager $groupManager,
        private IAppManager $appManager,
        private IConfig $systemConfig
    ) {
    }

    public function groups(): array {
        $groups = [];
        foreach ($this->groupManager->search('', 10000, 0) as $group) {
            $groups[] = $group->getGID();
        }

        $groups = array_values(array_unique(array_map('strval', $groups)));
        sort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        return $groups;
    }

    public function enabledAppIds(): array {
        $apps = array_values(array_unique(array_map('strval', $this->appManager->getEnabledApps())));
        sort($apps, SORT_NATURAL | SORT_FLAG_CASE);

        return $apps;
    }

    public function enabledApps(): array {
        return array_map(fn(string $appId): array => $this->appSummary($appId), $this->enabledAppIds());
    }

    public function appSummary(string $appId): array {
        $info = $this->appManager->getAppInfo($appId) ?? [];
        $restriction = $this->appRestriction($appId);

        return [
            'app_id' => $appId,
            'display_name' => (string)($info['name'] ?? $appId),
            'version' => $this->appVersion($appId, $info),
            'enabled' => true,
            'restricted' => $restriction !== [],
            'groups' => $restriction,
            'source' => $this->appSource($appId),
        ];
    }

    public function isAppEnabled(string $appId): bool {
        try {
            return $this->appManager->isEnabledForAnyone($appId);
        } catch (\Throwable) {
            return false;
        }
    }

    public function nextcloudVersion(): string {
        return $this->systemConfig->getSystemValueString('version', 'unknown');
    }

    public function appRestriction(string $appId): array {
        try {
            $groups = $this->appManager->getAppRestriction($appId);
        } catch (\Throwable) {
            return ['?'];
        }

        $groups = array_values(array_unique(array_map('strval', $groups)));
        sort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        return $groups;
    }

    private function appVersion(string $appId, array $info): string {
        try {
            return $this->appManager->getAppVersion($appId);
        } catch (\Throwable) {
            return (string)($info['version'] ?? 'unknown');
        }
    }

    private function appSource(string $appId): string {
        try {
            if ($this->appManager->isShipped($appId)) {
                return 'shipped';
            }

            $path = $this->appManager->getAppPath($appId);
            if (str_contains($path, '/custom_apps/')) {
                return 'custom';
            }

            return 'appstore';
        } catch (\Throwable) {
            return 'unknown';
        }
    }
}
