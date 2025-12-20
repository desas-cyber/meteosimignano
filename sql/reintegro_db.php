<?php
// ============================================================================
// SCRIPT DI IMPORTAZIONE DATI METEO DA CSV A DATABASE
// ============================================================================
// Scopo: Importare dati meteorologici da file CSV nel database MySQL
// Logica: INSERT per nuovi record, UPDATE solo per campi NULL in record esistenti
// ============================================================================

// Abilita visualizzazione errori per debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================================================
// CONTROLLO SICUREZZA
// ============================================================================
// Verifica token di sicurezza nell'URL (es: script.php?token=abc123)
if (($_GET['token'] ?? null) !== 'abc123') {
    die("Accesso negato.");
}

try {
    // ========================================================================
    // CONNESSIONE DATABASE
    // ========================================================================
    // Carica file con configurazione PDO (deve definire la variabile $pdo)
    require_once __DIR__ . '/../../envelop.php';
    require_once __DIR__ . '/../env_tables_helper.php';
    $table_name = table_name('dati_meteo_simignano');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ========================================================================
    // APERTURA E VERIFICA FILE CSV
    // ========================================================================
    $csvPath = __DIR__ . "/ecowitt_backup.csv";
    
    // Log diagnostici per verificare il percorso del file
    echo "Percorso CSV: " . realpath($csvPath) . "<br>\n";
    echo "File esiste? " . (file_exists($csvPath) ? 'SÌ' : 'NO') . "<br>\n";
    echo "Directory corrente: " . getcwd() . "<br>\n";
    
    // Verifica che il file esista prima di procedere
    if (!file_exists($csvPath)) {
        throw new Exception("File CSV non trovato: $csvPath");
    }
    
    echo "Tentativo di apertura file...<br>\n";
    $handle = fopen($csvPath, "r");
    if ($handle === false) {
        throw new Exception("Impossibile aprire il file CSV");
    }
    echo "File aperto con successo!<br>\n";

    // ========================================================================
    // LETTURA INTESTAZIONE CSV
    // ========================================================================
    // Salta la prima riga (intestazione) del CSV
    // Esempio intestazione: timestamp,temperatura,umidita,dew_point,pressione
    fgetcsv($handle);

    // ========================================================================
    // CONTATORI STATISTICHE
    // ========================================================================
    $inseriti = 0;   // Nuovi record inseriti
    $aggiornati = 0; // Record esistenti aggiornati
    $saltati = 0;    // Record ignorati (fuori intervallo o non validi)

    // ========================================================================
    // PREPARAZIONE QUERY SQL (PREPARED STATEMENTS)
    // ========================================================================
    
    // Query di verifica esistenza record
    // Cerca se esiste già un record con lo stesso timestamp
    $sel = $pdo->prepare("
        SELECT data_ora 
        FROM $table_name 
        WHERE data_ora = ?
    ");

    // Query di inserimento nuovo record
    // Inserisce tutti i campi disponibili
    $ins = $pdo->prepare("
        INSERT INTO $table_name
            (data_ora, temperatura_C, umidita_RH, dew_point_C, pressione_hPa)
        VALUES (?, ?, ?, ?, ?)
    ");

    // Query di aggiornamento record esistente
    // COALESCE(campo_db, ?) significa: "usa il valore del DB, ma se è NULL usa il nuovo valore"
    // Questo preserva i dati esistenti e riempie solo i NULL
    $upd = $pdo->prepare("
        UPDATE $table_name
        SET
            temperatura_C = COALESCE(temperatura_C, ?),
            umidita_RH    = COALESCE(umidita_RH, ?),
            dew_point_C   = COALESCE(dew_point_C, ?),
            pressione_hPa = COALESCE(pressione_hPa, ?)
        WHERE data_ora = ?
    ");

    // ========================================================================
    // DEFINIZIONE FINESTRA TEMPORALE
    // ========================================================================
    // Importa solo dati compresi tra maggio e settembre 2025
    $start = '2025-11-29 13:55:00';
    $end   = '2025-12-08 22:10:59';

    // ========================================================================
    // INIZIO TRANSAZIONE
    // ========================================================================
    // Usa una transazione per garantire atomicità:
    // - O tutti i dati vengono importati correttamente
    // - O nessun dato viene salvato (rollback in caso di errore)
    $pdo->beginTransaction();

    $lineNumber = 1; // Contatore righe per debugging (esclude header)

    // ========================================================================
    // CICLO DI LETTURA E IMPORTAZIONE CSV
    // ========================================================================
    while (($data = fgetcsv($handle)) !== false) {
        $lineNumber++;
        
        // ====================================================================
        // PARSING COLONNE CSV
        // ====================================================================
        // Struttura CSV attesa: timestamp,temperatura,umidita,dew_point,pressione
        // Se una colonna non esiste o è vuota, il valore diventa NULL
        // Se si usa un back-up ove interessa solo un campo, commentare tutte le righe lasciando quella di interesse controllando la poszizione della riga: es. per la pressione dal sensore di piazza:
        // invertire pressione e dewp  $pressione   = isset($data[3]) && $data[3] !== '' ? floatval($data[3]) : null;$dew_point   = isset($data[4]) && $data[4] !== '' ? floatval($data[3]) : null;
        
        $timestamp   = trim($data[0] ?? '');
        
        //Temperatura (colonna 1): converte a float se presente, altrimenti NULL
        $temperatura = isset($data[1]) && $data[1] !== '' ? floatval($data[1]) : null;
        
        // Umidità (colonna 2): converte a float se presente, altrimenti NULL
        $umidita     = isset($data[2]) && $data[2] !== '' ? floatval($data[2]) : null;
        
        // Dew Point (colonna 3): converte a float se presente, altrimenti NULL
        $dew_point   = isset($data[3]) && $data[3] !== '' ? floatval($data[3]) : null;
        
        // Pressione (colonna 4): converte a float se presente, altrimenti NULL
        $pressione   = isset($data[4]) && $data[4] !== '' ? floatval($data[4]) : null;

        // ====================================================================
        // VALIDAZIONE TIMESTAMP
        // ====================================================================
        // Salta righe senza timestamp valido (campo obbligatorio)
        if ($timestamp === '') {
            $saltati++;
            continue;
        }

        // Verifica che il timestamp sia nel formato corretto: YYYY-MM-DD HH:MM:SS
        if (!DateTime::createFromFormat('Y-m-d H:i:s', $timestamp)) {
            echo "Avviso: Formato timestamp non valido alla riga $lineNumber: $timestamp<br>\n";
            $saltati++;
            continue;
        }

        // ====================================================================
        // FILTRO INTERVALLO TEMPORALE
        // ====================================================================
        // Salta record fuori dalla finestra maggio-settembre 2025
        if ($timestamp < $start || $timestamp > $end) {
            $saltati++;
            continue;
        }

        // ====================================================================
        // VERIFICA ESISTENZA RECORD
        // ====================================================================
        // Controlla se esiste già un record con questo timestamp
        $sel->execute([$timestamp]);
        $exists = $sel->fetchColumn();

        if ($exists === false) {
            // ================================================================
            // INSERIMENTO NUOVO RECORD
            // ================================================================
            // Il record non esiste, quindi lo inseriamo completamente
            $ins->execute([$timestamp, $temperatura, $umidita, $dew_point, $pressione]);
            $inseriti++;
            
        } else {
            // ================================================================
            // AGGIORNAMENTO RECORD ESISTENTE
            // ================================================================
            // Il record esiste già, aggiorniamo SOLO i campi che sono NULL nel DB
            // Esempio: se il DB ha temperatura=15.5 e il CSV ha temperatura=16.0,
            // il valore 15.5 viene mantenuto (COALESCE preserva il valore esistente)
            $upd->execute([$temperatura, $umidita, $dew_point, $pressione, $timestamp]);
            
            // Conta solo se è stato realmente aggiornato almeno un campo
            // rowCount() > 0 significa che almeno un campo NULL è stato riempito
            if ($upd->rowCount() > 0) {
                $aggiornati++;
            }
        }
    }

    // ========================================================================
    // COMMIT E CHIUSURA
    // ========================================================================
    // Conferma tutte le modifiche al database
    $pdo->commit();
    
    // Chiude il file CSV
    fclose($handle);

    // ========================================================================
    // REPORT FINALE
    // ========================================================================
    echo "<br>========================================<br>\n";
    echo "Importazione completata con successo!<br>\n";
    echo "========================================<br>\n";
    echo "- Inseriti: $inseriti nuovi record<br>\n";
    echo "- Aggiornati: $aggiornati record esistenti (campi NULL riempiti)<br>\n";
    echo "- Saltati: $saltati record (fuori intervallo o non validi)<br>\n";
    echo "- Totale righe elaborate: " . ($inseriti + $aggiornati + $saltati) . "<br>\n";

} catch (Exception $e) {
    // ========================================================================
    // GESTIONE ERRORI
    // ========================================================================
    // In caso di errore, annulla tutte le modifiche (rollback)
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Chiude il file se era aperto
    if (isset($handle) && $handle !== false) {
        fclose($handle);
    }
    
    // Mostra messaggio di errore e termina lo script
    die("<br>❌ Errore durante l'importazione: " . $e->getMessage());
}
?>

<!-- 
============================================================================
NOTE SULL'USO:
============================================================================

1. STRUTTURA CSV ATTESA:
   timestamp,temperatura,umidita,dew_point,pressione
   2025-05-15 10:00:00,18.5,65,12.3,1013.2
   2025-05-15 11:00:00,19.2,63,12.8,1013.5

2. COLONNE MANCANTI:
   Se il CSV ha solo alcune colonne (es: solo timestamp e dew_point):
   - Le altre colonne diventeranno NULL
   - Il DB non verrà sovrascritto se ha già valori
   
3. CSV PARZIALE ESEMPIO:
   timestamp,dew_point
   2025-05-15 10:00:00,12.3
   
   In questo caso:
   - temperatura, umidita, pressione saranno NULL nel CSV
   - Ma COALESCE preserverà i valori esistenti nel DB
   - Solo dew_point verrà aggiornato se era NULL

4. SICUREZZA:
   - Usa sempre il token nell'URL: script.php?token=abc123
   - Cambia il token in produzione!

5. TRANSAZIONI:
   - Se qualcosa va storto, NESSUN dato viene salvato (rollback)
   - Garantisce coerenza dei dati

============================================================================
-->