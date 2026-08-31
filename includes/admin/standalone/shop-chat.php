<?php
/** Magyar, belső eRepairShop ügyfélchat. */

if (!defined('ABSPATH')) exit;

final class PPV_Standalone_Shop_Chat {
    public static function handle_availability() {
        self::require_valid_post();
        $mode = sanitize_key($_POST['mode'] ?? 'auto');
        if (!PPV_Shop_Chat::set_availability_mode($mode)) {
            self::json_error('Az elérhetőség nem menthető.', 400);
        }
        self::json_success([
            'mode' => PPV_Shop_Chat::availability_mode(),
            'online' => PPV_Shop_Chat::is_online_now(),
        ]);
    }

    public static function handle_reply() {
        self::require_valid_post();
        PPV_Shop_Chat::install_schema();
        $conversation_id = (int)($_POST['conversation_id'] ?? 0);
        $message = trim(sanitize_textarea_field(wp_unslash($_POST['message'] ?? '')));
        try {
            $attachments = PPV_Shop_Chat::uploaded_attachments('attachments');
        } catch (InvalidArgumentException $e) {
            self::json_error($e->getMessage(), 400);
        }
        if ($conversation_id <= 0 || ($message === '' && !$attachments) || mb_strlen($message) > 2000) {
            self::json_error('A válasz hiányzik vagy túl hosszú.', 400);
        }
        global $wpdb;
        $table = $wpdb->prefix . PPV_Shop_Chat::CONVERSATIONS_SUFFIX;
        $conversation = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $conversation_id));
        if (!$conversation) self::json_error('A beszélgetés nem található.', 404);
        $sent_by_email = $conversation->reply_channel === 'email';
        if ($sent_by_email) {
            try {
                PPV_Shop_Chat::send_email_reply($conversation, $message, $attachments);
            } catch (Throwable $e) {
                error_log('[Shop Chat Email] ' . preg_replace('/[\r\n]+/', ' ', $e->getMessage()));
                self::json_error('Az email nem küldhető el, a válasz nem lett elmentve.', 502);
            }
        }
        $now = current_time('mysql');
        $ok = $wpdb->insert($wpdb->prefix . PPV_Shop_Chat::MESSAGES_SUFFIX, [
            'conversation_id' => $conversation_id,
            'sender' => 'admin',
            'message' => $message,
            'page_url' => '',
            'created_at' => $now,
        ]);
        if (!$ok) self::json_error('A válasz nem menthető.', 500);
        $message_id = (int)$wpdb->insert_id;
        try {
            PPV_Shop_Chat::store_attachments($message_id, $conversation_id, 'admin', $attachments);
        } catch (Throwable $e) {
            $wpdb->delete($wpdb->prefix . PPV_Shop_Chat::MESSAGES_SUFFIX, ['id' => $message_id], ['%d']);
            error_log('[Shop Chat Attachment] ' . preg_replace('/[\r\n]+/', ' ', $e->getMessage()));
            self::json_error('A csatolmány nem menthető.', 500);
        }
        $wpdb->update($table, [
            'status' => 'open',
            'unread_admin' => 0,
            'last_message_at' => $now,
            'updated_at' => $now,
        ], ['id' => $conversation_id]);
        self::json_success([
            'messages' => PPV_Shop_Chat::messages_for_conversation($conversation_id),
            'sentByEmail' => $sent_by_email,
        ]);
    }

    public static function handle_status() {
        self::require_valid_post();
        PPV_Shop_Chat::install_schema();
        $conversation_id = (int)($_POST['conversation_id'] ?? 0);
        $status = sanitize_key($_POST['status'] ?? 'open');
        if ($conversation_id <= 0 || !in_array($status, ['open', 'closed'], true)) {
            self::json_error('Érvénytelen állapot.', 400);
        }
        global $wpdb;
        $ok = $wpdb->update(
            $wpdb->prefix . PPV_Shop_Chat::CONVERSATIONS_SUFFIX,
            ['status' => $status, 'unread_admin' => 0, 'updated_at' => current_time('mysql')],
            ['id' => $conversation_id]
        );
        if ($ok === false) self::json_error('Az állapot nem menthető.', 500);
        self::json_success(['status' => $status]);
    }

    public static function handle_read() {
        self::require_valid_post();
        global $wpdb;
        $conversation_id = (int)($_POST['conversation_id'] ?? 0);
        if ($conversation_id > 0) {
            $wpdb->update(
                $wpdb->prefix . PPV_Shop_Chat::CONVERSATIONS_SUFFIX,
                ['unread_admin' => 0, 'updated_at' => current_time('mysql')],
                ['id' => $conversation_id]
            );
        }
        self::json_success([]);
    }

    public static function handle_attachment() {
        PPV_Shop_Chat::install_schema();
        $attachment_id = (int)($_GET['id'] ?? 0);
        if ($attachment_id <= 0 || !PPV_Shop_Chat::stream_attachment($attachment_id)) {
            status_header(404);
            exit;
        }
    }

    public static function render() {
        PPV_Shop_Chat::install_schema();
        global $wpdb;
        $table = $wpdb->prefix . PPV_Shop_Chat::CONVERSATIONS_SUFFIX;
        $selected_public_id = sanitize_text_field($_GET['conversation'] ?? '');
        $selected_id = (int)($_GET['id'] ?? 0);
        if ($selected_public_id !== '') {
            $selected_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE public_id=%s", $selected_public_id));
        }
        $conversations = $wpdb->get_results("SELECT * FROM {$table} ORDER BY (unread_admin>0) DESC,last_message_at DESC LIMIT 200");
        if (!$selected_id && $conversations) $selected_id = (int)$conversations[0]->id;
        $selected = null;
        foreach ($conversations as $conversation) {
            if ((int)$conversation->id === $selected_id) $selected = $conversation;
        }
        $messages = $selected ? PPV_Shop_Chat::messages_for_conversation($selected_id) : [];
        $csrf = self::csrf_token();
        $availability_mode = PPV_Shop_Chat::availability_mode();
        $is_online = PPV_Shop_Chat::is_online_now();
        PPV_Standalone_Admin::get_admin_header('shop-chat');
        self::styles();
        ?>
        <div class="chat-head">
            <div><h1 class="page-title"><i class="ri-chat-3-line"></i> Webshop chat</h1><p>Valódi ügyfélüzenetek, kézi válaszadással</p></div>
            <div class="chat-tools">
                <div class="availability-control">
                    <strong class="availability-state <?php echo $is_online ? 'online' : 'offline'; ?>"><?php echo $is_online ? 'ONLINE' : 'OFFLINE'; ?></strong>
                    <div class="availability-buttons">
                        <button type="button" data-availability="online" class="<?php echo $availability_mode === 'online' ? 'active' : ''; ?>">Online</button>
                        <button type="button" data-availability="offline" class="<?php echo $availability_mode === 'offline' ? 'active' : ''; ?>">Offline</button>
                        <button type="button" data-availability="auto" class="<?php echo $availability_mode === 'auto' ? 'active' : ''; ?>">Automatikus</button>
                    </div>
                </div>
                <button type="button" class="refresh" onclick="location.reload()"><i class="ri-refresh-line"></i> Frissítés</button>
            </div>
        </div>
        <div class="chat-layout">
            <aside class="thread-list">
                <?php if (!$conversations): ?><div class="empty">Még nincs beszélgetés.</div><?php endif; ?>
                <?php foreach ($conversations as $conversation): ?>
                    <a href="/admin/shop-chat?id=<?php echo (int)$conversation->id; ?>" class="thread <?php echo (int)$conversation->id === $selected_id ? 'active' : ''; ?>">
                        <span class="avatar"><?php echo esc_html(mb_strtoupper(mb_substr($conversation->customer_name, 0, 1))); ?></span>
                        <span class="thread-copy"><strong><?php echo esc_html($conversation->customer_name); ?></strong><small><?php echo esc_html($conversation->customer_email); ?></small><small><?php echo esc_html(date_i18n('m. d. H:i', strtotime($conversation->last_message_at))); ?></small></span>
                        <?php if ((int)$conversation->unread_admin > 0): ?><b class="unread"><?php echo (int)$conversation->unread_admin; ?></b><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </aside>
            <main class="conversation">
                <?php if (!$selected): ?><div class="empty">Válassz egy beszélgetést.</div><?php else: ?>
                    <div class="customer-head">
                        <div><strong><?php echo esc_html($selected->customer_name); ?></strong><a href="mailto:<?php echo esc_attr($selected->customer_email); ?>"><?php echo esc_html($selected->customer_email); ?></a><span class="channel <?php echo esc_attr($selected->reply_channel); ?>"><?php echo $selected->reply_channel === 'email' ? 'Válasz emailben' : 'Válasz a chatben'; ?></span></div>
                        <button type="button" id="status-button" data-status="<?php echo esc_attr($selected->status); ?>"><?php echo $selected->status === 'closed' ? 'Újranyitás' : 'Lezárás'; ?></button>
                    </div>
                    <div class="messages" id="messages">
                        <?php foreach ($messages as $message): ?>
                            <div class="bubble <?php echo $message['sender'] === 'admin' ? 'admin' : 'customer'; ?>">
                                <?php if ($message['message'] !== ''): ?><p><?php echo nl2br(esc_html($message['message'])); ?></p><?php endif; ?>
                                <?php if (!empty($message['attachments'])): ?><div class="chat-attachments">
                                    <?php foreach ($message['attachments'] as $attachment): $attachment_url = '/admin/shop-chat/attachment?id=' . (int)$attachment['id']; ?>
                                        <?php if (strpos($attachment['mime'], 'image/') === 0): ?>
                                            <a class="chat-attachment image" href="<?php echo esc_url($attachment_url); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url($attachment_url); ?>" alt=""><span><?php echo esc_html($attachment['name']); ?></span></a>
                                        <?php else: ?>
                                            <a class="chat-attachment file" href="<?php echo esc_url($attachment_url); ?>"><i class="ri-file-pdf-2-line"></i><span><?php echo esc_html($attachment['name']); ?></span></a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div><?php endif; ?>
                                <small><?php echo esc_html(date_i18n('Y. m. d. H:i', strtotime($message['createdAt']))); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form class="reply" id="reply-form">
                        <div class="reply-compose"><textarea id="reply-message" maxlength="2000" placeholder="Válasz a vásárlónak"></textarea><label class="attach-button"><i class="ri-attachment-2"></i> Kép vagy PDF<input id="reply-attachments" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple></label><small id="reply-files">Legfeljebb 3 fájl, fájlonként 5 MB.</small></div>
                        <button type="submit"><i class="ri-send-plane-fill"></i> <?php echo $selected->reply_channel === 'email' ? 'Email küldése' : 'Küldés'; ?></button>
                    </form>
                <?php endif; ?>
            </main>
        </div>
        <div class="toast" id="toast"></div>
        <?php self::scripts($csrf, $selected_id, $selected ? $selected->status : 'open'); ?>
        <?php PPV_Standalone_Admin::get_admin_footer();
    }

    private static function styles() { ?>
        <style>
        .chat-tools{display:flex;align-items:center;gap:10px}.availability-control{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}.availability-state{padding:6px 10px;border-radius:999px;font-size:11px;letter-spacing:.08em}.availability-state.online{color:#d6ffe4;background:#138a42}.availability-state.offline{color:#e1e7ef;background:#536174}.availability-buttons{display:flex;gap:4px;padding:3px;border-radius:10px;background:#17263d}.availability-buttons button{border:0;border-radius:7px;padding:7px 9px;background:transparent;color:#aebbd0;font-weight:700;cursor:pointer}.availability-buttons button.active{background:#00a9c7;color:#fff}@media(max-width:800px){.chat-tools{align-items:flex-start;flex-direction:column}.availability-control{justify-content:flex-start}}
        .chat-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}.chat-head .page-title{margin:0}.chat-head p{color:#91a0b8;font-size:13px}.refresh,.customer-head button,.reply button{border:0;border-radius:10px;background:#00a9c7;color:#fff;padding:10px 14px;font-weight:700;cursor:pointer}.chat-layout{display:grid;grid-template-columns:330px minmax(0,1fr);min-height:650px;border:1px solid rgba(255,255,255,.1);border-radius:16px;overflow:hidden;background:rgba(8,18,36,.65)}.thread-list{border-right:1px solid rgba(255,255,255,.1);max-height:720px;overflow:auto}.thread{display:flex;gap:10px;align-items:center;padding:13px;color:#fff;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.07)}.thread.active{background:rgba(0,230,255,.1)}.avatar{width:38px;height:38px;display:grid;place-items:center;border-radius:50%;background:#00a9c7;font-weight:800}.thread-copy{min-width:0;display:flex;flex-direction:column;flex:1}.thread-copy strong,.thread-copy small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.thread-copy small{color:#91a0b8;font-size:11px}.unread{background:#ff6a3d;border-radius:12px;min-width:23px;height:23px;display:grid;place-items:center;font-size:11px}.conversation{display:flex;min-width:0;flex-direction:column}.customer-head{display:flex;justify-content:space-between;align-items:center;padding:16px;border-bottom:1px solid rgba(255,255,255,.1)}.customer-head div{display:flex;flex-direction:column}.customer-head a{color:#8adff0;font-size:12px}.channel{display:inline-block;width:max-content;margin-top:6px;padding:3px 8px;border-radius:999px;font-size:10px;font-weight:800;background:rgba(0,230,255,.13);color:#64eaff}.channel.email{background:rgba(255,152,0,.14);color:#ffb74d}.messages{padding:18px;display:flex;flex:1;flex-direction:column;gap:10px;overflow:auto;max-height:560px}.bubble{max-width:78%;padding:11px 13px;border-radius:14px;background:#243450;align-self:flex-start}.bubble.admin{background:#007f98;align-self:flex-end}.bubble p{white-space:normal;overflow-wrap:anywhere}.bubble small{display:block;margin-top:5px;color:#c1cedd;font-size:10px}.chat-attachments{display:grid;gap:7px;margin-top:8px}.chat-attachment{display:flex;align-items:center;gap:8px;color:#fff;text-decoration:none;overflow:hidden}.chat-attachment.image{flex-direction:column;align-items:flex-start}.chat-attachment img{display:block;max-width:260px;max-height:220px;border-radius:9px;object-fit:contain;background:#fff}.chat-attachment span{max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px}.chat-attachment.file{padding:9px;border:1px solid rgba(255,255,255,.18);border-radius:9px}.chat-attachment.file i{font-size:22px}.reply{display:flex;gap:10px;padding:14px;border-top:1px solid rgba(255,255,255,.1);align-items:flex-end}.reply-compose{display:flex;flex:1;flex-direction:column;gap:7px}.reply textarea{width:100%;min-height:74px;resize:vertical;border-radius:10px;border:1px solid rgba(255,255,255,.15);background:#111d33;color:#fff;padding:11px}.attach-button{display:inline-flex;width:max-content;align-items:center;gap:6px;border:1px solid rgba(255,255,255,.18);border-radius:8px;padding:7px 10px;color:#d9e9f4;cursor:pointer}.attach-button input{display:none}.reply-compose>small{color:#91a0b8}.empty{padding:30px;color:#91a0b8;text-align:center}.toast{position:fixed;right:18px;bottom:18px;background:#101d32;padding:12px 16px;border:1px solid #00a9c7;border-radius:10px;opacity:0;pointer-events:none;transition:.2s}.toast.show{opacity:1}@media(max-width:800px){.chat-layout{grid-template-columns:1fr}.thread-list{max-height:235px;border-right:0;border-bottom:1px solid rgba(255,255,255,.1)}.conversation{min-height:520px}.messages{max-height:420px}.bubble{max-width:88%}.chat-attachment img{max-width:220px}.reply{align-items:stretch;flex-direction:column}.reply button{width:100%}.chat-head{align-items:flex-start}}
        </style>
    <?php }

    private static function scripts($csrf, $conversation_id, $initial_status) { ?>
        <script>
        (function(){
            var csrf=<?php echo wp_json_encode($csrf); ?>,id=<?php echo (int)$conversation_id; ?>,status=<?php echo wp_json_encode($initial_status); ?>;
            var toast=document.getElementById('toast');function flash(t){toast.textContent=t;toast.classList.add('show');setTimeout(function(){toast.classList.remove('show')},2200)}
            function post(url,data){var multipart=data instanceof FormData;if(multipart){data.append('csrf',csrf);if(id)data.append('conversation_id',id)}else{data.set('csrf',csrf);if(id)data.set('conversation_id',id)}var options={method:'POST',body:multipart?data:data.toString()};if(!multipart)options.headers={'Content-Type':'application/x-www-form-urlencoded'};return fetch(url,options).then(function(r){return r.json().then(function(result){if(!r.ok)throw new Error(result.message||'Hiba');return result})})}
            document.querySelectorAll('[data-availability]').forEach(function(button){button.addEventListener('click',function(){var data=new URLSearchParams(),clicked=this;data.set('mode',clicked.dataset.availability);document.querySelectorAll('[data-availability]').forEach(function(item){item.disabled=true});post('/admin/shop-chat/availability',data).then(function(){flash('Elérhetőség elmentve');setTimeout(function(){location.reload()},250)}).catch(function(){flash('Az elérhetőség nem menthető')}).finally(function(){document.querySelectorAll('[data-availability]').forEach(function(item){item.disabled=false})})})});
            if(!id)return;
            var read=new URLSearchParams();post('/admin/shop-chat/read',read).catch(function(){});
            var messages=document.getElementById('messages');if(messages)messages.scrollTop=messages.scrollHeight;
            var attachmentInput=document.getElementById('reply-attachments'),fileState=document.getElementById('reply-files');attachmentInput.addEventListener('change',function(){var files=Array.from(this.files||[]);fileState.textContent=files.length?files.map(function(file){return file.name}).join(', '):'Legfeljebb 3 fájl, fájlonként 5 MB.'});
            document.getElementById('reply-form').addEventListener('submit',function(e){e.preventDefault();var input=document.getElementById('reply-message'),button=e.currentTarget.querySelector('button'),files=Array.from(attachmentInput.files||[]);if(!input.value.trim()&&!files.length){flash('Írj üzenetet vagy válassz csatolmányt');return}if(files.length>3||files.some(function(file){return file.size>5242880})){flash('Legfeljebb 3 darab, egyenként 5 MB-os fájl küldhető');return}var data=new FormData();data.append('message',input.value);files.forEach(function(file){data.append('attachments[]',file,file.name)});button.disabled=true;post('/admin/shop-chat/reply',data).then(function(result){input.value='';attachmentInput.value='';flash(result.data&&result.data.sentByEmail?'Email elküldve':'Válasz elküldve');setTimeout(function(){location.reload()},350)}).catch(function(error){flash(error.message||'A küldés nem sikerült')}).finally(function(){button.disabled=false})});
            document.getElementById('status-button').addEventListener('click',function(){var button=this,next=status==='closed'?'open':'closed',data=new URLSearchParams();data.set('status',next);button.disabled=true;post('/admin/shop-chat/status',data).then(function(){status=next;button.dataset.status=next;button.textContent=next==='closed'?'Újranyitás':'Lezárás';flash(next==='closed'?'Beszélgetés lezárva':'Beszélgetés újranyitva')}).catch(function(){flash('Az állapot nem menthető')}).finally(function(){button.disabled=false})});
        })();
        </script>
    <?php }

    private static function require_valid_post() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') self::json_error('Érvénytelen kérés.', 405);
        $provided = sanitize_text_field($_POST['csrf'] ?? '');
        if (!$provided || !hash_equals(self::csrf_token(), $provided)) self::json_error('Lejárt munkamenet.', 403);
    }

    private static function csrf_token() {
        if (session_status() === PHP_SESSION_NONE) ppv_maybe_start_session();
        if (empty($_SESSION['ppv_shop_chat_csrf'])) $_SESSION['ppv_shop_chat_csrf'] = bin2hex(random_bytes(24));
        return $_SESSION['ppv_shop_chat_csrf'];
    }

    private static function json_success($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function json_error($message, $status) {
        status_header($status);
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
