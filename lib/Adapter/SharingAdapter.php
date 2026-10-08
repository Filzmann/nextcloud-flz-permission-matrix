<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Adapter;

use OCA\FlzPermissionMatrix\Model\AccessCondition;
use OCA\FlzPermissionMatrix\Model\AccessRule;
use OCA\FlzPermissionMatrix\Model\MatrixRow;
use OCA\FlzPermissionMatrix\Service\ConfigService;
use OCA\FlzPermissionMatrix\Service\InventoryService;
use OCA\FlzPermissionMatrix\Service\NativeSharingSourceInterface;

/**
 * Zweck: Bildet native Nextcloud-Sharing-Policies und optional konkrete Gruppenfreigaben ab.
 *
 * Vertrag:
 * - Einzelne Freigaben werden nur bei include_share_metadata=true gelesen und persistiert.
 * - Ungueltige Pfade, unbekannte Gruppen und unvollstaendige Quellen bleiben fail-closed.
 * - Nextcloud kennt fuer Shares kein Ausfuehren-Bit; dieses Recht wird deshalb als n/a ausgewiesen.
 */
class SharingAdapter implements PermissionAdapterInterface {
    private const KNOWN_PERMISSION_MASK = 31;

    public function __construct(
        private InventoryService $inventory,
        private ConfigService $config,
        private NativeSharingSourceInterface $source
    ) {
    }

    public function supports(string $appId): bool {
        return $appId === 'files_sharing';
    }

