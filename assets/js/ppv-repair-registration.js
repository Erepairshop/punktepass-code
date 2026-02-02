/**
 * PunktePass - Repair Registration JS
 * Handles the repair shop registration form
 */
(function() {
    'use strict';

    var form = document.getElementById('ppv-repair-register-form');
    if (!form) return;

    var submitBtn = document.getElementById('rr-submit');
    var submitText = form.querySelector('.ppv-repair-submit-text');
    var submitLoading = form.querySelector('.ppv-repair-submit-loading');
    var errorDiv = document.getElementById('rr-error');
    var successDiv = document.getElementById('rr-success');

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // Validate
        var password = document.getElementById('rr-password').value;
        var password2 = document.getElementById('rr-password2').value;

        if (password !== password2) {
            showError('Passwörter stimmen nicht überein');
            return;
        }

        if (password.length < 6) {
            showError('Passwort muss mindestens 6 Zeichen lang sein');
            return;
        }

        if (!document.getElementById('rr-terms').checked) {
            showError('Bitte akzeptieren Sie die AGB und Datenschutzerklärung');
            return;
        }

        // Show loading
        submitBtn.disabled = true;
        submitText.style.display = 'none';
        submitLoading.style.display = 'inline';
        errorDiv.style.display = 'none';

        // Build form data
        var fd = new FormData();
        fd.append('action', 'ppv_repair_register');
        fd.append('nonce', ppvRepairReg.nonce);
        fd.append('shop_name', document.getElementById('rr-shop-name').value);
        fd.append('owner_name', document.getElementById('rr-owner-name').value);
        fd.append('email', document.getElementById('rr-email').value);
        fd.append('password', password);
        fd.append('phone', document.getElementById('rr-phone').value);
        fd.append('address', document.getElementById('rr-address').value);
        fd.append('plz', document.getElementById('rr-plz').value);
        fd.append('city', document.getElementById('rr-city').value);
        fd.append('tax_id', document.getElementById('rr-tax-id').value);

        fetch(ppvRepairReg.ajaxurl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                // Show success
                form.style.display = 'none';
                document.querySelector('.ppv-repair-features').style.display = 'none';
                successDiv.style.display = 'block';

                var formUrl = data.data.form_url;
                document.getElementById('rr-form-url').href = formUrl;
                document.getElementById('rr-form-url').textContent = formUrl;
                document.getElementById('rr-form-link').href = formUrl;

                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                showError(data.data?.message || 'Registrierung fehlgeschlagen');
            }
        })
        .catch(function() {
            showError('Verbindungsfehler. Bitte versuchen Sie es erneut.');
        })
        .finally(function() {
            submitBtn.disabled = false;
            submitText.style.display = 'inline';
            submitLoading.style.display = 'none';
        });
    });

    function showError(msg) {
        errorDiv.textContent = msg;
        errorDiv.style.display = 'block';
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Live slug preview
    var shopNameInput = document.getElementById('rr-shop-name');
    if (shopNameInput) {
        shopNameInput.addEventListener('input', function() {
            var slug = this.value.toLowerCase()
                .replace(/[äÄ]/g, 'ae').replace(/[öÖ]/g, 'oe').replace(/[üÜ]/g, 'ue').replace(/ß/g, 'ss')
                .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            var preview = document.getElementById('rr-slug-preview');
            if (!preview && slug) {
                preview = document.createElement('div');
                preview.id = 'rr-slug-preview';
                preview.className = 'ppv-repair-slug-preview';
                this.parentNode.appendChild(preview);
            }
            if (preview) {
                preview.textContent = slug ? 'punktepass.de/repair/' + slug : '';
                preview.style.display = slug ? 'block' : 'none';
            }
        });
    }
})();
