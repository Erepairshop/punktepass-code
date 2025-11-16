jQuery(document).ready(function($){

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
                $(".ppv-save-message").text("Gespeichert ✅").fadeIn().delay(2000).fadeOut();
            } else {
                alert("Fehler: " + resp.data.message);
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
                alert("Abo-Status geändert: " + resp.data.new_status);
                location.reload();
            } else {
                alert("Fehler: " + resp.data.message);
            }
        });
    });
});
