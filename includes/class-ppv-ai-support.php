<?php
/**
 * PunktePass AI Support Chat Widget
 * Floating chat for store owners/handlers - knows the full PunktePass system
 * Appears on all handler/admin pages via wp_footer hook
 *
 * Author: PunktePass / Erik Borota
 */

if (!defined('ABSPATH')) exit;

class PPV_AI_Support {

    public static function hooks() {
        // render_widget() is called directly from PPV_Bottom_Nav::render_nav() (handler section)
        // Same pattern as the feedback modal - rendered inside the shortcode output
        add_action('wp_ajax_ppv_ai_support_chat', [__CLASS__, 'ajax_chat']);
        add_action('wp_ajax_nopriv_ppv_ai_support_chat', [__CLASS__, 'ajax_chat']);
    }

    /**
     * Detect if current user is a handler/vendor/admin
     */
    private static function is_handler() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $user  = wp_get_current_user();
        $roles = (array)$user->roles;

        if (in_array('vendor', $roles) || in_array('pp_vendor', $roles) || current_user_can('manage_options')) {
            return true;
        }

        if (!empty($_SESSION['ppv_user_type']) && in_array($_SESSION['ppv_user_type'], ['vendor', 'store', 'handler', 'admin'])) {
            return true;
        }

        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            return true;
        }

        if (isset($GLOBALS['ppv_active_store']) && !empty($GLOBALS['ppv_active_store']->id)) {
            return true;
        }

        if (class_exists('PPV_Session')) {
            $store = PPV_Session::current_store();
            if (!empty($store) && !empty($store->id)) return true;
        }

        return false;
    }

    /**
     * Don't show on user-facing pages
     */
    private static function is_user_page() {
        $path = $_SERVER['REQUEST_URI'] ?? '';
        return (
            strpos($path, '/user_dashboard') !== false ||
            strpos($path, '/meine-punkte') !== false ||
            strpos($path, '/belohnungen') !== false ||
            strpos($path, '/einstellungen') !== false ||
            strpos($path, '/punkte') !== false ||
            strpos($path, '/formular/') !== false
        );
    }

    /**
     * Build the PunktePass knowledge base system prompt
     */
    public static function get_system_prompt($lang = 'de') {
        $lang_names = [
            'de' => 'German', 'hu' => 'Hungarian', 'ro' => 'Romanian',
            'en' => 'English', 'it' => 'Italian',
        ];
        $lang_name = $lang_names[$lang] ?? 'German';

        return <<<PROMPT
You are the PunktePass AI Support Assistant. You help store owners and handlers understand and use the PunktePass loyalty system.

RESPOND ONLY IN {$lang_name}. Be concise, friendly, and practical. Use short paragraphs and bullet points (•) where helpful. Do NOT use markdown formatting (no **, ##, etc.) - just plain text.

=== PUNKTEPASS SYSTEM KNOWLEDGE ===

WHAT IS PUNKTEPASS:
PunktePass is a digital loyalty points system for repair shops and service businesses. Customers scan QR codes at checkout to earn points, which they redeem for rewards/discounts.

QR CENTER (/qr-center):
• This is the main dashboard for stores - the "cash register" view
• Shows real-time scan activity with Ably real-time sync
• Customers scan the store's QR code with their phone to earn points
• Each scan = configurable points (default: 1 point)
• The store can have multiple scanner devices (tablets, POS)
• Opening hours enforcement: scans only work during set hours
• Campaign mode: time-limited bonus point campaigns

POINTS SYSTEM:
• Customers earn points per scan/visit
• Points accumulate across visits
• Lifetime points tracked separately (never decrease)
• VIP tiers: Bronze, Silver, Gold, Platinum with bonus multipliers
• Bonus days: special dates with point multipliers (holidays, etc.)

REWARDS (/rewards):
• Store creates rewards with point requirements (e.g., "10% discount = 50 points")
• Customers redeem on their phone
• Real-time redemption prompt appears on store's QR center
• Reward types: percentage discount, fixed amount, free item
• Campaigns: time-limited special rewards

STORE PROFILE (/mein-profil):
• Store name, address, phone, email, website
• Logo and cover image upload
• Opening hours configuration
• Gallery images
• Google Maps location

REPAIR FORM (/formular/slug):
• Public repair request form for customers
• Customizable fields: brand, model, IMEI, accessories, photos, signature
• Custom problem categories (quick-select tags)
• Repair form gives bonus points to customers with PunktePass account
• Email notifications to store + customer
• Tracking system with QR code
• Offline support (queues submissions when no internet)
• KFZ/Vehicle mode: license plate, VIN, mileage fields

REPAIR ADMIN (/formular/admin):
• Dashboard to manage incoming repair requests
• Status workflow: new → in progress → done
• Comments system for internal notes
• Auto-polling every 15 seconds for new repairs
• Print/PDF export
• Feedback email sent 24h after "done" status

STATISTICS (/statistik):
• Sales trends, customer count, scan frequency
• Filter by time period, location (Filiale)
• Customer insights: top customers, visit patterns
• Export capabilities

DEVICE MANAGEMENT:
• Stores can register multiple scanner devices
• Device fingerprinting for security
• Approval flow: new device needs email confirmation
• Max devices limit per store based on plan

VIP TIERS:
• Bronze/Silver/Gold/Platinum levels
• Each tier has a bonus multiplier (e.g., Gold = 1.5x points)
• Thresholds set by store owner
• Customers level up automatically based on lifetime points

BONUS DAYS:
• Special dates with point multipliers
• Configure in VIP settings
• Good for holidays, store anniversaries, slow days

CAMPAIGNS:
• Time-limited point promotions
• Set start/end date, bonus points
• Visible in QR center during active period

FILIALEN (BRANCHES):
• Multi-location support
• Each branch has its own settings and scanner
• Switch between branches in admin

NOTIFICATIONS:
• Push notifications via Firebase (iOS, Android, Web)
• Email notifications for key events
• WhatsApp integration available

SUBSCRIPTIONS & BILLING:
• Free trial period
• Stripe and PayPal payment support
• Bank transfer option
• Monthly/annual plans

COMMON QUESTIONS AND TIPS:

Q: How to increase customer engagement?
A: Set up bonus days on slow days, create campaigns, enable VIP tiers

Q: QR code not scanning?
A: Check opening hours settings, ensure device is approved, check internet connection

Q: How to customize the repair form?
A: Go to Profile → Repair Form Settings. Enable/disable fields, add custom brands and problem categories

Q: How do customers sign up?
A: They can scan the store QR code and register, or sign up at the website/app

Q: What if a customer loses their phone?
A: They can log in on a new device - device approval email will be sent

Q: How to track which employee scanned?
A: Each scanner device has a unique fingerprint. Check scan logs in Statistics

=== RULES ===
• Only answer questions about PunktePass features and usage
• If you don't know something specific, say so honestly
• Don't make up features that don't exist
• Give practical, actionable advice
• Keep answers concise (2-4 short paragraphs max)
• Suggest relevant pages/settings when applicable (e.g., "Go to /mein-profil to change this")
• ESCALATION: If you cannot answer a question, or if the user asks about billing/invoices/account issues, custom development, or anything that needs personal human support, add the exact marker [ESCALATE] at the very end of your response (after your helpful message). This will show the user a button to contact support directly via WhatsApp or email. Always try to give a helpful partial answer first before escalating.
PROMPT;
    }

    /**
     * AJAX handler for support chat
     */
    public static function ajax_chat() {
        // Auth: check PunktePass session (handlers use session auth, not WP login)
        if (!self::is_handler()) {
            wp_send_json_error(['message' => 'Not authorized']);
        }

        // Rate limit: 10 messages per 10 minutes per user/IP
        $user_id = get_current_user_id();
        $rate_id = $user_id ?: ('ip_' . $_SERVER['REMOTE_ADDR']);
        $rate_key = 'ppv_ai_chat_' . md5($rate_id);
        $count = intval(get_transient($rate_key));
        if ($count >= 10) {
            wp_send_json_error(['message' => 'Too many messages. Please wait a few minutes.']);
        }
        set_transient($rate_key, $count + 1, 600);

        $message  = sanitize_textarea_field($_POST['message'] ?? '');
        $history  = json_decode(stripslashes($_POST['history'] ?? '[]'), true);
        $lang     = sanitize_text_field($_POST['lang'] ?? 'de');

        if (!in_array($lang, ['de', 'hu', 'ro', 'en', 'it'], true)) {
            $lang = 'de';
        }

        if (empty($message) || mb_strlen($message) < 2) {
            wp_send_json_error(['message' => 'Message too short']);
        }

        require_once PPV_PLUGIN_DIR . 'includes/class-ppv-ai-engine.php';

        if (!PPV_AI_Engine::is_available()) {
            wp_send_json_error(['message' => 'AI not configured. Please add ANTHROPIC_API_KEY to wp-config.php']);
        }

        // Build messages array from history + new message
        $messages = [];
        $user_msg_count = 0;
        if (is_array($history)) {
            foreach (array_slice($history, -10) as $h) {
                if (!empty($h['role']) && !empty($h['content'])) {
                    $messages[] = [
                        'role'    => $h['role'] === 'assistant' ? 'assistant' : 'user',
                        'content' => $h['content'],
                    ];
                    if ($h['role'] === 'user') $user_msg_count++;
                }
            }
        }

        // Conversation limit: max 10 user messages, then suggest email
        if ($user_msg_count >= 10) {
            wp_send_json_success([
                'reply'         => self::get_limit_message($lang),
                'limit_reached' => true,
            ]);
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $result = PPV_AI_Engine::chat_with_history(
            self::get_system_prompt($lang),
            $messages
        );

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Check for escalation marker from AI
        $reply = $result['text'];
        $escalate = false;
        if (strpos($reply, '[ESCALATE]') !== false) {
            $reply = trim(str_replace('[ESCALATE]', '', $reply));
            $escalate = true;
        }

        $response = ['reply' => $reply];

        if ($escalate) {
            $whatsapp = get_option('ppv_support_whatsapp', '4917698479520');
            $email    = get_option('ppv_support_email', 'info@punktepass.de');
            if ($whatsapp) {
                $response['escalate']     = true;
                $response['whatsapp_url'] = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $whatsapp);
                $response['support_email'] = $email;
            }
        }

        wp_send_json_success($response);
    }

    /**
     * Render the floating chat widget in wp_footer
     */
    public static function render_widget() {
        // Always render the chat panel - don't gate on is_available()
        // The AJAX handler checks availability and returns a proper error message
        // This ensures the nav button click always opens the panel

        $lang = 'de';
        if (class_exists('PPV_Lang')) {
            $lang = PPV_Lang::current();
        }

        $labels = self::get_labels($lang);
        ?>

<style>
#ppv-ai-chat-panel{position:fixed;bottom:84px;right:16px;z-index:9991;width:360px;max-width:calc(100vw - 32px);height:460px;max-height:calc(100vh - 120px);background:#fff;border-radius:16px;box-shadow:0 12px 48px rgba(0,0,0,0.15);display:none;flex-direction:column;overflow:hidden;animation:ppvChatSlideUp .25s ease}
@keyframes ppvChatSlideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
#ppv-ai-chat-panel.visible{display:flex}
.ppv-chat-header{display:flex;align-items:center;gap:10px;padding:14px 16px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;flex-shrink:0}
.ppv-chat-header-icon{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px}
.ppv-chat-header-info{flex:1}
.ppv-chat-header-name{font-size:14px;font-weight:700}
.ppv-chat-header-status{font-size:11px;opacity:.8}
.ppv-chat-close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;padding:4px;opacity:.8;transition:opacity .2s}
.ppv-chat-close:hover{opacity:1}
.ppv-chat-messages{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px}
.ppv-chat-msg{max-width:85%;padding:10px 14px;border-radius:14px;font-size:13px;line-height:1.5;word-break:break-word;white-space:pre-line}
.ppv-chat-msg.bot{background:#f1f5f9;color:#334155;align-self:flex-start;border-bottom-left-radius:4px}
.ppv-chat-msg.user{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;align-self:flex-end;border-bottom-right-radius:4px}
.ppv-chat-msg.typing{background:#f1f5f9;align-self:flex-start;border-bottom-left-radius:4px;padding:12px 18px}
.ppv-chat-typing-dots{display:flex;gap:4px}
.ppv-chat-typing-dots span{width:7px;height:7px;border-radius:50%;background:#94a3b8;animation:ppvTypingBounce 1.2s infinite}
.ppv-chat-typing-dots span:nth-child(2){animation-delay:.2s}
.ppv-chat-typing-dots span:nth-child(3){animation-delay:.4s}
@keyframes ppvTypingBounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}
.ppv-chat-input-wrap{display:flex;gap:8px;padding:12px 16px;border-top:1px solid #f1f5f9;flex-shrink:0;background:#fff}
.ppv-chat-input{flex:1;border:1.5px solid #e2e8f0;border-radius:20px;padding:8px 14px;font-size:13px;outline:none;font-family:inherit;resize:none;max-height:60px;line-height:1.4}
.ppv-chat-input:focus{border-color:#667eea}
.ppv-chat-send{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;transition:transform .2s}
.ppv-chat-send:hover{transform:scale(1.08)}
.ppv-chat-send:disabled{opacity:.5;cursor:not-allowed;transform:none}
.ppv-chat-escalate{display:flex;gap:8px;align-self:flex-start;flex-wrap:wrap;margin:2px 0}
.ppv-chat-escalate a{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:20px;text-decoration:none;font-size:12px;font-weight:600;transition:filter .2s}
.ppv-chat-escalate a:hover{filter:brightness(0.9)}
.ppv-chat-wa-btn{background:#25d366;color:#fff!important}
.ppv-chat-email-btn{background:#667eea;color:#fff!important}
@media(max-width:480px){#ppv-ai-chat-panel{right:8px;left:8px;width:auto;bottom:140px;height:calc(100vh - 180px);max-height:none}}
</style>

<div id="ppv-ai-chat-panel">
    <div class="ppv-chat-header">
        <div class="ppv-chat-header-icon"><i class="ri-sparkling-2-fill"></i></div>
        <div class="ppv-chat-header-info">
            <div class="ppv-chat-header-name"><?php echo esc_html($labels['title']); ?></div>
            <div class="ppv-chat-header-status"><?php echo esc_html($labels['status']); ?></div>
        </div>
        <button type="button" class="ppv-chat-close" id="ppv-ai-chat-close">&times;</button>
    </div>
    <div class="ppv-chat-messages" id="ppv-ai-chat-messages">
        <div class="ppv-chat-msg bot"><?php echo esc_html($labels['welcome']); ?></div>
    </div>
    <div class="ppv-chat-input-wrap">
        <textarea class="ppv-chat-input" id="ppv-ai-chat-input" rows="1" placeholder="<?php echo esc_attr($labels['placeholder']); ?>"></textarea>
        <button type="button" class="ppv-chat-send" id="ppv-ai-chat-send"><i class="ri-send-plane-fill"></i></button>
    </div>
</div>

<script>
(function(){
    var navBtn = document.getElementById('ppv-ai-support-nav-btn');
    var panel = document.getElementById('ppv-ai-chat-panel');
    var closeBtn = document.getElementById('ppv-ai-chat-close');
    var input = document.getElementById('ppv-ai-chat-input');
    var sendBtn = document.getElementById('ppv-ai-chat-send');
    var msgContainer = document.getElementById('ppv-ai-chat-messages');
    var ajaxUrl = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
    var lang = '<?php echo esc_js($lang); ?>';
    var isOpen = false;
    var isSending = false;
    var history = [];

    if (!navBtn || !panel) return;

    // Restore history from sessionStorage
    try {
        var saved = sessionStorage.getItem('ppv_ai_chat_history');
        if (saved) {
            history = JSON.parse(saved);
            history.forEach(function(h) {
                addMessage(h.content, h.role === 'user' ? 'user' : 'bot', true);
            });
        }
    } catch(e) {}

    function saveHistory() {
        try { sessionStorage.setItem('ppv_ai_chat_history', JSON.stringify(history)); } catch(e) {}
    }

    function toggle() {
        isOpen = !isOpen;
        panel.classList.toggle('visible', isOpen);
        if (navBtn) navBtn.classList.toggle('active', isOpen);
        if (isOpen) {
            scrollToBottom();
            setTimeout(function() { input.focus(); }, 100);
        }
    }

    function addMessage(text, type, silent) {
        var msg = document.createElement('div');
        msg.className = 'ppv-chat-msg ' + type;
        msg.textContent = text;
        msgContainer.appendChild(msg);
        if (!silent) scrollToBottom();
    }

    function showTyping() {
        var msg = document.createElement('div');
        msg.className = 'ppv-chat-msg typing';
        msg.id = 'ppv-ai-typing';
        msg.innerHTML = '<div class="ppv-chat-typing-dots"><span></span><span></span><span></span></div>';
        msgContainer.appendChild(msg);
        scrollToBottom();
    }

    function hideTyping() {
        var el = document.getElementById('ppv-ai-typing');
        if (el) el.remove();
    }

    function scrollToBottom() {
        requestAnimationFrame(function() {
            msgContainer.scrollTop = msgContainer.scrollHeight;
        });
    }

    function sendMessage() {
        var text = input.value.trim();
        if (!text || isSending) return;

        addMessage(text, 'user');
        history.push({ role: 'user', content: text });
        saveHistory();
        input.value = '';
        input.style.height = 'auto';
        isSending = true;
        sendBtn.disabled = true;
        showTyping();

        var fd = new FormData();
        fd.append('action', 'ppv_ai_support_chat');
        fd.append('message', text);
        fd.append('history', JSON.stringify(history.slice(-10)));
        fd.append('lang', lang);

        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) {
                if (!r.ok) {
                    console.error('PPV AI Support: HTTP ' + r.status);
                }
                return r.text();
            })
            .then(function(raw) {
                hideTyping();
                var data;
                try { data = JSON.parse(raw); } catch(e) {
                    console.error('PPV AI Support: invalid JSON', raw.substring(0, 200));
                    addMessage(<?php echo wp_json_encode($labels['error']); ?> + ' (server error)', 'bot');
                    return;
                }
                if (data.success && data.data && data.data.reply) {
                    addMessage(data.data.reply, 'bot');
                    history.push({ role: 'assistant', content: data.data.reply });
                    saveHistory();
                    if (data.data.escalate && data.data.whatsapp_url) {
                        var lastQ = history.filter(function(h){return h.role==='user'}).slice(-1);
                        var ctx = lastQ.length ? lastQ[0].content : '';
                        var waText = <?php echo wp_json_encode($labels['wa_prefill'] ?? 'Hallo, ich brauche Hilfe mit PunktePass'); ?> + (ctx ? ':\n' + ctx : '');
                        var esc = document.createElement('div');
                        esc.className = 'ppv-chat-escalate';
                        esc.innerHTML = '<a class="ppv-chat-wa-btn" href="' + data.data.whatsapp_url + '?text=' + encodeURIComponent(waText) + '" target="_blank" rel="noopener"><i class="ri-whatsapp-fill"></i> WhatsApp</a>'
                            + '<a class="ppv-chat-email-btn" href="mailto:' + (data.data.support_email || 'info@punktepass.de') + '?subject=PunktePass%20Support&body=' + encodeURIComponent(ctx) + '"><i class="ri-mail-fill"></i> Email</a>';
                        msgContainer.appendChild(esc);
                        scrollToBottom();
                    }
                    if (data.data.limit_reached) {
                        input.disabled = true;
                        sendBtn.disabled = true;
                        input.placeholder = <?php echo wp_json_encode($labels['limit_placeholder'] ?? 'Limit reached'); ?>;
                    }
                } else {
                    var errMsg = (data.data && data.data.message) ? data.data.message : <?php echo wp_json_encode($labels['error']); ?>;
                    console.error('PPV AI Support: error response', data);
                    addMessage(errMsg, 'bot');
                }
            })
            .catch(function(err) {
                hideTyping();
                console.error('PPV AI Support: fetch error', err);
                addMessage(<?php echo wp_json_encode($labels['error']); ?>, 'bot');
            })
            .finally(function() {
                isSending = false;
                sendBtn.disabled = false;
                input.focus();
            });
    }

    // Events
    navBtn.addEventListener('click', function(e) { e.preventDefault(); toggle(); });
    closeBtn.addEventListener('click', toggle);
    sendBtn.addEventListener('click', sendMessage);

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Auto-resize input
    input.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 60) + 'px';
    });
})();
</script>
        <?php
    }

    /**
     * Get UI labels for the chat widget
     */
    private static function get_labels($lang) {
        $labels = [
            'de' => [
                'title'             => 'PunktePass Assistent',
                'status'            => 'KI-Hilfe für Ihr Geschäft',
                'welcome'           => 'Hallo! Ich bin Ihr PunktePass-Assistent. Wie kann ich Ihnen helfen? Fragen Sie mich zu QR-Center, Statistiken, Belohnungen, Reparaturformularen oder anderen Funktionen.',
                'placeholder'       => 'Nachricht eingeben...',
                'error'             => 'Entschuldigung, es gab einen Fehler. Bitte versuchen Sie es erneut.',
                'limit_placeholder' => 'Chat-Limit erreicht',
                'wa_prefill'        => 'Hallo, ich brauche Hilfe mit PunktePass',
            ],
            'hu' => [
                'title'             => 'PunktePass Asszisztens',
                'status'            => 'AI segítség az üzletéhez',
                'welcome'           => 'Helló! Én vagyok a PunktePass asszisztens. Hogyan segíthetek? Kérdezzen a QR Centerről, statisztikákról, jutalmakról, javítási űrlapokról vagy bármilyen funkcióról.',
                'placeholder'       => 'Írjon üzenetet...',
                'error'             => 'Sajnos hiba történt. Kérjük, próbálja újra.',
                'limit_placeholder' => 'Chat limit elérve',
                'wa_prefill'        => 'Szia, segítségre van szükségem a PunktePass-szal',
            ],
            'ro' => [
                'title'             => 'Asistent PunktePass',
                'status'            => 'Ajutor AI pentru afacerea dvs.',
                'welcome'           => 'Bună! Sunt asistentul PunktePass. Cum vă pot ajuta? Întrebați-mă despre QR Center, statistici, recompense, formulare de reparații sau alte funcții.',
                'placeholder'       => 'Scrieți un mesaj...',
                'error'             => 'Ne pare rău, a apărut o eroare. Vă rugăm să încercați din nou.',
                'limit_placeholder' => 'Limită chat atinsă',
                'wa_prefill'        => 'Bună, am nevoie de ajutor cu PunktePass',
            ],
            'en' => [
                'title'             => 'PunktePass Assistant',
                'status'            => 'AI help for your business',
                'welcome'           => 'Hello! I\'m your PunktePass assistant. How can I help? Ask me about QR Center, statistics, rewards, repair forms, or any other feature.',
                'placeholder'       => 'Type a message...',
                'error'             => 'Sorry, something went wrong. Please try again.',
                'limit_placeholder' => 'Chat limit reached',
                'wa_prefill'        => 'Hello, I need help with PunktePass',
            ],
            'it' => [
                'title'             => 'Assistente PunktePass',
                'status'            => 'Aiuto AI per la tua attività',
                'welcome'           => 'Ciao! Sono il tuo assistente PunktePass. Come posso aiutarti? Chiedimi del QR Center, statistiche, premi, moduli di riparazione o altre funzionalità.',
                'placeholder'       => 'Scrivi un messaggio...',
                'error'             => 'Spiacente, si è verificato un errore. Riprova.',
                'limit_placeholder' => 'Limite chat raggiunto',
                'wa_prefill'        => 'Ciao, ho bisogno di aiuto con PunktePass',
            ],
        ];

        return $labels[$lang] ?? $labels['de'];
    }

    /**
     * Get the conversation limit message (suggests email contact)
     */
    private static function get_limit_message($lang) {
        $msgs = [
            'de' => "Sie haben das Chat-Limit für diese Sitzung erreicht (max. 10 Nachrichten).\n\nFür weitere Hilfe kontaktieren Sie uns:\ninfo@punktepass.de",
            'hu' => "Elérte a chat limitet ebben a munkamenetben (max. 10 üzenet).\n\nTovábbi segítségért írjon nekünk:\ninfo@punktepass.de",
            'ro' => "Ați atins limita de chat pentru această sesiune (max. 10 mesaje).\n\nPentru ajutor suplimentar contactați-ne:\ninfo@punktepass.de",
            'en' => "You've reached the chat limit for this session (max 10 messages).\n\nFor further help, contact us:\ninfo@punktepass.de",
            'it' => "Hai raggiunto il limite di chat per questa sessione (max 10 messaggi).\n\nPer ulteriore aiuto, contattaci:\ninfo@punktepass.de",
        ];
        return $msgs[$lang] ?? $msgs['de'];
    }
}
