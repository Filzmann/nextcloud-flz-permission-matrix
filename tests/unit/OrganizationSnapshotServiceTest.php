<?php

declare(strict_types=1);

namespace OCP\App {
    interface IAppManager {
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
    use OCA\LocalBase\Organization\AdOrganizationSnapshot;
    use OCP\App\IAppManager;
    use Psr\Log\LoggerInterface;

    final class OrganizationTestApps implements IAppManager {
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
            private array|\Throwable $payload,
            OrganizationTestLogger $logger
        ) {
            parent::__construct(new OrganizationTestApps(), $logger, null);
        }

        protected function isProviderEnabled(): bool {
            return $this->enabled;
        }

        protected function readProviderSnapshot(): array {
            if ($this->payload instanceof \Throwable) {
                throw $this->payload;
            }

            return $this->payload;
        }
    }

    $validPayload = (new AdOrganizationSnapshot(true, 4, [
        'eb' => ['groupId' => 'team-eb', 'label' => 'Einsatzbegleitung'],
    ], [
        'north' => ['groupId' => 'area-north', 'label' => 'Nord'],
    ]))->toArray();
    $logger = new OrganizationTestLogger();
    $valid = (new OrganizationTestService(true, $validPayload, $logger))->snapshot();

    assertSameValue('VALID', $valid['status'], 'A matching, valid provider snapshot must be consumable.');
    assertSameValue(1, $valid['contract_version'], 'The public organization contract version must be recorded.');
    assertSameValue(4, $valid['definition_version'], 'The canonical definition version must be recorded.');
    assertSameValue($validPayload['checksum'], $valid['checksum'], 'The provider checksum must be preserved.');
    assertSameValue('team-eb', $valid['roles']['eb']['groupId'], 'Semantic role mappings must come from the provider.');

    $missing = (new OrganizationTestService(false, $validPayload, new OrganizationTestLogger()))->snapshot();
    assertSameValue('MISSING', $missing['status'], 'A disabled provider must have a controlled missing state.');
    assertSameValue([], $missing['roles'], 'A missing provider must not contribute role meaning.');

    $invalidPayload = (new AdOrganizationSnapshot(false, 4, [], []))->toArray();
    $invalid = (new OrganizationTestService(true, $invalidPayload, new OrganizationTestLogger()))->snapshot();
    assertSameValue('INVALID', $invalid['status'], 'An invalid provider snapshot must remain non-authoritative.');
    assertSameValue([], $invalid['areas'], 'An invalid provider must not contribute area meaning.');

    $incompatiblePayload = $validPayload;
    $incompatiblePayload['version'] = 2;
    $incompatible = (new OrganizationTestService(true, $incompatiblePayload, new OrganizationTestLogger()))->snapshot();
    assertSameValue('INCOMPATIBLE', $incompatible['status'], 'Unknown contract versions must fail closed.');
    assertSameValue([], $incompatible['roles'], 'An incompatible contract must not contribute role meaning.');

    $collidingPayload = (new AdOrganizationSnapshot(true, 4, [
        'eb' => ['groupId' => 'shared-group', 'label' => 'Einsatzbegleitung'],
    ], [
        'north' => ['groupId' => 'shared-group', 'label' => 'Nord'],
    ]))->toArray();
    $colliding = (new OrganizationTestService(true, $collidingPayload, new OrganizationTestLogger()))->snapshot();
    assertSameValue('INCOMPATIBLE', $colliding['status'], 'Ambiguous semantic group mappings must fail closed.');
    assertSameValue([], $colliding['roles'], 'Ambiguous mappings must not contribute role meaning.');

    $malformedPayload = $validPayload;
    $malformedPayload['roles']['eb']['label'] = ['synthetic-invalid-label'];
    $malformedPayload['checksum'] = hash('sha256', json_encode([
        'version' => $malformedPayload['version'],
        'valid' => $malformedPayload['valid'],
        'definitionVersion' => $malformedPayload['definitionVersion'],
        'roles' => $malformedPayload['roles'],
        'areas' => $malformedPayload['areas'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $malformed = (new OrganizationTestService(true, $malformedPayload, new OrganizationTestLogger()))->snapshot();
    assertSameValue('INCOMPATIBLE', $malformed['status'], 'Malformed mapping fields must fail closed even with a matching checksum.');

    $wrongScalarTypes = $validPayload;
    $wrongScalarTypes['version'] = '1';
    $wrongTypes = (new OrganizationTestService(true, $wrongScalarTypes, new OrganizationTestLogger()))->snapshot();
    assertSameValue('INCOMPATIBLE', $wrongTypes['status'], 'Scalar contract fields must keep their declared types.');

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
    assertSameValue(1, count($failedLogger->warnings), 'Provider failures must be diagnosable without exposing payload data.');

    echo 'OrganizationSnapshotService tests passed' . PHP_EOL;
}
