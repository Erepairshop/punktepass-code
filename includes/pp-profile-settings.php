<?php
if (!defined('ABSPATH')) exit;
wp_enqueue_script('pp-profile-settings', PPV_PLUGIN_URL . 'assets/js/pp-profile-settings.js', ['jquery'], time(), true);
$__data = is_array([
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce'    => wp_create_nonce('ppv_profile_nonce')
] ?? null) ? [
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce'    => wp_create_nonce('ppv_profile_nonce')
] : [];
$__json = wp_json_encode($__data);
wp_add_inline_script('pp-profile-settings', "window.ppv_ajax = {$__json};", 'before');
/**
 * PunktePass – Händler Einstellungen (Konto, Abo, Allgemeine)
 * Végleges működő verzió
 */

global $wpdb;
if (session_status() === PHP_SESSION_NONE) @session_start();
$user_id = intval($_SESSION['ppv_user_id'] ?? get_current_user_id());
// Resolve shared store: vendor_store_id (access user) OR own store
$__ppv_store_id = intval($_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_store_id'] ?? 0);
if (!$__ppv_store_id && $user_id) {
    $__ppv_store_id = intval($wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(NULLIF(vendor_store_id,0), (SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1))
         FROM {$wpdb->prefix}ppv_users WHERE id=%d", $user_id, $user_id)));
}
$store = $__ppv_store_id
    ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id=%d", $__ppv_store_id))
    : null;
$settings = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pp_store_settings WHERE store_id=%d", $store->id));

if (!$store) {
    echo '<p>Kein Store gefunden.</p>';
    return;
}
?>

<div class="ppv-settings-wrapper">
    <h2>⚙️ Konto & Einstellungen</h2>
    <p class="ppv-settings-subtitle">Verwalte dein Konto, Abo und allgemeine Präferenzen.</p>

    <div class="ppv-settings-sections">

        <!-- 🔐 Konto & Sicherheit -->
        <section class="ppv-settings-section">
            <h3>🔐 Konto & Sicherheit</h3>

            <p><label>E-Mail-Adresse:</label><br>
                <input type="email" id="ppv-email" value="<?php echo esc_attr($store->email ?? ''); ?>">
                <button type="button" class="ppv-btn-outline">E-Mail ändern</button>
            </p>

            <p><label>Passwort ändern:</label><br>
                <input type="password" id="ppv-pass-old" placeholder="Aktuelles Passwort">
                <input type="password" id="ppv-pass-new" placeholder="Neues Passwort">
                <button type="button" class="ppv-btn-outline">Passwort speichern</button>
            </p>

            <hr>
            <p><button type="button" class="ppv-btn-danger">Konto löschen</button></p>
        </section>

        <!-- 💳 Abo & Zahlung -->
        <section class="ppv-settings-section">
            <h3>💳 Abo & Zahlung</h3>

            <div class="ppv-abo-box">
                <p><strong>Aktueller Tarif:</strong>
                    <?php echo esc_html($store->subscription_plan ?? 'PRO'); ?>
                </p>

                <p><strong>Preis:</strong>
                    <?php
                    $price = $store->subscription_price ?? 30.00;
                    $vat = $store->subscription_vat ?? 19.00;
                    $brutto = round($price * (1 + $vat / 100), 2);
                    echo number_format($price, 2) . " € + " . $vat . "% MwSt = <b>" . number_format($brutto, 2) . " € / Monat</b>";
                    ?>
                </p>

                <p><strong>Status:</strong>
                    <?php echo esc_html($store->subscription_status ?? 'active'); ?>
                </p>

                <?php if (!empty($store->next_billing_date)) : ?>
                    <p><strong>Nächste Abbuchung:</strong> <?php echo esc_html($store->next_billing_date); ?></p>
                <?php endif; ?>
            </div>

            <div class="ppv-abo-actions">
                <button type="button" class="ppv-btn">Zahlungsdaten aktualisieren</button>

                <?php
                $status = $store->subscription_status ?? 'active';
                if ($status === 'paused') {
                    echo '<button type="button" class="ppv-btn-outline">Abo fortsetzen</button>';
                } elseif ($status === 'active') {
                    echo '<button type="button" class="ppv-btn-outline">Abo pausieren</button>';
                } else {
                    echo '<button type="button" class="ppv-btn-outline">Abo reaktivieren</button>';
                }
                ?>
                
                <button type="button" class="ppv-btn-outline">Abo kündigen</button>
                <button type="button" class="ppv-btn-outline">Rechnungen öffnen (Billbee)</button>
            </div>
        </section>

        <!-- ⚙️ Allgemeine Einstellungen -->
        <section class="ppv-settings-section">
            <h3>⚙️ Allgemeine Einstellungen</h3>

            <p>
                <label><input type="checkbox" id="ppv-notify-email" <?php checked($settings->notify_email ?? 1, 1); ?>> E-Mail-Benachrichtigungen aktiv</label>
            </p>

            <p>
                <label><input type="checkbox" id="ppv-notify-push" <?php checked($settings->notify_push ?? 0, 1); ?>> Push-Benachrichtigungen aktiv</label>
            </p>

            <p>
                <label><input type="checkbox" id="ppv-dark-mode" <?php checked($settings->dark_mode ?? 0, 1); ?>> Dunkler Modus aktivieren</label>
            </p>

            <p>
                <label><input type="checkbox" id="ppv-profile-public" <?php checked($settings->profile_public ?? 1, 1); ?>> Händlerprofil öffentlich sichtbar</label>
            </p>

            <hr>
            <button type="button" class="ppv-btn ppv-btn-save">Änderungen speichern</button>
            <div class="ppv-save-message" style="margin-top:10px;color:#4caf50;display:none;">Gespeichert ✅</div>
        </section>

    </div>
</div>

<style>
.ppv-settings-wrapper { padding:20px; max-width:800px; }
.ppv-settings-section { background:#fff; padding:20px; border-radius:12px; margin-bottom:25px; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
.ppv-settings-section h3 { margin-bottom:15px; }
.ppv-settings-section input[type="email"],
.ppv-settings-section input[type="password"] { width:100%; max-width:400px; padding:8px; margin-bottom:10px; border:1px solid #ccc; border-radius:8px; }
.ppv-btn, .ppv-btn-outline, .ppv-btn-danger {
    padding:8px 14px; border-radius:8px; cursor:pointer; font-weight:500; margin-right:8px;
}
.ppv-btn { background:#0073aa; color:#fff; border:none; }
.ppv-btn-outline { border:1px solid #0073aa; color:#0073aa; background:transparent; }
.ppv-btn-danger { background:#d9534f; color:#fff; border:none; }
.ppv-abo-box { background:#f9f9f9; border:1px solid #e0e0e0; border-radius:10px; padding:15px; margin-bottom:10px; }
.ppv-abo-actions button { margin-top:5px; }
.ppv-settings-subtitle { color:#555; margin-bottom:25px; }
</style>
<?php
// ===================================================
// 🔹 AJAX: Einstellungen speichern
// ===================================================
add_action('wp_ajax_ppv_save_settings', function() {
    check_ajax_referer('ppv_profile_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Nicht eingeloggt']);

    global $wpdb;
    $user_id = get_current_user_id();
    $sid = intval($_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_store_id'] ?? 0);
    if (!$sid && $user_id) {
        $sid = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(NULLIF(vendor_store_id,0), (SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1))
             FROM {$wpdb->prefix}ppv_users WHERE id=%d", $user_id, $user_id)));
    }
    $store = $sid ? $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ppv_stores WHERE id=%d", $sid)) : null;
    if (!$store) wp_send_json_error(['message' => 'Kein Store gefunden']);

    $data = [
        'notify_email'   => intval($_POST['notify_email'] ?? 0),
        'notify_push'    => intval($_POST['notify_push'] ?? 0),
        'dark_mode'      => intval($_POST['dark_mode'] ?? 0),
        'profile_public' => intval($_POST['profile_public'] ?? 0),
        'updated_at'     => current_time('mysql')
    ];

    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}pp_store_settings WHERE store_id=%d", $store->id));
    if ($exists) {
        $wpdb->update("{$wpdb->prefix}pp_store_settings", $data, ['store_id' => $store->id]);
    } else {
        $data['store_id'] = $store->id;
        $wpdb->insert("{$wpdb->prefix}pp_store_settings", $data);
    }

    wp_send_json_success(['message' => 'Einstellungen gespeichert']);
});

// ===================================================
// 🔹 AJAX: Abo pausieren / fortsetzen
// ===================================================
add_action('wp_ajax_ppv_toggle_abo_status', function() {
    check_ajax_referer('ppv_profile_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Nicht eingeloggt']);

    global $wpdb;
    if (session_status() === PHP_SESSION_NONE) @session_start();
    $user_id = intval($_SESSION['ppv_user_id'] ?? get_current_user_id());
    $sid = intval($_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_store_id'] ?? 0);
    if (!$sid && $user_id) {
        $sid = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(NULLIF(vendor_store_id,0), (SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1))
             FROM {$wpdb->prefix}ppv_users WHERE id=%d", $user_id, $user_id)));
    }
    $store = $sid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id=%d", $sid)) : null;
    if (!$store) wp_send_json_error(['message' => 'Kein Store gefunden']);

    $new_status = ($store->subscription_status === 'active') ? 'paused' : 'active';
    $wpdb->update("{$wpdb->prefix}ppv_stores", ['subscription_status' => $new_status], ['id' => $store->id]);

    wp_send_json_success(['new_status' => $new_status]);
});
