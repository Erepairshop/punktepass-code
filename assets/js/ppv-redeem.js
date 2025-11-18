/**
 * PunktePass – POS Redeem JS (v5.0 Unified Stable)
 * ✅ REST Token Auth (PPV-POS-Token)
 * ✅ Store/session fallback
 * ✅ Duplikált kattintás védelem
 * ✅ Offline detection
 * ✅ Tiszta UI + toast
 */

console.log("✅ PunktePass POS Redeem JS v5.0 aktiv");

jQuery(document).ready(function ($) {

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
      showToast("📡 Offline – Redeem später versuchen", "error");
      return true;
    }
    return false;
  }


  /* ============================================================
   * 💳 REWARD EINLÖSEN (POS)
   * ============================================================ */
  $(document).on("click", ".ppv-pos-redeem-btn", async function () {

    const btn = $(this);
    const rewardID = Number(btn.data("id"));
    const userID = Number($("#ppv-pos-user-id").val().trim());

    if (!userID) {
      showToast("⚠️ Bitte zuerst User-ID eingeben!", "error");
      return;
    }

    if (offlineCheck()) return;

    btn.prop("disabled", true).text("⏳ ...");

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
        showToast(json.message || "✅ Erfolgreich eingelöst.", "success");

        if (json.new_balance !== undefined) {
          showToast("💰 Neuer Punktestand: " + json.new_balance, "info");
        }

        setTimeout(() => location.reload(), 1200);

      } else {
        showToast(json?.message || "⚠️ Fehler beim Einlösen.", "error");
        btn.prop("disabled", false).text("💳 Einlösen");
      }

    } catch (err) {
      console.error("❌ Redeem Fehlschlag:", err);
      showToast("⚠️ Serverfehler!", "error");
      btn.prop("disabled", false).text("💳 Einlösen");
    }
  });

});
