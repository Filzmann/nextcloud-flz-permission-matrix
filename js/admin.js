(function() {
    const api = window.PermissionMatrix ? window.PermissionMatrix.api : null;

    function byId(id) {
        return document.getElementById(id);
    }

    function notice(message, type = 'info') {
        const box = byId('pm-admin-notice');
        box.textContent = message;
        box.className = `pm-notice pm-notice-${type}`;
        box.hidden = !message;
    }

    function formPayload(form) {
        const data = new FormData(form);
        return new URLSearchParams({
            viewer_groups: data.get('viewer_groups') || '',
            admin_groups: data.get('admin_groups') || '',
            scan_interval: data.get('scan_interval') || 'daily',
            strict_mode: data.has('strict_mode') ? '1' : '0',
            include_users: data.has('include_users') ? '1' : '0',
            redact_paths: data.has('redact_paths') ? '1' : '0',
            include_share_metadata: data.has('include_share_metadata') ? '1' : '0',
            export_formats: data.get('export_formats') || 'md,csv,json,html',
            retention: data.get('retention') || '50'
        });
    }

    function init() {
        const form = byId('pm-admin-form');
        if (!form || !api) {
            return;
        }
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                await api.saveConfig(formPayload(form));
                notice('Gespeichert.', 'success');
            } catch (error) {
                notice(error.message || 'Speichern fehlgeschlagen.', 'error');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
