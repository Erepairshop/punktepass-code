<?php
if (!defined('ABSPATH')) exit;

/**
 * PPV_QR_Users_Trait
 * Scanner users management functions for PPV_QR class
 * 
 * Contains:
 * - render_scanner_users()
 */
trait PPV_QR_Users_Trait {

    public static function render_scanner_users() {
        global $wpdb;

        // Get current handler's store_id (BASE store, not filiale)
        $store_id = 0;
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            $store_id = intval($_SESSION['ppv_vendor_store_id']);
        } elseif (!empty($_SESSION['ppv_store_id'])) {
            $store_id = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $user_id
            ));
        }

        // 🏪 Get parent store ID to fetch all filialen
        $parent_id = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(parent_store_id, id) FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store_id
        ));

        // Get all filialen belonging to this parent (including parent itself)
        $filialen = $wpdb->get_results($wpdb->prepare("
            SELECT id, name, city, parent_store_id
            FROM {$wpdb->prefix}ppv_stores
            WHERE id=%d OR parent_store_id=%d
            ORDER BY CASE WHEN id=%d THEN 0 ELSE 1 END, name ASC
        ", $parent_id, $parent_id, $parent_id));

        // Get all scanner users for this handler (BASE store + all filialen)
        $scanners = [];
        if ($store_id) {
            $scanners = $wpdb->get_results($wpdb->prepare(
                "SELECT u.id, u.email, u.username, u.created_at, u.active, u.vendor_store_id,
                        s.name as store_name, s.city as store_city, s.parent_store_id
                 FROM {$wpdb->prefix}ppv_users u
                 LEFT JOIN {$wpdb->prefix}ppv_stores s ON u.vendor_store_id = s.id
                 WHERE u.user_type = 'scanner'
                 AND (u.vendor_store_id = %d OR s.parent_store_id = %d)
                 ORDER BY u.created_at DESC",
                $parent_id, $parent_id
            ));
        }

        ?>
        <div class="ppv-scanner-users">
            <div class="ppv-scanner-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3><i class="ri-team-line"></i> <?php echo self::t('scanner_users_title', 'Scanner Felhasználók'); ?></h3>
                <button id="ppv-new-scanner-btn" class="ppv-btn neon" type="button">
                    <i class="ri-add-line"></i> <?php echo self::t('add_scanner_user', 'Új Scanner Létrehozása'); ?>
                </button>
            </div>

            <?php if (empty($scanners)): ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <p>📭 <?php echo self::t('no_scanner_users', 'Még nincs scanner felhasználó létrehozva.'); ?></p>
                </div>
            <?php else: ?>
                <div class="scanner-users-list">
                    <?php foreach ($scanners as $scanner): ?>
                        <?php
                        $is_active = $scanner->active == 1; // PPV users: 1 = active, 0 = disabled
                        $created_date = date('Y-m-d H:i', strtotime($scanner->created_at));
                        ?>
                        <div class="scanner-user-card glass-card <?php echo $is_active ? 'active' : 'inactive'; ?>">
                            <div class="scanner-user-card-inner">
                                <div class="scanner-user-info">
                                    <div class="scanner-user-email">
                                        👤 <strong><?php echo esc_html($scanner->username ?: 'N/A'); ?></strong>
                                        <?php if (!empty($scanner->email)): ?>
                                            <span style="color: #999; font-size: 0.9em;"> (<?php echo esc_html($scanner->email); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="scanner-user-meta">
                                        <?php echo self::t('created_at', 'Létrehozva'); ?>: <?php echo $created_date; ?>
                                    </div>
                                    <div class="scanner-user-store">
                                        🏪 <?php
                                        echo esc_html($scanner->store_name ?: 'N/A');
                                        if (!empty($scanner->store_city)) {
                                            echo ' - ' . esc_html($scanner->store_city);
                                        }
                                        // Show if it's main location
                                        if ($scanner->vendor_store_id == $parent_id && (empty($scanner->parent_store_id) || $scanner->parent_store_id === null)) {
                                            echo ' (' . self::t('main_location', 'Hauptstandort') . ')';
                                        }
                                        ?>
                                    </div>
                                    <div class="scanner-user-status">
                                        <?php if ($is_active): ?>
                                            <span class="scanner-status-badge scanner-status-active">
                                                ✅ <?php echo self::t('status_active', 'Aktív'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="scanner-status-badge scanner-status-inactive">
                                                🚫 <?php echo self::t('status_disabled', 'Letiltva'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="scanner-user-actions">
                                    <!-- Change Filiale -->
                                    <button class="ppv-scanner-change-filiale ppv-btn-outline" data-user-id="<?php echo $scanner->id; ?>" data-email="<?php echo esc_attr($scanner->email); ?>" data-current-store="<?php echo $scanner->vendor_store_id; ?>">
                                        <i class="ri-store-2-line"></i> <?php echo self::t('change_filiale', 'Filiale ändern'); ?>
                                    </button>

                                    <!-- Password Reset -->
                                    <button class="ppv-scanner-reset-pw ppv-btn-outline" data-user-id="<?php echo $scanner->id; ?>" data-email="<?php echo esc_attr($scanner->email); ?>">
                                        <i class="ri-refresh-line"></i> <?php echo self::t('reset_password', 'Jelszó Reset'); ?>
                                    </button>

                                    <!-- Toggle Active/Disable -->
                                    <?php if ($is_active): ?>
                                        <button class="ppv-scanner-toggle ppv-btn-outline scanner-btn-danger" data-user-id="<?php echo $scanner->id; ?>" data-action="disable">
                                            🚫 <?php echo self::t('disable', 'Letiltás'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button class="ppv-scanner-toggle ppv-btn" data-user-id="<?php echo $scanner->id; ?>" data-action="enable">
                                            ✅ <?php echo self::t('enable', 'Engedélyezés'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Create Scanner Modal -->
            <div id="ppv-scanner-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
                <div style="background: #1a1a2e; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                    <h3 style="margin-top: 0; color: #fff;"><i class="ri-user-add-line"></i> <?php echo self::t('create_scanner_title', 'Új Scanner Létrehozása'); ?></h3>

                    <label style="color: #fff; font-size: 13px; display: block; margin-bottom: 5px;">
                        <?php echo self::t('scanner_login', 'E-mail vagy Benutzername'); ?> <span style="color: #ff5252;">*</span>
                    </label>
                    <input type="text" id="ppv-scanner-login" class="ppv-input" placeholder="<?php echo self::t('scanner_login_placeholder', 'scanner1 oder scanner@example.com'); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">

                    <label style="color: #fff; font-size: 13px; display: block; margin-bottom: 5px;">
                        <?php echo self::t('scanner_password', 'Jelszó'); ?> <span style="color: #ff5252;">*</span>
                    </label>
                    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                        <input type="text" id="ppv-scanner-password" class="ppv-input" placeholder="<?php echo self::t('enter_password', 'Adjon meg jelszót'); ?>" style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff;">
                        <button id="ppv-scanner-gen-pw" class="ppv-btn-outline" style="padding: 12px; white-space: nowrap;">
                            🎲 <?php echo self::t('generate', 'Generálás'); ?>
                        </button>
                    </div>

                    <label style="color: #fff; font-size: 13px; display: block; margin-bottom: 5px;">
                        <i class="ri-store-2-line"></i> <?php echo self::t('scanner_filiale', 'Filiale zuweisen'); ?> <span style="color: #ff5252;">*</span>
                    </label>
                    <select id="ppv-scanner-filiale" class="ppv-input" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">
                        <?php foreach ($filialen as $filiale): ?>
                            <option value="<?php echo $filiale->id; ?>">
                                <?php
                                echo esc_html($filiale->name);
                                if (!empty($filiale->city)) {
                                    echo ' - ' . esc_html($filiale->city);
                                }
                                // Show if it's main location
                                if ($filiale->id == $parent_id && (empty($filiale->parent_store_id) || $filiale->parent_store_id === null)) {
                                    echo ' (' . self::t('main_location', 'Hauptstandort') . ')';
                                }
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div id="ppv-scanner-error" style="display: none; color: #ff5252; font-size: 13px; margin-bottom: 10px; padding: 10px; background: rgba(255, 82, 82, 0.1); border-radius: 6px;"></div>
                    <div id="ppv-scanner-success" style="display: none; color: #4caf50; font-size: 13px; margin-bottom: 10px; padding: 10px; background: rgba(76, 175, 80, 0.1); border-radius: 6px;"></div>

                    <div style="display: flex; gap: 10px;">
                        <button id="ppv-scanner-create" class="ppv-btn" style="flex: 1; padding: 12px;">
                            ✅ <?php echo self::t('create', 'Létrehozás'); ?>
                        </button>
                        <button id="ppv-scanner-cancel" class="ppv-btn-outline" style="flex: 1; padding: 12px;">
                            ❌ <?php echo self::t('cancel', 'Mégse'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Change Filiale Modal -->
            <div id="ppv-change-filiale-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center;">
                <div style="background: #1a1a2e; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                    <h3 style="margin-top: 0; color: #fff;"><i class="ri-store-2-line"></i> <?php echo self::t('change_filiale_title', 'Filiale ändern'); ?></h3>

                    <p style="color: #999; font-size: 14px; margin-bottom: 15px;">
                        <strong style="color: #fff;">📧 <span id="ppv-change-filiale-email"></span></strong>
                    </p>

                    <label style="color: #fff; font-size: 13px; display: block; margin-bottom: 5px;">
                        <?php echo self::t('select_new_filiale', 'Neue Filiale auswählen'); ?> <span style="color: #ff5252;">*</span>
                    </label>
                    <select id="ppv-change-filiale-select" class="ppv-input" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f0f1e; color: #fff; margin-bottom: 15px;">
                        <?php foreach ($filialen as $filiale): ?>
                            <option value="<?php echo $filiale->id; ?>">
                                <?php
                                echo esc_html($filiale->name);
                                if (!empty($filiale->city)) {
                                    echo ' - ' . esc_html($filiale->city);
                                }
                                // Show if it's main location
                                if ($filiale->id == $parent_id && (empty($filiale->parent_store_id) || $filiale->parent_store_id === null)) {
                                    echo ' (' . self::t('main_location', 'Hauptstandort') . ')';
                                }
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="hidden" id="ppv-change-filiale-user-id" value="">

                    <div id="ppv-change-filiale-error" style="display: none; color: #ff5252; font-size: 13px; margin-bottom: 10px; padding: 10px; background: rgba(255, 82, 82, 0.1); border-radius: 6px;"></div>
                    <div id="ppv-change-filiale-success" style="display: none; color: #4caf50; font-size: 13px; margin-bottom: 10px; padding: 10px; background: rgba(76, 175, 80, 0.1); border-radius: 6px;"></div>

                    <div style="display: flex; gap: 10px;">
                        <button id="ppv-change-filiale-save" class="ppv-btn" style="flex: 1; padding: 12px;">
                            ✅ <?php echo self::t('save', 'Speichern'); ?>
                        </button>
                        <button id="ppv-change-filiale-cancel" class="ppv-btn-outline" style="flex: 1; padding: 12px;">
                            ❌ <?php echo self::t('cancel', 'Abbrechen'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            // ============================================================
            // 👤 SCANNER USER MANAGEMENT
            // ============================================================

            // Show create scanner modal
            $('#ppv-new-scanner-btn').on('click', function(){
                $('#ppv-scanner-modal').css('display', 'flex').hide().fadeIn(200);
                $('#ppv-scanner-login').val('').focus();
                $('#ppv-scanner-password').val('');
                $('#ppv-scanner-error').hide();
                $('#ppv-scanner-success').hide();
            });

            // Hide create scanner modal
            $('#ppv-scanner-cancel').on('click', function(){
                $('#ppv-scanner-modal').fadeOut(200);
            });

            // Generate random password
            $('#ppv-scanner-gen-pw').on('click', function(){
                const chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
                let pw = '';
                for(let i = 0; i < 10; i++) pw += chars.charAt(Math.floor(Math.random() * chars.length));
                $('#ppv-scanner-password').val(pw);
            });

            // Create scanner user
            $('#ppv-scanner-create').on('click', function(){
                const login = $('#ppv-scanner-login').val().trim();
                const password = $('#ppv-scanner-password').val().trim();
                const filialeId = $('#ppv-scanner-filiale').val();
                const $btn = $(this);
                const $error = $('#ppv-scanner-error');
                const $success = $('#ppv-scanner-success');

                if(!login || !password) {
                    $error.text('<?php echo esc_js(self::t('err_fill_fields', 'Bitte alle Felder ausfüllen')); ?>').show();
                    return;
                }

                $btn.prop('disabled', true).html('⏳ <?php echo esc_js(self::t('creating', 'Wird erstellt...')); ?>');
                $error.hide();
                $success.hide();

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppv_create_scanner_user',
                        login: login,
                        password: password,
                        filiale_id: filialeId,
                        nonce: '<?php echo wp_create_nonce('ppv_scanner_nonce'); ?>'
                    },
                    success: function(response){
                        if(response.success){
                            $success.text('<?php echo esc_js(self::t('scanner_created', 'Scanner erfolgreich erstellt!')); ?>').show();
                            setTimeout(function(){ location.reload(); }, 1500);
                        } else {
                            $error.text(response.data?.message || '<?php echo esc_js(self::t('create_error', 'Fehler beim Erstellen')); ?>').show();
                            $btn.prop('disabled', false).html('✅ <?php echo esc_js(self::t('create', 'Létrehozás')); ?>');
                        }
                    },
                    error: function(){
                        $error.text('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>').show();
                        $btn.prop('disabled', false).html('✅ <?php echo esc_js(self::t('create', 'Létrehozás')); ?>');
                    }
                });
            });

            // Toggle scanner active/disabled
            $(document).on('click', '.ppv-scanner-toggle', function(){
                const userId = $(this).data('user-id');
                const action = $(this).data('action');
                const $btn = $(this);

                if(!confirm(action === 'disable'
                    ? '<?php echo esc_js(self::t('confirm_disable', 'Scanner wirklich deaktivieren?')); ?>'
                    : '<?php echo esc_js(self::t('confirm_enable', 'Scanner aktivieren?')); ?>'
                )) return;

                $btn.prop('disabled', true);

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppv_toggle_scanner_status',
                        user_id: userId,
                        toggle_action: action,
                        nonce: '<?php echo wp_create_nonce('ppv_scanner_nonce'); ?>'
                    },
                    success: function(response){
                        if(response.success){
                            location.reload();
                        } else {
                            alert(response.data?.message || '<?php echo esc_js(self::t('toggle_error', 'Fehler')); ?>');
                            $btn.prop('disabled', false);
                        }
                    },
                    error: function(){
                        alert('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>');
                        $btn.prop('disabled', false);
                    }
                });
            });

            // Reset scanner password
            $(document).on('click', '.ppv-scanner-reset-pw', function(){
                const userId = $(this).data('user-id');
                const email = $(this).data('email');
                const $btn = $(this);

                const newPassword = prompt('<?php echo esc_js(self::t('enter_new_password', 'Neues Passwort eingeben für')); ?> ' + email + ':');
                if(!newPassword || newPassword.length < 6) {
                    if(newPassword !== null) alert('<?php echo esc_js(self::t('password_min_length', 'Passwort muss mindestens 6 Zeichen haben')); ?>');
                    return;
                }

                $btn.prop('disabled', true);

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppv_reset_scanner_password',
                        user_id: userId,
                        new_password: newPassword,
                        nonce: '<?php echo wp_create_nonce('ppv_scanner_nonce'); ?>'
                    },
                    success: function(response){
                        if(response.success){
                            alert('<?php echo esc_js(self::t('password_reset_success', 'Passwort erfolgreich geändert!')); ?>');
                        } else {
                            alert(response.data?.message || '<?php echo esc_js(self::t('reset_error', 'Fehler beim Zurücksetzen')); ?>');
                        }
                        $btn.prop('disabled', false);
                    },
                    error: function(){
                        alert('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>');
                        $btn.prop('disabled', false);
                    }
                });
            });

            // Show change filiale modal
            $(document).on('click', '.ppv-scanner-change-filiale', function(){
                const userId = $(this).data('user-id');
                const email = $(this).data('email');
                const currentStore = $(this).data('current-store');

                $('#ppv-change-filiale-user-id').val(userId);
                $('#ppv-change-filiale-email').text(email);
                $('#ppv-change-filiale-select').val(currentStore);
                $('#ppv-change-filiale-error').hide();
                $('#ppv-change-filiale-success').hide();
                $('#ppv-change-filiale-modal').css('display', 'flex').hide().fadeIn(200);
            });

            // Hide change filiale modal
            $('#ppv-change-filiale-cancel').on('click', function(){
                $('#ppv-change-filiale-modal').fadeOut(200);
            });

            // Save filiale change
            $('#ppv-change-filiale-save').on('click', function(){
                const userId = $('#ppv-change-filiale-user-id').val();
                const newFilialeId = $('#ppv-change-filiale-select').val();
                const $btn = $(this);
                const $error = $('#ppv-change-filiale-error');
                const $success = $('#ppv-change-filiale-success');

                $btn.prop('disabled', true).html('⏳ <?php echo esc_js(self::t('saving', 'Speichern...')); ?>');
                $error.hide();
                $success.hide();

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppv_update_scanner_filiale',
                        user_id: userId,
                        new_filiale_id: newFilialeId,
                        nonce: '<?php echo wp_create_nonce('ppv_scanner_nonce'); ?>'
                    },
                    success: function(response){
                        if(response.success){
                            $success.text('<?php echo esc_js(self::t('filiale_changed', 'Filiale erfolgreich geändert!')); ?>').show();
                            setTimeout(function(){ location.reload(); }, 1500);
                        } else {
                            $error.text(response.data?.message || '<?php echo esc_js(self::t('change_error', 'Fehler beim Ändern')); ?>').show();
                            $btn.prop('disabled', false).html('✅ <?php echo esc_js(self::t('save', 'Speichern')); ?>');
                        }
                    },
                    error: function(){
                        $error.text('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>').show();
                        $btn.prop('disabled', false).html('✅ <?php echo esc_js(self::t('save', 'Speichern')); ?>');
                    }
                });
            });

            // Close modals on outside click
            $('#ppv-scanner-modal, #ppv-change-filiale-modal').on('click', function(e){
                if(e.target === this) $(this).fadeOut(200);
            });
        });
        </script>
        <?php
    }

    // ============================================================
    // 📱 RENDER USER DEVICES MANAGEMENT
    // ============================================================
}
