<?php
/**
 * PPV ROI Calculator
 * Standalone landing page at /rechner for potential customers
 */

if (!defined('ABSPATH')) exit;

class PPV_ROI_Calculator {

    public static function hooks() {
        add_shortcode('ppv_roi_calculator', [__CLASS__, 'render_calculator']);
        add_action('init', [__CLASS__, 'add_rewrite_rules']);
        add_action('template_redirect', [__CLASS__, 'handle_rechner_page']);
    }

    /**
     * Add rewrite rule for /rechner
     */
    public static function add_rewrite_rules() {
        add_rewrite_rule('^rechner/?$', 'index.php?ppv_rechner=1', 'top');
        add_rewrite_tag('%ppv_rechner%', '1');
    }

    /**
     * Handle /rechner page directly (no WordPress page needed)
     */
    public static function handle_rechner_page() {
        global $wp_query;

        if (get_query_var('ppv_rechner') == 1) {
            self::render_standalone_page();
            exit;
        }
    }

    /**
     * Render standalone calculator page (no WP theme)
     */
    public static function render_standalone_page() {
        $lang = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : 'en';

        // Translations
        $t = self::get_translations($lang);

        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo esc_html($t['page_title']); ?></title>
    <meta name="description" content="<?php echo esc_attr($t['meta_description']); ?>">

    <!-- PWA Meta -->
    <meta name="theme-color" content="#0b0f17">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <!-- Fonts - Google Fonts DISABLED for performance, using system fonts -->
    <!-- <link rel="preconnect" href="https://fonts.googleapis.com"> -->
    <!-- <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin> -->
    <!-- <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"> -->

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg: #0b0f17;
            --card-bg: #151a25;
            --card-border: #1e2533;
            --text: #ffffff;
            --text-muted: #8892a4;
            --accent: #00bfff;
            --accent-glow: rgba(0, 191, 255, 0.3);
            --success: #22c55e;
            --warning: #f59e0b;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.5;
            padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
        }

        .container {
            max-width: 480px;
            margin: 0 auto;
            padding: 24px 16px;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin-bottom: 16px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #fff 0%, #00bfff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: var(--text-muted);
            font-size: 15px;
        }

        /* Card */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title .icon {
            font-size: 24px;
        }

        /* Slider */
        .slider-container {
            margin-bottom: 24px;
        }

        .slider-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .slider-label span {
            color: var(--text-muted);
            font-size: 14px;
        }

