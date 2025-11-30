<?php
/**
 * Google OAuth Callback for iOS App
 * Redirects the OAuth response to the iOS app via custom URL scheme
 */

// Get the authorization code from Google
$code = $_GET['code'] ?? null;
$error = $_GET['error'] ?? null;

// iOS app custom URL scheme (reversed client ID)
$appScheme = 'com.googleusercontent.apps.645942978357-1bdviltt810gutpve9vjj2kab340man6';

if ($error) {
    // Redirect error to app
    $redirectUrl = $appScheme . ':/oauth2redirect?error=' . urlencode($error);
} elseif ($code) {
    // Redirect code to app
    $redirectUrl = $appScheme . ':/oauth2redirect?code=' . urlencode($code);
} else {
    // No code or error - redirect to app with error
    $redirectUrl = $appScheme . ':/oauth2redirect?error=no_code';
}

// Redirect to iOS app
header('Location: ' . $redirectUrl);
exit;
