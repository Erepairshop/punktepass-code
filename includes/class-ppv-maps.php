<?php
if ( ! defined('ABSPATH') ) exit;

class PPV_Maps {

  public static function hooks(){
    add_action('wp_enqueue_scripts', [__CLASS__,'enqueue']);
    add_shortcode('ppv_map', [__CLASS__,'render_map']);
  }

  public static function enqueue(){
    $api_key = get_option('ppv_google_api_key');
    if ( $api_key ){
      wp_enqueue_script(
        'google-maps',
        'https://maps.googleapis.com/maps/api/js?key='.$api_key.'&libraries=places',
        [],
        null,
        true
      );
    }
    wp_enqueue_script('ppv-maps-js', PPV_URL.'assets/js/ppv-maps.js', ['google-maps'], PPV_VERSION, true);
  }

  public static function render_map( $atts ){
    $atts = shortcode_atts(['store_id'=>0], $atts, 'ppv_map');
    global $wpdb;
    $p = $wpdb->prefix;
    $store = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}pp_stores WHERE id=%d", $atts['store_id']));
    if ( ! $store ) return '<p>Kein Standort gefunden.</p>';

    $lat = esc_attr($store->lat);
    $lng = esc_attr($store->lng);
    $name = esc_html($store->name);

    ob_start(); ?>
    <div id="ppv-map" style="width:100%;height:400px;" 
         data-lat="<?php echo $lat; ?>" 
         data-lng="<?php echo $lng; ?>" 
         data-title="<?php echo $name; ?>"></div>
    <?php
    return ob_get_clean();
  }
}
PPV_Maps::hooks();
