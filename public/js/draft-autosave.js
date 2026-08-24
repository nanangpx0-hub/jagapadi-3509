/**
 * Draft Auto-Save for Laporan Hama Form
 * Automatically saves form data to localStorage every 30 seconds
 * Restores draft on page load if available
 * 
 * @version 1.0.0
 * @author JAGAPADI Team
 */
(function () {
    'use strict';

    const STORAGE_KEY_PREFIX = 'jagapadi_laporan_draft';
    const LEGACY_STORAGE_KEY = 'jagapadi_laporan_draft';
    const SAVE_INTERVAL = 30000; // 30 seconds
    const MAX_DRAFT_AGE = 24 * 60 * 60 * 1000; // 24 hours

    class DraftAutoSave {
        constructor(formId) {
            this.form = document.getElementById(formId);
            this.storageKey = this.buildStorageKey();
            this.saveTimer = null;
            this.lastSaveTime = null;

            if (this.form) {
                this.init();
            }
        }

        buildStorageKey() {
            const userId = String(this.form.dataset.draftUser || '').trim();
            const moduleName = String(this.form.dataset.draftModule || '').trim();
            if (!/^\d+$/.test(userId) || !/^[a-z0-9_-]+$/i.test(moduleName)) {
                throw new Error('Identitas autosave draf tidak valid');
            }
            return `${STORAGE_KEY_PREFIX}:${userId}:${moduleName}`;
        }

        init() {
            // Key lama tidak terisolasi per pengguna/modul dan tidak aman untuk dipulihkan.
            localStorage.removeItem(LEGACY_STORAGE_KEY);

            // Check for existing draft on page load
            this.checkExistingDraft();

            // Start auto-save interval
            this.startAutoSave();

            // Simpan keadaan terakhir sebelum request. Jangan hapus di sini:
            // submit belum tentu berhasil dan server dapat mengembalikan validasi.
            this.form.addEventListener('submit', () => {
                this.saveDraft();
            });

            // Save on page unload (for accidental closes)
            window.addEventListener('beforeunload', () => {
                this.saveDraft();
            });

            console.log('[DraftAutoSave] Initialized for form:', this.form.id);
        }

        /**
         * Check if there's an existing draft and offer to restore
         */
        checkExistingDraft() {
            try {
                const draftJson = localStorage.getItem(this.storageKey);
                if (!draftJson) return;

                const draft = JSON.parse(draftJson);
                const age = Date.now() - draft.timestamp;

                // Only restore if less than MAX_DRAFT_AGE old
                if (age < MAX_DRAFT_AGE) {
                    const ageMinutes = Math.floor(age / 60000);
                    const ageText = ageMinutes < 60
                        ? `${ageMinutes} menit yang lalu`
                        : `${Math.floor(ageMinutes / 60)} jam yang lalu`;

                    if (confirm(`Ditemukan draft laporan yang belum selesai (tersimpan ${ageText}). Lanjutkan pengisian?`)) {
                        this.restoreDraft(draft.fields);
                        this.showNotification('Draft berhasil dipulihkan', 'success');
                    } else {
                        this.clearDraft();
                    }
                } else {
                    // Draft too old, clear it
                    this.clearDraft();
                }
            } catch (e) {
                console.error('[DraftAutoSave] Error checking draft:', e);
                this.clearDraft();
            }
        }

        /**
         * Save current form data to localStorage
         */
        saveDraft() {
            try {
                const formData = new FormData(this.form);
                const fields = {};

                formData.forEach((value, key) => {
                    // Skip sensitive/temporary fields
                    if (key !== 'csrf_token' && key !== 'foto' && key !== 'website_hp') {
                        fields[key] = value;
                    }
                });

                const ignoredDefaults = new Set([
                    'tanggal', 'tanggal_kejadian', 'status',
                    'kabupaten_id', 'kecamatan_id', 'desa_id',
                    'latitude', 'longitude', 'nama_pelapor'
                ]);
                const hasMeaningfulData = Object.entries(fields).some(([key, value]) =>
                    !ignoredDefaults.has(key) && String(value).trim() !== ''
                );

                // Jangan membuat draf hanya dari nilai default form.
                if (hasMeaningfulData) {
                    const draft = {
                        fields: fields,
                        timestamp: Date.now(),
                        url: window.location.href
                    };

                    localStorage.setItem(this.storageKey, JSON.stringify(draft));
                    this.lastSaveTime = new Date();
                    this.showSaveIndicator();
                } else {
                    this.clearDraft();
                }
            } catch (e) {
                console.error('[DraftAutoSave] Error saving draft:', e);
            }
        }

        /**
         * Restore form fields from draft
         */
        restoreDraft(fields) {
            Object.entries(fields).forEach(([name, value]) => {
                const input = this.form.querySelector(`[name="${name}"]`);
                if (input) {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = (value === 'on' || value === '1' || value === input.value);
                    } else {
                        input.value = value;
                    }

                    // Trigger change event for dependent dropdowns
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        }

        /**
         * Clear draft from localStorage
         */
        clearDraft() {
            try {
                localStorage.removeItem(this.storageKey);
                console.log('[DraftAutoSave] Draft cleared');
            } catch (e) {
                console.error('[DraftAutoSave] Error clearing draft:', e);
            }
        }

        /**
         * Start auto-save interval
         */
        startAutoSave() {
            this.saveTimer = setInterval(() => {
                this.saveDraft();
            }, SAVE_INTERVAL);
        }

        /**
         * Stop auto-save interval
         */
        stopAutoSave() {
            if (this.saveTimer) {
                clearInterval(this.saveTimer);
                this.saveTimer = null;
            }
        }

        /**
         * Show brief save indicator
         */
        showSaveIndicator() {
            let indicator = document.getElementById('autoSaveIndicator');

            if (!indicator) {
                indicator = document.createElement('span');
                indicator.id = 'autoSaveIndicator';
                indicator.className = 'text-success ml-2';
                indicator.style.cssText = 'font-size: 0.8rem; opacity: 0;';

                // Insert after submit button
                const submitBtn = document.getElementById('btnSubmitForm');
                if (submitBtn && submitBtn.parentNode) {
                    submitBtn.parentNode.appendChild(indicator);
                }
            }

            indicator.innerHTML = '<i class="fas fa-check-circle"></i> Draft tersimpan';
            indicator.style.opacity = '1';

            setTimeout(() => {
                indicator.style.opacity = '0';
            }, 2500);
        }

        /**
         * Show notification message
         */
        showNotification(message, type = 'info') {
            const alertClass = type === 'success' ? 'alert-success' :
                type === 'error' ? 'alert-danger' : 'alert-info';

            const alertDiv = document.createElement('div');
            alertDiv.className = `alert ${alertClass} alert-dismissible fade show`;
            alertDiv.setAttribute('role', 'alert');
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            `;

            // Insert at top of card-body
            const cardBody = this.form.querySelector('.card-body');
            if (cardBody) {
                cardBody.insertBefore(alertDiv, cardBody.firstChild);

                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }
        }
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        // Only initialize on create form
        if (document.getElementById('formCreateLaporan')) {
            window.draftAutoSave = new DraftAutoSave('formCreateLaporan');
        }
    });

    // Export for external use
    window.DraftAutoSave = DraftAutoSave;
})();
