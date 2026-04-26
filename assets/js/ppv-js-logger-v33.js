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

  // --- Filter: NEM logoljuk ezeket (external/extension/CORS-mask noise, nem PP bug) ---
  const NOISE_PATTERNS = [
    /^Script error\.?$/i,                    // generic CORS-blokkolt 3rd-party hiba (üzenet nélkül)
    /__firefox__/,                            // Firefox Focus iOS reader extension
    /window\.ethereum/,                       // MetaMask / crypto wallet
    /__BRAVE_/,                               // Brave Shield
    /chrome-extension:/,                      // Chrome extension források
    /moz-extension:/,                         // Firefox extension források
    /safari-extension:/,                      // Safari extension források
    /ResizeObserver loop/,                    // jóindulatú browser warning, nem hiba
    /Non-Error promise rejection captured/,   // Sentry-szerű generikus
    /Cannot redefine property: googletag/,   // Google Ads SDK
    /\bKaspersky\b/i,                         // Kaspersky AV inject
    /AbortError/,                             // user navigated away (route change közben)
  ];
  function isNoise(message) {
    if (!message || typeof message !== "string") return false;
    return NOISE_PATTERNS.some((re) => re.test(message));
  }

  // --- Helper: küldés (sendBeacon fallback navigation alatt is megérkezik) ---
  async function sendLog(url, payload) {
    if (!url) return;
    const body = JSON.stringify(payload);
    // Beacon: megbízhatóbban túléli a unload/navigate-et (max 64KB)
    if (navigator.sendBeacon && body.length < 60000) {
      try {
        const blob = new Blob([body], { type: "application/json" });
        if (navigator.sendBeacon(url, blob)) return;
      } catch (e) { /* fallthrough to fetch */ }
    }
    try {
      await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body,
        keepalive: true, // unload alatt is megpróbálja
      });
    } catch (e) {
      if (PPV_DEBUG) console.warn("⚠️ Logger send error:", e);
    }
  }

  // --- JS hibák (uncaught + capture phase a resource-loading errorokhoz) ---
  window.addEventListener("error", (e) => {
    // Resource load failure (img/script/link/audio/video 404, dekódolási hiba)
    if (e.target && e.target !== window && (e.target.src || e.target.href)) {
      const src = e.target.src || e.target.href;
      if (!shouldSend("res:" + src)) return;
      sendLog(API_JS, {
        message: "Resource load failed: " + (e.target.tagName || "?"),
        file: src,
        line: 0,
        browser: navigator.userAgent,
        type: "resource",
      });
      return;
    }
    // Sima JS error
    if (isNoise(e.message)) return;
    if (!shouldSend("js:" + e.message)) return;
    sendLog(API_JS, {
      message: e.message,
      file: e.filename,
      line: e.lineno,
      col: e.colno,
      stack: e.error && e.error.stack ? String(e.error.stack).substring(0, 800) : "",
      browser: navigator.userAgent,
      type: "js",
    });
  }, true); // capture phase: így a resource load failure is bubble-ödik ide

  window.addEventListener("unhandledrejection", (e) => {
    const reason = e.reason;
    let msg = "Unhandled Promise Rejection";
    let stack = "";
    if (reason) {
      if (typeof reason === "string") msg = reason;
      else if (reason.message) { msg = reason.message; stack = reason.stack ? String(reason.stack).substring(0, 800) : ""; }
      else { try { msg = JSON.stringify(reason).substring(0, 300); } catch (_) { msg = String(reason); } }
    }
    if (isNoise(msg)) return;
    if (!shouldSend("js:" + msg)) return;
    sendLog(API_JS, {
      message: msg,
      file: "Promise",
      line: 0,
      stack,
      browser: navigator.userAgent,
      type: "promise",
    });
  });

  // --- console.error / console.warn override (manualis hibalogolas elkapasa) ---
  ["error", "warn"].forEach(level => {
    const orig = console[level];
    console[level] = function(...args) {
      try {
        const msg = args.map(a => {
          if (a instanceof Error) return a.message + (a.stack ? "\n" + a.stack : "");
          if (typeof a === "object") { try { return JSON.stringify(a); } catch (_) { return String(a); } }
          return String(a);
        }).join(" ").substring(0, 500);
        if (isNoise(msg)) { return orig.apply(console, args); }
        if (shouldSend("console:" + level + ":" + msg)) {
          sendLog(API_JS, {
            message: "console." + level + ": " + msg,
            file: window.location.pathname,
            line: 0,
            browser: navigator.userAgent,
            type: "console-" + level,
          });
        }
      } catch (_) { /* never break logging */ }
      return orig.apply(console, args);
    };
  });

  // --- CSP violation (Content Security Policy blokkolt eroforras) ---
  document.addEventListener("securitypolicyviolation", (e) => {
    const key = "csp:" + e.violatedDirective + ":" + e.blockedURI;
    if (!shouldSend(key)) return;
    sendLog(API_JS, {
      message: "CSP violation: " + e.violatedDirective + " blocked " + (e.blockedURI || "(inline)"),
      file: e.sourceFile || window.location.pathname,
      line: e.lineNumber || 0,
      browser: navigator.userAgent,
      type: "csp",
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
