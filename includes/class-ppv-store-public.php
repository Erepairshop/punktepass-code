<?php
if (!defined('ABSPATH')) exit;

class PPV_Store_Public {

    /** 🔹 Hook regisztráció */
    public static function hooks() {
        add_shortcode('ppv_store_public', [__CLASS__, 'render_public_store']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /** 🔹 CSS/JS betöltése */
    public static function enqueue_assets() {
        wp_enqueue_style('ppv-store-public', PPV_PLUGIN_URL . 'assets/css/ppv-store-public.css', [], time());
    }

    /** 🔹 Nyilvános bolt oldal */
    public static function render_public_store() {
        global $wpdb, $wp;

        // ✅ Elsőként megpróbáljuk lekérni a store kulcsot paraméterből
        $store_key = isset($_GET['store']) ? sanitize_text_field($_GET['store']) : '';

        // ✅ Ha nincs paraméter, próbáljuk az URL-ből (slug formátum)
        if (empty($store_key) && isset($wp->request)) {
            $parts = explode('/', trim($wp->request, '/'));
            if (!empty($parts) && end($parts) !== 'store') {
                $store_key = sanitize_title(end($parts));
            }
        }

        if (empty($store_key)) {
            return '<p class="ppv-error">⚠️ Kein Store angegeben.</p>';
        }

        // 🔹 Store betöltése (store_key alapján)
        $store = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_stores 
            WHERE store_key=%s LIMIT 1
        ", $store_key));

        if (!$store) {
            return '<p class="ppv-error">⚠️ Store nicht gefunden.</p>';
        }

        // 🔹 Aktív kampány lekérése
        $today = current_time('Y-m-d');
        $active_campaign = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_campaigns
            WHERE store_id=%d AND status='active'
            AND start_date <= %s AND end_date >= %s
            ORDER BY start_date DESC LIMIT 1
        ", $store->id, $today, $today));

        // 🔹 Színek, logó és alapadatok
        $color = $store->design_color ?: '#00ffff';
        $logo = $store->design_logo ?: PPV_PLUGIN_URL . 'assets/img/default-store.png';
        $store_name = esc_html($store->store_name);
        $city = esc_html($store->city);
        $address = esc_html($store->address);

        // 🔹 QR URL generálás (mindig a /store/ oldalra mutat, query paraméterrel)
        $store_url = esc_url(add_query_arg('store', $store->store_key, home_url('/store/')));
        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . rawurlencode($store_url);

        ob_start();
        ?>
        <div class="ppv-public-store glass-card" style="--accent: <?php echo esc_attr($color); ?>;">
            <div class="ppv-store-header">
                <img src="<?php echo esc_url($logo); ?>" alt="Logo" class="ppv-store-logo">
                <h2 class="ppv-store-name neon-text"><?php echo $store_name; ?></h2>
                <p class="ppv-store-city"><?php echo $address . ($city ? ', ' . $city : ''); ?></p>
            </div>

            <?php if ($active_campaign): ?>
                <div class="ppv-active-campaign glass-section">
                    <h3>🎯 Aktive Kampagne: <?php echo esc_html($active_campaign->title); ?></h3>
                    <p>Gültig vom <?php echo esc_html($active_campaign->start_date); ?> bis <?php echo esc_html($active_campaign->end_date); ?></p>
                    <p><?php echo nl2br(esc_html($active_campaign->description)); ?></p>
                </div>
            <?php endif; ?>

            <div class="ppv-store-actions">
                <img src="<?php echo esc_url($qr_url); ?>" alt="QR Code" class="ppv-store-qr">
                <p>📱 Scanne den QR-Code, um Punkte zu sammeln!</p>
                <a href="<?php echo esc_url($store_url); ?>" class="ppv-btn neon">Jetzt Punkte sammeln</a>
            </div>

            <div class="ppv-store-footer">
                <p class="ppv-small">
                    Powered by <a href="https://punktepass.de" target="_blank">PunktePass</a>
                </p>
            </div>
        </div>

        <style>
        .ppv-public-store { max-width: 480px; margin: 40px auto; text-align:center; padding:25px; border-radius:20px; }
        .ppv-store-logo { width:100px; height:100px; border-radius:50%; object-fit:cover; margin-bottom:10px; }
        .ppv-store-name { font-size:1.8em; margin:5px 0; color:var(--accent); }
        .ppv-store-city { opacity:0.8; margin-bottom:15px; }
        .ppv-store-qr { width:220px; margin:20px 0; border:3px solid var(--accent); border-radius:15px; padding:6px; background:#fff; }
        .ppv-btn.neon { background:var(--accent); color:#000; font-weight:600; padding:10px 20px; border-radius:10px; display:inline-block; text-decoration:none; transition:0.2s; }
        .ppv-btn.neon:hover { filter:brightness(1.2); }
        .glass-card { background:rgba(0,0,0,0.6); color:#fff; box-shadow:0 0 20px rgba(0,255,255,0.2); backdrop-filter:blur(6px); }
        .glass-section { margin-top:20px; padding:15px; border-radius:15px; background:rgba(255,255,255,0.08); }
        .neon-text { color:var(--accent); text-shadow:0 0 10px var(--accent); }
        </style>
        <?php
        return ob_get_clean();
    }
}

// ✅ Hook regisztrálás
PPV_Store_Public::hooks();
