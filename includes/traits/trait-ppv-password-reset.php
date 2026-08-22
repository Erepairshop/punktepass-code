<?php
/**
 * Secure standalone password reset flow for PunktePass custom accounts.
 */

if (!defined('ABSPATH')) exit;

trait PPV_Password_Reset_Trait {
    public static function intercept_password_reset_page() {
        $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $path = '/' . trim((string)$path, '/');
        $request_paths = ['/passwort-vergessen', '/forgot-password', '/elfelejtett-jelszo'];
        $reset_paths = ['/passwort-zuruecksetzen', '/reset-password', '/jelszo-visszaallitas'];

        if (in_array($path, $request_paths, true)) {
            self::render_password_request_page();
            exit;
        }
        if (in_array($path, $reset_paths, true)) {
            self::render_password_reset_page();
            exit;
        }
    }

    private static function password_reset_copy($lang) {
        $copy = [
            'de' => [
                'request_title' => 'Passwort zurücksetzen',
                'request_intro' => 'Gib deine E-Mail-Adresse ein. Wenn ein aktives Konto existiert, senden wir dir einen sicheren Link.',
                'email' => 'E-Mail-Adresse', 'send' => 'Link senden',
                'generic' => 'Wenn ein aktives Konto zu dieser Adresse existiert, wurde ein Link versendet. Bitte prüfe auch den Spam-Ordner.',
                'back' => 'Zurück zur Anmeldung', 'reset_title' => 'Neues Passwort festlegen',
                'password' => 'Neues Passwort', 'confirm' => 'Passwort wiederholen', 'save' => 'Passwort speichern',
                'invalid' => 'Dieser Link ist ungültig oder abgelaufen. Bitte fordere einen neuen Link an.',
                'mismatch' => 'Die Passwörter stimmen nicht überein.',
                'short' => 'Das Passwort muss mindestens 10 Zeichen lang sein.',
                'changed' => 'Dein Passwort wurde geändert. Du kannst dich jetzt anmelden.',
                'mail_subject' => 'PunktePass Passwort zurücksetzen', 'mail_heading' => 'Passwort zurücksetzen',
                'mail_text' => 'Klicke auf den Button, um ein neues Passwort festzulegen. Der Link ist 60 Minuten gültig und kann nur einmal verwendet werden.',
                'mail_button' => 'Neues Passwort festlegen',
                'mail_ignore' => 'Wenn du diese Anfrage nicht gestellt hast, kannst du diese E-Mail ignorieren.',
                'changed_subject' => 'PunktePass Passwort geändert',
                'changed_mail' => 'Das Passwort deines PunktePass-Kontos wurde erfolgreich geändert. Wenn du das nicht warst, kontaktiere bitte sofort den Support.',
            ],
            'hu' => [
                'request_title' => 'Jelszó visszaállítása',
                'request_intro' => 'Add meg az emailcímedet. Ha tartozik hozzá aktív fiók, biztonságos linket küldünk.',
                'email' => 'Emailcím', 'send' => 'Link küldése',
                'generic' => 'Ha ehhez a címhez aktív fiók tartozik, elküldtük a linket. Nézd meg a spam mappát is.',
                'back' => 'Vissza a bejelentkezéshez', 'reset_title' => 'Új jelszó beállítása',
                'password' => 'Új jelszó', 'confirm' => 'Jelszó még egyszer', 'save' => 'Jelszó mentése',
                'invalid' => 'Ez a link érvénytelen vagy lejárt. Kérj új visszaállító linket.',
                'mismatch' => 'A két jelszó nem egyezik.', 'short' => 'A jelszó legalább 10 karakter hosszú legyen.',
                'changed' => 'A jelszavad megváltozott. Most már bejelentkezhetsz.',
                'mail_subject' => 'PunktePass jelszó-visszaállítás', 'mail_heading' => 'Jelszó visszaállítása',
                'mail_text' => 'A gombbal új jelszót állíthatsz be. A link 60 percig érvényes, és csak egyszer használható.',
                'mail_button' => 'Új jelszó beállítása',
                'mail_ignore' => 'Ha nem te kérted ezt, hagyd figyelmen kívül az emailt.',
                'changed_subject' => 'A PunktePass jelszavad megváltozott',
                'changed_mail' => 'A PunktePass-fiókod jelszava sikeresen megváltozott. Ha nem te voltál, azonnal keresd az ügyfélszolgálatot.',
            ],
            'ro' => [
                'request_title' => 'Resetarea parolei',
                'request_intro' => 'Introdu adresa de e-mail. Dacă există un cont activ, îți vom trimite un link securizat.',
                'email' => 'Adresa de e-mail', 'send' => 'Trimite linkul',
                'generic' => 'Dacă există un cont activ pentru această adresă, linkul a fost trimis. Verifică și folderul Spam.',
                'back' => 'Înapoi la autentificare', 'reset_title' => 'Setează o parolă nouă',
                'password' => 'Parolă nouă', 'confirm' => 'Repetă parola', 'save' => 'Salvează parola',
                'invalid' => 'Linkul este invalid sau a expirat. Solicită un link nou.',
                'mismatch' => 'Parolele nu coincid.', 'short' => 'Parola trebuie să aibă cel puțin 10 caractere.',
                'changed' => 'Parola a fost schimbată. Acum te poți autentifica.',
                'mail_subject' => 'Resetare parolă PunktePass', 'mail_heading' => 'Resetarea parolei',
                'mail_text' => 'Folosește butonul pentru a seta o parolă nouă. Linkul este valabil 60 de minute și poate fi folosit o singură dată.',
                'mail_button' => 'Setează parola nouă',
                'mail_ignore' => 'Dacă nu ai solicitat resetarea, ignoră acest e-mail.',
                'changed_subject' => 'Parola PunktePass a fost schimbată',
                'changed_mail' => 'Parola contului tău PunktePass a fost schimbată. Dacă nu ai făcut această modificare, contactează imediat serviciul de asistență.',
            ],
            'en' => [
                'request_title' => 'Reset password',
                'request_intro' => 'Enter your email address. If an active account exists, we will send you a secure link.',
                'email' => 'Email address', 'send' => 'Send link',
                'generic' => 'If an active account exists for this address, the link has been sent. Please check your spam folder too.',
                'back' => 'Back to login', 'reset_title' => 'Set a new password',
                'password' => 'New password', 'confirm' => 'Repeat password', 'save' => 'Save password',
                'invalid' => 'This link is invalid or has expired. Please request a new link.',
                'mismatch' => 'The passwords do not match.', 'short' => 'The password must be at least 10 characters long.',
                'changed' => 'Your password has been changed. You can now sign in.',
                'mail_subject' => 'Reset your PunktePass password', 'mail_heading' => 'Reset password',
                'mail_text' => 'Use the button to set a new password. The link is valid for 60 minutes and can only be used once.',
                'mail_button' => 'Set new password',
                'mail_ignore' => 'If you did not request this, you can ignore this email.',
                'changed_subject' => 'Your PunktePass password was changed',
                'changed_mail' => 'Your PunktePass account password was changed successfully. If this was not you, contact support immediately.',
            ],
        ];
        return $copy[$lang] ?? $copy['de'];
    }

