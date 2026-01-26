/**
 * PunktePass – POS Admin Dashboard (v4.6 Stable)
 * ✅ Fixed: loadStatus not defined
 * ✅ Fixed: URL base handling
 * ✅ Fixed: Store selector + login flow
 * ✅ Stable Chart.js rendering + refresh
 */

jQuery(document).ready(function ($) {

  // 🌐 Language detection
  const detectLang = () => document.cookie.match(/ppv_lang=([a-z]{2})/)?.[1] || localStorage.getItem('ppv_lang') || 'ro';
  const LANG = detectLang();
  const T = {
    de: {
      daily_points: 'Tägliche Punkte',
      enter_pin: 'Bitte geben Sie Ihren PIN ein.',
      checking_login: 'Anmeldung wird geprüft...',
      login_success: 'Erfolgreich angemeldet!',
      login_error: 'Serverfehler bei der Anmeldung.',
      load_error: 'Fehler beim Laden der Daten.',
      load_stores_error: 'Fehler beim Laden'
    },
    hu: {
      daily_points: 'Napi pontok',
      enter_pin: 'Kérlek add meg a PIN kódot.',
      checking_login: 'Bejelentkezés ellenőrzése...',
      login_success: 'Sikeres bejelentkezés!',
      login_error: 'Szerverhiba a bejelentkezésnél.',
      load_error: 'Hiba az adatok betöltésekor.',
      load_stores_error: 'Hiba a betöltéskor'
    },
    ro: {
      daily_points: 'Puncte zilnice',
      enter_pin: 'Te rog introdu PIN-ul.',
      checking_login: 'Se verifică autentificarea...',
      login_success: 'Autentificare reușită!',
      login_error: 'Eroare server la autentificare.',
      load_error: 'Eroare la încărcarea datelor.',
      load_stores_error: 'Eroare la încărcare'
    }
  }[LANG] || {
    daily_points: 'Tägliche Punkte',
    enter_pin: 'Bitte geben Sie Ihren PIN ein.',
    checking_login: 'Anmeldung wird geprüft...',
    login_success: 'Erfolgreich angemeldet!',
    login_error: 'Serverfehler bei der Anmeldung.',
    load_error: 'Fehler beim Laden der Daten.',
    load_stores_error: 'Fehler beim Laden'
  };

  const $loginView = $("#ppv-pos-login");
  const $dashboardView = $("#ppv-pos-dashboard");
  const $msgLogin = $("#ppv-pos-login-msg");
  const $msgDash = $("#ppv-pos-dashboard-msg");

  const TOKEN_KEY = "ppv_pos_token";
  const base = PPV_POS_ADMIN?.resturl || PPV_POS?.api_base || "/wp-json/ppv/v1/";

  /** ============================================================
   * INIT – ha már be van jelentkezve
   * ============================================================ */
  const existingToken = localStorage.getItem(TOKEN_KEY);
  if (existingToken) {
    showDashboard();
    loadStatus(existingToken);
  }

  /** ============================================================
   * LOGIN
   * ============================================================ */
  $("#ppv-pos-login-btn").on("click", async function () {
    const pin = $("#ppv-pos-pin").val().trim();
    if (!pin) {
      $msgLogin.text("❌ " + T.enter_pin);
      return;
    }

    $msgLogin.text("⏳ " + T.checking_login);

    try {
      const res = await fetch(base + "pos/login", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": PPV_POS_ADMIN.nonce
        },
        credentials: "include",
        body: JSON.stringify({ pin })
      });

      const data = await res.json();

      if (data.success) {
        const token = data.data.session_token;
        const currentStoreId = data.data.store_id;

        localStorage.setItem(TOKEN_KEY, token);
        localStorage.setItem("ppv_active_store", currentStoreId);
        document.cookie = "ppv_pos_token=" + token + "; path=/; max-age=" + 60 * 60 * 6 + "; SameSite=Lax";

        $msgLogin.text("✅ " + T.login_success);

        setTimeout(() => {
          showDashboard();
          initStoreSelector();
          setTimeout(() => loadStatus(token), 1000);
        }, 600);
      } else {
        $msgLogin.text("❌ " + (data.message || "Ungültiger PIN"));
      }

    } catch (err) {
      console.error("⚠️ Login-Fehler:", err);
      $msgLogin.text("⚠️ " + T.login_error);
    }
  }); // login click END


  /** ============================================================
   * STATUS / STATS LEKÉRÉSE (REST API)
   * ============================================================ */
  async function loadStatus(token) {
    const store_id = localStorage.getItem("ppv_active_store") || 1;

    try {
      const res = await $.ajax({
        url: base + "pos/stats",
        method: "GET",
        dataType: "json",
        data: { store_id },
        headers: { "X-WP-Nonce": PPV_POS_ADMIN.nonce },
      });

      if (res.success && res.stats) {
        const s = res.stats;

        $("#today-scans").text(s.today_scans ?? 0);
        $("#today-points").text(s.today_points ?? 0);
        $("#today-rewards").text(s.today_rewards ?? 0);
        $("#active-campaigns").text(s.active_campaigns ?? 0);
        $("#today-sales").text(s.today_sales ? s.today_sales.toFixed(2) : "0.00");
        $("#last-scan").text(s.last_scan ? s.last_scan : "—");

        if (s.chart && s.chart.length > 0) updateChart(s.chart);
        else console.warn("⚠️ Keine Chartdaten empfangen");

        $msgDash.text("✅ Daten aktualisiert");
      } else {
        $msgDash.text("⚠️ Keine gültige Antwort vom Server");
      }
    } catch (err) {
      console.error("❌ Fehler beim Laden der Stats:", err);
      $msgDash.text("⚠️ " + T.load_error);
    }
  }


  /** ============================================================
   * LOGOUT
   * ============================================================ */
  $("#ppv-pos-logout-btn").on("click", function () {
    logout();
  });

  async function logout() {
    const token = localStorage.getItem(TOKEN_KEY);

    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem("ppv_active_store");
    document.cookie = "ppv_pos_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";

    try {
      await $.ajax({
        url: base + "pos/logout",
        method: "POST",
        dataType: "json",
        data: { token },
        headers: { "X-WP-Nonce": PPV_POS_ADMIN.nonce },
      });
    } catch (err) {
      console.warn("⚠️ Logout-Fehler (ignoriert):", err);
    }

    $msgDash.text("✅ Abgemeldet.");
    showLogin();
    $("#ppv-pos-dashboard").hide();
    $("#ppv-pos-login").fadeIn(200);
  }


  /** ============================================================
   * SEGÉDFÜGGVÉNYEK
   * ============================================================ */
  function showDashboard() {
    $loginView.hide();
    $dashboardView.fadeIn(200);
  }

  function showLogin() {
    $dashboardView.hide();
    $loginView.fadeIn(200);
    $msgLogin.text("");
    $("#ppv-pos-pin").val("");
  }


  /** ============================================================
   * DIAGRAMM (Chart.js)
   * ============================================================ */
  function updateChart(data) {
    const canvas = document.getElementById("posChart");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    if (typeof Chart === "undefined") {
      $.getScript("https://cdn.jsdelivr.net/npm/chart.js", function () {
        renderChart(ctx, data);
      });
    } else {
      renderChart(ctx, data);
    }
  }

  function renderChart(ctx, data) {
    const labels = data.map(c => c.day);
    const values = data.map(c => c.points);

    if (window.posChartInstance) window.posChartInstance.destroy();

    window.posChartInstance = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: T.daily_points,
          data: values,
          borderColor: '#00e0ff',
          backgroundColor: 'rgba(0,224,255,0.25)',
          borderWidth: 2,
          tension: 0.3,
          fill: true,
          pointRadius: 3
        }]
      },
      options: {
        responsive: true,
        scales: {
          x: { ticks: { color: '#fff' } },
          y: { ticks: { color: '#fff', beginAtZero: true } }
        },
        plugins: { legend: { labels: { color: '#fff' } } }
      }
    });
  }


  /** ============================================================
   * STORE SELECTOR (DROPDOWN)
   * ============================================================ */
  async function initStoreSelector() {
    const dropdown = document.querySelector("#ppv-store-selector");
    if (!dropdown) return;

    const token = localStorage.getItem("ppv_pos_token");
    const activeStore = localStorage.getItem("ppv_active_store");

    if (!token) {
      console.warn("⚠️ Kein POS-Token gefunden. Bitte zuerst einloggen.");
      dropdown.innerHTML = "<option>Bitte zuerst einloggen</option>";
      return;
    }


    try {
      const response = await fetch(base + "pos/stores?token=" + encodeURIComponent(token), {
        method: "GET",
        headers: { "X-WP-Nonce": PPV_POS_ADMIN.nonce },
      });
      const result = await response.json();

      if (!result.success || !Array.isArray(result.data) || result.data.length === 0) {
        dropdown.innerHTML = "<option>Keine Stores gefunden</option>";
        return;
      }

      dropdown.innerHTML = result.data.map(store =>
        `<option value="${store.id}" ${store.id == activeStore ? "selected" : ""}>
          ${store.name} – ${store.city || ""}
        </option>`).join("");

      if (!activeStore && result.data.length > 0) {
        localStorage.setItem("ppv_active_store", result.data[0].id);
      }

    } catch (e) {
      console.error("❌ Fehler beim Laden der Stores:", e);
      dropdown.innerHTML = `<option>${T.load_stores_error}</option>`;
    }

    dropdown.addEventListener("change", (e) => {
      const storeId = e.target.value;
      localStorage.setItem("ppv_active_store", storeId);
      const token = localStorage.getItem("ppv_pos_token");
      if (token) loadStatus(token);
    });
  }


  /** ============================================================
   * AUTO INIT / REFRESH
   * ============================================================ */
  setTimeout(() => {
    const token = localStorage.getItem("ppv_pos_token");
    const checkReady = setInterval(() => {
      const dashboardVisible = $("#ppv-pos-dashboard").is(":visible");
      if (token && dashboardVisible) {
        clearInterval(checkReady);
        initStoreSelector();
        setTimeout(() => loadStatus(token), 600);

        // 📡 Initialize Ably real-time after dashboard is ready
        setTimeout(() => initAblyRealtime(), 1000);
      }
    }, 400);
  }, 800);

  $("#ppv-pos-refresh").on("click", function () {
    const token = localStorage.getItem(TOKEN_KEY);
    if (token) loadStatus(token);
  });


  /** ============================================================
   * 📡 ABLY REAL-TIME INTEGRATION
   * Refreshes stats when new scan arrives
   * ============================================================ */
  let ablyInstance = null;
  let ablyChannel = null;

  function initAblyRealtime() {
    // Check if Ably is available
    if (typeof Ably === 'undefined') {
      console.warn("⚠️ [POS Admin] Ably not loaded, skipping real-time");
      return;
    }

    // Get Ably config from global
    const ablyKey = window.PPV_POS_ADMIN?.ably_key || window.ppvAblyConfig?.key;
    if (!ablyKey) {
      console.warn("⚠️ [POS Admin] No Ably key found");
      return;
    }

    // Get store ID
    const storeId = localStorage.getItem("ppv_active_store");
    if (!storeId) {
      console.warn("⚠️ [POS Admin] No store ID for Ably channel");
      return;
    }

    // Close existing connection if any
    if (ablyInstance) {
      ablyInstance.close();
    }

    // Create Ably connection
    ablyInstance = new Ably.Realtime({ key: ablyKey });
    const channelName = 'store-' + storeId;
    ablyChannel = ablyInstance.channels.get(channelName);

    ablyInstance.connection.on('connected', () => {
    });

    ablyInstance.connection.on('failed', (err) => {
      console.error("❌ [POS Admin] Ably connection failed:", err);
    });

    // 🎯 Subscribe to new-scan events
    ablyChannel.subscribe('new-scan', (message) => {

      // Refresh stats immediately
      const token = localStorage.getItem(TOKEN_KEY);
      if (token) {
        loadStatus(token);
      }

      // Update last-scan directly if data is available
      if (message.data) {
        const scanTime = message.data.time_short || new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        let lastScanText = scanTime;

        // Show birthday bonus indicator
        if (message.data.birthday_bonus && message.data.birthday_bonus > 0) {
          lastScanText += " 🎂";
        }

        $("#last-scan").text(lastScanText);

        // Flash effect to show update (special color for birthday)
        const flashColor = message.data.birthday_bonus > 0 ? "#ff69b4" : "#00e0ff";
        $("#last-scan").css("color", flashColor);
        setTimeout(() => $("#last-scan").css("color", ""), 1500);
      }
    });

    // Subscribe to other relevant events
    ablyChannel.subscribe('reward-request', (message) => {
      const token = localStorage.getItem(TOKEN_KEY);
      if (token) loadStatus(token);
    });

  }

  // Cleanup on page unload
  window.addEventListener('beforeunload', () => {
    if (ablyInstance) {
      ablyInstance.close();
      ablyInstance = null;
    }
  });

  // Re-init Ably when store changes
  $(document).on('change', '#ppv-store-selector', function() {
    setTimeout(initAblyRealtime, 500);
  });

});
