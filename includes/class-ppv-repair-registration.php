<?php
/**
 * PunktePass - Repair Form Registration Page (Standalone)
 * Renders a complete standalone HTML page at /formular
 * No WordPress shortcode, no WordPress theme
 *
 * Routing is handled by PPV_Repair_Core::handle_routes()
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Repair_Registration {

    /**
     * Render complete standalone HTML registration page
     * Called by PPV_Repair_Core::handle_routes() for /formular
     */
    public static function render_standalone() {
        // If already logged in as repair handler, redirect to admin
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        if (!empty($_SESSION['ppv_repair_store_id'])) {
            header('Location: /formular/admin');
            exit;
        }

        // Load repair-specific translations
        PPV_Lang::load_extra('ppv-repair-lang');
        $lang = PPV_Lang::current();

        $ajax_url = admin_url('admin-ajax.php');
        $nonce    = wp_create_nonce('ppv_repair_register');
        $logo_url = PPV_PLUGIN_URL . 'assets/img/punktepass-repair-logo.svg';

        // JS translation strings for validation
        $js_strings = json_encode([
            'err_shop_name'   => PPV_Lang::t('repair_reg_err_shop_name'),
            'err_owner'       => PPV_Lang::t('repair_reg_err_owner'),
            'err_email'       => PPV_Lang::t('repair_reg_err_email'),
            'err_email_invalid' => PPV_Lang::t('repair_reg_err_email_invalid'),
            'err_password'    => PPV_Lang::t('repair_reg_err_password'),
            'err_password2'   => PPV_Lang::t('repair_reg_err_password2'),
            'err_terms'       => PPV_Lang::t('repair_reg_err_terms'),
            'err_server'      => PPV_Lang::t('repair_reg_err_server'),
            'err_unexpected'  => PPV_Lang::t('repair_reg_err_unexpected'),
            'err_failed'      => PPV_Lang::t('repair_reg_err_failed'),
            'err_network'     => PPV_Lang::t('repair_reg_err_network'),
            'creating'        => PPV_Lang::t('repair_reg_creating'),
            'submit_text'     => PPV_Lang::t('repair_reg_submit'),
            'connection_error'=> PPV_Lang::t('repair_connection_error'),
        ], JSON_UNESCAPED_UNICODE);

        ?><!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(PPV_Lang::t('repair_reg_page_title')); ?></title>
    <?php echo PPV_SEO::get_landing_page_head(); ?>
    <?php echo PPV_SEO::get_performance_hints(); ?>
    <?php echo PPV_SEO::get_favicon_links(); ?>

    <!-- Google Analytics (loads only with consent) -->
    <script>
        function loadGoogleAnalytics() {
            if (localStorage.getItem('cookie_consent') === 'accepted') {
                var s = document.createElement('script');
                s.async = true;
                s.src = 'https://www.googletagmanager.com/gtag/js?id=G-NDVQK1WSG3';
                document.head.appendChild(s);
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                window.gtag = gtag;
                gtag('js', new Date());
                gtag('config', 'G-NDVQK1WSG3');
            }
        }
        loadGoogleAnalytics();
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
    <link rel="preconnect" href="https://accounts.google.com" crossorigin>
    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <style>
        /* ── Reset & Base ── */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { font-size: 16px; -webkit-text-size-adjust: 100%; scroll-behavior: smooth; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            color: #1f2937;
            line-height: 1.6;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        a { color: #667eea; text-decoration: none; }
        a:hover { text-decoration: underline; }
        img { max-width: 100%; height: auto; }

        /* ── Animations ── */
        @keyframes heroFloat {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(30px, -20px) scale(1.05); }
            50% { transform: translate(-15px, 25px) scale(0.95); }
            75% { transform: translate(20px, 10px) scale(1.03); }
        }
        @keyframes heroFloat2 {
            0%, 100% { transform: translate(-50%, -50%) scale(1); }
            33% { transform: translate(-40%, -60%) scale(1.1); }
            66% { transform: translate(-60%, -40%) scale(0.9); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.92); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        @keyframes pp-spin {
            to { transform: rotate(360deg); }
        }
        .pp-fade-in { animation: fadeInUp 0.6s ease-out both; }
        .pp-fade-in-1 { animation-delay: 0.1s; }
        .pp-fade-in-2 { animation-delay: 0.2s; }
        .pp-fade-in-3 { animation-delay: 0.3s; }
        .pp-fade-in-4 { animation-delay: 0.4s; }
        .pp-fade-in-5 { animation-delay: 0.5s; }
        .pp-fade-in-6 { animation-delay: 0.6s; }

        /* ── HERO SECTION ── */
        .pp-hero {
            background: linear-gradient(135deg, #667eea 0%, #5a67d8 40%, #4338ca 70%, #3730a3 100%);
            padding: 56px 24px 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .pp-hero-bg {
            position: absolute;
            inset: 0;
            overflow: hidden;
            z-index: 0;
        }
        .pp-hero-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(70px);
            opacity: 0.3;
        }
        .pp-hero-blob--1 {
            width: 400px;
            height: 400px;
            background: #a78bfa;
            top: -120px;
            right: -100px;
            animation: heroFloat 14s ease-in-out infinite;
        }
        .pp-hero-blob--2 {
            width: 350px;
            height: 350px;
            background: #60a5fa;
            bottom: -80px;
            left: -80px;
            animation: heroFloat 16s ease-in-out infinite -6s;
        }
        .pp-hero-blob--3 {
            width: 250px;
            height: 250px;
            background: #f472b6;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: heroFloat2 18s ease-in-out infinite -3s;
        }
        .pp-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 720px;
            margin: 0 auto;
        }
        .pp-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 100px;
            padding: 8px 20px;
            font-size: 13px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 24px;
            animation: fadeInDown 0.6s ease-out;
        }
        .pp-hero-badge i {
            font-size: 16px;
            color: #fde047;
        }
        .pp-hero h1 {
            color: #fff;
            font-size: 40px;
            font-weight: 900;
            line-height: 1.15;
            margin-bottom: 16px;
            letter-spacing: -1px;
            animation: fadeInUp 0.7s ease-out 0.1s both;
        }
        .pp-hero h1 span {
            background: linear-gradient(135deg, #fde047, #facc15);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .pp-hero-sub {
            color: rgba(255,255,255,0.85);
            font-size: 17px;
            font-weight: 400;
            max-width: 520px;
            margin: 0 auto 28px;
            line-height: 1.6;
            animation: fadeInUp 0.7s ease-out 0.2s both;
        }
        .pp-hero-cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 32px;
            background: #fff;
            color: #4338ca;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            animation: fadeInUp 0.7s ease-out 0.3s both;
        }
        .pp-hero-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
            text-decoration: none;
        }
        .pp-hero-cta i {
            font-size: 18px;
            transition: transform 0.3s;
        }
        .pp-hero-cta:hover i {
            transform: translateX(3px);
        }
        .pp-hero-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 36px;
            animation: fadeInUp 0.7s ease-out 0.4s both;
        }
        .pp-hero-stat {
            text-align: center;
        }
        .pp-hero-stat-val {
            display: block;
            font-size: 28px;
            font-weight: 800;
            color: #fff;
            line-height: 1;
        }
        .pp-hero-stat-label {
            font-size: 12px;
            color: rgba(255,255,255,0.65);
            font-weight: 500;
            margin-top: 4px;
        }

        /* ── FEATURES GRID ── */
        .pp-features-section {
            max-width: 880px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .pp-features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-top: -48px;
            position: relative;
            z-index: 2;
        }
        .pp-feature {
            background: #fff;
            border-radius: 16px;
            padding: 28px 20px;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06), 0 0 0 1px rgba(0,0,0,0.02);
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            cursor: pointer;
        }
        .pp-feature:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 36px rgba(0,0,0,0.1), 0 0 0 1px rgba(0,0,0,0.02);
        }
        .pp-feature:active {
            transform: translateY(-2px);
        }
        .pp-feature-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            margin: 0 auto 14px;
            border-radius: 14px;
            font-size: 24px;
        }
        .pp-feature-icon.blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #3b82f6; }
        .pp-feature-icon.green { background: linear-gradient(135deg, #f0fdf4, #dcfce7); color: #22c55e; }
        .pp-feature-icon.purple { background: linear-gradient(135deg, #f5f3ff, #ede9fe); color: #8b5cf6; }
        .pp-feature-icon.amber { background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #f59e0b; }
        .pp-feature-icon.rose { background: linear-gradient(135deg, #fff1f2, #fce7f3); color: #f43f5e; }
        .pp-feature-icon.teal { background: linear-gradient(135deg, #f0fdfa, #ccfbf1); color: #14b8a6; }
        .pp-feature h3 {
            font-size: 14px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }
        .pp-feature p {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.4;
            margin: 0;
        }
        .pp-feature-more {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #667eea;
            margin-top: 12px;
            opacity: 0;
            transform: translateY(4px);
            transition: all 0.3s;
        }
        .pp-feature-more i { font-size: 14px; transition: transform 0.2s; }
        .pp-feature:hover .pp-feature-more { opacity: 1; transform: translateY(0); }
        .pp-feature:hover .pp-feature-more i { transform: translateX(2px); }

        /* ── FORM SECTION ── */
        .pp-form-section {
            max-width: 600px;
            margin: 48px auto 0;
            padding: 0 20px 48px;
        }
        .pp-form-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .pp-form-header h2 {
            font-size: 26px;
            font-weight: 800;
            color: #1f2937;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }
        .pp-form-header p {
            font-size: 15px;
            color: #6b7280;
        }

        /* ── Form Card ── */
        .pp-reg-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06), 0 0 0 1px rgba(0,0,0,0.02);
            overflow: hidden;
        }
        .pp-reg-form {
            padding: 32px 28px;
        }

        /* ── Form Sections ── */
        .pp-reg-section {
            margin-bottom: 28px;
        }
        .pp-reg-section:last-of-type {
            margin-bottom: 0;
        }
        .pp-reg-section-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f3f4f6;
        }
        .pp-reg-section-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea, #5a67d8);
            color: #fff;
            border-radius: 8px;
            font-size: 15px;
        }
        .pp-reg-section-head h3 {
            font-size: 14px;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        /* ── Fields ── */
        .pp-reg-field {
            margin-bottom: 14px;
        }
        .pp-reg-field:last-child {
            margin-bottom: 0;
        }
        .pp-reg-field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .pp-reg-field input[type="text"],
        .pp-reg-field input[type="email"],
        .pp-reg-field input[type="password"],
        .pp-reg-field input[type="tel"] {
            width: 100%;
            padding: 13px 16px;
            font-size: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            background: #fafbfc;
            color: #1f2937;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            outline: none;
            font-family: inherit;
        }
        .pp-reg-field input:hover {
            border-color: #d1d5db;
        }
        .pp-reg-field input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.12);
            background: #fff;
        }
        .pp-reg-field input::placeholder {
            color: #9ca3af;
        }

        /* ── Row Layout ── */
        .pp-reg-row {
            display: flex;
            gap: 12px;
        }
        .pp-reg-row .pp-reg-field {
            flex: 1;
        }
        .pp-reg-row .pp-reg-field-sm {
            flex: 0 0 100px;
        }

        /* ── Terms ── */
        .pp-reg-terms {
            margin: 24px 0;
            padding: 16px 18px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #f1f5f9;
        }
        .pp-reg-terms label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            color: #4b5563;
            cursor: pointer;
            line-height: 1.5;
        }
        .pp-reg-terms input[type="checkbox"] {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            accent-color: #667eea;
            cursor: pointer;
        }
        .pp-reg-terms a {
            color: #667eea;
            font-weight: 600;
        }

        /* ── Submit Button ── */
        .pp-reg-submit {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 16px 24px;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #667eea 0%, #5a67d8 50%, #4338ca 100%);
            border: none;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            font-family: inherit;
            letter-spacing: 0.3px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }
        .pp-reg-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.5s ease;
        }
        .pp-reg-submit:hover::before {
            left: 100%;
        }
        .pp-reg-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(102, 126, 234, 0.4);
        }
        .pp-reg-submit:active {
            transform: translateY(0);
        }
        .pp-reg-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .pp-reg-submit:disabled::before {
            display: none;
        }
        .pp-reg-submit i {
            font-size: 18px;
        }

        /* ── Error Message ── */
        .pp-reg-error {
            margin-top: 16px;
            padding: 14px 18px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            color: #dc2626;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
        }

        /* ── Success Screen ── */
        .pp-reg-success {
            text-align: center;
            padding: 48px 28px;
        }
        .pp-reg-success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: #fff;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
            animation: scaleIn 0.5s ease-out;
        }
        .pp-reg-success h2 {
            font-size: 24px;
            font-weight: 800;
            color: #059669;
            margin-bottom: 8px;
        }
        .pp-reg-success > p {
            font-size: 15px;
            color: #6b7280;
            margin-bottom: 20px;
        }
        .pp-reg-success-link {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 1.5px solid #bae6fd;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .pp-reg-success-link .pp-reg-link-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #0369a1;
            margin-bottom: 8px;
        }
        .pp-reg-success-link a {
            font-size: 16px;
            font-weight: 600;
            color: #1d4ed8;
            word-break: break-all;
        }
        .pp-reg-success-info {
            font-size: 13px;
            color: #9ca3af;
            margin-bottom: 24px;
        }
        .pp-reg-success-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .pp-reg-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 13px 28px;
            background: linear-gradient(135deg, #667eea, #4338ca);
            color: #fff;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25);
        }
        .pp-reg-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.35);
            text-decoration: none;
        }
        .pp-reg-btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 13px 28px;
            background: #f3f4f6;
            color: #374151;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s;
        }
        .pp-reg-btn-secondary:hover {
            background: #e5e7eb;
            text-decoration: none;
        }

        /* ── Login Link ── */
        .pp-reg-login-link {
            text-align: center;
            padding: 18px;
            font-size: 14px;
            color: #6b7280;
            border-top: 1px solid #f3f4f6;
        }
        .pp-reg-login-link a {
            color: #667eea;
            font-weight: 600;
        }

        /* ── Footer ── */
        .pp-reg-footer {
            text-align: center;
            padding: 32px 20px;
        }
        .pp-reg-footer-trust {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-bottom: 16px;
        }
        .pp-reg-footer-trust-item {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 500;
            color: #10b981;
            background: rgba(16, 185, 129, 0.06);
            padding: 6px 14px;
            border-radius: 100px;
            border: 1px solid rgba(16, 185, 129, 0.15);
        }
        .pp-reg-footer-trust-item i {
            font-size: 13px;
        }
        .pp-reg-footer-links {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        .pp-reg-footer-links a {
            color: #9ca3af;
            font-size: 13px;
            font-weight: 500;
        }
        .pp-reg-footer-links a:hover {
            color: #667eea;
        }
        .pp-reg-footer-dot {
            width: 3px;
            height: 3px;
            border-radius: 50%;
            background: #d1d5db;
        }
        .pp-reg-footer-powered {
            font-size: 12px;
            color: #9ca3af;
        }
        .pp-reg-footer-powered a {
            color: #667eea;
            font-weight: 600;
        }

        /* ── Spinner ── */
        .pp-reg-spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2.5px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: pp-spin 0.6s linear infinite;
            vertical-align: middle;
            margin-right: 8px;
        }

        /* ── Hide utility ── */
        .pp-hidden {
            display: none !important;
        }

        /* ── Language Switcher (footer) ── */
        .pp-lang-btn{font-family:inherit;letter-spacing:0.5px;transition:all .2s}
        .pp-lang-btn:hover{opacity:0.8}

        /* ── FEATURE MODAL ── */
        .pp-feat-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .pp-feat-overlay.active { display: flex; }
        .pp-feat-modal {
            position: relative;
            background: #fff;
            border-radius: 20px;
            max-width: 520px;
            width: 100%;
            max-height: 88vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            animation: scaleIn 0.25s ease;
        }
        .pp-feat-modal-head {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 24px 24px 0;
        }
        .pp-feat-modal-head .pp-feature-icon {
            margin: 0;
            flex-shrink: 0;
            width: 48px;
            height: 48px;
        }
        .pp-feat-modal-head h2 {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }
        .pp-feat-modal-head p {
            font-size: 13px;
            color: #6b7280;
            margin: 2px 0 0;
        }
        .pp-feat-close {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 34px;
            height: 34px;
            border: none;
            background: #f3f4f6;
            border-radius: 50%;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            transition: all 0.2s;
            z-index: 1;
        }
        .pp-feat-close:hover { background: #e5e7eb; color: #1f2937; }
        .pp-feat-body { padding: 20px 24px 24px; }
        .pp-feat-body[hidden] { display: none; }

        /* Mockup window */
        .pp-mockup {
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .pp-mockup-bar {
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .pp-mockup-bar span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
        }
        .pp-mockup-bar span:nth-child(1) { background: #fca5a5; }
        .pp-mockup-bar span:nth-child(2) { background: #fcd34d; }
        .pp-mockup-bar span:nth-child(3) { background: #86efac; }
        .pp-mockup-inner {
            padding: 16px;
        }

        /* Mockup: form fields */
        .pp-mock-field {
            margin-bottom: 8px;
        }
        .pp-mock-field label {
            display: block;
            font-size: 10px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .pp-mock-field .pp-mock-input {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 7px 10px;
            font-size: 12px;
            color: #334155;
        }
        .pp-mock-field .pp-mock-input.tall {
            min-height: 36px;
        }
        .pp-mock-btn {
            background: linear-gradient(135deg, #667eea, #4338ca);
            color: #fff;
            text-align: center;
            padding: 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 10px;
        }

        /* Mockup: invoice */
        .pp-mock-inv-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .pp-mock-inv-header h4 {
            font-size: 14px;
            font-weight: 700;
            color: #667eea;
            margin: 0;
        }
        .pp-mock-inv-header span {
            font-size: 10px;
            color: #94a3b8;
        }
        .pp-mock-inv-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 11px;
            color: #475569;
            border-bottom: 1px solid #f1f5f9;
        }
        .pp-mock-inv-total {
            display: flex;
            justify-content: space-between;
            padding: 8px 0 0;
            font-size: 13px;
            font-weight: 700;
            color: #1f2937;
        }

        /* Mockup: export cards */
        .pp-mock-exports {
            display: flex;
            gap: 10px;
        }
        .pp-mock-export-card {
            flex: 1;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 10px;
            text-align: center;
            transition: all 0.2s;
        }
        .pp-mock-export-card i {
            font-size: 24px;
            display: block;
            margin-bottom: 6px;
        }
        .pp-mock-export-card .ext {
            font-size: 11px;
            font-weight: 700;
            color: #1f2937;
        }
        .pp-mock-export-card .desc {
            font-size: 9px;
            color: #94a3b8;
            margin-top: 2px;
        }
        .pp-mock-export-card:nth-child(1) i { color: #22c55e; }
        .pp-mock-export-card:nth-child(2) i { color: #3b82f6; }
        .pp-mock-export-card:nth-child(3) i { color: #8b5cf6; }

        /* Mockup: customer list */
        .pp-mock-search {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 7px 10px;
            font-size: 12px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
        }
        .pp-mock-customer {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .pp-mock-customer:last-child { border-bottom: none; }
        .pp-mock-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }
        .pp-mock-customer:nth-child(1) .pp-mock-avatar { background: #667eea; }
        .pp-mock-customer:nth-child(2) .pp-mock-avatar { background: #f43f5e; }
        .pp-mock-customer:nth-child(3) .pp-mock-avatar { background: #14b8a6; }
        .pp-mock-customer-info strong {
            display: block;
            font-size: 12px;
            color: #1f2937;
        }
        .pp-mock-customer-info span {
            font-size: 10px;
            color: #94a3b8;
        }

        /* Mockup: branch grid */
        .pp-mock-branches {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .pp-mock-branch {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 8px;
            text-align: center;
        }
        .pp-mock-branch i {
            font-size: 22px;
            display: block;
            margin-bottom: 4px;
            color: #667eea;
        }
        .pp-mock-branch span {
            font-size: 10px;
            font-weight: 600;
            color: #475569;
        }

        /* Bullet points */
        .pp-feat-bullets {
            list-style: none;
            padding: 0;
            margin: 0 0 20px;
        }
        .pp-feat-bullets li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 8px 0;
            font-size: 13px;
            color: #374151;
            line-height: 1.4;
            border-bottom: 1px solid #f3f4f6;
        }
        .pp-feat-bullets li:last-child { border-bottom: none; }
        .pp-feat-bullets li i {
            color: #22c55e;
            font-size: 15px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        /* CTA */
        .pp-feat-cta {
            display: block;
            text-align: center;
            padding: 12px;
            background: linear-gradient(135deg, #667eea, #4338ca);
            color: #fff !important;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none !important;
            transition: all 0.2s;
        }
        .pp-feat-cta:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(102,126,234,0.4);
        }

        /* ── OAuth Buttons ── */
        .pp-oauth-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 13px 16px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            margin-bottom: 10px;
        }
        .pp-oauth-google {
            background: #fff;
            border: 2px solid #e5e7eb;
            color: #374151;
        }
        .pp-oauth-google:hover {
            border-color: #d1d5db;
            background: #f9fafb;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .pp-oauth-apple {
            background: #000;
            border: 2px solid #000;
            color: #fff;
        }
        .pp-oauth-apple:hover {
            background: #1a1a1a;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .pp-oauth-divider {
            display: flex;
            align-items: center;
            margin: 18px 0 0;
            gap: 12px;
        }
        .pp-oauth-divider::before,
        .pp-oauth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }
        .pp-oauth-divider span {
            font-size: 13px;
            font-weight: 500;
            color: #9ca3af;
            white-space: nowrap;
        }

        /* ── PAIN SECTION ── */
        .pp-pain-section {
            max-width: 880px;
            margin: 48px auto 0;
            padding: 0 20px;
        }
        .pp-pain-title {
            text-align: center;
            font-size: 26px;
            font-weight: 800;
            color: #1f2937;
            letter-spacing: -0.5px;
            margin-bottom: 28px;
        }
        .pp-pain-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .pp-pain-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px 20px;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid #fecaca;
            border-top: 3px solid #f87171;
        }
        .pp-pain-card-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 12px;
            background: #fef2f2;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #ef4444;
        }
        .pp-pain-card p {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            line-height: 1.5;
            margin: 0;
        }
        .pp-pain-solution {
            text-align: center;
            padding: 20px 24px;
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border-radius: 14px;
            border: 1px solid #bbf7d0;
        }
        .pp-pain-solution i {
            color: #22c55e;
            font-size: 18px;
            margin-right: 6px;
        }
        .pp-pain-solution p {
            display: inline;
            font-size: 15px;
            font-weight: 600;
            color: #166534;
            margin: 0;
        }

        /* ── STEPS SECTION ── */
        .pp-steps-section {
            max-width: 880px;
            margin: 48px auto 0;
            padding: 0 20px;
        }
        .pp-steps-title {
            text-align: center;
            font-size: 26px;
            font-weight: 800;
            color: #1f2937;
            letter-spacing: -0.5px;
            margin-bottom: 32px;
        }
        .pp-steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .pp-step-card {
            text-align: center;
            position: relative;
        }
        .pp-step-num {
            width: 44px;
            height: 44px;
            margin: 0 auto 14px;
            background: linear-gradient(135deg, #667eea, #4338ca);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            color: #fff;
        }
        .pp-step-card h3 {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 6px;
        }
        .pp-step-card p {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.5;
            margin: 0;
        }

        /* ── PRICING SECTION ── */
        .pp-pricing-section {
            max-width: 720px;
            margin: 48px auto 0;
            padding: 0 20px;
        }
        .pp-pricing-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .pp-pricing-header h2 {
            font-size: 26px;
            font-weight: 800;
            color: #1f2937;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }
        .pp-pricing-header p {
            font-size: 15px;
            color: #6b7280;
        }
        .pp-pricing-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .pp-pricing-card {
            background: #fff;
            border-radius: 18px;
            padding: 28px 24px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.06);
            border: 2px solid #e5e7eb;
            position: relative;
        }
        .pp-pricing-card.featured {
            border-color: #667eea;
            box-shadow: 0 4px 24px rgba(102,126,234,0.15);
        }
        .pp-pricing-popular {
            position: absolute;
            top: -12px;
            right: 20px;
            background: linear-gradient(135deg, #667eea, #4338ca);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 100px;
            letter-spacing: 0.3px;
        }
        .pp-pricing-name {
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .pp-pricing-price {
            font-size: 36px;
            font-weight: 900;
            color: #1f2937;
            line-height: 1;
            margin-bottom: 4px;
        }
        .pp-pricing-per {
            font-size: 13px;
            color: #9ca3af;
            margin-bottom: 20px;
        }
        .pp-pricing-features {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .pp-pricing-features li {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #374151;
            padding: 6px 0;
        }
        .pp-pricing-features li i {
            color: #22c55e;
            font-size: 15px;
            flex-shrink: 0;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .pp-hero {
                padding: 40px 20px 64px;
            }
            .pp-hero h1 {
                font-size: 28px;
            }
            .pp-hero-sub {
                font-size: 15px;
            }
            .pp-hero-stats {
                gap: 24px;
            }
            .pp-hero-stat-val {
                font-size: 22px;
            }
            .pp-features {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
                margin-top: -36px;
            }
            .pp-feature {
                padding: 20px 14px;
            }
            .pp-feat-overlay { padding: 12px; }
            .pp-feat-modal { max-height: 92vh; }
            .pp-feat-modal-head { padding: 20px 20px 0; }
            .pp-feat-body { padding: 16px 20px 20px; }
            .pp-form-section {
                margin-top: 36px;
            }
            .pp-reg-form {
                padding: 24px 20px;
            }
            .pp-pain-grid {
                grid-template-columns: 1fr 1fr;
            }
            .pp-steps-grid {
                gap: 16px;
            }
            .pp-pricing-grid {
                grid-template-columns: 1fr 1fr;
            }
            input, select, textarea {
                font-size: 16px !important;
            }
        }
        @media (max-width: 480px) {
            .pp-hero {
                padding: 32px 16px 56px;
            }
            .pp-hero h1 {
                font-size: 24px;
            }
            .pp-hero-sub {
                font-size: 14px;
            }
            .pp-hero-badge {
                font-size: 12px;
                padding: 6px 14px;
            }
            .pp-hero-stats {
                gap: 20px;
            }
            .pp-hero-stat-val {
                font-size: 20px;
            }
            .pp-hero-stat-label {
                font-size: 11px;
            }
            .pp-features {
                grid-template-columns: 1fr;
                margin-top: -28px;
            }
            .pp-feature {
                display: flex;
                align-items: center;
                text-align: left;
                gap: 14px;
                padding: 16px;
            }
            .pp-feature-icon {
                margin: 0;
                flex-shrink: 0;
            }
            .pp-feature-more { opacity: 1; transform: none; }
            .pp-pain-grid {
                grid-template-columns: 1fr;
            }
            .pp-pain-title, .pp-steps-title {
                font-size: 22px;
            }
            .pp-steps-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .pp-pricing-grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }
            .pp-pricing-price {
                font-size: 30px;
            }
            .pp-mock-exports { flex-direction: column; }
            .pp-mock-branches { grid-template-columns: repeat(2, 1fr); }
            .pp-reg-form {
                padding: 20px 16px;
            }
            .pp-reg-success-actions {
                flex-direction: column;
                align-items: center;
            }
            .pp-reg-footer-trust {
                flex-direction: column;
                align-items: center;
                gap: 8px;
            }
        }
    </style>
</head>
<body>

<!-- ============ HERO ============ -->
<div class="pp-hero" style="position:relative">
    <div class="pp-hero-bg">
        <div class="pp-hero-blob pp-hero-blob--1"></div>
        <div class="pp-hero-blob pp-hero-blob--2"></div>
        <div class="pp-hero-blob pp-hero-blob--3"></div>
    </div>
    <div class="pp-hero-inner">
        <div class="pp-hero-badge">
            <i class="ri-star-fill"></i> <?php echo esc_html(PPV_Lang::t('repair_reg_badge')); ?>
        </div>
        <h1><?php echo PPV_Lang::t('repair_reg_hero_title'); ?></h1>
        <p class="pp-hero-sub">
            <?php echo esc_html(PPV_Lang::t('repair_reg_hero_sub')); ?>
        </p>
        <a href="#register" class="pp-hero-cta" onclick="document.getElementById('register').scrollIntoView({behavior:'smooth'});return false;">
            <?php echo esc_html(PPV_Lang::t('repair_reg_cta')); ?> <i class="ri-arrow-right-line"></i>
        </a>
        <div class="pp-hero-stats">
            <div class="pp-hero-stat">
                <span class="pp-hero-stat-val"><?php echo esc_html(PPV_Lang::t('repair_reg_stat_free_label')); ?></span>
                <span class="pp-hero-stat-label"><?php echo esc_html(PPV_Lang::t('repair_reg_stat_free')); ?></span>
            </div>
            <div class="pp-hero-stat">
                <span class="pp-hero-stat-val">&lt; 2 Min</span>
                <span class="pp-hero-stat-label"><?php echo esc_html(PPV_Lang::t('repair_reg_stat_setup')); ?></span>
            </div>
            <div class="pp-hero-stat">
                <span class="pp-hero-stat-val"><?php echo esc_html(PPV_Lang::t('repair_reg_stat_gdpr_label')); ?></span>
                <span class="pp-hero-stat-label"><?php echo esc_html(PPV_Lang::t('repair_reg_stat_gdpr')); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ============ FEATURES ============ -->
<div class="pp-features-section">
    <div class="pp-features">
        <div class="pp-feature pp-fade-in pp-fade-in-1" data-feature="online">
            <div class="pp-feature-icon blue"><i class="ri-smartphone-line"></i></div>
            <div>
                <h3><?php echo esc_html(PPV_Lang::t('repair_feat_online_title')); ?></h3>
                <p><?php echo esc_html(PPV_Lang::t('repair_feat_online_desc')); ?></p>
                <span class="pp-feature-more"><?php echo esc_html(PPV_Lang::t('repair_feat_more')); ?> <i class="ri-arrow-right-s-line"></i></span>
            </div>
        </div>
        <div class="pp-feature pp-fade-in pp-fade-in-2" data-feature="invoice">
            <div class="pp-feature-icon green"><i class="ri-file-text-line"></i></div>
            <div>
                <h3><?php echo esc_html(PPV_Lang::t('repair_feat_invoice_title')); ?></h3>
                <p><?php echo esc_html(PPV_Lang::t('repair_feat_invoice_desc')); ?></p>
                <span class="pp-feature-more"><?php echo esc_html(PPV_Lang::t('repair_feat_more')); ?> <i class="ri-arrow-right-s-line"></i></span>
            </div>
        </div>
        <div class="pp-feature pp-fade-in pp-fade-in-3" data-feature="export">
            <div class="pp-feature-icon purple"><i class="ri-bar-chart-2-line"></i></div>
            <div>
                <h3><?php echo esc_html(PPV_Lang::t('repair_feat_export_title')); ?></h3>
                <p><?php echo esc_html(PPV_Lang::t('repair_feat_export_desc')); ?></p>
                <span class="pp-feature-more"><?php echo esc_html(PPV_Lang::t('repair_feat_more')); ?> <i class="ri-arrow-right-s-line"></i></span>
            </div>
        </div>
        <div class="pp-feature pp-fade-in pp-fade-in-4" data-feature="ankauf">
            <div class="pp-feature-icon amber"><i class="ri-hand-coin-line"></i></div>
            <div>
                <h3><?php echo esc_html(PPV_Lang::t('repair_feat_ankauf_title')); ?></h3>
                <p><?php echo esc_html(PPV_Lang::t('repair_feat_ankauf_desc')); ?></p>
                <span class="pp-feature-more"><?php echo esc_html(PPV_Lang::t('repair_feat_more')); ?> <i class="ri-arrow-right-s-line"></i></span>
            </div>
        </div>
        <div class="pp-feature pp-fade-in pp-fade-in-5" data-feature="crm">
            <div class="pp-feature-icon rose"><i class="ri-team-line"></i></div>
            <div>
                <h3><?php echo esc_html(PPV_Lang::t('repair_feat_crm_title')); ?></h3>
                <p><?php echo esc_html(PPV_Lang::t('repair_feat_crm_desc')); ?></p>
                <span class="pp-feature-more"><?php echo esc_html(PPV_Lang::t('repair_feat_more')); ?> <i class="ri-arrow-right-s-line"></i></span>
            </div>
        </div>
        <div class="pp-feature pp-fade-in pp-fade-in-6" data-feature="branch">
            <div class="pp-feature-icon teal"><i class="ri-check-double-line"></i></div>
            <div>
                <h3><?php echo esc_html(PPV_Lang::t('repair_feat_branch_title')); ?></h3>
                <p><?php echo esc_html(PPV_Lang::t('repair_feat_branch_desc')); ?></p>
                <span class="pp-feature-more"><?php echo esc_html(PPV_Lang::t('repair_feat_more')); ?> <i class="ri-arrow-right-s-line"></i></span>
            </div>
        </div>
    </div>
</div>

<!-- ============ PAIN SECTION ============ -->
<div class="pp-pain-section">
    <h2 class="pp-pain-title pp-fade-in"><?php echo esc_html(PPV_Lang::t('repair_reg_pain_title')); ?></h2>
    <div class="pp-pain-grid">
        <div class="pp-pain-card pp-fade-in pp-fade-in-1">
            <div class="pp-pain-card-icon"><i class="ri-edit-line"></i></div>
            <p><?php echo esc_html(PPV_Lang::t('repair_reg_pain_1')); ?></p>
        </div>
        <div class="pp-pain-card pp-fade-in pp-fade-in-2">
            <div class="pp-pain-card-icon"><i class="ri-file-unknow-line"></i></div>
            <p><?php echo esc_html(PPV_Lang::t('repair_reg_pain_2')); ?></p>
        </div>
        <div class="pp-pain-card pp-fade-in pp-fade-in-3">
            <div class="pp-pain-card-icon"><i class="ri-question-line"></i></div>
            <p><?php echo esc_html(PPV_Lang::t('repair_reg_pain_3')); ?></p>
        </div>
    </div>
    <div class="pp-pain-solution pp-fade-in">
        <i class="ri-arrow-right-circle-fill"></i>
        <p><?php echo esc_html(PPV_Lang::t('repair_reg_pain_solution')); ?></p>
    </div>
</div>

<!-- ============ STEPS SECTION ============ -->
<div class="pp-steps-section">
    <h2 class="pp-steps-title pp-fade-in"><?php echo esc_html(PPV_Lang::t('repair_reg_steps_title')); ?></h2>
    <div class="pp-steps-grid">
        <div class="pp-step-card pp-fade-in pp-fade-in-1">
            <div class="pp-step-num">1</div>
            <h3><?php echo esc_html(PPV_Lang::t('repair_reg_step1_title')); ?></h3>
            <p><?php echo esc_html(PPV_Lang::t('repair_reg_step1_desc')); ?></p>
        </div>
        <div class="pp-step-card pp-fade-in pp-fade-in-2">
            <div class="pp-step-num">2</div>
            <h3><?php echo esc_html(PPV_Lang::t('repair_reg_step2_title')); ?></h3>
            <p><?php echo esc_html(PPV_Lang::t('repair_reg_step2_desc')); ?></p>
        </div>
        <div class="pp-step-card pp-fade-in pp-fade-in-3">
            <div class="pp-step-num">3</div>
            <h3><?php echo esc_html(PPV_Lang::t('repair_reg_step3_title')); ?></h3>
            <p><?php echo esc_html(PPV_Lang::t('repair_reg_step3_desc')); ?></p>
        </div>
    </div>
</div>

<!-- ============ PRICING ============ -->
<div class="pp-pricing-section">
    <div class="pp-pricing-header pp-fade-in">
        <h2><?php echo esc_html(PPV_Lang::t('repair_reg_pricing_title')); ?></h2>
        <p><?php echo esc_html(PPV_Lang::t('repair_reg_pricing_sub')); ?></p>
    </div>
    <div class="pp-pricing-grid">
        <div class="pp-pricing-card pp-fade-in pp-fade-in-1">
            <div class="pp-pricing-name"><?php echo esc_html(PPV_Lang::t('repair_reg_price_free')); ?></div>
            <div class="pp-pricing-price"><?php echo esc_html(PPV_Lang::t('repair_reg_price_free_val')); ?></div>
            <div class="pp-pricing-per"><?php echo esc_html(PPV_Lang::t('repair_reg_price_free_per')); ?></div>
            <ul class="pp-pricing-features">
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_reg_price_free_f1')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_reg_price_free_f2')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_reg_price_free_f3')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_reg_price_free_f4')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_reg_price_free_f5')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_reg_price_free_f6')); ?></li>
            </ul>
        </div>
        <div class="pp-pricing-card featured pp-fade-in pp-fade-in-2">
            <div class="pp-pricing-popular"><?php echo esc_html(PPV_Lang::t('repair_reg_price_popular')); ?></div>
            <div class="pp-pricing-name"><?php echo esc_html(PPV_Lang::t('repair_reg_price_pro')); ?></div>
            <div class="pp-pricing-price"><?php echo esc_html(PPV_Lang::t('repair_reg_price_pro_val')); ?></div>
            <div class="pp-pricing-per"><?php echo esc_html(PPV_Lang::t('repair_reg_price_pro_per')); ?></div>
            <ul class="pp-pricing-features">
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_reg_price_pro_f1')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_reg_price_pro_f2')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_reg_price_pro_f3')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_reg_price_pro_f4')); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- ============ FORM ============ -->
<div class="pp-form-section" id="register">
    <div class="pp-form-header pp-fade-in">
        <h2><?php echo esc_html(PPV_Lang::t('repair_reg_form_title')); ?></h2>
        <p><?php echo esc_html(PPV_Lang::t('repair_reg_form_sub')); ?></p>
    </div>

    <div class="pp-reg-card pp-fade-in">

        <!-- OAuth Quick Registration -->
        <div style="padding:28px 28px 0">
            <button type="button" id="rr-google-btn" class="pp-oauth-btn pp-oauth-google">
                <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332C2.438 15.983 5.482 18 9 18z"/><path fill="#FBBC05" d="M3.964 10.71c-.18-.54-.282-1.117-.282-1.71s.102-1.17.282-1.71V4.958H.957C.347 6.173 0 7.548 0 9s.348 2.827.957 4.042l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/></svg>
                <?php echo esc_html(PPV_Lang::t('repair_reg_google')); ?>
            </button>
            <button type="button" id="rr-apple-btn" class="pp-oauth-btn pp-oauth-apple" onclick="ppvOAuthApple()">
                <svg width="18" height="18" viewBox="0 0 18 18"><path fill="currentColor" d="M14.94 9.88c-.02-2.07 1.69-3.06 1.77-3.11-.96-1.41-2.46-1.6-3-1.63-1.27-.13-2.49.75-3.14.75-.65 0-1.65-.73-2.72-.71-1.4.02-2.69.81-3.41 2.07-1.46 2.53-.37 6.27 1.05 8.32.69 1 1.52 2.13 2.61 2.09 1.05-.04 1.44-.68 2.71-.68 1.27 0 1.62.68 2.72.66 1.13-.02 1.84-1.02 2.53-2.03.8-1.16 1.12-2.28 1.14-2.34-.02-.01-2.19-.84-2.21-3.34zM12.87 3.53c.58-.7.97-1.67.86-2.64-.83.03-1.84.55-2.44 1.25-.53.62-1 1.61-.87 2.56.93.07 1.87-.47 2.45-1.17z"/></svg>
                <?php echo esc_html(PPV_Lang::t('repair_reg_apple')); ?>
            </button>
            <div class="pp-oauth-divider">
                <span><?php echo esc_html(PPV_Lang::t('repair_reg_or')); ?></span>
            </div>
        </div>

        <!-- Registration Form -->
        <form id="pp-reg-form" class="pp-reg-form" autocomplete="off" novalidate style="padding-top:0">

            <!-- Business Details -->
            <div class="pp-reg-section">
                <div class="pp-reg-section-head">
                    <div class="pp-reg-section-icon"><i class="ri-store-2-line"></i></div>
                    <h3><?php echo esc_html(PPV_Lang::t('repair_reg_business')); ?></h3>
                </div>

                <div class="pp-reg-field">
                    <label for="rr-shop-name"><?php echo esc_html(PPV_Lang::t('repair_reg_shop_name')); ?></label>
                    <input type="text" id="rr-shop-name" name="shop_name" required placeholder="<?php echo esc_attr(PPV_Lang::t('repair_reg_shop_placeholder')); ?>" autocomplete="organization">
                </div>

                <div class="pp-reg-field">
                    <label for="rr-owner-name"><?php echo esc_html(PPV_Lang::t('repair_reg_owner')); ?></label>
                    <input type="text" id="rr-owner-name" name="owner_name" required placeholder="<?php echo esc_attr(PPV_Lang::t('repair_reg_owner_placeholder')); ?>" autocomplete="name">
                </div>
            </div>

            <!-- Login Credentials -->
            <div class="pp-reg-section">
                <div class="pp-reg-section-head">
                    <div class="pp-reg-section-icon"><i class="ri-lock-line"></i></div>
                    <h3><?php echo esc_html(PPV_Lang::t('repair_reg_credentials')); ?></h3>
                </div>

                <div class="pp-reg-field">
                    <label for="rr-email"><?php echo esc_html(PPV_Lang::t('repair_reg_email')); ?></label>
                    <input type="email" id="rr-email" name="email" required placeholder="<?php echo esc_attr(PPV_Lang::t('repair_reg_email_placeholder')); ?>" autocomplete="email">
                </div>

                <div class="pp-reg-field">
                    <label for="rr-password"><?php echo esc_html(PPV_Lang::t('repair_reg_password')); ?> <span style="font-weight:400;color:#9ca3af;"><?php echo esc_html(PPV_Lang::t('repair_reg_password_hint')); ?></span></label>
                    <input type="password" id="rr-password" name="password" required minlength="6" placeholder="<?php echo esc_attr(PPV_Lang::t('repair_reg_password_placeholder')); ?>" autocomplete="new-password">
                </div>

                <div class="pp-reg-field">
                    <label for="rr-password2"><?php echo esc_html(PPV_Lang::t('repair_reg_password2')); ?></label>
                    <input type="password" id="rr-password2" name="password2" required minlength="6" placeholder="<?php echo esc_attr(PPV_Lang::t('repair_reg_password2_placeholder')); ?>" autocomplete="new-password">
                </div>
            </div>

            <!-- Terms -->
            <div class="pp-reg-terms">
                <label>
                    <input type="checkbox" id="rr-terms" required>
                    <span><?php echo esc_html(PPV_Lang::t('repair_accept_terms')); ?> <a href="/datenschutz" target="_blank"><?php echo esc_html(PPV_Lang::t('repair_privacy_policy')); ?></a> <?php echo esc_html(PPV_Lang::t('repair_and')); ?> <a href="/agb" target="_blank"><?php echo esc_html(PPV_Lang::t('repair_agb')); ?></a></span>
                </label>
            </div>

            <!-- Submit -->
            <button type="submit" id="rr-submit" class="pp-reg-submit">
                <i class="ri-rocket-2-line"></i> <?php echo esc_html(PPV_Lang::t('repair_reg_submit')); ?>
            </button>

            <!-- Error -->
            <div id="rr-error" class="pp-reg-error pp-hidden"></div>
        </form>

        <!-- Success Screen (hidden by default) -->
        <div id="rr-success" class="pp-reg-success pp-hidden">
            <div class="pp-reg-success-icon"><i class="ri-check-line"></i></div>
            <h2><?php echo esc_html(PPV_Lang::t('repair_reg_success_title')); ?></h2>
            <p><?php echo esc_html(PPV_Lang::t('repair_reg_success_text')); ?></p>
            <div class="pp-reg-success-link">
                <div class="pp-reg-link-label"><?php echo esc_html(PPV_Lang::t('repair_reg_form_link')); ?></div>
                <a id="rr-form-url" href="#" target="_blank"></a>
            </div>
            <p class="pp-reg-success-info"><?php echo esc_html(PPV_Lang::t('repair_reg_email_info')); ?></p>
            <div class="pp-reg-success-actions">
                <a href="/formular/admin" class="pp-reg-btn-primary"><i class="ri-dashboard-line"></i> <?php echo esc_html(PPV_Lang::t('repair_reg_to_admin')); ?></a>
                <a id="rr-form-link" href="#" class="pp-reg-btn-secondary" target="_blank"><i class="ri-external-link-line"></i> <?php echo esc_html(PPV_Lang::t('repair_reg_test_form')); ?></a>
            </div>
        </div>

        <!-- Login Link -->
        <div id="rr-login-row" class="pp-reg-login-link">
            <?php echo esc_html(PPV_Lang::t('repair_reg_login_text')); ?> <a href="/formular/admin/login"><?php echo esc_html(PPV_Lang::t('repair_reg_login_link')); ?> &rarr;</a>
        </div>
    </div>
</div>

<!-- ============ FOOTER ============ -->
<div class="pp-reg-footer">
    <div class="pp-reg-footer-trust">
        <div class="pp-reg-footer-trust-item"><i class="ri-lock-line"></i> <?php echo esc_html(PPV_Lang::t('repair_ssl_encrypted')); ?></div>
        <div class="pp-reg-footer-trust-item"><i class="ri-shield-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_dsgvo_conform')); ?></div>
    </div>
    <div class="pp-reg-footer-links">
        <a href="/datenschutz"><?php echo esc_html(PPV_Lang::t('repair_datenschutz')); ?></a>
        <span class="pp-reg-footer-dot"></span>
        <a href="/agb"><?php echo esc_html(PPV_Lang::t('repair_agb')); ?></a>
        <span class="pp-reg-footer-dot"></span>
        <a href="/impressum"><?php echo esc_html(PPV_Lang::t('repair_impressum')); ?></a>
    </div>
    <!-- Language Switcher (footer) -->
    <div style="display:flex;justify-content:center;gap:6px;margin:12px 0 8px">
        <button class="pp-lang-btn <?php echo $lang === 'de' ? 'active' : ''; ?>" data-lang="de" style="background:<?php echo $lang === 'de' ? '#667eea' : '#e5e7eb'; ?>;color:<?php echo $lang === 'de' ? '#fff' : '#6b7280'; ?>;border:none;font-size:11px;font-weight:700;padding:5px 10px;border-radius:6px;cursor:pointer">DE</button>
        <button class="pp-lang-btn <?php echo $lang === 'hu' ? 'active' : ''; ?>" data-lang="hu" style="background:<?php echo $lang === 'hu' ? '#667eea' : '#e5e7eb'; ?>;color:<?php echo $lang === 'hu' ? '#fff' : '#6b7280'; ?>;border:none;font-size:11px;font-weight:700;padding:5px 10px;border-radius:6px;cursor:pointer">HU</button>
        <button class="pp-lang-btn <?php echo $lang === 'ro' ? 'active' : ''; ?>" data-lang="ro" style="background:<?php echo $lang === 'ro' ? '#667eea' : '#e5e7eb'; ?>;color:<?php echo $lang === 'ro' ? '#fff' : '#6b7280'; ?>;border:none;font-size:11px;font-weight:700;padding:5px 10px;border-radius:6px;cursor:pointer">RO</button>
    </div>
    <div class="pp-reg-footer-powered">
        <?php echo esc_html(PPV_Lang::t('repair_powered_by')); ?> <a href="https://punktepass.de">PunktePass</a>
    </div>
</div>

<script>
(function() {
    'use strict';

    var AJAX_URL = <?php echo json_encode($ajax_url); ?>;
    var NONCE    = <?php echo json_encode($nonce); ?>;
    var ppvLang  = <?php echo $js_strings; ?>;

    // Language switcher
    var langBtns = document.querySelectorAll('.pp-lang-btn');
    langBtns.forEach(function(btn){
        btn.addEventListener('click', function(){
            var lang = btn.getAttribute('data-lang');
            var url = new URL(window.location.href);
            url.searchParams.set('lang', lang);
            document.cookie = 'ppv_lang=' + lang + ';path=/;max-age=31536000';
            window.location.href = url.toString();
        });
    });

    var form       = document.getElementById('pp-reg-form');
    var submitBtn  = document.getElementById('rr-submit');
    var errorBox   = document.getElementById('rr-error');
    var successBox = document.getElementById('rr-success');
    var loginRow   = document.getElementById('rr-login-row');

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.classList.remove('pp-hidden');
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function hideError() {
        errorBox.classList.add('pp-hidden');
    }

    function setLoading(loading) {
        if (loading) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="pp-reg-spinner"></span> ' + ppvLang.creating;
        } else {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ri-rocket-2-line"></i> ' + ppvLang.submit_text;
        }
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        hideError();

        var shopName  = document.getElementById('rr-shop-name').value.trim();
        var ownerName = document.getElementById('rr-owner-name').value.trim();
        var email     = document.getElementById('rr-email').value.trim();
        var password  = document.getElementById('rr-password').value;
        var password2 = document.getElementById('rr-password2').value;
        var terms     = document.getElementById('rr-terms').checked;

        // Validation
        if (!shopName) { showError(ppvLang.err_shop_name); return; }
        if (!ownerName) { showError(ppvLang.err_owner); return; }
        if (!email) { showError(ppvLang.err_email); return; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showError(ppvLang.err_email_invalid); return; }
        if (!password || password.length < 6) { showError(ppvLang.err_password); return; }
        if (password !== password2) { showError(ppvLang.err_password2); return; }
        if (!terms) { showError(ppvLang.err_terms); return; }

        setLoading(true);

        var data = new FormData();
        data.append('action', 'ppv_repair_register');
        data.append('nonce', NONCE);
        data.append('shop_name', shopName);
        data.append('owner_name', ownerName);
        data.append('email', email);
        data.append('password', password);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', AJAX_URL, true);
        xhr.onload = function() {
            setLoading(false);

            if (xhr.status !== 200) {
                showError(ppvLang.err_server);
                return;
            }

            try {
                var res = JSON.parse(xhr.responseText);
            } catch (err) {
                showError(ppvLang.err_unexpected);
                return;
            }

            if (res.success && res.data) {
                var slug    = res.data.slug || '';
                var formUrl = 'https://punktepass.de/formular/' + slug;

                document.getElementById('rr-form-url').href = formUrl;
                document.getElementById('rr-form-url').textContent = formUrl;
                document.getElementById('rr-form-link').href = formUrl;

                form.classList.add('pp-hidden');
                loginRow.classList.add('pp-hidden');
                successBox.classList.remove('pp-hidden');
                successBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                showError(res.data && res.data.message ? res.data.message : ppvLang.err_failed);
            }
        };
        xhr.onerror = function() {
            setLoading(false);
            showError(ppvLang.err_network);
        };
        xhr.send(data);
    });
})();

// ── Google OAuth (GSI) ──
var ppvGoogleClientId = '<?php echo defined("PPV_GOOGLE_CLIENT_ID") ? PPV_GOOGLE_CLIENT_ID : get_option("ppv_google_client_id", "645942978357-ndj7dgrapd2dgndnjf03se1p08l0o9ra.apps.googleusercontent.com"); ?>';
var ppvAppleClientId  = '<?php echo defined("PPV_APPLE_CLIENT_ID") ? PPV_APPLE_CLIENT_ID : get_option("ppv_apple_client_id", ""); ?>';
var ppvGoogleInit = false;

function ppvInitGoogle() {
    if (ppvGoogleInit || typeof google === 'undefined' || !google.accounts) return false;
    try {
        google.accounts.id.initialize({
            client_id: ppvGoogleClientId,
            callback: ppvGoogleCallback,
            auto_select: false
        });
        ppvGoogleInit = true;
    } catch(e) {}
    return ppvGoogleInit;
}

function ppvGoogleCallback(response) {
    if (!response.credential) return;
    var btn = document.getElementById('rr-google-btn');
    btn.disabled = true;
    btn.textContent = 'Google...';

    var data = new FormData();
    data.append('action', 'ppv_repair_google_login');
    data.append('credential', response.credential);
    data.append('mode', 'register');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', AJAX_URL, true);
    xhr.onload = function() {
        btn.disabled = false;
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.success && res.data) {
                window.location.href = res.data.redirect || '/formular/admin';
            } else {
                var errBox = document.getElementById('rr-error');
                errBox.textContent = res.data && res.data.message ? res.data.message : 'Google Login fehlgeschlagen';
                errBox.classList.remove('pp-hidden');
            }
        } catch(e) {
            alert('Unerwarteter Fehler');
        }
    };
    xhr.onerror = function() { btn.disabled = false; alert('Netzwerkfehler'); };
    xhr.send(data);
}

// Google button click
document.getElementById('rr-google-btn').addEventListener('click', function() {
    ppvInitGoogle();
    if (ppvGoogleInit && typeof google !== 'undefined' && google.accounts) {
        try { google.accounts.id.cancel(); } catch(e) {}
        google.accounts.id.prompt(function(notification) {
            if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
                // Fallback: render Google button
                var container = document.createElement('div');
                container.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999;background:#fff;padding:24px;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,0.2)';
                document.body.appendChild(container);
                google.accounts.id.renderButton(container, { theme:'outline', size:'large', width:280 });
                setTimeout(function() { var b = container.querySelector('[role="button"]'); if(b) b.click(); }, 200);
                setTimeout(function() { if(container.parentNode) container.parentNode.removeChild(container); }, 30000);
            }
        });
    } else {
        // SDK not loaded yet, try again
        setTimeout(function() { ppvInitGoogle(); }, 500);
    }
});

