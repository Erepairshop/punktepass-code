<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Dompdf betöltése
if ( file_exists(__DIR__ . '/vendor/autoload.php') ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Dompdf\Dompdf;
use Dompdf\Options;

class PPV_Marketing {

  public static function hooks(){
    add_action('template_redirect',[__CLASS__,'render_downloads']);
  }

  public static function render_for_dashboard($store_id){
    global $wpdb; $p=$wpdb->prefix;
    $store = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}pp_stores WHERE id=%d",$store_id));
    if(!$store){ echo '<p>Kein Store gefunden.</p>'; return; }

    echo '<h3>'.esc_html__('Marketing-Tools','punktepass').'</h3>';
    echo '<p>'.esc_html__('Nutzen Sie Poster, Flyer und Social Media Vorlagen, um Ihre Kunden auf PunktePass aufmerksam zu machen.','punktepass').'</p>';

    $qr_url = add_query_arg(['pp_scan'=>1,'store_id'=>$store_id], home_url('/'));
    $poster = add_query_arg(['pp_print_qr'=>1,'store'=>$store_id], home_url('/'));
    $flyer  = add_query_arg(['pp_marketing'=>1,'type'=>'flyer','store'=>$store_id], home_url('/'));
    $social = add_query_arg(['pp_marketing'=>1,'type'=>'social','store'=>$store_id], home_url('/'));

    echo '<div class="pp-grid">';
    echo '<div class="pp-card"><h4>📌 QR-Poster</h4><p>Großes Poster mit QR-Code.</p>
          <p><a class="button button-primary" target="_blank" href="'.esc_url($poster).'">Poster öffnen</a></p></div>';

    echo '<div class="pp-card"><h4>📄 Flyer (PDF)</h4><p>A4-Flyer mit QR-Code und Shopname.</p>
          <p><a class="button" target="_blank" href="'.esc_url($flyer).'">Flyer herunterladen</a></p></div>';

    echo '<div class="pp-card"><h4>📲 Social Media Bild</h4><p>PNG (1080x1080) mit QR-Code.</p>
          <p><a class="button" target="_blank" href="'.esc_url($social).'">Bild herunterladen</a></p></div>';

    echo '<div class="pp-card"><h4>🔗 Digitaler QR-Link</h4>
          <input type="text" readonly value="'.esc_attr($qr_url).'" style="width:100%" onclick="this.select()">
          <p><button class="button" onclick="navigator.clipboard.writeText(\''.esc_js($qr_url).'\')">Link kopieren</button></p></div>';

    $embed = '<iframe src="'.$qr_url.'" width="300" height="300" frameborder="0"></iframe>';
    echo '<div class="pp-card"><h4>💻 Einbettungscode</h4>
          <textarea readonly style="width:100%;height:80px;" onclick="this.select()">'.esc_textarea($embed).'</textarea></div>';
    echo '</div>';
  }

  public static function render_downloads(){
    if(!isset($_GET['pp_marketing']) || !isset($_GET['type']) || !isset($_GET['store'])) return;

    $type = sanitize_text_field($_GET['type']);
    $store_id = intval($_GET['store']);
    global $wpdb; $p=$wpdb->prefix;
    $store = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}pp_stores WHERE id=%d",$store_id));
    if(!$store) wp_die('Store nicht gefunden.');

    $qr_url = add_query_arg(['pp_scan'=>1,'store_id'=>$store_id], home_url('/'));
    $qr_img = 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&ecc=Q&margin=4&data='.rawurlencode($qr_url);

if($type==='flyer'){
  // Logo betöltés base64-be
  $logo_html = '';
  if (!empty($store->logo_url)) {
      $logo_data = @file_get_contents($store->logo_url);
      if ($logo_data !== false) {
          $mime = 'image/png';
          $base64 = 'data:'.$mime.';base64,'.base64_encode($logo_data);
          $logo_html = '<div class="logo"><img src="'.$base64.'" style="max-height:80px;"></div>';
      }
  }

  $html = '
  <html>
  <head>
    <style>
      @page { margin: 0; }
      body { margin:0; padding:0; font-family: Arial, sans-serif; background:#ffffff; color:#333; }
      .flyer {
        width: 210mm; height: 297mm;
        padding: 40px;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: space-between;
      }
      .header {
        width: 100%;
        text-align: center;
        margin-bottom: 10px;
      }
      .header h1 {
        margin: 10px 0 0 0;
        font-size: 28px;
        font-family: "Comic Sans MS", "Brush Script MT", cursive;
        color: #2c3e50;
      }
      .slogan {
        font-size: 22px;
        margin: 20px 0;
        font-family: "Comic Sans MS", "Brush Script MT", cursive;
        color:#27ae60;
      }
      .divider {
        width: 60%;
        height: 4px;
        margin: 15px auto;
        background: linear-gradient(90deg, #27ae60, #2980b9);
        border-radius: 2px;
      }
      .qr img {
        max-width: 260px;
        border: 3px solid #2c3e50;
        border-radius: 10px;
        padding: 8px;
      }
      .footer {
        margin-top: 30px;
        font-size: 14px;
        text-align: center;
        color:#555;
        border-top:1px solid #ddd;
        padding-top:10px;
      }
      .footer a {
        color:#2c3e50;
        text-decoration:none;
        font-weight: bold;
      }
    </style>
  </head>
  <body>
    <div class="flyer">
      <div class="header">
        '.$logo_html.'
        <h1>'.htmlspecialchars($store->name).'</h1>
      </div>
      <div class="divider"></div>
      <div class="slogan">Jetzt Punkte sammeln & exklusive Vorteile sichern!</div>
      <div class="qr"><img src="'.$qr_img.'"></div>
      <div class="footer">
        '.htmlspecialchars($store->address).', '.htmlspecialchars($store->plz).' '.htmlspecialchars($store->city).'<br>
        '.htmlspecialchars($store->phone).' | '.htmlspecialchars($store->email).'<br>
        <a href="'.esc_url($store->website).'" target="_blank">'.esc_html($store->website).'</a>
      </div>
    </div>
  </body>
  </html>';


  $options = new Options();
  $options->set('isRemoteEnabled', true);
  $dompdf = new Dompdf($options);
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();
  $dompdf->stream("Flyer-".$store->name.".pdf", ["Attachment"=>true]);
  exit;
}




    // --- Social Media Bild ---
    if($type==='social'){
      $im = imagecreatetruecolor(1080,1080);
      $white = imagecolorallocate($im,255,255,255);
      $black = imagecolorallocate($im,0,0,0);
      imagefill($im,0,0,$white);

      $font = __DIR__."/../assets/arial.ttf";
      if(!file_exists($font)){
        wp_die('Fehlender Font: assets/arial.ttf');
      }

      imagettftext($im,40,0,50,100,$black,$font,$store->name);
      $qr_data = file_get_contents($qr_img);
      $qr_img_res = imagecreatefromstring($qr_data);
      imagecopyresampled($im,$qr_img_res,240,200,0,0,600,600,imagesx($qr_img_res),imagesy($qr_img_res));
      imagettftext($im,24,0,350,950,$black,$font,"#PunktePass  #Treuepunkte");

      header('Content-Type: image/png');
      header('Content-Disposition: attachment; filename="Social-'.$store->name.'.png"');
      imagepng($im);
      imagedestroy($im);
      exit;
    }
  }
}
