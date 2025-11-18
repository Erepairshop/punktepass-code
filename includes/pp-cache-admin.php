<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Admin menu + Admin Bar integration for Cache clear.
 * - Submenu under PunktePass (if parent 'punktepass' exists), else under Tools.
 * - Adds top admin bar "Cache leeren" button for authorized users.
 */
class PPV_Cache_Admin {
  public static function hooks(){
    add_action('admin_menu', [__CLASS__, 'menu']);
    add_action('admin_bar_menu', [__CLASS__, 'admin_bar'], 80);
  }

  protected static function can_use(){
    if ( ! is_user_logged_in() ) return false;
    // Allow admins or vendors with ppv_cache_clear cap
    return ( current_user_can('manage_options') || current_user_can('ppv_cache_clear') || current_user_can('pp_vendor') );
  }

  public static function menu(){
    // Try attach under PunktePass if exists
    $parent = 'punktepass';
    $added = false;
    if ( self::can_use() ){
      $cb = [__CLASS__, 'render_page'];
      // Try PunktePass parent
      if ( function_exists('add_submenu_page') ){
        $hook = add_submenu_page($parent, __('Cache leeren','punktepass'), __('Cache leeren','punktepass'), 'read', 'punktepass-cache', $cb);
        if ( $hook ) $added = true;
      }
      // Fallback: Tools menu
      if ( ! $added && function_exists('add_management_page') ){
        add_management_page(__('Cache leeren','punktepass'), __('Cache leeren','punktepass'), 'read', 'punktepass-cache', $cb);
      }
    }
  }

  public static function render_page(){
    if ( ! self::can_use() ){ wp_die('No permission'); }
    echo '<div class="wrap"><h1>'.esc_html__('Cache leeren','punktepass').'</h1>';
    echo '<p>'.esc_html__('Hier kannst du die Caches leeren (Seiten- und Objekt-Cache).','punktepass').'</p>';
    if ( isset($_GET['ppmsg']) && $_GET['ppmsg']==='cache_cleared' ){
      echo '<div class="notice notice-success"><p>'.esc_html__('Cache wurde geleert.','punktepass').'</p></div>';
    }
    if ( class_exists('PPV_Cache') && method_exists('PPV_Cache','button_html') ){
      PPV_Cache::button_html();
    } else {
      // Fallback simple form
      $nonce = wp_create_nonce('ppv_cache_clear');
      echo '<form method="post" action="'.esc_url( admin_url('admin-post.php') ).'">';
      echo '<input type="hidden" name="action" value="ppv_cache_clear">';
      echo '<input type="hidden" name="_wpnonce" value="'.esc_attr($nonce).'">';
      submit_button(__('Cache leeren','punktepass'));
      echo '</form>';
    }
    echo '</div>';
  }

  public static function admin_bar($wp_admin_bar){
    if ( ! self::can_use() ) return;
    if ( ! current_user_can('read') ) return;
    $nonce = wp_create_nonce('ppv_cache_clear');
    $url = add_query_arg([
      'action'   => 'ppv_cache_clear',
      '_wpnonce' => $nonce,
    ], admin_url('admin-post.php'));
    $wp_admin_bar->add_node([
      'id'    => 'ppv-cache-clear',
      'title' => '⚡ Cache leeren',
      'href'  => $url,
      'meta'  => ['title' => 'Cache leeren'],
    ]);
  }
}
PPV_Cache_Admin::hooks();
