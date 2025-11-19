/**
 * PunktePass – User Dashboard JS (v4.6 - Modern Icons Edition)
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
 */

document.addEventListener("DOMContentLoaded", async () => {
  const boot = window.ppv_boot || {};
  const API = (boot.api || "/wp-json/ppv/v1/").replace(/\/+$/, '/');
  const lang = boot.lang || 'de';

  const T = {
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
      // ✅ NEW - Store Card
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
      // ✅ NEW - Store Card
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
      // ✅ NEW - Store Card
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
    }
  }[lang] || T.de;

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

    const closeLightbox = () => {
      lb.classList.remove('active');
      lightboxActive = false;
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

    document.addEventListener('keydown', (e) => {
      if (!lightboxActive) return;
      if (e.key === 'ArrowLeft') prevBtn.click();
      if (e.key === 'ArrowRight') nextBtn.click();
      if (e.key === 'Escape') closeLightbox();
    });

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

  const initPointSync = () => {
    let lastPolledPoints = boot.points || 0;
    let pollCount = 0;
    const pollInterval = setInterval(async () => {
      pollCount++;
      try {
        const res = await fetch(API + 'user/points-poll', {
          method: 'GET',
          headers: { 'Content-Type': 'application/json' }
        });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.success || !data.points) return;
        if (data.points > lastPolledPoints) {
          const pointDiff = data.points - lastPolledPoints;
          lastPolledPoints = data.points;
          boot.points = data.points;
          updateGlobalPoints(data.points);
          if (window.ppvShowPointToast) {
            window.ppvShowPointToast('success', pointDiff, data.store || 'PunktePass');
          }
        }
      } catch (e) {
        console.warn(`⚠️ [Polling] Error:`, e.message);
      }
    }, 3000);
    window.addEventListener('beforeunload', () => clearInterval(pollInterval));
  };

  function handleScanEvent(data) {
    console.log("📡 [handleScanEvent] Received:", data);

    if (!data?.type) {
      console.warn("⚠️ [handleScanEvent] No type in data");
      return;
    }

    // Handle success event
    if (data.type === "ppv-scan-success") {
      console.log("✅ [handleScanEvent] Success event - points:", data.points, "store:", data.store);
      const newPoints = boot.points + (data.points || 1);
      updateGlobalPoints(newPoints);
      boot.points = newPoints;
      if (window.ppvShowPointToast) {
        window.ppvShowPointToast("success", data.points || 1, data.store || "PunktePass");
        console.log("✅ [handleScanEvent] Toast called");
      } else {
        console.warn("⚠️ [handleScanEvent] ppvShowPointToast not found");
      }
    }

    // Handle error event
    if (data.type === "ppv-scan-error") {
      console.log("❌ [handleScanEvent] Error event - message:", data.message, "store:", data.store);
      if (window.ppvShowPointToast) {
        // Show error toast with store name and error message
        window.ppvShowPointToast("error", 0, data.store || "PunktePass", data.message || "Scan fehlgeschlagen");
        console.log("❌ [handleScanEvent] Error toast called");
      } else {
        console.warn("⚠️ [handleScanEvent] ppvShowPointToast not found");
      }
    }
  }

  /**
   * 🏪 RENDER STORE CARD - FULLY TRANSLATED ✅
   * 🎨 MODERN ICONS - All Remix Icon ✅
   */
  const renderStoreCard = (store) => {
    const logo = (store.logo && store.logo !== 'null')
        ? store.logo
        : (boot.assets?.store_default || PPV_PLUGIN_URL + 'assets/img/store-default-logo.webp');

    const distanceBadge = store.distance_km !== null ? `<span class="ppv-distance-badge"><i class="ri-map-pin-distance-line"></i> ${store.distance_km} ${T.km}</span>` : '';
    const statusBadge = store.open_now
      ? `<span class="ppv-status-badge ppv-open"><i class="ri-checkbox-blank-circle-fill"></i> ${T.open}</span>`
      : `<span class="ppv-status-badge ppv-closed"><i class="ri-checkbox-blank-circle-fill"></i> ${T.closed}</span>`;

    // Gallery
    const galleryHTML = store.gallery && store.gallery.length > 0
      ? `<div class="ppv-gallery-thumbs">
           ${store.gallery.map((img, idx) => `
             <img src="${img}" alt="${T.gallery_label}" class="ppv-gallery-thumb" data-index="${idx}">
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
  // SLIDER
  // ============================================================
  let sliderInitialized = false;
  const initDistanceSlider = (sliderHTML, userLat, userLng, currentDistance = 10) => {
    if (sliderInitialized) {
      console.log("⏸️ [Slider] Already initialized");
      return;
    }
    sliderInitialized = true;

    let sliderTimeout = null;
    const sliderHandler = async (e) => {
      if (e.target.id !== 'ppv-distance-slider') return;

      const newDistance = e.target.value;
      const valueSpan = document.getElementById('ppv-distance-value');
      if (valueSpan) valueSpan.textContent = newDistance;

      clearTimeout(sliderTimeout);
      sliderTimeout = setTimeout(async () => {
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
          storeListDiv.innerHTML = dynamicSliderHTML + storeCards;

          // Re-attach listener for new elements
          attachStoreListeners();
          attachRouteListener();

          console.log("✅ [Slider] Stores updated");
        } catch (err) {
          console.error("❌ Filter error:", err);
        }
      }, 500);
    };

    document.removeEventListener('input', sliderHandler);
    document.addEventListener('input', sliderHandler);
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
  // ROUTE BUTTON HANDLER ✅
  // ============================================================
  const attachRouteListener = () => {
    const storeListEl = document.getElementById('ppv-store-list');
    if (!storeListEl) return;

    storeListEl.addEventListener('click', (e) => {
      const routeBtn = e.target.closest('.ppv-route');
      if (!routeBtn) return;

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

      console.log("✅ [Route] Opening:", googleMapsUrl);
      if (navigator.vibrate) navigator.vibrate(20);
    });

    console.log("✅ [Route] Listener attached");
  };

  // ============================================================
  // LOAD STORES
  // ============================================================
  const initStores = async () => {
    const box = document.getElementById('ppv-store-list');
    if (!box) return;

    let url = API + 'stores/list-optimized';
    let userLat = null;
    let userLng = null;

    // Elsőként alap loading state
    box.innerHTML = `<p class="ppv-loading"><i class="ri-loader-4-line ri-spin"></i> ${T.loading}</p>`;

    // 1️⃣ Próbáljunk pontos helyet kérni, de fallback is legyen
    try {
      const pos = await Promise.race([
        new Promise((resolve) => {
          navigator.geolocation.getCurrentPosition(resolve, () => resolve(null), { timeout: 4000 });
        }),
        new Promise((resolve) => setTimeout(() => resolve(null), 5000))
      ]);

      if (pos?.coords) {
        userLat = pos.coords.latitude;
        userLng = pos.coords.longitude;
        url += `?lat=${userLat}&lng=${userLng}&max_distance=10`;
      } else {
        console.warn("⚠️ [Geo] No position, using fallback");
      }
    } catch (geoErr) {
      console.warn("⚠️ [Geo] Error:", geoErr);
    }

    // 2️⃣ Most töltsük az üzleteket
    try {
      console.log("🌍 [PPV] Fetching stores from:", url);
      const startTime = performance.now();

      const res = await fetch(url, { cache: "no-store" });
      console.log("✅ [PPV] Response received:", res.status, res.statusText);

      const stores = await res.json();
      console.log("📦 [PPV] JSON parsed in", (performance.now() - startTime).toFixed(1), "ms", stores?.length || 0, "items");
      console.log("🧠 [DEBUG] Stores data:", stores);

      try {
        const html = stores.map(renderStoreCard).join('');
        console.log("🧩 [DEBUG] Rendered HTML length:", html.length);
      } catch (err) {
        console.error("❌ [DEBUG] Render error:", err.message, err.stack);
      }

      if (!Array.isArray(stores) || stores.length === 0) {
        box.innerHTML = `<p class="ppv-no-stores"><i class="ri-store-3-line"></i> ${T.no_stores}</p>`;
        return;
      }

      const sliderHTML = `
        <div class="ppv-distance-filter">
          <label><i class="ri-ruler-line"></i> ${T.distance_label}: <span id="ppv-distance-value">10</span> km</label>
          <input type="range" id="ppv-distance-slider" min="10" max="1000" value="10" step="10">
          <div class="ppv-distance-labels"><span>10 km</span><span>1000 km</span></div>
        </div>
      `;

      box.innerHTML = sliderHTML + stores.map(renderStoreCard).join('');
      initDistanceSlider(sliderHTML, userLat, userLng);
      attachStoreListeners();

    } catch (e) {
      console.error("❌ [PPV] Store load failed:", e);
      box.innerHTML = `<p class="ppv-error"><i class="ri-error-warning-line"></i> ${T.no_stores}</p>`;
    }
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
  // INITIALIZATION
  // ============================================================
  initQRToggle();
  initPointSync();

  const waitForStoreList = setInterval(() => {
    const el = document.getElementById("ppv-store-list");
    const qrReady = document.querySelector(".ppv-btn-qr");
    if (el && qrReady) {
      clearInterval(waitForStoreList);
      console.log("✅ [SAFE INIT] QR ready, store list element found → initStores()");
      initStores();
    }
  }, 400);

  // ============================================================
  // TOAST - MODERN ICONS ✅
  // ============================================================

  window.ppvShowPointToast = function(type = "success", points = 1, store = "PunktePass", errorMessage = "") {
    if (document.querySelector(".ppv-point-toast")) return;
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

    const toast = document.createElement("div");
    toast.className = "ppv-point-toast " + type;
    toast.innerHTML = `<div class="ppv-point-toast-inner"><div class="ppv-toast-icon">${icon}</div><div class="ppv-toast-text">${text}</div></div>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add("show"), 30);
    if (type === "success" && navigator.vibrate) navigator.vibrate(40);
    setTimeout(() => {
      toast.classList.remove("show");
      setTimeout(() => toast.remove(), 400);
    }, type === "success" ? 6500 : 4500);
  };

  // ============================================================
  // 📡 BROADCAST EVENT LISTENERS
  // ============================================================

  // 1) BroadcastChannel (cross-tab communication)
  if (typeof BroadcastChannel !== 'undefined') {
    try {
      const bc = new BroadcastChannel("punktepass_scans");
      bc.addEventListener("message", (event) => {
        console.log("📡 [BroadcastChannel] Message received:", event.data);
        handleScanEvent(event.data);
      });
      console.log("✅ [BroadcastChannel] Initialized");
    } catch (e) {
      console.warn("⚠️ [BroadcastChannel] Failed:", e);
    }
  }

  // 2) LocalStorage (cross-tab fallback)
  window.addEventListener("storage", (event) => {
    if (event.key === "ppv_scan_event" && event.newValue) {
      try {
        const data = JSON.parse(event.newValue);
        console.log("📦 [LocalStorage] Event received:", data);
        handleScanEvent(data);
      } catch (e) {
        console.warn("⚠️ [LocalStorage] Parse error:", e);
      }
    }
  });
  console.log("✅ [LocalStorage Listener] Initialized");

  // 3) CustomEvent (same-page communication)
  window.addEventListener("ppv-scan-success", (event) => {
    console.log("🛰️ [CustomEvent] Success event:", event.detail);
    handleScanEvent(event.detail);
  });

  window.addEventListener("ppv-scan-error", (event) => {
    console.log("🛰️ [CustomEvent] Error event:", event.detail);
    handleScanEvent(event.detail);
  });
  console.log("✅ [CustomEvent Listeners] Initialized");

  console.log("✅ Dashboard initialized");
});