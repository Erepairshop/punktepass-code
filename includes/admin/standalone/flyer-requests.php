<?php
/**
 * PunktePass Standalone Admin - Flyer Requests
 * Route: /admin/flyer-requests
 * Lists all printed-flyer requests submitted by handlers, supports status updates.
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_FlyerRequests {

    public static function render() {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_flyer_requests';

        // Self-heal: ensure table exists
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        if (!$exists && class_exists('PPV_Advertisers') && method_exists('PPV_Advertisers', 'create_flyer_requests_table')) {
            PPV_Advertisers::create_flyer_requests_table();
        }

        $message = '';
        $message_type = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['flyer_action'])) {
            $action = sanitize_text_field($_POST['flyer_action']);
            $id = intval($_POST['id'] ?? 0);
            $status_map = [
                'mark_sent'      => 'sent',
                'mark_cancelled' => 'cancelled',
                'mark_pending'   => 'pending',
            ];
            if ($id > 0 && isset($status_map[$action])) {
                $wpdb->update(
                    $table,
                    ['status' => $status_map[$action], 'updated_at' => current_time('mysql')],
                    ['id' => $id],
                    ['%s', '%s'],
                    ['%d']
                );
                $message = "Status aktualisiert (#{$id} → {$status_map[$action]})";
                $message_type = 'success';
            }
        }

        $filter_status = sanitize_text_field($_GET['status'] ?? '');
        $where = '1=1';
        $where_args = [];
        if (in_array($filter_status, ['pending', 'sent', 'cancelled'], true)) {
            $where = 'fr.status = %s';
            $where_args[] = $filter_status;
        }

        // advertiser_id column may not exist yet on legacy installs — guard the join
        $has_adv_col = (bool)$wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'advertiser_id'");
        $adv_select = $has_adv_col ? ', a.business_name AS adv_business_name, a.slug AS adv_slug' : '';
        $adv_join   = $has_adv_col ? "LEFT JOIN {$wpdb->prefix}ppv_advertisers a ON a.id = fr.advertiser_id" : '';

        $sql = "SELECT fr.*, s.company_name AS store_name {$adv_select}
                FROM {$table} fr
                LEFT JOIN {$wpdb->prefix}ppv_stores s ON s.id = fr.store_id
                {$adv_join}
                WHERE {$where}
                ORDER BY fr.created_at DESC
                LIMIT 500";
        $rows = $where_args ? $wpdb->get_results($wpdb->prepare($sql, $where_args)) : $wpdb->get_results($sql);

        $counts = [
            'pending'   => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status='pending'"),
            'sent'      => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status='sent'"),
            'cancelled' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status='cancelled'"),
        ];

        self::render_html($rows, $counts, $filter_status, $message, $message_type);
    }

    private static function render_html($rows, $counts, $filter_status, $message, $message_type) {
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Flyer kérések - PunktePass Admin</title>
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
                body { font-family: 'Inter', -apple-system, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding: 20px; }
                .container { max-width: 1300px; margin: 0 auto; }
                h1 { font-size: 24px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
                .back-link { color: var(--text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 20px; }
                .back-link:hover { color: var(--primary); }
                .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
                .alert-success { background: rgba(0,200,83,0.15); border: 1px solid var(--success); }
                .alert-error { background: rgba(255,82,82,0.15); border: 1px solid var(--danger); }
                .filter-tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
                .filter-tabs a { padding: 6px 14px; border-radius: 6px; background: var(--card); color: var(--text-muted); text-decoration: none; border: 1px solid var(--border); font-size: 13px; }
                .filter-tabs a.active { background: var(--primary); color: #000; border-color: var(--primary); font-weight: 600; }
                .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 0; overflow: hidden; }
                table { width: 100%; border-collapse: collapse; font-size: 13px; }
                thead tr { background: rgba(255,255,255,0.03); }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid var(--border); vertical-align: top; }
                th { font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600; }
                .status-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #fff; }
                .status-pending { background: var(--warning); }
                .status-sent { background: var(--success); }
                .status-cancelled { background: var(--danger); }
                .btn-mini { padding: 4px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; color: #fff; margin-right: 4px; }
                .btn-sent { background: var(--success); }
                .btn-cancel { background: var(--danger); }
                .btn-reset { background: #6b7280; }
                .empty { padding: 40px; text-align: center; color: var(--text-muted); }
            </style>
        </head>
        <body>
            <div class="container">
                <a href="/admin" class="back-link"><i class="ri-arrow-left-line"></i> Vissza a Dashboardra</a>
                <h1><i class="ri-printer-line"></i> Flyer kérések</h1>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                        <?php echo esc_html($message); ?>
                    </div>
                <?php endif; ?>

                <div class="filter-tabs">
                    <a href="/admin/flyer-requests" class="<?php echo $filter_status === '' ? 'active' : ''; ?>">Mind</a>
                    <a href="/admin/flyer-requests?status=pending" class="<?php echo $filter_status === 'pending' ? 'active' : ''; ?>">Függőben (<?php echo $counts['pending']; ?>)</a>
                    <a href="/admin/flyer-requests?status=sent" class="<?php echo $filter_status === 'sent' ? 'active' : ''; ?>">Elküldve (<?php echo $counts['sent']; ?>)</a>
                    <a href="/admin/flyer-requests?status=cancelled" class="<?php echo $filter_status === 'cancelled' ? 'active' : ''; ?>">Törölve (<?php echo $counts['cancelled']; ?>)</a>
                </div>

                <div class="card">
                    <?php if (empty($rows)): ?>
                        <div class="empty">Még nincs flyer kérés.</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Dátum</th>
                                    <th>Source</th>
                                    <th>Bolt / Cég</th>
                                    <th>Név</th>
                                    <th>Cím</th>
                                    <th>Db</th>
                                    <th>Nyelv</th>
                                    <th>Üzenet</th>
                                    <th>Státusz</th>
                                    <th>Művelet</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td style="white-space: nowrap; font-size: 11px; color: var(--text-muted);"><?php echo esc_html($r->created_at); ?></td>
                                    <td style="white-space: nowrap;">
                                        <?php
                                        $adv_id = isset($r->advertiser_id) ? intval($r->advertiser_id) : 0;
                                        if ($adv_id > 0) {
                                            $adv_name = !empty($r->adv_business_name) ? $r->adv_business_name : ('Advertiser #' . $adv_id);
                                            echo '<span style="background:rgba(0,191,255,0.15); color:var(--primary); padding:2px 8px; border-radius:10px; font-size:10px; font-weight:600;">ADVERTISER</span>';
                                            echo '<br><small style="color:var(--text-muted);">' . esc_html($adv_name) . '</small>';
                                            if (!empty($r->adv_slug)) {
                                                echo '<br><small style="color:var(--text-muted); font-size:10px;">/' . esc_html($r->adv_slug) . '</small>';
                                            }
                                        } elseif (intval($r->store_id) > 0) {
                                            echo '<span style="background:rgba(255,179,0,0.15); color:var(--warning); padding:2px 8px; border-radius:10px; font-size:10px; font-weight:600;">HANDLER</span>';
                                            if (!empty($r->store_name)) {
                                                echo '<br><small style="color:var(--text-muted);">' . esc_html($r->store_name) . '</small>';
                                            }
                                            echo '<br><small style="color:var(--text-muted); font-size:10px;">store #' . intval($r->store_id) . '</small>';
                                        } else {
                                            echo '<span style="color:var(--text-muted); font-size:11px;">—</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($r->business_name); ?></strong>
                                    </td>
                                    <td><?php echo esc_html($r->name); ?></td>
                                    <td>
                                        <?php echo esc_html($r->address); ?><br>
                                        <?php echo esc_html($r->postcode . ' ' . $r->city); ?><br>
                                        <strong><?php echo esc_html($r->country); ?></strong>
                                    </td>
                                    <td style="text-align: center; font-weight: 600;"><?php echo intval($r->quantity); ?></td>
                                    <td style="text-transform: uppercase;"><?php echo esc_html($r->language); ?></td>
                                    <td style="max-width: 220px; font-size: 12px; color: var(--text-muted);"><?php echo nl2br(esc_html($r->message ?: '—')); ?></td>
                                    <td><span class="status-badge status-<?php echo esc_attr($r->status); ?>"><?php echo esc_html($r->status); ?></span></td>
                                    <td style="white-space: nowrap;">
                                        <?php if ($r->status !== 'sent'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="id" value="<?php echo intval($r->id); ?>">
                                                <input type="hidden" name="flyer_action" value="mark_sent">
                                                <button type="submit" class="btn-mini btn-sent">✓ Elküldve</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($r->status === 'pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="id" value="<?php echo intval($r->id); ?>">
                                                <input type="hidden" name="flyer_action" value="mark_cancelled">
                                                <button type="submit" class="btn-mini btn-cancel">✕ Törlés</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($r->status !== 'pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="id" value="<?php echo intval($r->id); ?>">
                                                <input type="hidden" name="flyer_action" value="mark_pending">
                                                <button type="submit" class="btn-mini btn-reset">↺ Visszaállít</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
