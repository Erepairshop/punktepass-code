<?php
/**
 * PunktePass Integration for eRepairShop
 *
 * Checks if a user exists, creates one if not, and adds bonus points.
 * Sends welcome email with credentials to new users.
 */

require_once __DIR__ . '/db_config.php';

// WordPress table prefix
define('WP_PREFIX', 'wp_');
// eRepairShop store ID in PunktePass
define('EREPAIRSHOP_STORE_ID', 9);
// Bonus points per repair form submission
define('REPAIR_BONUS_POINTS', 2);

/**
 * Process PunktePass integration for a repair form submission.
 *
 * @param string $email    Customer email
 * @param string $name     Customer name (for new user creation)
 * @return array           Result with keys: success, is_new_user, user_id, total_points, password (only for new users)
 */
function punktepass_process_repair($email, $name = '') {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Ungültige E-Mail-Adresse'];
    }

    try {
        $pdo = getDB();
        $prefix = WP_PREFIX;

        // 1. Check if user already exists
        $stmt = $pdo->prepare("SELECT id, email, first_name FROM {$prefix}ppv_users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

        $is_new_user = false;
        $user_id = null;
        $generated_password = null;

        if ($existing_user) {
            // Existing user - just add points
            $user_id = (int) $existing_user['id'];
        } else {
            // New user - create account
            $is_new_user = true;
            $generated_password = generate_secure_password();
            $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);
            $qr_token = generate_random_token(10);
            $login_token = bin2hex(random_bytes(32));

            // Parse first/last name
            $name_parts = explode(' ', trim($name), 2);
            $first_name = $name_parts[0] ?? '';
            $last_name = $name_parts[1] ?? '';

            $stmt = $pdo->prepare("
                INSERT INTO {$prefix}ppv_users
                (email, password, first_name, last_name, display_name, qr_token, login_token, user_type, active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'user', 1, NOW(), NOW())
            ");
            $stmt->execute([
                $email,
                $password_hash,
                $first_name,
                $last_name,
                trim($name) ?: $first_name,
                $qr_token,
                $login_token
            ]);
            $user_id = (int) $pdo->lastInsertId();
        }

        // 2. Add bonus points
        $stmt = $pdo->prepare("
            INSERT INTO {$prefix}ppv_points
            (user_id, store_id, points, type, reference, created)
            VALUES (?, ?, ?, 'bonus', 'Reparatur-Formular Bonus', NOW())
        ");
        $stmt->execute([$user_id, EREPAIRSHOP_STORE_ID, REPAIR_BONUS_POINTS]);

        // 3. Update lifetime_points
        $stmt = $pdo->prepare("
            UPDATE {$prefix}ppv_users
            SET lifetime_points = COALESCE(lifetime_points, 0) + ?
            WHERE id = ?
        ");
        $stmt->execute([REPAIR_BONUS_POINTS, $user_id]);

        // 4. Get total points for this store
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(points), 0) as total
            FROM {$prefix}ppv_points
            WHERE user_id = ? AND store_id = ?
        ");
        $stmt->execute([$user_id, EREPAIRSHOP_STORE_ID]);
        $total_points = (int) $stmt->fetchColumn();

        // 5. Send email
        if ($is_new_user) {
            send_punktepass_welcome_email($email, $name, $generated_password, $total_points);
        } else {
            send_punktepass_points_email($email, $existing_user['first_name'] ?: $name, REPAIR_BONUS_POINTS, $total_points);
        }

        return [
            'success' => true,
            'is_new_user' => $is_new_user,
            'user_id' => $user_id,
            'points_added' => REPAIR_BONUS_POINTS,
            'total_points' => $total_points,
            'password' => $generated_password // null for existing users
        ];

    } catch (Exception $e) {
        error_log("[PunktePass Integration] Error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Generate a readable password (8 chars, letters + digits)
 */
function generate_secure_password($length = 8) {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Generate a random alphanumeric token
 */
function generate_random_token($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $token;
}

/**
 * Send welcome email to new PunktePass user
 */
function send_punktepass_welcome_email($email, $name, $password, $total_points) {
    $first_name = explode(' ', trim($name))[0] ?: 'Kunde';
    $points_to_reward = 4 - $total_points;
    $points_to_reward = max(0, $points_to_reward);

    $subject = 'Willkommen bei PunktePass - Ihre Zugangsdaten + ' . REPAIR_BONUS_POINTS . ' Bonuspunkte!';

    $body = '<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
<div style="max-width:560px;margin:0 auto;padding:20px;">

<!-- Header -->
<div style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:16px 16px 0 0;padding:32px 28px;text-align:center;">
    <h1 style="color:#fff;font-size:24px;margin:0 0 8px;">Willkommen bei PunktePass!</h1>
    <p style="color:rgba(255,255,255,0.85);font-size:14px;margin:0;">Ihr Treuekonto bei eRepairShop</p>
</div>

<!-- Body -->
<div style="background:#fff;padding:32px 28px;border-radius:0 0 16px 16px;">

    <p style="font-size:16px;color:#1f2937;margin:0 0 20px;">Hallo <strong>' . htmlspecialchars($first_name) . '</strong>,</p>

    <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
        vielen Dank für Ihren Reparaturauftrag bei eRepairShop! Wir haben automatisch ein
        <strong>PunktePass-Konto</strong> für Sie erstellt, mit dem Sie bei jedem Besuch Treuepunkte sammeln können.
    </p>

    <!-- Points Badge -->
    <div style="background:linear-gradient(135deg,#f0fdf4,#ecfdf5);border:1px solid #bbf7d0;border-radius:12px;padding:20px;text-align:center;margin:0 0 24px;">
        <div style="font-size:36px;font-weight:800;color:#059669;">' . REPAIR_BONUS_POINTS . ' Punkte</div>
        <div style="font-size:13px;color:#6b7280;margin-top:4px;">wurden Ihrem Konto gutgeschrieben</div>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #d1fae5;font-size:13px;color:#374151;">
            Gesamt: <strong>' . $total_points . ' / 4 Punkte</strong>'
            . ($points_to_reward > 0 ? ' &mdash; noch ' . $points_to_reward . ' bis zum <strong>10&euro; Rabatt!</strong>' : ' &mdash; <strong style="color:#059669;">10&euro; Rabatt einlösbar!</strong>')
            . '
        </div>
    </div>

    <!-- Credentials -->
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin:0 0 24px;">
        <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:12px;text-transform:uppercase;letter-spacing:0.5px;">Ihre Zugangsdaten</div>
        <table style="width:100%;font-size:14px;color:#1f2937;">
            <tr>
                <td style="padding:6px 0;color:#6b7280;width:100px;">E-Mail:</td>
                <td style="padding:6px 0;font-weight:600;">' . htmlspecialchars($email) . '</td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#6b7280;">Passwort:</td>
                <td style="padding:6px 0;font-weight:600;font-family:monospace;letter-spacing:1px;">' . htmlspecialchars($password) . '</td>
            </tr>
        </table>
    </div>

    <p style="font-size:13px;color:#6b7280;line-height:1.6;margin:0 0 24px;">
        Mit der PunktePass-App können Sie Ihren QR-Code vorzeigen und bei jedem Besuch Punkte sammeln.
        Öffnen Sie einfach <strong>punktepass.de</strong> und melden Sie sich mit Ihren Zugangsdaten an.
    </p>

    <!-- CTA -->
    <div style="text-align:center;">
        <a href="https://punktepass.de" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;font-size:15px;">
            Jetzt bei PunktePass anmelden
        </a>
    </div>
</div>

<!-- Footer -->
<div style="text-align:center;padding:20px;font-size:12px;color:#9ca3af;">
    <p>eRepairShop &middot; Siedlungsring 51, 89415 Lauingen</p>
    <p>Diese E-Mail wurde automatisch gesendet.</p>
</div>

</div>
</body>
</html>';

    send_html_email($email, $subject, $body);
}

/**
 * Send points notification email to existing PunktePass user
 */
function send_punktepass_points_email($email, $name, $points_added, $total_points) {
    $first_name = explode(' ', trim($name))[0] ?: 'Kunde';
    $points_to_reward = 4 - $total_points;
    $points_to_reward = max(0, $points_to_reward);

    $subject = '+' . $points_added . ' Bonuspunkte auf Ihr PunktePass-Konto!';

    $body = '<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
<div style="max-width:560px;margin:0 auto;padding:20px;">

<!-- Header -->
<div style="background:linear-gradient(135deg,#10b981 0%,#059669 100%);border-radius:16px 16px 0 0;padding:32px 28px;text-align:center;">
    <div style="font-size:48px;font-weight:800;color:#fff;">+' . $points_added . '</div>
    <p style="color:rgba(255,255,255,0.9);font-size:15px;margin:8px 0 0;font-weight:500;">Bonuspunkte gutgeschrieben</p>
</div>

<!-- Body -->
<div style="background:#fff;padding:32px 28px;border-radius:0 0 16px 16px;">

    <p style="font-size:16px;color:#1f2937;margin:0 0 20px;">Hallo <strong>' . htmlspecialchars($first_name) . '</strong>,</p>

    <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
        vielen Dank für Ihren Reparaturauftrag! Wir haben <strong>' . $points_added . ' Bonuspunkte</strong>
        auf Ihr PunktePass-Konto gutgeschrieben.
    </p>

    <!-- Points Status -->
    <div style="background:linear-gradient(135deg,#f0fdf4,#ecfdf5);border:1px solid #bbf7d0;border-radius:12px;padding:20px;text-align:center;margin:0 0 24px;">
        <div style="font-size:13px;color:#6b7280;margin-bottom:4px;">Ihr Punktestand bei eRepairShop</div>
        <div style="font-size:36px;font-weight:800;color:#059669;">' . $total_points . ' Punkte</div>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #d1fae5;font-size:13px;color:#374151;">'
            . ($points_to_reward > 0 ? 'Noch ' . $points_to_reward . ' Punkte bis zum <strong>10&euro; Rabatt!</strong>' : '<strong style="color:#059669;">10&euro; Rabatt einlösbar!</strong>')
            . '
        </div>
    </div>

    <div style="text-align:center;">
        <a href="https://punktepass.de" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;font-size:15px;">
            Punkte ansehen
        </a>
    </div>
</div>

<!-- Footer -->
<div style="text-align:center;padding:20px;font-size:12px;color:#9ca3af;">
    <p>eRepairShop &middot; Siedlungsring 51, 89415 Lauingen</p>
</div>

</div>
</body>
</html>';

    send_html_email($email, $subject, $body);
}

/**
 * Send HTML email using SMTP (shared with WordPress SMTP config)
 */
function send_html_email($to, $subject, $html_body) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: PunktePass <noreply@punktepass.de>\r\n";

    $sent = @mail($to, $subject, $html_body, $headers);

    if (!$sent) {
        error_log("[PunktePass Integration] Failed to send email to: {$to}");
    }

    return $sent;
}
