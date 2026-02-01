<?php
/**
 * PunktePass - Repair Form Registration Page
 * Shortcode: [ppv_repair_register]
 * Standalone registration for repair shops
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Repair_Registration {

    public static function hooks() {
        add_shortcode('ppv_repair_register', [__CLASS__, 'render']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function enqueue_assets() {
        global $post;
        if (!isset($post->post_content) || !has_shortcode($post->post_content, 'ppv_repair_register')) {
            if (!is_page('repair-register')) return;
        }

        wp_enqueue_script(
            'ppv-repair-registration',
            PPV_PLUGIN_URL . 'assets/js/ppv-repair-registration.js',
            ['jquery'],
            PPV_VERSION,
            true
        );

        wp_localize_script('ppv-repair-registration', 'ppvRepairReg', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ppv_repair_register'),
        ]);
    }

    public static function render() {
        // If already logged in as handler with repair, redirect to admin
        if (!empty($_SESSION['ppv_user_id'])) {
            $store_id = PPV_Repair_Core::get_current_store_id();
            if ($store_id) {
                return '<div class="ppv-repair-msg">
                    <p>Sie sind bereits registriert.</p>
                    <a href="/repair-admin" class="ppv-btn">Zum Admin-Bereich &rarr;</a>
                </div>';
            }
        }

        ob_start();
        ?>
        <div class="ppv-repair-register-page">
            <div class="ppv-repair-register-container">
                <div class="ppv-repair-register-header">
                    <img src="<?php echo PPV_PLUGIN_URL; ?>assets/img/punktepass-logo.png" alt="PunktePass" class="ppv-repair-logo">
                    <h1>Reparaturformular registrieren</h1>
                    <p class="ppv-repair-subtitle">Erstellen Sie Ihr digitales Reparaturformular mit PunktePass Kundenbindung</p>
                </div>

                <div class="ppv-repair-features">
                    <div class="ppv-repair-feature">
                        <span class="ppv-repair-feature-icon">&#128736;</span>
                        <div>
                            <strong>Digitales Reparaturformular</strong>
                            <span>Kunden f&uuml;llen das Formular online aus</span>
                        </div>
                    </div>
                    <div class="ppv-repair-feature">
                        <span class="ppv-repair-feature-icon">&#11088;</span>
                        <div>
                            <strong>Automatische Bonuspunkte</strong>
                            <span>Kunden sammeln Punkte bei jeder Reparatur</span>
                        </div>
                    </div>
                    <div class="ppv-repair-feature">
                        <span class="ppv-repair-feature-icon">&#128279;</span>
                        <div>
                            <strong>Eigener Link</strong>
                            <span>punktepass.de/repair/ihr-shop</span>
                        </div>
                    </div>
                    <div class="ppv-repair-feature">
                        <span class="ppv-repair-feature-icon">&#9989;</span>
                        <div>
                            <strong>Kostenlos starten</strong>
                            <span>Bis zu 50 Formulare gratis</span>
                        </div>
                    </div>
                </div>

                <form id="ppv-repair-register-form" class="ppv-repair-register-form" autocomplete="off">
                    <div class="ppv-repair-form-section">
                        <h3>Gesch&auml;ftsdaten</h3>
                        <div class="ppv-repair-field">
                            <label for="rr-shop-name">Firmenname / Shopname *</label>
                            <input type="text" id="rr-shop-name" name="shop_name" required placeholder="z.B. HandyDoktor Lauingen">
                        </div>
                        <div class="ppv-repair-field">
                            <label for="rr-owner-name">Inhaber / Name *</label>
                            <input type="text" id="rr-owner-name" name="owner_name" required placeholder="Max Mustermann">
                        </div>
                        <div class="ppv-repair-row">
                            <div class="ppv-repair-field">
                                <label for="rr-address">Stra&szlig;e &amp; Nr.</label>
                                <input type="text" id="rr-address" name="address" placeholder="Hauptstr. 1">
                            </div>
                            <div class="ppv-repair-field ppv-repair-field-sm">
                                <label for="rr-plz">PLZ</label>
                                <input type="text" id="rr-plz" name="plz" placeholder="89415">
                            </div>
                        </div>
                        <div class="ppv-repair-row">
                            <div class="ppv-repair-field">
                                <label for="rr-city">Stadt</label>
                                <input type="text" id="rr-city" name="city" placeholder="Lauingen">
                            </div>
                            <div class="ppv-repair-field">
                                <label for="rr-phone">Telefon</label>
                                <input type="tel" id="rr-phone" name="phone" placeholder="+49 123 456789">
                            </div>
                        </div>
                        <div class="ppv-repair-field">
                            <label for="rr-tax-id">USt-IdNr. (optional)</label>
                            <input type="text" id="rr-tax-id" name="tax_id" placeholder="DE123456789">
                        </div>
                    </div>

                    <div class="ppv-repair-form-section">
                        <h3>Zugangsdaten</h3>
                        <div class="ppv-repair-field">
                            <label for="rr-email">E-Mail-Adresse *</label>
                            <input type="email" id="rr-email" name="email" required placeholder="info@ihr-shop.de">
                        </div>
                        <div class="ppv-repair-field">
                            <label for="rr-password">Passwort * (min. 6 Zeichen)</label>
                            <input type="password" id="rr-password" name="password" required minlength="6" placeholder="Sicheres Passwort">
                        </div>
                        <div class="ppv-repair-field">
                            <label for="rr-password2">Passwort best&auml;tigen *</label>
                            <input type="password" id="rr-password2" name="password2" required minlength="6" placeholder="Passwort wiederholen">
                        </div>
                    </div>

                    <div class="ppv-repair-terms">
                        <label>
                            <input type="checkbox" id="rr-terms" required>
                            Ich akzeptiere die <a href="/agb" target="_blank">AGB</a> und <a href="/datenschutz" target="_blank">Datenschutzerkl&auml;rung</a>
                        </label>
                    </div>

                    <button type="submit" id="rr-submit" class="ppv-repair-submit">
                        <span class="ppv-repair-submit-text">Kostenlos registrieren</span>
                        <span class="ppv-repair-submit-loading" style="display:none;">Wird erstellt...</span>
                    </button>

                    <div id="rr-error" class="ppv-repair-error" style="display:none;"></div>
                </form>

                <!-- Success screen -->
                <div id="rr-success" class="ppv-repair-success" style="display:none;">
                    <div class="ppv-repair-success-icon">&#127881;</div>
                    <h2>Registrierung erfolgreich!</h2>
                    <p>Ihr Reparaturformular ist bereit:</p>
                    <div class="ppv-repair-success-link">
                        <a id="rr-form-url" href="#" target="_blank"></a>
                    </div>
                    <p class="ppv-repair-success-info">Sie erhalten alle Details per E-Mail.</p>
                    <div class="ppv-repair-success-actions">
                        <a href="/repair-admin" class="ppv-repair-btn-primary">Zum Admin-Bereich</a>
                        <a id="rr-form-link" href="#" class="ppv-repair-btn-secondary" target="_blank">Formular testen</a>
                    </div>
                </div>

                <div class="ppv-repair-register-footer">
                    Powered by <a href="https://punktepass.de">PunktePass</a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
