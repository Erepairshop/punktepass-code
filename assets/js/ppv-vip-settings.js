/**
 * PunktePass – VIP Settings Management (Extended)
 * Supports 3 bonus types:
 * 1. Fixed point bonus per level
 * 2. Every Xth scan bonus
 * 3. First daily scan bonus
 *
 * Version: 2.1
 */

(function() {
    'use strict';

    function initVIPSettings() {
        const wrapper = document.getElementById('ppv-vip-settings');
        if (!wrapper) return;
        if (wrapper.dataset.initialized === 'true') return;
        wrapper.dataset.initialized = 'true';

        console.log('✅ [VIP] Extended VIP Settings JS initialized');

        const T = window.ppv_vip_translations || {};
        const lang = wrapper.dataset.lang || 'de';

        // ═══════════════════════════════════════════════════════════
        // ELEMENT REFERENCES
        // ═══════════════════════════════════════════════════════════

        // 1. Fixed point bonus
        const fixEnabled = document.getElementById('ppv-fix-enabled');
        const fixSilver = document.getElementById('ppv-fix-silver');
        const fixGold = document.getElementById('ppv-fix-gold');
        const fixPlatinum = document.getElementById('ppv-fix-platinum');
        const fixError = document.getElementById('ppv-fix-error');

        // 2. Streak bonus
        const streakEnabled = document.getElementById('ppv-streak-enabled');
        const streakCount = document.getElementById('ppv-streak-count');
        const streakType = document.getElementById('ppv-streak-type');
        const streakSilver = document.getElementById('ppv-streak-silver');
        const streakGold = document.getElementById('ppv-streak-gold');
        const streakPlatinum = document.getElementById('ppv-streak-platinum');
        const streakError = document.getElementById('ppv-streak-error');
        const streakFixedInputs = document.querySelector('.ppv-streak-fixed-inputs');

        // 3. Daily bonus
        const dailyEnabled = document.getElementById('ppv-daily-enabled');
        const dailySilver = document.getElementById('ppv-daily-silver');
        const dailyGold = document.getElementById('ppv-daily-gold');
        const dailyPlatinum = document.getElementById('ppv-daily-platinum');
        const dailyError = document.getElementById('ppv-daily-error');

        // Preview elements
        const previewLevelButtons = document.querySelectorAll('.ppv-preview-level');
        const previewRowFix = document.getElementById('preview-row-fix');
        const previewRowStreak = document.getElementById('preview-row-streak');
        const previewRowDaily = document.getElementById('preview-row-daily');
        const previewFixValue = document.getElementById('preview-fix-value');
        const previewStreakValue = document.getElementById('preview-streak-value');
        const previewDailyValue = document.getElementById('preview-daily-value');
        const previewTotal = document.getElementById('preview-total');

        // Save button
        const saveBtn = document.getElementById('ppv-vip-save');
        const statusEl = document.getElementById('ppv-vip-status');

        // Current preview level
        let currentPreviewLevel = 'gold';

        // ═══════════════════════════════════════════════════════════
        // LOAD SETTINGS
        // ═══════════════════════════════════════════════════════════

        loadSettings();

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
                    const s = data.data;

                    // 1. Fixed
                    if (fixEnabled) fixEnabled.checked = s.vip_fix_enabled;
                    if (fixSilver) fixSilver.value = s.vip_fix_silver;
                    if (fixGold) fixGold.value = s.vip_fix_gold;
                    if (fixPlatinum) fixPlatinum.value = s.vip_fix_platinum;

                    // 2. Streak
                    if (streakEnabled) streakEnabled.checked = s.vip_streak_enabled;
                    if (streakCount) streakCount.value = s.vip_streak_count;
                    if (streakType) streakType.value = s.vip_streak_type;
                    if (streakSilver) streakSilver.value = s.vip_streak_silver;
                    if (streakGold) streakGold.value = s.vip_streak_gold;
                    if (streakPlatinum) streakPlatinum.value = s.vip_streak_platinum;

                    // 3. Daily
                    if (dailyEnabled) dailyEnabled.checked = s.vip_daily_enabled;
                    if (dailySilver) dailySilver.value = s.vip_daily_silver;
                    if (dailyGold) dailyGold.value = s.vip_daily_gold;
                    if (dailyPlatinum) dailyPlatinum.value = s.vip_daily_platinum;

                    // Update UI state
                    updateCardStates();
                    updateStreakTypeVisibility();
                    updatePreview();

                    console.log('✅ [VIP] Extended settings loaded:', s);
                }
            })
            .catch(err => {
                console.error('❌ [VIP] Load error:', err);
            });
        }

        // ═══════════════════════════════════════════════════════════
        // UI STATE MANAGEMENT
        // ═══════════════════════════════════════════════════════════

        function updateCardStates() {
            // Update card body opacity based on toggle state
            const cards = [
                { toggle: fixEnabled, card: fixEnabled?.closest('.ppv-vip-card') },
                { toggle: streakEnabled, card: streakEnabled?.closest('.ppv-vip-card') },
                { toggle: dailyEnabled, card: dailyEnabled?.closest('.ppv-vip-card') },
            ];

            cards.forEach(({ toggle, card }) => {
                if (toggle && card) {
                    const body = card.querySelector('.ppv-vip-card-body');
                    if (body) {
                        body.style.opacity = toggle.checked ? '1' : '0.5';
                        body.style.pointerEvents = toggle.checked ? 'auto' : 'none';
                    }
                }
            });
        }

        function updateStreakTypeVisibility() {
            if (streakFixedInputs && streakType) {
                const isFixed = streakType.value === 'fixed';
                streakFixedInputs.style.display = isFixed ? 'flex' : 'none';
            }
        }

        // ═══════════════════════════════════════════════════════════
        // VALIDATION
        // ═══════════════════════════════════════════════════════════

        function validateAscending(silver, gold, platinum) {
            const s = parseInt(silver) || 0;
            const g = parseInt(gold) || 0;
            const p = parseInt(platinum) || 0;
            return s <= g && g <= p;
        }

        function validateAll() {
            let isValid = true;
            const validationMsg = T.validation_error || 'Values must be in ascending order: Silver ≤ Gold ≤ Platinum';

            // 1. Fixed
            if (fixEnabled?.checked) {
                const valid = validateAscending(fixSilver?.value, fixGold?.value, fixPlatinum?.value);
                if (fixError) {
                    fixError.textContent = valid ? '' : validationMsg;
                    fixError.style.display = valid ? 'none' : 'block';
                }
                if (!valid) isValid = false;
            } else if (fixError) {
                fixError.style.display = 'none';
            }

            // 2. Streak (only if type=fixed)
            if (streakEnabled?.checked && streakType?.value === 'fixed') {
                const valid = validateAscending(streakSilver?.value, streakGold?.value, streakPlatinum?.value);
                if (streakError) {
                    streakError.textContent = valid ? '' : validationMsg;
                    streakError.style.display = valid ? 'none' : 'block';
                }
                if (!valid) isValid = false;
            } else if (streakError) {
                streakError.style.display = 'none';
            }

            // 3. Daily
            if (dailyEnabled?.checked) {
                const valid = validateAscending(dailySilver?.value, dailyGold?.value, dailyPlatinum?.value);
                if (dailyError) {
                    dailyError.textContent = valid ? '' : validationMsg;
                    dailyError.style.display = valid ? 'none' : 'block';
                }
                if (!valid) isValid = false;
            } else if (dailyError) {
                dailyError.style.display = 'none';
            }

            return isValid;
        }

        // ═══════════════════════════════════════════════════════════
        // LIVE PREVIEW
        // ═══════════════════════════════════════════════════════════

        function updatePreview() {
            const basePoints = 100; // Scenario: 100 point scan
            let total = basePoints;

            // Get values based on current preview level
            const getValue = (silver, gold, platinum) => {
                const values = {
                    silver: parseInt(silver?.value) || 0,
                    gold: parseInt(gold?.value) || 0,
                    platinum: parseInt(platinum?.value) || 0
                };
                return values[currentPreviewLevel] || 0;
            };

            // 1. Fixed bonus
            if (fixEnabled?.checked) {
                const fix = getValue(fixSilver, fixGold, fixPlatinum);
                if (previewFixValue) previewFixValue.textContent = '+' + fix;
                if (previewRowFix) previewRowFix.style.display = 'flex';
                total += fix;
            } else {
                if (previewRowFix) previewRowFix.style.display = 'none';
            }

            // 2. Streak bonus (assume 10th scan in preview scenario)
            if (streakEnabled?.checked) {
                let streakBonus = 0;
                if (streakType?.value === 'fixed') {
                    streakBonus = getValue(streakSilver, streakGold, streakPlatinum);
                } else if (streakType?.value === 'double') {
                    streakBonus = basePoints; // Double means +100% = +basePoints
                } else if (streakType?.value === 'triple') {
                    streakBonus = basePoints * 2; // Triple means +200% = +2*basePoints
                }
                if (previewStreakValue) previewStreakValue.textContent = '+' + streakBonus;
                if (previewRowStreak) previewRowStreak.style.display = 'flex';
                total += streakBonus;
            } else {
                if (previewRowStreak) previewRowStreak.style.display = 'none';
            }

            // 3. Daily bonus (assume first scan of day in preview scenario)
            if (dailyEnabled?.checked) {
                const daily = getValue(dailySilver, dailyGold, dailyPlatinum);
                if (previewDailyValue) previewDailyValue.textContent = '+' + daily;
                if (previewRowDaily) previewRowDaily.style.display = 'flex';
                total += daily;
            } else {
                if (previewRowDaily) previewRowDaily.style.display = 'none';
            }

            // Update total
            if (previewTotal) previewTotal.textContent = total;

            // Validate
            validateAll();
        }

        // ═══════════════════════════════════════════════════════════
        // EVENT LISTENERS
        // ═══════════════════════════════════════════════════════════

        // Toggle state changes
        [fixEnabled, streakEnabled, dailyEnabled].forEach(toggle => {
            if (toggle) {
                toggle.addEventListener('change', () => {
                    updateCardStates();
                    updatePreview();
                });
            }
        });

        // Streak type change
        if (streakType) {
            streakType.addEventListener('change', () => {
                updateStreakTypeVisibility();
                updatePreview();
            });
        }

        // All input changes trigger preview update
        const allInputs = [
            fixSilver, fixGold, fixPlatinum,
            streakCount, streakSilver, streakGold, streakPlatinum,
            dailySilver, dailyGold, dailyPlatinum
        ];
        allInputs.forEach(input => {
            if (input) {
                input.addEventListener('input', updatePreview);
            }
        });

        // Preview level selector
        console.log('✅ [VIP] Preview level buttons found:', previewLevelButtons.length);
        previewLevelButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('✅ [VIP] Preview level clicked:', btn.dataset.level);
                previewLevelButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentPreviewLevel = btn.dataset.level;
                updatePreview();
            });
        });

        // ═══════════════════════════════════════════════════════════
        // SAVE SETTINGS
        // ═══════════════════════════════════════════════════════════

        console.log('✅ [VIP] Save button found:', !!saveBtn);
        if (saveBtn) {
            saveBtn.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('✅ [VIP] Save button clicked');
                saveSettings();
            });
        }

        function saveSettings() {
            // Validate first
            if (!validateAll()) {
                statusEl.innerHTML = '<span class="ppv-error">' + (T.validation_error || 'Validation error') + '</span>';
                return;
            }

            if (!window.ppv_vip || !window.ppv_vip.base) {
                console.error('❌ [VIP] ppv_vip config not found');
                return;
            }

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> ...';

            const formData = new FormData();

            // Language
            formData.append('lang', lang);

            // 1. Fixed
            formData.append('vip_fix_enabled', fixEnabled?.checked ? '1' : '0');
            formData.append('vip_fix_silver', fixSilver?.value || '1');
            formData.append('vip_fix_gold', fixGold?.value || '2');
            formData.append('vip_fix_platinum', fixPlatinum?.value || '3');

            // 2. Streak
            formData.append('vip_streak_enabled', streakEnabled?.checked ? '1' : '0');
            formData.append('vip_streak_count', streakCount?.value || '10');
            formData.append('vip_streak_type', streakType?.value || 'fixed');
            formData.append('vip_streak_silver', streakSilver?.value || '1');
            formData.append('vip_streak_gold', streakGold?.value || '2');
            formData.append('vip_streak_platinum', streakPlatinum?.value || '3');

            // 3. Daily
            formData.append('vip_daily_enabled', dailyEnabled?.checked ? '1' : '0');
            formData.append('vip_daily_silver', dailySilver?.value || '10');
            formData.append('vip_daily_gold', dailyGold?.value || '20');
            formData.append('vip_daily_platinum', dailyPlatinum?.value || '30');

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
                    console.log('✅ [VIP] Extended settings saved');
                } else {
                    // Show error message (may contain newlines)
                    const errorMsg = data.msg || T.error || 'Error';
                    statusEl.innerHTML = '<span class="ppv-error">' + errorMsg.replace(/\n/g, '<br>') + '</span>';
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

        // Initial state
        updateCardStates();
        updateStreakTypeVisibility();
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
