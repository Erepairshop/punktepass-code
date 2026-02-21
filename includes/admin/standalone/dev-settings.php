<?php
/**
 * PunktePass Standalone Admin - Developer Settings
 * Asset versioning and performance settings
 */

// Must be accessed via WordPress
if (!defined('ABSPATH')) exit;

// Security: Session-based auth is already handled by PPV_Standalone_Admin::process_admin_request()
global $wpdb;

// Handle form submissions
$success_message = '';
$error_message = '';

// Save Development Mode setting
if (isset($_POST['save_dev_mode']) && check_admin_referer('ppv_dev_settings', 'ppv_dev_nonce')) {
    $dev_mode = isset($_POST['dev_mode']) ? 1 : 0;
    update_option('ppv_dev_mode', $dev_mode);
    $success_message = '✅ Fejlesztői mód ' . ($dev_mode ? 'BEKAPCSOLVA' : 'KIKAPCSOLVA');
    ppv_log("🔧 [Dev Settings] Development mode set to: " . ($dev_mode ? 'ON' : 'OFF'));
}

// Force Version Bump (clear all caches)
if (isset($_POST['force_refresh']) && check_admin_referer('ppv_dev_settings', 'ppv_dev_nonce')) {
    // Clean timestamp-based version (not appending to avoid infinite growth)
    $new_version = PPV_VERSION . '.' . time();
    update_option('ppv_force_version', $new_version);

    // Clear WordPress object cache
    wp_cache_flush();

    // Clear transients
    delete_transient('ppv_stats_cache');

    $success_message = '✅ Cache törölve! Új verzió: ' . $new_version . ' — A felhasználók friss fájlokat kapnak.';
    ppv_log("🔄 [Dev Settings] Force refresh triggered. New version: {$new_version}");
}

// Get current settings
$dev_mode = get_option('ppv_dev_mode', false);
$current_version = PPV_VERSION;
$force_version = get_option('ppv_force_version', PPV_VERSION);

?>

<style>
.dev-settings-container {
    max-width: 900px;
    margin: 20px auto;
    padding: 30px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.dev-settings-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #0066FF;
}

.dev-settings-header h1 {
    margin: 0 0 10px 0;
    color: #1a1a1a;
    font-size: 28px;
}

.dev-settings-header p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.dev-section {
    background: #f8f9fb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
}

.dev-section h2 {
    margin: 0 0 16px 0;
    font-size: 20px;
    color: #1a1a1a;
    display: flex;
    align-items: center;
    gap: 10px;
}

.dev-section p {
    margin: 0 0 20px 0;
    color: #666;
    line-height: 1.6;
}

.dev-toggle {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: #fff;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 20px;
}

.dev-toggle input[type="checkbox"] {
    position: relative;
    width: 56px;
    min-width: 56px;
    height: 30px;
    -webkit-appearance: none;
    appearance: none;
    background: #d1d5db;
    border: none;
    border-radius: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.dev-toggle input[type="checkbox"]::before {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 24px;
    height: 24px;
    background: #fff;
    border-radius: 50%;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.dev-toggle input[type="checkbox"]:checked {
    background: linear-gradient(135deg, #0066FF 0%, #0052CC 100%);
}

.dev-toggle input[type="checkbox"]:checked::before {
    transform: translateX(26px);
}

.dev-toggle-label {
    flex: 1;
}

.dev-toggle-label strong {
    display: block;
    font-size: 16px;
    color: #1a1a1a;
    margin-bottom: 4px;
}

.dev-toggle-label span {
    display: block;
    font-size: 13px;
    color: #666;
}

.dev-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
}

.dev-status.on {
    background: #dcfce7;
    color: #166534;
}

.dev-status.off {
    background: #fee2e2;
    color: #991b1b;
}

.dev-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-top: 20px;
}

.dev-info-card {
    background: #fff;
    padding: 16px;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
}

.dev-info-card label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.dev-info-card strong {
    display: block;
    font-size: 18px;
    color: #0066FF;
    font-family: monospace;
}

.dev-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #0066FF 0%, #0052CC 100%);
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 14px rgba(0, 102, 255, 0.3);
}

.dev-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 102, 255, 0.4);
}

.dev-button.secondary {
    background: #fff;
    color: #0066FF;
    border: 2px solid #0066FF;
    box-shadow: none;
}

