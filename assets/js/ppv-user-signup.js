jQuery(document).ready(function($) {
    $('#ppv_user_form').on('submit', function(e) {
        e.preventDefault();

        const pw = $('#ppv_user_password').val();
        const pw2 = $('#ppv_user_password_repeat').val();
        const regex = /^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*()_\-+=<>?{}[\]~]).{8,}$/;


        if (pw !== pw2) {
            $('#ppv_user_msg').text('❌ Passwörter stimmen nicht überein.');
            return;
        }
        if (!regex.test(pw)) {
            $('#ppv_user_msg').text('❌ Passwort muss Großbuchstaben, Zahl und Sonderzeichen enthalten.');
            return;
        }

        const formData = $(this).serialize();
        $('#ppv_user_msg').text('⏳ Registrierung wird verarbeitet...');

        $.post(ppv_user_signup.ajax_url, formData, function(res) {
            if (res.success) {
                $('#ppv_user_msg').text('✅ ' + res.data.msg);
                if (res.data.redirect) window.location.href = res.data.redirect;
            } else {
                $('#ppv_user_msg').text('❌ ' + res.data.msg);
            }
        }).fail(function(xhr) {
            $('#ppv_user_msg').text('❌ Serverfehler (' + xhr.status + ').');
        });
    });
});
