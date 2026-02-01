<?php
/**
 * PunktePass - Public Repair Form (Standalone Page)
 * Renders a complete branded repair form for each store
 * URL: /repair/{shopslug}
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Repair_Form {

    /** ============================================================
     * Render the standalone public repair form page
     * Called from PPV_Repair_Core::handle_repair_page()
     * ============================================================ */
    public static function render_standalone_page($store, $limit_reached = false) {
        $store_name = esc_html($store->repair_company_name ?: $store->name);
        $color      = esc_attr($store->repair_color ?: '#667eea');
        $logo       = esc_url($store->logo ?: PPV_PLUGIN_URL . 'assets/img/punktepass-logo.png');
        $slug       = esc_attr($store->slug);
        $points     = intval($store->repair_points_per_form ?: 2);
        $store_id   = intval($store->id);
        $address    = esc_html(trim(($store->address ?: '') . ' ' . ($store->plz ?: '') . ' ' . ($store->city ?: '')));

        $nonce = wp_create_nonce('ppv_repair_form');
        $ajax_url = admin_url('admin-ajax.php');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Reparaturformular - <?php echo $store_name; ?></title>
    <meta name="description" content="Digitales Reparaturformular von <?php echo $store_name; ?> - Jetzt ausfüllen und Bonuspunkte sammeln!">
    <meta name="theme-color" content="<?php echo $color; ?>">
    <link rel="icon" href="<?php echo $logo; ?>" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="<?php echo PPV_PLUGIN_URL; ?>assets/css/ppv-repair.css?v=<?php echo PPV_VERSION; ?>">
    <style>:root{--repair-accent:<?php echo $color; ?>;--repair-accent-dark:color-mix(in srgb,<?php echo $color; ?>,#000 20%)}</style>
</head>
<body class="ppv-repair-body">

<div class="repair-page">
    <!-- Header -->
    <div class="repair-header">
        <div class="repair-header-inner">
            <?php if ($store->logo): ?>
                <img src="<?php echo $logo; ?>" alt="<?php echo $store_name; ?>" class="repair-logo">
            <?php endif; ?>
            <h1 class="repair-shop-name"><?php echo $store_name; ?></h1>
            <?php if ($address): ?>
                <p class="repair-shop-address"><i class="ri-map-pin-line"></i> <?php echo $address; ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($limit_reached): ?>
    <!-- Limit reached message -->
    <div class="repair-limit-reached">
        <div class="repair-limit-icon">&#9888;</div>
        <h2>Formularlimit erreicht</h2>
        <p>Dieses Formular ist momentan nicht verf&uuml;gbar. Bitte kontaktieren Sie den Anbieter direkt.</p>
        <?php if ($store->phone): ?>
            <a href="tel:<?php echo esc_attr($store->phone); ?>" class="repair-btn-phone">
                <i class="ri-phone-line"></i> <?php echo esc_html($store->phone); ?>
            </a>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- Bonus badge -->
    <div class="repair-bonus-badge">
        <i class="ri-gift-line"></i>
        <span>+<?php echo $points; ?> PunktePass Bonuspunkte f&uuml;r dieses Formular!</span>
    </div>

    <!-- Form -->
    <form id="repair-form" class="repair-form" autocomplete="off">
        <input type="hidden" name="action" value="ppv_repair_submit">
        <input type="hidden" name="nonce" value="<?php echo $nonce; ?>">
        <input type="hidden" name="store_id" value="<?php echo $store_id; ?>">

        <!-- Step 1: Customer info -->
        <div class="repair-section" id="step-customer">
            <div class="repair-section-title">
                <span class="repair-step-num">1</span>
                <h2>Ihre Daten</h2>
            </div>

            <div class="repair-field">
                <label for="rf-name">Name *</label>
                <input type="text" id="rf-name" name="customer_name" required placeholder="Vor- und Nachname">
            </div>

            <div class="repair-field">
                <label for="rf-email">E-Mail *</label>
                <input type="email" id="rf-email" name="customer_email" required placeholder="ihre@email.de">
            </div>

            <div class="repair-field">
                <label for="rf-phone">Telefon</label>
                <input type="tel" id="rf-phone" name="customer_phone" placeholder="+49 123 456789">
            </div>
        </div>

        <!-- Step 2: Device info -->
        <div class="repair-section" id="step-device">
            <div class="repair-section-title">
                <span class="repair-step-num">2</span>
                <h2>Ger&auml;teinformationen</h2>
            </div>

            <div class="repair-row">
                <div class="repair-field">
                    <label for="rf-brand">Marke</label>
                    <select id="rf-brand" name="device_brand">
                        <option value="">Bitte w&auml;hlen</option>
                        <option value="Apple">Apple</option>
                        <option value="Samsung">Samsung</option>
                        <option value="Huawei">Huawei</option>
                        <option value="Xiaomi">Xiaomi</option>
                        <option value="Google">Google</option>
                        <option value="OnePlus">OnePlus</option>
                        <option value="Sony">Sony</option>
                        <option value="OPPO">OPPO</option>
                        <option value="Nokia">Nokia</option>
                        <option value="Motorola">Motorola</option>
                        <option value="LG">LG</option>
                        <option value="Andere">Andere</option>
                    </select>
                </div>
                <div class="repair-field">
                    <label for="rf-model">Modell</label>
                    <input type="text" id="rf-model" name="device_model" placeholder="z.B. iPhone 15 Pro">
                </div>
            </div>

            <div class="repair-row">
                <div class="repair-field">
                    <label for="rf-imei">IMEI (optional)</label>
                    <input type="text" id="rf-imei" name="device_imei" placeholder="15-stellige IMEI-Nummer">
                </div>
                <div class="repair-field">
                    <label for="rf-pattern">Entsperrcode / Muster</label>
                    <input type="text" id="rf-pattern" name="device_pattern" placeholder="PIN oder Muster">
                </div>
            </div>
        </div>

        <!-- Step 3: Problem -->
        <div class="repair-section" id="step-problem">
            <div class="repair-section-title">
                <span class="repair-step-num">3</span>
                <h2>Problembeschreibung</h2>
            </div>

            <div class="repair-field">
                <label for="rf-problem">Was ist kaputt? *</label>
                <textarea id="rf-problem" name="problem_description" required rows="4" placeholder="Beschreiben Sie das Problem m&ouml;glichst genau..."></textarea>
            </div>

            <div class="repair-field">
                <label>Mitgegebenes Zubeh&ouml;r</label>
                <div class="repair-accessories">
                    <label class="repair-checkbox"><input type="checkbox" name="acc[]" value="charger"> Ladekabel</label>
                    <label class="repair-checkbox"><input type="checkbox" name="acc[]" value="case"> H&uuml;lle / Case</label>
                    <label class="repair-checkbox"><input type="checkbox" name="acc[]" value="sim"> SIM-Karte</label>
                    <label class="repair-checkbox"><input type="checkbox" name="acc[]" value="sd"> SD-Karte</label>
                    <label class="repair-checkbox"><input type="checkbox" name="acc[]" value="screen_protector"> Schutzfolie</label>
                </div>
            </div>
        </div>

        <!-- Terms -->
        <div class="repair-terms">
            <label class="repair-checkbox">
                <input type="checkbox" id="rf-terms" required>
                Ich akzeptiere die <a href="/repair/<?php echo $slug; ?>/datenschutz" target="_blank">Datenschutzerkl&auml;rung</a> und <a href="/repair/<?php echo $slug; ?>/agb" target="_blank">AGB</a>
            </label>
        </div>

        <!-- Submit -->
        <button type="submit" id="rf-submit" class="repair-submit">
            <span class="repair-submit-text"><i class="ri-send-plane-fill"></i> Reparatur einreichen</span>
            <span class="repair-submit-loading" style="display:none"><i class="ri-loader-4-line ri-spin"></i> Wird gesendet...</span>
        </button>

        <div id="rf-error" class="repair-error" style="display:none"></div>
    </form>
    <?php endif; ?>

    <!-- Success screen (hidden by default) -->
    <div id="repair-success" class="repair-success" style="display:none">
        <div class="repair-success-animation">
            <div class="repair-success-check">&#10003;</div>
        </div>
        <h2>Vielen Dank!</h2>
        <p>Ihr Reparaturauftrag wurde erfolgreich eingereicht.</p>

        <div id="repair-points-card" class="repair-points-card" style="display:none">
            <div class="repair-points-badge">
                <span class="repair-points-plus">+</span>
                <span id="repair-points-count" class="repair-points-count">0</span>
            </div>
            <div class="repair-points-label">PunktePass Bonuspunkte</div>
            <div id="repair-points-total" class="repair-points-total"></div>
        </div>

        <p class="repair-success-info">Sie erhalten eine Best&auml;tigung per E-Mail. Wir melden uns, sobald Ihr Ger&auml;t fertig ist.</p>

        <a href="/repair/<?php echo $slug; ?>" class="repair-btn-back">Neues Formular ausf&uuml;llen</a>
    </div>

    <!-- Footer -->
    <div class="repair-footer">
        <div class="repair-footer-links">
            <a href="/repair/<?php echo $slug; ?>/datenschutz">Datenschutz</a>
            <a href="/repair/<?php echo $slug; ?>/agb">AGB</a>
            <a href="/repair/<?php echo $slug; ?>/impressum">Impressum</a>
        </div>
        <div class="repair-footer-powered">
            Powered by <a href="https://punktepass.de" target="_blank">PunktePass</a>
        </div>
    </div>
</div>

<script>
(function() {
    var form = document.getElementById('repair-form');
    if (!form) return;

    var submitBtn = document.getElementById('rf-submit');
    var submitText = form.querySelector('.repair-submit-text');
    var submitLoading = form.querySelector('.repair-submit-loading');
    var errorDiv = document.getElementById('rf-error');
    var successDiv = document.getElementById('repair-success');

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // Validate
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Collect accessories
        var accessories = [];
        form.querySelectorAll('input[name="acc[]"]:checked').forEach(function(cb) {
            accessories.push(cb.value);
        });

        // Show loading
        submitBtn.disabled = true;
        submitText.style.display = 'none';
        submitLoading.style.display = 'inline-flex';
        errorDiv.style.display = 'none';

        // Build form data
        var fd = new FormData(form);
        fd.set('accessories', JSON.stringify(accessories));

        fetch('<?php echo esc_js($ajax_url); ?>', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                // Hide form, show success
                form.style.display = 'none';
                document.querySelector('.repair-bonus-badge').style.display = 'none';
                successDiv.style.display = 'block';

                // Show points card
                var d = data.data;
                if (d.points_added > 0) {
                    document.getElementById('repair-points-card').style.display = 'block';
                    document.getElementById('repair-points-count').textContent = d.points_added;
                    if (d.total_points) {
                        var remaining = Math.max(0, 4 - d.total_points);
                        document.getElementById('repair-points-total').textContent =
                            'Gesamt: ' + d.total_points + ' / 4 Punkte' +
                            (remaining > 0 ? ' — noch ' + remaining + ' bis 10€ Rabatt!' : ' — 10€ Rabatt einlösbar!');
                    }
                }

                // Confetti effect
                createConfetti();

                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                errorDiv.textContent = data.data?.message || 'Ein Fehler ist aufgetreten.';
                errorDiv.style.display = 'block';
            }
        })
        .catch(function() {
            errorDiv.textContent = 'Verbindungsfehler. Bitte versuchen Sie es erneut.';
            errorDiv.style.display = 'block';
        })
        .finally(function() {
            submitBtn.disabled = false;
            submitText.style.display = 'inline-flex';
            submitLoading.style.display = 'none';
        });
    });

    // Simple confetti
    function createConfetti() {
        var colors = ['#667eea', '#764ba2', '#f59e0b', '#10b981', '#ef4444', '#3b82f6'];
        for (var i = 0; i < 50; i++) {
            var confetti = document.createElement('div');
            confetti.className = 'confetti-piece';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDelay = Math.random() * 2 + 's';
            confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
            document.body.appendChild(confetti);
            setTimeout(function() { confetti.remove(); }, 4000);
        }
    }
})();
</script>

</body>
</html>
        <?php
        return ob_get_clean();
    }
}
