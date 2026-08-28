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
    const SCHEMA_VERSION = '1.0.0';
    const DEFAULT_ORIGIN = 'https://shop.erepairshop.de';

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
        if (get_option('ppv_shop_chat_schema_version') === self::SCHEMA_VERSION
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $conversations)) === $conversations
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $messages)) === $messages) {
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
            if ($path === '/shop-chat-api/send' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                self::public_send();
            } elseif ($path === '/shop-chat-api/messages' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
                self::public_messages();
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
        if ($text === '' || mb_strlen($text) > 2000) {
            self::json_response(['ok' => false, 'message' => 'Bitte geben Sie eine Nachricht mit höchstens 2000 Zeichen ein.'], 400);
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
            $ok = $wpdb->insert($wpdb->prefix . self::CONVERSATIONS_SUFFIX, [
                'public_id' => $public_id,
                'access_hash' => hash('sha256', $new_token),
                'customer_name' => mb_substr($name, 0, 160),
                'customer_email' => mb_substr($email, 0, 255),
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

        $was_read = ((int)$conversation->unread_admin === 0);
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . $wpdb->prefix . self::CONVERSATIONS_SUFFIX . " SET unread_admin=unread_admin+1,status='open',last_message_at=%s,updated_at=%s WHERE id=%d",
            $now, $now, (int)$conversation->id
        ));
        if ($was_read) self::notify_admin($conversation, $text);

        self::json_response([
            'ok' => true,
            'conversation' => $conversation->public_id,
            'token' => $new_token ?: null,
            'messages' => self::messages_for_conversation((int)$conversation->id),
        ]);
    }

    private static function public_messages() {
        $conversation = self::conversation_from_credentials($_GET['conversation'] ?? '', $_GET['token'] ?? '');
        if (!$conversation) self::json_response(['ok' => false, 'message' => 'A beszélgetés nem található.'], 404);
        self::json_response([
            'ok' => true,
            'status' => $conversation->status,
            'messages' => self::messages_for_conversation((int)$conversation->id),
        ]);
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

    public static function messages_for_conversation($conversation_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id,sender,message,created_at FROM ' . $wpdb->prefix . self::MESSAGES_SUFFIX . ' WHERE conversation_id=%d ORDER BY id ASC LIMIT 300',
            (int)$conversation_id
        ));
        return array_map(static function ($row) {
            return [
                'id' => (int)$row->id,
                'sender' => $row->sender,
                'message' => $row->message,
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
        $ids = $wpdb->get_col("SELECT id FROM {$conversations} WHERE status='closed' AND updated_at < DATE_SUB(NOW(), INTERVAL 180 DAY) LIMIT 500");
        if (!$ids) return;
        $ids = array_map('intval', $ids);
        $list = implode(',', $ids);
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
