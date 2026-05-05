<?php
/**
 * Push notification stack — include before </body> on user-facing standalone pages
 * to ensure push registration runs on every page (not just user_dashboard).
 *
 * Required vars in scope: $uid (int|null), $lang (string), $plugin_url (string), $version (string|int)
 */
if (!defined('ABSPATH')) return;
$uid_for_push = isset($uid) ? (int)$uid : (!empty($_SESSION['ppv_user_id']) ? (int)$_SESSION['ppv_user_id'] : 0);
$lang_for_push = isset($lang) ? $lang : (isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : 'de');
?>
<script>window.ppvUserId = <?php echo (int)$uid_for_push; ?>; window.ppvLang = "<?php echo esc_js($lang_for_push); ?>";</script>
<script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-firebase-messaging.js?v=<?php echo esc_attr($version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-push-bridge.js?v=<?php echo esc_attr($version); ?>"></script>
