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
    groups: ['Betriebsrat', 'IKT-Ausschuss'],
    summary: { compliance_status: 'yellow', group_count: 2, app_count: 1, object_count: 1, warning_count: 1, unsupported_count: 1 },
    warnings: ['Deck <unklar>'],
    unsupported_apps: ['deck'],
    apps: [{ app_id: 'deck', display_name: 'Deck <Test>', version: '1.0', source: 'appstore', restricted: true, groups: ['IKT-Ausschuss'] }],
    matrix: [{
        object_type: 'App',
        app_id: 'deck',
        object: 'Deck <Test>',
        permission_type: 'App-Verfuegbarkeit',
        status: 'UNSUPPORTED',
        cells: { Betriebsrat: '-', 'IKT-Ausschuss': 'X' }
    }],
    diff_to_baseline: [{ severity: 'critical', type: 'APP_GROUP_EXPANDED', message: 'Deck <Test>', old: '-', new: 'X', group: 'Betriebsrat' }]
};

const render = window.PermissionMatrix.render;
const overview = render.renderOverview(snapshot);
const matrix = render.renderMatrix(snapshot, { app: '', group: '', status: '', text: '' });
const apps = render.renderApps(snapshot);
const diffs = render.renderDiffs(snapshot);

assert(overview.includes('Nicht unterstuetzte Apps'));
assert(overview.includes('Deck &lt;unklar&gt;'));
assert(!overview.includes('Deck <unklar>'));
assert(matrix.includes('Deck &lt;Test&gt;'));
assert(!matrix.includes('Deck <Test>'));
assert(apps.includes('Deck &lt;Test&gt;'));
assert(diffs.includes('APP_GROUP_EXPANDED'));

console.log('Permission Matrix render smoke test passed.');
