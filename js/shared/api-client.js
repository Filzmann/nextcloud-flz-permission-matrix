(function() {
    window.OCA = window.OCA || {};
    window.OCA.FlzPermissionMatrix = window.OCA.FlzPermissionMatrix || {};
    window.OCA.FlzPermissionMatrix.Shared = window.OCA.FlzPermissionMatrix.Shared || {};
    const shared = window.OCA.FlzPermissionMatrix.Shared;

    class ApiClient {
        constructor(options = {}) {
            this.appId = options.appId || '';
            this.basePath = options.basePath || (this.appId ? `/apps/${this.appId}` : '');
            this.fetcher = options.fetcher || window.fetch.bind(window);
            this.generateUrl = options.generateUrl || ((path) => OC.generateUrl(path));
            this.requestToken = options.requestToken || (() => OC.requestToken);
            this.errorMessage = options.errorMessage || ((data, status) => {
                const message = typeof data?.message === 'string' ? data.message.trim() : '';
                const error = typeof data?.error === 'string' ? data.error.trim() : '';
                return message || error || `HTTP ${status}`;
            });
        }

        async request(path, options = {}) {
            const response = await this.fetcher(this.generateUrl(this.basePath + path), {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    requesttoken: this.requestToken(),
                    ...(options.headers || {})
                }
            });
            const data = this.parseResponseText(await response.text());
            if (!response.ok) {
                const error = new Error(this.errorMessage(data, response.status));
                error.data = data;
                error.status = response.status;
                throw error;
            }
            return data;
        }

        parseResponseText(text) {
            try {
                return text ? JSON.parse(text) : {};
            } catch (error) {
                return { raw: text };
            }
        }

        encode(value) {
            return ApiClient.encode(value);
        }

        static encode(value) {
            return encodeURIComponent(String(value));
        }
    }

    shared.api = shared.api || {};
    shared.api.ApiClient = ApiClient;
})();
