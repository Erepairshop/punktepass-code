/**
 * PunktePass – Kassenscanner & Kampagnen v5.3 COMPLETE
 * ✅ Save függvény integrálva
 * ✅ Összes dinamikus mező működik
 * ✅ Camera Scanner + Settings + Init
 * Author: Erik Borota / PunktePass
 */

console.log("🚀 PPV Kassenscanner v5.3 aktív!");

// ============================================================
// 🌐 GLOBAL STATE & CONFIG
// ============================================================
window.PPV_LAST_SCAN = window.PPV_LAST_SCAN || 0;
window.PPV_BROADCAST = window.PPV_BROADCAST || new BroadcastChannel("punktepass_scans");

window.PPV_STORE_KEY =
  (window.PPV_STORE_DATA?.store_key || "").trim() ||
  (sessionStorage.getItem("ppv_store_key") || "").trim() || "";

window.PPV_STORE_ID =
  window.PPV_STORE_DATA?.store_id ||
  Number(sessionStorage.getItem("ppv_store_id")) ||
  0;

sessionStorage.setItem("ppv_store_key", window.PPV_STORE_KEY);
sessionStorage.setItem("ppv_store_id", window.PPV_STORE_ID);

console.log("✅ Store ID:", window.PPV_STORE_ID, "| KEY:", window.PPV_STORE_KEY);

const L = window.ppv_lang || {};

// ============================================================
// 🛠️ UTILITY FUNCTIONS
// ============================================================

function canProcessScan() {
  const now = Date.now();
  if (now - window.PPV_LAST_SCAN < 600) return false;
  window.PPV_LAST_SCAN = now;
  return true;
}

window.ppvToast = function (msg, type = "info") {
  let box = document.createElement("div");
  box.className = "ppv-toast " + type;
  box.innerHTML = msg;
  document.body.appendChild(box);

  setTimeout(() => box.classList.add("show"), 10);
  setTimeout(() => box.classList.remove("show"), 3000);
  setTimeout(() => box.remove(), 3500);
};

function statusBadge(state) {
  const badges = {
    active: `<span style='color:#00e676'>🟢 ${L.state_active || 'Aktív'}</span>`,
    archived: `<span style='color:#ffab00'>📦 ${L.state_archived || 'Archivált'}</span>`,
    upcoming: `<span style='color:#2979ff'>🔵 ${L.state_upcoming || 'Hamarosan'}</span>`,
    expired: `<span style='color:#9e9e9e'>⚫ ${L.state_expired || 'Lejárt'}</span>`
  };
  return badges[state] || "";
}

// ============================================================
// 💬 UI MANAGER
// ============================================================
class UIManager {
  constructor(resultBox, logTable, campaignList) {
    this.resultBox = resultBox;
    this.logTable = logTable;
    this.campaignList = campaignList;
  }

  showMessage(text, type = "info") {
    if (!this.resultBox) return;
    this.resultBox.className = "ppv-result-box " + type;
    this.resultBox.innerHTML = text;
    this.resultBox.style.opacity = "1";
    setTimeout(() => (this.resultBox.style.opacity = "0"), 3500);
  }

  addLogRow(time, user, status) {
    if (!this.logTable) return;
    const row = document.createElement("tr");
    row.innerHTML = `<td>${time}</td><td>${user}</td><td>${status}</td>`;
    this.logTable.prepend(row);
    while (this.logTable.rows.length > 12) this.logTable.deleteRow(12);
  }

  flashCampaignList() {
    if (!this.campaignList) return;
    this.campaignList.scrollTo({ top: 0, behavior: "smooth" });
    this.campaignList.style.transition = "background 0.5s";
    this.campaignList.style.background = "rgba(0,255,120,0.25)";
    setTimeout(() => {
      this.campaignList.style.background = "transparent";
    }, 600);
  }
}

// ============================================================
// 📡 BROADCAST MANAGER
// ============================================================
class BroadcastManager {
  static send(data) {
    const payload = {
      type: "ppv-scan-success",
      points: data.points || 1,
      store: data.store_name || data.store || "PunktePass",
      time: Date.now(),
    };

    try {
      if (window.PPV_BROADCAST) {
        window.PPV_BROADCAST.postMessage(payload);
        console.log("📡 Broadcast sent:", payload);
      }
    } catch (e) {
      console.warn("⚠️ BroadcastChannel failed:", e);
    }

    try {
      localStorage.setItem("ppv_scan_event", JSON.stringify(payload));
      setTimeout(() => localStorage.removeItem("ppv_scan_event"), 5000);
      console.log("📦 LocalStorage event:", payload);
    } catch (e) {
      console.warn("⚠️ LocalStorage failed:", e);
    }

    try {
      window.dispatchEvent(new CustomEvent("ppv-scan-success", { detail: payload }));
      console.log("🛰️ CustomEvent dispatched");
    } catch (e) {
      console.warn("⚠️ CustomEvent failed:", e);
    }

    if ("serviceWorker" in navigator) {
      try {
        navigator.serviceWorker.ready.then(reg => {
          if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage(payload);
            console.log("📨 SW relay → controller");
          } else if (reg.active) {
            reg.active.postMessage(payload);
            console.log("📨 SW relay → active");
          }
        });
      } catch (e) {
        console.warn("⚠️ SW relay failed:", e);
      }
    }
  }
}

