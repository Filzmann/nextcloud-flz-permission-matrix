<?php
$hasMatrixAccess = (bool)($_['hasMatrixAccess'] ?? false);
script('flz_permission_matrix', 'modules/api');
script('flz_permission_matrix', 'admin-access');
if ($hasMatrixAccess) {
    script('flz_permission_matrix', 'modules/render');
    script('flz_permission_matrix', 'main');
}
style('flz_permission_matrix', 'style');
?>

<div id="permission-matrix-app" data-can-manage="<?php p($_['can_manage'] ? '1' : '0'); ?>">
    <header class="pm-head">
        <div>
            <div class="pm-title-row">
                <h1>Berechtigungsmatrix</h1>
                <?php if ($_['showMissingAdminGrant'] ?? false): ?>
                    <details class="pm-admin-access-warning">
                        <summary aria-label="Informationen zum fehlenden fachlichen Admin-Vollzugriff"><span aria-hidden="true">⚠</span></summary>
                        <div class="pm-admin-access-warning__panel">
                            <strong>Kein zeitlich begrenzter fachlicher Vollzugriff aktiv.</strong>
                            <p>Native Nextcloud-Administration erteilt keinen fachlichen Vollzugriff. Mitglieder der Gruppe Datenschutzbeauftragte können eine app-lokale Freigabe von höchstens 24 Stunden erteilen.</p>
                            <?php if ($_['showAdminAccessLink'] ?? false): ?><a href="#pm-full-access-heading" target="_blank" rel="noopener noreferrer">Freigabesteuerung in neuem Tab öffnen</a><?php endif; ?>
                        </div>
                    </details>
                <?php endif; ?>
            </div>
            <?php if ($hasMatrixAccess): ?><p id="pm-status-line" aria-live="polite" aria-atomic="true"></p><?php endif; ?>
        </div>
        <?php if ($hasMatrixAccess): ?>
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
        <?php endif; ?>
    </header>

    <?php if ($_['canManageAdminAccess'] ?? false): ?>
        <section class="pm-access-card" aria-labelledby="pm-full-access-heading">
            <h2 id="pm-full-access-heading" tabindex="-1">Zeitlich begrenzter Admin-Vollzugriff</h2>
            <p>Ausschließlich Mitglieder der Gruppe Datenschutzbeauftragte dürfen aktuellen Nextcloud-Administrationskonten fachlichen Vollzugriff erteilen. Maximal 24 Stunden sind zulässig.</p>
            <form id="pm-full-access-form" class="pm-access-form">
                <label>Admin-Benutzerkennung <input name="targetUid" required maxlength="64" autocomplete="off"></label>
                <label>Dauer <select name="durationMinutes" required><option value="60">1 Stunde</option><option value="240">4 Stunden</option><option value="480">8 Stunden</option><option value="1440">24 Stunden</option></select></label>
                <label><input id="pm-full-access-enabled" name="enabled" type="checkbox" required> Vollzugriff für diesen Zeitraum aktivieren</label>
                <button type="submit">Freigabe aktivieren</button>
            </form>
            <p id="pm-full-access-status" role="status" aria-live="polite"></p>
            <div class="pm-table-wrap pm-access-history" tabindex="0" role="region" aria-label="Protokollierte Admin-Vollzugriffszeiträume">
                <table class="pm-table"><caption>Protokollierte Admin-Vollzugriffszeiträume</caption><thead><tr><th>Ziel-Admin</th><th>Freigegeben von</th><th>Von</th><th>Geplant bis</th><th>Tatsächlich bis / Status</th><th>Aktion</th></tr></thead><tbody id="pm-full-access-history"><tr><td colspan="6">Freigaben werden geladen.</td></tr></tbody></table>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($hasMatrixAccess): ?>
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
    <?php endif; ?>
</div>
