<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Service;

use InvalidArgumentException;
use OCA\FlzOrganization\PublicApi\V1\Contract as OrganizationContract;
use OCA\FlzOrganization\PublicApi\V1\OrganizationSnapshotService as FlzOrganizationSnapshotService;
use OCP\App\IAppManager;
use OCP\Server;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

/**
 * Konsumiert den öffentlichen, datensparsamen flz_organization-Snapshot fail-closed.
 *
 * Der Provider wird erst nach Aktivierungs- und Klassenprüfung über den öffentlichen
 * Nextcloud-Container aufgelöst. Dadurch bleibt auch eine aktivierte inkompatible Provider-Version
 * ohne V1-Service ein kontrollierter, nicht berechtigender Zustand.
 */
class OrganizationSnapshotService
{
    private const PROVIDER_APP_ID = 'flz_organization';
    private const SUPPORTED_CONTRACT_VERSION = '1.0';

    public function __construct(
        private IAppManager $apps,
        private LoggerInterface $logger
    )
    {
    }

    public function snapshot(): array
    {
        if (!$this->isProviderEnabled())
        {
            return $this->unavailable(
                'MISSING',
                'Der Organisationsprovider fehlt; Gruppenbedeutungen bleiben UNKNOWN.'
            );
        }

        if (!$this->isProviderCompatible())
        {
            return $this->unavailable(
                'INCOMPATIBLE',
                'Der Organisationsprovider besitzt keinen kompatiblen öffentlichen V1-Vertrag; Gruppenbedeutungen bleiben UNKNOWN.'
            );
        }

        try
        {
            $payload = $this->readProviderSnapshot();
        } catch (InvalidArgumentException|UnexpectedValueException)
        {
            return $this->unavailable(
                'INCOMPATIBLE',
                'Die Organisationsschnittstelle ist inkompatibel; Gruppenbedeutungen bleiben UNKNOWN.'
            );
        } catch (\Throwable $e)
        {
            $this->logger->warning('Permission matrix organization snapshot unavailable', [
                'app' => 'flz_permission_matrix',
                'provider' => self::PROVIDER_APP_ID,
                'exception_class' => $e::class,
            ]);

            return $this->unavailable(
                'UNAVAILABLE',
                'Der Organisationssnapshot ist nicht verfügbar; Gruppenbedeutungen bleiben UNKNOWN.'
            );
        }

        try
        {
            foreach (['contractVersion', 'definitionVersion', 'checksum', 'isValid', 'roles', 'areas', 'identityGroups'] as $method)
            {
                if (!method_exists($payload, $method))
                {
                    throw new UnexpectedValueException('Incomplete organization snapshot contract.');
                }
            }
            $contractVersion = $payload->contractVersion();
            $definitionVersion = $payload->definitionVersion();
            $checksum = $payload->checksum();
            $valid = $payload->isValid();
            $roles = $payload->roles();
            $areas = $payload->areas();
            $identityGroups = $payload->identityGroups();
            if (!is_string($contractVersion)
                || !is_int($definitionVersion)
                || !is_string($checksum)
                || !is_bool($valid)
                || !is_array($roles)
                || !is_array($areas)
                || !is_array($identityGroups))
            {
                throw new UnexpectedValueException('Malformed organization snapshot contract.');
            }
        } catch (\Throwable)
        {
            return $this->unavailable(
                'INCOMPATIBLE',
                'Die Organisationsschnittstelle ist inkompatibel; Gruppenbedeutungen bleiben UNKNOWN.'
            );
        }

        if ($contractVersion !== self::SUPPORTED_CONTRACT_VERSION)
        {
            return $this->unavailable(
                'INCOMPATIBLE',
                'Der Organisationsvertrag ist inkompatibel; Gruppenbedeutungen bleiben UNKNOWN.',
                $contractVersion,
                $definitionVersion,
                $checksum
            );
        }

        if (!$valid)
        {
            return $this->unavailable(
                'INVALID',
                'Der Organisationssnapshot ist ungültig; Gruppenbedeutungen bleiben UNKNOWN.',
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
            'roles' => $roles,
            'areas' => $areas,
            'identity_groups' => $identityGroups,
            'warning' => null,
        ];
    }

    protected function isProviderEnabled(): bool
    {
        return in_array(self::PROVIDER_APP_ID, $this->apps->getEnabledApps(), true);
    }

    protected function isProviderCompatible(): bool
    {
        try
        {
            return class_exists(OrganizationContract::class)
                && method_exists(OrganizationContract::class, 'supports')
                && OrganizationContract::supports(self::SUPPORTED_CONTRACT_VERSION);
        } catch (\Throwable)
        {
            return false;
        }
    }

    protected function readProviderSnapshot(): object
    {
        if (!class_exists(FlzOrganizationSnapshotService::class))
        {
            throw new UnexpectedValueException(
                'Die Organisationsschnittstelle fehlt; Gruppenbedeutungen bleiben UNKNOWN.'
            );
        }

        $provider = Server::get(FlzOrganizationSnapshotService::class);
        if (!$provider instanceof FlzOrganizationSnapshotService)
        {
            throw new UnexpectedValueException(
                'Die Organisationsschnittstelle ist nicht auflösbar; Gruppenbedeutungen bleiben UNKNOWN.'
            );
        }

        return $provider->snapshot();
    }

    private function unavailable(
        string $status,
        string $warning,
        string $contractVersion = '',
        int $definitionVersion = 0,
        string $checksum = ''
    ): array
    {
        return [
            'status' => $status,
            'contract_version' => $contractVersion,
            'definition_version' => $definitionVersion,
            'checksum' => $checksum,
            'roles' => [],
            'areas' => [],
            'identity_groups' => [],
            'warning' => $warning,
        ];
    }
}