// ── Apple Sign-In ──
function ppvOAuthApple() {
    if (!ppvAppleClientId) {
        alert('Apple Sign-In ist derzeit nicht verfügbar');
        return;
    }
    // Load Apple SDK if not loaded
    if (typeof AppleID === 'undefined') {
        var s = document.createElement('script');
        s.src = 'https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js';
        s.onload = function() { ppvDoAppleSignIn(); };
        document.head.appendChild(s);
    } else {
        ppvDoAppleSignIn();
    }
}

function ppvDoAppleSignIn() {
    try {
        AppleID.auth.init({
            clientId: ppvAppleClientId,
            scope: 'name email',
            redirectURI: window.location.origin + '/formular',
            usePopup: true
        });
        AppleID.auth.signIn().then(function(response) {
            if (!response.authorization || !response.authorization.id_token) return;
            var btn = document.getElementById('rr-apple-btn');
            btn.disabled = true;
            btn.textContent = 'Apple...';

            var data = new FormData();
            data.append('action', 'ppv_repair_apple_login');
            data.append('id_token', response.authorization.id_token);
            data.append('mode', 'register');
            if (response.user) data.append('user', JSON.stringify(response.user));

            var xhr = new XMLHttpRequest();
            xhr.open('POST', AJAX_URL, true);
            xhr.onload = function() {
                btn.disabled = false;
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success && res.data) {
                        window.location.href = res.data.redirect || '/formular/admin';
                    } else {
                        var errBox = document.getElementById('rr-error');
                        errBox.textContent = res.data && res.data.message ? res.data.message : 'Apple Login fehlgeschlagen';
                        errBox.classList.remove('pp-hidden');
                    }
                } catch(e) { alert('Unerwarteter Fehler'); }
            };
            xhr.onerror = function() { btn.disabled = false; alert('Netzwerkfehler'); };
            xhr.send(data);
        }).catch(function(err) {
            if (err.error !== 'popup_closed_by_user') {
                alert('Apple Sign-In fehlgeschlagen');
            }
        });
    } catch(e) {
        alert('Apple Sign-In fehlgeschlagen');
    }
}

