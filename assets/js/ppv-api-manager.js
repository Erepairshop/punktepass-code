/**
 * PunktePass – Centralized API Manager v2.0
 *
 * This file MUST be loaded FIRST before all other PPV JS files!
 * Handles all API requests with:
 * - Request queue (max concurrent requests)
 * - 503 error retry with EXPONENTIAL BACKOFF
 * - Circuit breaker pattern (prevents server flooding)
 * - Turbo.js navigation pause + smart queue clear
 * - Request deduplication
 * - Global throttling
 * - Flag timeout auto-reset
 *
 * v2.0 Changes:
 * - Exponential backoff (2s → 4s → 8s) instead of fixed 2s
 * - Circuit breaker: stops requests after 5 consecutive failures
 * - Longer Turbo pause (2500ms) to match retry timing
 * - Smart queue clearing (once, not 3x)
 * - Flag timeout system for polling/slider
 */

(function() {
  'use strict';

  // Prevent duplicate initialization
  if (window.PPV_API_MANAGER_LOADED) {
    console.log('[API Manager] Already loaded, skipping');
    return;
  }
  window.PPV_API_MANAGER_LOADED = true;

  console.log('PPV API Manager v2.0 loaded');

  // ============================================================
  // CONFIGURATION - Optimized for stability
  // ============================================================
  const CONFIG = {
    maxConcurrent: 1,           // Only 1 request at a time
    retryCount: 3,              // Retry attempts for 503
    retryDelayBase: 2000,       // Base delay for exponential backoff (ms)
    requestDelay: 300,          // Delay between queued requests (ms)
    dedupeWindow: 3000,         // Time window for duplicate detection (ms)
    turboNavigationPause: 2500, // Pause during Turbo navigation (ms) - increased!
    initialDelay: 800,          // Delay before first request after navigation
    circuitBreakerThreshold: 5, // Consecutive failures before circuit opens
    circuitBreakerResetTime: 30000, // Time to reset circuit breaker (ms)
    flagTimeout: 15000,         // Auto-reset stuck flags after 15s
  };

  // ============================================================
  // STATE
  // ============================================================
  const state = {
    pending: 0,
    queue: [],
    recentRequests: new Map(),  // For deduplication
    isPaused: false,
    pauseTimeout: null,
    // Circuit breaker state
    consecutiveFailures: 0,
    circuitOpen: false,
    circuitOpenTime: null,
    // Navigation state
    isNavigating: false,
    queueCleared: false,  // Prevent multiple clears per navigation
  };

  // ============================================================
  // CIRCUIT BREAKER
  // ============================================================
  const circuitBreaker = {
    recordSuccess() {
      state.consecutiveFailures = 0;
      if (state.circuitOpen) {
        console.log('[API] Circuit breaker CLOSED - requests resuming');
        state.circuitOpen = false;
        state.circuitOpenTime = null;
      }
    },

    recordFailure() {
      state.consecutiveFailures++;
      console.warn(`[API] Failure recorded (${state.consecutiveFailures}/${CONFIG.circuitBreakerThreshold})`);

      if (state.consecutiveFailures >= CONFIG.circuitBreakerThreshold) {
        console.error('[API] Circuit breaker OPEN - stopping requests for', CONFIG.circuitBreakerResetTime/1000, 's');
        state.circuitOpen = true;
        state.circuitOpenTime = Date.now();

        // Auto-reset after timeout
        setTimeout(() => {
          if (state.circuitOpen) {
            console.log('[API] Circuit breaker auto-reset');
            state.circuitOpen = false;
            state.circuitOpenTime = null;
            state.consecutiveFailures = 0;
            window.PPV_REQUEST_QUEUE.process();
          }
        }, CONFIG.circuitBreakerResetTime);
      }
    },

    isOpen() {
      if (!state.circuitOpen) return false;

      // Check if enough time has passed to try again
      const elapsed = Date.now() - state.circuitOpenTime;
      if (elapsed >= CONFIG.circuitBreakerResetTime) {
        state.circuitOpen = false;
        state.circuitOpenTime = null;
        state.consecutiveFailures = 0;
        return false;
      }

      return true;
    }
  };

  // ============================================================
  // FLAG TIMEOUT MANAGER - Prevents stuck flags
  // ============================================================
  window.PPV_FLAG_TIMEOUTS = window.PPV_FLAG_TIMEOUTS || {};

  window.PPV_SET_FLAG = function(flagName, value) {
    window[flagName] = value;

    // Clear existing timeout
    if (window.PPV_FLAG_TIMEOUTS[flagName]) {
      clearTimeout(window.PPV_FLAG_TIMEOUTS[flagName]);
      delete window.PPV_FLAG_TIMEOUTS[flagName];
    }

    // Set auto-reset timeout if flag is true
    if (value === true) {
      window.PPV_FLAG_TIMEOUTS[flagName] = setTimeout(() => {
        if (window[flagName] === true) {
          console.warn(`[API] Auto-resetting stuck flag: ${flagName}`);
          window[flagName] = false;
        }
        delete window.PPV_FLAG_TIMEOUTS[flagName];
      }, CONFIG.flagTimeout);
    }
  };

  window.PPV_CLEAR_FLAG = function(flagName) {
    window[flagName] = false;
    if (window.PPV_FLAG_TIMEOUTS[flagName]) {
      clearTimeout(window.PPV_FLAG_TIMEOUTS[flagName]);
      delete window.PPV_FLAG_TIMEOUTS[flagName];
    }
  };

  // ============================================================
  // REQUEST QUEUE
  // ============================================================
  window.PPV_REQUEST_QUEUE = {
    get pending() { return state.pending; },
    get queueLength() { return state.queue.length; },
    get isCircuitOpen() { return circuitBreaker.isOpen(); },

    async add(fetchFn, options = {}) {
      const { priority = 0, key = null, skipDedupe = false } = options;

      // Circuit breaker check
      if (circuitBreaker.isOpen()) {
        console.warn('[API] Circuit breaker open - request rejected');
        return Promise.reject(new Error('Server temporarily unavailable'));
      }

      // Deduplication check
      if (key && !skipDedupe) {
        const now = Date.now();
        const lastRequest = state.recentRequests.get(key);
        if (lastRequest && (now - lastRequest) < CONFIG.dedupeWindow) {
          console.log(`[API] Skipping duplicate: ${key}`);
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
        return;
      }

      // Circuit breaker check
      if (circuitBreaker.isOpen()) {
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
        circuitBreaker.recordSuccess();
        resolve(result);
      } catch (e) {
        // Only record failure for server errors, not user aborts
        if (e.name !== 'AbortError') {
          circuitBreaker.recordFailure();
        }
        reject(e);
      } finally {
        state.pending--;
        // Delay before processing next request
        setTimeout(() => this.process(), CONFIG.requestDelay);
      }
    },

    pause(duration = CONFIG.turboNavigationPause) {
      state.isPaused = true;
      console.log(`[API] Paused for ${duration}ms`);

      if (state.pauseTimeout) clearTimeout(state.pauseTimeout);
      state.pauseTimeout = setTimeout(() => {
        state.isPaused = false;
        console.log('[API] Resumed');
        this.process();
      }, duration);
    },

    resume() {
      if (state.pauseTimeout) clearTimeout(state.pauseTimeout);
      state.isPaused = false;
      console.log('[API] Force resumed');
      this.process();
    },

    clear() {
      const cleared = state.queue.length;
      state.queue = [];
      if (cleared > 0) {
        console.log(`[API] Queue cleared (${cleared} items)`);
      }
    }
  };

  // ============================================================
  // EXPONENTIAL BACKOFF CALCULATOR
  // ============================================================
  const getRetryDelay = (attempt) => {
    // attempt 0 = first retry = 2s
    // attempt 1 = second retry = 4s
    // attempt 2 = third retry = 8s
    const delay = CONFIG.retryDelayBase * Math.pow(2, attempt);
    // Add some jitter (0-500ms) to prevent thundering herd
    const jitter = Math.random() * 500;
    return delay + jitter;
  };

  // ============================================================
  // GLOBAL FETCH WRAPPER
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

    const doFetch = async (retriesLeft, attempt = 0) => {
      try {
        const res = await fetch(url, options);

        // Handle 503 errors with exponential backoff
        if (res.status === 503) {
          if (retriesLeft > 0) {
            const delay = getRetryDelay(attempt);
            console.warn(`[API] 503 on ${url}, retry in ${Math.round(delay)}ms (${retriesLeft} left)`);
            await new Promise(r => setTimeout(r, delay));
            return doFetch(retriesLeft - 1, attempt + 1);
          }
          console.error(`[API] 503 on ${url}, no retries left`);
          throw new Error('Server überlastet. Bitte Seite neu laden.');
        }

        return res;
      } catch (e) {
        if (retriesLeft > 0 && e.name !== 'AbortError') {
          const delay = getRetryDelay(attempt);
          console.warn(`[API] Network error on ${url}, retry in ${Math.round(delay)}ms (${retriesLeft} left)`);
          await new Promise(r => setTimeout(r, delay));
          return doFetch(retriesLeft - 1, attempt + 1);
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
  // TURBO.JS INTEGRATION - Smart queue management
  // ============================================================

  // SMART CLEAR: Only clear once per navigation cycle
  const smartClear = () => {
    if (state.queueCleared) return;
    state.queueCleared = true;
    window.PPV_REQUEST_QUEUE.clear();
    window.PPV_REQUEST_QUEUE.pause(CONFIG.turboNavigationPause);
  };

  // Navigation starting - clear queue ONCE
  document.addEventListener('turbo:before-visit', function() {
    console.log('[API] Turbo navigation starting');
    state.isNavigating = true;
    state.queueCleared = false;  // Reset for new navigation
    smartClear();
  });

  // Page rendering - don't clear again, just ensure paused
  document.addEventListener('turbo:before-render', function() {
    if (!state.isPaused) {
      window.PPV_REQUEST_QUEUE.pause(500);
    }
  });

  // Navigation complete - resume with delay
  document.addEventListener('turbo:load', function() {
    console.log('[API] Turbo load complete');
    state.isNavigating = false;
    state.queueCleared = false;

    // Long delay to let all JS initialize before allowing requests
    setTimeout(() => {
      if (!state.isNavigating) {
        console.log('[API] Resuming queue after navigation');
        window.PPV_REQUEST_QUEUE.resume();
      }
    }, CONFIG.initialDelay);
  });

  // Before caching - clear queue
  document.addEventListener('turbo:before-cache', function() {
    window.PPV_REQUEST_QUEUE.clear();
  });

  // ============================================================
  // CLEANUP - Remove old requests from deduplication map
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
  // DEBUG HELPERS
  // ============================================================
  window.PPV_API_DEBUG = {
    getState() {
      return {
        pending: state.pending,
        queueLength: state.queue.length,
        isPaused: state.isPaused,
        isNavigating: state.isNavigating,
        circuitOpen: state.circuitOpen,
        consecutiveFailures: state.consecutiveFailures,
        recentRequestsCount: state.recentRequests.size,
        config: CONFIG
      };
    },

    resetCircuitBreaker() {
      state.circuitOpen = false;
      state.circuitOpenTime = null;
      state.consecutiveFailures = 0;
      console.log('[API] Circuit breaker manually reset');
    },

    setConfig(key, value) {
      if (CONFIG.hasOwnProperty(key)) {
        CONFIG[key] = value;
        console.log(`[API] Config ${key} set to ${value}`);
      }
    }
  };

})();
