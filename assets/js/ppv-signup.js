/**
 * PunktePass - Signup JavaScript (FIXED)
 * Password Strength Indicator + Validation + Google OAuth
 * Multi-language Support
 * Author: Erik Borota / PunktePass
 */

(function($) {
    'use strict';
    
    // Translation helper - use translations passed from PHP
    const t = (key) => {
        return window.ppvSignupTranslations?.[key] || key;
    };
    
    $(document).ready(function() {
        initSignup();
    });
    
    /**
     * Initialize Signup System
     */
    function initSignup() {
        initPasswordToggle();
        initPasswordStrength();
        initPasswordMatch();
        initFormValidation();
        initGoogleSignup();
        initFormSubmit();
        initLanguageSwitcher();
    }
    
    /**
     * Password Toggle (Show/Hide)
     */
    function initPasswordToggle() {
        $('.ppv-password-toggle').on('click', function() {
            const $btn = $(this);
            const $input = $btn.closest('.ppv-password-wrapper').find('input');
            const $eyeOpen = $btn.find('.ppv-eye-open');
            const $eyeClosed = $btn.find('.ppv-eye-closed');
            
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $eyeOpen.hide();
                $eyeClosed.show();
            } else {
                $input.attr('type', 'password');
                $eyeOpen.show();
                $eyeClosed.hide();
            }
        });
    }
    
    /**
     * Password Strength Indicator
     */
    function initPasswordStrength() {
        const $password = $('#ppv-password');
        const $strength = $('#ppv-password-strength');
        const $fill = $('.ppv-strength-fill');
        const $text = $('.ppv-strength-text');
        
        // Requirements
        const $reqLength = $('#req-length');
        const $reqUpper = $('#req-uppercase');
        const $reqNumber = $('#req-number');
        const $reqSpecial = $('#req-special');
        
        $password.on('input', function() {
            const password = $(this).val();
            
            if (password.length === 0) {
                $strength.hide();
                resetRequirements();
                return;
            }
            
            $strength.show();
            
            // Check requirements
            const hasLength = password.length >= 8;
            const hasUpper = /[A-Z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
            
            // Update requirement indicators
            updateRequirement($reqLength, hasLength);
            updateRequirement($reqUpper, hasUpper);
            updateRequirement($reqNumber, hasNumber);
            updateRequirement($reqSpecial, hasSpecial);
            
            // Calculate strength
            let strength = 0;
            if (hasLength) strength++;
            if (hasUpper) strength++;
            if (hasNumber) strength++;
            if (hasSpecial) strength++;
            
            // Update strength bar
            const percent = (strength / 4) * 100;
            $fill.css('width', percent + '%');
            
            // Update color and text based on language
            if (strength <= 1) {
                $fill.css('background', '#EF4444');
                $text.text(t('password_strength_weak')).css('color', '#EF4444');
            } else if (strength === 2) {
                $fill.css('background', '#F59E0B');
                $text.text(t('password_strength_medium')).css('color', '#F59E0B');
            } else if (strength === 3) {
                $fill.css('background', '#10B981');
                $text.text(t('password_strength_good')).css('color', '#10B981');
            } else {
                $fill.css('background', '#10B981');
                $text.text(t('password_strength_strong')).css('color', '#10B981');
            }
        });
        
        function resetRequirements() {
            $reqLength.css('color', '#6B7280');
            $reqUpper.css('color', '#6B7280');
            $reqNumber.css('color', '#6B7280');
            $reqSpecial.css('color', '#6B7280');
        }
        
        function updateRequirement($el, met) {
            if (met) {
                $el.css('color', '#10B981').html('✓ ' + $el.text().replace('✓ ', '').replace('✗ ', ''));
            } else {
                $el.css('color', '#EF4444').html('✗ ' + $el.text().replace('✓ ', '').replace('✗ ', ''));
            }
        }
    }
    
    /**
     * Password Match Indicator
     */
    function initPasswordMatch() {
        const $password = $('#ppv-password');
        const $confirm = $('#ppv-password-confirm');
        
        $confirm.on('input', function() {
            const password = $password.val();
            const confirm = $(this).val();
            
            if (confirm.length === 0) {
                $(this).css('border-color', '');
                return;
            }
            
            if (password === confirm) {
                $(this).css('border-color', '#10B981');
            } else {
                $(this).css('border-color', '#EF4444');
            }
        });
    }
    
    /**
     * Real-time Form Validation
     */
    function initFormValidation() {
        const $email = $('#ppv-email');
        
        $email.on('blur', function() {
            const email = $(this).val().trim();
            if (email && !isValidEmail(email)) {
                showFieldError($(this), t('error_invalid_email'));
            } else {
                clearFieldError($(this));
            }
        });
        
        $email.on('input', function() {
            clearFieldError($(this));
            hideAlert();
        });
    }
    
    /**
     * Email Validation
     */
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    /**
     * Show Field Error
     */
    function showFieldError($field, message) {
        $field.css({
            'border-color': '#EF4444',
            'background': '#FEE2E2'
        });
        
        if (!$field.next('.ppv-field-error').length) {
            $field.after(`<span class="ppv-field-error" style="color:#EF4444;font-size:13px;margin-top:4px;display:block;">${message}</span>`);
        }
    }
    
    /**
     * Clear Field Error
     */
    function clearFieldError($field) {
        $field.css({
            'border-color': '',
            'background': ''
        });
        $field.next('.ppv-field-error').remove();
    }
    
    /**
     * Initialize Google Signup
     */
    let googleInitialized = false;
    let googlePromptActive = false;
    let googleButtonRendered = false;

    function initGoogleSignup() {
        const clientId = ppvSignup.google_client_id;

        if (!clientId) {
            console.warn('Google Client ID not configured');
            $('#ppv-google-signup-btn').prop('disabled', true).css('opacity', '0.5');
            return;
        }

        // Try to initialize Google SDK (may not be loaded yet)
        tryInitializeGoogle(clientId);

        // Manual button click handler
        $('#ppv-google-signup-btn').on('click', function() {
            // Prevent double-click while prompt is active
            if (googlePromptActive) {
                return;
            }

            if (googleInitialized && typeof google !== 'undefined' && google.accounts) {
                showGooglePrompt();
            } else if (typeof google !== 'undefined' && google.accounts) {
                // SDK loaded but not initialized yet - initialize now
                tryInitializeGoogle(clientId);
                setTimeout(function() {
                    if (googleInitialized) {
                        showGooglePrompt();
                    } else {
                        showAlert(t('error_google_unavailable'), 'info');
                    }
                }, 100);
            } else {
                showAlert(t('error_google_unavailable'), 'info');
                waitForGoogleSDK(clientId);
            }
        });
    }

    /**
     * Show Google Sign-In prompt with proper error handling
     */
    function showGooglePrompt() {
        googlePromptActive = true;

        try {
            google.accounts.id.cancel();
            google.accounts.id.prompt((notification) => {
                googlePromptActive = false;

                if (notification.isNotDisplayed()) {
                    const reason = notification.getNotDisplayedReason();
                    if (reason === 'suppressed_by_user' || reason === 'opt_out_or_no_session') {
                        openGoogleButtonFallback();
                        return;
                    }
                    if (reason === 'browser_not_supported') {
                        showAlert(t('error_google_unavailable'), 'error');
                    } else if (reason === 'invalid_client') {
                        showAlert(t('error_google_unavailable'), 'error');
                    }
                }
            });
        } catch (e) {
            googlePromptActive = false;
            openGoogleButtonFallback();
        }
    }

    /**
     * Fallback: Render Google Sign-In button when One Tap is suppressed
     */
    function openGoogleButtonFallback() {
        if (googleButtonRendered) {
            const renderedBtn = document.querySelector('#ppv-google-rendered-btn div[role="button"]');
            if (renderedBtn) {
                renderedBtn.click();
            }
            return;
        }

        const $btn = $('#ppv-google-signup-btn');
        const container = document.createElement('div');
        container.id = 'ppv-google-rendered-btn';
        container.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;opacity:0.01;cursor:pointer;';
        $btn.css('position', 'relative').append(container);

        google.accounts.id.renderButton(container, {
            type: 'standard',
            theme: 'outline',
            size: 'large',
            width: $btn.outerWidth()
        });

        googleButtonRendered = true;

        setTimeout(() => {
            const renderedBtn = container.querySelector('div[role="button"]');
            if (renderedBtn) {
                renderedBtn.click();
            }
        }, 100);
    }

    /**
     * Try to initialize Google SDK
     */
    function tryInitializeGoogle(clientId) {
        if (googleInitialized) return true;

        if (typeof google !== 'undefined' && google.accounts) {
            try {
                google.accounts.id.initialize({
                    client_id: clientId,
                    callback: handleGoogleCallback,
                    auto_select: false,
                    cancel_on_tap_outside: true,
                    itp_support: true,
                    use_fedcm_for_prompt: false
                });
                googleInitialized = true;
                return true;
            } catch (e) {
                return false;
            }
        }
        return false;
    }

    /**
     * Wait for Google SDK to load then initialize
     */
    function waitForGoogleSDK(clientId) {
        let attempts = 0;
        const maxAttempts = 20;

        const checkInterval = setInterval(function() {
            attempts++;
            if (tryInitializeGoogle(clientId)) {
                clearInterval(checkInterval);
            } else if (attempts >= maxAttempts) {
                clearInterval(checkInterval);
            }
        }, 100);
    }
    
    /**
     * Handle Google OAuth Callback
     */
    function handleGoogleCallback(response) {
        if (!response.credential) {
            showAlert(t('error_google_failed'), 'error');
            return;
        }

        const $btn = $('#ppv-google-signup-btn');
        $btn.prop('disabled', true).html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></path></svg><span>' + t('registering') + '</span>');

        // Get selected user type
        const userType = $('#ppv-user-type').val() || 'user';

        $.ajax({
            url: ppvSignup.ajaxurl,
            type: 'POST',
            data: {
                action: 'ppv_google_signup',
                nonce: ppvSignup.nonce,
                credential: response.credential,
                user_type: userType
            },
            success: function(res) {
                if (res.success) {
                    showAlert(res.data.message, 'success');
                    setTimeout(function() {
                        window.location.href = res.data.redirect;
                    }, 1000);
                } else {
                    showAlert(res.data.message, 'error');
                    resetGoogleButton($btn);
                }
            },
            error: function() {
                showAlert(t('error_connection'), 'error');
                resetGoogleButton($btn);
            }
        });
    }
    
    /**
     * Reset Google Button
     */
    function resetGoogleButton($btn) {
        $btn.prop('disabled', false).html(`
            <svg width="20" height="20" viewBox="0 0 48 48">
                <path d="M47.532 24.5528C47.532 22.9214 47.3997 21.2811 47.1175 19.6761H24.48V28.9181H37.4434C36.9055 31.8988 35.177 34.5356 32.6461 36.2111V42.2078H40.3801C44.9217 38.0278 47.532 31.8547 47.532 24.5528Z" fill="#4285F4"/>
                <path d="M24.48 48.0016C30.9529 48.0016 36.4116 45.8764 40.3888 42.2078L32.6549 36.2111C30.5031 37.675 27.7252 38.5039 24.4888 38.5039C18.2275 38.5039 12.9187 34.2798 11.0139 28.6006H3.03296V34.7825C7.10718 42.8868 15.4056 48.0016 24.48 48.0016Z" fill="#34A853"/>
                <path d="M11.0051 28.6006C9.99973 25.6199 9.99973 22.3922 11.0051 19.4115V13.2296H3.03298C-0.371021 20.0112 -0.371021 28.0009 3.03298 34.7825L11.0051 28.6006Z" fill="#FBBC04"/>
                <path d="M24.48 9.49932C27.9016 9.44641 31.2086 10.7339 33.6866 13.0973L40.5387 6.24523C36.2 2.17101 30.4414 -0.068932 24.48 0.00161733C15.4055 0.00161733 7.10718 5.11644 3.03296 13.2296L11.005 19.4115C12.901 13.7235 18.2187 9.49932 24.48 9.49932Z" fill="#EA4335"/>
            </svg>
            <span>` + t('signup_google_btn') + `</span>
        `);
    }
    
    /**
     * Form Submit Handler
     */
    function initFormSubmit() {
        $('#ppv-signup-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $btn = $('#ppv-submit-btn');
            const $btnText = $btn.find('.ppv-btn-text');
            const $btnLoader = $btn.find('.ppv-btn-loader');
            
            // Get values
            const email = $('#ppv-email').val().trim();
            const password = $('#ppv-password').val();
            const passwordConfirm = $('#ppv-password-confirm').val();
            const terms = $('#ppv-terms').is(':checked');
            const privacy = $('#ppv-privacy').is(':checked');
            const userType = $('#ppv-user-type').val() || 'user';
            
            // Validation
            if (!email || !password || !passwordConfirm) {
                showAlert(t('error_fill_all'), 'error');
                return;
            }
            
            if (!isValidEmail(email)) {
                showAlert(t('error_invalid_email'), 'error');
                $('#ppv-email').focus();
                return;
            }
            
            if (password !== passwordConfirm) {
                showAlert(t('error_password_mismatch'), 'error');
                $('#ppv-password-confirm').focus();
                return;
            }
            
            if (password.length < 8) {
                showAlert(t('error_password_short'), 'error');
                $('#ppv-password').focus();
                return;
            }
            
            // Check password requirements
            const hasUpper = /[A-Z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
            
            if (!hasUpper || !hasNumber || !hasSpecial) {
                showAlert(t('error_password_requirements'), 'error');
                $('#ppv-password').focus();
                return;
            }
            
            if (!terms || !privacy) {
                showAlert(t('error_terms'), 'error');
                return;
            }
            
            // Show loading
            $btn.prop('disabled', true);
            $btnText.hide();
            $btnLoader.show();
            hideAlert();
            
            // AJAX request
            $.ajax({
                url: ppvSignup.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ppv_signup',
                    nonce: ppvSignup.nonce,
                    email: email,
                    password: password,
                    password_confirm: passwordConfirm,
                    terms: terms,
                    privacy: privacy,
                    user_type: userType
                },
                success: function(res) {
                    if (res.success) {
                        showAlert(res.data.message, 'success');
                        
                        // Disable form
                        $form.css({
                            'opacity': '0.5',
                            'pointer-events': 'none'
                        });
                        
                        // Redirect
                        setTimeout(function() {
                            window.location.href = res.data.redirect;
                        }, 1500);
                    } else {
                        showAlert(res.data.message, 'error');
                        resetSubmitButton($btn, $btnText, $btnLoader);
                        
                        // Shake animation
                        $form.css('animation', 'shake 0.5s');
                        setTimeout(function() {
                            $form.css('animation', '');
                        }, 500);
                    }
                },
                error: function() {
                    showAlert(t('error_connection'), 'error');
                    resetSubmitButton($btn, $btnText, $btnLoader);
                }
            });
        });
    }
    
    /**
     * Reset Submit Button
     */
    function resetSubmitButton($btn, $btnText, $btnLoader) {
        $btn.prop('disabled', false);
        $btnText.show();
        $btnLoader.hide();
    }
    
    /**
     * Show Alert
     */
    function showAlert(message, type) {
        const $alert = $('#ppv-signup-alert');
        $alert
            .removeClass('success error')
            .addClass(type)
            .html(message)
            .fadeIn(300);
    }
    
    /**
     * Hide Alert
     */
    function hideAlert() {
        $('#ppv-signup-alert').fadeOut(300);
    }
    
    /**
     * Language Switcher
     */
    function initLanguageSwitcher() {
        const sel = document.getElementById('ppv-lang-select');
        if (!sel) return;

        sel.addEventListener('change', function(e) {
            const lang = e.target.value;
            
            // Set cookie
            const maxAge = 60 * 60 * 24 * 365;
            document.cookie = `ppv_lang=${lang}; path=/; max-age=${maxAge}; SameSite=Lax`;
            
            // Set localStorage
            localStorage.setItem('ppv_lang', lang);
            
            // Reload with URL parameter
            const url = new URL(window.location.href);
            url.searchParams.set('lang', lang);
            window.location.href = url.toString();
        });
    }
    
    /**
     * Add shake animation CSS
     */
    const shakeCSS = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
    `;
    
    if (!document.getElementById('ppv-shake-animation')) {
        const style = document.createElement('style');
        style.id = 'ppv-shake-animation';
        style.textContent = shakeCSS;
        document.head.appendChild(style);
    }
    
    /**
     * Add password strength CSS
     */
    const strengthCSS = `
        .ppv-password-strength {
            margin-top: 8px;
        }
        .ppv-strength-bar {
            height: 4px;
            background: #E5E7EB;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 4px;
        }
        .ppv-strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background 0.3s ease;
        }
        .ppv-strength-text {
            font-size: 12px;
            font-weight: 600;
            margin: 0;
        }
        .ppv-password-requirements {
            margin-top: 8px;
        }
        .ppv-password-requirements ul li {
            transition: color 0.2s ease;
        }
    `;
    
    if (!document.getElementById('ppv-strength-css')) {
        const style = document.createElement('style');
        style.id = 'ppv-strength-css';
        style.textContent = strengthCSS;
        document.head.appendChild(style);
    }
    
})(jQuery);
