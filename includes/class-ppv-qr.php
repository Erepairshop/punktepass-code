<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Kassenscanner & Kampagnen v5.3 COMPLETE PHP
 * ✅ Gratis Termék opcióval
 * ✅ Dinamikus mezők
 * ✅ Teljes kampány kezelés
 * Author: Erik Borota / PunktePass
 */

class PPV_QR {

    // ============================================================
    // 🔹 INITIALIZATION
    // ============================================================
    public static function hooks() {
        add_shortcode('ppv_qr_center', [__CLASS__, 'render_qr_center']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    // ============================================================
    // 🧠 HELPERS
    // ============================================================

    private static function get_lang() {
        static $lang = null;
        if ($lang === null) {
            $lang = class_exists('PPV_Lang') ? (PPV_Lang::$strings ?? []) : [];
        }
        return $lang;
    }

    private static function t($key, $fallback = '') {
        $L = self::get_lang();
        return esc_html($L[$key] ?? $fallback);
    }

    private static function get_store_by_key($store_key) {
        if (empty($store_key)) return null;

        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email FROM {$wpdb->prefix}ppv_stores WHERE store_key=%s LIMIT 1",
            $store_key
        ));

        return $store ?: null;
    }

    private static function validate_store($store_key) {
        $store = self::get_store_by_key($store_key);

        if (!$store) {
            return [
                'valid' => false,
                'response' => new WP_REST_Response([
                    'success' => false,
                    'message' => self::t('err_unknown_store', '❌ Ismeretlen bolt')
                ], 400)
            ];
        }

        return [
            'valid' => true,
            'store' => $store
        ];
    }

    private function prepare_campaign_fields($data, $store_id) {
    $fields = [];

    // 🎯 Alap adatok
    $fields['store_id'] = (int) $store_id;
    $fields['title'] = sanitize_text_field($data['title'] ?? '');
    $fields['description'] = sanitize_textarea_field($data['description'] ?? '');
    $fields['status'] = sanitize_text_field($data['status'] ?? 'active');
    $fields['start_date'] = sanitize_text_field($data['start_date'] ?? '');
    $fields['end_date'] = sanitize_text_field($data['end_date'] ?? '');
    $fields['campaign_type'] = sanitize_text_field($data['campaign_type'] ?? '');

    $type = $fields['campaign_type'];

    // 🧠 Kampány típus szerint értékek hozzárendelése
    switch ($type) {
        case 'points':
            // pl. +50 extra pont
            $fields['extra_points'] = (int)($data['camp_value'] ?? 0);
            break;

        case 'discount':
            // pl. -20% kedvezmény
            $fields['discount_percent'] = (float)($data['camp_value'] ?? 0);
            break;

        case 'fixed':
            // pl. -5€ fix kedvezmény
            $fields['fixed_amount'] = (float)($data['camp_value'] ?? 0);
            break;

        case 'free_product':
            // 🎁 Ajándék termék kampány
            $fields['free_product'] = sanitize_text_field($data['free_product'] ?? '');
            $fields['free_product_value'] = floatval($data['free_product_value'] ?? 0);
            break;

        default:
            // ha típus ismeretlen, biztonsági fallback
            $fields['extra_points'] = 0;
            break;
    }

    // 🪙 Kiegészítő mezők (opcionális)
    $fields['required_points'] = (int)($data['required_points'] ?? 0);
    $fields['points_given'] = (int)($data['points_given'] ?? 0);

    return $fields;
}


