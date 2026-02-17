/**
 * PunktePass QR Scanner - Init Module
 * Contains: Initialization, Event Delegation, CSV Export, Redemption Modal
 * Depends on: all other ppv-qr-*.js modules
 */
(function() {
  'use strict';

  // Guard against multiple script loads
  if (window.PPV_QR_LOADED) {
    window.PPV_QR.log('[QR] Already loaded, skipping');
    return;
  }
  window.PPV_QR_LOADED = true;

  const {
    log: ppvLog,
    warn: ppvWarn,
    L,
    STATE,
    initGpsTracking,
    stopGpsTracking,
    getStoreKey,
    getScannerId,
    preloadSounds,
    playSound,
    escapeHtml,
    UIManager,
    OfflineSyncManager,
    ScanProcessor,
    CampaignManager,
    CameraScanner,
    SettingsManager
  } = window.PPV_QR;

  // ============================================================
  // EVENT DELEGATION
  // ============================================================
  function setupEventDelegation() {
    document.body.removeEventListener('click', handleBodyClick);
    document.body.addEventListener('click', handleBodyClick);

    const campTypeSelect = document.getElementById('camp-type');
    if (campTypeSelect && !campTypeSelect.dataset.listenerAdded) {
      campTypeSelect.dataset.listenerAdded = 'true';
      campTypeSelect.addEventListener('change', (e) => {
        STATE.campaignManager?.updateVisibilityByType(e.target.value);
        STATE.campaignManager?.updateValueLabel(e.target.value);
      });
    }

    const campFilterSelect = document.getElementById('ppv-campaign-filter');
    if (campFilterSelect && !campFilterSelect.dataset.listenerAdded) {
      campFilterSelect.dataset.listenerAdded = 'true';
      campFilterSelect.addEventListener('change', () => {
        STATE.campaignManager?.load();
      });
    }

    const campFilialeSelect = document.getElementById('ppv-campaign-filiale');
    if (campFilialeSelect && !campFilialeSelect.dataset.listenerAdded) {
      campFilialeSelect.dataset.listenerAdded = 'true';
      campFilialeSelect.addEventListener('change', () => {
        ppvLog('[QR] Campaign filiale changed to:', campFilialeSelect.value);
        STATE.campaignManager?.load();
      });
    }
  }

  function handleBodyClick(e) {
    const target = e.target;

    // Campaign actions
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

    // Save campaign
    if (target.id === 'camp-save' || target.closest('#camp-save')) {
      STATE.campaignManager?.save();
    }

    // Cancel campaign
    if (target.id === 'camp-cancel' || target.closest('#camp-cancel')) {
      STATE.campaignManager?.hideModal();
    }

    // Campaign filter
    if (target.id === 'ppv-campaign-filter') {
      STATE.campaignManager?.load();
    }

    // CSV Export Button
    if (target.id === 'ppv-csv-export-btn' || target.closest('#ppv-csv-export-btn')) {
      e.preventDefault();
      const menu = document.getElementById('ppv-csv-export-menu');
      if (menu) {
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
      }
    }

    // CSV Export Options
    if (target.classList.contains('ppv-csv-export-option') || target.closest('.ppv-csv-export-option')) {
      e.preventDefault();
      const option = target.closest('.ppv-csv-export-option') || target;
      const period = option.dataset.period;
      const menu = document.getElementById('ppv-csv-export-menu');
      if (menu) menu.style.display = 'none';
      handleCsvExport(period);
    }

    // Close CSV dropdown when clicking outside
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
      window.ppvToast('⚠️ ' + (L.no_store_selected || 'Kein Store ausgewählt'), 'warning');
      return;
    }

    let dateParam = '';

    if (period === 'today') {
      dateParam = new Date().toISOString().split('T')[0];
    } else if (period === 'date') {
      const selectedDate = prompt((L.csv_date_prompt || 'Datum eingeben (YYYY-MM-DD)') + ':', new Date().toISOString().split('T')[0]);
      if (!selectedDate) return;
      dateParam = selectedDate;
    } else if (period === 'month') {
      const now = new Date();
      dateParam = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    }

    window.ppvToast('⏳ ' + (L.csv_creating || 'CSV wird erstellt...'), 'info');

    try {
      const res = await fetch(`/wp-json/punktepass/v1/pos/export-csv?period=${period}&date=${dateParam}`, {
        headers: { 'PPV-POS-Token': storeKey }
      });

      if (!res.ok) throw new Error('Export failed');

      const data = await res.json();

      if (data.csv) {
        const blob = new Blob([data.csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = data.filename || `pos-export-${dateParam}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        window.ppvToast('✅ ' + (L.csv_downloaded || 'CSV heruntergeladen'), 'success');
      } else {
        throw new Error(data.message || 'Export failed');
      }
    } catch (err) {
      ppvWarn('[CSV] Export error:', err);
      window.ppvToast('❌ ' + (L.export_failed || 'Export fehlgeschlagen'), 'error');
    }
  }

  // ============================================================
  // CLEANUP
  // ============================================================
  function cleanup() {
    if (STATE.ablySubscriberId && window.PPV_ABLY_MANAGER) {
      ppvLog('[Ably] Unsubscribing via shared manager on cleanup');
      window.PPV_ABLY_MANAGER.unsubscribe(STATE.ablySubscriberId);
      STATE.ablySubscriberId = null;
    }

    if (STATE.pollInterval) {
      ppvLog('[Poll] Clearing interval on cleanup');
      clearInterval(STATE.pollInterval);
      STATE.pollInterval = null;
    }

    if (STATE.visibilityHandler) {
      document.removeEventListener('visibilitychange', STATE.visibilityHandler);
      STATE.visibilityHandler = null;
    }

    stopGpsTracking();

    STATE.cameraScanner?.cleanup();
    STATE.cameraScanner = null;
    STATE.campaignManager = null;
    STATE.scanProcessor = null;
    STATE.uiManager = null;
    STATE.initialized = false;
  }

  // ============================================================
  // HANDLER REDEMPTION MODAL
  // ============================================================
  let activeRedemptionModal = null;
  let activeRedemptionToken = null;

  function showHandlerRedemptionModal(data) {
    closeHandlerRedemptionModal();

    activeRedemptionToken = data.token;

    const modal = document.createElement('div');
    modal.id = 'ppv-handler-redemption-modal';
    modal.className = 'ppv-handler-redemption-modal';

    const userName = escapeHtml(data.customer_name || data.user_name || data.user_email || `Kunde #${data.user_id}`);
    const userEmail = data.email ? escapeHtml(data.email) : '';
    const avatarHtml = data.avatar
      ? `<img src="${escapeHtml(data.avatar)}" class="ppv-redemption-avatar" alt="">`
      : `<div class="ppv-redemption-avatar-placeholder">👤</div>`;

    const rewardTitle = escapeHtml(data.reward_title || 'Prämie');
    const rewardPoints = data.reward_points || 0;
    const currentPoints = data.current_points || 0;
    const rewardType = data.reward_type || 'info';
    const rewardValue = data.reward_value || 0;

    const isPercentType = rewardType === 'discount_percent';
    const isFixedType = rewardType === 'discount_fixed';
    const isFreeProduct = rewardType === 'free_product';

    const purchaseAmountHtml = isPercentType ? `
        <div class="ppv-redemption-purchase-amount" style="margin: 15px 0; padding: 15px; background: linear-gradient(135deg, #fff3e0, #ffe0b2); border-radius: 12px; border: 2px solid #ff9800;">
          <label for="ppv-purchase-amount" style="display: block; margin-bottom: 8px; font-weight: 600; color: #e65100;">
            <span style="font-size: 18px;">💰</span> ${L.enter_purchase_amount || 'Einkaufsbetrag eingeben'}:
          </label>
          <div style="display: flex; align-items: center; gap: 8px;">
            <input type="number" id="ppv-purchase-amount"
                   placeholder="0.00"
                   min="0.01"
                   step="0.01"
                   style="flex: 1; padding: 12px 15px; font-size: 20px; font-weight: bold; border: 2px solid #ff9800; border-radius: 8px; text-align: right;"
                   required>
            <span style="font-size: 20px; font-weight: bold; color: #e65100;">€</span>
          </div>
          <p style="margin: 8px 0 0 0; font-size: 13px; color: #bf360c;">
            ℹ️ ${L.customer_gets_discount || 'Der Kunde erhält'} <strong>${rewardValue}% ${L.discount || 'Rabatt'}</strong> ${L.on_this_amount || 'auf diesen Betrag'}
          </p>
        </div>
    ` : '';

    // 🎁 Build reward type badge and value display
    let rewardTypeBadge = '';
    let rewardValueDisplay = '';

    if (isFixedType && rewardValue > 0) {
      rewardTypeBadge = `<span style="display: inline-block; background: linear-gradient(135deg, #4caf50, #388e3c); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 8px;">💶 ${L.reward_type_fixed || 'FIX RABATT'}</span>`;
      rewardValueDisplay = `<div style="font-size: 28px; font-weight: 700; color: #2e7d32; margin: 10px 0;">−${rewardValue}€</div>`;
    } else if (isPercentType && rewardValue > 0) {
      rewardTypeBadge = `<span style="display: inline-block; background: linear-gradient(135deg, #ff9800, #f57c00); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 8px;">📊 ${L.reward_type_percent || '% RABATT'}</span>`;
      rewardValueDisplay = `<div style="font-size: 28px; font-weight: 700; color: #e65100; margin: 10px 0;">−${rewardValue}%</div>`;
    } else if (isFreeProduct) {
      const freeProductVal = data.free_product_value || 0;
      rewardTypeBadge = `<span style="display: inline-block; background: linear-gradient(135deg, #9c27b0, #7b1fa2); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 8px;">🎁 ${L.reward_type_free_product || 'GRATIS PRODUKT'}</span>`;
      rewardValueDisplay = freeProductVal > 0
        ? `<div style="font-size: 28px; font-weight: 700; color: #7b1fa2; margin: 10px 0;">${L.value || 'Wert'}: ${freeProductVal}€</div>`
        : `<div style="font-size: 22px; font-weight: 600; color: #7b1fa2; margin: 10px 0;">${L.free || 'Gratis'}!</div>`;
    }

    // Show description if available
    const rewardDescription = data.reward_description ? escapeHtml(data.reward_description) : '';
    const rewardDescriptionHtml = rewardDescription
      ? `<div style="font-size: 13px; color: #666; margin-top: 5px; font-style: italic;">${rewardDescription}</div>`
      : '';

    modal.innerHTML = `
      <div class="ppv-handler-redemption-content">
        <div class="ppv-redemption-header">
          <span class="ppv-redemption-icon">🎁</span>
          <h3>${L.confirm_redemption || 'Einlösung bestätigen'}</h3>
        </div>

        <div class="ppv-redemption-user-info">
          ${avatarHtml}
          <div class="ppv-redemption-user-details">
            <div class="ppv-redemption-user-name">${userName}</div>
            ${userEmail ? `<div class="ppv-redemption-user-email">${userEmail}</div>` : ''}
          </div>
        </div>

        <div class="ppv-redemption-reward-info" style="text-align: center; padding: 15px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 12px; margin: 10px 0;">
          ${rewardTypeBadge}
          <div class="ppv-redemption-reward-title" style="font-size: 16px; font-weight: 600; color: #333;">${rewardTitle}</div>
          ${rewardDescriptionHtml}
          ${rewardValueDisplay}
          <div class="ppv-redemption-reward-points" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #dee2e6;">
            <span class="ppv-redemption-cost" style="color: #dc3545; font-weight: 600;">−${rewardPoints} ${L.points || 'Punkte'}</span>
            <span class="ppv-redemption-balance" style="color: #666; margin-left: 8px;">(${L.balance || 'Guthaben'}: ${currentPoints} ${L.points || 'Punkte'})</span>
          </div>
        </div>

        ${purchaseAmountHtml}

        <div class="ppv-redemption-rejection-reason" style="display:none;">
          <label for="ppv-rejection-reason">${L.rejection_reason_optional || 'Ablehnungsgrund (optional)'}:</label>
          <input type="text" id="ppv-rejection-reason" placeholder="${L.rejection_reason_placeholder || 'z.B. Prämie nicht verfügbar'}" maxlength="255">
        </div>

        <div class="ppv-redemption-actions">
          <button class="ppv-btn ppv-btn-reject" id="ppv-handler-reject">
            <span class="ppv-btn-icon">❌</span>
            <span class="ppv-btn-text">${L.reject || 'Ablehnen'}</span>
          </button>
          <button class="ppv-btn ppv-btn-confirm" id="ppv-handler-confirm">
            <span class="ppv-btn-icon">✅</span>
            <span class="ppv-btn-text">${L.confirm || 'Bestätigen'}</span>
          </button>
        </div>
      </div>
    `;

    modal.dataset.rewardType = rewardType;
    modal.dataset.rewardValue = rewardValue;
    modal.dataset.freeProductValue = data.free_product_value || 0;

    document.body.appendChild(modal);
    activeRedemptionModal = modal;

    playSound('reward');  // 🎁 Play reward notification sound

    requestAnimationFrame(() => {
      modal.classList.add('show');
    });

    const confirmBtn = modal.querySelector('#ppv-handler-confirm');
    const rejectBtn = modal.querySelector('#ppv-handler-reject');
    const rejectionReasonDiv = modal.querySelector('.ppv-redemption-rejection-reason');
    const rejectionReasonInput = modal.querySelector('#ppv-rejection-reason');
    const purchaseAmountInput = modal.querySelector('#ppv-purchase-amount');

    let showingRejectionReason = false;

    confirmBtn.addEventListener('click', async () => {
      if (rewardType === 'discount_percent') {
        const purchaseAmount = parseFloat(purchaseAmountInput?.value) || 0;
        if (purchaseAmount <= 0) {
          window.ppvToast('⚠️ ' + (L.please_enter_purchase_amount || 'Bitte Einkaufsbetrag eingeben!'), 'warning');
          purchaseAmountInput?.focus();
          return;
        }
      }

      confirmBtn.disabled = true;
      confirmBtn.innerHTML = '<span class="ppv-btn-icon">⏳</span><span class="ppv-btn-text">' + (L.processing || 'Wird verarbeitet...') + '</span>';

      const purchaseAmount = rewardType === 'discount_percent' ? (parseFloat(purchaseAmountInput?.value) || 0) : null;
      await handleHandlerResponse('approve', data.token, null, purchaseAmount);
    });

    rejectBtn.addEventListener('click', async () => {
      if (!showingRejectionReason) {
        showingRejectionReason = true;
        rejectionReasonDiv.style.display = 'block';
        rejectionReasonInput.focus();
        rejectBtn.innerHTML = '<span class="ppv-btn-icon">❌</span><span class="ppv-btn-text">' + (L.reject_now || 'Jetzt ablehnen') + '</span>';
        return;
      }

      rejectBtn.disabled = true;
      rejectBtn.innerHTML = '<span class="ppv-btn-icon">⏳</span><span class="ppv-btn-text">' + (L.processing || 'Wird verarbeitet...') + '</span>';
      const reason = rejectionReasonInput.value.trim() || (L.rejected || 'Abgelehnt');
      await handleHandlerResponse('reject', data.token, reason);
    });
  }

  function closeHandlerRedemptionModal() {
    if (activeRedemptionModal) {
      activeRedemptionModal.classList.remove('show');
      setTimeout(() => {
        activeRedemptionModal?.remove();
        activeRedemptionModal = null;
        activeRedemptionToken = null;
      }, 300);
    }
  }

  async function handleHandlerResponse(action, token, reason, purchaseAmount = null) {
    try {
      const payload = {
        token: token,
        action: action
      };

      if (reason) {
        payload.reason = reason;
      }

      if (purchaseAmount !== null && purchaseAmount > 0) {
        payload.purchase_amount = purchaseAmount;
      }

      const response = await fetch('/wp-json/ppv/v1/redemption/handler-response', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'PPV-POS-Token': getStoreKey()
        },
        body: JSON.stringify(payload)
      });

      const data = await response.json();

      if (data.success) {
        if (action === 'approve') {
          window.ppvToast('✅ ' + (L.redemption_confirmed || 'Einlösung bestätigt!'), 'success');
          playSound('success');
        } else {
          window.ppvToast('❌ ' + (L.redemption_rejected || 'Einlösung abgelehnt'), 'info');
        }
        closeHandlerRedemptionModal();
      } else {
        window.ppvToast('⚠️ ' + (data.message || L.error || 'Fehler'), 'error');
        playSound('error');
        const modal = document.getElementById('ppv-handler-redemption-modal');
        if (modal) {
          const confirmBtn = modal.querySelector('#ppv-handler-confirm');
          const rejectBtn = modal.querySelector('#ppv-handler-reject');
          if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<span class="ppv-btn-icon">✅</span><span class="ppv-btn-text">' + (L.confirm || 'Bestätigen') + '</span>';
          }
          if (rejectBtn) {
            rejectBtn.disabled = false;
            rejectBtn.innerHTML = '<span class="ppv-btn-icon">❌</span><span class="ppv-btn-text">' + (L.reject_now || 'Jetzt ablehnen') + '</span>';
          }
        }
      }
    } catch (err) {
      console.error('[Handler] Response error:', err);
      window.ppvToast('⚠️ ' + (L.network_error || 'Netzwerkfehler'), 'error');
      playSound('error');
    }
  }

  // ============================================================
  // QUICK STATS (server-rendered initial values, JS increments)
  // ============================================================

  // Increment stats locally when a new scan comes in (no API needed)
  function incrementQuickStats(points) {
    const scansEl = document.getElementById('ppv-qs-scans');
    const pointsEl = document.getElementById('ppv-qs-points');
    if (scansEl) {
      const cur = parseInt(scansEl.textContent) || 0;
      animateStat('ppv-qs-scans', cur + 1);
    }
    if (pointsEl && points > 0) {
      const cur = parseInt(pointsEl.textContent) || 0;
      animateStat('ppv-qs-points', cur + points);
    }
  }

  // Try API refresh (best-effort, server-rendered values are the fallback)
  async function loadQuickStats() {
    const storeKey = getStoreKey();
    const storeId = window.PPV_STORE_DATA?.store_id || 0;
    if (!storeKey && !storeId) return;
    try {
      const mgmt = window.ppv_rewards_mgmt || {};
      const nonce = mgmt.nonce || '';
      const baseUrl = mgmt.base || '/wp-json/ppv/v1/';
      const url = baseUrl + 'pos/stats?store_id=' + storeId;
      const headers = { 'PPV-POS-Token': storeKey };
      if (nonce) headers['X-WP-Nonce'] = nonce;
      const res = await fetch(url, { headers, credentials: 'same-origin' });
      if (!res.ok) { ppvLog('[QS] Stats response:', res.status); return; }
      const data = await res.json();
      if (!data.success || !data.stats) return;
      const s = data.stats;
      animateStat('ppv-qs-scans', s.today_scans || 0);
      animateStat('ppv-qs-points', s.today_points || 0);
      animateStat('ppv-qs-rewards', s.today_rewards || 0);
    } catch (e) {
      ppvLog('[QS] Stats fetch failed:', e);
    }
  }

  function animateStat(id, newVal) {
    const el = document.getElementById(id);
    if (!el) return;
    const old = parseInt(el.textContent) || 0;
    if (old === newVal) return;
    el.textContent = newVal;
    el.classList.remove('ppv-bump');
    void el.offsetWidth; // reflow
    el.classList.add('ppv-bump');
  }

  // ============================================================
  // INITIALIZATION
  // ============================================================
  function init() {
    const now = Date.now();
    if (now - STATE.lastInitTime < 2000) {
      ppvLog('[QR] Init throttled (too soon)');
      return;
    }
    STATE.lastInitTime = now;

    const campaignList = document.getElementById('ppv-campaign-list');
    const posInput = document.getElementById('ppv-pos-input');
    const posLog = document.getElementById('ppv-pos-log');

    if (!campaignList && !posInput && !posLog) {
      cleanup();
      return;
    }

    ppvLog('[QR] Initializing...');
    cleanup();

    preloadSounds();
    initGpsTracking();

    STATE.uiManager = new UIManager();
    STATE.uiManager.init();

    STATE.scanProcessor = new ScanProcessor(STATE.uiManager);

    STATE.campaignManager = new CampaignManager(STATE.uiManager);
    STATE.campaignManager.init();

    STATE.cameraScanner = new CameraScanner(STATE.scanProcessor);
    STATE.cameraScanner.init();

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
    loadQuickStats();

    // Listen for successful scans to increment stats locally
    document.addEventListener('ppv:scan-success', (e) => {
      const pts = e.detail?.points || 1;
      incrementQuickStats(pts);
      // Also try API refresh in background (best-effort)
      setTimeout(loadQuickStats, 2000);
    });

    // ============================================================
    // REAL-TIME UPDATES: Ably (primary) or Polling (fallback)
    // ============================================================
    const POLL_INTERVAL_MS = 10000;

    const ablyConfig = window.PPV_STORE_DATA?.ably;
    const storeId = window.PPV_STORE_DATA?.store_id;

    if (ablyConfig && window.PPV_ABLY_MANAGER && storeId) {
      ppvLog('[Ably] Initializing via shared manager with key:', ablyConfig.key.substring(0, 10) + '...');

      const manager = window.PPV_ABLY_MANAGER;
      const channelName = 'store-' + storeId;

      if (!manager.init({ key: ablyConfig.key, channel: channelName })) {
        ppvLog('[Ably] Failed to init shared manager, using polling');
        startPolling();
        STATE.initialized = true;
        return;
      }

      manager.onStateChange((state) => {
        if (state === 'connected') {
          ppvLog('[Ably] Connected via shared manager');
          if (STATE.pollInterval) {
            clearInterval(STATE.pollInterval);
            STATE.pollInterval = null;
          }
        } else if (state === 'disconnected') {
          console.warn('⚠️ [Ably] DISCONNECTED - starting fallback polling');
          ppvLog('[Ably] Disconnected, starting fallback polling');
          startPolling();
        } else if (state === 'failed') {
          console.error('❌ [Ably] CONNECTION FAILED');
          ppvLog('[Ably] Connection failed');
          startPolling();
        }
      });

      STATE.ablySubscriberId = 'qr-center-' + storeId;

      manager.subscribe(channelName, 'new-scan', (message) => {
        ppvLog('[Ably] New scan received:', message.data);
        if (STATE.uiManager) {
          STATE.uiManager.addScanItem({ ...message.data, _realtime: true });
        } else {
          console.warn('📡 [Ably] STATE.uiManager is null!');
        }
        if (message.data?.success !== false) {
          incrementQuickStats(message.data?.points || 1);
        }
      }, STATE.ablySubscriberId);

      manager.subscribe(channelName, 'reward-request', (message) => {
        ppvLog('[Ably] Reward request received:', message.data);
        STATE.scanProcessor?.loadLogs();
      }, STATE.ablySubscriberId);

      manager.subscribe(channelName, 'redemption-request', (message) => {
        // Only show modal if this is the scanner that scanned the user's QR
        const myScannerId = getScannerId();
        const targetScannerId = message.data?.scanner_id;

        // If scanner_id is specified, only show on that device
        // If scanner_id is null/undefined (legacy), show on all devices
        if (targetScannerId && myScannerId && Number(targetScannerId) !== Number(myScannerId)) {
          ppvLog('[Ably] Redemption request for different scanner:', targetScannerId, 'my:', myScannerId);
          return; // Not for this scanner
        }

        showHandlerRedemptionModal(message.data);
      }, STATE.ablySubscriberId);

      manager.subscribe(channelName, 'redemption-cancelled', (message) => {
        closeHandlerRedemptionModal();
        window.ppvToast('❌ ' + (L.customer_cancelled || 'Kunde hat abgebrochen'), 'info');
      }, STATE.ablySubscriberId);

      // Close modal on other devices when one handler processes the redemption
      manager.subscribe(channelName, 'redemption-handled', (message) => {
        ppvLog('[Ably] Redemption handled by another device:', message.data);
        // Only close if this modal shows the same token
        if (activeRedemptionToken && message.data?.token === activeRedemptionToken) {
          closeHandlerRedemptionModal();
          const actionText = message.data.action === 'approved'
            ? (L.handled_by_colleague_approved || '✅ Kollege hat bestätigt')
            : (L.handled_by_colleague_rejected || '❌ Kollege hat abgelehnt');
          window.ppvToast(actionText, 'info');
        }
      }, STATE.ablySubscriberId);

      manager.subscribe(channelName, 'campaign-update', (message) => {
        ppvLog('[Ably] Campaign update received:', message.data);
        window.ppvToast(`📢 ${L.campaign || 'Kampagne'} ${message.data.action === 'created' ? (L.created || 'erstellt') : message.data.action === 'updated' ? (L.updated || 'aktualisiert') : (L.deleted || 'gelöscht')}`, 'info');
        STATE.campaignManager?.load();
      }, STATE.ablySubscriberId);

      manager.subscribe(channelName, 'reward-update', (message) => {
        ppvLog('[Ably] Reward update received:', message.data);
        window.ppvToast(`🎁 ${L.reward || 'Prämie'} ${message.data.action === 'created' ? (L.created || 'erstellt') : message.data.action === 'updated' ? (L.updated || 'aktualisiert') : (L.deleted || 'gelöscht')}`, 'info');
      }, STATE.ablySubscriberId);

      STATE.initialized = true;
      ppvLog('[QR] Initialization complete (Ably shared manager mode)');

    } else {
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

      if (STATE.pollInterval) {
        clearInterval(STATE.pollInterval);
      }

      ppvLog('[Poll] Starting polling (every ' + (POLL_INTERVAL_MS / 1000) + 's)');

      const poll = () => {
        if (document.hidden) return;
        STATE.scanProcessor?.loadLogs();
      };

      STATE.pollInterval = setInterval(poll, POLL_INTERVAL_MS);
    }

    if (!STATE.visibilityHandler) {
      let lastVis = 0;
      STATE.visibilityHandler = () => {
        if (!document.hidden && Date.now() - lastVis > 3000) {
          lastVis = Date.now();
          STATE.campaignManager?.load();
          STATE.scanProcessor?.loadLogs();
          loadQuickStats();
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

  ppvLog('[QR-Init] Script loaded v6.5');

})();
