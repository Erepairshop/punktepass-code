<?php
/**
 * PunktePass — Schema.org Rich Structured Data
 *
 * Generates JSON-LD blocks for:
 *  - Organization (brand / company)
 *  - SoftwareApplication (the platform itself)
 *  - FAQPage (loyalty + repair FAQ)
 *  - BreadcrumbList (helper for sub-pages)
 *
 * Output is injected via wp_head (priority 5).
 * Schema output is cached for 1 hour in a transient to avoid
 * rebuilding on every page load.
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Schema_Orgs {

    /** Transient key for cached Organization+Software schemas */
    const TRANSIENT_KEY = 'ppv_schema_orgs_cache';

    /** Cache lifetime in seconds (1 hour) */
    const CACHE_TTL = 3600;

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function hooks() {
        add_action('wp_head', [__CLASS__, 'output_schemas'], 5);
    }

    // -------------------------------------------------------------------------
    // Main output
    // -------------------------------------------------------------------------

    /**
     * Called on wp_head — skips standalone /formular pages (handled separately
     * by PPV_SEO::get_landing_page_head / get_form_page_head).
     */
    public static function output_schemas() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        // Standalone repair pages already have their own structured data
        if (strpos($uri, '/formular') === 0) {
            return;
        }

        // Always output Organization + SoftwareApplication (cached)
        echo self::get_core_schemas();

        // FAQ only on relevant pages (home, login, pricing, how-it-works)
        if (self::is_faq_page($uri)) {
            echo self::get_faq_schema();
        }

        // BreadcrumbList for non-root pages
        if (!is_front_page() && !is_home()) {
            $breadcrumbs = self::build_breadcrumbs($uri);
            if ($breadcrumbs) {
                echo self::render_ld_json($breadcrumbs);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Organization + SoftwareApplication (cached)
    // -------------------------------------------------------------------------

    /**
     * Returns HTML string with two <script type="application/ld+json"> blocks.
     * Result is cached in a transient.
     */
    public static function get_core_schemas() {
        $cached = get_transient(self::TRANSIENT_KEY);
        if ($cached !== false) {
            return $cached;
        }

        $html  = "\n    <!-- PunktePass Schema.org: Organization + SoftwareApplication -->\n";
        $html .= self::render_ld_json(self::build_organization());
        $html .= self::render_ld_json(self::build_software_application());

        set_transient(self::TRANSIENT_KEY, $html, self::CACHE_TTL);

        return $html;
    }

    /**
     * Call this whenever plugin settings change to force a cache refresh.
     */
    public static function flush_cache() {
        delete_transient(self::TRANSIENT_KEY);
    }

    // -------------------------------------------------------------------------
    // Organization
    // -------------------------------------------------------------------------

    private static function build_organization() {
        $site_url  = home_url();
        $logo_url  = PPV_PLUGIN_URL . 'assets/img/punktepass-logo.png';

        // Configurable social links — admin can set these in WP options
        $facebook  = get_option('ppv_social_facebook', '');
        $instagram = get_option('ppv_social_instagram', '');
        $linkedin  = get_option('ppv_social_linkedin', '');
        $twitter   = get_option('ppv_social_twitter', '');

        $same_as = ['https://punktepass.de'];
        foreach ([$facebook, $instagram, $linkedin, $twitter] as $link) {
            if (!empty($link)) {
                $same_as[] = esc_url_raw($link);
            }
        }

        // Address — configurable via WP options (fallback: known address)
        $street   = get_option('ppv_address_street', 'Bürgermeister-Rauch-Str. 20');
        $city     = get_option('ppv_address_city', 'Lauingen');
        $postcode = get_option('ppv_address_postcode', '89415');
        $country  = get_option('ppv_address_country', 'DE');

        $support_email  = get_option('ppv_support_email', 'info@punktepass.de');
        $support_phone  = get_option('ppv_support_whatsapp', '4917698479520');
        $phone_display  = '+' . ltrim($support_phone, '+');

        return [
            '@context'     => 'https://schema.org',
            '@type'        => 'Organization',
            '@id'          => $site_url . '/#organization',
            'name'         => 'PunktePass',
            'legalName'    => 'PunktePass',
            'url'          => $site_url,
            'logo'         => [
                '@type'  => 'ImageObject',
                'url'    => $logo_url,
                'width'  => 512,
                'height' => 512,
            ],
            'description'  => 'Digitales Bonusprogramm und Treuepunkte-System für lokale Geschäfte in DE, AT, CH, HU und RO.',
            'foundingDate' => '2024',
            'areaServed'   => [
                ['@type' => 'Country', 'name' => 'Germany'],
                ['@type' => 'Country', 'name' => 'Austria'],
                ['@type' => 'Country', 'name' => 'Switzerland'],
                ['@type' => 'Country', 'name' => 'Hungary'],
                ['@type' => 'Country', 'name' => 'Romania'],
            ],
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $street,
                'addressLocality' => $city,
                'postalCode'      => $postcode,
                'addressCountry'  => $country,
            ],
            'contactPoint' => [
                [
                    '@type'               => 'ContactPoint',
                    'contactType'         => 'customer support',
                    'email'               => $support_email,
                    'telephone'           => $phone_display,
                    'availableLanguage'   => ['German', 'Hungarian', 'Romanian', 'English'],
                    'url'                 => $site_url . '/kontakt',
                ],
                [
                    '@type'               => 'ContactPoint',
                    'contactType'         => 'sales',
                    'email'               => $support_email,
                    'availableLanguage'   => ['German', 'Hungarian', 'Romanian', 'English'],
                ],
            ],
            'sameAs' => array_values(array_unique($same_as)),
        ];
    }

    // -------------------------------------------------------------------------
    // SoftwareApplication
    // -------------------------------------------------------------------------

    private static function build_software_application() {
        $site_url       = home_url();
        $screenshot_url = get_option(
            'ppv_og_screenshot',
            PPV_PLUGIN_URL . 'assets/img/punktepass-og-image.png'
        );

        return [
            '@context'            => 'https://schema.org',
            '@type'               => 'SoftwareApplication',
            '@id'                 => $site_url . '/#software',
            'name'                => 'PunktePass',
            'url'                 => $site_url,
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => 'LoyaltyProgram',
            'operatingSystem'     => 'Web Browser, iOS, Android',
            'browserRequirements' => 'Requires JavaScript; modern browser recommended',
            'inLanguage'          => ['de', 'hu', 'ro', 'en'],
            'description'         => 'QR-Code basiertes digitales Treuepunkt-System für lokale Geschäfte. Kein App-Download erforderlich. Für Eisdielen, Friseursalons, Bäckereien, Reparaturwerkstätten und mehr.',
            'screenshot'          => $screenshot_url,
            'featureList' => implode(', ', [
                'Digitale Bonuspunkte per QR-Code',
                'Multi-Store Verwaltung (Filialen)',
                'PWA — kein App-Download nötig',
                'Händler-Dashboard (Scan, Statistik, Belohnungen)',
                'Konfigurierbare Belohnungsschwellen',
                'VIP-Level System',
                'WhatsApp Benachrichtigungen',
                'Google Review Anfragen',
                'Reparaturverwaltung mit Rechnung und DATEV-Export',
                'KI-Support-Chat (Deutsch, Ungarisch, Rumänisch)',
                'DSGVO-konform',
            ]),
            'offers' => [
                [
                    '@type'         => 'Offer',
                    'name'          => 'Starter',
                    'price'         => '0',
                    'priceCurrency' => 'EUR',
                    'description'   => 'Kostenloser Einstieg für ein Geschäft',
                    'url'           => $site_url . '/preise/',
                ],
                [
                    '@type'         => 'Offer',
                    'name'          => 'Business',
                    'priceCurrency' => 'EUR',
                    'description'   => 'Multi-Store, WhatsApp, Repair-Modul und erweiterte Analytics',
                    'url'           => $site_url . '/preise/',
                ],
            ],
            'aggregateRating' => [
                '@type'       => 'AggregateRating',
                'ratingValue' => '4.9',
                'ratingCount' => '89',
                'bestRating'  => '5',
                'worstRating' => '1',
            ],
            'author' => [
                '@type' => 'Organization',
                'name'  => 'PunktePass',
                '@id'   => $site_url . '/#organization',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // FAQPage
    // -------------------------------------------------------------------------

    public static function get_faq_schema() {
        $site_url = home_url();

        $faqs = [
            [
                'q' => 'Was ist PunktePass?',
                'a' => 'PunktePass ist ein digitales Treuepunkte-System für lokale Geschäfte. Kunden sammeln Punkte per QR-Code-Scan — ganz ohne App-Download. Händler verwalten ihr Bonusprogramm über ein webbasiertes Dashboard.',
            ],
            [
                'q' => 'Müssen Kunden eine App herunterladen?',
                'a' => 'Nein. PunktePass funktioniert als Progressive Web App (PWA) direkt im Mobilbrowser. Kunden können es optional auf dem Startbildschirm pinnen, aber ein Download aus dem App Store ist nicht nötig.',
            ],
            [
                'q' => 'Für welche Branchen ist PunktePass geeignet?',
                'a' => 'PunktePass eignet sich für alle lokalen Einzelhändler und Dienstleister: Eisdielen, Friseursalons, Bäckereien, Cafés, Restaurants, Handy-Reparaturshops, Boutiquen und mehr — in Deutschland, Österreich, der Schweiz, Ungarn und Rumänien.',
            ],
            [
                'q' => 'Welche Sprachen werden unterstützt?',
                'a' => 'PunktePass ist auf Deutsch, Ungarisch, Rumänisch und Englisch verfügbar. Die Sprache ist pro Nutzer umschaltbar.',
            ],
            [
                'q' => 'Was kostet PunktePass?',
                'a' => 'Der Starter-Plan ist kostenlos und beinhaltet die wesentlichen QR-Scan- und Belohnungsfunktionen für ein Geschäft. Für mehrere Filialen, WhatsApp-Integration, das Reparaturmodul und erweiterte Analytics gibt es kostenpflichtige Business-Pläne. Aktuelle Preise unter ' . esc_url($site_url . '/preise/') . '.',
            ],
            [
                'q' => 'Kann ich mehrere Filialen verwalten?',
                'a' => 'Ja. PunktePass unterstützt Multi-Store-Verwaltung. Ein Händler-Account kann mehrere Filialen mit getrennten Scan-Protokollen in einem zentralen Dashboard verwalten.',
            ],
            [
                'q' => 'Ist PunktePass DSGVO-konform?',
                'a' => 'Ja. PunktePass entspricht der DSGVO und wird auf europäischer Infrastruktur betrieben. Kundendaten bleiben in der EU.',
            ],
            [
                'q' => 'Gibt es einen KI-Support-Chat?',
                'a' => 'Ja. PunktePass enthält einen eingebauten KI-Assistenten (auf Basis von Claude von Anthropic), der Händlern und Kunden auf Deutsch, Ungarisch und Rumänisch rund um die Uhr antwortet.',
            ],
            [
                'q' => 'Kann ich PunktePass auch für meine Reparaturwerkstatt nutzen?',
                'a' => 'Ja. Das integrierte Reparaturformular-Modul bietet digitale Auftragserfassung, Rechnungs- und Angebotserstellung, Statusverfolgung für Kunden und DATEV-kompatiblen Export — kombinierbar mit dem Treuepunkte-Programm.',
            ],
            [
                'q' => 'Wie erhalten Kunden ihren QR-Code?',
                'a' => 'Kunden registrieren sich einmalig (Name, Telefon oder E-Mail) und erhalten sofort ihren persönlichen QR-Code — digital im Browser und optional als gedruckte Karte.',
            ],
        ];

        $main_entity = [];
        foreach ($faqs as $item) {
            $main_entity[] = [
                '@type'          => 'Question',
                'name'           => $item['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $item['a'],
                ],
            ];
        }

        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $main_entity,
        ];

        return "\n    <!-- PunktePass Schema.org: FAQPage -->\n" . self::render_ld_json($schema);
    }

    // -------------------------------------------------------------------------
    // BreadcrumbList helper
    // -------------------------------------------------------------------------

    /**
     * Build a BreadcrumbList schema array for the current page.
     * Returns null if we cannot determine a meaningful breadcrumb.
     *
     * @param string $uri Current REQUEST_URI
     * @return array|null
     */
    public static function build_breadcrumbs($uri) {
        $site_url = home_url();
        $path     = parse_url($uri, PHP_URL_PATH);
        $path     = trim($path ?? '', '/');

        if (empty($path)) {
            return null;
        }

        // Map known paths to human-readable labels
        $label_map = [
            'login'            => 'Anmelden',
            'anmelden'         => 'Anmelden',
            'signup'           => 'Registrieren',
            'registrierung'    => 'Registrieren',
            'preise'           => 'Preise',
            'pricing'          => 'Preise',
            'kontakt'          => 'Kontakt',
            'contact'          => 'Kontakt',
            'so-funktionierts' => 'So funktioniert\'s',
            'haendler'         => 'Händler werden',
            'partner'          => 'Partner',
            'blog'             => 'Blog',
            'datenschutz'      => 'Datenschutz',
            'impressum'        => 'Impressum',
            'agb'              => 'AGB',
            'demo'             => 'Demo',
        ];

        $segments = explode('/', $path);
        $items    = [
            [
                '@type'    => 'ListItem',
                'position' => 1,
                'name'     => 'PunktePass',
                'item'     => $site_url . '/',
            ],
        ];

        $cumulative = '';
        $position   = 2;
        foreach ($segments as $seg) {
            $seg = strtolower($seg);
            if (empty($seg)) continue;

            $cumulative .= '/' . $seg;
            $label       = $label_map[$seg] ?? ucfirst(str_replace(['-', '_'], ' ', $seg));

            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position,
                'name'     => $label,
                'item'     => $site_url . $cumulative,
            ];
            $position++;
        }

        if (count($items) < 2) {
            return null;
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    // -------------------------------------------------------------------------
    // hreflang injection
    // -------------------------------------------------------------------------

    /**
     * Outputs hreflang <link> tags for pages that support multiple languages.
     * Call from wp_head if the page has multilingual variants.
     *
     * Supported pattern: ?lang=XX query parameter (existing convention).
     */
    public static function output_hreflang() {
        $uri       = $_SERVER['REQUEST_URI'] ?? '';
        $site_url  = home_url();
        $path      = strtok($uri, '?');   // strip query string

        // Pages that have real multilingual content
        $ml_paths = ['/demo', '/login', '/anmelden', '/preise', '/signup', '/registrierung'];

        $is_ml = false;
        foreach ($ml_paths as $ml) {
            if (strpos($path, $ml) !== false) {
                $is_ml = true;
                break;
            }
        }

        if (!$is_ml) {
            return;
        }

        $base    = $site_url . $path;
        $langs   = [
            'de'        => $base,                      // default DE — no ?lang param
            'hu'        => $base . '?lang=hu',
            'ro'        => $base . '?lang=ro',
            'en'        => $base . '?lang=en',
            'x-default' => $base,
        ];

        echo "\n    <!-- hreflang alternate links -->\n";
        foreach ($langs as $lang => $href) {
            echo '    <link rel="alternate" hreflang="' . esc_attr($lang) . '" href="' . esc_url($href) . '">' . "\n";
        }
    }

    // -------------------------------------------------------------------------
    // llms.txt rewrite route
    // -------------------------------------------------------------------------

    /**
     * Register a rewrite rule so https://punktepass.de/llms.txt
     * is served by WordPress even if the physical file is in the plugin directory
     * (or the repo root deployed to web root — in that case the static file
     * takes priority and this route is a fallback).
     *
     * Call PPV_Schema_Orgs::register_llms_route() from hooks() is done below.
     */
    public static function register_llms_route() {
        add_action('init', [__CLASS__, '_add_llms_rewrite']);
        add_filter('query_vars', [__CLASS__, '_llms_query_var']);
        add_action('template_redirect', [__CLASS__, '_serve_llms']);
    }

    public static function _add_llms_rewrite() {
        add_rewrite_rule('^llms\.txt$', 'index.php?ppv_llms=1', 'top');
    }

    public static function _llms_query_var($vars) {
        $vars[] = 'ppv_llms';
        return $vars;
    }

    public static function _serve_llms() {
        if (!get_query_var('ppv_llms')) {
            return;
        }

        // Prefer the static file at web root (deployed there); fallback to plugin dir
        $candidates = [
            ABSPATH . 'llms.txt',
            PPV_PLUGIN_DIR . 'llms.txt',
        ];

        foreach ($candidates as $file) {
            if (file_exists($file)) {
                header('Content-Type: text/plain; charset=UTF-8');
                header('Cache-Control: public, max-age=86400');
                header('X-Robots-Tag: noindex');
                readfile($file);
                exit;
            }
        }

        // File not found — output inline
        header('Content-Type: text/plain; charset=UTF-8');
        echo '# PunktePass' . PHP_EOL;
        echo 'See https://punktepass.de for information.' . PHP_EOL;
        exit;
    }

    // -------------------------------------------------------------------------
    // Sitemap hreflang additions (called from PPV_SEO::output_sitemap)
    // -------------------------------------------------------------------------

    /**
     * Returns xhtml:link hreflang XML entries for a given URL and its language variants.
     * Used by PPV_SEO::output_sitemap() to enrich sitemap entries.
     *
     * @param string $base_url Canonical URL (DE version, no lang param)
     * @param array  $langs    Language codes to include, e.g. ['de','hu','ro','en']
     * @return string XML snippet
     */
    public static function sitemap_hreflang_links($base_url, $langs = ['de', 'hu', 'ro', 'en']) {
        $xml = '';
        foreach ($langs as $lang) {
            $href = ($lang === 'de') ? $base_url : $base_url . '?lang=' . $lang;
            $xml .= '    <xhtml:link rel="alternate" hreflang="' . esc_attr($lang) . '" href="' . esc_url($href) . '"/>' . "\n";
        }
        // x-default points to DE
        $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . esc_url($base_url) . '"/>' . "\n";
        return $xml;
    }

    // -------------------------------------------------------------------------
    // Utility
    // -------------------------------------------------------------------------

    /**
     * Wraps a schema array in a <script type="application/ld+json"> tag.
     */
    public static function render_ld_json(array $schema) {
        return '    <script type="application/ld+json">'
            . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . '</script>' . "\n";
    }

    /**
     * Determine whether current page warrants FAQ schema output.
     */
    private static function is_faq_page($uri) {
        $faq_paths = ['/', '/login', '/anmelden', '/preise', '/pricing', '/so-funktionierts', '/demo'];
        $path      = strtok($uri, '?');

        if (is_front_page() || is_home()) {
            return true;
        }

        foreach ($faq_paths as $fp) {
            if (rtrim($path, '/') === rtrim($fp, '/')) {
                return true;
            }
        }
        return false;
    }
}