.dev-button.secondary:hover {
    background: #f0f7ff;
}

.alert {
    padding: 14px 18px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert.success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}

.alert.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.code-example {
    background: #1e293b;
    color: #e2e8f0;
    padding: 16px;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    overflow-x: auto;
    margin-top: 12px;
}

.code-example .comment {
    color: #94a3b8;
}

.code-example .keyword {
    color: #f472b6;
}

.code-example .string {
    color: #a5f3fc;
}
</style>

<div class="dev-settings-container">
    <div class="dev-settings-header">
        <h1>⚙️ Fejlesztői Beállítások</h1>
        <p>Asset verziókezelés és teljesítmény optimalizálási beállítások</p>
    </div>

    <?php if ($success_message): ?>
        <div class="alert success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Development Mode -->
    <div class="dev-section">
        <h2>
            <span>🔧</span> Fejlesztői Mód
        </h2>
        <p>
            Ha be van kapcsolva, a CSS/JS fájlok módosítási időbélyeget használnak a plugin verzió helyett.
            Ez biztosítja hogy fejlesztés során mindig a legfrissebb fájlokat kapd, de lassabb lehet.
        </p>

        <form method="post">
            <?php wp_nonce_field('ppv_dev_settings', 'ppv_dev_nonce'); ?>

            <div class="dev-toggle">
                <input type="checkbox" name="dev_mode" id="dev_mode" <?php checked($dev_mode); ?>>
                <label for="dev_mode" class="dev-toggle-label">
                    <strong>Fejlesztői Mód Bekapcsolása</strong>
                    <span>A fájlok automatikusan frissülnek amikor változnak</span>
                </label>
                <span class="dev-status <?php echo $dev_mode ? 'on' : 'off'; ?>">
                    <?php echo $dev_mode ? '✅ BE' : '❌ KI'; ?>
                </span>
            </div>

            <div class="code-example">
                <span class="comment">// Jelenlegi viselkedés:</span><br>
                <?php if ($dev_mode): ?>
                <span class="keyword">wp_enqueue_script</span>(<span class="string">'script.js'</span>, [], <span class="keyword">filemtime</span>($file));
                <span class="comment">→ script.js?ver=1737001234 (változik amikor frissül a fájl)</span>
                <?php else: ?>
                <span class="keyword">wp_enqueue_script</span>(<span class="string">'script.js'</span>, [], <span class="string">'<?php echo PPV_VERSION; ?>'</span>);
                <span class="comment">→ script.js?ver=<?php echo PPV_VERSION; ?> (cache-elve amíg nem változik a verzió)</span>
                <?php endif; ?>
            </div>

            <button type="submit" name="save_dev_mode" class="dev-button" style="margin-top: 20px;">
                💾 Beállítások Mentése
            </button>
        </form>
    </div>

    <!-- Version Information -->
    <div class="dev-section">
        <h2>
            <span>📦</span> Verzió Információk
        </h2>

        <div class="dev-info-grid">
            <div class="dev-info-card">
                <label>Plugin Verzió</label>
                <strong><?php echo PPV_VERSION; ?></strong>
            </div>
            <div class="dev-info-card">
                <label>Kényszerített Verzió</label>
                <strong><?php echo $force_version; ?></strong>
            </div>
            <div class="dev-info-card">
                <label>Mód</label>
                <strong><?php echo $dev_mode ? 'FEJL' : 'ÉLES'; ?></strong>
            </div>
        </div>
    </div>

    <!-- Force Refresh -->
    <div class="dev-section">
        <h2>
            <span>🔄</span> Felhasználói Cache Frissítés
        </h2>
        <p>
            Amikor frissítést adsz ki, kattints erre a gombra hogy minden felhasználó friss fájlokat töltsön le.
            Ez törli a cache-t és növeli a verzió számot.
        </p>

        <form method="post" onsubmit="return confirm('Biztos? Ez minden felhasználót friss fájlok letöltésére kényszerít.');">
            <?php wp_nonce_field('ppv_dev_settings', 'ppv_dev_nonce'); ?>
            <button type="submit" name="force_refresh" class="dev-button secondary">
                🚀 Összes Felhasználó Frissítése
            </button>
        </form>
    </div>
</div>
