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

        // Meghosszabbítás: ha van subscription_expires_at, ahhoz adunk, ha nincs, mai naptól
        $current_end = !empty($handler->subscription_expires_at) ? $handler->subscription_expires_at : date('Y-m-d');
        $new_end = date('Y-m-d', strtotime($current_end . ' + ' . $days . ' days'));

        $result = $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            ['subscription_expires_at' => $new_end],
            ['id' => $handler_id],
            ['%s'],
            ['%d']
        );

        if ($result !== false) {
            $admin_email = $_SESSION['ppv_admin_email'] ?? 'admin';
            ppv_log("📅 [Admin Handler Extend] handler_id={$handler_id}, name={$handler->name}, days={$days}, new_end={$new_end}, by={$admin_email}");
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
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Inter', sans-serif;
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
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Inter', sans-serif;
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
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_scans
             WHERE is_suspicious = 1
             AND reviewed_at IS NULL
             AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        ));

        // Pending scans (waiting for confirmation)
        $counts['pending'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_scans WHERE status = 'pending'"
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
        global $wpdb;

        $handlers = $wpdb->get_results(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}ppv_user_devices WHERE store_id = s.id AND status = 'active') as device_count,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE parent_store_id = s.id) as filiale_count
             FROM {$wpdb->prefix}ppv_stores s
             WHERE s.parent_store_id IS NULL OR s.parent_store_id = 0
             ORDER BY s.name ASC"
        );

        // Eszközök lekérése handler-enként (device_info-val és mobile_scanner-rel együtt)
        $devices_by_store = [];
        $all_devices = $wpdb->get_results(
            "SELECT id, store_id, fingerprint_hash, device_name, user_agent, device_info, ip_address, registered_at, last_used_at, status, mobile_scanner
             FROM {$wpdb->prefix}ppv_user_devices WHERE status = 'active' ORDER BY registered_at DESC"
        );
        foreach ($all_devices as $device) {
            $devices_by_store[$device->store_id][] = $device;
        }

        $deleted = $_GET['deleted'] ?? '';
        $store_deleted = $_GET['store'] ?? '';
        $mobile_deactivated = $_GET['mobile_deactivated'] ?? '';
        $device_mobile_deactivated = $_GET['device_mobile_deactivated'] ?? '';
        $success = $_GET['success'] ?? '';
        $error = $_GET['error'] ?? '';

        self::get_admin_header('handlers');
        ?>
        <h1 class="page-title"><i class="ri-store-2-line"></i> Handler áttekintés</h1>

        <?php if ($deleted): ?>
            <div class="success-msg">✅ Eszköz törölve: <strong><?php echo esc_html($deleted); ?></strong> (<?php echo esc_html($store_deleted); ?>)</div>
        <?php endif; ?>
        <?php if ($mobile_deactivated): ?>
            <div class="success-msg">📵 Mobile Scanner deaktiválva (Handler): <strong><?php echo esc_html($mobile_deactivated); ?></strong></div>
        <?php endif; ?>
        <?php if ($device_mobile_deactivated): ?>
            <div class="success-msg">📵 Mobile Scanner deaktiválva (Készülék): <strong><?php echo esc_html($device_mobile_deactivated); ?></strong></div>
        <?php endif; ?>
        <?php if ($success === 'extended'): ?>
            <div class="success-msg">✅ Előfizetés sikeresen meghosszabbítva!</div>
        <?php elseif ($success === 'activated'): ?>
            <div class="success-msg">✅ Handler sikeresen aktiválva!</div>
        <?php elseif ($success === 'mobile_updated'): ?>
            <div class="success-msg">✅ Mobile Scanner beállítás frissítve!</div>
        <?php elseif ($success === 'filialen_updated'): ?>
            <div class="success-msg">✅ Fiók limit frissítve!</div>
        <?php elseif ($success === 'device_mobile_enabled'): ?>
            <div class="success-msg">✅ Mobile Scanner engedélyezve a készüléken!</div>
        <?php endif; ?>
        <?php if ($error === 'missing_reason'): ?>
            <div class="error-msg">❌ Add meg az indokot!</div>
        <?php elseif ($error === 'device_not_found'): ?>
            <div class="error-msg">❌ Eszköz nem található!</div>
        <?php elseif ($error === 'store_not_found'): ?>
            <div class="error-msg">❌ Handler nem található!</div>
        <?php elseif ($error === 'delete_failed'): ?>
            <div class="error-msg">❌ Hiba a törlés során!</div>
        <?php elseif ($error === 'deactivate_failed'): ?>
            <div class="error-msg">❌ Hiba a Mobile Scanner deaktiválása során!</div>
        <?php elseif ($error): ?>
            <div class="error-msg">❌ <?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <!-- Keresés és szűrés -->
        <div class="filter-bar" style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 200px;">
                <input type="text" id="handlerSearch" placeholder="Keresés név, város vagy email alapján..."
                       style="width: 100%; padding: 10px 15px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff; font-size: 14px;"
                       oninput="filterHandlers()">
            </div>
            <select id="statusFilter" onchange="filterHandlers()"
                    style="padding: 10px 15px; background: #1a1a2e; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; color: #fff; font-size: 14px;">
                <option value="" style="background: #1a1a2e; color: #fff;">Összes státusz</option>
                <option value="active" style="background: #1a1a2e; color: #4caf50;">Aktív</option>
                <option value="trial" style="background: #1a1a2e; color: #ff9800;">Trial</option>
                <option value="expired" style="background: #1a1a2e; color: #f44336;">Lejárt</option>
            </select>
            <select id="scannerFilter" onchange="filterHandlers()"
                    style="padding: 10px 15px; background: #1a1a2e; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; color: #fff; font-size: 14px;">
                <option value="" style="background: #1a1a2e; color: #fff;">Összes scanner</option>
                <option value="mobile" style="background: #1a1a2e; color: #00e6ff;">Mobile</option>
                <option value="fixed" style="background: #1a1a2e; color: #888;">Fixed</option>
            </select>
            <span id="handlerCount" style="color: #888; font-size: 13px;"></span>
        </div>

        <div class="card">
            <table id="handlersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Név</th>
                        <th>Város</th>
                        <th>Abo hátra</th>
                        <th>Scanner</th>
                        <th>Készülékek</th>
                        <th>Fiókok</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($handlers as $handler):
                        $handler_devices = $devices_by_store[$handler->id] ?? [];
                        // Készülék adatok előkészítése JSON-hoz
                        $devices_json = array_map(function($d) {
                            $info = self::parse_device_info($d->user_agent ?? '');
                            $fp = self::format_device_info_json($d->device_info ?? '');
                            return [
                                'id' => $d->id,
                                'name' => $d->device_name,
                                'model' => $info['model'],
                                'os' => $info['os'],
                                'mobile_scanner' => !empty($d->mobile_scanner),
                                'ip' => $d->ip_address,
                                'registered_at' => $d->registered_at,
                                'fingerprint' => $fp
                            ];
                        }, $handler_devices);

                        // Abo hátra számítás
                        $sub_status = $handler->subscription_status ?? 'trial';
                        $days_remaining = null;
                        $status_for_filter = $sub_status;

                        if ($sub_status === 'trial' && $handler->trial_ends_at) {
                            $days_remaining = (int) ((strtotime($handler->trial_ends_at) - time()) / 86400);
                        } elseif ($sub_status === 'active' && $handler->subscription_expires_at) {
                            $days_remaining = (int) ((strtotime($handler->subscription_expires_at) - time()) / 86400);
                        }

                        if ($days_remaining !== null && $days_remaining < 0) {
                            $status_for_filter = 'expired';
                        }

                        $handler_data = json_encode([
                            'id' => $handler->id,
                            'name' => $handler->name,
                            'company_name' => $handler->company_name ?? '',
                            'city' => $handler->city ?? '',
                            'email' => $handler->email ?? '',
                            'scanner_type' => $handler->scanner_type ?? 'fixed',
                            'subscription_status' => $handler->subscription_status ?? 'trial',
                            'trial_ends_at' => $handler->trial_ends_at ?? null,
                            'subscription_expires_at' => $handler->subscription_expires_at ?? null,
                            'max_filialen' => $handler->max_filialen ?? 1,
                            'filiale_count' => intval($handler->filiale_count),
                            'device_count' => count($handler_devices),
                            'devices' => $devices_json
                        ], JSON_HEX_APOS | JSON_HEX_QUOT);
                    ?>
                        <tr class="clickable-row handler-row"
                            onclick='openHandlerModal(<?php echo $handler_data; ?>)'
                            style="cursor: pointer;"
                            data-name="<?php echo esc_attr(strtolower($handler->name)); ?>"
                            data-city="<?php echo esc_attr(strtolower($handler->city ?? '')); ?>"
                            data-email="<?php echo esc_attr(strtolower($handler->email ?? '')); ?>"
                            data-status="<?php echo esc_attr($status_for_filter); ?>"
                            data-scanner="<?php echo esc_attr($handler->scanner_type ?? 'fixed'); ?>">
                            <td>#<?php echo $handler->id; ?></td>
                            <td><strong><?php echo esc_html($handler->name); ?></strong></td>
                            <td><?php echo esc_html($handler->city ?: '-'); ?></td>
                            <td>
                                <?php if ($days_remaining === null): ?>
                                    <span style="color: #666;">-</span>
                                <?php elseif ($days_remaining < 0): ?>
                                    <span style="color: #f44336; font-weight: 600;">Lejárt</span>
                                <?php elseif ($days_remaining <= 7): ?>
                                    <span style="color: #ff9800; font-weight: 600;"><?php echo $days_remaining; ?> nap</span>
                                <?php else: ?>
                                    <span style="color: #4caf50;"><?php echo $days_remaining; ?> nap</span>
                                <?php endif; ?>
                                <small style="display: block; color: #666; font-size: 10px;">
                                    <?php echo $sub_status === 'trial' ? 'Trial' : ($sub_status === 'active' ? 'Aktív' : $sub_status); ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($handler->scanner_type === 'mobile'): ?>
                                    <span class="badge badge-mobile">Mobile</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(255,255,255,0.1); color: #888;">Fixed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($handler_devices)): ?>
                                    <span style="color: #666;">0</span>
                                <?php else: ?>
                                    <strong style="color: #4caf50;"><?php echo count($handler_devices); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $current_filialen = intval($handler->filiale_count) + 1;
                                $max_filialen = intval($handler->max_filialen ?? 1);
                                ?>
                                <span style="color: <?php echo $current_filialen >= $max_filialen ? '#f44336' : '#4caf50'; ?>;">
                                    <?php echo $current_filialen; ?>/<?php echo $max_filialen; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Handler Details Modal -->
        <div id="handlerModal" class="handler-modal-overlay">
            <div class="handler-modal">
                <div class="handler-modal-header">
                    <div>
                        <h2 id="handlerModalTitle"><i class="ri-store-2-line"></i> Handler</h2>
                        <div class="handler-modal-stats" id="handlerModalStats"></div>
                    </div>
                    <button class="handler-modal-close" onclick="closeHandlerModal()"><i class="ri-close-line"></i></button>
                </div>
                <div class="handler-modal-body">
                    <!-- Tabs -->
                    <div class="handler-tabs">
                        <button class="handler-tab-btn active" onclick="switchHandlerTab('devices')"><i class="ri-smartphone-line"></i> Készülékek</button>
                        <button class="handler-tab-btn" onclick="switchHandlerTab('subscription')"><i class="ri-calendar-line"></i> Előfizetés</button>
                        <button class="handler-tab-btn" onclick="switchHandlerTab('mobile')"><i class="ri-phone-line"></i> Mobile Scanner</button>
                        <button class="handler-tab-btn" onclick="switchHandlerTab('filialen')"><i class="ri-building-line"></i> Fiókok</button>
                    </div>

                    <!-- Tab: Devices -->
                    <div id="handler-tab-devices" class="handler-tab-content active">
                        <div id="handlerDeviceList" class="handler-device-list"></div>
                    </div>

                    <!-- Tab: Subscription -->
                    <div id="handler-tab-subscription" class="handler-tab-content">
                        <div id="handlerSubscriptionInfo" class="handler-subscription-info"></div>

                        <div class="handler-info-box">
                            <h4><i class="ri-time-line"></i> Előfizetés meghosszabbítása</h4>
                            <p>Add meg a napok számát amivel meg szeretnéd hosszabbítani</p>
                        </div>
                        <form method="POST" action="/admin/handler-extend" style="margin-bottom: 25px;">
                            <input type="hidden" name="handler_id" id="extendHandlerId">
                            <div style="display: flex; gap: 10px; align-items: flex-end;">
                                <div style="flex: 1;">
                                    <label style="display: block; font-size: 12px; color: #888; margin-bottom: 6px;">Napok száma</label>
                                    <input type="number" name="days" min="1" max="365" value="30" required
                                           style="width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff;">
                                </div>
                                <button type="submit" class="btn" style="background: #00e6ff; color: #000;">
                                    <i class="ri-add-line"></i> Meghosszabbítás
                                </button>
                            </div>
                        </form>

                        <hr style="border: none; border-top: 1px solid rgba(255,255,255,0.1); margin: 25px 0;">

                        <div class="handler-info-box handler-info-box-success" id="activateBox">
                            <h4><i class="ri-checkbox-circle-line"></i> Aktiválás</h4>
                            <p>Trial státuszból aktív előfizetésre váltás (6 hónap)</p>
                        </div>
                        <form method="POST" action="/admin/handler-activate" id="activateForm">
                            <input type="hidden" name="handler_id" id="activateHandlerId">
                            <button type="submit" class="btn" id="activateBtn" style="background: rgba(76,175,80,0.3); color: #81c784; border: 1px solid rgba(76,175,80,0.4);">
                                <i class="ri-check-line"></i> Aktiválás (6 hónap)
                            </button>
                        </form>
                    </div>

                    <!-- Tab: Mobile Scanner -->
                    <div id="handler-tab-mobile" class="handler-tab-content">
                        <div class="handler-info-box">
                            <h4><i class="ri-phone-line"></i> Mobile Scanner Mód</h4>
                            <p>Állítsd be hogy a handler használhat-e mobile scannert (GPS nélkül)</p>
                        </div>
                        <form method="POST" action="/admin/handler-mobile">
                            <input type="hidden" name="handler_id" id="mobileHandlerId">

                            <div style="margin-bottom: 20px;">
                                <label class="handler-toggle-option">
                                    <input type="radio" name="mobile_mode" value="off">
                                    <div>
                                        <strong>Kikapcsolva (Fixed)</strong>
                                        <span style="display: block; font-size: 11px; color: #888;">GPS kötelező minden scanneléshez</span>
                                    </div>
                                </label>

                                <label class="handler-toggle-option">
                                    <input type="radio" name="mobile_mode" value="global">
                                    <div>
                                        <strong>Globális Mobile Mód</strong>
                                        <span style="display: block; font-size: 11px; color: #888;">Összes jelenlegi és jövőbeli készülékre érvényes</span>
                                    </div>
                                </label>

                                <label class="handler-toggle-option">
                                    <input type="radio" name="mobile_mode" value="per_device">
                                    <div>
                                        <strong>Készülékenként</strong>
                                        <span style="display: block; font-size: 11px; color: #888;">Csak kiválasztott készülékekre (a Készülékek tabon állítható)</span>
                                    </div>
                                </label>
                            </div>

                            <button type="submit" class="btn" style="background: rgba(156,39,176,0.3); color: #ce93d8; border: 1px solid rgba(156,39,176,0.4);">
                                <i class="ri-save-line"></i> Mentés
                            </button>
                        </form>
                    </div>

                    <!-- Tab: Filialen -->
                    <div id="handler-tab-filialen" class="handler-tab-content">
                        <div id="handlerFilialenInfo"></div>

                        <div class="handler-info-box">
                            <h4><i class="ri-building-line"></i> Fiók limit</h4>
                            <p>Állítsd be hány fiókot (filiále) hozhat létre a handler</p>
                        </div>
                        <form method="POST" action="/admin/handler-filialen">
                            <input type="hidden" name="handler_id" id="filialenHandlerId">
                            <div style="display: flex; gap: 10px; align-items: flex-end;">
                                <div style="flex: 1;">
                                    <label style="display: block; font-size: 12px; color: #888; margin-bottom: 6px;">Maximum fiókok száma</label>
                                    <input type="number" name="max_filialen" id="maxFilialenInput" min="1" max="100" value="1" required
                                           style="width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff;">
                                </div>
                                <button type="submit" class="btn" style="background: #00e6ff; color: #000;">
                                    <i class="ri-save-line"></i> Mentés
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Device Modal -->
        <div id="deleteModal" class="handler-modal-overlay">
            <div class="handler-modal" style="max-width: 450px;">
                <div class="handler-modal-header">
                    <h2 style="color: #f44336;"><i class="ri-alert-line"></i> Eszköz törlése</h2>
                    <button class="handler-modal-close" onclick="closeDeleteModal()"><i class="ri-close-line"></i></button>
                </div>
                <div class="handler-modal-body">
                    <div style="background: rgba(244,67,54,0.1); border: 1px solid rgba(244,67,54,0.3); border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                        <p style="margin: 0; color: #fff;"><strong id="deleteDeviceName"></strong></p>
                        <p style="margin: 5px 0 0 0; color: #00e6ff;" id="deleteDeviceModel"></p>
                        <p style="margin: 5px 0 0 0; color: #888; font-size: 12px;">Handler: <span id="deleteStoreName"></span></p>
                    </div>
                    <form method="POST" action="/admin/delete-device">
                        <input type="hidden" name="device_id" id="deleteDeviceId">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; color: #fff; margin-bottom: 8px;">Törlés oka:</label>
                            <textarea name="reason" required rows="2"
                                      style="width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 12px; color: #fff; font-size: 14px; resize: none;"
                                      placeholder="pl. Ügyfél kérése, elveszett eszköz..."></textarea>
                        </div>
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button type="button" onclick="closeDeleteModal()" class="btn" style="background: rgba(255,255,255,0.1); color: #fff;">Mégse</button>
                            <button type="submit" class="btn btn-reject"><i class="ri-delete-bin-line"></i> Törlés</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <style>
        .handler-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.85);
            z-index: 1000;
            align-items: flex-start;
            justify-content: center;
            padding: 30px 20px;
            overflow-y: auto;
        }
        .handler-modal-overlay.active { display: flex; }
        .handler-modal {
            background: #1a1a2e;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            width: 100%;
            max-width: 900px;
        }
        .handler-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .handler-modal-header h2 { font-size: 18px; color: #00e6ff; margin: 0; }
        .handler-modal-stats { display: flex; gap: 15px; margin-top: 8px; flex-wrap: wrap; font-size: 12px; color: #888; }
        .handler-modal-close {
            background: none;
            border: none;
            color: #888;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
        }
        .handler-modal-close:hover { color: #fff; }
        .handler-modal-body { padding: 24px; }
        .handler-tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
        .handler-tab-btn {
            padding: 10px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #aaa;
            cursor: pointer;
            font-size: 13px;
        }
        .handler-tab-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .handler-tab-btn.active { background: rgba(0,230,255,0.2); color: #00e6ff; border-color: rgba(0,230,255,0.3); }
        .handler-tab-content { display: none; }
        .handler-tab-content.active { display: block; }
        .handler-device-list { }
        .handler-device-item {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .handler-device-item:hover { background: rgba(255,255,255,0.05); }
        .handler-device-name { font-weight: 600; color: #00e6ff; }
        .handler-device-meta { font-size: 11px; color: #888; margin-top: 4px; }
        .handler-device-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
        .handler-device-badge {
            background: rgba(255,255,255,0.05);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            color: #aaa;
        }
        .handler-device-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .handler-info-box {
            background: rgba(0,230,255,0.1);
            border: 1px solid rgba(0,230,255,0.2);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .handler-info-box h4 { color: #fff; margin: 0 0 5px 0; font-size: 14px; }
        .handler-info-box p { color: #aaa; font-size: 12px; margin: 0; }
        .handler-info-box-success { background: rgba(76,175,80,0.1); border-color: rgba(76,175,80,0.2); }
        .handler-subscription-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .handler-sub-card {
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            padding: 15px;
        }
        .handler-sub-card .label { font-size: 11px; color: #888; margin-bottom: 5px; }
        .handler-sub-card .value { font-size: 18px; font-weight: 600; }
        .handler-sub-card .value.active { color: #81c784; }
        .handler-sub-card .value.trial { color: #ffb74d; }
        .handler-sub-card .value.expired { color: #ef5350; }
        .handler-toggle-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
        }
        .handler-toggle-option:hover { background: rgba(255,255,255,0.05); }
        .handler-toggle-option input { width: 18px; height: 18px; }
        .handler-toggle-option strong { color: #fff; font-size: 13px; }
        tr.clickable-row:hover { background: rgba(0,230,255,0.1); }
        </style>

        <script>
        let currentHandler = null;

        // Handler szűrés és keresés
        function filterHandlers() {
            const searchTerm = document.getElementById('handlerSearch').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const scannerFilter = document.getElementById('scannerFilter').value;
            const rows = document.querySelectorAll('.handler-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.dataset.name || '';
                const city = row.dataset.city || '';
                const email = row.dataset.email || '';
                const status = row.dataset.status || '';
                const scanner = row.dataset.scanner || '';

                // Keresés ellenőrzés
                const matchesSearch = !searchTerm ||
                    name.includes(searchTerm) ||
                    city.includes(searchTerm) ||
                    email.includes(searchTerm);

                // Státusz szűrés
                const matchesStatus = !statusFilter || status === statusFilter;

                // Scanner szűrés
                const matchesScanner = !scannerFilter || scanner === scannerFilter;

                if (matchesSearch && matchesStatus && matchesScanner) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('handlerCount').textContent = visibleCount + ' / ' + rows.length + ' handler';
        }

        // Oldal betöltéskor számoljuk meg
        document.addEventListener('DOMContentLoaded', filterHandlers);

        function openHandlerModal(handler) {
            currentHandler = handler;

            // Set title & stats
            document.getElementById('handlerModalTitle').innerHTML = '<i class="ri-store-2-line"></i> ' + escapeHtml(handler.name || handler.company_name);
            document.getElementById('handlerModalStats').innerHTML =
                '<span><i class="ri-map-pin-line"></i> ' + escapeHtml(handler.city || '-') + '</span>' +
                '<span><i class="ri-mail-line"></i> ' + escapeHtml(handler.email || '-') + '</span>' +
                '<span><strong>' + handler.device_count + '</strong> készülék</span>';

            // Set hidden IDs
            document.getElementById('extendHandlerId').value = handler.id;
            document.getElementById('activateHandlerId').value = handler.id;
            document.getElementById('mobileHandlerId').value = handler.id;
            document.getElementById('filialenHandlerId').value = handler.id;

            // Render tabs
            renderDevices(handler);
            renderSubscription(handler);
            setMobileMode(handler.scanner_type);
            renderFilialen(handler);

            // Show modal
            document.getElementById('handlerModal').classList.add('active');
            switchHandlerTab('devices');
        }

        function closeHandlerModal() {
            document.getElementById('handlerModal').classList.remove('active');
            currentHandler = null;
        }

        function switchHandlerTab(tabId) {
            document.querySelectorAll('.handler-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.handler-tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelector('[onclick="switchHandlerTab(\'' + tabId + '\')"]').classList.add('active');
            document.getElementById('handler-tab-' + tabId).classList.add('active');
        }

        function renderDevices(handler) {
            const container = document.getElementById('handlerDeviceList');
            if (!handler.devices || handler.devices.length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;"><i class="ri-smartphone-line" style="font-size: 32px; display: block; margin-bottom: 10px;"></i>Nincs regisztrált készülék</div>';
                return;
            }
            container.innerHTML = handler.devices.map(d => {
                const fp = d.fingerprint || {};
                return '<div class="handler-device-item">' +
                    '<div style="flex: 1; min-width: 200px;">' +
                        '<div class="handler-device-name">' + escapeHtml(d.name) + '</div>' +
                        '<div class="handler-device-meta">' +
                            '<strong style="color: #00e6ff;">' + escapeHtml(d.model) + '</strong> &bull; ' +
                            escapeHtml(d.os || '-') + ' &bull; IP: ' + escapeHtml(d.ip) + ' &bull; ' + formatDate(d.registered_at) +
                        '</div>' +
                        '<div class="handler-device-badges">' +
                            (fp.screen ? '<span class="handler-device-badge"><i class="ri-computer-line"></i> ' + fp.screen + '</span>' : '') +
                            (fp.memory ? '<span class="handler-device-badge"><i class="ri-database-line"></i> ' + fp.memory + '</span>' : '') +
                            (fp.cpuCores ? '<span class="handler-device-badge"><i class="ri-cpu-line"></i> ' + fp.cpuCores + '</span>' : '') +
                            (fp.touch ? '<span class="handler-device-badge"><i class="ri-hand-coin-line"></i> ' + fp.touch + '</span>' : '') +
                            (fp.timezone ? '<span class="handler-device-badge"><i class="ri-time-line"></i> ' + fp.timezone + '</span>' : '') +
                        '</div>' +
                    '</div>' +
                    '<div class="handler-device-actions">' +
                        (d.mobile_scanner
                            ? '<span class="badge badge-mobile">Mobile</span>'
                            : '<button class="btn btn-sm" style="background: rgba(156,39,176,0.2); color: #ce93d8; border: 1px solid rgba(156,39,176,0.3);" onclick="enableDeviceMobile(' + d.id + ')"><i class="ri-smartphone-line"></i> Mobile BE</button>'
                        ) +
                        '<button class="btn btn-sm btn-reject" onclick="openDeleteModal(' + d.id + ', \'' + escapeHtml(d.name).replace(/'/g, "\\'") + '\', \'' + escapeHtml(d.model).replace(/'/g, "\\'") + '\', \'' + escapeHtml(currentHandler.name).replace(/'/g, "\\'") + '\')"><i class="ri-delete-bin-line"></i></button>' +
                    '</div>' +
                '</div>';
            }).join('');
        }

        function renderSubscription(handler) {
            const now = new Date();
            const trialEnd = handler.trial_ends_at ? new Date(handler.trial_ends_at) : null;
            const subEnd = handler.subscription_expires_at ? new Date(handler.subscription_expires_at) : null;
            const trialDays = trialEnd ? Math.max(0, Math.ceil((trialEnd - now) / 86400000)) : 0;
            const subDays = subEnd ? Math.max(0, Math.ceil((subEnd - now) / 86400000)) : 0;

            let statusClass = 'trial', statusText = 'Trial', daysLeft = trialDays;
            if (handler.subscription_status === 'active') {
                statusClass = subDays > 0 ? 'active' : 'expired';
                statusText = subDays > 0 ? 'Aktív' : 'Lejárt';
                daysLeft = subDays;
            } else {
                statusClass = trialDays > 0 ? 'trial' : 'expired';
                statusText = trialDays > 0 ? 'Trial' : 'Lejárt';
            }

            document.getElementById('handlerSubscriptionInfo').innerHTML =
                '<div class="handler-sub-card"><div class="label">Státusz</div><div class="value ' + statusClass + '">' + statusText + '</div></div>' +
                '<div class="handler-sub-card"><div class="label">Hátralévő</div><div class="value ' + statusClass + '">' + daysLeft + ' nap</div></div>' +
                '<div class="handler-sub-card"><div class="label">Trial vége</div><div class="value">' + (trialEnd ? formatDate(handler.trial_ends_at) : '-') + '</div></div>' +
                '<div class="handler-sub-card"><div class="label">Előfizetés vége</div><div class="value">' + (subEnd ? formatDate(handler.subscription_expires_at) : '-') + '</div></div>';

            // Hide activate button if already active
            const isActive = handler.subscription_status === 'active' && subDays > 0;
            document.getElementById('activateBox').style.display = isActive ? 'none' : 'block';
            document.getElementById('activateBtn').style.display = isActive ? 'none' : 'inline-flex';
        }

        function setMobileMode(scannerType) {
            document.querySelectorAll('input[name="mobile_mode"]').forEach(r => r.checked = false);
            if (scannerType === 'mobile') {
                document.querySelector('input[name="mobile_mode"][value="global"]').checked = true;
            } else {
                document.querySelector('input[name="mobile_mode"][value="off"]').checked = true;
            }
        }

        function renderFilialen(handler) {
            const current = parseInt(handler.filiale_count) + 1;
            const max = parseInt(handler.max_filialen) || 1;
            document.getElementById('handlerFilialenInfo').innerHTML =
                '<div class="handler-subscription-info" style="margin-bottom: 20px;">' +
                    '<div class="handler-sub-card"><div class="label">Jelenlegi</div><div class="value" style="color: #00e6ff;">' + current + '</div></div>' +
                    '<div class="handler-sub-card"><div class="label">Maximum</div><div class="value" style="color: #81c784;">' + max + '</div></div>' +
                    '<div class="handler-sub-card"><div class="label">Szabad</div><div class="value" style="color: ' + (max - current > 0 ? '#81c784' : '#ef5350') + ';">' + Math.max(0, max - current) + '</div></div>' +
                '</div>';
            document.getElementById('maxFilialenInput').value = max;
        }

        function enableDeviceMobile(deviceId) {
            if (!confirm('Biztosan engedélyezed a Mobile Scanner módot erre a készülékre?')) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/admin/device-mobile-enable';
            form.innerHTML = '<input type="hidden" name="device_id" value="' + deviceId + '"><input type="hidden" name="handler_id" value="' + currentHandler.id + '">';
            document.body.appendChild(form);
            form.submit();
        }

        function openDeleteModal(deviceId, deviceName, deviceModel, storeName) {
            document.getElementById('deleteDeviceId').value = deviceId;
            document.getElementById('deleteDeviceName').textContent = deviceName;
            document.getElementById('deleteDeviceModel').textContent = deviceModel;
            document.getElementById('deleteStoreName').textContent = storeName;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const d = new Date(dateStr);
            return d.toLocaleDateString('hu-HU', { year: 'numeric', month: '2-digit', day: '2-digit' });
        }

        // ESC & background click
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeHandlerModal();
                closeDeleteModal();
            }
        });
        document.getElementById('handlerModal').addEventListener('click', function(e) {
            if (e.target === this) closeHandlerModal();
        });
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });
        </script>
        <?php
        self::get_admin_footer();
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
