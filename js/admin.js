(function() {
    const api = window.PermissionMatrix ? window.PermissionMatrix.api : null;

    function byId(id) {
        return document.getElementById(id);
    }

    function notice(message, type = 'info') {
        const box = byId('pm-admin-notice');
        box.textContent = message;
        box.className = `pm-notice pm-notice-${type}`;
        box.setAttribute('role', type === 'error' ? 'alert' : 'status');
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
            retention: data.get('retention') || '50',
            export_metadata_retention_days: data.get('export_metadata_retention_days') || '180',
            audit_retention_days: data.get('audit_retention_days') || '180'
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
        initFullAccess();
    }

    function initFullAccess() {
        const form=byId('pm-full-access-form');const history=byId('pm-full-access-history');const status=byId('pm-full-access-status');if(!form||!history||!status||!api)return;const show=message=>{status.textContent=message;};const date=value=>value?new Date(value).toLocaleString():'—';
        const load=async()=>{try{const state=await api.adminFullAccess();history.replaceChildren();if(!state.history?.length){const row=document.createElement('tr');const cell=document.createElement('td');cell.colSpan=6;cell.textContent='Noch keine Freigaben protokolliert.';row.append(cell);history.append(row);return;}state.history.forEach(grant=>{const row=document.createElement('tr');const active=!grant.revokedAt&&new Date(grant.startsAt).getTime()<=Date.now()&&new Date(grant.endsAt).getTime()>Date.now();[grant.targetUid,grant.grantedBy,date(grant.startsAt),date(grant.endsAt),grant.revokedAt?date(grant.revokedAt):(active?'Aktiv':'Planmäßig beendet')].forEach(value=>{const cell=document.createElement('td');cell.textContent=value;row.append(cell);});const action=document.createElement('td');if(active){const button=document.createElement('button');button.type='button';button.textContent='Widerrufen';button.dataset.revokeUid=grant.targetUid;action.append(button);}row.append(action);history.append(row);});}catch(error){show(error.message||'Die Vollzugriffshistorie konnte nicht geladen werden.');}};
        form.addEventListener('submit',async event=>{event.preventDefault();const fields=new FormData(form);if(fields.get('enabled')!=='on')return;try{await api.activateAdminFullAccess(String(fields.get('targetUid')||'').trim(),Number(fields.get('durationMinutes')));form.elements.enabled.checked=false;show('Der zeitlich begrenzte Vollzugriff wurde aktiviert.');await load();}catch(error){show(error.message||'Der Vollzugriff konnte nicht aktiviert werden.');}});
        history.addEventListener('click',async event=>{const button=event.target.closest('button[data-revoke-uid]');if(!button)return;button.disabled=true;try{await api.revokeAdminFullAccess(button.dataset.revokeUid);show('Der Vollzugriff wurde widerrufen.');await load();}catch(error){button.disabled=false;show(error.message||'Der Vollzugriff konnte nicht widerrufen werden.');}});load();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
