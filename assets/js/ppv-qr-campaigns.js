/**
 * PunktePass QR Scanner - Campaigns Module
 * Contains: CampaignManager class
 * Depends on: ppv-qr-core.js
 */
(function() {
  'use strict';

  if (window.PPV_QR_CAMPAIGNS_LOADED) return;
  window.PPV_QR_CAMPAIGNS_LOADED = true;

  const {
    log: ppvLog,
    L,
    getStoreKey,
    escapeHtml,
    statusBadge
  } = window.PPV_QR;

  // ============================================================
  // CAMPAIGN MANAGER
  // ============================================================
  class CampaignManager {
    constructor(uiManager) {
      this.ui = uiManager;
      this.campaigns = [];
      this.editingId = 0;
      this.modal = null;
      this.list = null;
    }

    init() {
      this.list = document.getElementById('ppv-campaign-list');
      this.modal = document.getElementById('ppv-campaign-modal');

      if (this.modal && this.modal.parentElement !== document.body) {
        document.body.appendChild(this.modal);
      }
    }

    async load() {
      if (!this.list) return;
      if (!getStoreKey()) {
        this.list.innerHTML = `<p style='text-align:center;color:#999;padding:20px;'>${L.camp_no_store || 'Kein Geschäft ausgewählt'}</p>`;
        return;
      }

      ppvLog('[QR] 📡 campaigns.load() called at', new Date().toLocaleTimeString());
      this.list.innerHTML = `<div class='ppv-loading'>⏳ ${L.camp_loading || 'Lade Kampagnen...'}</div>`;

      const filter = document.getElementById('ppv-campaign-filter')?.value || 'active';
      const filialeSelect = document.getElementById('ppv-campaign-filiale');
      const filialeId = filialeSelect?.value || 'all';

      try {
        let url = '/wp-json/punktepass/v1/pos/campaigns';
        if (filialeId && filialeId !== 'all') {
          url += `?filiale_id=${encodeURIComponent(filialeId)}`;
        } else {
          url += '?filiale_id=all';
        }

        const res = await fetch(url, {
          headers: { 'PPV-POS-Token': getStoreKey() }
        });
        ppvLog('[QR] 📡 campaigns.load() response:', res.status, 'filiale:', filialeId);
        const data = await res.json();

        this.list.innerHTML = '';

        if (!data || !data.length) {
          this.list.innerHTML = `<p>${L.camp_none || 'Keine Kampagnen'}</p>`;
          return;
        }

        this.campaigns = data;
        let filtered = data;
        if (filter === 'active') filtered = data.filter(c => c.status === 'active');
        if (filter === 'archived') filtered = data.filter(c => c.status === 'archived');

        filtered.forEach(c => this.renderCampaign(c));
      } catch (e) {
        this.list.innerHTML = `<p>⚠️ ${L.camp_load_error || 'Fehler beim Laden'}</p>`;
      }
    }

    renderCampaign(c) {
      let value = '';
      if (c.campaign_type === 'points') value = c.extra_points + ' pt';
      if (c.campaign_type === 'discount') value = c.discount_percent + '%';
      if (c.campaign_type === 'fixed') value = (c.min_purchase ?? c.fixed_amount ?? 0) + '€';

      const safeTitle = escapeHtml(c.title || '');
      const safeType = escapeHtml(c.campaign_type || '');
      const safeStoreName = c.store_name ? escapeHtml(c.store_name) : '';

      const card = document.createElement('div');
      card.className = 'ppv-campaign-item glass';
      card.innerHTML = `
        <div class="ppv-camp-header">
          <span class="ppv-camp-title">${safeTitle}</span>
          <div class="ppv-camp-actions">
            <span class="ppv-camp-clone" data-id="${c.id}">📄</span>
            <span class="ppv-camp-archive" data-id="${c.id}">📦</span>
            <span class="ppv-camp-edit" data-id="${c.id}">✏️</span>
            <span class="ppv-camp-delete" data-id="${c.id}">🗑️</span>
          </div>
        </div>
        ${safeStoreName ? `<p class="ppv-camp-store"><i class="ri-store-2-line"></i> ${safeStoreName}</p>` : ''}
        <p class="ppv-camp-dates">${(c.start_date || '').substring(0, 10)} – ${(c.end_date || '').substring(0, 10)}</p>
        <p class="ppv-camp-meta">⭐ ${safeType} | ${value} | ${statusBadge(c.state)}</p>
      `;
      this.list.appendChild(card);
    }

    edit(camp) {
      if (!camp) return;
      this.showModal();
      this.editingId = camp.id;

      const safe = id => document.getElementById(id);
      if (safe('camp-status')) safe('camp-status').value = camp.status || 'active';
      if (safe('camp-title')) safe('camp-title').value = camp.title;
      if (safe('camp-start')) safe('camp-start').value = (camp.start_date || '').substring(0, 10);
      if (safe('camp-end')) safe('camp-end').value = (camp.end_date || '').substring(0, 10);
      if (safe('camp-type')) safe('camp-type').value = camp.campaign_type;
      if (safe('camp-required-points')) safe('camp-required-points').value = camp.required_points || 0;
      if (safe('camp-points-given')) safe('camp-points-given').value = camp.points_given || 1;
      if (safe('camp-free-product-name')) safe('camp-free-product-name').value = camp.free_product || '';
      if (safe('camp-free-product-value')) safe('camp-free-product-value').value = camp.free_product_value || 0;

      const campValue = safe('camp-value');
      if (campValue) {
        if (camp.campaign_type === 'points') campValue.value = camp.extra_points || 0;
        else if (camp.campaign_type === 'discount') campValue.value = camp.discount_percent || 0;
        else if (camp.campaign_type === 'fixed') campValue.value = camp.min_purchase || camp.fixed_amount || 0;
        else campValue.value = 0;
      }

      this.updateVisibilityByType(camp.campaign_type);
      this.updateValueLabel(camp.campaign_type);
    }

    async save() {
      const safe = id => document.getElementById(id)?.value || '';
      const safeNum = id => Number(document.getElementById(id)?.value) || 0;

      const title = safe('camp-title');
      const start = safe('camp-start');
      const end = safe('camp-end');
      const realType = safe('camp-type');
      const value = safe('camp-value');
      const status = safe('camp-status');
      const requiredPoints = safeNum('camp-required-points');
      const pointsGiven = safeNum('camp-points-given');
      const freeProductName = safe('camp-free-product-name').trim();
      const freeProductValue = safeNum('camp-free-product-value');

      if (!title || !start || !end) {
        window.ppvToast('⚠️ ' + (L.camp_fill_title_date || 'Bitte Titel und Datum ausfüllen'), 'warning');
        return;
      }

      if (realType === 'free_product' && (!freeProductName || freeProductValue <= 0)) {
        window.ppvToast('⚠️ ' + (L.camp_fill_free_product_name_value || 'Bitte Produktname und Wert angeben'), 'warning');
        return;
      }

      const endpoint = this.editingId > 0
        ? '/wp-json/punktepass/v1/pos/campaign/update'
        : '/wp-json/punktepass/v1/pos/campaign';

      try {
        const res = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'PPV-POS-Token': getStoreKey() },
          body: JSON.stringify({
            id: this.editingId,
            store_key: getStoreKey(),
            title, start_date: start, end_date: end, campaign_type: realType,
            camp_value: value, required_points: requiredPoints,
            free_product: freeProductName, free_product_value: freeProductValue,
            points_given: pointsGiven, status
          })
        });

        const data = await res.json();

        if (data.success) {
          window.ppvToast(this.editingId > 0 ? (L.camp_updated || '✅ Aktualisiert!') : (L.camp_saved || '✅ Gespeichert!'), 'success');
          this.hideModal();
          this.resetForm();
          setTimeout(() => this.load(), 500);
        } else {
          window.ppvToast('❌ ' + (data.message || L.error_generic || 'Fehler'), 'error');
        }
      } catch (e) {
        window.ppvToast('⚠️ ' + (L.server_error || 'Serverfehler'), 'error');
      }
    }

    async delete(id) {
      if (!confirm(L.confirm_delete || 'Wirklich löschen?')) return;
      try {
        const res = await fetch('/wp-json/punktepass/v1/pos/campaign/delete', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'PPV-POS-Token': getStoreKey() },
          body: JSON.stringify({ id, store_key: getStoreKey() })
        });
        const data = await res.json();
        if (data.success) {
          window.ppvToast('🗑️ ' + (L.camp_deleted || 'Gelöscht'), 'success');
          setTimeout(() => this.load(), 500);
        }
      } catch (e) {
        window.ppvToast('⚠️ ' + (L.server_error || 'Serverfehler'), 'error');
      }
    }

    async archive(id) {
      try {
        const res = await fetch('/wp-json/punktepass/v1/pos/campaign/update', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'PPV-POS-Token': getStoreKey() },
          body: JSON.stringify({ id, store_key: getStoreKey(), status: 'archived' })
        });
        const data = await res.json();
        if (data.success) {
          window.ppvToast('📦 ' + (L.camp_archived || 'Archiviert'), 'success');
          setTimeout(() => this.load(), 500);
        }
      } catch (e) {
        window.ppvToast('⚠️ ' + (L.server_error || 'Serverfehler'), 'error');
      }
    }

    async clone(id) {
      const original = this.campaigns.find(c => c.id == id);
      if (!original) return;

      try {
        const res = await fetch('/wp-json/punktepass/v1/pos/campaign', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'PPV-POS-Token': getStoreKey() },
          body: JSON.stringify({
            store_key: getStoreKey(),
            title: original.title + ' (' + (L.copy || 'Kopie') + ')',
            start_date: original.start_date, end_date: original.end_date,
            campaign_type: original.campaign_type,
            camp_value: original.extra_points || original.discount_percent || original.min_purchase,
            required_points: original.required_points || 0,
            free_product: original.free_product || '',
            free_product_value: original.free_product_value || 0,
            points_given: original.points_given || 1
          })
        });
        const data = await res.json();
        if (data.success) {
          window.ppvToast('📄 ' + (L.camp_cloned || 'Dupliziert!'), 'success');
          setTimeout(() => this.load(), 500);
        }
      } catch (e) {
        window.ppvToast('⚠️ ' + (L.server_error || 'Serverfehler'), 'error');
      }
    }

    showModal() { if (this.modal) this.modal.classList.add('show'); }
    hideModal() { if (this.modal) this.modal.classList.remove('show'); }

    resetForm() {
      const safe = id => document.getElementById(id);
      ['camp-title', 'camp-start', 'camp-end', 'camp-free-product-name'].forEach(id => { if (safe(id)) safe(id).value = ''; });
      ['camp-value', 'camp-required-points', 'camp-free-product-value'].forEach(id => { if (safe(id)) safe(id).value = 0; });
      if (safe('camp-points-given')) safe('camp-points-given').value = 1;
      if (safe('camp-type')) safe('camp-type').value = 'points';
      if (safe('camp-status')) safe('camp-status').value = 'active';
      this.editingId = 0;
    }

    updateVisibilityByType(type) {
      const safe = id => document.getElementById(id);
      ['camp-required-points-wrapper', 'camp-points-given-wrapper', 'camp-free-product-name-wrapper', 'camp-free-product-value-wrapper'].forEach(id => {
        if (safe(id)) safe(id).style.display = 'none';
      });

      if (safe('camp-required-points-wrapper')) safe('camp-required-points-wrapper').style.display = 'block';

      if (type === 'discount' || type === 'fixed') {
        if (safe('camp-points-given-wrapper')) safe('camp-points-given-wrapper').style.display = 'block';
      } else if (type === 'free_product') {
        if (safe('camp-free-product-name-wrapper')) safe('camp-free-product-name-wrapper').style.display = 'block';
        if (safe('camp-free-product-value-wrapper')) safe('camp-free-product-value-wrapper').style.display = 'block';
        if (safe('camp-points-given-wrapper')) safe('camp-points-given-wrapper').style.display = 'block';
      }
    }

    updateValueLabel(type) {
      const label = document.getElementById('camp-value-label');
      const campValue = document.getElementById('camp-value');
      if (!label || !campValue) return;

      if (type === 'points') label.innerText = L.camp_extra_points || 'Extra Punkte';
      else if (type === 'discount') label.innerText = L.camp_discount || 'Rabatt (%)';
      else if (type === 'fixed') label.innerText = L.camp_fixed_bonus || 'Fix Bonus (€)';
      else if (type === 'free_product') {
        label.innerText = L.camp_free_product || 'Gratis Produkt';
        campValue.style.display = 'none';
        return;
      }
      campValue.style.display = 'block';
    }
  }

  // Export to global namespace
  window.PPV_QR.CampaignManager = CampaignManager;

  ppvLog('[QR-Campaigns] Module loaded');

})();
