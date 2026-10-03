const assert = require('assert');

global.window = {};
global.document = {
    getElementById() {
        return { innerHTML: '', value: '' };
    }
};

require('../../js/modules/render.js');

const snapshot = {
    snapshot_id: 'pm-test',
    created_at: '2026-07-05T12:00:00+00:00',
    nextcloud_version: '34.0.0',
    groups: ['Betriebsrat', 'IKT-Ausschuss', 'ad-ASN-Ada', 'ad-ASN-Berta', 'ad-EB-Ada'],
    group_catalog: [
        { key: 'family:adplaner_assistance_teams', label: 'AdPlaner · Assistenznehmer-Teams', type: 'family', groups: ['ad-ASN-Ada', 'ad-ASN-Berta'], count: 2, members: [
            { group: 'ad-ASN-Ada', label: 'Team Ada · Assistenz', team: 'Ada', role: 'assistant' },
            { group: 'ad-ASN-Berta', label: 'Team Berta · Assistenz', team: 'Berta', role: 'assistant' }
        ] },
        { key: 'family:adplaner_eb_roles', label: 'AdPlaner · Einsatzbegleitung', type: 'family', groups: ['ad-EB-Ada'], count: 1, members: [
            { group: 'ad-EB-Ada', label: 'Rolle EB · Ada', team: null, role: 'eb' }
        ] },
        { key: 'Betriebsrat', label: 'Betriebsrat', type: 'group', groups: ['Betriebsrat'], count: 1, meaning_status: 'UNKNOWN' },
        { key: 'IKT-Ausschuss', label: 'IKT-Ausschuss', type: 'group', groups: ['IKT-Ausschuss'], count: 1, meaning_status: 'KNOWN' }
    ],
    summary: { compliance_status: 'yellow', group_count: 4, group_family_count: 1, app_count: 1, object_count: 1, warning_count: 1, unsupported_count: 1 },
    metadata: { organization_snapshot: { status: 'VALID', contract_version: 1, definition_version: 4, checksum: 'abc123' } },
    warnings: ['Deck <unklar>'],
    unsupported_apps: ['deck'],
    adapter_status: [
        { app_id: 'deck', adapter: 'GenericAppAdapter', status: 'AVAILABILITY_ONLY', confidence: 'high', warnings: [] },
        { app_id: 'deck', adapter: 'AdapterCatalogService', status: 'UNSUPPORTED', confidence: 'low', warnings: ['Keine Detailrechte'] }
    ],
    apps: [{ app_id: 'deck', display_name: 'Deck <Test>', version: '1.0', source: 'appstore', restricted: true, groups: ['IKT-Ausschuss'] }],
    matrix: [{
        object_type: 'App',
        app_id: 'deck',
        object: 'Deck <Test>',
        detail: 'App-Nutzung',
        permission_type: 'App-Verfuegbarkeit',
        status: 'NEW',
        source: 'core-app-config',
        confidence: 'high',
        cells: { Betriebsrat: '-', 'IKT-Ausschuss': 'X', 'ad-ASN-Ada': 'X', 'ad-ASN-Berta': '-', 'ad-EB-Ada': 'X' },
        access_rules: [{
            permission: 'app.use', effect: 'allow', scope: 'app:deck',
            condition_text: 'Gruppe IKT-Ausschuss', source: 'core-app-config', confidence: 'high'
        }]
    }],
    diff_to_baseline: [{ severity: 'critical', type: 'APP_GROUP_EXPANDED', message: 'Deck <Test>', old: '-', new: 'X', group: 'Betriebsrat' }]
};

const render = window.PermissionMatrix.render;
const overview = render.renderOverview(snapshot);
const matrix = render.renderMatrix(snapshot, { app: '', group: '', groupMode: 'summary', status: '', coverage: '', text: '' });
const rawMatrix = render.renderMatrix(snapshot, { app: '', group: '', groupMode: 'raw', status: '', text: '' });
const teamMatrix = render.renderMatrix(snapshot, { app: '', group: '', groupMode: 'teams', status: '', text: '' });
const collapsedMatrix = render.renderMatrix(snapshot, { app: '', group: '', groupMode: 'teams', status: '', text: '', collapsedApps: ['deck'] });
const apps = render.renderApps(snapshot);
const groups = render.renderGroups(snapshot);
const diffs = render.renderDiffs(snapshot);

