<?php

declare(strict_types=1);

namespace OCP {
    interface IRequest {
        public function getParams(): array;
    }
}

namespace OCP\AppFramework {
    class Http {
        public const STATUS_BAD_REQUEST = 400;
        public const STATUS_FORBIDDEN = 403;
        public const STATUS_INTERNAL_SERVER_ERROR = 500;
    }

    class Controller {
        public function __construct(string $appName, protected \OCP\IRequest $request) {
        }
    }
}

namespace OCP\AppFramework\Http {
    class DataResponse {
        public function __construct(private mixed $data = [], private int $status = 200) {
        }
        public function getData(): mixed { return $this->data; }
        public function getStatus(): int { return $this->status; }
    }
}

namespace OCP\AppFramework\Http\Attribute {
    #[\Attribute(\Attribute::TARGET_METHOD)] class NoAdminRequired {}
    #[\Attribute(\Attribute::TARGET_METHOD)] class NoCSRFRequired {}
}

namespace OCA\FlzPermissionMatrix\AppInfo {
    final class Application {
        public const APP_ID = 'flz_permission_matrix';
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
    use OCA\FlzPermissionMatrix\Controller\ConfigController;
    use OCA\FlzPermissionMatrix\Exception\AccessDeniedException;
    use OCA\FlzPermissionMatrix\Exception\ConfigValidationException;
    use OCA\FlzPermissionMatrix\Service\AccessService;
    use OCA\FlzPermissionMatrix\Service\AuditLogService;
    use OCA\FlzPermissionMatrix\Service\ConfigService;
    use OCP\IRequest;
    use Psr\Log\LoggerInterface;

    final class ConfigControllerTestRequest implements IRequest {
        public function __construct(private array $params) {}
        public function getParams(): array { return $this->params; }
    }

    final class ConfigControllerTestAccess extends AccessService {
        public function __construct(public bool $allowed) {}
        public function assertCanManage(): void {
            if (!$this->allowed) throw new AccessDeniedException('Zugriff verweigert.');
        }
    }

    final class ConfigControllerTestConfig extends ConfigService {
        public int $saveCalls = 0;
        public array $lastPayload = [];
        public function __construct(private bool $valid) {}
        public function save(array $payload): array {
            $this->saveCalls++;
            $this->lastPayload = $payload;
            if (!$this->valid) throw new ConfigValidationException('Retention muss zwischen 1 und 500 liegen.');
            return ['retention' => 50, 'export_metadata_retention_days' => 180, 'audit_retention_days' => 180];
        }
    }

    final class ConfigControllerTestAudit extends AuditLogService {
        public array $actions = [];
        public function __construct() {}
        public function record(string $action, bool $exportGenerated = false, ?string $snapshotId = null, array $details = []): void {
            $this->actions[] = $action;
        }
    }

    final class ConfigControllerTestLogger implements LoggerInterface {
        public array $errors = [];
        public function emergency($message, array $context = []): void {}
        public function alert($message, array $context = []): void {}
        public function critical($message, array $context = []): void {}
        public function error($message, array $context = []): void { $this->errors[] = $message; }
        public function warning($message, array $context = []): void {}
        public function notice($message, array $context = []): void {}
        public function info($message, array $context = []): void {}
        public function debug($message, array $context = []): void {}
        public function log($level, $message, array $context = []): void {}
    }

    $deniedConfig = new ConfigControllerTestConfig(true);
    $deniedAudit = new ConfigControllerTestAudit();
    $denied = new ConfigController(
        new ConfigControllerTestRequest([
            'retention' => '50',
            'export_metadata_retention_days' => '120',
            'audit_retention_days' => '240',
            'ignored' => 'must-not-pass',
        ]),
        new ConfigControllerTestAccess(false),
        $deniedConfig,
        $deniedAudit,
        new ConfigControllerTestLogger()
    );
    assertSameValue(403, $denied->save()->getStatus(), 'Unauthorized configuration writes must be rejected server-side.');
    assertSameValue(0, $deniedConfig->saveCalls, 'Denied requests must not reach configuration persistence.');
    assertSameValue(['api.config.save.denied'], $deniedAudit->actions, 'Denied writes must be audited.');

    $invalidConfig = new ConfigControllerTestConfig(false);
    $invalidAudit = new ConfigControllerTestAudit();
    $invalidLogger = new ConfigControllerTestLogger();
    $invalid = new ConfigController(
        new ConfigControllerTestRequest(['retention' => '0']),
        new ConfigControllerTestAccess(true),
        $invalidConfig,
        $invalidAudit,
        $invalidLogger
    );
    $invalidResponse = $invalid->save();
    assertSameValue(400, $invalidResponse->getStatus(), 'Invalid configuration must return HTTP 400.');
    assertContainsText('Retention', $invalidResponse->getData()['message'], 'The validation response should identify the rejected field.');
    assertSameValue(['api.config.save.rejected'], $invalidAudit->actions, 'Validation rejection must be distinguishable in the audit.');
    assertSameValue([], $invalidLogger->errors, 'Expected validation problems must not be logged as internal failures.');

    $allowedConfig = new ConfigControllerTestConfig(true);
    $allowedAudit = new ConfigControllerTestAudit();
    $allowed = new ConfigController(
        new ConfigControllerTestRequest([
            'retention' => '50',
            'export_metadata_retention_days' => '120',
            'audit_retention_days' => '240',
            'ignored' => 'must-not-pass',
        ]),
        new ConfigControllerTestAccess(true),
        $allowedConfig,
        $allowedAudit,
        new ConfigControllerTestLogger()
    );
    assertSameValue(200, $allowed->save()->getStatus(), 'Authorized valid configuration writes should succeed.');
    assertSameValue(1, $allowedConfig->saveCalls, 'The authorized path should persist exactly once.');
    assertSameValue([
        'retention' => '50',
        'export_metadata_retention_days' => '120',
        'audit_retention_days' => '240',
    ], $allowedConfig->lastPayload, 'Only allowlisted retention settings should reach persistence.');
    assertSameValue(['api.config.save'], $allowedAudit->actions, 'Successful writes must be audited.');

    echo 'ConfigController tests passed' . PHP_EOL;
}