// Init Google on page load
if (typeof google !== 'undefined' && google.accounts) { ppvInitGoogle(); }
else { window.addEventListener('load', function() { setTimeout(ppvInitGoogle, 500); }); }
</script>

<!-- ============ FEATURE MODAL ============ -->
<div class="pp-feat-overlay" id="ppFeatOverlay">
    <div class="pp-feat-modal">
        <button class="pp-feat-close" id="ppFeatClose"><i class="ri-close-line"></i></button>

        <!-- === Online & Vor-Ort === -->
        <div class="pp-feat-body" data-feat="online" hidden>
            <div class="pp-feat-modal-head">
                <div class="pp-feature-icon blue"><i class="ri-smartphone-line"></i></div>
                <div>
                    <h2><?php echo esc_html(PPV_Lang::t('repair_feat_online_title')); ?></h2>
                    <p><?php echo esc_html(PPV_Lang::t('repair_feat_online_desc')); ?></p>
                </div>
            </div>
            <div class="pp-mockup" style="margin-top:20px">
                <div class="pp-mockup-bar"><span></span><span></span><span></span></div>
                <div class="pp-mockup-inner">
                    <div class="pp-mock-field"><label>Name</label><div class="pp-mock-input">Max Mustermann</div></div>
                    <div class="pp-mock-field"><label><?php echo esc_html(PPV_Lang::t('repair_feat_online_mock_phone')); ?></label><div class="pp-mock-input">+49 176 1234567</div></div>
                    <div class="pp-mock-field"><label><?php echo esc_html(PPV_Lang::t('repair_feat_online_mock_device')); ?></label><div class="pp-mock-input">iPhone 15 Pro Max</div></div>
                    <div class="pp-mock-field"><label><?php echo esc_html(PPV_Lang::t('repair_feat_online_mock_problem')); ?></label><div class="pp-mock-input tall"><?php echo esc_html(PPV_Lang::t('repair_feat_online_mock_problem_text')); ?></div></div>
                    <div class="pp-mock-btn"><i class="ri-send-plane-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_online_mock_submit')); ?></div>
                </div>
            </div>
            <ul class="pp-feat-bullets">
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_online_b1')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_online_b2')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_online_b3')); ?></li>
            </ul>
            <a href="#register" class="pp-feat-cta" onclick="document.getElementById('ppFeatOverlay').classList.remove('active')"><?php echo esc_html(PPV_Lang::t('repair_feat_modal_cta')); ?></a>
        </div>

        <!-- === Rechnungen & Angebote === -->
        <div class="pp-feat-body" data-feat="invoice" hidden>
            <div class="pp-feat-modal-head">
                <div class="pp-feature-icon green"><i class="ri-file-text-line"></i></div>
                <div>
                    <h2><?php echo esc_html(PPV_Lang::t('repair_feat_invoice_title')); ?></h2>
                    <p><?php echo esc_html(PPV_Lang::t('repair_feat_invoice_desc')); ?></p>
                </div>
            </div>
            <div class="pp-mockup" style="margin-top:20px">
                <div class="pp-mockup-bar"><span></span><span></span><span></span></div>
                <div class="pp-mockup-inner">
                    <div class="pp-mock-inv-header">
                        <div>
                            <h4><?php echo esc_html(PPV_Lang::t('repair_feat_invoice_mock_title')); ?></h4>
                            <span style="font-size:11px;color:#64748b">#2025-0042 · 11.02.2025</span>
                        </div>
                        <span style="background:#dcfce7;color:#16a34a;font-size:10px;font-weight:600;padding:3px 8px;border-radius:20px"><?php echo esc_html(PPV_Lang::t('repair_feat_invoice_mock_sent')); ?></span>
                    </div>
                    <div class="pp-mock-inv-row"><span><?php echo esc_html(PPV_Lang::t('repair_feat_invoice_mock_display')); ?></span><span>89,00 €</span></div>
                    <div class="pp-mock-inv-row"><span><?php echo esc_html(PPV_Lang::t('repair_feat_invoice_mock_battery')); ?></span><span>49,00 €</span></div>
                    <div class="pp-mock-inv-row"><span><?php echo esc_html(PPV_Lang::t('repair_feat_invoice_mock_labor')); ?></span><span>25,00 €</span></div>
                    <div class="pp-mock-inv-total"><span>Total</span><span>163,00 €</span></div>
                    <div class="pp-mock-btn" style="margin-top:12px"><i class="ri-mail-send-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_invoice_mock_send')); ?></div>
                </div>
            </div>
            <ul class="pp-feat-bullets">
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_invoice_b1')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_invoice_b2')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_invoice_b3')); ?></li>
            </ul>
            <a href="#register" class="pp-feat-cta" onclick="document.getElementById('ppFeatOverlay').classList.remove('active')"><?php echo esc_html(PPV_Lang::t('repair_feat_modal_cta')); ?></a>
        </div>

        <!-- === DATEV & Export === -->
        <div class="pp-feat-body" data-feat="export" hidden>
            <div class="pp-feat-modal-head">
                <div class="pp-feature-icon purple"><i class="ri-bar-chart-2-line"></i></div>
                <div>
                    <h2><?php echo esc_html(PPV_Lang::t('repair_feat_export_title')); ?></h2>
                    <p><?php echo esc_html(PPV_Lang::t('repair_feat_export_desc')); ?></p>
                </div>
            </div>
            <div class="pp-mockup" style="margin-top:20px">
                <div class="pp-mockup-bar"><span></span><span></span><span></span></div>
                <div class="pp-mockup-inner">
                    <div class="pp-mock-exports">
                        <div class="pp-mock-export-card">
                            <i class="ri-file-excel-2-line"></i>
                            <div class="ext">CSV</div>
                            <div class="desc"><?php echo esc_html(PPV_Lang::t('repair_feat_export_mock_csv')); ?></div>
                        </div>
                        <div class="pp-mock-export-card">
                            <i class="ri-file-chart-line"></i>
                            <div class="ext">Excel</div>
                            <div class="desc"><?php echo esc_html(PPV_Lang::t('repair_feat_export_mock_excel')); ?></div>
                        </div>
                        <div class="pp-mock-export-card">
                            <i class="ri-bank-line"></i>
                            <div class="ext">DATEV</div>
                            <div class="desc"><?php echo esc_html(PPV_Lang::t('repair_feat_export_mock_datev')); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <ul class="pp-feat-bullets">
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_export_b1')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_export_b2')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_export_b3')); ?></li>
            </ul>
            <a href="#register" class="pp-feat-cta" onclick="document.getElementById('ppFeatOverlay').classList.remove('active')"><?php echo esc_html(PPV_Lang::t('repair_feat_modal_cta')); ?></a>
        </div>

        <!-- === Digitaler Ankauf === -->
        <div class="pp-feat-body" data-feat="ankauf" hidden>
            <div class="pp-feat-modal-head">
                <div class="pp-feature-icon amber"><i class="ri-hand-coin-line"></i></div>
                <div>
                    <h2><?php echo esc_html(PPV_Lang::t('repair_feat_ankauf_title')); ?></h2>
                    <p><?php echo esc_html(PPV_Lang::t('repair_feat_ankauf_desc')); ?></p>
                </div>
            </div>
            <div class="pp-mockup" style="margin-top:20px">
                <div class="pp-mockup-bar"><span></span><span></span><span></span></div>
                <div class="pp-mockup-inner">
                    <div class="pp-mock-inv-header">
                        <div>
                            <h4><?php echo esc_html(PPV_Lang::t('repair_feat_ankauf_mock_title')); ?></h4>
                            <span style="font-size:11px;color:#64748b">AK-2025-0018</span>
                        </div>
                    </div>
                    <div class="pp-mock-inv-row"><span><?php echo esc_html(PPV_Lang::t('repair_feat_ankauf_mock_device')); ?></span><span>Samsung Galaxy S24</span></div>
                    <div class="pp-mock-inv-row"><span>IMEI</span><span>356789012345678</span></div>
                    <div class="pp-mock-inv-row"><span><?php echo esc_html(PPV_Lang::t('repair_feat_ankauf_mock_condition')); ?></span><span>⭐⭐⭐⭐ <?php echo esc_html(PPV_Lang::t('repair_feat_ankauf_mock_good')); ?></span></div>
                    <div class="pp-mock-inv-total"><span><?php echo esc_html(PPV_Lang::t('repair_feat_ankauf_mock_price')); ?></span><span>350,00 €</span></div>
                    <div style="display:flex;align-items:center;gap:8px;margin-top:12px;padding-top:10px;border-top:1px solid #e2e8f0">
                        <i class="ri-quill-pen-line" style="color:#667eea;font-size:18px"></i>
                        <span style="font-size:11px;color:#64748b"><?php echo esc_html(PPV_Lang::t('repair_feat_ankauf_mock_signature')); ?></span>
                        <span style="font-family:cursive;font-size:16px;color:#667eea;margin-left:auto">M. Schmidt</span>
                    </div>
                </div>
            </div>
            <ul class="pp-feat-bullets">
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_ankauf_b1')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_ankauf_b2')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_ankauf_b3')); ?></li>
            </ul>
            <a href="#register" class="pp-feat-cta" onclick="document.getElementById('ppFeatOverlay').classList.remove('active')"><?php echo esc_html(PPV_Lang::t('repair_feat_modal_cta')); ?></a>
        </div>

        <!-- === Kundenverwaltung === -->
        <div class="pp-feat-body" data-feat="crm" hidden>
            <div class="pp-feat-modal-head">
                <div class="pp-feature-icon rose"><i class="ri-team-line"></i></div>
                <div>
                    <h2><?php echo esc_html(PPV_Lang::t('repair_feat_crm_title')); ?></h2>
                    <p><?php echo esc_html(PPV_Lang::t('repair_feat_crm_desc')); ?></p>
                </div>
            </div>
            <div class="pp-mockup" style="margin-top:20px">
                <div class="pp-mockup-bar"><span></span><span></span><span></span></div>
                <div class="pp-mockup-inner">
                    <div class="pp-mock-search"><i class="ri-search-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_crm_mock_search')); ?></div>
                    <div class="pp-mock-customer">
                        <div class="pp-mock-avatar">MM</div>
                        <div class="pp-mock-customer-info">
                            <strong>Max Mustermann</strong>
                            <span>5 <?php echo esc_html(PPV_Lang::t('repair_feat_crm_mock_repairs')); ?> · max@example.de</span>
                        </div>
                    </div>
                    <div class="pp-mock-customer">
                        <div class="pp-mock-avatar">AS</div>
                        <div class="pp-mock-customer-info">
                            <strong>Anna Schmidt</strong>
                            <span>2 <?php echo esc_html(PPV_Lang::t('repair_feat_crm_mock_repairs')); ?> · anna@example.de</span>
                        </div>
                    </div>
                    <div class="pp-mock-customer">
                        <div class="pp-mock-avatar">TB</div>
                        <div class="pp-mock-customer-info">
                            <strong>Thomas Braun</strong>
                            <span>1 <?php echo esc_html(PPV_Lang::t('repair_feat_crm_mock_repair')); ?> · thomas@example.de</span>
                        </div>
                    </div>
                </div>
            </div>
            <ul class="pp-feat-bullets">
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_crm_b1')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_crm_b2')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_crm_b3')); ?></li>
            </ul>
            <a href="#register" class="pp-feat-cta" onclick="document.getElementById('ppFeatOverlay').classList.remove('active')"><?php echo esc_html(PPV_Lang::t('repair_feat_modal_cta')); ?></a>
        </div>

        <!-- === Jede Branche === -->
        <div class="pp-feat-body" data-feat="branch" hidden>
            <div class="pp-feat-modal-head">
                <div class="pp-feature-icon teal"><i class="ri-check-double-line"></i></div>
                <div>
                    <h2><?php echo esc_html(PPV_Lang::t('repair_feat_branch_title')); ?></h2>
                    <p><?php echo esc_html(PPV_Lang::t('repair_feat_branch_desc')); ?></p>
                </div>
            </div>
            <div class="pp-mockup" style="margin-top:20px">
                <div class="pp-mockup-bar"><span></span><span></span><span></span></div>
                <div class="pp-mockup-inner">
                    <div class="pp-mock-branches">
                        <div class="pp-mock-branch"><i class="ri-smartphone-line"></i><span><?php echo esc_html(PPV_Lang::t('repair_feat_branch_mock_phone')); ?></span></div>
                        <div class="pp-mock-branch"><i class="ri-computer-line"></i><span>PC / Laptop</span></div>
                        <div class="pp-mock-branch"><i class="ri-tablet-line"></i><span>Tablet</span></div>
                        <div class="pp-mock-branch"><i class="ri-roadster-line"></i><span><?php echo esc_html(PPV_Lang::t('repair_feat_branch_mock_car')); ?></span></div>
                        <div class="pp-mock-branch"><i class="ri-riding-line"></i><span><?php echo esc_html(PPV_Lang::t('repair_feat_branch_mock_bike')); ?></span></div>
                        <div class="pp-mock-branch"><i class="ri-gamepad-line"></i><span><?php echo esc_html(PPV_Lang::t('repair_feat_branch_mock_console')); ?></span></div>
                    </div>
                </div>
            </div>
            <ul class="pp-feat-bullets">
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_branch_b1')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_branch_b2')); ?></li>
                <li><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('repair_feat_branch_b3')); ?></li>
            </ul>
            <a href="#register" class="pp-feat-cta" onclick="document.getElementById('ppFeatOverlay').classList.remove('active')"><?php echo esc_html(PPV_Lang::t('repair_feat_modal_cta')); ?></a>
        </div>
    </div>
