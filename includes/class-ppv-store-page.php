<?php
/**
 * PPV_Store_Page — Per-store public landing pages with Schema.org LocalBusiness markup
 *
 * Route:  /shop/{store-slug}/
 * Schema: JSON-LD LocalBusiness (name, address, geo, telephone, openingHoursSpecification,
 *         aggregateRating) injected into <head> for every store page.
 * Meta:   Localised <meta name="description"> (de/hu/ro/en).
 * Cache:  Per-store schema string cached in wp_cache for 1 hour.
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Store_Page {

    const CACHE_GROUP  = 'ppv_store_schema';
    const CACHE_TTL    = 3600; // 1 hour

    /** Day index → Schema.org dayOfWeek URI */
    private static $day_map = [
        'monday'    => 'https://schema.org/Monday',
        'tuesday'   => 'https://schema.org/Tuesday',
        'wednesday' => 'https://schema.org/Wednesday',
        'thursday'  => 'https://schema.org/Thursday',
        'friday'    => 'https://schema.org/Friday',
        'saturday'  => 'https://schema.org/Saturday',
        'sunday'    => 'https://schema.org/Sunday',
    ];

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function hooks() {
        add_action('init',              [__CLASS__, 'register_rewrite']);
        add_filter('query_vars',        [__CLASS__, 'add_query_vars']);
        add_action('template_redirect', [__CLASS__, 'maybe_render']);
    }

    /**
     * Called on plugin activation — flush rewrite rules so /shop/{slug}/ resolves.
     */
    public static function flush_rewrites() {
        self::register_rewrite();
        flush_rewrite_rules();
    }

    // -------------------------------------------------------------------------
    // Rewrite
    // -------------------------------------------------------------------------

    public static function register_rewrite() {
        add_rewrite_rule('^shop/([^/]+)/?$', 'index.php?ppv_shop_slug=$matches[1]', 'top');
    }

    public static function add_query_vars(array $vars): array {
        $vars[] = 'ppv_shop_slug';
        return $vars;
    }

    // -------------------------------------------------------------------------
    // Route handler
    // -------------------------------------------------------------------------

    public static function maybe_render() {
        $slug = get_query_var('ppv_shop_slug');
        if (!$slug) {
            return;
        }

        global $wpdb;

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE store_slug = %s LIMIT 1",
            $slug
        ));

        if (!$store) {
            wp_die(
                esc_html__('Store nicht gefunden.', 'punktepass'),
                esc_html__('404 – Store nicht gefunden', 'punktepass'),
                ['response' => 404]
            );
        }

        // Inactive (canceled or disabled) → 301 redirect to homepage so Google
        // transfers link equity instead of accumulating 404s.
        if (empty($store->active) || empty($store->visible)) {
            wp_redirect(home_url('/'), 301);
            exit;
        }

        self::render_page($store);
        exit;
    }

    // -------------------------------------------------------------------------
    // Page renderer
    // -------------------------------------------------------------------------

    private static function render_page($store) {
        $lang       = self::detect_lang();
        $schema_html = self::get_store_schema_html($store, $lang);
        $meta_desc  = self::get_meta_description($store, $lang);
        $store_name = esc_html($store->name ?? $store->company_name ?? '');
        $city       = esc_html($store->city ?? '');
        $address    = esc_html(trim(($store->address ?? '') . ', ' . ($store->plz ?? '') . ' ' . $city, ', '));
        $logo_url   = !empty($store->logo) ? esc_url($store->logo) : '';
        $phone      = esc_html($store->phone ?? '');
        $website    = !empty($store->website) ? esc_url($store->website) : '';
        $store_url  = esc_url(home_url('/shop/' . $store->store_slug . '/'));

        // Opening hours human-readable
        $hours_html = self::render_opening_hours_html($store->opening_hours ?? '');

        // Trial subscription pages are NOT indexed — only paying merchants
        // get sitemap inclusion + indexable status. Mirrors advertiser logic.
        $is_indexable = (($store->subscription_status ?? '') === 'active');

        status_header(200);
        header('Content-Type: text/html; charset=UTF-8');
        if (!$is_indexable) {
            header('X-Robots-Tag: noindex, nofollow');
        }

        ?><!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $store_name; ?> – PunktePass</title>
<meta name="description" content="<?php echo esc_attr($meta_desc); ?>">
<?php if (!$is_indexable): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<link rel="canonical" href="<?php echo $store_url; ?>">

<?php
        // hreflang for each supported language
        $langs = ['de', 'hu', 'ro', 'en'];
        foreach ($langs as $l) {
            $href = $store_url . ($l === 'de' ? '' : '?lang=' . $l);
            echo '<link rel="alternate" hreflang="' . esc_attr($l) . '" href="' . esc_url($href) . '">' . "\n";
        }
        echo '<link rel="alternate" hreflang="x-default" href="' . $store_url . '">' . "\n";

        // Schema.org JSON-LD
        echo $schema_html;
?>

<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; padding: 0; background: #f7f8fa; color: #222; }
  .ppv-sp-wrap { max-width: 680px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,.08); overflow: hidden; }
  .ppv-sp-header { background: #0071e3; color: #fff; padding: 32px 28px 24px; display: flex; align-items: center; gap: 20px; }
  .ppv-sp-logo { width: 80px; height: 80px; border-radius: 12px; object-fit: cover; background: #fff; flex-shrink: 0; }
  .ppv-sp-logo-placeholder { width: 80px; height: 80px; border-radius: 12px; background: rgba(255,255,255,.2); flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 2rem; }
  .ppv-sp-title { font-size: 1.6rem; font-weight: 700; margin: 0 0 4px; }
  .ppv-sp-city  { opacity: .85; margin: 0; font-size: .95rem; }
  .ppv-sp-body  { padding: 24px 28px; }
  .ppv-sp-section { margin-bottom: 22px; }
  .ppv-sp-section h3 { font-size: 1rem; font-weight: 600; margin: 0 0 8px; color: #555; text-transform: uppercase; letter-spacing: .04em; }
  .ppv-sp-section p, .ppv-sp-section a { font-size: .97rem; color: #333; margin: 0; }
  .ppv-sp-hours table { border-collapse: collapse; width: 100%; }
  .ppv-sp-hours td { padding: 3px 8px 3px 0; font-size: .92rem; }
  .ppv-sp-hours td:first-child { width: 110px; color: #555; }
  .ppv-sp-cta { display: inline-block; margin-top: 12px; padding: 12px 24px; background: #0071e3; color: #fff; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: .97rem; }
  .ppv-sp-footer { text-align: center; padding: 16px; font-size: .82rem; color: #aaa; border-top: 1px solid #f0f0f0; }
  .ppv-sp-footer a { color: #0071e3; text-decoration: none; }
</style>
</head>
<body>
<div class="ppv-sp-wrap">
  <div class="ppv-sp-header">
    <?php if ($logo_url): ?>
      <img src="<?php echo $logo_url; ?>" alt="<?php echo $store_name; ?>" class="ppv-sp-logo">
    <?php else: ?>
      <div class="ppv-sp-logo-placeholder">🏪</div>
    <?php endif; ?>
    <div>
      <p class="ppv-sp-title"><?php echo $store_name; ?></p>
      <p class="ppv-sp-city"><?php echo $city; ?></p>
    </div>
  </div>

  <div class="ppv-sp-body">

    <?php if ($address): ?>
    <div class="ppv-sp-section">
      <h3><?php echo self::label('address', $lang); ?></h3>
      <p><?php echo $address; ?></p>
    </div>
    <?php endif; ?>

    <?php if ($phone): ?>
    <div class="ppv-sp-section">
      <h3><?php echo self::label('phone', $lang); ?></h3>
      <p><a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone)); ?>"><?php echo $phone; ?></a></p>
    </div>
    <?php endif; ?>

    <?php if ($hours_html): ?>
    <div class="ppv-sp-section ppv-sp-hours">
      <h3><?php echo self::label('hours', $lang); ?></h3>
      <?php echo $hours_html; ?>
    </div>
    <?php endif; ?>

    <?php if ($website): ?>
    <div class="ppv-sp-section">
      <h3><?php echo self::label('website', $lang); ?></h3>
      <p><a href="<?php echo $website; ?>" target="_blank" rel="noopener"><?php echo $website; ?></a></p>
    </div>
    <?php endif; ?>

    <div class="ppv-sp-section">
      <h3><?php echo self::label('loyalty', $lang); ?></h3>
      <p><?php echo self::label('loyalty_desc', $lang); ?></p>
      <a href="<?php echo esc_url(home_url('/login/?store=' . urlencode($store->store_slug ?? ''))); ?>" class="ppv-sp-cta">
        <?php echo self::label('collect', $lang); ?>
      </a>
    </div>

  </div>

  <div class="ppv-sp-footer">
    <?php echo self::label('powered', $lang); ?> <a href="https://punktepass.de" target="_blank" rel="noopener">PunktePass</a>
  </div>
</div>
</body>
</html>
        <?php
    }

    // -------------------------------------------------------------------------
    // Schema.org LocalBusiness
    // -------------------------------------------------------------------------

    /**
     * Returns the HTML <script> block(s) for a store.
     * Uses wp_cache (group: ppv_store_schema, key: {id}_{lang}).
     */
    public static function get_store_schema_html($store, string $lang = 'de'): string {
        $cache_key = 'store_' . intval($store->id) . '_' . $lang;
        $cached    = wp_cache_get($cache_key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached;
        }

        $schema = self::build_store_schema($store, $lang);
        $html   = "\n    <!-- PunktePass Schema.org: LocalBusiness -->\n"
                . PPV_Schema_Orgs::render_ld_json($schema);

        wp_cache_set($cache_key, $html, self::CACHE_GROUP, self::CACHE_TTL);
        return $html;
    }

    /**
     * Build the LocalBusiness schema array for a store row.
     * Public so PPV_Schema_Orgs can call it too via get_store_schema().
     */
    public static function build_store_schema($store, string $lang = 'de'): array {
        $site_url   = home_url();
        $store_url  = $site_url . '/shop/' . ($store->store_slug ?? '');
        $store_name = $store->name ?? $store->company_name ?? '';

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'LocalBusiness',
            '@id'      => $store_url . '/#localbusiness',
            'name'     => $store_name,
            'url'      => !empty($store->website) ? esc_url_raw($store->website) : $store_url,
        ];

        // Logo / image
        if (!empty($store->logo)) {
            $schema['image'] = esc_url_raw($store->logo);
        }

        // Address
        $has_address = !empty($store->address) || !empty($store->city);
        if ($has_address) {
            $schema['address'] = [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $store->address ?? '',
                'addressLocality' => $store->city ?? '',
                'postalCode'      => $store->plz ?? '',
                'addressCountry'  => $store->country ?? 'DE',
            ];
        }

        // Geo coordinates
        if (!empty($store->latitude) && !empty($store->longitude)
            && floatval($store->latitude) != 0.0 && floatval($store->longitude) != 0.0) {
            $schema['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => floatval($store->latitude),
                'longitude' => floatval($store->longitude),
            ];
        }

        // Telephone
        if (!empty($store->phone)) {
            $schema['telephone'] = $store->phone;
        }

        // Email
        if (!empty($store->public_email)) {
            $schema['email'] = $store->public_email;
        }

        // Opening hours specification
        $ohs = self::build_opening_hours_spec($store->opening_hours ?? '');
        if (!empty($ohs)) {
            $schema['openingHoursSpecification'] = $ohs;
        }

        // Aggregate rating (from wp_pp_reviews)
        $rating_data = self::get_aggregate_rating(intval($store->id));
        if ($rating_data) {
            $schema['aggregateRating'] = $rating_data;
        }

        // Description (localised)
        $schema['description'] = self::get_meta_description($store, $lang);

        return $schema;
    }

    // -------------------------------------------------------------------------
    // Opening hours
    // -------------------------------------------------------------------------

    /**
     * Convert stored JSON opening hours to Schema.org OpeningHoursSpecification array.
     * Format stored: {"monday": {"von": "09:00", "bis": "18:00", "closed": 0}, ...}
     * Falls back to empty array if JSON is missing/malformed.
     */
    private static function build_opening_hours_spec(string $json): array {
        if (empty($json) || $json === '[]' || $json === '{}') {
            return [];
        }

        $hours = json_decode($json, true);
        if (!is_array($hours)) {
            return [];
        }

        $specs = [];
        foreach (self::$day_map as $key => $schema_day) {
            if (!isset($hours[$key])) {
                continue;
            }
            $day_data = $hours[$key];
            if (!empty($day_data['closed'])) {
                continue; // skip closed days
            }
            $von = $day_data['von'] ?? '';
            $bis = $day_data['bis'] ?? '';
            if (empty($von) || empty($bis)) {
                continue;
            }
            $specs[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => $schema_day,
                'opens'     => $von,
                'closes'    => $bis,
            ];
        }

        return $specs;
    }

    /**
     * Render opening hours as an HTML table (for the public page body).
     */
    private static function render_opening_hours_html(string $json): string {
        if (empty($json) || $json === '[]' || $json === '{}') {
            return '';
        }

        $hours = json_decode($json, true);
        if (!is_array($hours)) {
            return '';
        }

        $day_labels = [
            'monday'    => 'Montag',
            'tuesday'   => 'Dienstag',
            'wednesday' => 'Mittwoch',
            'thursday'  => 'Donnerstag',
            'friday'    => 'Freitag',
            'saturday'  => 'Samstag',
            'sunday'    => 'Sonntag',
        ];

        $rows = '';
        foreach (self::$day_map as $key => $unused) {
            if (!isset($hours[$key])) continue;
            $d   = $hours[$key];
            $lbl = $day_labels[$key];
            if (!empty($d['closed'])) {
                $rows .= '<tr><td>' . esc_html($lbl) . '</td><td>Geschlossen</td></tr>';
            } else {
                $von = $d['von'] ?? '';
                $bis = $d['bis'] ?? '';
                if ($von && $bis) {
                    $rows .= '<tr><td>' . esc_html($lbl) . '</td><td>' . esc_html($von) . ' – ' . esc_html($bis) . '</td></tr>';
                }
            }
        }

        if (!$rows) return '';
        return '<table>' . $rows . '</table>';
    }

    // -------------------------------------------------------------------------
    // Aggregate rating
    // -------------------------------------------------------------------------

    /**
     * Query wp_pp_reviews for a store's average rating + count.
     * Returns null when no reviews exist (schema omits aggregateRating entirely).
     */
    public static function get_aggregate_rating(int $store_id): ?array {
        global $wpdb;

        $cache_key = 'ppv_store_rating_' . $store_id;
        $cached    = wp_cache_get($cache_key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached ?: null;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count
             FROM {$wpdb->prefix}pp_reviews
             WHERE store_id = %d AND rating > 0",
            $store_id
        ));

        if (!$row || intval($row->review_count) < 1) {
            wp_cache_set($cache_key, false, self::CACHE_GROUP, self::CACHE_TTL);
            return null;
        }

        $result = [
            '@type'       => 'AggregateRating',
            'ratingValue' => round(floatval($row->avg_rating), 1),
            'reviewCount' => intval($row->review_count),
            'bestRating'  => 5,
            'worstRating' => 1,
        ];

        wp_cache_set($cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL);
        return $result;
    }

    // -------------------------------------------------------------------------
    // Meta description (localised)
    // -------------------------------------------------------------------------

    public static function get_meta_description($store, string $lang = 'de'): string {
        $name = $store->name ?? $store->company_name ?? '';
        $city = $store->city ?? '';

        switch ($lang) {
            case 'hu':
                return "{$name} – Gyűjts hűségpontokat a(z) {$name} üzletben ({$city}) a PunktePass segítségével.";
            case 'ro':
                return "{$name} – Colectează puncte de fidelitate la {$name} din {$city} cu PunktePass.";
            case 'en':
                return "{$name} – Collect loyalty points at {$name} in {$city} with PunktePass.";
            default: // 'de'
                return "PunktePass bei {$name} in {$city} — sammle Treuepunkte beim {$name}.";
        }
    }

    // -------------------------------------------------------------------------
    // Cache invalidation
    // -------------------------------------------------------------------------

    /**
     * Call this whenever a store's data changes to force schema rebuild on next request.
     */
    public static function flush_store_cache(int $store_id) {
        foreach (['de', 'hu', 'ro', 'en'] as $lang) {
            wp_cache_delete('store_' . $store_id . '_' . $lang, self::CACHE_GROUP);
        }
        wp_cache_delete('ppv_store_rating_' . $store_id, self::CACHE_GROUP);
    }

    // -------------------------------------------------------------------------
    // Language detection
    // -------------------------------------------------------------------------

    private static function detect_lang(): string {
        $lang = sanitize_key($_GET['lang'] ?? '');
        if (in_array($lang, ['de', 'hu', 'ro', 'en'], true)) {
            return $lang;
        }
        // Fallback: try PPV_Lang if available
        if (class_exists('PPV_Lang') && method_exists('PPV_Lang', 'get_current_lang')) {
            $detected = PPV_Lang::get_current_lang();
            if (in_array($detected, ['de', 'hu', 'ro', 'en'], true)) {
                return $detected;
            }
        }
        return 'de';
    }

    // -------------------------------------------------------------------------
    // UI labels
    // -------------------------------------------------------------------------

    private static function label(string $key, string $lang): string {
        $labels = [
            'de' => [
                'address'      => 'Adresse',
                'phone'        => 'Telefon',
                'hours'        => 'Öffnungszeiten',
                'website'      => 'Website',
                'loyalty'      => 'Treueprogramm',
                'loyalty_desc' => 'Sammle Punkte bei jedem Besuch und löse sie gegen tolle Prämien ein.',
                'collect'      => 'Punkte sammeln',
                'powered'      => 'Powered by',
            ],
            'hu' => [
                'address'      => 'Cím',
                'phone'        => 'Telefon',
                'hours'        => 'Nyitvatartás',
                'website'      => 'Weboldal',
                'loyalty'      => 'Hűségprogram',
                'loyalty_desc' => 'Gyűjts pontokat minden látogatáskor, és váltsd be őket jutalmakra.',
                'collect'      => 'Pontok gyűjtése',
                'powered'      => 'Fejlesztette:',
            ],
            'ro' => [
                'address'      => 'Adresă',
                'phone'        => 'Telefon',
                'hours'        => 'Program',
                'website'      => 'Website',
                'loyalty'      => 'Program de fidelitate',
                'loyalty_desc' => 'Colectează puncte la fiecare vizită și schimbă-le cu premii..',
                'collect'      => 'Colectează puncte',
                'powered'      => 'Realizat de',
            ],
            'en' => [
                'address'      => 'Address',
                'phone'        => 'Phone',
                'hours'        => 'Opening hours',
                'website'      => 'Website',
                'loyalty'      => 'Loyalty program',
                'loyalty_desc' => 'Earn points on every visit and redeem them for great rewards.',
                'collect'      => 'Collect points',
                'powered'      => 'Powered by',
            ],
        ];

        return $labels[$lang][$key] ?? ($labels['de'][$key] ?? $key);
    }
}

// Boot
PPV_Store_Page::hooks();
