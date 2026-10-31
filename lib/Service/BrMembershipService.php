<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Service;

use InvalidArgumentException;
use OCA\LocalBase\PublicApi\V1\BrMembershipSnapshotService as LocalBaseBrMembershipSnapshotService;
use OCP\App\IAppManager;
use OCP\Server;
use Psr\Log\LoggerInterface;
use Throwable;
use UnexpectedValueException;

/**
 * Löst ausschließlich die kanonische BR-Mitgliedergruppe aus dem öffentlichen LocalBase-V1-Vertrag auf.
 *
 * Fehlende, deaktivierte, inkompatible oder ungültige Providerstände liefern niemals eine
 * Gruppen-ID. AccessService kann dadurch die unverzichtbare BR-Lesefreigabe fail-closed anwenden.
 */
class BrMembershipService {
    private const PROVIDER_APP_ID = 'localbase';
    private const SUPPORTED_CONTRACT_VERSION = '1.0';
    private const SUPPORTED_DEFINITION_VERSION = 1;

    /** @var array<string,mixed>|null */
    private ?array $resolved = null;

    public function __construct(
        private IAppManager $apps,
        private LoggerInterface $logger,
    ) {
    }

    /** @return array{status:string,contract_version:string,definition_version:int,definition_revision:int,checksum:string,member_group_id:?string,warning:?string} */
    public function snapshot(): array {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        if (!$this->isProviderEnabled()) {
            return $this->resolved = $this->unavailable(
                'MISSING',
                'Die kanonische BR-Mitgliedschaft ist nicht verfügbar.',
            );
        }

        try {
            $payload = $this->readProviderSnapshot();
        } catch (InvalidArgumentException|UnexpectedValueException) {
            return $this->resolved = $this->unavailable(
                'INCOMPATIBLE',
                'Der öffentliche LocalBase-BR-Vertrag ist inkompatibel.',
            );
        } catch (Throwable $e) {
            $this->logger->warning('Permission matrix BR membership snapshot unavailable', [
                'app' => 'flz_permission_matrix',
                'provider' => self::PROVIDER_APP_ID,
                'exception' => $e,
            ]);

            return $this->resolved = $this->unavailable(
                'UNAVAILABLE',
                'Die kanonische BR-Mitgliedschaft ist vorübergehend nicht verfügbar.',
            );
        }

        try {
            foreach (['contractVersion', 'definitionVersion', 'definitionRevision', 'checksum', 'isValid', 'memberGroupId'] as $method) {
                if (!method_exists($payload, $method)) {
                    throw new UnexpectedValueException('Incomplete BR membership contract.');
                }
            }
            $contractVersion = $payload->contractVersion();
            $definitionVersion = $payload->definitionVersion();
            $definitionRevision = $payload->definitionRevision();
            $checksum = $payload->checksum();
            $valid = $payload->isValid();
            $memberGroupId = $payload->memberGroupId();
        } catch (Throwable) {
            return $this->resolved = $this->unavailable(
                'INCOMPATIBLE',
                'Der öffentliche LocalBase-BR-Vertrag ist inkompatibel.',
            );
        }

        if ($contractVersion !== self::SUPPORTED_CONTRACT_VERSION
            || $definitionVersion !== self::SUPPORTED_DEFINITION_VERSION) {
            return $this->resolved = $this->unavailable(
                'INCOMPATIBLE',
                'Der öffentliche LocalBase-BR-Vertrag besitzt eine nicht unterstützte Version.',
                is_string($contractVersion) ? $contractVersion : '',
                is_int($definitionVersion) ? $definitionVersion : 0,
                is_int($definitionRevision) ? $definitionRevision : 0,
                is_string($checksum) ? $checksum : '',
            );
        }

        if ($valid !== true
            || !is_int($definitionRevision)
            || $definitionRevision < 1
            || !$this->isValidGroupId($memberGroupId)) {
            return $this->resolved = $this->unavailable(
                'INVALID',
                'Die kanonische BR-Mitgliedschaft ist ungültig.',
                $contractVersion,
                $definitionVersion,
                is_int($definitionRevision) ? $definitionRevision : 0,
                is_string($checksum) ? $checksum : '',
            );
        }

        return $this->resolved = [
            'status' => 'VALID',
            'contract_version' => $contractVersion,
            'definition_version' => $definitionVersion,
            'definition_revision' => $definitionRevision,
            'checksum' => is_string($checksum) ? $checksum : '',
            'member_group_id' => trim($memberGroupId),
            'warning' => null,
        ];
    }

    public function memberGroupId(): ?string {
        $snapshot = $this->snapshot();

        return $snapshot['status'] === 'VALID' ? $snapshot['member_group_id'] : null;
    }

    protected function isProviderEnabled(): bool {
        return in_array(self::PROVIDER_APP_ID, $this->apps->getEnabledApps(), true);
    }

    protected function readProviderSnapshot(): object {
        if (!class_exists(LocalBaseBrMembershipSnapshotService::class)) {
            throw new UnexpectedValueException('LocalBase BR membership service is missing.');
        }

        $provider = Server::get(LocalBaseBrMembershipSnapshotService::class);
        if (!$provider instanceof LocalBaseBrMembershipSnapshotService) {
            throw new UnexpectedValueException('LocalBase BR membership service is not resolvable.');
        }

        return $provider->snapshot();
    }

    private function isValidGroupId(mixed $groupId): bool {
        if (!is_string($groupId)) {
            return false;
        }
        $groupId = trim($groupId);
        $characters = preg_match_all('/./us', $groupId, $matches);

        return $groupId !== ''
            && $characters !== false
            && $characters <= 64
            && preg_match('/[\x00-\x1F\x7F]/u', $groupId) !== 1;
    }

    /** @return array{status:string,contract_version:string,definition_version:int,definition_revision:int,checksum:string,member_group_id:null,warning:string} */
    private function unavailable(
        string $status,
        string $warning,
        string $contractVersion = '',
        int $definitionVersion = 0,
        int $definitionRevision = 0,
        string $checksum = '',
    ): array {
        return [
            'status' => $status,
            'contract_version' => $contractVersion,
            'definition_version' => $definitionVersion,
            'definition_revision' => $definitionRevision,
            'checksum' => $checksum,
            'member_group_id' => null,
            'warning' => $warning,
        ];
    }
}
