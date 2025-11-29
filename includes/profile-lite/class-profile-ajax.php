<?php
/**
 * PunktePass Profile Lite - AJAX Handlers Module
 * Contains all ajax_* functions for profile management
 */

if (!defined('ABSPATH')) exit;

class PPV_Profile_Ajax {

    /**
     * Register all AJAX hooks
     */
    public static function register_hooks() {
        add_action('wp_ajax_ppv_get_strings', [__CLASS__, 'ajax_get_strings']);
        add_action('wp_ajax_nopriv_ppv_get_strings', [__CLASS__, 'ajax_get_strings']);
        add_action('wp_ajax_ppv_geocode_address', [__CLASS__, 'ajax_geocode_address']);
        add_action('wp_ajax_nopriv_ppv_geocode_address', [__CLASS__, 'ajax_geocode_address']);
        add_action('wp_ajax_ppv_save_profile', [__CLASS__, 'ajax_save_profile']);
        add_action('wp_ajax_nopriv_ppv_save_profile', [__CLASS__, 'ajax_save_profile']);
        add_action('wp_ajax_ppv_delete_media', [__CLASS__, 'ajax_delete_media']);
        add_action('wp_ajax_nopriv_ppv_delete_media', [__CLASS__, 'ajax_delete_media']);
        add_action('wp_ajax_ppv_auto_save_profile', [__CLASS__, 'ajax_auto_save_profile']);
        add_action('wp_ajax_nopriv_ppv_auto_save_profile', [__CLASS__, 'ajax_auto_save_profile']);
        add_action('wp_ajax_ppv_delete_gallery_image', [__CLASS__, 'ajax_delete_gallery_image']);
        add_action('wp_ajax_nopriv_ppv_delete_gallery_image', [__CLASS__, 'ajax_delete_gallery_image']);
        add_action('wp_ajax_ppv_reset_trusted_device', [__CLASS__, 'ajax_reset_trusted_device']);
        add_action('wp_ajax_nopriv_ppv_reset_trusted_device', [__CLASS__, 'ajax_reset_trusted_device']);
        add_action('wp_ajax_ppv_activate_referral_grace_period', [__CLASS__, 'ajax_activate_referral_grace_period']);
        add_action('wp_ajax_nopriv_ppv_activate_referral_grace_period', [__CLASS__, 'ajax_activate_referral_grace_period']);
    }

