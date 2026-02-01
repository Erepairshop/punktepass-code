<?php
/**
 * Standalone Repair Bonus API Endpoint
 * Bypasses WordPress REST API entirely - no filters, no permission_callback middleware.
 * Loads WordPress for DB access and wp_mail, but handles auth directly.
 *
 * URL: https://punktepass.de/wp-content/plugins/punktepass/api-repair-bonus.php
 */

// Load WordPress
$wp_load = dirname(__FILE__) . '/../../../wp-load.php';
if (!file_exists($wp_load)) {
    // Try alternative paths
    $wp_load = dirname(__FILE__) . '/../../../../wp-load.php';
}
if (!file_exists($wp_load)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'WordPress not found']);
    exit;
}
require_once $wp_load;

// Set JSON response header
header('Content-Type: application/json; charset=utf-8');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Read API key from multiple sources
$api_key = '';
if (!empty($_GET['api_key'])) {
    $api_key = sanitize_text_field($_GET['api_key']);
}
if (empty($api_key) && !empty($_SERVER['HTTP_X_API_KEY'])) {
    $api_key = sanitize_text_field($_SERVER['HTTP_X_API_KEY']);
}

// Read JSON body
$raw_body = file_get_contents('php://input');
$params = json_decode($raw_body, true) ?: [];

if (empty($api_key) && !empty($params['api_key'])) {
    $api_key = sanitize_text_field($params['api_key']);
}

// Validate API key
if (empty($api_key)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Missing API key']);
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix;

// Look up store by API key
$api_store = $wpdb->get_row($wpdb->prepare(
    "SELECT id, name, email as store_email FROM {$prefix}ppv_stores WHERE pos_api_key=%s AND active=1 LIMIT 1",
    $api_key
));

if (!$api_store) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid API key']);
    exit;
}

// Extract parameters
$email     = sanitize_email($params['email'] ?? '');
$name      = sanitize_text_field($params['name'] ?? '');
$store_id  = intval($params['store_id'] ?? 0);
$points    = intval($params['points'] ?? 2);
$reference = sanitize_text_field($params['reference'] ?? 'Reparatur-Formular Bonus');

// Auto-detect store_id from API key if not provided
if (!$store_id) {
    $store_id = (int) $api_store->id;
}

if (empty($email) || !is_email($email)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid email']);
    exit;
}
if (!$store_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing store_id']);
    exit;
}

// Verify store exists
$store = $wpdb->get_row($wpdb->prepare(
    "SELECT id, name, email as store_email FROM {$prefix}ppv_stores WHERE id=%d AND active=1", $store_id
));
if (!$store) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Store not found']);
    exit;
}

// Check if user exists
$user = $wpdb->get_row($wpdb->prepare(
    "SELECT id, email, first_name FROM {$prefix}ppv_users WHERE email=%s LIMIT 1", $email
));

$is_new_user = false;
$generated_password = null;

if (!$user) {
    // Create new user
    $is_new_user = true;
    $generated_password = wp_generate_password(8, false, false);
    $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);
    $qr_token = wp_generate_password(10, false, false);
    $login_token = bin2hex(random_bytes(32));

    $name_parts = explode(' ', trim($name), 2);
    $first_name = $name_parts[0] ?? '';
    $last_name  = $name_parts[1] ?? '';

    $wpdb->insert("{$prefix}ppv_users", [
        'email'        => $email,
        'password'     => $password_hash,
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'display_name' => trim($name) ?: $first_name,
        'qr_token'     => $qr_token,
        'login_token'  => $login_token,
        'user_type'    => 'user',
        'active'       => 1,
        'created_at'   => current_time('mysql'),
        'updated_at'   => current_time('mysql'),
    ], ['%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s']);

    $user_id = $wpdb->insert_id;

    if (!$user_id) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'User creation failed']);
        exit;
    }
} else {
    $user_id = (int) $user->id;
}

// ✅ Duplicate protection: max 1 repair bonus per email per store per day
$today = current_time('Y-m-d');
$already_awarded = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$prefix}ppv_points
     WHERE user_id = %d AND store_id = %d AND type = 'bonus' AND reference = %s
       AND DATE(created) = %s",
    $user_id, $store_id, $reference, $today
));

