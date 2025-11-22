/**
 * PunktePass – VIP Settings Management
 * Version: 1.0
 */

(function() {
    'use strict';

    function initVIPSettings() {
        const wrapper = document.getElementById('ppv-vip-settings');
        if (!wrapper) return;
        if (wrapper.dataset.initialized === 'true') return;
        wrapper.dataset.initialized = 'true';

        console.log('✅ [VIP] VIP Settings JS initialized');

        const enabledCheckbox = document.getElementById('ppv-vip-enabled');
        const levelsContainer = document.getElementById('ppv-vip-levels');
        const silverInput = document.getElementById('ppv-silver-bonus');
        const goldInput = document.getElementById('ppv-gold-bonus');
        const platinumInput = document.getElementById('ppv-platinum-bonus');
        const saveBtn = document.getElementById('ppv-vip-save');
        const statusEl = document.getElementById('ppv-vip-status');

        const T = window.ppv_vip_translations || {};

        // Load current settings
        loadSettings();

        // Toggle levels visibility
        if (enabledCheckbox) {
            enabledCheckbox.addEventListener('change', function() {
                levelsContainer.style.opacity = this.checked ? '1' : '0.5';
                levelsContainer.style.pointerEvents = this.checked ? 'auto' : 'none';
            });
        }

        // Update preview on input change
        [silverInput, goldInput, platinumInput].forEach(input => {
            if (input) {
                input.addEventListener('input', updatePreview);
            }
        });

        // Save button
        if (saveBtn) {
            saveBtn.addEventListener('click', saveSettings);
        }

        function loadSettings() {
            if (!window.ppv_vip || !window.ppv_vip.base) {
                console.error('❌ [VIP] ppv_vip config not found');
                return;
            }

            fetch(window.ppv_vip.base + 'vip/settings', {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': window.ppv_vip.nonce
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    const settings = data.data;

                    if (enabledCheckbox) {
                        enabledCheckbox.checked = settings.vip_enabled;
                        levelsContainer.style.opacity = settings.vip_enabled ? '1' : '0.5';
                        levelsContainer.style.pointerEvents = settings.vip_enabled ? 'auto' : 'none';
                    }

                    if (silverInput) silverInput.value = settings.vip_silver_bonus;
                    if (goldInput) goldInput.value = settings.vip_gold_bonus;
                    if (platinumInput) platinumInput.value = settings.vip_platinum_bonus;

                    updatePreview();
                    console.log('✅ [VIP] Settings loaded:', settings);
                }
            })
            .catch(err => {
                console.error('❌ [VIP] Load error:', err);
            });
        }

        function updatePreview() {
            const silver = parseFloat(silverInput?.value || 5);
            const gold = parseFloat(goldInput?.value || 10);
            const platinum = parseFloat(platinumInput?.value || 20);

            const previewSilver = document.getElementById('preview-silver');
            const previewGold = document.getElementById('preview-gold');
            const previewPlatinum = document.getElementById('preview-platinum');

            if (previewSilver) previewSilver.textContent = (1 + silver / 100).toFixed(2);
            if (previewGold) previewGold.textContent = (1 + gold / 100).toFixed(2);
            if (previewPlatinum) previewPlatinum.textContent = (1 + platinum / 100).toFixed(2);
        }

        function saveSettings() {
            if (!window.ppv_vip || !window.ppv_vip.base) {
                console.error('❌ [VIP] ppv_vip config not found');
                return;
            }

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> ...';

            const formData = new FormData();
            formData.append('vip_enabled', enabledCheckbox?.checked ? '1' : '0');
            formData.append('vip_silver_bonus', silverInput?.value || '5');
            formData.append('vip_gold_bonus', goldInput?.value || '10');
            formData.append('vip_platinum_bonus', platinumInput?.value || '20');

            fetch(window.ppv_vip.base + 'vip/save', {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': window.ppv_vip.nonce
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="ri-save-line"></i> ' + (T.save_btn || 'Save');

                if (data.success) {
                    statusEl.innerHTML = '<span class="ppv-success">' + (T.saved || 'Saved!') + '</span>';
                    setTimeout(() => { statusEl.innerHTML = ''; }, 3000);
                    console.log('✅ [VIP] Settings saved');
                } else {
                    statusEl.innerHTML = '<span class="ppv-error">' + (T.error || 'Error') + '</span>';
                    console.error('❌ [VIP] Save failed:', data.msg);
                }
            })
            .catch(err => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="ri-save-line"></i> ' + (T.save_btn || 'Save');
                statusEl.innerHTML = '<span class="ppv-error">' + (T.error || 'Error') + '</span>';
                console.error('❌ [VIP] Save error:', err);
            });
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVIPSettings);
    } else {
        initVIPSettings();
    }

    // Turbo support
    document.addEventListener('turbo:load', function() {
        const wrapper = document.getElementById('ppv-vip-settings');
        if (wrapper) wrapper.dataset.initialized = 'false';
        initVIPSettings();
    });
})();
