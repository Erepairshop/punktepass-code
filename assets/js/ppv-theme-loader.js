/**
 * PunktePass – Theme Loader v2.0 (UNIVERSAL)
 * ✅ Auto-detects all pages
 * ✅ Multi-domain cookie
 * ✅ MutationObserver for button detection
 * ✅ Service Worker messaging
 * ✅ Refresh memory
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
  // 🔹 LOAD CSS
  // ============================================================
  function loadThemeCSS(theme) {
    const id = 'ppv-theme-css';
    const href = `/wp-content/plugins/punktepass/assets/css/ppv-theme-${theme}.css?v=${Date.now()}`;

    // Remove old
    document.querySelectorAll(`link[id="${id}"]`).forEach(e => e.remove());

    // Create new
    const link = document.createElement('link');
    link.id = id;
    link.rel = 'stylesheet';
    link.href = href;
    link.onload = () => {
      log('INFO', '✅ Theme CSS loaded:', theme);
      document.documentElement.setAttribute('data-theme', theme);
      document.body.classList.remove('ppv-light', 'ppv-dark');
      document.body.classList.add(`ppv-${theme}`);
    };
    link.onerror = () => {
      log('ERROR', '❌ Theme CSS failed to load:', href);
    };

    document.head.appendChild(link);
  }

  // ============================================================
  // 🔹 GET THEME (Priority: DB > Cookie > Default)
  // ============================================================
  function getTheme() {
    // 1. Meta tag from PHP
    const meta = document.querySelector('meta[name="ppv-theme"]');
    if (meta) {
      const theme = meta.getAttribute('content');
      if (['dark', 'light'].includes(theme)) {
        log('DEBUG', 'Theme from meta tag:', theme);
        return theme;
      }
    }

    // 2. localStorage
    const saved = localStorage.getItem(THEME_KEY);
    if (saved && ['dark', 'light'].includes(saved)) {
      log('DEBUG', 'Theme from localStorage:', saved);
      return saved;
    }

    // 3. Cookie
    const cookie = getCookie(THEME_KEY);
    if (cookie && ['dark', 'light'].includes(cookie)) {
      log('DEBUG', 'Theme from cookie:', cookie);
      return cookie;
    }

    // 4. Default
    log('DEBUG', 'Using default theme: dark');
    return 'dark';
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

    // Set on current domain
    document.cookie = `${THEME_KEY}=${value};path=${path};expires=${expire.toUTCString()};SameSite=Lax;Secure`;

    log('DEBUG', '🍪 Cookie set:', value);
  }

  // ============================================================
  // 🔹 SYNC TO SERVER
  // ============================================================
  async function syncThemeToServer(theme) {
    try {
      const response = await fetch(API_URL + '/set', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': document.querySelector('meta[name="wp-nonce"]')?.content || '',
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
    const btn = document.getElementById('ppv-theme-toggle');
    if (!btn) {
      log('DEBUG', 'Button not found yet, will keep watching');
      return false;
    }

    log('INFO', '✅ Theme toggle button found, attaching listener');

    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();

      const current = document.documentElement.getAttribute('data-theme') || 'dark';
      const newTheme = current === 'dark' ? 'light' : 'dark';

      log('INFO', `🔄 Theme switching: ${current} → ${newTheme}`);

      // 1. Update UI immediately
      loadThemeCSS(newTheme);
      localStorage.setItem(THEME_KEY, newTheme);
      setMultiDomainCookie(newTheme);

      // 2. Sync to server (async)
      await syncThemeToServer(newTheme);

      // 3. Message Service Worker to clear cache
      if (navigator.serviceWorker?.controller) {
        navigator.serviceWorker.controller.postMessage({
          type: 'clear-theme-cache',
          theme: newTheme,
        });
        log('INFO', '✉️ SW message sent: clear-theme-cache');
      }

      // 4. Broadcast to other tabs
      if (typeof BroadcastChannel !== 'undefined') {
        try {
          const bc = new BroadcastChannel('ppv-theme-sync');
          bc.postMessage({ type: 'theme-changed', theme: newTheme });
          log('INFO', '📢 Broadcast sent: theme-changed');
        } catch (err) {
          log('DEBUG', 'BroadcastChannel not available');
        }
      }

      // 5. Haptic feedback
      if (navigator.vibrate) navigator.vibrate(20);
    });

    return true;
  }

  // ============================================================
  // 🔹 MUTATION OBSERVER (for dynamic buttons)
  // ============================================================
  function startMutationObserver() {
    const observer = new MutationObserver(() => {
      if (!document.getElementById('ppv-theme-toggle')) return;

      if (attachButtonListener()) {
        observer.disconnect();
        log('INFO', '✅ MutationObserver: Button found and attached');
      }
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });

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

          // Update UI
          loadThemeCSS(newTheme);
          localStorage.setItem(THEME_KEY, newTheme);
        }
      });

      log('DEBUG', 'BroadcastChannel listener started');
    } catch (err) {
      log('DEBUG', 'BroadcastChannel error:', err.message);
    }
  }

  // ============================================================
  // 🔹 MAIN INIT
  // ============================================================
  window.addEventListener('DOMContentLoaded', () => {
    log('INFO', '🚀 Theme Loader v2.0 initialized');

    // 1. Get current theme
    const theme = getTheme();
    log('INFO', `📍 Current theme: ${theme}`);

    // 2. Load CSS immediately
    loadThemeCSS(theme);

    // 3. Try to attach button (might already exist)
    if (!attachButtonListener()) {
      // Button doesn't exist yet, start watching
      startMutationObserver();
    }

    // 4. Listen for cross-tab broadcasts
    listenForBroadcasts();
  });

  // ============================================================
  // 🔹 EARLY INIT (before DOMContentLoaded)
  // ============================================================
  // Apply theme ASAP (avoid flash)
  const earlyTheme = getTheme();
  loadThemeCSS(earlyTheme);
  log('INFO', `⚡ Early theme load: ${earlyTheme}`);

  // ============================================================
  // 🔹 STORAGE EVENT (sync with other windows)
  // ============================================================
  window.addEventListener('storage', (e) => {
    if (e.key === THEME_KEY && e.newValue) {
      const newTheme = e.newValue;
      log('INFO', '🔄 Storage event: theme changed', newTheme);
      loadThemeCSS(newTheme);
    }
  });
})();