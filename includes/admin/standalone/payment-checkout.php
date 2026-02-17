<?php
/**
 * Payment Checkout Page for PunktePass Händler Subscription
 *
 * Route: /checkout or /zahlung
 *
 * Price: 39€ net + 19% VAT = 46.41€ gross/month
 * Payment option: PayPal
 */

if (!defined('ABSPATH')) {
    require_once dirname(__FILE__, 6) . '/wp-load.php';
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in (support both vendor and repair admin sessions)
$store_id = $_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_repair_store_id'] ?? 0;
$redirect_back = !empty($_SESSION['ppv_repair_store_id']) ? '/formular/admin' : '/handler_dashboard';
if (!$store_id) {
    wp_redirect('/formular/admin/login?redirect=' . urlencode('/checkout'));
    exit;
}

// Get store info
global $wpdb;
$store = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
    $store_id
));

if (!$store) {
    wp_redirect($redirect_back);
    exit;
}

// Check if already has active subscription
if ($store->subscription_status === 'active' && $store->repair_premium && strtotime($store->subscription_expires_at) > time()) {
    wp_redirect($redirect_back . '?notice=already_active');
    exit;
}

// Price - VAT only for German stores
$price_net = 39.00;
$store_country = strtoupper($store->country ?? 'DE');
$is_domestic = ($store_country === 'DE');
$vat_rate = $is_domestic ? 0.19 : 0.00;
$vat = round($price_net * $vat_rate, 2);
$price_gross = round($price_net + $vat, 2);

// Language - PPV_Lang is already detected at init priority 1
$lang = PPV_Lang::$active ?: 'en';
$paypal_locales = ['de' => 'de_DE', 'en' => 'en_US', 'hu' => 'hu_HU', 'ro' => 'ro_RO', 'it' => 'it_IT'];
$paypal_locale = $paypal_locales[$lang] ?? 'en_US';

// Number format per locale
$dec_sep = ($lang === 'en') ? '.' : ',';
$thou_sep = ($lang === 'en') ? ',' : '.';

