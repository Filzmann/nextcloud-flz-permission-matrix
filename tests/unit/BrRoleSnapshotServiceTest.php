<?php

declare(strict_types=1);

use OCA\FlzPermissionMatrix\PublicApi\V1\BrRoleSnapshotService;
use OCA\FlzPermissionMatrix\Service\OrganizationSnapshotService;

final class BrRoleOrganizationSnapshotStub extends OrganizationSnapshotService {
    public function __construct(private array $payload) {}
    public function snapshot(): array { return $this->payload; }
}

function brRoleOrganizationSnapshot(string $status, array $identityGroups): array {
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

$service = new BrRoleSnapshotService(new BrRoleOrganizationSnapshotStub(brRoleOrganizationSnapshot('VALID', [
    'works_council_member' => ['groupId' => 'BR-Kanonisch', 'label' => 'Betriebsratsmitglieder'],
    'works_council_chair' => ['groupId' => 'BR-Vorsitz', 'label' => 'Betriebsratsvorsitzende'],
    'works_council_deputy' => ['groupId' => 'BR-Stellvertretung', 'label' => 'Stellvertretende Betriebsratsvorsitzende'],
])));
$snapshot = $service->snapshot();
assertSameValue('1.0', $snapshot->contractVersion(), 'BR role contract version must be 1.0.');
assertSameValue(true, $snapshot->isValid(), 'A valid organization snapshot must produce a valid BR role snapshot.');
assertSameValue('BR-Kanonisch', $snapshot->memberGroupId(), 'Member group must come from the canonical identity.');
assertSameValue('BR-Vorsitz', $snapshot->chairGroupId(), 'Chair group must come from the canonical identity.');
assertSameValue('BR-Stellvertretung', $snapshot->deputyGroupId(), 'Deputy group must come from the canonical identity.');
assertSameValue(64, strlen($snapshot->checksum()), 'The BR role checksum must be a sha256 hex digest.');

$broken = new BrRoleSnapshotService(new BrRoleOrganizationSnapshotStub(brRoleOrganizationSnapshot('MISSING', [])));
$invalid = $broken->snapshot();
assertSameValue(false, $invalid->isValid(), 'A missing organization provider must produce an invalid BR role snapshot.');
assertSameValue(null, $invalid->memberGroupId(), 'An invalid BR role snapshot must not expose group identities.');
assertSameValue(null, $invalid->chairGroupId(), 'An invalid BR role snapshot must not expose group identities.');
assertSameValue(null, $invalid->deputyGroupId(), 'An invalid BR role snapshot must not expose group identities.');

echo 'BrRoleSnapshotService tests passed' . PHP_EOL;