if ($already_awarded > 0) {
    // Already got bonus today - return success silently (no duplicate points, no duplicate email)
    $total_points = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(points),0) FROM {$prefix}ppv_points WHERE user_id=%d AND store_id=%d",
        $user_id, $store_id
    ));
    echo json_encode([
        'status'       => 'ok',
        'is_new_user'  => $is_new_user,
        'user_id'      => $user_id,
        'points_added' => 0,
        'total_points' => $total_points,
        'duplicate'    => true,
    ]);
    exit;
}

// Add bonus points
$wpdb->insert("{$prefix}ppv_points", [
    'user_id'   => $user_id,
    'store_id'  => $store_id,
    'points'    => $points,
    'type'      => 'bonus',
    'reference' => $reference,
    'created'   => current_time('mysql'),
], ['%d','%d','%d','%s','%s','%s']);

// Log to ppv_pos_log so it appears in QR Center "Letzte Scans"
$wpdb->insert("{$prefix}ppv_pos_log", [
    'store_id'      => $store_id,
    'user_id'       => $user_id,
    'email'         => $email,
    'message'       => '+' . $points . ' Punkte (Reparatur-Bonus)',
    'type'          => 'qr_scan',
    'points_change' => $points,
    'status'        => 'ok',
    'ip_address'    => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'metadata'      => json_encode([
        'timestamp'   => current_time('mysql'),
        'type'        => 'repair_bonus',
        'reference'   => $reference,
        'is_new_user' => $is_new_user,
    ]),
    'created_at'    => current_time('mysql'),
]);

// Update lifetime_points
if (class_exists('PPV_User_Level') && $points > 0) {
    PPV_User_Level::add_lifetime_points($user_id, $points);
}

// Get total points for this store
$total_points = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(points),0) FROM {$prefix}ppv_points WHERE user_id=%d AND store_id=%d",
    $user_id, $store_id
));

// Send email
$first_name = $is_new_user ? (explode(' ', trim($name))[0] ?: 'Kunde') : ($user->first_name ?: 'Kunde');

