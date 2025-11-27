<?php
/**
 * PunktePass Standalone Admin Panel
 *
 * Elérhető: /admin - egyszerű email/jelszó bejelentkezéssel
 * Független a WordPress admintól
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Admin {

    /**
     * Hooks - a plugin által hívott metódus
     */
    public static function hooks() {
        add_action('init', [__CLASS__, 'run_migration'], 0);
        add_action('init', [__CLASS__, 'handle_admin_routes'], 1);
        add_action('rest_api_init', [__CLASS__, 'register_api_routes']);
    }

    /**
     * Migráció: admin_access oszlop hozzáadása a ppv_stores táblához
     */
    public static function run_migration() {
        global $wpdb;

        $migration_version = get_option('ppv_admin_migration_version', '0');

        // Migration 1.0: Add admin_access column
        if (version_compare($migration_version, '1.0', '<')) {
            $table = $wpdb->prefix . 'ppv_stores';

            // Ellenőrizzük hogy létezik-e már az oszlop
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'admin_access'");

            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN admin_access TINYINT(1) NOT NULL DEFAULT 0");
                ppv_log("✅ [PPV_Standalone_Admin] Added admin_access column to ppv_stores");
            }

            update_option('ppv_admin_migration_version', '1.0');
        }
    }

    /**
     * User Agent-ből eszköz info kinyerése
     */
    private static function parse_device_info($user_agent) {
        if (empty($user_agent)) {
            return ['model' => 'Ismeretlen', 'os' => '', 'browser' => ''];
        }

        $info = [
            'model' => '',
            'os' => '',
            'browser' => '',
            'raw' => $user_agent
        ];

        // iPhone detection
        if (preg_match('/iPhone/', $user_agent)) {
            $info['model'] = 'iPhone';
            if (preg_match('/iPhone OS ([\d_]+)/', $user_agent, $matches)) {
                $info['os'] = 'iOS ' . str_replace('_', '.', $matches[1]);
            }
        }
        // iPad detection
        elseif (preg_match('/iPad/', $user_agent)) {
            $info['model'] = 'iPad';
            if (preg_match('/CPU OS ([\d_]+)/', $user_agent, $matches)) {
                $info['os'] = 'iPadOS ' . str_replace('_', '.', $matches[1]);
            }
        }
        // Android device detection - try to get model
        elseif (preg_match('/Android/', $user_agent)) {
            // Try to extract model: Android X.X; MODEL Build/
            if (preg_match('/Android [\d.]+;\s*([^)]+?)\s*(?:Build|;|\))/', $user_agent, $matches)) {
                $model = trim($matches[1]);
                // Clean up common prefixes
                $model = preg_replace('/^(SAMSUNG|Samsung|LG|Xiaomi|HUAWEI|Huawei|OPPO|OnePlus|Realme|vivo|Motorola)\s*/i', '', $model);

                // Chrome 110+ privacy: "K" helyett valódi modell nem elérhető
                if ($model === 'K' || $model === 'k') {
                    $info['model'] = 'Android (rejtett)';
                    $info['note'] = 'Chrome privacy mód';
                } else {
                    $info['model'] = $model ?: 'Android';
                }
            } else {
                $info['model'] = 'Android';
            }
            if (preg_match('/Android ([\d.]+)/', $user_agent, $matches)) {
                $info['os'] = 'Android ' . $matches[1];
            }
        }
        // Windows
        elseif (preg_match('/Windows/', $user_agent)) {
            $info['model'] = 'Windows PC';
            if (preg_match('/Windows NT ([\d.]+)/', $user_agent, $matches)) {
                $versions = ['10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7'];
                $info['os'] = 'Windows ' . ($versions[$matches[1]] ?? $matches[1]);
            }
        }
        // Mac
        elseif (preg_match('/Macintosh/', $user_agent)) {
            $info['model'] = 'Mac';
            if (preg_match('/Mac OS X ([\d_]+)/', $user_agent, $matches)) {
                $info['os'] = 'macOS ' . str_replace('_', '.', $matches[1]);
            }
        }
        // Linux
        elseif (preg_match('/Linux/', $user_agent)) {
            $info['model'] = 'Linux PC';
            $info['os'] = 'Linux';
        }
        else {
            $info['model'] = 'Ismeretlen';
        }

        // Browser detection
        if (preg_match('/Chrome\/([\d.]+)/', $user_agent, $matches)) {
            $info['browser'] = 'Chrome ' . explode('.', $matches[1])[0];
        } elseif (preg_match('/Safari\/([\d.]+)/', $user_agent) && !preg_match('/Chrome/', $user_agent)) {
            $info['browser'] = 'Safari';
        } elseif (preg_match('/Firefox\/([\d.]+)/', $user_agent, $matches)) {
            $info['browser'] = 'Firefox ' . explode('.', $matches[1])[0];
        }

        return $info;
    }

    /**
     * FingerprintJS device_info JSON formázása megjelenítéshez
     */
    private static function format_device_info_json($device_info_json) {
        if (empty($device_info_json)) {
            return null;
        }

        $info = json_decode($device_info_json, true);
        if (!$info || !is_array($info)) {
            return null;
        }

        $formatted = [];

        // Képernyő
        if (!empty($info['screen'])) {
            $formatted['screen'] = $info['screen'];
        }

        // Memória
        if (!empty($info['memory'])) {
            $formatted['memory'] = $info['memory'] . ' GB';
        }

        // CPU magok
        if (!empty($info['cpuCores'])) {
            $formatted['cpuCores'] = $info['cpuCores'] . ' mag';
        }

        // Platform
        if (!empty($info['platform'])) {
            $formatted['platform'] = $info['platform'];
        }

        // Időzóna
        if (!empty($info['timezone'])) {
            $formatted['timezone'] = $info['timezone'];
        }

        // Érintőképernyő
        if (!empty($info['touchSupport']) && is_array($info['touchSupport'])) {
            $touch = $info['touchSupport'];
            if (!empty($touch['maxTouchPoints']) && $touch['maxTouchPoints'] > 0) {
                $formatted['touch'] = 'Igen (' . $touch['maxTouchPoints'] . ' pont)';
            } else {
                $formatted['touch'] = 'Nem';
            }
        }

        // WebGL renderer (GPU info)
        if (!empty($info['webglRenderer']) && $info['webglRenderer'] !== 'no') {
            $renderer = $info['webglRenderer'];
            // Rövidítsük le ha túl hosszú
            if (strlen($renderer) > 40) {
                $renderer = substr($renderer, 0, 37) . '...';
            }
            $formatted['gpu'] = $renderer;
        }

        // Vendor (gyártó)
        if (!empty($info['vendor'])) {
            $formatted['vendor'] = $info['vendor'];
        }

        // Nyelvek
        if (!empty($info['languages'])) {
            if (is_array($info['languages'])) {
                $formatted['languages'] = implode(', ', array_slice($info['languages'], 0, 3));
            } else {
                $formatted['languages'] = $info['languages'];
            }
        }

        // Gyűjtés ideje
        if (!empty($info['collectedAt'])) {
            $formatted['collectedAt'] = date('Y.m.d H:i', strtotime($info['collectedAt']));
        }

        return $formatted;
    }

    /**
     * /admin útvonalak kezelése
     */
    public static function handle_admin_routes() {
        $request_uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($request_uri, PHP_URL_PATH);

        // Záró perjel eltávolítása
        $path = rtrim($path, '/');

        // Admin útvonal ellenőrzése
        if ($path === '/admin' || strpos($path, '/admin/') === 0) {
            self::process_admin_request($path);
            exit;
        }
    }

    /**
     * Admin kérés feldolgozása
     */
    private static function process_admin_request($path) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Kijelentkezés kezelése
        if ($path === '/admin/logout') {
            unset($_SESSION['ppv_admin_logged_in']);
            unset($_SESSION['ppv_admin_email']);
            unset($_SESSION['ppv_admin_store_id']);
            unset($_SESSION['ppv_admin_store_name']);
            wp_redirect('/admin');
            exit;
        }

        // Bejelentkezés POST kezelése
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === '/admin/login') {
            self::handle_login();
            exit;
        }

        // Bejelentkezés ellenőrzése
        if (empty($_SESSION['ppv_admin_logged_in'])) {
            self::render_login_page();
            return;
        }

        // Különböző admin oldalak kezelése
        if ($path === '/admin' || $path === '/admin/dashboard') {
            self::render_dashboard();
        } elseif ($path === '/admin/device-requests') {
            self::render_device_requests();
        } elseif ($path === '/admin/handlers') {
            self::render_handlers_page();
        } elseif ($path === '/admin/delete-device') {
            self::handle_delete_device();
        } elseif (preg_match('#/admin/approve/([a-zA-Z0-9]+)#', $path, $matches)) {
            self::handle_approve($matches[1]);
        } elseif (preg_match('#/admin/reject/([a-zA-Z0-9]+)#', $path, $matches)) {
            self::handle_reject($matches[1]);
        } else {
            self::render_dashboard();
        }
    }

    /**
     * Eszköz törlése admin által
     */
    private static function handle_delete_device() {
        global $wpdb;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_redirect('/admin/handlers');
            exit;
        }

        $device_id = intval($_POST['device_id'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');

        if (empty($device_id)) {
            wp_redirect('/admin/handlers?error=missing_device');
            exit;
        }

        if (empty($reason)) {
            wp_redirect('/admin/handlers?error=missing_reason');
            exit;
        }

        // Eszköz lekérése
        $device = $wpdb->get_row($wpdb->prepare(
            "SELECT d.*, s.name as store_name
             FROM {$wpdb->prefix}ppv_user_devices d
             LEFT JOIN {$wpdb->prefix}ppv_stores s ON d.store_id = s.id
             WHERE d.id = %d",
            $device_id
        ));

        if (!$device) {
            wp_redirect('/admin/handlers?error=device_not_found');
            exit;
        }

        // Eszköz törlése
        $result = $wpdb->delete(
            $wpdb->prefix . 'ppv_user_devices',
            ['id' => $device_id],
            ['%d']
        );

        if ($result) {
            // Log the deletion
            $admin_email = $_SESSION['ppv_admin_email'] ?? 'admin';
            $device_info = self::parse_device_info($device->user_agent ?? '');
            ppv_log("🗑️ [Admin Device Delete] device_id={$device_id}, store={$device->store_name} (#{$device->store_id}), device={$device->device_name} ({$device_info['model']}), reason={$reason}, by={$admin_email}");

            wp_redirect('/admin/handlers?deleted=' . urlencode($device->device_name) . '&store=' . urlencode($device->store_name));
        } else {
            wp_redirect('/admin/handlers?error=delete_failed');
        }
        exit;
    }

    /**
     * Bejelentkezés kezelése - ppv_stores táblából, admin_access oszlop ellenőrzéssel
     */
    private static function handle_login() {
        global $wpdb;

        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            wp_redirect('/admin?error=missing_fields');
            exit;
        }

        // Store keresése email alapján (admin_access = 1)
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email, password, name, admin_access FROM {$wpdb->prefix}ppv_stores WHERE email = %s",
            $email
        ));

        // Ellenőrzés: létezik-e
        if (!$store) {
            ppv_log("🔐 [Admin] Ismeretlen email: {$email}");
            wp_redirect('/admin?error=invalid_credentials');
            exit;
        }

        // Ellenőrzés: admin_access = 1
        if (empty($store->admin_access) || $store->admin_access != 1) {
            ppv_log("🔐 [Admin] Store nincs engedélyezve (admin_access != 1): {$store->id} ({$email})");
            wp_redirect('/admin?error=not_authorized');
            exit;
        }

        // Jelszó ellenőrzése
        if (!password_verify($password, $store->password)) {
            ppv_log("🔐 [Admin] Hibás jelszó: {$email}");
            wp_redirect('/admin?error=invalid_credentials');
            exit;
        }

        // Sikeres bejelentkezés
        $_SESSION['ppv_admin_logged_in'] = true;
        $_SESSION['ppv_admin_email'] = $email;
        $_SESSION['ppv_admin_store_id'] = $store->id;
        $_SESSION['ppv_admin_store_name'] = $store->name;

        ppv_log("🔐 [Admin] Sikeres bejelentkezés: {$email} (Store #{$store->id})");
        wp_redirect('/admin/dashboard');
        exit;
    }

    /**
     * API útvonalak regisztrálása
     */
    public static function register_api_routes() {
        register_rest_route('punktepass/v1', '/admin/change-password', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_change_password'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * Jelszó módosítása
     */
    public static function rest_change_password(WP_REST_Request $request) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION['ppv_admin_logged_in'])) {
            return new WP_REST_Response(['success' => false, 'message' => 'Nincs bejelentkezve'], 401);
        }

        $data = $request->get_json_params();
        $current_password = $data['current_password'] ?? '';
        $new_password = $data['new_password'] ?? '';

        if (strlen($new_password) < 8) {
            return new WP_REST_Response(['success' => false, 'message' => 'A jelszónak legalább 8 karakter hosszúnak kell lennie'], 400);
        }

        $email = $_SESSION['ppv_admin_email'];
        $admins = get_option(self::ADMIN_OPTION_KEY, []);

        if (!isset($admins[$email]) || !password_verify($current_password, $admins[$email])) {
            return new WP_REST_Response(['success' => false, 'message' => 'Hibás jelenlegi jelszó'], 400);
        }

        $admins[$email] = password_hash($new_password, PASSWORD_DEFAULT);
        update_option(self::ADMIN_OPTION_KEY, $admins);

        ppv_log("🔐 [Admin] Jelszó módosítva: {$email}");
        return new WP_REST_Response(['success' => true, 'message' => 'Jelszó módosítva']);
    }

    /**
     * Jóváhagyás kezelése
     */
    private static function handle_approve($token) {
        global $wpdb;

        $req = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_device_requests WHERE approval_token = %s AND status = 'pending'",
            $token
        ));

        if (!$req) {
            self::render_message_page('error', 'Kérelem nem található vagy már feldolgozva');
            return;
        }

        // Típus alapján feldolgozás
        if ($req->request_type === 'add') {
            $wpdb->insert(
                $wpdb->prefix . 'ppv_user_devices',
                [
                    'store_id' => $req->store_id,
                    'fingerprint_hash' => $req->fingerprint_hash,
                    'device_name' => $req->device_name,
                    'user_agent' => $req->user_agent,
                    'ip_address' => $req->ip_address,
                    'registered_at' => current_time('mysql'),
                    'status' => 'active'
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
            );
            $message = 'Készülék sikeresen hozzáadva!';
        } elseif ($req->request_type === 'remove') {
            $wpdb->delete(
                $wpdb->prefix . 'ppv_user_devices',
                ['store_id' => $req->store_id, 'fingerprint_hash' => $req->fingerprint_hash],
                ['%d', '%s']
            );
            $message = 'Készülék sikeresen eltávolítva!';
        } elseif ($req->request_type === 'mobile_scanner') {
            $wpdb->update(
                $wpdb->prefix . 'ppv_stores',
                ['scanner_type' => 'mobile'],
                ['id' => $req->store_id],
                ['%s'],
                ['%d']
            );
            $message = 'Mobile Scanner aktiválva!';
        } else {
            self::render_message_page('error', 'Ismeretlen kérelem típus');
            return;
        }

        // Jóváhagyottként jelölés
        $wpdb->update(
            $wpdb->prefix . 'ppv_device_requests',
            [
                'status' => 'approved',
                'processed_at' => current_time('mysql'),
                'processed_by' => $_SESSION['ppv_admin_email'] ?? 'admin'
            ],
            ['id' => $req->id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        ppv_log("✅ [Admin] Kérelem jóváhagyva #{$req->id}: {$req->request_type}");
        wp_redirect('/admin/device-requests?success=' . urlencode($message));
        exit;
    }

    /**
     * Elutasítás kezelése
     */
    private static function handle_reject($token) {
        global $wpdb;

        $req = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_device_requests WHERE approval_token = %s AND status = 'pending'",
            $token
        ));

        if (!$req) {
            self::render_message_page('error', 'Kérelem nem található vagy már feldolgozva');
            return;
        }

        $wpdb->update(
            $wpdb->prefix . 'ppv_device_requests',
            [
                'status' => 'rejected',
                'processed_at' => current_time('mysql'),
                'processed_by' => $_SESSION['ppv_admin_email'] ?? 'admin'
            ],
            ['id' => $req->id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        ppv_log("❌ [Admin] Kérelem elutasítva #{$req->id}: {$req->request_type}");
        wp_redirect('/admin/device-requests?success=Kérelem+elutasítva');
        exit;
    }

    /**
     * Bejelentkezési oldal
     */
    private static function render_login_page() {
        $error = $_GET['error'] ?? '';
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>PunktePass Admin</title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Inter', sans-serif;
                    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .login-card {
                    background: rgba(255, 255, 255, 0.05);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 20px;
                    padding: 40px;
                    width: 100%;
                    max-width: 400px;
                }
                .logo {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .logo h1 {
                    color: #00e6ff;
                    font-size: 28px;
                    font-weight: 700;
                }
                .logo p {
                    color: #888;
                    font-size: 14px;
                    margin-top: 5px;
                }
                .form-group {
                    margin-bottom: 20px;
                }
                .form-group label {
                    display: block;
                    color: #fff;
                    font-size: 14px;
                    margin-bottom: 8px;
                }
                .form-group input {
                    width: 100%;
                    padding: 14px 16px;
                    background: rgba(255, 255, 255, 0.05);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 10px;
                    color: #fff;
                    font-size: 16px;
                    transition: all 0.3s;
                }
                .form-group input:focus {
                    outline: none;
                    border-color: #00e6ff;
                    background: rgba(0, 230, 255, 0.05);
                }
                .btn {
                    width: 100%;
                    padding: 14px;
                    background: linear-gradient(135deg, #00e6ff, #0099ff);
                    border: none;
                    border-radius: 10px;
                    color: #fff;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s;
                }
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 30px rgba(0, 230, 255, 0.3);
                }
                .error {
                    background: rgba(244, 67, 54, 0.1);
                    border: 1px solid #f44336;
                    color: #f44336;
                    padding: 12px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="login-card">
                <div class="logo">
                    <h1>PunktePass</h1>
                    <p>Admin Panel</p>
                </div>

                <?php if ($error === 'invalid_credentials'): ?>
                    <div class="error">Hibás email cím vagy jelszó</div>
                <?php elseif ($error === 'missing_fields'): ?>
                    <div class="error">Kérjük töltse ki az összes mezőt</div>
                <?php elseif ($error === 'no_admins'): ?>
                    <div class="error">Nincs beállítva admin felhasználó. Kérjük lépjen kapcsolatba a rendszergazdával.</div>
                <?php elseif ($error === 'not_authorized'): ?>
                    <div class="error">Ez a fiók nincs engedélyezve az admin panelhez.</div>
                <?php endif; ?>

                <form method="POST" action="/admin/login">
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" required autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label>Jelszó</label>
                        <input type="password" name="password" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn">Bejelentkezés</button>
                </form>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Admin fejléc HTML
     */
    private static function get_admin_header($current_page = 'dashboard') {
        $admin_email = $_SESSION['ppv_admin_email'] ?? 'Admin';
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>PunktePass Admin</title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Inter', sans-serif;
                    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                    min-height: 100vh;
                    color: #fff;
                }
                .admin-header {
                    background: rgba(255, 255, 255, 0.03);
                    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                    padding: 15px 30px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .admin-logo {
                    font-size: 22px;
                    font-weight: 700;
                    color: #00e6ff;
                }
                .admin-nav {
                    display: flex;
                    gap: 10px;
                }
                .admin-nav a {
                    color: #888;
                    text-decoration: none;
                    padding: 10px 16px;
                    border-radius: 8px;
                    transition: all 0.3s;
                    font-size: 14px;
                }
                .admin-nav a:hover, .admin-nav a.active {
                    color: #fff;
                    background: rgba(255, 255, 255, 0.05);
                }
                .admin-nav a.active {
                    background: rgba(0, 230, 255, 0.1);
                    color: #00e6ff;
                }
                .admin-user {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                .admin-user span {
                    color: #888;
                    font-size: 14px;
                }
                .admin-user .logout-btn {
                    color: #f44336;
                    text-decoration: none;
                    font-size: 14px;
                }
                .admin-content {
                    padding: 30px;
                    max-width: 1400px;
                    margin: 0 auto;
                }
                .page-title {
                    font-size: 24px;
                    font-weight: 600;
                    margin-bottom: 25px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .card {
                    background: rgba(255, 255, 255, 0.03);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    border-radius: 15px;
                    padding: 25px;
                    margin-bottom: 20px;
                }
                .card-title {
                    font-size: 18px;
                    font-weight: 600;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .stat-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                }
                .stat-card {
                    background: rgba(255, 255, 255, 0.03);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    border-radius: 12px;
                    padding: 20px;
                    text-align: center;
                }
                .stat-card .number {
                    font-size: 36px;
                    font-weight: 700;
                    color: #00e6ff;
                }
                .stat-card .label {
                    color: #888;
                    font-size: 14px;
                    margin-top: 5px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                }
                th, td {
                    text-align: left;
                    padding: 12px 15px;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
                }
                th {
                    color: #888;
                    font-weight: 500;
                    font-size: 13px;
                    text-transform: uppercase;
                }
                td {
                    font-size: 14px;
                }
                .btn {
                    display: inline-block;
                    padding: 8px 16px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-size: 13px;
                    font-weight: 500;
                    transition: all 0.3s;
                    border: none;
                    cursor: pointer;
                }
                .btn-approve {
                    background: rgba(76, 175, 80, 0.2);
                    color: #4caf50;
                    border: 1px solid rgba(76, 175, 80, 0.3);
                }
                .btn-approve:hover {
                    background: #4caf50;
                    color: #fff;
                }
                .btn-reject {
                    background: rgba(244, 67, 54, 0.2);
                    color: #f44336;
                    border: 1px solid rgba(244, 67, 54, 0.3);
                }
                .btn-reject:hover {
                    background: #f44336;
                    color: #fff;
                }
                .badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 500;
                }
                .badge-pending {
                    background: rgba(255, 152, 0, 0.2);
                    color: #ff9800;
                }
                .badge-approved {
                    background: rgba(76, 175, 80, 0.2);
                    color: #4caf50;
                }
                .badge-rejected {
                    background: rgba(244, 67, 54, 0.2);
                    color: #f44336;
                }
                .badge-add { background: rgba(33, 150, 243, 0.2); color: #2196f3; }
                .badge-remove { background: rgba(244, 67, 54, 0.2); color: #f44336; }
                .badge-mobile { background: rgba(156, 39, 176, 0.2); color: #9c27b0; }
                .success-msg {
                    background: rgba(76, 175, 80, 0.1);
                    border: 1px solid rgba(76, 175, 80, 0.3);
                    color: #4caf50;
                    padding: 15px;
                    border-radius: 10px;
                    margin-bottom: 20px;
                }
                .error-msg {
                    background: rgba(244, 67, 54, 0.1);
                    border: 1px solid rgba(244, 67, 54, 0.3);
                    color: #f44336;
                    padding: 15px;
                    border-radius: 10px;
                    margin-bottom: 20px;
                }
                .btn-sm {
                    padding: 6px 12px;
                    font-size: 12px;
                }
                .empty-state {
                    text-align: center;
                    padding: 60px 20px;
                    color: #666;
                }
                .empty-state i {
                    font-size: 48px;
                    margin-bottom: 15px;
                    display: block;
                }
                @media (max-width: 768px) {
                    .admin-header {
                        flex-direction: column;
                        gap: 15px;
                    }
                    .admin-nav {
                        flex-wrap: wrap;
                        justify-content: center;
                    }
                    .admin-content {
                        padding: 20px 15px;
                    }
                }
            </style>
        </head>
        <body>
            <header class="admin-header">
                <div class="admin-logo">PunktePass Admin</div>
                <nav class="admin-nav">
                    <a href="/admin/dashboard" class="<?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                        <i class="ri-dashboard-line"></i> Vezérlőpult
                    </a>
                    <a href="/admin/device-requests" class="<?php echo $current_page === 'device-requests' ? 'active' : ''; ?>">
                        <i class="ri-smartphone-line"></i> Készülék kérelmek
                    </a>
                    <a href="/admin/handlers" class="<?php echo $current_page === 'handlers' ? 'active' : ''; ?>">
                        <i class="ri-store-2-line"></i> Handlerek
                    </a>
                </nav>
                <div class="admin-user">
                    <span><?php echo esc_html($admin_email); ?></span>
                    <a href="/admin/logout" class="logout-btn"><i class="ri-logout-box-line"></i> Kilépés</a>
                </div>
            </header>
            <main class="admin-content">
        <?php
    }

    /**
     * Admin lábléc HTML
     */
    private static function get_admin_footer() {
        ?>
            </main>
        </body>
        </html>
        <?php
    }

    /**
     * Vezérlőpult megjelenítése
     */
    private static function render_dashboard() {
        global $wpdb;

        // Statisztikák lekérése
        $pending_device_requests = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_device_requests WHERE status = 'pending'"
        );
        $pending_mobile_requests = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_device_requests WHERE status = 'pending' AND request_type = 'mobile_scanner'"
        );
        $total_handlers = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE parent_store_id IS NULL OR parent_store_id = 0"
        );
        $active_devices = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_user_devices WHERE status = 'active'"
        );

        self::get_admin_header('dashboard');
        ?>
        <h1 class="page-title"><i class="ri-dashboard-line"></i> Vezérlőpult</h1>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="number"><?php echo intval($pending_device_requests); ?></div>
                <div class="label">Nyitott készülék kérelmek</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo intval($pending_mobile_requests); ?></div>
                <div class="label">Mobile Scanner kérelmek</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo intval($total_handlers); ?></div>
                <div class="label">Handlerek</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo intval($active_devices); ?></div>
                <div class="label">Regisztrált készülékek</div>
            </div>
        </div>

        <?php
        // Legutóbbi kérelmek
        $recent_requests = $wpdb->get_results(
            "SELECT r.*, s.name as store_name
             FROM {$wpdb->prefix}ppv_device_requests r
             LEFT JOIN {$wpdb->prefix}ppv_stores s ON r.store_id = s.id
             ORDER BY r.requested_at DESC LIMIT 10"
        );
        ?>

        <div class="card">
            <h3 class="card-title"><i class="ri-time-line"></i> Legutóbbi kérelmek</h3>
            <?php if (empty($recent_requests)): ?>
                <div class="empty-state">
                    <i class="ri-inbox-line"></i>
                    <p>Nincsenek kérelmek</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Dátum</th>
                            <th>Üzlet</th>
                            <th>Típus</th>
                            <th>Státusz</th>
                            <th>Műveletek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_requests as $req): ?>
                            <tr>
                                <td><?php echo date('Y.m.d H:i', strtotime($req->requested_at)); ?></td>
                                <td><?php echo esc_html($req->store_name ?: "Store #{$req->store_id}"); ?></td>
                                <td>
                                    <?php if ($req->request_type === 'add'): ?>
                                        <span class="badge badge-add">Hozzáadás</span>
                                    <?php elseif ($req->request_type === 'remove'): ?>
                                        <span class="badge badge-remove">Eltávolítás</span>
                                    <?php else: ?>
                                        <span class="badge badge-mobile">Mobile Scanner</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($req->status === 'pending'): ?>
                                        <span class="badge badge-pending">Függőben</span>
                                    <?php elseif ($req->status === 'approved'): ?>
                                        <span class="badge badge-approved">Jóváhagyva</span>
                                    <?php else: ?>
                                        <span class="badge badge-rejected">Elutasítva</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($req->status === 'pending'): ?>
                                        <a href="/admin/approve/<?php echo $req->approval_token; ?>" class="btn btn-approve">Jóváhagyás</a>
                                        <a href="/admin/reject/<?php echo $req->approval_token; ?>" class="btn btn-reject">Elutasítás</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        self::get_admin_footer();
    }

    /**
     * Készülék kérelmek oldal
     */
    private static function render_device_requests() {
        global $wpdb;

        $success = $_GET['success'] ?? '';

        // Összes kérelem (pending előre, utána a feldolgozottak)
        $requests = $wpdb->get_results(
            "SELECT r.*, s.name as store_name, s.city as store_city
             FROM {$wpdb->prefix}ppv_device_requests r
             LEFT JOIN {$wpdb->prefix}ppv_stores s ON r.store_id = s.id
             ORDER BY
                CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END,
                r.requested_at DESC
             LIMIT 50"
        );

        // Pending és feldolgozott szétválasztása
        $pending_requests = array_filter($requests, fn($r) => $r->status === 'pending');
        $processed_requests = array_filter($requests, fn($r) => $r->status !== 'pending');

        self::get_admin_header('device-requests');
        ?>
        <h1 class="page-title"><i class="ri-smartphone-line"></i> Készülék kérelmek</h1>

        <?php if ($success): ?>
            <div class="success-msg"><?php echo esc_html($success); ?></div>
        <?php endif; ?>

        <!-- Nyitott kérelmek -->
        <h2 style="color: #ff9800; margin-bottom: 15px;">
            <i class="ri-time-line"></i> Nyitott kérelmek (<?php echo count($pending_requests); ?>)
        </h2>
        <div class="card">
            <?php if (empty($pending_requests)): ?>
                <div class="empty-state">
                    <i class="ri-checkbox-circle-line"></i>
                    <p>Nincsenek nyitott kérelmek</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Dátum</th>
                            <th>Üzlet</th>
                            <th>Készülék név</th>
                            <th>Eszköz modell</th>
                            <th>Típus</th>
                            <th>IP</th>
                            <th>Műveletek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_requests as $req):
                            $device_info = self::parse_device_info($req->user_agent ?? '');
                        ?>
                            <tr>
                                <td><?php echo date('Y.m.d H:i', strtotime($req->requested_at)); ?></td>
                                <td>
                                    <strong><?php echo esc_html($req->store_name ?: "Store #{$req->store_id}"); ?></strong>
                                    <?php if ($req->store_city): ?>
                                        <br><small style="color: #888;"><?php echo esc_html($req->store_city); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($req->device_name); ?></td>
                                <td title="<?php echo esc_attr($req->user_agent ?? ''); ?>">
                                    <strong style="color: #00e6ff;"><?php echo esc_html($device_info['model']); ?></strong>
                                    <?php if ($device_info['os']): ?>
                                        <br><small style="color: #aaa;"><?php echo esc_html($device_info['os']); ?></small>
                                    <?php endif; ?>
                                    <?php if ($device_info['browser']): ?>
                                        <br><small style="color: #666;"><?php echo esc_html($device_info['browser']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($req->request_type === 'add'): ?>
                                        <span class="badge badge-add">Hozzáadás</span>
                                    <?php elseif ($req->request_type === 'remove'): ?>
                                        <span class="badge badge-remove">Eltávolítás</span>
                                    <?php else: ?>
                                        <span class="badge badge-mobile">Mobile Scanner</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo esc_html($req->ip_address); ?></small></td>
                                <td>
                                    <a href="/admin/approve/<?php echo $req->approval_token; ?>" class="btn btn-approve">
                                        <i class="ri-check-line"></i> Jóváhagyás
                                    </a>
                                    <a href="/admin/reject/<?php echo $req->approval_token; ?>" class="btn btn-reject">
                                        <i class="ri-close-line"></i> Elutasítás
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Feldolgozott kérelmek -->
        <?php if (!empty($processed_requests)): ?>
        <h2 style="color: #888; margin: 30px 0 15px 0;">
            <i class="ri-history-line"></i> Feldolgozott kérelmek
        </h2>
        <div class="card" style="opacity: 0.8;">
            <table>
                <thead>
                    <tr>
                        <th>Dátum</th>
                        <th>Üzlet</th>
                        <th>Készülék név</th>
                        <th>Eszköz modell</th>
                        <th>Típus</th>
                        <th>Állapot</th>
                        <th>Feldolgozta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processed_requests as $req):
                        $device_info = self::parse_device_info($req->user_agent ?? '');
                    ?>
                        <tr style="opacity: 0.7;">
                            <td><?php echo date('Y.m.d H:i', strtotime($req->requested_at)); ?></td>
                            <td>
                                <strong><?php echo esc_html($req->store_name ?: "Store #{$req->store_id}"); ?></strong>
                            </td>
                            <td><?php echo esc_html($req->device_name); ?></td>
                            <td>
                                <strong style="color: #00e6ff;"><?php echo esc_html($device_info['model']); ?></strong>
                                <?php if ($device_info['os']): ?>
                                    <br><small style="color: #aaa;"><?php echo esc_html($device_info['os']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($req->request_type === 'add'): ?>
                                    <span class="badge badge-add">Hozzáadás</span>
                                <?php elseif ($req->request_type === 'remove'): ?>
                                    <span class="badge badge-remove">Eltávolítás</span>
                                <?php else: ?>
                                    <span class="badge badge-mobile">Mobile Scanner</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($req->status === 'approved'): ?>
                                    <span style="color: #4caf50;">✅ Jóváhagyva</span>
                                <?php else: ?>
                                    <span style="color: #f44336;">❌ Elutasítva</span>
                                <?php endif; ?>
                                <?php if ($req->processed_at): ?>
                                    <br><small style="color: #666;"><?php echo date('m.d H:i', strtotime($req->processed_at)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small style="color: #888;"><?php echo esc_html($req->processed_by ?? '-'); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php
        self::get_admin_footer();
    }

    /**
     * Handlerek oldal
     */
    private static function render_handlers_page() {
        global $wpdb;

        $handlers = $wpdb->get_results(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}ppv_user_devices WHERE store_id = s.id AND status = 'active') as device_count,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE parent_store_id = s.id) as filiale_count
             FROM {$wpdb->prefix}ppv_stores s
             WHERE s.parent_store_id IS NULL OR s.parent_store_id = 0
             ORDER BY s.name ASC"
        );

        // Eszközök lekérése handler-enként (device_info-val együtt)
        $devices_by_store = [];
        $all_devices = $wpdb->get_results(
            "SELECT id, store_id, fingerprint_hash, device_name, user_agent, device_info, ip_address, registered_at, last_used_at, status
             FROM {$wpdb->prefix}ppv_user_devices WHERE status = 'active' ORDER BY registered_at DESC"
        );
        foreach ($all_devices as $device) {
            $devices_by_store[$device->store_id][] = $device;
        }

        $deleted = $_GET['deleted'] ?? '';
        $store_deleted = $_GET['store'] ?? '';
        $error = $_GET['error'] ?? '';

        self::get_admin_header('handlers');
        ?>
        <h1 class="page-title"><i class="ri-store-2-line"></i> Handler áttekintés</h1>

        <?php if ($deleted): ?>
            <div class="success-msg">✅ Eszköz törölve: <strong><?php echo esc_html($deleted); ?></strong> (<?php echo esc_html($store_deleted); ?>)</div>
        <?php endif; ?>
        <?php if ($error === 'missing_reason'): ?>
            <div class="error-msg">❌ Add meg a törlés okát!</div>
        <?php elseif ($error === 'device_not_found'): ?>
            <div class="error-msg">❌ Eszköz nem található!</div>
        <?php elseif ($error === 'delete_failed'): ?>
            <div class="error-msg">❌ Hiba a törlés során!</div>
        <?php endif; ?>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Név</th>
                        <th>Város</th>
                        <th>Scanner</th>
                        <th>Készülékek</th>
                        <th>Fiókok</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($handlers as $handler):
                        $handler_devices = $devices_by_store[$handler->id] ?? [];
                    ?>
                        <tr>
                            <td>#<?php echo $handler->id; ?></td>
                            <td><strong><?php echo esc_html($handler->name); ?></strong></td>
                            <td><?php echo esc_html($handler->city ?: '-'); ?></td>
                            <td>
                                <?php if ($handler->scanner_type === 'mobile'): ?>
                                    <span class="badge badge-mobile">Mobile</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(255,255,255,0.1); color: #888;">Fixed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($handler_devices)): ?>
                                    <span style="color: #f44336;">0</span>
                                <?php else: ?>
                                    <strong style="color: #4caf50;"><?php echo count($handler_devices); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td><?php echo intval($handler->filiale_count); ?></td>
                        </tr>
                        <?php if (!empty($handler_devices)): ?>
                        <tr>
                            <td colspan="6" style="padding: 0; background: rgba(0,230,255,0.05);">
                                <div style="padding: 15px 20px 15px 50px;">
                                    <strong style="color: #00e6ff; font-size: 12px;">📱 REGISZTRÁLT ESZKÖZÖK:</strong>
                                    <table style="margin-top: 10px; background: transparent;">
                                        <thead>
                                            <tr style="background: rgba(0,0,0,0.2);">
                                                <th style="padding: 8px; font-size: 11px;">Név</th>
                                                <th style="padding: 8px; font-size: 11px;">Modell</th>
                                                <th style="padding: 8px; font-size: 11px;">OS</th>
                                                <th style="padding: 8px; font-size: 11px;">Böngésző</th>
                                                <th style="padding: 8px; font-size: 11px;">Regisztrálva</th>
                                                <th style="padding: 8px; font-size: 11px;">IP</th>
                                                <th style="padding: 8px; font-size: 11px;">Művelet</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($handler_devices as $device):
                                                $device_info = self::parse_device_info($device->user_agent ?? '');
                                                $fp_info = self::format_device_info_json($device->device_info ?? '');
                                            ?>
                                            <tr style="background: transparent;">
                                                <td style="padding: 8px;"><?php echo esc_html($device->device_name); ?></td>
                                                <td style="padding: 8px;">
                                                    <strong style="color: #00e6ff;"><?php echo esc_html($device_info['model']); ?></strong>
                                                </td>
                                                <td style="padding: 8px; color: #aaa; font-size: 12px;"><?php echo esc_html($device_info['os'] ?: '-'); ?></td>
                                                <td style="padding: 8px; color: #666; font-size: 12px;"><?php echo esc_html($device_info['browser'] ?: '-'); ?></td>
                                                <td style="padding: 8px; color: #888; font-size: 11px;">
                                                    <?php echo date('Y.m.d H:i', strtotime($device->registered_at)); ?>
                                                </td>
                                                <td style="padding: 8px; color: #666; font-size: 11px;"><?php echo esc_html($device->ip_address); ?></td>
                                                <td style="padding: 8px;">
                                                    <button type="button" class="btn btn-reject btn-sm"
                                                            onclick="openDeleteModal(<?php echo $device->id; ?>, '<?php echo esc_js($device->device_name); ?>', '<?php echo esc_js($device_info['model']); ?>', '<?php echo esc_js($handler->name); ?>')">
                                                        <i class="ri-delete-bin-line"></i> Törlés
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php if ($fp_info): // FingerprintJS részletek ha elérhetők ?>
                                            <tr style="background: rgba(0,230,255,0.03);">
                                                <td colspan="7" style="padding: 5px 8px 10px 30px;">
                                                    <div style="display: flex; flex-wrap: wrap; gap: 10px; font-size: 11px;">
                                                        <?php if (!empty($fp_info['screen'])): ?>
                                                            <span style="background: rgba(255,255,255,0.05); padding: 3px 8px; border-radius: 4px;">
                                                                <i class="ri-computer-line" style="color: #00e6ff;"></i> <?php echo esc_html($fp_info['screen']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($fp_info['memory'])): ?>
                                                            <span style="background: rgba(255,255,255,0.05); padding: 3px 8px; border-radius: 4px;">
                                                                <i class="ri-database-line" style="color: #4caf50;"></i> <?php echo esc_html($fp_info['memory']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($fp_info['cpuCores'])): ?>
                                                            <span style="background: rgba(255,255,255,0.05); padding: 3px 8px; border-radius: 4px;">
                                                                <i class="ri-cpu-line" style="color: #ff9800;"></i> <?php echo esc_html($fp_info['cpuCores']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($fp_info['touch'])): ?>
                                                            <span style="background: rgba(255,255,255,0.05); padding: 3px 8px; border-radius: 4px;">
                                                                <i class="ri-hand-coin-line" style="color: #e91e63;"></i> Touch: <?php echo esc_html($fp_info['touch']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($fp_info['platform'])): ?>
                                                            <span style="background: rgba(255,255,255,0.05); padding: 3px 8px; border-radius: 4px;">
                                                                <i class="ri-device-line" style="color: #9c27b0;"></i> <?php echo esc_html($fp_info['platform']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($fp_info['vendor'])): ?>
                                                            <span style="background: rgba(255,255,255,0.05); padding: 3px 8px; border-radius: 4px;">
                                                                <i class="ri-building-line" style="color: #2196f3;"></i> <?php echo esc_html($fp_info['vendor']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($fp_info['timezone'])): ?>
                                                            <span style="background: rgba(255,255,255,0.05); padding: 3px 8px; border-radius: 4px;">
                                                                <i class="ri-time-line" style="color: #ffeb3b;"></i> <?php echo esc_html($fp_info['timezone']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($fp_info['gpu'])): ?>
                                                            <span style="background: rgba(255,255,255,0.05); padding: 3px 8px; border-radius: 4px; color: #888;" title="<?php echo esc_attr($fp_info['gpu']); ?>">
                                                                <i class="ri-palette-line" style="color: #00bcd4;"></i> GPU
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Törlés megerősítő modal -->
        <div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
            <div style="background: #1a1a2e; border: 1px solid rgba(255,255,255,0.1); border-radius: 15px; padding: 30px; max-width: 450px; width: 90%;">
                <h3 style="color: #f44336; margin: 0 0 20px 0;"><i class="ri-alert-line"></i> Eszköz törlése</h3>
                <p style="color: #ccc; margin-bottom: 15px;">
                    Biztosan törölni szeretnéd az alábbi eszközt?
                </p>
                <div style="background: rgba(244,67,54,0.1); border: 1px solid rgba(244,67,54,0.3); border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                    <p style="margin: 0; color: #fff;"><strong id="deleteDeviceName"></strong></p>
                    <p style="margin: 5px 0 0 0; color: #00e6ff;" id="deleteDeviceModel"></p>
                    <p style="margin: 5px 0 0 0; color: #888; font-size: 12px;">Handler: <span id="deleteStoreName"></span></p>
                </div>
                <form method="POST" action="/admin/delete-device" id="deleteForm">
                    <input type="hidden" name="device_id" id="deleteDeviceId">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; color: #fff; margin-bottom: 8px;">Törlés oka: <span style="color: #f44336;">*</span></label>
                        <textarea name="reason" id="deleteReason" required rows="3"
                                  style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 12px; color: #fff; font-size: 14px; resize: none;"
                                  placeholder="pl. Ügyfél kérése, elveszett eszköz, új készülék..."></textarea>
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" onclick="closeDeleteModal()" class="btn" style="background: rgba(255,255,255,0.1); color: #fff;">
                            Mégse
                        </button>
                        <button type="submit" class="btn btn-reject">
                            <i class="ri-delete-bin-line"></i> Véglegesen törlés
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openDeleteModal(deviceId, deviceName, deviceModel, storeName) {
            document.getElementById('deleteDeviceId').value = deviceId;
            document.getElementById('deleteDeviceName').textContent = deviceName;
            document.getElementById('deleteDeviceModel').textContent = deviceModel;
            document.getElementById('deleteStoreName').textContent = storeName;
            document.getElementById('deleteReason').value = '';
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // ESC billentyű
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeDeleteModal();
        });

        // Kattintás a háttérre
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });
        </script>
        <?php
        self::get_admin_footer();
    }

    /**
     * Üzenet oldal megjelenítése
     */
    private static function render_message_page($type, $message) {
        self::get_admin_header('');
        ?>
        <div class="card" style="max-width: 500px; margin: 50px auto; text-align: center;">
            <?php if ($type === 'error'): ?>
                <i class="ri-error-warning-line" style="font-size: 48px; color: #f44336;"></i>
            <?php else: ?>
                <i class="ri-checkbox-circle-line" style="font-size: 48px; color: #4caf50;"></i>
            <?php endif; ?>
            <h2 style="margin: 20px 0;"><?php echo esc_html($message); ?></h2>
            <a href="/admin/dashboard" class="btn" style="background: #00e6ff; color: #000;">Vissza a vezérlőpulthoz</a>
        </div>
        <?php
        self::get_admin_footer();
    }
}
