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
    use OCA\FlzPermissionMatrix\Service\BrMembershipService;
    use OCA\LocalBase\PublicApi\V1\BrMembershipSnapshot;
    use OCA\LocalBase\PublicApi\V1\BrMembershipSnapshotService as LocalBaseBrMembershipSnapshotService;
    use OCP\App\IAppManager;
    use OCP\Server;
    use Psr\Log\LoggerInterface;

    final class BrMembershipTestApps implements IAppManager {
    }

    final class BrMembershipEnabledApps implements IAppManager {
        public function getEnabledApps(): array { return ['localbase']; }
    }

    final class BrMembershipTestLogger implements LoggerInterface {
        public array $warnings = [];
        public function emergency($message, array $context = []): void {}
        public function alert($message, array $context = []): void {}
        public function critical($message, array $context = []): void {}
        public function error($message, array $context = []): void {}
        public function warning($message, array $context = []): void { $this->warnings[] = [$message, $context]; }
        public function notice($message, array $context = []): void {}
        public function info($message, array $context = []): void {}
        public function debug($message, array $context = []): void {}
        public function log($level, $message, array $context = []): void {}
    }

    final class BrMembershipTestService extends BrMembershipService {
        public function __construct(
            private bool $enabled,
            private mixed $providerSnapshot,
            BrMembershipTestLogger $logger,
        ) {
            parent::__construct(new BrMembershipTestApps(), $logger);
        }

        protected function isProviderEnabled(): bool {
            return $this->enabled;
        }

        protected function readProviderSnapshot(): object {
            if ($this->providerSnapshot instanceof \Throwable) {
                throw $this->providerSnapshot;
            }
            if ($this->providerSnapshot === null) {
                throw new UnexpectedValueException('synthetic incompatible provider');
            }
            return $this->providerSnapshot;
        }
    }

    $validSnapshot = new BrMembershipSnapshot(true, 1, 4, 'BR-Kanonisch');
    $logger = new BrMembershipTestLogger();
    Server::$service = new LocalBaseBrMembershipSnapshotService($validSnapshot);
    $resolved = (new BrMembershipService(new BrMembershipEnabledApps(), $logger))->snapshot();
    assertSameValue('VALID', $resolved['status'], 'The enabled public V1 BR service must be resolved lazily through the Nextcloud container.');
    assertSameValue('BR-Kanonisch', $resolved['member_group_id'], 'The canonical BR member group must come from LocalBase.');

    $valid = (new BrMembershipTestService(true, $validSnapshot, $logger))->snapshot();
    assertSameValue('VALID', $valid['status'], 'A matching and valid provider snapshot must authorize the canonical BR identity.');
    assertSameValue('1.0', $valid['contract_version'], 'The public BR contract version must be checked.');
    assertSameValue(1, $valid['definition_version'], 'The supported BR definition version must be checked.');
    assertSameValue(4, $valid['definition_revision'], 'The persisted BR definition revision must be recorded.');

    $missing = (new BrMembershipTestService(false, $validSnapshot, new BrMembershipTestLogger()))->snapshot();
    assertSameValue('MISSING', $missing['status'], 'A disabled LocalBase provider must fail closed.');
    assertSameValue(null, $missing['member_group_id'], 'A disabled provider must not contribute BR identity.');

    $incompatible = new class {
        public function contractVersion(): string { return '2.0'; }
        public function isValid(): bool { return true; }
        public function definitionVersion(): int { return 1; }
        public function definitionRevision(): int { return 1; }
        public function memberGroupId(): ?string { return 'BR-Kanonisch'; }
        public function checksum(): string { return 'incompatible'; }
    };
    $incompatibleResult = (new BrMembershipTestService(true, $incompatible, new BrMembershipTestLogger()))->snapshot();
    assertSameValue('INCOMPATIBLE', $incompatibleResult['status'], 'An incompatible public contract version must fail closed.');
    assertSameValue(null, $incompatibleResult['member_group_id'], 'An incompatible provider must not authorize BR access.');

    $invalid = (new BrMembershipTestService(
        true,
        new BrMembershipSnapshot(false, 1, 0, null),
        new BrMembershipTestLogger(),
    ))->snapshot();
    assertSameValue('INVALID', $invalid['status'], 'An invalid BR definition must fail closed.');

    $failedLogger = new BrMembershipTestLogger();
    $failed = (new BrMembershipTestService(true, new RuntimeException('private provider detail'), $failedLogger))->snapshot();
    assertSameValue('UNAVAILABLE', $failed['status'], 'Unexpected provider failures need a stable fail-closed state.');
    assertSameValue(null, $failed['member_group_id'], 'A failed provider must not authorize BR access.');
    assertSameValue(1, count($failedLogger->warnings), 'Unexpected provider failures must be diagnosable.');
    assertSameValue(false, str_contains((string)$failed['warning'], 'private provider detail'), 'Private provider failure details must not reach users.');

    echo 'BrMembershipService tests passed' . PHP_EOL;
}
