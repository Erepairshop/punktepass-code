/**
 * PunktePass – Admin Profil Frontend (v2.0 i18n - Fixed)
 * ✅ DE, HU, RO Language Support
 * ✅ Dynamic String Translation
 * ✅ Real-time Validation
 * ✅ Nonce Fix
 * ✅ Geocoding FIX
 */

(function() {
    'use strict';

    console.log('🏖️ [PPV] pp-profile-lite.js LOADED - v2.1');

    // ============================================================
    // 🚫 TURBO CACHE FIX - Prevent stale data on back navigation
    // ============================================================
    (function setupTurboCacheFix() {
        // 1. Add meta tag to head (if not already there)
        if (!document.querySelector('head meta[name="turbo-cache-control"]')) {
            const meta = document.createElement('meta');
            meta.name = 'turbo-cache-control';
            meta.content = 'no-cache';
            document.head.appendChild(meta);
        }

        // 2. Prevent Turbo from caching this page snapshot - reset form binding
        document.addEventListener('turbo:before-cache', function() {
            const profileForm = document.getElementById('ppv-profile-form');
            if (profileForm) {
                profileForm.dataset.ppvBound = 'false';
            }
        }, { once: false });

        // 3. Turbo SPA handles back/forward - no reload needed
    })();

    class PPVProfileForm {
        constructor() {
            this.$form = document.getElementById('ppv-profile-form');
            this.strings = window.ppv_profile?.strings || {};
            this.currentLang = window.ppv_profile?.lang || 'de';
            this.nonce = window.ppv_profile?.nonce || '';
            this.ajaxUrl = window.ppv_profile?.ajaxUrl || '';

            this.hasChanges = false;

            this.init();
        }

        init() {
            if (!this.$form) {
                return;
            }

            // ✅ FIX: Prevent duplicate event listener bindings on Turbo navigation
            if (this.$form.dataset.ppvBound === 'true') {
                return;
            }
            this.$form.dataset.ppvBound = 'true';

            // ✅ DEBUG: Log current form data to verify correct store loaded
            const storeIdInput = this.$form.querySelector('[name="store_id"]');
            const storeNameInput = this.$form.querySelector('[name="store_name"]');

            // ✅ Check if we just saved and verify data matches
            const lastSave = sessionStorage.getItem('ppv_last_save');
            if (lastSave) {
                try {
                    const saveData = JSON.parse(lastSave);
                    const timeDiff = Date.now() - saveData.timestamp;
                    if (timeDiff < 10000) { // Within 10 seconds
                        if (saveData.store_name !== storeNameInput?.value) {
                            console.warn('⚠️ [Profile] MISMATCH! Saved name differs from current form!');
                        }
                    }
                    sessionStorage.removeItem('ppv_last_save');
                } catch(e) {}
            }

            this.bindTabs();
            this.bindFormInputs();
            this.bindFormSubmit();
            this.bindGalleryDelete();
            this.bindOnboardingReset();
            this.bindEmailChange();
            this.bindPasswordChange();

            this.updateUI();

            // ✅ Restore tab from URL hash or localStorage (survives page refresh)
            let restoredTab = null;
            if (window.location.hash?.startsWith('#tab-')) {
                restoredTab = window.location.hash.replace('#tab-', '');
            } else {
                try {
                    restoredTab = localStorage.getItem('ppv_profile_active_tab');
                } catch (e) {}
            }
            if (restoredTab) {
                this.switchTab(restoredTab);
            }
        }

        // ==================== ONBOARDING RESET ====================
        bindOnboardingReset() {
            const resetBtn = document.getElementById('ppv-reset-onboarding-btn');
            if (!resetBtn) return;

            resetBtn.addEventListener('click', () => {
                const L = this.strings;
                if (!confirm(L.onboarding_reset_confirm || 'Biztosan újraindítod az onboarding-ot?')) {
                    return;
                }

                resetBtn.disabled = true;
                resetBtn.innerHTML = '⏳ ' + (L.onboarding_resetting || 'Újraindítás...');

                fetch(window.ppv_onboarding?.rest_url + 'reset' || '/wp-json/ppv/v1/onboarding/reset', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.ppv_onboarding?.nonce || ''
                    },
                    body: JSON.stringify({})
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        this.showAlert(L.onboarding_reset_success || '✅ Onboarding újraindítva! Az oldal frissül...', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        this.showAlert(L.onboarding_reset_error || '❌ Hiba történt!', 'error');
                        resetBtn.disabled = false;
                        resetBtn.innerHTML = '🔄 ' + (L.onboarding_reset_btn || 'Onboarding újraindítása');
                    }
                })
                .catch(err => {
                    console.error('Onboarding reset error:', err);
                    this.showAlert(L.onboarding_reset_error || '❌ Hiba történt!', 'error');
                    resetBtn.disabled = false;
                    resetBtn.innerHTML = '🔄 ' + (L.onboarding_reset_btn || 'Onboarding újraindítása');
                });
            });
        }

        // ==================== EMAIL CHANGE ====================
        bindEmailChange() {
            const changeBtn = document.getElementById('ppv-change-email-btn');
            if (!changeBtn) return;

            changeBtn.addEventListener('click', () => {
                const L = this.strings;
                const newEmail = document.getElementById('ppv-new-email')?.value?.trim();
                const confirmEmail = document.getElementById('ppv-confirm-email')?.value?.trim();

                if (!newEmail) {
                    this.showAlert(L.error_email_required || 'E-mail cím megadása kötelező', 'error');
                    return;
                }

                if (newEmail !== confirmEmail) {
                    this.showAlert(L.error_email_mismatch || 'Az e-mail címek nem egyeznek', 'error');
                    return;
                }

                if (!confirm(L.confirm_email_change || 'Biztosan módosítja az e-mail címet?')) {
                    return;
                }

                changeBtn.disabled = true;
                changeBtn.innerHTML = '⏳ ' + (L.saving || 'Mentés...');

                const formData = new FormData();
                formData.append('action', 'ppv_change_email');
                formData.append('ppv_nonce', this.nonce);
                formData.append('new_email', newEmail);
                formData.append('confirm_email', confirmEmail);

                fetch(this.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        this.showAlert(data.data?.msg || L.email_changed_success || '✅ E-mail cím sikeresen módosítva!', 'success');
                        // Clear fields
                        document.getElementById('ppv-new-email').value = '';
                        document.getElementById('ppv-confirm-email').value = '';
                        // Reset hasChanges to prevent "unsaved changes" warning
                        this.hasChanges = false;
                        // Reload to show new email
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        this.showAlert(data.data?.msg || L.error || '❌ Hiba történt!', 'error');
                    }
                    changeBtn.disabled = false;
                    changeBtn.innerHTML = '📧 ' + (L.change_email_btn || 'E-mail cím módosítása');
                })
                .catch(err => {
                    console.error('Email change error:', err);
                    this.showAlert(L.error || '❌ Hiba történt!', 'error');
                    changeBtn.disabled = false;
                    changeBtn.innerHTML = '📧 ' + (L.change_email_btn || 'E-mail cím módosítása');
                });
            });
        }

        // ==================== PASSWORD CHANGE ====================
        bindPasswordChange() {
            const changeBtn = document.getElementById('ppv-change-password-btn');
            if (!changeBtn) return;

            changeBtn.addEventListener('click', () => {
                const L = this.strings;
                const currentPassword = document.getElementById('ppv-current-password')?.value;
                const newPassword = document.getElementById('ppv-new-password')?.value;
                const confirmPassword = document.getElementById('ppv-confirm-password')?.value;

                if (!currentPassword) {
                    this.showAlert(L.error_current_password_required || 'Jelenlegi jelszó megadása kötelező', 'error');
                    return;
                }

                if (!newPassword) {
                    this.showAlert(L.error_new_password_required || 'Új jelszó megadása kötelező', 'error');
                    return;
                }

                if (newPassword.length < 6) {
                    this.showAlert(L.error_password_too_short || 'A jelszó legalább 6 karakter legyen', 'error');
                    return;
                }

                if (newPassword !== confirmPassword) {
                    this.showAlert(L.error_password_mismatch || 'Az új jelszavak nem egyeznek', 'error');
                    return;
                }

                if (!confirm(L.confirm_password_change || 'Biztosan módosítja a jelszót?')) {
                    return;
                }

                changeBtn.disabled = true;
                changeBtn.innerHTML = '⏳ ' + (L.saving || 'Mentés...');

                const formData = new FormData();
                formData.append('action', 'ppv_change_password');
                formData.append('ppv_nonce', this.nonce);
                formData.append('current_password', currentPassword);
                formData.append('new_password', newPassword);
                formData.append('confirm_password', confirmPassword);

                fetch(this.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        this.showAlert(data.data?.msg || L.password_changed_success || '✅ Jelszó sikeresen módosítva!', 'success');
                        // Clear fields
                        document.getElementById('ppv-current-password').value = '';
                        document.getElementById('ppv-new-password').value = '';
                        document.getElementById('ppv-confirm-password').value = '';
                    } else {
                        this.showAlert(data.data?.msg || L.error || '❌ Hiba történt!', 'error');
                    }
                    changeBtn.disabled = false;
                    changeBtn.innerHTML = '🔐 ' + (L.change_password_btn || 'Jelszó módosítása');
                })
                .catch(err => {
                    console.error('Password change error:', err);
                    this.showAlert(L.error || '❌ Hiba történt!', 'error');
                    changeBtn.disabled = false;
                    changeBtn.innerHTML = '🔐 ' + (L.change_password_btn || 'Jelszó módosítása');
                });
            });
        }

        // ==================== GALLERY DELETE ====================
        bindGalleryDelete() {
            document.querySelectorAll('.ppv-gallery-delete-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const imageUrl = e.target.dataset.imageUrl;
                    this.deleteGalleryImage(imageUrl);
                });
            });
        }

        deleteGalleryImage(imageUrl) {
            if (!confirm(this.strings.confirm_delete_image || 'Bild löschen?')) return;

            const formData = new FormData();
            formData.append('action', 'ppv_delete_gallery_image');
            formData.append('ppv_nonce', this.nonce);
            formData.append('store_id', this.$form.querySelector('[name="store_id"]').value);
            formData.append('image_url', imageUrl);

            fetch(this.ajaxUrl + '?action=ppv_delete_gallery_image', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.showAlert(this.strings.image_deleted || 'Bild gelöscht!', 'success');
                    location.reload();
                } else {
                    this.showAlert(data.data?.msg || this.strings.delete_error || 'Fehler beim Löschen', 'error');
                }
            })
            .catch(err => {
                this.showAlert(this.strings.delete_error || 'Fehler beim Löschen', 'error');
            });
        }

        // ==================== TABS ====================
        bindTabs() {
            document.querySelectorAll('.ppv-tab-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const tabName = e.currentTarget.dataset.tab;
                    this.switchTab(tabName);
                });
            });
        }

        switchTab(tabName) {
            document.querySelectorAll('.ppv-tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            document.getElementById(`tab-${tabName}`)?.classList.add('active');

            document.querySelectorAll('.ppv-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tabName);
            });

            // ✅ Persist tab to URL hash (survives page refresh)
            if (history.replaceState) {
                history.replaceState(null, '', '#tab-' + tabName);
            } else {
                window.location.hash = 'tab-' + tabName;
            }

            // ✅ Also save to localStorage as fallback
            try {
                localStorage.setItem('ppv_profile_active_tab', tabName);
            } catch (e) {}
        }

        // ==================== FORM INPUTS ====================
        bindFormInputs() {
            this.$form.addEventListener('change', () => {
                this.hasChanges = true;
            });

            this.$form.addEventListener('input', () => {
                this.hasChanges = true;
            });

            this.$form.querySelectorAll('input[type="email"]').forEach(input => {
                input.addEventListener('blur', (e) => this.validateEmail(e.target));
            });

            this.$form.querySelectorAll('input[type="tel"]').forEach(input => {
                input.addEventListener('blur', (e) => this.validatePhone(e.target));
            });

            this.$form.querySelectorAll('input[type="url"]').forEach(input => {
                input.addEventListener('blur', (e) => this.validateUrl(e.target));
            });

            this.$form.querySelectorAll('.ppv-file-input').forEach(input => {
                input.addEventListener('change', (e) => this.handleFileUpload(e));
            });

            // Vacation toggle - enable/disable date fields
            const vacationToggle = document.getElementById('ppv-vacation-enabled');
            console.log('[PPV] Vacation toggle found:', !!vacationToggle);
            if (vacationToggle) {
                // Remove old listener if exists (for Turbo re-init)
                vacationToggle.removeEventListener('change', vacationToggle._ppvHandler);

                vacationToggle._ppvHandler = (e) => {
                    console.log('[PPV] Vacation toggle changed:', e.target.checked);
                    const vacationFields = document.querySelector('.ppv-vacation-fields');
                    if (vacationFields) {
                        vacationFields.style.opacity = e.target.checked ? '1' : '0.5';
                        vacationFields.style.pointerEvents = e.target.checked ? 'auto' : 'none';
                    }

                    // Update toggle status text
                    const wrapper = e.target.closest('.ppv-toggle-wrapper');
                    if (wrapper) {
                        const statusEl = wrapper.querySelector('.ppv-toggle-status');
                        if (statusEl) {
                            statusEl.textContent = e.target.checked ? statusEl.dataset.on : statusEl.dataset.off;
                            statusEl.classList.toggle('active', e.target.checked);
                        }
                    }
                };
                vacationToggle.addEventListener('change', vacationToggle._ppvHandler);
            }

            // Filiale vacation toggles
            document.querySelectorAll('.ppv-filiale-vacation-toggle').forEach(toggle => {
                toggle.addEventListener('change', (e) => {
                    const storeId = e.target.dataset.storeId;
                    const card = e.target.closest('.ppv-vacation-filiale-card');
                    const body = card?.querySelector('.ppv-vacation-card-body');

                    if (body) {
                        body.style.display = e.target.checked ? 'block' : 'none';
                    }

                    card?.classList.toggle('vacation-active', e.target.checked);

                    // Update toggle status text
                    const wrapper = e.target.closest('.ppv-toggle-wrapper');
                    if (wrapper) {
                        const statusEl = wrapper.querySelector('.ppv-toggle-status');
                        if (statusEl) {
                            statusEl.textContent = e.target.checked ? statusEl.dataset.on : statusEl.dataset.off;
                            statusEl.classList.toggle('active', e.target.checked);
                        }
                    }
                });
            });

            // Filiale vacation save buttons
            document.querySelectorAll('.ppv-save-filiale-vacation').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    this.saveFilialVacation(e.target.closest('button'));
                });
            });
        }

        async saveFilialVacation(btn) {
            const storeId = btn.dataset.storeId;
            const card = btn.closest('.ppv-vacation-filiale-card');

            if (!card || !storeId) return;

            const toggle = card.querySelector('.ppv-filiale-vacation-toggle');
            const fromInput = card.querySelector('.ppv-filiale-vacation-from');
            const toInput = card.querySelector('.ppv-filiale-vacation-to');
            const messageInput = card.querySelector('.ppv-filiale-vacation-message');

            btn.classList.add('saving');
            btn.innerHTML = '<i class="ri-loader-4-line"></i> ...';

            try {
                const formData = new FormData();
                formData.append('action', 'ppv_save_filiale_vacation');
                formData.append('store_id', storeId);
                formData.append('vacation_enabled', toggle?.checked ? '1' : '0');
                formData.append('vacation_from', fromInput?.value || '');
                formData.append('vacation_to', toInput?.value || '');
                formData.append('vacation_message', messageInput?.value || '');

                const response = await fetch(ppv_profile.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    this.showAlert(data.data?.msg || 'Mentve!', 'success');
                } else {
                    this.showAlert(data.data?.msg || 'Hiba történt', 'error');
                }
            } catch (error) {
                console.error('Filiale vacation save error:', error);
                this.showAlert('Hiba történt', 'error');
            } finally {
                btn.classList.remove('saving');
                btn.innerHTML = '<i class="ri-save-line"></i> ' + (this.t('save') || 'Mentés');
            }
        }

        validateEmail(el) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const valid = regex.test(el.value);
            el.classList.toggle('ppv-invalid', !valid && el.value.length > 0);
        }

        validatePhone(el) {
            const regex = /^[\d\s\-\+\(\)]+$/;
            const valid = regex.test(el.value) || el.value.length === 0;
            el.classList.toggle('ppv-invalid', !valid);
        }

        validateUrl(el) {
            try {
                new URL(el.value);
                el.classList.remove('ppv-invalid');
            } catch {
                el.classList.toggle('ppv-invalid', el.value.length > 0);
            }
        }

        handleFileUpload(e) {
            const input = e.target;
            const files = input.files;

            if (!files.length) return;

            for (let file of files) {
                if (file.size > 4 * 1024 * 1024) {
                    this.showAlert(this.t('file_too_large'), 'error');
                    return;
                }

                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                    this.showAlert(this.t('invalid_file_type'), 'error');
                    return;
                }
            }

            for (let file of files) {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    const preview = document.createElement('img');
                    preview.src = ev.target.result;
                    
                    const container = input.closest('.ppv-media-group')?.querySelector('[id*="preview"]');
                    if (container) {
                        const isGallery = container.id === 'ppv-gallery-preview';
                        
                        if (isGallery) {
                            if (!container.style.gridTemplateColumns) {
                                container.style.display = 'grid';
                                container.style.gridTemplateColumns = 'repeat(auto-fill, minmax(100px, 1fr))';
                                container.style.gap = '10px';
                                container.style.marginTop = '10px';
                            }
                            container.appendChild(preview);
                        } else {
                            container.innerHTML = '';
                            container.appendChild(preview);
                        }
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        // ==================== FORM SUBMIT ====================
        bindFormSubmit() {
            this.$form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveForm();
            });

            window.addEventListener('beforeunload', (e) => {
                if (this.hasChanges) {
                    e.preventDefault();
                    e.returnValue = this.t('unsaved_warning');
                }
            });
        }

        saveForm() {
            const formData = new FormData(this.$form);

            this.updateStatus(this.t('saving'));

            // ✅ Disable form submit button to prevent double-submit
            const submitBtn = document.getElementById('ppv-submit-btn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '⏳ ' + this.t('saving');
            }

            fetch(`${this.ajaxUrl}?action=ppv_save_profile`, {
                method: 'POST',
                body: formData,
                keepalive: true  // ✅ FIX: Ensures request completes even if user navigates away
            })
            .then(r => r.json())
            .then(data => {

                // ✅ Re-enable submit button
                const submitBtn = document.getElementById('ppv-submit-btn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '💾 <span>' + this.t('save') + '</span>';
                }

                if (data.success) {
                    this.showAlert(this.t('profile_saved_success'), 'success');
                    this.updateStatus(this.t('saved'));
                    this.hasChanges = false;

                    document.getElementById('ppv-last-updated').textContent =
                        `${this.t('last_updated')}: ${new Date().toLocaleString()}`;

                    // ✅ Frissítjük a form mezőket (no reload - Turbo SPA)
                    if (data.data?.store) {
                        this.updateFormFields(data.data.store);
                    }
                } else {
                    this.showAlert(data.data?.msg || this.t('profile_save_error'), 'error');
                    this.updateStatus(this.t('error'));
                }
            })
            .catch(err => {
                console.error('❌ [Profile] Save error:', err);
                // ✅ Re-enable submit button on error
                const submitBtn = document.getElementById('ppv-submit-btn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '💾 <span>' + this.t('save') + '</span>';
                }
                this.showAlert(this.t('profile_save_error'), 'error');
                this.updateStatus(this.t('error'));
            });
        }

        // ==================== UI UPDATES ====================
        updateStatus(text) {
            const indicator = document.getElementById('ppv-save-indicator');
            if (indicator) {
                indicator.textContent = text;
                indicator.classList.add('ppv-visible');

                if (text === this.t('saved')) {
                    setTimeout(() => indicator.classList.remove('ppv-visible'), 2500);
                }
            }
        }

        updateFormFields(store) {
            // Frissítjük a form mezőket a backend válasz alapján
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
                'vacation_from': store.vacation_from,
                'vacation_to': store.vacation_to,
                'vacation_message': store.vacation_message
            };

            // Text/number/select mezők
            for (const [fieldName, value] of Object.entries(fieldMap)) {
                const field = this.$form.querySelector(`[name="${fieldName}"]`);
                if (field && value !== null && value !== undefined) {
                    field.value = value;
                }
            }

            // Checkbox mezők
            const checkboxMap = {
                'is_taxable': store.is_taxable,
                'active': store.active,
                'visible': store.visible,
                'vacation_enabled': store.vacation_enabled
            };

            for (const [fieldName, value] of Object.entries(checkboxMap)) {
                const field = this.$form.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    // Fix: "0" string should be false, "1" or 1 should be true
                    field.checked = value === true || value === 1 || value === '1';

                    // Update toggle status text if exists
                    const wrapper = field.closest('.ppv-toggle-wrapper');
                    if (wrapper) {
                        const statusEl = wrapper.querySelector('.ppv-toggle-status');
                        if (statusEl) {
                            const isChecked = field.checked;
                            statusEl.textContent = isChecked ? statusEl.dataset.on : statusEl.dataset.off;
                            statusEl.classList.toggle('active', isChecked);
                        }
                    }

                    // Update vacation fields visibility
                    if (fieldName === 'vacation_enabled') {
                        const vacationFields = document.querySelector('.ppv-vacation-fields');
                        if (vacationFields) {
                            vacationFields.style.opacity = field.checked ? '1' : '0.5';
                            vacationFields.style.pointerEvents = field.checked ? 'auto' : 'none';
                        }
                    }
                }
            }
        }

        updateAllText() {
            document.querySelectorAll('[data-i18n]').forEach(el => {
                el.textContent = this.t(el.dataset.i18n) + (el.textContent.match(/\s\*$/) ? ' *' : '');
            });

            document.querySelectorAll('[data-placeholder-i18n]').forEach(el => {
                el.placeholder = this.t(el.dataset.placeholderI18n);
            });

            this.updateUI();
        }

        updateUI() {
            document.querySelectorAll('.ppv-tab-btn[data-i18n]').forEach(btn => {
                const key = btn.dataset.i18n;
                const icon = btn.textContent.match(/^.{1,2}\s/)?.[0] || '';
                btn.textContent = icon + this.t(key);
            });

            document.querySelectorAll('label[data-i18n]').forEach(label => {
                const key = label.dataset.i18n;
                const isRequired = label.textContent.includes('*');
                label.textContent = this.t(key) + (isRequired ? ' *' : '');
            });

            document.querySelectorAll('h2[data-i18n], h3[data-i18n]').forEach(heading => {
                heading.textContent = this.t(heading.dataset.i18n);
            });

            document.querySelectorAll('p[data-i18n]').forEach(p => {
                p.textContent = this.t(p.dataset.i18n);
            });

            document.querySelectorAll('button[data-i18n] span').forEach(span => {
                span.textContent = this.t(span.parentElement.dataset.i18n);
            });
        }

        // ==================== ALERTS ====================
        showAlert(message, type = 'info') {
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

        // ==================== HELPERS ====================
        t(key) {
            return this.strings[key] || key;
        }

        setCookie(name, value, days = 365) {
            const date = new Date();
            date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
            document.cookie = `${name}=${value};expires=${date.toUTCString()};path=/`;
        }
    }

    // ==================== INIT (Turbo-compatible) ====================
    function initProfileForm() {
        const form = document.getElementById('ppv-profile-form');
        if (!form) {
            window.ppvProfileForm = null;
            return;
        }

        // ✅ FIX: Check if old instance references a different (stale) DOM element
        if (window.ppvProfileForm && window.ppvProfileForm.$form) {
            // If the DOM element changed (Turbo replaced it), force re-init
            if (window.ppvProfileForm.$form !== form) {
                window.ppvProfileForm = null;
                form.dataset.ppvBound = 'false'; // Reset bound flag
            } else {
                return; // Same form, already initialized
            }
        }

        window.ppvProfileForm = new PPVProfileForm();
    }

    // Init on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProfileForm);
    } else {
        initProfileForm();
    }

    // 🚀 Turbo: Re-init after navigation
    document.addEventListener('turbo:load', initProfileForm);
    // ✅ Also listen to turbo:render for Turbo.visit with action: "replace"
    document.addEventListener('turbo:render', initProfileForm);

    // 🔄 Turbo SPA handles back/forward - bfcache reload removed

})();

