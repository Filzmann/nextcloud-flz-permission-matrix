(function() {
    const api = window.PermissionMatrix.api;
    const render = window.PermissionMatrix.render;

    function emptyFilters() {
        return {
            app: '',
            group: '',
            groupMode: 'teams',
            status: '',
            coverage: '',
            text: '',
            collapsedApps: []
        };
    }

    function nextTabIndex(current, key, count) {
        if (count < 1) return null;
        if (key === 'Home') return 0;
        if (key === 'End') return count - 1;
        if (key === 'ArrowRight' || key === 'ArrowDown') return (current + 1) % count;
        if (key === 'ArrowLeft' || key === 'ArrowUp') return (current - 1 + count) % count;
        return null;
    }

    const state = {
        snapshot: null,
        snapshots: [],
        baseline: '',
        canManage: false,
        exportFormats: ['md', 'csv', 'json', 'html'],
        filters: emptyFilters()
    };

    function notice(message, type = 'info') {
        const box = render.byId('pm-notice');
        box.textContent = message;
        box.className = `pm-notice pm-notice-${type}`;
        box.setAttribute('role', type === 'error' ? 'alert' : 'status');
        box.hidden = !message;
    }

    async function init() {
        const root = render.byId('permission-matrix-app');
        state.canManage = root.dataset.canManage === '1';
        bindEvents();
        await loadState();
    }

    function bindEvents() {
        const tabs = document.querySelector('.pm-tabs');
        tabs.addEventListener('keydown', handleTabKeydown);
        document.querySelectorAll('.pm-tabs [role="tab"]').forEach((button) => {
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
        ['pm-filter-app', 'pm-filter-group', 'pm-filter-status', 'pm-filter-coverage', 'pm-filter-text'].forEach((id) => {
            const element = render.byId(id);
            element.addEventListener('input', updateFilters);
            element.addEventListener('change', updateFilters);
        });
        render.byId('pm-group-mode').addEventListener('change', () => {
            state.filters.groupMode = render.byId('pm-group-mode').value;
            state.filters.group = '';
            render.fillFilters(state.snapshot, state.filters.groupMode);
            updateFilters();
        });
        render.byId('pm-reset-filters').addEventListener('click', resetFilters);
        render.byId('pm-matrix').addEventListener('click', handleMatrixClick);
        render.byId('pm-snapshots').addEventListener('click', async (event) => {
            const button = event.target.closest('[data-baseline-id]');
            if (!button) {
                return;
            }
            await setBaseline(button.dataset.baselineId);
        });
    }

    function handleTabKeydown(event) {
        const buttons = Array.from(document.querySelectorAll('.pm-tabs [role="tab"]'));
        const current = buttons.indexOf(event.target);
        if (current < 0) return;
        const targetIndex = nextTabIndex(current, event.key, buttons.length);
        if (targetIndex === null) return;
        event.preventDefault();
        const target = buttons[targetIndex];
        activateTab(target.dataset.tab);
        target.focus();
    }

    async function loadState() {
        try {
            const data = await api.state();
            state.snapshot = data.snapshot;
            state.baseline = data.baseline_snapshot || '';
            state.canManage = Boolean(data.can_manage);
            state.exportFormats = Array.isArray(data.export_formats) ? data.export_formats : state.exportFormats;
            const snapshots = await api.snapshots();
            state.snapshots = snapshots.snapshots || [];
            render.fillFilters(state.snapshot, state.filters.groupMode);
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
            render.fillFilters(state.snapshot, state.filters.groupMode);
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
        state.filters.groupMode = render.byId('pm-group-mode').value;
        state.filters.status = render.byId('pm-filter-status').value;
        state.filters.coverage = render.byId('pm-filter-coverage').value;
        state.filters.text = render.byId('pm-filter-text').value.trim();
        render.byId('pm-matrix').innerHTML = render.renderMatrix(state.snapshot, state.filters);
    }

    function resetFilters() {
        state.filters = emptyFilters();
        render.byId('pm-group-mode').value = 'teams';
        render.byId('pm-filter-app').value = '';
        render.byId('pm-filter-group').value = '';
        render.byId('pm-filter-status').value = '';
        render.byId('pm-filter-coverage').value = '';
        render.byId('pm-filter-text').value = '';
        render.fillFilters(state.snapshot, 'teams');
        updateFilters();
    }

    function handleMatrixClick(event) {
        const appToggle = event.target.closest('[data-app-toggle]');
        if (appToggle) {
            const appId = appToggle.dataset.appToggle;
            const collapsed = new Set(state.filters.collapsedApps);
            collapsed.has(appId) ? collapsed.delete(appId) : collapsed.add(appId);
            state.filters.collapsedApps = Array.from(collapsed);
            updateFilters();
            return;
        }

        const groupFocus = event.target.closest('[data-group-focus]');
        if (groupFocus) {
            const groupSelect = render.byId('pm-filter-group');
            groupSelect.value = state.filters.group === groupFocus.dataset.groupFocus ? '' : groupFocus.dataset.groupFocus;
            updateFilters();
            return;
        }

        const action = event.target.closest('[data-matrix-action]');
        if (!action) {
            return;
        }
        state.filters.collapsedApps = action.dataset.matrixAction === 'collapse-all'
            ? Array.from(render.byId('pm-matrix').querySelectorAll('[data-app-toggle]')).map((button) => button.dataset.appToggle)
            : [];
        updateFilters();
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
        document.querySelectorAll('[data-export]').forEach((button) => {
            button.hidden = !state.exportFormats.includes(button.dataset.export);
        });
    }

    function activateTab(tab) {
        document.querySelectorAll('.pm-tabs [role="tab"]').forEach((button) => {
            const active = button.dataset.tab === tab;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
            button.setAttribute('tabindex', active ? '0' : '-1');
        });
        document.querySelectorAll('.pm-view[role="tabpanel"]').forEach((view) => {
            const active = view.id === `pm-view-${tab}`;
            view.classList.toggle('active', active);
            view.hidden = !active;
        });
    }

    window.PermissionMatrix.navigation = { nextTabIndex, emptyFilters };
    document.addEventListener('DOMContentLoaded', init);
})();
