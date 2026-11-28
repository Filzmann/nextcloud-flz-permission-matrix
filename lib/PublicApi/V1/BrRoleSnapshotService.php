<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\PublicApi\V1;

use OCA\FlzPermissionMatrix\Service\OrganizationSnapshotService;
use Throwable;

/**
 * Öffentliche read-only Projektion der kanonischen BR-Rollenidentitäten
 * (Mitglieder, Vorsitz, Stellvertretung) aus der flz_organization-V1-Definition.
 */
final class BrRoleSnapshotService {
    private const IDENTITY_MEMBER = 'works_council_member';
    private const IDENTITY_CHAIR = 'works_council_chair';
    private const IDENTITY_DEPUTY = 'works_council_deputy';

    public function __construct(private OrganizationSnapshotService $organization) {
    }

    public function snapshot(): BrRoleSnapshot {
        try {
            $organization = $this->organization->snapshot();
            if (($organization['status'] ?? null) !== 'VALID') {
                return new BrRoleSnapshot(false, 1, 0, null, null, null);
            }
            $identityGroups = is_array($organization['identity_groups'] ?? null) ? $organization['identity_groups'] : [];
            $member = $identityGroups[self::IDENTITY_MEMBER]['groupId'] ?? null;
            $chair = $identityGroups[self::IDENTITY_CHAIR]['groupId'] ?? null;
            $deputy = $identityGroups[self::IDENTITY_DEPUTY]['groupId'] ?? null;
            $definitionVersion = (int)($organization['definition_version'] ?? 0);

            return new BrRoleSnapshot(
                true,
                max(1, $definitionVersion),
                max(1, $definitionVersion),
                is_string($member) ? $member : null,
                is_string($chair) ? $chair : null,
                is_string($deputy) ? $deputy : null,
            );
        } catch (Throwable) {
            return new BrRoleSnapshot(false, 1, 0, null, null, null);
        }
    }
}
