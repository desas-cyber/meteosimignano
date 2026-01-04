<?php
/**
 * Import dati pluviometrici CFR Simignano
 * Scarica JSON da GitHub e salva in precipitazioni_cfr + pluvio_giornaliero + pluvio_record_mensili
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
    logmsg("ERRORE cURL: $curl_error - Uscita");
    exit(1);
} elseif ($http_code != 200) {
    logmsg("ERRORE HTTP: Codice $http_code - Uscita");
    exit(1);
} else {
    logmsg("JSON scaricato da GitHub");
}

// ==========================================
// 2. DECODIFICA JSON
// ==========================================

$json_data = json_decode($json_raw, true);

if (!$json_data || !isset($json_data['dati'][0])) {
    logmsg("JSON non valido o vuoto");
    exit(1);
}

$stazione = $json_data['dati'][0];
$timestamp = $json_data['timestamp'];

logmsg("=== DATI RICEVUTI DAL JSON ===");
logmsg("Stazione: " . $stazione['nome_stazione']);
logmsg("Timestamp JSON (completo): " . $timestamp);
logmsg("Ultimi dati campo (solo gg/mm): " . $stazione['ultimi_dati']);
logmsg("Data aggiornamento CFR: " . $json_data['data_aggiornamento']);
logmsg("Ora server PHP: " . date('Y-m-d H:i:s'));

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
        logmsg("Dati salvati in precipitazioni_cfr (nuovo record)");
    } else {
        logmsg("Dati gia presenti in precipitazioni_cfr, nessun inserimento (ultimi_dati duplicato)");
    }
    
} catch (PDOException $e) {
    logmsg("ERRORE DATABASE precipitazioni_cfr: " . $e->getMessage());
    exit(1);
}

// ==========================================
// 4. ESTRAI VALORI E DATETIME
// ==========================================

try {
    // USA IL TIMESTAMP JSON CHE CONTIENE L'ANNO COMPLETO
    // IMPORTANTE: il timestamp e in UTC, va convertito a Europe/Rome
    logmsg("=== PARSING TIMESTAMP ===");
    logmsg("Input timestamp UTC: '$timestamp'");
    
    $datetime = new DateTime($timestamp, new DateTimeZone('UTC'));
    $datetime->setTimezone(new DateTimeZone('Europe/Rome'));
    
    $anno = (int)$datetime->format('Y');
    $mese = (int)$datetime->format('m');
    $giorno = (int)$datetime->format('d');
    $data_oggi = $datetime->format('Y-m-d');
    
    // CONVERTI IL TIMESTAMP IN FORMATO MYSQL
    $timestamp_mysql = $datetime->format('Y-m-d H:i:s');
    
    logmsg("DateTime UTC: " . (new DateTime($timestamp, new DateTimeZone('UTC')))->format('Y-m-d H:i:s'));
    logmsg("DateTime Italia (Europe/Rome): " . $datetime->format('Y-m-d H:i:s'));
    logmsg("Anno estratto: $anno");
    logmsg("Mese estratto: $mese");
    logmsg("Giorno estratto: $giorno");
    logmsg("Data per DB (data_oggi): $data_oggi");
    
    // PARSING ultimi_dati per ottenere il timestamp REALE della registrazione
    // formato: "01/01 00.45" -> gg/mm hh.mm
    $ultimi_dati_str = $stazione['ultimi_dati'];
    if (preg_match('/(\d{2})\/(\d{2})\s+(\d{2})\.(\d{2})/', $ultimi_dati_str, $matches)) {
        $giorno_registro = (int)$matches[1];
        $mese_registro = (int)$matches[2];
        $ora_registro = (int)$matches[3];
        $minuti_registro = (int)$matches[4];
        
        // LOGICA ANNO INTELLIGENTE per gestire il cambio anno
        // Il timestamp JSON potrebbe essere di un anno diverso rispetto ai dati effettivi
        $anno_registro = $anno;
        
        if ($mese == 1 && $mese_registro == 12) {
            // Siamo a gennaio ma i dati sono di dicembre -> anno precedente
            $anno_registro = $anno - 1;
            logmsg("ATTENZIONE: Dati di dicembre in gennaio, uso anno precedente: $anno_registro");
        } elseif ($mese == 12 && $mese_registro == 1) {
            // Siamo a dicembre ma i dati sono di gennaio -> anno successivo
            $anno_registro = $anno + 1;
            logmsg("ATTENZIONE: Dati di gennaio in dicembre, uso anno successivo: $anno_registro");
        }
        
        // Usa l'anno calcolato con la logica intelligente
        $timestamp_registrazione = sprintf('%04d-%02d-%02d %02d:%02d:00', 
            $anno_registro, $mese_registro, $giorno_registro, $ora_registro, $minuti_registro);
        
        // Data del giorno per la tabella giornaliera (basata su ultimi_dati)
        $data_registrazione = sprintf('%04d-%02d-%02d', $anno_registro, $mese_registro, $giorno_registro);
        
        logmsg("Ultimi_dati parsed: '$ultimi_dati_str' -> $timestamp_registrazione");
        logmsg("Data registrazione (per tabella giornaliera): $data_registrazione");
    } else {
        // Fallback: usa il timestamp JSON convertito
        $timestamp_registrazione = $timestamp_mysql;
        $data_registrazione = $data_oggi;
        logmsg("ATTENZIONE: ultimi_dati non parsabile, uso timestamp JSON");
    }
    
    logmsg("Timestamp MySQL (generazione JSON): $timestamp_mysql");
    logmsg("Timestamp registrazione dati (da ultimi_dati): $timestamp_registrazione");
    
    // Valori precipitazioni
    $prec_1h = floatval($stazione['precipitazioni_1h']);
    $prec_6h = floatval($stazione['precipitazioni_6h']);
    $prec_12h = floatval($stazione['precipitazioni_12h']);
    $prec_24h = floatval($stazione['precipitazioni_24h']);
    
    logmsg("=== VALORI PRECIPITAZIONI ===");
    logmsg("1h:  $prec_1h mm");
    logmsg("6h:  $prec_6h mm");
    logmsg("12h: $prec_12h mm");
    logmsg("24h: $prec_24h mm");
    
} catch (Exception $e) {
    logmsg("ERRORE parsing timestamp: " . $e->getMessage());
    exit(1);
}

// ==========================================
// 5. SALVA IN pluvio_giornaliero
// ==========================================

logmsg("=== SALVATAGGIO DATI GIORNALIERI ===");
logmsg("Tabella: $TABLE_GIORNALIERO");
logmsg("Data da salvare: $data_registrazione");
logmsg("Cumulato 24h: $prec_24h mm");

try {
    $sql_giornaliero = "
        INSERT INTO $TABLE_GIORNALIERO (data, cumulato_24h, ultimi_dati) 
        VALUES (:data, :cumulato_24h, :ultimi_dati)
        ON DUPLICATE KEY UPDATE 
            cumulato_24h = VALUES(cumulato_24h),
            ultimi_dati = VALUES(ultimi_dati)
    ";
    
    $stmt = $pdo->prepare($sql_giornaliero);
    $stmt->execute([
        ':data' => $data_registrazione,
        ':cumulato_24h' => $prec_24h,
        ':ultimi_dati' => $timestamp_registrazione
    ]);
    
    $affected = $stmt->rowCount();
    logmsg("Query eseguita (righe affette: $affected)");
    logmsg("Dati giornalieri aggiornati per $data_registrazione: $prec_24h mm");
    
} catch (PDOException $e) {
    logmsg("ERRORE inserimento giornaliero: " . $e->getMessage());
    exit(1);
}

// ==========================================
// 6. AGGIORNA RECORD MENSILI
// ==========================================

logmsg("=== AGGIORNAMENTO RECORD MENSILI ===");
logmsg("Tabella: $TABLE_RECORD");
logmsg("Anno/Mese da aggiornare: $anno/$mese");

try {
    // Verifica se esiste il record per questo mese
    $sql_check = "SELECT * FROM $TABLE_RECORD WHERE anno = :anno AND mese = :mese";
    $stmt = $pdo->prepare($sql_check);
    $stmt->execute([':anno' => $anno, ':mese' => $mese]);
    $record_esistente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record_esistente) {
        logmsg("Record mensile NON esistente - creo nuovo record");
        logmsg("   Anno: $anno, Mese: $mese");
        logmsg("   Data record: $timestamp_registrazione");
        
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
            ':d24' => $timestamp_registrazione,
            ':d12' => $timestamp_registrazione,
            ':d6' => $timestamp_registrazione,
            ':d1' => $timestamp_registrazione
        ]);
        
        logmsg("Nuovo record mensile creato per $mese/$anno");
        logmsg("   24h: $prec_24h mm");
        logmsg("   12h: $prec_12h mm");
        logmsg("   6h: $prec_6h mm");
        logmsg("   1h: $prec_1h mm");
        
    } else {
        logmsg("Record mensile ESISTENTE per $mese/$anno");
        logmsg("   Record attuali - 24h: {$record_esistente['record_24h']}, 12h: {$record_esistente['record_12h']}, 6h: {$record_esistente['record_6h']}, 1h: {$record_esistente['record_1h']}");
        logmsg("   Valori correnti - 24h: $prec_24h, 12h: $prec_12h, 6h: $prec_6h, 1h: $prec_1h");
        
        // Aggiorna solo i record superati
        $updates = [];
        $params = [':anno' => $anno, ':mese' => $mese];
        
        if ($prec_24h > $record_esistente['record_24h']) {
            $updates[] = "record_24h = :r24, data_record_24h = :d24";
            $params[':r24'] = $prec_24h;
            $params[':d24'] = $timestamp_registrazione;
            logmsg("Nuovo record 24h: $prec_24h mm (precedente: {$record_esistente['record_24h']})");
        }
        
        if ($prec_12h > $record_esistente['record_12h']) {
            $updates[] = "record_12h = :r12, data_record_12h = :d12";
            $params[':r12'] = $prec_12h;
            $params[':d12'] = $timestamp_registrazione;
            logmsg("Nuovo record 12h: $prec_12h mm (precedente: {$record_esistente['record_12h']})");
        }
        
        if ($prec_6h > $record_esistente['record_6h']) {
            $updates[] = "record_6h = :r6, data_record_6h = :d6";
            $params[':r6'] = $prec_6h;
            $params[':d6'] = $timestamp_registrazione;
            logmsg("Nuovo record 6h: $prec_6h mm (precedente: {$record_esistente['record_6h']})");
        }
        
        if ($prec_1h > $record_esistente['record_1h']) {
            $updates[] = "record_1h = :r1, data_record_1h = :d1";
            $params[':r1'] = $prec_1h;
            $params[':d1'] = $timestamp_registrazione;
            logmsg("Nuovo record 1h: $prec_1h mm (precedente: {$record_esistente['record_1h']})");
        }
        
        if (!empty($updates)) {
            $sql_update = "UPDATE $TABLE_RECORD SET " . implode(', ', $updates) . 
                         " WHERE anno = :anno AND mese = :mese";
            $stmt = $pdo->prepare($sql_update);
            $stmt->execute($params);
            logmsg("Record mensili aggiornati");
        } else {
            logmsg("Nessun record superato per $mese/$anno");
        }
    }
    
} catch (PDOException $e) {
    logmsg("ERRORE aggiornamento record: " . $e->getMessage());
    exit(1);
}

// ==========================================
// 7. RIEPILOGO FINALE
// ==========================================

logmsg("=== RIEPILOGO IMPORT ===");
logmsg("Timestamp JSON (generazione): $timestamp_mysql");
logmsg("Timestamp dati stazione (registrazione): $timestamp_registrazione");
logmsg("Anno/Mese/Giorno: $anno/$mese (da timestamp UTC convertito)");
logmsg("Data salvata DB giornaliero: $data_registrazione");
logmsg("Precipitazioni salvate:");
logmsg("  1h:  $prec_1h mm");
logmsg("  6h:  $prec_6h mm");
logmsg("  12h: $prec_12h mm");
logmsg("  24h: $prec_24h mm");
logmsg("=== FINE IMPORT CFR ===");

exit(0);
?>