        .slider-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--accent);
        }

        .slider-value small {
            font-size: 16px;
            font-weight: 400;
            color: var(--text-muted);
        }

        input[type="range"] {
            width: 100%;
            height: 8px;
            -webkit-appearance: none;
            appearance: none;
            background: linear-gradient(to right, var(--accent) 0%, var(--accent) var(--progress), #2a3142 var(--progress), #2a3142 100%);
            border-radius: 4px;
            outline: none;
            cursor: pointer;
        }

        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 24px;
            height: 24px;
            background: var(--accent);
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 0 20px var(--accent-glow);
            transition: transform 0.2s;
        }

        input[type="range"]::-webkit-slider-thumb:hover {
            transform: scale(1.1);
        }

        input[type="range"]::-moz-range-thumb {
            width: 24px;
            height: 24px;
            background: var(--accent);
            border-radius: 50%;
            cursor: pointer;
            border: none;
            box-shadow: 0 0 20px var(--accent-glow);
        }

        /* Results */
        .results {
            display: grid;
            gap: 16px;
        }

        .result-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            background: rgba(0, 191, 255, 0.05);
            border: 1px solid rgba(0, 191, 255, 0.15);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .result-item:hover {
            background: rgba(0, 191, 255, 0.1);
            transform: translateX(4px);
        }

        .result-icon {
            font-size: 28px;
            flex-shrink: 0;
        }

        .result-content {
            flex: 1;
        }

        .result-title {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .result-value {
            font-size: 18px;
            font-weight: 600;
        }

        .result-value.highlight {
            color: var(--success);
        }

        /* Summary Box */
        .summary-box {
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.15) 0%, rgba(34, 197, 94, 0.15) 100%);
            border: 1px solid rgba(0, 191, 255, 0.3);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            margin-top: 24px;
        }

        .summary-title {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .summary-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--success);
            margin-bottom: 8px;
        }

        .summary-subtitle {
            font-size: 15px;
            color: var(--text);
        }

        /* Coffee Quote */
        .coffee-quote {
            text-align: center;
            padding: 20px;
            margin-top: 8px;
        }

        .coffee-quote .icon {
            font-size: 40px;
            margin-bottom: 12px;
        }

        .coffee-quote .price {
            font-size: 24px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 8px;
        }

        .coffee-quote .text {
            color: var(--text-muted);
            font-size: 14px;
        }

        /* CTA Button */
        .cta-button {
            display: block;
            width: 100%;
            padding: 18px 24px;
            background: linear-gradient(135deg, #00bfff 0%, #0099cc 100%);
            color: #fff;
            font-size: 17px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 191, 255, 0.3);
            margin-top: 24px;
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 30px rgba(0, 191, 255, 0.4);
        }

        .cta-button:active {
            transform: translateY(0);
        }

        /* Features List */
        .features {
            margin-top: 32px;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--card-border);
        }

        .feature-item:last-child {
            border-bottom: none;
        }

        .feature-icon {
            font-size: 20px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .feature-text {
            font-size: 14px;
            color: var(--text-muted);
        }

        .feature-text strong {
            color: var(--text);
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 24px;
            color: var(--text-muted);
            font-size: 13px;
        }

        .footer a {
            color: var(--accent);
            text-decoration: none;
        }

        /* Language Switcher */
        .lang-switcher {
            position: fixed;
            top: 12px;
            right: 12px;
            z-index: 1000;
            display: flex;
            gap: 4px;
            background: rgba(21, 26, 37, 0.95);
            padding: 5px;
            border-radius: 10px;
            border: 1px solid var(--card-border);
            backdrop-filter: blur(10px);
        }
        .lang-btn {
            padding: 6px 12px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
        }
        .lang-btn:hover { background: rgba(255,255,255,0.1); color: var(--text); }
        .lang-btn.active { background: linear-gradient(135deg, #00bfff, #0099cc); color: white; }

        /* Animations */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card {
            animation: slideUp 0.5s ease forwards;
        }

        .card:nth-child(2) {
            animation-delay: 0.1s;
        }

        .card:nth-child(3) {
            animation-delay: 0.2s;
        }

        /* Number animation */
        .animate-number {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <!-- Language Switcher -->
    <div class="lang-switcher">
        <a href="?lang=de" class="lang-btn <?php echo $lang === 'de' ? 'active' : ''; ?>">DE</a>
        <a href="?lang=hu" class="lang-btn <?php echo $lang === 'hu' ? 'active' : ''; ?>">HU</a>
        <a href="?lang=ro" class="lang-btn <?php echo $lang === 'ro' ? 'active' : ''; ?>">RO</a>
    </div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <img src="https://punktepass.de/wp-content/plugins/punktepass/assets/img/logo.webp" alt="PunktePass" class="logo" width="60" height="60">
            <h1><?php echo esc_html($t['headline']); ?></h1>
            <p><?php echo esc_html($t['subheadline']); ?></p>
        </div>

        <!-- Calculator Card -->
        <div class="card">
            <div class="card-title">
                <span class="icon">🧮</span>
                <?php echo esc_html($t['calculator_title']); ?>
            </div>

            <div class="slider-container">
                <div class="slider-label">
                    <span><?php echo esc_html($t['customers_per_month']); ?></span>
                </div>
                <div class="slider-value">
                    <span id="customer-count">100</span>
                    <small><?php echo esc_html($t['customers_unit']); ?></small>
                </div>
                <input type="range" id="customer-slider" min="20" max="500" value="100" step="10">
            </div>
        </div>

        <!-- Results Card -->
        <div class="card">
            <div class="card-title">
                <span class="icon">📈</span>
                <?php echo esc_html($t['results_title']); ?>
            </div>

            <div class="results">
                <div class="result-item">
                    <span class="result-icon">📍</span>
                    <div class="result-content">
                        <div class="result-title"><?php echo esc_html($t['discovery_title']); ?></div>
                        <div class="result-value highlight">+<span id="discovery-count">5</span> <?php echo esc_html($t['new_customers']); ?></div>
                    </div>
                </div>

                <div class="result-item">
                    <span class="result-icon">👋</span>
                    <div class="result-content">
                        <div class="result-title"><?php echo esc_html($t['comeback_title']); ?></div>
                        <div class="result-value highlight">+<span id="comeback-count">12</span> <?php echo esc_html($t['customers_unit']); ?></div>
                    </div>
                </div>

                <div class="result-item">
                    <span class="result-icon">🎂</span>
                    <div class="result-content">
                        <div class="result-title"><?php echo esc_html($t['birthday_title']); ?></div>
                        <div class="result-value highlight">+<span id="birthday-count">8</span> <?php echo esc_html($t['visits_unit']); ?></div>
                    </div>
                </div>

                <div class="result-item">
                    <span class="result-icon">⭐</span>
                    <div class="result-content">
                        <div class="result-title"><?php echo esc_html($t['vip_title']); ?></div>
                        <div class="result-value highlight">+<span id="vip-percent">20</span>% <?php echo esc_html($t['more_visits']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div class="summary-box">
                <div class="summary-title"><?php echo esc_html($t['total_extra']); ?></div>
                <div class="summary-value">+<span id="total-extra">25</span></div>
                <div class="summary-subtitle"><?php echo esc_html($t['extra_visits_month']); ?></div>
            </div>
        </div>

        <!-- Coffee Quote -->
        <div class="coffee-quote">
            <div class="icon">☕</div>
            <div class="price">€1 / <?php echo esc_html($t['per_day']); ?></div>
            <div class="text"><?php echo esc_html($t['cheaper_than_coffee']); ?></div>
        </div>

        <!-- CTA Button -->
        <a href="mailto:info@punktepass.de?subject=<?php echo rawurlencode($t['email_subject']); ?>" class="cta-button">
            <?php echo esc_html($t['cta_button']); ?>
        </a>

        <!-- Features -->
        <div class="card features">
            <div class="card-title">
                <span class="icon">✨</span>
                <?php echo esc_html($t['included_title']); ?>
            </div>

            <div class="feature-item">
                <span class="feature-icon">📱</span>
                <div class="feature-text">
                    <strong><?php echo esc_html($t['feature_1_title']); ?></strong><br>
                    <?php echo esc_html($t['feature_1_desc']); ?>
                </div>
            </div>

            <div class="feature-item">
                <span class="feature-icon">🎁</span>
                <div class="feature-text">
                    <strong><?php echo esc_html($t['feature_2_title']); ?></strong><br>
                    <?php echo esc_html($t['feature_2_desc']); ?>
                </div>
            </div>

            <div class="feature-item">
                <span class="feature-icon">📊</span>
                <div class="feature-text">
                    <strong><?php echo esc_html($t['feature_3_title']); ?></strong><br>
                    <?php echo esc_html($t['feature_3_desc']); ?>
                </div>
            </div>

            <div class="feature-item">
                <span class="feature-icon">🔐</span>
                <div class="feature-text">
                    <strong><?php echo esc_html($t['feature_4_title']); ?></strong><br>
                    <?php echo esc_html($t['feature_4_desc']); ?>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>© <?php echo date('Y'); ?> <a href="https://punktepass.de">PunktePass</a></p>
            <p style="margin-top: 8px;">
                <a href="https://punktepass.de/impressum">Impressum</a> ·
                <a href="https://punktepass.de/datenschutz">Datenschutz</a>
            </p>
        </div>
    </div>

    <script>
        // Calculator Logic
        const slider = document.getElementById('customer-slider');
        const customerCount = document.getElementById('customer-count');
        const discoveryCount = document.getElementById('discovery-count');
        const comebackCount = document.getElementById('comeback-count');
        const birthdayCount = document.getElementById('birthday-count');
        const vipPercent = document.getElementById('vip-percent');
        const totalExtra = document.getElementById('total-extra');

        function updateProgress() {
            const value = slider.value;
            const min = slider.min;
            const max = slider.max;
            const progress = ((value - min) / (max - min)) * 100;
            slider.style.setProperty('--progress', progress + '%');
        }

        function animateNumber(element, newValue) {
            const currentValue = parseInt(element.textContent) || 0;
            const diff = newValue - currentValue;
            const steps = 10;
            const stepValue = diff / steps;
            let step = 0;

            const interval = setInterval(() => {
                step++;
                element.textContent = Math.round(currentValue + (stepValue * step));
                if (step >= steps) {
                    element.textContent = newValue;
                    clearInterval(interval);
                }
            }, 30);
        }

        function calculate() {
            const customers = parseInt(slider.value);

            // Store Discovery: New customers who find the store in the app
            // Based on local PunktePass users seeing the store card
            // ~5% of customer base as potential new discovery (conservative estimate)
            const discovery = Math.round(customers * 0.05) + 3; // minimum 3 new customers

            // Comeback: ~10-15% of inactive customers return
            const comeback = Math.round(customers * 0.12);

            // Birthday: ~8% get birthday bonus
            const birthday = Math.round(customers * 0.08);

            // VIP: Additional 20% frequency increase (already shown as %)
            const vip = 20;

            // Total extra visits (discovery + comeback + birthday + vip frequency boost)
            const total = discovery + comeback + birthday + Math.round(customers * 0.05);

            // Update UI with animation
            animateNumber(customerCount, customers);
            animateNumber(discoveryCount, discovery);
            animateNumber(comebackCount, comeback);
            animateNumber(birthdayCount, birthday);
            animateNumber(totalExtra, total);

            updateProgress();
        }

        // Event listeners
        slider.addEventListener('input', calculate);
        slider.addEventListener('change', calculate);

        // Initial calculation
        calculate();

        // Touch feedback
        slider.addEventListener('touchstart', () => {
            slider.style.transform = 'scale(1.02)';
        });
        slider.addEventListener('touchend', () => {
            slider.style.transform = 'scale(1)';
        });
    </script>
</body>
</html>
        <?php
    }

    /**
     * Get translations
     */
    public static function get_translations($lang = 'de') {
        $translations = [
            'de' => [
                'page_title' => 'ROI Rechner | PunktePass',
                'meta_description' => 'Berechnen Sie, wie viele zusätzliche Kunden PunktePass für Ihr Geschäft bringen kann.',
                'headline' => 'ROI Rechner',
                'subheadline' => 'Berechnen Sie Ihren Mehrwert mit PunktePass',
                'calculator_title' => 'Ihre Kundenzahl',
                'customers_per_month' => 'Kunden pro Monat',
                'customers_unit' => 'Kunden',
                'visits_unit' => 'Besuche',
                'results_title' => 'Ihre Vorteile',
                'discovery_title' => 'App-Entdeckung',
                'new_customers' => 'Neukunden',
                'comeback_title' => 'Comeback-Kampagne',
                'birthday_title' => 'Geburtstags-Bonus',
                'vip_title' => 'VIP-Programm',
                'more_visits' => 'häufiger',
                'total_extra' => 'GESAMT EXTRA',
                'extra_visits_month' => 'zusätzliche Besuche / Monat',
                'per_day' => 'Tag',
                'cheaper_than_coffee' => 'Günstiger als ein Kaffee pro Tag',
                'cta_button' => 'Jetzt kostenlos testen',
                'email_subject' => 'PunktePass Anfrage',
                'included_title' => 'Alles inklusive',
                'feature_1_title' => 'Digitale Kundenkarte',
                'feature_1_desc' => 'Apple Wallet & Google Pay kompatibel',
                'feature_2_title' => 'Belohnungssystem',
                'feature_2_desc' => 'Flexible Prämien und VIP-Stufen',
                'feature_3_title' => 'Statistiken',
                'feature_3_desc' => 'Detaillierte Kundenanalyse',
                'feature_4_title' => 'DSGVO-konform',
                'feature_4_desc' => 'Deutsche Server, volle Datensicherheit',
            ],
            'hu' => [
                'page_title' => 'ROI Kalkulátor | PunktePass',
                'meta_description' => 'Számítsa ki, hány extra vásárlót hozhat a PunktePass az Ön üzletének.',
                'headline' => 'ROI Kalkulátor',
                'subheadline' => 'Számolja ki a PunktePass előnyeit',
                'calculator_title' => 'Vásárlói szám',
                'customers_per_month' => 'Vásárló havonta',
                'customers_unit' => 'vásárló',
                'visits_unit' => 'látogatás',
                'results_title' => 'Az Ön előnyei',
                'discovery_title' => 'App felfedezés',
                'new_customers' => 'új vásárló',
                'comeback_title' => 'Comeback kampány',
                'birthday_title' => 'Születésnapi bónusz',
                'vip_title' => 'VIP program',
                'more_visits' => 'gyakrabban',
                'total_extra' => 'ÖSSZESEN EXTRA',
                'extra_visits_month' => 'extra látogatás / hónap',
                'per_day' => 'nap',
                'cheaper_than_coffee' => 'Olcsóbb mint egy kávé naponta',
                'cta_button' => 'Ingyenes próba',
                'email_subject' => 'PunktePass érdeklődés',
                'included_title' => 'Minden benne van',
                'feature_1_title' => 'Digitális törzskártya',
                'feature_1_desc' => 'Apple Wallet & Google Pay kompatibilis',
                'feature_2_title' => 'Jutalomrendszer',
                'feature_2_desc' => 'Rugalmas jutalmak és VIP szintek',
                'feature_3_title' => 'Statisztikák',
                'feature_3_desc' => 'Részletes vásárlói elemzés',
                'feature_4_title' => 'GDPR megfelelő',
                'feature_4_desc' => 'Európai szerverek, teljes adatbiztonság',
            ],
            'ro' => [
                'page_title' => 'Calculator ROI | PunktePass',
                'meta_description' => 'Calculați câți clienți suplimentari poate aduce PunktePass pentru afacerea dvs.',
                'headline' => 'Calculator ROI',
                'subheadline' => 'Calculați avantajele PunktePass',
                'calculator_title' => 'Numărul de clienți',
                'customers_per_month' => 'Clienți pe lună',
                'customers_unit' => 'clienți',
                'visits_unit' => 'vizite',
                'results_title' => 'Avantajele dvs.',
                'discovery_title' => 'Descoperire în App',
                'new_customers' => 'clienți noi',
                'comeback_title' => 'Campanie Comeback',
                'birthday_title' => 'Bonus ziua de naștere',
                'vip_title' => 'Program VIP',
                'more_visits' => 'mai frecvent',
                'total_extra' => 'TOTAL EXTRA',
                'extra_visits_month' => 'vizite suplimentare / lună',
                'per_day' => 'zi',
                'cheaper_than_coffee' => 'Mai ieftin decât o cafea pe zi',
                'cta_button' => 'Testează gratuit',
                'email_subject' => 'Solicitare PunktePass',
                'included_title' => 'Totul inclus',
                'feature_1_title' => 'Card de fidelitate digital',
                'feature_1_desc' => 'Compatibil Apple Wallet & Google Pay',
                'feature_2_title' => 'Sistem de recompense',
                'feature_2_desc' => 'Recompense flexibile și niveluri VIP',
                'feature_3_title' => 'Statistici',
                'feature_3_desc' => 'Analiză detaliată a clienților',
                'feature_4_title' => 'Conform GDPR',
                'feature_4_desc' => 'Servere europene, securitate completă a datelor',
            ],
        ];

        return $translations[$lang] ?? $translations['de'];
    }

    /**
     * Shortcode for embedding calculator in WordPress pages
     */
    public static function render_calculator($atts = []) {
        $atts = shortcode_atts([
            'lang' => 'de',
        ], $atts);

        ob_start();
        self::render_standalone_page();
        return ob_get_clean();
    }
}

// Initialize
PPV_ROI_Calculator::hooks();
