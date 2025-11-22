/**
 * PunktePass – Kampagnenverwaltung (v5)
 * Entwickelt für: Händler Dashboard + POS
 * Funktionen: create / update / delete / toggle / duplicate
 * Design: Neon Dark UI + PWA Ready
 * ✅ TURBO COMPATIBLE
 */

// ✅ Duplicate load prevention
if (window.PPV_CAMPAIGNS_LOADED) {
  console.warn('⚠️ PPV Campaigns JS already loaded - skipping duplicate!');
} else {
  window.PPV_CAMPAIGNS_LOADED = true;

console.log("✅ ppv-campaigns.js v5 loaded!");

// 🔹 Neon Toast Status (global function)
function showCampaignToast(msg, type = "info") {
  let box = jQuery("#ppv-toast");
  if (!box.length) {
    box = jQuery('<div id="ppv-toast"></div>').appendTo("body");
  }
  box
    .stop(true, true)
    .removeClass()
    .addClass(`show ${type}`)
    .html(msg)
    .fadeIn(200);

  setTimeout(() => box.fadeOut(400), 2500);
}

function initCampaigns() {
  console.log("🔄 [CAMPAIGNS] Initializing...");

  const $ = jQuery;

  // Check if we're on the campaigns page
  if (!$("#ppv-campaigns-admin-modal").length && !$(".ppv-campaign-card").length) {
    console.log("ℹ️ [CAMPAIGNS] Not on campaigns page - skipping init");
    return;
  }

  console.log("🔧 API:", typeof ppv_campaigns !== 'undefined' ? ppv_campaigns : 'not defined');

  const API = typeof ppv_campaigns !== 'undefined' ? ppv_campaigns.ajaxurl : '';
  const nonce = typeof ppv_campaigns !== 'undefined' ? ppv_campaigns.nonce : '';

  // 🔹 Flatpickr Calendar - reinitialize for new DOM
  if (typeof flatpickr !== "undefined") {
    // Destroy existing instances first
    document.querySelectorAll(".ppv-datepicker").forEach(el => {
      if (el._flatpickr) {
        el._flatpickr.destroy();
      }
    });

    flatpickr(".ppv-datepicker", {
      dateFormat: "Y-m-d",
      altInput: true,
      altFormat: "d.m.Y",
      locale: "de",
      minDate: "today",
      disableMobile: false,
    });
  }

  // === Modal Open / Close === (use event delegation)
  // Note: Form submit and modal handlers use event delegation below

  console.log("✅ [CAMPAIGNS] Initialization complete");
}

// === EVENT DELEGATION - Runs once, works after Turbo ===
jQuery(document).ready(function($) {

  const API = typeof ppv_campaigns !== 'undefined' ? ppv_campaigns.ajaxurl : '';
  const nonce = typeof ppv_campaigns !== 'undefined' ? ppv_campaigns.nonce : '';

  // === Modal Open ===
  $(document).on("click", "#ppv-new-campaign-btn", function () {
    console.log("🔘 Campaign button clicked!");
    const form = $("#ppv-campaign-form");
    if (form.length && form[0]) form[0].reset();
    $("#campaign-id").val("");
    $("#ppv-modal-title").text("🧩 Neue Kampagne");
    $("#ppv-campaigns-admin-modal").fadeIn(200).css("display", "flex");
    console.log("✅ Modal should be visible now");
  });

  // === Modal Close ===
  $(document).on("click", ".ppv-close, #campaign-cancel", function() {
    $("#ppv-campaigns-admin-modal").fadeOut(200);
  });

  $(document).on("keydown", function (e) {
    if (e.key === "Escape") $("#ppv-campaigns-admin-modal").fadeOut(200);
  });

  // === Kampagne speichern (Neu / Update) ===
  $(document).on("submit", "#ppv-campaign-form", async function (e) {
    e.preventDefault();

    const API = typeof ppv_campaigns !== 'undefined' ? ppv_campaigns.ajaxurl : '';
    const nonce = typeof ppv_campaigns !== 'undefined' ? ppv_campaigns.nonce : '';

    const id = $("#campaign-id").val();
    const action = id ? "ppv_update_campaign" : "ppv_save_campaign";
    const btn = $(this).find("button[type='submit']");
    btn.prop("disabled", true).html("💾 Speichern...");

    const data = {
      action,
      nonce,
      id,
      title: $("#campaign-title").val().trim(),
      description: $("#campaign-description").val().trim(),
      multiplier: $("#campaign-multiplier").val(),
      extra_points: $("#campaign-extra").val(),
      daily_limit: $("#campaign-daily-limit").val(),
      discount_percent: $("#campaign-discount").val(),
      campaign_type: $("#campaign-type").val(),
      start: $("#campaign-start").val(),
      end: $("#campaign-end").val(),
    };

    if (!data.title || !data.start || !data.end) {
      showCampaignToast("⚠️ Bitte Titel und Datum angeben.", "warn");
      btn.prop("disabled", false).html("💾 Speichern");
      return;
    }

    try {
      const res = await $.post(API, data);
      btn.prop("disabled", false).html("💾 Speichern");

      if (res.success) {
        showCampaignToast("✅ " + res.data.msg, "success");
        setTimeout(() => location.reload(), 600);
      } else {
        showCampaignToast("❌ " + (res.data.msg || "Fehler beim Speichern"), "error");
      }
    } catch (err) {
      console.error(err);
      btn.prop("disabled", false).html("💾 Speichern");
      showCampaignToast("⚠️ Netzwerkfehler – bitte erneut versuchen.", "error");
    }
  });

  // === Kampagne bearbeiten ===
  $(document).on("click", ".edit-campaign", function () {
    const card = $(this).closest(".ppv-campaign-card");
    const id = $(this).data("id");

    $("#ppv-modal-title").text("✏️ Kampagne bearbeiten");
    $("#campaign-id").val(id);
    $("#campaign-title").val(card.find("h4").text());
    $("#campaign-description").val(card.find("p").first().text());
    $("#campaign-multiplier").val(card.data("multiplier") || 1);
    $("#campaign-extra").val(card.data("extra") || 0);
    $("#campaign-daily-limit").val(card.data("limit") || 0);
    $("#campaign-discount").val(card.data("discount") || 0);
    $("#campaign-type").val(card.data("type") || "points");

    $("#ppv-campaigns-admin-modal").fadeIn(200).css("display", "flex");
  });

  // === Kampagne löschen ===
  $(document).on("click", ".delete-campaign", async function () {
    if (!confirm("🗑️ Wirklich löschen?")) return;

    const API = typeof ppv_campaigns !== 'undefined' ? ppv_campaigns.ajaxurl : '';
    const nonce = typeof ppv_campaigns !== 'undefined' ? ppv_campaigns.nonce : '';

    try {
      const res = await $.post(API, {
        action: "ppv_delete_campaign",
        id: $(this).data("id"),
        nonce,
      });
      if (res.success) {
        showCampaignToast("🗑️ Kampagne gelöscht", "success");
        setTimeout(() => location.reload(), 600);
      }
    } catch {
      showCampaignToast("❌ Fehler beim Löschen", "error");
    }
  });

  // === Aktivieren / Deaktivieren ===
  $(document).on("click", ".toggle-campaign", async function () {
    const API = typeof ppv_campaigns !== 'undefined' ? ppv_campaigns.ajaxurl : '';
    const nonce = typeof ppv_campaigns !== 'undefined' ? ppv_campaigns.nonce : '';

    try {
      const res = await $.post(API, {
        action: "ppv_toggle_campaign",
        id: $(this).data("id"),
        nonce,
      });
      if (res.success) {
        showCampaignToast("🔁 Status geändert", "success");
        setTimeout(() => location.reload(), 500);
      }
    } catch {
      showCampaignToast("⚠️ Fehler beim Ändern des Status", "error");
    }
  });

  // === Duplizieren ===
  $(document).on("click", ".duplicate-campaign", async function () {
    const API = typeof ppv_campaigns !== 'undefined' ? ppv_campaigns.ajaxurl : '';
    const nonce = typeof ppv_campaigns !== 'undefined' ? ppv_campaigns.nonce : '';

    try {
      const res = await $.post(API, {
        action: "ppv_duplicate_campaign",
        id: $(this).data("id"),
        nonce,
      });
      if (res.success) {
        showCampaignToast("📄 Kampagne dupliziert", "success");
        setTimeout(() => location.reload(), 500);
      }
    } catch {
      showCampaignToast("⚠️ Fehler beim Duplizieren", "error");
    }
  });

  // === Tooltip ===
  $(document).on("click touchstart", ".ppv-tooltip", function(e){
    e.preventDefault();
    $(this).toggleClass("show");
  });
});

// 🚀 Export reinit function for Turbo
window.ppv_campaigns_reinit = initCampaigns;

// Initial load
document.addEventListener("DOMContentLoaded", initCampaigns);

// 🔄 Turbo: Re-initialize after navigation
document.addEventListener('turbo:load', function() {
  console.log('🔄 [CAMPAIGNS] turbo:load event');
  setTimeout(initCampaigns, 100);
});

document.addEventListener('turbo:render', function() {
  console.log('🔄 [CAMPAIGNS] turbo:render event');
  setTimeout(initCampaigns, 100);
});

} // End of duplicate load prevention


/* === Neon Toast Styles (globális CSS-be tehető, de ide is rakható ideiglenesen) === */
// ✅ FIX: Only create style element once (avoid duplicate declaration error)
if (!document.getElementById('ppv-toast-styles')) {
  const toastStyle = document.createElement("style");
  toastStyle.id = 'ppv-toast-styles';
  toastStyle.innerHTML = `
#ppv-toast {
  position: fixed;
  bottom: 20px;
  right: 25px;
  background: rgba(0, 255, 255, 0.1);
  border: 1px solid rgba(0,255,255,0.3);
  color: #00ffff;
  padding: 10px 15px;
  border-radius: 12px;
  backdrop-filter: blur(10px);
  box-shadow: 0 0 15px rgba(0,255,255,0.3);
  font-family: 'Inter', sans-serif;
  font-size: 0.9rem;
  display: none;
  z-index: 9999;
}
#ppv-toast.success { border-color: #00ff9d; color: #00ff9d; }
#ppv-toast.error { border-color: #ff4b4b; color: #ff4b4b; }
#ppv-toast.warn { border-color: #ffaa00; color: #ffaa00; }
`;
  document.head.appendChild(toastStyle);
}
