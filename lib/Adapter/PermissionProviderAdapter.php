<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Adapter;

use OCA\FlzPermissionMatrix\Model\AccessCondition;
use OCA\FlzPermissionMatrix\Model\AccessRule;
use OCA\FlzPermissionMatrix\Model\MatrixRow;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionCondition;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionRule;
use OCA\FlzPermissionMatrix\Service\InventoryService;
use OCA\FlzPermissionMatrix\Service\PermissionProviderSourceInterface;
use Throwable;

final class PermissionProviderAdapter implements PermissionAdapterInterface {
    public function __construct(
        private InventoryService $inventory,
        private PermissionProviderSourceInterface $providers,
    ) {}

    public function supports(string $appId): bool { return $this->providers->hasProvider($appId); }

    public function collect(): AdapterResult {
        $groups = $this->inventory->groups();
        $enabled = array_flip($this->inventory->enabledAppIds());
        $rows = [];
        $warnings = [];
        $statuses = [];

        foreach ($this->providers->registrationFailures() as $appId => $failure) {
            $warnings[] = $appId . ': ' . $failure;
        }
        foreach ($this->providers->providers() as $appId => $provider) {
            if (!isset($enabled[$appId])) {
                continue;
            }
            try {
                $result = $provider->collect();
                foreach ($result->rules() as $rule) {
                    $rows[] = $this->row($appId, $rule, $groups, $result->complete(), $result->warnings());
                }
                if ($result->rules() === [] || !$result->complete()) {
                    $warnings[] = $appId . ': Providerabdeckung ist unvollstaendig.';
                }
                $statuses[] = [
                    'app_id' => $appId,
                    'adapter' => 'PermissionProviderAdapter',
                    'status' => $result->complete() ? 'SUPPORTED' : 'UNKNOWN',
                    'confidence' => $result->complete() ? 'high' : 'low',
                    'warnings' => $result->warnings(),
                ];
            } catch (Throwable) {
                $message = 'Der Berechtigungsprovider konnte nicht sicher ausgewertet werden.';
                $warnings[] = $appId . ': ' . $message;
                $rows[] = new MatrixRow('AppPermission', $appId, $appId, 'Providerabdeckung', 'Unbekannt', 'UNKNOWN', 'permission-provider:v1', 'low', array_fill_keys($groups, '?'), [$message]);
                $statuses[] = ['app_id' => $appId, 'adapter' => 'PermissionProviderAdapter', 'status' => 'UNKNOWN', 'confidence' => 'low', 'warnings' => [$message]];
            }
        }

        return new AdapterResult($rows, $warnings, [], $statuses);
    }

    private function row(string $appId, PermissionRule $rule, array $groups, bool $complete, array $providerWarnings): MatrixRow {
        $cells = array_fill_keys($groups, 'n/a');
        $projection = $this->groupProjection($rule->condition());
        if ($projection !== null) {
            $cells = array_fill_keys($groups, '-');
            foreach ($projection['groups'] as $groupId) {
                if (array_key_exists($groupId, $cells)) {
                    $cells[$groupId] = $rule->effect() === 'deny' ? 'deny' : $projection['value'];
                }
            }
        }
        $warnings = $providerWarnings;
        if ($projection === null) {
            $warnings[] = 'Akteursbedingung ist nicht als pauschales Gruppenrecht darstellbar.';
        }
        return new MatrixRow(
            $rule->objectType(),
            $appId,
            $rule->objectName(),
            $rule->detail(),
            $rule->permissionLabel(),
            $complete ? 'NEW' : 'UNKNOWN',
            $rule->source(),
            $complete ? $rule->confidence() : 'low',
            $cells,
            $warnings,
            [new AccessRule($rule->permission(), $rule->effect(), $rule->scope(), $this->condition($rule->condition()), $rule->source(), $rule->confidence())]
        );
    }

    private function condition(PermissionCondition $condition): AccessCondition {
        return match ($condition->operator()) {
            'group' => AccessCondition::group((string)$condition->groupId()),
            'all' => AccessCondition::all(array_map(fn(PermissionCondition $child): AccessCondition => $this->condition($child), $condition->children())),
            'any' => AccessCondition::any(array_map(fn(PermissionCondition $child): AccessCondition => $this->condition($child), $condition->children())),
            'self' => AccessCondition::self(),
            'authenticated' => AccessCondition::authenticated(),
            'nextcloud-admin' => AccessCondition::nextcloudAdmin(),
            'app-admin-grant' => AccessCondition::temporaryAppAdminGrant(),
        };
    }

    private function groupProjection(PermissionCondition $condition): ?array {
        if ($condition->operator() === 'group') {
            return ['groups' => [$condition->groupId()], 'value' => 'X'];
        }
        if (!in_array($condition->operator(), ['all', 'any'], true)) {
            return null;
        }
        $groups = [];
        foreach ($condition->children() as $child) {
            $projection = $this->groupProjection($child);
            if ($projection === null) {
                return null;
            }
            $groups = [...$groups, ...$projection['groups']];
        }
        return ['groups' => array_values(array_unique($groups)), 'value' => $condition->operator() === 'all' ? 'AND' : 'X'];
    }

    public function getConfidence(): string { return 'high'; }
    public function getWarnings(): array { return []; }
}
