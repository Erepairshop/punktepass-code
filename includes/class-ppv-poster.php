<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('PPV_Poster')) {

class PPV_Poster {

    public static function hooks() {
        // ❌ Nincs automatikus betöltés – kézzel hívjuk a QR modulból
        add_action('wp_ajax_ppv_save_poster', [__CLASS__, 'ajax_save_poster']);
    }

    /** 🔹 Assets – kézzel hívható, mindig friss verzióval */
    public static function enqueue_assets() {
        // Cache tiltás fejlesztéshez
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        wp_enqueue_style(
            'ppv-poster',
            PPV_PLUGIN_URL . 'assets/css/ppv-poster.css',
            [],
            time()
        );

        wp_enqueue_script(
            'html2canvas',
            'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js',
            [],
            null,
            true
        );

        wp_enqueue_script(
            'ppv-poster',
            PPV_PLUGIN_URL . 'assets/js/ppv-poster.js',
            ['jquery', 'html2canvas'],
            time(),
            true
        );

        $__data = is_array([
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ppv_poster_nonce')
        ] ?? null) ? [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ppv_poster_nonce')
        ] : [];
$__json = wp_json_encode($__data);
wp_add_inline_script('ppv-poster', "window.PPV_POSTER = {$__json};", 'before');
    }

    /** 🔹 Poster fül megjelenítés */
    public static function render_poster($store) {
        global $wpdb;
        if (!$store) {
            echo '<p>⚠️ Kein Store gefunden.</p>';
            return;
        }

        $store_name   = esc_html($store->name ?? '');
        $city         = esc_html($store->city ?? '');
        $plz          = esc_html($store->plz ?? '');
        $slug         = esc_html($store->store_slug ?? '');
        $poster_title = $store_name . ($city ? " – {$plz} {$city}" : '');
        $qr_url       = esc_url(home_url('/store/' . $slug));
        $qr_img       = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=' . rawurlencode($qr_url);
        $logo         = PPV_PLUGIN_URL . 'assets/img/punktepass-poster-logo.png';

        $template = $store->poster_template ?? 'hell';
        $text     = esc_html($store->poster_text ?? '');
        ?>
        <div class="ppv-poster-config">
            <label>Poster-Stil:</label>
            <div class="ppv-radio-group">
                <label><input type="radio" name="poster_template" value="hell" <?php checked($template, 'hell'); ?>> Hell</label>
                <label><input type="radio" name="poster_template" value="dunkel" <?php checked($template, 'dunkel'); ?>> Dunkel</label>
                <label><input type="radio" name="poster_template" value="neon" <?php checked($template, 'neon'); ?>> Neon</label>
            </div>

            <label for="poster_text">Eigener Text:</label>
            <input id="poster_text" type="text" maxlength="100" value="<?php echo $text; ?>">
            <button id="poster_save" class="ppv-btn neon">💾 Speichern</button>
            <span id="poster_saved" style="display:none;color:#28a745;font-weight:bold;">Gespeichert ✅</span>
        </div>

        <div class="ppv-poster-container">
            <div id="ppv-poster-preview" class="ppv-poster-preview <?php echo esc_attr($template); ?>">
                <img class="ppv-logo" src="<?php echo esc_url($logo); ?>" alt="PunktePass Logo">
                <h2 class="ppv-store-name"><?php echo $poster_title; ?></h2>
                <p class="ppv-slogan">Sammle Punkte – erhalte Belohnungen!</p>
                <?php if ($text): ?>
                    <p class="ppv-custom"><?php echo $text; ?></p>
                <?php endif; ?>
                <img class="ppv-poster-qr" src="<?php echo esc_url($qr_img . '?v=' . time()); ?>" alt="QR Code">
                <p class="ppv-url"><?php echo $qr_url; ?></p>
            </div>

            <div class="ppv-poster-actions">
                <button id="ppv-poster-download" class="ppv-btn neon">💾 Poster herunterladen (PNG)</button>
                <button id="ppv-poster-print" class="ppv-btn neon">🖨️ Drucken</button>
            </div>
        </div>
        <?php
    }

    /** 🔹 AJAX mentés */
    public static function ajax_save_poster() {
        check_ajax_referer('ppv_poster_nonce', 'nonce');
        global $wpdb;

        $uid = get_current_user_id();
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
            $uid
        ));

        if (!$store) {
            wp_send_json_error(['msg' => 'Kein Store gefunden.']);
        }

        $template = sanitize_text_field($_POST['template'] ?? 'hell');
        $text     = sanitize_text_field($_POST['text'] ?? '');

        $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            ['poster_template' => $template, 'poster_text' => $text],
            ['id' => $store->id]
        );

        wp_send_json_success(['msg' => 'Gespeichert ✅']);
    }
}

} // class_exists
