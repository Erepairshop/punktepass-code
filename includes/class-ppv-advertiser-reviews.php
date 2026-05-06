<?php
/**
 * Advertiser ratings (Werbe-Händler).
 *
 * Mirrors the loyalty-store ppv_reviews / wp_pp_reviews logic but keyed by
 * advertiser_id. Anon-friendly (PPV_Anon_Users). One review per user per
 * advertiser per 365 days (same policy as stores).
 *
 * Table: wp_pp_advertiser_reviews(id, advertiser_id, user_id, rating,
 *   comment, reply, created_at). user_id is SIGNED so anon negative ids fit.
 *
 * REST routes (all anon-allowed via punktepass.php whitelist):
 *   POST /wp-json/ppv/v1/advertiser-review        body: {slug, rating, comment?}
 *   GET  /wp-json/ppv/v1/advertiser-reviews?slug=...
 */

if (!defined('ABSPATH')) exit;

class PPV_Advertiser_Reviews {

    const TABLE = 'pp_advertiser_reviews';

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function install_table() {
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$t} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            advertiser_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT SIGNED NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            comment TEXT NULL,
            reply TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY adv_idx (advertiser_id),
            KEY user_idx (user_id)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function register_routes() {
        register_rest_route('ppv/v1', '/advertiser-review', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'rest_submit'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('ppv/v1', '/advertiser-reviews', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'rest_list'],
            'permission_callback' => '__return_true',
        ]);
    }

    /** Returns the actor id (positive named or negative anon, 0 if no auth). */
    private static function actor_id($auto_create = false) {
        if (function_exists('ppv_maybe_start_session')) ppv_maybe_start_session();
        $id = !empty($_SESSION['ppv_user_id']) ? (int) $_SESSION['ppv_user_id'] : 0;
        if ($id !== 0) return $id;
        if ($auto_create && class_exists('PPV_Anon_Users')) {
            return (int) PPV_Anon_Users::get_or_create();
        }
        return 0;
    }

    public static function rest_submit($req) {
        $params = $req->get_json_params();
        $slug    = sanitize_text_field($params['slug'] ?? '');
        $rating  = (int) ($params['rating'] ?? 0);
        $comment = sanitize_textarea_field($params['comment'] ?? '');
        if (empty($slug) || $rating < 1 || $rating > 5) {
            return new WP_REST_Response(['success' => false, 'msg' => 'invalid'], 400);
        }

        global $wpdb;
        $advertiser_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_advertisers WHERE slug = %s AND is_active = 1 LIMIT 1",
            $slug
        ));
        if (!$advertiser_id) {
            return new WP_REST_Response(['success' => false, 'msg' => 'advertiser not found'], 404);
        }

        $user_id = self::actor_id(true);
        if ($user_id === 0) {
            return new WP_REST_Response(['success' => false, 'msg' => 'no actor'], 401);
        }

        // 1× per year per (advertiser, user)
        $recent = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}" . self::TABLE . "
             WHERE advertiser_id = %d AND user_id = %d
             AND created_at > (NOW() - INTERVAL 365 DAY)
             ORDER BY created_at DESC LIMIT 1",
            $advertiser_id, $user_id
        ));
        if ($recent) {
            return new WP_REST_Response(['success' => false, 'msg' => 'rate_limit'], 409);
        }

        $wpdb->insert($wpdb->prefix . self::TABLE, [
            'advertiser_id' => $advertiser_id,
            'user_id'       => $user_id,
            'rating'        => $rating,
            'comment'       => $comment,
            'created_at'    => current_time('mysql'),
        ]);

        return new WP_REST_Response(['success' => true, 'msg' => 'thanks'], 200);
    }

    public static function rest_list($req) {
        $slug = sanitize_text_field($req->get_param('slug'));
        if (empty($slug)) return new WP_REST_Response(['success' => false], 400);

        global $wpdb;
        $advertiser_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_advertisers WHERE slug = %s LIMIT 1",
            $slug
        ));
        if (!$advertiser_id) return new WP_REST_Response(['success' => false, 'msg' => '404'], 404);

        return new WP_REST_Response([
            'success' => true,
            'aggregate' => self::aggregate($advertiser_id),
            'reviews'   => self::recent_reviews($advertiser_id, 20),
        ], 200);
    }

    /** Helpers for inline rendering inside business-public.php. */
    public static function aggregate($advertiser_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS cnt
             FROM {$wpdb->prefix}" . self::TABLE . "
             WHERE advertiser_id = %d", $advertiser_id
        ));
        return [
            'avg' => $row && $row->cnt > 0 ? (float) $row->avg_rating : null,
            'count' => $row ? (int) $row->cnt : 0,
        ];
    }

    public static function recent_reviews($advertiser_id, $limit = 10) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT rating, comment, reply, created_at
             FROM {$wpdb->prefix}" . self::TABLE . "
             WHERE advertiser_id = %d
             ORDER BY created_at DESC
             LIMIT %d", $advertiser_id, $limit
        ));
        return array_map(function ($r) {
            return [
                'rating'  => (int) $r->rating,
                'comment' => (string) $r->comment,
                'reply'   => (string) $r->reply,
                'date'    => substr((string) $r->created_at, 0, 10),
            ];
        }, $rows ?: []);
    }
}
PPV_Advertiser_Reviews::hooks();