        public static function ajax_get_strings() {
            $lang = sanitize_text_field($_GET['lang'] ?? PPV_Lang::current());
            $file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-{$lang}.php";
            if (!file_exists($file)) {
                $file = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-de.php";
            }
            $strings = include $file;
            wp_send_json_success($strings);
        }

public static function ajax_save_profile() {
    global $wpdb; // ✅ FIX: Declare $wpdb at the start of the function

    if (!isset($_POST[PPV_Profile_Lite_i18n::NONCE_NAME])) {
        wp_send_json_error(['msg' => 'Nonce missing']);
    }

    if (!wp_verify_nonce($_POST[PPV_Profile_Lite_i18n::NONCE_NAME], PPV_Profile_Lite_i18n::NONCE_ACTION)) {
        wp_send_json_error(['msg' => 'Invalid nonce']);
    }

    PPV_Profile_Auth::ensure_session();
    $auth = PPV_Profile_Auth::check_auth();

    if (!$auth['valid']) {
        wp_send_json_error(['msg' => PPV_Lang::t('error')]);
    }

    // 🏪 FILIALE SUPPORT: ALWAYS use session-aware store ID, ignore POST parameter
    $store_id = PPV_Profile_Auth::get_store_id();

    if ($auth['type'] === 'ppv_stores' && $store_id != $auth['store_id']) {
        wp_send_json_error(['msg' => 'Unauthorized']);
    }

    $upload_dir = wp_upload_dir();
    $gallery_files = [];

    // ✅ FIX: Get existing store data to preserve logo/gallery if not uploading new
    $existing_store = $wpdb->get_row($wpdb->prepare(
        "SELECT logo, gallery FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
        $store_id
    ));

    // Logo upload
    if (!empty($_FILES['logo']['name'])) {
        $tmp_file = $_FILES['logo']['tmp_name'];
        $filename = basename($_FILES['logo']['name']);
        $new_file = $upload_dir['path'] . '/' . $filename;

        if (move_uploaded_file($tmp_file, $new_file)) {
            $_POST['logo'] = $upload_dir['url'] . '/' . $filename;
        }
    } else {
        // ✅ FIX: Preserve existing logo if no new upload
        $_POST['logo'] = $existing_store->logo ?? '';
    }

    // Gallery upload
    if (!empty($_FILES['gallery']['name'][0])) {
        // ✅ FIX: Get existing gallery to merge with new uploads
        $existing_gallery = json_decode($existing_store->gallery ?? '[]', true) ?: [];

        foreach ($_FILES['gallery']['name'] as $key => $filename) {
            if ($_FILES['gallery']['error'][$key] === UPLOAD_ERR_OK) {
                $tmp_file = $_FILES['gallery']['tmp_name'][$key];
                $new_file = $upload_dir['path'] . '/' . basename($filename);

                if (move_uploaded_file($tmp_file, $new_file)) {
                    $gallery_files[] = $upload_dir['url'] . '/' . basename($filename);
                }
            }
        }

        // ✅ FIX: Merge new uploads with existing gallery (append new images)
        $gallery_files = array_merge($existing_gallery, $gallery_files);
    }


    // ============================================================
    // ✅ OPENING HOURS - FELDOLGOZÁS
    // ============================================================
    $opening_hours = [];
    $days = ['mo', 'di', 'mi', 'do', 'fr', 'sa', 'so'];
    
    foreach ($days as $day) {
        $von = sanitize_text_field($_POST['hours'][$day]['von'] ?? '');
        $bis = sanitize_text_field($_POST['hours'][$day]['bis'] ?? '');
        $closed = !empty($_POST['hours'][$day]['closed']) ? 1 : 0;
        
        $opening_hours[$day] = [
            'von' => $von,
            'bis' => $bis,
            'closed' => $closed
        ];
    }
    // ============================================================
    // ✅ ÖSSZES MEZŐ - TAX_ID ÉS IS_TAXABLE BENNE!
    // ============================================================
    $update_data = [
        'name' => sanitize_text_field($_POST['store_name'] ?? ''),
        'country' => sanitize_text_field($_POST['country'] ?? 'DE'),

        'latitude' => floatval($_POST['latitude'] ?? 0),
        'longitude' => floatval($_POST['longitude'] ?? 0),
        
        'slogan' => sanitize_text_field($_POST['slogan'] ?? ''),
        'category' => sanitize_text_field($_POST['category'] ?? ''),
        'address' => sanitize_text_field($_POST['address'] ?? ''),
        'plz' => sanitize_text_field($_POST['plz'] ?? ''),
        'city' => sanitize_text_field($_POST['city'] ?? ''),

        // ✅ COMPANY FIELDS (were missing!)
        'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
        'contact_person' => sanitize_text_field($_POST['contact_person'] ?? ''),

        // ✅ ÚJ MEZŐK:
        'tax_id' => sanitize_text_field($_POST['tax_id'] ?? ''),
        'is_taxable' => !empty($_POST['is_taxable']) ? 1 : 0,
        
        'phone' => sanitize_text_field($_POST['phone'] ?? ''),
        'email' => sanitize_email($_POST['email'] ?? ''),
        'website' => esc_url_raw($_POST['website'] ?? ''),
        'whatsapp' => sanitize_text_field($_POST['whatsapp'] ?? ''),
        'facebook' => esc_url_raw($_POST['facebook'] ?? ''),
        'instagram' => esc_url_raw($_POST['instagram'] ?? ''),
        'tiktok' => esc_url_raw($_POST['tiktok'] ?? ''),
        'description' => wp_kses_post($_POST['description'] ?? ''),
        'active' => !empty($_POST['active']) ? 1 : 0,
        'visible' => !empty($_POST['visible']) ? 1 : 0,
        'maintenance_mode' => !empty($_POST['maintenance_mode']) ? 1 : 0,
        'maintenance_message' => wp_kses_post($_POST['maintenance_message'] ?? ''),
        'timezone' => sanitize_text_field($_POST['timezone'] ?? 'Europe/Berlin'),
        'updated_at' => current_time('mysql'),
        'logo' => sanitize_text_field($_POST['logo'] ?? ''),
// ✅ FIX: Preserve existing gallery if no new uploads
        'gallery' => !empty($gallery_files) ? json_encode($gallery_files) : ($existing_store->gallery ?? ''),
    'opening_hours' => json_encode($opening_hours),

        // ============================================================
        // ✅ MARKETING AUTOMATION FIELDS
        // ============================================================
        // Google Review
        'google_review_enabled' => !empty($_POST['google_review_enabled']) ? 1 : 0,
        'google_review_url' => esc_url_raw($_POST['google_review_url'] ?? ''),
        'google_review_threshold' => intval($_POST['google_review_threshold'] ?? 100),
        'google_review_frequency' => sanitize_text_field($_POST['google_review_frequency'] ?? 'once'),

        // Birthday Bonus
        'birthday_bonus_enabled' => !empty($_POST['birthday_bonus_enabled']) ? 1 : 0,
        'birthday_bonus_type' => sanitize_text_field($_POST['birthday_bonus_type'] ?? 'double_points'),
        'birthday_bonus_value' => intval($_POST['birthday_bonus_value'] ?? 0),
        'birthday_bonus_message' => sanitize_text_field($_POST['birthday_bonus_message'] ?? ''),

        // Comeback Campaign
        'comeback_enabled' => !empty($_POST['comeback_enabled']) ? 1 : 0,
        'comeback_days' => intval($_POST['comeback_days'] ?? 30),
        'comeback_bonus_type' => sanitize_text_field($_POST['comeback_bonus_type'] ?? 'double_points'),
        'comeback_bonus_value' => intval($_POST['comeback_bonus_value'] ?? 50),
        'comeback_message' => sanitize_text_field($_POST['comeback_message'] ?? ''),

        // ============================================================
        // ✅ WHATSAPP CLOUD API - Only enable/disable toggle
        // API settings are managed in /admin/whatsapp
        // ============================================================
        'whatsapp_enabled' => !empty($_POST['whatsapp_enabled']) ? 1 : 0,

        // ============================================================
        // ✅ REFERRAL PROGRAM FIELDS
        // ============================================================
        'referral_enabled' => !empty($_POST['referral_enabled']) ? 1 : 0,
        'referral_grace_days' => max(7, min(180, intval($_POST['referral_grace_days'] ?? 60))),
        'referral_reward_type' => in_array($_POST['referral_reward_type'] ?? '', ['points', 'euro', 'gift']) ? $_POST['referral_reward_type'] : 'points',
        'referral_reward_value' => intval($_POST['referral_reward_value'] ?? $_POST['referral_reward_value_euro'] ?? 50),
        'referral_reward_gift' => sanitize_text_field($_POST['referral_reward_gift'] ?? ''),
        'referral_manual_approval' => !empty($_POST['referral_manual_approval']) ? 1 : 0,
];

// ✅ Format specifierek az összes mezőhöz
$format_specs = [
    '%s',  // name
    '%s',  // country
    '%f',  // latitude
    '%f',  // longitude
    '%s',  // slogan
    '%s',  // category
    '%s',  // address
    '%s',  // plz
    '%s',  // city
    '%s',  // company_name
    '%s',  // contact_person
    '%s',  // tax_id
    '%d',  // is_taxable
    '%s',  // phone
    '%s',  // email
    '%s',  // website
    '%s',  // whatsapp
    '%s',  // facebook
    '%s',  // instagram
    '%s',  // tiktok
    '%s',  // description
    '%d',  // active
    '%d',  // visible
    '%d',  // maintenance_mode
    '%s',  // maintenance_message
    '%s',  // timezone
    '%s',  // updated_at
    '%s',  // logo
    '%s',  // gallery
    '%s',  // opening_hours
    // Marketing Automation
    '%d',  // google_review_enabled
    '%s',  // google_review_url
    '%d',  // google_review_threshold
    '%s',  // google_review_frequency
    '%d',  // birthday_bonus_enabled
    '%s',  // birthday_bonus_type
    '%d',  // birthday_bonus_value
    '%s',  // birthday_bonus_message
    '%d',  // comeback_enabled
    '%d',  // comeback_days
    '%s',  // comeback_bonus_type
    '%d',  // comeback_bonus_value
    '%s',  // comeback_message
    // WhatsApp Cloud API - only enable toggle (settings managed in /admin/whatsapp)
    '%d',  // whatsapp_enabled
    // Referral Program
    '%d',  // referral_enabled
    '%d',  // referral_grace_days
    '%s',  // referral_reward_type
    '%d',  // referral_reward_value
    '%s',  // referral_reward_gift
    '%d',  // referral_manual_approval
];

ppv_log("💾 [DEBUG] Saving store ID: {$store_id}");
ppv_log("💾 [DEBUG] Country: " . ($update_data['country'] ?? 'NULL'));
ppv_log("💾 [DEBUG] Store Name: " . ($update_data['name'] ?? 'NULL'));
ppv_log("💾 [DEBUG] Session store_id: " . ($_SESSION['ppv_store_id'] ?? 'NULL'));
ppv_log("💾 [DEBUG] Session filiale_id: " . ($_SESSION['ppv_current_filiale_id'] ?? 'NULL'));

$result = $wpdb->update(
    $wpdb->prefix . 'ppv_stores',
    $update_data,
    ['id' => $store_id],
    $format_specs,
    ['%d']
);

ppv_log("💾 [DEBUG] Update result: " . ($result !== false ? 'OK (rows: ' . $result . ')' : 'FAILED'));
ppv_log("💾 [DEBUG] Last SQL error: " . $wpdb->last_error);

    if ($result !== false) {
        // ✅ FIX: Return updated store data so JS can refresh form fields without reload
        $updated_store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $store_id
        ));

