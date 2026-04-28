<?php
if (!defined('ABSPATH')) exit;
$adv = PPV_Advertisers::current_advertiser();
global $wpdb;
$totals = $wpdb->get_row($wpdb->prepare(
    "SELECT SUM(impressions) as imp, SUM(clicks) as clk, COUNT(*) as cnt
     FROM {$wpdb->prefix}ppv_ads WHERE advertiser_id = %d", $adv->id
));
$followers = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_advertiser_followers WHERE advertiser_id = %d", $adv->id));
$ctr = ($totals && $totals->imp > 0) ? round($totals->clk / $totals->imp * 100, 1) : 0;
?>
<div class="bz-card">
  <h1 class="bz-h1"><?php echo esc_html(PPV_Lang::t('biz_stats_title')); ?></h1>
</div>
<div class="bz-grid">
  <div class="bz-card"><h2 class="bz-h2"><?php echo esc_html(PPV_Lang::t('biz_stats_ads')); ?></h2><div style="font-size:32px; font-weight:700;"><?php echo (int)($totals->cnt ?? 0); ?></div></div>
  <div class="bz-card"><h2 class="bz-h2"><?php echo esc_html(PPV_Lang::t('biz_stats_imp')); ?></h2><div style="font-size:32px; font-weight:700;"><?php echo (int)($totals->imp ?? 0); ?></div></div>
  <div class="bz-card"><h2 class="bz-h2"><?php echo esc_html(PPV_Lang::t('biz_stats_clk')); ?></h2><div style="font-size:32px; font-weight:700;"><?php echo (int)($totals->clk ?? 0); ?></div></div>
  <div class="bz-card"><h2 class="bz-h2"><?php echo esc_html(PPV_Lang::t('biz_stats_ctr')); ?></h2><div style="font-size:32px; font-weight:700;"><?php echo $ctr; ?>%</div></div>
  <div class="bz-card"><h2 class="bz-h2"><?php echo esc_html(PPV_Lang::t('biz_stats_followers')); ?></h2><div style="font-size:32px; font-weight:700;"><?php echo $followers; ?></div></div>
</div>
