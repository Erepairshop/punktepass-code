<?php
/**
 * PunktePass Remote Service
 *
 * Client-side error logging + device heartbeat/diagnostics + remote action endpoint.
 * Lets admins remotely see device health and act on issues (scan-failed, permission-blocked, offline).
 */

if (!defined('ABSPATH')) exit;

class PPV_Remote_Service {

    public static function hooks() {
        add_action('init', [__CLASS__, 'ensure_tables'], 5);
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_telemetry']);
        // Fallback: direct footer print for pages that bypass enqueue
        add_action('wp_footer', [__CLASS__, 'print_footer_telemetry'], 999);
        // PP standalone pages: use output buffer to inject before </body>
        add_action('init', [__CLASS__, 'start_output_buffer'], 1);
        add_action('shutdown', [__CLASS__, 'flush_output_buffer'], 1);
        // AJAX nopriv fallback (in case REST blocked by auth filter)
        add_action('wp_ajax_ppv_client_error', [__CLASS__, 'ajax_log_error']);
        add_action('wp_ajax_nopriv_ppv_client_error', [__CLASS__, 'ajax_log_error']);
        add_action('wp_ajax_ppv_device_heartbeat', [__CLASS__, 'ajax_heartbeat']);
        add_action('wp_ajax_nopriv_ppv_device_heartbeat', [__CLASS__, 'ajax_heartbeat']);
        add_action('wp_ajax_ppv_pending_actions', [__CLASS__, 'ajax_pending_actions']);
        add_action('wp_ajax_nopriv_ppv_pending_actions', [__CLASS__, 'ajax_pending_actions']);
        add_action('wp_ajax_ppv_action_result', [__CLASS__, 'ajax_action_result']);
        add_action('wp_ajax_nopriv_ppv_action_result', [__CLASS__, 'ajax_action_result']);
    }

    private static function make_json_req($method = 'POST') {
        $req = new WP_REST_Request($method);
        $raw = file_get_contents('php://input');
        $req->set_body($raw);
        $req->add_header('Content-Type', 'application/json');
        // Manually parse + prime body params so get_json_params() works
        if ($raw) {
            $parsed = json_decode($raw, true);
            if (is_array($parsed)) {
                foreach ($parsed as $k => $v) $req->set_param($k, $v);
            }
        }
        return $req;
    }
    public static function ajax_log_error() {
        $req = self::make_json_req('POST');
        foreach ($_POST as $k => $v) $req->set_param($k, $v);
        self::rest_log_error($req);
        wp_send_json(['ok' => true]);
    }
    public static function ajax_heartbeat() {
        $req = self::make_json_req('POST');
        self::rest_heartbeat($req);
        wp_send_json(['ok' => true]);
    }
    public static function ajax_pending_actions() {
        $req = new WP_REST_Request('GET');
        foreach ($_GET as $k => $v) $req->set_param($k, $v);
        wp_send_json(self::rest_pending_actions($req));
    }
    public static function ajax_action_result() {
        $req = self::make_json_req('POST');
        wp_send_json(self::rest_action_result($req));
    }

