/**
 * PunktePass – Händler Rewards + Redeem Management
 * Version: 5.0 Unified
 * Kompatibel: class-ppv-rewards.php v5.0
 */

console.log("✅ PunktePass Rewards+Redeem JS v5.0 aktiv");

document.addEventListener("DOMContentLoaded", function () {
  const base = ppv_rewards_rest.base;
  const storeID = window.PPV_STORE_ID || 0;

  // ============================================================
// 🔹 TAB VÁLTÁS (Fix v5.1)
// ============================================================
const tabButtons = document.querySelectorAll(".ppv-tab-btn");
const tabs = document.querySelectorAll(".ppv-tab");

tabButtons.forEach((btn) => {
  btn.addEventListener("click", () => {
    const target = btn.dataset.tab;

    // remove all active
    tabButtons.forEach((b) => b.classList.remove("active"));
    tabs.forEach((t) => t.classList.remove("active"));

    // activate current
    btn.classList.add("active");
    const activeTab = document.getElementById("ppv-tab-" + target);
    if (activeTab) activeTab.classList.add("active");
  });
});


  // ============================================================
  // 🧩 TOAST FUNKCIÓ
  // ============================================================
  function showToast(msg, type = "info") {
    const t = document.createElement("div");
    t.className = `ppv-toast ${type}`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.classList.add("show"), 50);
    setTimeout(() => {
      t.classList.remove("show");
      setTimeout(() => t.remove(), 400);
    }, 3000);
  }

  // ============================================================
  // 🎁 REWARDS LIST LADEN
  // ============================================================
  async function loadRewards() {
    const list = document.getElementById("ppv-rewards-list");
    if (!list) return;
    list.innerHTML = "<p>⏳ Lade Prämien...</p>";

    try {
      const res = await fetch(`${base}rewards/list?store_id=${storeID}`);
      const data = await res.json();

      if (data.success && Array.isArray(data.rewards)) {
        list.innerHTML = "";
        data.rewards.forEach((r) => {
          const card = document.createElement("div");
          card.className = "ppv-reward-item glass-card";
          card.innerHTML = `
            <h4>${r.title}</h4>
            <p>${r.description || ""}</p>
            <small>⭐ ${r.required_points} Punkte</small><br>
            <small>${r.action_type || ""}: ${r.action_value || ""}</small><br>
            <button class="ppv-delete" data-id="${r.id}">🗑️ Löschen</button>
          `;
          list.appendChild(card);
        });
      } else {
        list.innerHTML = "<p>ℹ️ Keine Prämien vorhanden.</p>";
      }
    } catch (err) {
      console.error("❌ Fehler beim Laden:", err);
      list.innerHTML = "<p>⚠️ Fehler beim Laden.</p>";
    }
  }

  // ============================================================
  // 💾 REWARD SPEICHERN
  // ============================================================
  const form = document.getElementById("ppv-reward-form");
  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const body = {
        store_id: storeID,
        title: form.title.value.trim(),
        required_points: parseInt(form.required_points.value),
        description: form.description.value.trim(),
        action_type: form.action_type.value,
        action_value: form.action_value.value.trim(),
      };

      try {
        const res = await fetch(`${base}rewards/save`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(body),
        });
        const data = await res.json();
        showToast(data.message || "✅ Gespeichert", "success");
        form.reset();
        loadRewards();
      } catch (err) {
        console.error(err);
        showToast("⚠️ Fehler beim Speichern", "error");
      }
    });
  }

  // ============================================================
  // 🗑️ REWARD LÖSCHEN
  // ============================================================
  document.body.addEventListener("click", async (e) => {
    if (e.target.classList.contains("ppv-delete")) {
      const id = e.target.dataset.id;
      if (!confirm("Prämie löschen?")) return;
      await fetch(`${base}rewards/delete`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id, store_id: storeID }),
      });
      showToast("🗑️ Prämie gelöscht", "success");
      loadRewards();
    }
  });

  // ============================================================
  // ✅ REDEEMS LADEN
  // ============================================================
  async function loadRedeems() {
    const list = document.getElementById("ppv-redeem-list");
    if (!list) return;
    list.innerHTML = "<p>⏳ Lade Einlösungen...</p>";

    try {
      const res = await fetch(`${base}redeem/list?store_id=${storeID}`);
      const data = await res.json();

      if (data.success && Array.isArray(data.items)) {
        list.innerHTML = "";
        data.items.forEach((r) => {
          const el = document.createElement("div");
          el.className = "ppv-redeem-item glass-card";
          el.innerHTML = `
            <strong>${r.reward_title || "Unbekannt"}</strong><br>
            <small>User: ${r.user_email || r.user_id}</small><br>
            <small>Status: ${r.status}</small><br>
            ${
              r.status === "approved"
                ? "<span style='opacity:.6'>✅ Bestätigt</span>"
                : `
              <button class="ppv-approve" data-id="${r.id}">✅</button>
              <button class="ppv-reject" data-id="${r.id}">❌</button>`
            }
          `;
          list.appendChild(el);
        });
      } else {
        list.innerHTML = "<p>ℹ️ Keine Einlösungen.</p>";
      }
    } catch (err) {
      console.error("❌ Fehler beim Laden:", err);
      list.innerHTML = "<p>⚠️ Fehler beim Laden.</p>";
    }
  }

  // ============================================================
  // ⚙️ REDEEM UPDATE (Approve / Reject)
  // ============================================================
  document.body.addEventListener("click", async (e) => {
    if (e.target.classList.contains("ppv-approve") || e.target.classList.contains("ppv-reject")) {
      const id = e.target.dataset.id;
      const status = e.target.classList.contains("ppv-approve") ? "approved" : "cancelled";
      e.target.disabled = true;

      try {
        const res = await fetch(`${base}redeem/update`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ id, status, store_id: storeID }),
        });
        const data = await res.json();
        showToast(data.message || "✅ Aktualisiert", "success");
        loadRedeems();
        loadRedeemLog();
      } catch (err) {
        showToast("⚠️ Fehler beim Update", "error");
      }
    }
  });

  // ============================================================
  // 📜 REDEEM LOG + AUTO-REFRESH
  // ============================================================
  async function loadRedeemLog() {
    const log = document.getElementById("ppv-log-list");
    if (!log) return;
    try {
      const res = await fetch(`${base}redeem/log`);
      const data = await res.json();
      if (data.success && data.items.length) {
        log.innerHTML = "<ul>" + data.items.map((r) => `
          <li>${r.status === "approved" ? "✅" : "🕓"} ${r.user_email || "?"} – ${r.points_spent || 0} Punkte</li>
        `).join("") + "</ul>";
      } else {
        log.innerHTML = "<p>ℹ️ Keine Logeinträge.</p>";
      }
    } catch (err) {
      log.innerHTML = "<p>⚠️ Fehler beim Log laden.</p>";
    }
  }

  // ============================================================
  // 🔔 AUTO-POLLING (Neue Einlösungen Popup)
  // ============================================================
  let lastUpdate = 0;
  async function checkNewRedeems() {
    try {
      const res = await fetch(`${base}redeem/ping?_=${Date.now()}`);
      const data = await res.json();
      if (data.success && data.last_update > lastUpdate) {
        showPopupNotification("🎁 Neue Einlösung entdeckt!");
        loadRedeems();
        loadRedeemLog();
        lastUpdate = data.last_update;
      }
    } catch (err) {
      console.warn("Polling Fehler:", err);
    }
  }

  function showPopupNotification(msg) {
    document.querySelectorAll(".ppv-popup-alert").forEach((el) => el.remove());
    const popup = document.createElement("div");
    popup.className = "ppv-popup-alert";
    popup.innerHTML = `
      <div class="ppv-popup-inner">
        <h3>🎁 Neue Einlösung!</h3>
        <p>${msg}</p>
        <button id="ppv-popup-close">OK</button>
      </div>`;
    document.body.appendChild(popup);
    document.getElementById("ppv-popup-close").onclick = () => popup.remove();
  }

  // ============================================================
  // 🚀 INITIALISIERUNG
  // ============================================================
  loadRewards();
  loadRedeems();
  loadRedeemLog();
  setInterval(checkNewRedeems, 15000);
  
  function playRedeemSound() {
  try {
    const audio = new Audio(
      "https://cdn.pixabay.com/download/audio/2022/03/15/audio_dba733ce07.mp3"
    );
    audio.volume = 0.5;
    audio.play().catch(() => console.warn("🔇 Ton blockiert (Autoplay)."));
  } catch (e) {
    console.warn("Audio Fehler:", e);
  }
}

// 🎵 illeszd be a showPopupNotification() végére:
playRedeemSound();

});
