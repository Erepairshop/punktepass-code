<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass - WhatsApp Support Chat UI
 *
 * Provides a chat interface for store owners to view and respond
 * to customer WhatsApp messages.
 */
class PPV_WhatsApp_Chat {

    public static function hooks() {
        add_shortcode('ppv_whatsapp_chat', [__CLASS__, 'render_chat']);
        add_action('wp_ajax_ppv_whatsapp_mark_read', [__CLASS__, 'ajax_mark_read']);
    }

    /**
     * Render the WhatsApp chat interface
     */
    public static function render_chat($atts = []) {
        // Get store from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $store_id = $_SESSION['ppv_store_id'] ?? 0;
        if (!$store_id) {
            return '<div class="ppv-error">Nicht eingeloggt.</div>';
        }

        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if (!$store || empty($store->whatsapp_enabled) || empty($store->whatsapp_support_enabled)) {
            return '<div class="ppv-info" style="padding: 20px; background: rgba(255,255,255,0.05); border-radius: 12px; text-align: center;">
                <p style="color: #888;">WhatsApp Support ist nicht aktiviert.</p>
                <p><a href="/profil/?tab=marketing" style="color: #25D366;">Jetzt aktivieren →</a></p>
            </div>';
        }

        // Get conversations
        $conversations = $wpdb->get_results($wpdb->prepare("
            SELECT c.*, u.display_name as user_name, u.email as user_email
            FROM {$wpdb->prefix}ppv_whatsapp_conversations c
            LEFT JOIN {$wpdb->prefix}ppv_users u ON c.user_id = u.id
            WHERE c.store_id = %d
            ORDER BY c.last_message_at DESC
            LIMIT 50
        ", $store_id));

        ob_start();
        ?>
        <div class="ppv-wa-chat-container" data-store-id="<?php echo esc_attr($store_id); ?>">
            <style>
                .ppv-wa-chat-container {
                    display: grid;
                    grid-template-columns: 300px 1fr;
                    gap: 0;
                    background: #111827;
                    border-radius: 16px;
                    overflow: hidden;
                    height: 600px;
                    border: 1px solid rgba(255,255,255,0.1);
                }
                .ppv-wa-sidebar {
                    background: #1f2937;
                    border-right: 1px solid rgba(255,255,255,0.1);
                    overflow-y: auto;
                }
                .ppv-wa-sidebar-header {
                    padding: 15px;
                    background: #25D366;
                    color: white;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    position: sticky;
                    top: 0;
                }
                .ppv-wa-conv-item {
                    padding: 12px 15px;
                    border-bottom: 1px solid rgba(255,255,255,0.05);
                    cursor: pointer;
                    transition: background 0.2s;
                }
                .ppv-wa-conv-item:hover, .ppv-wa-conv-item.active {
                    background: rgba(37, 211, 102, 0.1);
                }
                .ppv-wa-conv-name {
                    font-weight: 600;
                    color: #f1f5f9;
                    font-size: 14px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .ppv-wa-conv-phone {
                    color: #64748b;
                    font-size: 12px;
                    margin-top: 2px;
                }
                .ppv-wa-conv-preview {
                    color: #94a3b8;
                    font-size: 12px;
                    margin-top: 4px;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                .ppv-wa-conv-time {
                    color: #64748b;
                    font-size: 10px;
                }
                .ppv-wa-unread {
                    background: #25D366;
                    color: white;
                    font-size: 10px;
                    padding: 2px 6px;
                    border-radius: 10px;
                    font-weight: 600;
                }
                .ppv-wa-main {
                    display: flex;
                    flex-direction: column;
                }
                .ppv-wa-main-header {
                    padding: 15px;
                    background: #1f2937;
                    border-bottom: 1px solid rgba(255,255,255,0.1);
                    min-height: 60px;
                }
                .ppv-wa-messages {
                    flex: 1;
                    overflow-y: auto;
                    padding: 15px;
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }
                .ppv-wa-msg {
                    max-width: 70%;
                    padding: 10px 14px;
                    border-radius: 12px;
                    font-size: 14px;
                    line-height: 1.4;
                }
                .ppv-wa-msg.inbound {
                    background: #374151;
                    color: #f1f5f9;
                    align-self: flex-start;
                    border-bottom-left-radius: 4px;
                }
                .ppv-wa-msg.outbound {
                    background: #25D366;
                    color: white;
                    align-self: flex-end;
                    border-bottom-right-radius: 4px;
                }
                .ppv-wa-msg-time {
                    font-size: 10px;
                    opacity: 0.7;
                    margin-top: 4px;
                    text-align: right;
                }
                .ppv-wa-input-area {
                    padding: 15px;
                    background: #1f2937;
                    border-top: 1px solid rgba(255,255,255,0.1);
                    display: flex;
                    gap: 10px;
                }
                .ppv-wa-input {
                    flex: 1;
                    background: #374151;
                    border: 1px solid rgba(255,255,255,0.1);
                    border-radius: 24px;
                    padding: 10px 16px;
                    color: #f1f5f9;
                    font-size: 14px;
                    outline: none;
                }
                .ppv-wa-input:focus {
                    border-color: #25D366;
                }
                .ppv-wa-send-btn {
                    background: #25D366;
                    color: white;
                    border: none;
                    border-radius: 50%;
                    width: 44px;
                    height: 44px;
                    cursor: pointer;
                    font-size: 18px;
                    transition: transform 0.2s;
                }
                .ppv-wa-send-btn:hover {
                    transform: scale(1.05);
                }
                .ppv-wa-send-btn:disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }
                .ppv-wa-empty {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    height: 100%;
                    color: #64748b;
                    text-align: center;
                    padding: 20px;
                }
                .ppv-wa-empty-icon {
                    font-size: 48px;
                    margin-bottom: 10px;
                    opacity: 0.5;
                }
                @media (max-width: 768px) {
                    .ppv-wa-chat-container {
                        grid-template-columns: 1fr;
                        height: auto;
                    }
                    .ppv-wa-sidebar {
                        max-height: 300px;
                    }
                    .ppv-wa-messages {
                        min-height: 300px;
                    }
                }
            </style>

            <div class="ppv-wa-sidebar">
                <div class="ppv-wa-sidebar-header">
                    <span>💬</span> WhatsApp Support
                </div>
                <?php if (empty($conversations)): ?>
                    <div class="ppv-wa-empty" style="padding: 40px 20px;">
                        <div class="ppv-wa-empty-icon">📭</div>
                        <p>Noch keine Nachrichten</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <div class="ppv-wa-conv-item" data-phone="<?php echo esc_attr($conv->phone_number); ?>">
                            <div class="ppv-wa-conv-name">
                                <span><?php echo esc_html($conv->customer_name ?: 'Unbekannt'); ?></span>
                                <?php if ($conv->unread_count > 0): ?>
                                    <span class="ppv-wa-unread"><?php echo intval($conv->unread_count); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="ppv-wa-conv-phone"><?php echo esc_html(self::format_phone($conv->phone_number)); ?></div>
                            <div class="ppv-wa-conv-preview"><?php echo esc_html($conv->last_message_preview); ?></div>
                            <div class="ppv-wa-conv-time"><?php echo esc_html(self::time_ago($conv->last_message_at)); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="ppv-wa-main">
                <div class="ppv-wa-main-header" id="wa-chat-header">
                    <div class="ppv-wa-empty" style="height: auto; padding: 0;">
                        <p style="margin: 0;">Wählen Sie eine Konversation</p>
                    </div>
                </div>
                <div class="ppv-wa-messages" id="wa-messages">
                    <div class="ppv-wa-empty">
                        <div class="ppv-wa-empty-icon">💬</div>
                        <p>Klicken Sie links auf eine Konversation<br>um die Nachrichten zu sehen</p>
                    </div>
                </div>
                <div class="ppv-wa-input-area">
                    <input type="text" class="ppv-wa-input" id="wa-message-input" placeholder="Nachricht schreiben..." disabled>
                    <button class="ppv-wa-send-btn" id="wa-send-btn" disabled>➤</button>
                </div>
            </div>
        </div>

        <script>
        (function() {
            const container = document.querySelector('.ppv-wa-chat-container');
            const storeId = container.dataset.storeId;
            const messagesEl = document.getElementById('wa-messages');
            const headerEl = document.getElementById('wa-chat-header');
            const inputEl = document.getElementById('wa-message-input');
            const sendBtn = document.getElementById('wa-send-btn');
            let currentPhone = null;

            // Click on conversation
            document.querySelectorAll('.ppv-wa-conv-item').forEach(item => {
                item.addEventListener('click', function() {
                    document.querySelectorAll('.ppv-wa-conv-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');

                    currentPhone = this.dataset.phone;
                    const name = this.querySelector('.ppv-wa-conv-name span').textContent;
                    const phone = this.querySelector('.ppv-wa-conv-phone').textContent;

                    // Update header
                    headerEl.innerHTML = `
                        <div style="font-weight: 600; color: #f1f5f9;">${name}</div>
                        <div style="font-size: 12px; color: #64748b;">${phone}</div>
                    `;

                    // Enable input
                    inputEl.disabled = false;
                    sendBtn.disabled = false;

                    // Load messages
                    loadMessages(currentPhone);

                    // Clear unread badge
                    const badge = this.querySelector('.ppv-wa-unread');
                    if (badge) badge.remove();
                });
            });

            // Load messages for a conversation
            function loadMessages(phone) {
                messagesEl.innerHTML = '<div style="text-align: center; color: #888; padding: 20px;">Laden...</div>';

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=ppv_whatsapp_get_messages&nonce=<?php echo wp_create_nonce('ppv_whatsapp_nonce'); ?>&store_id=${storeId}&phone=${encodeURIComponent(phone)}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data.messages) {
                        renderMessages(data.data.messages);
                    } else {
                        messagesEl.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 20px;">Fehler beim Laden</div>';
                    }
                });
            }

            // Render messages
            function renderMessages(messages) {
                if (!messages.length) {
                    messagesEl.innerHTML = '<div class="ppv-wa-empty"><p>Keine Nachrichten</p></div>';
                    return;
                }

                messagesEl.innerHTML = messages.map(m => {
                    const content = m.message_type === 'template'
                        ? `[Template: ${m.template_name || 'unknown'}]`
                        : (typeof m.message_content === 'string' && m.message_content.startsWith('{')
                            ? JSON.parse(m.message_content).text || m.message_content
                            : m.message_content);

                    const time = new Date(m.created_at).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
                    const date = new Date(m.created_at).toLocaleDateString('de-DE');

                    return `
                        <div class="ppv-wa-msg ${m.direction}">
                            ${escapeHtml(content)}
                            <div class="ppv-wa-msg-time">${date} ${time}</div>
                        </div>
                    `;
                }).join('');

                messagesEl.scrollTop = messagesEl.scrollHeight;
            }

            // Send message
            sendBtn.addEventListener('click', sendMessage);
            inputEl.addEventListener('keypress', e => { if (e.key === 'Enter') sendMessage(); });

            function sendMessage() {
                const text = inputEl.value.trim();
                if (!text || !currentPhone) return;

                sendBtn.disabled = true;
                inputEl.disabled = true;

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=ppv_whatsapp_send_message&nonce=<?php echo wp_create_nonce('ppv_whatsapp_nonce'); ?>&store_id=${storeId}&phone=${encodeURIComponent(currentPhone)}&message=${encodeURIComponent(text)}`
                })
                .then(r => r.json())
                .then(data => {
                    inputEl.value = '';
                    inputEl.disabled = false;
                    sendBtn.disabled = false;
                    inputEl.focus();

                    if (data.success) {
                        loadMessages(currentPhone);
                    } else {
                        alert(data.data.message || 'Fehler beim Senden');
                        if (data.data.hint) {
                            alert(data.data.hint);
                        }
                    }
                });
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Format phone number for display
     */
    private static function format_phone($phone) {
        // Simple formatting: +49 176 1234567
        if (strlen($phone) >= 10) {
            return '+' . substr($phone, 0, 2) . ' ' . substr($phone, 2, 3) . ' ' . substr($phone, 5);
        }
        return $phone;
    }

    /**
     * Format time ago
     */
    private static function time_ago($datetime) {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 60) return 'Jetzt';
        if ($diff < 3600) return floor($diff / 60) . ' Min';
        if ($diff < 86400) return floor($diff / 3600) . ' Std';
        if ($diff < 604800) return floor($diff / 86400) . ' Tage';

        return date('d.m.Y', $time);
    }

    /**
     * Mark conversation as read
     */
    public static function ajax_mark_read() {
        check_ajax_referer('ppv_whatsapp_nonce', 'nonce');

        $store_id = intval($_POST['store_id'] ?? 0);
        $phone = sanitize_text_field($_POST['phone'] ?? '');

        if (!$store_id || !$phone) {
            wp_send_json_error(['message' => 'Missing parameters']);
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'ppv_whatsapp_conversations',
            ['unread_count' => 0],
            ['store_id' => $store_id, 'phone_number' => $phone]
        );

        wp_send_json_success();
    }
}

// Initialize
PPV_WhatsApp_Chat::hooks();
