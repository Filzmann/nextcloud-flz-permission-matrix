<?php

declare(strict_types=1);

namespace OCP\EventDispatcher {
    if (!class_exists(Event::class)) {
        class Event {}
    }
}

namespace {
    use OCA\FilzmannPermissionMatrix\PublicApi\V1\PermissionCondition;
    use OCA\FilzmannPermissionMatrix\PublicApi\V1\PermissionProvider;
    use OCA\FilzmannPermissionMatrix\PublicApi\V1\PermissionProviderDescriptor;
    use OCA\FilzmannPermissionMatrix\PublicApi\V1\PermissionProviderResult;
    use OCA\FilzmannPermissionMatrix\PublicApi\V1\PermissionRule;
    use OCA\FilzmannPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;

    $condition = PermissionCondition::all([
        PermissionCondition::group('ad-team-a'),
        PermissionCondition::any([
            PermissionCondition::self(),
            PermissionCondition::nextcloudAdmin(),
        ]),
    ]);
    assertSameValue(
        ['operator' => 'all', 'children' => [
            ['operator' => 'group', 'group_id' => 'ad-team-a'],
            ['operator' => 'any', 'children' => [
                ['operator' => 'self'],
                ['operator' => 'nextcloud-admin'],
            ]],
        ]],
        $condition->toArray(),
        'The public contract must preserve group and non-group actor semantics losslessly.'
    );
    assertSameValue(
        ['operator' => 'app-admin-grant'],
        PermissionCondition::temporaryAppAdminGrant()->toArray(),
        'A provider must describe the app-local temporary admin grant separately from native Nextcloud administration.',
    );

    $rule = new PermissionRule(
        'Schedule',
        'Duty schedule',
        'Own entries',
        'schedule.entry.read',
        'Read',
        'allow',
        'own-entry',
        PermissionCondition::self(),
        'adplaner:policy',
        'high'
    );
    $result = new PermissionProviderResult([$rule], false, ['A dynamic exception remains.']);
    assertSameValue(false, $result->complete(), 'Providers must be able to report partial coverage explicitly.');
    assertSameValue('self', $result->rules()[0]->condition()->operator(), 'Self access must remain distinct from group access.');

    $compatible = new class($result) implements PermissionProvider {
        public function __construct(private PermissionProviderResult $result) {}
        public function descriptor(): PermissionProviderDescriptor {
            return new PermissionProviderDescriptor('adplaner', 'AD-Planer', '1.0', ['permissions']);
        }
        public function collect(): PermissionProviderResult { return $this->result; }
    };
    $incompatible = new class implements PermissionProvider {
        public function descriptor(): PermissionProviderDescriptor {
            return new PermissionProviderDescriptor('legacy_app', 'Legacy', '2.0', ['permissions']);
        }
        public function collect(): PermissionProviderResult { return new PermissionProviderResult([]); }
    };

    $event = new RegisterPermissionProvidersEvent();
    $event->register($compatible);
    $event->register($incompatible);
    $event->register($compatible);
    $event->register($compatible);

    assertSameValue([], array_keys($event->providers()), 'A duplicate registration must exclude the ambiguous provider instead of choosing one silently.');
    assertSameValue(
        ['legacy_app' => 'Provider incompatible.', 'adplaner' => 'Provider incompatible.'],
        $event->registrationFailures(),
        'Incompatible and duplicate providers must remain visible without breaking discovery.'
    );

    echo 'Permission provider contract tests passed' . PHP_EOL;
}
