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

                        <h2>Üdvözlünk a PunktePass-ban!</h2>

                        <p>Segítünk beállítani a 2 alapvető dolgot, hogy a vendégek pontokat gyűjthessenek!</p>

                        <div class="ppv-welcome-steps">
                            <div class="ppv-welcome-step">
                                <span class="step-number">1</span>
                                <span>Töltsd ki az üzlet alapadatait</span>
                            </div>
                            <div class="ppv-welcome-step">
                                <span class="step-number">2</span>
                                <span>Hozd létre az első prémiumot</span>
                            </div>
                        </div>

                        <div class="ppv-welcome-time">
                            ⏱️ Körülbelül 3 perc
                        </div>

                        <div class="ppv-modal-actions">
                            <button class="ppv-btn ppv-btn-secondary" data-action="later">⏭️ Később</button>
                            <button class="ppv-btn ppv-btn-primary" data-action="start">🚀 Kezdjük!</button>
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

                <h3>1️⃣ Üzlet Alapadatok</h3>

                <form class="ppv-wizard-form" id="ppv-profile-form">
                    <div class="ppv-form-group">
                        <label>Shop név *</label>
                        <input type="text" name="company_name" required placeholder="pl. Teszt Kávézó">
                    </div>

                    <div class="ppv-form-group">
                        <label>Ország *</label>
                        <select name="country" required>
                            <option value="">Válassz országot...</option>
                            <option value="HU">Magyarország</option>
                            <option value="AT">Ausztria</option>
                            <option value="DE">Németország</option>
                            <option value="SK">Szlovákia</option>
                            <option value="RO">Románia</option>
                            <option value="HR">Horvátország</option>
                            <option value="SI">Szlovénia</option>
                        </select>
                    </div>

                    <div class="ppv-form-group">
                        <label>Cím *</label>
                        <input type="text" name="address" required placeholder="pl. Fő utca 12.">
                    </div>

                    <div class="ppv-form-row">
                        <div class="ppv-form-group">
                            <label>Város *</label>
                            <input type="text" name="city" required placeholder="pl. Budapest">
                        </div>

                        <div class="ppv-form-group">
                            <label>Irányítószám *</label>
                            <input type="text" name="zip" required placeholder="pl. 1011">
                        </div>
                    </div>

                    <div class="ppv-form-group">
                        <label>Telefon *</label>
                        <input type="tel" name="phone" required placeholder="pl. +36 30 123 4567">
                    </div>

                    <div class="ppv-form-group">
                        <label>Ortskoordinaten</label>
                        <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <div style="flex: 1;">
                                <label style="font-size: 12px; color: #999;">Breitengrad (Latitude)</label>
                                <input type="text" name="latitude" placeholder="pl. 47.5000" pattern="-?[0-9]+\\.?[0-9]*">
                            </div>
                            <div style="flex: 1;">
                                <label style="font-size: 12px; color: #999;">Längengrad (Longitude)</label>
                                <input type="text" name="longitude" placeholder="pl. 19.0400" pattern="-?[0-9]+\\.?[0-9]*">
                            </div>
                        </div>
                        <button type="button" class="ppv-btn ppv-btn-secondary" id="ppv-geocode-btn" style="width: 100%;">
                            🔍 Koordinaten suchen
                        </button>
                        <small style="color: #999; margin-top: 5px; display: block;">
                            💡 Opcionális: GPS koordinátákat automatikusan kereshetünk a cím alapján
                        </small>
                    </div>
                </form>

                <div class="ppv-modal-actions">
                    <button type="button" class="ppv-btn ppv-btn-secondary" data-action="skip">⏭️ Kihagyom</button>
                    <button type="button" class="ppv-btn ppv-btn-primary" data-action="next">➡️ Következő</button>
                </div>
            `);

            content.html(html);

            // Koordináták keresés
            html.on('click', '#ppv-geocode-btn', (e) => {
                e.preventDefault();
                const address = $('[name="address"]').val();
                const city = $('[name="city"]').val();
                const zip = $('[name="zip"]').val();
                const country = $('[name="country"]').val();

                if (!address || !city) {
                    alert('Kérlek add meg a címet és várost először!');
                    return;
                }

                const btn = $(e.target);
                btn.prop('disabled', true).text('🔍 Keresés...');

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
                            $('[name="latitude"]').val(response.lat.toFixed(4));
                            $('[name="longitude"]').val(response.lng.toFixed(4));
                            this.showToast('✅ Koordináták megtalálva!', 'success');
                        } else {
                            this.showToast('❌ Nem találtunk koordinátákat', 'error');
                        }
                    },
                    error: () => {
                        this.showToast('❌ Geocoding hiba', 'error');
                    },
                    complete: () => {
                        btn.prop('disabled', false).text('🔍 Koordinaten suchen');
                    }
                });
            });

            // Next gomb
            html.on('click', '[data-action="next"]', (e) => {
                e.preventDefault();
                const form = $('#ppv-profile-form')[0];

                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                const data = {
                    company_name: $('[name="company_name"]').val(),
                    country: $('[name="country"]').val(),
                    address: $('[name="address"]').val(),
                    city: $('[name="city"]').val(),
                    zip: $('[name="zip"]').val(),
                    phone: $('[name="phone"]').val(),
                    latitude: $('[name="latitude"]').val() || null,
                    longitude: $('[name="longitude"]').val() || null
                };

                this.saveWizardStep('profile_lite', data, modal);
            });

            // Skip gomb
            html.on('click', '[data-action="skip"]', () => {
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

                <h3>2️⃣ Első Prémium Létrehozása</h3>

                <form class="ppv-wizard-form" id="ppv-reward-form">
                    <div class="ppv-form-group">
                        <label>Prémium neve *</label>
                        <input type="text" name="title" required placeholder="pl. Ingyenes Kávé">
                    </div>

                    <div class="ppv-form-group">
                        <label>Szükséges pontok *</label>
                        <input type="number" name="required_points" required placeholder="100" min="1" value="100">
                    </div>

                    <div class="ppv-form-group">
                        <label>Leírás (opcionális)</label>
                        <textarea name="description" rows="3" placeholder="pl. Egy ingyenes eszpresszó vagy cappuccino választható."></textarea>
                    </div>

                    <div class="ppv-form-group">
                        <label>Jutalmazás típusa *</label>
                        <select name="action_type" required>
                            <option value="discount_percent">Rabatt (%)</option>
                            <option value="discount_fixed">Fix rabatt</option>
                            <option value="free_product" selected>Ingyenes termék</option>
                        </select>
                    </div>

                    <div class="ppv-form-group">
                        <label>Érték *</label>
                        <input type="text" name="action_value" required placeholder="pl. 10" value="0">
                        <small style="color: #999;">💶 Érték a jutalomhoz (pl. 10% vagy 5 EUR)</small>
                    </div>

                    <div class="ppv-form-group">
                        <label>Pontok adva (ha beváltják) *</label>
                        <input type="number" name="points_given" required placeholder="5" min="0" value="0">
                        <small style="color: #999;">⭐ Ezek a pontok jutalmazzák az ügyfelet beváltáskor</small>
                    </div>

                    <div class="ppv-wizard-tip">
                        💡 Később adhatsz hozzá képet és további prémiumokat!
                    </div>
                </form>

                <div class="ppv-modal-actions">
                    <button type="button" class="ppv-btn ppv-btn-secondary" data-action="skip">⏭️ Kihagyom</button>
                    <button type="button" class="ppv-btn ppv-btn-primary" data-action="finish">🎉 Befejezés</button>
                </div>
            `);

            content.html(html);

            // Finish gomb
            html.on('click', '[data-action="finish"]', (e) => {
                e.preventDefault();
                const form = $('#ppv-reward-form')[0];

                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                const data = {
                    title: $('[name="title"]').val(),
                    required_points: parseInt($('[name="required_points"]').val()),
                    description: $('[name="description"]').val(),
                    action_type: $('[name="action_type"]').val(),
                    action_value: $('[name="action_value"]').val(),
                    points_given: parseInt($('[name="points_given"]').val())
                };

                this.saveWizardStep('reward', data, modal);
            });

            // Skip gomb
            html.on('click', '[data-action="skip"]', () => {
                this.closeModal(modal);
            });
        }

        /** ============================================================
         *  💾 SAVE WIZARD STEP
         * ============================================================ */
        saveWizardStep(step, data, modal) {
            const btn = modal.find('[data-action="next"], [data-action="finish"]');
            btn.prop('disabled', true).text('⏳ Mentés...');

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
                    alert('❌ Hiba történt a mentés során');
                    btn.prop('disabled', false).text(step === 'reward' ? '🎉 Befejezés' : '➡️ Következő');
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

                        <h2>Gratulálunk!</h2>

                        <p>A PunktePass használatra kész! 🚀</p>

                        <p>A vendégek most már gyűjthetnek pontokat és beválthatják a prémiumokat!</p>

                        <div class="ppv-celebration-tip">
                            💡 <strong>Tipp:</strong> Hozz létre egy kampányt hogy gyorsabban gyűjtsenek pontokat a vendégeid!
                        </div>

                        <button class="ppv-btn ppv-btn-primary ppv-btn-large" data-action="close">✅ Rendben</button>

                        <div class="ppv-auto-close">Auto-bezár 5 mp múlva</div>
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
                modal.find('.ppv-auto-close').text(`Auto-bezár ${countdown} mp múlva`);

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
                        <h3>🎯 Kezdeti Beállítások</h3>
                        <button class="ppv-card-close" data-action="dismiss">&times;</button>
                    </div>

                    <div class="ppv-progress-bar-container">
                        <div class="ppv-progress-bar">
                            <div class="ppv-progress-fill" style="width: ${percentage}%"></div>
                        </div>
                        <div class="ppv-progress-label">${percentage}%</div>
                    </div>

                    <div class="ppv-progress-steps">
                        ${this.renderProgressStep('profile_lite', 'Profil adatok kitöltve', this.progress.steps.profile_lite)}
                        ${this.renderProgressStep('reward', 'Első prémium', this.progress.steps.reward)}
                    </div>

                    <div class="ppv-progress-actions">
                        <button class="ppv-btn ppv-btn-secondary ppv-btn-sm" data-action="later">⏭️ Később</button>
                        <button class="ppv-btn ppv-btn-primary ppv-btn-sm" data-action="continue">🚀 Folytatás</button>
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
                if (confirm('Biztosan bezárod? Később visszahozhatod a beállításokból.')) {
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
            return `
                <div class="ppv-progress-step ${completed ? 'completed' : ''}" data-step="${key}">
                    <div class="ppv-step-icon">${completed ? '✅' : '⏳'}</div>
                    <div class="ppv-step-label">${label}</div>
                    ${!completed ? '<button class="ppv-step-action">➡️ Beállítás</button>' : ''}
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
            let text = '🚀 Kezdd el a beállítást!';

            if (completed === 1) {
                text = '🎯 Fejezd be a beállítást (1/2)';
            } else if (completed >= 4) {
                text = '🔥 Már majdnem kész! (4/5)';
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
                if (confirm('Biztosan elrejted? Később visszahozhatod a beállításokból.')) {
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
