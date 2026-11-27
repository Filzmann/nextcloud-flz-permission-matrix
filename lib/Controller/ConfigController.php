<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Controller;

use OCA\FlzPermissionMatrix\AppInfo\Application;
use OCA\FlzPermissionMatrix\Exception\AccessDeniedException;
use OCA\FlzPermissionMatrix\Exception\ConfigValidationException;
use OCA\FlzPermissionMatrix\Service\AccessService;
use OCA\FlzPermissionMatrix\Service\AuditLogService;
use OCA\FlzPermissionMatrix\Service\ConfigService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ConfigController extends Controller {
    public function __construct(
        IRequest $request,
        private AccessService $access,
        private ConfigService $config,
        private AuditLogService $auditLog,
        private LoggerInterface $logger,
        private ?IL10N $l10n = null,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /** Lokalisierung fachlicher Texte; im Test ohne IL10N identity. */
    private function t(string $text): string
    {
        return $this->l10n?->t($text) ?? $text;
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
                'scan_interval',
                'strict_mode',
                'include_users',
                'redact_paths',
                'include_share_metadata',
                'export_formats',
                'retention',
                'export_metadata_retention_days',
                'audit_retention_days',
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
            return new DataResponse(['ok' => false, 'message' => $this->t($e->getMessage())], Http::STATUS_FORBIDDEN);
        } catch (ConfigValidationException $e) {
            $this->auditLog->record($action . '.rejected');
            return new DataResponse(['ok' => false, 'message' => $this->t($e->getMessage())], Http::STATUS_BAD_REQUEST);
        } catch (\Throwable $e) {
            $this->auditLog->record($action . '.failed');
            $this->logger->error('Konfigurations-API der Berechtigungsmatrix fehlgeschlagen', ['app' => Application::APP_ID, 'exception' => $e]);

            return new DataResponse(['ok' => false, 'message' => $this->t('Konfiguration konnte nicht gespeichert werden.')], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
