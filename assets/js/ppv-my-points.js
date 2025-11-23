/**
 * PunktePass – My Points (Production v2.0)
 * ✅ String translations from PHP (window.ppv_lang)
 * ✅ getLabels() function
 * ✅ Offline fallback
 * ✅ Auto-translate on language change
 */

(() => {
  const DEBUG = false; // ✅ Disabled in production to reduce console spam
  let isOnline = navigator.onLine;

  // ✅ Production-safe logging - only logs when DEBUG is true
  const log = (...args) => { if (DEBUG) console.log(...args); };
  const warn = (...args) => { if (DEBUG) console.warn(...args); };
  const error = console.error; // Always show errors

  log('🟢 [PPV_MYPOINTS] Production script loaded');

  /** ============================
   * 🌍 DEFAULT FALLBACK STRINGS (Offline)
   * ============================ */
  const DEFAULT_STRINGS = {
    de: {
      title: "Meine Punkte",
      total: "Gesamtpunkte",
      motivation: "Sammle weiter Punkte und erhalte tolle Belohnungen!",
      avg: "Durchschnitt",
      best_day: "Bester Tag",
      top_store: "Top Store",
      next_reward: "Nächste Belohnung",
      remaining: "verbleibend",
      reward_reached: "🎉 Prämie erreicht!",
      top3: "Top 3 Filialen",
      recent: "Kürzliche Aktivität",
      offline_mode: "Offline-Modus",
      no_data: "Keine Daten verfügbar",
      no_entries: "Keine Einträge",
      error: "Fehler",
      error_offline: "Offline - Bitte verbinden Sie sich mit dem Internet",
      error_unauthorized: "Nicht autorisiert",
      error_forbidden: "Zugriff verweigert",
      error_api_not_found: "API nicht gefunden",
      error_loading: "Fehler beim Laden der Daten",
      error_try_again: "Bitte versuchen Sie es später erneut",
      points_label: "Punkte",
      date_label: "Datum",
      store_label: "Geschäft",
      time_label: "Zeit",
      score_label: "Punktzahl",
    },
    hu: {
      title: "Pontjaim",
      total: "Összes pont",
      motivation: "Gyűjts pontokat és szerezz csodálatos jutalmakat!",
      avg: "Átlag",
      best_day: "Legjobb nap",
      top_store: "Legjobb bolt",
      next_reward: "Következő jutalom",
      remaining: "hátralévő",
      reward_reached: "🎉 Jutalom elért!",
      top3: "Top 3 üzlet",
      recent: "Legutóbbi tevékenység",
      offline_mode: "Offline mód",
      no_data: "Nincs adat",
      no_entries: "Nincs bejegyzés",
      error: "Hiba",
      error_offline: "Offline - Kérem kapcsolódjon az internethez",
      error_unauthorized: "Nem engedélyezett",
      error_forbidden: "Hozzáférés megtagadva",
      error_api_not_found: "API nem található",
      error_loading: "Hiba az adatok betöltésekor",
      error_try_again: "Kérem próbálja újra később",
      points_label: "Pontok",
      date_label: "Dátum",
      store_label: "Üzlet",
      time_label: "Idő",
      score_label: "Pontszám",
    },
    ro: {
      title: "Punctele mele",
      total: "Puncte totale",
      motivation: "Colectează puncte și câștigă recompense minunate!",
      avg: "Medie",
      best_day: "Ziua cea mai bună",
      top_store: "Magazin top",
      next_reward: "Următoarea recompensă",
      remaining: "rămas",
      reward_reached: "🎉 Recompensă atinsă!",
      top3: "Top 3 magazine",
      recent: "Activitate recentă",
      offline_mode: "Mod offline",
      no_data: "Fără date",
      no_entries: "Fără intrări",
      error: "Eroare",
      error_offline: "Offline - Vă rugăm să vă conectați la internet",
      error_unauthorized: "Neautorizat",
      error_forbidden: "Acces refuzat",
      error_api_not_found: "API nu a fost găsit",
      error_loading: "Eroare la încărcarea datelor",
      error_try_again: "Vă rugăm încercați din nou mai târziu",
      points_label: "Puncte",
      date_label: "Dată",
      store_label: "Magazin",
      time_label: "Ora",
      score_label: "Scor",
    }
  };

  /** ============================
   * 🌍 GET LABELS (Server strings + Fallback)
   * ============================ */
  function getLabels(lang = 'de') {
    // Get server strings (from PHP)
    const serverLabels = window.ppv_lang || {};
    
    // Get fallback
    const defaults = DEFAULT_STRINGS[lang] || DEFAULT_STRINGS.de;
    
    // Merge: server > fallback
    const merged = Object.assign({}, defaults, serverLabels);
    
    log(`🌍 [getLabels] lang=${lang}, strings=${Object.keys(merged).length}`);
    return merged;
  }

  /** ============================
   * ⚙️ INIT
   * ============================ */
  document.body.classList.add("ppv-app-mode", "ppv-my-points");

  window.addEventListener("online", () => {
    isOnline = true;
    log('🟢 [PPV_MYPOINTS] Back online!');
    document.body.classList.remove("ppv-offline-mode");
  });

  window.addEventListener("offline", () => {
    isOnline = false;
    log('🔴 [PPV_MYPOINTS] Offline mode');
    document.body.classList.add("ppv-offline-mode");
  });

  // 🚀 Turbo handles transitions now - removed beforeunload/pageshow opacity code
  // These don't work well with Turbo.js SPA navigation

  // 🌍 LISTEN FOR LANGUAGE CHANGE FROM DASHBOARD
  window.addEventListener('ppv_lang_changed', (e) => {
    log('🌍 [PPV_MYPOINTS] Language changed event:', e.detail);
    if (e.detail.lang) {
      const newLang = e.detail.lang;
      if (['de', 'hu', 'ro'].includes(newLang)) {
        // Reload the page with new language
        const url = new URL(window.location);
        url.searchParams.set('lang', newLang);
        window.location.href = url.toString();
      }
    }
  });

  // 🚀 Main initialization function
  function initAll() {
    // ✅ FIRST: Check if we're on the my-points page
    const container = document.getElementById("ppv-my-points-app");
    if (!container) {
      log('⏭️ [PPV_MYPOINTS] Not a my-points page, skipping');
      return;
    }

    // ✅ Prevent duplicate initialization (causes HTTP 503 on rapid re-init)
    if (container.dataset.initialized === 'true') {
      log('⏭️ [PPV_MYPOINTS] Already initialized, skipping');
      return;
    }
    container.dataset.initialized = 'true';

    log('📄 [PPV_MYPOINTS] Initializing...');
    initLayout();
    initToken();
    initMyPoints();
    protectBottomNav();
    if (DEBUG) initDebug();
  }

  // Initialize on DOMContentLoaded
  document.addEventListener("DOMContentLoaded", initAll);

  // 🚀 Turbo-compatible: Reset flag before rendering new page
  document.addEventListener("turbo:before-render", function() {
    // Reset initialization flag before new content renders
    const container = document.getElementById("ppv-my-points-app");
    if (container) {
      container.dataset.initialized = 'false';
    }
  });

  // 🚀 Re-initialize after navigation (only turbo:load, not render to avoid double-init)
  document.addEventListener("turbo:load", initAll);

  /** ============================
   * 🧩 LAYOUT INIT
   * ============================ */
  function initLayout() {
    log('🧩 [PPV_MYPOINTS] initLayout started');
    const body = document.body;
    body.classList.remove("ppv-user-dashboard");
    body.classList.add("ppv-app-mode", "ppv-my-points");
    
    if (!isOnline) {
      body.classList.add("ppv-offline-mode");
    }
    
    void body.offsetHeight;
    setTimeout(() => window.scrollTo(0, 0), 50);
    log('✅ [PPV_MYPOINTS] Layout OK');
  }

  /** ============================
   * 🔐 TOKEN SYNC
   * ============================ */
  function initToken() {
    log('🔐 [PPV_MYPOINTS] initToken started');
    if (!window.ppvAuthToken && window.ppv_bridge?.token) {
      window.ppvAuthToken = window.ppv_bridge.token;
      log("🔐 Token synced");
    }
  }

  /** ============================
   * 🛡️ BOTTOM NAV PROTECTION
   * ============================ */
  function protectBottomNav() {
    log('🛡️ [PPV_MYPOINTS] protectBottomNav started');
    const navItems = document.querySelectorAll('.ppv-bottom-nav .nav-item[data-navlink]');
    
    navItems.forEach(item => {
      item.addEventListener('click', (e) => {
        if (e.target.closest('[data-navlink]')) {
          window.ppv_skip_fade = true;
        }
      });
    });
  }

  /** ============================
   * 🌍 INIT MY POINTS
   * ============================ */
  async function initMyPoints() {
    log('🔍 [PPV_MYPOINTS::initMyPoints] ========== START ==========');
    log('🔍 [PPV_MYPOINTS] Current URL:', window.location.href);
    log('🔍 [PPV_MYPOINTS] Online status:', isOnline);

    const container = document.getElementById("ppv-my-points-app");
    if (!container) {
      log('ℹ️ [PPV_MYPOINTS] Container not found - script not needed on this page');
      return;
    }
    log('✅ [PPV_MYPOINTS] Container found:', container);

    // Check window.ppv_mypoints
    log('🔍 [PPV_MYPOINTS] Checking window.ppv_mypoints...');
    if (typeof window.ppv_mypoints === 'undefined') {
      error('❌ [PPV_MYPOINTS] window.ppv_mypoints is UNDEFINED!');
      log('🔍 [PPV_MYPOINTS] This means PHP inline script did not load or Service Worker cached old HTML');
    } else {
      log('✅ [PPV_MYPOINTS] window.ppv_mypoints exists:', window.ppv_mypoints);
      log('    - ajaxurl:', window.ppv_mypoints.ajaxurl);
      log('    - api_url:', window.ppv_mypoints.api_url);
      log('    - lang:', window.ppv_mypoints.lang);
      log('    - nonce:', window.ppv_mypoints.nonce ? window.ppv_mypoints.nonce.substring(0, 10) + '...' : 'NOT SET');
    }

    // Check window.ppv_lang
    log('🔍 [PPV_MYPOINTS] Checking window.ppv_lang...');
    if (typeof window.ppv_lang === 'undefined') {
      warn('⚠️ [PPV_MYPOINTS] window.ppv_lang is UNDEFINED - using fallback strings');
    } else {
      log('✅ [PPV_MYPOINTS] window.ppv_lang exists with', Object.keys(window.ppv_lang).length, 'keys');
    }

    // Get language from global
    let lang = window.ppv_mypoints?.lang || 'de';
    if (!["de", "hu", "ro"].includes(lang)) lang = "de";

    const l = getLabels(lang);
    log('🌍 [PPV_MYPOINTS] Active language:', lang);
    log('🔍 [PPV_MYPOINTS] Labels loaded:', Object.keys(l).length, 'keys');

    document.dispatchEvent(new Event("ppv-show-loader"));

    try {
      log('📡 [PPV_MYPOINTS] Fetching points data...');

      let pointsData = null;

      if (isOnline) {
        pointsData = await fetchPointsFromServer(lang);
      } else {
        log('🔴 [PPV_MYPOINTS] Offline mode - loading cache');
        if (window.ppv_offlineDB) {
          pointsData = await window.ppv_offlineDB.getPointsData();
        }
      }

      if (!pointsData) {
        throw new Error(l.error_loading || 'No data available');
      }

      log('✅ [PPV_MYPOINTS] Data loaded successfully');
      renderPoints(container, pointsData, lang, l);

    } catch (err) {
      error("❌ [PPV_MYPOINTS] Error:", err.message);
      error("❌ [PPV_MYPOINTS] Full error:", err);
      const l = getLabels(lang);
      container.innerHTML = `<div style="padding: 20px; color: #f55; text-align: center;">
        <strong>❌ ${l.error}:</strong> ${escapeHtml(err.message)}
      </div>`;
    } finally {
      document.dispatchEvent(new Event("ppv-hide-loader"));
      log('🔍 [PPV_MYPOINTS::initMyPoints] ========== END ==========');
    }
  }

  /** ============================
   * 📡 FETCH FROM SERVER
   * ============================ */
  async function fetchPointsFromServer(lang) {
    log('🔍 [fetchPointsFromServer] ========== START ==========');
    log('🔍 [fetchPointsFromServer] Lang:', lang);

    const token = window.ppvAuthToken || window.ppv_bridge?.token || "";
    log('🔍 [fetchPointsFromServer] Token:', token ? token.substring(0, 20) + '...' : 'NOT SET');

    const headers = new Headers();
    headers.append("Cache-Control", "no-cache");
    headers.append("X-PPV-Lang", lang);
    if (token) headers.append("Authorization", "Bearer " + token);

    // ✅ NE küldjünk WordPress nonce-t!
    // A WordPress REST cookie authentication middleware automatikusan fut ha van X-WP-Nonce header,
    // és 403-at ad vissza invalid nonce esetén, MÉG A permission callback előtt!
    // Mivel saját session-based permission callback-ünk van (check_mypoints_permission),
    // nincs szükség WordPress nonce-ra.
    log('🔍 [fetchPointsFromServer] NOT sending X-WP-Nonce (using session-based auth instead)');

    const apiUrl = window.ppv_mypoints?.api_url ||
                   `${location.origin}/wp-json/ppv/v1/mypoints`;

    log('🔍 [fetchPointsFromServer] API URL:', apiUrl);
    log('🔍 [fetchPointsFromServer] Using fallback URL:', !window.ppv_mypoints?.api_url);

    log('📡 [fetchPointsFromServer] Making fetch request...');
    const res = await fetch(apiUrl, {
      method: "GET",
      headers,
      credentials: "include",
      cache: "no-store",
    });

    log('🔍 [fetchPointsFromServer] Response status:', res.status, res.statusText);
    log('🔍 [fetchPointsFromServer] Response headers:');
    res.headers.forEach((value, key) => {
      log(`    - ${key}: ${value}`);
    });

    if (!res.ok) {
      error('❌ [fetchPointsFromServer] HTTP error:', res.status);

      // Try to get error body
      let errorBody = '';
      try {
        errorBody = await res.text();
        error('❌ [fetchPointsFromServer] Error body:', errorBody);
      } catch (e) {
        error('❌ [fetchPointsFromServer] Could not read error body');
      }

      const l = getLabels(lang);
      if (res.status === 401) throw new Error(l.error_unauthorized);
      if (res.status === 403) throw new Error(l.error_forbidden);
      if (res.status === 404) throw new Error(l.error_api_not_found);
      throw new Error("HTTP " + res.status);
    }

    const jsonData = await res.json();
    log('✅ [fetchPointsFromServer] Success! Data:', jsonData);
    log('🔍 [fetchPointsFromServer] ========== END ==========');
    return jsonData;
  }

  /** ============================
   * 🎨 RENDER POINTS
   * ============================ */
  function renderPoints(container, json, lang, l) {
    log('🎨 renderPoints started');
    
    const d = json.data || {};
    const total = d.total || 0;
    const next = d.remaining || 0;
    const progress = d.next_goal ? Math.min(100, ((d.next_goal - next) / d.next_goal) * 100) : 0;

    // Offline banner
    const offlineBanner = isOnline ? '' : `
      <div class="ppv-offline-banner">
        <i class="ri-signal-tower-2-line"></i>
        <span>${l.offline_mode}</span>
      </div>
    `;

    // Build HTML with all strings from l (getLabels)
    let html = offlineBanner + `
      <div class="ppv-dashboard-netto animate-in">
        <div class="ppv-dashboard-inner">
          
          <!-- HEADER -->
          <div class="ppv-points-header">
          
            <h2>${l.title}</h2>

            <div class="ppv-points-summary">
              <i class="ri-star-fill"></i>
              <span class="big">${total}</span>
              <span class="label">${l.total}</span>
            </div>
            <p class="ppv-motivation">${l.motivation}</p>
          </div>

          <!-- ANALYTICS SECTION -->
          <div id="ppv-analytics-section"></div>

          <!-- STATS GRID -->
          <div class="ppv-stats-grid">
            <div class="ppv-stat-card">
              <i class="ri-line-chart-fill"></i>
              <div class="label">${l.avg}</div>
              <div class="value">${d.avg || 0}</div>
            </div>
            <div class="ppv-stat-card">
              <i class="ri-calendar-event-fill"></i>
              <div class="label">${l.best_day}</div>
              <div class="value">${d.top_day ? d.top_day.total + " • " + d.top_day.day : "-"}</div>
            </div>
            <div class="ppv-stat-card">
              <i class="ri-store-2-fill"></i>
              <div class="label">${l.top_store}</div>
              <div class="value">${d.top_store ? d.top_store.store_name + " (" + d.top_store.total + ")" : "-"}</div>
            </div>
          </div>

         <!-- REWARDS BY STORE (ÚJ!) -->
<div class="ppv-rewards-by-store">
  <h3><i class="ri-store-2-fill"></i> ${l.rewards_by_store_title || 'Jutalmak boltok szerint'}</h3>
  ${buildRewardsByStore(d.rewards_by_store || [], l)}
</div>

          <!-- TOP 3 -->
          <div class="ppv-top3">
            <h3><i class="ri-trophy-fill"></i> ${l.top3}</h3>
            <div class="ppv-top3-grid">
              ${buildTop3Html(d.top3 || [], l)}
            </div>
          </div>

          <!-- RECENT ACTIVITY -->
          <div class="ppv-points-list">
            <h3><i class="ri-time-fill"></i> ${l.recent}</h3>
            ${buildEntriesHtml(d.entries || [], l)}
          </div>
          
        </div>
      </div>
    `;

    container.innerHTML = html;
    log('✅ Render complete');

    // Init analytics
    if (window.ppv_analytics) {
      setTimeout(() => {
        try {
          window.ppv_analytics.init('ppv-analytics-section');
          log('✅ Analytics initialized');
        } catch (err) {
          warn('⚠️ Analytics error:', err.message);
        }
      }, 100);
    }
  }

  /** ============================
   * BUILD TOP 3 HTML
   * ============================ */
  function buildTop3Html(top3, l) {
    if (!top3 || top3.length === 0) {
      return `<p>${l.no_data}</p>`;
    }

    let html = '';
    top3.forEach((s, i) => {
      html += `
        <div class="ppv-top3-card">
          <i class="ri-store-2-line"></i>
          <span class="rank">#${i + 1}</span>
          <span class="name">${escapeHtml(s.store_name || "-")}</span>
          <span class="score">+${s.total || 0} ${l.points_label}</span>
        </div>
      `;
    });
    return html;
  }

  /** ============================
   * BUILD ENTRIES HTML
   * ============================ */
  function buildEntriesHtml(entries, l) {
    if (!entries || entries.length === 0) {
      return `<p style="text-align:center;color:#999;padding:20px;">${l.no_entries}</p>`;
    }

    let html = '';
    entries.forEach(e => {
      const dateStr = e.created ? new Date(e.created).toLocaleString() : "-";
      html += `
        <div class="ppv-point-card">
          <i class="ri-qr-code-line"></i>
          <div class="info">
            <strong>${escapeHtml(e.store_name || "-")}</strong>
            <small>${dateStr}</small>
          </div>
          <span class="ppv-points">${e.points || 0} ${l.points_label}</span>
        </div>
      `;
    });
    return html;
  }

  /** ============================
   * 🛡️ XSS PROTECTION
   * ============================ */
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
  
  /** ============================
 * BUILD REWARDS BY STORE HTML
 * ============================ */
function buildRewardsByStore(stores, l) {
  if (!stores || stores.length === 0) {
    return `<p style="text-align:center;color:#999;padding:20px;">${l.no_rewards || 'Még nincs jutalom'}</p>`;
  }

  let html = '';
  stores.forEach(store => {
    const achieved = store.achieved;
    const statusClass = achieved ? 'ppv-reward-achieved' : 'ppv-reward-progress';
    const statusIcon = achieved ? '🎉' : '🎯';
    const statusText = achieved ? (l.reward_achieved || 'Elérhető!') : `${store.remaining} ${l.points_label || 'pont'} hiányzik`;
    
    html += `
      <div class="ppv-reward-card ${statusClass}" data-store-id="${store.store_id}">
        <div class="reward-header">
          <h4>${statusIcon} ${escapeHtml(store.store_name)}</h4>
          <span class="reward-points">${store.current_points} / ${store.next_goal}</span>
        </div>
        <div class="reward-progress">
          <div class="progress-bar">
            <div class="progress-fill" style="width:${store.progress_percent}%;"></div>
          </div>
          <span class="progress-text">${store.progress_percent}%</span>
        </div>
        <div class="reward-status">
          ${achieved 
            ? `<button class="ppv-btn-claim" onclick="claimReward(${store.store_id})">${l.claim_reward || 'Beváltás'}</button>` 
            : `<span class="remaining">${statusText}</span>`
          }
        </div>
      </div>
    `;
  });
  
  return html;
}

/** ============================
 * CLAIM REWARD (REDIRECT)
 * ============================ */
function claimReward(storeId) {
  if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
  
  // Toast notification
  if (window.ppvShowPointToast) {
    window.ppvShowPointToast('success', 0, '🎉 Jutalom elérhető!');
  }
  
  // Redirect after 1s
  setTimeout(() => {
    window.location.href = '/belohnung?store=' + storeId;
  }, 1000);
}

  /** ============================
   * 🧠 DEBUG
   * ============================ */
  function initDebug() {
    log('🧠 [PPV_DEBUG] ===== DEBUG INFO =====');
    log('🧠 Online:', isOnline);
    log('🧠 Container:', !!document.getElementById("ppv-my-points-app"));
    log('🧠 API URL:', window.ppv_mypoints?.api_url);
    log('🧠 Lang:', window.ppv_mypoints?.lang);
    log('🧠 Strings:', Object.keys(window.ppv_lang || {}).length);
    log('🧠 =======================');
  }

})();