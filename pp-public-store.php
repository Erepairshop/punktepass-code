<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Public Store Page – /store/{slug}
 * Shows: logo, description, active rewards. QR is NOT public.
 */
class PPV_Public_Store {
  public static function hooks(){
    add_action('init', [__CLASS__, 'rewrite']);
    add_filter('query_vars', function($vars){ $vars[]='pp_store_slug'; return $vars; });
    add_action('template_redirect', [__CLASS__, 'render']);
  }
  public static function rewrite(){
    add_rewrite_rule('^store/([^/]+)/?$', 'index.php?pp_store_slug=$matches[1]', 'top');
  }
  public static function render(){
    $slug = get_query_var('pp_store_slug');
    if ( ! $slug ) return;
    global $wpdb; $p=$wpdb->prefix;
    $store = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$p}pp_stores WHERE slug=%s LIMIT 1", $slug) );
    status_header(200);
    nocache_headers();
    echo '<!DOCTYPE html><meta charset="utf-8"><title>'.esc_html(get_bloginfo('name')).' – Store</title>';
    echo '<link rel="stylesheet" href="'.esc_url( PPV_URL.'assets/css/public-store.css?ver='.PPV_VERSION ).'">';
    echo '<div class="pp-store-public">';
    if($store){
      if($store->cover_url){ echo '<div class="pp-cover" style="background-image:url('.esc_url($store->cover_url).')"></div>'; }
      echo '<div class="pp-head">';
      if($store->logo_url){ echo '<img class="pp-logo" src="'.esc_url($store->logo_url).'" alt="logo">'; }
      echo '<div class="pp-meta"><h1>'.esc_html($store->name).'</h1>';
      if($store->address){ echo '<div class="pp-addr">'.esc_html($store->address).'</div>'; }
      echo '</div></div>';
      // rewards
      $rewards = $wpdb->get_results( $wpdb->prepare("SELECT id,title,points_required,category,featured,active FROM {$p}pp_rewards WHERE store_id=%d AND active=1 ORDER BY featured DESC, id DESC", $store->id) );
      if($rewards){
        echo '<h2>'.esc_html__('Aktuelle Prämien','punktepass').'</h2><div class="pp-grid">';
        foreach($rewards as $r){
          echo '<div class="pp-card">';
          echo '<div class="pp-pts">'.intval($r->points_required).' '.esc_html__('Punkte','punktepass').'</div>';
          echo '<div class="pp-title">'.esc_html($r->title).'</div>';
          if($r->category){ echo '<div class="pp-cat">'.esc_html($r->category).'</div>'; }
          if($r->featured){ echo '<div class="pp-feat">★ '.esc_html__('Featured','punktepass').'</div>'; }
          echo '</div>';
        }
        echo '</div>';
      } else {
        echo '<p>'.esc_html__('Derzeit keine Prämien.','punktepass').'</p>';
      }
      echo '<p class="pp-note">'.esc_html__('Der tägliche QR ist nur für angemeldete Nutzer sichtbar.','punktepass').'</p>';
    } else {
      echo '<p>'.esc_html__('Store nicht gefunden.','punktepass').'</p>';
    }
    echo '</div>';
    exit;
  }
}
PPV_Public_Store::hooks();
