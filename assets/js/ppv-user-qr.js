/**
 * PunktePass – Timed QR with 30min Countdown
 * REST API + Auto Countdown + Refresh on Expiry
 * ✅ Offline Support - Cache QR for offline viewing
 * ✅ Local QR Generation - No external API dependency
 */

const QR_CACHE_KEY = 'ppv_qr_cache';
const STATIC_QR_CACHE_KEY = 'ppv_static_qr_cache';

// ═══════════════════════════════════════════════════════════════
// 🎨 LOCAL QR CODE GENERATION (using qrcode-generator library)
// ═══════════════════════════════════════════════════════════════

/**
 * Generate QR code as data URL (base64 PNG)
 * @param {string} text - The text/data to encode
 * @param {number} size - Output image size in pixels (default 300)
 * @returns {string} - data:image/png;base64,... URL
 */
function generateQRCodeDataURL(text, size = 300) {
  if (typeof qrcode === 'undefined') {
    console.error('qrcode-generator library not loaded!');
    return null;
  }

  try {
    // Type 0 = auto-detect, Error correction level L (7%)
    const qr = qrcode(0, 'M');
    qr.addData(text, 'Byte');
    qr.make();

    // Get module count to calculate cell size
    const moduleCount = qr.getModuleCount();
    const cellSize = Math.floor(size / moduleCount);
    const margin = Math.floor((size - (cellSize * moduleCount)) / 2);

    // Create canvas and draw QR code
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');

    // White background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, size, size);

    // Draw QR modules
    ctx.fillStyle = '#000000';
    for (let row = 0; row < moduleCount; row++) {
      for (let col = 0; col < moduleCount; col++) {
        if (qr.isDark(row, col)) {
          ctx.fillRect(
            margin + col * cellSize,
            margin + row * cellSize,
            cellSize,
            cellSize
          );
        }
      }
    }

    return canvas.toDataURL('image/png');
  } catch (e) {
    console.error('QR generation error:', e);
    return null;
  }
}
let countdownInterval = null;
let expiresAt = null;
let currentUserId = null;
let wakeLock = null;

// ═══════════════════════════════════════════════════════════════
// 🔆 BRIGHTNESS BOOST - Wake Lock API (prevents screen dimming)
// ═══════════════════════════════════════════════════════════════
async function requestWakeLock() {
  try {
    if ('wakeLock' in navigator) {
      wakeLock = await navigator.wakeLock.request('screen');
      console.log('🔆 Wake Lock activated - screen stays bright');
    }
  } catch (e) {
    console.log('Wake Lock not available:', e.message);
  }
}

function releaseWakeLock() {
  if (wakeLock) {
    wakeLock.release();
    wakeLock = null;
    console.log('🔅 Wake Lock released');
  }
}

// ═══════════════════════════════════════════════════════════════
// 📺 FULLSCREEN API
// ═══════════════════════════════════════════════════════════════
function enterFullscreen(element) {
  if (element.requestFullscreen) {
    element.requestFullscreen();
  } else if (element.webkitRequestFullscreen) {
    element.webkitRequestFullscreen(); // iOS Safari
  } else if (element.msRequestFullscreen) {
    element.msRequestFullscreen();
  }
}

function exitFullscreen() {
  if (document.exitFullscreen) {
    document.exitFullscreen();
  } else if (document.webkitExitFullscreen) {
    document.webkitExitFullscreen(); // iOS Safari
  } else if (document.msExitFullscreen) {
    document.msExitFullscreen();
  }
}

function isFullscreen() {
  return !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
}

