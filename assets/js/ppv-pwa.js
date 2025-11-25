/**
 * PunktePass – PWA Mode Detection v4.0
 *
 * SIMPLIFIED: Only handles PWA standalone mode detection.
 * Navigation is now handled by Turbo.js and ppv-spa-loader.js
 *
 * REMOVED: Link interception (was causing conflicts with Turbo.js)
 */

(function() {
  'use strict';

  // Guard against multiple runs
  if (window.PPV_PWA_INITIALIZED) {
    return;
  }
  window.PPV_PWA_INITIALIZED = true;

  // Detect standalone PWA mode
  const isStandalone = window.matchMedia("(display-mode: standalone)").matches ||
                       window.navigator.standalone ||
                       document.referrer.includes('android-app://');

  if (isStandalone) {
    document.body.classList.add("ppv-app-mode");
    console.log("📱 [PWA] Standalone mode detected");
  } else {
    console.log("🌐 [PWA] Browser mode");
  }

  // Listen for display mode changes (user installs PWA while using)
  window.matchMedia("(display-mode: standalone)").addEventListener('change', (e) => {
    if (e.matches) {
      document.body.classList.add("ppv-app-mode");
      console.log("📱 [PWA] Switched to standalone mode");
    }
  });

})();
