<?php

if (PHP_SAPI !== 'cli' || getenv('PPV_PASSWORD_RESET_SMOKE') !== '1') {
    fwrite(STDERR, "Password reset smoke test is CLI-only and requires PPV_PASSWORD_RESET_SMOKE=1\n");
    exit(1);
}
if (!class_exists('PPV_Login')) {
    fwrite(STDERR, "PPV_Login is not loaded\n");
    exit(1);
}

$email = sanitize_email((string)getenv('PPV_RESET_SMOKE_EMAIL'));
if (!$email) {
    fwrite(STDERR, "PPV_RESET_SMOKE_EMAIL is required\n");
    exit(1);
}

$method = static function (string $name): ReflectionMethod {
    $reflection = new ReflectionMethod('PPV_Login', $name);
    $reflection->setAccessible(true);
    return $reflection;
};

$emailKey = $method('password_reset_email_key')->invoke(null, $email);
$throttleKey = $method('password_reset_throttle_key')->invoke(null, $email);
$previousHash = get_transient($emailKey);
if (is_string($previousHash) && preg_match('/^[a-f0-9]{64}$/', $previousHash)) {
    delete_transient('ppv_pw_reset_' . $previousHash);
}
delete_transient($emailKey);
delete_transient($throttleKey);

$captured = null;
add_filter('pre_wp_mail', static function ($return, array $atts) use (&$captured) {
    $captured = $atts;
    return true;
}, 10, 2);

$method('request_password_reset')->invoke(null, $email, 'de');
$message = is_array($captured) ? (string)($captured['message'] ?? '') : '';
$tokenFound = preg_match('/token=([a-f0-9]{64})/', $message, $matches) === 1;
$token = $tokenFound ? $matches[1] : '';
$valid = $tokenFound && $method('validate_password_reset_token')->invoke(null, $token, $email) === true;
$currentHash = get_transient($emailKey);
$stored = is_string($currentHash) && hash_equals($currentHash, hash('sha256', $token));

if ($tokenFound) delete_transient($method('password_reset_transient_key')->invoke(null, $token));
delete_transient($emailKey);
delete_transient($throttleKey);

$result = [
    'mailIntercepted' => is_array($captured),
    'tokenFound' => $tokenFound,
    'tokenStored' => $stored,
    'tokenValidated' => $valid,
    'tokenCleaned' => $tokenFound ? get_transient($method('password_reset_transient_key')->invoke(null, $token)) === false : false,
];
$smtp = get_option('ppv_smtp_settings', []);
$result['smtpConfigured'] = !empty($smtp['enabled']) && !empty($smtp['host']) && !empty($smtp['username']) && !empty($smtp['password']) && !empty($smtp['from_email']);
echo wp_json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
if (in_array(false, $result, true)) exit(1);
