/**
 * PunktePass – Händler Reward Management (v4.3 FINAL)
 * ✅ REST + Token kompatibilis
 * ✅ storeId fallback (sessionStorage)
 * ✅ Toast + Error Handling stabil
 * ✅ Safe JSON + Type Guard
 * Author: PunktePass (Erik)
 */

console.log("🔥 PPV Rewards JS v4.3 geladen");

document.addEventListener("DOMContentLoaded", function () {

  if (!window.PPV_STORE_ID && !sessionStorage.getItem("ppv_store_id")) {
    console.warn("⚠️ Keine store_id im Kontext!");
  }

  /* ============================================================
   *  Store Detection
   * ============================================================ */
  const storeId =
    Number(window.PPV_STORE_ID || 0) ||
    Number(sessionStorage.getItem("ppv_store_id") || 0) ||
    1;

  sessionStorage.setItem("ppv_store_id", storeId);

  /* ============================================================
   *  POS-Token Fallback
   * ============================================================ */
  const POS_TOKEN =
    (window.PPV_STORE_KEY || "").trim() ||
    (sessionStorage.getItem("ppv_store_key") || "").trim() ||
    "";

  if (!POS_TOKEN) {
    console.warn("⚠️ PPV-POS-Token fehlt – REST könnte ablehnen.");
  }

  /* ============================================================
   *  DOM Elements
   * ============================================================ */
  const base = ppv_rewards_rest.base;
  const listContainer = document.getElementById("ppv-rewards-list");
  const redeemList = document.getElementById("ppv-redeem-list");
  const form = document.getElementById("ppv-reward-form");

  /* ============================================================
   *  Toast Helper
   * ============================================================ */
  function showToast(msg, type = "info") {
    const t = document.createElement("div");
    t.className = `ppv-toast ${type}`;
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(() => t.classList.add("show"));
    setTimeout(() => {
      t.classList.remove("show");
      setTimeout(() => t.remove(), 350);
    }, 2800);
  }

  /* ============================================================
   *  LOAD REWARDS
   * ============================================================ */
  async function loadRewards() {
    if (!listContainer) return;
    listContainer.innerHTML = "⏳ Lade…";

    const url = `${base}rewards/list?store_id=${storeId}`;
    try {
      const res = await fetch(url, {
        headers: { "PPV-POS-Token": POS_TOKEN },
      });
      const data = await res.json();

      if (!data.success || !Array.isArray(data.rewards)) {
        listContainer.innerHTML = "ℹ️ Keine Prämien vorhanden.";
        return;
      }

      listContainer.innerHTML = "";
      data.rewards.forEach((r) => {
        const card = document.createElement("div");
        card.className = "ppv-reward-item glass-card";
        card.innerHTML = `
          <h4>${r.title}</h4>
          <p>${r.description || ""}</p>
          <small>⭐ ${r.required_points} Punkte</small><br>
          <small>${r.action_type}: ${r.action_value}</small><br>
          <button class="ppv-delete" data-id="${r.id}">🗑️ Löschen</button>
        `;
        listContainer.appendChild(card);
      });

    } catch (err) {
      console.warn(err);
      listContainer.innerHTML = "⚠️ Fehler beim Laden.";
    }
  }

 

  /* ============================================================
   *  SAVE NEW REWARD
   * ============================================================ */
  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const body = {
        store_id: storeId,
        title: form.title.value.trim(),
        required_points: Number(form.required_points.value),
        description: form.description.value.trim(),
        action_type: form.action_type.value,
        action_value: form.action_value.value.trim(),
      };

      try {
        const res = await fetch(`${base}rewards/save`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "PPV-POS-Token": POS_TOKEN,
          },
          body: JSON.stringify(body),
        });

        const data = await res.json();
        showToast(data.message || "✅ Gespeichert", "success");

        form.reset();
        loadRewards();

      } catch (_) {
        showToast("⚠️ Fehler beim Speichern", "error");
      }
    });
  }

  /* ============================================================
   *  DELETE REWARD
   * ============================================================ */
  document.body.addEventListener("click", async (e) => {
    if (!e.target.classList.contains("ppv-delete")) return;

    if (!confirm("Prämie löschen?")) return;
    const id = e.target.dataset.id;

    try {
      await fetch(`${base}rewards/delete`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "PPV-POS-Token": POS_TOKEN,
        },
        body: JSON.stringify({ id, store_id: storeId }),
      });

      showToast("🗑️ Gelöscht!", "success");
      loadRewards();
    } catch (_) {
      showToast("⚠️ Fehler beim Löschen", "error");
    }
  });

  /* ============================================================
   *  APPROVE / REJECT REDEEM
   * ============================================================ */
  document.body.addEventListener("click", async (e) => {
    if (!e.target.classList.contains("ppv-approve") &&
        !e.target.classList.contains("ppv-reject")) return;

    const id = e.target.dataset.id;
    const status = e.target.classList.contains("ppv-approve") ? "approved" : "cancelled";

    try {
      const res = await fetch(`${base}rewards/approve`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "PPV-POS-Token": POS_TOKEN,
        },
        body: JSON.stringify({ id, status, store_id: storeId }),
      });

      const json = await res.json();
      showToast(json.message || "✅ abgeschlossen", "success");

      loadRedeemRequests();

    } catch (_) {
      showToast("⚠️ Serverfehler", "error");
    }
  });

  /* ============================================================
   *  INIT
   * ============================================================ */
  loadRewards();
  loadRedeemRequests();
});
