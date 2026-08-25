<?php

declare(strict_types=1);

namespace OCP\EventDispatcher {
    class Event {}
    interface IEventListener { public function handle(Event $event): void; }
}

namespace {
    use OCA\FilzmannDataProtection\PublicApi\V1\DataSubjectRef;
    use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataRequest;
    use OCA\FilzmannDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;
    use OCA\FilzmannPermissionMatrix\Db\PersonalDataProjectionRepository;
    use OCA\FilzmannPermissionMatrix\Privacy\PermissionMatrixPersonalDataProvider;
    use OCA\FilzmannPermissionMatrix\Privacy\PermissionMatrixPersonalDataProviderListener;
    use OCA\FilzmannPermissionMatrix\Service\ConfigService;

    if (!class_exists(PermissionMatrixPersonalDataProvider::class)) {
        throw new RuntimeException('Der Permission-Matrix-PersonalDataProvider fehlt.');
    }

    $records = [
        [
            'source' => 'snapshot',
            'id' => '11',
            'createdAt' => '2026-08-23T12:00:00+00:00',
            'snapshotId' => 'pm-snapshot-own',
            'nextcloudVersion' => '34.0.2',
            'complianceStatus' => 'green',
            'groupCount' => 4,
            'appCount' => 10,
            'objectCount' => 20,
            'memberUids' => ['subject-17', 'third-person'],
        ],
        [
            'source'=>'admin_access','id'=>'15','createdAt'=>'2026-08-22T10:00:00+00:00','targetUid'=>'subject-17','grantedBy'=>'other-admin','startsAt'=>'2026-08-22T10:00:00+00:00','endsAt'=>'2026-08-22T14:00:00+00:00','revokedAt'=>null,'revokedBy'=>null,
        ],
        [
            'source' => 'export',
            'id' => '12',
            'createdAt' => '2026-01-01T11:00:00+00:00',
            'snapshotId' => 'pm-snapshot-own',
            'format' => 'csv',
            'sizeBytes' => 2048,
            'filename' => 'third-person-name.csv',
        ],
        [
            'source' => 'audit',
            'id' => '13',
            'createdAt' => '2025-12-01T10:00:00+00:00',
            'snapshotId' => 'pm-snapshot-own',
            'action' => 'snapshot.view',
            'exportGenerated' => false,
            'details' => ['affectedUser' => 'third-person'],
        ],
        [
            'source' => 'audit',
            'id' => '14',
            'createdAt' => '2025-11-01T09:00:00+00:00',
            'snapshotId' => null,
            'action' => 'config.view',
            'exportGenerated' => false,
        ],
    ];

    $repository = new class($records) extends PersonalDataProjectionRepository {
        public array $requests = [];
        public function __construct(private array $records) {}
        public function collectForSubject(string $uid, int $limit, string $asOf): array {
            $this->requests[] = [$uid, $limit, $asOf];
            return array_slice($this->records, 0, $limit);
        }
    };
    $config = new class extends ConfigService {
        public function __construct() {}
        public function exportMetadataRetentionDays(): int { return 120; }
        public function auditRetentionDays(): int { return 240; }
    };
    $provider = new PermissionMatrixPersonalDataProvider($repository, $config);

    assertSameValue('filzmann_permission_matrix', $provider->descriptor()->appId(), 'Provider-App-ID muss stabil sein.');
    assertSameValue('1.0', $provider->descriptor()->contractVersion(), 'Provider muss den V1-Vertrag deklarieren.');

    $subject = new DataSubjectRef('nextcloud-user', 'subject-17');
    $first = $provider->collect(new PersonalDataRequest($subject, 'de', 'access-report', 2, []));
    assertSameValue('partial', $first->status(), 'Eine begrenzte erste Seite muss als teilweise markiert sein.');
    assertSameValue(2, count($first->entries()), 'Die Seitengrenze muss eingehalten werden.');
    if ($first->nextCursor() === null) throw new RuntimeException('Eine Folgeseite benötigt einen opaken Cursor.');
    assertSameValue('subject-17', $repository->requests[0][0], 'Die Repository-Abfrage muss strikt an das Subject gebunden sein.');

