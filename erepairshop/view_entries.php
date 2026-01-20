<?php
// Erledigt-Status setzen oder entfernen
$erledigtDatei = 'erledigt_status.txt';
if (isset($_POST['markAsDone'], $_POST['identifikator'])) {
    $identifikator = $_POST['identifikator'];
    $erledigtStatus = file_exists($erledigtDatei) ? file($erledigtDatei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

    if (($key = array_search($identifikator, $erledigtStatus)) !== false) {
        unset($erledigtStatus[$key]);
    } else {
        $erledigtStatus[] = $identifikator;
    }

    file_put_contents($erledigtDatei, implode("\n", $erledigtStatus));
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
    } elseif (isset($_POST['loesche_kommentar'], $_POST['kommentar_id'])) {
        $kommentar_id = $_POST['kommentar_id'];
        $alleKommentare = file('kommentare.txt', FILE_IGNORE_NEW_LINES);
        $alleKommentare = array_filter($alleKommentare, function($line) use ($kommentar_id) {
            list($identifikator, $id, $kommentar) = explode('|', $line);
            return trim($id) !== $kommentar_id;
        });
        file_put_contents('kommentare.txt', implode("\n", $alleKommentare));
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// Einträge und Erledigt-Status laden
$eintraegeDatei = 'entries.txt';
$alleEintraege = file_exists($eintraegeDatei) ? file($eintraegeDatei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$erledigtStatus = file_exists($erledigtDatei) ? file($erledigtDatei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$alleEintraege = array_reverse($alleEintraege);


// Kommentare laden
$alleKommentare = file('kommentare.txt', FILE_IGNORE_NEW_LINES);
$kommentarArray = [];
foreach ($alleKommentare as $kommentarEintrag) {
    list($kommentarIdentifikator, $kommentarId, $kommentarText) = explode('|', $kommentarEintrag, 3);
    $kommentarArray[$kommentarIdentifikator][$kommentarId] = $kommentarText;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Einträge anzeigen</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            color: #333;
            line-height: 1.6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 16px;
        }
        th {
            background-color: #f8f8f8;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .button-link, input[type='submit'], button {
            background-color: #007bff;
            color: white;
            padding: 5px 10px;
            text-align: center;
            display: inline-block;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            margin: 5px 0 20px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .erledigt-btn {
    background-color: #28a745; /* Grün */
}

.kommentar-btn {
    background-color: #17a2b8; /* Blau */
}

.loeschen-btn {
    background-color: #dc3545; /* Rot */
}

.button-link:hover, input[type='submit']:hover, button:hover {
    opacity: 0.8;
}
        }
        .kommentar-box {
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        input[type='text'] {
            width: calc(100% - 22px);
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #ced4da;
            border-radius: 5px;
        }
        
        input[type='text'] {
    width: 50%; /* Verkleinert die Breite des Eingabefelds */
    padding: 5px; /* Reduziert das Padding, um das Feld kleiner erscheinen zu lassen */
    margin: 5px 0;
    border: 1px solid #ced4da;
    border-radius: 5px;
    font-size: 12px; /* Optional: Verkleinert die Schriftgröße für das Eingabefeld */
}
        @media (max-width: 768px) {
            .button-link, input[type='submit'], button {
                width: 100%;
                box-sizing: border-box;
            }
            input[type='text'] {
                width: calc(100% - 22px);
            }
        }
table tr.erledigt, table tr.erledigt th, table tr.erledigt td {
    background-color: #d4edda; /* Hellgrün oder eine andere Farbe Ihrer Wahl */
}
    </style>
</head>
<body>
    <div class="container">
        <table>
            <tr>
                <th>Datum</th>
                <th>Name</th>
                <th>Telefon</th>
                <th>Marke</th>
                <th>Modell</th>
                <th>Problem</th>
                <th>Andere Problem</th>
                <th>PIN</th>
                <th>Muster</th>
                <th>Aktionen</th>
                <th>Auswählen</th>
            </tr>
            <?php foreach ($alleEintraege as $eintrag): ?>
                <?php if (empty($eintrag)) continue; ?>
                <?php
                list($datum, $name, $telefon, $marke, $modell, $problem, $other, $pin, $musterImagePath) = explode('|', $eintrag);
                $istErledigt = in_array($telefon, $erledigtStatus); // Prüft, ob der Eintrag erledigt ist
                ?>
                <tr<?php echo $istErledigt ? ' class="erledigt"' : ''; ?>>

    <td><?php echo htmlspecialchars($datum); ?></td>
    <td><?php echo htmlspecialchars($name); ?></td>
    <td><?php echo htmlspecialchars($telefon); ?></td>
    <td><?php echo htmlspecialchars($marke); ?></td>
    <td><?php echo htmlspecialchars($modell); ?></td>
    <td><?php echo htmlspecialchars($problem); ?></td>
    <td><?php echo htmlspecialchars($other); ?></td>
    <td><?php echo htmlspecialchars($pin); ?></td>
    <td>
        <?php if ($musterImagePath): ?>
            <a href="/formular/<?php echo htmlspecialchars($musterImagePath); ?>" target="_blank" class="button-link">Ansehen</a>
        <?php else: ?>
            Kein Muster
        <?php endif; ?>
    </td>
    <td>
            
            <td>
                <form action="loesche_eintrag.php" method="post">
                    <input type="hidden" name="eintrag" value="<?php echo htmlspecialchars($eintrag); ?>">
                    <button type="submit" class="loeschen-btn">Auftrag Löschen</button>
                </form>
                <form action="" method="post">
                    <input type="hidden" name="identifikator" value="<?php echo htmlspecialchars($telefon); ?>">
                    <input type="text" name="kommentar">
                    <input type="submit" class="kommentar-btn" value="Kommentar hinzufügen">
                </form>
                <?php
                if (array_key_exists($telefon, $kommentarArray)) {
                    foreach ($kommentarArray[$telefon] as $kommentarId => $kommentarText): ?>
                        <div class="kommentar-box">
                            <?php echo htmlspecialchars($kommentarText); ?>
                            <form action="" method="post">
                                <input type="hidden" name="loesche_kommentar" value="1">
                                <input type="hidden" name="kommentar_id" value="<?php echo htmlspecialchars($kommentarId); ?>">
                                <button type="submit" class="button-link">Kommentar löschen</button>
                            </form>
                        </div>
                    
                    
                    <?php 
                    
                    endforeach;
                }
                ?>
            </td>
    <td>
        <form action="" method="post">
                <input type="hidden" name="identifikator" value="<?php echo htmlspecialchars($telefon); ?>">
                <input type="hidden" name="markAsDone" value="1">
                <button type="submit" class="button-link erledigt-btn"><?php echo $istErledigt ? 'Rückgängig' : 'Erledigt'; ?></button>
            </form>
    </td>
</tr>
<?php endforeach; ?>

    
</table>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var erledigtButtons = document.querySelectorAll('.erledigt-btn'); // Selektiert alle "Erledigt"-Buttons
    erledigtButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            this.closest('tr').classList.toggle('erledigt');
        });
    });
});
</script>
</body>
</html>
