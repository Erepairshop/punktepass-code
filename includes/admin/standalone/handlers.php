<?php
/**
 * PunktePass Standalone Admin - Handler Management
 * Two tabs: 1) Handler Overview, 2) User to Handler Conversion
 */

// Must be accessed via WordPress
if (!defined('ABSPATH')) exit;

// Security: Session-based auth is already handled by PPV_Standalone_Admin::process_admin_request()
global $wpdb;

// ============================================================
// TAB 1: HANDLER OVERVIEW DATA
// ============================================================

// Get handlers with device and filiale counts (for overview tab)
$handlers_overview = $wpdb->get_results(
    "SELECT s.*,
            (SELECT COUNT(*) FROM {$wpdb->prefix}ppv_user_devices WHERE store_id = s.id AND status = 'active') as device_count,
            (SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE parent_store_id = s.id) as filiale_count
     FROM {$wpdb->prefix}ppv_stores s
     WHERE s.parent_store_id IS NULL OR s.parent_store_id = 0
     ORDER BY s.name ASC"
);

// Get devices grouped by store
$devices_by_store = [];
$all_devices = $wpdb->get_results(
    "SELECT id, store_id, fingerprint_hash, device_name, user_agent, device_info, ip_address, registered_at, last_used_at, status, mobile_scanner
     FROM {$wpdb->prefix}ppv_user_devices WHERE status = 'active' ORDER BY registered_at DESC"
);
foreach ($all_devices as $device) {
    $devices_by_store[$device->store_id][] = $device;
}

// ============================================================
// TAB 2: USER TO HANDLER CONVERSION - FORM SUBMISSIONS
// ============================================================

// Convert user to handler (long form)
if (isset($_POST['convert_to_handler']) && check_admin_referer('ppv_convert_handler', 'ppv_convert_nonce')) {
    $user_identifier = sanitize_text_field($_POST['user_identifier']);
    $company_name = sanitize_text_field($_POST['company_name']);
    $store_email = sanitize_email($_POST['store_email']);
    $country = sanitize_text_field($_POST['country'] ?? 'DE');

    // Find user by ID or email
    $user = null;
    if (is_numeric($user_identifier)) {
        $user = get_user_by('ID', intval($user_identifier));
    } else {
        $user = get_user_by('email', $user_identifier);
    }

    if (!$user) {
        $error_message = '❌ User nem található / User not found: ' . esc_html($user_identifier);
    } else {
        // Check if already a handler
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
            $user->ID
        ));

        if ($existing) {
            $error_message = '⚠️ Ez a user már handler / This user is already a handler (Store ID: ' . $existing . ')';
        } else {
            // Generate store key
            $store_key = bin2hex(random_bytes(16));
            $trial_ends_at = date('Y-m-d H:i:s', strtotime('+30 days'));

            // Create store
            $result = $wpdb->insert(
                "{$wpdb->prefix}ppv_stores",
                [
                    'user_id' => $user->ID,
                    'name' => $user->display_name,
                    'company_name' => $company_name,
                    'email' => $store_email,
                    'country' => $country,
                    'store_key' => $store_key,
                    'trial_ends_at' => $trial_ends_at,
                    'subscription_status' => 'trial',
                    'active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
            );

            if ($result) {
                $new_store_id = $wpdb->insert_id;
                ppv_log("✅ [PPV_Admin] User #{$user->ID} ({$user->user_email}) zu Handler konvertiert. Store ID: {$new_store_id}");
                $success_message = "✅ User '{$user->display_name}' ({$user->user_email}) sikeresen handler-ré lett téve!<br>
                                    <strong>Store ID:</strong> {$new_store_id}<br>
                                    <strong>Store Key:</strong> <code>{$store_key}</code><br>
                                    <strong>Trial vége:</strong> {$trial_ends_at}";
            } else {
                $error_message = '❌ Hiba történt / Error: ' . $wpdb->last_error;
                ppv_log("❌ [PPV_Admin] Handler konverzió sikertelen: " . $wpdb->last_error);
            }
        }
    }
}

// Quick convert PPV user to handler (from user list button)
if (isset($_POST['quick_convert']) && check_admin_referer('ppv_quick_convert', 'ppv_quick_convert_nonce')) {
    $user_id = intval($_POST['user_id']);

    // Get PPV user
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ppv_users WHERE id = %d LIMIT 1",
        $user_id
    ));

    if (!$user) {
        $error_message = '❌ PPV User nem található!';
    } else {
        // Check if already a handler
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
            $user->id
        ));

        if ($existing) {
            $error_message = '⚠️ Ez a user már handler (Store ID: ' . $existing . ')';
        } else {
            // Generate store key
            $store_key = bin2hex(random_bytes(16));
            $trial_ends_at = date('Y-m-d H:i:s', strtotime('+30 days'));

            // Create store with minimal data
            $result = $wpdb->insert(
                "{$wpdb->prefix}ppv_stores",
                [
                    'user_id' => $user->id,
                    'name' => $user->username ?: $user->email,
                    'company_name' => $user->username ?: $user->email,
                    'email' => $user->email,
                    'country' => 'DE',
                    'store_key' => $store_key,
                    'trial_ends_at' => $trial_ends_at,
                    'subscription_status' => 'trial',
                    'active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
            );

            if ($result) {
                $new_store_id = $wpdb->insert_id;
                $success_message = "✅ User '{$user->username}' (ID: {$user->id}) handler-ré téve! (Store ID: {$new_store_id})";
                ppv_log("✅ [PPV_Admin] Quick Convert: PPV User #{$user->id} ({$user->email}) → Handler Store #{$new_store_id}");
            } else {
                $error_message = '❌ Hiba: ' . $wpdb->last_error;
                ppv_log("❌ [PPV_Admin] Quick Convert failed: " . $wpdb->last_error);
            }
        }
    }
}

