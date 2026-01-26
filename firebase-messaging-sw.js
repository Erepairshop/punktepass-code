// Firebase Messaging Service Worker
// This file must be at the root of the domain

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

// Handle background messages
messaging.onBackgroundMessage((payload) => {
    console.log('[FCM SW] Background message received:', payload);

    const notificationTitle = payload.notification?.title || 'PunktePass';
    const notificationOptions = {
        body: payload.notification?.body || '',
        icon: '/wp-content/plugins/punktepass/assets/img/pwa-icon-192.png',
        badge: '/wp-content/plugins/punktepass/assets/img/pwa-icon-192.png',
        tag: 'punktepass-notification',
        data: payload.data
    };

    return self.registration.showNotification(notificationTitle, notificationOptions);
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
