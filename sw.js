// PunktePass PWA Service Worker
// Version: 6.0 - Global cache fix for dynamic pages
// ✅ Timeout protection (6s for iOS)
// ✅ Safari compatible with retry
// ✅ No POST blocking
// ✅ Network-first for API calls
// ✅ Fresh CSS/JS always
// ✅ Login pages never cached
// ✅ Force old cache deletion
// ✅ Dynamic pages (handler/user) never cached - fixes onboarding/profile state issues

const CACHE_VERSION = "v6.1";
const CACHE_NAME = "punktepass-" + CACHE_VERSION;
const API_CACHE = "punktepass-api-v6.1";

// Only cache critical files
const ASSETS = [
  "/manifest.json"
];

// ============================================================
// 🔧 INSTALL
// ============================================================
self.addEventListener("install", e => {
  e.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(ASSETS).catch(() => {});
      })
      .then(() => self.skipWaiting())
  );
});

// ============================================================
// 🧹 ACTIVATE - Clear old caches
// ============================================================
self.addEventListener("activate", e => {
  e.waitUntil(
    caches.keys()
      .then(keys => {
        return Promise.all(
          keys
            .filter(k => k.startsWith("punktepass-") && k !== CACHE_NAME && k !== API_CACHE)
            .map(k => {
              return caches.delete(k).catch(() => {});
            })
        );
      })
      .then(() => self.clients.claim())
  );
});

// ============================================================
// ⏱️ FETCH WITH TIMEOUT HELPER
// ============================================================
function fetchWithTimeout(req, timeout = 5000) {
  return Promise.race([
    fetch(req),
    new Promise((_, reject) =>
      setTimeout(() => reject(new Error('timeout')), timeout)
    )
  ]);
}

