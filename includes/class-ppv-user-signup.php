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
        // Load FingerprintJS for device identification (local vendor file - no CDN dependency)
        wp_enqueue_script('fingerprintjs', PPV_PLUGIN_URL . 'assets/js/vendor/fp.min.js', [], '4.6.2', true);

        wp_enqueue_script('ppv-user-signup', PPV_PLUGIN_URL . 'assets/js/ppv-user-signup.js', ['jquery', 'fingerprintjs'], time(), true);
        $__data = is_array([
            'ajax_url' => admin_url('admin-ajax.php'),
            'max_accounts_per_device' => 2
        ] ?? null) ? [
            'ajax_url' => admin_url('admin-ajax.php'),
            'max_accounts_per_device' => 2
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
            <input type="hidden" name="device_fingerprint" id="ppv_device_fingerprint" value="">

            <h2>👤 PunktePass Benutzerregistrierung</h2>

            <!-- 📱 Device limit warning box (hidden by default) -->
            <div id="ppv_device_warning" style="display:none; padding:12px; border-radius:8px; margin-bottom:15px; font-size:14px;">
                <span id="ppv_device_warning_icon"></span>
                <span id="ppv_device_warning_text"></span>
            </div>

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
                <a href="<?php echo esc_url($google_url); ?>" id="ppv_google_login_btn" class="ppv-btn" style="background:#4285F4;">
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
            // 📱 Initialize FingerprintJS and get device fingerprint
            let deviceFingerprint = '';
            let deviceBlocked = false;

            if (typeof FingerprintJS !== 'undefined') {
                FingerprintJS.load().then(fp => {
                    fp.get().then(result => {
                        deviceFingerprint = result.visitorId;
                        $('#ppv_device_fingerprint').val(deviceFingerprint);
                        console.log('📱 Device fingerprint loaded');

                        // 📱 Check device limit via REST API
                        $.ajax({
                            url: '<?php echo rest_url('punktepass/v1/device/check'); ?>',
                            method: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({ fingerprint: deviceFingerprint }),
                            success: function(res) {
                                const $warning = $('#ppv_device_warning');
                                const $icon = $('#ppv_device_warning_icon');
                                const $text = $('#ppv_device_warning_text');
                                const $submitBtn = $('#ppv_user_form button[type="submit"]');

                                if (res.blocked) {
                                    // 🚫 BLOCKED DEVICE - Admin blocked this device
                                    deviceBlocked = true;
                                    $warning.css({ display: 'block', background: '#fee2e2', border: '1px solid #ef4444', color: '#991b1b' });
                                    $icon.text('🚫 ');
                                    $text.text(res.message || 'Dieses Gerät wurde gesperrt. Registrierung nicht möglich.');
                                    $submitBtn.prop('disabled', true).css({ opacity: 0.5, cursor: 'not-allowed' });
                                    $('#ppv_google_login_btn').css({ opacity: 0.5, cursor: 'not-allowed', pointerEvents: 'none' });
                                } else if (res.accounts >= res.limit) {
                                    // 🔴 BLOCKED - Max accounts reached
                                    deviceBlocked = true;
                                    $warning.css({ display: 'block', background: '#fee2e2', border: '1px solid #ef4444', color: '#991b1b' });
                                    $icon.text('🚫 ');
                                    $text.text('Maximale Konten für dieses Gerät erreicht (' + res.accounts + '/' + res.limit + '). Registrierung nicht möglich.');
                                    $submitBtn.prop('disabled', true).css({ opacity: 0.5, cursor: 'not-allowed' });
                                    // 📱 Also disable Google login button
                                    $('#ppv_google_login_btn').css({ opacity: 0.5, cursor: 'not-allowed', pointerEvents: 'none' });
                                } else if (res.accounts > 0) {
                                    // 🟡 WARNING - Has accounts but can still register
                                    $warning.css({ display: 'block', background: '#fef3c7', border: '1px solid #f59e0b', color: '#92400e' });
                                    $icon.text('⚠️ ');
                                    $text.text('Auf diesem Gerät existiert bereits ' + res.accounts + ' Konto. Noch ' + (res.limit - res.accounts) + ' möglich.');
                                }
                                // If accounts == 0, no warning shown
                            }
                        });
                    });
                });
            }

            // 📱 Intercept Google login to pass fingerprint
            $('#ppv_google_login_btn').on('click', function(e) {
                if (deviceBlocked) {
                    e.preventDefault();
                    $('#ppv_user_msg').html('🚫 <strong>Registrierung blockiert.</strong> Maximale Konten für dieses Gerät erreicht.');
                    return false;
                }
                if (deviceFingerprint) {
                    e.preventDefault();
                    const baseUrl = $(this).attr('href');
                    const separator = baseUrl.includes('?') ? '&' : '?';
                    window.location.href = baseUrl + separator + 'device_fp=' + encodeURIComponent(deviceFingerprint);
                }
            });

            $('#ppv_user_form').on('submit', function(e){
                // Block submit if device limit reached
                if (deviceBlocked) {
                    e.preventDefault();
                    $('#ppv_user_msg').html('🚫 <strong>Registrierung blockiert.</strong> Maximale Konten für dieses Gerät erreicht.');
                    return false;
                }
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

                // Ensure fingerprint is set
                if (deviceFingerprint) {
                    $('#ppv_device_fingerprint').val(deviceFingerprint);
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

    $email       = sanitize_email($_POST['email'] ?? '');
    $password    = sanitize_text_field($_POST['password'] ?? '');

    // Validate fingerprint with central validation
    $fingerprint = '';
    if (!empty($_POST['device_fingerprint']) && class_exists('PPV_Device_Fingerprint')) {
        $fp_validation = PPV_Device_Fingerprint::validate_fingerprint($_POST['device_fingerprint'], true);
        $fingerprint = $fp_validation['valid'] ? ($fp_validation['sanitized'] ?? '') : '';
    } elseif (!empty($_POST['device_fingerprint'])) {
        $fingerprint = sanitize_text_field($_POST['device_fingerprint']);
    }

    if (!is_email($email)) {
        wp_send_json_error(['msg' => 'Ungültige E-Mail-Adresse.']);
    }

    // 📱 Device fingerprint limit check (max 2 accounts per device)
    if (!empty($fingerprint) && class_exists('PPV_Device_Fingerprint')) {
        $device_check = PPV_Device_Fingerprint::check_device_limit($fingerprint);
        if (!$device_check['allowed']) {
            ppv_log("⚠️ [Signup] Device limit reached: fingerprint={$fingerprint}, accounts={$device_check['accounts']}");
            wp_send_json_error([
                'msg' => 'Maximale Anzahl an Konten für dieses Gerät erreicht (' . $device_check['limit'] . ').',
                'error_type' => 'device_limit'
            ]);
        }
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

    // 📱 Store device fingerprint after successful registration
    if (!empty($fingerprint) && $user_id > 0 && class_exists('PPV_Device_Fingerprint')) {
        PPV_Device_Fingerprint::store_fingerprint($user_id, $fingerprint);
    }

    // 🔹 Login-Session Cookie beállítás
    setcookie('ppv_user_id', $user_id, time() + (86400*14),
        COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);

    // 🔹 Debug log (ellenőrzéshez)
    ppv_log("✅ Neuer User erstellt (ID={$user_id}, Email={$email}, QR={$qr_token}, FP=" . ($fingerprint ? 'YES' : 'NO') . ")");

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
            // 📱 Save device fingerprint to cookie before Google redirect
            if (!empty($_GET['device_fp'])) {
                setcookie('ppv_device_fp_temp', sanitize_text_field($_GET['device_fp']), time() + 600,
                    COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);
            }

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

                // 📱 Get device fingerprint from temp cookie
                $fingerprint = isset($_COOKIE['ppv_device_fp_temp']) ? sanitize_text_field($_COOKIE['ppv_device_fp_temp']) : '';

                // 📱 Check device limit for NEW registrations
                if (!$exists && !empty($fingerprint) && class_exists('PPV_Device_Fingerprint')) {
                    $device_check = PPV_Device_Fingerprint::check_device_limit($fingerprint);
                    if (!$device_check['allowed']) {
                        ppv_log("⚠️ [Google Signup] Device limit reached: fingerprint={$fingerprint}, accounts={$device_check['accounts']}");
                        // Clear temp cookie
                        setcookie('ppv_device_fp_temp', '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);
                        wp_redirect(home_url('/user_dashboard?login=device_limit'));
                        exit;
                    }
                }

                if (!$exists) {
                    // 🔹 QR token generálás a Google-regisztrált felhasználónak
                    $qr_token = wp_generate_password(10, false, false);
                    $display_name = trim($first . ' ' . $last);

                    $wpdb->insert($table, [
                        'email'      => $email,
                        'google_id'  => $google_id,
                        'first_name' => $first,
                        'last_name'  => $last,
                        'display_name' => $display_name,
                        'avatar'     => $avatar,
                        'qr_token'   => $qr_token,
                        'active'     => 1,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ]);

                    $user_id = $wpdb->insert_id;

                    // 📱 Store device fingerprint for NEW Google registration
                    if (!empty($fingerprint) && class_exists('PPV_Device_Fingerprint')) {
                        PPV_Device_Fingerprint::store_fingerprint($user_id, $fingerprint);
                        ppv_log("📱 [Google Signup] Fingerprint stored for new user ID={$user_id}");
                    }

                    ppv_log("✅ Neuer Google-User mit QR erstellt (ID={$user_id}, Email={$email}, FP=" . ($fingerprint ? 'YES' : 'NO') . ")");
                } else {
                    $user_id = $exists;

                    // 📱 Track login fingerprint for EXISTING users
                    if (!empty($fingerprint) && class_exists('PPV_Device_Fingerprint')) {
                        PPV_Device_Fingerprint::track_login($user_id, $fingerprint);
                    }
                }

                // 📱 Clear temp fingerprint cookie
                setcookie('ppv_device_fp_temp', '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);


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
