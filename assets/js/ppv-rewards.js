/**
 * PunktePass – Einlösungen Admin Dashboard v2.0
 * Modern dashboard with stats, pending list, history, receipts
 */

(function() {
  'use strict';


  const config = window.ppv_rewards_config || {};
  const base = config.base || '/wp-json/ppv/v1/';
  const storeId = config.store_id || window.PPV_STORE_ID || 0;
  const L = window.ppv_lang || {};

  let currentTab = 'pending';
  let pollInterval = null;

  // ============================================================
  // INIT
  // ============================================================
  function init() {
    const container = document.querySelector('.ppv-einloesungen-admin');
    if (!container) return;

    if (container.dataset.initialized === 'true') {
      return;
    }
    container.dataset.initialized = 'true';


    // Load initial data
    loadStats();
    loadPending();

    // Setup tabs
    initTabs();

    // Setup refresh button
    initRefresh();

    // Setup filter listeners
    initFilters();

    // Setup receipt generator
    initReceiptGenerator();

    // Setup real-time
    initRealtime();

  }

  // ============================================================
  // TABS
  // ============================================================
  function initTabs() {
    const tabs = document.querySelectorAll('.ppv-ea-tab');
    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        // 📳 Haptic feedback on tab switch
        if (window.ppvHaptic) window.ppvHaptic('tap');
        const targetTab = tab.dataset.tab;
        if (targetTab === currentTab) return;

        // Update active states
        document.querySelectorAll('.ppv-ea-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.ppv-ea-tab-content').forEach(c => c.classList.remove('active'));

        tab.classList.add('active');
        document.getElementById('tab-' + targetTab)?.classList.add('active');

        currentTab = targetTab;

        // Load content for tab
        if (targetTab === 'pending') {
          loadPending();
        } else if (targetTab === 'history') {
          loadHistory();
        } else if (targetTab === 'receipts') {
          loadReceipts();
        }
      });
    });
  }

  // ============================================================
  // REFRESH BUTTON
  // ============================================================
  function initRefresh() {
    const btn = document.getElementById('ppv-ea-refresh');
    if (btn) {
      btn.addEventListener('click', () => {
        // 📳 Haptic feedback on refresh
        if (window.ppvHaptic) window.ppvHaptic('button');
        btn.classList.add('spinning');
        loadStats();

        if (currentTab === 'pending') loadPending();
        else if (currentTab === 'history') loadHistory();
        else if (currentTab === 'receipts') loadReceipts();

        setTimeout(() => btn.classList.remove('spinning'), 1000);
      });
    }
  }

  // ============================================================
  // FILTERS
  // ============================================================
  function initFilters() {
    const statusFilter = document.getElementById('ppv-ea-filter-status');
    const dateFilter = document.getElementById('ppv-ea-filter-date');

    if (statusFilter) {
      statusFilter.addEventListener('change', () => loadHistory());
    }
    if (dateFilter) {
      dateFilter.addEventListener('change', () => loadHistory());
    }
  }

  // ============================================================
  // LOAD STATS
  // ============================================================
  async function loadStats() {
    try {
      const res = await fetch(`${base}einloesungen/stats`);
      const data = await res.json();

      if (data.success && data.stats) {
        const s = data.stats;
        document.getElementById('stat-heute').textContent = s.heute;
        document.getElementById('stat-woche').textContent = s.woche;
        document.getElementById('stat-monat').textContent = s.monat;
        document.getElementById('stat-wert').textContent = formatCurrency(s.wert, s.currency);
        document.getElementById('pending-count').textContent = s.pending;

        // Show/hide pending badge
        const badge = document.getElementById('pending-count');
        if (badge) {
          badge.style.display = s.pending > 0 ? 'inline-flex' : 'none';
        }
      }
    } catch (err) {
      console.error('[PPV_REWARDS_V2] Stats error:', err);
    }
  }

  // ============================================================
  // LOAD PENDING
  // ============================================================
  async function loadPending() {
    const container = document.getElementById('ppv-ea-pending-list');
    if (!container) return;

    container.innerHTML = '<div class="ppv-ea-loading"><i class="ri-loader-4-line ri-spin"></i> ' + (L.rewards_loading || 'Lade...') + '</div>';

    try {
      const res = await fetch(`${base}einloesungen/list?status=pending`);
      const data = await res.json();

      if (data.success && data.items && data.items.length > 0) {
        container.innerHTML = '';
        data.items.forEach(item => {
          container.appendChild(createPendingCard(item));
        });
      } else {
        container.innerHTML = `
          <div class="ppv-ea-empty">
            <i class="ri-checkbox-circle-line"></i>
            <p>${L.rewards_no_pending || 'Keine ausstehenden Einlösungen'}</p>
          </div>
        `;
      }
    } catch (err) {
      console.error('[PPV_REWARDS_V2] Pending error:', err);
      container.innerHTML = '<div class="ppv-ea-error"><i class="ri-error-warning-line"></i> ' + (L.rewards_toast_error || 'Fehler beim Laden') + '</div>';
    }
  }

  // ============================================================
  // CREATE PENDING CARD
  // ============================================================
  function createPendingCard(item) {
    const card = document.createElement('div');
    card.className = 'ppv-ea-card ppv-ea-card-pending';
    card.dataset.id = item.id;

    const userName = formatUserName(item);
    const date = formatDate(item.redeemed_at);
    const points = item.points_spent || 0;
    const reward = item.reward_title || L.rewards_default_title || 'Belohnung';
    const pointsLabel = L.rewards_points || 'Punkte';

    card.innerHTML = `
      <div class="ppv-ea-card-main">
        <div class="ppv-ea-card-icon pending">
          <i class="ri-time-line"></i>
        </div>
        <div class="ppv-ea-card-info">
          <span class="ppv-ea-card-user">${escapeHtml(userName)}</span>
          <span class="ppv-ea-card-reward"><i class="ri-gift-line"></i> ${escapeHtml(reward)}</span>
          <span class="ppv-ea-card-meta">
            <i class="ri-calendar-line"></i> ${date}
            <span class="ppv-ea-card-points">-${points} ${pointsLabel}</span>
          </span>
        </div>
      </div>
      <div class="ppv-ea-card-actions">
        <button class="ppv-ea-btn-approve" data-id="${item.id}" title="${L.rewards_btn_approve || 'Bestätigen'}">
          <i class="ri-check-line"></i>
        </button>
        <button class="ppv-ea-btn-reject" data-id="${item.id}" title="${L.rewards_btn_reject || 'Ablehnen'}">
          <i class="ri-close-line"></i>
        </button>
      </div>
    `;

    // Event listeners
    card.querySelector('.ppv-ea-btn-approve').addEventListener('click', () => updateStatus(item.id, 'approved', card));
    card.querySelector('.ppv-ea-btn-reject').addEventListener('click', () => updateStatus(item.id, 'cancelled', card));

    return card;
  }

  // ============================================================
  // UPDATE STATUS
  // ============================================================
  async function updateStatus(id, status, card) {
    const btn = card.querySelector(status === 'approved' ? '.ppv-ea-btn-approve' : '.ppv-ea-btn-reject');
    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i>';

    try {
      const res = await fetch(`${base}einloesungen/update`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce
        },
        body: JSON.stringify({ id, status })
      });
      const data = await res.json();

      if (data.success) {
        // 📳 Haptic feedback on success
        if (window.ppvHaptic) window.ppvHaptic(status === 'approved' ? 'success' : 'warning');
        // Animate card removal
        card.classList.add('ppv-ea-card-fade-out');
        setTimeout(() => {
          card.remove();
          loadStats(); // Refresh stats

          // Check if list is empty
          const container = document.getElementById('ppv-ea-pending-list');
          if (container && container.children.length === 0) {
            container.innerHTML = `
              <div class="ppv-ea-empty">
                <i class="ri-checkbox-circle-line"></i>
                <p>${L.rewards_no_pending || 'Keine ausstehenden Einlösungen'}</p>
              </div>
            `;
          }
        }, 300);

        showToast(status === 'approved' ? (L.rewards_toast_approved || 'Bestätigt!') : (L.rewards_toast_rejected || 'Abgelehnt'), status === 'approved' ? 'success' : 'info');
      } else {
        // 📳 Haptic feedback on error
        if (window.ppvHaptic) window.ppvHaptic('error');
        showToast(data.message || L.rewards_toast_error || 'Fehler', 'error');
        btn.disabled = false;
        btn.innerHTML = status === 'approved' ? '<i class="ri-check-line"></i>' : '<i class="ri-close-line"></i>';
      }
    } catch (err) {
      console.error('[PPV_REWARDS_V2] Update error:', err);
      showToast(L.network_error || 'Netzwerkfehler', 'error');
      btn.disabled = false;
    }
  }

  // ============================================================
  // LOAD HISTORY
  // ============================================================
  async function loadHistory() {
    const container = document.getElementById('ppv-ea-history-list');
    if (!container) return;

    container.innerHTML = '<div class="ppv-ea-loading"><i class="ri-loader-4-line ri-spin"></i> ' + (L.rewards_loading || 'Lade...') + '</div>';

    const statusFilter = document.getElementById('ppv-ea-filter-status')?.value || 'all';
    const dateFilter = document.getElementById('ppv-ea-filter-date')?.value || '';

    let url = `${base}einloesungen/list?status=${statusFilter === 'all' ? 'history' : statusFilter}`;
    if (dateFilter) url += `&date=${dateFilter}`;

    try {
      const res = await fetch(url);
      const data = await res.json();

      if (data.success && data.items && data.items.length > 0) {
        container.innerHTML = '';
        data.items.forEach(item => {
          container.appendChild(createHistoryCard(item));
        });
      } else {
        container.innerHTML = `
          <div class="ppv-ea-empty">
            <i class="ri-history-line"></i>
            <p>${L.rewards_no_history || 'Keine Einträge gefunden'}</p>
          </div>
        `;
      }
    } catch (err) {
      console.error('[PPV_REWARDS_V2] History error:', err);
      container.innerHTML = '<div class="ppv-ea-error"><i class="ri-error-warning-line"></i> ' + (L.rewards_toast_error || 'Fehler beim Laden') + '</div>';
    }
  }

  // ============================================================
  // CREATE HISTORY CARD
  // ============================================================
  function createHistoryCard(item) {
    const card = document.createElement('div');
    card.className = `ppv-ea-card ppv-ea-card-${item.status}`;

    const userName = formatUserName(item);
    const date = formatDate(item.redeemed_at);
    const points = item.points_spent || 0;
    const reward = item.reward_title || L.rewards_default_title || 'Belohnung';
    const receiptNum = `#${new Date(item.redeemed_at).getFullYear()}-${String(item.id).padStart(4, '0')}`;
    const pointsLabel = L.rewards_points || 'Punkte';

    const statusIcon = item.status === 'approved' ? 'ri-checkbox-circle-fill' : 'ri-close-circle-fill';
    const statusClass = item.status === 'approved' ? 'approved' : 'cancelled';

    card.innerHTML = `
      <div class="ppv-ea-card-main">
        <div class="ppv-ea-card-icon ${statusClass}">
          <i class="${statusIcon}"></i>
        </div>
        <div class="ppv-ea-card-info">
          <span class="ppv-ea-card-user">${escapeHtml(userName)}</span>
          <span class="ppv-ea-card-reward"><i class="ri-gift-line"></i> ${escapeHtml(reward)} <span class="ppv-ea-card-points">-${points} ${pointsLabel}</span></span>
          <span class="ppv-ea-card-meta">
            <i class="ri-calendar-line"></i> ${date}
            <span class="ppv-ea-card-beleg">${receiptNum}</span>
          </span>
        </div>
      </div>
    `;

    return card;
  }

  // ============================================================
  // RECEIPTS
  // ============================================================
  function initReceiptGenerator() {
    const monthlyBtn = document.getElementById('ppv-ea-generate-receipt');
    if (monthlyBtn) {
      monthlyBtn.addEventListener('click', generateMonthlyReceipt);
    }

    const dateBtn = document.getElementById('ppv-ea-generate-date-receipt');
    if (dateBtn) {
      dateBtn.addEventListener('click', generateDateReceipt);
    }
  }

  async function generateMonthlyReceipt() {
    const btn = document.getElementById('ppv-ea-generate-receipt');
    const month = document.getElementById('ppv-ea-receipt-month')?.value;
    const year = document.getElementById('ppv-ea-receipt-year')?.value;

    if (!month || !year) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> ' + (L.rewards_btn_creating || 'Erstelle...');

    try {
      const res = await fetch(`${base}einloesungen/monthly-receipt`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce
        },
        body: JSON.stringify({ month: parseInt(month), year: parseInt(year) })
      });
      const data = await res.json();

      if (data.success && data.receipt_url) {
        showToast(L.rewards_toast_monthly_created || 'Monatsbericht erstellt!', 'success');
        loadReceipts(); // Refresh list
        // Direct download
        downloadPdf(data.receipt_url);
      } else {
        showToast(data.message || L.rewards_toast_no_data || 'Keine Einlösungen für diesen Zeitraum', 'error');
      }
    } catch (err) {
      console.error('[PPV_REWARDS_V2] Receipt error:', err);
      showToast(L.rewards_toast_error || 'Fehler beim Erstellen', 'error');
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="ri-file-add-line"></i> ' + (L.rewards_btn_create || 'Erstellen');
  }

  async function generateDateReceipt() {
    const btn = document.getElementById('ppv-ea-generate-date-receipt');
    const dateFrom = document.getElementById('ppv-ea-receipt-date-from')?.value;
    const dateTo = document.getElementById('ppv-ea-receipt-date-to')?.value;

    if (!dateFrom || !dateTo) return;

    // Validate date range
    if (dateFrom > dateTo) {
      showToast(L.rewards_toast_date_error || 'Startdatum muss vor Enddatum liegen', 'error');
      return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> ' + (L.rewards_btn_creating || 'Erstelle...');

    try {
      const res = await fetch(`${base}einloesungen/date-receipt`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce
        },
        body: JSON.stringify({ date_from: dateFrom, date_to: dateTo })
      });
      const data = await res.json();

      if (data.success && data.receipt_url) {
        showToast(data.message || L.rewards_toast_period_created || 'Zeitraumbericht erstellt!', 'success');
        loadReceipts(); // Refresh list
        // Direct download
        downloadPdf(data.receipt_url);
      } else {
        showToast(data.message || L.rewards_toast_no_data || 'Keine Einlösungen für diesen Zeitraum', 'error');
      }
    } catch (err) {
      console.error('[PPV_REWARDS_V2] Date receipt error:', err);
      showToast(L.rewards_toast_error || 'Fehler beim Erstellen', 'error');
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="ri-file-add-line"></i> ' + (L.rewards_btn_create || 'Erstellen');
  }

  async function loadReceipts() {
    const container = document.getElementById('ppv-ea-receipts-list');
    if (!container) return;

    container.innerHTML = '<div class="ppv-ea-loading"><i class="ri-loader-4-line ri-spin"></i> ' + (L.rewards_loading_receipts || 'Lade Belege...') + '</div>';

    try {
      const res = await fetch(`${base}einloesungen/receipts`);
      const data = await res.json();

      if (data.success && data.items && data.items.length > 0) {
        container.innerHTML = '';
        data.items.forEach(item => {
          container.appendChild(createReceiptCard(item, data.base_url));
        });
      } else {
        container.innerHTML = `
          <div class="ppv-ea-empty">
            <i class="ri-file-list-3-line"></i>
            <p>${L.rewards_no_receipts || 'Keine Belege vorhanden'}</p>
          </div>
        `;
      }
    } catch (err) {
      console.error('[PPV_REWARDS_V2] Receipts error:', err);
      container.innerHTML = '<div class="ppv-ea-error"><i class="ri-error-warning-line"></i> ' + (L.rewards_toast_error || 'Fehler beim Laden') + '</div>';
    }
  }

  function createReceiptCard(item, baseUrl) {
    const card = document.createElement('div');
    card.className = 'ppv-ea-receipt-card';

    // Format: Inv-YYYY-XXXX (e.g. Inv-2025-0109)
    const year = item.redeemed_at ? new Date(item.redeemed_at).getFullYear() : new Date().getFullYear();
    const invoiceNum = `Inv-${year}-${String(item.id).padStart(4, '0')}`;
    const date = formatDate(item.redeemed_at);
    const reward = item.reward_title || L.rewards_default_title || 'Belohnung';
    const receiptUrl = item.receipt_pdf_path ? `${baseUrl}/${item.receipt_pdf_path}` : null;

    card.innerHTML = `
      <div class="ppv-ea-receipt-info">
        <span class="ppv-ea-receipt-user">${escapeHtml(invoiceNum)}</span>
        <span class="ppv-ea-receipt-reward">${escapeHtml(reward)}</span>
        <span class="ppv-ea-receipt-date">${date}</span>
      </div>
      ${receiptUrl ? `
        <a href="${receiptUrl}" download class="ppv-ea-receipt-download" title="${L.rewards_receipt_download || 'Herunterladen'}">
          <i class="ri-download-line"></i>
        </a>
      ` : `
        <button class="ppv-ea-receipt-generate" data-id="${item.id}" title="${L.rewards_btn_create || 'Beleg erstellen'}">
          <i class="ri-file-add-line"></i>
        </button>
      `}
    `;

    // Add click handler for generate button
    const generateBtn = card.querySelector('.ppv-ea-receipt-generate');
    if (generateBtn) {
      generateBtn.addEventListener('click', () => generateSingleReceipt(item.id, baseUrl));
    }

    return card;
  }

  // Generate single receipt
  async function generateSingleReceipt(redeemId, baseUrl) {
    const base = config.base || '/wp-json/ppv/v1/';
    try {
      const res = await fetch(`${base}einloesungen/generate-receipt`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce
        },
        body: JSON.stringify({ redeem_id: redeemId })
      });
      const data = await res.json();
      if (data.success && data.receipt_url) {
        showNotification(L.rewards_toast_approved || 'Beleg erstellt!', 'PDF');
        loadReceipts(); // Refresh list
      } else {
        showNotification(L.rewards_toast_error || 'Fehler', data.message || L.rewards_toast_error || 'Beleg konnte nicht erstellt werden');
      }
    } catch (err) {
      console.error('[PPV_REWARDS_V2] Generate receipt error:', err);
      showNotification(L.rewards_toast_error || 'Fehler', L.network_error || 'Netzwerkfehler');
    }
  }

  // ============================================================
  // REAL-TIME (ABLY)
  // ============================================================
  function initRealtime() {
    if (config.ably && config.ably.key && typeof Ably !== 'undefined') {

      const ably = new Ably.Realtime({ key: config.ably.key });
      const channel = ably.channels.get(config.ably.channel);

      ably.connection.on('connected', () => {
        if (pollInterval) {
          clearInterval(pollInterval);
          pollInterval = null;
        }
      });

      ably.connection.on('disconnected', () => {
        startPolling();
      });

      channel.subscribe('reward-request', (message) => {
        showNotification(L.rewards_new_redemption || 'Neue Einlösung!', message.data.reward_title || L.rewards_default_title || 'Belohnung');
        loadStats();
        if (currentTab === 'pending') loadPending();
      });

    } else {
      startPolling();
    }
  }

  function startPolling() {
    if (pollInterval) return;
    pollInterval = setInterval(() => {
      loadStats();
      if (currentTab === 'pending') loadPending();
    }, 30000);
  }

  // ============================================================
  // HELPERS
  // ============================================================
  function formatUserName(item) {
    if (item.first_name || item.last_name) {
      return `${item.first_name || ''} ${item.last_name || ''}`.trim();
    }
    return item.user_email || 'Unbekannt';
  }

  function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  function formatCurrency(amount, currency = 'EUR') {
    const symbols = { EUR: '€', HUF: 'Ft', RON: 'Lei' };
    const symbol = symbols[currency] || currency;
    return `${symbol}${Number(amount).toLocaleString('de-DE', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
  }

  function downloadPdf(url) {
    // Create hidden link and trigger download
    const link = document.createElement('a');
    link.href = url;
    link.download = url.split('/').pop();
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `ppv-ea-toast ppv-ea-toast-${type}`;
    toast.innerHTML = `<i class="ri-${type === 'success' ? 'checkbox-circle' : type === 'error' ? 'error-warning' : 'information'}-line"></i> ${escapeHtml(message)}`;
    document.body.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 50);
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  function showNotification(title, body) {
    // Play sound
    try {
      const audio = new Audio('https://cdn.pixabay.com/download/audio/2022/03/15/audio_dba733ce07.mp3');
      audio.volume = 0.5;
      audio.play().catch(() => {});
    } catch (e) {}

    // Show toast
    showToast(`${title}: ${body}`, 'info');

    // Browser notification (if permitted)
    if ('Notification' in window && Notification.permission === 'granted') {
      new Notification(title, { body, icon: config.plugin_url + 'assets/img/icon-192.png' });
    }
  }

  // ============================================================
  // INITIALIZE
  // ============================================================
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Turbo support (only turbo:load, not render to avoid double-init)
  document.addEventListener('turbo:load', () => {
    const container = document.querySelector('.ppv-einloesungen-admin');
    if (container) container.dataset.initialized = 'false';
    init();
  });

})();
