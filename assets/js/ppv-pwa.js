/**
 * PunktePass – PWA Navigation v3.3 FINAL
 * iOS SAFE MODE – csak REST-oldalak mennek SPA-ban
 */

document.addEventListener("DOMContentLoaded", () => {
  const html = document.documentElement;
  const isStandalone = window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone;
  if (isStandalone) document.body.classList.add("ppv-app-mode");

  console.log("📱 PWA gestartet (v3.3 Final)");

  document.querySelectorAll("a[href]").forEach(link => {
    const href = link.getAttribute("href");
    if (!href || href.startsWith("#") || href.startsWith("mailto:") || href.startsWith("tel:")) return;
    if (href.startsWith("http") && !href.includes(location.host)) return;

    link.addEventListener("click", e => {
      // csak belső navigációt interceptálunk
      e.preventDefault();
      html.style.transition = "opacity 0.25s ease";
      html.style.opacity = "0.3";

      // 🔹 REST-alapú oldalak listája (SPA kompatibilis)
      const spaPages = ["user-dashboard", "belohnungen", "rewards", "pos-admin"];

      const isSpa = spaPages.some(page => href.includes(page));

      if (isSpa) {
        // SPA fade load
        fetch(href, { cache: "no-cache" })
          .then(res => res.text())
          .then(text => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(text, "text/html");
            const newBody = doc.querySelector("body");
            if (!newBody) throw new Error("Keine BODY gefunden");

            // csak a dashboard-container tartalmat cseréljük
            const newMain = newBody.querySelector(".ppv-dashboard-netto") || newBody;
            const current = document.querySelector(".ppv-dashboard-netto") || document.body;

            current.innerHTML = newMain.innerHTML;
            window.scrollTo(0, 0);
            html.style.opacity = "1";
            history.pushState(null, "", href);
          })
          .catch(err => {
            console.error("⚠️ SPA Fehler:", err);
            window.location.href = href;
          });
      } else {
        // minden más oldalhoz teljes reload (shortcode, PHP stb.)
        setTimeout(() => {
          window.location.href = href;
        }, 100);
      }
    });
  });
});
