<?php
if (!defined('ABSPATH')) exit;

// Get current advertiser and their parent/main filiale ID
$adv = PPV_Advertisers::current_advertiser();
if (!$adv) {
    echo "<p>Authentication error.</p>";
    return;
}
$parent_id = $adv->parent_advertiser_id ?: $adv->id;

global $wpdb;

// Fetch all sibling filialen + the parent for the dropdown
$filialen_for_copy = $wpdb->get_results($wpdb->prepare(
    "SELECT id, filiale_label, business_name, address, city, country, postcode, phone 
    FROM {$wpdb->prefix}ppv_advertisers 
    WHERE (id = %d OR parent_advertiser_id = %d) AND is_active = 1",
    $parent_id,
    $parent_id
));

// Fetch the main parent advertiser details to pre-fill the business name
$parent_advertiser = $wpdb->get_row($wpdb->prepare("SELECT business_name FROM {$wpdb->prefix}ppv_advertisers WHERE id = %d", $parent_id));

?>

<div class="bz-card">
    <h1 class="bz-h1"><i class="ri-store-2-line"></i> <?php echo PPV_Lang::t('biz_filiale_new_title', 'Új fiók hozzáadása'); ?></h1>
    <p style="margin-top:-8px; margin-bottom:16px; color:var(--muted); font-size:13px;">Itt adhatsz hozzá új telephelyet a fiókodhoz. A fiókok külön kezelhetők, de egy előfizetés alá tartoznak.</p>

    <?php if (!empty($_GET['err'])):
        $err = sanitize_text_field($_GET['err']);
        $msg_map = [
            'missing_fields' => 'Hiányzó kötelező mezők (név, cím, város, fiók-név mind kötelező).',
            'pin_too_close' => 'A megadott GPS-koordinátához túl közel van egy másik fiók.',
            'db_error' => 'Adatbázis hiba: ' . sanitize_text_field($_GET['msg'] ?? ''),
            'auth' => 'Bejelentkezés szükséges.',
        ];
        $msg = $msg_map[$err] ?? "Hiba: {$err}";
    ?>
    <div class="bz-msg err" style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:8px;margin-bottom:14px;border:1px solid #fecaca;">
        <strong>⚠ <?php echo esc_html($msg); ?></strong>
    </div>
    <?php endif; ?>

    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="ppv_advertiser_filiale_create">
        <?php wp_nonce_field('ppv_advertiser_filiale_create_nonce'); ?>

        <!-- Copy data from another filiale -->
        <div style="margin-bottom: 20px;">
            <label for="copy_from" class="bz-label"><?php echo PPV_Lang::t('biz_filiale_copy_from', 'Adatok átvétele másik filialéből'); ?></label>
            <select id="copy_from" class="bz-input">
                <option value=""><?php echo PPV_Lang::t('biz_filiale_copy_select_placeholder', '-- Válassz egy fiókot --'); ?></option>
                <?php foreach ($filialen_for_copy as $filiale_option): ?>
                    <option value="<?php echo esc_attr($filiale_option->id); ?>" data-details="<?php echo esc_attr(json_encode($filiale_option)); ?>">
                        <?php echo esc_html($filiale_option->filiale_label ?: $filiale_option->business_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Form Fields -->
        <div class="bz-grid">
            <div>
                <label for="filiale_label" class="bz-label"><?php echo PPV_Lang::t('biz_filiale_label', 'Fiók neve (pl. "Pécs Pláza")'); ?> *</label>
                <input type="text" id="filiale_label" name="filiale_label" class="bz-input" required>
            </div>
            <div>
                <label for="business_name" class="bz-label"><?php echo PPV_Lang::t('biz_filiale_business_name', 'Cégnév'); ?> *</label>
                <input type="text" id="business_name" name="business_name" class="bz-input" value="<?php echo esc_attr($parent_advertiser->business_name ?? ''); ?>" required>
            </div>
        </div>

        <div style="margin-top: 12px;">
            <label for="address" class="bz-label"><?php echo PPV_Lang::t('address', 'Cím'); ?> *</label>
            <input type="text" id="address" name="address" class="bz-input" required>
        </div>

        <div class="bz-grid" style="margin-top: 12px;">
            <div>
                <label for="postcode" class="bz-label"><?php echo PPV_Lang::t('postcode', 'Irányítószám'); ?></label>
                <input type="text" id="postcode" name="postcode" class="bz-input">
            </div>
            <div>
                <label for="city" class="bz-label"><?php echo PPV_Lang::t('city', 'Város'); ?> *</label>
                <input type="text" id="city" name="city" class="bz-input" required>
            </div>
        </div>
        
        <div class="bz-grid" style="margin-top: 12px;">
             <div>
                <label for="country" class="bz-label"><?php echo PPV_Lang::t('country', 'Ország'); ?></label>
                <input type="text" id="country" name="country" class="bz-input">
            </div>
            <div>
                <label for="phone" class="bz-label"><?php echo PPV_Lang::t('phone', 'Telefonszám'); ?></label>
                <input type="text" id="phone" name="phone" class="bz-input">
            </div>
        </div>
        
        <div class="bz-grid" style="margin-top: 12px; display:none;">
            <div>
                <label for="lat" class="bz-label">Latitude</label>
                <input type="text" id="lat" name="lat" class="bz-input">
            </div>
            <div>
                <label for="lng" class="bz-label">Longitude</label>
                <input type="text" id="lng" name="lng" class="bz-input">
            </div>
        </div>

        <div style="margin-top: 20px; display: flex; gap: 8px; align-items: center;">
            <button type="submit" class="bz-btn"><?php echo PPV_Lang::t('biz_filiale_new_submit', 'Fiók létrehozása'); ?></button>
            <a href="<?php echo esc_url(home_url('/business/admin/')); ?>" class="bz-btn secondary"><?php echo PPV_Lang::t('biz_cancel', 'Mégse'); ?></a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const copyFrom = document.getElementById('copy_from');
    if (!copyFrom) return;

    copyFrom.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (!selectedOption.value) {
            return;
        }

        try {
            const details = JSON.parse(selectedOption.getAttribute('data-details'));

            // Set values, but exclude specific fields as requested
            // Excluded: filiale_label, address, lat, lng
            document.getElementById('business_name').value = details.business_name || '';
            document.getElementById('city').value = details.city || '';
            document.getElementById('country').value = details.country || '';
            document.getElementById('postcode').value = details.postcode || '';
            document.getElementById('phone').value = details.phone || '';
            
            // Clear the fields that must be unique per filiale
            document.getElementById('filiale_label').value = '';
            document.getElementById('address').value = '';
            document.getElementById('lat').value = '';
            document.getElementById('lng').value = '';
            
            // Focus on the first field that needs to be filled
            document.getElementById('filiale_label').focus();

        } catch (e) {
            console.error('Error parsing filiale details:', e);
        }
    });
});
</script>
