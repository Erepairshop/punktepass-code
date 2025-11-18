<?php if ( ! defined( 'ABSPATH' ) ) exit;
class PPV_Updater {
  public static function hooks(){
    add_action('admin_menu',[__CLASS__,'menu']);
    add_action('admin_post_ppv_apply_patch',[__CLASS__,'apply_patch']);
  }
  public static function menu(){
    add_submenu_page('tools.php','PunktePass Update','PunktePass Update','manage_options','ppv-updater',[__CLASS__,'render']);
  }
  public static function render(){
    if(!current_user_can('manage_options')) return;
    echo '<div class="wrap"><h1>PunktePass – Updates</h1>';
    echo '<p>Lade hier ein <strong>Patch-ZIP</strong> hoch. Nur a benne lévő fájlok kerülnek felülírásra a plugin mappában.</p>';
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data">';
    wp_nonce_field('ppv_patch_upload');
    echo '<input type="hidden" name="action" value="ppv_apply_patch">';
    echo '<input type="file" name="ppv_patch" accept=".zip" required> ';
    echo '<button class="button button-primary">Patch anwenden</button>';
    echo '</form></div>';
  }
  public static function apply_patch(){
    if(!current_user_can('manage_options')) wp_die('forbidden');
    check_admin_referer('ppv_patch_upload');
    if(empty($_FILES['ppv_patch']['name'])) wp_die('No file');
    $file = $_FILES['ppv_patch'];
    require_once ABSPATH.'wp-admin/includes/file.php';
    require_once ABSPATH.'wp-admin/includes/plugin.php';
    require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
    $overrides = ['test_form'=>false,'mimes'=>['zip'=>'application/zip']];
    $move = wp_handle_upload($file, $overrides);
    if(empty($move['file'])) wp_die('Upload failed');
    $zip = $move['file'];
    // Optional: create simple backup if ZipArchive available
    $uploads = wp_upload_dir();
    $backupDir = trailingslashit($uploads['basedir']).'punktepass-backups';
    if(!is_dir($backupDir)) wp_mkdir_p($backupDir);
    if(class_exists('ZipArchive')){
      $bk = $backupDir.'/ppv-backup-'.date('Ymd-His').'.zip';
      $za = new ZipArchive();
      if($za->open($bk, ZipArchive::CREATE)===true){
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PPV_DIR, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach($it as $f){
          $local = substr($f->getPathname(), strlen(PPV_DIR));
          $za->addFile($f->getPathname(), 'punktepass/'.$local);
        }
        $za->close();
      }
    }
    // Unzip into plugin dir
    require_once ABSPATH.'wp-admin/includes/class-pclzip.php';
    $res = unzip_file($zip, PPV_DIR);
    if(is_wp_error($res)){
      wp_die('Unzip failed: '.$res->get_error_message());
    }
    wp_redirect(add_query_arg('ppvmsg','patched',admin_url('tools.php?page=ppv-updater')));
    exit;
  }
}
