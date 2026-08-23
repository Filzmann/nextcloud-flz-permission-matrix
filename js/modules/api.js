(function() {
    const base = '/apps/filzmann_permission_matrix';

    function url(path) {
        if (window.OC && typeof window.OC.generateUrl === 'function') {
            return window.OC.generateUrl(base + path);
        }

        return base + path;
    }

    async function request(path, options = {}) {
        const headers = options.headers || {};
        if (options.method && options.method !== 'GET') {
            headers.requesttoken = window.OC ? window.OC.requestToken : '';
        }
        const response = await fetch(url(path), { credentials: 'same-origin', ...options, headers });
        const data = await response.json();
        if (!response.ok || data.ok === false) {
            throw new Error(data.message || 'Request failed');
        }

        return data;
    }

    function post(path, body) {
        const headers = { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' };
        const payload = body instanceof URLSearchParams ? body : new URLSearchParams(body || {});

        return request(path, { method: 'POST', body: payload, headers });
    }

    window.PermissionMatrix = window.PermissionMatrix || {};
    window.PermissionMatrix.api = {
        state: () => request('/api/state'),
        scan: () => post('/api/scan'),
        snapshots: () => request('/api/snapshots'),
        setBaseline: (snapshotId) => post('/api/baseline/' + encodeURIComponent(snapshotId)),
        exportUrl: (format, snapshotId) => url('/api/export/' + encodeURIComponent(format) + (snapshotId ? '/' + encodeURIComponent(snapshotId) : '')),
        config: () => request('/api/config'),
        saveConfig: (payload) => post('/api/config', payload)
    };
})();
