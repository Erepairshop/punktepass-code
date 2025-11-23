/**
 * PunktePass – User Dashboard JS (v4.8 - Geo Retry Edition)
 *
 * ✨ REQUIRED: Remix Icon CDN
 * Add this to your HTML <head>:
 * <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
 *
 * 🎨 ICONS: All icons from Remix Icon (https://remixicon.com/)
 * ✅ FIXED: Toggle listener - Simple & Clean
 * ✅ REMOVED: Complex attachStoreCardListeners, flag system
 * ✅ KEPT: All other functionality
 * ✅ FULLY TRANSLATED: DE/HU/RO
 * ✅ MODERN ICONS: No emojis, pure icon fonts
 * 🚀 TURBO-COMPATIBLE: Re-initializes on navigation
 * ✅ FIX: Geolocation timeout increased (2s→8s) for first-time users
 * ✅ FIX: Auto-retry geo after 5s if first attempt fails (login redirect fix)
 */

// 🚀 Global state for Turbo navigation cleanup
window.PPV_POLL_INTERVAL_ID = null;
window.PPV_VISIBILITY_HANDLER = null;
window.PPV_SLIDER_HANDLER = null;
window.PPV_SLIDER_INITIALIZED = false;
window.PPV_STORES_LOADING = false;
window.PPV_POLLING_IN_PROGRESS = false; // ✅ FIX: Prevent concurrent poll calls
window.PPV_SLIDER_FETCH_IN_PROGRESS = false; // ✅ FIX: Prevent concurrent slider fetches
window.PPV_CURRENT_DISTANCE = 10; // ✅ FIX: Track current slider value

// ✅ OPTIMIZATION: Translation object as top-level constant (created once, not per render)
const PPV_TRANSLATIONS = {
  de: {
    welcome: "Willkommen bei PunktePass",
    points: "Meine Punkte",
    rewards: "Prämien",
    collect_here: "Hier Punkte sammeln",
    show_in_store: "Zeig deinen persönlichen QR-Code im Geschäft",
    show_qr: "QR-Code anzeigen",
    show_code_tip: "Zeig diesen Code im Geschäft, um Punkte zu sammeln.",
    how_to_use: "So verwendest du den Code",
    qr_instruction_1: "1. Zeige diesen Code dem Kassierer",
    qr_instruction_2: "2. Er scannt ihn mit seinem Terminal",
    qr_instruction_3: "3. Du sammelst automatisch Punkte!",
    nearby: "Geschäfte in deiner Nähe",
    no_stores: "Keine Geschäfte gefunden",
    route: "Route",
    open: "Geöffnet",
    closed: "Geschlossen",
    dist_unknown: "Entfernung unbekannt",
    call: "Anrufen",
    website: "Webseite",
    campaign: "Kampagne",
    loading: "Lädt...",
    km: "km",
    distance_label: "Entfernung",
    rewards_title: "Prämien",
    campaigns_title: "Kampagnen:",
    rewards_preview: "x Prämien",
    campaigns_preview: "x Kampagnen",
    gallery_label: "Galerie",
    reward_label_required: "Erforderlich:",
    reward_label_reward: "Prämie:",
    reward_label_date: "Datum:",
    reward_per_scan: "Pro Scan:",
    discount_percent_text: "% Rabatt",
    discount_fixed_text: "€ Rabatt",
    points_multiplier_text: "x Punkte",
    fixed_text: "€ Bonus",
    free_product_text: "Kostenloses Produkt",
    special_offer: "Speziales Angebot",
    err_already_scanned_today: "⚠️ Heute bereits gescannt",
    err_duplicate_scan: "⚠️ Bereits gescannt. Bitte warten.",
    vip_title: "VIP Boni",
    vip_fix_title: "Fixpunkte",
    vip_streak_title: "X. Scan",
    vip_daily_title: "1. Scan/Tag",
    vip_bronze: "Bronze",
    vip_silver: "Silber",
    vip_gold: "Gold",
    vip_platinum: "Platin",
    vip_every: "Jeden",
    vip_scan: "Scan",
    vip_double: "2x Punkte",
    vip_triple: "3x Punkte",
  },
  hu: {
    welcome: "Üdv a PunktePassban",
    points: "Pontjaim",
    rewards: "Jutalmak",
    collect_here: "Itt tudsz pontot gyűjteni",
    show_in_store: "Mutasd a saját QR-kódod az üzletben",
    show_qr: "QR-kód megjelenítése",
    show_code_tip: "Mutasd ezt a kódot az üzletben a pontgyűjtéshez.",
    how_to_use: "Így használd a kódot",
    qr_instruction_1: "1. Mutasd ezt a kódot a pénztárosnak",
    qr_instruction_2: "2. Ő beolvassa a terminálba",
    qr_instruction_3: "3. Automatikusan gyűjtesz pontot!",
    nearby: "Közeli üzletek",
    no_stores: "Nem található üzlet",
    route: "Útvonal",
    open: "Nyitva",
    closed: "Zárva",
    dist_unknown: "Ismeretlen távolság",
    call: "Hívás",
    website: "Weboldal",
    campaign: "Kampány",
    loading: "Betöltés...",
    km: "km",
    distance_label: "Távolság",
    rewards_title: "Jutalmak",
    campaigns_title: "Kampányok:",
    rewards_preview: "x Jutalom",
    campaigns_preview: "x Kampány",
    gallery_label: "Galéria",
    reward_label_required: "Szükséges:",
    reward_label_reward: "Jutalom:",
    reward_label_date: "Dátum:",
    reward_per_scan: "Per Scan:",
    discount_percent_text: "% engedmény",
    discount_fixed_text: "€ engedmény",
    points_multiplier_text: "x Pontok",
    fixed_text: "€ Bonus",
    free_product_text: "Ingyenes termék",
    special_offer: "Különleges ajánlat",
    err_already_scanned_today: "⚠️ Ma már beolvasva",
    err_duplicate_scan: "⚠️ Már beolvasva. Kérlek várj.",
    vip_title: "VIP Bónuszok",
    vip_fix_title: "Fix pont",
    vip_streak_title: "X. scan",
    vip_daily_title: "1. scan/nap",
    vip_bronze: "Bronz",
    vip_silver: "Ezüst",
    vip_gold: "Arany",
    vip_platinum: "Platina",
    vip_every: "Minden",
    vip_scan: "scan",
    vip_double: "2x Pont",
    vip_triple: "3x Pont",
  },
  ro: {
    welcome: "Bun venit la PunktePass",
    points: "Punctele mele",
    rewards: "Recompense",
    collect_here: "Colectează puncte aici",
    show_in_store: "Arată codul tău QR în magazin",
    show_qr: "Afișează codul QR",
    show_code_tip: "Arată acest cod în magazin pentru a colecta puncte.",
    how_to_use: "Cum să folosești codul",
    qr_instruction_1: "1. Arată acest cod casierului",
    qr_instruction_2: "2. El îl scanează pe terminalul lui",
    qr_instruction_3: "3. Colectezi automat puncte!",
    nearby: "Magazine în apropiere",
    no_stores: "Nu s-au găsit magazine",
    route: "Rută",
    open: "Deschis",
    closed: "Închis",
    dist_unknown: "Distanță necunoscută",
    call: "Apelează",
    website: "Site",
    campaign: "Campanie",
    loading: "Se încarcă...",
    km: "km",
    distance_label: "Distanță",
    rewards_title: "Recompense",
    campaigns_title: "Campanii:",
    rewards_preview: "x Recompense",
    campaigns_preview: "x Campanii",
    gallery_label: "Galerie",
    reward_label_required: "Necesar:",
    reward_label_reward: "Recompensă:",
    reward_label_date: "Dată:",
    reward_per_scan: "Per Scan:",
    discount_percent_text: "% Reducere",
    discount_fixed_text: "€ Reducere",
    points_multiplier_text: "x Puncte",
    fixed_text: "€ Bonus",
    free_product_text: "Produs gratuit",
    special_offer: "Ofertă specială",
    err_already_scanned_today: "⚠️ Deja scanat astăzi",
    err_duplicate_scan: "⚠️ Deja scanat. Vă rugăm așteptați.",
    vip_title: "Bonusuri VIP",
    vip_fix_title: "Puncte fixe",
    vip_streak_title: "Scan X",
    vip_daily_title: "1. scan/zi",
    vip_bronze: "Bronz",
    vip_silver: "Argint",
    vip_gold: "Aur",
    vip_platinum: "Platină",
    vip_every: "La fiecare",
    vip_scan: "scanare",
    vip_double: "2x Puncte",
    vip_triple: "3x Puncte",
  }
};

