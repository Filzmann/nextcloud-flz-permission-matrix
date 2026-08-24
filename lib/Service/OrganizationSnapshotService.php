<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Service;

use OCA\LocalBase\Organization\AdOrganizationSnapshotService;
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
    private const SUPPORTED_CONTRACT_VERSION = 1;

    public function __construct(
        private IAppManager $apps,
        private LoggerInterface $logger,
        private ?AdOrganizationSnapshotService $provider
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
        } catch (UnexpectedValueException) {
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

        if (!is_int($payload['version'] ?? null)
            || !is_bool($payload['valid'] ?? null)
            || !is_int($payload['definitionVersion'] ?? null)
            || !is_string($payload['checksum'] ?? null)) {
            return $this->unavailable(
                'INCOMPATIBLE',
                'LocalBase-Organisationsvertrag enthält ungültige Feldtypen; Gruppenbedeutungen bleiben UNKNOWN.'
            );
        }

        $contractVersion = $payload['version'];
        $definitionVersion = $payload['definitionVersion'];
        $checksum = $payload['checksum'];
        if ($contractVersion !== self::SUPPORTED_CONTRACT_VERSION) {
            return $this->unavailable(
                'INCOMPATIBLE',
                'LocalBase-Organisationsvertrag ist inkompatibel; Gruppenbedeutungen bleiben UNKNOWN.',
                $contractVersion,
                $definitionVersion,
                $checksum
            );
        }

        if (!$this->checksumMatches($payload, $checksum)) {
            return $this->unavailable(
                'INCOMPATIBLE',
                'LocalBase-Organisationssnapshot hat eine ungültige Prüfsumme; Gruppenbedeutungen bleiben UNKNOWN.',
                $contractVersion,
                $definitionVersion,
                $checksum
            );
        }

        if (($payload['valid'] ?? false) !== true) {
            return $this->unavailable(
                'INVALID',
                'LocalBase-Organisationssnapshot ist ungültig; Gruppenbedeutungen bleiben UNKNOWN.',
                $contractVersion,
                $definitionVersion,
                $checksum
            );
        }

        try {
            $roles = $this->normalizeMappings($payload['roles'] ?? null);
            $areas = $this->normalizeMappings($payload['areas'] ?? null);
            $groupIds = [
                ...array_column($roles, 'groupId'),
                ...array_column($areas, 'groupId'),
            ];
            if (count($groupIds) !== count(array_unique($groupIds))) {
                throw new UnexpectedValueException('Organisationsgruppen sind nicht eindeutig.');
            }
        } catch (UnexpectedValueException) {
            return $this->unavailable(
                'INCOMPATIBLE',
                'LocalBase-Organisationssnapshot ist unvollständig; Gruppenbedeutungen bleiben UNKNOWN.',
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
            'warning' => null,
        ];
    }

    protected function isProviderEnabled(): bool {
        return in_array(self::PROVIDER_APP_ID, $this->apps->getEnabledApps(), true);
    }

    protected function readProviderSnapshot(): array {
        if ($this->provider === null) {
            throw new UnexpectedValueException(
                'LocalBase-Organisationsschnittstelle fehlt; Gruppenbedeutungen bleiben UNKNOWN.'
            );
        }

        return $this->provider->snapshot()->toArray();
    }

    private function checksumMatches(array $payload, string $checksum): bool {
        if (preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1) {
            return false;
        }

        try {
            $expected = hash('sha256', json_encode([
                'version' => (int)($payload['version'] ?? 0),
                'valid' => (bool)($payload['valid'] ?? false),
                'definitionVersion' => (int)($payload['definitionVersion'] ?? 0),
                'roles' => is_array($payload['roles'] ?? null) ? $payload['roles'] : [],
                'areas' => is_array($payload['areas'] ?? null) ? $payload['areas'] : [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            return false;
        }

        return hash_equals($expected, $checksum);
    }

    private function normalizeMappings(mixed $mappings): array {
        if (!is_array($mappings)) {
            throw new UnexpectedValueException('Organisationszuordnungen fehlen.');
        }

        $normalized = [];
        foreach ($mappings as $key => $mapping) {
            if (!is_string($key) || $key === '' || !is_array($mapping)) {
                throw new UnexpectedValueException('Organisationszuordnung ist ungültig.');
            }
            if (!is_string($mapping['groupId'] ?? null) || !is_string($mapping['label'] ?? null)) {
                throw new UnexpectedValueException('Organisationszuordnung hat ungültige Felder.');
            }
            $groupId = trim($mapping['groupId']);
            $label = trim($mapping['label']);
            if ($groupId === '' || $label === '') {
                throw new UnexpectedValueException('Organisationszuordnung ist unvollständig.');
            }
            $normalized[$key] = ['groupId' => $groupId, 'label' => $label];
        }

        return $normalized;
    }

    private function unavailable(
        string $status,
        string $warning,
        int $contractVersion = 0,
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
