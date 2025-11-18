<?php
if(!$store) return;

global $wpdb; $p=$wpdb->prefix;
$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}pp_campaigns WHERE store_id=%d ORDER BY id DESC",$store->id));

echo '<div class="ppv-campaigns">';

// Új kampány űrlap
echo '<form method="post" class="pp-form ppv-campaign-form">';
wp_nonce_field('ppv_qr');
echo '<input type="hidden" name="pp_action" value="create_campaign">';
echo '<p><label>Titel<br><input type="text" name="title" required></label></p>';
echo '<p><label>Beschreibung<br><textarea name="description"></textarea></label></p>';
echo '<div class="pp-grid-2">';
echo '<p><label>Startdatum<br><input type="date" name="start_date" required></label></p>';
echo '<p><label>Enddatum<br><input type="date" name="end_date" required></label></p>';
echo '</div>';
echo '<p><button class="button button-primary">+ Kampagne erstellen</button></p>';
echo '</form>';

// Kampánylista
if($rows){
    echo '<h4>Bestehende Kampagnen</h4>';
    echo '<table class="pp-table">';
    echo '<tr><th>ID</th><th>Titel</th><th>Zeitraum</th><th>QR</th><th>Aktionen</th></tr>';
    foreach($rows as $r){
        $qr_url=add_query_arg(['pp_scan'=>1,'store_id'=>$store->id,'t'=>$r->qr_secret],home_url('/'));
        $qr_img='https://api.qrserver.com/v1/create-qr-code/?size=150x150&data='.rawurlencode($qr_url);
        echo '<tr>';
        echo '<td>'.intval($r->id).'</td>';
        echo '<td>'.esc_html($r->title).'</td>';
        echo '<td>'.esc_html($r->start_date).' – '.esc_html($r->end_date).'</td>';
        echo '<td><img src="'.$qr_img.'" alt="QR" style="width:80px;height:80px;"></td>';
        echo '<td><button class="button ppv-campaign-del" data-id="'.$r->id.'">Löschen</button></td>';
        echo '</tr>';
    }
    echo '</table>';
}

echo '</div>';
