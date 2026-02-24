<?php
/**
 * PunktePass - Professional SEO System
 * Handles all SEO-related functionality including:
 * - Meta tags (title, description, keywords)
 * - Open Graph tags (Facebook, LinkedIn)
 * - Twitter Card tags
 * - JSON-LD Structured Data (Schema.org)
 * - Canonical URLs
 * - Sitemap.xml generation
 * - robots.txt handling
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_SEO {

    // Google Search Console verification code (filename without .html)
    const GOOGLE_VERIFICATION_CODE = 'googlead9253a892c914de';

    /**
     * Initialize SEO system
     */
    public static function init() {
        // Register sitemap route
        add_action('init', [__CLASS__, 'register_sitemap_route']);

        // Add robots.txt rules
        add_filter('robots_txt', [__CLASS__, 'custom_robots_txt'], 10, 2);

        // Add SEO to WordPress pages via wp_head
        add_action('wp_head', [__CLASS__, 'inject_wordpress_seo'], 1);

        // Override Yoast/RankMath if needed
        add_filter('wpseo_title', [__CLASS__, 'filter_seo_title'], 10, 1);
        add_filter('wpseo_metadesc', [__CLASS__, 'filter_seo_description'], 10, 1);

        // Handle Google verification file requests early
        add_action('init', [__CLASS__, 'handle_google_verification'], 1);
    }

    /**
     * Handle Google Search Console verification file requests
     * Serves the verification HTML file dynamically
     */
    public static function handle_google_verification() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH);

        // Check for Google verification file (e.g., /googlead9253a892c914de.html)
        if (preg_match('/^\/(google[a-f0-9]+)\.html$/', $path, $matches)) {
            $requested_code = $matches[1];

            // Verify it matches our configured code
            if ($requested_code === self::GOOGLE_VERIFICATION_CODE) {
                header('Content-Type: text/html; charset=UTF-8');
                header('X-Robots-Tag: noindex');
                echo 'google-site-verification: ' . self::GOOGLE_VERIFICATION_CODE . '.html';
                exit;
            }
        }
    }

    /**
     * Inject SEO tags into WordPress pages
     */
    public static function inject_wordpress_seo() {
        // Skip if on formular standalone pages (handled separately)
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/formular') === 0) {
            return;
        }

        // Get page-specific SEO data
        $seo_data = self::get_wordpress_page_seo();
        if (!$seo_data) {
            return;
        }

        echo self::build_seo_tags($seo_data);
        echo self::get_wordpress_structured_data();
    }

    /**
     * Get SEO data for WordPress pages
     */
    private static function get_wordpress_page_seo() {
        $site_url = home_url();
        $current_url = home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''));
        $og_image = PPV_PLUGIN_URL . 'assets/img/punktepass-og-image.png';

        // Default SEO for main site
        $default_seo = [
            'title' => 'PunktePass - Digitales Bonusprogramm für Ihren Shop',
            'description' => 'PunktePass ist das digitale Treuepunkt-System für lokale Geschäfte. Kundenbindung, Bonuspunkte, QR-Code Stempelkarte, PWA App und POS Integration. Jetzt kostenlos starten!',
            'keywords' => 'Bonusprogramm, Treuepunkte, Kundenbindung, Stempelkarte digital, QR Code Bonus, PunktePass, Loyalty App, Kundenbonus, Händler App, Einzelhandel Software',
            'canonical' => $current_url ?: $site_url,
            'og_type' => 'website',
            'og_image' => $og_image,
            'og_site_name' => 'PunktePass',
            'twitter_card' => 'summary_large_image',
            'twitter_site' => '@punktepass',
            'robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'author' => 'PunktePass',
            'language' => 'de-DE',
        ];

        // Page-specific SEO
        if (is_front_page() || is_home()) {
            return array_merge($default_seo, [
                'title' => 'PunktePass - Digitales Bonusprogramm & Treuepunkte für lokale Geschäfte',
                'description' => 'Digitales Bonusprogramm für Ihren Shop. Kundenbindung durch Treuepunkte, QR-Code Stempelkarte, automatische Belohnungen. PWA App für Kunden. Kostenlos testen!',
                'canonical' => $site_url,
            ]);
        }

        // Detect specific pages by URL or slug
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        // Login page is the main landing page for PunktePass - should be indexed!
        if (strpos($uri, '/login') !== false || strpos($uri, '/anmelden') !== false) {
            return array_merge($default_seo, [
                'title' => 'PunktePass - Digitales Bonusprogramm & Treuepunkte für lokale Geschäfte',
                'description' => 'PunktePass ist das digitale Treuepunkt-System für lokale Geschäfte. Kundenbindung, Bonuspunkte sammeln, QR-Code Stempelkarte, automatische Belohnungen. PWA App für Kunden. Jetzt kostenlos starten!',
                'keywords' => 'Bonusprogramm, Treuepunkte, Kundenbindung, Stempelkarte digital, QR Code Bonus, PunktePass, Loyalty App, digitale Stempelkarte, Kundenbonus, Händler App, Einzelhandel Software, Bonuskarte, Treueprogramm',
                'robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
                'canonical' => home_url('/login'),
            ]);
        }

        if (strpos($uri, '/signup') !== false || strpos($uri, '/registrierung') !== false) {
            return array_merge($default_seo, [
                'title' => 'Kostenlos registrieren - PunktePass',
                'description' => 'Registrieren Sie sich kostenlos bei PunktePass und sammeln Sie Bonuspunkte bei Ihren Lieblingshändlern.',
            ]);
        }

        if (strpos($uri, '/haendler') !== false || strpos($uri, '/partner') !== false) {
            return array_merge($default_seo, [
                'title' => 'Händler werden - PunktePass Bonusprogramm',
                'description' => 'Werden Sie PunktePass Partner! Digitales Bonussystem für Ihren Shop. Kundenbindung, mehr Umsatz, einfache Integration. Jetzt als Händler registrieren!',
                'keywords' => 'Händler werden, Bonusprogramm Händler, Kundenbindung Einzelhandel, Treueprogramm Shop, PunktePass Partner',
            ]);
        }

        if (strpos($uri, '/so-funktionierts') !== false || strpos($uri, '/wie-es-funktioniert') !== false) {
            return array_merge($default_seo, [
                'title' => 'So funktioniert PunktePass - Einfach Punkte sammeln',
                'description' => 'Erfahren Sie, wie PunktePass funktioniert: QR-Code scannen, Punkte sammeln, Prämien einlösen. Das digitale Bonusprogramm für lokale Geschäfte.',
            ]);
        }

        if (strpos($uri, '/preise') !== false || strpos($uri, '/pricing') !== false) {
            return array_merge($default_seo, [
                'title' => 'Preise - PunktePass Bonusprogramm für Händler',
                'description' => 'Transparente Preise für das PunktePass Bonusprogramm. Kostenlos starten, flexible Pakete für jeden Bedarf. Keine versteckten Kosten.',
            ]);
        }

        if (strpos($uri, '/kontakt') !== false || strpos($uri, '/contact') !== false) {
            return array_merge($default_seo, [
                'title' => 'Kontakt - PunktePass Support',
                'description' => 'Kontaktieren Sie das PunktePass Team. Wir helfen Ihnen gerne bei Fragen zu unserem Bonusprogramm und der Integration in Ihren Shop.',
                'robots' => 'index, follow',
            ]);
        }

        if (strpos($uri, '/datenschutz') !== false || strpos($uri, '/privacy') !== false) {
            return array_merge($default_seo, [
                'title' => 'Datenschutz - PunktePass',
                'description' => 'Datenschutzerklärung von PunktePass. Erfahren Sie, wie wir Ihre Daten schützen und verarbeiten.',
                'robots' => 'noindex, follow',
            ]);
        }

        if (strpos($uri, '/impressum') !== false || strpos($uri, '/imprint') !== false) {
            return array_merge($default_seo, [
                'title' => 'Impressum - PunktePass',
                'description' => 'Impressum und rechtliche Informationen zu PunktePass.',
                'robots' => 'noindex, follow',
            ]);
        }

        if (strpos($uri, '/agb') !== false || strpos($uri, '/terms') !== false) {
            return array_merge($default_seo, [
                'title' => 'AGB - PunktePass',
                'description' => 'Allgemeine Geschäftsbedingungen von PunktePass.',
                'robots' => 'noindex, follow',
            ]);
        }

        // Blog pages (rendered standalone, but inject SEO for WP pages too)
        if (strpos($uri, '/blog') === 0) {
            return array_merge($default_seo, [
                'title' => 'Blog - PunktePass | Tipps für Kundenbindung & Bonusprogramme',
                'description' => 'Tipps, News und Wissenswertes rund um Kundenbindung, Bonusprogramme und digitale Lösungen für lokale Geschäfte.',
                'keywords' => 'Kundenbindung, Bonusprogramm, Treuepunkte, lokale Geschäfte, Einzelhandel, QR-Code, PunktePass Blog',
                'canonical' => home_url('/blog/'),
            ]);
        }

        return $default_seo;
    }

    /**
     * Generate structured data for WordPress pages
     */
    private static function get_wordpress_structured_data() {
        $site_url = home_url();
        $logo_url = PPV_PLUGIN_URL . 'assets/img/punktepass-logo.png';

        $organization = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'PunktePass',
            'url' => $site_url,
            'logo' => $logo_url,
            'description' => 'Digitales Bonusprogramm und Treuepunkte-System für lokale Geschäfte',
            'foundingDate' => '2024',
            'areaServed' => [
                '@type' => 'Country',
                'name' => 'Germany'
            ],
            'sameAs' => [
                'https://punktepass.de',
            ],
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'availableLanguage' => ['German', 'English'],
                'url' => $site_url . '/kontakt',
            ],
        ];

        $website = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'PunktePass',
            'url' => $site_url,
            'description' => 'Digitales Bonusprogramm für lokale Geschäfte',
            'inLanguage' => 'de-DE',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $site_url . '/?s={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        $software = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'PunktePass',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web Browser, iOS, Android',
            'description' => 'Digitales Bonusprogramm und Kundenbindungssystem für lokale Geschäfte',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'EUR',
                'description' => 'Kostenlos starten',
            ],
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => '4.9',
                'ratingCount' => '89',
                'bestRating' => '5',
                'worstRating' => '1',
            ],
            'featureList' => [
                'Digitale Bonuspunkte',
                'QR-Code Stempelkarte',
                'PWA App für Kunden',
                'Automatische Belohnungen',
                'POS Integration',
                'Kundenanalysen',
                'Marketing-Tools',
                'Reparaturverwaltung',
            ],
        ];

        $html = "\n    <!-- PunktePass Structured Data -->\n";
        $html .= '    <script type="application/ld+json">' . json_encode($organization, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        $html .= '    <script type="application/ld+json">' . json_encode($website, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        $html .= '    <script type="application/ld+json">' . json_encode($software, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

        return $html;
    }

    /**
     * Filter SEO title for Yoast/RankMath compatibility
     */
    public static function filter_seo_title($title) {
        $seo_data = self::get_wordpress_page_seo();
        return $seo_data['title'] ?? $title;
    }

    /**
     * Filter SEO description for Yoast/RankMath compatibility
     */
    public static function filter_seo_description($description) {
        $seo_data = self::get_wordpress_page_seo();
        return $seo_data['description'] ?? $description;
    }

    /**
     * Generate complete SEO head tags for landing page
     */
    public static function get_landing_page_head() {
        $site_url = home_url();
        $page_url = home_url('/formular');
        $logo_url = PPV_PLUGIN_URL . 'assets/img/punktepass-repair-logo.svg';

        $title = 'Reparaturverwaltung für Ihren Shop - Kostenlose Werkstatt Software | PunktePass';
        $description = 'Kostenlose Reparaturverwaltung für Werkstätten: Digitales Formular, Rechnungen, Angebote, Ankauf, DATEV-Export, Kundenverwaltung. Handy Reparatur, Computer, KFZ, Fahrrad. Online & Tablet. Jetzt gratis starten!';
        $keywords = 'Reparaturverwaltung, Reparatursoftware, Werkstatt Software kostenlos, Handy Reparatur Software, Computer Reparatur Programm, KFZ Werkstatt Software, Fahrrad Reparatur Software, Rechnungen erstellen kostenlos, Angebote erstellen, DATEV Export, Kundenverwaltung, digitales Reparaturformular, Werkstatt Verwaltung, Reparatur App, PunktePass, Reparatur Lauingen, Reparatur Dillingen, Reparatur Bayern';

        $og_image = PPV_PLUGIN_URL . 'assets/img/punktepass-og-image.png';

        return self::build_seo_tags([
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $page_url,
            'og_type' => 'website',
            'og_image' => $og_image,
            'og_site_name' => 'PunktePass',
            'twitter_card' => 'summary_large_image',
            'twitter_site' => '@punktepass',
            'robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'author' => 'PunktePass',
            'language' => 'de-DE',
        ]) . self::get_landing_page_structured_data();
    }

    /**
     * Generate SEO head tags for store form page
     */
    public static function get_form_page_head($store) {
        $store_name = esc_attr($store->repair_company_name ?: $store->name);
        $color = esc_attr($store->repair_color ?: '#667eea');
        $logo = esc_url($store->logo ?: '');
        $slug = esc_attr($store->store_slug);
        $raw_title_seo = $store->repair_form_title ?? '';
        $default_titles_seo = ['', 'Reparaturauftrag', 'Szervizmegrendelés', 'Repair Order', 'Comandă de service', 'Ordine di riparazione'];
        $form_title = esc_attr(in_array($raw_title_seo, $default_titles_seo, true) ? PPV_Lang::t('repair_admin_form_title_ph') : $raw_title_seo);
        $service_type = esc_attr($store->repair_service_type ?? 'Allgemein');
        $city = esc_attr($store->city ?: '');
        $address = trim(($store->address ?: '') . ', ' . ($store->plz ?: '') . ' ' . $city);

        // Service type specific keywords
        $service_keywords = self::get_service_type_keywords($service_type);

        $page_url = home_url("/formular/{$slug}");
        $title = "{$form_title} - {$store_name}" . ($city ? " | {$city}" : '');

        $description = "{$service_type} bei {$store_name}";
        if ($city) {
            $description .= " in {$city}";
        }
        $description .= ". Reparaturauftrag online einreichen. Schnell, einfach und digital. Jetzt Termin anfragen!";

        $keywords = "{$store_name}, {$service_type}, Reparatur, Reparaturauftrag";
        if ($city) {
            $keywords .= ", Reparatur {$city}, {$service_type} {$city}";
        }
        $keywords .= ", {$service_keywords}";

        return self::build_seo_tags([
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $page_url,
            'og_type' => 'business.business',
            'og_image' => $logo ?: PPV_PLUGIN_URL . 'assets/img/punktepass-og-image.png',
            'og_site_name' => $store_name,
            'twitter_card' => 'summary',
            'robots' => 'index, follow',
            'theme_color' => $color,
            'language' => 'de-DE',
        ]) . self::get_local_business_structured_data($store);
    }

    /**
     * Get keywords for specific service types
     */
    private static function get_service_type_keywords($service_type) {
        $keywords_map = [
            'Handy-Reparatur' => 'Handy Reparatur, Smartphone Reparatur, iPhone Reparatur, Samsung Reparatur, Display Reparatur, Akku tauschen, Handy Display kaputt',
            'Computer-Reparatur' => 'Computer Reparatur, PC Reparatur, Laptop Reparatur, Notebook Reparatur, Windows Reparatur, Mac Reparatur, Datenrettung',
            'Fahrrad-Reparatur' => 'Fahrrad Reparatur, Bike Service, E-Bike Reparatur, Fahrrad Inspektion, Bremsen einstellen, Reifen wechseln',
            'KFZ-Reparatur' => 'KFZ Reparatur, Auto Reparatur, Autowerkstatt, Inspektion, Ölwechsel, Bremsen Service, TÜV Vorbereitung',
            'Schmuck-Reparatur' => 'Schmuck Reparatur, Ring vergrößern, Kette reparieren, Goldschmied, Silberschmied, Schmuck reinigen',
            'Uhren-Reparatur' => 'Uhren Reparatur, Uhr reparieren, Batterie wechseln, Armband kürzen, Uhrmacher, Chronograph Service',
            'Schuh-Reparatur' => 'Schuh Reparatur, Schuhmacher, Absätze erneuern, Sohlen kleben, Schuhe dehnen, Leder Reparatur',
            'Elektro-Reparatur' => 'Elektro Reparatur, Haushaltsgeräte Reparatur, Waschmaschine Reparatur, Kühlschrank Reparatur, Elektriker',
        ];

        return $keywords_map[$service_type] ?? 'Reparatur Service, professionelle Reparatur, schnelle Reparatur';
    }

    /**
     * Generate SEO head tags for status tracking page
     */
    public static function get_status_page_head($store, $repair) {
        $store_name = esc_attr($store->repair_company_name ?: $store->name);
        $slug = esc_attr($store->store_slug);
        $token = esc_attr($repair->tracking_token);

        $page_url = home_url("/formular/{$slug}/status/{$token}");
        $title = "Reparaturstatus - {$store_name}";
        $description = "Verfolgen Sie den Status Ihrer Reparatur bei {$store_name}. Echtzeit-Updates zu Ihrem Auftrag.";

        return self::build_seo_tags([
            'title' => $title,
            'description' => $description,
            'canonical' => $page_url,
            'robots' => 'noindex, nofollow', // Private page
            'language' => 'de-DE',
        ]);
    }

    /**
     * Build SEO meta tags HTML
     */
    private static function build_seo_tags($args) {
        $defaults = [
            'title' => '',
            'description' => '',
            'keywords' => '',
            'canonical' => '',
            'og_type' => 'website',
            'og_image' => '',
            'og_site_name' => 'PunktePass',
            'twitter_card' => 'summary_large_image',
            'twitter_site' => '',
            'robots' => 'index, follow',
            'author' => '',
            'theme_color' => '#667eea',
            'language' => 'de-DE',
        ];

        $args = wp_parse_args($args, $defaults);

        $html = "\n    <!-- SEO Meta Tags - PunktePass -->\n";

        // Basic meta tags
        if ($args['description']) {
            $html .= '    <meta name="description" content="' . esc_attr($args['description']) . '">' . "\n";
        }
        if ($args['keywords']) {
            $html .= '    <meta name="keywords" content="' . esc_attr($args['keywords']) . '">' . "\n";
        }
        if ($args['author']) {
            $html .= '    <meta name="author" content="' . esc_attr($args['author']) . '">' . "\n";
        }
        $html .= '    <meta name="robots" content="' . esc_attr($args['robots']) . '">' . "\n";
        $html .= '    <meta name="googlebot" content="' . esc_attr($args['robots']) . '">' . "\n";
        $html .= '    <meta name="bingbot" content="' . esc_attr($args['robots']) . '">' . "\n";

        // Language
        $html .= '    <meta http-equiv="content-language" content="' . esc_attr($args['language']) . '">' . "\n";

        // Canonical URL
        if ($args['canonical']) {
            $html .= '    <link rel="canonical" href="' . esc_url($args['canonical']) . '">' . "\n";
        }

        // Theme color
        $html .= '    <meta name="theme-color" content="' . esc_attr($args['theme_color']) . '">' . "\n";
        $html .= '    <meta name="msapplication-TileColor" content="' . esc_attr($args['theme_color']) . '">' . "\n";

        // Open Graph tags (Facebook, LinkedIn)
        $html .= "\n    <!-- Open Graph / Facebook -->\n";
        $html .= '    <meta property="og:type" content="' . esc_attr($args['og_type']) . '">' . "\n";
        $html .= '    <meta property="og:url" content="' . esc_url($args['canonical']) . '">' . "\n";
        $html .= '    <meta property="og:title" content="' . esc_attr($args['title']) . '">' . "\n";
        $html .= '    <meta property="og:description" content="' . esc_attr($args['description']) . '">' . "\n";
        if ($args['og_image']) {
            $html .= '    <meta property="og:image" content="' . esc_url($args['og_image']) . '">' . "\n";
            $html .= '    <meta property="og:image:width" content="1200">' . "\n";
            $html .= '    <meta property="og:image:height" content="630">' . "\n";
        }
        $html .= '    <meta property="og:site_name" content="' . esc_attr($args['og_site_name']) . '">' . "\n";
        $html .= '    <meta property="og:locale" content="de_DE">' . "\n";

        // Twitter Card tags
        $html .= "\n    <!-- Twitter Card -->\n";
        $html .= '    <meta name="twitter:card" content="' . esc_attr($args['twitter_card']) . '">' . "\n";
        $html .= '    <meta name="twitter:url" content="' . esc_url($args['canonical']) . '">' . "\n";
        $html .= '    <meta name="twitter:title" content="' . esc_attr($args['title']) . '">' . "\n";
        $html .= '    <meta name="twitter:description" content="' . esc_attr($args['description']) . '">' . "\n";
        if ($args['og_image']) {
            $html .= '    <meta name="twitter:image" content="' . esc_url($args['og_image']) . '">' . "\n";
        }
        if ($args['twitter_site']) {
            $html .= '    <meta name="twitter:site" content="' . esc_attr($args['twitter_site']) . '">' . "\n";
        }

        // Additional SEO optimizations
        $html .= "\n    <!-- Additional SEO -->\n";
        $html .= '    <meta name="format-detection" content="telephone=no">' . "\n";
        $html .= '    <meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        $html .= '    <meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
        $html .= '    <meta name="mobile-web-app-capable" content="yes">' . "\n";

        return $html;
    }

    /**
     * Generate JSON-LD Structured Data for landing page
     */
    private static function get_landing_page_structured_data() {
        $site_url = home_url();
        $page_url = home_url('/formular');
        $logo_url = PPV_PLUGIN_URL . 'assets/img/punktepass-repair-logo.svg';

        $organization = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'PunktePass',
            'url' => $site_url,
            'logo' => $logo_url,
            'description' => 'Digitale Reparaturverwaltung und Kundenbindungssystem',
            'foundingDate' => '2024',
            'sameAs' => [
                'https://punktepass.de',
            ],
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'availableLanguage' => ['German', 'English'],
            ],
        ];

        $software = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'PunktePass Reparaturverwaltung',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web Browser',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'EUR',
                'description' => 'Kostenlos starten',
            ],
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => '4.8',
                'ratingCount' => '127',
                'bestRating' => '5',
                'worstRating' => '1',
            ],
            'featureList' => [
                'Digitales Reparaturformular',
                'Rechnungserstellung',
                'Angebotserstellung',
                'Ankauf-Verwaltung',
                'DATEV-Export',
                'Kundenverwaltung',
                'PunktePass Bonussystem',
                'E-Mail Benachrichtigungen',
            ],
        ];

        $webpage = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => 'Reparaturverwaltung für Ihren Shop',
            'url' => $page_url,
            'description' => 'Digitale Reparaturverwaltung mit Formular, Rechnungen, Angebote, Ankauf, DATEV-Export und Kundenverwaltung.',
            'inLanguage' => 'de-DE',
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => 'PunktePass',
                'url' => $site_url,
            ],
            'breadcrumb' => [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Home',
                        'item' => $site_url,
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => 'Reparaturverwaltung',
                        'item' => $page_url,
                    ],
                ],
            ],
        ];

        $faq = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name' => 'Was kostet die PunktePass Reparaturverwaltung?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Die Basisversion ist kostenlos. Sie können sofort starten und bis zu 50 Formulare pro Monat kostenlos nutzen. Für unbegrenzte Nutzung und Premium-Features gibt es kostengünstige Abo-Optionen.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Kann ich Rechnungen und Angebote erstellen?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Ja! Die Reparaturverwaltung enthält eine vollständige Rechnungs- und Angebotserstellung mit PDF-Export, E-Mail-Versand und DATEV-kompatibler Buchhaltungsexport.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Funktioniert die Software auf Tablet und Smartphone?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Ja, die komplette Anwendung ist responsive und funktioniert perfekt auf Desktop, Tablet und Smartphone. Kunden können Formulare direkt im Shop auf dem Tablet ausfüllen.',
                    ],
                ],
            ],
        ];

        $html = "\n    <!-- Structured Data / JSON-LD -->\n";
        $html .= '    <script type="application/ld+json">' . json_encode($organization, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        $html .= '    <script type="application/ld+json">' . json_encode($software, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        $html .= '    <script type="application/ld+json">' . json_encode($webpage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        $html .= '    <script type="application/ld+json">' . json_encode($faq, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

        return $html;
    }

    /**
     * Generate JSON-LD Structured Data for store form page
     */
    private static function get_local_business_structured_data($store) {
        $store_name = $store->repair_company_name ?: $store->name;
        $slug = $store->store_slug;
        $page_url = home_url("/formular/{$slug}");
        $logo = $store->logo ?: '';
        $service_type = $store->repair_service_type ?? 'Allgemein';
        $form_title = $store->repair_form_title ?: 'Reparaturauftrag';

        // Map service type to Schema.org type
        $business_types = [
            'Handy-Reparatur' => 'ElectronicsStore',
            'Computer-Reparatur' => 'ComputerStore',
            'Fahrrad-Reparatur' => 'BikeStore',
            'KFZ-Reparatur' => 'AutoRepair',
            'Schmuck-Reparatur' => 'JewelryStore',
            'Uhren-Reparatur' => 'JewelryStore',
            'Schuh-Reparatur' => 'ShoeStore',
            'Elektro-Reparatur' => 'ElectronicsStore',
        ];
        $schema_type = $business_types[$service_type] ?? 'LocalBusiness';

        $local_business = [
            '@context' => 'https://schema.org',
            '@type' => $schema_type,
            'name' => $store_name,
            'url' => $page_url,
            'description' => "Reparaturservice von {$store_name}",
        ];

        if ($logo) {
            $local_business['image'] = $logo;
            $local_business['logo'] = $logo;
        }

        if ($store->address || $store->city) {
            $local_business['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $store->address ?: '',
                'postalCode' => $store->plz ?: '',
                'addressLocality' => $store->city ?: '',
                'addressCountry' => 'DE',
            ];
        }

        if ($store->phone) {
            $local_business['telephone'] = $store->phone;
        }

        if ($store->email) {
            $local_business['email'] = $store->email;
        }

        // Add service offerings
        $local_business['hasOfferCatalog'] = [
            '@type' => 'OfferCatalog',
            'name' => 'Reparaturservices',
            'itemListElement' => [
                [
                    '@type' => 'Offer',
                    'itemOffered' => [
                        '@type' => 'Service',
                        'name' => $store->repair_form_title ?? 'Reparaturservice',
                        'description' => "Professioneller Reparaturservice bei {$store_name}",
                    ],
                ],
            ],
        ];

        // Add potential action for form submission
        $local_business['potentialAction'] = [
            '@type' => 'OrderAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => $page_url,
                'actionPlatform' => [
                    'http://schema.org/DesktopWebPlatform',
                    'http://schema.org/MobileWebPlatform',
                ],
            ],
            'name' => $form_title . ' einreichen',
        ];

        $webpage = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $form_title . ' - ' . $store_name,
            'url' => $page_url,
            'description' => "{$form_title} online einreichen bei {$store_name}",
            'inLanguage' => 'de-DE',
            'about' => [
                '@type' => 'Service',
                'name' => 'Reparaturservice',
                'provider' => [
                    '@type' => $schema_type,
                    'name' => $store_name,
                ],
            ],
        ];

        $breadcrumb = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'PunktePass',
                    'item' => home_url(),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Reparaturverwaltung',
                    'item' => home_url('/formular'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $store_name,
                    'item' => $page_url,
                ],
            ],
        ];

        $html = "\n    <!-- Structured Data / JSON-LD -->\n";
        $html .= '    <script type="application/ld+json">' . json_encode($local_business, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        $html .= '    <script type="application/ld+json">' . json_encode($webpage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        $html .= '    <script type="application/ld+json">' . json_encode($breadcrumb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

        return $html;
    }

    /**
     * Register sitemap route
     */
    public static function register_sitemap_route() {
        add_rewrite_rule('^formular-sitemap\.xml$', 'index.php?ppv_sitemap=1', 'top');
        add_filter('query_vars', function($vars) {
            $vars[] = 'ppv_sitemap';
            return $vars;
        });
        add_action('template_redirect', [__CLASS__, 'handle_sitemap_request']);
    }

    /**
     * Handle sitemap request
     */
    public static function handle_sitemap_request() {
        if (get_query_var('ppv_sitemap')) {
            self::output_sitemap();
            exit;
        }
    }

    /**
     * Generate and output XML sitemap
     */
    public static function output_sitemap() {
        global $wpdb;
        $prefix = $wpdb->prefix;

        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex');

        $site_url = home_url();

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        // Main formular page
        echo '  <url>' . "\n";
        echo '    <loc>' . esc_url($site_url . '/formular') . '</loc>' . "\n";
        echo '    <changefreq>weekly</changefreq>' . "\n";
        echo '    <priority>1.0</priority>' . "\n";
        echo '  </url>' . "\n";

        // All active stores
        $stores = $wpdb->get_results(
            "SELECT store_slug, name, repair_company_name, logo, city
             FROM {$prefix}ppv_stores
             WHERE repair_enabled = 1
             AND store_slug IS NOT NULL
             AND store_slug != ''
             ORDER BY name ASC"
        );

        foreach ($stores as $store) {
            $store_name = $store->repair_company_name ?: $store->name;
            $url = $site_url . '/formular/' . $store->store_slug;

            echo '  <url>' . "\n";
            echo '    <loc>' . esc_url($url) . '</loc>' . "\n";
            echo '    <changefreq>weekly</changefreq>' . "\n";
            echo '    <priority>0.8</priority>' . "\n";

            if ($store->logo) {
                echo '    <image:image>' . "\n";
                echo '      <image:loc>' . esc_url($store->logo) . '</image:loc>' . "\n";
                echo '      <image:title>' . esc_html($store_name) . '</image:title>' . "\n";
                echo '    </image:image>' . "\n";
            }

            echo '  </url>' . "\n";

            // Legal pages for each store
            $legal_pages = ['datenschutz', 'agb', 'impressum'];
            foreach ($legal_pages as $page) {
                echo '  <url>' . "\n";
                echo '    <loc>' . esc_url($url . '/' . $page) . '</loc>' . "\n";
                echo '    <changefreq>monthly</changefreq>' . "\n";
                echo '    <priority>0.3</priority>' . "\n";
                echo '  </url>' . "\n";
            }
        }

        echo '</urlset>';
    }

    /**
     * Customize robots.txt
     */
    public static function custom_robots_txt($output, $public) {
        $site_url = home_url();

        // Add formular sitemap
        $output .= "\n# PunktePass Formular System\n";
        $output .= "Sitemap: {$site_url}/formular-sitemap.xml\n\n";

        // Blog sitemap
        $output .= "Sitemap: {$site_url}/blog-sitemap.xml\n\n";

        // Blog
        $output .= "# PunktePass Blog\n";
        $output .= "Allow: /blog\n";
        $output .= "Allow: /blog/*\n\n";

        // Allow all formular pages
        $output .= "# Allow formular pages\n";
        $output .= "Allow: /formular\n";
        $output .= "Allow: /formular/*\n\n";

        // Disallow admin and status pages (private)
        $output .= "# Disallow private pages\n";
        $output .= "Disallow: /formular/admin\n";
        $output .= "Disallow: /formular/admin/*\n";
        $output .= "Disallow: /formular/*/status/*\n\n";

        return $output;
    }

    /**
     * Generate complete SEO head tags for login/landing page (standalone)
     * Language-aware: supports DE, HU, RO, EN
     */
    public static function get_login_page_head($lang = 'de') {
        $site_url = home_url();
        $og_image = PPV_PLUGIN_URL . 'assets/img/punktepass-og-image.png';

        // Language-specific SEO content
        $seo_by_lang = [
            'de' => [
                'title' => 'PunktePass - Digitales Bonusprogramm & Treuepunkte für lokale Geschäfte',
                'description' => 'PunktePass ist das digitale Treuepunkt-System für lokale Geschäfte. Bonuspunkte sammeln, QR-Code Stempelkarte, automatische Belohnungen. PWA App für Kunden. Jetzt kostenlos starten!',
                'keywords' => 'Bonusprogramm, Treuepunkte, Kundenbindung, Stempelkarte digital, QR Code Bonus, PunktePass, Loyalty App, digitale Stempelkarte, Kundenbonus, Händler App, Einzelhandel Software, Bonuskarte, Treueprogramm',
                'locale' => 'de_DE',
                'language' => 'de-DE',
                'canonical' => $site_url . '/login',
            ],
            'hu' => [
                'title' => 'PunktePass - Digitális Bonuszprogram & Hűségpontok helyi üzleteknek',
                'description' => 'PunktePass a digitális hűségpont-rendszer helyi üzleteknek. Pontgyűjtés, QR-kód törzsvásárlói kártya, automatikus jutalmak. Ingyenesen kipróbálható!',
                'keywords' => 'bonuszprogram, hűségpont, ügyfélmegtartás, törzsvásárlói kártya, QR kód bónusz, PunktePass, hűségprogram, digitális törzskártya',
                'locale' => 'hu_HU',
                'language' => 'hu-HU',
                'canonical' => $site_url . '/bejelentkezes',
            ],
            'ro' => [
                'title' => 'PunktePass - Program Digital de Bonus & Puncte de Fidelitate pentru Magazine Locale',
                'description' => 'PunktePass este sistemul digital de puncte de fidelitate pentru magazine locale. Colectează puncte, card de fidelitate QR, recompense automate. Începe gratuit!',
                'keywords' => 'program bonus, puncte fidelitate, fidelizare clienți, card fidelitate digital, cod QR bonus, PunktePass, program loialitate, card digital',
                'locale' => 'ro_RO',
                'language' => 'ro-RO',
                'canonical' => $site_url . '/login',
            ],
            'en' => [
                'title' => 'PunktePass - Digital Loyalty Program & Reward Points for Local Shops',
                'description' => 'PunktePass is the digital loyalty point system for local shops. Collect bonus points, QR code stamp card, automatic rewards. PWA app for customers. Start free now!',
                'keywords' => 'loyalty program, reward points, customer retention, digital stamp card, QR code bonus, PunktePass, loyalty app, customer bonus, retail software',
                'locale' => 'en_US',
                'language' => 'en-US',
                'canonical' => $site_url . '/login',
            ],
        ];

        $seo = $seo_by_lang[$lang] ?? $seo_by_lang['de'];

        $tags = self::build_seo_tags([
            'title' => $seo['title'],
            'description' => $seo['description'],
            'keywords' => $seo['keywords'],
            'canonical' => $seo['canonical'],
            'og_type' => 'website',
            'og_image' => $og_image,
            'og_site_name' => 'PunktePass',
            'twitter_card' => 'summary_large_image',
            'twitter_site' => '@punktepass',
            'robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'author' => 'PunktePass',
            'language' => $seo['language'],
        ]);

        // Structured data for login/landing page
        $tags .= self::get_login_page_structured_data($seo, $site_url);

        return $tags;
    }

    /**
     * Generate JSON-LD structured data for login/landing page
     */
    private static function get_login_page_structured_data($seo, $site_url) {
        $logo_url = PPV_PLUGIN_URL . 'assets/img/punktepass-logo.png';

        $organization = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'PunktePass',
            'url' => $site_url,
            'logo' => $logo_url,
            'description' => $seo['description'],
            'foundingDate' => '2024',
            'areaServed' => [
                ['@type' => 'Country', 'name' => 'Germany'],
                ['@type' => 'Country', 'name' => 'Romania'],
                ['@type' => 'Country', 'name' => 'Hungary'],
            ],
            'sameAs' => [
                'https://punktepass.de',
                'https://punktepass.ro',
            ],
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'availableLanguage' => ['German', 'English', 'Hungarian', 'Romanian'],
                'url' => $site_url . '/kontakt',
            ],
        ];

        $software = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'PunktePass',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web Browser, iOS, Android',
            'description' => $seo['description'],
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'EUR',
                'description' => 'Kostenlos starten / Start free',
            ],
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => '4.9',
                'ratingCount' => '89',
                'bestRating' => '5',
                'worstRating' => '1',
            ],
            'featureList' => [
                'Digitale Bonuspunkte / Digital Bonus Points',
                'QR-Code Stempelkarte / QR Code Stamp Card',
                'PWA App für Kunden / PWA App for Customers',
                'Automatische Belohnungen / Automatic Rewards',
                'POS Integration',
                'Kundenanalysen / Customer Analytics',
                'Multi-language (DE/HU/RO/EN)',
                'Reparaturverwaltung / Repair Management',
            ],
        ];

        $webpage = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $seo['title'],
            'url' => $seo['canonical'],
            'description' => $seo['description'],
            'inLanguage' => $seo['language'],
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => 'PunktePass',
                'url' => $site_url,
            ],
        ];

        $html = "\n    <!-- Structured Data / JSON-LD -->\n";
        $html .= '    <script type="application/ld+json">' . json_encode($organization, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        $html .= '    <script type="application/ld+json">' . json_encode($software, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        $html .= '    <script type="application/ld+json">' . json_encode($webpage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";

        return $html;
    }

    /**
     * Generate complete SEO head tags for signup page (standalone)
     */
    public static function get_signup_page_head($lang = 'de') {
        $site_url = home_url();
        $og_image = PPV_PLUGIN_URL . 'assets/img/punktepass-og-image.png';

        $seo_by_lang = [
            'de' => [
                'title' => 'Kostenlos registrieren - PunktePass Bonusprogramm',
                'description' => 'Jetzt kostenlos bei PunktePass registrieren und Bonuspunkte bei Ihren Lieblingshändlern sammeln. QR-Code Stempelkarte, automatische Belohnungen, lokale Angebote.',
                'keywords' => 'PunktePass registrieren, Bonusprogramm anmelden, Treuepunkte sammeln, kostenlos registrieren, Kundenkarte digital',
                'language' => 'de-DE',
                'canonical' => $site_url . '/signup',
            ],
            'hu' => [
                'title' => 'Ingyenes regisztráció - PunktePass Bonuszprogram',
                'description' => 'Regisztrálj most ingyen a PunktePass-hoz és gyűjts bónuszpontokat kedvenc üzleteidben. QR-kód törzskártya, automatikus jutalmak, helyi ajánlatok.',
                'keywords' => 'PunktePass regisztráció, bonuszprogram, hűségpont gyűjtés, ingyenes regisztráció, digitális törzskártya',
                'language' => 'hu-HU',
                'canonical' => $site_url . '/signup',
            ],
            'ro' => [
                'title' => 'Înregistrare gratuită - PunktePass Program de Bonus',
                'description' => 'Înregistrează-te gratuit la PunktePass și colectează puncte bonus la magazinele tale preferate. Card QR de fidelitate, recompense automate, oferte locale.',
                'keywords' => 'PunktePass înregistrare, program bonus, puncte fidelitate, înregistrare gratuită, card digital fidelitate',
                'language' => 'ro-RO',
                'canonical' => $site_url . '/signup',
            ],
            'en' => [
                'title' => 'Register for Free - PunktePass Loyalty Program',
                'description' => 'Register for free at PunktePass and collect bonus points at your favorite local shops. QR code stamp card, automatic rewards, local deals.',
                'keywords' => 'PunktePass register, loyalty program sign up, collect reward points, free registration, digital loyalty card',
                'language' => 'en-US',
                'canonical' => $site_url . '/signup',
            ],
        ];

        $seo = $seo_by_lang[$lang] ?? $seo_by_lang['de'];

        return self::build_seo_tags([
            'title' => $seo['title'],
            'description' => $seo['description'],
            'keywords' => $seo['keywords'],
            'canonical' => $seo['canonical'],
            'og_type' => 'website',
            'og_image' => $og_image,
            'og_site_name' => 'PunktePass',
            'twitter_card' => 'summary_large_image',
            'twitter_site' => '@punktepass',
            'robots' => 'index, follow',
            'author' => 'PunktePass',
            'language' => $seo['language'],
        ]);
    }

    /**
     * Get preload/prefetch links for performance
     */
    public static function get_performance_hints() {
        return '
    <!-- Performance Hints -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="//www.googletagmanager.com">
    <link rel="dns-prefetch" href="//www.google-analytics.com">
';
    }

    /**
     * Get favicon links
     */
    public static function get_favicon_links($custom_icon = null) {
        $icon = $custom_icon ?: 'https://punktepass.de/wp-content/uploads/2025/04/cropped-ppfavicon-32x32.png';

        return '
    <!-- Favicons -->
    <link rel="icon" href="' . esc_url($icon) . '" sizes="32x32">
    <link rel="icon" href="' . esc_url($icon) . '" sizes="192x192">
    <link rel="apple-touch-icon" href="' . esc_url($icon) . '">
';
    }
}

// Initialize SEO system
add_action('init', ['PPV_SEO', 'init'], 5);
