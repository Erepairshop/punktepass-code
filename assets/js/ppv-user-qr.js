/**
 * PunktePass – User QR (PWA Neon Blue Version)
 * REST API kompatibilis + Auto Refresh + Copy Feedback
 */

console.log("✅ PunktePass User QR JS aktiv");

document.addEventListener("DOMContentLoaded", async () => {
  const qrBox = document.querySelector(".ppv-user-qr");
  if (!qrBox) return;

  const qrImg = qrBox.querySelector(".ppv-user-qr-img");
  const qrValue = qrBox.querySelector(".ppv-user-qr-value");
  const status = document.createElement("div");
  status.className = "ppv-user-qr-status";
  qrBox.appendChild(status);

  // 🔹 Felhasználó ID lekérése (WordPress globálból vagy localStorage)
  const userId = window.PPV_USER_ID || localStorage.getItem("ppv_user_id");
  if (!userId) {
    status.innerHTML = "⚠️ Nicht eingeloggt";
    return;
  }

  try {
    const res = await fetch(`/wp-json/ppv/v1/user/qr?user_id=${userId}`);
    const data = await res.json();

    if (data.error) {
      status.innerHTML = `⚠️ ${data.error}`;
      return;
    }

    // 🔹 QR adatok frissítése
    qrImg.src = data.qr_url;
    qrValue.value = data.qr_value;
    status.innerHTML = "✅ QR-Code geladen";

    // 🔹 Másolás kattintásra
    qrValue.addEventListener("click", () => {
      navigator.clipboard.writeText(qrValue.value);
      status.innerHTML = "📋 QR kopiert!";
      setTimeout(() => (status.innerHTML = "✅ QR-Code geladen"), 2000);
    });
  } catch (err) {
    status.innerHTML = "❌ Netzwerkfehler";
    console.error(err);
  }
});
