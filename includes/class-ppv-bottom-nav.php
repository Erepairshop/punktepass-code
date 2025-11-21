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
        if ($is_user_page): ?>
            <nav class="ppv-bottom-nav">
                <a href="/user_dashboard" class="nav-item" data-navlink="true" title="Dashboard"><i class="ri-home-smile-2-line"></i></a>
                <a href="/meine-punkte" class="nav-item" data-navlink="true" title="Meine Punkte"><i class="ri-donut-chart-line"></i></a>
                <a href="/belohnungen" class="nav-item" data-navlink="true" title="Belohnungen"><i class="ri-coupon-3-line"></i></a>
                <a href="/einstellungen" class="nav-item" data-navlink="true" title="Einstellungen"><i class="ri-equalizer-line"></i></a>
            </nav>
        <?php
        // --- Händler / POS navigáció ---
        elseif ($is_vendor || $is_pos): ?>
            <nav class="ppv-bottom-nav">
                <a href="/qr-center" class="nav-item" data-navlink="true" title="Start"><i class="ri-home-smile-2-line"></i></a>
                <a href="/rewards" class="nav-item" data-navlink="true" title="Rewards"><i class="ri-coupon-3-line"></i></a>
                <a href="/mein-profil" class="nav-item" data-navlink="true" title="Profil"><i class="ri-user-3-line"></i></a>
                <a href="/statistik" class="nav-item" data-navlink="true" title="Statistik"><i class="ri-bar-chart-line"></i></a>
                <a href="/support" class="nav-item" data-navlink="true" title="Support"><i class="ri-customer-service-2-line"></i></a>
            </nav>
        <?php
        // --- Alap user nav ---
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
        }";
    }

    /** ============================================================
     * JS – Aktív ikon kijelölés + Link navigation
     * ============================================================ */
    private static function inline_js() {
        return "
        jQuery(document).ready(function(\$) {
            console.log('✅ Bottom Nav aktiv');
            const currentPath = window.location.pathname.replace(/\/+$/, '');
            
            // Aktív ikon megjelölése
            \$('.ppv-bottom-nav .nav-item').each(function() {
                const href = \$(this).attr('href').replace(/\/+$/, '');
                if (currentPath === href || currentPath.startsWith(href)) {
                    \$(this).addClass('active');
                }
            });
            
            // ✅ NORMÁLIS LINKEK MŰKÖDJENEK! (NE INTERCEPTÁLÓDJON)
            \$('.ppv-bottom-nav .nav-item[data-navlink]').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const href = \$(this).attr('href');
                console.log('🔗 Bottom Nav Navigate to:', href);
                setTimeout(function() {
                    window.location.href = href;
                }, 50);
                return false;
            });
            
            // Smooth icon hover feedback
            \$('.ppv-bottom-nav .nav-item').on('touchstart mousedown', function() {
                \$(this).addClass('touch');
            }).on('touchend mouseup', function() {
                \$(this).removeClass('touch');
            });
        });
        ";
    }
}

PPV_Bottom_Nav::hooks();