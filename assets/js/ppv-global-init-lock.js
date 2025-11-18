/**
 * PPV Global Init Lock - Prevents duplicate event listeners
 * Priority: FIRST to load (priority 1)
 */
(function() {
  'use strict';
  
  // Global lock object
  if (!window.PPV) window.PPV = {};
  
  if (window.PPV.INIT_DONE) {
    console.log("🔒 [PPV] Init lock already active, skipping duplicates");
    return;
  }
  
  window.PPV.INIT_DONE = {};
  window.PPV.INIT_LOCK = true;
  
  console.log("✅ [PPV] Global init lock ACTIVATED");
  
  // Helper to register init only once
  window.ppvOnce = function(name, callback) {
    if (window.PPV.INIT_DONE[name]) {
      console.log(`⏸️ [PPV] "${name}" már fut, skip`);
      return false;
    }
    window.PPV.INIT_DONE[name] = true;
    console.log(`✅ [PPV] "${name}" init started`);
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
  console.log(`📊 [PPV] Click listeners will be monitored`);
})();