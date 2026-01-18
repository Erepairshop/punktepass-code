/**
 * PunktePass - Premium Login JavaScript
 * Google OAuth + Form Handling + Animations
 * ✅ Session expired return URL support
 * Author: Erik Borota / PunktePass
 */

// 🍎 GLOBAL iOS Google callback - ALWAYS AVAILABLE for native iOS app
window.handleGoogleCallback = function(data) {
    ppvLog("🍎 Native Google token received", data);

    if (!data || !data.credential) {
        alert(ppvLogin?.strings?.google_login_failed || "Google bejelentkezés sikertelen");
        return;
    }

    // Fallback values if ppvLogin doesn't exist
    var ajaxUrl = (typeof ppvLogin !== 'undefined' && ppvLogin.ajaxurl)
        ? ppvLogin.ajaxurl
        : '/wp-admin/admin-ajax.php';
    var nonce = (typeof ppvLogin !== 'undefined' && ppvLogin.nonce)
        ? ppvLogin.nonce
        : '';

    ppvLog("🍎 Sending to:", ajaxUrl);

    jQuery.ajax({
        url: ajaxUrl,
        type: "POST",
        data: {
            action: "ppv_google_login",
            nonce: nonce,
            credential: data.credential,
            device_fingerprint: ""
        },
        success: function(res) {
            ppvLog("🍎 Server response:", res);
            if (res.success) {
                window.location.href = res.data.redirect || "/";
            } else {
                alert(res.data.message || ppvLogin?.strings?.google_login_failed || "Google bejelentkezés sikertelen");
            }
        },
        error: function(xhr, status, error) {
            ppvLog.error("🍎 AJAX error:", status, error);
            alert(ppvLogin?.strings?.connection_error || "Kapcsolódási hiba");
        }
    });
};

