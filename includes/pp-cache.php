<?php
if ( ! defined('ABSPATH') ) exit;
/**
 * Cache clear utility with vendor permission support.
 */
class PPV_Cache {
  public static function hooks(){
    add_action('admin_post_ppv_cache_clear', [__CLASS__, 'handle']);
    add_action('wp_ajax_ppv_cache_clear', [__CLASS__, 'ajax']);
    add_action('init', [__CLASS__, 'ensure_cap']);
  }
  public static function ensure_cap(){
    // Give vendors permission to clear plugin cache
    $role = get_role('pp_vendor');
    if ($role && ! $role->has_cap('ppv_cache_clear')){
      $role->add_cap('ppv_cache_clear', true);
    }
  }
  public static function can_use(){
    return ( is_user_logged_in() && ( current_user_can('manage_options') || current_user_can('ppv_cache_clear') || current_user_can('pp_vendor') ) );
  }
  protected static function flush_all(){
    if ( function_exists('wp_cache_flush') ) { @wp_cache_flush(); }
    if ( function_exists('litespeed_purge_all') ) { @litespeed_purge_all(); }
    if ( function_exists('w3tc_flush_all') ) { @w3tc_flush_all(); }
    if ( function_exists('rocket_clean_domain') ) { @rocket_clean_domain(); }
    if ( function_exists('wpfc_clear_all_cache') ) { @wpfc_clear_all_cache(true); }
    do_action('ppv_cache_flush');
    do_action('after_rocket_clean_domain');
    nocache_headers();
  }
  public static function handle(){
    if( ! self::can_use() ) wp_die('No permission');
    check_admin_referer('ppv_cache_clear');
    self::flush_all();
    wp_safe_redirect( add_query_arg('ppmsg','cache_cleared', wp_get_referer() ?: home_url() ) ); exit;
  }
  public static function ajax(){
    if( ! self::can_use() ) wp_send_json_error('no_permission',403);
    check_ajax_referer('ppv_cache_clear','nonce');
    self::flush_all();
    wp_send_json_success(['status'=>'ok']);
  }
  public static function button_html(){
    if( ! self::can_use() ) return;
    $nonce = wp_create_nonce('ppv_cache_clear');
    echo '<form method="post" action="'.esc_url( admin_url('admin-post.php') ).'" style="display:inline;margin-left:8px">';
    echo '<input type="hidden" name="action" value="ppv_cache_clear">';
    echo '<input type="hidden" name="_wpnonce" value="'.esc_attr($nonce).'">';
    echo '<button class="button">'.esc_html__('Cache leeren','punktepass').'</button>';
    echo '</form>';
  }
}
PPV_Cache::hooks();
