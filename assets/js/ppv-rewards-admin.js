/**
 * PunktePass – Händler Rewards + Redeem Management
 * Version: 5.0 Unified
 * Kompatibel: class-ppv-rewards.php v5.0
 */


document.addEventListener("DOMContentLoaded", function () {
  const base = ppv_rewards_rest.base;

  // 🌐 Language detection
  const detectLang = () => document.cookie.match(/ppv_lang=([a-z]{2})/)?.[1] || localStorage.getItem('ppv_lang') || 'ro';
  const LANG = detectLang();
  const T = {
    de: {
      points: 'Punkte',
      loading_rewards: 'Lade Prämien...',
      delete_btn: 'Löschen',
      no_rewards: 'Keine Prämien vorhanden.',
      load_error: 'Fehler beim Laden.',
      saved: 'Gespeichert',
      save_error: 'Fehler beim Speichern',
      confirm_delete: 'Prämie löschen?',
      deleted: 'Prämie gelöscht',
      loading_redeems: 'Lade Einlösungen...',
      unknown: 'Unbekannt',
      confirmed: 'Bestätigt',
      no_redeems: 'Keine Einlösungen.',
      updated: 'Aktualisiert',
      update_error: 'Fehler beim Update',
      no_log: 'Keine Logeinträge.',
      log_error: 'Fehler beim Log laden.',
      new_redemption: 'Neue Einlösung!',
      new_redemption_detected: 'Neue Einlösung entdeckt!',
      reward: 'Belohnung',
      created: 'erstellt',
      reward_updated: 'aktualisiert',
      reward_deleted: 'gelöscht'
    },
    hu: {
      points: 'pont',
      loading_rewards: 'Jutalmak betöltése...',
      delete_btn: 'Törlés',
      no_rewards: 'Nincs elérhető jutalom.',
      load_error: 'Betöltési hiba.',
      saved: 'Mentve',
      save_error: 'Mentési hiba',
      confirm_delete: 'Törlöd a jutalmat?',
      deleted: 'Jutalom törölve',
      loading_redeems: 'Beváltások betöltése...',
      unknown: 'Ismeretlen',
      confirmed: 'Jóváhagyva',
      no_redeems: 'Nincs beváltás.',
      updated: 'Frissítve',
      update_error: 'Frissítési hiba',
      no_log: 'Nincs naplóbejegyzés.',
      log_error: 'Napló betöltési hiba.',
      new_redemption: 'Új beváltás!',
      new_redemption_detected: 'Új beváltás érkezett!',
      reward: 'Jutalom',
      created: 'létrehozva',
      reward_updated: 'frissítve',
      reward_deleted: 'törölve'
    },
    ro: {
      points: 'puncte',
      loading_rewards: 'Se încarcă premiile...',
      delete_btn: 'Șterge',
      no_rewards: 'Nu există premii.',
      load_error: 'Eroare la încărcare.',
      saved: 'Salvat',
      save_error: 'Eroare la salvare',
      confirm_delete: 'Ștergi premiul?',
      deleted: 'Premiu șters',
      loading_redeems: 'Se încarcă răscumpărările...',
      unknown: 'Necunoscut',
      confirmed: 'Confirmat',
      no_redeems: 'Nu există răscumpărări.',
      updated: 'Actualizat',
      update_error: 'Eroare la actualizare',
      no_log: 'Nu există înregistrări.',
      log_error: 'Eroare la încărcarea jurnalului.',
      new_redemption: 'Răscumpărare nouă!',
      new_redemption_detected: 'Răscumpărare nouă detectată!',
      reward: 'Premiu',
      created: 'creat',
      reward_updated: 'actualizat',
      reward_deleted: 'șters'
    }
  }[LANG] || {
    points: 'Punkte', loading_rewards: 'Lade Prämien...', delete_btn: 'Löschen', no_rewards: 'Keine Prämien vorhanden.',
    load_error: 'Fehler beim Laden.', saved: 'Gespeichert', save_error: 'Fehler beim Speichern', confirm_delete: 'Prämie löschen?',
    deleted: 'Prämie gelöscht', loading_redeems: 'Lade Einlösungen...', unknown: 'Unbekannt', confirmed: 'Bestätigt',
    no_redeems: 'Keine Einlösungen.', updated: 'Aktualisiert', update_error: 'Fehler beim Update', no_log: 'Keine Logeinträge.',
    log_error: 'Fehler beim Log laden.', new_redemption: 'Neue Einlösung!', new_redemption_detected: 'Neue Einlösung entdeckt!',
    reward: 'Belohnung', created: 'erstellt', reward_updated: 'aktualisiert', reward_deleted: 'gelöscht'
  };

  // ============================================================
  // 🏪 FILIALE SUPPORT: Store ID Detection
  // ============================================================
  let storeID = 0;

  // ALWAYS prioritize window.PPV_STORE_ID over sessionStorage
  if (window.PPV_STORE_ID && Number(window.PPV_STORE_ID) > 0) {
    storeID = Number(window.PPV_STORE_ID);
    // Clear sessionStorage if it differs
    const cachedStoreId = sessionStorage.getItem("ppv_store_id");
    if (cachedStoreId && Number(cachedStoreId) !== storeID) {
      sessionStorage.removeItem("ppv_store_id");
    }
  } else {
    storeID = Number(sessionStorage.getItem("ppv_store_id") || 0) || 0;
    console.warn(`⚠️ [REWARDS-ADMIN] window.PPV_STORE_ID not set, using sessionStorage: ${storeID}`);
  }

  if (storeID > 0) {
    sessionStorage.setItem("ppv_store_id", storeID);
  }

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
    list.innerHTML = `<p>⏳ ${T.loading_rewards}</p>`;

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
            <small>⭐ ${r.required_points} ${T.points}</small><br>
            <small>${r.action_type || ""}: ${r.action_value || ""}</small><br>
            <button class="ppv-delete" data-id="${r.id}">🗑️ ${T.delete_btn}</button>
          `;
          list.appendChild(card);
        });
      } else {
        list.innerHTML = `<p>ℹ️ ${T.no_rewards}</p>`;
      }
    } catch (err) {
      console.error("❌ Load error:", err);
      list.innerHTML = `<p>⚠️ ${T.load_error}</p>`;
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
        showToast(data.message || `✅ ${T.saved}`, "success");
        form.reset();
        loadRewards();
      } catch (err) {
        console.error(err);
        showToast(`⚠️ ${T.save_error}`, "error");
      }
    });
  }

  // ============================================================
  // 🗑️ REWARD LÖSCHEN
  // ============================================================
  document.body.addEventListener("click", async (e) => {
    if (e.target.classList.contains("ppv-delete")) {
      const id = e.target.dataset.id;
      if (!confirm(T.confirm_delete)) return;
      await fetch(`${base}rewards/delete`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id, store_id: storeID }),
      });
      showToast(`🗑️ ${T.deleted}`, "success");
      loadRewards();
    }
  });

  // ============================================================
  // ✅ REDEEMS LADEN
  // ============================================================
  async function loadRedeems() {
    const list = document.getElementById("ppv-redeem-list");
    if (!list) return;
    list.innerHTML = `<p>⏳ ${T.loading_redeems}</p>`;

    try {
      const res = await fetch(`${base}redeem/list?store_id=${storeID}`);
      const data = await res.json();

      if (data.success && Array.isArray(data.items)) {
        list.innerHTML = "";
        data.items.forEach((r) => {
          const el = document.createElement("div");
          el.className = "ppv-redeem-item glass-card";
          el.innerHTML = `
            <strong>${r.reward_title || T.unknown}</strong><br>
            <small>User: ${r.user_email || r.user_id}</small><br>
            <small>Status: ${r.status}</small><br>
            ${
              r.status === "approved"
                ? `<span style='opacity:.6'>✅ ${T.confirmed}</span>`
                : `
              <button class="ppv-approve" data-id="${r.id}">✅</button>
              <button class="ppv-reject" data-id="${r.id}">❌</button>`
            }
          `;
          list.appendChild(el);
        });
      } else {
        list.innerHTML = `<p>ℹ️ ${T.no_redeems}</p>`;
      }
    } catch (err) {
      console.error("❌ Load error:", err);
      list.innerHTML = `<p>⚠️ ${T.load_error}</p>`;
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
        showToast(data.message || `✅ ${T.updated}`, "success");
        loadRedeems();
        loadRedeemLog();
      } catch (err) {
        showToast(`⚠️ ${T.update_error}`, "error");
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
          <li>${r.status === "approved" ? "✅" : "🕓"} ${r.user_email || "?"} – ${r.points_spent || 0} ${T.points}</li>
        `).join("") + "</ul>";
      } else {
        log.innerHTML = `<p>ℹ️ ${T.no_log}</p>`;
      }
    } catch (err) {
      log.innerHTML = `<p>⚠️ ${T.log_error}</p>`;
    }
  }

  // ============================================================
  // 🔔 POPUP NOTIFICATION
  // ============================================================
  function showPopupNotification(msg) {
    document.querySelectorAll(".ppv-popup-alert").forEach((el) => el.remove());
    const popup = document.createElement("div");
    popup.className = "ppv-popup-alert";
    popup.innerHTML = `
      <div class="ppv-popup-inner">
        <h3>🎁 ${T.new_redemption}</h3>
        <p>${msg}</p>
        <button id="ppv-popup-close">OK</button>
      </div>`;
    document.body.appendChild(popup);
    document.getElementById("ppv-popup-close").onclick = () => popup.remove();
    playRedeemSound();
  }

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

  // ============================================================
  // 📡 ABLY REAL-TIME + POLLING FALLBACK
  // ============================================================
  const config = window.ppv_rewards_rest || {};
  let pollInterval = null;
  let lastUpdate = 0;

  async function checkNewRedeems() {
    try {
      const res = await fetch(`${base}redeem/ping?_=${Date.now()}`);
      const data = await res.json();
      if (data.success && data.last_update > lastUpdate) {
        showPopupNotification(`🎁 ${T.new_redemption_detected}`);
        loadRedeems();
        loadRedeemLog();
        lastUpdate = data.last_update;
      }
    } catch (err) {
      console.warn("Polling Fehler:", err);
    }
  }

  function startPolling() {
    if (pollInterval) return;
    pollInterval = setInterval(checkNewRedeems, 15000);
  }

  function initRealtime() {
    if (config.ably && config.ably.key && typeof Ably !== 'undefined') {

      const ably = new Ably.Realtime({ key: config.ably.key });
      const channel = ably.channels.get(config.ably.channel);

      ably.connection.on('connected', () => {
        if (pollInterval) {
          clearInterval(pollInterval);
          pollInterval = null;
        }
      });

      ably.connection.on('disconnected', () => {
        startPolling();
      });

      ably.connection.on('failed', (err) => {
        startPolling();
      });

      // 📡 Handle reward requests
      channel.subscribe('reward-request', (message) => {
        showPopupNotification(`🎁 ${T.new_redemption}: ${message.data.reward_title || T.reward}`);
        loadRedeems();
        loadRedeemLog();
      });

      // 📡 Handle reward updates (CRUD)
      channel.subscribe('reward-update', (message) => {
        showToast(`🎁 ${T.reward} ${message.data.action === 'created' ? T.created : message.data.action === 'updated' ? T.reward_updated : T.reward_deleted}`, 'info');
        loadRewards();
      });

    } else {
      startPolling();
    }
  }

  // ============================================================
  // 🚀 INITIALISIERUNG
  // ============================================================
  loadRewards();
  loadRedeems();
  loadRedeemLog();
  initRealtime();

});
