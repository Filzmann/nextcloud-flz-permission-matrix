<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Privacy;

use DateTimeImmutable;
use InvalidArgumentException;
use OCA\FlzDataProtection\PublicApi\V1\RetentionCandidate;
use OCA\FlzDataProtection\PublicApi\V1\RetentionPolicy;
use OCA\FlzDataProtection\PublicApi\V1\RetentionPreviewPage;
use OCA\FlzDataProtection\PublicApi\V1\RetentionPreviewRequest;
use OCA\FlzDataProtection\PublicApi\V1\RetentionProvider;
use OCA\FlzDataProtection\PublicApi\V1\RetentionProviderDescriptor;
use OCA\FlzPermissionMatrix\AppInfo\AppId;
use OCA\FlzPermissionMatrix\Db\RetentionReviewRepository;
use OCA\FlzPermissionMatrix\Service\ConfigService;

final class PermissionMatrixRetentionProvider implements RetentionProvider {
    private const MAX_PAGE_SIZE = 200;
    private const MAX_OFFSET = 1000000;

    public function __construct(private RetentionReviewRepository $records, private ConfigService $config) {}

    public function descriptor(): RetentionProviderDescriptor {
        return new RetentionProviderDescriptor(AppId::VALUE, 'Filzmann Permission Matrix', '1.0', self::MAX_PAGE_SIZE);
    }

    public function policies(): array {
        return [
            new RetentionPolicy('export-metadata-review', 'Exportmetadaten', 'Prüfung technischer Exportnachweise', 'CREATED_AT', $this->config->exportMetadataRetentionDays(), 'REVIEW', '1.0'),
            new RetentionPolicy('audit-log-review', 'Auditprotokolle', 'Prüfung technischer Zugriffs- und Administrationsnachweise', 'CREATED_AT', $this->config->auditRetentionDays(), 'REVIEW', '1.0'),
        ];
    }

    public function preview(RetentionPreviewRequest $request): RetentionPreviewPage {
        [$source, $days] = match ($request->policyId()) {
            'export-metadata-review' => ['export', $this->config->exportMetadataRetentionDays()],
            'audit-log-review' => ['audit', $this->config->auditRetentionDays()],
            default => throw new InvalidArgumentException('Unknown Permission Matrix retention policy.'),
        };
        $offset = $this->offset($request->cursor(), $request->policyId(), $request->evaluatedAt());
        $limit = min($request->limit(), self::MAX_PAGE_SIZE);
        $cutoff = (new DateTimeImmutable($request->evaluatedAt()))->modify('-' . $days . ' days')->format(DATE_ATOM);
        $records = $this->records->collectDue($source, $cutoff, $offset + $limit + 1);
        $pageRecords = array_slice($records, $offset, $limit + 1);
        $hasMore = count($pageRecords) > $limit;
        if ($hasMore) $pageRecords = array_slice($pageRecords, 0, $limit);

        $label = $source === 'export' ? 'Exportmetadaten' : 'Auditprotokoll';
        $candidates = array_map(static fn(array $record): RetentionCandidate => new RetentionCandidate(
            $request->policyId(),
            'permission-matrix:' . $source . ':' . (string)$record['id'],
            (string)$record['createdAt'],
            'REVIEW',
            'Konfigurierte Prüffrist von ' . $days . ' Tagen überschritten.',
            ['Datentyp' => $label, 'Erstellt am' => (string)$record['createdAt']],
        ), $pageRecords);

        return new RetentionPreviewPage(
            $hasMore ? 'partial' : 'complete',
            $candidates,
            [],
            $hasMore ? $this->cursor($request->policyId(), $request->evaluatedAt(), $offset + $limit) : null,
        );
    }

    private function offset(?string $cursor, string $policyId, string $evaluatedAt): int {
        if ($cursor === null) return 0;
        $padding = (4 - strlen($cursor) % 4) % 4;
        $decoded = base64_decode(strtr($cursor . str_repeat('=', $padding), '-_', '+/'), true);
        $state = $decoded === false ? null : json_decode($decoded, true);
        if (!is_array($state) || ($state['policyId'] ?? null) !== $policyId || ($state['evaluatedAt'] ?? null) !== $evaluatedAt || !is_int($state['offset'] ?? null) || $state['offset'] < 1 || $state['offset'] > self::MAX_OFFSET) {
            throw new InvalidArgumentException('Invalid Permission Matrix retention cursor.');
        }
        return $state['offset'];
    }

    private function cursor(string $policyId, string $evaluatedAt, int $offset): string {
        return rtrim(strtr(base64_encode(json_encode(compact('policyId', 'evaluatedAt', 'offset'), JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }
}
