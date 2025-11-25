/**
 * PunktePass – Theme Loader v2.5 (SINGLE CSS - light.css contains all styles)
 * ✅ Auto-detects all pages
 * ✅ Multi-domain cookie
 * ✅ MutationObserver for button detection
 * ✅ Service Worker messaging
 * ✅ Refresh memory
 * ✅ Icon sync on page load (sun/moon)
 * ✅ Works with both ppv-theme-toggle and ppv-theme-toggle-global
 * ✅ localStorage priority over meta tag (fixes Turbo navigation)
 * ✅ Prevents duplicate CSS loading (PHP already loads theme CSS)
 * Author: Erik Borota / PunktePass
 */

(function () {
  'use strict';

  const THEME_KEY = 'ppv_theme';
  const API_URL = '/wp-json/ppv/v1/theme';
  const DEBUG = false;

  // ============================================================
  // 🔹 LOG HELPER
  // ============================================================
  function log(level, msg, data = '') {
    if (level === 'DEBUG' && !DEBUG) return;
    console.log(`[PPV_THEME_v2] ${level}`, msg, data ? data : '');
  }

  // ============================================================
  // 🔹 LOAD CSS (Always use LIGHT CSS - contains all dark mode styles via body.ppv-dark)
  // ============================================================
  function loadThemeCSS(theme, forceReload = false) {
    // ALWAYS use light CSS - it contains both light and dark styles via body.ppv-dark selectors
    const cssPath = 'ppv-theme-light.css';

    // Check if light CSS is already loaded (by PHP wp_enqueue_style or previous JS call)
    const existingLinks = document.querySelectorAll('link[rel="stylesheet"]');
    let lightCSSLoaded = false;

    existingLinks.forEach(link => {
      if (link.href && link.href.includes(cssPath)) {
        lightCSSLoaded = true;
      }
    });

    // Update body classes and data-theme attribute for theme switching
    document.documentElement.setAttribute('data-theme', theme);
    if (document.body) {
      document.body.classList.remove('ppv-light', 'ppv-dark');
      document.body.classList.add(`ppv-${theme}`);
      log('INFO', `✅ Theme applied via body class: ppv-${theme}`);
    }

    // If light CSS already loaded, we're done (classes are updated above)
    if (lightCSSLoaded) {
      log('DEBUG', '⏩ Light CSS already loaded, theme switched via body class');
      return;
    }

    // Load light CSS if not already loaded
    const id = 'ppv-theme-css';
    const href = `/wp-content/plugins/punktepass/assets/css/ppv-theme-light.css?v=${Date.now()}`;

    // Remove any old JS-loaded CSS
    document.querySelectorAll(`link[id="${id}"]`).forEach(e => e.remove());

    const link = document.createElement('link');
    link.id = id;
    link.rel = 'stylesheet';
    link.href = href;
    link.onload = () => {
      log('INFO', '✅ Light CSS loaded (contains all theme styles)');
    };
    link.onerror = () => {
      log('ERROR', '❌ Light CSS failed to load:', href);
    };

    document.head.appendChild(link);
  }

  // ============================================================
  // 🔹 UPDATE THEME ICON (sun/moon)
  // ============================================================
  function updateThemeIcon(theme) {
    const icon = document.getElementById('ppv-theme-icon');
    if (icon) {
      // Icon shows what you'll switch TO:
      // light mode = moon icon (click to go dark)
      // dark mode = sun icon (click to go light)
      icon.className = theme === 'light' ? 'ri-moon-line' : 'ri-sun-line';
      log('INFO', '🌙☀️ Icon updated:', theme === 'light' ? 'moon (click for dark)' : 'sun (click for light)');
    }
  }

  // ============================================================
  // 🔹 GET THEME (Priority: localStorage > Cookie > Meta > Default)
  // ============================================================
  function getTheme() {
    // 1. localStorage (CLIENT-SIDE - highest priority, updated immediately on toggle)
    const saved = localStorage.getItem(THEME_KEY);
    if (saved && ['dark', 'light'].includes(saved)) {
      log('DEBUG', 'Theme from localStorage:', saved);
      return saved;
    }

    // 2. Cookie (also client-side, persists across sessions)
    const cookie = getCookie(THEME_KEY);
    if (cookie && ['dark', 'light'].includes(cookie)) {
      log('DEBUG', 'Theme from cookie:', cookie);
      return cookie;
    }

    // 3. Meta tag from PHP (server-side, may be stale after client toggle)
    const meta = document.querySelector('meta[name="ppv-theme"]');
    if (meta) {
      const theme = meta.getAttribute('content');
      if (['dark', 'light'].includes(theme)) {
        log('DEBUG', 'Theme from meta tag:', theme);
        return theme;
      }
    }

    // 4. Default
    log('DEBUG', 'Using default theme: light');
    return 'light';
  }

  // ============================================================
  // 🔹 GET COOKIE
  // ============================================================
  function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? match[2] : null;
  }

  // ============================================================
  // 🔹 SET MULTI-DOMAIN COOKIE
  // ============================================================
  function setMultiDomainCookie(value) {
    const expire = new Date();
    expire.setFullYear(expire.getFullYear() + 1);
    const path = '/';
    const isSecure = window.location.protocol === 'https:';

    // Set on current domain (only use Secure flag on HTTPS)
    const cookieStr = isSecure
      ? `${THEME_KEY}=${value};path=${path};expires=${expire.toUTCString()};SameSite=Lax;Secure`
      : `${THEME_KEY}=${value};path=${path};expires=${expire.toUTCString()};SameSite=Lax`;

    document.cookie = cookieStr;

    log('DEBUG', '🍪 Cookie set:', value, isSecure ? '(Secure)' : '(HTTP)');
  }

  // ============================================================
  // 🔹 SYNC TO SERVER
  // ============================================================
  async function syncThemeToServer(theme) {
    try {
      // Get nonce from wp_localize_script or fallback to meta tag
      const nonce = (typeof ppvTheme !== 'undefined' && ppvTheme.nonce)
        ? ppvTheme.nonce
        : document.querySelector('meta[name="wp-nonce"]')?.content || '';

      const response = await fetch(API_URL + '/set', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({ theme }),
        credentials: 'include',
      });

      if (!response.ok) {
        log('WARN', 'Theme sync failed:', response.status);
        return false;
      }

      const data = await response.json();
      log('INFO', '✅ Theme synced to server:', theme);
      return true;

    } catch (err) {
      log('WARN', 'Sync error (offline?):', err.message);
      return false;
    }
  }

  // ============================================================
  // 🔹 ATTACH BUTTON LISTENER (MutationObserver)
  // ============================================================
  function attachButtonListener() {
    // Try both button IDs (legacy and new global)
    const btn = document.getElementById('ppv-theme-toggle') || document.getElementById('ppv-theme-toggle-global');
    if (!btn) {
      log('DEBUG', 'Button not found yet, will keep watching');
      return false;
    }

    // Skip if already attached (prevents double listeners on Turbo navigation)
    if (btn.dataset.themeListenerAttached) {
      log('DEBUG', 'Button listener already attached, skipping');
      return true;
    }
    btn.dataset.themeListenerAttached = 'true';

    log('INFO', '✅ Theme toggle button found, attaching listener');

    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();

      const current = document.documentElement.getAttribute('data-theme') || 'light';
      const newTheme = current === 'dark' ? 'light' : 'dark';

      log('INFO', `🔄 Theme switching: ${current} → ${newTheme}`);

      // 1. Update UI immediately (forceReload=true for theme switch)
      loadThemeCSS(newTheme, true);
      localStorage.setItem(THEME_KEY, newTheme);
      setMultiDomainCookie(newTheme);

      // 2. Update theme icon immediately
      updateThemeIcon(newTheme);

      // 3. Sync to server (async)
      await syncThemeToServer(newTheme);

      // 4. Message Service Worker to clear cache
      if (navigator.serviceWorker?.controller) {
        navigator.serviceWorker.controller.postMessage({
          type: 'clear-theme-cache',
          theme: newTheme,
        });
        log('INFO', '✉️ SW message sent: clear-theme-cache');
      }

      // 5. Broadcast to other tabs
      if (typeof BroadcastChannel !== 'undefined') {
        try {
          const bc = new BroadcastChannel('ppv-theme-sync');
          bc.postMessage({ type: 'theme-changed', theme: newTheme });
          log('INFO', '📢 Broadcast sent: theme-changed');
        } catch (err) {
          log('DEBUG', 'BroadcastChannel not available');
        }
      }

      // 6. Haptic feedback
      if (navigator.vibrate) navigator.vibrate(20);
    });

    return true;
  }

  // ============================================================
  // 🔹 MUTATION OBSERVER (for dynamic buttons)
  // ============================================================
  function startMutationObserver() {
    const observer = new MutationObserver(() => {
      // Check for either button ID
      const btn = document.getElementById('ppv-theme-toggle') || document.getElementById('ppv-theme-toggle-global');
      if (!btn) return;

      if (attachButtonListener()) {
        // Also update icon when button is found
        updateThemeIcon(getTheme());
        observer.disconnect();
        log('INFO', '✅ MutationObserver: Button found and attached');
      }
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });

    // ✅ FIX: Auto-disconnect after 10s if button never found (prevents memory leak)
    setTimeout(() => {
      observer.disconnect();
      log('DEBUG', 'MutationObserver auto-disconnected after 10s timeout');
    }, 10000);

    log('DEBUG', 'MutationObserver started');
  }

  // ============================================================
  // 🔹 LISTEN FOR BROADCASTS (Cross-tab sync)
  // ============================================================
  function listenForBroadcasts() {
    if (typeof BroadcastChannel === 'undefined') return;

    try {
      const bc = new BroadcastChannel('ppv-theme-sync');
      bc.addEventListener('message', (event) => {
        if (event.data?.type === 'theme-changed') {
          const newTheme = event.data.theme;
          log('INFO', '📨 Broadcast received: theme-changed', newTheme);

          // Update UI (forceReload=true for cross-tab theme sync)
          loadThemeCSS(newTheme, true);
          localStorage.setItem(THEME_KEY, newTheme);
        }
      });

      log('DEBUG', 'BroadcastChannel listener started');
    } catch (err) {
      log('DEBUG', 'BroadcastChannel error:', err.message);
    }
  }

  // ============================================================
  // 🔹 MAIN INIT FUNCTION (Turbo-compatible)
  // ============================================================
  function initThemeLoader() {
    log('INFO', '🚀 Theme Loader v2.3 initialized (localStorage priority + icon sync)');

    // 1. Get current theme
    const theme = getTheme();
    log('INFO', `📍 Current theme: ${theme}`);

    // 2. Load CSS immediately
    loadThemeCSS(theme);

    // 3. Apply body classes immediately (don't wait for CSS load)
    document.documentElement.setAttribute('data-theme', theme);
    document.body.classList.remove('ppv-light', 'ppv-dark');
    document.body.classList.add(`ppv-${theme}`);

    // 4. Update theme icon (sun/moon) - MUST happen on every init!
    updateThemeIcon(theme);

    // 5. Try to attach button (might already exist)
    if (!attachButtonListener()) {
      // Button doesn't exist yet, start watching
      startMutationObserver();
    }

    // 6. Listen for cross-tab broadcasts (only once)
    if (!window.PPV_BROADCAST_LISTENER) {
      window.PPV_BROADCAST_LISTENER = true;
      listenForBroadcasts();
    }
  }

  // ============================================================
  // 🔹 RUN ON DOMContentLoaded
  // ============================================================
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThemeLoader);
  } else {
    initThemeLoader();
  }

  // ============================================================
  // 🔹 RUN ON TURBO NAVIGATION (re-apply theme after page change)
  // ============================================================
  document.addEventListener('turbo:load', () => {
    log('INFO', '🔄 Turbo:load - Re-applying theme');
    initThemeLoader();
  });

  // ============================================================
  // 🔹 TURBO: Apply theme BEFORE render (prevent flash)
  // ============================================================
  document.addEventListener('turbo:before-render', (event) => {
    const theme = getTheme();
    const newBody = event.detail.newBody;
    if (newBody) {
      newBody.classList.remove('ppv-light', 'ppv-dark');
      newBody.classList.add(`ppv-${theme}`);
      log('DEBUG', '⚡ Turbo:before-render - Applied theme to new body:', theme);
    }
  });

  // ============================================================
  // 🔹 EARLY INIT (before DOMContentLoaded) - First page load only
  // ============================================================
  // Apply theme ASAP (avoid flash)
  const earlyTheme = getTheme();
  loadThemeCSS(earlyTheme);
  document.documentElement.setAttribute('data-theme', earlyTheme);
  if (document.body) {
    document.body.classList.remove('ppv-light', 'ppv-dark');
    document.body.classList.add(`ppv-${earlyTheme}`);
  }
  log('INFO', `⚡ Early theme load: ${earlyTheme}`);

  // ============================================================
  // 🔹 STORAGE EVENT (sync with other windows)
  // ============================================================
  window.addEventListener('storage', (e) => {
    if (e.key === THEME_KEY && e.newValue) {
      const newTheme = e.newValue;
      log('INFO', '🔄 Storage event: theme changed', newTheme);
      loadThemeCSS(newTheme, true);
    }
  });
})();