    private static function check_rate_limit($user_id, $store_id) {
        global $wpdb;

        // Get store name for error responses
        $store_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store_id
        ));

        // 1) Check if already scanned TODAY (daily limit: 1 scan per day per store)
        $already_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
            WHERE user_id=%d AND store_id=%d
            AND DATE(created)=CURDATE()
            AND type='qr_scan'
        ", $user_id, $store_id));

        if ($already_today > 0) {
            // Log the existing scan details
            $existing_scan = $wpdb->get_row($wpdb->prepare("
                SELECT created, points FROM {$wpdb->prefix}ppv_points
                WHERE user_id=%d AND store_id=%d
                AND DATE(created)=CURDATE()
                AND type='qr_scan'
                ORDER BY created DESC LIMIT 1
            ", $user_id, $store_id));

            return [
                'limited' => true,
                'response' => new WP_REST_Response([
                    'success' => false,
                    'message' => self::t('err_already_scanned_today', '⚠️ Heute bereits gescannt'),
                    'store_name' => $store_name ?? 'PunktePass',
                    'error_type' => 'already_scanned_today'
                ], 429)
            ];
        }

        // 2) Check for duplicate scan (within 2 minutes - prevents retry spam)
        // FIXED: Check wp_ppv_points instead of wp_ppv_pos_log
        // Log table contains ALL attempts (successful and failed), causing false positives
        $recent = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}ppv_points
            WHERE user_id=%d AND store_id=%d
            AND created >= (NOW() - INTERVAL 2 MINUTE)
            AND type='qr_scan'
        ", $user_id, $store_id));

        if ($recent) {
            return [
                'limited' => true,
                'response' => new WP_REST_Response([
                    'success' => false,
                    'message' => self::t('err_duplicate_scan', '⚠️ Bereits gescannt. Bitte warten.'),
                    'store_name' => $store_name ?? 'PunktePass',
                    'error_type' => 'duplicate_scan'
                ], 429)
            ];
        }

        return ['limited' => false];
    }

    private static function insert_log($store_id, $user_id, $msg, $type = 'scan', $error_type = null) {
        global $wpdb;

        // Get IP address
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_address = sanitize_text_field(explode(',', $ip_address)[0]);

        // Get user agent
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        // Prepare metadata (can be extended with additional info)
        $metadata_array = [
            'timestamp' => current_time('mysql'),
            'type' => $type
        ];

        // Add error_type to metadata if provided (for client-side translation)
        if ($error_type !== null) {
            $metadata_array['error_type'] = $error_type;
        }

        $metadata = json_encode($metadata_array);

        $wpdb->insert("{$wpdb->prefix}ppv_pos_log", [
            'store_id' => $store_id,
            'user_id' => $user_id,
            'message' => sanitize_text_field($msg),
            'type' => $type,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'metadata' => $metadata,
            'created_at' => current_time('mysql')
        ]);
    }

    private static function decode_user_from_qr($qr) {
        if (empty($qr)) return false;

        if (strpos($qr, 'PPU') === 0) {
            $body = substr($qr, 3);
            if (preg_match('/^(\d+)/', $body, $m)) {
                return intval($m[1]);
            }
        }

        if (strpos($qr, 'PPUSER-') === 0) {
            $parts = explode('-', $qr);
            return intval($parts[1] ?? 0);
        }

        return false;
    }

    // ============================================================
    // 📡 ASSET ENQUEUE
    // ============================================================
    public static function enqueue_assets() {
        global $wpdb;

        // ✅ SESSION INICIALIZÁLÁS
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // ⛔ PERMISSION CHECK: Only load camera scanner JS for handlers/scanners
        $is_handler = false;

        // Check session user type
        if (!empty($_SESSION['ppv_user_id'])) {
            $user_type = $_SESSION['ppv_user_type'] ?? '';

            // If not set in session, check database
            if (empty($user_type)) {
                $user_type = $wpdb->get_var($wpdb->prepare(
                    "SELECT user_type FROM {$wpdb->prefix}ppv_users WHERE id=%d LIMIT 1",
                    $_SESSION['ppv_user_id']
                ));
            }

            $handler_types = ['store', 'handler', 'vendor', 'admin', 'scanner'];
            $is_handler = in_array(strtolower(trim($user_type)), $handler_types);
        }

        wp_enqueue_style(
            'remixicons',
            'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css',
            [],
            null
        );

        // Only enqueue camera scanner JS for handlers/scanners
        if ($is_handler) {
            wp_enqueue_script('ppv-qr', PPV_PLUGIN_URL . 'assets/js/ppv-qr.js', ['jquery'], time(), true);

            $lang = sanitize_text_field($_COOKIE['ppv_lang'] ?? '');
            if (empty($lang) || !in_array($lang, ['de', 'hu', 'ro'])) {
                $lang = defined('PPV_LANG_ACTIVE') ? PPV_LANG_ACTIVE : 'de';
            }

            if (class_exists('PPV_Lang')) {
                $file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-{$lang}.php";
                if (!file_exists($file)) {
                    $file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-de.php";
                }
                $strings = include $file;
            } else {
                $strings = [];
            }

            wp_add_inline_script('ppv-qr', 'window.ppv_lang = ' . wp_json_encode($strings) . ';', 'before');

            $store_id = 0;
            $store_key = '';

            // 1️⃣ SESSION
            if (!empty($_SESSION['ppv_active_store'])) {
                $store_id = intval($_SESSION['ppv_active_store']);
            }
            // 2️⃣ GLOBAL
            elseif (!empty($GLOBALS['ppv_active_store'])) {
                $active = $GLOBALS['ppv_active_store'];
                $store_id = is_object($active) ? intval($active->id) : intval($active);
            }
            // 3️⃣ LOGGED IN USER
            elseif (is_user_logged_in()) {
                $uid = get_current_user_id();
                $store_id = intval($wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                    $uid
                )));
            }
            // 4️⃣ ADMIN FALLBACK
            else {
                if (current_user_can('administrator')) {
                    $row = $wpdb->get_row("SELECT id, store_key FROM {$wpdb->prefix}ppv_stores WHERE id=1 LIMIT 1");
                    if ($row) {
                        $store_id = $row->id;
                        $store_key = $row->store_key;
                    }
                }
            }

            // Fetch store_key if store_id is set
            if ($store_id > 0 && empty($store_key)) {
                $store_key = $wpdb->get_var($wpdb->prepare(
                    "SELECT store_key FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
                    $store_id
                ));
            }

            wp_localize_script('ppv-qr', 'PPV_STORE_DATA', [
                'store_id' => intval($store_id),
                'store_key' => $store_key ?: '',
            ]);

            wp_enqueue_script('ppv-hidden-scan', PPV_PLUGIN_URL . 'assets/js/ppv-hidden-scan.js', [], time(), true);

            $lang = defined('PPV_LANG_ACTIVE') ? PPV_LANG_ACTIVE : (get_locale() ?? 'de');
            wp_localize_script('ppv-hidden-scan', 'PPV_SCAN_DATA', [
                'rest_url' => esc_url(rest_url('punktepass/v1/pos/scan')),
                'store_key' => $store_key ?: '',
                'plugin_url' => PPV_PLUGIN_URL,
                'lang' => substr($lang, 0, 2),
            ]);
        }
    }

    // ============================================================
    // 🎨 FRONTEND RENDERING
    // ============================================================
    public static function render_qr_center() {
        global $wpdb;

        // ⛔ PERMISSION CHECK: Only handlers and scanners can access
        if (!class_exists('PPV_Permissions')) {
            return '<div class="ppv-error" style="padding: 20px; text-align: center; color: #ff5252;">
                ❌ Zugriff verweigert. Nur für Händler und Scanner.
            </div>';
        }

        $auth_check = PPV_Permissions::check_handler();
        if (is_wp_error($auth_check)) {
            return '<div class="ppv-error" style="padding: 20px; text-align: center; color: #ff5252;">
                ❌ Zugriff verweigert. Nur für Händler und Scanner.
            </div>';
        }

        $lang = sanitize_text_field($_COOKIE['ppv_lang'] ?? '');
        if (empty($lang) || !in_array($lang, ['de', 'hu', 'ro'])) {
            $lang = defined('PPV_LANG_ACTIVE') ? PPV_LANG_ACTIVE : 'de';
        }

        $lang_file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-{$lang}.php";
        if (!file_exists($lang_file)) {
            $lang_file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-de.php";
        }
        $GLOBALS['ppv_current_lang'] = include $lang_file;

        ?>
        <?php
        // ✅ Check if scanner user (only show scanner interface)
        $is_scanner = class_exists('PPV_Permissions') && PPV_Permissions::is_scanner_user();
        ?>

        <?php if ($is_scanner): ?>
            <!-- SCANNER USER: Only Scanner Interface -->
            <div class="ppv-pos-center glass-section">
                <?php self::render_pos_scanner(); ?>
            </div>
        <?php else: ?>
            <!-- HANDLER USER: Full Interface with Tabs -->
            <div class="ppv-qr-wrapper glass-card">
                <h2>📡 <?php echo self::t('qrcamp_title', 'Kassenscanner & Kampagnen'); ?></h2>

                <!-- FILIALE SWITCHER -->
                <?php self::render_filiale_switcher(); ?>

                <div class="ppv-tabs">
                    <button class="ppv-tab active" data-tab="scanner" id="ppv-tab-scanner">
                        📲 <?php echo self::t('tab_scanner', 'Kassenscanner'); ?>
                    </button>
                    <button class="ppv-tab" data-tab="rewards" id="ppv-tab-rewards">
                        🎁 <?php echo self::t('tab_rewards', 'Prämien'); ?>
                    </button>
                    <button class="ppv-tab" data-tab="campaigns" id="ppv-tab-campaigns">
                        🎯 <?php echo self::t('tab_campaigns', 'Kampagnen'); ?>
                    </button>
                    <button class="ppv-tab" data-tab="scanner-users" id="ppv-tab-scanner-users">
                        👥 <?php echo self::t('tab_scanner_users', 'Scanner Felhasználók'); ?>
                    </button>
                </div>

                <!-- TAB CONTENT: SCANNER -->
                <div class="ppv-tab-content active" id="tab-scanner">
                    <?php self::render_pos_scanner(); ?>
                </div>

                <!-- TAB CONTENT: PRÄMIEN -->
                <div class="ppv-tab-content" id="tab-rewards">
                    <?php echo do_shortcode('[ppv_rewards_management]'); ?>
                </div>

                <!-- TAB CONTENT: KAMPAGNEN -->
                <div class="ppv-tab-content" id="tab-campaigns">
                    <?php self::render_campaigns(); ?>
                </div>

                <!-- TAB CONTENT: SCANNER FELHASZNÁLÓK -->
                <div class="ppv-tab-content" id="tab-scanner-users">
                    <?php self::render_scanner_users(); ?>
                </div>
            </div>
        <?php endif; ?>

        <script>
        jQuery(document).ready(function($){
            $(".ppv-tab").on("click", function(){
                $(".ppv-tab").removeClass("active");
                $(this).addClass("active");
                $(".ppv-tab-content").removeClass("active");
                $("#tab-" + $(this).data("tab")).addClass("active");
            });
        });
        </script>

        <?php
        if (class_exists('PPV_Bottom_Nav')) {
            echo PPV_Bottom_Nav::render_nav();
        } else {
            echo do_shortcode('[ppv_bottom_nav]');
        }
    }

    // ============================================================
    // 🏪 FILIALE SWITCHER
    // ============================================================
    public static function render_filiale_switcher() {
        if (!class_exists('PPV_Filiale')) {
            return;
        }

        global $wpdb;
        $store = ppv_current_store();
        if (!$store) {
            return;
        }

        // Get parent store ID
        $parent_id = PPV_Filiale::get_parent_id($store->id);

        // Get all filialen for this parent
        $filialen = PPV_Filiale::get_filialen($parent_id);

        // If no filialen found, add current store as fallback
        if (empty($filialen)) {
            $filialen = [$store];
        }

        // Get current active filiale from session
        $current_filiale_id = PPV_Filiale::get_current_filiale();
        if (!$current_filiale_id) {
            $current_filiale_id = $store->id;
        }

        // Always show switcher (even for trial handlers, even if no branches yet)
        // This allows adding new filialen

        ?>
        <div class="ppv-filiale-switcher glass-section" style="margin-bottom: 20px; padding: 15px;">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <label for="ppv-filiale-select" style="font-weight: 600; margin: 0;">
                    🏪 <?php echo self::t('current_filiale', 'Aktuelle Filiale'); ?>:
                </label>

                <select id="ppv-filiale-select" style="flex: 1; min-width: 200px; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color, #e0e0e0); background: var(--bg-secondary, #fff); color: var(--text-primary, #333);">
                    <?php foreach ($filialen as $filiale): ?>
                        <option value="<?php echo esc_attr($filiale->id); ?>" <?php selected($filiale->id, $current_filiale_id); ?>>
                            <?php
                            echo esc_html($filiale->name);
                            // Show "Main Location" label for parent store
                            if ($filiale->id == $parent_id && (empty($filiale->parent_store_id) || $filiale->parent_store_id === null)) {
                                echo ' (' . self::t('main_location', 'Hauptstandort') . ')';
                            }
                            if (!empty($filiale->city)) {
                                echo ' - ' . esc_html($filiale->city);
                            }
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="button" id="ppv-add-filiale-btn" class="ppv-btn ppv-btn-primary" style="padding: 8px 16px; white-space: nowrap;">
                    ➕ <?php echo self::t('add_filiale', 'Neue Filiale'); ?>
                </button>
            </div>
        </div>

        <!-- ADD FILIALE MODAL -->
        <div id="ppv-add-filiale-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10000; padding: 20px; overflow-y: auto;">
            <div class="glass-card" style="max-width: 500px; margin: 50px auto; padding: 30px;">
                <h3 style="margin-top: 0;">➕ <?php echo self::t('add_filiale', 'Neue Filiale'); ?></h3>

                <div style="margin-bottom: 20px;">
                    <label for="ppv-new-filiale-name" style="display: block; margin-bottom: 8px; font-weight: 600;">
                        <?php echo self::t('filiale_name', 'Filialname'); ?>:
                    </label>
                    <input type="text" id="ppv-new-filiale-name" placeholder="<?php echo esc_attr(self::t('enter_filiale_name', 'Geben Sie den Filialnamen ein')); ?>" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color, #e0e0e0); background: var(--bg-secondary, #fff); color: var(--text-primary, #333);">
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" id="ppv-cancel-filiale-btn" class="ppv-btn ppv-btn-secondary" style="padding: 10px 20px;">
                        ❌ <?php echo self::t('cancel', 'Abbrechen'); ?>
                    </button>
                    <button type="button" id="ppv-save-filiale-btn" class="ppv-btn ppv-btn-primary" style="padding: 10px 20px;">
                        ✅ <?php echo self::t('create_filiale', 'Filiale erstellen'); ?>
                    </button>
                </div>

                <div id="ppv-filiale-message" style="margin-top: 15px; padding: 10px; border-radius: 8px; display: none;"></div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            // Switch filiale on dropdown change
            $('#ppv-filiale-select').on('change', function(){
                const filialeId = $(this).val();
                const $select = $(this);
                const originalValue = $select.data('original-value') || $select.val();

                $select.prop('disabled', true);

                // Show loading message
                $('<div class="ppv-loading-overlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;font-weight:bold;">⏳ <?php echo esc_js(self::t('switching_filiale', 'Wechselt...')); ?></div>').appendTo('body');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppv_switch_filiale',
                        filiale_id: filialeId
                    },
                    success: function(response) {
                        if (response.success) {
                            // Wait 300ms for session/cache to flush on server
                            setTimeout(function() {
                                // Force fresh page load (bypass all caches)
                                var url = window.location.href.split('?')[0];
                                window.location.href = url + '?_refresh=' + Date.now();
                            }, 300);
                        } else {
                            alert(response.data.msg || '<?php echo esc_js(self::t('filiale_error', 'Fehler beim Erstellen der Filiale')); ?>');
                            $select.val(originalValue);
                            $select.prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>');
                        $select.val(originalValue);
                        $select.prop('disabled', false);
                    }
                });
            });

            // Store original value for rollback
            $('#ppv-filiale-select').data('original-value', $('#ppv-filiale-select').val());

            // Show add filiale modal
            $('#ppv-add-filiale-btn').on('click', function(){
                $('#ppv-add-filiale-modal').fadeIn(200);
                $('#ppv-new-filiale-name').val('').focus();
                $('#ppv-filiale-message').hide();
            });

            // Hide modal
            $('#ppv-cancel-filiale-btn').on('click', function(){
                $('#ppv-add-filiale-modal').fadeOut(200);
            });

            // Close modal on background click
            $('#ppv-add-filiale-modal').on('click', function(e){
                if (e.target.id === 'ppv-add-filiale-modal') {
                    $(this).fadeOut(200);
                }
            });

            // Create new filiale
            $('#ppv-save-filiale-btn').on('click', function(){
                const filialeName = $('#ppv-new-filiale-name').val().trim();

                if (!filialeName) {
                    $('#ppv-filiale-message')
                        .removeClass('success').addClass('error')
                        .html('<?php echo esc_js(self::t('enter_filiale_name', 'Geben Sie den Filialnamen ein')); ?>')
                        .fadeIn();
                    return;
                }

                const $btn = $(this);
                const originalText = $btn.html();
                $btn.prop('disabled', true).html('⏳ <?php echo esc_js(self::t('creating_filiale', 'Filiale wird erstellt...')); ?>');
                $('#ppv-filiale-message').hide();

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppv_create_filiale',
                        parent_store_id: <?php echo intval($parent_id); ?>,
                        filiale_name: filialeName
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#ppv-filiale-message')
                                .removeClass('error').addClass('success')
                                .html('✅ ' + (response.data.msg || '<?php echo esc_js(self::t('filiale_created', 'Filiale erfolgreich erstellt!')); ?>'))
                                .fadeIn();

                            // Reload page after 1 second to show new filiale
                            setTimeout(function(){
                                window.location.reload();
                            }, 1000);
                        } else {
                            $('#ppv-filiale-message')
                                .removeClass('success').addClass('error')
                                .html('❌ ' + (response.data.msg || '<?php echo esc_js(self::t('filiale_error', 'Fehler beim Erstellen der Filiale')); ?>'))
                                .fadeIn();
                            $btn.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function() {
                        $('#ppv-filiale-message')
                            .removeClass('success').addClass('error')
                            .html('❌ <?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>')
                            .fadeIn();
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            });

            // Submit on Enter key
            $('#ppv-new-filiale-name').on('keypress', function(e){
                if (e.which === 13) {
                    e.preventDefault();
                    $('#ppv-save-filiale-btn').click();
                }
            });
        });
        </script>

        <style>
        .ppv-filiale-switcher .success {
            background-color: #4caf50;
            color: white;
        }
        .ppv-filiale-switcher .error {
            background-color: #ff5252;
            color: white;
        }
        </style>
        <?php
    }

    // ============================================================
    // 📲 POS SCANNER
    // ============================================================
    public static function render_pos_scanner() {
        global $wpdb;

        // Get store trial info
        $store_id = 0;
        $trial_days_left = 0;
        $subscription_days_left = 0;
        $subscription_status = 'unknown';
        $renewal_requested = false;

        // Try to get store ID from session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (!empty($_SESSION['ppv_active_store'])) {
            $store_id = intval($_SESSION['ppv_active_store']);
        } elseif (!empty($_SESSION['ppv_store_id'])) {
            $store_id = intval($_SESSION['ppv_store_id']);
        }

        // ✅ If no store_id in session, try to get it via user_id
        if ($store_id === 0 && !empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);

            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $user_id
            ));

            if ($store_id) {
                $store_id = intval($store_id);
            } else {
            }
        }

        // 🐛 DEBUG: Log store_id

        // Fetch subscription info from database
        if ($store_id > 0) {
            $store_data = $wpdb->get_row($wpdb->prepare(
                "SELECT trial_ends_at, subscription_status, subscription_expires_at, subscription_renewal_requested FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
                $store_id
            ));

            if ($store_data) {
                $subscription_status = $store_data->subscription_status ?? 'trial';
                $renewal_requested = !empty($store_data->subscription_renewal_requested);
                $now = current_time('timestamp');

                // Calculate trial days left
                if (!empty($store_data->trial_ends_at)) {
                    $trial_end = strtotime($store_data->trial_ends_at);
                    $diff_seconds = $trial_end - $now;
                    $trial_days_left = max(0, ceil($diff_seconds / 86400));
                }

                // Calculate subscription days left (for active subscriptions)
                if (!empty($store_data->subscription_expires_at)) {
                    $sub_end = strtotime($store_data->subscription_expires_at);
                    $diff_seconds = $sub_end - $now;
                    $subscription_days_left = max(0, ceil($diff_seconds / 86400));
                }
            }
        }

        // Determine message and styling based on days left
        $info_class = 'info';
        $info_icon = 'ℹ️';
        $info_color = 'rgba(0, 230, 255, 0.1)';
        $border_color = 'rgba(0, 230, 255, 0.3)';
        $show_description = false;

        // ✅ Check if renewal button should be shown
        $show_renewal_button = !$renewal_requested && (
            ($subscription_status === 'trial' && $trial_days_left === 0) ||
            ($subscription_status === 'active' && $subscription_days_left === 0)
        );

        // ✅ Check if upgrade button should be shown (trial with 7 days or less)
        $show_upgrade_button = !$renewal_requested && ($subscription_status === 'trial' && $trial_days_left <= 7 && $trial_days_left > 0);

        // 🐛 DEBUG: Log button visibility

        if ($subscription_status === 'active') {
            // Active subscription with expiry date
            if ($subscription_days_left > 0) {
                if ($subscription_days_left > 30) {
                    $info_message = sprintf(self::t('subscription_active_days', 'Aktives Abo - Noch %d Tage'), $subscription_days_left);
                    $info_class = 'success';
                    $info_icon = '✅';
                    $info_color = 'rgba(0, 230, 118, 0.1)';
                    $border_color = 'rgba(0, 230, 118, 0.3)';
                } elseif ($subscription_days_left > 7) {
                    $info_message = sprintf(self::t('subscription_expiring_soon', 'Abo läuft in %d Tagen ab'), $subscription_days_left);
                    $info_class = 'info';
                    $info_icon = '📅';
                    $info_color = 'rgba(0, 230, 255, 0.1)';
                    $border_color = 'rgba(0, 230, 255, 0.3)';
                } else {
                    $info_message = sprintf(self::t('subscription_expiring_warning', 'Abo endet bald - Nur noch %d Tage!'), $subscription_days_left);
                    $info_class = 'warning';
                    $info_icon = '⚠️';
                    $info_color = 'rgba(255, 171, 0, 0.1)';
                    $border_color = 'rgba(255, 171, 0, 0.3)';
                }
                $show_description = true;
            } else {
                // Active but expired
                $info_message = self::t('subscription_expired', 'Abo abgelaufen');
                $info_class = 'error';
                $info_icon = '❌';
                $info_color = 'rgba(239, 68, 68, 0.1)';
                $border_color = 'rgba(239, 68, 68, 0.3)';
            }
        } elseif ($trial_days_left > 7) {
            $info_message = sprintf(self::t('trial_days_left', 'Noch %d Tage Testversion'), $trial_days_left);
            $info_icon = '📅';
        } elseif ($trial_days_left > 0) {
            $info_message = sprintf(self::t('trial_days_left_warning', 'Nur noch %d Tage!'), $trial_days_left);
            $info_class = 'warning';
            $info_icon = '⚠️';
            $info_color = 'rgba(255, 171, 0, 0.1)';
            $border_color = 'rgba(255, 171, 0, 0.3)';
        } else {
            $info_message = self::t('trial_expired', 'Testversion abgelaufen');
            $info_class = 'error';
            $info_icon = '❌';
            $info_color = 'rgba(239, 68, 68, 0.1)';
            $border_color = 'rgba(239, 68, 68, 0.3)';
        }

        // ✅ Check if scanner user (don't show subscription info to scanners)
        $is_scanner = class_exists('PPV_Permissions') && PPV_Permissions::is_scanner_user();

        if (!$is_scanner): ?>
        <div class="ppv-trial-info-block" style="margin-bottom: 15px; padding: 12px 16px; background: <?php echo $info_color; ?>; border-radius: 10px; border: 2px solid <?php echo $border_color; ?>;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;"><?php echo $info_icon; ?></span>
                    <div>
                        <div style="font-weight: bold; font-size: 15px; margin-bottom: 2px;">
                            <?php echo $info_message; ?>
                        </div>
                        <?php if ($renewal_requested): ?>
                            <div style="font-size: 12px; opacity: 0.9; color: #00e6ff;">
                                <?php echo self::t('renewal_in_progress', 'Aboverlängerung in Bearbeitung - Wir kontaktieren Sie bald per E-Mail oder Telefon'); ?>
                            </div>
                        <?php elseif ($show_description && $subscription_status === 'active'): ?>
                            <div style="font-size: 12px; opacity: 0.7;">
                                <?php echo self::t('subscription_info_desc', 'Aktive Premium-Mitgliedschaft'); ?>
                            </div>
                        <?php elseif ($subscription_status === 'trial' && $trial_days_left > 0): ?>
                            <div style="font-size: 12px; opacity: 0.7;">
                                <?php echo self::t('trial_info_desc', 'Registriert mit 30 Tage Probezeit'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($show_upgrade_button): ?>
                    <button id="ppv-request-renewal-btn" class="ppv-btn-outline" style="padding: 6px 12px; font-size: 13px; white-space: nowrap;">
                        📧 <?php echo self::t('upgrade_now', 'Jetzt upgraden'); ?>
                    </button>
                <?php elseif ($show_renewal_button): ?>
                    <button id="ppv-request-renewal-btn" class="ppv-btn-outline" style="padding: 6px 12px; font-size: 13px; white-space: nowrap;">
                        📧 <?php echo self::t('request_renewal', 'Abo verlängern'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($show_renewal_button || $show_upgrade_button): ?>
        <!-- Renewal Request Modal -->
        <div id="ppv-renewal-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
            <div style="background: #1a1a2e; padding: 30px; border-radius: 15px; max-width: 400px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                <h3 style="margin-top: 0; color: #fff;">📧 <?php echo self::t('renewal_request_title', 'Abo Verlängerung anfragen'); ?></h3>
                <p style="color: #ccc; font-size: 14px;"><?php echo self::t('renewal_request_desc', 'Bitte geben Sie Ihre Telefonnummer ein. Wir kontaktieren Sie schnellstmöglich.'); ?></p>
                <input type="tel" id="ppv-renewal-phone" class="ppv-input" placeholder="<?php echo self::t('phone_placeholder', 'Telefonnummer'); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">
                <div id="ppv-renewal-error" style="display: none; color: #ff5252; font-size: 13px; margin-bottom: 10px;"></div>
                <div style="display: flex; gap: 10px;">
                    <button id="ppv-renewal-submit" class="ppv-btn" style="flex: 1; padding: 12px;">
                        ✅ <?php echo self::t('send_request', 'Anfrage senden'); ?>
                    </button>
                    <button id="ppv-renewal-cancel" class="ppv-btn-outline" style="flex: 1; padding: 12px;">
                        ❌ <?php echo self::t('cancel', 'Abbrechen'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; // End scanner check for subscription info ?>

        <!-- 🆘 Floating Support Button (ALWAYS SHOW - including scanner users) -->
        <button id="ppv-support-btn">
            🆘
        </button>

        <!-- Support Ticket Modal (ALWAYS SHOW - including scanner users) -->
        <div id="ppv-support-modal" class="ppv-support-modal">
            <div class="ppv-support-modal-content">
                <h3 class="ppv-support-modal-title">
                    🆘 <?php echo self::t('support_ticket_title', 'Support anfragen'); ?>
                </h3>
                <p class="ppv-support-modal-description">
                    <?php echo self::t('support_ticket_desc', 'Beschreiben Sie Ihr Problem. Wir melden uns schnellstmöglich bei Ihnen.'); ?>
                </p>

                <!-- Problem Description -->
                <label class="ppv-support-label">
                    <?php echo self::t('problem_description', 'Problembeschreibung'); ?> <span class="ppv-required">*</span>
                </label>
                <textarea id="ppv-support-description" class="ppv-support-input" placeholder="<?php echo self::t('problem_placeholder', 'Bitte beschreiben Sie Ihr Problem...'); ?>" rows="4"></textarea>

                <!-- Priority -->
                <label class="ppv-support-label">
                    <?php echo self::t('priority', 'Priorität'); ?>
                </label>
                <select id="ppv-support-priority" class="ppv-support-input">
                    <option value="normal"><?php echo self::t('priority_normal', 'Normal'); ?></option>
                    <option value="urgent"><?php echo self::t('priority_urgent', 'Dringend'); ?></option>
                    <option value="low"><?php echo self::t('priority_low', 'Niedrig'); ?></option>
                </select>

                <!-- Contact Preference -->
                <label class="ppv-support-label">
                    <?php echo self::t('contact_preference', 'Bevorzugter Kontakt'); ?>
                </label>
                <select id="ppv-support-contact" class="ppv-support-input">
                    <option value="email">📧 <?php echo self::t('contact_email', 'E-Mail'); ?></option>
                    <option value="phone">📞 <?php echo self::t('contact_phone', 'Telefon'); ?></option>
                    <option value="whatsapp">💬 <?php echo self::t('contact_whatsapp', 'WhatsApp'); ?></option>
                </select>

                <div id="ppv-support-error" class="ppv-support-message ppv-support-error"></div>
                <div id="ppv-support-success" class="ppv-support-message ppv-support-success"></div>

                <div class="ppv-support-buttons">
                    <button id="ppv-support-submit" class="ppv-support-btn-submit">
                        ✅ <?php echo self::t('send_ticket', 'Ticket senden'); ?>
                    </button>
                    <button id="ppv-support-cancel" class="ppv-support-btn-cancel">
                        ❌ <?php echo self::t('cancel', 'Abbrechen'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="ppv-pos-center glass-section">
            <div id="ppv-offline-banner" style="display:none;background:#ffcc00;padding:8px;border-radius:6px;margin-bottom:10px;">
                🛰️ <?php echo self::t('offline_banner', 'Offline-Modus aktiv'); ?>
                <button id="ppv-sync-btn" class="ppv-btn small" type="button">
                    <?php echo self::t('sync_now', 'Sync'); ?>
                </button>
            </div>

            <div class="ppv-pos-header">
                <h4 class="ppv-pos-title"><?php echo self::t('table_title', '📋 Letzte Scans'); ?></h4>

                <!-- 📥 CSV EXPORT DROPDOWN -->
                <div class="ppv-csv-wrapper">
                    <button id="ppv-csv-export-btn" class="ppv-btn ppv-csv-btn">
                        📥 <?php echo self::t('csv_export', 'CSV Export'); ?>
                    </button>
                    <div id="ppv-csv-export-menu" class="ppv-csv-dropdown">
                        <a href="#" class="ppv-csv-export-option" data-period="today">
                            📅 <?php echo self::t('csv_today', 'Heute'); ?>
                        </a>
                        <a href="#" class="ppv-csv-export-option" data-period="date">
                            📆 <?php echo self::t('csv_date', 'Datum wählen'); ?>
                        </a>
                        <a href="#" class="ppv-csv-export-option" data-period="month">
                            📊 <?php echo self::t('csv_month', 'Diesen Monat'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <table id="ppv-pos-log" class="glass-table">
                <thead>
                    <tr>
                        <th><?php echo self::t('t_col_time', 'Zeit'); ?></th>
                        <th><?php echo self::t('t_col_customer', 'Kunde'); ?></th>
                        <th><?php echo self::t('t_col_status', 'Status'); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <!-- 🎥 CAMERA SCANNER MODAL -->
            <div id="ppv-camera-modal" class="ppv-modal" role="dialog" aria-modal="true" style="display: none;">
                <div class="ppv-modal-inner" style="max-width: 100%; width: 100%; max-height: 90vh; overflow-y: auto;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h4 style="margin: 0;">📷 <?php echo self::t('camera_scanner_title', 'Kamera QR-Scanner'); ?></h4>
                        <button id="ppv-camera-close" class="ppv-btn-outline" type="button" style="width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center;">
                            ✕
                        </button>
                    </div>

                    <p style="color: #999; font-size: 14px; margin-bottom: 15px;">
                        🎥 <?php echo self::t('camera_scanner_desc', 'Halte den QR-Code des Kunden vor die Kamera. Der Scanner erkennt diesen automatisch.'); ?>
                    </p>

                    <!-- Scanner Area -->
                    <div id="ppv-reader" style="width: 100%; max-width: 400px; height: 400px; margin: 0 auto 20px; border: 2px solid #00e6ff; border-radius: 14px; overflow: hidden; background: #000;"></div>

                    <!-- Result Box -->
                    <div id="ppv-scan-result" style="text-align: center; padding: 15px; background: rgba(0, 230, 255, 0.1); border-radius: 8px; border: 1px solid rgba(0, 230, 255, 0.3); margin-bottom: 15px; min-height: 40px; display: flex; align-items: center; justify-content: center; color: #00e6ff; font-weight: bold;">
                        ⏳ Scanner aktiv...
                    </div>

                    <!-- Controls -->
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button id="ppv-torch-toggle" class="ppv-btn" type="button" style="display: none;">
                            🔦 Taschenlampe
                        </button>
                        <button id="ppv-camera-cancel" class="ppv-btn-outline" type="button" style="flex: 1;">
                            <?php echo self::t('btn_cancel', 'Abbrechen'); ?>
                        </button>
                    </div>

                </div>
            </div>
        </div>
        <?php
    }

    // ============================================================
    // 🎯 KAMPAGNEN - KOMPLETT FORMA
    // ============================================================
    public static function render_campaigns() {
        ?>
        <div class="ppv-campaigns glass-section">
            <div class="ppv-campaign-header">
                <h3>🎯 <?php echo self::t('campaigns_title', 'Kampagnen'); ?></h3>
                <div class="ppv-campaign-controls">
                    <select id="ppv-campaign-filter" class="ppv-filter">
                        <option value="all">📋 <?php echo self::t('camp_filter_all', 'Alle'); ?></option>
                        <option value="active">🟢 <?php echo self::t('camp_filter_active', 'Aktive'); ?></option>
                        <option value="archived">📦 <?php echo self::t('camp_filter_archived', 'Archiv'); ?></option>
                    </select>
                    <button id="ppv-new-campaign" class="ppv-btn neon" type="button"><?php echo self::t('camp_new', '+ Neue Kampagne'); ?></button>
                </div>
            </div>

            <div id="ppv-campaign-list" class="ppv-campaign-list"></div>

            <!-- 🎯 KAMPAGNE MODAL - KOMPLETT FORMA! -->
            <div id="ppv-campaign-modal" class="ppv-modal" role="dialog" aria-modal="true">
                <div class="ppv-modal-inner">
                    <h4><?php echo self::t('camp_edit_modal', 'Kampagne bearbeiten'); ?></h4>

                    <!-- TITEL -->
                    <label><?php echo self::t('label_title', 'Titel'); ?></label>
                    <input type="text" id="camp-title" placeholder="<?php echo esc_attr(self::t('camp_placeholder_title', 'z. B. Doppelte Punkte-Woche')); ?>">

                    <!-- STARTDATUM -->
                    <label><?php echo self::t('label_start', 'Startdatum'); ?></label>
                    <input type="date" id="camp-start">

                    <!-- ENDDATUM -->
                    <label><?php echo self::t('label_end', 'Enddatum'); ?></label>
                    <input type="date" id="camp-end">

                    <!-- KAMPAGNEN TYP -->
                    <label><?php echo self::t('label_type', 'Kampagnen Typ'); ?></label>
                    <select id="camp-type">
                        <option value="points"><?php echo self::t('type_points', 'Extra Punkte'); ?></option>
                        <option value="discount"><?php echo self::t('type_discount', 'Rabatt (%)'); ?></option>
                        <option value="fixed"><?php echo self::t('type_fixed', 'Fix Bonus (€)'); ?></option>
                        <option value="free_product">🎁 <?php echo self::t('type_free_product', 'Gratis Termék'); ?></option>
                    </select>

                    <!-- SZÜKSÉGES PONTOK (csak POINTS típusnál!) -->
                    <div id="camp-required-points-wrapper" style="display: none;">
                        <label><?php echo self::t('label_required_points', 'Szükséges pontok'); ?></label>
                        <input type="number" id="camp-required-points" value="0" min="0" step="1">
                    </div>

                    <!-- WERT -->
                    <label id="camp-value-label"><?php echo self::t('camp_value_label', 'Wert'); ?></label>
                    <input type="number" id="camp-value" value="0" min="0" step="0.1">

                    <!-- PONTOK PER SCAN (csak POINTS típusnál!) -->
                    <div id="camp-points-given-wrapper" style="display: none;">
                        <label><?php echo self::t('label_points_given', 'Pontok per scan'); ?></label>
                        <input type="number" id="camp-points-given" value="1" min="1" step="1">
                    </div>

                    <!-- GRATIS TERMÉK NEVE (csak FREE_PRODUCT típusnál!) -->
                    <div id="camp-free-product-name-wrapper" style="display: none;">
                        <label><?php echo self::t('label_free_product', '🎁 Termék neve'); ?></label>
                        <input type="text" id="camp-free-product-name" placeholder="<?php echo esc_attr(self::t('camp_placeholder_free_product', 'pl. Kávé + Sütemény')); ?>">
                    </div>

                    <!-- GRATIS TERMÉK ÉRTÉKE (csak ha van termék név!) -->
                    <div id="camp-free-product-value-wrapper" style="display: none;">
                        <label style="color: #ff9800;">💰 <?php echo self::t('label_free_product_value', 'Termék értéke'); ?> <span style="color: #ff0000;">*</span></label>
                        <input type="number" id="camp-free-product-value" value="0" min="0.01" step="0.01" placeholder="0.00" style="border-color: #ff9800;">
                    </div>

                    <!-- STATUS -->
                    <label><?php echo self::t('label_status', 'Status'); ?></label>
                    <select id="camp-status">
                        <option value="active"><?php echo self::t('status_active', '🟢 Aktiv'); ?></option>
                        <option value="archived"><?php echo self::t('status_archived', '📦 Archiv'); ?></option>
                    </select>

                    <!-- GOMBÓK -->
                    <div class="ppv-modal-actions">
                        <button id="camp-save" class="ppv-btn neon" type="button">
                            <?php echo self::t('btn_save', '💾 Speichern'); ?>
                        </button>
                        <button id="camp-cancel" class="ppv-btn-outline" type="button">
                            <?php echo self::t('btn_cancel', 'Abbrechen'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // ============================================================
    // 👥 RENDER SCANNER USERS MANAGEMENT
    // ============================================================
    public static function render_scanner_users() {
        global $wpdb;

        // Get current handler's store_id
        $store_id = 0;
        if (!empty($_SESSION['ppv_store_id'])) {
            $store_id = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $user_id
            ));
        }

        // Get all scanner users for this store (from PPV users table)
        $scanners = [];
        if ($store_id) {
            $scanners = $wpdb->get_results($wpdb->prepare(
                "SELECT id, email, created_at, active FROM {$wpdb->prefix}ppv_users
                 WHERE user_type = 'scanner' AND vendor_store_id = %d
                 ORDER BY created_at DESC",
                $store_id
            ));
        }

        ?>
        <div class="ppv-scanner-users glass-section">
            <div class="ppv-scanner-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>👥 <?php echo self::t('scanner_users_title', 'Scanner Felhasználók'); ?></h3>
                <button id="ppv-new-scanner-btn" class="ppv-btn neon" type="button">
                    ➕ <?php echo self::t('add_scanner_user', 'Új Scanner Létrehozása'); ?>
                </button>
            </div>

            <?php if (empty($scanners)): ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <p>📭 <?php echo self::t('no_scanner_users', 'Még nincs scanner felhasználó létrehozva.'); ?></p>
                </div>
            <?php else: ?>
                <div class="scanner-users-list">
                    <?php foreach ($scanners as $scanner): ?>
                        <?php
                        $is_active = $scanner->active == 1; // PPV users: 1 = active, 0 = disabled
                        $created_date = date('Y-m-d H:i', strtotime($scanner->created_at));
                        ?>
                        <div class="scanner-user-card glass-card" style="padding: 15px; margin-bottom: 15px; border-left: 4px solid <?php echo $is_active ? '#4caf50' : '#999'; ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-weight: bold; font-size: 16px; margin-bottom: 5px;">
                                        📧 <?php echo esc_html($scanner->email); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #999;">
                                        <?php echo self::t('created_at', 'Létrehozva'); ?>: <?php echo $created_date; ?>
                                    </div>
                                    <div style="margin-top: 5px;">
                                        <?php if ($is_active): ?>
                                            <span style="background: #4caf50; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px;">
                                                ✅ <?php echo self::t('status_active', 'Aktív'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="background: #999; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px;">
                                                🚫 <?php echo self::t('status_disabled', 'Letiltva'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <!-- Password Reset -->
                                    <button class="ppv-scanner-reset-pw ppv-btn-outline" data-user-id="<?php echo $scanner->id; ?>" data-email="<?php echo esc_attr($scanner->email); ?>" style="padding: 8px 12px; font-size: 13px;">
                                        🔄 <?php echo self::t('reset_password', 'Jelszó Reset'); ?>
                                    </button>

                                    <!-- Toggle Active/Disable -->
                                    <?php if ($is_active): ?>
                                        <button class="ppv-scanner-toggle ppv-btn-outline" data-user-id="<?php echo $scanner->id; ?>" data-action="disable" style="padding: 8px 12px; font-size: 13px; background: #f44336; color: white; border: none;">
                                            🚫 <?php echo self::t('disable', 'Letiltás'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button class="ppv-scanner-toggle ppv-btn" data-user-id="<?php echo $scanner->id; ?>" data-action="enable" style="padding: 8px 12px; font-size: 13px;">
                                            ✅ <?php echo self::t('enable', 'Engedélyezés'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Create Scanner Modal -->
            <div id="ppv-scanner-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
                <div style="background: #1a1a2e; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                    <h3 style="margin-top: 0; color: #fff;">➕ <?php echo self::t('create_scanner_title', 'Új Scanner Létrehozása'); ?></h3>

                    <label style="color: #fff; font-size: 13px; display: block; margin-bottom: 5px;">
                        <?php echo self::t('scanner_email', 'E-mail cím'); ?> <span style="color: #ff5252;">*</span>
                    </label>
                    <input type="email" id="ppv-scanner-email" class="ppv-input" placeholder="scanner@example.com" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">

                    <label style="color: #fff; font-size: 13px; display: block; margin-bottom: 5px;">
                        <?php echo self::t('scanner_password', 'Jelszó'); ?> <span style="color: #ff5252;">*</span>
                    </label>
                    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                        <input type="text" id="ppv-scanner-password" class="ppv-input" placeholder="<?php echo self::t('enter_password', 'Adjon meg jelszót'); ?>" style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff;">
                        <button id="ppv-scanner-gen-pw" class="ppv-btn-outline" style="padding: 12px; white-space: nowrap;">
                            🎲 <?php echo self::t('generate', 'Generálás'); ?>
                        </button>
                    </div>

                    <div id="ppv-scanner-error" style="display: none; color: #ff5252; font-size: 13px; margin-bottom: 10px; padding: 10px; background: rgba(255, 82, 82, 0.1); border-radius: 6px;"></div>
                    <div id="ppv-scanner-success" style="display: none; color: #4caf50; font-size: 13px; margin-bottom: 10px; padding: 10px; background: rgba(76, 175, 80, 0.1); border-radius: 6px;"></div>

                    <div style="display: flex; gap: 10px;">
                        <button id="ppv-scanner-create" class="ppv-btn" style="flex: 1; padding: 12px;">
                            ✅ <?php echo self::t('create', 'Létrehozás'); ?>
                        </button>
                        <button id="ppv-scanner-cancel" class="ppv-btn-outline" style="flex: 1; padding: 12px;">
                            ❌ <?php echo self::t('cancel', 'Mégse'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // ============================================================
    // 📡 REST ROUTES REGISTRATION
    // ============================================================
    public static function register_rest_routes() {
        register_rest_route('punktepass/v1', '/pos/scan', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_process_scan'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/logs', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_logs'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/sync_offline', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_sync_offline'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/campaign', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_create_campaign'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/campaigns', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_list_campaigns'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/campaign/delete', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_delete_campaign'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/campaign/update', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_update_campaign'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/pos/campaign/archive', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_archive_campaign'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('punktepass/v1', '/strings', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_strings'],
            'permission_callback' => ['PPV_Permissions', 'allow_anonymous'],
        ]);
    }

    public static function rest_get_strings(WP_REST_Request $r) {
        $lang = sanitize_text_field($r->get_header('X-Lang') ?? $_GET['lang'] ?? 'de');

        if (!in_array($lang, ['de', 'hu', 'ro'])) {
            $lang = 'de';
        }

        $file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-{$lang}.php";
        if (!file_exists($file)) {
            $file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-de.php";
        }

        $strings = include $file;

        return new WP_REST_Response($strings, 200);
    }

    // ============================================================
    // 🔍 REST: PROCESS SCAN
    // ============================================================
    public static function rest_process_scan(WP_REST_Request $r) {
        global $wpdb;

        $data = $r->get_json_params();
        $qr_code = sanitize_text_field($data['qr'] ?? '');
        $store_key = sanitize_text_field($data['store_key'] ?? '');

        if (empty($qr_code) || empty($store_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_invalid_request', '❌ Érvénytelen kérés')
            ], 400);
        }

        $validation = self::validate_store($store_key);
        if (!$validation['valid']) {
            return $validation['response'];
        }
        $store = $validation['store'];

        $user_id = self::decode_user_from_qr($qr_code);
        if (!$user_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_invalid_qr', '❌ Érvénytelen QR'),
                'store_name' => $store->name ?? 'PunktePass',
                'error_type' => 'invalid_qr'
            ], 400);
        }

        $rate_check = self::check_rate_limit($user_id, $store->id);
        if ($rate_check['limited']) {
            // Log the rate limit error with error_type for client-side translation
            $response_data = $rate_check['response']->get_data();
            $error_type = $response_data['error_type'] ?? null;
            self::insert_log($store->id, $user_id, $response_data['message'] ?? '⚠️ Rate limit', 'error', $error_type);
            return $rate_check['response'];
        }

        $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id' => $user_id,
            'store_id' => $store->id,
            'points' => 1,
            'type' => 'qr_scan',
            'created' => current_time('mysql')
        ]);

        self::insert_log($store->id, $user_id, self::t('log_point_added', '1 pont hozzáadva'), 'qr_scan');

        // Get store name for response
        $store_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store->id
        ));

        return new WP_REST_Response([
            'success' => true,
            'message' => self::t('scan_success', '✅ 1 pont hozzáadva'),
            'user_id' => $user_id,
            'store_id' => $store->id,
            'store_name' => $store_name ?? 'PunktePass',
            'points' => 1,
            'time' => current_time('mysql')
        ], 200);
    }

    // ============================================================
    // 📜 REST: GET LOGS
    // ============================================================
    public static function rest_get_logs(WP_REST_Request $r) {
        global $wpdb;

        $store_key = sanitize_text_field(
            $r->get_header('ppv-pos-token') ?? $r->get_param('store_key') ?? ''
        );

        if (empty($store_key)) return new WP_REST_Response([], 400);

        $store = self::get_store_by_key($store_key);
        if (!$store) return new WP_REST_Response([], 400);

        return new WP_REST_Response($wpdb->get_results($wpdb->prepare("
            SELECT created_at, user_id, message
            FROM {$wpdb->prefix}ppv_pos_log
            WHERE store_id=%d
            ORDER BY id DESC LIMIT 15
        ", $store->id)), 200);
    }

    // ============================================================
    // 💾 REST: OFFLINE SYNC
    // ============================================================
    public static function rest_sync_offline(WP_REST_Request $r) {
        global $wpdb;

        $payload = $r->get_json_params();
        $scans = $payload['scans'] ?? [];
        $synced = 0;
        $duplicates = [];

        foreach ($scans as $s) {
            $qr = sanitize_text_field($s['qr'] ?? '');
            $store_key = sanitize_text_field($s['store_key'] ?? '');

            if (empty($qr) || empty($store_key)) continue;

            $store = self::get_store_by_key($store_key);
            if (!$store) continue;

            $user = self::decode_user_from_qr($qr);
            if (!$user) continue;

            $recent = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
                WHERE user_id=%d 
                AND store_id=%d
                AND created >= DATE_SUB(%s, INTERVAL 2 MINUTE)
            ", $user, $store->id, current_time('mysql')));

            if ($recent > 0) {
                $duplicates[] = $qr;
                continue;
            }

            $wpdb->insert("{$wpdb->prefix}ppv_points", [
                'user_id' => $user,
                'store_id' => $store->id,
                'points' => 1,
                'type' => 'pos_offline',
                'reference' => $qr,
                'created' => current_time('mysql')
            ]);

            self::insert_log($store->id, $user, "Offline szinkronizálva: $qr", 'offline_sync');
            $synced++;
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => self::t('offline_synced', '✅ Offline szinkronizálva'),
            'synced' => $synced,
            'duplicates' => $duplicates,
            'duplicate_count' => count($duplicates)
        ], 200);
    }

    // ============================================================
    // 🎯 REST: CREATE CAMPAIGN
    // ============================================================
    public static function rest_create_campaign(WP_REST_Request $r) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // 🧩 UNIVERZÁLIS JSON PARSE FIX
        $raw = @file_get_contents('php://input');
        if (!$raw || trim($raw) === '') {
            // néha a streamet újra kell nyitni, ha már olvasta a WP
            $stream = fopen('php://input', 'r');
            $raw = stream_get_contents($stream);
            fclose($stream);
        }

        $data = json_decode($raw, true);
        if (empty($data)) $data = $r->get_json_params();
        if (empty($data)) $data = $_POST;

        // 🔍 LOG – ellenőrzéshez


        $store_key = sanitize_text_field($data['store_key'] ?? '');
        $validation = self::validate_store($store_key);
        if (!$validation['valid']) return $validation['response'];
        $store = $validation['store'];

        // 🎯 Kampány adatok előkészítése
        // ✅ FIX: Don't accept empty campaign_type
        $campaign_type = sanitize_text_field($data['campaign_type'] ?? '');
        if (empty($campaign_type)) {
            $campaign_type = 'points';
        }

        $fields = [
            'store_id'           => $store->id,
            'title'              => sanitize_text_field($data['title'] ?? ''),
            'start_date'         => sanitize_text_field($data['start_date'] ?? ''),
            'end_date'           => sanitize_text_field($data['end_date'] ?? ''),
            'campaign_type'      => $campaign_type,
            'required_points'    => (int)($data['required_points'] ?? 0),
            'points_given'       => (int)($data['points_given'] ?? 1),
            'status'             => sanitize_text_field($data['status'] ?? 'active'),
            'created_at'         => current_time('mysql'),
        ];

        // 🧠 Típusfüggő értékek
        switch ($fields['campaign_type']) {
            case 'points':
                $fields['extra_points'] = (int)($data['camp_value'] ?? 0);
                break;
            case 'discount':
                $fields['discount_percent'] = (float)($data['camp_value'] ?? 0);
                break;
            case 'fixed':
                $fields['fixed_amount'] = (float)($data['camp_value'] ?? 0);
                break;
            case 'free_product':
                $fields['free_product'] = sanitize_text_field($data['free_product'] ?? '');
                $fields['free_product_value'] = (float)($data['free_product_value'] ?? 0);
                break;
            default:
                break;
        }

        // ✅ DEBUG: Log fields before insert

        $wpdb->insert("{$prefix}ppv_campaigns", $fields);

        // ✅ DEBUG: Check if insert succeeded
        if ($wpdb->last_error) {
        } else {
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => '✅ Kampány sikeresen létrehozva',
            'fields'  => $fields,
        ], 200);
    }

    // ============================================================
    // 📋 REST: LIST CAMPAIGNS
    // ============================================================
    public static function rest_list_campaigns(WP_REST_Request $r) {
        global $wpdb;

        $store_key = sanitize_text_field(
            $r->get_header('ppv-pos-token') ?? $r->get_param('store_key') ?? ''
        );

        if (empty($store_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Token hiányzik'
            ], 400);
        }

        $store = self::get_store_by_key($store_key);
        if (!$store) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ismeretlen bolt'
            ], 400);
        }

        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT id, title, start_date, end_date, campaign_type, multiplier,
                   extra_points, discount_percent, min_purchase, fixed_amount, status,
                   required_points, free_product, free_product_value, points_given
            FROM {$wpdb->prefix}ppv_campaigns
            WHERE store_id=%d ORDER BY start_date DESC
        ", $store->id));

        if (empty($rows)) {
            return new WP_REST_Response([], 200);
        }

        $store_country = $wpdb->get_var($wpdb->prepare(
            "SELECT country FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store->id
        ));

        $today = date('Y-m-d');
        $data = array_map(function ($r) use ($today, $store_country) {
            if ($today < $r->start_date) {
                $state = 'upcoming';
            } elseif ($today > $r->end_date) {
                $state = 'expired';
            } else {
                $state = 'active';
            }

            return [
                'id' => intval($r->id),
                'title' => $r->title,
                'start_date' => $r->start_date,
                'end_date' => $r->end_date,
                'campaign_type' => $r->campaign_type,
                'multiplier' => intval($r->multiplier),
                'extra_points' => intval($r->extra_points),
                'discount_percent' => floatval($r->discount_percent),
                'min_purchase' => floatval($r->min_purchase),
                'fixed_amount' => floatval($r->fixed_amount),
                'required_points' => intval($r->required_points ?? 0),
                'free_product' => $r->free_product ?? '',
                'free_product_value' => floatval($r->free_product_value ?? 0),
                'points_given' => intval($r->points_given ?? 1),
                'status' => $r->status,
                'state' => $state,
                'country' => $store_country
            ];
        }, $rows);

        return new WP_REST_Response($data, 200);
    }

    // ============================================================
    // 🗑️ REST: DELETE CAMPAIGN
    // ============================================================
    public static function rest_delete_campaign(WP_REST_Request $r) {
        global $wpdb;

        $d = $r->get_json_params();
        $store_key = sanitize_text_field($d['store_key'] ?? '');
        $id = intval($d['id'] ?? 0);

        if (empty($id) || empty($store_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_missing_data', '❌ Hiányzó adat')
            ], 400);
        }

        $validation = self::validate_store($store_key);
        if (!$validation['valid']) {
            return $validation['response'];
        }
        $store = $validation['store'];

        $wpdb->delete("{$wpdb->prefix}ppv_campaigns", [
            'id' => $id,
            'store_id' => $store->id
        ]);

        self::insert_log($store->id, 0, "Kampány törölve: ID {$id}", 'campaign_delete');

        return new WP_REST_Response([
            'success' => true,
            'message' => self::t('campaign_deleted', '🗑️ Kampány törölve!')
        ], 200);
    }

    // ============================================================
    // ✏️ REST: UPDATE CAMPAIGN
    // ============================================================
    public static function rest_update_campaign(WP_REST_Request $r) {
        global $wpdb;
        
        $prefix = $wpdb->prefix;
        $raw = @file_get_contents('php://input');
        if (!$raw || trim($raw) === '') {
            $stream = fopen('php://input', 'r');
            $raw = stream_get_contents($stream);
            fclose($stream);
        }

        $d = json_decode($raw, true);
        if (empty($d)) $d = $r->get_json_params();
        if (empty($d)) $d = $_POST;



        $id = intval($d['id'] ?? 0);
        $store_key = sanitize_text_field($d['store_key'] ?? '');

        if (empty($id) || empty($store_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => '❌ Kampány ID vagy bolt kulcs hiányzik'
            ], 400);
        }

        $validation = self::validate_store($store_key);
        if (!$validation['valid']) {
            return $validation['response'];
        }
        $store = $validation['store'];

        // 🎯 Frissítési mezők
        // ✅ FIX: Don't accept empty campaign_type
        $campaign_type = sanitize_text_field($d['campaign_type'] ?? '');
        if (empty($campaign_type)) {
            $campaign_type = 'points';
        }

        $fields = [
            'title'           => sanitize_text_field($d['title'] ?? ''),
            'start_date'      => sanitize_text_field($d['start_date'] ?? ''),
            'end_date'        => sanitize_text_field($d['end_date'] ?? ''),
            'campaign_type'   => $campaign_type,
            'required_points' => (int)($d['required_points'] ?? 0),
            'points_given'    => (int)($d['points_given'] ?? 1),
            'status'          => sanitize_text_field($d['status'] ?? 'active'),
            'updated_at'      => current_time('mysql'),
        ];

        // 🧠 Típusfüggő értékek
        switch ($fields['campaign_type']) {
            case 'points':
                $fields['extra_points'] = (int)($d['camp_value'] ?? 0);
                break;
            case 'discount':
                $fields['discount_percent'] = (float)($d['camp_value'] ?? 0);
                break;
            case 'fixed':
                $fields['fixed_amount'] = (float)($d['camp_value'] ?? 0);
                break;
            case 'free_product':
                $fields['free_product'] = sanitize_text_field($d['free_product'] ?? '');
                $fields['free_product_value'] = (float)($d['free_product_value'] ?? 0);
                break;
            default:
                break;
        }

        $wpdb->update("{$prefix}ppv_campaigns", $fields, [
            'id'        => $id,
            'store_id'  => $store->id,
        ]);

        self::insert_log($store->id, 0, "Kampány frissítve: ID {$id}", 'campaign_update');

        return new WP_REST_Response([
            'success' => true,
            'message' => '✅ Kampány sikeresen frissítve',
            'fields'  => $fields,
        ], 200);
    }

    // ============================================================
    // 📦 REST: ARCHIVE CAMPAIGN
    // ============================================================
    public static function rest_archive_campaign(WP_REST_Request $r) {
        global $wpdb;

        $d = $r->get_json_params();
        $id = intval($d['id'] ?? 0);
        $store_key = sanitize_text_field($d['store_key'] ?? '');

        if (empty($id) || empty($store_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => self::t('err_missing_data', '❌ Hiányzó adat')
            ], 400);
        }

        $validation = self::validate_store($store_key);
        if (!$validation['valid']) {
            return $validation['response'];
        }
        $store = $validation['store'];

        $wpdb->update("{$wpdb->prefix}ppv_campaigns", [
            'status' => 'archived',
            'updated_at' => current_time('mysql')
        ], [
            'id' => $id,
            'store_id' => $store->id
        ]);

        self::insert_log($store->id, 0, "Kampány archivált: ID {$id}", 'campaign_archive');

        return new WP_REST_Response([
            'success' => true,
            'message' => self::t('archived_success', '📦 Archiválva')
        ], 200);
    }
}

PPV_QR::hooks();