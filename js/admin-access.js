(function() {
    const init = () => {
        const shared = window.OCA.FlzPermissionMatrix?.Shared?.ui;
        const api = window.PermissionMatrix?.api;
        if (!shared?.AdminAccessOverlay || !api) return;

        new shared.AdminAccessOverlay({
            overlayId: 'pm-admin-access-overlay',
            triggerId: 'pm-admin-access-warning',
            closeId: 'pm-admin-access-overlay-close',
            formId: 'pm-full-access-form',
            historyId: 'pm-full-access-history',
            statusId: 'pm-full-access-status',
            api: {
                getState: () => api.adminFullAccess(),
                activate: (targetUid, durationMinutes) => api.activateAdminFullAccess(targetUid, durationMinutes),
                revoke: (targetUid) => api.revokeAdminFullAccess(targetUid),
            },
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
