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
    use OCA\FilzmannPermissionMatrix\Service\OrganizationSnapshotService;
    use OCA\LocalBase\PublicApi\V1\OrganizationSnapshot;
    use OCA\LocalBase\PublicApi\V1\OrganizationSnapshotService as LocalBaseOrganizationSnapshotService;
    use OCP\App\IAppManager;
    use OCP\Server;
    use Psr\Log\LoggerInterface;

    final class OrganizationTestApps implements IAppManager {
    }

    final class OrganizationEnabledApps implements IAppManager {
        public function getEnabledApps(): array { return ['localbase']; }
    }

    final class OrganizationTestLogger implements LoggerInterface {
        public array $warnings = [];
        public function emergency($message, array $context = []): void {}
        public function alert($message, array $context = []): void {}
        public function critical($message, array $context = []): void {}
        public function error($message, array $context = []): void {}
        public function warning($message, array $context = []): void { $this->warnings[] = $message; }
        public function notice($message, array $context = []): void {}
        public function info($message, array $context = []): void {}
        public function debug($message, array $context = []): void {}
        public function log($level, $message, array $context = []): void {}
    }

    final class OrganizationTestService extends OrganizationSnapshotService {
        public function __construct(
            private bool $enabled,
            private OrganizationSnapshot|\Throwable|null $providerSnapshot,
            OrganizationTestLogger $logger
        ) {
            parent::__construct(new OrganizationTestApps(), $logger);
        }

        protected function isProviderEnabled(): bool {
            return $this->enabled;
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

    $validSnapshot = new OrganizationSnapshot(true, 4, [
        'eb' => ['groupId' => 'team-eb', 'label' => 'Einsatzbegleitung'],
    ], [
        'north' => ['groupId' => 'area-north', 'label' => 'Nord'],
    ]);
    $logger = new OrganizationTestLogger();
    Server::$service = new LocalBaseOrganizationSnapshotService($validSnapshot);
    $resolved = (new OrganizationSnapshotService(new OrganizationEnabledApps(), $logger))->snapshot();
    assertSameValue('VALID', $resolved['status'], 'The enabled public V1 service must be resolved lazily through the Nextcloud container.');

    $valid = (new OrganizationTestService(true, $validSnapshot, $logger))->snapshot();

    assertSameValue('VALID', $valid['status'], 'A matching, valid provider snapshot must be consumable.');
    assertSameValue('1.0', $valid['contract_version'], 'The public organization contract version must be recorded.');
    assertSameValue(4, $valid['definition_version'], 'The canonical definition version must be recorded.');
    assertSameValue($validSnapshot->checksum(), $valid['checksum'], 'The provider checksum must be preserved.');
    assertSameValue('team-eb', $valid['roles']['eb']['groupId'], 'Semantic role mappings must come from the provider.');

    $missing = (new OrganizationTestService(false, $validSnapshot, new OrganizationTestLogger()))->snapshot();
    assertSameValue('MISSING', $missing['status'], 'A disabled provider must have a controlled missing state.');
    assertSameValue([], $missing['roles'], 'A missing provider must not contribute role meaning.');

    $invalidSnapshot = new OrganizationSnapshot(false, 4, [], []);
    $invalid = (new OrganizationTestService(true, $invalidSnapshot, new OrganizationTestLogger()))->snapshot();
    assertSameValue('INVALID', $invalid['status'], 'An invalid provider snapshot must remain non-authoritative.');
    assertSameValue([], $invalid['areas'], 'An invalid provider must not contribute area meaning.');

    $incompatible = (new OrganizationTestService(true, null, new OrganizationTestLogger()))->snapshot();
    assertSameValue('INCOMPATIBLE', $incompatible['status'], 'An unavailable V1 service in an enabled provider must fail closed.');
    assertSameValue([], $incompatible['roles'], 'An incompatible contract must not contribute role meaning.');

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

    echo 'OrganizationSnapshotService tests passed' . PHP_EOL;
}
