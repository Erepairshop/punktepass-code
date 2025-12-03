/**
 * PunktePass – Global PWA Controller (v3.0)
 * Turbo.js compatible
 * Minden oldalra betöltődik (Dashboard, Points, Rewards, stb.)
 * ✅ Global 401 handler - automatikus login redirect
 */

// ============================================================
// 🔇 PRODUCTION MODE - Disable all console output
// ============================================================
(function() {
  // Check if debug mode is enabled via PHP
  const isDebug = window.PPV_DEBUG === true;

  if (!isDebug) {
    // Save original console methods (for emergencies)
    window._console = {
      log: console.log,
      warn: console.warn,
      error: console.error,
      debug: console.debug,
      info: console.info
    };

    // Override console methods to do nothing
    console.log = function() {};
    console.debug = function() {};
    console.info = function() {};
    // Keep warn and error for critical issues
    // console.warn = function() {};
    // console.error = function() {};
  }
})();

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

  // ✅ FIX: Use { once: true } to auto-remove listener after firing (prevents memory leak)
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
    }, { once: true }); // ← Auto-remove after firing
    return originalXHRSend.apply(this, args);
  };
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
}

// ============================================================
// 📳 GLOBAL HAPTIC FEEDBACK UTILITY
// ============================================================
// Usage: window.ppvHaptic('tap') / ('success') / ('error') / ('warning')
// ============================================================

window.ppvHaptic = (function() {
  // Check if vibration is supported
  const supportsVibration = 'vibrate' in navigator;

  // Vibration patterns (in milliseconds)
  const patterns = {
    tap: 30,                    // Light tap for buttons
    button: 50,                 // Medium for important buttons
    success: [50, 30, 50],      // Double tap for success
    error: [100, 50, 100, 50, 100], // Triple for errors
    warning: [80, 40, 80],      // Medium double for warnings
    scan: [30, 20, 30, 20, 50], // QR scan success pattern
    reward: [50, 30, 80, 30, 100], // Celebration for rewards
  };

  return function(type = 'tap') {
    if (!supportsVibration) return false;

    const pattern = patterns[type] || patterns.tap;

    try {
      navigator.vibrate(pattern);
      return true;
    } catch (e) {
      return false;
    }
  };
})();

// ============================================================
// ⏳ GLOBAL BUTTON LOADING STATE UTILITY
// ============================================================
// Usage: window.ppvBtnLoading(btn, true) / (btn, false, 'Original Text')
// ============================================================

window.ppvBtnLoading = (function() {
  const originalStates = new WeakMap();

  return function(btn, loading = true, restoreText = null) {
    if (!btn) return;

    // Handle jQuery objects
    const el = btn.jquery ? btn[0] : btn;
    if (!el) return;

    if (loading) {
      // Save original state
      originalStates.set(el, {
        html: el.innerHTML,
        disabled: el.disabled,
        width: el.offsetWidth
      });

      // Set fixed width to prevent layout shift
      el.style.minWidth = el.offsetWidth + 'px';

      // Show spinner
      el.innerHTML = '<i class="ri-loader-4-line ri-spin"></i>';
      el.disabled = true;
      el.classList.add('ppv-btn-loading');

    } else {
      // Restore original state
      const original = originalStates.get(el);
      if (original) {
        el.innerHTML = restoreText || original.html;
        el.disabled = original.disabled;
        el.style.minWidth = '';
        originalStates.delete(el);
      } else if (restoreText) {
        el.innerHTML = restoreText;
        el.disabled = false;
      }
      el.classList.remove('ppv-btn-loading');
    }
  };
})();

// ============================================================
// 💬 GLOBAL USER-FRIENDLY ERROR MESSAGES
// ============================================================
// Usage: window.ppvErrorMsg('network') / ('offline') / ('auth') / (errorObj)
// ============================================================

