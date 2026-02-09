<?php
/**
 * PunktePass Lead Finder - Business Email Extractor
 * Route: /formular/lead-finder
 * Finds businesses in a region and extracts their contact info
 */

if (!defined('ABSPATH')) exit;

class PPV_Lead_Finder {

    /**
     * Render lead finder page
     */
    public static function render() {
        global $wpdb;

        // Ensure table exists
        self::maybe_create_table();

        // Handle POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['start_search'])) {
                self::handle_search();
            } elseif (isset($_POST['manual_import'])) {
                self::handle_manual_import();
            } elseif (isset($_POST['scrape_website'])) {
                self::handle_scrape();
            } elseif (isset($_POST['delete_lead'])) {
                self::handle_delete();
            } elseif (isset($_POST['export_emails'])) {
                self::handle_export();
            } elseif (isset($_POST['delete_all_leads'])) {
                self::handle_delete_all();
            } elseif (isset($_POST['delete_selected_leads'])) {
                self::handle_delete_selected();
            }
        }

        // Handle AJAX scrape
        if (isset($_GET['ajax_scrape']) && isset($_GET['id'])) {
            self::ajax_scrape_website();
            exit;
        }

        // Tavily API test endpoint: /formular/lead-finder?tavily_test=1
        if (isset($_GET['tavily_test'])) {
            self::tavily_test();
            exit;
        }

        // Get filters
        $filter_region = isset($_GET['region']) ? sanitize_text_field($_GET['region']) : '';
        $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        // Build WHERE
        $where = "1=1";
        if (!empty($filter_region)) {
            $where .= $wpdb->prepare(" AND region = %s", $filter_region);
        }
        if ($filter_status === 'with_email') {
            $where .= " AND email != ''";
        } elseif ($filter_status === 'no_email') {
            $where .= " AND email = ''";
        } elseif ($filter_status === 'not_scraped') {
            $where .= " AND scraped_at IS NULL";
        }

        // Get leads
        $leads = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ppv_leads WHERE $where ORDER BY created_at DESC LIMIT 500");

        // Get stats
        $total_leads = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_leads");
        $with_email = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_leads WHERE email != ''");
        $not_scraped = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_leads WHERE scraped_at IS NULL");

        // Get unique regions
        $regions = $wpdb->get_col("SELECT DISTINCT region FROM {$wpdb->prefix}ppv_leads WHERE region != '' ORDER BY region");

        self::render_html($leads, $total_leads, $with_email, $not_scraped, $regions, $filter_region, $filter_status);
    }

    /**
     * Create table if not exists
     */
    private static function maybe_create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table = $wpdb->prefix . 'ppv_leads';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $sql = "CREATE TABLE $table (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                business_name varchar(500) NOT NULL,
                region varchar(255) DEFAULT '',
                address varchar(500) DEFAULT '',
                phone varchar(100) DEFAULT '',
                website varchar(500) DEFAULT '',
                email varchar(255) DEFAULT '',
                keyword varchar(255) DEFAULT '',
                search_query varchar(255) DEFAULT '',
                scraped_at datetime DEFAULT NULL,
                scrape_status varchar(50) DEFAULT '',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                notes text DEFAULT '',
                PRIMARY KEY (id),
                KEY region (region),
                KEY email (email),
                KEY scraped_at (scraped_at)
            ) $charset_collate;";
            dbDelta($sql);
        }

        // Add keyword column if it doesn't exist (for existing tables)
        $col = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'keyword'");
        if (empty($col)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN keyword varchar(255) DEFAULT '' AFTER email");
        }
    }

    /**
     * Handle search - uses multiple sources to find businesses
     */
    private static function handle_search() {
        global $wpdb;

        $query = sanitize_text_field($_POST['search_query'] ?? '');
        $region = sanitize_text_field($_POST['search_region'] ?? '');

        if (empty($query) || empty($region)) {
            wp_redirect("/formular/lead-finder?error=empty_search");
            exit;
        }

        $full_query = "$query $region";
        $found_count = 0;

        // Method 1: Google Maps search (via places autocomplete-like approach)
        $maps_results = self::search_google_maps($query, $region);
        foreach ($maps_results as $business) {
            if (self::add_lead_if_new($business, $region, $full_query)) {
                $found_count++;
            }
        }

        // Method 2: Bing Places search
        $bing_results = self::search_bing($full_query);
        foreach ($bing_results as $business) {
            if (self::add_lead_if_new($business, $region, $full_query)) {
                $found_count++;
            }
        }

        // Method 3: DuckDuckGo search
        $ddg_results = self::search_duckduckgo($full_query);
        foreach ($ddg_results as $business) {
            if (self::add_lead_if_new($business, $region, $full_query)) {
                $found_count++;
            }
        }

        wp_redirect("/formular/lead-finder?success=search&found=$found_count&region=" . urlencode($region));
        exit;
    }

    /**
     * Handle manual import from Google Maps paste
     */
    private static function handle_manual_import() {
        global $wpdb;

        $content = $_POST['import_content'] ?? '';
        $region = sanitize_text_field($_POST['import_region'] ?? '');
        $keyword = sanitize_text_field($_POST['import_keyword'] ?? '');

        if (empty($content)) {
            wp_redirect("/formular/lead-finder?error=empty_import");
            exit;
        }

        $found_count = 0;

        // Google Maps list format detection
        // Business names appear before ratings like "5,0" or "4,6" followed by stars ★ or (reviews)
        // Pattern: "Business Name\n5,0 ★★★★★ (36)" or "Business Name\n4,6(53)"

        // Method 1: Split by lines and look for patterns
        $lines = preg_split('/[\r\n]+/', $content);
        $business_entries = []; // [{name, line_index, website, phone, address, email}]
        $current_business_idx = -1;

        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;
            $next_line = isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '';

            // Check if next line starts with a rating (e.g., "5,0", "4,6", "3,8") or "Keine Rezensionen"
            if (preg_match('/^[1-5][,\.]\d\s*(?:★|⭐|\()|^Keine Rezensionen/u', $next_line)) {
                if (strlen($line) >= 4 && strlen($line) <= 150 && self::is_valid_business_name($line)) {
                    $current_business_idx = count($business_entries);
                    $business_entries[] = [
                        'name' => $line,
                        'line_index' => $i,
                        'website' => '',
                        'phone' => '',
                        'address' => '',
                        'email' => ''
                    ];
                }
                continue;
            }

            // For lines after a business name, try to extract data and associate it
            if ($current_business_idx >= 0) {
                // Extract website domain from line (Google Maps shows domains like "stuttgartphone.de" or "www.example.de")
                if (empty($business_entries[$current_business_idx]['website'])) {
                    // Full URLs
                    if (preg_match('/(https?:\/\/(?:www\.)?[a-zA-Z0-9][a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}(?:\/[^\s]*)?)/i', $line, $url_m)) {
                        $domain = parse_url($url_m[1], PHP_URL_HOST);
                        if ($domain && !preg_match('/(google|facebook|instagram|twitter|youtube|yelp|tripadvisor|wikipedia|amazon|ebay|linkedin|tiktok|pinterest)/i', $domain)) {
                            $business_entries[$current_business_idx]['website'] = $url_m[1];
                        }
                    }
                    // Bare domain names like "example.de" or "www.example.de"
                    elseif (preg_match('/^((?:www\.)?[a-zA-Z0-9][a-zA-Z0-9\-]+\.[a-zA-Z]{2,}(?:\.[a-zA-Z]{2})?)$/i', $line, $domain_m)) {
                        $domain = $domain_m[1];
                        if (!preg_match('/(google|facebook|instagram|twitter|youtube|yelp|tripadvisor|wikipedia|amazon|ebay|linkedin|tiktok|pinterest)/i', $domain)) {
                            $business_entries[$current_business_idx]['website'] = 'https://' . $domain;
                        }
                    }
                }

                // Extract phone numbers
                if (empty($business_entries[$current_business_idx]['phone'])) {
                    if (preg_match('/((?:\+49|0049|0)\s*[\d\s\-\/\(\)]{6,18})/u', $line, $phone_m)) {
                        $phone = trim(preg_replace('/\s+/', ' ', $phone_m[1]));
                        if (strlen(preg_replace('/\D/', '', $phone)) >= 8) {
                            $business_entries[$current_business_idx]['phone'] = $phone;
                        }
                    }
                }

                // Extract addresses
                if (empty($business_entries[$current_business_idx]['address'])) {
                    if (preg_match('/([A-ZÄÖÜa-zäöüß\-]+(?:str(?:aße|\.)?|weg|allee|platz|gasse|ring|damm|ufer)\s*\d+[a-z]?)/iu', $line, $addr_m)) {
                        $business_entries[$current_business_idx]['address'] = $addr_m[1];
                    }
                }

                // Extract email addresses
                if (empty($business_entries[$current_business_idx]['email'])) {
                    if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $line, $email_m)) {
                        $business_entries[$current_business_idx]['email'] = strtolower($email_m[1]);
                    }
                }
            }
        }

        // Extract business_names for compatibility
        $business_names = array_column($business_entries, 'name');

        // Fallback: Original pattern for different formats
        if (empty($business_names)) {
            preg_match_all('/([A-ZÄÖÜa-zäöüß][A-ZÄÖÜa-zäöüß0-9\s\-\&\.\,\']+(?:GmbH|UG|AG|e\.K\.|Ltd|Inc|Shop|Store|Service|Reparatur|Repair|Mobile|Phone|Handy|Tech|IT|Digital|Express|Center|Centre|Studio|Lab|Pro|Plus)?)\s*(?:\d[\d\,\.]*\s*(?:\(\d+\))?|Keine Rezensionen)/u', $content, $name_matches);

            if (!empty($name_matches[1])) {
                foreach ($name_matches[1] as $name) {
                    $name = trim($name);
                    if (strlen($name) >= 5 && strlen($name) <= 100 && self::is_valid_business_name($name)) {
                        $business_entries[] = [
                            'name' => $name,
                            'website' => '',
                            'phone' => '',
                            'address' => '',
                            'email' => ''
                        ];
                    }
                }
                $business_names = array_column($business_entries, 'name');
            }
        }

        // Also extract any full URLs and bare domains from entire content
        $extra_websites = [];

        // Full URLs
        preg_match_all('/(https?:\/\/(?:www\.)?[a-zA-Z0-9][a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}(?:\/[^\s<>"\'\)]*)?)/i', $content, $url_matches);
        if (!empty($url_matches[1])) {
            foreach ($url_matches[1] as $url) {
                $url = rtrim($url, '.,;:)');
                $domain = parse_url($url, PHP_URL_HOST);
                if ($domain && !preg_match('/(google|facebook|instagram|twitter|youtube|yelp|tripadvisor|wikipedia|amazon|ebay|linkedin|tiktok|pinterest)/i', $domain)) {
                    $extra_websites[$domain] = $url;
                }
            }
        }

        // Bare domains on their own line (common in Google Maps copy)
        preg_match_all('/(?:^|\n)\s*((?:www\.)?[a-zA-Z0-9][a-zA-Z0-9\-]+\.(?:de|com|net|org|eu|at|ch|info|shop|store|online|io)(?:\.[a-zA-Z]{2})?)\s*(?:\n|$)/im', $content, $bare_domain_matches);
        if (!empty($bare_domain_matches[1])) {
            foreach ($bare_domain_matches[1] as $domain) {
                $domain = trim($domain);
                $host = preg_replace('/^www\./', '', $domain);
                if (!isset($extra_websites[$host]) && !isset($extra_websites['www.' . $host])) {
                    $extra_websites[$host] = 'https://' . $domain;
                }
            }
        }

        // Phone numbers from entire content
        preg_match_all('/(?:Tel(?:efon)?\.?:?\s*)?(\+49|0049|0)\s*[\d\s\-\/\(\)]{6,20}/i', $content, $phone_matches);

        // Email addresses from entire content
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i', $content, $email_matches);

        // Addresses (German style)
        preg_match_all('/([A-ZÄÖÜa-zäöüß\-]+(?:str(?:aße|\.)?|weg|allee|platz|gasse|ring|damm)\s*\d+[a-z]?)\s*[\,\n]\s*(\d{5})\s+([A-ZÄÖÜa-zäöüß\-\s]+)/iu', $content, $address_matches, PREG_SET_ORDER);

        // Create leads from extracted business entries
        $extra_websites_array = array_values($extra_websites);

        foreach ($business_entries as $i => $entry) {
            $business = [
                'name' => $entry['name'],
                'website' => $entry['website'],
                'phone' => $entry['phone'],
                'email' => $entry['email'],
                'address' => $entry['address']
            ];

            // If no website from line-by-line parsing, try matching from extra_websites by position
            if (empty($business['website']) && isset($extra_websites_array[$i])) {
                $business['website'] = $extra_websites_array[$i];
            }

            // Try to match address from global address matches
            if (empty($business['address'])) {
                foreach ($address_matches as $addr) {
                    $pos_name = stripos($content, $entry['name']);
                    $pos_addr = stripos($content, $addr[0]);
                    if ($pos_name !== false && $pos_addr !== false && abs($pos_name - $pos_addr) < 500) {
                        $business['address'] = $addr[1] . ', ' . $addr[2] . ' ' . trim($addr[3]);
                        break;
                    }
                }
            }

            if (self::add_lead_if_new($business, $region, 'Google Maps Import', $keyword)) {
                $found_count++;
            }

            if ($found_count >= 100) break;
        }

        // Also add any websites we found but didn't match to a name
        if ($found_count < 50) {
            foreach ($extra_websites as $domain => $url) {
                $already_added = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ppv_leads WHERE website LIKE %s",
                    '%' . $wpdb->esc_like($domain) . '%'
                ));

                if (!$already_added) {
                    $name = preg_replace('/^www\./', '', $domain);
                    $name = preg_replace('/\.(de|com|net|org|eu|info|shop|store)$/i', '', $name);
                    $name = ucfirst($name);

                    $wpdb->insert(
                        $wpdb->prefix . 'ppv_leads',
                        [
                            'business_name' => $name,
                            'region' => $region,
                            'website' => $url,
                            'search_query' => 'Google Maps Import'
                        ],
                        ['%s', '%s', '%s', '%s']
                    );
                    $found_count++;

                    if ($found_count >= 100) break;
                }
            }
        }

        wp_redirect("/formular/lead-finder?success=import&found=$found_count&region=" . urlencode($region));
        exit;
    }

    /**
     * Add lead if it doesn't exist
     */
    private static function add_lead_if_new($business, $region, $query, $keyword = '') {
        global $wpdb;

        if (empty($business['name']) || strlen($business['name']) < 3) {
            return false;
        }

        // Clean the business name
        $business['name'] = self::clean_business_name($business['name']);

        // Validate again after cleaning
        if (strlen($business['name']) < 4 || !self::is_valid_business_name($business['name'])) {
            return false;
        }

        // Check if already exists
        $exists = false;
        if (!empty($business['website'])) {
            $domain = parse_url($business['website'], PHP_URL_HOST);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_leads WHERE website LIKE %s",
                '%' . $wpdb->esc_like($domain) . '%'
            ));
        }
        if (!$exists && !empty($business['name'])) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_leads WHERE business_name = %s AND region = %s",
                $business['name'], $region
            ));
        }

        if ($exists) {
            return false;
        }

        $wpdb->insert(
            $wpdb->prefix . 'ppv_leads',
            [
                'business_name' => $business['name'],
                'region' => $region,
                'website' => $business['website'] ?? '',
                'phone' => $business['phone'] ?? '',
                'address' => $business['address'] ?? '',
                'email' => $business['email'] ?? '',
                'keyword' => $keyword,
                'search_query' => $query
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return true;
    }

    /**
     * Check if a string is a valid business name (not an address, time, etc.)
     */
    private static function is_valid_business_name($name) {
        // Clean the name first - remove trailing commas and whitespace
        $name = rtrim(trim($name), ',;.');

        // Reject if too short
        if (strlen($name) < 4) {
            return false;
        }

        // Reject if starts with time/day patterns
        if (preg_match('/^(Öffnet|Öffnungszeiten|Geschlossen|Geöffnet|Schließt|Jetzt|Heute|Morgen)/iu', $name)) {
            return false;
        }

        // Reject day names at start
        if (preg_match('/^(Montag|Dienstag|Mittwoch|Donnerstag|Freitag|Samstag|Sonntag|Mo\s|Di\s|Mi\s|Do\s|Fr\s|Sa\s|So\s)/iu', $name)) {
            return false;
        }

        // Reject Google Maps UI elements
        if (preg_match('/^(Google|Maps|Routenplaner|Weitere|Ergebnisse|Bewertung|Website|Anrufen|Teilen|Speichern|Route|Wegbeschreibung|Fotos|Rezensionen|Übersicht|Info|Karte|Satellit|Gelände|Alle\s|Filter|Sortieren|Neuste|Neueste|Standort|Entfernung)/iu', $name)) {
            return false;
        }

        // Reject standalone street names (with or without number)
        // German street suffixes: straße, str, weg, allee, platz, gasse, ring, damm, ufer, hof, park, markt, chaussee, steig
        $street_suffixes = 'str(?:a(?:ß|ss)e|\.)?|weg|allee|platz|gasse|ring|damm|ufer|hof|park|markt|chaussee|steig|pfad|stieg|brücke|graben|grund|berg|tal';

        // Reject if the entire name IS a street name (e.g., "Schwabstraße", "Giescheweg", "Annonay-Straße")
        if (preg_match('/^[A-ZÄÖÜa-zäöüß\-]+(?:' . $street_suffixes . ')(?:\s*\d+[a-z]?)?\s*,?\s*$/iu', $name)) {
            return false;
        }

        // Reject "Word-Straße" patterns (e.g., "Annonay-Straße")
        if (preg_match('/^[A-ZÄÖÜa-zäöüß]+[\-\s](?:Straße|Strasse|Str\.|Weg|Allee|Platz|Gasse|Ring|Damm|Ufer)\s*\d*\s*,?\s*$/iu', $name)) {
            return false;
        }

        // Reject addresses - street name + number patterns (multiword streets)
        if (preg_match('/(?:' . $street_suffixes . ')\s+\d+/iu', $name)) {
            // But allow if it's clearly a business name containing a street
            // Business names typically have more than just street + number
            $without_address = preg_replace('/[A-ZÄÖÜa-zäöüß\-]+(?:' . $street_suffixes . ')\s*\d+[a-z]?\s*,?/iu', '', $name);
            if (strlen(trim($without_address)) < 5) {
                return false;
            }
        }

        // Reject if it looks like just "Word Number" or "Word Number," (address pattern)
        if (preg_match('/^[A-ZÄÖÜa-zäöüß\-]+\s+\d+[a-z]?\s*,?\s*$/iu', $name)) {
            return false;
        }

        // Reject short entries like "DHL 5," or similar
        if (preg_match('/^[A-Z]{2,5}\s+\d+\s*,?\s*$/i', $name)) {
            return false;
        }

        // Reject time patterns like "um 10:00" or "10:00 Uhr"
        if (preg_match('/um\s+\d{1,2}[:\.]?\d{0,2}|^\d{1,2}[:\.]?\d{0,2}\s*Uhr/iu', $name)) {
            return false;
        }

        // Reject PLZ + City patterns
        if (preg_match('/^\d{5}\s+[A-ZÄÖÜa-zäöüß]/u', $name)) {
            return false;
        }

        // Reject pure numbers or very short entries
        if (preg_match('/^\d+\s*,?\s*$/', $name)) {
            return false;
        }

        // Reject common non-business patterns
        if (preg_match('/^(Mehr|Weniger|Zurück|Vor|Weiter|Schließen|Abbrechen|OK|Ja|Nein|DHL|UPS|Hermes|DPD)\s*\d*\s*,?\s*$/iu', $name)) {
            return false;
        }

        // Reject if name ends with just a number+comma (likely address fragment leaked into name)
        // e.g., "Business Name 4," -> clean to "Business Name"
        // This is handled in clean_business_name() instead

        return true;
    }

    /**
     * Clean up a business name - remove address fragments, trailing numbers etc.
     */
    private static function clean_business_name($name) {
        // Remove trailing house number + comma: "Business Name 4," -> "Business Name"
        $name = preg_replace('/\s+\d+[a-z]?\s*,\s*$/', '', $name);
        // Remove trailing comma
        $name = rtrim(trim($name), ',;.');
        // Remove trailing " -" or "- "
        $name = preg_replace('/\s*-\s*$/', '', $name);
        return trim($name);
    }

    /**
     * Search Google Maps via web search
     */
    private static function search_google_maps($query, $region) {
        $results = [];

        // Search for Google Maps listings
        $search_url = "https://www.google.com/search?q=" . urlencode("$query $region site:google.com/maps") . "&num=30";

        $response = wp_remote_get($search_url, [
            'timeout' => 20,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);

        if (!is_wp_error($response)) {
            $html = wp_remote_retrieve_body($response);

            // Also do a regular search to find business websites
            $regular_url = "https://www.google.com/search?q=" . urlencode("$query $region") . "&num=50";
            $regular_response = wp_remote_get($regular_url, [
                'timeout' => 20,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ]);

            if (!is_wp_error($regular_response)) {
                $regular_html = wp_remote_retrieve_body($regular_response);

                // Extract URLs and titles
                preg_match_all('/<a[^>]+href="\/url\?q=([^&"]+)[^"]*"[^>]*>.*?<h3[^>]*>([^<]+)<\/h3>/is', $regular_html, $matches, PREG_SET_ORDER);

                if (empty($matches)) {
                    // Alternative pattern
                    preg_match_all('/href="(https?:\/\/(?!www\.google|webcache|translate\.google)[^"]+)"[^>]*>([^<]{5,100})</i', $regular_html, $alt_matches, PREG_SET_ORDER);
                    $matches = $alt_matches;
                }

                foreach ($matches as $match) {
                    $url = urldecode($match[1]);
                    $name = strip_tags(html_entity_decode($match[2]));

                    // Filter out non-business URLs
                    if (preg_match('/(google|youtube|facebook|instagram|twitter|linkedin|yelp|tripadvisor|wikipedia|amazon|ebay)/i', $url)) {
                        continue;
                    }

                    // Clean up name
                    $name = preg_replace('/\s*[-|–]\s*.*$/', '', $name); // Remove "- Site description" parts
                    $name = self::clean_business_name($name);

                    if (strlen($name) >= 5 && strlen($name) <= 150 && self::is_valid_business_name($name)) {
                        $results[] = [
                            'name' => $name,
                            'website' => $url
                        ];
                    }
                }
            }
        }

        return array_slice($results, 0, 30);
    }

    /**
     * Search Bing
     */
    private static function search_bing($query) {
        $results = [];

        $search_url = "https://www.bing.com/search?q=" . urlencode($query) . "&count=50";

        $response = wp_remote_get($search_url, [
            'timeout' => 20,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);

        if (!is_wp_error($response)) {
            $html = wp_remote_retrieve_body($response);

            // Extract Bing search results
            preg_match_all('/<a[^>]+href="(https?:\/\/[^"]+)"[^>]*><h2>([^<]+)<\/h2>/i', $html, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $url = $match[1];
                $name = strip_tags(html_entity_decode($match[2]));

                if (preg_match('/(bing|microsoft|google|youtube|facebook|instagram|twitter|wikipedia)/i', $url)) {
                    continue;
                }

                $name = preg_replace('/\s*[-|–]\s*.*$/', '', $name);
                $name = self::clean_business_name($name);

                if (strlen($name) >= 5 && strlen($name) <= 150 && self::is_valid_business_name($name)) {
                    $results[] = [
                        'name' => $name,
                        'website' => $url
                    ];
                }
            }
        }

        return array_slice($results, 0, 20);
    }

    /**
     * Search DuckDuckGo
     */
    private static function search_duckduckgo($query) {
        $results = [];

        $search_url = "https://html.duckduckgo.com/html/?q=" . urlencode($query);

        $response = wp_remote_get($search_url, [
            'timeout' => 20,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);

        if (!is_wp_error($response)) {
            $html = wp_remote_retrieve_body($response);

            // Extract DuckDuckGo results
            preg_match_all('/<a[^>]+class="result__a"[^>]+href="([^"]+)"[^>]*>([^<]+)</i', $html, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $url = $match[1];
                $name = strip_tags(html_entity_decode($match[2]));

                // Follow DuckDuckGo redirects
                if (preg_match('/uddg=([^&]+)/', $url, $uddg)) {
                    $url = urldecode($uddg[1]);
                }

                if (preg_match('/(duckduckgo|google|youtube|facebook|instagram|twitter|wikipedia)/i', $url)) {
                    continue;
                }

                $name = preg_replace('/\s*[-|–]\s*.*$/', '', $name);
                $name = self::clean_business_name($name);

                if (strlen($name) >= 5 && strlen($name) <= 150 && self::is_valid_business_name($name)) {
                    $results[] = [
                        'name' => $name,
                        'website' => $url
                    ];
                }
            }
        }

        return array_slice($results, 0, 20);
    }

    /**
     * Scrape a single website for contact info
     */
    private static function handle_scrape() {
        global $wpdb;

        $lead_id = intval($_POST['lead_id'] ?? 0);
        if (!$lead_id) {
            wp_redirect("/formular/lead-finder?error=invalid_lead");
            exit;
        }

        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_leads WHERE id = %d",
            $lead_id
        ));

        if (!$lead) {
            wp_redirect("/formular/lead-finder?error=invalid_lead");
            exit;
        }

        // Smart search: Tavily → website impressum/kontakt
        $keyword = isset($lead->keyword) ? $lead->keyword : '';
        $found = self::smart_find_email($lead->business_name, $lead->region, $keyword);

        $status = !empty($found['email']) ? 'email_found' : 'no_email_found';

        $update_data = [
            'email' => $found['email'],
            'phone' => $found['phone'] ?: $lead->phone,
            'scraped_at' => current_time('mysql'),
            'scrape_status' => $status
        ];
        if (!empty($found['website']) && empty($lead->website)) {
            $update_data['website'] = $found['website'];
        }

        $wpdb->update(
            $wpdb->prefix . 'ppv_leads',
            $update_data,
            ['id' => $lead_id]
        );

        wp_redirect("/formular/lead-finder?success=scraped&email=" . urlencode($found['email']));
        exit;
    }

    /**
     * AJAX scrape website
     */
    private static function ajax_scrape_website() {
        global $wpdb;

        header('Content-Type: application/json');

        $lead_id = intval($_GET['id'] ?? 0);
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_leads WHERE id = %d",
            $lead_id
        ));

        if (!$lead) {
            echo json_encode(['success' => false, 'error' => 'Invalid lead']);
            exit;
        }

        // Smart search: Tavily → website impressum/kontakt
        $keyword = isset($lead->keyword) ? $lead->keyword : '';
        $found = self::smart_find_email($lead->business_name, $lead->region, $keyword);

        $status = !empty($found['email']) ? 'email_found' : 'no_email_found';

        // Save results
        $update_data = [
            'email' => $found['email'],
            'phone' => $found['phone'] ?: $lead->phone,
            'address' => $lead->address,
            'scraped_at' => current_time('mysql'),
            'scrape_status' => $status
        ];
        if (!empty($found['website']) && empty($lead->website)) {
            $update_data['website'] = $found['website'];
        }

        $wpdb->update(
            $wpdb->prefix . 'ppv_leads',
            $update_data,
            ['id' => $lead_id]
        );

        echo json_encode([
            'success' => true,
            'email' => $found['email'],
            'phone' => $found['phone'] ?: $lead->phone ?: '',
            'website' => $found['website'] ?: $lead->website ?: '',
            'status' => $status
        ]);
        exit;
    }

    // Tavily API key
    private static $tavily_api_key = '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';

    /**
     * Tavily API debug test - visit /formular/lead-finder?tavily_test=1
     */
    private static function tavily_test() {
        header('Content-Type: application/json');

        $query = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : 'City-Phone24 Stuttgart email';

        $post_body = json_encode([
            'api_key' => self::$tavily_api_key,
            'query' => $query,
            'max_results' => 3,
            'search_depth' => 'basic',
            'include_answer' => false,
            'include_raw_content' => false,
        ]);

        $response = wp_remote_post('https://api.tavily.com/search', [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $post_body,
        ]);

        $debug = [
            'api_key_length' => strlen(self::$tavily_api_key),
            'api_key_start' => substr(self::$tavily_api_key, 0, 10) . '...',
            'query' => $query,
            'request_body' => $post_body,
        ];

        if (is_wp_error($response)) {
            $debug['error'] = $response->get_error_message();
            $debug['error_code'] = $response->get_error_code();
            echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $debug['http_code'] = wp_remote_retrieve_response_code($response);
        $debug['response_headers'] = wp_remote_retrieve_headers($response)->getAll();
        $body = wp_remote_retrieve_body($response);
        $debug['response_body'] = json_decode($body, true) ?: $body;

        // Also extract emails from results
        $data = json_decode($body, true);
        $found_emails = [];
        if (!empty($data['results'])) {
            foreach ($data['results'] as $r) {
                $content = ($r['content'] ?? '') . ' ' . ($r['title'] ?? '');
                preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i', $content, $m);
                if (!empty($m[0])) {
                    $found_emails = array_merge($found_emails, $m[0]);
                }
            }
        }
        $debug['found_emails'] = array_unique($found_emails);

        echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Search via Tavily API - returns array of results with 'url', 'title', 'content'
     */
    private static function tavily_search($query, $max_results = 5) {
        $response = wp_remote_post('https://api.tavily.com/search', [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'api_key' => self::$tavily_api_key,
                'query' => $query,
                'max_results' => $max_results,
                'search_depth' => 'basic',
                'include_answer' => false,
                'include_raw_content' => false,
            ]),
        ]);

        if (is_wp_error($response)) {
            error_log('Tavily API error: ' . $response->get_error_message());
            return [];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log('Tavily API HTTP ' . $code . ': ' . wp_remote_retrieve_body($response));
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['results'] ?? [];
    }

    /**
     * Smart email search using Tavily API
     * Step 1: Tavily search "[name] email" → extract email from result content
     * Step 2: If no email in results → visit the business website impressum/kontakt
     * Returns ['email' => '', 'website' => '', 'phone' => '']
     */
    private static function smart_find_email($business_name, $region = '', $keyword = '') {
        $result = ['email' => '', 'website' => '', 'phone' => ''];
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $excluded_domains = '/(google|facebook|instagram|twitter|youtube|yelp|tripadvisor|wikipedia|amazon|ebay|linkedin|tiktok|pinterest|bing|reddit|microsoft)/i';

        // Build search query: "[name] [keyword] [region] email"
        // Keyword (e.g. "Handyreparatur") makes the search more precise
        $parts = [$business_name];
        if (!empty($keyword)) $parts[] = $keyword;
        if (!empty($region)) $parts[] = $region;
        $parts[] = 'email';

        // --- STEP 1: Tavily search "[name] [keyword] [region] email" ---
        $query = implode(' ', $parts);
        $results = self::tavily_search($query, 5);

        foreach ($results as $r) {
            $content = ($r['content'] ?? '') . ' ' . ($r['title'] ?? '') . ' ' . ($r['url'] ?? '');

            // Extract emails from result content
            preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i', $content, $matches);
            if (!empty($matches[0])) {
                $valid = self::filter_emails($matches[0]);
                if (!empty($valid)) {
                    $result['email'] = strtolower(reset($valid));
                }
            }

            // Extract first relevant website URL
            if (empty($result['website']) && !empty($r['url'])) {
                $domain = parse_url($r['url'], PHP_URL_HOST);
                if ($domain && !preg_match($excluded_domains, $domain)) {
                    $result['website'] = $r['url'];
                }
            }
        }

        // If email found, we're done! (1 API call = 1 credit)
        if (!empty($result['email'])) {
            return $result;
        }

        // --- STEP 2: Tavily search "[name] [keyword] Kontakt Impressum" ---
        $parts2 = [$business_name];
        if (!empty($keyword)) $parts2[] = $keyword;
        if (!empty($region)) $parts2[] = $region;
        $parts2[] = 'Kontakt Impressum';
        $query2 = implode(' ', $parts2);
        $results2 = self::tavily_search($query2, 5);

        foreach ($results2 as $r) {
            $content = ($r['content'] ?? '') . ' ' . ($r['title'] ?? '');

            preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i', $content, $matches);
            if (!empty($matches[0])) {
                $valid = self::filter_emails($matches[0]);
                if (!empty($valid)) {
                    $result['email'] = strtolower(reset($valid));
                }
            }

            if (empty($result['website']) && !empty($r['url'])) {
                $domain = parse_url($r['url'], PHP_URL_HOST);
                if ($domain && !preg_match($excluded_domains, $domain)) {
                    $result['website'] = $r['url'];
                }
            }
        }

        if (!empty($result['email'])) {
            return $result;
        }

        // --- STEP 3: Visit website impressum/kontakt page directly ---
        if (!empty($result['website'])) {
            $found = self::scrape_contact_page_for_email($result['website'], $ua);
            if (!empty($found['email'])) {
                $result['email'] = $found['email'];
            }
            if (!empty($found['phone'])) {
                $result['phone'] = $found['phone'];
            }
        }

        return $result;
    }

    /**
     * Visit a website's contact/impressum page and extract email
     * Smart: first checks main page for email, then tries /impressum, /kontakt etc.
     */
    private static function scrape_contact_page_for_email($url, $ua) {
        $result = ['email' => '', 'phone' => ''];

        if (strpos($url, 'http') !== 0) {
            $url = 'https://' . $url;
        }

        $parsed = parse_url($url);
        $base_url = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        // First: quick check on main page
        $response = wp_remote_get($base_url, [
            'timeout' => 6, 'user-agent' => $ua, 'sslverify' => false, 'redirection' => 3
        ]);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400) {
            $html = wp_remote_retrieve_body($response);
            $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Check mailto links first (most reliable)
            preg_match_all('/mailto:([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/i', $html, $mailto);
            $all_emails = !empty($mailto[1]) ? $mailto[1] : [];

            // Then plain text emails
            preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i', $decoded, $plain);
            if (!empty($plain[0])) {
                $all_emails = array_merge($all_emails, $plain[0]);
            }

            // Check obfuscated: "info [at] domain [dot] de"
            preg_match_all('/([a-zA-Z0-9._%+-]+)\s*[\[\(]\s*(?:at|@|AT)\s*[\]\)]\s*([a-zA-Z0-9.-]+)\s*[\[\(]\s*(?:dot|punkt|\.)\s*[\]\)]\s*([a-zA-Z]{2,})/i', $decoded, $obf, PREG_SET_ORDER);
            foreach ($obf as $o) {
                $all_emails[] = $o[1] . '@' . $o[2] . '.' . $o[3];
            }

            $valid = self::filter_emails($all_emails);
            if (!empty($valid)) {
                $result['email'] = strtolower(reset($valid));
                return $result;
            }

            // Extract phone from main page
            preg_match_all('/(?:tel:|telefon|fon)?:?\s*((?:\+49|0049|0)\s*[\d\s\-\/\(\)]{6,18})/iu', $decoded, $phones);
            if (!empty($phones[1])) {
                $phone = trim(preg_replace('/\s+/', ' ', $phones[1][0]));
                if (strlen(preg_replace('/\D/', '', $phone)) >= 8) {
                    $result['phone'] = $phone;
                }
            }

            // Find contact/impressum links on the page
            $contact_paths = [];
            preg_match_all('/href=["\']([^"\']*(?:kontakt|contact|impressum|about|uber-uns|ueber-uns)[^"\']*)["\']/', strtolower($html), $link_matches);
            if (!empty($link_matches[1])) {
                $seen = [];
                foreach ($link_matches[1] as $path) {
                    if (strpos($path, 'javascript:') !== false || strpos($path, '#') !== false) continue;
                    if (strpos($path, 'http') !== 0) {
                        $path = (strpos($path, '/') === 0) ? $base_url . $path : $base_url . '/' . $path;
                    }
                    if (isset($seen[$path])) continue;
                    $seen[$path] = true;
                    $contact_paths[] = $path;
                }
            }

            // Try common paths if no links found
            if (empty($contact_paths)) {
                $contact_paths = [
                    $base_url . '/impressum',
                    $base_url . '/kontakt',
                ];
            }

            // Visit max 2 contact pages
            foreach (array_slice($contact_paths, 0, 2) as $contact_url) {
                $cr = wp_remote_get($contact_url, [
                    'timeout' => 5, 'user-agent' => $ua, 'sslverify' => false, 'redirection' => 3
                ]);

                if (!is_wp_error($cr) && wp_remote_retrieve_response_code($cr) < 400) {
                    $ch = wp_remote_retrieve_body($cr);
                    $cd = html_entity_decode($ch, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    // mailto first
                    preg_match_all('/mailto:([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/i', $ch, $cm);
                    $page_emails = !empty($cm[1]) ? $cm[1] : [];
                    preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i', $cd, $cp);
                    if (!empty($cp[0])) $page_emails = array_merge($page_emails, $cp[0]);

                    // Obfuscated
                    preg_match_all('/([a-zA-Z0-9._%+-]+)\s*[\[\(]\s*(?:at|@|AT)\s*[\]\)]\s*([a-zA-Z0-9.-]+)\s*[\[\(]\s*(?:dot|punkt|\.)\s*[\]\)]\s*([a-zA-Z]{2,})/i', $cd, $co, PREG_SET_ORDER);
                    foreach ($co as $o) $page_emails[] = $o[1] . '@' . $o[2] . '.' . $o[3];

                    $pv = self::filter_emails($page_emails);
                    if (!empty($pv)) {
                        $result['email'] = strtolower(reset($pv));
                        return $result;
                    }

                    // Phone fallback
                    if (empty($result['phone'])) {
                        preg_match_all('/(?:tel:|telefon|fon)?:?\s*((?:\+49|0049|0)\s*[\d\s\-\/\(\)]{6,18})/iu', $cd, $pp);
                        if (!empty($pp[1])) {
                            $phone = trim(preg_replace('/\s+/', ' ', $pp[1][0]));
                            if (strlen(preg_replace('/\D/', '', $phone)) >= 8) {
                                $result['phone'] = $phone;
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Find website for a business by searching the web
     */
    private static function find_website($business_name, $region = '') {
        // Method 1: Try domain guessing (most reliable - doesn't depend on search engines)
        $guessed = self::guess_website_domain($business_name);
        if (!empty($guessed)) {
            return $guessed;
        }

        $search_query = $business_name;
        if (!empty($region)) {
            $search_query .= ' ' . $region;
        }

        // Method 2: Try DuckDuckGo HTML search
        $ddg_result = self::search_engine_find_url("https://html.duckduckgo.com/html/?q=" . urlencode($search_query), 'duckduckgo');
        if (!empty($ddg_result)) return $ddg_result;

        // Method 3: Try Bing
        $bing_result = self::search_engine_find_url("https://www.bing.com/search?q=" . urlencode($search_query), 'bing');
        if (!empty($bing_result)) return $bing_result;

        // Method 4: Try Google
        $google_result = self::search_engine_find_url("https://www.google.com/search?q=" . urlencode($search_query) . "&num=5", 'google');
        if (!empty($google_result)) return $google_result;

        return '';
    }

    /**
     * Guess website domain from business name and check if it exists
     */
    private static function guess_website_domain($business_name) {
        // Step 0: If name contains a domain directly (like "Lauer-Repair.de"), use it
        if (preg_match('/\b([a-zA-Z0-9][\w\-]*\.(de|com|net|org|eu|shop|store|online))\b/i', $business_name, $domain_in_name)) {
            $direct_domain = strtolower($domain_in_name[1]);
            $url = 'https://' . $direct_domain;
            $response = wp_remote_head($url, ['timeout' => 5, 'sslverify' => false, 'redirection' => 3]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400) {
                return $url;
            }
        }

        // Step 1: Extract the actual business name (before description/location words)
        $name = $business_name;

        // Remove everything after common separator patterns: " - ", " / ", " | ", " für ", " in "
        $name = preg_replace('/\s+[-\/|]\s+.*/u', '', $name);
        // Remove description words that come AFTER the business name
        $name = preg_replace('/\s+(für|und|in|An\s*&|An\s+und|Reparatur\s+und|Service\s+für|Handy\s*reparatur|Smartphone|Tablet|iPhone|Samsung|Computer|Laptop|Reparatur|Service|Repair|Shop|Store)\b.*/iu', '', $name);
        // Remove legal forms
        $name = preg_replace('/\s+(GmbH|UG|AG|e\.K\.|Ltd|Inc|Co\.?|KG|OHG)\b.*/i', '', $name);
        // Remove city names at the end
        $name = preg_replace('/\s+(Stuttgart|Hamburg|München|Berlin|Köln|Frankfurt|Düsseldorf|Hannover|Leipzig|Dresden|Nürnberg|Bremen|Essen|Dortmund|Bochum|Wuppertal|Bielefeld|Bonn|Münster|Karlsruhe|Mannheim|Augsburg|Wiesbaden|Braunschweig|Kiel|Aachen|Freiburg|Erfurt|Mainz|Rostock|Kassel|Potsdam|Heidelberg|Darmstadt|Regensburg|Ingolstadt|Würzburg|Wolfsburg|Ulm|Heilbronn|Göttingen|Trier|Reutlingen|Koblenz|Winnenden|Schorndorf|Waiblingen|Backnang|Feuerbach|West|Ost|Nord|Süd|Mitte|Zentrum)\s*$/iu', '', $name);

        $name = trim($name);

        // If name is now too short, use original first part
        if (strlen($name) < 3) {
            $name = preg_replace('/\s+[-\/|]\s+.*/', '', $business_name);
            $name = trim($name);
        }

        // Step 2: Convert to domain-friendly slug
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[äÄ]/', 'ae', $slug);
        $slug = preg_replace('/[öÖ]/', 'oe', $slug);
        $slug = preg_replace('/[üÜ]/', 'ue', $slug);
        $slug = preg_replace('/ß/', 'ss', $slug);

        $slug_nospace = preg_replace('/[^a-z0-9]/', '', $slug);
        $slug_dashed = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug_dashed = trim($slug_dashed, '-');

        // Step 3: Generate domain candidates (ordered by likelihood)
        $candidates = [];

        // Only add if the slug is reasonable length for a domain (not too long, not too short)
        if (strlen($slug_nospace) >= 5 && strlen($slug_nospace) <= 30) {
            $candidates[] = $slug_nospace . '.de';
            $candidates[] = $slug_dashed . '.de';
        }
        if (strlen($slug_nospace) >= 5 && strlen($slug_nospace) <= 25) {
            $candidates[] = $slug_nospace . '.com';
            $candidates[] = $slug_dashed . '.com';
        }

        // Deduplicate
        $candidates = array_unique($candidates);

        // Step 4: Check each candidate with HTTP HEAD (short timeout for speed)
        foreach (array_slice($candidates, 0, 4) as $domain) {
            $url = 'https://' . $domain;
            $response = wp_remote_head($url, [
                'timeout' => 3,
                'sslverify' => false,
                'redirection' => 2
            ]);

            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                if ($code >= 200 && $code < 400) {
                    return $url;
                }
            }
        }

        return '';
    }

    /**
     * Search a search engine URL and extract the first relevant result URL
     */
    private static function search_engine_find_url($search_url, $engine) {
        $excluded = '/(google|facebook|instagram|twitter|youtube|yelp|tripadvisor|wikipedia|amazon|ebay|linkedin|tiktok|pinterest|bing|duckduckgo|reddit|microsoft|gstatic|googleapis)/i';

        $response = wp_remote_get($search_url, [
            'timeout' => 10,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return '';
        }

        $html = wp_remote_retrieve_body($response);

        // Collect all URLs found with various patterns
        $found_urls = [];

        if ($engine === 'duckduckgo') {
            // DuckDuckGo uddg redirect URLs
            preg_match_all('/uddg=([^&"]+)/i', $html, $m);
            foreach ($m[1] ?? [] as $u) $found_urls[] = urldecode($u);

            // result__url text
            preg_match_all('/class="result__url"[^>]*>([^<]+)/i', $html, $m);
            foreach ($m[1] ?? [] as $u) {
                $u = trim(strip_tags($u));
                if (strpos($u, 'http') !== 0) $u = 'https://' . $u;
                $found_urls[] = $u;
            }
        } elseif ($engine === 'bing') {
            preg_match_all('/<a[^>]+href="(https?:\/\/[^"]+)"[^>]*><h2/i', $html, $m);
            foreach ($m[1] ?? [] as $u) $found_urls[] = $u;
            preg_match_all('/<h2><a[^>]+href="(https?:\/\/[^"]+)"/i', $html, $m);
            foreach ($m[1] ?? [] as $u) $found_urls[] = $u;
            preg_match_all('/<cite[^>]*>(https?:\/\/[^<]+)<\/cite>/i', $html, $m);
            foreach ($m[1] ?? [] as $u) $found_urls[] = trim(strip_tags($u));
            preg_match_all('/<cite[^>]*>([^<]+\.[a-z]{2,}[^<]*)<\/cite>/i', $html, $m);
            foreach ($m[1] ?? [] as $u) {
                $u = trim(strip_tags($u));
                if (strpos($u, 'http') !== 0) $u = 'https://' . $u;
                $found_urls[] = $u;
            }
        } elseif ($engine === 'google') {
            preg_match_all('/\/url\?q=(https?:\/\/[^&"]+)/i', $html, $m);
            foreach ($m[1] ?? [] as $u) $found_urls[] = urldecode($u);
            preg_match_all('/href="(https?:\/\/(?!www\.google)[^"]+)"/i', $html, $m);
            foreach ($m[1] ?? [] as $u) $found_urls[] = $u;
        }

        // Also generic: find any https URLs in the HTML
        preg_match_all('/href="(https?:\/\/[^"]+)"/i', $html, $m);
        foreach ($m[1] ?? [] as $u) $found_urls[] = $u;

        // Filter and return first valid
        foreach ($found_urls as $url) {
            $url = rtrim($url, '.,;:)');
            $domain = parse_url($url, PHP_URL_HOST);
            if ($domain && !preg_match($excluded, $domain)) {
                return $url;
            }
        }

        return '';
    }

    /**
     * Scrape website for email and contact info
     */
    private static function scrape_website($url) {
        $result = [
            'email' => '',
            'phone' => '',
            'address' => '',
            'status' => 'scraped'
        ];

        // Ensure URL has protocol
        if (strpos($url, 'http') !== 0) {
            $url = 'https://' . $url;
        }

        // Fetch main page
        $response = wp_remote_get($url, [
            'timeout' => 8,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'sslverify' => false,
            'redirection' => 3
        ]);

        if (is_wp_error($response)) {
            // Try with http:// if https:// failed
            if (strpos($url, 'https://') === 0) {
                $url = str_replace('https://', 'http://', $url);
                $response = wp_remote_get($url, [
                    'timeout' => 8,
                    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'sslverify' => false,
                    'redirection' => 3
                ]);
            }
            if (is_wp_error($response)) {
                $result['status'] = 'error: ' . $response->get_error_message();
                return $result;
            }
        }

        $html = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 400) {
            $result['status'] = "error: HTTP $status_code";
            return $result;
        }

        // Decode HTML entities for better email extraction
        $decoded_html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Extract emails from multiple sources
        $all_emails = self::extract_emails_from_html($decoded_html);

        // Also check mailto: links specifically (most reliable)
        preg_match_all('/mailto:([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/i', $html, $mailto_matches);
        if (!empty($mailto_matches[1])) {
            $all_emails = array_merge($mailto_matches[1], $all_emails);
        }

        // Also check for obfuscated emails like "info [at] domain [dot] de"
        preg_match_all('/([a-zA-Z0-9._%+-]+)\s*[\[\(]\s*(?:at|@|AT)\s*[\]\)]\s*([a-zA-Z0-9.-]+)\s*[\[\(]\s*(?:dot|punkt|\.)\s*[\]\)]\s*([a-zA-Z]{2,})/i', $decoded_html, $obf_matches, PREG_SET_ORDER);
        foreach ($obf_matches as $obf) {
            $all_emails[] = $obf[1] . '@' . $obf[2] . '.' . $obf[3];
        }

        // Filter and pick the best email
        $valid_emails = self::filter_emails($all_emails);
        if (!empty($valid_emails)) {
            $result['email'] = strtolower(reset($valid_emails));
        }

        // Extract phone numbers (German format) from decoded HTML
        preg_match_all('/(?:tel:|phone:|telefon|fon|fax)?:?\s*((?:\+49|0049|0)\s*[\d\s\-\/\(\)]{6,18})/iu', $decoded_html, $phones);
        if (!empty($phones[1])) {
            $phone = trim(preg_replace('/\s+/', ' ', $phones[1][0]));
            if (strlen(preg_replace('/\D/', '', $phone)) >= 8) {
                $result['phone'] = $phone;
            }
        }

        // Try to find contact/impressum pages for more info
        if (empty($result['email'])) {
            $contact_urls = [];
            $parsed_url = parse_url($url);
            $base = ($parsed_url['scheme'] ?? 'https') . '://' . ($parsed_url['host'] ?? '');

            preg_match_all('/href=["\']([^"\']*(?:kontakt|contact|impressum|about|uber-uns|ueber-uns|datenschutz|legal|info)[^"\']*)["\']/', strtolower($html), $contact_matches);
            if (!empty($contact_matches[1])) {
                $seen = [];
                foreach ($contact_matches[1] as $contact_path) {
                    if (strpos($contact_path, 'http') !== 0) {
                        if (strpos($contact_path, '/') === 0) {
                            $contact_path = $base . $contact_path;
                        } else {
                            $contact_path = $base . '/' . $contact_path;
                        }
                    }
                    // Skip #anchors and javascript:
                    if (strpos($contact_path, '#') !== false || strpos($contact_path, 'javascript:') !== false) continue;
                    // Deduplicate
                    if (isset($seen[$contact_path])) continue;
                    $seen[$contact_path] = true;
                    $contact_urls[] = $contact_path;
                }
            }

            foreach (array_slice($contact_urls, 0, 2) as $contact_url) {
                $contact_response = wp_remote_get($contact_url, [
                    'timeout' => 6,
                    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'sslverify' => false,
                    'redirection' => 3
                ]);

                if (!is_wp_error($contact_response) && wp_remote_retrieve_response_code($contact_response) < 400) {
                    $contact_html = wp_remote_retrieve_body($contact_response);
                    $contact_decoded = html_entity_decode($contact_html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    // mailto: links first
                    preg_match_all('/mailto:([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/i', $contact_html, $mailto_m);
                    $page_emails = !empty($mailto_m[1]) ? $mailto_m[1] : [];
                    $page_emails = array_merge($page_emails, self::extract_emails_from_html($contact_decoded));

                    $valid = self::filter_emails($page_emails);
                    if (!empty($valid)) {
                        $result['email'] = strtolower(reset($valid));
                        break;
                    }

                    // Also grab phone if we don't have one
                    if (empty($result['phone'])) {
                        preg_match_all('/(?:tel:|phone:|telefon|fon|fax)?:?\s*((?:\+49|0049|0)\s*[\d\s\-\/\(\)]{6,18})/iu', $contact_decoded, $cp);
                        if (!empty($cp[1])) {
                            $phone = trim(preg_replace('/\s+/', ' ', $cp[1][0]));
                            if (strlen(preg_replace('/\D/', '', $phone)) >= 8) {
                                $result['phone'] = $phone;
                            }
                        }
                    }
                }

                usleep(300000); // 300ms delay between requests
            }
        }

        if (empty($result['email'])) {
            $result['status'] = 'no_email_found';
        }

        return $result;
    }

    /**
     * Extract emails from HTML content
     */
    private static function extract_emails_from_html($html) {
        $emails = [];

        // Standard email pattern
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i', $html, $matches);
        if (!empty($matches[0])) {
            $emails = array_merge($emails, $matches[0]);
        }

        return array_unique($emails);
    }

    /**
     * Filter out non-business emails
     */
    private static function filter_emails($emails) {
        return array_values(array_filter(array_unique($emails), function($email) {
            $email = strtolower(trim($email));
            // Filter out technical/non-business emails
            if (preg_match('/(example\.com|test\.|noreply|no-reply|wordpress|wix\.com|google\.com|facebook\.com|sentry\.io|schema\.org|jquery|bootstrap|cloudflare|w3\.org|gravatar|wp-content|placeholder|@sentry|@wix|@wordpress|\.png|\.jpg|\.gif|\.svg|protection@)/i', $email)) {
                return false;
            }
            // Reject emails that look like CSS/JS code artifacts
            if (strlen($email) > 60 || strlen($email) < 6) {
                return false;
            }
            // Must have a valid TLD
            if (!preg_match('/\.(de|com|net|org|eu|at|ch|info|biz|co|io|shop|store|online|site|app|dev|email|mail)$/i', $email)) {
                return false;
            }
            return true;
        }));
    }

    /**
     * Handle delete lead
     */
    private static function handle_delete() {
        global $wpdb;

        $lead_id = intval($_POST['lead_id'] ?? 0);
        if ($lead_id) {
            $wpdb->delete($wpdb->prefix . 'ppv_leads', ['id' => $lead_id], ['%d']);
        }

        wp_redirect("/formular/lead-finder?success=deleted");
        exit;
    }

    /**
     * Handle delete all leads
     */
    private static function handle_delete_all() {
        global $wpdb;

        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}ppv_leads");

        wp_redirect("/formular/lead-finder?success=deleted_all");
        exit;
    }

    /**
     * Handle delete selected leads
     */
    private static function handle_delete_selected() {
        global $wpdb;

        $ids = $_POST['selected_leads'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids);
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}ppv_leads WHERE id IN ($placeholders)",
                    ...$ids
                ));
            }
        }

        $count = count($ids);
        wp_redirect("/formular/lead-finder?success=deleted_selected&count=$count");
        exit;
    }

    /**
     * Handle export emails
     */
    private static function handle_export() {
        global $wpdb;

        $region = sanitize_text_field($_POST['export_region'] ?? '');

        $where = "email != ''";
        if (!empty($region)) {
            $where .= $wpdb->prepare(" AND region = %s", $region);
        }

        $emails = $wpdb->get_col("SELECT DISTINCT email FROM {$wpdb->prefix}ppv_leads WHERE $where");

        if (!empty($emails)) {
            $email_list = implode(', ', $emails);
            // Redirect to email sender with pre-filled emails
            wp_redirect("/formular/email-sender?to=" . urlencode($email_list));
            exit;
        }

        wp_redirect("/formular/lead-finder?error=no_emails");
        exit;
    }

    /**
     * Render HTML
     */
    private static function render_html($leads, $total_leads, $with_email, $not_scraped, $regions, $filter_region, $filter_status) {
        $success = $_GET['success'] ?? '';
        $error = $_GET['error'] ?? '';
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Finder - PunktePass</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; min-height: 100vh; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { font-size: 24px; font-weight: 700; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        h1 i { color: #667eea; }

        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: #fff; padding: 16px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-card .value { font-size: 28px; font-weight: 700; color: #1e293b; }
        .stat-card .label { font-size: 13px; color: #64748b; }
        .stat-card.success .value { color: #10b981; }
        .stat-card.warning .value { color: #f59e0b; }

        .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .card-body { padding: 20px; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr auto; gap: 12px; align-items: end; }
        .form-group { margin-bottom: 0; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }

        .btn { padding: 10px 18px; border: none; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
        .btn-primary { background: #667eea; color: #fff; }
        .btn-primary:hover { background: #5a67d8; }
        .btn-success { background: #10b981; color: #fff; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-icon { width: 32px; height: 32px; padding: 0; justify-content: center; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }

        .filters { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .filters select { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #475569; }
        tr:hover { background: #f8fafc; }

        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .badge-gray { background: #f1f5f9; color: #64748b; }

        .url { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .url a { color: #667eea; text-decoration: none; }
        .url a:hover { text-decoration: underline; }

        .actions { display: flex; gap: 6px; }

        .bulk-actions { display: flex; gap: 12px; align-items: center; padding: 12px 16px; background: #f8fafc; border-radius: 8px; margin-bottom: 16px; }

        .progress-bar { height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden; margin-top: 8px; }
        .progress-bar .fill { height: 100%; background: #667eea; transition: width 0.3s; }

        .back-link { display: inline-flex; align-items: center; gap: 6px; color: #667eea; text-decoration: none; margin-bottom: 16px; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <a href="/formular/email-sender" class="back-link"><i class="ri-arrow-left-line"></i> Zurück zum Email Sender</a>

        <h1><i class="ri-search-eye-line"></i> Lead Finder</h1>

        <?php if ($success === 'search'): ?>
            <div class="alert alert-success">
                <i class="ri-check-line"></i> Suche abgeschlossen! <?php echo intval($_GET['found'] ?? 0); ?> neue Leads gefunden.
            </div>
        <?php elseif ($success === 'import'): ?>
            <div class="alert alert-success">
                <i class="ri-check-line"></i> Import erfolgreich! <?php echo intval($_GET['found'] ?? 0); ?> Leads importiert. Klicke jetzt "Alle scrapen" um die Emails zu finden!
            </div>
        <?php elseif ($success === 'scraped'): ?>
            <div class="alert alert-success">
                <i class="ri-check-line"></i> Website gescraped! <?php echo $_GET['email'] ? 'Email gefunden: ' . esc_html($_GET['email']) : 'Keine Email gefunden.'; ?>
            </div>
        <?php elseif ($success === 'deleted'): ?>
            <div class="alert alert-success">
                <i class="ri-check-line"></i> Lead gelöscht.
            </div>
        <?php elseif ($success === 'deleted_all'): ?>
            <div class="alert alert-success">
                <i class="ri-check-line"></i> Alle Leads wurden gelöscht.
            </div>
        <?php elseif ($success === 'deleted_selected'): ?>
            <div class="alert alert-success">
                <i class="ri-check-line"></i> <?php echo intval($_GET['count'] ?? 0); ?> Leads gelöscht.
            </div>
        <?php endif; ?>

        <?php if ($error === 'empty_search'): ?>
            <div class="alert alert-error">
                <i class="ri-error-warning-line"></i> Bitte Suchbegriff und Region eingeben.
            </div>
        <?php elseif ($error === 'no_emails'): ?>
            <div class="alert alert-error">
                <i class="ri-error-warning-line"></i> Keine Emails zum Exportieren gefunden.
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats">
            <div class="stat-card">
                <div class="value"><?php echo $total_leads; ?></div>
                <div class="label">Leads gesamt</div>
            </div>
            <div class="stat-card success">
                <div class="value"><?php echo $with_email; ?></div>
                <div class="label">Mit Email</div>
            </div>
            <div class="stat-card warning">
                <div class="value"><?php echo $not_scraped; ?></div>
                <div class="label">Nicht gescraped</div>
            </div>
        </div>

        <!-- Manual Import (Google Maps) -->
        <div class="card">
            <div class="card-header"><i class="ri-map-pin-line"></i> Google Maps Import <span style="font-weight:normal;color:#10b981;font-size:12px;margin-left:8px">EMPFOHLEN</span></div>
            <div class="card-body">
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px;margin-bottom:16px">
                    <strong style="color:#065f46"><i class="ri-lightbulb-line"></i> So geht's:</strong>
                    <ol style="margin:10px 0 0 20px;color:#065f46;font-size:13px;line-height:1.8">
                        <li>Öffne <a href="https://www.google.com/maps/search/Handyreparatur+Hamburg" target="_blank" style="color:#059669;font-weight:600">Google Maps</a> und suche z.B. "Handyreparatur Hamburg"</li>
                        <li>Scrolle durch die Ergebnisse (mehr laden)</li>
                        <li>Drücke <kbd style="background:#fff;padding:2px 6px;border-radius:4px;border:1px solid #d1d5db">Ctrl+A</kbd> und dann <kbd style="background:#fff;padding:2px 6px;border-radius:4px;border:1px solid #d1d5db">Ctrl+C</kbd></li>
                        <li>Füge hier ein und klicke "Importieren"</li>
                    </ol>
                </div>
                <form method="post">
                    <div style="display:flex;gap:12px;margin-bottom:12px">
                        <div class="form-group" style="flex:1">
                            <label>Region / Stadt</label>
                            <input type="text" name="import_region" class="form-control" placeholder="z.B. Hamburg">
                        </div>
                        <div class="form-group" style="flex:1">
                            <label>Branche / Kulcsszó</label>
                            <input type="text" name="import_keyword" class="form-control" placeholder="z.B. Handyreparatur" value="Handyreparatur">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:12px">
                        <label>Google Maps Inhalt einf&uuml;gen</label>
                        <textarea name="import_content" class="form-control" rows="6" placeholder="Kopiere den gesamten Google Maps Inhalt hier hinein..."></textarea>
                    </div>
                    <button type="submit" name="manual_import" class="btn btn-success">
                        <i class="ri-download-line"></i> Importieren &amp; Websites extrahieren
                    </button>
                </form>
            </div>
        </div>

        <!-- Auto Search (may be blocked) -->
        <div class="card">
            <div class="card-header" style="cursor:pointer" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'block':'none'">
                <i class="ri-search-line"></i> Automatische Suche
                <span style="font-weight:normal;color:#f59e0b;font-size:11px;margin-left:8px">(kann blockiert werden)</span>
                <i class="ri-arrow-down-s-line" style="margin-left:auto"></i>
            </div>
            <div class="card-body" style="display:none">
                <form method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Suchbegriff (z.B. "Handyreparatur")</label>
                            <input type="text" name="search_query" class="form-control" value="Handyreparatur" placeholder="Handyreparatur">
                        </div>
                        <div class="form-group">
                            <label>Region / Stadt</label>
                            <input type="text" name="search_region" class="form-control" placeholder="z.B. Hamburg, München, Berlin">
                        </div>
                        <button type="submit" name="start_search" class="btn btn-primary">
                            <i class="ri-search-line"></i> Suchen
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Leads Table -->
        <div class="card">
            <div class="card-header"><i class="ri-list-check"></i> Gefundene Leads</div>
            <div class="card-body">
                <!-- Filters -->
                <div class="filters">
                    <form method="get" style="display:flex;gap:12px;flex-wrap:wrap">
                        <select name="region" onchange="this.form.submit()">
                            <option value="">Alle Regionen</option>
                            <?php foreach ($regions as $r): ?>
                                <option value="<?php echo esc_attr($r); ?>" <?php echo $filter_region === $r ? 'selected' : ''; ?>>
                                    <?php echo esc_html($r); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="status" onchange="this.form.submit()">
                            <option value="">Alle Status</option>
                            <option value="with_email" <?php echo $filter_status === 'with_email' ? 'selected' : ''; ?>>Mit Email</option>
                            <option value="no_email" <?php echo $filter_status === 'no_email' ? 'selected' : ''; ?>>Ohne Email</option>
                            <option value="not_scraped" <?php echo $filter_status === 'not_scraped' ? 'selected' : ''; ?>>Nicht gescraped</option>
                        </select>
                    </form>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions">
                    <button type="button" class="btn btn-primary btn-sm" onclick="scrapeAll()">
                        <i class="ri-robot-line"></i> Alle scrapen
                    </button>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="export_region" value="<?php echo esc_attr($filter_region); ?>">
                        <button type="submit" name="export_emails" class="btn btn-success btn-sm">
                            <i class="ri-mail-send-line"></i> Emails exportieren &rarr; Email Sender
                        </button>
                    </form>
                    <button type="button" class="btn btn-danger btn-sm" id="delete-selected-btn" style="display:none" onclick="deleteSelected()">
                        <i class="ri-delete-bin-line"></i> <span id="delete-selected-count">0</span> Ausgewählte löschen
                    </button>
                    <form method="post" style="display:inline" onsubmit="return confirm('ALLE <?php echo $total_leads; ?> Leads wirklich löschen? Dies kann nicht rückgängig gemacht werden!')">
                        <button type="submit" name="delete_all_leads" class="btn btn-danger btn-sm">
                            <i class="ri-delete-bin-line"></i> Alle löschen
                        </button>
                    </form>
                    <div id="scrape-progress" style="display:none;flex:1">
                        <span id="scrape-status">0 / 0</span>
                        <div class="progress-bar"><div class="fill" id="scrape-fill" style="width:0%"></div></div>
                    </div>
                </div>

                <form method="post" id="delete-selected-form">
                <table>
                    <thead>
                        <tr>
                            <th style="width:40px"><input type="checkbox" id="select-all" onclick="toggleSelectAll(this)" title="Alle auswählen"></th>
                            <th>Name</th>
                            <th>Region</th>
                            <th>Website</th>
                            <th>Email</th>
                            <th>Telefon</th>
                            <th>Status</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leads)): ?>
                            <tr><td colspan="8" style="text-align:center;color:#64748b;padding:40px">Keine Leads gefunden. Starte eine Suche!</td></tr>
                        <?php else: ?>
                            <?php foreach ($leads as $lead): ?>
                                <tr data-id="<?php echo $lead->id; ?>">
                                    <td><input type="checkbox" name="selected_leads[]" value="<?php echo $lead->id; ?>" class="lead-checkbox" onclick="updateSelectedCount()"></td>
                                    <td><strong><?php echo esc_html($lead->business_name); ?></strong></td>
                                    <td><?php echo esc_html($lead->region); ?></td>
                                    <td class="url">
                                        <?php if ($lead->website): ?>
                                            <a href="<?php echo esc_url($lead->website); ?>" target="_blank"><?php echo esc_html(parse_url($lead->website, PHP_URL_HOST)); ?></a>
                                        <?php else: ?>
                                            <span style="color:#94a3b8">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="lead-email">
                                        <?php if ($lead->email): ?>
                                            <span class="badge badge-success"><?php echo esc_html($lead->email); ?></span>
                                        <?php else: ?>
                                            <span style="color:#94a3b8">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($lead->phone ?: '-'); ?></td>
                                    <td class="lead-status">
                                        <?php if ($lead->scraped_at): ?>
                                            <?php if ($lead->email): ?>
                                                <span class="badge badge-success">OK</span>
                                            <?php elseif (strpos($lead->scrape_status, 'error') !== false): ?>
                                                <span class="badge badge-error" title="<?php echo esc_attr($lead->scrape_status); ?>">Error</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Keine Email</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-gray">Neu</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <?php if (!$lead->scraped_at): ?>
                                                <button type="button" class="btn btn-primary btn-sm btn-icon scrape-btn" onclick="scrapeSingle(<?php echo $lead->id; ?>, this)" title="<?php echo $lead->website ? 'Scrapen' : 'Website suchen & Scrapen'; ?>">
                                                    <i class="ri-robot-line"></i>
                                                </button>
                                            <?php endif; ?>
                                            <form method="post" style="display:inline" onsubmit="return confirm('Wirklich löschen?')">
                                                <input type="hidden" name="lead_id" value="<?php echo $lead->id; ?>">
                                                <button type="submit" name="delete_lead" class="btn btn-danger btn-sm btn-icon" title="Löschen">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <input type="hidden" name="delete_selected_leads" value="1">
                </form>
            </div>
        </div>
    </div>

    <script>
    function toggleSelectAll(masterCheckbox) {
        var checkboxes = document.querySelectorAll('.lead-checkbox');
        checkboxes.forEach(function(cb) { cb.checked = masterCheckbox.checked; });
        updateSelectedCount();
    }

    function updateSelectedCount() {
        var checked = document.querySelectorAll('.lead-checkbox:checked');
        var btn = document.getElementById('delete-selected-btn');
        var countEl = document.getElementById('delete-selected-count');
        if (checked.length > 0) {
            btn.style.display = 'inline-flex';
            countEl.textContent = checked.length;
        } else {
            btn.style.display = 'none';
        }
    }

    function deleteSelected() {
        var checked = document.querySelectorAll('.lead-checkbox:checked');
        if (checked.length === 0) return;
        if (!confirm(checked.length + ' Leads wirklich löschen?')) return;
        document.getElementById('delete-selected-form').submit();
    }
    </script>

    <script>
    function scrapeSingle(id, btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="ri-loader-4-line" style="animation:spin 1s linear infinite"></i>';

        return fetch('/formular/lead-finder?ajax_scrape=1&id=' + id)
            .then(r => r.json())
            .then(data => {
                var row = document.querySelector('tr[data-id="' + id + '"]');
                if (data.success) {
                    // Update website column if a website was found
                    if (data.website) {
                        var urlCell = row.querySelector('.url');
                        if (urlCell) {
                            try {
                                var hostname = new URL(data.website).hostname;
                                urlCell.innerHTML = '<a href="' + data.website + '" target="_blank">' + hostname + '</a>';
                            } catch(e) {}
                        }
                    }
                    if (data.email) {
                        row.querySelector('.lead-email').innerHTML = '<span class="badge badge-success">' + data.email + '</span>';
                        row.querySelector('.lead-status').innerHTML = '<span class="badge badge-success">OK</span>';
                    } else {
                        row.querySelector('.lead-status').innerHTML = '<span class="badge badge-warning">Keine Email</span>';
                    }
                    if (data.phone) {
                        var phoneCells = row.querySelectorAll('td');
                        if (phoneCells[4]) phoneCells[4].textContent = data.phone;
                    }
                } else {
                    row.querySelector('.lead-status').innerHTML = '<span class="badge badge-error">Error</span>';
                }
                btn.remove();
                return data;
            })
            .catch((err) => {
                btn.disabled = false;
                btn.innerHTML = '<i class="ri-robot-line"></i>';
                return {success: false, error: err.message};
            });
    }

    function scrapeAll() {
        var buttons = document.querySelectorAll('.scrape-btn');
        if (buttons.length === 0) {
            alert('Keine Leads zum Scrapen!');
            return;
        }

        if (!confirm('Alle ' + buttons.length + ' Leads scrapen? Das kann eine Weile dauern (Website-Suche + Scraping pro Lead).')) {
            return;
        }

        document.getElementById('scrape-progress').style.display = 'flex';
        var total = buttons.length;
        var current = 0;
        var emailsFound = 0;

        async function scrapeNext() {
            if (current >= total) {
                document.getElementById('scrape-status').textContent = 'Fertig! ' + emailsFound + ' Emails gefunden von ' + total + ' Leads';
                document.getElementById('scrape-fill').style.width = '100%';
                return;
            }

            var btn = buttons[current];
            var id = btn.closest('tr').dataset.id;

            document.getElementById('scrape-status').textContent = (current + 1) + ' / ' + total + ' (' + emailsFound + ' Emails)';
            document.getElementById('scrape-fill').style.width = ((current + 1) / total * 100) + '%';

            try {
                var data = await scrapeSingle(id, btn);
                if (data && data.email) emailsFound++;
            } catch(e) {}

            current++;
            // Small delay between requests to avoid overloading
            await new Promise(resolve => setTimeout(resolve, 500));
            scrapeNext();
        }

        scrapeNext();
    }
    </script>
    <style>
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>
</body>
</html>
        <?php
    }
}
