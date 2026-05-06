<?php
if (!defined('ABSPATH')) exit;

// Display success/error messages
if (isset($_GET['filiale_created']) && $_GET['filiale_created'] == 1) {
    echo '<div class="bz-alert success" style="margin-bottom:15px;">' . PPV_Lang::t('biz_filiale_created_msg', '✓ Filiale sikeresen hozzáadva!') . '</div>';
}
if (isset($_GET['filiale_deleted']) && $_GET['filiale_deleted'] == 1) {
    echo '<div class="bz-alert" style="margin-bottom:15px;">' . PPV_Lang::t('biz_filiale_deleted_msg', '✓ Filiale törölve!') . '</div>';
}

$adv = PPV_Advertisers::current_advertiser();
global $wpdb;
$ad_count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_ads WHERE advertiser_id = %d AND is_active=1", $adv->id));
$followers = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_advertiser_followers WHERE advertiser_id = %d", $adv->id));
$tier = $adv->tier;
$tier_info = PPV_Advertisers::TIERS[$tier] ?? ['ads'=>1,'push_per_month'=>4];
$days_left = $adv->subscription_until ? max(0, (strtotime($adv->subscription_until) - time()) / 86400) : 0;
?>
<?php
if ($adv && class_exists('PPV_Advertisers')) {
    $parent_id = $adv->parent_advertiser_id ?: $adv->id;
    
    // Get all filialen for the parent
    $filialen = $wpdb->get_results($wpdb->prepare(
        "SELECT id, business_name, filiale_label, address, city, postcode, parent_advertiser_id FROM {$wpdb->prefix}ppv_advertisers WHERE (parent_advertiser_id = %d OR id = %d) AND is_active = 1 ORDER BY parent_advertiser_id, id ASC",
        $parent_id, $parent_id
    ));

    $filiale_count = count($filialen);
?>
<div class="bz-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
        <h2 class="bz-h2" style="margin:0;"><i class="ri-store-2-line"></i> <?php echo PPV_Lang::t('biz_filiale_section_title'); ?></h2>
        <a href="<?php echo esc_url(home_url('/business/admin/filiale-new')); ?>" class="bz-btn secondary" style="padding: 6px 12px; font-size: 13px;">
            <i class="ri-add-line"></i> <?php echo PPV_Lang::t('biz_filiale_add_btn'); ?>
        </a>
    </div>
    
    <?php if ($filiale_count > 0): ?>
        <ul style="list-style:none; padding:0; margin:0 0 16px; display:flex; flex-direction:column; gap:10px;">
            <?php foreach ($filialen as $filiale): 
                $is_parent = !$filiale->parent_advertiser_id;
                $ad_count_filiale = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_ads WHERE advertiser_id = %d AND is_active=1", $filiale->id));
                $full_address = implode(', ', array_filter([$filiale->address, $filiale->postcode, $filiale->city]));
            ?>
            <li style="display:flex; align-items:center; gap:12px; padding:10px; border-radius:8px; <?php echo $is_parent ? 'border:1px solid var(--pp); background:rgba(99,102,241,.05);' : 'border:1px solid var(--border);'; ?>">
                <div style="flex:1;">
                    <strong style="font-weight:600;"><?php echo esc_html($filiale->business_name); ?><?php if ($is_parent) echo ' (' . PPV_Lang::t('main_location', 'Fő telephely') . ')'; ?></strong>
                    <div style="font-size:12px; color:var(--muted);"><?php echo esc_html($full_address); ?></div>
                </div>
                <span class="bz-tag basic"><?php echo sprintf(PPV_Lang::t('biz_filiale_ads_count'), $ad_count_filiale); ?></span>
                <?php if (!$is_parent): ?>
                <button type="button" class="bz-btn danger ghost filiale-delete-btn" data-filiale-id="<?php echo esc_attr($filiale->id); ?>" title="<?php echo PPV_Lang::t('biz_filiale_delete_btn', 'Törlés'); ?>">
                    <i class="ri-delete-bin-line"></i>
                </button>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p><?php echo PPV_Lang::t('biz_filiale_no_filialen_yet'); ?></p>
    <?php endif; ?>

    <div style="padding-top:12px; border-top:1px solid var(--border); font-size:12px; color:var(--muted); text-align:right;">
        <?php
        if ($filiale_count > 0 && class_exists('PPV_Advertisers')) {
            // Currency selection: use UI language (HU and RO langs → RON)
            $ui_lang = isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : 'de';
            $use_ron = in_array($ui_lang, ['ro', 'hu'], true);
            $currency = $use_ron ? 'RON' : '€';
            $base_price = $use_ron ? PPV_Advertisers::TIERS[PPV_Advertisers::TIER_BASIC]['price_ron'] : PPV_Advertisers::TIERS[PPV_Advertisers::TIER_BASIC]['price_eur'];
            $price = $base_price * max(1, $filiale_count);
            $push_limit = PPV_Advertisers::get_effective_push_limit($parent_id);

            echo sprintf(
                PPV_Lang::t('biz_filiale_price_line'), 
                $filiale_count, 
                number_format($base_price, 0) . '&nbsp;' . $currency, 
                number_format($price, 0) . '&nbsp;' . $currency
            );
            echo '<br>';
            echo sprintf(PPV_Lang::t('biz_filiale_push_limit'), $push_limit);
        }
        ?>
    </div>
</div>

<div id="ppv-filiale-delete-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:12px;max-width:420px;width:100%;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.3);font-family:inherit;">
    <div style="font-size:16px;line-height:1.5;color:#111;margin-bottom:20px;" id="ppv-filiale-delete-msg"><?php echo esc_html(PPV_Lang::t('biz_filiale_delete_confirm', 'Biztosan törölni szeretnéd ezt a fiókot?')); ?></div>
    <div style="display:flex;gap:10px;justify-content:flex-end;">
      <button type="button" id="ppv-filiale-delete-cancel" style="padding:10px 18px;border:1px solid #d1d5db;background:#fff;color:#374151;border-radius:8px;font-size:14px;cursor:pointer;font-family:inherit;"><?php echo esc_html(PPV_Lang::t('cancel', 'Mégse')); ?></button>
      <button type="button" id="ppv-filiale-delete-confirm" style="padding:10px 18px;border:none;background:#dc2626;color:#fff;border-radius:8px;font-size:14px;cursor:pointer;font-family:inherit;font-weight:600;"><?php echo esc_html(PPV_Lang::t('biz_filiale_delete_btn', 'Törlés')); ?></button>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('ppv-filiale-delete-modal');
    const btnOk = document.getElementById('ppv-filiale-delete-confirm');
    const btnCancel = document.getElementById('ppv-filiale-delete-cancel');
    let pendingId = null;

    const closeModal = () => { modal.style.display = 'none'; pendingId = null; };
    btnCancel.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    btnOk.addEventListener('click', function() {
        if (!pendingId) return;
        const id = pendingId;
        closeModal();
        fetch('<?php echo esc_url(rest_url('punktepass/v1/filiale-delete')); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ target_id: id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = window.location.pathname + '?filiale_deleted=1';
            } else {
                alert((data.message || 'Hiba'));
            }
        })
        .catch(() => alert('Network error'));
    });

    document.querySelectorAll('.filiale-delete-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const filialeId = this.dataset.filialeId;
            if (!filialeId) return;
            pendingId = filialeId;
            modal.style.display = 'flex';
        });
    });
});
</script>

