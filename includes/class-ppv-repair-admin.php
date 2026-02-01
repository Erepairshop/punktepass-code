<?php
/**
 * PunktePass - Repair Admin Dashboard
 * Shortcode: [ppv_repair_admin]
 * Separate admin page for repair shop owners
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Repair_Admin {

    public static function hooks() {
        add_shortcode('ppv_repair_admin', [__CLASS__, 'render']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function enqueue_assets() {
        global $post;
        if (!isset($post->post_content) || !has_shortcode($post->post_content, 'ppv_repair_admin')) {
            if (!is_page('repair-admin')) return;
        }

        wp_enqueue_style(
            'ppv-repair-admin-css',
            PPV_PLUGIN_URL . 'assets/css/ppv-repair.css',
            ['remixicons'],
            PPV_VERSION
        );

        wp_enqueue_script(
            'ppv-repair-admin-js',
            PPV_PLUGIN_URL . 'assets/js/ppv-repair-admin.js',
            ['jquery'],
            PPV_VERSION,
            true
        );

        $store_id = PPV_Repair_Core::get_current_store_id();

        wp_localize_script('ppv-repair-admin-js', 'ppvRepairAdmin', [
            'ajaxurl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ppv_repair_admin'),
            'store_id' => $store_id,
        ]);
    }

    public static function render() {
        // Check login
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $user_id = $_SESSION['ppv_user_id'] ?? 0;
        if (!$user_id) {
            return self::render_login_form();
        }

        // Get store
        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d AND repair_enabled = 1 LIMIT 1",
            $user_id
        ));

        if (!$store) {
            return '<div class="ppv-repair-msg">
                <p>Kein Reparaturformular gefunden. <a href="/repair-register">Jetzt registrieren</a></p>
            </div>';
        }

        // Get stats
        $total_repairs = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_repairs WHERE store_id = %d", $store->id
        ));
        $open_repairs = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_repairs WHERE store_id = %d AND status IN ('new','in_progress','waiting_parts')", $store->id
        ));
        $today_repairs = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_repairs WHERE store_id = %d AND DATE(created_at) = %s", $store->id, current_time('Y-m-d')
        ));
        $total_customers = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT customer_email) FROM {$wpdb->prefix}ppv_repairs WHERE store_id = %d", $store->id
        ));

        // Recent repairs
        $recent = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_repairs WHERE store_id = %d ORDER BY created_at DESC LIMIT 20", $store->id
        ));

        $form_url = home_url("/repair/{$store->slug}");
        $limit_pct = $store->repair_form_limit > 0 ? min(100, round(($store->repair_form_count / $store->repair_form_limit) * 100)) : 0;

        ob_start();
        ?>
        <div class="repair-admin" data-store-id="<?php echo intval($store->id); ?>">

            <!-- Header -->
            <div class="ra-header">
                <div class="ra-header-left">
                    <?php if ($store->logo): ?>
                        <img src="<?php echo esc_url($store->logo); ?>" alt="" class="ra-logo">
                    <?php endif; ?>
                    <div>
                        <h1 class="ra-title"><?php echo esc_html($store->name); ?></h1>
                        <p class="ra-subtitle">Reparatur Admin</p>
                    </div>
                </div>
                <div class="ra-header-right">
                    <a href="<?php echo esc_url($form_url); ?>" target="_blank" class="ra-btn ra-btn-outline">
                        <i class="ri-external-link-line"></i> Formular ansehen
                    </a>
                    <button class="ra-btn ra-btn-icon" onclick="document.getElementById('ra-settings').classList.toggle('ra-hidden')" title="Einstellungen">
                        <i class="ri-settings-3-line"></i>
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="ra-stats">
                <div class="ra-stat-card">
                    <div class="ra-stat-value"><?php echo $total_repairs; ?></div>
                    <div class="ra-stat-label">Gesamt</div>
                </div>
                <div class="ra-stat-card ra-stat-highlight">
                    <div class="ra-stat-value"><?php echo $open_repairs; ?></div>
                    <div class="ra-stat-label">Offen</div>
                </div>
                <div class="ra-stat-card">
                    <div class="ra-stat-value"><?php echo $today_repairs; ?></div>
                    <div class="ra-stat-label">Heute</div>
                </div>
                <div class="ra-stat-card">
                    <div class="ra-stat-value"><?php echo $total_customers; ?></div>
                    <div class="ra-stat-label">Kunden</div>
                </div>
            </div>

            <!-- Usage bar (free tier) -->
            <?php if (!$store->repair_premium): ?>
            <div class="ra-usage">
                <div class="ra-usage-info">
                    <span>Formulare: <?php echo intval($store->repair_form_count); ?> / <?php echo intval($store->repair_form_limit); ?></span>
                    <?php if ($limit_pct >= 80): ?>
                        <span class="ra-usage-warn">Limit fast erreicht!</span>
                    <?php endif; ?>
                </div>
                <div class="ra-usage-bar">
                    <div class="ra-usage-fill" style="width:<?php echo $limit_pct; ?>%"></div>
                </div>
            </div>
            <?php else: ?>
            <div class="ra-premium-badge">
                <i class="ri-vip-crown-line"></i> Premium aktiv
            </div>
            <?php endif; ?>

            <!-- Form Link -->
            <div class="ra-link-card">
                <div class="ra-link-label">Ihr Formular-Link</div>
                <div class="ra-link-row">
                    <input type="text" value="<?php echo esc_attr($form_url); ?>" readonly class="ra-link-input" id="ra-form-url">
                    <button class="ra-btn ra-btn-copy" onclick="navigator.clipboard.writeText(document.getElementById('ra-form-url').value);this.innerHTML='<i class=\'ri-check-line\'></i>';setTimeout(()=>{this.innerHTML='<i class=\'ri-file-copy-line\'></i>'},2000)">
                        <i class="ri-file-copy-line"></i>
                    </button>
                </div>
            </div>

            <!-- Settings panel (hidden by default) -->
            <div id="ra-settings" class="ra-settings ra-hidden">
                <h3><i class="ri-settings-3-line"></i> Einstellungen</h3>
                <form id="ra-settings-form" class="ra-settings-form">
                    <div class="ra-settings-grid">
                        <div class="repair-field">
                            <label>Firmenname</label>
                            <input type="text" name="name" value="<?php echo esc_attr($store->name); ?>">
                        </div>
                        <div class="repair-field">
                            <label>Bonuspunkte pro Formular</label>
                            <input type="number" name="repair_points_per_form" value="<?php echo intval($store->repair_points_per_form); ?>" min="0" max="10">
                        </div>
                        <div class="repair-field">
                            <label>Adresse</label>
                            <input type="text" name="address" value="<?php echo esc_attr($store->address); ?>">
                        </div>
                        <div class="repair-field">
                            <label>PLZ</label>
                            <input type="text" name="plz" value="<?php echo esc_attr($store->plz); ?>">
                        </div>
                        <div class="repair-field">
                            <label>Stadt</label>
                            <input type="text" name="city" value="<?php echo esc_attr($store->city); ?>">
                        </div>
                        <div class="repair-field">
                            <label>Telefon</label>
                            <input type="text" name="phone" value="<?php echo esc_attr($store->phone); ?>">
                        </div>
                        <div class="repair-field">
                            <label>Firmenname (Impressum)</label>
                            <input type="text" name="repair_company_name" value="<?php echo esc_attr($store->repair_company_name); ?>">
                        </div>
                        <div class="repair-field">
                            <label>Inhaber (Impressum)</label>
                            <input type="text" name="repair_owner_name" value="<?php echo esc_attr($store->repair_owner_name); ?>">
                        </div>
                        <div class="repair-field">
                            <label>USt-IdNr.</label>
                            <input type="text" name="repair_tax_id" value="<?php echo esc_attr($store->repair_tax_id); ?>">
                        </div>
                        <div class="repair-field">
                            <label>Akzentfarbe</label>
                            <input type="color" name="repair_color" value="<?php echo esc_attr($store->repair_color ?: '#667eea'); ?>">
                        </div>
                    </div>

                    <!-- Logo upload -->
                    <div class="ra-logo-section">
                        <label>Logo</label>
                        <div class="ra-logo-upload">
                            <?php if ($store->logo): ?>
                                <img src="<?php echo esc_url($store->logo); ?>" class="ra-logo-preview" id="ra-logo-preview">
                            <?php else: ?>
                                <div class="ra-logo-placeholder" id="ra-logo-preview">
                                    <i class="ri-image-add-line"></i>
                                </div>
                            <?php endif; ?>
                            <input type="file" id="ra-logo-file" accept="image/*" style="display:none">
                            <button type="button" class="ra-btn ra-btn-outline ra-btn-sm" onclick="document.getElementById('ra-logo-file').click()">
                                <i class="ri-upload-2-line"></i> Logo hochladen
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="ra-btn ra-btn-primary">
                        <i class="ri-save-line"></i> Speichern
                    </button>
                </form>

                <!-- Legal pages links -->
                <div class="ra-legal-links">
                    <h4>Automatisch generierte Seiten</h4>
                    <a href="/repair/<?php echo esc_attr($store->slug); ?>/datenschutz" target="_blank"><i class="ri-shield-check-line"></i> Datenschutz</a>
                    <a href="/repair/<?php echo esc_attr($store->slug); ?>/agb" target="_blank"><i class="ri-file-text-line"></i> AGB</a>
                    <a href="/repair/<?php echo esc_attr($store->slug); ?>/impressum" target="_blank"><i class="ri-building-line"></i> Impressum</a>
                </div>

                <!-- PunktePass admin link -->
                <div class="ra-pp-link">
                    <a href="/qr-center" class="ra-btn ra-btn-outline">
                        <i class="ri-qr-code-line"></i> PunktePass Admin &ouml;ffnen
                    </a>
                </div>
            </div>

            <!-- Search & Filters -->
            <div class="ra-toolbar">
                <div class="ra-search">
                    <i class="ri-search-line"></i>
                    <input type="text" id="ra-search-input" placeholder="Name, E-Mail, Telefon oder Ger&auml;t suchen...">
                </div>
                <div class="ra-filters">
                    <select id="ra-filter-status">
                        <option value="">Alle Status</option>
                        <option value="new">Neu</option>
                        <option value="in_progress">In Bearbeitung</option>
                        <option value="waiting_parts">Wartet auf Teile</option>
                        <option value="done">Fertig</option>
                        <option value="delivered">Abgeholt</option>
                        <option value="cancelled">Storniert</option>
                    </select>
                </div>
            </div>

            <!-- Repairs list -->
            <div class="ra-repairs" id="ra-repairs-list">
                <?php if (empty($recent)): ?>
                    <div class="ra-empty">
                        <i class="ri-inbox-line"></i>
                        <p>Noch keine Reparaturen. Teilen Sie Ihren Formular-Link!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent as $r): ?>
                        <?php echo self::render_repair_card($r); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Load more -->
            <?php if ($total_repairs > 20): ?>
            <div class="ra-load-more">
                <button class="ra-btn ra-btn-outline" id="ra-load-more" data-page="1">Mehr laden</button>
            </div>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /** ============================================================
     * Render single repair card
     * ============================================================ */
    public static function render_repair_card($r) {
        $status_labels = [
            'new'           => ['Neu', 'ra-status-new'],
            'in_progress'   => ['In Bearbeitung', 'ra-status-progress'],
            'waiting_parts' => ['Wartet auf Teile', 'ra-status-waiting'],
            'done'          => ['Fertig', 'ra-status-done'],
            'delivered'     => ['Abgeholt', 'ra-status-delivered'],
            'cancelled'     => ['Storniert', 'ra-status-cancelled'],
        ];

        $status = $status_labels[$r->status] ?? ['Unbekannt', ''];
        $device = trim(($r->device_brand ?: '') . ' ' . ($r->device_model ?: ''));
        $date = date('d.m.Y H:i', strtotime($r->created_at));

        ob_start();
        ?>
        <div class="ra-repair-card" data-id="<?php echo intval($r->id); ?>">
            <div class="ra-repair-header">
                <div class="ra-repair-id">#<?php echo intval($r->id); ?></div>
                <span class="ra-status <?php echo esc_attr($status[1]); ?>"><?php echo esc_html($status[0]); ?></span>
            </div>
            <div class="ra-repair-body">
                <div class="ra-repair-customer">
                    <strong><?php echo esc_html($r->customer_name); ?></strong>
                    <span class="ra-repair-meta">
                        <?php echo esc_html($r->customer_email); ?>
                        <?php if ($r->customer_phone): ?> &middot; <?php echo esc_html($r->customer_phone); ?><?php endif; ?>
                    </span>
                </div>
                <?php if ($device): ?>
                    <div class="ra-repair-device"><i class="ri-smartphone-line"></i> <?php echo esc_html($device); ?></div>
                <?php endif; ?>
                <div class="ra-repair-problem"><?php echo esc_html(mb_strimwidth($r->problem_description, 0, 150, '...')); ?></div>
                <div class="ra-repair-date"><i class="ri-time-line"></i> <?php echo $date; ?></div>
            </div>
            <div class="ra-repair-actions">
                <select class="ra-status-select" data-repair-id="<?php echo intval($r->id); ?>">
                    <?php foreach ($status_labels as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($r->status, $key); ?>><?php echo esc_html($label[0]); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /** ============================================================
     * Render login form for repair admin
     * ============================================================ */
    private static function render_login_form() {
        ob_start();
        ?>
        <div class="repair-admin-login">
            <div class="repair-admin-login-box">
                <img src="<?php echo PPV_PLUGIN_URL; ?>assets/img/punktepass-logo.png" alt="PunktePass" class="ra-login-logo">
                <h2>Repair Admin Login</h2>
                <form id="ra-login-form">
                    <div class="repair-field">
                        <label for="ra-login-email">E-Mail</label>
                        <input type="email" id="ra-login-email" required placeholder="info@ihr-shop.de">
                    </div>
                    <div class="repair-field">
                        <label for="ra-login-pass">Passwort</label>
                        <input type="password" id="ra-login-pass" required placeholder="Passwort">
                    </div>
                    <button type="submit" class="ra-btn ra-btn-primary ra-btn-full">Anmelden</button>
                    <div id="ra-login-error" class="repair-error" style="display:none"></div>
                </form>
                <p class="ra-login-register">Noch kein Konto? <a href="/repair-register">Jetzt registrieren</a></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
