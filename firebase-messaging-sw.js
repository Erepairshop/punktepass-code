// Firebase Messaging Service Worker v5 - 2026-04-27
// This file must be at the root of the domain

// Take over immediately so new SW versions activate without waiting for
// all TWA tabs to close.
self.addEventListener('install', (e) => { self.skipWaiting(); });
self.addEventListener('activate', (e) => { e.waitUntil(self.clients.claim()); });

importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js');

// Firebase configuration
firebase.initializeApp({
    apiKey: "AIzaSyBB4-sQb-ZlMEDj4LVGYSenB8b8R_mUuOI",
    authDomain: "punktepass.firebaseapp.com",
    projectId: "punktepass",
    storageBucket: "punktepass.firebasestorage.app",
    messagingSenderId: "373165045072",
    appId: "1:373165045072:web:1ef83f576e6fc222a7a855"
});

const messaging = firebase.messaging();

// Handle background messages.
// In TWA the FCM SDK does NOT auto-display, so we must always call
// showNotification. Use a stable tag from the payload (or hash of title+body)
// so any duplicates from auto-display would collapse into one.
messaging.onBackgroundMessage((payload) => {
    console.log('[FCM SW] Background message received:', payload);

    const title = payload.notification?.title || payload.data?.title || 'PunktePass';
    const body = payload.notification?.body || payload.data?.body || '';
    const stableTag = payload.notification?.tag
        || payload.fcmOptions?.tag
        || ('pp-' + (title + '|' + body).split('').reduce((h,c)=>((h<<5)-h+c.charCodeAt(0))|0, 0));

    const notificationOptions = {
        body: body,
        icon: payload.notification?.icon || '/wp-content/plugins/punktepass/assets/img/pwa-icon-192.png',
        badge: '/wp-content/plugins/punktepass/assets/img/pwa-icon-192.png',
        image: payload.notification?.image,
        tag: stableTag,
        renotify: false,
        data: payload.data || {}
    };
    return self.registration.showNotification(title, notificationOptions);
});

// Handle notification click
self.addEventListener('notificationclick', (event) => {
    console.log('[FCM SW] Notification clicked:', event);
    event.notification.close();

    // Open or focus the app
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // If a window is already open, focus it
                for (const client of clientList) {
                    if (client.url.includes('punktepass') && 'focus' in client) {
                        return client.focus();
                    }
                }
                // Otherwise open a new window
                if (clients.openWindow) {
                    return clients.openWindow('/user_dashboard');
                }
            })
    );
});
