<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\PublicApi\V1;

use InvalidArgumentException;

/** Datensparsame, unveränderliche V1-Projektion der kanonischen BR-Gruppenidentitäten. */
final class BrRoleSnapshot {
    public const CONTRACT_VERSION = '1.0';

    public function __construct(
        private bool $valid,
        private int $definitionVersion,
        private int $definitionRevision,
        private ?string $memberGroupId,
        private ?string $chairGroupId = null,
        private ?string $deputyGroupId = null,
    ) {
        if ($definitionVersion < 1) {
            throw new InvalidArgumentException('Ungültige BR-Definitionsversion.');
        }
        if (!$valid) {
            $this->definitionRevision = 0;
            $this->memberGroupId = null;
            $this->chairGroupId = null;
            $this->deputyGroupId = null;
            return;
        }
        if ($definitionRevision < 1) {
            throw new InvalidArgumentException('Eine gültige BR-Identität erfordert eine persistierte Definitionsrevision.');
        }
        $memberGroupId = $this->validatedGroupId($memberGroupId);
        if ($chairGroupId === null && $deputyGroupId === null) {
            $this->memberGroupId = $memberGroupId;
            return;
        }
        if ($chairGroupId === null || $deputyGroupId === null) {
            throw new InvalidArgumentException('BR-Vorsitz- und Stellvertreter-Identitäten müssen gemeinsam projiziert werden.');
        }
        $groupIds = [$memberGroupId, $this->validatedGroupId($chairGroupId), $this->validatedGroupId($deputyGroupId)];
        if (count(array_unique($groupIds)) !== count($groupIds)) {
            throw new InvalidArgumentException('BR-Rollengruppen-Identitäten müssen verschieden sein.');
        }
        [$this->memberGroupId, $this->chairGroupId, $this->deputyGroupId] = $groupIds;
    }

    public function contractVersion(): string { return self::CONTRACT_VERSION; }
    public function isValid(): bool { return $this->valid; }
    public function definitionVersion(): int { return $this->definitionVersion; }
    public function definitionRevision(): int { return $this->definitionRevision; }
    public function memberGroupId(): ?string { return $this->memberGroupId; }
    public function chairGroupId(): ?string { return $this->chairGroupId; }
    public function deputyGroupId(): ?string { return $this->deputyGroupId; }

    public function checksum(): string {
        return hash('sha256', json_encode($this->payload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @return array{contractVersion:string,valid:bool,definitionVersion:int,definitionRevision:int,memberGroupId:?string,chairGroupId:?string,deputyGroupId:?string,checksum:string} */
    public function toArray(): array {
        return [...$this->payload(), 'checksum' => $this->checksum()];
    }

    /** @return array{contractVersion:string,valid:bool,definitionVersion:int,definitionRevision:int,memberGroupId:?string,chairGroupId:?string,deputyGroupId:?string} */
    private function payload(): array {
        return [
            'contractVersion' => self::CONTRACT_VERSION,
            'valid' => $this->valid,
            'definitionVersion' => $this->definitionVersion,
            'definitionRevision' => $this->definitionRevision,
            'memberGroupId' => $this->memberGroupId,
            'chairGroupId' => $this->chairGroupId,
            'deputyGroupId' => $this->deputyGroupId,
        ];
    }

    private function validatedGroupId(?string $groupId): string {
        if (!is_string($groupId)) {
            throw new InvalidArgumentException('Ungültige BR-Gruppenidentität.');
        }
        $groupId = trim($groupId);
        $characters = preg_match_all('/./us', $groupId, $matches);
        if ($groupId === ''
            || $characters === false
            || $characters > 64
            || preg_match('/[\x00-\x1F\x7F]/u', $groupId) === 1) {
            throw new InvalidArgumentException('Ungültige BR-Gruppenidentität.');
        }
        return $groupId;
    }
}