<?php } ?>
<?php
$is_welcome = isset($_GET['welcome']);
$profile_complete = !empty($adv->address) && !empty($adv->lat) && !empty($adv->lng);
$has_ad = $ad_count > 0;
$public_url = home_url('/business/' . $adv->slug);
?>
<?php if ($is_welcome): ?>
<div class="bz-card" style="background:linear-gradient(135deg,#dcfce7,#bbf7d0); border-color:#86efac;">
  <h1 class="bz-h1" style="color:#166534;"><i class="ri-checkbox-circle-fill"></i> <?php echo esc_html(sprintf(PPV_Lang::t('biz_welcome_title'), $adv->business_name)); ?></h1>
  <p style="margin:0; color:#166534;"><?php echo esc_html(PPV_Lang::t('biz_welcome_msg')); ?></p>
</div>
<?php endif; ?>

<div class="bz-card" style="display:flex; align-items:center; gap:14px;">
  <div style="width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--pp),var(--pp2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:700; flex-shrink:0;">
    <?php echo esc_html(mb_strtoupper(mb_substr($adv->business_name, 0, 1))); ?>
  </div>
  <div style="flex:1; min-width:0;">
    <h1 class="bz-h1" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html($adv->business_name); ?></h1>
    <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:var(--muted); flex-wrap:wrap;">
      <span class="bz-tag <?php echo esc_attr($tier); ?>"><?php echo esc_html(strtoupper($tier)); ?></span>
      <?php if ($adv->subscription_status === 'trial'): ?>
        <span><i class="ri-time-line"></i> <?php echo esc_html(PPV_Lang::t('biz_trial_label')); ?> <strong><?php echo (int)$days_left; ?> <?php echo esc_html(PPV_Lang::t('biz_trial_days')); ?></strong></span>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="bz-grid">
  <div class="bz-card">
    <h2 class="bz-h2"><i class="ri-megaphone-line"></i> <?php echo esc_html(PPV_Lang::t('biz_stat_ads')); ?></h2>
    <div style="font-size:26px; font-weight:700; line-height:1;"><?php echo $ad_count; ?><span style="font-size:13px; color:var(--muted); font-weight:500;">/<?php echo $tier_info['ads']; ?></span></div>
  </div>
  <div class="bz-card">
    <h2 class="bz-h2"><i class="ri-group-line"></i> <?php echo esc_html(PPV_Lang::t('biz_stat_followers')); ?></h2>
    <div style="font-size:26px; font-weight:700; line-height:1;"><?php echo $followers; ?></div>
  </div>
  <div class="bz-card">
    <h2 class="bz-h2"><i class="ri-notification-3-line"></i> <?php echo esc_html(PPV_Lang::t('biz_stat_push_month')); ?></h2>
    <div style="font-size:26px; font-weight:700; line-height:1;"><?php echo (int)$adv->push_used_this_month; ?><span style="font-size:13px; color:var(--muted); font-weight:500;">/<?php echo $tier_info['push_per_month']; ?></span></div>
  </div>