    private static function password_reset_account_exists($email) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $user = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}ppv_users WHERE email=%s AND active=1 LIMIT 1", $email));
        $store = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}ppv_stores WHERE email=%s AND active=1 LIMIT 1", $email));
        $advertiser = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}ppv_advertisers WHERE owner_email=%s AND is_active=1 LIMIT 1", $email));
        return (bool)($user || $store || $advertiser);
    }

    private static function password_reset_transient_key($token) {
        return 'ppv_pw_reset_' . hash('sha256', $token);
    }

    private static function password_reset_email_key($email) {
        return 'ppv_pw_email_' . hash('sha256', strtolower($email));
    }

    private static function password_reset_throttle_key($email) {
        return 'ppv_pw_throttle_' . hash('sha256', strtolower($email));
    }

    private static function password_reset_context($value = null) {
        if ($value === null) $value = $_REQUEST['context'] ?? '';
        return sanitize_key(wp_unslash((string)$value)) === 'formular' ? 'formular' : '';
    }

    private static function request_password_reset($email, $lang, $context = '') {
        if (!is_email($email) || !self::password_reset_account_exists($email)) return;
        $email_key = self::password_reset_email_key($email);
        $throttle_key = self::password_reset_throttle_key($email);
        if (get_transient($throttle_key)) return;

        try {
            $token = bin2hex(random_bytes(32));
        } catch (Exception $exception) {
            ppv_log('[PPV_Login] Password reset token generation failed');
            return;
        }

        $token_hash = hash('sha256', $token);
        $previous_hash = get_transient($email_key);
        if (is_string($previous_hash) && preg_match('/^[a-f0-9]{64}$/', $previous_hash)) {
            delete_transient('ppv_pw_reset_' . $previous_hash);
        }
        $context = self::password_reset_context($context);
        set_transient(self::password_reset_transient_key($token), ['email' => strtolower($email), 'lang' => $lang, 'context' => $context], HOUR_IN_SECONDS);
        set_transient($email_key, $token_hash, HOUR_IN_SECONDS);
        set_transient($throttle_key, 1, MINUTE_IN_SECONDS);

        $copy = self::password_reset_copy($lang);
        $reset_url = add_query_arg(['token' => $token, 'email' => $email, 'lang' => $lang, 'context' => $context], home_url('/passwort-zuruecksetzen'));
        $message = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#172033">'
            . '<h2 style="color:#165ddb">' . esc_html($copy['mail_heading']) . '</h2>'
            . '<p>' . esc_html($copy['mail_text']) . '</p>'
            . '<p style="margin:28px 0"><a href="' . esc_url($reset_url) . '" style="background:#165ddb;color:#fff;text-decoration:none;padding:13px 20px;border-radius:10px;font-weight:700">' . esc_html($copy['mail_button']) . '</a></p>'
            . '<p style="font-size:13px;color:#64748b">' . esc_html($copy['mail_ignore']) . '</p></div>';
        $sent = wp_mail($email, $copy['mail_subject'], $message, ['Content-Type: text/html; charset=UTF-8']);
        if (!$sent) {
            delete_transient(self::password_reset_transient_key($token));
            delete_transient($email_key);
            delete_transient($throttle_key);
            ppv_log('[PPV_Login] Password reset email could not be sent');
        }
    }

    private static function validate_password_reset_token($token, $email) {
        if (!preg_match('/^[a-f0-9]{64}$/', $token) || !is_email($email)) return false;
        $data = get_transient(self::password_reset_transient_key($token));
        if (!is_array($data) || empty($data['email']) || !hash_equals(strtolower($data['email']), strtolower($email))) return false;
        $current_hash = get_transient(self::password_reset_email_key($email));
        return is_string($current_hash) && hash_equals($current_hash, hash('sha256', $token));
    }

    private static function apply_password_reset($email, $password) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!$hash) return false;

        $user_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$prefix}ppv_users WHERE email=%s AND active=1", $email));
        $store_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$prefix}ppv_stores WHERE email=%s AND active=1", $email));
        $advertiser_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$prefix}ppv_advertisers WHERE owner_email=%s AND is_active=1", $email));
        if (!$user_ids && !$store_ids && !$advertiser_ids) return false;

        $wpdb->query('START TRANSACTION');
        try {
            foreach ($user_ids as $id) {
                $updated = $wpdb->update("{$prefix}ppv_users", [
                    'password' => $hash,
                    'login_token' => bin2hex(random_bytes(16)),
                ], ['id' => (int)$id], ['%s', '%s'], ['%d']);
                if ($updated === false) throw new RuntimeException('user update failed');
            }
            foreach ($store_ids as $id) {
                if ($wpdb->update("{$prefix}ppv_stores", ['password' => $hash], ['id' => (int)$id], ['%s'], ['%d']) === false) {
                    throw new RuntimeException('store update failed');
                }
            }
            foreach ($advertiser_ids as $id) {
                if ($wpdb->update("{$prefix}ppv_advertisers", ['password_hash' => $hash], ['id' => (int)$id], ['%s'], ['%d']) === false) {
                    throw new RuntimeException('advertiser update failed');
                }
            }
            $wpdb->query('COMMIT');
        } catch (Throwable $exception) {
            $wpdb->query('ROLLBACK');
            ppv_log('[PPV_Login] Password reset database update failed');
            return false;
        }
        ppv_log('[PPV_Login] Password reset completed for active account records: ' . (count($user_ids) + count($store_ids) + count($advertiser_ids)));
        return true;
    }

    private static function password_reset_ip_allowed() {
        $key = 'ppv_pw_ip_' . hash('sha256', self::get_client_ip());
        $attempts = (int)get_transient($key);
        if ($attempts >= 5) return false;
        set_transient($key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
        return true;
    }

    private static function render_password_request_page() {
        $lang = self::get_current_lang();
        $context = self::password_reset_context();
        $copy = self::password_reset_copy($lang);
        $message = '';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
            if (wp_verify_nonce($nonce, 'ppv_password_reset_request')) {
                $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
                if (self::password_reset_ip_allowed()) self::request_password_reset($email, $lang, $context);
                $message = $copy['generic'];
            }
        }
        self::render_password_page_shell($lang, $copy['request_title'], function() use ($copy, $message, $context) {
            if ($message) {
                echo '<div class="pr-alert pr-success">' . esc_html($message) . '</div>';
                return;
            }
            echo '<p class="pr-intro">' . esc_html($copy['request_intro']) . '</p><form method="post" class="pr-form">';
            wp_nonce_field('ppv_password_reset_request', 'nonce');
            echo '<input type="hidden" name="context" value="' . esc_attr($context) . '">';
            echo '<label for="pr-email">' . esc_html($copy['email']) . '</label>';
            echo '<input id="pr-email" type="email" name="email" autocomplete="email" required>';
            echo '<button type="submit">' . esc_html($copy['send']) . '</button></form>';
        }, $copy['back'], $context);
    }

    private static function render_password_reset_page() {
        $lang = self::get_current_lang();
        $context = self::password_reset_context();
        $copy = self::password_reset_copy($lang);
        $token = sanitize_text_field(wp_unslash($_REQUEST['token'] ?? ''));
        $email = sanitize_email(wp_unslash($_REQUEST['email'] ?? ''));
        $valid = self::validate_password_reset_token($token, $email);
        $error = '';
        $changed = false;

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $valid) {
            $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
            $password = (string)wp_unslash($_POST['password'] ?? '');
            $confirm = (string)wp_unslash($_POST['password_confirm'] ?? '');
            if (!wp_verify_nonce($nonce, 'ppv_password_reset_apply')) $error = $copy['invalid'];
            elseif (strlen($password) < 10) $error = $copy['short'];
            elseif (!hash_equals($password, $confirm)) $error = $copy['mismatch'];
            elseif (self::apply_password_reset($email, $password)) {
                delete_transient(self::password_reset_transient_key($token));
                delete_transient(self::password_reset_email_key($email));
                delete_transient(self::password_reset_throttle_key($email));
                $changed = true;
                wp_mail($email, $copy['changed_subject'], $copy['changed_mail'], ['Content-Type: text/plain; charset=UTF-8']);
            } else $error = $copy['invalid'];
        } elseif (!$valid) {
            $error = $copy['invalid'];
        }

        self::render_password_page_shell($lang, $copy['reset_title'], function() use ($copy, $token, $email, $valid, $error, $changed, $context) {
            if ($changed) {
                echo '<div class="pr-alert pr-success">' . esc_html($copy['changed']) . '</div>';
                return;
            }
            if ($error) echo '<div class="pr-alert pr-error">' . esc_html($error) . '</div>';
            if (!$valid) {
                echo '<a class="pr-button" href="' . esc_url(add_query_arg(['lang' => self::get_current_lang(), 'context' => $context], home_url('/passwort-vergessen'))) . '">' . esc_html($copy['request_title']) . '</a>';
                return;
            }
            echo '<form method="post" class="pr-form">';
            wp_nonce_field('ppv_password_reset_apply', 'nonce');
            echo '<input type="hidden" name="token" value="' . esc_attr($token) . '"><input type="hidden" name="email" value="' . esc_attr($email) . '"><input type="hidden" name="context" value="' . esc_attr($context) . '">';
            echo '<label for="pr-password">' . esc_html($copy['password']) . '</label><input id="pr-password" type="password" name="password" minlength="10" autocomplete="new-password" required>';
            echo '<label for="pr-password-confirm">' . esc_html($copy['confirm']) . '</label><input id="pr-password-confirm" type="password" name="password_confirm" minlength="10" autocomplete="new-password" required>';
            echo '<button type="submit">' . esc_html($copy['save']) . '</button></form>';
        }, $copy['back'], $context);
    }

    private static function render_password_page_shell($lang, $title, $content, $back_label, $context = '') {
        status_header(200);
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow', true);
        ?><!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?php echo esc_html($title); ?> - PunktePass</title>
    <link rel="icon" href="<?php echo esc_url(PPV_PLUGIN_URL); ?>assets/img/icon-192.png" type="image/png">
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:linear-gradient(145deg,#f7f9ff,#eef4ff);font-family:Inter,Arial,sans-serif;color:#172033}.pr-card{width:100%;max-width:440px;background:#fff;border:1px solid #dfe7f5;border-radius:22px;padding:30px;box-shadow:0 18px 55px rgba(22,93,219,.13)}.pr-brand{display:flex;align-items:center;justify-content:center;gap:10px;color:#165ddb;font-size:20px;font-weight:800;text-decoration:none;margin-bottom:24px}.pr-brand img{width:38px;height:38px;border-radius:10px}.pr-card h1{font-size:26px;line-height:1.2;text-align:center;margin:0 0 14px}.pr-intro{color:#5f6d84;line-height:1.55;text-align:center;margin:0 0 22px}.pr-form{display:grid;gap:10px}.pr-form label{font-size:14px;font-weight:700;margin-top:4px}.pr-form input{width:100%;border:1.5px solid #cad5e7;border-radius:12px;padding:13px 14px;font-size:16px;outline:none}.pr-form input:focus{border-color:#165ddb;box-shadow:0 0 0 3px rgba(22,93,219,.12)}.pr-form button,.pr-button{display:block;width:100%;border:0;border-radius:12px;background:#165ddb;color:#fff;padding:14px 18px;font-size:16px;font-weight:800;text-align:center;text-decoration:none;cursor:pointer;margin-top:8px}.pr-alert{border-radius:12px;padding:14px;line-height:1.5;text-align:center}.pr-success{background:#eaf9f0;color:#176b3a}.pr-error{background:#fff0f0;color:#a72c2c;margin-bottom:16px}.pr-back{display:block;text-align:center;color:#51627c;text-decoration:none;font-weight:700;margin-top:22px}@media(max-width:480px){.pr-card{padding:24px 18px;border-radius:18px}.pr-card h1{font-size:23px}}
    </style>
</head>
<body><main class="pr-card">
    <a class="pr-brand" href="<?php echo esc_url(home_url('/')); ?>"><img src="<?php echo esc_url(PPV_PLUGIN_URL); ?>assets/img/logo.webp?v=2" alt=""><span>PunktePass</span></a>
    <h1><?php echo esc_html($title); ?></h1>
    <?php call_user_func($content); ?>
    <?php $back_url = self::password_reset_context($context) === 'formular' ? home_url('/formular/admin/login') : home_url('/login'); ?>
    <a class="pr-back" href="<?php echo esc_url(add_query_arg('lang', $lang, $back_url)); ?>"><?php echo esc_html($back_label); ?></a>
</main></body></html><?php
    }
}
