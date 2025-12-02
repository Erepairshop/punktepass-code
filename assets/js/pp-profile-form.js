/**
 * PunktePass Profile Lite - Form Module
 * Contains: Form validation, submit handling, field updates
 * Depends on: pp-profile-core.js, pp-profile-tabs.js
 */
(function() {
    'use strict';

    // Guard against multiple loads
    if (window.PPV_PROFILE_FORM_LOADED) return;
    window.PPV_PROFILE_FORM_LOADED = true;

    const { STATE, t, showAlert, updateStatus } = window.PPV_PROFILE || {};

    // ============================================================
    // FORM MANAGER CLASS
    // ============================================================
    class FormManager {
        constructor(form, tabManager) {
            this.$form = form;
            this.tabManager = tabManager;
            this.hasChanges = false;
        }

        /**
         * Bind form input events for change tracking
         */
        bindInputs() {
            if (!this.$form) return;

            this.$form.addEventListener('change', () => {
                this.hasChanges = true;
            });

            this.$form.addEventListener('input', () => {
                this.hasChanges = true;
            });

            // Email validation
            this.$form.querySelectorAll('input[type="email"]').forEach(input => {
                input.addEventListener('blur', (e) => this.validateEmail(e.target));
            });

            // Phone validation
            this.$form.querySelectorAll('input[type="tel"]').forEach(input => {
                input.addEventListener('blur', (e) => this.validatePhone(e.target));
            });

            // URL validation
            this.$form.querySelectorAll('input[type="url"]').forEach(input => {
                input.addEventListener('blur', (e) => this.validateUrl(e.target));
            });
        }

        /**
         * Bind form submit event
         */
        bindSubmit() {
            if (!this.$form) return;

            this.$form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.save();
            });

            // Warn on unsaved changes
            window.addEventListener('beforeunload', (e) => {
                if (this.hasChanges) {
                    e.preventDefault();
                    e.returnValue = t('unsaved_warning');
                }
            });
        }

        /**
         * Validate email field
         */
        validateEmail(el) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const valid = regex.test(el.value);
            el.classList.toggle('ppv-invalid', !valid && el.value.length > 0);
            return valid || el.value.length === 0;
        }

        /**
         * Validate phone field
         */
        validatePhone(el) {
            const regex = /^[\d\s\-\+\(\)]+$/;
            const valid = regex.test(el.value) || el.value.length === 0;
            el.classList.toggle('ppv-invalid', !valid);
            return valid;
        }

        /**
         * Validate URL field
         */
        validateUrl(el) {
            if (el.value.length === 0) {
                el.classList.remove('ppv-invalid');
                return true;
            }
            try {
                new URL(el.value);
                el.classList.remove('ppv-invalid');
                return true;
            } catch {
                el.classList.add('ppv-invalid');
                return false;
            }
        }

        /**
         * Save form via AJAX
         */
        async save() {
            const formData = new FormData(this.$form);

            updateStatus(t('saving'));

            // Disable submit button
            const submitBtn = document.getElementById('ppv-submit-btn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="ppv-spinner"></span> ' + t('saving');
            }

            try {
                const response = await fetch(`${STATE.ajaxUrl}?action=ppv_save_profile`, {
                    method: 'POST',
                    body: formData,
                    keepalive: true
                });

                const data = await response.json();

                // Re-enable submit button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span class="ppv-icon">💾</span> <span>' + t('save') + '</span>';
                }

                if (data.success) {
                    showAlert(t('profile_saved_success'), 'success');
                    updateStatus(t('saved'));
                    this.hasChanges = false;

                    const lastUpdated = document.getElementById('ppv-last-updated');
                    if (lastUpdated) {
                        lastUpdated.textContent = `${t('last_updated')}: ${new Date().toLocaleString()}`;
                    }

                    // ✅ Update form fields (no reload - Turbo SPA)
                    if (data.data?.store) {
                        this.updateFields(data.data.store);
                    }
                } else {
                    showAlert(data.data?.msg || t('profile_save_error'), 'error');
                    updateStatus(t('error'));
                }
            } catch (err) {
                console.error('[Profile] Save error:', err);

                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span class="ppv-icon">💾</span> <span>' + t('save') + '</span>';
                }

                showAlert(t('profile_save_error'), 'error');
                updateStatus(t('error'));
            }
        }

        /**
         * Update form fields from server response
         */
        updateFields(store) {
            const fieldMap = {
                'store_name': store.name,
                'slogan': store.slogan,
                'category': store.category,
                'country': store.country,
                'address': store.address,
                'plz': store.plz,
                'city': store.city,
                'company_name': store.company_name,
                'contact_person': store.contact_person,
                'tax_id': store.tax_id,
                'phone': store.phone,
                'email': store.email,
                'website': store.website,
                'whatsapp': store.whatsapp,
                'facebook': store.facebook,
                'instagram': store.instagram,
                'tiktok': store.tiktok,
                'description': store.description,
                'latitude': store.latitude,
                'longitude': store.longitude,
                'timezone': store.timezone,
                'maintenance_message': store.maintenance_message
            };

            // Text/number/select fields
            for (const [fieldName, value] of Object.entries(fieldMap)) {
                const field = this.$form.querySelector(`[name="${fieldName}"]`);
                if (field && value !== null && value !== undefined) {
                    field.value = value;
                }
            }

            // Checkbox fields
            const checkboxMap = {
                'is_taxable': store.is_taxable,
                'active': store.active,
                'visible': store.visible,
                'maintenance_mode': store.maintenance_mode
            };

            for (const [fieldName, value] of Object.entries(checkboxMap)) {
                const field = this.$form.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    field.checked = !!value;
                }
            }
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
    }

    // ============================================================
    // EXPORT TO GLOBAL
    // ============================================================
    window.PPV_PROFILE = window.PPV_PROFILE || {};
    window.PPV_PROFILE.FormManager = FormManager;

    console.log('[Profile-Form] Module loaded v3.0');

})();
