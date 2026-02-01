<?php
/**
 * PunktePass - Repair Form Registration Page (Standalone)
 * Renders a complete standalone HTML page at /formular
 * No WordPress shortcode, no WordPress theme
 *
 * Routing is handled by PPV_Repair_Core::handle_routes()
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Repair_Registration {

    /**
     * Render complete standalone HTML registration page
     * Called by PPV_Repair_Core::handle_routes() for /formular
     */
    public static function render_standalone() {
        // If already logged in as repair handler, redirect to admin
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        if (!empty($_SESSION['ppv_repair_store_id'])) {
            header('Location: /formular/admin');
            exit;
        }

        $ajax_url = admin_url('admin-ajax.php');
        $nonce    = wp_create_nonce('ppv_repair_register');
        $logo_url = PPV_PLUGIN_URL . 'assets/img/punktepass-logo.png';

        ?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reparaturformular registrieren - PunktePass</title>
    <meta name="description" content="Erstellen Sie Ihr digitales Reparaturformular mit automatischen Bonuspunkten. Kostenlos starten mit PunktePass.">
    <meta name="robots" content="index, follow">
    <link rel="icon" href="https://punktepass.de/wp-content/uploads/2025/04/cropped-ppfavicon-32x32.png" sizes="32x32">
    <style>
        /* ── Reset & Base ── */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { font-size: 16px; -webkit-text-size-adjust: 100%; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f4f5f7;
            color: #1f2937;
            line-height: 1.6;
            min-height: 100vh;
        }
        a { color: #667eea; text-decoration: none; }
        a:hover { text-decoration: underline; }
        img { max-width: 100%; height: auto; }

        /* ── Page Header ── */
        .pp-reg-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 48px 20px 40px;
            text-align: center;
        }
        .pp-reg-header-logo {
            height: 48px;
            margin-bottom: 16px;
            filter: brightness(0) invert(1);
        }
        .pp-reg-header h1 {
            color: #fff;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.3px;
        }
        .pp-reg-header p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 15px;
            max-width: 480px;
            margin: 0 auto;
        }

        /* ── Container ── */
        .pp-reg-container {
            max-width: 640px;
            margin: 0 auto;
            padding: 0 16px 40px;
        }

        /* ── Features Grid ── */
        .pp-reg-features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: -24px 0 32px;
            position: relative;
            z-index: 1;
        }
        .pp-reg-feature {
            background: #fff;
            border-radius: 12px;
            padding: 20px 16px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .pp-reg-feature:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }
        .pp-reg-feature-icon {
            display: block;
            font-size: 28px;
            margin-bottom: 8px;
        }
        .pp-reg-feature strong {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }
        .pp-reg-feature span {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.4;
        }

        /* ── Form Card ── */
        .pp-reg-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }
        .pp-reg-form {
            padding: 32px 24px;
        }

        /* ── Form Sections ── */
        .pp-reg-section {
            margin-bottom: 28px;
        }
        .pp-reg-section:last-of-type {
            margin-bottom: 0;
        }
        .pp-reg-section h3 {
            font-size: 15px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f3f4f6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 12px;
        }

        /* ── Fields ── */
        .pp-reg-field {
            margin-bottom: 16px;
        }
        .pp-reg-field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .pp-reg-field input[type="text"],
        .pp-reg-field input[type="email"],
        .pp-reg-field input[type="password"],
        .pp-reg-field input[type="tel"] {
            width: 100%;
            padding: 12px 14px;
            font-size: 15px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            background: #fafafa;
            color: #1f2937;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            outline: none;
            font-family: inherit;
        }
        .pp-reg-field input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
            background: #fff;
        }
        .pp-reg-field input::placeholder {
            color: #9ca3af;
        }

        /* ── Row Layout ── */
        .pp-reg-row {
            display: flex;
            gap: 12px;
        }
        .pp-reg-row .pp-reg-field {
            flex: 1;
        }
        .pp-reg-row .pp-reg-field-sm {
            flex: 0 0 100px;
        }

        /* ── Terms ── */
        .pp-reg-terms {
            margin: 24px 0;
            padding: 16px;
            background: #f9fafb;
            border-radius: 10px;
        }
        .pp-reg-terms label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            color: #4b5563;
            cursor: pointer;
            line-height: 1.5;
        }
        .pp-reg-terms input[type="checkbox"] {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            accent-color: #667eea;
            cursor: pointer;
        }
        .pp-reg-terms a {
            color: #667eea;
            font-weight: 600;
        }

        /* ── Submit Button ── */
        .pp-reg-submit {
            display: block;
            width: 100%;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.1s;
            font-family: inherit;
            letter-spacing: 0.3px;
        }
        .pp-reg-submit:hover {
            opacity: 0.92;
        }
        .pp-reg-submit:active {
            transform: scale(0.985);
        }
        .pp-reg-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* ── Error Message ── */
        .pp-reg-error {
            margin-top: 16px;
            padding: 12px 16px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            color: #dc2626;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
        }

        /* ── Success Screen ── */
        .pp-reg-success {
            text-align: center;
            padding: 48px 24px;
        }
        .pp-reg-success-icon {
            font-size: 56px;
            margin-bottom: 16px;
        }
        .pp-reg-success h2 {
            font-size: 24px;
            font-weight: 700;
            color: #059669;
            margin-bottom: 8px;
        }
        .pp-reg-success > p {
            font-size: 15px;
            color: #6b7280;
            margin-bottom: 20px;
        }
        .pp-reg-success-link {
            background: #f0f9ff;
            border: 1.5px solid #bae6fd;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .pp-reg-success-link .pp-reg-link-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #0369a1;
            margin-bottom: 8px;
        }
        .pp-reg-success-link a {
            font-size: 16px;
            font-weight: 600;
            color: #1d4ed8;
            word-break: break-all;
        }
        .pp-reg-success-info {
            font-size: 13px;
            color: #9ca3af;
            margin-bottom: 24px;
        }
        .pp-reg-success-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .pp-reg-btn-primary {
            display: inline-block;
            padding: 12px 28px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .pp-reg-btn-primary:hover {
            opacity: 0.9;
            text-decoration: none;
        }
        .pp-reg-btn-secondary {
            display: inline-block;
            padding: 12px 28px;
            background: #f3f4f6;
            color: #374151;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: background 0.2s;
        }
        .pp-reg-btn-secondary:hover {
            background: #e5e7eb;
            text-decoration: none;
        }

        /* ── Login Link ── */
        .pp-reg-login-link {
            text-align: center;
            padding: 20px;
            font-size: 14px;
            color: #6b7280;
            border-top: 1px solid #f3f4f6;
        }
        .pp-reg-login-link a {
            color: #667eea;
            font-weight: 600;
        }

        /* ── Footer ── */
        .pp-reg-footer {
            text-align: center;
            padding: 24px 16px;
            font-size: 12px;
            color: #9ca3af;
        }
        .pp-reg-footer a {
            color: #667eea;
        }

        /* ── Mobile Adjustments ── */
        @media (max-width: 480px) {
            .pp-reg-header {
                padding: 36px 16px 32px;
            }
            .pp-reg-header h1 {
                font-size: 22px;
            }
            .pp-reg-header p {
                font-size: 14px;
            }
            .pp-reg-features {
                grid-template-columns: 1fr;
                margin-top: -16px;
            }
            .pp-reg-feature {
                display: flex;
                align-items: center;
                text-align: left;
                gap: 12px;
                padding: 14px 16px;
            }
            .pp-reg-feature-icon {
                font-size: 24px;
                margin-bottom: 0;
                flex-shrink: 0;
            }
            .pp-reg-form {
                padding: 24px 16px;
            }
            .pp-reg-row {
                flex-direction: column;
                gap: 0;
            }
            .pp-reg-row .pp-reg-field-sm {
                flex: 1;
            }
            .pp-reg-success-actions {
                flex-direction: column;
                align-items: center;
            }
        }

        /* ── Spinner ── */
        .pp-reg-spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2.5px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: pp-spin 0.6s linear infinite;
            vertical-align: middle;
            margin-right: 8px;
        }
        @keyframes pp-spin {
            to { transform: rotate(360deg); }
        }

        /* ── Hide utility ── */
        .pp-hidden {
            display: none !important;
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="pp-reg-header">
    <img src="<?php echo esc_url($logo_url); ?>" alt="PunktePass" class="pp-reg-header-logo">
    <h1>Reparaturformular registrieren</h1>
    <p>Erstellen Sie Ihr digitales Reparaturformular mit automatischen Bonuspunkten f&uuml;r Ihre Kunden</p>
</div>

<div class="pp-reg-container">

    <!-- Features -->
    <div class="pp-reg-features">
        <div class="pp-reg-feature">
            <span class="pp-reg-feature-icon">&#128736;</span>
            <div>
                <strong>Digitales Reparaturformular</strong>
                <span>Kunden f&uuml;llen das Formular online aus</span>
            </div>
        </div>
        <div class="pp-reg-feature">
            <span class="pp-reg-feature-icon">&#11088;</span>
            <div>
                <strong>Automatische Bonuspunkte</strong>
                <span>Kunden sammeln Punkte bei jeder Reparatur</span>
            </div>
        </div>
        <div class="pp-reg-feature">
            <span class="pp-reg-feature-icon">&#128279;</span>
            <div>
                <strong>Eigener Link</strong>
                <span>punktepass.de/formular/ihr-shop</span>
            </div>
        </div>
        <div class="pp-reg-feature">
            <span class="pp-reg-feature-icon">&#9989;</span>
            <div>
                <strong>Kostenlos starten</strong>
                <span>Bis zu 50 Formulare gratis</span>
            </div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="pp-reg-card">

        <!-- Registration Form -->
        <form id="pp-reg-form" class="pp-reg-form" autocomplete="off" novalidate>

            <!-- Business Details -->
            <div class="pp-reg-section">
                <h3>Gesch&auml;ftsdaten</h3>

                <div class="pp-reg-field">
                    <label for="rr-shop-name">Firmenname / Shopname *</label>
                    <input type="text" id="rr-shop-name" name="shop_name" required placeholder="z.B. HandyDoktor Lauingen">
                </div>

                <div class="pp-reg-field">
                    <label for="rr-owner-name">Inhaber / Name *</label>
                    <input type="text" id="rr-owner-name" name="owner_name" required placeholder="Max Mustermann">
                </div>

                <div class="pp-reg-row">
                    <div class="pp-reg-field">
                        <label for="rr-address">Stra&szlig;e &amp; Nr.</label>
                        <input type="text" id="rr-address" name="address" placeholder="Hauptstr. 1">
                    </div>
                    <div class="pp-reg-field pp-reg-field-sm">
                        <label for="rr-plz">PLZ</label>
                        <input type="text" id="rr-plz" name="plz" placeholder="89415" maxlength="5">
                    </div>
                </div>

                <div class="pp-reg-field">
                    <label for="rr-city">Stadt</label>
                    <input type="text" id="rr-city" name="city" placeholder="Lauingen">
                </div>

                <div class="pp-reg-row">
                    <div class="pp-reg-field">
                        <label for="rr-phone">Telefon</label>
                        <input type="tel" id="rr-phone" name="phone" placeholder="+49 123 456789">
                    </div>
                    <div class="pp-reg-field">
                        <label for="rr-tax-id">USt-IdNr.</label>
                        <input type="text" id="rr-tax-id" name="tax_id" placeholder="DE123456789">
                    </div>
                </div>
            </div>

            <!-- Login Credentials -->
            <div class="pp-reg-section">
                <h3>Zugangsdaten</h3>

                <div class="pp-reg-field">
                    <label for="rr-email">E-Mail-Adresse *</label>
                    <input type="email" id="rr-email" name="email" required placeholder="info@ihr-shop.de">
                </div>

                <div class="pp-reg-field">
                    <label for="rr-password">Passwort * <span style="font-weight:400;color:#9ca3af;">(min. 6 Zeichen)</span></label>
                    <input type="password" id="rr-password" name="password" required minlength="6" placeholder="Sicheres Passwort">
                </div>

                <div class="pp-reg-field">
                    <label for="rr-password2">Passwort best&auml;tigen *</label>
                    <input type="password" id="rr-password2" name="password2" required minlength="6" placeholder="Passwort wiederholen">
                </div>
            </div>

            <!-- Terms -->
            <div class="pp-reg-terms">
                <label>
                    <input type="checkbox" id="rr-terms" required>
                    <span>Ich akzeptiere die <a href="/datenschutz" target="_blank">Datenschutzerkl&auml;rung</a> und <a href="/agb" target="_blank">AGB</a></span>
                </label>
            </div>

            <!-- Submit -->
            <button type="submit" id="rr-submit" class="pp-reg-submit">
                Kostenlos registrieren
            </button>

            <!-- Error -->
            <div id="rr-error" class="pp-reg-error pp-hidden"></div>
        </form>

        <!-- Success Screen (hidden by default) -->
        <div id="rr-success" class="pp-reg-success pp-hidden">
            <div class="pp-reg-success-icon">&#127881;</div>
            <h2>Registrierung erfolgreich!</h2>
            <p>Ihr Reparaturformular ist bereit:</p>
            <div class="pp-reg-success-link">
                <div class="pp-reg-link-label">Ihr Formular-Link</div>
                <a id="rr-form-url" href="#" target="_blank"></a>
            </div>
            <p class="pp-reg-success-info">Sie erhalten alle Zugangsdaten per E-Mail.</p>
            <div class="pp-reg-success-actions">
                <a href="/formular/admin" class="pp-reg-btn-primary">Zum Admin-Bereich &rarr;</a>
                <a id="rr-form-link" href="#" class="pp-reg-btn-secondary" target="_blank">Formular testen</a>
            </div>
        </div>

        <!-- Login Link -->
        <div id="rr-login-row" class="pp-reg-login-link">
            Bereits registriert? <a href="/formular/admin/login">Hier einloggen</a>
        </div>
    </div>
</div>

<!-- Footer -->
<div class="pp-reg-footer">
    Powered by <a href="https://punktepass.de">PunktePass</a>
</div>

<script>
(function() {
    'use strict';

    var AJAX_URL = <?php echo json_encode($ajax_url); ?>;
    var NONCE    = <?php echo json_encode($nonce); ?>;

    var form       = document.getElementById('pp-reg-form');
    var submitBtn  = document.getElementById('rr-submit');
    var errorBox   = document.getElementById('rr-error');
    var successBox = document.getElementById('rr-success');
    var loginRow   = document.getElementById('rr-login-row');

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.classList.remove('pp-hidden');
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function hideError() {
        errorBox.classList.add('pp-hidden');
    }

    function setLoading(loading) {
        if (loading) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="pp-reg-spinner"></span> Wird erstellt...';
        } else {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Kostenlos registrieren';
        }
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        hideError();

        var shopName  = document.getElementById('rr-shop-name').value.trim();
        var ownerName = document.getElementById('rr-owner-name').value.trim();
        var email     = document.getElementById('rr-email').value.trim();
        var password  = document.getElementById('rr-password').value;
        var password2 = document.getElementById('rr-password2').value;
        var terms     = document.getElementById('rr-terms').checked;
        var address   = document.getElementById('rr-address').value.trim();
        var plz       = document.getElementById('rr-plz').value.trim();
        var city      = document.getElementById('rr-city').value.trim();
        var phone     = document.getElementById('rr-phone').value.trim();
        var taxId     = document.getElementById('rr-tax-id').value.trim();

        // Validation
        if (!shopName) { showError('Bitte geben Sie den Firmennamen ein.'); return; }
        if (!ownerName) { showError('Bitte geben Sie den Inhaber-Namen ein.'); return; }
        if (!email) { showError('Bitte geben Sie Ihre E-Mail-Adresse ein.'); return; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showError('Bitte geben Sie eine g\u00fcltige E-Mail-Adresse ein.'); return; }
        if (!password || password.length < 6) { showError('Das Passwort muss mindestens 6 Zeichen lang sein.'); return; }
        if (password !== password2) { showError('Die Passw\u00f6rter stimmen nicht \u00fcberein.'); return; }
        if (!terms) { showError('Bitte akzeptieren Sie die AGB und Datenschutzerkl\u00e4rung.'); return; }

        setLoading(true);

        var data = new FormData();
        data.append('action', 'ppv_repair_register');
        data.append('nonce', NONCE);
        data.append('shop_name', shopName);
        data.append('owner_name', ownerName);
        data.append('email', email);
        data.append('password', password);
        data.append('address', address);
        data.append('plz', plz);
        data.append('city', city);
        data.append('phone', phone);
        data.append('tax_id', taxId);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', AJAX_URL, true);
        xhr.onload = function() {
            setLoading(false);

            if (xhr.status !== 200) {
                showError('Serverfehler. Bitte versuchen Sie es sp\u00e4ter erneut.');
                return;
            }

            try {
                var res = JSON.parse(xhr.responseText);
            } catch (err) {
                showError('Unerwartete Antwort vom Server.');
                return;
            }

            if (res.success && res.data) {
                var slug    = res.data.slug || '';
                var formUrl = 'https://punktepass.de/formular/' + slug;

                document.getElementById('rr-form-url').href = formUrl;
                document.getElementById('rr-form-url').textContent = formUrl;
                document.getElementById('rr-form-link').href = formUrl;

                form.classList.add('pp-hidden');
                loginRow.classList.add('pp-hidden');
                successBox.classList.remove('pp-hidden');
                successBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                showError(res.data && res.data.message ? res.data.message : 'Registrierung fehlgeschlagen. Bitte versuchen Sie es erneut.');
            }
        };
        xhr.onerror = function() {
            setLoading(false);
            showError('Netzwerkfehler. Bitte pr\u00fcfen Sie Ihre Internetverbindung.');
        };
        xhr.send(data);
    });
})();
</script>

</body>
</html>
<?php
    }
}
