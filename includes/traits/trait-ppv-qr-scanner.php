<?php
if (!defined('ABSPATH')) exit;

/**
 * PPV_QR_Scanner_Trait
 * Scanner related render functions for PPV_QR class
 * 
 * Contains:
 * - render_pos_scanner()
 * - render_filiale_switcher()
 */
trait PPV_QR_Scanner_Trait {

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

        // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $store_id = intval($_SESSION['ppv_current_filiale_id']);
        } elseif (!empty($_SESSION['ppv_store_id'])) {
            $store_id = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_active_store'])) {
            $store_id = intval($_SESSION['ppv_active_store']);
        } elseif (!empty($_SESSION['ppv_vendor_store_id'])) {
            $store_id = intval($_SESSION['ppv_vendor_store_id']);
        }

        // ✅ If no store_id in session, try to get it via user_id (fallback)
        if ($store_id === 0 && !empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);

            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $user_id
            ));

            if ($store_id) {
                $store_id = intval($store_id);
            } else {
            }
        }

        // 🐛 DEBUG: Log store_id

        // Fetch subscription info from database
        // 🏪 FILIALE SUPPORT: Use parent store's subscription for filialen
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
        $info_icon = 'ℹ️';
        $info_color = 'rgba(0, 230, 255, 0.1)';
        $border_color = 'rgba(0, 230, 255, 0.3)';
        $show_description = false;

        // ✅ Check if renewal button should be shown
        $show_renewal_button = !$renewal_requested && (
            ($subscription_status === 'trial' && $trial_days_left === 0) ||
            ($subscription_status === 'active' && $subscription_days_left === 0)
        );

        // ✅ Check if upgrade button should be shown (trial with 7 days or less)
        $show_upgrade_button = !$renewal_requested && ($subscription_status === 'trial' && $trial_days_left <= 7 && $trial_days_left > 0);

        // 🐛 DEBUG: Log button visibility

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

        // ✅ Check if scanner user (don't show subscription info to scanners)
        $is_scanner = class_exists('PPV_Permissions') && PPV_Permissions::is_scanner_user();

        if (!$is_scanner): ?>
        <div class="ppv-trial-info-block" style="margin-bottom: 10px; padding: 8px 12px; background: <?php echo $info_color; ?>; border-radius: 8px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px;"><?php echo $info_icon; ?></span>
                    <div>
                        <div style="font-weight: 600; font-size: 13px;">
                            <?php echo $info_message; ?>
                        </div>
                        <?php if ($renewal_requested): ?>
                            <div style="font-size: 11px; opacity: 0.9; color: #00e6ff;">
                                <?php echo self::t('renewal_in_progress', 'Verlängerung in Bearbeitung'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($show_upgrade_button): ?>
                    <button id="ppv-request-renewal-btn" class="ppv-btn-outline" style="padding: 4px 10px; font-size: 11px; white-space: nowrap;">
                        <?php echo self::t('upgrade_now', 'Upgrade'); ?>
                    </button>
                <?php elseif ($show_renewal_button): ?>
                    <button id="ppv-request-renewal-btn" class="ppv-btn-outline" style="padding: 4px 10px; font-size: 11px; white-space: nowrap;">
                        <?php echo self::t('request_renewal', 'Verlängern'); ?>
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

            <div class="ppv-pos-header" style="margin-bottom: 8px;">
                <h4 class="ppv-pos-title" style="font-size: 14px; margin: 0;"><?php echo self::t('table_title', '📋 Letzte Scans'); ?></h4>
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

            <!-- 📥 CSV EXPORT DROPDOWN - at bottom -->
            <div class="ppv-csv-wrapper" style="margin-top: 12px; text-align: center;">
                <button id="ppv-csv-export-btn" class="ppv-btn ppv-csv-btn" style="padding: 8px 14px; font-size: 12px;">
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

            <!-- 🎥 CAMERA SCANNER MODAL -->
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

    // ============================================================
    // 🏪 FILIALE SWITCHER
    // ============================================================
    private static function render_filiale_switcher() {
        global $wpdb;

        // ✅ SCANNER USERS: Don't show filiale switcher
        if (class_exists('PPV_Permissions') && PPV_Permissions::is_scanner_user()) {
            return;
        }

        // Get current store ID from session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 🏪 FILIALE SUPPORT: Check all possible store ID sources
        $current_filiale_id = 0;
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $current_filiale_id = intval($_SESSION['ppv_current_filiale_id']);
        } elseif (!empty($_SESSION['ppv_store_id'])) {
            $current_filiale_id = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_vendor_store_id'])) {
            // Trial handlers may only have vendor_store_id set
            $current_filiale_id = intval($_SESSION['ppv_vendor_store_id']);
        }

        if (!$current_filiale_id) {
            ppv_log("⚠️ [PPV_QR] render_filiale_switcher: No store_id found in session");
            return; // No store in session
        }

        ppv_log("🏪 [PPV_QR] render_filiale_switcher: current_filiale_id={$current_filiale_id}");

        // Get parent store ID (if current is a filiale, get its parent; otherwise it's the parent)
        $parent_id = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(parent_store_id, id) FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $current_filiale_id
        ));

        if (!$parent_id) {
            return;
        }

        // Get all filialen belonging to this parent (including parent itself)
        $filialen = $wpdb->get_results($wpdb->prepare("
            SELECT id, name, city, parent_store_id
            FROM {$wpdb->prefix}ppv_stores
            WHERE id=%d OR parent_store_id=%d
            ORDER BY CASE WHEN id=%d THEN 0 ELSE 1 END, name ASC
        ", $parent_id, $parent_id, $parent_id));

        // Always show switcher (even if only 1 location, to allow adding new)
        ?>
        <div class="ppv-filiale-switcher" style="margin-bottom: 10px; padding: 10px 12px;">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <label for="ppv-filiale-select" style="font-weight: 600; margin: 0; font-size: 12px;">
                    <i class="ri-store-2-line"></i> <?php echo self::t('current_filiale', 'Filiale'); ?>:
                </label>

                <select id="ppv-filiale-select" style="flex: 1; min-width: 140px; padding: 6px 10px; font-size: 13px; border-radius: 6px; border: 1px solid var(--border-color, #e0e0e0); background: var(--bg-secondary, #fff); color: var(--text-primary, #333);">
                    <?php foreach ($filialen as $filiale): ?>
                        <option value="<?php echo esc_attr($filiale->id); ?>" <?php selected($filiale->id, $current_filiale_id); ?>>
                            <?php
                            echo esc_html($filiale->name);
                            // Show "Main Location" label for parent store
                            if ($filiale->id == $parent_id && (empty($filiale->parent_store_id) || $filiale->parent_store_id === null)) {
                                echo ' (' . self::t('main_location', 'Haupt') . ')';
                            }
                            if (!empty($filiale->city)) {
                                echo ' - ' . esc_html($filiale->city);
                            }
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button id="ppv-add-filiale-btn" class="ppv-btn" type="button" style="white-space: nowrap; padding: 6px 10px; font-size: 12px;">
                    <i class="ri-add-line"></i> <?php echo self::t('add_filiale', 'Neu'); ?>
                </button>
            </div>

            <div id="ppv-filiale-message" style="margin-top: 15px; padding: 10px; border-radius: 8px; display: none;"></div>
        </div>

        <!-- Add Filiale Modal -->
        <div id="ppv-add-filiale-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
            <div style="background: #1a1a2e; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                <h3 style="margin-top: 0; color: #fff;"><i class="ri-add-circle-line"></i> <?php echo self::t('add_new_filiale', 'Neue Filiale hinzufügen'); ?></h3>

                <label style="display: block; margin-bottom: 5px; color: #ccc; font-size: 14px;">
                    <?php echo self::t('filiale_name', 'Name der Filiale'); ?> <span style="color: #ff5252;">*</span>
                </label>
                <input type="text" id="ppv-new-filiale-name" class="ppv-input" placeholder="<?php echo esc_attr(self::t('filiale_name_placeholder', 'z.B. Wien Filiale 1')); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">

                <label style="display: block; margin-bottom: 5px; color: #ccc; font-size: 14px;">
                    <?php echo self::t('filiale_city', 'Stadt'); ?>
                </label>
                <input type="text" id="ppv-new-filiale-city" class="ppv-input" placeholder="<?php echo esc_attr(self::t('filiale_city_placeholder', 'z.B. Wien')); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">

                <label style="display: block; margin-bottom: 5px; color: #ccc; font-size: 14px;">
                    <?php echo self::t('filiale_plz', 'PLZ'); ?>
                </label>
                <input type="text" id="ppv-new-filiale-plz" class="ppv-input" placeholder="<?php echo esc_attr(self::t('filiale_plz_placeholder', 'z.B. 1010')); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">

                <div id="ppv-add-filiale-error" style="display: none; color: #ff5252; font-size: 13px; margin-bottom: 10px;"></div>

                <div style="display: flex; gap: 10px;">
                    <button id="ppv-save-filiale-btn" class="ppv-btn" style="flex: 1; padding: 12px;">
                        ✅ <?php echo self::t('save', 'Speichern'); ?>
                    </button>
                    <button id="ppv-cancel-filiale-btn" class="ppv-btn-outline" style="flex: 1; padding: 12px;">
                        ❌ <?php echo self::t('cancel', 'Abbrechen'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Contact Modal for Filiale Limit Reached -->
        <div id="ppv-filiale-contact-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
            <div style="background: #1a1a2e; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                <h3 style="margin-top: 0; color: #fff;"><i class="ri-building-line"></i> <?php echo self::t('more_filialen_title', 'Mehr Filialen benötigt?'); ?></h3>

                <p style="color: #ccc; font-size: 14px; margin-bottom: 20px;">
                    <?php echo self::t('more_filialen_desc', 'Sie haben das Maximum an Filialen erreicht. Kontaktieren Sie uns, um weitere Filialen freizuschalten!'); ?>
                </p>

                <div id="ppv-filiale-limit-info" style="background: rgba(255,82,82,0.1); border: 1px solid rgba(255,82,82,0.3); border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                    <span style="color: #ff5252; font-size: 13px;"><i class="ri-information-line"></i> <span id="ppv-filiale-limit-text"></span></span>
                </div>

                <label style="display: block; margin-bottom: 5px; color: #ccc; font-size: 14px;">
                    <?php echo self::t('contact_email', 'E-Mail'); ?>
                </label>
                <input type="email" id="ppv-contact-email" class="ppv-input" placeholder="<?php echo esc_attr(self::t('contact_email_placeholder', 'Ihre E-Mail-Adresse')); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">

                <label style="display: block; margin-bottom: 5px; color: #ccc; font-size: 14px;">
                    <?php echo self::t('contact_phone', 'Telefon'); ?>
                </label>
                <input type="tel" id="ppv-contact-phone" class="ppv-input" placeholder="<?php echo esc_attr(self::t('contact_phone_placeholder', 'Ihre Telefonnummer')); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">

                <label style="display: block; margin-bottom: 5px; color: #ccc; font-size: 14px;">
                    <?php echo self::t('contact_message', 'Nachricht (optional)'); ?>
                </label>
                <textarea id="ppv-contact-message" class="ppv-input" placeholder="<?php echo esc_attr(self::t('contact_message_placeholder', 'Wie viele Filialen benötigen Sie?')); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px; min-height: 80px; resize: vertical;"></textarea>

                <div id="ppv-contact-error" style="display: none; color: #ff5252; font-size: 13px; margin-bottom: 10px;"></div>
                <div id="ppv-contact-success" style="display: none; color: #4caf50; font-size: 13px; margin-bottom: 10px;"></div>

                <div style="display: flex; gap: 10px;">
                    <button id="ppv-send-contact-btn" class="ppv-btn" style="flex: 1; padding: 12px;">
                        📧 <?php echo self::t('send_request', 'Anfrage senden'); ?>
                    </button>
                    <button id="ppv-cancel-contact-btn" class="ppv-btn-outline" style="flex: 1; padding: 12px;">
                        ❌ <?php echo self::t('cancel', 'Abbrechen'); ?>
                    </button>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            // Switch filiale on dropdown change
            $('#ppv-filiale-select').on('change', function(){
                const filialeId = $(this).val();
                const $select = $(this);
                const originalValue = $select.data('original-value') || $select.val();

                $select.prop('disabled', true);

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppv_switch_filiale',
                        filiale_id: filialeId,
                        nonce: '<?php echo wp_create_nonce('ppv_filiale_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Reload page to update all data
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php echo esc_js(self::t('switch_error', 'Fehler beim Wechseln')); ?>');
                            $select.val(originalValue);
                            $select.prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>');
                        $select.val(originalValue);
                        $select.prop('disabled', false);
                    }
                });
            });

            // Store original value for rollback
            $('#ppv-filiale-select').data('original-value', $('#ppv-filiale-select').val());

            // Show add filiale modal (check limit first)
            $('#ppv-add-filiale-btn').on('click', function(){
                const $btn = $(this);
                $btn.prop('disabled', true);

                // Check filiale limit first
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppv_check_filiale_limit',
                        parent_store_id: <?php echo intval($parent_id); ?>
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);

                        if (response.success && response.data.can_add) {
                            // Can add more - show add modal
                            $('#ppv-add-filiale-modal').fadeIn(200).css('display', 'flex');
                            $('#ppv-new-filiale-name').val('').focus();
                            $('#ppv-new-filiale-city').val('');
                            $('#ppv-new-filiale-plz').val('');
                            $('#ppv-add-filiale-error').hide();
                        } else {
                            // Limit reached - show contact modal
                            const current = response.data?.current || 1;
                            const max = response.data?.max || 1;
                            $('#ppv-filiale-limit-text').text('<?php echo esc_js(self::t('filiale_limit_info', 'Aktuell')); ?>: ' + current + ' / ' + max + ' <?php echo esc_js(self::t('filialen', 'Filialen')); ?>');
                            $('#ppv-filiale-contact-modal').fadeIn(200).css('display', 'flex');
                            $('#ppv-contact-email').val('').focus();
                            $('#ppv-contact-phone').val('');
                            $('#ppv-contact-message').val('');
                            $('#ppv-contact-error').hide();
                            $('#ppv-contact-success').hide();
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false);
                        // On error, just show the add modal (server will check limit again)
                        $('#ppv-add-filiale-modal').fadeIn(200).css('display', 'flex');
                        $('#ppv-new-filiale-name').val('').focus();
                    }
                });
            });

            // Hide add filiale modal
            $('#ppv-cancel-filiale-btn').on('click', function(){
                $('#ppv-add-filiale-modal').fadeOut(200);
            });

            // Hide contact modal
            $('#ppv-cancel-contact-btn').on('click', function(){
                $('#ppv-filiale-contact-modal').fadeOut(200);
            });

            // Send contact request
            $('#ppv-send-contact-btn').on('click', function(){
                const email = $('#ppv-contact-email').val().trim();
                const phone = $('#ppv-contact-phone').val().trim();
                const message = $('#ppv-contact-message').val().trim();
                const $btn = $(this);
                const $error = $('#ppv-contact-error');
                const $success = $('#ppv-contact-success');

                $error.hide();
                $success.hide();

                if (!email && !phone) {
                    $error.text('<?php echo esc_js(self::t('contact_required', 'Bitte geben Sie eine E-Mail oder Telefonnummer an')); ?>').show();
                    return;
                }

                $btn.prop('disabled', true).text('<?php echo esc_js(self::t('sending', 'Senden...')); ?>');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppv_request_more_filialen',
                        parent_store_id: <?php echo intval($parent_id); ?>,
                        contact_email: email,
                        contact_phone: phone,
                        message: message
                    },
                    success: function(response) {
                        if (response.success) {
                            $success.text(response.data?.msg || '<?php echo esc_js(self::t('request_sent', 'Anfrage erfolgreich gesendet!')); ?>').show();
                            $btn.text('✅ <?php echo esc_js(self::t('sent', 'Gesendet')); ?>');
                            setTimeout(function(){
                                $('#ppv-filiale-contact-modal').fadeOut(200);
                                $btn.prop('disabled', false).html('📧 <?php echo esc_js(self::t('send_request', 'Anfrage senden')); ?>');
                            }, 2000);
                        } else {
                            $error.text(response.data?.msg || '<?php echo esc_js(self::t('send_error', 'Fehler beim Senden')); ?>').show();
                            $btn.prop('disabled', false).html('📧 <?php echo esc_js(self::t('send_request', 'Anfrage senden')); ?>');
                        }
                    },
                    error: function() {
                        $error.text('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>').show();
                        $btn.prop('disabled', false).html('📧 <?php echo esc_js(self::t('send_request', 'Anfrage senden')); ?>');
                    }
                });
            });

            // Save new filiale
            $('#ppv-save-filiale-btn').on('click', function(){
                const name = $('#ppv-new-filiale-name').val().trim();
                const city = $('#ppv-new-filiale-city').val().trim();
                const plz = $('#ppv-new-filiale-plz').val().trim();
                const $btn = $(this);
                const $error = $('#ppv-add-filiale-error');

                $error.hide();

                if (!name) {
                    $error.text('<?php echo esc_js(self::t('name_required', 'Name ist erforderlich')); ?>').show();
                    return;
                }

                $btn.prop('disabled', true).text('<?php echo esc_js(self::t('saving', 'Speichern...')); ?>');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppv_create_filiale',
                        parent_store_id: <?php echo intval($parent_id); ?>,
                        filiale_name: name,
                        city: city,
                        plz: plz,
                        nonce: '<?php echo wp_create_nonce('ppv_filiale_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            // Check if limit was reached
                            if (response.data?.limit_reached) {
                                // Close add modal and show contact modal
                                $('#ppv-add-filiale-modal').fadeOut(200);
                                const current = response.data?.current || 1;
                                const max = response.data?.max || 1;
                                $('#ppv-filiale-limit-text').text('<?php echo esc_js(self::t('filiale_limit_info', 'Aktuell')); ?>: ' + current + ' / ' + max + ' <?php echo esc_js(self::t('filialen', 'Filialen')); ?>');
                                setTimeout(function(){
                                    $('#ppv-filiale-contact-modal').fadeIn(200).css('display', 'flex');
                                    $('#ppv-contact-email').val('').focus();
                                }, 200);
                                $btn.prop('disabled', false).html('✅ <?php echo esc_js(self::t('save', 'Speichern')); ?>');
                            } else {
                                $error.text(response.data?.msg || '<?php echo esc_js(self::t('save_error', 'Fehler beim Speichern')); ?>').show();
                                $btn.prop('disabled', false).html('✅ <?php echo esc_js(self::t('save', 'Speichern')); ?>');
                            }
                        }
                    },
                    error: function() {
                        $error.text('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>').show();
                        $btn.prop('disabled', false).html('✅ <?php echo esc_js(self::t('save', 'Speichern')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
