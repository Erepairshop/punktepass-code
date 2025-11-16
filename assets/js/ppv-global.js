/**
 * 🌍 PunktePass – Global PWA Controller (v1.0)
 * Minden oldalra betöltődik (Dashboard, Points, Rewards, stb.)
 */

console.log("✅ [PPV_GLOBAL] active");

// 🔹 Page fade-in / fade-out animáció
window.addEventListener("beforeunload", () => {
  document.body.style.opacity = "0";
  document.body.style.transition = "opacity 0.2s ease-out";
});
window.addEventListener("pageshow", () => {
  document.body.style.opacity = "1";
});

// 🔹 Instant navigáció – cache előtöltés
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("a[href^='/']").forEach((link) => {
    link.addEventListener("mouseenter", () => {
      const url = link.getAttribute("href");
      if (url && !url.startsWith("#")) fetch(url, { cache: "force-cache" });
    });
  });
});

// 🔹 Egyszerű loader overlay (betöltéskor)
document.addEventListener("DOMContentLoaded", () => {
  const loader = document.createElement("div");
  loader.id = "ppv-loader";
  loader.innerHTML = '<div class="pulse"></div>';
  document.body.appendChild(loader);
  setTimeout(() => loader.remove(), 350);
});

// 🔹 Service Worker státusz
if ("serviceWorker" in navigator) {
  navigator.serviceWorker.ready
    .then(() => console.log("🟢 [PPV_SW] ready"))
    .catch(() => console.log("⚠️ [PPV_SW] not active"));
}
