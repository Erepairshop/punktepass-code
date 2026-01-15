<?php
/**
 * PunktePass - Legal Pages (Datenschutz, AGB, Impressum)
 * Multi-language Support (DE/HU/RO)
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Legal {

    public static function hooks() {
        add_shortcode('ppv_datenschutz', [__CLASS__, 'render_datenschutz']);
        add_shortcode('ppv_agb', [__CLASS__, 'render_agb']);
        add_shortcode('ppv_impressum', [__CLASS__, 'render_impressum']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function enqueue_assets() {
        // Only on legal pages
        if (!is_page(['datenschutz', 'agb', 'impressum', 'privacy', 'terms', 'imprint'])) {
            return;
        }

        // RemixIcons loaded globally in punktepass.php

        wp_enqueue_style(
            'ppv-legal',
            PPV_PLUGIN_URL . 'assets/css/ppv-legal.css',
            [],
            time()
        );
    }

    /** ============================================================
     * Get Current Language
     * ============================================================ */
    private static function get_language() {
        if (!empty($_COOKIE['ppv_lang'])) {
            return sanitize_text_field($_COOKIE['ppv_lang']);
        }
        if (!empty($_GET['lang'])) {
            return sanitize_text_field($_GET['lang']);
        }
        $locale = get_locale();
        if (strpos($locale, 'hu') !== false) return 'hu';
        if (strpos($locale, 'ro') !== false) return 'ro';
        return 'de';
    }

    /** ============================================================
     * Render Page Wrapper
     * ============================================================ */
    private static function render_page($title, $content) {
        $lang = self::get_language();

        ob_start();
        ?>
        <div class="ppv-legal-page" data-lang="<?php echo esc_attr($lang); ?>">
            <div class="ppv-legal-container">
                <div class="ppv-legal-header">
                    <a href="/" class="ppv-legal-back">
                        <i class="ri-arrow-left-line"></i>
                        <?php echo PPV_Lang::t('legal_back_home'); ?>
                    </a>
                    <div class="ppv-legal-lang">
                        <button class="ppv-lang-btn <?php echo $lang === 'de' ? 'active' : ''; ?>" data-lang="de">DE</button>
                        <button class="ppv-lang-btn <?php echo $lang === 'hu' ? 'active' : ''; ?>" data-lang="hu">HU</button>
                        <button class="ppv-lang-btn <?php echo $lang === 'ro' ? 'active' : ''; ?>" data-lang="ro">RO</button>
                    </div>
                </div>

                <h1 class="ppv-legal-title"><?php echo esc_html($title); ?></h1>
                <p class="ppv-legal-updated"><?php echo PPV_Lang::t('legal_last_updated'); ?>: <?php echo date('d.m.Y'); ?></p>

                <div class="ppv-legal-content">
                    <?php echo $content; ?>
                </div>

                <div class="ppv-legal-footer">
                    <a href="/datenschutz"><?php echo PPV_Lang::t('landing_footer_privacy'); ?></a>
                    <span>•</span>
                    <a href="/agb"><?php echo PPV_Lang::t('landing_footer_terms'); ?></a>
                    <span>•</span>
                    <a href="/impressum"><?php echo PPV_Lang::t('landing_footer_imprint'); ?></a>
                </div>
            </div>
        </div>

        <script>
        document.querySelectorAll('.ppv-lang-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const lang = this.dataset.lang;
                document.cookie = 'ppv_lang=' + lang + ';path=/;max-age=31536000';
                window.location.reload();
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /** ============================================================
     * DATENSCHUTZ / Privacy Policy
     * ============================================================ */
    public static function render_datenschutz() {
        $title = PPV_Lang::t('legal_privacy_title');

        ob_start();
        ?>
        <section>
            <h2><?php echo PPV_Lang::t('privacy_intro_title'); ?></h2>
            <p><?php echo PPV_Lang::t('privacy_intro_text'); ?></p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('privacy_responsible_title'); ?></h2>
            <p><?php echo PPV_Lang::t('privacy_responsible_text'); ?></p>
            <p>
                <strong>PunktePass</strong><br>
                Erik Borota<br>
                Siedlungsring 51<br>
                89415 Lauingen<br>
                Deutschland<br><br>
                E-Mail: <a href="mailto:info@punktepass.de">info@punktepass.de</a>
            </p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('privacy_data_collection_title'); ?></h2>
            <p><?php echo PPV_Lang::t('privacy_data_collection_text'); ?></p>
            <ul>
                <li><?php echo PPV_Lang::t('privacy_data_name'); ?></li>
                <li><?php echo PPV_Lang::t('privacy_data_email'); ?></li>
                <li><?php echo PPV_Lang::t('privacy_data_phone'); ?></li>
                <li><?php echo PPV_Lang::t('privacy_data_address'); ?></li>
                <li><?php echo PPV_Lang::t('privacy_data_usage'); ?></li>
            </ul>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('privacy_purpose_title'); ?></h2>
            <p><?php echo PPV_Lang::t('privacy_purpose_text'); ?></p>
            <ul>
                <li><?php echo PPV_Lang::t('privacy_purpose_account'); ?></li>
                <li><?php echo PPV_Lang::t('privacy_purpose_points'); ?></li>
                <li><?php echo PPV_Lang::t('privacy_purpose_communication'); ?></li>
                <li><?php echo PPV_Lang::t('privacy_purpose_improvement'); ?></li>
            </ul>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('privacy_cookies_title'); ?></h2>
            <p><?php echo PPV_Lang::t('privacy_cookies_text'); ?></p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('privacy_third_party_title'); ?></h2>
            <p><?php echo PPV_Lang::t('privacy_third_party_text'); ?></p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('privacy_rights_title'); ?></h2>
            <p><?php echo PPV_Lang::t('privacy_rights_text'); ?></p>
            <ul>
                <li><?php echo PPV_Lang::t('privacy_rights_access'); ?></li>
                <li><?php echo PPV_Lang::t('privacy_rights_rectification'); ?></li>
                <li><?php echo PPV_Lang::t('privacy_rights_erasure'); ?></li>
                <li><?php echo PPV_Lang::t('privacy_rights_restriction'); ?></li>
                <li><?php echo PPV_Lang::t('privacy_rights_portability'); ?></li>
                <li><?php echo PPV_Lang::t('privacy_rights_objection'); ?></li>
            </ul>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('privacy_security_title'); ?></h2>
            <p><?php echo PPV_Lang::t('privacy_security_text'); ?></p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('privacy_contact_title'); ?></h2>
            <p><?php echo PPV_Lang::t('privacy_contact_text'); ?></p>
            <p>E-Mail: <a href="mailto:info@punktepass.de">info@punktepass.de</a></p>
        </section>
        <?php
        $content = ob_get_clean();

        return self::render_page($title, $content);
    }

    /** ============================================================
     * AGB / Terms and Conditions
     * ============================================================ */
    public static function render_agb() {
        $title = PPV_Lang::t('legal_terms_title');

        ob_start();
        ?>
        <section>
            <h2><?php echo PPV_Lang::t('terms_scope_title'); ?></h2>
            <p><?php echo PPV_Lang::t('terms_scope_text'); ?></p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('terms_service_title'); ?></h2>
            <p><?php echo PPV_Lang::t('terms_service_text'); ?></p>
            <ul>
                <li><?php echo PPV_Lang::t('terms_service_points'); ?></li>
                <li><?php echo PPV_Lang::t('terms_service_rewards'); ?></li>
                <li><?php echo PPV_Lang::t('terms_service_app'); ?></li>
                <li><?php echo PPV_Lang::t('terms_service_qr'); ?></li>
            </ul>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('terms_registration_title'); ?></h2>
            <p><?php echo PPV_Lang::t('terms_registration_text'); ?></p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('terms_points_title'); ?></h2>
            <p><?php echo PPV_Lang::t('terms_points_text'); ?></p>
            <ul>
                <li><?php echo PPV_Lang::t('terms_points_earn'); ?></li>
                <li><?php echo PPV_Lang::t('terms_points_redeem'); ?></li>
                <li><?php echo PPV_Lang::t('terms_points_expire'); ?></li>
                <li><?php echo PPV_Lang::t('terms_points_transfer'); ?></li>
            </ul>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('terms_vendor_title'); ?></h2>
            <p><?php echo PPV_Lang::t('terms_vendor_text'); ?></p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('terms_subscription_title'); ?></h2>
            <p><?php echo PPV_Lang::t('terms_subscription_text'); ?></p>
            <ul>
                <li><?php echo PPV_Lang::t('terms_subscription_price'); ?></li>
                <li><?php echo PPV_Lang::t('terms_subscription_payment'); ?></li>
                <li><?php echo PPV_Lang::t('terms_subscription_cancel'); ?></li>
            </ul>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('terms_liability_title'); ?></h2>
            <p><?php echo PPV_Lang::t('terms_liability_text'); ?></p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('terms_termination_title'); ?></h2>
            <p><?php echo PPV_Lang::t('terms_termination_text'); ?></p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('terms_changes_title'); ?></h2>
            <p><?php echo PPV_Lang::t('terms_changes_text'); ?></p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('terms_law_title'); ?></h2>
            <p><?php echo PPV_Lang::t('terms_law_text'); ?></p>
        </section>
        <?php
        $content = ob_get_clean();

        return self::render_page($title, $content);
    }

    /** ============================================================
     * IMPRESSUM / Imprint
     * ============================================================ */
    public static function render_impressum() {
        $title = PPV_Lang::t('legal_imprint_title');

        ob_start();
        ?>
        <section>
            <h2><?php echo PPV_Lang::t('imprint_provider_title'); ?></h2>
            <p>
                <strong>PunktePass</strong><br>
                Erik Borota<br>
                Siedlungsring 51<br>
                89415 Lauingen<br>
                Deutschland<br><br>
                <strong>USt.-ID:</strong> DE308874569<br>
                E-Mail: <a href="mailto:info@punktepass.de">info@punktepass.de</a>
            </p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('imprint_contact_title'); ?></h2>
            <p>
                E-Mail: <a href="mailto:info@punktepass.de">info@punktepass.de</a><br>
                Website: <a href="https://punktepass.de">www.punktepass.de</a>
            </p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('imprint_responsible_title'); ?></h2>
            <p>Erik Borota</p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('imprint_dispute_title'); ?></h2>
            <p><?php echo PPV_Lang::t('imprint_dispute_text'); ?></p>
            <p>
                <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener">
                    https://ec.europa.eu/consumers/odr
                </a>
            </p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('imprint_liability_title'); ?></h2>
            <p><?php echo PPV_Lang::t('imprint_liability_content_text'); ?></p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('imprint_liability_links_title'); ?></h2>
            <p><?php echo PPV_Lang::t('imprint_liability_links_text'); ?></p>
        </section>

        <section>
            <h2><?php echo PPV_Lang::t('imprint_copyright_title'); ?></h2>
            <p><?php echo PPV_Lang::t('imprint_copyright_text'); ?></p>
        </section>
        <?php
        $content = ob_get_clean();

        return self::render_page($title, $content);
    }
}

PPV_Legal::hooks();
