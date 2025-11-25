/**
 * PunktePass – Bottom Nav (Active + Lang Sync)
 * FIX: Added throttle to prevent rapid clicks causing DB overload
 * FIX: User dashboard pages use Turbo (no full reload)
 */
jQuery(document).ready(function ($) {
  console.log("✅ Bottom Nav aktiv");

  const currentPath = window.location.pathname.replace(/\/+$/, "");
  let isNavigating = false; // Throttle flag

  // User dashboard pages - these use Turbo for SPA navigation
  const userPages = ['/user_dashboard', '/meine-punkte', '/belohnungen', '/einstellungen', '/punkte'];
  const isUserPage = userPages.some(p => currentPath === p || currentPath.startsWith(p + '/'));

  $(".ppv-bottom-nav .nav-item").each(function () {
    const href = $(this).attr("href").replace(/\/+$/, "");
    if (currentPath === href) {
      $(this).addClass("active");
    }
  });

  // smooth icon hover feedback
  $(".ppv-bottom-nav .nav-item").on("touchstart mousedown", function () {
    $(this).addClass("touch");
  }).on("touchend mouseup", function () {
    $(this).removeClass("touch");
  });

  // Navigation click handler
  // User pages: Let Turbo handle it (SPA-style, no reload)
  // Other pages: Force full page refresh
  $(".ppv-bottom-nav .nav-item").on("click", function (e) {
    const href = $(this).attr("href");
    if (!href || href === '#') return;

    // Block rapid clicks
    if (isNavigating) {
      e.preventDefault();
      return;
    }

    const targetPath = href.replace(/\/+$/, "");
    const isTargetUserPage = userPages.some(p => targetPath === p || targetPath.startsWith(p + '/'));

    // If we're on a user page AND going to a user page, let Turbo handle it
    if (isUserPage && isTargetUserPage) {
      console.log("🚀 [Nav] Turbo navigation to:", href);
      isNavigating = true;
      $(this).addClass("navigating");
      // Don't preventDefault - let Turbo handle the click
      setTimeout(() => { isNavigating = false; }, 1000);
      return;
    }

    // Otherwise, force full page refresh (old behavior)
    e.preventDefault();
    isNavigating = true;
    $(this).addClass("navigating");
    window.location.href = href;
  });
});
