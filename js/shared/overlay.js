(function() {
    window.OCA = window.OCA || {};
    window.OCA.FlzPermissionMatrix = window.OCA.FlzPermissionMatrix || {};
    window.OCA.FlzPermissionMatrix.Shared = window.OCA.FlzPermissionMatrix.Shared || {};
    const shared = window.OCA.FlzPermissionMatrix.Shared;

    const OVERLAY_STYLE_ID = 'flz-shared-overlay-styles';
    const OVERLAY_CSS = [
        '.flz-shared-overlay[hidden]{display:none !important;}',
        '.flz-shared-overlay{position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(0,0,0,.45);}',
        '.flz-shared-overlay__panel{display:flex;flex-direction:column;width:min(760px,100%);max-height:calc(100vh - 48px);border:1px solid var(--color-border);border-radius:8px;background:var(--color-main-background);box-shadow:0 18px 48px rgba(0,0,0,.28);}',
        '.flz-shared-overlay__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border-bottom:1px solid var(--color-border);}',
        '.flz-shared-overlay__head h2{margin:0;}',
        '.flz-shared-overlay__content{overflow-y:auto;padding:14px;}',
        '.flz-shared-overlay__warning{margin:0 0 12px;padding:10px 12px;border:1px solid var(--color-warning);border-radius:var(--border-radius,6px);background:var(--color-warning-light);}',
        '.flz-shared-overlay__warning p{margin:0 0 8px;}',
        '.flz-shared-overlay__warning p:last-child{margin-bottom:0;}',
        '.flz-shared-warn-trigger{display:grid;width:26px;height:26px;place-items:center;padding:0;cursor:pointer;border:1px solid var(--color-warning);border-radius:50%;color:var(--color-warning-text,var(--color-main-text));background:var(--color-warning-light);font-size:16px;}',
        '.flz-shared-warn-trigger:focus-visible{outline:3px solid var(--color-primary-element);outline-offset:2px;}',
        '@media (max-width:760px){.flz-shared-overlay{padding:8px;}.flz-shared-overlay__panel{max-height:calc(100vh - 16px);}}',
    ].join('\n');

    function byId(id) {
        return document.getElementById(id);
    }

    function injectOverlayStyles() {
        if (typeof document === 'undefined' || !document.head || !document.createElement || !document.getElementById) {
            return;
        }
        if (document.getElementById(OVERLAY_STYLE_ID)) {
            return;
        }
        const style = document.createElement('style');
        style.id = OVERLAY_STYLE_ID;
        style.textContent = OVERLAY_CSS;
        document.head.appendChild(style);
    }

    class Overlay {
        constructor(options = {}) {
            this.overlay = options.overlay || (options.overlayId ? byId(options.overlayId) : null);
            this.triggers = [];
            if (options.trigger) {
                this.triggers.push(options.trigger);
            } else if (options.triggerId && byId(options.triggerId)) {
                this.triggers.push(byId(options.triggerId));
            }
            if (options.extraTriggerSelector && document.querySelectorAll) {
                document.querySelectorAll(options.extraTriggerSelector).forEach(trigger => this.triggers.push(trigger));
            }
            this.closeButton = options.closeButton || (options.closeId ? byId(options.closeId) : null);
            this.onOpen = options.onOpen || null;
            this.lastTrigger = null;
            if (!this.overlay) {
                return;
            }
            injectOverlayStyles();
            this.triggers.forEach(trigger => trigger.addEventListener('click', () => this.open(trigger)));
            if (this.closeButton) {
                this.closeButton.addEventListener('click', () => this.close());
            }
            this.overlay.addEventListener('click', event => {
                if (event.target === this.overlay) {
                    this.close();
                }
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && !this.overlay.hidden) {
                    this.close();
                }
            });
        }

        setExpanded(expanded) {
            this.triggers.forEach(trigger => trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false'));
        }

        open(source) {
            this.overlay.hidden = false;
            this.lastTrigger = source || this.triggers[0] || null;
            this.setExpanded(true);
            if (this.closeButton && typeof this.closeButton.focus === 'function') {
                this.closeButton.focus();
            }
            if (this.onOpen) {
                return this.onOpen();
            }
            return undefined;
        }

        close() {
            this.overlay.hidden = true;
            this.setExpanded(false);
            if (this.lastTrigger && typeof this.lastTrigger.focus === 'function') {
                this.lastTrigger.focus();
            }
            this.lastTrigger = null;
        }
    }

    class AdminAccessOverlay {
        constructor(options = {}) {
            this.api = options.api || null;
            this.canManage = options.canManage !== false;
            this.form = options.formId ? byId(options.formId) : null;
            this.history = options.historyId ? byId(options.historyId) : null;
            this.status = options.statusId ? byId(options.statusId) : null;
            this.overlay = new Overlay({
                overlayId: options.overlayId,
                triggerId: options.triggerId,
                closeId: options.closeId,
                extraTriggerSelector: '[data-open-admin-access]',
                onOpen: () => this.load(),
            });
            if (!this.api || !this.status) {
                return;
            }
            if (this.form && this.canManage) {
                this.form.addEventListener('submit', event => this.submit(event));
            }
            if (this.history && this.canManage) {
                this.history.addEventListener('click', event => this.revokeClick(event));
            }
        }

        setStatus(message, isError = false) {
            if (!this.status) {
                return;
            }
            this.status.textContent = message;
            this.status.setAttribute('role', isError ? 'alert' : 'status');
        }

        formatDate(value) {
            if (!value) {
                return '—';
            }
            return new Intl.DateTimeFormat('de-DE', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
        }

        renderGrant(grant) {
            const row = document.createElement('tr');
            const now = Date.now();
            const active = !grant.revokedAt && Date.parse(grant.startsAt) <= now && Date.parse(grant.endsAt) > now;
            const values = [
                grant.targetUid,
                grant.grantedBy,
                this.formatDate(grant.startsAt),
                this.formatDate(grant.endsAt),
                `${this.formatDate(grant.revokedAt || grant.endsAt)} · ${active ? 'aktiv' : (grant.revokedAt ? 'widerrufen' : 'abgelaufen')}`,
            ];
            values.forEach(value => {
                const cell = document.createElement('td');
                cell.textContent = String(value || '—');
                row.append(cell);
            });
            const action = document.createElement('td');
            if (active && this.canManage) {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = 'Widerrufen';
                button.dataset.revokeUid = grant.targetUid;
                action.append(button);
            } else {
                action.textContent = '—';
            }
            row.append(action);
            return row;
        }

        async load() {
            if (!this.history || !this.api) {
                return;
            }
            try {
                const state = await this.api.getState();
                this.history.replaceChildren();
                if (!state.history?.length) {
                    const row = document.createElement('tr');
                    const cell = document.createElement('td');
                    cell.colSpan = 6;
                    cell.textContent = 'Noch keine Freigaben protokolliert.';
                    row.append(cell);
                    this.history.append(row);
                    return;
                }
                state.history.forEach(grant => this.history.append(this.renderGrant(grant)));
            } catch (error) {
                this.setStatus(error?.message || 'Die Vollzugriffshistorie konnte nicht geladen werden.', true);
            }
        }

        async submit(event) {
            event.preventDefault();
            const fields = new FormData(this.form);
            if (fields.get('enabled') !== 'on') {
                return;
            }
            try {
                await this.api.activate(String(fields.get('targetUid') || '').trim(), Number(fields.get('durationMinutes')));
                this.form.elements.enabled.checked = false;
                this.setStatus('Der zeitlich begrenzte Vollzugriff wurde aktiviert.');
                await this.load();
            } catch (error) {
                this.setStatus(error?.message || 'Der Vollzugriff konnte nicht aktiviert werden.', true);
            }
        }

        async revokeClick(event) {
            const button = event.target.closest('button[data-revoke-uid]');
            if (!button) {
                return;
            }
            button.disabled = true;
            try {
                await this.api.revoke(button.dataset.revokeUid);
                this.setStatus('Der Vollzugriff wurde widerrufen.');
                await this.load();
            } catch (error) {
                button.disabled = false;
                this.setStatus(error?.message || 'Der Vollzugriff konnte nicht widerrufen werden.', true);
            }
        }
    }

    shared.ui = shared.ui || {};
    shared.ui.Overlay = Overlay;
    shared.ui.AdminAccessOverlay = AdminAccessOverlay;
    shared.ui.injectOverlayStyles = injectOverlayStyles;
})();
