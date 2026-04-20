<?php
/**
 * PunktePass – Store Setting Helper
 * ==================================
 * Egyseges API a ppv_stores.custom_settings JSON oszlop olvasasara/irasara.
 * Per-bolt override-ok gyors lekerdezesehez, cache-elt kulcs-ertek formaban.
 *
 * Felhasznalas:
 *   // Olvasas (default-tal):
 *   $cooldown = PPV_Store_Setting::get($store_id, 'scan_cooldown_sec', 0);
 *
 *   // Irasa (JSON merge, nem felulirja a tobbi kulcsot):
 *   PPV_Store_Setting::set($store_id, 'scan_cooldown_sec', 600);
 *
 *   // Teljes settings array:
 *   $all = PPV_Store_Setting::get_all($store_id);
 *
 *   // Torles (kulcs eltavolitasa):
 *   PPV_Store_Setting::remove($store_id, 'scan_cooldown_sec');
 *
 * A custom_settings kulcsok szabadon valaszthatoak; a jelenleg hasznalt kulcsok:
 *   - scan_cooldown_sec (int): azonos user ket scan-je kozott minimum masodperc
 *     (DEFAULT 0 = nincs cooldown, a beepitett 5mp duplicate-guard ervenyes csak)
 *   - (jovoben bovul: daily_scan_limit, max_points_per_scan override, stb.)
 *
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

class PPV_Store_Setting {

    /** In-request cache: [store_id => decoded_settings_array] */
    private static $cache = [];

    /**
     * Egy kulcs erteket olvas. Ha nincs ilyen kulcs vagy a settings JSON hibas,
     * a $default-ot adja vissza.
     */
    public static function get($store_id, $key, $default = null) {
        $store_id = intval($store_id);
        if ($store_id <= 0) return $default;

        $settings = self::get_all($store_id);
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * Teljes decoded settings array-t adja vissza. Uresan ures tomb ([]),
     * soha nem null — szamithatsz a foreach-re nelkul isset nelkul.
     */
    public static function get_all($store_id) {
        $store_id = intval($store_id);
        if ($store_id <= 0) return [];

        if (array_key_exists($store_id, self::$cache)) {
            return self::$cache[$store_id];
        }

        global $wpdb;
        $raw = $wpdb->get_var($wpdb->prepare(
            "SELECT custom_settings FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        $decoded = [];
        if ($raw) {
            $tmp = json_decode($raw, true);
            if (is_array($tmp)) $decoded = $tmp;
        }
        self::$cache[$store_id] = $decoded;
        return $decoded;
    }

    /**
     * Kulcs-ertek parosat ment (JSON merge). A tobbi kulcsot hagyja.
     * Visszater: true = mentve, false = hiba.
     */
    public static function set($store_id, $key, $value) {
        $store_id = intval($store_id);
        if ($store_id <= 0 || !is_string($key) || $key === '') return false;

        global $wpdb;
        $current = self::get_all($store_id);
        $current[$key] = $value;

        $result = $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            ['custom_settings' => wp_json_encode($current)],
            ['id' => $store_id]
        );

        // In-request cache invalidacio, hogy azonnal ervenyben legyen
        self::$cache[$store_id] = $current;
        return $result !== false;
    }

    /**
     * Egy kulcs tavolitasa. Ha a kulcs nem letezett, no-op.
     */
    public static function remove($store_id, $key) {
        $store_id = intval($store_id);
        if ($store_id <= 0 || !is_string($key) || $key === '') return false;

        $current = self::get_all($store_id);
        if (!array_key_exists($key, $current)) return true; // nothing to do

        unset($current[$key]);

        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            ['custom_settings' => $current ? wp_json_encode($current) : null],
            ['id' => $store_id]
        );

        self::$cache[$store_id] = $current;
        return $result !== false;
    }

    /**
     * Cache ureskitese — teszteleshez vagy ha egy masik process modositott.
     * Nem szokott kelleni normalis kontextusban (a set/remove frissiti).
     */
    public static function flush_cache($store_id = null) {
        if ($store_id === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[intval($store_id)]);
        }
    }
}
