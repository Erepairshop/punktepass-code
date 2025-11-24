/**
 * PunktePass – Bottom Nav (Active + Lang Sync)
 * FIX: Added throttle to prevent rapid clicks causing DB overload
 */
jQuery(document).ready(function ($) {
  console.log("✅ Bottom Nav aktiv");

  const currentPath = window.location.pathname.replace(/\/+$/, "");
  let isNavigating = false; // Throttle flag

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

  // Force full page refresh on nav click (no Turbo)
  // FIX: Throttle to prevent multiple rapid clicks
  $(".ppv-bottom-nav .nav-item").on("click", function (e) {
    e.preventDefault();

    // Block rapid clicks
    if (isNavigating) return;

    const href = $(this).attr("href");
    if (href) {
      isNavigating = true;
      $(this).addClass("navigating");
      window.location.href = href;
    }
  });
});
