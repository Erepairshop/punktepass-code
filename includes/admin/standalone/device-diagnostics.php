<?php
if (!defined('ABSPATH')) exit;

class PPV_Standalone_DeviceDiagnostics {
    public static function render() {
        global $wpdb;
        $p = $wpdb->prefix;

        // Issue remote action
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action_user_id']) && !empty($_POST['action_fp'])) {
            $wpdb->insert("{$p}ppv_remote_actions", [
                'user_id' => intval($_POST['action_user_id']),
                'device_fingerprint' => sanitize_text_field($_POST['action_fp']),
                'action' => sanitize_text_field($_POST['action_type']),
                'payload' => sanitize_textarea_field($_POST['action_payload'] ?? ''),
                'status' => 'pending',
                'issued_by' => intval($_SESSION['ppv_admin_id'] ?? 0),
                'issued_at' => current_time('mysql'),
            ]);
        }

        $rows = $wpdb->get_results("
            SELECT d.*, u.email, s.name AS store_name,
                TIMESTAMPDIFF(MINUTE, d.last_heartbeat, NOW()) AS mins_ago
            FROM {$p}ppv_device_diagnostics d
            LEFT JOIN {$p}ppv_users u ON d.user_id = u.id
            LEFT JOIN {$p}ppv_stores s ON d.store_id = s.id
            ORDER BY d.last_heartbeat DESC LIMIT 200
        ");

        self::html($rows);
    }

    private static function html($rows) {
?><!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><title>Device diagnostics - PP Admin</title>
<style>
body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#1a1a2e;color:#e0e0e0;margin:0;padding:20px;}
h1{color:#4ec9b0;margin:0 0 8px;}
.bar{margin-bottom:16px;color:#aaa;font-size:14px;}
.bar a{color:#4ec9b0;margin-right:10px;}
table{width:100%;border-collapse:collapse;background:#16213e;border-radius:8px;overflow:hidden;font-size:12px;}
th,td{padding:6px 8px;text-align:left;border-bottom:1px solid #253252;vertical-align:top;}
th{background:#0f3460;color:#4ec9b0;font-size:11px;text-transform:uppercase;}
tr:hover{background:#1e2a4a;}
.online{color:#4ec9b0;}
.offline{color:#ff6b6b;}
.stale{color:#ffa94d;}
.perm-granted{color:#4ec9b0;}
.perm-denied{color:#ff6b6b;}
.perm-prompt{color:#ffa94d;}
.small{font-size:10px;color:#888;}
form.inline{display:inline;}
select,button,input{background:#0f3460;color:#e0e0e0;border:1px solid #253252;padding:3px 6px;border-radius:3px;font-size:11px;}
button{cursor:pointer;}
button:hover{background:#4ec9b0;color:#1a1a2e;}
</style></head><body>
<h1>Device diagnostics (<?php echo count($rows); ?> eszköz)</h1>
<div class="bar"><a href="/admin">← Vissza</a><a href="/admin/client-errors">🛑 Client errors</a></div>
<table><thead><tr>
<th>User / Store</th><th>Heartbeat</th><th>Ver</th><th>Screen</th><th>Lang</th><th>On</th>
<th>Kamera</th><th>Notif</th><th>Akku</th><th>Conn</th><th>URL</th><th>Remote action</th>
</tr></thead><tbody>
<?php foreach ($rows as $r):
    $stale = $r->mins_ago > 15;
    $offline = !$r->online;
?>
<tr>
<td><?php echo esc_html($r->email ?: 'user#' . $r->user_id); ?><div class="small"><?php echo esc_html($r->store_name ?: '-'); ?> · fp:<?php echo esc_html(substr($r->device_fingerprint, 0, 12)); ?></div></td>
<td class="<?php echo $stale ? 'stale' : 'online'; ?>"><?php echo $r->mins_ago; ?>p</td>
<td><?php echo esc_html($r->sw_version ?: '-'); ?></td>
<td><?php echo esc_html($r->screen); ?></td>
<td><?php echo esc_html($r->lang); ?></td>
<td class="<?php echo $offline ? 'offline' : 'online'; ?>"><?php echo $offline ? '🔴' : '🟢'; ?></td>
<td class="perm-<?php echo esc_attr($r->camera_permission); ?>"><?php echo esc_html($r->camera_permission ?: '?'); ?></td>
<td class="perm-<?php echo esc_attr($r->notification_permission); ?>"><?php echo esc_html($r->notification_permission ?: '?'); ?></td>
<td><?php echo $r->battery !== null ? $r->battery . '%' : '-'; ?></td>
<td><?php echo esc_html($r->connection ?: '-'); ?></td>
<td class="small" title="<?php echo esc_attr($r->last_url); ?>"><?php echo esc_html(substr($r->last_url, 0, 40)); ?></td>
<td>
<form class="inline" method="post">
  <input type="hidden" name="action_user_id" value="<?php echo $r->user_id; ?>">
  <input type="hidden" name="action_fp" value="<?php echo esc_attr($r->device_fingerprint); ?>">
  <select name="action_type">
    <option value="reload">Reload</option>
    <option value="clear_cache">Clear cache</option>
    <option value="force_logout">Force logout</option>
    <option value="alert">Alert üzenet</option>
  </select>
  <input type="text" name="action_payload" placeholder="üzenet" size="15">
  <button type="submit">→</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
<p class="small">Akciók: Reload = oldal újratöltés, Clear cache = localStorage ürítés, Force logout = kijelentkeztetés, Alert = JS alert. Eszköz 3 percenként lekérdezi a pending akciókat.</p>
</body></html><?php
    }
}
