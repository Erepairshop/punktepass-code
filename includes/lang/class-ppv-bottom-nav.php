<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Bottom Navigation Bar
 * Version: 1.0 (Auto Theme + Multi-Lang)
 * Author: Erik Borota / PunktePass
 */

class PPV_Bottom_Nav {

  public static function hooks() {
    add_shortcode('ppv_bottom_nav', [__CLASS__, 'render']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
  }

  /** 🔹 Assets */
  public static function enqueue_assets() {
    // RemixIcons loaded globally in punktepass.php
    wp_enqueue_style('ppv-bottom-nav', PPV_PLUGIN_URL . 'assets/css/ppv-bottom-nav.css', [], time());
    wp_enqueue_script('ppv-bottom-nav', PPV_PLUGIN_URL . 'assets/js/ppv-bottom-nav.js', ['jquery'], time(), true);
  }

  /** 🔹 Render Nav */
  public static function render() {
    $lang = $GLOBALS['ppv_lang_code'] ?? 'de';
    $labels = [
      'de' => ['dashboard'=>'Startseite','points'=>'Meine Punkte','rewards'=>'Belohnungen','settings'=>'Einstellungen'],
      'hu' => ['dashboard'=>'Kezdőlap','points'=>'Pontjaim','rewards'=>'Jutalmak','settings'=>'Beállítások'],
      'ro' => ['dashboard'=>'Acasă','points'=>'Punctele Mele','rewards'=>'Recompense','settings'=>'Setări'],
    ];
    $L = $labels[$lang] ?? $labels['de'];

    ob_start(); ?>
    <nav class="ppv-bottom-nav">
      <a href="/user_dashboard" class="nav-item" data-key="dashboard">
        <i class="ri-home-5-line"></i><span><?= esc_html($L['dashboard']); ?></span>
      </a>
      <a href="/meine-punkte" class="nav-item" data-key="points">
        <i class="ri-star-line"></i><span><?= esc_html($L['points']); ?></span>
      </a>
      <a href="/belohnungen" class="nav-item" data-key="rewards">
        <i class="ri-gift-line"></i><span><?= esc_html($L['rewards']); ?></span>
      </a>
      <a href="/einstellungen" class="nav-item" data-key="settings">
        <i class="ri-settings-4-line"></i><span><?= esc_html($L['settings']); ?></span>
      </a>
    </nav>
    <?php
    return ob_get_clean();
  }
}

PPV_Bottom_Nav::hooks();