// ==================== EXPORT ====================
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PPVProfileForm;
}

// ============================================================
// 🗺️ GEOCODING - Cím → Lat/Lng (PHP API) - FIXED + TURBO
// ============================================================

// Global variables for interactive map
let ppvInteractiveMap = null;
let ppvInteractiveMapMarker = null;

// ✅ Global showMapPreview function
function showMapPreview(lat, lon) {
  const mapDiv = document.getElementById('ppv-location-map');
  if (!mapDiv) return;

  mapDiv.innerHTML = `
    <div style="position: relative; width: 100%; height: 100%; border-radius: 8px; overflow: hidden; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
      <iframe style="width: 100%; height: 100%; border: none; border-radius: 8px;" src="https://www.openstreetmap.org/export/embed.html?bbox=${lon - 0.01},${lat - 0.01},${lon + 0.01},${lat + 0.01}&layer=mapnik&marker=${lat},${lon}"></iframe>
    </div>
  `;
}

// ✅ Expose showMapPreview globally
window.showMapPreview = showMapPreview;

// ============================================================
// 🗺️ INTERACTIVE MAP MODAL - Manual Geocoding
// ============================================================

function openInteractiveMap(defaultLat, defaultLng) {
  // Get translations
  const L = window.ppv_profile?.strings || {};
  const mapTitle = L.map_modal_title || 'Jelöld meg a helyet a térképen';
  const mapClick = L.map_modal_click || 'Kattints a térképre';
  const mapCancel = L.map_modal_cancel || 'Mégse';
  const mapConfirm = L.map_modal_confirm || 'Elfogadom';

  // Modal HTML
  const modalHTML = `
    <div id="ppv-map-modal" style="
      position: fixed;
      top: 0; left: 0;
      width: 100vw; height: 100vh;
      background: rgba(0,0,0,0.7);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 999999;
    ">
      <div style="
        background: white;
        border-radius: 12px;
        width: 90%;
        max-width: 800px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      ">
        <!-- Header -->
        <div style="
          padding: 1.5rem;
          border-bottom: 1px solid #e5e7eb;
          display: flex;
          justify-content: space-between;
          align-items: center;
        ">
          <h2 style="margin: 0; font-size: 1.3rem;">🗺️ ${mapTitle}</h2>
          <button onclick="window.closeInteractiveMap()" style="
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
          ">✕</button>
        </div>

        <!-- Map Container -->
        <div id="ppv-interactive-map" style="
          flex: 1;
          min-height: 400px;
          margin: 1rem;
          border-radius: 8px;
          border: 2px solid #ddd;
        "></div>

        <!-- Info & Buttons -->
        <div style="
          padding: 1.5rem;
          border-top: 1px solid #e5e7eb;
          display: flex;
          justify-content: space-between;
          align-items: center;
          gap: 1rem;
        ">
          <p style="margin: 0; color: #666; font-size: 0.9rem;">
            📍 <strong id="ppv-coord-display">${mapClick}</strong>
          </p>
          <div style="display: flex; gap: 0.75rem;">
            <button onclick="window.closeInteractiveMap()" style="
              padding: 0.75rem 1.5rem;
              border: 1px solid #ddd;
              background: #f0f0f0;
              border-radius: 6px;
              cursor: pointer;
              font-weight: 600;
            ">${mapCancel}</button>
            <button onclick="window.confirmInteractiveMap()" style="
              padding: 0.75rem 1.5rem;
              border: none;
              background: linear-gradient(135deg, #6366f1, #4f46e5);
              color: white;
              border-radius: 6px;
              cursor: pointer;
              font-weight: 600;
            ">✅ ${mapConfirm}</button>
          </div>
        </div>
      </div>
    </div>
  `;

  document.body.insertAdjacentHTML('beforeend', modalHTML);

  // Initialize map
  setTimeout(() => {
    if (typeof google === 'undefined' || !google.maps) {
      console.warn('Google Maps not loaded');
      return;
    }

    ppvInteractiveMap = new google.maps.Map(
      document.getElementById('ppv-interactive-map'),
      {
        zoom: 15,
        center: { lat: defaultLat || 47.5, lng: defaultLng || 22.5 },
        mapTypeControl: true,
        fullscreenControl: true,
        streetViewControl: false
      }
    );

    // Click listener
    ppvInteractiveMap.addListener('click', (e) => {
      const lat = e.latLng.lat();
      const lng = e.latLng.lng();

      // Remove old marker
      if (ppvInteractiveMapMarker) {
        ppvInteractiveMapMarker.setMap(null);
      }

      // Add new marker
      ppvInteractiveMapMarker = new google.maps.Marker({
        position: { lat, lng },
        map: ppvInteractiveMap,
        title: `${lat.toFixed(4)}, ${lng.toFixed(4)}`
      });

      // Update display
      document.getElementById('ppv-coord-display').innerHTML =
        `<strong>${lat.toFixed(4)}, ${lng.toFixed(4)}</strong>`;

      // Store coordinates
      window.ppvSelectedCoords = { lat, lng };
    });

  }, 100);
}

