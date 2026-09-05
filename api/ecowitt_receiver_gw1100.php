<?php
/**
 * ============================================================================
 * ECOWITT DATA RECEIVER - BACKUP (GW1100) - ecowitt_receiver_gw1100.php
 * ============================================================================
 *
 * RESPONSABILITA':
 * - Riceve dati POST dal gateway Ecowitt GW1100 (backup del GW3000A)
 * - Converte i dati nel formato compatibile con dati_meteo_simignano
 * - Inserisce SOLO nella tabella di backup dati_meteo_backup_gw1100
 * - NON scrive MAI nelle tabelle di produzione/test: il riversamento
 *   verso dati_meteo_simignano avviene tramite uno script di
 *   riconciliazione separato (cron, ogni N ore), non da questo file.
 *
 * TABELLA DI BACKUP (da creare una tantum sul DB, non ancora presente):
 *
 * CREATE TABLE dati_meteo_backup_gw1100 (
 *     data_ora DATETIME NOT NULL,
 *     temperatura_C DECIMAL(4,1) DEFAULT NULL,
 *     umidita_RH TINYINT DEFAULT NULL,
 *     pressione_hPa DECIMAL(6,1) DEFAULT NULL,
 *     dew_point_C DECIMAL(4,1) DEFAULT NULL,
 *     vento_kmh DECIMAL(5,1) DEFAULT NULL,
 *     direzione_vento_deg SMALLINT DEFAULT NULL,
 *     radianza_wm2 DECIMAL(7,2) DEFAULT NULL,
 *     radianza_int_whm2 DECIMAL(9,2) DEFAULT NULL,
 *     PRIMARY KEY (data_ora)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * Il PRIMARY KEY su data_ora e' fondamentale:
 * - rende gli INSERT idempotenti (INSERT IGNORE) in caso di retry di rete
 *   del gateway
 * - permette allo script di riconciliazione di fare
 *   "INSERT IGNORE INTO dati_meteo_simignano SELECT ... FROM
 *    dati_meteo_backup_gw1100 WHERE data_ora <= NOW() - INTERVAL 10 MINUTE"
 *   in una singola transazione, senza confronti riga per riga.
 *
 * PARAMETRI GATEWAY GW1100 ECOWITT:
 * serverIP/hostname: www.meteosimignano.it
 * path: /api/ecowitt_receiver_gw1100.php
 * port: 80
 * method: POST
 * (stesso protocollo del GW3000A: connessione solo http, vedi .htaccess
 *  della cartella api/)
 *
 * MAPPATURA PARAMETRI ECOWITT -> DATABASE: identica a ecowitt_receiver.php
 */

// === INCLUDE FILES ===
require_once __DIR__ . '/../datetime_helper.php';
require_once __DIR__ . '/../env_tables_helper.php';
require_once __DIR__ . '/../../envelop.php';

// === CONFIGURAZIONE ===
$debug = false; // Mostra output per debug
$log_file = __DIR__ . '/ecowitt_receiver_gw1100.log'; // Log separato dal receiver principale
$tabella_backup = 'dati_meteo_backup_gw1100';

/*
 * Scrive nel log mantenendo solo le ultime N righe
 */