// ============================================================
// 💾 OFFLINE SYNC MANAGER
// ============================================================
class OfflineSyncManager {
  static STORAGE_KEY = "ppv_offline_sync";
  static DUPLICATE_WINDOW = 2 * 60 * 1000;

  static save(qrCode) {
    try {
      let items = JSON.parse(localStorage.getItem(this.STORAGE_KEY) || "[]");

      const twoMinutesAgo = Date.now() - this.DUPLICATE_WINDOW;
      const isDuplicate = items.some(item =>
        item.qr === qrCode &&
        new Date(item.time).getTime() > twoMinutesAgo
      );

      if (isDuplicate) {
        console.warn("⚠️ [OFFLINE] Duplicate detected (2 min):", qrCode);
        window.ppvToast("⚠️ " + (L.pos_duplicate || "Már beolvasva (2 perc)"), "warning");
        return false;
      }

      items.push({
        id: `${window.PPV_STORE_KEY}-${qrCode}-${Date.now()}`,
        qr: qrCode,
        time: new Date().toISOString(),
        store_key: window.PPV_STORE_KEY,
        synced: false
      });

      localStorage.setItem(this.STORAGE_KEY, JSON.stringify(items));
      console.log("✅ [OFFLINE] Saved:", qrCode);
      return true;
    } catch (e) {
      console.error("❌ [OFFLINE] Save failed:", e);
      return false;
    }
  }

  static async sync() {
    try {
      let items = JSON.parse(localStorage.getItem(this.STORAGE_KEY) || "[]");
      if (!items.length) return;

      const unsynced = items.filter(i => !i.synced);
      if (!unsynced.length) {
        console.log("✅ [OFFLINE] All synced");
        return;
      }

      console.log(`⏳ [OFFLINE] Syncing ${unsynced.length} items...`);

      const res = await fetch("/wp-json/punktepass/v1/pos/sync_offline", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "PPV-POS-Token": window.PPV_STORE_KEY,
        },
        body: JSON.stringify({ scans: unsynced }),
      });

      const result = await res.json();

      if (result.success) {
        const synced = unsynced.map(i => i.id);
        let remaining = items.filter(i => !synced.includes(i.id));
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(remaining));

        console.log(`✅ [OFFLINE] ${result.synced} synced, ${remaining.length} remaining`);
        window.ppvToast(`✅ ${result.synced} ${L.pos_sync || "szinkronizálva"}`, "success");
      } else if (result.message?.includes("Duplikátum") || result.message?.includes("már")) {
        console.warn("⚠️ [OFFLINE] Duplicates on server:", result.message);
        const syncedDups = unsynced.filter(i =>
          result.duplicates?.includes(i.qr)
        ).map(i => i.id);
        let remaining = items.filter(i => !syncedDups.includes(i.id));
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(remaining));
        window.ppvToast("⚠️ " + (result.message || "Duplikátumok eltávolítva"), "warning");
      } else {
        window.ppvToast("❌ " + (result.message || "Szinkronizálás nem sikerült"), "error");
      }
    } catch (e) {
      console.warn("⚠️ [OFFLINE] Network error:", e);
      window.ppvToast("🚫 " + (L.pos_network_error || "Hálózati hiba"), "error");
    }
  }
}

// ============================================================
// 🔍 SCAN PROCESSOR
// ============================================================
class ScanProcessor {
  constructor(uiManager) {
    this.ui = uiManager;
  }

  async process(qrCode) {
    if (!qrCode || !window.PPV_STORE_KEY) return;
    if (!canProcessScan()) return;

    this.ui.showMessage("⏳ " + (L.pos_checking || "Ellenőrzés..."), "info");

    try {
      const res = await fetch("/wp-json/punktepass/v1/pos/scan", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "PPV-POS-Token": window.PPV_STORE_KEY,
        },
        body: JSON.stringify({
          qr: qrCode,
          store_key: window.PPV_STORE_KEY,
          points: 1,
        }),
      });

      let data;
      try {
        data = await res.json();
      } catch (e) {
        data = {
          success: false,
          message: L.pos_server_error || "Szerverhiba"
        };
      }

      if (data.success) {
        this.ui.showMessage("✅ " + data.message, "success");
        this.ui.addLogRow(
          data.time || new Date().toLocaleString(),
          data.user_id || "-",
          "✅"
        );
        BroadcastManager.send(data);
      } else {
        const msg = data.message || "";
        this.ui.showMessage("⚠️ " + msg, "warning");

        if (!/már|beolvasva|duplikátum/i.test(msg)) {
          OfflineSyncManager.save(qrCode);
        }
      }

      setTimeout(() => this.loadLogs(), 1000);

    } catch (e) {
      console.warn("⚠️ Scan error:", e);
      this.ui.showMessage("⚠️ " + (L.server_error || "Szerverhiba"), "error");
      OfflineSyncManager.save(qrCode);
    }
  }

  async loadLogs() {
    // Check if store key exists
    if (!window.PPV_STORE_KEY || window.PPV_STORE_KEY.trim() === '') {
      console.log('ℹ️ [Logs] No store key - skipping logs load');
      return;
    }

    try {
      const res = await fetch("/wp-json/punktepass/v1/pos/logs", {
        headers: { "PPV-POS-Token": window.PPV_STORE_KEY },
      });
      const logs = await res.json();
      (logs || []).forEach((l) =>
        this.ui.addLogRow(l.created_at, l.user_id, l.success ? "✅" : "❌")
      );
    } catch (e) {
      console.warn("⚠️ Log load failed:", e);
    }
  }
}

