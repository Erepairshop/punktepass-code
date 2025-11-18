<?php
if(!$store) return;

$qr_url = add_query_arg([
    'pp_scan'=>1,
    'store_id'=>$store->id,
    't'=>$store->qr_secret
], home_url('/'));

$qr_img = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data='.rawurlencode($qr_url);

echo '<div class="ppv-qr-block">';
echo '<div class="ppv-qr-img-wrap">';
echo '<img id="ppv-qr-preview" src="'.$qr_img.'" data-url="'.esc_attr($qr_url).'" alt="QR Code">';
echo '</div>';

echo '<div class="ppv-qr-actions">';
echo '<input type="text" readonly value="'.esc_attr($qr_url).'" class="ppv-qr-link">';
echo '<button type="button" class="button ppv-copy-btn" data-copy="'.esc_attr($qr_url).'">Link kopieren</button> ';
echo '<a href="'.$qr_img.'" download="qr-code.png" class="button">Download</a>';
echo '</div>';

echo '</div>';
