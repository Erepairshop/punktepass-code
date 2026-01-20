<?php
// Erledigt-Status setzen oder entfernen (AJAX support)
$erledigtDatei = 'erledigt_status.txt';

// AJAX request handling
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: application/json');

    if (isset($_POST['markAsDone'], $_POST['identifikator'])) {
        $identifikator = $_POST['identifikator'];
        $erledigtStatus = file_exists($erledigtDatei) ? file($erledigtDatei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

        $wasErledigt = in_array($identifikator, $erledigtStatus);

        if (($key = array_search($identifikator, $erledigtStatus)) !== false) {
            unset($erledigtStatus[$key]);
            $nowErledigt = false;
        } else {
            $erledigtStatus[] = $identifikator;
            $nowErledigt = true;
        }

        file_put_contents($erledigtDatei, implode("\n", array_values($erledigtStatus)));
        echo json_encode(['success' => true, 'erledigt' => $nowErledigt, 'wasErledigt' => $wasErledigt]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

// Normal POST handling (fallback)
if (isset($_POST['markAsDone'], $_POST['identifikator'])) {
    $identifikator = $_POST['identifikator'];
    $erledigtStatus = file_exists($erledigtDatei) ? file($erledigtDatei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

    if (($key = array_search($identifikator, $erledigtStatus)) !== false) {
        unset($erledigtStatus[$key]);
    } else {
        $erledigtStatus[] = $identifikator;
    }

    file_put_contents($erledigtDatei, implode("\n", array_values($erledigtStatus)));
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// Kommentar hinzufügen oder löschen
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['kommentar'], $_POST['identifikator']) && !empty($_POST['kommentar'])) {
        $kommentar = trim($_POST['kommentar']);
        $identifikator = trim($_POST['identifikator']);
        $kommentarEintrag = $identifikator . '|' . uniqid() . '|' . $kommentar . "\n";
        file_put_contents('kommentare.txt', $kommentarEintrag, FILE_APPEND);
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } elseif (isset($_POST['loesche_kommentar'], $_POST['kommentar_id'])) {
        $kommentar_id = $_POST['kommentar_id'];
        $alleKommentare = file('kommentare.txt', FILE_IGNORE_NEW_LINES);
        $alleKommentare = array_filter($alleKommentare, function($line) use ($kommentar_id) {
            $parts = explode('|', $line);
            return isset($parts[1]) && trim($parts[1]) !== $kommentar_id;
        });
        file_put_contents('kommentare.txt', implode("\n", $alleKommentare));
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// Einträge und Erledigt-Status laden
$eintraegeDatei = 'entries.txt';
$alleEintraege = file_exists($eintraegeDatei) ? file($eintraegeDatei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$erledigtStatus = file_exists($erledigtDatei) ? file($erledigtDatei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$alleEintraege = array_reverse($alleEintraege);

// Sort: Offen (nicht erledigt) first, then Erledigt
usort($alleEintraege, function($a, $b) use ($erledigtStatus) {
    $partsA = explode('|', $a);
    $partsB = explode('|', $b);
    $telefonA = isset($partsA[2]) ? $partsA[2] : '';
    $telefonB = isset($partsB[2]) ? $partsB[2] : '';
    $erledigtA = in_array($telefonA, $erledigtStatus) ? 1 : 0;
    $erledigtB = in_array($telefonB, $erledigtStatus) ? 1 : 0;
    return $erledigtA - $erledigtB; // Offen (0) comes before Erledigt (1)
});

// Kommentare laden
$kommentarDatei = 'kommentare.txt';
$alleKommentare = file_exists($kommentarDatei) ? file($kommentarDatei, FILE_IGNORE_NEW_LINES) : [];
$kommentarArray = [];
foreach ($alleKommentare as $kommentarEintrag) {
    $parts = explode('|', $kommentarEintrag, 3);
    if (count($parts) === 3) {
        list($kommentarIdentifikator, $kommentarId, $kommentarText) = $parts;
        $kommentarArray[$kommentarIdentifikator][$kommentarId] = $kommentarText;
    }
}

// Count stats
$totalCount = count($alleEintraege);
$erledigtCount = 0;
foreach ($alleEintraege as $eintrag) {
    if (empty($eintrag)) continue;
    $parts = explode('|', $eintrag);
    if (isset($parts[2]) && in_array($parts[2], $erledigtStatus)) {
        $erledigtCount++;
    }
}
$offenCount = $totalCount - $erledigtCount;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reparatur-Aufträge</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --radius: 12px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            min-height: 100vh;
            color: var(--gray-800);
            line-height: 1.5;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: white;
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header h1 i {
            color: var(--primary);
        }

        .stats {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .stat-card {
            background: var(--gray-50);
            padding: 12px 20px;
            border-radius: 8px;
            text-align: center;
            min-width: 100px;
        }

        .stat-card.success { background: var(--success-light); }
        .stat-card.warning { background: #fef3c7; }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-900);
        }

        .stat-label {
            font-size: 12px;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Cards Grid */
        .entries-grid {
            display: grid;
            gap: 16px;
        }

        .entry-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary);
        }

        .entry-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .entry-card.erledigt {
            border-left-color: var(--success);
            background: linear-gradient(to right, var(--success-light), white);
        }

        .entry-header {
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid var(--gray-100);
            flex-wrap: wrap;
            gap: 12px;
        }

        .entry-title {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .entry-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .entry-date {
            font-size: 13px;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .entry-status {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.offen {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.erledigt {
            background: var(--success-light);
            color: #065f46;
        }

        .entry-body {
            padding: 16px 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .info-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-label {
            font-size: 11px;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .info-value {
            font-size: 15px;
            color: var(--gray-800);
            font-weight: 500;
        }

        .info-value.highlight {
            color: var(--primary);
            font-weight: 600;
        }

        /* Actions */
        .entry-actions {
            padding: 16px 20px;
            background: var(--gray-50);
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-outline {
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-outline:hover {
            background: var(--gray-100);
            border-color: var(--gray-400);
        }

        /* Comments */
        .comments-section {
            padding: 0 20px 16px;
        }

        .comment-form {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }

        .comment-input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .comment-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .comment-box {
            background: var(--gray-100);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .comment-text {
            font-size: 14px;
            color: var(--gray-700);
            flex: 1;
        }

        .comment-delete {
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .comment-delete:hover {
            color: var(--danger);
            background: var(--danger-light);
        }

        /* Muster Link */
        .muster-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: var(--primary);
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .muster-link:hover {
            background: var(--primary-dark);
        }

        /* Loading State */
        .btn.loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .btn.loading::after {
            content: '';
            width: 14px;
            height: 14px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-left: 6px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Scroll to top button */
        .scroll-top {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 50px;
            height: 50px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            transition: all 0.3s ease;
            opacity: 0;
            visibility: hidden;
        }

        .scroll-top.visible {
            opacity: 1;
            visibility: visible;
        }

        .scroll-top:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .empty-state i {
            font-size: 64px;
            color: var(--gray-300);
            margin-bottom: 16px;
        }

        .empty-state h3 {
            color: var(--gray-600);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--gray-500);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 12px;
            }

            .header {
                padding: 16px;
            }

            .header h1 {
                font-size: 20px;
            }

            .stat-card {
                min-width: 80px;
                padding: 10px 14px;
            }

            .stat-number {
                font-size: 22px;
            }

            .entry-body {
                grid-template-columns: 1fr 1fr;
            }

            .entry-actions {
                flex-direction: column;
            }

            .entry-actions .btn {
                width: 100%;
                justify-content: center;
            }

            .comment-form {
                flex-direction: column;
            }

            .comment-form .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="ri-smartphone-line"></i> Reparatur-Aufträge</h1>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalCount; ?></div>
                    <div class="stat-label">Gesamt</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-number"><?php echo $offenCount; ?></div>
                    <div class="stat-label">Offen</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-number"><?php echo $erledigtCount; ?></div>
                    <div class="stat-label">Erledigt</div>
                </div>
            </div>
        </div>

        <!-- Entries -->
        <div class="entries-grid">
            <?php if (empty($alleEintraege)): ?>
                <div class="empty-state">
                    <i class="ri-inbox-line"></i>
                    <h3>Keine Aufträge vorhanden</h3>
                    <p>Sobald Kunden das Formular ausfüllen, erscheinen die Aufträge hier.</p>
                </div>
            <?php else: ?>
                <?php foreach ($alleEintraege as $eintrag): ?>
                    <?php if (empty($eintrag)) continue; ?>
                    <?php
                    $parts = explode('|', $eintrag);
                    if (count($parts) < 9) continue;
                    list($datum, $name, $telefon, $marke, $modell, $problem, $other, $pin, $musterImagePath) = $parts;
                    $istErledigt = in_array($telefon, $erledigtStatus);
                    $problemLabels = [
                        'display' => 'Displaybruch',
                        'battery' => 'Akkuproblem',
                        'water' => 'Wasserschaden',
                        'charging' => 'Ladebuchse defekt',
                        'buttons' => 'Tasten defekt',
                        'backcover' => 'Backcover beschädigt',
                        'frame' => 'Rahmen verbogen',
                        'other' => 'Andere'
                    ];
                    $problemText = $problemLabels[$problem] ?? $problem;
                    ?>
                    <div class="entry-card <?php echo $istErledigt ? 'erledigt' : ''; ?>" data-id="<?php echo htmlspecialchars($telefon); ?>">
                        <div class="entry-header">
                            <div class="entry-title">
                                <div class="entry-name"><?php echo htmlspecialchars($name); ?></div>
                                <div class="entry-date">
                                    <i class="ri-calendar-line"></i>
                                    <?php echo htmlspecialchars($datum); ?>
                                </div>
                            </div>
                            <div class="entry-status">
                                <span class="status-badge <?php echo $istErledigt ? 'erledigt' : 'offen'; ?>">
                                    <?php echo $istErledigt ? 'Erledigt' : 'Offen'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="entry-body">
                            <div class="info-group">
                                <span class="info-label">Telefon</span>
                                <span class="info-value highlight">
                                    <a href="tel:<?php echo htmlspecialchars($telefon); ?>" style="color: inherit; text-decoration: none;">
                                        <?php echo htmlspecialchars($telefon); ?>
                                    </a>
                                </span>
                            </div>
                            <div class="info-group">
                                <span class="info-label">Gerät</span>
                                <span class="info-value"><?php echo htmlspecialchars(ucfirst($marke)); ?> <?php echo htmlspecialchars($modell); ?></span>
                            </div>
                            <div class="info-group">
                                <span class="info-label">Problem</span>
                                <span class="info-value"><?php echo htmlspecialchars($problemText); ?></span>
                            </div>
                            <?php if (!empty($other)): ?>
                            <div class="info-group">
                                <span class="info-label">Details</span>
                                <span class="info-value"><?php echo htmlspecialchars($other); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-group">
                                <span class="info-label">PIN/Muster</span>
                                <span class="info-value">
                                    <?php if (!empty($pin)): ?>
                                        <strong><?php echo htmlspecialchars($pin); ?></strong>
                                    <?php elseif (!empty($musterImagePath)): ?>
                                        <a href="/formular/<?php echo htmlspecialchars($musterImagePath); ?>" target="_blank" class="muster-link">
                                            <i class="ri-pattern-lock-line"></i> Muster anzeigen
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--gray-400);">Nicht angegeben</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Comments Section -->
                        <div class="comments-section">
                            <form class="comment-form" method="post">
                                <input type="hidden" name="identifikator" value="<?php echo htmlspecialchars($telefon); ?>">
                                <input type="text" name="kommentar" class="comment-input" placeholder="Kommentar hinzufügen...">
                                <button type="submit" class="btn btn-outline">
                                    <i class="ri-chat-1-line"></i> Hinzufügen
                                </button>
                            </form>

                            <?php if (isset($kommentarArray[$telefon])): ?>
                                <?php foreach ($kommentarArray[$telefon] as $kommentarId => $kommentarText): ?>
                                    <div class="comment-box">
                                        <span class="comment-text"><?php echo htmlspecialchars($kommentarText); ?></span>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="loesche_kommentar" value="1">
                                            <input type="hidden" name="kommentar_id" value="<?php echo htmlspecialchars($kommentarId); ?>">
                                            <button type="submit" class="comment-delete" title="Löschen">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <div class="entry-actions">
                            <button type="button" class="btn <?php echo $istErledigt ? 'btn-warning' : 'btn-success'; ?> erledigt-btn"
                                    data-id="<?php echo htmlspecialchars($telefon); ?>"
                                    data-erledigt="<?php echo $istErledigt ? '1' : '0'; ?>">
                                <i class="ri-<?php echo $istErledigt ? 'arrow-go-back-line' : 'check-line'; ?>"></i>
                                <?php echo $istErledigt ? 'Rückgängig' : 'Als erledigt markieren'; ?>
                            </button>

                            <form action="loesche_eintrag.php" method="post" style="display: inline;"
                                  onsubmit="return confirm('Möchten Sie diesen Auftrag wirklich löschen?');">
                                <input type="hidden" name="eintrag" value="<?php echo htmlspecialchars($eintrag); ?>">
                                <button type="submit" class="btn btn-danger">
                                    <i class="ri-delete-bin-line"></i> Löschen
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scroll to top button -->
    <button class="scroll-top" id="scrollTop" title="Nach oben">
        <i class="ri-arrow-up-line"></i>
    </button>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Erledigt Toggle with AJAX
        document.querySelectorAll('.erledigt-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();

                const button = this;
                const card = button.closest('.entry-card');
                const identifikator = button.dataset.id;
                const wasErledigt = button.dataset.erledigt === '1';

                // Add loading state
                button.classList.add('loading');

                // Send AJAX request
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ajax=1&markAsDone=1&identifikator=' + encodeURIComponent(identifikator)
                })
                .then(response => response.json())
                .then(data => {
                    button.classList.remove('loading');

                    if (data.success) {
                        const grid = document.querySelector('.entries-grid');

                        if (data.erledigt) {
                            // Marked as done
                            card.classList.add('erledigt');
                            button.classList.remove('btn-success');
                            button.classList.add('btn-warning');
                            button.innerHTML = '<i class="ri-arrow-go-back-line"></i> Rückgängig';
                            button.dataset.erledigt = '1';

                            // Update status badge
                            const badge = card.querySelector('.status-badge');
                            badge.classList.remove('offen');
                            badge.classList.add('erledigt');
                            badge.textContent = 'Erledigt';

                            // Animate and move to bottom
                            card.style.transition = 'all 0.4s ease';
                            card.style.opacity = '0.5';
                            card.style.transform = 'scale(0.98)';

                            setTimeout(() => {
                                grid.appendChild(card);
                                card.style.opacity = '1';
                                card.style.transform = 'scale(1)';
                            }, 300);

                        } else {
                            // Unmarked - move to top
                            card.classList.remove('erledigt');
                            button.classList.remove('btn-warning');
                            button.classList.add('btn-success');
                            button.innerHTML = '<i class="ri-check-line"></i> Als erledigt markieren';
                            button.dataset.erledigt = '0';

                            // Update status badge
                            const badge = card.querySelector('.status-badge');
                            badge.classList.remove('erledigt');
                            badge.classList.add('offen');
                            badge.textContent = 'Offen';

                            // Animate and move to top
                            card.style.transition = 'all 0.4s ease';
                            card.style.opacity = '0.5';
                            card.style.transform = 'scale(0.98)';

                            setTimeout(() => {
                                grid.insertBefore(card, grid.firstChild);
                                card.style.opacity = '1';
                                card.style.transform = 'scale(1)';
                                // Scroll to top
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                            }, 300);
                        }

                        // Update stats
                        updateStats();
                    }
                })
                .catch(error => {
                    button.classList.remove('loading');
                    console.error('Error:', error);
                    // Fallback to form submit
                    const form = document.createElement('form');
                    form.method = 'post';
                    form.innerHTML = '<input type="hidden" name="identifikator" value="' + identifikator + '">' +
                                     '<input type="hidden" name="markAsDone" value="1">';
                    document.body.appendChild(form);
                    form.submit();
                });
            });
        });

        // Update stats without page reload
        function updateStats() {
            const total = document.querySelectorAll('.entry-card').length;
            const erledigt = document.querySelectorAll('.entry-card.erledigt').length;
            const offen = total - erledigt;

            const statNumbers = document.querySelectorAll('.stat-number');
            if (statNumbers.length >= 3) {
                statNumbers[0].textContent = total;
                statNumbers[1].textContent = offen;
                statNumbers[2].textContent = erledigt;
            }
        }

        // Scroll to top button
        const scrollTopBtn = document.getElementById('scrollTop');

        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                scrollTopBtn.classList.add('visible');
            } else {
                scrollTopBtn.classList.remove('visible');
            }
        });

        scrollTopBtn.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
    </script>
</body>
</html>
