/**
 * PunktePass – Handler Onboarding System
 * Version: 1.0
 * ✅ Welcome Modal + Wizard + Progress Card + Sticky Reminder
 * ✅ Csak handlereknek jelenik meg
 */

(function($) {
    'use strict';

    if (!window.ppv_onboarding) {
        console.warn('⚠️ PPV Onboarding config not loaded');
        return;
    }

    const L = window.ppv_lang || {};
    const config = window.ppv_onboarding;

    console.log('✅ PPV Onboarding JS loaded', config);

    class PPVOnboarding {
        constructor() {
            this.config = config;
            this.progress = config.progress;
            this.wizardStep = 0;
            this.wizardData = {
                profile_lite: {},
                reward: {}
            };

            this.init();
        }

        init() {
            // Ha már completed vagy dismissed, ne csináljunk semmit
            if (this.progress.is_complete || this.config.dismissed) {
                console.log('🎯 Onboarding already complete or dismissed');
                return;
            }

            $(document).ready(() => {
                // Welcome Modal - csak QR-center oldalon, ha még nem látta
                if (this.config.is_qr_center && !this.config.welcome_shown) {
                    setTimeout(() => this.showWelcomeModal(), 1000);
                }

                // Progress Card - mindig
                this.renderProgressCard();

                // Sticky Reminder - ha < 100%
                if (this.progress.percentage < 100 && !this.config.sticky_hidden) {
                    this.renderStickyReminder();
                }

                // Progress polling - 15 másodpercenként
                setInterval(() => this.refreshProgress(), 15000);
            });
        }

        /** ============================================================
         *  👋 WELCOME MODAL
         * ============================================================ */
        showWelcomeModal() {
            const modal = $(`
                <div class="ppv-onboarding-modal-backdrop">
                    <div class="ppv-onboarding-modal ppv-welcome-modal">
                        <button class="ppv-modal-close" data-action="close">&times;</button>

                        <div class="ppv-modal-icon">🎉</div>

                        <h2>${L.onb_welcome_title || 'Üdvözlünk a PunktePass-ban!'}</h2>

                        <p>${L.onb_welcome_subtitle || 'Segítünk beállítani a 2 alapvető dolgot, hogy a vendégek pontokat gyűjthessenek!'}</p>

                        <div class="ppv-welcome-steps">
                            <div class="ppv-welcome-step">
                                <span class="step-number">1</span>
                                <span>${L.onb_welcome_step1 || 'Töltsd ki az üzlet alapadatait'}</span>
                            </div>
                            <div class="ppv-welcome-step">
                                <span class="step-number">2</span>
                                <span>${L.onb_welcome_step2 || 'Hozd létre az első prémiumot'}</span>
                            </div>
                        </div>

                        <div class="ppv-welcome-time">
                            ${L.onb_welcome_time || '⏱️ Körülbelül 3 perc'}
                        </div>

                        <div class="ppv-modal-actions">
                            <button class="ppv-btn ppv-btn-secondary" data-action="later">${L.onb_btn_later || '⏭️ Később'}</button>
                            <button class="ppv-btn ppv-btn-primary" data-action="start">${L.onb_btn_start || '🚀 Kezdjük!'}</button>
                        </div>
                    </div>
                </div>
            `);

            $('body').append(modal);

            // Animáció
            setTimeout(() => modal.addClass('show'), 10);

            // Gombok
            modal.on('click', '[data-action="start"]', () => {
                this.markWelcomeShown();
                this.closeModal(modal);
                setTimeout(() => this.showWizardModal(), 300);
            });

            modal.on('click', '[data-action="later"]', () => {
                this.markWelcomeShown();
                this.closeModal(modal);
            });

            modal.on('click', '[data-action="close"]', () => {
                this.markWelcomeShown();
                this.closeModal(modal);
            });

            // Backdrop click
            modal.on('click', '.ppv-onboarding-modal-backdrop', (e) => {
                if (e.target === e.currentTarget) {
                    this.markWelcomeShown();
                    this.closeModal(modal);
                }
            });
        }

        markWelcomeShown() {
            $.post(this.config.rest_url + 'mark-welcome-shown', {}, () => {
                this.config.welcome_shown = true;
            });
        }

        /** ============================================================
         *  🧙 WIZARD MODAL - 2 LÉPÉS
         * ============================================================ */
        showWizardModal() {
            this.wizardStep = this.progress.steps.profile_lite ? 1 : 0;

            const modal = $(`
                <div class="ppv-onboarding-modal-backdrop">
                    <div class="ppv-onboarding-modal ppv-wizard-modal">
                        <button class="ppv-modal-close" data-action="close">&times;</button>
                        <div class="ppv-wizard-content"></div>
                    </div>
                </div>
            `);

            $('body').append(modal);
            setTimeout(() => modal.addClass('show'), 10);

            this.renderWizardStep(modal);

            // Backdrop click
            modal.on('click', '.ppv-onboarding-modal-backdrop', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeModal(modal);
                }
            });

            modal.on('click', '[data-action="close"]', () => {
                this.closeModal(modal);
            });
        }

        renderWizardStep(modal) {
            const content = modal.find('.ppv-wizard-content');
            content.empty();

            if (this.wizardStep === 0) {
                this.renderProfileLiteStep(content, modal);
            } else if (this.wizardStep === 1) {
                this.renderRewardStep(content, modal);
            }
        }

        /** ============================================================
         *  1️⃣ PROFILE LITE STEP
         * ============================================================ */
        renderProfileLiteStep(content, modal) {
            const html = $(`
                <div class="ppv-wizard-progress">
                    <div class="ppv-progress-bar">
                        <div class="ppv-progress-fill" style="width: 50%"></div>
                    </div>
                    <div class="ppv-progress-text">50% (1/2)</div>
                </div>

                <h3>${L.onb_profile_step_title || '1️⃣ Üzlet Alapadatok'}</h3>

                <form class="ppv-wizard-form" id="ppv-profile-form">
                    <div class="ppv-form-group">
                        <label>${L.onb_profile_company_name || 'Shop név'} *</label>
                        <input type="text" name="company_name" required placeholder="${L.onb_profile_company_name_placeholder || 'pl. Teszt Kávézó'}">
                    </div>

                    <div class="ppv-form-group">
                        <label>${L.onb_profile_country || 'Ország'} *</label>
                        <select name="country" required>
                            <option value="">${L.onb_profile_country_placeholder || 'Válassz országot...'}</option>
                            <option value="HU">${L.country_hu || 'Magyarország'}</option>
                            <option value="AT">${L.country_at || 'Ausztria'}</option>
                            <option value="DE">${L.country_de || 'Németország'}</option>
                            <option value="SK">${L.country_sk || 'Szlovákia'}</option>
                            <option value="RO">${L.country_ro || 'Románia'}</option>
                            <option value="HR">${L.country_hr || 'Horvátország'}</option>
                            <option value="SI">${L.country_si || 'Szlovénia'}</option>
                        </select>
                    </div>

                    <div class="ppv-form-group">
                        <label>${L.onb_profile_address || 'Cím'} *</label>
                        <input type="text" name="address" required placeholder="${L.onb_profile_address_placeholder || 'pl. Fő utca 12.'}">
                    </div>

                    <div class="ppv-form-row">
                        <div class="ppv-form-group">
                            <label>${L.onb_profile_city || 'Város'} *</label>
                            <input type="text" name="city" required placeholder="${L.onb_profile_city_placeholder || 'pl. Budapest'}">
                        </div>

                        <div class="ppv-form-group">
                            <label>${L.onb_profile_zip || 'Irányítószám'} *</label>
                            <input type="text" name="zip" required placeholder="${L.onb_profile_zip_placeholder || 'pl. 1011'}">
                        </div>
                    </div>

                    <div class="ppv-form-group">
                        <label>${L.onb_profile_phone || 'Telefon'} *</label>
                        <input type="tel" name="phone" required placeholder="${L.onb_profile_phone_placeholder || 'pl. +36 30 123 4567'}">
                    </div>

                    <div class="ppv-form-group">
                        <label>${L.onb_profile_coordinates || 'Ortskoordinaten'}</label>
                        <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <div style="flex: 1;">
                                <label style="font-size: 12px; color: #999;">${L.onb_profile_latitude || 'Breitengrad (Latitude)'}</label>
                                <input type="text" name="latitude" placeholder="pl. 47.5000" pattern="-?[0-9]+\\.?[0-9]*">
                            </div>
                            <div style="flex: 1;">
                                <label style="font-size: 12px; color: #999;">${L.onb_profile_longitude || 'Längengrad (Longitude)'}</label>
                                <input type="text" name="longitude" placeholder="pl. 19.0400" pattern="-?[0-9]+\\.?[0-9]*">
                            </div>
                        </div>
                        <button type="button" class="ppv-btn ppv-btn-secondary" id="ppv-geocode-btn" style="width: 100%;">
                            ${L.onb_profile_geocode_btn || '🔍 Koordinaten suchen'}
                        </button>
                        <small style="color: #999; margin-top: 5px; display: block;">
                            ${L.onb_profile_geocode_tip || '💡 Opcionális: GPS koordinátákat automatikusan kereshetünk a cím alapján'}
                        </small>
                    </div>
                </form>

                <div class="ppv-modal-actions">
                    <button type="button" class="ppv-btn ppv-btn-secondary" data-action="skip">${L.onb_btn_skip || '⏭️ Kihagyom'}</button>
                    <button type="button" class="ppv-btn ppv-btn-primary" data-action="next">${L.onb_btn_next || '➡️ Következő'}</button>
                </div>
            `);

            content.html(html);

            // Koordináták keresés
            content.on('click', '#ppv-geocode-btn', (e) => {
                e.preventDefault();
                const address = content.find('[name="address"]').val();
                const city = content.find('[name="city"]').val();
                const zip = content.find('[name="zip"]').val();
                const country = content.find('[name="country"]').val();

                if (!address || !city) {
                    this.showToast(L.onb_error_address || '❌ Kérlek add meg a címet és várost először!', 'error');
                    return;
                }

                const btn = $(e.target);
                btn.prop('disabled', true).text(L.onb_state_searching || '🔍 Keresés...');

                $.ajax({
                    url: this.config.rest_url + 'geocode',
                    method: 'POST',
                    contentType: 'application/json',
                    headers: {
                        'X-WP-Nonce': this.config.nonce
                    },
                    data: JSON.stringify({ address, city, zip, country }),
                    success: (response) => {
                        if (response.success && response.lat && response.lng) {
                            content.find('[name="latitude"]').val(response.lat.toFixed(4));
                            content.find('[name="longitude"]').val(response.lng.toFixed(4));
                            this.showToast(L.onb_success_geocode || '✅ Koordináták megtalálva!', 'success');
                        } else {
                            this.showToast(L.onb_error_not_found || '❌ Nem találtunk koordinátákat', 'error');
                        }
                    },
                    error: () => {
                        this.showToast(L.onb_error_geocoding || '❌ Geocoding hiba', 'error');
                    },
                    complete: () => {
                        btn.prop('disabled', false).text(L.onb_profile_geocode_btn || '🔍 Koordinaten suchen');
                    }
                });
            });

            // Next gomb
            content.on('click', '[data-action="next"]', (e) => {
                e.preventDefault();
                const form = content.find('#ppv-profile-form')[0];

                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                const data = {
                    company_name: content.find('[name="company_name"]').val(),
                    country: content.find('[name="country"]').val(),
                    address: content.find('[name="address"]').val(),
                    city: content.find('[name="city"]').val(),
                    zip: content.find('[name="zip"]').val(),
                    phone: content.find('[name="phone"]').val(),
                    latitude: content.find('[name="latitude"]').val() || null,
                    longitude: content.find('[name="longitude"]').val() || null
                };

                this.saveWizardStep('profile_lite', data, modal);
            });

            // Skip gomb
            content.on('click', '[data-action="skip"]', () => {
                this.wizardStep = 1;
                this.renderWizardStep(modal);
            });
        }

        /** ============================================================
         *  2️⃣ REWARD STEP
         * ============================================================ */
        renderRewardStep(content, modal) {
            const html = $(`
                <div class="ppv-wizard-progress">
                    <div class="ppv-progress-bar">
                        <div class="ppv-progress-fill" style="width: 100%"></div>
                    </div>
                    <div class="ppv-progress-text">100% (2/2)</div>
                </div>

                <h3>${L.onb_reward_step_title || '2️⃣ Első Prémium Létrehozása'}</h3>

                <form class="ppv-wizard-form" id="ppv-reward-form">
                    <div class="ppv-form-group">
                        <label>${L.onb_reward_name || 'Prémium neve'} *</label>
                        <input type="text" name="title" placeholder="${L.onb_reward_name_placeholder || 'pl. Ingyenes Kávé'}">
                        <small style="color: #999;">${L.onb_reward_name_helper || '📝 A prémium neve, amit az ügyfelek látnak'}</small>
                    </div>

                    <div class="ppv-form-group">
                        <label>${L.onb_reward_points || 'Szükséges pontok'} *</label>
                        <input type="number" name="required_points" placeholder="100" min="1" value="100">
                        <small style="color: #999;">${L.onb_reward_points_helper || '🎯 Hány pont szükséges az ügyfélnek ezen prémium beváltásához'}</small>
                    </div>

                    <div class="ppv-form-group">
                        <label>${L.onb_reward_description || 'Leírás (opcionális)'}</label>
                        <textarea name="description" rows="3" placeholder="${L.onb_reward_description_placeholder || 'pl. Egy ingyenes eszpresszó vagy cappuccino választható.'}"></textarea>
                        <small style="color: #999;">${L.onb_reward_description_helper || '💬 További részletek a prémiumról (opcionális)'}</small>
                    </div>

                    <div class="ppv-form-group">
                        <label>${L.onb_reward_type || 'Jutalmazás típusa'} *</label>
                        <select name="action_type" id="onboarding-action-type">
                            <option value="discount_percent">${L.onb_reward_type_percent || 'Rabatt (%)'}</option>
                            <option value="discount_fixed">${L.onb_reward_type_fixed || 'Fix rabatt'}</option>
                            <option value="free_product" selected>${L.onb_reward_type_free || 'Ingyenes termék'}</option>
                        </select>
                        <small style="color: #999;">${L.onb_reward_type_helper || '🎁 Milyen típusú jutalmat kap az ügyfél'}</small>
                    </div>

                    <div class="ppv-form-group" id="onboarding-action-value-wrapper">
                        <label>${L.onb_reward_value || 'Érték'} *</label>
                        <input type="text" name="action_value" placeholder="pl. 10" value="0">
                        <small style="color: #999;">${L.onb_reward_value_helper || '💶 Rabatt értéke (pl. 10 = 10% vagy 5 = 5 EUR)'}</small>
                    </div>

                    <!-- GRATIS TERMÉK NEVE (csak FREE_PRODUCT típusnál!) -->
                    <div class="ppv-form-group" id="onboarding-free-product-name-wrapper" style="display: none;">
                        <label>${L.onb_reward_free_product || '🎁 Produktname'}</label>
                        <input type="text" name="free_product" id="onboarding-free-product-name" placeholder="${L.onb_reward_free_product_placeholder || 'pl. Kaffee + Kuchen'}">
                        <small style="color: #999;">${L.onb_reward_free_product_helper || '🎁 Az ingyenes termék neve (pl. Kávé + Sütemény)'}</small>
                    </div>

                    <!-- GRATIS TERMÉK ÉRTÉKE -->
                    <div class="ppv-form-group" id="onboarding-free-product-value-wrapper" style="display: none;">
                        <label style="color: #ff9800;">${L.onb_reward_free_product_value || '💰 Produktwert'} <span style="color: #ff0000;">*</span></label>
                        <input type="number" name="free_product_value" id="onboarding-free-product-value" value="0" min="0.01" step="0.01" placeholder="0.00" style="border-color: #ff9800;">
                        <small style="color: #ff9800;">${L.onb_reward_free_product_value_helper || '💰 A termék rendes ára'}</small>
                    </div>

                    <div class="ppv-form-group">
                        <label>${L.onb_reward_points_given || 'Pontok adva (ha beváltják)'} *</label>
                        <input type="number" name="points_given" placeholder="5" min="0" value="0">
                        <small style="color: #999;">${L.onb_reward_points_given_helper || '⭐ Ezek a pontok jutalmazzák az ügyfelet beváltáskor'}</small>
                    </div>

                    <div class="ppv-wizard-tip">
                        ${L.onb_reward_tip || '💡 Később adhatsz hozzá képet és további prémiumokat!'}
                    </div>
                </form>

                <div class="ppv-modal-actions">
                    <button type="button" class="ppv-btn ppv-btn-secondary" data-action="skip">${L.onb_btn_skip || '⏭️ Kihagyom'}</button>
                    <button type="button" class="ppv-btn ppv-btn-primary" data-action="finish">${L.onb_btn_finish || '🎉 Befejezés'}</button>
                </div>
            `);

            content.html(html);

            // 🎯 DYNAMIC FORM - Show/Hide fields based on action_type
            const toggleOnboardingFields = () => {
                const selectedType = content.find('[name="action_type"]').val();
                const actionValueWrapper = content.find('#onboarding-action-value-wrapper');
                const freeProductNameWrapper = content.find('#onboarding-free-product-name-wrapper');
                const freeProductValueWrapper = content.find('#onboarding-free-product-value-wrapper');

                if (selectedType === 'free_product') {
                    // 🎁 Ingyenes termék - Product mezők láthatók, action_value HIDDEN
                    actionValueWrapper.hide();
                    content.find('[name="action_value"]').val('0');
                    freeProductNameWrapper.show();
                    freeProductValueWrapper.show();
                } else {
                    // 💶 Rabatt típusok - action_value látható, Product mezők HIDDEN
                    actionValueWrapper.show();
                    freeProductNameWrapper.hide();
                    freeProductValueWrapper.hide();
                }
            };

            content.on('change', '[name="action_type"]', toggleOnboardingFields);
            toggleOnboardingFields(); // Initial check

            // Finish gomb
            content.on('click', '[data-action="finish"]', (e) => {
                e.preventDefault();

                // Manual validation - használjuk a content.find()-ot a modal scope miatt!
                const title = content.find('[name="title"]').val().trim();
                const required_points = parseInt(content.find('[name="required_points"]').val());

                if (!title) {
                    this.showToast(L.onb_error_reward_name || '❌ Kérlek add meg a prémium nevét!', 'error');
                    content.find('[name="title"]').focus();
                    return;
                }

                if (!required_points || required_points < 1) {
                    this.showToast(L.onb_error_reward_points || '❌ Kérlek adj meg legalább 1 pontot!', 'error');
                    content.find('[name="required_points"]').focus();
                    return;
                }

                const data = {
                    title: title,
                    required_points: required_points,
                    description: content.find('[name="description"]').val(),
                    action_type: content.find('[name="action_type"]').val(),
                    action_value: content.find('[name="action_value"]').val(),
                    points_given: parseInt(content.find('[name="points_given"]').val()) || 0,
                    free_product: content.find('[name="free_product"]').val() || '',
                    free_product_value: parseFloat(content.find('[name="free_product_value"]').val()) || 0
                };

                this.saveWizardStep('reward', data, modal);
            });

            // Skip gomb
            content.on('click', '[data-action="skip"]', () => {
                this.closeModal(modal);
            });
        }

        /** ============================================================
         *  💾 SAVE WIZARD STEP
         * ============================================================ */
        saveWizardStep(step, data, modal) {
            const btn = modal.find('[data-action="next"], [data-action="finish"]');
            btn.prop('disabled', true).text(L.onb_state_saving || '⏳ Mentés...');

            $.ajax({
                url: this.config.rest_url + 'complete-step',
                method: 'POST',
                contentType: 'application/json',
                headers: {
                    'X-WP-Nonce': this.config.nonce
                },
                data: JSON.stringify({
                    step: step,
                    value: data
                }),
                success: (response) => {
                    if (response.success) {
                        this.progress = response.progress;

                        // Ha ez volt az utolsó lépés és 100%
                        if (step === 'reward' || this.progress.is_complete) {
                            this.closeModal(modal);
                            setTimeout(() => this.showCelebrationModal(), 300);
                        } else {
                            // Következő lépés
                            this.wizardStep++;
                            this.renderWizardStep(modal);
                        }

                        // Frissítjük a Progress Card-ot
                        this.renderProgressCard();
                    }
                },
                error: () => {
                    alert(L.onb_error_save || '❌ Hiba történt a mentés során');
                    btn.prop('disabled', false).text(step === 'reward' ? (L.onb_btn_finish || '🎉 Befejezés') : (L.onb_btn_next || '➡️ Következő'));
                }
            });
        }

        /** ============================================================
         *  🎉 CELEBRATION MODAL
         * ============================================================ */
        showCelebrationModal() {
            const modal = $(`
                <div class="ppv-onboarding-modal-backdrop">
                    <div class="ppv-onboarding-modal ppv-celebration-modal">
                        <div class="ppv-confetti-container"></div>

                        <div class="ppv-modal-icon ppv-celebration-icon">🎉</div>

                        <h2>${L.onb_celebration_title || 'Gratulálunk!'}</h2>

                        <p>${L.onb_celebration_subtitle || 'A PunktePass használatra kész! 🚀'}</p>

                        <p>${L.onb_celebration_message || 'A vendégek most már gyűjthetnek pontokat és beválthatják a prémiumokat!'}</p>

                        <div class="ppv-celebration-tip">
                            ${L.onb_celebration_tip || '💡 <strong>Tipp:</strong> Hozz létre egy kampányt hogy gyorsabban gyűjtsenek pontokat a vendégeid!'}
                        </div>

                        <button class="ppv-btn ppv-btn-primary ppv-btn-large" data-action="close">${L.onb_btn_done || '✅ Rendben'}</button>

                        <div class="ppv-auto-close">${L.onb_celebration_autoclose || 'Auto-bezár 5 mp múlva'}</div>
                    </div>
                </div>
            `);

            $('body').append(modal);
            setTimeout(() => modal.addClass('show'), 10);

            // Confetti animation
            this.showConfetti(modal.find('.ppv-confetti-container'));

            // Auto-close 5 másodperc
            let countdown = 5;
            const timer = setInterval(() => {
                countdown--;
                const autoCloseText = L.onb_celebration_autoclose || 'Auto-bezár 5 mp múlva';
                modal.find('.ppv-auto-close').text(autoCloseText.replace('5', countdown));

                if (countdown <= 0) {
                    clearInterval(timer);
                    this.closeModal(modal);
                    this.hideAllOnboarding();
                }
            }, 1000);

            modal.on('click', '[data-action="close"]', () => {
                clearInterval(timer);
                this.closeModal(modal);
                this.hideAllOnboarding();
            });

            modal.on('click', '.ppv-onboarding-modal-backdrop', (e) => {
                if (e.target === e.currentTarget) {
                    clearInterval(timer);
                    this.closeModal(modal);
                    this.hideAllOnboarding();
                }
            });
        }

        /** ============================================================
         *  🎊 CONFETTI ANIMATION
         * ============================================================ */
        showConfetti(container) {
            const colors = ['#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6', '#ec4899'];

            for (let i = 0; i < 40; i++) {
                const confetti = $('<div class="ppv-confetti"></div>');
                confetti.css({
                    left: Math.random() * 100 + '%',
                    backgroundColor: colors[Math.floor(Math.random() * colors.length)],
                    animationDelay: Math.random() * 0.5 + 's',
                    animationDuration: (Math.random() * 1 + 2) + 's'
                });
                container.append(confetti);
            }
        }

        /** ============================================================
         *  📊 PROGRESS CARD
         * ============================================================ */
        renderProgressCard() {
            // Eltávolítjuk a régit ha van
            $('.ppv-onboarding-progress-card').remove();

            // Ha 100% és már látta a celebration-t, ne mutassuk
            if (this.progress.is_complete) {
                return;
            }

            const percentage = this.progress.percentage;
            const completed = this.progress.completed;
            const total = this.progress.total;

            const card = $(`
                <div class="ppv-onboarding-progress-card">
                    <div class="ppv-progress-header">
                        <h3>${L.onb_progress_title || '🎯 Kezdeti Beállítások'}</h3>
                        <button class="ppv-card-close" data-action="dismiss">&times;</button>
                    </div>

                    <div class="ppv-progress-bar-container">
                        <div class="ppv-progress-bar">
                            <div class="ppv-progress-fill" style="width: ${percentage}%"></div>
                        </div>
                        <div class="ppv-progress-label">${percentage}%</div>
                    </div>

                    <div class="ppv-progress-steps">
                        ${this.renderProgressStep('profile_lite', L.onb_progress_step_profile || 'Profil adatok kitöltve', this.progress.steps.profile_lite)}
                        ${this.renderProgressStep('reward', L.onb_progress_step_reward || 'Első prémium', this.progress.steps.reward)}
                    </div>

                    <div class="ppv-progress-actions">
                        <button class="ppv-btn ppv-btn-secondary ppv-btn-sm" data-action="later">${L.onb_btn_later || '⏭️ Később'}</button>
                        <button class="ppv-btn ppv-btn-primary ppv-btn-sm" data-action="continue">${L.onb_btn_continue || '🚀 Folytatás'}</button>
                    </div>
                </div>
            `);

            // QR-center oldalon vagy dashboard-on jelenjen meg
            if ($('.ppv-qr-wrapper').length) {
                $('.ppv-qr-wrapper').prepend(card);
            } else if ($('.ppv-rewards-wrapper').length) {
                $('.ppv-rewards-wrapper').prepend(card);
            } else {
                $('body').append(card);
            }

            setTimeout(() => card.addClass('show'), 10);

            // Gombok
            card.on('click', '[data-action="continue"]', () => {
                this.showWizardModal();
            });

            card.on('click', '[data-action="later"]', () => {
                card.removeClass('show');
                setTimeout(() => card.remove(), 300);
            });

            card.on('click', '[data-action="dismiss"]', () => {
                if (confirm(L.onb_confirm_dismiss || 'Biztosan bezárod? Később visszahozhatod a beállításokból.')) {
                    this.dismissOnboarding('permanent');
                    card.removeClass('show');
                    setTimeout(() => card.remove(), 300);
                }
            });

            // Step kattintás - navigálás
            card.on('click', '.ppv-progress-step:not(.completed)', (e) => {
                const step = $(e.currentTarget).data('step');
                this.navigateToStep(step);
            });
        }

        renderProgressStep(key, label, completed) {
            const L = window.ppv_lang || {};
            return `
                <div class="ppv-progress-step ${completed ? 'completed' : ''}" data-step="${key}">
                    <div class="ppv-step-icon">${completed ? '✅' : '⏳'}</div>
                    <div class="ppv-step-label">${label}</div>
                    ${!completed ? `<button class="ppv-step-action">➡️ ${L.onb_btn_next || 'Beállítás'}</button>` : ''}
                </div>
            `;
        }

        navigateToStep(step) {
            if (step === 'profile_lite') {
                // Wizard megnyitása első lépésre
                this.wizardStep = 0;
                this.showWizardModal();
            } else if (step === 'reward') {
                // Wizard megnyitása második lépésre
                this.wizardStep = 1;
                this.showWizardModal();
            }
        }

        /** ============================================================
         *  📌 STICKY REMINDER
         * ============================================================ */
        renderStickyReminder() {
            if ($('.ppv-onboarding-sticky').length) {
                return; // Már létezik
            }

            const completed = this.progress.completed;
            const total = this.progress.total;
            let text = L.onb_sticky_start || '🚀 Kezdd el a beállítást!';

            if (completed === 1) {
                text = L.onb_sticky_finish_1_of_2 || '🎯 Fejezd be a beállítást (1/2)';
            } else if (completed >= 4) {
                text = L.onb_sticky_almost_done_4_of_5 || '🔥 Már majdnem kész! (4/5)';
            }

            const sticky = $(`
                <div class="ppv-onboarding-sticky">
                    <button class="ppv-sticky-btn" data-action="open">
                        ${text}
                    </button>
                    <button class="ppv-sticky-close" data-action="hide">&times;</button>
                </div>
            `);

            $('body').append(sticky);
            setTimeout(() => sticky.addClass('show'), 10);

            sticky.on('click', '[data-action="open"]', () => {
                // Scroll to Progress Card vagy megnyitjuk a wizard-ot
                const card = $('.ppv-onboarding-progress-card');
                if (card.length) {
                    $('html, body').animate({
                        scrollTop: card.offset().top - 100
                    }, 500);
                    card.addClass('highlight');
                    setTimeout(() => card.removeClass('highlight'), 2000);
                } else {
                    this.showWizardModal();
                }
            });

            sticky.on('click', '[data-action="hide"]', () => {
                if (confirm(L.onb_confirm_hide || 'Biztosan elrejted? Később visszahozhatod a beállításokból.')) {
                    this.dismissOnboarding('sticky');
                    sticky.removeClass('show');
                    setTimeout(() => sticky.remove(), 300);
                }
            });
        }

        /** ============================================================
         *  🔄 REFRESH PROGRESS
         * ============================================================ */
        refreshProgress() {
            $.get(this.config.rest_url + 'progress', (response) => {
                if (response.success) {
                    this.progress = response.progress;
                    this.renderProgressCard();

                    // Ha közben elérte a 100%-ot
                    if (this.progress.is_complete && !this.celebrationShown) {
                        this.celebrationShown = true;
                        this.showCelebrationModal();
                    }
                }
            });
        }

        /** ============================================================
         *  ❌ DISMISS
         * ============================================================ */
        dismissOnboarding(type) {
            $.ajax({
                url: this.config.rest_url + 'dismiss',
                method: 'POST',
                contentType: 'application/json',
                headers: {
                    'X-WP-Nonce': this.config.nonce
                },
                data: JSON.stringify({ type: type })
            });
        }

        /** ============================================================
         *  🚫 HIDE ALL
         * ============================================================ */
        hideAllOnboarding() {
            $('.ppv-onboarding-progress-card').fadeOut(300, function() { $(this).remove(); });
            $('.ppv-onboarding-sticky').fadeOut(300, function() { $(this).remove(); });
        }

        /** ============================================================
         *  🍞 TOAST NOTIFICATION
         * ============================================================ */
        showToast(message, type = 'info') {
            const bgColors = {
                success: '#10b981',
                error: '#ef4444',
                info: '#3b82f6'
            };

            const toast = $(`
                <div class="ppv-onboarding-toast" style="
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    padding: 12px 20px;
                    background: ${bgColors[type] || bgColors.info};
                    color: white;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: 999999;
                    font-size: 14px;
                    font-weight: 500;
                    opacity: 0;
                    transition: opacity 0.3s;
                ">
                    ${message}
                </div>
            `);

            $('body').append(toast);

            setTimeout(() => toast.css('opacity', '1'), 10);

            setTimeout(() => {
                toast.css('opacity', '0');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        /** ============================================================
         *  🔒 CLOSE MODAL
         * ============================================================ */
        closeModal(modal) {
            modal.removeClass('show');
            setTimeout(() => modal.remove(), 300);
        }
    }

    // Initialize
    new PPVOnboarding();

})(jQuery);
