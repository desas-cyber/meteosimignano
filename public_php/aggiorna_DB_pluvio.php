<?php
/**
 * Import dati pluviometrici CFR Simignano
 * Scarica JSON da GitHub e salva in precipitazioni_cfr + pluvio_giornaliero + pluvio_record_mensili
 * 
 * LOGICA PLUVIO_GIORNALIERO:
 * - Salva un solo dato per giorno (data = quella di ultimi_dati)
 * - Mantiene solo il dato più vicino alla mezzanotte (00:00:00)
 * - ultimi_dati = timestamp della registrazione effettiva del pluviometro CFR
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../envelop.php';
require_once __DIR__ . '/../env_tables_helper.php';
require_once __DIR__ . '/../datetime_helper.php';

// Configurazione
$JSON_URL = 'https://raw.githubusercontent.com/desas-cyber/simignano-pluvio/main/dati_simignano.json';
$TABLE_CFR = table_name('precipitazioni_cfr');
$TABLE_GIORNALIERO = table_name('pluvio_giornaliero');
$TABLE_RECORD = table_name('pluvio_record_mensili');

/**
 * Funzione di logging
 */
function logmsg($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    error_log("[$timestamp] CFR Import: $message");
}

logmsg("=== INIZIO IMPORT CFR SIMIGNANO ===");

// ==========================================
// 1. SCARICA JSON DA GITHUB
// ==========================================

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $JSON_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'MeteoCFRImporter/1.0');
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

$json_raw = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Gestione errori download
if ($curl_error) {
    logmsg("❌ ERRORE cURL: $curl_error - Uscita");
    exit(1);
} elseif ($http_code != 200) {
    logmsg("❌ ERRORE HTTP: Codice $http_code - Uscita");
    exit(1);
} else {
    logmsg("✅ JSON scaricato da GitHub");
}

// ==========================================
// 2. DECODIFICA JSON
// ==========================================

$json_data = json_decode($json_raw, true);

if (!$json_data || !isset($json_data['dati'][0])) {
    logmsg("❌ JSON non valido o vuoto");
    exit(1);
}

$stazione = $json_data['dati'][0];
$timestamp = $json_data['timestamp'];

logmsg("Stazione: " . $stazione['nome_stazione']);
logmsg("Timestamp JSON: " . $timestamp);
logmsg("Ultimo aggiornamento CFR: " . $json_data['data_aggiornamento']);

// ==========================================
// 3. SALVA IN precipitazioni_cfr
// ==========================================

try {
    $sql = "
    INSERT INTO $TABLE_CFR
    (nome_stazione, prec_1h, prec_3h, prec_6h, prec_12h, prec_24h, ultimi_dati, data_import, fonte)
    VALUES
    (:nome, :p1, :p3, :p6, :p12, :p24, :plast, :dt, :fonte)
    ON DUPLICATE KEY UPDATE
     prec_1h = VALUES(prec_1h),
     prec_3h = VALUES(prec_3h),
     prec_6h = VALUES(prec_6h),
     prec_12h = VALUES(prec_12h),
     prec_24h = VALUES(prec_24h),
     ultimi_dati = VALUES(ultimi_dati),
     data_import = VALUES(data_import)
    ";
    
    $stmt = $pdo->prepare($sql);
    
    $params = [
        ':nome'  => $stazione['nome_stazione'],
        ':p1'    => floatval($stazione['precipitazioni_1h']),
        ':p3'    => floatval($stazione['precipitazioni_3h']),
        ':p6'    => floatval($stazione['precipitazioni_6h']),
        ':p12'   => floatval($stazione['precipitazioni_12h']),
        ':p24'   => floatval($stazione['precipitazioni_24h']),
        ':plast' => $stazione['ultimi_dati'],
        ':dt'    => date('Y-m-d H:i:s'),
        ':fonte' => 'CFR Toscana'
    ];
    
    $stmt->execute($params);
    
    $rowCount = $stmt->rowCount();
    
    if ($rowCount > 0) {
        logmsg("✅ Dati salvati in precipitazioni_cfr (nuovo record)");
    } else {
        logmsg("ℹ️  Dati già presenti in precipitazioni_cfr, nessun inserimento (ultimi_dati duplicato)");
    }
    
} catch (PDOException $e) {
    logmsg("❌ ERRORE DATABASE precipitazioni_cfr: " . $e->getMessage());
    exit(1);
}
// ==========================================
// 3.5 PULIZIA DATI VECCHI (> 96h)
// ==========================================

