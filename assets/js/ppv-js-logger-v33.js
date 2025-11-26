/**
 * PunktePass – JS Logger v4.0 (Stable)
 * Teljes JS + AJAX + UI + REST monitor
 */

(() => {
  // ✅ DEBUG mode - set to true for verbose logging
  const PPV_DEBUG = false;
  const ppvLog = (...args) => { if (PPV_DEBUG) console.log(...args); };

  ppvLog("🧠 PPV JS Logger v4.0 aktiv");

  // --- CONFIG ---
  const API_JS = PPV_LOG_API?.js_url || "";
  const API_AJAX = PPV_LOG_API?.ajax_url || "";
  const THROTTLE_MS = 2000; // min 2s per log type
  const sentEvents = new Map();

  // --- Helper: időkorlát duplikált logok ellen ---
  const shouldSend = (key) => {
    const now = Date.now();
    if (sentEvents.has(key) && now - sentEvents.get(key) < THROTTLE_MS) return false;
    sentEvents.set(key, now);
    return true;
  };

  // --- Helper: küldés ---
  async function sendLog(url, payload) {
    if (!url) return;
    try {
      await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
    } catch (e) {
      if (PPV_DEBUG) console.warn("⚠️ Logger send error:", e);
    }
  }

  // --- JS hibák ---
  window.addEventListener("error", (e) => {
    if (!shouldSend("js:" + e.message)) return;
    sendLog(API_JS, {
      message: e.message,
      file: e.filename,
      line: e.lineno,
      browser: navigator.userAgent,
    });
  });

  window.addEventListener("unhandledrejection", (e) => {
    const msg = e.reason ? e.reason.toString() : "Unhandled Promise Rejection";
    if (!shouldSend("js:" + msg)) return;
    sendLog(API_JS, {
      message: msg,
      file: "Promise",
      line: 0,
      browser: navigator.userAgent,
    });
  });

  // --- User Interaction ---
  ["click", "submit", "change"].forEach((evt) => {
    document.addEventListener(evt, (e) => {
      const t = e.target.closest("button, a, input, form, select, textarea");
      if (!t) return;
      const info = {
        tag: t.tagName.toLowerCase(),
        cls: t.className || "(no-class)",
        id: t.id || "(no-id)",
        text: (t.innerText || t.value || "").trim().substring(0, 60),
        event: evt,
        page: window.location.pathname,
      };
      const key = `${evt}:${info.cls}:${info.id}`;
      if (!shouldSend(key)) return;
      sendLog(API_AJAX, {
        url: "UI-Event",
        status: "info",
        statusText: "User Interaction",
        body: info,
      });
    });
  });

  // --- Fetch Interceptor (REST/AJAX monitor) ---
  const origFetch = window.fetch;
  window.fetch = async function (...args) {
    const url = args[0];
    const opts = args[1] || {};
    const start = performance.now();

    if (url.includes("/ppv/v1/log/")) {
      return origFetch(...args);
    }

    try {
      const res = await origFetch(...args);
      const clone = res.clone();
      const text = await clone.text();
      const duration = (performance.now() - start).toFixed(0) + "ms";

      // Csak PPV vagy admin-ajax hívásokat logolunk
      if (url.includes("admin-ajax.php") || url.includes("/wp-json/ppv/")) {
        const key = "fetch:" + url;
        if (shouldSend(key)) {
          sendLog(API_AJAX, {
            url,
            status: res.status,
            statusText: res.statusText || "OK",
            duration,
            body: opts.body ? opts.body.toString().substring(0, 200) : "(none)",
            response: text.substring(0, 400),
          });
        }
      }
      return res;
    } catch (err) {
      sendLog(API_AJAX, {
        url: args[0],
        status: 0,
        statusText: "Network Error: " + err.message,
      });
      throw err;
    }
  };

  // --- Debug overlay (ha ?debug=1) ---
  if (window.location.search.includes("debug=1")) {
    const dbg = document.createElement("div");
    dbg.id = "ppv-debug-overlay";
    Object.assign(dbg.style, {
      position: "fixed",
      bottom: "0",
      right: "0",
      width: "400px",
      height: "200px",
      background: "rgba(0,0,0,0.8)",
      color: "#0f0",
      fontSize: "11px",
      padding: "5px",
      overflowY: "auto",
      zIndex: 999999,
    });
    document.body.appendChild(dbg);

      const line = document.createElement("div");
      line.textContent = args.join(" ");
      dbg.appendChild(line);
      dbg.scrollTop = dbg.scrollHeight;
      oldLog.apply(console, args);
    };

    ppvLog("🧾 Debug Overlay aktiv (v4.0)");
  }

  // --- Self-Test ---
  async function selfTest() {
    const checks = {
      hasPPV: typeof PPV_LOG_API !== "undefined",
      hasJS: !!API_JS,
      hasAJAX: !!API_AJAX,
    };
    try {
      await sendLog(API_AJAX, {
        url: "SELFTEST",
        status: "info",
        statusText: "SelfTest OK",
        body: checks,
      });
      ppvLog("✅ PPV Logger SelfTest OK:", checks);
    } catch (e) {
      if (PPV_DEBUG) console.warn("⚠️ PPV Logger SelfTest failed:", e);
    }
  }

  document.addEventListener("DOMContentLoaded", () => setTimeout(selfTest, 500));
})();