if ($is_new_user) {
    // Welcome email with credentials
    $points_to_reward = max(0, 4 - $total_points);
    $subject = "Willkommen bei PunktePass – {$points} Bonuspunkte von {$store->name}!";

    // Reward claim section (when 4+ points reached)
    $reward_section = '';
    if ($total_points >= 4) {
        $reward_section = '
    <div style="background:linear-gradient(135deg,#fef3c7,#fde68a);border:1px solid #f59e0b;border-radius:12px;padding:20px;margin:0 0 24px;text-align:center;">
        <div style="font-size:28px;margin-bottom:8px;">🎉🏆</div>
        <div style="font-size:18px;font-weight:700;color:#92400e;margin-bottom:8px;">Sie haben 10€ Rabatt gewonnen!</div>
        <p style="font-size:13px;color:#78350f;margin:0 0 16px;line-height:1.5;">
            Melden Sie sich bei <strong>PunktePass</strong> an, zeigen Sie Ihren QR-Code im Geschäft vor und lösen Sie Ihre Prämie ein!
        </p>
        <a href="https://punktepass.de" target="_blank" style="display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;text-decoration:none;border-radius:10px;font-size:14px;font-weight:700;">
            Prämie einlösen &rarr;
        </a>
    </div>';
    }

    $body = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
<div style="max-width:560px;margin:0 auto;padding:20px;">
<div style="background:linear-gradient(135deg,#667eea,#764ba2);border-radius:16px 16px 0 0;padding:32px 28px;text-align:center;">
    <h1 style="color:#fff;font-size:24px;margin:0 0 8px;">Willkommen bei PunktePass!</h1>
    <p style="color:rgba(255,255,255,0.85);font-size:14px;margin:0;">Ihr Treuekonto bei ' . esc_html($store->name) . '</p>
</div>
<div style="background:#fff;padding:32px 28px;border-radius:0 0 16px 16px;">
    <p style="font-size:16px;color:#1f2937;margin:0 0 20px;">Hallo <strong>' . esc_html($first_name) . '</strong>,</p>
    <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">vielen Dank! Wir haben ein <strong>PunktePass-Konto</strong> für Sie erstellt.</p>
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;text-align:center;margin:0 0 24px;">
        <div style="font-size:36px;font-weight:800;color:#059669;">+' . $points . ' Punkte</div>
        <div style="font-size:13px;color:#6b7280;margin-top:4px;">Gesamt: ' . $total_points . ' / 4 Punkte' . ($points_to_reward > 0 ? ' — noch ' . $points_to_reward . ' bis 10€ Rabatt!' : ' — 10€ Rabatt einlösbar!') . '</div>
    </div>
    ' . $reward_section . '
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin:0 0 24px;">
        <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:12px;">IHRE ZUGANGSDATEN</div>
        <p style="margin:4px 0;font-size:14px;"><strong>E-Mail:</strong> ' . esc_html($email) . '</p>
        <p style="margin:4px 0;font-size:14px;"><strong>Passwort:</strong> <code>' . esc_html($generated_password) . '</code></p>
    </div>
    <div style="text-align:center;"><a href="https://punktepass.de" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;">Jetzt anmelden</a></div>
</div>
</div></body></html>';
    wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
} else {
    // Points notification email
    $points_to_reward = max(0, 4 - $total_points);

    // Reward claim section (when 4+ points reached)
    $reward_section = '';
    if ($total_points >= 4) {
        $subject = "🏆 Sie haben 10€ Rabatt bei {$store->name} gewonnen!";
        $reward_section = '
    <div style="background:linear-gradient(135deg,#fef3c7,#fde68a);border:1px solid #f59e0b;border-radius:12px;padding:20px;margin:0 0 24px;text-align:center;">
        <div style="font-size:28px;margin-bottom:8px;">🎉🏆</div>
        <div style="font-size:18px;font-weight:700;color:#92400e;margin-bottom:8px;">Sie haben 10€ Rabatt gewonnen!</div>
        <p style="font-size:13px;color:#78350f;margin:0 0 16px;line-height:1.5;">
            Melden Sie sich bei <strong>PunktePass</strong> an, zeigen Sie Ihren QR-Code im Geschäft vor und lösen Sie Ihre Prämie ein!
        </p>
        <a href="https://punktepass.de" target="_blank" style="display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;text-decoration:none;border-radius:10px;font-size:14px;font-weight:700;">
            Prämie einlösen &rarr;
        </a>
    </div>';
    } else {
        $subject = "+{$points} Bonuspunkte auf Ihr PunktePass-Konto!";
    }

    $body = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
<div style="max-width:560px;margin:0 auto;padding:20px;">
<div style="background:linear-gradient(135deg,#10b981,#059669);border-radius:16px 16px 0 0;padding:32px 28px;text-align:center;">
    <div style="font-size:48px;font-weight:800;color:#fff;">+' . $points . '</div>
    <p style="color:rgba(255,255,255,0.9);font-size:15px;margin:8px 0 0;">Bonuspunkte gutgeschrieben</p>
</div>
<div style="background:#fff;padding:32px 28px;border-radius:0 0 16px 16px;">
    <p style="font-size:16px;color:#1f2937;margin:0 0 20px;">Hallo <strong>' . esc_html($first_name) . '</strong>,</p>
    <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">+' . $points . ' Bonuspunkte von ' . esc_html($store->name) . ' gutgeschrieben.</p>
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;text-align:center;margin:0 0 24px;">
        <div style="font-size:36px;font-weight:800;color:#059669;">' . $total_points . ' Punkte</div>
        <div style="font-size:13px;color:#374151;margin-top:8px;">' . ($points_to_reward > 0 ? 'Noch ' . $points_to_reward . ' bis 10€ Rabatt!' : '10€ Rabatt einlösbar!') . '</div>
    </div>
    ' . $reward_section . '
    <div style="text-align:center;"><a href="https://punktepass.de" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;">Punkte ansehen</a></div>
</div>
</div></body></html>';
    wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
}

// Return success
echo json_encode([
    'status'       => 'ok',
    'is_new_user'  => $is_new_user,
    'user_id'      => $user_id,
    'points_added' => $points,
    'total_points' => $total_points,
]);
