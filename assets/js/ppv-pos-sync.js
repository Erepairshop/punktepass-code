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

  /** 📱 Device Fingerprint for Fraud Detection */
  let deviceFingerprint = null;

  // Generate fallback fingerprint IMMEDIATELY (synchronous)
  function generateFallbackFingerprint() {
    try {
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      ctx.textBaseline = 'top';
      ctx.font = '14px Arial';
      ctx.fillText('fingerprint', 0, 0);
      const data = canvas.toDataURL() + navigator.userAgent + screen.width + screen.height + navigator.language + (new Date()).getTimezoneOffset();
      let hash1 = 0, hash2 = 0;
      for (let i = 0; i < data.length; i++) {
        hash1 = ((hash1 << 5) - hash1) + data.charCodeAt(i);
        hash1 = hash1 & hash1;
        hash2 = ((hash2 << 7) - hash2) + data.charCodeAt(i);
        hash2 = hash2 & hash2;
      }
      const fp = 'fp_' + Math.abs(hash1).toString(16).padStart(8, '0') + Math.abs(hash2).toString(16).padStart(8, '0');
      console.log('📱 [POS-Sync] Generated fingerprint:', fp);
      return fp;
    } catch (e) {
      console.error('📱 [POS-Sync] Fingerprint generation error:', e);
      return null;
    }
  }

  // Initialize fallback immediately (before any scan can happen)
  deviceFingerprint = generateFallbackFingerprint();
  console.log('📱 [POS-Sync] Device fingerprint initialized:', deviceFingerprint);

  // Try to upgrade to FingerprintJS if available (async)
  (async function() {
    try {
      if (window.FingerprintJS) {
        const fp = await FingerprintJS.load();
        const result = await fp.get();
        deviceFingerprint = result.visitorId;
      }
    } catch (e) { /* keep fallback */ }
  })();

  function getDeviceFingerprint() {
    return deviceFingerprint || null;
  }

  /** 👤 Scanner Info (employee who is scanning) */
  function getScannerId() {
    return window.PPV_STORE_DATA?.scanner_id ||
           Number(sessionStorage.getItem('ppv_scanner_id')) || null;
  }

  function getScannerName() {
    return window.PPV_STORE_DATA?.scanner_name ||
           sessionStorage.getItem('ppv_scanner_name') || null;
  }

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
    const fp = getDeviceFingerprint();

    // 🔍 DEBUG: Log what we're sending
    console.log('📱 [POS-Sync] Sending scan with:', {
      scanner_id: getScannerId(),
      device_fingerprint: fp,
      gps: gps
    });

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
          longitude: gps.longitude,
          scanner_id: getScannerId(),
          scanner_name: getScannerName(),
          device_fingerprint: fp
        })
      });

      const result = await res.json();

      if (result.success) {
        // 📳 Haptic feedback on scan success
        if (window.ppvHaptic) window.ppvHaptic('scan');
        showStatus(result.message || "✅ Scan erfolgreich", "green");
      } else {
        // 📳 Haptic feedback on error
        if (window.ppvHaptic) window.ppvHaptic('error');
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
