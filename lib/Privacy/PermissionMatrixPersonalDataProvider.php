<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Privacy;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataEntry;
use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataPage;
use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataProvider;
use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataRequest;
use OCA\FilzmannDataProtection\PublicApi\V1\ProviderDescriptor;
use OCA\FilzmannPermissionMatrix\AppInfo\AppId;
use OCA\FilzmannPermissionMatrix\Db\PersonalDataProjectionRepository;
use OCA\FilzmannPermissionMatrix\Service\ConfigService;

final class PermissionMatrixPersonalDataProvider implements PersonalDataProvider {
    private const MAX_PAGE_SIZE = 200;
    private const MAX_OFFSET = 1000000;

    public function __construct(
        private PersonalDataProjectionRepository $records,
        private ConfigService $config,
    ) {
    }

    public function descriptor(): ProviderDescriptor {
        return new ProviderDescriptor(
            AppId::VALUE,
            'Filzmann Permission Matrix',
            '1.0',
            ['nextcloud-user'],
            ['personal-data'],
            self::MAX_PAGE_SIZE,
        );
    }

    public function collect(PersonalDataRequest $request): PersonalDataPage {
        if ($request->subject()->subjectType() !== 'nextcloud-user') {
            return new PersonalDataPage('not_applicable');
        }

        [$asOf, $offset] = $this->cursorState($request->cursor());
        $limit = min($request->pageLimit(), self::MAX_PAGE_SIZE);
        $records = $this->records->collectForSubject(
            $request->subject()->subjectId(),
            $offset + $limit + 1,
            $asOf,
        );
        usort($records, static fn(array $left, array $right): int => [
            (string)$right['createdAt'],
            (string)$right['source'],
            (string)$right['id'],
        ] <=> [
            (string)$left['createdAt'],
            (string)$left['source'],
            (string)$left['id'],
        ]);

        $pageRecords = array_slice($records, $offset, $limit + 1);
        $hasMore = count($pageRecords) > $limit;
        if ($hasMore) {
            $pageRecords = array_slice($pageRecords, 0, $limit);
        }

        if ($pageRecords === [] && $offset === 0) {
            return new PersonalDataPage('not_applicable');
        }

        return new PersonalDataPage(
            $hasMore ? 'partial' : 'complete',
            array_map(fn(array $record): PersonalDataEntry => $this->entry($record, $asOf, $request->subject()->subjectId()), $pageRecords),
            $hasMore ? ['Weitere eigene Permission-Matrix-Nachweise sind auf einer Folgeseite verfügbar.'] : [],
            $hasMore ? $this->encodeCursor($asOf, $offset + $limit) : null,
        );
    }

    /** @param array<string, scalar|null> $record */
    private function entry(array $record, string $asOf, string $subjectUid): PersonalDataEntry {
        return match ($record['source'] ?? '') {
            'snapshot' => $this->snapshotEntry($record),
            'export' => $this->exportEntry($record, $asOf),
            'audit' => $this->auditEntry($record, $asOf),
            'admin_access' => $this->adminAccessEntry($record, $subjectUid),
            default => throw new InvalidArgumentException('Unknown personal-data projection record.'),
        };
    }

