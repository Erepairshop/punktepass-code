<?php
/**
 * PunktePass - Partner Dashboard (Public)
 * Route: /formular/partner/dashboard?code=PP-XXXXXX&token=HASH
 *
 * Partners get a unique link (with HMAC token) to view their stats:
 * - How many shops registered through them
 * - Total forms submitted by those shops
 * - Commission earned
 * - List of referred shops
 *
 * Bilingual: DE/EN (switchable)
 */

if (!defined('ABSPATH')) exit;

class PPV_Partner_Dashboard {

    /**
     * Generate HMAC token for partner code (stateless auth)
     */
    public static function generate_token($partner_code) {
        return hash_hmac('sha256', $partner_code, wp_salt('auth'));
    }

    /**
     * Build dashboard URL for a partner
     */
    public static function get_dashboard_url($partner_code) {
        $token = self::generate_token($partner_code);
        return home_url('/formular/partner/dashboard?code=' . urlencode($partner_code) . '&token=' . $token);
    }

    /**
     * Render dashboard page
     */
    public static function render() {
        $code  = sanitize_text_field($_GET['code'] ?? '');
        $token = sanitize_text_field($_GET['token'] ?? '');
        $lang  = sanitize_text_field($_GET['lang'] ?? 'de');

        if (!$code || !$token) {
            self::render_error('Zugang verweigert', 'Kein g&uuml;ltiger Dashboard-Link.', 'Access denied', 'No valid dashboard link.');
            return;
        }

        // Verify HMAC token
        $expected = self::generate_token($code);
        if (!hash_equals($expected, $token)) {
            self::render_error('Zugang verweigert', 'Ung&uuml;ltiger oder abgelaufener Link.', 'Access denied', 'Invalid or expired link.');
            return;
        }

        // Get partner data
        global $wpdb;
        $partner = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_partners WHERE partner_code = %s",
            $code
        ));

        if (!$partner) {
            self::render_error('Partner nicht gefunden', 'Dieser Partner existiert nicht.', 'Partner not found', 'This partner does not exist.');
            return;
        }

        // Get referred stores
        $stores = $wpdb->get_results($wpdb->prepare(
            "SELECT repair_company_name, city, created_at, repair_form_count, repair_premium
             FROM {$wpdb->prefix}ppv_stores
             WHERE partner_id = %d
             ORDER BY created_at DESC",
            $partner->id
        ));

        $total_forms = 0;
        $premium_count = 0;
        foreach ($stores as $s) {
            $total_forms += (int)$s->repair_form_count;
            if ((int)$s->repair_premium) $premium_count++;
        }

        // Commission calculation
        $commission_rate = (float)$partner->commission_rate;
        $premium_price = 39.0; // EUR/month
        $estimated_commission = $premium_count * $premium_price * ($commission_rate / 100);

        // Translations
        $t = self::get_translations($lang);

        // Base URL for language switch
        $base_url = '/formular/partner/dashboard?code=' . urlencode($code) . '&token=' . urlencode($token);

        ob_start();
        ?>
