<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $phoneModel = htmlspecialchars($_POST['phoneModel']);
    $message = htmlspecialchars($_POST['message']);
    $fileUpload = $_FILES['fileUpload'] ?? null;

    $mail = new PHPMailer(true); // Ensure the object is created first

    try {
        // Debug settings
        $mail->SMTPDebug = 0; // 2 = Detailed debug information
        $mail->Debugoutput = 'html';

        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'info@erepairshop.de';
        $mail->Password = 'F(%:K)a:$rkSCPTe3';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('info@erepairshop.de', 'Erepairshop Kontakt');
$mail->addReplyTo($email, $name);
        $mail->addAddress('borota25@gmail.com');

        // Attachments
        if ($fileUpload && $fileUpload['error'] == UPLOAD_ERR_OK) {
            $fileTempPath = $fileUpload['tmp_name'];
            $fileName = $fileUpload['name'];
            if (is_uploaded_file($fileTempPath)) {
                $mail->addAttachment($fileTempPath, $fileName);
            } else {
                echo "<div class='alert alert-danger mt-3'>Die Datei konnte nicht verarbeitet werden. Bitte erneut versuchen.</div>";
                exit;
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Neue Kontaktanfrage von $name";
        $mail->Body    = "
            <h1>Kontaktanfrage</h1>
            <p><strong>Name:</strong> $name</p>
            <p><strong>E-Mail:</strong> $email</p>
            <p><strong>Handymodell:</strong> $phoneModel</p>
            <p><strong>Nachricht:</strong><br>$message</p>
        ";

        $mail->send();
        echo "<div style='
        display: flex; 
        justify-content: center; 
        align-items: center; 
        height: 100vh; 
        text-align: center;'>
        <div style='
            padding: 20px; 
            border: 2px solid #4CAF50; 
            border-radius: 10px; 
            background-color: #dff0d8; 
            color: #3c763d; 
            font-size: 1.5em; 
            font-family: Arial, sans-serif;'>
            <strong>Nachricht erfolgreich gesendet!</strong>
        </div>
    </div>";
    } catch (Exception $e) {
        echo "<div class='alert alert-danger mt-3'>Fehler beim Senden der Nachricht: {$mail->ErrorInfo}</div>";
    }
} else {
    echo "Ungültige Anforderung.";
}
?>
