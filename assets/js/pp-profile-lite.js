/**
 * PunktePass – Admin Profil Frontend (v2.0 i18n - Fixed)
 * ✅ DE, HU, RO Language Support
 * ✅ Dynamic String Translation
 * ✅ Auto-save Draft
 * ✅ Real-time Validation
 * ✅ Nonce Fix
 * ✅ Geocoding FIX
 */

(function() {
    'use strict';

    class PPVProfileForm {
        constructor() {
            this.$form = document.getElementById('ppv-profile-form');
            this.strings = window.ppv_profile?.strings || {};
            this.currentLang = window.ppv_profile?.lang || 'de';
            this.nonce = window.ppv_profile?.nonce || '';
            this.ajaxUrl = window.ppv_profile?.ajaxUrl || '';
            
            this.hasChanges = false;
            this.autoSaveTimer = null;
            
            this.init();
        }

        init() {
            if (!this.$form) {
                console.warn('⚠️ PPV Profile Form not found');
                return;
            }

            this.bindTabs();
            this.bindFormInputs();
            this.bindFormSubmit();
            this.bindGalleryDelete();

            this.updateUI();
            
            console.log('✅ PPV Profile Form initialized');
            console.log('🔐 Nonce:', this.nonce);
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
            if (!confirm('Törlöd ezt a képet?')) return;

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
                    this.showAlert('Kép törölve!', 'success');
                    location.reload();
                } else {
                    this.showAlert(data.data?.msg || 'Hiba a törléskor', 'error');
                }
            })
            .catch(err => {
                console.error('Delete error:', err);
                this.showAlert('Hiba a törléskor', 'error');
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
        }

        // ==================== FORM INPUTS ====================
        bindFormInputs() {
            this.$form.addEventListener('change', () => {
                this.hasChanges = true;
                this.autoSave();
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

        // ==================== AUTO-SAVE ====================
        autoSave() {
            clearTimeout(this.autoSaveTimer);

            this.autoSaveTimer = setTimeout(() => {
                const formData = new FormData(this.$form);
                
                console.log('💾 Auto-saving...');
                
                fetch(`${this.ajaxUrl}?action=ppv_auto_save_profile`, {
                    method: 'POST',
                    body: formData
                })
                .then(r => {
                    console.log('📨 Response status:', r.status, r.statusText);
                    return r.json();
                })
                .then(data => {
                    if (data.success) {
                        console.log('✅ Auto-saved:', data.data);
                        this.updateStatus(this.t('saved'));
                    } else {
                        console.error('❌ Auto-save failed:', data.data?.msg);
                    }
                })
                .catch(err => {
                    console.error('❌ Auto-save error:', err);
                });
            }, 2000);
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

            console.log('💾 Saving form...');

            fetch(`${this.ajaxUrl}?action=ppv_save_profile`, {
                method: 'POST',
                body: formData
            })
            .then(r => {
                console.log('📨 Response status:', r.status, r.statusText);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    this.showAlert(this.t('profile_saved_success'), 'success');
                    this.updateStatus(this.t('saved'));
                    this.hasChanges = false;
                    
                    document.getElementById('ppv-last-updated').textContent = 
                        `${this.t('last_updated')}: ${new Date().toLocaleString()}`;
                    
                    console.log('✅ Profile saved:', data.data);
                } else {
                    this.showAlert(data.data?.msg || this.t('profile_save_error'), 'error');
                    this.updateStatus(this.t('error'));
                    console.error('❌ Save failed:', data.data?.msg);
                }
            })
            .catch(err => {
                console.error('❌ Save error:', err);
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

    // ==================== INIT ====================
    document.addEventListener('DOMContentLoaded', () => {
        window.ppvProfileForm = new PPVProfileForm();
    });
    
})();

// ==================== EXPORT ====================
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PPVProfileForm;
}

// ============================================================
// 🗺️ GEOCODING - Cím → Lat/Lng (PHP API) - FIXED
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
  const geocodeBtn = document.getElementById('ppv-geocode-btn');
  if (!geocodeBtn) return;

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

    console.log('📍 Geocode inputs:', { address, plz, city, country });

    // ✅ ELLENŐRZÉS
    if (!address || !city || !country) {
      alert('Kérlek, add meg az utcát, a várost ÉS az országot!');
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

      console.log('📤 Sending to server:', { address, plz, city, country });

      const response = await fetch(ppv_profile.ajaxUrl, {
        method: 'POST',
        body: formData,
      });

      const responseText = await response.text();
      console.log('📡 Raw response:', responseText);

      let data;
      try {
        data = JSON.parse(responseText);
      } catch (e) {
        console.error('❌ JSON parse error:', e);
        console.error('Response body:', responseText);
        alert('❌ PHP hiba történt!\n\n' + responseText);
        geocodeBtn.disabled = false;
        geocodeBtn.textContent = '🗺️ Koordináták keresése (Cím alapján)';
        return;
      }

      console.log('📦 Parsed data:', data);

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

console.log(`✅ Koordináták: ${lat}, ${lon}`);

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
      console.error('❌ Geocoding error:', error);
      console.error('Error message:', error.message);
      alert('❌ Hiba a koordináták keresésekor!\n\n' + error.message);
    }

    geocodeBtn.disabled = false;
    geocodeBtn.textContent = '🗺️ Koordináták keresése (Cím alapján)';
  });

  function showMapPreview(lat, lon) {
    const mapDiv = document.getElementById('ppv-location-map');
    if (!mapDiv) return;

    mapDiv.innerHTML = `
      <div style="position: relative; width: 100%; height: 100%; border-radius: 8px; overflow: hidden; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
        <iframe style="width: 100%; height: 100%; border: none; border-radius: 8px;" src="https://www.openstreetmap.org/export/embed.html?bbox=${lon - 0.01},${lat - 0.01},${lon + 0.01},${lat + 0.01}&layer=mapnik&marker=${lat},${lon}"></iframe>
      </div>
    `;
  }
  
  // ============================================================
// 🗺️ INTERACTIVE MAP MODAL - Manual Geocoding
// ============================================================

let interactiveMap = null;
let interactiveMapMarker = null;

function openInteractiveMap(defaultLat, defaultLng) {
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
          <h2 style="margin: 0; font-size: 1.3rem;">🗺️ Jelöld meg a helyet a térképen</h2>
          <button onclick="closeInteractiveMap()" style="
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
            📍 <strong id="ppv-coord-display">Kattints a térképre</strong>
          </p>
          <div style="display: flex; gap: 0.75rem;">
            <button onclick="closeInteractiveMap()" style="
              padding: 0.75rem 1.5rem;
              border: 1px solid #ddd;
              background: #f0f0f0;
              border-radius: 6px;
              cursor: pointer;
              font-weight: 600;
            ">Mégse</button>
            <button onclick="confirmInteractiveMap()" style="
              padding: 0.75rem 1.5rem;
              border: none;
              background: linear-gradient(135deg, #6366f1, #4f46e5);
              color: white;
              border-radius: 6px;
              cursor: pointer;
              font-weight: 600;
            ">✅ Elfogadom</button>
          </div>
        </div>
      </div>
    </div>
  `;

  document.body.insertAdjacentHTML('beforeend', modalHTML);

  // Initialize map
  setTimeout(() => {
    interactiveMap = new google.maps.Map(
      document.getElementById('ppv-interactive-map'),
      {
        zoom: 12,
        center: { lat: defaultLat || 47.5, lng: defaultLng || 22.5 },
        mapTypeControl: true,
        fullscreenControl: true,
        streetViewControl: false
      }
    );

    // Click listener
    interactiveMap.addListener('click', (e) => {
      const lat = e.latLng.lat();
      const lng = e.latLng.lng();

      console.log(`📍 Map clicked: ${lat}, ${lng}`);

      // Remove old marker
      if (interactiveMapMarker) {
        interactiveMapMarker.setMap(null);
      }

      // Add new marker
      interactiveMapMarker = new google.maps.Marker({
        position: { lat, lng },
        map: interactiveMap,
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

function closeInteractiveMap() {
  const modal = document.getElementById('ppv-map-modal');
  if (modal) modal.remove();
  interactiveMap = null;
  interactiveMapMarker = null;
}

function confirmInteractiveMap() {
  if (!window.ppvSelectedCoords) {
    alert('Kérlek, kattints a térképre!');
    return;
  }

  const { lat, lng } = window.ppvSelectedCoords;
  
  document.getElementById('store_latitude').value = lat.toFixed(4);
  document.getElementById('store_longitude').value = lng.toFixed(4);

  closeInteractiveMap();
  
  alert(`✅ Koordináták beállítva!\n\nLat: ${lat.toFixed(4)}\nLng: ${lng.toFixed(4)}`);
}

// Geocoding button - add fallback button
document.addEventListener('DOMContentLoaded', () => {
  // Add manual button next to geocode button
  const geocodeBtn = document.getElementById('ppv-geocode-btn');
  if (geocodeBtn) {
    const manualBtn = document.createElement('button');
    manualBtn.type = 'button';
    manualBtn.textContent = '🗺️ Manuálisan a térképen';
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
});
});