// Get PPV users who are NOT handlers yet
$non_handler_users = $wpdb->get_results("
    SELECT u.id, u.username, u.email, u.created_at, u.user_type
    FROM {$wpdb->prefix}ppv_users u
    LEFT JOIN {$wpdb->prefix}ppv_stores s ON u.id = s.user_id
    WHERE s.id IS NULL
    AND u.user_type NOT IN ('store', 'handler', 'vendor', 'scanner', 'admin')
    ORDER BY u.created_at DESC
");

// Get all handlers for conversion tab
$handlers_list = $wpdb->get_results("
    SELECT
        s.id,
        s.user_id,
        s.name,
        s.company_name,
        s.email,
        s.city,
        s.country,
        s.trial_ends_at,
        s.subscription_status,
        s.subscription_expires_at,
        s.created_at,
        s.active,
        s.store_key,
        u.user_email as wp_email,
        u.display_name as wp_display_name,
        (SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE parent_store_id = s.id) as filiale_count,
        (SELECT COUNT(*) FROM {$wpdb->prefix}ppv_users WHERE user_type = 'scanner' AND vendor_store_id = s.id) as scanner_count
    FROM {$wpdb->prefix}ppv_stores s
    LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
    WHERE s.parent_store_id IS NULL
    ORDER BY s.id DESC
");

// Helper function for device info parsing
function ppv_parse_device_info($user_agent) {
    $info = ['os' => 'Unknown', 'browser' => 'Unknown', 'model' => 'Unknown'];

    if (preg_match('/\(([^)]+)\)/', $user_agent, $matches)) {
        $details = $matches[1];
        if (strpos($details, 'Windows') !== false) {
            $info['os'] = 'Windows';
        } elseif (strpos($details, 'Macintosh') !== false || strpos($details, 'Mac OS') !== false) {
            $info['os'] = 'macOS';
        } elseif (strpos($details, 'Linux') !== false) {
            $info['os'] = 'Linux';
        } elseif (strpos($details, 'Android') !== false) {
            $info['os'] = 'Android';
            if (preg_match('/;\s*([^;]+)\s+Build/', $details, $model_match)) {
                $info['model'] = trim($model_match[1]);
            }
        } elseif (strpos($details, 'iPhone') !== false || strpos($details, 'iPad') !== false) {
            $info['os'] = 'iOS';
            $info['model'] = strpos($details, 'iPad') !== false ? 'iPad' : 'iPhone';
        }
    }

    if (strpos($user_agent, 'Chrome') !== false && strpos($user_agent, 'Edg') === false) {
        $info['browser'] = 'Chrome';
    } elseif (strpos($user_agent, 'Safari') !== false && strpos($user_agent, 'Chrome') === false) {
        $info['browser'] = 'Safari';
    } elseif (strpos($user_agent, 'Firefox') !== false) {
        $info['browser'] = 'Firefox';
    } elseif (strpos($user_agent, 'Edg') !== false) {
        $info['browser'] = 'Edge';
    }

    return $info;
}

function ppv_format_device_info_json($device_info_json) {
    if (empty($device_info_json)) return 'N/A';
    $info = json_decode($device_info_json, true);
    if (!$info) return 'N/A';

    $parts = [];
    if (!empty($info['screen'])) $parts[] = $info['screen'];
    if (!empty($info['cpu'])) $parts[] = $info['cpu'];
    if (!empty($info['gpu'])) $parts[] = substr($info['gpu'], 0, 30);

    return implode(' | ', $parts) ?: 'N/A';
}

?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PunktePass Admin - Handler Management</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0a0f1a 0%, #1a1a2e 100%);
            color: #e2e8f0;
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #00d4ff 0%, #00a8cc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            color: #94a3b8;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }

        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            gap: 8px;
            margin-bottom: 30px;
            border-bottom: 2px solid rgba(0, 212, 255, 0.2);
            padding-bottom: 0;
        }

        .tab-button {
            padding: 14px 28px;
            background: rgba(20, 30, 51, 0.6);
            border: 1px solid rgba(0, 212, 255, 0.2);
            border-bottom: none;
            border-radius: 12px 12px 0 0;
            color: #94a3b8;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            bottom: -2px;
        }

        .tab-button:hover {
            background: rgba(0, 212, 255, 0.1);
            color: #00d4ff;
        }

        .tab-button.active {
            background: rgba(0, 212, 255, 0.15);
            border-color: #00d4ff;
            color: #00d4ff;
            border-bottom: 2px solid #00d4ff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.1) 100%);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.1) 100%);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .card {
            background: rgba(20, 30, 51, 0.8);
            border: 1px solid rgba(0, 212, 255, 0.2);
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .card h2 {
            font-size: 1.4rem;
            margin-bottom: 20px;
            color: #00d4ff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #cbd5e1;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            background: rgba(15, 23, 42, 0.9);
            border: 2px solid rgba(100, 116, 139, 0.6);
            border-radius: 10px;
            color: #e2e8f0;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.2);
        }

        .form-group small {
            display: block;
            margin-top: 6px;
            color: #94a3b8;
            font-size: 0.85rem;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00d4ff 0%, #00a8cc 100%);
            color: #0a0f1a;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 212, 255, 0.4);
        }

        .handlers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .handlers-table th {
            background: rgba(0, 212, 255, 0.1);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #00d4ff;
            border-bottom: 2px solid rgba(0, 212, 255, 0.3);
            font-size: 0.9rem;
        }

        .handlers-table td {
            padding: 14px 12px;
            border-bottom: 1px solid rgba(100, 116, 139, 0.2);
            color: #cbd5e1;
            font-size: 0.9rem;
        }

        .handlers-table tr:hover {
            background: rgba(0, 212, 255, 0.05);
        }

        .handlers-table .clickable-row {
            cursor: pointer;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-active {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-inactive {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .badge-trial {
            background: rgba(251, 146, 60, 0.2);
            color: #fb923c;
            border: 1px solid rgba(251, 146, 60, 0.3);
        }

        .badge-active-sub {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-mobile {
            background: rgba(0, 230, 255, 0.2);
            color: #00e6ff;
            border: 1px solid rgba(0, 230, 255, 0.3);
        }

        code {
            background: rgba(0, 0, 0, 0.3);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: #00d4ff;
        }

        .store-key {
            font-family: 'Courier New', monospace;
            font-size: 0.75rem;
            color: #94a3b8;
            cursor: pointer;
            user-select: all;
        }

        .store-key:hover {
            color: #00d4ff;
        }

        /* Filter bar for overview tab */
        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-bar input[type="text"] {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
        }

        .filter-bar select {
            padding: 10px 15px;
            background: #1a1a2e;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
        }

        /* Handler Modal Styles */
        .handler-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.85);
            z-index: 1000;
            align-items: flex-start;
            justify-content: center;
            padding: 30px 20px;
            overflow-y: auto;
        }
        .handler-modal-overlay.active { display: flex; }
        .handler-modal {
            background: #1a1a2e;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            width: 100%;
            max-width: 900px;
        }
        .handler-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .handler-modal-header h2 { font-size: 18px; color: #00e6ff; margin: 0; }
        .handler-modal-stats { display: flex; gap: 15px; margin-top: 8px; flex-wrap: wrap; font-size: 12px; color: #888; }
        .handler-modal-close {
            background: none;
            border: none;
            color: #888;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
        }
        .handler-modal-close:hover { color: #fff; }
        .handler-modal-body { padding: 24px; }
        .handler-tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
        .handler-tab-btn {
            padding: 10px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #aaa;
            cursor: pointer;
            font-size: 13px;
        }
        .handler-tab-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .handler-tab-btn.active { background: rgba(0,230,255,0.2); color: #00e6ff; border-color: rgba(0,230,255,0.3); }
        .handler-tab-content { display: none; }
        .handler-tab-content.active { display: block; }
        .handler-device-list { }
        .handler-device-item {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .handler-device-item:hover { background: rgba(255,255,255,0.05); }
        .handler-device-name { font-weight: 600; color: #00e6ff; }
        .handler-device-meta { font-size: 11px; color: #888; margin-top: 4px; }
        .handler-device-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
        .handler-device-badge {
            background: rgba(255,255,255,0.05);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            color: #aaa;
        }
        .handler-device-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .handler-info-box {
            background: rgba(0,230,255,0.1);
            border: 1px solid rgba(0,230,255,0.2);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .handler-info-box h4 { color: #fff; margin: 0 0 5px 0; font-size: 14px; }
        .handler-info-box p { color: #aaa; font-size: 12px; margin: 0; }
        .handler-info-box-success { background: rgba(76,175,80,0.1); border-color: rgba(76,175,80,0.2); }
        .handler-subscription-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .handler-sub-card {
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            padding: 15px;
        }
        .handler-sub-card .label { font-size: 11px; color: #888; margin-bottom: 5px; }
        .handler-sub-card .value { font-size: 18px; font-weight: 600; }
        .handler-sub-card .value.active { color: #81c784; }
        .handler-sub-card .value.trial { color: #ffb74d; }
        .handler-sub-card .value.expired { color: #ef5350; }
        .handler-toggle-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
        }
        .handler-toggle-option:hover { background: rgba(255,255,255,0.05); }
        .handler-toggle-option input { width: 18px; height: 18px; }
        .handler-toggle-option strong { color: #fff; font-size: 13px; }
        tr.clickable-row:hover { background: rgba(0,230,255,0.1); cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏪 Handler Management</h1>
        <p class="subtitle">Handler overview és user-to-handler konverzió</p>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-button active" onclick="switchTab('overview')">
                <i class="ri-store-2-line"></i> Handler Overview
            </button>
            <button class="tab-button" onclick="switchTab('conversion')">
                <i class="ri-user-add-line"></i> User to Handler
            </button>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 1: HANDLER OVERVIEW -->
        <!-- ============================================================ -->
        <div id="tab-overview" class="tab-content active">
            <!-- Search and filters -->
            <div class="filter-bar">
                <input type="text" id="handlerSearch" placeholder="Keresés név, város vagy email alapján..." oninput="filterHandlers()">
                <select id="statusFilter" onchange="filterHandlers()">
                    <option value="">Összes státusz</option>
                    <option value="active">Aktív</option>
                    <option value="trial">Trial</option>
                    <option value="expired">Lejárt</option>
                </select>
                <select id="scannerFilter" onchange="filterHandlers()">
                    <option value="">Összes scanner</option>
                    <option value="mobile">Mobile</option>
                    <option value="fixed">Fixed</option>
                </select>
                <span id="handlerCount" style="color: #888; font-size: 13px;"></span>
            </div>

            <div class="card">
                <h2><i class="ri-store-2-line"></i> Handler áttekintés (<?php echo count($handlers_overview); ?>)</h2>

                <?php if (empty($handlers_overview)): ?>
                    <p style="text-align: center; color: #94a3b8; padding: 40px;">
                        Még nincs handler a rendszerben.
                    </p>
                <?php else: ?>
                    <table id="handlersTable" class="handlers-table">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Név</th>
                                <th>Város</th>
                                <th>Abo hátra</th>
                                <th>Scanner</th>
                                <th>Készülékek</th>
                                <th>Fiókok</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($handlers_overview as $handler):
                                $handler_devices = $devices_by_store[$handler->id] ?? [];

                                // Device data for modal
                                $devices_json = array_map(function($d) {
                                    $info = ppv_parse_device_info($d->user_agent ?? '');
                                    $fp = ppv_format_device_info_json($d->device_info ?? '');
                                    return [
                                        'id' => $d->id,
                                        'name' => $d->device_name,
                                        'model' => $info['model'],
                                        'os' => $info['os'],
                                        'mobile_scanner' => !empty($d->mobile_scanner),
                                        'ip' => $d->ip_address,
                                        'registered_at' => $d->registered_at,
                                        'fingerprint' => $fp
                                    ];
                                }, $handler_devices);

                                // Subscription status
                                $sub_status = $handler->subscription_status ?? 'trial';
                                $days_remaining = null;
                                $status_for_filter = $sub_status;

                                if ($sub_status === 'trial' && $handler->trial_ends_at) {
                                    $days_remaining = (int) ((strtotime($handler->trial_ends_at) - time()) / 86400);
                                } elseif ($sub_status === 'active' && $handler->subscription_expires_at) {
                                    $days_remaining = (int) ((strtotime($handler->subscription_expires_at) - time()) / 86400);
                                }

                                if ($days_remaining !== null && $days_remaining < 0) {
                                    $status_for_filter = 'expired';
                                }

                                $handler_data = json_encode([
                                    'id' => $handler->id,
                                    'name' => $handler->name,
                                    'company_name' => $handler->company_name ?? '',
                                    'city' => $handler->city ?? '',
                                    'email' => $handler->email ?? '',
                                    'scanner_type' => $handler->scanner_type ?? 'fixed',
                                    'subscription_status' => $handler->subscription_status ?? 'trial',
                                    'trial_ends_at' => $handler->trial_ends_at ?? null,
                                    'subscription_expires_at' => $handler->subscription_expires_at ?? null,
                                    'max_filialen' => $handler->max_filialen ?? 1,
                                    'filiale_count' => intval($handler->filiale_count),
                                    'device_count' => count($handler_devices),
                                    'devices' => $devices_json
                                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                            ?>
                                <tr class="clickable-row handler-row"
                                    onclick='openHandlerModal(<?php echo $handler_data; ?>)'
                                    data-name="<?php echo esc_attr(strtolower($handler->name)); ?>"
                                    data-city="<?php echo esc_attr(strtolower($handler->city ?? '')); ?>"
                                    data-email="<?php echo esc_attr(strtolower($handler->email ?? '')); ?>"
                                    data-status="<?php echo esc_attr($status_for_filter); ?>"
                                    data-scanner="<?php echo esc_attr($handler->scanner_type ?? 'fixed'); ?>">
                                    <td style="font-size: 12px; color: #00e6ff;"><?php echo esc_html($handler->email ?: '-'); ?></td>
                                    <td><strong><?php echo esc_html($handler->name); ?></strong></td>
                                    <td><?php echo esc_html($handler->city ?: '-'); ?></td>
                                    <td>
                                        <?php if ($days_remaining === null): ?>
                                            <span style="color: #666;">-</span>
                                        <?php elseif ($days_remaining < 0): ?>
                                            <span style="color: #f44336; font-weight: 600;">Lejárt</span>
                                        <?php elseif ($days_remaining <= 7): ?>
                                            <span style="color: #ff9800; font-weight: 600;"><?php echo $days_remaining; ?> nap</span>
                                        <?php else: ?>
                                            <span style="color: #4caf50;"><?php echo $days_remaining; ?> nap</span>
                                        <?php endif; ?>
                                        <small style="display: block; color: #666; font-size: 10px;">
                                            <?php echo $sub_status === 'trial' ? 'Trial' : ($sub_status === 'active' ? 'Aktív' : $sub_status); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($handler->scanner_type === 'mobile'): ?>
                                            <span class="badge badge-mobile">Mobile</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: rgba(255,255,255,0.1); color: #888;">Fixed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (empty($handler_devices)): ?>
                                            <span style="color: #666;">0</span>
                                        <?php else: ?>
                                            <strong style="color: #4caf50;"><?php echo count($handler_devices); ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $current_filialen = intval($handler->filiale_count) + 1;
                                        $max_filialen = intval($handler->max_filialen ?? 1);
                                        ?>
                                        <span style="color: <?php echo $current_filialen >= $max_filialen ? '#f44336' : '#4caf50'; ?>;">
                                            <?php echo $current_filialen; ?>/<?php echo $max_filialen; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 2: USER TO HANDLER CONVERSION -->
        <!-- ============================================================ -->
        <div id="tab-conversion" class="tab-content">
            <!-- PunktePass Users List -->
            <div class="card">
                <h2>👥 PunktePass Userek (<?php echo count($non_handler_users); ?>)</h2>
                <p style="color: #94a3b8; margin-bottom: 20px;">Kattints a "Handler jogot ad" gombra bármely user mellett, hogy azonnal handler-ré tedd őket.</p>

                <?php if (empty($non_handler_users)): ?>
                    <p style="text-align: center; color: #94a3b8; padding: 40px;">
                        Minden PunktePass user már handler! 🎉
                    </p>
                <?php else: ?>
                    <table class="handlers-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>User Type</th>
                                <th>Regisztrálva</th>
                                <th>Művelet</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($non_handler_users as $user): ?>
                                <tr>
                                    <td><strong><?php echo $user->id; ?></strong></td>
                                    <td><?php echo esc_html($user->username ?: '-'); ?></td>
                                    <td><?php echo esc_html($user->email); ?></td>
                                    <td><span class="badge"><?php echo $user->user_type; ?></span></td>
                                    <td><small><?php echo date('Y-m-d H:i', strtotime($user->created_at)); ?></small></td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('ppv_quick_convert', 'ppv_quick_convert_nonce'); ?>
                                            <input type="hidden" name="user_id" value="<?php echo $user->id; ?>">
                                            <button type="submit" name="quick_convert" class="btn btn-primary" style="padding: 6px 12px; font-size: 0.85rem;">
                                                🏪 Handler jogot ad
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Convert User to Handler Form -->
            <div class="card">
                <h2>👤 ➡️ 🏪 User to Handler Konverzió</h2>

                <form method="post">
                    <?php wp_nonce_field('ppv_convert_handler', 'ppv_convert_nonce'); ?>

                    <div class="form-group">
                        <label>User ID vagy Email cím *</label>
                        <input type="text" name="user_identifier" required placeholder="123 vagy user@example.com">
                        <small>Add meg a WordPress user ID-t vagy email címét</small>
                    </div>

                    <div class="form-group">
                        <label>Cégnév (Company Name) *</label>
                        <input type="text" name="company_name" required placeholder="pl. Test GmbH">
                        <small>A bolt hivatalos cégneve</small>
                    </div>

                    <div class="form-group">
                        <label>Bolt Email *</label>
                        <input type="email" name="store_email" required placeholder="store@example.com">
                        <small>A bolt kapcsolattartási email címe</small>
                    </div>

                    <div class="form-group">
                        <label>Ország (Country) *</label>
                        <select name="country" required>
                            <option value="DE">🇩🇪 Németország (Deutschland)</option>
                            <option value="HU">🇭🇺 Magyarország</option>
                            <option value="AT">🇦🇹 Ausztria (Österreich)</option>
                            <option value="RO">🇷🇴 Románia (România)</option>
                            <option value="SK">🇸🇰 Szlovákia (Slovensko)</option>
                            <option value="HR">🇭🇷 Horvátország (Hrvatska)</option>
                            <option value="SI">🇸🇮 Szlovénia (Slovenija)</option>
                        </select>
                        <small>Automatikusan beállítja a megfelelő pénznemet</small>
                    </div>

                    <button type="submit" name="convert_to_handler" class="btn btn-primary">
                        ✨ User Handler-ré Konvertálása
                    </button>
                </form>
            </div>

            <!-- Handlers List -->
            <div class="card">
                <h2>📋 Összes Handler (<?php echo count($handlers_list); ?>)</h2>

                <?php if (empty($handlers_list)): ?>
                    <p style="text-align: center; color: #94a3b8; padding: 40px;">
                        Még nincs handler a rendszerben.
                    </p>
                <?php else: ?>
                    <table class="handlers-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Név / Cégnév</th>
                                <th>Email</th>
                                <th>WP User</th>
                                <th>Ország</th>
                                <th>Status</th>
                                <th>Trial / Sub</th>
                                <th>Filiálék</th>
                                <th>Scannerek</th>
                                <th>Store Key</th>
                                <th>Létrehozva</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($handlers_list as $h): ?>
                                <tr>
                                    <td><strong><?php echo $h->id; ?></strong></td>
                                    <td>
                                        <strong><?php echo esc_html($h->name); ?></strong><br>
                                        <small><?php echo esc_html($h->company_name); ?></small>
                                    </td>
                                    <td><?php echo esc_html($h->email); ?></td>
                                    <td>
                                        <small>
                                            #<?php echo $h->user_id; ?><br>
                                            <?php echo esc_html($h->wp_display_name); ?><br>
                                            <?php echo esc_html($h->wp_email); ?>
                                        </small>
                                    </td>
                                    <td><?php echo $h->country; ?> <?php if ($h->currency): ?>(<?php echo $h->currency; ?>)<?php endif; ?></td>
                                    <td>
                                        <?php if ($h->active == 1): ?>
                                            <span class="badge badge-active">✅ Aktív</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">🚫 Inaktív</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($h->subscription_status === 'trial'): ?>
                                            <span class="badge badge-trial">Trial</span><br>
                                            <small><?php echo date('Y-m-d', strtotime($h->trial_ends_at)); ?></small>
                                        <?php elseif ($h->subscription_status === 'active'): ?>
                                            <span class="badge badge-active-sub">Előfizető</span><br>
                                            <small><?php echo date('Y-m-d', strtotime($h->subscription_expires_at)); ?></small>
                                        <?php else: ?>
                                            <span class="badge"><?php echo $h->subscription_status; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $h->filiale_count; ?></td>
                                    <td><?php echo $h->scanner_count; ?></td>
                                    <td>
                                        <span class="store-key" title="Kattints a másoláshoz">
                                            <?php echo substr($h->store_key, 0, 8); ?>...
                                        </span>
                                    </td>
                                    <td><small><?php echo date('Y-m-d H:i', strtotime($h->created_at)); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Handler Details Modal -->
    <div id="handlerModal" class="handler-modal-overlay">
        <div class="handler-modal">
            <div class="handler-modal-header">
                <div>
                    <h2 id="handlerModalTitle"><i class="ri-store-2-line"></i> Handler</h2>
                    <div class="handler-modal-stats" id="handlerModalStats"></div>
                </div>
                <button class="handler-modal-close" onclick="closeHandlerModal()"><i class="ri-close-line"></i></button>
            </div>
            <div class="handler-modal-body">
                <!-- Tabs -->
                <div class="handler-tabs">
                    <button class="handler-tab-btn active" onclick="switchHandlerTab('devices')"><i class="ri-smartphone-line"></i> Készülékek</button>
                    <button class="handler-tab-btn" onclick="switchHandlerTab('subscription')"><i class="ri-calendar-line"></i> Előfizetés</button>
                    <button class="handler-tab-btn" onclick="switchHandlerTab('mobile')"><i class="ri-phone-line"></i> Mobile Scanner</button>
                    <button class="handler-tab-btn" onclick="switchHandlerTab('filialen')"><i class="ri-building-line"></i> Fiókok</button>
                </div>

                <!-- Tab: Devices -->
                <div id="handler-tab-devices" class="handler-tab-content active">
                    <div id="handlerDeviceList" class="handler-device-list"></div>
                </div>

                <!-- Tab: Subscription -->
                <div id="handler-tab-subscription" class="handler-tab-content">
                    <div id="handlerSubscriptionInfo" class="handler-subscription-info"></div>

                    <div class="handler-info-box">
                        <h4><i class="ri-time-line"></i> Előfizetés meghosszabbítása</h4>
                        <p>Add meg a napok számát amivel meg szeretnéd hosszabbítani</p>
                    </div>
                    <form method="POST" action="/admin/handler-extend" style="margin-bottom: 25px;">
                        <input type="hidden" name="handler_id" id="extendHandlerId">
                        <div style="display: flex; gap: 10px; align-items: flex-end;">
                            <div style="flex: 1;">
                                <label style="display: block; font-size: 12px; color: #888; margin-bottom: 6px;">Napok száma</label>
                                <input type="number" name="days" min="1" max="365" value="30" required
                                       style="width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff;">
                            </div>
                            <button type="submit" class="btn" style="background: #00e6ff; color: #000;">
                                <i class="ri-add-line"></i> Meghosszabbítás
                            </button>
                        </div>
                    </form>

                    <hr style="border: none; border-top: 1px solid rgba(255,255,255,0.1); margin: 25px 0;">

                    <div class="handler-info-box handler-info-box-success" id="activateBox">
                        <h4><i class="ri-checkbox-circle-line"></i> Aktiválás</h4>
                        <p>Trial státuszból aktív előfizetésre váltás (6 hónap)</p>
                    </div>
                    <form method="POST" action="/admin/handler-activate" id="activateForm">
                        <input type="hidden" name="handler_id" id="activateHandlerId">
                        <button type="submit" class="btn" id="activateBtn" style="background: rgba(76,175,80,0.3); color: #81c784; border: 1px solid rgba(76,175,80,0.4);">
                            <i class="ri-check-line"></i> Aktiválás (6 hónap)
                        </button>
                    </form>
                </div>

                <!-- Tab: Mobile Scanner -->
                <div id="handler-tab-mobile" class="handler-tab-content">
                    <div class="handler-info-box">
                        <h4><i class="ri-phone-line"></i> Mobile Scanner Mód</h4>
                        <p>Állítsd be hogy a handler használhat-e mobile scannert (GPS nélkül)</p>
                    </div>
                    <form method="POST" action="/admin/handler-mobile">
                        <input type="hidden" name="handler_id" id="mobileHandlerId">

                        <div style="margin-bottom: 20px;">
                            <label class="handler-toggle-option">
                                <input type="radio" name="mobile_mode" value="off">
                                <div>
                                    <strong>Kikapcsolva (Fixed)</strong>
                                    <span style="display: block; font-size: 11px; color: #888;">GPS kötelező minden scanneléshez</span>
                                </div>
                            </label>

                            <label class="handler-toggle-option">
                                <input type="radio" name="mobile_mode" value="global">
                                <div>
                                    <strong>Globális Mobile Mód</strong>
                                    <span style="display: block; font-size: 11px; color: #888;">Összes jelenlegi és jövőbeli készülékre érvényes</span>
                                </div>
                            </label>

                            <label class="handler-toggle-option">
                                <input type="radio" name="mobile_mode" value="per_device">
                                <div>
                                    <strong>Készülékenként</strong>
                                    <span style="display: block; font-size: 11px; color: #888;">Csak kiválasztott készülékekre (a Készülékek tabon állítható)</span>
                                </div>
                            </label>
                        </div>

                        <button type="submit" class="btn" style="background: rgba(156,39,176,0.3); color: #ce93d8; border: 1px solid rgba(156,39,176,0.4);">
                            <i class="ri-save-line"></i> Mentés
                        </button>
                    </form>
                </div>

                <!-- Tab: Filialen -->
                <div id="handler-tab-filialen" class="handler-tab-content">
                    <div id="handlerFilialenInfo"></div>

                    <div class="handler-info-box">
                        <h4><i class="ri-building-line"></i> Fiók limit</h4>
                        <p>Állítsd be hány fiókot (filiále) hozhat létre a handler</p>
                    </div>
                    <form method="POST" action="/admin/handler-filialen">
                        <input type="hidden" name="handler_id" id="filialenHandlerId">
                        <div style="display: flex; gap: 10px; align-items: flex-end;">
                            <div style="flex: 1;">
                                <label style="display: block; font-size: 12px; color: #888; margin-bottom: 6px;">Maximum fiókok száma</label>
                                <input type="number" name="max_filialen" id="maxFilialenInput" min="1" max="100" value="1" required
                                       style="width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff;">
                            </div>
                            <button type="submit" class="btn" style="background: #00e6ff; color: #000;">
                                <i class="ri-save-line"></i> Mentés
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Device Modal -->
    <div id="deleteModal" class="handler-modal-overlay">
        <div class="handler-modal" style="max-width: 450px;">
            <div class="handler-modal-header">
                <h2 style="color: #f44336;"><i class="ri-alert-line"></i> Eszköz törlése</h2>
                <button class="handler-modal-close" onclick="closeDeleteModal()"><i class="ri-close-line"></i></button>
            </div>
            <div class="handler-modal-body">
                <div style="background: rgba(244,67,54,0.1); border: 1px solid rgba(244,67,54,0.3); border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                    <p style="margin: 0; color: #fff;"><strong id="deleteDeviceName"></strong></p>
                    <p style="margin: 5px 0 0 0; color: #00e6ff;" id="deleteDeviceModel"></p>
                    <p style="margin: 5px 0 0 0; color: #888; font-size: 12px;">Handler: <span id="deleteStoreName"></span></p>
                </div>
                <form method="POST" action="/admin/delete-device">
                    <input type="hidden" name="device_id" id="deleteDeviceId">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; color: #fff; margin-bottom: 8px;">Törlés oka:</label>
                        <textarea name="reason" required rows="2"
                                  style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 12px; color: #fff; font-size: 14px; resize: none;"
                                  placeholder="pl. Ügyfél kérése, elveszett eszköz..."></textarea>
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" onclick="closeDeleteModal()" class="btn" style="background: rgba(255,255,255,0.1); color: #fff;">Mégse</button>
                        <button type="submit" class="btn" style="background: rgba(244,67,54,0.3); color: #ef5350; border: 1px solid rgba(244,67,54,0.4);"><i class="ri-delete-bin-line"></i> Törlés</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById('tab-' + tabName).classList.add('active');

            // Activate button
            event.target.closest('.tab-button').classList.add('active');
        }

        // Filter handlers (for overview tab)
        function filterHandlers() {
            const searchValue = document.getElementById('handlerSearch').value.toLowerCase();
            const statusValue = document.getElementById('statusFilter').value;
            const scannerValue = document.getElementById('scannerFilter').value;

            const rows = document.querySelectorAll('.handler-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.dataset.name || '';
                const city = row.dataset.city || '';
                const email = row.dataset.email || '';
                const status = row.dataset.status || '';
                const scanner = row.dataset.scanner || '';

                const matchesSearch = name.includes(searchValue) || city.includes(searchValue) || email.includes(searchValue);
                const matchesStatus = !statusValue || status === statusValue;
                const matchesScanner = !scannerValue || scanner === scannerValue;

                if (matchesSearch && matchesStatus && matchesScanner) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('handlerCount').textContent = `${visibleCount} handler`;
        }

        // Initialize filter count
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('handlerSearch')) {
                filterHandlers();
            }
        });

        // Handler modal
        let currentHandler = null;

        function openHandlerModal(handler) {
            currentHandler = handler;
            const modal = document.getElementById('handlerModal');

            // Set title and stats
            document.getElementById('handlerModalTitle').innerHTML = `<i class="ri-store-2-line"></i> ${handler.name}`;
            document.getElementById('handlerModalStats').innerHTML = `
                <span><strong>Email:</strong> ${handler.email || '-'}</span>
                <span><strong>Város:</strong> ${handler.city || '-'}</span>
                <span><strong>Státusz:</strong> ${handler.subscription_status || 'N/A'}</span>
            `;

            // Set hidden form IDs
            document.getElementById('extendHandlerId').value = handler.id;
            document.getElementById('activateHandlerId').value = handler.id;
            document.getElementById('mobileHandlerId').value = handler.id;
            document.getElementById('filialenHandlerId').value = handler.id;

            // Populate Devices Tab
            populateDevicesTab(handler);

            // Populate Subscription Tab
            populateSubscriptionTab(handler);

            // Populate Mobile Scanner Tab
            populateMobileTab(handler);

            // Populate Filialen Tab
            populateFilialenTab(handler);

            modal.classList.add('active');
        }

        function populateDevicesTab(handler) {
            const deviceList = document.getElementById('handlerDeviceList');

            if (!handler.devices || handler.devices.length === 0) {
                deviceList.innerHTML = '<p style="color: #888; text-align: center; padding: 30px;">Nincs regisztrált készülék</p>';
                return;
            }

            let html = '';
            handler.devices.forEach(device => {
                html += `
                    <div class="handler-device-item">
                        <div style="flex: 1;">
                            <div class="handler-device-name">${device.name || 'Névtelen készülék'}</div>
                            <div class="handler-device-meta">${device.os || 'Unknown OS'} • ${device.model || 'Unknown Model'} • IP: ${device.ip || 'N/A'}</div>
                            <div class="handler-device-badges">
                                ${device.mobile_scanner ? '<span class="handler-device-badge" style="background: rgba(0,230,255,0.2); color: #00e6ff;">Mobile Scanner</span>' : ''}
                                <span class="handler-device-badge">Reg: ${device.registered_at ? new Date(device.registered_at).toLocaleDateString('hu-HU') : 'N/A'}</span>
                            </div>
                        </div>
                        <div class="handler-device-actions">
                            ${handler.scanner_type === 'per_device' || !handler.scanner_type ? `
                                <form method="POST" action="/admin/device-mobile-enable" style="display: inline;">
                                    <input type="hidden" name="device_id" value="${device.id}">
                                    <button type="submit" class="btn" style="background: rgba(0,230,255,0.2); color: #00e6ff; font-size: 11px; padding: 6px 10px;">
                                        <i class="ri-phone-line"></i> ${device.mobile_scanner ? 'Disable' : 'Enable'} Mobile
                                    </button>
                                </form>
                            ` : ''}
                            <button onclick="openDeleteModal(${device.id}, '${device.name || 'Készülék'}', '${device.os || 'Unknown'} • ${device.model || 'Unknown'}', '${handler.name}')"
                                    class="btn" style="background: rgba(244,67,54,0.2); color: #ef5350; font-size: 11px; padding: 6px 10px;">
                                <i class="ri-delete-bin-line"></i> Törlés
                            </button>
                        </div>
                    </div>
                `;
            });

            deviceList.innerHTML = html;
        }

        function populateSubscriptionTab(handler) {
            const subInfo = document.getElementById('handlerSubscriptionInfo');

            let trialDate = handler.trial_ends_at ? new Date(handler.trial_ends_at).toLocaleDateString('hu-HU') : 'N/A';
            let subDate = handler.subscription_expires_at ? new Date(handler.subscription_expires_at).toLocaleDateString('hu-HU') : 'N/A';
            let statusClass = handler.subscription_status === 'active' ? 'active' : (handler.subscription_status === 'trial' ? 'trial' : 'expired');

            subInfo.innerHTML = `
                <div class="handler-sub-card">
                    <div class="label">Státusz</div>
                    <div class="value ${statusClass}">${handler.subscription_status || 'N/A'}</div>
                </div>
                <div class="handler-sub-card">
                    <div class="label">Trial vége</div>
                    <div class="value">${trialDate}</div>
                </div>
                <div class="handler-sub-card">
                    <div class="label">Előfizetés vége</div>
                    <div class="value">${subDate}</div>
                </div>
            `;

            // Hide activate button if already active
            if (handler.subscription_status === 'active') {
                document.getElementById('activateBox').style.display = 'none';
                document.getElementById('activateForm').style.display = 'none';
            } else {
                document.getElementById('activateBox').style.display = 'block';
                document.getElementById('activateForm').style.display = 'block';
            }
        }

        function populateMobileTab(handler) {
            // Set radio button values based on scanner_type
            const scannerType = handler.scanner_type || 'fixed';
            document.querySelectorAll('input[name="mobile_mode"]').forEach(radio => {
                if (scannerType === 'fixed' && radio.value === 'off') {
                    radio.checked = true;
                } else if (scannerType === 'mobile' && radio.value === 'global') {
                    radio.checked = true;
                } else if (scannerType === 'per_device' && radio.value === 'per_device') {
                    radio.checked = true;
                }
            });
        }

        function populateFilialenTab(handler) {
            const filialenInfo = document.getElementById('handlerFilialenInfo');
            const currentFilialen = handler.filiale_count || 0;
            const maxFilialen = handler.max_filialen || 1;

            filialenInfo.innerHTML = `
                <div class="handler-sub-card" style="margin-bottom: 20px;">
                    <div class="label">Jelenlegi fiókok</div>
                    <div class="value" style="color: ${currentFilialen >= maxFilialen ? '#ef5350' : '#81c784'};">${currentFilialen} / ${maxFilialen}</div>
                </div>
            `;

            document.getElementById('maxFilialenInput').value = maxFilialen;
        }

        function switchHandlerTab(tabName) {
            // Remove active from all tabs
            document.querySelectorAll('.handler-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.handler-tab-content').forEach(content => content.classList.remove('active'));

            // Add active to selected tab
            event.target.classList.add('active');
            document.getElementById('handler-tab-' + tabName).classList.add('active');
        }

        function closeHandlerModal() {
            document.getElementById('handlerModal').classList.remove('active');
        }

        // Delete Device Modal
        function openDeleteModal(deviceId, deviceName, deviceModel, storeName) {
            document.getElementById('deleteDeviceId').value = deviceId;
            document.getElementById('deleteDeviceName').textContent = deviceName;
            document.getElementById('deleteDeviceModel').textContent = deviceModel;
            document.getElementById('deleteStoreName').textContent = storeName;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        // Close modals on outside click
        document.getElementById('handlerModal').addEventListener('click', function(e) {
            if (e.target === this) closeHandlerModal();
        });
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });
    </script>
</body>
</html>
