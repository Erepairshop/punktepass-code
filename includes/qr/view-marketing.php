<?php
echo '<div class="ppv-marketing">';
echo '<div class="pp-grid">';

$tools = [
    'Instagram-Post'   => 'Quadratische Vorlage für Social Media.',
    'Flyer A4'         => 'Druckbare Vorlage für deinen Laden.',
    'Facebook-Banner'  => 'Optimiert für Titelbilder.',
    'WhatsApp-Share'   => 'Direkter Share-Link.'
];

foreach($tools as $name=>$desc){
    echo '<div class="pp-card">';
    echo '<h4>'.esc_html($name).'</h4>';
    echo '<p>'.esc_html($desc).'</p>';
    echo '<p><button class="button">Download</button></p>';
    echo '</div>';
}

echo '</div>';
echo '</div>';
