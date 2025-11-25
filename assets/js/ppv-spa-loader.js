/**
 * PunktePass – SPA Navigation v3.1 (Lightning Mix)
 * Ultra-fast + Cache + Offline + Feedback
 * FIXED: Removed duplicate v2.0 code, added throttle to scroll, optimized prefetch
 * Author: PunktePass / Erik Borota
 */

document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector("#ppv-app-root");
  if (!root) return;

  const cache = new Map();
  const cacheTime = JSON.parse(localStorage.getItem("spa_cache_time") || "{}");
  let scrollThrottle = null; // Throttle for scroll events

  // ✅ Preloader
  if (!document.getElementById("ppv-preloader")) {
    const loader = document.createElement("div");
    loader.id = "ppv-preloader";
    loader.innerHTML = `<div class="ppv-loader-inner"><div class="pulse"></div></div>`;
    document.body.appendChild(loader);
  }

  // ✅ Fade helpers
  const fadeOut = () => {
    root.style.transition = "opacity 0.25s ease";
    root.style.opacity = "0";
    document.body.classList.add("ppv-loading");
  };
  const fadeIn = () => {
    root.style.opacity = "1";
    document.body.classList.remove("ppv-loading");
  };

  // ✅ Close QR modal before navigation (prevents flash)
  const closeModals = () => {
    const modal = document.getElementById("ppv-user-qr");
    const overlay = document.getElementById("ppv-qr-overlay");
    if (modal) modal.classList.remove("show");
    if (overlay) overlay.classList.remove("show");
    document.body.classList.remove("qr-modal-open");
    document.body.style.overflow = "";
  };

  // ✅ Menü intercept (with navigation guard)
  let isNavigating = false;
  document.querySelectorAll(".ppv-bottom-nav a").forEach(link => {
    link.addEventListener("click", async e => {
      e.preventDefault();

      // Block rapid clicks
      if (isNavigating) return;

      // ✅ Close any open modals before navigation
      closeModals();

      const url = link.href;
      if (!url || url === window.location.href) return;

      isNavigating = true;
      fadeOut();

      let html = cache.get(url);
      const now = Date.now();
      const validCache = cacheTime[url] && now - cacheTime[url] < 60000;

      if (!html && localStorage.getItem(url)) html = localStorage.getItem(url);
      if (!validCache) {
        try {
          const res = await fetch(url, { headers: { "X-PPV-SPA": "1" } });
          html = await res.text();
          cache.set(url, html);
          localStorage.setItem(url, html);
          cacheTime[url] = now;
          localStorage.setItem("spa_cache_time", JSON.stringify(cacheTime));
        } catch {
          showToast("⚠️ Offline-Modus aktiv", "warn");
          isNavigating = false;
        }
      }

      if (!html) {
        isNavigating = false;
        return fadeIn();
      }

      const dom = new DOMParser().parseFromString(html, "text/html");
      const newRoot = dom.querySelector("#ppv-app-root");
      if (newRoot) root.innerHTML = newRoot.innerHTML;

      document.querySelectorAll(".ppv-bottom-nav a").forEach(a => a.classList.remove("active"));
      link.classList.add("active");

      window.history.pushState({}, "", url);
      setTimeout(fadeIn, 80);
      setTimeout(() => document.body.classList.add("ppv-loaded-flash"), 50);
      setTimeout(() => document.body.classList.remove("ppv-loaded-flash"), 250);

      // Reset navigation guard after transition
      setTimeout(() => { isNavigating = false; }, 300);
    });
  });

  // ✅ Scroll-restore (THROTTLED - max 1x per 200ms)
  window.addEventListener("scroll", () => {
    if (scrollThrottle) return;
    scrollThrottle = setTimeout(() => {
      sessionStorage.setItem("scroll_" + location.pathname, window.scrollY);
      scrollThrottle = null;
    }, 200);
  });
  const scrollPos = sessionStorage.getItem("scroll_" + location.pathname);
  if (scrollPos) setTimeout(() => window.scrollTo(0, scrollPos), 100);

  // ✅ Offline / online toast
  window.addEventListener("offline", () => showToast("⚠️ Offline-Modus aktiv", "warn"));
  window.addEventListener("online", () => showToast("🔄 Verbindung wiederhergestellt", "ok"));

  // ✅ Toast rendszer
  function showToast(msg, type = "info") {
    const el = document.createElement("div");
    el.className = `ppv-toast ${type}`;
    el.innerHTML = `<div class="ppv-toast-inner">${msg}</div>`;
    document.body.appendChild(el);
    setTimeout(() => el.classList.add("show"), 10);
    setTimeout(() => el.classList.remove("show"), 2500);
    setTimeout(() => el.remove(), 3000);
  }

  // ✅ History back
  window.addEventListener("popstate", async () => {
    closeModals(); // ✅ Close modals before navigation
    fadeOut();
    try {
      const res = await fetch(location.href);
      const html = await res.text();
      const dom = new DOMParser().parseFromString(html, "text/html");
      const newRoot = dom.querySelector("#ppv-app-root");
      if (newRoot) root.innerHTML = newRoot.innerHTML;
    } finally {
      fadeIn();
    }
  });
});
