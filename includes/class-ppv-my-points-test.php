<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Meine Punkte TEST (Standalone)
 * Cél: iPhone PWA render debug, Elementor és cache nélkül
 */

class PPV_My_Points_Test {

    public static function hooks() {
        add_shortcode('ppv_my_points_test', [__CLASS__, 'render_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /** 🔹 CSS betöltése */
    public static function enqueue_assets() {
        wp_enqueue_style(
            'ppv-my-points-test',
            PPV_PLUGIN_URL . 'assets/css/ppv-my-points-test.css',
            [],
            time()
        );
    }

    /** 🔹 Render */
    public static function render_page() {
        if (!is_user_logged_in()) {
            return '<div class="ppv-notice">Bitte anmelden, um Punkte zu sehen.</div>';
        }

        $user_id = get_current_user_id();
        $data = ['total' => 123, 'avg' => 4.5]; // Tesztadatok

        ob_start(); ?>
        <div id="ppv-my-points-test" class="ppv-my-points">
          <div class="ppv-dashboard-netto">
            <div class="ppv-dashboard-inner">

              <div class="ppv-back-btn">
                <a href="https://punktepass.de/user-dashboard">← Zurück zum Dashboard</a>
              </div>

              <h2>Meine Punkte (Testseite)</h2>

              <div class="ppv-stats-grid">
                <div class="ppv-stat-card"><div class="label">Gesamtpunkte</div><div class="value"><?php echo $data['total']; ?></div></div>
                <div class="ppv-stat-card"><div class="label">Ø Punkte</div><div class="value"><?php echo $data['avg']; ?></div></div>
              </div>

              <p style="text-align:center;color:#00ffff;margin-top:20px;">
                ✅ Diese Seite wurde als Test geladen (Standalone-Version)
              </p>

            </div>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

PPV_My_Points_Test::hooks();