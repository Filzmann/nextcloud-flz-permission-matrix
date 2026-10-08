<?php

declare(strict_types=1);

use OCA\FlzPermissionMatrix\Adapter\PermissionProviderAdapter;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionCondition;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProvider;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProviderDescriptor;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProviderResult;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionRule;
use OCA\FlzPermissionMatrix\Service\InventoryService;
use OCA\FlzPermissionMatrix\Service\PermissionProviderSourceInterface;

final class PermissionProviderInventory extends InventoryService {
    public function __construct() {}
    public function groups(): array { return ['ad-team-a', 'ad-team-b', 'other']; }
    public function enabledAppIds(): array { return ['flzplaner', 'failing_app']; }
}

$provider = new class implements PermissionProvider {
    public function descriptor(): PermissionProviderDescriptor {
        return new PermissionProviderDescriptor('flzplaner', 'Filzmann Assistenzplanung', '1.0', ['permissions']);
    }
    public function collect(): PermissionProviderResult {
        return new PermissionProviderResult([
            new PermissionRule('Schedule', 'Duty schedule', 'Coordination', 'schedule.coordinate', 'Coordinate', 'allow', 'all-teams', PermissionCondition::all([
                PermissionCondition::group('ad-team-a'),
                PermissionCondition::group('ad-team-b'),
            ]), 'flzplaner:policy', 'high'),
            new PermissionRule('Schedule', 'Duty schedule', 'Own entry', 'schedule.own.read', 'Read own', 'allow', 'own-entry', PermissionCondition::self(), 'flzplaner:policy', 'high'),
            new PermissionRule('Schedule', 'Duty schedule', 'Signed in', 'schedule.read', 'Read', 'allow', 'app', PermissionCondition::authenticated(), 'flzplaner:policy', 'high'),
            new PermissionRule('Schedule', 'Duty schedule', 'Temporary administration', 'schedule.admin', 'Administer', 'allow', 'app', PermissionCondition::all([
                PermissionCondition::nextcloudAdmin(),
                PermissionCondition::temporaryAppAdminGrant(),
            ]), 'flzplaner:policy', 'high'),
        ]);
    }
};
$failing = new class implements PermissionProvider {
    public function descriptor(): PermissionProviderDescriptor {
        return new PermissionProviderDescriptor('failing_app', 'Failing app', '1.0', ['permissions']);
    }
    public function collect(): PermissionProviderResult { throw new RuntimeException('private failure detail'); }
};
$source = new class($provider, $failing) implements PermissionProviderSourceInterface {
    public function __construct(private PermissionProvider $provider, private PermissionProvider $failing) {}
    public function providers(): array { return ['flzplaner' => $this->provider, 'failing_app' => $this->failing]; }
    public function registrationFailures(): array { return []; }
    public function hasProvider(string $appId): bool { return in_array($appId, ['flzplaner', 'failing_app'], true); }
};

$result = (new PermissionProviderAdapter(new PermissionProviderInventory(), $source))->collect();
$rows = $result->rows();

assertSameValue(5, count($rows), 'Every provider rule plus a visible provider failure row must be returned.');
assertSameValue(['AND', 'AND', '-'], array_values($rows[0]->cells()), 'AND policies must not be presented as independent group grants.');
assertSameValue(['n/a', 'n/a', 'n/a'], array_values($rows[1]->cells()), 'Self policies must not be translated into group grants.');
assertSameValue('self', $rows[1]->accessRules()[0]->toArray()['condition']['operator'], 'Self semantics must remain machine-readable.');
assertSameValue(['n/a', 'n/a', 'n/a'], array_values($rows[2]->cells()), 'Authenticated policies must not be translated into group grants.');
assertSameValue(['n/a', 'n/a', 'n/a'], array_values($rows[3]->cells()), 'Temporary admin grants must not be translated into group grants.');
assertContainsText('Nextcloud-Administration UND Aktive zeitlich begrenzte App-Adminfreigabe', $rows[3]->accessRules()[0]->conditionText(), 'The combined temporary admin condition must remain explicit.');
assertSameValue('UNKNOWN', $rows[4]->status(), 'A failing provider must produce visible unknown coverage.');
assertSameValue(['?', '?', '?'], array_values($rows[4]->cells()), 'A failing provider must not synthesize denies or grants.');
assertSameValue(false, str_contains(implode(' ', $rows[4]->warnings()), 'private failure detail'), 'Provider exceptions must not leak internal details.');

echo 'Permission provider adapter tests passed' . PHP_EOL;
