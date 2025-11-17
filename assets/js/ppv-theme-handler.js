/**
 * PunktePass – Global Theme + App Bridge
 * Version: 3.5 POS-Aware Stable
 * ✅ Dark / Light Theme Switch (auto cache reload)
 * ✅ Multilingual Menu Translator (DE/HU/RO/EN)
 * ✅ SPA Navigation Bridge + Toast System
 * ✅ POS Dashboard Safe Mode (skips theme/menu scripts)
 * Author: Erik Borota / PunktePass
 */

(function () {
  const THEME_KEY = "ppv_theme";
  const LANG_KEY = "ppv_lang";
  const DARK_LOGO = "/wp-content/plugins/punktepass/assets/img/logo.webp";
  const LIGHT_LOGO = "/wp-content/plugins/punktepass/assets/img/logo.webp";


  document.addEventListener("DOMContentLoaded", () => {
      // ⛔ Skip entire script on POS dashboard pages
if (document.body.classList.contains("ppv-pos-dashboard")) return;

    const btn = document.getElementById("ppv-theme-toggle");
    const logo = document.querySelector(".ppv-header-logo-min");
if (navigator.serviceWorker?.controller) {
  navigator.serviceWorker.controller.postMessage({ type: "clear-theme-cache" });
}

    if (btn) {
  btn.addEventListener("click", async () => {
    // 🔹 Read current theme from DOM (not closure variable)
    const currentTheme = document.documentElement.getAttribute("data-theme") || localStorage.getItem(THEME_KEY) || "light";
    theme = currentTheme === "light" ? "dark" : "light";

    // 💾 Save to localStorage + cookie
    localStorage.setItem(THEME_KEY, theme);
    document.cookie = `${THEME_KEY}=${theme};path=/;max-age=${60 * 60 * 24 * 365}`;

    // 🎨 Update DOM immediately
    document.documentElement.setAttribute("data-theme", theme);
    document.body.classList.remove("ppv-light", "ppv-dark");
    document.body.classList.add(`ppv-${theme}`);

    // ⚡ CSS újratöltés
    const link = document.getElementById("ppv-theme-css");
    if (link) {
      const base = link.href.split("?")[0];
      link.href = `${base}?t=${Date.now()}`;
    }

    // ⚡ Logo újratöltés
    const logo = document.querySelector(".ppv-header-logo-min");
    if (logo) {
      const src =
        theme === "light"
          ? "/wp-content/plugins/punktepass/assets/img/logo.webp"
          : "/wp-content/plugins/punktepass/assets/img/logo.webp";
      logo.src = src + "?t=" + Date.now();
    }

    // 💾 Save to server (database)
    try {
      const response = await fetch('/wp-json/ppv/v1/theme/set', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ theme }),
        credentials: 'include',
      });
      if (response.ok) {
        console.log("✅ Theme saved to server:", theme);
      }
    } catch (err) {
      console.warn("⚠️ Could not save theme to server (offline?):", err.message);
    }

    // 🧹 Cache törlés
    if (navigator.serviceWorker?.controller) {
      navigator.serviceWorker.controller.postMessage("clear-theme-cache");
    }
  });
}


       // 🧹 Service worker cache ürítése
if (navigator.serviceWorker?.controller) {
  navigator.serviceWorker.controller.postMessage("clear-theme-cache");
}

// 🔁 Biztonsági reflow (azonnali redraw)
setTimeout(() => {
  document.documentElement.style.display = "none";
  document.documentElement.offsetHeight;
  document.documentElement.style.display = "";
}, 100);

// 🔹 LOGO update – biztonságosan DOM után
document.addEventListener("DOMContentLoaded", () => {
  const logoEl = document.querySelector("#ppv-logo img, .ppv-logo img");
  const activeTheme = document.body.dataset.theme || localStorage.getItem(THEME_KEY) || "light";
  if (logoEl) updateLogo(logoEl, activeTheme);
});


  /** ============================
   * 🧩 THEME HELPER
   * ============================ */
  function applyTheme(t, logoEl) {
    document.documentElement.setAttribute("data-theme", t);
    const linkId = "ppv-theme-css";

    let link = document.getElementById(linkId);
    if (!link) {
      link = document.createElement("link");
      link.id = linkId;
      link.rel = "stylesheet";
      document.head.appendChild(link);
    }
    link.href = `/wp-content/plugins/punktepass/assets/css/ppv-theme-${t}.css?v=${Date.now()}`;

    if (logoEl) updateLogo(logoEl, t);
    console.log("🎨 Theme aktiv:", t);
  }

  /** ============================
   * 🎨 THEME INIT
   * ============================ */
  let theme = localStorage.getItem(THEME_KEY) || getCookie(THEME_KEY) || "light";
 
applyTheme(theme);
updateLogo(theme);
  function updateLogo(theme) {
  // Mindig újra keresi a logót (Elementor újraépülhet)
  const logoEl = document.querySelector(".ppv-header-logo-min");
  if (!logoEl) {
    console.warn("⚠️ Logo element not found");
    return;
  }

  // Kép URL logika
  const newSrc =
    theme === "light"
      ? "/wp-content/plugins/punktepass/assets/img/logo.webp"
      : "/wp-content/plugins/punktepass/assets/img/logo.webp";

  // Ha tényleg váltani kell
  const cleanSrc = logoEl.src.split("?")[0];
  if (cleanSrc.endsWith(newSrc)) {
    console.log("ℹ️ Logo already active:", newSrc);
    return;
  }

  // Fade out + új src cache-bypass paraméterrel
  logoEl.style.transition = "opacity 0.4s ease";
  logoEl.style.opacity = "0";
  setTimeout(() => {
    const freshSrc = `${newSrc}?v=${Date.now()}`;
    logoEl.setAttribute("src", freshSrc);
    logoEl.setAttribute("srcset", freshSrc); // Elementor néha srcsetből tölti
    logoEl.removeAttribute("loading"); // kényszeríti a friss betöltést

    // Kép tényleges újratöltés
    const img = new Image();
    img.onload = () => {
      logoEl.style.opacity = "1";
      console.log("✅ Logo refreshed:", freshSrc);
    };
    img.src = freshSrc; // ez indítja az új letöltést
  }, 200);
}



  // ============================
  // ⚡ Optimal Elementor Menü Translator
  // ============================
  (function () {
    const LANG_KEY = "ppv_lang";
    let observerActive = false;

    function translateMenu(lang) {
      const labels = {
        de: { home: "Startseite", points: "Meine Punkte", rewards: "Belohnungen", settings: "Einstellungen" },
        ro: { home: "Acasă", points: "Punctele Mele", rewards: "Recompense", settings: "Setări" },
        hu: { home: "Kezdőlap", points: "Pontjaim", rewards: "Jutalmak", settings: "Beállítások" },
        en: { home: "Home", points: "My Points", rewards: "Rewards", settings: "Settings" },
      };
      const t = labels[lang] || labels.de;

      document.querySelectorAll("#punktepass-menu [data-key]").forEach((el) => {
        const key = el.getAttribute("data-key");
        const title = el.querySelector(".elementor-icon-box-title");
        const anchor = el.querySelector(".elementor-icon-box-icon a");
        if (t[key] && title) title.textContent = t[key];
        if (t[key] && anchor) anchor.setAttribute("aria-label", t[key]);
      });
    }

    function applyOnceStable() {
      const lang =
        localStorage.getItem(LANG_KEY) ||
        document.cookie.match(/(?:^| )ppv_lang=([^;]+)/)?.[1] ||
        "de";
      translateMenu(lang);
    }

    document.addEventListener("DOMContentLoaded", () => setTimeout(applyOnceStable, 400));
    window.addEventListener("ppv-lang-changed", applyOnceStable);

    const obs = new MutationObserver(() => {
      const menu = document.querySelector("#punktepass-menu [data-key]");
      if (menu && !observerActive) {
        observerActive = true;
        setTimeout(applyOnceStable, 200);
        obs.disconnect();
      }
    });
    obs.observe(document.body, { childList: true, subtree: true });
  })();

  /** ============================
   * 🍪 COOKIE GETTER
   * ============================ */
  function getCookie(name) {
    const match = document.cookie.match(new RegExp("(^| )" + name + "=([^;]+)"));
    return match ? match[2] : null;
  }

  // PunktePass – SPA Client Bridge
  if ("serviceWorker" in navigator) {
    navigator.serviceWorker.addEventListener("message", (event) => {
      if (event.data.type === "UPDATE_CONTENT") {
        const parser = new DOMParser();
        const doc = parser.parseFromString(event.data.html, "text/html");
        const newMain = doc.querySelector("#ppv-app-main");
        const currentMain = document.querySelector("#ppv-app-main");
        if (newMain && currentMain) {
          currentMain.innerHTML = newMain.innerHTML;
          history.pushState({}, "", event.data.url);
        }
      }
    });
  }

// Intercept internal link clicks → send NAVIGATE to SW
document.addEventListener("click", (e) => {
  const link = e.target.closest("a");
  if (!link || !link.href.startsWith(location.origin)) return;
  const href = link.getAttribute("href");
  if (!href || href.startsWith("#")) return;
  e.preventDefault();

  if (navigator.serviceWorker.controller) {
    navigator.serviceWorker.controller.postMessage({ type: "NAVIGATE", url: href });
  } else {
    window.location.href = href;
  }
});

/** ============================
 * 🧩 LOGO DEBUG + REALTIME SWITCH
 * ============================ */
document.addEventListener("DOMContentLoaded", () => {
  const logo = document.querySelector("#ppv-logo img, .ppv-logo img");
  console.log("🧠 [PPV_THEME_DEBUG] DOM loaded, theme:", document.body.dataset.theme);
  console.log("🧠 [PPV_THEME_DEBUG] Found logo element:", logo ? logo.src : "❌ none");

  const observer = new MutationObserver(() => {
    console.log("🎨 [PPV_THEME_DEBUG] Theme changed →", document.body.dataset.theme);
    const currentLogo = document.querySelector("#ppv-logo img, .ppv-logo img");
    const currentTheme = document.body.dataset.theme || "light";
    if (currentLogo) updateLogo(currentTheme);
  });

  observer.observe(document.body, { attributes: true, attributeFilter: ["data-theme"] });
});

/** 🔄 LOGO váltás valós időben (custom event) */
document.addEventListener("ppv-theme-changed", () => {
  const activeTheme = document.body.dataset.theme || "light";
  updateLogo(activeTheme);
  console.log("🎨 [PPV_THEME_DEBUG] Logo updated for theme:", activeTheme);
});


  });
  
  /** ============================
 * ⚡ PUNKTEPASS SPA v3.0
 * ============================ */
