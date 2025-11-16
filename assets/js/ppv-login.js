/**
 * PunktePass - Premium Login JavaScript
 * Google OAuth + Form Handling + Animations
 * Author: Erik Borota / PunktePass
 */

(function($) {
    'use strict';
    
    // Wait for DOM
    $(document).ready(function() {
        initLogin();
    });
    
    /**
     * Initialize Login System
     */
function initLogin() {
        initPasswordToggle();
        initFormValidation();
        initGoogleLogin();
        initFormSubmit();
        initLanguageSwitcher(); // ← ADD THIS!
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
                $btn.attr('aria-label', 'Passwort verstecken');
            } else {
                $input.attr('type', 'password');
                $eyeOpen.show();
                $eyeClosed.hide();
                $btn.attr('aria-label', 'Passwort anzeigen');
            }
        });
    }
    
    /**
     * Real-time Form Validation
     */
    function initFormValidation() {
        const $email = $('#ppv-email');
        const $password = $('#ppv-password');
        
        // Email validation
        $email.on('blur', function() {
            const email = $(this).val().trim();
            if (email && !isValidEmail(email)) {
                showFieldError($(this), 'Bitte geben Sie eine gültige Email-Adresse ein');
            } else {
                clearFieldError($(this));
            }
        });
        
        // Password validation
        $password.on('blur', function() {
            const password = $(this).val();
            if (password && password.length < 6) {
                showFieldError($(this), 'Passwort muss mindestens 6 Zeichen lang sein');
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
    function initGoogleLogin() {
        const clientId = ppvLogin.google_client_id;
        
        if (!clientId) {
            console.warn('Google Client ID not configured');
            return;
        }
        
        // Initialize Google Identity Services
        if (typeof google !== 'undefined' && google.accounts) {
            google.accounts.id.initialize({
                client_id: clientId,
                callback: handleGoogleCallback,
                auto_select: false,
                cancel_on_tap_outside: true
            });
        }
        
        // Manual button click handler
        $('#ppv-google-login-btn').on('click', function() {
            if (typeof google !== 'undefined' && google.accounts) {
                google.accounts.id.prompt();
            } else {
                showAlert('Google Login ist momentan nicht verfügbar', 'error');
            }
        });
    }
    
    /**
     * Handle Google OAuth Callback
     */
    function handleGoogleCallback(response) {
        if (!response.credential) {
            showAlert('Google Login fehlgeschlagen', 'error');
            return;
        }
        
        // Show loading
        const $btn = $('#ppv-google-login-btn');
        $btn.prop('disabled', true).html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></path></svg><span>Anmelden...</span>');
        
        // Send to backend
        $.ajax({
            url: ppvLogin.ajaxurl,
            type: 'POST',
            data: {
                action: 'ppv_google_login',
                nonce: ppvLogin.nonce,
                credential: response.credential
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
                showAlert('Verbindungsfehler. Bitte versuchen Sie es erneut.', 'error');
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
            <span>Mit Google anmelden</span>
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
                showAlert('Bitte füllen Sie alle Felder aus', 'error');
                return;
            }
            
            if (!isValidEmail(email)) {
                showAlert('Bitte geben Sie eine gültige Email-Adresse ein', 'error');
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
                    remember: remember
                },
                success: function(res) {
                    if (res.success) {
                        showAlert(res.data.message, 'success');
                        
                        // Add success animation
                        $form.css({
                            'opacity': '0.5',
                            'pointer-events': 'none'
                        });
                        
                        // Redirect
                        setTimeout(function() {
                            window.location.href = res.data.redirect;
                        }, 1000);
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
                    showAlert('Verbindungsfehler. Bitte versuchen Sie es erneut.', 'error');
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