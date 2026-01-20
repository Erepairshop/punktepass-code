<?php
/**
 * Adatbázis telepítő script
 * Futtasd egyszer a böngészőben: https://erepairshop.de/formular/setup_database.php
 * Utána TÖRÖLD ezt a fájlt biztonsági okokból!
 */

require_once __DIR__ . '/db_config.php';

echo "<pre style='font-family: monospace; background: #1a1a2e; color: #00ff88; padding: 20px; border-radius: 10px;'>";
echo "=== Adatbázis telepítés ===\n\n";

try {
    $pdo = getDB();
    echo "[OK] Adatbázis kapcsolat létrejött\n\n";

    // 1. Táblák létrehozása
    echo "--- Táblák létrehozása ---\n";

    // entries tábla
    $pdo->exec("CREATE TABLE IF NOT EXISTS entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        datum VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        telefon VARCHAR(50) NOT NULL,
        marke VARCHAR(100),
        modell VARCHAR(100),
        problem VARCHAR(50),
        other TEXT,
        pin VARCHAR(50),
        muster_image_path VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_telefon (telefon)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[OK] entries tábla létrehozva\n";

    // erledigt_status tábla
    $pdo->exec("CREATE TABLE IF NOT EXISTS erledigt_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        telefon VARCHAR(50) NOT NULL UNIQUE,
        erledigt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_telefon (telefon)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[OK] erledigt_status tábla létrehozva\n";

    // kommentare tábla
    $pdo->exec("CREATE TABLE IF NOT EXISTS kommentare (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kommentar_id VARCHAR(50) NOT NULL UNIQUE,
        telefon VARCHAR(50) NOT NULL,
        kommentar TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_telefon (telefon)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[OK] kommentare tábla létrehozva\n\n";

    // 2. Adatok importálása txt fájlokból
    echo "--- Adatok importálása ---\n";

    // entries.txt importálása
    $entriesFile = __DIR__ . '/entries.txt';
    if (file_exists($entriesFile)) {
        $lines = file($entriesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $imported = 0;
        $skipped = 0;

        $stmt = $pdo->prepare("INSERT IGNORE INTO entries (datum, name, telefon, marke, modell, problem, other, pin, muster_image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 9) {
                try {
                    $stmt->execute([
                        $parts[0], // datum
                        $parts[1], // name
                        $parts[2], // telefon
                        $parts[3], // marke
                        $parts[4], // modell
                        $parts[5], // problem
                        $parts[6], // other
                        $parts[7], // pin
                        $parts[8]  // muster_image_path
                    ]);
                    if ($stmt->rowCount() > 0) {
                        $imported++;
                    } else {
                        $skipped++;
                    }
                } catch (Exception $e) {
                    $skipped++;
                }
            }
        }
        echo "[OK] entries.txt: $imported importálva, $skipped kihagyva\n";
    } else {
        echo "[--] entries.txt nem található\n";
    }

    // erledigt_status.txt importálása
    $erledigtFile = __DIR__ . '/erledigt_status.txt';
    if (file_exists($erledigtFile)) {
        $lines = file($erledigtFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $imported = 0;

        $stmt = $pdo->prepare("INSERT IGNORE INTO erledigt_status (telefon) VALUES (?)");

        foreach ($lines as $telefon) {
            $telefon = trim($telefon);
            if (!empty($telefon)) {
                $stmt->execute([$telefon]);
                if ($stmt->rowCount() > 0) {
                    $imported++;
                }
            }
        }
        echo "[OK] erledigt_status.txt: $imported importálva\n";
    } else {
        echo "[--] erledigt_status.txt nem található\n";
    }

    // kommentare.txt importálása
    $kommentareFile = __DIR__ . '/kommentare.txt';
    if (file_exists($kommentareFile)) {
        $lines = file($kommentareFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $imported = 0;

        $stmt = $pdo->prepare("INSERT IGNORE INTO kommentare (telefon, kommentar_id, kommentar) VALUES (?, ?, ?)");

        foreach ($lines as $line) {
            $parts = explode('|', $line, 3);
            if (count($parts) === 3) {
                $stmt->execute([$parts[0], $parts[1], $parts[2]]);
                if ($stmt->rowCount() > 0) {
                    $imported++;
                }
            }
        }
        echo "[OK] kommentare.txt: $imported importálva\n";
    } else {
        echo "[--] kommentare.txt nem található\n";
    }

    echo "\n=== KÉSZ! ===\n";
    echo "\n<span style='color: #ff6b6b;'>FONTOS: Töröld ezt a fájlt (setup_database.php) a szerverről!</span>\n";

} catch (Exception $e) {
    echo "[HIBA] " . $e->getMessage() . "\n";
}

echo "</pre>";
