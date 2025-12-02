/**
 * PunktePass – Prämien Management Frontend
 * Version: 2.0 – MODERN DESIGN
 */


document.addEventListener("DOMContentLoaded", function () {

  const base = ppv_rewards_mgmt?.base || "/wp-json/ppv/v1/";
  const storeID = ppv_rewards_mgmt?.store_id || window.PPV_STORE_ID || 0;

  const form = document.getElementById("ppv-reward-form");
  const formWrapper = document.getElementById("ppv-reward-form-wrapper");
  const listContainer = document.getElementById("ppv-rewards-list");
  const saveBtn = document.getElementById("save-btn");
  const cancelBtn = document.getElementById("cancel-btn");
  const toggleFormBtn = document.getElementById("ppv-toggle-form");
  const rewardsCountEl = document.getElementById("ppv-rewards-count");


  if (!listContainer) {
    // Not a rewards management page, silently exit
    return;
  }

  let editMode = false;
  let editID = 0;

  const L = window.ppv_lang || {};

  /* ============================================================
   * 🎯 TOGGLE FORM VISIBILITY
   * ============================================================ */
  if (toggleFormBtn && formWrapper) {
    toggleFormBtn.addEventListener("click", function() {
      const isVisible = formWrapper.style.display !== "none";
      formWrapper.style.display = isVisible ? "none" : "block";
      toggleFormBtn.innerHTML = isVisible
        ? '<i class="ri-add-line"></i><span>' + (L.rewards_add_new || 'Új jutalom') + '</span>'
        : '<i class="ri-close-line"></i><span>' + (L.rewards_form_cancel || 'Mégse') + '</span>';
    });
  }

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
    
    listContainer.innerHTML = "<div class='ppv-loading'>⏳ " + (L.rewards_list_loading || "Betöltés...") + "</div>";

    try {
      const res = await fetch(url);
      
      const json = await res.json();

      if (!json?.success) {
        console.warn("⚠️ API nem sikeres:", json?.message);
        listContainer.innerHTML = `<p style='text-align:center;color:#999;'>ℹ️ ${json?.message || L.rewards_form_none || 'Nincsenek jutalmak.'}</p>`;
        return;
      }

      if (!json?.rewards || json.rewards.length === 0) {
        if (rewardsCountEl) rewardsCountEl.textContent = "0";
        listContainer.innerHTML = `
          <div class="ppv-empty-state">
            <i class="ri-gift-line"></i>
            <p>${L.rewards_form_none || "Nincsenek jutalmak. Kattints az 'Új jutalom' gombra a létrehozáshoz!"}</p>
          </div>
        `;
        return;
      }


      // Update rewards count
      if (rewardsCountEl) {
        rewardsCountEl.textContent = json.rewards.length;
      }

      listContainer.innerHTML = "";
      json.rewards.forEach((r) => {

        // 📅 Campaign badge and date info
        const isCampaign = r.is_campaign == 1;
        const campaignBadge = isCampaign
          ? `<span class="ppv-reward-campaign-badge"><i class="ri-calendar-event-fill"></i> ${L.rewards_campaign_badge || 'Kampány'}</span>`
          : '';

        let dateInfo = '';
        if (isCampaign && (r.start_date || r.end_date)) {
          const startStr = r.start_date ? formatDate(r.start_date) : '∞';
          const endStr = r.end_date ? formatDate(r.end_date) : '∞';
          dateInfo = `<div class="ppv-reward-dates">
            <i class="ri-calendar-line"></i> ${startStr} → ${endStr}
          </div>`;
        }

        // Get action type label
        const actionTypeLabels = {
          'discount_percent': L.rewards_type_percent || 'Kedvezmény',
          'discount_fixed': L.rewards_type_fixed || 'Fix kedvezmény',
          'free_product': L.rewards_type_free || 'Ingyenes termék'
        };
        const actionLabel = actionTypeLabels[r.action_type] || r.action_type;

        // Format value display
        let valueDisplay = '';
        if (r.action_type === 'discount_percent') {
          valueDisplay = `${r.action_value}%`;
        } else if (r.action_type === 'free_product') {
          valueDisplay = r.free_product || L.rewards_free_product || 'Ingyenes';
        } else {
          valueDisplay = `${r.action_value} ${r.currency || 'EUR'}`;
        }

        const card = document.createElement("div");
        card.className = "ppv-reward-card";
        card.innerHTML = `
          <div class="ppv-reward-card-header">
            <h4 class="ppv-reward-card-title">
              <i class="ri-gift-line"></i>
              ${escapeHtml(r.title)}
            </h4>
            ${campaignBadge}
          </div>
          <div class="ppv-reward-card-body">
            ${r.description ? `<p class="ppv-reward-description">${escapeHtml(r.description)}</p>` : ''}
            ${dateInfo}
            <div class="ppv-reward-stats">
              <div class="ppv-reward-stat points">
                <i class="ri-star-fill"></i>
                <span>${r.required_points} ${L.rewards_points_label || 'Punkte'}</span>
              </div>
              <div class="ppv-reward-stat bonus">
                <i class="ri-add-circle-fill"></i>
                <span>+${r.points_given || 0}</span>
              </div>
              <div class="ppv-reward-stat value">
                <i class="ri-price-tag-3-fill"></i>
                <span>${valueDisplay}</span>
              </div>
            </div>
            <div class="ppv-reward-card-actions">
              <button class="ppv-reward-btn ppv-reward-btn-edit ppv-edit" data-id="${r.id}">
                <i class="ri-edit-line"></i>
                ${L.rewards_btn_edit || 'Szerkesztés'}
              </button>
              <button class="ppv-reward-btn ppv-reward-btn-delete ppv-delete" data-id="${r.id}">
                <i class="ri-delete-bin-line"></i>
                ${L.rewards_btn_delete || 'Törlés'}
              </button>
            </div>
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

      // 📅 Campaign fields
      const isCampaignCheckbox = document.getElementById("reward-is-campaign");
      const startDateInput = document.getElementById("reward-start-date");
      const endDateInput = document.getElementById("reward-end-date");

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
        // 📅 Campaign options
        is_campaign: isCampaignCheckbox?.checked ? 1 : 0,
        start_date: startDateInput?.value || null,
        end_date: endDateInput?.value || null,
        // 🏢 Filiale options
        target_store_id: targetStoreSelect?.value || "current",
        apply_to_all: applyAllCheckbox?.checked || false
      };


      const endpoint = editMode ? "rewards/update" : "rewards/save";
      if (editMode) body.id = editID;

      try {
        const res = await fetch(`${base}${endpoint}`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": ppv_rewards_mgmt?.nonce || ""
          },
          body: JSON.stringify(body)
        });


        const json = await res.json();

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
      
      try {
        const res = await fetch(`${base}rewards/list?store_id=${storeID}`);
        const json = await res.json();

        const reward = json.rewards.find(r => r.id == id);
        
        if (reward) {
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

          // 📅 Campaign fields
          const isCampaignCheckbox = document.getElementById("reward-is-campaign");
          const startDateInput = document.getElementById("reward-start-date");
          const endDateInput = document.getElementById("reward-end-date");
          const campaignDateFields = document.getElementById("campaign-date-fields");

          if (isCampaignCheckbox) {
            isCampaignCheckbox.checked = reward.is_campaign == 1;
            if (campaignDateFields) {
              if (reward.is_campaign == 1) {
                campaignDateFields.classList.add("show");
              } else {
                campaignDateFields.classList.remove("show");
              }
            }
          }
          if (startDateInput) startDateInput.value = reward.start_date || "";
          if (endDateInput) endDateInput.value = reward.end_date || "";

          // Trigger field visibility update
          toggleRewardFields();

          // Update button and show form
          saveBtn.innerHTML = '<i class="ri-save-line"></i><span>' + (L.rewards_btn_update || 'Frissítés') + '</span>';

          // Show form wrapper
          if (formWrapper) {
            formWrapper.style.display = "block";
          }
          if (toggleFormBtn) {
            toggleFormBtn.innerHTML = '<i class="ri-close-line"></i><span>' + (L.rewards_form_cancel || 'Mégse') + '</span>';
          }

          formWrapper.scrollIntoView({ behavior: "smooth" });
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

      try {
        const res = await fetch(`${base}rewards/delete`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": ppv_rewards_mgmt?.nonce || ""
          },
          body: JSON.stringify({ store_id: storeID, id })
        });


        const json = await res.json();

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
    saveBtn.innerHTML = '<i class="ri-save-line"></i><span>' + (L.rewards_form_save || 'Mentés') + '</span>';

    // 🏢 Reset filiale selector
    const targetStoreSelect = document.getElementById("reward-target-store");
    const applyAllCheckbox = document.getElementById("reward-apply-all");
    if (targetStoreSelect) targetStoreSelect.value = "current";
    if (applyAllCheckbox) applyAllCheckbox.checked = false;

    // 📅 Reset campaign fields
    const isCampaignCheckbox = document.getElementById("reward-is-campaign");
    const campaignDateFields = document.getElementById("campaign-date-fields");
    if (isCampaignCheckbox) isCampaignCheckbox.checked = false;
    if (campaignDateFields) campaignDateFields.classList.remove("show");

    // Hide form wrapper if not editing
    if (formWrapper && !editMode) {
      formWrapper.style.display = "none";
      if (toggleFormBtn) {
        toggleFormBtn.innerHTML = '<i class="ri-add-line"></i><span>' + (L.rewards_add_new || 'Új jutalom') + '</span>';
      }
    }
  }

  /* ============================================================
   * 📅 CAMPAIGN CHECKBOX TOGGLE
   * ============================================================ */
  const campaignCheckbox = document.getElementById("reward-is-campaign");
  const campaignDateFieldsDiv = document.getElementById("campaign-date-fields");

  if (campaignCheckbox && campaignDateFieldsDiv) {
    campaignCheckbox.addEventListener("change", function() {
      if (this.checked) {
        campaignDateFieldsDiv.classList.add("show");
      } else {
        campaignDateFieldsDiv.classList.remove("show");
      }
    });
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
   * 📅 FORMAT DATE
   * ============================================================ */
  function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('hu-HU', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    });
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
    if (config.ably && config.ably.key && window.PPV_ABLY_MANAGER) {

      const manager = window.PPV_ABLY_MANAGER;

      // Initialize shared connection
      if (!manager.init(config.ably)) {
        startPolling();
        return;
      }

      // Listen for connection state changes
      manager.onStateChange((state) => {
        if (state === 'connected') {
          // Stop polling if running
          if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
          }
        } else if (state === 'disconnected' || state === 'failed') {
          startPolling();
        }
      });

      // 📡 Handle reward updates via shared manager
      manager.subscribe(config.ably.channel, 'reward-update', (message) => {
        showToast(`🎁 Prämie ${message.data.action === 'created' ? 'erstellt' : message.data.action === 'updated' ? 'aktualisiert' : 'gelöscht'}`, 'info');
        loadRewards();
      }, 'rewards-mgmt');

    } else {
      startPolling();
    }
  }

  function startPolling() {
    if (pollInterval) return; // Already polling
    pollInterval = setInterval(() => {
      if (listContainer) {
        loadRewards();
      }
    }, 30000);
  }

  /* ============================================================
   * 🚀 INIT
   * ============================================================ */
  loadRewards();
  initRealtime();
});