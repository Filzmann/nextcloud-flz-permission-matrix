const assert = require('assert');
const vm = require('vm');
const { readFileSync } = require('fs');
const { join } = require('path');

const element = () => ({
    children: [], dataset: {}, disabled: false, hidden: false, textContent: '', attributes: {}, focused: false, listeners: {},
    append(child) { this.children.push(child); },
    addEventListener(type, callback) { (this.listeners[type] = this.listeners[type] || []).push(callback); },
    setAttribute(name, value) { this.attributes[name] = String(value); },
    getAttribute(name) { return name in this.attributes ? this.attributes[name] : null; },
    focus() { this.focused = true; },
    replaceChildren() { this.children = []; },
});
const form = element();
form.elements = { enabled: { checked: true } };
const history = element();
const status = element();
const overlay = element();
overlay.hidden = true;
const trigger = element();
const closeButton = element();
const docListeners = {};
const byId = {
    'pm-full-access-form': form,
    'pm-full-access-history': history,
    'pm-full-access-status': status,
    'pm-admin-access-overlay': overlay,
    'pm-admin-access-warning': trigger,
    'pm-admin-access-overlay-close': closeButton,
};
const document = {
    getElementById: id => byId[id] || null,
    createElement: () => element(),
    addEventListener(type, callback) {
        if (type === 'DOMContentLoaded') { callback(); return; }
        (docListeners[type] = docListeners[type] || []).push(callback);
    },
    querySelectorAll: () => [],
    head: { appendChild() {} },
};
const calls = [];
const api = {
    async adminFullAccess() { calls.push(['GET']); return { history: [] }; },
    async activateAdminFullAccess(uid, minutes) { calls.push(['POST', uid, minutes]); },
    async revokeAdminFullAccess(uid) { calls.push(['DELETE', uid]); },
};
class FormData {
    get(name) { return { enabled: 'on', targetUid: ' admin-target ', durationMinutes: '60' }[name]; }
}
const context = {
    window: { PermissionMatrix: { api } }, document, FormData, Date, Number, String, Intl, JSON, Promise, encodeURIComponent,
};
const overlayFilename = join(__dirname, '..', '..', 'js', 'shared', 'overlay.js');
vm.runInNewContext(readFileSync(overlayFilename, 'utf8'), context, { filename: overlayFilename });
const filename = join(__dirname, '..', '..', 'js', 'admin-access.js');
vm.runInNewContext(readFileSync(filename, 'utf8'), context, { filename });

const fire = async (el, type, event) => { for (const callback of el.listeners[type] || []) await callback(event); };

(async () => {
    await new Promise(resolve => setImmediate(resolve));
    // Ohne geöffnetes Overlay wird keine Historie geladen.
    assert.deepStrictEqual(calls, []);

    // Warn-Icon öffnet das Overlay und lädt die Historie.
    await fire(trigger, 'click', { target: trigger });
    assert.strictEqual(overlay.hidden, false);
    assert.strictEqual(trigger.getAttribute('aria-expanded'), 'true');
    assert.strictEqual(closeButton.focused, true);
    assert.deepStrictEqual(calls, [['GET']]);

    await fire(form, 'submit', { preventDefault() {} });
    const button = { dataset: { revokeUid: 'admin-target' }, disabled: false };
    await fire(history, 'click', { target: { closest: () => button } });
    assert.deepStrictEqual(calls, [['GET'], ['POST', 'admin-target', 60], ['GET'], ['DELETE', 'admin-target'], ['GET']]);
    assert.match(status.textContent, /widerrufen/);

    // Escape schließt das Overlay und gibt den Fokus zurück.
    for (const callback of docListeners.keydown || []) await callback({ key: 'Escape' });
    assert.strictEqual(overlay.hidden, true);
    assert.strictEqual(trigger.getAttribute('aria-expanded'), 'false');
    assert.strictEqual(trigger.focused, true);

    // Schließen-Button und Backdrop-Klick schließen ebenfalls.
    await fire(trigger, 'click', { target: trigger });
    await fire(closeButton, 'click', {});
    assert.strictEqual(overlay.hidden, true);
    await fire(trigger, 'click', { target: trigger });
    await fire(overlay, 'click', { target: overlay });
    assert.strictEqual(overlay.hidden, true);
    assert.deepStrictEqual(calls.slice(-2), [['GET'], ['GET']]);
    console.log('Permission Matrix admin access overlay smoke test passed.');
})().catch(error => { console.error(error); process.exit(1); });
