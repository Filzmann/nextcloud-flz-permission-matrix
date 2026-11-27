<?php

declare(strict_types=1);

namespace OCP\App {
    interface IAppManager {
    }
}

namespace OCP {
    final class Server {
        public static mixed $service = null;
        public static function get(string $name): mixed { return self::$service; }
    }
}

namespace Psr\Log {
    interface LoggerInterface {
        public function emergency($message, array $context = []): void;
        public function alert($message, array $context = []): void;
        public function critical($message, array $context = []): void;
        public function error($message, array $context = []): void;
        public function warning($message, array $context = []): void;
        public function notice($message, array $context = []): void;
        public function info($message, array $context = []): void;
        public function debug($message, array $context = []): void;
        public function log($level, $message, array $context = []): void;
    }
}

namespace {
    use OCA\FlzPermissionMatrix\Service\OrganizationSnapshotService;
    use OCA\FlzOrganization\PublicApi\V1\OrganizationSnapshot;
    use OCA\FlzOrganization\PublicApi\V1\OrganizationSnapshotService as FlzOrganizationSnapshotService;
    use OCP\App\IAppManager;
    use OCP\Server;
    use Psr\Log\LoggerInterface;

    final class OrganizationTestApps implements IAppManager {
    }

    final class OrganizationEnabledApps implements IAppManager {
        public function getEnabledApps(): array { return ['flz_organization']; }
    }

    final class OrganizationTestLogger implements LoggerInterface {
        public array $warnings = [];
        public array $contexts = [];
        public function emergency($message, array $context = []): void {}
        public function alert($message, array $context = []): void {}
        public function critical($message, array $context = []): void {}
        public function error($message, array $context = []): void {}
        public function warning($message, array $context = []): void
        {
            $this->warnings[] = $message;
            $this->contexts[] = $context;
        }
        public function notice($message, array $context = []): void {}
        public function info($message, array $context = []): void {}
        public function debug($message, array $context = []): void {}
        public function log($level, $message, array $context = []): void {}
    }

    final class OrganizationTestService extends OrganizationSnapshotService {
        public function __construct(
            private bool $enabled,
            private OrganizationSnapshot|\Throwable|null $providerSnapshot,
            OrganizationTestLogger $logger,
            private bool $compatible = true,
        ) {
            parent::__construct(new OrganizationTestApps(), $logger);
        }

        protected function isProviderEnabled(): bool {
            return $this->enabled;
        }

        protected function isProviderCompatible(): bool {
            return $this->compatible;
        }

        protected function readProviderSnapshot(): OrganizationSnapshot {
            if ($this->providerSnapshot instanceof \Throwable) {
                throw $this->providerSnapshot;
            }
            if ($this->providerSnapshot === null) {
                throw new UnexpectedValueException('synthetic incompatible provider');
            }
            return $this->providerSnapshot;
        }
    }

    $validSnapshot = new OrganizationSnapshot(true, 5, [
        'eb' => ['groupId' => 'team-eb', 'label' => 'Einsatzbegleitung'],
    ], [
        'north' => ['groupId' => 'area-north', 'label' => 'Nord'],
    ], [
        'works_council_member' => ['groupId' => 'BR-Kanonisch', 'label' => 'Betriebsratsmitglieder'],
    ]);
    $logger = new OrganizationTestLogger();
    Server::$service = new FlzOrganizationSnapshotService($validSnapshot);
    $resolved = (new OrganizationSnapshotService(new OrganizationEnabledApps(), $logger))->snapshot();
    assertSameValue('VALID', $resolved['status'], 'The enabled public V1 service must be resolved lazily through the Nextcloud container.');

    $valid = (new OrganizationTestService(true, $validSnapshot, $logger))->snapshot();

    assertSameValue('VALID', $valid['status'], 'A matching, valid provider snapshot must be consumable.');
    assertSameValue('1.0', $valid['contract_version'], 'The public organization contract version must be recorded.');
    assertSameValue(5, $valid['definition_version'], 'The canonical definition version must be recorded.');
    assertSameValue($validSnapshot->checksum(), $valid['checksum'], 'The provider checksum must be preserved.');
    assertSameValue('team-eb', $valid['roles']['eb']['groupId'], 'Semantic role mappings must come from the provider.');
    assertSameValue('BR-Kanonisch', $valid['identity_groups']['works_council_member']['groupId'], 'Canonical identity groups must come from the same provider snapshot.');

