<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Controller;

use DomainException;
use InvalidArgumentException;
use OCA\BrPermissionMatrix\AppInfo\Application;
use OCA\BrPermissionMatrix\Db\ExportMapper;
use OCA\BrPermissionMatrix\Db\SnapshotMapper;
use OCA\BrPermissionMatrix\Exception\AccessDeniedException;
use OCA\BrPermissionMatrix\Service\AccessService;
use OCA\BrPermissionMatrix\Service\AuditLogService;
use OCA\BrPermissionMatrix\Service\BaselineService;
use OCA\BrPermissionMatrix\Service\DiffService;
use OCA\BrPermissionMatrix\Service\ExportService;
use OCA\BrPermissionMatrix\Service\ScannerService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ApiController extends Controller {
    public function __construct(
        IRequest $request,
        private AccessService $access,
        private SnapshotMapper $snapshots,
        private ScannerService $scanner,
        private BaselineService $baseline,
        private DiffService $diffs,
        private ExportService $exports,
        private ExportMapper $exportLog,
        private AuditLogService $auditLog,
        private LoggerInterface $logger
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function state(): DataResponse {
        return $this->respondView('api.state', function(): array {
            $latest = $this->snapshots->latest();
            $this->auditLog->record('api.state', false, $latest?->snapshotId());

            return [
                'ok' => true,
                'snapshot' => $latest?->toArray(),
                'baseline_snapshot' => $this->baseline->currentBaselineId(),
                'can_manage' => $this->access->canManageCurrentUser(),
            ];
        });
    }

    #[NoAdminRequired]
    public function scan(): DataResponse {
        return $this->respondManage('api.scan', function(): array {
            $snapshot = $this->scanner->scan($this->access->currentUserId());
            $this->auditLog->record('api.scan', false, $snapshot->snapshotId());

            return [
                'ok' => true,
                'snapshot' => $snapshot->toArray(),
            ];
        });
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function snapshots(): DataResponse {
        return $this->respondView('api.snapshots', function(): array {
            $this->auditLog->record('api.snapshots');

            return [
                'ok' => true,
                'snapshots' => $this->snapshots->list(100),
                'baseline_snapshot' => $this->baseline->currentBaselineId(),
            ];
        });
    }

    #[NoAdminRequired]
    public function setBaseline(string $snapshotId): DataResponse {
        return $this->respondManage('api.baseline.set', function() use ($snapshotId): array {
            $snapshot = $this->baseline->setBaseline($snapshotId);
            $this->auditLog->record('api.baseline.set', false, $snapshotId);

            return [
                'ok' => true,
                'baseline' => $snapshot->toArray(),
            ];
        });
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function diff(string $snapshotA, string $snapshotB): DataResponse {
        return $this->respondView('api.diff', function() use ($snapshotA, $snapshotB): array {
            $a = $this->snapshots->find($snapshotA);
            $b = $this->snapshots->find($snapshotB);
            if ($a === null || $b === null) {
                throw new DomainException('Snapshot nicht gefunden.');
            }
            $this->auditLog->record('api.diff', false, $snapshotB, ['from' => $snapshotA]);

            return [
                'ok' => true,
                'diff' => $this->diffs->compareSnapshots($a, $b),
            ];
        });
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function exportLatest(string $format): Response {
        return $this->exportResponse($format, null);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function exportSnapshot(string $format, string $snapshotId): Response {
        return $this->exportResponse($format, $snapshotId);
    }

    private function exportResponse(string $format, ?string $snapshotId): Response {
        try {
            $this->access->assertCanView();
            $snapshot = $snapshotId === null ? $this->snapshots->latest() : $this->snapshots->find($snapshotId);
            if ($snapshot === null) {
                $this->auditLog->record('api.export.not_found');
                return new DataResponse(['ok' => false, 'message' => 'Snapshot nicht gefunden.'], Http::STATUS_NOT_FOUND);
            }
            $export = $this->exports->export($snapshot, $format);
            $this->exportLog->insert($snapshot->snapshotId(), $this->access->currentUserId(), $format, $export['filename'], strlen($export['content']));
            $this->auditLog->record('api.export', true, $snapshot->snapshotId(), ['format' => $format]);

            return new DataDownloadResponse($export['content'], $export['filename'], $export['content_type']);
        } catch (InvalidArgumentException $e) {
            $this->auditLog->record('api.export.rejected', false, null, ['reason' => 'invalid_format']);
            return new DataResponse(['ok' => false, 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (AccessDeniedException $e) {
            $this->auditLog->record('api.export.denied');
            return new DataResponse(['ok' => false, 'message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        } catch (\Throwable $e) {
            $this->auditLog->record('api.export.failed');
            $this->logger->error('Permission matrix export failed', ['app' => Application::APP_ID, 'exception' => $e]);

            return new DataResponse(['ok' => false, 'message' => 'Export konnte nicht erzeugt werden.'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    private function respondView(string $action, callable $callback): DataResponse {
        try {
            $this->access->assertCanView();

            return new DataResponse($callback());
        } catch (AccessDeniedException $e) {
            $this->auditLog->record($action . '.denied');
            return new DataResponse(['ok' => false, 'message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        } catch (DomainException $e) {
            $this->auditLog->record($action . '.not_found');
            return new DataResponse(['ok' => false, 'message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
        } catch (\Throwable $e) {
            $this->auditLog->record($action . '.failed');
            $this->logger->error('Permission matrix API failed', ['app' => Application::APP_ID, 'exception' => $e]);

            return new DataResponse(['ok' => false, 'message' => 'Aktion konnte nicht ausgefuehrt werden.'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    private function respondManage(string $action, callable $callback): DataResponse {
        try {
            $this->access->assertCanManage();

            return new DataResponse($callback());
        } catch (AccessDeniedException $e) {
            $this->auditLog->record($action . '.denied');
            return new DataResponse(['ok' => false, 'message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        } catch (DomainException $e) {
            $this->auditLog->record($action . '.not_found');
            return new DataResponse(['ok' => false, 'message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
        } catch (\Throwable $e) {
            $this->auditLog->record($action . '.failed');
            $this->logger->error('Permission matrix API failed', ['app' => Application::APP_ID, 'exception' => $e]);

            return new DataResponse(['ok' => false, 'message' => 'Aktion konnte nicht ausgefuehrt werden.'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
