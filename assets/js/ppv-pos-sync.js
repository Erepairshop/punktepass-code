/**
 * PunktePass – Offline Sync Handler (v1.4 Production Safe)
 * ✅ Offline Speicherung lokaler Scans
 * ✅ Auto-Sync bei Online-Rückkehr
 * ✅ Duplicate-Schutz
 * ✅ Token Memory Fix
 * ✅ Sync-Queue Freeze Fix
 */

(function($) {
  const STORAGE_KEY = "ppv_offline_scans";

  /** ✅ Token Memory Safe (PWA reload fix) */
  const POS_TOKEN =
    (window.PPV_STORE_KEY || "").trim() ||
    (sessionStorage.getItem("ppv_store_key") || "").trim() ||
    "";

  /** 🛰️ Offline Status Banner */
  function updateOfflineStatus() {
    if (!navigator.onLine) {
      $("#ppv-offline-banner").fadeIn(200);
      showStatus("🛰️ Offline-Modus aktiv – Scans werden lokal gespeichert", "orange");
    } else {
      $("#ppv-offline-banner").fadeOut(200);
      syncOfflineScans();
    }
  }

  /** 💾 Lokales Speichern eines Scans (Offline) */
  async function saveOfflineScan(data) {
    let scans = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");

    // Duplicate Schutz – gleicher QR + Store
    const exists = scans.some(s =>
      s.qr === data.qr &&
      s.store_key === data.store_key
    );
    if (exists) {
      showStatus("⚠️ QR bereits lokal gespeichert", "gray");
      return;
    }

    scans.push({
      ...data,
      saved_at: new Date().toISOString()
    });

    localStorage.setItem(STORAGE_KEY, JSON.stringify(scans));
    showStatus("📦 Scan offline gespeichert", "orange");
  }

  /** 🔄 Synchronisation wenn online */
  async function syncOfflineScans(manual = false) {
    let scans = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");

    if (scans.length === 0) {
      if (manual) showStatus("ℹ️ Keine Offline-Scans vorhanden", "gray");
      return;
    }

    showStatus(`🔄 Synchronisiere ${scans.length} Scans...`, "blue");

    try {
      const res = await fetch("/wp-json/punktepass/v1/pos/sync_offline", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "PPV-POS-Token": POS_TOKEN
        },
        body: JSON.stringify({
          scans,
          store_key: POS_TOKEN
        })
      });

      const result = await res.json();

      if (result.success) {
        showStatus(`✅ ${result.synced} Scans erfolgreich synchronisiert`, "green");
        localStorage.removeItem(STORAGE_KEY);
      } else {
        showStatus("⚠️ Synchronisation fehlgeschlagen", "red");
      }

    } catch (err) {
      console.error("❌ Sync fehlgeschlagen", err);
      showStatus("🚫 Keine Verbindung – später erneut versuchen", "red");
    }
  }

  /** 💬 Statusanzeige im POS */
  function showStatus(msg, color = "gray") {
    let box = $("#ppv-pos-result");
    if (!box.length) return;
    box.html(`<div style="color:${color};font-weight:500;">${msg}</div>`);
  }

  /** 🌐 Online / Offline Events */
  window.addEventListener("online", updateOfflineStatus);
  window.addEventListener("offline", updateOfflineStatus);

  /** 🖱️ Manueller Sync Button */
  $(document).on("click", "#ppv-sync-btn", () => syncOfflineScans(true));

  /** 📍 GPS Position für Fraud Detection */
  let gpsPosition = null;

  function initGpsTracking() {
    if (!navigator.geolocation) return;

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        gpsPosition = { latitude: pos.coords.latitude, longitude: pos.coords.longitude, ts: Date.now() };
      },
      () => { gpsPosition = null; },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    );

    navigator.geolocation.watchPosition(
      (pos) => { gpsPosition = { latitude: pos.coords.latitude, longitude: pos.coords.longitude, ts: Date.now() }; },
      () => {},
      { enableHighAccuracy: false, timeout: 30000, maximumAge: 60000 }
    );
  }

  function getGps() {
    if (gpsPosition && (Date.now() - gpsPosition.ts) < 120000) {
      return { latitude: gpsPosition.latitude, longitude: gpsPosition.longitude };
    }
    return { latitude: null, longitude: null };
  }

  // Start GPS tracking on load
  initGpsTracking();

  /** 🎯 POS Scan Handler */
  $(document).on("ppv:scan", async function(e, scanData) {
    if (!scanData || !scanData.qr || !POS_TOKEN) {
      console.warn("⚠️ Ungültige Scan-Daten:", scanData);
      return;
    }

    // Wenn offline → lokal speichern
    if (!navigator.onLine) {
      await saveOfflineScan(scanData);
      return;
    }

    // Get GPS for fraud detection
    const gps = getGps();

    try {
      const res = await fetch("/wp-json/punktepass/v1/pos/scan", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "PPV-POS-Token": POS_TOKEN
        },
        body: JSON.stringify({
          qr: scanData.qr,
          store_key: POS_TOKEN,
          points_add: scanData.points_add || 1,
          latitude: gps.latitude,
          longitude: gps.longitude
        })
      });

      const result = await res.json();

      if (result.success) {
        showStatus(result.message || "✅ Scan erfolgreich", "green");
      } else {
        showStatus(result.message || "⚠️ Scan-Fehler – lokal gespeichert", "red");
        await saveOfflineScan(scanData);
      }

    } catch (err) {
      console.error("❌ Scan Fehler:", err);
      showStatus("🚫 Netzwerkfehler – lokal gespeichert", "orange");
      await saveOfflineScan(scanData);
    }
  });

  /** 🚀 Init */
  $(document).ready(updateOfflineStatus);

})(jQuery);