</div>

<div class="bz-card">
  <h2 class="bz-h2"><i class="ri-rocket-line"></i> <?php echo esc_html(PPV_Lang::t('biz_quick_steps')); ?></h2>
  <div style="display:flex; flex-direction:column; gap:8px;">
    <a href="<?php echo esc_url(home_url('/business/admin/profile')); ?>" style="display:flex; align-items:center; gap:10px; padding:12px; border-radius:10px; background:<?php echo $profile_complete ? '#dcfce7' : '#fef3c7'; ?>; text-decoration:none; color:var(--text); transition:transform .15s;" onmousedown="this.style.transform='scale(.98)'" onmouseup="this.style.transform=''">
      <i class="<?php echo $profile_complete ? 'ri-checkbox-circle-fill' : 'ri-map-pin-line'; ?>" style="font-size:22px; color:<?php echo $profile_complete ? '#16a34a' : '#d97706'; ?>;"></i>
      <div style="flex:1;">
        <div style="font-weight:600; font-size:13px;"><?php echo esc_html(PPV_Lang::t('biz_step_profile_title')); ?></div>
        <div style="font-size:11px; color:var(--muted);"><?php echo esc_html($profile_complete ? PPV_Lang::t('biz_step_profile_done') : PPV_Lang::t('biz_step_profile_todo')); ?></div>
      </div>
      <i class="ri-arrow-right-s-line" style="color:var(--muted);"></i>
    </a>
    <a href="<?php echo esc_url(home_url('/business/admin/ads')); ?>" style="display:flex; align-items:center; gap:10px; padding:12px; border-radius:10px; background:<?php echo $has_ad ? '#dcfce7' : '#fef3c7'; ?>; text-decoration:none; color:var(--text);">
      <i class="<?php echo $has_ad ? 'ri-checkbox-circle-fill' : 'ri-megaphone-line'; ?>" style="font-size:22px; color:<?php echo $has_ad ? '#16a34a' : '#d97706'; ?>;"></i>
      <div style="flex:1;">
        <div style="font-weight:600; font-size:13px;"><?php echo esc_html(PPV_Lang::t('biz_step_first_ad_title')); ?></div>
        <div style="font-size:11px; color:var(--muted);"><?php echo esc_html($has_ad ? PPV_Lang::t('biz_step_first_ad_done') : PPV_Lang::t('biz_step_first_ad_todo')); ?></div>
      </div>
      <i class="ri-arrow-right-s-line" style="color:var(--muted);"></i>
    </a>
    <div style="display:flex; align-items:center; gap:10px; padding:12px; border-radius:10px; background:#f3f4f6;">
      <i class="ri-share-line" style="font-size:22px; color:var(--pp);"></i>
      <div style="flex:1; min-width:0;">
        <div style="font-weight:600; font-size:13px;"><?php echo esc_html(PPV_Lang::t('biz_public_link')); ?></div>
        <div style="font-size:11px; color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html($public_url); ?></div>
      </div>
      <button onclick="navigator.clipboard.writeText('<?php echo esc_js($public_url); ?>'); this.innerHTML='<i class=\'ri-check-line\'></i>'; setTimeout(()=>this.innerHTML='<i class=\'ri-file-copy-line\'></i>', 1500);" style="border:none; background:#fff; padding:8px 10px; border-radius:8px; cursor:pointer; color:var(--pp);"><i class="ri-file-copy-line"></i></button>
    </div>
  </div>
