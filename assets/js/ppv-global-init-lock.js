/**
 * PPV Global Init Lock - Prevents duplicate event listeners
 * Priority: FIRST to load (priority 1)
 */
(function() {
  'use strict';
  
  // Global lock object
  if (!window.PPV) window.PPV = {};
  
  if (window.PPV.INIT_DONE) {
    return;
  }
  
  window.PPV.INIT_DONE = {};
  window.PPV.INIT_LOCK = true;
  
  
  // Helper to register init only once
  window.ppvOnce = function(name, callback) {
    if (window.PPV.INIT_DONE[name]) {
      return false;
    }
    window.PPV.INIT_DONE[name] = true;
    try {
      callback();
    } catch(e) {
      console.error(`❌ [PPV] "${name}" error:`, e);
    }
    return true;
  };
  
  // Remove duplicate click listeners (safety net)
  const origAddEventListener = document.addEventListener;
  let clickCount = 0;
  
  // Don't actually override, just log (safer approach)
})();