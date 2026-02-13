<?php
/**
 * PunktePass - Partner Admin Management
 * CRUD for partners (wholesalers/distributors)
 * Route: /formular/admin/partners
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Repair_Partner_Admin {

    /**
     * Check session-based auth (same as repair admin)
     */
    private static function check_auth() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? $_GET['nonce'] ?? '', 'ppv_partner_admin')) {
            return false;
        }
        // Must be logged in via repair admin session
        return PPV_Repair_Core::is_repair_admin_logged_in();
    }

    /**
     * Get partner by ID
     */
    public static function get_partner($partner_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_partners WHERE id = %d",
            $partner_id
        ));
    }

    /**
     * Get partner by code
     */
    public static function get_partner_by_code($code) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_partners WHERE partner_code = %s AND status = 'active'",
            $code
        ));
    }

    /**
     * Generate unique partner code (e.g., PP-AB12CD)
     */
    public static function generate_partner_code() {
        global $wpdb;
        $prefix = 'PP-';
        do {
            $code = $prefix . strtoupper(wp_generate_password(6, false, false));
        } while ($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_partners WHERE partner_code = %s", $code
        )));
        return $code;
    }

    /**
     * Get referred stores count for a partner
     */
    public static function get_referred_stores_count($partner_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE partner_id = %d",
            $partner_id
        ));
    }

    /**
     * Get referred stores list for a partner
     */
    public static function get_referred_stores($partner_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, repair_company_name, email, city, created_at, repair_form_count, repair_premium
             FROM {$wpdb->prefix}ppv_stores
             WHERE partner_id = %d
             ORDER BY created_at DESC",
            $partner_id
        ));
    }

    /**
     * AJAX: List all partners (admin only)
     */
    public static function ajax_list_partners() {
        if (!self::check_auth()) {
            wp_send_json_error(['message' => 'Nicht autorisiert']);
        }

        global $wpdb;
        $partners = $wpdb->get_results("
            SELECT p.*,
                (SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores s WHERE s.partner_id = p.id) as referred_stores,
                (SELECT SUM(s2.repair_form_count) FROM {$wpdb->prefix}ppv_stores s2 WHERE s2.partner_id = p.id) as total_forms
            FROM {$wpdb->prefix}ppv_partners p
            ORDER BY p.created_at DESC
        ");

        wp_send_json_success(['partners' => $partners]);
    }

    /**
     * AJAX: Create new partner
     */
    public static function ajax_create_partner() {
        if (!self::check_auth()) {
            wp_send_json_error(['message' => 'Nicht autorisiert']);
        }

        global $wpdb;

        $company_name = sanitize_text_field($_POST['company_name'] ?? '');
        $contact_name = sanitize_text_field($_POST['contact_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $website = esc_url_raw($_POST['website'] ?? '');
        $address = sanitize_text_field($_POST['address'] ?? '');
        $plz = sanitize_text_field($_POST['plz'] ?? '');
        $city = sanitize_text_field($_POST['city'] ?? '');
        $country = sanitize_text_field($_POST['country'] ?? 'DE');
        $partnership_model = sanitize_text_field($_POST['partnership_model'] ?? 'package_insert');
        $commission_rate = floatval($_POST['commission_rate'] ?? 0);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        if (empty($company_name) || empty($email)) {
            wp_send_json_error(['message' => 'Firmenname und E-Mail sind Pflichtfelder']);
        }

        $partner_code = self::generate_partner_code();

        // Handle logo upload
        $logo_url = '';
        if (!empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logo_url = self::handle_logo_upload($_FILES['logo']);
        }

        $valid_models = ['newsletter', 'package_insert', 'co_branded'];
        if (!in_array($partnership_model, $valid_models)) $partnership_model = 'package_insert';

        $wpdb->insert("{$wpdb->prefix}ppv_partners", [
            'partner_code'     => $partner_code,
            'company_name'     => $company_name,
            'contact_name'     => $contact_name,
            'email'            => $email,
            'phone'            => $phone,
            'website'          => $website,
            'logo_url'         => $logo_url,
            'address'          => $address,
            'plz'              => $plz,
            'city'             => $city,
            'country'          => $country,
            'partnership_model' => $partnership_model,
            'commission_rate'  => $commission_rate,
            'status'           => 'active',
            'notes'            => $notes,
        ]);

        $partner_id = $wpdb->insert_id;
        if (!$partner_id) {
            wp_send_json_error(['message' => 'Partner konnte nicht erstellt werden']);
        }

        wp_send_json_success([
            'partner_id' => $partner_id,
            'partner_code' => $partner_code,
            'message' => 'Partner erfolgreich erstellt',
        ]);
    }

    /**
     * AJAX: Update partner
     */
    public static function ajax_update_partner() {
        if (!self::check_auth()) {
            wp_send_json_error(['message' => 'Nicht autorisiert']);
        }

        global $wpdb;
        $partner_id = intval($_POST['partner_id'] ?? 0);
        if (!$partner_id) wp_send_json_error(['message' => 'Ungültige Partner-ID']);

        $update = [];
        $fields = ['company_name', 'contact_name', 'phone', 'website', 'address', 'plz', 'city', 'country', 'partnership_model', 'notes', 'status'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                $update[$f] = sanitize_text_field($_POST[$f]);
            }
        }
        if (isset($_POST['email'])) $update['email'] = sanitize_email($_POST['email']);
        if (isset($_POST['commission_rate'])) $update['commission_rate'] = floatval($_POST['commission_rate']);

        // Handle logo upload
        if (!empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $update['logo_url'] = self::handle_logo_upload($_FILES['logo']);
        }

        if (!empty($update)) {
            $wpdb->update("{$wpdb->prefix}ppv_partners", $update, ['id' => $partner_id]);
        }

        wp_send_json_success(['message' => 'Partner aktualisiert']);
    }

    /**
     * AJAX: Delete partner
     */
    public static function ajax_delete_partner() {
        if (!self::check_auth()) {
            wp_send_json_error(['message' => 'Nicht autorisiert']);
        }

        global $wpdb;
        $partner_id = intval($_POST['partner_id'] ?? 0);
        if (!$partner_id) wp_send_json_error(['message' => 'Ungültige Partner-ID']);

        // Unlink stores from partner (don't delete stores)
        $wpdb->update("{$wpdb->prefix}ppv_stores", ['partner_id' => null], ['partner_id' => $partner_id]);

        $wpdb->delete("{$wpdb->prefix}ppv_partners", ['id' => $partner_id]);

        wp_send_json_success(['message' => 'Partner gelöscht']);
    }

    /**
     * AJAX: Get partner details with referred stores
     */
    public static function ajax_get_partner() {
        if (!self::check_auth()) {
            wp_send_json_error(['message' => 'Nicht autorisiert']);
        }

        $partner_id = intval($_POST['partner_id'] ?? $_GET['partner_id'] ?? 0);
        if (!$partner_id) wp_send_json_error(['message' => 'Ungültige Partner-ID']);

        $partner = self::get_partner($partner_id);
        if (!$partner) wp_send_json_error(['message' => 'Partner nicht gefunden']);

        $stores = self::get_referred_stores($partner_id);

        // Generate dashboard token for this partner
        $partner_data = (array) $partner;
        $partner_data['_dashboard_token'] = hash_hmac('sha256', $partner->partner_code, wp_salt('auth'));

        wp_send_json_success([
            'partner' => $partner_data,
            'stores' => $stores,
        ]);
    }

    /**
     * Handle partner logo upload
     */
    private static function handle_logo_upload($file) {
        $upload_dir = wp_upload_dir();
        $partner_dir = $upload_dir['basedir'] . '/ppv_partners';
        if (!file_exists($partner_dir)) wp_mkdir_p($partner_dir);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
        if (!in_array($ext, $allowed)) return '';

        $filename = 'partner-' . uniqid() . '.' . $ext;
        $filepath = $partner_dir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return $upload_dir['baseurl'] . '/ppv_partners/' . $filename;
        }
        return '';
    }

    /**
     * Render standalone admin page for partner management
     */
    public static function render_standalone() {
        // Auth is checked in route handler (PPV_Repair_Core::is_repair_admin_logged_in())
        $nonce = wp_create_nonce('ppv_partner_admin');
        $ajax_url = admin_url('admin-ajax.php');

        ob_start();
        ?>
<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Partner-Verwaltung - PunktePass</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Inter,-apple-system,sans-serif;background:#f1f5f9;color:#0f172a;min-height:100vh}
.pp-header{background:linear-gradient(135deg,#667eea,#4338ca);padding:20px 32px;color:#fff;display:flex;align-items:center;gap:16px}
.pp-header h1{font-size:20px;font-weight:700}
.pp-header a{color:rgba(255,255,255,0.8);text-decoration:none;font-size:14px}
.pp-header a:hover{color:#fff}
.pp-body{max-width:1200px;margin:0 auto;padding:24px}
.pp-card{background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08);padding:24px;margin-bottom:20px}
.pp-card h2{font-size:18px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.pp-btn{padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-size:14px;font-weight:600;font-family:inherit;transition:all .2s}
.pp-btn-primary{background:linear-gradient(135deg,#667eea,#4338ca);color:#fff}
.pp-btn-primary:hover{opacity:0.9;transform:translateY(-1px)}
.pp-btn-sm{padding:6px 14px;font-size:13px;border-radius:6px}
.pp-btn-danger{background:#ef4444;color:#fff}
.pp-btn-ghost{background:transparent;color:#667eea;border:1px solid #e2e8f0}
.pp-btn-ghost:hover{background:#f8fafc}
.pp-table{width:100%;border-collapse:collapse}
.pp-table th{text-align:left;padding:10px 12px;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #e2e8f0}
.pp-table td{padding:12px;border-bottom:1px solid #f1f5f9;font-size:14px;vertical-align:middle}
.pp-table tr:hover td{background:#f8fafc}
.pp-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600}
.pp-badge-active{background:#dcfce7;color:#166534}
.pp-badge-inactive{background:#fee2e2;color:#991b1b}
.pp-badge-pending{background:#fef3c7;color:#92400e}
.pp-partner-logo{width:36px;height:36px;border-radius:8px;object-fit:contain;background:#f8fafc;border:1px solid #e2e8f0}
.pp-stat{text-align:center;padding:16px}
.pp-stat-num{font-size:28px;font-weight:800;color:#667eea}
.pp-stat-label{font-size:12px;color:#64748b;margin-top:4px}
.pp-stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px}
.pp-code{font-family:monospace;background:#f1f5f9;padding:4px 10px;border-radius:6px;font-size:13px;font-weight:600;color:#4338ca;display:inline-flex;align-items:center;gap:6px;cursor:pointer}
.pp-code:hover{background:#e2e8f0}
.pp-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;display:none;align-items:center;justify-content:center}
.pp-modal-overlay.active{display:flex}
.pp-modal{background:#fff;border-radius:16px;padding:28px;width:90%;max-width:600px;max-height:90vh;overflow-y:auto}
.pp-modal h3{font-size:18px;font-weight:700;margin-bottom:20px}
.pp-form-row{margin-bottom:14px}
.pp-form-row label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:4px}
.pp-form-row input,.pp-form-row select,.pp-form-row textarea{width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;outline:none;transition:border-color .2s}
.pp-form-row input:focus,.pp-form-row select:focus,.pp-form-row textarea:focus{border-color:#667eea}
.pp-form-row textarea{resize:vertical;min-height:70px}
.pp-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
.pp-ref-url{background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:12px;font-size:13px;word-break:break-all}
.pp-ref-url strong{color:#0369a1}
.pp-empty{text-align:center;padding:40px;color:#94a3b8}
.pp-empty i{font-size:48px;margin-bottom:12px;display:block}
.pp-btn-widget{background:linear-gradient(135deg,#10b981,#059669);color:#fff}
.pp-btn-widget:hover{opacity:0.9;transform:translateY(-1px)}
/* Widget Configurator */
.pp-wc-layout{display:grid;grid-template-columns:280px 1fr;gap:24px;margin-bottom:20px}
.pp-wc-controls{display:flex;flex-direction:column;gap:14px}
.pp-wc-ctrl label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px}
.pp-wc-ctrl select,.pp-wc-ctrl input[type="color"],.pp-wc-ctrl input[type="text"]{width:100%;padding:10px 12px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;outline:none;transition:border-color .2s;background:#fff}
.pp-wc-ctrl select:focus,.pp-wc-ctrl input:focus{border-color:#667eea}
.pp-wc-ctrl input[type="color"]{height:44px;padding:4px;cursor:pointer}
.pp-wc-color-row{display:flex;gap:8px;align-items:center}
.pp-wc-color-row input[type="color"]{width:44px;height:44px;flex-shrink:0;border-radius:8px;border:2px solid #e2e8f0;padding:2px;cursor:pointer}
.pp-wc-color-row input[type="text"]{flex:1}
.pp-wc-preview-wrap{background:#f8fafc;border:2px dashed #e2e8f0;border-radius:12px;min-height:340px;position:relative;overflow:hidden;display:flex;align-items:flex-end;justify-content:flex-end;padding:16px}
.pp-wc-preview-wrap.pos-left{justify-content:flex-start}
.pp-wc-preview-label{position:absolute;top:12px;left:12px;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px}
/* Preview float FAB */
.pp-wc-fab{display:flex;align-items:center;gap:8px;color:#fff;border:none;padding:12px 20px;border-radius:50px;cursor:default;font-size:14px;font-weight:600;font-family:Inter,sans-serif;box-shadow:0 4px 24px rgba(0,0,0,0.18);line-height:1}
.pp-wc-fab svg{width:20px;height:20px;flex-shrink:0}
/* Preview panel */
.pp-wc-panel{position:absolute;bottom:56px;width:320px;background:#fff;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,0.15);overflow:hidden;font-family:Inter,sans-serif}
.pp-wc-panel.pos-right{right:16px}
.pp-wc-panel.pos-left{left:16px}
.pp-wc-panel-header{padding:20px;color:#fff;position:relative;overflow:hidden}
.pp-wc-panel-header::after{content:"";position:absolute;top:-20px;right:-20px;width:80px;height:80px;background:rgba(255,255,255,0.08);border-radius:50%}
.pp-wc-panel-title{font-size:16px;font-weight:700;margin-bottom:4px;position:relative;z-index:1}
.pp-wc-panel-sub{font-size:12px;opacity:0.85;line-height:1.4;position:relative;z-index:1}
.pp-wc-panel-free{display:inline-block;background:rgba(255,255,255,0.2);color:#fff;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;margin-top:6px;text-transform:uppercase;position:relative;z-index:1}
.pp-wc-panel-body{padding:16px 20px}
.pp-wc-panel-feats{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:14px}
.pp-wc-panel-feat{display:flex;align-items:center;gap:6px;font-size:11px;color:#374151;font-weight:500}
.pp-wc-panel-feat span{font-size:14px}
.pp-wc-panel-cta{display:block;width:100%;padding:10px;border:none;border-radius:10px;cursor:default;font-size:13px;font-weight:700;font-family:inherit;text-align:center;color:#fff}
.pp-wc-panel-footer{padding:8px 20px;border-top:1px solid #f1f5f9;text-align:center;font-size:10px;color:#94a3b8}
/* Preview inline banner */
.pp-wc-inline{width:100%;max-width:400px;background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08);overflow:hidden;font-family:Inter,sans-serif}
.pp-wc-inline-header{padding:16px 20px;color:#fff;position:relative;overflow:hidden}
.pp-wc-inline-header::after{content:"";position:absolute;top:-20px;right:-20px;width:80px;height:80px;background:rgba(255,255,255,0.08);border-radius:50%}
.pp-wc-inline .pp-wc-panel-body{padding:14px 20px}
.pp-wc-inline .pp-wc-panel-footer{padding:8px 20px;border-top:1px solid #f1f5f9;text-align:center;font-size:10px;color:#94a3b8}
/* Code output */
.pp-wc-code-wrap{background:#1e293b;border-radius:10px;padding:16px;position:relative}
.pp-wc-code{color:#e2e8f0;font-size:13px;font-family:'Courier New',monospace;word-break:break-all;line-height:1.6;white-space:pre-wrap}
.pp-wc-code .attr{color:#7dd3fc}
.pp-wc-code .val{color:#86efac}
.pp-wc-code .tag{color:#fbbf24}
.pp-wc-copy-btn{position:absolute;top:10px;right:10px;background:#334155;border:none;color:#94a3b8;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:12px;font-family:inherit;font-weight:600;transition:all .2s;display:flex;align-items:center;gap:4px}
.pp-wc-copy-btn:hover{background:#475569;color:#fff}
@media(max-width:768px){.pp-stats-row{grid-template-columns:1fr 1fr}.pp-form-grid{grid-template-columns:1fr}.pp-body{padding:16px}.pp-wc-layout{grid-template-columns:1fr}.pp-wc-preview-wrap{min-height:280px}}
</style></head><body>

<div class="pp-header">
    <a href="/formular/admin" title="Zurück">&larr;</a>
    <h1><i class="ri-handshake-line"></i> Partner-Verwaltung</h1>
    <div style="flex:1"></div>
    <a href="/wp-admin">WP Admin</a>
</div>

<div class="pp-body">
    <div class="pp-stats-row" id="pp-stats">
        <div class="pp-card pp-stat"><div class="pp-stat-num" id="stat-total">-</div><div class="pp-stat-label">Partner gesamt</div></div>
        <div class="pp-card pp-stat"><div class="pp-stat-num" id="stat-active">-</div><div class="pp-stat-label">Aktive Partner</div></div>
        <div class="pp-card pp-stat"><div class="pp-stat-num" id="stat-stores">-</div><div class="pp-stat-label">Verwiesene Shops</div></div>
        <div class="pp-card pp-stat"><div class="pp-stat-num" id="stat-forms">-</div><div class="pp-stat-label">Gesamt-Formulare</div></div>
    </div>

    <div class="pp-card">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
            <h2 style="margin:0;flex:1"><i class="ri-team-line"></i> Partner</h2>
            <button class="pp-btn pp-btn-primary" onclick="openCreateModal()"><i class="ri-add-line"></i> Neuer Partner</button>
        </div>
        <div id="pp-partners-list"></div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div class="pp-modal-overlay" id="pp-modal">
<div class="pp-modal">
    <h3 id="modal-title">Neuer Partner</h3>
    <form id="pp-partner-form" enctype="multipart/form-data">
        <input type="hidden" id="pf-id" value="">
        <div class="pp-form-grid">
            <div class="pp-form-row"><label>Firmenname *</label><input type="text" id="pf-company" required></div>
            <div class="pp-form-row"><label>Ansprechpartner</label><input type="text" id="pf-contact"></div>
            <div class="pp-form-row"><label>E-Mail *</label><input type="email" id="pf-email" required></div>
            <div class="pp-form-row"><label>Telefon</label><input type="text" id="pf-phone"></div>
            <div class="pp-form-row"><label>Website</label><input type="url" id="pf-website" placeholder="https://"></div>
            <div class="pp-form-row"><label>Logo</label><input type="file" id="pf-logo" accept="image/*"></div>
            <div class="pp-form-row"><label>Adresse</label><input type="text" id="pf-address"></div>
            <div class="pp-form-row"><label>PLZ / Stadt</label><div style="display:flex;gap:8px"><input type="text" id="pf-plz" style="width:80px" placeholder="PLZ"><input type="text" id="pf-city" placeholder="Stadt"></div></div>
            <div class="pp-form-row"><label>Partnerschaft-Modell</label>
                <select id="pf-model">
                    <option value="newsletter">Newsletter &amp; Webshop</option>
                    <option value="package_insert" selected>Paketbeilage</option>
                    <option value="co_branded">Co-Branded (Exklusiv)</option>
                </select>
            </div>
            <div class="pp-form-row"><label>Provision (%)</label><input type="number" id="pf-commission" min="0" max="100" step="0.5" value="0"></div>
        </div>
        <div class="pp-form-row"><label>Notizen</label><textarea id="pf-notes" rows="3"></textarea></div>
        <div style="display:flex;gap:10px;margin-top:16px">
            <button type="submit" class="pp-btn pp-btn-primary"><i class="ri-save-line"></i> Speichern</button>
            <button type="button" class="pp-btn pp-btn-ghost" onclick="closeModal()">Abbrechen</button>
        </div>
    </form>
</div>
</div>

<!-- Detail Modal -->
<div class="pp-modal-overlay" id="pp-detail-modal">
<div class="pp-modal" style="max-width:700px">
    <div id="pp-detail-content"></div>
    <div style="margin-top:16px"><button class="pp-btn pp-btn-ghost" onclick="closeDetailModal()">Schließen</button></div>
</div>
</div>

<!-- Widget Configurator Modal -->
<div class="pp-modal-overlay" id="pp-widget-modal">
<div class="pp-modal" style="max-width:860px;padding:0;overflow:hidden">
    <div style="background:linear-gradient(135deg,#10b981,#059669);padding:20px 28px;color:#fff;display:flex;align-items:center;gap:12px">
        <i class="ri-code-s-slash-line" style="font-size:22px"></i>
        <div>
            <h3 style="margin:0;font-size:18px;font-weight:700">Widget-Konfigurator</h3>
            <div id="wc-partner-label" style="font-size:13px;opacity:0.85;margin-top:2px"></div>
        </div>
        <div style="flex:1"></div>
        <button onclick="closeWidgetModal()" style="background:rgba(255,255,255,0.15);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center">&times;</button>
    </div>
    <div style="padding:24px 28px">
        <input type="hidden" id="wc-partner-code" value="">

        <div class="pp-wc-layout">
            <!-- Controls -->
            <div class="pp-wc-controls">
                <div class="pp-wc-ctrl">
                    <label><i class="ri-layout-4-line"></i> Modus</label>
                    <select id="wc-mode" onchange="updateWidgetPreview()">
                        <option value="float">Float (Schwebender Button)</option>
                        <option value="inline">Inline (Banner)</option>
                    </select>
                </div>
                <div class="pp-wc-ctrl">
                    <label><i class="ri-translate-2"></i> Sprache</label>
                    <select id="wc-lang" onchange="updateWidgetPreview()">
                        <option value="de">Deutsch</option>
                        <option value="en">English</option>
                    </select>
                </div>
                <div class="pp-wc-ctrl" id="wc-position-ctrl">
                    <label><i class="ri-layout-bottom-line"></i> Position</label>
                    <select id="wc-position" onchange="updateWidgetPreview()">
                        <option value="bottom-right">Unten rechts</option>
                        <option value="bottom-left">Unten links</option>
                    </select>
                </div>
                <div class="pp-wc-ctrl">
                    <label><i class="ri-palette-line"></i> Farbe</label>
                    <div class="pp-wc-color-row">
                        <input type="color" id="wc-color" value="#667eea" onchange="document.getElementById('wc-color-hex').value=this.value;updateWidgetPreview()">
                        <input type="text" id="wc-color-hex" value="#667eea" maxlength="7" placeholder="#667eea" oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)){document.getElementById('wc-color').value=this.value;updateWidgetPreview()}">
                    </div>
                </div>
                <div style="margin-top:auto;padding-top:10px;border-top:1px solid #e2e8f0">
                    <div style="font-size:11px;color:#64748b;display:flex;flex-direction:column;gap:4px">
                        <div><i class="ri-checkbox-circle-line" style="color:#22c55e"></i> Keine Dependencies</div>
                        <div><i class="ri-checkbox-circle-line" style="color:#22c55e"></i> DSGVO-konform</div>
                        <div><i class="ri-checkbox-circle-line" style="color:#22c55e"></i> &lt; 5 KB</div>
                        <div><i class="ri-checkbox-circle-line" style="color:#22c55e"></i> Cross-Domain CORS</div>
                    </div>
                </div>
            </div>

            <!-- Live Preview -->
            <div class="pp-wc-preview-wrap" id="wc-preview-area">
                <span class="pp-wc-preview-label"><i class="ri-eye-line"></i> Vorschau</span>
                <div id="wc-preview-content"></div>
            </div>
        </div>

        <!-- Generated Code -->
        <div style="margin-top:4px">
            <label style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px"><i class="ri-code-s-slash-line"></i> Embed-Code</label>
            <div class="pp-wc-code-wrap">
                <button class="pp-wc-copy-btn" onclick="copyGeneratedCode(this)"><i class="ri-file-copy-line"></i> Kopieren</button>
                <div class="pp-wc-code" id="wc-generated-code"></div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
var AJAX = <?php echo json_encode($ajax_url); ?>;
var NONCE = <?php echo json_encode($nonce); ?>;

function loadPartners() {
    var data = new FormData();
    data.append("action", "ppv_partner_list");
    data.append("nonce", NONCE);
    fetch(AJAX, {method:"POST", body:data}).then(function(r){return r.json()}).then(function(res) {
        if (!res.success) return;
        var partners = res.data.partners || [];
        var totalStores = 0, totalForms = 0, active = 0;
        partners.forEach(function(p) {
            totalStores += parseInt(p.referred_stores || 0);
            totalForms += parseInt(p.total_forms || 0);
            if (p.status === "active") active++;
        });
        document.getElementById("stat-total").textContent = partners.length;
        document.getElementById("stat-active").textContent = active;
        document.getElementById("stat-stores").textContent = totalStores;
        document.getElementById("stat-forms").textContent = totalForms;

        if (!partners.length) {
            document.getElementById("pp-partners-list").innerHTML = '<div class="pp-empty"><i class="ri-handshake-line"></i><p>Noch keine Partner vorhanden</p><p style="font-size:13px;margin-top:8px">Erstellen Sie Ihren ersten Partner mit dem Button oben</p></div>';
            return;
        }
        var html = '<table class="pp-table"><thead><tr><th></th><th>Partner</th><th>Code</th><th>Modell</th><th>Shops</th><th>Formulare</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>';
        partners.forEach(function(p) {
            var logo = p.logo_url ? '<img src="'+p.logo_url+'" class="pp-partner-logo">' : '<div class="pp-partner-logo" style="display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:#667eea">'+p.company_name.charAt(0)+'</div>';
            var badge = p.status === "active" ? "pp-badge-active" : (p.status === "pending" ? "pp-badge-pending" : "pp-badge-inactive");
            var model = {newsletter:"Newsletter",package_insert:"Paketbeilage",co_branded:"Co-Branded"};
            html += "<tr><td>"+logo+"</td>";
            html += '<td><strong>'+p.company_name+'</strong><br><span style="font-size:12px;color:#64748b">'+(p.contact_name || "")+'</span></td>';
            html += '<td><span class="pp-code" onclick="copyCode(this,\''+p.partner_code+'\')" title="Klicken zum Kopieren">'+p.partner_code+' <i class="ri-file-copy-line" style="font-size:12px"></i></span></td>';
            html += "<td>"+(model[p.partnership_model] || p.partnership_model)+"</td>";
            html += '<td style="font-weight:600">'+(p.referred_stores || 0)+"</td>";
            html += "<td>"+(p.total_forms || 0)+"</td>";
            html += '<td><span class="pp-badge '+badge+'">'+p.status+"</span></td>";
            html += '<td><button class="pp-btn pp-btn-sm pp-btn-ghost" onclick="viewPartner('+p.id+')"><i class="ri-eye-line"></i></button> ';
            html += '<button class="pp-btn pp-btn-sm pp-btn-widget" onclick="openWidgetConfigurator(\''+p.partner_code+'\',\''+p.company_name.replace(/'/g, "\\'")+'\')"><i class="ri-code-s-slash-line"></i></button> ';
            html += '<button class="pp-btn pp-btn-sm pp-btn-ghost" onclick="editPartner('+p.id+')"><i class="ri-pencil-line"></i></button></td></tr>';
        });
        html += "</tbody></table>";
        document.getElementById("pp-partners-list").innerHTML = html;
    });
}

function copyCode(el, code) {
    navigator.clipboard.writeText(code);
    el.innerHTML = code + ' <i class="ri-check-line" style="font-size:12px;color:#22c55e"></i>';
    setTimeout(function() {
        el.innerHTML = code + ' <i class="ri-file-copy-line" style="font-size:12px"></i>';
    }, 1500);
}

function copyWidgetCode(btn) {
    var code = btn.getAttribute('data-code');
    navigator.clipboard.writeText(code);
    btn.innerHTML = '<i class="ri-check-line"></i> Copied!';
    btn.style.background = '#22c55e';
    btn.style.color = '#fff';
    setTimeout(function() {
        btn.innerHTML = '<i class="ri-file-copy-line"></i> Copy';
        btn.style.background = '#334155';
        btn.style.color = '#94a3b8';
    }, 2000);
}

// ─── Widget Configurator ─────────────────────────────────
var wcTranslations = {
    de: { badge:'Reparatur-Service', title:'Digitale Reparaturverwaltung', sub:'Reparaturen digital erfassen, Rechnungen erstellen, Kunden informieren.', feat1:'Online-Formular', feat2:'Rechnungen', feat3:'Kundenverwaltung', feat4:'DATEV-Export', cta:'Kostenlos starten', free:'Kostenlos', powered:'Powered by' },
    en: { badge:'Repair Service', title:'Digital Repair Management', sub:'Record repairs digitally, create invoices, keep customers informed.', feat1:'Online Form', feat2:'Invoices', feat3:'Customer Mgmt', feat4:'DATEV Export', cta:'Start for free', free:'Free', powered:'Powered by' }
};

function openWidgetConfigurator(partnerCode, companyName) {
    document.getElementById('wc-partner-code').value = partnerCode;
    document.getElementById('wc-partner-label').textContent = companyName + ' (' + partnerCode + ')';
    // Reset to defaults
    document.getElementById('wc-mode').value = 'float';
    document.getElementById('wc-lang').value = 'de';
    document.getElementById('wc-position').value = 'bottom-right';
    document.getElementById('wc-color').value = '#667eea';
    document.getElementById('wc-color-hex').value = '#667eea';
    document.getElementById('wc-position-ctrl').style.display = '';
    updateWidgetPreview();
    document.getElementById('pp-widget-modal').classList.add('active');
}

function closeWidgetModal() {
    document.getElementById('pp-widget-modal').classList.remove('active');
}

function updateWidgetPreview() {
    var code = document.getElementById('wc-partner-code').value;
    var mode = document.getElementById('wc-mode').value;
    var lang = document.getElementById('wc-lang').value;
    var pos = document.getElementById('wc-position').value;
    var color = document.getElementById('wc-color').value;
    var t = wcTranslations[lang] || wcTranslations.de;

    // Show/hide position control for inline mode
    document.getElementById('wc-position-ctrl').style.display = mode === 'inline' ? 'none' : '';

    var previewArea = document.getElementById('wc-preview-area');
    var previewContent = document.getElementById('wc-preview-content');
    var isLeft = pos === 'bottom-left';

    // Preview area alignment
    previewArea.className = 'pp-wc-preview-wrap' + (isLeft && mode === 'float' ? ' pos-left' : '');

    var gradient = 'linear-gradient(135deg,' + color + ',#4338ca)';
    var html = '';

    if (mode === 'float') {
        // Panel
        html += '<div class="pp-wc-panel ' + (isLeft ? 'pos-left' : 'pos-right') + '">';
        html += '<div class="pp-wc-panel-header" style="background:' + gradient + '">';
        html += '<div class="pp-wc-panel-title">' + t.title + '</div>';
        html += '<div class="pp-wc-panel-sub">' + t.sub + '</div>';
        html += '<span class="pp-wc-panel-free">\u2713 ' + t.free + '</span>';
        html += '</div>';
        html += '<div class="pp-wc-panel-body">';
        html += '<div class="pp-wc-panel-feats">';
        html += '<div class="pp-wc-panel-feat"><span>\uD83D\uDCF1</span> ' + t.feat1 + '</div>';
        html += '<div class="pp-wc-panel-feat"><span>\uD83D\uDCCE</span> ' + t.feat2 + '</div>';
        html += '<div class="pp-wc-panel-feat"><span>\uD83D\uDC65</span> ' + t.feat3 + '</div>';
        html += '<div class="pp-wc-panel-feat"><span>\uD83D\uDCCA</span> ' + t.feat4 + '</div>';
        html += '</div>';
        html += '<div class="pp-wc-panel-cta" style="background:' + gradient + '">' + t.cta + ' \u2192</div>';
        html += '</div>';
        html += '<div class="pp-wc-panel-footer">' + t.powered + ' <strong>PunktePass</strong></div>';
        html += '</div>';
        // FAB
        html += '<div class="pp-wc-fab" style="background:' + gradient + '">';
        html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>';
        html += '<span>' + t.badge + '</span></div>';
    } else {
        // Inline banner
        html += '<div class="pp-wc-inline">';
        html += '<div class="pp-wc-inline-header" style="background:' + gradient + '">';
        html += '<div class="pp-wc-panel-title" style="position:relative;z-index:1">' + t.title + '</div>';
        html += '<div class="pp-wc-panel-sub" style="position:relative;z-index:1">' + t.sub + '</div>';
        html += '</div>';
        html += '<div class="pp-wc-panel-body">';
        html += '<div class="pp-wc-panel-feats">';
        html += '<div class="pp-wc-panel-feat"><span>\uD83D\uDCF1</span> ' + t.feat1 + '</div>';
        html += '<div class="pp-wc-panel-feat"><span>\uD83D\uDCCE</span> ' + t.feat2 + '</div>';
        html += '<div class="pp-wc-panel-feat"><span>\uD83D\uDC65</span> ' + t.feat3 + '</div>';
        html += '<div class="pp-wc-panel-feat"><span>\uD83D\uDCCA</span> ' + t.feat4 + '</div>';
        html += '</div>';
        html += '<div class="pp-wc-panel-cta" style="background:' + gradient + '">' + t.cta + ' \u2192</div>';
        html += '</div>';
        html += '<div class="pp-wc-panel-footer">' + t.powered + ' <strong>PunktePass</strong></div>';
        html += '</div>';
    }

    previewContent.innerHTML = html;

    // Generate embed code (syntax highlighted)
    var attrs = ' <span class="attr">data-partner</span>=<span class="val">"' + code + '"</span>';
    if (lang !== 'de') attrs += '\n  <span class="attr">data-lang</span>=<span class="val">"' + lang + '"</span>';
    if (mode !== 'float') attrs += '\n  <span class="attr">data-mode</span>=<span class="val">"' + mode + '"</span>\n  <span class="attr">data-target</span>=<span class="val">"#punktepass-widget"</span>';
    if (pos !== 'bottom-right' && mode === 'float') attrs += '\n  <span class="attr">data-position</span>=<span class="val">"' + pos + '"</span>';
    if (color !== '#667eea') attrs += '\n  <span class="attr">data-color</span>=<span class="val">"' + color + '"</span>';

    var codeHtml = '';
    if (mode === 'inline') {
        codeHtml += '<span class="tag">&lt;div</span> <span class="attr">id</span>=<span class="val">"punktepass-widget"</span><span class="tag">&gt;&lt;/div&gt;</span>\n';
    }
    codeHtml += '<span class="tag">&lt;script</span>\n  <span class="attr">src</span>=<span class="val">"https://punktepass.de/formular/widget.js"</span>\n  ' + attrs.trim() + '<span class="tag">&gt;&lt;/script&gt;</span>';

    document.getElementById('wc-generated-code').innerHTML = codeHtml;
}

function copyGeneratedCode(btn) {
    // Build raw code from current config
    var code = document.getElementById('wc-partner-code').value;
    var mode = document.getElementById('wc-mode').value;
    var lang = document.getElementById('wc-lang').value;
    var pos = document.getElementById('wc-position').value;
    var color = document.getElementById('wc-color').value;

    var raw = '';
    if (mode === 'inline') {
        raw += '<div id="punktepass-widget"></div>\n';
    }
    raw += '<script src="https://punktepass.de/formular/widget.js"';
    raw += ' data-partner="' + code + '"';
    if (lang !== 'de') raw += ' data-lang="' + lang + '"';
    if (mode !== 'float') raw += ' data-mode="' + mode + '" data-target="#punktepass-widget"';
    if (pos !== 'bottom-right' && mode === 'float') raw += ' data-position="' + pos + '"';
    if (color !== '#667eea') raw += ' data-color="' + color + '"';
    raw += '><\/script>';

    navigator.clipboard.writeText(raw);
    btn.innerHTML = '<i class="ri-check-line"></i> Kopiert!';
    btn.style.background = '#22c55e';
    btn.style.color = '#fff';
    setTimeout(function() {
        btn.innerHTML = '<i class="ri-file-copy-line"></i> Kopieren';
        btn.style.background = '#334155';
        btn.style.color = '#94a3b8';
    }, 2000);
}

function openCreateModal() {
    document.getElementById("modal-title").textContent = "Neuer Partner";
    document.getElementById("pf-id").value = "";
    document.getElementById("pp-partner-form").reset();
    document.getElementById("pp-modal").classList.add("active");
}

function closeModal() {
    document.getElementById("pp-modal").classList.remove("active");
}

function closeDetailModal() {
    document.getElementById("pp-detail-modal").classList.remove("active");
}

function editPartner(id) {
    var data = new FormData();
    data.append("action", "ppv_partner_get");
    data.append("nonce", NONCE);
    data.append("partner_id", id);
    fetch(AJAX, {method:"POST", body:data}).then(function(r){return r.json()}).then(function(res) {
        if (!res.success) return;
        var p = res.data.partner;
        document.getElementById("modal-title").textContent = "Partner bearbeiten: " + p.company_name;
        document.getElementById("pf-id").value = p.id;
        document.getElementById("pf-company").value = p.company_name || "";
        document.getElementById("pf-contact").value = p.contact_name || "";
        document.getElementById("pf-email").value = p.email || "";
        document.getElementById("pf-phone").value = p.phone || "";
        document.getElementById("pf-website").value = p.website || "";
        document.getElementById("pf-address").value = p.address || "";
        document.getElementById("pf-plz").value = p.plz || "";
        document.getElementById("pf-city").value = p.city || "";
        document.getElementById("pf-model").value = p.partnership_model || "package_insert";
        document.getElementById("pf-commission").value = p.commission_rate || 0;
        document.getElementById("pf-notes").value = p.notes || "";
        document.getElementById("pp-modal").classList.add("active");
    });
}

function viewPartner(id) {
    var data = new FormData();
    data.append("action", "ppv_partner_get");
    data.append("nonce", NONCE);
    data.append("partner_id", id);
    fetch(AJAX, {method:"POST", body:data}).then(function(r){return r.json()}).then(function(res) {
        if (!res.success) return;
        var p = res.data.partner;
        var stores = res.data.stores || [];
        var refUrl = "https://punktepass.de/formular?ref=" + p.partner_code;
        var model = {newsletter:"Newsletter & Webshop",package_insert:"Paketbeilage (Empfohlen)",co_branded:"Co-Branded (Exklusiv)"};

        var html = '<div style="display:flex;gap:16px;align-items:center;margin-bottom:20px">';
        if (p.logo_url) html += '<img src="'+p.logo_url+'" style="height:48px;border-radius:8px">';
        html += '<div><h3 style="margin:0">'+p.company_name+'</h3>';
        html += '<span class="pp-code" onclick="copyCode(this,\''+p.partner_code+'\')">'+p.partner_code+' <i class="ri-file-copy-line" style="font-size:12px"></i></span></div></div>';

        html += '<div class="pp-ref-url"><strong>Referral-Link:</strong><br>'+refUrl+'</div>';

        // Dashboard link
        var dashToken = p._dashboard_token || '';
        if (dashToken) {
            var dashUrl = 'https://punktepass.de/formular/partner/dashboard?code=' + p.partner_code + '&token=' + dashToken;
            html += '<div style="margin-top:8px;background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;padding:12px;font-size:13px;word-break:break-all">';
            html += '<strong style="color:#7c3aed"><i class="ri-dashboard-line"></i> Dashboard-Link (f\u00fcr Partner):</strong><br>';
            html += '<span style="color:#6d28d9">' + dashUrl + '</span>';
            html += ' <span class="pp-code" onclick="copyCode(this,\'' + dashUrl + '\')" style="font-size:11px;margin-left:4px">Kopieren <i class="ri-file-copy-line" style="font-size:11px"></i></span>';
            html += '</div>';
        }

        // Embed Widget code section
        var widgetCode = '&lt;script src=&quot;https://punktepass.de/formular/widget.js&quot; data-partner=&quot;'+p.partner_code+'&quot;&gt;&lt;/script&gt;';
        var widgetCodeRaw = '<script src="https://punktepass.de/formular/widget.js" data-partner="'+p.partner_code+'"><\/script>';
        html += '<div style="margin-top:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px">';
        html += '<strong style="font-size:13px;color:#166534"><i class="ri-code-s-slash-line"></i> Embed Widget</strong>';
        html += '<p style="font-size:12px;color:#4b5563;margin:6px 0 8px">Partner kann diesen Code in seine Website einf&uuml;gen:</p>';
        html += '<div style="background:#1e293b;color:#e2e8f0;border-radius:8px;padding:12px;font-size:12px;font-family:monospace;word-break:break-all;position:relative">';
        html += '<code>'+widgetCode+'</code>';
        html += '<button onclick="copyWidgetCode(this)" data-code="'+widgetCodeRaw.replace(/"/g, '&quot;')+'" style="position:absolute;top:8px;right:8px;background:#334155;border:none;color:#94a3b8;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:11px;font-family:inherit"><i class="ri-file-copy-line"></i> Copy</button>';
        html += '</div>';
        html += '<div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">';
        html += '<span style="font-size:11px;background:#e0e7ff;color:#3730a3;padding:2px 8px;border-radius:4px">Float (Standard)</span>';
        html += '<span style="font-size:11px;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:4px">+ data-lang=&quot;en&quot;</span>';
        html += '<span style="font-size:11px;background:#fce7f3;color:#9d174d;padding:2px 8px;border-radius:4px">+ data-position=&quot;bottom-left&quot;</span>';
        html += '<span style="font-size:11px;background:#e0f2fe;color:#0c4a6e;padding:2px 8px;border-radius:4px">+ data-mode=&quot;inline&quot; data-target=&quot;#id&quot;</span>';
        html += '</div>';
        html += '</div>';

        html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px;font-size:14px">';
        html += "<div><strong>Kontakt:</strong> "+(p.contact_name || "-")+"</div>";
        html += "<div><strong>E-Mail:</strong> "+(p.email || "-")+"</div>";
        html += "<div><strong>Telefon:</strong> "+(p.phone || "-")+"</div>";
        html += "<div><strong>Modell:</strong> "+(model[p.partnership_model] || p.partnership_model)+"</div>";
        html += "<div><strong>Provision:</strong> "+p.commission_rate+"%</div>";
        html += "<div><strong>Shops:</strong> "+stores.length+"</div>";
        html += "</div>";

        if (stores.length) {
            html += '<h4 style="margin-top:20px;margin-bottom:8px"><i class="ri-store-2-line"></i> Verwiesene Shops ('+stores.length+')</h4>';
            html += '<table class="pp-table"><thead><tr><th>Shop</th><th>Stadt</th><th>Formulare</th><th>Premium</th><th>Registriert</th></tr></thead><tbody>';
            stores.forEach(function(s) {
                html += '<tr><td><strong>'+(s.repair_company_name || s.name)+'</strong><br><span style="font-size:12px;color:#64748b">'+s.email+'</span></td>';
                html += "<td>"+(s.city || "-")+"</td>";
                html += "<td>"+(s.repair_form_count || 0)+"</td>";
                html += "<td>"+(parseInt(s.repair_premium) ? '<span class="pp-badge pp-badge-active">Premium</span>' : "Free")+"</td>";
                html += "<td>"+new Date(s.created_at).toLocaleDateString("de-DE")+"</td></tr>";
            });
            html += "</tbody></table>";
        } else {
            html += '<div style="margin-top:16px;padding:20px;text-align:center;color:#94a3b8;background:#f8fafc;border-radius:8px"><i class="ri-store-2-line" style="font-size:24px"></i><br>Noch keine verwiesenen Shops</div>';
        }

        document.getElementById("pp-detail-content").innerHTML = html;
        document.getElementById("pp-detail-modal").classList.add("active");
    });
}

// Form submit
document.getElementById("pp-partner-form").addEventListener("submit", function(e) {
    e.preventDefault();
    var data = new FormData();
    var id = document.getElementById("pf-id").value;
    data.append("action", id ? "ppv_partner_update" : "ppv_partner_create");
    data.append("nonce", NONCE);
    if (id) data.append("partner_id", id);
    data.append("company_name", document.getElementById("pf-company").value);
    data.append("contact_name", document.getElementById("pf-contact").value);
    data.append("email", document.getElementById("pf-email").value);
    data.append("phone", document.getElementById("pf-phone").value);
    data.append("website", document.getElementById("pf-website").value);
    data.append("address", document.getElementById("pf-address").value);
    data.append("plz", document.getElementById("pf-plz").value);
    data.append("city", document.getElementById("pf-city").value);
    data.append("partnership_model", document.getElementById("pf-model").value);
    data.append("commission_rate", document.getElementById("pf-commission").value);
    data.append("notes", document.getElementById("pf-notes").value);
    var logoFile = document.getElementById("pf-logo").files[0];
    if (logoFile) data.append("logo", logoFile);

    fetch(AJAX, {method:"POST", body:data}).then(function(r){return r.json()}).then(function(res) {
        if (res.success) {
            closeModal();
            loadPartners();
        } else {
            alert(res.data.message || "Fehler");
        }
    });
});

// Init
loadPartners();
</script>
</body></html>
        <?php
        echo ob_get_clean();
        exit;
    }
}
