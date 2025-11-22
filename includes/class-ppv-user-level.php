<?php
/**
 * PunktePass – User Level System
 * Bronze → Silver → Gold → Platinum
 * Based on total points collected across all stores
 * Author: PunktePass / Erik Borota
 */

if (!defined('ABSPATH')) exit;

class PPV_User_Level {

    // Level thresholds (total points)
    const LEVELS = [
        'bronze'   => ['min' => 0,    'max' => 99,   'name_de' => 'Bronze',   'name_hu' => 'Bronz',    'name_ro' => 'Bronz'],
        'silver'   => ['min' => 100,  'max' => 499,  'name_de' => 'Silber',   'name_hu' => 'Ezüst',    'name_ro' => 'Argint'],
        'gold'     => ['min' => 500,  'max' => 999,  'name_de' => 'Gold',     'name_hu' => 'Arany',    'name_ro' => 'Aur'],
        'platinum' => ['min' => 1000, 'max' => 999999, 'name_de' => 'Platin', 'name_hu' => 'Platina',  'name_ro' => 'Platină'],
    ];

    // Level icons (RemixIcon classes)
    const ICONS = [
        'bronze'   => 'ri-medal-line',
        'silver'   => 'ri-medal-fill',
        'gold'     => 'ri-vip-crown-fill',
        'platinum' => 'ri-vip-diamond-fill',
    ];

    // Level colors
    const COLORS = [
        'bronze'   => ['bg' => '#CD7F32', 'text' => '#fff', 'glow' => 'rgba(205, 127, 50, 0.4)'],
        'silver'   => ['bg' => '#C0C0C0', 'text' => '#333', 'glow' => 'rgba(192, 192, 192, 0.5)'],
        'gold'     => ['bg' => '#FFD700', 'text' => '#333', 'glow' => 'rgba(255, 215, 0, 0.5)'],
        'platinum' => ['bg' => 'linear-gradient(135deg, #E5E4E2 0%, #A0B2C6 50%, #E5E4E2 100%)', 'text' => '#1a1a2e', 'glow' => 'rgba(160, 178, 198, 0.6)'],
    ];

    /**
     * Get user's total points across all stores
     */
    public static function get_total_points($user_id) {
        global $wpdb;

        if (!$user_id) return 0;

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0)
             FROM {$wpdb->prefix}ppv_points
             WHERE user_id = %d",
            $user_id
        ));

        return intval($total);
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
     * bronze=1, silver=2, gold=3, platinum=4
     */
    public static function get_level_value($level_key) {
        $values = ['bronze' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4];
        return $values[$level_key] ?? 1;
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
            'total_points' => self::get_total_points($user_id),
            'progress' => self::get_progress($user_id),
            'points_to_next' => self::get_points_to_next_level($user_id),
            'level_value' => self::get_level_value($level),
        ];
    }
}