// ============================================================
// 🎯 CAMPAIGN MANAGER - COMPLETE
// ============================================================
class CampaignManager {
  constructor(uiManager, listElement, modalElement) {
    this.ui = uiManager;
    this.list = listElement;
    this.modal = modalElement;
    this.campaigns = [];
    this.editingId = 0;
  }

  async load() {
    if (!this.list) return;

    // Check if store key exists
    if (!window.PPV_STORE_KEY || window.PPV_STORE_KEY.trim() === '') {
      console.log('ℹ️ [Campaigns] No store key - skipping campaigns load');
      this.list.innerHTML = "<p style='text-align:center;color:#999;padding:20px;'>" +
        (L.camp_no_store || "Kein Geschäft ausgewählt") + "</p>";
      return;
    }

    this.list.innerHTML = "<div class='ppv-loading'>⏳ " +
      (L.camp_loading || "Kampányok betöltése...") + "</div>";

    const filter = document.getElementById("ppv-campaign-filter")?.value || "active";

    try {
      const res = await fetch("/wp-json/punktepass/v1/pos/campaigns", {
        headers: { "PPV-POS-Token": window.PPV_STORE_KEY },
      });
      const data = await res.json();

      this.list.innerHTML = "";

      if (!data || !data.length) {
        this.list.innerHTML = "<p>" + (L.camp_none || "Nincsenek kampányok") + "</p>";
        return;
      }

      this.campaigns = data;

      let filtered = data;
      if (filter === "active") filtered = data.filter(c => c.status === "active");
      if (filter === "archived") filtered = data.filter(c => c.status === "archived");

      filtered.forEach(c => this.renderCampaign(c));
      this.bindEvents();

    } catch (e) {
      console.warn("⚠️ Campaign load error:", e);
      this.list.innerHTML = "<p>⚠️ " + (L.camp_load_error || "Hiba a betöltéskor") + "</p>";
    }
  }

  renderCampaign(c) {
    let value = "";
    if (c.campaign_type === "points") value = c.extra_points + " pt";
    if (c.campaign_type === "discount") value = c.discount_percent + "%";
    if (c.campaign_type === "fixed") value = (c.min_purchase ?? c.fixed_amount ?? 0) + "€";

    const card = document.createElement("div");
    card.className = "ppv-campaign-item glass";

    card.innerHTML = `
      <div class="ppv-camp-header">
        <h4>${c.title}</h4>
        <div class="ppv-camp-actions">
          <span class="ppv-camp-clone" data-id="${c.id}">📄</span>
          <span class="ppv-camp-archive" data-id="${c.id}">📦</span>
          <span class="ppv-camp-edit" data-id="${c.id}">✏️</span>
          <span class="ppv-camp-delete" data-id="${c.id}">🗑️</span>
        </div>
      </div>
      <p>${c.start_date.substring(0, 10)} – ${c.end_date.substring(0, 10)}</p>
      <p>⭐ ${L.camp_type || "Típus"}: ${c.campaign_type} | ${L.camp_value || "Érték"}: ${value} | ${statusBadge(c.state)}</p>
    `;

    this.list.appendChild(card);
  }

  bindEvents() {
    document.querySelectorAll(".ppv-camp-edit, .ppv-camp-delete, .ppv-camp-archive, .ppv-camp-clone")
      .forEach(el => el.replaceWith(el.cloneNode(true)));

    document.querySelectorAll(".ppv-camp-edit").forEach(btn => {
      btn.addEventListener("click", () => {
        const camp = this.campaigns.find(c => c.id == btn.dataset.id);
        if (camp) this.edit(camp);
      });
    });

    document.querySelectorAll(".ppv-camp-delete").forEach(btn => {
      btn.addEventListener("click", () => this.delete(btn.dataset.id));
    });

    document.querySelectorAll(".ppv-camp-archive").forEach(btn => {
      btn.addEventListener("click", () => this.archive(btn.dataset.id));
    });

    document.querySelectorAll(".ppv-camp-clone").forEach(btn => {
      btn.addEventListener("click", () => this.clone(btn.dataset.id));
    });
  }

  edit(c) {
    if (!c) return;

    this.showModal();
    this.editingId = c.id;

    const safe = (id) => document.getElementById(id);

    if (safe("camp-status")) safe("camp-status").value = c.status || "active";
    if (safe("camp-title")) safe("camp-title").value = c.title;
    if (safe("camp-start")) safe("camp-start").value = c.start_date?.substring(0, 10) || "";
    if (safe("camp-end")) safe("camp-end").value = c.end_date?.substring(0, 10) || "";
    if (safe("camp-type")) safe("camp-type").value = c.campaign_type;

    if (safe("camp-required-points")) safe("camp-required-points").value = c.required_points || 0;
    if (safe("camp-points-given")) safe("camp-points-given").value = c.points_given || 1;
    if (safe("camp-free-product-name")) safe("camp-free-product-name").value = c.free_product || "";
    if (safe("camp-free-product-value")) safe("camp-free-product-value").value = c.free_product_value || 0;

    const campValue = safe("camp-value");
    if (campValue) {
      if (c.campaign_type === "points") campValue.value = c.extra_points || 0;
      else if (c.campaign_type === "discount") campValue.value = c.discount_percent || 0;
      else if (c.campaign_type === "fixed") campValue.value = c.min_purchase || c.fixed_amount || 0;
      else if (c.campaign_type === "free_product") campValue.value = 0;
    }

    this.updateValueLabel(c.campaign_type);

    // ✅ KÖZVETLENÜL ÁLLÍTJUK BE A LÁTHATÓSÁGOT!
    this.updateVisibilityByType(c.campaign_type);

    setTimeout(() => {
      this.initTypeListener();
      this.initFreeProductListener();

      const typeSelect = safe("camp-type");
      if (typeSelect) {
        typeSelect.dispatchEvent(new Event('change'));
      }

      const productInput = safe("camp-free-product-name");
      if (productInput) {
        productInput.dispatchEvent(new Event('input'));
      }
    }, 100);
  }

