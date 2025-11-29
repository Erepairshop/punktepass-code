<?php
if (!defined('ABSPATH')) exit;

/**
 * PPV_QR_Scanner_Trait
 * Scanner related functions for PPV_QR class
 *
 * Contains:
 * - render_pos_scanner()
 * - render_filiale_switcher()
 * - render_scanner_users()
 */
trait PPV_QR_Scanner_Trait {

    // ============================================================
    // 📲 POS SCANNER
    // ============================================================
    public static function render_pos_scanner() {
        global $wpdb;

        // Get store trial info
        $store_id = 0;
        $trial_days_left = 0;
        $subscription_days_left = 0;
        $subscription_status = 'unknown';
        $renewal_requested = false;

        // Try to get store ID from session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $store_id = intval($_SESSION['ppv_current_filiale_id']);
        } elseif (!empty($_SESSION['ppv_store_id'])) {
            $store_id = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_active_store'])) {
            $store_id = intval($_SESSION['ppv_active_store']);
        } elseif (!empty($_SESSION['ppv_vendor_store_id'])) {
            $store_id = intval($_SESSION['ppv_vendor_store_id']);
        }

        // If no store_id in session, try to get it via user_id (fallback)
        if ($store_id === 0 && !empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);

            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $user_id
            ));

            if ($store_id) {
                $store_id = intval($store_id);
            }
        }

        // Fetch subscription info from database
        // FILIALE SUPPORT: Use parent store's subscription for filialen
        if ($store_id > 0) {
            // First check if this is a filiale with a parent
            $parent_check = $wpdb->get_var($wpdb->prepare(
                "SELECT parent_store_id FROM {$wpdb->prefix}ppv_stores WHERE id = %d AND parent_store_id IS NOT NULL AND parent_store_id > 0 LIMIT 1",
                $store_id
            ));

            // Use parent store ID for subscription check if this is a filiale
            $subscription_store_id = $parent_check ? intval($parent_check) : $store_id;

            $store_data = $wpdb->get_row($wpdb->prepare(
                "SELECT trial_ends_at, subscription_status, subscription_expires_at, subscription_renewal_requested FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
                $subscription_store_id
            ));

            if ($store_data) {
                $subscription_status = $store_data->subscription_status ?? 'trial';
                $renewal_requested = !empty($store_data->subscription_renewal_requested);
                $now = current_time('timestamp');

                // Calculate trial days left
                if (!empty($store_data->trial_ends_at)) {
                    $trial_end = strtotime($store_data->trial_ends_at);
                    $diff_seconds = $trial_end - $now;
                    $trial_days_left = max(0, ceil($diff_seconds / 86400));
                }

                // Calculate subscription days left (for active subscriptions)
                if (!empty($store_data->subscription_expires_at)) {
                    $sub_end = strtotime($store_data->subscription_expires_at);
                    $diff_seconds = $sub_end - $now;
                    $subscription_days_left = max(0, ceil($diff_seconds / 86400));
                }
            }
        }

        // Determine message and styling based on days left
        $info_class = 'info';
        $info_icon = 'i';
        $info_color = 'rgba(0, 230, 255, 0.1)';
        $border_color = 'rgba(0, 230, 255, 0.3)';
        $show_description = false;

        // Check if renewal button should be shown
        $show_renewal_button = !$renewal_requested && (
            ($subscription_status === 'trial' && $trial_days_left === 0) ||
            ($subscription_status === 'active' && $subscription_days_left === 0)
        );

        // Check if upgrade button should be shown (trial with 7 days or less)
        $show_upgrade_button = !$renewal_requested && ($subscription_status === 'trial' && $trial_days_left <= 7 && $trial_days_left > 0);

        if ($subscription_status === 'active') {
            // Active subscription with expiry date
            if ($subscription_days_left > 0) {
                if ($subscription_days_left > 30) {
                    $info_message = sprintf(self::t('subscription_active_days', 'Aktives Abo - Noch %d Tage'), $subscription_days_left);
                    $info_class = 'success';
                    $info_icon = '✅';
                    $info_color = 'rgba(0, 230, 118, 0.1)';
                    $border_color = 'rgba(0, 230, 118, 0.3)';
                } elseif ($subscription_days_left > 7) {
                    $info_message = sprintf(self::t('subscription_expiring_soon', 'Abo läuft in %d Tagen ab'), $subscription_days_left);
                    $info_class = 'info';
                    $info_icon = '📅';
                    $info_color = 'rgba(0, 230, 255, 0.1)';
                    $border_color = 'rgba(0, 230, 255, 0.3)';
                } else {
                    $info_message = sprintf(self::t('subscription_expiring_warning', 'Abo endet bald - Nur noch %d Tage!'), $subscription_days_left);
                    $info_class = 'warning';
                    $info_icon = '⚠️';
                    $info_color = 'rgba(255, 171, 0, 0.1)';
                    $border_color = 'rgba(255, 171, 0, 0.3)';
                }
                $show_description = true;
            } else {
                // Active but expired
                $info_message = self::t('subscription_expired', 'Abo abgelaufen');
                $info_class = 'error';
                $info_icon = '❌';
                $info_color = 'rgba(239, 68, 68, 0.1)';
                $border_color = 'rgba(239, 68, 68, 0.3)';
            }
        } elseif ($trial_days_left > 7) {
            $info_message = sprintf(self::t('trial_days_left', 'Noch %d Tage Testversion'), $trial_days_left);
            $info_icon = '📅';
        } elseif ($trial_days_left > 0) {
            $info_message = sprintf(self::t('trial_days_left_warning', 'Nur noch %d Tage!'), $trial_days_left);
            $info_class = 'warning';
            $info_icon = '⚠️';
            $info_color = 'rgba(255, 171, 0, 0.1)';
            $border_color = 'rgba(255, 171, 0, 0.3)';
        } else {
            $info_message = self::t('trial_expired', 'Testversion abgelaufen');
            $info_class = 'error';
            $info_icon = '❌';
            $info_color = 'rgba(239, 68, 68, 0.1)';
            $border_color = 'rgba(239, 68, 68, 0.3)';
        }

        // Check if scanner user (don't show subscription info to scanners)
        $is_scanner = class_exists('PPV_Permissions') && PPV_Permissions::is_scanner_user();

        if (!$is_scanner): ?>
        <div class="ppv-trial-info-block" style="margin-bottom: 15px; padding: 12px 16px; background: <?php echo $info_color; ?>; border-radius: 10px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;"><?php echo $info_icon; ?></span>
                    <div>
                        <div style="font-weight: bold; font-size: 15px; margin-bottom: 2px;">
                            <?php echo $info_message; ?>
                        </div>
                        <?php if ($renewal_requested): ?>
                            <div style="font-size: 12px; opacity: 0.9; color: #00e6ff;">
                                <?php echo self::t('renewal_in_progress', 'Aboverlängerung in Bearbeitung - Wir kontaktieren Sie bald per E-Mail oder Telefon'); ?>
                            </div>
                        <?php elseif ($show_description && $subscription_status === 'active'): ?>
                            <div style="font-size: 12px; opacity: 0.7;">
                                <?php echo self::t('subscription_info_desc', 'Aktive Premium-Mitgliedschaft'); ?>
                            </div>
                        <?php elseif ($subscription_status === 'trial' && $trial_days_left > 0): ?>
                            <div style="font-size: 12px; opacity: 0.7;">
                                <?php echo self::t('trial_info_desc', 'Registriert mit 30 Tage Probezeit'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($show_upgrade_button): ?>
                    <button id="ppv-request-renewal-btn" class="ppv-btn-outline" style="padding: 6px 12px; font-size: 13px; white-space: nowrap;">
                        📧 <?php echo self::t('upgrade_now', 'Jetzt upgraden'); ?>
                    </button>
                <?php elseif ($show_renewal_button): ?>
                    <button id="ppv-request-renewal-btn" class="ppv-btn-outline" style="padding: 6px 12px; font-size: 13px; white-space: nowrap;">
                        📧 <?php echo self::t('request_renewal', 'Abo verlängern'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($show_renewal_button || $show_upgrade_button): ?>
        <!-- Renewal Request Modal -->
        <div id="ppv-renewal-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
            <div style="background: #1a1a2e; padding: 30px; border-radius: 15px; max-width: 400px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                <h3 style="margin-top: 0; color: #fff;">📧 <?php echo self::t('renewal_request_title', 'Abo Verlängerung anfragen'); ?></h3>
                <p style="color: #ccc; font-size: 14px;"><?php echo self::t('renewal_request_desc', 'Bitte geben Sie Ihre Telefonnummer ein. Wir kontaktieren Sie schnellstmöglich.'); ?></p>
                <input type="tel" id="ppv-renewal-phone" class="ppv-input" placeholder="<?php echo self::t('phone_placeholder', 'Telefonnummer'); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">
                <div id="ppv-renewal-error" style="display: none; color: #ff5252; font-size: 13px; margin-bottom: 10px;"></div>
                <div style="display: flex; gap: 10px;">
                    <button id="ppv-renewal-submit" class="ppv-btn" style="flex: 1; padding: 12px;">
                        ✅ <?php echo self::t('send_request', 'Anfrage senden'); ?>
                    </button>
                    <button id="ppv-renewal-cancel" class="ppv-btn-outline" style="flex: 1; padding: 12px;">
                        ❌ <?php echo self::t('cancel', 'Abbrechen'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; // End scanner check for subscription info ?>

        <?php self::render_filiale_switcher(); ?>

        <div class="ppv-pos-center">
            <div id="ppv-offline-banner" style="display:none;background:#ffcc00;padding:8px;border-radius:6px;margin-bottom:10px;">
                🛰️ <?php echo self::t('offline_banner', 'Offline-Modus aktiv'); ?>
                <button id="ppv-sync-btn" class="ppv-btn small" type="button">
                    <?php echo self::t('sync_now', 'Sync'); ?>
                </button>
            </div>

            <div class="ppv-pos-header">
                <h4 class="ppv-pos-title"><?php echo self::t('table_title', '📋 Letzte Scans'); ?></h4>

                <!-- CSV EXPORT DROPDOWN -->
                <div class="ppv-csv-wrapper">
                    <button id="ppv-csv-export-btn" class="ppv-btn ppv-csv-btn">
                        📥 <?php echo self::t('csv_export', 'CSV Export'); ?>
                    </button>
                    <div id="ppv-csv-export-menu" class="ppv-csv-dropdown">
                        <a href="#" class="ppv-csv-export-option" data-period="today">
                            📅 <?php echo self::t('csv_today', 'Heute'); ?>
                        </a>
                        <a href="#" class="ppv-csv-export-option" data-period="date">
                            📆 <?php echo self::t('csv_date', 'Datum wählen'); ?>
                        </a>
                        <a href="#" class="ppv-csv-export-option" data-period="month">
                            <i class="ri-file-chart-line"></i> <?php echo self::t('csv_month', 'Diesen Monat'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <table id="ppv-pos-log" class="glass-table">
                <thead>
                    <tr>
                        <th><?php echo self::t('t_col_time', 'Zeit'); ?></th>
                        <th><?php echo self::t('t_col_customer', 'Kunde'); ?></th>
                        <th><?php echo self::t('t_col_status', 'Status'); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <!-- CAMERA SCANNER MODAL -->
            <div id="ppv-camera-modal" class="ppv-modal" role="dialog" aria-modal="true" style="display: none;">
                <div class="ppv-modal-inner" style="max-width: 100%; width: 100%; max-height: 90vh; overflow-y: auto;">

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h4 style="margin: 0;"><i class="ri-camera-line"></i> <?php echo self::t('camera_scanner_title', 'Kamera QR-Scanner'); ?></h4>
                        <button id="ppv-camera-close" class="ppv-btn-outline" type="button" style="width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center;">
                            ✕
                        </button>
                    </div>

                    <p style="color: #999; font-size: 14px; margin-bottom: 15px;">
                        <i class="ri-vidicon-line"></i> <?php echo self::t('camera_scanner_desc', 'Halte den QR-Code des Kunden vor die Kamera. Der Scanner erkennt diesen automatisch.'); ?>
                    </p>

                    <!-- Scanner Area -->
                    <div id="ppv-reader" style="width: 100%; max-width: 400px; height: 400px; margin: 0 auto 20px; border: 2px solid #00e6ff; border-radius: 14px; overflow: hidden; background: #000;"></div>

                    <!-- Result Box -->
                    <div id="ppv-scan-result" style="text-align: center; padding: 15px; background: rgba(0, 230, 255, 0.1); border-radius: 8px; border: 1px solid rgba(0, 230, 255, 0.3); margin-bottom: 15px; min-height: 40px; display: flex; align-items: center; justify-content: center; color: #00e6ff; font-weight: bold;">
                            <i class="ri-loader-4-line ri-spin"></i> Scanner aktiv...
                    </div>

                    <!-- Controls -->
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button id="ppv-torch-toggle" class="ppv-btn" type="button" style="display: none;">
                            🔦 Taschenlampe
                        </button>
                        <button id="ppv-camera-cancel" class="ppv-btn-outline" type="button" style="flex: 1;">
                            <?php echo self::t('btn_cancel', 'Abbrechen'); ?>
                        </button>
                    </div>

                </div>
            </div>
        </div>
        <?php
    }

}