// ✅ Expose functions globally for inline onclick handlers
window.closeInteractiveMap = function() {
  const modal = document.getElementById('ppv-map-modal');
  if (modal) modal.remove();
  ppvInteractiveMap = null;
  ppvInteractiveMapMarker = null;
};

window.confirmInteractiveMap = function() {
  const L = window.ppv_profile?.strings || {};
  if (!window.ppvSelectedCoords) {
    alert(L.map_click_required || 'Bitte klicken Sie auf die Karte!');
    return;
  }

  const { lat, lng } = window.ppvSelectedCoords;

  document.getElementById('store_latitude').value = lat.toFixed(4);
  document.getElementById('store_longitude').value = lng.toFixed(4);

  showMapPreview(lat, lng);
  window.closeInteractiveMap();

  alert(`✅ Koordináták beállítva!\n\nLat: ${lat.toFixed(4)}\nLng: ${lng.toFixed(4)}`);
};

// ============================================================
// 🗺️ GEOCODING INIT - Turbo Compatible
// ============================================================

function initGeocodingFeatures() {
  const geocodeBtn = document.getElementById('ppv-geocode-btn');
  if (!geocodeBtn || geocodeBtn.dataset.geocodeInitialized) return;
  geocodeBtn.dataset.geocodeInitialized = 'true';

  geocodeBtn.addEventListener('click', async (e) => {
    e.preventDefault();

    // ✅ ÖSSZES MEZŐ LEKÉRÉSE
    const addressInput = document.querySelector('input[name="address"]');
    const plzInput = document.querySelector('input[name="plz"]');
    const cityInput = document.querySelector('input[name="city"]');
    const countryInput = document.querySelector('select[name="country"]');

    const address = addressInput?.value || '';
    const plz = plzInput?.value || '';
    const city = cityInput?.value || '';
    const country = countryInput?.value || 'DE';

    const latInput = document.getElementById('store_latitude');
    const lngInput = document.getElementById('store_longitude');

    // ✅ ELLENŐRZÉS
    const L = window.ppv_profile?.strings || {};
    if (!address || !city || !country) {
      alert(L.geocode_fields_required || 'Bitte Straße, Stadt und Land eingeben!');
      return;
    }

    geocodeBtn.disabled = true;
    geocodeBtn.textContent = '⏳ Keresés...';

    try {
      const formData = new FormData();
      formData.append('action', 'ppv_geocode_address');
      formData.append('ppv_nonce', ppv_profile.nonce);
      formData.append('address', address);
      formData.append('plz', plz);
      formData.append('city', city);
      formData.append('country', country);

      const response = await fetch(ppv_profile.ajaxUrl, {
        method: 'POST',
        body: formData,
      });

      const responseText = await response.text();

      let data;
      try {
        data = JSON.parse(responseText);
      } catch (e) {
        alert('❌ ' + (L.php_error || 'PHP Fehler') + '!\n\n' + responseText);
        geocodeBtn.disabled = false;
        geocodeBtn.textContent = '🗺️ ' + (L.geocode_button || 'Koordinaten suchen (nach Adresse)');
        return;
      }

      if (!data.success) {
        const errorMsg = data.data?.msg || 'Ismeretlen hiba történt';
        alert(`❌ ${errorMsg}`);
        geocodeBtn.disabled = false;
        geocodeBtn.textContent = '🗺️ Koordináták keresése (Cím alapján)';
        return;
      }

      const { lat, lon, country: detectedCountry, display_name, open_manual_map } = data.data;

      latInput.value = lat;
      lngInput.value = lon;

      if (countryInput) {
        countryInput.value = detectedCountry;
      }

      latInput.style.borderColor = '#10b981';
      lngInput.style.borderColor = '#10b981';

      showMapPreview(lat, lon);

      // ✅ AUTO-NYITÁS MANUÁLIS MÓDBAN HA SZÜKSÉGES
      if (open_manual_map) {
        alert(`⚠️ Az utca nem található!\n\nA város koordinátáit használom: ${display_name}\n\nKérjük, szúrd meg az X és a 🗺️ gombbal az pontos helyet!`);
        setTimeout(() => {
          openInteractiveMap(lat, lon);
        }, 500);
      } else {
        alert(`✅ Koordináták megtalálva!\n\n📍 ${display_name}\n\nSzélesség: ${lat}\nHosszúság: ${lon}`);
      }

    } catch (error) {
      alert('❌ ' + (L.geocode_error || 'Fehler bei der Koordinatensuche') + '!\n\n' + error.message);
    }

    geocodeBtn.disabled = false;
    geocodeBtn.textContent = '🗺️ ' + (L.geocode_button || 'Koordinaten suchen (nach Adresse)');
  });
}

