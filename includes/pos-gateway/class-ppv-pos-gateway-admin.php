<?php
/**
 * PunktePass POS Gateway Admin Panel
 *
 * Standalone admin panel for POS Gateway management
 * Available at: /pos-admin
 *
 * @package PunktePass
 * @subpackage POS_Gateway
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

class PPV_POS_Gateway_Admin {

    /**
     * Initialize hooks
     */
    public static function hooks() {
        add_action('init', [__CLASS__, 'handle_routes'], 2);
    }

    /**
     * Handle /pos-admin routes
     */
    public static function handle_routes() {
        $request_uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($request_uri, PHP_URL_PATH);
        $path = rtrim($path, '/');

        // Only handle /pos-admin routes
        if ($path !== '/pos-admin' && strpos($path, '/pos-admin/') !== 0) {
            return;
        }

        // Start session if needed
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Handle logout
        if ($path === '/pos-admin/logout') {
            self::handle_logout();
            exit;
        }

        // Handle login POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === '/pos-admin/login') {
            self::handle_login();
            exit;
        }

        // Check authentication
        if (empty($_SESSION['ppv_pos_admin_logged_in'])) {
            self::render_login_page();
            exit;
        }

        // Route to appropriate page
        switch ($path) {
            case '/pos-admin':
            case '/pos-admin/dashboard':
                self::render_dashboard();
                break;
            case '/pos-admin/gateways':
                self::render_gateways();
                break;
            case '/pos-admin/gateways/new':
                self::render_gateway_form();
                break;
            case '/pos-admin/transactions':
                self::render_transactions();
                break;
            case '/pos-admin/docs':
                self::render_docs();
                break;
            default:
                // Check for gateway detail page: /pos-admin/gateways/{id}
                if (preg_match('#/pos-admin/gateways/(\d+)#', $path, $matches)) {
                    self::render_gateway_detail((int)$matches[1]);
                } else {
                    self::render_dashboard();
                }
        }
        exit;
    }

    /**
     * Handle login
     */
    private static function handle_login() {
        global $wpdb;

        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            self::render_login_page('Bitte E-Mail und Passwort eingeben');
            return;
        }

        // WordPress Admin bypass - check if this is a WP admin user
        $wp_user = get_user_by('email', $email);
        if ($wp_user && user_can($wp_user, 'manage_options') && wp_check_password($password, $wp_user->user_pass, $wp_user->ID)) {
            // Admin login - pick first active store or create admin session
            $first_store = $wpdb->get_row("
                SELECT * FROM {$wpdb->prefix}ppv_stores WHERE active = 1 ORDER BY id ASC LIMIT 1
            ");

            $_SESSION['ppv_pos_admin_logged_in'] = true;
            $_SESSION['ppv_pos_admin_store_id'] = $first_store ? (int)$first_store->id : 0;
            $_SESSION['ppv_pos_admin_store_name'] = $first_store ? ($first_store->company_name ?: $first_store->name) : 'Admin';
            $_SESSION['ppv_pos_admin_email'] = $email;
            $_SESSION['ppv_pos_admin_user_id'] = $wp_user->ID;
            $_SESSION['ppv_pos_admin_is_wp_admin'] = true;

            wp_redirect('/pos-admin/dashboard');
            exit;
        }

        // Find store by email
        $store = $wpdb->get_row($wpdb->prepare("
            SELECT s.*, u.email as user_email
            FROM {$wpdb->prefix}ppv_stores s
            LEFT JOIN {$wpdb->prefix}ppv_users u ON s.user_id = u.id
            WHERE u.email = %s AND s.active = 1
            LIMIT 1
        ", $email));

        if (!$store) {
            self::render_login_page('Ungültige Anmeldedaten');
            return;
        }

        // Verify password
        $user = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_users WHERE email = %s LIMIT 1
        ", $email));

        if (!$user || !password_verify($password, $user->password)) {
            self::render_login_page('Ungültige Anmeldedaten');
            return;
        }

        // Check subscription
        if (!in_array($store->subscription_status, ['active', 'trial'])) {
            self::render_login_page('Ihr Abonnement ist abgelaufen');
            return;
        }

        // Set session
        $_SESSION['ppv_pos_admin_logged_in'] = true;
        $_SESSION['ppv_pos_admin_store_id'] = (int)$store->id;
        $_SESSION['ppv_pos_admin_store_name'] = $store->company_name ?: $store->name;
        $_SESSION['ppv_pos_admin_email'] = $email;
        $_SESSION['ppv_pos_admin_user_id'] = (int)$user->id;

        wp_redirect('/pos-admin/dashboard');
        exit;
    }

    /**
     * Handle logout
     */
    private static function handle_logout() {
        unset($_SESSION['ppv_pos_admin_logged_in']);
        unset($_SESSION['ppv_pos_admin_store_id']);
        unset($_SESSION['ppv_pos_admin_store_name']);
        unset($_SESSION['ppv_pos_admin_email']);
        unset($_SESSION['ppv_pos_admin_user_id']);
        wp_redirect('/pos-admin');
        exit;
    }

    /**
     * Get current store ID
     */
    private static function get_store_id(): int {
        return (int)($_SESSION['ppv_pos_admin_store_id'] ?? 0);
    }

    /**
     * Render login page
     */
    private static function render_login_page($error = null) {
        self::render_header('Login', false);
        ?>
        <div class="pos-login-container">
            <div class="pos-login-box">
                <div class="pos-login-logo">
                    <h1>🔌 POS Gateway</h1>
                    <p>PunktePass Kassza-Integráció</p>
                </div>

                <?php if ($error): ?>
                    <div class="pos-alert pos-alert-error"><?php echo esc_html($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="/pos-admin/login" class="pos-login-form">
                    <div class="pos-form-group">
                        <label for="email">E-Mail</label>
                        <input type="email" id="email" name="email" required autocomplete="email" placeholder="ihre@email.de">
                    </div>
                    <div class="pos-form-group">
                        <label for="password">Passwort</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                    </div>
                    <button type="submit" class="pos-btn pos-btn-primary pos-btn-block">Anmelden</button>
                </form>

                <div class="pos-login-footer">
                    <a href="/">← Zurück zu PunktePass</a>
                </div>
            </div>
        </div>
        <?php
        self::render_footer();
    }

    /**
     * Render dashboard
     */
    private static function render_dashboard() {
        global $wpdb;
        $store_id = self::get_store_id();

        // Get gateway count
        $gateway_count = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pos_gateways WHERE store_id = %d AND active = 1",
            $store_id
        ));

        // Get today's transactions
        $today_tx = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pos_gateway_transactions
             WHERE store_id = %d AND DATE(created_at) = CURDATE()",
            $store_id
        ));

        // Get total transactions
        $total_tx = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pos_gateway_transactions WHERE store_id = %d",
            $store_id
        ));

        // Get recent transactions
        $recent = $wpdb->get_results($wpdb->prepare("
            SELECT t.*, g.gateway_name, u.display_name as customer_name
            FROM {$wpdb->prefix}ppv_pos_gateway_transactions t
            LEFT JOIN {$wpdb->prefix}ppv_pos_gateways g ON t.gateway_id = g.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON t.user_id = u.id
            WHERE t.store_id = %d
            ORDER BY t.created_at DESC
            LIMIT 5
        ", $store_id));

        self::render_header('Dashboard');
        ?>
        <div class="pos-content">
            <h1>Dashboard</h1>

            <div class="pos-stats-grid">
                <div class="pos-stat-card">
                    <div class="pos-stat-icon">🔌</div>
                    <div class="pos-stat-value"><?php echo $gateway_count; ?></div>
                    <div class="pos-stat-label">Aktive Gateways</div>
                </div>
                <div class="pos-stat-card">
                    <div class="pos-stat-icon">📊</div>
                    <div class="pos-stat-value"><?php echo $today_tx; ?></div>
                    <div class="pos-stat-label">Heute</div>
                </div>
                <div class="pos-stat-card">
                    <div class="pos-stat-icon">📈</div>
                    <div class="pos-stat-value"><?php echo $total_tx; ?></div>
                    <div class="pos-stat-label">Gesamt</div>
                </div>
            </div>

            <?php if ($gateway_count === 0): ?>
                <div class="pos-empty-state">
                    <div class="pos-empty-icon">🔌</div>
                    <h2>Keine Gateways konfiguriert</h2>
                    <p>Erstellen Sie Ihr erstes Gateway, um Ihre Kasse mit PunktePass zu verbinden.</p>
                    <a href="/pos-admin/gateways/new" class="pos-btn pos-btn-primary">+ Neues Gateway erstellen</a>
                </div>
            <?php else: ?>
                <div class="pos-section">
                    <div class="pos-section-header">
                        <h2>Letzte Transaktionen</h2>
                        <a href="/pos-admin/transactions" class="pos-btn pos-btn-sm">Alle anzeigen →</a>
                    </div>

                    <?php if (empty($recent)): ?>
                        <p class="pos-muted">Noch keine Transaktionen</p>
                    <?php else: ?>
                        <table class="pos-table">
                            <thead>
                                <tr>
                                    <th>Zeit</th>
                                    <th>Gateway</th>
                                    <th>Kunde</th>
                                    <th>Betrag</th>
                                    <th>Punkte</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent as $tx): ?>
                                    <tr>
                                        <td><?php echo date('d.m H:i', strtotime($tx->created_at)); ?></td>
                                        <td><?php echo esc_html($tx->gateway_name ?: 'N/A'); ?></td>
                                        <td><?php echo esc_html($tx->customer_name ?: '—'); ?></td>
                                        <td><?php echo number_format($tx->total, 2); ?> <?php echo $tx->currency; ?></td>
                                        <td>
                                            <?php if ($tx->points_earned > 0): ?>
                                                <span class="pos-badge pos-badge-success">+<?php echo $tx->points_earned; ?></span>
                                            <?php endif; ?>
                                            <?php if ($tx->points_spent > 0): ?>
                                                <span class="pos-badge pos-badge-warning">-<?php echo $tx->points_spent; ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        self::render_footer();
    }

    /**
     * Render gateways list
     */
    private static function render_gateways() {
        global $wpdb;
        $store_id = self::get_store_id();

        $gateways = $wpdb->get_results($wpdb->prepare("
            SELECT g.*,
                   (SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pos_gateway_transactions WHERE gateway_id = g.id) as tx_count
            FROM {$wpdb->prefix}ppv_pos_gateways g
            WHERE g.store_id = %d
            ORDER BY g.created_at DESC
        ", $store_id));

        self::render_header('Gateways');
        ?>
        <div class="pos-content">
            <div class="pos-page-header">
                <h1>Gateways</h1>
                <a href="/pos-admin/gateways/new" class="pos-btn pos-btn-primary">+ Neues Gateway</a>
            </div>

            <?php if (empty($gateways)): ?>
                <div class="pos-empty-state">
                    <div class="pos-empty-icon">🔌</div>
                    <h2>Keine Gateways</h2>
                    <p>Erstellen Sie ein Gateway für jede Kasse, die Sie verbinden möchten.</p>
                </div>
            <?php else: ?>
                <div class="pos-cards-grid">
                    <?php foreach ($gateways as $gw): ?>
                        <div class="pos-gateway-card <?php echo $gw->active ? '' : 'pos-gateway-inactive'; ?>">
                            <div class="pos-gateway-header">
                                <span class="pos-gateway-type"><?php echo strtoupper($gw->gateway_type); ?></span>
                                <span class="pos-gateway-status <?php echo $gw->active ? 'active' : 'inactive'; ?>">
                                    <?php echo $gw->active ? '● Aktiv' : '○ Inaktiv'; ?>
                                </span>
                            </div>
                            <h3><?php echo esc_html($gw->gateway_name); ?></h3>
                            <div class="pos-gateway-meta">
                                <span>📊 <?php echo $gw->tx_count; ?> Transaktionen</span>
                                <?php if ($gw->last_activity_at): ?>
                                    <span>🕐 <?php echo date('d.m.Y H:i', strtotime($gw->last_activity_at)); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="pos-gateway-actions">
                                <a href="/pos-admin/gateways/<?php echo $gw->id; ?>" class="pos-btn pos-btn-sm">Details →</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        self::render_footer();
    }

    /**
     * Render gateway detail
     */
    private static function render_gateway_detail(int $gateway_id) {
        global $wpdb;
        $store_id = self::get_store_id();

        $gateway = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_pos_gateways
            WHERE id = %d AND store_id = %d
        ", $gateway_id, $store_id));

        if (!$gateway) {
            wp_redirect('/pos-admin/gateways');
            exit;
        }

        // Handle actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if ($action === 'regenerate_key') {
                $new_key = 'pk_live_' . bin2hex(random_bytes(16));
                $wpdb->update(
                    $wpdb->prefix . 'ppv_pos_gateways',
                    ['api_key' => $new_key],
                    ['id' => $gateway_id]
                );
                $gateway->api_key = $new_key;
                $_GET['regenerated'] = 1;
            } elseif ($action === 'toggle_active') {
                $new_status = $gateway->active ? 0 : 1;
                $wpdb->update(
                    $wpdb->prefix . 'ppv_pos_gateways',
                    ['active' => $new_status],
                    ['id' => $gateway_id]
                );
                $gateway->active = $new_status;
            } elseif ($action === 'delete') {
                $wpdb->delete($wpdb->prefix . 'ppv_pos_gateways', ['id' => $gateway_id]);
                wp_redirect('/pos-admin/gateways?deleted=1');
                exit;
            } elseif ($action === 'update') {
                $wpdb->update(
                    $wpdb->prefix . 'ppv_pos_gateways',
                    ['gateway_name' => sanitize_text_field($_POST['gateway_name'])],
                    ['id' => $gateway_id]
                );
                $gateway->gateway_name = sanitize_text_field($_POST['gateway_name']);
                $_GET['updated'] = 1;
            }
        }

        // Get recent transactions
        $transactions = $wpdb->get_results($wpdb->prepare("
            SELECT t.*, u.display_name as customer_name
            FROM {$wpdb->prefix}ppv_pos_gateway_transactions t
            LEFT JOIN {$wpdb->prefix}ppv_users u ON t.user_id = u.id
            WHERE t.gateway_id = %d
            ORDER BY t.created_at DESC
            LIMIT 20
        ", $gateway_id));

        self::render_header($gateway->gateway_name);
        ?>
        <div class="pos-content">
            <div class="pos-breadcrumb">
                <a href="/pos-admin/gateways">← Gateways</a>
            </div>

            <?php if (!empty($_GET['regenerated'])): ?>
                <div class="pos-alert pos-alert-warning">⚠️ API-Schlüssel wurde neu generiert. Bitte aktualisieren Sie Ihre Kasse!</div>
            <?php endif; ?>

            <?php if (!empty($_GET['updated'])): ?>
                <div class="pos-alert pos-alert-success">✅ Gateway aktualisiert</div>
            <?php endif; ?>

            <div class="pos-gateway-detail">
                <div class="pos-detail-header">
                    <div>
                        <span class="pos-gateway-type-badge"><?php echo strtoupper($gateway->gateway_type); ?></span>
                        <h1><?php echo esc_html($gateway->gateway_name); ?></h1>
                    </div>
                    <span class="pos-gateway-status <?php echo $gateway->active ? 'active' : 'inactive'; ?>">
                        <?php echo $gateway->active ? '● Aktiv' : '○ Inaktiv'; ?>
                    </span>
                </div>

                <!-- API Key Section -->
                <div class="pos-detail-section">
                    <h2>🔑 API-Schlüssel</h2>
                    <div class="pos-api-key-box">
                        <code id="api-key"><?php echo esc_html($gateway->api_key); ?></code>
                        <button onclick="copyApiKey()" class="pos-btn pos-btn-sm">📋 Kopieren</button>
                    </div>
                    <p class="pos-muted">Diesen Schlüssel in Ihrer Kasse als <code>X-POS-API-Key</code> Header konfigurieren.</p>

                    <form method="POST" style="margin-top: 1rem;" onsubmit="return confirm('API-Schlüssel wirklich neu generieren? Die alte Konfiguration funktioniert dann nicht mehr!');">
                        <input type="hidden" name="action" value="regenerate_key">
                        <button type="submit" class="pos-btn pos-btn-warning pos-btn-sm">🔄 Neu generieren</button>
                    </form>
                </div>

                <!-- Settings Section -->
                <div class="pos-detail-section">
                    <h2>⚙️ Einstellungen</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="update">
                        <div class="pos-form-group">
                            <label>Gateway Name</label>
                            <input type="text" name="gateway_name" value="<?php echo esc_attr($gateway->gateway_name); ?>" required>
                        </div>
                        <button type="submit" class="pos-btn pos-btn-primary">Speichern</button>
                    </form>

                    <hr style="margin: 1.5rem 0;">

                    <div class="pos-action-row">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_active">
                            <button type="submit" class="pos-btn <?php echo $gateway->active ? 'pos-btn-warning' : 'pos-btn-success'; ?>">
                                <?php echo $gateway->active ? '⏸ Deaktivieren' : '▶️ Aktivieren'; ?>
                            </button>
                        </form>

                        <form method="POST" style="display: inline;" onsubmit="return confirm('Gateway wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden!');">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="pos-btn pos-btn-danger">🗑️ Löschen</button>
                        </form>
                    </div>
                </div>

                <!-- Transactions Section -->
                <div class="pos-detail-section">
                    <h2>📊 Letzte Transaktionen</h2>
                    <?php if (empty($transactions)): ?>
                        <p class="pos-muted">Noch keine Transaktionen über dieses Gateway</p>
                    <?php else: ?>
                        <table class="pos-table">
                            <thead>
                                <tr>
                                    <th>Zeit</th>
                                    <th>Kunde</th>
                                    <th>Betrag</th>
                                    <th>Rabatt</th>
                                    <th>Punkte</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr>
                                        <td><?php echo date('d.m H:i', strtotime($tx->created_at)); ?></td>
                                        <td><?php echo esc_html($tx->customer_name ?: '—'); ?></td>
                                        <td><?php echo number_format($tx->total, 2); ?> <?php echo $tx->currency; ?></td>
                                        <td><?php echo $tx->discount_amount > 0 ? '-' . number_format($tx->discount_amount, 2) : '—'; ?></td>
                                        <td>
                                            <?php if ($tx->points_earned > 0): ?>
                                                <span class="pos-badge pos-badge-success">+<?php echo $tx->points_earned; ?></span>
                                            <?php endif; ?>
                                            <?php if ($tx->points_spent > 0): ?>
                                                <span class="pos-badge pos-badge-warning">-<?php echo $tx->points_spent; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="pos-badge pos-badge-<?php echo $tx->status === 'completed' ? 'success' : 'error'; ?>">
                                                <?php echo $tx->status; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        function copyApiKey() {
            const key = document.getElementById('api-key').textContent;
            navigator.clipboard.writeText(key).then(() => {
                alert('API-Schlüssel kopiert!');
            });
        }
        </script>
        <?php
        self::render_footer();
    }

    /**
     * Render gateway creation form
     */
    private static function render_gateway_form() {
        global $wpdb;
        $store_id = self::get_store_id();

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $gateway_name = sanitize_text_field($_POST['gateway_name'] ?? '');
            $gateway_type = sanitize_text_field($_POST['gateway_type'] ?? 'generic');

            if (empty($gateway_name)) {
                $error = 'Bitte Gateway-Name eingeben';
            } else {
                $api_key = 'pk_live_' . bin2hex(random_bytes(16));
                $verification_token = 'vt_' . bin2hex(random_bytes(16));

                $wpdb->insert($wpdb->prefix . 'ppv_pos_gateways', [
                    'store_id' => $store_id,
                    'gateway_type' => $gateway_type,
                    'gateway_name' => $gateway_name,
                    'api_key' => $api_key,
                    'verification_token' => $verification_token,
                    'active' => 1,
                    'created_at' => current_time('mysql')
                ]);

                wp_redirect('/pos-admin/gateways/' . $wpdb->insert_id . '?created=1');
                exit;
            }
        }

        self::render_header('Neues Gateway');
        ?>
        <div class="pos-content">
            <div class="pos-breadcrumb">
                <a href="/pos-admin/gateways">← Gateways</a>
            </div>

            <h1>Neues Gateway erstellen</h1>

            <?php if (!empty($error)): ?>
                <div class="pos-alert pos-alert-error"><?php echo esc_html($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="pos-form">
                <div class="pos-form-group">
                    <label for="gateway_name">Gateway Name *</label>
                    <input type="text" id="gateway_name" name="gateway_name" required placeholder="z.B. Kasse 1, Hauptkasse, etc.">
                    <small>Ein beschreibender Name für diese Kasse</small>
                </div>

                <div class="pos-form-group">
                    <label for="gateway_type">Kassen-Typ</label>
                    <select id="gateway_type" name="gateway_type">
                        <option value="generic">Generic (Alle Kassen)</option>
                        <option value="sumup">SumUp</option>
                        <option value="ready2order">ready2order</option>
                        <option value="zettle">Zettle (PayPal)</option>
                    </select>
                    <small>Wählen Sie Ihren Kassen-Anbieter oder "Generic" für andere</small>
                </div>

                <div class="pos-form-actions">
                    <button type="submit" class="pos-btn pos-btn-primary">Gateway erstellen</button>
                    <a href="/pos-admin/gateways" class="pos-btn">Abbrechen</a>
                </div>
            </form>
        </div>
        <?php
        self::render_footer();
    }

    /**
     * Render transactions page
     */
    private static function render_transactions() {
        global $wpdb;
        $store_id = self::get_store_id();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        $total = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pos_gateway_transactions WHERE store_id = %d",
            $store_id
        ));

        $transactions = $wpdb->get_results($wpdb->prepare("
            SELECT t.*, g.gateway_name, u.display_name as customer_name
            FROM {$wpdb->prefix}ppv_pos_gateway_transactions t
            LEFT JOIN {$wpdb->prefix}ppv_pos_gateways g ON t.gateway_id = g.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON t.user_id = u.id
            WHERE t.store_id = %d
            ORDER BY t.created_at DESC
            LIMIT %d OFFSET %d
        ", $store_id, $per_page, $offset));

        $total_pages = ceil($total / $per_page);

        self::render_header('Transaktionen');
        ?>
        <div class="pos-content">
            <h1>Transaktionen</h1>
            <p class="pos-muted"><?php echo $total; ?> Transaktionen gesamt</p>

            <?php if (empty($transactions)): ?>
                <div class="pos-empty-state">
                    <p>Noch keine Transaktionen</p>
                </div>
            <?php else: ?>
                <table class="pos-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Zeit</th>
                            <th>Gateway</th>
                            <th>Kunde</th>
                            <th>Betrag</th>
                            <th>Rabatt</th>
                            <th>Punkte</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td>#<?php echo $tx->id; ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($tx->created_at)); ?></td>
                                <td><?php echo esc_html($tx->gateway_name ?: 'N/A'); ?></td>
                                <td><?php echo esc_html($tx->customer_name ?: '—'); ?></td>
                                <td><?php echo number_format($tx->total, 2); ?> <?php echo $tx->currency; ?></td>
                                <td><?php echo $tx->discount_amount > 0 ? '-' . number_format($tx->discount_amount, 2) : '—'; ?></td>
                                <td>
                                    <?php if ($tx->points_earned > 0): ?><span class="pos-badge pos-badge-success">+<?php echo $tx->points_earned; ?></span><?php endif; ?>
                                    <?php if ($tx->points_spent > 0): ?><span class="pos-badge pos-badge-warning">-<?php echo $tx->points_spent; ?></span><?php endif; ?>
                                </td>
                                <td><span class="pos-badge pos-badge-<?php echo $tx->status === 'completed' ? 'success' : 'error'; ?>"><?php echo $tx->status; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="pos-pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="pos-btn pos-btn-sm">← Zurück</a>
                        <?php endif; ?>
                        <span>Seite <?php echo $page; ?> von <?php echo $total_pages; ?></span>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="pos-btn pos-btn-sm">Weiter →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        self::render_footer();
    }

    /**
     * Render API documentation
     */
    private static function render_docs() {
        $base_url = home_url('/wp-json/punktepass/v1/pos-gateway');

        self::render_header('API Dokumentation');
        ?>
        <div class="pos-content pos-docs">
            <h1>API Dokumentation</h1>
            <p>Verwenden Sie diese Endpunkte, um Ihre Kasse mit PunktePass zu verbinden.</p>

            <div class="pos-doc-section">
                <h2>🔐 Authentifizierung</h2>
                <p>Alle Anfragen müssen den API-Schlüssel im Header enthalten:</p>
                <pre><code>X-POS-API-Key: pk_live_ihr_api_schluessel</code></pre>
            </div>

            <div class="pos-doc-section">
                <h2>📱 Kunde per QR-Code identifizieren</h2>
                <pre><code>POST <?php echo $base_url; ?>/customer/qr-lookup

{
    "qr_code": "PP-U-abc123xyz"
}

// Antwort:
{
    "success": true,
    "customer": {
        "id": "12345",
        "name": "Max Mustermann",
        "points_balance": 230,
        "vip_level": "silver",
        "available_rewards": [...]
    }
}</code></pre>
            </div>

            <div class="pos-doc-section">
                <h2>🎁 Verfügbare Belohnungen abrufen</h2>
                <pre><code>GET <?php echo $base_url; ?>/customer/{id}/rewards?cart_total=25.00

// Antwort:
{
    "success": true,
    "rewards": [
        {
            "id": "1",
            "title": "5 EUR Rabatt",
            "type": "money_off",
            "value": 5.00,
            "required_points": 100
        }
    ]
}</code></pre>
            </div>

            <div class="pos-doc-section">
                <h2>💳 Transaktion abschließen</h2>
                <pre><code>POST <?php echo $base_url; ?>/transaction/complete

{
    "transaction_id": "pos_tx_12345",
    "customer_id": "12345",
    "subtotal": 24.50,
    "discount_applied": {
        "reward_id": "1",
        "amount": 5.00
    },
    "total": 19.50,
    "currency": "EUR"
}

// Antwort:
{
    "success": true,
    "points_earned": 24,
    "points_spent": 100,
    "new_balance": 154,
    "message": "Vielen Dank! Sie haben 24 Punkte gesammelt."
}</code></pre>
            </div>

            <div class="pos-doc-section">
                <h2>🔍 Alle Endpunkte</h2>
                <table class="pos-table">
                    <thead>
                        <tr><th>Methode</th><th>Endpunkt</th><th>Beschreibung</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>POST</td><td>/customer/search</td><td>Kunde nach Name suchen</td></tr>
                        <tr><td>POST</td><td>/customer/qr-lookup</td><td>Kunde per QR-Code identifizieren</td></tr>
                        <tr><td>GET</td><td>/customer/{id}/balance</td><td>Punktestand abrufen</td></tr>
                        <tr><td>GET</td><td>/customer/{id}/rewards</td><td>Verfügbare Belohnungen</td></tr>
                        <tr><td>POST</td><td>/reward/apply</td><td>Belohnung anwenden</td></tr>
                        <tr><td>POST</td><td>/transaction/complete</td><td>Transaktion abschließen</td></tr>
                        <tr><td>POST</td><td>/transaction/cancel</td><td>Transaktion stornieren</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        self::render_footer();
    }

    /**
     * Render page header
     */
    private static function render_header($title, $show_nav = true) {
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($title); ?> - POS Gateway</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
            <style><?php echo self::get_styles(); ?></style>
        </head>
        <body>
            <?php if ($show_nav): ?>
            <nav class="pos-nav">
                <div class="pos-nav-brand">
                    <span class="pos-nav-logo">🔌</span>
                    <span>POS Gateway</span>
                </div>
                <div class="pos-nav-store"><?php echo esc_html($_SESSION['ppv_pos_admin_store_name'] ?? ''); ?></div>
                <div class="pos-nav-links">
                    <a href="/pos-admin/dashboard" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false || $_SERVER['REQUEST_URI'] === '/pos-admin' ? 'active' : ''; ?>">Dashboard</a>
                    <a href="/pos-admin/gateways" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'gateways') !== false ? 'active' : ''; ?>">Gateways</a>
                    <a href="/pos-admin/transactions" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'transactions') !== false ? 'active' : ''; ?>">Transaktionen</a>
                    <a href="/pos-admin/docs" class="<?php echo strpos($_SERVER['REQUEST_URI'], 'docs') !== false ? 'active' : ''; ?>">API Docs</a>
                    <a href="/pos-admin/logout" class="pos-nav-logout">Abmelden</a>
                </div>
            </nav>
            <?php endif; ?>
            <main class="pos-main">
        <?php
    }

    /**
     * Render page footer
     */
    private static function render_footer() {
        ?>
            </main>
            <footer class="pos-footer">
                <p>PunktePass POS Gateway &copy; <?php echo date('Y'); ?></p>
            </footer>
        </body>
        </html>
        <?php
    }

    /**
     * Get CSS styles
     */
    private static function get_styles() {
        return <<<CSS
:root {
    --pos-primary: #00bfff;
    --pos-success: #00c853;
    --pos-warning: #ffb300;
    --pos-danger: #e53935;
    --pos-bg: #0b0f17;
    --pos-card: #141a24;
    --pos-border: #1e2836;
    --pos-text: #e4e8ec;
    --pos-muted: #6b7280;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Inter', -apple-system, sans-serif;
    background: var(--pos-bg);
    color: var(--pos-text);
    min-height: 100vh;
    line-height: 1.5;
}

/* Navigation */
.pos-nav {
    display: flex;
    align-items: center;
    gap: 2rem;
    padding: 1rem 2rem;
    background: var(--pos-card);
    border-bottom: 1px solid var(--pos-border);
    position: sticky;
    top: 0;
    z-index: 100;
}

.pos-nav-brand {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    font-size: 1.1rem;
}

.pos-nav-logo { font-size: 1.5rem; }

.pos-nav-store {
    color: var(--pos-muted);
    font-size: 0.9rem;
    padding: 0.25rem 0.75rem;
    background: rgba(255,255,255,0.05);
    border-radius: 4px;
}

.pos-nav-links {
    display: flex;
    gap: 0.5rem;
    margin-left: auto;
}

.pos-nav-links a {
    color: var(--pos-muted);
    text-decoration: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    transition: all 0.2s;
}

.pos-nav-links a:hover,
.pos-nav-links a.active {
    color: var(--pos-text);
    background: rgba(255,255,255,0.1);
}

.pos-nav-logout { color: var(--pos-danger) !important; }

/* Main Content */
.pos-main {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
    min-height: calc(100vh - 140px);
}

.pos-content h1 {
    font-size: 1.75rem;
    margin-bottom: 1.5rem;
}

/* Login */
.pos-login-container {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 2rem;
}

.pos-login-box {
    background: var(--pos-card);
    border: 1px solid var(--pos-border);
    border-radius: 12px;
    padding: 2.5rem;
    width: 100%;
    max-width: 400px;
}

.pos-login-logo {
    text-align: center;
    margin-bottom: 2rem;
}

.pos-login-logo h1 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.pos-login-logo p {
    color: var(--pos-muted);
}

.pos-login-footer {
    text-align: center;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--pos-border);
}

