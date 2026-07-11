<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Adapter;

use OCA\BrPermissionMatrix\Model\AccessCondition;
use OCA\BrPermissionMatrix\Model\AccessRule;
use OCA\BrPermissionMatrix\Model\MatrixRow;
use OCA\BrPermissionMatrix\Service\InventoryService;

/**
 * Zweck: Bildet die im AdPlaner serverseitig erzwungenen Team- und EB-Bedingungen ab.
 *
 * Zusammenspiel:
 * - InventoryService liefert die vorhandenen Rohgruppen; die Regeln spiegeln
 *   AdPlaner TeamAccessService::assertTeamAccess() und ::assertCanCoordinate().
 *
 * Vertrag:
 * - Teamzugriff erfordert die konkrete ad-ASN-Gruppe.
 * - Koordination erfordert dieselbe Teamgruppe UND mindestens eine vorhandene ad-EB-Rollengruppe.
 * - PFK-Regeln werden erst aufgenommen, sobald AdPlaner sie selbst serverseitig erzwingt.
 */
class AdPlanerAdapter implements PermissionAdapterInterface {
    public function __construct(
        private InventoryService $inventory
    ) {
    }

    public function supports(string $appId): bool {
        return $appId === 'adplaner';
    }

    public function collect(): AdapterResult {
        if (!$this->inventory->isAppEnabled('adplaner')) {
            return AdapterResult::empty();
        }

        $groups = $this->inventory->groups();
        $teamGroups = array_values(array_filter(
            $groups,
            static fn(string $group): bool => preg_match('/^ad-ASN-[\p{L}\p{N}]{1,16}$/u', $group) === 1
        ));
        $ebGroups = array_values(array_filter(
            $groups,
            static fn(string $group): bool => preg_match('/^ad-EB-.+$/u', $group) === 1
        ));
        $rows = [];
        $warnings = [];

        foreach ($teamGroups as $teamGroup) {
            $teamCode = substr($teamGroup, strlen('ad-ASN-'));
            $teamCells = array_fill_keys($groups, '-');
            $teamCells[$teamGroup] = 'X';
            $rows[] = new MatrixRow(
                'AppPermission',
                'adplaner',
                'Team anzeigen · ' . $teamCode,
                'Mitgliedschaft in der konkreten Assistenznehmer-Gruppe',
                'Teamzugriff',
                'NEW',
                'adplaner:TeamAccessService::assertTeamAccess',
                'high',
                $teamCells,
                [],
                [new AccessRule(
                    'team.view',
                    'allow',
                    'team:' . $teamCode,
                    AccessCondition::group($teamGroup),
                    'adplaner:TeamAccessService::assertTeamAccess',
                    'high'
                )]
            );

            $coordinateCells = array_fill_keys($groups, '-');
            $coordinateCells[$teamGroup] = 'AND';
            foreach ($ebGroups as $ebGroup) {
                $coordinateCells[$ebGroup] = 'AND';
            }
            $rowWarnings = [];
            $accessRules = [];
            $status = 'NEW';
            if ($ebGroups === []) {
                $status = 'UNKNOWN';
                $rowWarnings[] = 'Keine ad-EB-Rollengruppe vorhanden; Koordinationsbedingung ist derzeit nicht erfuellbar.';
                $warnings[] = 'adplaner: keine ad-EB-Rollengruppe fuer Koordinationsrechte vorhanden.';
            } else {
                $accessRules[] = new AccessRule(
                    'team.coordinate',
                    'allow',
                    'team:' . $teamCode,
                    AccessCondition::all([
                        AccessCondition::group($teamGroup),
                        AccessCondition::any(array_map(
                            static fn(string $group): AccessCondition => AccessCondition::group($group),
                            $ebGroups
                        )),
                    ]),
                    'adplaner:TeamAccessService::assertCanCoordinate',
                    'high'
                );
            }
            $rows[] = new MatrixRow(
                'AppPermission',
                'adplaner',
                'Team koordinieren · ' . $teamCode,
                'Teammitgliedschaft UND EB-Rolle',
                'Dienst- und Urlaubsplanung',
                $status,
                'adplaner:TeamAccessService::assertCanCoordinate',
                $ebGroups === [] ? 'low' : 'high',
                $coordinateCells,
                $rowWarnings,
                $accessRules
            );
        }

        $coverageWarning = 'AdPlaner-Teamzugriff und EB-Koordination sind ausgelesen; weitere Funktionsrechte bleiben offen.';
        $warnings[] = $coverageWarning;

        return new AdapterResult($rows, $warnings, [], [[
            'app_id' => 'adplaner',
            'adapter' => self::class,
            'status' => 'PARTIAL',
            'confidence' => 'high',
            'warnings' => [$coverageWarning],
        ]]);
    }

    public function getConfidence(): string {
        return 'high';
    }

    public function getWarnings(): array {
        return [];
    }
}
