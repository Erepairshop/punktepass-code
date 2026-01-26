<?php
/**
 * PunktePass Standalone Admin Panel
 *
 * Elérhető: /admin - egyszerű email/jelszó bejelentkezéssel
 * Független a WordPress admintól
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Admin {

    /**
     * Hooks - a plugin által hívott metódus
     */
    public static function hooks() {
        add_action('init', [__CLASS__, 'run_migration'], 0);
        add_action('init', [__CLASS__, 'handle_admin_routes'], 1);
        add_action('rest_api_init', [__CLASS__, 'register_api_routes']);
    }

    /**
     * Migráció: admin_access oszlop hozzáadása a ppv_stores táblához
     */
    public static function run_migration() {
        global $wpdb;

        $migration_version = get_option('ppv_admin_migration_version', '0');

        // Migration 1.0: Add admin_access column
        if (version_compare($migration_version, '1.0', '<')) {
            $table = $wpdb->prefix . 'ppv_stores';

            // Ellenőrizzük hogy létezik-e már az oszlop
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'admin_access'");

            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN admin_access TINYINT(1) NOT NULL DEFAULT 0");
                ppv_log("✅ [PPV_Standalone_Admin] Added admin_access column to ppv_stores");
            }

            update_option('ppv_admin_migration_version', '1.0');
        }
    }

    /**
     * User Agent-ből eszköz info kinyerése
     */
    private static function parse_device_info($user_agent) {
        if (empty($user_agent)) {
            return ['model' => 'Ismeretlen', 'os' => '', 'browser' => ''];
        }

        $info = [
            'model' => '',
            'os' => '',
            'browser' => '',
            'raw' => $user_agent
        ];

        // iPhone detection
        if (preg_match('/iPhone/', $user_agent)) {
            $info['model'] = 'iPhone';
            if (preg_match('/iPhone OS ([\d_]+)/', $user_agent, $matches)) {
                $info['os'] = 'iOS ' . str_replace('_', '.', $matches[1]);
            }
        }
        // iPad detection
        elseif (preg_match('/iPad/', $user_agent)) {
            $info['model'] = 'iPad';
            if (preg_match('/CPU OS ([\d_]+)/', $user_agent, $matches)) {
                $info['os'] = 'iPadOS ' . str_replace('_', '.', $matches[1]);
            }
        }
        // Android device detection - try to get model
        elseif (preg_match('/Android/', $user_agent)) {
            // Try to extract model: Android X.X; MODEL Build/
            if (preg_match('/Android [\d.]+;\s*([^)]+?)\s*(?:Build|;|\))/', $user_agent, $matches)) {
                $model = trim($matches[1]);
                // Clean up common prefixes
                $model = preg_replace('/^(SAMSUNG|Samsung|LG|Xiaomi|HUAWEI|Huawei|OPPO|OnePlus|Realme|vivo|Motorola)\s*/i', '', $model);

                // Chrome 110+ privacy: "K" helyett valódi modell nem elérhető
                if ($model === 'K' || $model === 'k') {
                    $info['model'] = 'Android (rejtett)';
                    $info['note'] = 'Chrome privacy mód';
                } else {
                    $info['model'] = $model ?: 'Android';
                }
            } else {
                $info['model'] = 'Android';
            }
            if (preg_match('/Android ([\d.]+)/', $user_agent, $matches)) {
                $info['os'] = 'Android ' . $matches[1];
            }
        }
        // Windows
        elseif (preg_match('/Windows/', $user_agent)) {
            $info['model'] = 'Windows PC';
            if (preg_match('/Windows NT ([\d.]+)/', $user_agent, $matches)) {
                $versions = ['10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7'];
                $info['os'] = 'Windows ' . ($versions[$matches[1]] ?? $matches[1]);
            }
        }
        // Mac
        elseif (preg_match('/Macintosh/', $user_agent)) {
            $info['model'] = 'Mac';
            if (preg_match('/Mac OS X ([\d_]+)/', $user_agent, $matches)) {
                $info['os'] = 'macOS ' . str_replace('_', '.', $matches[1]);
            }
        }
        // Linux
        elseif (preg_match('/Linux/', $user_agent)) {
            $info['model'] = 'Linux PC';
            $info['os'] = 'Linux';
        }
        else {
            $info['model'] = 'Ismeretlen';
        }

        // Browser detection
        if (preg_match('/Chrome\/([\d.]+)/', $user_agent, $matches)) {
            $info['browser'] = 'Chrome ' . explode('.', $matches[1])[0];
        } elseif (preg_match('/Safari\/([\d.]+)/', $user_agent) && !preg_match('/Chrome/', $user_agent)) {
            $info['browser'] = 'Safari';
        } elseif (preg_match('/Firefox\/([\d.]+)/', $user_agent, $matches)) {
            $info['browser'] = 'Firefox ' . explode('.', $matches[1])[0];
        }

        return $info;
    }

    /**
     * FingerprintJS device_info JSON formázása megjelenítéshez
     */
    private static function format_device_info_json($device_info_json) {
        if (empty($device_info_json)) {
            return null;
        }

        $info = json_decode($device_info_json, true);
        if (!$info || !is_array($info)) {
            return null;
        }

        $formatted = [];

        // Képernyő
        if (!empty($info['screen'])) {
            $formatted['screen'] = $info['screen'];
        }

        // Memória
        if (!empty($info['memory'])) {
            $formatted['memory'] = $info['memory'] . ' GB';
        }

        // CPU magok
        if (!empty($info['cpuCores'])) {
            $formatted['cpuCores'] = $info['cpuCores'] . ' mag';
        }

        // Platform
        if (!empty($info['platform'])) {
            $formatted['platform'] = $info['platform'];
        }

        // Időzóna
        if (!empty($info['timezone'])) {
            $formatted['timezone'] = $info['timezone'];
        }

        // Érintőképernyő
        if (!empty($info['touchSupport']) && is_array($info['touchSupport'])) {
            $touch = $info['touchSupport'];
            if (!empty($touch['maxTouchPoints']) && $touch['maxTouchPoints'] > 0) {
                $formatted['touch'] = 'Igen (' . $touch['maxTouchPoints'] . ' pont)';
            } else {
                $formatted['touch'] = 'Nem';
            }
        }

        // WebGL renderer (GPU info)
        if (!empty($info['webglRenderer']) && $info['webglRenderer'] !== 'no') {
            $renderer = $info['webglRenderer'];
            // Rövidítsük le ha túl hosszú
            if (strlen($renderer) > 40) {
                $renderer = substr($renderer, 0, 37) . '...';
            }
            $formatted['gpu'] = $renderer;
        }

        // Vendor (gyártó)
        if (!empty($info['vendor'])) {
            $formatted['vendor'] = $info['vendor'];
        }

        // Nyelvek
        if (!empty($info['languages'])) {
            if (is_array($info['languages'])) {
                $formatted['languages'] = implode(', ', array_slice($info['languages'], 0, 3));
            } else {
                $formatted['languages'] = $info['languages'];
            }
        }

        // Gyűjtés ideje
        if (!empty($info['collectedAt'])) {
            $formatted['collectedAt'] = date('Y.m.d H:i', strtotime($info['collectedAt']));
        }

        return $formatted;
    }

    /**
     * /admin útvonalak kezelése
     */
    public static function handle_admin_routes() {
        $request_uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($request_uri, PHP_URL_PATH);

        // Záró perjel eltávolítása
        $path = rtrim($path, '/');

        // Public sales page (no login required)
        if ($path === '/sales' || $path === '/sales/partner') {
            self::render_sales_page();
            exit;
        }

        // Admin útvonal ellenőrzése
        if ($path === '/admin' || strpos($path, '/admin/') === 0) {
            self::process_admin_request($path);
            exit;
        }
    }

    /**
     * Render the public sales page
     */
    private static function render_sales_page() {
        $sales_file = plugin_dir_path(__FILE__) . '../sales/punktepass-partner-info.html';

        if (file_exists($sales_file)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($sales_file);
        } else {
            wp_die('Sales page not found', 'PunktePass', ['response' => 404]);
        }
    }

    /**
     * Admin kérés feldolgozása
     */
    private static function process_admin_request($path) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Kijelentkezés kezelése
        if ($path === '/admin/logout') {
            unset($_SESSION['ppv_admin_logged_in']);
            unset($_SESSION['ppv_admin_email']);
            unset($_SESSION['ppv_admin_store_id']);
            unset($_SESSION['ppv_admin_store_name']);
            wp_redirect('/admin');
            exit;
        }

        // Bejelentkezés POST kezelése
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === '/admin/login') {
            self::handle_login();
            exit;
        }

        // Bejelentkezés ellenőrzése
        if (empty($_SESSION['ppv_admin_logged_in'])) {
            self::render_login_page();
            return;
        }

        // Különböző admin oldalak kezelése
        if ($path === '/admin' || $path === '/admin/dashboard') {
            self::render_dashboard();
        } elseif ($path === '/admin/device-requests') {
            self::render_device_requests();
        } elseif ($path === '/admin/handlers') {
            self::render_handlers_page();
        } elseif ($path === '/admin/device-deletion-log') {
            self::render_device_deletion_log();
        } elseif ($path === '/admin/whatsapp') {
            self::render_whatsapp_settings();
        } elseif ($path === '/admin/delete-device') {
            self::handle_delete_device();
        } elseif ($path === '/admin/deactivate-mobile') {
            self::handle_deactivate_mobile();
        } elseif ($path === '/admin/deactivate-device-mobile') {
            self::handle_deactivate_device_mobile();
        } elseif ($path === '/admin/handler-extend') {
            self::handle_handler_extend();
        } elseif ($path === '/admin/handler-activate') {
            self::handle_handler_activate();
        } elseif ($path === '/admin/handler-mobile') {
            self::handle_handler_mobile();
        } elseif ($path === '/admin/handler-filialen') {
            self::handle_handler_filialen();
        } elseif ($path === '/admin/device-mobile-enable') {
            self::handle_device_mobile_enable();
        } elseif (preg_match('#/admin/approve/([a-zA-Z0-9]+)#', $path, $matches)) {
            self::handle_approve($matches[1]);
        } elseif (preg_match('#/admin/reject/([a-zA-Z0-9]+)#', $path, $matches)) {
            self::handle_reject($matches[1]);
        }
        // ========================================
        // 🆕 NEW STANDALONE ADMIN PAGES
        // ========================================
        elseif ($path === '/admin/renewals') {
            require_once __DIR__ . '/admin/standalone/renewals.php';
            PPV_Standalone_Renewals::render();
        } elseif ($path === '/admin/support') {
            require_once __DIR__ . '/admin/standalone/support.php';
            PPV_Standalone_Support::render();
        } elseif ($path === '/admin/suspicious-scans') {
            require_once __DIR__ . '/admin/standalone/suspicious-scans.php';
            PPV_Standalone_SuspiciousScans::render();
        } elseif ($path === '/admin/pending-scans') {
            require_once __DIR__ . '/admin/standalone/pending-scans.php';
            PPV_Standalone_PendingScans::render();
        } elseif ($path === '/admin/devices') {
            require_once __DIR__ . '/admin/standalone/devices.php';
            PPV_Standalone_Devices::render();
        } elseif ($path === '/admin/pos-log') {
            require_once __DIR__ . '/admin/standalone/pos-log.php';
            PPV_Standalone_POSLog::render();
        } elseif ($path === '/admin/db-health') {
            require_once __DIR__ . '/admin/standalone/db-health.php';
            PPV_Standalone_DBHealth::render();
        } elseif ($path === '/admin/contracts') {
            require_once __DIR__ . '/admin/standalone/contracts.php';
            PPV_Standalone_Contracts::render();
        } elseif ($path === '/admin/email-sender') {
            require_once __DIR__ . '/admin/standalone/email-sender.php';
            PPV_Standalone_Email_Sender::render();
        } elseif ($path === '/admin/sales-map') {
            require_once __DIR__ . '/admin/standalone/sales-map.php';
            PPV_Standalone_Sales_Map::render();
        } elseif ($path === '/admin/push-sender') {
            require_once __DIR__ . '/admin/standalone/push-sender.php';
            PPV_Standalone_Push_Sender::render();
        } elseif ($path === '/admin/dev-settings') {
            self::render_dev_settings();
        } else {
            self::render_dashboard();
        }
    }

    /**
     * Eszköz törlése admin által
     */
    private static function handle_delete_device() {
        global $wpdb;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_redirect('/admin/handlers');
            exit;
        }

        $device_id = intval($_POST['device_id'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');

        if (empty($device_id)) {
            wp_redirect('/admin/handlers?error=missing_device');
            exit;
        }

        if (empty($reason)) {
            wp_redirect('/admin/handlers?error=missing_reason');
            exit;
        }

        // Eszköz lekérése
        $device = $wpdb->get_row($wpdb->prepare(
            "SELECT d.*, s.name as store_name
             FROM {$wpdb->prefix}ppv_user_devices d
             LEFT JOIN {$wpdb->prefix}ppv_stores s ON d.store_id = s.id
             WHERE d.id = %d",
            $device_id
        ));

        if (!$device) {
            wp_redirect('/admin/handlers?error=device_not_found');
            exit;
        }

        // Eszköz törlése
        $result = $wpdb->delete(
            $wpdb->prefix . 'ppv_user_devices',
            ['id' => $device_id],
            ['%d']
        );

        if ($result) {
            // Log the deletion
            $admin_email = $_SESSION['ppv_admin_email'] ?? 'admin';
            $device_info = self::parse_device_info($device->user_agent ?? '');
            ppv_log("🗑️ [Admin Device Delete] device_id={$device_id}, store={$device->store_name} (#{$device->store_id}), device={$device->device_name} ({$device_info['model']}), reason={$reason}, by={$admin_email}");

            wp_redirect('/admin/handlers?deleted=' . urlencode($device->device_name) . '&store=' . urlencode($device->store_name));
        } else {
            wp_redirect('/admin/handlers?error=delete_failed');
        }
        exit;
    }

    /**
     * Mobile Scanner deaktiválása admin által
     */
    private static function handle_deactivate_mobile() {
        global $wpdb;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_redirect('/admin/handlers');
            exit;
        }

        $store_id = intval($_POST['store_id'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');

        if (empty($store_id)) {
            wp_redirect('/admin/handlers?error=missing_store');
            exit;
        }

        if (empty($reason)) {
            wp_redirect('/admin/handlers?error=missing_reason');
            exit;
        }

        // Store lekérése
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if (!$store) {
            wp_redirect('/admin/handlers?error=store_not_found');
            exit;
        }

        if ($store->scanner_type !== 'mobile') {
            wp_redirect('/admin/handlers?error=not_mobile');
            exit;
        }

        // Mobile Scanner deaktiválása (visszaállítás fixed-re)
        $result = $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            ['scanner_type' => 'fixed'],
            ['id' => $store_id],
            ['%s'],
            ['%d']
        );

        if ($result !== false) {
            // Log the deactivation
            $admin_email = $_SESSION['ppv_admin_email'] ?? 'admin';
            ppv_log("📵 [Admin Mobile Deactivate] store_id={$store_id}, store={$store->name}, reason={$reason}, by={$admin_email}");

            wp_redirect('/admin/handlers?mobile_deactivated=' . urlencode($store->name));
        } else {
            wp_redirect('/admin/handlers?error=deactivate_failed');
        }
        exit;
    }

    /**
     * Mobile Scanner deaktiválása készüléken (per-device)
     */
    private static function handle_deactivate_device_mobile() {
        global $wpdb;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_redirect('/admin/handlers');
            exit;
        }

        $device_id = intval($_POST['device_id'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');

        if (empty($device_id)) {
            wp_redirect('/admin/handlers?error=missing_device');
            exit;
        }

        if (empty($reason)) {
            wp_redirect('/admin/handlers?error=missing_reason');
            exit;
        }

        // Eszköz lekérése
        $device = $wpdb->get_row($wpdb->prepare(
            "SELECT d.*, s.name as store_name
             FROM {$wpdb->prefix}ppv_user_devices d
             LEFT JOIN {$wpdb->prefix}ppv_stores s ON d.store_id = s.id
             WHERE d.id = %d",
            $device_id
        ));

        if (!$device) {
            wp_redirect('/admin/handlers?error=device_not_found');
            exit;
        }

        if (empty($device->mobile_scanner)) {
            wp_redirect('/admin/handlers?error=not_mobile_device');
            exit;
        }

        // Mobile Scanner deaktiválása az eszközön
        $result = $wpdb->update(
            $wpdb->prefix . 'ppv_user_devices',
            ['mobile_scanner' => 0],
            ['id' => $device_id],
            ['%d'],
            ['%d']
        );

        if ($result !== false) {
            // Log the deactivation
            $admin_email = $_SESSION['ppv_admin_email'] ?? 'admin';
            ppv_log("📵 [Admin Device Mobile Deactivate] device_id={$device_id}, device={$device->device_name}, store={$device->store_name}, reason={$reason}, by={$admin_email}");

            wp_redirect('/admin/handlers?device_mobile_deactivated=' . urlencode($device->device_name));
        } else {
            wp_redirect('/admin/handlers?error=deactivate_failed');
        }
        exit;
    }

    /**
     * Handler előfizetés meghosszabbítása napokkal
     */
    private static function handle_handler_extend() {
        global $wpdb;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_redirect('/admin/handlers');
            exit;
        }

        $handler_id = intval($_POST['handler_id'] ?? 0);
        $days = intval($_POST['days'] ?? 0);

        if (empty($handler_id) || $days < 1 || $days > 365) {
            wp_redirect('/admin/handlers?error=Hibás paraméterek');
            exit;
        }

        $handler = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $handler_id
        ));

        if (!$handler) {
            wp_redirect('/admin/handlers?error=Handler nem található');
            exit;
        }

        // Determine which date field to extend based on subscription status
        $is_trial = ($handler->subscription_status === 'trial');

        if ($is_trial) {
            // Trial: extend trial_ends_at
            $current_end = !empty($handler->trial_ends_at) ? $handler->trial_ends_at : date('Y-m-d');
            $new_end = date('Y-m-d', strtotime($current_end . ' + ' . $days . ' days'));

            $result = $wpdb->update(
                $wpdb->prefix . 'ppv_stores',
                ['trial_ends_at' => $new_end],
                ['id' => $handler_id],
                ['%s'],
                ['%d']
            );

            $field_updated = 'trial_ends_at';
        } else {
            // Active: extend subscription_expires_at
            $current_end = !empty($handler->subscription_expires_at) ? $handler->subscription_expires_at : date('Y-m-d');
            $new_end = date('Y-m-d', strtotime($current_end . ' + ' . $days . ' days'));

            $result = $wpdb->update(
                $wpdb->prefix . 'ppv_stores',
                ['subscription_expires_at' => $new_end],
                ['id' => $handler_id],
                ['%s'],
                ['%d']
            );

            $field_updated = 'subscription_expires_at';
        }

        if ($result !== false) {
            $admin_email = $_SESSION['ppv_admin_email'] ?? 'admin';
            ppv_log("📅 [Admin Handler Extend] handler_id={$handler_id}, name={$handler->name}, status={$handler->subscription_status}, field={$field_updated}, days={$days}, new_end={$new_end}, by={$admin_email}");
            wp_redirect('/admin/handlers?success=extended');
        } else {
            wp_redirect('/admin/handlers?error=Hiba a meghosszabbítás során');
        }
        exit;
    }

    /**
     * Handler aktiválása (trial -> active, 6 hónap)
     */
    private static function handle_handler_activate() {
        global $wpdb;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_redirect('/admin/handlers');
            exit;
        }

        $handler_id = intval($_POST['handler_id'] ?? 0);

        if (empty($handler_id)) {
            wp_redirect('/admin/handlers?error=Hibás paraméterek');
            exit;
        }

        $handler = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $handler_id
        ));

        if (!$handler) {
            wp_redirect('/admin/handlers?error=Handler nem található');
            exit;
        }

        $subscription_end = date('Y-m-d', strtotime('+6 months'));

        $result = $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            [
                'subscription_status' => 'active',
                'subscription_expires_at' => $subscription_end
            ],
            ['id' => $handler_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            $admin_email = $_SESSION['ppv_admin_email'] ?? 'admin';
            ppv_log("✅ [Admin Handler Activate] handler_id={$handler_id}, name={$handler->name}, expires={$subscription_end}, by={$admin_email}");
            wp_redirect('/admin/handlers?success=activated');
        } else {
            wp_redirect('/admin/handlers?error=Hiba az aktiválás során');
        }
        exit;
    }

    /**
     * Handler mobile scanner mód beállítása
     */
    private static function handle_handler_mobile() {
        global $wpdb;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_redirect('/admin/handlers');
            exit;
        }

        $handler_id = intval($_POST['handler_id'] ?? 0);
        $mobile_mode = sanitize_text_field($_POST['mobile_mode'] ?? 'off');

        if (empty($handler_id)) {
            wp_redirect('/admin/handlers?error=Hibás paraméterek');
            exit;
        }

        $handler = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $handler_id
        ));

        if (!$handler) {
            wp_redirect('/admin/handlers?error=Handler nem található');
            exit;
        }

        $scanner_type = ($mobile_mode === 'global') ? 'mobile' : 'fixed';

        $result = $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            ['scanner_type' => $scanner_type],
            ['id' => $handler_id],
            ['%s'],
            ['%d']
        );

        // Ha global mobile mód, akkor az összes eszközön is bekapcsoljuk
        if ($mobile_mode === 'global') {
            $wpdb->update(
                $wpdb->prefix . 'ppv_user_devices',
                ['mobile_scanner' => 1],
                ['store_id' => $handler_id],
                ['%d'],
                ['%d']
            );
        } elseif ($mobile_mode === 'off') {
            $wpdb->update(
                $wpdb->prefix . 'ppv_user_devices',
                ['mobile_scanner' => 0],
                ['store_id' => $handler_id],
                ['%d'],
                ['%d']
            );
        }

        if ($result !== false) {
            $admin_email = $_SESSION['ppv_admin_email'] ?? 'admin';
            ppv_log("📱 [Admin Handler Mobile] handler_id={$handler_id}, name={$handler->name}, mode={$mobile_mode}, by={$admin_email}");
            wp_redirect('/admin/handlers?success=mobile_updated');
        } else {
            wp_redirect('/admin/handlers?error=Hiba a mentés során');
        }
        exit;
    }

    /**
     * Handler fiók limit beállítása
     */
    private static function handle_handler_filialen() {
        global $wpdb;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_redirect('/admin/handlers');
            exit;
        }

        $handler_id = intval($_POST['handler_id'] ?? 0);
        $max_filialen = intval($_POST['max_filialen'] ?? 1);

        if (empty($handler_id) || $max_filialen < 1 || $max_filialen > 100) {
            wp_redirect('/admin/handlers?error=Hibás paraméterek');
            exit;
        }

        $handler = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $handler_id
        ));

        if (!$handler) {
            wp_redirect('/admin/handlers?error=Handler nem található');
            exit;
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            ['max_filialen' => $max_filialen],
            ['id' => $handler_id],
            ['%d'],
            ['%d']
        );

        if ($result !== false) {
            $admin_email = $_SESSION['ppv_admin_email'] ?? 'admin';
            ppv_log("🏢 [Admin Handler Filialen] handler_id={$handler_id}, name={$handler->name}, max_filialen={$max_filialen}, by={$admin_email}");
            wp_redirect('/admin/handlers?success=filialen_updated');
        } else {
            wp_redirect('/admin/handlers?error=Hiba a mentés során');
        }
        exit;
    }

    /**
     * Eszköz mobile scanner engedélyezése
     */
    private static function handle_device_mobile_enable() {
        global $wpdb;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_redirect('/admin/handlers');
            exit;
        }

        $device_id = intval($_POST['device_id'] ?? 0);

        if (empty($device_id)) {
            wp_redirect('/admin/handlers?error=Hibás paraméterek');
            exit;
        }

        $device = $wpdb->get_row($wpdb->prepare(
            "SELECT d.*, s.name as store_name
             FROM {$wpdb->prefix}ppv_user_devices d
             LEFT JOIN {$wpdb->prefix}ppv_stores s ON d.store_id = s.id
             WHERE d.id = %d",
            $device_id
        ));

        if (!$device) {
            wp_redirect('/admin/handlers?error=Eszköz nem található');
            exit;
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'ppv_user_devices',
            ['mobile_scanner' => 1],
            ['id' => $device_id],
            ['%d'],
            ['%d']
        );

        if ($result !== false) {
            $admin_email = $_SESSION['ppv_admin_email'] ?? 'admin';
            ppv_log("📱 [Admin Device Mobile Enable] device_id={$device_id}, device={$device->device_name}, store={$device->store_name}, by={$admin_email}");
            wp_redirect('/admin/handlers?success=device_mobile_enabled');
        } else {
            wp_redirect('/admin/handlers?error=Hiba az engedélyezés során');
        }
        exit;
    }

    /**
     * Bejelentkezés kezelése - ppv_stores táblából, admin_access oszlop ellenőrzéssel
     */
    private static function handle_login() {
        global $wpdb;

        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            wp_redirect('/admin?error=missing_fields');
            exit;
        }

        // Store keresése email alapján (admin_access = 1)
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email, password, name, admin_access FROM {$wpdb->prefix}ppv_stores WHERE email = %s",
            $email
        ));

        // Ellenőrzés: létezik-e
        if (!$store) {
            ppv_log("🔐 [Admin] Ismeretlen email: {$email}");
            wp_redirect('/admin?error=invalid_credentials');
            exit;
        }

        // Ellenőrzés: admin_access = 1
        if (empty($store->admin_access) || $store->admin_access != 1) {
            ppv_log("🔐 [Admin] Store nincs engedélyezve (admin_access != 1): {$store->id} ({$email})");
            wp_redirect('/admin?error=not_authorized');
            exit;
        }

        // Jelszó ellenőrzése
        if (!password_verify($password, $store->password)) {
            ppv_log("🔐 [Admin] Hibás jelszó: {$email}");
            wp_redirect('/admin?error=invalid_credentials');
            exit;
        }

        // Sikeres bejelentkezés
        $_SESSION['ppv_admin_logged_in'] = true;
        $_SESSION['ppv_admin_email'] = $email;
        $_SESSION['ppv_admin_store_id'] = $store->id;
        $_SESSION['ppv_admin_store_name'] = $store->name;

        ppv_log("🔐 [Admin] Sikeres bejelentkezés: {$email} (Store #{$store->id})");

        // Session mentése redirect előtt
        session_write_close();

        wp_redirect('/admin/dashboard');
        exit;
    }

    /**
     * API útvonalak regisztrálása
     */
    public static function register_api_routes() {
        register_rest_route('punktepass/v1', '/admin/change-password', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_change_password'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * Jelszó módosítása
     */
    public static function rest_change_password(WP_REST_Request $request) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION['ppv_admin_logged_in'])) {
            return new WP_REST_Response(['success' => false, 'message' => 'Nincs bejelentkezve'], 401);
        }

        $data = $request->get_json_params();
        $current_password = $data['current_password'] ?? '';
        $new_password = $data['new_password'] ?? '';

        if (strlen($new_password) < 8) {
            return new WP_REST_Response(['success' => false, 'message' => 'A jelszónak legalább 8 karakter hosszúnak kell lennie'], 400);
        }

        $email = $_SESSION['ppv_admin_email'];
        $admins = get_option(self::ADMIN_OPTION_KEY, []);

        if (!isset($admins[$email]) || !password_verify($current_password, $admins[$email])) {
            return new WP_REST_Response(['success' => false, 'message' => 'Hibás jelenlegi jelszó'], 400);
        }

        $admins[$email] = password_hash($new_password, PASSWORD_DEFAULT);
        update_option(self::ADMIN_OPTION_KEY, $admins);

        ppv_log("🔐 [Admin] Jelszó módosítva: {$email}");
        return new WP_REST_Response(['success' => true, 'message' => 'Jelszó módosítva']);
    }

    /**
     * Jóváhagyás kezelése
     */
    private static function handle_approve($token) {
        global $wpdb;

        $req = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_device_requests WHERE approval_token = %s AND status = 'pending'",
            $token
        ));

        if (!$req) {
            self::render_message_page('error', 'Kérelem nem található vagy már feldolgozva');
            return;
        }

        // Típus alapján feldolgozás
        if ($req->request_type === 'add') {
            $wpdb->insert(
                $wpdb->prefix . 'ppv_user_devices',
                [
                    'store_id' => $req->store_id,
                    'fingerprint_hash' => $req->fingerprint_hash,
                    'device_name' => $req->device_name,
                    'user_agent' => $req->user_agent,
                    'ip_address' => $req->ip_address,
                    'registered_at' => current_time('mysql'),
                    'status' => 'active'
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
            );
            $message = 'Készülék sikeresen hozzáadva!';
        } elseif ($req->request_type === 'remove') {
            $wpdb->delete(
                $wpdb->prefix . 'ppv_user_devices',
                ['store_id' => $req->store_id, 'fingerprint_hash' => $req->fingerprint_hash],
                ['%d', '%s']
            );
            $message = 'Készülék sikeresen eltávolítva!';
        } elseif ($req->request_type === 'mobile_scanner') {
            $wpdb->update(
                $wpdb->prefix . 'ppv_stores',
                ['scanner_type' => 'mobile'],
                ['id' => $req->store_id],
                ['%s'],
                ['%d']
            );
            $message = 'Mobile Scanner aktiválva!';
        } else {
            self::render_message_page('error', 'Ismeretlen kérelem típus');
            return;
        }

        // Jóváhagyottként jelölés
        $wpdb->update(
            $wpdb->prefix . 'ppv_device_requests',
            [
                'status' => 'approved',
                'processed_at' => current_time('mysql'),
                'processed_by' => $_SESSION['ppv_admin_email'] ?? 'admin'
            ],
            ['id' => $req->id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        ppv_log("✅ [Admin] Kérelem jóváhagyva #{$req->id}: {$req->request_type}");
        wp_redirect('/admin/device-requests?success=' . urlencode($message));
        exit;
    }

    /**
     * Elutasítás kezelése
     */
    private static function handle_reject($token) {
        global $wpdb;

        $req = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_device_requests WHERE approval_token = %s AND status = 'pending'",
            $token
        ));

        if (!$req) {
            self::render_message_page('error', 'Kérelem nem található vagy már feldolgozva');
            return;
        }

        $wpdb->update(
            $wpdb->prefix . 'ppv_device_requests',
            [
                'status' => 'rejected',
                'processed_at' => current_time('mysql'),
                'processed_by' => $_SESSION['ppv_admin_email'] ?? 'admin'
            ],
            ['id' => $req->id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        ppv_log("❌ [Admin] Kérelem elutasítva #{$req->id}: {$req->request_type}");
        wp_redirect('/admin/device-requests?success=Kérelem+elutasítva');
        exit;
    }

    /**
     * Bejelentkezési oldal
     */
    private static function render_login_page() {
        $error = $_GET['error'] ?? '';
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>PunktePass Admin</title>
            <!-- Google Fonts DISABLED for performance -->
            <!-- <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"> -->
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .login-card {
                    background: rgba(255, 255, 255, 0.05);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 20px;
                    padding: 40px;
                    width: 100%;
                    max-width: 400px;
                }
                .logo {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .logo h1 {
                    color: #00e6ff;
                    font-size: 28px;
                    font-weight: 700;
                }
                .logo p {
                    color: #888;
                    font-size: 14px;
                    margin-top: 5px;
                }
                .form-group {
                    margin-bottom: 20px;
                }
                .form-group label {
                    display: block;
                    color: #fff;
                    font-size: 14px;
                    margin-bottom: 8px;
                }
                .form-group input {
                    width: 100%;
                    padding: 14px 16px;
                    background: rgba(255, 255, 255, 0.05);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 10px;
                    color: #fff;
                    font-size: 16px;
                    transition: all 0.3s;
                }
                .form-group input:focus {
                    outline: none;
                    border-color: #00e6ff;
                    background: rgba(0, 230, 255, 0.05);
                }
                .btn {
                    width: 100%;
                    padding: 14px;
                    background: linear-gradient(135deg, #00e6ff, #0099ff);
                    border: none;
                    border-radius: 10px;
                    color: #fff;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s;
                }
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 30px rgba(0, 230, 255, 0.3);
                }
                .error {
                    background: rgba(244, 67, 54, 0.1);
                    border: 1px solid #f44336;
                    color: #f44336;
                    padding: 12px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="login-card">
                <div class="logo">
                    <h1>PunktePass</h1>
                    <p>Admin Panel</p>
                </div>

                <?php if ($error === 'invalid_credentials'): ?>
                    <div class="error">Hibás email cím vagy jelszó</div>
                <?php elseif ($error === 'missing_fields'): ?>
                    <div class="error">Kérjük töltse ki az összes mezőt</div>
                <?php elseif ($error === 'no_admins'): ?>
                    <div class="error">Nincs beállítva admin felhasználó. Kérjük lépjen kapcsolatba a rendszergazdával.</div>
                <?php elseif ($error === 'not_authorized'): ?>
                    <div class="error">Ez a fiók nincs engedélyezve az admin panelhez.</div>
                <?php endif; ?>

                <form method="POST" action="/admin/login">
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" required autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label>Jelszó</label>
                        <input type="password" name="password" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn">Bejelentkezés</button>
                </form>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Admin fejléc HTML
     */
    private static function get_admin_header($current_page = 'dashboard') {
        global $wpdb;
        $admin_email = $_SESSION['ppv_admin_email'] ?? 'Admin';

        // Get notification counts
        $counts = self::get_notification_counts();
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>PunktePass Admin</title>
            <!-- Google Fonts DISABLED for performance -->
            <!-- <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"> -->
            <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                    min-height: 100vh;
                    color: #fff;
                }
                .admin-header {
                    background: rgba(255, 255, 255, 0.03);
                    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                    padding: 15px 30px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .admin-logo {
                    font-size: 22px;
                    font-weight: 700;
                    color: #00e6ff;
                }
                .admin-nav {
                    display: flex;
                    gap: 6px;
                    flex-wrap: wrap;
                    justify-content: center;
                    max-width: 900px;
                }
                .admin-nav a {
                    color: #888;
                    text-decoration: none;
                    padding: 6px 10px;
                    border-radius: 6px;
                    transition: all 0.3s;
                    font-size: 12px;
                    white-space: nowrap;
                }
                .admin-nav a:hover, .admin-nav a.active {
                    color: #fff;
                    background: rgba(255, 255, 255, 0.05);
                }
                .admin-nav a.active {
                    background: rgba(0, 230, 255, 0.1);
                    color: #00e6ff;
                }
                .admin-nav a {
                    position: relative;
                }
                .nav-badge {
                    position: absolute;
                    top: -5px;
                    right: -5px;
                    background: #f44336;
                    color: #fff;
                    font-size: 10px;
                    font-weight: 700;
                    min-width: 18px;
                    height: 18px;
                    border-radius: 9px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0 5px;
                    box-shadow: 0 2px 8px rgba(244, 67, 54, 0.5);
                    animation: pulse-badge 2s infinite;
                }
                @keyframes pulse-badge {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.1); }
                }
                .admin-user {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                .admin-user span {
                    color: #888;
                    font-size: 14px;
                }
                .admin-user .logout-btn {
                    color: #f44336;
                    text-decoration: none;
                    font-size: 14px;
                }
                .admin-content {
                    padding: 30px;
                    max-width: 1400px;
                    margin: 0 auto;
                }
                .page-title {
                    font-size: 24px;
                    font-weight: 600;
                    margin-bottom: 25px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .card {
                    background: rgba(255, 255, 255, 0.03);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    border-radius: 15px;
                    padding: 25px;
                    margin-bottom: 20px;
                }
                .card-title {
                    font-size: 18px;
                    font-weight: 600;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .stat-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                }
                .stat-card {
                    background: rgba(255, 255, 255, 0.03);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    border-radius: 12px;
                    padding: 20px;
                    text-align: center;
                }
                .stat-card .number {
                    font-size: 36px;
                    font-weight: 700;
                    color: #00e6ff;
                }
                .stat-card .label {
                    color: #888;
                    font-size: 14px;
                    margin-top: 5px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                }
                th, td {
                    text-align: left;
                    padding: 12px 15px;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
                }
                th {
                    color: #888;
                    font-weight: 500;
                    font-size: 13px;
                    text-transform: uppercase;
                }
                td {
                    font-size: 14px;
                }
                .btn {
                    display: inline-block;
                    padding: 8px 16px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-size: 13px;
                    font-weight: 500;
                    transition: all 0.3s;
                    border: none;
                    cursor: pointer;
                }
                .btn-approve {
                    background: rgba(76, 175, 80, 0.2);
                    color: #4caf50;
                    border: 1px solid rgba(76, 175, 80, 0.3);
                }
                .btn-approve:hover {
                    background: #4caf50;
                    color: #fff;
                }
                .btn-reject {
                    background: rgba(244, 67, 54, 0.2);
                    color: #f44336;
                    border: 1px solid rgba(244, 67, 54, 0.3);
                }
                .btn-reject:hover {
                    background: #f44336;
                    color: #fff;
                }
                .badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 500;
                }
                .badge-pending {
                    background: rgba(255, 152, 0, 0.2);
                    color: #ff9800;
                }
                .badge-approved {
                    background: rgba(76, 175, 80, 0.2);
                    color: #4caf50;
                }
                .badge-rejected {
                    background: rgba(244, 67, 54, 0.2);
                    color: #f44336;
                }
                .badge-add { background: rgba(33, 150, 243, 0.2); color: #2196f3; }
                .badge-remove { background: rgba(244, 67, 54, 0.2); color: #f44336; }
                .badge-mobile { background: rgba(156, 39, 176, 0.2); color: #9c27b0; }
                .success-msg {
                    background: rgba(76, 175, 80, 0.1);
                    border: 1px solid rgba(76, 175, 80, 0.3);
                    color: #4caf50;
                    padding: 15px;
                    border-radius: 10px;
                    margin-bottom: 20px;
                }
                .error-msg {
                    background: rgba(244, 67, 54, 0.1);
                    border: 1px solid rgba(244, 67, 54, 0.3);
                    color: #f44336;
                    padding: 15px;
                    border-radius: 10px;
                    margin-bottom: 20px;
                }
                .btn-sm {
                    padding: 6px 12px;
                    font-size: 12px;
                }
                .empty-state {
                    text-align: center;
                    padding: 60px 20px;
                    color: #666;
                }
                .empty-state i {
                    font-size: 48px;
                    margin-bottom: 15px;
                    display: block;
                }
                @media (max-width: 768px) {
                    .admin-header {
                        flex-direction: column;
                        gap: 15px;
                    }
                    .admin-nav {
                        flex-wrap: wrap;
                        justify-content: center;
                    }
                    .admin-content {
                        padding: 20px 15px;
                    }
                }
            </style>
        </head>
        <body>
            <header class="admin-header">
                <div class="admin-logo">PunktePass Admin</div>
                <nav class="admin-nav">
                    <a href="/admin/dashboard" class="<?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                        <i class="ri-dashboard-line"></i> Vezérlőpult
                    </a>
                    <a href="/admin/handlers" class="<?php echo $current_page === 'handlers' ? 'active' : ''; ?>">
                        <i class="ri-store-2-line"></i> Handlerek
                    </a>
                    <a href="/admin/device-requests" class="<?php echo $current_page === 'device-requests' ? 'active' : ''; ?>">
                        <i class="ri-smartphone-line"></i> Készülékek
                        <?php if ($counts['device_requests'] > 0): ?><span class="nav-badge"><?php echo $counts['device_requests']; ?></span><?php endif; ?>
                    </a>
                    <a href="/admin/support" class="<?php echo $current_page === 'support' ? 'active' : ''; ?>">
                        <i class="ri-customer-service-line"></i> Támogatás
                        <?php if ($counts['support'] > 0): ?><span class="nav-badge"><?php echo $counts['support']; ?></span><?php endif; ?>
                    </a>
                    <a href="/admin/renewals" class="<?php echo $current_page === 'renewals' ? 'active' : ''; ?>">
                        <i class="ri-refresh-line"></i> Megújítások
                        <?php if ($counts['renewals'] > 0): ?><span class="nav-badge"><?php echo $counts['renewals']; ?></span><?php endif; ?>
                    </a>
                    <a href="/admin/suspicious-scans" class="<?php echo $current_page === 'suspicious-scans' ? 'active' : ''; ?>">
                        <i class="ri-alarm-warning-line"></i> Gyanús
                        <?php if ($counts['suspicious'] > 0): ?><span class="nav-badge"><?php echo $counts['suspicious']; ?></span><?php endif; ?>
                    </a>
                    <a href="/admin/pending-scans" class="<?php echo $current_page === 'pending-scans' ? 'active' : ''; ?>">
                        <i class="ri-time-line"></i> Függő
                        <?php if ($counts['pending'] > 0): ?><span class="nav-badge"><?php echo $counts['pending']; ?></span><?php endif; ?>
                    </a>
                    <a href="/admin/devices" class="<?php echo $current_page === 'devices' ? 'active' : ''; ?>">
                        <i class="ri-device-line"></i> Eszközök
                    </a>
                    <a href="/admin/pos-log" class="<?php echo $current_page === 'pos-log' ? 'active' : ''; ?>">
                        <i class="ri-file-list-line"></i> POS Log
                    </a>
                    <a href="/admin/db-health" class="<?php echo $current_page === 'db-health' ? 'active' : ''; ?>">
                        <i class="ri-database-2-line"></i> DB Health
                    </a>
                    <a href="/admin/whatsapp" class="<?php echo $current_page === 'whatsapp' ? 'active' : ''; ?>">
                        <i class="ri-whatsapp-line"></i> WhatsApp
                    </a>
                    <a href="/admin/contracts" class="<?php echo $current_page === 'contracts' ? 'active' : ''; ?>">
                        <i class="ri-file-text-line"></i> Szerződések
                    </a>
                    <a href="/admin/email-sender" class="<?php echo $current_page === 'email-sender' ? 'active' : ''; ?>">
                        <i class="ri-mail-send-line"></i> Email Sender
                    </a>
                    <a href="/admin/push-sender" class="<?php echo $current_page === 'push-sender' ? 'active' : ''; ?>">
                        <i class="ri-notification-3-line"></i> Push Sender
                    </a>
                    <a href="/admin/sales-map" class="<?php echo $current_page === 'sales-map' ? 'active' : ''; ?>">
                        <i class="ri-map-pin-line"></i> Sales Map
                    </a>
                    <a href="/admin/dev-settings" class="<?php echo $current_page === 'dev-settings' ? 'active' : ''; ?>">
                        <i class="ri-settings-3-line"></i> Dev Settings
                    </a>
                </nav>
                <div class="admin-user">
                    <span><?php echo esc_html($admin_email); ?></span>
                    <a href="/admin/logout" class="logout-btn"><i class="ri-logout-box-line"></i> Kilépés</a>
                </div>
            </header>
            <main class="admin-content">
        <?php
    }

    /**
     * Admin lábléc HTML
     */
    private static function get_admin_footer() {
        ?>
            </main>
        </body>
        </html>
        <?php
    }

    /**
     * Get notification counts for admin menu badges
     */
    private static function get_notification_counts() {
        global $wpdb;

        // Cache key for performance
        $cache_key = 'ppv_admin_nav_counts';
        $cached = wp_cache_get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $counts = [
            'device_requests' => 0,
            'support' => 0,
            'renewals' => 0,
            'suspicious' => 0,
            'pending' => 0
        ];

        // Pending device requests (new/pending status)
        $counts['device_requests'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_device_requests WHERE status = 'pending'"
        ));

        // New support tickets (status = 'new')
        $counts['support'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE status = 'new'"
        ));

        // Pending renewal requests (subscription + filiale)
        $subscription_renewals = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE subscription_renewal_requested IS NOT NULL"
        ));
        $filiale_requests = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_filiale_requests WHERE status = 'pending'"
        ));
        $counts['renewals'] = $subscription_renewals + $filiale_requests;

        // Suspicious scans (last 24 hours, unreviewed)
        $counts['suspicious'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_suspicious_scans
             WHERE reviewed_at IS NULL
             AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        ));

        // Pending redemptions (waiting for approval)
        $counts['pending'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_rewards_redeemed WHERE status = 'pending'"
        ));

        // Cache for 1 minute
        wp_cache_set($cache_key, $counts, '', 60);

        return $counts;
    }

    /**
     * Vezérlőpult megjelenítése
     */
    private static function render_dashboard() {
        global $wpdb;

        // Statisztikák lekérése
        $pending_device_requests = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_device_requests WHERE status = 'pending'"
        );
        $pending_mobile_requests = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_device_requests WHERE status = 'pending' AND request_type = 'mobile_scanner'"
        );
        $total_handlers = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE parent_store_id IS NULL OR parent_store_id = 0"
        );
        $active_devices = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_user_devices WHERE status = 'active'"
        );
        $total_users = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_users"
        );

        self::get_admin_header('dashboard');
        ?>
        <h1 class="page-title"><i class="ri-dashboard-line"></i> Vezérlőpult</h1>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="number"><?php echo intval($pending_device_requests); ?></div>
                <div class="label">Nyitott készülék kérelmek</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo intval($pending_mobile_requests); ?></div>
                <div class="label">Mobile Scanner kérelmek</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo intval($total_handlers); ?></div>
                <div class="label">Handlerek</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo intval($active_devices); ?></div>
                <div class="label">Regisztrált készülékek</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo intval($total_users); ?></div>
                <div class="label">Regisztrált felhasználók</div>
            </div>
        </div>

        <?php
        // Legutóbbi kérelmek
        $recent_requests = $wpdb->get_results(
            "SELECT r.*, s.name as store_name
             FROM {$wpdb->prefix}ppv_device_requests r
             LEFT JOIN {$wpdb->prefix}ppv_stores s ON r.store_id = s.id
             ORDER BY r.requested_at DESC LIMIT 10"
        );
        ?>

        <div class="card">
            <h3 class="card-title"><i class="ri-time-line"></i> Legutóbbi kérelmek</h3>
            <?php if (empty($recent_requests)): ?>
                <div class="empty-state">
                    <i class="ri-inbox-line"></i>
                    <p>Nincsenek kérelmek</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Dátum</th>
                            <th>Üzlet</th>
                            <th>Típus</th>
                            <th>Státusz</th>
                            <th>Műveletek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_requests as $req): ?>
                            <tr>
                                <td><?php echo date('Y.m.d H:i', strtotime($req->requested_at)); ?></td>
                                <td><?php echo esc_html($req->store_name ?: "Store #{$req->store_id}"); ?></td>
                                <td>
                                    <?php if ($req->request_type === 'add'): ?>
                                        <span class="badge badge-add">Hozzáadás</span>
                                    <?php elseif ($req->request_type === 'remove'): ?>
                                        <span class="badge badge-remove">Eltávolítás</span>
                                    <?php else: ?>
                                        <span class="badge badge-mobile">Mobile Scanner</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($req->status === 'pending'): ?>
                                        <span class="badge badge-pending">Függőben</span>
                                    <?php elseif ($req->status === 'approved'): ?>
                                        <span class="badge badge-approved">Jóváhagyva</span>
                                    <?php else: ?>
                                        <span class="badge badge-rejected">Elutasítva</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($req->status === 'pending'): ?>
                                        <a href="/admin/approve/<?php echo $req->approval_token; ?>" class="btn btn-approve">Jóváhagyás</a>
                                        <a href="/admin/reject/<?php echo $req->approval_token; ?>" class="btn btn-reject">Elutasítás</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        self::get_admin_footer();
    }

    /**
     * Készülék kérelmek oldal
     */
    private static function render_device_requests() {
        global $wpdb;

        $success = $_GET['success'] ?? '';

        // Összes kérelem (pending előre, utána a feldolgozottak)
        $requests = $wpdb->get_results(
            "SELECT r.*, s.name as store_name, s.city as store_city
             FROM {$wpdb->prefix}ppv_device_requests r
             LEFT JOIN {$wpdb->prefix}ppv_stores s ON r.store_id = s.id
             ORDER BY
                CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END,
                r.requested_at DESC
             LIMIT 50"
        );

        // Pending és feldolgozott szétválasztása
        $pending_requests = array_filter($requests, fn($r) => $r->status === 'pending');
        $processed_requests = array_filter($requests, fn($r) => $r->status !== 'pending');

        self::get_admin_header('device-requests');
        ?>
        <h1 class="page-title"><i class="ri-smartphone-line"></i> Készülék kérelmek</h1>

        <?php if ($success): ?>
            <div class="success-msg"><?php echo esc_html($success); ?></div>
        <?php endif; ?>

        <!-- Nyitott kérelmek -->
        <h2 style="color: #ff9800; margin-bottom: 15px;">
            <i class="ri-time-line"></i> Nyitott kérelmek (<?php echo count($pending_requests); ?>)
        </h2>
        <div class="card">
            <?php if (empty($pending_requests)): ?>
                <div class="empty-state">
                    <i class="ri-checkbox-circle-line"></i>
                    <p>Nincsenek nyitott kérelmek</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Dátum</th>
                            <th>Üzlet</th>
                            <th>Készülék név</th>
                            <th>Eszköz modell</th>
                            <th>Típus</th>
                            <th>IP</th>
                            <th>Műveletek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_requests as $req):
                            $device_info = self::parse_device_info($req->user_agent ?? '');
                        ?>
                            <tr>
                                <td><?php echo date('Y.m.d H:i', strtotime($req->requested_at)); ?></td>
                                <td>
                                    <strong><?php echo esc_html($req->store_name ?: "Store #{$req->store_id}"); ?></strong>
                                    <?php if ($req->store_city): ?>
                                        <br><small style="color: #888;"><?php echo esc_html($req->store_city); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($req->device_name); ?></td>
                                <td title="<?php echo esc_attr($req->user_agent ?? ''); ?>">
                                    <strong style="color: #00e6ff;"><?php echo esc_html($device_info['model']); ?></strong>
                                    <?php if ($device_info['os']): ?>
                                        <br><small style="color: #aaa;"><?php echo esc_html($device_info['os']); ?></small>
                                    <?php endif; ?>
                                    <?php if ($device_info['browser']): ?>
                                        <br><small style="color: #666;"><?php echo esc_html($device_info['browser']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($req->request_type === 'add'): ?>
                                        <span class="badge badge-add">Hozzáadás</span>
                                    <?php elseif ($req->request_type === 'remove'): ?>
                                        <span class="badge badge-remove">Eltávolítás</span>
                                    <?php else: ?>
                                        <span class="badge badge-mobile">Mobile Scanner</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo esc_html($req->ip_address); ?></small></td>
                                <td>
                                    <a href="/admin/approve/<?php echo $req->approval_token; ?>" class="btn btn-approve">
                                        <i class="ri-check-line"></i> Jóváhagyás
                                    </a>
                                    <a href="/admin/reject/<?php echo $req->approval_token; ?>" class="btn btn-reject">
                                        <i class="ri-close-line"></i> Elutasítás
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Feldolgozott kérelmek -->
        <?php if (!empty($processed_requests)): ?>
        <h2 style="color: #888; margin: 30px 0 15px 0;">
            <i class="ri-history-line"></i> Feldolgozott kérelmek
        </h2>
        <div class="card" style="opacity: 0.8;">
            <table>
                <thead>
                    <tr>
                        <th>Dátum</th>
                        <th>Üzlet</th>
                        <th>Készülék név</th>
                        <th>Eszköz modell</th>
                        <th>Típus</th>
                        <th>Állapot</th>
                        <th>Feldolgozta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processed_requests as $req):
                        $device_info = self::parse_device_info($req->user_agent ?? '');
                    ?>
                        <tr style="opacity: 0.7;">
                            <td><?php echo date('Y.m.d H:i', strtotime($req->requested_at)); ?></td>
                            <td>
                                <strong><?php echo esc_html($req->store_name ?: "Store #{$req->store_id}"); ?></strong>
                            </td>
                            <td><?php echo esc_html($req->device_name); ?></td>
                            <td>
                                <strong style="color: #00e6ff;"><?php echo esc_html($device_info['model']); ?></strong>
                                <?php if ($device_info['os']): ?>
                                    <br><small style="color: #aaa;"><?php echo esc_html($device_info['os']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($req->request_type === 'add'): ?>
                                    <span class="badge badge-add">Hozzáadás</span>
                                <?php elseif ($req->request_type === 'remove'): ?>
                                    <span class="badge badge-remove">Eltávolítás</span>
                                <?php else: ?>
                                    <span class="badge badge-mobile">Mobile Scanner</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($req->status === 'approved'): ?>
                                    <span style="color: #4caf50;">✅ Jóváhagyva</span>
                                <?php else: ?>
                                    <span style="color: #f44336;">❌ Elutasítva</span>
                                <?php endif; ?>
                                <?php if ($req->processed_at): ?>
                                    <br><small style="color: #666;"><?php echo date('m.d H:i', strtotime($req->processed_at)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small style="color: #888;"><?php echo esc_html($req->processed_by ?? '-'); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php
        self::get_admin_footer();
    }

    /**
     * Eszköz törlési napló oldal
     * Megjeleníti a felhasználók által törölt eszközöket (handler és scanner userek)
     */
    private static function render_device_deletion_log() {
        global $wpdb;

        // Ellenőrizzük, hogy létezik-e a tábla
        $table_name = $wpdb->prefix . 'ppv_device_deletion_log';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

        // Törlési napló lekérése
        $deletions = [];
        if ($table_exists) {
            $deletions = $wpdb->get_results(
                "SELECT dl.*, s.name as store_name, s.city as store_city
                 FROM {$table_name} dl
                 LEFT JOIN {$wpdb->prefix}ppv_stores s ON dl.store_id = s.id
                 ORDER BY dl.deleted_at DESC
                 LIMIT 100"
            );
        }

        self::get_admin_header('device-deletion-log');
        ?>
        <h1 class="page-title"><i class="ri-delete-bin-line"></i> Eszköz törlési napló</h1>

        <div class="card">
            <p style="color: #888; margin-bottom: 20px;">
                <i class="ri-information-line"></i>
                Itt láthatod mely eszközöket törölték a felhasználók (Handler-ek és Scanner felhasználók).
            </p>

            <?php if (!$table_exists): ?>
                <div class="empty-state">
                    <i class="ri-database-2-line"></i>
                    <p>A törlési napló tábla még nem létezik.<br>Az első törlés után automatikusan létrejön.</p>
                </div>
            <?php elseif (empty($deletions)): ?>
                <div class="empty-state">
                    <i class="ri-checkbox-circle-line"></i>
                    <p>Még nem történt eszköz törlés</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Törlés dátuma</th>
                            <th>Üzlet</th>
                            <th>Törölt eszköz</th>
                            <th>Törölte</th>
                            <th>User típus</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deletions as $del): ?>
                            <tr>
                                <td><?php echo date('Y.m.d H:i', strtotime($del->deleted_at)); ?></td>
                                <td>
                                    <strong><?php echo esc_html($del->store_name ?: "Store #{$del->store_id}"); ?></strong>
                                    <?php if (!empty($del->store_city)): ?>
                                        <br><small style="color: #888;"><?php echo esc_html($del->store_city); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: #f44336;"><?php echo esc_html($del->device_name ?: 'N/A'); ?></strong>
                                    <br><small style="color: #666;">ID: #<?php echo $del->device_id; ?></small>
                                </td>
                                <td>
                                    <?php echo esc_html($del->deleted_by_user_email ?: "User #{$del->deleted_by_user_id}"); ?>
                                </td>
                                <td>
                                    <?php if ($del->deleted_by_user_type === 'scanner'): ?>
                                        <span class="badge" style="background: rgba(156, 39, 176, 0.2); color: #9c27b0;">
                                            <i class="ri-qr-scan-2-line"></i> Scanner
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(33, 150, 243, 0.2); color: #2196f3;">
                                            <i class="ri-store-2-line"></i> Handler
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><small style="color: #888;"><?php echo esc_html($del->ip_address ?: '-'); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        self::get_admin_footer();
    }

    /**
     * Handlerek oldal
     */
    private static function render_handlers_page() {
        // ✅ Use new standalone handlers page with user-to-handler conversion
        require_once __DIR__ . '/admin/standalone/handlers.php';
    }

    /**
     * Üzenet oldal megjelenítése
     */
    private static function render_message_page($type, $message) {
        self::get_admin_header('');
        ?>
        <div class="card" style="max-width: 500px; margin: 50px auto; text-align: center;">
            <?php if ($type === 'error'): ?>
                <i class="ri-error-warning-line" style="font-size: 48px; color: #f44336;"></i>
            <?php else: ?>
                <i class="ri-checkbox-circle-line" style="font-size: 48px; color: #4caf50;"></i>
            <?php endif; ?>
            <h2 style="margin: 20px 0;"><?php echo esc_html($message); ?></h2>
            <a href="/admin/dashboard" class="btn" style="background: #00e6ff; color: #000;">Vissza a vezérlőpulthoz</a>
        </div>
        <?php
        self::get_admin_footer();
    }

    /**
     * WhatsApp beállítások oldal
     */
    private static function render_whatsapp_settings() {
        global $wpdb;

        // POST kezelése
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['whatsapp_save'])) {
            self::handle_whatsapp_save();
        }

        // Get all stores for dropdown
        $stores = $wpdb->get_results(
            "SELECT id, name, city, whatsapp_enabled, whatsapp_phone_id, whatsapp_business_id,
                    whatsapp_access_token, whatsapp_marketing_enabled, whatsapp_support_enabled
             FROM {$wpdb->prefix}ppv_stores
             WHERE parent_store_id IS NULL OR parent_store_id = 0
             ORDER BY name ASC"
        );

        $selected_store_id = intval($_GET['store_id'] ?? ($stores[0]->id ?? 0));
        $selected_store = null;
        foreach ($stores as $store) {
            if ($store->id == $selected_store_id) {
                $selected_store = $store;
                break;
            }
        }

        $success = $_GET['success'] ?? '';
        $error = $_GET['error'] ?? '';

        self::get_admin_header('whatsapp');
        ?>
        <h1 class="page-title">
            <i class="ri-whatsapp-line" style="color: #25D366;"></i> WhatsApp Cloud API Beállítások
        </h1>

        <?php if ($success): ?>
            <div class="success-msg"><?php echo esc_html($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error-msg"><?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <!-- Store kiválasztó -->
        <div class="card" style="margin-bottom: 20px;">
            <form method="GET" action="/admin/whatsapp" style="display: flex; align-items: center; gap: 15px;">
                <label style="color: #888;">Handler kiválasztása:</label>
                <select name="store_id" onchange="this.form.submit()" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 10px 15px; color: #fff; font-size: 14px; min-width: 250px;">
                    <?php foreach ($stores as $store): ?>
                        <option value="<?php echo $store->id; ?>" <?php selected($store->id, $selected_store_id); ?>>
                            <?php echo esc_html($store->name); ?>
                            <?php if ($store->city): ?>(<?php echo esc_html($store->city); ?>)<?php endif; ?>
                            <?php if ($store->whatsapp_enabled): ?> ✓<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selected_store): ?>
        <form method="POST" action="/admin/whatsapp?store_id=<?php echo $selected_store_id; ?>">
            <input type="hidden" name="whatsapp_save" value="1">
            <input type="hidden" name="store_id" value="<?php echo $selected_store_id; ?>">

            <!-- API Konfiguráció -->
            <div class="card">
                <h3 class="card-title" style="color: #25D366;">
                    <i class="ri-key-2-line"></i> API Konfiguráció
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <label style="display: block; color: #fff; margin-bottom: 8px;">Phone Number ID</label>
                        <input type="text" name="whatsapp_phone_id"
                               value="<?php echo esc_attr($selected_store->whatsapp_phone_id ?? ''); ?>"
                               placeholder="123456789012345"
                               style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 12px; color: #fff; font-size: 14px;">
                        <small style="color: #666; display: block; margin-top: 5px;">Meta Business Suite → WhatsApp Manager → API-Setup</small>
                    </div>
                    <div>
                        <label style="display: block; color: #fff; margin-bottom: 8px;">Business Account ID</label>
                        <input type="text" name="whatsapp_business_id"
                               value="<?php echo esc_attr($selected_store->whatsapp_business_id ?? ''); ?>"
                               placeholder="123456789012345"
                               style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 12px; color: #fff; font-size: 14px;">
                        <small style="color: #666; display: block; margin-top: 5px;">WhatsApp-Unternehmenskonto-ID</small>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <label style="display: block; color: #fff; margin-bottom: 8px;">Access Token</label>
                    <input type="password" name="whatsapp_access_token" id="wa-token"
                           value="<?php echo !empty($selected_store->whatsapp_access_token) ? '••••••••••••••••••••••••' : ''; ?>"
                           placeholder="EAAxxxxxxx..."
                           style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 12px; color: #fff; font-size: 14px;">
                    <small style="color: #666; display: block; margin-top: 5px;">System User Token mit whatsapp_business_messaging Berechtigung</small>
                    <button type="button" onclick="document.getElementById('wa-token').type = document.getElementById('wa-token').type === 'password' ? 'text' : 'password'"
                            style="margin-top: 8px; background: rgba(255,255,255,0.1); border: none; border-radius: 6px; padding: 6px 12px; color: #888; cursor: pointer; font-size: 12px;">
                        <i class="ri-eye-line"></i> Token anzeigen/verbergen
                    </button>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 15px;">
                    <button type="button" id="verify-connection-btn" class="btn" style="background: rgba(37,211,102,0.2); color: #25D366; border: 1px solid rgba(37,211,102,0.3);">
                        <i class="ri-check-double-line"></i> Verbindung testen
                    </button>
                    <span id="verify-result" style="display: none; padding: 10px 15px; border-radius: 8px;"></span>
                </div>
            </div>

            <!-- Funkciók -->
            <div class="card">
                <h3 class="card-title" style="color: #00e6ff;">
                    <i class="ri-toggle-line"></i> Funkciók
                </h3>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <label style="display: flex; align-items: center; gap: 12px; background: rgba(0,0,0,0.2); padding: 20px; border-radius: 10px; cursor: pointer;">
                        <input type="checkbox" name="whatsapp_enabled" value="1" <?php checked($selected_store->whatsapp_enabled ?? 0, 1); ?>
                               style="width: 20px; height: 20px; accent-color: #25D366;">
                        <div>
                            <strong style="color: #fff; display: block;">WhatsApp aktív</strong>
                            <small style="color: #888;">API-Verbindung aktivieren</small>
                        </div>
                    </label>

                    <label style="display: flex; align-items: center; gap: 12px; background: rgba(0,0,0,0.2); padding: 20px; border-radius: 10px; cursor: pointer;">
                        <input type="checkbox" name="whatsapp_marketing_enabled" value="1" <?php checked($selected_store->whatsapp_marketing_enabled ?? 0, 1); ?>
                               style="width: 20px; height: 20px; accent-color: #ff9800;">
                        <div>
                            <strong style="color: #fff; display: block;">📣 Marketing</strong>
                            <small style="color: #888;">Geburtstag & Comeback</small>
                        </div>
                    </label>

                    <label style="display: flex; align-items: center; gap: 12px; background: rgba(0,0,0,0.2); padding: 20px; border-radius: 10px; cursor: pointer;">
                        <input type="checkbox" name="whatsapp_support_enabled" value="1" <?php checked($selected_store->whatsapp_support_enabled ?? 0, 1); ?>
                               style="width: 20px; height: 20px; accent-color: #2196f3;">
                        <div>
                            <strong style="color: #fff; display: block;">🎧 Support Chat</strong>
                            <small style="color: #888;">Kunden-Nachrichten</small>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Webhook Info -->
            <div class="card" style="background: rgba(37,211,102,0.05); border-color: rgba(37,211,102,0.2);">
                <h3 class="card-title" style="color: #25D366;">
                    <i class="ri-webhook-line"></i> Webhook Konfiguráció (Meta Dashboard-ban beállítandó)
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <label style="display: block; color: #888; margin-bottom: 8px;">Webhook URL:</label>
                        <div style="background: rgba(0,0,0,0.3); padding: 12px; border-radius: 8px; font-family: monospace; font-size: 13px; color: #25D366; word-break: break-all;">
                            <?php echo esc_html(home_url('/wp-json/punktepass/v1/whatsapp-webhook')); ?>
                        </div>
                    </div>
                    <div>
                        <label style="display: block; color: #888; margin-bottom: 8px;">Verify Token:</label>
                        <div style="background: rgba(0,0,0,0.3); padding: 12px; border-radius: 8px; font-family: monospace; font-size: 13px; color: #25D366;">
                            punktepass_whatsapp_2024
                        </div>
                    </div>
                </div>

                <div style="margin-top: 15px; padding: 12px; background: rgba(255,152,0,0.1); border-radius: 8px; border: 1px solid rgba(255,152,0,0.3);">
                    <p style="margin: 0; color: #ff9800; font-size: 13px;">
                        <i class="ri-information-line"></i> <strong>Felder für Webhook-Abonnement:</strong> messages, message_status
                    </p>
                </div>
            </div>

            <!-- Testnachricht -->
            <div class="card">
                <h3 class="card-title" style="color: #9c27b0;">
                    <i class="ri-send-plane-line"></i> Testnachricht senden
                </h3>

                <div style="display: flex; gap: 15px; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label style="display: block; color: #888; margin-bottom: 8px;">Telefonnummer:</label>
                        <input type="tel" id="test-phone" placeholder="+49 176 12345678"
                               style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 12px; color: #fff; font-size: 14px;">
                    </div>
                    <button type="button" id="send-test-btn" class="btn" style="background: rgba(156,39,176,0.2); color: #9c27b0; border: 1px solid rgba(156,39,176,0.3); white-space: nowrap;">
                        <i class="ri-send-plane-fill"></i> Test senden
                    </button>
                </div>
                <small style="color: #666; display: block; margin-top: 8px;">Sendet das "Hello World" Template an diese Nummer</small>
                <div id="test-result" style="margin-top: 15px; display: none;"></div>
            </div>

            <!-- Mentés gomb -->
            <div style="text-align: right; margin-top: 20px;">
                <button type="submit" class="btn" style="background: linear-gradient(135deg, #25D366, #128C7E); color: #fff; padding: 14px 30px; font-size: 16px;">
                    <i class="ri-save-line"></i> Beállítások mentése
                </button>
            </div>
        </form>

        <script>
        // Verbindung testen
        document.getElementById('verify-connection-btn').addEventListener('click', async function() {
            const btn = this;
            const result = document.getElementById('verify-result');

            btn.disabled = true;
            btn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Prüfe...';
            result.style.display = 'none';

            try {
                const response = await fetch('/wp-json/punktepass/v1/whatsapp/verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ store_id: <?php echo $selected_store_id; ?> })
                });
                const data = await response.json();

                result.style.display = 'inline-block';
                if (data.success) {
                    result.style.background = 'rgba(76,175,80,0.2)';
                    result.style.color = '#4caf50';
                    result.innerHTML = '✓ ' + (data.data?.message || 'Verbindung OK');
                } else {
                    result.style.background = 'rgba(244,67,54,0.2)';
                    result.style.color = '#f44336';
                    result.innerHTML = '✗ ' + (data.message || 'Fehler');
                }
            } catch (e) {
                result.style.display = 'inline-block';
                result.style.background = 'rgba(244,67,54,0.2)';
                result.style.color = '#f44336';
                result.innerHTML = '✗ Netzwerkfehler';
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="ri-check-double-line"></i> Verbindung testen';
        });

        // Testnachricht senden
        document.getElementById('send-test-btn').addEventListener('click', async function() {
            const btn = this;
            const phone = document.getElementById('test-phone').value;
            const result = document.getElementById('test-result');

            if (!phone) {
                alert('Bitte Telefonnummer eingeben');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Sende...';

            try {
                const response = await fetch('/wp-json/punktepass/v1/whatsapp/test', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ store_id: <?php echo $selected_store_id; ?>, phone: phone })
                });
                const data = await response.json();

                result.style.display = 'block';
                if (data.success) {
                    result.innerHTML = '<div style="background: rgba(76,175,80,0.2); color: #4caf50; padding: 12px; border-radius: 8px;">✓ ' + (data.data?.message || 'Nachricht gesendet!') + '</div>';
                } else {
                    result.innerHTML = '<div style="background: rgba(244,67,54,0.2); color: #f44336; padding: 12px; border-radius: 8px;">✗ ' + (data.message || 'Fehler') + '</div>';
                }
            } catch (e) {
                result.style.display = 'block';
                result.innerHTML = '<div style="background: rgba(244,67,54,0.2); color: #f44336; padding: 12px; border-radius: 8px;">✗ Netzwerkfehler</div>';
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="ri-send-plane-fill"></i> Test senden';
        });
        </script>
        <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <i class="ri-store-2-line"></i>
                    <p>Keine Handler gefunden</p>
                </div>
            </div>
        <?php endif; ?>
        <?php
        self::get_admin_footer();
    }

    /**
     * Developer Settings Page (standalone)
     */
    private static function render_dev_settings() {
        self::get_admin_header('dev-settings');
        require_once __DIR__ . '/admin/standalone/dev-settings.php';
        self::get_admin_footer();
    }

    /**
     * WhatsApp beállítások mentése
     */
    private static function handle_whatsapp_save() {
        global $wpdb;

        $store_id = intval($_POST['store_id'] ?? 0);
        if (!$store_id) {
            wp_redirect('/admin/whatsapp?error=' . urlencode('Nincs kiválasztva handler'));
            exit;
        }

        $update_data = [
            'whatsapp_enabled' => !empty($_POST['whatsapp_enabled']) ? 1 : 0,
            'whatsapp_phone_id' => sanitize_text_field($_POST['whatsapp_phone_id'] ?? ''),
            'whatsapp_business_id' => sanitize_text_field($_POST['whatsapp_business_id'] ?? ''),
            'whatsapp_marketing_enabled' => !empty($_POST['whatsapp_marketing_enabled']) ? 1 : 0,
            'whatsapp_support_enabled' => !empty($_POST['whatsapp_support_enabled']) ? 1 : 0,
        ];

        $format_specs = ['%d', '%s', '%s', '%d', '%d'];

        // Access Token - csak ha nem a maszkolás placeholder
        $wa_token = $_POST['whatsapp_access_token'] ?? '';
        if (!empty($wa_token) && strpos($wa_token, '••••') === false) {
            if (class_exists('PPV_WhatsApp')) {
                $update_data['whatsapp_access_token'] = PPV_WhatsApp::encrypt_token($wa_token);
            } else {
                $update_data['whatsapp_access_token'] = $wa_token;
            }
            $format_specs[] = '%s';
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            $update_data,
            ['id' => $store_id],
            $format_specs,
            ['%d']
        );

        $admin_email = $_SESSION['ppv_admin_email'] ?? 'admin';
        ppv_log("[WhatsApp Admin] Store #{$store_id} beállítások frissítve by {$admin_email}");

        wp_redirect('/admin/whatsapp?store_id=' . $store_id . '&success=' . urlencode('WhatsApp beállítások mentve!'));
        exit;
    }
}
