(function() {
    const api = window.PermissionMatrix.api;
    const render = window.PermissionMatrix.render;
    const state = {
        snapshot: null,
        snapshots: [],
        baseline: '',
        canManage: false,
        filters: {
            app: '',
            group: '',
            status: '',
            text: ''
        }
    };

    function notice(message, type = 'info') {
        const box = render.byId('pm-notice');
        box.textContent = message;
        box.className = `pm-notice pm-notice-${type}`;
        box.hidden = !message;
    }

    async function init() {
        const root = render.byId('permission-matrix-app');
        state.canManage = root.dataset.canManage === '1';
        bindEvents();
        await loadState();
    }

    function bindEvents() {
        document.querySelectorAll('.pm-tabs button').forEach((button) => {
            button.addEventListener('click', () => activateTab(button.dataset.tab));
        });
        document.querySelectorAll('[data-export]').forEach((button) => {
            button.addEventListener('click', () => {
                window.location.href = api.exportUrl(button.dataset.export, state.snapshot ? state.snapshot.snapshot_id : null);
            });
        });
        const scan = render.byId('pm-run-scan');
        if (scan) {
            scan.addEventListener('click', runScan);
        }
        const baseline = render.byId('pm-set-baseline');
        if (baseline) {
            baseline.addEventListener('click', setLatestBaseline);
        }
        ['pm-filter-app', 'pm-filter-group', 'pm-filter-status', 'pm-filter-text'].forEach((id) => {
            const element = render.byId(id);
            element.addEventListener('input', updateFilters);
            element.addEventListener('change', updateFilters);
        });
        render.byId('pm-snapshots').addEventListener('click', async (event) => {
            const button = event.target.closest('[data-baseline-id]');
            if (!button) {
                return;
            }
            await setBaseline(button.dataset.baselineId);
        });
    }

    async function loadState() {
        try {
            const data = await api.state();
            state.snapshot = data.snapshot;
            state.baseline = data.baseline_snapshot || '';
            state.canManage = Boolean(data.can_manage);
            const snapshots = await api.snapshots();
            state.snapshots = snapshots.snapshots || [];
            render.fillFilters(state.snapshot);
            draw();
            notice('');
        } catch (error) {
            notice(error.message || 'Daten konnten nicht geladen werden.', 'error');
        }
    }

    async function runScan() {
        notice('Scan laeuft ...');
        try {
            const data = await api.scan();
            state.snapshot = data.snapshot;
            const snapshots = await api.snapshots();
            state.snapshots = snapshots.snapshots || [];
            render.fillFilters(state.snapshot);
            draw();
            notice('Scan abgeschlossen.', 'success');
        } catch (error) {
            notice(error.message || 'Scan fehlgeschlagen.', 'error');
        }
    }

    async function setLatestBaseline() {
        if (!state.snapshot) {
            notice('Kein Snapshot vorhanden.', 'error');
            return;
        }
        await setBaseline(state.snapshot.snapshot_id);
    }

    async function setBaseline(snapshotId) {
        try {
            await api.setBaseline(snapshotId);
            state.baseline = snapshotId;
            const snapshots = await api.snapshots();
            state.snapshots = snapshots.snapshots || [];
            draw();
            notice('Baseline gesetzt.', 'success');
        } catch (error) {
            notice(error.message || 'Baseline konnte nicht gesetzt werden.', 'error');
        }
    }

    function updateFilters() {
        state.filters.app = render.byId('pm-filter-app').value.trim();
        state.filters.group = render.byId('pm-filter-group').value;
        state.filters.status = render.byId('pm-filter-status').value;
        state.filters.text = render.byId('pm-filter-text').value.trim();
        render.byId('pm-matrix').innerHTML = render.renderMatrix(state.snapshot, state.filters);
    }

    function draw() {
        const summary = state.snapshot ? state.snapshot.summary || {} : {};
        render.byId('pm-status-line').innerHTML = state.snapshot
            ? `Scan ${render.esc(state.snapshot.created_at)} ${render.badge(summary.compliance_status || 'UNKNOWN')}`
            : 'Noch kein Scan';
        render.byId('pm-overview').innerHTML = render.renderOverview(state.snapshot);
        render.byId('pm-matrix').innerHTML = render.renderMatrix(state.snapshot, state.filters);
        render.byId('pm-apps').innerHTML = render.renderApps(state.snapshot);
        render.byId('pm-groups').innerHTML = render.renderGroups(state.snapshot);
        render.byId('pm-diffs').innerHTML = render.renderDiffs(state.snapshot);
        render.byId('pm-snapshots').innerHTML = render.renderSnapshots(state.snapshots, state.baseline, state.canManage);
    }

    function activateTab(tab) {
        document.querySelectorAll('.pm-tabs button').forEach((button) => button.classList.toggle('active', button.dataset.tab === tab));
        document.querySelectorAll('.pm-view').forEach((view) => view.classList.toggle('active', view.id === `pm-view-${tab}`));
    }

    document.addEventListener('DOMContentLoaded', init);
})();
