const assert = require('assert');
const vm = require('vm');
const { readFileSync } = require('fs');
const { join } = require('path');

const element = () => ({
    children: [], dataset: {}, disabled: false, textContent: '', listeners: {},
    append(child) { this.children.push(child); },
    addEventListener(type, callback) { this.listeners[type] = callback; },
    setAttribute(name, value) { this[name] = value; },
    replaceChildren() { this.children = []; },
});
const form = element();
form.elements = { enabled: { checked: true } };
const history = element();
const status = element();
const calls = [];
const document = {
    getElementById: id => ({
        'pm-full-access-form': form,
        'pm-full-access-history': history,
        'pm-full-access-status': status,
    }[id] || null),
    createElement: () => element(),
    addEventListener(type, callback) { if (type === 'DOMContentLoaded') callback(); },
};
const api = {
    async adminFullAccess() { calls.push(['GET']); return { history: [] }; },
    async activateAdminFullAccess(uid, minutes) { calls.push(['POST', uid, minutes]); },
    async revokeAdminFullAccess(uid) { calls.push(['DELETE', uid]); },
};
class FormData {
    get(name) { return { enabled: 'on', targetUid: ' admin-target ', durationMinutes: '60' }[name]; }
}
vm.runInNewContext(readFileSync(join(__dirname, '..', '..', 'js', 'admin-access.js'), 'utf8'), {
    window: { PermissionMatrix: { api } }, document, FormData, Date, Number, String,
});

(async () => {
    await new Promise(resolve => setImmediate(resolve));
    await form.listeners.submit({ preventDefault() {} });
    const button = { dataset: { revokeUid: 'admin-target' }, disabled: false };
    await history.listeners.click({ target: { closest: () => button } });
    assert.deepStrictEqual(calls, [['GET'], ['POST', 'admin-target', 60], ['GET'], ['DELETE', 'admin-target'], ['GET']]);
    assert.match(status.textContent, /widerrufen/);
    console.log('Permission Matrix admin access smoke test passed.');
})().catch(error => { console.error(error); process.exit(1); });
