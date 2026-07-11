<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Service;

/**
 * Zweck: Fasst bekannte technische Massengruppen fuer die Darstellung zu fachlichen Familien zusammen.
 *
 * Zusammenspiel:
 * - MatrixBuilder speichert den Katalog zusaetzlich zu den Rohgruppen im Snapshot; UI und
 *   ExportService aggregieren nur die Darstellung anhand der enthaltenen Gruppen.
 *
 * Vertrag:
 * - Keine Rohgruppe oder Matrixzelle wird ersetzt.
 * - Nicht exakt erkannte Namensvarianten bleiben sichtbare Einzelgruppen.
 */
class GroupCatalogService {
    /**
     * Bedeutung: Explizit bestaetigte Namensvertraege der Quell-Apps; die Reihenfolge bestimmt
     * zugleich die stabile Reihenfolge der Familien in UI und Export.
     *
     * Zusammenspiel:
     * - Die Muster folgen AdPlaner TeamAccessService::teamsForCurrentUser(),
     *   TeamAccessService::currentUserIsEbForTeam() und den dort erzeugten Urlaubsgruppen.
     */
    private const FAMILIES = [
        'adplaner_vacation_visibility' => [
            'label' => 'AdPlaner · Urlaubssichtbarkeit',
            'source_app' => 'adplaner',
            'pattern' => '/^ad-ASN-[\p{L}\p{N}]{1,16}-Urlaub$/u',
        ],
        'adplaner_assistance_teams' => [
            'label' => 'AdPlaner · Assistenznehmer-Teams',
            'source_app' => 'adplaner',
            'pattern' => '/^ad-ASN-[\p{L}\p{N}]{1,16}$/u',
        ],
        'adplaner_eb_roles' => [
            'label' => 'AdPlaner · Einsatzbegleitung',
            'source_app' => 'adplaner',
            'pattern' => '/^ad-EB-.+$/u',
        ],
        'adplaner_pfk_roles' => [
            'label' => 'AdPlaner · Pflegefachkraefte',
            'source_app' => 'adplaner',
            'pattern' => '/^ad-PFK-.+$/u',
        ],
    ];

    public function catalog(array $groups): array {
        $families = [];
        $individual = [];

        foreach (array_values(array_unique(array_map('strval', $groups))) as $group) {
            $familyKey = $this->familyKey($group);
            if ($familyKey === null) {
                $individual[] = $this->entry($group, $group, 'group', null, null, [$group]);
                continue;
            }

            $families[$familyKey][] = $group;
        }

        $catalog = [];
        foreach (self::FAMILIES as $key => $definition) {
            if (!isset($families[$key])) {
                continue;
            }
            $members = $families[$key];
            sort($members, SORT_NATURAL | SORT_FLAG_CASE);
            $catalog[] = $this->entry(
                'family:' . $key,
                $definition['label'],
                'family',
                $definition['source_app'],
                $key,
                $members
            );
        }

        usort($individual, static fn(array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
        return [...$catalog, ...$individual];
    }

    private function familyKey(string $group): ?string {
        foreach (self::FAMILIES as $key => $definition) {
            if (preg_match($definition['pattern'], $group) === 1) {
                return $key;
            }
        }
        return null;
    }

    private function entry(
        string $key,
        string $label,
        string $type,
        ?string $sourceApp,
        ?string $family,
        array $groups
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'source_app' => $sourceApp,
            'family' => $family,
            'groups' => array_values($groups),
            'count' => count($groups),
            'members' => array_map(fn(string $group): array => $this->member($group), $groups),
        ];
    }

    /**
     * Zweck: Liefert eine verstaendliche Beschriftung fuer technische Teamgruppen.
     *
     * Vertrag:
     * - Die Rohgruppen-ID bleibt der Schluessel; Team und Rolle sind reine Anzeige-Metadaten.
     * - Unbekannte Gruppen werden unveraendert und ohne erfundene Teamzuordnung geliefert.
     *
     * @return array{group: string, label: string, team: ?string, role: ?string}
     */
    private function member(string $group): array {
        $team = null;
        $role = null;
        $roleLabel = null;

        if (preg_match('/^ad-ASN-([\p{L}\p{N}]{1,16})(-Urlaub)?$/u', $group, $matches) === 1) {
            $team = $matches[1];
            $role = ($matches[2] ?? '') === '-Urlaub' ? 'vacation' : 'assistant';
            $roleLabel = $role === 'vacation' ? 'Urlaub' : 'Assistenz';
        } elseif (preg_match('/^ad-(EB|PFK)-(.+)$/u', $group, $matches) === 1) {
            $role = strtolower($matches[1]);
            $roleLabel = $matches[1] . ' · ' . $matches[2];
        }

        return [
            'group' => $group,
            'label' => $role === null ? $group : ($team === null ? 'Rolle ' . $roleLabel : 'Team ' . $team . ' · ' . $roleLabel),
            'team' => $team,
            'role' => $role,
        ];
    }
}
