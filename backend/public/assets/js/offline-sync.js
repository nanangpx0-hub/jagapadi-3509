(function() {
  'use strict';

  var DB_NAME = 'jagapadi_offline';
  var DB_VERSION = 1;
  var STORE_MASTER = 'master_data';
  var STORE_DRAFTS = 'offline_drafts';
  var SYNC_KEY = 'jagapadi_sync_queue';
  var MASTER_TTL = 86400000;

  var db = null;

  function openDB() {
    return new Promise(function(resolve, reject) {
      if (db) return resolve(db);
      var req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = function(e) {
        var d = e.target.result;
        if (!d.objectStoreNames.contains(STORE_MASTER)) {
          d.createObjectStore(STORE_MASTER, { keyPath: 'key' });
        }
        if (!d.objectStoreNames.contains(STORE_DRAFTS)) {
          var store = d.createObjectStore(STORE_DRAFTS, { keyPath: 'id', autoIncrement: true });
          store.createIndex('status', 'status', { unique: false });
        }
      };
      req.onsuccess = function(e) { db = e.target.result; resolve(db); };
      req.onerror = function() { reject(req.error); };
    });
  }

  function storeMaster(key, data) {
    return openDB().then(function(db) {
      var tx = db.transaction(STORE_MASTER, 'readwrite');
      tx.objectStore(STORE_MASTER).put({ key: key, data: data, storedAt: Date.now() });
      return new Promise(function(resolve) { tx.oncomplete = resolve; });
    });
  }

  function getMaster(key) {
    return openDB().then(function(db) {
      return new Promise(function(resolve) {
        var tx = db.transaction(STORE_MASTER, 'readonly');
        var req = tx.objectStore(STORE_MASTER).get(key);
        req.onsuccess = function() {
          var entry = req.result;
          if (entry && (Date.now() - entry.storedAt) < MASTER_TTL) {
            resolve(entry.data);
          } else {
            if (entry) {
              var delTx = db.transaction(STORE_MASTER, 'readwrite');
              delTx.objectStore(STORE_MASTER).delete(key);
            }
            resolve(null);
          }
        };
        req.onerror = function() { resolve(null); };
      });
    });
  }

  function saveDraftOffline(data) {
    var draft = {
      fields: data,
      status: 'unsynced',
      createdAt: Date.now(),
      retries: 0
    };
    return openDB().then(function(db) {
      return new Promise(function(resolve, reject) {
        var tx = db.transaction(STORE_DRAFTS, 'readwrite');
        var req = tx.objectStore(STORE_DRAFTS).add(draft);
        req.onsuccess = function() { resolve(req.result); };
        req.onerror = function() { reject(req.error); };
      });
    });
  }

  function getUnsyncedDrafts() {
    return openDB().then(function(db) {
      return new Promise(function(resolve) {
        var results = [];
        var tx = db.transaction(STORE_DRAFTS, 'readonly');
        var req = tx.objectStore(STORE_DRAFTS).index('status').getAll('unsynced');
        req.onsuccess = function() { resolve(req.result || []); };
        req.onerror = function() { resolve([]); };
      });
    });
  }

  function markDraftSynced(id) {
    return openDB().then(function(db) {
      var tx = db.transaction(STORE_DRAFTS, 'readwrite');
      var store = tx.objectStore(STORE_DRAFTS);
      store.put({ id: id, status: 'synced' });
      return new Promise(function(resolve) { tx.oncomplete = resolve; });
    });
  }

  function incrementRetry(id, fields) {
    return openDB().then(function(db) {
      var tx = db.transaction(STORE_DRAFTS, 'readwrite');
      var store = tx.objectStore(STORE_DRAFTS);
      fields.retries = (fields.retries || 0) + 1;
      fields.status = 'failed';
      store.put(fields);
      return new Promise(function(resolve) { tx.oncomplete = resolve; });
    });
  }

  function cleanupOldDrafts() {
    var cutoff = Date.now() - 604800000;
    return openDB().then(function(db) {
      var tx = db.transaction(STORE_DRAFTS, 'readwrite');
      var store = tx.objectStore(STORE_DRAFTS);
      var req = store.openCursor();
      req.onsuccess = function(e) {
        var cursor = e.target.result;
        if (cursor) {
          if (cursor.value.createdAt < cutoff || cursor.value.status === 'synced') {
            store.delete(cursor.key);
          }
          cursor.continue();
        }
      };
    });
  }

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function processSyncQueue() {
    if (!navigator.onLine) return;

    getUnsyncedDrafts().then(function(drafts) {
      drafts.forEach(function(draft) {
        if (draft.retries >= 3) return;
        var formData = new FormData();
        formData.append('action', 'submit');
        formData.append('_csrf_token', getCsrfToken());
        Object.keys(draft.fields).forEach(function(k) {
          formData.append(k, draft.fields[k]);
        });

        fetch('/laporan-hama/light/store', {
          method: 'POST',
          body: formData
        }).then(function(resp) {
          if (resp.ok) {
            markDraftSynced(draft.id);
          } else {
            incrementRetry(draft.id, draft);
          }
        }).catch(function() {
          incrementRetry(draft.id, draft);
        });
      });
    });
  }

  function refreshMasterData() {
    var endpoints = [
      { key: 'opt_list', url: '/api/v1/opt' },
      { key: 'kecamatan_list', url: '/wilayah/kecamatan-json?kabupaten_id=1' },
    ];
    endpoints.forEach(function(ep) {
      getMaster(ep.key).then(function(cached) {
        if (cached) return;
        fetch(ep.url)
          .then(function(r) { return r.json(); })
          .then(function(data) { storeMaster(ep.key, data); })
          .catch(function() {});
      });
    });
  }

  function init() {
    cleanupOldDrafts();
    refreshMasterData();
    window.addEventListener('online', function() {
      processSyncQueue();
    });
    setInterval(processSyncQueue, 60000);
  }

  var OfflineSync = {
    init: init,
    saveDraft: saveDraftOffline,
    getMaster: getMaster,
    processSync: processSyncQueue,
    cleanup: cleanupOldDrafts
  };

  if (typeof window !== 'undefined') {
    window.OfflineSync = OfflineSync;
  }

  if (document.readyState === 'complete') {
    init();
  } else {
    document.addEventListener('DOMContentLoaded', init);
  }
})();
