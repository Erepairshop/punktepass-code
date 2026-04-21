<?php
if (!defined('ABSPATH')) exit;

class PPV_Standalone_ClientErrors {
    public static function render() {
        global $wpdb;
        $p = $wpdb->prefix;

        // Handle ack/delete
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ack_id'])) {
            $wpdb->update("{$p}ppv_client_errors", ['acknowledged' => 1], ['id' => intval($_POST['ack_id'])]);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_id'])) {
            $wpdb->delete("{$p}ppv_client_errors", ['id' => intval($_POST['delete_id'])]);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['purge_ack'])) {
            $wpdb->query("DELETE FROM {$p}ppv_client_errors WHERE acknowledged=1");
        }

        $show_ack = isset($_GET['all']);
        $where = $show_ack ? '1=1' : 'acknowledged=0';

        $rows = $wpdb->get_results("
            SELECT e.*, s.name AS store_name
            FROM {$p}ppv_client_errors e
            LEFT JOIN {$p}ppv_stores s ON e.store_id = s.id
            WHERE $where
            ORDER BY e.id DESC LIMIT 300
        ");
        $count_new = $wpdb->get_var("SELECT COUNT(*) FROM {$p}ppv_client_errors WHERE acknowledged=0");
        $count_total = $wpdb->get_var("SELECT COUNT(*) FROM {$p}ppv_client_errors");

        self::html($rows, $count_new, $count_total, $show_ack);
    }

    private static function html($rows, $count_new, $count_total, $show_ack) {
?><!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><title>Client Errors - PP Admin</title>
<style>
body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#1a1a2e;color:#e0e0e0;margin:0;padding:20px;}
h1{color:#4ec9b0;margin:0 0 8px;}
.bar{margin-bottom:16px;color:#aaa;font-size:14px;}
.bar a,.bar button{color:#4ec9b0;margin-right:10px;background:none;border:1px solid #4ec9b0;padding:6px 12px;border-radius:4px;cursor:pointer;text-decoration:none;font-size:13px;}
.bar button:hover{background:#4ec9b0;color:#1a1a2e;}
table{width:100%;border-collapse:collapse;background:#16213e;border-radius:8px;overflow:hidden;font-size:13px;}
th,td{padding:8px 10px;text-align:left;border-bottom:1px solid #253252;vertical-align:top;}
th{background:#0f3460;color:#4ec9b0;font-size:12px;text-transform:uppercase;}
tr:hover{background:#1e2a4a;}
.msg{max-width:400px;word-break:break-word;}
.stack{color:#888;font-family:monospace;font-size:11px;max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.stack:hover{white-space:normal;}
.ack{opacity:0.5;}
.sev-error{color:#ff6b6b;}
.sev-warn{color:#ffa94d;}
a{color:#4ec9b0;}
form.inline{display:inline;}
.small{font-size:11px;color:#888;}
</style></head><body>
<h1>Client errors (<?php echo $count_new; ?> új / <?php echo $count_total; ?> összes)</h1>
<div class="bar">
  <a href="/admin">← Vissza</a>
  <?php if (!$show_ack): ?><a href="?all=1">Mind mutatása</a><?php else: ?><a href="/admin/client-errors">Csak új</a><?php endif; ?>
  <form class="inline" method="post" onsubmit="return confirm('Törli az összes acknowledged hibát?');">
    <button name="purge_ack" value="1">🗑 Purge acked</button>
  </form>
</div>
<table><thead><tr><th>ID</th><th>Mikor</th><th>Sev</th><th>Email / Store</th><th>URL</th><th>Üzenet</th><th>Stack</th><th>UA</th><th>Action</th></tr></thead><tbody>
<?php foreach ($rows as $r):
    $ctx = json_decode($r->context, true);
?>
<tr class="<?php echo $r->acknowledged ? 'ack' : ''; ?>">
<td>#<?php echo $r->id; ?></td>
<td><?php echo date('m-d H:i', strtotime($r->created_at)); ?></td>
<td class="sev-<?php echo esc_attr($r->severity); ?>"><?php echo esc_html($r->severity); ?></td>
<td><?php echo esc_html($r->email ?: '-'); ?><div class="small"><?php echo esc_html($r->store_name ?: 'store #' . $r->store_id); ?></div></td>
<td><span class="small" title="<?php echo esc_attr($r->url); ?>"><?php echo esc_html(substr($r->url, 0, 50)); ?></span></td>
<td class="msg"><?php echo esc_html($r->message); ?></td>
<td class="stack" title="<?php echo esc_attr($r->stack); ?>"><?php echo esc_html(substr($r->stack, 0, 100)); ?></td>
<td class="small"><?php echo esc_html(substr($r->user_agent, 0, 40)); ?></td>
<td>
  <?php if (!$r->acknowledged): ?>
  <form class="inline" method="post"><button name="ack_id" value="<?php echo $r->id; ?>">✓</button></form>
  <?php endif; ?>
  <form class="inline" method="post" onsubmit="return confirm('Törli?');"><button name="delete_id" value="<?php echo $r->id; ?>">✗</button></form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
</body></html><?php
    }
}