<!DOCTYPE html><html lang="<?php echo $lang; ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html($t['page_title']); ?> - <?php echo esc_html($partner->company_name); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
<meta name="robots" content="noindex,nofollow">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Inter,-apple-system,sans-serif;background:#f1f5f9;color:#0f172a;min-height:100vh}
.pd-header{background:linear-gradient(135deg,#667eea,#4338ca);padding:24px 32px;color:#fff;position:relative;overflow:hidden}
.pd-header::after{content:"";position:absolute;top:-60px;right:-60px;width:200px;height:200px;background:rgba(255,255,255,0.06);border-radius:50%}
.pd-header::before{content:"";position:absolute;bottom:-40px;left:20%;width:150px;height:150px;background:rgba(255,255,255,0.04);border-radius:50%}
.pd-header-inner{max-width:1000px;margin:0 auto;position:relative;z-index:1;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.pd-logo{width:48px;height:48px;border-radius:12px;object-fit:contain;background:rgba(255,255,255,0.15);border:2px solid rgba(255,255,255,0.2)}
.pd-logo-placeholder{width:48px;height:48px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:#fff}
.pd-header h1{font-size:22px;font-weight:800}
.pd-header .pd-subtitle{font-size:13px;color:rgba(255,255,255,0.8);margin-top:2px}
.pd-lang-switch{margin-left:auto;display:flex;gap:4px}
.pd-lang-btn{padding:6px 12px;border-radius:6px;border:1px solid rgba(255,255,255,0.25);background:transparent;color:rgba(255,255,255,0.7);font-size:12px;font-weight:700;cursor:pointer;text-decoration:none;font-family:inherit;transition:all .2s}
.pd-lang-btn:hover,.pd-lang-btn.active{background:rgba(255,255,255,0.2);color:#fff;border-color:rgba(255,255,255,0.4)}
.pd-body{max-width:1000px;margin:0 auto;padding:24px}
.pd-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.pd-stat-card{background:#fff;border-radius:14px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.06);text-align:center;transition:transform .2s}
.pd-stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.08)}
.pd-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;margin:0 auto 10px}
.pd-stat-num{font-size:32px;font-weight:800;line-height:1}
.pd-stat-label{font-size:12px;color:#64748b;margin-top:6px;font-weight:500}
.pd-card{background:#fff;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.06);padding:24px;margin-bottom:20px}
.pd-card h2{font-size:17px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.pd-table{width:100%;border-collapse:collapse}
.pd-table th{text-align:left;padding:10px 12px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #e2e8f0}
.pd-table td{padding:12px;border-bottom:1px solid #f1f5f9;font-size:14px}
.pd-table tr:hover td{background:#f8fafc}
.pd-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
.pd-badge-premium{background:#dcfce7;color:#166534}
.pd-badge-free{background:#f1f5f9;color:#64748b}
.pd-empty{text-align:center;padding:32px;color:#94a3b8}
.pd-empty i{font-size:40px;display:block;margin-bottom:10px}
.pd-ref-card{background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border:1px solid #bae6fd;border-radius:14px;padding:20px;margin-bottom:20px}
.pd-ref-card h3{font-size:15px;font-weight:700;color:#0369a1;margin-bottom:8px;display:flex;align-items:center;gap:8px}
.pd-ref-link{font-family:monospace;font-size:13px;word-break:break-all;color:#0c4a6e;background:#fff;padding:10px 14px;border-radius:8px;border:1px solid #bae6fd;display:flex;align-items:center;gap:10px;cursor:pointer}
.pd-ref-link:hover{background:#f0f9ff}
.pd-ref-link .copy-icon{color:#0369a1;font-size:16px;flex-shrink:0}
.pd-footer{text-align:center;padding:24px;color:#94a3b8;font-size:12px}
.pd-footer a{color:#667eea;text-decoration:none}
@media(max-width:768px){.pd-stats{grid-template-columns:1fr 1fr}.pd-header-inner{flex-direction:column;align-items:flex-start}.pd-lang-switch{margin-left:0}.pd-body{padding:16px}}
</style></head><body>

<div class="pd-header">
    <div class="pd-header-inner">
        <?php if ($partner->logo_url): ?>
            <img src="<?php echo esc_url($partner->logo_url); ?>" class="pd-logo" alt="Logo">
        <?php else: ?>
            <div class="pd-logo-placeholder"><?php echo esc_html(mb_substr($partner->company_name, 0, 1)); ?></div>
        <?php endif; ?>
        <div>
            <h1><?php echo $t['page_title']; ?></h1>
            <div class="pd-subtitle"><?php echo esc_html($partner->company_name); ?> &middot; <?php echo esc_html($partner->partner_code); ?></div>
        </div>
        <div class="pd-lang-switch">
            <a href="<?php echo esc_url($base_url . '&lang=de'); ?>" class="pd-lang-btn<?php echo $lang==='de'?' active':''; ?>">DE</a>
            <a href="<?php echo esc_url($base_url . '&lang=en'); ?>" class="pd-lang-btn<?php echo $lang==='en'?' active':''; ?>">EN</a>
        </div>
    </div>
</div>

<div class="pd-body">
    <!-- Stats -->
    <div class="pd-stats">
        <div class="pd-stat-card">
            <div class="pd-stat-icon" style="background:#ede9fe;color:#7c3aed"><i class="ri-store-2-line"></i></div>
            <div class="pd-stat-num" style="color:#7c3aed"><?php echo count($stores); ?></div>
            <div class="pd-stat-label"><?php echo $t['stat_shops']; ?></div>
        </div>
        <div class="pd-stat-card">
            <div class="pd-stat-icon" style="background:#dbeafe;color:#2563eb"><i class="ri-file-list-3-line"></i></div>
            <div class="pd-stat-num" style="color:#2563eb"><?php echo $total_forms; ?></div>
            <div class="pd-stat-label"><?php echo $t['stat_forms']; ?></div>
        </div>
        <div class="pd-stat-card">
            <div class="pd-stat-icon" style="background:#dcfce7;color:#16a34a"><i class="ri-vip-crown-2-line"></i></div>
            <div class="pd-stat-num" style="color:#16a34a"><?php echo $premium_count; ?></div>
            <div class="pd-stat-label"><?php echo $t['stat_premium']; ?></div>
        </div>
        <div class="pd-stat-card">
            <div class="pd-stat-icon" style="background:#fef3c7;color:#d97706"><i class="ri-money-euro-circle-line"></i></div>
            <div class="pd-stat-num" style="color:#d97706">&euro;<?php echo number_format($estimated_commission, 2, ',', '.'); ?></div>
            <div class="pd-stat-label"><?php echo $t['stat_commission']; ?> (<?php echo $commission_rate; ?>%)</div>
        </div>
    </div>

    <!-- Referral Link -->
    <div class="pd-ref-card">
        <h3><i class="ri-link"></i> <?php echo $t['ref_title']; ?></h3>
        <p style="font-size:13px;color:#475569;margin-bottom:10px"><?php echo $t['ref_desc']; ?></p>
        <div class="pd-ref-link" onclick="copyRefLink(this)">
            <span>https://punktepass.de/formular?ref=<?php echo esc_html($partner->partner_code); ?></span>
            <i class="ri-file-copy-line copy-icon"></i>
        </div>
    </div>

    <!-- Shops Table -->
    <div class="pd-card">
        <h2><i class="ri-store-2-line"></i> <?php echo $t['shops_title']; ?> (<?php echo count($stores); ?>)</h2>
        <?php if (count($stores) > 0): ?>
        <table class="pd-table">
            <thead>
                <tr>
                    <th><?php echo $t['col_shop']; ?></th>
                    <th><?php echo $t['col_city']; ?></th>
                    <th><?php echo $t['col_forms']; ?></th>
                    <th><?php echo $t['col_plan']; ?></th>
                    <th><?php echo $t['col_registered']; ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stores as $s): ?>
                <tr>
                    <td><strong><?php echo esc_html($s->repair_company_name ?: '-'); ?></strong></td>
                    <td><?php echo esc_html($s->city ?: '-'); ?></td>
                    <td style="font-weight:600"><?php echo (int)$s->repair_form_count; ?></td>
                    <td><?php echo (int)$s->repair_premium
                        ? '<span class="pd-badge pd-badge-premium"><i class="ri-vip-crown-2-line"></i> Premium</span>'
                        : '<span class="pd-badge pd-badge-free">Free</span>'; ?></td>
                    <td><?php echo date_i18n($lang === 'de' ? 'd.m.Y' : 'M j, Y', strtotime($s->created_at)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="pd-empty">
            <i class="ri-store-2-line"></i>
            <p><?php echo $t['no_shops']; ?></p>
            <p style="font-size:13px;margin-top:6px"><?php echo $t['no_shops_sub']; ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Commission Info -->
    <div class="pd-card">
        <h2><i class="ri-money-euro-circle-line"></i> <?php echo $t['commission_title']; ?></h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:14px">
            <div>
                <div style="color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;margin-bottom:4px"><?php echo $t['your_rate']; ?></div>
                <div style="font-size:24px;font-weight:800;color:#667eea"><?php echo $commission_rate; ?>%</div>
            </div>
            <div>
                <div style="color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;margin-bottom:4px"><?php echo $t['monthly_est']; ?></div>
                <div style="font-size:24px;font-weight:800;color:#d97706">&euro;<?php echo number_format($estimated_commission, 2, ',', '.'); ?></div>
            </div>
        </div>
        <div style="margin-top:16px;padding:12px;background:#f8fafc;border-radius:8px;font-size:13px;color:#64748b">
            <i class="ri-information-line"></i> <?php echo $t['commission_note']; ?>
        </div>
    </div>

    <!-- Partner Info -->
    <div class="pd-card" style="background:#f8fafc;border:1px solid #e2e8f0;box-shadow:none">
        <h2 style="font-size:14px;color:#64748b"><i class="ri-user-settings-line"></i> <?php echo $t['account_title']; ?></h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:14px">
            <div><span style="color:#94a3b8"><?php echo $t['acc_model']; ?>:</span> <strong><?php
                $models_t = $lang === 'de'
                    ? ['newsletter'=>'Newsletter & Webshop','package_insert'=>'Paketbeilage','co_branded'=>'Co-Branded']
                    : ['newsletter'=>'Newsletter & Webshop','package_insert'=>'Package Insert','co_branded'=>'Co-Branded'];
                echo $models_t[$partner->partnership_model] ?? $partner->partnership_model;
            ?></strong></div>
            <div><span style="color:#94a3b8"><?php echo $t['acc_status']; ?>:</span>
                <span class="pd-badge <?php echo $partner->status === 'active' ? 'pd-badge-premium' : 'pd-badge-free'; ?>"><?php echo esc_html(ucfirst($partner->status)); ?></span>
            </div>
            <div><span style="color:#94a3b8"><?php echo $t['acc_since']; ?>:</span> <strong><?php echo date_i18n($lang === 'de' ? 'd.m.Y' : 'M j, Y', strtotime($partner->created_at)); ?></strong></div>
            <div><span style="color:#94a3b8"><?php echo $t['acc_code']; ?>:</span> <strong style="font-family:monospace;color:#667eea"><?php echo esc_html($partner->partner_code); ?></strong></div>
        </div>
    </div>
</div>

<div class="pd-footer">
    <?php echo $t['powered_by']; ?> <a href="https://punktepass.de" target="_blank">PunktePass</a> &middot;
    <a href="mailto:info@punktepass.de"><?php echo $t['contact']; ?></a>
</div>

<script>
function copyRefLink(el) {
    var text = el.querySelector('span').textContent;
    navigator.clipboard.writeText(text);
    var icon = el.querySelector('.copy-icon');
    icon.className = 'ri-check-line copy-icon';
    icon.style.color = '#22c55e';
    setTimeout(function() {
        icon.className = 'ri-file-copy-line copy-icon';
        icon.style.color = '#0369a1';
    }, 2000);
}
</script>
</body></html>
        <?php
        echo ob_get_clean();
        exit;
    }

    /**
     * Render error page
     */
    private static function render_error($title_de, $msg_de, $title_en, $msg_en) {
        $lang = sanitize_text_field($_GET['lang'] ?? 'de');
        $title = $lang === 'en' ? $title_en : $title_de;
        $msg = $lang === 'en' ? $msg_en : $msg_de;
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . $title . '</title>';
        echo '<style>body{font-family:-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f1f5f9;color:#0f172a}';
        echo '.box{text-align:center;padding:40px}.box h1{font-size:24px;margin-bottom:8px}.box p{color:#64748b}</style></head>';
        echo '<body><div class="box"><h1>' . $title . '</h1><p>' . $msg . '</p></div></body></html>';
        exit;
    }

    /**
     * Get translations for the dashboard
     */
    private static function get_translations($lang) {
        $translations = [
            'de' => [
                'page_title' => 'Partner-Dashboard',
                'stat_shops' => 'Registrierte Shops',
                'stat_forms' => 'Formulare gesamt',
                'stat_premium' => 'Premium-Kunden',
                'stat_commission' => 'Gesch. Provision',
                'ref_title' => 'Ihr Empfehlungslink',
                'ref_desc' => 'Teilen Sie diesen Link mit potenziellen Kunden. Jede Registrierung wird automatisch Ihrem Partner-Konto zugeordnet.',
                'shops_title' => 'Empfohlene Shops',
                'col_shop' => 'Shop',
                'col_city' => 'Stadt',
                'col_forms' => 'Formulare',
                'col_plan' => 'Plan',
                'col_registered' => 'Registriert',
                'no_shops' => 'Noch keine Shops registriert',
                'no_shops_sub' => 'Teilen Sie Ihren Empfehlungslink, um Shops einzuladen.',
                'commission_title' => 'Provision',
                'your_rate' => 'Ihr Satz',
                'monthly_est' => 'Monatliche Sch\u00e4tzung',
                'commission_note' => 'Berechnung basiert auf aktiven Premium-Kunden (\u20ac39/Monat) &times; Ihrem Provisionssatz. Die tats\u00e4chliche Auszahlung erfolgt nach Vereinbarung.',
                'account_title' => 'Ihre Partnerdaten',
                'acc_model' => 'Modell',
                'acc_status' => 'Status',
                'acc_since' => 'Partner seit',
                'acc_code' => 'Partner-Code',
                'powered_by' => 'Betrieben von',
                'contact' => 'Kontakt',
            ],
            'en' => [
                'page_title' => 'Partner Dashboard',
                'stat_shops' => 'Registered Shops',
                'stat_forms' => 'Total Forms',
                'stat_premium' => 'Premium Customers',
                'stat_commission' => 'Est. Commission',
                'ref_title' => 'Your Referral Link',
                'ref_desc' => 'Share this link with potential customers. Every registration is automatically attributed to your partner account.',
                'shops_title' => 'Referred Shops',
                'col_shop' => 'Shop',
                'col_city' => 'City',
                'col_forms' => 'Forms',
                'col_plan' => 'Plan',
                'col_registered' => 'Registered',
                'no_shops' => 'No shops registered yet',
                'no_shops_sub' => 'Share your referral link to invite shops.',
                'commission_title' => 'Commission',
                'your_rate' => 'Your Rate',
                'monthly_est' => 'Monthly Estimate',
                'commission_note' => 'Calculation based on active Premium customers (\u20ac39/month) \u00d7 your commission rate. Actual payouts per agreement.',
                'account_title' => 'Your Partner Details',
                'acc_model' => 'Model',
                'acc_status' => 'Status',
                'acc_since' => 'Partner since',
                'acc_code' => 'Partner Code',
                'powered_by' => 'Powered by',
                'contact' => 'Contact',
            ]
        ];
        return $translations[$lang] ?? $translations['de'];
    }
}
