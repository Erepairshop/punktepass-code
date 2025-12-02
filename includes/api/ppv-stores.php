<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Stores REST API
 * Version: 2.6 Cached
 * ✅ POS-token kompatibilis
 * ✅ Lat/Lng detection optimalizálva
 * ✅ Fast open_now check (pre-fetched)
 * ✅ Transient cache for performance (5 min)
 */

class PPV_Stores_API {

    const CACHE_KEY = 'ppv_stores_list';
    const CACHE_TTL = 300; // 5 minutes

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        // Clear cache when stores are updated
        add_action('ppv_store_updated', [__CLASS__, 'clear_cache']);
        add_action('ppv_reward_updated', [__CLASS__, 'clear_cache']);
    }

    public static function register_routes() {
        register_rest_route('ppv/v1', '/stores/list', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_stores'],
            'permission_callback' => ['PPV_Permissions', 'allow_anonymous']
        ]);
    }

    /** Clear the stores cache */
    public static function clear_cache() {
        delete_transient(self::CACHE_KEY);
    }

    /** ============================================================
     * 🔹 Store lista lekérése távolsággal, rewarddal
     * ============================================================ */
    public static function get_stores($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        /** 🩵 LAT/LNG detection */
        $lat = floatval($request->get_param('lat') ?? ($_GET['lat'] ?? 0));
        $lng = floatval($request->get_param('lng') ?? ($_GET['lng'] ?? 0));

        /** 🔐 POS token check */
        $token = $request->get_header('ppv-pos-token') ?: ($_GET['pos_token'] ?? '');

        /** 🚀 Try to get from cache first (base store data without distance) */
        $cached_stores = get_transient(self::CACHE_KEY);

        if ($cached_stores === false) {
            /** 🧠 Bolt + reward + nyitvatartás lekérés */
            $query = "
                SELECT s.id, s.name, s.city, s.address, s.latitude, s.longitude,
                       s.logo, s.category, s.website, s.phone, s.email, s.description,
                       s.zeiten, s.active, s.visible,
                       MAX(r.title) AS reward_title, MAX(r.required_points) AS required_points
                FROM {$prefix}ppv_stores s
                LEFT JOIN {$prefix}ppv_rewards r ON r.store_id = s.id
                WHERE s.active = 1 AND s.visible = 1
                GROUP BY s.id
                ORDER BY s.name ASC
            ";

            $stores = $wpdb->get_results($query);
            if (!$stores) return rest_ensure_response([]);

            // Build cacheable base data
            $cached_stores = [];
            foreach ($stores as $s) {
                $logo = $s->logo ?: PPV_PLUGIN_URL . 'assets/img/store-default.png';
                $reward_text = ($s->reward_title)
                    ? $s->reward_title . ' – ' . intval($s->required_points) . ' Punkte'
                    : '';

                $cached_stores[] = [
                    'id'         => intval($s->id),
                    'name'       => $s->name,
                    'city'       => $s->city,
                    'address'    => $s->address,
                    'category'   => $s->category,
                    'reward'     => $reward_text,
                    'logo'       => esc_url($logo),
                    'latitude'   => floatval($s->latitude),
                    'longitude'  => floatval($s->longitude),
                    'zeiten'     => $s->zeiten,
                    'website'    => $s->website,
                    'phone'      => $s->phone,
                    'email'      => $s->email,
                    'description'=> $s->description
                ];
            }

            // Cache for 5 minutes
            set_transient(self::CACHE_KEY, $cached_stores, self::CACHE_TTL);
        }

        // Now add dynamic data (distance, open_now) to each store
        $results = [];
        foreach ($cached_stores as $store) {
            // Calculate distance if coordinates provided
            $distance_km = 0;
            if ($lat && $lng && $store['latitude'] && $store['longitude']) {
                $distance_km = self::haversine($lat, $lng, $store['latitude'], $store['longitude']);
            }

            // Check open_now (dynamic, not cached)
            $open_now = self::is_open_now_cached($store['zeiten']);

            $results[] = [
                'id'         => $store['id'],
                'name'       => $store['name'],
                'city'       => $store['city'],
                'address'    => $store['address'],
                'category'   => $store['category'],
                'reward'     => $store['reward'],
                'logo'       => $store['logo'],
                'latitude'   => $store['latitude'],
                'longitude'  => $store['longitude'],
                'distance_km'=> $distance_km ? max(0.1, round($distance_km, 1)) : 0,
                'open_now'   => $open_now,
                'website'    => $store['website'],
                'phone'      => $store['phone'],
                'email'      => $store['email'],
                'description'=> $store['description']
            ];
        }

        return rest_ensure_response($results);
    }

    /** ============================================================
     * 🔸 Distance calculation
     * ============================================================ */
    private static function haversine($lat1, $lon1, $lat2, $lon2) {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earth * $c;
    }

    /** ============================================================
     * 🔸 Open-now check (cached via zeiten JSON)
     * ============================================================ */
    private static function is_open_now_cached($zeiten_json) {
        if (!$zeiten_json) return false;
        $zeiten = json_decode($zeiten_json, true);
        if (!$zeiten || !is_array($zeiten)) return false;

        $weekday = strtolower((new DateTime('now', wp_timezone()))->format('D'));
        $map = ['mon'=>'mo','tue'=>'di','wed'=>'mi','thu'=>'do','fri'=>'fr','sat'=>'sa','sun'=>'so'];
        $tag_key = $map[$weekday] ?? 'mo';
        $today = $zeiten[$tag_key] ?? [];

        $now = (new DateTime('now', wp_timezone()))->format('H:i');
        $von = $today['von'] ?? '00:00';
        $bis = $today['bis'] ?? '23:59';
        $closed = !empty($today['closed']);

        if ($closed) return false;
        if ($von > $bis) return ($now >= $von || $now <= $bis);
        return ($now >= $von && $now <= $bis);
    }
}

PPV_Stores_API::hooks();