    $missing = (new OrganizationTestService(false, $validSnapshot, new OrganizationTestLogger()))->snapshot();
    assertSameValue('MISSING', $missing['status'], 'A disabled provider must have a controlled missing state.');
    assertSameValue([], $missing['roles'], 'A missing provider must not contribute role meaning.');

    $invalidSnapshot = new OrganizationSnapshot(false, 5, [], []);
    $invalid = (new OrganizationTestService(true, $invalidSnapshot, new OrganizationTestLogger()))->snapshot();
    assertSameValue('INVALID', $invalid['status'], 'An invalid provider snapshot must remain non-authoritative.');
    assertSameValue([], $invalid['areas'], 'An invalid provider must not contribute area meaning.');

    $incompatible = (new OrganizationTestService(true, null, new OrganizationTestLogger()))->snapshot();
    assertSameValue('INCOMPATIBLE', $incompatible['status'], 'An unavailable V1 service in an enabled provider must fail closed.');
    assertSameValue([], $incompatible['roles'], 'An incompatible contract must not contribute role meaning.');

    $incompatibleHandshake = (new OrganizationTestService(true, $validSnapshot, new OrganizationTestLogger(), false))->snapshot();
    assertSameValue('INCOMPATIBLE', $incompatibleHandshake['status'], 'A provider without the supported V1 handshake must fail closed before service resolution.');
    assertSameValue([], $incompatibleHandshake['identity_groups'], 'An incompatible handshake must not expose identity groups.');

    $legacyV1Snapshot = new class {
        public function contractVersion(): string { return '1.0'; }
        public function definitionVersion(): int { return 4; }
        public function checksum(): string { return 'legacy'; }
        public function isValid(): bool { return true; }
        public function roles(): array { return []; }
        public function areas(): array { return []; }
    };
    Server::$service = new FlzOrganizationSnapshotService($legacyV1Snapshot);
    $legacyV1 = (new OrganizationSnapshotService(new OrganizationEnabledApps(), new OrganizationTestLogger()))->snapshot();
    assertSameValue('INCOMPATIBLE', $legacyV1['status'], 'An older V1 snapshot without identity groups must fail closed rather than causing a fatal error.');
    assertSameValue([], $legacyV1['identity_groups'], 'An incomplete V1 snapshot must not expose identities.');

    $incompatibleError = (new OrganizationTestService(
        true,
        new UnexpectedValueException('synthetic provider internals'),
        new OrganizationTestLogger()
    ))->snapshot();
    assertSameValue('INCOMPATIBLE', $incompatibleError['status'], 'Provider contract errors need a stable incompatible state.');
    assertSameValue(
        false,
        str_contains($incompatibleError['warning'], 'synthetic provider internals'),
        'Provider exception details must not be exposed in snapshot warnings.'
    );

    $failedLogger = new OrganizationTestLogger();
    $failed = (new OrganizationTestService(true, new RuntimeException('synthetic provider failure'), $failedLogger))->snapshot();
    assertSameValue('UNAVAILABLE', $failed['status'], 'Provider failures need a stable, controlled state.');
    assertSameValue([], $failed['roles'], 'A failed provider must not contribute role meaning.');
    assertSameValue([], $failed['areas'], 'A failed provider must not contribute area meaning.');
    assertSameValue(1, count($failedLogger->warnings), 'Provider failures must be diagnosable without exposing payload data.');
    assertSameValue(
        RuntimeException::class,
        $failedLogger->contexts[0]['exception_class'] ?? null,
        'Provider diagnostics must retain only the technical exception class.'
    );
    assertSameValue(
        false,
        isset($failedLogger->contexts[0]['exception']),
        'Provider exceptions must not pass their payload or trace to the logger.'
    );

    echo 'OrganizationSnapshotService tests passed' . PHP_EOL;
}