try {
    // Elimina record più vecchi di 96 ore (4 giorni)
    $sql_cleanup = "
        DELETE FROM $TABLE_CFR 
        WHERE data_import < DATE_SUB(NOW(), INTERVAL 96 HOUR)
    ";
    
    $stmt_cleanup = $pdo->prepare($sql_cleanup);
    $stmt_cleanup->execute();
    $deleted_count = $stmt_cleanup->rowCount();
    
    if ($deleted_count > 0) {
        logmsg("🗑️  Eliminati $deleted_count record più vecchi di 96h da precipitazioni_cfr");
    } else {
        logmsg("ℹ️  Nessun record da eliminare (tutti < 96h)");
    }
    
} catch (PDOException $e) {
    logmsg("⚠️  ERRORE durante pulizia dati vecchi: " . $e->getMessage());
    // Non bloccare lo script per errori di pulizia
}
// ==========================================
// 4. ESTRAI VALORI E DATETIME
// ==========================================

try {
    // PARSING TIMESTAMP DELLA REGISTRAZIONE EFFETTIVA (pluviometro CFR)
    // Formato CFR: "28/12 00.15" → deve essere convertito in "2025-12-28 00:15:00"
    
    $ultimi_dati_raw = $stazione['ultimi_dati'];
    
    // Pattern: "DD/MM HH.MM" (es. "28/12 00.15")
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\s+(\d{1,2})\.(\d{2})$/', $ultimi_dati_raw, $matches)) {
        $giorno = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $mese_num = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $ora = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
        $minuti = $matches[4];
        
        // Anno corrente (assumiamo anno corrente)
        $anno_corrente = date('Y');
        
        // Costruisci stringa MySQL completa
        $ultimi_dati_mysql = "$anno_corrente-$mese_num-$giorno $ora:$minuti:00";
        
        // Crea oggetto DateTime
        $datetime_lettura = new DateTime($ultimi_dati_mysql);
        
        logmsg("📅 Parsing ultimi_dati: '$ultimi_dati_raw' → '$ultimi_dati_mysql'");
        
    } else {
        // Fallback: prova parsing diretto (potrebbe essere formato ISO)
        $datetime_lettura = new DateTime($ultimi_dati_raw);
        $ultimi_dati_mysql = $datetime_lettura->format('Y-m-d H:i:s');
        logmsg("📅 Parsing ultimi_dati (ISO): '$ultimi_dati_raw' → '$ultimi_dati_mysql'");
    }
    
    $anno = (int)$datetime_lettura->format('Y');
    $mese = (int)$datetime_lettura->format('m');
    $data_giorno = $datetime_lettura->format('Y-m-d');
    
    // Timestamp JSON (per record mensili - formato ISO standard)
    $datetime_json = new DateTime($timestamp);
    $timestamp_mysql = $datetime_json->format('Y-m-d H:i:s');
    
    // Valori precipitazioni
    $prec_1h = floatval($stazione['precipitazioni_1h']);
    $prec_6h = floatval($stazione['precipitazioni_6h']);
    $prec_12h = floatval($stazione['precipitazioni_12h']);
    $prec_24h = floatval($stazione['precipitazioni_24h']);
    
    logmsg("Data giorno (da ultimi_dati): $data_giorno | Anno: $anno | Mese: $mese");
    logmsg("Ultimi_dati (registrazione CFR): $ultimi_dati_mysql");
    logmsg("Timestamp JSON: $timestamp_mysql");
    logmsg("Precipitazioni 1h: $prec_1h mm");
    logmsg("Precipitazioni 6h: $prec_6h mm");
    logmsg("Precipitazioni 12h: $prec_12h mm");
    logmsg("Precipitazioni 24h: $prec_24h mm");
    
} catch (Exception $e) {
    logmsg("❌ ERRORE parsing timestamp: " . $e->getMessage());
    logmsg("   Valore raw: " . ($stazione['ultimi_dati'] ?? 'NULL'));
    exit(1);
}

