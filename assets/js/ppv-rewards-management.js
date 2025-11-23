/**
 * PunktePass – Prämien Management v2.0 CLEAN
 * Turbo.js compatible, clean architecture
 * Author: PunktePass / Erik Borota
 */

(function() {
  'use strict';

  // ============================================================
  // GLOBAL STATE
  // ============================================================
  const STATE = {
    initialized: false,
    editMode: false,
    editID: 0,
    elements: {}
  };

  const L = window.ppv_lang || {};

  // ============================================================
  // CONFIG HELPERS
  // ============================================================
  function getBaseUrl() {
    return window.ppv_rewards_mgmt?.base || '/wp-json/ppv/v1/';
  }

  function getStoreID() {
    return window.ppv_rewards_mgmt?.store_id ||
           window.PPV_STORE_ID ||
           parseInt(sessionStorage.getItem('ppv_store_id')) || 0;
  }

  // ============================================================
  // HELPERS
  // ============================================================
  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function showToast(msg, type = 'info') {
    const el = document.createElement('div');
    el.className = `ppv-toast ${type}`;
    el.textContent = msg;
    document.body.appendChild(el);
    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => {
      el.classList.remove('show');
      setTimeout(() => el.remove(), 350);
    }, 2600);
  }

  // ============================================================
  // TYPE INFO HELPERS
  // ============================================================
  function getTypeInfo(actionType) {
    switch(actionType) {
      case 'discount_percent':
        return { class: 'discount', icon: 'ri-percent-line', valueIcon: 'ri-percent-fill' };
      case 'discount_fixed':
        return { class: 'discount', icon: 'ri-money-euro-circle-line', valueIcon: 'ri-money-euro-circle-fill' };
      case 'free_product':
        return { class: 'free', icon: 'ri-gift-line', valueIcon: 'ri-gift-fill' };
      default:
        return { class: '', icon: 'ri-coupon-line', valueIcon: 'ri-coupon-fill' };
    }
  }

  function formatValue(r) {
    if (r.action_type === 'discount_percent') {
      return `${r.action_value || 0}%`;
    } else if (r.action_type === 'discount_fixed') {
      return `${r.action_value || 0} ${r.currency || 'EUR'}`;
    } else if (r.action_type === 'free_product') {
      return r.free_product || r.action_value || '🎁';
    }
    return r.action_value || '-';
  }

  // ============================================================
  // LOAD REWARDS
  // ============================================================
  async function loadRewards() {
    const listContainer = STATE.elements.listContainer;
    if (!listContainer) return;

    const storeID = getStoreID();
    const base = getBaseUrl();

    if (!storeID) {
      listContainer.innerHTML = `<p style='text-align:center;color:#ef4444;'>⚠️ ${L.rewards_error_no_store || 'Nincs Store ID!'}</p>`;
      return;
    }

    listContainer.innerHTML = `<div class='ppv-loading'>⏳ ${L.rewards_list_loading || 'Betöltés...'}</div>`;

    try {
      const res = await fetch(`${base}rewards/list?store_id=${storeID}`);
      const json = await res.json();

      if (!json?.success) {
        listContainer.innerHTML = `<p style='text-align:center;color:#999;'>ℹ️ ${json?.message || L.rewards_form_none || 'Nincsenek jutalmak.'}</p>`;
        return;
      }

      if (!json?.rewards || json.rewards.length === 0) {
        listContainer.innerHTML = `<p style='text-align:center;color:#999;'>ℹ️ ${L.rewards_form_none || 'Nincsenek jutalmak.'}</p>`;
        return;
      }

      listContainer.innerHTML = '';
      json.rewards.forEach(r => {
        const typeInfo = getTypeInfo(r.action_type);
        const card = document.createElement('div');
        card.className = 'ppv-reward-item glass-card';
        card.innerHTML = `
          <div class="reward-type-badge ${typeInfo.class}">
            <i class="${typeInfo.icon}"></i>
          </div>
          <div class="reward-content">
            <h4>${escapeHtml(r.title)}</h4>
            ${r.description ? `<p>${escapeHtml(r.description)}</p>` : ''}
            <div class="reward-stats">
              <span class="stat-badge points"><i class="ri-star-fill"></i> ${r.required_points} ${L.rewards_points_label || 'Punkte'}</span>
              <span class="stat-badge points-given"><i class="ri-add-circle-fill"></i> +${r.points_given || 0} ${L.rewards_points_given_label || 'vergeben'}</span>
              <span class="stat-badge value"><i class="${typeInfo.valueIcon}"></i> ${formatValue(r)}</span>
            </div>
          </div>
          <div class="reward-actions">
            <button class="btn-edit ppv-edit" data-id="${r.id}"><i class="ri-pencil-line"></i> ${L.rewards_btn_edit || 'Bearbeiten'}</button>
            <button class="btn-delete ppv-delete" data-id="${r.id}"><i class="ri-delete-bin-line"></i> ${L.rewards_btn_delete || 'Löschen'}</button>
          </div>
        `;
        listContainer.appendChild(card);
      });

    } catch (err) {
      console.error('[REWARDS-MGMT] Load error:', err);
      listContainer.innerHTML = `<p style='text-align:center;color:#ef4444;'>⚠️ ${L.rewards_error_loading || 'Hiba a betöltéskor'}: ${err.message}</p>`;
    }
  }

  // ============================================================
  // SAVE REWARD
  // ============================================================
  async function saveReward(e) {
    e.preventDefault();

    const form = STATE.elements.form;
    if (!form) return;

    const storeID = getStoreID();
    const base = getBaseUrl();

    const targetStoreSelect = document.getElementById('reward-target-store');
    const applyAllCheckbox = document.getElementById('reward-apply-all');

    const body = {
      store_id: storeID,
      title: form.title.value.trim(),
      required_points: parseInt(form.required_points.value),
      points_given: parseInt(document.getElementById('reward-points-given')?.value || 0),
      description: form.description.value.trim(),
      action_type: form.action_type.value,
      action_value: form.action_value.value.trim(),
      free_product: document.getElementById('reward-free-product-name')?.value.trim() || '',
      free_product_value: parseFloat(document.getElementById('reward-free-product-value')?.value || 0),
      target_store_id: targetStoreSelect?.value || 'current',
      apply_to_all: applyAllCheckbox?.checked || false
    };

    const endpoint = STATE.editMode ? 'rewards/update' : 'rewards/save';
    if (STATE.editMode) body.id = STATE.editID;

    try {
      const res = await fetch(`${base}${endpoint}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });

      const json = await res.json();
      showToast(json.message || (L.rewards_saved || '✅ Mentve.'), 'success');
      resetForm();
      loadRewards();

    } catch (err) {
      console.error('[REWARDS-MGMT] Save error:', err);
      showToast(`⚠️ ${L.rewards_error_save || 'Mentési hiba'}: ${err.message}`, 'error');
    }
  }

  // ============================================================
  // EDIT REWARD
  // ============================================================
  async function editReward(id) {
    const storeID = getStoreID();
    const base = getBaseUrl();

    try {
      const res = await fetch(`${base}rewards/list?store_id=${storeID}`);
      const json = await res.json();
      const reward = json.rewards?.find(r => r.id == id);

      if (!reward) {
        console.error('[REWARDS-MGMT] Reward not found:', id);
        return;
      }

      STATE.editMode = true;
      STATE.editID = reward.id;

      document.getElementById('reward-title').value = reward.title;
      document.getElementById('reward-points').value = reward.required_points;
      document.getElementById('reward-points-given').value = reward.points_given || 0;
      document.getElementById('reward-description').value = reward.description || '';
      document.getElementById('reward-type').value = reward.action_type || 'discount_percent';
      document.getElementById('reward-value').value = reward.action_value || '';

      const freeProductNameInput = document.getElementById('reward-free-product-name');
      const freeProductValueInput = document.getElementById('reward-free-product-value');
      if (freeProductNameInput) freeProductNameInput.value = reward.free_product || '';
      if (freeProductValueInput) freeProductValueInput.value = reward.free_product_value || 0;

      toggleRewardFields();

      if (STATE.elements.saveBtn) STATE.elements.saveBtn.textContent = '💾 ' + (L.rewards_btn_update || 'Frissítés');
      if (STATE.elements.cancelBtn) STATE.elements.cancelBtn.style.display = 'block';

      STATE.elements.form?.scrollIntoView({ behavior: 'smooth' });

    } catch (err) {
      console.error('[REWARDS-MGMT] Edit error:', err);
      showToast(`⚠️ ${L.rewards_error_loading || 'Hiba a betöltéskor'}: ${err.message}`, 'error');
    }
  }

  // ============================================================
  // DELETE REWARD
  // ============================================================
  async function deleteReward(id) {
    if (!confirm(L.rewards_confirm_delete || 'Biztosan törlöd a jutalmat?')) return;

    const storeID = getStoreID();
    const base = getBaseUrl();

    try {
      const res = await fetch(`${base}rewards/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ store_id: storeID, id })
      });

      const json = await res.json();
      showToast(json.message || (L.rewards_deleted || '🗑️ Törölve.'), 'success');
      loadRewards();

    } catch (err) {
      console.error('[REWARDS-MGMT] Delete error:', err);
      showToast(`⚠️ ${L.rewards_error_delete || 'Törlési hiba'}: ${err.message}`, 'error');
    }
  }

  // ============================================================
  // RESET FORM
  // ============================================================
  function resetForm() {
    STATE.editMode = false;
    STATE.editID = 0;

    if (STATE.elements.form) STATE.elements.form.reset();
    if (STATE.elements.saveBtn) STATE.elements.saveBtn.textContent = '💾 ' + (L.rewards_form_save || 'Mentés');
    if (STATE.elements.cancelBtn) STATE.elements.cancelBtn.style.display = 'none';

    const targetStoreSelect = document.getElementById('reward-target-store');
    const applyAllCheckbox = document.getElementById('reward-apply-all');
    if (targetStoreSelect) targetStoreSelect.value = 'current';
    if (applyAllCheckbox) applyAllCheckbox.checked = false;
  }

  // ============================================================
  // TOGGLE REWARD FIELDS (based on type)
  // ============================================================
  function toggleRewardFields() {
    const rewardTypeSelect = document.getElementById('reward-type');
    const rewardValueInput = document.getElementById('reward-value');
    const rewardValueLabel = rewardValueInput?.previousElementSibling;
    const rewardValueHelper = rewardValueInput?.nextElementSibling;
    const freeProductNameWrapper = document.getElementById('reward-free-product-name-wrapper');
    const freeProductValueWrapper = document.getElementById('reward-free-product-value-wrapper');

    if (!rewardTypeSelect) return;

    const selectedType = rewardTypeSelect.value;

    if (selectedType === 'free_product') {
      if (rewardValueInput) rewardValueInput.style.display = 'none';
      if (rewardValueLabel) rewardValueLabel.style.display = 'none';
      if (rewardValueHelper) rewardValueHelper.style.display = 'none';
      if (rewardValueInput) rewardValueInput.value = '0';
      if (rewardValueInput) rewardValueInput.removeAttribute('required');

      if (freeProductNameWrapper) freeProductNameWrapper.style.display = 'block';
      if (freeProductValueWrapper) freeProductValueWrapper.style.display = 'block';
    } else {
      if (rewardValueInput) rewardValueInput.style.display = 'block';
      if (rewardValueLabel) rewardValueLabel.style.display = 'block';
      if (rewardValueHelper) rewardValueHelper.style.display = 'block';
      if (rewardValueInput) rewardValueInput.setAttribute('required', 'required');

      if (freeProductNameWrapper) freeProductNameWrapper.style.display = 'none';
      if (freeProductValueWrapper) freeProductValueWrapper.style.display = 'none';
    }
  }

  // ============================================================
  // EVENT DELEGATION
  // ============================================================
  function setupEventDelegation() {
    document.body.removeEventListener('click', handleBodyClick);
    document.body.addEventListener('click', handleBodyClick);
  }

  function handleBodyClick(e) {
    const target = e.target;

    // Edit button
    if (target.classList.contains('ppv-edit') || target.closest('.ppv-edit')) {
      e.preventDefault();
      const btn = target.classList.contains('ppv-edit') ? target : target.closest('.ppv-edit');
      const id = btn.dataset.id;
      if (id) editReward(id);
    }

    // Delete button
    if (target.classList.contains('ppv-delete') || target.closest('.ppv-delete')) {
      e.preventDefault();
      const btn = target.classList.contains('ppv-delete') ? target : target.closest('.ppv-delete');
      const id = btn.dataset.id;
      if (id) deleteReward(id);
    }
  }

  // ============================================================
  // CLEANUP
  // ============================================================
  function cleanup() {
    STATE.initialized = false;
    STATE.editMode = false;
    STATE.editID = 0;
    STATE.elements = {};
  }

  // ============================================================
  // INITIALIZATION
  // ============================================================
  function init() {
    const listContainer = document.getElementById('ppv-rewards-list');
    const form = document.getElementById('ppv-reward-form');

    // Only init if we have reward management elements
    if (!listContainer && !form) {
      cleanup();
      return;
    }

    console.log('[REWARDS-MGMT] Initializing...');
    cleanup();

    // Cache elements
    STATE.elements.listContainer = listContainer;
    STATE.elements.form = form;
    STATE.elements.saveBtn = document.getElementById('save-btn');
    STATE.elements.cancelBtn = document.getElementById('cancel-btn');

    // Setup event delegation
    setupEventDelegation();

    // Form submit handler
    if (form) {
      form.removeEventListener('submit', saveReward);
      form.addEventListener('submit', saveReward);
    }

    // Cancel button handler
    if (STATE.elements.cancelBtn) {
      STATE.elements.cancelBtn.removeEventListener('click', resetForm);
      STATE.elements.cancelBtn.addEventListener('click', resetForm);
    }

    // Type select handler
    const rewardTypeSelect = document.getElementById('reward-type');
    if (rewardTypeSelect) {
      rewardTypeSelect.removeEventListener('change', toggleRewardFields);
      rewardTypeSelect.addEventListener('change', toggleRewardFields);
      toggleRewardFields();
    }

    // Load data
    loadRewards();

    STATE.initialized = true;
    console.log('[REWARDS-MGMT] Initialization complete');
  }

  // ============================================================
  // EVENT LISTENERS
  // ============================================================

  // Initial load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Turbo.js support
  document.addEventListener('turbo:load', init);
  document.addEventListener('turbo:before-visit', cleanup);

  // Custom SPA event support
  window.addEventListener('ppv:spa-navigate', init);

  console.log('[REWARDS-MGMT] Script loaded v2.0');

})();
