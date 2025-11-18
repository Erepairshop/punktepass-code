<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('PPV_User_Signup', false)) {

class PPV_User_Signup {

    public static function hooks() {
        add_shortcode('pp_user_signup', [__CLASS__, 'render_form']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_ppv_user_signup', [__CLASS__, 'ajax_register_user']);
        add_action('wp_ajax_nopriv_ppv_user_signup', [__CLASS__, 'ajax_register_user']);
        add_action('template_redirect', [__CLASS__, 'handle_google_oauth']);
    }

    /** 🔹 Scripts + AJAX URL */
    public static function enqueue_assets() {
        wp_enqueue_script('ppv-user-signup', PPV_PLUGIN_URL . 'assets/js/ppv-user-signup.js', ['jquery'], time(), true);
        $__data = is_array([
            'ajax_url' => admin_url('admin-ajax.php')
        ] ?? null) ? [
            'ajax_url' => admin_url('admin-ajax.php')
        ] : [];
$__json = wp_json_encode($__data);
wp_add_inline_script('ppv-user-signup', "window.ppv_user_signup = {$__json};", 'before');
    }

    /** 🔹 Registrierungsformular */
    public static function render_form() {
        $google_url = add_query_arg('ppv_google_start', '1', home_url('/user_dashboard'));
        ob_start(); ?>
        
        <form id="ppv_user_form" method="post">
            <?php wp_nonce_field('ppv_user_signup', 'ppv_user_nonce'); ?>
            <input type="hidden" name="action" value="ppv_user_signup">

            <h2>👤 PunktePass Benutzerregistrierung</h2>

            <label>E-Mail *</label>
            <input type="email" name="email" required>

            <label>Passwort *</label>
            <input type="password" name="password" id="ppv_user_password"
                placeholder="Mind. 8 Zeichen, Großbuchstabe, Zahl, Sonderzeichen" required>

            <label>Passwort wiederholen *</label>
            <input type="password" name="password_repeat" id="ppv_user_password_repeat" required>

            <p><button type="submit" class="ppv-btn">Registrieren</button></p>
            <p style="text-align:center;">oder</p>
            <p style="text-align:center;">
                <a href="<?php echo esc_url($google_url); ?>" class="ppv-btn" style="background:#4285F4;">
                    Mit Google anmelden
                </a>
            </p>

            <div id="ppv_user_msg"></div>
        </form>

        <style>
            #ppv_user_form {
                max-width:400px; margin:auto; background:#fff; padding:25px;
                border-radius:10px; box-shadow:0 3px 10px rgba(0,0,0,0.1);
            }
            #ppv_user_form input {
                width:100%; padding:10px; margin-bottom:10px;
                border:1px solid #ccc; border-radius:6px;
            }
            .ppv-btn {
                background:#0073aa; color:#fff; border:none;
                padding:10px 20px; border-radius:6px;
                cursor:pointer; text-decoration:none; display:inline-block;
            }
            #ppv_user_msg { margin-top:10px; font-weight:500; }
        </style>

        <script>
        jQuery(function($){
            $('#ppv_user_form').on('submit', function(e){
                e.preventDefault();
                const pw  = $('#ppv_user_password').val();
                const pw2 = $('#ppv_user_password_repeat').val();
                const regex = /^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*()_\-+=<>?{}[\]~]).{8,}$/;

                if (pw !== pw2) {
                    $('#ppv_user_msg').text('❌ Passwörter stimmen nicht überein.');
                    return;
                }
                if (!regex.test(pw)) {
                    $('#ppv_user_msg').text('❌ Passwort muss Großbuchstaben, Zahl und Sonderzeichen enthalten.');
                    return;
                }

                $('#ppv_user_msg').text('⏳ Registrierung läuft...');
                $.post(ppv_user_signup.ajax_url, $(this).serialize(), function(res){
                    if (res.success) {
                        $('#ppv_user_msg').text('✅ ' + res.data.msg);
                        if (res.data.redirect) window.location.href = res.data.redirect;
                    } else {
                        $('#ppv_user_msg').text('❌ ' + res.data.msg);
                    }
                }).fail(function(xhr){
                    $('#ppv_user_msg').text('❌ Serverfehler: ' + xhr.status);
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

  
 /** 🔹 AJAX Registrierung */
public static function ajax_register_user() {
    if (empty($_POST['ppv_user_nonce']) || !wp_verify_nonce($_POST['ppv_user_nonce'], 'ppv_user_signup')) {
        wp_send_json_error(['msg' => 'Ungültiger Sicherheits-Token. Bitte Seite neu laden.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ppv_users';

    $email    = sanitize_email($_POST['email'] ?? '');
    $password = sanitize_text_field($_POST['password'] ?? '');

    if (!is_email($email)) {
        wp_send_json_error(['msg' => 'Ungültige E-Mail-Adresse.']);
    }

    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE email=%s", $email));
    if ($exists) {
        wp_send_json_error(['msg' => 'Diese E-Mail ist bereits registriert.']);
    }

    $password_hashed = password_hash($password, PASSWORD_DEFAULT);

    // 🔹 QR Token generieren
    $qr_token = wp_generate_password(10, false, false);

    // 🔹 Benutzer speichern + Token beilleszteni
    $wpdb->insert($table, [
        'email'      => $email,
        'password'   => $password_hashed,
        'qr_token'   => $qr_token,          // ✅ új mező
        'active'     => 1,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ]);

    $user_id = $wpdb->insert_id;

    // 🔹 Login-Session Cookie beállítás
    setcookie('ppv_user_id', $user_id, time() + (86400*14),
        COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);

    // 🔹 Debug log (ellenőrzéshez)
    error_log("✅ Neuer User erstellt (ID={$user_id}, Email={$email}, QR={$qr_token})");

    wp_send_json_success([
        'msg' => 'Registrierung erfolgreich! Dein QR-Code wurde erstellt.',
        'redirect' => home_url('/user_dashboard')
    ]);
}


    /** 🔹 Google Login / OAuth Handler */
    public static function handle_google_oauth() {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_users';

        // 1️⃣ Start OAuth
        if (isset($_GET['ppv_google_start'])) {
            $params = [
                'client_id'     => PPV_GOOGLE_CLIENT_ID,
                'redirect_uri'  => home_url('/user_dashboard/?ppv_google_callback=1'),
                'response_type' => 'code',
                'scope'         => 'email profile',
                'access_type'   => 'online',
                'prompt'        => 'select_account'
            ];
            wp_redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
            exit;
        }

        // 2️⃣ Callback
        if (isset($_GET['ppv_google_callback']) && isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            $response = wp_remote_post('https://oauth2.googleapis.com/token', [
                'body' => [
                    'code'          => $code,
                    'client_id'     => PPV_GOOGLE_CLIENT_ID,
                    'client_secret' => PPV_GOOGLE_CLIENT_SECRET,
                    'redirect_uri'  => home_url('/user_dashboard/?ppv_google_callback=1'),
                    'grant_type'    => 'authorization_code'
                ]
            ]);
            $body  = json_decode(wp_remote_retrieve_body($response), true);
            $token = $body['access_token'] ?? null;

            if ($token) {
                $user_info = wp_remote_get('https://www.googleapis.com/oauth2/v2/userinfo', [
                    'headers' => ['Authorization' => 'Bearer ' . $token]
                ]);
                $data = json_decode(wp_remote_retrieve_body($user_info), true);

                $email     = sanitize_email($data['email'] ?? '');
                $google_id = sanitize_text_field($data['id'] ?? '');
                $avatar    = esc_url_raw($data['picture'] ?? '');
                $name_parts = explode(' ', sanitize_text_field($data['name'] ?? ''), 2);
                $first = $name_parts[0] ?? '';
                $last  = $name_parts[1] ?? '';

                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE email=%s", $email));
     if (!$exists) {
    // 🔹 QR token generálás a Google-regisztrált felhasználónak
    $qr_token = wp_generate_password(10, false, false);

    $wpdb->insert($table, [
        'email'      => $email,
        'google_id'  => $google_id,
        'first_name' => $first,
        'last_name'  => $last,
        'avatar'     => $avatar,
        'qr_token'   => $qr_token,  // ✅ QR mező hozzáadva
        'active'     => 1,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ]);

    $user_id = $wpdb->insert_id;
    error_log("✅ Neuer Google-User mit QR erstellt (ID={$user_id}, Email={$email})");
} else {
    $user_id = $exists;
}


                setcookie('ppv_user_id', $user_id, time() + (86400*14),
                    COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);
                wp_redirect(home_url('/user_dashboard?login=google'));
                exit;
            } else {
                wp_redirect(home_url('/user_dashboard?login=error'));
                exit;
            }
        }
    }
}

}

PPV_User_Signup::hooks();