function scriviLogGW1100($messaggio, $log_file, $max_righe = 1440) {
    $timestamp = date('Y-m-d H:i:s');
    $nuova_riga = "[$timestamp] $messaggio";

    $righe = [];
    if (file_exists($log_file)) {
        $righe = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    $righe[] = $nuova_riga;

    if (count($righe) > $max_righe) {
        $righe = array_slice($righe, -$max_righe);
    }

    file_put_contents($log_file, implode("\n", $righe) . "\n");
}

/**
 * Conversione Fahrenheit -> Celsius
 */
function fahrenheitToCelsius($f) {
    return ($f - 32) * 5 / 9;
}

/**
 * Conversione inHg -> hPa
 */
function inHgToHPa($inHg) {
    return $inHg * 33.8639;
}

/**
 * Conversione mph -> km/h
 */
function mphToKmh($mph) {
    return $mph * 1.60934;
}

/**
 * Calcola il dew point (punto di rugiada)
 * Formula di Magnus-Tetens
 */
function calcolaDewPoint($temp_c, $humidity) {
    if ($humidity <= 0 || $humidity > 100) {
        return null;
    }

    $a = 17.27;
    $b = 237.7;
    $alpha = (($a * $temp_c) / ($b + $temp_c)) + log($humidity / 100);
    $dew_point = ($b * $alpha) / ($a - $alpha);

    return round($dew_point, 1);
}

/**
 * Converte timestamp UTC in formato locale per il database
 * @throws Exception se il timestamp non e' valido
 */
function convertiTimestampUTC($dateutc) {
    // Il formato e': 2026-01-14+23:09:28 (spazio codificato come +)
    $dateutc = str_replace('+', ' ', $dateutc);

    try {
        $dt = new DateTime($dateutc, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Rome'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        throw new Exception("Timestamp non valido: " . $dateutc);
    }
}

/**
 * Verifica se un valore e' valido (non vuoto, NULL, NA, ecc.)
 */
function isValidValue($value) {
    if (!isset($value)) return false;
    if ($value === '') return false;
    if ($value === null) return false;
    if ($value === 'NULL') return false;
    if ($value === 'null') return false;
    if (strtoupper($value) === 'NA') return false;
    if (strtoupper($value) === 'N/A') return false;
    if ($value === '--') return false;
    if ($value === '---') return false;

    // Caso speciale: stringa numerica "-999" o simili (sensori offline)
    if (is_numeric($value) && (float)$value <= -999) return false;

    return true;
}

/**
 * Parsa i dati POST di Ecowitt e li converte nel formato del database
 */
function parsaEcowittData($post_data) {
    $dati = [];

    // === TIMESTAMP (OBBLIGATORIO) ===
    if (!isset($post_data['dateutc']) || $post_data['dateutc'] === '' || $post_data['dateutc'] === null) {
        return null; // Dato completamente invalido
    }

    try {
        $dati['data_ora'] = convertiTimestampUTC($post_data['dateutc']);
    } catch (Exception $e) {
        return null;
    }

    // === TEMPERATURA ===
    if (isValidValue($post_data['tempf'] ?? null)) {
        $temp_f = (float)$post_data['tempf'];
        $temp_c = fahrenheitToCelsius($temp_f);
        if ($temp_c >= -35 && $temp_c <= 60) {
            $dati['temperatura_C'] = round($temp_c, 1);
        }
    }

    // === UMIDITA' ===
    if (isValidValue($post_data['humidity'] ?? null)) {
        $humidity = (int)$post_data['humidity'];
        if ($humidity >= 0 && $humidity <= 100) {
            $dati['umidita_RH'] = $humidity;
        }
    }

    // === PRESSIONE ===
    if (isValidValue($post_data['baromrelin'] ?? null)) {
        $press_hpa = inHgToHPa((float)$post_data['baromrelin']);
        if ($press_hpa >= 900 && $press_hpa <= 1100) {
            $dati['pressione_hPa'] = round($press_hpa, 1);
        }
    }

    // === VENTO VELOCITA' ===
    if (isValidValue($post_data['windspeedmph'] ?? null)) {
        $wind_kmh = mphToKmh((float)$post_data['windspeedmph']);
        if ($wind_kmh >= 0 && $wind_kmh <= 200) {
            $dati['vento_kmh'] = round($wind_kmh, 1);
        }
    }

    // === VENTO DIREZIONE ===
    if (isValidValue($post_data['winddir'] ?? null)) {
        $wind_dir = (int)$post_data['winddir'];
        if ($wind_dir >= 0 && $wind_dir <= 360) {
            $dati['direzione_vento_deg'] = $wind_dir;
        }
    }

    // === RADIAZIONE SOLARE ===
    if (isValidValue($post_data['solarradiation'] ?? null)) {
        $rad = (float)$post_data['solarradiation'];
        if ($rad >= 0 && $rad <= 2000) {
            $dati['radianza_wm2'] = round($rad, 2);
        }
    }

    // === DEW POINT (calcolato) ===
    if (isset($dati['temperatura_C']) && isset($dati['umidita_RH'])) {
        $dew = calcolaDewPoint($dati['temperatura_C'], $dati['umidita_RH']);
        if ($dew !== null && $dew >= -50 && $dew <= 60) {
            $dati['dew_point_C'] = $dew;
        }
    }

    // === DATI AGGIUNTIVI (solo per il log) ===
    $dati['_metadata'] = [
        'stationtype' => $post_data['stationtype'] ?? 'unknown',
        'model' => $post_data['model'] ?? 'unknown',
        'passkey' => isset($post_data['PASSKEY']) ? substr($post_data['PASSKEY'], 0, 8) . '...' : 'none',
    ];

    return $dati;
}

/**
 * Inserisce i dati SOLO nella tabella di backup.
 *
 * Usa INSERT IGNORE: se il gateway GW1100 reinvia lo stesso timestamp
 * (retry di rete lato gateway), la riga viene scartata silenziosamente
 * invece di generare un errore, perche' data_ora e' PRIMARY KEY della
 * tabella di backup.
 *
 * IMPORTANTE: questa funzione fa solo INSERT, mai UPDATE, per non
 * entrare in contesa di lock con lo script di riconciliazione che
 * periodicamente legge/cancella dalla stessa tabella.
 */
function inserisciDatiBackup($pdo, $dati, $tabella_backup) {
    $tutti_campi = [
        'data_ora',
        'temperatura_C',
        'umidita_RH',
        'pressione_hPa',
        'dew_point_C',
        'vento_kmh',
        'direzione_vento_deg',
        'radianza_wm2',
        'radianza_int_whm2'
    ];

    $colonne = [];
    $valori = [];
    $params = [];

    foreach ($tutti_campi as $campo) {
        $colonne[] = $campo;

        if (isset($dati[$campo])) {
            $valori[] = ':' . $campo;
            $params[':' . $campo] = $dati[$campo];
        } else {
            $valori[] = 'NULL';
        }
    }

    $sql = "INSERT IGNORE INTO $tabella_backup (" . implode(', ', $colonne) . ")
            VALUES (" . implode(', ', $valori) . ")";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

// === MAIN EXECUTION ===
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed. Only POST is accepted.']);
        exit;
    }

    $post_data = $_POST;

    if (empty($post_data)) {
        $raw_input = file_get_contents('php://input');
        parse_str($raw_input, $post_data);
    }

    if (empty($post_data)) {
        http_response_code(400);
        echo json_encode(['error' => 'No POST data received']);
        scriviLogGW1100('ERRORE: Nessun dato POST ricevuto', $log_file);
        exit;
    }

    if ($debug) {
        scriviLogGW1100('=== DATI ECOWITT GW1100 RICEVUTI ===', $log_file);
        scriviLogGW1100(json_encode($post_data, JSON_PRETTY_PRINT), $log_file);
    }

    $dati = parsaEcowittData($post_data);

    if ($dati === null) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid or missing timestamp',
            'message' => 'Il campo dateutc e\' obbligatorio e deve essere valido',
            'received_dateutc' => $post_data['dateutc'] ?? 'NOT_PRESENT'
        ]);
        scriviLogGW1100('ERRORE: Timestamp mancante o non valido - POST rifiutato completamente', $log_file);
        exit;
    }

    if ($debug) {
        scriviLogGW1100('=== DATI PARSATI ===', $log_file);
        scriviLogGW1100(json_encode($dati, JSON_PRETTY_PRINT), $log_file);
    }

    // Connessione al database (gia' creata da envelop.php)
    // $pdo e' gia' disponibile - scrittura sempre su $pdo, mai su $pdo_lettura
    $success = inserisciDatiBackup($pdo, $dati, $tabella_backup);

    if ($success) {
        http_response_code(200);

        $response = [
            'status' => 'success',
            'message' => 'Data saved to backup table',
            'table' => $tabella_backup,
            'timestamp' => $dati['data_ora']
        ];

        echo json_encode($response);
        scriviLogGW1100('OK: backup salvato per ' . $dati['data_ora'], $log_file);

        if ($debug) {
            scriviLogGW1100('SUCCESS: Dati salvati correttamente nel backup', $log_file);
        }
    } else {
        throw new Exception('Inserimento nel backup fallito');
    }

} catch (Exception $e) {
    http_response_code(500);
    $error_response = [
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ];
    echo json_encode($error_response);

    scriviLogGW1100('ERRORE: ' . $e->getMessage(), $log_file);
    scriviLogGW1100('Stack trace: ' . $e->getTraceAsString(), $log_file);
}