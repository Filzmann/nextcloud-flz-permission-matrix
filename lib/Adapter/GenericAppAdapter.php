<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Adapter;

use OCA\BrPermissionMatrix\Model\AccessCondition;
use OCA\BrPermissionMatrix\Model\AccessRule;
use OCA\BrPermissionMatrix\Model\MatrixRow;
use OCA\BrPermissionMatrix\Service\AdapterCatalogService;
use OCA\BrPermissionMatrix\Service\InventoryService;

/**
 * Zweck: Erzeugt fuer jede aktivierte App eine Zeile zur Nextcloud-App-Verfuegbarkeit.
 *
 * Zusammenspiel:
 * - InventoryService liefert die App-Gruppenbeschraenkung; AdapterCatalogService kennzeichnet,
 *   ob zusaetzlich belastbare Detailrechte aus einem spezialisierten Adapter vorliegen.
 *
 * Vertrag:
 * - Der Adapterstatus UNSUPPORTED bedeutet nur, dass Detailrechte fehlen. Der Zeilenstatus und
 *   die Zellen bleiben davon unabhaengig: X/- bilden die App-Gruppenbeschraenkung ab, ? eine unklare Konfiguration.
 */
class GenericAppAdapter implements PermissionAdapterInterface {
    public function __construct(
        private InventoryService $inventory,
        private AdapterCatalogService $catalog
    ) {
    }

    public function supports(string $appId): bool {
        return true;
    }

    public function collect(): AdapterResult {
        $groups = $this->inventory->groups();
        $rows = [];
        $warnings = [];
        $unsupported = [];
        $adapterStatus = [];

        foreach ($this->inventory->enabledApps() as $app) {
            $appId = (string)$app['app_id'];
            $restriction = $app['groups'] ?? [];
            $restrictionUnknown = in_array('?', $restriction, true);
            $hasAdapter = $this->catalog->hasImplementedAdapter($appId);
            $cells = [];

            foreach ($groups as $group) {
                if ($restrictionUnknown) {
                    $cells[$group] = '?';
                } elseif ($restriction === []) {
                    $cells[$group] = 'X';
                } else {
                    $cells[$group] = in_array($group, $restriction, true) ? 'X' : '-';
                }
            }

            $rowWarnings = [];
            $accessRules = [];
            $status = 'NEW';
            $confidence = 'high';
            $coverageWarnings = [];
            if ($restrictionUnknown) {
                $status = 'UNKNOWN';
                $confidence = 'low';
                $rowWarnings[] = 'App-Gruppenbeschraenkung konnte nicht eindeutig gelesen werden.';
                $warnings[] = $appId . ': App-Gruppenbeschraenkung unklar.';
            } elseif (!$hasAdapter) {
                $coverageWarnings[] = 'Keine Detailrechte auswertbar; App muss fachlich geprueft werden.';
                $warnings[] = $appId . ': kein Detailadapter vorhanden.';
                $unsupported[] = $appId;
            }
            if (!$restrictionUnknown && $restriction !== []) {
                $accessRules[] = new AccessRule(
                    'app.use',
                    'allow',
                    'app:' . $appId,
                    AccessCondition::any(array_map(
                        static fn(string $group): AccessCondition => AccessCondition::group($group),
                        $restriction
                    )),
                    'nextcloud:IAppManager::getAppRestriction',
                    'high'
                );
            }

            $rows[] = new MatrixRow(
                'App',
                $appId,
                (string)$app['display_name'],
                'App-Nutzung',
                'App-Verfuegbarkeit',
                $status,
                'core-app-config',
                $confidence,
                $cells,
                $rowWarnings,
                $accessRules
            );

            $adapterStatus[] = [
                'app_id' => $appId,
                'adapter' => 'GenericAppAdapter',
                'status' => $hasAdapter ? 'AVAILABILITY_ONLY' : 'UNSUPPORTED',
                'confidence' => $hasAdapter ? 'high' : 'medium',
                'warnings' => $coverageWarnings,
            ];
        }

        return new AdapterResult($rows, $warnings, $unsupported, $adapterStatus);
    }

    public function getConfidence(): string {
        return 'medium';
    }

    public function getWarnings(): array {
        return [];
    }
}
