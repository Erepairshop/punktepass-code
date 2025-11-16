/**
 * PunktePass – Kamera QR Scanner (v3.5 PRO)
 * ⚙️ Autofókusz + Torch + Stabil felismerés
 * ✅ Ferde szög / gyenge fény tolerancia
 * ✅ Duplikált olvasás elleni védelem
 * ✅ REST kompatibilis (PPV_QR::rest_process_scan)
 */

document.addEventListener("DOMContentLoaded", () => {
  const area = document.getElementById("ppv-scan-area");
  if (!area) return;

  area.innerHTML = `
    <div id="reader" style="width:320px;height:320px;margin:auto;border:2px solid #0ff;border-radius:14px;"></div>
    <p id="scanResult" style="margin-top:10px;color:#0ff;font-weight:bold;"></p>
  `;

  // --- Külső lib betöltése
  const script = document.createElement("script");
  script.src = "https://unpkg.com/html5-qrcode";
  document.body.appendChild(script);

  script.onload = async () => {
    if (!window.Html5Qrcode) {
      area.innerHTML = "❌ Scanner konnte nicht geladen werden.";
      return;
    }

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
        document.getElementById("scanResult").innerText = "✅ Gelesen: " + qrCode;
        scanner.stop().then(() => sendToServer(qrCode));
      }
    };

    // --- Kamera indítása
    try {
      await scanner.start({ facingMode: "environment" }, config, onScanSuccess);

      // 🔦 Torch (LED) automatikus bekapcsolás, ha elérhető
      try {
        await scanner.turnOnTorch();
      } catch (e) {
        console.log("🔦 Torch not supported:", e.message);
      }

      console.log("📷 PunktePass Kamera-Scanner aktiv");
    } catch (err) {
      console.error("Kamera Fehler:", err);
      area.innerHTML = "❌ Keine Kamera gefunden oder Zugriff verweigert.";
    }

    // --- REST kommunikáció
    async function sendToServer(qrCode) {
      const storeKey = window.PPV_STORE_DATA?.store_key || "";
      document.getElementById("scanResult").innerText = "⏳ Punkte werden hinzugefügt...";

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
  
  
  
});
