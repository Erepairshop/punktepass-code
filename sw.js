// PunktePass PWA Service Worker
// Version: 6.2 - iOS PWA white screen fix
// ✅ Timeout protection (6s for iOS)
// ✅ Safari compatible with retry
// ✅ No POST blocking
// ✅ Network-first for API calls
// ✅ Fresh CSS/JS always
// ✅ Login pages never cached
// ✅ Force old cache deletion
// ✅ Dynamic pages (handler/user) never cached - fixes onboarding/profile state issues
// ✅ PWA standalone mode: Skip cache for HTML navigation (v6.2)

const CACHE_VERSION = "v6.3";
const CACHE_NAME = "punktepass-" + CACHE_VERSION;
const API_CACHE = "punktepass-api-v6.3";

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

  // ✅ PWA STANDALONE MODE - Always fresh HTML to prevent white screen
  // Check if request comes from PWA (display-mode: standalone)
  // The Sec-Fetch-Dest header tells us if this is a document request
  const isNavigationRequest = req.mode === 'navigate' || req.destination === 'document';
  const isPWARequest = req.headers.get('Sec-Fetch-Mode') === 'navigate' &&
                       (url.searchParams.has('source') && url.searchParams.get('source') === 'pwa');

  // For PWA navigation requests, ALWAYS fetch fresh (no cache)
  // This prevents white screen issues on iOS when returning from background
  if (isNavigationRequest) {
    const referer = req.headers.get('Referer') || '';
    const isPWANavigation = referer.includes('source=pwa') ||
                            url.searchParams.get('source') === 'pwa' ||
                            req.headers.get('X-PWA-Request') === 'true';

    // Check for PWA via service worker client info
    if (isPWANavigation || isPWARequest) {
      e.respondWith(
        fetch(req, { cache: 'no-store' }).catch(() => {
          return new Response(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title></head><body style="font-family:system-ui;text-align:center;padding:40px;background:#0f172a;color:#e2e8f0;"><h1>📱 Offline</h1><p>Bitte überprüfe deine Internetverbindung.</p><button onclick="location.reload()" style="margin-top:20px;padding:12px 24px;background:#3b82f6;color:white;border:none;border-radius:8px;font-size:16px;cursor:pointer;">Erneut versuchen</button></body></html>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
          );
        })
      );
      return;
    }
  }

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
  // v6.3: minden lang-variansot + /handler URL-t belefoglalva (scroll-fix nem ment ki
  //       mert pl. /handler / /mein-konto / /my-points nem volt a listan es
  //       pageCache() regi HTML-t szolgalt ki)
  const dynamicPages = [
    '/handler',
    '/qr-center',
    '/kasszascanner', '/kassenscanner',
    '/mein-profil', '/mein-konto', '/my-account', '/profile', '/profil', '/fiok',
    '/rewards', '/belohnungen', '/jutalmak', '/recompense', '/premi',
    '/statistik', '/statistics', '/statisztika', '/statistici',
    '/einstellungen', '/settings', '/beallitasok', '/setari',
    '/user_dashboard', '/user-dashboard', '/dashboard', '/mein-dashboard',
    '/my-points', '/meine-punkte', '/pontjaim', '/punctele-mele'
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

  // ✅ HTML pages - NETWORK FIRST (v6.3: scroll-fix es barmilyen inline style
  // azonnal jusson el a user-hez, cache-first-ben a regi HTML maradt.
  // Offline fallback: ha nincs net, cache-bol szolgaljuk ki.)
  if (req.mode === "navigate") {
    e.respondWith(
      fetch(req, { cache: 'no-store' })
        .then(fresh => {
          if (fresh && fresh.status === 200) {
            const cache = caches.open(CACHE_NAME);
            cache.then(c => c.put(req, fresh.clone()).catch(() => {}));
          }
          return fresh;
        })
        .catch(() => caches.match(req) || new Response(
          '<html><body style="font-family:sans-serif;text-align:center;padding:40px"><h1>Offline</h1><p>Bitte überprüfe deine Internetverbindung.</p></body></html>',
          { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        ))
    );
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
// 🔔 PUSH NOTIFICATIONS — handled exclusively by firebase-messaging-sw.js
// (this root SW would otherwise fire a duplicate showNotification when its
//  scope captures FCM payloads. Disabled to prevent the "2 push" bug.)
// ============================================================