  // ✅ ÚJ FÜGGVÉNY: Direkt display logika
  updateVisibilityByType(type) {
    const safe = (id) => document.getElementById(id);

    const requiredPointsWrapper = safe("camp-required-points-wrapper");
    const pointsGivenWrapper = safe("camp-points-given-wrapper"); // ← PER SCAN!
    const freeProductNameWrapper = safe("camp-free-product-name-wrapper");
    const freeProductValueWrapper = safe("camp-free-product-value-wrapper");

    // Összes elrejtése
    if (requiredPointsWrapper) requiredPointsWrapper.style.display = "none";
    if (pointsGivenWrapper) pointsGivenWrapper.style.display = "none";
    if (freeProductNameWrapper) freeProductNameWrapper.style.display = "none";
    if (freeProductValueWrapper) freeProductValueWrapper.style.display = "none";

    // ✅ ÖSSZES TÍPUSNAK KELL A SZÜKSÉGES PONT!
    if (requiredPointsWrapper) requiredPointsWrapper.style.display = "block";

    // Kiválasztott típus alapján TOVÁBBI megjelenítése
    if (type === "points") {
      // Extra Punkte - NINCS per scan pont!
    } else if (type === "discount" || type === "fixed") {
      // ✅ Rabatt & Fix Bonus - PER SCAN PONT KELL!
      if (pointsGivenWrapper) pointsGivenWrapper.style.display = "block";
    } else if (type === "free_product") {
      // ✅ Gratis Termék - TERMÉK + PER SCAN PONT!
      if (freeProductNameWrapper) freeProductNameWrapper.style.display = "block";
      if (freeProductValueWrapper) freeProductValueWrapper.style.display = "block";
      if (pointsGivenWrapper) pointsGivenWrapper.style.display = "block";
    }
  }

  async delete(id) {
    if (!confirm(L.confirm_delete || "Biztosan törlöd?")) return;

    try {
      const res = await fetch("/wp-json/punktepass/v1/pos/campaign/delete", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "PPV-POS-Token": window.PPV_STORE_KEY,
        },
        body: JSON.stringify({ id, store_key: window.PPV_STORE_KEY }),
      });

