/**
 * PunktePass – User Settings v5.2
 * Avatar Upload • Modal System • Notifications • Privacy • Address
 * 🚀 Turbo-compatible
 * Author: Erik Borota / PunktePass
 */

(function() {
  'use strict';

  // ✅ Script guard - prevent duplicate execution
  if (window.PPV_USER_SETTINGS_LOADED) {
    return;
  }
  window.PPV_USER_SETTINGS_LOADED = true;

  // ✅ DEBUG MODE - Set to false in production to reduce console spam
  const PPV_SETTINGS_DEBUG = false;

  // ✅ Conditional logger
  function settingsLog(...args) {
    if (PPV_SETTINGS_DEBUG) {
      console.log(...args);
    }
  }

// 🚀 Main initialization function
function initUserSettings() {
  const $ = jQuery;

  // Prevent double initialization
  const wrapper = document.querySelector('.ppv-settings-wrapper');
  if (!wrapper) {
    settingsLog("⏭️ [Settings] Not a settings page, skipping");
    return;
  }
  if (wrapper.dataset.initialized === 'true') {
    settingsLog("⏭️ [Settings] Already initialized, skipping");
    return;
  }
  wrapper.dataset.initialized = 'true';

  settingsLog("✅ PunktePass User Settings JS v5.2 aktiv (Turbo)");

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
    },
    en: {
      avatar_updated: "✅ Avatar updated",
      upload_failed: "⚠️ Upload failed",
      network_error: "❌ Network error",
      settings_saved: "✅ Settings saved",
      save_error: "Error saving",
      logout_all_confirm: "Are you sure you want to log out on all devices?",
      password_required: "⚠️ Please enter your password",
      delete_final_warning: "⚠️ LAST WARNING: Are you sure you want to permanently delete your account?",
    }
  }[LANG] || {
    avatar_updated: "✅ Avatar aktualisiert",
    upload_failed: "⚠️ Upload fehlgeschlagen",
    network_error: "❌ Netzwerkfehler",
    settings_saved: "✅ Einstellungen gespeichert",
    save_error: "Fehler beim Speichern",
    logout_all_confirm: "Möchten Sie sich wirklich auf allen Geräten abmelden?",
    password_required: "⚠️ Bitte Passwort eingeben",
    delete_final_warning: "⚠️ LETZTE WARNUNG: Konto wirklich unwiderruflich löschen?",
  };

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
  $("#ppv-avatar-upload").off("change").on("change", function () {
    const file = this.files[0];
    if (!file) return;

    // ✅ Ellenőrizzük, hogy a settings objektum elérhető-e
    if (!window.ppv_user_settings || !window.ppv_user_settings.nonce) {
      console.error("❌ [Avatar] ppv_user_settings not available!", window.ppv_user_settings);
      showToast(T.network_error + " (config)", "error");
      return;
    }

    const formData = new FormData();
    formData.append("action", "ppv_upload_avatar");
    formData.append("avatar", file);
    formData.append("nonce", window.ppv_user_settings.nonce);


    $.ajax({
      url: window.ppv_user_settings.ajax_url,
      type: "POST",
      processData: false,
      contentType: false,
      data: formData,
      success: (res) => {
        if (res.success && res.data.url) {
          $("#ppv-avatar-preview").attr("src", res.data.url);
          showToast(T.avatar_updated, "success");
        } else {
          showToast(T.upload_failed + (res.data?.msg ? ": " + res.data.msg : ""), "error");
        }
      },
      error: (xhr, status, error) => {
        console.error("❌ [Avatar] Upload error:", status, error, xhr.responseText);
        showToast(T.network_error + " (" + xhr.status + ")", "error");
      },
    });
  });

  /** =============================
   * 📱 WHATSAPP PHONE TOGGLE
   * ============================= */
  const $whatsappToggle = $('#ppv-whatsapp-toggle');
  const $phoneWrapper = $('#ppv-whatsapp-phone-wrapper');

  $whatsappToggle.off('change.whatsapp').on('change.whatsapp', function() {
    if ($(this).is(':checked')) {
      $phoneWrapper.slideDown(300);
      // Focus on phone input after animation
      setTimeout(() => {
        $('#ppv-phone-number').focus();
      }, 350);
    } else {
      $phoneWrapper.slideUp(300);
    }
  });

  /** =============================
   * 💾 BEÁLLÍTÁSOK MENTÉSE
   * ============================= */
  $("#ppv-settings-form").off("submit").on("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    // Checkbox értékek kezelése
    formData.set('email_notifications', $('input[name="email_notifications"]').is(':checked'));
    formData.set('push_notifications', $('input[name="push_notifications"]').is(':checked'));
    formData.set('promo_notifications', $('input[name="promo_notifications"]').is(':checked'));
    formData.set('profile_visible', $('input[name="profile_visible"]').is(':checked'));
    formData.set('marketing_emails', $('input[name="marketing_emails"]').is(':checked'));
    formData.set('data_sharing', $('input[name="data_sharing"]').is(':checked'));
    formData.set('whatsapp_notifications', $('input[name="whatsapp_notifications"]').is(':checked'));

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
  $("#ppv-logout-all").off("click").on("click", function () {
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
            showToast("⚠️ " + (res.data?.msg || T.error || "Fehler"), "error");
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
  $("#ppv-delete-account-btn").off("click").on("click", function () {
    $modal.fadeIn(300);
    $("#ppv-delete-password").val('');
  });

  // Modal bezárása
  $(".ppv-modal-close, #ppv-cancel-delete").off("click").on("click", function () {
    $modal.fadeOut(300);
  });

  // Modal bezárása kattintással
  $(window).off("click.ppvModal").on("click.ppvModal", function (e) {
    if (e.target.id === "ppv-delete-modal") {
      $modal.fadeOut(300);
    }
  });

  // Törlés megerősítése
  $("#ppv-confirm-delete").off("click").on("click", function () {
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

  /** =============================
   * ❓ FAQ ACCORDION
   * ============================= */
  const faqSection = document.querySelector('.ppv-faq-section');
  if (faqSection) {
    // Add FAQ styles if not present
    if (!document.getElementById('ppv-faq-styles')) {
      const style = document.createElement('style');
      style.id = 'ppv-faq-styles';
      style.textContent = `
        .ppv-faq-section {
          margin-top: 24px;
        }
        .ppv-faq-subtitle {
          color: var(--ppv-text-secondary, #666);
          margin-bottom: 20px;
          font-size: 14px;
        }
        .ppv-faq-category {
          margin-bottom: 20px;
        }
        .ppv-faq-category h4 {
          display: flex;
          align-items: center;
          gap: 8px;
          font-size: 15px;
          font-weight: 600;
          color: var(--ppv-primary, #2563eb);
          margin: 16px 0 12px 0;
          padding-bottom: 8px;
          border-bottom: 1px solid var(--ppv-border, #e5e7eb);
        }
        .ppv-faq-category h4 i {
          font-size: 18px;
        }
        .ppv-faq-item {
          margin-bottom: 8px;
          border-radius: 10px;
          overflow: hidden;
          background: var(--ppv-card-bg, #f9fafb);
          border: 1px solid var(--ppv-border, #e5e7eb);
        }
        .ppv-faq-question {
          width: 100%;
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 14px 16px;
          background: transparent;
          border: none;
          cursor: pointer;
          text-align: left;
          font-size: 14px;
          font-weight: 500;
          color: var(--ppv-text, #1f2937);
          transition: background 0.2s;
        }
        .ppv-faq-question:hover {
          background: var(--ppv-hover, rgba(0,0,0,0.03));
        }
        .ppv-faq-question span {
          flex: 1;
          padding-right: 12px;
        }
        .ppv-faq-question i {
          font-size: 20px;
          color: var(--ppv-text-secondary, #6b7280);
          transition: transform 0.3s ease;
        }
        .ppv-faq-item.open .ppv-faq-question i {
          transform: rotate(180deg);
        }
        .ppv-faq-answer {
          max-height: 0;
          overflow: hidden;
          padding: 0 16px;
          font-size: 13px;
          line-height: 1.6;
          color: var(--ppv-text-secondary, #4b5563);
          background: var(--ppv-card-bg, #fff);
          transition: max-height 0.3s ease, padding 0.3s ease;
        }
        .ppv-faq-item.open .ppv-faq-answer {
          max-height: 500px;
          padding: 14px 16px;
          border-top: 1px solid var(--ppv-border, #e5e7eb);
        }
        .ppv-faq-steps {
          list-style: none;
          padding: 0;
          margin: 8px 0 0 0;
        }
        .ppv-faq-steps li {
          padding: 6px 0;
          padding-left: 24px;
          position: relative;
        }
        .ppv-faq-steps li::before {
          content: '';
          position: absolute;
          left: 0;
          top: 50%;
          transform: translateY(-50%);
          width: 16px;
          height: 16px;
          background: var(--ppv-primary, #2563eb);
          border-radius: 50%;
          opacity: 0.2;
        }
        /* Dark mode support */
        body.ppv-dark .ppv-faq-item {
          background: rgba(255,255,255,0.05);
          border-color: rgba(255,255,255,0.1);
        }
        body.ppv-dark .ppv-faq-question {
          color: #fff;
        }
        body.ppv-dark .ppv-faq-question:hover {
          background: rgba(255,255,255,0.05);
        }
        body.ppv-dark .ppv-faq-answer {
          background: rgba(255,255,255,0.02);
          color: rgba(255,255,255,0.7);
          border-color: rgba(255,255,255,0.1);
        }
      `;
      document.head.appendChild(style);
    }

    // FAQ accordion toggle
    faqSection.querySelectorAll('.ppv-faq-question').forEach(btn => {
      btn.addEventListener('click', function() {
        const item = this.closest('.ppv-faq-item');
        const wasOpen = item.classList.contains('open');

        // Close all others in the same category
        const category = item.closest('.ppv-faq-category');
        category.querySelectorAll('.ppv-faq-item.open').forEach(openItem => {
          openItem.classList.remove('open');
        });

        // Toggle current
        if (!wasOpen) {
          item.classList.add('open');
        }
      });
    });

    settingsLog("✅ FAQ accordion initialized");
  }
}

// Initialize on jQuery ready
jQuery(document).ready(initUserSettings);

// 🚀 Turbo-compatible: Re-initialize after navigation (only turbo:load, not render to avoid double-init)
document.addEventListener("turbo:load", function() {
  const wrapper = document.querySelector('.ppv-settings-wrapper');
  if (wrapper) {
    wrapper.dataset.initialized = 'false';
  }
  initUserSettings();
});

})(); // End IIFE
