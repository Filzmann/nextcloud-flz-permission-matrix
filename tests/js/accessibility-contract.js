const assert = require('assert');
const { readFileSync } = require('fs');
const { join } = require('path');

const root = join(__dirname, '..', '..');
const template = readFileSync(join(root, 'templates', 'index.php'), 'utf8');
const adminTemplate = readFileSync(join(root, 'templates', 'admin.php'), 'utf8');
const main = readFileSync(join(root, 'js', 'main.js'), 'utf8');
const admin = readFileSync(join(root, 'js', 'admin.js'), 'utf8');
const style = readFileSync(join(root, 'css', 'style.css'), 'utf8');
const info = readFileSync(join(root, 'appinfo', 'info.xml'), 'utf8');

assert(template.includes('aria-controls="pm-view-matrix"'));
assert(template.includes('aria-labelledby="pm-tab-matrix" hidden'));
assert(template.includes('<option value="teams">Teams</option>'));
assert(template.includes('id="pm-notice" class="pm-notice" role="status" aria-live="polite"'));
assert(template.includes("script('orgsuite', 'suite-navigation')"));
assert(template.includes("style('orgsuite', 'suite-navigation')"));
assert(template.includes('data-orgsuite data-suite="br" data-current-app="br_permission_matrix"'));
assert(info.includes('<app>orgsuite</app>'));
assert(!info.includes('<navigations>'));
assert(adminTemplate.includes('id="pm-admin-notice" class="pm-notice" role="status" aria-live="polite"'));
assert(main.includes("button.setAttribute('aria-pressed', active ? 'true' : 'false')"));
assert(main.includes('view.hidden = !active'));
assert(main.includes("event.target.closest('[data-app-toggle]')"));
assert(main.includes("event.target.closest('[data-group-focus]')"));
assert(main.includes("type === 'error' ? 'alert' : 'status'"));
assert(admin.includes("type === 'error' ? 'alert' : 'status'"));
assert(style.includes('overflow-y: auto !important'));
assert(style.includes('max-height: calc(100vh - 300px)'));

console.log('Permission Matrix accessibility contract test passed.');
