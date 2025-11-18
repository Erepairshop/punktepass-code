<?php
/**
 * PunktePass – Admin Profil Form Template (Optimized v2.0)
 * 
 * Jellemzők:
 * - Deduplikált field rendering
 * - Helper függvények
 * - Validációs hints
 * - i18n támogatás
 */

if (!defined('ABSPATH')) exit;

// Fallback ha nincs $store
if (empty($store)) {
    echo '<div class="ppv-error">' . esc_html__('Profil-Daten nicht verfügbar', 'ppv') . '</div>';
    return;
}

// ==================== KATEGÓRIA LISTA ====================
$categories = [
    'handy'       => __('Handyreparatur', 'ppv'),
    'cafe'        => __('Café', 'ppv'),
    'friseur'     => __('Friseur', 'ppv'),
    'mode'        => __('Mode & Accessoires', 'ppv'),
    'fitness'     => __('Fitness', 'ppv'),
    'elektronik'  => __('Elektronik', 'ppv'),
    'sonstiges'   => __('Sonstiges', 'ppv'),
];

$categories = apply_filters('ppv_store_categories', $categories);

// ==================== HELPER FUNCTIONS ====================
function ppv_input($name, $label, $value = '', $required = false, $type = 'text', $placeholder = '') {
    $required_attr = $required ? 'required' : '';
    $required_mark = $required ? ' <span class="ppv-required">*</span>' : '';
    ?>
    <p class="ppv-field">
        <label for="<?php echo esc_attr($name); ?>">
            <?php echo esc_html($label); echo $required_mark; ?>
        </label>
        <input 
            type="<?php echo esc_attr($type); ?>" 
            id="<?php echo esc_attr($name); ?>" 
            name="<?php echo esc_attr($name); ?>" 
            value="<?php echo esc_attr($value); ?>"
            placeholder="<?php echo esc_attr($placeholder); ?>"
            <?php echo $required_attr; ?>
        >
    </p>
    <?php
}

function ppv_textarea($name, $label, $value = '', $required = false, $rows = 3) {
    $required_attr = $required ? 'required' : '';
    $required_mark = $required ? ' <span class="ppv-required">*</span>' : '';
    ?>
    <p class="ppv-field">
        <label for="<?php echo esc_attr($name); ?>">
            <?php echo esc_html($label); echo $required_mark; ?>
        </label>
        <textarea 
            id="<?php echo esc_attr($name); ?}" 
            name="<?php echo esc_attr($name); ?}" 
            rows="<?php echo intval($rows); ?>"
            <?php echo $required_attr; ?>
        ><?php echo esc_textarea($value); ?></textarea>
    </p>
    <?php
}

function ppv_select($name, $label, $options, $value = '', $required = false) {
    $required_attr = $required ? 'required' : '';
    $required_mark = $required ? ' <span class="ppv-required">*</span>' : '';
    ?>
    <p class="ppv-field">
        <label for="<?php echo esc_attr($name); ?>">
            <?php echo esc_html($label); echo $required_mark; ?>
        </label>
        <select 
            id="<?php echo esc_attr($name); ?}" 
            name="<?php echo esc_attr($name); ?}"
            <?php echo $required_attr; ?>
        >
            <option value="">-- <?php esc_html_e('Bitte wählen', 'ppv'); ?> --</option>
            <?php foreach ($options as $key => $text): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                    <?php echo esc_html($text); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php
}

function ppv_file_input($name, $label, $current_url = '', $preview = true) {
    ?>
    <p class="ppv-field">
        <label for="<?php echo esc_attr($name); ?>">
            <?php echo esc_html($label); ?>
        </label>
        <input type="file" id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>">
        
        <?php if (!empty($current_url) && $preview): ?>
            <div class="ppv-img-wrapper" style="margin-top: 10px;">
                <img src="<?php echo esc_url($current_url); ?>" class="ppv-preview-img" alt="<?php echo esc_attr($label); ?>">
                <button 
                    type="button" 
                    class="ppv-delete-img" 
                    data-field="<?php echo esc_attr($name); ?>" 
                    data-url="<?php echo esc_url($current_url); ?>"
                    title="<?php esc_attr_e('Löschen', 'ppv'); ?>"
                >×</button>
            </div>
        <?php endif; ?>
    </p>
    <?php
}

// ==================== FORM START ====================
?>

<div class="ppv-back-bar">
    <a href="/handler_dashboard" class="ppv-back-link">← <?php esc_html_e('Zurück', 'ppv'); ?></a>
</div>

