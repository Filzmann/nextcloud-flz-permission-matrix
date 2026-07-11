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

    function groupCountLabel(count) {
        return `${count} ${count === 1 ? 'Gruppe' : 'Gruppen'}`;
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
            ['Gruppenfamilien', summary.group_family_count],
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
        const availableColumns = groupColumns(snapshot, filters.groupMode || 'summary');
        const groups = groupFilter
            ? availableColumns.filter((column) => column.key === groupFilter)
            : availableColumns;
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
            <div class="pm-table-wrap" tabindex="0" aria-label="Berechtigungsmatrix, horizontal und vertikal scrollbar">
                <table class="pm-table">
                    <thead><tr>
                        <th scope="col">Objekttyp</th><th scope="col">App-ID</th><th scope="col">Objekt/Funktion</th><th scope="col">Berechtigungsart</th><th scope="col">Status</th>
                        ${groups.map((group) => `<th scope="col">${esc(group.label)}${group.type === 'family' ? `<small> ${groupCountLabel(group.count)}</small>` : ''}</th>`).join('')}
                    </tr></thead>
                    <tbody>
                        ${rows.map((row) => `
                            <tr>
                                <th scope="row">${esc(row.object_type)}</th>
                                <td>${esc(row.app_id)}</td>
                                <td>${esc(row.object)}</td>
                                <td>${esc(row.permission_type)}</td>
                                <td>${badge(row.status)}</td>
                                ${groups.map((group) => {
                                    const cell = aggregateCell(row.cells || {}, group);
                                    const accessibleValue = group.type === 'family'
                                        ? `${group.label}: ${cell.value}. Einzelwerte: ${cell.title}`
                                        : `${group.label}: ${cell.value}`;
                                    return `<td class="pm-cell pm-cell-${cell.className}" title="${esc(cell.title)}" aria-label="${esc(accessibleValue)}">${esc(cell.value)}</td>`;
                                }).join('')}
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
                ${groupColumns(snapshot, 'summary').map((entry) => {
                    const active = (snapshot.matrix || []).filter((row) => {
                        const cell = aggregateCell(row.cells || {}, entry);
                        return !['-', 'n/a'].includes(cell.value);
                    });
                    const unknown = active.filter((row) => {
                        const cell = aggregateCell(row.cells || {}, entry);
                        return ['unknown', 'mixed'].includes(cell.className) || ['UNKNOWN', 'UNSUPPORTED'].includes(row.status);
                    });
                    return `
                        <article class="pm-list-item">
                            <h2>${esc(entry.label)}</h2>
                            <dl>
                                <dt>Typ</dt><dd>${entry.type === 'family' ? 'Gruppenfamilie' : 'Einzelgruppe'}</dd>
                                <dt>Enthaltene Gruppen</dt><dd>${esc(entry.count)}</dd>
                                <dt>Relevante Rechte</dt><dd>${esc(active.length)}</dd>
                                <dt>Unklare Bereiche</dt><dd>${esc(unknown.length)}</dd>
                            </dl>
                            ${entry.type === 'family' ? `<details><summary>Rohgruppen anzeigen</summary><ul>${entry.groups.map((group) => `<li>${esc(group)}</li>`).join('')}</ul></details>` : ''}
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
            <div class="pm-table-wrap" tabindex="0" aria-label="Snapshotliste, horizontal und vertikal scrollbar">
                <table class="pm-table">
                    <thead><tr><th scope="col">Snapshot</th><th scope="col">Stand</th><th scope="col">Status</th><th scope="col">Objekte</th><th scope="col">Warnungen</th><th scope="col">Aktion</th></tr></thead>
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

    function fillFilters(snapshot, mode = 'summary') {
        if (!snapshot) {
            return;
        }
        const groupSelect = byId('pm-filter-group');
        const statusSelect = byId('pm-filter-status');
        const selectedStatus = statusSelect.value;
        const statuses = Array.from(new Set((snapshot.matrix || []).map((row) => row.status))).sort();
        groupSelect.innerHTML = '<option value="">Alle</option>' + groupColumns(snapshot, mode).map((group) => `<option value="${esc(group.key)}">${esc(group.label)}${group.type === 'family' ? ` (${group.count})` : ''}</option>`).join('');
        statusSelect.innerHTML = '<option value="">Alle</option>' + statuses.map((status) => `<option value="${esc(status)}">${esc(status)}</option>`).join('');
        if (statuses.includes(selectedStatus)) {
            statusSelect.value = selectedStatus;
        }
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

    /**
     * Zweck: Uebersetzt Rohgruppen oder den serverseitigen Gruppenkatalog in darstellbare Spalten.
     *
     * Zusammenspiel:
     * - GroupCatalogService erzeugt snapshot.group_catalog; Matrix, Gruppenansicht und Filter
     *   verwenden hier dieselbe normalisierte Spaltenstruktur.
     */
    function groupColumns(snapshot, mode = 'summary') {
        if (mode === 'summary' && Array.isArray(snapshot.group_catalog) && snapshot.group_catalog.length > 0) {
            return snapshot.group_catalog.map((entry) => ({
                key: String(entry.key),
                label: String(entry.label || entry.key),
                type: entry.type === 'family' ? 'family' : 'group',
                groups: Array.isArray(entry.groups) ? entry.groups.map(String) : [],
                count: Number(entry.count || (entry.groups || []).length || 1)
            }));
        }
        return (snapshot.groups || []).map((group) => ({
            key: String(group), label: String(group), type: 'group', groups: [String(group)], count: 1
        }));
    }

    /**
     * Zweck: Verdichtet Rohzellen einer Gruppenfamilie, ohne Teilbelegungen zu verbergen.
     *
     * Spiegelung:
     * - PHP: ExportService::aggregateCell() bildet denselben Vertrag fuer Markdown und HTML ab.
     *
     * Vertrag:
     * - Einheitliche Werte bleiben unveraendert, Teilbelegungen tragen n/m und Konflikte werden
     *   als gemischt markiert; title enthaelt weiterhin jeden Rohwert.
     */
    function aggregateCell(cells, column) {
        const values = column.groups.map((group) => String(cells[group] ?? '-'));
        const unique = Array.from(new Set(values));
        const title = column.groups.map((group, index) => `${group}: ${values[index]}`).join('; ');
        if (unique.length === 1) {
            return { value: unique[0], className: cellClass(unique[0]), title };
        }
        const relevant = values.filter((value) => !['-', 'n/a'].includes(value));
        if (relevant.length === 0) {
            return { value: '-', className: 'none', title };
        }
        const relevantUnique = Array.from(new Set(relevant));
        if (relevantUnique.length === 1) {
            return {
                value: `${relevantUnique[0]} (${relevant.length}/${values.length})`,
                className: cellClass(relevantUnique[0]),
                title
            };
        }
        return { value: `gemischt (${relevant.length}/${values.length})`, className: 'mixed', title };
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
        fillFilters,
        groupColumns,
        aggregateCell
    };
})();
