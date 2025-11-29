/**
 * PunktePass Profile Lite - Init Module
 * Contains: Initialization, UI updates, onboarding reset
 * Depends on: all other pp-profile-*.js modules
 */
(function() {
    'use strict';

    // Guard against multiple loads
    if (window.PPV_PROFILE_LOADED) {
        console.log('[Profile-Init] Already loaded, skipping');
        return;
    }
    window.PPV_PROFILE_LOADED = true;

    const {
        STATE,
        t,
        showAlert,
        initState,
        TabManager,
        FormManager,
        MediaManager,
        GeocodingManager
    } = window.PPV_PROFILE || {};

    // ============================================================
    // PROFILE CONTROLLER
    // ============================================================
    class ProfileController {
        constructor() {
            this.$form = null;
            this.tabManager = null;
            this.formManager = null;
            this.mediaManager = null;
            this.geocodingManager = null;
        }

        /**
         * Initialize all profile features
         */
        init() {
            this.$form = document.getElementById('ppv-profile-form');

            if (!this.$form) {
                console.log('[Profile-Init] No profile form found');
                return;
            }

            // Prevent duplicate initialization
            if (this.$form.dataset.ppvBound === 'true') {
                console.log('[Profile-Init] Already bound, skipping');
                return;
            }
            this.$form.dataset.ppvBound = 'true';

            console.log('[Profile-Init] Initializing...');

            // Initialize global state
            initState();

            // Verify saved data (debug)
            this.verifySavedData();

            // Initialize managers
            this.initTabManager();
            this.initFormManager();
            this.initMediaManager();
            this.initGeocodingManager();
            this.initOnboardingReset();

            // Update UI translations
            this.updateUI();

            console.log('[Profile-Init] Initialization complete');
        }

        /**
         * Initialize tab manager
         */
        initTabManager() {
            this.tabManager = new TabManager();
            this.tabManager.bindTabs();
            this.tabManager.restoreTab();
        }

        /**
         * Initialize form manager
         */
        initFormManager() {
            this.formManager = new FormManager(this.$form, this.tabManager);
            this.formManager.bindInputs();
            this.formManager.bindSubmit();
        }

        /**
         * Initialize media manager
         */
        initMediaManager() {
            this.mediaManager = new MediaManager(this.$form);
            this.mediaManager.bindFileInputs();
            this.mediaManager.bindGalleryDelete();
        }

        /**
         * Initialize geocoding manager
         */
        initGeocodingManager() {
            this.geocodingManager = new GeocodingManager();
            this.geocodingManager.init();
        }

        /**
         * Initialize onboarding reset button
         */
        initOnboardingReset() {
            const resetBtn = document.getElementById('ppv-reset-onboarding-btn');
            if (!resetBtn) return;

            resetBtn.addEventListener('click', async () => {
                const L = STATE.strings;

                if (!confirm(L.onboarding_reset_confirm || 'Are you sure you want to restart onboarding?')) {
                    return;
                }

                resetBtn.disabled = true;
                resetBtn.innerHTML = '⏳ ' + (L.onboarding_resetting || 'Restarting...');

                try {
                    const response = await fetch(window.ppv_onboarding?.rest_url + 'reset' || '/wp-json/ppv/v1/onboarding/reset', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': window.ppv_onboarding?.nonce || ''
                        },
                        body: JSON.stringify({})
                    });

                    const data = await response.json();

                    if (data.success) {
                        showAlert(L.onboarding_reset_success || '✅ Onboarding restarted! Page will reload...', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert(L.onboarding_reset_error || '❌ Error occurred!', 'error');
                        this.resetOnboardingButton(resetBtn, L);
                    }
                } catch (err) {
                    console.error('[Profile] Onboarding reset error:', err);
                    showAlert(L.onboarding_reset_error || '❌ Error occurred!', 'error');
                    this.resetOnboardingButton(resetBtn, L);
                }
            });
        }

        /**
         * Reset onboarding button state
         */
        resetOnboardingButton(btn, L) {
            btn.disabled = false;
            btn.innerHTML = '🔄 ' + (L.onboarding_reset_btn || 'Restart onboarding');
        }

        /**
         * Verify saved data matches current form (debug)
         */
        verifySavedData() {
            const lastSave = sessionStorage.getItem('ppv_last_save');
            if (lastSave) {
                try {
                    const saveData = JSON.parse(lastSave);
                    const timeDiff = Date.now() - saveData.timestamp;
                    if (timeDiff < 10000) {
                        const storeNameInput = this.$form.querySelector('[name="store_name"]');
                        if (saveData.store_name !== storeNameInput?.value) {
                            console.warn('[Profile] MISMATCH! Saved name differs from current form!');
                        }
                    }
                    sessionStorage.removeItem('ppv_last_save');
                } catch(e) {}
            }
        }

        /**
         * Update UI translations
         */
        updateUI() {
            // Tab buttons
            document.querySelectorAll('.ppv-tab-btn[data-i18n]').forEach(btn => {
                const key = btn.dataset.i18n;
                const icon = btn.textContent.match(/^.{1,2}\s/)?.[0] || '';
                btn.textContent = icon + t(key);
            });

            // Labels
            document.querySelectorAll('label[data-i18n]').forEach(label => {
                const key = label.dataset.i18n;
                const isRequired = label.textContent.includes('*');
                label.textContent = t(key) + (isRequired ? ' *' : '');
            });

            // Headings
            document.querySelectorAll('h2[data-i18n], h3[data-i18n]').forEach(heading => {
                heading.textContent = t(heading.dataset.i18n);
            });

            // Paragraphs
            document.querySelectorAll('p[data-i18n]').forEach(p => {
                p.textContent = t(p.dataset.i18n);
            });

            // Button spans
            document.querySelectorAll('button[data-i18n] span').forEach(span => {
                span.textContent = t(span.parentElement.dataset.i18n);
            });
        }

        /**
         * Cleanup (for Turbo navigation)
         */
        cleanup() {
            this.tabManager = null;
            this.formManager = null;
            this.mediaManager = null;
            this.geocodingManager = null;
            this.$form = null;
        }
    }

    // ============================================================
    // INITIALIZATION
    // ============================================================
    let profileController = null;

    function initProfile() {
        const form = document.getElementById('ppv-profile-form');

        if (!form) {
            if (profileController) {
                profileController.cleanup();
                profileController = null;
            }
            return;
        }

        // Check if old instance references stale DOM
        if (profileController && profileController.$form) {
            if (profileController.$form !== form) {
                profileController.cleanup();
                profileController = null;
                form.dataset.ppvBound = 'false';
            } else {
                return; // Same form, already initialized
            }
        }

        profileController = new ProfileController();
        profileController.init();
    }

    // ============================================================
    // EVENT LISTENERS
    // ============================================================

    // Initial load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProfile);
    } else {
        initProfile();
    }

    // Turbo navigation
    document.addEventListener('turbo:load', initProfile);
    document.addEventListener('turbo:render', initProfile);

    // Browser back/forward cache
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    // ============================================================
    // EXPORT
    // ============================================================
    window.PPV_PROFILE = window.PPV_PROFILE || {};
    window.PPV_PROFILE.ProfileController = ProfileController;
    window.PPV_PROFILE.init = initProfile;

    // Legacy global export
    window.ppvProfileForm = profileController;

    console.log('[Profile-Init] Module loaded v3.0');

})();
