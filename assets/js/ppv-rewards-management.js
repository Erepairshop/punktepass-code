/**
 * PunktePass – Prämien Management Frontend
 * Version: 1.2 – TURBO COMPATIBLE
 */

console.log("✅ PPV Rewards Management JS v1.2 loaded (Turbo-compatible)");

(function() {
  "use strict";

  // Module-level state
  let editMode = false;
  let editID = 0;
  let base = "";
  let storeID = 0;
  let form = null;
  let listContainer = null;
  let saveBtn = null;
  let cancelBtn = null;
  let L = {};
  let eventListenersAttached = false;

  /* ============================================================
   * 🧩 TOAST HELPER
   * ============================================================ */
  function showToast(msg, type = "info") {
    const el = document.createElement("div");
    el.className = `ppv-toast ${type}`;
    el.textContent = msg;
    el.style.cssText = `
      position: fixed;
      bottom: 20px;
      right: 20px;
      padding: 1rem 1.5rem;
      background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
      color: white;
      border-radius: 8px;
      z-index: 999999;
      animation: slideIn 0.3s ease-out;
    `;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  }

  /* ============================================================
   * 🛡️ ESCAPE HTML
   * ============================================================ */
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /* ============================================================
   * 📋 LOAD REWARDS
   * ============================================================ */
  async function loadRewards() {
    if (!listContainer) {
      console.error("❌ listContainer nem elérhető");
      return;
    }

    if (!storeID) {
      console.error("❌ storeID hiányzik!");
      listContainer.innerHTML = "<p style='text-align:center;color:#ef4444;'>⚠️ " + (L.rewards_error_no_store || "Nincs Store ID!") + "</p>";
      return;
    }

    const url = `${base}rewards/list?store_id=${storeID}`;
    console.log("📡 Fetch URL:", url);

    listContainer.innerHTML = "<div class='ppv-loading'>⏳ " + (L.rewards_list_loading || "Betöltés...") + "</div>";

    try {
      const res = await fetch(url);
      console.log("📨 Response status:", res.status, res.statusText);

      const json = await res.json();
      console.log("📦 Response JSON:", json);

      if (!json?.success) {
        console.warn("⚠️ API nem sikeres:", json?.message);
        listContainer.innerHTML = `<p style='text-align:center;color:#999;'>ℹ️ ${json?.message || L.rewards_form_none || 'Nincsenek jutalmak.'}</p>`;
        return;
      }

      if (!json?.rewards || json.rewards.length === 0) {
        console.log("ℹ️ Nincsenek prémiumok");
        listContainer.innerHTML = "<p style='text-align:center;color:#999;'>ℹ️ " + (L.rewards_form_none || "Nincsenek jutalmak.") + "</p>";
        return;
      }

      console.log("✅ Prémiumok betöltve:", json.rewards.length);

      listContainer.innerHTML = "";
      json.rewards.forEach((r) => {
        console.log("  🎁 Prémium:", r.title, "Pontok:", r.required_points);

        const card = document.createElement("div");
        card.className = "ppv-reward-item glass-card";
        card.innerHTML = `
          <h4>${escapeHtml(r.title)}</h4>
          <p>${escapeHtml(r.description || "")}</p>
          <div class="ppv-reward-item-meta">
            <small class="ppv-points-required"><strong>⭐ ${r.required_points} ${L.rewards_points_label || 'Punkte'}</strong></small>
            <small class="ppv-points-given">➕ ${r.points_given || 0} ${L.rewards_points_given_label || 'Punkte vergeben'}</small>
            <small class="ppv-reward-type">${r.action_type || ""}: ${r.action_value || ""} ${r.currency || ''}</small>
          </div>
          <div class="ppv-reward-item-actions">
            <button type="button" class="ppv-btn-outline ppv-edit" data-id="${r.id}">✏️ ${L.rewards_btn_edit || 'Bearbeiten'}</button>
            <button type="button" class="ppv-btn-outline ppv-delete" data-id="${r.id}">🗑️ ${L.rewards_btn_delete || 'Löschen'}</button>
          </div>
        `;
        listContainer.appendChild(card);
      });

    } catch (err) {
      console.error("❌ loadRewards hiba:", err);
      listContainer.innerHTML = `<p style='text-align:center;color:#ef4444;'>⚠️ ${L.rewards_error_loading || 'Hiba a betöltéskor'}: ${err.message}</p>`;
    }
  }

  /* ============================================================
   * 🔄 RESET FORM
   * ============================================================ */
  function resetForm() {
    editMode = false;
    editID = 0;
    if (form) form.reset();
    if (saveBtn) saveBtn.textContent = "💾 " + (L.rewards_form_save || 'Speichern');
    if (cancelBtn) cancelBtn.style.display = "none";

    // 🏢 Reset filiale selector
    const targetStoreSelect = document.getElementById("reward-target-store");
    const applyAllCheckbox = document.getElementById("reward-apply-all");
    if (targetStoreSelect) targetStoreSelect.value = "current";
    if (applyAllCheckbox) applyAllCheckbox.checked = false;
  }

  /* ============================================================
   * 🎯 DYNAMIC FORM - Show/Hide fields based on action_type
   * ============================================================ */
  function initDynamicForm() {
    const rewardTypeSelect = document.getElementById("reward-type");
    const rewardValueInput = document.getElementById("reward-value");
    const rewardValueLabel = rewardValueInput?.previousElementSibling;
    const rewardValueHelper = rewardValueInput?.nextElementSibling;
    const freeProductNameWrapper = document.getElementById("reward-free-product-name-wrapper");
    const freeProductValueWrapper = document.getElementById("reward-free-product-value-wrapper");

    function toggleRewardFields() {
      if (!rewardTypeSelect) return;

      const selectedType = rewardTypeSelect.value;

      if (selectedType === "free_product") {
        if (rewardValueInput) rewardValueInput.style.display = "none";
        if (rewardValueLabel) rewardValueLabel.style.display = "none";
        if (rewardValueHelper) rewardValueHelper.style.display = "none";
        if (rewardValueInput) rewardValueInput.value = "0";
        if (rewardValueInput) rewardValueInput.removeAttribute("required");

        if (freeProductNameWrapper) freeProductNameWrapper.style.display = "block";
        if (freeProductValueWrapper) freeProductValueWrapper.style.display = "block";
      } else {
        if (rewardValueInput) rewardValueInput.style.display = "block";
        if (rewardValueLabel) rewardValueLabel.style.display = "block";
        if (rewardValueHelper) rewardValueHelper.style.display = "block";
        if (rewardValueInput) rewardValueInput.setAttribute("required", "required");

        if (freeProductNameWrapper) freeProductNameWrapper.style.display = "none";
        if (freeProductValueWrapper) freeProductValueWrapper.style.display = "none";
      }
    }

    if (rewardTypeSelect) {
      // Remove old listener if exists, then add new one
      rewardTypeSelect.removeEventListener("change", toggleRewardFields);
      rewardTypeSelect.addEventListener("change", toggleRewardFields);
      toggleRewardFields();
    }

    // Expose for edit mode
    window.toggleRewardFields = toggleRewardFields;
  }

  /* ============================================================
   * 📝 SETUP FORM SUBMIT HANDLER
   * ============================================================ */
  function setupFormSubmit() {
    if (!form) return;

    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      const targetStoreSelect = document.getElementById("reward-target-store");
      const applyAllCheckbox = document.getElementById("reward-apply-all");

      const body = {
        store_id: storeID,
        title: form.title.value.trim(),
        required_points: parseInt(form.required_points.value),
        points_given: parseInt(document.getElementById("reward-points-given")?.value || 0),
        description: form.description.value.trim(),
        action_type: form.action_type.value,
        action_value: form.action_value.value.trim(),
        free_product: document.getElementById("reward-free-product-name")?.value?.trim() || "",
        free_product_value: parseFloat(document.getElementById("reward-free-product-value")?.value || 0),
        target_store_id: targetStoreSelect?.value || "current",
        apply_to_all: applyAllCheckbox?.checked || false
      };

      console.log("💾 Save body:", body);

      const endpoint = editMode ? "rewards/update" : "rewards/save";
      if (editMode) body.id = editID;

      try {
        const res = await fetch(`${base}${endpoint}`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(body)
        });

        console.log("📨 Save response status:", res.status);

        const json = await res.json();
        console.log("✅ Save response:", json);

        showToast(json.message || (L.rewards_saved || "✅ Gespeichert."), "success");

        resetForm();
        loadRewards();

      } catch (err) {
        console.error("❌ saveReward hiba:", err);
        showToast(`⚠️ ${L.rewards_error_save || 'Speicherfehler'}: ${err.message}`, "error");
      }
    });
  }

  /* ============================================================
   * 🖱️ SETUP EVENT DELEGATION (ONCE)
   * ============================================================ */
  function setupEventDelegation() {
    if (eventListenersAttached) {
      console.log("📋 Event listeners already attached, skipping");
      return;
    }
    eventListenersAttached = true;

    // ✏️ EDIT REWARD
    document.body.addEventListener("click", async (e) => {
      const editBtn = e.target.closest(".ppv-edit");
      if (!editBtn) return;

      e.preventDefault();
      e.stopPropagation();

      const id = editBtn.dataset.id;
      console.log("✏️ Edit ID:", id);

      try {
        const res = await fetch(`${base}rewards/list?store_id=${storeID}`);
        const json = await res.json();
        console.log("📦 Edit fetch response:", json);

        const reward = json.rewards.find(r => r.id == id);

        if (reward) {
          console.log("✏️ Editing:", reward);
          editMode = true;
          editID = reward.id;

          document.getElementById("reward-title").value = reward.title;
          document.getElementById("reward-points").value = reward.required_points;
          document.getElementById("reward-points-given").value = reward.points_given || 0;
          document.getElementById("reward-description").value = reward.description || "";
          document.getElementById("reward-type").value = reward.action_type || "discount_percent";
          document.getElementById("reward-value").value = reward.action_value || "";

          const freeProductNameInput = document.getElementById("reward-free-product-name");
          const freeProductValueInput = document.getElementById("reward-free-product-value");
          if (freeProductNameInput) freeProductNameInput.value = reward.free_product || "";
          if (freeProductValueInput) freeProductValueInput.value = reward.free_product_value || 0;

          if (window.toggleRewardFields) window.toggleRewardFields();

          if (saveBtn) saveBtn.textContent = "💾 " + (L.rewards_btn_update || 'Aktualisieren');
          if (cancelBtn) cancelBtn.style.display = "block";

          if (form) form.scrollIntoView({ behavior: "smooth" });
        } else {
          console.error("❌ Prémium nem találva:", id);
        }
      } catch (err) {
        console.error("❌ loadReward hiba:", err);
        showToast(`⚠️ ${L.rewards_error_loading || 'Ladefehler'}: ${err.message}`, "error");
      }
    });

    // 🗑️ DELETE REWARD
    document.body.addEventListener("click", async (e) => {
      const deleteBtn = e.target.closest(".ppv-delete");
      if (!deleteBtn) return;

      e.preventDefault();
      e.stopPropagation();

      if (!confirm(L.rewards_confirm_delete || "Wirklich löschen?")) return;

      const id = deleteBtn.dataset.id;
      console.log("🗑️ Delete ID:", id);

      try {
        const res = await fetch(`${base}rewards/delete`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ store_id: storeID, id })
        });

        console.log("📨 Delete response status:", res.status);

        const json = await res.json();
        console.log("✅ Delete response:", json);

        showToast(json.message || (L.rewards_deleted || "🗑️ Gelöscht."), "success");
        loadRewards();

      } catch (err) {
        console.error("❌ deleteReward hiba:", err);
        showToast(`⚠️ ${L.rewards_error_delete || 'Löschfehler'}: ${err.message}`, "error");
      }
    });

    console.log("📋 Event delegation attached");
  }

  /* ============================================================
   * 🚀 MAIN INIT FUNCTION
   * ============================================================ */
  function initRewardsManagement() {
    const wrapper = document.querySelector(".ppv-rewards-management-wrapper");
    if (!wrapper) {
      console.log("📋 Not a rewards management page, skipping");
      return;
    }

    if (wrapper.dataset.initialized === "true") {
      console.log("📋 Already initialized, skipping");
      return;
    }

    wrapper.dataset.initialized = "true";

    // Update module-level variables
    base = window.ppv_rewards_mgmt?.base || "/wp-json/ppv/v1/";
    storeID = window.ppv_rewards_mgmt?.store_id || window.PPV_STORE_ID || 0;

    form = document.getElementById("ppv-reward-form");
    listContainer = document.getElementById("ppv-rewards-list");
    saveBtn = document.getElementById("save-btn");
    cancelBtn = document.getElementById("cancel-btn");
    L = window.ppv_lang || {};

    console.log("🔧 Config:", { base, storeID, form, listContainer });

    if (!listContainer) {
      console.error("❌ ppv-rewards-list container nem található!");
      return;
    }

    // Reset state
    editMode = false;
    editID = 0;

    // Setup form submit
    setupFormSubmit();

    // Setup cancel button
    if (cancelBtn) {
      cancelBtn.addEventListener("click", resetForm);
    }

    // Setup dynamic form fields
    initDynamicForm();

    // Setup event delegation (only once)
    setupEventDelegation();

    // Load rewards
    console.log("🚀 Rewards management initialized (Turbo-compatible)");
    loadRewards();
  }

  /* ============================================================
   * 🚀 EVENT LISTENERS (Turbo-compatible)
   * ============================================================ */

  // Standard DOMContentLoaded
  document.addEventListener("DOMContentLoaded", initRewardsManagement);

  // 🚀 Turbo-compatible: Re-initialize after navigation
  document.addEventListener("turbo:load", function() {
    const wrapper = document.querySelector(".ppv-rewards-management-wrapper");
    if (wrapper) {
      wrapper.dataset.initialized = "false";
    }
    initRewardsManagement();
  });

  document.addEventListener("turbo:render", function() {
    const wrapper = document.querySelector(".ppv-rewards-management-wrapper");
    if (wrapper) {
      wrapper.dataset.initialized = "false";
    }
    initRewardsManagement();
  });

})();
