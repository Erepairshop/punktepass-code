<?php
if (!defined('ABSPATH')) exit;

class PPV_POS_Log {

    public static function hooks() {
        add_action('admin_menu', [__CLASS__, 'add_admin_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
    }

    /** 🔹 Admin menüpont */
    public static function add_admin_page() {
        add_menu_page(
            'POS Log',
            'POS Log',
            'manage_options',
            'ppv-pos-log',
            [__CLASS__, 'render_admin_page'],
            'dashicons-clipboard',
            56
        );
    }

    /** 🔹 Stílus hozzáadása */
    public static function enqueue_styles() {
        wp_enqueue_style('ppv-pos-log', PPV_PLUGIN_URL . 'assets/css/ppv-pos-log.css', [], time());
    }

    /** 🔹 Napló oldal megjelenítése */
    public static function render_admin_page() {
        global $wpdb;

        $rows = $wpdb->get_results("
            SELECT id, email, store_id, points_change, reward_title, message, created_at 
            FROM wp_ppv_pos_log 
            ORDER BY id DESC 
            LIMIT 100
        ");

        ?>
        <div class="wrap">
            <h1>📊 PunktePass POS Log</h1>
            <p>Letzte 100 Transaktionen aus allen Kassen.</p>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Datum</th>
                        <th>Benutzer</th>
                        <th>Store ID</th>
                        <th>Änderung</th>
                        <th>Belohnung</th>
                        <th>Nachricht</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows): foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo intval($r->id); ?></td>
                        <td><?php echo esc_html($r->created_at); ?></td>
                        <td><?php echo esc_html($r->email); ?></td>
                        <td><?php echo intval($r->store_id); ?></td>
                        <td style="color:<?php echo ($r->points_change >= 0 ? '#00ff7f' : '#ff5555'); ?>;">
                            <?php echo ($r->points_change >= 0 ? '+' : '') . intval($r->points_change); ?> Punkte
                        </td>
                        <td><?php echo esc_html($r->reward_title ?: '-'); ?></td>
                        <td><?php echo esc_html($r->message); ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7">Keine POS-Einträge gefunden.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

add_action('plugins_loaded', function() {
    if (class_exists('PPV_POS_Log')) PPV_POS_Log::hooks();
});
