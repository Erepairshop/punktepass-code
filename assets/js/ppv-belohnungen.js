/**
 * PunktePass – Belohnungen v3.1 (Compact PWA Design)
 * Features: Store accordion, filter, collapsible how-to
 * Turbo-compatible
 */

(function() {
  'use strict';


  // Global functions for inline onclick handlers
  window.ppvToggleStore = function(header) {
    const card = header.closest('.ppv-rw-store-card');
    const list = card.querySelector('.ppv-rw-rewards-list');
    const chevron = header.querySelector('.ppv-rw-chevron');

    if (list.classList.contains('is-open')) {
      list.classList.remove('is-open');
      chevron.style.transform = 'rotate(0deg)';
    } else {
      list.classList.add('is-open');
      chevron.style.transform = 'rotate(180deg)';
    }
  };

  window.ppvToggleHowto = function(header) {
    const howto = header.closest('.ppv-rw-howto');
    const content = howto.querySelector('.ppv-rw-howto-content');
    const chevron = header.querySelector('.ppv-rw-chevron');

    if (content.classList.contains('is-open')) {
      content.classList.remove('is-open');
      chevron.style.transform = 'rotate(0deg)';
    } else {
      content.classList.add('is-open');
      chevron.style.transform = 'rotate(180deg)';
    }
  };

  function init() {
    const container = document.querySelector('.ppv-rewards-v3');
    if (!container) return;

    if (container.dataset.initialized === 'true') {
      return;
    }
    container.dataset.initialized = 'true';


    // Initialize first store as open
    initAccordion();

    // Initialize store filter
    initFilter();

    // Animate elements
    animateOnLoad();

  }

  function initAccordion() {
    // Set first store's chevron to rotated
    const firstCard = document.querySelector('.ppv-rw-store-card');
    if (firstCard) {
      const chevron = firstCard.querySelector('.ppv-rw-chevron');
      if (chevron) chevron.style.transform = 'rotate(180deg)';
    }
  }

  function initFilter() {
    const filter = document.getElementById('ppv-store-filter');
    if (!filter) return;

    filter.addEventListener('change', function() {
      const value = this.value;
      const cards = document.querySelectorAll('.ppv-rw-store-card');

      cards.forEach(card => {
        if (value === 'all') {
          card.style.display = '';
        } else {
          const storeId = card.dataset.storeId;
          card.style.display = (storeId === value) ? '' : 'none';
        }
      });

      // If filtering to single store, open it
      if (value !== 'all') {
        const visibleCard = document.querySelector(`.ppv-rw-store-card[data-store-id="${value}"]`);
        if (visibleCard) {
          const list = visibleCard.querySelector('.ppv-rw-rewards-list');
          const chevron = visibleCard.querySelector('.ppv-rw-chevron');
          if (list && !list.classList.contains('is-open')) {
            list.classList.add('is-open');
            if (chevron) chevron.style.transform = 'rotate(180deg)';
          }
        }
      }
    });
  }

  function animateOnLoad() {
    // Subtle fade-in for cards
    const elements = document.querySelectorAll('.ppv-rw-store-card, .ppv-rw-prog-card, .ppv-rw-history-item');
    elements.forEach((el, i) => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(10px)';
      el.style.transition = 'opacity 0.3s ease, transform 0.3s ease';

      setTimeout(() => {
        el.style.opacity = '1';
        el.style.transform = 'translateY(0)';
      }, 50 + (i * 40));
    });

    // Pulse animation for ready badges
    const readyBadges = document.querySelectorAll('.ppv-rw-badge-ready, .ppv-rw-ready-badge');
    readyBadges.forEach(badge => {
      badge.classList.add('ppv-pulse');
    });
  }

  // Initialize
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Turbo support
  document.addEventListener('turbo:load', function() {
    const container = document.querySelector('.ppv-rewards-v3');
    if (container) container.dataset.initialized = 'false';
    init();
  });

  document.addEventListener('turbo:render', function() {
    const container = document.querySelector('.ppv-rewards-v3');
    if (container) container.dataset.initialized = 'false';
    init();
  });

})();
