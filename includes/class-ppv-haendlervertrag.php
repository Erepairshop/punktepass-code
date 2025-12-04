<?php
/**
 * PPV Händlervertrag
 * Standalone contract page at /vertrag for dealers
 */

if (!defined('ABSPATH')) exit;

class PPV_Haendlervertrag {

    public static function hooks() {
        add_action('init', [__CLASS__, 'add_rewrite_rules']);
        add_action('template_redirect', [__CLASS__, 'handle_vertrag_page']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    /**
     * Add rewrite rule for /vertrag
     */
    public static function add_rewrite_rules() {
        add_rewrite_rule('^vertrag/?$', 'index.php?ppv_vertrag=1', 'top');
        add_rewrite_tag('%ppv_vertrag%', '1');
    }

    /**
     * Handle /vertrag page directly (no WordPress page needed)
     */
    public static function handle_vertrag_page() {
        if (get_query_var('ppv_vertrag') == 1) {
            self::render_page();
            exit;
        }
    }

    /**
     * Register REST API route for contract submission
     */
    public static function register_rest_routes() {
        register_rest_route('punktepass/v1', '/contract/submit', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_contract_submit'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle contract submission
     */
    public static function handle_contract_submit($request) {
        $data = $request->get_json_params();

        // Validate required fields
        $required = ['haendlername', 'adresse', 'plz', 'ort', 'ansprechpartner', 'email', 'telefon'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', "Feld '$field' ist erforderlich", ['status' => 400]);
            }
        }

        // Sanitize data
        $haendlername = sanitize_text_field($data['haendlername']);
        $adresse = sanitize_text_field($data['adresse']);
        $plz = sanitize_text_field($data['plz']);
        $ort = sanitize_text_field($data['ort']);
        $ansprechpartner = sanitize_text_field($data['ansprechpartner']);
        $email = sanitize_email($data['email']);
        $telefon = sanitize_text_field($data['telefon']);
        $steuernummer = sanitize_text_field($data['steuernummer'] ?? '');
        $imei = sanitize_text_field($data['imei'] ?? '');
        $datumHaendler = sanitize_text_field($data['datumHaendler'] ?? date('Y-m-d'));
        $zubehoer = is_array($data['zubehoer'] ?? []) ? implode(', ', $data['zubehoer']) : ($data['zubehoer'] ?? '');
        $zustand = sanitize_text_field($data['zustand'] ?? '');

        // Build email content
        $email_body = self::build_email_body($data);

        // Send to admin
        $admin_email = 'info@punktepass.de';
        $subject = "Neuer Händlervertrag - $haendlername";

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: PunktePass <noreply@punktepass.de>',
            "Reply-To: $ansprechpartner <$email>",
        ];

        $sent_admin = wp_mail($admin_email, $subject, $email_body, $headers);

        // Send copy to dealer
        $dealer_subject = "Ihr PunktePass Händlervertrag - Kopie";
        $dealer_headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: PunktePass <noreply@punktepass.de>',
        ];

        $sent_dealer = wp_mail($email, $dealer_subject, $email_body, $dealer_headers);

        if ($sent_admin || $sent_dealer) {
            return ['success' => true, 'message' => 'Vertrag erfolgreich gesendet'];
        }

        return new WP_Error('email_failed', 'E-Mail konnte nicht gesendet werden', ['status' => 500]);
    }

    /**
     * Build HTML email body
     */
    private static function build_email_body($data) {
        $haendlername = esc_html($data['haendlername']);
        $adresse = esc_html($data['adresse']);
        $plz = esc_html($data['plz']);
        $ort = esc_html($data['ort']);
        $ansprechpartner = esc_html($data['ansprechpartner']);
        $email = esc_html($data['email']);
        $telefon = esc_html($data['telefon']);
        $steuernummer = esc_html($data['steuernummer'] ?? '-');
        $imei = esc_html($data['imei'] ?? '-');
        $datumHaendler = esc_html($data['datumHaendler'] ?? date('Y-m-d'));
        $zubehoer = is_array($data['zubehoer'] ?? []) ? implode(', ', $data['zubehoer']) : esc_html($data['zubehoer'] ?? '-');
        $zustand = esc_html($data['zustand'] ?? '-');

        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
        .section { margin-bottom: 25px; }
        .section-title { font-size: 16px; font-weight: bold; color: #1a1a2e; border-bottom: 2px solid #00d4ff; padding-bottom: 8px; margin-bottom: 15px; }
        .field { margin-bottom: 10px; }
        .field-label { font-weight: bold; color: #666; }
        .field-value { color: #333; }
        .terms { background: #fff; padding: 20px; border-radius: 8px; margin-top: 20px; }
        .term { margin-bottom: 15px; }
        .term h4 { color: #1a1a2e; margin-bottom: 5px; }
        .term p { color: #555; font-size: 14px; margin: 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        .signature-note { background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>PunktePass Händlervertrag</h1>
        </div>
        <div class='content'>
            <div class='section'>
                <div class='section-title'>Anbieter (Dienstleister)</div>
                <p><strong>Erik Borota – PunktePass</strong><br>
                Adresse: Lauingen, Deutschland<br>
                E-Mail: info@punktepass.de<br>
                Telefon: +49 176 61860854<br>
                Steuernummer: 151/219/51051</p>
            </div>

            <div class='section'>
                <div class='section-title'>Händlerdaten</div>
                <div class='field'><span class='field-label'>Händlername:</span> <span class='field-value'>$haendlername</span></div>
                <div class='field'><span class='field-label'>Adresse:</span> <span class='field-value'>$adresse</span></div>
                <div class='field'><span class='field-label'>PLZ / Ort:</span> <span class='field-value'>$plz $ort</span></div>
                <div class='field'><span class='field-label'>Ansprechpartner:</span> <span class='field-value'>$ansprechpartner</span></div>
                <div class='field'><span class='field-label'>E-Mail:</span> <span class='field-value'>$email</span></div>
                <div class='field'><span class='field-label'>Telefon:</span> <span class='field-value'>$telefon</span></div>
                <div class='field'><span class='field-label'>Steuernummer:</span> <span class='field-value'>$steuernummer</span></div>
            </div>

            <div class='terms'>
                <div class='term'>
                    <h4>§1 Vertragslaufzeit</h4>
                    <p>Die Mindestvertragslaufzeit beträgt 6 Monate und beginnt nach Ablauf der 30-tägigen kostenlosen Testphase. Der Vertrag endet automatisch nach Ablauf der vereinbarten Laufzeit. Eine Verlängerung erfolgt nur auf ausdrücklichen Wunsch des Händlers – es gibt keine automatische Verlängerung.</p>
                </div>
                <div class='term'>
                    <h4>§2 Testphase (0 € – 30 Tage)</h4>
                    <p>Der Händler erhält eine 30-tägige Testphase kostenlos. Erfolgt bis zum Ende dieser Phase keine Kündigung, tritt der 6-Monatsvertrag in Kraft.</p>
                </div>
                <div class='term'>
                    <h4>§3 Bereitgestelltes Gerät – Leihgabe</h4>
                    <p>Der Händler erhält für die Laufzeit ein Gerät zur Nutzung des PunktePass Systems: Xiaomi Redmi A5 – 4G – 64GB (Neu). Das Gerät bleibt Eigentum des Anbieters.</p>
                </div>
                <div class='term'>
                    <h4>§4 Gebühren & Zahlung</h4>
                    <p>Monatliche Gebühr nach Testphase: 30 € netto. Zahlung erfolgt wahlweise per Banküberweisung oder PayPal innerhalb von 7 Tagen nach Rechnungserhalt.</p>
                </div>
                <div class='term'>
                    <h4>§5 Verlängerungsoption</h4>
                    <p>Der Händler kann den Vertrag vor Ablauf freiwillig um weitere 6 Monate verlängern. Eine automatische Verlängerung findet nicht statt.</p>
                </div>
            </div>

            <div class='section' style='margin-top: 25px;'>
                <div class='section-title'>Übergabeprotokoll Gerät</div>
                <div class='field'><span class='field-label'>Gerät:</span> <span class='field-value'>Xiaomi Redmi A5 – 4G – 64GB</span></div>
                <div class='field'><span class='field-label'>IMEI:</span> <span class='field-value'>$imei</span></div>
                <div class='field'><span class='field-label'>Zubehör:</span> <span class='field-value'>$zubehoer</span></div>
                <div class='field'><span class='field-label'>Zustand:</span> <span class='field-value'>$zustand</span></div>
            </div>

            <div class='signature-note'>
                <strong>Unterschrift Händler:</strong> Digital unterzeichnet am $datumHaendler<br>
                <small>Die digitale Unterschrift wurde online erfasst.</small>
            </div>
        </div>
        <div class='footer'>
            <p>© " . date('Y') . " PunktePass | info@punktepass.de</p>
        </div>
    </div>
</body>
</html>";
    }

    /**
     * Render the contract page
     */
    public static function render_page() {
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PunktePass Händlervertrag</title>
    <meta name="description" content="PunktePass Händlervertrag - Werden Sie Partner und bieten Sie Ihren Kunden ein digitales Bonussystem.">
    <link rel="icon" href="<?php echo PPV_PLUGIN_URL; ?>assets/img/favicon.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .contract-container {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .contract-header {
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            color: #fff;
            padding: 40px;
            text-align: center;
        }

        .contract-header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .contract-header .subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: #fff;
            border-radius: 16px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: #00d4ff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .contract-body {
            padding: 40px;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            background: linear-gradient(135deg, #1a1a2e 0%, #2d3a4f 100%);
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 1.1rem;
        }

        .provider-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #00d4ff;
        }

        .provider-info p {
            margin-bottom: 8px;
            color: #555;
        }

        .provider-info strong {
            color: #1a1a2e;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .contract-header { padding: 25px; }
            .contract-header h1 { font-size: 1.6rem; }
            .contract-body { padding: 20px; }
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #1a1a2e;
            font-size: 0.95rem;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.1);
        }

        .terms-section {
            background: #fafafa;
            padding: 25px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .term {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .term:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .term h3 {
            color: #1a1a2e;
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .term p {
            color: #555;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .signature-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px dashed #e0e0e0;
        }

        .signature-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-top: 20px;
        }

        @media (max-width: 600px) {
            .signature-grid {
                grid-template-columns: 1fr;
            }
        }

        .signature-box {
            text-align: center;
        }

        .signature-box label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #1a1a2e;
        }

        .signature-canvas {
            width: 100%;
            height: 150px;
            border: 2px dashed #ccc;
            border-radius: 8px;
            background: #fafafa;
            cursor: crosshair;
            touch-action: none;
        }

        .signature-canvas.signing {
            border-color: #00d4ff;
            background: #fff;
        }

        .signature-actions {
            margin-top: 10px;
        }

        .btn-clear {
            background: #ff6b6b;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: background 0.3s;
        }

        .btn-clear:hover {
            background: #ee5a5a;
        }

        .date-input {
            margin-top: 15px;
        }

        .date-input input {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .appendix {
            margin-top: 40px;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }

        .appendix h2 {
            color: #1a1a2e;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #00d4ff;
        }

        .device-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        @media (max-width: 600px) {
            .device-info {
                grid-template-columns: 1fr;
            }
        }

        .device-field {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .device-field label {
            font-weight: 600;
            color: #1a1a2e;
            min-width: 120px;
        }

        .device-field input {
            flex: 1;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
        }

        .checkbox-group {
            margin-top: 20px;
        }

        .checkbox-group h4 {
            color: #1a1a2e;
            margin-bottom: 10px;
        }

        .checkbox-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .checkbox-item input[type="checkbox"],
        .checkbox-item input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #00d4ff;
        }

        .submit-section {
            margin-top: 40px;
            text-align: center;
            padding: 30px;
            background: linear-gradient(135deg, #1a1a2e 0%, #2d3a4f 100%);
            border-radius: 12px;
        }

        .submit-section p {
            color: rgba(255,255,255,0.8);
            margin-bottom: 20px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            color: #fff;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 212, 255, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 212, 255, 0.4);
        }

        .btn-submit:disabled {
            background: #666;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-print {
            background: transparent;
            color: #fff;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            margin-left: 15px;
            transition: all 0.3s ease;
        }

        .btn-print:hover {
            border-color: #fff;
            background: rgba(255,255,255,0.1);
        }

        .success-message {
            display: none;
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }

        .success-message.show {
            display: block;
        }

        .error-message {
            display: none;
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }

        .error-message.show {
            display: block;
        }

        .agreement-checkbox {
            margin-top: 20px;
            padding: 15px;
            background: #fff3cd;
            border-radius: 8px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .agreement-checkbox input {
            margin-top: 3px;
            width: 20px;
            height: 20px;
            accent-color: #00d4ff;
        }

        .agreement-checkbox label {
            color: #856404;
            line-height: 1.5;
        }

        /* Print styles */
        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .contract-container {
                box-shadow: none;
                border-radius: 0;
            }

            .contract-header {
                background: #00d4ff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .submit-section {
                display: none;
            }

            .btn-clear {
                display: none;
            }

            .signature-canvas {
                border: 1px solid #000;
            }
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
            vertical-align: middle;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="contract-container">
        <div class="contract-header">
            <div class="logo">P</div>
            <h1>PunktePass Händlervertrag</h1>
            <p class="subtitle">Partnerschaft für digitale Kundenbindung</p>
        </div>

        <div class="contract-body">
            <!-- Provider Section -->
            <div class="section">
                <div class="section-title">Anbieter (Dienstleister)</div>
                <div class="provider-info">
                    <p><strong>Erik Borota – PunktePass</strong></p>
                    <p>Adresse: Lauingen, Deutschland</p>
                    <p>E-Mail: info@punktepass.de</p>
                    <p>Telefon: +49 176 61860854</p>
                    <p>Steuernummer: 151/219/51051</p>
                </div>
            </div>

            <!-- Dealer Data Section -->
            <div class="section">
                <div class="section-title">Händlerdaten (vom Kunden auszufüllen)</div>
                <form id="contractForm">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="haendlername">Händlername / Firmenname *</label>
                            <input type="text" id="haendlername" name="haendlername" required>
                        </div>
                        <div class="form-group full-width">
                            <label for="adresse">Adresse *</label>
                            <input type="text" id="adresse" name="adresse" required>
                        </div>
                        <div class="form-group">
                            <label for="plz">PLZ *</label>
                            <input type="text" id="plz" name="plz" required>
                        </div>
                        <div class="form-group">
                            <label for="ort">Ort *</label>
                            <input type="text" id="ort" name="ort" required>
                        </div>
                        <div class="form-group">
                            <label for="ansprechpartner">Ansprechpartner *</label>
                            <input type="text" id="ansprechpartner" name="ansprechpartner" required>
                        </div>
                        <div class="form-group">
                            <label for="telefon">Telefon *</label>
                            <input type="tel" id="telefon" name="telefon" required>
                        </div>
                        <div class="form-group">
                            <label for="email">E-Mail *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="steuernummer">Steuernummer (falls vorhanden)</label>
                            <input type="text" id="steuernummer" name="steuernummer">
                        </div>
                    </div>

                    <!-- Contract Terms -->
                    <div class="terms-section">
                        <div class="term">
                            <h3>§1 Vertragslaufzeit</h3>
                            <p>Die Mindestvertragslaufzeit beträgt <strong>6 Monate</strong> und beginnt nach Ablauf der 30-tägigen kostenlosen Testphase. Der Vertrag endet automatisch nach Ablauf der vereinbarten Laufzeit. Eine Verlängerung erfolgt nur auf ausdrücklichen Wunsch des Händlers – es gibt keine automatische Verlängerung.</p>
                        </div>

                        <div class="term">
                            <h3>§2 Testphase (0 € – 30 Tage)</h3>
                            <p>Der Händler erhält eine <strong>30-tägige Testphase kostenlos</strong>. Erfolgt bis zum Ende dieser Phase keine Kündigung, tritt der 6-Monatsvertrag in Kraft. Der Händler kann während oder nach der Testphase jederzeit kündigen.</p>
                        </div>

                        <div class="term">
                            <h3>§3 Bereitgestelltes Gerät – Leihgabe</h3>
                            <p>Der Händler erhält für die Laufzeit ein Gerät zur Nutzung des PunktePass Systems:<br>
                            <strong>Xiaomi Redmi A5 – 4G – 64GB (Neu)</strong><br>
                            Das Gerät bleibt Eigentum des Anbieters. Bei Vertragsende oder Kündigung ist es innerhalb von 7 Tagen zurückzugeben, ansonsten wird der Neuwert berechnet.</p>
                        </div>

                        <div class="term">
                            <h3>§4 Gebühren & Zahlung</h3>
                            <p>Monatliche Gebühr nach Testphase: <strong>30 € netto</strong><br>
                            Zahlung erfolgt wahlweise per Banküberweisung oder PayPal innerhalb von 7 Tagen nach Rechnungserhalt. Bei Zahlungsverzug darf der Anbieter den Systemzugang vorübergehend sperren.</p>
                        </div>

                        <div class="term">
                            <h3>§5 Verlängerungsoption</h3>
                            <p>Der Händler kann den Vertrag vor Ablauf <strong>freiwillig</strong> um weitere 6 Monate verlängern. Eine automatische Verlängerung findet <strong>nicht</strong> statt. Jede Verlängerung muss aktiv vom Händler angefordert werden.</p>
                        </div>
                    </div>

                    <!-- Signature Section -->
                    <div class="signature-section">
                        <div class="section-title">Unterschriften</div>
                        <div class="signature-grid">
                            <div class="signature-box">
                                <label>Unterschrift Händler *</label>
                                <canvas id="signatureHaendler" class="signature-canvas"></canvas>
                                <div class="signature-actions">
                                    <button type="button" class="btn-clear" onclick="clearSignature('signatureHaendler')">Löschen</button>
                                </div>
                                <div class="date-input">
                                    <label>Datum: </label>
                                    <input type="date" id="datumHaendler" name="datumHaendler" required>
                                </div>
                            </div>
                            <div class="signature-box">
                                <label>Unterschrift Anbieter</label>
                                <canvas id="signatureAnbieter" class="signature-canvas"></canvas>
                                <div class="signature-actions">
                                    <button type="button" class="btn-clear" onclick="clearSignature('signatureAnbieter')">Löschen</button>
                                </div>
                                <div class="date-input">
                                    <label>Datum: </label>
                                    <input type="date" id="datumAnbieter" name="datumAnbieter">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Appendix - Device Handover -->
                    <div class="appendix">
                        <h2>Anhang – Übergabeprotokoll Gerät</h2>
                        <div class="device-info">
                            <div class="device-field">
                                <label>Gerät:</label>
                                <span>Xiaomi Redmi A5 – 4G – 64GB</span>
                            </div>
                            <div class="device-field">
                                <label>IMEI:</label>
                                <input type="text" id="imei" name="imei" placeholder="IMEI-Nummer">
                            </div>
                        </div>

                        <div class="checkbox-group">
                            <h4>Zubehör enthalten:</h4>
                            <div class="checkbox-row">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="zubehoer" value="Kabel"> Kabel
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="zubehoer" value="Netzteil"> Netzteil
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="zubehoer" value="OVP"> OVP
                                </label>
                            </div>
                        </div>

                        <div class="checkbox-group">
                            <h4>Zustand bei Übergabe:</h4>
                            <div class="checkbox-row">
                                <label class="checkbox-item">
                                    <input type="radio" name="zustand" value="Neu"> Neu
                                </label>
                                <label class="checkbox-item">
                                    <input type="radio" name="zustand" value="Sehr gut"> Sehr gut
                                </label>
                                <label class="checkbox-item">
                                    <input type="radio" name="zustand" value="Gut"> Gut
                                </label>
                                <label class="checkbox-item">
                                    <input type="radio" name="zustand" value="Gebrauchsspuren"> Gebrauchsspuren
                                </label>
                            </div>
                        </div>

                        <div class="device-info" style="margin-top: 20px;">
                            <div class="device-field">
                                <label>Datum Übergabe:</label>
                                <input type="date" id="datumUebergabe" name="datumUebergabe">
                            </div>
                            <div class="device-field">
                                <label>Datum Rückgabe:</label>
                                <input type="date" id="datumRueckgabe" name="datumRueckgabe">
                            </div>
                        </div>
                    </div>

                    <!-- Agreement Checkbox -->
                    <div class="agreement-checkbox">
                        <input type="checkbox" id="agreement" name="agreement" required>
                        <label for="agreement">Ich habe die Vertragsbedingungen gelesen und akzeptiere diese. Ich bestätige, dass alle angegebenen Daten korrekt sind.</label>
                    </div>

                    <!-- Submit Section -->
                    <div class="submit-section">
                        <p>Nach dem Absenden erhalten Sie eine Kopie des Vertrags an Ihre E-Mail-Adresse.</p>
                        <button type="submit" class="btn-submit" id="submitBtn">
                            Vertrag absenden & Kopie erhalten
                        </button>
                        <button type="button" class="btn-print" onclick="window.print()">
                            Drucken / PDF speichern
                        </button>
                    </div>

                    <div class="success-message" id="successMessage">
                        <strong>Vertrag erfolgreich gesendet!</strong><br>
                        Eine Kopie wurde an Ihre E-Mail-Adresse gesendet.
                    </div>

                    <div class="error-message" id="errorMessage">
                        <strong>Fehler beim Senden!</strong><br>
                        <span id="errorText">Bitte versuchen Sie es erneut.</span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Signature Canvas Setup
        const signatureCanvases = {};

        function setupSignatureCanvas(canvasId) {
            const canvas = document.getElementById(canvasId);
            const ctx = canvas.getContext('2d');
            let isDrawing = false;
            let lastX = 0;
            let lastY = 0;

            // Set canvas size
            function resizeCanvas() {
                const rect = canvas.getBoundingClientRect();
                canvas.width = rect.width;
                canvas.height = rect.height;
                ctx.strokeStyle = '#1a1a2e';
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
            }

            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);

            function getPosition(e) {
                const rect = canvas.getBoundingClientRect();
                if (e.touches) {
                    return {
                        x: e.touches[0].clientX - rect.left,
                        y: e.touches[0].clientY - rect.top
                    };
                }
                return {
                    x: e.clientX - rect.left,
                    y: e.clientY - rect.top
                };
            }

            function startDrawing(e) {
                e.preventDefault();
                isDrawing = true;
                canvas.classList.add('signing');
                const pos = getPosition(e);
                lastX = pos.x;
                lastY = pos.y;
            }

            function draw(e) {
                if (!isDrawing) return;
                e.preventDefault();
                const pos = getPosition(e);
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                lastX = pos.x;
                lastY = pos.y;
            }

            function stopDrawing() {
                isDrawing = false;
            }

            // Mouse events
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseout', stopDrawing);

            // Touch events
            canvas.addEventListener('touchstart', startDrawing);
            canvas.addEventListener('touchmove', draw);
            canvas.addEventListener('touchend', stopDrawing);

            signatureCanvases[canvasId] = { canvas, ctx };
        }

        function clearSignature(canvasId) {
            const { canvas, ctx } = signatureCanvases[canvasId];
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            canvas.classList.remove('signing');
        }

        function isCanvasEmpty(canvasId) {
            const { canvas, ctx } = signatureCanvases[canvasId];
            const pixelBuffer = new Uint32Array(
                ctx.getImageData(0, 0, canvas.width, canvas.height).data.buffer
            );
            return !pixelBuffer.some(color => color !== 0);
        }

        // Initialize signature canvases
        setupSignatureCanvas('signatureHaendler');
        setupSignatureCanvas('signatureAnbieter');

        // Set today's date as default
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('datumHaendler').value = today;
        document.getElementById('datumAnbieter').value = today;
        document.getElementById('datumUebergabe').value = today;

        // Form submission
        document.getElementById('contractForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            // Validate signature
            if (isCanvasEmpty('signatureHaendler')) {
                alert('Bitte unterschreiben Sie den Vertrag.');
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="loading"></span>Wird gesendet...';
            submitBtn.disabled = true;

            // Hide previous messages
            document.getElementById('successMessage').classList.remove('show');
            document.getElementById('errorMessage').classList.remove('show');

            // Collect form data
            const formData = new FormData(this);
            const data = {};
            formData.forEach((value, key) => {
                if (data[key]) {
                    if (!Array.isArray(data[key])) {
                        data[key] = [data[key]];
                    }
                    data[key].push(value);
                } else {
                    data[key] = value;
                }
            });

            // Add signatures as base64
            data.signatureHaendler = document.getElementById('signatureHaendler').toDataURL();
            data.signatureAnbieter = document.getElementById('signatureAnbieter').toDataURL();

            try {
                const response = await fetch('/wp-json/punktepass/v1/contract/submit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    document.getElementById('successMessage').classList.add('show');
                    submitBtn.innerHTML = 'Gesendet!';
                    submitBtn.style.background = '#22c55e';
                } else {
                    throw new Error(result.message || 'Unbekannter Fehler');
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('errorText').textContent = error.message || 'Bitte versuchen Sie es erneut.';
                document.getElementById('errorMessage').classList.add('show');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    </script>
</body>
</html>
        <?php
    }
}

// Initialize
PPV_Haendlervertrag::hooks();
