<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Controller;

use OCA\BrPermissionMatrix\AppInfo\Application;
use OCA\BrPermissionMatrix\Exception\AccessDeniedException;
use OCA\BrPermissionMatrix\Service\AccessService;
use OCA\BrPermissionMatrix\Service\AuditLogService;
use OCA\BrPermissionMatrix\Service\ConfigService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ConfigController extends Controller {
    public function __construct(
        private IRequest $request,
        private AccessService $access,
        private ConfigService $config,
        private AuditLogService $auditLog,
        private LoggerInterface $logger
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function get(): DataResponse {
        return $this->respond('api.config.get', function(): array {
            $this->auditLog->record('api.config.get');

            return [
                'ok' => true,
                'config' => $this->config->toArray(),
            ];
        });
    }

    #[NoAdminRequired]
    public function save(): DataResponse {
        return $this->respond('api.config.save', function(): array {
            $payload = array_intersect_key($this->request->getParams(), array_flip([
                'viewer_groups',
                'admin_groups',
                'scan_interval',
                'strict_mode',
                'include_users',
                'redact_paths',
                'include_share_metadata',
                'export_formats',
                'retention',
            ]));
            $config = $this->config->save($payload);
            $this->auditLog->record('api.config.save');

            return [
                'ok' => true,
                'config' => $config,
            ];
        });
    }

    private function respond(string $action, callable $callback): DataResponse {
        try {
            $this->access->assertCanManage();

            return new DataResponse($callback());
        } catch (AccessDeniedException $e) {
            $this->auditLog->record($action . '.denied');
            return new DataResponse(['ok' => false, 'message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        } catch (\Throwable $e) {
            $this->auditLog->record($action . '.failed');
            $this->logger->error('Permission matrix config API failed', ['app' => Application::APP_ID, 'exception' => $e]);

            return new DataResponse(['ok' => false, 'message' => 'Konfiguration konnte nicht gespeichert werden.'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
