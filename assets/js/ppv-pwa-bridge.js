/**
 * PunktePass – Bridge v2
 * Stabil REST + SPA kezelő réteg PWA-hoz
 * Verzió: 2.0 (session + lang + auto refresh)
 */

console.log("🧩 PunktePass Bridge v2 aktiv");

// 🔹 Token kezelés automatikusan (ha a PHP még nem adott)
if (!window.ppvAuthToken && typeof ppv_bridge_user !== "undefined" && ppv_bridge_user.is_logged) {
  console.warn("🧩 Kein Token gefunden – erstelle neuen Token für User", ppv_bridge_user.id);
  fetch(ppv_bridge.rest + "auth/create", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ user_id: ppv_bridge_user.id })
  })
  .then(r => r.json())
  .then(j => {
    if (j.token) {
      window.ppvAuthToken = j.token;
      console.log("✅ Neuer Token gesetzt:", j.token.substring(0,10)+"…");
    }
  })
  .catch(e => console.error("❌ Token-Erstellung fehlgeschlagen:", e));
}


const PPVBridge = {
  base: ppv_bridge.rest || "",
  nonce: ppv_bridge.nonce || "",

  async get(endpoint) {
    try {
      const res = await fetch(this.base + endpoint, {
        method: "GET",
        credentials: "include",
        headers: {
          "X-WP-Nonce": this.nonce,
          "Cache-Control": "no-cache",
        },
      });
      if (!res.ok) throw new Error(res.status);
      return await res.json();
    } catch (err) {
      console.error("❌ Bridge REST Fehler:", err);
      return { error: true, msg: err.message };
    }
  },

  async check() {
    const data = await this.get("bridge");
    console.log("🧠 Bridge check:", data);
    if (data.error) {
      alert("Bridge-Fehler: " + data.msg);
    } else if (data.session === "none") {
      console.warn("⚠️ Keine aktive Session erkannt!");
    }
  },

  reloadIfStuck() {
    const content = document.querySelector(".ppv-dashboard-netto, #ppv-my-points-wrapper");
    if (!content) return;
    setTimeout(() => {
      if (content.innerHTML.trim() === "") {
        console.warn("🔄 Bridge Auto-Reload ausgeführt");
        location.reload();
      }
    }, 2000);
  },
};

// Automatikusan ellenőriz PWA-ban
document.addEventListener("DOMContentLoaded", () => {
  PPVBridge.check();
  PPVBridge.reloadIfStuck();
});

// ✅ Service Worker & Install Prompt
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/service-worker.js').then(reg => {
    console.log('🟢 PunktePass SW ready:', reg.scope);
  }).catch(err => console.error('❌ SW Error:', err));
}

window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  window.deferredPrompt = e;
  console.log('📲 PunktePass install prompt available');
});

