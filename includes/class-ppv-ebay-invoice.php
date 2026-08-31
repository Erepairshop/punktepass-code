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
    const GROUP_WINDOW_SECONDS = 900;

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
        $queue_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        $queue_additions = [
            'cancellation_status' => "ALTER TABLE {$table} ADD COLUMN cancellation_status varchar(32) NULL AFTER email_sent_at",
            'cancellation_state' => "ALTER TABLE {$table} ADD COLUMN cancellation_state varchar(32) NULL AFTER cancellation_status",
            'cancellation_checked_at' => "ALTER TABLE {$table} ADD COLUMN cancellation_checked_at datetime NULL AFTER cancellation_state",
            'cancelled_at' => "ALTER TABLE {$table} ADD COLUMN cancelled_at datetime NULL AFTER cancellation_checked_at",
            'cancellation_invoice_id' => "ALTER TABLE {$table} ADD COLUMN cancellation_invoice_id bigint(20) unsigned NULL AFTER cancelled_at",
            'cancellation_email_sent_at' => "ALTER TABLE {$table} ADD COLUMN cancellation_email_sent_at datetime NULL AFTER cancellation_invoice_id",
            'cancellation_attempts' => "ALTER TABLE {$table} ADD COLUMN cancellation_attempts int(10) unsigned NOT NULL DEFAULT 0 AFTER cancellation_email_sent_at",
            'cancellation_last_error' => "ALTER TABLE {$table} ADD COLUMN cancellation_last_error text NULL AFTER cancellation_attempts",
            'buyer_note' => "ALTER TABLE {$table} ADD COLUMN buyer_note text NULL AFTER email_sent_at",
            'buyer_note_hash' => "ALTER TABLE {$table} ADD COLUMN buyer_note_hash char(64) NULL AFTER buyer_note",
            'buyer_note_action' => "ALTER TABLE {$table} ADD COLUMN buyer_note_action varchar(32) NULL AFTER buyer_note_hash",
            'buyer_note_email' => "ALTER TABLE {$table} ADD COLUMN buyer_note_email varchar(190) NULL AFTER buyer_note_action",
            'buyer_note_notified_at' => "ALTER TABLE {$table} ADD COLUMN buyer_note_notified_at datetime NULL AFTER buyer_note_email",
        ];
        foreach ($queue_additions as $column => $sql) {
            if (!in_array($column, $queue_columns, true)) $wpdb->query($sql);
        }
        $queue_indexes = $wpdb->get_col("SHOW INDEX FROM {$table}", 2);
        if (!in_array('idx_cancellation_invoice', $queue_indexes, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD KEY idx_cancellation_invoice (cancellation_invoice_id)");
        }
        if ($wpdb->last_error) throw new RuntimeException('eBay cancellation schema failed: ' . $wpdb->last_error);

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
                if (($order['cancelStatus']['cancelState'] ?? '') === 'CANCELED') continue;
                $created = strtotime($order['creationDate'] ?? '');
                if (!$created || $created < $start_ts || empty($order['orderId'])) continue;
                self::enqueue_order($order['orderId'], null, self::mysql_time($order['creationDate']));
                $queued++;
            }
            $url = !empty($body['next']) ? (string)$body['next'] : null;
        }
        return $queued;
    }

    /**
     * Rendelések lekérése a belső eBay Cockpit számára.
     * A hozzáférési tokent nem adja át a megjelenítési rétegnek.
     */
    public static function cockpit_orders($days = 120) {
        $days = max(1, min(365, (int)$days));
        $start_ts = time() - ($days * DAY_IN_SECONDS);
        $token = self::access_token();
        $filter = 'creationdate:[' . gmdate('Y-m-d\TH:i:s.000\Z', $start_ts) . '..' . gmdate('Y-m-d\TH:i:s.000\Z') . ']';
        $url = self::API_BASE . '/sell/fulfillment/v1/order?' . http_build_query([
            'limit' => 100,
            'filter' => $filter,
        ]);
        $orders = [];
        for ($page = 0; $url && $page < 30; $page++) {
            $body = self::api_get($url, $token);
            foreach (($body['orders'] ?? []) as $order) {
                if (!empty($order['orderId'])) $orders[] = $order;
            }
            $url = !empty($body['next']) ? (string)$body['next'] : null;
        }
        return $orders;
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
        $result['buyer_notes'] = self::process_note_notifications(50);
        return $result;
    }

    public static function process_note_notifications($limit = 50) {
        global $wpdb;
        self::install_schema();
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,order_id,buyer_note FROM {$table}
             WHERE buyer_note_action='notify_pending' AND buyer_note_notified_at IS NULL
             ORDER BY id ASC LIMIT %d", max(1, (int)$limit)
        ));
        $result = ['pending' => count($rows), 'sent' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            if (self::send_buyer_note_ntfy($row->order_id, $row->buyer_note)) {
                $wpdb->update($table, [
                    'buyer_note_action' => 'manual_notified',
                    'buyer_note_notified_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ], ['id' => $row->id]);
                $result['sent']++;
            } else {
                $result['failed']++;
            }
        }
        return $result;
    }

    public static function scan_buyer_notes($days = 7) {
        global $wpdb;
        self::install_schema();
        $days = max(1, min(30, (int)$days));
        $start_ts = time() - ($days * DAY_IN_SECONDS);
        $filter = 'creationdate:[' . gmdate('Y-m-d\TH:i:s.000\Z', $start_ts) . '..' . gmdate('Y-m-d\TH:i:s.000\Z') . ']';
        $url = self::API_BASE . '/sell/fulfillment/v1/order?' . http_build_query(['limit' => 100, 'filter' => $filter]);
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $found = 0;
        for ($page = 0; $url && $page < 5; $page++) {
            $body = self::api_get($url, self::access_token());
            foreach (($body['orders'] ?? []) as $order) {
                $note = self::buyer_note_analysis($order);
                if ($note['text'] === '' || empty($order['orderId'])) continue;
                self::enqueue_order($order['orderId'], null, self::mysql_time($order['creationDate'] ?? null));
                $row_id = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE order_id=%s LIMIT 1", (string)$order['orderId']
                ));
                if ($row_id) self::store_buyer_note($row_id, $note);
                $found++;
            }
            $url = !empty($body['next']) ? (string)$body['next'] : null;
        }
        return ['notes_found' => $found, 'notifications' => self::process_note_notifications(100)];
    }

    public static function process_cancellations($limit = 100, $dry_run = false) {
        global $wpdb;
        self::install_schema();
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status='completed' AND invoice_id IS NOT NULL
               AND (cancellation_status IS NULL OR cancellation_status IN ('retry','awaiting_refund','detected','invoice_created'))
               AND cancellation_attempts < 30
             ORDER BY id ASC LIMIT %d", max(1, (int)$limit)
        ));
        $result = ['checked' => 0, 'cancelled' => 0, 'awaiting_refund' => 0, 'completed' => 0, 'retry' => 0, 'dry_run' => (bool)$dry_run];
        foreach ($rows as $row) {
            $result['checked']++;
            try {
                $order = self::get_order($row->order_id);
                $state = (string)($order['cancelStatus']['cancelState'] ?? 'NONE_REQUESTED');
                if ($state !== 'CANCELED') {
                    if (!$dry_run) $wpdb->update($table, [
                        'cancellation_state' => $state,
                        'cancellation_checked_at' => current_time('mysql'),
                        'cancellation_last_error' => null,
                    ], ['id' => $row->id]);
                    continue;
                }
                $result['cancelled']++;
                if (($order['orderPaymentStatus'] ?? '') !== 'FULLY_REFUNDED') {
                    $result['awaiting_refund']++;
                    if (!$dry_run) $wpdb->update($table, [
                        'cancellation_status' => 'awaiting_refund',
                        'cancellation_state' => 'CANCELED',
                        'cancellation_checked_at' => current_time('mysql'),
                        'cancelled_at' => self::mysql_time($order['cancelStatus']['cancelledDate'] ?? null),
                        'cancellation_last_error' => null,
                    ], ['id' => $row->id]);
                    continue;
                }
                if ($dry_run) continue;
                self::process_cancelled_order($row, $order);
                $result['completed']++;
            } catch (Throwable $e) {
                if (!$dry_run) self::mark_cancellation_retry($row->id, $e->getMessage());
                $result['retry']++;
            }
        }
        return $result;
    }

    public static function dry_run_order($order_id) {
        $order = self::get_order($order_id);
        $data = self::invoice_data($order);
        $note = self::buyer_note_analysis($order);
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
            'buyerNoteAction' => $note['action'],
            'buyerNoteEmailOverride' => $note['action'] === 'invoice_email',
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
        // A single lock also protects multi-order invoice grouping if the worker
        // is ever started twice outside the systemd flock.
        $lock_name = 'ppv_ebay_invoice_group';
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,10)', $lock_name)) !== 1) {
            throw new RuntimeException('Order lock timeout.');
        }
        try {
            $fresh = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $row->id));
            if (!$fresh || $fresh->status === 'completed') return;
            $order = self::get_order($fresh->order_id);
            $note = self::buyer_note_analysis($order);
            self::store_buyer_note($fresh->id, $note);
            if (($order['cancelStatus']['cancelState'] ?? '') === 'CANCELED') {
                $wpdb->update($table, [
                    'status' => 'cancelled_before_invoice',
                    'cancellation_status' => 'not_required',
                    'cancellation_state' => 'CANCELED',
                    'cancellation_checked_at' => current_time('mysql'),
                    'cancelled_at' => self::mysql_time($order['cancelStatus']['cancelledDate'] ?? null),
                    'last_error' => null,
                    'updated_at' => current_time('mysql'),
                ], ['id' => $fresh->id]);
                return;
            }
            if (($order['orderPaymentStatus'] ?? '') !== 'PAID') {
                throw new RuntimeException('Order payment is not cleared.');
            }
            $data = self::invoice_data($order);
            if ($data['currency'] !== 'EUR') throw new RuntimeException('Unsupported order currency.');
            if ($data['customer_email'] === '') throw new RuntimeException('Buyer email is not available yet.');

            $invoice_id = (int)$fresh->invoice_id;
            $members = [['row' => $fresh, 'data' => $data]];
            if (!$invoice_id) {
                $members = self::collect_invoice_group($fresh, $data);
                $combined = self::combine_invoice_data($members);
                $invoice_id = self::create_invoice($members, $combined);
                foreach ($members as $member) {
                    $wpdb->update($table, [
                        'invoice_id' => $invoice_id,
                        'status' => 'invoice_created',
                        'last_error' => null,
                        'updated_at' => current_time('mysql'),
                    ], ['id' => $member['row']->id]);
                }
            } elseif ($note['action'] === 'invoice_email' && is_email($note['email'])) {
                $wpdb->update($wpdb->prefix . 'ppv_repair_invoices', [
                    'customer_email' => $note['email'],
                ], [
                    'id' => $invoice_id,
                    'store_id' => self::STORE_ID,
                ]);
            }

            foreach ($members as $member) {
                $wpdb->update($table, [
                    'status' => 'email_sending',
                    'updated_at' => current_time('mysql'),
                ], ['id' => $member['row']->id]);
            }
            self::send_invoice($invoice_id);
            foreach ($members as $member) {
                $wpdb->update($table, [
                    'status' => 'completed',
                    'email_sent_at' => current_time('mysql'),
                    'last_error' => null,
                    'updated_at' => current_time('mysql'),
                ], ['id' => $member['row']->id]);
            }
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }

    private static function collect_invoice_group($primary_row, array $primary_data) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $members = [['row' => $primary_row, 'data' => $primary_data]];
        $primary_time = strtotime($primary_data['created_at'] ?? '');
        if (!$primary_time) return $members;
        $identity = self::invoice_identity($primary_data);
        $candidates = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE id<>%d AND invoice_id IS NULL
               AND status IN ('pending','retry') AND attempts < 30
             ORDER BY id ASC LIMIT %d",
            (int)$primary_row->id, 100
        ));
        foreach ($candidates as $candidate) {
            try {
                $order = self::get_order($candidate->order_id);
                $candidate_note = self::buyer_note_analysis($order);
                self::store_buyer_note($candidate->id, $candidate_note);
                if (($order['orderPaymentStatus'] ?? '') !== 'PAID') continue;
                if (($order['cancelStatus']['cancelState'] ?? '') === 'CANCELED') continue;
                $data = self::invoice_data($order);
                $created = strtotime($data['created_at'] ?? '');
                if (!$created || abs($created - $primary_time) > self::GROUP_WINDOW_SECONDS) continue;
                if ($data['currency'] !== 'EUR' || $data['customer_email'] === '') continue;
                if (self::invoice_identity($data) !== $identity) continue;
                $members[] = ['row' => $candidate, 'data' => $data];
            } catch (Throwable $e) {
                // A broken unrelated candidate must not block the current order.
                continue;
            }
        }
        usort($members, function($a, $b) {
            return strtotime($a['data']['created_at']) <=> strtotime($b['data']['created_at']);
        });
        return $members;
    }

    private static function invoice_identity(array $data) {
        $fields = [
            'customer_email', 'customer_name', 'customer_company',
            'customer_address', 'customer_plz', 'customer_city', 'customer_country',
        ];
        $values = [];
        foreach ($fields as $field) {
            $value = preg_replace('/\s+/u', ' ', trim((string)($data[$field] ?? '')));
            $values[] = mb_strtolower($value, 'UTF-8');
        }
        return hash('sha256', implode("\n", $values));
    }

    private static function combine_invoice_data(array $members) {
        $combined = $members[0]['data'];
        $combined['gross_total'] = 0.0;
        $combined['net_total'] = 0.0;
        $combined['vat_amount'] = 0.0;
        $combined['line_items'] = [];
        $combined['order_ids'] = [];
        foreach ($members as $member) {
            $combined['gross_total'] += (float)$member['data']['gross_total'];
            $combined['net_total'] += (float)$member['data']['net_total'];
            $combined['vat_amount'] += (float)$member['data']['vat_amount'];
            $combined['line_items'] = array_merge($combined['line_items'], $member['data']['line_items']);
            $combined['order_ids'][] = (string)$member['row']->order_id;
        }
        $combined['gross_total'] = round($combined['gross_total'], 2);
        $combined['net_total'] = round($combined['net_total'], 2);
        $combined['vat_amount'] = round($combined['vat_amount'], 2);
        return $combined;
    }

    private static function create_invoice(array $members, array $data) {
        global $wpdb;
        $invoice_table = $wpdb->prefix . 'ppv_repair_invoices';
        $store_table = $wpdb->prefix . 'ppv_stores';
        $queue_row = $members[0]['row'];
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
            $order_ids = $data['order_ids'] ?? [$queue_row->order_id];
            $order_label = count($order_ids) > 1 ? 'eBay-Bestellungen: ' : 'eBay-Bestellung: ';
            $notes = $order_label . implode(', ', $order_ids) . "\n" .
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

    private static function process_cancelled_order($row, array $order) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $lock_name = 'ppv_ebay_cancel_' . md5($row->order_id);
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,10)', $lock_name)) !== 1) {
            throw new RuntimeException('Cancellation lock timeout.');
        }
        try {
            $fresh = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $row->id));
            if (!$fresh || $fresh->cancellation_status === 'completed' || $fresh->cancellation_status === 'email_sending') return;
            $cancelled_at = self::mysql_time($order['cancelStatus']['cancelledDate'] ?? null);
            $shared_invoice_orders = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE invoice_id=%d",
                (int)$fresh->invoice_id
            ));
            if ($shared_invoice_orders > 1) {
                // A full automatic storno would incorrectly cancel the other
                // orders on the shared invoice. Keep it for manual correction.
                $wpdb->update($table, [
                    'cancellation_status' => 'manual_review',
                    'cancellation_state' => 'CANCELED',
                    'cancellation_checked_at' => current_time('mysql'),
                    'cancelled_at' => $cancelled_at,
                    'cancellation_last_error' => 'Grouped invoice requires a partial correction.',
                    'updated_at' => current_time('mysql'),
                ], ['id' => $fresh->id]);
                return;
            }
            $wpdb->update($table, [
                'cancellation_status' => 'detected',
                'cancellation_state' => 'CANCELED',
                'cancellation_checked_at' => current_time('mysql'),
                'cancelled_at' => $cancelled_at,
                'cancellation_last_error' => null,
            ], ['id' => $fresh->id]);

            $storno_id = self::create_cancellation_invoice($fresh, $cancelled_at);
            $wpdb->update($table, [
                'cancellation_invoice_id' => $storno_id,
                'cancellation_status' => 'invoice_created',
                'cancellation_last_error' => null,
            ], ['id' => $fresh->id]);
            $wpdb->update($table, ['cancellation_status' => 'email_sending'], ['id' => $fresh->id]);
            self::send_cancellation_invoice($storno_id, (int)$fresh->invoice_id);
            $wpdb->update($table, [
                'cancellation_status' => 'completed',
                'cancellation_email_sent_at' => current_time('mysql'),
                'cancellation_last_error' => null,
                'updated_at' => current_time('mysql'),
            ], ['id' => $fresh->id]);
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }

    private static function create_cancellation_invoice($queue_row, $cancelled_at) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_repair_invoices';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE store_id=%d AND external_source='ebay_storno' AND external_order_id=%s LIMIT 1",
            self::STORE_ID, $queue_row->order_id
        ));
        if ($existing) return (int)$existing;
        $original = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id=%d AND store_id=%d LIMIT 1",
            (int)$queue_row->invoice_id, self::STORE_ID
        ));
        if (!$original) throw new RuntimeException('Original invoice for cancellation is missing.');
        $items = json_decode($original->line_items ?: '[]', true);
        if (!is_array($items)) $items = [];
        foreach ($items as &$item) $item['amount'] = -abs((float)($item['amount'] ?? 0));
        unset($item);
        $number = 'ST-' . $original->invoice_number;
        $notes = 'Storniert die Rechnung ' . $original->invoice_number . ' vom ' . date('d.m.Y', strtotime($original->created_at)) . ".\n" .
            'eBay-Bestellung: ' . $queue_row->order_id . ".\n" .
            'Vollständige Aufhebung wegen Bestellstornierung.';
        $inserted = $wpdb->insert($table, [
            'store_id' => self::STORE_ID,
            'repair_id' => null,
            'invoice_number' => $number,
            'doc_type' => 'storno',
            'customer_name' => $original->customer_name,
            'customer_email' => $original->customer_email,
            'customer_phone' => $original->customer_phone,
            'customer_company' => $original->customer_company,
            'customer_address' => $original->customer_address,
            'customer_plz' => $original->customer_plz,
            'customer_city' => $original->customer_city,
            'device_info' => 'eBay-Bestellung',
            'description' => 'Vollständige Stornierung der ursprünglichen Rechnung',
            'line_items' => wp_json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'subtotal' => -abs((float)$original->subtotal),
            'discount_type' => 'none',
            'discount_value' => 0,
            'net_amount' => -abs((float)$original->net_amount),
            'vat_rate' => $original->vat_rate,
            'vat_amount' => -abs((float)$original->vat_amount),
            'total' => -abs((float)$original->total),
            'is_kleinunternehmer' => (int)$original->is_kleinunternehmer,
            'is_differenzbesteuerung' => (int)$original->is_differenzbesteuerung,
            'notes' => $notes,
            'status' => 'sent',
            'created_at' => $cancelled_at,
            'paid_at' => null,
            'payment_method' => 'ebay-refund',
            'external_source' => 'ebay_storno',
            'external_order_id' => $queue_row->order_id,
        ]);
        if (!$inserted) throw new RuntimeException('Cancellation invoice insert failed: ' . $wpdb->last_error);
        return (int)$wpdb->insert_id;
    }

    private static function send_cancellation_invoice($invoice_id, $original_invoice_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_repair_invoices';
        $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d AND store_id=%d", $invoice_id, self::STORE_ID));
        $original = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d AND store_id=%d", $original_invoice_id, self::STORE_ID));
        $store = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id=%d", self::STORE_ID));
        if (!$invoice || !$original || !$store || !is_email($invoice->customer_email)) throw new RuntimeException('Cancellation email data is missing.');
        if (class_exists('PPV_Lang')) { PPV_Lang::$active = 'de'; PPV_Lang::load('de'); }
        if (!class_exists('PPV_Repair_Invoice')) require_once PPV_PLUGIN_DIR . 'includes/class-ppv-repair-invoice.php';
        $method = new ReflectionMethod('PPV_Repair_Invoice', 'build_invoice_html');
        $method->setAccessible(true);
        $html = $method->invoke(null, $store, $invoice);
        require_once PPV_PLUGIN_DIR . 'libs/dompdf/autoload.inc.php';
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html); $dompdf->setPaper('A4', 'portrait'); $dompdf->render();
        $temp = wp_tempnam(sanitize_file_name($invoice->invoice_number) . '.pdf');
        if (!$temp || file_put_contents($temp, $dompdf->output()) === false) throw new RuntimeException('Temporary cancellation PDF could not be created.');
        try {
            $mail = self::new_mailer();
            $mail->setFrom('info@erepairshop.de', 'eRepairShop');
            $mail->addReplyTo('info@erepairshop.de', 'eRepairShop');
            $mail->addAddress($invoice->customer_email, $invoice->customer_name);
            $mail->Subject = 'Ihre Stornorechnung ' . $invoice->invoice_number . ' von eRepairShop';
            $mail->Body = "Guten Tag " . $invoice->customer_name . ",\n\n" .
                "Ihre eBay-Bestellung wurde vollständig storniert. Im Anhang erhalten Sie die Stornorechnung " . $invoice->invoice_number .
                " zur ursprünglichen Rechnung " . $original->invoice_number . ".\n\n" .
                "Freundliche Grüße\neRepairShop\nErik Borota";
            $mail->addAttachment($temp, sanitize_file_name($invoice->invoice_number) . '.pdf');
            $mail->send();
        } finally { @unlink($temp); }
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
        $note = self::buyer_note_analysis($order);
        if ($note['action'] === 'invoice_email' && is_email($note['email'])) {
            $email = $note['email'];
        }
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
            'customer_country' => strtoupper(sanitize_text_field($address['countryCode'] ?? '')),
        ];
    }

    private static function buyer_note_analysis(array $order) {
        $raw = trim((string)($order['buyerCheckoutNotes'] ?? ''));
        if ($raw === '') {
            return ['text' => '', 'hash' => null, 'action' => null, 'email' => null];
        }
        $text = sanitize_textarea_field($raw);
        $emails = [];
        if (preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $text, $matches)) {
            foreach ($matches[0] as $candidate) {
                $candidate = sanitize_email($candidate);
                if ($candidate !== '' && is_email($candidate)) $emails[strtolower($candidate)] = $candidate;
            }
        }
        $invoice_intent = preg_match('/\b(rechnung|invoice|sz[aá]mla)\b/iu', $text) === 1;
        $action = ($invoice_intent && count($emails) === 1) ? 'invoice_email' : 'notify_pending';
        return [
            'text' => $text,
            'hash' => hash('sha256', $text),
            'action' => $action,
            'email' => $action === 'invoice_email' ? reset($emails) : null,
        ];
    }

    private static function store_buyer_note($row_id, array $note) {
        global $wpdb;
        if ($note['text'] === '') return;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT buyer_note_hash,buyer_note_action,buyer_note_notified_at FROM {$table} WHERE id=%d",
            (int)$row_id
        ));
        if ($current && hash_equals((string)$current->buyer_note_hash, (string)$note['hash'])) return;
        $wpdb->update($table, [
            'buyer_note' => $note['text'],
            'buyer_note_hash' => $note['hash'],
            'buyer_note_action' => $note['action'],
            'buyer_note_email' => $note['email'],
            'buyer_note_notified_at' => null,
            'updated_at' => current_time('mysql'),
        ], ['id' => (int)$row_id]);
    }

    private static function send_buyer_note_ntfy($order_id, $note) {
        $safe = trim(preg_replace('/\s+/u', ' ', (string)$note));
        $safe = preg_replace('/([A-Z0-9._%+\-])[A-Z0-9._%+\-]*(@[A-Z0-9.\-]+\.[A-Z]{2,})/iu', '$1***$2', $safe);
        $safe = mb_substr($safe, 0, 700);
        $suffix = substr((string)$order_id, -8);
        $response = wp_remote_post('https://ntfy.sh/plizio-borota25-alerts', [
            'timeout' => 12,
            'headers' => [
                'Title' => 'eBay vevoi megjegyzes',
                'Priority' => 'high',
                'Tags' => 'package,memo',
                'Content-Type' => 'text/plain; charset=utf-8',
            ],
            'body' => 'Rendeles ...' . $suffix . ': ' . $safe,
        ]);
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) >= 200 && wp_remote_retrieve_response_code($response) < 300;
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

    private static function mark_cancellation_retry($row_id, $message) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $safe = preg_replace('/[\r\n]+/', ' ', (string)$message);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET cancellation_status='retry',cancellation_attempts=cancellation_attempts+1,cancellation_last_error=%s,cancellation_checked_at=%s,updated_at=%s WHERE id=%d",
            mb_substr($safe, 0, 1000), current_time('mysql'), current_time('mysql'), (int)$row_id
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
