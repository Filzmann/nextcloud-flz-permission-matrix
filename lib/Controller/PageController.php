<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Controller;

use OCA\FilzmannPermissionMatrix\AppInfo\Application;
use OCA\FilzmannPermissionMatrix\Service\AccessService;
use OCA\FilzmannPermissionMatrix\Service\AuditLogService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

class PageController extends Controller {
    public function __construct(
        IRequest $request,
        private AccessService $access,
        private AuditLogService $auditLog
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        if (!$this->access->canViewCurrentUser()) {
            $this->auditLog->record('page.index.denied');
            $response = new TemplateResponse(Application::APP_ID, 'denied');
            $response->setStatus(Http::STATUS_FORBIDDEN);

            return $response;
        }

        $this->auditLog->record('page.index');

        return new TemplateResponse(Application::APP_ID, 'index', [
            'can_manage' => $this->access->canManageCurrentUser(),
        ]);
    }
}