</div>

<!-- ============================================================
     FLYER BONUS — moved from handler /mein-profil to business advertiser admin
     ============================================================ -->
<!-- ============================================================
     MARKETING TOOLKIT — minden tool egyhelyen a follower-szerzéshez
     ============================================================ -->
<?php
$adv_slug = $adv->slug ?? '';
$adv_url  = home_url('/business/' . $adv_slug);
$wa_text  = 'Hi! Wir sind jetzt auf PunktePass — folge uns für tägliche Aktionen, Coupons und Geschenke: ' . $adv_url;
$fb_url   = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($adv_url);
$wa_url   = 'https://wa.me/?text=' . urlencode($wa_text);
?>
<div class="bz-card" id="marketing-toolkit-card">
  <h2 class="bz-h2"><i class="ri-megaphone-line"></i> Marketing — Follower gewinnen</h2>
  <p style="margin:0 0 16px; color:var(--muted); font-size:13px;">
    Hier findest du alle Werkzeuge, um Kunden auf deine PunktePass-Seite zu bringen. Je mehr Kanäle du nutzt, desto mehr Follower bekommst du.
  </p>

  <!-- Direct link copy -->
  <div style="background:#f1f5f9; border-radius:10px; padding:14px; margin-bottom:14px;">
    <label class="bz-label" style="display:block; margin-bottom:6px; font-weight:600;">
      <i class="ri-link"></i> Dein Follow-Link
    </label>
    <div style="display:flex; gap:6px; align-items:stretch;">
      <input type="text" id="ppv-mkt-url" class="bz-input" readonly value="<?php echo esc_attr($adv_url); ?>" style="flex:1; font-family:monospace; font-size:13px;">
      <button type="button" id="ppv-mkt-copy" class="bz-btn"><i class="ri-file-copy-line"></i> Kopieren</button>
    </div>
    <small id="ppv-mkt-copy-msg" style="display:block; margin-top:6px; color:var(--muted); min-height:1em;">Teile diesen Link überall — auf Visitenkarten, im Schaufenster, im Newsletter.</small>
  </div>

  <!-- 1-tap shares -->
  <div class="bz-grid" style="margin-bottom:14px;">
    <a class="bz-btn" href="<?php echo esc_url($wa_url); ?>" target="_blank" rel="noopener" style="background:#25d366; color:#fff;">
      <i class="ri-whatsapp-fill"></i> WhatsApp teilen
    </a>
    <a class="bz-btn" href="<?php echo esc_url($fb_url); ?>" target="_blank" rel="noopener" style="background:#1877f2; color:#fff;">
      <i class="ri-facebook-circle-fill"></i> Facebook teilen
    </a>
  </div>

  <!-- Social image -->
  <div style="background:linear-gradient(135deg,#6366f1,#8b5cf6); border-radius:10px; padding:14px; margin-bottom:14px;">
    <div style="color:#fff; font-weight:700; margin-bottom:4px;"><i class="ri-instagram-line"></i> Social-Media-Bild (1080×1080)</div>
    <div style="color:#fff; font-size:12px; opacity:.95; margin-bottom:10px;">Lade dieses Bild herunter und poste es auf Instagram, Facebook oder als Story. QR-Code mit deinem Link ist eingebaut.</div>
    <div class="bz-grid">
      <a class="bz-btn" style="background:#fff; color:#4338ca;" href="<?php echo esc_url(home_url('/wp-json/ppv/v1/social-image?lang=de&slug=' . urlencode($adv_slug))); ?>" target="_blank" rel="noopener">
        <i class="ri-download-line"></i> Social DE
      </a>
      <a class="bz-btn" style="background:#fff; color:#4338ca;" href="<?php echo esc_url(home_url('/wp-json/ppv/v1/social-image?lang=hu&slug=' . urlencode($adv_slug))); ?>" target="_blank" rel="noopener">
        <i class="ri-download-line"></i> Social HU
      </a>
      <a class="bz-btn" style="background:#fff; color:#4338ca;" href="<?php echo esc_url(home_url('/wp-json/ppv/v1/social-image?lang=ro&slug=' . urlencode($adv_slug))); ?>" target="_blank" rel="noopener">
        <i class="ri-download-line"></i> Social RO
      </a>
      <a class="bz-btn" style="background:#fff; color:#4338ca;" href="<?php echo esc_url(home_url('/wp-json/ppv/v1/social-image?lang=en&slug=' . urlencode($adv_slug))); ?>" target="_blank" rel="noopener">
        <i class="ri-download-line"></i> Social EN
      </a>
    </div>
  </div>

  <!-- Tips -->
  <details style="background:#fef3c7; border-radius:10px; padding:14px; cursor:pointer;">
    <summary style="font-weight:700; color:#92400e;"><i class="ri-lightbulb-line"></i> Tipps: So gewinnst du in der ersten Woche 50–100 Follower</summary>
    <ul style="margin:10px 0 0; padding-left:22px; color:#78350f; font-size:13px; line-height:1.7;">
      <li><strong>Stammkunden-Liste:</strong> Schicke deinen WhatsApp-Kontakten (Bestand) den Follow-Link mit einer kurzen Nachricht. Höchste Conversion (30–50%).</li>
      <li><strong>Schaufenster:</strong> Drucke den Flyer (DE) und klebe ihn ins Schaufenster. Auch Vorbeigehende sehen ihn 24/7.</li>
      <li><strong>An der Kasse:</strong> Stelle den Flyer auf einen Plexi-Aufsteller direkt neben die Kasse. Frage Kunden aktiv, ob sie folgen möchten — 10 Sek. Aufwand.</li>
      <li><strong>Social Media:</strong> Poste das Social-Bild 1× pro Woche auf Instagram + Facebook + Story. Reichweite = deine bestehenden Follower dort.</li>
      <li><strong>Erste Aktion ankündigen:</strong> Plane innerhalb der ersten Woche eine kleine Push-Aktion (z. B. "Heute -10% für alle Follower") — Kunden sehen sofort den Wert des Folgens.</li>
    </ul>
  </details>
