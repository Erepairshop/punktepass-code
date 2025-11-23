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
        // Add module type to Turbo script (only once)
        static $turbo_filter_added = false;
        if (!$turbo_filter_added) {
            add_filter('script_loader_tag', function($tag, $handle) {
                if ($handle === 'turbo') {
                    return str_replace('<script ', '<script type="module" ', $tag);
                }
                return $tag;
            }, 10, 2);
            $turbo_filter_added = true;
        }

        wp_register_style('ppv-bottom-nav', false);
        wp_add_inline_style('ppv-bottom-nav', self::inline_css());
        wp_enqueue_style('ppv-bottom-nav');

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

                    <label class="ppv-support-label">E-Mail Adresse <span class="ppv-required">*</span></label>
                    <input type="email" id="ppv-support-email" class="ppv-support-input" placeholder="ihre@email.de" required>

                    <label class="ppv-support-label">Telefonnummer <span class="ppv-required">*</span></label>
                    <input type="tel" id="ppv-support-phone" class="ppv-support-input" placeholder="+49 123 456789" required>

                    <label class="ppv-support-label">Problembeschreibung <span class="ppv-required">*</span></label>
                    <textarea id="ppv-support-desc" class="ppv-support-input" placeholder="Beschreiben Sie Ihr Anliegen..." rows="3"></textarea>

                    <label class="ppv-support-label">Priorität</label>
                    <select id="ppv-support-priority" class="ppv-support-input">
                        <option value="normal">Normal</option>
                        <option value="urgent">Dringend</option>
                        <option value="low">Niedrig</option>
                    </select>

                    <div id="ppv-support-msg" class="ppv-support-message"></div>

                    <div class="ppv-support-buttons">
                        <button id="ppv-support-send" class="ppv-support-btn-submit">
                            <i class="ri-send-plane-line"></i> Senden
                        </button>
                        <button id="ppv-support-close" class="ppv-support-btn-cancel">
                            <i class="ri-close-line"></i> Abbrechen
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
     * CSS – Neon Blue Icon Bar
     * ============================================================ */
    private static function inline_css() {
        return "
        .ppv-bottom-nav {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 12px 0;
            background: rgba(255,255,255,0.96);
            border-top: 1px solid rgba(0,0,0,0.08);
            backdrop-filter: blur(12px);
            z-index: 9999;
        }
        .ppv-bottom-nav .nav-item {
            flex: 1;
            text-align: center;
            color: #667eea;
            font-size: 26px;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: all 0.25s ease;
            text-decoration: none;
            cursor: pointer;
        }
        .ppv-bottom-nav .nav-item.active {
            color: #764ba2;
            transform: scale(1.18);
            text-shadow: 0 0 10px rgba(102,126,234,0.3);
        }
        .ppv-bottom-nav .nav-item:hover {
            color: #764ba2;
        }
        .ppv-bottom-nav .nav-item.touch {
            transform: scale(0.95);
            opacity: 0.8;
        }
        
        /* 🌙 DARK MODE */
        body.ppv-dark .ppv-bottom-nav {
            background: rgba(10,18,28,0.95);
            border-top-color: rgba(102,126,234,0.2);
        }
        body.ppv-dark .ppv-bottom-nav .nav-item {
            color: #667eea;
        }
        body.ppv-dark .ppv-bottom-nav .nav-item.active {
            color: #00eaff;
            text-shadow: 0 0 10px rgba(0,234,255,0.5);
        }
        body.ppv-dark .ppv-bottom-nav .nav-item:hover {
            color: #00eaff;
        }
        
        @supports(padding:max(0px)) {
            .ppv-bottom-nav {
                padding-bottom: max(12px, env(safe-area-inset-bottom));
            }
        }

        /* 🚀 Turbo Loading Indicator */
        .turbo-progress-bar {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2, #00eaff);
            z-index: 99999;
            transition: width 0.3s ease;
        }

        body.turbo-loading::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2, #00eaff);
            background-size: 200% 100%;
            animation: turbo-loading 1s ease-in-out infinite;
            z-index: 99999;
        }

        @keyframes turbo-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }";
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
                console.log('✅ Bottom Nav aktiv (Turbo)');
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

                // ============ SUPPORT MODAL ============
                const \$modal = \$('#ppv-support-modal');
                const \$msg = \$('#ppv-support-msg');

                // Unbind previous handlers to prevent duplicates
                \$('#ppv-support-nav-btn').off('click');
                \$('#ppv-support-close').off('click');
                \$modal.off('click');
                \$('#ppv-support-send').off('click');

                // Open modal
                \$('#ppv-support-nav-btn').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    \$modal.addClass('show');
                    \$('#ppv-support-email').val('');
                    \$('#ppv-support-phone').val('');
                    \$('#ppv-support-desc').val('');
                    \$('#ppv-support-email').focus();
                    \$msg.removeClass('show');
                });

                // Close modal
                \$('#ppv-support-close').on('click', function() {
                    \$modal.removeClass('show');
                });

                // Close on backdrop click
                \$modal.on('click', function(e) {
                    if (e.target === this) {
                        \$modal.removeClass('show');
                    }
                });

                // Send ticket
                \$('#ppv-support-send').on('click', function() {
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

                    \$btn.prop('disabled', true).html('<i class=\"ri-loader-4-line ri-spin\"></i> Senden...');
                    \$msg.removeClass('show');

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
                document.addEventListener('DOMContentLoaded', initAll);
            } else {
                initAll();
            }

            // 🚀 Reinitialize after Turbo navigation
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