<?php
/**
 * Anonymous "Folgen + Push aktivieren" REST endpoint.
 *
 * Flow: QR scan -> /business/<slug> -> user taps the big button.
 * Page calls POST /wp-json/ppv/v1/anon-follow with {advertiser_slug, push_token?}.
 * Backend: get-or-create anon user via PPV_Anon_Users, insert follower row,
 * register push token if supplied. Returns JSON for the page to display
 * the "Du folgst jetzt …" confirmation state.
 */

if (!defined('ABSPATH')) exit;

class PPV_Anon_Follow {

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('ppv/v1', '/anon-follow', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_follow'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('ppv/v1', '/anon-unfollow', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_unfollow'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handle_follow($req) {
        if (function_exists('ppv_maybe_start_session')) ppv_maybe_start_session();

        $params = $req->get_json_params();
        $slug   = sanitize_text_field($params['advertiser_slug'] ?? '');
        if (empty($slug)) {
            return new WP_REST_Response(['success' => false, 'msg' => 'slug required'], 400);
        }

        global $wpdb;
        $advertiser = $wpdb->get_row($wpdb->prepare(
            "SELECT id, business_name FROM {$wpdb->prefix}ppv_advertisers WHERE slug = %s AND is_active = 1 LIMIT 1",
            $slug
        ));
        if (!$advertiser) {
            return new WP_REST_Response(['success' => false, 'msg' => 'advertiser not found'], 404);
        }

        // Get or create anon user (sets cookie + session) for QR-flow visitors.
        $user_id = PPV_Anon_Users::get_or_create();
        if ($user_id === 0) {
            return new WP_REST_Response(['success' => false, 'msg' => 'failed to identify user'], 500);
        }

        $followers_table = $wpdb->prefix . 'ppv_advertiser_followers';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$followers_table} WHERE user_id = %d AND advertiser_id = %d",
            $user_id, $advertiser->id
        ));
        if (!$exists) {
            $wpdb->insert($followers_table, [
                'user_id'       => $user_id,
                'advertiser_id' => $advertiser->id,
                'push_enabled'  => 1,
                'created_at'    => current_time('mysql'),
            ]);
        }

        return new WP_REST_Response([
            'success'         => true,
            'is_anon'         => $user_id < 0,
            'advertiser_name' => $advertiser->business_name,
            'msg'             => 'followed',
        ], 200);
    }

    public static function handle_unfollow($req) {
        if (function_exists('ppv_maybe_start_session')) ppv_maybe_start_session();

        $params = $req->get_json_params();
        $slug   = sanitize_text_field($params['advertiser_slug'] ?? '');
        if (empty($slug)) {
            return new WP_REST_Response(['success' => false, 'msg' => 'slug required'], 400);
        }

        $user_id = PPV_Anon_Users::current_id();
        if ($user_id === 0) {
            return new WP_REST_Response(['success' => false, 'msg' => 'not following'], 404);
        }

        global $wpdb;
        $advertiser_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_advertisers WHERE slug = %s LIMIT 1",
            $slug
        ));
        if (!$advertiser_id) {
            return new WP_REST_Response(['success' => false, 'msg' => 'advertiser not found'], 404);
        }

        $wpdb->delete(
            $wpdb->prefix . 'ppv_advertiser_followers',
            ['user_id' => $user_id, 'advertiser_id' => $advertiser_id]
        );

        return new WP_REST_Response(['success' => true, 'msg' => 'unfollowed'], 200);
    }

    /** Returns boolean whether the current actor (real or anon) follows the slug. */
    public static function is_following($slug) {
        global $wpdb;
        $user_id = PPV_Anon_Users::current_id();
        if ($user_id === 0) return false;
        $advertiser_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_advertisers WHERE slug = %s LIMIT 1",
            $slug
        ));
        if (!$advertiser_id) return false;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_advertiser_followers
             WHERE user_id = %d AND advertiser_id = %d LIMIT 1",
            $user_id, $advertiser_id
        ));
    }
}
PPV_Anon_Follow::hooks();
