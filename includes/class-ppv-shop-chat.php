<?php
/**
 * Human operated eRepairShop live chat.
 *
 * The public widget talks to these endpoints from shop.erepairshop.de. Messages
 * stay in the PunktePass database so the standalone admin can answer them.
 */

if (!defined('ABSPATH')) exit;

final class PPV_Shop_Chat {
    const CONVERSATIONS_SUFFIX = 'ppv_shop_chat_conversations';
    const MESSAGES_SUFFIX = 'ppv_shop_chat_messages';
    const ATTACHMENTS_SUFFIX = 'ppv_shop_chat_attachments';
    const SCHEMA_VERSION = '1.2.0';
    const DEFAULT_ORIGIN = 'https://shop.erepairshop.de';
    const MAX_ATTACHMENT_BYTES = 5242880;
    const MAX_ATTACHMENTS_PER_MESSAGE = 3;
    const MAX_TOTAL_ATTACHMENT_BYTES = 10485760;

    public static function init() {
        add_action('init', [__CLASS__, 'dispatch_public_api'], 0);
        add_action('ppv_shop_chat_daily_cleanup', [__CLASS__, 'cleanup_old_conversations']);
        if (!wp_next_scheduled('ppv_shop_chat_daily_cleanup')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'ppv_shop_chat_daily_cleanup');
        }
    }

    public static function install_schema() {
        global $wpdb;
        $conversations = $wpdb->prefix . self::CONVERSATIONS_SUFFIX;
        $messages = $wpdb->prefix . self::MESSAGES_SUFFIX;
        $attachments = $wpdb->prefix . self::ATTACHMENTS_SUFFIX;
        if (get_option('ppv_shop_chat_schema_version') === self::SCHEMA_VERSION
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $conversations)) === $conversations
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $messages)) === $messages
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $attachments)) === $attachments) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$conversations} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id varchar(40) NOT NULL,
            access_hash char(64) NOT NULL,
            customer_name varchar(160) NOT NULL,
            customer_email varchar(255) NOT NULL,
            reply_channel varchar(20) NOT NULL DEFAULT 'chat',
            status varchar(20) NOT NULL DEFAULT 'open',
            unread_admin int(10) unsigned NOT NULL DEFAULT 0,
            last_message_at datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_public_id (public_id),
            KEY idx_status_last (status,last_message_at),
            KEY idx_unread (unread_admin,last_message_at)
        ) {$charset};");
        dbDelta("CREATE TABLE {$messages} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            sender varchar(20) NOT NULL,
            message text NOT NULL,
            page_url varchar(500) NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_conversation (conversation_id,id)
        ) {$charset};");
        dbDelta("CREATE TABLE {$attachments} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            message_id bigint(20) unsigned NOT NULL,
            conversation_id bigint(20) unsigned NOT NULL,
            uploader varchar(20) NOT NULL,
            file_name varchar(255) NOT NULL,
            mime_type varchar(100) NOT NULL,
            file_size bigint(20) unsigned NOT NULL,
            file_content longblob NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_message (message_id,id),
            KEY idx_conversation (conversation_id,id)
        ) {$charset};");
        if ($wpdb->last_error) {
            throw new RuntimeException('A webshop chat adatbázisa nem hozható létre.');
        }
        update_option('ppv_shop_chat_schema_version', self::SCHEMA_VERSION, false);
    }

    public static function dispatch_public_api() {
        $path = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        if (strpos($path, '/shop-chat-api') !== 0) return;

        self::send_public_headers();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            status_header(204);
            exit;
        }

        try {
            self::install_schema();
            if ($path === '/shop-chat-api/status' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
                self::public_status();
            } elseif ($path === '/shop-chat-api/send' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                self::public_send();
            } elseif ($path === '/shop-chat-api/messages' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
                self::public_messages();
            } elseif ($path === '/shop-chat-api/attachment' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
                self::public_attachment();
            } else {
                self::json_response(['ok' => false, 'message' => 'Ismeretlen végpont.'], 404);
            }
        } catch (Throwable $e) {
            error_log('[Shop Chat] ' . preg_replace('/[\r\n]+/', ' ', $e->getMessage()));
            self::json_response(['ok' => false, 'message' => 'Der Chat ist momentan nicht erreichbar.'], 500);
        }
    }

    private static function public_send() {
        self::rate_limit();
        $payload = self::request_payload();
        if (!empty($payload['website'])) self::json_response(['ok' => true], 200);

        $text = trim(sanitize_textarea_field($payload['message'] ?? ''));
        try {
            $attachments = self::uploaded_attachments('attachments');
        } catch (InvalidArgumentException $e) {
            self::json_response(['ok' => false, 'message' => $e->getMessage()], 400);
        }
        if (($text === '' && !$attachments) || mb_strlen($text) > 2000) {
            self::json_response(['ok' => false, 'message' => 'Bitte geben Sie eine Nachricht oder einen Anhang mit höchstens 2000 Zeichen ein.'], 400);
        }

        global $wpdb;
        $conversation = self::conversation_from_credentials($payload['conversation'] ?? '', $payload['token'] ?? '');
        $new_token = '';
        if (!$conversation) {
            $name = trim(sanitize_text_field($payload['name'] ?? ''));
            $email = sanitize_email($payload['email'] ?? '');
            if ($name === '' || !$email) {
                self::json_response(['ok' => false, 'message' => 'Bitte geben Sie Ihren Namen und Ihre E Mail Adresse ein.'], 400);
            }
            $public_id = wp_generate_uuid4();
            $new_token = bin2hex(random_bytes(32));
            $now = current_time('mysql');
            $reply_channel = self::is_online_now() ? 'chat' : 'email';
            $ok = $wpdb->insert($wpdb->prefix . self::CONVERSATIONS_SUFFIX, [
                'public_id' => $public_id,
                'access_hash' => hash('sha256', $new_token),
                'customer_name' => mb_substr($name, 0, 160),
                'customer_email' => mb_substr($email, 0, 255),
                'reply_channel' => $reply_channel,
                'status' => 'open',
                'unread_admin' => 0,
                'last_message_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!$ok) throw new RuntimeException('Conversation insert failed.');
            $conversation = $wpdb->get_row($wpdb->prepare(
                'SELECT * FROM ' . $wpdb->prefix . self::CONVERSATIONS_SUFFIX . ' WHERE id=%d',
                $wpdb->insert_id
            ));
        }

        $reply_channel = self::is_online_now() ? 'chat' : 'email';
        $page_url = esc_url_raw($payload['pageUrl'] ?? '');
        if ($page_url !== '' && strpos($page_url, self::allowed_origin()) !== 0) $page_url = '';
        $now = current_time('mysql');
        $ok = $wpdb->insert($wpdb->prefix . self::MESSAGES_SUFFIX, [
            'conversation_id' => (int)$conversation->id,
            'sender' => 'customer',
            'message' => $text,
            'page_url' => mb_substr($page_url, 0, 500),
            'created_at' => $now,
        ]);
        if (!$ok) throw new RuntimeException('Message insert failed.');
        $message_id = (int)$wpdb->insert_id;
        try {
            self::store_attachments($message_id, (int)$conversation->id, 'customer', $attachments);
        } catch (Throwable $e) {
            $wpdb->delete($wpdb->prefix . self::MESSAGES_SUFFIX, ['id' => $message_id], ['%d']);
            throw $e;
        }

        if ($new_token !== '') {
            $automatic_reply = $reply_channel === 'chat'
                ? 'Vielen Dank für Ihre Nachricht. Wir verbinden Sie mit einem Mitarbeiter. Bitte haben Sie einen Moment Geduld.'
                : 'Vielen Dank für Ihre Nachricht. Wir sind derzeit offline und antworten Ihnen per E Mail.';
            $wpdb->insert($wpdb->prefix . self::MESSAGES_SUFFIX, [
                'conversation_id' => (int)$conversation->id,
                'sender' => 'admin',
                'message' => $automatic_reply,
                'page_url' => '',
                'created_at' => $now,
            ]);
        }

        $was_read = ((int)$conversation->unread_admin === 0);
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . $wpdb->prefix . self::CONVERSATIONS_SUFFIX . " SET unread_admin=unread_admin+1,status='open',reply_channel=%s,last_message_at=%s,updated_at=%s WHERE id=%d",
            $reply_channel, $now, $now, (int)$conversation->id
        ));
        if ($was_read) {
            $preview = $text !== '' ? $text : 'Csatolmány: ' . implode(', ', wp_list_pluck($attachments, 'name'));
            self::notify_admin($conversation, $preview);
        }

        self::json_response([
            'ok' => true,
            'conversation' => $conversation->public_id,
            'token' => $new_token ?: null,
            'online' => self::is_online_now(),
            'replyChannel' => $reply_channel,
            'messages' => self::messages_for_conversation((int)$conversation->id),
        ]);
    }

    private static function public_messages() {
        $conversation = self::conversation_from_credentials($_GET['conversation'] ?? '', $_GET['token'] ?? '');
        if (!$conversation) self::json_response(['ok' => false, 'message' => 'A beszélgetés nem található.'], 404);
        self::json_response([
            'ok' => true,
            'status' => $conversation->status,
            'online' => self::is_online_now(),
            'replyChannel' => $conversation->reply_channel,
            'messages' => self::messages_for_conversation((int)$conversation->id),
        ]);
    }

    private static function public_attachment() {
        $conversation = self::conversation_from_credentials($_GET['conversation'] ?? '', $_GET['token'] ?? '');
        $attachment_id = (int)($_GET['id'] ?? 0);
        if (!$conversation || $attachment_id <= 0 || !self::stream_attachment($attachment_id, (int)$conversation->id)) {
            self::json_response(['ok' => false, 'message' => 'Der Anhang wurde nicht gefunden.'], 404);
        }
    }

    private static function public_status() {
        self::json_response([
            'ok' => true,
            'online' => self::is_online_now(),
            'availabilityMode' => self::availability_mode(),
            'timezone' => 'Europe/Berlin',
            'onlineDays' => 'Mo-Fr',
            'onlineFrom' => '09:00',
            'onlineUntil' => '16:00',
        ]);
    }

    public static function is_online_now() {
        $mode = self::availability_mode();
        if ($mode === 'online') return true;
        if ($mode === 'offline') return false;
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
        if ((int)$now->format('N') > 5) return false;
        $minutes = ((int)$now->format('G') * 60) + (int)$now->format('i');
        return $minutes >= 540 && $minutes < 960;
    }

    public static function availability_mode() {
        $mode = sanitize_key((string)get_option('ppv_shop_chat_availability_mode', 'auto'));
        return in_array($mode, ['auto', 'online', 'offline'], true) ? $mode : 'auto';
    }

    public static function set_availability_mode($mode) {
        $mode = sanitize_key((string)$mode);
        if (!in_array($mode, ['auto', 'online', 'offline'], true)) return false;
        return update_option('ppv_shop_chat_availability_mode', $mode, false) !== false
            || self::availability_mode() === $mode;
    }

    public static function send_email_reply($conversation, $message, array $attachments = []) {
        if (!defined('ERS_PPV_BRIDGE_SECRET') || strlen((string)ERS_PPV_BRIDGE_SECRET) < 32) {
            throw new RuntimeException('A chat email híd nincs beállítva.');
        }
        $body = wp_json_encode([
            'email' => $conversation->customer_email,
            'name' => $conversation->customer_name,
            'message' => $message,
            'conversation' => $conversation->public_id,
            'attachments' => array_map(static function ($attachment) {
                return [
                    'name' => $attachment['name'],
                    'mime' => $attachment['mime'],
                    'size' => (int)$attachment['size'],
                    'content' => base64_encode($attachment['content']),
                ];
            }, $attachments),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string)time();
        $response = wp_remote_post('https://shop.erepairshop.de/?ers-shop-chat-email=1', [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'X-ERS-Timestamp' => $timestamp,
                'X-ERS-Signature' => hash_hmac('sha256', $timestamp . "\n" . $body, (string)ERS_PPV_BRIDGE_SECRET),
            ],
            'body' => $body,
        ]);
        if (is_wp_error($response)) throw new RuntimeException($response->get_error_message());
        $status = (int)wp_remote_retrieve_response_code($response);
        $payload = json_decode(wp_remote_retrieve_body($response), true);
        if ($status !== 200 || empty($payload['ok'])) {
            throw new RuntimeException('A chat válasz emailje nem küldhető el.');
        }
        return true;
    }

    public static function conversation_from_credentials($public_id, $token) {
        $public_id = sanitize_text_field((string)$public_id);
        $token = preg_replace('/[^a-f0-9]/i', '', (string)$token);
        if ($public_id === '' || strlen($token) !== 64) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . $wpdb->prefix . self::CONVERSATIONS_SUFFIX . ' WHERE public_id=%s',
            $public_id
        ));
        if (!$row || !hash_equals((string)$row->access_hash, hash('sha256', strtolower($token)))) return null;
        return $row;
    }

    public static function uploaded_attachments($field = 'attachments') {
        if (empty($_FILES[$field]) || !isset($_FILES[$field]['name'])) return [];
        $files = $_FILES[$field];
        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $types = is_array($files['type']) ? $files['type'] : [$files['type']];
        $temporary = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
        $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];
        $count = count(array_filter($errors, static function ($error) { return (int)$error !== UPLOAD_ERR_NO_FILE; }));
        if ($count > self::MAX_ATTACHMENTS_PER_MESSAGE) {
            throw new InvalidArgumentException('Pro Nachricht sind höchstens drei Anhänge erlaubt.');
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        $result = [];
        $total = 0;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) throw new InvalidArgumentException('Der Dateityp konnte nicht geprüft werden.');
        try {
            foreach ($names as $index => $original_name) {
                $error = (int)($errors[$index] ?? UPLOAD_ERR_NO_FILE);
                if ($error === UPLOAD_ERR_NO_FILE) continue;
                if ($error !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Der Anhang konnte nicht hochgeladen werden.');
                $tmp = (string)($temporary[$index] ?? '');
                $size = (int)($sizes[$index] ?? 0);
                if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0 || $size > self::MAX_ATTACHMENT_BYTES) {
                    throw new InvalidArgumentException('Jeder Anhang darf höchstens 5 MB groß sein.');
                }
                $total += $size;
                if ($total > self::MAX_TOTAL_ATTACHMENT_BYTES) {
                    throw new InvalidArgumentException('Die Anhänge dürfen zusammen höchstens 10 MB groß sein.');
                }
                $mime = (string)finfo_file($finfo, $tmp);
                if (!isset($allowed[$mime])) {
                    throw new InvalidArgumentException('Erlaubt sind JPG, PNG, WEBP und PDF.');
                }
                if (strpos($mime, 'image/') === 0) {
                    $image = @getimagesize($tmp);
                    if (!$image || empty($image[0]) || empty($image[1]) || ((int)$image[0] * (int)$image[1]) > 40000000) {
                        throw new InvalidArgumentException('Die Bilddatei ist ungültig oder zu groß.');
                    }
                } elseif (file_get_contents($tmp, false, null, 0, 5) !== '%PDF-') {
                    throw new InvalidArgumentException('Die PDF Datei ist ungültig.');
                }
                $content = file_get_contents($tmp);
                if ($content === false || strlen($content) !== $size) {
                    throw new InvalidArgumentException('Der Anhang konnte nicht gelesen werden.');
                }
                $safe_name = sanitize_file_name(wp_unslash((string)$original_name));
                $base = pathinfo($safe_name, PATHINFO_FILENAME);
                if ($base === '') $base = $mime === 'application/pdf' ? 'Dokument' : 'Bild';
                $result[] = [
                    'name' => mb_substr($base, 0, 180) . '.' . $allowed[$mime],
                    'mime' => $mime,
                    'size' => $size,
                    'content' => $content,
                ];
            }
        } finally {
            finfo_close($finfo);
        }
        return $result;
    }

    public static function store_attachments($message_id, $conversation_id, $uploader, array $attachments) {
        if (!$attachments) return;
        global $wpdb;
        $table = $wpdb->prefix . self::ATTACHMENTS_SUFFIX;
        $stored = [];
        foreach ($attachments as $attachment) {
            $ok = $wpdb->insert($table, [
                'message_id' => (int)$message_id,
                'conversation_id' => (int)$conversation_id,
                'uploader' => $uploader === 'admin' ? 'admin' : 'customer',
                'file_name' => (string)$attachment['name'],
                'mime_type' => (string)$attachment['mime'],
                'file_size' => (int)$attachment['size'],
                'file_content' => $attachment['content'],
                'created_at' => current_time('mysql'),
            ]);
            if (!$ok) {
                if ($stored) $wpdb->query('DELETE FROM ' . $table . ' WHERE id IN (' . implode(',', array_map('intval', $stored)) . ')');
                throw new RuntimeException('Attachment insert failed.');
            }
            $stored[] = (int)$wpdb->insert_id;
        }
    }

    public static function stream_attachment($attachment_id, $conversation_id = 0) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . $wpdb->prefix . self::ATTACHMENTS_SUFFIX . ' WHERE id=%d',
            (int)$attachment_id
        ));
        if (!$row || ($conversation_id > 0 && (int)$row->conversation_id !== (int)$conversation_id)) return false;
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($row->mime_type, $allowed, true)) return false;
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$row->file_name);
        if ($fallback === '') $fallback = 'attachment';
        nocache_headers();
        header('Content-Type: ' . $row->mime_type);
        header('Content-Length: ' . strlen($row->file_content));
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        $disposition = strpos($row->mime_type, 'image/') === 0 ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disposition . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($row->file_name));
        echo $row->file_content;
        exit;
    }

    public static function messages_for_conversation($conversation_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id,sender,message,created_at FROM ' . $wpdb->prefix . self::MESSAGES_SUFFIX . ' WHERE conversation_id=%d ORDER BY id ASC LIMIT 300',
            (int)$conversation_id
        ));
        $attachment_rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id,message_id,file_name,mime_type,file_size FROM ' . $wpdb->prefix . self::ATTACHMENTS_SUFFIX . ' WHERE conversation_id=%d ORDER BY id ASC',
            (int)$conversation_id
        ));
        $by_message = [];
        foreach ($attachment_rows ?: [] as $attachment) {
            $by_message[(int)$attachment->message_id][] = [
                'id' => (int)$attachment->id,
                'name' => $attachment->file_name,
                'mime' => $attachment->mime_type,
                'size' => (int)$attachment->file_size,
            ];
        }
        return array_map(static function ($row) use ($by_message) {
            return [
                'id' => (int)$row->id,
                'sender' => $row->sender,
                'message' => $row->message,
                'attachments' => $by_message[(int)$row->id] ?? [],
                'createdAt' => mysql_to_rfc3339($row->created_at),
            ];
        }, $rows ?: []);
    }

    private static function notify_admin($conversation, $text) {
        if (!defined('PPV_SHOP_CHAT_NTFY_URL') || !PPV_SHOP_CHAT_NTFY_URL) return;
        $preview = mb_substr(preg_replace('/\s+/', ' ', $text), 0, 220);
        wp_remote_post(PPV_SHOP_CHAT_NTFY_URL, [
            'timeout' => 8,
            'headers' => [
                'Title' => 'Új eRepairShop chat üzenet',
                'Priority' => 'high',
                'Tags' => 'speech_balloon,shopping_cart',
                'Click' => 'https://punktepass.de/admin/shop-chat?conversation=' . rawurlencode($conversation->public_id),
                'Content-Type' => 'text/plain; charset=utf-8',
            ],
            'body' => $conversation->customer_name . ': ' . $preview,
        ]);
    }

    public static function cleanup_old_conversations() {
        self::install_schema();
        global $wpdb;
        $conversations = $wpdb->prefix . self::CONVERSATIONS_SUFFIX;
        $messages = $wpdb->prefix . self::MESSAGES_SUFFIX;
        $attachments = $wpdb->prefix . self::ATTACHMENTS_SUFFIX;
        $ids = $wpdb->get_col("SELECT id FROM {$conversations} WHERE status='closed' AND updated_at < DATE_SUB(NOW(), INTERVAL 180 DAY) LIMIT 500");
        if (!$ids) return;
        $ids = array_map('intval', $ids);
        $list = implode(',', $ids);
        $wpdb->query("DELETE FROM {$attachments} WHERE conversation_id IN ({$list})");
        $wpdb->query("DELETE FROM {$messages} WHERE conversation_id IN ({$list})");
        $wpdb->query("DELETE FROM {$conversations} WHERE id IN ({$list})");
    }

    private static function request_payload() {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        return is_array($json) ? $json : wp_unslash($_POST);
    }

    private static function rate_limit() {
        $candidate = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ip = filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : 'unknown';
        $key = 'ppv_chat_rate_' . hash('sha256', $ip);
        $count = (int)get_transient($key);
        if ($count >= 12) self::json_response(['ok' => false, 'message' => 'Bitte warten Sie kurz und versuchen Sie es erneut.'], 429);
        set_transient($key, $count + 1, MINUTE_IN_SECONDS);
    }

    private static function send_public_headers() {
        $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin === self::allowed_origin()) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
    }

    private static function allowed_origin() {
        return defined('PPV_SHOP_CHAT_ORIGIN') && PPV_SHOP_CHAT_ORIGIN
            ? rtrim(PPV_SHOP_CHAT_ORIGIN, '/')
            : self::DEFAULT_ORIGIN;
    }

    private static function json_response($payload, $status = 200) {
        status_header($status);
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
