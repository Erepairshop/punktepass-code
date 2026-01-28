<?php
/**
 * PunktePass Integration for eRepairShop
 *
 * Calls the PunktePass REST API on punktepass.de to:
 * - Check if user exists, create if not
 * - Add bonus points
 * - Send email (handled by PunktePass server)
 */

// PunktePass API configuration (standalone endpoint - bypasses WP REST API filters)
define('PUNKTEPASS_API_URL', 'https://punktepass.de/wp-content/plugins/punktepass/api-repair-bonus.php');
define('PUNKTEPASS_API_KEY', '7b6e6938a91011f0bca9a33a376863b7'); // Store 9 API key
define('EREPAIRSHOP_STORE_ID', 9);
define('REPAIR_BONUS_POINTS', 2);

/**
 * Process PunktePass integration for a repair form submission.
 *
 * @param string $email    Customer email
 * @param string $name     Customer name (for new user creation)
 * @return array           Result with keys: success, is_new_user, user_id, total_points, points_added
 */
function punktepass_process_repair($email, $name = '') {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("[PunktePass] Invalid email: '{$email}'");
        return ['success' => false, 'message' => 'Ungültige E-Mail-Adresse', 'debug' => 'Email validation failed'];
    }

    // Include api_key in body as fallback (some shared hosts strip custom headers)
    $payload = json_encode([
        'email'     => $email,
        'name'      => $name,
        'store_id'  => EREPAIRSHOP_STORE_ID,
        'points'    => REPAIR_BONUS_POINTS,
        'reference' => 'Reparatur-Formular Bonus',
        'api_key'   => PUNKTEPASS_API_KEY,
    ]);

    // Append api_key as URL query param (most reliable for shared hosting WAF/proxy)
    $api_url = PUNKTEPASS_API_URL . '?api_key=' . urlencode(PUNKTEPASS_API_KEY);

    error_log("[PunktePass] Calling API: " . $api_url . " for email: {$email}");

    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Api-Key: ' . PUNKTEPASS_API_KEY,
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    error_log("[PunktePass] Response HTTP {$http_code} from {$effective_url}");
    error_log("[PunktePass] Response body: " . substr($response ?: '(empty)', 0, 500));

    if ($curl_error) {
        error_log("[PunktePass] cURL error: {$curl_error}");
        return [
            'success' => false,
            'message' => 'Verbindungsfehler: ' . $curl_error,
            'debug'   => "cURL error: {$curl_error}\nURL: {$effective_url}\nHTTP: {$http_code}",
        ];
    }

    $data = json_decode($response, true);

    if ($http_code !== 200 || !$data || ($data['status'] ?? '') !== 'ok') {
        $msg = $data['message'] ?? $data['code'] ?? "HTTP {$http_code}";
        error_log("[PunktePass] API error: {$msg} (HTTP {$http_code})");
        return [
            'success'  => false,
            'message'  => $msg,
            'debug'    => "HTTP {$http_code} from {$effective_url}\nResponse: " . substr($response ?: '(empty)', 0, 800),
        ];
    }

    error_log("[PunktePass] Success! user_id={$data['user_id']}, new=" . ($data['is_new_user'] ? 'yes' : 'no') . ", total={$data['total_points']}");

    return [
        'success'      => true,
        'is_new_user'  => $data['is_new_user'] ?? false,
        'user_id'      => $data['user_id'] ?? 0,
        'points_added' => $data['points_added'] ?? REPAIR_BONUS_POINTS,
        'total_points' => $data['total_points'] ?? 0,
    ];
}
