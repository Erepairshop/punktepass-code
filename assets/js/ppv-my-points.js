/**
 * PunktePass – My Points (Production v2.1)
 * ✅ String translations from PHP (window.ppv_lang)
 * ✅ getLabels() function
 * ✅ Offline fallback
 * ✅ Auto-translate on language change
 * ✅ Safari performance fixes
 */

(() => {
  const DEBUG = false; // ✅ FIX: Set to false for production
  let isOnline = navigator.onLine;

  // 🍎 Safari detection
  const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
  if (isSafari) {
  }

  // 🤖 Android detection (for Google Wallet button)
  const isAndroid = /android/i.test(navigator.userAgent);

  // ✅ OPTIMIZED: Conditional logging (only in DEBUG mode)
  const log = (...args) => { if (DEBUG) console.log(...args); };
  const warn = (...args) => { if (DEBUG) console.warn(...args); };
  const error = (...args) => console.error(...args); // Always log errors

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
      // Rewards by store
      rewards_by_store_title: "Belohnungen nach Geschäft",
      no_rewards: "Keine Belohnungen verfügbar",
      reward_achieved: "Einlösbar!",
      claim_reward: "Einlösen",
      points_missing: "fehlen noch",
      // Statistics
      stats_this_week: "Diese Woche",
      stats_this_month: "Dieser Monat",
      stats_this_year: "Dieses Jahr",
      stats_streak: "Aktuelle Serie",
      stats_days: "Tage",
      // Load more
      load_more: "Mehr anzeigen",
      more_stores: "weitere",
      // Google Wallet
      google_wallet_add: "Zu Google Wallet hinzufügen",
      google_wallet_loading: "Wird geladen…",
      google_wallet_error: "Google Wallet nicht verfügbar",
    },
    hu: {
      title: "Pontjaim",
      total: "Összes pont",
      motivation: "Gyűjts pontokat és szerezz csodálatos jutalmakat!",
      avg: "Átlag",
      best_day: "Legjobb nap",
      top_store: "Top bolt",
      next_reward: "Következő jutalom",
      remaining: "hátralévő",
      reward_reached: "🎉 Jutalom elérve!",
      top3: "Top 3 üzlet",
      recent: "Legutóbbi aktivitás",
      offline_mode: "Offline mód",
      no_data: "Nincs elérhető adat",
      no_entries: "Nincs bejegyzés",
      error: "Hiba",
      error_offline: "Offline - Kérlek csatlakozz az internethez",
      error_unauthorized: "Nincs jogosultság",
      error_forbidden: "Hozzáférés megtagadva",
      error_api_not_found: "API nem található",
      error_loading: "Hiba az adatok betöltésekor",
      error_try_again: "Kérlek próbáld újra később",
      points_label: "pont",
      date_label: "Dátum",
      store_label: "Üzlet",
      time_label: "Idő",
      score_label: "Pontszám",
      // Rewards by store
      rewards_by_store_title: "Jutalmak boltok szerint",
      no_rewards: "Nincs elérhető jutalom",
      reward_achieved: "Beváltható!",
      claim_reward: "Beváltás",
      points_missing: "hiányzik",
      // Statistics
      stats_this_week: "Ezen a héten",
      stats_this_month: "Ebben a hónapban",
      stats_this_year: "Idén",
      stats_streak: "Aktuális sorozat",
      stats_days: "nap",
      // Load more
      load_more: "Tovább",
      more_stores: "további",
      // Google Wallet
      google_wallet_add: "Hozzáadás a Google Wallethez",
      google_wallet_loading: "Betöltés…",
      google_wallet_error: "Google Wallet nem elérhető",
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
      no_data: "Nu există date",
      no_entries: "Fără intrări",
      error: "Eroare",
      error_offline: "Offline - Vă rugăm să vă conectați la internet",
      error_unauthorized: "Neautorizat",
      error_forbidden: "Acces refuzat",
      error_api_not_found: "API nu a fost găsit",
      error_loading: "Eroare la încărcarea datelor",
      error_try_again: "Vă rugăm încercați din nou mai târziu",
      points_label: "puncte",
      date_label: "Dată",
      store_label: "Magazin",
      time_label: "Ora",
      score_label: "Scor",
      // Rewards by store
      rewards_by_store_title: "Recompense după magazin",
      no_rewards: "Nu există recompense disponibile",
      reward_achieved: "Disponibil!",
      claim_reward: "Revendică",
      points_missing: "lipsesc",
      // Statistics
      stats_this_week: "Această săptămână",
      stats_this_month: "Această lună",
      stats_this_year: "Acest an",
      stats_streak: "Șirul actual",
      stats_days: "zile",
      // Load more
      load_more: "Mai multe",
      more_stores: "în plus",
      // Google Wallet
      google_wallet_add: "Adaugă în Google Wallet",
      google_wallet_loading: "Se încarcă…",
      google_wallet_error: "Google Wallet indisponibil",
    },
    en: {
      title: "My Points",
      total: "Total Points",
      motivation: "Keep collecting points and earn great rewards!",
      avg: "Average",
      best_day: "Best Day",
      top_store: "Top Store",
      next_reward: "Next Reward",
      remaining: "remaining",
      reward_reached: "🎉 Reward reached!",
      top3: "Top 3 Stores",
      recent: "Recent Activity",
      offline_mode: "Offline Mode",
      no_data: "No data available",
      no_entries: "No entries",
      error: "Error",
      error_offline: "Offline - Please connect to the internet",
      error_unauthorized: "Not authorized",
      error_forbidden: "Access denied",
      error_api_not_found: "API not found",
      error_loading: "Error loading data",
      error_try_again: "Please try again later",
      points_label: "Points",
      date_label: "Date",
      store_label: "Store",
      time_label: "Time",
      score_label: "Score",
      // Rewards by store
      rewards_by_store_title: "Rewards by Store",
      no_rewards: "No rewards available",
      reward_achieved: "Redeemable!",
      claim_reward: "Redeem",
      points_missing: "missing",
      // Statistics
      stats_this_week: "This Week",
      stats_this_month: "This Month",
      stats_this_year: "This Year",
      stats_streak: "Current Streak",
      stats_days: "days",
      // Load more
      load_more: "Load more",
      more_stores: "more",
      // Google Wallet
      google_wallet_add: "Add to Google Wallet",
      google_wallet_loading: "Loading…",
      google_wallet_error: "Google Wallet unavailable",
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
    initAblySync();  // 📡 Real-time updates
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

    // 🍎 Safari fix: Force layout recalculation to prevent flash
    if (isSafari) {
      body.style.display = 'none';
      void body.offsetHeight; // Force reflow
      body.style.display = '';
    } else {
      void body.offsetHeight;
    }

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
    if (!["de", "en", "hu", "ro"].includes(lang)) lang = "de";

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

    log('🔍 [fetchPointsFromServer] NOT sending X-WP-Nonce (using session-based auth instead)');

    const apiUrl = window.ppv_mypoints?.api_url ||
                   `${location.origin}/wp-json/ppv/v1/mypoints`;

    log('🔍 [fetchPointsFromServer] API URL:', apiUrl);

    // Fetch mypoints and analytics summary in parallel
    log('📡 [fetchPointsFromServer] Making parallel fetch requests...');
    const [res, summaryRes] = await Promise.all([
      fetch(apiUrl, {
        method: "GET",
        headers,
        credentials: "include",
        cache: "no-store",
      }),
      fetch('/wp-json/ppv/v1/analytics/summary', {
        headers: { 'X-PPV-Lang': lang },
        credentials: 'include'
      }).catch(() => null) // Don't fail if summary fetch fails
    ]);

    log('🔍 [fetchPointsFromServer] Response status:', res.status, res.statusText);

    if (!res.ok) {
      error('❌ [fetchPointsFromServer] HTTP error:', res.status);

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

    // Parse summary data if available
    let summaryData = null;
    if (summaryRes && summaryRes.ok) {
      try {
        summaryData = await summaryRes.json();
        log('✅ [fetchPointsFromServer] Summary data loaded');
      } catch (e) {
        warn('⚠️ [fetchPointsFromServer] Could not parse summary');
      }
    }

    // Merge summary into response
    if (summaryData?.summary) {
      jsonData.data = jsonData.data || {};
      jsonData.data.stats = summaryData.summary;
    }

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
    const stats = d.stats || {};

    // Offline banner
    const offlineBanner = isOnline ? '' : `
      <div class="ppv-offline-banner">
        <i class="ri-signal-tower-2-line"></i>
        <span>${l.offline_mode}</span>
      </div>
    `;

    // Build HTML (no tabs)
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

          <!-- 🏆 TIER PROGRESS SECTION -->
          ${buildTierProgressHtml(d.tier, d.tiers, l, lang)}

          <!-- 📱 GOOGLE WALLET (Android only) -->
          ${isAndroid ? buildGoogleWalletHtml(l) : ''}

          <!-- 🎁 REFERRAL PROGRAM SECTION -->
          ${buildReferralHtml(d.referral, l, lang)}

          <!-- 📊 STATISTICS SUMMARY (2x2 grid) -->
          <div class="ppv-stats-summary ppv-analytics-summary" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin: 16px 0;">
            <div class="ppv-summary-card" style="display: flex; align-items: center; gap: 12px; padding: 14px; background: rgba(102, 126, 234, 0.08); border-radius: 12px;">
              <div class="card-icon" style="font-size: 24px; color: #667eea;"><i class="ri-calendar-event-fill"></i></div>
              <div class="card-content">
                <div class="label" style="font-size: 11px; color: #64748b; text-transform: uppercase;">${l.stats_this_week}</div>
                <div class="value" style="font-size: 20px; font-weight: 700; color: #1e293b;">${stats.week_points || 0}</div>
                <div class="unit" style="font-size: 11px; color: #94a3b8;">${l.points_label}</div>
              </div>
            </div>
            <div class="ppv-summary-card" style="display: flex; align-items: center; gap: 12px; padding: 14px; background: rgba(118, 75, 162, 0.08); border-radius: 12px;">
              <div class="card-icon" style="font-size: 24px; color: #764ba2;"><i class="ri-calendar-2-fill"></i></div>
              <div class="card-content">
                <div class="label" style="font-size: 11px; color: #64748b; text-transform: uppercase;">${l.stats_this_month}</div>
                <div class="value" style="font-size: 20px; font-weight: 700; color: #1e293b;">${stats.month_points || 0}</div>
                <div class="unit" style="font-size: 11px; color: #94a3b8;">${l.points_label}</div>
              </div>
            </div>
            <div class="ppv-summary-card" style="display: flex; align-items: center; gap: 12px; padding: 14px; background: rgba(16, 185, 129, 0.08); border-radius: 12px;">
              <div class="card-icon" style="font-size: 24px; color: #10b981;"><i class="ri-calendar-check-fill"></i></div>
              <div class="card-content">
                <div class="label" style="font-size: 11px; color: #64748b; text-transform: uppercase;">${l.stats_this_year}</div>
                <div class="value" style="font-size: 20px; font-weight: 700; color: #1e293b;">${stats.year_points || 0}</div>
                <div class="unit" style="font-size: 11px; color: #94a3b8;">${l.points_label}</div>
              </div>
            </div>
            <div class="ppv-summary-card" style="display: flex; align-items: center; gap: 12px; padding: 14px; background: rgba(245, 158, 11, 0.08); border-radius: 12px;">
              <div class="card-icon" style="font-size: 24px; color: #f59e0b;"><i class="ri-fire-fill"></i></div>
              <div class="card-content">
                <div class="label" style="font-size: 11px; color: #64748b; text-transform: uppercase;">${l.stats_streak}</div>
                <div class="value" style="font-size: 20px; font-weight: 700; color: #1e293b;">${stats.current_streak || 0}</div>
                <div class="unit" style="font-size: 11px; color: #94a3b8;">${l.stats_days}</div>
              </div>
            </div>
          </div>

          <!-- TOP 3 -->
          <div class="ppv-top3">
            <h3><i class="ri-trophy-fill"></i> ${l.top3}</h3>
            <div class="ppv-top3-grid">
              ${buildTop3Html(d.top3 || [], l)}
            </div>
          </div>

          <!-- RECENT ACTIVITY (Last 5) -->
          <div class="ppv-points-list">
            <h3><i class="ri-time-fill"></i> ${l.recent}</h3>
            ${buildEntriesHtml((d.entries || []).slice(0, 5), l)}
          </div>

        </div>
      </div>
    `;

    container.innerHTML = html;

    // Bind Google Wallet button click
    const gwalletBtn = container.querySelector('#ppv-google-wallet-btn');
    if (gwalletBtn) {
      gwalletBtn.addEventListener('click', function() {
        const btn = this;
        const label = btn.querySelector('.gwallet-label');
        const origText = label.textContent;
        label.textContent = l.google_wallet_loading;
        btn.style.opacity = '0.7';
        btn.style.pointerEvents = 'none';

        fetch(location.origin + '/wp-json/ppv/v1/google-wallet/save-url', {
          credentials: 'include',
        })
        .then(r => r.json())
        .then(data => {
          if (data.save_url) {
            window.location.href = data.save_url;
          } else {
            label.textContent = data.error || l.google_wallet_error;
            setTimeout(() => { label.textContent = origText; btn.style.opacity = '1'; btn.style.pointerEvents = ''; }, 3000);
          }
        })
        .catch(() => {
          label.textContent = l.google_wallet_error;
          setTimeout(() => { label.textContent = origText; btn.style.opacity = '1'; btn.style.pointerEvents = ''; }, 3000);
        });
      });
    }

    log('✅ Render complete');
  }

  /** ============================
   * 📱 BUILD GOOGLE WALLET HTML
   * ============================ */
  function buildGoogleWalletHtml(l) {
    return `
      <div style="text-align:center; margin: 16px 0;">
        <button id="ppv-google-wallet-btn" type="button" style="
          display: inline-flex; align-items: center; gap: 10px;
          background: #1a1a1a; color: #fff; border: none; border-radius: 24px;
          padding: 12px 24px; font-size: 14px; font-weight: 500;
          cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.15);
          transition: transform 0.15s, box-shadow 0.15s;
        " onmouseover="this.style.transform='scale(1.02)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)'"
           onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)'">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
            <path d="M21.2 9.3l-1.7-1.1c-.2-.1-.4-.1-.5 0l-3.3 2.1-2-1.3c-.2-.1-.4-.1-.5 0L9.8 11.2l-2-1.3c-.2-.1-.4-.1-.5 0L3.8 12c-.2.1-.3.3-.3.5v3.2c0 .2.1.4.3.5l3.5 2.2c.1.1.2.1.3.1s.2 0 .3-.1l3.3-2.1 2 1.3c.1.1.2.1.3.1s.2 0 .3-.1l3.3-2.1 2 1.3c.1.1.2.1.3.1s.2 0 .3-.1l1.7-1.1c.2-.1.3-.3.3-.5V9.8c-.1-.2-.2-.4-.4-.5z" fill="#4285F4"/>
            <path d="M10.1 16.4l-3.3 2.1c-.1.1-.2.1-.3.1s-.2 0-.3-.1l-3.5-2.2c-.2-.1-.3-.3-.3-.5v-3.2c0-.2.1-.4.3-.5l3.5-2.2c.1-.1.4-.1.5 0l2 1.3" fill="#EA4335"/>
            <path d="M13.9 14.3l-3.3 2.1c-.2.1-.4.1-.5 0l-2-1.3" fill="#FBBC04"/>
            <path d="M17.7 12.2l-3.3 2.1c-.2.1-.4.1-.5 0l-2-1.3" fill="#4285F4"/>
            <path d="M21.5 9.8v3.2c0 .2-.1.4-.3.5l-1.7 1.1c-.1.1-.2.1-.3.1s-.2 0-.3-.1l-2-1.3" fill="#34A853"/>
          </svg>
          <span class="gwallet-label">${l.google_wallet_add}</span>
        </button>
      </div>
    `;
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
   * 🏆 BUILD TIER PROGRESS HTML
   * ============================ */
  function buildTierProgressHtml(tier, tiers, l, lang) {
    if (!tier || !tiers) {
      return '';
    }

    // Tier labels for different languages
    const tierLabels = {
      de: {
        your_level: 'Dein bestes VIP Level',
        next_level: 'Nächstes Level',
        points_needed: 'Scans noch nötig',
        max_level: 'Maximales Level erreicht!',
        lifetime_points: 'Scans bei',
        all_shops_info: 'VIP-Boni gelten nur in dem Geschäft, wo du sie erreicht hast',
        vip_not_everywhere: 'Nicht alle Geschäfte bieten VIP-Boni an',
        how_vip_works: 'Je öfter du bei einem Geschäft scannst, desto höher dein VIP-Level dort',
      },
      hu: {
        your_level: 'Legjobb VIP szinted',
        next_level: 'Következő szint',
        points_needed: 'scan még szükséges',
        max_level: 'Maximális szint elérve!',
        lifetime_points: 'Scan itt:',
        all_shops_info: 'A VIP bónuszok csak abban az üzletben érvényesek, ahol elérted',
        vip_not_everywhere: 'Nem minden üzlet kínál VIP bónuszokat',
        how_vip_works: 'Minél többet szkennelsz egy üzletben, annál magasabb a VIP szinted ott',
      },
      ro: {
        your_level: 'Cel mai bun nivel VIP',
        next_level: 'Următorul nivel',
        points_needed: 'scanări mai necesare',
        max_level: 'Nivel maxim atins!',
        lifetime_points: 'Scanări la',
        all_shops_info: 'Bonusurile VIP sunt valabile doar în magazinul unde le-ai obținut',
        vip_not_everywhere: 'Nu toate magazinele oferă bonusuri VIP',
        how_vip_works: 'Cu cât scanezi mai mult la un magazin, cu atât nivelul VIP e mai mare acolo',
      },
      en: {
        your_level: 'Your best VIP level',
        next_level: 'Next level',
        points_needed: 'scans still needed',
        max_level: 'Maximum level reached!',
        lifetime_points: 'Scans at',
        all_shops_info: 'VIP bonuses only apply at the store where you earned them',
        vip_not_everywhere: 'Not all stores offer VIP bonuses',
        how_vip_works: 'The more you scan at a store, the higher your VIP level there',
      }
    };

    const t = tierLabels[lang] || tierLabels.de;
    const currentLevel = tier.level || 'starter';
    const lifetimePoints = tier.lifetime_points || 0;
    const progress = tier.progress || 0;
    const pointsToNext = tier.points_to_next || 0;
    const isMaxLevel = currentLevel === 'platinum';

    // Tier icons and colors
    const tierIcons = {
      starter: 'ri-user-line',
      bronze: 'ri-medal-line',
      silver: 'ri-medal-fill',
      gold: 'ri-vip-crown-fill',
      platinum: 'ri-vip-diamond-fill'
    };

    const tierColors = {
      starter: '#6c757d',
      bronze: '#CD7F32',
      silver: '#C0C0C0',
      gold: '#FFD700',
      platinum: '#A0B2C6'
    };

    // Find next level
    const tierOrder = ['starter', 'bronze', 'silver', 'gold', 'platinum'];
    const currentIndex = tierOrder.indexOf(currentLevel);
    const nextLevel = currentIndex < tierOrder.length - 1 ? tierOrder[currentIndex + 1] : null;
    const nextLevelName = nextLevel && tiers[nextLevel] ? tiers[nextLevel].name : '';
    const nextLevelMin = nextLevel && tiers[nextLevel] ? tiers[nextLevel].min : 0;

    // Build tier dots/steps
    let tierDotsHtml = '';
    tierOrder.forEach((t, i) => {
      const isActive = i <= currentIndex;
      const isCurrent = t === currentLevel;
      const tierData = tiers[t] || {};
      tierDotsHtml += `
        <div class="ppv-tier-step ${isActive ? 'active' : ''} ${isCurrent ? 'current' : ''}"
             style="--tier-color: ${tierColors[t]}">
          <div class="tier-dot">
            <i class="${tierIcons[t]}"></i>
          </div>
          <span class="tier-name">${tierData.name || t}</span>
          <span class="tier-points">${tierData.min || 0}+ scan</span>
        </div>
      `;
    });

    return `
      <div class="ppv-tier-progress-section">
        <h3><i class="ri-vip-crown-fill"></i> ${t.your_level}</h3>

        <!-- Current Level Badge -->
        <div class="ppv-current-tier-badge" style="--tier-color: ${tierColors[currentLevel]}">
          <i class="${tierIcons[currentLevel]}"></i>
          <span class="tier-level-name">${tier.name || currentLevel}</span>
        </div>

        <!-- Scans at best store -->
        <div class="ppv-lifetime-points">
          <span class="points-value">${lifetimePoints}</span>
          <span class="points-label">${t.lifetime_points} ${tier.best_store_name || ''}</span>
        </div>

        <!-- Progress Bar to Next Level -->
        ${!isMaxLevel ? `
          <div class="ppv-tier-progress-bar-container">
            <div class="ppv-tier-progress-info">
              <span>${t.next_level}: <strong>${nextLevelName}</strong></span>
              <span><strong>${pointsToNext}</strong> ${t.points_needed}</span>
            </div>
            <div class="ppv-tier-progress-bar">
              <div class="ppv-tier-progress-fill" style="width: ${progress}%; background: linear-gradient(90deg, ${tierColors[currentLevel]}, ${tierColors[nextLevel]})"></div>
            </div>
          </div>
        ` : `
          <div class="ppv-tier-max-level">
            <i class="ri-trophy-fill"></i>
            <span>${t.max_level}</span>
          </div>
        `}

        <!-- All Tiers Overview -->
        <div class="ppv-tier-steps">
          ${tierDotsHtml}
        </div>

        <!-- Info text -->
        <p class="ppv-tier-info-text" style="margin-bottom: 6px;">
          <i class="ri-lightbulb-line"></i>
          ${t.how_vip_works}
        </p>
        <p class="ppv-tier-info-text">
          <i class="ri-information-line"></i>
          ${t.all_shops_info}
        </p>
        <p class="ppv-tier-info-text ppv-vip-warning" style="color: #f59e0b; margin-top: 6px;">
          <i class="ri-error-warning-line"></i>
          ${t.vip_not_everywhere}
        </p>
      </div>
    `;
  }

  /** ============================
   * 🎁 BUILD REFERRAL SECTION HTML
   * ============================ */
  function buildReferralHtml(referral, l, lang) {
    if (!referral || !referral.enabled || !referral.stores || referral.stores.length === 0) {
      return '';
    }

    // Use translations from ppv_lang (l) with fallbacks
    const fallbacks = {
      de: {
        title: 'Freunde einladen',
        subtitle: 'Empfehle Freunde und sammelt beide Bonuspunkte!',
        your_code: 'Dein Einladungscode',
        copy: 'Link kopieren',
        copied: 'Kopiert!',
        whatsapp: 'WhatsApp',
        successful: 'Erfolgreich',
        pending: 'Ausstehend',
        no_referrals_yet: 'Noch keine Einladungen',
      },
      hu: {
        title: 'Barátok meghívása',
        subtitle: 'Hívd meg barátaidat és mindketten bónuszpontokat kaptok!',
        your_code: 'A meghívó kódod',
        copy: 'Link másolása',
        copied: 'Másolva!',
        whatsapp: 'WhatsApp',
        successful: 'Sikeres',
        pending: 'Függőben',
        no_referrals_yet: 'Még nincsenek meghívások',
      },
      en: {
        title: 'Invite Friends',
        subtitle: 'Invite friends and both of you earn bonus points!',
        your_code: 'Your invite code',
        copy: 'Copy link',
        copied: 'Copied!',
        whatsapp: 'WhatsApp',
        successful: 'Successful',
        pending: 'Pending',
        no_referrals_yet: 'No invitations yet',
      },
      ro: {
        title: 'Invită prieteni',
        subtitle: 'Invită-ți prietenii și amândoi primiți puncte bonus!',
        your_code: 'Codul tău de invitație',
        copy: 'Copiază link',
        copied: 'Copiat!',
        whatsapp: 'WhatsApp',
        successful: 'Reușit',
        pending: 'În așteptare',
        no_referrals_yet: 'Încă nu ai invitații',
      }
    };

    const fb = fallbacks[lang] || fallbacks.de;
    const t = {
      title: l.referral_section_title || fb.title,
      subtitle: l.referral_section_subtitle || fb.subtitle,
      your_code: l.referral_your_code || fb.your_code,
      copy: l.referral_copy_link || fb.copy,
      copied: l.referral_copied || fb.copied,
      whatsapp: l.referral_share_whatsapp || fb.whatsapp,
      successful: l.referral_successful || fb.successful,
      pending: l.referral_pending || fb.pending,
      no_referrals_yet: l.referral_no_invites || fb.no_referrals_yet,
    };

    // Build store cards
    let storeCardsHtml = '';
    referral.stores.forEach(store => {
      const hasReferrals = store.stats.total > 0;

      storeCardsHtml += `
        <div class="ppv-referral-store-card" data-store-id="${store.id}">
          <div class="referral-store-header">
            <h4><i class="ri-store-2-fill"></i> ${escapeHtml(store.name)}</h4>
            <span class="referral-reward-badge">
              <i class="ri-gift-fill"></i> ${escapeHtml(store.reward_text)}
            </span>
          </div>

          <div class="referral-code-box">
            <span class="code-label">${t.your_code}:</span>
            <span class="code-value">${escapeHtml(referral.code)}</span>
          </div>

          <div class="referral-share-buttons">
            <button class="ppv-btn-share ppv-btn-whatsapp" onclick="shareReferralWhatsApp('${escapeHtml(store.share_url)}', '${escapeHtml(store.name)}')">
              <i class="ri-whatsapp-fill"></i> ${t.whatsapp}
            </button>
            <button class="ppv-btn-share ppv-btn-copy" onclick="copyReferralLink('${escapeHtml(store.share_url)}', this)">
              <i class="ri-link"></i> ${t.copy}
            </button>
          </div>

          ${hasReferrals ? `
            <div class="referral-stats">
              <div class="stat">
                <i class="ri-check-double-fill"></i>
                <span class="value">${store.stats.successful}</span>
                <span class="label">${t.successful}</span>
              </div>
              <div class="stat">
                <i class="ri-time-fill"></i>
                <span class="value">${store.stats.pending}</span>
                <span class="label">${t.pending}</span>
              </div>
            </div>
          ` : `
            <p class="referral-no-stats"><i class="ri-user-add-line"></i> ${t.no_referrals_yet}</p>
          `}
        </div>
      `;
    });

    return `
      <div class="ppv-referral-section">
        <h3><i class="ri-user-add-fill"></i> ${t.title}</h3>
        <p class="referral-subtitle">${t.subtitle}</p>
        <div class="ppv-referral-stores">
          ${storeCardsHtml}
        </div>
      </div>
    `;
  }

  // Share via WhatsApp
  window.shareReferralWhatsApp = function(url, storeName) {
    if (navigator.vibrate) navigator.vibrate(50);
    const lang = window.ppv_mypoints?.lang || 'de';
    const messages = {
      de: `Hey! Hol dir Punkte bei ${storeName}! Nutze meinen Einladungslink: ${url}`,
      en: `Hey! Collect points at ${storeName}! Use my invite link: ${url}`,
      hu: `Hé! Gyűjts pontokat itt: ${storeName}! Használd a meghívó linkemet: ${url}`,
      ro: `Hei! Colectează puncte la ${storeName}! Folosește link-ul meu de invitație: ${url}`
    };
    const text = encodeURIComponent(messages[lang] || messages.de);
    window.open(`https://wa.me/?text=${text}`, '_blank');
  };

  // Copy referral link
  window.copyReferralLink = function(url, btn) {
    if (navigator.vibrate) navigator.vibrate([50, 30, 50]);

    navigator.clipboard.writeText(url).then(() => {
      const originalHtml = btn.innerHTML;
      btn.innerHTML = '<i class="ri-check-line"></i> Kopiert!';
      btn.classList.add('copied');

      setTimeout(() => {
        btn.innerHTML = originalHtml;
        btn.classList.remove('copied');
      }, 2000);
    }).catch(err => {
      console.error('Copy failed:', err);
      // Fallback for older browsers
      const input = document.createElement('input');
      input.value = url;
      document.body.appendChild(input);
      input.select();
      document.execCommand('copy');
      document.body.removeChild(input);

      btn.innerHTML = '<i class="ri-check-line"></i> Kopiert!';
      btn.classList.add('copied');
      setTimeout(() => {
        btn.innerHTML = '<i class="ri-link"></i> Link kopieren';
        btn.classList.remove('copied');
      }, 2000);
    });
  };

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
 * BUILD REWARDS BY STORE HTML (5 at a time with Load More)
 * ============================ */
