// PunktePass HiddenScan Service Worker v2.1 (Final Relay Fix)
// ✅ REST Relay → BroadcastChannel → User Toast Bridge
// ✅ Cross-tab + PWA kompatibilis
// ✅ Offline-safe queue (future use)
//
// ⛔ DISABLED - Not in use

self.addEventListener("install", (e) => {
  self.skipWaiting();
});

self.addEventListener("activate", (e) => {
  return self.clients.claim();
});

// ============================================================
// 🔁 QR üzenet feldolgozása
// ============================================================
self.addEventListener("message", async (e) => {
  // ⛔ DISABLED - Not in use
  return;

  const data = e.data || {};
  if (!data.qr) return;

  const code = data.qr;
  const storeKey = data.store_key || "";
  const lang = data.lang || "de";


  try {
    // --- POS Scan REST hívás ---
    const res = await fetch("/wp-json/punktepass/v1/pos/scan", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "PPV-POS-Token": storeKey,
      },
      body: JSON.stringify({ qr: code, store_key: storeKey, lang }),
    });

    const result = await res.json();

    // --- csak siker esetén broadcast ---
    if (result.success) {
      const payload = {
        type: "ppv-scan-success",
        points: result.points || 1,
        store: result.store_name || "PunktePass",
        time: Date.now(),
        target: "user",
      };

      // 1️⃣ BroadcastChannel (cross-tab)
      try {
        const bc = new BroadcastChannel("punktepass_scans");
        bc.postMessage(payload);
        bc.close();
      } catch (err) {
        console.warn("⚠️ BroadcastChannel failed:", err);
      }

      // 2️⃣ Client message relay (controlled pages)
      const allClients = await self.clients.matchAll({ includeUncontrolled: true });
      for (const client of allClients) {
        client.postMessage(payload);
      }


      // 3️⃣ ✅ Force relay – minden tabnak, még uncontrolled esetben is
      try {
        const all = await self.clients.matchAll({ includeUncontrolled: true, type: "window" });
        for (const c of all) {
          c.postMessage({
            type: "ppv-scan-success",
            points: payload.points,
            store: payload.store,
            source: "HiddenScanForce",
          });
        }
      } catch (err) {
        console.warn("⚠️ Forced relay failed:", err);
      }

    } else {
      console.warn("⚠️ PunktePass scan failed:", result.message);
    }
  } catch (err) {
    console.error("❌ SW relay error:", err);
  }
});
