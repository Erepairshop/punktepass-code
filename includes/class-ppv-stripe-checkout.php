<?php
if (!defined('ABSPATH')) exit;

class PPV_Stripe_Checkout {

    public static function hooks() {
        // 🔹 REST route regisztrálása a megfelelő időben
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        error_log('✅ PPV_Stripe_Checkout::hooks() initialized');
    }

    /** 🔹 REST API route a Checkout Session létrehozásához */
    public static function register_routes() {
        error_log('✅ Stripe Checkout REST route registered');
        register_rest_route('punktepass/v1', '/create-checkout-session', [
            'methods'  => ['POST', 'GET'], // GET engedélyezve teszteléshez
            'callback' => [__CLASS__, 'create_checkout_session'],
            'permission_callback' => '__return_true',
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

        try {
            $session = \Stripe\Checkout\Session::create([
                'mode' => 'subscription',
                'line_items' => [[
                    'price' => 'price_1SFWxYG5r7ItrUMax8uVCa9p',
                    'quantity' => 1,
                ]],
                'subscription_data' => [
                    'trial_period_days' => 7,
                ],
                'success_url' => site_url('/handler_dashboard?payment=success'),
                'cancel_url'  => site_url('/handler_dashboard?payment=cancel'),
            ]);

            return new WP_REST_Response(['url' => $session->url], 200);

        } catch (Exception $e) {
            error_log('❌ Stripe Checkout Fehler: ' . $e->getMessage());
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}