<form method="post" enctype="multipart/form-data" class="ppv-profile-form" id="ppv-profile-form">
    <?php wp_nonce_field('ppv_save_profile', 'ppv_nonce'); ?>
    <input type="hidden" name="action" value="ppv_save_profile">

    <!-- ==================== TABS ==================== -->
    <div class="ppv-tabs">
        <button type="button" class="ppv-tab active" data-tab="allgemein">
            <?php esc_html_e('Allgemein', 'ppv'); ?>
        </button>
        <button type="button" class="ppv-tab" data-tab="zeiten">
            <?php esc_html_e('Öffnungszeiten', 'ppv'); ?>
        </button>
        <button type="button" class="ppv-tab" data-tab="bilder">
            <?php esc_html_e('Bilder & Medien', 'ppv'); ?>
        </button>
        <button type="button" class="ppv-tab" data-tab="kontakt">
            <?php esc_html_e('Kontakt & Social', 'ppv'); ?>
        </button>
        <button type="button" class="ppv-tab" data-tab="vorschau">
            <?php esc_html_e('Vorschau', 'ppv'); ?>
        </button>
        <button type="button" class="ppv-tab" data-tab="einstellungen">
            <?php esc_html_e('Einstellungen', 'ppv'); ?>
        </button>
    </div>

    <!-- ==================== TAB: ALLGEMEIN ==================== -->
    <div class="ppv-tab-content active" id="tab-allgemein">
        <h3><?php esc_html_e('Allgemeine Informationen', 'ppv'); ?></h3>

        <?php 
        ppv_input('store_name', __('Store Name', 'ppv'), $store->name ?? '', true);
        ppv_input('store_slogan', __('Slogan', 'ppv'), $store->slogan ?? '', false, 'text', __('z. B. Schnell. Fair. Lokal.', 'ppv'));
        ppv_select('store_category', __('Kategorie', 'ppv'), $categories, $store->category ?? '');
        ?>

        <hr>
        <h4><?php esc_html_e('Adresse', 'ppv'); ?></h4>

        <?php 
        ppv_input('store_address', __('Straße & Hausnummer', 'ppv'), $store->address ?? '');
        ?>

        <div class="ppv-3col">
            <?php 
            ppv_input('store_plz', __('PLZ', 'ppv'), $store->plz ?? '');
            ppv_input('store_city', __('Stadt', 'ppv'), $store->city ?? '');
            ppv_input('store_country', __('Land', 'ppv'), $store->country ?? '');
            ?>
        </div>

        <hr>
        <h4><?php esc_html_e('Unternehmen', 'ppv'); ?></h4>

        <?php 
        ppv_input('company_name', __('Firmenname', 'ppv'), $store->company_name ?? '');
        ppv_input('contact_person', __('Kontaktperson', 'ppv'), $store->contact_person ?? '');
        ppv_textarea('store_description', __('Beschreibung', 'ppv'), $store->description ?? '', false, 4);
        ?>
    </div>

    <!-- ==================== TAB: ÖFFNUNGSZEITEN ==================== -->
    <div class="ppv-tab-content" id="tab-zeiten">
        <h3><?php esc_html_e('Öffnungszeiten', 'ppv'); ?></h3>
        
        <table class="ppv-opening-hours">
            <thead>
                <tr>
                    <th><?php esc_html_e('Tag', 'ppv'); ?></th>
                    <th><?php esc_html_e('Von', 'ppv'); ?></th>
                    <th><?php esc_html_e('Bis', 'ppv'); ?></th>
                    <th><?php esc_html_e('Status', 'ppv'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $days = [
                'mo' => __('Montag', 'ppv'),
                'di' => __('Dienstag', 'ppv'),
                'mi' => __('Mittwoch', 'ppv'),
                'do' => __('Donnerstag', 'ppv'),
                'fr' => __('Freitag', 'ppv'),
                'sa' => __('Samstag', 'ppv'),
                'so' => __('Sonntag', 'ppv'),
            ];
            
            $zeiten = !empty($store->zeiten) ? json_decode($store->zeiten, true) : [];
            
            foreach ($days as $key => $label):
                $von = $zeiten[$key]['von'] ?? '';
                $bis = $zeiten[$key]['bis'] ?? '';
                $closed = !empty($zeiten[$key]['closed']);
            ?>
                <tr class="ppv-day-row <?php echo $closed ? 'closed' : ''; ?>">
                    <td><?php echo esc_html($label); ?></td>
                    <td>
                        <input 
                            type="time" 
                            name="zeiten[<?php echo esc_attr($key); ?>][von]" 
                            value="<?php echo esc_attr($von); ?>"
                            class="ppv-time-input"
                            <?php echo $closed ? 'disabled' : ''; ?>
                        >
                    </td>
                    <td>
                        <input 
                            type="time" 
                            name="zeiten[<?php echo esc_attr($key); ?>][bis]" 
                            value="<?php echo esc_attr($bis); ?>"
                            class="ppv-time-input"
                            <?php echo $closed ? 'disabled' : ''; ?>
                        >
                    </td>
                    <td>
                        <label class="ppv-checkbox">
                            <input 
                                type="checkbox" 
                                name="zeiten[<?php echo esc_attr($key); ?>][closed]" 
                                value="1"
                                class="ppv-closed-toggle"
                                data-day="<?php echo esc_attr($key); ?>"
                                <?php checked($closed, true); ?>
                            >
                            <?php esc_html_e('Geschlossen', 'ppv'); ?>
                        </label>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ==================== TAB: BILDER ==================== -->
    <div class="ppv-tab-content" id="tab-bilder">
        <h3><?php esc_html_e('Bilder & Medien', 'ppv'); ?></h3>

        <?php 
        ppv_file_input('store_logo', __('Logo', 'ppv'), $store->logo ?? '', true);
        ppv_file_input('store_cover', __('Titelbild', 'ppv'), $store->cover ?? '', true);
        ?>

        <hr>
        <h4><?php esc_html_e('Galerie', 'ppv'); ?></h4>
        <p class="ppv-field">
            <button type="button" id="ppv-add-gallery-btn" class="ppv-btn-secondary">
                + <?php esc_html_e('Bilder hinzufügen', 'ppv'); ?>
            </button>
            <input type="file" id="ppv-gallery-input" name="store_gallery[]" multiple accept="image/*" style="display:none;">
        </p>

        <div class="ppv-gallery-grid">
            <?php
            if (!empty($store->gallery)):
                $gallery = json_decode($store->gallery, true);
                if (is_array($gallery)):
                    foreach ($gallery as $img_url):
            ?>
                <div class="ppv-gallery-item">
                    <img src="<?php echo esc_url($img_url); ?>" alt="Gallery">
                    <button 
                        type="button" 
                        class="ppv-delete-img" 
                        data-field="gallery" 
                        data-url="<?php echo esc_url($img_url); ?>"
                    >×</button>
                </div>
            <?php 
                    endforeach;
                endif;
            endif;
            ?>
        </div>
    </div>

    <!-- ==================== TAB: KONTAKT & SOCIAL ==================== -->
    <div class="ppv-tab-content" id="tab-kontakt">
        <h3><?php esc_html_e('Kontaktdaten', 'ppv'); ?></h3>

        <?php 
        ppv_input('store_phone', __('Telefon', 'ppv'), $store->phone ?? '', false, 'tel');
        ppv_input('store_email', __('E-Mail', 'ppv'), $store->email ?? '', false, 'email');
        ppv_input('store_website', __('Webseite', 'ppv'), $store->website ?? '', false, 'url');
        ppv_input('store_whatsapp', __('WhatsApp', 'ppv'), $store->whatsapp ?? '', false, 'text', '+49...');
        ?>

        <hr>
        <h3><?php esc_html_e('Soziale Netzwerke', 'ppv'); ?></h3>

        <?php 
        ppv_input('store_facebook', __('Facebook URL', 'ppv'), $store->facebook ?? '', false, 'url');
        ppv_input('store_instagram', __('Instagram URL', 'ppv'), $store->instagram ?? '', false, 'url');
        ppv_input('store_tiktok', __('TikTok URL', 'ppv'), $store->tiktok ?? '', false, 'url');
        ?>
    </div>

    <!-- ==================== TAB: VORSCHAU ==================== -->
    <div class="ppv-tab-content" id="tab-vorschau">
        <?php echo PPV_Profile_Lite::render_vorschau($store); ?>
    </div>

    <!-- ==================== TAB: EINSTELLUNGEN ==================== -->
    <div class="ppv-tab-content" id="tab-einstellungen">
        <h3><?php esc_html_e('Profil-Einstellungen', 'ppv'); ?></h3>

        <label class="ppv-checkbox">
            <input type="checkbox" name="store_active" value="1" <?php checked($store->active ?? 0, 1); ?>>
            <?php esc_html_e('Store ist aktiv', 'ppv'); ?>
        </label>

        <label class="ppv-checkbox">
            <input type="checkbox" name="store_visible" value="1" <?php checked($store->visible ?? 0, 1); ?>>
            <?php esc_html_e('Öffentlich sichtbar', 'ppv'); ?>
        </label>

        <label class="ppv-checkbox">
            <input type="checkbox" name="pos_enabled" value="1" <?php checked($store->pos_enabled ?? 0, 1); ?>>
            <?php esc_html_e('POS-System aktiviert', 'ppv'); ?>
        </label>

        <?php 
        if (file_exists(PPV_PLUGIN_DIR . 'includes/pp-profile-settings.php')) {
            include PPV_PLUGIN_DIR . 'includes/pp-profile-settings.php';
        } else {
            echo '<p style="color:#999;margin-top:20px;">' . esc_html__('Weitere Einstellungen werden bald verfügbar…', 'ppv') . '</p>';
        }
        ?>
    </div>

    <!-- ==================== SAVE BAR ==================== -->
    <div class="ppv-save-bar">
        <button type="submit" class="ppv-btn ppv-btn-primary">
            💾 <?php esc_html_e('Speichern', 'ppv'); ?>
        </button>
    </div>

    <div id="ppv-profile-message" class="ppv-message-bottom"></div>
</form>