// ============================================================
// ⚡ FETCH HANDLER
// ============================================================
self.addEventListener("fetch", e => {
  const req = e.request;
  const url = new URL(req.url);

  // ✅ CRITICAL: Skip ALL non-GET requests (POST, PUT, DELETE, etc.)
  if (req.method !== 'GET') {
    e.respondWith(
      fetch(req).catch(() => {
        return new Response(
          JSON.stringify({ success: false, message: 'Offline' }),
          { status: 503, headers: { 'Content-Type': 'application/json' } }
        );
      })
    );
    return;
  }

  // ✅ API calls - Network first with timeout (6s for iOS compatibility)
  if (url.pathname.startsWith("/wp-json/ppv/v1/") ||
      url.pathname.startsWith("/wp-json/punktepass/v1/")) {
    e.respondWith(networkFirstWithTimeout(req, 6000));
    return;
  }

  // ✅ Login/Signup pages - Always fresh, never cache
  if (url.pathname.includes('/login') ||
      url.pathname.includes('/anmelden') ||
      url.pathname.includes('/bejelentkezes') ||
      url.pathname.includes('/signup') ||
      url.pathname.includes('/registrierung') ||
      url.pathname.includes('/regisztracio')) {
    e.respondWith(
      fetch(req, { cache: 'no-store' }).catch(() => {
        return new Response(
          '<html><body><h1>Offline</h1><p>Bitte überprüfe deine Internetverbindung.</p></body></html>',
          { status: 503, headers: { 'Content-Type': 'text/html' } }
        );
      })
    );
    return;
  }

  // ✅ Dynamic handler/user pages - Network first, no cache
  // These pages have dynamic state (onboarding, profile, points, etc.)
  const dynamicPages = [
    '/qr-center',
    '/mein-profil',
    '/rewards',
    '/statistik',
    '/einstellungen',
    '/user_dashboard',
    '/meine-punkte',
    '/belohnungen'
  ];
  if (dynamicPages.some(page => url.pathname.includes(page))) {
    e.respondWith(
      fetch(req, { cache: 'no-store' }).catch(() => {
        return new Response(
          '<html><body style="font-family: sans-serif; text-align: center; padding: 40px;"><h1>Offline</h1><p>Bitte überprüfe deine Internetverbindung.</p></body></html>',
          { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
      })
    );
    return;
  }

  // ✅ CSS & JS - Always fresh, fallback to cache if offline
  if (url.pathname.endsWith(".css") || 
      url.pathname.endsWith(".js") ||
      url.pathname.includes('/assets/css/') ||
      url.pathname.includes('/assets/js/')) {
    e.respondWith(
      fetch(req, { cache: 'no-store' })
        .catch(() => {
          return caches.match(req);
        })
    );
    return;
  }

  // ✅ Root "/" - Always fresh
  if (url.pathname === '/' || url.pathname === '') {
    e.respondWith(
      fetch(req, { cache: 'no-store' })
        .catch(() => caches.match(req))
    );
    return;
  }

  // ✅ HTML pages - Cache first with background refresh
  if (req.mode === "navigate") {
    e.respondWith(pageCache(req));
    return;
  }

  // ✅ Static assets - Cache first
  if (ASSETS.some(a => url.pathname.endsWith(a.split("/").pop()))) {
    e.respondWith(cacheFirst(req));
    return;
  }

  // ✅ Images - Cache first
  if (url.pathname.match(/\.(png|jpg|jpeg|gif|webp|svg)$/i)) {
    e.respondWith(cacheFirstImage(req));
    return;
  }

  // ✅ Everything else - Network first with fallback
  e.respondWith(
    fetch(req)
      .catch(() => caches.match(req))
  );
});

// ============================================================
// 💾 CACHE STRATEGIES
// ============================================================

// Cache first - for static assets
async function cacheFirst(req) {
  try {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(req);
    
    if (cached) {
      return cached;
    }

    const fresh = await fetch(req);
    
    if (fresh && fresh.status === 200) {
      cache.put(req, fresh.clone());
    }
    
    return fresh;
  } catch (err) {
    return new Response('Offline', { status: 503 });
  }
}

// Cache first for images (longer cache)
async function cacheFirstImage(req) {
  try {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(req);
    
    if (cached) {
      return cached;
    }

    const fresh = await fetchWithTimeout(req, 8000);
    
    if (fresh && fresh.status === 200) {
      cache.put(req, fresh.clone());
    }
    
    return fresh;
  } catch (err) {
    return new Response('Image offline', { status: 503 });
  }
}
// Network first with TIMEOUT and RETRY - for API calls (NO CACHE)
// iOS Safari fix: retry once on timeout/failure
async function networkFirstWithTimeout(req, timeout = 6000) {
  // First attempt
  try {
    const fresh = await fetchWithTimeout(req, timeout);
    if (fresh.ok) return fresh;
    // Non-ok response but not a timeout - return as-is
    return fresh;
  } catch (err) {
    // First attempt failed - retry once (iOS Safari often fails first request)
    try {
      const retry = await fetchWithTimeout(req, timeout);
      return retry;
    } catch (retryErr) {
      // Both attempts failed - return offline response
      return new Response(
        JSON.stringify({ success: false, message: 'API unavailable', offline: true }),
        { status: 503, headers: { 'Content-Type': 'application/json' } }
      );
    }
  }
}

// Page cache - for HTML navigation
async function pageCache(req) {
  try {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(req);
    
    const fetchPromise = fetch(req)
      .then(res => {
        if (res && res.status === 200 && res.type === 'basic') {
          cache.put(req, res.clone());
        }
        return res;
      })
      .catch(err => {
        if (cached) {
          return cached;
        }
        return new Response(
          '<html><body style="font-family: sans-serif; text-align: center; padding: 40px;"><h1>🚫 Offline</h1><p>Bitte überprüfe deine Internetverbindung.</p></body></html>',
          { 
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=utf-8' } 
          }
        );
      });
    
    return cached || fetchPromise;
  } catch (err) {
    return new Response('Error', { status: 500 });
  }
}

// ============================================================
// 🧠 MESSAGE HANDLER
// ============================================================
self.addEventListener("message", event => {
  const data = event.data;
  
  // Clear cache on request
  if (data === "clear-cache" || data?.type === "clear-cache") {
    caches.keys().then(keys => {
      keys.forEach(cacheName => {
        if (cacheName.includes('punktepass')) {
          caches.delete(cacheName).catch(() => {});
        }
      });
    });
    
    return;
  }
  
  // SPA navigation
  if (data?.type === "NAVIGATE") {
    const url = data.url;
    
    event.waitUntil(
      fetch(url, { cache: 'no-store' })
        .then(r => {
          if (r.ok) return r.text();
          throw new Error(`HTTP ${r.status}`);
        })
        .then(html => {
          self.clients.matchAll({ type: "window" }).then(clients => {
            clients.forEach(client => {
              client.postMessage({ 
                type: "UPDATE_CONTENT", 
                html, 
                url 
              });
            });
          });
        })
        .catch(err => {
          self.clients.matchAll({ type: "window" }).then(clients => {
            clients.forEach(client => {
              client.postMessage({ 
                type: "NAVIGATE_ERROR", 
                error: err.message
              });
            });
          });
        })
    );
  }
});

// ============================================================
// 🔁 BACKGROUND SYNC
// ============================================================
self.addEventListener("sync", e => {
  if (e.tag === "punktepass-sync") {
    e.waitUntil(Promise.resolve());
  }
});

// ============================================================
// 🔔 PUSH NOTIFICATIONS
// ============================================================
self.addEventListener("push", e => {
  try {
    const data = e.data ? e.data.json() : {};

    e.waitUntil(
      self.registration.showNotification(data.title || "PunktePass", {
        body: data.body || "Új esemény érkezett!",
        icon: "/wp-content/plugins/punktepass/assets/img/pwa-icon-192.png",
        badge: "/wp-content/plugins/punktepass/assets/img/pwa-icon-192.png",
        tag: "punktepass-notification",
        requireInteraction: false
      }).catch(err => {})
    );
  } catch (err) {}
});