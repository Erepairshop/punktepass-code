document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("ppv-bonus-form");
  const list = document.getElementById("ppv-bonus-list");

  // 🚨 Ha sem form, sem list nincs az oldalon, ne fusson a script
  if (!form && !list) {
    console.warn("⚠️ Bonus Days Modul deaktiviert – keine relevanten Elemente gefunden.");
    return;
  }

  async function loadBonusDays() {
    if (!list) return;
    try {
      const res = await fetch(`${ppv_bonus_ajax.ajax_url}?action=ppv_get_bonus_days`);
      const data = await res.json();

      if (data.success && data.data.length) {
        list.innerHTML = "";
        data.data.forEach((b) => {
          const el = document.createElement("div");
          el.className = "ppv-bonus-item";
          el.innerHTML = `
            <div>
              <b>${b.date}</b> — x${b.multiplier} +${b.extra_points} Punkte 
              (${b.active == 1 ? "Aktiv" : "Inaktiv"})
            </div>
            <button class="ppv-bonus-delete" data-id="${b.id}">🗑️ Löschen</button>
          `;
          list.appendChild(el);
        });
      } else {
        list.innerHTML = "<p>Keine Bonus-Tage gefunden.</p>";
      }
    } catch (err) {
      console.error("❌ Fehler beim Laden der Bonus-Tage:", err);
    }
  }

  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const formData = new FormData(form);
      formData.append("action", "ppv_save_bonus_day");
      formData.append("nonce", ppv_bonus_ajax.nonce);

      const res = await fetch(ppv_bonus_ajax.ajax_url, { method: "POST", body: formData });
      const data = await res.json();
      alert(data.data.message || "Gespeichert.");
      form.reset();
      loadBonusDays();
    });
  }

  if (list) {
    list.addEventListener("click", async (e) => {
      if (e.target.classList.contains("ppv-bonus-delete")) {
        const id = e.target.dataset.id;
        if (!confirm("Wirklich löschen?")) return;
        const fd = new FormData();
        fd.append("action", "ppv_delete_bonus_day");
        fd.append("nonce", ppv_bonus_ajax.nonce);
        fd.append("id", id);
        await fetch(ppv_bonus_ajax.ajax_url, { method: "POST", body: fd });
        loadBonusDays();
      }
    });
  }

  loadBonusDays();
});
