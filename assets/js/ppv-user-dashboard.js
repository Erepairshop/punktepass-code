/**
 * PunktePass – User Dashboard JS (v5.0 - Turbo SPA Edition)
 *
 * REQUIRED: Remix Icon CDN
 * Add this to your HTML <head>:
 * <link href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css" rel="stylesheet">
 *
 * ICONS: All icons from Remix Icon (https://remixicon.com/)
 * TURBO-COMPATIBLE: Full SPA navigation support
 *
 * v5.0 Changes:
 * - Uses PPV_SET_FLAG/PPV_CLEAR_FLAG for auto-reset stuck flags
 * - Improved Turbo cleanup with AbortController
 * - Better listener management
 * - Enhanced error handling
 */

// Global state for Turbo navigation cleanup
window.PPV_POLL_INTERVAL_ID = null;
window.PPV_VISIBILITY_HANDLER = null;
window.PPV_SLIDER_HANDLER = null;
window.PPV_SLIDER_INITIALIZED = false;
window.PPV_STORES_LOADING = false;
window.PPV_POLLING_IN_PROGRESS = false;
window.PPV_SLIDER_FETCH_IN_PROGRESS = false;
window.PPV_CURRENT_DISTANCE = 10;
window.PPV_ABORT_CONTROLLER = null; // For cancelling in-flight requests

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
    qr_daily_warning: "Pro Geschäft ist nur 1 Scan pro Tag möglich!",
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
    vip_daily_title: "Erster Scan",
    vip_bronze: "Bronze",
    vip_silver: "Silber",
    vip_gold: "Gold",
    vip_platinum: "Platin",
    vip_every: "Jeden",
    vip_scan: "Scan",
    vip_double: "2x Punkte",
    vip_triple: "3x Punkte",
    qr_valid_for: "Gültig noch:",
    qr_expired: "QR-Code abgelaufen",
    qr_refresh: "Neuen QR-Code generieren",
    qr_new_generated: "Neuer QR-Code (30 Min)",
    reward_valid_until: "Gültig bis:",
  },
  hu: {
    welcome: "Üdv a PunktePassban",
    points: "Pontjaim",
    rewards: "Jutalmak",
    collect_here: "Itt tudsz pontot gyűjteni",
    show_in_store: "Mutasd a saját QR-kódod az üzletben",
    show_qr: "QR-kód megjelenítése",
    show_code_tip: "Mutasd ezt a kódot az üzletben a pontgyűjtéshez.",
    qr_daily_warning: "Üzletenként naponta csak 1 beolvasás lehetséges!",
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
    vip_daily_title: "Első scan",
    vip_bronze: "Bronz",
    vip_silver: "Ezüst",
    vip_gold: "Arany",
    vip_platinum: "Platina",
    vip_every: "Minden",
    vip_scan: "scan",
    vip_double: "2x Pont",
    vip_triple: "3x Pont",
    qr_valid_for: "Érvényes még:",
    qr_expired: "QR-kód lejárt",
    qr_refresh: "Új QR-kód generálása",
    qr_new_generated: "Új QR-kód (30 perc)",
    reward_valid_until: "Érvényes:",
  },
  ro: {
    welcome: "Bun venit la PunktePass",
    points: "Punctele mele",
    rewards: "Recompense",
    collect_here: "Colectează puncte aici",
    show_in_store: "Arată codul tău QR în magazin",
    show_qr: "Afișează codul QR",
    show_code_tip: "Arată acest cod în magazin pentru a colecta puncte.",
    qr_daily_warning: "Doar 1 scanare pe zi este permisă per magazin!",
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
    vip_daily_title: "Primul scan",
    vip_bronze: "Bronz",
    vip_silver: "Argint",
    vip_gold: "Aur",
    vip_platinum: "Platină",
    vip_every: "La fiecare",
    vip_scan: "scanare",
    vip_double: "2x Puncte",
    vip_triple: "3x Puncte",
    qr_valid_for: "Valid încă:",
    qr_expired: "Cod QR expirat",
    qr_refresh: "Generează cod QR nou",
    qr_new_generated: "Cod QR nou (30 min)",
    reward_valid_until: "Valid până:",
  }
};

// 🍎 Safari detection
const PPV_IS_SAFARI = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
if (PPV_IS_SAFARI) {
}