    private function adminAccessEntry(array $record, string $subjectUid): PersonalDataEntry {
        $roles = [];
        if ($record['targetUid'] === $subjectUid) {
            $roles[] = 'Ziel der Vollzugriffsfreigabe';
        }
        if ($record['grantedBy'] === $subjectUid) {
            $roles[] = 'Freigebende Administration';
        }
        if ($record['revokedBy'] === $subjectUid) {
            $roles[] = 'Widerrufende Administration';
        }
        $actualEnd = $record['revokedAt'] ?? $record['endsAt'];

        return new PersonalDataEntry(
            categoryId: 'admin-access',
            categoryLabel: 'Zeitlich begrenzter Admin-Vollzugriff',
            reference: 'permission-matrix:admin-access:' . (string)$record['id'],
            summary: 'Admin-Vollzugriff vom ' . $this->dateLabel((string)$record['startsAt']),
            purpose: 'Nachweis einer zeitlich begrenzten administrativen Matrix-Freigabe',
            source: 'App-lokale Freigabe im Nextcloud-Adminbereich',
            recipientCategories: $this->recipients(),
            retention: 'Keine feste Löschfrist festgelegt; die sicherheitsrelevante Freigabehistorie bleibt bis zu einer gesonderten Aufbewahrungsentscheidung erhalten.',
            thirdCountryTransfer: 'Durch die Permission-Matrix sind keine Drittlandübermittlungen vorgesehen.',
            automatedDecision: 'Der Server beendet den Vollzugriff spätestens nach 24 Stunden automatisch.',
            thirdPartyContentNotice: 'Kennungen anderer beteiligter Administrator*innen werden nicht ausgegeben.',
            attributes: [
                'Eigene Rolle im Vorgang' => implode(', ', $roles),
                'Beginn' => (string)$record['startsAt'],
                'Geplantes Ende' => (string)$record['endsAt'],
                'Tatsächliches Ende' => (string)$actualEnd,
                'Status' => $record['revokedAt'] === null ? 'planmäßig beendet oder noch aktiv' : 'widerrufen',
            ],
        );
    }

    /** @param array<string, scalar|null> $record */
    private function snapshotEntry(array $record): PersonalDataEntry {
        return new PersonalDataEntry(
            categoryId: 'snapshot_creation',
            categoryLabel: 'Erstellter Berechtigungssnapshot',
            reference: 'permission-matrix:snapshot:' . (string)$record['id'],
            summary: 'Berechtigungssnapshot vom ' . $this->dateLabel((string)$record['createdAt']),
            purpose: 'Dokumentation und Prüfung des Berechtigungsstands der Nextcloud-Instanz',
            source: 'Manueller oder geplanter Matrix-Scan unter deiner Nextcloud-Kennung',
            recipientCategories: $this->recipients(),
            retention: 'Mengenbasiert: Der Snapshot bleibt bestehen, bis er durch die konfigurierte Anzahl neuerer Snapshots verdrängt wird.',
            thirdCountryTransfer: 'Durch die Permission-Matrix sind keine Drittlandübermittlungen vorgesehen.',
            automatedDecision: 'Der Compliance-Status wird regelbasiert abgeleitet; er entfaltet keine rechtliche oder vergleichbar erhebliche Wirkung.',
            thirdPartyContentNotice: 'Snapshotinhalt und mögliche Benutzerlisten werden nicht ausgegeben; erhalten bleibt nur dein Erstellungsbezug mit neutralen Kennzahlen.',
            attributes: [
                'Snapshot-Referenz' => (string)$record['snapshotId'],
                'Nextcloud-Version' => (string)$record['nextcloudVersion'],
                'Compliance-Status' => (string)$record['complianceStatus'],
                'Gruppenanzahl' => (int)$record['groupCount'],
                'App-Anzahl' => (int)$record['appCount'],
                'Berechtigungsobjekte' => (int)$record['objectCount'],
                'Erstellt am' => (string)$record['createdAt'],
            ],
        );
    }

    /** @param array<string, scalar|null> $record */
    private function exportEntry(array $record, string $asOf): PersonalDataEntry {
        $review = $this->retentionReview((string)$record['createdAt'], $asOf, $this->config->exportMetadataRetentionDays());

        return new PersonalDataEntry(
            categoryId: 'export_creation',
            categoryLabel: 'Erzeugter Matrixexport',
            reference: 'permission-matrix:export:' . (string)$record['id'],
            summary: 'Matrixexport vom ' . $this->dateLabel((string)$record['createdAt']),
            purpose: 'Nachweis, wann und in welchem Format eine Berechtigungsmatrix exportiert wurde',
            source: 'Von dir ausgelöster Export in der Permission-Matrix',
            recipientCategories: $this->recipients(),
            retention: $review['description'],
            thirdCountryTransfer: 'Durch die Permission-Matrix sind keine Drittlandübermittlungen vorgesehen.',
            automatedDecision: 'Keine automatisierte Entscheidung.',
            thirdPartyContentNotice: 'Freier Dateiname und Exportinhalt werden nicht ausgegeben; der technische Exportkontext bleibt erhalten.',
            attributes: [
                'Snapshot-Referenz' => (string)$record['snapshotId'],
                'Format' => (string)$record['format'],
                'Größe in Byte' => (int)$record['sizeBytes'],
                'Erstellt am' => (string)$record['createdAt'],
                'Datenschutz-REVIEW' => $review['status'],
            ],
        );
    }

