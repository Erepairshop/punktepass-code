/**
 * PunktePass – Einlösungen Admin Dashboard v2.0
 * Modern dashboard with stats, pending list, history, receipts
 */

(function() {
  'use strict';

  console.log('[PPV_REWARDS_V2] Loading...');

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
      console.log('[PPV_REWARDS_V2] Already initialized');
      return;
    }
    container.dataset.initialized = 'true';

    console.log('[PPV_REWARDS_V2] Initializing...', { storeId, base });

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

    console.log('[PPV_REWARDS_V2] Ready');
  }

  // ============================================================
  // TABS
  // ============================================================
  function initTabs() {
    const tabs = document.querySelectorAll('.ppv-ea-tab');
    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
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

    container.innerHTML = '<div class="ppv-ea-loading"><i class="ri-loader-4-line ri-spin"></i> Lade...</div>';

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
            <p>Keine ausstehenden Einlösungen</p>
          </div>
        `;
      }
    } catch (err) {
      console.error('[PPV_REWARDS_V2] Pending error:', err);
      container.innerHTML = '<div class="ppv-ea-error"><i class="ri-error-warning-line"></i> Fehler beim Laden</div>';
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
    const reward = item.reward_title || 'Belohnung';

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
            <span class="ppv-ea-card-points">-${points} Punkte</span>
          </span>
        </div>
      </div>
      <div class="ppv-ea-card-actions">
        <button class="ppv-ea-btn-approve" data-id="${item.id}" title="Bestätigen">
          <i class="ri-check-line"></i>
        </button>
        <button class="ppv-ea-btn-reject" data-id="${item.id}" title="Ablehnen">
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
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status })
      });
      const data = await res.json();

      if (data.success) {
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
                <p>Keine ausstehenden Einlösungen</p>
              </div>
            `;
          }
        }, 300);

        showToast(status === 'approved' ? 'Bestätigt!' : 'Abgelehnt', status === 'approved' ? 'success' : 'info');
      } else {
        showToast(data.message || 'Fehler', 'error');
        btn.disabled = false;
        btn.innerHTML = status === 'approved' ? '<i class="ri-check-line"></i>' : '<i class="ri-close-line"></i>';
      }
    } catch (err) {
      console.error('[PPV_REWARDS_V2] Update error:', err);
      showToast('Netzwerkfehler', 'error');
      btn.disabled = false;
    }
  }

  // ============================================================
  // LOAD HISTORY
  // ============================================================
  async function loadHistory() {
    const container = document.getElementById('ppv-ea-history-list');
    if (!container) return;

    container.innerHTML = '<div class="ppv-ea-loading"><i class="ri-loader-4-line ri-spin"></i> Lade...</div>';

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
            <p>Keine Einträge gefunden</p>
          </div>
        `;
      }
    } catch (err) {
      console.error('[PPV_REWARDS_V2] History error:', err);
      container.innerHTML = '<div class="ppv-ea-error"><i class="ri-error-warning-line"></i> Fehler beim Laden</div>';
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
    const reward = item.reward_title || 'Belohnung';
    const receiptNum = `#${new Date(item.redeemed_at).getFullYear()}-${String(item.id).padStart(4, '0')}`;

    const statusIcon = item.status === 'approved' ? 'ri-checkbox-circle-fill' : 'ri-close-circle-fill';
    const statusClass = item.status === 'approved' ? 'approved' : 'cancelled';

    card.innerHTML = `
      <div class="ppv-ea-card-main">
        <div class="ppv-ea-card-icon ${statusClass}">
          <i class="${statusIcon}"></i>
        </div>
        <div class="ppv-ea-card-info">
          <span class="ppv-ea-card-user">${escapeHtml(userName)}</span>
          <span class="ppv-ea-card-reward"><i class="ri-gift-line"></i> ${escapeHtml(reward)} <span class="ppv-ea-card-points">-${points} Punkte</span></span>
          <span class="ppv-ea-card-meta">
            <i class="ri-calendar-line"></i> ${date}
            <span class="ppv-ea-card-beleg">Beleg: ${receiptNum}</span>
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
    const btn = document.getElementById('ppv-ea-generate-receipt');
    if (btn) {
      btn.addEventListener('click', generateMonthlyReceipt);
    }
  }

  async function generateMonthlyReceipt() {
    const btn = document.getElementById('ppv-ea-generate-receipt');
    const month = document.getElementById('ppv-ea-receipt-month')?.value;
    const year = document.getElementById('ppv-ea-receipt-year')?.value;

    if (!month || !year) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Erstelle...';

    try {
      const res = await fetch(`${base}einloesungen/monthly-receipt`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ month: parseInt(month), year: parseInt(year) })
      });
      const data = await res.json();

      if (data.success && data.receipt_url) {
        showToast('Monatsbericht erstellt!', 'success');
        window.open(data.receipt_url, '_blank');
        loadReceipts(); // Refresh list
      } else {
        showToast(data.message || 'Keine Einlösungen für diesen Zeitraum', 'error');
      }
    } catch (err) {
      console.error('[PPV_REWARDS_V2] Receipt error:', err);
      showToast('Fehler beim Erstellen', 'error');
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="ri-file-add-line"></i> Erstellen';
  }

  async function loadReceipts() {
    const container = document.getElementById('ppv-ea-receipts-list');
    if (!container) return;

    container.innerHTML = '<div class="ppv-ea-loading"><i class="ri-loader-4-line ri-spin"></i> Lade Belege...</div>';

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
            <p>Keine Belege vorhanden</p>
          </div>
        `;
      }
    } catch (err) {
      console.error('[PPV_REWARDS_V2] Receipts error:', err);
      container.innerHTML = '<div class="ppv-ea-error"><i class="ri-error-warning-line"></i> Fehler beim Laden</div>';
    }
  }

  function createReceiptCard(item, baseUrl) {
    const card = document.createElement('div');
    card.className = 'ppv-ea-receipt-card';

    const userName = formatUserName(item);
    const date = formatDate(item.redeemed_at);
    const reward = item.reward_title || 'Belohnung';
    const receiptUrl = item.receipt_pdf_path ? `${baseUrl}/${item.receipt_pdf_path}` : null;

    card.innerHTML = `
      <div class="ppv-ea-receipt-info">
        <span class="ppv-ea-receipt-user">${escapeHtml(userName)}</span>
        <span class="ppv-ea-receipt-reward">${escapeHtml(reward)}</span>
        <span class="ppv-ea-receipt-date">${date}</span>
      </div>
      ${receiptUrl ? `
        <a href="${receiptUrl}" target="_blank" class="ppv-ea-receipt-download">
          <i class="ri-download-line"></i>
        </a>
      ` : ''}
    `;

    return card;
  }

  // ============================================================
  // REAL-TIME (ABLY)
  // ============================================================
  function initRealtime() {
    if (config.ably && config.ably.key && typeof Ably !== 'undefined') {
      console.log('[PPV_REWARDS_V2] Initializing Ably...');

      const ably = new Ably.Realtime({ key: config.ably.key });
      const channel = ably.channels.get(config.ably.channel);

      ably.connection.on('connected', () => {
        console.log('[PPV_REWARDS_V2] Ably connected');
        if (pollInterval) {
          clearInterval(pollInterval);
          pollInterval = null;
        }
      });

      ably.connection.on('disconnected', () => {
        console.log('[PPV_REWARDS_V2] Ably disconnected');
        startPolling();
      });

      channel.subscribe('reward-request', (message) => {
        console.log('[PPV_REWARDS_V2] New reward request:', message.data);
        showNotification('Neue Einlösung!', message.data.reward_title || 'Belohnung');
        loadStats();
        if (currentTab === 'pending') loadPending();
      });

    } else {
      console.log('[PPV_REWARDS_V2] Ably not available, using polling');
      startPolling();
    }
  }

  function startPolling() {
    if (pollInterval) return;
    console.log('[PPV_REWARDS_V2] Starting polling (30s)');
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

  // Turbo support
  document.addEventListener('turbo:load', () => {
    const container = document.querySelector('.ppv-einloesungen-admin');
    if (container) container.dataset.initialized = 'false';
    init();
  });

  document.addEventListener('turbo:render', () => {
    const container = document.querySelector('.ppv-einloesungen-admin');
    if (container) container.dataset.initialized = 'false';
    init();
  });

})();