window.ppvErrorMsg = (function() {
  // Get current language from HTML or fallback
  const getLang = () => {
    const htmlLang = document.documentElement.lang || 'de';
    return ['de', 'hu', 'ro'].includes(htmlLang) ? htmlLang : 'de';
  };

  // User-friendly error messages
  const messages = {
    de: {
      network: 'Verbindungsproblem. Bitte überprüfe deine Internetverbindung.',
      offline: 'Du bist offline. Bitte verbinde dich mit dem Internet.',
      auth: 'Sitzung abgelaufen. Bitte melde dich erneut an.',
      forbidden: 'Du hast keine Berechtigung für diese Aktion.',
      not_found: 'Die angeforderte Ressource wurde nicht gefunden.',
      server: 'Ein Serverfehler ist aufgetreten. Bitte versuche es später erneut.',
      timeout: 'Die Anfrage hat zu lange gedauert. Bitte versuche es erneut.',
      invalid_data: 'Ungültige Daten. Bitte überprüfe deine Eingabe.',
      rate_limit: 'Zu viele Anfragen. Bitte warte einen Moment.',
      scan_duplicate: 'Dieser QR-Code wurde bereits gescannt.',
      scan_expired: 'Der QR-Code ist abgelaufen. Bitte generiere einen neuen.',
      insufficient_points: 'Nicht genügend Punkte für diese Aktion.',
      unknown: 'Ein unerwarteter Fehler ist aufgetreten.',
      try_again: 'Bitte versuche es erneut.',
    },
    hu: {
      network: 'Kapcsolati hiba. Kérlek ellenőrizd az internetkapcsolatod.',
      offline: 'Offline vagy. Kérlek csatlakozz az internethez.',
      auth: 'A munkamenet lejárt. Kérlek jelentkezz be újra.',
      forbidden: 'Nincs jogosultságod ehhez a művelethez.',
      not_found: 'A kért erőforrás nem található.',
      server: 'Szerverhiba történt. Kérlek próbáld újra később.',
      timeout: 'A kérés túl sokáig tartott. Kérlek próbáld újra.',
      invalid_data: 'Érvénytelen adatok. Kérlek ellenőrizd a bevitelt.',
      rate_limit: 'Túl sok kérés. Kérlek várj egy pillanatot.',
      scan_duplicate: 'Ez a QR-kód már be lett szkennelve.',
      scan_expired: 'A QR-kód lejárt. Kérlek generálj egy újat.',
      insufficient_points: 'Nincs elég pontod ehhez a művelethez.',
      unknown: 'Váratlan hiba történt.',
      try_again: 'Kérlek próbáld újra.',
    },
    ro: {
      network: 'Problemă de conexiune. Te rugăm să verifici conexiunea la internet.',
      offline: 'Ești offline. Te rugăm să te conectezi la internet.',
      auth: 'Sesiunea a expirat. Te rugăm să te autentifici din nou.',
      forbidden: 'Nu ai permisiunea pentru această acțiune.',
      not_found: 'Resursa solicitată nu a fost găsită.',
      server: 'A apărut o eroare de server. Te rugăm să încerci mai târziu.',
      timeout: 'Cererea a durat prea mult. Te rugăm să încerci din nou.',
      invalid_data: 'Date invalide. Te rugăm să verifici intrarea.',
      rate_limit: 'Prea multe cereri. Te rugăm să aștepți un moment.',
      scan_duplicate: 'Acest cod QR a fost deja scanat.',
      scan_expired: 'Codul QR a expirat. Te rugăm să generezi unul nou.',
      insufficient_points: 'Nu ai suficiente puncte pentru această acțiune.',
      unknown: 'A apărut o eroare neașteptată.',
      try_again: 'Te rugăm să încerci din nou.',
    }
  };

  // Map HTTP status codes to error types
  const statusMap = {
    400: 'invalid_data',
    401: 'auth',
    403: 'forbidden',
    404: 'not_found',
    408: 'timeout',
    429: 'rate_limit',
    500: 'server',
    502: 'server',
    503: 'server',
    504: 'timeout'
  };

  return function(errorOrType, fallbackMsg = null) {
    const lang = getLang();
    const langMsgs = messages[lang] || messages.de;

    // If it's a string error type
    if (typeof errorOrType === 'string') {
      return langMsgs[errorOrType] || fallbackMsg || langMsgs.unknown;
    }

    // If it's an Error object
    if (errorOrType instanceof Error) {
      const msg = errorOrType.message?.toLowerCase() || '';

      // Check for network errors
      if (msg.includes('network') || msg.includes('fetch') || msg.includes('connection')) {
        return navigator.onLine ? langMsgs.network : langMsgs.offline;
      }

      // Check for timeout
      if (msg.includes('timeout') || msg.includes('aborted')) {
        return langMsgs.timeout;
      }

      // Return server message if it's a user-friendly message already
      if (errorOrType.message && !msg.includes('http') && msg.length < 100) {
        return errorOrType.message;
      }

      return fallbackMsg || langMsgs.unknown;
    }

    // If it's an HTTP response or status code
    if (typeof errorOrType === 'number') {
      const type = statusMap[errorOrType];
      return type ? langMsgs[type] : langMsgs.server;
    }

    // If it's a response object
    if (errorOrType && errorOrType.status) {
      const type = statusMap[errorOrType.status];
      return type ? langMsgs[type] : langMsgs.server;
    }

    return fallbackMsg || langMsgs.unknown;
  };
})();

// ============================================================
// 🚀 SCROLL PERFORMANCE OPTIMIZATION
// ============================================================
// Adds 'is-scrolling' class to body during scroll to disable
// expensive CSS effects (backdrop-filter, animations) for 60fps
// ============================================================

(function() {
  let scrollTimeout = null;
  let isScrolling = false;

  const onScroll = () => {
    if (!isScrolling) {
      isScrolling = true;
      document.body.classList.add('is-scrolling');
    }

    // Clear previous timeout
    if (scrollTimeout) {
      clearTimeout(scrollTimeout);
    }

    // Remove class after scroll ends (150ms debounce)
    scrollTimeout = setTimeout(() => {
      isScrolling = false;
      document.body.classList.remove('is-scrolling');
    }, 150);
  };

  // Use passive listener for better scroll performance
  window.addEventListener('scroll', onScroll, { passive: true });

  // Also handle touch scroll on mobile
  document.addEventListener('touchmove', onScroll, { passive: true });
})();
