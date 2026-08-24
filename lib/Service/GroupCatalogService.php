<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Service;

/**
 * Erzeugt eine historische, verlustfreie Präsentationssicht auf rohe Nextcloud-Gruppen.
 *
 * Semantische Rollen und Bereiche stammen ausschließlich aus dem validierten
 * LocalBase-Organisationssnapshot. App-spezifische AdPlaner-Teamfamilien werden nur dann
 * ergänzt, wenn diese kanonische Organisationsquelle im selben Scan gültig war.
 */
class GroupCatalogService {
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
    ];

    public function catalog(array $groups, array $organizationSnapshot = []): array {
        $groups = array_values(array_unique(array_map('strval', $groups)));
        $validOrganization = ($organizationSnapshot['status'] ?? '') === 'VALID';
        if (!$validOrganization) {
            $individual = array_map(
                fn(string $group): array => $this->entry(
                    $group,
                    $group,
                    'group',
                    null,
                    null,
                    [$group],
                    'UNKNOWN'
                ),
                $groups
            );
            usort($individual, static fn(array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

            return $individual;
        }

        $semanticGroups = $this->semanticGroups($organizationSnapshot);
        $families = [];
        $individual = [];
        foreach ($groups as $group) {
            if (isset($semanticGroups[$group])) {
                $semantic = $semanticGroups[$group];
                $individual[] = $this->entry(
                    $group,
                    $semantic['prefix'] . ' · ' . $semantic['label'],
                    'group',
                    'localbase',
                    null,
                    [$group],
                    'KNOWN',
                    $semantic['type'],
                    $semantic['key'],
                    [[
                        'group' => $group,
                        'label' => $semantic['prefix'] . ' · ' . $semantic['label'],
                        'team' => null,
                        'role' => $semantic['type'] === 'role' ? $semantic['key'] : null,
                    ]]
                );
                continue;
            }

            $familyKey = $this->familyKey($group);
            if ($familyKey === null) {
                $individual[] = $this->entry(
                    $group,
                    $group,
                    'group',
                    null,
                    null,
                    [$group],
                    'UNKNOWN'
                );
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
                $members,
                'KNOWN',
                'family',
                $key
            );
        }

        usort($individual, static fn(array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return [...$catalog, ...$individual];
    }

    private function semanticGroups(array $snapshot): array {
        $semantic = [];
        foreach ([
            'roles' => ['type' => 'role', 'prefix' => 'Rolle'],
            'areas' => ['type' => 'area', 'prefix' => 'Bereich'],
        ] as $collection => $definition) {
            foreach (is_array($snapshot[$collection] ?? null) ? $snapshot[$collection] : [] as $key => $mapping) {
                if (!is_array($mapping)) {
                    continue;
                }
                $groupId = (string)($mapping['groupId'] ?? '');
                if ($groupId === '') {
                    continue;
                }
                $semantic[$groupId] = [
                    'type' => $definition['type'],
                    'prefix' => $definition['prefix'],
                    'key' => (string)$key,
                    'label' => (string)($mapping['label'] ?? $key),
                ];
            }
        }

        return $semantic;
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
        array $groups,
        string $meaningStatus,
        ?string $semanticType = null,
        ?string $semanticKey = null,
        ?array $members = null
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'source_app' => $sourceApp,
            'family' => $family,
            'groups' => array_values($groups),
            'count' => count($groups),
            'meaning_status' => $meaningStatus,
            'semantic_type' => $semanticType,
            'semantic_key' => $semanticKey,
            'members' => $members ?? array_map(fn(string $group): array => $this->member($group), $groups),
        ];
    }

    private function member(string $group): array {
        $team = null;
        $role = null;
        $roleLabel = null;

        if (preg_match('/^ad-ASN-([\p{L}\p{N}]{1,16})(-Urlaub)?$/u', $group, $matches) === 1) {
            $team = $matches[1];
            $role = ($matches[2] ?? '') === '-Urlaub' ? 'vacation' : 'assistant';
            $roleLabel = $role === 'vacation' ? 'Urlaub' : 'Assistenz';
        }

        return [
            'group' => $group,
            'label' => $role === null ? $group : 'Team ' . $team . ' · ' . $roleLabel,
            'team' => $team,
            'role' => $role,
        ];
    }
}
