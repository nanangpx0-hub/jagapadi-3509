/**
 * Offline Laporan Manager
 * Enables offline form submission using IndexedDB and Background Sync API
 * 
 * Features:
 * - Stores form data in IndexedDB when offline
 * - Automatically syncs when online connection is restored
 * - Shows visual indicators for offline/online status
 * 
 * @version 1.0.0
 * @author JAGAPADI Team
 */
(function () {
    'use strict';

    const DB_NAME = 'jagapadi-offline';
    const DB_VERSION = 2;
    const STORE_NAME = 'laporan_drafts';

    class OfflineLaporanManager {
        constructor() {
            this.db = null;
            this.isOnline = navigator.onLine;
            this.form = document.getElementById('formCreateLaporan');

            if (this.form) {
                this.init();
            }
        }

        async init() {
            try {
                // Open/create IndexedDB
                this.db = await this.openDatabase();

                // Setup form interception for offline mode
                this.setupFormInterception();

                // Setup online/offline listeners
                this.setupConnectivityListeners();

                // Create offline indicator
                this.createOfflineIndicator();

                // Check for pending submissions on load
                await this.checkPendingSubmissions();

                console.log('[OfflineLaporan] Initialized successfully');
            } catch (error) {
                console.error('[OfflineLaporan] Initialization failed:', error);
            }
        }

        /**
         * Open IndexedDB database
         */
        openDatabase() {
            return new Promise((resolve, reject) => {
                const request = indexedDB.open(DB_NAME, DB_VERSION);

                request.onerror = () => reject(request.error);
                request.onsuccess = () => resolve(request.result);

                request.onupgradeneeded = (event) => {
                    const db = event.target.result;

                    // Create object store if it doesn't exist
                    if (!db.objectStoreNames.contains(STORE_NAME)) {
                        const store = db.createObjectStore(STORE_NAME, {
                            keyPath: 'id',
                            autoIncrement: true
                        });
                        store.createIndex('timestamp', 'timestamp', { unique: false });
                        store.createIndex('synced', 'synced', { unique: false });
                    }
                };
            });
        }

        /**
         * Setup form interception to save offline when no connection
         */
        setupFormInterception() {
            this.form.addEventListener('submit', async (e) => {
                // If offline, save to IndexedDB instead of submitting
                if (!navigator.onLine) {
                    e.preventDefault();
                    e.stopPropagation();

                    await this.saveOfflineSubmission();
                }
            });
        }

        /**
         * Save form data to IndexedDB for later sync
         */
        async saveOfflineSubmission() {
            try {
                const formData = new FormData(this.form);
                const data = {};

                formData.forEach((value, key) => {
                    // Skip file inputs for now (complex to store)
                    if (key !== 'foto') {
                        data[key] = value;
                    }
                });

                const submission = {
                    data: data,
                    timestamp: Date.now(),
                    synced: false,
                    attempts: 0
                };

                const tx = this.db.transaction(STORE_NAME, 'readwrite');
                const store = tx.objectStore(STORE_NAME);
                await store.add(submission);

                this.showNotification(
                    'Laporan disimpan offline. Akan dikirim otomatis saat koneksi tersedia.',
                    'warning'
                );

                // Register for background sync if available
                await this.registerBackgroundSync();

                // Update indicator
                this.updatePendingCount();

            } catch (error) {
                console.error('[OfflineLaporan] Error saving offline:', error);
                this.showNotification('Gagal menyimpan laporan offline', 'error');
            }
        }

        /**
         * Register for background sync
         */
        async registerBackgroundSync() {
            if ('serviceWorker' in navigator && 'sync' in window.SyncManager) {
                try {
                    const registration = await navigator.serviceWorker.ready;
                    await registration.sync.register('laporan-submit');
                    console.log('[OfflineLaporan] Background sync registered');
                } catch (error) {
                    console.error('[OfflineLaporan] Background sync failed:', error);
                }
            }
        }

        /**
         * Setup online/offline event listeners
         */
        setupConnectivityListeners() {
            window.addEventListener('online', () => {
                this.isOnline = true;
                this.updateOfflineIndicator(true);
                this.showNotification('Koneksi tersedia. Mengirim laporan tertunda...', 'info');
                this.syncPendingSubmissions();
            });

            window.addEventListener('offline', () => {
                this.isOnline = false;
                this.updateOfflineIndicator(false);
                this.showNotification('Koneksi terputus. Mode offline aktif.', 'warning');
            });
        }

        /**
         * Create offline status indicator
         */
        createOfflineIndicator() {
            const indicator = document.createElement('div');
            indicator.id = 'offlineIndicator';
            indicator.className = 'offline-indicator';
            indicator.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                padding: 10px 15px;
                border-radius: 8px;
                font-size: 0.9rem;
                z-index: 9999;
                display: none;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            `;

            document.body.appendChild(indicator);

            // Show if currently offline
            if (!navigator.onLine) {
                this.updateOfflineIndicator(false);
            }
        }

        /**
         * Update offline indicator appearance
         */
        updateOfflineIndicator(isOnline) {
            const indicator = document.getElementById('offlineIndicator');
            if (!indicator) return;

            if (isOnline) {
                indicator.style.display = 'none';
            } else {
                indicator.style.display = 'block';
                indicator.style.backgroundColor = '#f8d7da';
                indicator.style.color = '#721c24';
                indicator.style.border = '1px solid #f5c6cb';
                indicator.innerHTML = `
                    <i class="fas fa-wifi-slash"></i> Mode Offline
                    <span id="pendingCount" class="ml-2 badge badge-warning"></span>
                `;
                this.updatePendingCount();
            }
        }

        /**
         * Update pending submission count
         */
        async updatePendingCount() {
            const count = await this.getPendingCount();
            const badge = document.getElementById('pendingCount');
            if (badge && count > 0) {
                badge.textContent = `${count} tertunda`;
            }
        }

        /**
         * Get count of pending submissions
         */
        async getPendingCount() {
            try {
                const tx = this.db.transaction(STORE_NAME, 'readonly');
                const store = tx.objectStore(STORE_NAME);
                const index = store.index('synced');
                const request = index.count(IDBKeyRange.only(false));

                return new Promise((resolve) => {
                    request.onsuccess = () => resolve(request.result);
                    request.onerror = () => resolve(0);
                });
            } catch (e) {
                return 0;
            }
        }

        /**
         * Check for pending submissions on load
         */
        async checkPendingSubmissions() {
            const count = await this.getPendingCount();
            if (count > 0 && navigator.onLine) {
                this.showNotification(
                    `Ditemukan ${count} laporan tertunda. Mengirim ulang...`,
                    'info'
                );
                await this.syncPendingSubmissions();
            }
        }

        /**
         * Sync all pending submissions
         */
        async syncPendingSubmissions() {
            if (!navigator.onLine) return;

            try {
                const tx = this.db.transaction(STORE_NAME, 'readonly');
                const store = tx.objectStore(STORE_NAME);
                const pendingRequest = store.getAll();

                pendingRequest.onsuccess = async () => {
                    const pending = pendingRequest.result.filter(s => !s.synced);

                    for (const submission of pending) {
                        await this.syncSubmission(submission);
                    }

                    if (pending.length > 0) {
                        this.showNotification(
                            `${pending.length} laporan berhasil dikirim!`,
                            'success'
                        );
                    }
                };
            } catch (error) {
                console.error('[OfflineLaporan] Sync error:', error);
            }
        }

        /**
         * Sync a single submission
         */
        async syncSubmission(submission) {
            try {
                const formData = new FormData();
                Object.entries(submission.data).forEach(([key, value]) => {
                    formData.append(key, value);
                });

                const response = await fetch(this.form.action, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                if (response.ok || response.redirected) {
                    // Mark as synced
                    await this.markAsSynced(submission.id);
                    console.log('[OfflineLaporan] Synced submission:', submission.id);
                } else {
                    // Increment attempt counter
                    await this.incrementAttempts(submission.id);
                }
            } catch (error) {
                console.error('[OfflineLaporan] Sync submission error:', error);
                await this.incrementAttempts(submission.id);
            }
        }

        /**
         * Mark submission as synced
         */
        async markAsSynced(id) {
            const tx = this.db.transaction(STORE_NAME, 'readwrite');
            const store = tx.objectStore(STORE_NAME);
            const request = store.get(id);

            request.onsuccess = () => {
                const data = request.result;
                data.synced = true;
                data.syncedAt = Date.now();
                store.put(data);
            };
        }

        /**
         * Increment sync attempts
         */
        async incrementAttempts(id) {
            const tx = this.db.transaction(STORE_NAME, 'readwrite');
            const store = tx.objectStore(STORE_NAME);
            const request = store.get(id);

            request.onsuccess = () => {
                const data = request.result;
                data.attempts = (data.attempts || 0) + 1;
                store.put(data);
            };
        }

        /**
         * Show notification
         */
        showNotification(message, type = 'info') {
            const colors = {
                info: { bg: '#d1ecf1', text: '#0c5460', border: '#bee5eb' },
                success: { bg: '#d4edda', text: '#155724', border: '#c3e6cb' },
                warning: { bg: '#fff3cd', text: '#856404', border: '#ffeeba' },
                error: { bg: '#f8d7da', text: '#721c24', border: '#f5c6cb' }
            };

            const style = colors[type] || colors.info;

            const toast = document.createElement('div');
            toast.className = 'offline-toast';
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 8px;
                z-index: 10000;
                max-width: 350px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                background: ${style.bg};
                color: ${style.text};
                border: 1px solid ${style.border};
            `;
            toast.innerHTML = `<i class="fas fa-info-circle mr-2"></i>${message}`;

            document.body.appendChild(toast);

            setTimeout(() => toast.remove(), 4000);
        }
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('formCreateLaporan')) {
            window.offlineLaporanManager = new OfflineLaporanManager();
        }
    });

    // Export
    window.OfflineLaporanManager = OfflineLaporanManager;
})();
