<?php
/**
 * PunktePass Profile Lite - Tab Rendering Module
 * Contains all render_tab_* functions
 */

if (!defined('ABSPATH')) exit;

class PPV_Profile_Tabs {

public static function render_tab_general($store) {
    ob_start();
    ?>
    <div class="ppv-tab-content active" id="tab-general">
        <h2 data-i18n="general_info"><?php echo esc_html(PPV_Lang::t('general_info')); ?></h2>

        <!-- STORE NAME -->
        <div class="ppv-form-group">
            <label data-i18n="store_name"><?php echo esc_html(PPV_Lang::t('store_name')); ?> *</label>
            <input type="text" name="store_name" value="<?php echo esc_attr($store->name ?? ''); ?>" required>
        </div>

        <!-- SLOGAN -->
        <div class="ppv-form-group">
            <label data-i18n="slogan"><?php echo esc_html(PPV_Lang::t('slogan')); ?></label>
            <input type="text" name="slogan" value="<?php echo esc_attr($store->slogan ?? ''); ?>">
        </div>

        <!-- CATEGORY -->
        <div class="ppv-form-group">
            <label data-i18n="category"><?php echo esc_html(PPV_Lang::t('category')); ?></label>
            <select name="category">
                <option value="" data-i18n="category_select"><?php echo esc_html(PPV_Lang::t('category_select')); ?></option>
                <option value="cafe" <?php selected($store->category ?? '', 'cafe'); ?> data-i18n="category_cafe"><?php echo esc_html(PPV_Lang::t('category_cafe')); ?></option>
                <option value="restaurant" <?php selected($store->category ?? '', 'restaurant'); ?> data-i18n="category_restaurant"><?php echo esc_html(PPV_Lang::t('category_restaurant')); ?></option>
                <option value="friseur" <?php selected($store->category ?? '', 'friseur'); ?> data-i18n="category_friseur"><?php echo esc_html(PPV_Lang::t('category_friseur')); ?></option>
                <option value="beauty" <?php selected($store->category ?? '', 'beauty'); ?> data-i18n="category_beauty"><?php echo esc_html(PPV_Lang::t('category_beauty')); ?></option>
                <option value="mode" <?php selected($store->category ?? '', 'mode'); ?> data-i18n="category_mode"><?php echo esc_html(PPV_Lang::t('category_mode')); ?></option>
                <option value="fitness" <?php selected($store->category ?? '', 'fitness'); ?> data-i18n="category_fitness"><?php echo esc_html(PPV_Lang::t('category_fitness')); ?></option>
                <option value="pharmacy" <?php selected($store->category ?? '', 'pharmacy'); ?> data-i18n="category_pharmacy"><?php echo esc_html(PPV_Lang::t('category_pharmacy')); ?></option>
                <option value="sportshop" <?php selected($store->category ?? '', 'sportshop'); ?> data-i18n="category_sportshop"><?php echo esc_html(PPV_Lang::t('category_sportshop')); ?></option>
                <option value="other" <?php selected($store->category ?? '', 'other'); ?> data-i18n="category_other"><?php echo esc_html(PPV_Lang::t('category_other')); ?></option>
            </select>
        </div>

        <hr>

        <!-- ============================================================
             ✅ ÚJ SZEKCIÓ: ORSZÁG ÉS ADÓ INFORMÁCIÓK
             ============================================================ -->
        <h3 data-i18n="country_tax_section"><?php echo esc_html(PPV_Lang::t('country_tax_section')); ?></h3>

        <!-- ORSZÁG KIVÁLASZTÁS -->
        <div class="ppv-form-group">
            <label data-i18n="country" style="font-weight: 600;"><?php echo esc_html(PPV_Lang::t('country')); ?> *</label>
            <select name="country" required>
                <option value="">-- <?php echo esc_html(PPV_Lang::t('country_select')); ?> --</option>
                <option value="DE" <?php selected($store->country ?? '', 'DE'); ?> data-i18n="country_de">🇩🇪 <?php echo esc_html(PPV_Lang::t('country_de')); ?></option>
                <option value="HU" <?php selected($store->country ?? '', 'HU'); ?> data-i18n="country_hu">🇭🇺 <?php echo esc_html(PPV_Lang::t('country_hu')); ?></option>
                <option value="RO" <?php selected($store->country ?? '', 'RO'); ?> data-i18n="country_ro">🇷🇴 <?php echo esc_html(PPV_Lang::t('country_ro')); ?></option>
            </select>
        </div>

        <!-- ADÓSZÁM (TAX ID) -->
        <div class="ppv-form-group">
            <label data-i18n="tax_id" style="font-weight: 600;"><?php echo esc_html(PPV_Lang::t('tax_id')); ?></label>
            <input type="text" name="tax_id" value="<?php echo esc_attr($store->tax_id ?? ''); ?>" placeholder="<?php echo esc_attr(PPV_Lang::t('tax_id_placeholder')); ?>">
            <small style="display: block; margin-top: 4px; color: #666;" data-i18n="tax_id_help">
                <?php echo esc_html(PPV_Lang::t('tax_id_help')); ?>
            </small>
        </div>

        <!-- ÁFA STATUS CHECKBOX -->
        <div class="ppv-checkbox-group">
            <label class="ppv-checkbox">
                <input type="checkbox" name="is_taxable" value="1" <?php checked($store->is_taxable ?? 1, 1); ?>>
                <strong data-i18n="is_taxable"><?php echo esc_html(PPV_Lang::t('is_taxable')); ?></strong>
                <small data-i18n="is_taxable_help"><?php echo esc_html(PPV_Lang::t('is_taxable_help')); ?></small>
            </label>
        </div>

        <hr>

        <!-- ============================================================
             MEGLÉVŐ: CÍM ADATOK
             ============================================================ -->
        <h3 data-i18n="address_section"><?php echo esc_html(PPV_Lang::t('address_section')); ?></h3>

        <!-- STREET ADDRESS -->
        <div class="ppv-form-group">
            <label data-i18n="street_address"><?php echo esc_html(PPV_Lang::t('street_address')); ?></label>
            <input type="text" name="address" value="<?php echo esc_attr($store->address ?? ''); ?>">
        </div>

        <!-- POSTAL CODE & CITY (2 columns) -->
        <div class="ppv-form-row">
            <div class="ppv-form-group">
                <label data-i18n="postal_code"><?php echo esc_html(PPV_Lang::t('postal_code')); ?></label>
                <input type="text" name="plz" value="<?php echo esc_attr($store->plz ?? ''); ?>">
            </div>
            <div class="ppv-form-group">
                <label data-i18n="city"><?php echo esc_html(PPV_Lang::t('city')); ?></label>
                <input type="text" name="city" value="<?php echo esc_attr($store->city ?? ''); ?>">
            </div>
        </div>

        <hr>

        <!-- ============================================================
             MEGLÉVŐ: HELYKOORDINÁTÁK (LOCATION)
             ============================================================ -->
        <h3 data-i18n="location_section"><?php echo esc_html(PPV_Lang::t('location_section')); ?></h3>

        <!-- LATITUDE -->
        <div class="ppv-form-group">
            <label data-i18n="latitude"><?php echo esc_html(PPV_Lang::t('latitude')); ?></label>
            <input type="number" step="0.0001" name="latitude" id="store_latitude" value="<?php echo esc_attr($store->latitude ?? ''); ?>" placeholder="47.5095">
        </div>

        <!-- LONGITUDE -->
        <div class="ppv-form-group">
            <label data-i18n="longitude"><?php echo esc_html(PPV_Lang::t('longitude')); ?></label>
            <input type="number" step="0.0001" name="longitude" id="store_longitude" value="<?php echo esc_attr($store->longitude ?? ''); ?>" placeholder="19.0408">
        </div>

        <!-- GEOCODE BUTTON -->
        <div class="ppv-form-group">
            <button type="button" id="ppv-geocode-btn" class="ppv-btn ppv-btn-secondary" style="width: 100%; margin-top: 10px;" data-i18n="geocode_button">
                🗺️ <?php echo esc_html(PPV_Lang::t('geocode_button')); ?>
            </button>
        </div>

        <!-- MAP (Google Maps / Leaflet) -->
        <div id="ppv-location-map" style="width: 100%; height: 300px; border-radius: 8px; margin-top: 12px; border: 1px solid #ddd; background: #f0f0f0;"></div>

        <hr>

        <!-- ============================================================
             MEGLÉVŐ: CÉGINFORMÁCIÓK (COMPANY)
             ============================================================ -->
        <h3 data-i18n="company_section"><?php echo esc_html(PPV_Lang::t('company_section')); ?></h3>

        <!-- COMPANY NAME -->
        <div class="ppv-form-group">
            <label data-i18n="company_name"><?php echo esc_html(PPV_Lang::t('company_name')); ?></label>
            <input type="text" name="company_name" value="<?php echo esc_attr($store->company_name ?? ''); ?>">
        </div>

        <!-- CONTACT PERSON -->
        <div class="ppv-form-group">
            <label data-i18n="contact_person"><?php echo esc_html(PPV_Lang::t('contact_person')); ?></label>
            <input type="text" name="contact_person" value="<?php echo esc_attr($store->contact_person ?? ''); ?>">
        </div>

        <!-- DESCRIPTION -->
        <div class="ppv-form-group">
            <label data-i18n="description"><?php echo esc_html(PPV_Lang::t('description')); ?></label>
            <textarea name="description"><?php echo esc_textarea($store->description ?? ''); ?></textarea>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
        public static function render_tab_hours($store) {
            ob_start();
            ?>
            <div class="ppv-tab-content" id="tab-hours">
                <h2 data-i18n="opening_hours"><?php echo esc_html(PPV_Lang::t('opening_hours')); ?></h2>
                <p data-i18n="opening_hours_info"><?php echo esc_html(PPV_Lang::t('opening_hours_info')); ?></p>

                <div class="ppv-opening-hours-wrapper" id="ppv-hours">
                    <?php
                    $days = ['mo', 'di', 'mi', 'do', 'fr', 'sa', 'so'];
                    $day_names = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                    $hours = json_decode($store->opening_hours ?? '{}', true);

                    foreach ($days as $idx => $day) {
                        $day_data = $hours[$day] ?? [];
                        $von = $day_data['von'] ?? '';
                        $bis = $day_data['bis'] ?? '';
                        $closed = $day_data['closed'] ?? 0;
                        ?>
                        <div class="ppv-hour-row">
                            <label data-i18n="<?php echo esc_attr($day_names[$idx]); ?>"><?php echo esc_html(PPV_Lang::t($day_names[$idx])); ?></label>
                            <div class="ppv-hour-inputs">
                                <input type="time" name="hours[<?php echo esc_attr($day); ?>][von]" value="<?php echo esc_attr($von); ?>">
                                <span>-</span>
                                <input type="time" name="hours[<?php echo esc_attr($day); ?>][bis]" value="<?php echo esc_attr($bis); ?>">
                                <label class="ppv-checkbox">
                                    <input type="checkbox" name="hours[<?php echo esc_attr($day); ?>][closed]" <?php checked($closed, 1); ?>>
                                    <span data-i18n="closed"><?php echo esc_html(PPV_Lang::t('closed')); ?></span>
                                </label>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }
        public static function render_tab_media($store) {
            ob_start();
            ?>
            <div class="ppv-tab-content" id="tab-media">
                <h2 data-i18n="media_section"><?php echo esc_html(PPV_Lang::t('media_section')); ?></h2>

                <div class="ppv-media-group">
                    <label data-i18n="logo"><?php echo esc_html(PPV_Lang::t('logo')); ?></label>
                    <p class="ppv-help" data-i18n="logo_info"><?php echo esc_html(PPV_Lang::t('logo_info')); ?></p>
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="ppv-file-input">
               <div id="ppv-logo-preview" class="ppv-media-preview" style="display: flex; align-items: center; justify-content: center; min-height: 120px; border: 1px solid #ddd; border-radius: 4px;">
    <?php if (!empty($store->logo)): ?>
        <img src="<?php echo esc_url($store->logo); ?>" alt="Logo" style="max-width: 100%; max-height: 100px; object-fit: contain;">
    <?php endif; ?>
</div>
                </div>

                <div class="ppv-media-group">
                    <label data-i18n="gallery"><?php echo esc_html(PPV_Lang::t('gallery')); ?></label>
                    <p class="ppv-help" data-i18n="gallery_info"><?php echo esc_html(PPV_Lang::t('gallery_info')); ?></p>
                    <input type="file" name="gallery[]" multiple accept="image/jpeg,image/png,image/webp" class="ppv-file-input">
<div id="ppv-gallery-preview" class="ppv-gallery-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-top: 10px;">
<?php 

if (!empty($store->gallery)) {
    $gallery = json_decode($store->gallery, true);
    if (is_array($gallery)) {
        foreach ($gallery as $image_url) {
            echo '<div class="ppv-gallery-item" style="position: relative; display: inline-block; width: 100%;">';
            // ✅ OPTIMIZED: Added loading="lazy" for performance
            echo '<img src="' . esc_url($image_url) . '" alt="Gallery" loading="lazy" style="width: 100%; height: auto; border-radius: 4px;">';
            echo '<button type="button" class="ppv-gallery-delete-btn" data-image-url="' . esc_attr($image_url) . '" style="position: absolute; top: -10px; right: -10px; background: red; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 18px; padding: 0; line-height: 1;">×</button>';
            echo '</div>';
        }
    }
}
?>
</div>                </div>
            </div>
            <?php
            return ob_get_clean();
        }
        public static function render_tab_contact($store) {
            ob_start();
            ?>
            <div class="ppv-tab-content" id="tab-contact">
                <h2 data-i18n="contact_data"><?php echo esc_html(PPV_Lang::t('contact_data')); ?></h2>

                <div class="ppv-form-group">
                    <label data-i18n="phone"><?php echo esc_html(PPV_Lang::t('phone')); ?></label>
                    <input type="tel" name="phone" value="<?php echo esc_attr($store->phone ?? ''); ?>">
                </div>

                <div class="ppv-form-group">
                    <label data-i18n="email"><?php echo esc_html(PPV_Lang::t('email')); ?></label>
                    <input type="email" name="email" value="<?php echo esc_attr($store->email ?? ''); ?>">
                </div>

                <div class="ppv-form-group">
                    <label data-i18n="website"><?php echo esc_html(PPV_Lang::t('website')); ?></label>
                    <input type="url" name="website" value="<?php echo esc_attr($store->website ?? ''); ?>">
                </div>

                <div class="ppv-form-group">
                    <label data-i18n="whatsapp"><?php echo esc_html(PPV_Lang::t('whatsapp')); ?></label>
                    <input type="tel" name="whatsapp" value="<?php echo esc_attr($store->whatsapp ?? ''); ?>">
                </div>

                <hr>

                <h3 data-i18n="social_media"><?php echo esc_html(PPV_Lang::t('social_media')); ?></h3>

                <div class="ppv-form-group">
                    <label data-i18n="facebook_url"><?php echo esc_html(PPV_Lang::t('facebook_url')); ?></label>
                    <input type="url" name="facebook" value="<?php echo esc_attr($store->facebook ?? ''); ?>">
                </div>

                <div class="ppv-form-group">
                    <label data-i18n="instagram_url"><?php echo esc_html(PPV_Lang::t('instagram_url')); ?></label>
                    <input type="url" name="instagram" value="<?php echo esc_attr($store->instagram ?? ''); ?>">
                </div>

                <div class="ppv-form-group">
                    <label data-i18n="tiktok_url"><?php echo esc_html(PPV_Lang::t('tiktok_url')); ?></label>
                    <input type="url" name="tiktok" value="<?php echo esc_attr($store->tiktok ?? ''); ?>">
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * ============================================================
         * MARKETING & AUTOMATION TAB
         * ============================================================
         */
        public static function render_tab_marketing($store) {
            ob_start();
            ?>
            <div class="ppv-tab-content" id="tab-marketing">
                <h2 data-i18n="marketing_automation"><?php echo esc_html(PPV_Lang::t('marketing_automation')); ?></h2>
                <p class="ppv-help" style="margin-bottom: 20px;" data-i18n="marketing_automation_help">
                    <?php echo esc_html(PPV_Lang::t('marketing_automation_help')); ?>
                </p>

                <!-- ============================================================
                     GOOGLE REVIEW REQUEST
                     ============================================================ -->
                <div class="ppv-marketing-card" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div class="ppv-marketing-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <div>
                            <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 24px;">⭐</span>
                                <span data-i18n="google_review_title"><?php echo esc_html(PPV_Lang::t('google_review_title')); ?></span>
                            </h3>
                            <small style="color: #888;" data-i18n="google_review_desc"><?php echo esc_html(PPV_Lang::t('google_review_desc')); ?></small>
                        </div>
                        <label class="ppv-toggle">
                            <input type="checkbox" name="google_review_enabled" value="1" <?php checked($store->google_review_enabled ?? 0, 1); ?>>
                            <span class="ppv-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="ppv-marketing-body" id="google-review-settings" style="<?php echo empty($store->google_review_enabled) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                        <div class="ppv-form-group">
                            <label data-i18n="google_review_url"><?php echo esc_html(PPV_Lang::t('google_review_url')); ?></label>
                            <input type="url" name="google_review_url" value="<?php echo esc_attr($store->google_review_url ?? ''); ?>" placeholder="https://g.page/r/...">
                            <small style="color: #666;" data-i18n="google_review_url_help"><?php echo esc_html(PPV_Lang::t('google_review_url_help')); ?></small>
                        </div>

                        <div class="ppv-form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="ppv-form-group">
                                <label data-i18n="google_review_threshold"><?php echo esc_html(PPV_Lang::t('google_review_threshold')); ?></label>
                                <input type="number" name="google_review_threshold" value="<?php echo esc_attr($store->google_review_threshold ?? 100); ?>" min="10" max="1000" step="10">
                            </div>
                            <div class="ppv-form-group">
                                <label data-i18n="google_review_bonus_points"><?php echo esc_html(PPV_Lang::t('google_review_bonus_points')); ?></label>
                                <input type="number" name="google_review_bonus_points" value="<?php echo esc_attr($store->google_review_bonus_points ?? 5); ?>" min="0" max="100" step="1">
                                <small style="color: #888;" data-i18n="google_review_bonus_help"><?php echo esc_html(PPV_Lang::t('google_review_bonus_help')); ?></small>
                            </div>
                        </div>

                        <!-- How it works collapsible -->
                        <details class="ppv-how-it-works" style="margin-top: 15px; background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.2); border-radius: 10px; overflow: hidden;">
                            <summary style="padding: 12px 15px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 500; color: #3b82f6; list-style: none;">
                                <span style="font-size: 16px;">💡</span>
                                <span data-i18n="google_review_how_it_works"><?php echo esc_html(PPV_Lang::t('google_review_how_it_works')); ?></span>
                                <svg style="margin-left: auto; width: 16px; height: 16px; transition: transform 0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div style="padding: 0 15px 15px 15px; color: #ccc; font-size: 13px; line-height: 1.6;">
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                                        <span style="background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">1</span>
                                        <span data-i18n="google_review_step1"><?php echo esc_html(PPV_Lang::t('google_review_step1')); ?></span>
                                    </div>
                                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                                        <span style="background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">2</span>
                                        <span data-i18n="google_review_step2"><?php echo esc_html(PPV_Lang::t('google_review_step2')); ?></span>
                                    </div>
                                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                                        <span style="background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">3</span>
                                        <span data-i18n="google_review_step3"><?php echo esc_html(PPV_Lang::t('google_review_step3')); ?></span>
                                    </div>
                                </div>
                            </div>
                        </details>
                        <style>
                            .ppv-how-it-works[open] summary svg { transform: rotate(180deg); }
                            .ppv-how-it-works summary::-webkit-details-marker { display: none; }
                        </style>
                    </div>
                </div>

                <!-- ============================================================
                     BIRTHDAY BONUS
                     ============================================================ -->
                <div class="ppv-marketing-card" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div class="ppv-marketing-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <div>
                            <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 24px;">🎂</span>
                                <span data-i18n="birthday_bonus_title"><?php echo esc_html(PPV_Lang::t('birthday_bonus_title')); ?></span>
                            </h3>
                            <small style="color: #888;" data-i18n="birthday_bonus_desc"><?php echo esc_html(PPV_Lang::t('birthday_bonus_desc')); ?></small>
                        </div>
                        <label class="ppv-toggle">
                            <input type="checkbox" name="birthday_bonus_enabled" value="1" <?php checked($store->birthday_bonus_enabled ?? 0, 1); ?>>
                            <span class="ppv-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="ppv-marketing-body" id="birthday-bonus-settings" style="<?php echo empty($store->birthday_bonus_enabled) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                        <div class="ppv-form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="ppv-form-group">
                                <label data-i18n="birthday_bonus_type"><?php echo esc_html(PPV_Lang::t('birthday_bonus_type')); ?></label>
                                <select name="birthday_bonus_type" id="birthday_bonus_type">
                                    <option value="double_points" <?php selected($store->birthday_bonus_type ?? 'double_points', 'double_points'); ?> data-i18n="bonus_double_points"><?php echo esc_html(PPV_Lang::t('bonus_double_points')); ?></option>
                                    <option value="fixed_points" <?php selected($store->birthday_bonus_type ?? '', 'fixed_points'); ?> data-i18n="bonus_fixed_points"><?php echo esc_html(PPV_Lang::t('bonus_fixed_points')); ?></option>
                                    <option value="free_product" <?php selected($store->birthday_bonus_type ?? '', 'free_product'); ?> data-i18n="bonus_free_product"><?php echo esc_html(PPV_Lang::t('bonus_free_product')); ?></option>
                                </select>
                            </div>
                            <div class="ppv-form-group" id="birthday_bonus_value_group" style="<?php echo ($store->birthday_bonus_type ?? 'double_points') !== 'fixed_points' ? 'display: none;' : ''; ?>">
                                <label data-i18n="birthday_bonus_value"><?php echo esc_html(PPV_Lang::t('birthday_bonus_value')); ?></label>
                                <input type="number" id="birthday_bonus_value_input" name="birthday_bonus_value" value="<?php echo esc_attr($store->birthday_bonus_value ?? 50); ?>" min="1" max="1000" <?php echo ($store->birthday_bonus_type ?? 'double_points') !== 'fixed_points' ? 'disabled' : ''; ?>>
                            </div>
                        </div>

                        <div class="ppv-form-group">
                            <label data-i18n="birthday_bonus_message"><?php echo esc_html(PPV_Lang::t('birthday_bonus_message')); ?></label>
                            <textarea name="birthday_bonus_message" rows="2" placeholder="<?php echo esc_attr(PPV_Lang::t('birthday_bonus_message_placeholder')); ?>"><?php echo esc_textarea($store->birthday_bonus_message ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     COMEBACK CAMPAIGN
                     ============================================================ -->
                <div class="ppv-marketing-card" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div class="ppv-marketing-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <div>
                            <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 24px;">👋</span>
                                <span data-i18n="comeback_title"><?php echo esc_html(PPV_Lang::t('comeback_title')); ?></span>
                            </h3>
                            <small style="color: #888;" data-i18n="comeback_desc"><?php echo esc_html(PPV_Lang::t('comeback_desc')); ?></small>
                        </div>
                        <label class="ppv-toggle">
                            <input type="checkbox" name="comeback_enabled" value="1" <?php checked($store->comeback_enabled ?? 0, 1); ?>>
                            <span class="ppv-toggle-slider"></span>
                        </label>
                    </div>

                    <div class="ppv-marketing-body" id="comeback-settings" style="<?php echo empty($store->comeback_enabled) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                        <div class="ppv-form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="ppv-form-group">
                                <label data-i18n="comeback_days"><?php echo esc_html(PPV_Lang::t('comeback_days')); ?></label>
                                <select name="comeback_days">
                                    <option value="14" <?php selected($store->comeback_days ?? 30, 14); ?>>14 <?php echo esc_html(PPV_Lang::t('days')); ?></option>
                                    <option value="30" <?php selected($store->comeback_days ?? 30, 30); ?>>30 <?php echo esc_html(PPV_Lang::t('days')); ?></option>
                                    <option value="60" <?php selected($store->comeback_days ?? 30, 60); ?>>60 <?php echo esc_html(PPV_Lang::t('days')); ?></option>
                                    <option value="90" <?php selected($store->comeback_days ?? 30, 90); ?>>90 <?php echo esc_html(PPV_Lang::t('days')); ?></option>
                                </select>
                            </div>
                            <div class="ppv-form-group">
                                <label data-i18n="comeback_bonus_type"><?php echo esc_html(PPV_Lang::t('comeback_bonus_type')); ?></label>
                                <select name="comeback_bonus_type" id="comeback_bonus_type">
                                    <option value="double_points" <?php selected($store->comeback_bonus_type ?? 'double_points', 'double_points'); ?> data-i18n="bonus_double_points"><?php echo esc_html(PPV_Lang::t('bonus_double_points')); ?></option>
                                    <option value="fixed_points" <?php selected($store->comeback_bonus_type ?? '', 'fixed_points'); ?> data-i18n="bonus_fixed_points"><?php echo esc_html(PPV_Lang::t('bonus_fixed_points')); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="ppv-form-group" id="comeback_bonus_value_group" style="<?php echo ($store->comeback_bonus_type ?? 'double_points') !== 'fixed_points' ? 'display: none;' : ''; ?>">
                            <label data-i18n="comeback_bonus_value"><?php echo esc_html(PPV_Lang::t('comeback_bonus_value')); ?></label>
                            <input type="number" id="comeback_bonus_value_input" name="comeback_bonus_value" value="<?php echo esc_attr($store->comeback_bonus_value ?? 50); ?>" min="1" max="500" <?php echo ($store->comeback_bonus_type ?? 'double_points') !== 'fixed_points' ? 'disabled' : ''; ?>>
                        </div>

                        <div class="ppv-form-group">
                            <label data-i18n="comeback_message"><?php echo esc_html(PPV_Lang::t('comeback_message')); ?></label>
                            <textarea name="comeback_message" rows="2" placeholder="<?php echo esc_attr(PPV_Lang::t('comeback_message_placeholder')); ?>"><?php echo esc_textarea($store->comeback_message ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     WHATSAPP CLOUD API
                     ============================================================ -->
                <div class="ppv-marketing-card" style="background: linear-gradient(135deg, rgba(37, 211, 102, 0.08), rgba(37, 211, 102, 0.02)); border: 1px solid rgba(37, 211, 102, 0.3); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div class="ppv-marketing-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 24px;">💬</span>
                                <span>WhatsApp Business</span>
                                <?php if (!empty($store->whatsapp_enabled)): ?>
                                <span style="background: #25D366; color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 600;" data-i18n="status_active"><?php echo esc_html(PPV_Lang::t('status_active')); ?></span>
                                <?php else: ?>
                                <span style="background: rgba(255,255,255,0.1); color: #888; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 600;" data-i18n="status_inactive"><?php echo esc_html(PPV_Lang::t('status_inactive')); ?></span>
                                <?php endif; ?>
                            </h3>
                            <small style="color: #888;" data-i18n="whatsapp_desc"><?php echo esc_html(PPV_Lang::t('whatsapp_desc')); ?></small>
                        </div>
                        <label class="ppv-toggle">
                            <input type="checkbox" name="whatsapp_enabled" value="1" <?php checked($store->whatsapp_enabled ?? 0, 1); ?>>
                            <span class="ppv-toggle-slider"></span>
                        </label>
                    </div>
                    <?php if (empty($store->whatsapp_phone_id)): ?>
                    <div style="margin-top: 15px; padding: 12px; background: rgba(255,152,0,0.1); border: 1px solid rgba(255,152,0,0.3); border-radius: 8px;">
                        <p style="margin: 0; color: #ff9800; font-size: 13px;">
                            <strong>⚠️ <?php echo esc_html(PPV_Lang::t('whatsapp_not_configured')); ?></strong><br>
                            <span style="color: #888;"><?php echo esc_html(PPV_Lang::t('whatsapp_managed_by_team')); ?></span>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ============================================================
                     REFERRAL PROGRAM
                     ============================================================ -->
                <?php
                // Calculate grace period status
                $referral_activated_at = $store->referral_activated_at ?? null;
                $referral_grace_days = intval($store->referral_grace_days ?? 60);
                $grace_period_over = false;
                $grace_days_remaining = $referral_grace_days;

                if ($referral_activated_at) {
                    $activated_date = new DateTime($referral_activated_at);
                    $now = new DateTime();
                    $days_since_activation = $now->diff($activated_date)->days;
                    $grace_days_remaining = max(0, $referral_grace_days - $days_since_activation);
                    $grace_period_over = ($grace_days_remaining === 0);
                }
                ?>
                <div class="ppv-marketing-card" style="background: linear-gradient(135deg, rgba(255, 107, 107, 0.08), rgba(255, 107, 107, 0.02)); border: 1px solid rgba(255, 107, 107, 0.3); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div class="ppv-marketing-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <div>
                            <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 24px;">🎁</span>
                                <span><?php echo PPV_Lang::t('referral_admin_title') ?: 'Referral Program'; ?></span>
                                <?php if (!$referral_activated_at): ?>
                                    <span style="background: rgba(255,255,255,0.1); color: #888; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 600;"><?php echo PPV_Lang::t('referral_admin_not_started') ?: 'NEU'; ?></span>
                                <?php elseif (!$grace_period_over): ?>
                                    <span style="background: rgba(255,152,0,0.3); color: #ff9800; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 600;">⏳ <?php echo $grace_days_remaining; ?> <?php echo PPV_Lang::t('days') ?: 'Tage'; ?></span>
                                <?php elseif (!empty($store->referral_enabled)): ?>
                                    <span style="background: #ff6b6b; color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 600;"><?php echo strtoupper(PPV_Lang::t('referral_admin_active') ?: 'AKTIV'); ?></span>
                                <?php endif; ?>
                            </h3>
                            <small style="color: #888;"><?php echo PPV_Lang::t('referral_section_subtitle') ?: 'Kunden werben Kunden - Neue Kunden durch Empfehlungen'; ?></small>
                        </div>
                        <label class="ppv-toggle">
                            <input type="checkbox" name="referral_enabled" value="1" <?php checked($store->referral_enabled ?? 0, 1); ?> <?php echo !$grace_period_over ? 'disabled' : ''; ?>>
                            <span class="ppv-toggle-slider"></span>
                        </label>
                    </div>

                    <?php if (!$referral_activated_at): ?>
                    <!-- Not yet activated - show activation prompt -->
                    <div style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 20px; text-align: center;">
                        <p style="color: #f1f5f9; margin: 0 0 15px;">
                            <strong>🚀 <?php echo PPV_Lang::t('referral_admin_start_title') ?: 'Referral Program starten'; ?></strong><br>
                            <span style="color: #888; font-size: 13px;">
                                <?php echo sprintf(PPV_Lang::t('referral_admin_start_desc') ?: 'Nach der Aktivierung beginnt eine %d-tägige Sammelphase. In dieser Zeit werden alle bestehenden Kunden erfasst, damit nur echte Neukunden als Referrals zählen.', $referral_grace_days); ?>
                            </span>
                        </p>
                        <button type="button" id="activate-referral-btn" class="ppv-btn" style="background: linear-gradient(135deg, #ff6b6b, #ee5a5a); border: none; color: white; padding: 12px 24px; border-radius: 8px; cursor: pointer;">
                            <i class="ri-rocket-line"></i> <?php echo PPV_Lang::t('referral_admin_start_btn') ?: 'Jetzt starten'; ?>
                        </button>
                    </div>

                    <?php elseif (!$grace_period_over): ?>
                    <!-- Grace period active -->
                    <div style="background: rgba(255,152,0,0.1); border: 1px solid rgba(255,152,0,0.3); border-radius: 8px; padding: 15px;">
                        <p style="margin: 0; color: #ff9800;">
                            <strong>⏳ <?php echo PPV_Lang::t('referral_admin_grace_title') ?: 'Sammelphase läuft'; ?></strong><br>
                            <span style="color: #888;">
                                <?php echo sprintf(PPV_Lang::t('referral_admin_grace_remaining') ?: 'Noch %d Tage bis das Referral Program aktiviert werden kann.', $grace_days_remaining); ?><br>
                                <?php echo PPV_Lang::t('referral_admin_grace_desc') ?: 'In dieser Zeit werden bestehende Kunden erfasst.'; ?>
                            </span>
                        </p>
                        <div style="margin-top: 10px; background: rgba(0,0,0,0.2); border-radius: 4px; height: 8px; overflow: hidden;">
                            <?php $progress = (($referral_grace_days - $grace_days_remaining) / $referral_grace_days) * 100; ?>
                            <div style="background: linear-gradient(90deg, #ff6b6b, #ff9800); height: 100%; width: <?php echo $progress; ?>%; transition: width 0.3s;"></div>
                        </div>
                    </div>

                    <!-- Grace period settings (editable) -->
                    <div style="margin-top: 15px; background: rgba(0,0,0,0.2); border-radius: 8px; padding: 15px;">
                        <div class="ppv-form-group" style="margin-bottom: 0;">
                            <label><?php echo PPV_Lang::t('referral_admin_grace_days') ?: 'Grace Period (Tage)'; ?></label>
                            <input type="number" name="referral_grace_days" value="<?php echo esc_attr($referral_grace_days); ?>" min="7" max="180" style="width: 100px;">
                            <small style="color: #666;"><?php echo PPV_Lang::t('min_max_days') ?: 'Mindestens 7, maximal 180 Tage'; ?></small>
                        </div>
                    </div>

                    <?php else: ?>
                    <!-- Grace period over - show full settings -->
                    <div class="ppv-marketing-body" id="referral-settings" style="<?php echo empty($store->referral_enabled) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">

                        <!-- Reward Type Selection -->
                        <div style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                            <h4 style="margin: 0 0 10px; color: #ff6b6b; font-size: 14px;">🎁 <?php echo PPV_Lang::t('referral_admin_reward_type') ?: 'Belohnung konfigurieren'; ?></h4>
                            <p style="color: #888; font-size: 12px; margin-bottom: 15px;"><?php echo PPV_Lang::t('referral_section_subtitle') ?: 'Was bekommen Werber und Geworbener?'; ?></p>

                            <div class="ppv-form-row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px;">
                                <label style="display: flex; flex-direction: column; align-items: center; gap: 8px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; cursor: pointer; border: 2px solid <?php echo ($store->referral_reward_type ?? 'points') === 'points' ? '#ff6b6b' : 'transparent'; ?>;">
                                    <input type="radio" name="referral_reward_type" value="points" <?php checked($store->referral_reward_type ?? 'points', 'points'); ?> style="display: none;" onchange="updateReferralRewardUI()">
                                    <span style="font-size: 24px;">⭐</span>
                                    <strong style="color: #f1f5f9;"><?php echo PPV_Lang::t('referral_admin_reward_points') ?: 'Punkte'; ?></strong>
                                </label>

                                <label style="display: flex; flex-direction: column; align-items: center; gap: 8px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; cursor: pointer; border: 2px solid <?php echo ($store->referral_reward_type ?? '') === 'euro' ? '#ff6b6b' : 'transparent'; ?>;">
                                    <input type="radio" name="referral_reward_type" value="euro" <?php checked($store->referral_reward_type ?? '', 'euro'); ?> style="display: none;" onchange="updateReferralRewardUI()">
                                    <span style="font-size: 24px;">💶</span>
                                    <strong style="color: #f1f5f9;"><?php echo PPV_Lang::t('referral_admin_reward_euro') ?: 'Euro'; ?></strong>
                                </label>

                                <label style="display: flex; flex-direction: column; align-items: center; gap: 8px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; cursor: pointer; border: 2px solid <?php echo ($store->referral_reward_type ?? '') === 'gift' ? '#ff6b6b' : 'transparent'; ?>;">
                                    <input type="radio" name="referral_reward_type" value="gift" <?php checked($store->referral_reward_type ?? '', 'gift'); ?> style="display: none;" onchange="updateReferralRewardUI()">
                                    <span style="font-size: 24px;">🎀</span>
                                    <strong style="color: #f1f5f9;"><?php echo PPV_Lang::t('referral_admin_reward_gift') ?: 'Geschenk'; ?></strong>
                                </label>
                            </div>

                            <!-- Points value -->
                            <div id="referral-value-points" class="ppv-form-group" style="<?php echo ($store->referral_reward_type ?? 'points') !== 'points' ? 'display:none;' : ''; ?>">
                                <label><?php echo PPV_Lang::t('referral_admin_points_value') ?: 'Punkte pro Empfehlung'; ?></label>
                                <input type="number" name="referral_reward_value" value="<?php echo esc_attr($store->referral_reward_value ?? 50); ?>" min="1" max="500" style="width: 100px;">
                                <small style="color: #666;"><?php echo PPV_Lang::t('referral_section_subtitle') ?: 'Werber UND Geworbener bekommen diese Punkte'; ?></small>
                            </div>

                            <!-- Euro value -->
                            <div id="referral-value-euro" class="ppv-form-group" style="<?php echo ($store->referral_reward_type ?? '') !== 'euro' ? 'display:none;' : ''; ?>">
                                <label><?php echo PPV_Lang::t('referral_admin_euro_value') ?: 'Euro Rabatt'; ?></label>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <input type="number" name="referral_reward_value_euro" value="<?php echo esc_attr($store->referral_reward_value ?? 5); ?>" min="1" max="50" style="width: 80px;">
                                    <span style="color: #888;">€</span>
                                </div>
                            </div>

                            <!-- Gift -->
                            <div id="referral-value-gift" class="ppv-form-group" style="<?php echo ($store->referral_reward_type ?? '') !== 'gift' ? 'display:none;' : ''; ?>">
                                <label><?php echo PPV_Lang::t('referral_admin_gift_value') ?: 'Geschenk-Beschreibung'; ?></label>
                                <input type="text" name="referral_reward_gift" value="<?php echo esc_attr($store->referral_reward_gift ?? ''); ?>" placeholder="<?php echo PPV_Lang::t('referral_admin_gift_placeholder') ?: 'z.B. Gratis Kaffee, 1x Dessert...'; ?>">
                            </div>
                        </div>

                        <!-- Manual Approval -->
                        <div style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="referral_manual_approval" value="1" <?php checked($store->referral_manual_approval ?? 0, 1); ?> style="width: 18px; height: 18px;">
                                <span>
                                    <strong style="color: #f1f5f9;">🔍 <?php echo PPV_Lang::t('referral_admin_manual_approval') ?: 'Manuelle Freigabe'; ?></strong><br>
                                    <small style="color: #888;"><?php echo PPV_Lang::t('referral_admin_manual_desc') ?: 'Jeden neuen Referral vor der Belohnung prüfen'; ?></small>
                                </span>
                            </label>
                        </div>

                        <!-- Statistics -->
                        <?php
                        global $wpdb;
                        $referral_stats = $wpdb->get_row($wpdb->prepare(
                            "SELECT
                                COUNT(*) as total,
                                SUM(CASE WHEN status IN ('completed', 'approved') THEN 1 ELSE 0 END) as successful,
                                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                             FROM {$wpdb->prefix}ppv_referrals WHERE store_id = %d",
                            $store->id
                        ));
                        ?>
                        <div style="background: rgba(255,107,107,0.1); border: 1px solid rgba(255,107,107,0.3); border-radius: 8px; padding: 15px;">
                            <h4 style="margin: 0 0 10px; color: #ff6b6b; font-size: 14px;">📊 <?php echo PPV_Lang::t('referral_admin_stats') ?: 'Referral Statistik'; ?></h4>
                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; text-align: center;">
                                <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 6px;">
                                    <div style="font-size: 24px; font-weight: bold; color: #f1f5f9;"><?php echo intval($referral_stats->total ?? 0); ?></div>
                                    <div style="font-size: 11px; color: #888;"><?php echo PPV_Lang::t('referral_admin_total') ?: 'Gesamt'; ?></div>
                                </div>
                                <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 6px;">
                                    <div style="font-size: 24px; font-weight: bold; color: #4caf50;"><?php echo intval($referral_stats->successful ?? 0); ?></div>
                                    <div style="font-size: 11px; color: #888;"><?php echo PPV_Lang::t('referral_successful') ?: 'Erfolgreich'; ?></div>
                                </div>
                                <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 6px;">
                                    <div style="font-size: 24px; font-weight: bold; color: #ff9800;"><?php echo intval($referral_stats->pending ?? 0); ?></div>
                                    <div style="font-size: 11px; color: #888;"><?php echo PPV_Lang::t('referral_pending') ?: 'Ausstehend'; ?></div>
                                </div>
                                <div style="background: rgba(0,0,0,0.2); padding: 10px; border-radius: 6px;">
                                    <div style="font-size: 24px; font-weight: bold; color: #f44336;"><?php echo intval($referral_stats->rejected ?? 0); ?></div>
                                    <div style="font-size: 11px; color: #888;"><?php echo PPV_Lang::t('referral_admin_rejected') ?: 'Abgelehnt'; ?></div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <?php endif; ?>

                    <!-- How it works collapsible (always visible) -->
                    <details class="ppv-how-it-works" style="margin-top: 15px; background: rgba(255,107,107,0.08); border: 1px solid rgba(255,107,107,0.2); border-radius: 10px; overflow: hidden;">
                        <summary style="padding: 12px 15px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 500; color: #ff6b6b; list-style: none;">
                            <span style="font-size: 16px;">💡</span>
                            <span data-i18n="referral_how_it_works"><?php echo esc_html(PPV_Lang::t('referral_how_it_works') ?: 'So funktioniert\'s'); ?></span>
                            <svg style="margin-left: auto; width: 16px; height: 16px; transition: transform 0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <div style="padding: 0 15px 15px 15px; color: #ccc; font-size: 13px; line-height: 1.6;">
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <div style="display: flex; align-items: flex-start; gap: 10px;">
                                    <span style="background: linear-gradient(135deg, #ff6b6b, #ee5a5a); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">1</span>
                                    <span data-i18n="referral_step1"><?php echo esc_html(PPV_Lang::t('referral_step1') ?: 'Ein Kunde teilt seinen persönlichen Empfehlungslink oder QR-Code mit Freunden und Familie.'); ?></span>
                                </div>
                                <div style="display: flex; align-items: flex-start; gap: 10px;">
                                    <span style="background: linear-gradient(135deg, #ff6b6b, #ee5a5a); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">2</span>
                                    <span data-i18n="referral_step2"><?php echo esc_html(PPV_Lang::t('referral_step2') ?: 'Der geworbene Neukunde registriert sich über den Link und sammelt seine ersten Punkte.'); ?></span>
                                </div>
                                <div style="display: flex; align-items: flex-start; gap: 10px;">
                                    <span style="background: linear-gradient(135deg, #ff6b6b, #ee5a5a); color: white; min-width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">3</span>
                                    <span data-i18n="referral_step3"><?php echo esc_html(PPV_Lang::t('referral_step3') ?: 'Beide erhalten automatisch die eingestellte Belohnung - der Werber und der Geworbene.'); ?></span>
                                </div>
                            </div>
                        </div>
                    </details>
                </div>

                <!-- Info box -->
                <div class="ppv-info-box" style="background: rgba(0, 230, 255, 0.05); border: 1px solid rgba(0, 230, 255, 0.2); border-radius: 8px; padding: 15px; margin-top: 20px;">
                    <p style="margin: 0; color: #00e6ff; font-size: 13px;">
                        <strong>💡 <?php echo esc_html(PPV_Lang::t('marketing_tip_title')); ?></strong><br>
                        <span style="color: #888;"><?php echo esc_html(PPV_Lang::t('marketing_tip_text')); ?></span>
                    </p>
                </div>
            </div>

            <script>
            // WhatsApp nonce for AJAX
            window.ppvWhatsAppNonce = '<?php echo wp_create_nonce('ppv_whatsapp_nonce'); ?>';
            window.ppvWhatsAppStoreId = <?php echo intval($store->id ?? 0); ?>;

        public static function render_tab_settings($store) {
            ob_start();
            ?>
            <div class="ppv-tab-content" id="tab-settings">
                <h2 data-i18n="profile_settings"><?php echo esc_html(PPV_Lang::t('profile_settings')); ?></h2>

                <h3 style="margin-top: 0;">Aktivierung</h3>

                <div class="ppv-checkbox-group">
                    <label class="ppv-checkbox">
                        <input type="checkbox" name="active" value="1" <?php checked($store->active ?? 1, 1); ?>>
                        <strong data-i18n="store_active"><?php echo esc_html(PPV_Lang::t('store_active')); ?></strong>
                        <small data-i18n="store_active_help"><?php echo esc_html(PPV_Lang::t('store_active_help')); ?></small>
                    </label>
                </div>

                <div class="ppv-checkbox-group">
                    <label class="ppv-checkbox">
                        <input type="checkbox" name="visible" value="1" <?php checked($store->visible ?? 1, 1); ?>>
                        <strong data-i18n="store_visible"><?php echo esc_html(PPV_Lang::t('store_visible')); ?></strong>
                        <small data-i18n="store_visible_help"><?php echo esc_html(PPV_Lang::t('store_visible_help')); ?></small>
                    </label>
                </div>

                <hr>

                <h3>Wartungsmodus / Karbantartás / Mod de Întreținere</h3>

                <div class="ppv-checkbox-group">
                    <label class="ppv-checkbox">
                        <input type="checkbox" name="maintenance_mode" value="1" <?php checked($store->maintenance_mode ?? 0, 1); ?>>
                        <strong data-i18n="maintenance_mode"><?php echo esc_html(PPV_Lang::t('maintenance_mode')); ?></strong>
                        <small data-i18n="maintenance_mode_help"><?php echo esc_html(PPV_Lang::t('maintenance_mode_help')); ?></small>
                    </label>
                </div>

                <div class="ppv-form-group" style="margin-top: 12px;">
                    <label data-i18n="maintenance_message"><?php echo esc_html(PPV_Lang::t('maintenance_message')); ?></label>
                    <p class="ppv-help" data-i18n="maintenance_message_help" style="margin-bottom: 8px;">
                        <?php echo esc_html(PPV_Lang::t('maintenance_message_help')); ?>
                    </p>
                    <textarea name="maintenance_message" placeholder="<?php echo esc_attr(PPV_Lang::t('maintenance_message_placeholder')); ?>" style="min-height: 100px;">
<?php echo esc_textarea($store->maintenance_message ?? ''); ?>
                    </textarea>
                </div>

                <hr>

                <h3>Zeitzone / Időzóna / Fus Orar</h3>

                <div class="ppv-form-group">
                    <label data-i18n="timezone"><?php echo esc_html(PPV_Lang::t('timezone')); ?></label>
                    <p class="ppv-help" data-i18n="timezone_help" style="margin-bottom: 8px;">
                        <?php echo esc_html(PPV_Lang::t('timezone_help')); ?>
                    </p>
                    <select name="timezone" style="margin-bottom: 16px;">
                        <option value="Europe/Berlin" <?php selected($store->timezone ?? '', 'Europe/Berlin'); ?> data-i18n="timezone_berlin">
                            <?php echo esc_html(PPV_Lang::t('timezone_berlin')); ?>
                        </option>
                        <option value="Europe/Budapest" <?php selected($store->timezone ?? '', 'Europe/Budapest'); ?> data-i18n="timezone_budapest">
                            <?php echo esc_html(PPV_Lang::t('timezone_budapest')); ?>
                        </option>
                        <option value="Europe/Bucharest" <?php selected($store->timezone ?? '', 'Europe/Bucharest'); ?> data-i18n="timezone_bucharest">
                            <?php echo esc_html(PPV_Lang::t('timezone_bucharest')); ?>
                        </option>
                    </select>
                </div>

                <hr>

                <!-- ============================================================
                     ONBOARDING RESET
                     ============================================================ -->
                <h3>Onboarding</h3>

                <div class="ppv-form-group">
                    <p class="ppv-help" data-i18n="onboarding_reset_help" style="margin-bottom: 12px;">
                        <?php echo esc_html(PPV_Lang::t('onboarding_reset_help')); ?>
                    </p>
                    <button type="button" id="ppv-reset-onboarding-btn" class="ppv-btn ppv-btn-secondary" style="width: 100%;">
                        🔄 <span data-i18n="onboarding_reset_btn"><?php echo esc_html(PPV_Lang::t('onboarding_reset_btn')); ?></span>
                    </button>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

}
