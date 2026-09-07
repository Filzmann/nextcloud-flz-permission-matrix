const assert = require('assert');

global.window = { PermissionMatrix: { api: {}, render: {} } };
global.document = { addEventListener() {} };

require('../../js/main.js');

const navigation = window.PermissionMatrix.navigation;
assert.strictEqual(navigation.nextTabIndex(0, 'ArrowRight', 6), 1);
assert.strictEqual(navigation.nextTabIndex(0, 'ArrowLeft', 6), 5);
assert.strictEqual(navigation.nextTabIndex(5, 'ArrowRight', 6), 0);
assert.strictEqual(navigation.nextTabIndex(3, 'Home', 6), 0);
assert.strictEqual(navigation.nextTabIndex(2, 'End', 6), 5);
assert.strictEqual(navigation.nextTabIndex(2, 'Escape', 6), null);

assert.deepStrictEqual(navigation.emptyFilters(), {
    app: '',
    group: '',
    groupMode: 'teams',
    status: '',
    coverage: '',
    text: '',
    collapsedApps: []
});

console.log('Permission Matrix navigation smoke test passed.');
