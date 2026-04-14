<?php
/**
 * PunktePass Agent Dashboard
 * Mobile-first page for sales agents to track visits and prospects
 * Route: /agent (registered in punktepass.php via add_rewrite_rule)
 */

if (!defined('ABSPATH')) exit;

class PPV_Agent {

    public static function hooks() {
        add_action('init', [__CLASS__, 'register_route']);
        add_action('template_redirect', [__CLASS__, 'maybe_render']);
        add_shortcode('ppv_agent', [__CLASS__, 'render_agent_page']);
    }

    public static function register_route() {
        add_rewrite_rule('^agent/?$', 'index.php?ppv_page=agent', 'top');
        add_filter('query_vars', function($vars) {
            $vars[] = 'ppv_page';
            return $vars;
        });
    }

    public static function maybe_render() {
        $page = get_query_var('ppv_page');
        if ($page !== 'agent') return;

        if (session_status() === PHP_SESSION_NONE) @session_start();

        // Check auth
        $user_type = $_SESSION['ppv_user_type'] ?? '';
        $is_admin = current_user_can('manage_options');

        if (!$is_admin && !in_array($user_type, ['agent', 'admin', 'vendor'])) {
            wp_redirect(home_url('/login'));
            exit;
        }

        // Render standalone page
        self::render_standalone();
        exit;
    }

    private static function render_standalone() {
        $agent_id = intval($_SESSION['ppv_user_id'] ?? 0);
        $user_type = $_SESSION['ppv_user_type'] ?? 'agent';
        $api_base = rest_url('ppv/v1/agent');
        $nonce = wp_create_nonce('wp_rest');

        ?><!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>PunktePass Agent</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="<?php echo plugins_url('assets/css/ppv-agent.css', dirname(__FILE__)); ?>?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Top Bar -->
    <header class="agent-header">
        <h1><i class="ri-map-pin-user-fill"></i> PunktePass Agent</h1>
        <div class="agent-header-actions">
            <button id="btn-stats" class="header-btn" title="Statistici"><i class="ri-bar-chart-fill"></i></button>
            <button id="btn-list" class="header-btn active" title="Listă"><i class="ri-list-check"></i></button>
            <button id="btn-map" class="header-btn" title="Hartă"><i class="ri-map-2-fill"></i></button>
        </div>
    </header>

    <!-- Stats Panel -->
    <div id="panel-stats" class="panel" style="display:none">
        <div class="stats-grid" id="stats-grid">
            <div class="stat-card"><div class="stat-num" id="stat-today">-</div><div class="stat-label">Azi</div></div>
            <div class="stat-card"><div class="stat-num" id="stat-week">-</div><div class="stat-label">Săptămâna</div></div>
            <div class="stat-card"><div class="stat-num" id="stat-month">-</div><div class="stat-label">Luna</div></div>
            <div class="stat-card accent"><div class="stat-num" id="stat-followups">-</div><div class="stat-label">Follow-up</div></div>
        </div>
        <div class="stats-pipeline" id="stats-pipeline"></div>
    </div>

    <!-- List Panel -->
    <div id="panel-list" class="panel">
        <div class="filter-bar">
            <select id="filter-status">
                <option value="">Toate statusurile</option>
                <option value="visited">Vizitat</option>
                <option value="contacted">Contactat</option>
                <option value="interested">Interesat</option>
                <option value="customer">Client</option>
                <option value="not_interested">Neinteresat</option>
            </select>
            <select id="filter-period">
                <option value="">Toate</option>
                <option value="today">Azi</option>
                <option value="week">Săptămâna</option>
            </select>
        </div>
        <div id="prospect-list" class="prospect-list"></div>
    </div>

    <!-- Map Panel -->
    <div id="panel-map" class="panel" style="display:none">
        <div id="agent-map" style="width:100%;height:calc(100vh - 140px)"></div>
    </div>

    <!-- FAB: Check-in Button -->
    <button id="btn-checkin" class="fab-checkin">
        <i class="ri-map-pin-add-fill"></i>
        <span>Am fost aici!</span>
    </button>

    <!-- Check-in Modal -->
    <div id="checkin-modal" class="modal" style="display:none">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="ri-map-pin-add-fill"></i> Check-in</h2>
                <button class="modal-close" id="checkin-close">&times;</button>
            </div>
            <div id="checkin-gps-status" class="gps-status">
                <i class="ri-gps-fill spin"></i> Se determină poziția GPS...
            </div>
            <form id="checkin-form">
                <input type="hidden" id="ci-lat" name="lat">
                <input type="hidden" id="ci-lng" name="lng">

                <div class="form-group">
                    <label>Adresă (auto)</label>
                    <input type="text" id="ci-address" name="address" readonly placeholder="Se determină automat...">
                </div>
                <div class="form-group">
                    <label>Oraș (auto)</label>
                    <input type="text" id="ci-city" name="city" readonly>
                </div>

                <div class="form-group">
                    <label>Numele firmei *</label>
                    <input type="text" id="ci-business" name="business_name" required placeholder="ex. Gelateria Milano">
                </div>

                <div class="form-group">
                    <label>Persoană de contact</label>
                    <input type="text" id="ci-contact" name="contact_name" placeholder="Nume">
                </div>
                <div class="form-group">
                    <label>Telefon</label>
                    <input type="tel" id="ci-phone" name="contact_phone" placeholder="+40...">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <div class="status-buttons">
                        <button type="button" class="status-btn active" data-status="visited">Vizitat</button>
                        <button type="button" class="status-btn" data-status="interested">Interesat</button>
                        <button type="button" class="status-btn" data-status="not_interested">Neinteresat</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Notițe</label>
                    <textarea id="ci-notes" name="notes" rows="2" placeholder="Notă scurtă..."></textarea>
                </div>

                <button type="submit" class="btn-submit" id="ci-submit">
                    <i class="ri-save-fill"></i> Salvează
                </button>
            </form>
        </div>
    </div>

    <!-- Prospect Detail Modal -->
    <div id="detail-modal" class="modal" style="display:none">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="detail-title">Prospect</h2>
                <button class="modal-close" id="detail-close">&times;</button>
            </div>
            <div id="detail-body"></div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        window.PPV_AGENT = {
            api: '<?php echo esc_js($api_base); ?>',
            nonce: '<?php echo esc_js($nonce); ?>',
            agentId: <?php echo $agent_id; ?>,
            userType: '<?php echo esc_js($user_type); ?>'
        };
    </script>
    <script src="<?php echo plugins_url('assets/js/ppv-agent.js', dirname(__FILE__)); ?>?v=<?php echo time(); ?>"></script>
</body>
</html>
        <?php
    }

    /**
     * Shortcode fallback (if someone uses [ppv_agent])
     */
    public static function render_agent_page() {
        ob_start();
        self::render_standalone();
        return ob_get_clean();
    }
}
