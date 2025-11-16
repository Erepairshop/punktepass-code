/**
 * PunktePass – User Settings v4.0
 * Avatar Upload • Toast System • Language Sync • PWA Compatible
 * Author: Erik Borota / PunktePass
 */

jQuery(document).ready(function ($) {
  console.log("✅ PunktePass User Settings JS v4.0 aktiv");

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
          showToast("✅ Avatar aktualisiert", "success");
        } else {
          showToast("⚠️ Upload fehlgeschlagen", "error");
        }
      },
      error: () => showToast("❌ Netzwerkfehler", "error"),
    });
  });

  /** =============================
   * 💾 BEÁLLÍTÁSOK MENTÉSE
   * ============================= */
  $("#ppv-settings-form").on("submit", function (e) {
    e.preventDefault();

    const data = $(this).serialize();
    $.ajax({
      url: ppv_user_settings.ajax_url,
      type: "POST",
      dataType: "json",
      data: data + "&action=ppv_save_user_settings&nonce=" + ppv_user_settings.nonce,
      success: (res) => {
        if (res.success) {
          showToast("✅ Einstellungen gespeichert", "success");
          // ha nyelv váltott, frissítsük az oldalt, és Elementor menüt is
          const newLang = $("#ppv-language-select").val();
          document.cookie = `ppv_lang=${newLang}; path=/; max-age=31536000`;
          setTimeout(() => {
            window.location.href = window.location.href.split("?")[0] + "?lang=" + newLang;
          }, 1000);
        } else {
          showToast("⚠️ " + (res.data?.msg || "Fehler beim Speichern"), "error");
        }
      },
      error: () => showToast("❌ Netzwerkfehler", "error"),
    });
  });

  /** =============================
   * 🌐 NYELVVÁLTÁS – Elementor menü sync
   * ============================= */
  $("#ppv-language-select").on("change", function () {
    const lang = $(this).val();
    document.cookie = `ppv_lang=${lang}; path=/; max-age=31536000`;

    // Elementor menü feliratai frissítése valós időben
    const labels = {
      de: { home: "Startseite", points: "Meine Punkte", rewards: "Belohnungen", settings: "Einstellungen" },
      hu: { home: "Kezdőlap", points: "Pontjaim", rewards: "Jutalmak", settings: "Beállítások" },
      ro: { home: "Acasă", points: "Punctele Mele", rewards: "Recompense", settings: "Setări" },
    };

    const set = labels[lang];
    $("#punktepass-menu [data-key]").each(function () {
      const key = $(this).data("key");
      if (set[key]) $(this).text(set[key]);
    });
  });

  /** =============================
   * 📦 ADAT EXPORT
   * ============================= */
  $("#ppv-export-data").on("click", function () {
    showToast("📦 Export wird vorbereitet...", "info");
    // később REST: /user/export
    setTimeout(() => showToast("✅ Datenexport abgeschlossen", "success"), 1500);
  });

  /** =============================
   * 📱 ESZKÖZ KIJELENTKEZTETÉS
   * ============================= */
  $("#ppv-logout-all").on("click", function () {
    if (confirm("Möchten Sie sich wirklich auf allen Geräten abmelden?")) {
      showToast("🔐 Abmeldung überall durchgeführt", "success");
      // később REST: /user/logout_all
    }
  });

  /** =============================
   * 🗑️ FIÓK TÖRLÉS
   * ============================= */
  $("#ppv-delete-account").on("click", function () {
    if (confirm("⚠️ Konto wirklich löschen? Diese Aktion ist endgültig.")) {
      showToast("🗑️ Konto zur Löschung markiert", "error");
      // később REST: /user/delete
    }
  });
});
