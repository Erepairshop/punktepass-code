<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Bottom Navigation (v6.7 Fixed)
 * ✅ User + Händler kompatibilis
 * ✅ Session Engine v4 támogatás
 * ✅ Multi-Filiale Safe
 * ✅ POS-Tablet kompatibilis
 * ✅ Neon Blue Theme
 * ✅ Linkek NEM interceptálódnak!
 * Author: PunktePass / Erik Borota
 */

class PPV_Bottom_Nav {

    public static function hooks() {
        add_shortcode('ppv_bottom_nav', [__CLASS__, 'render_nav']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 99);
        add_action('wp_footer', [__CLASS__, 'inject_context'], 1);
    }

    /** ============================================================
     * Assets (CSS + JS)
     * ============================================================ */
    public static function enqueue_assets() {
        // RemixIcons loaded globally in punktepass.php

        // 🚫 SPA navigation PERMANENTLY DISABLED - too many plugin conflicts
        // Use normal page navigation instead (LiteSpeed cache helps with speed)

        wp_enqueue_style(
            'ppv-bottom-nav',
            plugins_url('assets/css/ppv-bottom-nav.css', dirname(__FILE__)),
            [],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/ppv-bottom-nav.css')
        );

        // Simple inline JS for active icon + feedback modal only
        wp_register_script('ppv-bottom-nav', false, ['jquery'], time(), true);
        wp_add_inline_script('ppv-bottom-nav', self::inline_js());
        wp_enqueue_script('ppv-bottom-nav');
    }

    /** ============================================================
     * Global Store Context Injection (Session v4 Safe)
     * ============================================================ */
    public static function inject_context() {
        $store = $GLOBALS['ppv_active_store'] ?? (class_exists('PPV_Session') ? PPV_Session::current_store() : null);

        $store_id  = $store->id        ?? 0;
        $store_key = $store->store_key ?? '';
        $pos       = (function_exists('ppv_is_pos') && ppv_is_pos()) ? 'true' : 'false';
        $token     = sanitize_text_field($_COOKIE['ppv_pos_token'] ?? '');

        echo '<script>window.PPV_PROFILE = ' . wp_json_encode([
            'store_id'  => intval($store_id),
            'store_key' => $store_key,
            'pos'       => ($pos === 'true'),
            'token'     => $token,
            'url'       => get_site_url(),
            'debug'     => false,
        ]) . ';</script>';
    }