        wp_send_json_success([
            'msg' => PPV_Lang::t('profile_saved_success'),
            'store_id' => $store_id,
            'store' => $updated_store  // ✅ This enables updateFormFields() in JS
        ]);
    } else {
        wp_send_json_error(['msg' => PPV_Lang::t('profile_save_error')]);
    }
}

        public static function ajax_auto_save_profile() {
            if (!isset($_POST[PPV_Profile_Lite_i18n::NONCE_NAME])) {
                wp_send_json_error(['msg' => 'Nonce missing']);
            }

            if (!wp_verify_nonce($_POST[PPV_Profile_Lite_i18n::NONCE_NAME], PPV_Profile_Lite_i18n::NONCE_ACTION)) {
                wp_send_json_error(['msg' => 'Invalid nonce']);
            }

            PPV_Profile_Auth::ensure_session();
            $auth = PPV_Profile_Auth::check_auth();

            if (!$auth['valid']) {
                wp_send_json_error(['msg' => 'Not authenticated']);
            }

            $draft_data = $_POST['draft'] ?? [];

            // 🏪 FILIALE SUPPORT: ALWAYS use session-aware store ID, ignore POST parameter
            $store_id = PPV_Profile_Auth::get_store_id();

            if ($auth['type'] === 'ppv_stores' && $store_id != $auth['store_id']) {
                wp_send_json_error(['msg' => 'Unauthorized']);
            }

            global $wpdb;
            $wpdb->update($wpdb->prefix . 'ppv_stores', ['draft_data' => json_encode($draft_data)], ['id' => $store_id], ['%s'], ['%d']);

            wp_send_json_success(['msg' => 'Draft saved', 'timestamp' => current_time('mysql')]);
        }
        
        public static function ajax_delete_gallery_image() {
            if (!isset($_POST[PPV_Profile_Lite_i18n::NONCE_NAME])) {
                wp_send_json_error(['msg' => 'Nonce missing']);
            }

            if (!wp_verify_nonce($_POST[PPV_Profile_Lite_i18n::NONCE_NAME], PPV_Profile_Lite_i18n::NONCE_ACTION)) {
                wp_send_json_error(['msg' => 'Invalid nonce']);
            }

            PPV_Profile_Auth::ensure_session();
            $auth = PPV_Profile_Auth::check_auth();

            if (!$auth['valid']) {
                wp_send_json_error(['msg' => 'Not authenticated']);
            }

            // 🏪 FILIALE SUPPORT: ALWAYS use session-aware store ID, ignore POST parameter
            $store_id = PPV_Profile_Auth::get_store_id();
            $image_url = sanitize_text_field($_POST['image_url'] ?? '');

            if ($auth['type'] === 'ppv_stores' && $store_id != $auth['store_id']) {
                wp_send_json_error(['msg' => 'Unauthorized']);
            }

            global $wpdb;
            $store = $wpdb->get_row($wpdb->prepare("SELECT gallery FROM {$wpdb->prefix}ppv_stores WHERE id = %d", $store_id));

            if (!$store) {
                wp_send_json_error(['msg' => 'Store not found']);
            }

            $gallery = json_decode($store->gallery, true);
            if (!is_array($gallery)) {
                wp_send_json_error(['msg' => 'Gallery error']);
            }

            // Remove the image
            $gallery = array_filter($gallery, function($url) use ($image_url) {
                return $url !== $image_url;
            });

            // Reindex array
            $gallery = array_values($gallery);

            // Update database
            $result = $wpdb->update(
                $wpdb->prefix . 'ppv_stores',
                ['gallery' => json_encode($gallery)],
                ['id' => $store_id],
                ['%s'],
                ['%d']
            );

            if ($result !== false) {
                wp_send_json_success(['msg' => 'Törölt']);
            } else {
                wp_send_json_error(['msg' => 'Hiba']);
            }
        }

        public static function ajax_delete_media() {
        // Nonce ellenőrzés
            if (!isset($_POST[PPV_Profile_Lite_i18n::NONCE_NAME])) {
                wp_send_json_error(['msg' => 'Nonce missing']);
                return;
            }

            if (!wp_verify_nonce($_POST[PPV_Profile_Lite_i18n::NONCE_NAME], PPV_Profile_Lite_i18n::NONCE_ACTION)) {
                wp_send_json_error(['msg' => 'Invalid nonce']);
                return;
            }
            PPV_Profile_Auth::ensure_session();
            $auth = PPV_Profile_Auth::check_auth();

            if (!$auth['valid']) {
                wp_send_json_error(['msg' => 'Unauthorized']);
            }

            $media_id = intval($_POST['media_id'] ?? 0);

            if (wp_delete_attachment($media_id, true)) {
                wp_send_json_success(['msg' => 'Deleted']);
            } else {
                wp_send_json_error(['msg' => 'Error']);
            }
        }

        public static function handle_form_submit() {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST[PPV_Profile_Lite_i18n::NONCE_NAME])) {
                return;
            }

            if (!wp_verify_nonce($_POST[PPV_Profile_Lite_i18n::NONCE_NAME], PPV_Profile_Lite_i18n::NONCE_ACTION)) {
                wp_die(esc_html(PPV_Lang::t('error')));
            }
        }
