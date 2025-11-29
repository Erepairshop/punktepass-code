/**
 * PunktePass Profile Lite - Geocoding Module
 * Contains: Address geocoding, map preview, interactive map modal
 * Depends on: pp-profile-core.js
 */
(function() {
    'use strict';

    // Guard against multiple loads
    if (window.PPV_PROFILE_GEOCODING_LOADED) return;
    window.PPV_PROFILE_GEOCODING_LOADED = true;

    const { STATE, t, showAlert } = window.PPV_PROFILE || {};

    // Global variables for interactive map
    let interactiveMap = null;
    let interactiveMapMarker = null;
    let selectedCoords = null;

    // ============================================================
    // MAP PREVIEW (OpenStreetMap embed)
    // ============================================================
    function showMapPreview(lat, lon) {
        const mapDiv = document.getElementById('ppv-location-map');
        if (!mapDiv) return;

        mapDiv.innerHTML = `
            <div style="position: relative; width: 100%; height: 100%; border-radius: 8px; overflow: hidden; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                <iframe style="width: 100%; height: 100%; border: none; border-radius: 8px;"
                    src="https://www.openstreetmap.org/export/embed.html?bbox=${lon - 0.01},${lat - 0.01},${lon + 0.01},${lat + 0.01}&layer=mapnik&marker=${lat},${lon}">
                </iframe>
            </div>
        `;
    }

    // ============================================================
    // INTERACTIVE MAP MODAL (Google Maps)
    // ============================================================
    function openInteractiveMap(defaultLat, defaultLng) {
        const L = window.ppv_profile?.strings || {};
        const mapTitle = L.map_modal_title || 'Mark your location on the map';
        const mapClick = L.map_modal_click || 'Click on the map';
        const mapCancel = L.map_modal_cancel || 'Cancel';
        const mapConfirm = L.map_modal_confirm || 'Confirm';

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
                    <div style="
                        padding: 1.5rem;
                        border-bottom: 1px solid #e5e7eb;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    ">
                        <h2 style="margin: 0; font-size: 1.3rem;">🗺️ ${mapTitle}</h2>
                        <button onclick="window.PPV_PROFILE.closeInteractiveMap()" style="
                            background: none;
                            border: none;
                            font-size: 1.5rem;
                            cursor: pointer;
                            color: #666;
                        ">✕</button>
                    </div>

                    <div id="ppv-interactive-map" style="
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
                            📍 <strong id="ppv-coord-display">${mapClick}</strong>
                        </p>
                        <div style="display: flex; gap: 0.75rem;">
                            <button onclick="window.PPV_PROFILE.closeInteractiveMap()" style="
                                padding: 0.75rem 1.5rem;
                                border: 1px solid #ddd;
                                background: #f0f0f0;
                                border-radius: 6px;
                                cursor: pointer;
                                font-weight: 600;
                            ">${mapCancel}</button>
                            <button onclick="window.PPV_PROFILE.confirmInteractiveMap()" style="
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

        // Initialize Google Maps
        setTimeout(() => {
            if (typeof google === 'undefined' || !google.maps) {
                console.warn('[Profile-Geocoding] Google Maps not loaded');
                return;
            }

            interactiveMap = new google.maps.Map(
                document.getElementById('ppv-interactive-map'),
                {
                    zoom: 15,
                    center: { lat: defaultLat || 47.5, lng: defaultLng || 22.5 },
                    mapTypeControl: true,
                    fullscreenControl: true,
                    streetViewControl: false
                }
            );

            interactiveMap.addListener('click', (e) => {
                const lat = e.latLng.lat();
                const lng = e.latLng.lng();

                if (interactiveMapMarker) {
                    interactiveMapMarker.setMap(null);
                }

                interactiveMapMarker = new google.maps.Marker({
                    position: { lat, lng },
                    map: interactiveMap,
                    title: `${lat.toFixed(4)}, ${lng.toFixed(4)}`
                });

                document.getElementById('ppv-coord-display').innerHTML =
                    `<strong>${lat.toFixed(4)}, ${lng.toFixed(4)}</strong>`;

                selectedCoords = { lat, lng };
            });
        }, 100);
    }

    function closeInteractiveMap() {
        const modal = document.getElementById('ppv-map-modal');
        if (modal) modal.remove();
        interactiveMap = null;
        interactiveMapMarker = null;
        selectedCoords = null;
    }

    function confirmInteractiveMap() {
        const L = window.ppv_profile?.strings || {};

        if (!selectedCoords) {
            alert(L.map_click_required || 'Please click on the map!');
            return;
        }

        const { lat, lng } = selectedCoords;

        document.getElementById('store_latitude').value = lat.toFixed(4);
        document.getElementById('store_longitude').value = lng.toFixed(4);

        showMapPreview(lat, lng);
        closeInteractiveMap();

        alert(`✅ ${L.coordinates_set || 'Coordinates set!'}\n\nLat: ${lat.toFixed(4)}\nLng: ${lng.toFixed(4)}`);
    }

    // ============================================================
    // GEOCODING MANAGER CLASS
    // ============================================================
    class GeocodingManager {
        constructor() {
            this.geocodeBtn = null;
            this.manualBtn = null;
        }

        /**
         * Initialize geocoding features
         */
        init() {
            this.bindGeocodeButton();
            this.addManualMapButton();
        }

        /**
         * Bind geocode button click event
         */
        bindGeocodeButton() {
            this.geocodeBtn = document.getElementById('ppv-geocode-btn');
            if (!this.geocodeBtn || this.geocodeBtn.dataset.geocodeInitialized) return;
            this.geocodeBtn.dataset.geocodeInitialized = 'true';

            this.geocodeBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                await this.geocodeAddress();
            });
        }

        /**
         * Geocode address via PHP API
         */
        async geocodeAddress() {
            const L = window.ppv_profile?.strings || {};

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

            if (!address || !city || !country) {
                alert(L.geocode_missing_fields || 'Please enter street, city and country!');
                return;
            }

            this.geocodeBtn.disabled = true;
            this.geocodeBtn.textContent = '⏳ ' + (L.searching || 'Searching...');

            try {
                const formData = new FormData();
                formData.append('action', 'ppv_geocode_address');
                formData.append('ppv_nonce', STATE.nonce || window.ppv_profile?.nonce);
                formData.append('address', address);
                formData.append('plz', plz);
                formData.append('city', city);
                formData.append('country', country);

                const response = await fetch(STATE.ajaxUrl || window.ppv_profile?.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                });

                const responseText = await response.text();

                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    alert('❌ ' + (L.php_error || 'PHP error!') + '\n\n' + responseText);
                    this.resetGeocodeButton();
                    return;
                }

                if (!data.success) {
                    alert('❌ ' + (data.data?.msg || L.unknown_error || 'Unknown error'));
                    this.resetGeocodeButton();
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

                if (open_manual_map) {
                    alert(`⚠️ ${L.street_not_found || 'Street not found!'}\n\n${L.using_city_coords || 'Using city coordinates:'} ${display_name}\n\n${L.use_manual_map || 'Please use the manual map to set the exact location!'}`);
                    setTimeout(() => {
                        openInteractiveMap(lat, lon);
                    }, 500);
                } else {
                    alert(`✅ ${L.coordinates_found || 'Coordinates found!'}\n\n📍 ${display_name}\n\nLat: ${lat}\nLon: ${lon}`);
                }

            } catch (error) {
                console.error('[Profile-Geocoding] Error:', error);
                alert('❌ ' + (L.geocode_error || 'Error searching coordinates!') + '\n\n' + error.message);
            }

            this.resetGeocodeButton();
        }

        /**
         * Reset geocode button state
         */
        resetGeocodeButton() {
            if (this.geocodeBtn) {
                const L = window.ppv_profile?.strings || {};
                this.geocodeBtn.disabled = false;
                this.geocodeBtn.textContent = '🗺️ ' + (L.geocode_button || 'Search coordinates (by address)');
            }
        }

        /**
         * Add manual map button
         */
        addManualMapButton() {
            const geocodeBtn = document.getElementById('ppv-geocode-btn');
            if (!geocodeBtn || geocodeBtn.dataset.manualBtnAdded) return;
            geocodeBtn.dataset.manualBtnAdded = 'true';

            const L = window.ppv_profile?.strings || {};
            const manualBtn = document.createElement('button');
            manualBtn.type = 'button';
            manualBtn.className = 'ppv-manual-map-btn';
            manualBtn.textContent = '🗺️ ' + (L.manual_map_button || 'Set manually on map');
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
            manualBtn.onmouseover = () => manualBtn.style.background = '#e0e0e0';
            manualBtn.onmouseout = () => manualBtn.style.background = '#f0f0f0';
            manualBtn.onclick = (e) => {
                e.preventDefault();
                const lat = parseFloat(document.getElementById('store_latitude')?.value) || 47.5;
                const lng = parseFloat(document.getElementById('store_longitude')?.value) || 22.5;
                openInteractiveMap(lat, lng);
            };

            geocodeBtn.parentElement?.insertAdjacentElement('afterend', manualBtn);
            this.manualBtn = manualBtn;
        }
    }

    // ============================================================
    // EXPORT TO GLOBAL
    // ============================================================
    window.PPV_PROFILE = window.PPV_PROFILE || {};
    window.PPV_PROFILE.GeocodingManager = GeocodingManager;
    window.PPV_PROFILE.showMapPreview = showMapPreview;
    window.PPV_PROFILE.openInteractiveMap = openInteractiveMap;
    window.PPV_PROFILE.closeInteractiveMap = closeInteractiveMap;
    window.PPV_PROFILE.confirmInteractiveMap = confirmInteractiveMap;

    // Legacy global exports (for backwards compatibility)
    window.showMapPreview = showMapPreview;
    window.closeInteractiveMap = closeInteractiveMap;
    window.confirmInteractiveMap = confirmInteractiveMap;

    console.log('[Profile-Geocoding] Module loaded v3.0');

})();