</div>

<script>
(function(){
  var btn = document.getElementById('ppv-mkt-copy');
  var inp = document.getElementById('ppv-mkt-url');
  var msg = document.getElementById('ppv-mkt-copy-msg');
  if (btn && inp) {
    btn.addEventListener('click', async function(){
      try {
        await navigator.clipboard.writeText(inp.value);
        msg.style.color = '#10b981';
        msg.textContent = 'In die Zwischenablage kopiert!';
      } catch(e) {
        inp.select(); document.execCommand('copy');
        msg.style.color = '#10b981';
        msg.textContent = 'Kopiert!';
      }
      setTimeout(function(){ msg.style.color='var(--muted)'; msg.textContent='Teile diesen Link überall — auf Visitenkarten, im Schaufenster, im Newsletter.'; }, 3000);
    });
  }
})();
</script>

<div class="bz-card" id="flyer-bonus-card">
  <h2 class="bz-h2"><i class="ri-qr-code-line"></i> Personalisierter Flyer</h2>
  <p style="margin:0 0 12px; color:var(--muted); font-size:13px;">Lade deinen Flyer herunter, drucke ihn selbst aus und stelle ihn im Geschäft auf. Der QR-Code führt Kunden direkt auf deine Geschäftsseite — sie folgen dir mit 1 Tap.</p>

  <div id="ppv-slug-row" style="background:#f1f5f9; border-radius:10px; padding:12px; margin-bottom:12px;">
    <label class="bz-label" style="display:block; margin-bottom:6px; font-weight:600;">
      <i class="ri-link"></i> Deine URL — wähle einen kurzen, einprägsamen Namen
    </label>
    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
      <span style="color:#475569; font-size:14px; font-family:monospace;">punktepass.de/business/</span>
      <input type="text" id="ppv-slug-input" class="bz-input" value="<?php echo esc_attr($adv->slug ?? ''); ?>" maxlength="60" pattern="[a-z0-9-]+" style="flex:1; min-width:160px; font-family:monospace;">
      <button type="button" id="ppv-slug-save" class="bz-btn">
        <i class="ri-save-line"></i> Speichern
      </button>
    </div>
    <small id="ppv-slug-msg" style="display:block; margin-top:6px; color:var(--muted); min-height:1em;">3–60 Zeichen, nur Kleinbuchstaben/Zahlen/Bindestriche.</small>
  </div>

  <div style="padding:14px; background:linear-gradient(135deg,#f59e0b,#fbbf24); border-radius:10px;">
    <div class="bz-grid">
      <a class="bz-btn" style="background:#fff; color:#92400e;" href="<?php echo esc_url(home_url('/wp-json/ppv/v1/personalized-flyer?lang=de&slug=' . urlencode($adv->slug ?? ''))); ?>" target="_blank" rel="noopener">
        <i class="ri-download-line"></i> Flyer DE (mein QR)
      </a>
      <a class="bz-btn" style="background:#fff; color:#92400e;" href="<?php echo esc_url(home_url('/wp-json/ppv/v1/personalized-flyer?lang=ro&slug=' . urlencode($adv->slug ?? ''))); ?>" target="_blank" rel="noopener">
        <i class="ri-download-line"></i> Flyer RO (mein QR)
      </a>
    </div>
  </div>

  <form id="flyer-request-form" style="display:none; margin-top:14px; padding-top:14px; border-top:1px solid #e5e7eb;">
    <h3 class="bz-h2" style="margin-bottom:10px;"><?php echo esc_html(PPV_Lang::t('flyer_request_form_title')); ?></h3>

    <div class="bz-grid">
      <div>
        <label class="bz-label"><?php echo esc_html(PPV_Lang::t('flyer_field_name')); ?> *</label>
        <input type="text" name="name" class="bz-input" required maxlength="120">
      </div>
      <div>
        <label class="bz-label"><?php echo esc_html(PPV_Lang::t('flyer_field_business')); ?> *</label>
        <input type="text" name="business_name" class="bz-input" required maxlength="120" value="<?php echo esc_attr($adv->business_name); ?>">
      </div>
    </div>

    <label class="bz-label" style="margin-top:8px;"><?php echo esc_html(PPV_Lang::t('flyer_field_address')); ?> *</label>
    <input type="text" name="address" class="bz-input" required maxlength="200">

    <div class="bz-grid" style="margin-top:8px;">
      <div>
        <label class="bz-label"><?php echo esc_html(PPV_Lang::t('flyer_field_postcode')); ?> *</label>
        <input type="text" name="postcode" class="bz-input" required maxlength="20">
      </div>
      <div>
        <label class="bz-label"><?php echo esc_html(PPV_Lang::t('flyer_field_city')); ?> *</label>
        <input type="text" name="city" class="bz-input" required maxlength="80">
      </div>
    </div>

    <div class="bz-grid" style="margin-top:8px;">
      <div>
        <label class="bz-label"><?php echo esc_html(PPV_Lang::t('flyer_field_country')); ?> *</label>
        <select name="country" class="bz-input" required>
          <option value="DE">Deutschland</option>
          <option value="AT">Österreich</option>
          <option value="CH">Schweiz</option>
          <option value="HU">Magyarország</option>
          <option value="RO">România</option>
        </select>
      </div>
      <input type="hidden" name="quantity" value="1">
    </div>

    <label class="bz-label" style="margin-top:8px;"><?php echo esc_html(PPV_Lang::t('flyer_field_language')); ?></label>
    <select name="language" class="bz-input">
      <option value="de">Deutsch</option>
      <option value="hu">Magyar</option>
      <option value="ro">Română</option>
      <option value="en">English</option>
    </select>

    <label class="bz-label" style="margin-top:8px;"><?php echo esc_html(PPV_Lang::t('flyer_field_message')); ?></label>
    <textarea name="message" class="bz-textarea" maxlength="500" rows="3"></textarea>

    <div style="margin-top:14px; display:flex; gap:8px; flex-wrap:wrap;">
      <button type="submit" class="bz-btn"><i class="ri-send-plane-line"></i> <?php echo esc_html(PPV_Lang::t('flyer_submit')); ?></button>
      <button type="button" id="flyer-request-cancel" class="bz-btn secondary"><?php echo esc_html(PPV_Lang::t('flyer_cancel')); ?></button>
    </div>
    <div id="flyer-request-error" class="bz-msg err" style="display:none; margin-top:10px;"></div>
  </form>

  <div id="flyer-request-success" style="display:none; margin-top:14px; padding:14px; background:#dcfce7; border:1px solid #86efac; border-radius:10px; color:#166534;">
    <strong><?php echo esc_html(PPV_Lang::t('flyer_success')); ?></strong>
  </div>
