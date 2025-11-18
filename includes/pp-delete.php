<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Stable server-side delete handlers to avoid JS flakiness.
 * Converts delete actions to admin-post requests with nonces.
 */
class PPV_Delete_Stable {
  public static function hooks(){
    add_action('admin_post_ppv_reward_delete', [__CLASS__, 'reward_delete']);
    add_action('admin_post_nopriv_ppv_reward_delete', [__CLASS__, 'forbid']);
  }
  public static function forbid(){ wp_die('No permission'); }

  public static function reward_delete(){
    if( ! is_user_logged_in() ) wp_die('No permission');
    check_admin_referer('ppv_reward_delete');
    $id = isset($_POST['reward_id']) ? intval($_POST['reward_id']) : 0;
    if( $id <= 0 ) wp_die('Invalid ID');
    global $wpdb; $p=$wpdb->prefix;
    // Permission: vendor must own the reward (same store)
    $user_id = get_current_user_id();
    $store_id = $wpdb->get_var( $wpdb->prepare("SELECT id FROM {$p}pp_stores WHERE user_id=%d LIMIT 1", $user_id) );
    if( ! $store_id ) wp_die('No store');
    $ok = $wpdb->query( $wpdb->prepare("DELETE FROM {$p}pp_rewards WHERE id=%d AND store_id=%d", $id, $store_id) );
    $url = remove_query_arg(['ppmsg'], wp_get_referer() ?: home_url('/haendler-dashboard'));
    $url = add_query_arg('ppmsg', $ok ? 'rdeleted' : 'rdel_fail', $url);
    wp_safe_redirect($url); exit;
  }
}
PPV_Delete_Stable::hooks();