      const data = await res.json();
      if (data.success) {
        this.ui.showMessage("🗑️ " + (L.camp_deleted || "Kampány törölve"), "success");
        this.refresh();
      } else {
        this.ui.showMessage(data.message, "error");
      }
    } catch (e) {
      this.ui.showMessage("⚠️ " + (L.server_error || "Szerverhiba"), "error");
    }
  }

  async archive(id) {
    try {
      const res = await fetch("/wp-json/punktepass/v1/pos/campaign/update", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "PPV-POS-Token": window.PPV_STORE_KEY,
        },
        body: JSON.stringify({
          id,
          store_key: window.PPV_STORE_KEY,
          status: "archived"
        }),
      });

      const data = await res.json();
      if (data.success) {
        this.ui.showMessage("📦 " + (L.camp_archived || "Archiválva"), "success");
        this.refresh();
      } else {
        this.ui.showMessage(data.message, "error");
      }
    } catch (e) {
      this.ui.showMessage("⚠️ " + (L.server_error || "Szerverhiba"), "error");
    }
  }

  async clone(id) {
    const original = this.campaigns.find(c => c.id == id);
    if (!original) return;

    try {
      const res = await fetch("/wp-json/punktepass/v1/pos/campaign", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "PPV-POS-Token": window.PPV_STORE_KEY,
        },
        body: JSON.stringify({
          store_key: window.PPV_STORE_KEY,
          title: original.title + " (" + (L.copy || "Másolat") + ")",
          start_date: original.start_date,
          end_date: original.end_date,
          campaign_type: original.campaign_type,
          camp_value: original.extra_points || original.discount_percent || original.min_purchase,
          required_points: original.required_points || 0,
          free_product: original.free_product || "",
          free_product_value: original.free_product_value || 0,
          points_given: original.points_given || 1,
        }),
      });

      const data = await res.json();
      if (data.success) {
        this.ui.showMessage("📄 " + (L.camp_cloned || "Duplizálva!"), "success");
        this.refresh();
      }
    } catch (e) {
      this.ui.showMessage("⚠️ " + (L.server_error || "Szerverhiba"), "error");
    }
  }

  // ✅ SAVE FÜGGVÉNY - TELJES!
  async save() {
    const safe = (id) => {
      const el = document.getElementById(id);
      return el ? el.value : "";
    };

    const safeNum = (id) => {
      const el = document.getElementById(id);
      return el ? Number(el.value) || 0 : 0;
    };

    const title = safe("camp-title");
    const start = safe("camp-start");
    const end = safe("camp-end");
    const type = safe("camp-type");
    const value = safe("camp-value");
    const status = safe("camp-status");
    // 🩵 Fix: valós campaign_type lekérése DOM-ból
    const realType = document.getElementById("camp-type")?.value || type;
    console.log("🎯 Campaign type detected:", realType);

    const requiredPoints = safeNum("camp-required-points");
    const pointsGiven = safeNum("camp-points-given");
    const freeProductName = (document.getElementById("camp-free-product-name")?.value || "").trim();
    const freeProductValue = safeNum("camp-free-product-value");

    if (!title || !start || !end) {
      const msg = L.camp_fill_title_date || "Kérlek töltsd ki a címet és a dátumot";
      this.ui.showMessage(msg, "warning");
      window.ppvToast("⚠️ " + msg, "warning");
      return;
    }

   // ✅ VALIDÁCIÓ: Gratis termék + érték
    if (realType === "free_product") {
      console.log("🧩 Validating free_product:", freeProductName, freeProductValue);
      if (!freeProductName || freeProductValue <= 0) {
        const msg = L.camp_fill_free_product_name_value || "⚠️ Kérlek add meg a termék nevét és értékét!";
        this.ui.showMessage(msg, "warning");
        window.ppvToast(msg, "warning");
        const el = document.getElementById("camp-free-product-name");
        if (el) el.focus();
        return;
      }
    }

    const endpoint = this.editingId > 0
      ? "/wp-json/punktepass/v1/pos/campaign/update"
      : "/wp-json/punktepass/v1/pos/campaign";

    try {
      const res = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json; charset=UTF-8",
          "Accept": "application/json",
          "PPV-POS-Token": window.PPV_STORE_KEY,
        },
        body: JSON.stringify({
          id: this.editingId,
          store_key: window.PPV_STORE_KEY,
          title,
          start_date: start,
          end_date: end,
          campaign_type: realType, // ✅ ez a fix

          camp_value: value,
          required_points: requiredPoints,
          free_product: freeProductName,
          free_product_value: freeProductValue,
          points_given: pointsGiven,
          status,
        }),
      });

      const data = await res.json();

      if (data.success) {
        const msg = this.editingId > 0
          ? (L.camp_updated || "✅ Kampány frissítve!")
          : (L.camp_saved || "✅ Kampány mentve!");

        window.ppvToast(msg, "success");
        this.ui.showMessage(msg, "success");

        this.hideModal();
        this.resetForm();
        this.refresh();
      } else {
        const errMsg = "❌ " + (data.message || L.error_generic || "Hiba");
        this.ui.showMessage(errMsg, "error");
        window.ppvToast(errMsg, "error");
      }
    } catch (e) {
      const errMsg = "⚠️ " + (L.server_error || "Szerverhiba");
      this.ui.showMessage(errMsg, "error");
      window.ppvToast(errMsg, "error");
      console.error("Save error:", e);
    }
  }

  showModal() {
    if (this.modal) {
      this.modal.classList.add("show");
    }
  }

  hideModal() {
    if (this.modal) {
      this.modal.classList.remove("show");
    }
  }

  initTypeListener() {
    const typeSelect = document.getElementById("camp-type");
    if (!typeSelect) return;

    // Remove old listener to prevent duplicates
    typeSelect.removeEventListener("change", this._typeChangeHandler);

    this._typeChangeHandler = (e) => {
      const type = e.target.value;
      this.updateVisibilityByType(type);
      this.updateValueLabel(type); // ✅ ÚJ: Értékcímke frissítése
      console.log("✅ [Type Change] Type:", type);
    };

    typeSelect.addEventListener("change", this._typeChangeHandler);
  }

  initFreeProductListener() {
    const productInput = document.getElementById("camp-free-product-name");
    const valueWrapper = document.getElementById("camp-free-product-value-wrapper");
    const valueInput = document.getElementById("camp-free-product-value");

    if (!productInput || !valueWrapper || !valueInput) return;

    productInput.addEventListener("input", (e) => {
      const productName = e.target.value.trim();

      if (productName.length > 0) {
        valueWrapper.style.display = "block";
        // valueInput.required = true; // ❌ KIVET!
        console.log("✅ [FreeProduct] Megjelent az érték mező");
      } else {
        valueWrapper.style.display = "none";
        // valueInput.required = false; // ❌ KIVET!
        valueInput.value = 0;
        console.log("❌ [FreeProduct] Elrejtettük az érték mezőt");
      }
    });
  }

  updateValueLabel(campType) {
    const label = document.getElementById("camp-value-label");
    const campValue = document.getElementById("camp-value");

    if (!label || !campValue) return;

    if (campType === "points") label.innerText = L.camp_extra_points || "Extra pontok";
    else if (campType === "discount") label.innerText = L.camp_discount || "Rabatt (%)";
    else if (campType === "fixed") label.innerText = L.camp_fixed_bonus || "Fix bonus (€)";
    else if (campType === "free_product") {
      label.innerText = "🎁 Ingyenes termék";
      campValue.style.display = "none";
      return;
    }

    label.style.opacity = "1";
    campValue.style.display = "block";
  }

  resetForm() {
    const safe = (id) => document.getElementById(id);

    if (safe("camp-title")) safe("camp-title").value = "";
    if (safe("camp-start")) safe("camp-start").value = "";
    if (safe("camp-end")) safe("camp-end").value = "";
    if (safe("camp-value")) safe("camp-value").value = 0;
    if (safe("camp-type")) safe("camp-type").value = "points";
    if (safe("camp-status")) safe("camp-status").value = "active";
    if (safe("camp-required-points")) safe("camp-required-points").value = 0;
    if (safe("camp-points-given")) safe("camp-points-given").value = 1;
    if (safe("camp-free-product-name")) safe("camp-free-product-name").value = "";
    if (safe("camp-free-product-value")) safe("camp-free-product-value").value = 0;

    if (safe("camp-required-points-wrapper")) safe("camp-required-points-wrapper").style.display = "none";
    if (safe("camp-points-given-wrapper")) safe("camp-points-given-wrapper").style.display = "none";
    if (safe("camp-free-product-name-wrapper")) safe("camp-free-product-name-wrapper").style.display = "none";
    if (safe("camp-free-product-value-wrapper")) safe("camp-free-product-value-wrapper").style.display = "none";

    this.editingId = 0;
  }

  refresh(delay = 800) {
    setTimeout(async () => {
      await this.load();
      this.ui.flashCampaignList();
    }, delay);
  }
}

