<?php

declare(strict_types=1);

use OCA\FlzPermissionMatrix\Service\BrMembershipService;
use OCA\FlzPermissionMatrix\Service\OrganizationSnapshotService;

final class BrMembershipOrganizationSnapshot extends OrganizationSnapshotService {
    public function __construct(private array $payload) {}
    public function snapshot(): array { return $this->payload; }
}

function brOrganizationSnapshot(
    string $status = 'VALID',
    array $identityGroups = ['works_council_member' => ['groupId' => 'BR-Kanonisch', 'label' => 'Betriebsratsmitglieder']],
): array {
    return [
        'status' => $status,
        'contract_version' => $status === 'VALID' ? '1.0' : '',
        'definition_version' => $status === 'VALID' ? 5 : 0,
        'checksum' => $status === 'VALID' ? 'organization-checksum' : '',
        'roles' => [],
        'areas' => [],
        'identity_groups' => $identityGroups,
        'warning' => $status === 'VALID' ? null : 'The organization provider is unavailable.',
    ];
}

$valid = new BrMembershipService(new BrMembershipOrganizationSnapshot(brOrganizationSnapshot()));
assertSameValue('VALID', $valid->snapshot()['status'], 'A valid organization snapshot must provide the canonical works-council membership identity.');
assertSameValue('BR-Kanonisch', $valid->memberGroupId(), 'The canonical BR group must come from flz_organization.');

foreach (['MISSING', 'INCOMPATIBLE', 'INVALID', 'UNAVAILABLE'] as $status) {
    $failed = new BrMembershipService(new BrMembershipOrganizationSnapshot(brOrganizationSnapshot($status, [])));
    assertSameValue($status, $failed->snapshot()['status'], "Organization provider state {$status} must remain fail-closed.");
    assertSameValue(null, $failed->memberGroupId(), "Organization provider state {$status} must not authorize BR access.");
}

$missingIdentity = new BrMembershipService(new BrMembershipOrganizationSnapshot(brOrganizationSnapshot(identityGroups: [])));
assertSameValue('INVALID', $missingIdentity->snapshot()['status'], 'A valid organization snapshot without the canonical BR identity must fail closed.');
assertSameValue(null, $missingIdentity->memberGroupId(), 'A missing identity mapping must not authorize BR access.');

$manipulatedIdentity = new BrMembershipService(new BrMembershipOrganizationSnapshot(brOrganizationSnapshot(identityGroups: [
    'works_council_member' => ['groupId' => "BR\nInjected", 'label' => 'Betriebsratsmitglieder'],
])));
assertSameValue('INVALID', $manipulatedIdentity->snapshot()['status'], 'A manipulated group identity must fail closed.');
assertSameValue(null, $manipulatedIdentity->memberGroupId(), 'A manipulated identity must not authorize BR access.');

echo 'BrMembershipService tests passed' . PHP_EOL;