/**
 * 🗺️ GEOCODE ADDRESS - FIX (Romania/Hungary support)
 * Egyszerűen másold be ezt a függvényt a PHP fájlba
 */

public static function ajax_geocode_address() {
    if (!isset($_POST[PPV_Profile_Lite_i18n::NONCE_NAME])) {
        wp_send_json_error(['msg' => 'Nonce missing']);
        return;
    }

    if (!wp_verify_nonce($_POST[PPV_Profile_Lite_i18n::NONCE_NAME], PPV_Profile_Lite_i18n::NONCE_ACTION)) {
        wp_send_json_error(['msg' => 'Invalid nonce']);
        return;
    }

    PPV_Profile_Auth::ensure_session();

    $address = sanitize_text_field($_POST['address'] ?? '');
    $plz = sanitize_text_field($_POST['plz'] ?? '');
    $city = sanitize_text_field($_POST['city'] ?? '');
    $country = sanitize_text_field($_POST['country'] ?? 'DE');

if (empty($address) || empty($city) || empty($country)) {
    wp_send_json_error(['msg' => 'Cím, város és ország szükséges!']);
    return;
}
    // ✅ ORSZÁG NEVEI
    $country_names = [
        'DE' => 'Deutschland',
        'HU' => 'Hungary',
        'RO' => 'Romania'
    ];
    $country_name = $country_names[$country] ?? 'Germany';

    // ✅ JOBB FORMÁTUM (vessző, ország)
    $full_address = "{$address}, {$plz} {$city}, {$country_name}";
    
    ppv_log("🔍 [PPV_GEOCODE] Keresés: {$full_address}");

    $google_api_key = defined('PPV_GOOGLE_MAPS_KEY') ? PPV_GOOGLE_MAPS_KEY : '';


// ============================================================
// 1️⃣ GOOGLE MAPS GEOCODING (Erőteljes keresés)
// ============================================================
if ($google_api_key) {
    ppv_log("🔍 [PPV_GEOCODE] Google Maps API keresés iniciálva");
    
    // Több keresési variáns
    $search_variants = [
        $full_address, // Teljes: "Str. Noua 742, 447080 Capleni, Romania"
        "{$address}, {$plz} {$city}, {$country_name}",
        "{$address}, {$city}, {$country_name}",
        str_replace(['Str.', 'str.'], 'Strada', $address) . ", {$plz} {$city}, {$country_name}",
    ];

    foreach ($search_variants as $search_query) {
        ppv_log("  → Variáns: {$search_query}");
        
        $url = 'https://maps.googleapis.com/maps/api/geocode/json';
        $response = wp_remote_get(
            add_query_arg([
                'address' => $search_query,
                'components' => 'country:' . strtolower($country),
                'key' => $google_api_key,
                'language' => 'en'
            ], $url),
            ['timeout' => 10]
        );

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            ppv_log("  ✓ Status: " . ($data['status'] ?? 'unknown'));

            if ($data['status'] === 'OK' && !empty($data['results'])) {
                $first = $data['results'][0];
                $lat = floatval($first['geometry']['location']['lat'] ?? 0);
                $lon = floatval($first['geometry']['location']['lng'] ?? 0);

                $detected_country = 'DE';
                if (!empty($first['address_components'])) {
                    foreach ($first['address_components'] as $component) {
                        if (in_array('country', $component['types'], true)) {
                            $detected_country = strtoupper($component['short_name']);
                            break;
                        }
                    }
                }

                ppv_log("✅ [PPV_GEOCODE] Google Maps MEGTALÁLTA: {$lat}, {$lon} ({$detected_country})");

                wp_send_json_success([
                    'lat' => round($lat, 4),
                    'lon' => round($lon, 4),
                    'country' => $detected_country,
                    'display_name' => $first['formatted_address'] ?? $search_query,
                    'source' => 'google_maps'
                ]);
                return;
            }
        }
    }
    
ppv_log("⚠️ [PPV_GEOCODE] Google Maps utca: NINCS TALÁLAT - fallback városra");

// FALLBACK: Csak város keresése
$city_search = "{$city}, {$country_name}";
ppv_log("🔍 [PPV_GEOCODE] Fallback keresés: {$city_search}");

$url = 'https://maps.googleapis.com/maps/api/geocode/json';
$response = wp_remote_get(
    add_query_arg([
        'address' => $city_search,
        'key' => $google_api_key,
        'language' => 'en'
    ], $url),
    ['timeout' => 10]
);

if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if ($data['status'] === 'OK' && !empty($data['results'])) {
        $first = $data['results'][0];
        $lat = floatval($first['geometry']['location']['lat'] ?? 0);
        $lon = floatval($first['geometry']['location']['lng'] ?? 0);

        $detected_country = 'DE';
        if (!empty($first['address_components'])) {
            foreach ($first['address_components'] as $component) {
                if (in_array('country', $component['types'], true)) {
                    $detected_country = strtoupper($component['short_name']);
                    break;
                }
            }
        }

        ppv_log("✅ [PPV_GEOCODE] Város MEGTALÁLVA: {$lat}, {$lon}");

        // 🔴 FONTOS: flag hogy manuálisra kell váltani
        wp_send_json_success([
            'lat' => round($lat, 4),
            'lon' => round($lon, 4),
            'country' => $detected_country,
            'display_name' => $first['formatted_address'] ?? $city_search,
            'source' => 'google_maps_city',
            'open_manual_map' => true  // ← FONTOS!
        ]);
        return;
    }
}

