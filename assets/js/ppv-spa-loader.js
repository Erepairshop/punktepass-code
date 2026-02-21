/**
 * PunktePass – SPA Utilities v4.0 (Turbo-Only)
 * REMOVED: Custom navigation - Turbo.js handles all navigation now
 * KEPT: Preloader, Toast, Scroll-restore, Offline detection
 *
 * v4.0: Complete navigation removal - Turbo.js is the only navigation handler
 * Author: PunktePass / Erik Borota
 */

document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector("#ppv-app-root");
  if (!root) return;


  // ✅ Clean up old SPA cache from localStorage (one-time migration)
  if (!localStorage.getItem("ppv_spa_cache_cleaned")) {
    const keysToRemove = [];
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key && (key.startsWith("http") || key === "spa_cache_time")) {
        keysToRemove.push(key);
      }
    }
    keysToRemove.forEach(k => localStorage.removeItem(k));
    localStorage.setItem("ppv_spa_cache_cleaned", "1");
    if (keysToRemove.length > 0) {
    }
  }

  let scrollThrottle = null;

  // ✅ Global navigation state (shared with ppv-bottom-nav.js)
  window.PPV_NAV_STATE = window.PPV_NAV_STATE || { isNavigating: false };

  // ✅ Preloader (only if not exists)
  if (!document.getElementById("ppv-preloader")) {
    const loader = document.createElement("div");
    loader.id = "ppv-preloader";
    loader.innerHTML = `<div class="ppv-loader-inner"><div class="pulse"></div></div>`;
    document.body.appendChild(loader);
  }

  // ✅ Close modals on Turbo navigation (prevents flash)
  const closeModals = () => {
    const modal = document.getElementById("ppv-user-qr");
    const overlay = document.getElementById("ppv-qr-overlay");
    if (modal) modal.classList.remove("show");
    if (overlay) overlay.classList.remove("show");
    document.body.classList.remove("qr-modal-open");
    document.body.style.overflow = "";
  };

  // ✅ Turbo event listeners for state sync
  document.addEventListener('turbo:before-visit', () => {
    closeModals();
    window.PPV_NAV_STATE.isNavigating = true;
  });

  document.addEventListener('turbo:load', () => {
    window.PPV_NAV_STATE.isNavigating = false;
  });

  // ✅ Scroll-restore (THROTTLED - max 1x per 200ms)
  window.addEventListener("scroll", () => {
    if (scrollThrottle) return;
    scrollThrottle = setTimeout(() => {
      sessionStorage.setItem("scroll_" + location.pathname, window.scrollY);
      scrollThrottle = null;
    }, 200);
  });

  // Scroll-restore: only on full page load (not after SPA navigation)
  // ppv-bottom-nav.js clears scroll keys on SPA nav, so this only fires on back/refresh
  const scrollPos = sessionStorage.getItem("scroll_" + location.pathname);
  if (scrollPos && !window.PPV_NAV_STATE?.isNavigating) {
    setTimeout(() => window.scrollTo(0, parseInt(scrollPos, 10)), 100);
  }

  // ✅ Offline / online toast
  window.addEventListener("offline", () => showToast("Offline-Modus aktiv", "warn"));
  window.addEventListener("online", () => showToast("Verbindung wiederhergestellt", "ok"));

  // ✅ Toast system
  function showToast(msg, type = "info") {
    const el = document.createElement("div");
    el.className = `ppv-toast ${type}`;
    el.innerHTML = `<div class="ppv-toast-inner">${msg}</div>`;
    document.body.appendChild(el);
    setTimeout(() => el.classList.add("show"), 10);
    setTimeout(() => el.classList.remove("show"), 2500);
    setTimeout(() => el.remove(), 3000);
  }

  // Expose toast globally
  window.ppvShowToast = showToast;
});