document.addEventListener("DOMContentLoaded", async () => {
  const qrBox = document.querySelector(".ppv-user-qr");
  if (!qrBox) return;

  const userId = qrBox.getAttribute("data-user-id");
  currentUserId = userId;
  if (!userId) {
    showStatus("⚠️ User ID fehlt", "error");
    return;
  }

  // Check online status
  if (!navigator.onLine) {
    // Try to load from cache when offline
    const cached = loadFromCache(userId);
    const now = Math.floor(Date.now() / 1000);

    // Check if timed QR is still valid
    if (cached && !cached._isExpired && cached.expires_at > now) {
      showOfflineBanner();
      displayQR(cached);
      startCountdown(cached.expires_at);
      showStatus("📱 Offline-Modus - Gespeicherter QR-Code", "success");
    } else {
      // 🔐 STATIC QR FALLBACK - Timed QR expired or unavailable
      const staticQR = loadStaticQRFromCache(userId);
      if (staticQR && staticQR.qr_value) {
        showOfflineBanner();
        displayQR(staticQR);
        // Hide timer for static QR
        const timerEl = document.getElementById("ppvQrTimer");
        if (timerEl) timerEl.style.display = "none";
        showStatus("📱 Offline - Tages-QR (1x pro Geschäft)", "warning");
      } else if (cached) {
        // Fallback: show expired timed QR if no static available
        showOfflineBanner();
        displayQR(cached);
        showStatus("⏰ Offline - QR-Code abgelaufen", "warning");
      } else {
        showStatus("📡 Offline - Bitte einmal online laden", "error");
        hideLoading();
      }
    }
    // Don't return - still setup refresh button for when back online
  } else {
    // Load initial timed QR (only when online)
    await loadTimedQR(userId);
    // 🔐 Cache static QR for offline fallback (runs in background)
    fetchAndCacheStaticQR(userId);
  }

  // Refresh button click handler
  const refreshBtn = document.getElementById("ppvBtnRefresh");
  if (refreshBtn) {
    refreshBtn.addEventListener("click", async () => {
      // 📳 Haptic feedback on refresh
      if (window.ppvHaptic) window.ppvHaptic('button');
      // ⏳ Show loading state
      if (window.ppvBtnLoading) window.ppvBtnLoading(refreshBtn, true);
      await loadTimedQR(userId, true);
      // ⏳ Restore button
      if (window.ppvBtnLoading) window.ppvBtnLoading(refreshBtn, false);
    });
  }

  // Copy to clipboard on input click
  const qrValueInput = document.getElementById("ppvQrValue");
  if (qrValueInput) {
    qrValueInput.addEventListener("click", (e) => {
      navigator.clipboard.writeText(e.target.value);
      // 📳 Haptic feedback on copy
      if (window.ppvHaptic) window.ppvHaptic('success');
      showStatus("📋 QR-Code kopiert!", "success");
    });
  }

  // 📺 Fullscreen button handler - CSS zoom for iOS compatibility
  const fullscreenBtn = document.getElementById("ppvQrFullscreenBtn");
  if (fullscreenBtn) {
    let isZoomed = false;

    fullscreenBtn.addEventListener("click", (e) => {
      e.preventDefault();

      if (isZoomed) {
        // Exit zoomed mode
        qrBox.classList.remove('ppv-qr-zoomed');
        fullscreenBtn.innerHTML = '<i class="ri-fullscreen-line"></i> <span>Vollbild</span>';
        isZoomed = false;
      } else {
        // Enter zoomed mode (works on iOS too!)
        qrBox.classList.add('ppv-qr-zoomed');
        fullscreenBtn.innerHTML = '<i class="ri-fullscreen-exit-line"></i> <span>Verkleinern</span>';
        isZoomed = true;
      }
      if (navigator.vibrate) navigator.vibrate(20);
    });
  }

  // 🔆 Activate Wake Lock to keep screen bright
  requestWakeLock();

  // Re-acquire wake lock if it gets released (e.g., tab visibility change)
  document.addEventListener('visibilitychange', async () => {
    if (document.visibilityState === 'visible') {
      await requestWakeLock();
    }
  });
});