    public function collect(): AdapterResult {
        if (!$this->inventory->isAppEnabled('files_sharing')) {
            return AdapterResult::empty();
        }

        $groups = $this->inventory->groups();
        $rows = [];
        $warnings = [];
        $policyUnknown = false;
        try {
            $policies = $this->source->policies();
        } catch (\Throwable) {
            $policies = ['values' => [], 'warnings' => ['Native Nextcloud-Sharing-Policies konnten nicht gelesen werden.']];
        }
        $warnings = [...$warnings, ...(is_array($policies['warnings'] ?? null) ? $policies['warnings'] : [])];

        $definitions = [
            'sharing' => ['Dateifreigaben global', 'Sharing insgesamt'],
            'group_sharing' => ['Gruppen-Sharing', 'Freigabe an Gruppen'],
            'link_sharing' => ['Link-Sharing', 'Oeffentliche Links'],
            'resharing' => ['Resharing', 'Weiterfreigabe'],
            'link_password' => ['Passwortpflicht fuer Links', 'Schutzpflicht'],
            'link_expiry' => ['Ablaufdatum fuer Links', 'Ablaufdatum'],
            'link_expiry_enforced' => ['Ablaufdatum fuer Links erzwungen', 'Ablaufdatum'],
            'public_upload' => ['Upload ueber oeffentliche Links', 'Oeffentlicher Upload'],
            'group_members_only' => ['Teilen nur mit Gruppenmitgliedern', 'Empfaengerbegrenzung'],
            'internal_expiry' => ['Ablaufdatum fuer interne Freigaben', 'Ablaufdatum'],
            'remote_expiry' => ['Ablaufdatum fuer Federated Shares', 'Ablaufdatum'],
        ];
        foreach ($definitions as $key => [$label, $type]) {
            $policy = is_array($policies['values'][$key] ?? null) ? $policies['values'][$key] : [];
            $value = is_bool($policy['value'] ?? null) ? $policy['value'] : null;
            $unknown = $value === null;
            $policyUnknown = $policyUnknown || $unknown;
            $rows[] = new MatrixRow(
                'SharingPolicy',
                'files_sharing',
                $label,
                'Globale Nextcloud-Policy',
                $type,
                $unknown ? 'UNKNOWN' : 'NEW',
                (string)($policy['source'] ?? 'nextcloud:OCP\\Share\\IManager'),
                $unknown ? 'low' : (string)($policy['confidence'] ?? 'high'),
                array_fill_keys($groups, $unknown ? '?' : ($value ? 'allow' : 'deny')),
                $unknown ? ['Policy konnte nicht eindeutig aus der nativen Quelle gelesen werden.'] : []
            );
        }

        if (!$this->config->includeShareMetadata()) {
            $rows[] = new MatrixRow(
                'SharingPolicy',
                'files_sharing',
                'Einzelne Gruppenfreigaben',
                'Durch include_share_metadata deaktiviert',
                'Datenschutzgrenze',
                'NEW',
                'flz_permission_matrix:ConfigService',
                'high',
                array_fill_keys($groups, 'n/a')
            );

            return new AdapterResult($rows, array_values(array_unique($warnings)), [], [[
                'app_id' => 'files_sharing',
                'adapter' => self::class,
                'status' => $policyUnknown ? 'PARTIAL' : 'IMPLEMENTED',
                'confidence' => $policyUnknown ? 'low' : 'high',
                'warnings' => array_values(array_unique($warnings)),
            ]]);
        }

        try {
            $shareResult = $this->source->groupShares();
        } catch (\Throwable) {
            $shareResult = [
                'shares' => [],
                'complete' => false,
                'warnings' => ['Native Gruppenfreigaben konnten nicht gelesen werden.'],
            ];
        }
        $complete = ($shareResult['complete'] ?? false) === true;
        $shareWarnings = is_array($shareResult['warnings'] ?? null) ? $shareResult['warnings'] : [];
        $warnings = [...$warnings, ...$shareWarnings];
        $validatedShares = [];
        foreach (is_array($shareResult['shares'] ?? null) ? $shareResult['shares'] : [] as $share) {
            if (!is_array($share)) {
                $complete = false;
                $warnings[] = 'Die Share-Quelle lieferte einen unbekannten Datensatztyp.';
                continue;
            }
            $target = $this->normalizeTarget((string)($share['target'] ?? ''));
            if ($target === null) {
                $complete = false;
                $warnings[] = 'Mindestens eine Gruppenfreigabe hatte einen ungültigen oder mehrdeutigen Zielpfad.';
                continue;
            }
            $groupId = trim((string)($share['group_id'] ?? ''));
            $knownGroup = in_array($groupId, $groups, true);
            if (!$knownGroup) {
                $complete = false;
                $warnings[] = 'Mindestens eine Share-Zielgruppe fehlt im aktuellen Gruppeninventar.';
            }
            $permissions = (int)($share['permissions'] ?? -1);
            $validMask = $permissions >= 0 && ($permissions & ~self::KNOWN_PERMISSION_MASK) === 0;
            if (!$validMask) {
                $complete = false;
                $warnings[] = 'Mindestens eine Gruppenfreigabe enthielt unbekannte Permission-Bits.';
            }
            $reference = substr(hash('sha256', (string)($share['reference'] ?? 'missing')), 0, 12);
            $nodeType = strtolower((string)($share['node_type'] ?? ''));
            if (!in_array($nodeType, ['folder', 'file'], true)) {
                $complete = false;
                $warnings[] = 'Mindestens eine Gruppenfreigabe hatte einen unbekannten Node-Typ.';
                continue;
            }
            $validatedShares[] = [
                'group_id' => $groupId,
                'known_group' => $knownGroup,
                'target' => $target,
                'object_type' => $nodeType === 'folder' ? 'SharedFolder' : 'SharedFile',
                'permissions' => $permissions,
                'valid_mask' => $validMask,
                'reference' => $reference,
                'inherited' => ($share['inherited'] ?? false) === true,
            ];
        }

        $signatures = [];
        foreach ($validatedShares as $share) {
            $key = implode("\0", [$share['group_id'], $share['target'], $share['object_type']]);
            $signatures[$key][(string)$share['permissions']] = true;
        }
        foreach ($signatures as $permissionSets) {
            if (count($permissionSets) > 1) {
                $complete = false;
                $warnings[] = 'Die native Share-Quelle lieferte widersprüchliche Rechte fuer dasselbe Gruppen-Ziel.';
            }
        }

        $shareRows = [];
        foreach ($validatedShares as $share) {
            $shareRows = [...$shareRows, ...$this->permissionRows(
                $groups,
                $share['group_id'],
                $share['known_group'],
                $share['target'],
                $share['object_type'],
                $share['permissions'],
                $share['valid_mask'],
                $complete,
                $share['reference'],
                $share['inherited'],
                $shareWarnings
            )];
        }

        if ($shareRows === []) {
            $shareRows[] = new MatrixRow(
                'SharedFolder',
                'files_sharing',
                $complete ? 'Keine Gruppenfreigaben gefunden' : 'Gruppenfreigaben nicht auslesbar',
                'Native Gruppenfreigaben',
                'Bestandsstatus',
                $complete ? 'NEW' : 'UNKNOWN',
                'nextcloud:OCP\\Share\\IManager::getSharesBy',
                $complete ? 'high' : 'low',
                array_fill_keys($groups, $complete ? 'n/a' : '?'),
                $complete ? [] : ['Die konkrete Gruppenfreigaben-Matrix ist unvollstaendig.']
            );
        }
        $rows = [...$rows, ...$shareRows];
        $warnings = array_values(array_unique($warnings));

        return new AdapterResult($rows, $warnings, [], [[
            'app_id' => 'files_sharing',
            'adapter' => self::class,
            'status' => $complete && !$policyUnknown ? 'IMPLEMENTED' : 'PARTIAL',
            'confidence' => $complete && !$policyUnknown ? 'high' : 'low',
            'warnings' => $warnings,
        ]]);
    }