// ============================================================
// 📷 MINI ALWAYS-ON CAMERA SCANNER
// ============================================================
class CameraScanner {
  constructor(scanProcessor) {
    this.beep = new Audio("/wp-content/plugins/punktepass/assets/sounds/scan-beep.wav");
    this.beep.volume = 1.0;

    this.scanProcessor = scanProcessor;
    this.scanner = null;
    this.scanning = false;
    this.lastRead = '';
    this.repeatCount = 0;

    // State management: 'scanning', 'processing', 'paused'
    this.state = 'scanning';
    this.countdown = 0;
    this.countdownInterval = null;
    this.pauseTimeout = null;

    this.miniContainer = null;
    this.readerDiv = null;
    this.statusDiv = null;

    this.createMiniScanner();
    this.autoStart();
  }

  createMiniScanner() {
    const existing = document.getElementById('ppv-mini-scanner');
    if (existing) existing.remove();

    this.miniContainer = document.createElement('div');
    this.miniContainer.id = 'ppv-mini-scanner';
    this.miniContainer.className = 'ppv-mini-scanner-active';
    this.miniContainer.innerHTML = `
      <div id="ppv-mini-reader"></div>
      <div id="ppv-mini-status">
        <span class="ppv-mini-icon">📷</span>
        <span class="ppv-mini-text">${L.scanner_active || 'Scanner aktív'}</span>
      </div>
    `;

    document.body.appendChild(this.miniContainer);

    this.readerDiv = document.getElementById('ppv-mini-reader');
    this.statusDiv = document.getElementById('ppv-mini-status');
  }

  async autoStart() {
    // Wait a bit for the page to fully load
    setTimeout(async () => {
      await this.loadLibrary();
    }, 500);
  }

  async loadLibrary() {
    if (window.Html5Qrcode) {
      await this.startScanner();
      return;
    }

    const script = document.createElement('script');
    script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
    script.onload = () => this.startScanner();
    script.onerror = () => {
      this.updateStatus('error', '❌ Scanner könyvtár nem tölthető be');
    };
    document.head.appendChild(script);
  }

  async startScanner() {
    const readerElement = document.getElementById('ppv-mini-reader');
    if (!readerElement || !window.Html5Qrcode) {
      this.updateStatus('error', '❌ Scanner elem nem találhat');
      return;
    }

    try {
      this.scanner = new Html5Qrcode('ppv-mini-reader');

      // Enhanced configuration for better QR detection from any angle/distance
      const config = {
        fps: 20, // Increased from 10 to 20 for faster scanning
        qrbox: function(viewfinderWidth, viewfinderHeight) {
          // Dynamic QR box - 90% of the smaller dimension for better detection
          let minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
          let qrboxSize = Math.floor(minEdgeSize * 0.9);
          return {
            width: qrboxSize,
            height: qrboxSize
          };
        },
        aspectRatio: 1.0, // Square aspect ratio for QR codes
        disableFlip: false, // Read mirrored QR codes too
        // Advanced experimental features for better detection
        experimentalFeatures: {
          useBarCodeDetectorIfSupported: true // Use native barcode detector if available
        }
      };

      // Enhanced camera constraints for higher quality
      const cameraConstraints = {
        facingMode: 'environment', // Back camera
        advanced: [
          { zoom: 1.0 },
          { focusMode: 'continuous' },
          { exposureMode: 'continuous' }
        ],
        width: { ideal: 1920, min: 640 },
        height: { ideal: 1080, min: 480 }
      };

      await this.scanner.start(
        cameraConstraints,
        config,
        (qrCode) => this.onScanSuccess(qrCode)
      );

      this.scanning = true;
      this.state = 'scanning';
      this.updateStatus('scanning', L.scanner_active || '📷 Scanning...');

      console.log('✅ Enhanced scanner started with improved detection');

    } catch (err) {
      this.updateStatus('error', '❌ Kamera hiba');
      console.error('Camera error:', err);

      // Fallback: Try with basic config if enhanced fails
      try {
        console.log('⚠️ Trying fallback configuration...');
        const basicConfig = {
          fps: 15,
          qrbox: 250,
          aspectRatio: 1.0
        };

        await this.scanner.start(
          { facingMode: 'environment' },
          basicConfig,
          (qrCode) => this.onScanSuccess(qrCode)
        );

        this.scanning = true;
        this.state = 'scanning';
        this.updateStatus('scanning', L.scanner_active || '📷 Scanning...');
        console.log('✅ Fallback scanner started');
      } catch (fallbackErr) {
        console.error('Fallback also failed:', fallbackErr);
      }
    }
  }

