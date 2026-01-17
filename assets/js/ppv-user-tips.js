/**
 * PunktePass - User Tips Display
 * Megjelenít személyre szabott tippeket a felhasználónak
 *
 * Version: 1.0
 */

(function() {
  'use strict';

  // Config
  const CHECK_INTERVAL = 60000; // Check every 60 seconds
  const SHOW_DELAY = 3000;      // Wait 3s after page load before showing
  let tipCheckTimer = null;
  let isShowingTip = false;

  // Get language from cookie or default
  function getLang() {
    const match = document.cookie.match(/ppv_lang=([a-z]{2})/);
    return match ? match[1] : 'de';
  }

  // Get REST base URL
  function getRestBase() {
    return window.ppvConfig?.restBase || '/wp-json/ppv/v1/';
  }

  // Fetch pending tips
  async function fetchPendingTip() {
    try {
      const lang = getLang();
      const response = await fetch(`${getRestBase()}tips/pending?lang=${lang}`, {
        credentials: 'same-origin',
        headers: {
          'X-WP-Nonce': window.ppvConfig?.nonce || ''
        }
      });

      if (!response.ok) return null;

      const data = await response.json();
      return data.success ? data.tip : null;
    } catch (err) {
      console.error('[PPV Tips] Error fetching tips:', err);
      return null;
    }
  }

  // Mark tip as shown
  async function markTipShown(tipKey) {
    try {
      await fetch(`${getRestBase()}tips/${tipKey}/shown`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'X-WP-Nonce': window.ppvConfig?.nonce || ''
        }
      });
    } catch (err) {
      console.error('[PPV Tips] Error marking tip shown:', err);
    }
  }

  // Mark tip as dismissed
  async function dismissTip(tipKey) {
    try {
      await fetch(`${getRestBase()}tips/${tipKey}/dismiss`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'X-WP-Nonce': window.ppvConfig?.nonce || ''
        }
      });
    } catch (err) {
      console.error('[PPV Tips] Error dismissing tip:', err);
    }
  }

  // Mark tip as clicked (user took action)
  async function clickTip(tipKey) {
    try {
      await fetch(`${getRestBase()}tips/${tipKey}/clicked`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'X-WP-Nonce': window.ppvConfig?.nonce || ''
        }
      });
    } catch (err) {
      console.error('[PPV Tips] Error marking tip clicked:', err);
    }
  }

  // Create toast element
  function createTipToast(tip) {
    // Remove existing toast if any
    const existing = document.querySelector('.ppv-tip-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'ppv-tip-toast';
    toast.innerHTML = `
      <div class="ppv-tip-toast-content">
        <button class="ppv-tip-close" aria-label="Close">
          <i class="ri-close-line"></i>
        </button>
        <div class="ppv-tip-icon">
          <i class="${tip.icon}"></i>
        </div>
        <div class="ppv-tip-body">
          <h4 class="ppv-tip-title">${escapeHtml(tip.title)}</h4>
          <p class="ppv-tip-message">${escapeHtml(tip.message)}</p>
        </div>
        <button class="ppv-tip-action" data-url="${escapeHtml(tip.action_url)}">
          ${escapeHtml(tip.button)}
          <i class="ri-arrow-right-line"></i>
        </button>
      </div>
    `;

    // Add styles if not already added
    if (!document.querySelector('#ppv-tip-styles')) {
      const styles = document.createElement('style');
      styles.id = 'ppv-tip-styles';
      styles.textContent = `
        .ppv-tip-toast {
          position: fixed;
          bottom: 80px;
          left: 50%;
          transform: translateX(-50%) translateY(100px);
          z-index: 10000;
          width: calc(100% - 32px);
          max-width: 400px;
          opacity: 0;
          transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
          pointer-events: none;
        }
        .ppv-tip-toast.show {
          transform: translateX(-50%) translateY(0);
          opacity: 1;
          pointer-events: all;
        }
        .ppv-tip-toast-content {
          background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
          border: 1px solid rgba(255, 255, 255, 0.1);
          border-radius: 16px;
          padding: 16px;
          box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
          position: relative;
        }
        .ppv-tip-close {
          position: absolute;
          top: 8px;
          right: 8px;
          background: rgba(255, 255, 255, 0.1);
          border: none;
          color: #94a3b8;
          width: 28px;
          height: 28px;
          border-radius: 50%;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
          transition: all 0.2s;
        }
        .ppv-tip-close:hover {
          background: rgba(255, 255, 255, 0.2);
          color: #fff;
        }
        .ppv-tip-icon {
          width: 48px;
          height: 48px;
          background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
          border-radius: 12px;
          display: flex;
          align-items: center;
          justify-content: center;
          margin-bottom: 12px;
        }
        .ppv-tip-icon i {
          font-size: 24px;
          color: #fff;
        }
        .ppv-tip-title {
          color: #fff;
          font-size: 16px;
          font-weight: 600;
          margin: 0 0 6px 0;
        }
        .ppv-tip-message {
          color: #94a3b8;
          font-size: 14px;
          line-height: 1.5;
          margin: 0 0 16px 0;
        }
        .ppv-tip-action {
          width: 100%;
          background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
          border: none;
          color: #fff;
          padding: 12px 20px;
          border-radius: 10px;
          font-size: 14px;
          font-weight: 600;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
          transition: all 0.2s;
        }
        .ppv-tip-action:hover {
          transform: translateY(-2px);
          box-shadow: 0 4px 12px rgba(6, 182, 212, 0.4);
        }
        .ppv-tip-action i {
          font-size: 16px;
        }

        /* Dark theme support */
        @media (prefers-color-scheme: light) {
          .ppv-tip-toast-content {
            background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
            border-color: rgba(0, 0, 0, 0.1);
          }
          .ppv-tip-title { color: #1e293b; }
          .ppv-tip-message { color: #64748b; }
          .ppv-tip-close { color: #64748b; }
        }
      `;
      document.head.appendChild(styles);
    }

    document.body.appendChild(toast);

    // Event listeners
    const closeBtn = toast.querySelector('.ppv-tip-close');
    const actionBtn = toast.querySelector('.ppv-tip-action');

    closeBtn.addEventListener('click', () => {
      hideTipToast(toast);
      dismissTip(tip.key);
    });

    actionBtn.addEventListener('click', () => {
      hideTipToast(toast);
      clickTip(tip.key);

      // Handle special actions
      if (tip.action_url === 'rate_app') {
        // Open app store rating
        if (window.ppvPlatform === 'ios') {
          window.location.href = 'https://apps.apple.com/app/punktepass/id...'; // iOS App Store
        } else if (window.ppvPlatform === 'android') {
          window.location.href = 'https://play.google.com/store/apps/details?id=...'; // Play Store
        }
      } else if (tip.action_url) {
        window.location.href = tip.action_url;
      }
    });

    return toast;
  }

  // Show toast
  function showTipToast(toast) {
    requestAnimationFrame(() => {
      toast.classList.add('show');
    });
  }

  // Hide toast
  function hideTipToast(toast) {
    toast.classList.remove('show');
    isShowingTip = false;
    setTimeout(() => {
      toast.remove();
    }, 400);
  }

  // Escape HTML
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Check and show tip
  async function checkAndShowTip() {
    if (isShowingTip) return;

    const tip = await fetchPendingTip();
    if (!tip) return;

    isShowingTip = true;
    const toast = createTipToast(tip);

    // Show after a brief delay
    setTimeout(() => {
      showTipToast(toast);
      markTipShown(tip.key);
    }, 300);

    // Auto-hide after 15 seconds (unless user interacts)
    setTimeout(() => {
      if (document.querySelector('.ppv-tip-toast.show')) {
        hideTipToast(toast);
        // Don't dismiss - just hide. Will show again next time.
      }
    }, 15000);
  }

  // Initialize
  function init() {
    // Only run on user dashboard pages
    if (!document.body.classList.contains('ppv-user-dashboard') &&
        !window.location.pathname.includes('/app') &&
        !window.location.pathname.includes('/dashboard')) {
      return;
    }

    // Check for tips after initial delay
    setTimeout(() => {
      checkAndShowTip();

      // Then check periodically
      tipCheckTimer = setInterval(checkAndShowTip, CHECK_INTERVAL);
    }, SHOW_DELAY);

    // Stop checking when page is hidden
    document.addEventListener('visibilitychange', () => {
      if (document.hidden && tipCheckTimer) {
        clearInterval(tipCheckTimer);
        tipCheckTimer = null;
      } else if (!document.hidden && !tipCheckTimer) {
        tipCheckTimer = setInterval(checkAndShowTip, CHECK_INTERVAL);
      }
    });
  }

  // Start when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Expose for manual triggering
  window.ppvTips = {
    check: checkAndShowTip,
    dismiss: dismissTip
  };

})();
