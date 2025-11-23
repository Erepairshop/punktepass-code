/**
 * PunktePass – Bottom Nav v2.1 (Active + Lang Sync)
 * FIXED: Event delegation to prevent listener duplication
 * FIXED: Turbo/SPA listeners only added once
 */
(function($) {
  'use strict';

  // Guard against multiple initializations
  if (window.PPV_BOTTOM_NAV_INITIALIZED) {
    console.log("⏭️ [Bottom Nav] Already initialized");
    return;
  }
  window.PPV_BOTTOM_NAV_INITIALIZED = true;

  console.log("✅ [Bottom Nav] v2.1 active");

  // Update active state
  function updateActiveState() {
    const currentPath = window.location.pathname.replace(/\/+$/, "");
    $(".ppv-bottom-nav .nav-item").each(function() {
      const href = $(this).attr("href")?.replace(/\/+$/, "") || "";
      $(this).toggleClass("active", currentPath === href);
    });
  }

  // Initial active state
  updateActiveState();

  // Event delegation for touch feedback (only one listener on document)
  // Using namespaced events to prevent duplicates
  $(document).off("touchstart.ppvnav mousedown.ppvnav").on("touchstart.ppvnav mousedown.ppvnav", ".ppv-bottom-nav .nav-item", function() {
    $(this).addClass("touch");
  });

  $(document).off("touchend.ppvnav mouseup.ppvnav mouseleave.ppvnav").on("touchend.ppvnav mouseup.ppvnav mouseleave.ppvnav", ".ppv-bottom-nav .nav-item", function() {
    $(this).removeClass("touch");
  });

  // Update active state on Turbo navigation (listeners are within IIFE guard)
  document.addEventListener('turbo:load', updateActiveState);

  // Update on custom SPA navigation
  window.addEventListener('ppv:spa-navigate', updateActiveState);

  // Update on popstate (back/forward)
  window.addEventListener('popstate', updateActiveState);

})(jQuery);