    /** @param array<string, scalar|null> $record */
    private function auditEntry(array $record, string $asOf): PersonalDataEntry {
        $review = $this->retentionReview((string)$record['createdAt'], $asOf, $this->config->auditRetentionDays());

        return new PersonalDataEntry(
            categoryId: 'audit_action',
            categoryLabel: 'Protokollierter Matrixzugriff',
            reference: 'permission-matrix:audit:' . (string)$record['id'],
            summary: 'Protokollierte Aktion vom ' . $this->dateLabel((string)$record['createdAt']),
            purpose: 'Nachvollziehbarkeit von Zugriffen und administrativen Handlungen in der Permission-Matrix',
            source: 'Serverseitiges Auditprotokoll einer Aktion unter deiner Nextcloud-Kennung',
            recipientCategories: $this->recipients(),
            retention: $review['description'],
            thirdCountryTransfer: 'Durch die Permission-Matrix sind keine Drittlandübermittlungen vorgesehen.',
            automatedDecision: 'Keine automatisierte Entscheidung.',
            thirdPartyContentNotice: 'Freie Auditdetails werden wegen möglicher Drittpersonen- oder technischer Angaben nicht ausgegeben.',
            attributes: [
                'Aktion' => (string)$record['action'],
                'Export erzeugt' => (bool)$record['exportGenerated'] ? 'ja' : 'nein',
                'Snapshot-Referenz' => $record['snapshotId'] === null ? '—' : (string)$record['snapshotId'],
                'Protokolliert am' => (string)$record['createdAt'],
                'Datenschutz-REVIEW' => $review['status'],
            ],
        );
    }

    /** @return list<string> */
    private function recipients(): array {
        return [
            'Konfigurierte Viewer- und Administrationsgruppen der Permission-Matrix',
            'Nextcloud-Administrator*innen',
        ];
    }

    /** @return array{0: string, 1: int} */
    private function cursorState(?string $cursor): array {
        if ($cursor === null) {
            return [(new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM), 0];
        }

        $padding = (4 - strlen($cursor) % 4) % 4;
        $json = base64_decode(strtr($cursor . str_repeat('=', $padding), '-_', '+/'), true);
        $state = $json === false ? null : json_decode($json, true);
        if (
            !is_array($state)
            || !is_string($state['asOf'] ?? null)
            || !is_int($state['offset'] ?? null)
            || $state['offset'] < 1
            || $state['offset'] > self::MAX_OFFSET
        ) {
            throw new InvalidArgumentException('Invalid Permission Matrix privacy cursor.');
        }
        try {
            $asOf = (new DateTimeImmutable($state['asOf']))->format(DATE_ATOM);
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid Permission Matrix privacy cursor.');
        }

        return [$asOf, $state['offset']];
    }

    private function encodeCursor(string $asOf, int $offset): string {
        return rtrim(strtr(base64_encode(json_encode(
            ['asOf' => $asOf, 'offset' => $offset],
            JSON_THROW_ON_ERROR,
        )), '+/', '-_'), '=');
    }

    private function dateLabel(string $value): string {
        try {
            return (new DateTimeImmutable($value))->format('d.m.Y, H:i');
        } catch (\Throwable) {
            return 'unbekannt';
        }
    }

    /** @return array{status: string, description: string} */
    private function retentionReview(string $createdAt, string $asOf, int $days): array {
        try {
            $reviewDue = (new DateTimeImmutable($createdAt))->modify('+' . $days . ' days');
            $status = $reviewDue <= new DateTimeImmutable($asOf)
                ? 'REVIEW erforderlich'
                : 'Frist noch nicht erreicht';
        } catch (\Throwable) {
            $status = 'REVIEW-Datum nicht bestimmbar';
        }

        return [
            'status' => $status,
            'description' => 'Konfigurierbare Frist: ' . $days . ' Tage. Status: ' . $status . '. Keine automatische Löschung.',
        ];
    }
}
