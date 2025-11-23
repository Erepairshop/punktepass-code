/**
 * PunktePass – Global Controller v3.0
 * Turbo.js compatible, minimal overhead
 *
 * REMOVED: Manual prefetch (Turbo.js handles this)
 * REMOVED: Redundant event listeners
 */

(function() {
  'use strict';

  // Guard against multiple initializations
  if (window.PPV_GLOBAL_INITIALIZED) {
    return;
  }
  window.PPV_GLOBAL_INITIALIZED = true;

  console.log("✅ [PPV_GLOBAL] v3.0 active (Turbo-compatible)");

  // Service Worker status check (one-time)
  if ("serviceWorker" in navigator) {
    navigator.serviceWorker.ready
      .then(() => console.log("🟢 [PPV_SW] ready"))
      .catch(() => console.log("⚠️ [PPV_SW] not active"));
  }

  // Note: Turbo.js handles prefetching automatically via data-turbo-prefetch
  // No need for manual mouseenter listeners

})();
