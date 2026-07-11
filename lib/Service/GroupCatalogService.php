<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Service;

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
        'adplaner_eb_roles' => [
            'label' => 'AdPlaner · Einsatzbegleitung',
            'source_app' => 'adplaner',
            'pattern' => '/^ad-EB-.+$/u',
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
        ];
    }
}
