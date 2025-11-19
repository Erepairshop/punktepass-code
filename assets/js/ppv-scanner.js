/**
 * PunktePass – Kamera QR Scanner (v3.6 PRO)
 * ⚙️ Autofókusz + Torch + Stabil felismerés
 * ✅ Ferde szög / gyenge fény tolerancia
 * ✅ Duplikált olvasás elleni védelem
 * ✅ REST kompatibilis (PPV_QR::rest_process_scan)
 * ✅ Manual start button (kamera csak gomb nyomásra)
 * ✅ Keep screen awake (Wake Lock API + fallback)
 */

document.addEventListener("DOMContentLoaded", () => {
  const area = document.getElementById("ppv-scan-area");
  if (!area) return;

  // ============================================================
  // 📱 KEEP SCREEN AWAKE - Wake Lock API + Fallback
  // ============================================================
  let wakeLock = null;

  async function keepScreenAwake() {
    // Try Wake Lock API (modern browsers)
    if ('wakeLock' in navigator) {
      try {
        wakeLock = await navigator.wakeLock.request('screen');
        console.log('✅ [Scanner] Wake Lock active - screen won\'t sleep');

        wakeLock.addEventListener('release', () => {
          console.log('⚠️ [Scanner] Wake Lock released');
        });

        return true;
      } catch (err) {
        console.warn('⚠️ [Scanner] Wake Lock failed:', err);
      }
    }

    // Fallback: Play invisible video loop (works on iOS Safari)
    try {
      const video = document.createElement('video');
      video.setAttribute('loop', '');
      video.setAttribute('muted', '');
      video.setAttribute('playsinline', '');
      video.style.display = 'none';
      video.src = 'data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAAAu1tZGF0AAACrgYF//+q3EXpvebZSLeWLNgg2SPu73gyNjQgLSBjb3JlIDE1NSByMjkwMSA3ZDZhYjNkIC0gSC4yNjQvTVBFRy00IEFWQyBjb2RlYyAtIENvcHlsZWZ0IDIwMDMtMjAxOCAtIGh0dHA6Ly93d3cudmlkZW9sYW4ub3JnL3gyNjQuaHRtbCAtIG9wdGlvbnM6IGNhYmFjPTEgcmVmPTMgZGVibG9jaz0xOjA6MCBhbmFseXNlPTB4MzoweDExMyBtZT1oZXggc3VibWU9NyBwc3k9MSBwc3lfcmQ9MS4wMDowLjAwIG1peGVkX3JlZj0xIG1lX3JhbmdlPTE2IGNocm9tYV9tZT0xIHRyZWxsaXM9MSA4eDhkY3Q9MSBjcW09MCBkZWFkem9uZT0yMSwxMSBmYXN0X3Bza2lwPTEgY2hyb21hX3FwX29mZnNldD0tMiB0aHJlYWRzPTEgbG9va2FoZWFkX3RocmVhZHM9MSBzbGljZWRfdGhyZWFkcz0wIG5yPTAgZGVjaW1hdGU9MSBpbnRlcmxhY2VkPTAgYmx1cmF5X2NvbXBhdD0wIGNvbnN0cmFpbmVkX2ludHJhPTAgYmZyYW1lcz0zIGJfcHlyYW1pZD0yIGJfYWRhcHQ9MSBiX2JpYXM9MCBkaXJlY3Q9MSB3ZWlnaHRiPTEgb3Blbl9nb3A9MCB3ZWlnaHRwPTIga2V5aW50PTI1MCBrZXlpbnRfbWluPTI1IHNjZW5lY3V0PTQwIGludHJhX3JlZnJlc2g9MCByY19sb29rYWhlYWQ9NDAgcmM9Y3JmIG1idHJlZT0xIGNyZj0yMy4wIHFjb21wPTAuNjAgcXBtaW49MCBxcG1heD02OSBxcHN0ZXA9NCBpcF9yYXRpbz0xLjQwIGFxPTE6MS4wMACAAAAAD2WIhAA3//728P4FNjuZQQAAAu5tb292AAAAbG12aGQAAAAAAAAAAAAAAAAAAAPoAAAAAwABAAABAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACAAACGHRyYWsAAABcdGtoZAAAAAMAAAAAAAAAAAAAAAEAAAAAAAADAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
      document.body.appendChild(video);
      await video.play();
      console.log('✅ [Scanner] Fallback video playing - screen won\'t sleep');
      return true;
    } catch (err) {
      console.warn('⚠️ [Scanner] Fallback video failed:', err);
    }

    return false;
  }

  function releaseWakeLock() {
    if (wakeLock !== null) {
      wakeLock.release()
        .then(() => {
          wakeLock = null;
          console.log('🔓 [Scanner] Wake Lock released');
        });
    }
  }

  // ============================================================
  // 🎥 SCANNER UI + START BUTTON
  // ============================================================
  area.innerHTML = `
    <div style="text-align:center;">
      <button id="ppv-start-scanner-btn" style="
        background: linear-gradient(135deg, #00e676, #00c853);
        color: #fff;
        border: none;
        border-radius: 12px;
        padding: 16px 32px;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        box-shadow: 0 4px 20px rgba(0, 230, 118, 0.4);
        margin: 20px auto;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
      " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 25px rgba(0, 230, 118, 0.5)';"
         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 20px rgba(0, 230, 118, 0.4)';">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7"/>
          <rect x="14" y="3" width="7" height="7"/>
          <rect x="14" y="14" width="7" height="7"/>
          <rect x="3" y="14" width="7" height="7"/>
        </svg>
        <span>📷 Scanner starten</span>
      </button>
    </div>
    <div id="reader" style="width:320px;height:320px;margin:auto;border:2px solid #0ff;border-radius:14px;display:none;"></div>
    <p id="scanResult" style="margin-top:10px;color:#0ff;font-weight:bold;text-align:center;"></p>
  `;

  // --- Külső lib betöltése
  const script = document.createElement("script");
  script.src = "https://unpkg.com/html5-qrcode";
  document.body.appendChild(script);

  script.onload = () => {
    if (!window.Html5Qrcode) {
      area.innerHTML = "❌ Scanner konnte nicht geladen werden.";
      return;
    }

    const startBtn = document.getElementById('ppv-start-scanner-btn');
    const readerDiv = document.getElementById('reader');
    const resultP = document.getElementById('scanResult');

    // ============================================================
    // 🚀 START SCANNER (csak gomb kattintásra)
    // ============================================================
    startBtn.addEventListener('click', async () => {
      console.log('🎬 [Scanner] Start button clicked');

      // Hide button, show camera
      startBtn.style.display = 'none';
      readerDiv.style.display = 'block';
      resultP.innerText = '📷 Kamera wird gestartet...';

      // Keep screen awake
      await keepScreenAwake();

      const scanner = new Html5Qrcode("reader");

      const config = {
        fps: 15,
        qrbox: { width: 280, height: 280 },
        aspectRatio: 1.0,
        experimentalFeatures: { useBarCodeDetectorIfSupported: true },
      };

      let lastRead = "";
      let repeatCount = 0;
      let scanning = true;

      const onScanSuccess = (qrCode) => {
        if (!scanning) return;

        // Stabilizált ismételt olvasás
        if (qrCode === lastRead) repeatCount++;
        else {
          lastRead = qrCode;
          repeatCount = 1;
        }

        if (repeatCount >= 2) {
          scanning = false;
          resultP.innerText = "✅ Gelesen: " + qrCode;
          scanner.stop().then(() => {
            releaseWakeLock(); // Release wake lock when done
            sendToServer(qrCode);
          });
        }
      };

      // --- Kamera indítása
      try {
        await scanner.start({ facingMode: "environment" }, config, onScanSuccess);
        resultP.innerText = '✅ Scanner aktiv - QR-Code scannen';

        // 🔦 Torch (LED) automatikus bekapcsolás, ha elérhető
        try {
          await scanner.turnOnTorch();
        } catch (e) {
          console.log("🔦 Torch not supported:", e.message);
        }

        console.log("📷 PunktePass Kamera-Scanner aktiv");
      } catch (err) {
        console.error("Kamera Fehler:", err);
        resultP.innerText = "❌ Keine Kamera gefunden oder Zugriff verweigert.";
        startBtn.style.display = 'inline-flex'; // Show button again
        readerDiv.style.display = 'none';
        releaseWakeLock();
      }
    });

    // --- REST kommunikáció
    async function sendToServer(qrCode) {
      const storeKey = window.PPV_STORE_DATA?.store_key || "";
      resultP.innerText = "⏳ Punkte werden hinzugefügt...";

      try {
        const res = await fetch("/wp-json/punktepass/v1/pos/scan", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            qr: qrCode,
            store_key: storeKey,
          }),
        });

        const data = await res.json();

        if (data.success) {
          showToast(data.message || "🎉 Punkt erfolgreich hinzugefügt!");
        } else {
          showToast(data.message || "⚠️ Fehler beim Hinzufügen", "error");
        }
      } catch (e) {
        console.error(e);
        showToast("⚠️ Netzwerkfehler", "error");
      }
    }

    // --- Egyszerű toast animáció
    function showToast(msg, type = "success") {
      const t = document.createElement("div");
      t.className = "ppv-toast " + type;
      t.textContent = msg;
      Object.assign(t.style, {
        position: "fixed",
        bottom: "30px",
        left: "50%",
        transform: "translateX(-50%)",
        background: type === "error" ? "#ff4444" : "#00e676",
        color: "#fff",
        padding: "10px 20px",
        borderRadius: "8px",
        fontWeight: "bold",
        boxShadow: "0 0 10px rgba(0,0,0,0.3)",
        zIndex: 9999,
        opacity: 0,
        transition: "opacity 0.4s",
      });
      document.body.appendChild(t);
      setTimeout(() => (t.style.opacity = 1), 50);
      setTimeout(() => {
        t.style.opacity = 0;
        setTimeout(() => t.remove(), 400);
      }, 2500);
    }
  };

  // ============================================================
  // 🧹 CLEANUP - Release wake lock when leaving page
  // ============================================================
  window.addEventListener('beforeunload', () => {
    releaseWakeLock();
  });

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
      releaseWakeLock();
    }
  });

});
