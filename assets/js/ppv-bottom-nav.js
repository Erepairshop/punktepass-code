/**
 * PunktePass – Bottom Nav v4.0 (Pure SPA Edition)
 *
 * Features:
 * - Zero page refresh navigation via fetch
 * - Instant content swap (~50ms)
 * - History API for back/forward support
 * - Page content caching for instant revisits
 * - Smooth transitions
 *
 * v4.0 Changes:
 * - Removed Turbo.js dependency
 * - Pure fetch-based SPA navigation
 * - Content caching for speed
 */
(function() {
  'use strict';

  // Content cache for instant revisits
  const pageCache = new Map();
  const CACHE_TTL = 60000; // 1 minute cache

  // Navigation state
  let isNavigating = false;
  let lastClickTime = 0;

  // Main content selectors (in priority order)
  const CONTENT_SELECTORS = [
    '#ppv-my-points-app',
    '#ppv-dashboard-root',
    '.ppv-profile-container',
    '.ppv-qr-wrapper',
    '.ppv-rewards-container',
    '.ppv-settings-container',
    '.ppv-statistik-container',
    'main.ppv-main',
    'main',
    '#main-content',
    '.site-content',
    '#content'
  ];

  // Find main content container
  const findContentContainer = (doc = document) => {
    for (const selector of CONTENT_SELECTORS) {
      const el = doc.querySelector(selector);
      if (el) return el;
    }
    return null;
  };

  // Update active nav item
  const updateActiveNav = (path) => {
    const cleanPath = path.replace(/\/+$/, '');
    document.querySelectorAll('.ppv-bottom-nav .nav-item').forEach(item => {
      const href = item.getAttribute('href');
      if (href && href !== '#') {
        const itemPath = href.replace(/\/+$/, '');
        item.classList.toggle('active', cleanPath === itemPath);
      }
    });
  };

  // Fetch and cache page content
  const fetchPage = async (url) => {
    // Check cache first
    const cached = pageCache.get(url);
    if (cached && Date.now() - cached.time < CACHE_TTL) {
      console.log('[SPA] Cache hit:', url);
      return cached.html;
    }

    console.log('[SPA] Fetching:', url);
    const response = await fetch(url, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-PPV-SPA': 'true'
      },
      credentials: 'same-origin'
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const html = await response.text();

    // Cache the response
    pageCache.set(url, { html, time: Date.now() });

    return html;
  };

  // Parse HTML and extract content
  const parseContent = (html) => {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');

    // Find content in the fetched page
    const newContent = findContentContainer(doc);
    if (!newContent) {
      console.warn('[SPA] Could not find content container in response');
      return null;
    }

    // Also get the page title
    const title = doc.querySelector('title')?.textContent || document.title;

    // Get any inline scripts that need to run
    const scripts = doc.querySelectorAll('script:not([src])');

    return { content: newContent, title, scripts };
  };

  // Navigate to a new page
  const navigateTo = async (url, pushState = true) => {
    if (isNavigating) return;

    const currentPath = window.location.pathname.replace(/\/+$/, '');
    const targetPath = new URL(url, window.location.origin).pathname.replace(/\/+$/, '');

    // Skip if same page
    if (currentPath === targetPath) {
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

    isNavigating = true;
    const startTime = performance.now();

    // Add loading state
    document.body.classList.add('ppv-spa-loading');

    try {
      // Fetch the new page
      const html = await fetchPage(url);

      // Parse the content
      const parsed = parseContent(html);
      if (!parsed) {
        // Fallback to regular navigation
        window.location.href = url;
        return;
      }

      // Find current content container
      const currentContainer = findContentContainer();
      if (!currentContainer) {
        window.location.href = url;
        return;
      }

      // Fade out current content
      currentContainer.style.opacity = '0';
      currentContainer.style.transition = 'opacity 0.15s ease-out';

      await new Promise(r => setTimeout(r, 150));

      // Replace content
      currentContainer.innerHTML = parsed.content.innerHTML;

      // Update title
      document.title = parsed.title;

      // Update URL
      if (pushState) {
        history.pushState({ url }, parsed.title, url);
      }

      // Update active nav
      updateActiveNav(targetPath);

      // Fade in new content
      currentContainer.style.opacity = '1';

      // Execute inline scripts (only PPV-specific ones to avoid conflicts)
      parsed.scripts.forEach(script => {
        const content = script.textContent || '';
        // Skip scripts that would cause conflicts (already declared variables)
        if (content.includes('lazyloadRunObserver') ||
            content.includes('already been declared') ||
            content.includes('<!DOCTYPE') ||
            content.includes('<html')) {
          console.log('[SPA] Skipping conflicting script');
          return;
        }
        // Only run PPV-related scripts
        if (content.includes('ppv') || content.includes('PPV') || content.includes('punktepass')) {
          try {
            const newScript = document.createElement('script');
            newScript.textContent = content;
            document.body.appendChild(newScript);
            document.body.removeChild(newScript);
          } catch (e) {
            console.warn('[SPA] Script execution error:', e);
          }
        }
      });

      // Dispatch custom event for other scripts to react
      window.dispatchEvent(new CustomEvent('ppv:navigate', {
        detail: { url, path: targetPath }
      }));

      // Scroll to top and clear saved scroll position (prevents ppv-spa-loader.js from restoring old scroll)
      sessionStorage.removeItem('scroll_' + targetPath);
      // Also try with trailing slash variant
      sessionStorage.removeItem('scroll_' + targetPath + '/');
      window.scrollTo({ top: 0, behavior: 'instant' });

      const duration = Math.round(performance.now() - startTime);
      console.log(`[SPA] Navigation complete: ${duration}ms`);

    } catch (error) {
      console.error('[SPA] Navigation error:', error);
      // Fallback to regular navigation on error
      window.location.href = url;
    } finally {
      isNavigating = false;
      document.body.classList.remove('ppv-spa-loading');
    }
  };

  // Handle nav clicks
  const handleNavClick = (e) => {
    const link = e.target.closest('.nav-item[data-spa="true"]');
    if (!link) return;

    const href = link.getAttribute('href');
    if (!href || href === '#') return;

    e.preventDefault();

    // Debounce rapid clicks
    const now = Date.now();
    if (now - lastClickTime < 300) return;
    lastClickTime = now;

    // Haptic feedback
    if (window.ppvHaptic) {
      window.ppvHaptic('tap');
    }

    navigateTo(href);
  };

  // Handle browser back/forward
  const handlePopState = (e) => {
    if (e.state && e.state.url) {
      navigateTo(e.state.url, false);
    } else {
      // Fallback: navigate to current URL
      navigateTo(window.location.href, false);
    }
  };

  // Touch feedback
  const setupTouchFeedback = () => {
    document.querySelectorAll('.ppv-bottom-nav .nav-item').forEach(item => {
      item.addEventListener('touchstart', () => item.classList.add('touch'), { passive: true });
      item.addEventListener('touchend', () => item.classList.remove('touch'), { passive: true });
      item.addEventListener('mousedown', () => item.classList.add('touch'));
      item.addEventListener('mouseup', () => item.classList.remove('touch'));
      item.addEventListener('mouseleave', () => item.classList.remove('touch'));
    });
  };

  // Prefetch on hover for even faster navigation
  const setupPrefetch = () => {
    document.querySelectorAll('.ppv-bottom-nav .nav-item[data-spa="true"]').forEach(link => {
      link.addEventListener('mouseenter', () => {
        const href = link.getAttribute('href');
        if (href && href !== '#' && !pageCache.has(href)) {
          // Prefetch in background
          fetchPage(href).catch(() => {});
        }
      }, { passive: true });
    });
  };

  // Initialize
  const init = () => {
    // Set initial history state
    history.replaceState({ url: window.location.href }, document.title);

    // Update active nav on load
    updateActiveNav(window.location.pathname);

    // Event listeners
    document.addEventListener('click', handleNavClick);
    window.addEventListener('popstate', handlePopState);

    // Setup enhancements
    setupTouchFeedback();
    // setupPrefetch(); // DISABLED - reduces server requests

    console.log('[SPA] Bottom Nav v4.0 initialized');
  };

  // Run on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Export for debugging
  window.PPV_SPA = {
    navigateTo,
    clearCache: () => pageCache.clear(),
    isNavigating: () => isNavigating
  };
})();
