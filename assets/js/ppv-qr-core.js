/**
 * PunktePass QR Scanner - Core Module
 * Contains: Debug, State, GPS, Store Config, Toast, Sounds, Helpers
 */
(function() {
  'use strict';

  // Guard against multiple script loads
  if (window.PPV_QR_CORE_LOADED) return;
  window.PPV_QR_CORE_LOADED = true;

  // ============================================================
  // DEBUG MODE
  // ============================================================
  const PPV_DEBUG = false;
  const ppvLog = (...args) => { if (PPV_DEBUG) console.log(...args); };
  const ppvWarn = (...args) => { if (PPV_DEBUG) console.warn(...args); };

  // ============================================================
  // GLOBAL STATE
  // ============================================================
  const STATE = {
    initialized: false,
    campaignManager: null,
    cameraScanner: null,
    scanProcessor: null,
    uiManager: null,
    lastInitTime: 0,
    ablySubscriberId: null,
    pollInterval: null,
    gpsPosition: null,
    gpsWatchId: null,
    visibilityHandler: null
  };

  // ============================================================
  // LANGUAGE STRINGS
  // ============================================================
  const L = window.ppv_lang || {};

  // ============================================================
  // GPS LOCATION TRACKING
  // ============================================================
  function initGpsTracking() {
    if (!navigator.geolocation) {
      ppvLog('[GPS] Geolocation not supported');
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        STATE.gpsPosition = {
          latitude: pos.coords.latitude,
          longitude: pos.coords.longitude,
          accuracy: pos.coords.accuracy,
          timestamp: Date.now()
        };
        ppvLog('[GPS] Position acquired:', STATE.gpsPosition.latitude.toFixed(4), STATE.gpsPosition.longitude.toFixed(4));
      },
      (err) => {
        ppvWarn('[GPS] Position error:', err.message);
        STATE.gpsPosition = null;
      },
      { enableHighAccuracy: true, timeout: 30000, maximumAge: 120000 }
    );

    STATE.gpsWatchId = navigator.geolocation.watchPosition(
      (pos) => {
        STATE.gpsPosition = {
          latitude: pos.coords.latitude,
          longitude: pos.coords.longitude,
          accuracy: pos.coords.accuracy,
          timestamp: Date.now()
        };
        ppvLog('[GPS] Position updated:', STATE.gpsPosition.latitude.toFixed(4), STATE.gpsPosition.longitude.toFixed(4));
      },
      (err) => { ppvWarn('[GPS] Watch error:', err.message); },
      { enableHighAccuracy: false, timeout: 60000, maximumAge: 120000 }
    );
  }

  function stopGpsTracking() {
    if (STATE.gpsWatchId !== null) {
      navigator.geolocation.clearWatch(STATE.gpsWatchId);
      STATE.gpsWatchId = null;
      ppvLog('[GPS] Watch stopped');
    }
  }

  function getGpsCoordinates() {
    if (STATE.gpsPosition && (Date.now() - STATE.gpsPosition.timestamp) < 120000) {
      return {
        latitude: STATE.gpsPosition.latitude,
        longitude: STATE.gpsPosition.longitude
      };
    }
    return { latitude: null, longitude: null };
  }

  function calculateGpsDistance(lat1, lng1, lat2, lng2) {
    const earthRadius = 6371000;
    const lat1Rad = lat1 * Math.PI / 180;
    const lat2Rad = lat2 * Math.PI / 180;
    const deltaLat = (lat2 - lat1) * Math.PI / 180;
    const deltaLng = (lng2 - lng1) * Math.PI / 180;

    const a = Math.sin(deltaLat / 2) * Math.sin(deltaLat / 2) +
              Math.cos(lat1Rad) * Math.cos(lat2Rad) *
              Math.sin(deltaLng / 2) * Math.sin(deltaLng / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return Math.round(earthRadius * c);
  }

  async function checkGpsGeofence(storeGps) {
    if (!storeGps || !storeGps.store_lat || !storeGps.store_lng) {
      ppvLog('[GPS] Store has no coordinates, skipping geofence check');
      return { allowed: true, skipped: 'no_store_gps' };
    }

    const deviceGps = getGpsCoordinates();

    if (!deviceGps.latitude || !deviceGps.longitude) {
      ppvLog('[GPS] No cached GPS, requesting position...');
      try {
        const position = await new Promise((resolve, reject) => {
          if (!navigator.geolocation) {
            reject(new Error('Geolocation not supported'));
            return;
          }
          navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true,
            timeout: 30000,
            maximumAge: 120000
          });
        });

        STATE.gpsPosition = {
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy,
          timestamp: Date.now()
        };

        deviceGps.latitude = position.coords.latitude;
        deviceGps.longitude = position.coords.longitude;
        ppvLog('[GPS] Position acquired:', deviceGps.latitude.toFixed(4), deviceGps.longitude.toFixed(4));
      } catch (err) {
        ppvWarn('[GPS] Failed to get position:', err.message);
        return {
          allowed: false,
          message: L.gps_permission_required || 'GPS-Standortzugriff ist erforderlich. Bitte aktivieren Sie GPS und erteilen Sie die Berechtigung.',
          reason: 'gps_unavailable'
        };
      }
    }

    const distance = calculateGpsDistance(
      parseFloat(storeGps.store_lat),
      parseFloat(storeGps.store_lng),
      deviceGps.latitude,
      deviceGps.longitude
    );

    const maxDistance = storeGps.max_distance || 500;

    ppvLog('[GPS] Distance check:', {
      storeLocation: { lat: storeGps.store_lat, lng: storeGps.store_lng },
      deviceLocation: { lat: deviceGps.latitude.toFixed(4), lng: deviceGps.longitude.toFixed(4) },
      distance: distance + 'm',
      maxAllowed: maxDistance + 'm'
    });

    if (distance <= maxDistance) {
      return { allowed: true, distance: distance };
    }

    return {
      allowed: false,
      message: (L.gps_too_far || 'Sie befinden sich zu weit vom Geschäft entfernt ({distance}m). Maximale Entfernung: {max}m')
        .replace('{distance}', distance)
        .replace('{max}', maxDistance),
      distance: distance,
      maxDistance: maxDistance,
      reason: 'gps_distance'
    };
  }

  // ============================================================
  // STORE CONFIG
  // ============================================================
  function getStoreKey() {
    return (window.PPV_STORE_KEY || '').trim() ||
           (window.PPV_STORE_DATA?.store_key || '').trim() ||
           (sessionStorage.getItem('ppv_store_key') || '').trim() || '';
  }

  function getStoreID() {
    return window.PPV_STORE_ID ||
           window.PPV_STORE_DATA?.store_id ||
           Number(sessionStorage.getItem('ppv_store_id')) || 0;
  }

  function getScannerId() {
    return window.PPV_STORE_DATA?.scanner_id ||
           Number(sessionStorage.getItem('ppv_scanner_id')) || null;
  }

  function getScannerName() {
    return window.PPV_STORE_DATA?.scanner_name ||
           sessionStorage.getItem('ppv_scanner_name') || null;
  }

  // Save to session
  if (getStoreKey()) sessionStorage.setItem('ppv_store_key', getStoreKey());
  if (getStoreID()) sessionStorage.setItem('ppv_store_id', getStoreID());
  if (getScannerId()) sessionStorage.setItem('ppv_scanner_id', getScannerId());
  if (getScannerName()) sessionStorage.setItem('ppv_scanner_name', getScannerName());

  // ============================================================
  // TOAST
  // ============================================================
  window.ppvToast = function(msg, type = 'info') {
    const box = document.createElement('div');
    box.className = 'ppv-toast ' + type;
    box.textContent = msg;
    document.body.appendChild(box);
    setTimeout(() => box.classList.add('show'), 10);
    setTimeout(() => box.classList.remove('show'), 3000);
    setTimeout(() => box.remove(), 3500);
  };

  // ============================================================
  // SCAN THROTTLE
  // ============================================================
  let lastScanTime = 0;
  function canProcessScan() {
    const now = Date.now();
    if (now - lastScanTime < 600) return false;
    lastScanTime = now;
    return true;
  }

  // ============================================================
  // SOUND EFFECTS
  // ============================================================
  const SOUNDS = { success: null, error: null };

  function preloadSounds() {
    try {
      const baseUrl = window.PPV_ASSETS_URL || '/wp-content/plugins/punktepass/assets';
      SOUNDS.success = new Audio(`${baseUrl}/sounds/scan-beep.wav`);
      SOUNDS.error = new Audio(`${baseUrl}/sounds/error.mp3`);
      SOUNDS.success.load();
      SOUNDS.error.load();
      ppvLog('[Sound] Sounds preloaded');
    } catch (e) {
      ppvWarn('[Sound] Failed to preload sounds:', e);
    }
  }

  function playSound(type) {
    try {
      const sound = SOUNDS[type];
      if (sound) {
        sound.currentTime = 0;
        sound.play().catch(() => {});
      }
    } catch (e) {}
  }

  // ============================================================
  // HTML ESCAPE (XSS Prevention)
  // ============================================================
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // ============================================================
  // STATUS BADGE
  // ============================================================
  function statusBadge(state) {
    const badges = {
      active: `<span style='color:#00e676'>🟢 ${L.state_active || 'Aktiv'}</span>`,
      archived: `<span style='color:#ffab00'>📦 ${L.state_archived || 'Archiviert'}</span>`,
      upcoming: `<span style='color:#2979ff'>🔵 ${L.state_upcoming || 'Geplant'}</span>`,
      expired: `<span style='color:#9e9e9e'>⚫ ${L.state_expired || 'Abgelaufen'}</span>`
    };
    return badges[state] || '';
  }

  // ============================================================
  // EXPORT TO GLOBAL NAMESPACE
  // ============================================================
  window.PPV_QR = {
    // Debug
    DEBUG: PPV_DEBUG,
    log: ppvLog,
    warn: ppvWarn,

    // State
    STATE: STATE,
    L: L,

    // GPS
    initGpsTracking: initGpsTracking,
    stopGpsTracking: stopGpsTracking,
    getGpsCoordinates: getGpsCoordinates,
    calculateGpsDistance: calculateGpsDistance,
    checkGpsGeofence: checkGpsGeofence,

    // Store Config
    getStoreKey: getStoreKey,
    getStoreID: getStoreID,
    getScannerId: getScannerId,
    getScannerName: getScannerName,

    // Utilities
    canProcessScan: canProcessScan,
    preloadSounds: preloadSounds,
    playSound: playSound,
    escapeHtml: escapeHtml,
    statusBadge: statusBadge
  };

  ppvLog('[QR-Core] Module loaded');

})();
