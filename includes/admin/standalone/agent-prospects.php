<?php
/**
 * PunktePass Admin — Agent Prospects Overview
 * Shows all sales agent visits and prospect pipeline
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Agent_Prospects {

    public static function render() {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_sales_markers';
        $users_table = $wpdb->prefix . 'ppv_users';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            echo '<div class="admin-card"><p>Nincs még agent adat. Az ágens még nem használta a rendszert.</p></div>';
            return;
        }

        // Handle user_type update to 'agent'
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_agent_role'])) {
            $uid = intval($_POST['user_id'] ?? 0);
            if ($uid) {
                $wpdb->update($users_table, ['user_type' => 'agent'], ['id' => $uid], ['%s'], ['%d']);
                echo '<div style="background:#dcfce7;padding:10px 16px;border-radius:8px;margin-bottom:16px;color:#166534;">User #' . $uid . ' → agent role beállítva!</div>';
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_agent_role'])) {
            $uid = intval($_POST['user_id'] ?? 0);
            if ($uid) {
                $wpdb->update($users_table, ['user_type' => 'user'], ['id' => $uid], ['%s'], ['%d']);
                echo '<div style="background:#fef3c7;padding:10px 16px;border-radius:8px;margin-bottom:16px;color:#92400e;">User #' . $uid . ' → agent role eltávolítva</div>';
            }
        }

        // Filters
        $filter_status = sanitize_text_field($_GET['status'] ?? '');
        $filter_result = sanitize_text_field($_GET['result'] ?? '');
        $filter_agent  = intval($_GET['agent_id'] ?? 0);

        $where = "WHERE 1=1";
        $args = [];

        if ($filter_status) { $where .= " AND m.status = %s"; $args[] = $filter_status; }
        if ($filter_result) { $where .= " AND m.result = %s"; $args[] = $filter_result; }
        if ($filter_agent)  { $where .= " AND m.agent_id = %d"; $args[] = $filter_agent; }

        $query = "SELECT m.*, u.email as agent_email, u.display_name as agent_name
                  FROM $table m
                  LEFT JOIN $users_table u ON u.id = m.agent_id
                  $where ORDER BY m.visited_at DESC LIMIT 500";

        if (!empty($args)) {
            $query = $wpdb->prepare($query, ...$args);
        }

        $prospects = $wpdb->get_results($query);

        // Stats
        $total = count($prospects);
        $stats = ['visited'=>0, 'contacted'=>0, 'interested'=>0, 'customer'=>0, 'not_interested'=>0];
        $results = ['pending'=>0, 'trial'=>0, 'converted'=>0, 'lost'=>0];
        foreach ($prospects as $p) {
            if (isset($stats[$p->status])) $stats[$p->status]++;
            if (!empty($p->result) && isset($results[$p->result])) $results[$p->result]++;
        }

        // Get agents list
        $agents = $wpdb->get_results("SELECT id, email, display_name FROM $users_table WHERE user_type = 'agent' ORDER BY email");

        // Get potential agent users (for role assignment) — search or last 5
        $agent_search = sanitize_text_field($_GET['agent_search'] ?? '');
        if ($agent_search) {
            $potential_agents = $wpdb->get_results($wpdb->prepare(
                "SELECT id, email, display_name, user_type FROM $users_table WHERE email LIKE %s AND user_type != 'agent' ORDER BY email LIMIT 20",
                '%' . $wpdb->esc_like($agent_search) . '%'
            ));
        } else {
            $potential_agents = $wpdb->get_results("SELECT id, email, display_name, user_type FROM $users_table WHERE user_type != 'agent' ORDER BY id DESC LIMIT 5");
        }

        ?>
        <style>
            .agent-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:10px; margin-bottom:20px; }
            .agent-stat { background:#fff; padding:14px; border-radius:10px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,.06); }
            .agent-stat .num { font-size:24px; font-weight:800; }
            .agent-stat .lbl { font-size:12px; color:#64748b; }
            .agent-filters { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
            .agent-filters select { padding:8px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; }
            .agent-table { width:100%; border-collapse:collapse; font-size:13px; }
            .agent-table th { background:#f1f5f9; padding:10px 12px; text-align:left; font-weight:600; position:sticky; top:0; }
            .agent-table td { padding:10px 12px; border-bottom:1px solid #f1f5f9; }
            .agent-table tr:hover { background:#f8fafc; }
            .badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; }
            .badge-visited { background:#f1f5f9; color:#64748b; }
            .badge-contacted { background:#dbeafe; color:#2563eb; }
            .badge-interested { background:#fef3c7; color:#d97706; }
            .badge-customer { background:#dcfce7; color:#16a34a; }
            .badge-not_interested { background:#fee2e2; color:#dc2626; }
            .badge-trial { background:#fef3c7; color:#d97706; }
            .badge-converted { background:#dcfce7; color:#16a34a; }
            .badge-lost { background:#fee2e2; color:#dc2626; }
            .badge-pending { background:#f1f5f9; color:#64748b; }
            .section-title { font-size:16px; font-weight:700; margin:24px 0 12px; padding-bottom:8px; border-bottom:2px solid #e2e8f0; }
            .agent-role-form { display:inline-flex; align-items:center; gap:6px; }
            .agent-role-form button { padding:4px 10px; border:none; border-radius:6px; font-size:12px; cursor:pointer; }
            .btn-set-agent { background:#22c55e; color:#fff; }
            .btn-remove-agent { background:#ef4444; color:#fff; }
            .table-scroll { overflow-x:auto; max-height:600px; overflow-y:auto; }
        </style>

        <h2><i class="ri-user-location-line"></i> Agent Vizite & Prospect Pipeline</h2>

        <!-- Stats -->
        <div class="agent-stats">
            <div class="agent-stat"><div class="num"><?php echo $total; ?></div><div class="lbl">Összes</div></div>
            <div class="agent-stat"><div class="num" style="color:#f59e0b"><?php echo $stats['interested']; ?></div><div class="lbl">Érdeklődő</div></div>
            <div class="agent-stat"><div class="num" style="color:#22c55e"><?php echo $stats['customer']; ?></div><div class="lbl">Ügyfél</div></div>
            <div class="agent-stat"><div class="num" style="color:#3b82f6"><?php echo $results['trial']; ?></div><div class="lbl">Trial</div></div>
            <div class="agent-stat"><div class="num" style="color:#16a34a"><?php echo $results['converted']; ?></div><div class="lbl">Converted</div></div>
            <div class="agent-stat"><div class="num" style="color:#ef4444"><?php echo $results['lost']; ?></div><div class="lbl">Lost</div></div>
        </div>

        <!-- Filters -->
        <form class="agent-filters" method="get" action="/admin/agent-prospects">
            <select name="status">
                <option value="">Összes státusz</option>
                <option value="visited" <?php selected($filter_status, 'visited'); ?>>Vizitat</option>
                <option value="contacted" <?php selected($filter_status, 'contacted'); ?>>Contactat</option>
                <option value="interested" <?php selected($filter_status, 'interested'); ?>>Interesat</option>
                <option value="customer" <?php selected($filter_status, 'customer'); ?>>Client</option>
                <option value="not_interested" <?php selected($filter_status, 'not_interested'); ?>>Neinteresat</option>
            </select>
            <select name="result">
                <option value="">Összes eredmény</option>
                <option value="pending" <?php selected($filter_result, 'pending'); ?>>Pending</option>
                <option value="trial" <?php selected($filter_result, 'trial'); ?>>Trial</option>
                <option value="converted" <?php selected($filter_result, 'converted'); ?>>Converted</option>
                <option value="lost" <?php selected($filter_result, 'lost'); ?>>Lost</option>
            </select>
            <select name="agent_id">
                <option value="">Összes ágens</option>
                <?php foreach ($agents as $a): ?>
                    <option value="<?php echo $a->id; ?>" <?php selected($filter_agent, $a->id); ?>><?php echo esc_html($a->display_name ?: $a->email); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" style="padding:8px 16px;background:#1e293b;color:#fff;border:none;border-radius:8px;cursor:pointer;">Szűrés</button>
            <a href="/admin/agent-prospects" style="padding:8px 16px;text-decoration:none;color:#64748b;">Reset</a>
        </form>

        <!-- Prospects Table -->
        <div class="table-scroll">
        <table class="agent-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Üzlet</th>
                    <th>Város</th>
                    <th>Státusz</th>
                    <th>Eredmény</th>
                    <th>Ágens</th>
                    <th>Kontakt</th>
                    <th>Készülék</th>
                    <th>Trial</th>
                    <th>Follow-up</th>
                    <th>Vizit</th>
                    <th>Megjegyzés</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($prospects)): ?>
                    <tr><td colspan="12" style="text-align:center;padding:40px;color:#94a3b8;">Nincs adat</td></tr>
                <?php endif; ?>
                <?php foreach ($prospects as $i => $p): ?>
                <tr>
                    <td><?php echo $p->id; ?></td>
                    <td><strong><?php echo esc_html($p->business_name); ?></strong><br><small style="color:#94a3b8"><?php echo esc_html($p->address); ?></small></td>
                    <td><?php echo esc_html($p->city); ?></td>
                    <td><span class="badge badge-<?php echo esc_attr($p->status); ?>"><?php echo esc_html($p->status); ?></span></td>
                    <td><?php if ($p->result): ?><span class="badge badge-<?php echo esc_attr($p->result); ?>"><?php echo esc_html($p->result); ?></span><?php endif; ?></td>
                    <td><?php echo esc_html($p->agent_name ?: $p->agent_email ?: '-'); ?></td>
                    <td>
                        <?php echo esc_html($p->contact_name); ?>
                        <?php if ($p->contact_phone): ?><br><small><a href="tel:<?php echo esc_attr($p->contact_phone); ?>"><?php echo esc_html($p->contact_phone); ?></a></small><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p->needs_device): ?>
                            <?php echo $p->device_delivered ? '✅' : '⏳'; ?>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p->trial_start): ?>
                            <?php echo date('d.m', strtotime($p->trial_start)); ?> - <?php echo date('d.m', strtotime($p->trial_end)); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php if ($p->next_followup): ?><span style="color:<?php echo strtotime($p->next_followup) <= time() ? '#ef4444' : '#64748b'; ?>"><?php echo date('d.m.Y', strtotime($p->next_followup)); ?></span><?php endif; ?></td>
                    <td><small><?php echo $p->visited_at ? date('d.m.Y H:i', strtotime($p->visited_at)) : '-'; ?></small></td>
                    <td><small style="color:#475569"><?php echo esc_html($p->notes ?: '-'); ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Agent Role Management -->
        <div class="section-title"><i class="ri-user-settings-line"></i> Agent jogosultság kezelés</div>

        <?php if (!empty($agents)): ?>
        <p style="margin-bottom:10px"><strong>Aktív ágensek:</strong></p>
        <table class="agent-table" style="max-width:600px;margin-bottom:20px">
            <?php foreach ($agents as $a): ?>
            <tr>
                <td>#<?php echo $a->id; ?></td>
                <td><?php echo esc_html($a->display_name ?: '-'); ?></td>
                <td><?php echo esc_html($a->email); ?></td>
                <td>
                    <form method="post" class="agent-role-form">
                        <input type="hidden" name="user_id" value="<?php echo $a->id; ?>">
                        <button type="submit" name="remove_agent_role" class="btn-remove-agent">Agent eltávolítás</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <p style="margin-bottom:10px"><strong>Agent jog hozzáadása felhasználóhoz:</strong></p>
        <form method="get" action="/admin/agent-prospects" style="margin-bottom:12px;display:flex;gap:8px;max-width:500px">
            <input type="text" name="agent_search" value="<?php echo esc_attr($agent_search); ?>" placeholder="Keresés email címre..." style="flex:1;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px">
            <button type="submit" style="padding:8px 16px;background:#1e293b;color:#fff;border:none;border-radius:8px;cursor:pointer;">Keresés</button>
            <?php if ($agent_search): ?><a href="/admin/agent-prospects" style="padding:8px 12px;color:#64748b;text-decoration:none;">✕</a><?php endif; ?>
        </form>
        <table class="agent-table" style="max-width:700px">
            <thead><tr><th>ID</th><th>Név</th><th>Email</th><th>Jelenlegi típus</th><th></th></tr></thead>
            <?php foreach ($potential_agents as $u): ?>
            <tr>
                <td>#<?php echo $u->id; ?></td>
                <td><?php echo esc_html($u->display_name ?: '-'); ?></td>
                <td><?php echo esc_html($u->email); ?></td>
                <td><?php echo esc_html($u->user_type); ?></td>
                <td>
                    <form method="post" class="agent-role-form">
                        <input type="hidden" name="user_id" value="<?php echo $u->id; ?>">
                        <button type="submit" name="set_agent_role" class="btn-set-agent">→ Agent</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }
}