// Cleanup function - call before navigation or re-init
// v3.0: Uses PPV_CLEAR_FLAG for auto-reset, aborts in-flight requests
function cleanupPolling() {

  // Abort any in-flight fetch requests
  if (window.PPV_ABORT_CONTROLLER) {
    try {
      window.PPV_ABORT_CONTROLLER.abort();
    } catch (e) { /* ignore */ }
    window.PPV_ABORT_CONTROLLER = null;
  }

  // Close Ably connection (iOS Safari fix)
  if (window.PPV_ABLY_INSTANCE) {
    try {
      if (PPV_IS_SAFARI && window.PPV_ABLY_INSTANCE.connection) {
        window.PPV_ABLY_INSTANCE.connection.close();
      }
      window.PPV_ABLY_INSTANCE.close();
    } catch (e) { /* ignore */ }
    window.PPV_ABLY_INSTANCE = null;
  }

  // Clear QR countdown interval
  if (window.PPV_QR_COUNTDOWN_INTERVAL) {
    clearInterval(window.PPV_QR_COUNTDOWN_INTERVAL);
    window.PPV_QR_COUNTDOWN_INTERVAL = null;
  }

  // Clear polling interval
  if (window.PPV_POLL_INTERVAL_ID) {
    clearInterval(window.PPV_POLL_INTERVAL_ID);
    window.PPV_POLL_INTERVAL_ID = null;
  }

  // Clear header polling
  if (window.PPV_HEADER_POLLING_ID) {
    clearInterval(window.PPV_HEADER_POLLING_ID);
    window.PPV_HEADER_POLLING_ID = null;
  }

  // Remove event listeners
  if (window.PPV_VISIBILITY_HANDLER) {
    document.removeEventListener('visibilitychange', window.PPV_VISIBILITY_HANDLER);
    window.PPV_VISIBILITY_HANDLER = null;
  }

  if (window.PPV_SLIDER_HANDLER) {
    document.removeEventListener('input', window.PPV_SLIDER_HANDLER);
    window.PPV_SLIDER_HANDLER = null;
  }

  // Reset all state flags using PPV_CLEAR_FLAG if available (auto-clears timeouts)
  const clearFlag = window.PPV_CLEAR_FLAG || ((name) => { window[name] = false; });
  clearFlag('PPV_POLLING_ACTIVE');
  clearFlag('PPV_SLIDER_INITIALIZED');
  clearFlag('PPV_STORES_LOADING');
  clearFlag('PPV_POLLING_IN_PROGRESS');
  clearFlag('PPV_SLIDER_FETCH_IN_PROGRESS');

  window.PPV_CURRENT_DISTANCE = 10;

  // Safari: Force garbage collection hint
  if (PPV_IS_SAFARI && typeof window.gc === 'function') {
    try { window.gc(); } catch (e) { /* ignore */ }
  }

}

