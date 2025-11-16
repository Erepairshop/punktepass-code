/**
 * PunktePass – Belohnungen (v2.2 MODERN + BEAUTIFUL + TRANSLATED)
 * ✅ Modern UI, gorgeous buttons, full translations
 * ✅ Smooth animations, responsive design
 * ✅ Progress bars, status indicators
 * Version: 2.2 REDESIGN
 */

(function ($) {
  'use strict';
  
  console.log("✅ [PPV_BELOHNUNGEN] JS v2.2 REDESIGN loaded");

  /* ==========================================================
   * 🔹 CONFIG & LANG
   * ========================================================== */
  if (typeof window.ppv_belohnungen === 'undefined') {
    console.error('❌ [PPV_BELOHNUNGEN] Config not found!');
    return;
  }

  const config = window.ppv_belohnungen;
  const DEBUG = config.debug || false;
  const LANG = config.lang || 'de';

  // ✅ TELJES FORDÍTÁSOK (DE, HU, RO)
  const labels = {
    'de': {
            'no_results': 'Keine Ergebnisse gefunden',

      'belohnungen_title': 'Meine Belohnungen',
      'belohnungen_subtitle': 'Wähle eine Belohnung aus und löse sie ein',
      'my_points': 'Meine Punkte',
      'search_rewards': '🔍 Belohnungen durchsuchen...',
      'filter_by_store': 'Nach Geschäft filtern',
      'all_stores': 'Alle Geschäfte',
      'filter_reset': '↻ Zurücksetzen',
      'redeem_button': '✅ Bevältsen',
      'redeeming_status': '⏳ Wird eingelöst...',
      'redeem_success': '🎁 {title}: Anfrage gesendet!',
      'redeem_error': '❌ Fehler beim Einlösen',
      'network_error': '❌ Netzwerkfehler.',
      'invalid_data': '⚠️ Ungültige Daten',
      'reward_approved': '✅ {title}: Belohnung bestätigt!',
      'reward_cancelled': '❌ {title}: Anfrage abgelehnt.',
      'points_missing': 'Punkte fehlen',
      'points': 'Punkte',
      'general': 'Allgemein',
      'pending_section': 'Meine Anfragen',
      'status_pending': '⏳ Wird bearbeitet',
      'status_approved': '✅ Bestätigt',
      'status_cancelled': '❌ Abgelehnt',
      'no_rewards': 'Keine Belohnungen vorhanden',
      'no_login': 'Bitte melde dich an',
    },
    'hu': {
            'no_results': 'Nincs találat',

      'belohnungen_title': 'Saját Jutalmak',
      'belohnungen_subtitle': 'Válassz egy jutalmat és váltsd be',
      'my_points': 'Saját Pontjaim',
      'search_rewards': '🔍 Jutalmak keresése...',
      'filter_by_store': 'Szűrés bolt szerint',
      'all_stores': 'Összes bolt',
      'filter_reset': '↻ Alaphelyzetbe',
      'redeem_button': '✅ Beváltás',
      'redeeming_status': '⏳ Beváltás folyamatban...',
      'redeem_success': '🎁 {title}: Kérelem elküldve!',
      'redeem_error': '❌ Hiba a beváltáskor',
      'network_error': '❌ Hálózati hiba.',
      'invalid_data': '⚠️ Érvénytelen adatok',
      'reward_approved': '✅ {title}: Megerősítve!',
      'reward_cancelled': '❌ {title}: Elutasítva.',
      'points_missing': 'pont hiányzik',
      'points': 'Pont',
      'general': 'Általános',
      'pending_section': 'Saját Kérelmek',
      'status_pending': '⏳ Feldolgozás alatt',
      'status_approved': '✅ Megerősítve',
      'status_cancelled': '❌ Elutasítva',
      'no_rewards': 'Nincsenek jutalmazások',
      'no_login': 'Kérjük, hogy jelentkezz be',
    },
    'ro': {
            'no_results': 'Nu s-au găsit rezultate',

      'belohnungen_title': 'Premiile Mele',
      'belohnungen_subtitle': 'Alege o recompensă și încasează-o',
      'my_points': 'Punctele Mele',
      'search_rewards': '🔍 Caută recompense...',
      'filter_by_store': 'Filtrează după magazin',
      'all_stores': 'Toate magazinele',
      'filter_reset': '↻ Resetează',
      'redeem_button': '✅ Încasare',
      'redeeming_status': '⏳ Se schimbă...',
      'redeem_success': '🎁 {title}: Cerere trimisă!',
      'redeem_error': '❌ Eroare la schimb',
      'network_error': '❌ Eroare de rețea.',
      'invalid_data': '⚠️ Date invalide',
      'reward_approved': '✅ {title}: Confirmat!',
      'reward_cancelled': '❌ {title}: Respins.',
      'points_missing': 'puncte lipsesc',
      'points': 'Puncte',
      'general': 'General',
      'pending_section': 'Cererile Mele',
      'status_pending': '⏳ În curs de procesare',
      'status_approved': '✅ Confirmat',
      'status_cancelled': '❌ Respins',
      'no_rewards': 'Nu sunt recompense disponibile',
      'no_login': 'Te rog să te conectezi',
    }
  };

  function getLabel(key) {
    return (labels[LANG] || labels['de'])[key] || key;
  }

  /* ==========================================================
   * 🛡️ SECURITY
   * ========================================================== */
  
  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
  }

  function log(level, msg, data) {
    const prefix = `[PPV_BELOHNUNGEN] ${level}`;
    if (level === 'DEBUG' && !DEBUG) return;
    if (data) {
      console.log(prefix, msg, data);
    } else {
      console.log(prefix, msg);
    }
  }

  /* ==========================================================
   * 🎨 MODERN POPUP
   * ========================================================== */
  
  const popupStyles = `
    .ppv-reward-popup {
      position: fixed !important;
      top: 0; left: 0;
      width: 100vw; height: 100vh;
      background: rgba(0, 0, 0, 0.6) !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      z-index: 999999 !important;
      animation: ppvPopupFadeIn 0.3s ease-out;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      backdrop-filter: blur(4px);
    }
    
    .ppv-reward-popup-inner {
      background: white;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
      padding: 2rem 2.5rem;
      text-align: center;
      max-width: 450px;
      width: 90%;
      animation: ppvPopupSlideIn 0.3s ease-out;
    }

    @media (prefers-color-scheme: dark) {
      .ppv-reward-popup-inner {
        background: #1f2937;
        color: #f3f4f6;
      }
    }
    
    .ppv-reward-popup-inner p {
      color: inherit;
      font-size: 1.1rem;
      margin: 0 0 1.5rem 0;
      line-height: 1.6;
      font-weight: 500;
    }
    
    .ppv-reward-popup-inner .popup-button {
      background: linear-gradient(135deg, #6366f1, #4f46e5);
      color: #fff;
      border: none;
      padding: 0.85rem 2rem;
      border-radius: 10px;
      cursor: pointer;
      font-size: 1rem;
      font-weight: 600;
      transition: all 0.3s;
      font-family: inherit;
    }
    
    .ppv-reward-popup-inner .popup-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
    }
    
    .ppv-reward-popup-inner .popup-button:active {
      transform: scale(0.98);
    }
    
    .popup-success { color: #10b981 !important; }
    .popup-error { color: #ef4444 !important; }
    .popup-warning { color: #f59e0b !important; }
    
    @keyframes ppvPopupFadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @keyframes ppvPopupSlideIn {
      from {
        opacity: 0;
        transform: scale(0.9);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }
    
    @media (max-width: 480px) {
      .ppv-reward-popup-inner {
        padding: 1.5rem 1.25rem;
      }
      .ppv-reward-popup-inner p {
        font-size: 1rem;
      }
    }
  `;
  
  const $style = $('<style>').text(popupStyles);
  $('head').append($style);

  function showPopup(message, type = 'info') {
    if (!message) return;

    const safeMessage = escapeHtml(message);
    const typeClass = {
      'success': 'popup-success',
      'error': 'popup-error',
      'warning': 'popup-warning',
      'info': ''
    }[type] || '';

    const emoji = {
      'success': '✅',
      'error': '❌',
      'warning': '⚠️',
      'info': 'ℹ️'
    }[type] || '';

    const $popup = $(`
      <div class="ppv-reward-popup">
        <div class="ppv-reward-popup-inner">
          <p class="${typeClass}">${emoji} ${safeMessage}</p>
          <button class="popup-button">${getLabel('ok', 'OK')}</button>
        </div>
      </div>
    `);

    $('body').append($popup);

    $popup.find('.popup-button').on('click', function() {
      $popup.fadeOut(150, function() { 
        $(this).remove(); 
      });
    });

    $popup.on('click', function(e) {
      if (e.target === this) {
        $(this).fadeOut(150, function() { 
          $(this).remove(); 
        });
      }
    });

    setTimeout(() => {
      if ($popup.parent().length) {
        $popup.fadeOut(150, function() { 
          $(this).remove(); 
        });
      }
    }, 4000);
  }

  /* ==========================================================
   * 🔍 SEARCH & FILTER
   * ========================================================== */
  
  function initSearchFilter() {
    const $container = $('#ppv-search-filter-container');
    if ($container.length === 0) {
      return;
    }

    const stores = window.ppv_available_stores || [];
    
    let storeOptions = `<option value="">${getLabel('all_stores')}</option>`;
    stores.forEach(store => {
      storeOptions += `<option value="${parseInt(store.id)}">${escapeHtml(store.name)}</option>`;
    });

    const html = `
      <div class="ppv-search-filter">
        <div class="ppv-search-box">
          <i class="ri-search-line"></i>
          <input type="text" id="ppv-search-input" placeholder="${getLabel('search_rewards')}" />
        </div>
        <div class="ppv-filter-box">
          <select id="ppv-store-filter">
            ${storeOptions}
          </select>
          <button id="ppv-filter-reset" class="ppv-filter-reset-btn">
            <i class="ri-refresh-line"></i> ${getLabel('filter_reset')}
          </button>
        </div>
      </div>
    `;

    $container.html(html);

    $('#ppv-search-input').on('keyup', function() {
      const query = $(this).val().toLowerCase();
      filterRewards(query, $('#ppv-store-filter').val());
    });

    $('#ppv-store-filter').on('change', function() {
      const query = $('#ppv-search-input').val().toLowerCase();
      filterRewards(query, $(this).val());
    });

    $('#ppv-filter-reset').on('click', function() {
      $('#ppv-search-input').val('');
      $('#ppv-store-filter').val('');
      filterRewards('', '');
    });
  }

  function filterRewards(query, storeId) {
    const $cards = $('.ppv-reward-card');
    let visibleCount = 0;

    $cards.each(function() {
      const $card = $(this);
      const title = $card.find('h4').text().toLowerCase();
      const desc = $card.find('p').text().toLowerCase();
      const cardStore = $card.data('store') || '';

      const matchesQuery = !query || title.includes(query) || desc.includes(query);
      const matchesStore = !storeId || String(cardStore) === String(storeId);

      if (matchesQuery && matchesStore) {
        $card.fadeIn(200);
        visibleCount++;
      } else {
        $card.fadeOut(200);
      }
    });

    if (visibleCount === 0) {
      if ($('.ppv-no-results').length === 0) {
$('.ppv-reward-grid').after(`<p class="ppv-no-results"><i class="ri-inbox-line"></i> ${getLabel('no_results')}</p>`);
      }
      $('.ppv-no-results').fadeIn(200);
    } else {
      $('.ppv-no-results').fadeOut(200);
    }
  }

  /* ==========================================================
   * 🎁 REDEEM REQUEST
   * ========================================================== */
  
  $(document).on('click', '.ppv-redeem-btn:not(.disabled)', async function(e) {
    e.preventDefault();
    
    const $btn = $(this);
    
    if ($btn.data('processing')) {
      log('WARN', 'Request already in progress');
      return;
    }

    const reward_id = parseInt($btn.data('id') || 0);
    const user_id = parseInt($btn.data('user') || 0);
    const reward_title = String($btn.data('title') || 'Reward');

    if (!reward_id || !user_id) {
      log('ERROR', 'Missing data', { reward_id, user_id });
      showPopup(getLabel('invalid_data'), 'error');
      return;
    }

    $btn.data('processing', true);
    const originalText = $btn.html();
    $btn.prop('disabled', true).html(`<i class="ri-loader-4-line ri-spin"></i> ${getLabel('redeeming_status')}`);

    log('INFO', 'Redeem started', { reward_id, user_id });

    try {
      if (!config.base_url || !config.nonce) {
        throw new Error('Config invalid');
      }

      const response = await fetch(config.base_url + 'rewards/redeem', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        body: JSON.stringify({
          reward_id: reward_id,
          user_id: user_id
        }),
        credentials: 'include'
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      log('INFO', 'Response', data);

      if (data.success) {
        const msg = getLabel('redeem_success').replace('{title}', reward_title);
        showPopup(msg, 'success');
        
        $btn.fadeOut(300, function() { 
          $(this).closest('.ppv-reward-card').fadeOut(300);
        });
        
        window.ppvLastRedeemId = data.redeem_id || null;
        window.ppvStatusPollCount = 0;
      } else {
        const errorMsg = data.message || getLabel('redeem_error');
        showPopup(escapeHtml(errorMsg), 'error');
        $btn.data('processing', false);
        $btn.prop('disabled', false).html(originalText);
      }

    } catch (err) {
      log('ERROR', 'Redeem error', err.message);
      showPopup(getLabel('network_error'), 'error');
      $btn.data('processing', false);
      $btn.prop('disabled', false).html(originalText);
    }
  });

  /* ==========================================================
   * 🟢 STATUS POLLING
   * ========================================================== */
  
  let pollCount = 0;
  const MAX_POLLS = 120;
  let lastStatuses = {};

  async function checkRewardStatus() {
    const currentUser = $('button.ppv-redeem-btn').first().data('user');
    
    if (!currentUser) {
      log('DEBUG', 'No user found');
      return;
    }

    try {
      const response = await fetch(
        config.base_url + 'rewards/status?user_id=' + currentUser + '&_=' + Date.now(),
        {
          headers: {
            'X-WP-Nonce': config.nonce,
          },
          credentials: 'include',
          cache: 'no-store'
        }
      );

      if (!response.ok) {
        log('WARN', `Status check failed: HTTP ${response.status}`);
        return;
      }

      const json = await response.json();
      
      if (!json.success || !Array.isArray(json.items)) {
        log('WARN', 'Invalid response');
        return;
      }

      log('DEBUG', `Status: ${json.count || 0} items`);

      if (Object.keys(lastStatuses).length === 0) {
        json.items.forEach(item => {
          const key = `${item.id}_${item.reward_id || 0}`;
          lastStatuses[key] = String(item.status).toLowerCase();
        });
        log('INFO', 'Status cache initialized');
        return;
      }

      json.items.forEach(item => {
        const key = `${item.id}_${item.reward_id || 0}`;
        const currentStatus = String(item.status).toLowerCase();
        const previousStatus = lastStatuses[key];

        if (previousStatus === currentStatus) {
          return;
        }

        lastStatuses[key] = currentStatus;
        const title = escapeHtml(item.title || 'Reward');

        log('INFO', `Status changed: ${title} → ${currentStatus}`);

        if (currentStatus === 'approved' || currentStatus === 'bestätigt') {
          const msg = getLabel('reward_approved').replace('{title}', title);
          showPopup(msg, 'success');
          pollCount = MAX_POLLS;
          
        } else if (currentStatus === 'cancelled' || currentStatus === 'abgelehnt') {
          const msg = getLabel('reward_cancelled').replace('{title}', title);
          showPopup(msg, 'error');
          pollCount = MAX_POLLS;
        }
      });

    } catch (err) {
      log('ERROR', 'Status check error', err.message);
    }
  }

  function startStatusPolling() {
    if (pollCount >= MAX_POLLS) {
      log('INFO', 'Polling stopped');
      return;
    }

    checkRewardStatus();
    pollCount++;

    let nextInterval = 5000;
    if (pollCount > 60) {
      nextInterval = 30000;
    } else if (pollCount > 30) {
      nextInterval = 15000;
    } else if (pollCount > 10) {
      nextInterval = 10000;
    }

    log('DEBUG', `Next poll in ${nextInterval / 1000}s`);
    setTimeout(() => startStatusPolling(), nextInterval);
  }

  /* ==========================================================
   * 🚀 INIT
   * ========================================================== */
  
  $(document).ready(function() {
    log('INFO', 'Document ready');

    // Init search/filter
    initSearchFilter();

    // Start polling
    const $redeemButtons = $('button.ppv-redeem-btn');
    const hasUser = $redeemButtons.first().data('user') > 0;

    if (hasUser) {
      log('INFO', 'Starting status polling');
      startStatusPolling();
    }

    log('INFO', 'Initialization complete');
  });

})(jQuery);