<?php
/**
 * PunktePass – User Level System
 * Starter → Bronze → Silver → Gold → Platinum
 * Based on LIFETIME points (never decreases, even after redemptions)
 * VIP bonuses start at Bronze (100+ points)
 * Author: PunktePass / Erik Borota
 */

if (!defined('ABSPATH')) exit;

class PPV_User_Level {

    // Level thresholds (lifetime points - never decreases)
    // Starter = no VIP bonus, VIP starts at Bronze (100+)
    const LEVELS = [
        'starter'  => ['min' => 0,    'max' => 99,     'name_de' => 'Starter',  'name_hu' => 'Kezdő',    'name_ro' => 'Începător', 'vip' => false],
        'bronze'   => ['min' => 100,  'max' => 499,    'name_de' => 'Bronze',   'name_hu' => 'Bronz',    'name_ro' => 'Bronz',     'vip' => true],
        'silver'   => ['min' => 500,  'max' => 999,    'name_de' => 'Silber',   'name_hu' => 'Ezüst',    'name_ro' => 'Argint',    'vip' => true],
        'gold'     => ['min' => 1000, 'max' => 1999,   'name_de' => 'Gold',     'name_hu' => 'Arany',    'name_ro' => 'Aur',       'vip' => true],
        'platinum' => ['min' => 2000, 'max' => 999999, 'name_de' => 'Platin',   'name_hu' => 'Platina',  'name_ro' => 'Platină',   'vip' => true],
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
     * Get user's LIFETIME points (never decreases, even after redemptions)
     * This is used for VIP level calculation
     */
    public static function get_total_points($user_id) {
        global $wpdb;

        if (!$user_id) return 0;

        // Get lifetime_points from ppv_users table
        $lifetime = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(lifetime_points, 0)
             FROM {$wpdb->prefix}ppv_users
             WHERE id = %d",
            $user_id
        ));

        return intval($lifetime);
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
     * Get user's level key (bronze/silver/gold/platinum)
     */
    public static function get_level($user_id) {
        $total = self::get_total_points($user_id);

        foreach (self::LEVELS as $key => $level) {
            if ($total >= $level['min'] && $total <= $level['max']) {
                return $key;
            }
        }

        return 'bronze';
    }

    /**
     * Get level name in specified language
     */
    public static function get_level_name($user_id, $lang = 'de') {
        $level = self::get_level($user_id);
        $name_key = "name_{$lang}";

        return self::LEVELS[$level][$name_key] ?? self::LEVELS[$level]['name_de'];
    }

    /**
     * Get level icon class
     */
    public static function get_level_icon($user_id) {
        $level = self::get_level($user_id);
        return self::ICONS[$level];
    }

    /**
     * Get level colors
     */
    public static function get_level_colors($user_id) {
        $level = self::get_level($user_id);
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
     * Check if user has VIP status (Bronze or higher = 100+ lifetime points)
     * Starter users (0-99 points) do NOT get VIP bonuses
     */
    public static function has_vip($user_id) {
        $level = self::get_level($user_id);
        return self::LEVELS[$level]['vip'] ?? false;
    }

    /**
     * Get VIP level for bonus calculation (returns null for Starter)
     * Used by POS scan to determine bonus amounts
     */
    public static function get_vip_level_for_bonus($user_id) {
        $lifetime = self::get_total_points($user_id);
        $level = self::get_level($user_id);
        $has_vip = self::has_vip($user_id);

        ppv_log("🔍 [PPV_User_Level] VIP check: user_id={$user_id}, lifetime_points={$lifetime}, level={$level}, has_vip=" . ($has_vip ? 'YES' : 'NO'));

        if (!$has_vip) {
            return null; // No VIP bonus for Starter
        }
        return $level;
    }

    /**
     * Check if user meets minimum level requirement
     */
    public static function meets_level($user_id, $min_level) {
        if (empty($min_level)) return true; // No requirement

        $user_level = self::get_level($user_id);
        return self::get_level_value($user_level) >= self::get_level_value($min_level);
    }

    /**
     * Get progress to next level (percentage)
     */
    public static function get_progress($user_id) {
        $total = self::get_total_points($user_id);
        $level = self::get_level($user_id);

        if ($level === 'platinum') {
            return 100; // Max level
        }

        $current_min = self::LEVELS[$level]['min'];
        $current_max = self::LEVELS[$level]['max'];

        $progress = (($total - $current_min) / ($current_max - $current_min + 1)) * 100;
        return min(100, max(0, round($progress)));
    }

    /**
     * Get points needed for next level
     */
    public static function get_points_to_next_level($user_id) {
        $total = self::get_total_points($user_id);
        $level = self::get_level($user_id);

        if ($level === 'platinum') {
            return 0; // Already max
        }

        $next_level_min = self::LEVELS[$level]['max'] + 1;
        return max(0, $next_level_min - $total);
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
     * Get all level info for a user (for API/JS)
     */
    public static function get_user_level_info($user_id, $lang = 'de') {
        $level = self::get_level($user_id);

        return [
            'level' => $level,
            'name' => self::get_level_name($user_id, $lang),
            'icon' => self::ICONS[$level],
            'colors' => self::COLORS[$level],
            'lifetime_points' => self::get_total_points($user_id),  // Lifetime (never decreases)
            'current_balance' => self::get_current_balance($user_id), // Spendable balance
            'has_vip' => self::has_vip($user_id),  // true if Bronze or higher
            'progress' => self::get_progress($user_id),
            'points_to_next' => self::get_points_to_next_level($user_id),
            'level_value' => self::get_level_value($level),
        ];
    }

    /**
     * Increment user's lifetime points (called when points are ADDED, not redeemed)
     */
    public static function add_lifetime_points($user_id, $points) {
        global $wpdb;

        if (!$user_id || $points <= 0) return false;

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ppv_users
             SET lifetime_points = COALESCE(lifetime_points, 0) + %d
             WHERE id = %d",
            $points,
            $user_id
        ));

        if ($result !== false) {
            ppv_log("✅ [PPV_User_Level] Added {$points} lifetime points to user {$user_id}");
        }

        return $result !== false;
    }
}
