<?php
/** Magyar, belső WooCommerce rendelési és csomagolási felület. */

if (!defined('ABSPATH')) exit;

final class PPV_Standalone_Webshop_Orders {
    const TABLE_SUFFIX = 'ppv_webshop_orders';
    const SCHEMA_VERSION = '1.0.0';
    const SNAPSHOT_FILE = '/var/lib/punktepass/webshop-orders.json';

    public static function install_schema() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        if (get_option('ppv_webshop_orders_schema_version') === self::SCHEMA_VERSION
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            order_number varchar(80) NOT NULL,
            creation_date datetime NOT NULL,
            modified_date datetime NULL,
            order_status varchar(40) NOT NULL,
            payment_method varchar(160) NULL,
            shipping_methods varchar(255) NULL,
            ship_name varchar(255) NOT NULL,
            ship_company varchar(255) NULL,
            ship_address1 varchar(255) NULL,
            ship_address2 varchar(255) NULL,
            ship_postal_code varchar(32) NULL,
            ship_city varchar(128) NULL,
            ship_country varchar(8) NULL,
            ship_phone varchar(80) NULL,
            ship_email varchar(255) NULL,
            customer_note text NULL,
            items_json longtext NOT NULL,
            gross_total decimal(12,2) NOT NULL DEFAULT 0,
            shipping_total decimal(12,2) NOT NULL DEFAULT 0,
            currency varchar(8) NOT NULL DEFAULT 'EUR',
            packed tinyint(1) NOT NULL DEFAULT 0,
            packed_at datetime NULL,
            synced_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_order_id (order_id),
            KEY idx_creation_date (creation_date),
            KEY idx_packed_status (packed,order_status)
        ) {$charset};");
        if ($wpdb->last_error) throw new RuntimeException('A webshop rendelési tábla nem hozható létre.');
        update_option('ppv_webshop_orders_schema_version', self::SCHEMA_VERSION, false);
    }

    public static function import_snapshot() {
        self::install_schema();
        if (!is_readable(self::SNAPSHOT_FILE)) {
            update_option('ppv_webshop_orders_last_error', 'A webshop pillanatképe még nem érhető el.', false);
            return ['saved' => 0, 'missing' => true];
        }
        $hash = hash_file('sha256', self::SNAPSHOT_FILE);
        if ($hash && hash_equals((string)get_option('ppv_webshop_orders_snapshot_hash', ''), $hash)) {
            return ['saved' => 0, 'unchanged' => true];
        }
        $payload = json_decode(file_get_contents(self::SNAPSHOT_FILE), true);
        if (!is_array($payload) || !is_array($payload['orders'] ?? null)) {
            throw new RuntimeException('A webshop pillanatkép formátuma hibás.');
        }
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $saved = 0;
        foreach ($payload['orders'] as $order) {
            $order_id = (int)($order['id'] ?? 0);
            if ($order_id <= 0) continue;
            $shipping = is_array($order['shipping'] ?? null) ? $order['shipping'] : [];
            $name = trim(sanitize_text_field(($shipping['firstName'] ?? '') . ' ' . ($shipping['lastName'] ?? '')));
            if ($name === '') $name = 'Név nélkül';
            $data = [
                'order_number' => sanitize_text_field($order['number'] ?? $order_id),
                'creation_date' => self::mysql_time($order['createdAt'] ?? null),
                'modified_date' => self::mysql_time($order['modifiedAt'] ?? null),
                'order_status' => sanitize_key($order['status'] ?? 'pending'),
                'payment_method' => sanitize_text_field($order['paymentMethod'] ?? ''),
                'shipping_methods' => sanitize_text_field(implode(', ', array_map('sanitize_text_field', (array)($order['shippingMethods'] ?? [])))),
                'ship_name' => $name,
                'ship_company' => sanitize_text_field($shipping['company'] ?? ''),
                'ship_address1' => sanitize_text_field($shipping['address1'] ?? ''),
                'ship_address2' => sanitize_text_field($shipping['address2'] ?? ''),
                'ship_postal_code' => sanitize_text_field($shipping['postcode'] ?? ''),
                'ship_city' => sanitize_text_field($shipping['city'] ?? ''),
                'ship_country' => sanitize_text_field($shipping['country'] ?? ''),
                'ship_phone' => sanitize_text_field($shipping['phone'] ?? ''),
                'ship_email' => sanitize_email($shipping['email'] ?? ''),
                'customer_note' => sanitize_textarea_field($order['customerNote'] ?? ''),
                'items_json' => wp_json_encode(self::clean_items($order['items'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'gross_total' => round((float)($order['total'] ?? 0), 2),
                'shipping_total' => round((float)($order['shippingTotal'] ?? 0), 2),
                'currency' => sanitize_text_field($order['currency'] ?? 'EUR'),
                'synced_at' => current_time('mysql'),
            ];
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE order_id=%d", $order_id));
            $ok = $existing
                ? $wpdb->update($table, $data, ['order_id' => $order_id])
                : $wpdb->insert($table, array_merge(['order_id' => $order_id, 'packed' => 0], $data));
            if ($ok === false) throw new RuntimeException('A webshop rendelés mentése sikertelen.');
            $saved++;
        }
        update_option('ppv_webshop_orders_last_sync', sanitize_text_field($payload['generatedAt'] ?? current_time('mysql')), false);
        update_option('ppv_webshop_orders_last_error', '', false);
        if ($hash) update_option('ppv_webshop_orders_snapshot_hash', $hash, false);
        return ['saved' => $saved, 'missing' => false];
    }

    public static function handle_packing() {
        self::require_valid_post();
        global $wpdb;
        $order_id = (int)($_POST['order_id'] ?? 0);
        $packed = !empty($_POST['packed']) ? 1 : 0;
        if ($order_id <= 0) self::json_error('Hiányzó rendelésazonosító.', 400);
        $updated = $wpdb->update(
            $wpdb->prefix . self::TABLE_SUFFIX,
            ['packed' => $packed, 'packed_at' => $packed ? current_time('mysql') : null],
            ['order_id' => $order_id],
            ['%d', '%s'],
            ['%d']
        );
        if ($updated === false) self::json_error('A csomagolási állapot nem menthető.', 500);
        self::json_success(['packed' => (bool)$packed]);
    }

    public static function handle_refresh() {
        self::require_valid_post();
        try {
            self::json_success(self::import_snapshot());
        } catch (Throwable $e) {
            update_option('ppv_webshop_orders_last_error', preg_replace('/[\r\n]+/', ' ', $e->getMessage()), false);
            self::json_error('A frissítés most nem sikerült.', 500);
        }
    }

    public static function render() {
        try {
            self::import_snapshot();
        } catch (Throwable $e) {
            update_option('ppv_webshop_orders_last_error', preg_replace('/[\r\n]+/', ' ', $e->getMessage()), false);
        }
        $csrf = self::csrf_token();
        PPV_Standalone_Admin::get_admin_header('webshop-orders');
        self::render_styles();
        ?>
        <div class="shop-head">
            <div>
                <h1 class="page-title"><i class="ri-store-3-line"></i> Webshop rendelések</h1>
                <p>eRepairShop rendelések és csomagolás egy helyen</p>
            </div>
            <button type="button" class="shop-refresh" id="shop-refresh"><i class="ri-refresh-line"></i> Frissítés</button>
        </div>
        <?php self::render_sync_state(); ?>
        <?php self::render_orders(); ?>
        <?php self::render_scripts($csrf); ?>
        <?php PPV_Standalone_Admin::get_admin_footer();
    }

    private static function render_orders() {
        global $wpdb;
        $filter = sanitize_key($_GET['status'] ?? 'open');
        $search = sanitize_text_field($_GET['q'] ?? '');
        $where = ['1=1'];
        $params = [];
        if ($filter === 'open') $where[] = "packed=0 AND order_status IN ('processing','on-hold','pending')";
        elseif ($filter === 'packed') $where[] = 'packed=1';
        elseif ($filter === 'completed') $where[] = "order_status='completed'";
        elseif ($filter === 'cancelled') $where[] = "order_status IN ('cancelled','refunded','failed')";
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(order_number LIKE %s OR ship_name LIKE %s OR ship_city LIKE %s OR items_json LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }
        $sql = "SELECT * FROM {$wpdb->prefix}" . self::TABLE_SUFFIX . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY creation_date DESC LIMIT 300';
        $orders = $params ? $wpdb->get_results($wpdb->prepare($sql, ...$params)) : $wpdb->get_results($sql);
        $counts = $wpdb->get_row("SELECT COUNT(*) total,
            SUM(packed=0 AND order_status IN ('processing','on-hold','pending')) open_count,
            SUM(packed=1) packed_count,
            SUM(order_status='completed') completed_count
            FROM {$wpdb->prefix}" . self::TABLE_SUFFIX);
        ?>
        <div class="shop-stats">
            <div><strong><?php echo (int)($counts->open_count ?? 0); ?></strong><span>Csomagolandó</span></div>
            <div><strong><?php echo (int)($counts->packed_count ?? 0); ?></strong><span>Becsomagolva</span></div>
            <div><strong><?php echo (int)($counts->completed_count ?? 0); ?></strong><span>Teljesítve</span></div>
            <div><strong><?php echo (int)($counts->total ?? 0); ?></strong><span>Összes rendelés</span></div>
        </div>
        <form class="shop-filters" method="get" action="/admin/webshop-orders">
            <select name="status">
                <option value="open" <?php selected($filter, 'open'); ?>>Csomagolandó</option>
                <option value="packed" <?php selected($filter, 'packed'); ?>>Becsomagolva</option>
                <option value="completed" <?php selected($filter, 'completed'); ?>>Teljesítve</option>
                <option value="cancelled" <?php selected($filter, 'cancelled'); ?>>Törölt és visszatérített</option>
                <option value="all" <?php selected($filter, 'all'); ?>>Minden rendelés</option>
            </select>
            <input type="search" name="q" value="<?php echo esc_attr($search); ?>" placeholder="Név, rendelés, termék vagy SKU">
            <button type="submit">Keresés</button>
        </form>
        <div class="shop-order-list">
        <?php if (!$orders): ?><div class="shop-empty"><i class="ri-inbox-line"></i>Nincs a szűrésnek megfelelő rendelés.</div><?php endif; ?>
        <?php foreach ($orders as $order):
            $items = (array)json_decode($order->items_json, true);
            $closed = in_array($order->order_status, ['cancelled', 'refunded', 'failed'], true);
        ?>
            <article class="shop-order <?php echo $order->packed ? 'is-packed' : ''; ?> <?php echo $closed ? 'is-closed' : ''; ?>" data-order-id="<?php echo (int)$order->order_id; ?>">
                <div class="order-top">
                    <label class="pack-check">
                        <input type="checkbox" <?php checked((int)$order->packed, 1); ?> <?php disabled($closed); ?>>
                        <span class="check-box"><i class="ri-check-line"></i></span><span>Becsomagolva</span>
                    </label>
                    <span><?php echo esc_html(date_i18n('Y. m. d. H:i', strtotime($order->creation_date))); ?></span>
                </div>
                <div class="order-body">
                    <section class="customer">
                        <small>Szállítási címzett</small>
                        <h2><?php echo esc_html($order->ship_name); ?></h2>
                        <?php if ($order->ship_company): ?><div><?php echo esc_html($order->ship_company); ?></div><?php endif; ?>
                        <div><?php echo esc_html($order->ship_address1); ?></div>
                        <?php if ($order->ship_address2): ?><div><?php echo esc_html($order->ship_address2); ?></div><?php endif; ?>
                        <div><?php echo esc_html(trim($order->ship_postal_code . ' ' . $order->ship_city)); ?>, <?php echo esc_html($order->ship_country); ?></div>
                        <?php if ($order->ship_phone): ?><div class="contact"><i class="ri-phone-line"></i> <?php echo esc_html($order->ship_phone); ?></div><?php endif; ?>
                    </section>
                    <section class="items">
                        <?php foreach ($items as $item): ?>
                            <div class="item">
                                <span class="quantity"><?php echo (int)($item['quantity'] ?? 1); ?> db</span>
                                <span><strong><?php echo esc_html($item['name'] ?? 'Termék'); ?></strong>
                                <?php if (!empty($item['sku'])): ?><small>SKU: <?php echo esc_html($item['sku']); ?></small><?php endif; ?>
                                <?php foreach ((array)($item['meta'] ?? []) as $meta): ?><small><?php echo esc_html($meta); ?></small><?php endforeach; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </section>
                    <section class="money">
                        <strong><?php echo esc_html(self::money($order->gross_total, $order->currency)); ?></strong>
                        <span class="status"><?php echo esc_html(self::status_label($order)); ?></span>
                        <small>#<?php echo esc_html($order->order_number); ?></small>
                        <?php if ($order->payment_method): ?><small><?php echo esc_html($order->payment_method); ?></small><?php endif; ?>
                    </section>
                </div>
                <?php if ($order->shipping_methods): ?><div class="order-detail"><i class="ri-truck-line"></i> <?php echo esc_html($order->shipping_methods); ?></div><?php endif; ?>
                <?php if ($order->customer_note): ?><div class="customer-note"><strong>Vásárlói megjegyzés:</strong> <?php echo esc_html($order->customer_note); ?></div><?php endif; ?>
                <?php if ($order->packed_at): ?><div class="packed-at">Becsomagolva: <?php echo esc_html(date_i18n('Y. m. d. H:i', strtotime($order->packed_at))); ?></div><?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
        <?php
    }

    private static function render_sync_state() {
        $last = get_option('ppv_webshop_orders_last_sync', 'Még nem futott le');
        $error = get_option('ppv_webshop_orders_last_error', '');
        echo '<div class="sync-state">Utolsó webshop frissítés: <strong>' . esc_html($last) . '</strong>. Automatikus frissítés ötpercenként.</div>';
        if ($error) echo '<div class="sync-error">' . esc_html($error) . '</div>';
    }

    private static function render_styles() { ?>
        <style>
        .shop-head{display:flex;align-items:center;justify-content:space-between;gap:16px}.shop-head .page-title{margin-bottom:4px}.shop-head p,.sync-state{color:#91a0b8;font-size:13px}.sync-state{margin:12px 0}.shop-refresh,.shop-filters button{border:0;border-radius:10px;background:#00d4ea;color:#06161c;padding:11px 16px;font-weight:700;cursor:pointer}.shop-refresh:disabled{opacity:.55}.sync-error{background:rgba(255,166,0,.12);border:1px solid rgba(255,166,0,.35);color:#ffc45c;padding:12px;border-radius:10px}.shop-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:18px 0}.shop-stats>div{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.09);border-radius:14px;padding:18px}.shop-stats strong{display:block;color:#00e6ff;font-size:26px}.shop-stats span{color:#91a0b8;font-size:13px}.shop-filters{display:flex;gap:10px;margin:18px 0}.shop-filters select,.shop-filters input{background:#16213e;color:#fff;border:1px solid rgba(255,255,255,.14);border-radius:10px;padding:11px 13px}.shop-filters input{flex:1}.shop-order-list{display:grid;gap:12px}.shop-order{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:18px}.shop-order.is-packed{border-color:rgba(76,175,80,.5);background:rgba(76,175,80,.06)}.shop-order.is-closed{opacity:.55}.order-top,.order-body{display:flex;align-items:center;justify-content:space-between;gap:18px}.order-top{padding-bottom:13px;border-bottom:1px solid rgba(255,255,255,.08);color:#91a0b8;font-size:12px}.pack-check{display:flex;align-items:center;gap:10px;color:#fff;font-size:14px;font-weight:700;cursor:pointer}.pack-check input{position:absolute;opacity:0}.check-box{width:32px;height:32px;border:2px solid #70809a;border-radius:9px;display:grid;place-items:center;color:transparent;font-size:22px}.pack-check input:checked+.check-box{background:#24b47e;border-color:#24b47e;color:#fff}.order-body{align-items:flex-start;padding-top:16px}.customer{min-width:270px}.customer small,.items small,.money small,.packed-at{display:block;color:#91a0b8;font-size:12px}.customer h2{font-size:20px;margin:4px 0}.contact{margin-top:8px;color:#a9b4c7}.items{flex:1}.item{display:flex;gap:10px;margin-bottom:10px}.quantity{background:rgba(0,230,255,.12);color:#00e6ff;border-radius:8px;padding:5px 8px;height:max-content;white-space:nowrap}.money{text-align:right}.money>strong{display:block;font-size:20px;margin-bottom:8px}.status{display:inline-block;background:rgba(255,255,255,.08);border-radius:20px;padding:5px 9px;color:#aab5c8;font-size:11px;margin-bottom:5px}.order-detail,.customer-note{margin-top:12px;padding-top:10px;border-top:1px solid rgba(255,255,255,.07);color:#aab5c8;font-size:12px}.customer-note{color:#ffd489}.packed-at{text-align:right;margin-top:10px}.shop-empty{text-align:center;padding:60px 20px;color:#91a0b8}.shop-empty i{display:block;font-size:44px}.shop-flash{position:fixed;right:20px;bottom:20px;background:#13213a;border:1px solid #00d4ea;border-radius:12px;padding:13px 16px;z-index:9999}
        @media(max-width:800px){.shop-stats{grid-template-columns:repeat(2,1fr)}.shop-filters{flex-wrap:wrap}.shop-filters input{min-width:100%}.order-body{display:block}.customer{min-width:0;margin-bottom:16px}.money{text-align:left;margin-top:12px}.admin-content{padding:16px 10px}.shop-order{padding:15px}.packed-at{text-align:left}}
        @media(max-width:480px){.shop-head{display:block}.shop-refresh{margin-top:12px;width:100%}.shop-stats{grid-template-columns:1fr}.customer h2{font-size:18px}}
        </style>
    <?php }

    private static function render_scripts($csrf) { ?>
        <script>
        (function(){
            var csrf=<?php echo wp_json_encode($csrf); ?>;
            function flash(text){var n=document.createElement('div');n.className='shop-flash';n.textContent=text;document.body.appendChild(n);setTimeout(function(){n.remove();},2600);}
            document.querySelectorAll('.pack-check input').forEach(function(box){box.addEventListener('change',function(){var card=box.closest('.shop-order');var data=new URLSearchParams();data.set('csrf',csrf);data.set('order_id',card.dataset.orderId);data.set('packed',box.checked?'1':'0');box.disabled=true;fetch('/admin/webshop-orders/packing',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data.toString()}).then(function(r){if(!r.ok)throw new Error();return r.json();}).then(function(){card.classList.toggle('is-packed',box.checked);flash(box.checked?'Becsomagolva elmentve':'Jelölés visszavonva');}).catch(function(){box.checked=!box.checked;flash('A mentés nem sikerült');}).finally(function(){box.disabled=false;});});});
            var refresh=document.getElementById('shop-refresh');if(refresh)refresh.addEventListener('click',function(){var data=new URLSearchParams();data.set('csrf',csrf);refresh.disabled=true;refresh.textContent='Frissítés folyamatban';fetch('/admin/webshop-orders/refresh',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data.toString()}).then(function(r){if(!r.ok)throw new Error();return r.json();}).then(function(){location.reload();}).catch(function(){flash('A frissítés most nem sikerült');refresh.disabled=false;refresh.textContent='Frissítés';});});
        })();
        </script>
    <?php }

    private static function clean_items($items) {
        $clean = [];
        foreach ((array)$items as $item) {
            if (!is_array($item)) continue;
            $clean[] = [
                'name' => sanitize_text_field($item['name'] ?? ''),
                'quantity' => max(1, (int)($item['quantity'] ?? 1)),
                'sku' => sanitize_text_field($item['sku'] ?? ''),
                'meta' => array_values(array_filter(array_map('sanitize_text_field', (array)($item['meta'] ?? [])))),
                'total' => round((float)($item['total'] ?? 0), 2),
            ];
        }
        return $clean;
    }

    private static function status_label($order) {
        if ($order->order_status === 'completed' && (int)$order->packed === 1) return 'Teljesítve, becsomagolva';
        if ((int)$order->packed === 1) return 'Becsomagolva';
        $labels = ['pending'=>'Fizetésre vár','processing'=>'Feldolgozás alatt','on-hold'=>'Függőben','completed'=>'Teljesítve','cancelled'=>'Törölve','refunded'=>'Visszatérítve','failed'=>'Sikertelen'];
        return $labels[$order->order_status] ?? ucfirst((string)$order->order_status);
    }

    private static function money($value, $currency) {
        return number_format((float)$value, 2, ',', ' ') . ' ' . ($currency === 'EUR' ? '€' : sanitize_text_field($currency));
    }

    private static function mysql_time($value) {
        $timestamp = $value ? strtotime((string)$value) : false;
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : current_time('mysql');
    }

    private static function csrf_token() {
        if (session_status() === PHP_SESSION_NONE) ppv_maybe_start_session();
        if (empty($_SESSION['ppv_webshop_orders_csrf'])) $_SESSION['ppv_webshop_orders_csrf'] = bin2hex(random_bytes(24));
        return $_SESSION['ppv_webshop_orders_csrf'];
    }

    private static function require_valid_post() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') self::json_error('Nem engedélyezett kérés.', 405);
        $provided = (string)($_POST['csrf'] ?? '');
        if ($provided === '' || !hash_equals(self::csrf_token(), $provided)) self::json_error('A munkamenet lejárt. Frissítsd az oldalt.', 403);
    }

    private static function json_success($data) {
        nocache_headers();
        wp_send_json_success($data);
    }

    private static function json_error($message, $status) {
        nocache_headers();
        wp_send_json_error(['message' => $message], $status);
    }
}
