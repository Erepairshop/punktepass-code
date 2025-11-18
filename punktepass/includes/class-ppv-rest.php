<?php if ( ! defined( 'ABSPATH' ) ) exit;
class PPV_REST {
  public function hooks(){ add_action('rest_api_init', [$this,'routes']); }
  public function routes(){
    register_rest_route('punktepass/v1','/scan',[ 'methods'=>'POST','callback'=>[$this,'scan'],'permission_callback'=>function(){return is_user_logged_in();} ]);
    register_rest_route('punktepass/v1','/redeem',[ 'methods'=>'POST','callback'=>[$this,'redeem'],'permission_callback'=>function(){return is_user_logged_in();} ]);
  }
  public function scan($req){
    $store_id=intval($req->get_param('store_id')); $loc=intval($req->get_param('location_id'));
    if(!$store_id) return new WP_REST_Response(['ok'=>false,'msg'=>__('Ungültige Anfrage.','punktepass')],400);
    $res=PPV_Core::daily_scan(get_current_user_id(),$store_id,$loc);
    if(is_wp_error($res)) return new WP_REST_Response(['ok'=>false,'msg'=>$res->get_error_message()],400);
    return new WP_REST_Response(['ok'=>true,'msg'=>__('1 Punkt gutgeschrieben.','punktepass')],200);
  }
  public function redeem($req){
    $store_id=intval($req->get_param('store_id')); $rid=intval($req->get_param('reward_id'));
    if(!$store_id||!$rid) return new WP_REST_Response(['ok'=>false,'msg'=>__('Ungültige Anfrage.','punktepass')],400);
    $code=PPV_Core::issue_redemption_code(get_current_user_id(),$store_id,$rid);
    if(is_wp_error($code)) return new WP_REST_Response(['ok'=>false,'msg'=>$code->get_error_message()],400);
    return new WP_REST_Response(['ok'=>true,'code'=>$code,'expires_in'=>300],200);
  }
}


function ppv_check_limits($store_id, $reward_id, $user_id){
  global $wpdb; $p=$wpdb->prefix;
  $r = $wpdb->get_row( $wpdb->prepare("SELECT daily_limit,user_period_limit FROM {$p}pp_rewards WHERE id=%d", $reward_id) );
  if(!$r) return true;
  if( !empty($r->daily_limit) ){
    $cnt = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(1) FROM {$p}pp_redemptions WHERE reward_id=%d AND DATE(created_at)=CURDATE()", $reward_id) );
    if( $cnt >= intval($r->daily_limit) ) return new WP_Error('limit_daily','Tageslimit erreicht', ['status'=>429]);
  }
  if( !empty($r->user_period_limit) ){
    $interval = '1 DAY'; if($r->user_period_limit==='week') $interval='7 DAY'; if($r->user_period_limit==='month') $interval='30 DAY';
    $cnt = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(1) FROM {$p}pp_redemptions WHERE reward_id=%d AND user_id=%d AND created_at >= (NOW() - INTERVAL $interval)", $reward_id, $user_id) );
    if( $cnt > 0 ) return new WP_Error('limit_user','Periodenlimit erreicht', ['status'=>429]);
  }
  return true;
}
