/**
 * PunktePass – Bizonylatok Verwaltung (Receipts Management)
 * Version: 2.3 - Element ID fix
 * ✅ Működő download funkcionalitás
 * ✅ Helyes JSON kezelés
 * ✅ Auto-load on DOMContentLoaded + Turbo.js + Tab change
 * ✅ FIX: Uses ppv-receipts-container (correct HTML element ID)
 */

(function() {
  'use strict';

  // Script guard - prevent duplicate loading with Turbo.js
  if (window.PPV_RECEIPTS_LOADED) { return; }
  window.PPV_RECEIPTS_LOADED = true;

  // ✅ DEBUG mode - set to true for verbose logging
  const PPV_DEBUG = false;
  const ppvWarn = (...args) => { if (PPV_DEBUG) console.warn(...args); };

  ppvLog("✅ PunktePass Bizonylatok JS v2.1 geladen");

  /* ============================================================
   * 🔑 BASE + TOKEN + STORE - GLOBAL
   * ============================================================ */
  const base = window.ppv_receipts_rest?.base || "/wp-json/ppv/v1/";
  let storeID = 0;

  try {
    storeID =
      parseInt(window.PPV_STORE_ID) ||
      parseInt(sessionStorage.getItem("ppv_store_id")) ||
      1;
  } catch (_) { storeID = 1; }

  sessionStorage.setItem("ppv_store_id", String(storeID));

  let POS_TOKEN =
    (window.PPV_STORE_KEY || "").trim() ||
    (sessionStorage.getItem("ppv_store_key") || "").trim() ||
    "";

  if (window.PPV_STORE_KEY)
    sessionStorage.setItem("ppv_store_key", window.PPV_STORE_KEY);

  ppvLog(`📦 [RECEIPTS v2.0] Store ID: ${storeID}`);

  /* ============================================================
   * 🧩 TOAST HELPER
   * ============================================================ */
  function showToast(msg, type = "info") {
    const el = document.createElement("div");
    el.className = `ppv-toast ${type}`;
    el.textContent = msg;
    document.body.appendChild(el);
    requestAnimationFrame(() => el.classList.add("show"));
    setTimeout(() => {
      el.classList.remove("show");
      setTimeout(() => el.remove(), 350);
    }, 2600);
  }

  /* ============================================================
   * 🛡️ HTML ESCAPE
   * ============================================================ */
  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /* ============================================================
   * 📅 DATE FORMAT
   * ============================================================ */
  function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    const hours = String(d.getHours()).padStart(2, '0');
    const mins = String(d.getMinutes()).padStart(2, '0');
    return `${day}.${month}.${year} ${hours}:${mins}`;
  }

  /* ============================================================
   * 📋 LOAD RECEIPTS - MAIN FUNCTION
   * ============================================================ */
  window.ppv_receipts_load = async function() {
    ppvLog('📦 [RECEIPTS v2.3] ppv_receipts_load() called');

    // ✅ FIX: Correct element ID is ppv-receipts-container (not ppv-receipts-list)
    const receiptsList = document.getElementById("ppv-receipts-container") || document.getElementById("ppv-receipts-list");

    if (!receiptsList) {
      ppvLog('❌ [RECEIPTS v2.3] receiptsList element not found!');
      return;
    }

    const url = `${base}receipts/list?store_id=${storeID}`;
    ppvLog(`📡 [RECEIPTS v2.0] Loading from: ${url}`);

    receiptsList.innerHTML = '<div class="ppv-loading">📄 Bizonylatok betöltése...</div>';

    try {
      const res = await fetch(url, {
        headers: { "PPV-POS-Token": POS_TOKEN }
      });

      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }

      const json = await res.json();

      ppvLog(`📦 [RECEIPTS v2.0] Response success: ${json.success}, count: ${json.count}`);

      if (!json?.success || !json?.items?.length) {
        receiptsList.innerHTML = '<div class="ppv-receipts-empty" style="padding: 30px; text-align: center; background: #f5f5f5; border-radius: 8px; color: #666;">📭 Nincs elérhető bizonylat</div>';
        ppvLog('📦 [RECEIPTS v2.0] No receipts found');
        return;
      }

      receiptsList.innerHTML = '';

      ppvLog(`✅ [RECEIPTS v2.0] Loaded ${json.items.length} receipts`);

      json.items.forEach((receipt) => {
        const card = createReceiptCard(receipt);
        receiptsList.appendChild(card);
      });

      showToast(`✅ ${json.count} bizonylat betöltve`, 'success');

    } catch (err) {
      ppvLog('❌ [RECEIPTS v2.0] Load error:', err);
      receiptsList.innerHTML = '<div class="ppv-error" style="padding: 20px; background: #fee; border-radius: 8px; color: #c33;">❌ Hiba az adatok betöltésekor</div>';
      showToast('❌ Betöltési hiba', 'error');
    }
  };

  /* ============================================================
   * 🎨 CREATE RECEIPT CARD
   * ============================================================ */
  function createReceiptCard(receipt) {
    const card = document.createElement("div");
    card.className = 'ppv-receipt-card';
    card.style.cssText = `
      padding: 15px;
      margin-bottom: 12px;
      background: white;
      border: 1px solid #ddd;
      border-radius: 8px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 15px;
    `;

    const customer = escapeHtml(
      `${receipt.first_name || ''} ${receipt.last_name || ''}`.trim() || receipt.user_email || 'Ismeretlen'
    );
    const email = escapeHtml(receipt.user_email || '-');
    const reward = escapeHtml(receipt.reward_title || 'Bizonylat');
    const amount = parseFloat(receipt.actual_amount || receipt.points_spent || 0);
    const points = parseInt(receipt.points_spent || 0, 10);
    const date = formatDate(receipt.redeemed_at);

    const currency = receipt.country === 'RO' ? 'RON' : 'EUR';

    card.innerHTML = `
      <div style="flex: 1; min-width: 0;">
        <div style="display: flex; justify-content: space-between; align-items: start; gap: 15px;">
          <div>
            <strong style="font-size: 14px;">${customer}</strong><br>
            <small style="color: #666; font-size: 12px;">📧 ${email}</small><br>
            <small style="color: #666; font-size: 12px;">🎁 ${reward}</small>
          </div>
          <div style="text-align: right; white-space: nowrap;">
            <div style="font-size: 16px; font-weight: bold; color: #0066cc;">${amount.toFixed(2)} ${currency}</div>
            <small style="color: #666;">${points} pont</small><br>
            <small style="color: #999; font-size: 11px;">${date}</small>
          </div>
        </div>
      </div>
      <div>
        <button class="ppv-receipt-download-btn" data-id="${receipt.id}" style="
          padding: 10px 16px;
          background: #0066cc;
          color: white;
          border: none;
          border-radius: 6px;
          cursor: pointer;
          font-size: 12px;
          font-weight: 600;
          display: flex;
          align-items: center;
          gap: 6px;
          white-space: nowrap;
        ">
          📥 PDF
        </button>
      </div>
    `;

    const downloadBtn = card.querySelector('.ppv-receipt-download-btn');
    downloadBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const receiptId = this.getAttribute('data-id');
      ppvLog(`📥 [RECEIPTS v2.0] Download button clicked for receipt #${receiptId}`);
      downloadReceipt(receiptId);
    });

    return card;
  }

  /* ============================================================
   * 📥 DOWNLOAD RECEIPT - MŰKÖDŐ VERZIÓ
   * ============================================================ */
  function downloadReceipt(receiptId) {
    ppvLog(`📥 Download: #${receiptId}`);

    if (!receiptId) {
        showToast('❌ Bizonylat ID hiányzik', 'error');
        return;
    }

    const downloadUrl = `${base}receipts/download?id=${receiptId}&store_id=${storeID}`;

    // 👉 1) Nyissunk EGY ÚJ TABOT a PDF-nek
    window.open(downloadUrl, '_blank');

    // 👉 2) A JELENLEGI TABBAN azonnali refresh, 200ms után
    setTimeout(() => {
        window.location.href = "/rewards";
    }, 200);

    showToast('📄 Bizonylat letöltése...', "success");
}


  /* ============================================================
   * 🔍 FILTER RECEIPTS
   * ============================================================ */
  window.ppv_receipts_filter = async function() {
    // ✅ FIX: Correct element ID
    const receiptsList = document.getElementById("ppv-receipts-container") || document.getElementById("ppv-receipts-list");
    const searchInput = document.getElementById("ppv-receipt-search");
    const dateFromInput = document.getElementById("ppv-receipt-date-from");
    const dateToInput = document.getElementById("ppv-receipt-date-to");

    if (!receiptsList) return;

    const search = (searchInput?.value || '').trim();
    const dateFrom = (dateFromInput?.value || '').trim();
    const dateTo = (dateToInput?.value || '').trim();

    ppvLog(`🔍 [RECEIPTS v2.0] Filtering - search: "${search}", from: ${dateFrom}, to: ${dateTo}`);

    receiptsList.innerHTML = '<div class="ppv-loading">🔍 Szűrés...</div>';

    try {
      const url = new URL(`${base}receipts/filter`, window.location.origin);
      url.searchParams.append('store_id', storeID);
      
      if (search) url.searchParams.append('search', search);
      if (dateFrom) url.searchParams.append('date_from', dateFrom);
      if (dateTo) url.searchParams.append('date_to', dateTo);

      const res = await fetch(url.toString(), {
        headers: { "PPV-POS-Token": POS_TOKEN }
      });

      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }

      const json = await res.json();

      ppvLog(`🔍 [RECEIPTS v2.0] Filter result - success: ${json.success}, count: ${json.count}`);

      if (!json?.success || !json?.items?.length) {
        receiptsList.innerHTML = '<div class="ppv-receipts-empty" style="padding: 30px; text-align: center; background: #f5f5f5; border-radius: 8px; color: #666;">📭 Nem talált bizonylat</div>';
        showToast('⚠️ Nem talált eredményt', 'warning');
        return;
      }

      receiptsList.innerHTML = '';

      json.items.forEach((receipt) => {
        const card = createReceiptCard(receipt);
        receiptsList.appendChild(card);
      });

      showToast(`✅ ${json.count} bizonylat találva`, 'success');

    } catch (err) {
      ppvLog('❌ [RECEIPTS v2.0] Filter error:', err);
      receiptsList.innerHTML = '<div class="ppv-error" style="padding: 20px; background: #fee; border-radius: 8px; color: #c33;">❌ Hiba a szűrés során</div>';
      showToast('❌ Szűrési hiba', 'error');
    }
  };

  /* ============================================================
   * ⚡ INIT FUNCTION
   * ============================================================ */
  function initReceipts() {
    // ✅ FIX: Correct element ID is ppv-receipts-container (not ppv-receipts-list)
    const receiptsList = document.getElementById("ppv-receipts-container") || document.getElementById("ppv-receipts-list");

    if (!receiptsList) {
      ppvLog('📦 [RECEIPTS v2.3] No receipts container element on this page');
      return;
    }

    ppvLog('📦 [RECEIPTS v2.2] Initializing...');

    // Setup filter button
    const filterBtn = document.getElementById('ppv-receipt-filter-btn');
    const searchInput = document.getElementById('ppv-receipt-search');

    if (filterBtn && !filterBtn.hasAttribute('data-ppv-bound')) {
      filterBtn.setAttribute('data-ppv-bound', 'true');
      filterBtn.addEventListener('click', (e) => {
        e.preventDefault();
        window.ppv_receipts_filter();
      });
      ppvLog('✅ [RECEIPTS v2.2] Filter button listener attached');
    }

    if (searchInput && !searchInput.hasAttribute('data-ppv-bound')) {
      searchInput.setAttribute('data-ppv-bound', 'true');
      searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          window.ppv_receipts_filter();
        }
      });
      ppvLog('✅ [RECEIPTS v2.2] Search input listener attached');
    }

    // ✅ AUTO-LOAD RECEIPTS!
    window.ppv_receipts_load();
  }

  /* ============================================================
   * ⚡ EVENT LISTENERS
   * ============================================================ */

  // Initial load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => setTimeout(initReceipts, 100));
  } else {
    setTimeout(initReceipts, 100);
  }

  // Turbo.js support
  document.addEventListener('turbo:load', () => setTimeout(initReceipts, 100));

  // Tab change support - reinitialize when receipts/quittungen tab becomes visible
  window.addEventListener('ppv:tab-change', function(e) {
    if (e.detail?.tab === 'receipts' || e.detail?.tab === 'quittungen') {
      ppvLog('[RECEIPTS v2.2] Tab activated, loading...');
      setTimeout(initReceipts, 100);
    }
  });

  ppvLog("✅ [RECEIPTS v2.2] Ready!");

})(); // End IIFE