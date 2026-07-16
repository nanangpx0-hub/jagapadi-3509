/**
 * JAGAPADI Event Delegation System
 * 
 * Centralized event handling to reduce memory leaks and improve performance.
 * Uses event delegation pattern to handle click/submit events globally.
 * 
 * Usage in HTML:
 * <button data-action="delete-report" data-id="123">Delete</button>
 * <form data-action="save-report">...</form>
 * <a href="/api/reports/123" data-confirm="Delete report?">Delete</a>
 */

(function() {
    'use strict';

    const AppEvents = {
        handlers: new Map(),
        initialized: false,

        init: function() {
            if (this.initialized) return;
            
            this.registerDefaultHandlers();
            this.attachDocumentListeners();
            this.initialized = true;
            
            console.log('[AppEvents] Initialized');
        },

        registerDefaultHandlers: function() {
            this.on('delete-report', this.handleDelete);
            this.on('confirm-action', this.handleConfirm);
            this.on('submit-form', this.handleSubmit);
            this.on('toggle-modal', this.handleModal);
            this.on('refresh-page', this.handleRefresh);
            this.on('ajax-request', this.handleAjax);
            this.on('copy-text', this.handleCopy);
            this.on('toggle-sidebar', this.handleSidebar);
        },

        attachDocumentListeners: function() {
            document.addEventListener('click', this.delegateClick.bind(this), { passive: true });
            document.addEventListener('submit', this.delegateSubmit.bind(this), { passive: false });
            document.addEventListener('change', this.delegateChange.bind(this), { passive: true });
            document.addEventListener('input', this.delegateInput.bind(this), { passive: true });
        },

        delegateClick: function(e) {
            const target = e.target.closest('[data-action]');
            if (!target) return;

            e.preventDefault();
            
            const action = target.dataset.action;
            const handlers = this.getHandlers(action);
            
            handlers.forEach(handler => {
                handler.call(this, e, target, this.extractData(target));
            });
        },

        delegateSubmit: function(e) {
            const target = e.target;
            if (!target.matches('[data-action]')) return;

            e.preventDefault();
            
            const formData = new FormData(target);
            const data = Object.fromEntries(formData.entries());
            
            this.dispatch('submit-form', { event: e, target, data, formData });
        },

        delegateChange: function(e) {
            const target = e.target;
            if (!target.matches('[data-action]')) return;

            const action = target.dataset.action;
            this.dispatch(action, { event: e, target, value: target.value });
        },

        delegateInput: function(e) {
            const target = e.target;
            if (!target.matches('[data-live]')) return;

            const action = target.dataset.action;
            this.dispatch(action, { event: e, target, value: target.value });
        },

        getHandlers: function(action) {
            const handlers = this.handlers.get(action) || [];
            return handlers.filter(h => typeof h === 'function');
        },

        extractData: function(element) {
            const data = {};
            
            for (const [key, value] of Object.entries(element.dataset)) {
                if (key === 'action' || key === 'confirm') continue;
                
                const camelKey = key.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
                data[camelKey] = value;
            }
            
            return data;
        },

        on: function(action, handler) {
            if (!this.handlers.has(action)) {
                this.handlers.set(action, []);
            }
            this.handlers.get(action).push(handler);
        },

        off: function(action, handler) {
            if (!handler) {
                this.handlers.delete(action);
                return;
            }
            
            const handlers = this.handlers.get(action);
            if (handlers) {
                const index = handlers.indexOf(handler);
                if (index > -1) handlers.splice(index, 1);
            }
        },

        dispatch: function(action, payload = {}) {
            const handlers = this.getHandlers(action);
            handlers.forEach(handler => handler(payload));
        },

        handleDelete: function(e, target, data) {
            const message = target.dataset.confirm || 'Apakah Anda yakin?';
            
            if (!confirm(message)) return;
            
            const id = data.id || target.dataset.id;
            const endpoint = target.href || target.dataset.endpoint;
            
            if (!endpoint && !id) {
                console.error('[AppEvents] No endpoint or ID provided for delete action');
                return;
            }

            this.fetchWithConfirm(endpoint || `/api/${data.resource || 'reports'}/${id}`, {
                method: 'DELETE',
                headers: this.getCsrfHeaders()
            }).then(result => {
                if (result.success) {
                    this.showToast(result.message || 'Data berhasil dihapus', 'success');
                    this.refreshContent(target);
                } else {
                    this.showToast(result.message || 'Gagal menghapus data', 'error');
                }
            }).catch(err => {
                this.showToast('Terjadi kesalahan: ' + err.message, 'error');
            });
        },

        handleConfirm: function(e, target, data) {
            const message = data.confirm || target.dataset.confirm || 'Lanjutkan?';
            
            if (confirm(message)) {
                const callback = data.callback;
                if (callback && typeof window[callback] === 'function') {
                    window[callback](data);
                } else if (target.href && !target.dataset.method) {
                    window.location.href = target.href;
                } else {
                    this.dispatch('confirmed-action', { target, data });
                }
            }
        },

        handleSubmit: function(payload) {
            const { event, target, data, formData } = payload;
            
            const submitBtn = target.querySelector('[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner"></span> Memproses...';
            }

            const action = target.dataset.action;
            const method = target.dataset.method || 'POST';
            const endpoint = target.action;

            this.fetch(endpoint, {
                method,
                body: method !== 'GET' ? formData : undefined,
                headers: method === 'POST' ? this.getCsrfHeaders() : {}
            }).then(result => {
                if (result.success) {
                    this.showToast(result.message || 'Berhasil disimpan', 'success');
                    this.refreshContent(target);
                    
                    if (target.dataset.reset === 'true') {
                        target.reset();
                    }
                    
                    if (target.dataset.closeModal) {
                        this.closeModal(target.dataset.closeModal);
                    }
                } else {
                    this.showToast(result.message || 'Gagal menyimpan', 'error');
                }
            }).catch(err => {
                this.showToast('Terjadi kesalahan', 'error');
            }).finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        },

        handleModal: function(e, target, data) {
            const modalId = data.id || target.dataset.modalId;
            if (!modalId) return;

            if (target.dataset.close) {
                this.closeModal(modalId);
            } else {
                this.openModal(modalId);
            }
        },

        handleRefresh: function(e, target, data) {
            this.refreshContent(target);
        },

        handleAjax: function(e, target, data) {
            const url = target.dataset.url || target.href;
            if (!url) return;

            const cacheKey = data.cache || target.dataset.cache;
            if (cacheKey && this.hasCache(cacheKey)) {
                this.renderContent(target, this.getCache(cacheKey));
                return;
            }

            fetch(url, {
                headers: this.getCsrfHeaders()
            })
            .then(r => r.json())
            .then(result => {
                if (cacheKey) this.setCache(cacheKey, result);
                this.renderContent(target, result);
            })
            .catch(err => {
                this.showToast('Gagal memuat data', 'error');
            });
        },

        handleCopy: function(e, target, data) {
            const text = data.text || target.dataset.text || target.textContent;
            navigator.clipboard.writeText(text).then(() => {
                this.showToast('Teks disalin ke clipboard', 'success');
            }).catch(() => {
                this.showToast('Gagal menyalin teks', 'error');
            });
        },

        handleSidebar: function(e, target, data) {
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', document.body.classList.contains('sidebar-collapsed'));
        },

        openModal: function(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;

            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        },

        closeModal: function(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;

            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        },

        refreshContent: function(element) {
            const target = element.dataset.target || element.closest('[data-refresh]');
            if (!target) {
                window.location.reload();
                return;
            }

            const url = target.dataset.url || window.location.href;
            this.fetch(url).then(html => {
                this.renderContent(target, html);
            });
        },

        renderContent: function(element, content) {
            if (typeof content === 'string') {
                const temp = document.createElement('div');
                temp.innerHTML = content;
                element.innerHTML = '';
                element.appendChild(temp);
            }
        },

        showToast: function(message, type = 'info') {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'toast-container';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            
            container.appendChild(toast);
            
            requestAnimationFrame(() => {
                toast.classList.add('show');
            });

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        },

        cache: new Map(),
        hasCache: function(key) {
            return this.cache.has(key);
        },
        getCache: function(key) {
            return this.cache.get(key);
        },
        setCache: function(key, value) {
            this.cache.set(key, value);
            setTimeout(() => this.cache.delete(key), 300000);
        },

        fetch: function(url, options = {}) {
            return fetch(url, {
                ...options,
                credentials: 'same-origin'
            }).then(r => r.json());
        },

        fetchWithConfirm: function(url, options = {}) {
            return fetch(url, {
                ...options,
                credentials: 'same-origin'
            }).then(r => r.json());
        },

        getCsrfHeaders: function() {
            const token = document.querySelector('input[name="csrf_token"]')?.value 
                      || document.querySelector('meta[name="csrf-token"]')?.content;
            return token ? { 'X-CSRF-TOKEN': token } : {};
        }
    };

    window.AppEvents = AppEvents;
    document.addEventListener('DOMContentLoaded', () => AppEvents.init());

})();