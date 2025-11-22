/**
 * PunktePass – VIP Settings Management (Extended)
 * Supports 4 bonus types:
 * 1. Percentage bonus per level
 * 2. Fixed point bonus per level
 * 3. Every Xth scan bonus
 * 4. First daily scan bonus
 *
 * Version: 2.0
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

        // 1. Percentage bonus
        const pctEnabled = document.getElementById('ppv-vip-enabled');
        const pctSilver = document.getElementById('ppv-pct-silver');
        const pctGold = document.getElementById('ppv-pct-gold');
        const pctPlatinum = document.getElementById('ppv-pct-platinum');
        const pctError = document.getElementById('ppv-pct-error');

        // 2. Fixed point bonus
        const fixEnabled = document.getElementById('ppv-fix-enabled');
        const fixSilver = document.getElementById('ppv-fix-silver');
        const fixGold = document.getElementById('ppv-fix-gold');
        const fixPlatinum = document.getElementById('ppv-fix-platinum');
        const fixError = document.getElementById('ppv-fix-error');

        // 3. Streak bonus
        const streakEnabled = document.getElementById('ppv-streak-enabled');
        const streakCount = document.getElementById('ppv-streak-count');
        const streakType = document.getElementById('ppv-streak-type');
        const streakSilver = document.getElementById('ppv-streak-silver');
        const streakGold = document.getElementById('ppv-streak-gold');
        const streakPlatinum = document.getElementById('ppv-streak-platinum');
        const streakError = document.getElementById('ppv-streak-error');
        const streakFixedInputs = document.querySelector('.ppv-streak-fixed-inputs');

        // 4. Daily bonus
        const dailyEnabled = document.getElementById('ppv-daily-enabled');
        const dailySilver = document.getElementById('ppv-daily-silver');
        const dailyGold = document.getElementById('ppv-daily-gold');
        const dailyPlatinum = document.getElementById('ppv-daily-platinum');
        const dailyError = document.getElementById('ppv-daily-error');

        // Preview elements
        const previewLevelButtons = document.querySelectorAll('.ppv-preview-level');
        const previewRowPct = document.getElementById('preview-row-pct');
        const previewRowFix = document.getElementById('preview-row-fix');
        const previewRowStreak = document.getElementById('preview-row-streak');
        const previewRowDaily = document.getElementById('preview-row-daily');
        const previewPctValue = document.getElementById('preview-pct-value');
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

                    // 1. Percentage
                    if (pctEnabled) pctEnabled.checked = s.vip_enabled;
                    if (pctSilver) pctSilver.value = s.vip_silver_bonus;
                    if (pctGold) pctGold.value = s.vip_gold_bonus;
                    if (pctPlatinum) pctPlatinum.value = s.vip_platinum_bonus;

                    // 2. Fixed
                    if (fixEnabled) fixEnabled.checked = s.vip_fix_enabled;
                    if (fixSilver) fixSilver.value = s.vip_fix_silver;
                    if (fixGold) fixGold.value = s.vip_fix_gold;
                    if (fixPlatinum) fixPlatinum.value = s.vip_fix_platinum;

                    // 3. Streak
                    if (streakEnabled) streakEnabled.checked = s.vip_streak_enabled;
                    if (streakCount) streakCount.value = s.vip_streak_count;
                    if (streakType) streakType.value = s.vip_streak_type;
                    if (streakSilver) streakSilver.value = s.vip_streak_silver;
                    if (streakGold) streakGold.value = s.vip_streak_gold;
                    if (streakPlatinum) streakPlatinum.value = s.vip_streak_platinum;

                    // 4. Daily
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
                { toggle: pctEnabled, card: pctEnabled?.closest('.ppv-vip-card') },
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

            // 1. Percentage
            if (pctEnabled?.checked) {
                const valid = validateAscending(pctSilver?.value, pctGold?.value, pctPlatinum?.value);
                if (pctError) {
                    pctError.textContent = valid ? '' : validationMsg;
                    pctError.style.display = valid ? 'none' : 'block';
                }
                if (!valid) isValid = false;
            } else if (pctError) {
                pctError.style.display = 'none';
            }

            // 2. Fixed
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

            // 3. Streak (only if type=fixed)
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

            // 4. Daily
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

            // 1. Percentage bonus
            if (pctEnabled?.checked) {
                const pct = getValue(pctSilver, pctGold, pctPlatinum);
                const pctBonus = Math.round(basePoints * (pct / 100));
                if (previewPctValue) previewPctValue.textContent = '+' + pctBonus;
                if (previewRowPct) previewRowPct.style.display = 'flex';
                total += pctBonus;
            } else {
                if (previewRowPct) previewRowPct.style.display = 'none';
            }

            // 2. Fixed bonus
            if (fixEnabled?.checked) {
                const fix = getValue(fixSilver, fixGold, fixPlatinum);
                if (previewFixValue) previewFixValue.textContent = '+' + fix;
                if (previewRowFix) previewRowFix.style.display = 'flex';
                total += fix;
            } else {
                if (previewRowFix) previewRowFix.style.display = 'none';
            }

            // 3. Streak bonus (assume 10th scan in preview scenario)
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

            // 4. Daily bonus (assume first scan of day in preview scenario)
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
        [pctEnabled, fixEnabled, streakEnabled, dailyEnabled].forEach(toggle => {
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
            pctSilver, pctGold, pctPlatinum,
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
        previewLevelButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                previewLevelButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentPreviewLevel = btn.dataset.level;
                updatePreview();
            });
        });

        // ═══════════════════════════════════════════════════════════
        // SAVE SETTINGS
        // ═══════════════════════════════════════════════════════════

        if (saveBtn) {
            saveBtn.addEventListener('click', saveSettings);
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

            // 1. Percentage
            formData.append('vip_enabled', pctEnabled?.checked ? '1' : '0');
            formData.append('vip_silver_bonus', pctSilver?.value || '5');
            formData.append('vip_gold_bonus', pctGold?.value || '10');
            formData.append('vip_platinum_bonus', pctPlatinum?.value || '20');

            // 2. Fixed
            formData.append('vip_fix_enabled', fixEnabled?.checked ? '1' : '0');
            formData.append('vip_fix_silver', fixSilver?.value || '5');
            formData.append('vip_fix_gold', fixGold?.value || '10');
            formData.append('vip_fix_platinum', fixPlatinum?.value || '20');

            // 3. Streak
            formData.append('vip_streak_enabled', streakEnabled?.checked ? '1' : '0');
            formData.append('vip_streak_count', streakCount?.value || '10');
            formData.append('vip_streak_type', streakType?.value || 'fixed');
            formData.append('vip_streak_silver', streakSilver?.value || '30');
            formData.append('vip_streak_gold', streakGold?.value || '50');
            formData.append('vip_streak_platinum', streakPlatinum?.value || '100');

            // 4. Daily
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
