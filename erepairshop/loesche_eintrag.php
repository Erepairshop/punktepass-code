<?php
// Einträge aus der Datei lesen
$alleEintraege = file('entries.txt', FILE_IGNORE_NEW_LINES);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['eintrag'])) {
    $zu_loeschender_eintrag = $_POST['eintrag'];

    // Suche den Index des zu löschenden Eintrags
    $index = array_search($zu_loeschender_eintrag, $alleEintraege);

    // Wenn der Eintrag gefunden wurde, entferne ihn
    if ($index !== false) {
        unset($alleEintraege[$index]);

        // Extrahiere den Pfad des Bildes aus dem Eintrag, falls vorhanden
        $eintragsteile = explode('|', $zu_loeschender_eintrag);
        $bildpfad = end($eintragsteile); // Das Bild ist der letzte Teil des Eintrags
        $vollstaendigerBildpfad = $_SERVER['DOCUMENT_ROOT'] . '/formular/' . trim($bildpfad);

        if (!empty($bildpfad) && file_exists($vollstaendigerBildpfad)) {
            if (!unlink($vollstaendigerBildpfad)) {
                error_log("Fehler beim Löschen der Bilddatei: " . $vollstaendigerBildpfad);
            }
        }

        // Schreiben der verbleibenden Einträge zurück in die Datei
        // Stellen Sie sicher, dass ein Zeilenumbruch am Ende steht, wenn die Datei nicht leer ist
        if (count($alleEintraege) > 0) {
            file_put_contents('entries.txt', implode(PHP_EOL, $alleEintraege) . PHP_EOL);
        } else {
            // Wenn keine Einträge vorhanden sind, leere die Datei komplett
            file_put_contents('entries.txt', '');
        }

        header('Location: view_entries.php');
        exit();
    }
}
?>