assert(overview.includes('Nicht unterstuetzte Apps'));
assert(overview.includes('Organisationsvertrag'));
assert(overview.includes('VALID · v1 / Definition 4'));
assert(overview.includes('Deck &lt;unklar&gt;'));
assert(!overview.includes('Deck <unklar>'));
assert(matrix.includes('Deck &lt;Test&gt;'));
assert(!matrix.includes('Deck <Test>'));
assert(matrix.includes('tabindex="0" aria-label="Berechtigungsmatrix, horizontal scrollbar"'));
assert(matrix.includes('data-pm-matrix-scroll-track'));
assert(matrix.includes('data-pm-matrix-scroll-spacer'));
assert(matrix.includes('data-pm-matrix-scroll'));
assert(matrix.includes('data-app-toggle="deck"'));
assert(matrix.includes('<strong>Deck &lt;Test&gt;</strong>'));
assert(matrix.includes('1 Berechtigung'));
assert(matrix.includes('Details <span class="pm-badge pm-badge-unsupported">UNSUPPORTED</span>'));
assert(matrix.includes('<strong>Bedingung:</strong> Gruppe IKT-Ausschuss'));
assert(matrix.includes('core-app-config · high'));
assert(matrix.includes('AdPlaner · Assistenznehmer-Teams'));
assert(matrix.includes('<small>1 Gruppe</small>'));
assert(matrix.includes('X (1/2)'));
assert(matrix.includes('ad-ASN-Ada: X; ad-ASN-Berta: -'));
assert(matrix.includes('aria-label="AdPlaner · Assistenznehmer-Teams: X (1/2). Einzelwerte: ad-ASN-Ada: X; ad-ASN-Berta: -"'));
assert(!matrix.includes('data-group-focus="ad-ASN-Ada"'));
assert(rawMatrix.includes('data-group-focus="ad-ASN-Ada"'));
assert(teamMatrix.includes('Team Ada · Assistenz'));
assert(teamMatrix.includes('Rolle EB · Ada'));
assert(teamMatrix.indexOf('Team Ada · Assistenz') < teamMatrix.indexOf('Rolle EB · Ada'));
assert(!collapsedMatrix.includes('<td>App-Verfuegbarkeit</td>'));
assert(apps.includes('Deck &lt;Test&gt;'));
assert(apps.includes('<dt>Detailabdeckung</dt><dd><span class="pm-badge pm-badge-unsupported">UNSUPPORTED</span></dd>'));
assert(groups.includes('<summary>Rohgruppen anzeigen</summary>'));
assert(groups.includes('<li>ad-ASN-Ada</li>'));
assert(groups.includes('<dt>Bedeutung</dt><dd><span class="pm-badge pm-badge-unknown">UNKNOWN</span></dd>'));
assert(diffs.includes('APP_GROUP_EXPANDED'));

const mixed = render.aggregateCell(
    { 'ad-ASN-Ada': 'X', 'ad-ASN-Berta': '?' },
    render.groupColumns(snapshot, 'summary')[0]
);
assert.deepStrictEqual(mixed, {
    value: 'gemischt (2/2)',
    className: 'mixed',
    title: 'ad-ASN-Ada: X; ad-ASN-Berta: ?'
});

assert.deepStrictEqual(
    render.matrixSections(snapshot, { app: 'Deck <Test>', status: '', text: '' }).map((section) => section.appId),
    ['deck']
);
assert.strictEqual(render.adapterCoverage(snapshot, 'deck'), 'UNSUPPORTED');
assert.deepStrictEqual(render.matrixSections(snapshot, { app: '', status: '', coverage: 'PARTIAL', text: '' }), []);
assert.strictEqual(render.matrixSections(snapshot, { app: '', status: '', coverage: '', text: 'IKT-Ausschuss' }).length, 1);

function scrollNode(scrollWidth, clientWidth) {
    const listeners = new Map();
    return {
        scrollWidth,
        clientWidth,
        scrollLeft: 0,
        style: {},
        hidden: false,
        addEventListener(type, listener) {
            listeners.set(type, listener);
        },
        removeEventListener(type) {
            listeners.delete(type);
        },
        emit(type) {
            listeners.get(type)?.();
        },
        listenerCount() {
            return listeners.size;
        }
    };
}

const tableWrap = scrollNode(1200, 600);
const scrollTrack = scrollNode(0, 600);
const scrollSpacer = { style: {} };
scrollTrack.querySelector = (selector) => selector === '[data-pm-matrix-scroll-spacer]' ? scrollSpacer : null;
const scrollRoot = {
    querySelector(selector) {
        if (selector === '[data-pm-matrix-scroll]') return tableWrap;
        if (selector === '[data-pm-matrix-scroll-track]') return scrollTrack;
        return null;
    }
};

render.bindMatrixScrollTrack(scrollRoot);
assert.strictEqual(scrollTrack.hidden, false);
assert.strictEqual(scrollSpacer.style.width, '1200px');
tableWrap.scrollLeft = 240;
tableWrap.emit('scroll');
assert.strictEqual(scrollTrack.scrollLeft, 240);
scrollTrack.scrollLeft = 80;
scrollTrack.emit('scroll');
assert.strictEqual(tableWrap.scrollLeft, 80);
render.bindMatrixScrollTrack(scrollRoot);
assert.strictEqual(tableWrap.listenerCount(), 1);
assert.strictEqual(scrollTrack.listenerCount(), 1);

console.log('Permission Matrix render smoke test passed.');