// ═══════════════════════════════════════════════════════════════
// LOAD TIMED QR
// ═══════════════════════════════════════════════════════════════
async function loadTimedQR(userId, forceNew = false) {
  showLoading();

  try {
    const res = await fetch("/wp-json/ppv/v1/user/generate-timed-qr", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ user_id: userId })
    });

    if (!res.ok) {
      throw new Error(`HTTP ${res.status}`);
    }

    const data = await res.json();

    if (data.code) {
      // Error response
      showStatus("❌ " + (data.message || "Fehler beim Laden"), "error");
      return;
    }

    // Display QR
    displayQR(data);

    // Start countdown
    startCountdown(data.expires_at);

    // 💾 Cache QR for offline use (convert image to base64)
    cacheQRData(userId, data);

    // 📳 Haptic feedback on QR load success
    if (window.ppvHaptic) window.ppvHaptic('scan');

    // Hide offline banner if visible
    hideOfflineBanner();

    // Status message
    if (data.is_new) {
      showStatus("✅ Neuer QR-Code generiert (30 Min. gültig)", "success");
    } else {
      const remainingMin = Math.floor(data.expires_in / 60);
      showStatus(`✅ QR-Code geladen (${remainingMin} Min. verbleibend)`, "success");
    }

  } catch (err) {
    console.error("QR Load Error:", err);

    // 📱 Offline fallback - try to load cached QR
    const cached = loadFromCache(userId);
    const now = Math.floor(Date.now() / 1000);

    // Check if timed QR is still valid
    if (cached && cached.expires_at && cached.expires_at > now) {
      showOfflineBanner();
      displayQR(cached);
      startCountdown(cached.expires_at);
      showStatus("📱 Offline - Gespeicherter QR-Code", "warning");
      return;
    }

    // 🔐 STATIC QR FALLBACK - Timed QR expired or unavailable
    const staticQR = loadStaticQRFromCache(userId);
    if (staticQR && staticQR.qr_value) {
      showOfflineBanner();
      displayQR(staticQR);
      // Hide timer for static QR
      const timerEl = document.getElementById("ppvQrTimer");
      if (timerEl) timerEl.style.display = "none";
      showStatus("📱 Offline - Tages-QR (1x pro Geschäft)", "warning");
      return;
    }

    // Last resort: show expired timed QR if available
    if (cached) {
      showOfflineBanner();
      displayQR(cached);
      showStatus("⏰ Offline - QR-Code abgelaufen", "warning");
      return;
    }

    // No cache available at all
    hideLoading();
    showOfflineBanner();
    showStatus("📡 Offline - Bitte einmal online laden", "error");
  }
}

// ═══════════════════════════════════════════════════════════════
// DISPLAY QR
// ═══════════════════════════════════════════════════════════════
function displayQR(data) {
  hideLoading();
  hideExpired();

  const qrImg = document.getElementById("ppvQrImg");
  const qrValue = document.getElementById("ppvQrValue");
  const qrDisplay = document.getElementById("ppvQrDisplay");

  if (qrImg && data.qr_value) {
    // 🎨 Generate QR code locally (no external API)
    // Check if we have a cached base64 URL first
    let qrDataUrl = null;

    if (data.qr_url && data.qr_url.startsWith('data:')) {
      // Already have base64 from cache
      qrDataUrl = data.qr_url;
    } else {
      // Generate locally using qrcode-generator
      qrDataUrl = generateQRCodeDataURL(data.qr_value, 364);
    }

    if (qrDataUrl) {
      qrImg.src = qrDataUrl;
      qrImg.style.display = 'block';
      // Store the generated data URL for caching
      data._generatedQrUrl = qrDataUrl;
    } else {
      // Fallback: try external URL if local generation fails
      qrImg.onerror = function() {
        if (qrValue && data.qr_value) {
          qrImg.style.display = 'none';
          showStatus("⚠️ QR-Bild nicht verfügbar - Code: " + data.qr_value, "warning");
        }
      };
      qrImg.onload = function() {
        qrImg.style.display = 'block';
      };
      qrImg.src = data.qr_url || '';
    }
  }
  if (qrValue) qrValue.value = data.qr_value;
  if (qrDisplay) qrDisplay.style.display = "block";
}

