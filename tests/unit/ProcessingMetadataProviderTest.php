<?php

declare(strict_types=1);

namespace OCP\EventDispatcher {
    if (!class_exists(Event::class)) { class Event {} }
    if (!interface_exists(IEventListener::class)) { interface IEventListener { public function handle(Event $event): void; } }
}

namespace {
    use OCA\FilzmannDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent;
    use OCA\FilzmannPermissionMatrix\Privacy\PermissionMatrixProcessingMetadataProvider;
    use OCA\FilzmannPermissionMatrix\Privacy\PermissionMatrixProcessingMetadataProviderListener;
    use OCP\EventDispatcher\Event;

    if (!class_exists(PermissionMatrixProcessingMetadataProvider::class)) {
        throw new RuntimeException('Der Processing-Metadata-Provider der Berechtigungsmatrix fehlt.');
    }

    $provider = new PermissionMatrixProcessingMetadataProvider();
    $catalog = $provider->catalog();
    $descriptor = $provider->descriptor();

    assertSameValue('filzmann_permission_matrix', $descriptor->appId(), 'Die Provider-App-ID muss stabil sein.');
    assertSameValue('Berechtigungsmatrix', $descriptor->displayName(), 'Der Produktname muss der App-Benennung folgen.');
    assertSameValue('1.0', $descriptor->contractVersion(), 'Der Provider muss den V1-Vertrag deklarieren.');
    assertSameValue('filzmann_permission_matrix', $catalog->appId(), 'Der Katalog muss app-eigen bleiben.');
    assertSameValue([
        'permission_snapshot_and_matrix_management',
        'permission_matrix_export_generation',
        'permission_audit_logging',
        'temporary_admin_full_access',
    ], $catalog->processingIds(), 'Die vier belegten Verarbeitungen müssen stabil veröffentlicht werden.');
    if (array_key_exists('personal_runtime_data', $catalog->toArray())) {
        throw new RuntimeException('Personenbezogene Laufzeitdaten dürfen nicht Teil des Metadatenkatalogs sein.');
    }
    $catalogJson = json_encode($catalog->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    foreach (['Datenschutzbeauftragte', 'nativer Adminstatus ist dafür weder erforderlich noch ausreichend', 'Allow-, Deny- und Manipulationsprüfungen sind automatisiert belegt'] as $policy) {
        assertContainsText($policy, $catalogJson, 'Der Processing-Katalog bildet die umgesetzte DPO-Freigabepolicy nicht korrekt ab.');
    }

    $registration = new RegisterProcessingMetadataProvidersEvent();
    $listener = new PermissionMatrixProcessingMetadataProviderListener($provider);
    $listener->handle(new Event());
    assertSameValue([], $registration->providers(), 'Ein Fremdevent darf keinen Provider registrieren.');
    $listener->handle($registration);
    assertSameValue(['filzmann_permission_matrix'], array_keys($registration->providers()), 'Der Provider muss lazy registriert werden.');

    $application = (string)file_get_contents(dirname(__DIR__, 2) . '/lib/AppInfo/Application.php');
    assertContainsText(
        'registerEventListener(RegisterProcessingMetadataProvidersEvent::class, PermissionMatrixProcessingMetadataProviderListener::class)',
        $application,
        'Die Application muss den optionalen Provider lazy registrieren.',
    );

    echo 'Permission Matrix processing metadata provider tests passed' . PHP_EOL;
}
