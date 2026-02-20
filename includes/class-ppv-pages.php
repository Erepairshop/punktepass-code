<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('PPV_Pages', false)) {

class PPV_Pages {
    
   /** ============================================================
 *  🔹 iOS PWA Render Fix (Elementor invisibility)
 * ============================================================ */
public static function force_visible() {
    if (wp_is_mobile()) {
        echo '<style>
            @supports (-webkit-touch-callout: none) {
                body.ppv-app-mode .elementor,
                body.ppv-app-mode .ppv-dashboard-netto,
                body.ppv-app-mode #ppv-my-points-app {
                    display:block!important;
                    opacity:1!important;
                    visibility:visible!important;
                    min-height:100vh!important;
                    overflow:auto!important;
                    -webkit-overflow-scrolling:touch!important;
                    z-index:2!important;
                    background:var(--pp-bg,transparent)!important;
                }
                /* ✅ iOS: Prevent flash during Turbo page transitions */
                body.ppv-app-mode.turbo-loading .ppv-dashboard-netto,
                body.ppv-app-mode.turbo-loading #ppv-my-points-app {
                    opacity:1!important;
                    visibility:visible!important;
                    transition:none!important;
                }
            }
        </style>';
    }
}

    /** 🔹 Hooks initialisieren */
    public static function hooks() {
        add_shortcode('pp_store_dashboard', [__CLASS__, 'shortcode_dashboard']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_footer', [__CLASS__, 'force_visible']);
    }
    
    /** 🔹 Dashboard Shortcode */
    public static function shortcode_dashboard() {
        ob_start();
        
        // 🔹 Initialize language system
        if (class_exists('PPV_Lang')) {
            $lang = PPV_Lang::current();
        }

        // 🔹 WordPress user session check
        if (!function_exists('wp_get_current_user')) {
            require_once(ABSPATH . 'wp-includes/pluggable.php');
        }
        $current_user = wp_get_current_user();

        // 🔹 User login check
        if (!$current_user || empty($current_user->user_email)) {
            echo '<div style="text-align:center; margin:20px; color:#dc3545;">⚠️ ' . PPV_Lang::t('not_logged_in_vendor') . '</div>';
            return ob_get_clean();
        }

        // 🔹 Tab determination
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

        echo '<div class="ppv-dashboard">';
        
        // 🔹 Activation banner
        ?>
        <div id="ppv-activation-banner" style="display:none;">
            ✅ <?php echo PPV_Lang::t('account_activated_success'); ?>
        </div>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get("activated") === "1") {
                const banner = document.getElementById("ppv-activation-banner");
                if (banner) {
                    banner.style.display = "block";
                    banner.classList.add("show");
                    setTimeout(() => banner.classList.remove("show"), 4500);
                    setTimeout(() => banner.style.display = "none", 5000);
                }
            }
        });
        </script>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const url = new URL(window.location);
            if (url.searchParams.get("activated") === "1") {
                setTimeout(() => {
                    url.searchParams.delete("activated");
                    window.history.replaceState({}, document.title, url.pathname + url.search);
                }, 3000);
            }
        });
        </script>

        <style>
        #ppv-activation-banner {
            position: relative;
            margin: 10px auto 20px auto;
            padding: 12px 20px;
            border-radius: 10px;
            background-color: #28a745;
            color: #fff;
            font-weight: 500;
            text-align: center;
            max-width: 600px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
            opacity: 0;
            transition: all 0.4s ease;
        }
        #ppv-activation-banner.show {
            opacity: 1;
            transform: translateY(5px);
        }
        </style>
        <?php

        /** 🔹 Vendor status display */
        global $wpdb;
        $current_user = wp_get_current_user();
        $table = $wpdb->prefix . 'ppv_stores';

        if ($current_user && $current_user->user_email) {
            $status = $wpdb->get_row($wpdb->prepare(
                "SELECT subscription_status, trial_end FROM $table WHERE email=%s",
                $current_user->user_email
            ));

            if ($status) {
                $trial_end = $status->trial_end ? date_i18n('d.m.Y', strtotime($status->trial_end)) : null;
                $status_text = '';
                $color = '#0073aa';

                if ($status->subscription_status === 'active') {
                    $status_text = '🟢 ' . PPV_Lang::t('subscription_active');
                    $color = '#28a745';
                } elseif ($status->subscription_status === 'trial') {
                    $status_text = '🟡 ' . PPV_Lang::t('trial_active');
                    if ($trial_end) $status_text .= ' – ' . PPV_Lang::t('trial_until') . ' ' . $trial_end;
                    $color = '#ffc107';
                } else {
                    $status_text = '🔴 ' . PPV_Lang::t('no_active_subscription');
                    $color = '#dc3545';
                }

                echo '<div class="ppv-status-banner" style="border-left:5px solid ' . esc_attr($color) . ';">' . esc_html($status_text) . '</div>';
            }
        }
        ?>

        <style>
        .ppv-status-banner {
            margin: 10px auto 20px auto;
            background: #fff;
            padding: 10px 16px;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
            font-weight: 500;
            color: #333;
            max-width: 600px;
        }
        </style>

        <?php
        echo '<h2>' . PPV_Lang::t('vendor_dashboard') . '</h2>';

        // 🔹 Tab routing
        if ($tab === 'profil') {
            if (class_exists('PPV_Profile_Lite')) {
                PPV_Profile_Lite::render_form();
            } else {
                echo '<p>' . PPV_Lang::t('module_not_found_profile') . '</p>';
            }

        } elseif ($tab === 'stats') {
            if (file_exists(PPV_PLUGIN_DIR . 'includes/class-ppv-stats.php')) {
                include_once PPV_PLUGIN_DIR . 'includes/class-ppv-stats.php';
                if (class_exists('PPV_Stats')) {
                    PPV_Stats::render_template_for_dashboard();
                }
            } else {
                echo '<p>' . PPV_Lang::t('module_not_found_stats') . '</p>';
            }

        } elseif ($tab === 'qr') {
            if (file_exists(PPV_PLUGIN_DIR . 'includes/class-ppv-qr.php')) {
                include_once PPV_PLUGIN_DIR . 'includes/class-ppv-qr.php';
                if (class_exists('PPV_QR')) {
                    PPV_QR::render_template_for_dashboard();
                }
            } else {
                echo '<p>' . PPV_Lang::t('module_not_found_qr') . '</p>';
            }

        } elseif ($tab === 'rewards') {
            if (file_exists(PPV_PLUGIN_DIR . 'includes/class-ppv-rewards.php')) {
                include_once PPV_PLUGIN_DIR . 'includes/class-ppv-rewards.php';
                if (class_exists('PPV_Rewards')) {
                    PPV_Rewards::render_template_for_dashboard();
                }
            } else {
                echo '<p>' . PPV_Lang::t('module_not_found_rewards') . '</p>';
            }

        } else {
            // 🔹 Overview
            ?>
            <div class="ppv-dashboard-grid">
                <div class="ppv-card">
                    <h3><?php echo PPV_Lang::t('my_profile'); ?></h3>
                    <p><?php echo PPV_Lang::t('edit_vendor_profile'); ?></p>
                    <a href="?tab=profil" class="ppv-btn"><?php echo PPV_Lang::t('open'); ?></a>
                </div>

                <div class="ppv-card">
                    <h3><?php echo PPV_Lang::t('stats'); ?></h3>
                    <p><?php echo PPV_Lang::t('analyze_points'); ?></p>
                    <a href="?tab=stats" class="ppv-btn"><?php echo PPV_Lang::t('view'); ?></a>
                </div>

                <div class="ppv-card">
                    <h3><?php echo PPV_Lang::t('qr_center'); ?></h3>
                    <p><?php echo PPV_Lang::t('generate_qr'); ?></p>
                    <a href="?tab=qr" class="ppv-btn"><?php echo PPV_Lang::t('start'); ?></a>
                </div>

                <div class="ppv-card">
                    <h3><?php echo PPV_Lang::t('rewards'); ?></h3>
                    <p><?php echo PPV_Lang::t('manage_rewards'); ?></p>
                    <a href="?tab=rewards" class="ppv-btn"><?php echo PPV_Lang::t('open'); ?></a>
                </div>
            </div>
            <?php
        }

        echo '</div>';

        return ob_get_clean();
    }

    /** 🔹 CSS + JS betöltés (FIXED - SKIP LOGIN PAGE) */
    public static function enqueue_assets() {
        // ⛔ LOGIN OLDAL SKIP
        if (function_exists('ppv_is_login_page') && ppv_is_login_page()) {
            return; // Ne töltse be a theme CSS-t login oldalon!
        }
        
        // ⛔ SIGNUP OLDAL SKIP
    if (function_exists('ppv_is_signup_page') && ppv_is_signup_page()) {
        return;
    }
        
        // ⛔ HANDLER SESSION SKIP
        if (function_exists('ppv_is_handler_session') && ppv_is_handler_session()) {
            return; // Handler-nek saját CSS-e van
        }

        // 🧹 Minden PPV CSS törlése (kivéve whitelist)
        $whitelist = ['ppv-core', 'ppv-layout', 'ppv-components', 'ppv-bottom-nav', 'ppv-theme-light', 'ppv-login-light', 'ppv-handler-light', 'ppv-handler-dark'];
        foreach (wp_styles()->queue as $handle) {
            if (strpos($handle, 'ppv-') === 0 && !in_array($handle, $whitelist)) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }

        // 🔹 New modular CSS architecture
        $v = defined('PPV_VERSION') ? PPV_VERSION : time();
        wp_enqueue_style('ppv-core', PPV_PLUGIN_URL . 'assets/css/ppv-core.css', [], $v);
        wp_enqueue_style('ppv-layout', PPV_PLUGIN_URL . 'assets/css/ppv-layout.css', ['ppv-core'], $v);
        wp_enqueue_style('ppv-components', PPV_PLUGIN_URL . 'assets/css/ppv-components.css', ['ppv-core'], $v);

        // 🔹 Legacy theme (still needed during migration)
        wp_enqueue_style(
            'ppv-theme-light',
            PPV_PLUGIN_URL . 'assets/css/ppv-theme-light.css',
            ['ppv-core'],
            $v
        );

        // 🔹 Globális JS
        wp_enqueue_script(
            'ppv-theme',
            PPV_PLUGIN_URL . 'assets/js/ppv-theme-handler.js',
            [],
            defined('PPV_VERSION') ? PPV_VERSION : time(),
            true
        );

        // ❌ REMOVED: ppv-user-dashboard.js - already loaded by class-ppv-user-dashboard.php
        // This was causing duplicate script loading and "PPV_TRANSLATIONS already declared" error
    }
}

}

PPV_Pages::hooks();