</div>

<script>
// Slug edit handler
(function(){
  var input = document.getElementById('ppv-slug-input');
  var btn   = document.getElementById('ppv-slug-save');
  var msg   = document.getElementById('ppv-slug-msg');
  if (!btn || !input) return;
  btn.addEventListener('click', async function(){
    var v = (input.value || '').trim().toLowerCase().replace(/[^a-z0-9-]/g,'-').replace(/-+/g,'-').replace(/^-+|-+$/g,'');
    input.value = v;
    if (v.length < 3 || v.length > 60) { msg.style.color = '#dc2626'; msg.textContent = '3-60 Zeichen, nur Kleinbuchstaben/Zahlen/Bindestriche.'; return; }
    btn.disabled = true; msg.style.color = '#475569'; msg.textContent = '…';
    try {
      var r = await fetch('/wp-json/punktepass/v1/update-slug', {
        method:'POST', credentials:'include',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({slug: v})
      });
      var j = await r.json();
      if (j && j.success) {
        msg.style.color = '#10b981'; msg.textContent = 'Gespeichert! Neue URL: punktepass.de/business/' + j.slug;
        // refresh download links so the flyer URL uses the new slug
        document.querySelectorAll('a[href*="personalized-flyer"]').forEach(function(a){
          a.href = a.href.replace(/slug=[^&]*/, 'slug=' + encodeURIComponent(j.slug));
        });
      } else {
        msg.style.color = '#dc2626';
        if (j && j.msg === 'taken')    msg.textContent = 'Diese URL ist bereits vergeben.';
        else if (j && j.msg === 'reserved') msg.textContent = 'Dieser Name ist reserviert.';
        else if (j && j.msg === 'length')   msg.textContent = '3-60 Zeichen erforderlich.';
        else msg.textContent = (j && j.msg) ? j.msg : 'Fehler.';
      }
    } catch(e) { msg.style.color = '#dc2626'; msg.textContent = String(e); }
    finally { btn.disabled = false; }
  });
})();

