<?php
if (!defined('ABSPATH')) exit;

class PPV_Stripe_Checkout {

    public static function hooks() {
        // 🔹 REST route regisztrálása a megfelelő időben
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        ppv_log('✅ PPV_Stripe_Checkout::hooks() initialized');
    }

    /** 🔹 REST API route a Checkout Session létrehozásához */
    public static function register_routes() {
        ppv_log('✅ Stripe Checkout REST route registered');
        register_rest_route('punktepass/v1', '/create-checkout-session', [
            'methods'  => ['POST', 'GET'], // GET engedélyezve teszteléshez
            'callback' => [__CLASS__, 'create_checkout_session'],
            'permission_callback' => ['PPV_Permissions', 'allow_anonymous'],
        ]);
    }

    /** 🔹 Checkout Session létrehozása (7 nap Trial) */
    public static function create_checkout_session(WP_REST_Request $request) {

        if ($request->get_method() === 'GET') {
            return new WP_REST_Response(['status' => 'Stripe Checkout endpoint aktiv ✅'], 200);
        }

        if (!class_exists('\Stripe\Stripe')) {
            if (file_exists(WP_CONTENT_DIR . '/vendor/autoload.php')) {
                require_once WP_CONTENT_DIR . '/vendor/autoload.php';
            } elseif (file_exists(ABSPATH . 'vendor/autoload.php')) {
                require_once ABSPATH . 'vendor/autoload.php';
            }
        }

        if (!defined('STRIPE_SECRET_KEY')) {
            return new WP_REST_Response(['error' => 'Stripe secret key missing'], 500);
        }

        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

        // Determine price based on store country (VAT only for DE)
        $price_id = 'price_1SFWxYG5r7ItrUMax8uVCa9p'; // Default: gross (incl. VAT)
        if (session_status() === PHP_SESSION_NONE) session_start();
        $store_id = $_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_repair_store_id'] ?? 0;
        if ($store_id) {
            global $wpdb;
            $country = $wpdb->get_var($wpdb->prepare(
                "SELECT country FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
                $store_id
            ));
            if (strtoupper($country ?: 'DE') !== 'DE' && defined('STRIPE_PRICE_ID_NET')) {
                $price_id = STRIPE_PRICE_ID_NET;
            }
        }

        try {
            $session = \Stripe\Checkout\Session::create([
                'mode' => 'subscription',
                'line_items' => [[
                    'price' => $price_id,
                    'quantity' => 1,
                ]],
                'subscription_data' => [
                    'trial_period_days' => 7,
                    'metadata' => ['store_id' => $store_id],
                ],
                'metadata' => ['store_id' => $store_id],
                'success_url' => site_url('/handler_dashboard?payment=success'),
                'cancel_url'  => site_url('/handler_dashboard?payment=cancel'),
            ]);

            return new WP_REST_Response(['url' => $session->url], 200);

        } catch (Exception $e) {
            ppv_log('❌ Stripe Checkout Fehler: ' . $e->getMessage());
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}
