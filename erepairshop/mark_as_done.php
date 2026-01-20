<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['markAsDone'], $_POST['identifikator'])) {
    $identifikator = $_POST['identifikator'];
    $erledigtDatei = 'erledigt_status.txt';
    $erledigtStatus = file_exists($erledigtDatei) ? file($erledigtDatei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

    if (($key = array_search($identifikator, $erledigtStatus)) !== false) {
        unset($erledigtStatus[$key]);
    } else {
        $erledigtStatus[] = $identifikator;
    }

    if(file_put_contents($erledigtDatei, implode("\n", $erledigtStatus))) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
?>