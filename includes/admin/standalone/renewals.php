<?php
/**
 * PunktePass Standalone Admin - Megújítási kérelmek
 * Route: /admin/renewals
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Renewals {

    /**
     * Render renewals page
     */
    public static function render() {
        global $wpdb;

        // Handle mark as done
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_done'])) {
            self::handle_mark_done();
        }

        // Fetch handlers with renewal requests
        $requests = $wpdb->get_results("
            SELECT
                s.id,
                s.name,
                s.company_name,
                s.email,
                s.phone,
                s.renewal_phone,
                s.city,
                s.subscription_renewal_requested,
                s.subscription_status,
                s.trial_ends_at,
                s.subscription_expires_at,
                s.created_at
            FROM {$wpdb->prefix}ppv_stores s
            WHERE s.subscription_renewal_requested IS NOT NULL
            ORDER BY s.subscription_renewal_requested DESC
        ");

        $open_count = count($requests);

        self::render_html($requests, $open_count);
    }

    /**
     * Handle mark as done action
     */
    private static function handle_mark_done() {
        global $wpdb;

        $handler_id = intval($_POST['handler_id'] ?? 0);
        if ($handler_id > 0) {
            $wpdb->update(
                "{$wpdb->prefix}ppv_stores",
                [
                    'subscription_renewal_requested' => NULL,
                    'renewal_phone' => NULL
                ],
                ['id' => $handler_id],
                ['%s', '%s'],
                ['%d']
            );
            ppv_log("✅ [Standalone Admin] Renewal request marked as done for Handler #{$handler_id}");
        }

        wp_redirect('/admin/renewals?success=marked_done');
        exit;
    }

    /**
     * Render HTML
     */
    private static function render_html($requests, $open_count) {
        $success = isset($_GET['success']) ? $_GET['success'] : '';
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Megújítási kérelmek - PunktePass Admin</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #1a1a2e;
                    color: #e0e0e0;
                    min-height: 100vh;
                }
                .admin-header {
                    background: #16213e;
                    padding: 15px 20px;
                    border-bottom: 1px solid #0f3460;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .admin-header h1 {
                    font-size: 18px;
                    color: #00d9ff;
                }
                .admin-header .back-link {
                    color: #aaa;
                    text-decoration: none;
                    font-size: 14px;
                }
                .admin-header .back-link:hover { color: #00d9ff; }
                .container {
                    max-width: 1400px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .success-msg {
                    background: #0f5132;
                    color: #d1e7dd;
                    padding: 12px 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }
                .empty-state {
                    text-align: center;
                    padding: 60px 20px;
                    background: #16213e;
                    border-radius: 12px;
                    color: #888;
                }
                .empty-state i { font-size: 48px; margin-bottom: 15px; display: block; color: #00d9ff; }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    background: #16213e;
                    border-radius: 12px;
                    overflow: hidden;
                }
                th, td {
                    padding: 12px 15px;
                    text-align: left;
                    border-bottom: 1px solid #0f3460;
                }
                th {
                    background: #0f3460;
                    color: #00d9ff;
                    font-weight: 600;
                    font-size: 13px;
                }
                tr:hover { background: #1f2b4d; }
                .badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 11px;
                    font-weight: 600;
                }
                .badge-success { background: #0f5132; color: #d1e7dd; }
                .badge-info { background: #084298; color: #cfe2ff; }
                .badge-warning { background: #664d03; color: #fff3cd; }
                .badge-error { background: #842029; color: #f8d7da; }
                .btn {
                    display: inline-block;
                    padding: 6px 12px;
                    border-radius: 6px;
                    font-size: 12px;
                    font-weight: 600;
                    text-decoration: none;
                    cursor: pointer;
                    border: none;
                    transition: all 0.2s;
                }
                .btn-primary { background: #00d9ff; color: #000; }
                .btn-primary:hover { background: #00b8d9; }
                .btn-secondary { background: #374151; color: #fff; }
                .btn-secondary:hover { background: #4b5563; }
                a { color: #00d9ff; text-decoration: none; }
                a:hover { text-decoration: underline; }
                .phone-highlight {
                    color: #00d9ff;
                    font-weight: 600;
                }
            </style>
        </head>
        <body>
            <div class="admin-header">
                <h1>📧 Megújítási kérelmek (<?php echo $open_count; ?> nyitott)</h1>
                <a href="/admin" class="back-link"><i class="ri-arrow-left-line"></i> Vissza</a>
            </div>

            <div class="container">
                <?php if ($success === 'marked_done'): ?>
                    <div class="success-msg">✅ Kérelem készként jelölve!</div>
                <?php endif; ?>

                <?php if ($open_count === 0): ?>
                    <div class="empty-state">
                        <i class="ri-checkbox-circle-line"></i>
                        <h3>Nincs nyitott megújítási kérelem!</h3>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cégnév</th>
                                <th>E-mail</th>
                                <th>Telefon</th>
                                <th>Megújítási telefon</th>
                                <th>Város</th>
                                <th>Kérelem dátuma</th>
                                <th>Státusz</th>
                                <th>Műveletek</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <?php
                                $now = current_time('timestamp');
                                $trial_end = !empty($request->trial_ends_at) ? strtotime($request->trial_ends_at) : 0;
                                $sub_end = !empty($request->subscription_expires_at) ? strtotime($request->subscription_expires_at) : 0;

                                $trial_days_left = $trial_end > 0 ? max(0, ceil(($trial_end - $now) / 86400)) : 0;
                                $sub_days_left = $sub_end > 0 ? max(0, ceil(($sub_end - $now) / 86400)) : 0;

                                if ($request->subscription_status === 'active') {
                                    if ($sub_days_left > 0) {
                                        $status_text = "✅ Aktív ({$sub_days_left} nap)";
                                        $status_class = 'success';
                                    } else {
                                        $status_text = '❌ Előfizetés lejárt';
                                        $status_class = 'error';
                                    }
                                } else {
                                    if ($trial_days_left > 0) {
                                        $status_text = "📅 Próba ({$trial_days_left} nap)";
                                        $status_class = 'info';
                                    } else {
                                        $status_text = '❌ Próba lejárt';
                                        $status_class = 'error';
                                    }
                                }

                                $requested_time = date('Y-m-d H:i', strtotime($request->subscription_renewal_requested));
                                ?>
                                <tr>
                                    <td><?php echo intval($request->id); ?></td>
                                    <td><strong><?php echo esc_html($request->company_name ?: $request->name); ?></strong></td>
                                    <td><a href="mailto:<?php echo esc_attr($request->email); ?>"><?php echo esc_html($request->email); ?></a></td>
                                    <td>
                                        <?php if (!empty($request->phone)): ?>
                                            <a href="tel:<?php echo esc_attr($request->phone); ?>"><?php echo esc_html($request->phone); ?></a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($request->renewal_phone)): ?>
                                            <span class="phone-highlight">📞 <?php echo esc_html($request->renewal_phone); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($request->city); ?></td>
                                    <td><?php echo $requested_time; ?></td>
                                    <td><span class="badge badge-<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                    <td>
                                        <form method="post" style="display: inline-block;">
                                            <input type="hidden" name="mark_done" value="1">
                                            <input type="hidden" name="handler_id" value="<?php echo intval($request->id); ?>">
                                            <button type="submit" class="btn btn-primary" onclick="return confirm('Készként jelöli?');">✅ Kész</button>
                                        </form>
                                        <a href="mailto:<?php echo esc_attr($request->email); ?>?subject=Előfizetés%20hosszabbítás" class="btn btn-secondary">📧</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }
}