function buildRewardsByStore(stores, l) {
  if (!stores || stores.length === 0) {
    return `<p style="text-align:center;color:#999;padding:20px;">${l.no_rewards || 'Még nincs jutalom'}</p>`;
  }

  const ITEMS_PER_PAGE = 5;
  const uniqueId = 'rewards-' + Date.now();

  // Build all reward cards
  let cardsHtml = '';
  stores.forEach((store, index) => {
    const achieved = store.achieved;
    const statusClass = achieved ? 'ppv-reward-achieved' : 'ppv-reward-progress';
    const statusIcon = achieved ? '🎉' : '🎯';
    const statusText = achieved ? (l.reward_achieved || 'Einlösbar!') : `${store.remaining} ${l.points_label || 'Punkte'} ${l.points_missing || 'fehlen noch'}`;
    const hiddenClass = index >= ITEMS_PER_PAGE ? 'ppv-reward-hidden' : '';

    // Show both names like user-dashboard (company_name + name)
    // store_name = company_name, store_name_short = name (filiale)
    const companyName = store.store_name || '';
    const storeName = store.store_name_short || '';
    const displayName = companyName || storeName || 'Unknown';

    cardsHtml += `
      <div class="ppv-reward-card ${statusClass} ${hiddenClass}" data-store-id="${store.store_id}" data-index="${index}" style="${index >= ITEMS_PER_PAGE ? 'display:none;' : ''}">
        <div class="reward-header" style="flex-direction: column; align-items: flex-start;">
          <div style="display: flex; justify-content: space-between; width: 100%; align-items: flex-start;">
            <h4 style="margin: 0;">${statusIcon} ${escapeHtml(companyName || storeName)}</h4>
            <span class="reward-points">${store.current_points} / ${store.next_goal || '?'}</span>
          </div>
          ${storeName && companyName ? `<p style="font-size: 13px; color: #475569; font-weight: 500; margin: 4px 0 0 24px;">${escapeHtml(storeName)}</p>` : ''}
        </div>
        <div class="reward-progress">
          <div class="progress-bar" style="background: #e2e8f0; border-radius: 4px; height: 8px; overflow: hidden;">
            <div class="progress-fill" style="width:${store.progress_percent || 0}%; height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); border-radius: 4px;"></div>
          </div>
          <span class="progress-text" style="font-size: 12px; color: #64748b;">${store.progress_percent || 0}%</span>
        </div>
        <div class="reward-status">
          ${achieved
            ? `<button class="ppv-btn-claim" onclick="claimReward(${store.store_id})" style="background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer;">${l.claim_reward || 'Beváltás'}</button>`
            : `<span class="remaining" style="font-size: 13px; color: #64748b;">${statusText}</span>`
          }
        </div>
      </div>
    `;
  });

  // Add "Load More" button if needed
  const showLoadMore = stores.length > ITEMS_PER_PAGE;
  const loadMoreBtn = showLoadMore ? `
    <button id="${uniqueId}-loadmore" class="ppv-load-more-btn" onclick="loadMoreRewards('${uniqueId}', ${ITEMS_PER_PAGE})"
            style="display: block; width: 100%; padding: 12px; margin-top: 12px; background: rgba(102, 126, 234, 0.1); border: none; border-radius: 8px; color: #667eea; font-weight: 600; cursor: pointer;">
      ${l.load_more || 'Tovább'} (${stores.length - ITEMS_PER_PAGE} ${l.more_stores || 'további'})
    </button>
  ` : '';

  return `
    <div id="${uniqueId}" class="ppv-rewards-list" data-shown="${ITEMS_PER_PAGE}" data-total="${stores.length}">
      ${cardsHtml}
      ${loadMoreBtn}
    </div>
  `;
}

