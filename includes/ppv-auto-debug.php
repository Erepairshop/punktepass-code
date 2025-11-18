<?php
/**
 * PunktePass Auto Debug v5.0 (Stable)
 * Teljes körű hibalogoló rendszer (PHP + JS + REST + AJAX + SESSION + USER-Interaction + Smart Trace)
 * Log: /includes/log/ppv-debug.log
 */

if (!defined('ABSPATH')) exit;
if (class_exists('PPV_Auto_Debug')) return;

class PPV_Auto_Debug {

    private static $log_dir;
    private static $log_file;
    private static $daily_summary = [];
    private static $session_data = [];
    private static $initialized = false;

    /* ============================================================
     * 🔹 ENABLE DEBUG SYSTEM
     * ============================================================ */
    public static function enable() {
        if (self::$initialized || defined('PPV_DEBUG_LOADED')) return;
        define('PPV_DEBUG_LOADED', true);
        self::$initialized = true;

        self::$log_dir  = plugin_dir_path(__FILE__) . 'log/';
        self::$log_file = self::$log_dir . 'ppv-debug.log';
        if (!file_exists(self::$log_dir)) @mkdir(self::$log_dir, 0755, true);

        ini_set('log_errors', 1);
        ini_set('error_log', self::$log_file);
        error_reporting(E_ALL);

        set_error_handler([__CLASS__, 'handleError']);
        set_exception_handler([__CLASS__, 'handleException']);
        register_shutdown_function([__CLASS__, 'handleShutdown']);

        add_action('init', [__CLASS__, 'capture_session']);
        add_action('rest_api_init', [__CLASS__, 'register_rest']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_js_logger']);
        add_filter('rest_pre_dispatch', [__CLASS__, 'log_rest_request'], 10, 3);

        self::log('🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)');
    }

    /* ============================================================
     * 🔹 AUTO STACKTRACE + AI-DIAGNOSIS
     * ============================================================ */
    public static function handleError($errno, $errstr, $errfile, $errline) {
        $msg = "⚠️ PHP Error: $errstr in $errfile:$errline";
        self::log($msg);
        self::$daily_summary['php_errors'][] = $msg;

        // 🔸 Automatikus híváslánc
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $stack = array_map(function($t){
            $f = basename($t['file'] ?? '');
            $l = $t['line'] ?? '?';
            $fn = $t['function'] ?? '(anonymous)';
            return "$fn() in $f:$l";
        }, array_slice($trace, 0, 6));
        self::log("🧭 STACK TRACE:\n- " . implode("\n- ", $stack));

        self::auto_analyze_error($errstr, $errfile, $errline);
        return false;
    }

    public static function handleException($ex) {
        $msg = "💥 Exception: {$ex->getMessage()} in {$ex->getFile()}:{$ex->getLine()}";
        self::log($msg);
        self::$daily_summary['exceptions'][] = $msg;

        $trace = $ex->getTrace();
        $stack = array_map(function($t){
            $f = basename($t['file'] ?? '');
            $l = $t['line'] ?? '?';
            $fn = $t['function'] ?? '(unknown)';
            return "$fn() in $f:$l";
        }, array_slice($trace, 0, 6));
        self::log("🧭 STACK TRACE:\n- " . implode("\n- ", $stack));

        self::auto_analyze_error($ex->getMessage(), $ex->getFile(), $ex->getLine());
    }

    /* ============================================================
     * 🔹 SHUTDOWN HANDLER
     * ============================================================ */
    public static function handleShutdown() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $msg = "❌ FATAL ERROR: {$error['message']} in {$error['file']}:{$error['line']}";
            self::log($msg);
            self::$daily_summary['fatal'][] = $msg;
        }
        self::diagnose();
    }

    public static function auto_analyze_error($msg, $file, $line) {
        $patterns = [
            'Call to undefined function' => '❌ Hiányzó függvény – valószínűleg nem lett betöltve a megfelelő include.',
            'Undefined variable'         => '⚠️ Ismeretlen változó – lehet, hogy az $GLOBALS vagy $_SESSION nincs inicializálva.',
            'Cannot modify header'       => '⚠️ Output már elindult – session_start() vagy echo túl korán futott.',
            'Class .* not found'         => '❌ Hiányzó class – hibás include sorrend vagy require_once hiányzik.',
            'syntax error'               => '💥 Szintaxis hiba – ellenőrizd az utolsó szerkesztett PHP blokkot.',
            'headers already sent'       => '⚠️ Fejlécek már kiküldve – whitespace vagy UTF-8 BOM probléma.',
            'Maximum execution time'     => '🐢 Túl hosszú futás – végtelen ciklus vagy lassú query.',
            'Allowed memory size'        => '💾 Memória kimerült – optimalizáld a lekérdezéseket vagy csökkentsd az adatmennyiséget.',
        ];
        foreach ($patterns as $key => $desc) {
            if (stripos($msg, $key) !== false) {
                self::log("🧩 [AUTO-DIAG] $desc ($file:$line)");
                return;
            }
        }
        self::log("🧩 [AUTO-DIAG] Nem ismert hibatípus – kézi ellenőrzés szükséges ($file:$line)");
    }

    /* ============================================================
     * 🔹 SESSION TRACKING
     * ============================================================ */
    public static function capture_session() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) @session_start();
        self::$session_data = [
            'user_id'  => $_SESSION['ppv_user_id']  ?? (get_current_user_id() ?: 0),
            'store_id' => $_SESSION['ppv_store_id'] ?? 0,
            'pos'      => $_SESSION['ppv_is_pos']   ?? false
        ];
        self::log('🧩 SESSION STATE → ' . json_encode(self::$session_data));
    }

    /* ============================================================
     * 🔹 REST LOGGING
     * ============================================================ */
    public static function register_rest() {
        register_rest_route('ppv/v1', '/log/js_error', [
            'methods' => 'POST', 'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'receive_js_error']
        ]);

        register_rest_route('ppv/v1', '/log/ajax', [
            'methods' => 'POST', 'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'receive_ajax_log']
        ]);
    }

    public static function receive_js_error($req) {
        $data = $req->get_json_params() ?: [];
        self::log("🧩 JS Error: {$data['message']} | {$data['file']} ({$data['line']})");
        return ['ok' => true];
    }

    public static function receive_ajax_log($req) {
        $data = $req->get_json_params() ?: [];
        $session = sprintf(
            "[user_id=%d | store_id=%d | pos=%s]",
            self::$session_data['user_id'] ?? 0,
            self::$session_data['store_id'] ?? 0,
            (self::$session_data['pos'] ?? false) ? 'yes' : 'no'
        );
        $url = $data['url'] ?? '(no url)';
        $msg = "📡 AJAX/REST Log → {$url}\n{$session}\n" . substr(json_encode($data, JSON_PRETTY_PRINT), 0, 800);
        self::log($msg);
        return ['ok' => true];
    }

    public static function log_rest_request($result, $server, $request) {
        $route = $request->get_route();
        if (strpos($route, '/ppv/v1/log/') !== false) return $result;
        $params = $request->get_json_params();
        $user_id = get_current_user_id() ?: ($_SESSION['ppv_user_id'] ?? 0);
        self::log("🧠 REST CALL → {$route} | User={$user_id} | Params=" . json_encode($params));
        return $result;
    }

    /* ============================================================
     * 🔹 JS LOGGER
     * ============================================================ */
    public static function enqueue_js_logger() {
        wp_register_script('ppv-js-logger-v33', plugins_url('../assets/js/ppv-js-logger-v33.js', __FILE__), ['jquery'], time(), true);
        wp_enqueue_script('ppv-js-logger-v33');
        $data = [
            'js_url'   => rest_url('ppv/v1/log/js_error'),
            'ajax_url' => rest_url('ppv/v1/log/ajax')
        ];
        wp_add_inline_script('ppv-js-logger-v33', "window.PPV_LOG_API = " . wp_json_encode($data) . ";", 'before');
    }

    /* ============================================================
     * 🔹 HELPER FUNKCIÓK
     * ============================================================ */
    public static function log($message) {
        $time = date('Y-m-d H:i:s');
        $entry = "[$time] $message\n---------------------------------------------------\n";
        file_put_contents(self::$log_file, $entry, FILE_APPEND);
    }

    private static function diagnose() {
        $time = date('Y-m-d H:i:s');
        $summary = "\n📊 DAILY SUMMARY ($time)\n";
        foreach (self::$daily_summary as $type => $arr) {
            $summary .= "- $type: " . count($arr) . "\n";
        }
        $summary .= "---------------------------------------------------\n";
        file_put_contents(self::$log_file, $summary, FILE_APPEND);
    }
}