(function(){
  // instant link intercept
  document.addEventListener("click", async (e)=>{
    const link = e.target.closest("a.ppv-link, .ppv-bottom-nav a");
    if(!link || link.target === "_blank") return;
    const href = link.href;
    if(!href.startsWith(location.origin)) return;
    e.preventDefault();

    document.body.classList.add("ppv-loading");
    document.body.style.opacity="0.8";

    const res = await fetch(href, { headers: {"X-PPV-SPA":"1"} });
    const html = await res.text();
    const dom = new DOMParser().parseFromString(html,"text/html");
    const newRoot = dom.querySelector("#ppv-app-root");
    if(newRoot){
      document.querySelector("#ppv-app-root").innerHTML = newRoot.innerHTML;
      history.pushState({}, "", href);
      window.scrollTo(0,0);
    }

    setTimeout(()=>{
      document.body.style.opacity="1";
      document.body.classList.remove("ppv-loading");
    },150);
  });

  window.addEventListener("popstate",()=>location.reload());

  // universal toast (re-use for points)
  window.ppvToast = (msg,type="info")=>{
    const el=document.createElement("div");
    el.className=`ppv-toast ${type}`;
    el.innerHTML=`<div class="inner">${msg}</div>`;
    document.body.appendChild(el);
    setTimeout(()=>el.classList.add("show"),20);
    setTimeout(()=>el.remove(),3500);
  };
})();

