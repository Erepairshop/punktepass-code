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
        // Erfolgreiches Senden der E-Mail
        // Einbinden der QRCode-Bibliothek
        require_once 'phpqrcode/qrlib.php';

        // Ziel-URL für den QR-Code
        $googleReviewUrl = 'https://g.page/r/CRI2bxlc2Rx3EBM/review';

        // Erzeugen des QR-Codes direkt als Data-URI, um ihn im Bild-Tag zu nutzen
        ob_start();
        QRcode::png($googleReviewUrl, null, 'L', 4, 2);
        $imageString = base64_encode(ob_get_contents());
        ob_end_clean();

        // HTML-Struktur mit CSS für die Anzeige
        echo '<!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta http-equiv="refresh" content="300;url=https://erepairshop.de/formular/formular.html">
            <title>Bestätigung</title>
            <style>
                body { font-family: "Roboto", sans-serif; color: #333; background-color: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 20px auto; padding: 20px; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
                h1 { font-size: 24px; color: #444; }
                p { font-size: 16px; line-height: 1.6; margin: 20px 0; }
                img.qr-code { max-width: 150px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Vielen Dank für Ihren Auftrag!</h1>
                <p>Wir haben Ihre Anfrage erfolgreich erhalten und werden uns umgehend darum kümmern. Sie können sich auf unseren gewohnt schnellen und qualitativen Service verlassen.</p>
                <img src="data:image/png;base64,'.$imageString.'" class="qr-code" alt="QR Code">
                <p>Scannen Sie den QR-Code, um uns eine Bewertung auf Google zu hinterlassen. Ihre Meinung ist uns wichtig!</p>
            </div>
        </body>
        </html>';
    } else {
        // Fehlerbehandlung, falls die E-Mail nicht gesendet werden konnte
        echo "Es gab ein Problem beim Senden Ihrer Anfrage. Bitte versuchen Sie es später erneut.";
    }
}
?>