(function($) {
    'use strict';

    // 📱 Global device fingerprint (loaded async)
    let deviceFingerprint = '';

    // Wait for DOM
    $(document).ready(function() {
        initLogin();
        initFingerprintJS();
    });

    /**
     * 📱 Initialize FingerprintJS for device tracking
     * Note: FingerprintJS is now loaded locally via vendor/fp.min.js (no CDN dependency)
     */
    function initFingerprintJS() {
        // FingerprintJS should already be loaded via local vendor script
        if (typeof FingerprintJS !== 'undefined') {
            loadFingerprint();
        } else {
            // Fallback: wait for script to load (shouldn't happen normally)
            ppvLog.warn('📱 FingerprintJS not yet loaded, waiting...');
            setTimeout(initFingerprintJS, 100);
        }
    }

    /**
     * 📱 Load device fingerprint
     */
    function loadFingerprint() {
        if (typeof FingerprintJS !== 'undefined') {
            FingerprintJS.load().then(fp => {
                fp.get().then(result => {
                    deviceFingerprint = result.visitorId;
                    ppvLog('📱 Device fingerprint loaded for login tracking');
                });
            }).catch(err => {
                ppvLog.warn('📱 FingerprintJS error:', err);
            });
        }
    }

    /**
     * 📱 Get current fingerprint (for AJAX calls)
     */
    function getDeviceFingerprint() {
        return deviceFingerprint || '';
    }

    /**
     * Get return URL (from session expired redirect)
     */
    function getReturnUrl() {
        const returnUrl = sessionStorage.getItem('ppv_return_url');
        if (returnUrl && returnUrl !== '/login' && returnUrl !== '/signup') {
            return returnUrl;
        }
        return null;
    }

    /**
     * Clear return URL after use
     */
    function clearReturnUrl() {
        sessionStorage.removeItem('ppv_return_url');
    }

    /**
     * Get final redirect URL (return URL or server provided)
     */
    function getFinalRedirectUrl(serverRedirect) {
        const returnUrl = getReturnUrl();
        if (returnUrl) {
            clearReturnUrl();
            return returnUrl;
        }
        return serverRedirect;
    }

    /**
     * Initialize Login System
     */
function initLogin() {
        initPasswordToggle();
        initFormValidation();
        initGoogleLogin();
        initFacebookLogin();
        // initTikTokLogin(); // TikTok login disabled
        initAppleLogin();
        initFormSubmit();
        initLanguageSwitcher();
        showSessionExpiredMessage();
    }

    /**
     * Show message if redirected from session expiry
     */
    function showSessionExpiredMessage() {
        const returnUrl = getReturnUrl();
        if (returnUrl) {
            // Optional: Show info message
            // showAlert('Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.', 'info');
        }
    }
    
    /**
     * Password Toggle (Show/Hide)
     */
    function initPasswordToggle() {
        $('.ppv-password-toggle').on('click', function() {
            const $btn = $(this);
            const $input = $('#ppv-password');
            const $eyeOpen = $btn.find('.ppv-eye-open');
            const $eyeClosed = $btn.find('.ppv-eye-closed');
            
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $eyeOpen.hide();
                $eyeClosed.show();
                $btn.attr('aria-label', ppvLogin?.strings?.hide_password || 'Passwort verstecken');
            } else {
                $input.attr('type', 'password');
                $eyeOpen.show();
                $eyeClosed.hide();
                $btn.attr('aria-label', ppvLogin?.strings?.show_password || 'Passwort anzeigen');
            }
        });
    }
    
    /**
     * Real-time Form Validation
     */
    function initFormValidation() {
        const $email = $('#ppv-email');
        const $password = $('#ppv-password');

        // Email/Username validation - only validate email format if it looks like an email
        $email.on('blur', function() {
            const input = $(this).val().trim();
            // Only validate as email if it contains @ symbol
            if (input && input.includes('@') && !isValidEmail(input)) {
                showFieldError($(this), ppvLogin?.strings?.invalid_email || 'Bitte geben Sie eine gültige Email-Adresse ein');
            } else {
                clearFieldError($(this));
            }
        });

        // Password validation
        $password.on('blur', function() {
            const password = $(this).val();
            if (password && password.length < 6) {
                showFieldError($(this), ppvLogin?.strings?.password_min_length || 'Passwort muss mindestens 6 Zeichen lang sein');
            } else {
                clearFieldError($(this));
            }
        });
        
        // Clear error on input
        $email.add($password).on('input', function() {
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
        
        // Add error message if not exists
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
     * Initialize Google Login
     */
    let googleInitialized = false;
    let googlePromptActive = false;

    // 🔍 DEBUG MODE (set to false for production)
    const GOOGLE_DEBUG = false;
    function glog(...args) {
        if (GOOGLE_DEBUG) ppvLog('[GOOGLE]', ...args);
    }

    function initGoogleLogin() {
        const clientId = ppvLogin.google_client_id;

        glog('🚀 initGoogleLogin called');
        glog('📋 Client ID:', clientId ? clientId.substring(0, 20) + '...' : 'MISSING');
        glog('📦 google object exists:', typeof google !== 'undefined');
        glog('📦 google.accounts exists:', typeof google !== 'undefined' && !!google.accounts);
        glog('📱 isNativeIOSApp:', window.isNativeIOSApp);

        // 🍎 iOS native app handles Google login natively - skip web SDK initialization
        if (window.isNativeIOSApp) {
            glog('🍎 Native iOS app detected - skipping Google SDK initialization');
            ppvLog('🍎 Google login will be handled natively by iOS app');
            // Button remains enabled - iOS native handler will intercept clicks
            return;
        }

        if (!clientId) {
            ppvLog.warn('Google Client ID not configured');
            return;
        }

        // Try to initialize Google SDK (may not be loaded yet)
        const initResult = tryInitializeGoogle(clientId);
        glog('🔧 Initial tryInitializeGoogle result:', initResult);

        // Manual button click handler
        $('#ppv-google-login-btn').on('click', function() {
            glog('👆 Button clicked!');
            glog('   - googleInitialized:', googleInitialized);
            glog('   - googlePromptActive:', googlePromptActive);
            glog('   - google exists:', typeof google !== 'undefined');
            glog('   - google.accounts exists:', typeof google !== 'undefined' && !!google.accounts);

            // Prevent double-click while prompt is active
            if (googlePromptActive) {
                glog('⏳ Blocked - prompt already active');
                return;
            }

            if (googleInitialized && typeof google !== 'undefined' && google.accounts) {
                glog('✅ Path A: Already initialized, showing prompt');
                showGooglePrompt();
            } else if (typeof google !== 'undefined' && google.accounts) {
                glog('⚠️ Path B: SDK loaded but not initialized');
                // SDK loaded but not initialized yet - initialize now
                tryInitializeGoogle(clientId);
                // Small delay then prompt
                setTimeout(function() {
                    glog('   - After timeout, googleInitialized:', googleInitialized);
                    if (googleInitialized) {
                        showGooglePrompt();
                    } else {
                        showAlert(ppvLogin?.strings?.google_loading || 'Google bejelentkezés betöltése...', 'info');
                    }
                }, 100);
            } else {
                glog('❌ Path C: SDK not loaded yet');
                showAlert(ppvLogin?.strings?.google_loading || 'Google bejelentkezés betöltése...', 'info');
                // Try again in case SDK loads soon
                waitForGoogleSDK(clientId);
            }
        });

        glog('✅ Click handler registered');
    }

    /**
     * Show Google Sign-In prompt with proper error handling
     */
    function showGooglePrompt() {
        glog('🔔 showGooglePrompt() called');
        googlePromptActive = true;

        try {
            // Cancel any previous prompt first
            google.accounts.id.cancel();
            glog('📤 Calling google.accounts.id.prompt()...');

            google.accounts.id.prompt((notification) => {
                glog('📥 Prompt notification received');
                googlePromptActive = false;

                // Handle different notification states
                if (notification.isNotDisplayed()) {
                    const reason = notification.getNotDisplayedReason();
                    glog('❌ NOT DISPLAYED - reason:', reason);

                    // If suppressed/cooldown, use OAuth popup fallback
                    if (reason === 'suppressed_by_user' || reason === 'opt_out_or_no_session') {
                        glog('🔄 Using OAuth popup fallback...');
                        openGoogleOAuthPopup();
                        return;
                    }

                    if (reason === 'browser_not_supported') {
                        showAlert(ppvLogin?.strings?.google_not_supported || 'Google Login wird von diesem Browser nicht unterstützt', 'error');
                    } else if (reason === 'invalid_client') {
                        showAlert(ppvLogin?.strings?.google_login_failed || 'Google Login Konfigurationsfehler', 'error');
                    }
                }

                if (notification.isSkippedMoment()) {
                    const reason = notification.getSkippedReason();
                    glog('⏭️ SKIPPED - reason:', reason);
                    // User cancelled - also try popup on next click
                }

                if (notification.isDismissedMoment()) {
                    const reason = notification.getDismissedReason();
                    glog('🚪 DISMISSED - reason:', reason);
                }

                glog('📊 Moment type:', notification.getMomentType());
            });
            glog('📤 prompt() call completed');
        } catch (e) {
            glog('💥 EXCEPTION in prompt():', e);
            googlePromptActive = false;
            // Fallback to OAuth popup
            openGoogleOAuthPopup();
        }
    }

    /**
     * Fallback: Render Google Sign-In button when One Tap is suppressed
     */
    let googleButtonRendered = false;

    function openGoogleOAuthPopup() {
        glog('🔄 Fallback: Rendering Google button');

        // Only render once
        if (googleButtonRendered) {
            glog('⚠️ Button already rendered, triggering click');
            // Try to click the rendered button
            const renderedBtn = document.querySelector('#ppv-google-rendered-btn div[role="button"]');
            if (renderedBtn) {
                renderedBtn.click();
            }
            return;
        }

        const $btn = $('#ppv-google-login-btn');

        // Create container for Google's rendered button
        const container = document.createElement('div');
        container.id = 'ppv-google-rendered-btn';
        container.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;opacity:0.01;cursor:pointer;';

        // Insert into button
        $btn.css('position', 'relative').append(container);

        // Render Google button
        google.accounts.id.renderButton(container, {
            type: 'standard',
            theme: 'outline',
            size: 'large',
            width: $btn.outerWidth(),
            click_listener: () => {
                glog('🖱️ Google rendered button clicked');
            }
        });

        googleButtonRendered = true;
        glog('✅ Google button rendered as overlay');

        // Auto-click it
        setTimeout(() => {
            const renderedBtn = container.querySelector('div[role="button"]');
            if (renderedBtn) {
                glog('🖱️ Auto-clicking rendered button');
                renderedBtn.click();
            }
        }, 100);
    }

    /**
     * Try to initialize Google SDK
     */
    function tryInitializeGoogle(clientId) {
        glog('🔧 tryInitializeGoogle called');
        glog('   - Already initialized:', googleInitialized);

        if (googleInitialized) return true;

        glog('   - google exists:', typeof google !== 'undefined');
        glog('   - google.accounts exists:', typeof google !== 'undefined' && !!google.accounts);

        if (typeof google !== 'undefined' && google.accounts) {
            try {
                glog('🔧 Calling google.accounts.id.initialize()...');
                google.accounts.id.initialize({
                    client_id: clientId,
                    callback: handleGoogleCallback,
                    auto_select: false,
                    cancel_on_tap_outside: true,
                    itp_support: true,
                    use_fedcm_for_prompt: false  // Disable FedCM to avoid AbortError
                });
                googleInitialized = true;
                glog('✅ Google Sign-In initialized successfully!');
                return true;
            } catch (e) {
                glog('💥 EXCEPTION in initialize():', e);
                return false;
            }
        }
        glog('⚠️ SDK not available yet');
        return false;
    }

    /**
     * Wait for Google SDK to load then initialize
     */
    function waitForGoogleSDK(clientId) {
        glog('⏳ waitForGoogleSDK started');
        let attempts = 0;
        const maxAttempts = 20; // 2 seconds max

        const checkInterval = setInterval(function() {
            attempts++;
            glog(`   Attempt ${attempts}/${maxAttempts}`);
            if (tryInitializeGoogle(clientId)) {
                glog('✅ SDK loaded after waiting!');
                clearInterval(checkInterval);
            } else if (attempts >= maxAttempts) {
                glog('❌ SDK failed to load after max attempts');
                clearInterval(checkInterval);
            }
        }, 100);
    }
    
    /**
     * Handle Google OAuth Callback
     */
    function handleGoogleCallback(response) {
        glog('🎉 handleGoogleCallback called!');
        glog('   - response:', response);
        glog('   - has credential:', !!response.credential);

        if (!response.credential) {
            glog('❌ No credential in response!');
            showAlert(ppvLogin?.strings?.google_login_failed || 'Google bejelentkezés sikertelen', 'error');
            return;
        }

        glog('✅ Got credential, sending to backend...');

        // Show loading
        const $btn = $('#ppv-google-login-btn');
        $btn.prop('disabled', true).html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></path></svg><span>' + (ppvLogin?.strings?.login_loading || 'Anmelden...') + '</span>');
        
        // Send to backend
        $.ajax({
            url: ppvLogin.ajaxurl,
            type: 'POST',
            data: {
                action: 'ppv_google_login',
                nonce: ppvLogin.nonce,
                credential: response.credential,
                device_fingerprint: getDeviceFingerprint()
            },
            success: function(res) {
                if (res.success) {
                    showAlert(res.data.message, 'success');
                    setTimeout(function() {
                        window.location.href = getFinalRedirectUrl(res.data.redirect);
                    }, 1000);
                } else {
                    showAlert(res.data.message, 'error');
                    resetGoogleButton($btn);
                }
            },
            error: function() {
                showAlert(ppvLogin?.strings?.connection_error || 'Kapcsolódási hiba. Kérjük, próbálja újra.', 'error');
                resetGoogleButton($btn);
            }
        });
    }
    
    /**
     * Reset Google Button
     */
    function resetGoogleButton($btn) {
        $btn.prop('disabled', false).html(`
            <svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M47.532 24.5528C47.532 22.9214 47.3997 21.2811 47.1175 19.6761H24.48V28.9181H37.4434C36.9055 31.8988 35.177 34.5356 32.6461 36.2111V42.2078H40.3801C44.9217 38.0278 47.532 31.8547 47.532 24.5528Z" fill="#4285F4"/>
                <path d="M24.48 48.0016C30.9529 48.0016 36.4116 45.8764 40.3888 42.2078L32.6549 36.2111C30.5031 37.675 27.7252 38.5039 24.4888 38.5039C18.2275 38.5039 12.9187 34.2798 11.0139 28.6006H3.03296V34.7825C7.10718 42.8868 15.4056 48.0016 24.48 48.0016Z" fill="#34A853"/>
                <path d="M11.0051 28.6006C9.99973 25.6199 9.99973 22.3922 11.0051 19.4115V13.2296H3.03298C-0.371021 20.0112 -0.371021 28.0009 3.03298 34.7825L11.0051 28.6006Z" fill="#FBBC04"/>
                <path d="M24.48 9.49932C27.9016 9.44641 31.2086 10.7339 33.6866 13.0973L40.5387 6.24523C36.2 2.17101 30.4414 -0.068932 24.48 0.00161733C15.4055 0.00161733 7.10718 5.11644 3.03296 13.2296L11.005 19.4115C12.901 13.7235 18.2187 9.49932 24.48 9.49932Z" fill="#EA4335"/>
            </svg>
            <span>' + (ppvLogin?.strings?.login_with_google || 'Mit Google anmelden') + '</span>
        `);
    }
    
    /**
     * Form Submit Handler
     */
    function initFormSubmit() {
        $('#ppv-login-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $btn = $('#ppv-submit-btn');
            const $btnText = $btn.find('.ppv-btn-text');
            const $btnLoader = $btn.find('.ppv-btn-loader');
            
            // Get values
            const email = $('#ppv-email').val().trim();
            const password = $('#ppv-password').val();
            const remember = $('#ppv-remember').is(':checked');
            
            // Validation
            if (!email || !password) {
                showAlert(ppvLogin?.strings?.fill_all_fields || 'Bitte füllen Sie alle Felder aus', 'error');
                return;
            }

            // Only validate email format if input contains @ (looks like an email)
            if (email.includes('@') && !isValidEmail(email)) {
                showAlert(ppvLogin?.strings?.invalid_email || 'Bitte geben Sie eine gültige Email-Adresse ein', 'error');
                $('#ppv-email').focus();
                return;
            }
            
            // Show loading
            $btn.prop('disabled', true);
            $btnText.hide();
            $btnLoader.show();
            hideAlert();
            
            // AJAX request
            $.ajax({
                url: ppvLogin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ppv_login',
                    nonce: ppvLogin.nonce,
                    email: email,
                    password: password,
                    remember: remember,
                    device_fingerprint: getDeviceFingerprint()
                },
                success: function(res) {
                    if (res.success) {
                        showAlert(res.data.message, 'success');

                        // Add success animation
                        $form.css({
                            'opacity': '0.5',
                            'pointer-events': 'none'
                        });

                        // Redirect (use return URL if available)
                        setTimeout(function() {
                            window.location.href = getFinalRedirectUrl(res.data.redirect);
                        }, 1000);
                    } else {
                        // 🔧 FIX: Handle expired nonce by auto-reloading
                        if (res.data && res.data.nonce_expired) {
                            showAlert(res.data.message + ' (Seite wird neu geladen...)', 'error');
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                            return;
                        }

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
                    showAlert(ppvLogin?.strings?.connection_error || 'Kapcsolódási hiba. Kérjük, próbálja újra.', 'error');
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
        const $alert = $('#ppv-login-alert');
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
        $('#ppv-login-alert').fadeOut(300);
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
     * Initialize Facebook Login
     */
    function initFacebookLogin() {
        // Check if ppvLogin object exists
        if (typeof ppvLogin === 'undefined') {
            ppvLog.error('❌ ppvLogin object not found - wp_localize_script may not have loaded');
            $('#ppv-facebook-login-btn').prop('disabled', true).css('opacity', '0.5');
            return;
        }

        // Debug: Show full ppvLogin object

        const appId = ppvLogin.facebook_app_id;

        if (!appId || appId === '') {
            ppvLog.warn('⚠️ Facebook App ID is empty');
            $('#ppv-facebook-login-btn').prop('disabled', true).css('opacity', '0.5');
            return;
        }


        // Load Facebook SDK
        window.fbAsyncInit = function() {
            FB.init({
                appId: appId,
                cookie: true,
                xfbml: true,
                version: 'v18.0'
            });
        };

        // Load SDK script
        (function(d, s, id) {
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) return;
            js = d.createElement(s); js.id = id;
            js.src = "https://connect.facebook.net/de_DE/sdk.js";
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));

        // Button click handler
        $('#ppv-facebook-login-btn').on('click', function() {
            const $btn = $(this);

            FB.login(function(response) {
                if (response.authResponse) {
                    handleFacebookCallback(response.authResponse, $btn);
                } else {
                    showAlert(ppvLogin?.strings?.facebook_cancelled || 'Facebook Login abgebrochen', 'error');
                }
            }, {scope: 'public_profile,email'});
        });
    }

    /**
     * Handle Facebook OAuth Callback
     */
    function handleFacebookCallback(authResponse, $btn) {
        const accessToken = authResponse.accessToken;

        // Show loading
        $btn.prop('disabled', true).html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></path></svg><span>' + (ppvLogin?.strings?.login_loading || 'Anmelden...') + '</span>');

        // Send to backend
        $.ajax({
            url: ppvLogin.ajaxurl,
            type: 'POST',
            data: {
                action: 'ppv_facebook_login',
                nonce: ppvLogin.nonce,
                access_token: accessToken,
                device_fingerprint: getDeviceFingerprint()
            },
            success: function(res) {
                if (res.success) {
                    showAlert(res.data.message, 'success');
                    setTimeout(function() {
                        window.location.href = getFinalRedirectUrl(res.data.redirect);
                    }, 1000);
                } else {
                    showAlert(res.data.message, 'error');
                    resetFacebookButton($btn);
                }
            },
            error: function() {
                showAlert(ppvLogin?.strings?.connection_error || 'Kapcsolódási hiba. Kérjük, próbálja újra.', 'error');
                resetFacebookButton($btn);
            }
        });
    }

    /**
     * Reset Facebook Button
     */
    function resetFacebookButton($btn) {
        $btn.prop('disabled', false).html(`
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047v-2.66c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.971H15.83c-1.49 0-1.955.93-1.955 1.886v2.264h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z" fill="#FFFFFF"/>
            </svg>
            <span>Facebook</span>
        `);
    }

    /**
     * Initialize TikTok Login
     */
    function initTikTokLogin() {
        const clientKey = ppvLogin.tiktok_client_key;

        if (!clientKey) {
            ppvLog.warn('TikTok Client Key not configured');
            $('#ppv-tiktok-login-btn').prop('disabled', true).css('opacity', '0.5');
            return;
        }

        // Button click handler
        $('#ppv-tiktok-login-btn').on('click', function() {
            const redirectUri = encodeURIComponent(window.location.origin + '/login');
            const state = Math.random().toString(36).substring(7);
            const scope = 'user.info.basic';

            // Store state in sessionStorage for verification
            sessionStorage.setItem('tiktok_oauth_state', state);

            // Redirect to TikTok OAuth
            const authUrl = `https://www.tiktok.com/auth/authorize/` +
                `?client_key=${clientKey}` +
                `&scope=${scope}` +
                `&response_type=code` +
                `&redirect_uri=${redirectUri}` +
                `&state=${state}`;

            window.location.href = authUrl;
        });

        // Check for TikTok OAuth callback
        checkTikTokCallback();
    }

    /**
     * Check for TikTok OAuth Callback
     */
    function checkTikTokCallback() {
        const urlParams = new URLSearchParams(window.location.search);
        const code = urlParams.get('code');
        const state = urlParams.get('state');
        const storedState = sessionStorage.getItem('tiktok_oauth_state');

        if (code && state && state === storedState) {
            // Clear state
            sessionStorage.removeItem('tiktok_oauth_state');

            // Show loading
            const $btn = $('#ppv-tiktok-login-btn');
            $btn.prop('disabled', true).html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></path></svg><span>' + (ppvLogin?.strings?.login_loading || 'Anmelden...') + '</span>');

            // Send to backend
            $.ajax({
                url: ppvLogin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ppv_tiktok_login',
                    nonce: ppvLogin.nonce,
                    code: code,
                    device_fingerprint: getDeviceFingerprint()
                },
                success: function(res) {
                    if (res.success) {
                        showAlert(res.data.message, 'success');
                        setTimeout(function() {
                            window.location.href = getFinalRedirectUrl(res.data.redirect);
                        }, 1000);
                    } else {
                        showAlert(res.data.message, 'error');
                        resetTikTokButton($btn);
                    }
                },
                error: function() {
                    showAlert(ppvLogin?.strings?.connection_error || 'Kapcsolódási hiba. Kérjük, próbálja újra.', 'error');
                    resetTikTokButton($btn);
                }
            });
        }
    }

    /**
     * Reset TikTok Button
     */
    function resetTikTokButton($btn) {
        $btn.prop('disabled', false).html(`
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="#000000"/>
                <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="#EE1D52"/>
                <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" fill="#69C9D0"/>
            </svg>
            <span>TikTok</span>
        `);
    }

    /**
     * 🍎 Initialize Apple Sign In
     */
    function initAppleLogin() {
        const clientId = ppvLogin.apple_client_id;
        const redirectUri = ppvLogin.apple_redirect_uri || window.location.origin + '/login';

        if (!clientId) {
            ppvLog.warn('🍎 Apple Client ID not configured');
            $('#ppv-apple-login-btn').prop('disabled', true).css('opacity', '0.5');
            return;
        }

        // Load Apple JS SDK if not already loaded
        if (typeof AppleID === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js';
            script.onload = function() {
                initAppleAuth(clientId, redirectUri);
            };
            document.head.appendChild(script);
        } else {
            initAppleAuth(clientId, redirectUri);
        }
    }

    /**
     * 🍎 Initialize Apple Auth after SDK loads
     */
    function initAppleAuth(clientId, redirectUri) {
        try {
            AppleID.auth.init({
                clientId: clientId,
                scope: 'name email',
                redirectURI: redirectUri,
                usePopup: true
            });
        } catch (error) {
            ppvLog.error('🍎 Apple auth init error:', error);
        }

        // Button click handler
        $('#ppv-apple-login-btn').on('click', async function() {
            const $btn = $(this);

            try {
                // Show loading
                $btn.prop('disabled', true).html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></path></svg><span>' + (ppvLogin?.strings?.login_loading || 'Anmelden...') + '</span>');

                // Trigger Apple Sign In
                const response = await AppleID.auth.signIn();

                // Send to backend
                handleAppleResponse(response, $btn);

            } catch (error) {
                ppvLog.error('🍎 Apple Sign In error:', error);
                if (error.error !== 'popup_closed_by_user') {
                    showAlert(ppvLogin?.strings?.apple_login_failed || 'Apple bejelentkezés sikertelen', 'error');
                }
                resetAppleButton($btn);
            }
        });
    }

    /**
     * 🍎 Handle Apple Sign In Response
     */
    function handleAppleResponse(response, $btn) {
        if (!response.authorization || !response.authorization.id_token) {
            showAlert(ppvLogin?.strings?.apple_login_failed || 'Apple bejelentkezés sikertelen', 'error');
            resetAppleButton($btn);
            return;
        }

        // Prepare data - user info is only available on first sign in
        const data = {
            action: 'ppv_apple_login',
            nonce: ppvLogin.nonce,
            id_token: response.authorization.id_token,
            device_fingerprint: getDeviceFingerprint()
        };

        // Add user info if available (first sign in only)
        if (response.user) {
            data.user = JSON.stringify(response.user);
        }

        $.ajax({
            url: ppvLogin.ajaxurl,
            type: 'POST',
            data: data,
            success: function(res) {
                if (res.success) {
                    showAlert(res.data.message, 'success');
                    setTimeout(function() {
                        window.location.href = getFinalRedirectUrl(res.data.redirect);
                    }, 1000);
                } else {
                    showAlert(res.data.message || 'Apple bejelentkezés sikertelen', 'error');
                    resetAppleButton($btn);
                }
            },
            error: function() {
                showAlert(ppvLogin?.strings?.connection_error || 'Kapcsolódási hiba. Kérjük, próbálja újra.', 'error');
                resetAppleButton($btn);
            }
        });
    }

    /**
     * 🍎 Reset Apple Button
     */
    function resetAppleButton($btn) {
        $btn.prop('disabled', false).html(`
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/>
            </svg>
            <span>Apple</span>
        `);
    }

   /**
     * Language Switcher (Dashboard style)
     */
    function initLanguageSwitcher() {
        $('.ppv-lang-btn').on('click', function() {
            const $btn = $(this);
            const lang = $btn.data('lang');

            if ($btn.hasClass('active')) return;

            // Set cookie (1 year expiry)
            const maxAge = 60 * 60 * 24 * 365;
            document.cookie = `ppv_lang=${lang}; path=/; max-age=${maxAge}; SameSite=Lax`;

            // Set localStorage (fallback)
            localStorage.setItem('ppv_lang', lang);

            // Reload with URL parameter
            const url = new URL(window.location.href);
            url.searchParams.set('lang', lang);
            window.location.href = url.toString();
        });
    }



})(jQuery);