ppv_log("❌ [PPV_GEOCODE] Város sem találva!");
}


// ============================================================
// 2️⃣ OPENSTREETMAP (Nominatim) - FALLBACK (Multistep search)
// ============================================================

$search_variants = [
    // 1. Teljes: "Str. Noua 742, 447080 Capleni, Romania"
    "{$address}, {$plz} {$city}, {$country_name}",
    
    // 2. "Strada Noua" helyett (román forma)
    str_replace(['Str.', 'str.'], 'Strada', "{$address}, {$plz} {$city}, {$country_name}"),
    
    // 3. Csak házszám nélkül: "Str. Noua, 447080 Capleni, Romania"
    "{$address}, {$plz} {$city}, {$country_name}",
    
    // 4. Vezetéknév nélkül: "Noua 742, Capleni, Romania"
    preg_replace('/^Str\.\s*/', '', $address) . ", {$city}, {$country_name}",
];

foreach ($search_variants as $idx => $search_query) {
    ppv_log("🔍 [PPV_GEOCODE] Keresési variáns #" . ($idx + 1) . ": {$search_query}");
    
    $url = 'https://nominatim.openstreetmap.org/search';
    $response = wp_remote_get(
        add_query_arg([
            'format' => 'json',
            'q' => $search_query,
            'limit' => 10,
            'addressdetails' => 1,
            'bounded' => 1,
            'viewbox' => '20.2,43.6,29.8,48.3' // Romania bounding box
        ], $url),
        [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'PunktePass (https://punktepass.de)',
                'Accept-Language' => 'de,en;q=0.9'
            ]
        ]
    );

    if (!is_wp_error($response)) {
        $results = json_decode(wp_remote_retrieve_body($response), true);
        
        ppv_log("📍 [PPV_GEOCODE] Variáns #" . ($idx + 1) . " találatok: " . count($results ?? []) . "");
        
        if (!empty($results)) {
            // Legjobb találat: házszámos street vagy épület
            $best = null;
            
            foreach ($results as $result) {
                $type = $result['addresstype'] ?? '';
                $importance = floatval($result['importance'] ?? 0);
                
                // Prioritás: house > building > street
                if ($type === 'house' || $type === 'building') {
                    if (!$best || $importance > floatval($best['importance'] ?? 0)) {
                        $best = $result;
                    }
                }
            }
            
            // Ha nincs house/building, próbáljunk street-et
            if (!$best) {
                foreach ($results as $result) {
                    if ($result['addresstype'] === 'street') {
                        $best = $result;
                        break;
                    }
                }
            }
            
            // Ha még sincs, első találat
            if (!$best) {
                $best = $results[0];
            }

            if ($best) {
                $lat = floatval($best['lat']);
                $lon = floatval($best['lon']);
                $detected_country = 'DE';

                if (!empty($best['address'])) {
                    $addr = $best['address'];
                    if (!empty($addr['country_code'])) {
                        $detected_country = strtoupper($addr['country_code']);
                    }
                }

                ppv_log("✅ [PPV_GEOCODE] Nominatim MEGTALÁLVA (variáns #" . ($idx + 1) . "): {$lat}, {$lon} ({$detected_country})");
                ppv_log("   Display: " . ($best['display_name'] ?? 'N/A'));

                wp_send_json_success([
                    'lat' => round($lat, 4),
                    'lon' => round($lon, 4),
                    'country' => $detected_country,
                    'display_name' => $best['display_name'] ?? $search_query,
                    'source' => 'nominatim_variant_' . ($idx + 1)
                ]);
                return;
            }
        }
    }
    
    // Kis késleltetés az API-hoz
    usleep(500000);
}

