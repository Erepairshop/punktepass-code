/**
 * PunktePass Firebase Cloud Messaging for Web
 * Uses Firebase JS SDK to get FCM tokens that work with FCM V1 API
 */

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
            // Request notification permission
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                console.log('[PPV FCM] Notification permission denied');
                return null;
            }

            // Get FCM token
            const token = await messaging.getToken({ vapidKey: vapidKey });
            console.log('[PPV FCM] Token received:', token.substring(0, 20) + '...');
            return token;
        } catch (error) {
            console.error('[PPV FCM] Token error:', error);
            return null;
        }
    }

    /**
     * Register FCM token with backend
     */
    async function registerToken() {
        const userId = window.ppvUserId || window.ppv_user_id;
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
        register: registerToken
    };

    // Auto-initialize when user is logged in
    document.addEventListener('DOMContentLoaded', async function() {
        // Only init if user is logged in and Firebase SDK is available
        if ((window.ppvUserId || window.ppv_user_id) && typeof firebase !== 'undefined') {
            console.log('[PPV FCM] Auto-initializing for logged-in user');

            // Wait a bit for page to fully load
            setTimeout(async () => {
                if (await initFirebase()) {
                    setupMessageHandler();
                    // Auto-register if not already registered
                    const lastRegistered = localStorage.getItem('ppv_fcm_registered');
                    const oneDay = 24 * 60 * 60 * 1000;
                    if (!lastRegistered || (Date.now() - parseInt(lastRegistered)) > oneDay) {
                        registerToken();
                    }
                }
            }, 2000);
        }
    });

})();
