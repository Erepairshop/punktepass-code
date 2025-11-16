<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'PPV_Public', false ) ) {

class PPV_Public {

    public static function hooks() {
        add_shortcode( 'ppv_store_list', [ __CLASS__, 'shortcode_store_list' ] );
    }

    /**
     * Alle Stores Liste (Frontend)
     */
    public static function shortcode_store_list( $atts ) {
        global $wpdb;

        $atts = shortcode_atts([
            'limit' => 20,
        ], $atts );

        $stores = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores ORDER BY name ASC LIMIT %d",
            intval($atts['limit'])
        ) );

        if ( ! $stores ) {
            return '<p>' . __( 'Noch keine Stores verfügbar.', 'punktepass' ) . '</p>';
        }

        ob_start(); ?>
        <div class="ppv-store-list">
            <?php foreach ( $stores as $store ) : ?>
                <div class="ppv-store-item">
                    <?php if ( ! empty($store->logo) ) : ?>
                        <img src="<?php echo esc_url($store->logo); ?>" class="ppv-store-item-logo">
                    <?php endif; ?>

                    <h3><?php echo esc_html($store->name); ?></h3>
                    <p><?php echo esc_html($store->city . ' ' . $store->plz); ?></p>

                    <?php if ( ! empty($store->description) ) : ?>
                        <p class="ppv-store-desc">
                            <?php echo wp_trim_words( esc_html($store->description), 15 ); ?>
                        </p>
                    <?php endif; ?>

                    <a href="<?php echo site_url('?store_id='.$store->id); ?>" class="ppv-btn">
                        <?php _e('Profil ansehen','punktepass'); ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
        
        /** ============================================================
 *  🔹 PWA iOS Fallback Loader – shortcode erőltetett renderelés
 * ============================================================ */
add_action('template_redirect', function () {
    if (wp_is_mobile() && isset($_GET['pwa_fix']) && $_GET['pwa_fix'] === '1') {
        while (have_posts()) {
            the_post();
            echo '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<title>' . get_the_title() . '</title>';
            wp_head();
            echo '</head><body class="ppv-app-mode">';
            the_content(); // Itt fut le ténylegesen a shortcode
            wp_footer();
            echo '</body></html>';
        }
        exit;
    }
});

    }
    
}

}

PPV_Public::hooks();
