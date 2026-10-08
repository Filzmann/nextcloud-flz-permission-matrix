<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Controller;

use DateTimeInterface;
use InvalidArgumentException;
use OCA\FlzPermissionMatrix\AppInfo\AppId;
use OCA\FlzPermissionMatrix\Service\TemporaryAdminAccessDeniedException;
use OCA\FlzPermissionMatrix\Service\TemporaryAdminAccessService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

/** Dünne Admin-API für app-lokale, zeitbegrenzte Vollzugriffszeiträume. */
final class TemporaryAdminAccessController extends Controller {
    public function __construct(IRequest $request, private TemporaryAdminAccessService $service, private LoggerInterface $logger) {
        parent::__construct(AppId::VALUE, $request);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    public function status(): JSONResponse {
        try {
            $state = $this->service->state();
            $state['history'] = array_map([$this, 'serializeGrant'], $state['history']);
            return new JSONResponse($state);
        } catch (TemporaryAdminAccessDeniedException) {
            return $this->denied();
        } catch (Throwable $error) {
            return $this->failed($error);
        }
    }

    #[NoAdminRequired]
    public function activate(string $targetUid, int $durationMinutes): JSONResponse {
        try {
            return new JSONResponse(['grant' => $this->serializeGrant($this->service->activate($targetUid, $durationMinutes))]);
        } catch (InvalidArgumentException $error) {
            return new JSONResponse(['message' => $error->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (TemporaryAdminAccessDeniedException) {
            return $this->denied();
        } catch (Throwable $error) {
            return $this->failed($error);
        }
    }

    #[NoAdminRequired]
    public function revoke(string $targetUid): JSONResponse {
        try {
            return new JSONResponse(['revoked' => $this->service->revoke($targetUid)]);
        } catch (InvalidArgumentException $error) {
            return new JSONResponse(['message' => $error->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (TemporaryAdminAccessDeniedException) {
            return $this->denied();
        } catch (Throwable $error) {
            return $this->failed($error);
        }
    }

    private function serializeGrant(array $grant): array {
        foreach (['startsAt', 'endsAt', 'revokedAt'] as $field) {
            if (($grant[$field] ?? null) instanceof DateTimeInterface) {
                $grant[$field] = $grant[$field]->format(DATE_ATOM);
            }
        }
        return $grant;
    }

    private function denied(): JSONResponse { return new JSONResponse(['message' => 'Keine Berechtigung.'], Http::STATUS_FORBIDDEN); }

    private function failed(Throwable $error): JSONResponse {
        $this->logger->error('Temporärer Admin-Vollzugriff konnte nicht verarbeitet werden.', ['exception' => $error]);
        return new JSONResponse(['message' => 'Der Vollzugriff konnte nicht verarbeitet werden.'], Http::STATUS_INTERNAL_SERVER_ERROR);
    }
}

