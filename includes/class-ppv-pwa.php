<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – PWA & App Mode Handler
 * Version: 1.1
 */

class PPV_PWA {

    public static function hooks() {
        add_action('wp_head', [__CLASS__, 'meta_tags']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_footer', [__CLASS__, 'inject_scripts'], 99);

        // Serve manifest.json and sw.js from plugin folder
        add_action('init', [__CLASS__, 'register_pwa_routes']);
        add_action('template_redirect', [__CLASS__, 'serve_pwa_files']);
    }

    /** 🔹 Register rewrite rules for PWA files */
    public static function register_pwa_routes() {
        add_rewrite_rule('^manifest\.json$', 'index.php?ppv_pwa_file=manifest', 'top');
        add_rewrite_rule('^sw\.js$', 'index.php?ppv_pwa_file=sw', 'top');
        add_filter('query_vars', function($vars) {
            $vars[] = 'ppv_pwa_file';
            return $vars;
        });
    }

    /** 🔹 Serve PWA files from plugin folder */
    public static function serve_pwa_files() {
        $file = get_query_var('ppv_pwa_file');
        if (!$file) return;

        if ($file === 'manifest') {
            $path = PPV_PLUGIN_DIR . 'manifest.json';
            if (file_exists($path)) {
                header('Content-Type: application/manifest+json');
                header('Cache-Control: public, max-age=86400');
                readfile($path);
                exit;
            }
        }

        if ($file === 'sw') {
            $path = PPV_PLUGIN_DIR . 'sw.js';
            if (file_exists($path)) {
                header('Content-Type: application/javascript');
                header('Cache-Control: no-cache');
                header('Service-Worker-Allowed: /');
                readfile($path);
                exit;
            }
        }
    }

    /** 🔹 Meta tag-ek az iOS és Android app módhoz */
    public static function meta_tags() {
        ?>
        <!-- PunktePass PWA Meta -->
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, minimal-ui">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="PunktePass">
        <link rel="manifest" href="<?php echo esc_url(home_url('/manifest.json')); ?>">
        <link rel="apple-touch-icon" href="<?php echo esc_url(PPV_PLUGIN_URL . 'assets/img/punktepass-icon-512.png'); ?>">
        <?php
    }

    /** 🔹 PWA CSS + JS betöltés */
    public static function enqueue_assets() {
        wp_enqueue_script('punktepass-pwa', PPV_PLUGIN_URL . 'assets/js/ppv-pwa.js', [], time(), true);
    }

    /** 🔹 Service worker regisztráció és iOS tipp */
    public static function inject_scripts() {
        ?>
        <script>
        // 📱 Service Worker
        if ('serviceWorker' in navigator) {
          navigator.serviceWorker.register('<?php echo esc_url(home_url('/sw.js')); ?>')
            .then(reg => console.log('✅ PWA aktiv:', reg.scope))
            .catch(err => console.error('❌ SW Fehler:', err));
        }

        // 🍏 iOS Telepítési tipp
        document.addEventListener("DOMContentLoaded", () => {
          const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
          const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

          if (isIOS && !isStandalone && !localStorage.getItem('ppv_ios_tip')) {
            const popup = document.createElement('div');
            popup.innerHTML = `
              <div style="position:fixed;bottom:0;left:0;width:100%;background:#00bcd4;
                          color:#fff;padding:20px 15px;text-align:center;font-family:sans-serif;
                          font-size:18px;z-index:99999;box-shadow:0 -4px 15px rgba(0,0,0,0.4);">
                <div style="max-width:400px;margin:auto;">
                  <strong>📱 PunktePass als App installieren</strong><br>
                  <small>Bitte öffne die Seite in <b>Safari</b>.</small><br>
                  <small>Dann tippe unten auf <b>Teilen → Zum Home-Bildschirm hinzufügen</b>.</small><br>
                  <button id="ppv_ios_close"
                    style="margin-top:12px;padding:8px 16px;border:none;background:#fff;color:#00bcd4;
                           border-radius:6px;font-weight:600;cursor:pointer;">OK, verstanden</button>
                </div>
              </div>`;
            document.body.appendChild(popup);
            document.getElementById('ppv_ios_close').onclick = () => {
              popup.remove();
              localStorage.setItem('ppv_ios_tip', '1');
            };
          }
        });
        </script>
        <?php
    }
}
