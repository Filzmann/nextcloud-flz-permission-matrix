import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const checks = [
    ['node', ['--check', 'js/modules/api.js']],
    ['node', ['--check', 'js/modules/render.js']],
    ['node', ['--check', 'js/main.js']],
    ['node', ['--check', 'js/admin.js']],
    ['node', ['tests/js/render-smoke.js']],
    ['node', ['tests/js/accessibility-contract.js']],
];

for (const [command, args] of checks) {
    const display = [command, ...args].join(' ');
    console.log(`> ${display}`);
    const result = spawnSync(command, args, { cwd: root, stdio: 'inherit' });
    if (result.status !== 0) {
        process.exit(result.status || 1);
    }
}

console.log('BR Permission Matrix JavaScript tests passed');
