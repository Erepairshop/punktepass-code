<?php
/**
 * Magyar nyelvű, belső eBay rendelési és eredménykimutató felület.
 */

if (!defined('ABSPATH')) exit;

final class PPV_Standalone_Ebay_Cockpit {
    const STORE_ID = 9;
    const ORDER_TABLE_SUFFIX = 'ppv_ebay_cockpit_orders';
    const COST_TABLE_SUFFIX = 'ppv_ebay_cockpit_costs';
    const SCHEMA_VERSION = '1.0.0';
    const COST_FILE = '/var/lib/punktepass/ebay-cockpit-costs.json';

    public static function install_schema() {
        global $wpdb;
        $order_table = $wpdb->prefix . self::ORDER_TABLE_SUFFIX;
        $cost_table = $wpdb->prefix . self::COST_TABLE_SUFFIX;
        if (get_option('ppv_ebay_cockpit_schema_version') === self::SCHEMA_VERSION
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $order_table)) === $order_table
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $cost_table)) === $cost_table) {
            return;
        }
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$order_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id varchar(128) NOT NULL,
            creation_date datetime NOT NULL,
            last_modified_date datetime NULL,
            buyer_username varchar(255) NULL,
            ship_name varchar(255) NOT NULL,
            ship_company varchar(255) NULL,
            ship_address1 varchar(255) NULL,
            ship_address2 varchar(255) NULL,
            ship_postal_code varchar(32) NULL,
            ship_city varchar(128) NULL,
            ship_country varchar(8) NULL,
            items_json longtext NOT NULL,
            gross_total decimal(12,2) NOT NULL DEFAULT 0,
            shipping_revenue decimal(12,2) NOT NULL DEFAULT 0,
            currency varchar(8) NOT NULL DEFAULT 'EUR',
            payment_status varchar(40) NULL,
            fulfillment_status varchar(40) NULL,
            cancellation_state varchar(40) NULL,
            purchase_cost decimal(12,2) NULL,
            ebay_fee_cost decimal(12,2) NULL,
            ad_fee_cost decimal(12,2) NULL,
            shipping_cost decimal(12,2) NULL,
            packaging_cost decimal(12,2) NULL,
            cost_status varchar(24) NOT NULL DEFAULT 'missing',
            fee_status varchar(24) NOT NULL DEFAULT 'estimated',
            packed tinyint(1) NOT NULL DEFAULT 0,
            packed_at datetime NULL,
            synced_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_order_id (order_id),
            KEY idx_creation_date (creation_date),
            KEY idx_packed_creation (packed,creation_date),
            KEY idx_fulfillment (fulfillment_status)
        ) {$charset};");

        dbDelta("CREATE TABLE {$cost_table} (
            sku varchar(191) NOT NULL,
            supplier varchar(80) NULL,
            supplier_sku varchar(191) NULL,
            net_cost decimal(12,4) NOT NULL,
            observed_at datetime NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (sku),
            KEY idx_supplier_sku (supplier_sku)
        ) {$charset};");

        if ($wpdb->last_error) {
            throw new RuntimeException('Az eBay Cockpit adatbázisa nem hozható létre: ' . $wpdb->last_error);
        }
        update_option('ppv_ebay_cockpit_schema_version', self::SCHEMA_VERSION, false);
    }

    public static function sync_orders($days = null) {
        self::install_schema();
        if (!class_exists('PPV_Ebay_Invoice')) {
            require_once PPV_PLUGIN_DIR . 'includes/class-ppv-ebay-invoice.php';
        }
        self::import_cost_file();

        if ($days === null) {
            global $wpdb;
            $known_orders = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}" . self::ORDER_TABLE_SUFFIX);
            $days = $known_orders > 0 ? 14 : 120;
        }
        $orders = PPV_Ebay_Invoice::cockpit_orders(max(1, min(365, (int)$days)));
        $result = ['fetched' => count($orders), 'saved' => 0, 'failed' => 0];
        foreach ($orders as $order) {
            try {
                self::upsert_order($order);
                $result['saved']++;
            } catch (Throwable $e) {
                $result['failed']++;
                ppv_log('[eBay Cockpit] Sikertelen rendelésmentés: ' . preg_replace('/[\r\n]+/', ' ', $e->getMessage()));
            }
        }
        update_option('ppv_ebay_cockpit_last_sync', current_time('mysql'), false);
        update_option('ppv_ebay_cockpit_last_sync_error', '', false);
        return $result;
    }

    public static function import_cost_file() {
        global $wpdb;
        if (!is_readable(self::COST_FILE)) return ['imported' => 0, 'missing' => true];
        $file_hash = hash_file('sha256', self::COST_FILE);
        if ($file_hash && hash_equals((string)get_option('ppv_ebay_cockpit_cost_file_hash', ''), $file_hash)) {
            return ['imported' => 0, 'missing' => false, 'unchanged' => true];
        }
        $decoded = json_decode(file_get_contents(self::COST_FILE), true);
        $rows = is_array($decoded) && isset($decoded['items']) ? $decoded['items'] : $decoded;
        if (!is_array($rows)) throw new RuntimeException('A beszerzésiár fájl formátuma hibás.');

        $table = $wpdb->prefix . self::COST_TABLE_SUFFIX;
        $now = current_time('mysql');
        $count = 0;
        foreach ($rows as $row) {
            $sku = trim((string)($row['sku'] ?? ''));
            $cost = (float)($row['netCost'] ?? $row['lastNetPrice'] ?? 0);
            if ($sku === '' || $cost <= 0) continue;
            $observed = self::mysql_time($row['observedAt'] ?? null);
            $sql = $wpdb->prepare(
                "INSERT INTO {$table} (sku,supplier,supplier_sku,net_cost,observed_at,updated_at)
                 VALUES (%s,%s,%s,%f,%s,%s)
                 ON DUPLICATE KEY UPDATE supplier=VALUES(supplier),supplier_sku=VALUES(supplier_sku),
                 net_cost=VALUES(net_cost),observed_at=VALUES(observed_at),updated_at=VALUES(updated_at)",
                $sku,
                sanitize_text_field($row['supplier'] ?? ''),
                sanitize_text_field($row['supplierSku'] ?? ''),
                $cost,
                $observed,
                $now
            );
            $wpdb->query($sql);
            if (!$wpdb->last_error) $count++;
        }
        update_option('ppv_ebay_cockpit_last_cost_import', $now, false);
        if ($file_hash) update_option('ppv_ebay_cockpit_cost_file_hash', $file_hash, false);
        return ['imported' => $count, 'missing' => false];
    }

    public static function upsert_order(array $order) {
        global $wpdb;
        $order_id = trim((string)($order['orderId'] ?? ''));
        if ($order_id === '') throw new InvalidArgumentException('Hiányzó eBay rendelésazonosító.');

        $table = $wpdb->prefix . self::ORDER_TABLE_SUFFIX;
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE order_id=%s", $order_id));
        $old_items = [];
        if ($existing && $existing->items_json) {
            foreach ((array)json_decode($existing->items_json, true) as $item) {
                $key = (string)($item['lineItemId'] ?? $item['sku'] ?? '');
                if ($key !== '') $old_items[$key] = $item;
            }
        }

        $shipping = $order['fulfillmentStartInstructions'][0]['shippingStep']['shipTo'] ?? [];
        $address = $shipping['contactAddress'] ?? [];
        $items = [];
        $purchase_cost = 0.0;
        $missing_costs = 0;
        $ad_fee = 0.0;
        $settings = self::settings();

        foreach (($order['lineItems'] ?? []) as $line) {
            $qty = max(1, (int)($line['quantity'] ?? 1));
            $sku = trim((string)($line['sku'] ?? ''));
            $line_id = trim((string)($line['lineItemId'] ?? ''));
            $key = $line_id !== '' ? $line_id : $sku;
            $old = $old_items[$key] ?? [];
            $cost = null;
            $supplier = '';
            $cost_observed_at = null;
            if (isset($old['unitCostNet']) && (float)$old['unitCostNet'] > 0) {
                $cost = (float)$old['unitCostNet'];
                $supplier = (string)($old['supplier'] ?? '');
                $cost_observed_at = $old['costObservedAt'] ?? null;
            } elseif ($sku !== '') {
                $cost_row = self::find_cost($sku);
                if ($cost_row) {
                    $cost = (float)$cost_row->net_cost;
                    $supplier = (string)$cost_row->supplier;
                    $cost_observed_at = $cost_row->observed_at;
                }
            }
            if ($cost === null) {
                $missing_costs++;
            } else {
                $purchase_cost += $cost * $qty;
            }

            $line_gross = round((float)($line['total']['value'] ?? 0), 2);
            $sold_via_ad = !empty($line['lineItemProperties']['soldViaAdCampaign']);
            if ($sold_via_ad) {
                $unit_gross = $qty > 0 ? $line_gross / $qty : $line_gross;
                $rate = $unit_gross >= 200 ? $settings['ad_high_rate'] : $settings['ad_low_rate'];
                $ad_fee += $line_gross * ($rate / 100);
            }
            $items[] = [
                'lineItemId' => $line_id,
                'itemId' => sanitize_text_field($line['legacyItemId'] ?? ''),
                'sku' => $sku,
                'title' => sanitize_text_field($line['title'] ?? ($sku ?: 'eBay termék')),
                'quantity' => $qty,
                'gross' => $line_gross,
                'soldViaAdCampaign' => $sold_via_ad,
                'supplier' => $supplier,
                'unitCostNet' => $cost,
                'costObservedAt' => $cost_observed_at,
            ];
        }

        $gross = round((float)($order['pricingSummary']['total']['value'] ?? 0), 2);
        $shipping_revenue = round((float)($order['pricingSummary']['deliveryCost']['value'] ?? 0), 2);
        $country = strtoupper(sanitize_text_field($address['countryCode'] ?? ''));
        $shipping_cost = $existing && $existing->shipping_cost !== null
            ? (float)$existing->shipping_cost
            : ($country === 'DE' ? $settings['domestic_shipping_cost'] : $settings['eu_shipping_cost']);
        $packaging_cost = $existing && $existing->packaging_cost !== null
            ? (float)$existing->packaging_cost
            : $settings['packaging_cost'];
        $ebay_fee = $existing && $existing->fee_status === 'actual' && $existing->ebay_fee_cost !== null
            ? (float)$existing->ebay_fee_cost
            : round($gross * ($settings['ebay_fee_rate'] / 100), 2);
        $ad_fee_value = $existing && $existing->fee_status === 'actual' && $existing->ad_fee_cost !== null
            ? (float)$existing->ad_fee_cost
            : round($ad_fee, 2);

        $data = [
            'order_id' => $order_id,
            'creation_date' => self::mysql_time($order['creationDate'] ?? null),
            'last_modified_date' => self::mysql_time($order['lastModifiedDate'] ?? null),
            'buyer_username' => sanitize_text_field($order['buyer']['username'] ?? ''),
            'ship_name' => sanitize_text_field($shipping['fullName'] ?? ($order['buyer']['username'] ?? 'eBay vásárló')),
            'ship_company' => sanitize_text_field($shipping['companyName'] ?? ''),
            'ship_address1' => sanitize_text_field($address['addressLine1'] ?? ''),
            'ship_address2' => sanitize_text_field($address['addressLine2'] ?? ''),
            'ship_postal_code' => sanitize_text_field($address['postalCode'] ?? ''),
            'ship_city' => sanitize_text_field($address['city'] ?? ''),
            'ship_country' => $country,
            'items_json' => wp_json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'gross_total' => $gross,
            'shipping_revenue' => $shipping_revenue,
            'currency' => sanitize_text_field($order['pricingSummary']['total']['currency'] ?? 'EUR'),
            'payment_status' => sanitize_text_field($order['orderPaymentStatus'] ?? ''),
            'fulfillment_status' => sanitize_text_field($order['orderFulfillmentStatus'] ?? ''),
            'cancellation_state' => sanitize_text_field($order['cancelStatus']['cancelState'] ?? ''),
            'purchase_cost' => round($purchase_cost, 2),
            'ebay_fee_cost' => $ebay_fee,
            'ad_fee_cost' => $ad_fee_value,
            'shipping_cost' => round($shipping_cost, 2),
            'packaging_cost' => round($packaging_cost, 2),
            'cost_status' => $missing_costs === 0 && count($items) > 0 ? 'complete' : ($purchase_cost > 0 ? 'partial' : 'missing'),
            'fee_status' => $existing && $existing->fee_status === 'actual' ? 'actual' : 'estimated',
            'synced_at' => current_time('mysql'),
        ];

        if ($existing) {
            $ok = $wpdb->update($table, $data, ['order_id' => $order_id]);
        } else {
            $data['packed'] = 0;
            $data['packed_at'] = null;
            $ok = $wpdb->insert($table, $data);
        }
        if ($ok === false) throw new RuntimeException('A rendelés mentése sikertelen: ' . $wpdb->last_error);
    }

    public static function handle_packing() {
        self::require_valid_post();
        global $wpdb;
        $order_id = sanitize_text_field($_POST['order_id'] ?? '');
        $packed = !empty($_POST['packed']) ? 1 : 0;
        if ($order_id === '') self::json_error('Hiányzó rendelésazonosító.', 400);
        $updated = $wpdb->update(
            $wpdb->prefix . self::ORDER_TABLE_SUFFIX,
            ['packed' => $packed, 'packed_at' => $packed ? current_time('mysql') : null],
            ['order_id' => $order_id],
            ['%d', '%s'],
            ['%s']
        );
        if ($updated === false) self::json_error('A csomagolási állapot nem menthető.', 500);
        self::json_success(['packed' => (bool)$packed, 'packedAt' => $packed ? current_time('mysql') : null]);
    }

    public static function handle_settings() {
        self::require_valid_post();
        $settings = self::settings();
        foreach (array_keys($settings) as $key) {
            if (!isset($_POST[$key])) continue;
            $settings[$key] = max(0, min(1000, (float)str_replace(',', '.', (string)$_POST[$key])));
        }
        update_option('ppv_ebay_cockpit_settings', $settings, false);
        wp_safe_redirect('/admin/ebay-cockpit?tab=profit&saved=1');
        exit;
    }

    public static function handle_manual_sync() {
        self::require_valid_post();
        try {
            $result = self::sync_orders(120);
            self::json_success($result);
        } catch (Throwable $e) {
            update_option('ppv_ebay_cockpit_last_sync_error', preg_replace('/[\r\n]+/', ' ', $e->getMessage()), false);
            self::json_error('A frissítés most nem sikerült.', 500);
        }
    }

    public static function render() {
        self::install_schema();
        $tab = ($_GET['tab'] ?? 'orders') === 'profit' ? 'profit' : 'orders';
        $csrf = self::csrf_token();
        PPV_Standalone_Admin::get_admin_header('ebay-cockpit');
        self::render_styles();
        ?>
        <div class="ebay-head">
            <div>
                <h1 class="page-title"><i class="ri-shopping-bag-3-line"></i> eBay Cockpit</h1>
                <p class="ebay-subtitle">Rendelések, csomagolás és becsült nyereség egy helyen</p>
            </div>
            <button type="button" class="ebay-sync" id="ebay-sync"><i class="ri-refresh-line"></i> Frissítés</button>
        </div>
        <?php self::render_sync_state(); ?>
        <div class="ebay-tabs">
            <a href="/admin/ebay-cockpit" class="<?php echo $tab === 'orders' ? 'active' : ''; ?>">Rendelések</a>
            <a href="/admin/ebay-cockpit?tab=profit" class="<?php echo $tab === 'profit' ? 'active' : ''; ?>">Nyereség</a>
        </div>
        <?php
        if ($tab === 'profit') self::render_profit();
        else self::render_orders();
        self::render_scripts($csrf);
        PPV_Standalone_Admin::get_admin_footer();
    }

    private static function render_orders() {
        global $wpdb;
        $filter = sanitize_key($_GET['status'] ?? 'open');
        $search = sanitize_text_field($_GET['q'] ?? '');
        $where = ['1=1'];
        $params = [];
        if ($filter === 'open') $where[] = "packed=0 AND fulfillment_status<>'FULFILLED' AND cancellation_state<>'CANCELED'";
        elseif ($filter === 'packed') $where[] = 'packed=1';
        elseif ($filter === 'shipped') $where[] = "fulfillment_status='FULFILLED'";
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(order_id LIKE %s OR ship_name LIKE %s OR buyer_username LIKE %s OR items_json LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }
        $sql = "SELECT * FROM {$wpdb->prefix}" . self::ORDER_TABLE_SUFFIX . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY creation_date DESC LIMIT 200';
        $orders = $params ? $wpdb->get_results($wpdb->prepare($sql, ...$params)) : $wpdb->get_results($sql);
        $counts = $wpdb->get_row("SELECT COUNT(*) total,
            SUM(packed=0 AND fulfillment_status<>'FULFILLED' AND cancellation_state<>'CANCELED') open_count,
            SUM(packed=1) packed_count,
            SUM(fulfillment_status='FULFILLED') shipped_count
            FROM {$wpdb->prefix}" . self::ORDER_TABLE_SUFFIX);
        ?>
        <div class="ebay-stats">
            <div><strong><?php echo (int)($counts->open_count ?? 0); ?></strong><span>Csomagolandó</span></div>
            <div><strong><?php echo (int)($counts->packed_count ?? 0); ?></strong><span>Becsomagolva</span></div>
            <div><strong><?php echo (int)($counts->shipped_count ?? 0); ?></strong><span>Feladva</span></div>
            <div><strong><?php echo (int)($counts->total ?? 0); ?></strong><span>Összes rendelés</span></div>
        </div>
        <form class="ebay-filters" method="get" action="/admin/ebay-cockpit">
            <select name="status">
                <option value="open" <?php selected($filter, 'open'); ?>>Csomagolandó</option>
                <option value="packed" <?php selected($filter, 'packed'); ?>>Becsomagolva</option>
                <option value="shipped" <?php selected($filter, 'shipped'); ?>>Feladva</option>
                <option value="all" <?php selected($filter, 'all'); ?>>Minden rendelés</option>
            </select>
            <input type="search" name="q" value="<?php echo esc_attr($search); ?>" placeholder="Név, rendelés, termék vagy SKU">
            <button type="submit">Keresés</button>
        </form>
        <div class="order-list">
        <?php if (!$orders): ?>
            <div class="empty-state"><i class="ri-inbox-line"></i>Nincs a szűrésnek megfelelő rendelés.</div>
        <?php endif; ?>
        <?php foreach ($orders as $order):
            $items = (array)json_decode($order->items_json, true);
            $cancelled = $order->cancellation_state === 'CANCELED';
        ?>
            <article class="order-card <?php echo $order->packed ? 'is-packed' : ''; ?> <?php echo $cancelled ? 'is-cancelled' : ''; ?>" data-order-id="<?php echo esc_attr($order->order_id); ?>">
                <div class="order-top">
                    <label class="pack-check">
                        <input type="checkbox" <?php checked((int)$order->packed, 1); ?> <?php disabled($cancelled); ?>>
                        <span class="check-box"><i class="ri-check-line"></i></span>
                        <span>Becsomagolva</span>
                    </label>
                    <div class="order-date"><?php echo esc_html(date_i18n('Y. m. d. H:i', strtotime($order->creation_date))); ?></div>
                </div>
                <div class="order-grid">
                    <div class="customer-block">
                        <span class="minor">Szállítási címzett</span>
                        <h2><?php echo esc_html($order->ship_name); ?></h2>
                        <?php if ($order->ship_company): ?><div><?php echo esc_html($order->ship_company); ?></div><?php endif; ?>
                        <div class="address-line"><?php echo esc_html(trim($order->ship_postal_code . ' ' . $order->ship_city . ' ' . $order->ship_country)); ?></div>
                        <div class="order-id">eBay: <?php echo esc_html($order->order_id); ?></div>
                    </div>
                    <div class="items-block">
                        <?php foreach ($items as $item): ?>
                            <div class="item-line">
                                <span class="qty"><?php echo (int)($item['quantity'] ?? 1); ?> db</span>
                                <span><strong><?php echo esc_html($item['title'] ?? 'eBay termék'); ?></strong>
                                <?php if (!empty($item['sku'])): ?><small>SKU: <?php echo esc_html($item['sku']); ?></small><?php endif; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="order-money">
                        <strong><?php echo esc_html(self::money($order->gross_total)); ?></strong>
                        <span class="status-pill"><?php echo esc_html(self::status_label($order)); ?></span>
                    </div>
                </div>
                <?php if ($order->packed_at): ?><div class="packed-time">Becsomagolva: <?php echo esc_html(date_i18n('Y. m. d. H:i', strtotime($order->packed_at))); ?></div><?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
        <?php
    }

    private static function render_profit() {
        global $wpdb;
        $period = sanitize_key($_GET['period'] ?? '30');
        $days = in_array($period, ['1', '7', '30', '90'], true) ? (int)$period : 30;
        $from = date('Y-m-d 00:00:00', current_time('timestamp') - (($days - 1) * DAY_IN_SECONDS));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::ORDER_TABLE_SUFFIX . "
             WHERE creation_date>=%s AND cancellation_state<>'CANCELED' AND currency='EUR'
             ORDER BY creation_date DESC", $from
        ));
        $summary = ['gross' => 0.0, 'net' => 0.0, 'costs' => 0.0, 'profit' => 0.0, 'complete' => 0, 'incomplete' => 0];
        foreach ($rows as $row) {
            $summary['gross'] += (float)$row->gross_total;
            $summary['net'] += (float)$row->gross_total / 1.19;
            if ($row->cost_status !== 'complete') {
                $summary['incomplete']++;
                continue;
            }
            $costs = self::total_costs($row);
            $summary['costs'] += $costs;
            $summary['profit'] += ((float)$row->gross_total / 1.19) - $costs;
            $summary['complete']++;
        }
        $settings = self::settings();
        ?>
        <div class="period-links">
            <?php foreach ([1 => 'Ma', 7 => '7 nap', 30 => '30 nap', 90 => '90 nap'] as $value => $label): ?>
                <a href="/admin/ebay-cockpit?tab=profit&period=<?php echo $value; ?>" class="<?php echo $days === $value ? 'active' : ''; ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </div>
        <div class="profit-summary">
            <div><span>Bruttó forgalom</span><strong><?php echo esc_html(self::money($summary['gross'])); ?></strong></div>
            <div><span>Nettó bevétel</span><strong><?php echo esc_html(self::money($summary['net'])); ?></strong></div>
            <div><span>Levonható költségek</span><strong><?php echo esc_html(self::money($summary['costs'])); ?></strong></div>
            <div class="profit-main"><span>Becsült nyereség</span><strong><?php echo esc_html(self::money($summary['profit'])); ?></strong></div>
        </div>
        <?php if ($summary['incomplete']): ?>
            <div class="notice-warn"><?php echo (int)$summary['incomplete']; ?> rendelésnél még hiányzik legalább egy beszerzési ár. Ezek nem kerültek bele a nyereség összegébe.</div>
        <?php endif; ?>
        <div class="profit-table-wrap">
            <table class="profit-table">
                <thead><tr><th>Dátum</th><th>Vásárló</th><th>Bruttó</th><th>Beszerzés</th><th>eBay díj</th><th>Hirdetés</th><th>Posta és csomag</th><th>Nyereség</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): $complete = $row->cost_status === 'complete'; $profit = $complete ? ((float)$row->gross_total / 1.19) - self::total_costs($row) : null; ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n('m. d.', strtotime($row->creation_date))); ?></td>
                        <td><?php echo esc_html($row->ship_name); ?><small><?php echo esc_html($row->order_id); ?></small></td>
                        <td><?php echo esc_html(self::money($row->gross_total)); ?></td>
                        <td><?php echo $complete ? esc_html(self::money($row->purchase_cost)) : '<span class="missing">Hiányos</span>'; ?></td>
                        <td><?php echo esc_html(self::money($row->ebay_fee_cost)); ?></td>
                        <td><?php echo esc_html(self::money($row->ad_fee_cost)); ?></td>
                        <td><?php echo esc_html(self::money((float)$row->shipping_cost + (float)$row->packaging_cost)); ?></td>
                        <td class="profit-cell"><?php echo $profit === null ? '<span class="missing">Nem számítható</span>' : esc_html(self::money($profit)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <details class="profit-settings" <?php echo isset($_GET['saved']) ? 'open' : ''; ?>>
            <summary>Számítási beállítások</summary>
            <?php if (isset($_GET['saved'])): ?><div class="success-msg">A beállításokat elmentettük.</div><?php endif; ?>
            <form method="post" action="/admin/ebay-cockpit/settings">
                <input type="hidden" name="csrf" value="<?php echo esc_attr(self::csrf_token()); ?>">
                <?php
                $labels = [
                    'ebay_fee_rate' => 'eBay díj százaléka',
                    'ad_low_rate' => 'Hirdetési díj 200 euró alatt',
                    'ad_high_rate' => 'Hirdetési díj 200 eurótól',
                    'domestic_shipping_cost' => 'Németországi postaköltség',
                    'eu_shipping_cost' => 'EU postaköltség',
                    'packaging_cost' => 'Csomagolási költség',
                ];
                foreach ($labels as $key => $label): ?>
                    <label><span><?php echo esc_html($label); ?></span><input type="number" step="0.01" min="0" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($settings[$key]); ?>"></label>
                <?php endforeach; ?>
                <button type="submit">Beállítások mentése</button>
            </form>
            <p>A díjak jelenleg becslések. A beszerzési ár a rendelés első feldolgozásakor rögzül, ezért a későbbi beszállítói árváltozás nem írja át a régi rendelést.</p>
        </details>
        <?php
    }

    private static function render_sync_state() {
        $last = get_option('ppv_ebay_cockpit_last_sync', 'Még nem futott le');
        $cost = get_option('ppv_ebay_cockpit_last_cost_import', 'Még nincs árimport');
        $error = get_option('ppv_ebay_cockpit_last_sync_error', '');
        echo '<div class="sync-state">Utolsó rendelésfrissítés: <strong>' . esc_html($last) . '</strong> · Utolsó árfrissítés: <strong>' . esc_html($cost) . '</strong></div>';
        if ($error) echo '<div class="notice-warn">Az utolsó automatikus frissítés hibája: ' . esc_html($error) . '</div>';
    }

    private static function render_styles() { ?>
        <style>
            .ebay-head{display:flex;align-items:center;justify-content:space-between;gap:16px}.ebay-head .page-title{margin-bottom:4px}.ebay-subtitle,.sync-state{color:#91a0b8;font-size:13px}.sync-state{margin:12px 0}.ebay-sync,.ebay-filters button,.profit-settings button{border:0;border-radius:10px;background:#00d4ea;color:#06161c;padding:11px 16px;font-weight:700;cursor:pointer}.ebay-sync:disabled{opacity:.55}.ebay-tabs{display:flex;gap:8px;margin:22px 0}.ebay-tabs a,.period-links a{color:#a9b4c7;text-decoration:none;padding:10px 16px;border:1px solid rgba(255,255,255,.1);border-radius:10px}.ebay-tabs a.active,.period-links a.active{color:#fff;background:rgba(0,230,255,.15);border-color:#00d4ea}.ebay-stats,.profit-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:18px 0}.ebay-stats>div,.profit-summary>div{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.09);border-radius:14px;padding:18px}.ebay-stats strong,.profit-summary strong{display:block;color:#00e6ff;font-size:26px}.ebay-stats span,.profit-summary span{color:#91a0b8;font-size:13px}.profit-summary .profit-main{background:rgba(33,186,120,.12);border-color:rgba(33,186,120,.45)}.profit-summary .profit-main strong{color:#4dde9f}.ebay-filters{display:flex;gap:10px;margin:18px 0}.ebay-filters select,.ebay-filters input,.profit-settings input{background:#16213e;color:#fff;border:1px solid rgba(255,255,255,.14);border-radius:10px;padding:11px 13px}.ebay-filters input{flex:1}.order-list{display:grid;gap:12px}.order-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:18px}.order-card.is-packed{border-color:rgba(76,175,80,.5);background:rgba(76,175,80,.06)}.order-card.is-cancelled{opacity:.55}.order-top,.order-grid{display:flex;align-items:center;justify-content:space-between;gap:18px}.order-top{padding-bottom:13px;border-bottom:1px solid rgba(255,255,255,.08)}.pack-check{display:flex;align-items:center;gap:10px;font-weight:700;cursor:pointer}.pack-check input{position:absolute;opacity:0}.check-box{width:32px;height:32px;border:2px solid #70809a;border-radius:9px;display:grid;place-items:center;color:transparent;font-size:22px}.pack-check input:checked+.check-box{background:#24b47e;border-color:#24b47e;color:#fff}.order-date,.minor,.address-line,.order-id,.packed-time{color:#91a0b8;font-size:12px}.order-grid{align-items:flex-start;padding-top:16px}.customer-block{min-width:260px}.customer-block h2{font-size:20px;margin:4px 0}.items-block{flex:1}.item-line{display:flex;gap:10px;margin-bottom:10px}.item-line .qty{background:rgba(0,230,255,.12);color:#00e6ff;border-radius:8px;padding:5px 8px;height:max-content;white-space:nowrap}.item-line small,.profit-table small{display:block;color:#8290a8;margin-top:4px}.order-money{text-align:right}.order-money>strong{display:block;font-size:20px;margin-bottom:8px}.status-pill{display:inline-block;background:rgba(255,255,255,.08);border-radius:20px;padding:5px 9px;color:#aab5c8;font-size:11px}.packed-time{text-align:right;margin-top:10px}.period-links{display:flex;flex-wrap:wrap;gap:8px;margin:18px 0}.notice-warn{background:rgba(255,166,0,.12);border:1px solid rgba(255,166,0,.35);color:#ffc45c;padding:13px 16px;border-radius:11px;margin:14px 0}.profit-table-wrap{overflow:auto;background:rgba(255,255,255,.035);border:1px solid rgba(255,255,255,.08);border-radius:14px}.profit-table{min-width:980px}.profit-cell{color:#4dde9f;font-weight:700}.missing{color:#ffbd59}.profit-settings{margin-top:20px;background:rgba(255,255,255,.035);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:16px}.profit-settings summary{cursor:pointer;font-weight:700}.profit-settings form{display:grid;grid-template-columns:repeat(3,minmax(160px,1fr));gap:12px;margin:18px 0}.profit-settings label span{display:block;color:#a9b4c7;font-size:12px;margin-bottom:5px}.profit-settings input{width:100%}.profit-settings p{color:#91a0b8;font-size:12px}.sync-flash{position:fixed;right:20px;bottom:20px;background:#13213a;border:1px solid #00d4ea;border-radius:12px;padding:13px 16px;z-index:9999}
            @media(max-width:800px){.ebay-head{align-items:flex-start}.ebay-stats,.profit-summary{grid-template-columns:repeat(2,1fr)}.ebay-filters{flex-wrap:wrap}.ebay-filters input{min-width:100%}.order-grid{display:block}.customer-block{min-width:0;margin-bottom:16px}.order-money{text-align:left;margin-top:12px}.profit-settings form{grid-template-columns:1fr 1fr}.admin-content{padding:16px 10px}.order-card{padding:15px}.order-top{align-items:flex-start}.order-date{text-align:right}.packed-time{text-align:left}}
            @media(max-width:480px){.ebay-head{display:block}.ebay-sync{margin-top:12px;width:100%}.ebay-stats,.profit-summary,.profit-settings form{grid-template-columns:1fr}.ebay-tabs a{flex:1;text-align:center}.customer-block h2{font-size:18px}}
        </style>
    <?php }

    private static function render_scripts($csrf) { ?>
        <script>
        (function(){
            var csrf=<?php echo wp_json_encode($csrf); ?>;
            function flash(text){var n=document.createElement('div');n.className='sync-flash';n.textContent=text;document.body.appendChild(n);setTimeout(function(){n.remove();},2600);}
            document.querySelectorAll('.pack-check input').forEach(function(box){box.addEventListener('change',function(){var card=box.closest('.order-card');var data=new URLSearchParams();data.set('csrf',csrf);data.set('order_id',card.dataset.orderId);data.set('packed',box.checked?'1':'0');box.disabled=true;fetch('/admin/ebay-cockpit/packing',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data.toString()}).then(function(r){if(!r.ok)throw new Error();return r.json();}).then(function(){card.classList.toggle('is-packed',box.checked);flash(box.checked?'Becsomagolva elmentve':'Jelölés visszavonva');}).catch(function(){box.checked=!box.checked;flash('A mentés nem sikerült');}).finally(function(){box.disabled=false;});});});
            var sync=document.getElementById('ebay-sync');if(sync)sync.addEventListener('click',function(){var data=new URLSearchParams();data.set('csrf',csrf);sync.disabled=true;sync.textContent='Frissítés folyamatban';fetch('/admin/ebay-cockpit/sync',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data.toString()}).then(function(r){if(!r.ok)throw new Error();return r.json();}).then(function(){location.reload();}).catch(function(){flash('A frissítés most nem sikerült');sync.disabled=false;sync.textContent='Frissítés';});});
        })();
        </script>
    <?php }

    private static function settings() {
        $defaults = [
            'ebay_fee_rate' => 15.0,
            'ad_low_rate' => 9.0,
            'ad_high_rate' => 4.0,
            'domestic_shipping_cost' => 5.50,
            'eu_shipping_cost' => 9.00,
            'packaging_cost' => 0.50,
        ];
        $saved = get_option('ppv_ebay_cockpit_settings', []);
        return array_merge($defaults, is_array($saved) ? $saved : []);
    }

    private static function find_cost($sku) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::COST_TABLE_SUFFIX . ' WHERE sku=%s LIMIT 1', $sku
        ));
    }

    private static function total_costs($row) {
        return (float)$row->purchase_cost + (float)$row->ebay_fee_cost + (float)$row->ad_fee_cost + (float)$row->shipping_cost + (float)$row->packaging_cost;
    }

    private static function status_label($order) {
        if ($order->cancellation_state === 'CANCELED') return 'Törölve';
        if ($order->fulfillment_status === 'FULFILLED') return 'Feladva';
        if ((int)$order->packed === 1) return 'Becsomagolva';
        if ($order->payment_status === 'PAID') return 'Fizetve';
        return 'Feldolgozás alatt';
    }

    private static function money($value) {
        return number_format((float)$value, 2, ',', ' ') . ' €';
    }

    private static function csrf_token() {
        if (session_status() === PHP_SESSION_NONE) ppv_maybe_start_session();
        if (empty($_SESSION['ppv_ebay_cockpit_csrf'])) $_SESSION['ppv_ebay_cockpit_csrf'] = bin2hex(random_bytes(24));
        return $_SESSION['ppv_ebay_cockpit_csrf'];
    }

    private static function require_valid_post() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') self::json_error('Nem engedélyezett kérés.', 405);
        $expected = self::csrf_token();
        $provided = (string)($_POST['csrf'] ?? '');
        if ($provided === '' || !hash_equals($expected, $provided)) self::json_error('A munkamenet lejárt. Frissítsd az oldalt.', 403);
    }

    private static function json_success($data = []) {
        nocache_headers();
        wp_send_json_success($data);
    }

    private static function json_error($message, $status = 400) {
        nocache_headers();
        wp_send_json_error(['message' => $message], $status);
    }

    private static function mysql_time($value) {
        if (!$value) return null;
        $timestamp = strtotime((string)$value);
        if (!$timestamp) return null;
        return get_date_from_gmt(gmdate('Y-m-d H:i:s', $timestamp), 'Y-m-d H:i:s');
    }
}