</div>
<script>
(function() {
    var overlay = document.getElementById('ppFeatOverlay');
    var bodies = overlay.querySelectorAll('.pp-feat-body');

    // Open modal on card click
    document.querySelectorAll('.pp-feature[data-feature]').forEach(function(card) {
        card.addEventListener('click', function() {
            var feat = this.getAttribute('data-feature');
            bodies.forEach(function(b) { b.hidden = b.getAttribute('data-feat') !== feat; });
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    });

    // Close modal
    function closeModal() {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    document.getElementById('ppFeatClose').addEventListener('click', closeModal);
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay.classList.contains('active')) closeModal();
    });
})();
</script>

<!-- Cookie Consent Banner -->
<div id="cookieConsent" style="display:none; position:fixed; bottom:0; left:0; right:0; background:rgba(30,41,59,0.97); color:#fff; padding:16px 24px; z-index:9999; box-shadow:0 -4px 20px rgba(0,0,0,0.15); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px);">
    <div style="max-width:1200px; margin:0 auto; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px;">
        <div style="flex:1; min-width:280px;">
            <p style="margin:0; font-size:14px; line-height:1.5;">
                <strong><?php echo esc_html(PPV_Lang::t('repair_cookie_heading')); ?></strong> <?php echo esc_html(PPV_Lang::t('repair_cookie_notice')); ?>
                <a href="https://punktepass.de/datenschutz" target="_blank" style="color:#93c5fd; text-decoration:underline;"><?php echo esc_html(PPV_Lang::t('repair_privacy_policy')); ?></a>
            </p>
        </div>
        <div style="display:flex; gap:12px; flex-shrink:0;">
            <button onclick="rejectCookies()" style="padding:10px 20px; background:transparent; border:1px solid rgba(255,255,255,0.3); color:#fff; border-radius:8px; cursor:pointer; font-size:14px; transition:all 0.2s;"><?php echo esc_html(PPV_Lang::t('repair_cookie_reject')); ?></button>
            <button onclick="acceptCookies()" style="padding:10px 24px; background:linear-gradient(135deg,#667eea,#4338ca); border:none; color:#fff; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600; transition:all 0.2s;"><?php echo esc_html(PPV_Lang::t('repair_cookie_accept')); ?></button>
        </div>
    </div>
</div>
<script>
(function() {
    var consent = localStorage.getItem('cookie_consent');
    if (!consent) {
        document.getElementById('cookieConsent').style.display = 'block';
    }
})();
function acceptCookies() {
    localStorage.setItem('cookie_consent', 'accepted');
    document.getElementById('cookieConsent').style.display = 'none';
    loadGoogleAnalytics();
}
function rejectCookies() {
    localStorage.setItem('cookie_consent', 'rejected');
    document.getElementById('cookieConsent').style.display = 'none';
}
</script>

</body>
</html>
<?php
    }
}