// ============================
// ⚙️ PunktePass Scroll Fix v2
// ============================
(() => {
  const scrollArea = document.querySelector(".ppv-dashboard-netto");
  if (!scrollArea) return;

  let startY = 0;

  scrollArea.addEventListener("touchstart", e => {
    startY = e.touches[0].clientY;
  }, { passive: true });

  scrollArea.addEventListener("touchmove", e => {
    const el = e.currentTarget;
    const top = el.scrollTop;
    const total = el.scrollHeight;
    const visible = el.offsetHeight;
    const currentY = e.touches[0].clientY;

    // ha a tetején vagy alján vagy → nem engedjük a "megfagyást"
    if ((top <= 0 && currentY > startY) || (top + visible >= total && currentY < startY)) {
      e.preventDefault();
    }
  }, { passive: false });
})();
// ============================
// 📌 PunktePass Header Stability Fix
// ============================
(() => {
  const header = document.querySelector(".ppv-welcome-block");
  const content = document.querySelector(".ppv-dashboard-netto");
  if (!header || !content) return;

  // ha a viewport magasság kisebb, mindig fix marad
  const resizeHandler = () => {
    const vh = window.innerHeight;
    content.style.minHeight = `${vh - header.offsetHeight}px`;
  };

  window.addEventListener("resize", resizeHandler);
  window.addEventListener("orientationchange", resizeHandler);
  resizeHandler();
})();



})();
