<?php
if(!$store) return;

$url=home_url('/?pp_scan=1&store_id='.$store->id.'&t='.$store->qr_secret);

echo '<div class="ppv-share">';
echo '<p>Teile deinen QR-Code:</p>';
echo '<button class="button ppv-share" data-net="fb" data-url="'.esc_attr($url).'">Facebook</button> ';
echo '<button class="button ppv-share" data-net="wa" data-url="'.esc_attr($url).'">WhatsApp</button> ';
echo '<button class="button ppv-share" data-net="mail" data-url="'.esc_attr($url).'">E-Mail</button> ';
echo '<a href="'.$url.'" download class="button">Download</a>';
echo '</div>';
