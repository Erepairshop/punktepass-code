<?php
if ( ! defined('ABSPATH') ) exit;

class PPV_Dashboard {

  public static function hooks(){
    add_shortcode('ppv_vendor_dashboard',[__CLASS__,'shortcode']);
    add_action('wp_enqueue_scripts',[__CLASS__,'enqueue_assets']);
  }

  public static function enqueue_assets(){
    wp_enqueue_style('ppv-dashboard-css', plugins_url('../assets/css/ppv-dashboard.css', __FILE__), [], time());
  }

  public static function shortcode($atts){
    if( !is_user_logged_in() ) return '<p>Bitte logge dich ein.</p>';

    $user_id  = get_current_user_id();
    $store_id = get_user_meta($user_id,'ppv_store_id',true);
    if(!$store_id) return '<p>Kein Store verbunden.</p>';

    global $wpdb;
    $p = $wpdb->prefix;

    // --- Stat számok ---
    $total_users   = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$p}pp_points WHERE store_id=$store_id");
    $total_points  = $wpdb->get_var("SELECT SUM(points) FROM {$p}pp_points WHERE store_id=$store_id");
    $total_rewards = $wpdb->get_var("SELECT COUNT(*) FROM {$p}pp_rewards WHERE store_id=$store_id");
    $avg_rating    = $wpdb->get_var("SELECT AVG(rating) FROM {$p}pp_reviews WHERE store_id=$store_id");

    $total_users   = $total_users ?: 0;
    $total_points  = $total_points ?: 0;
    $total_rewards = $total_rewards ?: 0;
    $avg_rating    = $avg_rating ? round($avg_rating,1) : 0;

    // --- Utolsó 5 review ---
    $reviews = $wpdb->get_results("
      SELECT r.*, u.display_name 
      FROM {$p}pp_reviews r
      LEFT JOIN {$p}users u ON r.user_id=u.ID
      WHERE r.store_id=$store_id
      ORDER BY r.created_at DESC
      LIMIT 5
    ");

    ob_start();
    ?>
    <div class="ppv-dashboard">
      <h2>📊 Händler Dashboard</h2>
      <div class="ppv-stats">
        <div class="ppv-card">
          <div class="ppv-icon">👥</div>
          <h3><?php echo $total_users; ?></h3>
          <p>Kunden</p>
        </div>
        <div class="ppv-card">
          <div class="ppv-icon">🏆</div>
          <h3><?php echo $total_points; ?></h3>
          <p>Gesammelte Punkte</p>
        </div>
        <div class="ppv-card">
          <div class="ppv-icon">🎁</div>
          <h3><?php echo $total_rewards; ?></h3>
          <p>Eingelöste Belohnungen</p>
        </div>
        <div class="ppv-card">
          <div class="ppv-icon">⭐</div>
          <h3><?php echo $avg_rating; ?></h3>
          <p>Durchschnittliche Bewertung</p>
        </div>
      </div>

      <div class="ppv-extra">
        <h3>Letzte Bewertungen</h3>
        <?php if($reviews): ?>
          <ul class="ppv-reviews-list">
            <?php foreach($reviews as $r): ?>
              <li>
                <strong><?php echo esc_html($r->display_name); ?></strong>
                <span class="ppv-rating"><?php echo str_repeat("⭐",$r->rating).str_repeat("☆",5-$r->rating); ?></span>
                <p><?php echo esc_html($r->comment); ?></p>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p>Noch keine Bewertungen</p>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
  }

}
