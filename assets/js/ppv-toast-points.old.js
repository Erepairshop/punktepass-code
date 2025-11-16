/**
 * PunktePass – Universal Scan Toast (v2.0)
 * Multi-language • Confetti • 4 Status Types
 * Author: Erik Borota / PunktePass
 */

document.addEventListener("DOMContentLoaded", () => {
  // ============================================================
// 🔹 PunktePass Universal Scan Toast (Stable v5.0)
// ============================================================
window.ppvShowPointToast = function(type = "success", points = 1, store = "PunktePass") {
  if (document.querySelector(".ppv-point-toast")) return;

  // ikon + szöveg előkészítése
  let icon = "🎉";
  let text = "";
  switch (type) {
    case "duplicate":
      icon = "⚠️";
      text = "Heute bereits gescannt";
      break;
    case "error":
      icon = "❌";
      text = "Offline – wird später synchronisiert";
      break;
    case "pending":
      icon = "⏳";
      text = "Verbindung wird hergestellt...";
      break;
    default:
      icon = "🎉";
      text = "+" + points + " Punkt" + (points > 1 ? "e" : "") + " von " + store;
  }

  // elem létrehozás
  const overlay = document.createElement("div");
  overlay.className = "ppv-point-toast " + type;
  overlay.innerHTML =
    '<div class="ppv-point-toast-inner">' +
    '<div class="ppv-toast-icon">' + icon + "</div>" +
    '<div class="ppv-toast-text">' + text + "</div>" +
    "</div>" +
    (type === "success" ? '<canvas class="ppv-confetti"></canvas>' : "");
  document.body.appendChild(overlay);

  // konfetti animáció
  if (type === "success") {
    const canvas = overlay.querySelector(".ppv-confetti");
    const ctx = canvas.getContext("2d");
    const W = (canvas.width = window.innerWidth);
    const H = (canvas.height = window.innerHeight);
    const colors = ["#00e6ff", "#00ffd5", "#ffffff", "#007bff"];
    const confetti = Array.from({ length: 120 }, () => ({
      x: Math.random() * W,
      y: Math.random() * H - H,
      r: Math.random() * 4 + 2,
      c: colors[Math.floor(Math.random() * colors.length)],
      d: Math.random() * 1 + 1
    }));
    const draw = () => {
      ctx.clearRect(0, 0, W, H);
      confetti.forEach(p => {
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fillStyle = p.c;
        ctx.fill();
        p.y += p.d;
        if (p.y > H) p.y = -10;
      });
    };
    var anim = setInterval(draw, 20);
  }

  // megjelenítés és eltűnés
  setTimeout(() => overlay.classList.add("show"), 50);
  setTimeout(() => {
    overlay.classList.remove("show");
    setTimeout(() => {
      if (type === "success" && typeof anim !== "undefined") clearInterval(anim);
      overlay.remove();
    }, 400);
  }, type === "success" ? 7000 : 5000);
};


    // 🔹 konfetti csak siker esetén
    if (type === "success") {
      const canvas = overlay.querySelector(".ppv-confetti");
      const ctx = canvas.getContext("2d");
      const W = (canvas.width = window.innerWidth);
      const H = (canvas.height = window.innerHeight);
      const colors = ["#00e6ff", "#00ffd5", "#ffffff", "#007bff"];
      const confetti = Array.from({ length: 120 }, () => ({
        x: Math.random() * W,
        y: Math.random() * H - H,
        r: Math.random() * 4 + 2,
        c: colors[Math.floor(Math.random() * colors.length)],
        d: Math.random() * 1 + 1,
      }));

      const draw = () => {
        ctx.clearRect(0, 0, W, H);
        confetti.forEach((p) => {
          ctx.beginPath();
          ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
          ctx.fillStyle = p.c;
          ctx.fill();
          p.y += p.d;
          if (p.y > H) p.y = -10;
        });
      };
      var anim = setInterval(draw, 20);
    }

    // 🔹 animáció megjelenítése
    setTimeout(() => overlay.classList.add("show"), 50);

    // 🔹 6s után eltűnik
    setTimeout(() => {
      overlay.classList.remove("show");
      setTimeout(() => {
        if (type === "success" && anim) clearInterval(anim);
        overlay.remove();
      }, 400);
    }, type === "success" ? 7000 : 5000);
  };
});



