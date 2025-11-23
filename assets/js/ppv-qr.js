/**
 * PunktePass – Kassenscanner & Kampagnen v6.1 CLEAN
 * Turbo.js compatible, clean architecture
 * FIXED: Multiple init() calls causing API spam
 * Author: Erik Borota / PunktePass
 */

(function() {
  'use strict';

  // ✅ DEBUG mode - set to true for verbose logging
  const PPV_DEBUG = false;
  const ppvLog = (...args) => { if (PPV_DEBUG) console.log(...args); };
  const ppvWarn = (...args) => { if (PPV_DEBUG) console.warn(...args); };

  // Guard against multiple script loads
  if (window.PPV_QR_LOADED) {
    ppvLog('[QR] Already loaded, skipping');
    return;
  }
  window.PPV_QR_LOADED = true;

  // ============================================================
  // GLOBAL STATE
  // ============================================================
  const STATE = {
    initialized: false,
    campaignManager: null,
    cameraScanner: null,
    scanProcessor: null,
    uiManager: null,
    lastInitTime: 0  // Prevent rapid re-init
  };

  const L = window.ppv_lang || {};

  // ============================================================
  // STORE CONFIG
  // ============================================================
  function getStoreKey() {
    return (window.PPV_STORE_KEY || '').trim() ||
           (window.PPV_STORE_DATA?.store_key || '').trim() ||
           (sessionStorage.getItem('ppv_store_key') || '').trim() || '';
  }

  function getStoreID() {
    return window.PPV_STORE_ID ||
           window.PPV_STORE_DATA?.store_id ||
           Number(sessionStorage.getItem('ppv_store_id')) || 0;
  }

  // Save to session
  if (getStoreKey()) sessionStorage.setItem('ppv_store_key', getStoreKey());
  if (getStoreID()) sessionStorage.setItem('ppv_store_id', getStoreID());

  // ============================================================
  // TOAST
  // ============================================================
  window.ppvToast = function(msg, type = 'info') {
    const box = document.createElement('div');
    box.className = 'ppv-toast ' + type;
    box.innerHTML = msg;
    document.body.appendChild(box);
    setTimeout(() => box.classList.add('show'), 10);
    setTimeout(() => box.classList.remove('show'), 3000);
    setTimeout(() => box.remove(), 3500);
  };

  // ============================================================
  // SCAN THROTTLE
  // ============================================================
  let lastScanTime = 0;
  function canProcessScan() {
    const now = Date.now();
    if (now - lastScanTime < 600) return false;
    lastScanTime = now;
    return true;
  }

  // ============================================================
  // STATUS BADGE
  // ============================================================
  function statusBadge(state) {
    const badges = {
      active: `<span style='color:#00e676'>🟢 ${L.state_active || 'Aktiv'}</span>`,
      archived: `<span style='color:#ffab00'>📦 ${L.state_archived || 'Archiviert'}</span>`,
      upcoming: `<span style='color:#2979ff'>🔵 ${L.state_upcoming || 'Geplant'}</span>`,
      expired: `<span style='color:#9e9e9e'>⚫ ${L.state_expired || 'Abgelaufen'}</span>`
    };
    return badges[state] || '';
  }

  // ============================================================
  // UI MANAGER
  // ============================================================
  class UIManager {
    constructor() {
      this.resultBox = null;
      this.logList = null;
      this.campaignList = null;
    }

    init() {
      this.resultBox = document.getElementById('ppv-pos-result');
      this.logList = document.getElementById('ppv-pos-log');
      this.campaignList = document.getElementById('ppv-campaign-list');
    }

    showMessage(text, type = 'info') {
      window.ppvToast(text, type);
    }

    clearLogTable() {
      if (!this.logList) return;
      this.logList.innerHTML = '';
    }

    addScanItem(log) {
      if (!this.logList) return;
      const item = document.createElement('div');
      item.className = `ppv-scan-item ${log.success ? 'success' : 'error'}`;

      // Build display name: Name > Email > #ID
      const name = log.customer_name || log.email || '#' + log.user_id;
      // Show email as subtitle if we have both name AND email
      const subtitle = (log.customer_name && log.email) ? log.email : (log.date_short + ' ' + log.time_short);
      // For errors, show message instead of time
      const detail = log.success ? (log.date_short + ' ' + log.time_short) : (log.message || '');

      item.innerHTML = `
        <div class="ppv-scan-status">${log.success ? '✓' : '✗'}</div>
        <div class="ppv-scan-info">
          <div class="ppv-scan-name">#${log.user_id} ${name}</div>
          <div class="ppv-scan-detail">${subtitle}</div>
        </div>
        <div class="ppv-scan-points">${log.points || '-'}</div>
      `;
      this.logList.appendChild(item);
    }

    flashCampaignList() {
      if (!this.campaignList) return;
      this.campaignList.scrollTo({ top: 0, behavior: 'smooth' });
      this.campaignList.style.transition = 'background 0.5s';
      this.campaignList.style.background = 'rgba(0,255,120,0.25)';
      setTimeout(() => this.campaignList.style.background = 'transparent', 600);
    }
  }

  // ============================================================
  // OFFLINE SYNC MANAGER
  // ============================================================
  class OfflineSyncManager {
    static STORAGE_KEY = 'ppv_offline_sync';
    static DUPLICATE_WINDOW = 2 * 60 * 1000;

    static save(qrCode) {
      try {
        let items = JSON.parse(localStorage.getItem(this.STORAGE_KEY) || '[]');
        const twoMinutesAgo = Date.now() - this.DUPLICATE_WINDOW;
        const isDuplicate = items.some(item => item.qr === qrCode && new Date(item.time).getTime() > twoMinutesAgo);

        if (isDuplicate) {
          window.ppvToast('⚠️ ' + (L.pos_duplicate || 'Bereits gescannt'), 'warning');
          return false;
        }

        items.push({
          id: `${getStoreKey()}-${qrCode}-${Date.now()}`,
          qr: qrCode,
          time: new Date().toISOString(),
          store_key: getStoreKey(),
          synced: false
        });

        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(items));
        return true;
      } catch (e) {
        return false;
      }
    }

    static async sync() {
      try {
        let items = JSON.parse(localStorage.getItem(this.STORAGE_KEY) || '[]');
        const unsynced = items.filter(i => !i.synced);
        if (!unsynced.length) return;

        const res = await fetch('/wp-json/punktepass/v1/pos/sync_offline', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'PPV-POS-Token': getStoreKey() },
          body: JSON.stringify({ scans: unsynced })
        });

        const result = await res.json();
        if (result.success) {
          const synced = unsynced.map(i => i.id);
          localStorage.setItem(this.STORAGE_KEY, JSON.stringify(items.filter(i => !synced.includes(i.id))));
          window.ppvToast(`✅ ${result.synced} ${L.pos_sync || 'synchronisiert'}`, 'success');
        }
      } catch (e) {
        ppvWarn('[QR] Sync error:', e);
      }
    }
  }

  // ============================================================
  // SCAN PROCESSOR
  // ============================================================
  class ScanProcessor {
    constructor(uiManager) {
      this.ui = uiManager;
    }

    async process(qrCode) {
      if (!qrCode || !getStoreKey()) return;
      if (!canProcessScan()) return;

      this.ui.showMessage('⏳ ' + (L.pos_checking || 'Wird geprüft...'), 'info');

      try {
        const res = await fetch('/wp-json/punktepass/v1/pos/scan', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'PPV-POS-Token': getStoreKey() },
          body: JSON.stringify({ qr: qrCode, store_key: getStoreKey(), points: 1 })
        });

        const data = await res.json();

        if (data.success) {
          this.ui.showMessage('✅ ' + data.message, 'success');
          this.ui.addLogRow(data.time || new Date().toLocaleString(), data.user_id || '-', '✅');
        } else {
          this.ui.showMessage('⚠️ ' + (data.message || ''), 'warning');
          if (!/bereits|gescannt|duplikat/i.test(data.message || '')) {
            OfflineSyncManager.save(qrCode);
          }
        }

        setTimeout(() => this.loadLogs(), 1000);
      } catch (e) {
        this.ui.showMessage('⚠️ ' + (L.server_error || 'Serverfehler'), 'error');
        OfflineSyncManager.save(qrCode);
      }
    }

    async loadLogs() {
      if (!getStoreKey()) return;
      ppvLog('[QR] 📡 loadLogs() called at', new Date().toLocaleTimeString());
      try {
        const res = await fetch('/wp-json/punktepass/v1/pos/logs', {
          headers: { 'PPV-POS-Token': getStoreKey() }
        });
        ppvLog('[QR] 📡 loadLogs() response:', res.status);
        const logs = await res.json();

        // Clear existing items before loading fresh data
        this.ui.clearLogTable();

        // Add logs (API returns newest first)
        (logs || []).forEach(l => this.ui.addScanItem(l));
      } catch (e) {
        ppvWarn('[QR] Failed to load logs:', e);
      }
    }
  }

  // ============================================================
  // CAMPAIGN MANAGER
  // ============================================================
  class CampaignManager {
    constructor(uiManager) {
      this.ui = uiManager;
      this.campaigns = [];
      this.editingId = 0;
      this.modal = null;
      this.list = null;
    }

    init() {
      this.list = document.getElementById('ppv-campaign-list');
      this.modal = document.getElementById('ppv-campaign-modal');

      // Move modal to body for proper z-index
      if (this.modal && this.modal.parentElement !== document.body) {
        document.body.appendChild(this.modal);
      }
    }

    async load() {
      if (!this.list) return;
      if (!getStoreKey()) {
        this.list.innerHTML = `<p style='text-align:center;color:#999;padding:20px;'>${L.camp_no_store || 'Kein Geschäft ausgewählt'}</p>`;
        return;
      }

      ppvLog('[QR] 📡 campaigns.load() called at', new Date().toLocaleTimeString());

      this.list.innerHTML = `<div class='ppv-loading'>⏳ ${L.camp_loading || 'Lade Kampagnen...'}</div>`;

      const filter = document.getElementById('ppv-campaign-filter')?.value || 'active';

      try {
        const res = await fetch('/wp-json/punktepass/v1/pos/campaigns', {
          headers: { 'PPV-POS-Token': getStoreKey() }
        });
        ppvLog('[QR] 📡 campaigns.load() response:', res.status);
        const data = await res.json();

        this.list.innerHTML = '';

        if (!data || !data.length) {
          this.list.innerHTML = `<p>${L.camp_none || 'Keine Kampagnen'}</p>`;
          return;
        }

        this.campaigns = data;
        let filtered = data;
        if (filter === 'active') filtered = data.filter(c => c.status === 'active');
        if (filter === 'archived') filtered = data.filter(c => c.status === 'archived');

        filtered.forEach(c => this.renderCampaign(c));
      } catch (e) {
        this.list.innerHTML = `<p>⚠️ ${L.camp_load_error || 'Fehler beim Laden'}</p>`;
      }
    }

    renderCampaign(c) {
      let value = '';
      if (c.campaign_type === 'points') value = c.extra_points + ' pt';
      if (c.campaign_type === 'discount') value = c.discount_percent + '%';
      if (c.campaign_type === 'fixed') value = (c.min_purchase ?? c.fixed_amount ?? 0) + '€';

      const card = document.createElement('div');
      card.className = 'ppv-campaign-item glass';
      card.innerHTML = `
        <div class="ppv-camp-header">
          <h4>${c.title}</h4>
          <div class="ppv-camp-actions">
            <span class="ppv-camp-clone" data-id="${c.id}">📄</span>
            <span class="ppv-camp-archive" data-id="${c.id}">📦</span>
            <span class="ppv-camp-edit" data-id="${c.id}">✏️</span>
            <span class="ppv-camp-delete" data-id="${c.id}">🗑️</span>
          </div>
        </div>
        <p>${(c.start_date || '').substring(0, 10)} – ${(c.end_date || '').substring(0, 10)}</p>
        <p>⭐ ${L.camp_type || 'Typ'}: ${c.campaign_type} | ${L.camp_value || 'Wert'}: ${value} | ${statusBadge(c.state)}</p>
      `;
      this.list.appendChild(card);
    }

    edit(camp) {
      if (!camp) return;
      this.showModal();
      this.editingId = camp.id;

      const safe = id => document.getElementById(id);
      if (safe('camp-status')) safe('camp-status').value = camp.status || 'active';
      if (safe('camp-title')) safe('camp-title').value = camp.title;
      if (safe('camp-start')) safe('camp-start').value = (camp.start_date || '').substring(0, 10);
      if (safe('camp-end')) safe('camp-end').value = (camp.end_date || '').substring(0, 10);
      if (safe('camp-type')) safe('camp-type').value = camp.campaign_type;
      if (safe('camp-required-points')) safe('camp-required-points').value = camp.required_points || 0;
      if (safe('camp-points-given')) safe('camp-points-given').value = camp.points_given || 1;
      if (safe('camp-free-product-name')) safe('camp-free-product-name').value = camp.free_product || '';
      if (safe('camp-free-product-value')) safe('camp-free-product-value').value = camp.free_product_value || 0;

      const campValue = safe('camp-value');
      if (campValue) {
        if (camp.campaign_type === 'points') campValue.value = camp.extra_points || 0;
        else if (camp.campaign_type === 'discount') campValue.value = camp.discount_percent || 0;
        else if (camp.campaign_type === 'fixed') campValue.value = camp.min_purchase || camp.fixed_amount || 0;
        else campValue.value = 0;
      }

      this.updateVisibilityByType(camp.campaign_type);
      this.updateValueLabel(camp.campaign_type);
    }

    async save() {
      const safe = id => document.getElementById(id)?.value || '';
      const safeNum = id => Number(document.getElementById(id)?.value) || 0;

      const title = safe('camp-title');
      const start = safe('camp-start');
      const end = safe('camp-end');
      const realType = safe('camp-type');
      const value = safe('camp-value');
      const status = safe('camp-status');
      const requiredPoints = safeNum('camp-required-points');
      const pointsGiven = safeNum('camp-points-given');
      const freeProductName = safe('camp-free-product-name').trim();
      const freeProductValue = safeNum('camp-free-product-value');

      if (!title || !start || !end) {
        window.ppvToast('⚠️ ' + (L.camp_fill_title_date || 'Bitte Titel und Datum ausfüllen'), 'warning');
        return;
      }

      if (realType === 'free_product' && (!freeProductName || freeProductValue <= 0)) {
        window.ppvToast('⚠️ ' + (L.camp_fill_free_product_name_value || 'Bitte Produktname und Wert angeben'), 'warning');
        return;
      }

      const endpoint = this.editingId > 0
        ? '/wp-json/punktepass/v1/pos/campaign/update'
        : '/wp-json/punktepass/v1/pos/campaign';

      try {
        const res = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'PPV-POS-Token': getStoreKey() },
          body: JSON.stringify({
            id: this.editingId,
            store_key: getStoreKey(),
            title, start_date: start, end_date: end, campaign_type: realType,
            camp_value: value, required_points: requiredPoints,
            free_product: freeProductName, free_product_value: freeProductValue,
            points_given: pointsGiven, status
          })
        });

        const data = await res.json();

        if (data.success) {
          window.ppvToast(this.editingId > 0 ? (L.camp_updated || '✅ Aktualisiert!') : (L.camp_saved || '✅ Gespeichert!'), 'success');
          this.hideModal();
          this.resetForm();
          setTimeout(() => this.load(), 500);
        } else {
          window.ppvToast('❌ ' + (data.message || L.error_generic || 'Fehler'), 'error');
        }
      } catch (e) {
        window.ppvToast('⚠️ ' + (L.server_error || 'Serverfehler'), 'error');
      }
    }

    async delete(id) {
      if (!confirm(L.confirm_delete || 'Wirklich löschen?')) return;
      try {
        const res = await fetch('/wp-json/punktepass/v1/pos/campaign/delete', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'PPV-POS-Token': getStoreKey() },
          body: JSON.stringify({ id, store_key: getStoreKey() })
        });
        const data = await res.json();
        if (data.success) {
          window.ppvToast('🗑️ ' + (L.camp_deleted || 'Gelöscht'), 'success');
          setTimeout(() => this.load(), 500);
        }
      } catch (e) {
        window.ppvToast('⚠️ ' + (L.server_error || 'Serverfehler'), 'error');
      }
    }

    async archive(id) {
      try {
        const res = await fetch('/wp-json/punktepass/v1/pos/campaign/update', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'PPV-POS-Token': getStoreKey() },
          body: JSON.stringify({ id, store_key: getStoreKey(), status: 'archived' })
        });
        const data = await res.json();
        if (data.success) {
          window.ppvToast('📦 ' + (L.camp_archived || 'Archiviert'), 'success');
          setTimeout(() => this.load(), 500);
        }
      } catch (e) {
        window.ppvToast('⚠️ ' + (L.server_error || 'Serverfehler'), 'error');
      }
    }

    async clone(id) {
      const original = this.campaigns.find(c => c.id == id);
      if (!original) return;

      try {
        const res = await fetch('/wp-json/punktepass/v1/pos/campaign', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'PPV-POS-Token': getStoreKey() },
          body: JSON.stringify({
            store_key: getStoreKey(),
            title: original.title + ' (' + (L.copy || 'Kopie') + ')',
            start_date: original.start_date, end_date: original.end_date,
            campaign_type: original.campaign_type,
            camp_value: original.extra_points || original.discount_percent || original.min_purchase,
            required_points: original.required_points || 0,
            free_product: original.free_product || '',
            free_product_value: original.free_product_value || 0,
            points_given: original.points_given || 1
          })
        });
        const data = await res.json();
        if (data.success) {
          window.ppvToast('📄 ' + (L.camp_cloned || 'Dupliziert!'), 'success');
          setTimeout(() => this.load(), 500);
        }
      } catch (e) {
        window.ppvToast('⚠️ ' + (L.server_error || 'Serverfehler'), 'error');
      }
    }

    showModal() { if (this.modal) this.modal.classList.add('show'); }
    hideModal() { if (this.modal) this.modal.classList.remove('show'); }

    resetForm() {
      const safe = id => document.getElementById(id);
      ['camp-title', 'camp-start', 'camp-end', 'camp-free-product-name'].forEach(id => { if (safe(id)) safe(id).value = ''; });
      ['camp-value', 'camp-required-points', 'camp-free-product-value'].forEach(id => { if (safe(id)) safe(id).value = 0; });
      if (safe('camp-points-given')) safe('camp-points-given').value = 1;
      if (safe('camp-type')) safe('camp-type').value = 'points';
      if (safe('camp-status')) safe('camp-status').value = 'active';
      this.editingId = 0;
    }

    updateVisibilityByType(type) {
      const safe = id => document.getElementById(id);
      ['camp-required-points-wrapper', 'camp-points-given-wrapper', 'camp-free-product-name-wrapper', 'camp-free-product-value-wrapper'].forEach(id => {
        if (safe(id)) safe(id).style.display = 'none';
      });

      if (safe('camp-required-points-wrapper')) safe('camp-required-points-wrapper').style.display = 'block';

      if (type === 'discount' || type === 'fixed') {
        if (safe('camp-points-given-wrapper')) safe('camp-points-given-wrapper').style.display = 'block';
      } else if (type === 'free_product') {
        if (safe('camp-free-product-name-wrapper')) safe('camp-free-product-name-wrapper').style.display = 'block';
        if (safe('camp-free-product-value-wrapper')) safe('camp-free-product-value-wrapper').style.display = 'block';
        if (safe('camp-points-given-wrapper')) safe('camp-points-given-wrapper').style.display = 'block';
      }
    }

    updateValueLabel(type) {
      const label = document.getElementById('camp-value-label');
      const campValue = document.getElementById('camp-value');
      if (!label || !campValue) return;

      if (type === 'points') label.innerText = L.camp_extra_points || 'Extra Punkte';
      else if (type === 'discount') label.innerText = L.camp_discount || 'Rabatt (%)';
      else if (type === 'fixed') label.innerText = L.camp_fixed_bonus || 'Fix Bonus (€)';
      else if (type === 'free_product') {
        label.innerText = L.camp_free_product || 'Gratis Produkt';
        campValue.style.display = 'none';
        return;
      }
      campValue.style.display = 'block';
    }
  }

  // ============================================================
  // CAMERA SCANNER (Simplified)
  // ============================================================
  class CameraScanner {
    constructor(scanProcessor) {
      this.scanProcessor = scanProcessor;
      this.scanner = null;
      this.scanning = false;
      this.state = 'stopped';
      this.lastRead = '';
      this.countdown = 0;
      this.countdownInterval = null;
      this.miniContainer = null;
      this.readerDiv = null;
      this.statusDiv = null;
      this.toggleBtn = null;
    }

    init() {
      this.createMiniScanner();
    }

    createMiniScanner() {
      const existing = document.getElementById('ppv-mini-scanner');
      if (existing) existing.remove();

      this.miniContainer = document.createElement('div');
      this.miniContainer.id = 'ppv-mini-scanner';
      this.miniContainer.className = 'ppv-mini-scanner-active';
      this.miniContainer.innerHTML = `
        <div id="ppv-mini-drag-handle" class="ppv-mini-drag-handle"><span class="ppv-drag-icon">⋮⋮</span></div>
        <div id="ppv-mini-reader" style="display:none;"></div>
        <div id="ppv-mini-status" style="display:none;"><span class="ppv-mini-icon">📷</span><span class="ppv-mini-text">${L.scanner_active || 'Scanner aktiv'}</span></div>
        <button id="ppv-mini-toggle" class="ppv-mini-toggle"><span class="ppv-toggle-icon">📷</span><span class="ppv-toggle-text">Start</span></button>
      `;
      document.body.appendChild(this.miniContainer);

      this.readerDiv = document.getElementById('ppv-mini-reader');
      this.statusDiv = document.getElementById('ppv-mini-status');
      this.toggleBtn = document.getElementById('ppv-mini-toggle');

      this.loadPosition();
      this.makeDraggable();
      this.setupToggle();
    }

    loadPosition() {
      try {
        const saved = localStorage.getItem('ppv_scanner_position');
        if (saved) {
          const pos = JSON.parse(saved);
          this.miniContainer.style.bottom = 'auto';
          this.miniContainer.style.right = 'auto';
          this.miniContainer.style.left = pos.x + 'px';
          this.miniContainer.style.top = pos.y + 'px';
        }
      } catch (e) {}
    }

    savePosition(x, y) {
      try { localStorage.setItem('ppv_scanner_position', JSON.stringify({ x, y })); } catch (e) {}
    }

    makeDraggable() {
      const handle = document.getElementById('ppv-mini-drag-handle');
      if (!handle) return;

      let isDragging = false, currentX = 0, currentY = 0, offsetX = 0, offsetY = 0;

      const dragStart = e => {
        const rect = this.miniContainer.getBoundingClientRect();
        currentX = rect.left; currentY = rect.top;
        offsetX = (e.touches ? e.touches[0].clientX : e.clientX) - currentX;
        offsetY = (e.touches ? e.touches[0].clientY : e.clientY) - currentY;
        if (e.target === handle || e.target.classList.contains('ppv-drag-icon')) {
          isDragging = true;
          this.miniContainer.style.transition = 'none';
        }
      };

      const drag = e => {
        if (!isDragging) return;
        e.preventDefault();
        currentX = (e.touches ? e.touches[0].clientX : e.clientX) - offsetX;
        currentY = (e.touches ? e.touches[0].clientY : e.clientY) - offsetY;
        const rect = this.miniContainer.getBoundingClientRect();
        currentX = Math.max(0, Math.min(currentX, window.innerWidth - rect.width));
        currentY = Math.max(0, Math.min(currentY, window.innerHeight - rect.height));
        this.miniContainer.style.bottom = 'auto';
        this.miniContainer.style.right = 'auto';
        this.miniContainer.style.left = currentX + 'px';
        this.miniContainer.style.top = currentY + 'px';
      };

      const dragEnd = () => {
        if (isDragging) {
          isDragging = false;
          this.miniContainer.style.transition = '';
          this.savePosition(currentX, currentY);
        }
      };

      handle.addEventListener('mousedown', dragStart);
      document.addEventListener('mousemove', drag);
      document.addEventListener('mouseup', dragEnd);
      handle.addEventListener('touchstart', dragStart, { passive: false });
      document.addEventListener('touchmove', drag, { passive: false });
      document.addEventListener('touchend', dragEnd);
    }

    setupToggle() {
      if (!this.toggleBtn) return;
      this.toggleBtn.addEventListener('click', async () => {
        if (this.scanning) await this.stopScanner();
        else await this.startScannerManual();
      });
    }

    async stopScanner() {
      try {
        if (this.scanner) { await this.scanner.stop(); this.scanner = null; }
        if (this.iosStream) { this.iosStream.getTracks().forEach(t => t.stop()); this.iosStream = null; }
      } catch (e) {}

      this.scanning = false;
      this.state = 'stopped';
      this.readerDiv.style.display = 'none';
      this.statusDiv.style.display = 'none';
      this.toggleBtn.querySelector('.ppv-toggle-icon').textContent = '📷';
      this.toggleBtn.querySelector('.ppv-toggle-text').textContent = 'Start';
      this.toggleBtn.style.background = 'linear-gradient(135deg, #00e676, #00c853)';

      if (this.countdownInterval) { clearInterval(this.countdownInterval); this.countdownInterval = null; }
    }

    async startScannerManual() {
      this.readerDiv.style.display = 'block';
      this.statusDiv.style.display = 'block';
      this.toggleBtn.querySelector('.ppv-toggle-icon').textContent = '🛑';
      this.toggleBtn.querySelector('.ppv-toggle-text').textContent = 'Stop';
      this.toggleBtn.style.background = 'linear-gradient(135deg, #ff5252, #f44336)';
      await this.loadLibrary();
    }

    async loadLibrary() {
      const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

      if (isIOS) {
        if (window.jsQR) { await this.startIOSScanner(); return; }
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
        script.onload = () => this.startIOSScanner();
        script.onerror = () => this.updateStatus('error', '❌ Scanner nicht verfügbar');
        document.head.appendChild(script);
      } else {
        if (window.Html5Qrcode) { await this.startScanner(); return; }
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
        script.onload = () => this.startScanner();
        script.onerror = () => this.updateStatus('error', '❌ Scanner nicht verfügbar');
        document.head.appendChild(script);
      }
    }

    async startScanner() {
      if (!this.readerDiv || !window.Html5Qrcode) return;
      try {
        this.scanner = new Html5Qrcode('ppv-mini-reader');
        await this.scanner.start(
          { facingMode: 'environment' },
          { fps: 20, qrbox: 220, experimentalFeatures: { useBarCodeDetectorIfSupported: true } },
          qrCode => this.onScanSuccess(qrCode)
        );
        this.scanning = true;
        this.state = 'scanning';
        this.updateStatus('scanning', L.scanner_active || 'Scanning...');
      } catch (e) {
        this.updateStatus('error', '❌ Kamera nicht verfügbar');
      }
    }

    async startIOSScanner() {
      if (!this.readerDiv || !window.jsQR) return;
      if (this.iosStream) { this.iosStream.getTracks().forEach(t => t.stop()); this.iosStream = null; }

      try {
        const video = document.createElement('video');
        video.style.cssText = 'width:100%;height:100%;object-fit:cover;';
        video.setAttribute('playsinline', 'true');
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        this.readerDiv.innerHTML = '';
        this.readerDiv.appendChild(video);

        const stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: { exact: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
        });

        video.srcObject = stream;
        await video.play();

        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;

        this.iosStream = stream;
        this.iosVideo = video;
        this.iosCanvas = canvas;
        this.iosCanvasCtx = ctx;
        this.scanning = true;
        this.state = 'scanning';
        this.updateStatus('scanning', L.scanner_active || 'Scanning...');
        this.iosScanLoop();
      } catch (e) {
        this.updateStatus('error', '❌ Kamera nicht verfügbar');
      }
    }

    iosScanLoop() {
      if (!this.scanning || !this.iosVideo || !this.iosCanvas) return;

      const video = this.iosVideo, canvas = this.iosCanvas, ctx = this.iosCanvasCtx;

      if (video.readyState === video.HAVE_ENOUGH_DATA) {
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });
        if (code && code.data) this.onScanSuccess(code.data);
      }

      if (this.scanning) setTimeout(() => this.iosScanLoop(), 100);
    }

    onScanSuccess(qrCode) {
      if (this.state === 'paused' || this.state !== 'scanning') return;

      if (qrCode === this.lastRead) return;
      this.lastRead = qrCode;
      this.state = 'processing';
      this.updateStatus('processing', '⏳ ' + (L.scanner_points_adding || 'Wird verarbeitet...'));

      try { if (navigator.vibrate) navigator.vibrate(30); } catch (e) {}

      this.inlineProcessScan(qrCode);
    }

    inlineProcessScan(qrCode) {
      fetch('/wp-json/punktepass/v1/pos/scan', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'PPV-POS-Token': getStoreKey() },
        body: JSON.stringify({ qr: qrCode, store_key: getStoreKey(), points: 1 })
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            this.updateStatus('success', '✅ ' + (data.message || L.scanner_success_msg || 'Erfolgreich!'));
            window.ppvToast(data.message || L.scanner_point_added || '✅ Punkt hinzugefügt!', 'success');
            this.startPauseCountdown();
          } else {
            this.updateStatus('warning', '⚠️ ' + (data.message || L.error_generic || 'Fehler'));
            window.ppvToast(data.message || '⚠️ Fehler', 'warning');
            setTimeout(() => this.restartAfterError(), 3000);
          }
        })
        .catch(() => {
          this.updateStatus('error', '❌ ' + (L.pos_network_error || 'Netzwerkfehler'));
          window.ppvToast('❌ ' + (L.pos_network_error || 'Netzwerkfehler'), 'error');
          setTimeout(() => this.restartAfterError(), 3000);
        });
    }

    startPauseCountdown() {
      if (this.countdownInterval) clearInterval(this.countdownInterval);
      this.state = 'paused';
      this.countdown = 5;
      this.lastRead = '';
      this.updateStatus('paused', `⏸️ Pause: ${this.countdown}s`);

      this.countdownInterval = setInterval(() => {
        this.countdown--;
        if (this.countdown <= 0) {
          clearInterval(this.countdownInterval);
          this.countdownInterval = null;
          this.autoRestartScanner();
        } else {
          this.updateStatus('paused', `⏸️ Pause: ${this.countdown}s`);
        }
      }, 1000);
    }

    restartAfterError() {
      this.lastRead = '';
      this.state = 'scanning';
      this.updateStatus('scanning', L.scanner_active || 'Scanning...');
    }

    async autoRestartScanner() {
      if (this.state === 'stopped' || !this.scanning) return;
      this.state = 'scanning';
      this.updateStatus('scanning', L.scanner_active || 'Scanning...');
    }

    updateStatus(state, text) {
      if (!this.statusDiv) return;
      const iconMap = { scanning: '📷', processing: '⏳', success: '✅', warning: '⚠️', error: '❌', paused: '⏸️' };
      const iconEl = this.statusDiv.querySelector('.ppv-mini-icon');
      const textEl = this.statusDiv.querySelector('.ppv-mini-text');
      if (iconEl) iconEl.textContent = iconMap[state] || '📷';
      if (textEl) textEl.textContent = text.replace(/^[📷⏳✅⚠️❌⏸️]\s*/, '');
    }

    cleanup() {
      this.stopScanner();
      const mini = document.getElementById('ppv-mini-scanner');
      if (mini) mini.remove();
    }
  }

  // ============================================================
  // SETTINGS MANAGER
  // ============================================================
  class SettingsManager {
    static initLanguage() {
      const langSel = document.getElementById('ppv-lang-select');
      if (!langSel) return;

      const cur = (document.cookie.match(/ppv_lang=([^;]+)/) || [])[1] || 'de';
      langSel.value = cur;

      langSel.addEventListener('change', async e => {
        const newLang = e.target.value;
        document.cookie = `ppv_lang=${newLang};path=/;max-age=${60 * 60 * 24 * 365}`;
        localStorage.setItem('ppv_lang', newLang);

        try {
          const res = await fetch('/wp-json/punktepass/v1/strings', { headers: { 'X-Lang': newLang } });
          window.ppv_lang = await res.json();
          window.ppvToast(`✅ ${L.lang_changed || 'Sprache'}: ${newLang.toUpperCase()}`, 'success');
        } catch (e) {
          window.ppvToast('❌ ' + (L.lang_change_failed || 'Sprachänderung fehlgeschlagen'), 'error');
          langSel.value = cur;
        }
      });
    }

    static initTheme() {
      const themeBtn = document.getElementById('ppv-theme-toggle');
      if (!themeBtn) return;

      const apply = v => {
        document.body.classList.remove('ppv-light', 'ppv-dark');
        document.body.classList.add(`ppv-${v}`);
      };

      let cur = localStorage.getItem('ppv_theme') || 'dark';
      apply(cur);

      themeBtn.addEventListener('click', () => {
        cur = cur === 'dark' ? 'light' : 'dark';
        localStorage.setItem('ppv_theme', cur);
        apply(cur);
      });
    }
  }

  // ============================================================
  // EVENT DELEGATION
  // ============================================================
  function setupEventDelegation() {
    document.body.removeEventListener('click', handleBodyClick);
    document.body.addEventListener('click', handleBodyClick);
  }

  function handleBodyClick(e) {
    const target = e.target;

    // Campaign actions
    if (target.classList.contains('ppv-camp-edit')) {
      const camp = STATE.campaignManager?.campaigns.find(c => c.id == target.dataset.id);
      if (camp) STATE.campaignManager.edit(camp);
    }
    if (target.classList.contains('ppv-camp-delete')) {
      STATE.campaignManager?.delete(target.dataset.id);
    }
    if (target.classList.contains('ppv-camp-archive')) {
      STATE.campaignManager?.archive(target.dataset.id);
    }
    if (target.classList.contains('ppv-camp-clone')) {
      STATE.campaignManager?.clone(target.dataset.id);
    }

    // New campaign button
    if (target.id === 'ppv-new-campaign' || target.closest('#ppv-new-campaign')) {
      STATE.campaignManager?.resetForm();
      STATE.campaignManager?.showModal();
    }

    // Save campaign
    if (target.id === 'ppv-camp-save' || target.closest('#ppv-camp-save')) {
      STATE.campaignManager?.save();
    }

    // Cancel campaign
    if (target.id === 'ppv-camp-cancel' || target.closest('#ppv-camp-cancel')) {
      STATE.campaignManager?.hideModal();
    }

    // Campaign type change
    if (target.id === 'camp-type') {
      STATE.campaignManager?.updateVisibilityByType(target.value);
      STATE.campaignManager?.updateValueLabel(target.value);
    }

    // Campaign filter
    if (target.id === 'ppv-campaign-filter') {
      STATE.campaignManager?.load();
    }
  }

  // ============================================================
  // CLEANUP
  // ============================================================
  function cleanup() {
    STATE.cameraScanner?.cleanup();
    STATE.cameraScanner = null;
    STATE.campaignManager = null;
    STATE.scanProcessor = null;
    STATE.uiManager = null;
    STATE.initialized = false;
  }

  // ============================================================
  // INITIALIZATION
  // ============================================================
  function init() {
    // Prevent rapid re-initialization (within 2 seconds)
    const now = Date.now();
    if (now - STATE.lastInitTime < 2000) {
      ppvLog('[QR] Init throttled (too soon)');
      return;
    }
    STATE.lastInitTime = now;

    const campaignList = document.getElementById('ppv-campaign-list');
    const posInput = document.getElementById('ppv-pos-input');

    // Only init if we have QR elements
    if (!campaignList && !posInput) {
      cleanup();
      return;
    }

    ppvLog('[QR] Initializing...');
    cleanup();

    STATE.uiManager = new UIManager();
    STATE.uiManager.init();

    STATE.scanProcessor = new ScanProcessor(STATE.uiManager);

    STATE.campaignManager = new CampaignManager(STATE.uiManager);
    STATE.campaignManager.init();

    STATE.cameraScanner = new CameraScanner(STATE.scanProcessor);
    STATE.cameraScanner.init();

    // Setup event delegation
    setupEventDelegation();

    // Input handling
    if (posInput) {
      posInput.addEventListener('keypress', e => {
        if (e.key === 'Enter') {
          e.preventDefault();
          const qr = posInput.value.trim();
          if (qr.length >= 4) {
            STATE.scanProcessor.process(qr);
            posInput.value = '';
          }
        }
      });
      posInput.focus();
    }

    const sendBtn = document.getElementById('ppv-pos-send');
    if (sendBtn && posInput) {
      sendBtn.addEventListener('click', () => {
        const qr = posInput.value.trim();
        if (qr) {
          STATE.scanProcessor.process(qr);
          posInput.value = '';
        }
      });
    }

    // Settings
    SettingsManager.initLanguage();
    SettingsManager.initTheme();

    // Load data
    STATE.scanProcessor.loadLogs();
    STATE.campaignManager.load();
    OfflineSyncManager.sync();

    // Visibility change handler
    let lastVis = 0;
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden && Date.now() - lastVis > 5000) {
        lastVis = Date.now();
        STATE.campaignManager?.load();
      }
    });

    STATE.initialized = true;
    ppvLog('[QR] Initialization complete');
  }

  // ============================================================
  // EVENT LISTENERS
  // ============================================================

  // Initial load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Turbo.js support
  document.addEventListener('turbo:load', init);
  document.addEventListener('turbo:before-visit', cleanup);

  // Custom SPA event support
  window.addEventListener('ppv:spa-navigate', init);

  ppvLog('[QR] Script loaded v6.0');

})();
