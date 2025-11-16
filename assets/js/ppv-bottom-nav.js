/**
 * PunktePass – Bottom Nav (Active + Lang Sync)
 */
jQuery(document).ready(function ($) {
  console.log("✅ Bottom Nav aktiv");

  const currentPath = window.location.pathname.replace(/\/+$/, "");

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
});
