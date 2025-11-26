/**
 * PunktePass – Invoice Management Frontend
 * Version: 1.1 - IIFE + DEBUG mode
 * ✅ Single & Collective invoices
 * ✅ Modal workflow
 * ✅ Email sending
 */

(function() {
  'use strict';

  // Script guard - prevent duplicate loading with Turbo.js
  if (window.PPV_INVOICES_LOADED) { return; }
  window.PPV_INVOICES_LOADED = true;

  // ✅ DEBUG mode - set to true for verbose logging
  const PPV_DEBUG = false;
  const ppvLog = (...args) => { if (PPV_DEBUG) console.log(...args); };
  const ppvWarn = (...args) => { if (PPV_DEBUG) console.warn(...args); };

  ppvLog("✅ PPV Invoices JS v1.1 loaded");

  document.addEventListener("DOMContentLoaded", function () {

    const base = ppv_invoices?.rest_url || "/wp-json/ppv/v1/";

    // ============================================================
    // 🏪 FILIALE SUPPORT: Store ID Detection
    // ============================================================
    let storeID = 0;

    // ALWAYS prioritize window.PPV_STORE_ID over sessionStorage
    if (window.PPV_STORE_ID && Number(window.PPV_STORE_ID) > 0) {
      storeID = Number(window.PPV_STORE_ID);
      ppvLog(`✅ [INVOICES] Using window.PPV_STORE_ID: ${storeID}`);
      // Clear sessionStorage if it differs
      const cachedStoreId = sessionStorage.getItem("ppv_store_id");
      if (cachedStoreId && Number(cachedStoreId) !== storeID) {
        ppvLog(`🔄 [INVOICES] Store ID changed: ${cachedStoreId} -> ${storeID}`);
        sessionStorage.removeItem("ppv_store_id");
      }
    } else {
      storeID = Number(sessionStorage.getItem("ppv_store_id") || 0) || 1;
      ppvWarn(`⚠️ [INVOICES] window.PPV_STORE_ID not set, using sessionStorage: ${storeID}`);
    }

  if (storeID > 0) {
    sessionStorage.setItem("ppv_store_id", storeID);
  }

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
   * 📄 OPEN INVOICE MODAL (Single)
   * ============================================================ */
  window.openInvoiceModal = function(redeemId, amount, currency) {
    const modal = createInvoiceModal({
      type: 'single',
      redeemId: redeemId,
      amount: amount,
      currency: currency
    });
    document.body.appendChild(modal);
  };

  /* ============================================================
   * 📊 OPEN COLLECTIVE INVOICE MODAL
   * ============================================================ */
  window.openCollectiveModal = function() {
    const modal = createCollectiveModal();
    document.body.appendChild(modal);
  };

  /* ============================================================
   * 🎨 CREATE INVOICE MODAL (Single)
   * ============================================================ */
  function createInvoiceModal(data) {
    const modal = document.createElement('div');
    modal.className = 'ppv-modal ppv-invoice-modal';
    modal.style.display = 'flex';
    
    modal.innerHTML = `
      <div class="ppv-modal-inner" style="max-width: 500px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h3 style="margin: 0;">📄 Rechnung erstellen</h3>
          <button class="ppv-modal-close" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>

        <div style="margin-bottom: 20px; padding: 15px; background: rgba(0,230,255,0.1); border-radius: 8px; border: 1px solid rgba(0,230,255,0.3);">
          <strong>Einlösung #${data.redeemId}</strong><br>
          <span style="font-size: 18px; color: #00e6ff;">💰 ${data.amount} ${data.currency}</span>
        </div>

        <label style="display: block; margin-bottom: 8px; font-weight: 600;">MwSt.-Satz wählen:</label>
        <div class="ppv-vat-options" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
          <label style="display: flex; align-items: center; gap: 8px; padding: 12px; border: 2px solid var(--ppv-border); border-radius: 8px; cursor: pointer; transition: all 0.2s;">
            <input type="radio" name="vat_rate" value="19" checked style="width: 18px; height: 18px;">
            <span style="flex: 1;">19% (Normal)</span>
          </label>
          <label style="display: flex; align-items: center; gap: 8px; padding: 12px; border: 2px solid var(--ppv-border); border-radius: 8px; cursor: pointer; transition: all 0.2s;">
            <input type="radio" name="vat_rate" value="7" style="width: 18px; height: 18px;">
            <span style="flex: 1;">7% (Ermäßigt)</span>
          </label>
          <label style="display: flex; align-items: center; gap: 8px; padding: 12px; border: 2px solid var(--ppv-border); border-radius: 8px; cursor: pointer; transition: all 0.2s;">
            <input type="radio" name="vat_rate" value="0" style="width: 18px; height: 18px;">
            <span style="flex: 1;">0% (MwSt.-frei)</span>
          </label>
        </div>

        <div style="display: flex; gap: 12px;">
          <button class="ppv-btn ppv-btn-primary" id="ppv-create-invoice-btn" style="flex: 1;">
            📥 PDF erstellen
          </button>
          <button class="ppv-btn ppv-btn-outline ppv-modal-close">
            Abbrechen
          </button>
        </div>

        <div id="ppv-invoice-result" style="margin-top: 20px;"></div>
      </div>
    `;

    // Close handlers
    modal.querySelectorAll('.ppv-modal-close').forEach(btn => {
      btn.addEventListener('click', () => modal.remove());
    });

    // Radio hover effect
    modal.querySelectorAll('.ppv-vat-options label').forEach(label => {
      label.addEventListener('mouseenter', () => {
        label.style.borderColor = 'var(--ppv-primary)';
        label.style.background = 'rgba(99, 102, 241, 0.05)';
      });
      label.addEventListener('mouseleave', () => {
        if (!label.querySelector('input').checked) {
          label.style.borderColor = 'var(--ppv-border)';
          label.style.background = 'transparent';
        }
      });
      label.querySelector('input').addEventListener('change', () => {
        modal.querySelectorAll('.ppv-vat-options label').forEach(l => {
          l.style.borderColor = 'var(--ppv-border)';
          l.style.background = 'transparent';
        });
        if (label.querySelector('input').checked) {
          label.style.borderColor = 'var(--ppv-primary)';
          label.style.background = 'rgba(99, 102, 241, 0.1)';
        }
      });
    });

    // Create invoice
    modal.querySelector('#ppv-create-invoice-btn').addEventListener('click', async () => {
      const btn = modal.querySelector('#ppv-create-invoice-btn');
      const vatRate = parseFloat(modal.querySelector('input[name="vat_rate"]:checked').value);
      const resultBox = modal.querySelector('#ppv-invoice-result');

      btn.disabled = true;
      btn.textContent = '⏳ Erstelle...';

      try {
        const res = await fetch(`${base}invoices/create`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            store_id: storeID,
            redeem_id: data.redeemId,
            vat_rate: vatRate
          })
        });

        const json = await res.json();

        if (json.success) {
          resultBox.innerHTML = `
            <div style="padding: 15px; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); border-radius: 8px; margin-bottom: 15px;">
              <strong style="color: var(--ppv-success);">✅ Rechnung erstellt!</strong><br>
              <small>Nummer: ${json.invoice_number}</small>
            </div>
            <div style="display: flex; gap: 10px;">
              <a href="${json.pdf_url}" target="_blank" class="ppv-btn ppv-btn-primary" style="flex: 1; text-align: center; text-decoration: none;">
                📥 PDF herunterladen
              </a>
              <button class="ppv-send-email-btn ppv-btn" data-invoice-id="${json.invoice_id}" style="flex: 1;">
                ✉️ Per E-Mail
              </button>
            </div>
          `;

          // Email button handler
          resultBox.querySelector('.ppv-send-email-btn').addEventListener('click', () => {
            openEmailModal(json.invoice_id);
          });

          showToast('Rechnung erfolgreich erstellt!', 'success');
        } else {
          resultBox.innerHTML = `<p style="color: var(--ppv-danger);">❌ ${json.message}</p>`;
          showToast(json.message, 'error');
        }

      } catch (err) {
        ppvLog('[INVOICES] Error:', err);
        resultBox.innerHTML = `<p style="color: var(--ppv-danger);">❌ Serverfehler</p>`;
        showToast('Serverfehler', 'error');
      } finally {
        btn.disabled = false;
        btn.textContent = '📥 PDF erstellen';
      }
    });

    return modal;
  }

  /* ============================================================
   * 📊 CREATE COLLECTIVE MODAL
   * ============================================================ */
  function createCollectiveModal() {
    const today = new Date().toISOString().split('T')[0];
    const firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];

    const modal = document.createElement('div');
    modal.className = 'ppv-modal ppv-collective-modal';
    modal.style.display = 'flex';
    
    modal.innerHTML = `
      <div class="ppv-modal-inner" style="max-width: 600px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h3 style="margin: 0;">📊 Sammelrechnung erstellen</h3>
          <button class="ppv-modal-close" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>

        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Zeitraum:</label>
        <div style="display: flex; gap: 12px; margin-bottom: 20px;">
          <div style="flex: 1;">
            <label style="font-size: 13px; color: var(--ppv-text-secondary);">Von:</label>
            <input type="date" id="ppv-period-start" value="${firstDay}" 
                   style="width: 100%; padding: 10px; border: 1px solid var(--ppv-border); border-radius: 6px; margin-top: 4px;">
          </div>
          <div style="flex: 1;">
            <label style="font-size: 13px; color: var(--ppv-text-secondary);">Bis:</label>
            <input type="date" id="ppv-period-end" value="${today}" 
                   style="width: 100%; padding: 10px; border: 1px solid var(--ppv-border); border-radius: 6px; margin-top: 4px;">
          </div>
        </div>

        <button class="ppv-btn ppv-btn-secondary" id="ppv-preview-collective" style="width: 100%; margin-bottom: 20px;">
          🔍 Vorschau laden
        </button>

        <div id="ppv-collective-preview" style="margin-bottom: 20px;"></div>

        <div id="ppv-vat-section" style="display: none; margin-bottom: 20px;">
          <label style="display: block; margin-bottom: 8px; font-weight: 600;">MwSt.-Satz:</label>
          <div class="ppv-vat-options" style="display: flex; gap: 10px;">
            <label style="flex: 1; display: flex; align-items: center; gap: 8px; padding: 12px; border: 2px solid var(--ppv-border); border-radius: 8px; cursor: pointer;">
              <input type="radio" name="vat_collective" value="19" checked style="width: 18px; height: 18px;">
              19%
            </label>
            <label style="flex: 1; display: flex; align-items: center; gap: 8px; padding: 12px; border: 2px solid var(--ppv-border); border-radius: 8px; cursor: pointer;">
              <input type="radio" name="vat_collective" value="7" style="width: 18px; height: 18px;">
              7%
            </label>
            <label style="flex: 1; display: flex; align-items: center; gap: 8px; padding: 12px; border: 2px solid var(--ppv-border); border-radius: 8px; cursor: pointer;">
              <input type="radio" name="vat_collective" value="0" style="width: 18px; height: 18px;">
              0%
            </label>
          </div>
        </div>

        <div style="display: flex; gap: 12px;">
          <button class="ppv-btn ppv-btn-primary" id="ppv-create-collective-btn" style="flex: 1;" disabled>
            📊 Sammelrechnung erstellen
          </button>
          <button class="ppv-btn ppv-btn-outline ppv-modal-close">
            Abbrechen
          </button>
        </div>

        <div id="ppv-collective-result" style="margin-top: 20px;"></div>
      </div>
    `;

    // Close handlers
    modal.querySelectorAll('.ppv-modal-close').forEach(btn => {
      btn.addEventListener('click', () => modal.remove());
    });

    // Preview handler
    modal.querySelector('#ppv-preview-collective').addEventListener('click', async () => {
      const startDate = modal.querySelector('#ppv-period-start').value;
      const endDate = modal.querySelector('#ppv-period-end').value;
      const previewBox = modal.querySelector('#ppv-collective-preview');
      const vatSection = modal.querySelector('#ppv-vat-section');
      const createBtn = modal.querySelector('#ppv-create-collective-btn');

      if (!startDate || !endDate) {
        showToast('Bitte Zeitraum auswählen', 'warning');
        return;
      }

      previewBox.innerHTML = '<p>⏳ Lade...</p>';

      try {
        // Fetch redeems in period (we'll add this endpoint)
        const res = await fetch(`${base}redeem/list?store_id=${storeID}`);
        const json = await res.json();

        if (json.success && json.items) {
          const filtered = json.items.filter(item => {
            const date = new Date(item.redeemed_at || item.created_at).toISOString().split('T')[0];
            return date >= startDate && date <= endDate && item.status === 'approved';
          });

          if (filtered.length > 0) {
            const total = filtered.reduce((sum, item) => sum + parseFloat(item.points_spent || 0), 0);
            
            previewBox.innerHTML = `
              <div style="padding: 15px; background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3); border-radius: 8px;">
                <strong style="color: var(--ppv-info);">📊 ${filtered.length} Einlösungen gefunden</strong><br>
                <span style="font-size: 18px; font-weight: 600;">Gesamt: ${total.toFixed(2)} EUR</span>
              </div>
            `;
            
            vatSection.style.display = 'block';
            createBtn.disabled = false;
          } else {
            previewBox.innerHTML = `<p style="color: var(--ppv-text-tertiary);">ℹ️ Keine Einlösungen im Zeitraum</p>`;
            vatSection.style.display = 'none';
            createBtn.disabled = true;
          }
        }
      } catch (err) {
        ppvLog('[INVOICES] Preview error:', err);
        previewBox.innerHTML = `<p style="color: var(--ppv-danger);">❌ Fehler beim Laden</p>`;
      }
    });

    // Create collective invoice
    modal.querySelector('#ppv-create-collective-btn').addEventListener('click', async () => {
      const btn = modal.querySelector('#ppv-create-collective-btn');
      const startDate = modal.querySelector('#ppv-period-start').value;
      const endDate = modal.querySelector('#ppv-period-end').value;
      const vatRate = parseFloat(modal.querySelector('input[name="vat_collective"]:checked').value);
      const resultBox = modal.querySelector('#ppv-collective-result');

      btn.disabled = true;
      btn.textContent = '⏳ Erstelle...';

      try {
        const res = await fetch(`${base}invoices/collective`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            store_id: storeID,
            period_start: startDate,
            period_end: endDate,
            vat_rate: vatRate
          })
        });

        const json = await res.json();

        if (json.success) {
          resultBox.innerHTML = `
            <div style="padding: 15px; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); border-radius: 8px; margin-bottom: 15px;">
              <strong style="color: var(--ppv-success);">✅ Sammelrechnung erstellt!</strong><br>
              <small>Nummer: ${json.invoice_number} | ${json.count} Einlösungen</small>
            </div>
            <a href="${json.pdf_url}" target="_blank" class="ppv-btn ppv-btn-primary" style="width: 100%; text-align: center; text-decoration: none;">
              📥 PDF herunterladen
            </a>
          `;
          
          showToast('Sammelrechnung erstellt!', 'success');
        } else {
          resultBox.innerHTML = `<p style="color: var(--ppv-danger);">❌ ${json.message}</p>`;
          showToast(json.message, 'error');
        }
      } catch (err) {
        ppvLog('[INVOICES] Collective error:', err);
        resultBox.innerHTML = `<p style="color: var(--ppv-danger);">❌ Serverfehler</p>`;
      } finally {
        btn.disabled = false;
        btn.textContent = '📊 Sammelrechnung erstellen';
      }
    });

    return modal;
  }

  /* ============================================================
   * ✉️ OPEN EMAIL MODAL
   * ============================================================ */
  function openEmailModal(invoiceId) {
    const modal = document.createElement('div');
    modal.className = 'ppv-modal';
    modal.style.display = 'flex';
    
    modal.innerHTML = `
      <div class="ppv-modal-inner" style="max-width: 450px;">
        <h3>✉️ Per E-Mail senden</h3>
        <label style="display: block; margin-bottom: 8px;">E-Mail Adresse:</label>
        <input type="email" id="ppv-email-to" placeholder="buchh altung@example.com" 
               style="width: 100%; padding: 10px; border: 1px solid var(--ppv-border); border-radius: 6px; margin-bottom: 20px;">
        
        <div style="display: flex; gap: 12px;">
          <button class="ppv-btn ppv-btn-primary" id="ppv-send-email-submit" style="flex: 1;">
            ✉️ Senden
          </button>
          <button class="ppv-btn ppv-btn-outline ppv-modal-close">
            Abbrechen
          </button>
        </div>
        
        <div id="ppv-email-result" style="margin-top: 15px;"></div>
      </div>
    `;

    modal.querySelector('.ppv-modal-close').addEventListener('click', () => modal.remove());

    modal.querySelector('#ppv-send-email-submit').addEventListener('click', async () => {
      const btn = modal.querySelector('#ppv-send-email-submit');
      const emailTo = modal.querySelector('#ppv-email-to').value;
      const resultBox = modal.querySelector('#ppv-email-result');

      if (!emailTo || !emailTo.includes('@')) {
        showToast('Bitte gültige E-Mail eingeben', 'warning');
        return;
      }

      btn.disabled = true;
      btn.textContent = '⏳ Sende...';

      try {
        const res = await fetch(`${base}invoices/send-email`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            invoice_id: invoiceId,
            email_to: emailTo
          })
        });

        const json = await res.json();

        if (json.success) {
          resultBox.innerHTML = `<p style="color: var(--ppv-success);">✅ E-Mail erfolgreich gesendet!</p>`;
          showToast('E-Mail gesendet!', 'success');
          setTimeout(() => modal.remove(), 2000);
        } else {
          resultBox.innerHTML = `<p style="color: var(--ppv-danger);">❌ ${json.message}</p>`;
          showToast(json.message, 'error');
        }
      } catch (err) {
        ppvLog('[INVOICES] Email error:', err);
        resultBox.innerHTML = `<p style="color: var(--ppv-danger);">❌ Serverfehler</p>`;
      } finally {
        btn.disabled = false;
        btn.textContent = '✉️ Senden';
      }
    });

    document.body.appendChild(modal);
  }

  }); // End DOMContentLoaded

})(); // End IIFE