// Geocoding button - add fallback button
function initManualMapButton() {
  const geocodeBtn = document.getElementById('ppv-geocode-btn');
  if (geocodeBtn && !geocodeBtn.dataset.manualBtnAdded) {
    geocodeBtn.dataset.manualBtnAdded = 'true';
    const L = window.ppv_profile?.strings || {};
    const manualBtn = document.createElement('button');
    manualBtn.type = 'button';
    manualBtn.textContent = '🗺️ ' + (L.manual_map_button || 'Manuálisan a térképen');
    manualBtn.style.cssText = `
      width: 100%;
      margin-top: 10px;
      padding: 0.75rem;
      border: 1px solid #ddd;
      background: #f0f0f0;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.2s;
    `;
    manualBtn.onmouseover = (e) => e.target.style.background = '#e0e0e0';
    manualBtn.onmouseout = (e) => e.target.style.background = '#f0f0f0';
    manualBtn.onclick = (e) => {
      e.preventDefault();
      const lat = parseFloat(document.getElementById('store_latitude').value) || 47.5;
      const lng = parseFloat(document.getElementById('store_longitude').value) || 22.5;
      openInteractiveMap(lat, lng);
    };
    geocodeBtn.parentElement.insertAdjacentElement('afterend', manualBtn);
  }
}

// Init on DOMContentLoaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    initGeocodingFeatures();
    initManualMapButton();
  });
} else {
  initGeocodingFeatures();
  initManualMapButton();
}

// 🚀 Turbo: Re-init after navigation (only turbo:load, not render to avoid double-init)
document.addEventListener('turbo:load', () => {
  initGeocodingFeatures();
  initManualMapButton();
});