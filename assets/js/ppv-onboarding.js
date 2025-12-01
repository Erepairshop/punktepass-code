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


    class PPVOnboarding {
        constructor() {
            this.config = config;
            this.progress = config.progress;
            this.wizardStep = 0;
            this.wizardData = {
                profile_lite: {},
                reward: {},
                device: {}
            };
            this.celebrationShown = false; // ✅ Initialize flag
            this.progressInterval = null; // ✅ Store interval ID for cleanup
            this.ablySubscriberId = null; // ✅ Store Ably subscriber ID for cleanup

            this.init();
        }

        init() {
            // Ha már completed vagy dismissed, ne csináljunk semmit
            if (this.progress.is_complete || this.config.dismissed) {
                return;
            }

            $(document).ready(() => {
                // Welcome Modal - csak QR-center oldalon, ha még nem látta
                if (this.config.is_qr_center && !this.config.welcome_shown) {
                    setTimeout(() => this.showWelcomeModal(), 1000);
                }

                // Progress Card - mindig
                this.renderProgressCard();

                // ❌ Sticky Reminder eltávolítva - nem kell

                // 📡 Try Ably real-time first, fallback to polling
                if (this.config.ably && this.config.ably.key && window.PPV_ABLY_MANAGER) {
                    this.initAbly();
                } else {
                    // Fallback: Progress polling - 15 másodpercenként
                    this.progressInterval = setInterval(() => this.refreshProgress(), 15000);
                }
            });
        }

        /** ============================================================
         *  📡 ABLY REAL-TIME INITIALIZATION (uses shared manager)
         * ============================================================ */
        initAbly() {

            const manager = window.PPV_ABLY_MANAGER;

            // Initialize shared connection
            if (!manager.init(this.config.ably)) {
                console.warn('📡 [ONBOARDING] Failed to init Ably, using polling');
                this.startPollingFallback();
                return;
            }

            // Listen for connection state changes
            manager.onStateChange((state) => {
                if (state === 'connected') {
                    // Stop polling if running
                    if (this.progressInterval) {
                        clearInterval(this.progressInterval);
                        this.progressInterval = null;
                    }
                } else if (state === 'failed' || state === 'suspended') {
                    console.warn('⚠️ [ONBOARDING] Ably connection issue, starting polling fallback');
                    this.startPollingFallback();
                }
            });

            // Subscribe to onboarding-progress events via shared manager
            this.ablySubscriberId = manager.subscribe(
                this.config.ably.channel,
                'onboarding-progress',
                (message) => {
                    this.handleProgressUpdate(message.data);
                },
                'onboarding'
            );
        }

        startPollingFallback() {
            if (!this.progressInterval) {
                this.progressInterval = setInterval(() => this.refreshProgress(), 15000);
            }
        }

        handleProgressUpdate(progressData) {
            this.progress = progressData;
            this.renderProgressCard();

            // Ha közben elérte a 100%-ot
            if (this.progress.is_complete && !this.celebrationShown) {
                this.celebrationShown = true;
                this.showCelebrationModal();
            }
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

                        <h2>${L.onb_welcome_title || 'Willkommen bei PunktePass!'}</h2>

                        <p>${L.onb_welcome_subtitle || 'Wir helfen Ihnen, die 3 Grundlagen einzurichten, damit Gäste Punkte sammeln können!'}</p>

                        <div class="ppv-welcome-steps">
                            <div class="ppv-welcome-step">
                                <span class="step-number">1</span>
                                <span>${L.onb_welcome_step1 || 'Geschäftsdaten ausfüllen'}</span>
                            </div>
                            <div class="ppv-welcome-step">
                                <span class="step-number">2</span>
                                <span>${L.onb_welcome_step2 || 'Erste Prämie erstellen'}</span>
                            </div>
                            <div class="ppv-welcome-step">
                                <span class="step-number">3</span>
                                <span>${L.onb_welcome_step3 || 'Gerät registrieren'}</span>
                            </div>
                        </div>

                        <div class="ppv-welcome-time">
                            ${L.onb_welcome_time || '⏱️ Ca. 3 Minuten'}
                        </div>

                        <div class="ppv-modal-actions">
                            <button class="ppv-btn ppv-btn-secondary" data-action="later">${L.onb_btn_later || '⏭️ Später'}</button>
                            <button class="ppv-btn ppv-btn-primary" data-action="start">${L.onb_btn_start || '🚀 Los geht\'s!'}</button>
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
                this.postponeOnboarding(); // ⏰ 8 órás késleltetés
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
            $.post(this.config.ajax_url, { action: 'ppv_onboarding_mark_welcome' }, () => {
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
                    <div class="ppv-progress-text">1/2 ${L.onb_step || 'Schritt'}</div>
                </div>

                <div class="ppv-wizard-header">
                    <div class="ppv-wizard-icon">🏪</div>
                    <h3>${L.onb_profile_step_title || 'Geschäftsdaten'}</h3>
                    <p class="ppv-wizard-subtitle">${L.onb_profile_step_subtitle || 'Füllen Sie die wichtigsten Daten Ihres Geschäfts aus'}</p>
                </div>

                <form class="ppv-wizard-form" id="ppv-profile-form">
                    <!-- SECTION: Alapadatok -->
                    <div class="ppv-form-section">
                        <div class="ppv-section-title">
                            <span class="ppv-section-icon">📋</span>
                            ${L.onb_section_basic || 'Grunddaten'}
                        </div>

                        <div class="ppv-form-row">
                            <div class="ppv-form-group ppv-form-group-wide">
                                <label>${L.onb_profile_shop_name || 'Geschäftsname'} <span class="required">*</span></label>
                                <input type="text" name="shop_name" required placeholder="${L.onb_profile_shop_name_placeholder || 'z.B. Test Café'}">
                                <small>${L.onb_profile_shop_name_help || 'Dieser Name wird den Kunden angezeigt'}</small>
                            </div>
                        </div>

                        <div class="ppv-form-row">
                            <div class="ppv-form-group">
                                <label>${L.onb_profile_company_name || 'Firmenname'}</label>
                                <input type="text" name="company_name" placeholder="${L.onb_profile_company_name_placeholder || 'z.B. Test GmbH'}">
                                <small>${L.onb_profile_company_name_help || 'Offizieller Firmenname (für Rechnungen)'}</small>
                            </div>
                            <div class="ppv-form-group">
                                <label>${L.onb_profile_country || 'Land'} <span class="required">*</span></label>
                                <select name="country" required>
                                    <option value="">${L.onb_profile_country_placeholder || 'Auswählen...'}</option>
                                    <option value="HU">🇭🇺 ${L.country_hu || 'Ungarn'}</option>
                                    <option value="DE">🇩🇪 ${L.country_de || 'Deutschland'}</option>
                                    <option value="AT">🇦🇹 ${L.country_at || 'Österreich'}</option>
                                    <option value="RO">🇷🇴 ${L.country_ro || 'Rumänien'}</option>
                                    <option value="SK">🇸🇰 ${L.country_sk || 'Slowakei'}</option>
                                    <option value="HR">🇭🇷 ${L.country_hr || 'Kroatien'}</option>
                                    <option value="SI">🇸🇮 ${L.country_si || 'Slowenien'}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION: Cím -->
                    <div class="ppv-form-section">
                        <div class="ppv-section-title">
                            <span class="ppv-section-icon">📍</span>
                            ${L.onb_section_address || 'Adresse'}
                        </div>

                        <div class="ppv-form-group">
                            <label>${L.onb_profile_address || 'Straße, Hausnr.'} <span class="required">*</span></label>
                            <input type="text" name="address" required placeholder="${L.onb_profile_address_placeholder || 'z.B. Hauptstraße 12'}">
                        </div>

                        <div class="ppv-form-row ppv-form-row-3">
                            <div class="ppv-form-group">
                                <label>${L.onb_profile_zip || 'PLZ'} <span class="required">*</span></label>
                                <input type="text" name="zip" required placeholder="${L.onb_profile_zip_placeholder || '1011'}">
                            </div>
                            <div class="ppv-form-group ppv-form-group-wide">
                                <label>${L.onb_profile_city || 'Stadt'} <span class="required">*</span></label>
                                <input type="text" name="city" required placeholder="${L.onb_profile_city_placeholder || 'Budapest'}">
                            </div>
                        </div>
                    </div>

                    <!-- SECTION: Térkép & Koordináták -->
                    <div class="ppv-form-section">
                        <div class="ppv-section-title">
                            <span class="ppv-section-icon">🗺️</span>
                            ${L.onb_section_location || 'Standort auf der Karte'} <span class="required">*</span>
                        </div>

                        <div class="ppv-map-container">
                            <div id="ppv-onboarding-map" class="ppv-onboarding-map"></div>
                            <div class="ppv-map-instructions">
                                <span class="ppv-map-icon">👆</span>
                                ${L.onb_map_instructions || 'Klicken Sie auf die Karte, um den genauen Standort zu markieren!'}
                            </div>
                        </div>

                        <div class="ppv-form-row">
                            <div class="ppv-form-group">
                                <label>${L.onb_profile_latitude || 'Breitengrad (Lat)'}</label>
                                <input type="text" name="latitude" id="onb-latitude" placeholder="47.5000" readonly>
                            </div>
                            <div class="ppv-form-group">
                                <label>${L.onb_profile_longitude || 'Längengrad (Lng)'}</label>
                                <input type="text" name="longitude" id="onb-longitude" placeholder="19.0400" readonly>
                            </div>
                        </div>

                        <button type="button" class="ppv-btn ppv-btn-secondary ppv-btn-block" id="ppv-geocode-btn">
                            <span class="btn-icon">🔍</span>
                            ${L.onb_profile_geocode_btn || 'Koordinaten suchen (nach Adresse)'}
                        </button>

                        <button type="button" class="ppv-btn ppv-btn-outline ppv-btn-block" id="ppv-manual-map-btn" style="margin-top: 10px;">
                            <span class="btn-icon">🗺️</span>
                            ${L.onb_manual_map_btn || 'Manuell auf der Karte'}
                        </button>
                    </div>

                    <!-- SECTION: Nyitvatartás -->
                    <div class="ppv-form-section">
                        <div class="ppv-section-title">
                            <span class="ppv-section-icon">🕐</span>
                            ${L.onb_section_hours || 'Öffnungszeiten'} <span class="required">*</span>
                        </div>

                        <div class="ppv-opening-hours-grid">
                            ${this.renderOpeningHoursInputs()}
                        </div>

                        <div class="ppv-quick-hours">
                            <span>${L.onb_quick_fill || 'Schnelleingabe:'}</span>
                            <button type="button" class="ppv-chip" data-hours="9-17">${L.onb_hours_office || '9-17'}</button>
                            <button type="button" class="ppv-chip" data-hours="8-20">${L.onb_hours_retail || '8-20'}</button>
                            <button type="button" class="ppv-chip" data-hours="10-22">${L.onb_hours_gastro || '10-22'}</button>
                            <button type="button" class="ppv-chip" data-hours="0-24">${L.onb_hours_nonstop || '0-24'}</button>
                        </div>
                    </div>

                    <!-- SECTION: Időzóna -->
                    <div class="ppv-form-section">
                        <div class="ppv-section-title">
                            <span class="ppv-section-icon">🌍</span>
                            ${L.onb_section_timezone || 'Zeitzone'} <span class="required">*</span>
                        </div>

                        <div class="ppv-form-group">
                            <select name="timezone" required>
                                <option value="Europe/Budapest">🇭🇺 Budapest (CET/CEST)</option>
                                <option value="Europe/Berlin">🇩🇪 Berlin (CET/CEST)</option>
                                <option value="Europe/Vienna">🇦🇹 Bécs (CET/CEST)</option>
                                <option value="Europe/Bucharest">🇷🇴 Bukarest (EET/EEST)</option>
                                <option value="Europe/Bratislava">🇸🇰 Pozsony (CET/CEST)</option>
                                <option value="Europe/Zagreb">🇭🇷 Zágráb (CET/CEST)</option>
                                <option value="Europe/Ljubljana">🇸🇮 Ljubljana (CET/CEST)</option>
                            </select>
                            <small>${L.onb_timezone_help || 'Öffnungszeiten und Statistiken richten sich danach'}</small>
                        </div>
                    </div>
                </form>

                <div class="ppv-modal-actions">
                    <button type="button" class="ppv-btn ppv-btn-secondary" data-action="skip">
                        ${L.onb_btn_skip || 'Überspringen'}
                    </button>
                    <button type="button" class="ppv-btn ppv-btn-primary" data-action="next">
                        ${L.onb_btn_next || 'Weiter'} <span class="btn-arrow">→</span>
                    </button>
                </div>
            `);

            content.html(html);

            // Initialize map
            this.initOnboardingMap(content);

            // Quick hours buttons
            content.on('click', '.ppv-chip[data-hours]', (e) => {
                const hours = $(e.target).data('hours');
                this.applyQuickHours(content, hours);
            });

            // Geocode button
            content.on('click', '#ppv-geocode-btn', (e) => {
                e.preventDefault();
                this.geocodeAddress(content);
            });

            // Manual map button
            content.on('click', '#ppv-manual-map-btn', (e) => {
                e.preventDefault();
                const lat = parseFloat(content.find('[name="latitude"]').val()) || 47.5;
                const lng = parseFloat(content.find('[name="longitude"]').val()) || 19.04;
                this.openManualMapModal(content, lat, lng);
            });

            // Next button
            content.on('click', '[data-action="next"]', (e) => {
                e.preventDefault();
                const form = content.find('#ppv-profile-form')[0];

                // Check required fields
                const lat = content.find('[name="latitude"]').val();
                const lng = content.find('[name="longitude"]').val();

                if (!lat || !lng) {
                    this.showToast(L.onb_error_map || '❌ Kérlek jelöld meg az üzleted helyét a térképen!', 'error');
                    return;
                }

                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                // Collect opening hours
                const opening_hours = this.collectOpeningHours(content);

                const data = {
                    shop_name: content.find('[name="shop_name"]').val(),
                    company_name: content.find('[name="company_name"]').val(),
                    country: content.find('[name="country"]').val(),
                    address: content.find('[name="address"]').val(),
                    city: content.find('[name="city"]').val(),
                    zip: content.find('[name="zip"]').val(),
                    latitude: parseFloat(lat),
                    longitude: parseFloat(lng),
                    timezone: content.find('[name="timezone"]').val(),
                    opening_hours: opening_hours
                };

                this.saveWizardStep('profile_lite', data, modal);
            });

            // Skip button
            content.on('click', '[data-action="skip"]', () => {
                this.wizardStep = 1;
                this.renderWizardStep(modal);
            });
        }

        /** ============================================================
         *  🗺️ INTERACTIVE MAP
         * ============================================================ */
        initOnboardingMap(content) {
            const mapContainer = content.find('#ppv-onboarding-map')[0];
            if (!mapContainer) return;

            // Default center (Budapest)
            const defaultLat = 47.4979;
            const defaultLng = 19.0402;

            // Check if Leaflet is available
            if (typeof L !== 'undefined' && L.map) {
                this.onbMap = L.map(mapContainer).setView([defaultLat, defaultLng], 13);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap'
                }).addTo(this.onbMap);

                this.onbMarker = null;

                // Click handler
                this.onbMap.on('click', (e) => {
                    const lat = e.latlng.lat.toFixed(4);
                    const lng = e.latlng.lng.toFixed(4);

                    content.find('[name="latitude"]').val(lat);
                    content.find('[name="longitude"]').val(lng);

                    if (this.onbMarker) {
                        this.onbMarker.setLatLng(e.latlng);
                    } else {
                        this.onbMarker = L.marker(e.latlng).addTo(this.onbMap);
                    }

                    content.find('.ppv-map-instructions').addClass('success').html(
                        '<span class="ppv-map-icon">✅</span> ' + (window.ppv_lang?.onb_map_selected || 'Standort ausgewählt!')
                    );
                });

                // Invalidate size after modal animation
                setTimeout(() => this.onbMap.invalidateSize(), 300);
            } else {
                // Fallback: Simple placeholder with manual input
                $(mapContainer).html(`
                    <div class="ppv-map-fallback">
                        <p>🗺️ ${window.ppv_lang?.onb_map_fallback || 'Karte wird geladen...'}</p>
                        <p><small>${window.ppv_lang?.onb_map_fallback_tip || 'Nutzen Sie die Adresssuche'}</small></p>
                    </div>
                `);
                content.find('[name="latitude"], [name="longitude"]').prop('readonly', false);
            }
        }

        /** ============================================================
         *  🔍 GEOCODE ADDRESS
         * ============================================================ */
        geocodeAddress(content) {
            const address = content.find('[name="address"]').val();
            const city = content.find('[name="city"]').val();
            const zip = content.find('[name="zip"]').val();
            const country = content.find('[name="country"]').val();

            if (!address || !city) {
                this.showToast(window.ppv_lang?.onb_error_address || '❌ Bitte zuerst Adresse und Stadt eingeben!', 'error');
                return;
            }

            const btn = content.find('#ppv-geocode-btn');
            btn.prop('disabled', true).html('<span class="btn-icon">⏳</span> ' + (window.ppv_lang?.onb_state_searching || 'Suche...'));

            $.ajax({
                url: this.config.ajax_url,
                method: 'POST',
                data: { action: 'ppv_onboarding_geocode', address, city, zip, country },
                success: (response) => {
                    if (response.success && response.data && response.data.lat && response.data.lng) {
                        const lat = response.data.lat.toFixed(4);
                        const lng = response.data.lng.toFixed(4);

                        content.find('[name="latitude"]').val(lat);
                        content.find('[name="longitude"]').val(lng);

                        // Update map
                        if (this.onbMap) {
                            const latlng = [response.data.lat, response.data.lng];
                            this.onbMap.setView(latlng, 16);

                            if (this.onbMarker) {
                                this.onbMarker.setLatLng(latlng);
                            } else {
                                this.onbMarker = L.marker(latlng).addTo(this.onbMap);
                            }
                        }

                        content.find('.ppv-map-instructions').addClass('success').html(
                            '<span class="ppv-map-icon">✅</span> ' + (window.ppv_lang?.onb_map_selected || 'Standort ausgewählt!')
                        );

                        this.showToast(window.ppv_lang?.onb_success_geocode || '✅ Koordinaten gefunden!', 'success');
                    } else {
                        this.showToast(window.ppv_lang?.onb_error_not_found || '❌ Adresse nicht gefunden', 'error');
                    }
                },
                error: () => {
                    this.showToast(window.ppv_lang?.onb_error_geocoding || '❌ Geocoding Fehler', 'error');
                },
                complete: () => {
                    btn.prop('disabled', false).html('<span class="btn-icon">🔍</span> ' + (window.ppv_lang?.onb_profile_geocode_btn || 'Koordinaten suchen (nach Adresse)'));
                }
            });
        }

        /** ============================================================
         *  🗺️ MANUAL MAP MODAL
         * ============================================================ */
        openManualMapModal(content, defaultLat, defaultLng) {
            const L = window.ppv_lang || {};
            const mapTitle = L.map_modal_title || 'Standort auf der Karte markieren';
            const mapClick = L.map_modal_click || 'Klicken Sie auf die Karte';
            const mapCancel = L.map_modal_cancel || 'Abbrechen';
            const mapConfirm = L.map_modal_confirm || 'Übernehmen';

            // Remove existing modal
            $('#ppv-onb-map-modal').remove();

            const modalHTML = `
                <div id="ppv-onb-map-modal" style="
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
                        <div style="
                            padding: 1.5rem;
                            border-bottom: 1px solid #e5e7eb;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        ">
                            <h2 style="margin: 0; font-size: 1.3rem;">🗺️ ${mapTitle}</h2>
                            <button id="ppv-onb-map-close" style="
                                background: none;
                                border: none;
                                font-size: 1.5rem;
                                cursor: pointer;
                                color: #666;
                            ">✕</button>
                        </div>
                        <div id="ppv-onb-manual-map" style="
                            flex: 1;
                            min-height: 400px;
                            margin: 1rem;
                            border-radius: 8px;
                            border: 2px solid #ddd;
                        "></div>
                        <div style="
                            padding: 1.5rem;
                            border-top: 1px solid #e5e7eb;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            gap: 1rem;
                        ">
                            <p style="margin: 0; color: #666; font-size: 0.9rem;">
                                📍 <strong id="ppv-onb-coord-display">${mapClick}</strong>
                            </p>
                            <div style="display: flex; gap: 0.75rem;">
                                <button id="ppv-onb-map-cancel" style="
                                    padding: 0.75rem 1.5rem;
                                    border: 1px solid #ddd;
                                    background: #f0f0f0;
                                    border-radius: 6px;
                                    cursor: pointer;
                                    font-weight: 600;
                                ">${mapCancel}</button>
                                <button id="ppv-onb-map-confirm" style="
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

            $('body').append(modalHTML);

            let selectedCoords = null;
            let manualMap = null;
            let manualMarker = null;

            // Initialize Leaflet map in modal
            setTimeout(() => {
                const mapContainer = document.getElementById('ppv-onb-manual-map');
                if (!mapContainer) return;

                if (typeof window.L !== 'undefined' && window.L.map) {
                    manualMap = window.L.map(mapContainer).setView([defaultLat, defaultLng], 15);

                    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap'
                    }).addTo(manualMap);

                    // Click handler
                    manualMap.on('click', (e) => {
                        const lat = e.latlng.lat;
                        const lng = e.latlng.lng;

                        selectedCoords = { lat, lng };

                        if (manualMarker) {
                            manualMarker.setLatLng(e.latlng);
                        } else {
                            manualMarker = window.L.marker(e.latlng).addTo(manualMap);
                        }

                        $('#ppv-onb-coord-display').html(`<strong>${lat.toFixed(4)}, ${lng.toFixed(4)}</strong>`);
                    });

                    setTimeout(() => manualMap.invalidateSize(), 100);
                }
            }, 100);

            // Close button
            $('#ppv-onb-map-close, #ppv-onb-map-cancel').on('click', () => {
                $('#ppv-onb-map-modal').remove();
            });

            // Confirm button
            $('#ppv-onb-map-confirm').on('click', () => {
                const L = window.ppv_lang || {};
                if (!selectedCoords) {
                    this.showToast(L.map_click_required || 'Bitte klicken Sie auf die Karte!', 'error');
                    return;
                }

                content.find('[name="latitude"]').val(selectedCoords.lat.toFixed(4));
                content.find('[name="longitude"]').val(selectedCoords.lng.toFixed(4));

                // Update main map if exists
                if (this.onbMap) {
                    const latlng = [selectedCoords.lat, selectedCoords.lng];
                    this.onbMap.setView(latlng, 16);

                    if (this.onbMarker) {
                        this.onbMarker.setLatLng(latlng);
                    } else {
                        this.onbMarker = window.L.marker(latlng).addTo(this.onbMap);
                    }
                }

                content.find('.ppv-map-instructions').addClass('success').html(
                    '<span class="ppv-map-icon">✅</span> ' + (L.onb_map_selected || 'Standort ausgewählt!')
                );

                this.showToast(L.coordinates_set || '✅ Koordinaten gesetzt!', 'success');
                $('#ppv-onb-map-modal').remove();
            });
        }

        /** ============================================================
         *  🕐 OPENING HOURS HELPERS
         * ============================================================ */
        renderOpeningHoursInputs() {
            const days = [
                { key: 'mo', label: window.ppv_lang?.monday || 'Montag' },
                { key: 'di', label: window.ppv_lang?.tuesday || 'Dienstag' },
                { key: 'mi', label: window.ppv_lang?.wednesday || 'Mittwoch' },
                { key: 'do', label: window.ppv_lang?.thursday || 'Donnerstag' },
                { key: 'fr', label: window.ppv_lang?.friday || 'Freitag' },
                { key: 'sa', label: window.ppv_lang?.saturday || 'Samstag' },
                { key: 'so', label: window.ppv_lang?.sunday || 'Sonntag' }
            ];

            return days.map(day => `
                <div class="ppv-hour-row" data-day="${day.key}">
                    <span class="ppv-day-label">${day.label}</span>
                    <div class="ppv-hour-inputs">
                        <input type="time" name="hours_${day.key}_von" value="09:00">
                        <span class="ppv-hour-sep">–</span>
                        <input type="time" name="hours_${day.key}_bis" value="17:00">
                    </div>
                    <label class="ppv-closed-checkbox">
                        <input type="checkbox" name="hours_${day.key}_closed">
                        <span>${window.ppv_lang?.closed || 'Geschlossen'}</span>
                    </label>
                </div>
            `).join('');
        }

        applyQuickHours(content, preset) {
            const [open, close] = preset.split('-');
            const openTime = open.padStart(2, '0') + ':00';
            const closeTime = close.padStart(2, '0') + ':00';

            ['mo', 'di', 'mi', 'do', 'fr'].forEach(day => {
                content.find(`[name="hours_${day}_von"]`).val(openTime);
                content.find(`[name="hours_${day}_bis"]`).val(closeTime);
                content.find(`[name="hours_${day}_closed"]`).prop('checked', false);
            });

            // Weekend closed by default for office hours
            if (preset === '9-17') {
                ['sa', 'so'].forEach(day => {
                    content.find(`[name="hours_${day}_closed"]`).prop('checked', true);
                });
            } else {
                ['sa', 'so'].forEach(day => {
                    content.find(`[name="hours_${day}_von"]`).val(openTime);
                    content.find(`[name="hours_${day}_bis"]`).val(closeTime);
                    content.find(`[name="hours_${day}_closed"]`).prop('checked', false);
                });
            }

            this.showToast(window.ppv_lang?.onb_hours_applied || '✅ Öffnungszeiten eingestellt!', 'success');
        }

        collectOpeningHours(content) {
            const days = ['mo', 'di', 'mi', 'do', 'fr', 'sa', 'so'];
            const hours = {};

            days.forEach(day => {
                hours[day] = {
                    von: content.find(`[name="hours_${day}_von"]`).val(),
                    bis: content.find(`[name="hours_${day}_bis"]`).val(),
                    closed: content.find(`[name="hours_${day}_closed"]`).is(':checked') ? 1 : 0
                };
            });

            return hours;
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

                <h3>${L.onb_reward_step_title || '2️⃣ Erste Prämie erstellen'}</h3>

                <form class="ppv-wizard-form" id="ppv-reward-form">
                    <div class="ppv-form-group">
                        <label>${L.onb_reward_name || 'Prämienname'} *</label>
                        <input type="text" name="title" placeholder="${L.onb_reward_name_placeholder || 'z.B. Gratis Kaffee'}">
                        <small style="color: #999;">${L.onb_reward_name_helper || '📝 Der Prämienname, den Kunden sehen'}</small>
                    </div>

                    <div class="ppv-form-group">
                        <label>${L.onb_reward_points || 'Erforderliche Punkte'} *</label>
                        <input type="number" name="required_points" placeholder="100" min="1" value="100">
                        <small style="color: #999;">${L.onb_reward_points_helper || '🎯 Wie viele Punkte der Kunde für diese Prämie braucht'}</small>
                    </div>

                    <div class="ppv-form-group">
                        <label>${L.onb_reward_description || 'Beschreibung (optional)'}</label>
                        <textarea name="description" rows="3" placeholder="${L.onb_reward_description_placeholder || 'z.B. Ein gratis Espresso oder Cappuccino.'}"></textarea>
                        <small style="color: #999;">${L.onb_reward_description_helper || '💬 Weitere Details zur Prämie (optional)'}</small>
                    </div>

                    <div class="ppv-form-group">
                        <label>${L.onb_reward_type || 'Prämienart'} *</label>
                        <select name="action_type" id="onboarding-action-type">
                            <option value="discount_percent">${L.onb_reward_type_percent || 'Rabatt (%)'}</option>
                            <option value="discount_fixed">${L.onb_reward_type_fixed || 'Fixrabatt'}</option>
                            <option value="free_product" selected>${L.onb_reward_type_free || 'Gratis-Produkt'}</option>
                        </select>
                        <small style="color: #999;">${L.onb_reward_type_helper || '🎁 Welche Art von Prämie der Kunde erhält'}</small>
                    </div>

                    <div class="ppv-form-group" id="onboarding-action-value-wrapper">
                        <label>${L.onb_reward_value || 'Wert'} *</label>
                        <input type="text" name="action_value" placeholder="z.B. 10" value="0">
                        <small style="color: #999;">${L.onb_reward_value_helper || '💶 Rabattwert (z.B. 10 = 10% oder 5 = 5 EUR)'}</small>
                    </div>

                    <!-- GRATIS TERMÉK NEVE (csak FREE_PRODUCT típusnál!) -->
                    <div class="ppv-form-group" id="onboarding-free-product-name-wrapper" style="display: none;">
                        <label>${L.onb_reward_free_product || '🎁 Produktname'}</label>
                        <input type="text" name="free_product" id="onboarding-free-product-name" placeholder="${L.onb_reward_free_product_placeholder || 'z.B. Kaffee + Kuchen'}">
                        <small style="color: #999;">${L.onb_reward_free_product_helper || '🎁 Name des Gratis-Produkts (z.B. Kaffee + Kuchen)'}</small>
                    </div>

                    <!-- GRATIS TERMÉK ÉRTÉKE -->
                    <div class="ppv-form-group" id="onboarding-free-product-value-wrapper" style="display: none;">
                        <label style="color: #ff9800;">${L.onb_reward_free_product_value || '💰 Produktwert'} <span style="color: #ff0000;">*</span></label>
                        <input type="number" name="free_product_value" id="onboarding-free-product-value" value="0" min="0.01" step="0.01" placeholder="0.00" style="border-color: #ff9800;">
                        <small style="color: #ff9800;">${L.onb_reward_free_product_value_helper || '💰 Der reguläre Preis des Produkts'}</small>
                    </div>

                    <div class="ppv-form-group">
                        <label>${L.onb_reward_points_given || 'Punkte bei Einlösung'} *</label>
                        <input type="number" name="points_given" placeholder="5" min="0" value="0">
                        <small style="color: #999;">${L.onb_reward_points_given_helper || '⭐ Diese Punkte erhält der Kunde bei Einlösung'}</small>
                    </div>

                    <div class="ppv-wizard-tip">
                        ${L.onb_reward_tip || '💡 Später können Sie Bilder und weitere Prämien hinzufügen!'}
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
                url: this.config.ajax_url,
                method: 'POST',
                data: {
                    action: 'ppv_onboarding_complete_step',
                    step: step,
                    value: JSON.stringify(data)
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.progress = response.data.progress;

                        // ✅ Csak akkor celebration ha MIND A 3 LÉPÉS KÉSZ (100%)
                        if (this.progress.is_complete) {
                            this.celebrationShown = true;
                            this.closeModal(modal);
                            setTimeout(() => this.showCelebrationModal(), 300);
                        } else if (step === 'reward') {
                            // Reward kész, de device még nincs - bezárjuk és emlékeztetjük
                            this.closeModal(modal);
                            this.showToast(L.onb_need_device || '✅ Prémium mentve! Még egy lépés: regisztrálj egy eszközt.', 'info');
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

                        <h2>${L.onb_celebration_title || 'Herzlichen Glückwunsch!'}</h2>

                        <p>${L.onb_celebration_subtitle || 'PunktePass ist einsatzbereit! 🚀'}</p>

                        <p>${L.onb_celebration_message || 'Gäste können jetzt Punkte sammeln und Prämien einlösen!'}</p>

                        <div class="ppv-celebration-tip">
                            ${L.onb_celebration_tip || '💡 <strong>Tipp:</strong> Erstellen Sie eine Kampagne, damit Ihre Gäste schneller Punkte sammeln!'}
                        </div>

                        <button class="ppv-btn ppv-btn-primary ppv-btn-large" data-action="close">${L.onb_btn_done || '✅ Fertig'}</button>

                        <div class="ppv-auto-close">${L.onb_celebration_autoclose || 'Schließt in 5 Sek.'}</div>
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
                const autoCloseText = L.onb_celebration_autoclose || 'Schließt in 5 Sek.';
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

            // ✅ Ha dismissed, ne mutassuk
            if (this.config.dismissed) {
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
                        ${this.renderProgressStep('profile_lite', L.onb_progress_step_profile || 'Profildaten ausgefüllt', this.progress.steps.profile_lite)}
                        ${this.renderProgressStep('reward', L.onb_progress_step_reward || 'Erste Prämie', this.progress.steps.reward)}
                        ${this.renderProgressStep('device', L.onb_progress_step_device || 'Gerät registriert', this.progress.steps.device)}
                    </div>

                    <div class="ppv-progress-actions">
                        <button class="ppv-btn ppv-btn-secondary ppv-btn-sm" data-action="later">${L.onb_btn_later || '⏭️ Später'}</button>
                        <button class="ppv-btn ppv-btn-primary ppv-btn-sm" data-action="continue">${L.onb_btn_continue || '🚀 Fortfahren'}</button>
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
                this.postponeOnboarding(); // ⏰ 8 órás késleltetés
                card.removeClass('show');
                setTimeout(() => card.remove(), 300);
            });

            // X gomb - dismiss
            card.on('click', '[data-action="dismiss"]', () => {
                if (confirm(L.onb_confirm_dismiss || 'Wirklich schließen? Kann später in den Einstellungen wieder aktiviert werden.')) {
                    // ✅ Set dismissed flag and wait for backend confirmation
                    this.dismissOnboarding('permanent', () => {
                        this.config.dismissed = true;
                        this.hideAllOnboarding();
                    });
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
                    ${!completed ? `<button class="ppv-step-action">➡️ ${L.onb_btn_next || 'Einrichten'}</button>` : ''}
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
            } else if (step === 'device') {
                // Eszköz regisztrálás - átirányítás az eszköz kezelés tabra
                const qrCenterUrl = '/qr-center/?tab=devices';
                if (window.location.pathname.includes('qr-center')) {
                    // Ha már QR centeren vagyunk, váltás a tab-ra
                    const devicesTab = document.querySelector('[data-tab="devices"]');
                    if (devicesTab) {
                        devicesTab.click();
                    } else {
                        window.location.href = qrCenterUrl;
                    }
                } else {
                    window.location.href = qrCenterUrl;
                }
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
                if (confirm(L.onb_confirm_hide || 'Wirklich ausblenden? Kann später in den Einstellungen wieder aktiviert werden.')) {
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
            // ✅ Skip refresh if dismissed
            if (this.config.dismissed) {
                return;
            }

            $.post(this.config.ajax_url, { action: 'ppv_onboarding_progress' }, (response) => {
                if (response.success && response.data) {
                    this.progress = response.data.progress;
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
         *  ⏰ POSTPONE ONBOARDING (8 hours)
         * ============================================================ */
        postponeOnboarding() {
            $.post(this.config.ajax_url, { action: 'ppv_onboarding_postpone' }, (response) => {
                if (response.success) {
                    this.showToast(L.onb_postponed || '⏰ 8 óra múlva emlékeztetünk!', 'info');
                }
            }).fail(() => {
                console.error('❌ Failed to postpone onboarding');
            });
        }

        /** ============================================================
         *  ❌ DISMISS
         * ============================================================ */
        dismissOnboarding(type, callback) {
            $.post(this.config.ajax_url, { action: 'ppv_onboarding_dismiss', type: type }, (response) => {
                if (callback) callback();
            }).fail(() => {
                console.error('❌ Failed to dismiss onboarding');
                // Still call callback even on error to hide UI
                if (callback) callback();
            });
        }

        /** ============================================================
         *  🚫 HIDE ALL
         * ============================================================ */
        hideAllOnboarding() {
            // ✅ Clear progress polling interval to prevent memory leak
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
                this.progressInterval = null;
            }

            // ✅ Unsubscribe from Ably via shared manager
            if (this.ablySubscriberId && window.PPV_ABLY_MANAGER) {
                window.PPV_ABLY_MANAGER.unsubscribe(this.ablySubscriberId);
                this.ablySubscriberId = null;
            }

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

    // Initialize and store globally for cleanup
    window.PPV_ONBOARDING_INSTANCE = new PPVOnboarding();

    // ✅ FIX: Cleanup on navigation (iOS Safari fix)
    document.addEventListener('turbo:before-visit', function() {
        if (window.PPV_ONBOARDING_INSTANCE) {
            // Clear polling interval
            if (window.PPV_ONBOARDING_INSTANCE.progressInterval) {
                clearInterval(window.PPV_ONBOARDING_INSTANCE.progressInterval);
                window.PPV_ONBOARDING_INSTANCE.progressInterval = null;
            }
            // Unsubscribe from Ably (don't close - shared manager handles connection)
            if (window.PPV_ONBOARDING_INSTANCE.ablySubscriberId && window.PPV_ABLY_MANAGER) {
                window.PPV_ABLY_MANAGER.unsubscribe(window.PPV_ONBOARDING_INSTANCE.ablySubscriberId);
                window.PPV_ONBOARDING_INSTANCE.ablySubscriberId = null;
            }
        }
    });

})(jQuery);
