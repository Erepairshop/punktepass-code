<?php
/**
 * PunktePass Standalone Admin Panel
 *
 * Elérhető: /admin - egyszerű email/jelszó bejelentkezéssel
 * Független a WordPress admintól
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Admin {

    // Engedélyezett store ID-k - wp_options-ban tárolva
    const ALLOWED_STORES_KEY = 'ppv_standalone_admin_stores';

    /**
     * Hooks - a plugin által hívott metódus
     */
    public static function hooks() {
        add_action('init', [__CLASS__, 'handle_admin_routes'], 1);
        add_action('rest_api_init', [__CLASS__, 'register_api_routes']);
        add_action('admin_menu', [__CLASS__, 'add_wp_admin_menu']);
        add_action('admin_init', [__CLASS__, 'handle_wp_admin_actions']);
    }

    /**
     * WP-Admin menü hozzáadása
     */
    public static function add_wp_admin_menu() {
        add_submenu_page(
            'punktepass',
            'Admin Panel Felhasználók',
            'Admin Panel',
            'manage_options',
            'ppv-admin-panel-users',
            [__CLASS__, 'render_wp_admin_page']
        );
    }

    /**
     * WP-Admin oldal renderelése
     */
    public static function render_wp_admin_page() {
        global $wpdb;

        $allowed_stores = get_option(self::ALLOWED_STORES_KEY, []);

        // Összes store lekérése
        $all_stores = $wpdb->get_results("SELECT id, name, email, city FROM {$wpdb->prefix}ppv_stores ORDER BY name");

        ?>
        <div class="wrap">
            <h1>Admin Panel Felhasználók</h1>
            <p>Itt adhatod hozzá azokat az üzleteket, amelyek be tudnak jelentkezni a <code>/admin</code> felületre a saját email/jelszó adataikkal.</p>

            <h2>Engedélyezett üzletek</h2>
            <?php if (empty($allowed_stores)): ?>
                <p><em>Nincs még hozzáadott üzlet.</em></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Név</th>
                            <th>Email</th>
                            <th>Város</th>
                            <th>Művelet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allowed_stores as $store_id):
                            $store = $wpdb->get_row($wpdb->prepare(
                                "SELECT id, name, email, city FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
                                $store_id
                            ));
                            if (!$store) continue;
                        ?>
                        <tr>
                            <td><?php echo esc_html($store->id); ?></td>
                            <td><?php echo esc_html($store->name); ?></td>
                            <td><?php echo esc_html($store->email); ?></td>
                            <td><?php echo esc_html($store->city); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('ppv_remove_admin_store', 'ppv_nonce'); ?>
                                    <input type="hidden" name="ppv_action" value="remove_admin_store">
                                    <input type="hidden" name="store_id" value="<?php echo esc_attr($store->id); ?>">
                                    <button type="submit" class="button button-small" onclick="return confirm('Biztosan eltávolítod?');">Eltávolítás</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2 style="margin-top: 30px;">Üzlet hozzáadása</h2>
            <form method="post">
                <?php wp_nonce_field('ppv_add_admin_store', 'ppv_nonce'); ?>
                <input type="hidden" name="ppv_action" value="add_admin_store">
                <table class="form-table">
                    <tr>
                        <th><label for="store_id">Üzlet kiválasztása</label></th>
                        <td>
                            <select name="store_id" id="store_id" required>
                                <option value="">-- Válassz üzletet --</option>
                                <?php foreach ($all_stores as $store):
                                    if (in_array($store->id, $allowed_stores)) continue;
                                ?>
                                <option value="<?php echo esc_attr($store->id); ?>">
                                    #<?php echo esc_html($store->id); ?> - <?php echo esc_html($store->name); ?> (<?php echo esc_html($store->email); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Hozzáadás</button>
                </p>
            </form>

            <hr style="margin-top: 40px;">
            <h3>Bejelentkezés</h3>
            <p>Az engedélyezett üzletek a <strong><a href="/admin" target="_blank">/admin</a></strong> oldalon tudnak bejelentkezni a saját email címükkel és jelszavukkal.</p>
        </div>
        <?php
    }

    /**
     * WP-Admin műveletek kezelése
     */
    public static function handle_wp_admin_actions() {
        if (!isset($_POST['ppv_action']) || !isset($_POST['ppv_nonce'])) {
            return;
        }

        if ($_POST['ppv_action'] === 'add_admin_store') {
            if (!wp_verify_nonce($_POST['ppv_nonce'], 'ppv_add_admin_store')) {
                return;
            }

            $store_id = intval($_POST['store_id'] ?? 0);
            if ($store_id > 0) {
                $allowed_stores = get_option(self::ALLOWED_STORES_KEY, []);
                if (!in_array($store_id, $allowed_stores)) {
                    $allowed_stores[] = $store_id;
                    update_option(self::ALLOWED_STORES_KEY, $allowed_stores);
                }
            }

            wp_redirect(admin_url('admin.php?page=ppv-admin-panel-users&added=1'));
            exit;
        }

        if ($_POST['ppv_action'] === 'remove_admin_store') {
            if (!wp_verify_nonce($_POST['ppv_nonce'], 'ppv_remove_admin_store')) {
                return;
            }

            $store_id = intval($_POST['store_id'] ?? 0);
            $allowed_stores = get_option(self::ALLOWED_STORES_KEY, []);
            $allowed_stores = array_filter($allowed_stores, fn($id) => $id != $store_id);
            update_option(self::ALLOWED_STORES_KEY, array_values($allowed_stores));

            wp_redirect(admin_url('admin.php?page=ppv-admin-panel-users&removed=1'));
            exit;
        }
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
        } elseif (preg_match('#/admin/approve/([a-zA-Z0-9]+)#', $path, $matches)) {
            self::handle_approve($matches[1]);
        } elseif (preg_match('#/admin/reject/([a-zA-Z0-9]+)#', $path, $matches)) {
            self::handle_reject($matches[1]);
        } else {
            self::render_dashboard();
        }
    }

    /**
     * Bejelentkezés kezelése - ppv_stores táblából
     */
    private static function handle_login() {
        global $wpdb;

        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            wp_redirect('/admin?error=missing_fields');
            exit;
        }

        // Engedélyezett store ID-k lekérése
        $allowed_stores = get_option(self::ALLOWED_STORES_KEY, []);

        if (empty($allowed_stores)) {
            ppv_log("🔐 [Admin] Nincs engedélyezett store beállítva");
            wp_redirect('/admin?error=no_admins');
            exit;
        }

        // Store keresése email alapján
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email, password, name FROM {$wpdb->prefix}ppv_stores WHERE email = %s",
            $email
        ));

        // Ellenőrzés: létezik-e és engedélyezett-e
        if (!$store) {
            ppv_log("🔐 [Admin] Ismeretlen email: {$email}");
            wp_redirect('/admin?error=invalid_credentials');
            exit;
        }

        if (!in_array($store->id, $allowed_stores)) {
            ppv_log("🔐 [Admin] Store nincs engedélyezve: {$store->id} ({$email})");
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

        // Összes függőben lévő kérelem
        $requests = $wpdb->get_results(
            "SELECT r.*, s.name as store_name, s.city as store_city
             FROM {$wpdb->prefix}ppv_device_requests r
             LEFT JOIN {$wpdb->prefix}ppv_stores s ON r.store_id = s.id
             WHERE r.status = 'pending'
             ORDER BY r.requested_at DESC"
        );

        self::get_admin_header('device-requests');
        ?>
        <h1 class="page-title"><i class="ri-smartphone-line"></i> Készülék kérelmek</h1>

        <?php if ($success): ?>
            <div class="success-msg"><?php echo esc_html($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <?php if (empty($requests)): ?>
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
                            <th>Készülék</th>
                            <th>Típus</th>
                            <th>IP</th>
                            <th>Műveletek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><?php echo date('Y.m.d H:i', strtotime($req->requested_at)); ?></td>
                                <td>
                                    <strong><?php echo esc_html($req->store_name ?: "Store #{$req->store_id}"); ?></strong>
                                    <?php if ($req->store_city): ?>
                                        <br><small style="color: #888;"><?php echo esc_html($req->store_city); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($req->device_name); ?></td>
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

        self::get_admin_header('handlers');
        ?>
        <h1 class="page-title"><i class="ri-store-2-line"></i> Handler áttekintés</h1>

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
                    <?php foreach ($handlers as $handler): ?>
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
                            <td><?php echo intval($handler->device_count); ?></td>
                            <td><?php echo intval($handler->filiale_count); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
