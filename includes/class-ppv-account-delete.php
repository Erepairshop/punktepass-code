<?php
/**
 * PunktePass - Account Deletion Page
 * GDPR-compliant account deletion request page
 * Multi-language Support (DE/HU/RO)
 * Author: PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Account_Delete {

    /** ============================================================
     * 🔹 Hooks
     * ============================================================ */
    public static function hooks() {
        add_shortcode('ppv_account_delete', [__CLASS__, 'render_delete_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /** ============================================================
     * 🔹 Asset Loading
     * ============================================================ */
    public static function enqueue_assets() {
        global $post;
        if (!isset($post->post_content) || !has_shortcode($post->post_content, 'ppv_account_delete')) {
            return;
        }

        // Google Fonts - Inter (DISABLED for performance - uses system fonts)
        // wp_enqueue_style(
        //     'ppv-inter-font',
        //     'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
        //     [],
        //     null
        // );

        // 🔹 ALWAYS USE LIGHT CSS (contains all dark mode styles via body.ppv-dark selectors)
        wp_enqueue_style(
            'ppv-theme-light',
            PPV_PLUGIN_URL . 'assets/css/ppv-theme-light.css',
            [],
            defined('PPV_VERSION') ? PPV_VERSION : time()
        );
    }

    /** ============================================================
     * 🔹 Render Account Deletion Page
     * ============================================================ */
    public static function render_delete_page() {
        // Get current language
        $lang = self::get_current_lang();

        // Multi-language content
        $content = [
            'de' => [
                'title' => 'Konto löschen',
                'intro' => 'Wenn du dein PunktePass-Konto und alle zugehörigen Daten löschen möchtest, kannst du dies jederzeit anfordern.',
                'how_to_title' => 'So kannst du dein Konto löschen lassen:',
                'step_1' => 'Sende eine E-Mail an <strong>info@punktepass.de</strong>',
                'step_2' => 'Gib die E-Mail-Adresse an, mit der du dein PunktePass-Konto erstellt hast',
                'step_3' => 'Schreibe kurz „Konto löschen" dazu',
                'data_title' => 'Welche Daten werden gelöscht:',
                'data_items' => [
                    'Benutzerkonto',
                    'Punktehistorie',
                    'Rewards',
                    'QR-Token',
                    'Gerätetokens',
                    'Login-Daten'
                ],
                'footer' => 'Die Löschung erfolgt innerhalb von <strong>30 Tagen</strong> gemäß DSGVO.',
                'back_button' => 'Zurück zur Startseite'
            ],
            'hu' => [
                'title' => 'Fiók törlése',
                'intro' => 'Ha törölni szeretnéd a PunktePass fiókodat és az összes hozzá tartozó adatot, bármikor kérheted.',
                'how_to_title' => 'Így törölheted a fiókodat:',
                'step_1' => 'Küldj egy e-mailt a <strong>info@punktepass.de</strong> címre',
                'step_2' => 'Add meg azt az e-mail címet, amellyel létrehoztad a PunktePass fiókodat',
                'step_3' => 'Írd be, hogy „Fiók törlése"',
                'data_title' => 'Mely adatok kerülnek törlésre:',
                'data_items' => [
                    'Felhasználói fiók',
                    'Pontok története',
                    'Jutalmak',
                    'QR-token',
                    'Eszköz tokenek',
                    'Bejelentkezési adatok'
                ],
                'footer' => 'A törlés <strong>30 napon belül</strong> megtörténik a GDPR szerint.',
                'back_button' => 'Vissza a főoldalra'
            ],
            'ro' => [
                'title' => 'Șterge contul',
                'intro' => 'Dacă dorești să ștergi contul tău PunktePass și toate datele asociate, poți solicita acest lucru oricând.',
                'how_to_title' => 'Cum poți șterge contul:',
                'step_1' => 'Trimite un e-mail la <strong>info@punktepass.de</strong>',
                'step_2' => 'Specifică adresa de e-mail cu care ai creat contul PunktePass',
                'step_3' => 'Scrie scurt „Șterge contul"',
                'data_title' => 'Ce date vor fi șterse:',
                'data_items' => [
                    'Contul de utilizator',
                    'Istoricul punctelor',
                    'Recompense',
                    'Token QR',
                    'Token-uri dispozitiv',
                    'Date de autentificare'
                ],
                'footer' => 'Ștergerea va avea loc în termen de <strong>30 de zile</strong> conform GDPR.',
                'back_button' => 'Înapoi la pagina principală'
            ],
            'en' => [
                'title' => 'Delete Account',
                'intro' => 'If you want to delete your PunktePass account and all associated data, you can request this at any time.',
                'how_to_title' => 'How to delete your account:',
                'step_1' => 'Send an email to <strong>info@punktepass.de</strong>',
                'step_2' => 'Provide the email address you used to create your PunktePass account',
                'step_3' => 'Write "Delete account" in the message',
                'data_title' => 'What data will be deleted:',
                'data_items' => [
                    'User account',
                    'Points history',
                    'Rewards',
                    'QR token',
                    'Device tokens',
                    'Login data'
                ],
                'footer' => 'Deletion will take place within <strong>30 days</strong> in accordance with GDPR.',
                'back_button' => 'Back to home page'
            ]
        ];

        // Get content for current language, fallback to German
        $t = $content[$lang] ?? $content['de'];

        ob_start();
        ?>
        <style>
        body.ppv-user-dashboard {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--pp-bg, #f8fafc);
            color: var(--pp-text, #1e293b);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .ppv-delete-container {
            max-width: 800px;
            margin: 60px auto;
            padding: 20px;
        }

        .ppv-delete-card {
            background: var(--pp-surface, #ffffff);
            border: 1px solid var(--pp-border, rgba(0, 168, 204, 0.18));
            border-radius: 16px;
            padding: 40px;
            box-shadow: var(--pp-shadow, 0 4px 14px rgba(0,0,0,0.08));
        }

        .ppv-delete-card h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--pp-text, #1e293b);
            margin: 0 0 20px 0;
            text-align: center;
        }

        .ppv-delete-intro {
            font-size: 16px;
            line-height: 1.6;
            color: var(--pp-text-2, #475569);
            margin-bottom: 30px;
            text-align: center;
        }

        .ppv-delete-section {
            margin: 30px 0;
        }

        .ppv-delete-section h2 {
            font-size: 20px;
            font-weight: 600;
            color: var(--pp-text, #1e293b);
            margin: 0 0 15px 0;
            border-left: 4px solid var(--pp-primary, #00a8cc);
            padding-left: 12px;
        }

        .ppv-delete-steps {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .ppv-delete-steps li {
            padding: 12px 0;
            padding-left: 32px;
            position: relative;
            font-size: 15px;
            line-height: 1.6;
            color: var(--pp-text-2, #475569);
        }

        .ppv-delete-steps li:before {
            content: '✓';
            position: absolute;
            left: 0;
            top: 12px;
            width: 24px;
            height: 24px;
            background: var(--pp-primary, #00a8cc);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        .ppv-delete-data-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin: 20px 0;
        }

        .ppv-delete-data-item {
            background: var(--pp-bg-2, #f1f5f9);
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--pp-text-2, #475569);
            border-left: 3px solid var(--pp-primary, #00a8cc);
        }

        .ppv-delete-footer {
            margin-top: 30px;
            padding: 20px;
            background: var(--pp-bg-2, #f1f5f9);
            border-radius: 12px;
            text-align: center;
            font-size: 15px;
            line-height: 1.6;
            color: var(--pp-text-2, #475569);
        }

        .ppv-delete-back {
            margin-top: 30px;
            text-align: center;
        }

        .ppv-delete-back a {
            display: inline-block;
            padding: 12px 32px;
            background: var(--pp-primary, #00a8cc);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .ppv-delete-back a:hover {
            background: var(--pp-primary-dark, #0077a8);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 168, 204, 0.3);
        }

        @media (max-width: 768px) {
            .ppv-delete-container {
                margin: 20px auto;
                padding: 15px;
            }

            .ppv-delete-card {
                padding: 25px 20px;
            }

            .ppv-delete-card h1 {
                font-size: 24px;
            }

            .ppv-delete-data-list {
                grid-template-columns: 1fr;
            }
        }
        </style>

        <div class="ppv-delete-container">
            <div class="ppv-delete-card">
                <h1><?php echo esc_html($t['title']); ?></h1>

                <p class="ppv-delete-intro">
                    <?php echo esc_html($t['intro']); ?>
                </p>

                <div class="ppv-delete-section">
                    <h2><?php echo esc_html($t['how_to_title']); ?></h2>
                    <ul class="ppv-delete-steps">
                        <li><?php echo wp_kses_post($t['step_1']); ?></li>
                        <li><?php echo esc_html($t['step_2']); ?></li>
                        <li><?php echo esc_html($t['step_3']); ?></li>
                    </ul>
                </div>

                <div class="ppv-delete-section">
                    <h2><?php echo esc_html($t['data_title']); ?></h2>
                    <div class="ppv-delete-data-list">
                        <?php foreach ($t['data_items'] as $item): ?>
                            <div class="ppv-delete-data-item">
                                <?php echo esc_html($item); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ppv-delete-footer">
                    <?php echo wp_kses_post($t['footer']); ?>
                </div>

                <div class="ppv-delete-back">
                    <a href="<?php echo esc_url(home_url('/')); ?>">
                        <?php echo esc_html($t['back_button']); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /** ============================================================
     * 🔹 Get Current Language (Cookie > GET > Locale)
     * ============================================================ */
    private static function get_current_lang() {
        static $lang = null;
        if ($lang !== null) return $lang;

        // 1. Check cookie
        if (isset($_COOKIE['ppv_lang'])) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang']);
        }
        // 2. Check GET parameter
        elseif (isset($_GET['lang'])) {
            $lang = sanitize_text_field($_GET['lang']);
        }
        // 3. Fallback to locale
        else {
            $lang = substr(get_locale(), 0, 2);
        }

        // Validate (only allow de, hu, ro) - default Romanian
        if (!in_array($lang, ['de', 'hu', 'ro', 'en'])) {
            $lang = 'ro';
        }

        return $lang;
    }
}

PPV_Account_Delete::hooks();
