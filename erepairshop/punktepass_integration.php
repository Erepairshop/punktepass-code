<?php
/**
 * PunktePass Integration for eRepairShop
 *
 * Calls the PunktePass REST API on punktepass.de to:
 * - Check if user exists, create if not
 * - Add bonus points
 * - Send email (handled by PunktePass server)
 */

// PunktePass API configuration
define('PUNKTEPASS_API_URL', 'https://punktepass.de/wp-json/punktepass/v1/repair-bonus');
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
        return ['success' => false, 'message' => 'Ungültige E-Mail-Adresse'];
    }

    $payload = json_encode([
        'email'     => $email,
        'name'      => $name,
        'store_id'  => EREPAIRSHOP_STORE_ID,
        'points'    => REPAIR_BONUS_POINTS,
        'reference' => 'Reparatur-Formular Bonus',
    ]);

    $ch = curl_init(PUNKTEPASS_API_URL);
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
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("[PunktePass Integration] cURL error: {$curl_error}");
        return ['success' => false, 'message' => 'Verbindungsfehler: ' . $curl_error];
    }

    $data = json_decode($response, true);

    if ($http_code !== 200 || !$data || ($data['status'] ?? '') !== 'ok') {
        $msg = $data['message'] ?? "HTTP {$http_code}";
        error_log("[PunktePass Integration] API error: {$msg} (HTTP {$http_code})");
        return ['success' => false, 'message' => $msg];
    }

    return [
        'success'      => true,
        'is_new_user'  => $data['is_new_user'] ?? false,
        'user_id'      => $data['user_id'] ?? 0,
        'points_added' => $data['points_added'] ?? REPAIR_BONUS_POINTS,
        'total_points' => $data['total_points'] ?? 0,
    ];
}
