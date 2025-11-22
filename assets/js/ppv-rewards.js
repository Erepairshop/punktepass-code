/**
 * PunktePass – Einlösungen Management + Handler Notifications
 * Version: 7.3 - WITH NOTIFICATION SYSTEM + PPV_LANG + API MANAGER
 * ✅ Handler toast notifications
 * ✅ Browser notifications
 * ✅ Audio alerts
 * ✅ Page title badge
 * ✅ PPV_Lang translations
 * ✅ Uses centralized ppv-api-manager.js
 */

if (window.PPV_REWARDS_LOADED) {
  console.warn('⚠️ PPV Rewards JS already loaded - skipping duplicate!');
} else {
  window.PPV_REWARDS_LOADED = true;

  console.log("✅ PunktePass Einlösungen JS v7.3 geladen");

  // Use centralized API manager (fallback to fetch if not available)
  const ppvFetch = window.ppvFetch || window.apiFetch || fetch;

  // 🌍 FORDÍTÁSOK
  const L = window.ppv_lang || {};

  /* ============================================================
   * 🔔 HANDLER NOTIFICATION SYSTEM
   * ============================================================ */

  class HandlerNotificationSystem {
    constructor() {
      this.pendingCount = 0;
      this.lastNotifiedIds = new Set();
      this.soundEnabled = true;
      this.notificationEnabled = true;
      this.requestPermissions();
      this.injectStyles();
    }

    requestPermissions() {
      if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
      }
    }

    injectStyles() {
      const styles = `
        @keyframes ppvPagePulse {
          0% { background-color: transparent; }
          50% { background-color: rgba(245, 158, 11, 0.1); }
          100% { background-color: transparent; }
        }
        
        @keyframes ppvPulseGlow {
          0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
          50% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
        }
        
        .ppv-handler-toast {
          position: fixed;
          top: 20px;
          right: 20px;
          padding: 16px 24px;
          background: white;
          border-radius: 8px;
          box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
          font-size: 14px;
          font-weight: 600;
          z-index: 999999;
          animation: ppvToastSlideIn 0.3s ease-out;
          max-width: 350px;
        }
        
        .ppv-handler-toast.success {
          border-left: 4px solid #10b981;
          color: #10b981;
        }
        
        .ppv-handler-toast.new-redeem {
          border-left: 4px solid #f59e0b;
          color: #f59e0b;
          background: linear-gradient(135deg, rgba(245, 158, 11, 0.05), rgba(251, 146, 60, 0.05));
          animation: ppvToastPulse 0.5s ease-in-out, ppvToastSlideIn 0.3s ease-out;
          font-weight: 700;
          font-size: 15px;
        }
        
        @keyframes ppvToastSlideIn {
          from {
            transform: translateX(400px);
            opacity: 0;
          }
          to {
            transform: translateX(0);
            opacity: 1;
          }
        }
        
        @keyframes ppvToastPulse {
          0%, 100% { transform: scale(1); }
          50% { transform: scale(1.02); }
        }
        
        @media (max-width: 640px) {
          .ppv-handler-toast {
            top: 10px;
            right: 10px;
            left: 10px;
            max-width: none;
          }
        }
        
        .ppv-notification-control {
          position: fixed;
          bottom: 20px;
          right: 20px;
          z-index: 999998;
          display: flex;
          gap: 10px;
        }
        
        .ppv-notification-btn {
          width: 50px;
          height: 50px;
          border-radius: 50%;
          border: none;
          background: linear-gradient(135deg, #f59e0b, #fb923c);
          color: white;
          cursor: pointer;
          font-size: 20px;
          display: flex;
          align-items: center;
          justify-content: center;
          box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
          transition: all 0.2s;
          animation: ppvPulseGlow 2s infinite;
        }
        
        .ppv-notification-btn:hover {
          transform: scale(1.1);
          box-shadow: 0 6px 16px rgba(245, 158, 11, 0.4);
        }
        
        .ppv-notification-btn.disabled {
          opacity: 0.5;
          cursor: not-allowed;
          animation: none;
        }
      `;
      
      const $style = document.createElement('style');
      $style.textContent = styles;
      document.head.appendChild($style);
    }

    playSound() {
      if (!this.soundEnabled) return;

      try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);

        oscillator.frequency.value = 800;
        oscillator.type = 'sine';

        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.5);
      } catch (e) {
        console.log('🔊 Audio notification skipped:', e);
      }
    }

    showBrowserNotification(title, options = {}) {
      if ('Notification' in window && Notification.permission === 'granted' && this.notificationEnabled) {
        new Notification(title, {
          icon: '🎁',
          badge: '📧',
          requireInteraction: true,
          ...options
        });
      }
    }

    updatePageTitle(count) {
      this.pendingCount = count;
      const baseTitle = document.title.replace(/^\(\d+\)\s+📧\s+/, '');
      if (count > 0) {
        document.title = `(${count}) 📧 ${baseTitle}`;
      } else {
        document.title = baseTitle;
      }
    }

    drawAttention() {
      const body = document.body;
      body.style.animation = 'ppvPagePulse 0.5s ease-in-out';
      
      setTimeout(() => {
        body.style.animation = '';
      }, 500);
    }

    notifyNewRedeem(redeem) {
      const redeemId = parseInt(redeem.id);
      
      if (this.lastNotifiedIds.has(redeemId)) {
        return;
      }

      this.lastNotifiedIds.add(redeemId);

      const customerName = `${redeem.first_name || ''} ${redeem.last_name || ''}`.trim() || redeem.user_email || 'Ügyfél';
      const rewardTitle = redeem.reward_title || 'Belohnung';
      const points = redeem.points_spent || 0;

      const message = `📧 ${customerName}\n🎁 ${rewardTitle}\n⭐ ${points} Punkte`;

      console.log(`🔔 [HANDLER] Új beváltás: ${message}`);

      // 1. Hang
      this.playSound();

      // 2. Browser notification
      this.showBrowserNotification(L.redeem_new_notification || '🎁 Neue Beváltás!', {
        body: message,
        tag: `redeem-${redeemId}`,
      });

      // 3. Page villogás
      this.drawAttention();

      // 4. Toast megjelenítése
      showHandlerToast(
        `📧 ${customerName} - ${rewardTitle} (${points} pont)`,
        'new-redeem',
        6000
      );
    }

    toggleSound() {
      this.soundEnabled = !this.soundEnabled;
      localStorage.setItem('ppv_handler_sound', this.soundEnabled ? '1' : '0');
    }

    toggleNotifications() {
      this.notificationEnabled = !this.notificationEnabled;
      localStorage.setItem('ppv_handler_notifications', this.notificationEnabled ? '1' : '0');
    }

    loadSettings() {
      this.soundEnabled = localStorage.getItem('ppv_handler_sound') !== '0';
      this.notificationEnabled = localStorage.getItem('ppv_handler_notifications') !== '0';
    }
  }

  const notificationSystem = new HandlerNotificationSystem();
  notificationSystem.loadSettings();

  /* ============================================================
   * 🔔 TOAST HELPER
   * ============================================================ */
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
    console.log(`📢 [HANDLER TOAST] ${type.toUpperCase()}: ${message}`);
    
    const $toast = document.createElement('div');
    $toast.className = `ppv-handler-toast ${type}`;
    $toast.textContent = message;
    
    document.body.appendChild($toast);
    
    setTimeout(() => {
      $toast.style.opacity = '0';
      $toast.style.transition = 'opacity 0.3s';
      setTimeout(() => {
        $toast.remove();
      }, 300);
    }, duration);
  }

  function addNotificationControls() {
    if (document.getElementById('ppv-notification-control')) {
      return; // Already exists
    }

    const $controls = document.createElement('div');
    $controls.id = 'ppv-notification-control';
    $controls.className = 'ppv-notification-control';
    $controls.innerHTML = `
      <button class="ppv-notification-btn" id="ppv-toggle-sound" title="${L.redeem_sound_toggle || 'Hang be/ki'}">
        <i class="ri-volume-up-line"></i>
      </button>
      <button class="ppv-notification-btn" id="ppv-toggle-notifications" title="${L.redeem_notif_toggle || 'Értesítések be/ki'}">
        <i class="ri-notification-3-line"></i>
      </button>
    `;
    
    document.body.appendChild($controls);
    
    document.getElementById('ppv-toggle-sound').addEventListener('click', function() {
      notificationSystem.toggleSound();
      this.classList.toggle('disabled', !notificationSystem.soundEnabled);
      console.log('🔊 Hang:', notificationSystem.soundEnabled ? 'BE' : 'KI');
    });
    
    document.getElementById('ppv-toggle-notifications').addEventListener('click', function() {
      notificationSystem.toggleNotifications();
      this.classList.toggle('disabled', !notificationSystem.notificationEnabled);
      console.log('🔔 Értesítések:', notificationSystem.notificationEnabled ? 'BE' : 'KI');
    });
    
    if (!notificationSystem.soundEnabled) {
      document.getElementById('ppv-toggle-sound').classList.add('disabled');
    }
    if (!notificationSystem.notificationEnabled) {
      document.getElementById('ppv-toggle-notifications').classList.add('disabled');
    }
  }

  /* ============================================================
   * 🛡️ HTML ESCAPE
   * ============================================================ */
  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /* ============================================================
   * 📅 DATE FORMAT
   * ============================================================ */
  function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    const hours = String(d.getHours()).padStart(2, '0');
    const mins = String(d.getMinutes()).padStart(2, '0');
    return `${day}.${month}.${year} ${hours}:${mins}`;
  }

  document.addEventListener("DOMContentLoaded", function () {

    /* ============================================================
     * 🔑 BASE + TOKEN + STORE
     * ============================================================ */
    console.log('📦 [REWARDS] DOMContentLoaded fired');

    const base = window.ppv_rewards_rest?.base || "/wp-json/ppv/v1/";
    let storeID = 0;

    try {
      // 🏪 FILIALE SUPPORT: ALWAYS prioritize window.PPV_STORE_ID over sessionStorage
      // If window.PPV_STORE_ID exists, use it and clear old sessionStorage
      if (window.PPV_STORE_ID && parseInt(window.PPV_STORE_ID) > 0) {
        storeID = parseInt(window.PPV_STORE_ID);
        console.log(`✅ [REWARDS] Using window.PPV_STORE_ID: ${storeID}`);
        // Clear sessionStorage if it differs from current window.PPV_STORE_ID
        const cachedStoreId = sessionStorage.getItem("ppv_store_id");
        if (cachedStoreId && parseInt(cachedStoreId) !== storeID) {
          console.log(`🔄 [REWARDS] Store ID changed: ${cachedStoreId} -> ${storeID}`);
          sessionStorage.removeItem("ppv_store_id");
        }
      } else {
        // Fallback only if window.PPV_STORE_ID is not set
        storeID = parseInt(sessionStorage.getItem("ppv_store_id")) || 1;
        console.warn(`⚠️ [REWARDS] window.PPV_STORE_ID not set, using sessionStorage: ${storeID}`);
      }
    } catch (_) { storeID = 1; }

    sessionStorage.setItem("ppv_store_id", String(storeID));

    let POS_TOKEN =
      (window.PPV_STORE_KEY || "").trim() ||
      (sessionStorage.getItem("ppv_store_key") || "").trim() ||
      "";

    if (window.PPV_STORE_KEY)
      sessionStorage.setItem("ppv_store_key", window.PPV_STORE_KEY);

    const redeemList = document.getElementById("ppv-redeem-list");
    const logList = document.getElementById("ppv-log-list");
    const monthlyReceiptBtn = document.getElementById("ppv-monthly-receipt-btn");

    console.log(`📦 [REWARDS] base: ${base}, storeID: ${storeID}`);

    // ✅ Add notification controls
    setTimeout(() => {
      if (redeemList) {
        addNotificationControls();
      }
    }, 500);

    // ✅ Track previous pending IDs
    let previousPendingIds = new Set();

    /* ============================================================
     * ✅ LOAD REDEEM REQUESTS
     * ============================================================ */
    async function loadRedeemRequests() {
      console.log('📦 [REWARDS] loadRedeemRequests() called');
      
      if (!redeemList) {
        console.error('❌ [REWARDS] redeemList element not found!');
        return;
      }

      const url = `${base}redeem/list?store_id=${storeID}`;
      
      redeemList.innerHTML = "";
      redeemList.innerHTML = `<div class='ppv-loading'>${L.redeem_loading || 'Lade Einlösungen...'}</div>`;

      try {
        const res = await ppvFetch(url, {
          headers: { "PPV-POS-Token": POS_TOKEN }
        });

        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`);
        }

        const json = await res.json();
        
        if (!json?.success || !json?.items?.length) {
          redeemList.innerHTML = "";
          redeemList.innerHTML = `<div class='ppv-redeem-empty'>${L.redeem_no_items || 'Keine Einlösungen vorhanden'}</div>`;
          notificationSystem.updatePageTitle(0);
          return;
        }

        redeemList.innerHTML = "";
        
        const pending = json.items.filter(r => r.status === 'pending');
        const approved = json.items.filter(r => r.status === 'approved');

        console.log(`✅ [REWARDS] Pending: ${pending.length}, Approved: ${approved.length}`);

        // ✅ UPDATE PAGE TITLE
        notificationSystem.updatePageTitle(pending.length);

        // ⏳ PENDING SECTION
        if (pending.length > 0) {
          const pendingTitle = document.createElement('h4');
          pendingTitle.textContent = L.redeem_pending_section || '⏳ Offene Einlösungen';
          pendingTitle.style.cssText = 'margin: 20px 0 15px; font-size: 16px;';
          redeemList.appendChild(pendingTitle);

          pending.forEach((r) => {
            const redeemId = parseInt(r.id);

            // ✅ ÚJ PENDING DETEKTÁLÁSA
            if (!previousPendingIds.has(redeemId)) {
              console.log(`🔔 [REWARDS] Új pending bizonylat: #${redeemId}`);
              notificationSystem.notifyNewRedeem(r);
            }

            previousPendingIds.add(redeemId);

            const card = document.createElement("div");
            card.className = `ppv-redeem-item status-pending`;
            card.dataset.status = 'pending';
            card.dataset.id = r.id;
            
            card.innerHTML = `
              <strong>${escapeHtml(r.reward_title || 'Belohnung')}</strong>
              <small>👤 ${escapeHtml(r.user_email || 'Unbekannt')}</small>
              
              <div class="ppv-redeem-meta">
                <span class="ppv-redeem-meta-item">
                  <i class="ri-star-fill"></i>
                  ${r.points_spent || 0} ${L.redeem_points || 'Punkte'}
                </span>
                <span class="ppv-redeem-meta-item">
                  <i class="ri-time-line"></i>
                  ${formatDate(r.redeemed_at)}
                </span>
              </div>
              
              <div class="ppv-redeem-actions">
                <button class="ppv-approve" data-id="${r.id}">
                  ✅ ${L.redeem_btn_approve || 'Bestätigen'}
                </button>
                <button class="ppv-reject" data-id="${r.id}">
                  ❌ ${L.redeem_btn_reject || 'Ablehnen'}
                </button>
              </div>
            `;
            
            redeemList.appendChild(card);
          });
        }

        // ✅ APPROVED SECTION
        if (approved.length > 0) {
          const approvedTitle = document.createElement('h4');
          approvedTitle.textContent = L.redeem_approved_section || '✅ Bestätigte Einlösungen';
          approvedTitle.style.cssText = 'margin: 30px 0 15px; font-size: 16px;';
          redeemList.appendChild(approvedTitle);

          approved.forEach((r) => {
            const card = document.createElement("div");
            card.className = `ppv-redeem-item status-approved`;
            card.dataset.status = 'approved';
            card.dataset.id = r.id;
            
            const amount = parseFloat(r.actual_amount || r.points_spent || 0);
            
            card.innerHTML = `
              <strong>${escapeHtml(r.reward_title || 'Belohnung')}</strong>
              <small>👤 ${escapeHtml(r.user_email || 'Unbekannt')}</small>
              
              <div class="ppv-redeem-meta">
                <span class="ppv-redeem-meta-item">
                  <i class="ri-star-fill"></i>
                  ${r.points_spent || 0} ${L.redeem_points || 'Punkte'}
                </span>
                <span class="ppv-redeem-meta-item">
                  <i class="ri-euro-line"></i>
                  ${amount} EUR
                </span>
                <span class="ppv-redeem-meta-item">
                  <i class="ri-checkbox-circle-line"></i>
                  ✅ ${L.redeem_status_approved || 'Bestätigt'}
                </span>
              </div>
            `;
            
            redeemList.appendChild(card);
          });
        }

        // ✅ Attach event listeners
        redeemList.querySelectorAll('.ppv-approve').forEach(btn => {
          btn.addEventListener('click', (e) => {
            e.preventDefault();
            const id = parseInt(btn.dataset.id);
            updateRedeemStatus(id, 'approved');
          });
        });

        redeemList.querySelectorAll('.ppv-reject').forEach(btn => {
          btn.addEventListener('click', (e) => {
            e.preventDefault();
            const id = parseInt(btn.dataset.id);
            updateRedeemStatus(id, 'cancelled');
          });
        });

      } catch (err) {
        console.error('❌ [REWARDS] Load error:', err);
        redeemList.innerHTML = `<div class="ppv-error">❌ ${L.redeem_load_error || 'Fehler beim Laden der Daten'}</div>`;
      }
    }

    /* ============================================================
     * 🔄 LOAD RECENT LOGS
     * ============================================================ */
    async function loadRecentLogs() {
      console.log('📦 [REWARDS] loadRecentLogs() called');
      
      if (!logList) {
        return;
      }

      const url = `${base}redeem/log?store_id=${storeID}`;
      
      try {
        const res = await ppvFetch(url, {
          headers: { "PPV-POS-Token": POS_TOKEN }
        });

        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`);
        }

        const json = await res.json();

        if (!json?.success || !json?.items?.length) {
          logList.innerHTML = `<p>${L.redeem_no_logs || 'Keine Logs vorhanden'}</p>`;
          return;
        }

        logList.innerHTML = '';

        json.items.forEach((item) => {
          const statusBadge = item.status === 'approved' 
            ? `✅ ${L.redeem_status_approved || 'Bestätigt'}`
            : `❌ ${L.redeem_status_rejected || 'Abgelehnt'}`;
          const statusColor = item.status === 'approved' ? '#10b981' : '#ef4444';

          const logItem = document.createElement('div');
          logItem.className = 'ppv-log-item';
          logItem.style.cssText = `
            padding: 10px; 
            margin-bottom: 8px; 
            background: #f5f5f5; 
            border-left: 3px solid ${statusColor};
            border-radius: 4px;
            font-size: 12px;
          `;

          logItem.innerHTML = `
            <strong>${escapeHtml(item.user_email)}</strong>
            <span style="float: right; color: ${statusColor};">${statusBadge}</span>
            <br>
            <small>${item.points_spent} ${L.redeem_points || 'Punkte'} • ${formatDate(item.redeemed_at)}</small>
          `;

          logList.appendChild(logItem);
        });

      } catch (err) {
        console.error('❌ [REWARDS] Log error:', err);
      }
    }

    /* ============================================================
     * ✏️ UPDATE REDEEM STATUS
     * ============================================================ */
    async function updateRedeemStatus(id, status) {
      console.log(`📦 [REWARDS] Updating redeem #${id} → ${status}`);

      try {
        const res = await ppvFetch(`${base}redeem/update`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'PPV-POS-Token': POS_TOKEN
          },
          body: JSON.stringify({
            id: id,
            status: status,
            store_id: storeID
          })
        });

        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`);
        }

        const json = await res.json();

        if (json.success) {
          showToast(json.message, 'success');
          setTimeout(() => {
            loadRedeemRequests();
            loadRecentLogs();
          }, 1000);
        } else {
          showToast(`❌ ${json.message}`, 'error');
        }

      } catch (err) {
        console.error('❌ [REWARDS] Update error:', err);
        showToast(`❌ ${L.redeem_error_processing || 'Fehler bei der Verarbeitung'}`, 'error');
      }
    }

    /* ============================================================
     * 📊 MONTHLY RECEIPT MODAL
     * ============================================================ */
    function openMonthlyReceiptModal() {
      const today = new Date();
      const currentMonth = String(today.getMonth() + 1).padStart(2, '0');
      const currentYear = today.getFullYear();

      const months = [
        L.month_01 || 'Januar',
        L.month_02 || 'Februar',
        L.month_03 || 'März',
        L.month_04 || 'April',
        L.month_05 || 'Mai',
        L.month_06 || 'Juni',
        L.month_07 || 'Juli',
        L.month_08 || 'August',
        L.month_09 || 'September',
        L.month_10 || 'Oktober',
        L.month_11 || 'November',
        L.month_12 || 'Dezember'
      ];

      let monthOptions = '';
      months.forEach((month, index) => {
        const value = String(index + 1).padStart(2, '0');
        monthOptions += `<option value="${value}">${month}</option>`;
      });

      const modal = document.createElement('div');
      modal.className = 'ppv-modal ppv-monthly-modal';
      modal.style.display = 'flex';
      
      modal.innerHTML = `
        <div class="ppv-modal-inner" style="max-width: 500px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">📊 ${L.redeem_modal_title || 'Monatliche Dispoziție'}</h3>
            <button class="ppv-modal-close" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
          </div>

          <label style="display: block; margin-bottom: 8px; font-weight: 600;">${L.redeem_modal_select_period || 'Zeitraum auswählen:'}</label>
          <div style="display: flex; gap: 12px; margin-bottom: 20px;">
            <div style="flex: 1;">
              <label style="font-size: 13px;">${L.redeem_modal_month || 'Monat'}:</label>
              <select id="ppv-month-select" style="width: 100%; padding: 10px; border: 1px solid #333; border-radius: 6px; margin-top: 4px;">
                ${monthOptions}
              </select>
            </div>
            <div style="flex: 1;">
              <label style="font-size: 13px;">${L.redeem_modal_year || 'Jahr'}:</label>
              <input type="number" id="ppv-year-select" value="${currentYear}" min="2020" max="${currentYear}" 
                     style="width: 100%; padding: 10px; border: 1px solid #333; border-radius: 6px; margin-top: 4px;">
            </div>
          </div>

          <div style="display: flex; gap: 12px;">
            <button class="ppv-btn ppv-btn-primary" id="ppv-create-monthly-btn" style="flex: 1;">
              📊 ${L.redeem_modal_generate || 'Generieren'}
            </button>
            <button class="ppv-btn ppv-btn-outline ppv-modal-close" style="flex: 1;">
              ${L.redeem_modal_cancel || 'Abbrechen'}
            </button>
          </div>

          <div id="ppv-monthly-result" style="margin-top: 20px;"></div>
        </div>
      `;

      modal.querySelectorAll('.ppv-modal-close').forEach(btn => {
        btn.addEventListener('click', () => {
          modal.remove();
        });
      });

      setTimeout(() => {
        const monthSelect = modal.querySelector('#ppv-month-select');
        if (monthSelect) {
          monthSelect.value = currentMonth;
        }
      }, 50);

      modal.querySelector('#ppv-create-monthly-btn').addEventListener('click', async () => {
    const btn = modal.querySelector('#ppv-create-monthly-btn');
    const monthSelect = modal.querySelector('#ppv-month-select');
    const yearSelect = modal.querySelector('#ppv-year-select');
    const resultBox = modal.querySelector('#ppv-monthly-result');

    const month = monthSelect.value;
    const year = yearSelect.value;

    btn.disabled = true;
    btn.textContent = `⏳ ${L.redeem_modal_generating || 'Generiere...'}`;
    resultBox.innerHTML = '';

    try {
        // 1️⃣ Havi PDF generálás a szerveren
        const res = await ppvFetch(`${base}redeem/monthly-receipt`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "PPV-POS-Token": POS_TOKEN
            },
            body: JSON.stringify({
                store_id: storeID,
                year: year,
                month: month
            })
        });

        // 🔒 Biztonságos JSON ellenőrzés
        let json = null;
        const text = await res.text();

        try {
            json = JSON.parse(text);
        } catch (e) {
            console.error("⚠️ RAW RESPONSE (nem JSON):", text);
            throw new Error("Server returned non-JSON response");
        }

        // 2️⃣ Szerver visszaadta a megnyitási URL-t?
        if (!json.success) {
            resultBox.innerHTML = `<div class='ppv-error'>❌ ${json.message}</div>`;
            btn.disabled = false;
            btn.textContent = `📊 ${L.redeem_modal_generate || 'Generieren'}`;
            return;
        }

        // 3️⃣ PDF URL
        const url = json.receipt_url || json.open_url;

        if (!url) {
            resultBox.innerHTML = `<div class='ppv-error'>❌ Kein PDF-Link zurückgegeben!</div>`;
            btn.disabled = false;
            btn.textContent = `📊 ${L.redeem_modal_generate || 'Generieren'}`;
            return;
        }

   // 4️⃣ PDF megnyitása új ablakban - DOMPDF download endpoint
const downloadUrl = `${base}redeem/monthly-receipt-download?store_id=${storeID}&year=${year}&month=${month}`;

// Nyissunk új tabot → PDF letöltődik
window.open(downloadUrl, '_blank');

// Jelenlegi oldal frissítése 200ms után
setTimeout(() => {
    window.location.href = "/rewards";
}, 200);

// 5️⃣ Modal bezárása
setTimeout(() => modal.remove(), 300);

showToast("📄 Monatsbeleg wird heruntergeladen!", "success");

        showToast("📄 Monatsbeleg geöffnet!", "success");

    } catch (err) {
        console.error("❌ Monthly PDF error:", err);
        resultBox.innerHTML = `<div class='ppv-error'>❌ Fehler bei der Generierung</div>`;
    }

    btn.disabled = false;
    btn.textContent = `📊 ${L.redeem_modal_generate || 'Generieren'}`;
});


           
      document.body.appendChild(modal);
    }

    /* ============================================================
     * 📑 TAB SWITCHING
     * ============================================================ */
    const tabBtns = document.querySelectorAll('.ppv-tab-btn');
    const tabContents = document.querySelectorAll('.ppv-tab-content');

    tabBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const tabName = btn.dataset.tab;
        
        tabContents.forEach(tab => {
          tab.style.display = 'none';
        });

        tabBtns.forEach(b => {
          b.classList.remove('ppv-tab-active');
          b.style.color = '#666';
          b.style.borderBottom = '3px solid transparent';
        });

        const selectedTab = document.getElementById(`ppv-tab-${tabName}`);
        if (selectedTab) {
          selectedTab.style.display = 'block';
        }

        btn.classList.add('ppv-tab-active');
        btn.style.color = '#0066cc';
        btn.style.borderBottom = '3px solid #0066cc';

        if (tabName === 'receipts') {
          loadReceiptsTab();
        }
      });
    });

    /* ============================================================
     * 📄 LOAD RECEIPTS TAB
     * ============================================================ */
    function loadReceiptsTab() {
      const receiptContainer = document.getElementById('ppv-receipts-container');
      
      if (!receiptContainer) {
        return;
      }

      if (receiptContainer.dataset.loaded === 'true') {
        return;
      }

      receiptContainer.innerHTML = `
        <div class="ppv-receipts-filter" style="margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap;">
          <input type="text" id="ppv-receipt-search" placeholder="🔍 ${L.redeem_receipts_search || 'E-Mail keresés...'}" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px; flex: 1; min-width: 200px;">
          
          <input type="date" id="ppv-receipt-date-from" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
          <input type="date" id="ppv-receipt-date-to" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
          
          <button id="ppv-receipt-filter-btn" class="ppv-btn ppv-btn-secondary" style="padding: 10px 20px;">
            🔍 ${L.redeem_receipts_filter || 'Szűrés'}
          </button>

          <button id="ppv-receipt-monthly-btn" class="ppv-btn ppv-btn-secondary" style="padding: 10px 20px;">
            📊 ${L.redeem_receipts_monthly || 'Havi bizonylat'}
          </button>
        </div>

        <div id="ppv-receipts-list" class="ppv-receipts-grid">
          <p class="ppv-loading">⏳ ${L.redeem_receipts_loading || 'Bizonylatok betöltése...'}</p>
        </div>
      `;

      if (typeof window.PPV_RECEIPTS_LOADED === 'undefined') {
        const script = document.createElement('script');
        script.src = window.ppv_plugin_url + 'assets/js/ppv-receipts.js';
        document.body.appendChild(script);
      }

      setTimeout(() => {
        const monthlyBtn = document.getElementById('ppv-receipt-monthly-btn');
        if (monthlyBtn) {
          monthlyBtn.addEventListener('click', (e) => {
            e.preventDefault();
            openMonthlyReceiptModal();
          });
        }
      }, 100);

      receiptContainer.dataset.loaded = 'true';

      setTimeout(() => {
        if (typeof window.ppv_receipts_load === 'function') {
          window.ppv_receipts_load();
        }
      }, 100);
    }

    /* ============================================================
     * 🚀 INITIALIZATION
     * ============================================================ */
    console.log('📦 [REWARDS] Starting initialization');
    
    loadRedeemRequests();
    loadRecentLogs();

    if (monthlyReceiptBtn) {
      monthlyReceiptBtn.addEventListener('click', (e) => {
        e.preventDefault();
        openMonthlyReceiptModal();
      });
    }

    // ✅ Auto-refresh minden 10 másodpercben
    setInterval(() => {
      loadRedeemRequests();
      loadRecentLogs();
    }, 10000);
    
    console.log("✅ [REWARDS] Initialization complete!");
  });
}