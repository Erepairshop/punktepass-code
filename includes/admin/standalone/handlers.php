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
        s.currency,
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

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: linear-gradient(135deg, #0f1729 0%, #1a1a2e 100%);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 16px;
            padding: 32px;
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 28px;
            cursor: pointer;
            color: #94a3b8;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: #f87171;
        }
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
    <div id="handlerModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeHandlerModal()">&times;</span>
            <div id="handlerModalContent">
                <!-- Content will be populated by JavaScript -->
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
        function openHandlerModal(handler) {
            const modal = document.getElementById('handlerModal');
            const content = document.getElementById('handlerModalContent');

            let devicesHtml = '';
            if (handler.devices && handler.devices.length > 0) {
                devicesHtml = '<h3 style="color: #00d4ff; margin-top: 20px;">Készülékek (' + handler.devices.length + ')</h3>';
                devicesHtml += '<table class="handlers-table"><thead><tr><th>Név</th><th>OS</th><th>Model</th><th>Mobile Scanner</th><th>IP</th><th>Regisztrálva</th></tr></thead><tbody>';
                handler.devices.forEach(d => {
                    devicesHtml += '<tr>';
                    devicesHtml += '<td>' + (d.name || 'N/A') + '</td>';
                    devicesHtml += '<td>' + (d.os || 'Unknown') + '</td>';
                    devicesHtml += '<td>' + (d.model || 'Unknown') + '</td>';
                    devicesHtml += '<td>' + (d.mobile_scanner ? '<span class="badge badge-mobile">Mobile</span>' : '-') + '</td>';
                    devicesHtml += '<td><small>' + (d.ip || 'N/A') + '</small></td>';
                    devicesHtml += '<td><small>' + (d.registered_at || 'N/A') + '</small></td>';
                    devicesHtml += '</tr>';
                });
                devicesHtml += '</tbody></table>';
            } else {
                devicesHtml = '<p style="color: #94a3b8; margin-top: 20px;">Nincs regisztrált készülék.</p>';
            }

            content.innerHTML = `
                <h2 style="color: #00d4ff; margin-bottom: 20px;">🏪 ${handler.name}</h2>
                <p><strong>Cégnév:</strong> ${handler.company_name || '-'}</p>
                <p><strong>Email:</strong> ${handler.email || '-'}</p>
                <p><strong>Város:</strong> ${handler.city || '-'}</p>
                <p><strong>Scanner típus:</strong> ${handler.scanner_type === 'mobile' ? '<span class="badge badge-mobile">Mobile</span>' : '<span class="badge">Fixed</span>'}</p>
                <p><strong>Előfizetés:</strong> ${handler.subscription_status || 'N/A'}</p>
                <p><strong>Fiókok:</strong> ${handler.filiale_count}/${handler.max_filialen}</p>
                ${devicesHtml}
            `;

            modal.classList.add('active');
        }

        function closeHandlerModal() {
            document.getElementById('handlerModal').classList.remove('active');
        }

        // Close modal on outside click
        document.getElementById('handlerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeHandlerModal();
            }
        });
    </script>
</body>
</html>
