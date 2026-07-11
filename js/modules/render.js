(function() {
    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[char]);
    }

    function byId(id) {
        return document.getElementById(id);
    }

    function badge(value) {
        const key = String(value || 'UNKNOWN').toLowerCase().replace(/[^a-z0-9_-]/g, '-');
        return `<span class="pm-badge pm-badge-${esc(key)}">${esc(value)}</span>`;
    }

    function empty(message) {
        return `<p class="pm-empty">${esc(message)}</p>`;
    }

    function renderOverview(snapshot) {
        if (!snapshot) {
            return empty('Noch kein Snapshot vorhanden.');
        }
        const summary = snapshot.summary || {};
        const stats = [
            ['Nextcloud-Version', snapshot.nextcloud_version],
            ['Letzter Scan', snapshot.created_at],
            ['Gruppen', summary.group_count],
            ['Aktivierte Apps', summary.app_count],
            ['Gruppenbeschraenkte Apps', summary.restricted_app_count],
            ['Berechtigungsobjekte', summary.object_count],
            ['Warnungen', summary.warning_count],
            ['Nicht unterstuetzte Apps', summary.unsupported_count]
        ];

        return `
            <div class="pm-compliance">${badge(summary.compliance_status || 'UNKNOWN')}</div>
            <div class="pm-stats">
                ${stats.map(([label, value]) => `<div class="pm-stat"><span>${esc(label)}</span><strong>${esc(value ?? '-')}</strong></div>`).join('')}
            </div>
            <div class="pm-warning-list">${renderWarnings(snapshot)}</div>
        `;
    }

    function renderWarnings(snapshot) {
        const warnings = [...(snapshot.warnings || []), ...(snapshot.unsupported_apps || []).map((app) => `Nicht unterstuetzt: ${app}`)];
        if (!warnings.length) {
            return '';
        }

        return `<h2>Warnungen</h2><ul>${warnings.map((warning) => `<li>${esc(warning)}</li>`).join('')}</ul>`;
    }

    function renderMatrix(snapshot, filters) {
        if (!snapshot) {
            return empty('Keine Matrixdaten vorhanden.');
        }
        const groupFilter = filters.group || '';
        const groups = groupFilter ? [groupFilter] : snapshot.groups;
        const rows = (snapshot.matrix || []).filter((row) => {
            if (filters.app && !String(row.app_id).toLowerCase().includes(filters.app.toLowerCase())) {
                return false;
            }
            if (filters.status && row.status !== filters.status) {
                return false;
            }
            if (filters.text) {
                const haystack = [row.object_type, row.app_id, row.object, row.detail, row.permission_type, row.status].join(' ').toLowerCase();
                if (!haystack.includes(filters.text.toLowerCase())) {
                    return false;
                }
            }
            return true;
        });

        if (!rows.length) {
            return empty('Keine Matrixzeilen fuer diese Filter.');
        }

        return `
            <div class="pm-table-wrap">
                <table class="pm-table">
                    <thead><tr>
                        <th>Objekttyp</th><th>App-ID</th><th>Objekt/Funktion</th><th>Berechtigungsart</th><th>Status</th>
                        ${groups.map((group) => `<th>${esc(group)}</th>`).join('')}
                    </tr></thead>
                    <tbody>
                        ${rows.map((row) => `
                            <tr>
                                <th scope="row">${esc(row.object_type)}</th>
                                <td>${esc(row.app_id)}</td>
                                <td>${esc(row.object)}</td>
                                <td>${esc(row.permission_type)}</td>
                                <td>${badge(row.status)}</td>
                                ${groups.map((group) => `<td class="pm-cell pm-cell-${cellClass(row.cells[group])}">${esc(row.cells[group] ?? '-')}</td>`).join('')}
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderApps(snapshot) {
        if (!snapshot) {
            return empty('Keine Appdaten vorhanden.');
        }
        return `
            <div class="pm-list">
                ${(snapshot.apps || []).map((app) => `
                    <article class="pm-list-item">
                        <h2>${esc(app.display_name)}</h2>
                        <dl>
                            <dt>App-ID</dt><dd>${esc(app.app_id)}</dd>
                            <dt>Version</dt><dd>${esc(app.version)}</dd>
                            <dt>Quelle</dt><dd>${esc(app.source)}</dd>
                            <dt>Gruppenbeschraenkt</dt><dd>${esc(app.restricted ? 'ja' : 'nein')}</dd>
                            <dt>Gruppen</dt><dd>${esc(app.groups && app.groups.length ? app.groups.join(', ') : 'global')}</dd>
                        </dl>
                    </article>
                `).join('')}
            </div>
        `;
    }

    function renderGroups(snapshot) {
        if (!snapshot) {
            return empty('Keine Gruppendaten vorhanden.');
        }
        return `
            <div class="pm-list">
                ${(snapshot.groups || []).map((group) => {
                    const active = (snapshot.matrix || []).filter((row) => row.cells && !['-', 'n/a'].includes(String(row.cells[group] ?? '-')));
                    const unknown = active.filter((row) => ['?', 'UNKNOWN', 'UNSUPPORTED'].includes(String(row.cells[group] ?? '')) || ['UNKNOWN', 'UNSUPPORTED'].includes(row.status));
                    return `
                        <article class="pm-list-item">
                            <h2>${esc(group)}</h2>
                            <dl>
                                <dt>Relevante Rechte</dt><dd>${esc(active.length)}</dd>
                                <dt>Unklare Bereiche</dt><dd>${esc(unknown.length)}</dd>
                            </dl>
                        </article>
                    `;
                }).join('')}
            </div>
        `;
    }

    function renderDiffs(snapshot) {
        if (!snapshot) {
            return empty('Keine Abweichungsdaten vorhanden.');
        }
        const diffs = snapshot.diff_to_baseline || [];
        if (!diffs.length) {
            return empty('Keine Abweichungen.');
        }

        return `
            <div class="pm-diff-list">
                ${diffs.map((diff) => `
                    <article class="pm-diff pm-diff-${esc(diff.severity || 'info')}">
                        ${badge(diff.type || 'PERMISSION_CHANGED')}
                        <p>${esc(diff.message || '')}</p>
                        <small>${esc(diff.group || '')} ${esc(diff.old || '')} ${esc(diff.new ? '-> ' + diff.new : '')}</small>
                    </article>
                `).join('')}
            </div>
        `;
    }

    function renderSnapshots(items, baselineId, canManage) {
        if (!items || !items.length) {
            return empty('Keine Snapshots vorhanden.');
        }
        return `
            <div class="pm-table-wrap">
                <table class="pm-table">
                    <thead><tr><th>Snapshot</th><th>Stand</th><th>Status</th><th>Objekte</th><th>Warnungen</th><th></th></tr></thead>
                    <tbody>
                        ${items.map((item) => `
                            <tr>
                                <th scope="row">${esc(item.snapshot_id)}${item.snapshot_id === baselineId ? ' ' + badge('BASELINE') : ''}</th>
                                <td>${esc(item.created_at)}</td>
                                <td>${badge(item.compliance_status)}</td>
                                <td>${esc(item.object_count)}</td>
                                <td>${esc(item.warning_count)}</td>
                                <td>${canManage ? `<button type="button" data-baseline-id="${esc(item.snapshot_id)}">Baseline</button>` : ''}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function fillFilters(snapshot) {
        if (!snapshot) {
            return;
        }
        const groupSelect = byId('pm-filter-group');
        const statusSelect = byId('pm-filter-status');
        const statuses = Array.from(new Set((snapshot.matrix || []).map((row) => row.status))).sort();
        groupSelect.innerHTML = '<option value="">Alle</option>' + (snapshot.groups || []).map((group) => `<option value="${esc(group)}">${esc(group)}</option>`).join('');
        statusSelect.innerHTML = '<option value="">Alle</option>' + statuses.map((status) => `<option value="${esc(status)}">${esc(status)}</option>`).join('');
    }

    function cellClass(value) {
        const raw = String(value || 'none').toLowerCase();
        if (raw === '?') {
            return 'unknown';
        }
        if (raw === '-') {
            return 'none';
        }
        if (raw === 'n/a') {
            return 'na';
        }

        return raw.replace(/[^a-z0-9_-]/g, '-');
    }

    window.PermissionMatrix = window.PermissionMatrix || {};
    window.PermissionMatrix.render = {
        byId,
        esc,
        badge,
        renderOverview,
        renderMatrix,
        renderApps,
        renderGroups,
        renderDiffs,
        renderSnapshots,
        fillFilters
    };
})();
