<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Bottom Navigation (v6.7 Fixed)
 * ✅ User + Händler kompatibilis
 * ✅ Session Engine v4 támogatás
 * ✅ Multi-Filiale Safe
 * ✅ POS-Tablet kompatibilis
 * ✅ Neon Blue Theme
 * ✅ Linkek NEM interceptálódnak!
 * Author: PunktePass / Erik Borota
 */

class PPV_Bottom_Nav {

    public static function hooks() {
        add_shortcode('ppv_bottom_nav', [__CLASS__, 'render_nav']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 99);
        add_action('wp_footer', [__CLASS__, 'inject_context'], 1);
    }

    /** ============================================================
     * Assets (CSS + JS)
     * ============================================================ */
    public static function enqueue_assets() {
        wp_enqueue_style(
            'remixicons',
            'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css?ver=stable',
            [],
            null
        );

        // 🚀 Turbo.js for instant page transitions
        wp_enqueue_script(
            'turbo',
            'https://cdn.jsdelivr.net/npm/@hotwired/turbo@8.0.4/dist/turbo.es2017-esm.js',
            [],
            null,
            false
        );
        // Add module type to Turbo script
        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'turbo') {
                return str_replace('<script ', '<script type="module" ', $tag);
            }
            return $tag;
        }, 10, 2);

        wp_enqueue_style(
            'ppv-bottom-nav',
            plugins_url('assets/css/ppv-bottom-nav.css', dirname(__FILE__)),
            [],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/ppv-bottom-nav.css')
        );

        wp_register_script('ppv-bottom-nav', false, ['jquery'], time(), true);
        wp_add_inline_script('ppv-bottom-nav', self::inline_js());
        wp_enqueue_script('ppv-bottom-nav');
    }

    /** ============================================================
     * Global Store Context Injection (Session v4 Safe)
     * ============================================================ */
    public static function inject_context() {
        $store = $GLOBALS['ppv_active_store'] ?? (class_exists('PPV_Session') ? PPV_Session::current_store() : null);

        $store_id  = $store->id        ?? 0;
        $store_key = $store->store_key ?? '';
        $pos       = (function_exists('ppv_is_pos') && ppv_is_pos()) ? 'true' : 'false';
        $token     = sanitize_text_field($_COOKIE['ppv_pos_token'] ?? '');

        echo '<script>window.PPV_PROFILE = ' . wp_json_encode([
            'store_id'  => intval($store_id),
            'store_key' => $store_key,
            'pos'       => ($pos === 'true'),
            'token'     => $token,
            'url'       => get_site_url(),
            'debug'     => false,
        ]) . ';</script>';
    }

    /** ============================================================
     * Render Navigation (User vagy Händler)
     * ============================================================ */
    public static function render_nav() {
        // Session indítása ha kell
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $user  = wp_get_current_user();
        $roles = (array)$user->roles;

        // 🔹 Session alapú vendor-ellenőrzés
        $is_vendor = false;
        $is_pos    = function_exists('ppv_is_pos') && ppv_is_pos();

        // 1. WordPress role check
        if (in_array('vendor', $roles) || in_array('pp_vendor', $roles) || current_user_can('manage_options')) {
            $is_vendor = true;
        }

        // 2. PPV SESSION check - KRITIKUS a trial usereknek!
        if (!$is_vendor && !empty($_SESSION['ppv_user_type']) && in_array($_SESSION['ppv_user_type'], ['vendor', 'store', 'handler', 'admin'])) {
            $is_vendor = true;
        }

        // 2b. Trial handler check - ppv_vendor_store_id
        if (!$is_vendor && !empty($_SESSION['ppv_vendor_store_id'])) {
            $is_vendor = true;
        }

        // 3. Global store check
        if (!$is_vendor && isset($GLOBALS['ppv_active_store']) && !empty($GLOBALS['ppv_active_store']->id)) {
            $is_vendor = true;
        }

        // 4. PPV_Session check
        if (!$is_vendor && class_exists('PPV_Session')) {
            $store = PPV_Session::current_store();
            if (!empty($store) && !empty($store->id)) {
                $is_vendor = true;
            }
        }

        // 🔹 URL ellenőrzés - ha user_dashboard, akkor USER nav!
        $current_path = $_SERVER['REQUEST_URI'] ?? '';
        $is_user_page = (
            strpos($current_path, '/user_dashboard') !== false ||
            strpos($current_path, '/meine-punkte') !== false ||
            strpos($current_path, '/belohnungen') !== false ||
            strpos($current_path, '/einstellungen') !== false ||
            strpos($current_path, '/punkte') !== false
        );

        ob_start();

        // --- User navigáció (prioritás!) ---
        // 🚀 data-turbo="false" for vendors visiting user pages - forces full reload
        $turbo_attr = $is_vendor ? 'data-turbo="false"' : '';
        if ($is_user_page): ?>
            <nav class="ppv-bottom-nav">
                <a href="/user_dashboard" class="nav-item" <?php echo $turbo_attr; ?> data-navlink="true" title="Dashboard"><i class="ri-home-smile-2-line"></i></a>
                <a href="/meine-punkte" class="nav-item" <?php echo $turbo_attr; ?> data-navlink="true" title="Meine Punkte"><i class="ri-donut-chart-line"></i></a>
                <a href="/belohnungen" class="nav-item" <?php echo $turbo_attr; ?> data-navlink="true" title="Belohnungen"><i class="ri-coupon-3-line"></i></a>
                <a href="/einstellungen" class="nav-item" <?php echo $turbo_attr; ?> data-navlink="true" title="Einstellungen"><i class="ri-equalizer-line"></i></a>
            </nav>
        <?php
        // --- Händler / POS navigáció ---
        elseif ($is_vendor || $is_pos): ?>
            <nav class="ppv-bottom-nav">
                <a href="/qr-center" class="nav-item" data-navlink="true" title="Start"><i class="ri-home-smile-2-line"></i></a>
                <a href="/rewards" class="nav-item" data-navlink="true" title="Rewards"><i class="ri-coupon-3-line"></i></a>
                <a href="/mein-profil" class="nav-item" data-navlink="true" title="Profil"><i class="ri-user-3-line"></i></a>
                <a href="/statistik" class="nav-item" data-navlink="true" title="Statistik"><i class="ri-bar-chart-line"></i></a>
                <a href="#" class="nav-item" id="ppv-support-nav-btn" title="Support"><i class="ri-customer-service-2-line"></i></a>
            </nav>

            <!-- Support Modal -->
            <div id="ppv-support-modal" class="ppv-support-modal">
                <div class="ppv-support-modal-content">
                    <h3 class="ppv-support-modal-title">
                        <i class="ri-customer-service-2-line"></i> Support anfragen
                    </h3>
                    <p class="ppv-support-modal-description">
                        Beschreiben Sie Ihr Problem. Wir melden uns schnellstmöglich.
                    </p>

                    <input type="email" id="ppv-support-email" class="ppv-support-input" placeholder="E-Mail Adresse *" required>
                    <input type="tel" id="ppv-support-phone" class="ppv-support-input" placeholder="Telefonnummer *" required>
                    <textarea id="ppv-support-desc" class="ppv-support-input" placeholder="Problembeschreibung *" rows="4"></textarea>

                    <select id="ppv-support-priority" class="ppv-support-input">
                        <option value="normal">Priorität: Normal</option>
                        <option value="urgent">Priorität: Dringend</option>
                        <option value="low">Priorität: Niedrig</option>
                    </select>

                    <div id="ppv-support-msg" class="ppv-support-message"></div>

                    <div class="ppv-support-buttons">
                        <button id="ppv-support-send" class="ppv-support-btn-submit">
                            <i class="ri-send-plane-line"></i> Senden
                        </button>
                        <button id="ppv-support-close" class="ppv-support-btn-cancel">
                            Abbrechen
                        </button>
                    </div>
                </div>
            </div>
        <?php
        // --- Alap user nav (basic users - Turbo enabled) ---
        else: ?>
            <nav class="ppv-bottom-nav">
                <a href="/user_dashboard" class="nav-item" data-navlink="true" title="Dashboard"><i class="ri-home-smile-2-line"></i></a>
                <a href="/meine-punkte" class="nav-item" data-navlink="true" title="Meine Punkte"><i class="ri-donut-chart-line"></i></a>
                <a href="/belohnungen" class="nav-item" data-navlink="true" title="Belohnungen"><i class="ri-coupon-3-line"></i></a>
                <a href="/einstellungen" class="nav-item" data-navlink="true" title="Einstellungen"><i class="ri-equalizer-line"></i></a>
            </nav>
        <?php
        endif;

        return ob_get_clean();
    }


    /** ============================================================
     * JS – Aktív ikon kijelölés + Turbo Navigation
     * ============================================================ */
    private static function inline_js() {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('ppv_support_nonce');

        return "
        (function() {
            // 🚀 Initialize everything
            function initAll() {
                const \$ = jQuery;
                const currentPath = window.location.pathname.replace(/\/+\$/, '');

                // Aktív ikon megjelölése
                \$('.ppv-bottom-nav .nav-item').removeClass('active');
                \$('.ppv-bottom-nav .nav-item').each(function() {
                    const href = \$(this).attr('href');
                    if (href && href !== '#') {
                        const cleanHref = href.replace(/\/+\$/, '');
                        if (currentPath === cleanHref || currentPath.startsWith(cleanHref)) {
                            \$(this).addClass('active');
                        }
                    }
                });

                // Smooth icon hover feedback
                \$('.ppv-bottom-nav .nav-item').off('touchstart mousedown touchend mouseup');
                \$('.ppv-bottom-nav .nav-item').on('touchstart mousedown', function() {
                    \$(this).addClass('touch');
                }).on('touchend mouseup', function() {
                    \$(this).removeClass('touch');
                });

            }

            // ============ SUPPORT MODAL (Event Delegation - Turbo Safe) ============
            let supportEventsInitialized = false;
            function initSupportModal() {
                if (supportEventsInitialized) return;
                supportEventsInitialized = true;

                const \$ = jQuery;

                // Open modal - event delegation on body
                \$(document).on('click', '#ppv-support-nav-btn', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const \$modal = \$('#ppv-support-modal');
                    const \$msg = \$('#ppv-support-msg');
                    \$modal.addClass('show');
                    \$('#ppv-support-email').val('');
                    \$('#ppv-support-phone').val('');
                    \$('#ppv-support-desc').val('');
                    setTimeout(function() { \$('#ppv-support-email').focus(); }, 100);
                    \$msg.removeClass('show ppv-support-error ppv-support-success');
                });

                // Close modal
                \$(document).on('click', '#ppv-support-close', function() {
                    \$('#ppv-support-modal').removeClass('show');
                });

                // Close on backdrop click
                \$(document).on('click', '#ppv-support-modal', function(e) {
                    if (e.target === this) {
                        \$(this).removeClass('show');
                    }
                });

                // Send ticket
                \$(document).on('click', '#ppv-support-send', function() {
                    const \$modal = \$('#ppv-support-modal');
                    const \$msg = \$('#ppv-support-msg');
                    const email = \$('#ppv-support-email').val().trim();
                    const phone = \$('#ppv-support-phone').val().trim();
                    const desc = \$('#ppv-support-desc').val().trim();
                    const priority = \$('#ppv-support-priority').val();
                    const \$btn = \$(this);

                    if (!email) {
                        \$msg.removeClass('ppv-support-success').addClass('ppv-support-error show').text('Bitte geben Sie Ihre E-Mail Adresse ein.');
                        \$('#ppv-support-email').focus();
                        return;
                    }

                    const emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+\$/;
                    if (!emailRegex.test(email)) {
                        \$msg.removeClass('ppv-support-success').addClass('ppv-support-error show').text('Bitte geben Sie eine gültige E-Mail Adresse ein.');
                        \$('#ppv-support-email').focus();
                        return;
                    }

                    if (!phone) {
                        \$msg.removeClass('ppv-support-success').addClass('ppv-support-error show').text('Bitte geben Sie Ihre Telefonnummer ein.');
                        \$('#ppv-support-phone').focus();
                        return;
                    }

                    if (!desc) {
                        \$msg.removeClass('ppv-support-success').addClass('ppv-support-error show').text('Bitte beschreiben Sie Ihr Problem.');
                        \$('#ppv-support-desc').focus();
                        return;
                    }

                    \$btn.prop('disabled', true).html('<i class=\"ri-loader-4-line\"></i> Senden...');
                    \$msg.removeClass('show ppv-support-error ppv-support-success');

                    \$.ajax({
                        url: '{$ajax_url}',
                        type: 'POST',
                        data: {
                            action: 'ppv_submit_support_ticket',
                            email: email,
                            phone: phone,
                            description: desc,
                            priority: priority,
                            contact_method: 'email',
                            nonce: '{$nonce}'
                        },
                        success: function(res) {
                            if (res.success) {
                                \$msg.removeClass('ppv-support-error').addClass('ppv-support-success show').html('<i class=\"ri-checkbox-circle-line\"></i> Ticket gesendet!');
                                \$('#ppv-support-email').val('');
                                \$('#ppv-support-phone').val('');
                                \$('#ppv-support-desc').val('');
                                setTimeout(function() { \$modal.removeClass('show'); }, 1500);
                            } else {
                                \$msg.removeClass('ppv-support-success').addClass('ppv-support-error show').text(res.data?.message || 'Fehler');
                            }
                        },
                        error: function() {
                            \$msg.removeClass('ppv-support-success').addClass('ppv-support-error show').text('Netzwerkfehler');
                        },
                        complete: function() {
                            \$btn.prop('disabled', false).html('<i class=\"ri-send-plane-line\"></i> Senden');
                        }
                    });
                });
            }

            // Initialize on DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    initAll();
                    initSupportModal();
                });
            } else {
                initAll();
                initSupportModal();
            }

            // 🚀 Reinitialize nav after Turbo navigation (Support modal uses event delegation, no reinit needed)
            document.addEventListener('turbo:load', initAll);

            // 🚀 Show loading indicator
            document.addEventListener('turbo:before-fetch-request', function() {
                document.body.classList.add('turbo-loading');
            });
            document.addEventListener('turbo:render', function() {
                document.body.classList.remove('turbo-loading');
            });
        })();
        ";
    }
}

PPV_Bottom_Nav::hooks();