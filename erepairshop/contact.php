<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// ============ SPAM FILTER FUNCTIONS ============

function isSpam($name, $email, $message, $phoneModel) {
    // 1. Honeypot check - if filled, it's a bot
    if (!empty($_POST['website'])) {
        return "Bot detected (honeypot)";
    }

    // 2. Time check - form submitted too fast (under 3 seconds = bot)
    $formTime = isset($_POST['form_time']) ? intval($_POST['form_time']) : 0;
    if ($formTime > 0 && (time() - $formTime) < 3) {
        return "Form submitted too quickly";
    }

    // 3. Link check - too many URLs = spam
    $allText = $name . ' ' . $email . ' ' . $message . ' ' . $phoneModel;
    $urlCount = preg_match_all('/https?:\/\/|www\./i', $allText);
    if ($urlCount > 2) {
        return "Too many links ($urlCount)";
    }

    // 4. Spam keywords (common in spam)
    $spamKeywords = [
        'viagra', 'cialis', 'casino', 'poker', 'lottery', 'winner',
        'cryptocurrency', 'bitcoin investment', 'earn money fast',
        'click here', 'free money', 'act now', 'limited time',
        'nigerian prince', 'inheritance', 'million dollars',
        'seo service', 'web traffic', 'backlinks', 'marketing service',
        'adult content', 'xxx', 'porn', 'webcam',
        'weight loss', 'diet pill', 'enlargement',
        'prescription', 'pharmacy online', 'cheap meds',
        'работа', 'заработок', 'кредит', // Russian spam
        'crypto airdrop', 'nft giveaway', 'token sale'
    ];

    $lowerText = strtolower($allText);
    foreach ($spamKeywords as $keyword) {
        if (strpos($lowerText, strtolower($keyword)) !== false) {
            return "Spam keyword detected: $keyword";
        }
    }

    // 5. Check for excessive special characters (common in spam)
    $specialCharCount = preg_match_all('/[^\w\s@.\-äöüßÄÖÜ]/u', $allText);
    if ($specialCharCount > 50) {
        return "Too many special characters";
    }

    // 6. Check if email domain is suspicious
    $emailDomain = substr(strrchr($email, "@"), 1);
    $suspiciousDomains = ['tempmail.', 'throwaway.', '10minutemail.', 'guerrillamail.', 'mailinator.'];
    foreach ($suspiciousDomains as $domain) {
        if (stripos($emailDomain, $domain) !== false) {
            return "Suspicious email domain";
        }
    }

    // 7. Check message length (too short or too long)
    $messageLen = strlen($message);
    if ($messageLen < 10) {
        return "Message too short";
    }
    if ($messageLen > 5000) {
        return "Message too long";
    }

    // 8. All caps check (spam often uses ALL CAPS)
    $upperCount = preg_match_all('/[A-Z]/', $message);
    $letterCount = preg_match_all('/[a-zA-Z]/', $message);
    if ($letterCount > 20 && ($upperCount / $letterCount) > 0.7) {
        return "Too many uppercase letters";
    }

    return false; // Not spam
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $phoneModel = htmlspecialchars($_POST['phoneModel']);
    $message = htmlspecialchars($_POST['message']);
    $fileUpload = $_FILES['fileUpload'] ?? null;

    // Check for spam
    $spamReason = isSpam($name, $email, $message, $phoneModel);
    if ($spamReason !== false) {
        // Log spam attempt (optional)
        error_log("SPAM blocked from $email - Reason: $spamReason");

        // Show fake success to not alert spammers
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
        exit;
    }

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
