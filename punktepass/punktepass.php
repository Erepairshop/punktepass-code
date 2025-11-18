<?php
/**
 * Plugin Name: PunktePass
 * Description: Digitales Treueprogramm – Händler-Dashboard (6 Module) + integrierter Updater. Stille Aktivierung.
 * Version: 1.4.0
 * Author: PunktePass
 * Text Domain: punktepass
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'PPV_VERSION', '1.4.0' );
define( 'PPV_DIR', plugin_dir_path( __FILE__ ) );
define( 'PPV_URL', plugin_dir_url( __FILE__ ) );

// -- Silent activation: never print output while creating tables --
register_activation_hook( __FILE__, function () {
    $lvl = ob_get_level(); ob_start();
    try {
        if ( ! function_exists('dbDelta') ) { require_once ABSPATH . 'wp-admin/includes/upgrade.php'; }
        if ( function_exists('dbDelta') ) {
            global $wpdb; 
            $c = $wpdb->get_charset_collate(); 
            $p = $wpdb->prefix;

            @dbDelta("CREATE TABLE {$p}pp_stores (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(190) NOT NULL,
                slug VARCHAR(190) UNIQUE,
                logo_url TEXT NULL,
                cover_url TEXT NULL,
                address TEXT NULL,
                city VARCHAR(120) NULL,
                opening_hours TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                KEY user_id (user_id)
            ) $c;");

            @dbDelta("CREATE TABLE {$p}pp_store_locations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                store_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(190) NOT NULL,
                address TEXT NULL,
                qr_secret VARCHAR(64) NOT NULL,
                active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                KEY store_id (store_id)
            ) $c;");

            @dbDelta("CREATE TABLE {$p}pp_rewards (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                store_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(190) NOT NULL,
                description TEXT NULL,
                points_required INT NOT NULL DEFAULT 1,
                active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                KEY store_id (store_id)
            ) $c;");

            @dbDelta("CREATE TABLE {$p}pp_redemptions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                store_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                reward_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(32) NOT NULL,
                redeemed_by BIGINT UNSIGNED NULL,
                redeemed_at DATETIME NULL,
                expires_at DATETIME NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'issued',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                KEY store_id (store_id),
                KEY user_id (user_id),
                KEY code (code)
            ) $c;");

            @dbDelta("CREATE TABLE {$p}pp_scan_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                store_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                location_id BIGINT UNSIGNED NULL,
                points INT NOT NULL DEFAULT 1,
                scanned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                user_agent TEXT NULL,
                hash VARCHAR(64) NOT NULL,
                PRIMARY KEY(id),
                KEY store_user (store_id, user_id),
                KEY scanned_at (scanned_at)
            ) $c;");
        }
    } catch (Throwable $e) {
        if ( defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) { error_log('[PunktePass activate] '.$e->getMessage()); }
    } finally { while ( ob_get_level() > $lvl ) { ob_end_clean(); } }
});

// includes
require_once PPV_DIR.'includes/class-ppv-core.php';
require_once PPV_DIR.'includes/class-ppv-rest.php';
require_once PPV_DIR.'includes/class-ppv-pages.php';
require_once PPV_DIR.'includes/class-ppv-settings.php';
require_once PPV_DIR.'includes/class-ppv-public.php';
require_once PPV_DIR.'includes/class-ppv-updater.php';
require_once PPV_DIR.'includes/pp-migrations.php';
require_once PPV_DIR.'includes/pp-security.php';
require_once PPV_DIR.'includes/pp-anti-cache.php';
require_once PPV_DIR.'includes/pp-profile-lite.php';
require_once PPV_DIR.'includes/pp-discover.php';
require_once PPV_DIR.'includes/pp-public-store.php';

// load modules
add_action('plugins_loaded', function(){
    ( new PPV_Public() )->hooks();
    ( new PPV_REST() )->hooks();
    ( new PPV_Pages() )->hooks();
    PPV_Settings::hooks();
    PPV_Updater::hooks();
});

// Cache-tiltás minden PunktePass oldalnál
add_action('send_headers', function() {
    nocache_headers();
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
});

register_activation_hook(PPV_DIR.'punktepass.php', function(){ flush_rewrite_rules(); });
/* ============================================================
   🔹 PunktePass.de logó az adminbarban – PHP szintű csere
   ============================================================ */
add_action('admin_bar_menu', function($wp_admin_bar) {
    // Eltávolítjuk az eredeti "site name" elemet
    $wp_admin_bar->remove_node('site-name');
    $wp_admin_bar->remove_node('blog'); // egyes verziókban ez a neve

    // Új elem létrehozása logóval
    $logo_url = PPV_URL . 'assets/img/punktepass-poster-logo.png';
    $home_url = home_url('/');

    $wp_admin_bar->add_node([
        'id'    => 'punktepass-logo',
        'title' => '<img src="' . esc_url($logo_url) . '" alt="PunktePass" style="height:20px; margin-top:3px; filter:drop-shadow(0 0 5px rgba(0,255,255,0.8)); vertical-align:middle;">',
        'href'  => esc_url($home_url),
        'meta'  => [
            'title' => 'Zur Startseite',
            'class' => 'punktepass-admin-logo'
        ]
    ]);
}, 11);

/* 💅 Kis dizájn finomítás */
add_action('admin_head', function() {
    ?>
    <style>
    #wpadminbar #wp-admin-bar-punktepass-logo .ab-item {
        padding: 0 10px !important;
        display: flex !important;
        align-items: center !important;
    }
    #wpadminbar #wp-admin-bar-punktepass-logo:hover img {
        transform: scale(1.1);
        filter: drop-shadow(0 0 10px #00ffff);
        transition: all 0.3s ease;
    }
    </style>
    <?php
});


