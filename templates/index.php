<?php
script('br_permission_matrix', 'modules/api');
script('br_permission_matrix', 'modules/render');
script('br_permission_matrix', 'main');
style('br_permission_matrix', 'style');
?>

<div id="permission-matrix-app" data-can-manage="<?php p($_['can_manage'] ? '1' : '0'); ?>">
    <header class="pm-head">
        <div>
            <h1>Berechtigungsmatrix</h1>
            <p id="pm-status-line" aria-live="polite" aria-atomic="true"></p>
        </div>
        <div class="pm-actions">
            <?php if ($_['can_manage']): ?>
                <button type="button" id="pm-run-scan">Scan</button>
                <button type="button" id="pm-set-baseline">Baseline</button>
            <?php endif; ?>
            <button type="button" data-export="json">JSON</button>
            <button type="button" data-export="csv">CSV</button>
            <button type="button" data-export="md">Markdown</button>
            <button type="button" data-export="html">HTML</button>
        </div>
    </header>

    <div id="pm-notice" class="pm-notice" role="status" aria-live="polite" aria-atomic="true" hidden></div>

    <nav class="pm-tabs" aria-label="Berechtigungsmatrix Ansichten">
        <button type="button" id="pm-tab-overview" data-tab="overview" class="active" aria-controls="pm-view-overview" aria-pressed="true">Uebersicht</button>
        <button type="button" id="pm-tab-matrix" data-tab="matrix" aria-controls="pm-view-matrix" aria-pressed="false">Matrix</button>
        <button type="button" id="pm-tab-apps" data-tab="apps" aria-controls="pm-view-apps" aria-pressed="false">Apps</button>
        <button type="button" id="pm-tab-groups" data-tab="groups" aria-controls="pm-view-groups" aria-pressed="false">Gruppen</button>
        <button type="button" id="pm-tab-diffs" data-tab="diffs" aria-controls="pm-view-diffs" aria-pressed="false">Abweichungen</button>
        <button type="button" id="pm-tab-snapshots" data-tab="snapshots" aria-controls="pm-view-snapshots" aria-pressed="false">Snapshots</button>
    </nav>

    <section id="pm-view-overview" class="pm-view active" aria-labelledby="pm-tab-overview">
        <div id="pm-overview"></div>
    </section>

    <section id="pm-view-matrix" class="pm-view" aria-labelledby="pm-tab-matrix" hidden>
        <div class="pm-filters">
            <label>Gruppenansicht
                <select id="pm-group-mode">
                    <option value="teams">Teams</option>
                    <option value="summary">Gruppenfamilien</option>
                    <option value="raw">Rohgruppen</option>
                </select>
            </label>
            <label>App <input id="pm-filter-app" type="search"></label>
            <label>Gruppe <select id="pm-filter-group"></select></label>
            <label>Status <select id="pm-filter-status"></select></label>
            <label>Detailabdeckung
                <select id="pm-filter-coverage">
                    <option value="">Alle</option>
                    <option value="IMPLEMENTED">Vollstaendig</option>
                    <option value="PARTIAL">Teilweise</option>
                    <option value="UNSUPPORTED">Nicht unterstuetzt</option>
                    <option value="UNKNOWN">Unklar</option>
                </select>
            </label>
            <label>Text <input id="pm-filter-text" type="search"></label>
        </div>
        <div id="pm-matrix"></div>
    </section>

    <section id="pm-view-apps" class="pm-view" aria-labelledby="pm-tab-apps" hidden>
        <div id="pm-apps"></div>
    </section>

    <section id="pm-view-groups" class="pm-view" aria-labelledby="pm-tab-groups" hidden>
        <div id="pm-groups"></div>
    </section>

    <section id="pm-view-diffs" class="pm-view" aria-labelledby="pm-tab-diffs" hidden>
        <div id="pm-diffs"></div>
    </section>

    <section id="pm-view-snapshots" class="pm-view" aria-labelledby="pm-tab-snapshots" hidden>
        <div id="pm-snapshots"></div>
    </section>
</div>