    /** ============================================================
     * Render Navigation (User vagy Händler)
     * ============================================================ */
    public static function render_nav() {
        // Session indítása ha kell
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $user  = wp_get_current_user();
        $roles = (array)$user->roles;

        // 🔹 Session alapú vendor-ellenőrzés
        $is_vendor = false;
        $is_pos    = function_exists('ppv_is_pos') && ppv_is_pos();

        // 1. WordPress role check
        if (in_array('vendor', $roles) || in_array('pp_vendor', $roles) || current_user_can('manage_options')) {
            $is_vendor = true;
        }

        // 2. PPV SESSION check - KRITIKUS a trial usereknek!
        if (!$is_vendor && !empty($_SESSION['ppv_user_type']) && in_array($_SESSION['ppv_user_type'], ['vendor', 'store', 'handler', 'admin'])) {
            $is_vendor = true;
        }

        // 2b. Trial handler check - ppv_vendor_store_id
        if (!$is_vendor && !empty($_SESSION['ppv_vendor_store_id'])) {
            $is_vendor = true;
        }

        // 3. Global store check
        if (!$is_vendor && isset($GLOBALS['ppv_active_store']) && !empty($GLOBALS['ppv_active_store']->id)) {
            $is_vendor = true;
        }

        // 4. PPV_Session check
        if (!$is_vendor && class_exists('PPV_Session')) {
            $store = PPV_Session::current_store();
            if (!empty($store) && !empty($store->id)) {
                $is_vendor = true;
            }
        }

        // 🔹 URL ellenőrzés - ha user_dashboard, akkor USER nav!
        $current_path = $_SERVER['REQUEST_URI'] ?? '';
        $is_user_page = (
            strpos($current_path, '/user_dashboard') !== false ||
            strpos($current_path, '/meine-punkte') !== false ||
            strpos($current_path, '/belohnungen') !== false ||
            strpos($current_path, '/einstellungen') !== false ||
            strpos($current_path, '/punkte') !== false
        );

        ob_start();

        // Check if scanner user (limited navigation)
        $is_scanner = (class_exists('PPV_Permissions') && PPV_Permissions::is_scanner_user());

        // Determine user type for feedback modal
        $feedback_user_type = 'user';
        if ($is_vendor || $is_pos) {
            $feedback_user_type = 'handler';
        }

        // --- User navigáció (prioritás!) ---
        // 🚀 Pure SPA navigation - no page refresh
        if ($is_user_page): ?>
            <nav class="ppv-bottom-nav ppv-nav-labeled" data-nav-type="user">
                <a href="/user_dashboard" class="nav-item" data-spa="true" title="<?php echo esc_attr(PPV_Lang::t('nav_dashboard')); ?>"><i class="ri-home-smile-2-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_dashboard')); ?></span></a>
                <a href="/meine-punkte" class="nav-item" data-spa="true" title="<?php echo esc_attr(PPV_Lang::t('nav_my_points')); ?>"><i class="ri-donut-chart-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_my_points')); ?></span></a>
                <a href="/belohnungen" class="nav-item" data-spa="true" title="<?php echo esc_attr(PPV_Lang::t('nav_rewards')); ?>"><i class="ri-coupon-3-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_rewards')); ?></span></a>
                <a href="/einstellungen" class="nav-item" data-spa="true" title="<?php echo esc_attr(PPV_Lang::t('nav_settings')); ?>"><i class="ri-settings-3-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_settings')); ?></span></a>
                <a href="#" class="nav-item" id="ppv-feedback-nav-btn" title="<?php echo esc_attr(PPV_Lang::t('nav_feedback')); ?>"><i class="ri-feedback-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_feedback')); ?></span></a>
            </nav>
            <?php self::render_feedback_modal('user'); ?>
        <?php
        // --- Scanner navigáció (simplified for scanner users) ---
        elseif ($is_scanner): ?>
            <nav class="ppv-bottom-nav ppv-nav-labeled" data-nav-type="scanner">
                <a href="/qr-center" class="nav-item" data-spa="true" title="<?php echo esc_attr(PPV_Lang::t('nav_scanner')); ?>"><i class="ri-qr-scan-2-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_scanner')); ?></span></a>
                <a href="/mein-profil" class="nav-item" data-spa="true" title="<?php echo esc_attr(PPV_Lang::t('nav_profile')); ?>"><i class="ri-user-3-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_profile')); ?></span></a>
                <a href="#" class="nav-item" id="ppv-ai-support-nav-btn" title="<?php echo esc_attr(PPV_Lang::t('nav_support') ?: 'Support'); ?>"><i class="ri-sparkling-2-fill"></i><span><?php echo esc_html(PPV_Lang::t('nav_support') ?: 'Support'); ?></span></a>
                <a href="#" class="nav-item" id="ppv-feedback-nav-btn" title="<?php echo esc_attr(PPV_Lang::t('nav_feedback')); ?>"><i class="ri-feedback-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_feedback')); ?></span></a>
            </nav>
            <?php if (class_exists('PPV_AI_Support')) { PPV_AI_Support::render_widget(); } ?>
            <?php self::render_feedback_modal('handler'); ?>
        <?php
        // --- Händler / POS navigáció ---
        elseif ($is_vendor || $is_pos): ?>
            <nav class="ppv-bottom-nav ppv-nav-labeled" data-nav-type="handler">
                <a href="/qr-center" class="nav-item" data-spa="true" title="<?php echo esc_attr(PPV_Lang::t('nav_start')); ?>"><i class="ri-home-smile-2-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_start')); ?></span></a>
                <a href="/rewards" class="nav-item" data-spa="true" title="<?php echo esc_attr(PPV_Lang::t('nav_rewards')); ?>"><i class="ri-coupon-3-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_rewards')); ?></span></a>
                <a href="/mein-profil" class="nav-item" data-spa="true" title="<?php echo esc_attr(PPV_Lang::t('nav_profile')); ?>"><i class="ri-user-3-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_profile')); ?></span></a>
                <a href="/statistik" class="nav-item" data-spa="true" title="<?php echo esc_attr(PPV_Lang::t('nav_statistics')); ?>"><i class="ri-bar-chart-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_statistics')); ?></span></a>
                <a href="#" class="nav-item" id="ppv-ai-support-nav-btn" title="<?php echo esc_attr(PPV_Lang::t('nav_support') ?: 'Support'); ?>"><i class="ri-sparkling-2-fill"></i><span><?php echo esc_html(PPV_Lang::t('nav_support') ?: 'Support'); ?></span></a>
            </nav>
            <?php if (class_exists('PPV_AI_Support')) { PPV_AI_Support::render_widget(); } ?>
        <?php
        // --- Alap user nav (basic users) ---
        else: ?>
            <nav class="ppv-bottom-nav ppv-nav-labeled" data-nav-type="user">
                <a href="/user_dashboard" class="nav-item" data-spa="true" title="<?php echo esc_attr(PPV_Lang::t('nav_dashboard')); ?>"><i class="ri-home-smile-2-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_dashboard')); ?></span></a>
                <a href="/meine-punkte" class="nav-item" data-spa="true" title="<?php echo esc_attr(PPV_Lang::t('nav_my_points')); ?>"><i class="ri-donut-chart-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_my_points')); ?></span></a>
                <a href="/belohnungen" class="nav-item" data-spa="true" title="<?php echo esc_attr(PPV_Lang::t('nav_rewards')); ?>"><i class="ri-coupon-3-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_rewards')); ?></span></a>
                <a href="/einstellungen" class="nav-item" data-spa="true" title="<?php echo esc_attr(PPV_Lang::t('nav_settings')); ?>"><i class="ri-settings-3-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_settings')); ?></span></a>
                <a href="#" class="nav-item" id="ppv-feedback-nav-btn" title="<?php echo esc_attr(PPV_Lang::t('nav_feedback')); ?>"><i class="ri-feedback-line"></i><span><?php echo esc_html(PPV_Lang::t('nav_feedback')); ?></span></a>
            </nav>
            <?php self::render_feedback_modal('user'); ?>
        <?php
        endif;

        return ob_get_clean();
    }

