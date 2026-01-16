<?php
/**
 * PunktePass – User Level System
 * Starter → Bronze → Silver → Gold → Platinum
 * Based on LIFETIME SCANS per STORE (number of QR scans at each store)
 * VIP bonuses start at Bronze (25+ scans at a specific store)
 * MyPoints shows highest VIP level across all stores
 * Author: PunktePass / Erik Borota
 */

if (!defined('ABSPATH')) exit;

class PPV_User_Level {

    // Level thresholds (lifetime scans per store - never decreases)
    // Starter = no VIP bonus, VIP starts at Bronze (25+ scans)
    // Each level adds +25 scans
    const LEVELS = [
        'starter'  => ['min' => 0,   'max' => 24,     'name_de' => 'Starter',  'name_hu' => 'Kezdő',    'name_ro' => 'Începător', 'vip' => false],
        'bronze'   => ['min' => 25,  'max' => 49,     'name_de' => 'Bronze',   'name_hu' => 'Bronz',    'name_ro' => 'Bronz',     'vip' => true],
        'silver'   => ['min' => 50,  'max' => 74,     'name_de' => 'Silber',   'name_hu' => 'Ezüst',    'name_ro' => 'Argint',    'vip' => true],
        'gold'     => ['min' => 75,  'max' => 99,     'name_de' => 'Gold',     'name_hu' => 'Arany',    'name_ro' => 'Aur',       'vip' => true],
        'platinum' => ['min' => 100, 'max' => 999999, 'name_de' => 'Platin',   'name_hu' => 'Platina',  'name_ro' => 'Platină',   'vip' => true],
    ];

    // Level icons (RemixIcon classes)
    const ICONS = [
        'starter'  => 'ri-user-line',
        'bronze'   => 'ri-medal-line',
        'silver'   => 'ri-medal-fill',
        'gold'     => 'ri-vip-crown-fill',
        'platinum' => 'ri-vip-diamond-fill',
    ];

    // Level colors
    const COLORS = [
        'starter'  => ['bg' => '#6c757d', 'text' => '#fff', 'glow' => 'rgba(108, 117, 125, 0.4)'],
        'bronze'   => ['bg' => '#CD7F32', 'text' => '#fff', 'glow' => 'rgba(205, 127, 50, 0.4)'],
        'silver'   => ['bg' => '#C0C0C0', 'text' => '#333', 'glow' => 'rgba(192, 192, 192, 0.5)'],
        'gold'     => ['bg' => '#FFD700', 'text' => '#333', 'glow' => 'rgba(255, 215, 0, 0.5)'],
        'platinum' => ['bg' => 'linear-gradient(135deg, #E5E4E2 0%, #A0B2C6 50%, #E5E4E2 100%)', 'text' => '#1a1a2e', 'glow' => 'rgba(160, 178, 198, 0.6)'],
    ];

