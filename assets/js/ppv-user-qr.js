/**
 * PunktePass – Timed QR with 30min Countdown
 * REST API + Auto Countdown + Refresh on Expiry
 * ✅ Offline Support - Cache QR for offline viewing
 */

const QR_CACHE_KEY = 'ppv_qr_cache';
let countdownInterval = null;
let expiresAt = null;
let currentUserId = null;

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
    if (cached) {
      showOfflineBanner();
      displayQR(cached);
      startCountdown(cached.expires_at);

      // Show appropriate status message
      if (cached._imageNotCached) {
        showStatus("⚠️ Offline - QR-Bild nicht im Cache", "warning");
      } else if (cached._isExpired) {
        showStatus("⏰ Offline - QR-Code abgelaufen (trotzdem anzeigbar)", "warning");
      } else {
        showStatus("📱 Offline-Modus - Gespeicherter QR-Code", "success");
      }
    } else {
      showStatus("📡 Offline - Kein gespeicherter QR-Code", "error");
      hideLoading();
    }
    // Don't return - still setup refresh button for when back online
  } else {
    // Load initial timed QR (only when online)
    await loadTimedQR(userId);
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
    if (cached) {
      showOfflineBanner();
      displayQR(cached);
      startCountdown(cached.expires_at);
      showStatus("📱 Offline - Gespeicherter QR-Code wird angezeigt", "warning");
      return;
    }

    // No cache available
    hideLoading();
    showOfflineBanner();

    // Show helpful message based on offline status
    if (!navigator.onLine) {
      showStatus("📡 Offline - Bitte App einmal online laden, um QR-Code zu speichern", "error");
    } else {
      showStatus("❌ Netzwerkfehler - Bitte später erneut versuchen", "error");
    }
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

  if (qrImg) {
    // Handle image load error (e.g., offline with non-cached URL)
    qrImg.onerror = function() {
      // Show fallback with QR value
      if (qrValue && data.qr_value) {
        qrImg.style.display = 'none';
        showStatus("⚠️ QR-Bild nicht verfügbar - Code: " + data.qr_value, "warning");
      }
    };
    qrImg.onload = function() {
      qrImg.style.display = 'block';
    };
    qrImg.src = data.qr_url;
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
    // Convert QR image to base64 for offline storage
    let qrBase64 = data.qr_url;
    let cacheSuccess = false;

    // If it's not already a data URL, fetch and convert
    if (data.qr_url && !data.qr_url.startsWith('data:')) {
      try {
        // Try fetching with CORS mode
        const response = await fetch(data.qr_url, { mode: 'cors' });
        if (response.ok) {
          const blob = await response.blob();
          qrBase64 = await blobToBase64(blob);
          cacheSuccess = qrBase64.startsWith('data:');
        }
      } catch (e) {
        console.warn('Could not cache QR image (CORS?):', e);
        // Try alternative: use Image element
        try {
          qrBase64 = await imageToBase64(data.qr_url);
          cacheSuccess = qrBase64.startsWith('data:');
        } catch (e2) {
          console.warn('Image fallback also failed:', e2);
        }
      }
    } else {
      cacheSuccess = true;
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
      console.log('💾 QR cached for offline use (base64)');
    } else {
      console.warn('⚠️ QR cached but image is URL only - offline display may not work');
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

// ═══════════════════════════════════════════════════════════════
// 📡 NFC SEND SUPPORT (Phone-to-Phone)
// ═══════════════════════════════════════════════════════════════

const nfcSupported = 'NDEFReader' in window;
let nfcWriter = null;
let isNfcSending = false;

// Initialize NFC button if supported
document.addEventListener("DOMContentLoaded", () => {
  const debugInfo = {
    nfcSupported: nfcSupported,
    isSecureContext: window.isSecureContext,
    browser: /SamsungBrowser/.test(navigator.userAgent) ? 'Samsung Internet' :
             /Chrome/.test(navigator.userAgent) ? 'Chrome' :
             /Firefox/.test(navigator.userAgent) ? 'Firefox' : 'Ismeretlen'
  };

  // Show debug info on screen
  const qrDisplay = document.getElementById('ppvQrDisplay');
  if (qrDisplay) {
    const debugDiv = document.createElement('div');
    debugDiv.style.cssText = 'background:#333;color:#fff;padding:10px;margin-top:10px;border-radius:8px;font-size:12px;text-align:left;';
    debugDiv.innerHTML = `
      <b>NFC Debug:</b><br>
      📱 Böngésző: ${debugInfo.browser}<br>
      🔒 HTTPS: ${debugInfo.isSecureContext ? '✅ Igen' : '❌ Nem'}<br>
      📡 NFC API: ${debugInfo.nfcSupported ? '✅ Elérhető' : '❌ Nem elérhető'}<br>
      ${!debugInfo.nfcSupported ? '<br>⚠️ Használj <b>Chrome</b> böngészőt!' : ''}
      ${!debugInfo.isSecureContext ? '<br>⚠️ HTTPS szükséges!' : ''}
    `;
    qrDisplay.appendChild(debugDiv);
  }

  if (!nfcSupported) {
    return;
  }

  if (!window.isSecureContext) {
    return;
  }

  // Create NFC button
  createNfcButton();
});

function createNfcButton() {
  const qrDisplay = document.getElementById('ppvQrDisplay');
  if (!qrDisplay) return;

  // Check if button already exists
  if (document.getElementById('ppvNfcBtn')) return;

  const nfcBtn = document.createElement('button');
  nfcBtn.id = 'ppvNfcBtn';
  nfcBtn.className = 'ppv-nfc-btn';
  nfcBtn.innerHTML = `
    <span class="ppv-nfc-icon">📡</span>
    <span class="ppv-nfc-text">NFC küldés</span>
  `;
  nfcBtn.title = 'Érintsd a telefont a kasszához';

  // Add button styles
  const style = document.createElement('style');
  style.textContent = `
    .ppv-nfc-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      padding: 14px 20px;
      margin-top: 12px;
      background: linear-gradient(135deg, #9c27b0, #7b1fa2);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .ppv-nfc-btn:hover {
      background: linear-gradient(135deg, #ab47bc, #8e24aa);
      transform: translateY(-1px);
    }
    .ppv-nfc-btn:active {
      transform: translateY(0);
    }
    .ppv-nfc-btn.sending {
      background: linear-gradient(135deg, #4caf50, #388e3c);
      animation: nfc-pulse 1s infinite;
    }
    .ppv-nfc-btn .ppv-nfc-icon {
      font-size: 20px;
    }
    @keyframes nfc-pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.7; }
    }
  `;
  document.head.appendChild(style);

  nfcBtn.addEventListener('click', toggleNfcSend);
  qrDisplay.appendChild(nfcBtn);

  console.log('[NFC] Send button created');
}

async function toggleNfcSend() {
  const nfcBtn = document.getElementById('ppvNfcBtn');
  const qrValue = document.getElementById('ppvQrValue');

  if (!qrValue || !qrValue.value) {
    showStatus('❌ Nincs QR kód a küldéshez', 'error');
    return;
  }

  if (isNfcSending) {
    // Stop sending
    stopNfcSend();
    return;
  }

  // Start sending
  try {
    nfcWriter = new NDEFReader();

    // Write PunktePass data to NFC
    await nfcWriter.write({
      records: [{
        recordType: "text",
        data: "ppv:" + qrValue.value  // Prefix with ppv: so handler knows it's PunktePass
      }]
    });

    isNfcSending = true;

    if (nfcBtn) {
      nfcBtn.classList.add('sending');
      nfcBtn.querySelector('.ppv-nfc-text').textContent = 'NFC aktív - érintsd oda!';
    }

    // Haptic feedback
    if (window.ppvHaptic) window.ppvHaptic('success');

    showStatus('📡 NFC küldés aktív - érintsd a telefont a kasszához!', 'success');
    console.log('[NFC] 📡 Sending:', qrValue.value);

    // Auto-stop after 30 seconds
    setTimeout(() => {
      if (isNfcSending) {
        stopNfcSend();
        showStatus('⏰ NFC küldés időtúllépés - próbáld újra', 'warning');
      }
    }, 30000);

  } catch (e) {
    console.error('[NFC] Write error:', e);

    if (e.name === 'NotAllowedError') {
      showStatus('❌ NFC engedély megtagadva - engedélyezd a beállításokban', 'error');
    } else if (e.name === 'NotSupportedError') {
      showStatus('❌ NFC nem támogatott ezen az eszközön', 'error');
    } else {
      showStatus('❌ NFC hiba: ' + e.message, 'error');
    }
  }
}

function stopNfcSend() {
  isNfcSending = false;
  nfcWriter = null;

  const nfcBtn = document.getElementById('ppvNfcBtn');
  if (nfcBtn) {
    nfcBtn.classList.remove('sending');
    nfcBtn.querySelector('.ppv-nfc-text').textContent = 'NFC küldés';
  }

  console.log('[NFC] Send stopped');
}
