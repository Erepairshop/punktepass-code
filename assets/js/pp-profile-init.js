/**
 * PunktePass Profile Lite - Init Module
 * Contains: Initialization, UI updates, onboarding reset
 * Depends on: all other pp-profile-*.js modules
 */
(function() {
    'use strict';

    // Guard against multiple loads
    if (window.PPV_PROFILE_LOADED) {
        ppvLog('[Profile-Init] Already loaded, skipping');
        return;
    }
    window.PPV_PROFILE_LOADED = true;

    // Get references at runtime (not at module load time)
    function getModule() {
        return window.PPV_PROFILE || {};
    }

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
                ppvLog('[Profile-Init] No profile form found');
                return;
            }

            // Prevent duplicate initialization
            if (this.$form.dataset.ppvBound === 'true') {
                ppvLog('[Profile-Init] Already bound, skipping');
                return;
            }
            this.$form.dataset.ppvBound = 'true';

            ppvLog('[Profile-Init] Initializing...');

            // Initialize global state
            const { initState } = getModule();
            if (initState) initState();

            // Verify saved data (debug)
            this.verifySavedData();

            // Initialize managers
            this.initTabManager();
            this.initFormManager();
            this.initMediaManager();
            this.initGeocodingManager();
            this.initOnboardingReset();
            this.initEmailChange();
            this.initPasswordChange();

            // Update UI translations
            this.updateUI();

            ppvLog('[Profile-Init] Initialization complete');
        }

        /**
         * Initialize tab manager
         */
        initTabManager() {
            const { TabManager } = getModule();
            if (!TabManager) {
                ppvLog.error('[Profile-Init] TabManager not found!');
                return;
            }
            this.tabManager = new TabManager();
            this.tabManager.bindTabs();
            this.tabManager.restoreTab();
        }

        /**
         * Initialize form manager
         */
        initFormManager() {
            const { FormManager } = getModule();
            if (!FormManager) {
                ppvLog.error('[Profile-Init] FormManager not found!');
                return;
            }
            this.formManager = new FormManager(this.$form, this.tabManager);
            this.formManager.bindInputs();
            this.formManager.bindSubmit();
        }

        /**
         * Initialize media manager
         */
        initMediaManager() {
            const { MediaManager } = getModule();
            if (!MediaManager) {
                ppvLog.error('[Profile-Init] MediaManager not found!');
                return;
            }
            this.mediaManager = new MediaManager(this.$form);
            this.mediaManager.bindFileInputs();
            this.mediaManager.bindGalleryDelete();
        }

        /**
         * Initialize geocoding manager
         */
        initGeocodingManager() {
            const { GeocodingManager } = getModule();
            if (!GeocodingManager) {
                ppvLog.error('[Profile-Init] GeocodingManager not found!');
                return;
            }
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
                const { STATE, showAlert } = getModule();
                const L = STATE?.strings || {};

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
                    ppvLog.error('[Profile] Onboarding reset error:', err);
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
         * Initialize email change button
         */
        initEmailChange() {
            const changeBtn = document.getElementById('ppv-change-email-btn');
            if (!changeBtn) return;

            changeBtn.addEventListener('click', async () => {
                const { STATE, showAlert } = getModule();
                const L = STATE?.strings || {};

                const newEmail = document.getElementById('ppv-new-email')?.value?.trim();
                const confirmEmail = document.getElementById('ppv-confirm-email')?.value?.trim();

                if (!newEmail) {
                    showAlert(L.error_email_required || 'E-mail cím megadása kötelező', 'error');
                    return;
                }

                if (newEmail !== confirmEmail) {
                    showAlert(L.error_email_mismatch || 'Az e-mail címek nem egyeznek', 'error');
                    return;
                }

                if (!confirm(L.confirm_email_change || 'Biztosan módosítja az e-mail címet?')) {
                    return;
                }

                changeBtn.disabled = true;
                changeBtn.innerHTML = '⏳ ' + (L.saving || 'Mentés...');

                const formData = new FormData();
                formData.append('action', 'ppv_change_email');
                formData.append('ppv_nonce', STATE?.nonce || '');
                formData.append('new_email', newEmail);
                formData.append('confirm_email', confirmEmail);

                try {
                    const response = await fetch(STATE?.ajaxUrl || '/wp-admin/admin-ajax.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        showAlert(data.data?.msg || L.email_changed_success || '✅ E-mail cím sikeresen módosítva!', 'success');
                        document.getElementById('ppv-new-email').value = '';
                        document.getElementById('ppv-confirm-email').value = '';
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert(data.data?.msg || L.error || '❌ Hiba történt!', 'error');
                    }
                } catch (err) {
                    ppvLog.error('[Profile] Email change error:', err);
                    showAlert(L.error || '❌ Hiba történt!', 'error');
                }

                changeBtn.disabled = false;
                changeBtn.innerHTML = '📧 ' + (L.change_email_btn || 'E-mail cím módosítása');
            });
        }

        /**
         * Initialize password change button
         */
        initPasswordChange() {
            const changeBtn = document.getElementById('ppv-change-password-btn');
            if (!changeBtn) return;

            changeBtn.addEventListener('click', async () => {
                const { STATE, showAlert } = getModule();
                const L = STATE?.strings || {};

                const currentPassword = document.getElementById('ppv-current-password')?.value;
                const newPassword = document.getElementById('ppv-new-password')?.value;
                const confirmPassword = document.getElementById('ppv-confirm-password')?.value;

                if (!currentPassword) {
                    showAlert(L.error_current_password_required || 'Jelenlegi jelszó megadása kötelező', 'error');
                    return;
                }

                if (!newPassword) {
                    showAlert(L.error_new_password_required || 'Új jelszó megadása kötelező', 'error');
                    return;
                }

                if (newPassword.length < 6) {
                    showAlert(L.error_password_too_short || 'A jelszó legalább 6 karakter legyen', 'error');
                    return;
                }

                if (newPassword !== confirmPassword) {
                    showAlert(L.error_password_mismatch || 'Az új jelszavak nem egyeznek', 'error');
                    return;
                }

                if (!confirm(L.confirm_password_change || 'Biztosan módosítja a jelszót?')) {
                    return;
                }

                changeBtn.disabled = true;
                changeBtn.innerHTML = '⏳ ' + (L.saving || 'Mentés...');

                const formData = new FormData();
                formData.append('action', 'ppv_change_password');
                formData.append('ppv_nonce', STATE?.nonce || '');
                formData.append('current_password', currentPassword);
                formData.append('new_password', newPassword);
                formData.append('confirm_password', confirmPassword);

                try {
                    const response = await fetch(STATE?.ajaxUrl || '/wp-admin/admin-ajax.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        showAlert(data.data?.msg || L.password_changed_success || '✅ Jelszó sikeresen módosítva!', 'success');
                        document.getElementById('ppv-current-password').value = '';
                        document.getElementById('ppv-new-password').value = '';
                        document.getElementById('ppv-confirm-password').value = '';
                    } else {
                        showAlert(data.data?.msg || L.error || '❌ Hiba történt!', 'error');
                    }
                } catch (err) {
                    ppvLog.error('[Profile] Password change error:', err);
                    showAlert(L.error || '❌ Hiba történt!', 'error');
                }

                changeBtn.disabled = false;
                changeBtn.innerHTML = '🔐 ' + (L.change_password_btn || 'Jelszó módosítása');
            });
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
                            ppvLog.warn('[Profile] MISMATCH! Saved name differs from current form!');
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
            const { t } = getModule();
            if (!t) return;

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

    ppvLog('[Profile-Init] Module loaded v3.0');

})();