.pos-login-footer a {
    color: var(--pos-muted);
    text-decoration: none;
}

/* Forms */
.pos-form-group {
    margin-bottom: 1.25rem;
}

.pos-form-group label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.pos-form-group input,
.pos-form-group select {
    width: 100%;
    padding: 0.75rem 1rem;
    background: var(--pos-bg);
    border: 1px solid var(--pos-border);
    border-radius: 8px;
    color: var(--pos-text);
    font-size: 1rem;
}

.pos-form-group input:focus,
.pos-form-group select:focus {
    outline: none;
    border-color: var(--pos-primary);
}

.pos-form-group small {
    display: block;
    color: var(--pos-muted);
    margin-top: 0.25rem;
    font-size: 0.85rem;
}

.pos-form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

/* Buttons */
.pos-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
    background: var(--pos-border);
    color: var(--pos-text);
}

.pos-btn:hover { filter: brightness(1.1); }

.pos-btn-primary { background: var(--pos-primary); color: #000; }
.pos-btn-success { background: var(--pos-success); color: #000; }
.pos-btn-warning { background: var(--pos-warning); color: #000; }
.pos-btn-danger { background: var(--pos-danger); color: #fff; }
.pos-btn-block { width: 100%; }
.pos-btn-sm { padding: 0.5rem 1rem; font-size: 0.875rem; }

/* Stats Grid */
.pos-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.pos-stat-card {
    background: var(--pos-card);
    border: 1px solid var(--pos-border);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
}

.pos-stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.pos-stat-value { font-size: 2rem; font-weight: 700; }
.pos-stat-label { color: var(--pos-muted); }

/* Empty State */
.pos-empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--pos-card);
    border: 1px solid var(--pos-border);
    border-radius: 12px;
}

.pos-empty-icon { font-size: 4rem; margin-bottom: 1rem; }
.pos-empty-state h2 { margin-bottom: 0.5rem; }
.pos-empty-state p { color: var(--pos-muted); margin-bottom: 1.5rem; }

/* Cards Grid */
.pos-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.pos-gateway-card {
    background: var(--pos-card);
    border: 1px solid var(--pos-border);
    border-radius: 12px;
    padding: 1.5rem;
}

.pos-gateway-card.pos-gateway-inactive {
    opacity: 0.6;
}

.pos-gateway-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}

.pos-gateway-type {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    background: rgba(0,191,255,0.2);
    color: var(--pos-primary);
    border-radius: 4px;
}

.pos-gateway-status {
    font-size: 0.85rem;
}

.pos-gateway-status.active { color: var(--pos-success); }
.pos-gateway-status.inactive { color: var(--pos-muted); }

.pos-gateway-card h3 {
    margin-bottom: 0.75rem;
}

.pos-gateway-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    color: var(--pos-muted);
    font-size: 0.85rem;
    margin-bottom: 1rem;
}

