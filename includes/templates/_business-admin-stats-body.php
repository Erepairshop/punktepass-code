<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$adv = PPV_Advertisers::current_advertiser();

// Security check: ensure we have a valid advertiser
if (!$adv || !isset($adv->id)) {
    echo '<div class="bz-card"><p>Error: Could not determine the current advertiser.</p></div>';
    return;
}

$parent_id = $adv->id;

// Fetch branches (filialen) for the current advertiser
$branches = $wpdb->get_results($wpdb->prepare(
    "SELECT id, name FROM {$wpdb->prefix}ppv_advertisers WHERE parent_advertiser_id = %d OR id = %d ORDER BY name ASC",
    $parent_id, $parent_id
));

// Get the current filter from GET parameter
$filiale_filter = isset($_GET['filiale']) ? sanitize_text_field($_GET['filiale']) : 'all';

// --- Build the SQL WHERE clause based on the filter ---
$where_clause = '';
$params = [];

if ($filiale_filter === 'all') {
    // All branches + parent
    $where_clause = "WHERE advertiser_id IN (SELECT id FROM {$wpdb->prefix}ppv_advertisers WHERE parent_advertiser_id = %d OR id = %d)";
    $params[] = $parent_id;
    $params[] = $parent_id;
} else {
    // Specific branch (must be a numeric ID and a valid child)
    $filiale_id = (int)$filiale_filter;
    $is_valid_branch = false;
    foreach ($branches as $branch) {
        if ($branch->id == $filiale_id) {
            $is_valid_branch = true;
            break;
        }
    }
    // Only apply filter if the selected ID is a valid branch for this advertiser
    if ($filiale_id > 0 && $is_valid_branch) {
        $where_clause = "WHERE advertiser_id = %d";
        $params[] = $filiale_id;
    } else {
        // Fallback to all if filter is invalid
        $where_clause = "WHERE advertiser_id IN (SELECT id FROM {$wpdb->prefix}ppv_advertisers WHERE parent_advertiser_id = %d OR id = %d)";
        $params[] = $parent_id;
        $params[] = $parent_id;
    }
}

// --- Get statistics with the new filter ---
$totals_query = "SELECT SUM(clicks) as clk, COUNT(*) as cnt FROM {$wpdb->prefix}ppv_ads " . $where_clause;
$totals = $wpdb->get_row($wpdb->prepare($totals_query, ...$params));

// Follower count remains at the parent level as per instructions
$followers = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_advertiser_followers WHERE advertiser_id = %d", $parent_id));

?>

<div class="bz-card">
    <h1 class="bz-h1"><?php echo esc_html(PPV_Lang::t('biz_stats_title')); ?></h1>

    <?php if (!empty($branches) && count($branches) > 1) : ?>
        <form action="" method="get" class="bz-stats-filter-form">
            <?php
            // Preserve existing GET parameters
            foreach ($_GET as $key => $value) {
                if ($key !== 'filiale') {
                    echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                }
            }
            ?>
            <label for="filiale-filter"><?php echo esc_html(PPV_Lang::t('biz_stats_filter_label')); ?></label>
            <select name="filiale" id="filiale-filter" onchange="this.form.submit()">
                <option value="all" <?php selected($filiale_filter, 'all'); ?>><?php echo esc_html(PPV_Lang::t('biz_stats_filter_all')); ?></option>
                <?php foreach ($branches as $branch) : ?>
                    <option value="<?php echo esc_attr($branch->id); ?>" <?php selected($filiale_filter, $branch->id); ?>>
                        <?php echo esc_html($branch->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
</div>

<div class="bz-grid">
    <div class="bz-card">
        <h2 class="bz-h2"><?php echo esc_html(PPV_Lang::t('biz_stats_ads')); ?></h2>
        <div style="font-size:32px; font-weight:700;"><?php echo (int)($totals->cnt ?? 0); ?></div>
    </div>
    <div class="bz-card">
        <h2 class="bz-h2"><?php echo esc_html(PPV_Lang::t('biz_stats_clk')); ?></h2>
        <div style="font-size:32px; font-weight:700;"><?php echo (int)($totals->clk ?? 0); ?></div>
    </div>
    <div class="bz-card">
        <h2 class="bz-h2"><?php echo esc_html(PPV_Lang::t('biz_stats_followers')); ?></h2>
        <div style="font-size:32px; font-weight:700;"><?php echo $followers; ?></div>
    </div>
</div>

<style>
    .bz-stats-filter-form {
        margin-top: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
</style>
