<?php

declare(strict_types=1);

namespace {
    if (!interface_exists(\Psr\Log\LoggerInterface::class)) {
        eval('namespace Psr\Log; interface LoggerInterface {
            public function emergency($message, array $context = []): void;
            public function alert($message, array $context = []): void;
            public function critical($message, array $context = []): void;
            public function error($message, array $context = []): void;
            public function warning($message, array $context = []): void;
            public function notice($message, array $context = []): void;
            public function info($message, array $context = []): void;
            public function debug($message, array $context = []): void;
            public function log($level, $message, array $context = []): void;
        }');
    }
}

namespace {
    require_once __DIR__ . '/helpers.php';

    use OCA\BrPermissionMatrix\Db\SnapshotMapper;
    use OCA\BrPermissionMatrix\Model\MatrixRow;
    use OCA\BrPermissionMatrix\Service\BaselineService;
    use OCA\BrPermissionMatrix\Service\ConfigService;
    use OCA\BrPermissionMatrix\Service\DiffService;
    use OCA\BrPermissionMatrix\Service\InventoryService;
    use OCA\BrPermissionMatrix\Service\MatrixBuilder;
    use OCA\BrPermissionMatrix\Service\ScannerService;
    use Psr\Log\LoggerInterface;

    class ScannerFakeMatrixBuilder extends MatrixBuilder {
        public function __construct() {
        }

        public function build(): array {
            return [
                'groups' => ['Betriebsrat'],
                'apps' => [['app_id' => 'deck', 'display_name' => 'Deck', 'version' => '1.0', 'source' => 'appstore', 'restricted' => false, 'groups' => []]],
                'rows' => [new MatrixRow('App', 'deck', 'Deck', 'App-Nutzung', 'App-Verfuegbarkeit', 'UNSUPPORTED', 'core-app-config', 'medium', ['Betriebsrat' => 'X'])],
                'warnings' => ['deck: kein Detailadapter vorhanden.'],
                'unsupported_apps' => ['deck'],
                'adapter_status' => [],
            ];
        }
    }

    class ScannerFakeInventory extends InventoryService {
        public function __construct() {
        }

        public function nextcloudVersion(): string {
            return '34.0.0';
        }
    }

    class ScannerFakeConfig extends ConfigService {
        public function __construct() {
        }

        public function metadata(): array {
            return ['redacted' => true, 'include_users' => false, 'include_share_metadata' => false, 'strict_mode' => true];
        }

        public function strictMode(): bool {
            return true;
        }

        public function retention(): int {
            return 50;
        }
    }

    class ScannerFakeBaseline extends BaselineService {
        public function __construct() {
        }

        public function currentBaseline(): ?\OCA\BrPermissionMatrix\Model\Snapshot {
            return null;
        }
    }

    class ScannerFakeSnapshotMapper extends SnapshotMapper {
        public function __construct() {
        }
    }

    class ScannerFakeLogger implements LoggerInterface {
        public function emergency($message, array $context = []): void {}
        public function alert($message, array $context = []): void {}
        public function critical($message, array $context = []): void {}
        public function error($message, array $context = []): void {}
        public function warning($message, array $context = []): void {}
        public function notice($message, array $context = []): void {}
        public function info($message, array $context = []): void {}
        public function debug($message, array $context = []): void {}
        public function log($level, $message, array $context = []): void {}
    }

    $scanner = new ScannerService(
        new ScannerFakeMatrixBuilder(),
        new ScannerFakeInventory(),
        new ScannerFakeConfig(),
        new ScannerFakeBaseline(),
        new DiffService(),
        new ScannerFakeSnapshotMapper(),
        new ScannerFakeLogger()
    );

    $snapshot = $scanner->scan(null, false);

    assertSameValue('34.0.0', $snapshot->nextcloudVersion(), 'scanner should add Nextcloud version');
    assertSameValue(['deck'], $snapshot->unsupportedApps(), 'scanner should preserve unsupported apps');
    assertSameValue('red', $snapshot->summary()['compliance_status'], 'missing baseline should be red');
    assertSameValue('UNSUPPORTED', $snapshot->matrix()[0]->status(), 'scanner should not approve unsupported rows');

    echo 'ScannerService tests passed' . PHP_EOL;
}
