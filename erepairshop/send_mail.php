<?php
require_once __DIR__ . '/db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Empfangen der Formulardaten
    $name = htmlspecialchars($_POST['name'] ?? '');
    $phone = htmlspecialchars($_POST['phone'] ?? '');
    $brand = htmlspecialchars($_POST['brand'] ?? '');
    $model = htmlspecialchars($_POST['model'] ?? '');
    $problem = htmlspecialchars($_POST['problem'] ?? '');
    $other = isset($_POST['other']) ? htmlspecialchars($_POST['other']) : '';
    $pin = isset($_POST['pin']) ? htmlspecialchars($_POST['pin']) : 'N/A';

    // Empfangen und Verarbeiten der Musterdaten - nur wenn "muster" als Sicherheitstyp gewählt wurde
    $sicherheitstyp = $_POST['sicherheitstyp'] ?? '';
    $muster_base64 = isset($_POST['muster']) ? $_POST['muster'] : '';
    $muster_image_path = '';

    // Nur Musterbild speichern wenn Sicherheitstyp "muster" ist und Daten vorhanden sind
    if ($sicherheitstyp === 'muster' && !empty($muster_base64) && strpos($muster_base64, 'data:image') === 0) {
        $muster_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $muster_base64));
        $muster_image_path = 'uploads/muster_' . uniqid() . '.png';
        file_put_contents(__DIR__ . '/' . $muster_image_path, $muster_data);
    }

    // Speichern der Daten in der Datenbank
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO entries (datum, name, telefon, marke, modell, problem, other, pin, muster_image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            date("Y-m-d H:i:s"),
            $name,
            $phone,
            $brand,
            $model,
            $problem,
            $other,
            $pin,
            $muster_image_path
        ]);
    } catch (Exception $e) {
        error_log("Fehler beim Speichern: " . $e->getMessage());
    }

    // Vorbereitung der E-Mail-Nachricht
    $to = 'borota25@gmail.com';
    $subject = 'Neuer Reparaturauftrag';
    $boundary = uniqid('np');
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "From: webmaster@example.com\r\n";
    $headers .= "Content-Type: multipart/mixed;boundary=\"" . $boundary . "\"\r\n";

    // Erstellen der Nachricht
    $message = "--" . $boundary . "\r\n";
    $message .= "Content-type: text/plain;charset=utf-8\r\n\r\n";
    $message .= "Name: $name\nTelefonnummer: $phone\nMarke: $brand\nModell: $model\nProblem: $problem\nWeitere Problembeschreibung: $other\nPIN: $pin\n\n";

    // Hinzufügen des Musterbildes als Anhang, falls vorhanden
    if (!empty($muster_image_path)) {
        $file_name = basename($muster_image_path);
        $file_content = file_get_contents(__DIR__ . '/' . $muster_image_path);
        $file_encoded = chunk_split(base64_encode($file_content));

        $message .= "--" . $boundary . "\r\n";
        $message .= "Content-Type: application/octet-stream; name=\"" . $file_name . "\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename=\"" . $file_name . "\"\r\n\r\n";
        $message .= $file_encoded . "\r\n";
    }

    // Abschluss der MIME-E-Mail
    $message .= "--" . $boundary . "--";

    // Senden der E-Mail-Nachricht