    public static function ensure_tables() {
        global $wpdb;

        if (get_option('ppv_remote_service_db_v1')) return;

        $charset = $wpdb->get_charset_collate();
        $p = $wpdb->prefix;

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}ppv_client_errors (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            store_id BIGINT UNSIGNED NULL,
            email VARCHAR(255) NULL,
            url VARCHAR(500) NULL,
            message TEXT NULL,
            stack TEXT NULL,
            user_agent VARCHAR(500) NULL,
            severity VARCHAR(20) DEFAULT 'error',
            context TEXT NULL,
            acknowledged TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user (user_id),
            KEY idx_store (store_id),
            KEY idx_created (created_at),
            KEY idx_ack (acknowledged)
        ) $charset;");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}ppv_device_diagnostics (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            store_id BIGINT UNSIGNED NULL,
            device_fingerprint VARCHAR(100) NULL,
            user_agent VARCHAR(500) NULL,
            sw_version VARCHAR(50) NULL,
            screen VARCHAR(50) NULL,
            lang VARCHAR(10) NULL,
            online TINYINT(1) DEFAULT 1,
            camera_permission VARCHAR(20) NULL,
            notification_permission VARCHAR(20) NULL,
            battery INT NULL,
            connection VARCHAR(20) NULL,
            last_url VARCHAR(500) NULL,
            extra TEXT NULL,
            last_heartbeat DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_fp (user_id, device_fingerprint),
            KEY idx_heartbeat (last_heartbeat)
        ) $charset;");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}ppv_remote_actions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            device_fingerprint VARCHAR(100) NULL,
            action VARCHAR(50) NOT NULL,
            payload TEXT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            issued_by BIGINT UNSIGNED NULL,
            issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            executed_at DATETIME NULL,
            result TEXT NULL,
            KEY idx_target (user_id, device_fingerprint, status),
            KEY idx_status (status)
        ) $charset;");

        update_option('ppv_remote_service_db_v1', true);
    }

    public static function register_routes() {
        register_rest_route('punktepass/v1', '/client-error', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_log_error'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('punktepass/v1', '/device/heartbeat', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_heartbeat'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('punktepass/v1', '/device/pending-actions', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_pending_actions'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('punktepass/v1', '/device/action-result', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_action_result'],
            'permission_callback' => '__return_true'
        ]);
    }

    public static function rest_log_error(WP_REST_Request $req) {
        global $wpdb;
        $b = $req->get_json_params() ?: json_decode($req->get_body(), true) ?: [];
        if (session_status() === PHP_SESSION_NONE) ppv_maybe_start_session();
        $user_id = intval($_SESSION['ppv_user_id'] ?? 0);
        $store_id = intval($_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_store_id'] ?? 0);
        $email = sanitize_email($_SESSION['ppv_email'] ?? '');

        $wpdb->insert("{$wpdb->prefix}ppv_client_errors", [
            'user_id' => $user_id ?: null,
            'store_id' => $store_id ?: null,
            'email' => $email ?: null,
            'url' => substr(sanitize_text_field($b['url'] ?? ''), 0, 500),
            'message' => substr(sanitize_textarea_field($b['message'] ?? ''), 0, 2000),
            'stack' => substr(sanitize_textarea_field($b['stack'] ?? ''), 0, 5000),
            'user_agent' => substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            'severity' => sanitize_text_field($b['severity'] ?? 'error'),
            'context' => wp_json_encode($b['context'] ?? null),
            'created_at' => current_time('mysql'),
        ]);
        return ['ok' => true, 'id' => $wpdb->insert_id];
    }

    public static function rest_heartbeat(WP_REST_Request $req) {
        global $wpdb;
        $b = $req->get_json_params() ?: json_decode($req->get_body(), true) ?: [];
        if (session_status() === PHP_SESSION_NONE) ppv_maybe_start_session();
        if (empty($_SESSION)) {
            error_log('[PPV_Remote_Service] heartbeat empty session: session_id=' . session_id()
                . ', phpsessid_cookie=' . (!empty($_COOKIE[session_name()]) ? 'yes' : 'no')
                . ', cookie_name=' . session_name()
                . ', request_uri=' . ($_SERVER['REQUEST_URI'] ?? '')
                . ', fingerprint=' . sanitize_text_field($b['fingerprint'] ?? ''));
        }
        $user_id = intval($_SESSION['ppv_user_id'] ?? 0);
        $store_id = intval($_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_store_id'] ?? 0);

        $fp = sanitize_text_field($b['fingerprint'] ?? '');
        if (!$fp) return ['ok' => false, 'err' => 'no_fingerprint'];

        $data = [
            'user_id' => $user_id ?: null,
            'store_id' => $store_id ?: null,
            'device_fingerprint' => $fp,
            'user_agent' => substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            'sw_version' => sanitize_text_field($b['sw_version'] ?? ''),
            'screen' => sanitize_text_field($b['screen'] ?? ''),
            'lang' => sanitize_text_field($b['lang'] ?? ''),
            'online' => (int)(!empty($b['online'])),
            'camera_permission' => sanitize_text_field($b['camera'] ?? ''),
            'notification_permission' => sanitize_text_field($b['notification'] ?? ''),
            'battery' => isset($b['battery']) ? intval($b['battery']) : null,
            'connection' => sanitize_text_field($b['connection'] ?? ''),
            'last_url' => substr(sanitize_text_field($b['url'] ?? ''), 0, 500),
            'extra' => wp_json_encode($b['extra'] ?? null),
            'last_heartbeat' => current_time('mysql'),
        ];

        // UPSERT by (user_id, fingerprint)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_device_diagnostics WHERE user_id=%d AND device_fingerprint=%s LIMIT 1",
            $user_id ?: 0, $fp
        ));
        if ($existing) {
            $wpdb->update("{$wpdb->prefix}ppv_device_diagnostics", $data, ['id' => $existing]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert("{$wpdb->prefix}ppv_device_diagnostics", $data);
        }

        return ['ok' => true];
    }

    public static function rest_pending_actions(WP_REST_Request $req) {
        global $wpdb;
        if (session_status() === PHP_SESSION_NONE) ppv_maybe_start_session();
        $user_id = intval($_SESSION['ppv_user_id'] ?? 0);
        $fp = sanitize_text_field($req->get_param('fingerprint') ?? '');
        if (!$user_id || !$fp) return ['actions' => []];

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, action, payload FROM {$wpdb->prefix}ppv_remote_actions
             WHERE user_id=%d AND device_fingerprint=%s AND status='pending'
             ORDER BY id ASC LIMIT 10",
            $user_id, $fp
        ));
        return ['actions' => $rows];
    }

    public static function rest_action_result(WP_REST_Request $req) {
        global $wpdb;
        $b = $req->get_json_params() ?: json_decode($req->get_body(), true) ?: [];
        $id = intval($b['id'] ?? 0);
        if (!$id) return ['ok' => false];
        $wpdb->update("{$wpdb->prefix}ppv_remote_actions", [
            'status' => sanitize_text_field($b['status'] ?? 'done'),
            'result' => substr(sanitize_textarea_field($b['result'] ?? ''), 0, 2000),
            'executed_at' => current_time('mysql'),
        ], ['id' => $id]);
        return ['ok' => true];
    }

    private static $footer_printed = false;

    public static function enqueue_telemetry() {
        if (session_status() === PHP_SESSION_NONE) ppv_maybe_start_session();
        // Mark telemetry script so wp_footer fallback can skip
        if (!self::$footer_printed) {
            add_action('wp_print_footer_scripts', function() {
                self::$footer_printed = true;
            }, 1);
        }
        wp_register_script('ppv-telemetry', false, [], '1', true);
        wp_enqueue_script('ppv-telemetry');

        $ajax = esc_url_raw(admin_url('admin-ajax.php'));

        $inline = <<<JS
(function(){
  var AJAX = '{$ajax}';
  function post(action, body){
    try {
      return fetch(AJAX + '?action=' + action, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(body||{}),
        credentials: 'include',
        keepalive: true
      }).catch(function(){});
    } catch(e){}
  }

  // Global error handler
  window.addEventListener('error', function(ev){
    post('ppv_client_error', {
      url: location.href,
      message: (ev.message || 'Unknown error') + (ev.filename ? ' @ ' + ev.filename + ':' + ev.lineno : ''),
      stack: ev.error && ev.error.stack ? ev.error.stack : null,
      severity: 'error'
    });
  });
  window.addEventListener('unhandledrejection', function(ev){
    var r = ev.reason || {};
    post('ppv_client_error', {
      url: location.href,
      message: 'Unhandled promise: ' + (r.message || r.toString()),
      stack: r.stack || null,
      severity: 'error'
    });
  });

  // Device fingerprint (stable per-browser)
  function getFP(){
    try {
      var fp = localStorage.getItem('ppv_fp');
      if (!fp) { fp = 'fp_' + Math.random().toString(36).slice(2) + Date.now().toString(36); localStorage.setItem('ppv_fp', fp); }
      return fp;
    } catch(e){ return 'fp_no_ls'; }
  }

  // Heartbeat with diagnostics
  async function heartbeat(){
    var camera = 'unknown', notif = 'unknown', battery = null, conn = '';
    try { var c = await navigator.permissions.query({name:'camera'}); camera = c.state; } catch(e){}
    try { notif = (typeof Notification !== 'undefined' ? Notification.permission : 'unsupported'); } catch(e){}
    try { if (navigator.getBattery) { var b = await navigator.getBattery(); battery = Math.round(b.level*100); } } catch(e){}
    try { if (navigator.connection) conn = navigator.connection.effectiveType || ''; } catch(e){}

    post('ppv_device_heartbeat', {
      fingerprint: getFP(),
      sw_version: (window.PPV_VERSION || ''),
      screen: screen.width + 'x' + screen.height,
      lang: navigator.language,
      online: navigator.onLine,
      camera: camera,
      notification: notif,
      battery: battery,
      connection: conn,
      url: location.href
    });

    // Poll pending remote actions
    try {
      var r = await fetch(AJAX + '?action=ppv_pending_actions&fingerprint=' + encodeURIComponent(getFP()), {
        credentials: 'include'
      });
      var j = await r.json();
      (j.actions || []).forEach(function(a){ handleRemoteAction(a); });
    } catch(e){}
  }

  function handleRemoteAction(a){
    var result = 'unknown_action';
    try {
      switch(a.action){
        case 'reload': result = 'reloading'; setTimeout(function(){ location.reload(true); }, 500); break;
        case 'clear_cache':
          try { Object.keys(localStorage).filter(function(k){return k.startsWith('ppv_');}).forEach(function(k){ if(k !== 'ppv_fp') localStorage.removeItem(k); }); result = 'cache_cleared'; } catch(e){ result = 'err:' + e.message; }
          break;
        case 'force_logout':
          result = 'logging_out'; setTimeout(function(){ location.href = '/logout'; }, 300);
          break;
        case 'alert':
          try { alert(a.payload || 'Admin üzenet'); result = 'shown'; } catch(e){ result = 'err:' + e.message; }
          break;
      }
    } catch(e){ result = 'err:' + e.message; }
    post('ppv_action_result', { id: a.id, status: 'done', result: result });
  }

  // Fire heartbeat: once now + every 3 minutes
  heartbeat();
  setInterval(heartbeat, 180000);
})();
JS;

        wp_add_inline_script('ppv-telemetry', $inline);
    }

    public static function start_output_buffer() {
        if (defined('REST_REQUEST') || defined('DOING_AJAX') || defined('DOING_CRON') || php_sapi_name() === 'cli') return;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
        // Use callback form so buffer transforms on flush regardless of exit/die
        ob_start([__CLASS__, 'ob_inject_callback']);
    }

    public static function ob_inject_callback($html) {
        if (stripos($html, '</body>') === false) return $html;
        return str_ireplace('</body>', self::get_telemetry_snippet() . '</body>', $html);
    }

    public static function flush_output_buffer() {
        // No-op — ob_start callback handles injection on flush
    }

    public static function get_telemetry_snippet() {
        $ajax = esc_url_raw(admin_url('admin-ajax.php'));
        $js = '<script>/*PPV-telemetry-ob*/(function(){';
        $js .= 'var AJAX="' . $ajax . '";';
        $js .= 'function post(a,b){try{return fetch(AJAX+"?action="+a,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(b||{}),credentials:"include",keepalive:true}).catch(function(){});}catch(e){}}';
        $js .= 'window.addEventListener("error",function(ev){post("ppv_client_error",{url:location.href,message:(ev.message||"")+(ev.filename?" @ "+ev.filename+":"+ev.lineno:""),stack:ev.error&&ev.error.stack?ev.error.stack:null,severity:"error"});});';
        $js .= 'window.addEventListener("unhandledrejection",function(ev){var r=ev.reason||{};post("ppv_client_error",{url:location.href,message:"Unhandled promise: "+(r.message||r.toString()),stack:r.stack||null,severity:"error"});});';
        $js .= 'function getFP(){try{var fp=localStorage.getItem("ppv_fp");if(!fp){fp="fp_"+Math.random().toString(36).slice(2)+Date.now().toString(36);localStorage.setItem("ppv_fp",fp);}return fp;}catch(e){return "fp_no_ls";}}';
        $js .= 'async function hb(){var cam="?",nt="?",bat=null,conn="";try{var c=await navigator.permissions.query({name:"camera"});cam=c.state;}catch(e){}try{nt=(typeof Notification!=="undefined"?Notification.permission:"?");}catch(e){}try{if(navigator.getBattery){var b=await navigator.getBattery();bat=Math.round(b.level*100);}}catch(e){}try{if(navigator.connection)conn=navigator.connection.effectiveType||"";}catch(e){}post("ppv_device_heartbeat",{fingerprint:getFP(),sw_version:(window.PPV_VERSION||""),screen:screen.width+"x"+screen.height,lang:navigator.language,online:navigator.onLine,camera:cam,notification:nt,battery:bat,connection:conn,url:location.href});}';
        $js .= 'hb();setInterval(hb,180000);';
        $js .= '})();</script>';
        return $js;
    }

    public static function print_footer_telemetry() {
        echo self::get_telemetry_snippet();
    }
}

PPV_Remote_Service::hooks();

