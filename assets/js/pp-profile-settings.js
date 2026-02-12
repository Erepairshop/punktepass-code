jQuery(document).ready(function($){

    /** =============================
     * 🌍 TRANSLATIONS (DE/HU/RO)
     * ============================= */
    const LANG = (window.ppv_ajax && window.ppv_ajax.lang) || 'de';

    const T = {
        de: {
            saved: "Gespeichert ✅",
            error: "Fehler:",
            status_changed: "Abo-Status geändert:",
        },
        hu: {
            saved: "Mentve ✅",
            error: "Hiba:",
            status_changed: "Előfizetés állapota megváltozott:",
        },
        ro: {
            saved: "Salvat ✅",
            error: "Eroare:",
            status_changed: "Starea abonamentului s-a schimbat:",
        },
        en: {
            saved: "Saved ✅",
            error: "Error:",
            status_changed: "Subscription status changed:",
        }
    }[LANG] || T.de;

    // 🔹 Mentés gomb – Allgemeine Einstellungen
    $(".ppv-btn-save").on("click", function(){
        $.post(ppv_ajax.ajax_url, {
            action: "ppv_save_settings",
            nonce: ppv_ajax.nonce,
            notify_email: $("#ppv-notify-email").is(":checked") ? 1 : 0,
            notify_push: $("#ppv-notify-push").is(":checked") ? 1 : 0,
            dark_mode: $("#ppv-dark-mode").is(":checked") ? 1 : 0,
            profile_public: $("#ppv-profile-public").is(":checked") ? 1 : 0
        }, function(resp){
            if(resp.success){
                $(".ppv-save-message").text(T.saved).fadeIn().delay(2000).fadeOut();
            } else {
                alert(T.error + " " + resp.data.message);
            }
        });
    });

    // 🔹 Abo pausieren / fortsetzen
    $(".ppv-abo-actions").on("click", ".ppv-btn-outline:contains('Abo pausieren'), .ppv-btn-outline:contains('Abo fortsetzen')", function(){
        $.post(ppv_ajax.ajax_url, {
            action: "ppv_toggle_abo_status",
            nonce: ppv_ajax.nonce
        }, function(resp){
            if(resp.success){
                alert(T.status_changed + " " + resp.data.new_status);
                location.reload();
            } else {
                alert(T.error + " " + resp.data.message);
            }
        });
    });
});
