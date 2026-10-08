<?php

declare(strict_types=1);

namespace OCP {
    interface IRequest {
    }
}

namespace OCP\AppFramework {
    class Http {
        public const STATUS_BAD_REQUEST = 400;
        public const STATUS_FORBIDDEN = 403;
        public const STATUS_NOT_FOUND = 404;
        public const STATUS_INTERNAL_SERVER_ERROR = 500;
    }

    class Controller {
        public function __construct(string $appName, protected \OCP\IRequest $request) {
        }
    }
}

namespace OCP\AppFramework\Http {
    class Response {
        public function __construct(protected int $status = 200) {
        }

        public function getStatus(): int {
            return $this->status;
        }
    }

    class DataResponse extends Response {
        public function __construct(private mixed $data = [], int $status = 200) {
            parent::__construct($status);
        }

        public function getData(): mixed {
            return $this->data;
        }
    }

    class DataDownloadResponse extends Response {
        public function __construct(string $content, string $filename, string $contentType) {
            parent::__construct();
        }
    }
}

namespace OCP\AppFramework\Http\Attribute {
    #[\Attribute(\Attribute::TARGET_METHOD)]
    class NoAdminRequired {
    }

    #[\Attribute(\Attribute::TARGET_METHOD)]
    class NoCSRFRequired {
    }
}

namespace OCA\FlzPermissionMatrix\AppInfo {
    class Application {
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
    use OCA\FlzPermissionMatrix\Controller\ApiController;
    use OCA\FlzPermissionMatrix\Db\ExportMapper;
    use OCA\FlzPermissionMatrix\Db\SnapshotMapper;
    use OCA\FlzPermissionMatrix\Exception\AccessDeniedException;
    use OCA\FlzPermissionMatrix\Model\Snapshot;
    use OCA\FlzPermissionMatrix\Service\AccessService;
    use OCA\FlzPermissionMatrix\Service\AuditLogService;
    use OCA\FlzPermissionMatrix\Service\BaselineService;
    use OCA\FlzPermissionMatrix\Service\ConfigService;
    use OCA\FlzPermissionMatrix\Service\DiffService;
    use OCA\FlzPermissionMatrix\Service\ExportService;
    use OCA\FlzPermissionMatrix\Service\ScannerService;
    use OCP\IRequest;
    use Psr\Log\LoggerInterface;

    class ApiAccessTestRequest implements IRequest {
    }

    class ApiAccessTestAccess extends AccessService {
        public bool $viewAllowed = false;
        public bool $manageAllowed = false;

        public function __construct() {
        }

        public function assertCanView(): void {
            if (!$this->viewAllowed) {
                throw new AccessDeniedException('Zugriff verweigert.');
            }
        }

        public function assertCanManage(): void {
            if (!$this->manageAllowed) {
                throw new AccessDeniedException('Zugriff verweigert.');
            }
        }

        public function canManageCurrentUser(): bool {
            return $this->manageAllowed;
        }

        public function currentUserId(): ?string {
            return 'test-user';
        }
    }

    class ApiAccessTestSnapshots extends SnapshotMapper {
        public int $latestCalls = 0;

        public function __construct() {
        }

        public function latest(): ?Snapshot {
            $this->latestCalls++;
            return null;
        }

        public function find(string $snapshotId): ?Snapshot {
            return null;
        }
    }

    class ApiAccessTestScanner extends ScannerService {
        public int $scanCalls = 0;

        public function __construct() {
        }

        public function scan(?string $createdBy = null, bool $persist = true): Snapshot {
            $this->scanCalls++;
            throw new RuntimeException('Scanner must not run in this authorization test.');
        }
    }

    class ApiAccessTestBaseline extends BaselineService {
        public function __construct() {
        }

        public function currentBaselineId(): string {
            return '';
        }
    }

    class ApiAccessTestExportMapper extends ExportMapper {
        public function __construct() {
        }
    }

    class ApiAccessTestConfig extends ConfigService {
        public function __construct() {
        }

        public function exportFormats(): array {
            return ['md', 'csv', 'json', 'html'];
        }
    }

    class ApiAccessTestAudit extends AuditLogService {
        public array $actions = [];

        public function __construct() {
        }

        public function record(string $action, bool $exportGenerated = false, ?string $snapshotId = null, array $details = []): void {
            $this->actions[] = $action;
        }
    }

    class ApiAccessTestLogger implements LoggerInterface {
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

    $access = new ApiAccessTestAccess();
    $snapshots = new ApiAccessTestSnapshots();
    $scanner = new ApiAccessTestScanner();
    $audit = new ApiAccessTestAudit();
    $controller = new ApiController(
        new ApiAccessTestRequest(),
        $access,
        $snapshots,
        $scanner,
        new ApiAccessTestBaseline(),
        new DiffService(),
        new ExportService(new ApiAccessTestConfig()),
        new ApiAccessTestExportMapper(),
        $audit,
        new ApiAccessTestLogger()
    );

    $deniedState = $controller->state();
    assertSameValue(403, $deniedState->getStatus(), 'Direct state requests without viewer rights must be forbidden.');
    assertSameValue(0, $snapshots->latestCalls, 'Authorization must run before snapshot data is read.');
    assertSameValue(['api.state.denied'], $audit->actions, 'Denied state access must be audited.');

    $deniedScan = $controller->scan();
    assertSameValue(403, $deniedScan->getStatus(), 'Direct scan requests without management rights must be forbidden.');
    assertSameValue(0, $scanner->scanCalls, 'Authorization must run before a scan changes state.');
    assertSameValue(['api.state.denied', 'api.scan.denied'], $audit->actions, 'Denied management access must be audited.');

    $access->viewAllowed = true;
    $allowedState = $controller->state();
    assertSameValue(200, $allowedState->getStatus(), 'Configured viewers may call the state API.');
    assertSameValue(1, $snapshots->latestCalls, 'Allowed state requests should read the latest snapshot.');
    assertSameValue(true, $allowedState->getData()['ok'], 'Allowed state requests should return the regular payload.');
    assertSameValue(['md', 'csv', 'json', 'html'], $allowedState->getData()['export_formats'], 'State responses should expose the enforced export policy to the UI.');
    assertSameValue('api.state', $audit->actions[2], 'Allowed state access must be audited separately.');

    $missingDiff = $controller->diff('missing-a', 'missing-b');
    assertSameValue(404, $missingDiff->getStatus(), 'Missing snapshots must not be reported as authorization failures.');
    assertSameValue('api.diff.not_found', $audit->actions[3], 'Missing snapshot access must have a distinct audit outcome.');
    assertSameValue(
        true,
        class_exists(\OCA\FlzPermissionMatrix\Controller\ConfigController::class),
        'ConfigController must remain compatible with the protected request property of the Nextcloud base controller.'
    );

    echo 'ApiController access tests passed' . PHP_EOL;
}