if (mail($to, $subject, $message, $headers)) {
        // Modern success page
        echo '<!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="refresh" content="15;url=https://erepairshop.de/formular/formular.html">
            <title>Auftrag erfolgreich!</title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }

                body {
                    font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    padding: 20px;
                    overflow: hidden;
                }

                .success-container {
                    background: rgba(255, 255, 255, 0.95);
                    backdrop-filter: blur(20px);
                    border-radius: 24px;
                    padding: 50px 40px;
                    max-width: 480px;
                    width: 100%;
                    text-align: center;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
                }

                @keyframes slideUp {
                    from { opacity: 0; transform: translateY(30px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                .checkmark-circle {
                    width: 100px;
                    height: 100px;
                    margin: 0 auto 30px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #00c853 0%, #00e676 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
                    box-shadow: 0 10px 40px rgba(0, 200, 83, 0.4);
                }

                @keyframes scaleIn {
                    from { transform: scale(0); }
                    to { transform: scale(1); }
                }

                .checkmark {
                    width: 45px;
                    height: 45px;
                    stroke: white;
                    stroke-width: 3;
                    fill: none;
                    stroke-linecap: round;
                    stroke-linejoin: round;
                    stroke-dasharray: 100;
                    stroke-dashoffset: 100;
                    animation: drawCheck 0.6s ease-out 0.5s forwards;
                }

                @keyframes drawCheck {
                    to { stroke-dashoffset: 0; }
                }

                h1 {
                    font-size: 28px;
                    font-weight: 700;
                    color: #1a1a2e;
                    margin-bottom: 16px;
                    animation: fadeIn 0.5s ease 0.3s both;
                }

                .subtitle {
                    font-size: 17px;
                    color: #4a5568;
                    line-height: 1.7;
                    margin-bottom: 35px;
                    animation: fadeIn 0.5s ease 0.4s both;
                }

                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                .info-card {
                    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                    border-radius: 16px;
                    padding: 24px;
                    margin-bottom: 30px;
                    animation: fadeIn 0.5s ease 0.5s both;
                }

                .info-row {
                    display: flex;
                    align-items: center;
                    justify-content: flex-start;
                    gap: 14px;
                    padding: 12px 0;
                    border-bottom: 1px solid rgba(0,0,0,0.06);
                }

                .info-row:last-child { border-bottom: none; }

                .info-icon {
                    width: 42px;
                    height: 42px;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 20px;
                    flex-shrink: 0;
                }

                .icon-phone { background: linear-gradient(135deg, #3b82f6, #2563eb); }
                .icon-time { background: linear-gradient(135deg, #f59e0b, #d97706); }
                .icon-location { background: linear-gradient(135deg, #10b981, #059669); }

                .info-text {
                    text-align: left;
                }

                .info-label {
                    font-size: 12px;
                    color: #94a3b8;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 2px;
                }

                .info-value {
                    font-size: 15px;
                    font-weight: 600;
                    color: #1e293b;
                }

                .redirect-notice {
                    font-size: 13px;
                    color: #94a3b8;
                    animation: fadeIn 0.5s ease 0.6s both;
                }

                .progress-bar {
                    width: 100%;
                    height: 4px;
                    background: #e2e8f0;
                    border-radius: 2px;
                    margin-top: 12px;
                    overflow: hidden;
                }

                .progress-fill {
                    height: 100%;
                    background: linear-gradient(90deg, #667eea, #764ba2);
                    border-radius: 2px;
                    animation: progressFill 15s linear forwards;
                }

                @keyframes progressFill {
                    from { width: 0%; }
                    to { width: 100%; }
                }

                /* Floating particles */
                .particles {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    pointer-events: none;
                    overflow: hidden;
                    z-index: -1;
                }

                .particle {
                    position: absolute;
                    width: 10px;
                    height: 10px;
                    background: rgba(255,255,255,0.3);
                    border-radius: 50%;
                    animation: float 4s ease-in-out infinite;
                }

                @keyframes float {
                    0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.5; }
                    50% { transform: translateY(-20px) rotate(180deg); opacity: 1; }
                }

                @media (max-width: 500px) {
                    .success-container { padding: 40px 25px; }
                    h1 { font-size: 24px; }
                    .subtitle { font-size: 15px; }
                    .checkmark-circle { width: 80px; height: 80px; }
                    .checkmark { width: 35px; height: 35px; }
                }
            </style>
        </head>
        <body>
            <div class="particles">
                <div class="particle" style="left: 10%; top: 20%; animation-delay: 0s;"></div>
                <div class="particle" style="left: 20%; top: 80%; animation-delay: 1s;"></div>
                <div class="particle" style="left: 60%; top: 10%; animation-delay: 2s;"></div>
                <div class="particle" style="left: 80%; top: 60%; animation-delay: 0.5s;"></div>
                <div class="particle" style="left: 40%; top: 40%; animation-delay: 1.5s;"></div>
                <div class="particle" style="left: 90%; top: 30%; animation-delay: 2.5s;"></div>
            </div>

            <div class="success-container">
                <div class="checkmark-circle">
                    <svg class="checkmark" viewBox="0 0 24 24">
                        <path d="M4 12l6 6L20 6"/>
                    </svg>
                </div>

                <h1>Auftrag erfolgreich!</h1>
                <p class="subtitle">Vielen Dank für Ihr Vertrauen. Wir haben Ihren Reparaturauftrag erhalten und kümmern uns schnellstmöglich darum.</p>

                <div class="info-card">
                    <div class="info-row">
                        <div class="info-icon icon-phone">📞</div>
                        <div class="info-text">
                            <div class="info-label">Kontakt</div>
                            <div class="info-value">09074 / 21 03</div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon icon-time">⏰</div>
                        <div class="info-text">
                            <div class="info-label">Bearbeitungszeit</div>
                            <div class="info-value">In der Regel 1-2 Werktage</div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon icon-location">📍</div>
                        <div class="info-text">
                            <div class="info-label">Abholung</div>
                            <div class="info-value">Siedlungsring 51, Höchstädt</div>
                        </div>
                    </div>
                </div>

                <p class="redirect-notice">Sie werden automatisch weitergeleitet...</p>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
            </div>
        </body>
        </html>';
    } else {
        // Fehlerbehandlung, falls die E-Mail nicht gesendet werden konnte
        echo "Es gab ein Problem beim Senden Ihrer Anfrage. Bitte versuchen Sie es später erneut.";
    }
}
?>



