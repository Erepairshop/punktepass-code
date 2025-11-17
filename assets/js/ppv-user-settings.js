/**
 * PunktePass – User Settings v5.0
 * Avatar Upload • Modal System • Notifications • Privacy • Address
 * Author: Erik Borota / PunktePass
 */

jQuery(document).ready(function ($) {
  console.log("✅ PunktePass User Settings JS v5.0 aktiv");

  /** =============================
   * 🌍 TRANSLATIONS (DE/HU/RO)
   * ============================= */
  const LANG = (window.ppv_user_settings && window.ppv_user_settings.lang) || 'de';

  const T = {
    de: {
      avatar_updated: "✅ Avatar aktualisiert",
      upload_failed: "⚠️ Upload fehlgeschlagen",
      network_error: "❌ Netzwerkfehler",
      settings_saved: "✅ Einstellungen gespeichert",
      save_error: "Fehler beim Speichern",
      logout_all_confirm: "Möchten Sie sich wirklich auf allen Geräten abmelden?",
      password_required: "⚠️ Bitte Passwort eingeben",
      delete_final_warning: "⚠️ LETZTE WARNUNG: Konto wirklich unwiderruflich löschen?",
    },
    hu: {
      avatar_updated: "✅ Avatar frissítve",
      upload_failed: "⚠️ Feltöltés sikertelen",
      network_error: "❌ Hálózati hiba",
      settings_saved: "✅ Beállítások mentve",
      save_error: "Mentési hiba",
      logout_all_confirm: "Biztosan kijelentkezel minden eszközön?",
      password_required: "⚠️ Kérlek add meg a jelszót",
      delete_final_warning: "⚠️ UTOLSÓ FIGYELMEZTETÉS: Biztosan véglegesen törlöd a fiókot?",
    },
    ro: {
      avatar_updated: "✅ Avatar actualizat",
      upload_failed: "⚠️ Încărcare eșuată",
      network_error: "❌ Eroare de rețea",
      settings_saved: "✅ Setări salvate",
      save_error: "Eroare la salvare",
      logout_all_confirm: "Sigur vrei să te deconectezi de pe toate dispozitivele?",
      password_required: "⚠️ Te rog introdu parola",
      delete_final_warning: "⚠️ ULTIMĂ ATENȚIONARE: Sigur ștergi definitiv contul?",
    }
  }[LANG] || T.de;

  /** =============================
   * 🧩 TOAST RENDSZER
   * ============================= */
  const showToast = (msg, type = "info") => {
    $(".ppv-toast").remove();
    const toast = $(`
      <div class="ppv-toast ${type}">
        <div class="ppv-toast-inner">${msg}</div>
      </div>
    `);
    $("body").append(toast);
    setTimeout(() => toast.addClass("show"), 50);
    setTimeout(() => {
      toast.removeClass("show");
      setTimeout(() => toast.remove(), 400);
    }, 3500);
  };

  /** =============================
   * 📸 AVATAR FELTÖLTÉS
   * ============================= */
  $("#ppv-avatar-upload").on("change", function () {
    const file = this.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append("action", "ppv_upload_avatar");
    formData.append("avatar", file);
    formData.append("nonce", ppv_user_settings.nonce);

    $.ajax({
      url: ppv_user_settings.ajax_url,
      type: "POST",
      processData: false,
      contentType: false,
      data: formData,
      success: (res) => {
        if (res.success && res.data.url) {
          $("#ppv-avatar-preview").attr("src", res.data.url);
          showToast(T.avatar_updated, "success");
        } else {
          showToast(T.upload_failed, "error");
        }
      },
      error: () => showToast(T.network_error, "error"),
    });
  });

  /** =============================
   * 💾 BEÁLLÍTÁSOK MENTÉSE
   * ============================= */
  $("#ppv-settings-form").on("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    // Checkbox értékek kezelése
    formData.set('email_notifications', $('input[name="email_notifications"]').is(':checked'));
    formData.set('push_notifications', $('input[name="push_notifications"]').is(':checked'));
    formData.set('promo_notifications', $('input[name="promo_notifications"]').is(':checked'));
    formData.set('profile_visible', $('input[name="profile_visible"]').is(':checked'));
    formData.set('marketing_emails', $('input[name="marketing_emails"]').is(':checked'));
    formData.set('data_sharing', $('input[name="data_sharing"]').is(':checked'));

    formData.append('action', 'ppv_save_user_settings');
    formData.append('nonce', ppv_user_settings.nonce);

    $.ajax({
      url: ppv_user_settings.ajax_url,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: (res) => {
        if (res.success) {
          showToast(T.settings_saved, "success");
        } else {
          showToast("⚠️ " + (res.data?.msg || T.save_error), "error");
        }
      },
      error: () => showToast(T.network_error, "error"),
    });
  });

  /** =============================
   * 📱 ÖSSZES ESZKÖZ KIJELENTKEZTETÉSE
   * ============================= */
  $("#ppv-logout-all").on("click", function () {
    if (confirm(T.logout_all_confirm)) {
      $.ajax({
        url: ppv_user_settings.ajax_url,
        type: "POST",
        data: {
          action: 'ppv_logout_all_devices',
          nonce: ppv_user_settings.nonce
        },
        success: (res) => {
          if (res.success) {
            showToast("✅ " + res.data.msg, "success");
          } else {
            showToast("⚠️ " + (res.data?.msg || "Fehler"), "error");
          }
        },
        error: () => showToast(T.network_error, "error")
      });
    }
  });

  /** =============================
   * 🗑️ FIÓK TÖRLÉS MODAL
   * ============================= */
  const $modal = $("#ppv-delete-modal");

  // Modal megnyitása
  $("#ppv-delete-account-btn").on("click", function () {
    $modal.fadeIn(300);
    $("#ppv-delete-password").val('');
  });

  // Modal bezárása
  $(".ppv-modal-close, #ppv-cancel-delete").on("click", function () {
    $modal.fadeOut(300);
  });

  // Modal bezárása kattintással
  $(window).on("click", function (e) {
    if (e.target.id === "ppv-delete-modal") {
      $modal.fadeOut(300);
    }
  });

  // Törlés megerősítése
  $("#ppv-confirm-delete").on("click", function () {
    const password = $("#ppv-delete-password").val();

    if (!password) {
      showToast(T.password_required, "error");
      return;
    }

    if (!confirm(T.delete_final_warning)) {
      return;
    }

    $.ajax({
      url: ppv_user_settings.ajax_url,
      type: "POST",
      data: {
        action: 'ppv_delete_account',
        password: password,
        nonce: ppv_user_settings.nonce
      },
      success: (res) => {
        if (res.success) {
          showToast("✅ " + res.data.msg, "success");
          setTimeout(() => {
            window.location.href = res.data.redirect;
          }, 2000);
        } else {
          showToast("⚠️ " + (res.data?.msg || T.save_error), "error");
        }
      },
      error: () => showToast(T.network_error, "error")
    });
  });
});