    $firstPayload = json_encode(array_map(static fn($entry): array => $entry->toArray(), $first->entries()), JSON_THROW_ON_ERROR);
    if (str_contains($firstPayload, 'third-person') || str_contains($firstPayload, 'memberUids') || str_contains($firstPayload, 'filename')) {
        throw new RuntimeException('Drittpersonenlisten oder freie Dateinamen gelangen in die Auskunft.');
    }

    $second = $provider->collect(new PersonalDataRequest(
        $subject,
        'de',
        'access-report',
        2,
        ['filzmann_permission_matrix' => $first->nextCursor()],
    ));
    assertSameValue('partial', $second->status(), 'Die zweite Seite muss wegen des verbleibenden Datensatzes teilweise bleiben.');
    assertSameValue(2, count($second->entries()), 'Die zweite Seite muss die nächsten Datensätze liefern.');
    if ($second->nextCursor() === null) throw new RuntimeException('Fünf Datensätze benötigen eine dritte Seite.');
    $third = $provider->collect(new PersonalDataRequest($subject,'de','access-report',2,['filzmann_permission_matrix'=>$second->nextCursor()]));
    assertSameValue('complete',$third->status(),'Die dritte Seite muss vollständig abschließen.');
    assertSameValue(1,count($third->entries()),'Die dritte Seite muss den letzten Datensatz liefern.');
    assertSameValue(null, $third->nextCursor(), 'Die letzte Seite darf keinen weiteren Cursor behaupten.');
    $references = array_map(static fn($entry): string => $entry->toArray()['reference'], [...$first->entries(), ...$second->entries(), ...$third->entries()]);
    assertSameValue(5, count(array_unique($references)), 'Paging darf Datensätze weder doppeln noch auslassen.');

    $allPayload = json_encode(array_map(static fn($entry): array => $entry->toArray(), [...$first->entries(), ...$second->entries(), ...$third->entries()]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    foreach (['pm-snapshot-own', 'snapshot.view', 'config.view', 'csv', '2048', 'Admin-Vollzugriff'] as $contextValue) {
        if (!str_contains($allPayload, $contextValue)) throw new RuntimeException('Erforderlicher eigener Fachkontext fehlt: ' . $contextValue);
    }
    if (str_contains($allPayload, 'third-person') || str_contains($allPayload, 'other-admin')) throw new RuntimeException('Drittpersonenangaben wurden nicht entfernt.');
    foreach (['REVIEW erforderlich', '120 Tage', '240 Tage', 'Keine automatische Löschung'] as $retentionValue) {
        if (!str_contains($allPayload, $retentionValue)) throw new RuntimeException('Konfigurierbarer REVIEW-Hinweis fehlt: ' . $retentionValue);
    }

    $requestsBeforeForeignType = count($repository->requests);
    $foreignType = $provider->collect(new PersonalDataRequest(
        new DataSubjectRef('external-applicant', 'subject-17'),
        'de',
        'access-report',
        20,
        [],
    ));
    assertSameValue('not_applicable', $foreignType->status(), 'Nicht unterstützte Subject-Typen müssen ohne Daten antworten.');
    assertSameValue($requestsBeforeForeignType, count($repository->requests), 'Nicht unterstützte Subject-Typen dürfen keine Datenbankabfrage auslösen.');

    try {
        $provider->collect(new PersonalDataRequest($subject, 'de', 'access-report', 20, ['filzmann_permission_matrix' => 'manipuliert']));
        throw new RuntimeException('Ein manipulierter Cursor wurde akzeptiert.');
    } catch (InvalidArgumentException) {
    }

    $listener = new PermissionMatrixPersonalDataProviderListener($provider);
    $event = new RegisterPersonalDataProvidersEvent();
    $listener->handle($event);
    assertSameValue(['filzmann_permission_matrix'], array_keys($event->providers()), 'Der Provider wird nicht lazy registriert.');

    echo 'Permission Matrix privacy provider tests passed' . PHP_EOL;
}