.pos-gateway-actions {
    padding-top: 1rem;
    border-top: 1px solid var(--pos-border);
}

/* Tables */
.pos-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--pos-card);
    border-radius: 12px;
    overflow: hidden;
}

.pos-table th,
.pos-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--pos-border);
}

.pos-table th {
    background: rgba(0,0,0,0.2);
    font-weight: 600;
    color: var(--pos-muted);
    font-size: 0.85rem;
    text-transform: uppercase;
}

.pos-table tr:last-child td { border-bottom: none; }

/* Badges */
.pos-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.pos-badge-success { background: rgba(0,200,83,0.2); color: var(--pos-success); }
.pos-badge-warning { background: rgba(255,179,0,0.2); color: var(--pos-warning); }
.pos-badge-error { background: rgba(229,57,53,0.2); color: var(--pos-danger); }

/* Alerts */
.pos-alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.pos-alert-error { background: rgba(229,57,53,0.2); border: 1px solid var(--pos-danger); }
.pos-alert-success { background: rgba(0,200,83,0.2); border: 1px solid var(--pos-success); }
.pos-alert-warning { background: rgba(255,179,0,0.2); border: 1px solid var(--pos-warning); }

/* Sections */
.pos-section {
    background: var(--pos-card);
    border: 1px solid var(--pos-border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.pos-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.pos-section-header h2 { margin: 0; }

/* Detail Page */
.pos-breadcrumb {
    margin-bottom: 1.5rem;
}

.pos-breadcrumb a {
    color: var(--pos-muted);
    text-decoration: none;
}

.pos-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
}

.pos-gateway-type-badge {
    display: inline-block;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem 0.75rem;
    background: rgba(0,191,255,0.2);
    color: var(--pos-primary);
    border-radius: 4px;
    margin-bottom: 0.5rem;
}

.pos-detail-section {
    background: var(--pos-card);
    border: 1px solid var(--pos-border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.pos-detail-section h2 {
    margin-bottom: 1rem;
    font-size: 1.1rem;
}

.pos-api-key-box {
    display: flex;
    gap: 1rem;
    align-items: center;
    background: var(--pos-bg);
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.pos-api-key-box code {
    flex: 1;
    font-family: monospace;
    font-size: 0.9rem;
    word-break: break-all;
}

.pos-action-row {
    display: flex;
    gap: 1rem;
}

/* Page Header */
.pos-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.pos-page-header h1 { margin: 0; }

/* Pagination */
.pos-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--pos-border);
}

/* Docs */
.pos-docs pre {
    background: var(--pos-bg);
    border: 1px solid var(--pos-border);
    border-radius: 8px;
    padding: 1rem;
    overflow-x: auto;
    margin: 1rem 0;
}

.pos-docs code {
    font-family: 'SF Mono', Monaco, monospace;
    font-size: 0.85rem;
}

.pos-doc-section {
    background: var(--pos-card);
    border: 1px solid var(--pos-border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.pos-doc-section h2 {
    margin-bottom: 1rem;
}

/* Utilities */
.pos-muted { color: var(--pos-muted); }

/* Footer */
.pos-footer {
    text-align: center;
    padding: 2rem;
    color: var(--pos-muted);
    font-size: 0.85rem;
}

/* Responsive */
@media (max-width: 768px) {
    .pos-nav {
        flex-wrap: wrap;
        gap: 1rem;
    }

    .pos-nav-links {
        width: 100%;
        overflow-x: auto;
    }

    .pos-main {
        padding: 1rem;
    }

    .pos-cards-grid {
        grid-template-columns: 1fr;
    }

    .pos-action-row {
        flex-direction: column;
    }
}
CSS;
    }
}