// ═══════════════════════════════════════════════════════════════
// COUNTDOWN TIMER (30:00 → 00:00)
// ═══════════════════════════════════════════════════════════════
function startCountdown(expirationTimestamp) {
  expiresAt = expirationTimestamp;

  const timerElement = document.getElementById("ppvQrTimer");
  const timerValue = document.getElementById("ppvTimerValue");

  // Clear previous interval
  if (countdownInterval) {
    clearInterval(countdownInterval);
  }

  // Reset warning classes
  if (timerElement) {
    timerElement.classList.remove("ppv-timer-warning", "ppv-timer-critical");
  }

  // Update every second
  countdownInterval = setInterval(() => {
    const now = Math.floor(Date.now() / 1000);
    const remaining = expiresAt - now;

    if (remaining <= 0) {
      // QR expired
      clearInterval(countdownInterval);
      showExpired();
      return;
    }

    // Format: MM:SS
    const mins = Math.floor(remaining / 60);
    const secs = remaining % 60;
    const formatted = `${mins}:${secs.toString().padStart(2, "0")}`;

    if (timerValue) {
      timerValue.textContent = formatted;
    }

    // Warning state at 5 minutes
    if (remaining <= 300 && remaining > 60) {
      if (timerElement) {
        timerElement.classList.add("ppv-timer-warning");
        timerElement.classList.remove("ppv-timer-critical");
      }
    }

    // Critical state at 1 minute
    if (remaining <= 60) {
      if (timerElement) {
        timerElement.classList.add("ppv-timer-critical");
        timerElement.classList.remove("ppv-timer-warning");
      }
    }

  }, 1000);
}

// ═══════════════════════════════════════════════════════════════
// UI STATE HELPERS
// ═══════════════════════════════════════════════════════════════
function showLoading() {
  const loading = document.getElementById("ppvQrLoading");
  const display = document.getElementById("ppvQrDisplay");
  const expired = document.getElementById("ppvQrExpired");

  if (loading) loading.style.display = "block";
  if (display) display.style.display = "none";
  if (expired) expired.style.display = "none";
}

function hideLoading() {
  const loading = document.getElementById("ppvQrLoading");
  if (loading) loading.style.display = "none";
}

function showExpired() {
  const display = document.getElementById("ppvQrDisplay");
  const expired = document.getElementById("ppvQrExpired");

  if (display) display.style.display = "none";
  if (expired) expired.style.display = "block";

  showStatus("⏰ QR-Code abgelaufen - bitte neu generieren", "warning");
}

function hideExpired() {
  const expired = document.getElementById("ppvQrExpired");
  if (expired) expired.style.display = "none";
}

function showStatus(message, type = "info") {
  const status = document.getElementById("ppvQrStatus");
  if (!status) return;

  status.textContent = message;
  status.className = "ppv-user-qr-status ppv-status-" + type;

  // Auto-clear after 4 seconds
  setTimeout(() => {
    if (status.textContent === message) {
      status.textContent = "";
      status.className = "ppv-user-qr-status";
    }
  }, 4000);
}

// ═══════════════════════════════════════════════════════════════
// 💾 OFFLINE CACHE FUNCTIONS
// ═══════════════════════════════════════════════════════════════

async function cacheQRData(userId, data) {
  try {
    // 🎨 Use locally generated QR data URL (always base64, always works offline)
    let qrBase64 = null;
    let cacheSuccess = false;

    // Priority 1: Use the locally generated QR URL
    if (data._generatedQrUrl && data._generatedQrUrl.startsWith('data:')) {
      qrBase64 = data._generatedQrUrl;
      cacheSuccess = true;
    }
    // Priority 2: Generate fresh if not available
    else if (data.qr_value) {
      qrBase64 = generateQRCodeDataURL(data.qr_value, 364);
      cacheSuccess = qrBase64 && qrBase64.startsWith('data:');
    }
    // Priority 3: Use existing data URL
    else if (data.qr_url && data.qr_url.startsWith('data:')) {
      qrBase64 = data.qr_url;
      cacheSuccess = true;
    }

    // Fallback: store qr_value only (can regenerate on load)
    if (!qrBase64) {
      qrBase64 = null;
    }

    const cacheData = {
      user_id: userId,
      qr_url: qrBase64,
      qr_value: data.qr_value,
      expires_at: data.expires_at,
      cached_at: Math.floor(Date.now() / 1000),
      is_base64: cacheSuccess
    };

    localStorage.setItem(QR_CACHE_KEY + '_' + userId, JSON.stringify(cacheData));

    if (cacheSuccess) {
      console.log('💾 QR cached for offline use (local generation)');
    } else {
      console.log('💾 QR value cached - will regenerate image when needed');
    }
  } catch (e) {
    console.warn('Failed to cache QR:', e);
  }
}

