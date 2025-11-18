<?php
if(!$store) return;

$qr_url=add_query_arg(['pp_scan'=>1,'store_id'=>$store->id,'t'=>$store->qr_secret],home_url('/'));
$qr_img='https://api.qrserver.com/v1/create-qr-code/?size=400x400&data='.rawurlencode($qr_url);

echo '<div class="ppv-poster">';
echo '<div class="pp-poster-preview">';
echo '<h2>'.esc_html($store->name).'</h2>';
echo '<img src="'.$qr_img.'" alt="Poster QR" class="ppv-qr-img">';
echo '<p>'.esc_html($store->address).' – '.esc_html($store->city).'</p>';
echo '</div>';
echo '<button id="ppv-download-poster" class="button button-primary">Als PDF speichern</button>';
echo '</div>';
