<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Basic rate limit for scan/redeem via WP REST.
 */
class PPV_Security {
  public static function hooks(){
    add_filter('rest_pre_dispatch', [__CLASS__, 'ratelimit'], 10, 3);
  }
  public static function ratelimit($result, $server, $request){
    $route = $request->get_route();
    if ( strpos($route, '/punktepass/v1/scan') !== false || strpos($route, '/punktepass/v1/redeem') !== false ){
      $key = 'ppv_rl_'.md5($route.'|'.get_current_user_id().'|'.$_SERVER['REMOTE_ADDR']);
      $count = intval( get_transient($key) );
      if ( $count > 10 ){ // simple 10 req / minute
        return new WP_Error('rate_limited', __('Zu viele Anfragen. Bitte später erneut versuchen.','punktepass'), ['status'=>429]);
      }
      set_transient($key, $count+1, MINUTE_IN_SECONDS);
    }
    return $result;
  }
}
PPV_Security::hooks();
