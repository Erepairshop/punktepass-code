<?php
if (!defined('ABSPATH')) exit;

class PPV_Stripe_Callback {

    public static function hooks() {
        add_action('template_redirect', [__CLASS__, 'handle_stripe_success']);
    }

    public static function handle_stripe_success() {
        if (isset($_GET['payment']) && $_GET['payment'] === 'success' && isset($_GET['session_id']) && isset($_GET['store_id'])) {
            global $wpdb;
            $table = $wpdb->prefix . 'ppv_stores';
            $store_id = intval($_GET['store_id']);
            $session_id = sanitize_text_field($_GET['session_id']);

            error_log("💳 Stripe Callback gestartet – Store ID: $store_id | Session: $session_id");

            // Stripe SDK betöltés
            $paths = [
                ABSPATH . '/vendor/autoload.php',
                WP_CONTENT_DIR . '/vendor/autoload.php',
                PPV_PLUGIN_DIR . 'vendor/autoload.php'
            ];
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    error_log("✅ Stripe SDK geladen aus: $path");
                    break;
                }
            }

            if (!class_exists('\Stripe\Stripe')) {
                error_log("❌ Stripe SDK nicht gefunden für Callback");
                return;
            }

            try {
                \Stripe\Stripe::setApiKey(PPV_STRIPE_SECRET);

                $session = \Stripe\Checkout\Session::retrieve($session_id);
                $subscription = isset($session->subscription) ? \Stripe\Subscription::retrieve($session->subscription) : null;

                if ($session && $session->payment_status === 'paid') {
                    $wpdb->update($table, [
                        'subscription_status' => 'active',
                        'active' => 1,
                        'updated_at' => current_time('mysql')
                    ], ['id' => $store_id]);

                    error_log("✅ Händler aktiviert: ID $store_id");
                    wp_redirect(home_url('/handler_dashboard?activated=1'));
                    exit;
                } else {
                    error_log("⚠️ Zahlung noch nicht bestätigt: $session_id");
                    wp_redirect(home_url('/handler_dashboard?payment=pending'));
                    exit;
                }

            } catch (Exception $e) {
                error_log("❌ Stripe Callback Fehler: " . $e->getMessage());
                wp_redirect(home_url('/handler_dashboard?payment=error'));
                exit;
            }
        }
    }
}

PPV_Stripe_Callback::hooks();
