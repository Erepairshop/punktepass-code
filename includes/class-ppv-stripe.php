<?php
if (!defined('ABSPATH')) exit;
ppv_log('✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)');

class PPV_Stripe {

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /** 🔹 Stripe Webhook endpoint regisztrálása */
    public static function register_routes() {
        ppv_log('✅ Stripe REST route registered via rest_api_init');

        register_rest_route('punktepass/v1', '/stripe-webhook', [
            'methods'  => ['POST', 'GET'],
            'callback' => [__CLASS__, 'handle_webhook'],
            'permission_callback' => ['PPV_Permissions', 'allow_anonymous'],
        ]);
    }

    /** 🔹 Stripe webhook feldolgozás */
    public static function handle_webhook(WP_REST_Request $request) {

        // ✅ GET teszteléshez
        if ($request->get_method() === 'GET') {
            return new WP_REST_Response(['status' => 'Stripe Webhook endpoint aktiv ✅'], 200);
        }

        // 🔹 Payload + Signature
        $payload = $request->get_body();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret = defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '';

        // 🔹 Stripe SDK betöltése
        if (!class_exists('\Stripe\Stripe')) {
            if (file_exists(WP_CONTENT_DIR . '/vendor/autoload.php')) {
                require_once WP_CONTENT_DIR . '/vendor/autoload.php';
            } elseif (file_exists(ABSPATH . 'vendor/autoload.php')) {
                require_once ABSPATH . 'vendor/autoload.php';
            }
        }

        if (!class_exists('\Stripe\Webhook')) {
            return new WP_REST_Response(['error' => 'Stripe SDK not found'], 500);
        }

        // 🔹 Event validálása
        try {
            \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $secret);
        } catch (Exception $e) {
            ppv_log('❌ Stripe Webhook Error: ' . $e->getMessage());
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }

        global $wpdb;
        $stores_table = $wpdb->prefix . 'ppv_stores';

        // 🔹 Események feldolgozása
        switch ($event->type) {

            /* ============================================================
             * ✅ Sikeres fizetés → bolt aktiválása a ppv_stores táblában
             * ============================================================ */
            case 'checkout.session.completed':
                $session = $event->data->object;
                $store_id = $session->metadata->store_id ?? null;
                $store_email = strtolower(trim($session->customer_email ?? ''));
                
                ppv_log("✅ Stripe: Checkout abgeschlossen für {$store_email} (Store ID: {$store_id})");

                if ($store_id) {
                    // 🔹 Aktiválás ID alapján
                    $wpdb->update(
                        $stores_table,
                        [
                            'subscription_status' => 'active',
                            'active' => 1,
                            'visible' => 1,
                            'last_updated' => current_time('mysql')
                        ],
                        ['id' => $store_id]
                    );
                    ppv_log("🟢 Store #{$store_id} aktiviert über Stripe ✅");
                } elseif ($store_email) {
                    // 🔹 Ha nincs metadata → keresés e-mail alapján
                    $wpdb->update(
                        $stores_table,
                        [
                            'subscription_status' => 'active',
                            'active' => 1,
                            'visible' => 1,
                            'last_updated' => current_time('mysql')
                        ],
                        ['email' => $store_email]
                    );
                    ppv_log("🟢 Store aktiviert via Email-Match: {$store_email}");
                } else {
                    ppv_log("⚠️ Stripe Checkout ohne store_id und Email – keine Aktivierung möglich");
                }
                break;

            /* ============================================================
             * ❌ Sikertelen fizetés
             * ============================================================ */
            case 'invoice.payment_failed':
                ppv_log("❌ Stripe: Zahlung fehlgeschlagen");
                break;

            /* ============================================================
             * 🚫 Előfizetés törölve / lemondva
             * ============================================================ */
            case 'customer.subscription.deleted':
                $subscription = $event->data->object;
                $store_id = $subscription->metadata->store_id ?? null;
                $store_email = strtolower(trim($subscription->customer_email ?? ''));

                if ($store_id) {
                    $wpdb->update(
                        $stores_table,
                        ['subscription_status' => 'canceled', 'active' => 0, 'visible' => 0],
                        ['id' => $store_id]
                    );
                    ppv_log("🛑 Store #{$store_id} deaktiviert (Abo gelöscht)");
                } elseif ($store_email) {
                    $wpdb->update(
                        $stores_table,
                        ['subscription_status' => 'canceled', 'active' => 0, 'visible' => 0],
                        ['email' => $store_email]
                    );
                    ppv_log("🛑 Store deaktiviert via Email: {$store_email}");
                }
                break;

            /* ============================================================
             * ℹ️ Egyéb események logolása
             * ============================================================ */
            default:
                ppv_log("ℹ️ Stripe: Unbehandeltes Event – {$event->type}");
        }

        return new WP_REST_Response(['success' => true], 200);
    }
}

PPV_Stripe::hooks();