(function(){
  var toggleBtn = document.getElementById('flyer-request-toggle');
  var form      = document.getElementById('flyer-request-form');
  var cancelBtn = document.getElementById('flyer-request-cancel');
  var success   = document.getElementById('flyer-request-success');
  var errBox    = document.getElementById('flyer-request-error');
  if (!toggleBtn || !form) return;

  toggleBtn.addEventListener('click', function(){
    form.style.display = (form.style.display === 'none' || !form.style.display) ? 'block' : 'none';
    if (form.style.display === 'block') {
      var firstField = form.querySelector('input[name="name"]');
      if (firstField) firstField.focus();
    }
  });
  cancelBtn.addEventListener('click', function(){ form.style.display = 'none'; errBox.style.display='none'; });

  form.addEventListener('submit', function(e){
    e.preventDefault();
    errBox.style.display = 'none';
    var fd = new FormData(form);
    var payload = {};
    fd.forEach(function(v,k){ payload[k] = v; });

    var submitBtn = form.querySelector('button[type=submit]');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.style.opacity = '0.6'; }

    fetch('<?php echo esc_url(rest_url('punktepass/v1/flyer-request')); ?>', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type':'application/json','X-WP-Nonce':'<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'},
      body: JSON.stringify(payload)
    }).then(function(r){ return r.json().then(function(j){ return {ok:r.ok, data:j}; }); })
      .then(function(res){
        if (res.ok && res.data && res.data.success) {
          form.style.display = 'none';
          toggleBtn.style.display = 'none';
          success.style.display = 'block';
        } else {
          errBox.textContent = (res.data && res.data.message) ? res.data.message : '<?php echo esc_js(PPV_Lang::t('flyer_error_generic')); ?>';
          errBox.style.display = 'block';
        }
      })
      .catch(function(){
        errBox.textContent = '<?php echo esc_js(PPV_Lang::t('flyer_error_generic')); ?>';
        errBox.style.display = 'block';
      })
      .finally(function(){
        if (submitBtn) { submitBtn.disabled = false; submitBtn.style.opacity = ''; }
      });
  });
})();
</script>
