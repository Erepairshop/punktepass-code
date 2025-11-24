/**
 * PunktePass – Prämien Management Frontend
 * Version: 1.1 – FIXED + DEBUG
 */

console.log("✅ PPV Rewards Management JS v1.1 loaded");

document.addEventListener("DOMContentLoaded", function () {

  const base = ppv_rewards_mgmt?.base || "/wp-json/ppv/v1/";
  const storeID = ppv_rewards_mgmt?.store_id || window.PPV_STORE_ID || 0;


  const form = document.getElementById("ppv-reward-form");
  const listContainer = document.getElementById("ppv-rewards-list");
  const saveBtn = document.getElementById("save-btn");
  const cancelBtn = document.getElementById("cancel-btn");

  console.log("🔧 Config:", { base, storeID, form, listContainer });

  if (!listContainer) {
    console.error("❌ ppv-rewards-list container nem található!");
    return;
  }

  let editMode = false;
  let editID = 0;

  const L = window.ppv_lang || {};

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
          <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; flex-wrap: wrap; gap: 8px;">
            <small style="color:#00e6ff;"><strong>⭐ ${r.required_points} ${L.rewards_points_label || 'Pontok'}</strong></small>
            <small style="color:#999;">➕ ${r.points_given || 0} ${L.rewards_points_given_label || 'Pontok adott'}</small>
            <small style="color:#999;">${r.action_type || ""}: ${r.action_value || ""} ${r.currency || ''}</small>
          </div>
          <div style="display:flex; gap:8px; margin-top:12px;">
            <button class="ppv-btn-outline ppv-edit" data-id="${r.id}" style="flex:1;">✏️ ${L.rewards_btn_edit || 'Szerkesztés'}</button>
            <button class="ppv-btn-outline ppv-delete" data-id="${r.id}" style="flex:1; color:#ef4444; border-color:#ef4444;">🗑️ ${L.rewards_btn_delete || 'Törlés'}</button>
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
   * 💾 SAVE REWARD (CREATE OR UPDATE)
   * ============================================================ */
  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      // 🏢 Get filiale selection (if available)
      const targetStoreSelect = document.getElementById("reward-target-store");
      const applyAllCheckbox = document.getElementById("reward-apply-all");

      const body = {
        store_id: storeID,
        title: form.title.value.trim(),
        required_points: parseInt(form.required_points.value),
        points_given: parseInt(document.getElementById("reward-points-given").value || 0),
        description: form.description.value.trim(),
        action_type: form.action_type.value,
        action_value: form.action_value.value.trim(),
        free_product: document.getElementById("reward-free-product-name")?.value.trim() || "",
        free_product_value: parseFloat(document.getElementById("reward-free-product-value")?.value || 0),
        // 🏢 Filiale options
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

        showToast(json.message || (L.rewards_saved || "✅ Mentve."), "success");
        
        resetForm();
        loadRewards();

      } catch (err) {
        console.error("❌ saveReward hiba:", err);
        showToast(`⚠️ ${L.rewards_error_save || 'Mentési hiba'}: ${err.message}`, "error");
      }
    });
  }

  /* ============================================================
   * ✏️ EDIT REWARD
   * ============================================================ */
  document.body.addEventListener("click", async (e) => {
    if (e.target.classList.contains("ppv-edit")) {
      const id = e.target.dataset.id;
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

          // Free product fields
          const freeProductNameInput = document.getElementById("reward-free-product-name");
          const freeProductValueInput = document.getElementById("reward-free-product-value");
          if (freeProductNameInput) freeProductNameInput.value = reward.free_product || "";
          if (freeProductValueInput) freeProductValueInput.value = reward.free_product_value || 0;

          // Trigger field visibility update
          toggleRewardFields();

          saveBtn.textContent = "💾 " + (L.rewards_btn_update || 'Frissítés');
          cancelBtn.style.display = "block";
          
          form.scrollIntoView({ behavior: "smooth" });
        } else {
          console.error("❌ Prémium nem találva:", id);
        }
      } catch (err) {
        console.error("❌ loadReward hiba:", err);
        showToast(`⚠️ ${L.rewards_error_loading || 'Hiba a betöltéskor'}: ${err.message}`, "error");
      }
    }
  });

  /* ============================================================
   * 🗑️ DELETE REWARD
   * ============================================================ */
  document.body.addEventListener("click", async (e) => {
    if (e.target.classList.contains("ppv-delete")) {
      if (!confirm(L.rewards_confirm_delete || "Biztosan törlöd a jutalmat?")) return;
      
      const id = e.target.dataset.id;
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

        showToast(json.message || (L.rewards_deleted || "🗑️ Törölve."), "success");
        loadRewards();

      } catch (err) {
        console.error("❌ deleteReward hiba:", err);
        showToast(`⚠️ ${L.rewards_error_delete || 'Törlési hiba'}: ${err.message}`, "error");
      }
    }
  });

  /* ============================================================
   * ❌ CANCEL EDIT
   * ============================================================ */
  if (cancelBtn) {
    cancelBtn.addEventListener("click", resetForm);
  }

  function resetForm() {
    editMode = false;
    editID = 0;
    form.reset();
    saveBtn.textContent = "💾 " + (L.rewards_form_save || 'Mentés');
    cancelBtn.style.display = "none";

    // 🏢 Reset filiale selector
    const targetStoreSelect = document.getElementById("reward-target-store");
    const applyAllCheckbox = document.getElementById("reward-apply-all");
    if (targetStoreSelect) targetStoreSelect.value = "current";
    if (applyAllCheckbox) applyAllCheckbox.checked = false;
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
   * 🎯 DYNAMIC FORM - Show/Hide fields based on action_type
   * ============================================================ */
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
      // 🎁 Ingyenes termék - Product mezők láthatók, action_value HIDDEN
      if (rewardValueInput) rewardValueInput.style.display = "none";
      if (rewardValueLabel) rewardValueLabel.style.display = "none";
      if (rewardValueHelper) rewardValueHelper.style.display = "none";
      if (rewardValueInput) rewardValueInput.value = "0";
      if (rewardValueInput) rewardValueInput.removeAttribute("required");

      if (freeProductNameWrapper) freeProductNameWrapper.style.display = "block";
      if (freeProductValueWrapper) freeProductValueWrapper.style.display = "block";
    } else {
      // 💶 Rabatt típusok - action_value látható, Product mezők HIDDEN
      if (rewardValueInput) rewardValueInput.style.display = "block";
      if (rewardValueLabel) rewardValueLabel.style.display = "block";
      if (rewardValueHelper) rewardValueHelper.style.display = "block";
      if (rewardValueInput) rewardValueInput.setAttribute("required", "required");

      if (freeProductNameWrapper) freeProductNameWrapper.style.display = "none";
      if (freeProductValueWrapper) freeProductValueWrapper.style.display = "none";
    }
  }

  if (rewardTypeSelect) {
    rewardTypeSelect.addEventListener("change", toggleRewardFields);
    // Initial check
    toggleRewardFields();
  }

  /* ============================================================
   * 📡 ABLY REAL-TIME + POLLING FALLBACK
   * ============================================================ */
  const config = window.ppv_rewards_mgmt || {};
  let pollInterval = null;

  function initRealtime() {
    if (config.ably && config.ably.key && typeof Ably !== 'undefined') {
      console.log('📡 [REWARDS-MGMT] Initializing Ably real-time...');

      const ably = new Ably.Realtime({ key: config.ably.key });
      const channel = ably.channels.get(config.ably.channel);

      ably.connection.on('connected', () => {
        console.log('📡 [REWARDS-MGMT] Ably connected');
        // Stop polling if running
        if (pollInterval) {
          clearInterval(pollInterval);
          pollInterval = null;
        }
      });

      ably.connection.on('disconnected', () => {
        console.log('📡 [REWARDS-MGMT] Ably disconnected, starting polling');
        startPolling();
      });

      ably.connection.on('failed', (err) => {
        console.log('📡 [REWARDS-MGMT] Ably failed:', err);
        startPolling();
      });

      // 📡 Handle reward updates
      channel.subscribe('reward-update', (message) => {
        console.log('📡 [REWARDS-MGMT] Reward update received:', message.data);
        showToast(`🎁 Prämie ${message.data.action === 'created' ? 'erstellt' : message.data.action === 'updated' ? 'aktualisiert' : 'gelöscht'}`, 'info');
        loadRewards();
      });

      console.log('📡 [REWARDS-MGMT] Ably initialized');
    } else {
      console.log('🔄 [REWARDS-MGMT] Ably not available, using polling');
      startPolling();
    }
  }

  function startPolling() {
    if (pollInterval) return; // Already polling
    console.log('🔄 [REWARDS-MGMT] Starting polling (30s interval)');
    pollInterval = setInterval(() => {
      if (listContainer) {
        loadRewards();
      }
    }, 30000);
  }

  /* ============================================================
   * 🚀 INIT
   * ============================================================ */
  console.log("🚀 Initializing rewards management...");
  loadRewards();
  initRealtime();
});