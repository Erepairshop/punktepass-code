/**
 * PunktePass Push Notification Bridge
 * Handles communication between native apps (iOS/Android) and the web backend
 *
 * iOS: Listens for CustomEvents dispatched by PushNotifications.swift
 * Android: Listens for events from Capacitor/native bridge
 * Web: Handles Web Push API subscriptions
 *
 * @version 1.0.0
 */

(function() {
    'use strict';

    // Prevent multiple initializations
    if (window.PPVPushBridge) {
        console.log('[PPV Push] Bridge already initialized');
        return;
    }

    const PPVPushBridge = {
        initialized: false,
        platform: null,
        token: null,
        permissionState: null,

        /**
         * Initialize the push bridge
         */
        init: function() {
            if (this.initialized) return;

            this.platform = this.detectPlatform();
            console.log('[PPV Push] Platform detected:', this.platform);

            // Set up event listeners based on platform
            this.setupEventListeners();

            // Request permission state on init
            this.checkPermissionState();

            this.initialized = true;
            console.log('[PPV Push] Bridge initialized');
        },

        /**
         * Detect the current platform
         */
        detectPlatform: function() {
            // iOS native app (WKWebView)
            if (window.webkit && window.webkit.messageHandlers) {
                return 'ios';
            }

            // Android native app
            if (window.Android || (window.Capacitor && window.Capacitor.isNativePlatform())) {
                return 'android';
            }

            // Check user agent for additional context
            const ua = navigator.userAgent.toLowerCase();
            if (ua.includes('punktepass') && ua.includes('iphone')) {
                return 'ios';
            }
            if (ua.includes('punktepass') && ua.includes('android')) {
                return 'android';
            }

            // PWA or regular browser
            return 'web';
        },

        /**
         * Set up platform-specific event listeners
         */
        setupEventListeners: function() {
            const self = this;

            // iOS native events (from PushNotifications.swift)
            window.addEventListener('push-token', function(e) {
                console.log('[PPV Push] Received push-token event:', e.detail);
                if (e.detail && e.detail !== 'ERROR GET TOKEN') {
                    self.token = e.detail;
                    self.registerToken(e.detail);
                }
            });

            window.addEventListener('push-permission-request', function(e) {
                console.log('[PPV Push] Permission result:', e.detail);
                self.permissionState = e.detail;
                if (e.detail === 'granted') {
                    // Request token after permission granted
                    self.requestToken();
                }
            });

            window.addEventListener('push-permission-state', function(e) {
                console.log('[PPV Push] Permission state:', e.detail);
                self.permissionState = e.detail;
            });

            window.addEventListener('push-notification', function(e) {
                console.log('[PPV Push] Notification received:', e.detail);
                self.handleNotification(e.detail);
            });

            window.addEventListener('push-notification-click', function(e) {
                console.log('[PPV Push] Notification clicked:', e.detail);
                self.handleNotificationClick(e.detail);
            });

            // Handle app resume (re-check token)
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible' && self.platform !== 'web') {
                    // Re-verify token is still valid
                    setTimeout(() => self.requestToken(), 1000);
                }
            });
        },

        /**
         * Check current permission state
         */
        checkPermissionState: function() {
            if (this.platform === 'ios') {
                // iOS: Call native function
                if (window.webkit && window.webkit.messageHandlers && window.webkit.messageHandlers.push) {
                    try {
                        window.webkit.messageHandlers.push.postMessage({ action: 'getState' });
                    } catch (e) {
                        console.log('[PPV Push] iOS bridge not available for state check');
                    }
                }
            } else if (this.platform === 'web') {
                // Web: Check Notification API
                if ('Notification' in window) {
                    this.permissionState = Notification.permission;
                }
            }
        },

        /**
         * Request push notification permission
         */
        requestPermission: function() {
            const self = this;

            if (this.platform === 'ios') {
                // iOS: Call native function via message handler
                if (window.webkit && window.webkit.messageHandlers && window.webkit.messageHandlers.push) {
                    try {
                        window.webkit.messageHandlers.push.postMessage({ action: 'requestPermission' });
                        return Promise.resolve(true);
                    } catch (e) {
                        console.error('[PPV Push] iOS permission request failed:', e);
                        return Promise.reject(e);
                    }
                }
            } else if (this.platform === 'web') {
                // Web: Use Notification API
                if ('Notification' in window) {
                    return Notification.requestPermission().then(function(result) {
                        self.permissionState = result;
                        if (result === 'granted') {
                            return self.subscribeWebPush();
                        }
                        return result;
                    });
                }
            }

            return Promise.resolve(false);
        },

        /**
         * Request push token from native app
         */
        requestToken: function() {
            if (this.platform === 'ios') {
                if (window.webkit && window.webkit.messageHandlers && window.webkit.messageHandlers.push) {
                    try {
                        window.webkit.messageHandlers.push.postMessage({ action: 'getToken' });
                    } catch (e) {
                        console.log('[PPV Push] iOS token request not available');
                    }
                }
            }
        },

        /**
         * Register token with backend
         */
        registerToken: function(token) {
            const self = this;

            if (!token || token === 'ERROR GET TOKEN') {
                console.error('[PPV Push] Invalid token');
                return;
            }

            // Get user info from window object (set by PHP)
            const userId = window.ppvUserId || window.ppv_user_id || null;
            const storeId = window.ppvStoreId || window.ppv_store_id || null;
            const language = window.ppvLang || document.documentElement.lang || 'de';

            if (!userId) {
                console.log('[PPV Push] No user ID available, skipping registration');
                return;
            }

            const data = {
                token: token,
                platform: this.platform,
                user_id: userId,
                store_id: storeId,
                language: language,
                device_name: this.getDeviceName()
            };

            console.log('[PPV Push] Registering token:', data);

            fetch('/wp-json/punktepass/v1/push/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
                credentials: 'include'
            })
            .then(function(response) { return response.json(); })
            .then(function(result) {
                if (result.success) {
                    console.log('[PPV Push] Token registered successfully');
                    self.token = token;
                    // Store locally for reference
                    try {
                        localStorage.setItem('ppv_push_token', token);
                        localStorage.setItem('ppv_push_registered', Date.now().toString());
                    } catch (e) {}
                } else {
                    console.error('[PPV Push] Registration failed:', result.message);
                }
            })
            .catch(function(error) {
                console.error('[PPV Push] Registration error:', error);
            });
        },

        /**
         * Unregister current token
         */
        unregisterToken: function() {
            const token = this.token || localStorage.getItem('ppv_push_token');

            if (!token) {
                console.log('[PPV Push] No token to unregister');
                return Promise.resolve(true);
            }

            return fetch('/wp-json/punktepass/v1/push/unregister', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ token: token }),
                credentials: 'include'
            })
            .then(function(response) { return response.json(); })
            .then(function(result) {
                console.log('[PPV Push] Unregister result:', result);
                try {
                    localStorage.removeItem('ppv_push_token');
                    localStorage.removeItem('ppv_push_registered');
                } catch (e) {}
                return result.success;
            });
        },

        /**
         * Get device name for identification
         */
        getDeviceName: function() {
            const ua = navigator.userAgent;

            if (this.platform === 'ios') {
                if (ua.includes('iPhone')) return 'iPhone';
                if (ua.includes('iPad')) return 'iPad';
                return 'iOS Device';
            }

            if (this.platform === 'android') {
                const match = ua.match(/\(([^)]+)\)/);
                if (match) {
                    const parts = match[1].split(';');
                    if (parts.length > 1) {
                        return parts[parts.length - 1].trim().split(' Build')[0];
                    }
                }
                return 'Android Device';
            }

            // Web - return browser name
            if (ua.includes('Chrome')) return 'Chrome Browser';
            if (ua.includes('Firefox')) return 'Firefox Browser';
            if (ua.includes('Safari')) return 'Safari Browser';

            return 'Web Browser';
        },

        /**
         * Handle incoming notification
         */
        handleNotification: function(data) {
            // Dispatch custom event for app to handle
            const event = new CustomEvent('ppv-push-received', { detail: data });
            window.dispatchEvent(event);

            // Show in-app notification if configured
            if (window.ppvToast && data.aps && data.aps.alert) {
                const alert = data.aps.alert;
                const title = alert.title || 'PunktePass';
                const body = alert.body || '';
                window.ppvToast(title + ': ' + body, 'info');
            }
        },

        /**
         * Handle notification click
         */
        handleNotificationClick: function(data) {
            // Dispatch custom event
            const event = new CustomEvent('ppv-push-clicked', { detail: data });
            window.dispatchEvent(event);

            // Handle navigation based on notification type
            if (data.type) {
                switch (data.type) {
                    case 'points_received':
                        window.location.href = '/meine-punkte';
                        break;
                    case 'reward_approved':
                        window.location.href = '/belohnungen';
                        break;
                    case 'new_scan':
                    case 'reward_request':
                        // Store notification - stay on current page or go to dashboard
                        window.location.href = '/qr-center';
                        break;
                    case 'promotion':
                        if (data.store_id) {
                            window.location.href = '/store/' + data.store_id;
                        }
                        break;
                    default:
                        window.location.href = '/user_dashboard';
                }
            }
        },

        /**
         * Subscribe to Web Push (for PWA)
         */
        subscribeWebPush: function() {
            // TODO: Implement Web Push subscription
            // Requires VAPID keys and service worker setup
            console.log('[PPV Push] Web Push subscription not yet implemented');
            return Promise.resolve(false);
        },

        /**
         * Check if push is supported and available
         */
        isSupported: function() {
            if (this.platform === 'ios' || this.platform === 'android') {
                return true;
            }

            // Web Push support
            return 'Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window;
        },

        /**
         * Get current registration status
         */
        getStatus: function() {
            return {
                platform: this.platform,
                permissionState: this.permissionState,
                hasToken: !!this.token,
                isRegistered: !!localStorage.getItem('ppv_push_registered'),
                isSupported: this.isSupported()
            };
        }
    };

    // Export to window
    window.PPVPushBridge = PPVPushBridge;

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            PPVPushBridge.init();
        });
    } else {
        PPVPushBridge.init();
    }

})();
