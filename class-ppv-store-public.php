<?php
if ( ! defined('ABSPATH') ) exit;

class PPV_Store_Public {
  public static function hooks(){
    add_action('init', [__CLASS__, 'rewrite']);
    add_filter('query_vars', [__CLASS__, 'vars']);
    add_action('template_redirect', [__CLASS__, 'render']);
  }

  public static function rewrite(){
    add_rewrite_rule('^store/([^/]+)/?', 'index.php?ppv_store_slug=$matches[1]', 'top');
  }

  public static function vars($vars){
    $vars[] = 'ppv_store_slug';
    return $vars;
  }

  public static function render(){
    $slug = get_query_var('ppv_store_slug');
    if ( ! $slug ) return;
    global $wpdb; $p=$wpdb->prefix;
    $store = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$p}pp_stores WHERE slug=%s LIMIT 1", $slug) );
    if ( ! $store ) { wp_die('Store nicht gefunden'); }

    // Adatok előkészítése
    $logo  = $store->logo_url ? '<img src="'.esc_url($store->logo_url).'" style="max-height:120px">' : '';
    $cover = $store->cover_url ? '<img src="'.esc_url($store->cover_url).'" style="width:100%;max-height:200px;object-fit:cover">' : '';
    $addr  = esc_html($store->address.' '.$store->city);
    $map   = $store->address ? '<a target="_blank" href="https://maps.google.com/?q='.urlencode($store->address.' '.$store->city).'">Auf Google Maps ansehen</a>' : '';
    $desc  = wpautop(esc_html($store->description));

    // Rewards
    $rewards = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$p}pp_rewards WHERE store_id=%d AND active=1 ORDER BY id ASC", $store->id) );
    $rew_html = '';
    if ($rewards){
      $rew_html .= '<ul>';
      foreach ($rewards as $r){
        $rew_html .= '<li><strong>'.intval($r->points).' Punkte</strong> – '.esc_html($r->title).'</li>';
      }
      $rew_html .= '</ul>';
    } else {
      $rew_html = '<p>Noch keine Prämien.</p>';
    }

    // Output
    wp_head();
    echo '<div class="ppv-store-public">';
    echo $cover;
    echo '<h1>'.esc_html($store->name).'</h1>';
    echo $logo;
    echo '<p>'.$addr.'<br>'.$map.'</p>';
    echo '<h3>'.esc_html__('Öffnungszeiten','punktepass').'</h3><p>'.nl2br(esc_html($store->opening_hours)).'</p>';
    echo '<h3>'.esc_html__('Über uns','punktepass').'</h3>'.$desc;
    echo '<h3>'.esc_html__('Aktuelle Prämien','punktepass').'</h3>'.$rew_html;
    echo '</div>';
    wp_footer();
    exit;
    
  }
}

add_filter('template_include', function($template){
    if ( get_query_var('pp_store') ) {
        return PPV_DIR.'templates/store-public.php';
    }
    return $template;
});

PPV_Store_Public::hooks();