// 🚀 Turbo-compatible initialization
async function initUserDashboard() {
  // Check if dashboard root exists (only run on user dashboard pages)
  const dashboardRoot = document.getElementById('ppv-dashboard-root');
  if (!dashboardRoot) {
    // 🧹 Clean up polling if we're NOT on dashboard page anymore
    cleanupPolling();
    return;
  }

  // Prevent double initialization
  if (dashboardRoot.dataset.initialized === 'true') {
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

  // ════════════════════════════════════════════════════════════════
  // 🎁 REDEMPTION MODAL SYSTEM (60 second timeout)
  // ════════════════════════════════════════════════════════════════
  let redemptionCountdownInterval = null;
  let redemptionModalElement = null;

  const showRedemptionModal = (data) => {

    // Remove existing modal if present
    closeRedemptionModal();

    const { token, rewards, user_total_points, store_name, timeout_seconds } = data;
    const timeoutSec = timeout_seconds || 60;

    // Create modal HTML
    const modal = document.createElement('div');
    modal.id = 'ppv-redemption-modal';
    modal.className = 'ppv-redemption-modal';
    modal.innerHTML = `
      <div class="ppv-redemption-overlay"></div>
      <div class="ppv-redemption-container">
        <div class="ppv-redemption-header">
          <i class="ri-gift-2-fill" style="font-size: 48px; color: #34d399;"></i>
          <h2>${lang === 'de' ? 'Punkte einlösen?' : lang === 'hu' ? 'Beváltod a pontjaid?' : 'Răscumperi punctele?'}</h2>
          <p style="color: rgba(255,255,255,0.7);">${store_name || 'PunktePass'}</p>
        </div>

        <div class="ppv-redemption-timer">
          <i class="ri-time-line"></i>
          <span id="ppv-redemption-countdown">${timeoutSec}</span>s
        </div>

        <div class="ppv-redemption-points">
          <span>${lang === 'de' ? 'Deine Punkte:' : lang === 'hu' ? 'Pontjaid:' : 'Punctele tale:'}</span>
          <strong style="color: #00e6ff; font-size: 24px;">${user_total_points}</strong>
        </div>

        <div class="ppv-redemption-rewards">
          <p style="margin-bottom: 12px; color: rgba(255,255,255,0.8);">${lang === 'de' ? 'Wähle eine Prämie:' : lang === 'hu' ? 'Válassz jutalmat:' : 'Alege o recompensă:'}</p>
          ${rewards.map(r => `
            <button class="ppv-reward-option" data-reward-id="${r.id}" data-points="${r.required_points}">
              <div class="ppv-reward-option-info">
                <strong>${escapeHtml(r.title)}</strong>
                <span style="color: #fbbf24;">${r.required_points} ${lang === 'de' ? 'Punkte' : lang === 'hu' ? 'pont' : 'puncte'}</span>
              </div>
              <i class="ri-arrow-right-s-line"></i>
            </button>
          `).join('')}
        </div>

        <div class="ppv-redemption-actions">
          <button class="ppv-btn-later" id="ppv-redemption-later">
            <i class="ri-time-line"></i> ${lang === 'de' ? 'Später' : lang === 'hu' ? 'Később' : 'Mai târziu'}
          </button>
        </div>

        <div class="ppv-redemption-waiting" id="ppv-redemption-waiting" style="display: none;">
          <div class="ppv-spinner"></div>
          <p>${lang === 'de' ? 'Warte auf Bestätigung vom Händler...' : lang === 'hu' ? 'Várakozás a kereskedő megerősítésére...' : 'Așteptând confirmarea comerciantului...'}</p>
        </div>
      </div>
    `;

    document.body.appendChild(modal);
    redemptionModalElement = modal;

    // Animate in
    setTimeout(() => modal.classList.add('show'), 10);

    // Start countdown
    let remaining = timeoutSec;
    const countdownEl = document.getElementById('ppv-redemption-countdown');

    redemptionCountdownInterval = setInterval(() => {
      remaining--;
      if (countdownEl) countdownEl.textContent = remaining;

      if (remaining <= 0) {
        // Auto-decline on timeout
        handleRedemptionResponse(token, 'decline', null);
        closeRedemptionModal();
      }
    }, 1000);

    // Event: Later button
    document.getElementById('ppv-redemption-later').addEventListener('click', () => {
      handleRedemptionResponse(token, 'decline', null);
      closeRedemptionModal();
    });

    // Event: Reward selection
    modal.querySelectorAll('.ppv-reward-option').forEach(btn => {
      btn.addEventListener('click', () => {
        const rewardId = btn.dataset.rewardId;

        // Show waiting state
        modal.querySelector('.ppv-redemption-rewards').style.display = 'none';
        modal.querySelector('.ppv-redemption-actions').style.display = 'none';
        modal.querySelector('.ppv-redemption-timer').style.display = 'none';
        document.getElementById('ppv-redemption-waiting').style.display = 'flex';

        // Stop countdown
        if (redemptionCountdownInterval) {
          clearInterval(redemptionCountdownInterval);
          redemptionCountdownInterval = null;
        }

        handleRedemptionResponse(token, 'accept', rewardId);
      });
    });

    // Vibrate on show
    if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
  };

  const closeRedemptionModal = () => {
    if (redemptionCountdownInterval) {
      clearInterval(redemptionCountdownInterval);
      redemptionCountdownInterval = null;
    }

    if (redemptionModalElement) {
      redemptionModalElement.classList.remove('show');
      setTimeout(() => {
        if (redemptionModalElement && redemptionModalElement.parentNode) {
          redemptionModalElement.remove();
        }
        redemptionModalElement = null;
      }, 300);
    }
  };

  const handleRedemptionResponse = async (token, action, rewardId) => {
    try {
      const res = await fetch(API + 'redemption/user-response', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          token,
          action,
          reward_id: rewardId
        })
      });

      const data = await res.json();

      if (action === 'decline') {
        closeRedemptionModal();
        if (window.ppvShowPointToast && data.message) {
          window.ppvShowPointToast('info', 0, 'PunktePass', data.message);
        }
      }
      // If accept, we wait for Ably notification (redemption-approved/rejected)

    } catch (err) {
      console.error('🎁 [Redemption] Error:', err);
      closeRedemptionModal();
    }
  };

  // ============================================================
  // 🎫 TIMED QR WITH COUNTDOWN (v3.0)
  // ============================================================

  // ✅ FIX: Store globally for cleanup on navigation
  window.PPV_QR_COUNTDOWN_INTERVAL = null;
  let qrExpiresAt = null;

  const initQRToggle = () => {
    const btn = document.querySelector(".ppv-btn-qr");
    const modal = document.getElementById("ppv-user-qr");
    const overlay = document.getElementById("ppv-qr-overlay");
    const closeBtn = document.querySelector(".ppv-qr-close");
    const refreshBtn = document.getElementById("ppv-qr-refresh-btn");

    if (!btn || !modal || !overlay) {
      console.warn("⚠️ [QR] Elements not found");
      return;
    }

    const openQR = async (e) => {
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

      // Load timed QR on open
      await loadTimedQR();
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

    // Refresh button
    if (refreshBtn) {
      refreshBtn.addEventListener("click", async (e) => {
        e.preventDefault();
        await loadTimedQR(true);
      });
    }

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && modal.classList.contains("show")) closeQR();
    });

  };

  // ============================================================
  // 🔄 LOAD TIMED QR FROM REST API
  // ============================================================
  const loadTimedQR = async (forceNew = false) => {
    const qrImg = document.getElementById("ppv-qr-image");
    const qrLoading = document.getElementById("ppv-qr-loading");
    const qrDisplay = document.getElementById("ppv-qr-display");
    const qrExpired = document.getElementById("ppv-qr-expired");
    const qrStatus = document.getElementById("ppv-qr-status");

    // Show loading
    if (qrLoading) qrLoading.style.display = "flex";
    if (qrDisplay) qrDisplay.style.display = "none";
    if (qrExpired) qrExpired.style.display = "none";

    try {
      const res = await fetch(API + "user/generate-timed-qr", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ user_id: boot.uid })
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const data = await res.json();

      if (data.code) {
        // Error response
        showQRStatus("❌ " + (data.message || "Error"), "error");
        return;
      }

      // Display QR
      if (qrImg) qrImg.src = data.qr_url;
      if (qrLoading) qrLoading.style.display = "none";
      if (qrDisplay) qrDisplay.style.display = "block";
      if (qrExpired) qrExpired.style.display = "none";

      // Start countdown
      startQRCountdown(data.expires_at);

      // Status message
      if (data.is_new) {
        showQRStatus("✅ " + (T.qr_new_generated || "Neuer QR-Code (30 Min)"), "success");
      } else {
        const remainingMin = Math.floor(data.expires_in / 60);
        showQRStatus(`✅ QR geladen (${remainingMin} Min)`, "success");
      }


    } catch (err) {
      console.error("❌ [QR] Load error:", err);
      if (qrLoading) qrLoading.style.display = "none";
      showQRStatus("❌ Netzwerkfehler", "error");
    }
  };

  // ============================================================
  // ⏱️ COUNTDOWN TIMER (30:00 → 00:00)
  // ============================================================
  const startQRCountdown = (expirationTimestamp) => {
    qrExpiresAt = expirationTimestamp;

    const timerEl = document.getElementById("ppv-qr-timer");
    const timerValue = document.getElementById("ppv-qr-timer-value");

    // Clear previous interval
    if (window.PPV_QR_COUNTDOWN_INTERVAL) {
      clearInterval(window.PPV_QR_COUNTDOWN_INTERVAL);
    }

    // Reset classes
    if (timerEl) {
      timerEl.classList.remove("ppv-timer-warning", "ppv-timer-critical");
    }

    // Update every second
    window.PPV_QR_COUNTDOWN_INTERVAL = setInterval(() => {
      const now = Math.floor(Date.now() / 1000);
      const remaining = qrExpiresAt - now;

      if (remaining <= 0) {
        // QR expired
        clearInterval(window.PPV_QR_COUNTDOWN_INTERVAL);
        window.PPV_QR_COUNTDOWN_INTERVAL = null;
        showQRExpired();
        return;
      }

      // Format: MM:SS
      const mins = Math.floor(remaining / 60);
      const secs = remaining % 60;
      const formatted = `${mins}:${secs.toString().padStart(2, "0")}`;

      if (timerValue) {
        timerValue.textContent = formatted;
      }

      // Warning at 5 minutes
      if (remaining <= 300 && remaining > 60) {
        if (timerEl) {
          timerEl.classList.add("ppv-timer-warning");
          timerEl.classList.remove("ppv-timer-critical");
        }
      }

      // Critical at 1 minute
      if (remaining <= 60) {
        if (timerEl) {
          timerEl.classList.add("ppv-timer-critical");
          timerEl.classList.remove("ppv-timer-warning");
        }
      }
    }, 1000);
  };

  // ============================================================
  // 🔴 SHOW QR EXPIRED STATE
  // ============================================================
  const showQRExpired = () => {
    const qrDisplay = document.getElementById("ppv-qr-display");
    const qrExpired = document.getElementById("ppv-qr-expired");

    if (qrDisplay) qrDisplay.style.display = "none";
    if (qrExpired) qrExpired.style.display = "flex";

    showQRStatus("⏰ " + (T.qr_expired || "QR abgelaufen"), "warning");
  };

  // ============================================================
  // 📝 QR STATUS MESSAGE
  // ============================================================
  const showQRStatus = (message, type = "info") => {
    const status = document.getElementById("ppv-qr-status");
    if (!status) return;

    status.textContent = message;
    status.className = "ppv-qr-status ppv-status-" + type;

    // Auto-clear after 4 seconds
    setTimeout(() => {
      if (status.textContent === message) {
        status.textContent = "";
        status.className = "ppv-qr-status";
      }
    }, 4000);
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
    if (boot.ably && boot.ably.key && window.PPV_ABLY_MANAGER) {
      initAblySync();
    } else {
      initPollingSync();
    }
  };

  // 📡 ABLY REAL-TIME SYNC (via shared manager)
  const initAblySync = () => {

    const manager = window.PPV_ABLY_MANAGER;
    const channelName = 'user-' + boot.uid;

    // Initialize shared connection
    if (!manager.init({ key: boot.ably.key, channel: channelName })) {
      console.warn('📡 [Ably] Shared manager init failed, falling back to polling');
      initPollingSync();
      return;
    }

    // Store subscriber ID for cleanup
    window.PPV_DASHBOARD_ABLY_SUB = 'user-dashboard-' + boot.uid;

    // Listen for connection state changes
    manager.onStateChange((state) => {
      if (state === 'connected') {
      } else if (state === 'failed') {
        console.warn('📡 [Ably] Connection failed, falling back to polling');
        initPollingSync();
      }
    });

    // 🎯 Handle points update event
    manager.subscribe(channelName, 'points-update', (message) => {
      const data = message.data;

      // Skip toast if redemption modal is open (to avoid interference)
      const isRedemptionModalOpen = !!redemptionModalElement;

      if (data.success && data.points_added > 0) {
        // Show success toast only if redemption modal is NOT open
        if (window.ppvShowPointToast && !isRedemptionModalOpen) {
          window.ppvShowPointToast('success', data.points_added, data.store_name || 'PunktePass');
        } else if (isRedemptionModalOpen) {
        }

        // Update UI (always update points, even if modal is open)
        boot.points = data.total_points;
        updateGlobalPoints(data.total_points);

        // Update rewards count if provided
        if (data.total_rewards !== undefined) {
          updateGlobalRewards(data.total_rewards);
        }
      } else if (data.success === false) {
        // Show error toast (unless redemption modal is open)
        if (window.ppvShowPointToast && !isRedemptionModalOpen) {
          window.ppvShowPointToast('error', 0, data.store_name || 'PunktePass', data.message);
        }
      }
    }, window.PPV_DASHBOARD_ABLY_SUB);

    // 🎁 Handle reward approved event
    manager.subscribe(channelName, 'reward-approved', (message) => {
      const data = message.data;

      if (window.ppvShowPointToast) {
        window.ppvShowPointToast('reward', 0, data.store_name || 'PunktePass', data.reward_name || T.reward_redeemed);
      }

      // Refresh points (they decreased)
      if (data.new_points !== undefined) {
        boot.points = data.new_points;
        updateGlobalPoints(data.new_points);
      }
    }, window.PPV_DASHBOARD_ABLY_SUB);

    // ════════════════════════════════════════════════════════════════
    // 🎁 REAL-TIME REDEMPTION FLOW - New Feature
    // ════════════════════════════════════════════════════════════════

    // 🎁 Handle redemption prompt (user has enough points to redeem)
    manager.subscribe(channelName, 'redemption-prompt', (message) => {
      const data = message.data;
      showRedemptionModal(data);
    }, window.PPV_DASHBOARD_ABLY_SUB);

    // ✅ Handle redemption approved by handler
    manager.subscribe(channelName, 'redemption-approved', (message) => {
      const data = message.data;

      // Close waiting modal if open
      closeRedemptionModal();

      // Show redemption success toast with reward title
      if (window.ppvShowPointToast) {
        window.ppvShowPointToast('redemption_success', 0, data.reward_title || 'Prämie');
      }

      // Update points
      if (data.new_balance !== undefined) {
        boot.points = data.new_balance;
        updateGlobalPoints(data.new_balance);
      }
    }, window.PPV_DASHBOARD_ABLY_SUB);

    // ❌ Handle redemption rejected by handler
    manager.subscribe(channelName, 'redemption-rejected', (message) => {
      const data = message.data;

      // Close waiting modal if open
      closeRedemptionModal();

      // Show rejection toast with clear message and reason (longer duration)
      if (window.ppvShowPointToast) {
        window.ppvShowPointToast('rejection', 0, data.reward_title || 'Prämie', data.reason || '');
      }
    }, window.PPV_DASHBOARD_ABLY_SUB);

    // Cleanup on page unload (unsubscribe only, manager handles connection)
    window.addEventListener('beforeunload', () => {
      if (window.PPV_DASHBOARD_ABLY_SUB && window.PPV_ABLY_MANAGER) {
        window.PPV_ABLY_MANAGER.unsubscribe(window.PPV_DASHBOARD_ABLY_SUB);
      }
    });
  };

  // 🔄 FALLBACK POLLING SYNC
  const initPollingSync = () => {

    let lastPolledPoints = boot.points || 0;
    let lastShownErrorTimestamp = null;
    let isFirstPoll = true;

    const getCurrentInterval = () => {
      return document.hidden ? 30000 : 5000; // 30s inactive, 5s active
    };

    const pollPoints = async () => {
      if (!document.getElementById('ppv-dashboard-root')) {
        cleanupPolling();
        return;
      }

      if (window.PPV_POLLING_IN_PROGRESS) {
        return;
      }
      // Use PPV_SET_FLAG for auto-reset after timeout
      const setFlag = window.PPV_SET_FLAG || ((name, val) => { window[name] = val; });
      setFlag('PPV_POLLING_IN_PROGRESS', true);

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
        console.warn('[Polling] Error:', e.message);
      } finally {
        const clearFlag = window.PPV_CLEAR_FLAG || ((name) => { window[name] = false; });
        clearFlag('PPV_POLLING_IN_PROGRESS');
      }
    };

    const startPolling = () => {
      if (window.PPV_POLL_INTERVAL_ID) clearInterval(window.PPV_POLL_INTERVAL_ID);
      const interval = getCurrentInterval();
      window.PPV_POLL_INTERVAL_ID = setInterval(pollPoints, interval);
    };

    // ✅ FIX: Debounced visibility handler - only restart when visible, with 3s cooldown
    let lastVisibilityChange = 0;
    window.PPV_VISIBILITY_HANDLER = () => {
      if (document.hidden) return; // Only act when becoming visible
      const now = Date.now();
      if (now - lastVisibilityChange < 3000) return; // 3s debounce
      lastVisibilityChange = now;
      startPolling();
    };
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

            // Format end_date if available
            const endDateFormatted = r.end_date ? r.end_date.substring(0, 10).split('-').reverse().join('.') : null;

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
                ${endDateFormatted ? `
                <div class="ppv-reward-row">
                  <span class="ppv-reward-label"><i class="ri-calendar-line"></i> ${T.reward_valid_until}</span>
                  <span class="ppv-reward-value"><strong style="color: #fbbf24;">${endDateFormatted}</strong></span>
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
    // 👑 VIP BONUS SECTION - CLEAN TABLE VERSION
    // ============================================================
    const vipHTML = store.vip ? (() => {
      const vip = store.vip;
      const rows = [];

      // 1️⃣ FIX PONT BÓNUSZ
      if (vip.fix && vip.fix.enabled) {
        rows.push(`
          <tr class="ppv-vip-table-row">
            <td class="ppv-vip-label-cell"><i class="ri-add-circle-line"></i> ${T.vip_fix_title}</td>
            <td class="ppv-vip-cell bronze">+${vip.fix.bronze}</td>
            <td class="ppv-vip-cell silver">+${vip.fix.silver}</td>
            <td class="ppv-vip-cell gold">+${vip.fix.gold}</td>
            <td class="ppv-vip-cell platinum">+${vip.fix.platinum}</td>
          </tr>
        `);
      }

      // 2️⃣ STREAK BÓNUSZ
      if (vip.streak && vip.streak.enabled) {
        const isMultiplier = vip.streak.type === 'double' || vip.streak.type === 'triple';
        const multiplierText = vip.streak.type === 'double' ? T.vip_double : T.vip_triple;

        if (isMultiplier) {
          rows.push(`
            <tr class="ppv-vip-table-row">
              <td class="ppv-vip-label-cell"><i class="ri-fire-line"></i> ${vip.streak.count}. scan</td>
              <td class="ppv-vip-cell ppv-vip-multiplier" colspan="4">${multiplierText}</td>
            </tr>
          `);
        } else {
          rows.push(`
            <tr class="ppv-vip-table-row">
              <td class="ppv-vip-label-cell"><i class="ri-fire-line"></i> ${vip.streak.count}. scan</td>
              <td class="ppv-vip-cell bronze">+${vip.streak.bronze}</td>
              <td class="ppv-vip-cell silver">+${vip.streak.silver}</td>
              <td class="ppv-vip-cell gold">+${vip.streak.gold}</td>
              <td class="ppv-vip-cell platinum">+${vip.streak.platinum}</td>
            </tr>
          `);
        }
      }

      // 3️⃣ DAILY BÓNUSZ
      if (vip.daily && vip.daily.enabled) {
        rows.push(`
          <tr class="ppv-vip-table-row">
            <td class="ppv-vip-label-cell"><i class="ri-sun-line"></i> ${T.vip_daily_title}</td>
            <td class="ppv-vip-cell bronze">+${vip.daily.bronze}</td>
            <td class="ppv-vip-cell silver">+${vip.daily.silver}</td>
            <td class="ppv-vip-cell gold">+${vip.daily.gold}</td>
            <td class="ppv-vip-cell platinum">+${vip.daily.platinum}</td>
          </tr>
        `);
      }

      return rows.length ? `
        <div class="ppv-store-vip-table">
          <div class="ppv-vip-table-title">
            <i class="ri-vip-crown-fill"></i> ${T.vip_title}
          </div>
          <table class="ppv-vip-mini-table">
            <thead>
              <tr>
                <th class="ppv-vip-th-label"></th>
                <th class="ppv-vip-th bronze"><i class="ri-medal-line"></i><span>${T.vip_bronze}</span></th>
                <th class="ppv-vip-th silver"><i class="ri-medal-line"></i><span>${T.vip_silver}</span></th>
                <th class="ppv-vip-th gold"><i class="ri-medal-fill"></i><span>${T.vip_gold}</span></th>
                <th class="ppv-vip-th platinum"><i class="ri-vip-crown-fill"></i><span>${T.vip_platinum}</span></th>
              </tr>
            </thead>
            <tbody>
              ${rows.join('')}
            </tbody>
          </table>
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
        // Prevent concurrent slider fetches
        if (window.PPV_SLIDER_FETCH_IN_PROGRESS) {
          return;
        }
        const setFlag = window.PPV_SET_FLAG || ((name, val) => { window[name] = val; });
        setFlag('PPV_SLIDER_FETCH_IN_PROGRESS', true);

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

        } catch (err) {
          console.error("[Slider] Filter error:", err);
        } finally {
          const clearFlag = window.PPV_CLEAR_FLAG || ((name) => { window[name] = false; });
          clearFlag('PPV_SLIDER_FETCH_IN_PROGRESS');
        }
      }, 500);
    };

    document.addEventListener('input', window.PPV_SLIDER_HANDLER);
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
        return;
      }
    });

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
      return;
    }

    // Prevent duplicate loading
    if (window.PPV_STORES_LOADING) {
      return;
    }
    const setFlag = window.PPV_SET_FLAG || ((name, val) => { window[name] = val; });
    setFlag('PPV_STORES_LOADING', true);

    const startTime = performance.now();

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
        resolve(null);
      }, geoTimeoutMs);

      navigator.geolocation.getCurrentPosition(
        (p) => {
          clearTimeout(timeout);
          // Cache for next time
          localStorage.setItem('ppv_user_lat', p.coords.latitude.toString());
          localStorage.setItem('ppv_user_lng', p.coords.longitude.toString());
          resolve(p);
        },
        (err) => {
          clearTimeout(timeout);
          resolve(null);
        },
        { timeout: geoTimeoutMs, maximumAge: 600000, enableHighAccuracy: false }
      );
    });

    // 2️⃣ OPTIMIZED: Wait for geo first if no cached location, then fetch ONCE
    try {
      // If no cached location, wait for geo promise first (max 8s)
      if (!cachedLat && !cachedLng) {
        const freshPos = await geoPromise;
        if (freshPos?.coords) {
          userLat = freshPos.coords.latitude;
          userLng = freshPos.coords.longitude;
        } else {
        }
      }

      // Now make ONE fetch with best available coordinates
      const currentDist = window.PPV_CURRENT_DISTANCE || 10;
      let url = API + 'stores/list-optimized';
      if (userLat && userLng) {
        url += `?lat=${userLat}&lng=${userLng}&max_distance=${currentDist}`;
      }

      const res = await fetch(url, { cache: "no-store" });

      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }

      const stores = await res.json();

      // Render stores
      if (!Array.isArray(stores) || stores.length === 0) {
        box.innerHTML = `<p class="ppv-no-stores"><i class="ri-store-3-line"></i> ${T.no_stores}</p>`;
      } else {
        renderStoreList(box, stores, userLat, userLng);
      }

    } catch (e) {
      console.error('❌ [Stores] Load failed:', e.message);
      box.innerHTML = `<p class="ppv-error"><i class="ri-error-warning-line"></i> ${T.no_stores}</p>`;
    }

    const clearFlag = window.PPV_CLEAR_FLAG || ((name) => { window[name] = false; });
    clearFlag('PPV_STORES_LOADING');
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

          <!-- Loading State -->
          <div class="ppv-qr-loading" id="ppv-qr-loading" style="display: flex;">
            <div class="ppv-spinner"></div>
            <p>${T.loading || "Lädt..."}</p>
          </div>

          <!-- QR Display -->
          <div id="ppv-qr-display" style="display: none;">
            <img src="" alt="My QR Code" class="ppv-qr-image" id="ppv-qr-image">

            <!-- Countdown Timer -->
            <div class="ppv-qr-timer" id="ppv-qr-timer">
              <i class="ri-time-line"></i>
              <span>${T.qr_valid_for || "Gültig noch:"} <strong id="ppv-qr-timer-value">--:--</strong></span>
            </div>

            <div class="ppv-qr-warning">
              <span class="ppv-qr-warning-icon">⚠️</span>
              <span class="ppv-qr-warning-text">${T.qr_daily_warning}</span>
            </div>
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

          <!-- Expired State -->
          <div class="ppv-qr-expired" id="ppv-qr-expired" style="display: none;">
            <i class="ri-time-line" style="font-size: 48px; color: #f59e0b;"></i>
            <p style="margin: 16px 0;">${T.qr_expired || "QR-Code abgelaufen"}</p>
            <button class="ppv-btn-refresh" id="ppv-qr-refresh-btn" type="button">
              <i class="ri-refresh-line"></i> ${T.qr_refresh || "Neuen QR-Code generieren"}
            </button>
          </div>

          <!-- Status Message -->
          <div class="ppv-qr-status" id="ppv-qr-status"></div>
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

    // Remove existing toast if present
    const existingToast = document.querySelector(".ppv-point-toast");
    if (existingToast) {
      existingToast.classList.remove("show");
      setTimeout(() => existingToast.remove(), 200);
    }

    // Function to create new toast
    const createToast = () => {

      const L = {
        de: { dup: "Heute bereits gescannt", err: "Offline", pend: "Verbindung...", add: "Punkt(e) von", from: "von", rejected: "Einlösung abgelehnt", reason: "Grund", redeemed: "Einlösung bestätigt!" },
        hu: { dup: "Ma már", err: "Offline", pend: "Kapcsolódás...", add: "pont a", from: "-tól/-től", rejected: "Beváltás elutasítva", reason: "Ok", redeemed: "Beváltás sikeres!" },
        ro: { dup: "Astăzi", err: "Offline", pend: "Conectare...", add: "punct de la", from: "de la", rejected: "Răscumpărare respinsă", reason: "Motiv", redeemed: "Răscumpărare confirmată!" }
      }[lang] || L.de;

      let icon = '<i class="ri-emotion-happy-line"></i>', text = "";
      if (type === "duplicate") {
        icon = '<i class="ri-error-warning-line"></i>';
        text = L.dup;
      }
      else if (type === "redemption_success") {
        // Special type for approved redemptions - shows reward title and success message
        icon = '<i class="ri-gift-fill"></i>';
        text = `<strong>✅ ${L.redeemed}</strong>`;
        if (store && store !== 'Prämie' && store !== 'PunktePass') {
          text += `<br><small>🎁 ${store}</small>`;
        }
      }
      else if (type === "rejection") {
        // Special type for redemption rejections - shows clear message with reason
        icon = '<i class="ri-close-circle-fill"></i>';
        const reasonText = errorMessage || '';
        text = `<strong>❌ ${L.rejected}</strong>`;
        if (reasonText) {
          text += `<br><small>${L.reason}: ${reasonText}</small>`;
        }
        if (store && store !== 'Prämie' && store !== 'PunktePass') {
          text += `<br><small>${store}</small>`;
        }
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


      const toast = document.createElement("div");
      toast.className = "ppv-point-toast " + type;
      toast.innerHTML = `<div class="ppv-point-toast-inner"><div class="ppv-toast-icon">${icon}</div><div class="ppv-toast-text">${text}</div></div>`;
      document.body.appendChild(toast);

      setTimeout(() => {
        toast.classList.add("show");
      }, 30);

      if (type === "success" && navigator.vibrate) navigator.vibrate(40);
      if (type === "redemption_success" && navigator.vibrate) navigator.vibrate([100, 50, 100]); // Double vibration for redemption success
      if (type === "rejection" && navigator.vibrate) navigator.vibrate([100, 50, 100, 50, 100]); // Triple vibration for rejection

      // Duration: success/redemption_success=6500ms, rejection=8500ms (longer!), others=4500ms
      const duration = (type === "success" || type === "redemption_success") ? 6500 : type === "rejection" ? 8500 : 4500;

      setTimeout(() => {
        toast.classList.remove("show");
        setTimeout(() => {
          toast.remove();
        }, 400);
      }, duration);
    };

    // Wait for old toast to be removed before creating new one
    if (existingToast) {
      setTimeout(createToast, 250);
    } else {
      createToast();
    }
  };

}

// 🚀 Initialize on DOMContentLoaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initUserDashboard);
} else {
  initUserDashboard();
}

// 🧹 Turbo: Clean up BEFORE navigating away (prevents multiple polling instances)
document.addEventListener('turbo:before-visit', function() {
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

// 🍎 Safari fix: Also cleanup on pagehide (Safari doesn't always fire turbo events)
if (PPV_IS_SAFARI) {
  window.addEventListener('pagehide', function() {
    cleanupPolling();
  });

  // Safari fix: Cleanup when tab becomes hidden
  document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
      // Don't fully cleanup, just pause polling to reduce memory pressure
      if (window.PPV_POLL_INTERVAL_ID) {
        clearInterval(window.PPV_POLL_INTERVAL_ID);
        window.PPV_POLL_INTERVAL_ID = null;
      }
    }
  });
}