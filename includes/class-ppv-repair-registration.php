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

        $ajax_url = admin_url('admin-ajax.php');
        $nonce    = wp_create_nonce('ppv_repair_register');
        $logo_url = PPV_PLUGIN_URL . 'assets/img/punktepass-repair-logo.svg';

        ?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reparaturverwaltung f&uuml;r Ihren Shop - PunktePass</title>
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
            cursor: default;
        }
        .pp-feature:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 36px rgba(0,0,0,0.1), 0 0 0 1px rgba(0,0,0,0.02);
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
            .pp-form-section {
                margin-top: 36px;
            }
            .pp-reg-form {
                padding: 24px 20px;
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
            .pp-reg-form {
                padding: 20px 16px;
            }
            .pp-reg-row {
                flex-direction: column;
                gap: 0;
            }
            .pp-reg-row .pp-reg-field-sm {
                flex: 1;
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
<div class="pp-hero">
    <div class="pp-hero-bg">
        <div class="pp-hero-blob pp-hero-blob--1"></div>
        <div class="pp-hero-blob pp-hero-blob--2"></div>
        <div class="pp-hero-blob pp-hero-blob--3"></div>
    </div>
    <div class="pp-hero-inner">
        <div class="pp-hero-badge">
            <i class="ri-star-fill"></i> Kostenlos starten &mdash; keine Kreditkarte n&ouml;tig
        </div>
        <h1>Digitale Reparatur&shy;verwaltung <span>f&uuml;r Ihren Shop</span></h1>
        <p class="pp-hero-sub">
            Professionelles Formular, Rechnungen, Ankauf, Kundenverwaltung &amp; DATEV-Export &ndash; alles in einem System. Am Tablet vor Ort oder online.
        </p>
        <a href="#register" class="pp-hero-cta" onclick="document.getElementById('register').scrollIntoView({behavior:'smooth'});return false;">
            Jetzt registrieren <i class="ri-arrow-right-line"></i>
        </a>
        <div class="pp-hero-stats">
            <div class="pp-hero-stat">
                <span class="pp-hero-stat-val">100%</span>
                <span class="pp-hero-stat-label">Kostenlos</span>
            </div>
            <div class="pp-hero-stat">
                <span class="pp-hero-stat-val">&lt; 2 Min</span>
                <span class="pp-hero-stat-label">Einrichtung</span>
            </div>
            <div class="pp-hero-stat">
                <span class="pp-hero-stat-val">DSGVO</span>
                <span class="pp-hero-stat-label">Konform</span>
            </div>
        </div>
    </div>
</div>

<!-- ============ FEATURES ============ -->
<div class="pp-features-section">
    <div class="pp-features">
        <div class="pp-feature pp-fade-in pp-fade-in-1">
            <div class="pp-feature-icon blue"><i class="ri-smartphone-line"></i></div>
            <div>
                <h3>Online &amp; Vor-Ort</h3>
                <p>Formular am Tablet oder Online nutzen</p>
            </div>
        </div>
        <div class="pp-feature pp-fade-in pp-fade-in-2">
            <div class="pp-feature-icon green"><i class="ri-file-text-line"></i></div>
            <div>
                <h3>Rechnungen &amp; Angebote</h3>
                <p>PDF erstellen &amp; per E-Mail senden</p>
            </div>
        </div>
        <div class="pp-feature pp-fade-in pp-fade-in-3">
            <div class="pp-feature-icon purple"><i class="ri-bar-chart-2-line"></i></div>
            <div>
                <h3>DATEV &amp; Export</h3>
                <p>CSV, Excel, DATEV f&uuml;r Buchhalter</p>
            </div>
        </div>
        <div class="pp-feature pp-fade-in pp-fade-in-4">
            <div class="pp-feature-icon amber"><i class="ri-hand-coin-line"></i></div>
            <div>
                <h3>Digitaler Ankauf</h3>
                <p>Kaufvertr&auml;ge f&uuml;r Handy, KFZ &amp; mehr</p>
            </div>
        </div>
        <div class="pp-feature pp-fade-in pp-fade-in-5">
            <div class="pp-feature-icon rose"><i class="ri-team-line"></i></div>
            <div>
                <h3>Kundenverwaltung</h3>
                <p>Alle Kunden &amp; Historie im Blick</p>
            </div>
        </div>
        <div class="pp-feature pp-fade-in pp-fade-in-6">
            <div class="pp-feature-icon teal"><i class="ri-check-double-line"></i></div>
            <div>
                <h3>Jede Branche</h3>
                <p>Handy, PC, KFZ, Fahrrad &amp; mehr</p>
            </div>
        </div>
    </div>
</div>

<!-- ============ FORM ============ -->
<div class="pp-form-section" id="register">
    <div class="pp-form-header pp-fade-in">
        <h2>In 2 Minuten startklar</h2>
        <p>Erstellen Sie jetzt Ihr kostenloses Reparaturformular</p>
    </div>

    <div class="pp-reg-card pp-fade-in">

        <!-- Registration Form -->
        <form id="pp-reg-form" class="pp-reg-form" autocomplete="off" novalidate>

            <!-- Business Details -->
            <div class="pp-reg-section">
                <div class="pp-reg-section-head">
                    <div class="pp-reg-section-icon"><i class="ri-store-2-line"></i></div>
                    <h3>Gesch&auml;ftsdaten</h3>
                </div>

                <div class="pp-reg-field">
                    <label for="rr-shop-name">Firmenname / Shopname *</label>
                    <input type="text" id="rr-shop-name" name="shop_name" required placeholder="z.B. Meister Reparatur Berlin" autocomplete="organization">
                </div>

                <div class="pp-reg-field">
                    <label for="rr-owner-name">Inhaber / Name *</label>
                    <input type="text" id="rr-owner-name" name="owner_name" required placeholder="Max Mustermann" autocomplete="name">
                </div>

                <div class="pp-reg-row">
                    <div class="pp-reg-field">
                        <label for="rr-address">Stra&szlig;e &amp; Nr.</label>
                        <input type="text" id="rr-address" name="address" placeholder="Hauptstr. 1" autocomplete="street-address">
                    </div>
                    <div class="pp-reg-field pp-reg-field-sm">
                        <label for="rr-plz">PLZ</label>
                        <input type="text" id="rr-plz" name="plz" placeholder="89415" maxlength="5" autocomplete="postal-code">
                    </div>
                </div>

                <div class="pp-reg-field">
                    <label for="rr-city">Stadt</label>
                    <input type="text" id="rr-city" name="city" placeholder="Lauingen" autocomplete="address-level2">
                </div>

                <div class="pp-reg-row">
                    <div class="pp-reg-field">
                        <label for="rr-phone">Telefon</label>
                        <input type="tel" id="rr-phone" name="phone" placeholder="+49 123 456789" autocomplete="tel">
                    </div>
                    <div class="pp-reg-field">
                        <label for="rr-tax-id">USt-IdNr.</label>
                        <input type="text" id="rr-tax-id" name="tax_id" placeholder="DE123456789" autocomplete="nope">
                    </div>
                </div>
            </div>

            <!-- Login Credentials -->
            <div class="pp-reg-section">
                <div class="pp-reg-section-head">
                    <div class="pp-reg-section-icon"><i class="ri-lock-line"></i></div>
                    <h3>Zugangsdaten</h3>
                </div>

                <div class="pp-reg-field">
                    <label for="rr-email">E-Mail-Adresse *</label>
                    <input type="email" id="rr-email" name="email" required placeholder="info@ihr-shop.de" autocomplete="email">
                </div>

                <div class="pp-reg-field">
                    <label for="rr-password">Passwort * <span style="font-weight:400;color:#9ca3af;">(min. 6 Zeichen)</span></label>
                    <input type="password" id="rr-password" name="password" required minlength="6" placeholder="Sicheres Passwort" autocomplete="new-password">
                </div>

                <div class="pp-reg-field">
                    <label for="rr-password2">Passwort best&auml;tigen *</label>
                    <input type="password" id="rr-password2" name="password2" required minlength="6" placeholder="Passwort wiederholen" autocomplete="new-password">
                </div>
            </div>

            <!-- Terms -->
            <div class="pp-reg-terms">
                <label>
                    <input type="checkbox" id="rr-terms" required>
                    <span>Ich akzeptiere die <a href="/datenschutz" target="_blank">Datenschutzerkl&auml;rung</a> und <a href="/agb" target="_blank">AGB</a></span>
                </label>
            </div>

            <!-- Submit -->
            <button type="submit" id="rr-submit" class="pp-reg-submit">
                <i class="ri-rocket-2-line"></i> Kostenlos registrieren
            </button>

            <!-- Error -->
            <div id="rr-error" class="pp-reg-error pp-hidden"></div>
        </form>

        <!-- Success Screen (hidden by default) -->
        <div id="rr-success" class="pp-reg-success pp-hidden">
            <div class="pp-reg-success-icon"><i class="ri-check-line"></i></div>
            <h2>Registrierung erfolgreich!</h2>
            <p>Ihr Reparaturformular ist bereit:</p>
            <div class="pp-reg-success-link">
                <div class="pp-reg-link-label">Ihr Formular-Link</div>
                <a id="rr-form-url" href="#" target="_blank"></a>
            </div>
            <p class="pp-reg-success-info">Sie erhalten alle Zugangsdaten per E-Mail.</p>
            <div class="pp-reg-success-actions">
                <a href="/formular/admin" class="pp-reg-btn-primary"><i class="ri-dashboard-line"></i> Zum Admin-Bereich</a>
                <a id="rr-form-link" href="#" class="pp-reg-btn-secondary" target="_blank"><i class="ri-external-link-line"></i> Formular testen</a>
            </div>
        </div>

        <!-- Login Link -->
        <div id="rr-login-row" class="pp-reg-login-link">
            Bereits registriert? <a href="/formular/admin/login">Hier einloggen &rarr;</a>
        </div>
    </div>
</div>

<!-- ============ FOOTER ============ -->
<div class="pp-reg-footer">
    <div class="pp-reg-footer-trust">
        <div class="pp-reg-footer-trust-item"><i class="ri-lock-line"></i> SSL-verschl&uuml;sselt</div>
        <div class="pp-reg-footer-trust-item"><i class="ri-shield-check-line"></i> DSGVO-konform</div>
    </div>
    <div class="pp-reg-footer-links">
        <a href="/datenschutz">Datenschutz</a>
        <span class="pp-reg-footer-dot"></span>
        <a href="/agb">AGB</a>
        <span class="pp-reg-footer-dot"></span>
        <a href="/impressum">Impressum</a>
    </div>
    <div class="pp-reg-footer-powered">
        Powered by <a href="https://punktepass.de">PunktePass</a>
    </div>
</div>

<script>
(function() {
    'use strict';

    var AJAX_URL = <?php echo json_encode($ajax_url); ?>;
    var NONCE    = <?php echo json_encode($nonce); ?>;

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
            submitBtn.innerHTML = '<span class="pp-reg-spinner"></span> Wird erstellt...';
        } else {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ri-rocket-2-line"></i> Kostenlos registrieren';
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
        var address   = document.getElementById('rr-address').value.trim();
        var plz       = document.getElementById('rr-plz').value.trim();
        var city      = document.getElementById('rr-city').value.trim();
        var phone     = document.getElementById('rr-phone').value.trim();
        var taxId     = document.getElementById('rr-tax-id').value.trim();

        // Validation
        if (!shopName) { showError('Bitte geben Sie den Firmennamen ein.'); return; }
        if (!ownerName) { showError('Bitte geben Sie den Inhaber-Namen ein.'); return; }
        if (!email) { showError('Bitte geben Sie Ihre E-Mail-Adresse ein.'); return; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showError('Bitte geben Sie eine g\u00fcltige E-Mail-Adresse ein.'); return; }
        if (!password || password.length < 6) { showError('Das Passwort muss mindestens 6 Zeichen lang sein.'); return; }
        if (password !== password2) { showError('Die Passw\u00f6rter stimmen nicht \u00fcberein.'); return; }
        if (!terms) { showError('Bitte akzeptieren Sie die AGB und Datenschutzerkl\u00e4rung.'); return; }

        setLoading(true);

        var data = new FormData();
        data.append('action', 'ppv_repair_register');
        data.append('nonce', NONCE);
        data.append('shop_name', shopName);
        data.append('owner_name', ownerName);
        data.append('email', email);
        data.append('password', password);
        data.append('address', address);
        data.append('plz', plz);
        data.append('city', city);
        data.append('phone', phone);
        data.append('tax_id', taxId);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', AJAX_URL, true);
        xhr.onload = function() {
            setLoading(false);

            if (xhr.status !== 200) {
                showError('Serverfehler. Bitte versuchen Sie es sp\u00e4ter erneut.');
                return;
            }

            try {
                var res = JSON.parse(xhr.responseText);
            } catch (err) {
                showError('Unerwartete Antwort vom Server.');
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
                showError(res.data && res.data.message ? res.data.message : 'Registrierung fehlgeschlagen. Bitte versuchen Sie es erneut.');
            }
        };
        xhr.onerror = function() {
            setLoading(false);
            showError('Netzwerkfehler. Bitte pr\u00fcfen Sie Ihre Internetverbindung.');
        };
        xhr.send(data);
    });
})();
</script>

