<?php
if (!defined('ABSPATH')) exit;

class PPV_Campaigns {

    public static function hooks() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // AJAX CRUD funkciók
        add_action('wp_ajax_ppv_save_campaign', [__CLASS__, 'ajax_save_campaign']);
        add_action('wp_ajax_ppv_delete_campaign', [__CLASS__, 'ajax_delete_campaign']);
        add_action('wp_ajax_ppv_toggle_campaign', [__CLASS__, 'ajax_toggle_campaign']);
        add_action('wp_ajax_ppv_update_campaign', [__CLASS__, 'ajax_update_campaign']);
        add_action('wp_ajax_ppv_duplicate_campaign', [__CLASS__, 'ajax_duplicate_campaign']);
    }

    /** ============================================================
     *  🏪 GET VENDOR STORE ID (parent store for multi-filiale)
     * ============================================================ */
    private static function get_vendor_store_id() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            return intval($_SESSION['ppv_vendor_store_id']);
        }
        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }
        return 0;
    }

    /** ============================================================
     *  🏪 GET ALL FILIALEN FOR VENDOR
     * ============================================================ */
    private static function get_filialen_for_vendor() {
        global $wpdb;
        $vendor_store_id = self::get_vendor_store_id();
        if (!$vendor_store_id) return [];

        $filialen = $wpdb->get_results($wpdb->prepare("
            SELECT id, name, company_name, address, city, plz
            FROM {$wpdb->prefix}ppv_stores
            WHERE id = %d OR parent_store_id = %d
            ORDER BY (id = %d) DESC, name ASC
        ", $vendor_store_id, $vendor_store_id, $vendor_store_id));

        return $filialen ?: [];
    }

    /** ============================================================
     *  🔐 GET STORE ID (with FILIALE support)
     * ============================================================ */
    private static function get_store_id() {
        global $wpdb;

        // 🔐 Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            return intval($_SESSION['ppv_current_filiale_id']);
        }

        // Session - base store
        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }

        // Fallback: vendor store
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            return intval($_SESSION['ppv_vendor_store_id']);
        }

        // Fallback: WordPress user (rare case)
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                $uid
            ));
            if ($store_id) {
                return intval($store_id);
            }
        }

        return 0;
    }

    /** === CSS + JS betöltése === */
    public static function enqueue_assets() {
        // CSS: global theme-light.css használata (nincs külön campaigns CSS)
        wp_enqueue_script('ppv-campaigns', PPV_PLUGIN_URL . 'assets/js/ppv-campaigns.js', ['jquery'], time(), true);
        $__data = is_array([
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ppv_campaigns_nonce')
        ] ?? null) ? [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ppv_campaigns_nonce')
        ] : [];
$__json = wp_json_encode($__data);
wp_add_inline_script('ppv-campaigns', "window.ppv_campaigns = {$__json};", 'before');
    }

    /** === Kampánylista renderelése === */
    public static function render_campaigns($store) {
        if (!$store) {
            echo "<p>⚠️ Kein Store gefunden.</p>";
            return;
        }

        global $wpdb;
        $today = current_time('Y-m-d');

        // 🏪 FILIALE SUPPORT: Get all filialen for selector
        $filialen = self::get_filialen_for_vendor();
        $has_multiple_filialen = count($filialen) > 1;

        // 🏪 FILIALE SUPPORT: Get actual store ID from session (not passed parameter!)
        $store_id = self::get_store_id();
        if (!$store_id) {
            echo "<p>⚠️ Kein Store gefunden (Session).</p>";
            return;
        }

        // lejárt kampányok automatikus inaktiválása
        $wpdb->query($wpdb->prepare("
            UPDATE {$wpdb->prefix}ppv_campaigns
            SET status='expired'
            WHERE store_id=%d AND end_date < %s AND status != 'expired'
        ", $store_id, $today));

        // 🏪 FILIALE SUPPORT: Only show campaigns for current filiale/store
        $campaigns = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_campaigns WHERE store_id=%d ORDER BY created_at DESC
        ", $store_id));

        ?>
        <div class="ppv-campaigns">
            <div class="ppv-campaigns-header">
                <h3>🎯 Aktive Kampagnen</h3>
                <button class="ppv-btn neon" id="ppv-new-campaign-btn">+ Neue Kampagne starten</button>
            </div>

            <div class="ppv-campaign-list">
                <?php if ($campaigns): foreach ($campaigns as $c): ?>
                    <div class="ppv-campaign-card <?php echo esc_attr($c->status); ?>">
                        <h4><?php echo esc_html($c->title); ?></h4>
                        <p><?php echo esc_html($c->description); ?></p>
                        <p>⭐ <b><?php echo esc_html($c->multiplier); ?>x Punkte</b>, +<?php echo esc_html($c->extra_points); ?> Bonuspunkte</p>
                        <?php if ($c->discount_percent > 0): ?>
                            <p>💸 Rabatt: <?php echo esc_html($c->discount_percent); ?>%</p>
                        <?php endif; ?>
                        <span class="ppv-campaign-dates">
                            📅 <?php echo esc_html($c->start_date); ?> – <?php echo esc_html($c->end_date); ?>
                        </span>
                        <div class="ppv-campaign-status">
                            <?php
                            if ($c->status === 'active') echo '✅ Aktiv';
                            elseif ($c->status === 'inactive') echo '⏸️ Inaktiv';
                            else echo '❌ Abgelaufen';
                            ?>
                        </div>
                        <div class="ppv-campaign-actions">
                            <button class="ppv-btn small toggle-campaign" data-id="<?php echo $c->id; ?>">
                                <?php echo $c->status === 'active' ? 'Deaktivieren' : 'Aktivieren'; ?>
                            </button>
                            <button class="ppv-btn small edit-campaign" data-id="<?php echo $c->id; ?>">✏️ Bearbeiten</button>
                            <button class="ppv-btn small duplicate-campaign" data-id="<?php echo $c->id; ?>">📄 Duplizieren</button>
                            <button class="ppv-btn small danger delete-campaign" data-id="<?php echo $c->id; ?>">Löschen</button>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <p>Keine Kampagnen vorhanden.</p>
                <?php endif; ?>
            </div>

            <!-- Kampagne Modal -->
            <div id="ppv-campaigns-admin-modal" class="ppv-modal">
                <div class="ppv-modal-content">
                    <span class="ppv-close">&times;</span>
                    <h3 id="ppv-modal-title">+ Neue Kampagne</h3>
                    <form id="ppv-campaign-form">
                        <input type="hidden" name="id" id="campaign-id" value="">
                        
                        <label>Name der Kampagne</label>
                        <input type="text" name="title" id="campaign-title" required>

                        <label>Beschreibung</label>
                        <textarea name="description" id="campaign-description" placeholder="z. B. Doppelte Punkte auf alles!"></textarea>

                        <label>Punkt-Faktor (x)</label>
                        <input type="number" name="multiplier" id="campaign-multiplier" min="1" value="1">

                        <label>Extra Punkte pro Scan</label>
                        <input type="number" name="extra_points" id="campaign-extra" min="0" value="0">

                        <label>Tägliches Punkte-Limit</label>
                        <input type="number" name="daily_limit" id="campaign-daily-limit" min="0" value="0">

                        <label>Rabatt (%) (optional)</label>
                        <input type="number" name="discount_percent" id="campaign-discount" min="0" step="0.1" value="0">

                        <label>Typ</label>
                        <select name="campaign_type" id="campaign-type">
                            <option value="points">Punkte</option>
                            <option value="discount">Rabatt</option>
                        </select>

                        <?php if ($has_multiple_filialen): ?>
                        <!-- 🏪 FILIALE SELECTOR -->
                        <div class="ppv-filiale-selector" style="margin-top: 16px; padding: 12px; background: rgba(0,230,255,0.05); border-radius: 8px; border: 1px solid rgba(0,230,255,0.2);">
                            <label style="color: #00e6ff; font-weight: 500;"><?php echo class_exists('PPV_Lang') ? PPV_Lang::t('campaigns_form_filiale') : 'Für welche Filiale(n)?'; ?></label>
                            <select name="target_store_id" id="campaign-target-store" style="margin-top: 8px;">
                                <option value="current"><?php echo class_exists('PPV_Lang') ? PPV_Lang::t('campaigns_form_filiale_current') : 'Nur diese Filiale'; ?></option>
                                <?php foreach ($filialen as $fil): ?>
                                    <option value="<?php echo intval($fil->id); ?>">
                                        <?php echo esc_html($fil->company_name ?: $fil->name); ?>
                                        <?php if ($fil->city): ?>(<?php echo esc_html($fil->city); ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label style="display: flex; align-items: center; gap: 8px; margin-top: 12px; cursor: pointer;">
                                <input type="checkbox" name="apply_to_all" id="campaign-apply-all" value="1" style="width: 18px; height: 18px;">
                                <span><?php echo class_exists('PPV_Lang') ? PPV_Lang::t('campaigns_form_apply_all') : 'Auf alle Filialen anwenden'; ?></span>
                            </label>
                            <small style="color: #999; display: block; margin-top: 4px;"><?php echo class_exists('PPV_Lang') ? PPV_Lang::t('campaigns_form_apply_all_hint') : 'Diese Kampagne wird bei allen Filialen erstellt'; ?></small>
                        </div>
                        <?php endif; ?>

                        <label>Startdatum</label>
                        <input type="date" name="start" id="campaign-start">

                        <label>Enddatum</label>
                        <input type="date" name="end" id="campaign-end">

                        <button type="submit" class="ppv-btn neon">💾 Speichern</button>
                        <button type="button" class="ppv-btn-outline ppv-close">Abbrechen</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /** === Kampagne mentése === */
    public static function ajax_save_campaign() {
        check_ajax_referer('ppv_campaigns_nonce', 'nonce');
        global $wpdb;

        // 🏪 FILIALE SUPPORT: Get store ID from session
        $store_id = self::get_store_id();
        if (!$store_id) {
            wp_send_json_error(['msg' => 'Kein Store gefunden.']);
        }

        // Verify store exists and is active
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store_id
        ));
        if (!$store) {
            wp_send_json_error(['msg' => 'Store nicht gefunden.']);
        }

        // ✅ FIX: Validate campaign_type, reject empty
        $campaign_type = sanitize_text_field($_POST['campaign_type'] ?? '');
        if (empty($campaign_type)) {
            error_log("⚠️ [PPV_Campaigns] Empty campaign_type in AJAX save, defaulting to 'points'");
            $campaign_type = 'points';
        }

        // 🏪 FILIALE SUPPORT: Check if apply to all or specific store
        $apply_to_all = !empty($_POST['apply_to_all']) && $_POST['apply_to_all'] === '1';
        $target_store_id = sanitize_text_field($_POST['target_store_id'] ?? 'current');

        // Determine which stores to create campaign for
        $target_stores = [];
        if ($apply_to_all) {
            // Get all filialen for this vendor
            $filialen = self::get_filialen_for_vendor();
            foreach ($filialen as $fil) {
                $target_stores[] = intval($fil->id);
            }
        } elseif ($target_store_id !== 'current' && is_numeric($target_store_id)) {
            // Specific filiale selected - verify vendor owns it
            $filialen = self::get_filialen_for_vendor();
            $allowed_ids = array_map(function($f) { return intval($f->id); }, $filialen);
            if (in_array(intval($target_store_id), $allowed_ids)) {
                $target_stores[] = intval($target_store_id);
            } else {
                $target_stores[] = $store_id; // Fallback to current store
            }
        } else {
            // Current store only
            $target_stores[] = $store_id;
        }

        // Create campaign for each target store
        $created_count = 0;
        foreach ($target_stores as $target_id) {
            $data = [
                'store_id'        => $target_id,
                'title'           => sanitize_text_field($_POST['title']),
                'description'     => sanitize_textarea_field($_POST['description']),
                'multiplier'      => intval($_POST['multiplier']),
                'extra_points'    => intval($_POST['extra_points']),
                'daily_limit'     => intval($_POST['daily_limit']),
                'discount_percent'=> floatval($_POST['discount_percent']),
                'campaign_type'   => $campaign_type,
                'start_date'      => sanitize_text_field($_POST['start']),
                'end_date'        => sanitize_text_field($_POST['end']),
                'status'          => 'active',
                'created_at'      => current_time('mysql')
            ];

            $wpdb->insert($wpdb->prefix . 'ppv_campaigns', $data);
            if ($wpdb->insert_id) {
                $created_count++;
            }
        }

        // Return appropriate message
        if ($created_count > 1) {
            $msg = class_exists('PPV_Lang')
                ? sprintf(PPV_Lang::t('campaigns_saved_multiple'), $created_count)
                : sprintf('Kampagne bei %d Filialen erstellt.', $created_count);
        } else {
            $msg = 'Kampagne gespeichert';
        }

        wp_send_json_success(['msg' => $msg]);
    }

    /** === Frissítés === */
    public static function ajax_update_campaign() {
        check_ajax_referer('ppv_campaigns_nonce', 'nonce');
        global $wpdb;

        $id = intval($_POST['id']);

        // 🏪 FILIALE SUPPORT: Get store ID from session
        $store_id = self::get_store_id();
        if (!$store_id) {
            wp_send_json_error(['msg' => 'Kein Store gefunden.']);
        }

        // 🔒 SECURITY: Verify campaign belongs to handler's store/filiale
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT id, store_id FROM {$wpdb->prefix}ppv_campaigns WHERE id=%d LIMIT 1",
            $id
        ));
        if (!$campaign || $campaign->store_id != $store_id) {
            wp_send_json_error(['msg' => 'Keine Berechtigung für diese Kampagne.']);
        }

        // ✅ FIX: Validate campaign_type, reject empty
        $campaign_type = sanitize_text_field($_POST['campaign_type'] ?? '');
        if (empty($campaign_type)) {
            error_log("⚠️ [PPV_Campaigns] Empty campaign_type in AJAX update, defaulting to 'points'");
            $campaign_type = 'points';
        }

        $wpdb->update($wpdb->prefix . 'ppv_campaigns', [
            'title'           => sanitize_text_field($_POST['title']),
            'description'     => sanitize_textarea_field($_POST['description']),
            'multiplier'      => intval($_POST['multiplier']),
            'extra_points'    => intval($_POST['extra_points']),
            'daily_limit'     => intval($_POST['daily_limit']),
            'discount_percent'=> floatval($_POST['discount_percent']),
            'campaign_type'   => $campaign_type,
            'start_date'      => sanitize_text_field($_POST['start']),
            'end_date'        => sanitize_text_field($_POST['end'])
        ], ['id' => $id]);

        wp_send_json_success(['msg' => 'Kampagne aktualisiert']);
    }

    /** === Aktív / inaktív váltás === */
    public static function ajax_toggle_campaign() {
        check_ajax_referer('ppv_campaigns_nonce', 'nonce');
        global $wpdb;
        $id = intval($_POST['id']);

        // 🏪 FILIALE SUPPORT: Get store ID from session
        $store_id = self::get_store_id();
        if (!$store_id) {
            wp_send_json_error(['msg' => 'Kein Store gefunden.']);
        }

        // 🔒 SECURITY: Verify campaign belongs to handler's store/filiale
        $c = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status, store_id FROM {$wpdb->prefix}ppv_campaigns WHERE id=%d LIMIT 1",
            $id
        ));
        if (!$c) {
            wp_send_json_error(['msg' => 'Nicht gefunden']);
        }
        if ($c->store_id != $store_id) {
            wp_send_json_error(['msg' => 'Keine Berechtigung für diese Kampagne.']);
        }

        $new_status = $c->status === 'active' ? 'inactive' : 'active';
        $wpdb->update($wpdb->prefix . 'ppv_campaigns', ['status' => $new_status], ['id' => $id]);
        wp_send_json_success(['msg' => 'Status geändert']);
    }

    /** === Törlés === */
    public static function ajax_delete_campaign() {
        check_ajax_referer('ppv_campaigns_nonce', 'nonce');
        global $wpdb;
        $id = intval($_POST['id']);

        // 🏪 FILIALE SUPPORT: Get store ID from session
        $store_id = self::get_store_id();
        if (!$store_id) {
            wp_send_json_error(['msg' => 'Kein Store gefunden.']);
        }

        // 🔒 SECURITY: Verify campaign belongs to handler's store/filiale
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT id, store_id FROM {$wpdb->prefix}ppv_campaigns WHERE id=%d LIMIT 1",
            $id
        ));
        if (!$campaign || $campaign->store_id != $store_id) {
            wp_send_json_error(['msg' => 'Keine Berechtigung für diese Kampagne.']);
        }

        $wpdb->delete($wpdb->prefix . 'ppv_campaigns', ['id' => $id]);
        wp_send_json_success(['msg' => 'Kampagne gelöscht']);
    }

    /** === Duplikálás === */
    public static function ajax_duplicate_campaign() {
        check_ajax_referer('ppv_campaigns_nonce', 'nonce');
        global $wpdb;
        $id = intval($_POST['id']);

        // 🏪 FILIALE SUPPORT: Get store ID from session
        $store_id = self::get_store_id();
        if (!$store_id) {
            wp_send_json_error(['msg' => 'Kein Store gefunden.']);
        }

        // 🔒 SECURITY: Verify campaign belongs to handler's store/filiale
        $c = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_campaigns WHERE id=%d LIMIT 1",
            $id
        ));
        if (!$c) {
            wp_send_json_error(['msg' => 'Nicht gefunden']);
        }
        if ($c->store_id != $store_id) {
            wp_send_json_error(['msg' => 'Keine Berechtigung für diese Kampagne.']);
        }

        $wpdb->insert($wpdb->prefix . 'ppv_campaigns', [
            'store_id'        => $c->store_id,
            'title'           => $c->title . ' (Kopie)',
            'description'     => $c->description,
            'multiplier'      => $c->multiplier,
            'extra_points'    => $c->extra_points,
            'daily_limit'     => $c->daily_limit,
            'discount_percent'=> $c->discount_percent,
            'campaign_type'   => $c->campaign_type,
            'start_date'      => $c->start_date,
            'end_date'        => $c->end_date,
            'status'          => 'inactive',
            'created_at'      => current_time('mysql')
        ]);

        wp_send_json_success(['msg' => 'Kampagne dupliziert']);
    }
}
