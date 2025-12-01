/**
 * PunktePass – POS Redeem JS (v5.0 Unified Stable)
 * ✅ REST Token Auth (PPV-POS-Token)
 * ✅ Store/session fallback
 * ✅ Duplikált kattintás védelem
 * ✅ Offline detection
 * ✅ Tiszta UI + toast
 */


jQuery(document).ready(function ($) {

  /* ============================================================
   * 🌐 LANGUAGE DETECTION + TRANSLATIONS
   * ============================================================ */
  const detectLang = () => {
    return document.cookie.match(/ppv_lang=([a-z]{2})/)?.[1] ||
           localStorage.getItem('ppv_lang') || 'de';
  };
  const LANG = detectLang();
  const T = {
    de: { new_balance: 'Neuer Punktestand' },
    hu: { new_balance: 'Új egyenleg' },
    ro: { new_balance: 'Sold nou' }
  }[LANG] || { new_balance: 'Neuer Punktestand' };

  /* ============================================================
   * 🧠 STORE + TOKEN FALLBACK
   * ============================================================ */
  let storeID =
    parseInt(window.PPV_STORE_ID) ||
    parseInt(sessionStorage.getItem("ppv_store_id")) ||
    1;

  sessionStorage.setItem("ppv_store_id", String(storeID));

  let POS_TOKEN =
    (window.PPV_STORE_KEY || "").trim() ||
    (sessionStorage.getItem("ppv_store_key") || "").trim() ||
    "";

  if (window.PPV_STORE_KEY)
    sessionStorage.setItem("ppv_store_key", window.PPV_STORE_KEY);


  /* ============================================================
   * 🧩 TOAST FUNKCIÓ
   * ============================================================ */
  function showToast(msg, type = "info") {
    const t = $("<div class='ppv-toast " + type + "'>").text(msg);
    $("body").append(t);
    setTimeout(() => t.addClass("show"), 50);
    setTimeout(() => {
      t.removeClass("show");
      setTimeout(() => t.remove(), 400);
    }, 2600);
  }


  /* ============================================================
   * 🚫 Offline protection
   * ============================================================ */
  function offlineCheck() {
    if (!navigator.onLine) {
      const msg = window.ppvErrorMsg ? window.ppvErrorMsg('offline') : "📡 Offline – Redeem später versuchen";
      showToast(msg, "error");
      return true;
    }
    return false;
  }


  /* ============================================================
   * 💳 REWARD EINLÖSEN (POS)
   * ============================================================ */
  $(document).on("click", ".ppv-pos-redeem-btn", async function () {
    // 📳 Haptic feedback on button press
    if (window.ppvHaptic) window.ppvHaptic('button');

    const btn = $(this);
    const rewardID = Number(btn.data("id"));
    const userID = Number($("#ppv-pos-user-id").val().trim());

    if (!userID) {
      showToast("⚠️ Bitte zuerst User-ID eingeben!", "error");
      return;
    }

    if (offlineCheck()) return;

    // ⏳ Show loading state
    if (window.ppvBtnLoading) window.ppvBtnLoading(btn, true);

    try {
      const res = await fetch("/wp-json/punktepass/v1/pos/redeem", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "PPV-POS-Token": POS_TOKEN
        },
        body: JSON.stringify({
          store_id: storeID,
          user_id: userID,
          reward_id: rewardID
        })
      });

      const json = await res.json();

      if (json?.success) {
        // 📳 Haptic feedback on success
        if (window.ppvHaptic) window.ppvHaptic('reward');
        showToast(json.message || "✅ Erfolgreich eingelöst.", "success");

        if (json.new_balance !== undefined) {
          showToast("💰 " + T.new_balance + ": " + json.new_balance, "info");
        }

        setTimeout(() => location.reload(), 1200);

      } else {
        // 📳 Haptic feedback on error
        if (window.ppvHaptic) window.ppvHaptic('error');
        showToast(json?.message || "⚠️ Fehler beim Einlösen.", "error");
        // ⏳ Restore button
        if (window.ppvBtnLoading) window.ppvBtnLoading(btn, false, "💳 Einlösen");
      }

    } catch (err) {
      console.error("❌ Redeem Fehlschlag:", err);
      // 💬 User-friendly error message
      const errMsg = window.ppvErrorMsg ? window.ppvErrorMsg(err) : "⚠️ Serverfehler!";
      showToast(errMsg, "error");
      // ⏳ Restore button
      if (window.ppvBtnLoading) window.ppvBtnLoading(btn, false, "💳 Einlösen");
    }
  });

});