<!-- Cookie Consent Banner -->
<div id="cookieConsent" style="display:none; position:fixed; bottom:0; left:0; right:0; background:rgba(30,41,59,0.97); color:#fff; padding:16px 24px; z-index:9999; box-shadow:0 -4px 20px rgba(0,0,0,0.15); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px);">
    <div style="max-width:1200px; margin:0 auto; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px;">
        <div style="flex:1; min-width:280px;">
            <p style="margin:0; font-size:14px; line-height:1.5;">
                <strong>Cookie-Hinweis:</strong> Wir verwenden Cookies und Google Analytics, um unsere Website zu verbessern.
                <a href="https://punktepass.de/datenschutz" target="_blank" style="color:#93c5fd; text-decoration:underline;">Datenschutzerkl&auml;rung</a>
            </p>
        </div>
        <div style="display:flex; gap:12px; flex-shrink:0;">
            <button onclick="rejectCookies()" style="padding:10px 20px; background:transparent; border:1px solid rgba(255,255,255,0.3); color:#fff; border-radius:8px; cursor:pointer; font-size:14px; transition:all 0.2s;">Ablehnen</button>
            <button onclick="acceptCookies()" style="padding:10px 24px; background:linear-gradient(135deg,#667eea,#4338ca); border:none; color:#fff; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600; transition:all 0.2s;">Akzeptieren</button>
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
