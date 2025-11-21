/**
 * PunktePass – Global PWA Controller (v2.0)
 * Turbo.js compatible
 * Minden oldalra betöltődik (Dashboard, Points, Rewards, stb.)
 */

console.log("✅ [PPV_GLOBAL] v2.0 active (Turbo-compatible)");

// 🚀 Turbo handles transitions now - removed beforeunload/pageshow opacity code
// OLD CODE REMOVED:
// window.addEventListener("beforeunload", () => { ... });
// window.addEventListener("pageshow", () => { ... });

// 🔹 Instant navigáció – cache előtöltés (only for non-Turbo links)
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("a[href^='/']:not([data-turbo='false'])").forEach((link) => {
    link.addEventListener("mouseenter", () => {
      const url = link.getAttribute("href");
      if (url && !url.startsWith("#")) {
        // Turbo handles prefetching, but we can hint
        if (window.Turbo) {
          // Turbo will handle this
        } else {
          fetch(url, { cache: "force-cache" });
        }
      }
    });
  });
});

// 🔹 Service Worker státusz
if ("serviceWorker" in navigator) {
  navigator.serviceWorker.ready
    .then(() => console.log("🟢 [PPV_SW] ready"))
    .catch(() => console.log("⚠️ [PPV_SW] not active"));
}
