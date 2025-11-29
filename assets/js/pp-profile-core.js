/**
 * PunktePass Profile Lite - Core Module
 * Contains: State, Turbo Cache Fix, Helpers, Translations
 * v3.0 Modular Architecture
 */
(function() {
    'use strict';

    // Guard against multiple loads
    if (window.PPV_PROFILE_CORE_LOADED) return;
    window.PPV_PROFILE_CORE_LOADED = true;

    // ============================================================
    // GLOBAL STATE
    // ============================================================
    const STATE = {
        initialized: false,
        form: null,
        hasChanges: false,
        strings: {},
        currentLang: 'de',
        nonce: '',
        ajaxUrl: ''
    };

    // ============================================================
    // TURBO CACHE FIX - Prevent stale data on back navigation
    // ============================================================
    (function setupTurboCacheFix() {
        // 1. Add meta tag to head (if not already there)
        if (!document.querySelector('head meta[name="turbo-cache-control"]')) {
            const meta = document.createElement('meta');
            meta.name = 'turbo-cache-control';
            meta.content = 'no-cache';
            document.head.appendChild(meta);
        }

        // 2. Prevent Turbo from caching this page snapshot
        document.addEventListener('turbo:before-cache', function() {
            const profileForm = document.getElementById('ppv-profile-form');
            if (profileForm) {
                profileForm.dataset.ppvBound = 'false';
                // Mark that we're leaving profile page
                sessionStorage.setItem('ppv_was_on_profile', 'true');
            }
        }, { once: false });

        // 3. Force reload when coming back to profile page via Turbo
        document.addEventListener('turbo:before-render', function(e) {
            const newBody = e.detail.newBody;
            const hasProfileForm = newBody && newBody.querySelector('#ppv-profile-form');
            const wasOnProfile = sessionStorage.getItem('ppv_was_on_profile');

            if (hasProfileForm && wasOnProfile) {
                // Coming back to profile - force hard reload
                console.log('[Profile-Core] Forcing reload - returning to profile page');
                sessionStorage.removeItem('ppv_was_on_profile');
                e.preventDefault();
                window.location.reload();
            }
        });

        // 4. Clear flag when on profile page (fresh load)
        document.addEventListener('turbo:load', function() {
            const profileForm = document.getElementById('ppv-profile-form');
            if (profileForm) {
                // We're on profile page now - clear the flag
                sessionStorage.removeItem('ppv_was_on_profile');
            }
        });
    })();

    // ============================================================
    // TRANSLATION HELPER
    // ============================================================
    function t(key) {
        return STATE.strings[key] || key;
    }

    // ============================================================
    // COOKIE HELPER
    // ============================================================
    function setCookie(name, value, days = 365) {
        const date = new Date();
        date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
        document.cookie = `${name}=${value};expires=${date.toUTCString()};path=/`;
    }

    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    // ============================================================
    // ALERT SYSTEM
    // ============================================================
    function showAlert(message, type = 'info') {
        const zone = document.getElementById('ppv-alert-zone');
        if (!zone) return;

        const alert = document.createElement('div');
        alert.className = `ppv-alert ppv-alert-${type}`;
        alert.innerHTML = `
            <div class="ppv-alert-content">
                <span>${message}</span>
                <button type="button" class="ppv-alert-close">&times;</button>
            </div>
        `;

        zone.appendChild(alert);

        alert.querySelector('.ppv-alert-close').addEventListener('click', () => {
            alert.remove();
        });

        if (type === 'success' || type === 'info') {
            setTimeout(() => alert.remove(), 3000);
        }
    }

    // ============================================================
    // STATUS INDICATOR
    // ============================================================
    function updateStatus(text) {
        const indicator = document.getElementById('ppv-save-indicator');
        if (indicator) {
            indicator.textContent = text;
            indicator.classList.add('ppv-visible');

            if (text === t('saved')) {
                setTimeout(() => indicator.classList.remove('ppv-visible'), 2500);
            }
        }
    }

    // ============================================================
    // INITIALIZE STATE FROM CONFIG
    // ============================================================
    function initState() {
        STATE.strings = window.ppv_profile?.strings || {};
        STATE.currentLang = window.ppv_profile?.lang || 'de';
        STATE.nonce = window.ppv_profile?.nonce || '';
        STATE.ajaxUrl = window.ppv_profile?.ajaxUrl || '';
        STATE.form = document.getElementById('ppv-profile-form');
    }

    // ============================================================
    // EXPORT TO GLOBAL
    // ============================================================
    window.PPV_PROFILE = {
        STATE,
        t,
        setCookie,
        getCookie,
        showAlert,
        updateStatus,
        initState
    };

    console.log('[Profile-Core] Module loaded v3.0');

})();
