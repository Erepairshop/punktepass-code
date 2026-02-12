<?php
/**
 * PunktePass - Partner Pitch Page
 * Professional presentation page for potential wholesale partners
 * Route: /formular/partner
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Repair_Partner {

    public static function render() {
        $logo_url = PPV_PLUGIN_URL . 'assets/img/punktepass-repair-logo.svg';
        ?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reparaturpass Partner-Programm | PunktePass</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
    <?php echo PPV_SEO::get_favicon_links(); ?>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { font-size: 16px; scroll-behavior: smooth; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        a { color: #667eea; text-decoration: none; }

        /* ── Print styles ── */
        @media print {
            body { background: #fff; font-size: 11pt; }
            .pp-no-print { display: none !important; }
            .pp-section { break-inside: avoid; page-break-inside: avoid; }
            .pp-hero-partner { padding: 40px 20px; min-height: auto; }
            .pp-partner-container { max-width: 100%; }
        }

        /* ── Animations ── */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes slideRight {
            from { width: 0; }
            to { width: 100%; }
        }
        .pp-anim { opacity: 0; }
        .pp-anim.pp-visible { animation: fadeInUp 0.6s ease-out forwards; }
        .pp-anim-d1 { animation-delay: 0.1s; }
        .pp-anim-d2 { animation-delay: 0.2s; }
        .pp-anim-d3 { animation-delay: 0.3s; }
        .pp-anim-d4 { animation-delay: 0.4s; }
        .pp-anim-d5 { animation-delay: 0.5s; }
        .pp-anim-d6 { animation-delay: 0.6s; }

        /* ── Container ── */
        .pp-partner-container {
            max-width: 960px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ── Hero ── */
        .pp-hero-partner {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4338ca 60%, #667eea 100%);
            padding: 60px 24px 72px;
            text-align: center;
            position: relative;
            overflow: hidden;
            min-height: 420px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .pp-hero-partner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
        }
        .pp-hero-partner-inner {
            position: relative;
            z-index: 1;
            max-width: 680px;
        }
        .pp-hero-partner-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 100px;
            padding: 8px 20px;
            font-size: 13px;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
            margin-bottom: 28px;
        }
        .pp-hero-partner-badge i { font-size: 16px; color: #fde047; }
        .pp-hero-partner h1 {
            color: #fff;
            font-size: 42px;
            font-weight: 900;
            line-height: 1.15;
            margin-bottom: 18px;
            letter-spacing: -1px;
        }
        .pp-hero-partner h1 span {
            background: linear-gradient(135deg, #fde047, #facc15);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .pp-hero-partner-sub {
            color: rgba(255,255,255,0.8);
            font-size: 18px;
            max-width: 540px;
            margin: 0 auto;
            line-height: 1.7;
        }
        .pp-hero-partner-logo {
            height: 38px;
            margin-bottom: 20px;
            filter: brightness(0) invert(1);
        }

        /* ── Section base ── */
        .pp-section {
            padding: 64px 0;
        }
        .pp-section-alt {
            background: #fff;
        }
        .pp-section-title {
            font-size: 28px;
            font-weight: 800;
            color: #1e293b;
            text-align: center;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        .pp-section-sub {
            font-size: 15px;
            color: #64748b;
            text-align: center;
            max-width: 560px;
            margin: 0 auto 40px;
        }

        /* ── Problem Section ── */
        .pp-problem-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .pp-problem-card {
            background: #fff;
            border-radius: 16px;
            padding: 28px 24px;
            border: 1px solid #e2e8f0;
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }
        .pp-problem-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .pp-problem-icon.red { background: #fef2f2; color: #ef4444; }
        .pp-problem-icon.amber { background: #fffbeb; color: #f59e0b; }
        .pp-problem-card h4 {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .pp-problem-card p {
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
            margin: 0;
        }

        /* ── Solution Section ── */
        .pp-solution-hero {
            background: linear-gradient(135deg, #f0f2ff, #e8ecff);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            margin-bottom: 32px;
            border: 1px solid #ddd6fe;
        }
        .pp-solution-hero h3 {
            font-size: 22px;
            font-weight: 800;
            color: #4338ca;
            margin-bottom: 8px;
        }
        .pp-solution-hero p {
            font-size: 15px;
            color: #6366f1;
            max-width: 500px;
            margin: 0 auto;
        }
        .pp-solution-features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .pp-sol-feat {
            background: #fff;
            border-radius: 14px;
            padding: 24px 18px;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        .pp-sol-feat:hover {
            border-color: #c7d2fe;
            box-shadow: 0 4px 20px rgba(99,102,241,0.08);
        }
        .pp-sol-feat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin: 0 auto 12px;
        }
        .pp-sol-feat-icon.blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #3b82f6; }
        .pp-sol-feat-icon.green { background: linear-gradient(135deg, #f0fdf4, #dcfce7); color: #22c55e; }
        .pp-sol-feat-icon.purple { background: linear-gradient(135deg, #f5f3ff, #ede9fe); color: #8b5cf6; }
        .pp-sol-feat-icon.amber { background: linear-gradient(135deg, #fffbeb, #fef3c7); color: #f59e0b; }
        .pp-sol-feat-icon.rose { background: linear-gradient(135deg, #fff1f2, #fce7f3); color: #f43f5e; }
        .pp-sol-feat-icon.teal { background: linear-gradient(135deg, #f0fdfa, #ccfbf1); color: #14b8a6; }
        .pp-sol-feat h4 {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .pp-sol-feat p {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
            margin: 0;
        }

        /* ── Stats bar ── */
        .pp-stats-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 48px 0 0;
        }
        .pp-stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 24px 16px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .pp-stat-val {
            font-size: 32px;
            font-weight: 900;
            color: #4338ca;
            line-height: 1;
            margin-bottom: 4px;
        }
        .pp-stat-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }

        /* ── Win-Win Section ── */
        .pp-win-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .pp-win-card {
            border-radius: 20px;
            padding: 36px 28px;
            position: relative;
            overflow: hidden;
        }
        .pp-win-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            filter: blur(50px);
            opacity: 0.3;
        }
        .pp-win-card.partner {
            background: linear-gradient(135deg, #1e1b4b, #312e81);
            color: #fff;
        }
        .pp-win-card.partner::before { background: #818cf8; }
        .pp-win-card.customer {
            background: linear-gradient(135deg, #065f46, #047857);
            color: #fff;
        }
        .pp-win-card.customer::before { background: #34d399; }
        .pp-win-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 5px 12px;
            border-radius: 100px;
            margin-bottom: 18px;
            position: relative;
        }
        .pp-win-card.partner .pp-win-tag { background: rgba(255,255,255,0.15); color: #c7d2fe; }
        .pp-win-card.customer .pp-win-tag { background: rgba(255,255,255,0.15); color: #a7f3d0; }
        .pp-win-card h3 {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 16px;
            position: relative;
        }
        .pp-win-list {
            list-style: none;
            padding: 0;
            position: relative;
        }
        .pp-win-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 12px;
            opacity: 0.9;
        }
        .pp-win-list li i {
            font-size: 16px;
            margin-top: 2px;
            flex-shrink: 0;
        }
        .pp-win-card.partner .pp-win-list li i { color: #a5b4fc; }
        .pp-win-card.customer .pp-win-list li i { color: #6ee7b7; }

        /* ── Partnership Models ── */
        .pp-model-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .pp-model-card {
            background: #fff;
            border-radius: 16px;
            padding: 32px 24px;
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: all 0.3s;
            position: relative;
        }
        .pp-model-card:hover {
            border-color: #c7d2fe;
            transform: translateY(-4px);
            box-shadow: 0 12px 36px rgba(99,102,241,0.1);
        }
        .pp-model-card.recommended {
            border-color: #818cf8;
            box-shadow: 0 4px 20px rgba(99,102,241,0.12);
        }
        .pp-model-badge {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #667eea, #4338ca);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 100px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .pp-model-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 16px;
            background: linear-gradient(135deg, #f0f2ff, #e8ecff);
            color: #4338ca;
        }
        .pp-model-card h4 {
            font-size: 16px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .pp-model-card p {
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        .pp-model-detail {
            font-size: 12px;
            color: #94a3b8;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
            line-height: 1.5;
        }

        /* ── How it works ── */
        .pp-steps-partner {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            counter-reset: step;
        }
        .pp-step-card {
            background: #fff;
            border-radius: 14px;
            padding: 28px 18px;
            text-align: center;
            border: 1px solid #e2e8f0;
            position: relative;
        }
        .pp-step-num {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #4338ca);
            color: #fff;
            font-size: 15px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
        }
        .pp-step-card h4 {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .pp-step-card p {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
            margin: 0;
        }

        /* ── Pricing preview ── */
        .pp-pricing-partner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            max-width: 640px;
            margin: 0 auto;
        }
        .pp-price-box {
            background: #fff;
            border-radius: 16px;
            padding: 32px 28px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .pp-price-box.premium {
            border-color: #818cf8;
            background: linear-gradient(135deg, #fefeff, #f8f7ff);
        }
        .pp-price-tag {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 8px;
        }
        .pp-price-val {
            font-size: 36px;
            font-weight: 900;
            color: #1e293b;
            line-height: 1;
            margin-bottom: 4px;
        }
        .pp-price-val span {
            font-size: 15px;
            font-weight: 500;
            color: #94a3b8;
        }
        .pp-price-desc {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 16px;
        }
        .pp-price-list {
            list-style: none;
            padding: 0;
            text-align: left;
        }
        .pp-price-list li {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #475569;
            margin-bottom: 8px;
        }
        .pp-price-list li i { color: #22c55e; font-size: 15px; }

        /* ── CTA Section ── */
        .pp-cta-section {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4338ca 100%);
            padding: 72px 24px;
            text-align: center;
        }
        .pp-cta-section h2 {
            color: #fff;
            font-size: 32px;
            font-weight: 900;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        .pp-cta-section p {
            color: rgba(255,255,255,0.75);
            font-size: 16px;
            max-width: 480px;
            margin: 0 auto 32px;
        }
        .pp-cta-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .pp-cta-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 32px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s;
        }
        .pp-cta-btn.primary {
            background: #fff;
            color: #4338ca;
        }
        .pp-cta-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            text-decoration: none;
        }
        .pp-cta-btn.secondary {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .pp-cta-btn.secondary:hover {
            background: rgba(255,255,255,0.2);
            text-decoration: none;
        }

        /* ── Footer ── */
        .pp-partner-footer {
            text-align: center;
            padding: 28px 24px;
            font-size: 13px;
            color: #94a3b8;
        }
        .pp-partner-footer a { color: #667eea; }

        /* ── Print button ── */
        .pp-print-btn {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #4338ca);
            color: #fff;
            border: none;
            font-size: 22px;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(99,102,241,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            z-index: 100;
        }
        .pp-print-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 28px rgba(99,102,241,0.5);
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .pp-hero-partner { padding: 40px 20px 56px; min-height: auto; }
            .pp-hero-partner h1 { font-size: 28px; }
            .pp-hero-partner-sub { font-size: 15px; }
            .pp-section { padding: 48px 0; }
            .pp-section-title { font-size: 22px; }
            .pp-problem-grid { grid-template-columns: 1fr; }
            .pp-solution-features { grid-template-columns: 1fr 1fr; }
            .pp-stats-bar { grid-template-columns: 1fr 1fr; }
            .pp-win-grid { grid-template-columns: 1fr; }
            .pp-model-grid { grid-template-columns: 1fr; gap: 16px; }
            .pp-steps-partner { grid-template-columns: 1fr 1fr; }
            .pp-pricing-partner { grid-template-columns: 1fr; }
            .pp-cta-section h2 { font-size: 24px; }
        }
        @media (max-width: 480px) {
            .pp-hero-partner h1 { font-size: 24px; }
            .pp-solution-features { grid-template-columns: 1fr; }
            .pp-stats-bar { grid-template-columns: 1fr 1fr; }
            .pp-steps-partner { grid-template-columns: 1fr; }
            .pp-solution-hero { padding: 28px 20px; }
        }
    </style>
</head>
<body>

<!-- ============ HERO ============ -->
<div class="pp-hero-partner">
    <div class="pp-hero-partner-inner">
        <img src="<?php echo esc_url($logo_url); ?>" alt="Reparaturpass" class="pp-hero-partner-logo">
        <div class="pp-hero-partner-badge">
            <i class="ri-handshake-line"></i>
            Partner-Programm
        </div>
        <h1>Mehrwert f&uuml;r Ihre Kunden.<br><span>Kostenlos.</span></h1>
        <p class="pp-hero-partner-sub">
            Bieten Sie Ihren Werkstatt-Kunden eine professionelle digitale Reparaturverwaltung &ndash;
            v&ouml;llig kostenlos. Kein Risiko, kein Aufwand, maximaler Mehrwert.
        </p>
    </div>
</div>

<!-- ============ PROBLEM ============ -->
<div class="pp-section pp-section-alt">
    <div class="pp-partner-container">
        <h2 class="pp-section-title pp-anim">Das Problem Ihrer Kunden</h2>
        <p class="pp-section-sub pp-anim pp-anim-d1">Die meisten Reparaturwerkst&auml;tten arbeiten noch analog &ndash; das kostet Zeit, Geld und Kunden.</p>

        <div class="pp-problem-grid">
            <div class="pp-problem-card pp-anim pp-anim-d1">
                <div class="pp-problem-icon red"><i class="ri-file-paper-line"></i></div>
                <div>
                    <h4>Papier-Chaos</h4>
                    <p>Handschriftliche Reparaturzettel gehen verloren, sind unleserlich und nicht durchsuchbar.</p>
                </div>
            </div>
            <div class="pp-problem-card pp-anim pp-anim-d2">
                <div class="pp-problem-icon amber"><i class="ri-time-line"></i></div>
                <div>
                    <h4>Zeitverlust</h4>
                    <p>Jeder Auftrag wird manuell erfasst. Das kostet 5-10 Minuten pro Kunde &ndash; jeden Tag.</p>
                </div>
            </div>
            <div class="pp-problem-card pp-anim pp-anim-d3">
                <div class="pp-problem-icon red"><i class="ri-customer-service-line"></i></div>
                <div>
                    <h4>Kundenanfragen</h4>
                    <p>&ldquo;Wie ist der Status meiner Reparatur?&rdquo; &ndash; st&auml;ndige Anrufe, die den Betrieb st&ouml;ren.</p>
                </div>
            </div>
            <div class="pp-problem-card pp-anim pp-anim-d4">
                <div class="pp-problem-icon amber"><i class="ri-money-euro-circle-line"></i></div>
                <div>
                    <h4>Teure Software</h4>
                    <p>Professionelle L&ouml;sungen kosten oft &euro;50-100+/Monat &ndash; f&uuml;r kleine Werkst&auml;tten zu teuer.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============ SOLUTION ============ -->
<div class="pp-section">
    <div class="pp-partner-container">
        <h2 class="pp-section-title pp-anim">Unsere L&ouml;sung: Reparaturpass</h2>
        <p class="pp-section-sub pp-anim pp-anim-d1">Eine vollst&auml;ndige digitale Reparaturverwaltung &ndash; kostenlos nutzbar, sofort einsatzbereit.</p>

        <div class="pp-solution-hero pp-anim pp-anim-d2">
            <h3><i class="ri-smartphone-line"></i> Digitaler Reparaturbon</h3>
            <p>Kunden f&uuml;llen das Formular online aus. Die Werkstatt erh&auml;lt alle Daten digital &ndash; kein Papier, keine Fehler.</p>
        </div>

        <div class="pp-solution-features">
            <div class="pp-sol-feat pp-anim pp-anim-d1">
                <div class="pp-sol-feat-icon blue"><i class="ri-smartphone-line"></i></div>
                <h4>Online-Formular</h4>
                <p>Eigene URL pro Werkstatt mit individuellem Branding</p>
            </div>
            <div class="pp-sol-feat pp-anim pp-anim-d2">
                <div class="pp-sol-feat-icon green"><i class="ri-file-text-line"></i></div>
                <h4>Rechnungen & Bons</h4>
                <p>Automatische PDF-Erstellung mit Firmenlogo</p>
            </div>
            <div class="pp-sol-feat pp-anim pp-anim-d3">
                <div class="pp-sol-feat-icon purple"><i class="ri-bar-chart-2-line"></i></div>
                <h4>Statistiken</h4>
                <p>Dashboard mit CSV/PDF-Export aller Reparaturen</p>
            </div>
            <div class="pp-sol-feat pp-anim pp-anim-d4">
                <div class="pp-sol-feat-icon amber"><i class="ri-qr-code-line"></i></div>
                <h4>Status-Tracking</h4>
                <p>Kunden verfolgen ihren Auftrag per QR-Code</p>
            </div>
            <div class="pp-sol-feat pp-anim pp-anim-d5">
                <div class="pp-sol-feat-icon rose"><i class="ri-team-line"></i></div>
                <h4>Kundenverwaltung</h4>
                <p>Automatische Kundenhistorie und CRM-Funktionen</p>
            </div>
            <div class="pp-sol-feat pp-anim pp-anim-d6">
                <div class="pp-sol-feat-icon teal"><i class="ri-building-2-line"></i></div>
                <h4>Multi-Filiale</h4>
                <p>Mehrere Standorte unter einem Account verwalten</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="pp-stats-bar">
            <div class="pp-stat-card pp-anim pp-anim-d1">
                <div class="pp-stat-val">50</div>
                <div class="pp-stat-label">Formulare / Monat gratis</div>
            </div>
            <div class="pp-stat-card pp-anim pp-anim-d2">
                <div class="pp-stat-val">0&euro;</div>
                <div class="pp-stat-label">Einrichtungsgeb&uuml;hr</div>
            </div>
            <div class="pp-stat-card pp-anim pp-anim-d3">
                <div class="pp-stat-val">2 min</div>
                <div class="pp-stat-label">Registrierung</div>
            </div>
            <div class="pp-stat-card pp-anim pp-anim-d4">
                <div class="pp-stat-val">&infin;</div>
                <div class="pp-stat-label">Kein Vertrag, kein Limit</div>
            </div>
        </div>
    </div>
</div>

<!-- ============ WIN-WIN ============ -->
<div class="pp-section pp-section-alt">
    <div class="pp-partner-container">
        <h2 class="pp-section-title pp-anim">Win-Win Partnerschaft</h2>
        <p class="pp-section-sub pp-anim pp-anim-d1">Eine Partnerschaft, die beiden Seiten Mehrwert bringt &ndash; ohne Kosten.</p>

        <div class="pp-win-grid">
            <div class="pp-win-card partner pp-anim pp-anim-d2">
                <div class="pp-win-tag"><i class="ri-building-line"></i> F&uuml;r Sie als Gro&szlig;h&auml;ndler</div>
                <h3>Kundenbindung st&auml;rken</h3>
                <ul class="pp-win-list">
                    <li><i class="ri-check-line"></i> Ihre Kunden werden professioneller &ndash; und bestellen mehr bei Ihnen</li>
                    <li><i class="ri-check-line"></i> Sie positionieren sich als &ldquo;More Than Spareparts&rdquo;-Partner</li>
                    <li><i class="ri-check-line"></i> Kostenloser Mehrwert f&uuml;r Ihr Portfolio &ndash; kein Investment n&ouml;tig</li>
                    <li><i class="ri-check-line"></i> Co-Branded Pr&auml;senz: &ldquo;Empfohlen von [Ihr Name]&rdquo;</li>
                    <li><i class="ri-check-line"></i> Optionale Verg&uuml;tung bei Premium-Upgrades Ihrer Kunden</li>
                </ul>
            </div>
            <div class="pp-win-card customer pp-anim pp-anim-d3">
                <div class="pp-win-tag"><i class="ri-store-2-line"></i> F&uuml;r Ihre Werkstatt-Kunden</div>
                <h3>Sofort professioneller</h3>
                <ul class="pp-win-list">
                    <li><i class="ri-check-line"></i> Kostenlose digitale Reparaturverwaltung &ndash; sofort nutzbar</li>
                    <li><i class="ri-check-line"></i> Kein Papier-Chaos mehr &ndash; alles digital und durchsuchbar</li>
                    <li><i class="ri-check-line"></i> Professioneller Auftritt gegen&uuml;ber Endkunden</li>
                    <li><i class="ri-check-line"></i> Status-Tracking reduziert &ldquo;Wo ist mein Ger&auml;t?&rdquo;-Anrufe um 80%</li>
                    <li><i class="ri-check-line"></i> Spart 30+ Minuten t&auml;glich durch automatisierte Prozesse</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ============ PARTNERSHIP MODELS ============ -->
<div class="pp-section">
    <div class="pp-partner-container">
        <h2 class="pp-section-title pp-anim">Partnerschafts-Modelle</h2>
        <p class="pp-section-sub pp-anim pp-anim-d1">Flexibel und unverbindlich &ndash; w&auml;hlen Sie, was am besten passt.</p>

        <div class="pp-model-grid">
            <div class="pp-model-card pp-anim pp-anim-d1">
                <div class="pp-model-icon"><i class="ri-mail-send-line"></i></div>
                <h4>Newsletter & Webshop</h4>
                <p>Erw&auml;hnung in Ihrem Newsletter oder Banner in Ihrem Webshop. Minimaler Aufwand, sofortige Reichweite.</p>
                <div class="pp-model-detail">
                    <i class="ri-time-line"></i> 30 Min. Aufwand einmalig<br>
                    <i class="ri-money-euro-circle-line"></i> Kostenfrei
                </div>
            </div>
            <div class="pp-model-card recommended pp-anim pp-anim-d2">
                <div class="pp-model-badge">Empfohlen</div>
                <div class="pp-model-icon"><i class="ri-gift-line"></i></div>
                <h4>Paketbeileger</h4>
                <p>Ein kleiner Flyer in jeder Lieferung. Ihre Kunden entdecken den Reparaturpass beim Auspacken.</p>
                <div class="pp-model-detail">
                    <i class="ri-eye-line"></i> H&ouml;chste Sichtbarkeit<br>
                    <i class="ri-money-euro-circle-line"></i> Wir liefern die Flyer kostenlos
                </div>
            </div>
            <div class="pp-model-card pp-anim pp-anim-d3">
                <div class="pp-model-icon"><i class="ri-vip-crown-line"></i></div>
                <h4>Co-Branded</h4>
                <p>Eigene Partner-Landingpage mit Ihrem Logo. &ldquo;Empfohlen von [Ihr Unternehmen]&rdquo; im Reparaturpass.</p>
                <div class="pp-model-detail">
                    <i class="ri-star-line"></i> Exklusiv f&uuml;r Hauptpartner<br>
                    <i class="ri-money-euro-circle-line"></i> Revenue-Share m&ouml;glich
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============ HOW IT WORKS ============ -->
<div class="pp-section pp-section-alt">
    <div class="pp-partner-container">
        <h2 class="pp-section-title pp-anim">So einfach geht&rsquo;s</h2>
        <p class="pp-section-sub pp-anim pp-anim-d1">In 4 Schritten zur Partnerschaft &ndash; ohne Vertr&auml;ge, ohne Verpflichtungen.</p>

        <div class="pp-steps-partner">
            <div class="pp-step-card pp-anim pp-anim-d1">
                <div class="pp-step-num">1</div>
                <h4>Testen</h4>
                <p>Registrieren Sie sich selbst und erleben Sie den Reparaturpass aus Werkstatt-Sicht.</p>
            </div>
            <div class="pp-step-card pp-anim pp-anim-d2">
                <div class="pp-step-num">2</div>
                <h4>Vereinbaren</h4>
                <p>Wir besprechen, welches Modell am besten zu Ihnen passt.</p>
            </div>
            <div class="pp-step-card pp-anim pp-anim-d3">
                <div class="pp-step-num">3</div>
                <h4>Starten</h4>
                <p>Wir liefern Banner, Flyer oder Co-Branding &ndash; Sie teilen es mit Ihren Kunden.</p>
            </div>
            <div class="pp-step-card pp-anim pp-anim-d4">
                <div class="pp-step-num">4</div>
                <h4>Profitieren</h4>
                <p>Zufriedenere Kunden, st&auml;rkere Bindung &ndash; und optional Revenue-Share.</p>
            </div>
        </div>
    </div>
</div>

<!-- ============ PRICING PREVIEW ============ -->
<div class="pp-section">
    <div class="pp-partner-container">
        <h2 class="pp-section-title pp-anim">Preismodell f&uuml;r Werkst&auml;tten</h2>
        <p class="pp-section-sub pp-anim pp-anim-d1">Ihre Kunden starten kostenlos. Premium nur bei Bedarf.</p>

        <div class="pp-pricing-partner">
            <div class="pp-price-box pp-anim pp-anim-d1">
                <div class="pp-price-tag">Kostenlos</div>
                <div class="pp-price-val">0&euro; <span>/ Monat</span></div>
                <div class="pp-price-desc">F&uuml;r kleine Werkst&auml;tten</div>
                <ul class="pp-price-list">
                    <li><i class="ri-check-line"></i> 50 Formulare pro Monat</li>
                    <li><i class="ri-check-line"></i> Eigenes Reparaturformular</li>
                    <li><i class="ri-check-line"></i> Kunden-Tracking per QR</li>
                    <li><i class="ri-check-line"></i> Dashboard & &Uuml;bersicht</li>
                    <li><i class="ri-check-line"></i> Unbegrenzt nutzbar</li>
                </ul>
            </div>
            <div class="pp-price-box premium pp-anim pp-anim-d2">
                <div class="pp-price-tag">Premium</div>
                <div class="pp-price-val">39&euro; <span>/ Monat</span></div>
                <div class="pp-price-desc">F&uuml;r professionelle Betriebe</div>
                <ul class="pp-price-list">
                    <li><i class="ri-check-line"></i> Unbegrenzte Formulare</li>
                    <li><i class="ri-check-line"></i> Rechnungserstellung</li>
                    <li><i class="ri-check-line"></i> CSV/PDF Export</li>
                    <li><i class="ri-check-line"></i> Multi-Filiale Support</li>
                    <li><i class="ri-check-line"></i> Ankauf-Modul</li>
                    <li><i class="ri-check-line"></i> Priorit&auml;ts-Support</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ============ CTA ============ -->
<div class="pp-cta-section pp-no-print">
    <h2>Bereit f&uuml;r eine Partnerschaft?</h2>
    <p>Lassen Sie uns gemeinsam die Reparaturbranche digitalisieren. Kein Risiko, kein Aufwand &ndash; nur Mehrwert.</p>
    <div class="pp-cta-buttons">
        <a href="/formular" class="pp-cta-btn primary">
            <i class="ri-play-circle-line"></i> Live-Demo ansehen
        </a>
        <a href="mailto:info@punktepass.com?subject=Partnerschaft%20Reparaturpass" class="pp-cta-btn secondary">
            <i class="ri-mail-line"></i> Kontakt aufnehmen
        </a>
    </div>
</div>

<!-- ============ FOOTER ============ -->
<div class="pp-partner-footer">
    &copy; <?php echo date('Y'); ?> PunktePass &middot; <a href="/formular">reparaturpass.com</a>
    &middot; Dieses Dokument ist vertraulich.
</div>

<!-- Print Button -->
<button class="pp-print-btn pp-no-print" onclick="window.print()" title="Als PDF drucken">
    <i class="ri-printer-line"></i>
</button>

<!-- Scroll animations -->
<script>
(function(){
    var els = document.querySelectorAll('.pp-anim');
    if (!('IntersectionObserver' in window)) {
        els.forEach(function(e){ e.classList.add('pp-visible'); });
        return;
    }
    var io = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
            if (entry.isIntersecting) {
                entry.target.classList.add('pp-visible');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });
    els.forEach(function(e){ io.observe(e); });
})();
</script>

</body>
</html>
        <?php
        exit;
    }
}
