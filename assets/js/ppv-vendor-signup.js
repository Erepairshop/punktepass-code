jQuery(document).ready(function ($) {
    $('#ppv_vendor_form').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'ppv_vendor_signup');

        $('#ppv_vendor_msg').html('⏳ Registrierung läuft...');


        $.ajax({
            url: ppv_vendor_signup.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                if (response.success) {
                    $('#ppv_vendor_msg').html('✅ ' + response.data.msg);
                    setTimeout(() => window.location.href = response.data.redirect, 1200);
                } else {
                    $('#ppv_vendor_msg').html('❌ ' + response.data.msg);
                }
            },
            error: function (xhr) {
                $('#ppv_vendor_msg').html('⚠️ Serverfehler. Bitte später versuchen.');
            }
        });
    });
});