  onScanSuccess(qrCode) {
    if (!this.scanning || this.state !== 'scanning') return;

    // Duplicate protection
    if (qrCode === this.lastRead) {
      this.repeatCount++;
    } else {
      this.lastRead = qrCode;
      this.repeatCount = 1;
    }

    // Two consecutive identical reads required
    if (this.repeatCount >= 2) {
      this.scanning = false;
      this.state = 'processing';

      // Stop scanner temporarily
      if (this.scanner) {
        try {
          this.scanner.stop();
        } catch (e) {}
      }

      // Update UI
      this.updateStatus('processing', '⏳ ' + (L.scanner_points_adding || 'Processing...'));

      // Vibration
      try {
        if (navigator.vibrate) navigator.vibrate([50, 30, 50]);
      } catch (e) {}

      // Play beep sound
      try {
        this.beep.currentTime = 0;
        this.beep.play();
      } catch (e) {
        console.warn("Beep playback error:", e);
      }

      // Process the scan
      this.inlineProcessScan(qrCode);
    }
  }


  inlineProcessScan(qrCode) {
    fetch('/wp-json/punktepass/v1/pos/scan', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'PPV-POS-Token': window.PPV_STORE_KEY || ''
      },
      body: JSON.stringify({
        qr: qrCode,
        store_key: window.PPV_STORE_KEY || '',
        points: 1
      })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          // Update status to success
          this.updateStatus('success', '✅ ' + (data.message || L.scanner_success_msg || 'Sikeres!'));

          // Show toast notification
          if (window.ppvToast) {
            window.ppvToast(data.message || L.scanner_point_added || '✅ Pont hozzáadva!', 'success');
          }

          // Broadcast the scan event
          if (window.BroadcastManager) {
            BroadcastManager.send(data);
          }

          // Reload logs
          if (this.scanProcessor && this.scanProcessor.loadLogs) {
            setTimeout(() => {
              try {
                this.scanProcessor.loadLogs();
              } catch (e) {}
            }, 1000);
          }

          // Start 10-second pause
          this.startPauseCountdown();

        } else {
          // Error - show warning and restart scanner after 3 seconds
          this.updateStatus('warning', '⚠️ ' + (data.message || L.error_generic || 'Hiba'));

          if (window.ppvToast) {
            window.ppvToast(data.message || L.error_generic || '⚠️ Hiba', 'warning');
          }

          // Restart scanner after error
          setTimeout(() => {
            this.lastRead = "";
            this.repeatCount = 0;
            this.startScanner();
          }, 3000);
        }
      })
      .catch(e => {
        // Network error - show error and restart scanner
        this.updateStatus('error', '❌ ' + (L.pos_network_error || 'Hálózati hiba'));

        if (window.ppvToast) {
          window.ppvToast('❌ ' + (L.pos_network_error || 'Hálózati hiba'), 'error');
        }

        // Restart scanner after network error
        setTimeout(() => {
          this.lastRead = "";
          this.repeatCount = 0;
          this.startScanner();
        }, 3000);
      });
  }

  startPauseCountdown() {
    // Clear any existing intervals
    if (this.countdownInterval) {
      clearInterval(this.countdownInterval);
    }
    if (this.pauseTimeout) {
      clearTimeout(this.pauseTimeout);
    }

    // Set state to paused
    this.state = 'paused';
    this.countdown = 10;
    this.lastRead = "";
    this.repeatCount = 0;

    // Update status with countdown
    this.updateStatus('paused', `⏸️ Pause: ${this.countdown}s`);

    // Start countdown interval (update every second)
    this.countdownInterval = setInterval(() => {
      this.countdown--;

      if (this.countdown <= 0) {
        // Countdown finished - restart scanner
        clearInterval(this.countdownInterval);
        this.countdownInterval = null;
        this.autoRestartScanner();
      } else {
        // Update countdown display
        this.updateStatus('paused', `⏸️ Pause: ${this.countdown}s`);
      }
    }, 1000);
  }

  async autoRestartScanner() {
    console.log('🔄 Auto-restarting scanner after pause...');
    this.state = 'scanning';
    this.updateStatus('scanning', '🔄 Restarting...');

    try {
      await this.startScanner();
    } catch (e) {
      console.error('Auto-restart error:', e);
      this.updateStatus('error', '❌ Restart failed');

      // Try again after 5 seconds
      setTimeout(() => {
        this.autoRestartScanner();
      }, 5000);
    }
  }

  updateStatus(state, text) {
    if (!this.statusDiv) return;

    const iconMap = {
      scanning: '📷',
      processing: '⏳',
      success: '✅',
      warning: '⚠️',
      error: '❌',
      paused: '⏸️'
    };

    const stateClassMap = {
      scanning: 'ppv-mini-status-scanning',
      processing: 'ppv-mini-status-processing',
      success: 'ppv-mini-status-success',
      warning: 'ppv-mini-status-warning',
      error: 'ppv-mini-status-error',
      paused: 'ppv-mini-status-paused'
    };

    // Update status div classes
    this.statusDiv.className = '';
    if (stateClassMap[state]) {
      this.statusDiv.classList.add(stateClassMap[state]);
    }

    // Update icon
    const iconEl = this.statusDiv.querySelector('.ppv-mini-icon');
    if (iconEl) {
      iconEl.textContent = iconMap[state] || '📷';
    }

    // Update text
    const textEl = this.statusDiv.querySelector('.ppv-mini-text');
    if (textEl) {
      textEl.textContent = text.replace(/^[📷⏳✅⚠️❌⏸️]\s*/, '');
    }
  }

}

