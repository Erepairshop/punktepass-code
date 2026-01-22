<?php
require_once __DIR__ . '/db_config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['entry_id'])) {
    $pdo = getDB();
    $entry_id = (int)$_POST['entry_id'];
    $telefon = $_POST['telefon'] ?? '';

    // Get entry to delete image if exists
    $stmt = $pdo->prepare("SELECT muster_image_path FROM entries WHERE id = ?");
    $stmt->execute([$entry_id]);
    $entry = $stmt->fetch();

    if ($entry) {
        // Delete image file if exists
        if (!empty($entry['muster_image_path'])) {
            $bildpfad = $_SERVER['DOCUMENT_ROOT'] . '/formular/' . trim($entry['muster_image_path']);
            if (file_exists($bildpfad)) {
                @unlink($bildpfad);
            }
        }

        // Delete entry from database
        $deleteStmt = $pdo->prepare("DELETE FROM entries WHERE id = ?");
        $deleteStmt->execute([$entry_id]);

        // Also delete related erledigt_status and kommentare
        if (!empty($telefon)) {
            $pdo->prepare("DELETE FROM erledigt_status WHERE telefon = ?")->execute([$telefon]);
            $pdo->prepare("DELETE FROM kommentare WHERE telefon = ?")->execute([$telefon]);
        }
    }

    header('Location: view_entries.php');
    exit();
}

// Redirect if accessed directly
header('Location: view_entries.php');
exit();
