/**
 * PunktePass – Centralized API Manager v1.0
 *
 * This file MUST be loaded FIRST before all other PPV JS files!
 * Handles all API requests with:
 * - Request queue (max concurrent requests)
 * - 503 error retry logic
 * - Turbo.js navigation pause
 * - Request deduplication
 * - Global throttling
 */

(function() {
  'use strict';

  // Prevent duplicate initialization
  if (window.PPV_API_MANAGER_LOADED) {
    console.log('⏭️ [API Manager] Already loaded, skipping');
    return;
  }
  window.PPV_API_MANAGER_LOADED = true;

  console.log('✅ PPV API Manager v1.0 loaded');

  // ============================================================
  // 🔧 CONFIGURATION
  // ============================================================
  const CONFIG = {
    maxConcurrent: 2,           // Max simultaneous requests
    retryCount: 2,              // Retry attempts for 503
    retryDelay: 1500,           // Delay between retries (ms)
    requestDelay: 150,          // Delay between queued requests (ms)
    dedupeWindow: 2000,         // Time window for duplicate detection (ms)
    turboNavigationPause: 500,  // Pause requests during Turbo navigation (ms)
  };

  // ============================================================
  // 🗄️ STATE
  // ============================================================
  const state = {
    pending: 0,
    queue: [],
    recentRequests: new Map(),  // For deduplication
    isPaused: false,
    pauseTimeout: null,
  };

  // ============================================================
  // 🚦 REQUEST QUEUE
  // ============================================================
  window.PPV_REQUEST_QUEUE = {
    get pending() { return state.pending; },
    get queueLength() { return state.queue.length; },

    async add(fetchFn, options = {}) {
      const { priority = 0, key = null, skipDedupe = false } = options;

      // Deduplication check
      if (key && !skipDedupe) {
        const now = Date.now();
        const lastRequest = state.recentRequests.get(key);
        if (lastRequest && (now - lastRequest) < CONFIG.dedupeWindow) {
          console.log(`⏭️ [API] Skipping duplicate: ${key}`);
          return Promise.resolve({ skipped: true, reason: 'duplicate' });
        }
        state.recentRequests.set(key, now);
      }

      return new Promise((resolve, reject) => {
        state.queue.push({ fetchFn, resolve, reject, priority, key });
        state.queue.sort((a, b) => b.priority - a.priority);
        this.process();
      });
    },

    async process() {
      // Wait if paused (Turbo navigation)
      if (state.isPaused) {
        console.log('⏸️ [API] Paused, waiting...');
        return;
      }

      // Check concurrent limit
      if (state.pending >= CONFIG.maxConcurrent || state.queue.length === 0) {
        return;
      }

      const { fetchFn, resolve, reject, key } = state.queue.shift();
      state.pending++;

      try {
        const result = await fetchFn();
        resolve(result);
      } catch (e) {
        reject(e);
      } finally {
        state.pending--;
        // Delay before processing next request
        setTimeout(() => this.process(), CONFIG.requestDelay);
      }
    },

    pause(duration = CONFIG.turboNavigationPause) {
      state.isPaused = true;
      console.log(`⏸️ [API] Paused for ${duration}ms`);

      if (state.pauseTimeout) clearTimeout(state.pauseTimeout);
      state.pauseTimeout = setTimeout(() => {
        state.isPaused = false;
        console.log('▶️ [API] Resumed');
        this.process();
      }, duration);
    },

    resume() {
      if (state.pauseTimeout) clearTimeout(state.pauseTimeout);
      state.isPaused = false;
      console.log('▶️ [API] Force resumed');
      this.process();
    },

    clear() {
      state.queue = [];
      console.log('🗑️ [API] Queue cleared');
    }
  };

  // ============================================================
  // 🔄 GLOBAL FETCH WRAPPER
  // ============================================================
  window.ppvFetch = async function(url, options = {}, fetchOptions = {}) {
    const {
      retries = CONFIG.retryCount,
      priority = 0,
      skipDedupe = false,
      key = null
    } = fetchOptions;

    // Generate deduplication key from URL if not provided
    const dedupeKey = key || `${options.method || 'GET'}:${url}`;

    const doFetch = async (retriesLeft) => {
      try {
        const res = await fetch(url, options);

        // Handle 503 errors with retry
        if (res.status === 503) {
          if (retriesLeft > 0) {
            console.warn(`⚠️ [API] 503 error on ${url}, retrying in ${CONFIG.retryDelay}ms... (${retriesLeft} left)`);
            await new Promise(r => setTimeout(r, CONFIG.retryDelay));
            return doFetch(retriesLeft - 1);
          }
          console.error(`❌ [API] 503 error on ${url}, no retries left`);
          throw new Error('Server überlastet. Bitte Seite neu laden.');
        }

        return res;
      } catch (e) {
        if (retriesLeft > 0 && e.name !== 'AbortError') {
          console.warn(`⚠️ [API] Network error on ${url}, retrying... (${retriesLeft} left)`);
          await new Promise(r => setTimeout(r, CONFIG.retryDelay));
          return doFetch(retriesLeft - 1);
        }
        throw e;
      }
    };

    return window.PPV_REQUEST_QUEUE.add(
      () => doFetch(retries),
      { priority, key: dedupeKey, skipDedupe }
    );
  };

  // Alias for backwards compatibility
  window.apiFetch = window.ppvFetch;

  // ============================================================
  // 🔄 TURBO.JS INTEGRATION
  // ============================================================

  // Pause requests when Turbo starts navigation
  document.addEventListener('turbo:before-visit', function() {
    console.log('🔄 [API] Turbo navigation starting, pausing queue...');
    window.PPV_REQUEST_QUEUE.pause(CONFIG.turboNavigationPause);
  });

  // Also pause on turbo:before-render to catch all cases
  document.addEventListener('turbo:before-render', function() {
    window.PPV_REQUEST_QUEUE.pause(300);
  });

  // Resume after navigation completes
  document.addEventListener('turbo:load', function() {
    // Small delay to let DOM settle before resuming
    setTimeout(() => {
      window.PPV_REQUEST_QUEUE.resume();
    }, 200);
  });

  // Clear queue on turbo:before-cache (user navigating away)
  document.addEventListener('turbo:before-cache', function() {
    window.PPV_REQUEST_QUEUE.clear();
  });

  // ============================================================
  // 🧹 CLEANUP - Remove old requests from deduplication map
  // ============================================================
  setInterval(() => {
    const now = Date.now();
    const expiry = CONFIG.dedupeWindow * 2;

    for (const [key, timestamp] of state.recentRequests) {
      if (now - timestamp > expiry) {
        state.recentRequests.delete(key);
      }
    }
  }, 30000); // Clean up every 30 seconds

  // ============================================================
  // 📊 DEBUG HELPERS
  // ============================================================
  window.PPV_API_DEBUG = {
    getState() {
      return {
        pending: state.pending,
        queueLength: state.queue.length,
        isPaused: state.isPaused,
        recentRequestsCount: state.recentRequests.size,
        config: CONFIG
      };
    },

    setMaxConcurrent(n) {
      CONFIG.maxConcurrent = n;
      console.log(`✅ [API] Max concurrent set to ${n}`);
    },

    setRetryDelay(ms) {
      CONFIG.retryDelay = ms;
      console.log(`✅ [API] Retry delay set to ${ms}ms`);
    }
  };

})();
