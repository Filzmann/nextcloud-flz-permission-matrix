<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Service;

/**
 * Resolves the canonical works-council membership group from the already
 * activation- and version-checked organization snapshot.
 */
class BrMembershipService
{
    private const IDENTITY_KEY = 'works_council_member';

    /** @var array<string,mixed>|null */
    private ?array $resolved = null;

    public function __construct(private OrganizationSnapshotService $organization)
    {
    }

    /** @return array{status:string,contract_version:string,definition_version:int,checksum:string,member_group_id:?string,warning:?string} */
    public function snapshot(): array
    {
        if ($this->resolved !== null)
        {
            return $this->resolved;
        }

        $organization = $this->organization->snapshot();
        if (($organization['status'] ?? null) !== 'VALID')
        {
            return $this->resolved = $this->unavailable(
                is_string($organization['status'] ?? null) ? $organization['status'] : 'UNAVAILABLE',
                is_string($organization['warning'] ?? null)
                    ? $organization['warning']
                    : 'Die kanonische BR-Mitgliedschaft ist nicht verfügbar.',
                is_string($organization['contract_version'] ?? null) ? $organization['contract_version'] : '',
                is_int($organization['definition_version'] ?? null) ? $organization['definition_version'] : 0,
                is_string($organization['checksum'] ?? null) ? $organization['checksum'] : '',
            );
        }

        $mapping = $organization['identity_groups'][self::IDENTITY_KEY] ?? null;
        $memberGroupId = is_array($mapping) ? ($mapping['groupId'] ?? null) : null;
        if (!$this->isValidGroupId($memberGroupId))
        {
            return $this->resolved = $this->unavailable(
                'INVALID',
                'Die kanonische BR-Mitgliedschaft fehlt oder ist ungültig.',
                (string)($organization['contract_version'] ?? ''),
                (int)($organization['definition_version'] ?? 0),
                (string)($organization['checksum'] ?? ''),
            );
        }

        return $this->resolved = [
            'status' => 'VALID',
            'contract_version' => (string)$organization['contract_version'],
            'definition_version' => (int)$organization['definition_version'],
            'checksum' => (string)$organization['checksum'],
            'member_group_id' => trim($memberGroupId),
            'warning' => null,
        ];
    }

    public function memberGroupId(): ?string
    {
        $snapshot = $this->snapshot();
        return $snapshot['status'] === 'VALID' ? $snapshot['member_group_id'] : null;
    }

    private function isValidGroupId(mixed $groupId): bool
    {
        if (!is_string($groupId))
        {
            return false;
        }
        $groupId = trim($groupId);
        $characters = preg_match_all('/./us', $groupId, $matches);

        return $groupId !== ''
            && $characters !== false
            && $characters <= 64
            && preg_match('/[\x00-\x1F\x7F]/u', $groupId) !== 1;
    }

    /** @return array{status:string,contract_version:string,definition_version:int,checksum:string,member_group_id:null,warning:string} */
    private function unavailable(
        string $status,
        string $warning,
        string $contractVersion = '',
        int $definitionVersion = 0,
        string $checksum = '',
    ): array
    {
        return [
            'status' => $status,
            'contract_version' => $contractVersion,
            'definition_version' => $definitionVersion,
            'checksum' => $checksum,
            'member_group_id' => null,
            'warning' => $warning,
        ];
    }
}
