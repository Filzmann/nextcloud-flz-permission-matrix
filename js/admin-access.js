(function() {
    const api = window.PermissionMatrix ? window.PermissionMatrix.api : null;

    function byId(id) {
        return document.getElementById(id);
    }

    function init() {
        const form = byId('pm-full-access-form');
        const history = byId('pm-full-access-history');
        const status = byId('pm-full-access-status');
        if (!form || !history || !status || !api) {
            return;
        }

        const show = (message, type = 'info') => {
            status.textContent = message;
            status.setAttribute('role', type === 'error' ? 'alert' : 'status');
        };
        const date = value => value ? new Date(value).toLocaleString() : '—';
        const load = async () => {
            try {
                const state = await api.adminFullAccess();
                history.replaceChildren();
                if (!state.history || state.history.length === 0) {
                    const row = document.createElement('tr');
                    const cell = document.createElement('td');
                    cell.colSpan = 6;
                    cell.textContent = 'Noch keine Freigaben protokolliert.';
                    row.append(cell);
                    history.append(row);
                    return;
                }
                state.history.forEach(grant => {
                    const row = document.createElement('tr');
                    const active = !grant.revokedAt && new Date(grant.startsAt).getTime() <= Date.now() && new Date(grant.endsAt).getTime() > Date.now();
                    [grant.targetUid, grant.grantedBy, date(grant.startsAt), date(grant.endsAt), grant.revokedAt ? date(grant.revokedAt) : (active ? 'Aktiv' : 'Planmäßig beendet')].forEach(value => {
                        const cell = document.createElement('td');
                        cell.textContent = value;
                        row.append(cell);
                    });
                    const action = document.createElement('td');
                    if (active) {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.textContent = 'Widerrufen';
                        button.dataset.revokeUid = grant.targetUid;
                        action.append(button);
                    }
                    row.append(action);
                    history.append(row);
                });
            } catch (error) {
                show(error.message || 'Die Vollzugriffshistorie konnte nicht geladen werden.', 'error');
            }
        };

        form.addEventListener('submit', async event => {
            event.preventDefault();
            const fields = new FormData(form);
            if (fields.get('enabled') !== 'on') {
                return;
            }
            try {
                await api.activateAdminFullAccess(String(fields.get('targetUid') || '').trim(), Number(fields.get('durationMinutes')));
                form.elements.enabled.checked = false;
                show('Der zeitlich begrenzte Vollzugriff wurde aktiviert.');
                await load();
            } catch (error) {
                show(error.message || 'Der Vollzugriff konnte nicht aktiviert werden.', 'error');
            }
        });
        history.addEventListener('click', async event => {
            const button = event.target.closest('button[data-revoke-uid]');
            if (!button) {
                return;
            }
            button.disabled = true;
            try {
                await api.revokeAdminFullAccess(button.dataset.revokeUid);
                show('Der Vollzugriff wurde widerrufen.');
                await load();
            } catch (error) {
                button.disabled = false;
                show(error.message || 'Der Vollzugriff konnte nicht widerrufen werden.', 'error');
            }
        });
        load();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
