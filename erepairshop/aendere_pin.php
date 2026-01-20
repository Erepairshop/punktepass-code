<?php
if (!empty($_POST['identifikator']) && !empty($_POST['neue_pin'])) {
    $identifikator = $_POST['identifikator'];
    $neue_pin = $_POST['neue_pin'];

    // Einträge aus der Datei lesen
    $alleEintraege = file('entries.txt', FILE_IGNORE_NEW_LINES);
    $aktualisierteEintraege = [];

    foreach ($alleEintraege as $eintrag) {
        list($datum, $name, $telefon, $marke, $modell, $problem, $other, $pin, $musterImagePath) = explode('|', $eintrag);
        if ($telefon === $identifikator) {
            // Aktualisieren der PIN für den gefundenen Eintrag
            $eintrag = implode('|', [$datum, $name, $telefon, $marke, $modell, $problem, $other, $neue_pin, $musterImagePath]);
        }
        $aktualisierteEintraege[] = $eintrag;
    }

    // Die aktualisierte Liste in die Datei zurückschreiben
    file_put_contents('entries.txt', implode("\n", $aktualisierteEintraege));

    // Zurück zur Ansichtsseite
    header('Location: view_entries.php');
    exit;
}
?>