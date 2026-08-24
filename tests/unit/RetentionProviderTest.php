<?php

declare(strict_types=1);

namespace OCP\EventDispatcher {
    if (!class_exists(Event::class)) { class Event {} }
    if (!interface_exists(IEventListener::class)) { interface IEventListener { public function handle(Event $event): void; } }
}

namespace {
    use OCA\FilzmannDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent;
    use OCA\FilzmannDataProtection\PublicApi\V1\RetentionPreviewRequest;
    use OCA\FilzmannPermissionMatrix\Db\RetentionReviewRepository;
    use OCA\FilzmannPermissionMatrix\Privacy\PermissionMatrixRetentionProvider;
    use OCA\FilzmannPermissionMatrix\Privacy\PermissionMatrixRetentionProviderListener;
    use OCA\FilzmannPermissionMatrix\Service\ConfigService;

    if (!class_exists(PermissionMatrixRetentionProvider::class)) throw new RuntimeException('Der REVIEW-Provider fehlt.');

    $repository = new class extends RetentionReviewRepository {
        public array $requests = [];
        public function __construct() {}
        public function collectDue(string $source, string $cutoff, int $limit): array {
            $this->requests[] = [$source, $cutoff, $limit];
            return [
                ['id' => '17', 'createdAt' => '2026-01-01T10:00:00+00:00', 'creatorUid' => 'must-not-escape', 'details' => 'must-not-escape'],
                ['id' => '18', 'createdAt' => '2025-12-01T10:00:00+00:00'],
            ];
        }
    };
    $config = new class extends ConfigService {
        public function __construct() {}
        public function exportMetadataRetentionDays(): int { return 120; }
        public function auditRetentionDays(): int { return 240; }
    };
    $provider = new PermissionMatrixRetentionProvider($repository, $config);

    assertSameValue('filzmann_permission_matrix', $provider->descriptor()->appId(), 'Die Provider-ID ist nicht stabil.');
    assertSameValue(['export-metadata-review', 'audit-log-review'], array_map(static fn($policy): string => $policy->policyId(), $provider->policies()), 'Die beiden REVIEW-Policies fehlen.');
    assertSameValue([120, 240], array_map(static fn($policy): int => $policy->durationDays(), $provider->policies()), 'Konfigurierte Fristen werden nicht als kanonische Policies gemeldet.');
    assertSameValue(false, method_exists($provider, 'execute'), 'Der Provider darf keinen Ausführungspfad anbieten.');

    $page = $provider->preview(new RetentionPreviewRequest('export-metadata-review', '2026-08-23T12:00:00+00:00', 20));
    assertSameValue('complete', $page->status(), 'Die kleine REVIEW-Seite muss vollständig sein.');
    assertSameValue('export', $repository->requests[0][0], 'Die Exportpolicy fragt die falsche Datenklasse ab.');
    assertContainsText('2026-04-25', $repository->requests[0][1], 'Der Cutoff wird nicht aus den konfigurierten 120 Tagen berechnet.');
    $payload = json_encode(array_map(static fn($candidate): array => $candidate->toArray(), $page->candidates()), JSON_THROW_ON_ERROR);
    foreach (['must-not-escape', 'creatorUid', 'details'] as $forbidden) {
        if (str_contains($payload, $forbidden)) throw new RuntimeException('Nicht erforderliche Personen- oder Detaildaten sind ausgetreten.');
    }
    assertContainsText('permission-matrix:export:17', $payload, 'Die lokale technische Referenz fehlt.');
    assertContainsText('REVIEW', $payload, 'Die nicht-destruktive Maßnahme fehlt.');

    try {
        $provider->preview(new RetentionPreviewRequest('manipulated-policy', '2026-08-23T12:00:00+00:00', 20));
        throw new RuntimeException('Eine unbekannte Policy wurde akzeptiert.');
    } catch (InvalidArgumentException) {
    }
    assertSameValue(1, count($repository->requests), 'Eine unbekannte Policy hat eine Datenbankabfrage ausgelöst.');

    $event = new RegisterRetentionProvidersEvent();
    (new PermissionMatrixRetentionProviderListener($provider))->handle($event);
    assertSameValue(['filzmann_permission_matrix'], array_keys($event->providers()), 'Der REVIEW-Provider wird nicht lazy registriert.');

    echo 'Permission Matrix retention provider tests passed' . PHP_EOL;
}
