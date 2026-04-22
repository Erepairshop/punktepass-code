<?php
/**
 * PunktePass Agent REST API
 * Endpoints for sales agent mobile app
 */

if (!defined('ABSPATH')) exit;

class PPV_Agent_API {

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        // Check-in at a location
        register_rest_route('ppv/v1', '/agent/checkin', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'checkin'],
            'permission_callback' => '__return_true' // TODO: restore check_agent_permission after testing
        ]);

        // Get agent's prospects
        register_rest_route('ppv/v1', '/agent/prospects', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_prospects'],
            'permission_callback' => '__return_true' // TODO: restore check_agent_permission after testing
        ]);

        // Update a prospect
        register_rest_route('ppv/v1', '/agent/prospect/(?P<id>\d+)', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'update_prospect'],
            'permission_callback' => '__return_true' // TODO: restore check_agent_permission after testing
        ]);

        // Agent stats
        register_rest_route('ppv/v1', '/agent/stats', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_stats'],
            'permission_callback' => '__return_true' // TODO: restore check_agent_permission after testing
        ]);
    }

    /**
     * Permission check: user must be agent or admin
     */
    public static function check_agent_permission() {
        if (current_user_can('manage_options')) return true;

        if (session_status() === PHP_SESSION_NONE) ppv_maybe_start_session();

        $user_type = $_SESSION['ppv_user_type'] ?? '';
        if (in_array($user_type, ['agent', 'admin', 'vendor'])) return true;

        // Check via token
        if (class_exists('PPV_Permissions')) {
            $auth = PPV_Permissions::check_authenticated();
            if (!is_wp_error($auth)) {
                $user_data = PPV_Permissions::get_authenticated_user_data();
                if ($user_data && in_array($user_data['user_type'] ?? '', ['agent', 'admin', 'vendor'])) {
                    return true;
                }
            }
        }

        return new WP_Error('forbidden', 'Agent access required', ['status' => 403]);
    }

    /**
     * Get agent user ID from session
     */
    private static function get_agent_id() {
        if (session_status() === PHP_SESSION_NONE) ppv_maybe_start_session();
        return intval($_SESSION['ppv_user_id'] ?? 0);
    }

    /**
     * POST /agent/checkin — Register a visit at a location
     */
    public static function checkin(WP_REST_Request $request) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_sales_markers';

        self::maybe_upgrade_table();

        $params = $request->get_json_params();
        $agent_id = self::get_agent_id();

        $lat = floatval($params['lat'] ?? 0);
        $lng = floatval($params['lng'] ?? 0);
        $business_name = sanitize_text_field($params['business_name'] ?? '');
        $address = sanitize_text_field($params['address'] ?? '');
        $city = sanitize_text_field($params['city'] ?? '');
        $contact_name = sanitize_text_field($params['contact_name'] ?? '');
        $contact_phone = sanitize_text_field($params['contact_phone'] ?? '');
        $contact_email = sanitize_email($params['contact_email'] ?? '');
        $notes = sanitize_textarea_field($params['notes'] ?? '');
        $status = sanitize_text_field($params['status'] ?? 'visited');

        if (!$lat || !$lng) {
            return new WP_REST_Response(['success' => false, 'message' => 'GPS position required'], 400);
        }
        if (empty($business_name)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Business name required'], 400);
        }

        $inserted = $wpdb->insert($table, [
            'business_name'  => $business_name,
            'address'        => $address,
            'city'           => $city,
            'lat'            => $lat,
            'lng'            => $lng,
            'status'         => $status,
            'contact_name'   => $contact_name,
            'contact_email'  => $contact_email,
            'contact_phone'  => $contact_phone,
            'notes'          => $notes,
            'agent_id'       => $agent_id,
            'visited_at'     => current_time('mysql'),
            'created_at'     => current_time('mysql'),
        ], ['%s','%s','%s','%f','%f','%s','%s','%s','%s','%s','%d','%s','%s']);

        if (!$inserted) {
            return new WP_REST_Response(['success' => false, 'message' => 'Database error'], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Check-in saved!',
            'id' => $wpdb->insert_id
        ]);
    }

    /**
     * GET /agent/prospects — List agent's prospects
     */
    public static function get_prospects(WP_REST_Request $request) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_sales_markers';

        self::maybe_upgrade_table();

        $agent_id = self::get_agent_id();
        $filter = sanitize_text_field($request->get_param('status') ?? '');
        $period = sanitize_text_field($request->get_param('period') ?? '');

        $where = "WHERE 1=1";
        $args = [];

        // Agent filter (admin sees all)
        $user_type = $_SESSION['ppv_user_type'] ?? '';
        if ($user_type !== 'admin' && !current_user_can('manage_options')) {
            $where .= " AND agent_id = %d";
            $args[] = $agent_id;
        }

        if ($filter) {
            $where .= " AND status = %s";
            $args[] = $filter;
        }

        if ($period === 'today') {
            $where .= " AND DATE(visited_at) = %s";
            $args[] = current_time('Y-m-d');
        } elseif ($period === 'week') {
            $where .= " AND visited_at >= %s";
            $args[] = date('Y-m-d', strtotime('-7 days'));
        }

        $query = "SELECT * FROM $table $where ORDER BY visited_at DESC LIMIT 200";
        if (!empty($args)) {
            $query = $wpdb->prepare($query, ...$args);
        }

        $results = $wpdb->get_results($query);

        return new WP_REST_Response([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * POST /agent/prospect/{id} — Update a prospect
     */
    public static function update_prospect(WP_REST_Request $request) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_sales_markers';

        self::maybe_upgrade_table();

        $id = intval($request['id']);
        $agent_id = self::get_agent_id();
        $params = $request->get_json_params();

        // Verify ownership (agent can only edit own prospects, admin can edit all)
        $user_type = $_SESSION['ppv_user_type'] ?? '';
        if ($user_type !== 'admin' && !current_user_can('manage_options')) {
            $owner = $wpdb->get_var($wpdb->prepare(
                "SELECT agent_id FROM $table WHERE id = %d", $id
            ));
            if (intval($owner) !== $agent_id) {
                return new WP_REST_Response(['success' => false, 'message' => 'Not your prospect'], 403);
            }
        }

        $update = [];
        $formats = [];

        $text_fields = ['business_name', 'address', 'city', 'contact_name', 'contact_phone', 'notes', 'status', 'result'];
        foreach ($text_fields as $field) {
            if (isset($params[$field])) {
                $update[$field] = sanitize_text_field($params[$field]);
                $formats[] = '%s';
            }
        }

        if (isset($params['contact_email'])) {
            $update['contact_email'] = sanitize_email($params['contact_email']);
            $formats[] = '%s';
        }

        if (isset($params['needs_device'])) {
            $update['needs_device'] = intval($params['needs_device']);
            $formats[] = '%d';
        }

        if (isset($params['device_delivered'])) {
            $update['device_delivered'] = intval($params['device_delivered']);
            $formats[] = '%d';
        }

        // Auto-set trial dates when status changes to 'trial'
        if (isset($params['status']) && $params['status'] === 'interested') {
            $update['result'] = 'pending';
            $formats[] = '%s';
        }

        if (isset($params['result']) && $params['result'] === 'trial') {
            $update['trial_start'] = current_time('Y-m-d');
            $update['trial_end'] = date('Y-m-d', strtotime('+30 days'));
            $formats[] = '%s';
            $formats[] = '%s';
        }

        if (isset($params['next_followup'])) {
            $update['next_followup'] = sanitize_text_field($params['next_followup']);
            $formats[] = '%s';
        }

        $update['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $wpdb->update($table, $update, ['id' => $id], $formats, ['%d']);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Prospect updated'
        ]);
    }

    /**
     * GET /agent/stats — Agent performance stats
     */
    public static function get_stats(WP_REST_Request $request) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_sales_markers';

        self::maybe_upgrade_table();

        $agent_id = self::get_agent_id();
        $today = current_time('Y-m-d');
        $week_start = date('Y-m-d', strtotime('-6 days'));
        $month_start = date('Y-m-01');

        // Agent filter
        $agent_where = "";
        $user_type = $_SESSION['ppv_user_type'] ?? '';
        if ($user_type !== 'admin' && !current_user_can('manage_options')) {
            $agent_where = $wpdb->prepare(" AND agent_id = %d", $agent_id);
        }

        $stats = [
            'today' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE DATE(visited_at) = '$today' $agent_where"),
            'week' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE visited_at >= '$week_start' $agent_where"),
            'month' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE visited_at >= '$month_start' $agent_where"),
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE 1=1 $agent_where"),
            'by_status' => [],
            'by_result' => [],
            'followups_due' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE next_followup <= '$today' AND next_followup IS NOT NULL AND result NOT IN ('converted','lost') $agent_where"),
        ];

        $statuses = $wpdb->get_results("SELECT status, COUNT(*) as cnt FROM $table WHERE 1=1 $agent_where GROUP BY status");
        foreach ($statuses as $s) {
            $stats['by_status'][$s->status] = (int) $s->cnt;
        }

        $results = $wpdb->get_results("SELECT result, COUNT(*) as cnt FROM $table WHERE result IS NOT NULL AND result != '' $agent_where GROUP BY result");
        foreach ($results as $r) {
            $stats['by_result'][$r->result] = (int) $r->cnt;
        }

        return new WP_REST_Response(['success' => true, 'stats' => $stats]);
    }

    /**
     * Upgrade ppv_sales_markers table with new agent columns
     */
    private static function maybe_upgrade_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_sales_markers';

        // Check if agent_id column exists
        $col = $wpdb->get_var("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = 'agent_id'");
        if ($col) return;

        // Add new columns
        $wpdb->query("ALTER TABLE $table
            ADD COLUMN agent_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER id,
            ADD COLUMN trial_start DATE DEFAULT NULL AFTER next_followup,
            ADD COLUMN trial_end DATE DEFAULT NULL AFTER trial_start,
            ADD COLUMN needs_device TINYINT(1) DEFAULT 0 AFTER trial_end,
            ADD COLUMN device_delivered TINYINT(1) DEFAULT 0 AFTER needs_device,
            ADD COLUMN result VARCHAR(50) DEFAULT 'pending' AFTER device_delivered,
            ADD INDEX idx_agent (agent_id),
            ADD INDEX idx_result (result)
        ");
    }
}

PPV_Agent_API::hooks();