    private function permissionRows(
        array $groups,
        string $groupId,
        bool $knownGroup,
        string $target,
        string $objectType,
        int $permissions,
        bool $validMask,
        bool $complete,
        string $reference,
        bool $inherited,
        array $sourceWarnings
    ): array {
        $definitions = [
            ['Lesen', 1, 'R', 'files.read'],
            ['Ändern', 2, 'W', 'files.update'],
            ['Ausführen', 0, 'n/a', 'files.execute'],
            ['Löschen', 8, 'D', 'files.delete'],
            ['Erstellen', 4, 'C', 'files.create'],
            ['Teilen', 16, 'S', 'files.share'],
        ];
        $rows = [];
        $rowWarnings = array_values(array_unique([
            ...$sourceWarnings,
            ...(!$knownGroup ? ['Die Zielgruppe ist nicht mehr im Gruppeninventar vorhanden.'] : []),
            ...(!$validMask ? ['Die Quelle enthielt zusaetzliche, nicht abgebildete Permission-Bits.'] : []),
            ...(!$complete ? ['Die Gruppenfreigaben-Enumeration ist unvollstaendig.'] : []),
        ]));
        $status = $complete && $knownGroup && $validMask ? 'NEW' : 'UNKNOWN';

        foreach ($definitions as [$label, $bit, $symbol, $permission]) {
            $cells = array_fill_keys($groups, $knownGroup ? '-' : '?');
            $granted = $bit !== 0 && ($permissions & $bit) === $bit;
            if ($knownGroup) {
                $cells[$groupId] = $bit === 0 ? 'n/a' : ($granted ? $symbol : '-');
            }
            $rules = [];
            if ($granted && $knownGroup) {
                $rules[] = new AccessRule(
                    $permission,
                    'allow',
                    'group-share:' . $reference,
                    AccessCondition::group($groupId),
                    'nextcloud:OCP\\Share\\IManager::getSharesBy',
                    $status === 'NEW' ? 'high' : 'low'
                );
            }
            $rows[] = new MatrixRow(
                $objectType,
                'files_sharing',
                $target,
                ($inherited ? 'Weiterfreigabe' : 'Direkte Gruppenfreigabe') . ' · Referenz ' . $reference,
                $label,
                $status,
                'nextcloud:OCP\\Share\\IManager::getSharesBy',
                $status === 'NEW' ? 'high' : 'low',
                $cells,
                $rowWarnings,
                $rules
            );
        }

        return $rows;
    }

    private function normalizeTarget(string $target): ?string {
        $target = trim($target);
        if ($target === '' || strlen($target) > 4096 || str_contains($target, '\\')) {
            return null;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $target) === 1) {
            return null;
        }
        $segments = [];
        foreach (explode('/', $target) as $segment) {
            if ($segment === '') {
                continue;
            }
            if ($segment === '.' || $segment === '..') {
                return null;
            }
            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }

    public function getConfidence(): string {
        return 'high';
    }

    public function getWarnings(): array {
        return [];
    }
}
