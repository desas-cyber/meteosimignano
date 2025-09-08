<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verifica token di sicurezza
if (($_GET['token'] ?? null) !== 'abc123') {
    die("Accesso negato.");
}

try {
    require_once __DIR__ . '/../../envelop.php'; // deve definire $pdo (PDO)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- Apri CSV ---
    $csvPath = "ecowitt_backup.csv";
    
    echo "Percorso CSV: " . realpath($csvPath) . "<br>\n";
    echo "File esiste? " . (file_exists($csvPath) ? 'SÌ' : 'NO') . "<br>\n";
    echo "Directory corrente: " . getcwd() . "<br>\n";
    
    // Verifica esistenza file
    if (!file_exists($csvPath)) {
        throw new Exception("File CSV non trovato: $csvPath");
    }
    
    echo "Tentativo di apertura file...<br>\n";
    $handle = fopen($csvPath, "r");
    if ($handle === false) {
        throw new Exception("Impossibile aprire il file CSV");
    }
    echo "File aperto con successo!<br>\n";

    // Salta intestazione
    fgetcsv($handle);

    $inseriti = 0;
    $aggiornati = 0;
    $saltati = 0;

    // Prepara statement riutilizzabili
    $sel = $pdo->prepare("
        SELECT data_ora 
        FROM dati_meteo_simignano 
        WHERE data_ora = ?
    ");

    $ins = $pdo->prepare("
        INSERT INTO dati_meteo_simignano
            (data_ora, temperatura_C, umidita_RH, dew_point_C, pressione_hPa)
        VALUES (?, ?, ?, ?, ?)
    ");

    // CORREZIONE: Rimosso pressione_hPa dall'UPDATE dato che non è nel CSV
    $upd = $pdo->prepare("
        UPDATE dati_meteo_simignano
        SET
            temperatura_C = COALESCE(temperatura_C, ?),
            umidita_RH    = COALESCE(umidita_RH, ?),
            dew_point_C   = COALESCE(dew_point_C, ?)
        WHERE data_ora = ?
    ");

    // Finestra temporale (da maggio a settembre)
    $start = '2025-05-01 00:00:00';
    $end   = '2025-09-30 23:59:59';

    // Transazione per atomicità
    $pdo->beginTransaction();

    $lineNumber = 1; // Per debugging (escludendo header)

    while (($data = fgetcsv($handle)) !== false) {
        $lineNumber++;
        
        $timestamp   = trim($data[0] ?? '');
        $temperatura = isset($data[1]) && $data[1] !== '' ? floatval($data[1]) : null;
        $umidita     = isset($data[2]) && $data[2] !== '' ? floatval($data[2]) : null;
        $dew_point   = isset($data[3]) && $data[3] !== '' ? floatval($data[3]) : null;
        $pressione   = null; // Non presente nel CSV

        // Salta righe senza timestamp valido
        if ($timestamp === '') {
            $saltati++;
            continue;
        }

        // Validazione formato timestamp (opzionale ma consigliata)
        if (!DateTime::createFromFormat('Y-m-d H:i:s', $timestamp)) {
            echo "Avviso: Formato timestamp non valido alla riga $lineNumber: $timestamp<br>\n";
            $saltati++;
            continue;
        }

        // Filtra per intervallo temporale
        if ($timestamp < $start || $timestamp > $end) {
            $saltati++;
            continue;
        }

        // Verifica se il record esiste già
        $sel->execute([$timestamp]);
        $exists = $sel->fetchColumn();

        if ($exists === false) {
            // Non esiste -> INSERT
            $ins->execute([$timestamp, $temperatura, $umidita, $dew_point, $pressione]);
            $inseriti++;
        } else {
            // Esiste -> UPDATE solo dei campi NULL (CORREZIONE: solo 4 parametri ora)
            $upd->execute([$temperatura, $umidita, $dew_point, $timestamp]);
            
            // Conta solo se è stato realmente aggiornato qualcosa
            if ($upd->rowCount() > 0) {
                $aggiornati++;
            }
        }
    }

    $pdo->commit();
    fclose($handle);

    echo "Importazione completata con successo!<br>\n";
    echo "- Inseriti: $inseriti nuovi record<br>\n";
    echo "- Aggiornati: $aggiornati record esistenti (campi NULL riempiti)<br>\n";
    echo "- Saltati: $saltati record (fuori intervallo o non validi)<br>\n";
    echo "- Totale righe elaborate: " . ($inseriti + $aggiornati + $saltati) . "<br>\n";

} catch (Exception $e) {
    // Rollback in caso di errore
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    if (isset($handle) && $handle !== false) {
        fclose($handle);
    }
    
    die("Errore durante l'importazione: " . $e->getMessage());
}
?>