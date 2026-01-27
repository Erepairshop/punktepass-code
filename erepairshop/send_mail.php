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

    // Empfangen und Verarbeiten der Unterschrift
    $signature_base64 = isset($_POST['signature']) ? $_POST['signature'] : '';
    $signature_image_path = '';

    // Unterschrift speichern wenn vorhanden
    if (!empty($signature_base64) && strpos($signature_base64, 'data:image') === 0) {
        $signature_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $signature_base64));
        $signature_image_path = 'uploads/sig_' . uniqid() . '.png';
        file_put_contents(__DIR__ . '/' . $signature_image_path, $signature_data);
    }

    // Speichern der Daten in der Datenbank
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO entries (datum, name, telefon, marke, modell, problem, other, pin, muster_image_path, signature_image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            date("Y-m-d H:i:s"),
            $name,
            $phone,
            $brand,
            $model,
            $problem,
            $other,
            $pin,
            $muster_image_path,
            $signature_image_path
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
        // Modern in-store success page with confetti
        echo '<!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="refresh" content="5;url=https://erepairshop.de/formular/formular.html">
            <title>✓ Gespeichert!</title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }

                body {
                    font-family: "Inter", sans-serif;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #0f0f23;
                    overflow: hidden;
                }

                .container {
                    text-align: center;
                    z-index: 10;
                }

                .success-icon {
                    width: 160px;
                    height: 160px;
                    margin: 0 auto 40px;
                    position: relative;
                }

                .circle-bg {
                    width: 160px;
                    height: 160px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #00d26a 0%, #00ff88 100%);
                    position: absolute;
                    animation: pulse 2s ease-in-out infinite, scaleIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
                    box-shadow: 0 0 60px rgba(0, 210, 106, 0.5), 0 0 120px rgba(0, 210, 106, 0.3);
                }

                @keyframes pulse {
                    0%, 100% { box-shadow: 0 0 60px rgba(0, 210, 106, 0.5), 0 0 120px rgba(0, 210, 106, 0.3); }
                    50% { box-shadow: 0 0 80px rgba(0, 210, 106, 0.7), 0 0 150px rgba(0, 210, 106, 0.4); }
                }

                @keyframes scaleIn {
                    from { transform: scale(0); }
                    to { transform: scale(1); }
                }

                .checkmark {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    width: 70px;
                    height: 70px;
                    stroke: white;
                    stroke-width: 4;
                    fill: none;
                    stroke-linecap: round;
                    stroke-linejoin: round;
                    stroke-dasharray: 100;
                    stroke-dashoffset: 100;
                    animation: drawCheck 0.6s ease-out 0.4s forwards;
                    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
                }

                @keyframes drawCheck {
                    to { stroke-dashoffset: 0; }
                }

                h1 {
                    font-size: 48px;
                    font-weight: 800;
                    color: white;
                    margin-bottom: 16px;
                    animation: fadeUp 0.6s ease 0.3s both;
                    text-shadow: 0 4px 20px rgba(0,0,0,0.3);
                }

                .subtitle {
                    font-size: 20px;
                    color: rgba(255,255,255,0.7);
                    animation: fadeUp 0.6s ease 0.5s both;
                }

                @keyframes fadeUp {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                /* Confetti */
                .confetti {
                    position: fixed;
                    width: 15px;
                    height: 15px;
                    top: -20px;
                    animation: confettiFall 3s ease-out forwards;
                }

                @keyframes confettiFall {
                    0% { transform: translateY(0) rotate(0deg); opacity: 1; }
                    100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
                }

                .c1 { background: #ff6b6b; left: 10%; animation-delay: 0s; border-radius: 50%; }
                .c2 { background: #4ecdc4; left: 20%; animation-delay: 0.2s; }
                .c3 { background: #ffe66d; left: 30%; animation-delay: 0.1s; border-radius: 50%; }
                .c4 { background: #95e1d3; left: 40%; animation-delay: 0.3s; }
                .c5 { background: #f38181; left: 50%; animation-delay: 0.15s; border-radius: 50%; }
                .c6 { background: #aa96da; left: 60%; animation-delay: 0.25s; }
                .c7 { background: #fcbad3; left: 70%; animation-delay: 0.05s; border-radius: 50%; }
                .c8 { background: #a8d8ea; left: 80%; animation-delay: 0.35s; }
                .c9 { background: #00d26a; left: 90%; animation-delay: 0.1s; border-radius: 50%; }
                .c10 { background: #ffd93d; left: 15%; animation-delay: 0.4s; }
                .c11 { background: #6bcb77; left: 25%; animation-delay: 0.2s; border-radius: 50%; }
                .c12 { background: #ff8b94; left: 35%; animation-delay: 0.3s; }
                .c13 { background: #b5ead7; left: 45%; animation-delay: 0.15s; border-radius: 50%; }
                .c14 { background: #c7ceea; left: 55%; animation-delay: 0.25s; }
                .c15 { background: #ffc8dd; left: 65%; animation-delay: 0.1s; border-radius: 50%; }
                .c16 { background: #bde0fe; left: 75%; animation-delay: 0.35s; }
                .c17 { background: #e2f0cb; left: 85%; animation-delay: 0.2s; border-radius: 50%; }
                .c18 { background: #ffc107; left: 95%; animation-delay: 0.4s; }

                /* Progress ring */
                .progress-ring {
                    position: fixed;
                    bottom: 30px;
                    left: 50%;
                    transform: translateX(-50%);
                    animation: fadeUp 0.6s ease 0.7s both;
                }

                .progress-ring svg {
                    width: 50px;
                    height: 50px;
                    transform: rotate(-90deg);
                }

                .progress-ring circle {
                    fill: none;
                    stroke-width: 4;
                }

                .progress-bg {
                    stroke: rgba(255,255,255,0.2);
                }

                .progress-fill {
                    stroke: #00d26a;
                    stroke-dasharray: 126;
                    stroke-dashoffset: 126;
                    stroke-linecap: round;
                    animation: progressRing 5s linear forwards;
                }

                @keyframes progressRing {
                    to { stroke-dashoffset: 0; }
                }

                .progress-text {
                    color: rgba(255,255,255,0.5);
                    font-size: 12px;
                    margin-top: 8px;
                }
            </style>
        </head>
        <body>
            <!-- Confetti -->
            <div class="confetti c1"></div>
            <div class="confetti c2"></div>
            <div class="confetti c3"></div>
            <div class="confetti c4"></div>
            <div class="confetti c5"></div>
            <div class="confetti c6"></div>
            <div class="confetti c7"></div>
            <div class="confetti c8"></div>
            <div class="confetti c9"></div>
            <div class="confetti c10"></div>
            <div class="confetti c11"></div>
            <div class="confetti c12"></div>
            <div class="confetti c13"></div>
            <div class="confetti c14"></div>
            <div class="confetti c15"></div>
            <div class="confetti c16"></div>
            <div class="confetti c17"></div>
            <div class="confetti c18"></div>

            <div class="container">
                <div class="success-icon">
                    <div class="circle-bg"></div>
                    <svg class="checkmark" viewBox="0 0 24 24">
                        <path d="M4 12l6 6L20 6"/>
                    </svg>
                </div>

                <h1>Gespeichert!</h1>
                <p class="subtitle">Auftrag wurde erfolgreich angelegt</p>
            </div>

            <div class="progress-ring">
                <svg viewBox="0 0 44 44">
                    <circle class="progress-bg" cx="22" cy="22" r="20"/>
                    <circle class="progress-fill" cx="22" cy="22" r="20"/>
                </svg>
                <div class="progress-text">Nächster Kunde...</div>
            </div>
        </body>
        </html>';
    } else {
        // Fehlerbehandlung, falls die E-Mail nicht gesendet werden konnte
        echo "Es gab ein Problem beim Senden Ihrer Anfrage. Bitte versuchen Sie es später erneut.";
    }
}
?>



