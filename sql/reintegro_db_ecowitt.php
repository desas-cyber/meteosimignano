<?php
// ============================================================================
// SCRIPT DI IMPORTAZIONE DATI METEO DA CSV ECOWITT A DATABASE
// ============================================================================
// Scopo: Importare dati meteorologici da file CSV Ecowitt nel database MySQL
// Correzioni applicate:
// - Temperatura esterna: -0.2°C
// - Pressione relativa: corretta per altitudine 418m - commentata perchè ora l'interfaccia è calibrata per l'altezza
// - Radiazione solare: *0.95
// - Vento: convertito da m/s a km/h
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
    require_once __DIR__ . '/../../envelop.php';
    require_once __DIR__ . '/../env_tables_helper.php';
    $table_name = table_name('dati_meteo_simignano');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ========================================================================
    // APERTURA E VERIFICA FILE CSV
    // ========================================================================
    $csvPath = __DIR__ . "/ecowitt_backup.csv";
    
    echo "Percorso CSV: " . realpath($csvPath) . "<br>\n";
    echo "File esiste? " . (file_exists($csvPath) ? 'SÌ' : 'NO') . "<br>\n";
    
    if (!file_exists($csvPath)) {
        throw new Exception("File CSV non trovato: $csvPath");
    }
    
    $handle = fopen($csvPath, "r");
    if ($handle === false) {
        throw new Exception("Impossibile aprire il file CSV");
    }
    echo "File aperto con successo!<br>\n";

    // ========================================================================
    // LETTURA INTESTAZIONE CSV
    // ========================================================================
    // Legge la prima riga per mappare le colonne
    $header = fgetcsv($handle);
    
    // Trova gli indici delle colonne necessarie
    $colIndexes = [
        'time' => array_search('Time', $header),
        'temp_out' => array_search('Outdoor Temperature(°C)', $header),
        'humidity' => array_search('Outdoor Humidity(%)', $header),
        'dewpoint' => array_search('Dew Point(°C)', $header),
        'pressure_rel' => array_search('REL Pressure(hPa)', $header),
        'solar_rad' => array_search('Solar Rad(W/m2)', $header),
        'wind' => array_search('Wind(m/s)', $header),
        'gust' => array_search('Gust(m/s)', $header),
        'wind_dir' => array_search('Wind Direction(deg)', $header)
    ];
    
    // Verifica che le colonne essenziali esistano
    if ($colIndexes['time'] === false) {
        throw new Exception("Colonna 'Time' non trovata nel CSV");
    }
    
    echo "Colonne trovate: " . json_encode($colIndexes) . "<br>\n";

    // ========================================================================
    // CONTATORI STATISTICHE
    // ========================================================================
    $inseriti = 0;
    $aggiornati = 0;
    $saltati = 0;

    // ========================================================================
    // PREPARAZIONE QUERY SQL
    // ========================================================================
    $sel = $pdo->prepare("
        SELECT data_ora 
        FROM $table_name 
        WHERE data_ora = ?
    ");

    $ins = $pdo->prepare("
        INSERT INTO $table_name
            (data_ora, temperatura_C, umidita_RH, dew_point_C, pressione_hPa, 
             vento_kmh, direzione_vento_deg, radianza_wm2)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $upd = $pdo->prepare("
        UPDATE $table_name
        SET
            temperatura_C = COALESCE(temperatura_C, ?),
            umidita_RH = COALESCE(umidita_RH, ?),
            dew_point_C = COALESCE(dew_point_C, ?),
            pressione_hPa = COALESCE(pressione_hPa, ?),
            vento_kmh = COALESCE(vento_kmh, ?),
            direzione_vento_deg = COALESCE(direzione_vento_deg, ?),
            radianza_wm2 = COALESCE(radianza_wm2, ?)
        WHERE data_ora = ?
    ");

    // ========================================================================
    // DEFINIZIONE FINESTRA TEMPORALE
    // ========================================================================
    $start = '2026-01-01 00:00:00';
    $end   = '2026-01-31 23:59:59';

    // ========================================================================
    // COSTANTI PER CORREZIONI
    // ========================================================================
    $TEMP_CORRECTION = 1;        // °C da aggiungere alla temperatura; 1 perchè già calibrata dal gatway
    //$ALTITUDE_M = 418;              // Altitudine in metri
    $SOLAR_CORRECTION = 1;       // Fattore moltiplicativo per radiazione solare;1 perchè già calibrata dal gatway
    $MS_TO_KMH = 3.6;              // Conversione m/s a km/h
    
    // Calcolo correzione pressione per altitudine (formula barometrica)
    $PRESSURE_CORRECTION = 0; //$ALTITUDE_M / 8.3; // Circa 50.4 hPa per 418m - 0 perchè già calibrata dal gatway

    // ========================================================================
    // INIZIO TRANSAZIONE
    // ========================================================================
    $pdo->beginTransaction();

    $lineNumber = 1;

    // ========================================================================
    // CICLO DI LETTURA E IMPORTAZIONE CSV
    // ========================================================================
    while (($data = fgetcsv($handle)) !== false) {
        $lineNumber++;
        
        // ====================================================================
        // ESTRAZIONE VALORI DAL CSV
        // ====================================================================
        $timestamp_raw = trim($data[$colIndexes['time']] ?? '');
        
        // ====================================================================
        // VALIDAZIONE E NORMALIZZAZIONE TIMESTAMP
        // ====================================================================
        if ($timestamp_raw === '') {
            $saltati++;
            continue;
        }

        // Il CSV ha formato "YYYY-MM-DD HH:MM" senza secondi
        // Lo convertiamo in "YYYY-MM-DD HH:MM:00" per il database
        $timestamp = $timestamp_raw;
        
        // Verifica formato Y-m-d H:i e aggiungi :00 se mancano i secondi
        if (DateTime::createFromFormat('Y-m-d H:i', $timestamp_raw)) {
            $timestamp = $timestamp_raw . ':00';
        } elseif (!DateTime::createFromFormat('Y-m-d H:i:s', $timestamp_raw)) {
            echo "Avviso: Formato timestamp non valido alla riga $lineNumber: $timestamp_raw<br>\n";
            $saltati++;
            continue;
        }
        
        // Estrai valori grezzi (NULL se colonna non trovata o vuota)
        $temp_raw = ($colIndexes['temp_out'] !== false && isset($data[$colIndexes['temp_out']]) && $data[$colIndexes['temp_out']] !== '') 
            ? floatval($data[$colIndexes['temp_out']]) 
            : null;
            
        $umidita = ($colIndexes['humidity'] !== false && isset($data[$colIndexes['humidity']]) && $data[$colIndexes['humidity']] !== '') 
            ? floatval($data[$colIndexes['humidity']]) 
            : null;
            
        $dew_point = ($colIndexes['dewpoint'] !== false && isset($data[$colIndexes['dewpoint']]) && $data[$colIndexes['dewpoint']] !== '') 
            ? floatval($data[$colIndexes['dewpoint']]) 
            : null;
            
        $pressure_raw = ($colIndexes['pressure_rel'] !== false && isset($data[$colIndexes['pressure_rel']]) && $data[$colIndexes['pressure_rel']] !== '') 
            ? floatval($data[$colIndexes['pressure_rel']]) 
            : null;
            
        $solar_raw = ($colIndexes['solar_rad'] !== false && isset($data[$colIndexes['solar_rad']]) && $data[$colIndexes['solar_rad']] !== '') 
            ? floatval($data[$colIndexes['solar_rad']]) 
            : null;
            
        $wind_ms = ($colIndexes['wind'] !== false && isset($data[$colIndexes['wind']]) && $data[$colIndexes['wind']] !== '') 
            ? floatval($data[$colIndexes['wind']]) 
            : null;
            
        $gust_ms = ($colIndexes['gust'] !== false && isset($data[$colIndexes['gust']]) && $data[$colIndexes['gust']] !== '') 
            ? floatval($data[$colIndexes['gust']]) 
            : null;
            
        $wind_dir = ($colIndexes['wind_dir'] !== false && isset($data[$colIndexes['wind_dir']]) && $data[$colIndexes['wind_dir']] !== '') 
            ? intval($data[$colIndexes['wind_dir']]) 
            : null;

        // ====================================================================
        // APPLICAZIONE CORREZIONI
        // ====================================================================
        // Temperatura: -0.2°C
        $temperatura = $temp_raw !== null ? round($temp_raw - $TEMP_CORRECTION, 2) : null;
        
        // Pressione: corretta per altitudine 418m
        $pressione = $pressure_raw !== null ? round($pressure_raw + $PRESSURE_CORRECTION, 2) : null;
        
        // Radiazione solare: *0.95
        $radianza = $solar_raw !== null ? round($solar_raw * $SOLAR_CORRECTION, 2) : null;
        
        // Vento: usa il valore maggiore tra wind e gust, convertito in km/h
        $vento_max_ms = null;
        if ($wind_ms !== null && $gust_ms !== null) {
            $vento_max_ms = max($wind_ms, $gust_ms);
        } elseif ($wind_ms !== null) {
            $vento_max_ms = $wind_ms;
        } elseif ($gust_ms !== null) {
            $vento_max_ms = $gust_ms;
        }
        $vento_kmh = $vento_max_ms !== null ? round($vento_max_ms * $MS_TO_KMH, 2) : null;
        
        // Direzione vento: valore intero tra 0-359
        $direzione_vento = $wind_dir;

        // ====================================================================
        // FILTRO INTERVALLO TEMPORALE
        // ====================================================================
        if ($timestamp < $start || $timestamp > $end) {
            $saltati++;
            continue;
        }

        // ====================================================================
        // VERIFICA ESISTENZA RECORD
        // ====================================================================
        $sel->execute([$timestamp]);
        $exists = $sel->fetchColumn();

        if ($exists === false) {
            // ================================================================
            // INSERIMENTO NUOVO RECORD
            // ================================================================
            $ins->execute([
                $timestamp, 
                $temperatura, 
                $umidita, 
                $dew_point, 
                $pressione, 
                $vento_kmh,
                $direzione_vento,
                $radianza
            ]);
            $inseriti++;
            
        } else {
            // ================================================================
            // AGGIORNAMENTO RECORD ESISTENTE
            // ================================================================
            $upd->execute([
                $temperatura, 
                $umidita, 
                $dew_point, 
                $pressione, 
                $vento_kmh,
                $direzione_vento,
                $radianza,
                $timestamp
            ]);
            
            if ($upd->rowCount() > 0) {
                $aggiornati++;
            }
        }
    }

    // ========================================================================
    // COMMIT E CHIUSURA
    // ========================================================================
    $pdo->commit();
    fclose($handle);

    // ========================================================================
    // REPORT FINALE
    // ========================================================================
    echo "<br>========================================<br>\n";
    echo "Importazione completata con successo!<br>\n";
    echo "========================================<br>\n";
    /*echo "CORREZIONI APPLICATE:<br>\n";
    echo "- Temperatura: {$TEMP_CORRECTION}°C<br>\n";
    //echo "- Pressione: +" . round($PRESSURE_CORRECTION, 2) . " hPa (altitudine {$ALTITUDE_M}m)<br>\n";
    echo "- Radiazione solare: *{$SOLAR_CORRECTION}<br>\n";
    echo "- Vento: convertito da m/s a km/h (*{$MS_TO_KMH}), usato max tra Wind e Gust<br>\n";
    echo "----------------------------------------<br>\n";*/
    echo "- Inseriti: $inseriti nuovi record<br>\n";
    echo "- Aggiornati: $aggiornati record esistenti<br>\n";
    echo "- Saltati: $saltati record<br>\n";
    echo "- Totale righe elaborate: " . ($inseriti + $aggiornati + $saltati) . "<br>\n";

} catch (Exception $e) {
    // ========================================================================
    // GESTIONE ERRORI
    // ========================================================================
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    if (isset($handle) && $handle !== false) {
        fclose($handle);
    }
    
    die("<br>❌ Errore durante l'importazione: " . $e->getMessage());
}
?>

<!-- 
============================================================================
NOTE SULL'USO:
============================================================================

1. FORMATO TIMESTAMP CSV:
   - CSV Ecowitt usa: YYYY-MM-DD HH:MM (senza secondi)
   - Lo script aggiunge automaticamente :00 per il database
   - Database salva: YYYY-MM-DD HH:MM:00

2. CORREZIONI AUTOMATICHE:
   - Temperatura esterna: -0.2°C
   - Pressione relativa: corretta per 418m di altitudine (+~50.4 hPa)
   - Radiazione solare: moltiplicata per 0.95
   - Vento: convertito da m/s a km/h (moltiplicato per 3.6)
   - Vento: usa il valore MAX tra Wind e Gust

3. MAPPING COLONNE CSV → DATABASE:
   - Time → data_ora (con :00 aggiunto)
   - Outdoor Temperature(°C) → temperatura_C (corretta -0.2°C)
   - Outdoor Humidity(%) → umidita_RH
   - Dew Point(°C) → dew_point_C
   - REL Pressure(hPa) → pressione_hPa (corretta per altitudine)
   - Solar Rad(W/m2) → radianza_wm2 (corretta *0.95)
   - Wind(m/s) + Gust(m/s) → vento_kmh (max dei due, convertito a km/h)
   - Wind Direction(deg) → direzione_vento_deg

============================================================================
-->