// ==========================================
// 5. SALVA IN pluvio_giornaliero
//    LOGICA: mantieni solo il dato più vicino alla FINE del giorno (23:59:59)
// ==========================================

try {
    // 1. Verifica se esiste già un record per questa data
    $sql_check = "SELECT ultimi_dati FROM $TABLE_GIORNALIERO WHERE data = :data";
    $stmt = $pdo->prepare($sql_check);
    $stmt->execute([':data' => $data_giorno]);
    $record_esistente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // MEZZANOTTE = FINE GIORNO (23:59:59) non inizio!
    // Il giorno pluviometrico va da 00:00:00 a 23:59:59
    $fine_giorno = new DateTime($data_giorno . ' 23:59:59');
    $nuovo_timestamp = $datetime_lettura;
    
    // Calcola distanza dalla fine del giorno (in secondi assoluti)
    $distanza_nuovo = abs($fine_giorno->getTimestamp() - $nuovo_timestamp->getTimestamp());
    
    if (!$record_esistente) {
        // CASO 1: Nessun record esistente → INSERT
        $sql_insert = "
            INSERT INTO $TABLE_GIORNALIERO 
            (data, cumulato_24h, ultimi_dati) 
            VALUES (:data, :cumulato_24h, :ultimi_dati)
        ";
        
        $stmt = $pdo->prepare($sql_insert);
        $stmt->execute([
            ':data' => $data_giorno,
            ':cumulato_24h' => $prec_24h,
            ':ultimi_dati' => $ultimi_dati_mysql
        ]);
        
        logmsg("✅ Nuovo dato giornaliero inserito per $data_giorno");
        logmsg("   Cumulato 24h: $prec_24h mm");
        logmsg("   Ultimi_dati (registrazione): $ultimi_dati_mysql");
        logmsg("   Distanza da fine giorno (23:59): " . gmdate('H:i:s', $distanza_nuovo));
        
    } else {
        // CASO 2: Record esistente → verifica se il nuovo è più vicino alla fine giorno
        $vecchio_timestamp = new DateTime($record_esistente['ultimi_dati']);
        $distanza_vecchio = abs($fine_giorno->getTimestamp() - $vecchio_timestamp->getTimestamp());
        
        if ($distanza_nuovo < $distanza_vecchio) {
            // Il nuovo dato è più vicino alla fine giorno → UPDATE
            $sql_update = "
                UPDATE $TABLE_GIORNALIERO 
                SET cumulato_24h = :cumulato_24h,
                    ultimi_dati = :ultimi_dati
                WHERE data = :data
            ";
            
            $stmt = $pdo->prepare($sql_update);
            $stmt->execute([
                ':data' => $data_giorno,
                ':cumulato_24h' => $prec_24h,
                ':ultimi_dati' => $ultimi_dati_mysql
            ]);
            
            logmsg("🔄 Dato giornaliero AGGIORNATO per $data_giorno (più vicino a fine giorno)");
            logmsg("   Vecchio ultimi_dati: " . $record_esistente['ultimi_dati'] . " (distanza da 23:59: " . gmdate('H:i:s', $distanza_vecchio) . ")");
            logmsg("   Nuovo ultimi_dati:   $ultimi_dati_mysql (distanza da 23:59: " . gmdate('H:i:s', $distanza_nuovo) . ")");
            logmsg("   Nuovo cumulato 24h: $prec_24h mm");
            
        } else {
            // Il vecchio dato è più vicino → mantieni quello
            logmsg("ℹ️  Dato giornaliero NON aggiornato per $data_giorno (vecchio più vicino a fine giorno)");
            logmsg("   Vecchio ultimi_dati: " . $record_esistente['ultimi_dati'] . " (distanza da 23:59: " . gmdate('H:i:s', $distanza_vecchio) . ") ✓");
            logmsg("   Nuovo ultimi_dati:   $ultimi_dati_mysql (distanza da 23:59: " . gmdate('H:i:s', $distanza_nuovo) . ") ✗");
        }
    }
    
} catch (PDOException $e) {
    logmsg("❌ ERRORE inserimento/aggiornamento giornaliero: " . $e->getMessage());
    exit(1);
}

// ==========================================
// 6. AGGIORNA RECORD MENSILI
// ==========================================

