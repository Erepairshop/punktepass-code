/**
 * PunktePass – Bottom Nav v4.0 (Turbo Frames Edition)
 *
 * Features:
 * - Turbo Frames SPA navigation (instant content swap, no page refresh)
 * - Same-page navigation skip (prevents unnecessary re-renders)
 * - Improved debounce and throttle
 * - Global navigation state sync
 * - Safari optimizations
 *
 * v4.0 Changes:
 * - Turbo Frames support for vendor pages (instant ~100ms navigation)
 * - Better active state handling for frame navigation
 */
jQuery(document).ready(function ($) {

  const currentPath = window.location.pathname.replace(/\/+$/, "");
  let lastClickTime = 0;

  // Global navigation state (shared with other PPV scripts)
  window.PPV_NAV_STATE = window.PPV_NAV_STATE || { isNavigating: false };

  // Detect Safari
  const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);

  // User dashboard pages - these use Turbo for SPA navigation
  const userPages = ['/user_dashboard', '/meine-punkte', '/belohnungen', '/einstellungen', '/punkte'];

  // Vendor/Handler pages - also use Turbo for SPA navigation
  const vendorPages = ['/qr-center', '/mein-profil', '/rewards', '/statistik', '/profile-lite'];

  const isUserPagePath = (path) => {
    const cleanPath = path.replace(/\/+$/, "");
    return userPages.some(p => cleanPath === p || cleanPath.startsWith(p + '/'));
  };

  const isVendorPagePath = (path) => {
    const cleanPath = path.replace(/\/+$/, "");
    return vendorPages.some(p => cleanPath === p || cleanPath.startsWith(p + '/'));
  };

  const isUserPage = isUserPagePath(currentPath);
  const isVendorPage = isVendorPagePath(currentPath);

  // Mark active nav item
  const updateActiveNav = () => {
    const path = window.location.pathname.replace(/\/+$/, "");
    $(".ppv-bottom-nav .nav-item").each(function () {
      const href = $(this).attr("href").replace(/\/+$/, "");
      $(this).toggleClass("active", path === href);
    });
  };

  updateActiveNav();

  // Touch feedback (passive for performance)
  $(".ppv-bottom-nav .nav-item").each(function() {
    this.addEventListener("touchstart", function() {
      $(this).addClass("touch");
    }, { passive: true });

    this.addEventListener("touchend", function() {
      $(this).removeClass("touch");
    }, { passive: true });
  });

  $(".ppv-bottom-nav .nav-item").on("mousedown", function () {
    $(this).addClass("touch");
  }).on("mouseup mouseleave", function () {
    $(this).removeClass("touch");
  });

  // Navigation click handler
  $(".ppv-bottom-nav .nav-item").on("click", function (e) {
    // 📳 Haptic feedback removed - pages handle their own feedback to avoid double vibration

    const href = $(this).attr("href");
    if (!href || href === '#') return;

    const targetPath = href.replace(/\/+$/, "");
    const currentPathNow = window.location.pathname.replace(/\/+$/, "");

    // SKIP if already on the same page
    if (targetPath === currentPathNow) {
      e.preventDefault();
      // Just scroll to top instead
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

    // Debounce rapid clicks (300ms)
    const now = Date.now();
    if (now - lastClickTime < 300) {
      e.preventDefault();
      return;
    }
    lastClickTime = now;

    // Block if navigation already in progress
    if (window.PPV_NAV_STATE.isNavigating) {
      e.preventDefault();
      return;
    }

    const isTargetUserPage = isUserPagePath(targetPath);
    const isTargetVendorPage = isVendorPagePath(targetPath);

    // 🚀 Vendor pages use Turbo Frames (instant navigation via data-turbo-frame attribute)
    if (isVendorPage && isTargetVendorPage) {
      window.PPV_NAV_STATE.isNavigating = true;
      $(this).addClass("navigating");

      // Immediately update active state for instant feedback
      $(".ppv-bottom-nav .nav-item").removeClass("active");
      $(this).addClass("active");

      // Reset navigation state after frame loads (or timeout as fallback)
      setTimeout(() => {
        window.PPV_NAV_STATE.isNavigating = false;
        $(".ppv-bottom-nav .nav-item").removeClass("navigating");
      }, 500);

      // Let Turbo Frames handle the navigation (don't preventDefault)
      return;
    }

    // User pages use Turbo Drive (standard SPA navigation)
    if (isUserPage && isTargetUserPage) {
      window.PPV_NAV_STATE.isNavigating = true;
      $(this).addClass("navigating");

      // Reset navigation state after timeout (safety)
      const throttleTime = isSafari ? 600 : 1000;
      setTimeout(() => {
        window.PPV_NAV_STATE.isNavigating = false;
        $(".ppv-bottom-nav .nav-item").removeClass("navigating");
      }, throttleTime);

      // Let Turbo Drive handle the navigation (don't preventDefault)
      return;
    }

    // Cross-type navigation (user↔vendor) = full page refresh
    e.preventDefault();
    window.PPV_NAV_STATE.isNavigating = true;
    $(this).addClass("navigating");

    if (isSafari) {
      setTimeout(() => {
        window.location.href = href;
      }, 50);
    } else {
      window.location.href = href;
    }
  });

  // Turbo Drive event handlers (for user pages)
  document.addEventListener('turbo:load', function() {
    window.PPV_NAV_STATE.isNavigating = false;
    $(".ppv-bottom-nav .nav-item").removeClass("navigating");
    updateActiveNav();
  });

  document.addEventListener('turbo:before-visit', function() {
    window.PPV_NAV_STATE.isNavigating = true;
  });

  // 🚀 Turbo Frames event handlers (for vendor pages - instant navigation)
  document.addEventListener('turbo:frame-load', function(e) {
    if (e.target.id === 'main-content') {
      window.PPV_NAV_STATE.isNavigating = false;
      $(".ppv-bottom-nav .nav-item").removeClass("navigating");
      updateActiveNav();
    }
  });

  document.addEventListener('turbo:before-frame-render', function(e) {
    if (e.target.id === 'main-content') {
      window.PPV_NAV_STATE.isNavigating = true;
    }
  });

  // Handle browser back/forward buttons
  window.addEventListener('popstate', function() {
    setTimeout(updateActiveNav, 100);
  });
});