// 🧹 Cleanup function - call before navigation or re-init
function cleanupPolling() {
  if (window.PPV_POLL_INTERVAL_ID) {
    clearInterval(window.PPV_POLL_INTERVAL_ID);
    window.PPV_POLL_INTERVAL_ID = null;
    console.log('🧹 [Polling] Interval cleared');
  }
  if (window.PPV_VISIBILITY_HANDLER) {
    document.removeEventListener('visibilitychange', window.PPV_VISIBILITY_HANDLER);
    window.PPV_VISIBILITY_HANDLER = null;
    console.log('🧹 [Polling] Visibility listener removed');
  }
  if (window.PPV_SLIDER_HANDLER) {
    document.removeEventListener('input', window.PPV_SLIDER_HANDLER);
    window.PPV_SLIDER_HANDLER = null;
    console.log('🧹 [Slider] Handler removed');
  }
  window.PPV_POLLING_ACTIVE = false;
  window.PPV_SLIDER_INITIALIZED = false;
  window.PPV_STORES_LOADING = false;
  window.PPV_POLLING_IN_PROGRESS = false;
  window.PPV_SLIDER_FETCH_IN_PROGRESS = false;
  window.PPV_CURRENT_DISTANCE = 10;
}

// 🚀 Turbo-compatible initialization
async function initUserDashboard() {
  // Check if dashboard root exists (only run on user dashboard pages)
  const dashboardRoot = document.getElementById('ppv-dashboard-root');
  if (!dashboardRoot) {
    console.log("⏭️ [Dashboard] Not a dashboard page, skipping init");
    // 🧹 Clean up polling if we're NOT on dashboard page anymore
    cleanupPolling();
    return;
  }

  // Prevent double initialization
  if (dashboardRoot.dataset.initialized === 'true') {
    console.log("⏭️ [Dashboard] Already initialized, skipping");
    return;
  }
  dashboardRoot.dataset.initialized = 'true';
  const boot = window.ppv_boot || {};
  const API = (boot.api || "/wp-json/ppv/v1/").replace(/\/+$/, '/');
  const lang = boot.lang || 'de';

  // ✅ OPTIMIZATION: Use global constant instead of creating object each time
  const T = PPV_TRANSLATIONS[lang] || PPV_TRANSLATIONS.de;

  const root = document.getElementById("ppv-dashboard-root");
  if (!root) return;

  // ============================================================
  // LIGHTBOX SYSTEM
  // ============================================================
  let lightboxActive = false;
  let currentImageIndex = 0;
  let currentStoreImages = [];

  const createLightbox = () => {
    const lb = document.createElement('div');
    lb.id = 'ppv-lightbox';
    lb.className = 'ppv-lightbox';
    lb.innerHTML = `
      <div class="ppv-lightbox-overlay"></div>
      <div class="ppv-lightbox-container">
        <button class="ppv-lightbox-close">&times;</button>
        <button class="ppv-lightbox-prev"><i class="ri-arrow-left-s-line"></i></button>
        <img src="" alt="Gallery" class="ppv-lightbox-image">
        <button class="ppv-lightbox-next"><i class="ri-arrow-right-s-line"></i></button>
        <div class="ppv-lightbox-counter">
          <span class="ppv-lightbox-current">1</span> / <span class="ppv-lightbox-total">1</span>
        </div>
      </div>
    `;
    document.body.appendChild(lb);

    const overlay = lb.querySelector('.ppv-lightbox-overlay');
    const closeBtn = lb.querySelector('.ppv-lightbox-close');
    const prevBtn = lb.querySelector('.ppv-lightbox-prev');
    const nextBtn = lb.querySelector('.ppv-lightbox-next');
    const img = lb.querySelector('.ppv-lightbox-image');
    const currentSpan = lb.querySelector('.ppv-lightbox-current');
    const totalSpan = lb.querySelector('.ppv-lightbox-total');

    // ✅ FIX: Store keydown handler for cleanup
    let keydownHandler = null;

    const closeLightbox = () => {
      lb.classList.remove('active');
      lightboxActive = false;
      // ✅ FIX: Remove keydown listener to prevent memory leak
      if (keydownHandler) {
        document.removeEventListener('keydown', keydownHandler);
        keydownHandler = null;
      }
      setTimeout(() => lb.remove(), 300);
    };

    const updateImage = () => {
      if (currentStoreImages.length === 0) return;
      img.src = currentStoreImages[currentImageIndex];
      currentSpan.textContent = currentImageIndex + 1;
      totalSpan.textContent = currentStoreImages.length;
      if (navigator.vibrate) navigator.vibrate(10);
    };

    overlay.addEventListener('click', closeLightbox);
    closeBtn.addEventListener('click', closeLightbox);

    prevBtn.addEventListener('click', () => {
      currentImageIndex = (currentImageIndex - 1 + currentStoreImages.length) % currentStoreImages.length;
      updateImage();
    });

    nextBtn.addEventListener('click', () => {
      currentImageIndex = (currentImageIndex + 1) % currentStoreImages.length;
      updateImage();
    });

    // ✅ FIX: Store handler reference for cleanup
    keydownHandler = (e) => {
      if (!lightboxActive) return;
      if (e.key === 'ArrowLeft') prevBtn.click();
      if (e.key === 'ArrowRight') nextBtn.click();
      if (e.key === 'Escape') closeLightbox();
    };
    document.addEventListener('keydown', keydownHandler);

    setTimeout(() => lb.classList.add('active'), 10);
  };

  const openLightbox = (images, index = 0) => {
    if (lightboxActive || !images || images.length === 0) return;

    lightboxActive = true;
    currentImageIndex = index;
    currentStoreImages = images;

    if (document.getElementById('ppv-lightbox')) {
      document.getElementById('ppv-lightbox').remove();
    }

    createLightbox();
  };

  // ============================================================
  // HELPER FUNCTIONS
  // ============================================================

  const escapeHtml = (str = '') => {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    return String(str).replace(/[&<>"']/g, m => map[m]);
  };

  // ✅ UPDATE GLOBAL HEADER POINTS
  const updateGlobalPoints = (points) => {
    const globalPointsEl = document.getElementById('ppv-global-points');
    if (globalPointsEl) {
      globalPointsEl.textContent = points;
    }
  };

  // ✅ UPDATE GLOBAL HEADER REWARDS
  const updateGlobalRewards = (rewards) => {
    const globalRewardsEl = document.getElementById('ppv-global-rewards');
    if (globalRewardsEl) {
      globalRewardsEl.textContent = rewards;
    }
  };

  // ============================================================
  // 🎫 MODERN QR TOGGLE (v2.0)
  // ============================================================

  const initQRToggle = () => {
    const btn = document.querySelector(".ppv-btn-qr");
    const modal = document.getElementById("ppv-user-qr");
    const overlay = document.getElementById("ppv-qr-overlay");
    const closeBtn = document.querySelector(".ppv-qr-close");

    if (!btn || !modal || !overlay) {
      console.warn("⚠️ [QR] Elements not found");
      return;
    }

    const openQR = (e) => {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      modal.classList.add("show");
      overlay.classList.add("show");
      document.body.classList.add("qr-modal-open");
      document.body.style.overflow = "hidden";
      if (navigator.vibrate) navigator.vibrate(30);
      modal.offsetHeight;
    };

    const closeQR = () => {
      modal.classList.remove("show");
      overlay.classList.remove("show");
      document.body.classList.remove("qr-modal-open");
      document.body.style.overflow = "";
      if (navigator.vibrate) navigator.vibrate(10);
    };

    btn.addEventListener("click", openQR);
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) closeQR();
    });
    closeBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      closeQR();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && modal.classList.contains("show")) closeQR();
    });

    console.log("✅ [QR] Toggle initialized");
  };

  // ============================================================
  // POINT POLLING & SYNC
  // ============================================================

  // ============================================================
  // 📡 ABLY + FALLBACK POLLING - Real-time updates with polling fallback
  // ============================================================
  const initPointSync = () => {
    // 🧹 Always cleanup first to prevent multiple polling instances
    cleanupPolling();

    window.PPV_POLLING_ACTIVE = true;

    // 📡 Try Ably first for real-time updates
    if (boot.ably && boot.ably.key && window.Ably) {
      initAblySync();
    } else {
      console.log('🔄 [Sync] Ably not available, using polling fallback');
      initPollingSync();
    }
  };

  // 📡 ABLY REAL-TIME SYNC
  const initAblySync = () => {
    console.log('📡 [Ably] Initializing real-time sync...');
    console.log('📡 [Ably Debug] boot.uid:', boot.uid);

    const ably = new Ably.Realtime({ key: boot.ably.key });

    // Store for cleanup
    window.PPV_ABLY_INSTANCE = ably;

    // Subscribe to user's channel
    const channelName = 'user-' + boot.uid;
    console.log('📡 [Ably Debug] Subscribing to channel:', channelName);
    const channel = ably.channels.get(channelName);

    ably.connection.on('connected', () => {
      console.log('📡 [Ably] Connected to user channel:', channelName);
    });

    ably.connection.on('failed', (err) => {
      console.warn('📡 [Ably] Connection failed, falling back to polling', err);
      ably.close();
      initPollingSync();
    });

    // 🎯 Handle points update event
    channel.subscribe('points-update', (message) => {
      const data = message.data;
      console.log('📡 [Ably] Points update received:', data);

      if (data.success && data.points_added > 0) {
        // Show success toast
        if (window.ppvShowPointToast) {
          window.ppvShowPointToast('success', data.points_added, data.store_name || 'PunktePass');
        }

        // Update UI
        boot.points = data.total_points;
        updateGlobalPoints(data.total_points);

        // Update rewards count if provided
        if (data.total_rewards !== undefined) {
          updateGlobalRewards(data.total_rewards);
        }
      } else if (data.success === false) {
        // Show error toast
        console.log('📡 [Ably] Scan error received:', data.error_type, data.message);
        if (window.ppvShowPointToast) {
          window.ppvShowPointToast('error', 0, data.store_name || 'PunktePass', data.message);
        }
      }
    });

    // 🎁 Handle reward approved event
    channel.subscribe('reward-approved', (message) => {
      const data = message.data;
      console.log('📡 [Ably] Reward approved:', data);

      if (window.ppvShowPointToast) {
        window.ppvShowPointToast('reward', 0, data.store_name || 'PunktePass', data.reward_name || T.reward_redeemed);
      }

      // Refresh points (they decreased)
      if (data.new_points !== undefined) {
        boot.points = data.new_points;
        updateGlobalPoints(data.new_points);
      }
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
      if (window.PPV_ABLY_INSTANCE) {
        window.PPV_ABLY_INSTANCE.close();
      }
    });
  };

  // 🔄 FALLBACK POLLING SYNC
  const initPollingSync = () => {
    console.log('🔄 [Polling] Initializing point sync...');

    let lastPolledPoints = boot.points || 0;
    let lastShownErrorTimestamp = null;
    let isFirstPoll = true;

    const getCurrentInterval = () => {
      return document.hidden ? 30000 : 5000; // 30s inactive, 5s active
    };

    const pollPoints = async () => {
      if (!document.getElementById('ppv-dashboard-root')) {
        console.log('⏭️ [Polling] Dashboard not found, cleaning up');
        cleanupPolling();
        return;
      }

      if (window.PPV_POLLING_IN_PROGRESS) {
        console.log('⏭️ [Polling] Already in progress, skipping');
        return;
      }
      window.PPV_POLLING_IN_PROGRESS = true;

      try {
        const res = await fetch(API + 'user/points-poll', {
          method: 'GET',
          headers: { 'Content-Type': 'application/json' }
        });

        if (!res.ok) {
          if (res.status === 503) {
            console.warn('⚠️ [Polling] Server busy (503), will retry next interval');
          }
          return;
        }

        const data = await res.json();
        if (!data.success) return;

        if (data.points !== undefined && data.points !== lastPolledPoints) {
          const pointDiff = data.points - lastPolledPoints;

          if (pointDiff > 0) {
            if (window.ppvShowPointToast) {
              window.ppvShowPointToast('success', pointDiff, data.store || 'PunktePass');
            }
            lastShownErrorTimestamp = null;
          }

          lastPolledPoints = data.points;
          boot.points = data.points;
          updateGlobalPoints(data.points);
        }

        if (data.error_type && data.error_timestamp) {
          if (isFirstPoll) {
            lastShownErrorTimestamp = data.error_timestamp;
            console.log(`⏭️ [Polling] First poll: Initializing error tracking`);
          } else if (data.error_timestamp !== lastShownErrorTimestamp) {
            if (window.ppvShowPointToast) {
              const errorStore = data.error_store || data.store || 'PunktePass';
              const errorKey = 'err_' + data.error_type;
              const translatedError = T[errorKey] || data.error_message || T.err_duplicate_scan;
              window.ppvShowPointToast('error', 0, errorStore, translatedError);
            }
            lastShownErrorTimestamp = data.error_timestamp;
          }
        } else {
          lastShownErrorTimestamp = null;
        }

        if (isFirstPoll) isFirstPoll = false;
      } catch (e) {
        console.warn(`⚠️ [Polling] Error:`, e.message);
      } finally {
        window.PPV_POLLING_IN_PROGRESS = false;
      }
    };

    const startPolling = () => {
      if (window.PPV_POLL_INTERVAL_ID) clearInterval(window.PPV_POLL_INTERVAL_ID);
      const interval = getCurrentInterval();
      console.log(`🔄 [Polling] Starting with ${interval/1000}s interval`);
      window.PPV_POLL_INTERVAL_ID = setInterval(pollPoints, interval);
    };

    window.PPV_VISIBILITY_HANDLER = () => startPolling();
    document.addEventListener('visibilitychange', window.PPV_VISIBILITY_HANDLER);

    startPolling();
    window.addEventListener('beforeunload', cleanupPolling);
  };

  /**
   * 🏪 RENDER STORE CARD - FULLY TRANSLATED ✅
   * 🎨 MODERN ICONS - All Remix Icon ✅
   */
  const renderStoreCard = (store) => {
    // ✅ FIX: Better logo fallback - check for valid URL
    const defaultLogo = boot.assets?.store_default || '/wp-content/plugins/punktepass/assets/img/store-default-logo.webp';
    const logo = (store.logo && store.logo !== 'null' && store.logo.startsWith('http'))
        ? store.logo
        : defaultLogo;

    const distanceBadge = store.distance_km !== null ? `<span class="ppv-distance-badge"><i class="ri-map-pin-distance-line"></i> ${store.distance_km} ${T.km}</span>` : '';
    const statusBadge = store.open_now
      ? `<span class="ppv-status-badge ppv-open"><i class="ri-checkbox-blank-circle-fill"></i> ${T.open}</span>`
      : `<span class="ppv-status-badge ppv-closed"><i class="ri-checkbox-blank-circle-fill"></i> ${T.closed}</span>`;

    // Gallery - ✅ OPTIMIZED: Added loading="lazy" for performance
    const galleryHTML = store.gallery && store.gallery.length > 0
      ? `<div class="ppv-gallery-thumbs">
           ${store.gallery.map((img, idx) => `
             <img src="${img}" alt="${T.gallery_label}" class="ppv-gallery-thumb" data-index="${idx}" loading="lazy">
           `).join('')}
         </div>`
      : '';

    // Social media
    const socialHTML = (store.social?.facebook || store.social?.instagram || store.social?.tiktok)
      ? `<div class="ppv-social-links">
           ${store.social?.facebook ? `<a href="${escapeHtml(store.social.facebook)}" target="_blank" rel="noopener" class="ppv-social-btn ppv-fb"><i class="ri-facebook-circle-fill"></i></a>` : ''}
           ${store.social?.instagram ? `<a href="${escapeHtml(store.social.instagram)}" target="_blank" rel="noopener" class="ppv-social-btn ppv-ig"><i class="ri-instagram-fill"></i></a>` : ''}
           ${store.social?.tiktok ? `<a href="${escapeHtml(store.social.tiktok)}" target="_blank" rel="noopener" class="ppv-social-btn ppv-tk"><i class="ri-tiktok-fill"></i></a>` : ''}
         </div>`
      : '';

    // Hours
    const hoursHTML = store.open_hours_today
      ? `<span class="ppv-hours"><i class="ri-time-line"></i> ${store.open_hours_today}</span>`
      : '';

    // ✅ REWARDS - FULLY TRANSLATED - MODERN ICONS ✅
    const rewardsHTML = store.rewards && store.rewards.length > 0 ? `
      <div class="ppv-store-rewards">
        <div class="ppv-rewards-header">
          <h5 style="margin: 0; font-weight: 600; color: #00e6ff;"><i class="ri-gift-line"></i> ${T.rewards_title}</h5>
        </div>
        <div class="ppv-rewards-list">
          ${store.rewards.map((r, idx) => {
            let rewardText = '';
            if (r.action_type === 'discount_percent') {
              rewardText = `${r.action_value}${T.discount_percent_text}`;
            } else if (r.action_type === 'discount_fixed') {
              rewardText = `€${r.action_value} ${T.discount_fixed_text}`;
            } else {
              rewardText = `${r.action_value} ${r.currency || 'pont'}`;
            }

            return `
            <div class="ppv-reward-mini">
              <div class="ppv-reward-header">
                <strong>${escapeHtml(r.title)}</strong>
                <span class="ppv-reward-badge">${r.required_points} pont</span>
              </div>
              <div class="ppv-reward-details">
                <div class="ppv-reward-row">
                  <span class="ppv-reward-label"><i class="ri-map-pin-line"></i> ${T.reward_label_required}</span>
                  <span class="ppv-reward-value"><strong>${r.required_points} pont</strong></span>
                </div>
                <div class="ppv-reward-row">
                  <span class="ppv-reward-label"><i class="ri-gift-fill"></i> ${T.reward_label_reward}</span>
                  <span class="ppv-reward-value"><strong style="color: #34d399;">${rewardText}</strong></span>
                </div>
                <div class="ppv-reward-row">
                  <span class="ppv-reward-label"><i class="ri-coins-line"></i> ${T.reward_per_scan}</span>
                  <span class="ppv-reward-value"><strong style="color:#00e6ff;">+${r.points_given || 0} pont</strong></span>
                </div>
              </div>
            </div>
            `;
          }).join('')}
        </div>
      </div>
    ` : '';

    // ============================================================
    // 📢 CAMPAIGNS HTML - FULLY TRANSLATED ✅ - MODERN ICONS ✅
    // ============================================================
    const campaignsHTML = store.campaigns && store.campaigns.length > 0 ? `
      <div class="ppv-store-campaigns">
        <h5 style="margin: 12px 0 8px 0; font-weight: 600; color: #34d399;"><i class="ri-megaphone-line"></i> ${T.campaigns_title}</h5>
        <div class="ppv-campaigns-list">
          ${store.campaigns.map((c, idx) => {
            // 💰 PER SCAN PONTOK KISZÁMÍTÁSA
            let scanPoints = 1; // Base: 1 pont per scan
            let campaignReward = '';
            let currencySymbol = '€'; // Default: Euro

            // 🌍 ORSZÁG-SPECIFIKUS PÉNZNEM
            if (store.country === 'RO') {
              currencySymbol = 'RON';
            } else if (store.country === 'HU') {
              currencySymbol = 'Ft';
            }

            if (c.campaign_type === 'points') {
              if (c.multiplier > 1) {
                scanPoints = c.multiplier; // Pl. 2x = 2 pont per scan
              }
              if (c.extra_points > 0) {
                scanPoints += c.extra_points; // Pl. +10 pont
              }
              campaignReward = `${scanPoints}${T.points_multiplier_text}`;
            } else if (c.campaign_type === 'discount') {
              campaignReward = `${c.discount_percent}${T.discount_percent_text}`;
              scanPoints = 1; // Nem számít
            } else if (c.campaign_type === 'fixed') {
              const amount = c.min_purchase || c.fixed_amount || 0;
              campaignReward = `${amount}${T.fixed_text}`;
              scanPoints = 1; // Nem számít
            } else if (c.campaign_type === 'free_product') {
              campaignReward = `<i class="ri-gift-fill"></i> ${escapeHtml(c.free_product || T.free_product_text)}`;
              if (c.free_product_value > 0) {
                campaignReward += ` (${c.free_product_value}${currencySymbol})`;
              }
              scanPoints = 1;
            } else {
              // ✅ Check if free_product exists (even if campaign_type is empty)
              if (c.free_product && c.free_product.trim() !== '') {
                campaignReward = `<i class="ri-gift-fill"></i> ${escapeHtml(c.free_product)}`;
                if (c.free_product_value > 0) {
                  campaignReward += ` (${c.free_product_value}${currencySymbol})`;
                }
                scanPoints = 1;
              } else {
                // Real fallback for unknown types
                const typeLabel = c.campaign_type ? ` (${c.campaign_type})` : '';
                campaignReward = `<i class="ri-lightbulb-line"></i> ${T.special_offer}${typeLabel}`;
                scanPoints = 1;
                console.warn("⚠️ Unknown campaign type:", c.campaign_type);
              }
            }

            return `
            <div class="ppv-campaign-mini" key="${idx}">
              <!-- KAMPÁNY FEJLÉC -->
              <div class="ppv-campaign-header" style="margin-bottom: 10px;">
                <strong style="font-size: 15px;">${escapeHtml(c.title)}</strong>
              </div>

              <!-- KAMPÁNY ADATOK -->
              <div class="ppv-campaign-details">
                <!-- 📅 DÁTUM -->
                <div class="ppv-reward-row">
                  <span class="ppv-reward-label"><i class="ri-calendar-line"></i> ${T.reward_label_date}</span>
                  <span class="ppv-reward-value">${c.start_date.substring(0, 10)} - ${c.end_date.substring(0, 10)}</span>
                </div>

                <!-- 📍 SZÜKSÉGES PONT (ha van) -->
                ${c.required_points && c.required_points > 0 ? `
                <div class="ppv-reward-row">
                  <span class="ppv-reward-label"><i class="ri-map-pin-line"></i> ${T.reward_label_required}</span>
                  <span class="ppv-reward-value"><strong style="color: #fbbf24;">${c.required_points} pont</strong></span>
                </div>
                ` : ''}

                ${c.campaign_type !== 'points' ? `
                <div class="ppv-reward-row">
                  <span class="ppv-reward-label"><i class="ri-coins-line"></i> ${T.reward_per_scan}</span>
                  <span class="ppv-reward-value"><strong style="color: #00e6ff; font-size: 14px;">+${c.points_given || 1} pont</strong></span>
                </div>
                ` : ''}

                <!-- 🎁 JUTALOM -->
                <div class="ppv-reward-row" style="border-top: 1px solid rgba(52, 211, 153, 0.2); padding-top: 8px; margin-top: 8px;">
                  <span class="ppv-reward-label"><i class="ri-gift-fill"></i> ${T.reward_label_reward}</span>
                  <span class="ppv-reward-value"><strong style="color: #34d399;">${campaignReward}</strong></span>
                </div>

                <!-- DESCRIPTION (ha van) -->
                ${c.description ? `
                <div class="ppv-reward-row" style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                  <p style="margin: 0; font-size: 13px; color: rgba(255, 255, 255, 0.7);">
                    <i class="ri-file-text-line"></i> ${escapeHtml(c.description)}
                  </p>
                </div>
                ` : ''}
              </div>
            </div>
            `;
          }).join('')}
        </div>
      </div>
    ` : '';

    // ============================================================
    // 👑 VIP BONUS SECTION - COMPACT GRID VERSION
    // ============================================================
    const vipHTML = store.vip ? (() => {
      const vip = store.vip;
      const rows = [];

      // 1️⃣ FIX PONT BÓNUSZ - compact row
      if (vip.fix && vip.fix.enabled) {
        rows.push(`
          <div class="ppv-vip-row">
            <span class="ppv-vip-label"><i class="ri-add-circle-line"></i> ${T.vip_fix_title}</span>
            <span class="ppv-vip-grid">
              <span class="bronze" title="${T.vip_bronze}">+${vip.fix.bronze}</span>
              <span class="silver" title="${T.vip_silver}">+${vip.fix.silver}</span>
              <span class="gold" title="${T.vip_gold}">+${vip.fix.gold}</span>
              <span class="platinum" title="${T.vip_platinum}">+${vip.fix.platinum}</span>
            </span>
          </div>
        `);
      }

      // 2️⃣ STREAK BÓNUSZ - compact row
      if (vip.streak && vip.streak.enabled) {
        let streakValues = '';
        if (vip.streak.type === 'double') {
          streakValues = `<span class="ppv-vip-special">${T.vip_double}</span>`;
        } else if (vip.streak.type === 'triple') {
          streakValues = `<span class="ppv-vip-special">${T.vip_triple}</span>`;
        } else {
          streakValues = `
            <span class="bronze" title="${T.vip_bronze}">+${vip.streak.bronze}</span>
            <span class="silver" title="${T.vip_silver}">+${vip.streak.silver}</span>
            <span class="gold" title="${T.vip_gold}">+${vip.streak.gold}</span>
            <span class="platinum" title="${T.vip_platinum}">+${vip.streak.platinum}</span>
          `;
        }
        rows.push(`
          <div class="ppv-vip-row">
            <span class="ppv-vip-label"><i class="ri-fire-line"></i> ${vip.streak.count}. scan</span>
            <span class="ppv-vip-grid">${streakValues}</span>
          </div>
        `);
      }

      // 3️⃣ DAILY BÓNUSZ - compact row
      if (vip.daily && vip.daily.enabled) {
        rows.push(`
          <div class="ppv-vip-row">
            <span class="ppv-vip-label"><i class="ri-sun-line"></i> ${T.vip_daily_title}</span>
            <span class="ppv-vip-grid">
              <span class="bronze" title="${T.vip_bronze}">+${vip.daily.bronze}</span>
              <span class="silver" title="${T.vip_silver}">+${vip.daily.silver}</span>
              <span class="gold" title="${T.vip_gold}">+${vip.daily.gold}</span>
              <span class="platinum" title="${T.vip_platinum}">+${vip.daily.platinum}</span>
            </span>
          </div>
        `);
      }

      return rows.length ? `
        <div class="ppv-store-vip-compact">
          <div class="ppv-vip-header-row">
            <span class="ppv-vip-title"><i class="ri-vip-crown-fill"></i> ${T.vip_title}</span>
            <span class="ppv-vip-levels-header">
              <span class="bronze" title="${T.vip_bronze}"><i class="ri-medal-line"></i></span>
              <span class="silver" title="${T.vip_silver}"><i class="ri-medal-line"></i></span>
              <span class="gold" title="${T.vip_gold}"><i class="ri-medal-fill"></i></span>
              <span class="platinum" title="${T.vip_platinum}"><i class="ri-vip-crown-fill"></i></span>
            </span>
          </div>
          ${rows.join('')}
        </div>
      ` : '';
    })() : '';

    return `
      <div class="ppv-store-card-enhanced" data-store-id="${store.id}">
        <div class="ppv-store-header">
          <img src="${logo}" alt="Logo" class="ppv-store-logo">
          <div class="ppv-store-info">
            <h4>${escapeHtml(store.company_name || store.name)}</h4>
            <div class="ppv-store-badges">
              ${statusBadge}
              ${distanceBadge}
            </div>
            <div class="ppv-store-preview">
              ${store.rewards && store.rewards.length > 0 ? `
                <span class="ppv-preview-tag ppv-reward-tag">
                  <i class="ri-gift-line"></i> ${store.rewards.length} ${T.rewards_preview}
                </span>
              ` : ''}
              ${store.campaigns && store.campaigns.length > 0 ? `
                <span class="ppv-preview-tag ppv-campaign-tag">
                  <i class="ri-megaphone-line"></i> ${store.campaigns.length} ${T.campaigns_preview}
                </span>
              ` : ''}
              ${store.vip ? `
                <span class="ppv-preview-tag ppv-vip-tag">
                  <i class="ri-vip-crown-fill"></i> VIP
                </span>
              ` : ''}
            </div>
          </div>
          <button class="ppv-toggle-btn" type="button">
            <i class="ri-arrow-down-s-line"></i>
          </button>
        </div>

        <div class="ppv-store-details">
          ${galleryHTML}
          ${socialHTML}
          <div class="ppv-store-meta">
            ${hoursHTML}
            <span class="ppv-address"><i class="ri-map-pin-line"></i> ${escapeHtml(store.address || '')} ${store.plz || ''} ${store.city || ''}</span>
          </div>
          ${rewardsHTML}
          ${campaignsHTML}
          ${vipHTML}
          <div class="ppv-store-actions">
            <button class="ppv-action-btn ppv-route" data-lat="${store.latitude}" data-lng="${store.longitude}" type="button">
              <i class="ri-route-fill"></i> ${T.route}
            </button>
            ${store.phone ? `<a href="tel:${store.phone}" class="ppv-action-btn ppv-call"><i class="ri-phone-fill"></i> ${T.call}</a>` : ''}
            ${store.website ? `<a href="${store.website}" target="_blank" rel="noopener" class="ppv-action-btn ppv-web"><i class="ri-global-line"></i> ${T.website}</a>` : ''}
          </div>
        </div>
      </div>
    `;
  };

  // ============================================================
  // SLIDER - Uses global PPV_SLIDER_INITIALIZED and PPV_SLIDER_HANDLER
  // ============================================================
  let sliderTimeout = null;

  const initDistanceSlider = (sliderHTML, userLat, userLng, currentDistance = 10) => {
    if (window.PPV_SLIDER_INITIALIZED) {
      console.log("⏸️ [Slider] Already initialized");
      return;
    }
    window.PPV_SLIDER_INITIALIZED = true;

    // Remove old handler if exists
    if (window.PPV_SLIDER_HANDLER) {
      document.removeEventListener('input', window.PPV_SLIDER_HANDLER);
    }

    // Create new handler and store globally
    window.PPV_SLIDER_HANDLER = async (e) => {
      if (e.target.id !== 'ppv-distance-slider') return;

      const newDistance = parseInt(e.target.value, 10);
      window.PPV_CURRENT_DISTANCE = newDistance; // ✅ FIX: Track current value globally
      const valueSpan = document.getElementById('ppv-distance-value');
      if (valueSpan) valueSpan.textContent = newDistance;

      clearTimeout(sliderTimeout);
      sliderTimeout = setTimeout(async () => {
        // ✅ FIX: Prevent concurrent slider fetches
        if (window.PPV_SLIDER_FETCH_IN_PROGRESS) {
          console.log('⏭️ [Slider] Fetch already in progress, skipping');
          return;
        }
        window.PPV_SLIDER_FETCH_IN_PROGRESS = true;

        let newUrl = API + 'stores/list-optimized';
        if (userLat && userLng) {
          newUrl += `?lat=${userLat}&lng=${userLng}&max_distance=${newDistance}`;
        }

        try {
          const res = await fetch(newUrl);
          const newStores = await res.json();

          const dynamicSliderHTML = `
            <div class="ppv-distance-filter">
              <label><i class="ri-ruler-line"></i> ${T.distance_label}: <span id="ppv-distance-value">${newDistance}</span> km</label>
              <input type="range" id="ppv-distance-slider" min="10" max="1000" value="${newDistance}" step="10">
              <div class="ppv-distance-labels"><span>10 km</span><span>1000 km</span></div>
            </div>
          `;

          const storeCards = newStores.map(renderStoreCard).join('');
          const storeListDiv = document.getElementById('ppv-store-list');
          if (storeListDiv) {
            storeListDiv.innerHTML = dynamicSliderHTML + storeCards;
            // ✅ FIX: Only attach store listeners, route is already handled there
            attachStoreListeners();
          }

          console.log("✅ [Slider] Stores updated");
        } catch (err) {
          console.error("❌ Filter error:", err);
        } finally {
          // ✅ FIX: Always reset flag after fetch completes
          window.PPV_SLIDER_FETCH_IN_PROGRESS = false;
        }
      }, 500);
    };

    document.addEventListener('input', window.PPV_SLIDER_HANDLER);
    console.log("✅ [Slider] Initialized");
  };

  // ============================================================
  // COMBINED LISTENER - TOGGLE + ROUTE + ACTIONS ✅
  // ============================================================
  const attachStoreListeners = () => {
    const storeListEl = document.getElementById('ppv-store-list');
    if (!storeListEl) return;

    // Remove old listeners by cloning
    const newStoreList = storeListEl.cloneNode(true);
    storeListEl.parentNode.replaceChild(newStoreList, storeListEl);

    // ✅ ONE SINGLE LISTENER - Összes gomb kezelése
    document.getElementById('ppv-store-list').addEventListener('click', (e) => {

      // 1️⃣ TOGGLE - Boltkártya kinyitása/bezárása
      const storeHeader = e.target.closest('.ppv-store-header');
      if (storeHeader) {
        const card = storeHeader.closest('.ppv-store-card-enhanced');
        if (card) {
          const details = card.querySelector('.ppv-store-details');
          const toggleBtn = card.querySelector('.ppv-toggle-btn');

          if (details && toggleBtn) {
            details.classList.toggle('expanded');
            toggleBtn.classList.toggle('active');
            console.log("✅ [Toggle] Store expanded/collapsed");
          }
        }
        return;
      }

      // 2️⃣ ROUTE - Útvonal megnyitása
      const routeBtn = e.target.closest('.ppv-route');
      if (routeBtn) {
        const lat = routeBtn.getAttribute('data-lat');
        const lng = routeBtn.getAttribute('data-lng');

        if (!lat || !lng) {
          console.error("❌ [Route] No coordinates");
          return;
        }

        // 🌍 Google Maps - Default
        const googleMapsUrl = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;

        // 📱 Mobile: Apple Maps fallback
        const appleMapsUrl = `maps://maps.apple.com/?daddr=${lat},${lng}`;

        if (navigator.userAgent.includes('iPhone') || navigator.userAgent.includes('iPad')) {
          window.open(appleMapsUrl, '_blank');
        } else {
          window.open(googleMapsUrl, '_blank');
        }

        console.log("✅ [Route] Opening maps with coords:", lat, lng);
        if (navigator.vibrate) navigator.vibrate(20);
        return;
      }

      // 3️⃣ GALLERY - Galériakép lightbox
      const galleryThumb = e.target.closest('.ppv-gallery-thumb');
      if (galleryThumb) {
        const card = galleryThumb.closest('.ppv-store-card-enhanced');
        const images = Array.from(card.querySelectorAll('.ppv-gallery-thumb')).map(img => img.src);
        const index = Array.from(card.querySelectorAll('.ppv-gallery-thumb')).indexOf(galleryThumb);
        openLightbox(images, index);
        console.log("✅ [Gallery] Lightbox opened");
        return;
      }
    });

    console.log("✅ [Listeners] All listeners attached (toggle + route + gallery)");
  };

  // ============================================================
  // ❌ REMOVED: attachRouteListener() - Route handling is already in attachStoreListeners()
  // This was causing duplicate listeners and potential API loops!
  // ============================================================

  // ============================================================
  // LOAD STORES - SIMPLE & RELIABLE 🚀
  // ============================================================
  const initStores = async () => {
    const box = document.getElementById('ppv-store-list');
    if (!box) {
      console.log('⏭️ [Stores] No store list element found');
      return;
    }

    // Prevent duplicate loading
    if (window.PPV_STORES_LOADING) {
      console.log('⏭️ [Stores] Already loading, skipping');
      return;
    }
    window.PPV_STORES_LOADING = true;

    const startTime = performance.now();
    console.log('🏪 [Stores] Starting store load...');

    // Show loading state
    box.innerHTML = `<p class="ppv-loading"><i class="ri-loader-4-line ri-spin"></i> ${T.loading}</p>`;

    let userLat = null;
    let userLng = null;

    // 🚀 Try cached location first (instant!)
    const cachedLat = localStorage.getItem('ppv_user_lat');
    const cachedLng = localStorage.getItem('ppv_user_lng');
    if (cachedLat && cachedLng) {
      userLat = parseFloat(cachedLat);
      userLng = parseFloat(cachedLng);
      console.log('⚡ [Geo] Using cached position:', userLat.toFixed(4), userLng.toFixed(4));
    }

    // 1️⃣ Start geo request in background (non-blocking)
    // ✅ FIX: Use longer timeout when no cached location (first-time users need time for permission prompt)
    const geoTimeoutMs = (cachedLat && cachedLng) ? 2000 : 8000;
    const geoPromise = new Promise((resolve) => {
      if (!navigator.geolocation) {
        resolve(null);
        return;
      }
      const timeout = setTimeout(() => {
        console.log(`⏱️ [Geo] Timeout after ${geoTimeoutMs/1000}s`);
        resolve(null);
      }, geoTimeoutMs);

      navigator.geolocation.getCurrentPosition(
        (p) => {
          clearTimeout(timeout);
          // Cache for next time
          localStorage.setItem('ppv_user_lat', p.coords.latitude.toString());
          localStorage.setItem('ppv_user_lng', p.coords.longitude.toString());
          console.log('📍 [Geo] Fresh position cached:', p.coords.latitude.toFixed(4), p.coords.longitude.toFixed(4));
          resolve(p);
        },
        (err) => {
          clearTimeout(timeout);
          console.log('⚠️ [Geo] Error:', err.code, err.message);
          resolve(null);
        },
        { timeout: geoTimeoutMs, maximumAge: 600000, enableHighAccuracy: false }
      );
    });

    // 2️⃣ Fetch stores immediately with cached location (or without)
    try {
      let url = API + 'stores/list-optimized';
      if (userLat && userLng) {
        url += `?lat=${userLat}&lng=${userLng}&max_distance=10`;
      }

      console.log('🌐 [Stores] Fetching:', url);
      const res = await fetch(url, { cache: "no-store" });

      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }

      const stores = await res.json();
      console.log('✅ [Stores] Loaded', stores?.length || 0, 'stores in', (performance.now() - startTime).toFixed(0), 'ms');

      // Render stores
      if (!Array.isArray(stores) || stores.length === 0) {
        box.innerHTML = `<p class="ppv-no-stores"><i class="ri-store-3-line"></i> ${T.no_stores}</p>`;
      } else {
        renderStoreList(box, stores, userLat, userLng);
      }

      // 3️⃣ If we didn't have cached location, wait for geo and re-fetch
      if (!cachedLat && !cachedLng) {
        const freshPos = await geoPromise;
        if (freshPos?.coords) {
          const newLat = freshPos.coords.latitude;
          const newLng = freshPos.coords.longitude;
          console.log('🔄 [Stores] Re-fetching with fresh geo:', newLat.toFixed(4), newLng.toFixed(4));

          // ✅ FIX: Use current slider distance value, not hardcoded 10
          const currentDist = window.PPV_CURRENT_DISTANCE || 10;
          const newUrl = API + `stores/list-optimized?lat=${newLat}&lng=${newLng}&max_distance=${currentDist}`;

          try {
            const newRes = await fetch(newUrl, { cache: "no-store" });
            console.log('📡 [Stores] Re-fetch response:', newRes.status);

            if (newRes.ok) {
              const newStores = await newRes.json();
              // Log distances from API
              console.log('📦 [Stores] Got', newStores?.length || 0, 'stores. First 3 distances:',
                newStores?.slice(0,3).map(s => `${s.company_name||s.name||'?'}: ${s.distance_km}km`).join(', '));

              if (Array.isArray(newStores) && newStores.length > 0) {
                // ✅ FIX: Get fresh DOM reference
                const freshBox = document.getElementById('ppv-store-list');
                console.log('🎯 [DOM] freshBox found:', !!freshBox, 'children before:', freshBox?.children?.length);

                if (freshBox) {
                  // ✅ FIX: Directly update innerHTML instead of calling renderStoreList
                  const sliderHTML = `
                    <div class="ppv-distance-filter">
                      <label><i class="ri-ruler-line"></i> ${T.distance_label}: <span id="ppv-distance-value">${currentDist}</span> km</label>
                      <input type="range" id="ppv-distance-slider" min="10" max="1000" value="${currentDist}" step="10">
                      <div class="ppv-distance-labels"><span>10 km</span><span>1000 km</span></div>
                    </div>
                  `;
                  const cardsHTML = newStores.map(renderStoreCard).join('');
                  freshBox.innerHTML = sliderHTML + cardsHTML;
                  console.log('🎯 [DOM] innerHTML updated, children after:', freshBox?.children?.length);

                  initDistanceSlider(sliderHTML, newLat, newLng, currentDist);
                  attachStoreListeners();
                  console.log('✅ [Stores] Re-rendered with distance sorting');
                } else {
                  console.warn('⚠️ [Stores] Store list element not found for re-render');
                }
              } else {
                console.warn('⚠️ [Stores] No stores returned from re-fetch');
              }
            }
          } catch (fetchErr) {
            console.error('❌ [Stores] Re-fetch failed:', fetchErr.message);
          }
        } else {
          // ✅ FIX: Geo failed on first try - set up delayed retry
          console.log('⏳ [Geo] First attempt failed, scheduling retry in 5s...');
          setTimeout(async () => {
            // Check if we got location in the meantime (from another source)
            const retryLat = localStorage.getItem('ppv_user_lat');
            const retryLng = localStorage.getItem('ppv_user_lng');
            if (retryLat && retryLng) {
              console.log('✅ [Geo] Found cached location on retry');
              const currentDist = window.PPV_CURRENT_DISTANCE || 10;
              const retryUrl = API + `stores/list-optimized?lat=${retryLat}&lng=${retryLng}&max_distance=${currentDist}`;
              try {
                const retryRes = await fetch(retryUrl, { cache: "no-store" });
                if (retryRes.ok) {
                  const retryStores = await retryRes.json();
                  const currentBox = document.getElementById('ppv-store-list');
                  if (currentBox && Array.isArray(retryStores) && retryStores.length > 0) {
                    renderStoreList(currentBox, retryStores, parseFloat(retryLat), parseFloat(retryLng), true);
                    console.log('✅ [Stores] Re-rendered on retry with distance sorting');
                  }
                }
              } catch (e) {
                console.log('⚠️ [Stores] Retry fetch failed:', e.message);
              }
              return;
            }

            // Try geo again
            if (navigator.geolocation) {
              console.log('🔄 [Geo] Retrying geolocation...');
              navigator.geolocation.getCurrentPosition(
                async (p) => {
                  localStorage.setItem('ppv_user_lat', p.coords.latitude.toString());
                  localStorage.setItem('ppv_user_lng', p.coords.longitude.toString());
                  console.log('📍 [Geo] Retry succeeded:', p.coords.latitude.toFixed(4), p.coords.longitude.toFixed(4));

                  const currentDist = window.PPV_CURRENT_DISTANCE || 10;
                  const retryUrl = API + `stores/list-optimized?lat=${p.coords.latitude}&lng=${p.coords.longitude}&max_distance=${currentDist}`;
                  try {
                    const retryRes = await fetch(retryUrl, { cache: "no-store" });
                    if (retryRes.ok) {
                      const retryStores = await retryRes.json();
                      const currentBox = document.getElementById('ppv-store-list');
                      if (currentBox && Array.isArray(retryStores) && retryStores.length > 0) {
                        renderStoreList(currentBox, retryStores, p.coords.latitude, p.coords.longitude, true);
                        console.log('✅ [Stores] Re-rendered on geo retry with distance sorting');
                      }
                    }
                  } catch (e) {
                    console.log('⚠️ [Stores] Retry fetch failed:', e.message);
                  }
                },
                (err) => console.log('⚠️ [Geo] Retry also failed:', err.message),
                { timeout: 10000, maximumAge: 60000, enableHighAccuracy: false }
              );
            }
          }, 5000);
        }
      }

    } catch (e) {
      console.error('❌ [Stores] Load failed:', e.message);
      box.innerHTML = `<p class="ppv-error"><i class="ri-error-warning-line"></i> ${T.no_stores}</p>`;
    }

    window.PPV_STORES_LOADING = false;
    console.log('🏁 [Stores] Done in', (performance.now() - startTime).toFixed(0), 'ms');
  };

  // Helper function to render store list (avoids duplicate code)
  // ✅ FIX: Preserve current slider value instead of always resetting to 10
  const renderStoreList = (box, stores, userLat, userLng, preserveSliderValue = false) => {
    const currentDistance = preserveSliderValue ? window.PPV_CURRENT_DISTANCE : 10;
    const sliderHTML = `
      <div class="ppv-distance-filter">
        <label><i class="ri-ruler-line"></i> ${T.distance_label}: <span id="ppv-distance-value">${currentDistance}</span> km</label>
        <input type="range" id="ppv-distance-slider" min="10" max="1000" value="${currentDistance}" step="10">
        <div class="ppv-distance-labels"><span>10 km</span><span>1000 km</span></div>
      </div>
    `;
    box.innerHTML = sliderHTML + stores.map(renderStoreCard).join('');
    initDistanceSlider(sliderHTML, userLat, userLng, currentDistance);
    attachStoreListeners();
  };

  // ============================================================
  // RENDER HTML
  // ============================================================

  root.innerHTML = `
    <div class="ppv-dashboard-netto">
      <div class="ppv-dashboard-inner">

        <section class="ppv-qr-banner" id="ppv-show-qr">
          <div class="ppv-qr-text">
            <i class="ri-qr-code-line"></i>
            <div>
              <h3>${T.collect_here}</h3>
              <p>${T.show_in_store}</p>
            </div>
          </div>
          <button class="ppv-btn-qr" type="button">
            <i class="ri-download-line"></i> ${T.show_qr}
          </button>
        </section>

        <div class="ppv-qr-overlay" id="ppv-qr-overlay"></div>

        <div id="ppv-user-qr" class="ppv-user-qr">
          <button class="ppv-qr-close" type="button">
            <i class="ri-close-line"></i>
          </button>
          <img src="${boot.qr_url || ''}" alt="My QR Code" class="ppv-qr-image">
          <p class="qr-info">
            <strong>${T.show_code_tip}</strong>
          </p>
          <div class="ppv-qr-instructions">
            <strong><i class="ri-lightbulb-line"></i> ${T.how_to_use}:</strong><br>
            ${T.qr_instruction_1}<br>
            ${T.qr_instruction_2}<br>
            ${T.qr_instruction_3}
          </div>
        </div>

        <section class="ppv-store-section">
          <h3 class="ppv-section-title"><i class="ri-store-2-fill"></i> ${T.nearby}</h3>
          <div id="ppv-store-list" class="ppv-store-list"></div>
        </section>
      </div>
    </div>
  `;

  // ============================================================
  // INITIALIZATION - Direct call, no interval needed 🚀
  // ============================================================
  initQRToggle();
  initPointSync();

  // DOM is already rendered above, call initStores directly
  // Using requestAnimationFrame to ensure DOM is painted
  requestAnimationFrame(() => {
    initStores();
  });

  // ============================================================
  // TOAST - MODERN ICONS ✅
  // ============================================================

  window.ppvShowPointToast = function(type = "success", points = 1, store = "PunktePass", errorMessage = "") {
    console.log("🔔 [ppvShowPointToast] Called with:", { type, points, store, errorMessage });

    // Remove existing toast if present
    const existingToast = document.querySelector(".ppv-point-toast");
    if (existingToast) {
      console.log("🗑️ [ppvShowPointToast] Removing existing toast");
      existingToast.classList.remove("show");
      setTimeout(() => existingToast.remove(), 200);
    }

    // Function to create new toast
    const createToast = () => {
      console.log("✨ [ppvShowPointToast] Creating new toast");

      const L = {
        de: { dup: "Heute bereits gescannt", err: "Offline", pend: "Verbindung...", add: "Punkt(e) von", from: "von" },
        hu: { dup: "Ma már", err: "Offline", pend: "Kapcsolódás...", add: "pont a", from: "-tól/-től" },
        ro: { dup: "Astăzi", err: "Offline", pend: "Conectare...", add: "punct de la", from: "de la" }
      }[lang] || L.de;

      let icon = '<i class="ri-emotion-happy-line"></i>', text = "";
      if (type === "duplicate") {
        icon = '<i class="ri-error-warning-line"></i>';
        text = L.dup;
      }
      else if (type === "error") {
        icon = '<i class="ri-close-circle-line"></i>';
        // Show error message with store name
        text = errorMessage ? `${errorMessage} ${L.from} <strong>${store}</strong>` : L.err;
      }
      else if (type === "pending") {
        icon = '<i class="ri-time-line ri-spin"></i>';
        text = L.pend;
      }
      else {
        text = `+${points} ${L.add} <strong>${store}</strong>`;
      }

      console.log("📝 [ppvShowPointToast] Toast text:", text);

      const toast = document.createElement("div");
      toast.className = "ppv-point-toast " + type;
      toast.innerHTML = `<div class="ppv-point-toast-inner"><div class="ppv-toast-icon">${icon}</div><div class="ppv-toast-text">${text}</div></div>`;
      document.body.appendChild(toast);
      console.log("➕ [ppvShowPointToast] Toast appended to body");

      setTimeout(() => {
        toast.classList.add("show");
        console.log("👁️ [ppvShowPointToast] Toast shown");
      }, 30);

      if (type === "success" && navigator.vibrate) navigator.vibrate(40);

      setTimeout(() => {
        toast.classList.remove("show");
        setTimeout(() => {
          toast.remove();
          console.log("🗑️ [ppvShowPointToast] Toast removed after timeout");
        }, 400);
      }, type === "success" ? 6500 : 4500);
    };

    // Wait for old toast to be removed before creating new one
    if (existingToast) {
      console.log("⏳ [ppvShowPointToast] Waiting 250ms for old toast removal");
      setTimeout(createToast, 250);
    } else {
      createToast();
    }
  };

  console.log("✅ Dashboard initialized");
}

// 🚀 Initialize on DOMContentLoaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initUserDashboard);
} else {
  initUserDashboard();
}

// 🧹 Turbo: Clean up BEFORE navigating away (prevents multiple polling instances)
document.addEventListener('turbo:before-visit', function() {
  console.log('🧹 [Turbo] Before visit - cleaning up polling');
  cleanupPolling();
});

// 🚀 Turbo: Reset flag before rendering new page
document.addEventListener('turbo:before-render', function() {
  const root = document.getElementById('ppv-dashboard-root');
  if (root) {
    root.dataset.initialized = 'false';
  }
});

// 🚀 Turbo: Re-initialize after navigation (only turbo:load, not render to avoid double-init)
document.addEventListener('turbo:load', initUserDashboard);