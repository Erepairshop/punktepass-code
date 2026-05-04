<?php
/**
 * PunktePass Standalone Admin - Advertisers (Werber)
 * Route: /admin/advertisers
 * Lista a hirdetőkről, trial hosszabbítás, aktiválás / deaktiválás, featured toggle.
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Advertisers {

    public static function render() {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_advertisers';

        // Self-heal
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        if (!$exists && class_exists('PPV_Advertisers') && method_exists('PPV_Advertisers', 'run_migrations')) {
            PPV_Advertisers::run_migrations();
        }

        $message = '';
        $message_type = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['adv_action'])) {
            $action = sanitize_text_field($_POST['adv_action']);
            $id = intval($_POST['id'] ?? 0);

            if ($id > 0) {
                if ($action === 'extend_30') {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$table} SET subscription_until = GREATEST(IFNULL(subscription_until, CURDATE()), CURDATE()) + INTERVAL 30 DAY WHERE id = %d",
                        $id
                    ));
                    $message = "Trial +30 nap (#{$id})";
                    $message_type = 'success';
                } elseif ($action === 'extend_90') {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$table} SET subscription_until = GREATEST(IFNULL(subscription_until, CURDATE()), CURDATE()) + INTERVAL 90 DAY WHERE id = %d",
                        $id
                    ));
                    $message = "Trial +90 nap (#{$id})";
                    $message_type = 'success';
                } elseif ($action === 'extend_365') {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$table} SET subscription_until = GREATEST(IFNULL(subscription_until, CURDATE()), CURDATE()) + INTERVAL 365 DAY WHERE id = %d",
                        $id
                    ));
                    $message = "Előfizetés +1 év (#{$id})";
                    $message_type = 'success';
                } elseif ($action === 'activate') {
                    $wpdb->update($table, ['subscription_status' => 'active'], ['id' => $id], ['%s'], ['%d']);
                    $message = "Aktiválva (#{$id})";
                    $message_type = 'success';
                } elseif ($action === 'set_trial') {
                    $wpdb->update($table, ['subscription_status' => 'trial'], ['id' => $id], ['%s'], ['%d']);
                    $message = "Trial státuszba állítva (#{$id})";
                    $message_type = 'success';
                } elseif ($action === 'cancel') {
                    $wpdb->update($table, ['subscription_status' => 'cancelled'], ['id' => $id], ['%s'], ['%d']);
                    $message = "Lemondva (#{$id})";
                    $message_type = 'info';
                } elseif ($action === 'enable') {
                    $wpdb->update($table, ['is_active' => 1], ['id' => $id], ['%d'], ['%d']);
                    $message = "Engedélyezve (#{$id})";
                    $message_type = 'success';
                } elseif ($action === 'disable') {
                    $wpdb->update($table, ['is_active' => 0], ['id' => $id], ['%d'], ['%d']);
                    $message = "Letiltva (#{$id})";
                    $message_type = 'info';
                } elseif ($action === 'feature_on') {
                    $wpdb->update($table, ['featured' => 1], ['id' => $id], ['%d'], ['%d']);
                    $message = "Featured ON (#{$id})";
                    $message_type = 'success';
                } elseif ($action === 'feature_off') {
                    $wpdb->update($table, ['featured' => 0], ['id' => $id], ['%d'], ['%d']);
                    $message = "Featured OFF (#{$id})";
                    $message_type = 'info';
                }
            }
        }

        // Filters
        $filter_status  = sanitize_text_field($_GET['status'] ?? '');
        $filter_country = sanitize_text_field($_GET['country'] ?? '');
        $filter_q       = sanitize_text_field($_GET['q'] ?? '');

        $where = '1=1';
        $args = [];
        if (in_array($filter_status, ['trial', 'active', 'expired', 'cancelled'], true)) {
            $where .= ' AND subscription_status = %s';
            $args[] = $filter_status;
        }
        if ($filter_country !== '') {
            $where .= ' AND country = %s';
            $args[] = $filter_country;
        }
        if ($filter_q !== '') {
            $where .= ' AND (business_name LIKE %s OR owner_email LIKE %s OR slug LIKE %s OR city LIKE %s)';
            $like = '%' . $wpdb->esc_like($filter_q) . '%';
            $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like;
        }

        // Stats
        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN subscription_status = 'trial'     THEN 1 ELSE 0 END) AS trial_n,
                SUM(CASE WHEN subscription_status = 'active'    THEN 1 ELSE 0 END) AS active_n,
                SUM(CASE WHEN subscription_status = 'expired'   THEN 1 ELSE 0 END) AS expired_n,
                SUM(CASE WHEN subscription_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_n,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS disabled_n
             FROM {$table}",
            ARRAY_A
        );

        // Country list for filter
        $countries = $wpdb->get_col("SELECT DISTINCT country FROM {$table} WHERE country IS NOT NULL AND country <> '' ORDER BY country");

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT 500";
        $rows = empty($args) ? $wpdb->get_results($sql) : $wpdb->get_results($wpdb->prepare($sql, $args));

        // Render
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Hirdetők (Werber) — PunktePass Admin</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f1419; color: #e0e0e0; min-height: 100vh; }
                .admin-header { background: #16213e; padding: 14px 20px; border-bottom: 1px solid #0f3460; display: flex; justify-content: space-between; align-items: center; }
                .admin-header h1 { font-size: 17px; color: #00d9ff; font-weight: 700; }
                .admin-header a { color: #93c5fd; text-decoration: none; font-size: 13px; }
                .admin-content { padding: 20px; max-width: 1500px; margin: 0 auto; }
            </style>
        </head>
        <body>
            <header class="admin-header">
                <h1><i class="ri-megaphone-line"></i> Hirdetők (Werber)</h1>
                <div><a href="/admin"><i class="ri-arrow-left-line"></i> Vissza a Dashboardra</a></div>
            </header>
        <?php
        <style>
            .adv-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin: 16px 0; }
            .adv-stat { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 12px 14px; }
            .adv-stat .num { font-size: 22px; font-weight: 700; color: #fff; }
            .adv-stat .lbl { font-size: 11px; color: rgba(255,255,255,0.55); text-transform: uppercase; letter-spacing: 0.06em; }
            .adv-filters { display: flex; gap: 8px; flex-wrap: wrap; margin: 12px 0 16px; }
            .adv-filters input, .adv-filters select { background: #1a1f2e; border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 6px 10px; border-radius: 6px; font-size: 13px; }
            .adv-table { width: 100%; border-collapse: collapse; font-size: 13px; }
            .adv-table th, .adv-table td { padding: 8px 10px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.06); vertical-align: top; }
            .adv-table th { background: rgba(255,255,255,0.04); font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: rgba(255,255,255,0.55); }
            .adv-table tr:hover td { background: rgba(255,255,255,0.02); }
            .adv-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
            .adv-badge.trial    { background: rgba(99,102,241,0.18); color: #a5b4fc; }
            .adv-badge.active   { background: rgba(34,197,94,0.18);  color: #86efac; }
            .adv-badge.expired  { background: rgba(245,158,11,0.18); color: #fcd34d; }
            .adv-badge.cancelled{ background: rgba(239,68,68,0.18);  color: #fca5a5; }
            .adv-badge.disabled { background: rgba(156,163,175,0.18); color: #d1d5db; }
            .adv-badge.featured { background: rgba(244,114,182,0.18); color: #f9a8d4; }
            .adv-actions { display: flex; gap: 4px; flex-wrap: wrap; }
            .adv-actions form { display: inline; }
            .adv-actions button { background: rgba(99,102,241,0.18); color: #c7d2fe; border: 1px solid rgba(99,102,241,0.4); padding: 4px 8px; border-radius: 4px; font-size: 11px; cursor: pointer; }
            .adv-actions button.danger  { background: rgba(239,68,68,0.18); color: #fca5a5; border-color: rgba(239,68,68,0.4); }
            .adv-actions button.success { background: rgba(34,197,94,0.18); color: #86efac; border-color: rgba(34,197,94,0.4); }
            .adv-actions button:hover { filter: brightness(1.2); }
            .adv-msg { padding: 10px 14px; border-radius: 8px; margin: 10px 0; }
            .adv-msg.success { background: rgba(34,197,94,0.18); color: #86efac; }
            .adv-msg.info    { background: rgba(99,102,241,0.18); color: #c7d2fe; }
            .adv-msg.error   { background: rgba(239,68,68,0.18); color: #fca5a5; }
            .adv-meta { font-size: 11px; color: rgba(255,255,255,0.55); }
            .adv-link  { color: #93c5fd; text-decoration: none; }
            .adv-link:hover { text-decoration: underline; }
        </style>

        <div class="admin-content">
            <p style="color: rgba(255,255,255,0.55); margin: 0 0 16px;">
                <code>/business/register</code> regisztráltak. Trial hosszabbítás, státusz váltás, featured kapcsoló.
            </p>

            <?php if ($message): ?>
                <div class="adv-msg <?php echo esc_attr($message_type); ?>"><?php echo esc_html($message); ?></div>
            <?php endif; ?>

            <div class="adv-stats">
                <div class="adv-stat"><div class="num"><?php echo intval($stats['total']); ?></div><div class="lbl">Összes</div></div>
                <div class="adv-stat"><div class="num"><?php echo intval($stats['trial_n']); ?></div><div class="lbl">Trial</div></div>
                <div class="adv-stat"><div class="num"><?php echo intval($stats['active_n']); ?></div><div class="lbl">Aktív (fizetős)</div></div>
                <div class="adv-stat"><div class="num"><?php echo intval($stats['expired_n']); ?></div><div class="lbl">Lejárt</div></div>
                <div class="adv-stat"><div class="num"><?php echo intval($stats['cancelled_n']); ?></div><div class="lbl">Lemondott</div></div>
                <div class="adv-stat"><div class="num"><?php echo intval($stats['disabled_n']); ?></div><div class="lbl">Letiltott</div></div>
            </div>

            <form method="get" action="/admin/advertisers" class="adv-filters">
                <input type="text" name="q" placeholder="Keresés (név / email / slug / város)" value="<?php echo esc_attr($filter_q); ?>" style="min-width: 240px;">
                <select name="status">
                    <option value="">— Minden státusz —</option>
                    <?php foreach (['trial','active','expired','cancelled'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php selected($filter_status, $s); ?>><?php echo ucfirst($s); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="country">
                    <option value="">— Minden ország —</option>
                    <?php foreach ($countries as $c): if (!$c) continue; ?>
                        <option value="<?php echo esc_attr($c); ?>" <?php selected($filter_country, $c); ?>><?php echo esc_html($c); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" style="background:#0066cc;color:#fff;border:0;padding:6px 14px;border-radius:6px;cursor:pointer;">Szűrés</button>
                <a href="/admin/advertisers" style="color: rgba(255,255,255,0.55); padding: 6px 10px; align-self: center; font-size: 12px;">Reset</a>
            </form>

            <table class="adv-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Bolt</th>
                        <th>Email / Tel</th>
                        <th>Hely</th>
                        <th>Kategória</th>
                        <th>Státusz</th>
                        <th>Lejárat</th>
                        <th>Push</th>
                        <th>Reg.</th>
                        <th style="min-width: 240px;">Műveletek</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="10" style="text-align:center; padding: 30px; color: rgba(255,255,255,0.45);">Nincs találat.</td></tr>
                    <?php else: foreach ($rows as $r):
                        $today = date('Y-m-d');
                        $is_expired = $r->subscription_until && $r->subscription_until < $today;
                        $days_left = $r->subscription_until ? (int)((strtotime($r->subscription_until) - strtotime($today)) / 86400) : null;
                    ?>
                        <tr>
                            <td><?php echo intval($r->id); ?></td>
                            <td>
                                <a class="adv-link" href="/business/<?php echo esc_attr($r->slug); ?>" target="_blank">
                                    <?php echo esc_html($r->business_name ?: $r->slug); ?>
                                </a>
                                <?php if ($r->featured): ?> <span class="adv-badge featured">★</span><?php endif; ?>
                                <?php if (!$r->is_active): ?> <span class="adv-badge disabled">letiltva</span><?php endif; ?>
                                <div class="adv-meta">slug: <?php echo esc_html($r->slug); ?></div>
                            </td>
                            <td>
                                <a class="adv-link" href="mailto:<?php echo esc_attr($r->owner_email); ?>"><?php echo esc_html($r->owner_email); ?></a>
                                <?php if ($r->phone): ?><div class="adv-meta">📞 <?php echo esc_html($r->phone); ?></div><?php endif; ?>
                                <?php if ($r->whatsapp): ?><div class="adv-meta">📱 <a class="adv-link" href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/', '', $r->whatsapp)); ?>" target="_blank"><?php echo esc_html($r->whatsapp); ?></a></div><?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html(trim(($r->city ? $r->city : '') . ($r->country ? ' / ' . $r->country : ''))); ?>
                                <?php if ($r->postcode): ?><div class="adv-meta"><?php echo esc_html($r->postcode); ?></div><?php endif; ?>
                            </td>
                            <td><?php echo esc_html($r->category ?: '—'); ?></td>
                            <td>
                                <?php $st = $is_expired ? 'expired' : $r->subscription_status; ?>
                                <span class="adv-badge <?php echo esc_attr($st); ?>"><?php echo esc_html($st); ?></span>
                            </td>
                            <td>
                                <?php if ($r->subscription_until): ?>
                                    <?php echo esc_html($r->subscription_until); ?>
                                    <div class="adv-meta"><?php echo $days_left >= 0 ? "+{$days_left} nap" : "{$days_left} nap"; ?></div>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td><?php echo intval($r->push_used_this_month); ?> / hó</td>
                            <td class="adv-meta"><?php echo esc_html(date('Y-m-d', strtotime($r->created_at))); ?></td>
                            <td class="adv-actions">
                                <form method="post"><input type="hidden" name="id" value="<?php echo intval($r->id); ?>"><button type="submit" name="adv_action" value="extend_30" title="+30 nap">+30</button></form>
                                <form method="post"><input type="hidden" name="id" value="<?php echo intval($r->id); ?>"><button type="submit" name="adv_action" value="extend_90" title="+90 nap">+90</button></form>
                                <form method="post"><input type="hidden" name="id" value="<?php echo intval($r->id); ?>"><button type="submit" name="adv_action" value="extend_365" title="+1 év">+1év</button></form>
                                <?php if ($r->subscription_status !== 'active'): ?>
                                    <form method="post"><input type="hidden" name="id" value="<?php echo intval($r->id); ?>"><button class="success" type="submit" name="adv_action" value="activate" title="Aktivál">✓ aktív</button></form>
                                <?php else: ?>
                                    <form method="post"><input type="hidden" name="id" value="<?php echo intval($r->id); ?>"><button type="submit" name="adv_action" value="set_trial" title="Trial státusz">trial</button></form>
                                <?php endif; ?>
                                <?php if ($r->subscription_status !== 'cancelled'): ?>
                                    <form method="post" onsubmit="return confirm('Biztos lemondás?');"><input type="hidden" name="id" value="<?php echo intval($r->id); ?>"><button class="danger" type="submit" name="adv_action" value="cancel" title="Lemond">✗</button></form>
                                <?php endif; ?>
                                <?php if ($r->is_active): ?>
                                    <form method="post" onsubmit="return confirm('Letilt?');"><input type="hidden" name="id" value="<?php echo intval($r->id); ?>"><button class="danger" type="submit" name="adv_action" value="disable" title="Letilt">⊘</button></form>
                                <?php else: ?>
                                    <form method="post"><input type="hidden" name="id" value="<?php echo intval($r->id); ?>"><button class="success" type="submit" name="adv_action" value="enable" title="Engedélyez">●</button></form>
                                <?php endif; ?>
                                <?php if ($r->featured): ?>
                                    <form method="post"><input type="hidden" name="id" value="<?php echo intval($r->id); ?>"><button type="submit" name="adv_action" value="feature_off" title="Unfeature">★</button></form>
                                <?php else: ?>
                                    <form method="post"><input type="hidden" name="id" value="<?php echo intval($r->id); ?>"><button type="submit" name="adv_action" value="feature_on" title="Feature">☆</button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            <p style="font-size: 11px; color: rgba(255,255,255,0.45); margin-top: 12px;">
                Maximum 500 sor jelenik meg. Pontosabb listához használd a szűrőket.
            </p>
        </div>
        </body>
        </html>
        <?php
    }
}
