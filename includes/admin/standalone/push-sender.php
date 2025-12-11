<?php
/**
 * PunktePass Standalone Admin - Push Notification Sender
 * Route: /admin/push-sender
 * Send push notifications to users and stores
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Push_Sender {

    /**
     * Render push sender page
     */
    public static function render() {
        global $wpdb;

        // Handle POST actions
        $message = '';
        $message_type = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['send_push'])) {
                $result = self::handle_send_push();
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
            } elseif (isset($_POST['test_push'])) {
                $result = self::handle_test_push();
                $message = $result['message'];
                $message_type = $result['success'] ? 'success' : 'error';
            }
        }

        // Get stats
        $stats = self::get_stats();

        // Get subscriptions
        $subscriptions = $wpdb->get_results("
            SELECT ps.*, u.name as user_name, u.email as user_email, s.company_name as store_name
            FROM {$wpdb->prefix}ppv_push_subscriptions ps
            LEFT JOIN {$wpdb->prefix}ppv_users u ON ps.user_id = u.id
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON ps.store_id = s.id
            WHERE ps.is_active = 1
            ORDER BY ps.updated_at DESC
            LIMIT 100
        ");

        // Get stores for dropdown
        $stores = $wpdb->get_results("SELECT id, company_name FROM {$wpdb->prefix}ppv_stores ORDER BY company_name");

        self::render_html($subscriptions, $stores, $stats, $message, $message_type);
    }

    /**
     * Get push notification statistics
     */
    private static function get_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_push_subscriptions';

        return [
            'total' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_active = 1"),
            'ios' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND platform = 'ios'"),
            'android' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND platform = 'android'"),
            'web' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND platform = 'web'"),
            'stores' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND store_id IS NOT NULL"),
            'users' => (int)$wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE is_active = 1"),
        ];
    }

    /**
     * Handle send push notification
     */
    private static function handle_send_push() {
        if (!class_exists('PPV_Push') || !PPV_Push::is_enabled()) {
            return ['success' => false, 'message' => 'Push notifications nicht konfiguriert (PPV_FCM_SERVER_KEY fehlt)'];
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $body = sanitize_text_field($_POST['body'] ?? '');
        $target = sanitize_text_field($_POST['target'] ?? '');
        $store_id = intval($_POST['store_id'] ?? 0);
        $user_ids = $_POST['user_ids'] ?? '';

        if (empty($title) || empty($body)) {
            return ['success' => false, 'message' => 'Titel und Nachricht sind erforderlich'];
        }

        $payload = [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'admin_message',
                'timestamp' => time()
            ]
        ];

        $results = [];

        switch ($target) {
            case 'all':
                // Send to all users
                global $wpdb;
                $all_user_ids = $wpdb->get_col("SELECT DISTINCT user_id FROM {$wpdb->prefix}ppv_push_subscriptions WHERE is_active = 1");
                foreach ($all_user_ids as $uid) {
                    $results[] = PPV_Push::send_to_user($uid, $payload);
                }
                break;

            case 'store':
                if (!$store_id) {
                    return ['success' => false, 'message' => 'Bitte Store auswählen'];
                }
                $results[] = PPV_Push::send_to_store_customers($store_id, $payload);
                break;

            case 'users':
                if (empty($user_ids)) {
                    return ['success' => false, 'message' => 'Bitte User IDs eingeben'];
                }
                $ids = array_map('intval', explode(',', $user_ids));
                foreach ($ids as $uid) {
                    if ($uid > 0) {
                        $results[] = PPV_Push::send_to_user($uid, $payload);
                    }
                }
                break;

            case 'stores_pos':
                // Send to all POS devices
                global $wpdb;
                $pos_user_ids = $wpdb->get_col("SELECT DISTINCT user_id FROM {$wpdb->prefix}ppv_push_subscriptions WHERE is_active = 1 AND store_id IS NOT NULL");
                foreach ($pos_user_ids as $uid) {
                    $results[] = PPV_Push::send_to_user($uid, $payload);
                }
                break;

            default:
                return ['success' => false, 'message' => 'Ungültiges Ziel'];
        }

        $total_sent = 0;
        $total_failed = 0;
        foreach ($results as $r) {
            $total_sent += ($r['sent'] ?? ($r['success'] ? 1 : 0));
            $total_failed += ($r['failed'] ?? 0);
        }

        ppv_log("[PPV Push Admin] Push gesendet: {$title} - Gesendet: {$total_sent}, Fehlgeschlagen: {$total_failed}");

        return [
            'success' => $total_sent > 0,
            'message' => "Push gesendet: {$total_sent} erfolgreich, {$total_failed} fehlgeschlagen"
        ];
    }

    /**
     * Handle test push notification
     */
    private static function handle_test_push() {
        if (!class_exists('PPV_Push') || !PPV_Push::is_enabled()) {
            return ['success' => false, 'message' => 'Push notifications nicht konfiguriert (PPV_FCM_SERVER_KEY fehlt)'];
        }

        $token = sanitize_text_field($_POST['test_token'] ?? '');
        $user_id = intval($_POST['test_user_id'] ?? 0);

        if (empty($token) && !$user_id) {
            return ['success' => false, 'message' => 'Bitte Token oder User ID eingeben'];
        }

        $payload = [
            'title' => 'PunktePass Test',
            'body' => 'Dies ist eine Test-Benachrichtigung!',
            'data' => ['type' => 'test', 'timestamp' => time()]
        ];

        if ($token) {
            $result = PPV_Push::send_to_token($token, $payload);
        } else {
            $result = PPV_Push::send_to_user($user_id, $payload);
        }

        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Test-Push erfolgreich gesendet!' : 'Fehler: ' . ($result['message'] ?? 'Unbekannt')
        ];
    }

    /**
     * Render HTML
     */
    private static function render_html($subscriptions, $stores, $stats, $message, $message_type) {
        $fcm_enabled = class_exists('PPV_Push') && PPV_Push::is_enabled();
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Push Notifications - PunktePass Admin</title>
            <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
            <style>
                :root {
                    --bg: #0b0f17;
                    --card: #151b28;
                    --border: #2a3342;
                    --text: #e0e6ed;
                    --text-muted: #8892a4;
                    --primary: #00bfff;
                    --success: #00c853;
                    --warning: #ffb300;
                    --danger: #ff5252;
                }
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Inter', -apple-system, sans-serif;
                    background: var(--bg);
                    color: var(--text);
                    min-height: 100vh;
                    padding: 20px;
                }
                .container { max-width: 1200px; margin: 0 auto; }
                h1 { font-size: 24px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
                h1 i { color: var(--primary); }
                .back-link { color: var(--text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 20px; }
                .back-link:hover { color: var(--primary); }

                .alert {
                    padding: 12px 16px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .alert-success { background: rgba(0, 200, 83, 0.15); border: 1px solid var(--success); }
                .alert-error { background: rgba(255, 82, 82, 0.15); border: 1px solid var(--danger); }
                .alert-warning { background: rgba(255, 179, 0, 0.15); border: 1px solid var(--warning); }

                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 15px;
                    margin-bottom: 30px;
                }
                .stat-card {
                    background: var(--card);
                    border: 1px solid var(--border);
                    border-radius: 12px;
                    padding: 20px;
                    text-align: center;
                }
                .stat-card .number { font-size: 32px; font-weight: 700; color: var(--primary); }
                .stat-card .label { font-size: 13px; color: var(--text-muted); margin-top: 5px; }
                .stat-card.ios .number { color: #007aff; }
                .stat-card.android .number { color: #3ddc84; }
                .stat-card.web .number { color: #ff9800; }

                .card {
                    background: var(--card);
                    border: 1px solid var(--border);
                    border-radius: 12px;
                    padding: 20px;
                    margin-bottom: 20px;
                }
                .card-title { font-size: 18px; font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }

                .form-group { margin-bottom: 15px; }
                .form-group label { display: block; font-size: 13px; color: var(--text-muted); margin-bottom: 5px; }
                .form-group input, .form-group select, .form-group textarea {
                    width: 100%;
                    padding: 10px 12px;
                    background: var(--bg);
                    border: 1px solid var(--border);
                    border-radius: 8px;
                    color: var(--text);
                    font-size: 14px;
                }
                .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
                    outline: none;
                    border-color: var(--primary);
                }
                .form-group textarea { min-height: 100px; resize: vertical; }

                .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
                @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }

                .btn {
                    padding: 10px 20px;
                    border: none;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    transition: all 0.2s;
                }
                .btn-primary { background: var(--primary); color: #000; }
                .btn-primary:hover { background: #00a8e0; }
                .btn-secondary { background: var(--border); color: var(--text); }
                .btn-secondary:hover { background: #3a4352; }
                .btn:disabled { opacity: 0.5; cursor: not-allowed; }

                .target-options { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; }
                .target-option {
                    padding: 8px 16px;
                    background: var(--bg);
                    border: 1px solid var(--border);
                    border-radius: 20px;
                    cursor: pointer;
                    font-size: 13px;
                    transition: all 0.2s;
                }
                .target-option:hover { border-color: var(--primary); }
                .target-option.active { background: var(--primary); color: #000; border-color: var(--primary); }
                .target-option input { display: none; }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 13px;
                }
                th, td {
                    padding: 12px;
                    text-align: left;
                    border-bottom: 1px solid var(--border);
                }
                th { color: var(--text-muted); font-weight: 500; }
                .platform-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    padding: 4px 10px;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: 500;
                }
                .platform-ios { background: rgba(0, 122, 255, 0.2); color: #007aff; }
                .platform-android { background: rgba(61, 220, 132, 0.2); color: #3ddc84; }
                .platform-web { background: rgba(255, 152, 0, 0.2); color: #ff9800; }

                .token-preview {
                    max-width: 200px;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    font-family: monospace;
                    font-size: 11px;
                    color: var(--text-muted);
                }

                .hidden { display: none; }
            </style>
        </head>
        <body>
            <div class="container">
                <a href="/admin" class="back-link"><i class="ri-arrow-left-line"></i> Zurück zum Admin</a>

                <h1><i class="ri-notification-3-line"></i> Push Notifications</h1>

                <?php if (!$fcm_enabled): ?>
                <div class="alert alert-warning">
                    <i class="ri-alert-line"></i>
                    <div>
                        <strong>FCM nicht konfiguriert!</strong><br>
                        Bitte <code>PPV_FCM_SERVER_KEY</code> in wp-config.php setzen.
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="ri-<?php echo $message_type === 'success' ? 'check' : 'close'; ?>-circle-line"></i>
                    <?php echo esc_html($message); ?>
                </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['total']; ?></div>
                        <div class="label">Gesamt Abos</div>
                    </div>
                    <div class="stat-card ios">
                        <div class="number"><?php echo $stats['ios']; ?></div>
                        <div class="label"><i class="ri-apple-fill"></i> iOS</div>
                    </div>
                    <div class="stat-card android">
                        <div class="number"><?php echo $stats['android']; ?></div>
                        <div class="label"><i class="ri-android-fill"></i> Android</div>
                    </div>
                    <div class="stat-card web">
                        <div class="number"><?php echo $stats['web']; ?></div>
                        <div class="label"><i class="ri-global-line"></i> Web</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['users']; ?></div>
                        <div class="label">Benutzer</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['stores']; ?></div>
                        <div class="label">POS Geräte</div>
                    </div>
                </div>

                <!-- Send Push Form -->
                <div class="card">
                    <div class="card-title"><i class="ri-send-plane-line"></i> Push senden</div>

                    <form method="post" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Titel *</label>
                                <input type="text" name="title" required placeholder="z.B. Neue Aktion!">
                            </div>
                            <div class="form-group">
                                <label>Zielgruppe</label>
                                <div class="target-options">
                                    <label class="target-option active">
                                        <input type="radio" name="target" value="all" checked onchange="toggleTargetFields()">
                                        <i class="ri-group-line"></i> Alle
                                    </label>
                                    <label class="target-option">
                                        <input type="radio" name="target" value="store" onchange="toggleTargetFields()">
                                        <i class="ri-store-2-line"></i> Store-Kunden
                                    </label>
                                    <label class="target-option">
                                        <input type="radio" name="target" value="users" onchange="toggleTargetFields()">
                                        <i class="ri-user-line"></i> User IDs
                                    </label>
                                    <label class="target-option">
                                        <input type="radio" name="target" value="stores_pos" onchange="toggleTargetFields()">
                                        <i class="ri-tablet-line"></i> POS Geräte
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" id="store-select" style="display:none;">
                            <label>Store auswählen</label>
                            <select name="store_id">
                                <option value="">-- Store wählen --</option>
                                <?php foreach ($stores as $store): ?>
                                <option value="<?php echo $store->id; ?>"><?php echo esc_html($store->company_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" id="user-ids" style="display:none;">
                            <label>User IDs (kommagetrennt)</label>
                            <input type="text" name="user_ids" placeholder="z.B. 123, 456, 789">
                        </div>

                        <div class="form-group">
                            <label>Nachricht *</label>
                            <textarea name="body" required placeholder="Die Nachricht, die angezeigt wird..."></textarea>
                        </div>

                        <button type="submit" name="send_push" class="btn btn-primary" <?php echo !$fcm_enabled ? 'disabled' : ''; ?>>
                            <i class="ri-send-plane-fill"></i> Push senden
                        </button>
                    </form>
                </div>

                <!-- Test Push -->
                <div class="card">
                    <div class="card-title"><i class="ri-bug-line"></i> Test Push</div>

                    <form method="post" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label>FCM Token (direkt)</label>
                                <input type="text" name="test_token" placeholder="FCM Token eingeben...">
                            </div>
                            <div class="form-group">
                                <label>oder User ID</label>
                                <input type="number" name="test_user_id" placeholder="User ID">
                            </div>
                        </div>

                        <button type="submit" name="test_push" class="btn btn-secondary" <?php echo !$fcm_enabled ? 'disabled' : ''; ?>>
                            <i class="ri-play-line"></i> Test senden
                        </button>
                    </form>
                </div>

                <!-- Subscriptions Table -->
                <div class="card">
                    <div class="card-title"><i class="ri-list-check"></i> Aktive Abonnements (<?php echo count($subscriptions); ?>)</div>

                    <?php if (empty($subscriptions)): ?>
                    <p style="color: var(--text-muted); text-align: center; padding: 30px;">
                        Keine Push-Abonnements vorhanden
                    </p>
                    <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Platform</th>
                                    <th>Benutzer</th>
                                    <th>Store</th>
                                    <th>Gerät</th>
                                    <th>Token</th>
                                    <th>Aktualisiert</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscriptions as $sub): ?>
                                <tr>
                                    <td>
                                        <span class="platform-badge platform-<?php echo $sub->platform; ?>">
                                            <i class="ri-<?php echo $sub->platform === 'ios' ? 'apple-fill' : ($sub->platform === 'android' ? 'android-fill' : 'global-line'); ?>"></i>
                                            <?php echo ucfirst($sub->platform); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($sub->user_name): ?>
                                            <?php echo esc_html($sub->user_name); ?>
                                            <div style="font-size: 11px; color: var(--text-muted);"><?php echo esc_html($sub->user_email); ?></div>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">ID: <?php echo $sub->user_id; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $sub->store_name ? esc_html($sub->store_name) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html($sub->device_name ?: '-'); ?>
                                    </td>
                                    <td>
                                        <div class="token-preview" title="<?php echo esc_attr($sub->device_token); ?>">
                                            <?php echo esc_html(substr($sub->device_token, 0, 30) . '...'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo date('d.m.Y H:i', strtotime($sub->updated_at)); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Setup Guide -->
                <div class="card">
                    <div class="card-title"><i class="ri-book-open-line"></i> Setup Guide</div>

                    <div style="font-size: 14px; line-height: 1.6; color: var(--text-muted);">
                        <p><strong>1. Firebase Console:</strong> Firebase-Projekt erstellen und FCM aktivieren</p>
                        <p><strong>2. Server Key:</strong> In wp-config.php einfügen:</p>
                        <pre style="background: var(--bg); padding: 10px; border-radius: 6px; margin: 10px 0; overflow-x: auto;">define('PPV_FCM_SERVER_KEY', 'AAAA...Ihr_Server_Key');</pre>
                        <p><strong>3. iOS App:</strong> GoogleService-Info.plist bereits konfiguriert</p>
                        <p><strong>4. Testen:</strong> Push über diese Oberfläche an registrierte Geräte senden</p>
                    </div>
                </div>
            </div>

            <script>
                function toggleTargetFields() {
                    const target = document.querySelector('input[name="target"]:checked').value;
                    document.getElementById('store-select').style.display = target === 'store' ? 'block' : 'none';
                    document.getElementById('user-ids').style.display = target === 'users' ? 'block' : 'none';

                    // Update active state
                    document.querySelectorAll('.target-option').forEach(opt => {
                        opt.classList.toggle('active', opt.querySelector('input').checked);
                    });
                }
            </script>
        </body>
        </html>
        <?php
    }
}
