<?php
script('filzmann_permission_matrix', 'modules/api');
script('filzmann_permission_matrix', 'modules/render');
script('filzmann_permission_matrix', 'main');
style('filzmann_permission_matrix', 'style');
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

    <nav class="pm-tabs" role="tablist" aria-label="Ansichten der Berechtigungsmatrix">
        <button type="button" id="pm-tab-overview" data-tab="overview" role="tab" aria-selected="true" tabindex="0" aria-controls="pm-view-overview" class="active">Übersicht</button>
        <button type="button" id="pm-tab-matrix" data-tab="matrix" role="tab" aria-selected="false" tabindex="-1" aria-controls="pm-view-matrix">Matrix</button>
        <button type="button" id="pm-tab-apps" data-tab="apps" role="tab" aria-selected="false" tabindex="-1" aria-controls="pm-view-apps">Apps</button>
        <button type="button" id="pm-tab-groups" data-tab="groups" role="tab" aria-selected="false" tabindex="-1" aria-controls="pm-view-groups">Gruppen</button>
        <button type="button" id="pm-tab-diffs" data-tab="diffs" role="tab" aria-selected="false" tabindex="-1" aria-controls="pm-view-diffs">Abweichungen</button>
        <button type="button" id="pm-tab-snapshots" data-tab="snapshots" role="tab" aria-selected="false" tabindex="-1" aria-controls="pm-view-snapshots">Snapshots</button>
    </nav>

    <section id="pm-view-overview" class="pm-view active" role="tabpanel" aria-labelledby="pm-tab-overview">
        <div id="pm-overview"></div>
    </section>

    <section id="pm-view-matrix" class="pm-view" role="tabpanel" aria-labelledby="pm-tab-matrix" hidden>
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
                    <option value="IMPLEMENTED">Vollständig</option>
                    <option value="PARTIAL">Teilweise</option>
                    <option value="UNSUPPORTED">Nicht unterstützt</option>
                    <option value="UNKNOWN">Unklar</option>
                </select>
            </label>
            <label>Text <input id="pm-filter-text" type="search"></label>
            <button type="button" id="pm-reset-filters">Filter zurücksetzen</button>
        </div>
        <div id="pm-matrix"></div>
    </section>

    <section id="pm-view-apps" class="pm-view" role="tabpanel" aria-labelledby="pm-tab-apps" hidden>
        <div id="pm-apps"></div>
    </section>

    <section id="pm-view-groups" class="pm-view" role="tabpanel" aria-labelledby="pm-tab-groups" hidden>
        <div id="pm-groups"></div>
    </section>

    <section id="pm-view-diffs" class="pm-view" role="tabpanel" aria-labelledby="pm-tab-diffs" hidden>
        <div id="pm-diffs"></div>
    </section>

    <section id="pm-view-snapshots" class="pm-view" role="tabpanel" aria-labelledby="pm-tab-snapshots" hidden>
        <div id="pm-snapshots"></div>
    </section>
</div>