    /**
     * Get user's LIFETIME SCANS at a specific store (or globally if no store specified)
     * This is used for VIP level calculation
     * Counts rows in ppv_points table where points > 0 (only positive point entries = scans)
     *
     * @param int $user_id User ID
     * @param int|null $store_id Store ID (null = global count across all stores)
     * @return int Number of scans
     */
    public static function get_total_scans($user_id, $store_id = null) {
        global $wpdb;

        if (!$user_id) return 0;

        if ($store_id) {
            // Per-store scan count (including parent store and filialen)
            $scan_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->prefix}ppv_points p
                 JOIN {$wpdb->prefix}ppv_stores s ON p.store_id = s.id
                 WHERE p.user_id = %d
                 AND p.points > 0
                 AND (s.id = %d OR s.parent_store_id = %d)",
                $user_id, $store_id, $store_id
            ));
        } else {
            // Global scan count (all stores)
            $scan_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->prefix}ppv_points
                 WHERE user_id = %d AND points > 0",
                $user_id
            ));
        }

        return intval($scan_count);
    }

    /**
     * Get user's scan count at a specific store (for VIP bonus calculation)
     * Includes parent store and all filialen
     */
    public static function get_scans_at_store($user_id, $store_id) {
        return self::get_total_scans($user_id, $store_id);
    }

    /**
     * @deprecated Use get_total_scans() instead
     * Kept for backward compatibility
     */
    public static function get_total_points($user_id) {
        return self::get_total_scans($user_id);
    }

    /**
     * Get user's CURRENT balance (spendable points, decreases on redemption)
     */
    public static function get_current_balance($user_id) {
        global $wpdb;

        if (!$user_id) return 0;

        $balance = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0)
             FROM {$wpdb->prefix}ppv_points
             WHERE user_id = %d",
            $user_id
        ));

        return intval($balance);
    }

    /**
     * Get user's level key (bronze/silver/gold/platinum) at a specific store
     * Based on scan count at that store (or globally if no store specified)
     *
     * @param int $user_id User ID
     * @param int|null $store_id Store ID (null = global/highest level)
     * @return string Level key
     */
    public static function get_level($user_id, $store_id = null) {
        $total_scans = self::get_total_scans($user_id, $store_id);

        foreach (self::LEVELS as $key => $level) {
            if ($total_scans >= $level['min'] && $total_scans <= $level['max']) {
                return $key;
            }
        }

        return 'starter';
    }

    /**
     * Get level from scan count (without user lookup)
     */
    public static function get_level_from_scans($scan_count) {
        foreach (self::LEVELS as $key => $level) {
            if ($scan_count >= $level['min'] && $scan_count <= $level['max']) {
                return $key;
            }
        }
        return 'starter';
    }

    /**
     * Get level name in specified language
     */
    public static function get_level_name($user_id, $lang = 'de', $store_id = null) {
        $level = self::get_level($user_id, $store_id);
        $name_key = "name_{$lang}";

        return self::LEVELS[$level][$name_key] ?? self::LEVELS[$level]['name_de'];
    }

    /**
     * Get level name from level key
     */
    public static function get_level_name_by_key($level_key, $lang = 'de') {
        $name_key = "name_{$lang}";
        return self::LEVELS[$level_key][$name_key] ?? self::LEVELS[$level_key]['name_de'] ?? 'Starter';
    }

    /**
     * Get level icon class
     */
    public static function get_level_icon($user_id, $store_id = null) {
        $level = self::get_level($user_id, $store_id);
        return self::ICONS[$level];
    }

    /**
     * Get level colors
     */
    public static function get_level_colors($user_id, $store_id = null) {
        $level = self::get_level($user_id, $store_id);
        return self::COLORS[$level];
    }

    /**
     * Get level numeric value (for comparisons)
     * starter=0, bronze=1, silver=2, gold=3, platinum=4
     */
    public static function get_level_value($level_key) {
        $values = ['starter' => 0, 'bronze' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4];
        return $values[$level_key] ?? 0;
    }

    /**
     * Check if user has VIP status at a specific store (Bronze or higher = 25+ scans)
     * Starter users do NOT get VIP bonuses
     *
     * @param int $user_id User ID
     * @param int|null $store_id Store ID (null = check global/any store)
     */
    public static function has_vip($user_id, $store_id = null) {
        $level = self::get_level($user_id, $store_id);
        return self::LEVELS[$level]['vip'] ?? false;
    }

    /**
     * Get VIP level for bonus calculation at a specific store (returns null for Starter)
     * Used by POS scan to determine bonus amounts
     * IMPORTANT: Always pass store_id for accurate per-store VIP calculation
     *
     * @param int $user_id User ID
     * @param int|null $store_id Store ID where the scan is happening
     */
    public static function get_vip_level_for_bonus($user_id, $store_id = null) {
        $total_scans = self::get_total_scans($user_id, $store_id);
        $level = self::get_level($user_id, $store_id);
        $has_vip = self::has_vip($user_id, $store_id);

        ppv_log("🔍 [PPV_User_Level] VIP check: user_id={$user_id}, store_id=" . ($store_id ?? 'GLOBAL') . ", total_scans={$total_scans}, level={$level}, has_vip=" . ($has_vip ? 'YES' : 'NO'));

        if (!$has_vip) {
            return null; // No VIP bonus for Starter
        }
        return $level;
    }

    /**
     * Get user's HIGHEST VIP level across all stores (for MyPoints display)
     * Returns level info with the store where they have the highest status
     *
     * @param int $user_id User ID
     * @param string $lang Language code
     * @return array ['level' => 'gold', 'store_id' => 123, 'store_name' => 'XY Store', 'scans' => 80]
     */
    public static function get_highest_vip_level($user_id, $lang = 'de') {
        global $wpdb;

        if (!$user_id) {
            return ['level' => 'starter', 'store_id' => null, 'store_name' => null, 'scans' => 0];
        }

        // Get scan counts per store (grouping by parent store)
        $store_scans = $wpdb->get_results($wpdb->prepare("
            SELECT
                COALESCE(s.parent_store_id, s.id) as store_id,
                COUNT(*) as scan_count,
                MAX(COALESCE(parent.name, s.name, parent.company_name, s.company_name)) as store_name
            FROM {$wpdb->prefix}ppv_points p
            JOIN {$wpdb->prefix}ppv_stores s ON p.store_id = s.id
            LEFT JOIN {$wpdb->prefix}ppv_stores parent ON s.parent_store_id = parent.id
            WHERE p.user_id = %d AND p.points > 0
            GROUP BY COALESCE(s.parent_store_id, s.id)
            ORDER BY scan_count DESC
            LIMIT 1
        ", $user_id));

        if (empty($store_scans)) {
            return ['level' => 'starter', 'store_id' => null, 'store_name' => null, 'scans' => 0];
        }

        $top_store = $store_scans[0];
        $level = self::get_level_from_scans($top_store->scan_count);

        return [
            'level' => $level,
            'level_name' => self::get_level_name_by_key($level, $lang),
            'store_id' => intval($top_store->store_id),
            'store_name' => $top_store->store_name,
            'scans' => intval($top_store->scan_count),
            'icon' => self::ICONS[$level],
            'colors' => self::COLORS[$level],
            'has_vip' => self::LEVELS[$level]['vip'] ?? false,
        ];
    }

    /**
     * Check if user meets minimum level requirement at a store
     */
    public static function meets_level($user_id, $min_level, $store_id = null) {
        if (empty($min_level)) return true; // No requirement

        $user_level = self::get_level($user_id, $store_id);
        return self::get_level_value($user_level) >= self::get_level_value($min_level);
    }

    /**
     * Get progress to next level (percentage)
     */
    public static function get_progress($user_id, $store_id = null) {
        $total_scans = self::get_total_scans($user_id, $store_id);
        $level = self::get_level($user_id, $store_id);

        if ($level === 'platinum') {
            return 100; // Max level
        }

        $current_min = self::LEVELS[$level]['min'];
        $current_max = self::LEVELS[$level]['max'];

        $progress = (($total_scans - $current_min) / ($current_max - $current_min + 1)) * 100;
        return min(100, max(0, round($progress)));
    }

    /**
     * Get scans needed for next level
     */
    public static function get_scans_to_next_level($user_id, $store_id = null) {
        $total_scans = self::get_total_scans($user_id, $store_id);
        $level = self::get_level($user_id, $store_id);

        if ($level === 'platinum') {
            return 0; // Already max
        }

        $next_level_min = self::LEVELS[$level]['max'] + 1;
        return max(0, $next_level_min - $total_scans);
    }

    /**
     * @deprecated Use get_scans_to_next_level() instead
     */
    public static function get_points_to_next_level($user_id) {
        return self::get_scans_to_next_level($user_id);
    }

    /**
     * Render level badge HTML
     */
    public static function render_badge($user_id, $size = 'small', $lang = 'de') {
        $level = self::get_level($user_id);
        $name = self::get_level_name($user_id, $lang);
        $icon = self::get_level_icon($user_id);
        $colors = self::get_level_colors($user_id);

        $size_class = $size === 'large' ? 'ppv-level-badge-lg' : 'ppv-level-badge-sm';

        $bg_style = strpos($colors['bg'], 'gradient') !== false
            ? "background: {$colors['bg']};"
            : "background-color: {$colors['bg']};";

        return sprintf(
            '<span class="ppv-level-badge %s ppv-level-%s" style="%s color: %s; --glow-color: %s;" title="%s">
                <i class="%s"></i>
                <span class="ppv-level-text">%s</span>
            </span>',
            esc_attr($size_class),
            esc_attr($level),
            esc_attr($bg_style),
            esc_attr($colors['text']),
            esc_attr($colors['glow']),
            esc_attr($name),
            esc_attr($icon),
            esc_html($name)
        );
    }

    /**
     * Render compact badge (icon only, for header)
     */
    public static function render_compact_badge($user_id, $lang = 'de') {
        $level = self::get_level($user_id);
        $name = self::get_level_name($user_id, $lang);
        $icon = self::get_level_icon($user_id);
        $colors = self::get_level_colors($user_id);

        $bg_style = strpos($colors['bg'], 'gradient') !== false
            ? "background: {$colors['bg']};"
            : "background-color: {$colors['bg']};";

        return sprintf(
            '<span class="ppv-level-badge-compact ppv-level-%s" style="%s color: %s; --glow-color: %s;" title="%s">
                <i class="%s"></i>
            </span>',
            esc_attr($level),
            esc_attr($bg_style),
            esc_attr($colors['text']),
            esc_attr($colors['glow']),
            esc_attr($name),
            esc_attr($icon)
        );
    }

    /**
     * Get all level info for a user (for API/JS - MyPoints display)
     * Shows the HIGHEST VIP level across all stores
     */
    public static function get_user_level_info($user_id, $lang = 'de') {
        // Get highest VIP level for display
        $highest = self::get_highest_vip_level($user_id, $lang);
        $level = $highest['level'];

        return [
            'level' => $level,
            'name' => $highest['level_name'] ?? self::get_level_name_by_key($level, $lang),
            'icon' => self::ICONS[$level],
            'colors' => self::COLORS[$level],
            'total_scans' => $highest['scans'],  // Scans at best store
            'lifetime_points' => $highest['scans'],  // @deprecated - kept for backward compatibility
            'current_balance' => self::get_current_balance($user_id), // Spendable balance
            'has_vip' => $highest['has_vip'],  // true if Bronze or higher
            'progress' => self::get_progress_from_scans($highest['scans'], $level),
            'scans_to_next' => self::get_scans_to_next_from_count($highest['scans'], $level),
            'points_to_next' => self::get_scans_to_next_from_count($highest['scans'], $level),  // @deprecated
            'level_value' => self::get_level_value($level),
            // New fields for per-store info
            'best_store_id' => $highest['store_id'],
            'best_store_name' => $highest['store_name'],
            'is_per_store' => true,  // Flag indicating VIP is per-store
        ];
    }

    /**
     * Get progress from scan count (without user lookup)
     */
    private static function get_progress_from_scans($scan_count, $level) {
        if ($level === 'platinum') {
            return 100;
        }

        $current_min = self::LEVELS[$level]['min'];
        $current_max = self::LEVELS[$level]['max'];

        $progress = (($scan_count - $current_min) / ($current_max - $current_min + 1)) * 100;
        return min(100, max(0, round($progress)));
    }

    /**
     * Get scans needed for next level from count
     */
    private static function get_scans_to_next_from_count($scan_count, $level) {
        if ($level === 'platinum') {
            return 0;
        }

        $next_level_min = self::LEVELS[$level]['max'] + 1;
        return max(0, $next_level_min - $scan_count);
    }

    /**
     * @deprecated No longer needed - VIP is now based on scan count from ppv_points table
     * Kept for backward compatibility but does nothing significant
     */
    public static function add_lifetime_points($user_id, $points) {
        global $wpdb;

        if (!$user_id || $points <= 0) return false;

        // Still update lifetime_points for backward compatibility (reports, etc.)
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ppv_users
             SET lifetime_points = COALESCE(lifetime_points, 0) + %d
             WHERE id = %d",
            $points,
            $user_id
        ));

        if ($result !== false) {
            ppv_log("✅ [PPV_User_Level] Added {$points} lifetime points to user {$user_id} (VIP now based on scan count)");
        }

        return $result !== false;
    }
}
