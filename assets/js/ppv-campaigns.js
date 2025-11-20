/**
 * PunktePass – Kampagnenverwaltung (v4)
 * Entwickelt für: Händler Dashboard + POS
 * Funktionen: create / update / delete / toggle / duplicate
 * Design: Neon Dark UI + PWA Ready
 */

jQuery(document).ready(function ($) {
  const API = ppv_campaigns.ajaxurl;
  const nonce = ppv_campaigns.nonce;

  // 🔹 Neon Toast Status
  function showToast(msg, type = "info") {
    let box = $("#ppv-toast");
    if (!box.length) {
      box = $('<div id="ppv-toast"></div>').appendTo("body");
    }
    box
      .stop(true, true)
      .removeClass()
      .addClass(`show ${type}`)
      .html(msg)
      .fadeIn(200);

    setTimeout(() => box.fadeOut(400), 2500);
  }

  // 🔹 Flatpickr Calendar
  if (typeof flatpickr !== "undefined") {
    flatpickr(".ppv-datepicker", {
      dateFormat: "Y-m-d",
      altInput: true,
      altFormat: "d.m.Y",
      locale: "de",
      minDate: "today",
      disableMobile: false,
    });
  }

  // === Modal Open / Close ===
  $("#ppv-new-campaign-btn").on("click", function () {
    console.log("🔘 Campaign button clicked!");
    console.log("📦 Modal element:", $("#ppv-campaigns-admin-modal").length);
    $("#ppv-campaign-form")[0].reset();
    $("#campaign-id").val("");
    $("#ppv-modal-title").text("🧩 Neue Kampagne");
    $("#ppv-campaigns-admin-modal").fadeIn(200).css("display", "flex");
    console.log("✅ Modal should be visible now");
  });

  $(".ppv-close, #campaign-cancel").on("click", () =>
    $("#ppv-campaigns-admin-modal").fadeOut(200)
  );

  $(document).on("keydown", function (e) {
    if (e.key === "Escape") $("#ppv-campaigns-admin-modal").fadeOut(200);
  });

  // === Kampagne speichern (Neu / Update) ===
  $("#ppv-campaign-form").on("submit", async function (e) {
    e.preventDefault();

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
      showToast("⚠️ Bitte Titel und Datum angeben.", "warn");
      btn.prop("disabled", false).html("💾 Speichern");
      return;
    }

    try {
      const res = await $.post(API, data);
      btn.prop("disabled", false).html("💾 Speichern");

      if (res.success) {
        showToast("✅ " + res.data.msg, "success");
        setTimeout(() => location.reload(), 600);
      } else {
        showToast("❌ " + (res.data.msg || "Fehler beim Speichern"), "error");
      }
    } catch (err) {
      console.error(err);
      btn.prop("disabled", false).html("💾 Speichern");
      showToast("⚠️ Netzwerkfehler – bitte erneut versuchen.", "error");
    }
  });

  // === Kampagne bearbeiten ===
  $(".edit-campaign").on("click", function () {
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
  $(".delete-campaign").on("click", async function () {
    if (!confirm("🗑️ Wirklich löschen?")) return;

    try {
      const res = await $.post(API, {
        action: "ppv_delete_campaign",
        id: $(this).data("id"),
        nonce,
      });
      if (res.success) {
        showToast("🗑️ Kampagne gelöscht", "success");
        setTimeout(() => location.reload(), 600);
      }
    } catch {
      showToast("❌ Fehler beim Löschen", "error");
    }
  });

  // === Aktivieren / Deaktivieren ===
  $(".toggle-campaign").on("click", async function () {
    try {
      const res = await $.post(API, {
        action: "ppv_toggle_campaign",
        id: $(this).data("id"),
        nonce,
      });
      if (res.success) {
        showToast("🔁 Status geändert", "success");
        setTimeout(() => location.reload(), 500);
      }
    } catch {
      showToast("⚠️ Fehler beim Ändern des Status", "error");
    }
  });

  // === Duplizieren ===
  $(".duplicate-campaign").on("click", async function () {
    try {
      const res = await $.post(API, {
        action: "ppv_duplicate_campaign",
        id: $(this).data("id"),
        nonce,
      });
      if (res.success) {
        showToast("📄 Kampagne dupliziert", "success");
        setTimeout(() => location.reload(), 500);
      }
    } catch {
      showToast("⚠️ Fehler beim Duplizieren", "error");
    }
  });
});

jQuery(document).ready(function($){
  $(".ppv-tooltip").on("click touchstart", function(e){
    e.preventDefault();
    $(this).toggleClass("show");
  });
});


/* === Neon Toast Styles (globális CSS-be tehető, de ide is rakható ideiglenesen) === */
const style = document.createElement("style");
style.innerHTML = `
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
document.head.appendChild(style);
