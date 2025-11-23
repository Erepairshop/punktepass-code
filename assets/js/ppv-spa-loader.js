
/**
 * PunktePass – SPA Navigation v2.0 (Instant + Fade + Cache)
 * Author: PunktePass / Erik Borota
 */

document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector("#ppv-app-root");
  if (!root) return;

  const cache = new Map();

  // ✅ Preloader létrehozás (ha nincs)
  if (!document.getElementById("ppv-preloader")) {
    const loader = document.createElement("div");
    loader.id = "ppv-preloader";
    loader.innerHTML = `<div class="ppv-loader-inner"><div class="pulse"></div></div>`;
    document.body.appendChild(loader);
  }

  // ✅ Fade transition
  const fadeOut = () => {
    root.style.transition = "opacity 0.25s ease";
    root.style.opacity = "0";
    document.body.classList.add("ppv-loading");
  };
  const fadeIn = () => {
    root.style.opacity = "1";
    document.body.classList.remove("ppv-loading");
  };

  // ✅ Menü link intercept
  document.querySelectorAll(".ppv-bottom-nav a").forEach(link => {
    link.addEventListener("click", async e => {
      e.preventDefault();
      const url = link.href;
      if (!url || url === window.location.href) return;

      fadeOut();

      let html = cache.get(url);
      if (!html) {
        const res = await fetch(url, { headers: { "X-PPV-SPA": "1" } });
        html = await res.text();
        cache.set(url, html);
      }

      const dom = new DOMParser().parseFromString(html, "text/html");
      const newRoot = dom.querySelector("#ppv-app-root");

      if (newRoot) {
        root.innerHTML = newRoot.innerHTML;
        history.pushState({}, "", url);

        // 🔄 Dispatch custom event for JS re-initialization
        window.dispatchEvent(new CustomEvent('ppv:spa-navigate', { detail: { url } }));
      }

      document.querySelectorAll(".ppv-bottom-nav a").forEach(a => a.classList.remove("active"));
      link.classList.add("active");

      setTimeout(fadeIn, 80);
    });
  });

  // ✅ History kezelő
  window.addEventListener("popstate", async () => {
    fadeOut();
    const res = await fetch(location.href);
    const html = await res.text();
    const dom = new DOMParser().parseFromString(html, "text/html");
    const newRoot = dom.querySelector("#ppv-app-root");
    if (newRoot) {
      root.innerHTML = newRoot.innerHTML;
      // 🔄 Dispatch custom event for JS re-initialization
      window.dispatchEvent(new CustomEvent('ppv:spa-navigate', { detail: { url: location.href } }));
    }
    fadeIn();

  });
});

/**
 * PunktePass – SPA Navigation v3.0 (Lightning Mix)
 * Ultra-fast + Cache + Offline + Feedback
 * Author: PunktePass / Erik Borota
 */

document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector("#ppv-app-root");
  if (!root) return;

  const cache = new Map();
  const cacheTime = JSON.parse(localStorage.getItem("spa_cache_time") || "{}");

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

  // ✅ Menü intercept
  document.querySelectorAll(".ppv-bottom-nav a").forEach(link => {
    link.addEventListener("click", async e => {
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
          localStorage.setItem(url, html);
          cacheTime[url] = now;
          localStorage.setItem("spa_cache_time", JSON.stringify(cacheTime));
        } catch {
          showToast("⚠️ Offline-Modus aktiv", "warn");
        }
      }

      if (!html) return fadeIn();

      const dom = new DOMParser().parseFromString(html, "text/html");
      const newRoot = dom.querySelector("#ppv-app-root");
      if (newRoot) {
        root.innerHTML = newRoot.innerHTML;

        // 🔄 Dispatch custom event for JS re-initialization
        window.dispatchEvent(new CustomEvent('ppv:spa-navigate', { detail: { url } }));
      }

      document.querySelectorAll(".ppv-bottom-nav a").forEach(a => a.classList.remove("active"));
      link.classList.add("active");

      window.history.pushState({}, "", url);
      setTimeout(fadeIn, 80);
      setTimeout(() => document.body.classList.add("ppv-loaded-flash"), 50);
      setTimeout(() => document.body.classList.remove("ppv-loaded-flash"), 250);
    });
  });

  // ✅ Scroll-restore
  window.addEventListener("scroll", () => {
    sessionStorage.setItem("scroll_" + location.pathname, window.scrollY);
  });
  const scrollPos = sessionStorage.getItem("scroll_" + location.pathname);
  if (scrollPos) setTimeout(() => window.scrollTo(0, scrollPos), 100);

  // ✅ Prefetch fő oldalak
  window.addEventListener("load", () => {
    ["/user_dashboard", "/meine-punkte", "/belohnungen", "/einstellungen"].forEach(u =>
      fetch(u, { headers: { "X-PPV-SPA": "1" } })
    );
  });

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
    fadeOut();
    try {
      const res = await fetch(location.href);
      const html = await res.text();
      const dom = new DOMParser().parseFromString(html, "text/html");
      const newRoot = dom.querySelector("#ppv-app-root");
      if (newRoot) {
        root.innerHTML = newRoot.innerHTML;
        // 🔄 Dispatch custom event for JS re-initialization
        window.dispatchEvent(new CustomEvent('ppv:spa-navigate', { detail: { url: location.href } }));
      }
    } finally {
      fadeIn();
    }
  });
});
