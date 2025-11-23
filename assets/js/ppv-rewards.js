/**
 * PunktePass – Einlösungen Management v8.2 CLEAN
 * Turbo.js compatible, clean architecture
 * FIXED: Multiple interval creation bug causing 503 errors
 * Author: PunktePass / Erik Borota
 */

(function() {
  'use strict';

  // ✅ DEBUG MODE - Set to false in production to reduce console spam
  const PPV_DEBUG = false;
  const ppvLog = (...args) => { if (PPV_DEBUG) console.log(...args); };

  // Guard against multiple script loads
  if (window.PPV_REWARDS_LOADED) {
    ppvLog('[REWARDS] Already loaded, skipping');
    return;
  }
  window.PPV_REWARDS_LOADED = true;

  // ============================================================
  // GLOBAL STATE
  // ============================================================
  const STATE = {
    initialized: false,
    refreshInterval: null,
    previousPendingIds: new Set(),
    notificationSystem: null
  };

  const L = window.ppv_lang || {};

  // ============================================================
  // HELPERS
  // ============================================================
  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    const pad = n => String(n).padStart(2, '0');
    return `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  function getStoreID() {
    if (window.PPV_STORE_ID && parseInt(window.PPV_STORE_ID) > 0) {
      return parseInt(window.PPV_STORE_ID);
    }
    return parseInt(sessionStorage.getItem("ppv_store_id")) || 1;
  }

  function getBaseUrl() {
    return window.ppv_rewards_rest?.base || "/wp-json/ppv/v1/";
  }

  function getPosToken() {
    return (window.PPV_STORE_KEY || "").trim() ||
           (sessionStorage.getItem("ppv_store_key") || "").trim() || "";
  }

  // ============================================================
  // TOAST SYSTEM
  // ============================================================
  function showToast(msg, type = "info") {
    const el = document.createElement("div");
    el.className = `ppv-toast ${type}`;
    el.textContent = msg;
    document.body.appendChild(el);
    requestAnimationFrame(() => el.classList.add("show"));
    setTimeout(() => {
      el.classList.remove("show");
      setTimeout(() => el.remove(), 350);
    }, 2600);
  }

  function showHandlerToast(message, type = 'info', duration = 4000) {
    const toast = document.createElement('div');
    toast.className = `ppv-handler-toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.3s';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  }

  // ============================================================
  // NOTIFICATION SYSTEM
  // ============================================================
  class HandlerNotificationSystem {
    constructor() {
      this.pendingCount = 0;
      this.lastNotifiedIds = new Set();
      this.soundEnabled = localStorage.getItem('ppv_handler_sound') !== '0';
      this.notificationEnabled = localStorage.getItem('ppv_handler_notifications') !== '0';
      this.stylesInjected = false;
    }

    init() {
      this.requestPermissions();
      this.injectStyles();
    }

    requestPermissions() {
      if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
      }
    }

    injectStyles() {
      if (this.stylesInjected) return;
      this.stylesInjected = true;

      const styles = `
        @keyframes ppvPagePulse { 0% { background-color: transparent; } 50% { background-color: rgba(245, 158, 11, 0.1); } 100% { background-color: transparent; } }
        @keyframes ppvPulseGlow { 0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); } 50% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); } }
        @keyframes ppvToastSlideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .ppv-handler-toast { position: fixed; top: 20px; right: 20px; padding: 16px 24px; background: white; border-radius: 8px; box-shadow: 0 6px 20px rgba(0,0,0,0.15); font-size: 14px; font-weight: 600; z-index: 999999; animation: ppvToastSlideIn 0.3s ease-out; max-width: 350px; }
        .ppv-handler-toast.success { border-left: 4px solid #10b981; color: #10b981; }
        .ppv-handler-toast.new-redeem { border-left: 4px solid #f59e0b; color: #f59e0b; background: linear-gradient(135deg, rgba(245,158,11,0.05), rgba(251,146,60,0.05)); }
        @media (max-width: 640px) { .ppv-handler-toast { top: 10px; right: 10px; left: 10px; max-width: none; } }
        .ppv-notification-control { position: fixed; bottom: 80px; right: 20px; z-index: 999998; display: flex; gap: 10px; }
        .ppv-notification-btn { width: 50px; height: 50px; border-radius: 50%; border: none; background: linear-gradient(135deg, #f59e0b, #fb923c); color: white; cursor: pointer; font-size: 20px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(245,158,11,0.3); transition: all 0.2s; animation: ppvPulseGlow 2s infinite; }
        .ppv-notification-btn:hover { transform: scale(1.1); }
        .ppv-notification-btn.disabled { opacity: 0.5; animation: none; }
      `;
      const style = document.createElement('style');
      style.id = 'ppv-notification-styles';
      style.textContent = styles;
      if (!document.getElementById('ppv-notification-styles')) {
        document.head.appendChild(style);
      }
    }

    playSound() {
      if (!this.soundEnabled) return;
      try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = 800;
        osc.type = 'sine';
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.5);
      } catch (e) { /* ignore */ }
    }

    showBrowserNotification(title, options = {}) {
      if ('Notification' in window && Notification.permission === 'granted' && this.notificationEnabled) {
        new Notification(title, { icon: '', badge: '', requireInteraction: true, ...options });
      }
    }

    updatePageTitle(count) {
      this.pendingCount = count;
      const baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
      document.title = count > 0 ? `(${count}) ${baseTitle}` : baseTitle;
    }

    notifyNewRedeem(redeem) {
      const id = parseInt(redeem.id);
      if (this.lastNotifiedIds.has(id)) return;
      this.lastNotifiedIds.add(id);

      const name = `${redeem.first_name || ''} ${redeem.last_name || ''}`.trim() || redeem.user_email || 'Kunde';
      const reward = redeem.reward_title || 'Belohnung';
      const points = redeem.points_spent || 0;

      this.playSound();
      this.showBrowserNotification(L.redeem_new_notification || 'Neue Einlösung!', {
        body: `${name} - ${reward} (${points} Punkte)`,
        tag: `redeem-${id}`
      });
      showHandlerToast(`${name} - ${reward} (${points} Punkte)`, 'new-redeem', 6000);
    }

    toggleSound() {
      this.soundEnabled = !this.soundEnabled;
      localStorage.setItem('ppv_handler_sound', this.soundEnabled ? '1' : '0');
    }

    toggleNotifications() {
      this.notificationEnabled = !this.notificationEnabled;
      localStorage.setItem('ppv_handler_notifications', this.notificationEnabled ? '1' : '0');
    }
  }

  // ============================================================
  // NOTIFICATION CONTROLS
  // ============================================================
  function addNotificationControls() {
    // Remove old controls first
    const old = document.getElementById('ppv-notification-control');
    if (old) old.remove();

    const redeemList = document.getElementById("ppv-redeem-list");
    if (!redeemList) return;

    const controls = document.createElement('div');
    controls.id = 'ppv-notification-control';
    controls.className = 'ppv-notification-control';
    controls.innerHTML = `
      <button class="ppv-notification-btn" id="ppv-toggle-sound" title="${L.redeem_sound_toggle || 'Sound an/aus'}">
        <i class="ri-volume-up-line"></i>
      </button>
      <button class="ppv-notification-btn" id="ppv-toggle-notifications" title="${L.redeem_notif_toggle || 'Benachrichtigungen an/aus'}">
        <i class="ri-notification-3-line"></i>
      </button>
    `;
    document.body.appendChild(controls);

    document.getElementById('ppv-toggle-sound')?.addEventListener('click', function() {
      STATE.notificationSystem.toggleSound();
      this.classList.toggle('disabled', !STATE.notificationSystem.soundEnabled);
    });

    document.getElementById('ppv-toggle-notifications')?.addEventListener('click', function() {
      STATE.notificationSystem.toggleNotifications();
      this.classList.toggle('disabled', !STATE.notificationSystem.notificationEnabled);
    });

    if (!STATE.notificationSystem.soundEnabled) {
      document.getElementById('ppv-toggle-sound')?.classList.add('disabled');
    }
    if (!STATE.notificationSystem.notificationEnabled) {
      document.getElementById('ppv-toggle-notifications')?.classList.add('disabled');
    }
  }

  // ============================================================
  // API FUNCTIONS
  // ============================================================
  async function loadRedeemRequests() {
    const redeemList = document.getElementById("ppv-redeem-list");
    if (!redeemList) return;

    const storeID = getStoreID();
    const base = getBaseUrl();
    const token = getPosToken();

    redeemList.innerHTML = `<div class='ppv-loading'>${L.redeem_loading || 'Lade Einlösungen...'}</div>`;

    try {
      const res = await fetch(`${base}redeem/list?store_id=${storeID}`, {
        headers: { "PPV-POS-Token": token }
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const json = await res.json();

      if (!json?.success || !json?.items?.length) {
        redeemList.innerHTML = `<div class='ppv-redeem-empty'>${L.redeem_no_items || 'Keine Einlösungen vorhanden'}</div>`;
        STATE.notificationSystem?.updatePageTitle(0);
        return;
      }

      redeemList.innerHTML = "";

      const pending = json.items.filter(r => r.status === 'pending');
      const approved = json.items.filter(r => r.status === 'approved');

      STATE.notificationSystem?.updatePageTitle(pending.length);

      // Pending section
      if (pending.length > 0) {
        const title = document.createElement('h4');
        title.textContent = L.redeem_pending_section || 'Offene Einlösungen';
        title.style.cssText = 'margin: 20px 0 15px; font-size: 16px;';
        redeemList.appendChild(title);

        pending.forEach(r => {
          const id = parseInt(r.id);
          if (!STATE.previousPendingIds.has(id)) {
            STATE.notificationSystem?.notifyNewRedeem(r);
          }
          STATE.previousPendingIds.add(id);

          const card = document.createElement("div");
          card.className = "ppv-redeem-item status-pending";
          card.dataset.id = r.id;
          card.innerHTML = `
            <strong>${escapeHtml(r.reward_title || 'Belohnung')}</strong>
            <small>${escapeHtml(r.user_email || 'Unbekannt')}</small>
            <div class="ppv-redeem-meta">
              <span class="ppv-redeem-meta-item"><i class="ri-star-fill"></i> ${r.points_spent || 0} ${L.redeem_points || 'Punkte'}</span>
              <span class="ppv-redeem-meta-item"><i class="ri-time-line"></i> ${formatDate(r.redeemed_at)}</span>
            </div>
            <div class="ppv-redeem-actions">
              <button class="ppv-approve" data-id="${r.id}">${L.redeem_btn_approve || 'Bestätigen'}</button>
              <button class="ppv-reject" data-id="${r.id}">${L.redeem_btn_reject || 'Ablehnen'}</button>
            </div>
          `;
          redeemList.appendChild(card);
        });
      }

      // Approved section
      if (approved.length > 0) {
        const title = document.createElement('h4');
        title.textContent = L.redeem_approved_section || 'Bestätigte Einlösungen';
        title.style.cssText = 'margin: 30px 0 15px; font-size: 16px;';
        redeemList.appendChild(title);

        approved.forEach(r => {
          const card = document.createElement("div");
          card.className = "ppv-redeem-item status-approved";
          card.innerHTML = `
            <strong>${escapeHtml(r.reward_title || 'Belohnung')}</strong>
            <small>${escapeHtml(r.user_email || 'Unbekannt')}</small>
            <div class="ppv-redeem-meta">
              <span class="ppv-redeem-meta-item"><i class="ri-star-fill"></i> ${r.points_spent || 0} ${L.redeem_points || 'Punkte'}</span>
              <span class="ppv-redeem-meta-item"><i class="ri-checkbox-circle-line"></i> ${L.redeem_status_approved || 'Bestätigt'}</span>
            </div>
          `;
          redeemList.appendChild(card);
        });
      }

    } catch (err) {
      ppvLog('[REWARDS] Load error:', err);
      redeemList.innerHTML = `<div class="ppv-error">${L.redeem_load_error || 'Fehler beim Laden'}</div>`;
    }
  }

  async function loadRecentLogs() {
    const logList = document.getElementById("ppv-log-list");
    if (!logList) return;

    const storeID = getStoreID();
    const base = getBaseUrl();
    const token = getPosToken();

    try {
      const res = await fetch(`${base}redeem/log?store_id=${storeID}`, {
        headers: { "PPV-POS-Token": token }
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const json = await res.json();

      if (!json?.success || !json?.items?.length) {
        logList.innerHTML = `<p>${L.redeem_no_logs || 'Keine Logs vorhanden'}</p>`;
        return;
      }

      logList.innerHTML = '';

      json.items.forEach(item => {
        const statusBadge = item.status === 'approved'
          ? `${L.redeem_status_approved || 'Bestätigt'}`
          : `${L.redeem_status_rejected || 'Abgelehnt'}`;
        const color = item.status === 'approved' ? '#10b981' : '#ef4444';

        const logItem = document.createElement('div');
        logItem.className = 'ppv-log-item';
        logItem.style.cssText = `padding:10px;margin-bottom:8px;background:#f5f5f5;border-left:3px solid ${color};border-radius:4px;font-size:12px;`;
        logItem.innerHTML = `
          <strong>${escapeHtml(item.user_email)}</strong>
          <span style="float:right;color:${color};">${statusBadge}</span><br>
          <small>${item.points_spent} ${L.redeem_points || 'Punkte'} - ${formatDate(item.redeemed_at)}</small>
        `;
        logList.appendChild(logItem);
      });

    } catch (err) {
      ppvLog('[REWARDS] Log error:', err);
    }
  }

  async function updateRedeemStatus(id, status) {
    const storeID = getStoreID();
    const base = getBaseUrl();
    const token = getPosToken();

    try {
      const res = await fetch(`${base}redeem/update`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'PPV-POS-Token': token },
        body: JSON.stringify({ id, status, store_id: storeID })
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const json = await res.json();

      if (json.success) {
        showToast(json.message, 'success');
        setTimeout(() => {
          loadRedeemRequests();
          loadRecentLogs();
        }, 500);
      } else {
        showToast(json.message, 'error');
      }
    } catch (err) {
      ppvLog('[REWARDS] Update error:', err);
      showToast(L.redeem_error_processing || 'Fehler bei der Verarbeitung', 'error');
    }
  }

  // ============================================================
  // EVENT DELEGATION (handles dynamic elements)
  // ============================================================
  function setupEventDelegation() {
    // Remove old listener by using a named function
    document.body.removeEventListener('click', handleBodyClick);
    document.body.addEventListener('click', handleBodyClick);
  }

  function handleBodyClick(e) {
    const target = e.target;

    // Approve button
    if (target.classList.contains('ppv-approve') || target.closest('.ppv-approve')) {
      e.preventDefault();
      const btn = target.classList.contains('ppv-approve') ? target : target.closest('.ppv-approve');
      const id = parseInt(btn.dataset.id);
      if (id) updateRedeemStatus(id, 'approved');
    }

    // Reject button
    if (target.classList.contains('ppv-reject') || target.closest('.ppv-reject')) {
      e.preventDefault();
      const btn = target.classList.contains('ppv-reject') ? target : target.closest('.ppv-reject');
      const id = parseInt(btn.dataset.id);
      if (id) updateRedeemStatus(id, 'cancelled');
    }

    // Monthly receipt button
    if (target.id === 'ppv-monthly-receipt-btn' || target.closest('#ppv-monthly-receipt-btn')) {
      e.preventDefault();
      openMonthlyReceiptModal();
    }
  }

  // ============================================================
  // MONTHLY RECEIPT MODAL
  // ============================================================
  function openMonthlyReceiptModal() {
    const today = new Date();
    const currentMonth = String(today.getMonth() + 1).padStart(2, '0');
    const currentYear = today.getFullYear();

    const months = [
      L.month_01 || 'Januar', L.month_02 || 'Februar', L.month_03 || 'März',
      L.month_04 || 'April', L.month_05 || 'Mai', L.month_06 || 'Juni',
      L.month_07 || 'Juli', L.month_08 || 'August', L.month_09 || 'September',
      L.month_10 || 'Oktober', L.month_11 || 'November', L.month_12 || 'Dezember'
    ];

    let monthOptions = months.map((m, i) =>
      `<option value="${String(i + 1).padStart(2, '0')}">${m}</option>`
    ).join('');

    const modal = document.createElement('div');
    modal.className = 'ppv-modal ppv-monthly-modal';
    modal.style.display = 'flex';
    modal.innerHTML = `
      <div class="ppv-modal-inner" style="max-width:500px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
          <h3 style="margin:0;">${L.redeem_modal_title || 'Monatliche Abrechnung'}</h3>
          <button class="ppv-modal-close" style="background:none;border:none;font-size:24px;cursor:pointer;">&times;</button>
        </div>
        <label style="display:block;margin-bottom:8px;font-weight:600;">${L.redeem_modal_select_period || 'Zeitraum auswählen:'}</label>
        <div style="display:flex;gap:12px;margin-bottom:20px;">
          <div style="flex:1;">
            <label style="font-size:13px;">${L.redeem_modal_month || 'Monat'}:</label>
            <select id="ppv-month-select" style="width:100%;padding:10px;border:1px solid #333;border-radius:6px;margin-top:4px;">${monthOptions}</select>
          </div>
          <div style="flex:1;">
            <label style="font-size:13px;">${L.redeem_modal_year || 'Jahr'}:</label>
            <input type="number" id="ppv-year-select" value="${currentYear}" min="2020" max="${currentYear}" style="width:100%;padding:10px;border:1px solid #333;border-radius:6px;margin-top:4px;">
          </div>
        </div>
        <div style="display:flex;gap:12px;">
          <button class="ppv-btn ppv-btn-primary" id="ppv-create-monthly-btn" style="flex:1;">${L.redeem_modal_generate || 'Generieren'}</button>
          <button class="ppv-btn ppv-btn-outline ppv-modal-close" style="flex:1;">${L.redeem_modal_cancel || 'Abbrechen'}</button>
        </div>
        <div id="ppv-monthly-result" style="margin-top:20px;"></div>
      </div>
    `;

    document.body.appendChild(modal);

    // Set current month
    setTimeout(() => {
      const monthSelect = modal.querySelector('#ppv-month-select');
      if (monthSelect) monthSelect.value = currentMonth;
    }, 50);

    // Close handlers
    modal.querySelectorAll('.ppv-modal-close').forEach(btn => {
      btn.addEventListener('click', () => modal.remove());
    });

    // Generate handler
    modal.querySelector('#ppv-create-monthly-btn')?.addEventListener('click', async () => {
      const btn = modal.querySelector('#ppv-create-monthly-btn');
      const month = modal.querySelector('#ppv-month-select').value;
      const year = modal.querySelector('#ppv-year-select').value;
      const resultBox = modal.querySelector('#ppv-monthly-result');
      const storeID = getStoreID();
      const base = getBaseUrl();
      const token = getPosToken();

      btn.disabled = true;
      btn.textContent = L.redeem_modal_generating || 'Generiere...';
      resultBox.innerHTML = '';

      try {
        const res = await fetch(`${base}redeem/monthly-receipt`, {
          method: "POST",
          headers: { "Content-Type": "application/json", "PPV-POS-Token": token },
          body: JSON.stringify({ store_id: storeID, year, month })
        });

        const json = await res.json();

        if (!json.success) {
          resultBox.innerHTML = `<div class='ppv-error'>${json.message}</div>`;
          btn.disabled = false;
          btn.textContent = L.redeem_modal_generate || 'Generieren';
          return;
        }

        const downloadUrl = `${base}redeem/monthly-receipt-download?store_id=${storeID}&year=${year}&month=${month}`;
        window.open(downloadUrl, '_blank');
        showToast(L.redeem_modal_downloaded || "Monatsbeleg wird heruntergeladen!", "success");
        setTimeout(() => modal.remove(), 300);

      } catch (err) {
        ppvLog('[REWARDS] Monthly PDF error:', err);
        resultBox.innerHTML = `<div class='ppv-error'>Fehler bei der Generierung</div>`;
      }

      btn.disabled = false;
      btn.textContent = L.redeem_modal_generate || 'Generieren';
    });
  }

  // ============================================================
  // TAB SWITCHING
  // ============================================================
  function setupTabs() {
    const tabBtns = document.querySelectorAll('.ppv-tab-btn');
    const tabContents = document.querySelectorAll('.ppv-tab-content');

    tabBtns.forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        const tabName = btn.dataset.tab;

        tabContents.forEach(tab => tab.style.display = 'none');
        tabBtns.forEach(b => {
          b.classList.remove('ppv-tab-active');
          b.style.color = '#666';
          b.style.borderBottom = '3px solid transparent';
        });

        const selectedTab = document.getElementById(`ppv-tab-${tabName}`);
        if (selectedTab) selectedTab.style.display = 'block';

        btn.classList.add('ppv-tab-active');
        btn.style.color = '#0066cc';
        btn.style.borderBottom = '3px solid #0066cc';
      });
    });
  }

  // ============================================================
  // CLEANUP
  // ============================================================
  function cleanup() {
    if (STATE.refreshInterval) {
      clearInterval(STATE.refreshInterval);
      STATE.refreshInterval = null;
    }

    // Remove notification controls
    const controls = document.getElementById('ppv-notification-control');
    if (controls) controls.remove();
  }

  // ============================================================
  // INITIALIZATION
  // ============================================================
  function init() {
    // Check if we have reward elements on this page
    const redeemList = document.getElementById("ppv-redeem-list");
    const logList = document.getElementById("ppv-log-list");

    if (!redeemList && !logList) {
      ppvLog('[REWARDS] No reward elements on this page');
      cleanup();
      return;
    }

    ppvLog('[REWARDS] Initializing...');

    // Cleanup old state
    cleanup();

    // Initialize notification system
    if (!STATE.notificationSystem) {
      STATE.notificationSystem = new HandlerNotificationSystem();
    }
    STATE.notificationSystem.init();

    // Setup event delegation
    setupEventDelegation();
    setupTabs();

    // Load data
    loadRedeemRequests();
    loadRecentLogs();

    // Add notification controls
    setTimeout(addNotificationControls, 500);

    // Start auto-refresh (only if not already running)
    if (!STATE.refreshInterval) {
      ppvLog('[REWARDS] Starting auto-refresh interval (30s)');
      STATE.refreshInterval = setInterval(() => {
        ppvLog('[REWARDS] Auto-refresh tick');
        loadRedeemRequests();
        loadRecentLogs();
      }, 30000);  // Changed from 10s to 30s to reduce load
    }

    STATE.initialized = true;
    ppvLog('[REWARDS] Initialization complete');
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
  document.addEventListener('turbo:before-visit', function() {
    ppvLog('[REWARDS] Turbo before-visit - cleanup');
    cleanup();
  });

  // Custom SPA event support - cleanup FIRST, then init
  window.addEventListener('ppv:spa-navigate', function() {
    ppvLog('[REWARDS] SPA navigate - cleanup then init');
    cleanup();
    // Small delay to ensure DOM is ready
    setTimeout(init, 100);
  });

  ppvLog('[REWARDS] Script loaded v8.2 (fixed interval bug)');

})();