try {
    // Verifica se esiste il record per questo mese
    $sql_check = "SELECT * FROM $TABLE_RECORD WHERE anno = :anno AND mese = :mese";
    $stmt = $pdo->prepare($sql_check);
    $stmt->execute([':anno' => $anno, ':mese' => $mese]);
    $record_esistente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record_esistente) {
        // Crea nuovo record mensile
        $sql_insert = "
            INSERT INTO $TABLE_RECORD 
            (anno, mese, record_24h, record_12h, record_6h, record_1h, 
             data_record_24h, data_record_12h, data_record_6h, data_record_1h)
            VALUES (:anno, :mese, :r24, :r12, :r6, :r1, :d24, :d12, :d6, :d1)
        ";
        
        $stmt = $pdo->prepare($sql_insert);
        $stmt->execute([
            ':anno' => $anno,
            ':mese' => $mese,
            ':r24' => $prec_24h,
            ':r12' => $prec_12h,
            ':r6' => $prec_6h,
            ':r1' => $prec_1h,
            ':d24' => $timestamp_mysql,
            ':d12' => $timestamp_mysql,
            ':d6' => $timestamp_mysql,
            ':d1' => $timestamp_mysql
        ]);
        
        logmsg("✅ Nuovo record mensile creato per $mese/$anno");
        logmsg("   24h: $prec_24h mm");
        logmsg("   12h: $prec_12h mm");
        logmsg("   6h: $prec_6h mm");
        logmsg("   1h: $prec_1h mm");
        
    } else {
        // Aggiorna solo i record superati
        $updates = [];
        $params = [':anno' => $anno, ':mese' => $mese];
        
        if ($prec_24h > $record_esistente['record_24h']) {
            $updates[] = "record_24h = :r24, data_record_24h = :d24";
            $params[':r24'] = $prec_24h;
            $params[':d24'] = $timestamp_mysql;
            logmsg("🏆 Nuovo record 24h: $prec_24h mm (precedente: {$record_esistente['record_24h']})");
        }
        
        if ($prec_12h > $record_esistente['record_12h']) {
            $updates[] = "record_12h = :r12, data_record_12h = :d12";
            $params[':r12'] = $prec_12h;
            $params[':d12'] = $timestamp_mysql;
            logmsg("🏆 Nuovo record 12h: $prec_12h mm (precedente: {$record_esistente['record_12h']})");
        }
        
        if ($prec_6h > $record_esistente['record_6h']) {
            $updates[] = "record_6h = :r6, data_record_6h = :d6";
            $params[':r6'] = $prec_6h;
            $params[':d6'] = $timestamp_mysql;
            logmsg("🏆 Nuovo record 6h: $prec_6h mm (precedente: {$record_esistente['record_6h']})");
        }
        
        if ($prec_1h > $record_esistente['record_1h']) {
            $updates[] = "record_1h = :r1, data_record_1h = :d1";
            $params[':r1'] = $prec_1h;
            $params[':d1'] = $timestamp_mysql;
            logmsg("🏆 Nuovo record 1h: $prec_1h mm (precedente: {$record_esistente['record_1h']})");
        }
        
        if (!empty($updates)) {
            $sql_update = "UPDATE $TABLE_RECORD SET " . implode(', ', $updates) . 
                         " WHERE anno = :anno AND mese = :mese";
            $stmt = $pdo->prepare($sql_update);
            $stmt->execute($params);
            logmsg("✅ Record mensili aggiornati");
        } else {
            logmsg("ℹ️  Nessun record superato per $mese/$anno");
        }
    }
    
} catch (PDOException $e) {
    logmsg("❌ ERRORE aggiornamento record: " . $e->getMessage());
    exit(1);
}

// ==========================================
// 7. RIEPILOGO FINALE
// ==========================================

logmsg("=== FINE IMPORT CFR ===");
logmsg("Precipitazioni:");
logmsg("  1h:  $prec_1h mm");
logmsg("  6h:  $prec_6h mm");
logmsg("  12h: $prec_12h mm");
logmsg("  24h: $prec_24h mm");
logmsg("Data salvata: $data_giorno");
logmsg("Ultimi_dati (registrazione CFR): $ultimi_dati_mysql");

exit(0);
?>