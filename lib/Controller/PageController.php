<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Controller;

use OCA\FilzmannPermissionMatrix\AppInfo\Application;
use OCA\FilzmannPermissionMatrix\Service\AccessService;
use OCA\FilzmannPermissionMatrix\Service\AuditLogService;
use OCA\FilzmannPermissionMatrix\Service\TemporaryAdminAccessService;
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
        private AuditLogService $auditLog,
        private TemporaryAdminAccessService $temporaryAdminAccess,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        $hasMatrixAccess = $this->access->canViewCurrentUser();
        $canManageAdminAccess = $this->temporaryAdminAccess->canManage();
        $showMissingAdminGrant = $canManageAdminAccess && $this->temporaryAdminAccess->currentAdminNeedsGrant();
        if (!$hasMatrixAccess && !$canManageAdminAccess) {
            $this->auditLog->record('page.index.denied');
            $response = new TemplateResponse(Application::APP_ID, 'denied');
            $response->setStatus(Http::STATUS_FORBIDDEN);

            return $response;
        }

        $this->auditLog->record($hasMatrixAccess ? 'page.index' : 'page.index.admin_access');

        return new TemplateResponse(Application::APP_ID, 'index', [
            'can_manage' => $hasMatrixAccess && $this->access->canManageCurrentUser(),
            'hasMatrixAccess' => $hasMatrixAccess,
            'canManageAdminAccess' => $canManageAdminAccess,
            'showMissingAdminGrant' => $showMissingAdminGrant,
            'showAdminAccessLink' => $canManageAdminAccess && $showMissingAdminGrant,
        ]);
    }
}
