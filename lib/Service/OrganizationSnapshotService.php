<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Service;

use InvalidArgumentException;
use OCA\LocalBase\PublicApi\V1\OrganizationSnapshot;
use OCA\LocalBase\PublicApi\V1\OrganizationSnapshotService as LocalBaseOrganizationSnapshotService;
use OCP\App\IAppManager;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

/**
 * Konsumiert den öffentlichen, datensparsamen LocalBase-Organisationssnapshot fail-closed.
 *
 * Der nullable Provider ist ab Nextcloud 28 ein unterstützter DI-Vertrag: Eine fehlende oder
 * nicht auflösbare optionale Runtime-App führt damit zu einem kontrollierten Matrixstatus.
 */
class OrganizationSnapshotService {
    private const PROVIDER_APP_ID = 'localbase';
    private const SUPPORTED_CONTRACT_VERSION = '1.0';

    public function __construct(
        private IAppManager $apps,
        private LoggerInterface $logger,
        private ?LocalBaseOrganizationSnapshotService $provider
    ) {
    }

    public function snapshot(): array {
        if (!$this->isProviderEnabled()) {
            return $this->unavailable(
                'MISSING',
                'LocalBase-Organisationssnapshot fehlt; Gruppenbedeutungen bleiben UNKNOWN.'
            );
        }

        try {
            $payload = $this->readProviderSnapshot();
        } catch (InvalidArgumentException|UnexpectedValueException) {
            return $this->unavailable(
                'INCOMPATIBLE',
                'LocalBase-Organisationsschnittstelle ist inkompatibel; Gruppenbedeutungen bleiben UNKNOWN.'
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Permission matrix organization snapshot unavailable', [
                'app' => 'filzmann_permission_matrix',
                'provider' => self::PROVIDER_APP_ID,
                'exception' => $e,
            ]);

            return $this->unavailable(
                'UNAVAILABLE',
                'LocalBase-Organisationssnapshot ist nicht verfügbar; Gruppenbedeutungen bleiben UNKNOWN.'
            );
        }

        $contractVersion = $payload->contractVersion();
        $definitionVersion = $payload->definitionVersion();
        $checksum = $payload->checksum();
        if ($contractVersion !== self::SUPPORTED_CONTRACT_VERSION) {
            return $this->unavailable(
                'INCOMPATIBLE',
                'LocalBase-Organisationsvertrag ist inkompatibel; Gruppenbedeutungen bleiben UNKNOWN.',
                $contractVersion,
                $definitionVersion,
                $checksum
            );
        }

        if (!$payload->isValid()) {
            return $this->unavailable(
                'INVALID',
                'LocalBase-Organisationssnapshot ist ungültig; Gruppenbedeutungen bleiben UNKNOWN.',
                $contractVersion,
                $definitionVersion,
                $checksum
            );
        }

        return [
            'status' => 'VALID',
            'contract_version' => $contractVersion,
            'definition_version' => $definitionVersion,
            'checksum' => $checksum,
            'roles' => $payload->roles(),
            'areas' => $payload->areas(),
            'warning' => null,
        ];
    }

    protected function isProviderEnabled(): bool {
        return in_array(self::PROVIDER_APP_ID, $this->apps->getEnabledApps(), true);
    }

    protected function readProviderSnapshot(): OrganizationSnapshot {
        if ($this->provider === null) {
            throw new UnexpectedValueException(
                'LocalBase-Organisationsschnittstelle fehlt; Gruppenbedeutungen bleiben UNKNOWN.'
            );
        }

        return $this->provider->snapshot();
    }

    private function unavailable(
        string $status,
        string $warning,
        string $contractVersion = '',
        int $definitionVersion = 0,
        string $checksum = ''
    ): array {
        return [
            'status' => $status,
            'contract_version' => $contractVersion,
            'definition_version' => $definitionVersion,
            'checksum' => $checksum,
            'roles' => [],
            'areas' => [],
            'warning' => $warning,
        ];
    }
}
