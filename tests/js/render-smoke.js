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
        { key: 'family:adplaner_assistance_teams', label: 'AdPlaner · Assistenznehmer-Teams', type: 'family', groups: ['ad-ASN-Ada', 'ad-ASN-Berta'], count: 2 },
        { key: 'family:adplaner_eb_roles', label: 'AdPlaner · Einsatzbegleitung', type: 'family', groups: ['ad-EB-Ada'], count: 1 },
        { key: 'Betriebsrat', label: 'Betriebsrat', type: 'group', groups: ['Betriebsrat'], count: 1 },
        { key: 'IKT-Ausschuss', label: 'IKT-Ausschuss', type: 'group', groups: ['IKT-Ausschuss'], count: 1 }
    ],
    summary: { compliance_status: 'yellow', group_count: 4, group_family_count: 1, app_count: 1, object_count: 1, warning_count: 1, unsupported_count: 1 },
    warnings: ['Deck <unklar>'],
    unsupported_apps: ['deck'],
    apps: [{ app_id: 'deck', display_name: 'Deck <Test>', version: '1.0', source: 'appstore', restricted: true, groups: ['IKT-Ausschuss'] }],
    matrix: [{
        object_type: 'App',
        app_id: 'deck',
        object: 'Deck <Test>',
        permission_type: 'App-Verfuegbarkeit',
        status: 'UNSUPPORTED',
        cells: { Betriebsrat: '-', 'IKT-Ausschuss': 'X', 'ad-ASN-Ada': 'X', 'ad-ASN-Berta': '-', 'ad-EB-Ada': 'X' }
    }],
    diff_to_baseline: [{ severity: 'critical', type: 'APP_GROUP_EXPANDED', message: 'Deck <Test>', old: '-', new: 'X', group: 'Betriebsrat' }]
};

const render = window.PermissionMatrix.render;
const overview = render.renderOverview(snapshot);
const matrix = render.renderMatrix(snapshot, { app: '', group: '', groupMode: 'summary', status: '', text: '' });
const rawMatrix = render.renderMatrix(snapshot, { app: '', group: '', groupMode: 'raw', status: '', text: '' });
const apps = render.renderApps(snapshot);
const groups = render.renderGroups(snapshot);
const diffs = render.renderDiffs(snapshot);

assert(overview.includes('Nicht unterstuetzte Apps'));
assert(overview.includes('Deck &lt;unklar&gt;'));
assert(!overview.includes('Deck <unklar>'));
assert(matrix.includes('Deck &lt;Test&gt;'));
assert(!matrix.includes('Deck <Test>'));
assert(matrix.includes('AdPlaner · Assistenznehmer-Teams'));
assert(matrix.includes('<small> 1 Gruppe</small>'));
assert(matrix.includes('X (1/2)'));
assert(matrix.includes('ad-ASN-Ada: X; ad-ASN-Berta: -'));
assert(matrix.includes('aria-label="AdPlaner · Assistenznehmer-Teams: X (1/2). Einzelwerte: ad-ASN-Ada: X; ad-ASN-Berta: -"'));
assert(!matrix.includes('<th scope="col">ad-ASN-Ada</th>'));
assert(rawMatrix.includes('<th scope="col">ad-ASN-Ada</th>'));
assert(apps.includes('Deck &lt;Test&gt;'));
assert(groups.includes('<summary>Rohgruppen anzeigen</summary>'));
assert(groups.includes('<li>ad-ASN-Ada</li>'));
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

console.log('Permission Matrix render smoke test passed.');
