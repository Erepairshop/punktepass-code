<?php
if ( ! defined('ABSPATH') ) exit;

class PPV_Reviews_Admin {

  public static function hooks(){
    add_action('admin_menu',[__CLASS__,'menu']);
    add_action('admin_post_ppv_delete_review',[__CLASS__,'delete_review']);
    add_action('admin_post_ppv_reply_review',[__CLASS__,'save_reply']);
  }

  public static function menu(){
    add_submenu_page(
      'punktepass',             
      'Reviews',                
      'Reviews',                
      'read',                   // már elég a "read", vendor is láthatja
      'ppv-reviews',            
      [__CLASS__, 'page']       
    );
  }

  public static function page(){
    global $wpdb;
    $p=$wpdb->prefix;

    $per_page = 20;
    $paged = isset($_GET['paged']) ? max(1,intval($_GET['paged'])) : 1;
    $offset = ($paged-1)*$per_page;

    // Szűrés paraméterek
    $where = "1=1";
    $params = [];

    // Vendor csak a saját store-ját láthatja
    if(!current_user_can('manage_options')){
      $store_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$p}pp_stores WHERE user_id=%d", get_current_user_id()));
      if(!$store_ids) { echo '<div class="wrap"><h1>Reviews</h1><p>Keine Bewertungen verfügbar.</p></div>'; return; }
      $where .= " AND r.store_id IN (".implode(",",array_map('intval',$store_ids)).")";
    }

    if(!empty($_GET['store_id'])){
      $where .= " AND r.store_id=%d";
      $params[] = intval($_GET['store_id']);
    }

    if(!empty($_GET['rating'])){
      $where .= " AND r.rating=%d";
      $params[] = intval($_GET['rating']);
    }

    if(!empty($_GET['search'])){
      $s = '%'.$wpdb->esc_like($_GET['search']).'%';
      $where .= " AND (r.comment LIKE %s OR u.display_name LIKE %s)";
      $params[] = $s; $params[] = $s;
    }

    // Összes találat számolása
    $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}pp_reviews r LEFT JOIN {$p}users u ON r.user_id=u.ID WHERE $where",$params));

    // Lekérdezés
    $sql = "SELECT r.*, u.display_name 
            FROM {$p}pp_reviews r 
            LEFT JOIN {$p}users u ON r.user_id=u.ID 
            WHERE $where 
            ORDER BY r.created_at DESC 
            LIMIT %d OFFSET %d";
    $params[]=$per_page;
    $params[]=$offset;
    $reviews=$wpdb->get_results($wpdb->prepare($sql,$params));

    echo '<div class="wrap"><h1>Alle Bewertungen</h1>';

    // Szűrő form
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="ppv-reviews">';
    echo 'Store ID: <input type="number" name="store_id" value="'.esc_attr($_GET['store_id']??'').'" style="width:80px"> ';
    echo 'Rating: <select name="rating"><option value="">Alle</option>';
    for($i=1;$i<=5;$i++){
      $sel = (($_GET['rating']??'')==$i)?'selected':'';
      echo "<option value='$i' $sel>$i Sterne</option>";
    }
    echo '</select> ';
    echo 'Suche: <input type="text" name="search" value="'.esc_attr($_GET['search']??'').'" placeholder="Kommentar oder Benutzer"> ';
    echo '<button type="submit" class="button">Filtern</button>';
    echo '</form><br>';

    if (!$reviews){
      echo '<p>Noch keine Bewertungen vorhanden.</p></div>';
      return;
    }

    echo '<table class="widefat striped">';
    echo '<thead><tr>
      <th>ID</th>
      <th>Store</th>
      <th>User</th>
      <th>Rating</th>
      <th>Kommentar</th>
      <th>Antwort</th>
      <th>Datum</th>
      <th>Aktionen</th>
    </tr></thead><tbody>';

    foreach($reviews as $r){
      echo '<tr>';
      echo '<td>'.$r->id.'</td>';
      echo '<td>#'.$r->store_id.'</td>';
      echo '<td>'.esc_html($r->display_name).'</td>';
      echo '<td>'.$r->rating.' / 5</td>';
      echo '<td>'.esc_html($r->comment).'</td>';
      echo '<td>
        <form method="post" action="'.admin_url('admin-post.php').'">
          <input type="hidden" name="action" value="ppv_reply_review">
          <input type="hidden" name="review_id" value="'.$r->id.'">
          <textarea name="reply" rows="2" style="width:100%;">'.esc_textarea($r->reply).'</textarea>
          <br><button type="submit" class="button">Speichern</button>
        </form>
      </td>';
      echo '<td>'.esc_html(date_i18n(get_option('date_format').' '.get_option('time_format'), strtotime($r->created_at))).'</td>';
      echo '<td>
        <form method="post" action="'.admin_url('admin-post.php').'" onsubmit="return confirm(\'Wirklich löschen?\');">
          <input type="hidden" name="action" value="ppv_delete_review">
          <input type="hidden" name="review_id" value="'.$r->id.'">
          <button type="submit" class="button button-danger">Löschen</button>
        </form>
      </td>';
      echo '</tr>';
    }

    echo '</tbody></table>';

    // Pagination
    $pages = ceil($total/$per_page);
    if($pages>1){
      echo '<div class="tablenav"><div class="tablenav-pages">';
      for($i=1;$i<=$pages;$i++){
        $class = ($i==$paged)?'class="current-page"':'';
        $url = add_query_arg(array_merge($_GET,['paged'=>$i]));
        echo "<a $class href='".esc_url($url)."'>$i</a> ";
      }
      echo '</div></div>';
    }

    echo '</div>';
  }

  // Review törlés
  public static function delete_review(){
    global $wpdb;
    $p=$wpdb->prefix;
    $id=intval($_POST['review_id']);

    // Jogosultság ellenőrzés
    $store_id = $wpdb->get_var($wpdb->prepare("SELECT store_id FROM {$p}pp_reviews WHERE id=%d",$id));
    $owner_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$p}pp_stores WHERE id=%d",$store_id));
    if($owner_id!=get_current_user_id() && !current_user_can('manage_options')){
      wp_die('Keine Berechtigung');
    }

    $wpdb->delete("{$p}pp_reviews",['id'=>$id]);
    wp_safe_redirect(admin_url('admin.php?page=ppv-reviews&deleted=1'));
    exit;
  }

  // Reply mentés
  public static function save_reply(){
    global $wpdb;
    $p=$wpdb->prefix;
    $id=intval($_POST['review_id']);
    $reply=sanitize_textarea_field($_POST['reply']);

    // Jogosultság ellenőrzés
    $store_id = $wpdb->get_var($wpdb->prepare("SELECT store_id FROM {$p}pp_reviews WHERE id=%d",$id));
    $owner_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$p}pp_stores WHERE id=%d",$store_id));
    if($owner_id!=get_current_user_id() && !current_user_can('manage_options')){
      wp_die('Keine Berechtigung');
    }

    $wpdb->update("{$p}pp_reviews",['reply'=>$reply],['id'=>$id]);
    wp_safe_redirect(admin_url('admin.php?page=ppv-reviews&updated=1'));
    exit;
  }
}
