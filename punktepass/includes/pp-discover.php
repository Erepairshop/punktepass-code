<?php
if ( ! defined('ABSPATH') ) exit;

class PPV_Discover {
  public static function hooks(){
    add_action('init',[__CLASS__,'rewrite']);
    add_action('template_redirect',[__CLASS__,'render']);
  }
  public static function rewrite(){
    add_rewrite_rule('^discover/?$', 'index.php?ppv_discover=1', 'top');
    add_rewrite_tag('%ppv_discover%', '1');
  }
  public static function render(){
    if( ! get_query_var('ppv_discover') ) return;
    global $wpdb; $p=$wpdb->prefix;
    $city = isset($_GET['city']) ? sanitize_text_field($_GET['city']) : '';
    $where = $city ? $wpdb->prepare("WHERE city=%s", $city) : "";
    $rows = $wpdb->get_results("SELECT name,slug,city,logo_url FROM {$p}pp_stores $where ORDER BY id DESC LIMIT 200");
    status_header(200); nocache_headers();
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Discover – PunktePass</title>';
    echo '<link rel="stylesheet" href="'.esc_url( PPV_URL.'assets/css/discover.css?ver='.PPV_VERSION ).'">';
    echo '<div class="ppv-container"><h1>Felfedezés</h1>';
    echo '<form class="ppv-filters" method="get"><input name="city" placeholder="Város" value="'.esc_attr($city).'"><button>Szűrés</button></form>';
    echo '<div class="ppv-grid">';
    if($rows){
      foreach($rows as $s){
        echo '<a class="ppv-card" href="'.esc_url(home_url('/store/'.$s->slug)).'">';
        if($s->logo_url){ echo '<img src="'.esc_url($s->logo_url).'" alt="">'; }
        echo '<div class="t">'.esc_html($s->name).'</div>';
        if($s->city){ echo '<div class="c">'.esc_html($s->city).'</div>'; }
        echo '</a>';
      }
    } else {
      echo '<p>Nincs találat.</p>';
    }
    echo '</div></div>'; exit;
  }
}
PPV_Discover::hooks();