ppv_log("❌ [PPV_GEOCODE] Egyik variáns sem találta meg: {$full_address}");
wp_send_json_error(['msg' => 'A cím nem található! Próbáld meg máshogyan írni (pl. teljes utcanévvel).']);
}

        /**
         * ============================================================
         * 🔒 RESET TRUSTED DEVICE FINGERPRINT
         * ============================================================
         */
        public static function ajax_reset_trusted_device() {
            PPV_Profile_Auth::ensure_session();

            $auth = PPV_Profile_Auth::check_auth();
            if (!$auth['valid']) {
                wp_send_json_error(['message' => 'Nincs jogosultság']);
                return;
            }

            $store_id = PPV_Profile_Auth::get_store_id();
            if (!$store_id) {
                wp_send_json_error(['message' => 'Store not found']);
                return;
            }

            global $wpdb;

            // Reset the trusted device fingerprint
            $result = $wpdb->update(
                "{$wpdb->prefix}ppv_stores",
                ['trusted_device_fingerprint' => null],
                ['id' => $store_id]
            );

            if ($result !== false) {
                ppv_log("[PPV_DEVICES] Trusted device reset for store #{$store_id}");
                wp_send_json_success(['message' => 'Megbízható eszköz visszaállítva']);
            } else {
                wp_send_json_error(['message' => 'Hiba történt']);
            }
        }

        /**
         * ============================================================
         * 🎁 ACTIVATE REFERRAL GRACE PERIOD
         * ============================================================
         */
        public static function ajax_activate_referral_grace_period() {
            PPV_Profile_Auth::ensure_session();

            $auth = PPV_Profile_Auth::check_auth();
            if (!$auth['valid']) {
                wp_send_json_error(['msg' => 'Nincs jogosultság']);
                return;
            }

            $store_id = PPV_Profile_Auth::get_store_id();
            if (!$store_id) {
                wp_send_json_error(['msg' => 'Store not found']);
                return;
            }

            global $wpdb;

            // Check if already activated
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT referral_activated_at FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
                $store_id
            ));

            if (!empty($store->referral_activated_at)) {
                wp_send_json_error(['msg' => 'Grace Period bereits aktiviert']);
                return;
            }

            // Activate grace period
            $result = $wpdb->update(
                "{$wpdb->prefix}ppv_stores",
                ['referral_activated_at' => current_time('mysql')],
                ['id' => $store_id],
                ['%s'],
                ['%d']
            );

            if ($result !== false) {
                ppv_log("[PPV_REFERRAL] Grace period started for store #{$store_id}");
                wp_send_json_success(['msg' => 'Grace Period gestartet!']);
            } else {
                wp_send_json_error(['msg' => 'Fehler beim Starten der Grace Period']);
            }
        }

}
