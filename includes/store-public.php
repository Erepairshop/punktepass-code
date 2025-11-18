<?php
if ( ! defined('ABSPATH') ) exit;

$store_slug = get_query_var('pp_store');
global $wpdb; $p = $wpdb->prefix;

// Bolt adatok
$store = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$p}pp_stores WHERE slug=%s", $store_slug) );
if ( ! $store ) { echo '<p>Store nicht gefunden.</p>'; return; }

// Jutalmak
$rewards = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$p}pp_rewards WHERE store_id=%d AND active=1 ORDER BY id DESC", $store->id) );

// Telephelyek
$locations = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$p}pp_store_locations WHERE store_id=%d AND active=1 ORDER BY id DESC", $store->id) );
?>

<div class="pp-store-public" style="max-width:900px;margin:20px auto;font-family:sans-serif">
  <h1><?php echo esc_html($store->name); ?></h1>

  <?php if ( !empty($store->cover_url) ): ?>
    <div><img src="<?php echo esc_url($store->cover_url); ?>" alt="Cover" style="width:100%;max-height:300px;object-fit:cover;border-radius:8px"></div>
  <?php endif; ?>

  <?php if ( !empty($store->logo_url) ): ?>
    <div><img src="<?php echo esc_url($store->logo_url); ?>" alt="Logo" style="max-width:150px;margin:15px 0"></div>
  <?php endif; ?>

  <?php if ( !empty($store->description) ): ?>
    <p><strong><?php echo nl2br(esc_html($store->description)); ?></strong></p>
  <?php endif; ?>

  <p>
    <?php if ( !empty($store->address) ) echo '<div><strong>Adresse:</strong> '.esc_html($store->address).'</div>'; ?>
    <?php if ( !empty($store->city) ) echo '<div><strong>Stadt:</strong> '.esc_html($store->city).'</div>'; ?>
    <?php if ( !empty($store->opening_hours) ) echo '<div><strong>Öffnungszeiten:</strong><br>'.nl2br(esc_html($store->opening_hours)).'</div>'; ?>
  </p>

  <hr>

  <h2>Prämien</h2>
  <?php if ($rewards): ?>
    <ul style="list-style:none;padding:0">
      <?php foreach ($rewards as $r): ?>
        <li style="border:1px solid #ddd;margin:8px 0;padding:10px;border-radius:6px">
          <strong><?php echo esc_html($r->title); ?></strong> – <?php echo intval($r->points_required); ?> Punkte
          <?php if ( !empty($r->description) ): ?>
            <div><?php echo esc_html($r->description); ?></div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p>Noch keine Prämien.</p>
  <?php endif; ?>

  <hr>

  <h2>Filialen</h2>
  <?php if ($locations): ?>
    <ul style="list-style:none;padding:0">
      <?php foreach ($locations as $l): ?>
        <li style="border:1px solid #ddd;margin:8px 0;padding:10px;border-radius:6px">
          <strong><?php echo esc_html($l->name); ?></strong><br>
          <?php if ( !empty($l->address) ) echo esc_html($l->address); ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p>Noch keine Filialen.</p>
  <?php endif; ?>
</div>
