<?php
if(!$store) return;

echo '<div class="ppv-design">';
echo '<form method="post" enctype="multipart/form-data" id="ppv-design-form">';
wp_nonce_field('ppv_qr');
echo '<input type="hidden" name="pp_action" value="save_design">';

echo '<p><label>QR-Farbe<br><input type="color" name="qr_color" value="'.esc_attr($store->qr_color ?: '#000000').'" id="ppv-qr-color"></label></p>';

echo '<p><label>Logo (optional)<br><input type="file" name="qr_logo" id="ppv-logo-upload"></label></p>';

if($store->qr_logo){
    echo '<div class="pp-logo-preview"><img src="'.esc_url($store->qr_logo).'" alt="Logo" style="max-width:120px;"></div>';
}

echo '<div id="ppv-preview-wrap">';
$qr_url=add_query_arg(['pp_scan'=>1,'store_id'=>$store->id,'t'=>$store->qr_secret],home_url('/'));
$qr_img='https://api.qrserver.com/v1/create-qr-code/?size=200x200&color='.ltrim($store->qr_color ?: '#000000','#').'&data='.rawurlencode($qr_url);
echo '<img id="ppv-qr-preview" src="'.$qr_img.'" data-url="'.esc_attr($qr_url).'" alt="Preview">';
echo '</div>';

echo '<p><button class="button button-primary">Design speichern</button></p>';
echo '</form>';
echo '</div>';
