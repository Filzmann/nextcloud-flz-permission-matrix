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

    function permissionCountLabel(count) {
        return `${count} ${count === 1 ? 'Berechtigung' : 'Berechtigungen'}`;
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

    /**
     * Zweck: Trennt die Abdeckung app-spezifischer Detailrechte vom Status einzelner Matrixzeilen.
     *
     * Vertrag:
     * - AVAILABILITY_ONLY belegt nur die bekannte App-Sichtbarkeit und ist kein Detailadapter.
     */
    function adapterCoverage(snapshot, appId) {
        const statuses = (snapshot.adapter_status || [])
            .filter((entry) => String(entry.app_id) === String(appId) && entry.status !== 'AVAILABILITY_ONLY')
            .map((entry) => String(entry.status || 'UNKNOWN'));
        if (statuses.includes('IMPLEMENTED')) return 'IMPLEMENTED';
        if (statuses.includes('PARTIAL')) return 'PARTIAL';
        if (statuses.includes('UNSUPPORTED')) return 'UNSUPPORTED';
        return 'UNKNOWN';
    }

    /**
     * Zweck: Zeigt die technische Zugriffsbedingung mit Quelle und Aussagesicherheit.
     *
     * Spiegelung:
     * - PHP: AccessRule::toArray() liefert condition_text, source und confidence.
     */
    function renderAccessRules(row) {
        const rules = Array.isArray(row.access_rules) ? row.access_rules : [];
        if (!rules.length) {
            return esc(row.detail || '-');
        }

        return `${esc(row.detail || '-')}<ul class="pm-rule-list">${rules.map((rule) => `
            <li><strong>Bedingung:</strong> ${esc(rule.condition_text || '')}
                <small>${esc(rule.effect || 'allow')} · ${esc(rule.source || row.source || '')} · ${esc(rule.confidence || row.confidence || 'low')}</small>
            </li>`).join('')}</ul>`;
    }

    /**
     * Zweck: Ordnet gefilterte Berechtigungszeilen strikt nach App und innerhalb der App nach Fachbegriff.
     *
     * Vertrag:
     * - Jede Matrixzeile erscheint genau in einem App-Abschnitt; die App-Anzeige stammt bevorzugt
     *   aus dem Snapshot-Inventar und faellt sonst auf die App-ID zurueck.
     */
    function matrixSections(snapshot, filters) {
        const labels = new Map((snapshot.apps || []).map((app) => [String(app.app_id), String(app.display_name || app.app_id)]));
        const appFilter = String(filters.app || '').toLowerCase();
        const textFilter = String(filters.text || '').toLowerCase();
        const rows = (snapshot.matrix || []).filter((row) => {
            const appId = String(row.app_id || '');
            const appLabel = labels.get(appId) || appId;
            if (appFilter && !`${appId} ${appLabel}`.toLowerCase().includes(appFilter)) {
                return false;
            }
            if (filters.status && row.status !== filters.status) {
                return false;
            }
            if (filters.coverage && adapterCoverage(snapshot, appId) !== filters.coverage) {
                return false;
            }
            if (textFilter) {
                const rules = (row.access_rules || []).flatMap((rule) => [rule.condition_text, rule.source, rule.permission, rule.scope]);
                const haystack = [row.object_type, appId, appLabel, row.object, row.detail, row.permission_type, row.status, ...rules].join(' ').toLowerCase();
                if (!haystack.includes(textFilter)) {
                    return false;
                }
            }
            return true;
        }).sort((a, b) => {
            const appComparison = String(a.app_id).localeCompare(String(b.app_id), 'de', { numeric: true, sensitivity: 'base' });
            if (appComparison !== 0) {
                return appComparison;
            }
            return `${a.object_type} ${a.object}`.localeCompare(`${b.object_type} ${b.object}`, 'de', { numeric: true, sensitivity: 'base' });
        });

        const sections = [];
        rows.forEach((row) => {
            const appId = String(row.app_id || 'unbekannt');
            let section = sections[sections.length - 1];
            if (!section || section.appId !== appId) {
                section = { appId, label: labels.get(appId) || appId, rows: [] };
                sections.push(section);
            }
            section.rows.push(row);
        });

        return sections;
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
        const sections = matrixSections(snapshot, filters);
        const collapsedApps = new Set(filters.collapsedApps || []);
        const columnCount = 4 + groups.length;

        if (!sections.length) {
            return empty('Keine Matrixzeilen fuer diese Filter.');
        }

        return `
            <div class="pm-matrix-toolbar" aria-label="Matrixdarstellung steuern">
                <button type="button" data-matrix-action="expand-all">Alle Apps aufklappen</button>
                <button type="button" data-matrix-action="collapse-all">Alle Apps einklappen</button>
                <span>${esc(sections.length)} Apps · Gruppenueberschrift anklicken, um eine Gruppe zu fokussieren</span>
            </div>
            <div class="pm-table-wrap" tabindex="0" aria-label="Berechtigungsmatrix, horizontal und vertikal scrollbar">
                <table class="pm-table">
                    <thead><tr>
                        <th scope="col">Berechtigung</th><th scope="col">Detail</th><th scope="col">Art</th><th scope="col">Status</th>
                        ${groups.map((group) => `<th scope="col"><button type="button" class="pm-group-head" data-group-focus="${esc(group.key)}" aria-pressed="${groupFilter === group.key ? 'true' : 'false'}">${esc(group.label)}${group.type === 'family' ? `<small>${groupCountLabel(group.count)}</small>` : ''}</button></th>`).join('')}
                    </tr></thead>
                    ${sections.map((section) => {
                        const collapsed = collapsedApps.has(section.appId);
                        const coverage = adapterCoverage(snapshot, section.appId);
                        return `<tbody class="pm-app-section" data-app-section="${esc(section.appId)}">
                            <tr class="pm-app-heading">
                                <th colspan="${columnCount}">
                                    <button type="button" data-app-toggle="${esc(section.appId)}" aria-expanded="${collapsed ? 'false' : 'true'}">
                                        <span aria-hidden="true">${collapsed ? '▸' : '▾'}</span>
                                        <strong>${esc(section.label)}</strong>
                                        <code>${esc(section.appId)}</code>
                                        <span class="pm-coverage">Details ${badge(coverage)}</span>
                                        <small>${esc(permissionCountLabel(section.rows.length))}</small>
                                    </button>
                                </th>
                            </tr>
                            ${collapsed ? '' : section.rows.map((row) => `
                                <tr>
                                    <th scope="row" class="pm-permission-cell">${esc(row.object)}<small>${esc(row.object_type)}</small></th>
                                    <td>${renderAccessRules(row)}</td>
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
                        </tbody>`;
                    }).join('')}
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
                            <dt>Detailabdeckung</dt><dd>${badge(adapterCoverage(snapshot, app.app_id))}</dd>
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
        if (mode === 'teams' && Array.isArray(snapshot.group_catalog) && snapshot.group_catalog.length > 0) {
            const members = new Map();
            snapshot.group_catalog.forEach((entry) => {
                (Array.isArray(entry.members) ? entry.members : []).forEach((member) => {
                    members.set(String(member.group), member);
                });
            });
            const roleOrder = { assistant: 0, vacation: 1, eb: 2, pfk: 3 };
            return (snapshot.groups || []).map((group) => {
                const raw = String(group);
                const member = members.get(raw) || {};
                return {
                    key: raw,
                    label: String(member.label || raw),
                    type: 'group',
                    groups: [raw],
                    count: 1,
                    team: member.team ? String(member.team) : '',
                    role: member.role ? String(member.role) : ''
                };
            }).sort((a, b) => {
                if (a.team && !b.team) return -1;
                if (!a.team && b.team) return 1;
                const teamComparison = a.team.localeCompare(b.team, 'de', { numeric: true, sensitivity: 'base' });
                if (teamComparison !== 0) return teamComparison;
                const roleComparison = (roleOrder[a.role] ?? 99) - (roleOrder[b.role] ?? 99);
                return roleComparison !== 0 ? roleComparison : a.label.localeCompare(b.label, 'de', { numeric: true, sensitivity: 'base' });
            });
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
        adapterCoverage,
        matrixSections,
        groupColumns,
        aggregateCell
    };
})();
