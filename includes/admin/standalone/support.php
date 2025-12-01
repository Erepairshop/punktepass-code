<?php
/**
 * PunktePass Standalone Admin - Feedback & Support
 * Route: /admin/support
 * Hungarian admin interface with ticket detail modal, reply functionality, and ratings overview
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Support {

    /**
     * Render support tickets page
     */
    public static function render() {
        global $wpdb;

        // Handle POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['update_status'])) {
                self::handle_status_update();
            } elseif (isset($_POST['send_reply'])) {
                self::handle_send_reply();
            }
        }

        // Get filters
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'open';
        $category_filter = isset($_GET['cat']) ? sanitize_text_field($_GET['cat']) : 'all';

        // Build WHERE conditions
        $conditions = [];

        // Status filter
        if ($status_filter === 'open') {
            $conditions[] = "t.status IN ('new', 'in_progress')";
        } elseif ($status_filter === 'resolved') {
            $conditions[] = "t.status = 'resolved'";
        } elseif ($status_filter === 'new') {
            $conditions[] = "t.status = 'new'";
        } elseif ($status_filter === 'in_progress') {
            $conditions[] = "t.status = 'in_progress'";
        }

        // Category filter
        if ($category_filter !== 'all' && in_array($category_filter, ['support', 'bug', 'feature', 'question', 'rating'])) {
            $conditions[] = $wpdb->prepare("t.category = %s", $category_filter);
        }

        $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Fetch support tickets with user info
        $tickets = $wpdb->get_results("
            SELECT
                t.*,
                s.name as store_name_db,
                s.company_name,
                s.city,
                u.email as user_email_db,
                u.first_name as user_first_name,
                u.last_name as user_last_name
            FROM {$wpdb->prefix}ppv_support_tickets t
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON t.store_id = s.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON t.user_id = u.id
            {$where_clause}
            ORDER BY
                FIELD(t.status, 'new', 'in_progress', 'resolved'),
                FIELD(t.priority, 'urgent', 'normal', 'low'),
                t.created_at DESC
        ");

        // Count by status
        $new_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE status = 'new'");
        $in_progress_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE status = 'in_progress'");
        $open_count = $new_count + $in_progress_count;
        $resolved_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE status = 'resolved'");

        // Count by category (only open tickets)
        $bug_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE category = 'bug' AND status IN ('new', 'in_progress')");
        $feature_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE category = 'feature' AND status IN ('new', 'in_progress')");
        $question_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE category = 'question' AND status IN ('new', 'in_progress')");
        $rating_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE category = 'rating' AND status IN ('new', 'in_progress')");
        $support_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE (category = 'support' OR category IS NULL) AND status IN ('new', 'in_progress')");

        $category_counts = [
            'bug' => intval($bug_count),
            'feature' => intval($feature_count),
            'question' => intval($question_count),
            'rating' => intval($rating_count),
            'support' => intval($support_count)
        ];

        // Get average rating
        $avg_rating = $wpdb->get_var("SELECT AVG(rating) FROM {$wpdb->prefix}ppv_support_tickets WHERE category = 'rating' AND rating IS NOT NULL AND rating > 0");
        $total_ratings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_support_tickets WHERE category = 'rating' AND rating IS NOT NULL AND rating > 0");

        self::render_html($tickets, $status_filter, $category_filter, $new_count, $in_progress_count, $open_count, $resolved_count, $category_counts, $avg_rating, $total_ratings);
    }

    /**
     * Handle status update
     */
    private static function handle_status_update() {
        global $wpdb;

        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['new_status'] ?? '');

        if ($ticket_id > 0 && in_array($new_status, ['new', 'in_progress', 'resolved'])) {
            $update_data = ['status' => $new_status];

            if ($new_status === 'resolved') {
                $update_data['resolved_at'] = current_time('mysql');
            }

            $wpdb->update(
                "{$wpdb->prefix}ppv_support_tickets",
                $update_data,
                ['id' => $ticket_id],
                $new_status === 'resolved' ? ['%s', '%s'] : ['%s'],
                ['%d']
            );

            ppv_log("✅ [Support Admin] Ticket #{$ticket_id} státusz frissítve: {$new_status}");
        }

        $current_status = isset($_GET['status']) ? $_GET['status'] : 'open';
        $current_cat = isset($_GET['cat']) ? $_GET['cat'] : 'all';
        wp_redirect("/admin/support?status={$current_status}&cat={$current_cat}&success=updated");
        exit;
    }

    /**
     * Handle reply sending
     */
    private static function handle_send_reply() {
        global $wpdb;

        $ticket_id = intval($_POST['ticket_id'] ?? 0);
        $reply_message = sanitize_textarea_field($_POST['reply_message'] ?? '');

        if ($ticket_id <= 0 || empty($reply_message)) {
            wp_redirect("/admin/support?error=empty_reply");
            exit;
        }

        // Get ticket info
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_support_tickets WHERE id = %d",
            $ticket_id
        ));

        if (!$ticket) {
            wp_redirect("/admin/support?error=ticket_not_found");
            exit;
        }

        // Get user language (default to de)
        $lang = $ticket->language ?? 'de';
        if (!in_array($lang, ['de', 'hu', 'en'])) $lang = 'de';

        // Multi-language email templates
        $translations = [
            'de' => [
                'subject' => "Antwort auf Ihr Ticket #{$ticket_id}",
                'greeting' => $ticket->store_name ? "Hallo {$ticket->store_name}," : "Hallo,",
                'intro' => "wir haben auf Ihre Anfrage geantwortet:",
                'original' => "Ihre ursprüngliche Nachricht",
                'footer_1' => "Mit freundlichen Grüßen",
                'footer_2' => "Ihr PunktePass-Team",
                'reply_note' => "Sie können auf diese E-Mail antworten, um uns zu kontaktieren."
            ],
            'hu' => [
                'subject' => "Válasz a #{$ticket_id} jegyére",
                'greeting' => $ticket->store_name ? "Kedves {$ticket->store_name}!" : "Kedves Felhasználó!",
                'intro' => "Válaszoltunk az Ön kérésére:",
                'original' => "Az Ön eredeti üzenete",
                'footer_1' => "Üdvözlettel",
                'footer_2' => "A PunktePass csapat",
                'reply_note' => "Erre az e-mailre válaszolva kapcsolatba léphet velünk."
            ],
            'en' => [
                'subject' => "Reply to Your Ticket #{$ticket_id}",
                'greeting' => $ticket->store_name ? "Hello {$ticket->store_name}," : "Hello,",
                'intro' => "We have responded to your inquiry:",
                'original' => "Your original message",
                'footer_1' => "Best regards",
                'footer_2' => "Your PunktePass Team",
                'reply_note' => "You can reply to this email to contact us."
            ]
        ];

        $T = $translations[$lang];

        // Build email body
        $email_body = "{$T['greeting']}\n\n";
        $email_body .= "{$T['intro']}\n\n";
        $email_body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $email_body .= $reply_message . "\n\n";
        $email_body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $email_body .= "{$T['original']}:\n";
        $email_body .= "---\n";
        $email_body .= $ticket->description . "\n";
        $email_body .= "---\n\n";
        $email_body .= "{$T['footer_1']},\n";
        $email_body .= "{$T['footer_2']}\n\n";
        $email_body .= "www.punktepass.de\n\n";
        $email_body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $email_body .= "{$T['reply_note']}\n";

        $headers = [
            'From: PunktePass Support <info@punktepass.de>',
            'Reply-To: info@punktepass.de',
            'Content-Type: text/plain; charset=UTF-8'
        ];

        $sent = wp_mail($ticket->handler_email, $T['subject'], $email_body, $headers);

        if ($sent) {
            // Update ticket with reply info
            $wpdb->update(
                "{$wpdb->prefix}ppv_support_tickets",
                [
                    'admin_reply' => $reply_message,
                    'reply_sent_at' => current_time('mysql'),
                    'status' => 'in_progress'
                ],
                ['id' => $ticket_id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            ppv_log("📧 [Support Admin] Válasz elküldve ticket #{$ticket_id} - {$ticket->handler_email}");
            wp_redirect("/admin/support?success=reply_sent");
        } else {
            ppv_log("❌ [Support Admin] Válasz küldése sikertelen ticket #{$ticket_id}");
            wp_redirect("/admin/support?error=reply_failed");
        }
        exit;
    }

    /**
     * Render HTML
     */
    private static function render_html($tickets, $status_filter, $category_filter, $new_count, $in_progress_count, $open_count, $resolved_count, $category_counts, $avg_rating, $total_ratings) {
        $success = isset($_GET['success']) ? $_GET['success'] : '';
        $error = isset($_GET['error']) ? $_GET['error'] : '';
        $ticket_count = count($tickets);

        // Prepare tickets JSON for modal
        $tickets_json = [];
        foreach ($tickets as $ticket) {
            $user_type = $ticket->user_type ?? 'handler';
            $sender_name = '';
            if ($user_type === 'user' && !empty($ticket->user_first_name)) {
                $sender_name = trim(($ticket->user_first_name ?? '') . ' ' . ($ticket->user_last_name ?? ''));
            } elseif (!empty($ticket->company_name)) {
                $sender_name = $ticket->company_name;
            } else {
                $sender_name = $ticket->store_name;
            }

            $tickets_json[$ticket->id] = [
                'id' => $ticket->id,
                'category' => $ticket->category ?? 'support',
                'user_type' => $user_type,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'sender_name' => $sender_name,
                'email' => $ticket->handler_email,
                'phone' => $ticket->handler_phone,
                'store_id' => $ticket->store_id,
                'user_id' => $ticket->user_id,
                'language' => $ticket->language ?? 'de',
                'rating' => $ticket->rating,
                'description' => $ticket->description,
                'page_url' => $ticket->page_url,
                'device_info' => $ticket->device_info ?? '',
                'created_at' => date('Y-m-d H:i', strtotime($ticket->created_at)),
                'resolved_at' => $ticket->resolved_at ? date('Y-m-d H:i', strtotime($ticket->resolved_at)) : null,
                'admin_reply' => $ticket->admin_reply ?? '',
                'reply_sent_at' => $ticket->reply_sent_at ? date('Y-m-d H:i', strtotime($ticket->reply_sent_at)) : null
            ];
        }
        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Feedback & Support - PunktePass Admin</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #1a1a2e;
                    color: #e0e0e0;
                    min-height: 100vh;
                }
                .admin-header {
                    background: #16213e;
                    padding: 15px 20px;
                    border-bottom: 1px solid #0f3460;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 10px;
                }
                .admin-header h1 { font-size: 18px; color: #00d9ff; }
                .header-right { display: flex; align-items: center; gap: 15px; }
                .admin-header .back-link { color: #aaa; text-decoration: none; font-size: 14px; }
                .admin-header .back-link:hover { color: #00d9ff; }
                .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
                .success-msg {
                    background: #0f5132;
                    color: #d1e7dd;
                    padding: 12px 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }
                .error-msg {
                    background: #842029;
                    color: #f8d7da;
                    padding: 12px 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }
                .rating-overview {
                    background: linear-gradient(135deg, #5b21b6 0%, #7c3aed 100%);
                    padding: 20px;
                    border-radius: 12px;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    gap: 20px;
                }
                .rating-overview .rating-stars {
                    font-size: 32px;
                    color: #ffd93d;
                }
                .rating-overview .rating-number {
                    font-size: 48px;
                    font-weight: bold;
                    color: #fff;
                }
                .rating-overview .rating-info {
                    color: rgba(255,255,255,0.8);
                    font-size: 14px;
                }
                .filter-section {
                    margin-bottom: 15px;
                }
                .filter-section h3 {
                    font-size: 12px;
                    color: #888;
                    margin-bottom: 8px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .tabs {
                    display: flex;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .tab {
                    padding: 8px 16px;
                    background: #16213e;
                    border-radius: 8px;
                    text-decoration: none;
                    color: #aaa;
                    font-weight: 600;
                    font-size: 13px;
                    transition: all 0.2s;
                }
                .tab:hover { background: #1f2b4d; color: #fff; }
                .tab.active { background: #00d9ff; color: #000; }
                .tab.cat-bug.active { background: #ff6b6b; }
                .tab.cat-feature.active { background: #ffd93d; color: #000; }
                .tab.cat-question.active { background: #6bcb77; }
                .tab.cat-rating.active { background: #a78bfa; }
                .tab.cat-support.active { background: #00d9ff; }
                .empty-state {
                    text-align: center;
                    padding: 60px 20px;
                    background: #16213e;
                    border-radius: 12px;
                    color: #888;
                }
                .empty-state i { font-size: 48px; margin-bottom: 15px; display: block; color: #00d9ff; }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    background: #16213e;
                    border-radius: 12px;
                    overflow: hidden;
                }
                th, td {
                    padding: 12px 15px;
                    text-align: left;
                    border-bottom: 1px solid #0f3460;
                    font-size: 13px;
                }
                th { background: #0f3460; color: #00d9ff; font-weight: 600; }
                tr:hover { background: #1f2b4d; }
                tr.urgent { background: #3d1f1f; }
                tr.urgent:hover { background: #4d2929; }
                tr { cursor: pointer; }
                .badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 11px;
                    font-weight: 600;
                    white-space: nowrap;
                }
                .badge-success { background: #0f5132; color: #d1e7dd; }
                .badge-info { background: #084298; color: #cfe2ff; }
                .badge-warning { background: #664d03; color: #fff3cd; }
                .badge-error { background: #842029; color: #f8d7da; }
                .badge-purple { background: #5b21b6; color: #ede9fe; }
                .badge-teal { background: #0d9488; color: #ccfbf1; }
                .badge-user { background: #1e40af; color: #dbeafe; }
                .badge-handler { background: #065f46; color: #d1fae5; }
                .badge-lang { background: #374151; color: #fff; font-size: 10px; padding: 2px 6px; }
                .btn {
                    display: inline-block;
                    padding: 6px 12px;
                    border-radius: 6px;
                    font-size: 11px;
                    font-weight: 600;
                    text-decoration: none;
                    cursor: pointer;
                    border: none;
                    transition: all 0.2s;
                    margin: 2px;
                }
                .btn-primary { background: #00d9ff; color: #000; }
                .btn-primary:hover { background: #00b8d9; }
                .btn-warning { background: #ffb900; color: #000; }
                .btn-warning:hover { background: #e0a800; }
                .btn-secondary { background: #374151; color: #fff; }
                .btn-secondary:hover { background: #4b5563; }
                .btn-success { background: #10b981; color: #fff; }
                .btn-success:hover { background: #059669; }
                a { color: #00d9ff; text-decoration: none; }
                a:hover { text-decoration: underline; }
                .description { max-width: 250px; }
                .description-text {
                    display: block;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                .sender-info { line-height: 1.5; }

                /* Modal Styles */
                .modal-overlay {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0,0,0,0.8);
                    z-index: 1000;
                    justify-content: center;
                    align-items: flex-start;
                    padding: 20px;
                    overflow-y: auto;
                }
                .modal-overlay.active { display: flex; }
                .modal {
                    background: #16213e;
                    border-radius: 16px;
                    width: 100%;
                    max-width: 800px;
                    margin: 20px auto;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
                }
                .modal-header {
                    padding: 20px;
                    border-bottom: 1px solid #0f3460;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .modal-header h2 {
                    font-size: 18px;
                    color: #00d9ff;
                }
                .modal-close {
                    background: none;
                    border: none;
                    color: #888;
                    font-size: 24px;
                    cursor: pointer;
                    padding: 5px;
                }
                .modal-close:hover { color: #fff; }
                .modal-body {
                    padding: 20px;
                }
                .ticket-info-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 15px;
                    margin-bottom: 20px;
                }
                .info-card {
                    background: #0f3460;
                    padding: 15px;
                    border-radius: 8px;
                }
                .info-card label {
                    display: block;
                    font-size: 11px;
                    color: #888;
                    text-transform: uppercase;
                    margin-bottom: 5px;
                }
                .info-card .value {
                    font-size: 14px;
                    color: #fff;
                    word-break: break-word;
                }
                .message-box {
                    background: #0a1628;
                    border: 1px solid #0f3460;
                    border-radius: 8px;
                    padding: 20px;
                    margin-bottom: 20px;
                }
                .message-box h4 {
                    font-size: 12px;
                    color: #888;
                    text-transform: uppercase;
                    margin-bottom: 10px;
                }
                .message-box .content {
                    white-space: pre-wrap;
                    line-height: 1.6;
                    color: #e0e0e0;
                }
                .reply-section {
                    background: #0f3460;
                    border-radius: 8px;
                    padding: 20px;
                }
                .reply-section h4 {
                    font-size: 14px;
                    color: #00d9ff;
                    margin-bottom: 15px;
                }
                .reply-section textarea {
                    width: 100%;
                    min-height: 150px;
                    background: #0a1628;
                    border: 1px solid #1f2b4d;
                    border-radius: 8px;
                    padding: 15px;
                    color: #fff;
                    font-family: inherit;
                    font-size: 14px;
                    resize: vertical;
                    margin-bottom: 15px;
                }
                .reply-section textarea:focus {
                    outline: none;
                    border-color: #00d9ff;
                }
                .reply-section .footer-preview {
                    background: #0a1628;
                    border: 1px solid #1f2b4d;
                    border-radius: 8px;
                    padding: 15px;
                    margin-bottom: 15px;
                    font-size: 12px;
                    color: #888;
                }
                .reply-history {
                    background: #065f46;
                    border-radius: 8px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                .reply-history h4 {
                    color: #d1fae5;
                    font-size: 12px;
                    margin-bottom: 10px;
                }
                .reply-history .reply-text {
                    color: #fff;
                    white-space: pre-wrap;
                }
                .reply-history .reply-time {
                    color: #a7f3d0;
                    font-size: 11px;
                    margin-top: 10px;
                }
                .modal-actions {
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .star-rating { color: #ffd93d; }
            </style>
        </head>
        <body>
            <div class="admin-header">
                <h1>Feedback & Support (<?php echo $open_count; ?> nyitott)</h1>
                <div class="header-right">
                    <a href="/admin" class="back-link"><i class="ri-arrow-left-line"></i> Vissza</a>
                </div>
            </div>

            <div class="container">
                <?php if ($success === 'updated'): ?>
                    <div class="success-msg">Jegy sikeresen frissítve!</div>
                <?php elseif ($success === 'reply_sent'): ?>
                    <div class="success-msg">Válasz sikeresen elküldve!</div>
                <?php endif; ?>

                <?php if ($error === 'reply_failed'): ?>
                    <div class="error-msg">Hiba: A válasz küldése sikertelen!</div>
                <?php elseif ($error === 'empty_reply'): ?>
                    <div class="error-msg">Hiba: A válasz mező üres!</div>
                <?php endif; ?>

                <!-- Rating Overview (only show if we have ratings) -->
                <?php if ($total_ratings > 0 || $category_filter === 'rating'): ?>
                <div class="rating-overview">
                    <div class="rating-stars">
                        <?php
                        $display_rating = $avg_rating ? round($avg_rating, 1) : 0;
                        $full_stars = floor($display_rating);
                        $half_star = ($display_rating - $full_stars) >= 0.5;
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $full_stars) {
                                echo '<i class="ri-star-fill"></i>';
                            } elseif ($i == $full_stars + 1 && $half_star) {
                                echo '<i class="ri-star-half-fill"></i>';
                            } else {
                                echo '<i class="ri-star-line"></i>';
                            }
                        }
                        ?>
                    </div>
                    <div>
                        <div class="rating-number"><?php echo number_format($display_rating, 1); ?></div>
                        <div class="rating-info"><?php echo $total_ratings; ?> értékelés összesen</div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Status Filter -->
                <div class="filter-section">
                    <h3>Státusz</h3>
                    <div class="tabs">
                        <a href="/admin/support?status=open&cat=<?php echo $category_filter; ?>" class="tab <?php echo $status_filter === 'open' ? 'active' : ''; ?>">
                            Nyitott (<?php echo $open_count; ?>)
                        </a>
                        <a href="/admin/support?status=new&cat=<?php echo $category_filter; ?>" class="tab <?php echo $status_filter === 'new' ? 'active' : ''; ?>">
                            Új (<?php echo $new_count; ?>)
                        </a>
                        <a href="/admin/support?status=in_progress&cat=<?php echo $category_filter; ?>" class="tab <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>">
                            Folyamatban (<?php echo $in_progress_count; ?>)
                        </a>
                        <a href="/admin/support?status=resolved&cat=<?php echo $category_filter; ?>" class="tab <?php echo $status_filter === 'resolved' ? 'active' : ''; ?>">
                            Megoldva (<?php echo $resolved_count; ?>)
                        </a>
                    </div>
                </div>

                <!-- Category Filter -->
                <div class="filter-section">
                    <h3>Kategória</h3>
                    <div class="tabs">
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=all" class="tab <?php echo $category_filter === 'all' ? 'active' : ''; ?>">
                            Mind
                        </a>
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=bug" class="tab cat-bug <?php echo $category_filter === 'bug' ? 'active' : ''; ?>">
                            Bug (<?php echo $category_counts['bug']; ?>)
                        </a>
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=feature" class="tab cat-feature <?php echo $category_filter === 'feature' ? 'active' : ''; ?>">
                            Ötlet (<?php echo $category_counts['feature']; ?>)
                        </a>
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=question" class="tab cat-question <?php echo $category_filter === 'question' ? 'active' : ''; ?>">
                            Kérdés (<?php echo $category_counts['question']; ?>)
                        </a>
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=rating" class="tab cat-rating <?php echo $category_filter === 'rating' ? 'active' : ''; ?>">
                            Értékelés (<?php echo $category_counts['rating']; ?>)
                        </a>
                        <a href="/admin/support?status=<?php echo $status_filter; ?>&cat=support" class="tab cat-support <?php echo $category_filter === 'support' ? 'active' : ''; ?>">
                            Support (<?php echo $category_counts['support']; ?>)
                        </a>
                    </div>
                </div>

                <?php if ($ticket_count === 0): ?>
                    <div class="empty-state">
                        <i class="ri-checkbox-circle-line"></i>
                        <h3>Nincs jegy ebben a kategóriában!</h3>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kategória</th>
                                <th>Típus</th>
                                <th>Küldő</th>
                                <th>Üzenet</th>
                                <th>Létrehozva</th>
                                <th>Műveletek</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <?php
                                // Category badges with icons
                                $category_badges = [
                                    'bug' => ['icon' => 'ri-bug-line', 'text' => 'Bug', 'class' => 'error'],
                                    'feature' => ['icon' => 'ri-lightbulb-line', 'text' => 'Ötlet', 'class' => 'warning'],
                                    'question' => ['icon' => 'ri-question-line', 'text' => 'Kérdés', 'class' => 'teal'],
                                    'rating' => ['icon' => 'ri-star-line', 'text' => 'Értékelés', 'class' => 'purple'],
                                    'support' => ['icon' => 'ri-customer-service-line', 'text' => 'Support', 'class' => 'info']
                                ];
                                $cat = $ticket->category ?? 'support';
                                $category_badge = $category_badges[$cat] ?? $category_badges['support'];

                                // User type badge
                                $user_type = $ticket->user_type ?? 'handler';
                                $user_type_badge = $user_type === 'handler'
                                    ? ['icon' => 'ri-store-2-line', 'text' => 'Kereskedő', 'class' => 'handler']
                                    : ['icon' => 'ri-user-line', 'text' => 'Ügyfél', 'class' => 'user'];

                                // Status badges
                                $status_badges = [
                                    'new' => ['text' => 'Új', 'class' => 'info'],
                                    'in_progress' => ['text' => 'Folyamatban', 'class' => 'warning'],
                                    'resolved' => ['text' => 'Megoldva', 'class' => 'success']
                                ];
                                $status_badge = $status_badges[$ticket->status] ?? $status_badges['new'];

                                $created_time = date('Y-m-d H:i', strtotime($ticket->created_at));

                                // Get sender name
                                $sender_name = '';
                                if ($user_type === 'user' && !empty($ticket->user_first_name)) {
                                    $sender_name = trim(($ticket->user_first_name ?? '') . ' ' . ($ticket->user_last_name ?? ''));
                                } elseif (!empty($ticket->company_name)) {
                                    $sender_name = $ticket->company_name;
                                } else {
                                    $sender_name = $ticket->store_name;
                                }

                                $description_short = mb_strlen($ticket->description) > 80
                                    ? mb_substr($ticket->description, 0, 80) . '...'
                                    : $ticket->description;

                                // Language badge
                                $lang_display = strtoupper($ticket->language ?? 'DE');
                                ?>
                                <tr class="<?php echo $ticket->priority === 'urgent' ? 'urgent' : ''; ?>" onclick="openTicketModal(<?php echo intval($ticket->id); ?>)">
                                    <td>
                                        <strong>#<?php echo intval($ticket->id); ?></strong>
                                        <br><span class="badge badge-<?php echo $status_badge['class']; ?>"><?php echo $status_badge['text']; ?></span>
                                        <?php if ($ticket->rating && $cat === 'rating'): ?>
                                        <br><span class="star-rating"><?php echo str_repeat('★', $ticket->rating); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $category_badge['class']; ?>">
                                            <i class="<?php echo $category_badge['icon']; ?>"></i> <?php echo $category_badge['text']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $user_type_badge['class']; ?>">
                                            <i class="<?php echo $user_type_badge['icon']; ?>"></i> <?php echo $user_type_badge['text']; ?>
                                        </span>
                                        <br><span class="badge badge-lang"><?php echo $lang_display; ?></span>
                                    </td>
                                    <td class="sender-info">
                                        <strong><?php echo esc_html($sender_name); ?></strong>
                                        <br><span style="font-size: 11px; color: #888;"><?php echo esc_html($ticket->handler_email); ?></span>
                                    </td>
                                    <td class="description">
                                        <span class="description-text" title="<?php echo esc_attr($ticket->description); ?>">
                                            <?php echo esc_html($description_short); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $created_time; ?></td>
                                    <td onclick="event.stopPropagation();">
                                        <?php if ($ticket->status === 'new'): ?>
                                            <form method="post" style="display: inline-block;">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="ticket_id" value="<?php echo intval($ticket->id); ?>">
                                                <input type="hidden" name="new_status" value="in_progress">
                                                <button type="submit" class="btn btn-warning" title="Felvétel">📋</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($ticket->status !== 'resolved'): ?>
                                            <form method="post" style="display: inline-block;">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="ticket_id" value="<?php echo intval($ticket->id); ?>">
                                                <input type="hidden" name="new_status" value="resolved">
                                                <button type="submit" class="btn btn-primary" onclick="return confirm('Megoldottként jelöli?');" title="Megoldva">✅</button>
                                            </form>
                                        <?php endif; ?>

                                        <button class="btn btn-secondary" onclick="openTicketModal(<?php echo intval($ticket->id); ?>)" title="Részletek">👁️</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Ticket Detail Modal -->
            <div class="modal-overlay" id="ticketModal">
                <div class="modal">
                    <div class="modal-header">
                        <h2><i class="ri-ticket-line"></i> Jegy #<span id="modal-ticket-id"></span></h2>
                        <button class="modal-close" onclick="closeTicketModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="ticket-info-grid">
                            <div class="info-card">
                                <label>Kategória</label>
                                <div class="value" id="modal-category"></div>
                            </div>
                            <div class="info-card">
                                <label>Típus</label>
                                <div class="value" id="modal-user-type"></div>
                            </div>
                            <div class="info-card">
                                <label>Státusz</label>
                                <div class="value" id="modal-status"></div>
                            </div>
                            <div class="info-card">
                                <label>Nyelv</label>
                                <div class="value" id="modal-language"></div>
                            </div>
                            <div class="info-card">
                                <label>Küldő</label>
                                <div class="value" id="modal-sender"></div>
                            </div>
                            <div class="info-card">
                                <label>Email</label>
                                <div class="value" id="modal-email"></div>
                            </div>
                            <div class="info-card">
                                <label>Store ID / User ID</label>
                                <div class="value" id="modal-ids"></div>
                            </div>
                            <div class="info-card">
                                <label>Létrehozva</label>
                                <div class="value" id="modal-created"></div>
                            </div>
                        </div>

                        <div id="modal-rating-section" style="display: none;">
                            <div class="info-card" style="background: #5b21b6; margin-bottom: 20px;">
                                <label style="color: rgba(255,255,255,0.7);">Értékelés</label>
                                <div class="value star-rating" style="font-size: 24px;" id="modal-rating"></div>
                            </div>
                        </div>

                        <div class="message-box">
                            <h4>Üzenet</h4>
                            <div class="content" id="modal-description"></div>
                        </div>

                        <div class="info-card" style="margin-bottom: 20px;" id="modal-device-section">
                            <label>Eszköz & Oldal</label>
                            <div class="value" id="modal-device"></div>
                        </div>

                        <div id="modal-reply-history" style="display: none;" class="reply-history">
                            <h4>Korábbi válasz</h4>
                            <div class="reply-text" id="modal-previous-reply"></div>
                            <div class="reply-time" id="modal-reply-time"></div>
                        </div>

                        <div class="reply-section" id="modal-reply-section">
                            <h4>Válasz küldése</h4>
                            <form method="post" id="reply-form">
                                <input type="hidden" name="send_reply" value="1">
                                <input type="hidden" name="ticket_id" id="reply-ticket-id" value="">
                                <textarea name="reply_message" id="reply-message" placeholder="Írd be a válaszodat..."></textarea>
                                <div class="footer-preview">
                                    <strong>Automatikus lábléc:</strong><br>
                                    Mit freundlichen Grüßen / Üdvözlettel / Best regards,<br>
                                    Ihr PunktePass-Team<br>
                                    www.punktepass.de
                                </div>
                                <div class="modal-actions">
                                    <button type="submit" class="btn btn-success">📧 Válasz küldése</button>
                                    <button type="button" class="btn btn-secondary" onclick="closeTicketModal()">Bezárás</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            const ticketsData = <?php echo json_encode($tickets_json); ?>;

            const categoryNames = {
                'bug': '🐛 Bug',
                'feature': '💡 Ötlet',
                'question': '❓ Kérdés',
                'rating': '⭐ Értékelés',
                'support': '🎧 Support'
            };

            const userTypeNames = {
                'handler': '🏪 Kereskedő',
                'user': '👤 Ügyfél'
            };

            const statusNames = {
                'new': 'Új',
                'in_progress': 'Folyamatban',
                'resolved': 'Megoldva'
            };

            const languageNames = {
                'de': '🇩🇪 Német',
                'hu': '🇭🇺 Magyar',
                'en': '🇬🇧 Angol'
            };

            function openTicketModal(ticketId) {
                const ticket = ticketsData[ticketId];
                if (!ticket) return;

                document.getElementById('modal-ticket-id').textContent = ticket.id;
                document.getElementById('modal-category').textContent = categoryNames[ticket.category] || ticket.category;
                document.getElementById('modal-user-type').textContent = userTypeNames[ticket.user_type] || ticket.user_type;
                document.getElementById('modal-status').textContent = statusNames[ticket.status] || ticket.status;
                document.getElementById('modal-language').textContent = languageNames[ticket.language] || ticket.language.toUpperCase();
                document.getElementById('modal-sender').textContent = ticket.sender_name;
                document.getElementById('modal-email').innerHTML = '<a href="mailto:' + ticket.email + '">' + ticket.email + '</a>';
                document.getElementById('modal-ids').textContent = 'Store: #' + ticket.store_id + ' / User: #' + ticket.user_id;
                document.getElementById('modal-created').textContent = ticket.created_at;
                document.getElementById('modal-description').textContent = ticket.description;

                // Device info
                let deviceInfo = '';
                if (ticket.device_info) deviceInfo += '📱 ' + ticket.device_info + '\n';
                if (ticket.page_url) deviceInfo += '🌐 ' + ticket.page_url;
                document.getElementById('modal-device').textContent = deviceInfo || 'Nincs adat';

                // Rating section
                const ratingSection = document.getElementById('modal-rating-section');
                if (ticket.category === 'rating' && ticket.rating) {
                    ratingSection.style.display = 'block';
                    document.getElementById('modal-rating').textContent = '★'.repeat(ticket.rating) + '☆'.repeat(5 - ticket.rating) + ' (' + ticket.rating + '/5)';
                } else {
                    ratingSection.style.display = 'none';
                }

                // Previous reply
                const replyHistory = document.getElementById('modal-reply-history');
                if (ticket.admin_reply) {
                    replyHistory.style.display = 'block';
                    document.getElementById('modal-previous-reply').textContent = ticket.admin_reply;
                    document.getElementById('modal-reply-time').textContent = 'Elküldve: ' + ticket.reply_sent_at;
                } else {
                    replyHistory.style.display = 'none';
                }

                // Reply form
                document.getElementById('reply-ticket-id').value = ticket.id;
                document.getElementById('reply-message').value = '';

                // Hide reply section for resolved tickets
                const replySection = document.getElementById('modal-reply-section');
                replySection.style.display = ticket.status === 'resolved' ? 'none' : 'block';

                document.getElementById('ticketModal').classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeTicketModal() {
                document.getElementById('ticketModal').classList.remove('active');
                document.body.style.overflow = '';
            }

            // Close modal on overlay click
            document.getElementById('ticketModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeTicketModal();
                }
            });

            // Close modal on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeTicketModal();
                }
            });
            </script>
        </body>
        </html>
        <?php
    }
}