// ============================================================
// 🎛️ SETTINGS MANAGER
// ============================================================
class SettingsManager {
  static initLanguage() {
    const langSel = document.getElementById('ppv-lang-select');
    if (!langSel) return;

    const cur = (document.cookie.match(/ppv_lang=([^;]+)/) || [])[1] || 'de';
    langSel.value = cur;

    langSel.addEventListener('change', async (e) => {
      const newLang = e.target.value;

      document.cookie = `ppv_lang=${newLang};path=/;max-age=${60 * 60 * 24 * 365}`;
      localStorage.setItem('ppv_lang', newLang);

      try {
        const res = await fetch('/wp-json/punktepass/v1/strings', {
          method: 'GET',
          headers: {
            'X-Lang': newLang
          }
        });

        const strings = await res.json();

        window.ppv_lang = strings;

        this.refreshUIText(strings);

        localStorage.setItem(`ppv_strings_${newLang}`, JSON.stringify(strings));

        window.ppvToast(`✅ ${L.lang_changed || 'Nyelv'}: ${newLang.toUpperCase()}`, 'success');

      } catch (err) {
        console.error('Fordítás letöltési hiba:', err);
        window.ppvToast('❌ ' + (L.lang_change_failed || 'Nyelvváltás sikertelen'), 'error');
        langSel.value = cur;
      }
    });
  }

  static refreshUIText(strings) {
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      if (strings[key]) {
        el.textContent = strings[key];
      }
    });

    console.log(L.ui_translations_updated || '✅ UI fordítások frissítve');
  }

  static initTheme() {
    const themeBtn = document.getElementById('ppv-theme-toggle');
    if (!themeBtn) return;

    const key = 'ppv_theme';
    const apply = v => {
      document.body.classList.remove('ppv-light', 'ppv-dark');
      document.body.classList.add(`ppv-${v}`);
    };

    let cur = localStorage.getItem(key) || 'dark';
    apply(cur);

    themeBtn.addEventListener('click', () => {
      cur = (cur === 'dark' ? 'light' : 'dark');
      localStorage.setItem(key, cur);
      apply(cur);
      if (navigator.vibrate) navigator.vibrate(20);
    });
  }
}

// ============================================================
// 🚀 MAIN APPLICATION - INIT
// ============================================================
document.addEventListener("DOMContentLoaded", function () {
  const input = document.getElementById("ppv-pos-input");
  const sendBtn = document.getElementById("ppv-pos-send");
  const resultBox = document.getElementById("ppv-pos-result");
  const logTable = document.querySelector("#ppv-pos-log tbody");
  const campaignList = document.getElementById("ppv-campaign-list");
  const campaignModal = document.getElementById("ppv-campaign-modal");

  if (!input) return;

  const ui = new UIManager(resultBox, logTable, campaignList);
  const scanProcessor = new ScanProcessor(ui);
  const campaignManager = new CampaignManager(ui, campaignList, campaignModal);
  const cameraScanner = new CameraScanner(scanProcessor);

  input.addEventListener("keypress", (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      const qr = input.value.trim();
      if (qr.length >= 4) {
        scanProcessor.process(qr);
        input.value = "";
      }
    }
  });

  if (sendBtn) {
    sendBtn.addEventListener("click", () => {
      const qr = input.value.trim();
      if (qr) {
        scanProcessor.process(qr);
        input.value = "";
      }
    });
  }

  document.getElementById("ppv-new-campaign")?.addEventListener("click", () => {
    campaignManager.resetForm();
    campaignManager.updateVisibilityByType("points"); // Default: Points típus
    campaignManager.showModal();
  });

  document.getElementById("camp-cancel")?.addEventListener("click", () => {
    campaignManager.hideModal();
    campaignManager.resetForm();
  });

  document.getElementById("camp-save")?.addEventListener("click", () => {
    campaignManager.save();
  });

  document.getElementById("camp-type")?.addEventListener("change", (e) => {
    campaignManager.updateValueLabel(e.target.value);
  });

  document.getElementById("ppv-campaign-filter")?.addEventListener("change", () => {
    campaignManager.load();
  });

  // ✅ EGYSZERŰSÍTETT: Csak egy kattintás esemény
  if (campaignModal) {
    campaignModal.addEventListener("click", (e) => {
      // Ha a modal-ra kattintanak (és megnyílik)
      if (campaignModal.classList.contains("show")) {
        setTimeout(() => {
          campaignManager.initTypeListener();
          campaignManager.initFreeProductListener();
        }, 100);
      }
    });
  }

  SettingsManager.initLanguage();
  SettingsManager.initTheme();

  let lastVis = 0;
  document.addEventListener("visibilitychange", () => {
    const now = Date.now();
    if (!document.hidden && now - lastVis > 5000) {
      lastVis = now;
      campaignManager.load();
    }
  });

  scanProcessor.loadLogs();
  campaignManager.load();
  OfflineSyncManager.sync();
  input.focus();

  console.log(L.app_initialized || "✅ App fully initialized!");
});

console.log(L.app_complete || "✅ COMPLETE - Összes kód betöltve!");