// Alternative method to convert image to base64 using canvas
function imageToBase64(imgUrl) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = function() {
      try {
        const canvas = document.createElement('canvas');
        canvas.width = img.width;
        canvas.height = img.height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0);
        resolve(canvas.toDataURL('image/png'));
      } catch (e) {
        reject(e);
      }
    };
    img.onerror = reject;
    img.src = imgUrl;
  });
}

function loadFromCache(userId) {
  try {
    const cached = localStorage.getItem(QR_CACHE_KEY + '_' + userId);
    if (!cached) return null;

    const data = JSON.parse(cached);
    const now = Math.floor(Date.now() / 1000);

    // Check if QR is expired
    if (data.expires_at <= now) {
      // Don't delete - still show expired QR with warning
      // User can still show it at the store
      data._isExpired = true;
    }

    // Check if QR image is properly cached as base64
    if (data.qr_url && !data.qr_url.startsWith('data:')) {
      // Image is a URL, not base64 - won't work offline
      data._imageNotCached = true;
    }

    return data;
  } catch (e) {
    return null;
  }
}

// 🔐 Cache STATIC QR for offline fallback (never expires)
function cacheStaticQR(userId, qrValue, qrDataUrl) {
  try {
    const cacheData = {
      user_id: userId,
      qr_url: qrDataUrl,
      qr_value: qrValue,
      cached_at: Math.floor(Date.now() / 1000)
    };
    localStorage.setItem(STATIC_QR_CACHE_KEY + '_' + userId, JSON.stringify(cacheData));
    console.log('💾 Static QR cached for offline fallback');
  } catch (e) {
    console.warn('Failed to cache static QR:', e);
  }
}

// 🔐 Load STATIC QR from cache
function loadStaticQRFromCache(userId) {
  try {
    const cached = localStorage.getItem(STATIC_QR_CACHE_KEY + '_' + userId);
    if (!cached) return null;
    return JSON.parse(cached);
  } catch (e) {
    return null;
  }
}

// 🔐 Fetch and cache static QR (call once when online)
async function fetchAndCacheStaticQR(userId) {
  if (!userId) return;
  try {
    const res = await fetch("/wp-json/ppv/v1/user/qr?user_id=" + userId);
    if (!res.ok) return;
    const data = await res.json();
    if (data.qr_value) {
      const qrDataUrl = generateQRCodeDataURL(data.qr_value, 364);
      if (qrDataUrl) {
        cacheStaticQR(userId, data.qr_value, qrDataUrl);
      }
    }
  } catch (e) {
    // Silently fail - static QR is just a fallback
  }
}

function blobToBase64(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onloadend = () => resolve(reader.result);
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });
}

// ═══════════════════════════════════════════════════════════════
// 📡 OFFLINE BANNER
// ═══════════════════════════════════════════════════════════════

function showOfflineBanner() {
  // Check if banner already exists
  if (document.getElementById('ppvOfflineBanner')) return;

  const banner = document.createElement('div');
  banner.id = 'ppvOfflineBanner';
  banner.className = 'ppv-offline-qr-banner';
  banner.innerHTML = `
    <i class="ri-wifi-off-line"></i>
    <span>Offline-Modus</span>
  `;

  const qrBox = document.querySelector('.ppv-user-qr');
  if (qrBox) {
    qrBox.insertBefore(banner, qrBox.firstChild);
  }
}

function hideOfflineBanner() {
  const banner = document.getElementById('ppvOfflineBanner');
  if (banner) banner.remove();
}

// ═══════════════════════════════════════════════════════════════
// 🌐 ONLINE/OFFLINE EVENT LISTENERS
// ═══════════════════════════════════════════════════════════════

window.addEventListener('online', () => {
  console.log('🟢 Back online');
  hideOfflineBanner();
  // Reload QR when back online
  if (currentUserId) {
    loadTimedQR(currentUserId);
  }
});

window.addEventListener('offline', () => {
  console.log('🔴 Went offline');
  showOfflineBanner();
  showStatus("📡 Offline - QR-Code weiterhin verfügbar", "warning");
});
