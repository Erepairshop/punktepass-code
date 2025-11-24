/**
 * PunktePass – Kassenscanner & Kampagnen v6.4 OPTIMIZED
 * Turbo.js compatible, clean architecture
 * FIXED: Multiple init() calls causing API spam
 * FIXED: Ably connection cleanup on page navigation
 * FIXED: Better camera error messages (NotAllowed, NotFound, NotReadable, etc.)
 * FIXED: Fallback to lower resolution on OverconstrainedError
 * OPTIMIZED: Camera focus for XCover 4S and low-end devices
 * OPTIMIZED: Higher resolution, continuous autofocus, torch support
 * Author: Erik Borota / PunktePass
 */

(function() {
  'use strict';

  // ✅ DEBUG mode - set to false for production
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
    lastInitTime: 0,  // Prevent rapid re-init
    ablyInstance: null,  // Ably connection for cleanup
    pollInterval: null   // Polling interval for cleanup
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
    box.textContent = msg; // ✅ FIX: Use textContent to prevent XSS
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
  // HTML ESCAPE (XSS Prevention)
  // ============================================================
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
      this.displayedScanIds = new Set(); // ✅ Track displayed scans to prevent duplicates
    }

    init() {
      this.resultBox = document.getElementById('ppv-pos-result');
      this.logList = document.getElementById('ppv-pos-log');
      this.campaignList = document.getElementById('ppv-campaign-list');
      this.displayedScanIds.clear(); // Reset on init
    }

    showMessage(text, type = 'info') {
      window.ppvToast(text, type);
    }

    clearLogTable() {
      if (!this.logList) return;
      this.logList.innerHTML = '';
      this.displayedScanIds.clear(); // ✅ Clear tracked IDs when table is cleared
    }

    addScanItem(log) {
      if (!this.logList) return;

      // ✅ FIX: Prevent duplicates using scan_id
      const scanId = log.scan_id || `${log.user_id}-${log.date_short}-${log.time_short}`;
      if (log._realtime && this.displayedScanIds.has(scanId)) {
        ppvLog('[UI] Skipping duplicate scan:', scanId);
        return;
      }
      this.displayedScanIds.add(scanId);

      const item = document.createElement('div');
      item.className = `ppv-scan-item ${log.success ? 'success' : 'error'}`;
      item.dataset.scanId = scanId; // Store for reference

      // Build display: Name > Email > #ID
      const displayName = log.customer_name || log.email || `Kunde #${log.user_id}`;

      // Check for VIP bonus in message (e.g., "(VIP: +2)")
      const vipMatch = (log.message || '').match(/\(VIP:?\s*\+(\d+)\)/i);
      const vipBonus = vipMatch ? vipMatch[1] : null;
      const isVip = !!vipBonus;

      // ✅ FIX: Subtitle logic - ALWAYS show date/time, with error message as second line
      const dateTime = `${log.date_short || ''} ${log.time_short || ''}`.trim();
      let subtitle = dateTime;
      let subtitle2 = ''; // Second line for additional info

      if (!log.success && log.message) {
        // Error: show cleaned error message + date/time
        const errorMsg = log.message.replace(/^[⚠️❌✗\s]+/, '').trim();
        subtitle = errorMsg;
        subtitle2 = dateTime;
      } else if (log.customer_name && log.email) {
        // Success with name: show email + date
        subtitle = log.email;
        subtitle2 = dateTime;
      }

      // Avatar: Google profile pic or default icon
      const avatarHtml = log.avatar
        ? `<img src="${log.avatar}" class="ppv-scan-avatar" alt="">`
        : `<div class="ppv-scan-avatar-placeholder">${log.success ? '✓' : '✗'}</div>`;

      // Points display: show VIP badge if applicable, or error indicator
      let pointsHtml;
      if (!log.success) {
        pointsHtml = `<div class="ppv-scan-points error-badge">✗</div>`;
      } else if (isVip) {
        pointsHtml = `<div class="ppv-scan-points vip">+${log.points}<span class="ppv-vip-badge">VIP +${vipBonus}</span></div>`;
      } else {
        pointsHtml = `<div class="ppv-scan-points">+${log.points || '-'}</div>`;
      }

      // ✅ FIX: Show subtitle2 (date/time) for errors and successful scans with name
      const subtitle2Html = subtitle2 ? `<div class="ppv-scan-detail ppv-scan-time">${subtitle2}</div>` : '';

      item.innerHTML = `
        ${avatarHtml}
        <div class="ppv-scan-info">
          <div class="ppv-scan-name">${displayName}</div>
          <div class="ppv-scan-detail">${subtitle}</div>
          ${subtitle2Html}
        </div>
        ${pointsHtml}
      `;
      // ✅ FIX: Use prepend for real-time scans, append for initial load
      if (log._realtime) {
        this.logList.prepend(item);
      } else {
        this.logList.appendChild(item);
      }
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
          // ✅ FIX: Removed non-existent addLogRow call - inlineProcessScan handles UI updates
        } else {
          this.ui.showMessage('⚠️ ' + (data.message || ''), 'warning');
          if (!/bereits|gescannt|duplikat/i.test(data.message || '')) {
            OfflineSyncManager.save(qrCode);
          }
        }
        // ✅ FIX: Removed redundant loadLogs - real-time updates handle this
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

      // ✅ FIX: Escape HTML to prevent XSS
      const safeTitle = escapeHtml(c.title || '');
      const safeType = escapeHtml(c.campaign_type || '');

      const card = document.createElement('div');
      card.className = 'ppv-campaign-item glass';
      card.innerHTML = `
        <div class="ppv-camp-header">
          <span class="ppv-camp-title">${safeTitle}</span>
          <div class="ppv-camp-actions">
            <span class="ppv-camp-clone" data-id="${c.id}">📄</span>
            <span class="ppv-camp-archive" data-id="${c.id}">📦</span>
            <span class="ppv-camp-edit" data-id="${c.id}">✏️</span>
            <span class="ppv-camp-delete" data-id="${c.id}">🗑️</span>
          </div>
        </div>
        <p class="ppv-camp-dates">${(c.start_date || '').substring(0, 10)} – ${(c.end_date || '').substring(0, 10)}</p>
        <p class="ppv-camp-meta">⭐ ${safeType} | ${value} | ${statusBadge(c.state)}</p>
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
      this.torchBtn = null;
      this.torchOn = false;
      this.videoTrack = null;
      this.refocusInterval = null;
    }

    init() {
      this.createMiniScanner();
      // Auto-start if was running before navigation
      this.checkAutoStart();
    }

    checkAutoStart() {
      try {
        const wasRunning = localStorage.getItem('ppv_scanner_running') === 'true';
        if (wasRunning) {
          ppvLog('[Scanner] Auto-starting (was running before navigation)');
          setTimeout(() => this.startScannerManual(), 500);
        }
      } catch (e) {}
    }

    saveScannerState(running) {
      try {
        localStorage.setItem('ppv_scanner_running', running ? 'true' : 'false');
      } catch (e) {}
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
        <div class="ppv-mini-controls">
          <button id="ppv-mini-torch" class="ppv-mini-torch" style="display:none;" title="Blitz"><span class="ppv-torch-icon">🔦</span></button>
          <button id="ppv-mini-refocus" class="ppv-mini-refocus" style="display:none;" title="Fokus"><span class="ppv-refocus-icon">🎯</span></button>
          <button id="ppv-mini-toggle" class="ppv-mini-toggle"><span class="ppv-toggle-icon">📷</span><span class="ppv-toggle-text">Start</span></button>
        </div>
      `;
      document.body.appendChild(this.miniContainer);

      this.readerDiv = document.getElementById('ppv-mini-reader');
      this.statusDiv = document.getElementById('ppv-mini-status');
      this.toggleBtn = document.getElementById('ppv-mini-toggle');
      this.torchBtn = document.getElementById('ppv-mini-torch');
      this.refocusBtn = document.getElementById('ppv-mini-refocus');

      this.loadPosition();
      this.makeDraggable();
      this.setupToggle();
      this.setupTorch();
      this.setupRefocus();
    }

    // ============================================================
    // 🔦 TORCH CONTROL
    // ============================================================
    setupTorch() {
      if (!this.torchBtn) return;
      this.torchBtn.addEventListener('click', async () => {
        await this.toggleTorch();
      });
    }

    async toggleTorch() {
      if (!this.videoTrack) return;
      try {
        const capabilities = this.videoTrack.getCapabilities();
        if (!capabilities.torch) {
          ppvLog('[Camera] Torch not supported');
          return;
        }
        this.torchOn = !this.torchOn;
        await this.videoTrack.applyConstraints({ advanced: [{ torch: this.torchOn }] });
        this.torchBtn.querySelector('.ppv-torch-icon').textContent = this.torchOn ? '💡' : '🔦';
        ppvLog('[Camera] Torch:', this.torchOn ? 'ON' : 'OFF');
      } catch (e) {
        ppvWarn('[Camera] Torch error:', e);
      }
    }

    // ============================================================
    // 🎯 MANUAL REFOCUS
    // ============================================================
    setupRefocus() {
      if (!this.refocusBtn) return;
      this.refocusBtn.addEventListener('click', async () => {
        await this.triggerRefocus();
      });
    }

    async triggerRefocus() {
      if (!this.videoTrack) return;
      try {
        const capabilities = this.videoTrack.getCapabilities();
        if (capabilities.focusMode && capabilities.focusMode.includes('manual')) {
          // Briefly switch to manual then back to continuous to force refocus
          await this.videoTrack.applyConstraints({ advanced: [{ focusMode: 'manual' }] });
          await new Promise(r => setTimeout(r, 100));
          await this.videoTrack.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
          ppvLog('[Camera] Refocus triggered');
          window.ppvToast('🎯 Fokus aktualisiert', 'info');
        } else if (capabilities.focusMode && capabilities.focusMode.includes('continuous')) {
          // Some devices: toggle continuous off/on
          await this.videoTrack.applyConstraints({ advanced: [{ focusMode: 'single-shot' }] });
          await new Promise(r => setTimeout(r, 200));
          await this.videoTrack.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
          ppvLog('[Camera] Refocus triggered (single-shot method)');
          window.ppvToast('🎯 Fokus aktualisiert', 'info');
        }
      } catch (e) {
        ppvWarn('[Camera] Refocus error:', e);
      }
    }

    // Start periodic refocus (every 8 seconds) for problematic devices
    startPeriodicRefocus() {
      if (this.refocusInterval) return;
      this.refocusInterval = setInterval(() => {
        if (this.scanning && this.state === 'scanning') {
          this.triggerRefocus();
        }
      }, 8000);
      ppvLog('[Camera] Periodic refocus started (8s interval)');
    }

    stopPeriodicRefocus() {
      if (this.refocusInterval) {
        clearInterval(this.refocusInterval);
        this.refocusInterval = null;
        ppvLog('[Camera] Periodic refocus stopped');
      }
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
      this.videoTrack = null;
      this.torchOn = false;
      this.readerDiv.style.display = 'none';
      this.statusDiv.style.display = 'none';
      this.toggleBtn.querySelector('.ppv-toggle-icon').textContent = '📷';
      this.toggleBtn.querySelector('.ppv-toggle-text').textContent = 'Start';
      this.toggleBtn.style.background = 'linear-gradient(135deg, #00e676, #00c853)';

      // Hide torch and refocus buttons
      if (this.torchBtn) this.torchBtn.style.display = 'none';
      if (this.refocusBtn) this.refocusBtn.style.display = 'none';

      if (this.countdownInterval) { clearInterval(this.countdownInterval); this.countdownInterval = null; }

      // Stop periodic refocus
      this.stopPeriodicRefocus();

      // Save state for persistence across navigation
      this.saveScannerState(false);
    }

    async startScannerManual() {
      this.readerDiv.style.display = 'block';
      this.statusDiv.style.display = 'block';
      this.toggleBtn.querySelector('.ppv-toggle-icon').textContent = '🛑';
      this.toggleBtn.querySelector('.ppv-toggle-text').textContent = 'Stop';
      this.toggleBtn.style.background = 'linear-gradient(135deg, #ff5252, #f44336)';

      // Save state for persistence across navigation
      this.saveScannerState(true);

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

    async startScanner(fallbackMode = false) {
      if (!this.readerDiv || !window.Html5Qrcode) return;
      try {
        this.scanner = new Html5Qrcode('ppv-mini-reader');

        // Html5Qrcode only accepts { facingMode } - advanced constraints applied later via videoTrack
        const cameraConstraints = { facingMode: 'environment' };

        // Optimized scanner config
        const scannerConfig = {
          fps: fallbackMode ? 15 : 30,  // Lower FPS in fallback mode
          qrbox: { width: 250, height: 250 },  // Scan area
          aspectRatio: 1.0,
          videoConstraints: {
            // These are passed to getUserMedia internally
            width: { min: 640, ideal: 1280, max: 1920 },
            height: { min: 480, ideal: 720, max: 1080 }
          },
          experimentalFeatures: {
            useBarCodeDetectorIfSupported: true
          },
          // Only scan QR codes for faster processing
          formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE]
        };

        ppvLog('[Camera] Starting with', fallbackMode ? 'fallback' : 'optimized', 'constraints');

        await this.scanner.start(
          cameraConstraints,
          scannerConfig,
          qrCode => this.onScanSuccess(qrCode)
        );

        // Get video track for torch and refocus control
        try {
          const videoElement = document.querySelector('#ppv-mini-reader video');
          if (videoElement && videoElement.srcObject) {
            this.videoTrack = videoElement.srcObject.getVideoTracks()[0];
            if (this.videoTrack) {
              // Apply advanced focus settings
              const capabilities = this.videoTrack.getCapabilities();
              ppvLog('[Camera] Capabilities:', capabilities);

              const advancedConstraints = [];

              // Enable continuous autofocus
              if (capabilities.focusMode && capabilities.focusMode.includes('continuous')) {
                advancedConstraints.push({ focusMode: 'continuous' });
              }

              // Set focus distance for close-up QR (if supported)
              if (capabilities.focusDistance) {
                // Set to macro range (close focus)
                const minFocus = capabilities.focusDistance.min || 0;
                const maxFocus = capabilities.focusDistance.max || 1;
                const macroFocus = minFocus + (maxFocus - minFocus) * 0.2;  // 20% into range (close)
                advancedConstraints.push({ focusDistance: macroFocus });
              }

              // Enable continuous exposure
              if (capabilities.exposureMode && capabilities.exposureMode.includes('continuous')) {
                advancedConstraints.push({ exposureMode: 'continuous' });
              }

              if (advancedConstraints.length > 0) {
                await this.videoTrack.applyConstraints({ advanced: advancedConstraints });
                ppvLog('[Camera] Advanced constraints applied:', advancedConstraints);
              }

              // Show torch button if supported
              if (capabilities.torch && this.torchBtn) {
                this.torchBtn.style.display = 'inline-flex';
              }

              // Show refocus button
              if (this.refocusBtn) {
                this.refocusBtn.style.display = 'inline-flex';
              }

              // Start periodic refocus for problematic devices
              this.startPeriodicRefocus();
            }
          }
        } catch (trackErr) {
          ppvWarn('[Camera] Track setup error:', trackErr);
        }

        this.scanning = true;
        this.state = 'scanning';
        this.updateStatus('scanning', L.scanner_active || 'Scanning...');
      } catch (e) {
        ppvWarn('[Camera] Start error:', e);

        // Html5Qrcode may throw string errors or custom objects - normalize error info
        const errName = e?.name || (typeof e === 'string' ? 'StringError' : 'UnknownError');
        const errMsg = e?.message || (typeof e === 'string' ? e : JSON.stringify(e));
        console.error('[Camera] Detailed error:', errName, errMsg, e);

        // Check if error message contains constraint-related text (Html5Qrcode specific)
        const isConstraintError = errName === 'OverconstrainedError' ||
                                  errName === 'ConstraintNotSatisfiedError' ||
                                  /overconstrained|constraint/i.test(errMsg);

        // If constraint error and not already in fallback mode, retry with simpler constraints
        if (isConstraintError && !fallbackMode) {
          ppvLog('[Camera] Retrying with fallback constraints...');
          window.ppvToast('📷 Kamera wird neu gestartet...', 'info');
          this.scanner = null;
          await this.startScanner(true);  // Retry with fallback mode
          return;
        }

        // Show specific error message based on error type/message
        let errorMsg = '❌ Kamera nicht verfügbar';

        // Check by error name OR by error message content (Html5Qrcode specific)
        if (errName === 'NotAllowedError' || errName === 'PermissionDeniedError' ||
            /permission|denied|not allowed/i.test(errMsg)) {
          errorMsg = '❌ Kamera-Zugriff verweigert';
          window.ppvToast('Bitte erlaube den Kamerazugriff in den Browser-Einstellungen', 'error');
        } else if (errName === 'NotFoundError' || errName === 'DevicesNotFoundError' ||
                   /not found|no camera|no video/i.test(errMsg)) {
          errorMsg = '❌ Keine Kamera gefunden';
          window.ppvToast('Es wurde keine Kamera gefunden', 'error');
        } else if (errName === 'NotReadableError' || errName === 'TrackStartError' ||
                   /not readable|in use|busy/i.test(errMsg)) {
          errorMsg = '❌ Kamera wird verwendet';
          window.ppvToast('Die Kamera wird von einer anderen App verwendet', 'error');
        } else if (isConstraintError) {
          errorMsg = '❌ Kamera nicht kompatibel';
          window.ppvToast('Die Kamera unterstützt die Anforderungen nicht', 'error');
        } else if (errName === 'SecurityError' || /security|https|insecure/i.test(errMsg)) {
          errorMsg = '❌ HTTPS erforderlich';
          window.ppvToast('Kamera funktioniert nur über HTTPS', 'error');
        } else {
          // Unknown error - show the actual message to help debug
          window.ppvToast('Kamera-Fehler: ' + (errMsg.substring(0, 50) || 'Unbekannt'), 'error');
        }
        this.updateStatus('error', errorMsg);
      }
    }

    async startIOSScanner() {
      if (!this.readerDiv || !window.jsQR) return;
      if (this.iosStream) { this.iosStream.getTracks().forEach(t => t.stop()); this.iosStream = null; }

      try {
        const video = document.createElement('video');
        video.style.cssText = 'width:100%;height:100%;object-fit:cover;';
        video.setAttribute('playsinline', 'true');
        video.setAttribute('autoplay', 'true');
        video.setAttribute('muted', 'true');
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d', { willReadFrequently: true });

        this.readerDiv.innerHTML = '';
        this.readerDiv.appendChild(video);

        // Optimized iOS camera constraints
        const stream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: { exact: 'environment' },
            width: { min: 640, ideal: 1920, max: 2560 },
            height: { min: 480, ideal: 1080, max: 1440 },
            frameRate: { ideal: 30, max: 60 }
          }
        });

        video.srcObject = stream;
        await video.play();

        canvas.width = video.videoWidth || 1280;
        canvas.height = video.videoHeight || 720;

        this.iosStream = stream;
        this.iosVideo = video;
        this.iosCanvas = canvas;
        this.iosCanvasCtx = ctx;

        // Get video track for iOS torch/refocus
        this.videoTrack = stream.getVideoTracks()[0];
        if (this.videoTrack) {
          try {
            const capabilities = this.videoTrack.getCapabilities();
            ppvLog('[iOS Camera] Capabilities:', capabilities);

            // Apply focus constraints if available
            const advancedConstraints = [];
            if (capabilities.focusMode && capabilities.focusMode.includes('continuous')) {
              advancedConstraints.push({ focusMode: 'continuous' });
            }
            if (advancedConstraints.length > 0) {
              await this.videoTrack.applyConstraints({ advanced: advancedConstraints });
            }

            // Show torch button if supported
            if (capabilities.torch && this.torchBtn) {
              this.torchBtn.style.display = 'inline-flex';
            }

            // Show refocus button
            if (this.refocusBtn) {
              this.refocusBtn.style.display = 'inline-flex';
            }
          } catch (constraintErr) {
            ppvWarn('[iOS Camera] Constraint error:', constraintErr);
          }
        }

        this.scanning = true;
        this.state = 'scanning';
        this.updateStatus('scanning', L.scanner_active || 'Scanning...');
        this.iosScanLoop();
      } catch (e) {
        ppvWarn('[iOS Camera] Start error:', e);
        // Show specific error message based on error type
        let errorMsg = '❌ Kamera nicht verfügbar';
        if (e.name === 'NotAllowedError' || e.name === 'PermissionDeniedError') {
          errorMsg = '❌ Kamera-Zugriff verweigert';
          window.ppvToast('Bitte erlaube den Kamerazugriff in den Browser-Einstellungen', 'error');
        } else if (e.name === 'NotFoundError' || e.name === 'DevicesNotFoundError') {
          errorMsg = '❌ Keine Kamera gefunden';
        } else if (e.name === 'NotReadableError' || e.name === 'TrackStartError') {
          errorMsg = '❌ Kamera wird verwendet';
          window.ppvToast('Die Kamera wird von einer anderen App verwendet', 'error');
        } else if (e.name === 'OverconstrainedError') {
          errorMsg = '❌ Kamera nicht kompatibel';
        } else if (e.name === 'SecurityError') {
          errorMsg = '❌ HTTPS erforderlich';
          window.ppvToast('Kamera funktioniert nur über HTTPS', 'error');
        }
        console.error('[iOS Camera] Detailed error:', e.name, e.message);
        this.updateStatus('error', errorMsg);
      }
    }

    iosScanLoop() {
      if (!this.scanning || !this.iosVideo || !this.iosCanvas) return;

      const video = this.iosVideo, canvas = this.iosCanvas, ctx = this.iosCanvasCtx;

      if (video.readyState === video.HAVE_ENOUGH_DATA) {
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        // Use more aggressive inversion attempts for better detection
        const code = jsQR(imageData.data, imageData.width, imageData.height, {
          inversionAttempts: 'attemptBoth'  // Try both normal and inverted
        });
        if (code && code.data) this.onScanSuccess(code.data);
      }

      // Faster scan loop: 33ms = ~30fps (was 100ms = 10fps)
      if (this.scanning) setTimeout(() => this.iosScanLoop(), 33);
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

            // ✅ FIX: Add scan to UI immediately with unique scan_id
            const now = new Date();
            const scanId = data.scan_id || `local-${data.user_id}-${now.getTime()}`;

            if (STATE.uiManager) {
              STATE.uiManager.addScanItem({
                scan_id: scanId,
                user_id: data.user_id,
                customer_name: data.customer_name || null,
                email: data.email || null,
                avatar: data.avatar || null,
                message: data.message,
                points: data.points || 1,
                date_short: now.toLocaleDateString('de-DE', {day: '2-digit', month: '2-digit'}).replace(/\./g, '.'),
                time_short: now.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'}),
                success: true,
                _realtime: true  // Prepend to top of list
              });
            }

            this.startPauseCountdown();
          } else {
            this.updateStatus('warning', '⚠️ ' + (data.message || L.error_generic || 'Fehler'));
            window.ppvToast(data.message || '⚠️ Fehler', 'warning');

            // ✅ FIX: Add error scan to UI immediately with unique scan_id
            if (STATE.uiManager && data.user_id) {
              const now = new Date();
              const scanId = data.scan_id || `local-err-${data.user_id}-${now.getTime()}`;

              STATE.uiManager.addScanItem({
                scan_id: scanId,
                user_id: data.user_id,
                customer_name: data.customer_name || null,
                email: data.email || null,
                avatar: data.avatar || null,
                message: data.message || '⚠️ Fehler',
                points: 0,
                date_short: now.toLocaleDateString('de-DE', {day: '2-digit', month: '2-digit'}).replace(/\./g, '.'),
                time_short: now.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'}),
                success: false,
                _realtime: true  // Prepend to top of list
              });
            }

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

      // Trigger refocus when resuming scan (helps XCover 4S and similar devices)
      setTimeout(() => this.triggerRefocus(), 200);
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
      this.stopPeriodicRefocus();
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

    // ✅ FIX: Setup change listener for camp-type select (click doesn't work for select)
    const campTypeSelect = document.getElementById('camp-type');
    if (campTypeSelect && !campTypeSelect.dataset.listenerAdded) {
      campTypeSelect.dataset.listenerAdded = 'true';
      campTypeSelect.addEventListener('change', (e) => {
        STATE.campaignManager?.updateVisibilityByType(e.target.value);
        STATE.campaignManager?.updateValueLabel(e.target.value);
      });
    }

    // ✅ FIX: Setup change listener for campaign filter select
    const campFilterSelect = document.getElementById('ppv-campaign-filter');
    if (campFilterSelect && !campFilterSelect.dataset.listenerAdded) {
      campFilterSelect.dataset.listenerAdded = 'true';
      campFilterSelect.addEventListener('change', () => {
        STATE.campaignManager?.load();
      });
    }
  }

  function handleBodyClick(e) {
    const target = e.target;

    // Campaign actions - use closest() to handle clicks on child elements (emoji text, etc.)
    const editBtn = target.closest('.ppv-camp-edit');
    const deleteBtn = target.closest('.ppv-camp-delete');
    const archiveBtn = target.closest('.ppv-camp-archive');
    const cloneBtn = target.closest('.ppv-camp-clone');

    if (editBtn) {
      ppvLog('[QR] Edit button clicked, id:', editBtn.dataset.id);
      const camp = STATE.campaignManager?.campaigns.find(c => c.id == editBtn.dataset.id);
      if (camp) {
        ppvLog('[QR] Found campaign:', camp.title);
        STATE.campaignManager.edit(camp);
      } else {
        ppvWarn('[QR] Campaign not found for id:', editBtn.dataset.id);
      }
    }
    if (deleteBtn) {
      ppvLog('[QR] Delete button clicked, id:', deleteBtn.dataset.id);
      STATE.campaignManager?.delete(deleteBtn.dataset.id);
    }
    if (archiveBtn) {
      ppvLog('[QR] Archive button clicked, id:', archiveBtn.dataset.id);
      STATE.campaignManager?.archive(archiveBtn.dataset.id);
    }
    if (cloneBtn) {
      ppvLog('[QR] Clone button clicked, id:', cloneBtn.dataset.id);
      STATE.campaignManager?.clone(cloneBtn.dataset.id);
    }

    // New campaign button
    if (target.id === 'ppv-new-campaign' || target.closest('#ppv-new-campaign')) {
      STATE.campaignManager?.resetForm();
      STATE.campaignManager?.showModal();
    }

    // Save campaign (ID is "camp-save" in PHP)
    if (target.id === 'camp-save' || target.closest('#camp-save')) {
      STATE.campaignManager?.save();
    }

    // Cancel campaign (ID is "camp-cancel" in PHP)
    if (target.id === 'camp-cancel' || target.closest('#camp-cancel')) {
      STATE.campaignManager?.hideModal();
    }

    // ✅ NOTE: Campaign type change handled via setupCampTypeListener()

    // Campaign filter
    if (target.id === 'ppv-campaign-filter') {
      STATE.campaignManager?.load();
    }

    // ✅ CSV Export Button - toggle dropdown
    if (target.id === 'ppv-csv-export-btn' || target.closest('#ppv-csv-export-btn')) {
      e.preventDefault();
      const menu = document.getElementById('ppv-csv-export-menu');
      if (menu) {
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
      }
    }

    // ✅ CSV Export Options - handle export
    if (target.classList.contains('ppv-csv-export-option') || target.closest('.ppv-csv-export-option')) {
      e.preventDefault();
      const option = target.closest('.ppv-csv-export-option') || target;
      const period = option.dataset.period;

      // Hide dropdown
      const menu = document.getElementById('ppv-csv-export-menu');
      if (menu) menu.style.display = 'none';

      // Handle export based on period
      handleCsvExport(period);
    }

    // ✅ Close CSV dropdown when clicking outside
    if (!target.closest('.ppv-csv-wrapper')) {
      const menu = document.getElementById('ppv-csv-export-menu');
      if (menu) menu.style.display = 'none';
    }
  }

  // ============================================================
  // CSV EXPORT HANDLER
  // ============================================================
  async function handleCsvExport(period) {
    const storeKey = getStoreKey();
    if (!storeKey) {
      window.ppvToast('⚠️ Kein Store ausgewählt', 'warning');
      return;
    }

    let dateParam = '';

    if (period === 'today') {
      dateParam = new Date().toISOString().split('T')[0];
    } else if (period === 'date') {
      // Show date picker
      const selectedDate = prompt('Datum eingeben (YYYY-MM-DD):', new Date().toISOString().split('T')[0]);
      if (!selectedDate) return;
      dateParam = selectedDate;
    } else if (period === 'month') {
      // Current month
      const now = new Date();
      dateParam = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    }

    window.ppvToast('⏳ CSV wird erstellt...', 'info');

    try {
      const res = await fetch(`/wp-json/punktepass/v1/pos/export-csv?period=${period}&date=${dateParam}`, {
        headers: { 'PPV-POS-Token': storeKey }
      });

      if (!res.ok) throw new Error('Export failed');

      const data = await res.json();

      if (data.csv) {
        // Download CSV
        const blob = new Blob([data.csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = data.filename || `pos-export-${dateParam}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        window.ppvToast('✅ CSV heruntergeladen', 'success');
      } else {
        throw new Error(data.message || 'Export failed');
      }
    } catch (err) {
      ppvWarn('[CSV] Export error:', err);
      window.ppvToast('❌ Export fehlgeschlagen', 'error');
    }
  }

  // ============================================================
  // CLEANUP
  // ============================================================
  function cleanup() {
    // Close Ably connection if exists
    if (STATE.ablyInstance) {
      ppvLog('[Ably] Closing connection on cleanup');
      STATE.ablyInstance.close();
      STATE.ablyInstance = null;
    }

    // Clear polling interval if exists
    if (STATE.pollInterval) {
      ppvLog('[Poll] Clearing interval on cleanup');
      clearInterval(STATE.pollInterval);
      STATE.pollInterval = null;
    }

    // ✅ FIX: Remove visibilitychange handler to prevent memory leak
    if (STATE.visibilityHandler) {
      document.removeEventListener('visibilitychange', STATE.visibilityHandler);
      STATE.visibilityHandler = null;
    }

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

    // ============================================================
    // REAL-TIME UPDATES: Ably (primary) or Polling (fallback)
    // ============================================================
    const POLL_INTERVAL_MS = 10000; // 10s fallback polling

    // Check if Ably is configured
    const ablyConfig = window.PPV_STORE_DATA?.ably;
    const storeId = window.PPV_STORE_DATA?.store_id;

    // 🔍 DEBUG: Log all conditions
    console.log('[Ably Debug] PPV_STORE_DATA:', window.PPV_STORE_DATA);
    console.log('[Ably Debug] ablyConfig:', ablyConfig);
    console.log('[Ably Debug] storeId:', storeId);
    console.log('[Ably Debug] typeof Ably:', typeof Ably);

    if (ablyConfig && typeof Ably !== 'undefined' && storeId) {
      // ABLY MODE: Real-time updates via WebSocket
      ppvLog('[Ably] Initializing with key:', ablyConfig.key.substring(0, 10) + '...');

      STATE.ablyInstance = new Ably.Realtime({ key: ablyConfig.key });

      // Subscribe to store's channel
      const channelName = 'store-' + storeId;
      const channel = STATE.ablyInstance.channels.get(channelName);

      STATE.ablyInstance.connection.on('connected', () => {
        ppvLog('[Ably] Connected');
        // Stop polling if it was running
        if (STATE.pollInterval) {
          clearInterval(STATE.pollInterval);
          STATE.pollInterval = null;
        }
      });

      STATE.ablyInstance.connection.on('disconnected', () => {
        ppvLog('[Ably] Disconnected, starting fallback polling');
        startPolling();
      });

      STATE.ablyInstance.connection.on('failed', (err) => {
        ppvLog('[Ably] Connection failed:', err);
        startPolling();
      });

      // Handle incoming scan events
      channel.subscribe('new-scan', (message) => {
        ppvLog('[Ably] New scan received:', message.data);

        // ✅ FIX: Deduplication is now handled by addScanItem using scan_id
        // Just pass the data through - the UI manager will skip if scan_id already displayed
        if (STATE.uiManager) {
          STATE.uiManager.addScanItem({ ...message.data, _realtime: true });
        }
      });

      // Handle reward requests
      channel.subscribe('reward-request', (message) => {
        ppvLog('[Ably] Reward request received:', message.data);
        // Refresh logs to show pending rewards
        STATE.scanProcessor?.loadLogs();
      });

      // 📡 Handle campaign updates (create/update/delete)
      channel.subscribe('campaign-update', (message) => {
        ppvLog('[Ably] Campaign update received:', message.data);
        window.ppvToast(`📢 Kampány ${message.data.action === 'created' ? 'létrehozva' : message.data.action === 'updated' ? 'frissítve' : 'törölve'}`, 'info');
        // Refresh campaign list
        STATE.campaignManager?.load();
      });

      // 📡 Handle reward/prämien updates
      channel.subscribe('reward-update', (message) => {
        ppvLog('[Ably] Reward update received:', message.data);
        window.ppvToast(`🎁 Prämie ${message.data.action === 'created' ? 'létrehozva' : message.data.action === 'updated' ? 'frissítve' : 'törölve'}`, 'info');
      });

      STATE.initialized = true;
      ppvLog('[QR] Initialization complete (Ably mode)');

    } else {
      // POLLING MODE: Fallback when Ably not available
      ppvLog('[Poll] Ably not available, using polling fallback');
      startPolling();
      STATE.initialized = true;
      ppvLog('[QR] Initialization complete (polling mode)');
    }

    function startPolling() {
      if (!getStoreKey()) {
        ppvLog('[Poll] No store key, skipping polling');
        return;
      }

      // Clear existing interval if any
      if (STATE.pollInterval) {
        clearInterval(STATE.pollInterval);
      }

      ppvLog('[Poll] Starting polling (every ' + (POLL_INTERVAL_MS / 1000) + 's)');

      // Poll function - quick AJAX request that doesn't block PHP
      const poll = () => {
        if (document.hidden) return; // Skip if tab is hidden
        STATE.scanProcessor?.loadLogs();
      };

      // Start interval
      STATE.pollInterval = setInterval(poll, POLL_INTERVAL_MS);
    }

    // ✅ FIX: Move visibilitychange handler to STATE to prevent memory leak
    if (!STATE.visibilityHandler) {
      let lastVis = 0;
      STATE.visibilityHandler = () => {
        if (!document.hidden && Date.now() - lastVis > 3000) {
          lastVis = Date.now();
          STATE.campaignManager?.load();
          STATE.scanProcessor?.loadLogs();
        }
      };
      document.addEventListener('visibilitychange', STATE.visibilityHandler);
    }
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