// ============================================================
// ✅ Aktiválás
// ============================================================
add_action('plugins_loaded', ['PPV_Auto_Debug', 'enable']);
add_action('init', ['PPV_Auto_Debug', 'run_health_checks']);

/**
 * ============================================================
 *  🧠 SMART TRACE MODE – minden PHP hívás automatikusan felismeri a forrást
 * ============================================================
 */
add_action('init', function () {
    if (!class_exists('PPV_Auto_Debug') || defined('PPV_SMART_TRACE_ACTIVE')) return;
    define('PPV_SMART_TRACE_ACTIVE', true);

    PPV_Auto_Debug::log('🧠 SMART TRACE MODE aktiviert');

    // 🔹 Globális trace helper
    if (!function_exists('ppv_trace')) {
        function ppv_trace($context = null) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
            $origin = $trace[1] ?? $trace[0] ?? [];
            $class  = $origin['class']  ?? '(no class)';
            $func   = $origin['function'] ?? '(no func)';
            $file   = basename($origin['file'] ?? '(no file)');
            $line   = $origin['line']   ?? 0;

            $session = sprintf(
                "[user=%s | store=%s | pos=%s]",
                $_SESSION['ppv_user_id'] ?? get_current_user_id() ?? '–',
                $_SESSION['ppv_store_id'] ?? '–',
                ($_SESSION['ppv_is_pos'] ?? false) ? 'yes' : 'no'
            );

            $msg = "🧩 TRACE → {$class}::{$func}() in {$file}:{$line} {$session}";
            if ($context !== null) {
                $msg .= " | Context: " . (is_array($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : $context);
            }
            PPV_Auto_Debug::log($msg);
        }
    }

    // 🔹 Automatikus hook-figyelés
    add_action('all', function ($tag = null) {
        static $last = null;
        if ($tag === $last) return;
        $last = $tag;
        if (strpos($tag, 'wp_') === 0 || strpos($tag, 'ppv_') === 0) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $origin = basename($bt[0]['file'] ?? '(no file)');
            PPV_Auto_Debug::log("🔁 HOOK → {$tag} (source: {$origin})");
        }
    });
});
