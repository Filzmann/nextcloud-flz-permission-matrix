<?php

declare(strict_types=1);

namespace OCP { interface IRequest {} }
namespace OCP\AppFramework {
    class Controller { public function __construct(string $appId, \OCP\IRequest $request) {} }
    final class Http { public const STATUS_FORBIDDEN = 403; }
}
namespace OCP\AppFramework\Http {
    final class TemplateResponse {
        private int $status = 200;
        public function __construct(private string $appId, private string $template, private array $params = []) {}
        public function setStatus(int $status): void { $this->status = $status; }
        public function status(): int { return $this->status; }
        public function template(): string { return $this->template; }
        public function params(): array { return $this->params; }
    }
}
namespace OCP\AppFramework\Http\Attribute {
    #[\Attribute(\Attribute::TARGET_METHOD)] final class NoAdminRequired {}
    #[\Attribute(\Attribute::TARGET_METHOD)] final class NoCSRFRequired {}
}
namespace OCA\FilzmannPermissionMatrix\AppInfo { final class Application { public const APP_ID = 'filzmann_permission_matrix'; } }
namespace OCA\FilzmannPermissionMatrix\Service {
    class AccessService {
        public function __construct(public bool $view = false, public bool $manage = false) {}
        public function canViewCurrentUser(): bool { return $this->view; }
        public function canManageCurrentUser(): bool { return $this->manage; }
    }
    class AuditLogService {
        public array $actions = [];
        public function record(string $action): void { $this->actions[] = $action; }
    }
    class TemporaryAdminAccessService {
        public function __construct(public bool $manage = false, public bool $missing = false) {}
        public function canManage(): bool { return $this->manage; }
        public function currentAdminNeedsGrant(): bool { return $this->missing; }
    }
}
namespace {
    use OCA\FilzmannPermissionMatrix\Controller\PageController;
    use OCA\FilzmannPermissionMatrix\Service\AccessService;
    use OCA\FilzmannPermissionMatrix\Service\AuditLogService;
    use OCA\FilzmannPermissionMatrix\Service\TemporaryAdminAccessService;

    $request = new class implements OCP\IRequest {};

    $audit = new AuditLogService();
    $ordinaryResponse = (new PageController(
        $request,
        new AccessService(false, false),
        $audit,
        new TemporaryAdminAccessService(false, false),
    ))->index();
    assertSameValue(403, $ordinaryResponse->status(), 'An ordinary account must be denied at the main entry.');
    assertSameValue(['page.index.denied'], $audit->actions, 'The ordinary-account denial must be audited.');

    $audit = new AuditLogService();
    $adminResponse = (new PageController(
        $request,
        new AccessService(false, false),
        $audit,
        new TemporaryAdminAccessService(false, true),
    ))->index();
    assertSameValue(200, $adminResponse->status(), 'A native admin without a grant must reach its own safe warning.');
    assertSameValue(false, $adminResponse->params()['hasMatrixAccess'], 'The warning must not disclose matrix data.');
    assertSameValue(false, $adminResponse->params()['canManageAdminAccess'], 'Native admin status alone must not expose grant controls.');
    assertSameValue(true, $adminResponse->params()['showMissingAdminGrant'], 'The affected native admin must receive the missing-grant warning.');
    assertSameValue(false, $adminResponse->params()['showAdminAccessLink'], 'A native admin outside Datenschutzbeauftragte must not receive the grant-management link.');
    assertSameValue(['page.index.admin_access'], $audit->actions, 'The warning-only entry must be distinguishably audited.');

    $audit = new AuditLogService();
    $dpoResponse = (new PageController(
        $request,
        new AccessService(false, false),
        $audit,
        new TemporaryAdminAccessService(true, false),
    ))->index();
    assertSameValue(200, $dpoResponse->status(), 'A DPO without native admin status must reach grant management.');
    assertSameValue(false, $dpoResponse->params()['hasMatrixAccess'], 'DPO status alone must not disclose matrix data.');
    assertSameValue(true, $dpoResponse->params()['canManageAdminAccess'], 'DPO grant controls must be projected.');
    assertSameValue(false, $dpoResponse->params()['showMissingAdminGrant'], 'A non-admin DPO must not receive an irrelevant missing-grant notice.');
    assertSameValue(['page.index.admin_access'], $audit->actions, 'The grant-management-only entry must be distinguishably audited.');

    $combinedResponse = (new PageController(
        $request,
        new AccessService(false, false),
        new AuditLogService(),
        new TemporaryAdminAccessService(true, true),
    ))->index();
    assertSameValue(true, $combinedResponse->params()['showMissingAdminGrant'], 'The combined admin/DPO account should see its missing-grant notice.');
    assertSameValue(true, $combinedResponse->params()['showAdminAccessLink'], 'Only the same combined account should receive the direct control link.');

    echo "Permission Matrix page controller admin access tests passed\n";
}
