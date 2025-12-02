<?php
if (!defined('ABSPATH')) exit;

/**
 * PPV_QR_Campaigns_Trait
 * Campaign render functions for PPV_QR class
 * 
 * Contains:
 * - render_campaigns()
 */
trait PPV_QR_Campaigns_Trait {

    public static function render_campaigns() {
        // Get filialen for dropdown
        $filialen = self::get_handler_filialen();
        $has_multiple_filialen = count($filialen) > 1;
        ?>
        <div class="ppv-campaigns">
            <div class="ppv-campaign-header">
                <h3><i class="ri-focus-3-line"></i> <?php echo self::t('campaigns_title', 'Kampagnen'); ?></h3>
                <div class="ppv-campaign-controls">
                    <?php if ($has_multiple_filialen): ?>
                    <select id="ppv-campaign-filiale" class="ppv-filter ppv-filiale-select">
                        <option value="all"><?php echo self::t('all_branches', 'Összes filiale'); ?></option>
                        <?php foreach ($filialen as $fil): ?>
                            <option value="<?php echo intval($fil->id); ?>">
                                <?php echo esc_html($fil->company_name ?: $fil->name ?: 'Filiale #' . $fil->id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <select id="ppv-campaign-filter" class="ppv-filter">
                        <option value="all">📋 <?php echo self::t('camp_filter_all', 'Alle'); ?></option>
                        <option value="active">🟢 <?php echo self::t('camp_filter_active', 'Aktive'); ?></option>
                        <option value="archived">📦 <?php echo self::t('camp_filter_archived', 'Archiv'); ?></option>
                    </select>
                    <button id="ppv-new-campaign" class="ppv-btn neon" type="button"><?php echo self::t('camp_new', '+ Neue Kampagne'); ?></button>
                </div>
            </div>

            <div id="ppv-campaign-list" class="ppv-campaign-list"></div>

            <!-- 🎯 KAMPAGNE MODAL - KOMPLETT FORMA! -->
            <div id="ppv-campaign-modal" class="ppv-modal" role="dialog" aria-modal="true">
                <div class="ppv-modal-inner">
                    <h4><?php echo self::t('camp_edit_modal', 'Kampagne bearbeiten'); ?></h4>

                    <!-- TITEL -->
                    <label><?php echo self::t('label_title', 'Titel'); ?></label>
                    <input type="text" id="camp-title" placeholder="<?php echo esc_attr(self::t('camp_placeholder_title', 'z. B. Doppelte Punkte-Woche')); ?>">

                    <!-- STARTDATUM -->
                    <label><?php echo self::t('label_start', 'Startdatum'); ?></label>
                    <input type="date" id="camp-start">

                    <!-- ENDDATUM -->
                    <label><?php echo self::t('label_end', 'Enddatum'); ?></label>
                    <input type="date" id="camp-end">

                    <!-- KAMPAGNEN TYP -->
                    <label><?php echo self::t('label_type', 'Kampagnen Typ'); ?></label>
                    <select id="camp-type">
                        <option value="points"><?php echo self::t('type_points', 'Extra Punkte'); ?></option>
                        <option value="discount"><?php echo self::t('type_discount', 'Rabatt (%)'); ?></option>
                        <option value="fixed"><?php echo self::t('type_fixed', 'Fix Bonus (€)'); ?></option>
                        <option value="free_product">🎁 <?php echo self::t('type_free_product', 'Gratis Termék'); ?></option>
                    </select>

                    <!-- SZÜKSÉGES PONTOK (csak POINTS típusnál!) -->
                    <div id="camp-required-points-wrapper" style="display: none;">
                        <label><?php echo self::t('label_required_points', 'Szükséges pontok'); ?></label>
                        <input type="number" id="camp-required-points" value="0" min="0" step="1">
                    </div>

                    <!-- WERT -->
                    <label id="camp-value-label"><?php echo self::t('camp_value_label', 'Wert'); ?></label>
                    <input type="number" id="camp-value" value="0" min="0" step="0.1">

                    <!-- PONTOK PER SCAN (csak POINTS típusnál!) -->
                    <div id="camp-points-given-wrapper" style="display: none;">
                        <label><?php echo self::t('label_points_given', 'Pontok per scan'); ?></label>
                        <input type="number" id="camp-points-given" value="1" min="1" step="1">
                    </div>

                    <!-- GRATIS TERMÉK NEVE (csak FREE_PRODUCT típusnál!) -->
                    <div id="camp-free-product-name-wrapper" style="display: none;">
                        <label><?php echo self::t('label_free_product', '🎁 Termék neve'); ?></label>
                        <input type="text" id="camp-free-product-name" placeholder="<?php echo esc_attr(self::t('camp_placeholder_free_product', 'pl. Kávé + Sütemény')); ?>">
                    </div>

                    <!-- GRATIS TERMÉK ÉRTÉKE (csak ha van termék név!) -->
                    <div id="camp-free-product-value-wrapper" style="display: none;">
                        <label style="color: #ff9800;"><i class="ri-money-euro-circle-line"></i> <?php echo self::t('label_free_product_value', 'Termék értéke'); ?> <span style="color: #ff0000;">*</span></label>
                        <input type="number" id="camp-free-product-value" value="0" min="0.01" step="0.01" placeholder="0.00" style="border-color: #ff9800;">
                    </div>

                    <!-- STATUS -->
                    <label><?php echo self::t('label_status', 'Status'); ?></label>
                    <select id="camp-status">
                        <option value="active"><?php echo self::t('status_active', '🟢 Aktiv'); ?></option>
                        <option value="archived"><?php echo self::t('status_archived', '📦 Archiv'); ?></option>
                    </select>

                    <!-- 🏢 FILIALE SELECTOR (csak ha több filiálé van) -->
                    <?php if ($has_multiple_filialen): ?>
                    <div class="ppv-filiale-selector" style="margin-top: 16px; padding: 12px; background: rgba(102,126,234,0.1); border-radius: 8px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                            <input type="checkbox" id="camp-apply-all" style="width: 18px; height: 18px;">
                            <span><i class="ri-checkbox-multiple-line"></i> <?php echo self::t('camp_apply_all', 'Auf alle Filialen anwenden'); ?></span>
                        </label>
                        <small style="display: block; margin-top: 6px; color: #888;">
                            <?php echo self::t('camp_apply_all_hint', 'Kampagne wird für alle Filialen erstellt/aktualisiert'); ?>
                        </small>
                    </div>
                    <?php endif; ?>

                    <!-- GOMBÓK -->
                    <div class="ppv-modal-actions">
                        <button id="camp-save" class="ppv-btn neon" type="button">
                            <?php echo self::t('btn_save', '💾 Speichern'); ?>
                        </button>
                        <button id="camp-cancel" class="ppv-btn-outline" type="button">
                            <?php echo self::t('btn_cancel', 'Abbrechen'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // ============================================================
    // 👥 RENDER SCANNER USERS MANAGEMENT
    // ============================================================
}
