<?php
if (!defined('ABSPATH')) exit;

/**
 * PPV_QR_Devices_Trait
 * User devices management functions for PPV_QR class
 * 
 * Contains:
 * - render_user_devices()
 * - detect_device_type()
 */
trait PPV_QR_Devices_Trait {

    public static function render_user_devices() {
        global $wpdb;

        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Get current handler's store_id (same logic as API's get_session_store_id)
        $store_id = 0;
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $store_id = intval($_SESSION['ppv_current_filiale_id']);
        } elseif (!empty($_SESSION['ppv_store_id'])) {
            $store_id = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_vendor_store_id'])) {
            $store_id = intval($_SESSION['ppv_vendor_store_id']);
        } elseif (!empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $user_id
            ));
        }

        // 🏪 Get parent store ID (devices are linked to parent store)
        $parent_id = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(parent_store_id, id) FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store_id
        ));

        // Debug log
        ppv_log("📱 [QR Devices] store_id={$store_id}, parent_id={$parent_id}, session=" . json_encode([
            'filiale' => $_SESSION['ppv_current_filiale_id'] ?? null,
            'store' => $_SESSION['ppv_store_id'] ?? null,
            'vendor' => $_SESSION['ppv_vendor_store_id'] ?? null
        ]));

        // Get registered devices for this store
        $devices = [];
        $max_devices = 2;
        if (class_exists('PPV_Device_Fingerprint')) {
            $max_devices = PPV_Device_Fingerprint::MAX_DEVICES_PER_USER;
            if ($parent_id) {
                $devices = PPV_Device_Fingerprint::get_user_devices($parent_id);
            }
        }

        $device_count = count($devices);
        $can_add_more = $device_count < $max_devices;
        ?>
        <div class="ppv-user-devices">
            <div class="ppv-devices-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h3><i class="ri-smartphone-line"></i> <?php echo self::t('devices_title', 'Registrierte Geräte'); ?></h3>
                    <p style="font-size: 13px; color: #999; margin: 5px 0 0 0;">
                        <?php echo self::t('devices_subtitle', 'Verwalten Sie die Geräte, die den Scanner verwenden dürfen.'); ?>
                    </p>
                </div>
                <div style="text-align: right;">
                    <span class="ppv-device-counter" style="font-size: 14px; color: <?php echo $can_add_more ? '#4caf50' : '#ff9800'; ?>;">
                        <strong><?php echo $device_count; ?></strong> / <?php echo $max_devices; ?> <?php echo self::t('devices', 'Geräte'); ?>
                    </span>
                </div>
            </div>

            <!-- Info Box -->
            <div style="background: rgba(33, 150, 243, 0.1); border: 1px solid rgba(33, 150, 243, 0.3); border-radius: 10px; padding: 15px; margin-bottom: 20px;">
                <p style="margin: 0; font-size: 13px; color: #2196f3;">
                    <i class="ri-information-line"></i>
                    <?php echo self::t('devices_info', 'Registrieren Sie Geräte über "Dieses Gerät registrieren". Bei erreichtem Limit (2 Geräte) können Sie weitere Geräte mit Admin-Genehmigung anfordern.'); ?>
                </p>
            </div>

            <!-- Current Device Registration -->
            <div id="ppv-current-device-box" style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1) 0%, rgba(0, 230, 118, 0.1) 100%); border: 1px solid rgba(76, 175, 80, 0.3); border-radius: 15px; padding: 20px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h4 style="margin: 0 0 8px 0; color: #fff;"><i class="ri-device-line"></i> <?php echo self::t('current_device', 'Aktuelles Gerät'); ?></h4>
                        <p id="ppv-device-status" style="margin: 0; font-size: 13px; color: #999;">
                            <?php echo self::t('checking_device', 'Gerätestatus wird überprüft...'); ?>
                        </p>
                    </div>
                    <div id="ppv-device-actions">
                        <button id="ppv-register-device-btn" class="ppv-btn neon" type="button" style="display: none;">
                            <i class="ri-add-line"></i> <?php echo self::t('register_this_device', 'Dieses Gerät registrieren'); ?>
                        </button>
                        <span id="ppv-device-registered-badge" style="display: none; background: #4caf50; color: white; padding: 8px 16px; border-radius: 8px; font-size: 13px;">
                            ✅ <?php echo self::t('device_registered', 'Gerät registriert'); ?>
                        </span>
                        <button id="ppv-request-add-btn" class="ppv-btn-outline" type="button" style="display: none;">
                            <i class="ri-mail-send-line"></i> <?php echo self::t('request_admin_approval', 'Admin-Genehmigung anfordern'); ?>
                        </button>
                        <!-- Button for registered users to request additional device slot -->
                        <button id="ppv-request-new-slot-btn" class="ppv-btn-outline" type="button" style="display: none; margin-left: 10px; color: #ff9800; border-color: #ff9800;">
                            <i class="ri-add-circle-line"></i> <?php echo self::t('request_new_device_slot', 'Weiteres Gerät anfordern'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Registered Devices List -->
            <?php if (empty($devices)): ?>
                <div style="text-align: center; padding: 40px; color: #999; background: rgba(255,255,255,0.03); border-radius: 12px;">
                    <i class="ri-smartphone-line" style="font-size: 48px; color: #444; margin-bottom: 15px; display: block;"></i>
                    <p style="margin: 0;"><?php echo self::t('no_devices_registered', 'Noch keine Geräte registriert.'); ?></p>
                    <p style="margin: 10px 0 0 0; font-size: 13px;"><?php echo self::t('register_first_device', 'Registrieren Sie Ihr erstes Gerät, um den Scanner zu verwenden.'); ?></p>
                </div>
            <?php else: ?>
                <div class="ppv-devices-list">
                    <?php foreach ($devices as $device): ?>
                        <?php
                        $registered_date = date('d.m.Y H:i', strtotime($device->registered_at));
                        $last_used = $device->last_used_at ? date('d.m.Y H:i', strtotime($device->last_used_at)) : '-';
                        $device_type = self::detect_device_type($device->user_agent);
                        $is_mobile_scanner = !empty($device->mobile_scanner) && $device->mobile_scanner == 1;
                        ?>
                        <div class="ppv-device-card glass-card" data-device-id="<?php echo $device->id; ?>" data-fingerprint="<?php echo esc_attr($device->fingerprint_hash); ?>" data-mobile-scanner="<?php echo $is_mobile_scanner ? '1' : '0'; ?>" style="padding: 15px; margin-bottom: 15px; border-left: 4px solid #4caf50; border-radius: 12px; position: relative;">
                            <!-- Current device indicator (will be shown by JS) -->
                            <div class="ppv-current-device-badge" style="display: none; position: absolute; top: -8px; right: 15px; background: linear-gradient(135deg, #4caf50, #00e676); color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; box-shadow: 0 2px 8px rgba(76,175,80,0.4);">
                                <i class="ri-check-line"></i> <?php echo self::t('current_device_badge', 'Dieses Gerät'); ?>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; flex-wrap: wrap;">
                                <div style="flex: 1; min-width: 200px;">
                                    <div style="font-weight: bold; font-size: 16px; margin-bottom: 5px; color: #fff; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                        <?php echo esc_html($device_type['icon']); ?> <?php echo esc_html($device->device_name ?: $device_type['name']); ?>
                                        <?php if ($is_mobile_scanner): ?>
                                        <span style="background: linear-gradient(135deg, #9c27b0, #673ab7); color: white; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: normal;">
                                            <i class="ri-map-pin-line"></i> Mobile Scanner
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 12px; color: #999; margin-bottom: 3px;">
                                        <i class="ri-time-line"></i> <?php echo self::t('registered_at', 'Registriert'); ?>: <?php echo $registered_date; ?>
                                    </div>
                                    <div style="font-size: 12px; color: #999; margin-bottom: 3px;">
                                        <i class="ri-history-line"></i> <?php echo self::t('last_used', 'Zuletzt verwendet'); ?>: <?php echo $last_used; ?>
                                    </div>
                                    <?php if ($device->ip_address): ?>
                                    <div style="font-size: 11px; color: #666;">
                                        <i class="ri-global-line"></i> IP: <?php echo esc_html($device->ip_address); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <!-- Mobile Scanner request/status button -->
                                    <?php if (!$is_mobile_scanner): ?>
                                    <button class="ppv-device-mobile-scanner-btn ppv-btn-outline" data-device-id="<?php echo $device->id; ?>" data-device-name="<?php echo esc_attr($device->device_name ?: $device_type['name']); ?>" style="padding: 8px 12px; font-size: 12px; color: #9c27b0; border-color: #9c27b0;">
                                        <i class="ri-map-pin-add-line"></i> Mobile Scanner
                                    </button>
                                    <?php endif; ?>
                                    <button class="ppv-device-update-btn ppv-btn-outline" data-device-id="<?php echo $device->id; ?>" data-device-name="<?php echo esc_attr($device->device_name ?: $device_type['name']); ?>" style="padding: 8px 12px; font-size: 12px; color: #2196f3; border-color: #2196f3;">
                                        <i class="ri-refresh-line"></i> <?php echo self::t('update_fingerprint', 'Fingerprint aktualisieren'); ?>
                                    </button>
                                    <button class="ppv-device-remove-btn ppv-btn-outline" data-device-id="<?php echo $device->id; ?>" data-device-name="<?php echo esc_attr($device->device_name ?: $device_type['name']); ?>" style="padding: 8px 12px; font-size: 12px; color: #f44336; border-color: #f44336;">
                                        <i class="ri-delete-bin-line"></i> <?php echo self::t('request_removal', 'Entfernung anfordern'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Pending Requests Info -->
            <div id="ppv-pending-requests" style="display: none; margin-top: 20px; background: rgba(255, 152, 0, 0.1); border: 1px solid rgba(255, 152, 0, 0.3); border-radius: 10px; padding: 15px;">
                <p style="margin: 0; font-size: 13px; color: #ff9800;">
                    <i class="ri-time-line"></i>
                    <?php echo self::t('pending_requests_info', 'Sie haben ausstehende Geräteanfragen. Der Admin wurde per E-Mail benachrichtigt.'); ?>
                </p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            // ============================================================
            // 📱 USER DEVICE MANAGEMENT
            // ============================================================

            let currentFingerprint = null;
            let currentDeviceInfo = null; // 📱 Készülék adatok FingerprintJS-ből
            let deviceCheckResult = null;

            // Load FingerprintJS if not loaded
            function loadFingerprintJS() {
                return new Promise((resolve) => {
                    if (window.FingerprintJS) {
                        resolve();
                        return;
                    }

                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/@fingerprintjs/fingerprintjs@4/dist/fp.min.js';
                    script.onload = resolve;
                    script.onerror = () => {
                        // Fallback: use basic fingerprint
                        console.warn('[Devices] FingerprintJS failed to load, using fallback');
                        resolve();
                    };
                    document.head.appendChild(script);
                });
            }

            // 📱 Készülék információk kinyerése a FingerprintJS komponensekből
            function extractDeviceInfo(components) {
                if (!components) return null;

                const info = {};

                // Platform és OS
                if (components.platform) info.platform = components.platform.value;

                // Képernyő felbontás
                if (components.screenResolution) info.screen = components.screenResolution.value.join('x');

                // Színmélység
                if (components.colorDepth) info.colorDepth = components.colorDepth.value;

                // Memória (GB)
                if (components.deviceMemory) info.memory = components.deviceMemory.value;

                // CPU magok
                if (components.hardwareConcurrency) info.cpuCores = components.hardwareConcurrency.value;

                // Érintőképernyő támogatás
                if (components.touchSupport) {
                    const ts = components.touchSupport.value;
                    info.touchSupport = {
                        maxTouchPoints: ts.maxTouchPoints,
                        touchEvent: ts.touchEvent,
                        touchStart: ts.touchStart
                    };
                }

                // Időzóna
                if (components.timezone) info.timezone = components.timezone.value;

                // Nyelv
                if (components.languages) {
                    const langs = components.languages.value;
                    info.languages = Array.isArray(langs) ? langs.slice(0, 3) : langs;
                }

                // Vendor (gyártó)
                if (components.vendor) info.vendor = components.vendor.value;

                // Canvas hash (egyedi rajzolási aláírás)
                if (components.canvas) info.canvasHash = components.canvas.value ? 'yes' : 'no';

                // WebGL renderer (grafikus chip neve)
                if (components.webglRenderer) info.webglRenderer = components.webglRenderer.value;

                // Audio hash
                if (components.audio) info.audioHash = components.audio.value ? 'yes' : 'no';

                // Timestamp mikor gyűjtöttük
                info.collectedAt = new Date().toISOString();

                // User Agent is (backup)
                info.userAgent = navigator.userAgent;

                return info;
            }

            // Get device fingerprint (must be at least 16 chars for PHP validation)
            async function getDeviceFingerprint() {
                try {
                    if (window.FingerprintJS) {
                        const fp = await FingerprintJS.load();
                        const result = await fp.get();

                        // 📱 Tároljuk a készülék infókat
                        currentDeviceInfo = extractDeviceInfo(result.components);
                        console.log('[Devices] 📱 Device info collected:', currentDeviceInfo);

                        return result.visitorId;
                    }

                    // Fallback fingerprint (must be at least 16 chars)
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    ctx.textBaseline = 'top';
                    ctx.font = '14px Arial';
                    ctx.fillText('fingerprint', 0, 0);
                    const data = canvas.toDataURL() + navigator.userAgent + screen.width + screen.height + navigator.language + (new Date()).getTimezoneOffset();
                    let hash1 = 0, hash2 = 0;
                    for (let i = 0; i < data.length; i++) {
                        hash1 = ((hash1 << 5) - hash1) + data.charCodeAt(i);
                        hash1 = hash1 & hash1;
                        hash2 = ((hash2 << 7) - hash2) + data.charCodeAt(i);
                        hash2 = hash2 & hash2;
                    }
                    return 'fp_' + Math.abs(hash1).toString(16).padStart(8, '0') + Math.abs(hash2).toString(16).padStart(8, '0');
                } catch (e) {
                    console.error('[Devices] Fingerprint error:', e);
                    return null;
                }
            }

            // Check current device status
            async function checkDeviceStatus() {
                await loadFingerprintJS();
                currentFingerprint = await getDeviceFingerprint();

                if (!currentFingerprint) {
                    $('#ppv-device-status').html('<span style="color: #ff9800;">⚠️ <?php echo esc_js(self::t('fingerprint_error', 'Geräte-Fingerprint konnte nicht erstellt werden')); ?></span>');
                    return;
                }

                try {
                    console.log('[Devices] 🔍 Checking with fingerprint:', currentFingerprint);
                    const response = await fetch('/wp-json/punktepass/v1/user-devices/check', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ fingerprint: currentFingerprint })
                    });
                    const data = await response.json();
                    deviceCheckResult = data;
                    console.log('[Devices] API response:', data);
                    if (data.debug) {
                        console.log('[Devices] 📊 DEBUG INFO:');
                        console.log('  - Current hash:', data.debug.current_hash);
                        console.log('  - Registered hashes:', data.debug.registered_hashes);
                        console.log('  - Store ID:', data.debug.store_id);
                    }

                    // Handle API errors (401, etc.)
                    if (data.success === false || typeof data.device_count === 'undefined') {
                        // Auth error or API error - allow registration as fallback
                        console.warn('[Devices] API error or not authenticated, showing register button');
                        $('#ppv-device-status').html('<span style="color: #2196f3;">📱 <?php echo esc_js(self::t('no_devices_yet', 'Noch keine Geräte registriert. Registrieren Sie dieses Gerät.')); ?></span>');
                        $('#ppv-register-device-btn').show();
                        $('#ppv-device-registered-badge').hide();
                        $('#ppv-request-add-btn').hide();
                        $('#ppv-request-new-slot-btn').hide();
                    } else if (data.is_registered) {
                        // Device is registered
                        $('#ppv-device-status').html('<span style="color: #4caf50;">✅ <?php echo esc_js(self::t('device_is_registered', 'Dieses Gerät ist registriert und kann den Scanner verwenden.')); ?></span>');
                        $('#ppv-device-registered-badge').show();
                        $('#ppv-register-device-btn').hide();
                        $('#ppv-request-add-btn').hide();

                        // Check if limit is reached - show button to request additional device slot
                        if (parseInt(data.device_count, 10) >= parseInt(data.max_devices, 10)) {
                            $('#ppv-request-new-slot-btn').show();
                        } else {
                            $('#ppv-request-new-slot-btn').hide();
                        }
                    } else if (parseInt(data.device_count, 10) === 0) {
                        // No devices yet - can register
                        $('#ppv-device-status').html('<span style="color: #2196f3;">📱 <?php echo esc_js(self::t('no_devices_yet', 'Noch keine Geräte registriert. Registrieren Sie dieses Gerät.')); ?></span>');
                        $('#ppv-register-device-btn').show();
                        $('#ppv-device-registered-badge').hide();
                        $('#ppv-request-add-btn').hide();
                        $('#ppv-request-new-slot-btn').hide();
                    } else if (parseInt(data.device_count, 10) < parseInt(data.max_devices, 10)) {
                        // Can add more devices
                        $('#ppv-device-status').html('<span style="color: #ff9800;">⚠️ <?php echo esc_js(self::t('device_not_registered', 'Dieses Gerät ist nicht registriert.')); ?></span>');
                        $('#ppv-register-device-btn').show();
                        $('#ppv-device-registered-badge').hide();
                        $('#ppv-request-add-btn').hide();
                        $('#ppv-request-new-slot-btn').hide();
                    } else {
                        // Limit reached AND device NOT registered
                        // Check if there are available slots (pre-approved by admin)
                        const availableSlots = parseInt(data.available_slots || 0, 10);
                        if (availableSlots > 0) {
                            // There are available slots - user can register
                            $('#ppv-device-status').html('<span style="color: #ff9800;">📋 <?php echo esc_js(self::t('slot_available', 'Ein genehmigter Geräteplatz ist verfügbar. Sie können dieses Gerät registrieren.')); ?></span>');
                            $('#ppv-register-device-btn').show();
                            $('#ppv-device-registered-badge').hide();
                            $('#ppv-request-add-btn').hide();
                            $('#ppv-request-new-slot-btn').hide();
                        } else {
                            // No available slots - need admin approval for THIS device
                            $('#ppv-device-status').html('<span style="color: #f44336;">🚫 <?php echo esc_js(self::t('device_limit_reached', 'Gerätelimit erreicht. Admin-Genehmigung erforderlich.')); ?></span>');
                            $('#ppv-register-device-btn').hide();
                            $('#ppv-device-registered-badge').hide();
                            $('#ppv-request-add-btn').show();
                            $('#ppv-request-new-slot-btn').hide();
                        }
                    }
                } catch (e) {
                    console.error('[Devices] Check error:', e);
                    $('#ppv-device-status').html('<span style="color: #f44336;">❌ <?php echo esc_js(self::t('check_error', 'Fehler bei der Geräteprüfung')); ?></span>');
                }
            }

            // Register current device
            $('#ppv-register-device-btn').on('click', async function() {
                const $btn = $(this);
                const deviceName = prompt('<?php echo esc_js(self::t('enter_device_name', 'Gerätename eingeben (z.B. iPhone Kasse, Samsung Tablet)')); ?>:', '<?php echo esc_js(self::t('default_device_name', 'Scanner-Gerät')); ?>');

                if (!deviceName) return;

                $btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i> <?php echo esc_js(self::t('registering', 'Wird registriert...')); ?>');

                try {
                    const response = await fetch('/wp-json/punktepass/v1/user-devices/register', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            fingerprint: currentFingerprint,
                            device_name: deviceName,
                            device_info: currentDeviceInfo // 📱 Készülék adatok
                        })
                    });
                    const data = await response.json();

                    if (data.success) {
                        alert('<?php echo esc_js(self::t('device_registered_success', 'Gerät erfolgreich registriert!')); ?>');
                        location.reload();
                    } else {
                        alert(data.message || '<?php echo esc_js(self::t('registration_error', 'Fehler bei der Registrierung')); ?>');
                        $btn.prop('disabled', false).html('<i class="ri-add-line"></i> <?php echo esc_js(self::t('register_this_device', 'Dieses Gerät registrieren')); ?>');
                    }
                } catch (e) {
                    alert('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>');
                    $btn.prop('disabled', false).html('<i class="ri-add-line"></i> <?php echo esc_js(self::t('register_this_device', 'Dieses Gerät registrieren')); ?>');
                }
            });

            // Request admin approval for new device
            $('#ppv-request-add-btn').on('click', async function() {
                const $btn = $(this);
                const deviceName = prompt('<?php echo esc_js(self::t('enter_device_name', 'Gerätename eingeben (z.B. iPhone Kasse, Samsung Tablet)')); ?>:', '<?php echo esc_js(self::t('new_device', 'Neues Gerät')); ?>');

                if (!deviceName) return;

                if (!confirm('<?php echo esc_js(self::t('confirm_request_add', 'Eine Anfrage für dieses Gerät wird an den Admin gesendet. Fortfahren?')); ?>')) {
                    return;
                }

                $btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i> <?php echo esc_js(self::t('sending_request', 'Anfrage wird gesendet...')); ?>');

                try {
                    const response = await fetch('/wp-json/punktepass/v1/user-devices/request-add', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            fingerprint: currentFingerprint,
                            device_name: deviceName,
                            device_info: currentDeviceInfo // 📱 Készülék adatok
                        })
                    });
                    const data = await response.json();

                    if (data.success) {
                        alert('<?php echo esc_js(self::t('request_sent', 'Anfrage erfolgreich gesendet! Der Admin wird per E-Mail benachrichtigt.')); ?>');
                        $('#ppv-pending-requests').show();
                        $btn.hide();
                    } else {
                        alert(data.message || '<?php echo esc_js(self::t('request_error', 'Fehler beim Senden der Anfrage')); ?>');
                        $btn.prop('disabled', false).html('<i class="ri-mail-send-line"></i> <?php echo esc_js(self::t('request_admin_approval', 'Admin-Genehmigung anfordern')); ?>');
                    }
                } catch (e) {
                    alert('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>');
                    $btn.prop('disabled', false).html('<i class="ri-mail-send-line"></i> <?php echo esc_js(self::t('request_admin_approval', 'Admin-Genehmigung anfordern')); ?>');
                }
            });

            // Request additional device slot (for already registered users at limit)
            $('#ppv-request-new-slot-btn').on('click', async function() {
                const $btn = $(this);
                const deviceName = prompt('<?php echo esc_js(self::t('enter_new_device_name', 'Geben Sie einen Namen für das neue Gerät ein, das Sie hinzufügen möchten:')); ?>:', '<?php echo esc_js(self::t('new_device', 'Neues Gerät')); ?>');

                if (!deviceName) return;

                if (!confirm('<?php echo esc_js(self::t('confirm_request_new_slot', 'Sie möchten ein weiteres Gerät hinzufügen. Der Admin wird per E-Mail benachrichtigt und kann die Anfrage genehmigen. Fortfahren?')); ?>')) {
                    return;
                }

                $btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i> <?php echo esc_js(self::t('sending_request', 'Anfrage wird gesendet...')); ?>');

                try {
                    const response = await fetch('/wp-json/punktepass/v1/user-devices/request-new-slot', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            device_name: deviceName,
                            request_type: 'new_slot'
                        })
                    });
                    const data = await response.json();

                    if (data.success) {
                        alert('<?php echo esc_js(self::t('new_slot_request_sent', 'Anfrage für weiteres Gerät erfolgreich gesendet! Der Admin wird per E-Mail benachrichtigt.')); ?>');
                        $('#ppv-pending-requests').show();
                        $btn.hide();
                    } else {
                        alert(data.message || '<?php echo esc_js(self::t('request_error', 'Fehler beim Senden der Anfrage')); ?>');
                        $btn.prop('disabled', false).html('<i class="ri-add-circle-line"></i> <?php echo esc_js(self::t('request_new_device_slot', 'Weiteres Gerät anfordern')); ?>');
                    }
                } catch (e) {
                    alert('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>');
                    $btn.prop('disabled', false).html('<i class="ri-add-circle-line"></i> <?php echo esc_js(self::t('request_new_device_slot', 'Weiteres Gerät anfordern')); ?>');
                }
            });

            // Request device removal
            $(document).on('click', '.ppv-device-remove-btn', async function() {
                const $btn = $(this);
                const deviceId = $btn.data('device-id');
                const deviceName = $btn.data('device-name');

                if (!confirm('<?php echo esc_js(self::t('confirm_request_remove', 'Eine Löschungsanfrage für dieses Gerät wird an den Admin gesendet. Fortfahren?')); ?>\n\n' + deviceName)) {
                    return;
                }

                $btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i>');

                try {
                    const response = await fetch('/wp-json/punktepass/v1/user-devices/request-remove', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ device_id: deviceId })
                    });
                    const data = await response.json();

                    if (data.success) {
                        alert('<?php echo esc_js(self::t('removal_request_sent', 'Löschungsanfrage gesendet! Der Admin wird per E-Mail benachrichtigt.')); ?>');
                        $('#ppv-pending-requests').show();
                        $btn.closest('.ppv-device-card').css('opacity', '0.5');
                        $btn.replaceWith('<span style="color: #ff9800; font-size: 12px;"><i class="ri-time-line"></i> <?php echo esc_js(self::t('pending_removal', 'Löschung ausstehend')); ?></span>');
                    } else {
                        alert(data.message || '<?php echo esc_js(self::t('request_error', 'Fehler beim Senden der Anfrage')); ?>');
                        $btn.prop('disabled', false).html('<i class="ri-delete-bin-line"></i> <?php echo esc_js(self::t('request_removal', 'Entfernung anfordern')); ?>');
                    }
                } catch (e) {
                    alert('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>');
                    $btn.prop('disabled', false).html('<i class="ri-delete-bin-line"></i> <?php echo esc_js(self::t('request_removal', 'Entfernung anfordern')); ?>');
                }
            });

            // Update device fingerprint
            $(document).on('click', '.ppv-device-update-btn', async function() {
                const $btn = $(this);
                const deviceId = $btn.data('device-id');
                const deviceName = $btn.data('device-name');

                if (!confirm('<?php echo esc_js(self::t('confirm_update_fingerprint', 'Fingerprint für dieses Gerät mit dem aktuellen Browser aktualisieren?')); ?>\n\n' + deviceName)) {
                    return;
                }

                if (!currentFingerprint) {
                    alert('<?php echo esc_js(self::t('fingerprint_error', 'Geräte-Fingerprint konnte nicht erstellt werden')); ?>');
                    return;
                }

                $btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i>');

                try {
                    const response = await fetch('/wp-json/punktepass/v1/user-devices/update-fingerprint', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            device_id: deviceId,
                            fingerprint: currentFingerprint,
                            device_info: currentDeviceInfo // 📱 Készülék adatok
                        })
                    });
                    const data = await response.json();

                    if (data.success) {
                        alert('<?php echo esc_js(self::t('fingerprint_updated', 'Fingerprint erfolgreich aktualisiert! Die Seite wird neu geladen.')); ?>');
                        location.reload();
                    } else {
                        alert(data.message || '<?php echo esc_js(self::t('update_error', 'Fehler beim Aktualisieren')); ?>');
                        $btn.prop('disabled', false).html('<i class="ri-refresh-line"></i> <?php echo esc_js(self::t('update_fingerprint', 'Fingerprint aktualisieren')); ?>');
                    }
                } catch (e) {
                    alert('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>');
                    $btn.prop('disabled', false).html('<i class="ri-refresh-line"></i> <?php echo esc_js(self::t('update_fingerprint', 'Fingerprint aktualisieren')); ?>');
                }
            });

            // ============================================================
            // 📱 MOBILE SCANNER MANAGEMENT (PER-DEVICE)
            // ============================================================

            // Highlight current device using device_id from API response
            function highlightCurrentDevice() {
                // Use device_id from API response (deviceCheckResult)
                if (!deviceCheckResult || !deviceCheckResult.device_id) {
                    console.log('[Devices] ⚠️ No device_id in API response, cannot highlight current device');
                    return;
                }

                const currentDeviceId = deviceCheckResult.device_id;
                console.log('[Devices] 🔍 Looking for current device with ID:', currentDeviceId);

                $('.ppv-device-card').each(function() {
                    const $card = $(this);
                    const cardDeviceId = parseInt($card.data('device-id'), 10);

                    if (cardDeviceId === currentDeviceId) {
                        console.log('[Devices] ✅ Found current device! ID:', currentDeviceId);
                        // Highlight the card with green border and glow
                        $card.css({
                            'border-left-color': '#00e676',
                            'border-left-width': '6px',
                            'box-shadow': '0 0 20px rgba(0, 230, 118, 0.4)',
                            'background': 'linear-gradient(135deg, rgba(0, 230, 118, 0.1), rgba(76, 175, 80, 0.05))'
                        });
                        // Show the current device badge
                        $card.find('.ppv-current-device-badge').show();
                    }
                });
            }

            // Check pending mobile scanner requests for each device
            async function checkMobileScannerPendingRequests() {
                try {
                    const response = await fetch('/wp-json/punktepass/v1/user-devices/mobile-scanner-status');
                    const data = await response.json();
                    console.log('[Mobile Scanner] Status for devices:', data);

                    // If there are pending requests for specific devices, update their buttons
                    if (data.pending_device_ids && Array.isArray(data.pending_device_ids)) {
                        data.pending_device_ids.forEach(deviceId => {
                            const $btn = $(`.ppv-device-mobile-scanner-btn[data-device-id="${deviceId}"]`);
                            if ($btn.length) {
                                $btn.prop('disabled', true)
                                    .css({ 'background': '#ff9800', 'color': 'white', 'border-color': '#ff9800' })
                                    .html('<i class="ri-time-line"></i> <?php echo esc_js(self::t('mobile_scanner_pending_short', 'Ausstehend')); ?>');
                            }
                        });
                    }
                } catch (e) {
                    console.error('[Mobile Scanner] Status check error:', e);
                }
            }

            // Request mobile scanner for specific device
            $(document).on('click', '.ppv-device-mobile-scanner-btn', async function() {
                const $btn = $(this);
                const deviceId = $btn.data('device-id');
                const deviceName = $btn.data('device-name');

                if (!confirm('<?php echo esc_js(self::t('confirm_request_device_mobile_scanner', 'Mobile Scanner für dieses Gerät anfordern? Mit Mobile Scanner können Sie ohne GPS-Standortprüfung scannen.')); ?>')) {
                    return;
                }

                $btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i>');

                try {
                    const response = await fetch('/wp-json/punktepass/v1/user-devices/request-mobile-scanner', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ device_id: deviceId })
                    });
                    const data = await response.json();

                    if (data.success) {
                        alert('<?php echo esc_js(self::t('mobile_scanner_request_sent', 'Anfrage gesendet! Der Admin wird per E-Mail benachrichtigt.')); ?>');
                        // Update button to show pending status
                        $btn.css({ 'background': '#ff9800', 'color': 'white', 'border-color': '#ff9800' })
                            .html('<i class="ri-time-line"></i> <?php echo esc_js(self::t('mobile_scanner_pending_short', 'Ausstehend')); ?>');
                    } else {
                        alert(data.message || '<?php echo esc_js(self::t('request_error', 'Fehler beim Senden der Anfrage')); ?>');
                        $btn.prop('disabled', false).html('<i class="ri-map-pin-add-line"></i> Mobile Scanner');
                    }
                } catch (e) {
                    alert('<?php echo esc_js(self::t('network_error', 'Netzwerkfehler')); ?>');
                    $btn.prop('disabled', false).html('<i class="ri-map-pin-add-line"></i> Mobile Scanner');
                }
            });

            // Initialize on page load
            checkDeviceStatus().then(() => {
                highlightCurrentDevice();
                checkMobileScannerPendingRequests();
            });
        });
        </script>
        <?php
    }

    /**
     * Detect device type from user agent
     */
    private static function detect_device_type($user_agent) {
        $user_agent = strtolower($user_agent ?? '');

        if (strpos($user_agent, 'iphone') !== false) {
            return ['icon' => '📱', 'name' => 'iPhone'];
        } elseif (strpos($user_agent, 'ipad') !== false) {
            return ['icon' => '📱', 'name' => 'iPad'];
        } elseif (strpos($user_agent, 'android') !== false) {
            if (strpos($user_agent, 'mobile') !== false) {
                return ['icon' => '📱', 'name' => 'Android Phone'];
            } else {
                return ['icon' => '📱', 'name' => 'Android Tablet'];
            }
        } elseif (strpos($user_agent, 'windows') !== false) {
            return ['icon' => '💻', 'name' => 'Windows PC'];
        } elseif (strpos($user_agent, 'macintosh') !== false || strpos($user_agent, 'mac os') !== false) {
            return ['icon' => '💻', 'name' => 'Mac'];
        } elseif (strpos($user_agent, 'linux') !== false) {
            return ['icon' => '💻', 'name' => 'Linux PC'];
        }

        return ['icon' => '📱', 'name' => 'Unbekanntes Gerät'];
    }

    // ============================================================
    // 🏪 AJAX: SWITCH FILIALE
    // ============================================================
}
