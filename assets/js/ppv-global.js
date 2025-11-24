/**
 * PunktePass – Global PWA Controller (v3.0)
 * Turbo.js compatible
 * Minden oldalra betöltődik (Dashboard, Points, Rewards, stb.)
 * ✅ Global 401 handler - automatikus login redirect
 */

console.log("✅ [PPV_GLOBAL] v3.0 active (Turbo-compatible)");

// ============================================================
// 🔐 GLOBAL 401 HANDLER - Session expired redirect
// ============================================================
// Ha a session lejárt (401) és nincs navigáció (app mód),
// automatikusan visszairányít a login oldalra.
// ============================================================

(function() {
  // Store original fetch
  const originalFetch = window.fetch;

  // Login URL
  const LOGIN_URL = '/login';

  // Pages where we don't want to redirect (login page itself)
  const NO_REDIRECT_PAGES = ['/login', '/signup', '/register', '/forgot-password'];

  // Flag to prevent multiple redirects
  let isRedirecting = false;

  // Check if we're on a no-redirect page
  const shouldRedirect = () => {
    const currentPath = window.location.pathname;
    return !NO_REDIRECT_PAGES.some(page => currentPath.startsWith(page));
  };

  // Handle 401 response
  const handle401 = () => {
    if (isRedirecting) return;
    if (!shouldRedirect()) return;

    isRedirecting = true;
    console.warn('🔐 [PPV_GLOBAL] Session expired (401) - redirecting to login');

    // Show toast if available
    if (window.ppvShowPointToast) {
      const lang = document.documentElement.lang || 'de';
      const messages = {
        de: 'Sitzung abgelaufen. Bitte erneut anmelden.',
        hu: 'A munkamenet lejárt. Kérlek jelentkezz be újra.',
        ro: 'Sesiune expirată. Vă rugăm să vă autentificați din nou.'
      };
      window.ppvShowPointToast('error', 0, 'PunktePass', messages[lang] || messages.de);
    }

    // Redirect after short delay (allow toast to show)
    setTimeout(() => {
      // Save current URL for redirect back after login
      const returnUrl = window.location.pathname + window.location.search;
      if (returnUrl && returnUrl !== LOGIN_URL) {
        sessionStorage.setItem('ppv_return_url', returnUrl);
      }

      // Redirect to login
      window.location.href = LOGIN_URL;
    }, 1500);
  };

  // Override global fetch
  window.fetch = async function(...args) {
    try {
      const response = await originalFetch.apply(this, args);

      // Check for 401 Unauthorized
      if (response.status === 401) {
        // Check if this is an API call (not a static resource)
        const url = typeof args[0] === 'string' ? args[0] : args[0]?.url;
        const isApiCall = url && (
          url.includes('/wp-json/') ||
          url.includes('/api/') ||
          url.includes('admin-ajax.php')
        );

        if (isApiCall) {
          console.warn('🔐 [PPV_GLOBAL] 401 detected on API call:', url);
          handle401();
        }
      }

      return response;
    } catch (error) {
      throw error;
    }
  };

  // Also handle XMLHttpRequest for jQuery AJAX calls
  const originalXHROpen = XMLHttpRequest.prototype.open;
  const originalXHRSend = XMLHttpRequest.prototype.send;

  XMLHttpRequest.prototype.open = function(method, url, ...rest) {
    this._ppvUrl = url;
    return originalXHROpen.call(this, method, url, ...rest);
  };

  XMLHttpRequest.prototype.send = function(...args) {
    this.addEventListener('load', function() {
      if (this.status === 401) {
        const isApiCall = this._ppvUrl && (
          this._ppvUrl.includes('/wp-json/') ||
          this._ppvUrl.includes('/api/') ||
          this._ppvUrl.includes('admin-ajax.php')
        );

        if (isApiCall) {
          console.warn('🔐 [PPV_GLOBAL] 401 detected on XHR:', this._ppvUrl);
          handle401();
        }
      }
    });
    return originalXHRSend.apply(this, args);
  };

  console.log('🔐 [PPV_GLOBAL] 401 handler initialized');
})();

// 🚀 Turbo handles transitions now - removed beforeunload/pageshow opacity code
// OLD CODE REMOVED:
// window.addEventListener("beforeunload", () => { ... });
// window.addEventListener("pageshow", () => { ... });

// 🔹 Instant navigáció – cache előtöltés (only for non-Turbo links)
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("a[href^='/']:not([data-turbo='false'])").forEach((link) => {
    link.addEventListener("mouseenter", () => {
      const url = link.getAttribute("href");
      if (url && !url.startsWith("#")) {
        // Turbo handles prefetching, but we can hint
        if (window.Turbo) {
          // Turbo will handle this
        } else {
          fetch(url, { cache: "force-cache" });
        }
      }
    });
  });
});

// 🔹 Service Worker státusz
if ("serviceWorker" in navigator) {
  navigator.serviceWorker.ready
    .then(() => console.log("🟢 [PPV_SW] ready"))
    .catch(() => console.log("⚠️ [PPV_SW] not active"));
}
