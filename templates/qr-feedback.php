<?php
if (!defined('ABSPATH')) exit;
/**
 * QR Feedback Template – Zukunft 2030 Design
 *
 * Variables:
 * $success (bool) – sikeres gyűjtés?
 * $store (object|null) – bolt objektum
 * $message (string) – extra üzenet
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PunktePass – QR Feedback</title>
    <style>
        body {
            margin: 0;
            background: linear-gradient(to right, #0f172a, #1e1b4b, #0f172a);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: 'Inter', system-ui, sans-serif;
        }
        .ppv-card {
            max-width: 400px;
            width: 100%;
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(16px);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 8px 40px rgba(0,0,0,0.4);
            animation: fadeIn 0.8s ease;
        }
        .ppv-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .ppv-success { color: #4ade80; }
        .ppv-error { color: #f87171; }
        .ppv-store {
            margin-top: .5rem;
            font-size: 1.1rem;
            color: #c7d2fe;
        }
        .ppv-message {
            margin-top: 1rem;
            font-size: 1rem;
            color: #d1d5db;
        }
        .ppv-btn {
            display: inline-block;
            margin-top: 1.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            background: #6366f1;
            color: #fff;
            text-decoration: none;
            transition: background 0.2s ease;
        }
        .ppv-btn:hover {
            background: #4f46e5;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="ppv-card">
        <?php if ($success): ?>
            <div class="ppv-icon ppv-success">✅</div>
            <h1 style="font-size:1.5rem;font-weight:bold;">Punkt erfolgreich gesammelt!</h1>
            <?php if ($store): ?>
                <p class="ppv-store">bei <span style="font-weight:bold;"><?php echo esc_html($store->name); ?></span></p>
            <?php endif; ?>
        <?php else: ?>
            <div class="ppv-icon ppv-error">❌</div>
            <h1 style="font-size:1.5rem;font-weight:bold;">Aktion fehlgeschlagen</h1>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <p class="ppv-message"><?php echo esc_html($message); ?></p>
        <?php endif; ?>

        <a href="<?php echo esc_url(home_url('/')); ?>" class="ppv-btn">Zurück zur Startseite</a>
    </div>
</body>
</html>