// Load more rewards function
window.loadMoreRewards = function(containerId, itemsPerPage) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const shown = parseInt(container.dataset.shown) || itemsPerPage;
  const total = parseInt(container.dataset.total) || 0;
  const newShown = shown + itemsPerPage;

  // Show next batch
  const cards = container.querySelectorAll('.ppv-reward-card');
  cards.forEach((card, index) => {
    if (index < newShown) {
      card.style.display = '';
      card.classList.remove('ppv-reward-hidden');
    }
  });

  container.dataset.shown = newShown;

  // Hide button if all shown
  const btn = document.getElementById(containerId + '-loadmore');
  if (btn) {
    if (newShown >= total) {
      btn.style.display = 'none';
    } else {
      const remaining = total - newShown;
      btn.innerHTML = `Tovább (${remaining} további)`;
    }
  }

  // Haptic feedback
  if (window.ppvHaptic) window.ppvHaptic('tap');
};

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
   * 📡 ABLY REAL-TIME SYNC (via shared manager)
   * ============================ */
  function initAblySync() {
    const cfg = window.ppv_mypoints;
    if (!cfg?.ably?.key || !cfg?.uid || !window.PPV_ABLY_MANAGER) {
      log('🔄 [PPV_MYPOINTS] Ably not available, no real-time updates');
      return;
    }

    log('📡 [PPV_MYPOINTS] Initializing Ably via shared manager for user:', cfg.uid);

    const manager = window.PPV_ABLY_MANAGER;
    const channelName = 'user-' + cfg.uid;

    // Initialize shared connection
    if (!manager.init({ key: cfg.ably.key, channel: channelName })) {
      log('📡 [PPV_MYPOINTS] Shared manager init failed');
      return;
    }

    // Store subscriber ID for cleanup
    window.PPV_MYPOINTS_ABLY_SUB = 'mypoints-' + cfg.uid;

    manager.onStateChange((state) => {
      if (state === 'connected') {
        log('📡 [PPV_MYPOINTS] Ably connected via shared manager to channel:', channelName);
      }
    });

    // Handle points update - refresh the whole page data
    manager.subscribe(channelName, 'points-update', (message) => {
      const data = message.data;
      log('📡 [PPV_MYPOINTS] Points update received:', data);

      if (data.success && data.points_added > 0) {
        // Update total points in header if element exists
        const totalEl = document.querySelector('.ppv-mypoints-total-number');
        if (totalEl && data.total_points !== undefined) {
          totalEl.textContent = data.total_points;
          totalEl.style.transition = 'transform 0.3s, color 0.3s';
          totalEl.style.transform = 'scale(1.2)';
          totalEl.style.color = '#00e676';
          setTimeout(() => {
            totalEl.style.transform = 'scale(1)';
            totalEl.style.color = '';
          }, 500);
        }

        // Show toast notification
        if (window.ppvShowPointToast) {
          window.ppvShowPointToast('success', data.points_added, data.store_name || 'PunktePass');
        }

        // Refresh the page data after a short delay
        setTimeout(() => {
          log('📡 [PPV_MYPOINTS] Refreshing page data...');
          initMyPoints();
        }, 1000);
      } else if (data.success === false) {
        // Show error toast
        if (window.ppvShowPointToast) {
          window.ppvShowPointToast('error', 0, data.store_name || 'PunktePass', data.message);
        }
      }
    }, window.PPV_MYPOINTS_ABLY_SUB);
  }

  // Cleanup Ably subscription on navigation (manager handles connection)
  document.addEventListener('turbo:before-visit', () => {
    if (window.PPV_MYPOINTS_ABLY_SUB && window.PPV_ABLY_MANAGER) {
      log('🧹 [PPV_MYPOINTS] Cleaning up Ably subscription');
      window.PPV_ABLY_MANAGER.unsubscribe(window.PPV_MYPOINTS_ABLY_SUB);
      window.PPV_MYPOINTS_ABLY_SUB = null;
    }
  });

  // 🍎 Safari fix: Also cleanup on pagehide
  if (isSafari) {
    window.addEventListener('pagehide', () => {
      if (window.PPV_MYPOINTS_ABLY_SUB && window.PPV_ABLY_MANAGER) {
        log('🍎 [PPV_MYPOINTS] Safari pagehide - cleanup subscription');
        window.PPV_ABLY_MANAGER.unsubscribe(window.PPV_MYPOINTS_ABLY_SUB);
        window.PPV_MYPOINTS_ABLY_SUB = null;
        window.PPV_MYPOINTS_ABLY = null;
      }
    });
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