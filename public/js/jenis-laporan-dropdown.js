/**
 * JAGAPADI — Dropdown dinamis Jenis Laporan + OPT
 *
 * Modul frontend untuk mengisi <select> jenis laporan / OPT secara otomatis
 * tanpa memuat ulang halaman (fetch + render + event).
 *
 * Seluruh fungsi pengelola item dropdown diorganisir di dalam namespace
 * `window.OPT` (BAGIAN OPT) agar struktur kode rapi dan mudah dipelihara.
 *
 * Data source (runtime root/integrated, session + CSRF):
 * - Jenis laporan (read): GET /api/v1/jenis-laporan -> { success, data: [...] }
 * - OPT (full CRUD)     : GET /api/opt, POST /api/opt,
 *                         GET|PUT|DELETE /api/opt/{id}
 *
 * Server tetap menjadi sumber kebenaran untuk validasi & otorisasi.
 * Modul ini hanya progressive enhancement di atas <option> yang
 * sudah di-render server (fallback no-JS tetap berfungsi).
 *
 * @version 1.2.0
 */
(function () {
  'use strict';

  /* ================================================================
   * BAGIAN OPT — namespace terpusat untuk semua operasi dropdown.
   * Semua fungsi CRUD / validasi / event dropdown WAJIB tinggal di sini.
   * ============================================================== */
  window.OPT = window.OPT || {};

  var DEFAULTS = {
    jenisEndpoint: 'api/v1/jenis-laporan',
    optEndpoint: 'api/opt',
    // Urutan endpoint yang dicoba: web JSON runtime root (session) dulu
    // karena tetap satu origin dengan halaman pada semua topologi deployment
    // (/api/* pada sebagian produksi dilayani Backend v1 tanpa route ini),
    // lalu API internal sebagai cadangan.
    jenisEndpoints: ['laporan-lainnya/jenis-list', 'api/v1/jenis-laporan'],
    optEndpoints: ['opt/list-json', 'api/opt'],
    timeoutMs: 12000,
    retryCount: 1,
  };

  function getBaseUrl() {
    if (typeof window.JAGAPADI_BASE_URL === 'string' && window.JAGAPADI_BASE_URL) {
      return window.JAGAPADI_BASE_URL.replace(/\/?$/, '/');
    }
    var base = document.querySelector('meta[name="jagapadi-base-url"]');
    if (base && base.content) {
      return String(base.content).replace(/\/?$/, '/');
    }
    if (typeof window.BASE_URL === 'string' && window.BASE_URL) {
      return String(window.BASE_URL).replace(/\/?$/, '/');
    }
    return '/';
  }

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    var input = document.querySelector('input[name="_csrf_token"]');
    if (input && input.value) return input.value;
    if (typeof window.JAGAPADI_CSRF_TOKEN === 'string') return window.JAGAPADI_CSRF_TOKEN;
    return '';
  }

  /** Escape teks untuk disisipkan aman ke DOM (cegah XSS). */
  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function normalizeList(payload) {
    if (Array.isArray(payload)) return payload;
    if (payload && Array.isArray(payload.data)) return payload.data;
    if (payload && payload.data && Array.isArray(payload.data.data)) return payload.data.data;
    return [];
  }

  function fetchWithTimeout(url, options, timeoutMs) {
    var controller = ('AbortController' in window) ? new AbortController() : null;
    var timer = null;
    var opts = options || {};
    if (controller) {
      opts.signal = controller.signal;
      timer = setTimeout(function () { controller.abort(); }, timeoutMs || DEFAULTS.timeoutMs);
    }
    return fetch(url, opts).finally(function () {
      if (timer) clearTimeout(timer);
    });
  }

  function readErrorMessage(payload, fallback) {
    if (payload && typeof payload.message === 'string' && payload.message.trim() !== '') {
      return payload.message;
    }
    return fallback;
  }

  /** GET JSON dengan pesan error berstatus (baca body sekali sebagai teks). */
  function fetchJsonWithStatus(url, fetchOptions) {
    return fetchWithTimeout(url, fetchOptions).then(function (res) {
      if (!res.ok) {
        return res.text().then(function (body) {
          var serverMsg = '';
          try {
            var parsed = JSON.parse(body);
            if (parsed && (parsed.message || parsed.error)) {
              serverMsg = parsed.message || parsed.error;
            }
          } catch (e) { /* body bukan JSON (mis. halaman HTML) */ }
          throw new Error('HTTP ' + res.status + (serverMsg ? ': ' + serverMsg : ''));
        }, function () {
          throw new Error('HTTP ' + res.status);
        });
      }
      return res.json();
    });
  }

  /**
   * Coba daftar URL berurutan (fallback berlapis). Setiap URL di-retry
   * sesuai `retry`; kegagalan tiap URL dicatat ke console beserta URL-nya
   * untuk diagnosis, dan hanya kegagalan terakhir yang diteruskan.
   */
  function fetchJsonFromUrls(urls, fetchOptions, retry) {
    var maxRetry = (retry != null ? retry : DEFAULTS.retryCount);
    function attemptUrl(index, left) {
      return fetchJsonWithStatus(urls[index], fetchOptions).catch(function (err) {
        console.warn('[OPT] fetch gagal (' + urls[index] + '):', err);
        if (left > 0) return attemptUrl(index, left - 1);
        if (index + 1 < urls.length) return attemptUrl(index + 1, maxRetry);
        throw err;
      });
    }
    return attemptUrl(0, maxRetry);
  }

  /* ---------------------------------------------------------------
   * BAGIAN OPT — JenisLaporanDropdown
   * Mengelola <select id="jenisSelect"> (master_jenis_laporan).
   * ------------------------------------------------------------- */
  var JenisLaporanDropdown = {
    state: {
      items: [],
      loading: false,
      loadedAt: null,
      selectedId: '',
      error: '',
    },
    _listeners: { change: [], load: [], error: [] },
    _boundSelects: [],

    /** Daftarkan listener: 'change' | 'load' | 'error'. */
    on: function (eventName, callback) {
      if (this._listeners[eventName] && typeof callback === 'function') {
        this._listeners[eventName].push(callback);
      }
      return this;
    },

    _emit: function (eventName, detail) {
      (this._listeners[eventName] || []).forEach(function (fn) {
        try { fn(detail); } catch (err) { console.error('[OPT] listener error:', err); }
      });
    },

    /** Validasi satu item jenis laporan (dipakai sebelum create/update). */
    validateItem: function (item) {
      var errors = [];
      var nama = item && item.nama != null ? String(item.nama).trim() : '';
      var kode = item && item.kode != null ? String(item.kode).trim() : '';
      if (nama === '') errors.push('Nama jenis laporan wajib diisi.');
      if (nama.length > 150) errors.push('Nama jenis laporan maksimal 150 karakter.');
      if (kode !== '' && !/^[a-z0-9_]{2,60}$/.test(kode)) {
        errors.push('Kode hanya boleh huruf kecil, angka, dan underscore (2-60 karakter).');
      }
      if (item && item.fields_json !== undefined && item.fields_json !== null && item.fields_json !== '') {
        try {
          var parsed = typeof item.fields_json === 'string' ? JSON.parse(item.fields_json) : item.fields_json;
          if (!Array.isArray(parsed)) errors.push('fields_json harus berupa array JSON.');
        } catch (e) {
          errors.push('fields_json harus berupa JSON valid.');
        }
      }
      return { valid: errors.length === 0, errors: errors };
    },

    /** Validasi pilihan pengguna pada <select>. */
    validateSelection: function (value) {
      var v = String(value == null ? '' : value).trim();
      if (v === '') return { valid: false, errors: ['Jenis laporan wajib dipilih.'] };
      if (!/^\d+$/.test(v)) return { valid: false, errors: ['Pilihan jenis laporan tidak valid.'] };
      var known = this.state.items.some(function (it) { return String(it.id) === v; });
      // Item dari server-render awal tetap dianggap valid walau cache belum dimuat.
      if (this.state.items.length > 0 && !known) {
        return { valid: false, errors: ['Jenis laporan yang dipilih tidak terdaftar. Muat ulang daftar.'] };
      }
      return { valid: true, errors: [] };
    },

    /** READ — ambil daftar jenis aktif tanpa reload halaman.
     * Opsi `silent: true` dipakai untuk background refresh: saat gagal,
     * daftar server-render tetap dipakai dan tidak ada box error
     * (hanya console.warn) agar tidak mengganggu pengisian form. */
    load: function (options) {
      var self = this;
      var opts = options || {};
      var silent = opts.silent === true;
      var base = getBaseUrl();
      var bust = '?_=' + Date.now();
      var endpoints = opts.endpoints || DEFAULTS.jenisEndpoints;
      var urls = endpoints.map(function (ep) { return base + ep + bust; });
      self.state.loading = true;
      if (!silent) {
        self.setLoading(true, opts);
        self.clearError(opts);
      }

      return fetchJsonFromUrls(urls, {
        method: 'GET',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      }, opts.retry).then(function (payload) {
          var items = normalizeList(payload);
          self.state.items = items;
          self.state.loadedAt = new Date().toISOString();
          self.render(opts);
          self._emit('load', { items: items });
          return items;
        }).catch(function (err) {
          if (silent) {
            // Fallback diam: daftar server-render tetap berlaku.
            console.warn('[OPT][jenis-laporan] background refresh gagal, memakai daftar server:', err);
            self._emit('error', {
              message: String((err && err.message) || err),
              silent: true,
              originalError: err,
            });
            return self.state.items;
          }
          var detail = (err && err.message && err.message.indexOf('HTTP') === 0)
            ? ' (' + err.message + ')'
            : '';
          var msg = (err && err.name === 'AbortError')
            ? 'Memuat jenis laporan timeout. Periksa koneksi lalu tekan Muat Ulang.'
            : 'Gagal memuat jenis laporan' + detail + '. Periksa koneksi lalu tekan Muat Ulang.';
          self.handleError(msg, opts, err);
          throw err;
        })
        .finally(function () {
          self.state.loading = false;
          if (!silent) self.setLoading(false, opts);
        });
    },

    /** Alias realtime: muat ulang daftar tanpa reload halaman. */
    refresh: function (options) {
      return this.load(options);
    },

    /** Render <option> ke <select>, pertahankan pilihan & fallback server. */
    render: function (options) {
      var opts = options || {};
      var select = opts.select || document.getElementById(opts.selectId || 'jenisSelect');
      if (!select) return 0;
      var keep = String(opts.keepValue != null ? opts.keepValue : (select.value || this.state.selectedId || ''));
      var placeholder = opts.placeholder || '-- Pilih Jenis Laporan --';

      // Simpan option server-render sebagai fallback bila API kosong.
      if (!select.dataset.optServerFallback && select.options.length > 0) {
        select.dataset.optServerFallback = '1';
      }

      select.innerHTML = '';
      var ph = document.createElement('option');
      ph.value = '';
      ph.textContent = placeholder;
      select.appendChild(ph);

      this.state.items.forEach(function (item) {
        var opt = document.createElement('option');
        opt.value = String(item.id);
        opt.textContent = item.nama || ('Jenis #' + item.id);
        if (item.fields_json) opt.setAttribute('data-fields', String(item.fields_json));
        if (item.kode) opt.setAttribute('data-kode', String(item.kode));
        select.appendChild(opt);
      });

      if (keep && select.querySelector('option[value="' + keep.replace(/"/g, '') + '"]')) {
        select.value = keep;
      } else {
        select.value = '';
      }
      this.state.selectedId = select.value;
      if (typeof opts.onRender === 'function') {
        try { opts.onRender(select, this.state.items); } catch (e) { console.error(e); }
      }
      return select.options.length;
    },

    getAll: function () {
      return this.state.items.slice();
    },

    /** READ satu item dari cache lokal. */
    getById: function (id) {
      var key = String(id);
      for (var i = 0; i < this.state.items.length; i++) {
        if (String(this.state.items[i].id) === key) return this.state.items[i];
      }
      return null;
    },

    /**
     * CREATE — validasi lokal lalu POST ke endpoint bila tersedia.
     * Backend canonical saat ini hanya mengekspos GET; bila server
     * menjawab 404/405/501, kembalikan error ramah tanpa merusak form.
     */
    createItem: function (item, options) {
      var self = this;
      var check = self.validateItem(item || {});
      if (!check.valid) return Promise.reject({ errors: check.errors });
      var base = getBaseUrl();
      var token = getCsrfToken();
      return fetchWithTimeout(base + DEFAULTS.jenisEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
        body: JSON.stringify(item),
      }).then(function (res) {
        if (res.status === 404 || res.status === 405 || res.status === 501) {
          throw new Error('Penambahan jenis laporan hanya dapat dilakukan Admin melalui halaman Master Jenis Laporan. Pilihan Anda tetap tervalidasi di daftar aktif.');
        }
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      }).then(function () {
        return self.refresh(options);
      });
    },

    /** UPDATE — validasi lokal lalu PUT bila endpoint tersedia. */
    updateItem: function (id, item, options) {
      var self = this;
      if (!/^\d+$/.test(String(id))) return Promise.reject({ errors: ['ID jenis laporan tidak valid.'] });
      var check = self.validateItem(item || {});
      if (!check.valid) return Promise.reject({ errors: check.errors });
      var base = getBaseUrl();
      var token = getCsrfToken();
      return fetchWithTimeout(base + DEFAULTS.jenisEndpoint + '/' + encodeURIComponent(String(id)), {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
        body: JSON.stringify(item),
      }).then(function (res) {
        if (res.status === 404 || res.status === 405 || res.status === 501) {
          throw new Error('Perubahan jenis laporan hanya dapat dilakukan Admin melalui halaman Master Jenis Laporan.');
        }
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      }).then(function () {
        return self.refresh(options);
      });
    },

    /** DELETE — konfirmasi + validasi lalu DELETE bila endpoint tersedia. */
    deleteItem: function (id, options) {
      var self = this;
      if (!/^\d+$/.test(String(id))) return Promise.reject({ errors: ['ID jenis laporan tidak valid.'] });
      var base = getBaseUrl();
      var token = getCsrfToken();
      return fetchWithTimeout(base + DEFAULTS.jenisEndpoint + '/' + encodeURIComponent(String(id)), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
      }).then(function (res) {
        if (res.status === 404 || res.status === 405 || res.status === 501) {
          throw new Error('Penghapusan jenis laporan hanya dapat dilakukan Admin melalui halaman Master Jenis Laporan.');
        }
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      }).then(function () {
        return self.refresh(options);
      });
    },

    /** Event listener pilihan pengguna: validasi + emit + render field dinamis. */
    onSelect: function (value, options) {
      var opts = options || {};
      var check = this.validateSelection(value);
      this.state.selectedId = String(value == null ? '' : value);
      var item = this.getById(this.state.selectedId);
      var detail = { value: this.state.selectedId, item: item, valid: check.valid, errors: check.errors };
      this._emit('change', detail);
      // Event DOM global agar modul lain (mis. renderer field dinamis) bisa mendengar.
      try {
        document.dispatchEvent(new CustomEvent('jenis-laporan:change', { detail: detail }));
      } catch (e) { /* IE11: abaikan */ }
      if (!check.valid && opts.showAlert !== false) {
        this.handleError(check.errors[0], opts, null);
      } else if (check.valid) {
        this.clearError(opts);
      }
      return detail;
    },

    /** Ambil state awal dari <option> server-render (tanpa fetch).
     * Mengembalikan jumlah item yang berhasil di-hydrate. */
    hydrateFromSelect: function (selectOrId) {
      var select = typeof selectOrId === 'string'
        ? document.getElementById(selectOrId)
        : (selectOrId || document.getElementById('jenisSelect'));
      if (!select || select.options.length <= 1) return 0;
      var items = [];
      Array.from(select.options).forEach(function (opt) {
        if (!opt.value) return; // lewati placeholder
        items.push({
          id: opt.value,
          nama: (opt.textContent || '').trim(),
          kode: opt.getAttribute('data-kode') || '',
          fields_json: opt.getAttribute('data-fields') || '',
        });
      });
      if (items.length > 0) {
        this.state.items = items;
        this.state.selectedId = select.value || '';
        this.state.loadedAt = this.state.loadedAt || 'server-render';
      }
      return items.length;
    },

    /** Pasang listener + tombol muat-ulang + state awal ke sebuah <select>. */
    bind: function (selectOrId, options) {
      var self = this;
      var opts = options || {};
      var select = typeof selectOrId === 'string' ? document.getElementById(selectOrId) : selectOrId;
      if (!select) return null;
      if (select.dataset.optJenisBound === '1') return select;
      select.dataset.optJenisBound = '1';
      self._boundSelects.push(select);

      select.addEventListener('change', function () {
        var detail = self.onSelect(select.value, { select: select, showAlert: false });
        if (typeof opts.onChange === 'function') {
          try { opts.onChange(detail, select); } catch (e) { console.error(e); }
        }
        // Tandai visual valid/invalid tanpa memblokir submit (server final).
        select.classList.remove('is-invalid', 'is-valid');
        if (select.value === '') {
          select.classList.add('is-invalid');
        } else if (detail.valid) {
          select.classList.add('is-valid');
        }
      });

      if (opts.autoLoad !== false) {
        var hydratedCount = 0;
        try {
          hydratedCount = self.hydrateFromSelect(select);
        } catch (e) { console.error(e); }
        if (hydratedCount > 0) {
          // Server sudah me-render daftar lengkap: pakai sebagai state awal,
          // lalu segarkan diam-diam (silent) tanpa box error bila gagal.
          // Refresh eksplisit via tombol tetap non-silent (menampilkan error).
          self.clearError({ select: select });
          self.load({ select: select, keepValue: select.value, ...opts, silent: true }).catch(function () {});
        } else {
          // Tidak ada fallback server: tampilkan error bila gagal.
          self.load({ select: select, keepValue: select.value, ...opts }).catch(function () {
            // Fallback: biarkan option server tetap tampil; error sudah ditampilkan.
          });
        }
      }
      return select;
    },

    setLoading: function (isLoading, options) {
      var select = (options && options.select) || document.getElementById((options && options.selectId) || 'jenisSelect');
      if (select) {
        select.classList.toggle('is-loading', !!isLoading);
        select.setAttribute('aria-busy', isLoading ? 'true' : 'false');
      }
      var btn = (options && options.refreshButton)
        || document.querySelector('[data-jenis-refresh]');
      if (btn) {
        btn.disabled = !!isLoading;
        btn.classList.toggle('is-loading', !!isLoading);
      }
    },

    /** Penanganan error terpusat: tampilkan box ramah + pertahankan fallback. */
    handleError: function (message, options, originalError) {
      var opts = options || {};
      this.state.error = String(message || 'Terjadi kesalahan.');
      if (originalError) console.error('[OPT][jenis-laporan]', originalError);
      var box = opts.errorBox
        || (opts.select && opts.select.parentElement
          ? opts.select.parentElement.querySelector('[data-jenis-error]')
          : null)
        || document.querySelector('[data-jenis-error]');
      if (box) {
        box.innerHTML = '<i class="fas fa-exclamation-triangle" aria-hidden="true"></i> '
          + '<span>' + escapeHtml(this.state.error) + '</span>'
          + ' <button type="button" class="btn btn-sm btn-outline-danger ml-2" data-jenis-retry>Bisa dicoba lagi</button>';
        box.style.display = 'block';
        box.setAttribute('role', 'alert');
        var retry = box.querySelector('[data-jenis-retry]');
        if (retry) {
          var self = this;
          retry.addEventListener('click', function () { self.refresh(opts); });
        }
      }
      this._emit('error', { message: this.state.error, originalError: originalError || null });
      return this.state.error;
    },

    clearError: function (options) {
      this.state.error = '';
      var box = (options && options.errorBox)
        || document.querySelector('[data-jenis-error]');
      if (box) {
        box.style.display = 'none';
        box.innerHTML = '';
        box.removeAttribute('role');
      }
    },
  };

  /* ---------------------------------------------------------------
   * BAGIAN OPT — OptDropdown (master_opt untuk form laporan hama).
   * Memakai endpoint /api/opt yang sudah mendukung CRUD penuh.
   * ------------------------------------------------------------- */
  var OptDropdown = {
    state: { items: [], loading: false, loadedAt: null, selectedId: '', error: '' },
    _listeners: { change: [], load: [], error: [] },

    on: function (eventName, callback) {
      if (this._listeners[eventName] && typeof callback === 'function') {
        this._listeners[eventName].push(callback);
      }
      return this;
    },

    _emit: function (eventName, detail) {
      (this._listeners[eventName] || []).forEach(function (fn) {
        try { fn(detail); } catch (err) { console.error('[OPT] listener error:', err); }
      });
    },

    validateItem: function (item) {
      var errors = [];
      var nama = item && item.nama_opt != null ? String(item.nama_opt).trim() : '';
      var jenis = item && item.jenis != null ? String(item.jenis).trim() : '';
      if (nama === '') errors.push('Nama OPT wajib diisi.');
      if (nama.length > 150) errors.push('Nama OPT maksimal 150 karakter.');
      if (jenis !== '' && ['hama', 'penyakit', 'gulma'].indexOf(jenis) === -1) {
        errors.push('Jenis OPT tidak valid (hama/penyakit/gulma).');
      }
      return { valid: errors.length === 0, errors: errors };
    },

    validateSelection: function (value) {
      var v = String(value == null ? '' : value).trim();
      if (v === '') return { valid: false, errors: ['OPT wajib dipilih.'] };
      if (!/^\d+$/.test(v)) return { valid: false, errors: ['Pilihan OPT tidak valid.'] };
      return { valid: true, errors: [] };
    },

    _listUrls: function (extra) {
      var base = getBaseUrl();
      var params = new URLSearchParams({ limit: '100', _: String(Date.now()) });
      if (extra && extra.search) params.set('search', extra.search);
      if (extra && extra.jenis) params.set('jenis', extra.jenis);
      var query = params.toString();
      var endpoints = (extra && extra.endpoints) || DEFAULTS.optEndpoints;
      return endpoints.map(function (ep) { return base + ep + '?' + query; });
    },

    load: function (options) {
      var self = this;
      var opts = options || {};
      var silent = opts.silent === true;
      self.state.loading = true;
      var select = opts.select || document.getElementById(opts.selectId || 'masterOptSelect');
      if (select && !silent) { select.classList.add('is-loading'); select.setAttribute('aria-busy', 'true'); }
      if (!silent) self.clearError(opts);

      return fetchJsonFromUrls(self._listUrls(opts), {
        method: 'GET',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      }, opts.retry).then(function (payload) {
        var items = normalizeList(payload);
        if (silent && select && select.options.length > 1 && items.length < select.options.length - 1) {
          // API mengembalikan lebih sedikit dari daftar server-render yang
          // lengkap: pertahankan daftar server agar opsi tidak hilang.
          console.warn('[OPT][opt] background refresh mengembalikan data lebih sedikit; daftar server dipertahankan.');
          self._emit('load', { items: self.state.items, keptServerList: true });
          return self.state.items;
        }
        self.state.items = items;
        self.state.loadedAt = new Date().toISOString();
        self.render({ select: select, keepValue: select ? select.value : '', ...opts });
        self._emit('load', { items: items });
        return items;
      }).catch(function (err) {
        if (silent) {
          console.warn('[OPT][opt] background refresh gagal, memakai daftar server:', err);
          self._emit('error', {
            message: String((err && err.message) || err),
            silent: true,
            originalError: err,
          });
          return self.state.items;
        }
        var detail = (err && err.message && err.message.indexOf('HTTP') === 0)
          ? ' (' + err.message + ')'
          : '';
        var msg = (err && err.name === 'AbortError')
          ? 'Memuat data OPT timeout. Coba lagi.'
          : 'Gagal memuat data OPT' + detail + '. Data awal tetap dapat dipilih.';
        self.handleError(msg, { select: select, ...opts }, err);
        throw err;
      }).finally(function () {
        self.state.loading = false;
        if (select) { select.classList.remove('is-loading'); select.setAttribute('aria-busy', 'false'); }
      });
    },

    refresh: function (options) {
      return this.load(options);
    },

    render: function (options) {
      var opts = options || {};
      var select = opts.select || document.getElementById(opts.selectId || 'masterOptSelect');
      if (!select) return 0;
      var keep = String(opts.keepValue != null ? opts.keepValue : (select.value || ''));
      if (this.state.items.length === 0) return select.options.length; // pertahankan fallback server
      select.innerHTML = '';
      var ph = document.createElement('option');
      ph.value = '';
      ph.textContent = opts.placeholder || '-- Pilih OPT --';
      select.appendChild(ph);
      this.state.items.forEach(function (item) {
        var opt = document.createElement('option');
        opt.value = String(item.id);
        var label = item.nama_opt || ('OPT #' + item.id);
        if (item.nama_lokal) label += ' (' + item.nama_lokal + ')';
        opt.textContent = label;
        var hay = ((item.nama_opt || '') + ' ' + (item.nama_lokal || '') + ' ' + (item.nama_ilmiah || '')).toLowerCase();
        opt.setAttribute('data-search', hay);
        if (item.foto_url) opt.setAttribute('data-photo', String(item.foto_url));
        select.appendChild(opt);
      });
      if (keep && select.querySelector('option[value="' + keep.replace(/"/g, '') + '"]')) {
        select.value = keep;
      }
      this.state.selectedId = select.value;
      return select.options.length;
    },

    getAll: function () {
      return this.state.items.slice();
    },

    getById: function (id) {
      var key = String(id);
      for (var i = 0; i < this.state.items.length; i++) {
        if (String(this.state.items[i].id) === key) return this.state.items[i];
      }
      return null;
    },

    _mutate: function (method, id, body) {
      var base = getBaseUrl();
      var token = getCsrfToken();
      var url = base + DEFAULTS.optEndpoint + (id ? '/' + encodeURIComponent(String(id)) : '');
      return fetchWithTimeout(url, {
        method: method,
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
        ...(body ? { body: JSON.stringify(body) } : {}),
      }).then(function (res) {
        return res.json().then(function (payload) {
          if (!res.ok) throw new Error(readErrorMessage(payload, 'HTTP ' + res.status));
          return payload && payload.data !== undefined ? payload.data : payload;
        });
      });
    },

    /** CREATE — hanya Admin (server menegakkan 403). */
    createItem: function (item, options) {
      var check = this.validateItem(item || {});
      if (!check.valid) return Promise.reject({ errors: check.errors });
      var self = this;
      return self._mutate('POST', null, item).then(function (created) {
        return self.refresh(options).then(function () { return created; });
      });
    },

    /** READ satu OPT dari server. */
    readItem: function (id) {
      if (!/^\d+$/.test(String(id))) return Promise.reject({ errors: ['ID OPT tidak valid.'] });
      var base = getBaseUrl();
      return fetchWithTimeout(base + DEFAULTS.optEndpoint + '/' + encodeURIComponent(String(id)), {
        method: 'GET',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      }).then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      }).then(function (payload) {
        return payload && payload.data !== undefined ? payload.data : payload;
      });
    },

    /** UPDATE — hanya Admin (server menegakkan 403). */
    updateItem: function (id, item, options) {
      if (!/^\d+$/.test(String(id))) return Promise.reject({ errors: ['ID OPT tidak valid.'] });
      var check = this.validateItem({ ...(item || {}), jenis: (item && item.jenis) || 'hama' });
      // Jenis boleh kosong saat update parsial.
      if (item && !item.jenis) check = { valid: true, errors: [] };
      if (!check.valid) return Promise.reject({ errors: check.errors });
      var self = this;
      return self._mutate('PUT', id, item).then(function (updated) {
        return self.refresh(options).then(function () { return updated; });
      });
    },

    /** DELETE — hanya Admin & bila tidak dipakai laporan (server 400). */
    deleteItem: function (id, options) {
      if (!/^\d+$/.test(String(id))) return Promise.reject({ errors: ['ID OPT tidak valid.'] });
      var self = this;
      return self._mutate('DELETE', id, null).then(function (result) {
        return self.refresh(options).then(function () { return result; });
      });
    },

    onSelect: function (value, options) {
      var opts = options || {};
      var check = this.validateSelection(value);
      this.state.selectedId = String(value == null ? '' : value);
      var detail = { value: this.state.selectedId, item: this.getById(this.state.selectedId), valid: check.valid, errors: check.errors };
      this._emit('change', detail);
      try {
        document.dispatchEvent(new CustomEvent('opt:change', { detail: detail }));
      } catch (e) { /* abaikan */ }
      if (!check.valid && opts.showAlert !== false) {
        this.handleError(check.errors[0], opts, null);
      } else {
        this.clearError(opts);
      }
      return detail;
    },

    /** Filter lokal untuk kotak pencarian (debounce ditangani pemanggil). */
    filter: function (keyword, select) {
      var q = String(keyword || '').trim().toLowerCase();
      var box = select || document.getElementById('masterOptSelect');
      if (!box) return 0;
      var visible = 0;
      Array.from(box.options).forEach(function (opt, idx) {
        if (idx === 0 || q === '') {
          opt.hidden = false;
          if (idx > 0) visible++;
          return;
        }
        var hay = (opt.getAttribute('data-search') || opt.textContent || '').toLowerCase();
        var show = hay.indexOf(q) !== -1;
        opt.hidden = !show;
        if (show) visible++;
      });
      return visible;
    },

    /** Ambil state awal dari <option> server-render (tanpa fetch). */
    hydrateFromSelect: function (selectOrId) {
      var select = typeof selectOrId === 'string'
        ? document.getElementById(selectOrId)
        : (selectOrId || document.getElementById('masterOptSelect'));
      if (!select || select.options.length <= 1) return 0;
      var items = [];
      Array.from(select.options).forEach(function (opt) {
        if (!opt.value) return; // lewati placeholder
        items.push({
          id: opt.value,
          nama_opt: (opt.textContent || '').trim(),
          nama_lokal: '',
          nama_ilmiah: '',
          foto_url: opt.getAttribute('data-photo') || '',
        });
      });
      if (items.length > 0) {
        this.state.items = items;
        this.state.selectedId = select.value || '';
        this.state.loadedAt = this.state.loadedAt || 'server-render';
      }
      return items.length;
    },

    bind: function (selectOrId, options) {
      var self = this;
      var opts = options || {};
      var select = typeof selectOrId === 'string' ? document.getElementById(selectOrId) : selectOrId;
      if (!select || select.dataset.optDropdownBound === '1') return select;
      select.dataset.optDropdownBound = '1';

      select.addEventListener('change', function () {
        var detail = self.onSelect(select.value, { select: select, showAlert: false });
        select.classList.remove('is-invalid', 'is-valid');
        if (select.value === '') select.classList.add('is-invalid');
        else if (detail.valid) select.classList.add('is-valid');
        if (typeof opts.onChange === 'function') {
          try { opts.onChange(detail, select); } catch (e) { console.error(e); }
        }
      });

      var search = opts.searchInput
        || (opts.searchId ? document.getElementById(opts.searchId) : null)
        || document.getElementById('optSearch');
      if (search) {
        var timer = null;
        search.addEventListener('input', function () {
          clearTimeout(timer);
          timer = setTimeout(function () { self.filter(search.value, select); }, 150);
        });
      }

      if (opts.autoLoad !== false) {
        var hydratedCount = 0;
        try {
          hydratedCount = self.hydrateFromSelect(select);
        } catch (e) { console.error(e); }
        if (hydratedCount > 0) {
          // Daftar server sudah lengkap: segarkan diam-diam (silent).
          self.clearError({ select: select });
          self.load({ select: select, ...opts, silent: true }).catch(function () {});
        } else {
          self.load({ select: select, ...opts }).catch(function () { /* fallback server tetap tampil */ });
        }
      }
      return select;
    },

    handleError: function (message, options, originalError) {
      var opts = options || {};
      this.state.error = String(message || 'Terjadi kesalahan.');
      if (originalError) console.error('[OPT][opt-dropdown]', originalError);
      var box = opts.errorBox || document.querySelector('[data-opt-error]');
      if (box) {
        box.innerHTML = '<i class="fas fa-exclamation-triangle" aria-hidden="true"></i> '
          + '<span>' + escapeHtml(this.state.error) + '</span>';
        box.style.display = 'block';
        box.setAttribute('role', 'alert');
      }
      this._emit('error', { message: this.state.error, originalError: originalError || null });
      return this.state.error;
    },

    clearError: function (options) {
      this.state.error = '';
      var box = (options && options.errorBox) || document.querySelector('[data-opt-error]');
      if (box) {
        box.style.display = 'none';
        box.innerHTML = '';
        box.removeAttribute('role');
      }
    },
  };

  /* Ekspos fungsi-fungsi BAGIAN OPT secara terorganisir. */
  window.OPT.JenisLaporanDropdown = JenisLaporanDropdown;
  window.OPT.OptDropdown = OptDropdown;
  window.OPT._utils = {
    escapeHtml: escapeHtml,
    getBaseUrl: getBaseUrl,
    getCsrfToken: getCsrfToken,
  };

  /* Auto-bind progresif: cukup tambah atribut data pada <select>. */
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('select[data-jenis-laporan-dropdown]').forEach(function (select) {
      try { JenisLaporanDropdown.bind(select, { selectId: select.id }); } catch (e) { console.error(e); }
    });
    document.querySelectorAll('select[data-opt-dropdown]').forEach(function (select) {
      try { OptDropdown.bind(select, {}); } catch (e) { console.error(e); }
    });
  });
})();
