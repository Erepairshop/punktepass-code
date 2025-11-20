/**
 * PunktePass – Kassenscanner & Kampagnen v5.3 COMPLETE
 * ✅ Save függvény integrálva
 * ✅ Összes dinamikus mező működik
 * ✅ Camera Scanner + Settings + Init
 * Author: Erik Borota / PunktePass
 */


// ============================================================
// 🌐 GLOBAL STATE & CONFIG
// ============================================================
window.PPV_LAST_SCAN = window.PPV_LAST_SCAN || 0;

window.PPV_STORE_KEY =
  (window.PPV_STORE_DATA?.store_key || "").trim() ||
  (sessionStorage.getItem("ppv_store_key") || "").trim() || "";

window.PPV_STORE_ID =
  window.PPV_STORE_DATA?.store_id ||
  Number(sessionStorage.getItem("ppv_store_id")) ||
  0;

sessionStorage.setItem("ppv_store_key", window.PPV_STORE_KEY);
sessionStorage.setItem("ppv_store_id", window.PPV_STORE_ID);


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
    window.ppvToast(text, type);
  }

  addLogRow(time, user, status) {
    if (!this.logTable) return;
    const row = document.createElement("tr");
    row.innerHTML = `<td>${time}</td><td>${user}</td><td>${status}</td>`;
    this.logTable.prepend(row);
    while (this.logTable.rows.length > 15) this.logTable.deleteRow(15);
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
        return;
      }


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
      } else {
        valueWrapper.style.display = "none";
        // valueInput.required = false; // ❌ KIVET!
        valueInput.value = 0;
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
    // 🔊 Sound effects
    this.beep = new Audio("/wp-content/plugins/punktepass/assets/sounds/scan-beep.wav");
    this.beep.volume = 1.0;

    this.errorSound = new Audio("/wp-content/plugins/punktepass/assets/sounds/error.mp3");
    this.errorSound.volume = 0.8;

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
      <div id="ppv-mini-drag-handle" class="ppv-mini-drag-handle">
        <span class="ppv-drag-icon">⋮⋮</span>
      </div>
      <div id="ppv-mini-reader"></div>
      <div id="ppv-mini-status">
        <span class="ppv-mini-icon">📷</span>
        <span class="ppv-mini-text">${L.scanner_active || 'Scanner aktív'}</span>
      </div>
      <button id="ppv-mini-toggle" class="ppv-mini-toggle">
        <span class="ppv-toggle-icon">📷</span>
        <span class="ppv-toggle-text">Start</span>
      </button>
    `;

    document.body.appendChild(this.miniContainer);

    this.readerDiv = document.getElementById('ppv-mini-reader');
    this.statusDiv = document.getElementById('ppv-mini-status');
    this.toggleBtn = document.getElementById('ppv-mini-toggle');

    // Hide reader initially
    this.readerDiv.style.display = 'none';
    this.statusDiv.style.display = 'none';

    // Load saved position or use default
    this.loadPosition();

    // Make it draggable
    this.makeDraggable();

    // Setup toggle button
    this.setupToggle();
  }

  loadPosition() {
    try {
      const savedPos = localStorage.getItem('ppv_scanner_position');
      if (savedPos) {
        const pos = JSON.parse(savedPos);
        this.miniContainer.style.bottom = 'auto';
        this.miniContainer.style.right = 'auto';
        this.miniContainer.style.left = pos.x + 'px';
        this.miniContainer.style.top = pos.y + 'px';
      }
    } catch (e) {
      console.warn('Could not load scanner position:', e);
    }
  }

  savePosition(x, y) {
    try {
      localStorage.setItem('ppv_scanner_position', JSON.stringify({ x, y }));
    } catch (e) {
      console.warn('Could not save scanner position:', e);
    }
  }

  makeDraggable() {
    const handle = document.getElementById('ppv-mini-drag-handle');
    if (!handle) return;

    let isDragging = false;
    let currentX = 0;
    let currentY = 0;
    let initialX = 0;
    let initialY = 0;
    let offsetX = 0;
    let offsetY = 0;

    const dragStart = (e) => {
      // Get the current actual position from the DOM
      const rect = this.miniContainer.getBoundingClientRect();
      currentX = rect.left;
      currentY = rect.top;

      if (e.type === 'touchstart') {
        offsetX = e.touches[0].clientX - currentX;
        offsetY = e.touches[0].clientY - currentY;
      } else {
        offsetX = e.clientX - currentX;
        offsetY = e.clientY - currentY;
      }

      if (e.target === handle || e.target.classList.contains('ppv-drag-icon')) {
        isDragging = true;
        this.miniContainer.style.cursor = 'grabbing';
        this.miniContainer.style.transition = 'none';
      }
    };

    const drag = (e) => {
      if (!isDragging) return;

      e.preventDefault();

      if (e.type === 'touchmove') {
        currentX = e.touches[0].clientX - offsetX;
        currentY = e.touches[0].clientY - offsetY;
      } else {
        currentX = e.clientX - offsetX;
        currentY = e.clientY - offsetY;
      }

      // Constrain to viewport
      const rect = this.miniContainer.getBoundingClientRect();
      const maxX = window.innerWidth - rect.width;
      const maxY = window.innerHeight - rect.height;

      currentX = Math.max(0, Math.min(currentX, maxX));
      currentY = Math.max(0, Math.min(currentY, maxY));

      this.miniContainer.style.bottom = 'auto';
      this.miniContainer.style.right = 'auto';
      this.miniContainer.style.left = currentX + 'px';
      this.miniContainer.style.top = currentY + 'px';
    };

    const dragEnd = () => {
      if (isDragging) {
        isDragging = false;
        this.miniContainer.style.cursor = 'grab';
        this.miniContainer.style.transition = '';
        this.savePosition(currentX, currentY);
      }
    };

    // Mouse events
    handle.addEventListener('mousedown', dragStart);
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', dragEnd);

    // Touch events
    handle.addEventListener('touchstart', dragStart, { passive: false });
    document.addEventListener('touchmove', drag, { passive: false });
    document.addEventListener('touchend', dragEnd);
  }

  setupToggle() {
    if (!this.toggleBtn) return;

    this.toggleBtn.addEventListener('click', async () => {

      if (this.scanning) {
        // Stop scanner
        await this.stopScanner();
      } else {
        // Start scanner
        await this.startScannerManual();
      }
    });
  }

  async stopScanner() {

    try {
      if (this.scanner) {
        await this.scanner.stop();
        this.scanner = null;
      }

      this.scanning = false;
      this.state = 'stopped';

      // Hide reader and status
      this.readerDiv.style.display = 'none';
      this.statusDiv.style.display = 'none';

      // Update button
      this.toggleBtn.querySelector('.ppv-toggle-icon').textContent = '📷';
      this.toggleBtn.querySelector('.ppv-toggle-text').textContent = 'Start';
      this.toggleBtn.style.background = 'linear-gradient(135deg, #00e676, #00c853)';

      // Clear intervals
      if (this.countdownInterval) {
        clearInterval(this.countdownInterval);
        this.countdownInterval = null;
      }
      if (this.pauseTimeout) {
        clearTimeout(this.pauseTimeout);
        this.pauseTimeout = null;
      }

    } catch (err) {
      console.error('❌ [Scanner] Stop error:', err);
    }
  }

  async startScannerManual() {

    // Show reader and status
    this.readerDiv.style.display = 'block';
    this.statusDiv.style.display = 'block';

    // Update button
    this.toggleBtn.querySelector('.ppv-toggle-icon').textContent = '🛑';
    this.toggleBtn.querySelector('.ppv-toggle-text').textContent = 'Stop';
    this.toggleBtn.style.background = 'linear-gradient(135deg, #ff5252, #f44336)';

    // Load library and start
    await this.loadLibrary();
  }

  async autoStart() {
    // ✅ REMOVED: Don't auto-start anymore, user must click button
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
      this.updateStatus('error', '❌ Scanner elem nem található');
      return;
    }

    // 🍎 iOS Detection - iOS Safari needs simpler config
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
                  (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    try {
      this.scanner = new Html5Qrcode('ppv-mini-reader');

      // ✅ OPTIMIZED CONFIG - Fast QR detection from any angle
      const config = isIOS
        ? {
            // 🍎 iOS: Minimal config for maximum compatibility
            fps: 10,
            qrbox: 250,
            disableFlip: false
          }
        : {
            // 🤖 Android: Optimized config
            fps: 30,
            qrbox: { width: 250, height: 250 },
            disableFlip: false,
            aspectRatio: 1.0,
            formatsToSupport: [0],  // Only QR codes
            experimentalFeatures: {
              useBarCodeDetectorIfSupported: true
            }
          };

      // 📷 Camera constraints - iOS needs simple config only
      const cameraConstraints = isIOS
        ? { facingMode: 'environment' }  // 🍎 iOS: Simple config only
        : {
            facingMode: 'environment',
            advanced: [
              { focusMode: 'continuous' },  // 🎯 Continuous autofocus (Android)
              { zoom: 1.0 }
            ]
          };

      await this.scanner.start(
        cameraConstraints,
        config,
        (qrCode) => this.onScanSuccess(qrCode)
      );

      this.scanning = true;
      this.state = 'scanning';
      this.updateStatus('scanning', L.scanner_active || '📷 Scanning...');

      // 🔦 Don't auto-enable torch on iOS - causes issues
      if (!isIOS) {
        try {
          const capabilities = this.scanner.getRunningTrackCapabilities();
          if (capabilities && capabilities.torch) {
            await this.scanner.applyVideoConstraints({
              advanced: [{ torch: true }]
            });
          }
        } catch (torchErr) {
        }
      }

    } catch (err) {
      console.warn('⚠️ Optimized config failed:', err);

      // ✅ IMPORTANT: Create new scanner instance for fallback
      try {
        // Stop and clear previous instance
        if (this.scanner) {
          try {
            await this.scanner.stop();
          } catch (e) {}
          this.scanner = null;
        }

        this.scanner = new Html5Qrcode('ppv-mini-reader');

        const basicConfig = {
          fps: 20,
          qrbox: 220,
          disableFlip: false
        };

        // 🍎 iOS: Don't use experimentalFeatures
        if (!isIOS) {
          basicConfig.experimentalFeatures = {
            useBarCodeDetectorIfSupported: true
          };
        }

        await this.scanner.start(
          { facingMode: 'environment' },
          basicConfig,
          (qrCode) => this.onScanSuccess(qrCode)
        );

        this.scanning = true;
        this.state = 'scanning';
        this.updateStatus('scanning', L.scanner_active || '📷 Scanning...');

      } catch (err2) {
        console.warn('⚠️ Basic config failed:', err2);

        // ✅ IMPORTANT: Create new scanner instance for final fallback
        try {
          // Stop and clear previous instance
          if (this.scanner) {
            try {
              await this.scanner.stop();
            } catch (e) {}
            this.scanner = null;
          }

          this.scanner = new Html5Qrcode('ppv-mini-reader');

          await this.scanner.start(
            { facingMode: 'environment' },
            { fps: 15, qrbox: 200 },
            (qrCode) => this.onScanSuccess(qrCode)
          );

          this.scanning = true;
          this.state = 'scanning';
          this.updateStatus('scanning', L.scanner_active || '📷 Scanning...');

        } catch (err3) {
          console.error('❌ All methods failed:', err3);
          this.updateStatus('error', '❌ Kamera nem elérhető - engedélyezd a kamera hozzáférést');
        }
      }
    }
  }

  onScanSuccess(qrCode) {
    // Check if scanner is paused - show feedback
    if (this.state === 'paused') {
      const L = window.PPV_LANG || {};
      const pauseMsg = L.scanner_paused || 'Scanner pausiert';
      const waitMsg = L.please_wait || 'Bitte warten';

      // Show toast with remaining countdown
      if (window.ppvToast) {
        window.ppvToast(`⏸️ ${pauseMsg}: ${this.countdown}s - ${waitMsg}`, 'warning');
      }
      return;
    }

    if (!this.scanning || this.state !== 'scanning') return;

    // 🎯 FASTER: Single read detection (was 2, now 1)
    if (qrCode === this.lastRead) {
      this.repeatCount++;
    } else {
      this.lastRead = qrCode;
      this.repeatCount = 1;

      // ✅ GREEN BORDER: Show visual feedback when QR detected (not yet processed)
      this.showDetectionFeedback();
    }

    // ⚡ One read is enough (faster scanning)
    if (this.repeatCount >= 1) {
      // ✅ Keep scanning flag true, only change state to prevent duplicate scans
      this.state = 'processing';

      // Update UI
      this.updateStatus('processing', '⏳ ' + (L.scanner_points_adding || 'Processing...'));

      // 📳 IMPROVED: Shorter, sharper vibration (30ms)
      try {
        if (navigator.vibrate) navigator.vibrate(30);
      } catch (e) {}

      // 🔊 Play success beep sound
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

          // Reload logs
          if (this.scanProcessor && this.scanProcessor.loadLogs) {
            setTimeout(() => {
              try {
                this.scanProcessor.loadLogs();
              } catch (e) {}
            }, 1000);
          }

          // Start 5-second pause
          this.startPauseCountdown();

        } else {
          // ❌ ERROR - show warning and restart scanner after 3 seconds
          this.updateStatus('warning', '⚠️ ' + (data.message || L.error_generic || 'Hiba'));

          // 🔴 RED FLASH: Visual error feedback
          this.showErrorFeedback();

          // 🔊 Play error sound
          try {
            this.errorSound.currentTime = 0;
            this.errorSound.play();
          } catch (e) {
            console.warn("Error sound playback failed:", e);
          }

          // 📳 Error vibration (longer than success)
          try {
            if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
          } catch (e) {}

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
        // ❌ NETWORK ERROR - show error and restart scanner
        this.updateStatus('error', '❌ ' + (L.pos_network_error || 'Hálózati hiba'));

        // 🔴 RED FLASH: Visual error feedback
        this.showErrorFeedback();

        // 🔊 Play error sound
        try {
          this.errorSound.currentTime = 0;
          this.errorSound.play();
        } catch (e) {
          console.warn("Error sound playback failed:", e);
        }

        // 📳 Error vibration
        try {
          if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
        } catch (e) {}

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

    // Set state to paused (5 seconds)
    this.state = 'paused';
    this.countdown = 5;
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
    // ✅ Check if user manually stopped during pause
    if (this.state === 'stopped' || !this.scanning) {
      return;
    }

    this.state = 'scanning';
    this.updateStatus('scanning', '🔄 Restarting...');

    try {
      await this.startScanner();
    } catch (e) {
      console.error('Auto-restart error:', e);
      this.updateStatus('error', '❌ Restart failed');

      // Try again after 5 seconds (only if not manually stopped)
      if (this.state !== 'stopped' && this.scanning) {
        setTimeout(() => {
          this.autoRestartScanner();
        }, 5000);
      }
    }
  }

  // ✅ GREEN BORDER: Visual feedback when QR detected
  showDetectionFeedback() {
    if (!this.readerDiv) return;

    // Add green border animation
    this.readerDiv.style.boxShadow = '0 0 0 4px #00ff00, 0 0 20px rgba(0, 255, 0, 0.5)';
    this.readerDiv.style.transition = 'box-shadow 0.2s ease';

    // Remove after 300ms
    setTimeout(() => {
      this.readerDiv.style.boxShadow = '';
    }, 300);
  }

  // 🔴 RED FLASH: Visual feedback on error
  showErrorFeedback() {
    if (!this.readerDiv) return;

    // Flash red 3 times
    let count = 0;
    const flashInterval = setInterval(() => {
      if (count % 2 === 0) {
        this.readerDiv.style.boxShadow = '0 0 0 4px #ff0000, 0 0 20px rgba(255, 0, 0, 0.7)';
        this.readerDiv.style.transition = 'box-shadow 0.1s ease';
      } else {
        this.readerDiv.style.boxShadow = '';
      }
      count++;
      if (count >= 6) {
        clearInterval(flashInterval);
        this.readerDiv.style.boxShadow = '';
      }
    }, 150);
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

  // Initialize even without input (for scanner-only mode)
  const ui = new UIManager(resultBox, logTable, campaignList);
  const scanProcessor = new ScanProcessor(ui);
  const campaignManager = new CampaignManager(ui, campaignList, campaignModal);
  const cameraScanner = new CameraScanner(scanProcessor);

  // Only add input listeners if input exists
  if (input) {
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

    input.focus();
  }

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


  // ============================================================
  // 📧 RENEWAL REQUEST MODAL
  // ============================================================
  const renewalBtn = document.getElementById('ppv-request-renewal-btn');
  const renewalModal = document.getElementById('ppv-renewal-modal');
  const renewalSubmit = document.getElementById('ppv-renewal-submit');
  const renewalCancel = document.getElementById('ppv-renewal-cancel');
  const renewalPhone = document.getElementById('ppv-renewal-phone');
  const renewalError = document.getElementById('ppv-renewal-error');

  if (renewalBtn && renewalModal) {
    renewalBtn.addEventListener('click', () => {
      renewalModal.style.display = 'flex';
      renewalPhone.value = '';
      renewalError.style.display = 'none';
      renewalPhone.focus();
    });

    renewalCancel.addEventListener('click', () => {
      renewalModal.style.display = 'none';
    });

    renewalModal.addEventListener('click', (e) => {
      if (e.target === renewalModal) {
        renewalModal.style.display = 'none';
      }
    });

    renewalSubmit.addEventListener('click', async () => {
      const phone = renewalPhone.value.trim();

      if (!phone) {
        renewalError.textContent = L.phone_required || 'Telefonnummer ist erforderlich';
        renewalError.style.display = 'block';
        return;
      }

      renewalSubmit.disabled = true;
      renewalSubmit.textContent = L.sending || 'Wird gesendet...';

      try {
        const response = await fetch('/wp-admin/admin-ajax.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'ppv_request_subscription_renewal',
            phone: phone
          })
        });

        const data = await response.json();

        if (data.success) {
          renewalModal.style.display = 'none';
          location.reload(); // Reload to show "in progress" message
        } else {
          renewalError.textContent = data.data?.message || (L.error_occurred || 'Ein Fehler ist aufgetreten');
          renewalError.style.display = 'block';
          renewalSubmit.disabled = false;
          renewalSubmit.textContent = '✅ ' + (L.send_request || 'Anfrage senden');
        }
      } catch (err) {
        console.error('Renewal request error:', err);
        renewalError.textContent = L.error_occurred || 'Ein Fehler ist aufgetreten';
        renewalError.style.display = 'block';
        renewalSubmit.disabled = false;
        renewalSubmit.textContent = '✅ ' + (L.send_request || 'Anfrage senden');
      }
    });
  }

  // ============================================================
  // 🆘 SUPPORT TICKET MODAL
  // ============================================================
  const supportBtn = document.getElementById('ppv-support-btn');
  const supportModal = document.getElementById('ppv-support-modal');
  const supportSubmit = document.getElementById('ppv-support-submit');
  const supportCancel = document.getElementById('ppv-support-cancel');
  const supportDescription = document.getElementById('ppv-support-description');
  const supportPriority = document.getElementById('ppv-support-priority');
  const supportContact = document.getElementById('ppv-support-contact');
  const supportError = document.getElementById('ppv-support-error');
  const supportSuccess = document.getElementById('ppv-support-success');

  if (supportBtn && supportModal) {
    supportBtn.addEventListener('click', () => {
      supportModal.classList.add('show');
      supportDescription.value = '';
      supportPriority.value = 'normal';
      supportContact.value = 'email';
      supportError.classList.remove('show');
      supportSuccess.classList.remove('show');
      supportDescription.focus();
    });

    supportCancel.addEventListener('click', () => {
      supportModal.classList.remove('show');
    });

    supportModal.addEventListener('click', (e) => {
      if (e.target === supportModal) {
        supportModal.classList.remove('show');
      }
    });

    supportSubmit.addEventListener('click', async () => {
      const description = supportDescription.value.trim();
      const priority = supportPriority.value;
      const contactPref = supportContact.value;

      if (!description) {
        supportError.textContent = L.description_required || 'Problembeschreibung ist erforderlich';
        supportError.classList.add('show');
        return;
      }

      supportSubmit.disabled = true;
      const originalText = supportSubmit.textContent;
      supportSubmit.textContent = L.sending || 'Wird gesendet...';
      supportError.classList.remove('show');

      try {
        const response = await fetch('/wp-admin/admin-ajax.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'ppv_submit_support_ticket',
            description: description,
            priority: priority,
            contact_preference: contactPref,
            page_url: window.location.href
          })
        });

        const data = await response.json();

        if (data.success) {
          supportSuccess.textContent = data.data?.message || (L.ticket_sent || 'Ticket erfolgreich gesendet! Wir melden uns bald.');
          supportSuccess.classList.add('show');
          supportDescription.value = '';

          setTimeout(() => {
            supportModal.classList.remove('show');
            supportSuccess.classList.remove('show');
          }, 3000);
        } else {
          supportError.textContent = data.data?.message || (L.error_occurred || 'Ein Fehler ist aufgetreten');
          supportError.classList.add('show');
        }
      } catch (err) {
        console.error('Support ticket error:', err);
        supportError.textContent = L.error_occurred || 'Ein Fehler ist aufgetreten';
        supportError.classList.add('show');
      } finally {
        supportSubmit.disabled = false;
        supportSubmit.textContent = originalText;
      }
    });
  }

  // ============================================================
  // 👥 SCANNER USER MANAGEMENT
  // ============================================================
  const scannerModal = document.getElementById('ppv-scanner-modal');
  const newScannerBtn = document.getElementById('ppv-new-scanner-btn');
  const scannerCreate = document.getElementById('ppv-scanner-create');
  const scannerCancel = document.getElementById('ppv-scanner-cancel');
  const scannerEmail = document.getElementById('ppv-scanner-email');
  const scannerPassword = document.getElementById('ppv-scanner-password');
  const scannerGenPw = document.getElementById('ppv-scanner-gen-pw');
  const scannerError = document.getElementById('ppv-scanner-error');
  const scannerSuccess = document.getElementById('ppv-scanner-success');

  if (newScannerBtn && scannerModal) {
    // Open modal
    newScannerBtn.addEventListener('click', () => {
      scannerModal.style.display = 'flex';
      scannerEmail.value = '';
      scannerPassword.value = '';
      scannerError.style.display = 'none';
      scannerSuccess.style.display = 'none';
      scannerEmail.focus();
    });

    // Close modal
    scannerCancel.addEventListener('click', () => {
      scannerModal.style.display = 'none';
    });

    scannerModal.addEventListener('click', (e) => {
      if (e.target === scannerModal) {
        scannerModal.style.display = 'none';
      }
    });

    // Generate password
    scannerGenPw.addEventListener('click', () => {
      const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%^&*';
      let pw = '';
      for (let i = 0; i < 12; i++) {
        pw += chars.charAt(Math.floor(Math.random() * chars.length));
      }
      scannerPassword.value = pw;
    });

    // Create scanner
    scannerCreate.addEventListener('click', async () => {
      const email = scannerEmail.value.trim();
      const password = scannerPassword.value.trim();

      if (!email || !password) {
        scannerError.textContent = L.email_password_required || 'E-Mail und Passwort sind erforderlich';
        scannerError.style.display = 'block';
        return;
      }

      scannerCreate.disabled = true;
      const originalText = scannerCreate.textContent;
      scannerCreate.textContent = L.creating || 'Erstellen...';
      scannerError.style.display = 'none';

      try {
        const response = await fetch('/wp-admin/admin-ajax.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'ppv_create_scanner_user',
            email: email,
            password: password
          })
        });

        const data = await response.json();

        if (data.success) {
          scannerSuccess.textContent = data.data?.message || (L.scanner_created || 'Scanner erfolgreich erstellt!');
          scannerSuccess.style.display = 'block';
          scannerEmail.value = '';
          scannerPassword.value = '';

          setTimeout(() => {
            location.reload(); // Reload to show new scanner in list
          }, 1500);
        } else {
          scannerError.textContent = data.data?.message || (L.error_occurred || 'Ein Fehler ist aufgetreten');
          scannerError.style.display = 'block';
        }
      } catch (err) {
        console.error('Scanner creation error:', err);
        scannerError.textContent = L.error_occurred || 'Ein Fehler ist aufgetreten';
        scannerError.style.display = 'block';
      } finally {
        scannerCreate.disabled = false;
        scannerCreate.textContent = originalText;
      }
    });
  }

  // Reset password
  document.querySelectorAll('.ppv-scanner-reset-pw').forEach(btn => {
    btn.addEventListener('click', async function() {
      const userId = this.getAttribute('data-user-id');
      const email = this.getAttribute('data-email');

      const newPw = prompt(L.enter_new_password || 'Neues Passwort eingeben:', '');
      if (!newPw) return;

      this.disabled = true;
      const originalText = this.textContent;
      this.textContent = L.resetting || 'Reset...';

      try {
        const response = await fetch('/wp-admin/admin-ajax.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'ppv_reset_scanner_password',
            user_id: userId,
            new_password: newPw
          })
        });

        const data = await response.json();

        if (data.success) {
          alert(data.data?.message || (L.password_reset_success || 'Passwort erfolgreich zurückgesetzt!'));
        } else {
          alert(data.data?.message || (L.error_occurred || 'Ein Fehler ist aufgetreten'));
        }
      } catch (err) {
        console.error('Password reset error:', err);
        alert(L.error_occurred || 'Ein Fehler ist aufgetreten');
      } finally {
        this.disabled = false;
        this.textContent = originalText;
      }
    });
  });

  // Toggle enable/disable
  document.querySelectorAll('.ppv-scanner-toggle').forEach(btn => {
    btn.addEventListener('click', async function() {
      const userId = this.getAttribute('data-user-id');
      const action = this.getAttribute('data-action');

      if (!confirm((action === 'disable' ? L.confirm_disable : L.confirm_enable) || 'Sind Sie sicher?')) {
        return;
      }

      this.disabled = true;
      const originalText = this.textContent;
      this.textContent = L.processing || 'Verarbeitung...';

      try {
        const response = await fetch('/wp-admin/admin-ajax.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            action: 'ppv_toggle_scanner_status',
            user_id: userId,
            action_type: action
          })
        });

        const data = await response.json();

        if (data.success) {
          location.reload(); // Reload to show updated status
        } else {
          alert(data.data?.message || (L.error_occurred || 'Ein Fehler ist aufgetreten'));
        }
      } catch (err) {
        console.error('Toggle status error:', err);
        alert(L.error_occurred || 'Ein Fehler ist aufgetreten');
      } finally {
        this.disabled = false;
        this.textContent = originalText;
      }
    });
  });

  // ============================================================
  // 📋 LIVE RECENT SCANS POLLING (5s interval)
  // ============================================================
  if (logTable) {
    // Initial load
    async function loadRecentScans() {
      try {
        const response = await fetch('/wp-json/ppv/v1/pos/recent-scans', {
          method: 'GET',
          headers: { 'Content-Type': 'application/json' }
        });

        // ✅ Check if response is OK before parsing JSON
        if (!response.ok) {
          console.error(`❌ [loadRecentScans] HTTP error: ${response.status}`);
          return;
        }

        // ✅ Clone response BEFORE consuming it (for error debugging)
        const responseClone = response.clone();

        // ✅ Try to parse JSON with better error handling
        let data;
        try {
          data = await response.json();
        } catch (jsonErr) {
          // If JSON parsing fails, get the raw response body for debugging
          const text = await responseClone.text();
          console.error('❌ [loadRecentScans] JSON parse failed. Response body:', text);
          console.error('❌ [loadRecentScans] JSON error:', jsonErr);
          return;
        }

        if (data.success && data.scans) {
          // Clear current table
          logTable.innerHTML = '';

          // Add new rows (already sorted DESC by backend)
          data.scans.forEach(scan => {
            const row = document.createElement('tr');
            row.innerHTML = `<td>${scan.time}</td><td>${scan.user}</td><td>${scan.status}</td>`;
            logTable.appendChild(row);
          });
        } else if (data.success === false) {
          console.warn('⚠️ [loadRecentScans] Backend returned success=false:', data.message);
        }
      } catch (err) {
        console.error('❌ [loadRecentScans] Fetch error:', err);
      }
    }

    // Load immediately
    loadRecentScans();

    // Poll every 10 seconds
    setInterval(loadRecentScans, 10000);

  }

  // ============================================================
  // 📥 CSV EXPORT FUNCTIONALITY
  // ============================================================
  const csvExportBtn = document.getElementById('ppv-csv-export-btn');
  const csvExportMenu = document.getElementById('ppv-csv-export-menu');

  if (csvExportBtn && csvExportMenu) {
    // Toggle dropdown menu
    csvExportBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isVisible = csvExportMenu.style.display === 'block';
      csvExportMenu.style.display = isVisible ? 'none' : 'block';
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', () => {
      csvExportMenu.style.display = 'none';
    });

    // Handle CSV export options
    document.querySelectorAll('.ppv-csv-export-option').forEach(option => {
      option.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();

        const period = e.target.getAttribute('data-period');
        let date = new Date().toISOString().split('T')[0]; // Today's date

        // If "date" period, prompt for date
        if (period === 'date') {
          const userDate = prompt(L.csv_prompt_date || 'Datum eingeben (YYYY-MM-DD):', date);
          if (!userDate) return; // Cancelled
          date = userDate;
        }

        // If "month" period, use current month
        if (period === 'month') {
          date = new Date().toISOString().substr(0, 7) + '-01'; // First day of month
        }

        // Close dropdown
        csvExportMenu.style.display = 'none';

        // Download CSV
        try {

          const url = `/wp-json/ppv/v1/pos/export-logs?period=${period}&date=${date}`;
          const response = await fetch(url, {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json'
            }
          });

          if (!response.ok) {
            throw new Error('Export failed');
          }

          // Get CSV content
          const blob = await response.blob();
          const downloadUrl = window.URL.createObjectURL(blob);

          // Create download link
          const a = document.createElement('a');
          a.style.display = 'none';
          a.href = downloadUrl;
          a.download = `pos_logs_${period}_${date}.csv`;

          // Trigger download
          document.body.appendChild(a);
          a.click();

          // Cleanup
          window.URL.revokeObjectURL(downloadUrl);
          document.body.removeChild(a);


          if (window.ppvToast) {
            window.ppvToast('✅ CSV erfolgreich heruntergeladen', 'success');
          }
        } catch (err) {
          console.error('❌ [CSV Export] Failed:', err);
          if (window.ppvToast) {
            window.ppvToast('❌ CSV Export fehlgeschlagen', 'error');
          }
        }
      });

      // Hover effect
      option.addEventListener('mouseenter', (e) => {
        e.target.style.background = 'rgba(255,255,255,0.1)';
      });
      option.addEventListener('mouseleave', (e) => {
        e.target.style.background = 'transparent';
      });
    });

  }
});

