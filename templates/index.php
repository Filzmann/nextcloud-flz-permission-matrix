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
            <p id="pm-status-line"></p>
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

    <div id="pm-notice" class="pm-notice" hidden></div>

    <nav class="pm-tabs" aria-label="Berechtigungsmatrix Ansichten">
        <button type="button" data-tab="overview" class="active">Uebersicht</button>
        <button type="button" data-tab="matrix">Matrix</button>
        <button type="button" data-tab="apps">Apps</button>
        <button type="button" data-tab="groups">Gruppen</button>
        <button type="button" data-tab="diffs">Abweichungen</button>
        <button type="button" data-tab="snapshots">Snapshots</button>
    </nav>

    <section id="pm-view-overview" class="pm-view active">
        <div id="pm-overview"></div>
    </section>

    <section id="pm-view-matrix" class="pm-view">
        <div class="pm-filters">
            <label>App <input id="pm-filter-app" type="search"></label>
            <label>Gruppe <select id="pm-filter-group"></select></label>
            <label>Status <select id="pm-filter-status"></select></label>
            <label>Text <input id="pm-filter-text" type="search"></label>
        </div>
        <div id="pm-matrix"></div>
    </section>

    <section id="pm-view-apps" class="pm-view">
        <div id="pm-apps"></div>
    </section>

    <section id="pm-view-groups" class="pm-view">
        <div id="pm-groups"></div>
    </section>

    <section id="pm-view-diffs" class="pm-view">
        <div id="pm-diffs"></div>
    </section>

    <section id="pm-view-snapshots" class="pm-view">
        <div id="pm-snapshots"></div>
    </section>
</div>
