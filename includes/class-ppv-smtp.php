<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass - SMTP Configuration
 * Configures WordPress to send emails via SMTP (Hostinger or other providers)
 * This bypasses the default PHP mail() function which often fails with Gmail
 */
class PPV_SMTP {

    const OPTION_KEY = 'ppv_smtp_settings';

    public static function hooks() {
        // Configure PHPMailer to use SMTP
        add_action('phpmailer_init', [__CLASS__, 'configure_smtp'], 10, 1);

        // Admin settings
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);

        // AJAX handlers
        add_action('wp_ajax_ppv_test_smtp', [__CLASS__, 'ajax_test_smtp']);
        add_action('wp_ajax_ppv_save_smtp', [__CLASS__, 'ajax_save_smtp']);
    }

    /**
     * Get SMTP settings with defaults
     */
    public static function get_settings() {
        $defaults = [
            'enabled'     => false,
            'host'        => 'smtp.hostinger.com',
            'port'        => 465,
            'encryption'  => 'ssl', // ssl or tls
            'auth'        => true,
            'username'    => '',
            'password'    => '',
            'from_email'  => '',
            'from_name'   => 'PunktePass',
        ];

        $settings = get_option(self::OPTION_KEY, []);
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Save SMTP settings
     */
    public static function save_settings($settings) {
        $sanitized = [
            'enabled'     => !empty($settings['enabled']),
            'host'        => sanitize_text_field($settings['host'] ?? ''),
            'port'        => intval($settings['port'] ?? 465),
            'encryption'  => in_array($settings['encryption'] ?? '', ['ssl', 'tls', 'none']) ? $settings['encryption'] : 'ssl',
            'auth'        => !empty($settings['auth']),
            'username'    => sanitize_text_field($settings['username'] ?? ''),
            'password'    => $settings['password'] ?? '', // Don't sanitize password (might have special chars)
            'from_email'  => sanitize_email($settings['from_email'] ?? ''),
            'from_name'   => sanitize_text_field($settings['from_name'] ?? ''),
        ];

        return update_option(self::OPTION_KEY, $sanitized);
    }

    /**
     * Configure PHPMailer to use SMTP
     * This is called by WordPress before sending any email
     */
    public static function configure_smtp($phpmailer) {
        $settings = self::get_settings();

        // Skip if not enabled
        if (empty($settings['enabled'])) {
            return;
        }

        // Skip if not configured
        if (empty($settings['host']) || empty($settings['username']) || empty($settings['password'])) {
            ppv_log("[SMTP] Not configured properly, skipping SMTP");
            return;
        }

        // Configure SMTP
        $phpmailer->isSMTP();
        $phpmailer->Host       = $settings['host'];
        $phpmailer->Port       = $settings['port'];
        $phpmailer->SMTPAuth   = $settings['auth'];
        $phpmailer->Username   = $settings['username'];
        $phpmailer->Password   = $settings['password'];

        // Encryption
        if ($settings['encryption'] === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($settings['encryption'] === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = '';
        }

        // From address
        if (!empty($settings['from_email'])) {
            $phpmailer->From = $settings['from_email'];
        }
        if (!empty($settings['from_name'])) {
            $phpmailer->FromName = $settings['from_name'];
        }

        // Debug mode (only when PPV_DEBUG is on)
        if (defined('PPV_DEBUG') && PPV_DEBUG) {
            $phpmailer->SMTPDebug = 2;
            $phpmailer->Debugoutput = function($str, $level) {
                ppv_log("[SMTP Debug] $str");
            };
        }

        ppv_log("[SMTP] Configured: {$settings['host']}:{$settings['port']} ({$settings['encryption']})");
    }

    /**
     * Add SMTP settings page to admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'punktepass',
            'SMTP Beállítások',
            '📧 SMTP',
            'manage_options',
            'ppv-smtp',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting('ppv_smtp_options', self::OPTION_KEY);
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        $settings = self::get_settings();
        ?>
        <div class="wrap">
            <h1>📧 SMTP Beállítások</h1>
            <p>Email küldés konfigurálása SMTP szerveren keresztül (pl. Hostinger).</p>

            <div id="ppv-smtp-notice" style="display:none;" class="notice"></div>

            <form id="ppv-smtp-form" method="post" action="">
                <?php wp_nonce_field('ppv_smtp_nonce', 'ppv_smtp_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">SMTP Aktiválása</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled']); ?>>
                                SMTP használata email küldéshez
                            </label>
                            <p class="description">Ha bekapcsolod, minden email SMTP-n keresztül megy ki.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">SMTP Szerver</th>
                        <td>
                            <input type="text" name="host" value="<?php echo esc_attr($settings['host']); ?>" class="regular-text" placeholder="smtp.hostinger.com">
                            <p class="description">Hostinger: <code>smtp.hostinger.com</code></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Port</th>
                        <td>
                            <select name="port">
                                <option value="465" <?php selected($settings['port'], 465); ?>>465 (SSL)</option>
                                <option value="587" <?php selected($settings['port'], 587); ?>>587 (TLS)</option>
                                <option value="25" <?php selected($settings['port'], 25); ?>>25 (nincs titkosítás)</option>
                            </select>
                            <p class="description">Hostinger: <code>465</code> (SSL) ajánlott</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Titkosítás</th>
                        <td>
                            <select name="encryption">
                                <option value="ssl" <?php selected($settings['encryption'], 'ssl'); ?>>SSL</option>
                                <option value="tls" <?php selected($settings['encryption'], 'tls'); ?>>TLS</option>
                                <option value="none" <?php selected($settings['encryption'], 'none'); ?>>Nincs</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Felhasználónév</th>
                        <td>
                            <input type="text" name="username" value="<?php echo esc_attr($settings['username']); ?>" class="regular-text" placeholder="noreply@punktepass.de">
                            <p class="description">Teljes email cím (pl. <code>noreply@punktepass.de</code>)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Jelszó</th>
                        <td>
                            <input type="password" name="password" value="<?php echo esc_attr($settings['password']); ?>" class="regular-text" placeholder="••••••••">
                            <p class="description">Az email fiók jelszava</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Feladó Email</th>
                        <td>
                            <input type="email" name="from_email" value="<?php echo esc_attr($settings['from_email']); ?>" class="regular-text" placeholder="noreply@punktepass.de">
                            <p class="description">Ennek meg kell egyeznie a felhasználónévvel!</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Feladó Neve</th>
                        <td>
                            <input type="text" name="from_name" value="<?php echo esc_attr($settings['from_name']); ?>" class="regular-text" placeholder="PunktePass">
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">💾 Mentés</button>
                    <button type="button" id="ppv-test-smtp" class="button">🧪 Teszt Email Küldése</button>
                </p>
            </form>

            <hr>

            <h2>🧪 Teszt Email</h2>
            <p>Teszt email küldése a beállítások ellenőrzéséhez:</p>
            <p>
                <input type="email" id="ppv-test-email" placeholder="teszt@gmail.com" class="regular-text" value="">
                <button type="button" id="ppv-send-test" class="button">📧 Küldés</button>
            </p>
            <div id="ppv-test-result" style="margin-top: 15px;"></div>

            <hr>

            <h3>📋 Hostinger SMTP Beállítások</h3>
            <table class="widefat" style="max-width: 500px;">
                <tr><th>Szerver:</th><td><code>smtp.hostinger.com</code></td></tr>
                <tr><th>Port:</th><td><code>465</code> (SSL) vagy <code>587</code> (TLS)</td></tr>
                <tr><th>Titkosítás:</th><td><code>SSL</code></td></tr>
                <tr><th>Hitelesítés:</th><td>Igen</td></tr>
                <tr><th>Felhasználónév:</th><td>Teljes email cím</td></tr>
                <tr><th>Jelszó:</th><td>Email fiók jelszava</td></tr>
            </table>

            <p style="margin-top: 20px;">
                <strong>Megjegyzés:</strong> Először létre kell hoznod egy email fiókot a Hostinger-en
                (pl. <code>noreply@punktepass.de</code>), majd annak az adatait add meg itt.
            </p>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Save settings
            $('#ppv-smtp-form').on('submit', function(e) {
                e.preventDefault();

                var formData = $(this).serializeArray();
                formData.push({name: 'action', value: 'ppv_save_smtp'});

                $.post(ajaxurl, formData, function(response) {
                    var notice = $('#ppv-smtp-notice');
                    if (response.success) {
                        notice.removeClass('notice-error').addClass('notice-success').html('<p>' + response.data.message + '</p>').show();
                    } else {
                        notice.removeClass('notice-success').addClass('notice-error').html('<p>' + response.data.message + '</p>').show();
                    }
                    setTimeout(function() { notice.fadeOut(); }, 5000);
                });
            });

            // Test SMTP
            $('#ppv-send-test').on('click', function() {
                var email = $('#ppv-test-email').val();
                if (!email) {
                    alert('Kérlek add meg a teszt email címet!');
                    return;
                }

                var btn = $(this);
                btn.prop('disabled', true).text('Küldés...');

                $.post(ajaxurl, {
                    action: 'ppv_test_smtp',
                    email: email,
                    _wpnonce: '<?php echo wp_create_nonce('ppv_smtp_test'); ?>'
                }, function(response) {
                    btn.prop('disabled', false).text('📧 Küldés');
                    var result = $('#ppv-test-result');
                    if (response.success) {
                        result.html('<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>');
                    } else {
                        result.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p><pre style="background:#f5f5f5;padding:10px;overflow:auto;">' + (response.data.error || '') + '</pre></div>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Save SMTP settings
     */
    public static function ajax_save_smtp() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nincs jogosultságod']);
        }

        if (!wp_verify_nonce($_POST['ppv_smtp_nonce'] ?? '', 'ppv_smtp_nonce')) {
            wp_send_json_error(['message' => 'Érvénytelen token']);
        }

        $settings = [
            'enabled'    => !empty($_POST['enabled']),
            'host'       => $_POST['host'] ?? '',
            'port'       => intval($_POST['port'] ?? 465),
            'encryption' => $_POST['encryption'] ?? 'ssl',
            'auth'       => true,
            'username'   => $_POST['username'] ?? '',
            'password'   => $_POST['password'] ?? '',
            'from_email' => $_POST['from_email'] ?? '',
            'from_name'  => $_POST['from_name'] ?? '',
        ];

        self::save_settings($settings);

        wp_send_json_success(['message' => '✅ Beállítások mentve!']);
    }

    /**
     * AJAX: Test SMTP connection
     */
    public static function ajax_test_smtp() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nincs jogosultságod']);
        }

        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ppv_smtp_test')) {
            wp_send_json_error(['message' => 'Érvénytelen token']);
        }

        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(['message' => 'Érvénytelen email cím']);
        }

        $settings = self::get_settings();
        if (!$settings['enabled']) {
            wp_send_json_error(['message' => 'SMTP nincs bekapcsolva! Előbb mentsd el a beállításokat az SMTP bekapcsolásával.']);
        }

        // Send test email
        $subject = 'PunktePass SMTP Teszt - ' . date('Y-m-d H:i:s');
        $body = '
        <html>
        <body style="font-family: Arial, sans-serif; padding: 20px;">
            <h2>✅ SMTP Teszt Sikeres!</h2>
            <p>Ez egy teszt email a PunktePass rendszerből.</p>
            <p>Ha ezt az emailt megkaptad, az SMTP beállítások működnek.</p>
            <hr>
            <p style="color: #666; font-size: 12px;">
                Küldve: ' . date('Y-m-d H:i:s') . '<br>
                Szerver: ' . esc_html($settings['host']) . ':' . $settings['port'] . '<br>
                Titkosítás: ' . esc_html($settings['encryption']) . '
            </p>
        </body>
        </html>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        $sent = wp_mail($email, $subject, $body, $headers);

        if ($sent) {
            wp_send_json_success([
                'message' => "Teszt email elküldve ide: {$email}",
            ]);
        } else {
            global $phpmailer;
            $error = '';
            if (isset($phpmailer) && isset($phpmailer->ErrorInfo)) {
                $error = $phpmailer->ErrorInfo;
            }
            wp_send_json_error([
                'message' => 'Email küldés sikertelen',
                'error' => $error
            ]);
        }
    }
}

// Initialize
add_action('plugins_loaded', function() {
    PPV_SMTP::hooks();
});
