/**
 * PunktePass – Timed QR with 30min Countdown
 * REST API + Auto Countdown + Refresh on Expiry
 */


let countdownInterval = null;
let expiresAt = null;

document.addEventListener("DOMContentLoaded", async () => {
  const qrBox = document.querySelector(".ppv-user-qr");
  if (!qrBox) return;

  const userId = qrBox.getAttribute("data-user-id");
  if (!userId) {
    showStatus("⚠️ User ID fehlt", "error");
    return;
  }

  // Load initial timed QR
  await loadTimedQR(userId);

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

    // 📳 Haptic feedback on QR load success
    if (window.ppvHaptic) window.ppvHaptic('scan');

    // Status message
    if (data.is_new) {
      showStatus("✅ Neuer QR-Code generiert (30 Min. gültig)", "success");
    } else {
      const remainingMin = Math.floor(data.expires_in / 60);
      showStatus(`✅ QR-Code geladen (${remainingMin} Min. verbleibend)`, "success");
    }

  } catch (err) {
    console.error("QR Load Error:", err);
    showStatus("❌ Netzwerkfehler beim Laden des QR-Codes", "error");
    hideLoading();
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

  if (qrImg) qrImg.src = data.qr_url;
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
