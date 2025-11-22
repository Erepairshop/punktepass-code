/**
 * PunktePass – Hidden Scan (ChromeOS / Bluetooth)
 * Automatikus QR-küldés a háttérben → /pos/scan
 * ✅ TURBO COMPATIBLE
 */

// ✅ Duplicate load prevention
if (window.PPV_HIDDEN_SCAN_LOADED) {
  console.warn('⚠️ PPV Hidden Scan JS already loaded - skipping duplicate!');
} else {
  window.PPV_HIDDEN_SCAN_LOADED = true;

// 🔹 Service Worker regisztrálása háttérmódhoz
if ('serviceWorker' in navigator && typeof PPV_SCAN_DATA !== 'undefined') {
  navigator.serviceWorker.register(PPV_SCAN_DATA.plugin_url + 'assets/js/ppv-hidden-scan-sw.js')
    .then(reg => console.log('✅ HiddenScan SW registered', reg))
    .catch(err => console.error('❌ SW register failed', err));
}

// Use window-level variables to persist across Turbo navigations
window.ppvBuffer = window.ppvBuffer || "";
window.ppvTimer = window.ppvTimer || null;

// Only add keydown listener once
if (!window.PPV_HIDDEN_SCAN_KEYDOWN_ADDED) {
  window.PPV_HIDDEN_SCAN_KEYDOWN_ADDED = true;

  document.addEventListener("keydown", (e) => {
    // Ha ESC, buffer törlés
    if (e.key === "Escape") {
      window.ppvBuffer = "";
      return;
    }

    // Ha Enter – elküldjük a beolvasott QR-kódot
    if (e.key === "Enter" && window.ppvBuffer.length > 5) {
      const code = window.ppvBuffer.trim();
      window.ppvBuffer = "";
      ppvSendScan(code);
      return;
    }

    // Egyéb karakterek gyűjtése
    if (e.key.length === 1) {
      window.ppvBuffer += e.key;

      // Reset, ha túl hosszú szünet
      clearTimeout(window.ppvTimer);
      window.ppvTimer = setTimeout(() => (window.ppvBuffer = ""), 1000);
    }
  });
}

async function ppvSendScan(code) {
  console.log("📡 HiddenScan →", code);
  // Ha van aktív Service Worker, küldjük neki is a scan-t
if (navigator.serviceWorker && navigator.serviceWorker.controller) {
  navigator.serviceWorker.controller.postMessage({
    qr: code,
    store_key: PPV_SCAN_DATA.store_key,
    lang: PPV_SCAN_DATA.lang
  });
}


  try {
    const res = await fetch(PPV_SCAN_DATA.rest_url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        qr: code,
        store_key: PPV_SCAN_DATA.store_key,
        lang: PPV_SCAN_DATA.lang
      }),
    });

    const data = await res.json();
    console.log("✅ PunktePass response:", data);

    if (data.success) {
      ppvShowToast(data.message || "✅ Erfolgreich gescannt", "success");
      //new Audio(PPV_PLUGIN_URL + "assets/sounds/success.mp3").play();
    } else {
      ppvShowToast(data.message || "❌ Scan-Fehler", "error");
      //new Audio(PPV_PLUGIN_URL + "assets/sounds/error.mp3").play();
    }
  } catch (err) {
    console.error("❌ Scan error:", err);
    ppvShowToast("❌ Netzwerkfehler", "error");
  }
}

/** 🔹 Mini Toast ablak */
function ppvShowToast(msg, type = "info") {
  let box = document.createElement("div");
  box.className = "ppv-toast " + type;
  box.textContent = msg;
  document.body.appendChild(box);

  setTimeout(() => box.classList.add("show"), 10);
  setTimeout(() => box.classList.remove("show"), 3000);
  setTimeout(() => box.remove(), 3500);
}

// 🔹 Alap stílus (ha még nincs külön CSS) - only add once
if (!document.getElementById('ppv-hidden-scan-styles')) {
  const style = document.createElement("style");
  style.id = 'ppv-hidden-scan-styles';
  style.innerHTML = `
.ppv-toast {
  position: fixed;
  bottom: 30px;
  left: 50%;
  transform: translateX(-50%) scale(0.9);
  background: #222;
  color: white;
  padding: 10px 18px;
  border-radius: 12px;
  opacity: 0;
  transition: all 0.3s ease;
  z-index: 99999;
  font-family: system-ui;
  box-shadow: 0 0 12px rgba(0,0,0,0.4);
}
.ppv-toast.show { opacity: 1; transform: translateX(-50%) scale(1); }
.ppv-toast.success { background: #00c853; }
.ppv-toast.error { background: #e53935; }
`;
  document.head.appendChild(style);
}

} // End of duplicate load prevention
