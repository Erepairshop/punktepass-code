<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass POS Diagnostic Helper
 * Diagnosztikai logok és állapotellenőrzések
 */

class PPV_POS_Diagnostic {

    public static function init() {
        add_action('init', [__CLASS__, 'maybe_start_session'], 1);
        add_action('wp_ajax_ppv_pos_diag', [__CLASS__, 'ajax_diagnostic']);
        // 🔒 Removed wp_ajax_nopriv - authentication required for diagnostics
    }

    /** 
     * ✅ Session csak ha NEM POS admin és nincs már aktív
     */
    public static function maybe_start_session() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $is_pos = str_contains($uri, 'pos-admin') || (isset($_POST['action']) && str_contains($_POST['action'], 'ppv_pos_'));

        if ($is_pos) {
            ppv_log("🚫 [POS DIAG] session letiltva (POS context detected)");
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            $domain = str_replace('www.', '', $_SERVER['HTTP_HOST'] ?? 'punktepass.de');
            if (!headers_sent()) {
                session_set_cookie_params([
                    'lifetime' => 86400,
                    'path' => '/',
                    'domain' => $domain,
                    'secure' => isset($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                @session_start();
                ppv_log("✅ [POS DIAG] Session started (domain={$domain}, ID=" . session_id() . ")");
            }
        }
    }

    /** 
     * 🧩 AJAX Diagnostic Endpoint
     */
    public static function ajax_diagnostic() {
        ppv_log("📡 [POS DIAG] AJAX diagnostic request received");

        wp_send_json_success([
            'session_id' => session_id(),
            'session_status' => session_status(),
            'cookie_domain' => ini_get('session.cookie_domain'),
            'time' => date('Y-m-d H:i:s')
        ]);
    }
}

PPV_POS_Diagnostic::init();