    /** ============================================================
     * 💬 FEEDBACK MODAL - Professional Feedback System
     * Categories: Bug, Feature, Question, Rating
     * Auto-captures: Device, Page, User Type
     * Multi-language: DE, HU, EN
     * ============================================================ */
    private static function render_feedback_modal($user_type = 'user') {
        // Get current language
        $lang = isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : 'en';
        if (!in_array($lang, ['de', 'hu', 'en'])) $lang = 'en';

        // Translations
        $T = [
            'de' => [
                'title' => 'Feedback',
                'subtitle' => 'Was möchten Sie uns mitteilen?',
                'bug' => 'Fehler melden',
                'feature' => 'Idee / Wunsch',
                'question' => 'Frage',
                'rating' => 'Bewertung',
                'back' => 'Zurück',
                'rating_q' => 'Wie zufrieden sind Sie mit PunktePass?',
                'placeholder' => 'Beschreiben Sie Ihr Anliegen...',
                'email_placeholder' => 'E-Mail (optional)',
                'send' => 'Absenden',
                'thanks' => 'Vielen Dank!',
                'sent' => 'Ihr Feedback wurde gesendet.',
            ],
            'hu' => [
                'title' => 'Visszajelzés',
                'subtitle' => 'Mit szeretne nekünk írni?',
                'bug' => 'Hiba bejelentés',
                'feature' => 'Ötlet / Kívánság',
                'question' => 'Kérdés',
                'rating' => 'Értékelés',
                'back' => 'Vissza',
                'rating_q' => 'Mennyire elégedett a PunktePass-szal?',
                'placeholder' => 'Írja le a kérését...',
                'email_placeholder' => 'E-mail (opcionális)',
                'send' => 'Küldés',
                'thanks' => 'Köszönjük!',
                'sent' => 'Visszajelzését elküldtük.',
            ],
            'en' => [
                'title' => 'Feedback',
                'subtitle' => 'What would you like to tell us?',
                'bug' => 'Report Bug',
                'feature' => 'Idea / Wish',
                'question' => 'Question',
                'rating' => 'Rating',
                'back' => 'Back',
                'rating_q' => 'How satisfied are you with PunktePass?',
                'placeholder' => 'Describe your request...',
                'email_placeholder' => 'Email (optional)',
                'send' => 'Send',
                'thanks' => 'Thank you!',
                'sent' => 'Your feedback has been sent.',
            ],
        ];
        $t = $T[$lang];
        ?>
        <div id="ppv-feedback-modal" class="ppv-feedback-modal" data-user-type="<?php echo esc_attr($user_type); ?>" data-lang="<?php echo esc_attr($lang); ?>">
            <div class="ppv-feedback-content">
                <!-- Header -->
                <div class="ppv-feedback-header">
                    <h3><i class="ri-feedback-line"></i> <?php echo esc_html($t['title']); ?></h3>
                    <button id="ppv-feedback-close" class="ppv-feedback-close"><i class="ri-close-line"></i></button>
                </div>

                <!-- Category Selection (Step 1) -->
                <div id="ppv-feedback-step1" class="ppv-feedback-step active">
                    <p class="ppv-feedback-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
                    <div class="ppv-feedback-categories"><button class="ppv-feedback-cat" data-category="bug"><i class="ri-bug-line"></i><span><?php echo esc_html($t['bug']); ?></span></button><button class="ppv-feedback-cat" data-category="feature"><i class="ri-lightbulb-line"></i><span><?php echo esc_html($t['feature']); ?></span></button><button class="ppv-feedback-cat" data-category="question"><i class="ri-question-line"></i><span><?php echo esc_html($t['question']); ?></span></button><button class="ppv-feedback-cat" data-category="rating"><i class="ri-star-line"></i><span><?php echo esc_html($t['rating']); ?></span></button></div>
                </div>

                <!-- Feedback Form (Step 2) -->
                <div id="ppv-feedback-step2" class="ppv-feedback-step">
                    <button id="ppv-feedback-back" class="ppv-feedback-back">
                        <i class="ri-arrow-left-line"></i> <?php echo esc_html($t['back']); ?>
                    </button>

                    <div id="ppv-feedback-cat-header" class="ppv-feedback-cat-header">
                        <i class="ri-bug-line"></i>
                        <span><?php echo esc_html($t['bug']); ?></span>
                    </div>

                    <!-- Rating (only for rating category) -->
                    <div id="ppv-feedback-rating-section" class="ppv-feedback-rating" style="display: none;">
                        <p><?php echo esc_html($t['rating_q']); ?></p>
                        <div class="ppv-feedback-stars"><button class="ppv-star" data-rating="1"><i class="ri-star-line"></i></button><button class="ppv-star" data-rating="2"><i class="ri-star-line"></i></button><button class="ppv-star" data-rating="3"><i class="ri-star-line"></i></button><button class="ppv-star" data-rating="4"><i class="ri-star-line"></i></button><button class="ppv-star" data-rating="5"><i class="ri-star-line"></i></button></div>
                    </div>

                    <textarea id="ppv-feedback-message" class="ppv-feedback-input" placeholder="<?php echo esc_attr($t['placeholder']); ?>" rows="4"></textarea>

                    <!-- Contact (optional for users, required for handlers) -->
                    <?php if ($user_type === 'handler'): ?>
                    <input type="email" id="ppv-feedback-email" class="ppv-feedback-input" placeholder="<?php echo esc_attr($t['email_placeholder']); ?>">
                    <?php endif; ?>

                    <!-- Auto-captured context (hidden) -->
                    <input type="hidden" id="ppv-feedback-page" value="">
                    <input type="hidden" id="ppv-feedback-device" value="">
                    <input type="hidden" id="ppv-feedback-category" value="">
                    <input type="hidden" id="ppv-feedback-rating-value" value="0">

                    <div id="ppv-feedback-msg" class="ppv-feedback-message"></div>

                    <button id="ppv-feedback-send" class="ppv-feedback-submit">
                        <i class="ri-send-plane-line"></i> <?php echo esc_html($t['send']); ?>
                    </button>
                </div>

                <!-- Success (Step 3) -->
                <div id="ppv-feedback-step3" class="ppv-feedback-step">
                    <div class="ppv-feedback-success">
                        <i class="ri-checkbox-circle-line"></i>
                        <h4><?php echo esc_html($t['thanks']); ?></h4>
                        <p><?php echo esc_html($t['sent']); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /** ============================================================
     * JS – Aktív ikon kijelölés + Turbo Navigation + Feedback Modal
     * ============================================================ */
    public static function inline_js() {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('ppv_feedback_nonce');

        return "
        (function() {
            // 🚀 Initialize everything
            function initAll() {
                const \$ = jQuery;
                const currentPath = window.location.pathname.replace(/\/+\$/, '');

                // Aktív ikon megjelölése
                \$('.ppv-bottom-nav .nav-item').removeClass('active');
                \$('.ppv-bottom-nav .nav-item').each(function() {
                    const href = \$(this).attr('href');
                    if (href && href !== '#') {
                        const cleanHref = href.replace(/\/+\$/, '');
                        if (currentPath === cleanHref || currentPath.startsWith(cleanHref)) {
                            \$(this).addClass('active');
                        }
                    }
                });

                // Smooth icon hover feedback
                \$('.ppv-bottom-nav .nav-item').off('touchstart mousedown touchend mouseup');
                \$('.ppv-bottom-nav .nav-item').on('touchstart mousedown', function() {
                    \$(this).addClass('touch');
                }).on('touchend mouseup', function() {
                    \$(this).removeClass('touch');
                });
            }

            // ============ FEEDBACK MODAL (Event Delegation - Turbo Safe) ============
            let feedbackEventsInitialized = false;

            // 🔧 Store state in variables (more reliable than hidden inputs)
            let feedbackState = {
                category: '',
                rating: 0,
                pageUrl: '',
                deviceInfo: ''
            };

            function initFeedbackModal() {
                if (feedbackEventsInitialized) return;
                feedbackEventsInitialized = true;

                const \$ = jQuery;
                const lang = \$('#ppv-feedback-modal').data('lang') || 'de';

                const translations = {
                    de: {
                        bug: { icon: 'ri-bug-line', text: 'Fehler melden', placeholder: 'Was ist passiert? Beschreiben Sie den Fehler...' },
                        feature: { icon: 'ri-lightbulb-line', text: 'Idee / Wunsch', placeholder: 'Was wünschen Sie sich? Beschreiben Sie Ihre Idee...' },
                        question: { icon: 'ri-question-line', text: 'Frage', placeholder: 'Was möchten Sie wissen?' },
                        rating: { icon: 'ri-star-line', text: 'Bewertung', placeholder: 'Zusätzliche Anmerkungen (optional)...' }
                    },
                    hu: {
                        bug: { icon: 'ri-bug-line', text: 'Hiba bejelentés', placeholder: 'Mi történt? Írja le a hibát...' },
                        feature: { icon: 'ri-lightbulb-line', text: 'Ötlet / Kívánság', placeholder: 'Mit szeretne? Írja le az ötletét...' },
                        question: { icon: 'ri-question-line', text: 'Kérdés', placeholder: 'Mit szeretne tudni?' },
                        rating: { icon: 'ri-star-line', text: 'Értékelés', placeholder: 'További megjegyzések (opcionális)...' }
                    },
                    en: {
                        bug: { icon: 'ri-bug-line', text: 'Report Bug', placeholder: 'What happened? Describe the bug...' },
                        feature: { icon: 'ri-lightbulb-line', text: 'Idea / Wish', placeholder: 'What do you want? Describe your idea...' },
                        question: { icon: 'ri-question-line', text: 'Question', placeholder: 'What would you like to know?' },
                        rating: { icon: 'ri-star-line', text: 'Rating', placeholder: 'Additional notes (optional)...' }
                    }
                };
                const catMeta = translations[lang] || translations.de;

                // Error messages by language
                const errorMsgs = {
                    de: { selectRating: 'Bitte wählen Sie eine Bewertung.', describeIssue: 'Bitte beschreiben Sie Ihr Anliegen.', selectCategory: 'Bitte wählen Sie eine Kategorie.', networkError: 'Netzwerkfehler', sendError: 'Fehler beim Senden' },
                    hu: { selectRating: 'Kérjük válasszon értékelést.', describeIssue: 'Kérjük írja le a kérését.', selectCategory: 'Kérjük válasszon kategóriát.', networkError: 'Hálózati hiba', sendError: 'Küldési hiba' },
                    en: { selectRating: 'Please select a rating.', describeIssue: 'Please describe your request.', selectCategory: 'Please select a category.', networkError: 'Network error', sendError: 'Error sending' }
                };
                const errors = errorMsgs[lang] || errorMsgs.de;

                function resetModal() {
                    feedbackState.category = '';
                    feedbackState.rating = 0;
                    \$('#ppv-feedback-step1').addClass('active');
                    \$('#ppv-feedback-step2, #ppv-feedback-step3').removeClass('active');
                    \$('#ppv-feedback-message').val('');
                    \$('#ppv-feedback-email').val('');
                    \$('.ppv-star').removeClass('active');
                    \$('#ppv-feedback-rating-section').hide();
                    \$('#ppv-feedback-msg').removeClass('show error success').text('');
                }

                function getDeviceInfo() {
                    const ua = navigator.userAgent;
                    let device = 'Desktop';
                    if (/iPhone|iPad|iPod/.test(ua)) device = 'iOS';
                    else if (/Android/.test(ua)) device = 'Android';
                    else if (/Windows/.test(ua)) device = 'Windows';
                    else if (/Mac/.test(ua)) device = 'macOS';

                    let browser = 'Unknown';
                    if (/Chrome/.test(ua) && !/Edg/.test(ua)) browser = 'Chrome';
                    else if (/Safari/.test(ua) && !/Chrome/.test(ua)) browser = 'Safari';
                    else if (/Firefox/.test(ua)) browser = 'Firefox';
                    else if (/Edg/.test(ua)) browser = 'Edge';

                    return device + ' / ' + browser;
                }

                // Open modal
                \$(document).on('click', '#ppv-feedback-nav-btn', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    resetModal();
                    feedbackState.pageUrl = window.location.pathname;
                    feedbackState.deviceInfo = getDeviceInfo();
                    \$('#ppv-feedback-modal').addClass('show');
                });

                // Close modal
                \$(document).on('click', '#ppv-feedback-close', function() {
                    \$('#ppv-feedback-modal').removeClass('show');
                });

                // Close on backdrop
                \$(document).on('click', '#ppv-feedback-modal', function(e) {
                    if (e.target === this) \$(this).removeClass('show');
                });

                // Category selection
                \$(document).on('click', '.ppv-feedback-cat', function() {
                    const cat = \$(this).data('category');
                    if (!cat || !catMeta[cat]) return;

                    const meta = catMeta[cat];
                    feedbackState.category = cat;
                    feedbackState.rating = 0;
                    \$('.ppv-star').removeClass('active');

                    \$('#ppv-feedback-cat-header').html('<i class=\"' + meta.icon + '\"></i><span>' + meta.text + '</span>');
                    \$('#ppv-feedback-message').attr('placeholder', meta.placeholder);

                    // Show/hide rating section
                    if (cat === 'rating') {
                        \$('#ppv-feedback-rating-section').show();
                    } else {
                        \$('#ppv-feedback-rating-section').hide();
                    }

                    \$('#ppv-feedback-step1').removeClass('active');
                    \$('#ppv-feedback-step2').addClass('active');
                    \$('#ppv-feedback-msg').removeClass('show error success').text('');
                    setTimeout(function() { \$('#ppv-feedback-message').focus(); }, 100);
                });

                // Back button
                \$(document).on('click', '#ppv-feedback-back', function() {
                    \$('#ppv-feedback-step2').removeClass('active');
                    \$('#ppv-feedback-step1').addClass('active');
                    \$('#ppv-feedback-msg').removeClass('show error success').text('');
                });

                // Star rating
                \$(document).on('click', '.ppv-star', function() {
                    const rating = parseInt(\$(this).data('rating')) || 0;
                    feedbackState.rating = rating;
                    \$('.ppv-star').removeClass('active');
                    \$('.ppv-star').each(function() {
                        if (parseInt(\$(this).data('rating')) <= rating) {
                            \$(this).addClass('active');
                        }
                    });
                    \$('#ppv-feedback-msg').removeClass('show error success').text('');
                });

                // Submit feedback
                \$(document).on('click', '#ppv-feedback-send', function() {
                    const \$btn = \$(this);
                    const \$msg = \$('#ppv-feedback-msg');
                    const message = \$('#ppv-feedback-message').val().trim();
                    const category = feedbackState.category;
                    const rating = feedbackState.rating;
                    const userType = \$('#ppv-feedback-modal').data('user-type') || 'user';

                    // Validate category
                    if (!category) {
                        \$msg.removeClass('success').addClass('error show').text(errors.selectCategory);
                        return;
                    }

                    // Validate rating for rating category
                    if (category === 'rating' && rating < 1) {
                        \$msg.removeClass('success').addClass('error show').text(errors.selectRating);
                        return;
                    }

                    // Validate message for non-rating categories
                    if (!message && category !== 'rating') {
                        \$msg.removeClass('success').addClass('error show').text(errors.describeIssue);
                        \$('#ppv-feedback-message').focus();
                        return;
                    }

                    \$btn.prop('disabled', true).html('<i class=\"ri-loader-4-line ri-spin\"></i> Senden...');
                    \$msg.removeClass('show error success').text('');

                    \$.ajax({
                        url: '{$ajax_url}',
                        type: 'POST',
                        data: {
                            action: 'ppv_submit_feedback',
                            nonce: '{$nonce}',
                            category: category,
                            message: message,
                            rating: rating,
                            email: \$('#ppv-feedback-email').val() || '',
                            page_url: feedbackState.pageUrl,
                            device_info: feedbackState.deviceInfo,
                            user_type: userType
                        },
                        success: function(res) {
                            if (res.success) {
                                \$('#ppv-feedback-step2').removeClass('active');
                                \$('#ppv-feedback-step3').addClass('active');
                                setTimeout(function() {
                                    \$('#ppv-feedback-modal').removeClass('show');
                                }, 2000);
                            } else {
                                \$msg.removeClass('success').addClass('error show').text(res.data?.message || errors.sendError);
                            }
                        },
                        error: function() {
                            \$msg.removeClass('success').addClass('error show').text(errors.networkError);
                        },
                        complete: function() {
                            \$btn.prop('disabled', false).html('<i class=\"ri-send-plane-line\"></i> Absenden');
                        }
                    });
                });
            }

            // Initialize on DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    initAll();
                    initFeedbackModal();
                });
            } else {
                initAll();
                initFeedbackModal();
            }

            // 🚀 Reinitialize nav after Turbo navigation (Support modal uses event delegation, no reinit needed)
            document.addEventListener('turbo:load', initAll);

            // 🚀 Show loading indicator
            document.addEventListener('turbo:before-fetch-request', function() {
                document.body.classList.add('turbo-loading');
            });
            document.addEventListener('turbo:render', function() {
                document.body.classList.remove('turbo-loading');
            });
        })();
        ";
    }
}

PPV_Bottom_Nav::hooks();