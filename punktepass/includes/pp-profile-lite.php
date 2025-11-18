<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * /haendler-profil – vendor profile form (logo/cover/address/hours/description)
 * Protected: only logged-in users; ideally vendor role.
 */
class PPV_Profile_Lite {
  public static function hooks(){
    add_action('init',[__CLASS__,'rewrite']);
    add_action('template_redirect',[__CLASS__,'render']);
    add_action('init',[__CLASS__,'handle_post']);
  }
  public static function rewrite(){
    add_rewrite_rule('^haendler-profil/?$', 'index.php?ppv_vendor_profile=1', 'top');
    add_rewrite_tag('%ppv_vendor_profile%', '1');
  }
  public static function can_access(){
    return is_user_logged_in(); // optionally: current_user_can('read')
  }
  public static function handle_post(){
    if( empty($_POST['ppv_action']) || $_POST['ppv_action']!=='profile_save' ) return;
    if( ! self::can_access() ) wp_die('No permission');
    check_admin_referer('ppv_profile_save');
    global $wpdb; $p=$wpdb->prefix;
    $store = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$p}pp_stores WHERE user_id=%d LIMIT 1", get_current_user_id()) );
    if( ! $store ) wp_die('Store not found');

    $data = [
      'address' => sanitize_text_field($_POST['address'] ?? ''),
      'city' => sanitize_text_field($_POST['city'] ?? ''),
      'opening_hours' => wp_kses_post($_POST['opening_hours'] ?? ''),
      'description' => wp_kses_post($_POST['description'] ?? ''),
    ];

    require_once ABSPATH.'wp-admin/includes/file.php';
    require_once ABSPATH.'wp-admin/includes/image.php';
    $over = ['test_form'=>false];

    foreach(['logo'=>'logo_url','cover'=>'cover_url'] as $field=>$col){
      if( ! empty($_FILES[$field]['name']) ){
        $uploaded = wp_handle_upload($_FILES[$field], $over);
        if( empty($uploaded['error']) ){
          $file = $uploaded['file'];
          $editor = wp_get_image_editor($file);
          $url = $uploaded['url'];
          if( ! is_wp_error($editor) ){
            $editor->set_quality(85);
            $saved = $editor->save( pathinfo($file, PATHINFO_DIRNAME).'/'.pathinfo($file, PATHINFO_FILENAME).'.webp', 'image/webp' );
            if( ! is_wp_error($saved) && ! empty($saved['path']) ){
              $upload_dir = wp_upload_dir();
              $url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $saved['path']);
            }
          }
          $data[$col] = $url;
        }
      }
    }

    $wpdb->update("{$p}pp_stores", $data, ['id'=>$store->id]);
    wp_safe_redirect( home_url('/haendler-profil?ppmsg=ok') ); exit;
  }
  public static function render(){
    if( ! get_query_var('ppv_vendor_profile') ) return;
    if( ! self::can_access() ){ auth_redirect(); exit; }
    global $wpdb; $p=$wpdb->prefix;
    $store = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$p}pp_stores WHERE user_id=%d LIMIT 1", get_current_user_id()) );
    status_header(200); nocache_headers();
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Händler Profil</title>';
    echo '<link rel="stylesheet" href="'.esc_url( PPV_URL.'assets/css/profile.css?ver='.PPV_VERSION ).'">';
    echo '<div class="ppv-container"><h1>Händler Profil</h1>';
    if( isset($_GET['ppmsg']) && $_GET['ppmsg']==='ok'){ echo '<div class="ppv-ok">Gespeichert.</div>'; }
    if(!$store){ echo '<p>Kein Store gefunden.</p>'; echo '</div>'; exit; }
    $nonce = wp_create_nonce('ppv_profile_save');
    echo '<form method="post" enctype="multipart/form-data" class="ppv-form">';
    echo '<input type="hidden" name="ppv_action" value="profile_save"><input type="hidden" name="_wpnonce" value="'.esc_attr($nonce).'">';
    echo '<label>Logó<input type="file" name="logo" accept="image/*"></label>';
    if($store->logo_url){ echo '<img src="'.esc_url($store->logo_url).'" class="ppv-thumb">'; }
    echo '<label>Borítókép<input type="file" name="cover" accept="image/*"></label>';
    if($store->cover_url){ echo '<img src="'.esc_url($store->cover_url).'" class="ppv-thumb wide">'; }
    echo '<label>Cím<input type="text" name="address" value="'.esc_attr($store->address).'"></label>';
    echo '<label>Város<input type="text" name="city" value="'.esc_attr($store->city).'"></label>';
    echo '<label>Nyitvatartás<textarea name="opening_hours" rows="3">'.esc_textarea($store->opening_hours).'</textarea></label>';
    echo '<label>Rövid leírás<textarea name="description" rows="3">'.esc_textarea(isset($store->description)?$store->description:'').'</textarea></label>';
    echo '<button class="ppv-btn">Mentés</button> ';
    if($store->slug){ echo '<a class="ppv-link" target="_blank" href="'.esc_url(home_url('/store/'.$store->slug)).'">Publikus oldal megnyitása</a>'; }
    echo '</form></div>'; exit;
  }
}
PPV_Profile_Lite::hooks();
