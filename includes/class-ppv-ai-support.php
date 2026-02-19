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

        if (!empty($_SESSION['ppv_vendor_store_id']) || !empty($_SESSION['ppv_repair_store_id'])) {
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
You are the PunktePass Support Assistant. You help store owners and handlers use the PunktePass system.

RESPOND ONLY IN {$lang_name}. Do NOT use markdown (no **, ##, etc.) - plain text only. Use bullet points (•) if listing 2+ things.

IMPORTANT: The UI is multilingual. When referring to tab names, button labels, and menu items, ALWAYS translate them to {$lang_name} because that's what the user sees on screen. The knowledge base below uses German names as reference, but you must say the translated name.
Examples for Hungarian: Einstellungen → Beállítások, Geräte → Készülékek, Kassenscanner → Kasszascanner, Öffnungszeiten → Nyitvatartás, Belohnungen → Jutalmak, Statistik → Statisztika, Rechnungen → Számlák, Allgemein → Általános, Start → Kezdés, Profil → Profil, Support → Segítség.
Examples for Romanian: Einstellungen → Setări, Geräte → Dispozitive, Öffnungszeiten → Program, Belohnungen → Recompense, Statistik → Statistici, Rechnungen → Facturi, Start → Start, Profil → Profil, Support → Ajutor.

CRITICAL RULES:
• Answer in 1-3 SHORT sentences. Be direct. No essays.
• Tell them exactly WHERE: which page, which tab, which button - using the TRANSLATED name.
• If feature exists → YES + exact location.
• If feature does NOT exist → say NO. Never invent features.
• Do NOT speculate or suggest workarounds that don't exist.
• Do NOT repeat the question. Just answer.
• ESCALATION: If you truly cannot answer, add [ESCALATE] at the very end.

=== BOTTOM NAVIGATION (fixed bar at the bottom of the screen) ===

The bottom nav has 5 buttons. Different user types see different buttons:

STORE OWNER / HÄNDLER bottom nav (5 buttons, left to right):
1. "Start" (house icon) → opens /qr-center (QR Center dashboard)
2. "Belohnungen" (coupon icon) → opens /rewards (reward management)
3. "Profil" (person icon) → opens /mein-profil (store profile settings)
4. "Statistik" (chart icon) → opens /statistik (analytics)
5. "Support" (sparkle icon) → opens this AI chat panel

SCANNER / EMPLOYEE bottom nav (4 buttons, left to right):
1. "Scanner" (QR icon) → opens /qr-center
2. "Profil" (person icon) → opens /mein-profil
3. "Support" (sparkle icon) → opens this AI chat panel
4. "Feedback" (speech bubble icon) → opens feedback form

When giving directions, always refer to the button label AND position. Example: "Klick unten auf Start (erstes Symbol links) um zum QR-Center zu kommen."

Separate standalone apps (NOT in the bottom nav):
• /formular/admin → Repair admin dashboard (separate login at /formular/admin/login)
• /formular/slug → Public repair form link for customers (share this URL with customers)

=== QR CENTER (/qr-center) ===

5 TABS:
1. Kassenscanner tab: real-time scan activity, last scans table, CSV export (today/date/month)
2. Geräte tab: registered devices list, register new device, update fingerprint, delete device, "Mobile Scanner" toggle per device
3. Prämien tab: reward management (create/edit/delete rewards, campaigns) - same as /rewards
4. Scanner Benutzer tab: create scanner user accounts for employees, assign to Filiale, reset password, enable/disable
5. VIP Einstellungen tab: configure VIP bonus system

Scanner tab features:
• "Letzte Scans" table with time, customer, status columns
• CSV Export dropdown: Heute (today), Datum wählen (pick date), Diesen Monat (this month)
• Filiale switcher dropdown at top (if multiple branches)
• + Neu button to add new Filiale
• Camera QR Scanner modal (for scanning customer QR with device camera)
• Subscription/trial status banner with Upgrade/Verlängern buttons

Geräte tab features:
• Current device status (registered/not registered)
• Device list with: name, type (mobile/desktop), registered date, last used, IP
• Per device buttons: Mobile Scanner toggle, Fingerprint aktualisieren, Löschen
• Device limit counter (X / Y Geräte)
• Link share button to register new device on another tablet

Scanner Benutzer tab:
• Create new scanner employee account (login, password, filiale assignment)
• Password generator button
• Per user: change Filiale, reset password, enable/disable

VIP Einstellungen tab:
• Two bonus types (can enable both):
  1. Fixed Points Bonus: set bonus points for Bronze/Silver/Gold/Platinum levels
  2. Streak Bonus (every Xth scan): set multiplier or fixed bonus per VIP level
• Save Settings button

=== REWARDS (/rewards) ===

3 TABS:
1. Ausstehend (Pending): pending reward redemptions to approve or cancel
2. Verlauf (History): all past redemptions, filter by status + date
3. Belege (Receipts): generate monthly or date-range PDF reports

Top stats cards: Heute (today), Woche (week), Monat (month), Wert (total value)

Reward CRUD (in Prämien tab on QR Center):
• Create: + Neue Prämie button, fill in: title, required points, description, type (% discount / fixed discount / free product), value
• Edit: click edit on reward card
• Delete: click delete on reward card
• Campaign toggle: set start/end date for time-limited rewards
• Points per scan setting (0-20)
• Apply to single Filiale or all

=== STORE PROFILE (/mein-profil) ===

6 TABS:
1. Allgemein tab: store name, slogan, category, country, tax ID, address, PLZ, city, coordinates (Geocode button), company name, contact person, description
2. Öffnungszeiten tab: opening hours per day (Mo-So), open/close time + closed checkbox
3. Bilder & Medien tab: logo upload, gallery image upload (multiple)
4. Kontakt & Social tab: phone, public email, website, WhatsApp number, Facebook URL, Instagram URL, TikTok URL
5. Marketing tab: marketing automation features (see below)
6. Einstellungen tab: active/visible toggles, vacation mode (per Filiale), opening hours enforcement, timezone, email change, password change

Marketing tab features (5 sections):
1. Google Review Requests: enable toggle, Google Review URL, points threshold, bonus points for review
2. Birthday Bonus: enable toggle, bonus type (double/fixed/free product), bonus value, message
3. Comeback Campaign: enable toggle, inactivity days (14/30/60/90), bonus type, value, message
4. Push Notifications: subscriber count, sender name, title (max 50 chars), message (max 200 chars), Send button, weekly limit
5. Referral Program: enable toggle, activate button, status (NEU/grace period/AKTIV)

Einstellungen tab features:
• Store active/visible checkboxes
• Vacation mode: enable, from/to dates, message (per Filiale if multiple)
• Opening hours enforcement checkbox
• Timezone selector (Berlin/Budapest/Bucharest)
• Email change: new email, confirm, Change button
• Password change: current, new, confirm, Change button
• Onboarding reset button (trial only)

=== STATISTICS (/statistik) ===

5 TABS:
1. Übersicht tab: stat cards (today/week/month/total/unique scans), 7-day trend chart, top 5 customers, reward stats (redeemed/approved/pending/points spent), peak hours
2. Erweitert tab: trend data, spending breakdown, conversion rates, advanced CSV export (detailed with user+email or summary daily)
3. Mitarbeiter tab: scanner employee performance (total scanners, tracked/untracked scans, per-scanner stats)
4. Verdächtige Scans tab: suspicious scan detection, filter by status (new/reviewed/dismissed), flag for review
5. Geräte tab: device activity (last 7 days), total devices, mobile scanners count

Filters on Übersicht:
• Filiale selector (if multiple branches)
• Date range: Heute (today), Diese Woche (week), Diesen Monat (month), Gesamt (all time)
• CSV Export button

=== REPAIR ADMIN (/formular/admin) ===

6 TABS:
1. Reparaturen tab: repair cards, search, status filter, change status, comments, print ticket
2. Rechnungen tab: invoices & quotes (see details below)
3. Einstellungen tab: form fields, brands, categories, invoice templates, VAT
4. Ankauf tab: buy-back module for purchasing used devices
5. Partner tab: partner store management
6. Filialen tab: branch management

Invoice features (Rechnungen tab):
• Create Rechnung (invoice) or Angebot (quote)
• Auto-generate when repair = "done"
• Custom invoice number prefix (e.g. RE-001)
• Line items: service description + amount
• Optional warranty date (Garantie bis): appears on PDF if set
• VAT/MwSt (configurable, can disable for Kleinunternehmer)
• Send invoice email: click envelope icon → PDF sent to customer
• Bulk email: select multiple → Massen Email
• Payment reminder: button on unpaid invoices
• PDF download, CSV export, Bulk PDF as ZIP
• Status: Entwurf → Versendet → Bezahlt → Storniert
• Email template in Einstellungen with placeholders: {customer_name}, {invoice_number}, {invoice_date}, {total}, {company_name}

=== DEVICE MANAGEMENT (Geräte tab in /qr-center) ===

• Register devices (tablets, phones, POS)
• Each device gets fingerprint for security
• New device needs email confirmation from admin
• Device limit: 2 base + 1 per Filiale (depends on subscription)
• Mobile Scanner flag: mark a device as mobile (for on-site service)
• Share registration link to set up new device

=== FILIALEN (branches) ===

• Add new Filiale: + Neu button on QR Center, fill name/city/PLZ
• Switch active Filiale via dropdown on QR Center
• Each Filiale has own: settings, scanner devices, repair form slug, opening hours
• Filiale limit based on subscription (can request more via contact form)

=== SUBSCRIPTIONS ===

• Free trial period with countdown banner on QR Center
• Upgrade/Verlängern buttons when trial expires
• Stripe + PayPal payment
• Bank transfer option
• Monthly/annual plans
PROMPT;
    }

    /**
     * Build repair-form-specific system prompt (for /formular/admin chat)
     */
    public static function get_repair_system_prompt($lang = 'de') {
        $lang_names = [
            'de' => 'German', 'hu' => 'Hungarian', 'ro' => 'Romanian',
            'en' => 'English', 'it' => 'Italian',
        ];
        $lang_name = $lang_names[$lang] ?? 'German';

        return <<<PROMPT
You are the PunktePass Repair Assistant. You help store owners and handlers use the repair admin system.

RESPOND ONLY IN {$lang_name}. Do NOT use markdown (no **, ##, etc.) - plain text only. Use bullet points (•) where helpful.

IMPORTANT: The UI is multilingual. When referring to tab names and button labels, ALWAYS translate them to {$lang_name} because that's what the user sees on screen. The knowledge base below uses German names as reference.
Examples for Hungarian: Reparaturen → Javítások, Rechnungen → Számlák, Einstellungen → Beállítások, Ankauf → Felvásárlás, Partner → Partnerek, Filialen → Fióktelepek, Entwurf → Piszkozat, Versendet → Elküldve, Bezahlt → Fizetve, Storniert → Sztornózva.
Examples for Romanian: Reparaturen → Reparații, Rechnungen → Facturi, Einstellungen → Setări, Ankauf → Achiziții, Entwurf → Ciornă, Versendet → Trimis, Bezahlt → Plătit.

CRITICAL RULES:
• Answer in 1-3 SHORT sentences maximum. No essays, no long explanations.
• If the system has the feature, say YES and tell them exactly where to find it (which tab, which button) - using the TRANSLATED name.
• If the system does NOT have the feature, say NO honestly. Never make up features.
• Do NOT speculate or suggest workarounds. Only describe what the system actually does.
• Do NOT repeat the question back. Just answer directly.
• ESCALATION: If you truly cannot answer, add [ESCALATE] at the very end.

=== FEATURES THE SYSTEM ACTUALLY HAS ===

TABS IN REPAIR ADMIN:
• Reparaturen tab: list of all repairs, search, filter by status
• Rechnungen tab: invoices & quotes management
• Einstellungen tab: form settings, brands, problem categories, email templates
• Ankauf tab: buy-back/trade-in module for used devices
• Partner tab: partner store management
• Filialen tab: branch/location management

REPAIR MANAGEMENT (Reparaturen tab):
• View all repairs in card layout
• Status workflow: new → in_progress → waiting_parts → done → delivered → cancelled
• Change status via dropdown on each repair card
• Add internal comments/notes to each repair
• Print individual repair ticket (with QR code) via Print button
• Auto-polling every 15 seconds for new incoming repairs
• Search by customer name, phone, device info
• Filter by status
• Feedback email sent automatically 24h after "done" status
• TEIL ANGEKOMMEN (Part arrived): When repair is in "Wartet auf Teile" status, an amber/orange "Teil angekommen" button appears on the card. Click it to open a modal with two options:
  OPTION 1 - With appointment (Termin): Set date/time, optionally send appointment email to customer. Termin badge appears on card and public tracking page.
  OPTION 2 - Without appointment (ohne Termin): Check "Nur als angekommen markieren (ohne Termin)" checkbox. Hides date/time fields. Just marks the part as arrived.
  Both options change status to "In Bearbeitung" and remove the Teil angekommen button.

INVOICE SYSTEM (Rechnungen tab):
• Create invoices (Rechnung) or quotes (Angebot)
• QUOTE CREATION (Neues Angebot): modern 2-step wizard modal
  - Step 1: Customer data (name, company, email, phone, single address field that auto-parses street/PLZ/city)
  - Step 2: Positions with Qty × Unit Price per line (auto-calculated line totals), totals card (Netto/MwSt/Brutto), valid-until date, notes
  - Customer search: type to search existing customers, results appear in dropdown
  - Can also create quote from repair card (pre-fills customer + device info)
• Auto-generate invoice when repair status set to "done"
• Custom invoice numbering with prefix (e.g. RE-001)
• Optional warranty date (Garantie bis): set expiration date on invoice/quote, displayed on PDF
• Line items: add service descriptions + qty × price amounts
• VAT/MwSt calculation (configurable rate, can disable for Kleinunternehmer)
• EMAIL SEND: click the email icon (envelope) on any invoice to send PDF to customer
• Bulk email: select multiple invoices → "Massen Email" button
• Payment reminder: click reminder button on unpaid invoices
• PDF download: click PDF link on any invoice
• CSV export: single or bulk export
• Bulk PDF as ZIP download
• Status tracking: Entwurf (draft) → Versendet (sent) → Bezahlt (paid) → Storniert (cancelled)
• Status auto-changes to "Versendet" when email sent
• Email template customizable in Einstellungen tab (subject + body with placeholders)
• Placeholders: {customer_name}, {invoice_number}, {invoice_date}, {total}, {company_name}
• PunktePass reward discount auto-applied on invoice if customer has enough points

REPAIR FORM (public customer form at /formular/slug):
• Customers fill in: name, email, phone, device brand/model, problem description
• Optional fields: IMEI, accessories checklist, photos (upload), signature
• Custom problem categories as quick-select buttons (configurable in Settings)
• QR tracking code generated for each repair
• Offline mode: queues submissions when no internet
• KFZ/Vehicle mode: license plate, VIN, mileage (for auto repair shops)
• PC/Computer mode: condition check for PC components (mainboard, CPU, RAM, SSD, GPU, display, keyboard, fan, power supply, ports) - enable in Settings
• Form is fully configurable for any repair type (phone, PC, vehicle) via field toggles in Settings
• Bonus points for customers with PunktePass account
• Email notification to store + customer on submission

SETTINGS (Einstellungen tab):
• Form fields: toggle on/off (IMEI, accessories, photos, signature, etc.)
• Brands: add/remove phone brands, car brands etc.
• Problem categories: add/remove quick-select tags
• Invoice prefix and next number
• Invoice email subject + body templates
• VAT rate configuration
• Estimated repair time default
• Form language setting
• KFZ mode toggle
• Custom CSS for form appearance

ANKAUF (buy-back module):
• Create buy-back entries for purchasing used devices from customers
• Separate from repairs
• Own invoice generation

FILIALEN (branches):
• Multi-location support
• Each branch has its own repair form slug
• Switch active branch in admin
• Separate repair lists per branch

PARTNER STORES:
• Add partner stores that can share the repair system
• Partner management panel
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

        $context    = sanitize_text_field($_POST['context'] ?? '');
        $current_url = sanitize_text_field($_POST['current_url'] ?? '');

        // Get store name for personalized responses
        $store_name = '';
        if (!empty($_SESSION['ppv_repair_store_name'])) {
            $store_name = $_SESSION['ppv_repair_store_name'];
        } elseif (class_exists('PPV_Session')) {
            $store = PPV_Session::current_store();
            if (!empty($store->name)) $store_name = $store->name;
        }

        $system_prompt = ($context === 'repair')
            ? self::get_repair_system_prompt($lang)
            : self::get_system_prompt($lang);

        // Append dynamic context
        $extra_context = "\n\n=== CURRENT SESSION ===\n";
        if ($store_name) {
            $extra_context .= "Store name: {$store_name}\n";
        }
        if ($current_url) {
            $extra_context .= "User is currently on page: {$current_url}\n";
            $extra_context .= "If the answer is on THIS page, say so (e.g. 'Du bist schon auf der richtigen Seite, klick oben auf den X Tab').\n";
        }
        $system_prompt .= $extra_context;

        $result = PPV_AI_Engine::chat_with_history(
            $system_prompt,
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
.ppv-chat-chips{display:flex;flex-wrap:wrap;gap:6px;align-self:flex-start;margin:4px 0}
.ppv-chat-chip{background:#f1f5f9;border:1.5px solid #e2e8f0;border-radius:16px;padding:6px 12px;font-size:12px;color:#475569;cursor:pointer;transition:all .2s;font-family:inherit;line-height:1.3}
.ppv-chat-chip:hover{background:#667eea;color:#fff;border-color:#667eea}
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
    var hasHistory = false;
    try {
        var saved = sessionStorage.getItem('ppv_ai_chat_history');
        if (saved) {
            history = JSON.parse(saved);
            if (history.length) hasHistory = true;
            history.forEach(function(h) {
                addMessage(h.content, h.role === 'user' ? 'user' : 'bot', true);
            });
        }
    } catch(e) {}

    // Show quick question chips if no previous chat
    var chips = <?php echo wp_json_encode($labels['chips'] ?? []); ?>;
    if (!hasHistory && chips.length) {
        var chipWrap = document.createElement('div');
        chipWrap.className = 'ppv-chat-chips';
        chipWrap.id = 'ppv-chat-chips';
        chips.forEach(function(c) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ppv-chat-chip';
            btn.textContent = c;
            btn.addEventListener('click', function() {
                input.value = c;
                chipWrap.remove();
                sendMessage();
            });
            chipWrap.appendChild(btn);
        });
        msgContainer.appendChild(chipWrap);
    }

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
        fd.append('current_url', window.location.pathname);

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
                'welcome'           => 'Hallo! Ich bin Ihr PunktePass-Assistent. Wie kann ich Ihnen helfen?',
                'placeholder'       => 'Nachricht eingeben...',
                'error'             => 'Entschuldigung, es gab einen Fehler. Bitte versuchen Sie es erneut.',
                'limit_placeholder' => 'Chat-Limit erreicht',
                'wa_prefill'        => 'Hallo, ich brauche Hilfe mit PunktePass',
                'chips'             => ['Öffnungszeiten ändern', 'Neues Gerät registrieren', 'Belohnung erstellen', 'Statistik exportieren'],
            ],
            'hu' => [
                'title'             => 'PunktePass Asszisztens',
                'status'            => 'AI segítség az üzletéhez',
                'welcome'           => 'Helló! Én vagyok a PunktePass asszisztens. Hogyan segíthetek?',
                'placeholder'       => 'Írjon üzenetet...',
                'error'             => 'Sajnos hiba történt. Kérjük, próbálja újra.',
                'limit_placeholder' => 'Chat limit elérve',
                'wa_prefill'        => 'Szia, segítségre van szükségem a PunktePass-szal',
                'chips'             => ['Nyitvatartás módosítása', 'Új eszköz regisztrálása', 'Jutalom létrehozása', 'Statisztika exportálása'],
            ],
            'ro' => [
                'title'             => 'Asistent PunktePass',
                'status'            => 'Ajutor AI pentru afacerea dvs.',
                'welcome'           => 'Bună! Sunt asistentul PunktePass. Cum vă pot ajuta?',
                'placeholder'       => 'Scrieți un mesaj...',
                'error'             => 'Ne pare rău, a apărut o eroare. Vă rugăm să încercați din nou.',
                'limit_placeholder' => 'Limită chat atinsă',
                'wa_prefill'        => 'Bună, am nevoie de ajutor cu PunktePass',
                'chips'             => ['Modificare program', 'Înregistrare dispozitiv', 'Creare recompensă', 'Export statistici'],
            ],
            'en' => [
                'title'             => 'PunktePass Assistant',
                'status'            => 'AI help for your business',
                'welcome'           => 'Hello! I\'m your PunktePass assistant. How can I help?',
                'placeholder'       => 'Type a message...',
                'error'             => 'Sorry, something went wrong. Please try again.',
                'limit_placeholder' => 'Chat limit reached',
                'wa_prefill'        => 'Hello, I need help with PunktePass',
                'chips'             => ['Change opening hours', 'Register new device', 'Create reward', 'Export statistics'],
            ],
            'it' => [
                'title'             => 'Assistente PunktePass',
                'status'            => 'Aiuto AI per la tua attività',
                'welcome'           => 'Ciao! Sono il tuo assistente PunktePass. Come posso aiutarti?',
                'placeholder'       => 'Scrivi un messaggio...',
                'error'             => 'Spiacente, si è verificato un errore. Riprova.',
                'limit_placeholder' => 'Limite chat raggiunto',
                'wa_prefill'        => 'Ciao, ho bisogno di aiuto con PunktePass',
                'chips'             => ['Modificare orari', 'Registrare dispositivo', 'Creare premio', 'Esportare statistiche'],
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

    /**
     * Get UI labels for the repair chat widget
     */
    private static function get_repair_labels($lang) {
        $labels = [
            'de' => [
                'title'             => 'Reparatur-Assistent',
                'status'            => 'KI-Hilfe für Reparaturen',
                'welcome'           => 'Hallo! Ich bin Ihr Reparatur-Assistent. Wie kann ich helfen?',
                'placeholder'       => 'Frage zur Reparatur...',
                'error'             => 'Entschuldigung, es gab einen Fehler. Bitte versuchen Sie es erneut.',
                'limit_placeholder' => 'Chat-Limit erreicht',
                'wa_prefill'        => 'Hallo, ich brauche Hilfe mit dem Reparaturformular',
                'chips'             => ['Rechnung per Mail senden', 'Status ändern', 'Felder anpassen', 'Reparatur drucken'],
            ],
            'hu' => [
                'title'             => 'Javítás Asszisztens',
                'status'            => 'AI segítség a javításokhoz',
                'welcome'           => 'Helló! Én vagyok a javítás asszisztens. Hogyan segíthetek?',
                'placeholder'       => 'Kérdés a javításról...',
                'error'             => 'Sajnos hiba történt. Kérjük, próbálja újra.',
                'limit_placeholder' => 'Chat limit elérve',
                'wa_prefill'        => 'Szia, segítségre van szükségem a javítási űrlappal',
                'chips'             => ['Számla küldés emailben', 'Státusz módosítás', 'Mezők testreszabása', 'Javítás nyomtatása'],
            ],
            'ro' => [
                'title'             => 'Asistent Reparații',
                'status'            => 'Ajutor AI pentru reparații',
                'welcome'           => 'Bună! Sunt asistentul pentru reparații. Cum vă pot ajuta?',
                'placeholder'       => 'Întrebare despre reparații...',
                'error'             => 'Ne pare rău, a apărut o eroare. Vă rugăm să încercați din nou.',
                'limit_placeholder' => 'Limită chat atinsă',
                'wa_prefill'        => 'Bună, am nevoie de ajutor cu formularul de reparații',
                'chips'             => ['Trimite factură email', 'Schimbare status', 'Personalizare câmpuri', 'Tipărire reparație'],
            ],
            'en' => [
                'title'             => 'Repair Assistant',
                'status'            => 'AI help for repairs',
                'welcome'           => 'Hello! I\'m your repair assistant. How can I help?',
                'placeholder'       => 'Ask about repairs...',
                'error'             => 'Sorry, something went wrong. Please try again.',
                'limit_placeholder' => 'Chat limit reached',
                'wa_prefill'        => 'Hello, I need help with the repair form',
                'chips'             => ['Send invoice by email', 'Change status', 'Customize fields', 'Print repair ticket'],
            ],
            'it' => [
                'title'             => 'Assistente Riparazioni',
                'status'            => 'Aiuto AI per riparazioni',
                'welcome'           => 'Ciao! Sono il tuo assistente riparazioni. Come posso aiutarti?',
                'placeholder'       => 'Domanda sulle riparazioni...',
                'error'             => 'Spiacente, si è verificato un errore. Riprova.',
                'limit_placeholder' => 'Limite chat raggiunto',
                'wa_prefill'        => 'Ciao, ho bisogno di aiuto con il modulo di riparazione',
                'chips'             => ['Invia fattura email', 'Cambia stato', 'Personalizza campi', 'Stampa riparazione'],
            ],
        ];

        return $labels[$lang] ?? $labels['de'];
    }

    /**
     * Render floating chat widget for repair admin (/formular/admin)
     * This is a standalone widget (the page doesn't use WP shortcodes/bottom nav)
     */
    public static function render_repair_widget($lang = 'de') {
        $labels   = self::get_repair_labels($lang);
        $ajax_url = admin_url('admin-ajax.php');
        ?>

<style>
#ppv-repair-chat-fab{position:fixed;bottom:24px;right:24px;z-index:9990;width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:24px;box-shadow:0 6px 20px rgba(102,126,234,0.4);transition:transform .2s,box-shadow .2s}
#ppv-repair-chat-fab:hover{transform:scale(1.08);box-shadow:0 8px 28px rgba(102,126,234,0.5)}
#ppv-repair-chat-fab.has-panel{background:rgba(100,116,139,0.8);box-shadow:0 4px 12px rgba(0,0,0,0.15)}
#ppv-repair-chat-panel{position:fixed;bottom:92px;right:24px;z-index:9991;width:380px;max-width:calc(100vw - 48px);height:480px;max-height:calc(100vh - 140px);background:#fff;border-radius:16px;box-shadow:0 12px 48px rgba(0,0,0,0.15);display:none;flex-direction:column;overflow:hidden;animation:ppvRcSlideUp .25s ease}
@keyframes ppvRcSlideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
#ppv-repair-chat-panel.visible{display:flex}
.ppv-rc-header{display:flex;align-items:center;gap:10px;padding:14px 16px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;flex-shrink:0}
.ppv-rc-header-icon{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px}
.ppv-rc-header-info{flex:1}
.ppv-rc-header-name{font-size:14px;font-weight:700}
.ppv-rc-header-status{font-size:11px;opacity:.8}
.ppv-rc-close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;padding:4px;opacity:.8;transition:opacity .2s}
.ppv-rc-close:hover{opacity:1}
.ppv-rc-messages{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px}
.ppv-rc-msg{max-width:85%;padding:10px 14px;border-radius:14px;font-size:13px;line-height:1.5;word-break:break-word;white-space:pre-line}
.ppv-rc-msg.bot{background:#f1f5f9;color:#334155;align-self:flex-start;border-bottom-left-radius:4px}
.ppv-rc-msg.user{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;align-self:flex-end;border-bottom-right-radius:4px}
.ppv-rc-msg.typing{background:#f1f5f9;align-self:flex-start;border-bottom-left-radius:4px;padding:12px 18px}
.ppv-rc-typing-dots{display:flex;gap:4px}
.ppv-rc-typing-dots span{width:7px;height:7px;border-radius:50%;background:#94a3b8;animation:ppvRcBounce 1.2s infinite}
.ppv-rc-typing-dots span:nth-child(2){animation-delay:.2s}
.ppv-rc-typing-dots span:nth-child(3){animation-delay:.4s}
@keyframes ppvRcBounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}
.ppv-rc-input-wrap{display:flex;gap:8px;padding:12px 16px;border-top:1px solid #f1f5f9;flex-shrink:0;background:#fff}
.ppv-rc-input{flex:1;border:1.5px solid #e2e8f0;border-radius:20px;padding:8px 14px;font-size:13px;outline:none;font-family:inherit;resize:none;max-height:60px;line-height:1.4}
.ppv-rc-input:focus{border-color:#667eea}
.ppv-rc-send{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;transition:transform .2s}
.ppv-rc-send:hover{transform:scale(1.08)}
.ppv-rc-send:disabled{opacity:.5;cursor:not-allowed;transform:none}
.ppv-chat-chips{display:flex;flex-wrap:wrap;gap:6px;align-self:flex-start;margin:4px 0}
.ppv-chat-chip{background:#f1f5f9;border:1.5px solid #e2e8f0;border-radius:16px;padding:6px 12px;font-size:12px;color:#475569;cursor:pointer;transition:all .2s;font-family:inherit;line-height:1.3}
.ppv-chat-chip:hover{background:#667eea;color:#fff;border-color:#667eea}
.ppv-rc-escalate{display:flex;gap:8px;align-self:flex-start;flex-wrap:wrap;margin:2px 0}
.ppv-rc-escalate a{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:20px;text-decoration:none;font-size:12px;font-weight:600;transition:filter .2s}
.ppv-rc-escalate a:hover{filter:brightness(0.9)}
.ppv-rc-wa-btn{background:#25d366;color:#fff!important}
.ppv-rc-email-btn{background:#667eea;color:#fff!important}
@media(max-width:480px){#ppv-repair-chat-fab{bottom:16px;right:16px;width:50px;height:50px;font-size:22px}#ppv-repair-chat-panel{right:8px;left:8px;width:auto;bottom:80px;height:calc(100vh - 120px);max-height:none}}
</style>

<button type="button" id="ppv-repair-chat-fab" title="<?php echo esc_attr($labels['title']); ?>">
    <i class="ri-sparkling-2-fill"></i>
</button>

<div id="ppv-repair-chat-panel">
    <div class="ppv-rc-header">
        <div class="ppv-rc-header-icon"><i class="ri-tools-fill"></i></div>
        <div class="ppv-rc-header-info">
            <div class="ppv-rc-header-name"><?php echo esc_html($labels['title']); ?></div>
            <div class="ppv-rc-header-status"><?php echo esc_html($labels['status']); ?></div>
        </div>
        <button type="button" class="ppv-rc-close" id="ppv-rc-close">&times;</button>
    </div>
    <div class="ppv-rc-messages" id="ppv-rc-messages">
        <div class="ppv-rc-msg bot"><?php echo esc_html($labels['welcome']); ?></div>
    </div>
    <div class="ppv-rc-input-wrap">
        <textarea class="ppv-rc-input" id="ppv-rc-input" rows="1" placeholder="<?php echo esc_attr($labels['placeholder']); ?>"></textarea>
        <button type="button" class="ppv-rc-send" id="ppv-rc-send"><i class="ri-send-plane-fill"></i></button>
    </div>
</div>

<script>
(function(){
    var fab = document.getElementById('ppv-repair-chat-fab');
    var panel = document.getElementById('ppv-repair-chat-panel');
    var closeBtn = document.getElementById('ppv-rc-close');
    var input = document.getElementById('ppv-rc-input');
    var sendBtn = document.getElementById('ppv-rc-send');
    var msgContainer = document.getElementById('ppv-rc-messages');
    var ajaxUrl = <?php echo wp_json_encode(esc_url($ajax_url)); ?>;
    var lang = <?php echo wp_json_encode(esc_js($lang)); ?>;
    var isOpen = false;
    var isSending = false;
    var history = [];

    if (!fab || !panel) return;

    var hasHistory = false;
    try {
        var saved = sessionStorage.getItem('ppv_rc_history');
        if (saved) {
            history = JSON.parse(saved);
            if (history.length) hasHistory = true;
            history.forEach(function(h) {
                addMessage(h.content, h.role === 'user' ? 'user' : 'bot', true);
            });
        }
    } catch(e) {}

    var chips = <?php echo wp_json_encode($labels['chips'] ?? []); ?>;
    if (!hasHistory && chips.length) {
        var chipWrap = document.createElement('div');
        chipWrap.className = 'ppv-chat-chips';
        chipWrap.id = 'ppv-rc-chips';
        chips.forEach(function(c) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ppv-chat-chip';
            btn.textContent = c;
            btn.addEventListener('click', function() {
                input.value = c;
                chipWrap.remove();
                sendMessage();
            });
            chipWrap.appendChild(btn);
        });
        msgContainer.appendChild(chipWrap);
    }

    function saveHistory() {
        try { sessionStorage.setItem('ppv_rc_history', JSON.stringify(history)); } catch(e) {}
    }

    function toggle() {
        isOpen = !isOpen;
        panel.classList.toggle('visible', isOpen);
        fab.classList.toggle('has-panel', isOpen);
        fab.innerHTML = isOpen ? '<i class="ri-close-line"></i>' : '<i class="ri-sparkling-2-fill"></i>';
        if (isOpen) {
            scrollToBottom();
            setTimeout(function() { input.focus(); }, 100);
        }
    }

    function addMessage(text, type, silent) {
        var msg = document.createElement('div');
        msg.className = 'ppv-rc-msg ' + type;
        msg.textContent = text;
        msgContainer.appendChild(msg);
        if (!silent) scrollToBottom();
    }

    function showTyping() {
        var msg = document.createElement('div');
        msg.className = 'ppv-rc-msg typing';
        msg.id = 'ppv-rc-typing';
        msg.innerHTML = '<div class="ppv-rc-typing-dots"><span></span><span></span><span></span></div>';
        msgContainer.appendChild(msg);
        scrollToBottom();
    }

    function hideTyping() {
        var el = document.getElementById('ppv-rc-typing');
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
        fd.append('current_url', window.location.pathname);
        fd.append('context', 'repair');

        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) {
                if (!r.ok) console.error('PPV Repair Chat: HTTP ' + r.status);
                return r.text();
            })
            .then(function(raw) {
                hideTyping();
                var data;
                try { data = JSON.parse(raw); } catch(e) {
                    console.error('PPV Repair Chat: invalid JSON', raw.substring(0, 200));
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
                        var waText = <?php echo wp_json_encode($labels['wa_prefill']); ?> + (ctx ? ':\n' + ctx : '');
                        var esc = document.createElement('div');
                        esc.className = 'ppv-rc-escalate';
                        esc.innerHTML = '<a class="ppv-rc-wa-btn" href="' + data.data.whatsapp_url + '?text=' + encodeURIComponent(waText) + '" target="_blank" rel="noopener"><i class="ri-whatsapp-fill"></i> WhatsApp</a>'
                            + '<a class="ppv-rc-email-btn" href="mailto:' + (data.data.support_email || 'info@punktepass.de') + '?subject=PunktePass%20Repair%20Support&body=' + encodeURIComponent(ctx) + '"><i class="ri-mail-fill"></i> Email</a>';
                        msgContainer.appendChild(esc);
                        scrollToBottom();
                    }
                    if (data.data.limit_reached) {
                        input.disabled = true;
                        sendBtn.disabled = true;
                        input.placeholder = <?php echo wp_json_encode($labels['limit_placeholder']); ?>;
                    }
                } else {
                    var errMsg = (data.data && data.data.message) ? data.data.message : <?php echo wp_json_encode($labels['error']); ?>;
                    console.error('PPV Repair Chat: error', data);
                    addMessage(errMsg, 'bot');
                }
            })
            .catch(function(err) {
                hideTyping();
                console.error('PPV Repair Chat: fetch error', err);
                addMessage(<?php echo wp_json_encode($labels['error']); ?>, 'bot');
            })
            .finally(function() {
                isSending = false;
                sendBtn.disabled = false;
                input.focus();
            });
    }

    fab.addEventListener('click', toggle);
    closeBtn.addEventListener('click', toggle);
    sendBtn.addEventListener('click', sendMessage);

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    input.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 60) + 'px';
    });
})();
</script>
        <?php
    }
}
