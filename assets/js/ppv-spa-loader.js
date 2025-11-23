/**
 * PunktePass – SPA Navigation v3.1 (Cleaned - No Duplicates)
 * Ultra-fast + Cache + Offline + Feedback
 * Author: PunktePass / Erik Borota
 *
 * FIXED: Removed duplicate v2.0 code that caused memory leaks
 * FIXED: Added initialization guard to prevent multiple instances
 */

(function() {
  'use strict';

  // Guard against multiple initializations
  if (window.PPV_SPA_INITIALIZED) {
    console.log('⏭️ [SPA] Already initialized, skipping');
    return;
  }
  window.PPV_SPA_INITIALIZED = true;

  console.log('✅ [SPA] v3.1 Loaded (cleaned)');

  function initSpaNavigation() {
    const root = document.querySelector("#ppv-app-root");
    if (!root) return;

    // Check if already initialized on this root
    if (root.dataset.spaInitialized === 'true') {
      console.log('⏭️ [SPA] Root already initialized');
      return;
    }
    root.dataset.spaInitialized = 'true';

    const cache = new Map();
    const cacheTime = JSON.parse(localStorage.getItem("spa_cache_time") || "{}");

    // Preloader (only create once)
    if (!document.getElementById("ppv-preloader")) {
      const loader = document.createElement("div");
      loader.id = "ppv-preloader";
      loader.innerHTML = `<div class="ppv-loader-inner"><div class="pulse"></div></div>`;
      document.body.appendChild(loader);
    }

    // Fade helpers
    const fadeOut = () => {
      root.style.transition = "opacity 0.25s ease";
      root.style.opacity = "0";
      document.body.classList.add("ppv-loading");
    };
    const fadeIn = () => {
      root.style.opacity = "1";
      document.body.classList.remove("ppv-loading");
    };

    // Toast system
    function showToast(msg, type = "info") {
      const el = document.createElement("div");
      el.className = `ppv-toast ${type}`;
      el.innerHTML = `<div class="ppv-toast-inner">${msg}</div>`;
      document.body.appendChild(el);
      setTimeout(() => el.classList.add("show"), 10);
      setTimeout(() => el.classList.remove("show"), 2500);
      setTimeout(() => el.remove(), 3000);
    }

    // Event delegation for nav links (instead of per-element listeners)
    document.addEventListener("click", async (e) => {
      const link = e.target.closest(".ppv-bottom-nav a");
      if (!link) return;

      e.preventDefault();
      const url = link.href;
      if (!url || url === window.location.href) return;

      fadeOut();
      if (navigator.vibrate) navigator.vibrate(15);

      let html = cache.get(url);
      const now = Date.now();
      const validCache = cacheTime[url] && now - cacheTime[url] < 60000;

      if (!html && localStorage.getItem(url)) html = localStorage.getItem(url);
      if (!validCache) {
        try {
          const res = await fetch(url, { headers: { "X-PPV-SPA": "1" } });
          html = await res.text();
          cache.set(url, html);
          // Note: Removed localStorage HTML caching to prevent storage overflow
          cacheTime[url] = now;
          localStorage.setItem("spa_cache_time", JSON.stringify(cacheTime));
        } catch {
          showToast("Offline-Modus aktiv", "warn");
        }
      }

      if (!html) return fadeIn();

      const dom = new DOMParser().parseFromString(html, "text/html");
      const newRoot = dom.querySelector("#ppv-app-root");
      if (newRoot) {
        root.innerHTML = newRoot.innerHTML;
        window.dispatchEvent(new CustomEvent('ppv:spa-navigate', { detail: { url } }));
      }

      document.querySelectorAll(".ppv-bottom-nav a").forEach(a => a.classList.remove("active"));
      link.classList.add("active");

      window.history.pushState({}, "", url);
      setTimeout(fadeIn, 80);
    }, { capture: true });

    // Scroll restore (throttled)
    let scrollTimeout;
    window.addEventListener("scroll", () => {
      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(() => {
        sessionStorage.setItem("scroll_" + location.pathname, window.scrollY);
      }, 100);
    }, { passive: true });

    const scrollPos = sessionStorage.getItem("scroll_" + location.pathname);
    if (scrollPos) setTimeout(() => window.scrollTo(0, parseInt(scrollPos)), 100);

    // Offline / online toast (one-time listeners)
    window.addEventListener("offline", () => showToast("Offline-Modus aktiv", "warn"), { once: false });
    window.addEventListener("online", () => showToast("Verbindung wiederhergestellt", "ok"), { once: false });

    // History back (one listener only)
    window.addEventListener("popstate", async () => {
      fadeOut();
      try {
        const res = await fetch(location.href);
        const html = await res.text();
        const dom = new DOMParser().parseFromString(html, "text/html");
        const newRoot = dom.querySelector("#ppv-app-root");
        if (newRoot) {
          root.innerHTML = newRoot.innerHTML;
          window.dispatchEvent(new CustomEvent('ppv:spa-navigate', { detail: { url: location.href } }));
        }
      } finally {
        fadeIn();
      }
    });

    console.log('✅ [SPA] Navigation initialized');
  }

  // Initialize on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSpaNavigation);
  } else {
    initSpaNavigation();
  }

})();