?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(PPV_Lang::t('checkout_page_title')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .checkout-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .checkout-header {
            text-align: center;
            color: #fff;
            margin-bottom: 30px;
        }
        .checkout-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }
        .checkout-header p {
            opacity: 0.9;
            font-size: 16px;
        }
        .checkout-card {
            background: #fff;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .price-summary {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
            text-align: center;
        }
        .price-amount {
            font-size: 48px;
            font-weight: 700;
            color: #667eea;
        }
        .price-period {
            font-size: 18px;
            color: #6b7280;
        }
        .price-details {
            margin-top: 12px;
            font-size: 14px;
            color: #9ca3af;
        }
        .features-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 32px;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #374151;
        }
        .feature-item i {
            color: #10b981;
            font-size: 18px;
        }
        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .payment-option {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .payment-option:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .payment-option.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .payment-option-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .payment-option-header i {
            font-size: 28px;
            color: #667eea;
        }
        .payment-option-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
        }
        .payment-option p {
            font-size: 14px;
            color: #6b7280;
            margin-left: 40px;
        }
        .payment-option input[type="radio"] {
            display: none;
        }
        .checkout-btn {
            width: 100%;
            padding: 16px 32px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 24px;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        .checkout-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .checkout-btn .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        .checkout-btn.loading .spinner {
            display: block;
        }
        .checkout-btn.loading .btn-text {
            display: none;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .terms {
            margin-top: 20px;
            font-size: 13px;
            color: #6b7280;
            text-align: center;
        }
        .terms a {
            color: #667eea;
            text-decoration: none;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            opacity: 0.9;
        }
        .back-link:hover {
            opacity: 1;
        }
        .success-message, .error-message {
            display: none;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .success-message {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .cancellation-note {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 16px;
            font-size: 13px;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cancellation-note i {
            font-size: 18px;
        }
        /* Promo code section */
        .promo-section {
            margin-top: 24px;
            padding: 20px;
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            background: #fafafa;
        }
        .promo-section h4 {
            font-size: 14px;
            color: #374151;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .promo-input-row {
            display: flex;
            gap: 8px;
        }
        .promo-input-row input {
            flex: 1;
            padding: 10px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            outline: none;
            transition: border-color 0.2s;
        }
        .promo-input-row input:focus {
            border-color: #667eea;
        }
        .promo-input-row input.valid {
            border-color: #10b981;
            background: #ecfdf5;
        }
        .promo-input-row input.invalid {
            border-color: #ef4444;
            background: #fef2f2;
        }
        .promo-btn {
            padding: 10px 20px;
            background: #667eea;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s;
        }
        .promo-btn:hover { background: #5a6fd6; }
        .promo-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        .promo-result {
            margin-top: 10px;
            font-size: 13px;
            display: none;
        }
        .promo-result.success {
            color: #065f46;
            background: #d1fae5;
            padding: 10px 14px;
            border-radius: 8px;
            display: block;
        }
        .promo-result.error {
            color: #991b1b;
            display: block;
        }
        .promo-activate-btn {
            width: 100%;
            padding: 14px 32px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 12px;
            display: none;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .promo-activate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }
        .promo-activate-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        @media (max-width: 600px) {
            .features-list {
                grid-template-columns: 1fr;
            }
            .checkout-card {
                padding: 20px;
            }
            .price-amount {
                font-size: 36px;
            }
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="checkout-header">
            <h1><?php echo esc_html(PPV_Lang::t('checkout_title')); ?></h1>
            <p><?php echo esc_html(sprintf(PPV_Lang::t('checkout_activate_for'), $store->store_name)); ?></p>
        </div>

        <div class="checkout-card">
            <div id="success-message" class="success-message"></div>
            <div id="error-message" class="error-message"></div>

            <div class="price-summary">
                <div class="price-amount"><?php echo number_format($price_gross, 2, $dec_sep, $thou_sep); ?> €</div>
                <div class="price-period"><?php echo esc_html(PPV_Lang::t('checkout_per_month')); ?><?php if ($is_domestic): ?> <?php echo esc_html(PPV_Lang::t('checkout_incl_vat')); ?><?php else: ?> <?php echo esc_html(PPV_Lang::t('checkout_net_no_vat')); ?><?php endif; ?></div>
                <?php if ($is_domestic): ?>
                    <div class="price-details"><?php echo esc_html(sprintf(PPV_Lang::t('checkout_net_plus_vat'), number_format($price_net, 2, $dec_sep, $thou_sep), number_format($vat, 2, $dec_sep, $thou_sep))); ?></div>
                <?php else: ?>
                    <div class="price-details"><?php echo esc_html(PPV_Lang::t('checkout_no_vat_note')); ?></div>
                <?php endif; ?>
            </div>

            <div class="features-list">
                <div class="feature-item"><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('checkout_unlimited_customers')); ?></div>
                <div class="feature-item"><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('checkout_bonus_system')); ?></div>
                <div class="feature-item"><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('checkout_qr_scanner')); ?></div>
                <div class="feature-item"><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('checkout_repair_form')); ?></div>
                <div class="feature-item"><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('checkout_invoices')); ?></div>
                <div class="feature-item"><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('checkout_customer_mgmt')); ?></div>
                <div class="feature-item"><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('checkout_email_notifications')); ?></div>
                <div class="feature-item"><i class="ri-check-line"></i> <?php echo esc_html(PPV_Lang::t('checkout_support')); ?></div>
            </div>

            <div class="cancellation-note">
                <i class="ri-information-line"></i>
                <span><?php echo esc_html(PPV_Lang::t('checkout_cancel_note')); ?></span>
            </div>

            <h3 style="margin: 24px 0 16px; font-size: 16px; color: #374151;"><?php echo esc_html(PPV_Lang::t('checkout_pay_via_paypal')); ?></h3>

            <div class="payment-methods">
                <div class="payment-option selected" data-method="paypal">
                    <div class="payment-option-header">
                        <i class="ri-paypal-fill"></i>
                        <h3>PayPal</h3>
                    </div>
                    <p><?php echo esc_html(PPV_Lang::t('checkout_paypal_desc')); ?></p>
                    <div id="paypal-button-container" style="margin-top: 16px;"></div>
                </div>
            </div>

            <!-- Promo Code Section -->
            <div class="promo-section">
                <h4><i class="ri-coupon-3-line"></i> <?php echo esc_html(PPV_Lang::t('checkout_promo_title')); ?></h4>
                <div class="promo-input-row">
                    <input type="text" id="promo-code-input" placeholder="<?php echo esc_attr(PPV_Lang::t('checkout_promo_placeholder')); ?>" maxlength="30" autocomplete="off">
                    <button type="button" class="promo-btn" id="promo-validate-btn"><?php echo esc_html(PPV_Lang::t('checkout_promo_redeem')); ?></button>
                </div>
                <div class="promo-result" id="promo-result"></div>
                <button type="button" class="promo-activate-btn" id="promo-activate-btn">
                    <i class="ri-gift-line"></i> <span id="promo-activate-text"><?php echo esc_html(PPV_Lang::t('checkout_promo_activate')); ?></span>
                </button>
            </div>

            <div class="terms">
                <?php echo esc_html(PPV_Lang::t('checkout_terms')); ?>
                <a href="/agb" target="_blank"><?php echo esc_html(PPV_Lang::t('checkout_terms_link')); ?></a> <?php echo esc_html(PPV_Lang::t('checkout_terms_and')); ?>
                <a href="/datenschutz" target="_blank"><?php echo esc_html(PPV_Lang::t('checkout_privacy_link')); ?></a>.
            </div>
        </div>

        <a href="<?php echo esc_url($redirect_back); ?>" class="back-link">
            <i class="ri-arrow-left-line"></i> <?php echo esc_html(PPV_Lang::t('checkout_back')); ?>
        </a>
    </div>

    <!-- Promo Code JS -->
    <script>
    (function() {
        var T = <?php echo json_encode([
            'redeem'        => PPV_Lang::t('checkout_promo_redeem'),
            'activate'      => PPV_Lang::t('checkout_promo_activate'),
            'months_free'   => PPV_Lang::t('checkout_promo_months_free'),
            'activate_m'    => PPV_Lang::t('checkout_promo_activate_months'),
            'activating'    => PPV_Lang::t('checkout_promo_activating'),
            'success'       => PPV_Lang::t('checkout_promo_success'),
            'invalid'       => PPV_Lang::t('checkout_promo_invalid'),
            'error'         => PPV_Lang::t('checkout_promo_error'),
            'network_error' => PPV_Lang::t('checkout_promo_network_error'),
            'check_error'   => PPV_Lang::t('checkout_promo_check_error'),
        ]); ?>;
        var activeLang = <?php echo json_encode($lang); ?>;

        const promoInput = document.getElementById('promo-code-input');
        const promoBtn = document.getElementById('promo-validate-btn');
        const promoResult = document.getElementById('promo-result');
        const promoActivateBtn = document.getElementById('promo-activate-btn');
        const promoActivateText = document.getElementById('promo-activate-text');
        let validatedCode = '';

        function fmt(tpl, vals) {
            var r = tpl;
            for (var i = 0; i < vals.length; i++) r = r.replace('%d', vals[i]).replace('%s', vals[i]);
            return r;
        }

        promoInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                promoBtn.click();
            }
        });

        promoBtn.addEventListener('click', async function() {
            const code = promoInput.value.trim();
            if (!code) return;

            promoBtn.disabled = true;
            promoBtn.textContent = '...';
            promoResult.className = 'promo-result';
            promoResult.style.display = 'none';
            promoActivateBtn.style.display = 'none';
            validatedCode = '';

            try {
                const res = await fetch('/wp-json/punktepass/v1/promo/validate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ code: code })
                });
                const data = await res.json();

                if (data.valid) {
                    promoInput.className = 'valid';
                    promoResult.className = 'promo-result success';
                    promoResult.innerHTML = '<i class="ri-check-line"></i> <strong>' + fmt(T.months_free, [data.months]) + '</strong> ' + (data.desc || '');
                    promoActivateText.textContent = fmt(T.activate_m, [data.months]);
                    promoActivateBtn.style.display = 'flex';
                    validatedCode = code;
                } else {
                    promoInput.className = 'invalid';
                    promoResult.className = 'promo-result error';
                    promoResult.textContent = data.error || T.invalid;
                }
            } catch (err) {
                promoResult.className = 'promo-result error';
                promoResult.textContent = T.check_error;
            }

            promoBtn.disabled = false;
            promoBtn.textContent = T.redeem;
        });

        promoActivateBtn.addEventListener('click', async function() {
            if (!validatedCode) return;

            promoActivateBtn.disabled = true;
            promoActivateText.textContent = T.activating;

            try {
                const res = await fetch('/wp-json/punktepass/v1/promo/redeem', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ code: validatedCode })
                });
                const data = await res.json();

                if (data.success) {
                    var dateStr = new Date(data.expires).toLocaleDateString(activeLang === 'en' ? 'en-US' : activeLang + '-' + activeLang.toUpperCase());
                    promoResult.className = 'promo-result success';
                    promoResult.innerHTML = '<strong>' + fmt(T.success, [data.months, dateStr]) + '</strong>';
                    promoActivateBtn.style.display = 'none';

                    setTimeout(function() {
                        window.location.href = '<?php echo esc_js($redirect_back); ?>?payment=success&method=promo';
                    }, 2000);
                } else {
                    promoResult.className = 'promo-result error';
                    promoResult.textContent = data.error || T.error;
                    promoActivateBtn.disabled = false;
                    promoActivateText.textContent = T.activate;
                }
            } catch (err) {
                promoResult.className = 'promo-result error';
                promoResult.textContent = T.network_error;
                promoActivateBtn.disabled = false;
                promoActivateText.textContent = T.activate;
            }
        });
    })();
    </script>

    <!-- PayPal SDK -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : 'ATvIpJv2JtjokY3p4OBWc8ZfcJE5wUXn9Lt65IDYUewAoCAg0wMb3thS1bTYTETjeVl41BAX2djkO8FA'; ?>&vault=true&intent=subscription&locale=<?php echo $paypal_locale; ?>" data-sdk-integration-source="button-factory"></script>

    <script>
        var TPaypal = <?php echo json_encode([
            'thanks'   => PPV_Lang::t('checkout_paypal_thanks'),
            'success'  => PPV_Lang::t('checkout_paypal_success'),
            'redirect' => PPV_Lang::t('checkout_paypal_redirect'),
            'error'    => PPV_Lang::t('checkout_paypal_error'),
        ]); ?>;
        const successMessage = document.getElementById('success-message');
        const errorMessage = document.getElementById('error-message');
        const storeId = <?php echo $store_id; ?>;
        const isDomestic = <?php echo $is_domestic ? 'true' : 'false'; ?>;
        const planId = '<?php
            if ($is_domestic) {
                echo defined('PAYPAL_PLAN_ID') ? PAYPAL_PLAN_ID : 'P-2AM08048AE701010RNGBQB6I';
            } else {
                echo defined('PAYPAL_PLAN_ID_NET') ? PAYPAL_PLAN_ID_NET : 'P-7KM18573T8357751DNGFSNLY';
            }
        ?>';

        // PayPal Button
        paypal.Buttons({
            style: {
                shape: 'rect',
                color: 'blue',
                layout: 'vertical',
                label: 'subscribe'
            },
            createSubscription: function(data, actions) {
                return actions.subscription.create({
                    plan_id: planId,
                    custom_id: 'store_' + storeId
                });
            },
            onApprove: async function(data, actions) {
                // Save subscription to database
                try {
                    const response = await fetch('/wp-json/punktepass/v1/paypal/activate', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            subscription_id: data.subscriptionID,
                            store_id: storeId
                        })
                    });

                    successMessage.innerHTML = `
                        <strong>${TPaypal.thanks}</strong><br>
                        ${TPaypal.success}<br>
                        Subscription ID: <code>${data.subscriptionID}</code><br><br>
                        ${TPaypal.redirect}
                    `;
                    successMessage.style.display = 'block';

                    setTimeout(() => {
                        window.location.href = '/handler_dashboard?payment=success&method=paypal';
                    }, 2000);
                } catch (error) {
                    // Even if API fails, redirect - webhook will handle it
                    window.location.href = '/handler_dashboard?payment=success&method=paypal&sub=' + data.subscriptionID;
                }
            },
            onError: function(err) {
                errorMessage.textContent = TPaypal.error;
                errorMessage.style.display = 'block';
            }
        }).render('#paypal-button-container');

    </script>
</body>
</html>
