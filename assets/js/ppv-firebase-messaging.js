/**
 * PunktePass Firebase Cloud Messaging for Web
 * Uses Firebase JS SDK to get FCM tokens that work with FCM V1 API
 */

// TEMP: prove file is loaded — visible red chip in top-left, no deps
try {
    var _earlyChip = function(){
        var d = document.createElement('div');
        d.id = 'ppv-load-mark';
        d.style.cssText = 'position:fixed;top:5px;left:5px;background:#f00;color:#fff;font:bold 11px monospace;padding:3px 6px;z-index:99999;border-radius:3px;';
        d.textContent = '[fcm-js loaded ' + new Date().toLocaleTimeString() + ']';
        d.onclick = function(){ d.remove(); };
        if (document.body) document.body.appendChild(d);
        else document.addEventListener('DOMContentLoaded', function(){ document.body.appendChild(d); });
    };
    _earlyChip();
} catch(e) {}

(function() {
    'use strict';

    // Firebase configuration
    const firebaseConfig = {
        apiKey: "AIzaSyBB4-sQb-ZlMEDj4LVGYSenB8b8R_mUuOI",
        authDomain: "punktepass.firebaseapp.com",
        projectId: "punktepass",
        storageBucket: "punktepass.firebasestorage.app",
        messagingSenderId: "373165045072",
        appId: "1:373165045072:web:1ef83f576e6fc222a7a855"
    };

    // VAPID key from Firebase Console (Web Push certificates)
    const vapidKey = 'BCCTa3Fuxw0ZHzNsUf_pkuYsajMCwp69kCSxvV6x9lpYNDkz4MkRM4Kezp8s48qyxXo5GVu8TBcIs3Ih42Vci1Y';

    let messaging = null;
    let initialized = false;
    let serviceWorkerRegistration = null;

    function getCurrentUserId() {
        return window.ppvUserId ||
               window.ppv_user_id ||
               (window.ppv_bridge_user && window.ppv_bridge_user.id) ||
               (window.ppvPushConfig && window.ppvPushConfig.userId) ||
               null;
    }

    function isPushDismissedRecently() {
        const dismissedAt = parseInt(localStorage.getItem('ppv_push_dismissed') || '0', 10);
        if (!dismissedAt) return false;
        return (Date.now() - dismissedAt) < (24 * 60 * 60 * 1000);
    }

    /**
     * Initialize Firebase Messaging
     */
    async function initFirebase() {
        if (initialized) return true;

        try {
            // Check if Firebase is loaded
            if (typeof firebase === 'undefined') {
                console.log('[PPV FCM] Firebase SDK not loaded');
                return false;
            }

            // Initialize Firebase app if not already done
            if (!firebase.apps.length) {
                firebase.initializeApp(firebaseConfig);
            }

            // Get messaging instance
            messaging = firebase.messaging();
            initialized = true;
            console.log('[PPV FCM] Firebase initialized');
            return true;
        } catch (error) {
            console.error('[PPV FCM] Init error:', error);
            return false;
        }
    }

    /**
     * Request permission and get FCM token
     */
    async function getToken() {
        if (!initialized && !await initFirebase()) {
            return null;
        }

        try {
            const swRegistration = await getMessagingServiceWorkerRegistration();
            if (!swRegistration) {
                console.log('[PPV FCM] Messaging service worker not available');
                return null;
            }

            // Request notification permission
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                console.log('[PPV FCM] Notification permission denied');
                return null;
            }

            // Get FCM token
            const token = await messaging.getToken({
                vapidKey: vapidKey,
                serviceWorkerRegistration: swRegistration
            });
            console.log('[PPV FCM] Token received:', token.substring(0, 20) + '...');
            return token;
        } catch (error) {
            console.error('[PPV FCM] Token error:', error);
            return null;
        }
    }

    /**
     * Register the dedicated Firebase messaging service worker
     */
    async function getMessagingServiceWorkerRegistration() {
        if (serviceWorkerRegistration) {
            return serviceWorkerRegistration;
        }

        if (!('serviceWorker' in navigator)) {
            return null;
        }

        try {
            serviceWorkerRegistration = await navigator.serviceWorker.register('/firebase-messaging-sw.js', {
                scope: '/firebase-messaging/'
            });
            console.log('[PPV FCM] Messaging SW registered:', serviceWorkerRegistration.scope);
            return serviceWorkerRegistration;
        } catch (error) {
            console.error('[PPV FCM] Messaging SW registration failed:', error);
            return null;
        }
    }

    /**
     * Register FCM token with backend
     */
    async function registerToken() {
        const userId = getCurrentUserId();
        if (!userId) {
            console.log('[PPV FCM] No user ID, skipping registration');
            return false;
        }

        const token = await getToken();
        if (!token) {
            return false;
        }

        try {
            const response = await fetch('/wp-json/punktepass/v1/push/register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    token: token,
                    platform: 'web',
                    user_id: userId,
                    language: window.ppvLang || document.documentElement.lang || 'de',
                    device_name: getDeviceName()
                })
            });

            const result = await response.json();
            if (result.success) {
                console.log('[PPV FCM] Token registered successfully');
                localStorage.setItem('ppv_fcm_token', token);
                localStorage.setItem('ppv_fcm_registered', Date.now().toString());
                return true;
            } else {
                console.error('[PPV FCM] Registration failed:', result.message);
                return false;
            }
        } catch (error) {
            console.error('[PPV FCM] Registration error:', error);
            return false;
        }
    }

    /**
     * Get device name for identification
     */
    function getDeviceName() {
        const ua = navigator.userAgent;
        if (ua.includes('Chrome')) return 'Chrome Browser';
        if (ua.includes('Firefox')) return 'Firefox Browser';
        if (ua.includes('Safari')) return 'Safari Browser';
        if (ua.includes('Edge')) return 'Edge Browser';
        return 'Web Browser';
    }

    /**
     * Handle foreground messages
     */
    function setupMessageHandler() {
        if (!messaging) return;

        messaging.onMessage((payload) => {
            console.log('[PPV FCM] Message received:', payload);

            // Show notification manually for foreground
            if (Notification.permission === 'granted') {
                const title = payload.notification?.title || 'PunktePass';
                const options = {
                    body: payload.notification?.body || '',
                    icon: '/wp-content/plugins/punktepass/assets/img/pwa-icon-192.png',
                    badge: '/wp-content/plugins/punktepass/assets/img/pwa-icon-192.png',
                    data: payload.data
                };
                new Notification(title, options);
            }

            // Dispatch event for app to handle
            window.dispatchEvent(new CustomEvent('ppv-push-received', { detail: payload }));
        });
    }

    // Export functions
    window.PPVFirebaseMessaging = {
        init: initFirebase,
        getToken: getToken,
        register: registerToken,
        showOptIn: showPushOptIn,
        needsPermission: needsPushPermission
    };

    /**
     * Check if push permission is needed
     */
    // Bump this when we want all "Spater"/dismissed users to see the banner again.
    // 2026-04-27: bump 1->2 to re-prompt all unregistered users (TWA POST_NOTIFICATIONS rollout).
    var PUSH_PROMPT_VERSION = 2;
    function needsPushPermission() {
        if (!('Notification' in window)) return false;
        if (Notification.permission === 'granted') return false;
        if (Notification.permission === 'denied') return false;
        var seenVersion = parseInt(localStorage.getItem('ppv_push_optin_version') || '0', 10);
        if (seenVersion < PUSH_PROMPT_VERSION) return true; // ignore dismiss flag, re-prompt
        if (isPushDismissedRecently()) return false;
        return true;
    }

    /**
     * Show push opt-in banner
     */
    function showPushOptIn() {
        // Don't show if not needed or already shown
        if (!needsPushPermission()) return;
        if (document.getElementById('ppv-push-optin')) return;

        const lang = window.ppvLang || 'de';
        const texts = {
            de: { title: '🔔 Push-Benachrichtigungen', desc: 'Erhalten Sie Benachrichtigungen über neue Angebote und Punkte', btn: 'Aktivieren', dismiss: 'Später' },
            hu: { title: '🔔 Push értesítések', desc: 'Kapjon értesítéseket új ajánlatokról és pontokról', btn: 'Engedélyezés', dismiss: 'Később' },
            ro: { title: '🔔 Notificări Push', desc: 'Primiți notificări despre oferte și puncte noi', btn: 'Activare', dismiss: 'Mai târziu' },
            en: { title: '🔔 Push Notifications', desc: 'Receive notifications about new offers and points', btn: 'Enable', dismiss: 'Later' }
        };
        const t = texts[lang] || texts.de;

        const banner = document.createElement('div');
        banner.id = 'ppv-push-optin';
        banner.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;padding:15px 20px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:9999;display:flex;align-items:center;gap:15px;max-width:90%;width:400px;font-family:system-ui,sans-serif;';

        banner.innerHTML = `
            <div style="flex:1;">
                <div style="font-weight:600;margin-bottom:4px;">${t.title}</div>
                <div style="font-size:13px;opacity:0.9;">${t.desc}</div>
            </div>
            <div style="display:flex;gap:8px;flex-shrink:0;">
                <button id="ppv-push-enable" style="background:white;color:#6366f1;border:none;padding:8px 16px;border-radius:8px;font-weight:600;cursor:pointer;">${t.btn}</button>
                <button id="ppv-push-dismiss" style="background:transparent;color:white;border:1px solid rgba(255,255,255,0.5);padding:8px 12px;border-radius:8px;cursor:pointer;opacity:0.8;">✕</button>
            </div>
        `;

        document.body.appendChild(banner);

        // Enable button - triggers permission request
        document.getElementById('ppv-push-enable').addEventListener('click', async function() {
            this.textContent = '...';
            this.disabled = true;
            const success = await registerToken();
            if (success) {
                banner.innerHTML = '<div style="text-align:center;padding:10px;">✅ Push aktiviert!</div>';
                setTimeout(() => banner.remove(), 2000);
            } else {
                banner.innerHTML = '<div style="text-align:center;padding:10px;">❌ Konnte nicht aktiviert werden</div>';
                setTimeout(() => banner.remove(), 3000);
            }
        });

        // Dismiss button
        document.getElementById('ppv-push-dismiss').addEventListener('click', function() {
            localStorage.setItem('ppv_push_dismissed', Date.now().toString());
            localStorage.setItem('ppv_push_optin_version', String(PUSH_PROMPT_VERSION));
            banner.remove();
        });
    }

    // Diagnostic chip (TEMP) — visible on screen so user can debug TWA push state
    function showDiagChip(msg) {
        try {
            var el = document.getElementById('ppv-push-diag');
            if (!el) {
                el = document.createElement('div');
                el.id = 'ppv-push-diag';
                el.style.cssText = 'position:fixed;top:50px;left:5px;background:rgba(0,0,0,0.85);color:#0f0;font:bold 10px monospace;padding:4px 6px;z-index:99998;border-radius:3px;max-width:90vw;';
                document.body.appendChild(el);
                el.addEventListener('click', function(){ el.remove(); });
            }
            el.textContent = '[push] ' + msg;
        } catch(e) {}
    }

    // Auto-initialize when user is logged in
    document.addEventListener('DOMContentLoaded', async function() {
        showDiagChip('DOM ready uid=' + getCurrentUserId() + ' fb=' + (typeof firebase));
        // Only init if user is logged in and Firebase SDK is available
        if (getCurrentUserId() && typeof firebase !== 'undefined') {
            console.log('[PPV FCM] Auto-initializing for logged-in user');

            // Wait a bit for page to fully load
            setTimeout(async () => {
                showDiagChip('init... perm=' + Notification.permission);
                if (await initFirebase()) {
                    setupMessageHandler();

                    await getMessagingServiceWorkerRegistration();
                    showDiagChip('SW ok perm=' + Notification.permission);

                    // Check if already granted - then just refresh token
                    if (Notification.permission === 'granted') {
                        const lastRegistered = localStorage.getItem('ppv_fcm_registered');
                        const oneDay = 24 * 60 * 60 * 1000;
                        if (!lastRegistered || (Date.now() - parseInt(lastRegistered)) > oneDay) {
                            showDiagChip('granted, registerToken...');
                            const ok = await registerToken();
                            showDiagChip('register=' + (ok ? 'OK' : 'FAIL'));
                        } else {
                            showDiagChip('granted, recently registered');
                        }
                    } else if (Notification.permission === 'denied') {
                        showDiagChip('DENIED - Android Settings -> App -> Notifications -> Allow');
                    } else {
                        // Show opt-in banner for users who haven't decided yet
                        showDiagChip('default - banner shown');
                        showPushOptIn();
                    }
                } else {
                    showDiagChip('initFirebase FAILED');
                }
            }, 2000);
        } else {
            showDiagChip('skip: no userId or firebase undefined');
        }
    });

})();
