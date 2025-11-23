/**
 * PunktePass – User QR (PWA Neon Blue Version)
 * REST API kompatibilis + Auto Refresh + Copy Feedback
 * FIXED: Added initialization guard for Turbo.js compatibility
 */

(function() {
  'use strict';

  console.log("✅ PunktePass User QR JS aktiv");

  async function initUserQR() {
    const qrBox = document.querySelector(".ppv-user-qr");
    if (!qrBox) return;

    // Skip if already initialized
    if (qrBox.dataset.ppvInitialized === 'true') return;
    qrBox.dataset.ppvInitialized = 'true';

    const qrImg = qrBox.querySelector(".ppv-user-qr-img");
    const qrValue = qrBox.querySelector(".ppv-user-qr-value");

    // Only create status div if it doesn't exist
    let status = qrBox.querySelector(".ppv-user-qr-status");
    if (!status) {
      status = document.createElement("div");
      status.className = "ppv-user-qr-status";
      qrBox.appendChild(status);
    }

    // Felhasználó ID lekérése (WordPress globálból vagy localStorage)
    const userId = window.PPV_USER_ID || localStorage.getItem("ppv_user_id");
    if (!userId) {
      status.innerHTML = "⚠️ Nicht eingeloggt";
      return;
    }

    try {
      const res = await fetch(`/wp-json/ppv/v1/user/qr?user_id=${userId}`);
      const data = await res.json();

      if (data.error) {
        status.innerHTML = `⚠️ ${data.error}`;
        return;
      }

      // QR adatok frissítése
      qrImg.src = data.qr_url;
      qrValue.value = data.qr_value;
      status.innerHTML = "✅ QR-Code geladen";

      // Másolás kattintásra - skip if already set up
      if (qrValue.dataset.ppvClickInitialized !== 'true') {
        qrValue.dataset.ppvClickInitialized = 'true';
        qrValue.addEventListener("click", () => {
          navigator.clipboard.writeText(qrValue.value);
          status.innerHTML = "📋 QR kopiert!";
          setTimeout(() => (status.innerHTML = "✅ QR-Code geladen"), 2000);
        });
      }
    } catch (err) {
      status.innerHTML = "❌ Netzwerkfehler";
      console.error(err);
    }
  }

  // Initial load
  if (document.readyState === 'loading') {
    document.addEventListener("DOMContentLoaded", initUserQR);
  } else {
    initUserQR();
  }

  // Turbo.js support
  document.addEventListener('turbo:load', initUserQR);

  // SPA navigation support
  window.addEventListener('ppv:spa-navigate', initUserQR);

})();
