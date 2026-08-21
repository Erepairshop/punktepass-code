<?php
/**
 * eBay paid-order to PunktePass invoice bridge.
 *
 * Secrets live outside the web root in /etc/punktepass/ebay-invoice.env.
 * The webhook only queues verified order IDs. A systemd timer performs the
 * order lookup, idempotent invoice creation and email delivery.
 */

if (!defined('ABSPATH')) exit;

final class PPV_Ebay_Invoice {
    const STORE_ID = 9;
    const CONFIG_FILE = '/etc/punktepass/ebay-invoice.env';
    const API_BASE = 'https://api.ebay.com';
    const TABLE_SUFFIX = 'ppv_ebay_orders';

    private static $config = null;

    public static function install_schema() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            store_id bigint(20) unsigned NOT NULL,
            order_id varchar(128) NOT NULL,
            notification_id varchar(128) NULL,
            invoice_id bigint(20) unsigned NULL,
            status varchar(32) NOT NULL DEFAULT 'pending',
            attempts int(10) unsigned NOT NULL DEFAULT 0,
            last_error text NULL,
            notified_at datetime NULL,
            email_sent_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_order (order_id),
            UNIQUE KEY uniq_notification (notification_id),
            KEY idx_status (status, updated_at),
            KEY idx_invoice (invoice_id)
        ) {$charset}";
        $wpdb->query($sql);
        if ($wpdb->last_error) {
            throw new RuntimeException('eBay queue schema failed: ' . $wpdb->last_error);
        }

        $invoice_table = $wpdb->prefix . 'ppv_repair_invoices';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$invoice_table}", 0);
        if (!in_array('external_source', $columns, true)) {
            $wpdb->query("ALTER TABLE {$invoice_table} ADD COLUMN external_source varchar(32) NULL AFTER payment_method");
        }
        if (!in_array('external_order_id', $columns, true)) {
            $wpdb->query("ALTER TABLE {$invoice_table} ADD COLUMN external_order_id varchar(128) NULL AFTER external_source");
        }
        $indexes = $wpdb->get_col("SHOW INDEX FROM {$invoice_table}", 2);
        if (!in_array('uniq_external_order', $indexes, true)) {
            $wpdb->query("ALTER TABLE {$invoice_table} ADD UNIQUE KEY uniq_external_order (store_id,external_source,external_order_id)");
        }
        if ($wpdb->last_error) {
            throw new RuntimeException('Invoice idempotency schema failed: ' . $wpdb->last_error);
        }
    }

    public static function challenge_response($challenge_code) {
        $token = self::config('EBAY_VERIFICATION_TOKEN');
        $endpoint = self::config('EBAY_WEBHOOK_ENDPOINT');
        return hash('sha256', (string)$challenge_code . $token . $endpoint);
    }

    public static function verify_notification($raw_payload, $signature_header) {
        if (!$raw_payload || !$signature_header) return false;
        $decoded = base64_decode($signature_header, true);
        if ($decoded === false) return false;
        $packed = json_decode($decoded, true);
        if (!is_array($packed) || empty($packed['kid']) || empty($packed['signature'])) return false;

        $kid = (string)$packed['kid'];
        $signature = base64_decode((string)$packed['signature'], true);
        if ($signature === false) return false;
        $cache_key = 'ppv_ebay_pub_' . md5($kid);
        $public_key = get_transient($cache_key);
        if (!$public_key) {
            $token = self::access_token();
            $url = self::API_BASE . '/commerce/notification/v1/public_key/' . rawurlencode($kid);
            $response = wp_remote_get($url, [
                'timeout' => 20,
                'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
            ]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return false;
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (empty($body['key'])) return false;
            $public_key = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($body['key'], 64, "\n") . "-----END PUBLIC KEY-----\n";
            set_transient($cache_key, $public_key, HOUR_IN_SECONDS);
        }

        return openssl_verify($raw_payload, $signature, $public_key, OPENSSL_ALGO_SHA1) === 1;
    }

    public static function enqueue_notification(array $payload) {
        if (($payload['metadata']['topic'] ?? '') !== 'ORDER_CONFIRMATION') {
            throw new InvalidArgumentException('Unsupported notification topic.');
        }
        $notification = $payload['notification'] ?? [];
        $order_id = trim((string)($notification['data']['order']['orderId'] ?? ''));
        $notification_id = trim((string)($notification['notificationId'] ?? ''));
        if ($order_id === '' || $notification_id === '') {
            throw new InvalidArgumentException('Notification is missing order identifiers.');
        }
        $notified_at = self::mysql_time($notification['eventDate'] ?? null);
        self::enqueue_order($order_id, $notification_id, $notified_at);
    }

    public static function enqueue_order($order_id, $notification_id = null, $notified_at = null) {
        global $wpdb;
        self::install_schema();
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $now = current_time('mysql');
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (store_id,order_id,notification_id,status,notified_at,created_at,updated_at)
             VALUES (%d,%s,NULLIF(%s,''),'pending',%s,%s,%s)
             ON DUPLICATE KEY UPDATE
               notification_id=COALESCE(notification_id,VALUES(notification_id)),
               notified_at=COALESCE(notified_at,VALUES(notified_at)),
               updated_at=VALUES(updated_at)",
            self::STORE_ID, (string)$order_id, (string)$notification_id,
            $notified_at ?: $now, $now, $now
        );
        $wpdb->query($sql);
        if ($wpdb->last_error) throw new RuntimeException('Unable to queue eBay order.');
    }

    public static function reconcile_orders() {
        $start_at = self::config('EBAY_INVOICE_START_AT');
        $start_ts = strtotime($start_at);
        if (!$start_ts) throw new RuntimeException('Invalid EBAY_INVOICE_START_AT.');
        $token = self::access_token();
        $filter = 'creationdate:[' . gmdate('Y-m-d\TH:i:s.000\Z', $start_ts) . '..' . gmdate('Y-m-d\TH:i:s.000\Z') . ']';
        $url = self::API_BASE . '/sell/fulfillment/v1/order?' . http_build_query(['limit' => 50, 'filter' => $filter]);
        $queued = 0;
        for ($page = 0; $url && $page < 20; $page++) {
            $body = self::api_get($url, $token);
            foreach (($body['orders'] ?? []) as $order) {
                if (($order['orderPaymentStatus'] ?? '') !== 'PAID') continue;
                $created = strtotime($order['creationDate'] ?? '');
                if (!$created || $created < $start_ts || empty($order['orderId'])) continue;
                self::enqueue_order($order['orderId'], null, self::mysql_time($order['creationDate']));
                $queued++;
            }
            $url = !empty($body['next']) ? (string)$body['next'] : null;
        }
        return $queued;
    }

    public static function process_queue($limit = 20) {
        global $wpdb;
        self::install_schema();
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status IN ('pending','retry','invoice_created') AND attempts < 30
             ORDER BY id ASC LIMIT %d", max(1, (int)$limit)
        ));
        $result = ['processed' => 0, 'completed' => 0, 'retry' => 0];
        foreach ($rows as $row) {
            $result['processed']++;
            try {
                self::process_order_row($row);
                $result['completed']++;
            } catch (Throwable $e) {
                self::mark_retry($row->id, $e->getMessage());
                $result['retry']++;
            }
        }
        return $result;
    }

    public static function dry_run_order($order_id) {
        $order = self::get_order($order_id);
        $data = self::invoice_data($order);
        return [
            'paymentStatus' => $order['orderPaymentStatus'] ?? null,
            'fulfillmentStatus' => $order['orderFulfillmentStatus'] ?? null,
            'currency' => $data['currency'],
            'grossTotal' => $data['gross_total'],
            'netTotal' => $data['net_total'],
            'vatAmount' => $data['vat_amount'],
            'lineCount' => count($data['line_items']),
            'hasCustomerName' => $data['customer_name'] !== '',
            'hasCustomerAddress' => $data['customer_address'] !== '',
            'hasCustomerEmail' => $data['customer_email'] !== '',
        ];
    }

    public static function dry_run_latest_order() {
        $response = self::api_get(
            self::API_BASE . '/sell/fulfillment/v1/order?' . http_build_query(['limit' => 1]),
            self::access_token()
        );
        $order_id = (string)($response['orders'][0]['orderId'] ?? '');
        if ($order_id === '') throw new RuntimeException('No eBay order is available for a dry run.');
        return self::dry_run_order($order_id);
    }

    public static function status_summary() {
        global $wpdb;
        self::install_schema();
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        return $wpdb->get_results("SELECT status,COUNT(*) AS count FROM {$table} GROUP BY status ORDER BY status", ARRAY_A);
    }

    public static function smtp_auth_test() {
        $mail = self::new_mailer();
        try {
            if (!$mail->smtpConnect()) throw new RuntimeException('SMTP authentication failed.');
            $mail->smtpClose();
            return true;
        } finally {
            if (method_exists($mail, 'smtpClose')) $mail->smtpClose();
        }
    }

    public static function notification_topic() {
        return self::api_get(
            self::API_BASE . '/commerce/notification/v1/topic/ORDER_CONFIRMATION',
            self::access_token()
        );
    }

    public static function notification_status() {
        $token = self::access_token();
        $destination_rows = self::api_get(self::API_BASE . '/commerce/notification/v1/destination', $token);
        $subscription_rows = self::api_get(self::API_BASE . '/commerce/notification/v1/subscription', $token);
        $destinations = [];
        foreach (($destination_rows['destinations'] ?? []) as $row) {
            $destinations[] = [
                'destinationId' => $row['destinationId'] ?? '',
                'name' => $row['name'] ?? '',
                'status' => $row['status'] ?? '',
                'endpoint' => $row['deliveryConfig']['endpoint'] ?? '',
            ];
        }
        $subscriptions = [];
        foreach (($subscription_rows['subscriptions'] ?? []) as $row) {
            $subscriptions[] = [
                'subscriptionId' => $row['subscriptionId'] ?? '',
                'topicId' => $row['topicId'] ?? '',
                'status' => $row['status'] ?? '',
                'destinationId' => $row['destinationId'] ?? '',
                'payload' => $row['payload'] ?? [],
            ];
        }
        return [
            'destinations' => $destinations,
            'subscriptions' => $subscriptions,
        ];
    }

    public static function setup_notifications() {
        $token = self::access_token();
        $endpoint = self::config('EBAY_WEBHOOK_ENDPOINT');
        $verification_token = self::config('EBAY_VERIFICATION_TOKEN');
        $destination_id = '';

        $destinations = self::api_get(self::API_BASE . '/commerce/notification/v1/destination', $token);
        foreach (($destinations['destinations'] ?? []) as $destination) {
            if (($destination['deliveryConfig']['endpoint'] ?? '') === $endpoint) {
                $destination_id = (string)($destination['destinationId'] ?? '');
                break;
            }
        }
        if ($destination_id === '') {
            self::api_request('POST', self::API_BASE . '/commerce/notification/v1/destination', $token, [
                'name' => 'punktepass-ebay-order-invoices',
                'status' => 'ENABLED',
                'deliveryConfig' => [
                    'endpoint' => $endpoint,
                    'verificationToken' => $verification_token,
                ],
            ]);
            $destinations = self::api_get(self::API_BASE . '/commerce/notification/v1/destination', $token);
            foreach (($destinations['destinations'] ?? []) as $destination) {
                if (($destination['deliveryConfig']['endpoint'] ?? '') === $endpoint) {
                    $destination_id = (string)($destination['destinationId'] ?? '');
                    break;
                }
            }
        }
        if ($destination_id === '') throw new RuntimeException('eBay destination was not created.');
        self::api_request(
            'PUT',
            self::API_BASE . '/commerce/notification/v1/destination/' . rawurlencode($destination_id),
            $token,
            [
                'name' => 'punktepass-ebay-order-invoices',
                'status' => 'ENABLED',
                'deliveryConfig' => [
                    'endpoint' => $endpoint,
                    'verificationToken' => $verification_token,
                ],
            ]
        );

        $subscription_id = '';
        $subscriptions = self::api_get(self::API_BASE . '/commerce/notification/v1/subscription', $token);
        foreach (($subscriptions['subscriptions'] ?? []) as $subscription) {
            if (($subscription['topicId'] ?? '') === 'ORDER_CONFIRMATION' &&
                ($subscription['destinationId'] ?? '') === $destination_id) {
                $subscription_id = (string)($subscription['subscriptionId'] ?? '');
                break;
            }
        }
        if ($subscription_id === '') {
            $topic = self::notification_topic();
            $payload = $topic['supportedPayloads'][0] ?? null;
            if (!is_array($payload)) throw new RuntimeException('ORDER_CONFIRMATION payload definition is unavailable.');
            $formats = $payload['format'] ?? ['JSON'];
            $format = is_array($formats) ? (string)($formats[0] ?? 'JSON') : (string)$formats;
            self::api_request('POST', self::API_BASE . '/commerce/notification/v1/subscription', $token, [
                'topicId' => 'ORDER_CONFIRMATION',
                'destinationId' => $destination_id,
                'status' => 'ENABLED',
                'payload' => [
                    'format' => $format,
                    'schemaVersion' => (string)($payload['schemaVersion'] ?? '1.0'),
                    'deliveryProtocol' => (string)($payload['deliveryProtocol'] ?? 'HTTPS'),
                ],
            ]);
            $subscriptions = self::api_get(self::API_BASE . '/commerce/notification/v1/subscription', $token);
            foreach (($subscriptions['subscriptions'] ?? []) as $subscription) {
                if (($subscription['topicId'] ?? '') === 'ORDER_CONFIRMATION' &&
                    ($subscription['destinationId'] ?? '') === $destination_id) {
                    $subscription_id = (string)($subscription['subscriptionId'] ?? '');
                    break;
                }
            }
        }
        if ($subscription_id === '') throw new RuntimeException('eBay subscription was not created.');
        return ['destinationId' => $destination_id, 'subscriptionId' => $subscription_id];
    }

    public static function test_notification($subscription_id) {
        self::api_request(
            'POST',
            self::API_BASE . '/commerce/notification/v1/subscription/' . rawurlencode($subscription_id) . '/test',
            self::access_token(),
            null
        );
        return true;
    }

    private static function process_order_row($row) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $lock_name = 'ppv_ebay_' . md5($row->order_id);
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,10)', $lock_name)) !== 1) {
            throw new RuntimeException('Order lock timeout.');
        }
        try {
            $fresh = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $row->id));
            if (!$fresh || $fresh->status === 'completed') return;
            $order = self::get_order($fresh->order_id);
            if (($order['orderPaymentStatus'] ?? '') !== 'PAID') {
                throw new RuntimeException('Order payment is not cleared.');
            }
            $data = self::invoice_data($order);
            if ($data['currency'] !== 'EUR') throw new RuntimeException('Unsupported order currency.');
            if ($data['customer_email'] === '') throw new RuntimeException('Buyer email is not available yet.');

            $invoice_id = (int)$fresh->invoice_id;
            if (!$invoice_id) {
                $invoice_id = self::create_invoice($fresh, $data);
                $wpdb->update($table, [
                    'invoice_id' => $invoice_id,
                    'status' => 'invoice_created',
                    'last_error' => null,
                    'updated_at' => current_time('mysql'),
                ], ['id' => $fresh->id]);
            }

            $wpdb->update($table, [
                'status' => 'email_sending',
                'updated_at' => current_time('mysql'),
            ], ['id' => $fresh->id]);
            self::send_invoice($invoice_id);
            $wpdb->update($table, [
                'status' => 'completed',
                'email_sent_at' => current_time('mysql'),
                'last_error' => null,
                'updated_at' => current_time('mysql'),
            ], ['id' => $fresh->id]);
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }

    private static function create_invoice($queue_row, array $data) {
        global $wpdb;
        $invoice_table = $wpdb->prefix . 'ppv_repair_invoices';
        $store_table = $wpdb->prefix . 'ppv_stores';
        $lock = 'ppv_invoice_store_' . self::STORE_ID;
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,10)', $lock)) !== 1) {
            throw new RuntimeException('Invoice number lock timeout.');
        }
        try {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$invoice_table} WHERE store_id=%d AND external_source='ebay' AND external_order_id=%s LIMIT 1",
                self::STORE_ID, $queue_row->order_id
            ));
            if ($existing) return (int)$existing;
            $store = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$store_table} WHERE id=%d AND active=1", self::STORE_ID));
            if (!$store) throw new RuntimeException('eRepairShop store is unavailable.');
            $prefix = $store->repair_invoice_prefix ?: 'RE-';
            $next = max(1, (int)($store->repair_invoice_next_number ?: 1));
            $numbers = $wpdb->get_col($wpdb->prepare(
                "SELECT invoice_number FROM {$invoice_table} WHERE store_id=%d AND (doc_type='rechnung' OR doc_type IS NULL) AND invoice_number LIKE %s",
                self::STORE_ID, $wpdb->esc_like($prefix) . '%'
            ));
            foreach ($numbers as $number) {
                if (preg_match('/(\d+)$/', $number, $m)) $next = max($next, ((int)$m[1]) + 1);
            }
            $invoice_number = $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
            $created_at = self::mysql_time($data['created_at']);
            $notes = 'eBay-Bestellung: ' . $queue_row->order_id . "\n" .
                'Bestell- und Zahlungsdatum: ' . date('d.m.Y', strtotime($created_at)) . "\n" .
                'Leistungsmonat: ' . date('m/Y', strtotime($created_at));
            $inserted = $wpdb->insert($invoice_table, [
                'store_id' => self::STORE_ID,
                'repair_id' => null,
                'invoice_number' => $invoice_number,
                'doc_type' => 'rechnung',
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'],
                'customer_company' => $data['customer_company'],
                'customer_address' => $data['customer_address'],
                'customer_plz' => $data['customer_plz'],
                'customer_city' => $data['customer_city'],
                'device_info' => 'eBay-Bestellung',
                'description' => 'Online-Bestellung über eBay',
                'line_items' => wp_json_encode($data['line_items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'subtotal' => $data['gross_total'],
                'discount_type' => 'none',
                'discount_value' => 0,
                'net_amount' => $data['net_total'],
                'vat_rate' => 19,
                'vat_amount' => $data['vat_amount'],
                'total' => $data['gross_total'],
                'is_kleinunternehmer' => 0,
                'notes' => $notes,
                'status' => 'paid',
                'created_at' => $created_at,
                'paid_at' => $created_at,
                'payment_method' => 'ebay',
                'external_source' => 'ebay',
                'external_order_id' => $queue_row->order_id,
            ]);
            if (!$inserted) throw new RuntimeException('Invoice insert failed: ' . $wpdb->last_error);
            $invoice_id = (int)$wpdb->insert_id;
            $wpdb->update($store_table, ['repair_invoice_next_number' => $next + 1], ['id' => self::STORE_ID]);
            return $invoice_id;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    private static function send_invoice($invoice_id) {
        global $wpdb;
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_repair_invoices WHERE id=%d AND store_id=%d",
            $invoice_id, self::STORE_ID
        ));
        $store = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id=%d", self::STORE_ID));
        if (!$invoice || !$store) throw new RuntimeException('Invoice email data is missing.');
        if (!is_email($invoice->customer_email)) throw new RuntimeException('Buyer email is invalid.');

        if (class_exists('PPV_Lang')) {
            PPV_Lang::$active = 'de';
            PPV_Lang::load('de');
        }
        if (!class_exists('PPV_Repair_Invoice')) {
            require_once PPV_PLUGIN_DIR . 'includes/class-ppv-repair-invoice.php';
        }
        $method = new ReflectionMethod('PPV_Repair_Invoice', 'build_invoice_html');
        $method->setAccessible(true);
        $html = $method->invoke(null, $store, $invoice);

        require_once PPV_PLUGIN_DIR . 'libs/dompdf/autoload.inc.php';
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $temp = wp_tempnam(sanitize_file_name($invoice->invoice_number) . '.pdf');
        if (!$temp || file_put_contents($temp, $dompdf->output()) === false) {
            throw new RuntimeException('Temporary invoice PDF could not be created.');
        }
        try {
            $mail = self::new_mailer();
            $mail->setFrom('info@erepairshop.de', 'eRepairShop');
            $mail->addReplyTo('info@erepairshop.de', 'eRepairShop');
            $mail->addAddress($invoice->customer_email, $invoice->customer_name);
            $mail->Subject = 'Ihre Rechnung ' . $invoice->invoice_number . ' von eRepairShop';
            $mail->Body = "Guten Tag " . $invoice->customer_name . ",\n\n" .
                "vielen Dank für Ihre Bestellung bei eRepairShop über eBay. Im Anhang erhalten Sie Ihre Rechnung " . $invoice->invoice_number . ".\n\n" .
                "Gesamtbetrag: " . number_format((float)$invoice->total, 2, ',', '.') . " EUR\n\n" .
                "Freundliche Grüße\neRepairShop\nErik Borota";
            $mail->addAttachment($temp, sanitize_file_name($invoice->invoice_number) . '.pdf');
            $mail->send();
        } finally {
            @unlink($temp);
        }
    }

    private static function new_mailer() {
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        }
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'info@erepairshop.de';
        $mail->Password = self::config('SMTP_PASSWORD');
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 20;
        return $mail;
    }

    private static function get_order($order_id) {
        return self::api_get(self::API_BASE . '/sell/fulfillment/v1/order/' . rawurlencode($order_id), self::access_token());
    }

    private static function invoice_data(array $order) {
        $shipping = $order['fulfillmentStartInstructions'][0]['shippingStep']['shipTo'] ?? [];
        $registration = $order['buyer']['buyerRegistrationAddress'] ?? [];
        $contact = !empty($registration['contactAddress']['addressLine1']) ? $registration : $shipping;
        $address = $contact['contactAddress'] ?? [];
        $email = sanitize_email($registration['email'] ?? ($shipping['email'] ?? ''));
        $name = sanitize_text_field($contact['fullName'] ?? ($shipping['fullName'] ?? ($order['buyer']['username'] ?? 'eBay-Kunde')));
        $company = sanitize_text_field($contact['companyName'] ?? '');
        $currency = (string)($order['pricingSummary']['total']['currency'] ?? '');
        $gross = round((float)($order['pricingSummary']['total']['value'] ?? 0), 2);
        if ($gross <= 0) throw new RuntimeException('Order total is invalid.');
        $net = round($gross / 1.19, 2);
        $vat = round($gross - $net, 2);
        $items = [];
        $items_net_total = 0.0;
        foreach (($order['lineItems'] ?? []) as $line) {
            $qty = max(1, (int)($line['quantity'] ?? 1));
            $line_gross = round((float)($line['total']['value'] ?? 0), 2);
            $line_net = round($line_gross / 1.19, 2);
            $description = sanitize_text_field($line['title'] ?? ($line['sku'] ?? 'eBay-Artikel'));
            if (!empty($line['sku'])) $description .= ' | SKU ' . sanitize_text_field($line['sku']);
            $unit_net = round($line_net / $qty, 2);
            $allocated = 0.0;
            for ($i = 1; $i <= $qty; $i++) {
                $amount = ($i === $qty) ? round($line_net - $allocated, 2) : $unit_net;
                $items[] = ['description' => $description, 'amount' => $amount];
                $allocated = round($allocated + $amount, 2);
                $items_net_total = round($items_net_total + $amount, 2);
            }
        }
        if (!$items) throw new RuntimeException('Order has no invoiceable line items.');
        $adjustment = round($net - $items_net_total, 2);
        if (abs($adjustment) >= 0.01) {
            $items[] = [
                'description' => $adjustment > 0 ? 'Versand und Bestellanpassung' : 'eBay-Rabatt',
                'amount' => $adjustment,
            ];
        }
        $street = trim(implode(' ', array_filter([
            sanitize_text_field($address['addressLine1'] ?? ''),
            sanitize_text_field($address['addressLine2'] ?? ''),
        ])));
        return [
            'created_at' => $order['creationDate'] ?? gmdate('c'),
            'currency' => $currency,
            'gross_total' => $gross,
            'net_total' => $net,
            'vat_amount' => $vat,
            'line_items' => $items,
            'customer_name' => $name,
            'customer_email' => $email ?: '',
            'customer_phone' => sanitize_text_field($contact['primaryPhone']['phoneNumber'] ?? ''),
            'customer_company' => $company,
            'customer_address' => $street,
            'customer_plz' => sanitize_text_field($address['postalCode'] ?? ''),
            'customer_city' => sanitize_text_field($address['city'] ?? ''),
        ];
    }

    private static function access_token() {
        $cached = get_transient('ppv_ebay_invoice_access');
        if ($cached) return $cached;
        $client_id = self::config('EBAY_CLIENT_ID');
        $client_secret = self::config('EBAY_CLIENT_SECRET');
        $refresh_token = self::config('EBAY_REFRESH_TOKEN');
        $response = wp_remote_post(self::API_BASE . '/identity/v1/oauth2/token', [
            'timeout' => 25,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token,
                'scope' => implode(' ', [
                    'https://api.ebay.com/oauth/api_scope',
                    'https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly',
                    'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
                    'https://api.ebay.com/oauth/api_scope/commerce.notification.subscription',
                ]),
            ]),
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            throw new RuntimeException('eBay token refresh failed.');
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) throw new RuntimeException('eBay token response is incomplete.');
        $ttl = max(60, ((int)($body['expires_in'] ?? 7200)) - 300);
        set_transient('ppv_ebay_invoice_access', $body['access_token'], $ttl);
        return $body['access_token'];
    }

    private static function api_get($url, $token) {
        $response = wp_remote_get($url, [
            'timeout' => 25,
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
        ]);
        $status = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
        if (is_wp_error($response) || $status < 200 || $status >= 300) {
            throw new RuntimeException('eBay API request failed with HTTP ' . $status . '.');
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) throw new RuntimeException('eBay API returned invalid JSON.');
        return $body;
    }

    private static function api_request($method, $url, $token, $body = null) {
        $args = [
            'method' => strtoupper($method),
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];
        if ($body !== null) $args['body'] = wp_json_encode($body, JSON_UNESCAPED_SLASHES);
        $response = wp_remote_request($url, $args);
        $status = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
        if (is_wp_error($response) || $status < 200 || $status >= 300) {
            $details = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
            $details = preg_replace('/[\r\n]+/', ' ', (string)$details);
            throw new RuntimeException('eBay API ' . strtoupper($method) . ' failed with HTTP ' . $status . ': ' . mb_substr($details, 0, 800));
        }
        $raw = wp_remote_retrieve_body($response);
        if ($raw === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function mark_retry($row_id, $message) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $safe = preg_replace('/[\r\n]+/', ' ', (string)$message);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status='retry',attempts=attempts+1,last_error=%s,updated_at=%s WHERE id=%d",
            mb_substr($safe, 0, 1000), current_time('mysql'), (int)$row_id
        ));
    }

    private static function config($key) {
        if (self::$config === null) {
            if (!is_readable(self::CONFIG_FILE)) throw new RuntimeException('eBay invoice configuration is unavailable.');
            self::$config = [];
            foreach (file(self::CONFIG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
                list($name, $value) = explode('=', $line, 2);
                self::$config[trim($name)] = trim($value);
            }
        }
        $b64_key = $key . '_B64';
        if (isset(self::$config[$b64_key])) {
            $decoded = base64_decode(self::$config[$b64_key], true);
            if ($decoded === false || $decoded === '') throw new RuntimeException('Invalid secure configuration: ' . $key);
            return $decoded;
        }
        if (!isset(self::$config[$key]) || self::$config[$key] === '') throw new RuntimeException('Missing configuration: ' . $key);
        return self::$config[$key];
    }

    private static function mysql_time($value) {
        $timestamp = $value ? strtotime((string)$value) : time();
        if (!$timestamp) $timestamp = time();
        return get_date_from_gmt(gmdate('Y-m-d H:i:s', $timestamp), 'Y-m-d H:i:s');
    }
}
