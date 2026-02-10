<?php
/**
 * Payment Checkout Page for PunktePass Händler Subscription
 *
 * Route: /checkout or /zahlung
 *
 * Price: 39€ net + 19% VAT = 46.41€ gross/month
 * Payment options: PayPal, Banküberweisung
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

// Bank details
$bank_name = get_option('ppv_bank_name', 'Kreis- und Stadtsparkasse Dillingen a.d. Donau');
$bank_iban = get_option('ppv_bank_iban', 'DE57 7225 1520 0010 3435 55');
$bank_bic = get_option('ppv_bank_bic', 'BYLADEM1DLG');
$bank_holder = get_option('ppv_bank_account_holder', 'Erik Borota');

// Reference number
$reference = 'PP-' . str_pad($store_id, 5, '0', STR_PAD_LEFT) . '-' . date('Ym');

// Price - VAT only for German stores
$price_net = 39.00;
$store_country = strtoupper($store->country ?? 'DE');
$is_domestic = ($store_country === 'DE');
$vat_rate = $is_domestic ? 0.19 : 0.00;
$vat = round($price_net * $vat_rate, 2);
$price_gross = round($price_net + $vat, 2);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abo abschließen - PunktePass</title>
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
        .bank-details {
            display: none;
            margin-top: 16px;
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
            margin-left: 40px;
        }
        .bank-details.show {
            display: block;
        }
        .bank-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        .bank-row:last-child {
            border-bottom: none;
        }
        .bank-row label {
            color: #6b7280;
        }
        .bank-row span {
            font-weight: 500;
            color: #1f2937;
            font-family: monospace;
        }
        .copy-btn {
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            margin-left: 8px;
            font-size: 14px;
        }
        .copy-btn:hover {
            color: #5a67d8;
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
            <h1>PunktePass Händler-Abo</h1>
            <p>Aktivieren Sie Ihr Abo für <?php echo esc_html($store->store_name); ?></p>
        </div>

        <div class="checkout-card">
            <div id="success-message" class="success-message"></div>
            <div id="error-message" class="error-message"></div>

            <div class="price-summary">
                <div class="price-amount"><?php echo number_format($price_gross, 2, ',', '.'); ?> €</div>
                <div class="price-period">pro Monat<?php if ($is_domestic): ?> (inkl. 19% MwSt)<?php else: ?> (netto, ohne MwSt)<?php endif; ?></div>
                <?php if ($is_domestic): ?>
                    <div class="price-details"><?php echo number_format($price_net, 2, ',', '.'); ?> € netto + <?php echo number_format($vat, 2, ',', '.'); ?> € MwSt</div>
                <?php else: ?>
                    <div class="price-details">Kein MwSt-Aufschlag für Unternehmen außerhalb Deutschlands</div>
                <?php endif; ?>
            </div>

            <div class="features-list">
                <div class="feature-item"><i class="ri-check-line"></i> Unbegrenzte Kunden</div>
                <div class="feature-item"><i class="ri-check-line"></i> Bonuspunkte-System</div>
                <div class="feature-item"><i class="ri-check-line"></i> QR-Code Scanner</div>
                <div class="feature-item"><i class="ri-check-line"></i> Reparaturformular</div>
                <div class="feature-item"><i class="ri-check-line"></i> Rechnungen erstellen</div>
                <div class="feature-item"><i class="ri-check-line"></i> Kundenverwaltung</div>
                <div class="feature-item"><i class="ri-check-line"></i> E-Mail Benachrichtigungen</div>
                <div class="feature-item"><i class="ri-check-line"></i> Support</div>
            </div>

            <div class="cancellation-note">
                <i class="ri-information-line"></i>
                <span>Monatlich kündbar - Sie können Ihr Abo jederzeit zum Monatsende kündigen.</span>
            </div>

            <h3 style="margin: 24px 0 16px; font-size: 16px; color: #374151;">Zahlungsmethode wählen</h3>

            <div class="payment-methods">
                <div class="payment-option selected" data-method="paypal">
                    <div class="payment-option-header">
                        <i class="ri-paypal-fill"></i>
                        <h3>PayPal</h3>
                    </div>
                    <p>Automatische monatliche Abbuchung über PayPal</p>
                    <div id="paypal-button-container" style="margin-top: 16px;"></div>
                </div>

                <div class="payment-option" data-method="bank_transfer" onclick="selectBankTransfer()">
                    <div class="payment-option-header">
                        <i class="ri-bank-line"></i>
                        <h3>Banküberweisung</h3>
                    </div>
                    <p>Manuelle Überweisung - Aktivierung nach Zahlungseingang (1-2 Werktage)</p>
                    <div class="bank-details" id="bank-details">
                        <div class="bank-row">
                            <label>Empfänger</label>
                            <span><?php echo esc_html($bank_holder); ?></span>
                        </div>
                        <div class="bank-row">
                            <label>IBAN</label>
                            <span id="iban-value"><?php echo esc_html($bank_iban); ?></span>
                            <button type="button" class="copy-btn" onclick="event.stopPropagation(); copyToClipboard('<?php echo esc_js(str_replace(' ', '', $bank_iban)); ?>')">
                                <i class="ri-file-copy-line"></i>
                            </button>
                        </div>
                        <div class="bank-row">
                            <label>BIC</label>
                            <span><?php echo esc_html($bank_bic); ?></span>
                        </div>
                        <div class="bank-row">
                            <label>Bank</label>
                            <span><?php echo esc_html($bank_name); ?></span>
                        </div>
                        <div class="bank-row">
                            <label>Verwendungszweck</label>
                            <span id="reference-value"><?php echo esc_html($reference); ?></span>
                            <button type="button" class="copy-btn" onclick="event.stopPropagation(); copyToClipboard('<?php echo esc_js($reference); ?>')">
                                <i class="ri-file-copy-line"></i>
                            </button>
                        </div>
                        <div class="bank-row">
                            <label>Betrag</label>
                            <span><?php echo number_format($price_gross, 2, ',', '.'); ?> €</span>
                        </div>
                        <button type="button" class="checkout-btn" id="bank-transfer-btn" style="margin-top: 16px;" onclick="event.stopPropagation(); requestBankTransfer()">
                            <span class="spinner" id="bank-spinner"></span>
                            <span class="btn-text" id="bank-btn-text"><i class="ri-mail-send-line"></i> Bankdaten per E-Mail senden</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="terms">
                Mit dem Abschluss akzeptieren Sie unsere
                <a href="/agb" target="_blank">AGB</a> und
                <a href="/datenschutz" target="_blank">Datenschutzerklärung</a>.
            </div>
        </div>

        <a href="/handler_dashboard" class="back-link">
            <i class="ri-arrow-left-line"></i> Zurück zum Dashboard
        </a>
    </div>

    <!-- PayPal SDK (Live) -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : 'ATvIpJv2JtjokY3p4OBWc8ZfcJE5wUXn9Lt65IDYUewAoCAg0wMb3thS1bTYTETjeVl41BAX2djkO8FA'; ?>&vault=true&intent=subscription&locale=de_DE" data-sdk-integration-source="button-factory"></script>

    <script>
        const bankDetails = document.getElementById('bank-details');
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
                        <strong>Vielen Dank!</strong><br>
                        Ihr PayPal-Abo wurde erfolgreich aktiviert.<br>
                        Subscription ID: <code>${data.subscriptionID}</code><br><br>
                        Sie werden gleich weitergeleitet...
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
                errorMessage.textContent = 'PayPal Fehler: ' + (err.message || 'Bitte versuchen Sie es erneut.');
                errorMessage.style.display = 'block';
            }
        }).render('#paypal-button-container');

        // Bank Transfer selection
        function selectBankTransfer() {
            document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
            document.querySelector('[data-method="bank_transfer"]').classList.add('selected');
            bankDetails.classList.add('show');
        }

        // Request Bank Transfer
        async function requestBankTransfer() {
            const btn = document.getElementById('bank-transfer-btn');
            const spinner = document.getElementById('bank-spinner');
            const btnText = document.getElementById('bank-btn-text');

            spinner.style.display = 'block';
            btnText.style.display = 'none';
            btn.disabled = true;
            errorMessage.style.display = 'none';

            try {
                const response = await fetch('/wp-json/punktepass/v1/bank-transfer/request', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success) {
                    successMessage.innerHTML = `
                        <strong>Vielen Dank!</strong><br>
                        Bitte überweisen Sie <strong>${data.amount.toFixed(2).replace('.', ',')} €</strong>
                        mit dem Verwendungszweck <strong>${data.reference}</strong>.<br><br>
                        Eine E-Mail mit den Bankdaten wurde an Sie gesendet.<br>
                        Nach Zahlungseingang wird Ihr Abo innerhalb von 1-2 Werktagen aktiviert.
                    `;
                    successMessage.style.display = 'block';
                    btn.style.display = 'none';
                } else {
                    throw new Error(data.error || 'Request failed');
                }
            } catch (error) {
                errorMessage.textContent = error.message || 'Ein Fehler ist aufgetreten.';
                errorMessage.style.display = 'block';
                spinner.style.display = 'none';
                btnText.style.display = 'inline-flex';
                btn.disabled = false;
            }
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Kopiert!');
            });
        }
    </script>
</body>
</html>
