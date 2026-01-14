<?php
/**
 * PunktePass Standalone Admin - Handler Management
 * Convert users to handlers and manage handlers
 */

// Must be accessed via WordPress
if (!defined('ABSPATH')) exit;

// Security check - only admin can access
if (!current_user_can('manage_options')) {
    wp_die('Nincs jogosultság / No permission');
}

global $wpdb;

// ============================================================
// HANDLE FORM SUBMISSIONS
// ============================================================

// Convert user to handler
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

            // Calculate trial end (30 days)
            $trial_ends_at = date('Y-m-d H:i:s', strtotime('+30 days'));

            // Determine currency based on country
            $currency_map = [
                'DE' => 'EUR',
                'AT' => 'EUR',
                'HU' => 'HUF',
                'RO' => 'RON',
                'SK' => 'EUR',
                'HR' => 'EUR',
                'SI' => 'EUR'
            ];
            $currency = $currency_map[$country] ?? 'EUR';

            // Create store
            $result = $wpdb->insert(
                "{$wpdb->prefix}ppv_stores",
                [
                    'user_id' => $user->ID,
                    'name' => $user->display_name,
                    'company_name' => $company_name,
                    'email' => $store_email,
                    'country' => $country,
                    'currency' => $currency,
                    'store_key' => $store_key,
                    'trial_ends_at' => $trial_ends_at,
                    'subscription_status' => 'trial',
                    'active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
            );

            if ($result) {
                $new_store_id = $wpdb->insert_id;
                ppv_log("✅ [PPV_Admin] User #{$user->ID} ({$user->user_email}) zu Handler konvertiert. Store ID: {$new_store_id}");
                $success_message = "✅ User '{$user->display_name}' ({$user->user_email}) sikeresen handler-ré lett téve!<br>
                                    <strong>Store ID:</strong> {$new_store_id}<br>
                                    <strong>Store Key:</strong> <code>{$store_key}</code><br>
                                    <strong>Trial vége:</strong> {$trial_ends_at}<br>
                                    <strong>Country:</strong> {$country} | <strong>Currency:</strong> {$currency}";
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
                    'currency' => 'EUR',
                    'store_key' => $store_key,
                    'trial_ends_at' => $trial_ends_at,
                    'subscription_status' => 'trial',
                    'active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
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

// Get all PPV users who are NOT handlers yet (user_type = 'user', NOT 'store'/'handler'/'vendor')
$non_handler_users = $wpdb->get_results("
    SELECT u.id, u.username, u.email, u.created_at, u.user_type
    FROM {$wpdb->prefix}ppv_users u
    LEFT JOIN {$wpdb->prefix}ppv_stores s ON u.id = s.user_id
    WHERE s.id IS NULL
    AND u.user_type IN ('user', 'customer')
    ORDER BY u.created_at DESC
");

// Get all handlers
$handlers = $wpdb->get_results("
    SELECT
        s.id,
        s.user_id,
        s.name,
        s.company_name,
        s.email,
        s.phone,
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
        (SELECT COUNT(*) FROM {$wpdb->prefix}ppv_users WHERE user_type = 'scanner' AND (vendor_store_id = s.id OR vendor_store_id IN (SELECT id FROM {$wpdb->prefix}ppv_stores WHERE parent_store_id = s.id))) as scanner_count
    FROM {$wpdb->prefix}ppv_stores s
    LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
    WHERE s.parent_store_id IS NULL
    ORDER BY s.id DESC
");

?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PunktePass Admin - Handler Management</title>
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🏪 Handler Management</h1>
        <p class="subtitle">User to Handler konverzió és handler kezelés</p>

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

        <!-- PunktePass Users List (Not Handlers Yet) -->
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
            <h2>📋 Összes Handler (<?php echo count($handlers); ?>)</h2>

            <?php if (empty($handlers)): ?>
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
                        <?php foreach ($handlers as $h): ?>
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
                                <td><?php echo $h->country; ?> (<?php echo $h->currency; ?>)</td>
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
</body>
</html>
