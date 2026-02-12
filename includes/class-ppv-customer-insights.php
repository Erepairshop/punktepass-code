<?php
/**
 * PunktePass - Customer Insights for Händler
 *
 * Generates automatic customer insights shown to store owners after scan.
 * Helps build better customer relationships with visit patterns & preferences.
 *
 * @author Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Customer_Insights {

    // Minimum visits required for pattern analysis
    const MIN_VISITS_FOR_PATTERN = 4;

    // Days threshold for comeback detection
    const COMEBACK_DAYS_THRESHOLD = 30;

    // Day names (index 0 = Sunday in MySQL DAYOFWEEK - 1)
    private static $day_names = [
        'de' => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        'hu' => ['V', 'H', 'K', 'Sze', 'Cs', 'P', 'Szo'],
        'ro' => ['Du', 'Lu', 'Ma', 'Mi', 'Jo', 'Vi', 'Sâ']
    ];

    /**
     * Get customer insights for a specific user at a store
     */
    public static function get_insights($user_id, $store_id, $lang = 'de') {
        global $wpdb;

        if (!in_array($lang, ['de', 'hu', 'ro', 'en'])) {
            $lang = 'de';
        }

        // Get parent store ID if this is a filiale
        $parent_store_id = $store_id;
        if (class_exists('PPV_Filiale')) {
            $parent_store_id = PPV_Filiale::get_parent_id($store_id);
        }

        // Get all store IDs (parent + filialen)
        $store_ids = [$parent_store_id];
        $filialen = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE parent_store_id = %d",
            $parent_store_id
        ));
        if (!empty($filialen)) {
            $store_ids = array_merge($store_ids, $filialen);
        }
        $store_ids_str = implode(',', array_map('intval', $store_ids));

        // Get visit history
        $visits = $wpdb->get_results($wpdb->prepare("
            SELECT created, DAYOFWEEK(created) as dow, HOUR(created) as hour
            FROM {$wpdb->prefix}ppv_points
            WHERE user_id = %d AND store_id IN ({$store_ids_str}) AND type = 'qr_scan'
            ORDER BY created ASC
        ", $user_id));

        $visit_count = count($visits);

        // Get user info
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT lifetime_points, birthday FROM {$wpdb->prefix}ppv_users WHERE id = %d",
            $user_id
        ));

        // Current points balance
        $current_points = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM {$wpdb->prefix}ppv_points WHERE user_id = %d",
            $user_id
        ));

        // Next reward
        $next_reward = $wpdb->get_row($wpdb->prepare(
            "SELECT title, required_points FROM {$wpdb->prefix}ppv_rewards
             WHERE store_id = %d AND required_points > %d AND required_points > 0
             ORDER BY required_points ASC LIMIT 1",
            $parent_store_id, $current_points
        ));
        $points_to_reward = $next_reward ? ($next_reward->required_points - $current_points) : null;

        // VIP level
        $vip_level = self::get_vip_level($user->lifetime_points ?? 0);
        $vip_label = self::get_vip_label($vip_level, $lang);

        // Birthday check
        $is_birthday = false;
        if (!empty($user->birthday)) {
            $is_birthday = (date('m-d') === date('m-d', strtotime($user->birthday)));
        }

        // Days since last visit
        $days_since_last = null;
        if ($visit_count > 0) {
            $last = end($visits);
            $days_since_last = (int) floor((time() - strtotime($last->created)) / 86400);
        }

        // Comeback check (30+ days, not first visit)
        $is_comeback = ($visit_count > 1 && $days_since_last >= self::COMEBACK_DAYS_THRESHOLD);

        // Build base insights
        $insights = [
            'visit_count' => $visit_count,
            'is_new' => ($visit_count <= 1),
            'is_comeback' => $is_comeback,
            'is_birthday' => $is_birthday,
            'vip_level' => $vip_level,
            'vip_label' => $vip_label,
            'current_points' => $current_points,
            'points_to_reward' => $points_to_reward,
            'reward_available' => ($points_to_reward !== null && $points_to_reward <= 0),
            'days_since_last' => $days_since_last,
            'has_pattern' => false,
            'common_day' => null,
            'common_time' => null,
            'avg_frequency_days' => null,
        ];

        // Calculate patterns if enough visits
        if ($visit_count >= self::MIN_VISITS_FOR_PATTERN) {
            $pattern = self::calculate_pattern($visits, $lang);
            $insights = array_merge($insights, $pattern);
            $insights['has_pattern'] = true;
        }

        // Format for display
        $insights['display'] = self::format_display($insights, $lang);

        return $insights;
    }

    /**
     * Calculate visit patterns
     */
    private static function calculate_pattern($visits, $lang) {
        $day_counts = array_fill(1, 7, 0);
        $hour_counts = array_fill(0, 24, 0);
        $timestamps = [];

        foreach ($visits as $v) {
            $day_counts[$v->dow]++;
            $hour_counts[$v->hour]++;
            $timestamps[] = strtotime($v->created);
        }

        // Most common day
        $max_day = array_search(max($day_counts), $day_counts);
        $day_index = $max_day - 1; // Convert 1-7 to 0-6

        // Most common 2-hour window
        $best_hour = 10;
        $best_count = 0;
        for ($h = 8; $h <= 20; $h += 2) {
            $count = $hour_counts[$h] + ($hour_counts[$h + 1] ?? 0);
            if ($count > $best_count) {
                $best_count = $count;
                $best_hour = $h;
            }
        }

        // Average frequency
        $avg_freq = null;
        if (count($timestamps) >= 2) {
            $total = 0;
            for ($i = 1; $i < count($timestamps); $i++) {
                $total += ($timestamps[$i] - $timestamps[$i - 1]) / 86400;
            }
            $avg_freq = round($total / (count($timestamps) - 1));
        }

        return [
            'common_day' => self::$day_names[$lang][$day_index] ?? self::$day_names['de'][$day_index],
            'common_time' => sprintf('%d-%d', $best_hour, $best_hour + 2),
            'avg_frequency_days' => $avg_freq,
        ];
    }

    /**
     * Get VIP level from lifetime points
     */
    private static function get_vip_level($points) {
        if ($points >= 2000) return 'platinum';
        if ($points >= 1000) return 'gold';
        if ($points >= 500) return 'silver';
        if ($points >= 100) return 'bronze';
        return 'starter';
    }

    /**
     * Get localized VIP label
     */
    private static function get_vip_label($level, $lang) {
        $labels = [
            'de' => ['starter' => 'Starter', 'bronze' => 'Bronze', 'silver' => 'Silber', 'gold' => 'Gold', 'platinum' => 'Platin'],
            'hu' => ['starter' => 'Kezdő', 'bronze' => 'Bronz', 'silver' => 'Ezüst', 'gold' => 'Arany', 'platinum' => 'Platina'],
            'ro' => ['starter' => 'Începător', 'bronze' => 'Bronz', 'silver' => 'Argint', 'gold' => 'Aur', 'platinum' => 'Platină']
        ];
        return $labels[$lang][$level] ?? $labels['de'][$level];
    }

    /**
     * Format insights for compact display
     */
    private static function format_display($ins, $lang) {
        $t = self::get_strings($lang);

        // Line 1: Status
        if ($ins['is_new']) {
            $line1 = $t['new_customer'];
        } elseif ($ins['is_comeback']) {
            $line1 = str_replace('{days}', $ins['days_since_last'], $t['comeback']);
        } elseif (!$ins['has_pattern']) {
            $line1 = str_replace('{count}', $ins['visit_count'], $t['few_visits']);
        } else {
            $line1 = str_replace(['{vip}', '{count}'], [$ins['vip_label'], $ins['visit_count']], $t['vip_status']);
        }

        // Line 2: Pattern (if available)
        $line2 = '';
        if ($ins['has_pattern']) {
            $line2 = str_replace(
                ['{day}', '{time}', '{freq}'],
                [$ins['common_day'], $ins['common_time'], $ins['avg_frequency_days']],
                $t['pattern']
            );
        }

        // Line 3: Points
        if ($ins['reward_available']) {
            $line3 = $t['reward_ready'];
        } elseif ($ins['points_to_reward'] !== null) {
            $line3 = str_replace(['{points}', '{needed}'], [$ins['current_points'], $ins['points_to_reward']], $t['points_needed']);
        } else {
            $line3 = str_replace('{points}', $ins['current_points'], $t['points_total']);
        }

        // Birthday line
        $birthday_line = $ins['is_birthday'] ? $t['birthday'] : null;

        return [
            'line1' => $line1,
            'line2' => $line2,
            'line3' => $line3,
            'birthday' => $birthday_line
        ];
    }

    /**
     * Get localized strings
     */
    private static function get_strings($lang) {
        $all = [
            'de' => [
                'new_customer' => '🆕 Neuer Kunde – Willkommen!',
                'comeback' => '👋 Wieder da nach {days} Tagen!',
                'few_visits' => '📊 {count}. Besuch – Muster wird erfasst',
                'vip_status' => '⭐ {vip} | {count}x hier',
                'pattern' => '🗓 {day} | {time} Uhr | ~{freq} Tage',
                'points_needed' => '💰 {points} Pkt (noch {needed} bis Belohnung)',
                'points_total' => '💰 {points} Punkte gesamt',
                'reward_ready' => '🎁 Belohnung verfügbar!',
                'birthday' => '🎂 Alles Gute zum Geburtstag!'
            ],
            'hu' => [
                'new_customer' => '🆕 Új ügyfél – Üdvözöljük!',
                'comeback' => '👋 Visszatért {days} nap után!',
                'few_visits' => '📊 {count}. látogatás – minta rögzítése',
                'vip_status' => '⭐ {vip} | {count}x itt',
                'pattern' => '🗓 {day} | {time} | ~{freq} nap',
                'points_needed' => '💰 {points} pont (még {needed} a jutalomig)',
                'points_total' => '💰 {points} pont összesen',
                'reward_ready' => '🎁 Jutalom elérhető!',
                'birthday' => '🎂 Boldog születésnapot!'
            ],
            'ro' => [
                'new_customer' => '🆕 Client nou – Bine ați venit!',
                'comeback' => '👋 S-a întors după {days} zile!',
                'few_visits' => '📊 Vizita {count} – se înregistrează tiparul',
                'vip_status' => '⭐ {vip} | {count}x aici',
                'pattern' => '🗓 {day} | {time} | ~{freq} zile',
                'points_needed' => '💰 {points} pct (încă {needed} până la recompensă)',
                'points_total' => '💰 {points} puncte în total',
                'reward_ready' => '🎁 Recompensă disponibilă!',
                'birthday' => '🎂 La mulți ani!'
            ]
        ];
        return $all[$lang] ?? $all['de'];
    }
}
