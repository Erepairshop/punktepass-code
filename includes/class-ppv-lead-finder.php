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
            } elseif (isset($_POST['scrape_website'])) {
                self::handle_scrape();
            } elseif (isset($_POST['delete_lead'])) {
                self::handle_delete();
            } elseif (isset($_POST['export_emails'])) {
                self::handle_export();
            }
        }

        // Handle AJAX scrape
        if (isset($_GET['ajax_scrape']) && isset($_GET['id'])) {
            self::ajax_scrape_website();
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
     * Add lead if it doesn't exist
     */
    private static function add_lead_if_new($business, $region, $query) {
        global $wpdb;

        if (empty($business['name']) || strlen($business['name']) < 3) {
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
                'search_query' => $query
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return true;
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
                    $name = trim($name);

                    if (strlen($name) >= 5 && strlen($name) <= 150) {
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
                $name = trim($name);

                if (strlen($name) >= 5 && strlen($name) <= 150) {
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

                if (preg_match('/(duckduckgo|google|youtube|facebook|instagram|twitter|wikipedia)/i', $url)) {
                    continue;
                }

                $name = preg_replace('/\s*[-|–]\s*.*$/', '', $name);
                $name = trim($name);

                if (strlen($name) >= 5 && strlen($name) <= 150) {
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

        if (!$lead || empty($lead->website)) {
            wp_redirect("/formular/lead-finder?error=no_website");
            exit;
        }

        $result = self::scrape_website($lead->website);

        $wpdb->update(
            $wpdb->prefix . 'ppv_leads',
            [
                'email' => $result['email'] ?? '',
                'phone' => $result['phone'] ?: $lead->phone,
                'address' => $result['address'] ?: $lead->address,
                'scraped_at' => current_time('mysql'),
                'scrape_status' => $result['status']
            ],
            ['id' => $lead_id],
            ['%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        wp_redirect("/formular/lead-finder?success=scraped&email=" . urlencode($result['email'] ?? ''));
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

        if (!$lead || empty($lead->website)) {
            echo json_encode(['success' => false, 'error' => 'Invalid lead']);
            exit;
        }

        $result = self::scrape_website($lead->website);

        $wpdb->update(
            $wpdb->prefix . 'ppv_leads',
            [
                'email' => $result['email'] ?? '',
                'phone' => $result['phone'] ?: $lead->phone,
                'address' => $result['address'] ?: $lead->address,
                'scraped_at' => current_time('mysql'),
                'scrape_status' => $result['status']
            ],
            ['id' => $lead_id],
            ['%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        echo json_encode([
            'success' => true,
            'email' => $result['email'] ?? '',
            'phone' => $result['phone'] ?? '',
            'status' => $result['status']
        ]);
        exit;
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

        // Fetch main page
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            $result['status'] = 'error: ' . $response->get_error_message();
            return $result;
        }

        $html = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            $result['status'] = "error: HTTP $status_code";
            return $result;
        }

        // Extract emails
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i', $html, $emails);
        if (!empty($emails[0])) {
            // Filter out common non-business emails
            $valid_emails = array_filter($emails[0], function($email) {
                $email = strtolower($email);
                return !preg_match('/(example|test|noreply|no-reply|wordpress|wix|google|facebook|sentry|schema)/i', $email);
            });
            if (!empty($valid_emails)) {
                $result['email'] = strtolower(reset($valid_emails));
            }
        }

        // Try to find contact/impressum page for more info
        if (empty($result['email'])) {
            $contact_urls = [];
            preg_match_all('/href=["\']([^"\']*(?:kontakt|contact|impressum|about|uber-uns)[^"\']*)["\']/', $html, $contact_matches);
            if (!empty($contact_matches[1])) {
                foreach (array_slice($contact_matches[1], 0, 3) as $contact_path) {
                    if (strpos($contact_path, 'http') !== 0) {
                        $parsed = parse_url($url);
                        $base = $parsed['scheme'] . '://' . $parsed['host'];
                        $contact_path = $base . '/' . ltrim($contact_path, '/');
                    }
                    $contact_urls[] = $contact_path;
                }
            }

            foreach ($contact_urls as $contact_url) {
                $contact_response = wp_remote_get($contact_url, [
                    'timeout' => 10,
                    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'sslverify' => false
                ]);

                if (!is_wp_error($contact_response)) {
                    $contact_html = wp_remote_retrieve_body($contact_response);
                    preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i', $contact_html, $contact_emails);
                    if (!empty($contact_emails[0])) {
                        $valid = array_filter($contact_emails[0], function($email) {
                            return !preg_match('/(example|test|noreply|no-reply|wordpress|wix|google|facebook|sentry|schema)/i', strtolower($email));
                        });
                        if (!empty($valid)) {
                            $result['email'] = strtolower(reset($valid));
                            break;
                        }
                    }
                }

                usleep(500000); // 500ms delay between requests
            }
        }

        // Extract phone numbers (German format)
        preg_match_all('/(?:\+49|0049|0)\s*[\d\s\-\/]{8,15}/', $html, $phones);
        if (!empty($phones[0])) {
            $result['phone'] = trim(preg_replace('/\s+/', ' ', $phones[0][0]));
        }

        if (empty($result['email'])) {
            $result['status'] = 'no_email_found';
        }

        return $result;
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
        <?php elseif ($success === 'scraped'): ?>
            <div class="alert alert-success">
                <i class="ri-check-line"></i> Website gescraped! <?php echo $_GET['email'] ? 'Email gefunden: ' . esc_html($_GET['email']) : 'Keine Email gefunden.'; ?>
            </div>
        <?php elseif ($success === 'deleted'): ?>
            <div class="alert alert-success">
                <i class="ri-check-line"></i> Lead gelöscht.
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

        <!-- Search Form -->
        <div class="card">
            <div class="card-header"><i class="ri-search-line"></i> Neue Suche</div>
            <div class="card-body">
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
                            <i class="ri-mail-send-line"></i> Emails exportieren → Email Sender
                        </button>
                    </form>
                    <div id="scrape-progress" style="display:none;flex:1">
                        <span id="scrape-status">0 / 0</span>
                        <div class="progress-bar"><div class="fill" id="scrape-fill" style="width:0%"></div></div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
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
                            <tr><td colspan="7" style="text-align:center;color:#64748b;padding:40px">Keine Leads gefunden. Starte eine Suche!</td></tr>
                        <?php else: ?>
                            <?php foreach ($leads as $lead): ?>
                                <tr data-id="<?php echo $lead->id; ?>">
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
                                            <?php if ($lead->website && !$lead->scraped_at): ?>
                                                <button type="button" class="btn btn-primary btn-sm btn-icon scrape-btn" onclick="scrapeSingle(<?php echo $lead->id; ?>, this)" title="Scrapen">
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
            </div>
        </div>
    </div>

    <script>
    function scrapeSingle(id, btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="ri-loader-4-line" style="animation:spin 1s linear infinite"></i>';

        fetch('/formular/lead-finder?ajax_scrape=1&id=' + id)
            .then(r => r.json())
            .then(data => {
                var row = document.querySelector('tr[data-id="' + id + '"]');
                if (data.success) {
                    if (data.email) {
                        row.querySelector('.lead-email').innerHTML = '<span class="badge badge-success">' + data.email + '</span>';
                        row.querySelector('.lead-status').innerHTML = '<span class="badge badge-success">OK</span>';
                    } else {
                        row.querySelector('.lead-status').innerHTML = '<span class="badge badge-warning">Keine Email</span>';
                    }
                } else {
                    row.querySelector('.lead-status').innerHTML = '<span class="badge badge-error">Error</span>';
                }
                btn.remove();
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="ri-robot-line"></i>';
            });
    }

    function scrapeAll() {
        var buttons = document.querySelectorAll('.scrape-btn');
        if (buttons.length === 0) {
            alert('Keine Leads zum Scrapen!');
            return;
        }

        if (!confirm('Alle ' + buttons.length + ' Leads scrapen? Das kann eine Weile dauern.')) {
            return;
        }

        document.getElementById('scrape-progress').style.display = 'flex';
        var total = buttons.length;
        var current = 0;

        function scrapeNext() {
            if (current >= buttons.length) {
                document.getElementById('scrape-status').textContent = 'Fertig! ' + total + ' gescraped';
                return;
            }

            var btn = buttons[current];
            var id = btn.closest('tr').dataset.id;

            document.getElementById('scrape-status').textContent = (current + 1) + ' / ' + total;
            document.getElementById('scrape-fill').style.width = ((current + 1) / total * 100) + '%';

            btn.click();

            current++;
            setTimeout(scrapeNext, 1500); // 1.5s delay between requests
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
