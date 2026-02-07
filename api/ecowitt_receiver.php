<?php
/**
 * ============================================================================
 * ECOWITT DATA RECEIVER - ricevi_ecowitt.php
 * ============================================================================
 * 
 * RESPONSABILITA':
 * - Riceve dati POST dal gateway Ecowitt GW3000A
 * - Converte i dati nel formato compatibile con dati_meteo_simignano
 * - Inserisce i dati nel database
 * - Calcola valori derivati (dew point, wind chill, heat index)
 * 
 * MAPPATURA PARAMETRI ECOWITT -> DATABASE:
 * - tempf -> temperatura_C (conversione F->C)
 * - humidity -> umidita_RH
 * - baromrelin -> pressione_hPa (conversione inHg->hPa)
 * - windspeedmph -> vento_kmh (conversione mph->kmh)
 * - winddir -> direzione_vento_deg
 * - solarradiation -> radianza_wm2
 * - dateutc -> data_ora (conversione timezone) - OBBLIGATORIO!
 */

// === INCLUDE FILES ===
require_once __DIR__ . '/../datetime_helper.php';
require_once __DIR__ . '/../env_tables_helper.php';
require_once __DIR__ . '/../../envelop.php';

// === CONFIGURAZIONE ===
$debug = false; // Mostra output per debug
$log_file = __DIR__ . '/ecowitt_receiver.log'; // Log nella stessa cartella /api/

/*
 * Scrive nel log mantenendo solo le ultime N righe
 */
function scriviLog($messaggio, $log_file, $max_righe = 1440) {
    $timestamp = date('Y-m-d H:i:s');
    $nuova_riga = "[$timestamp] $messaggio";
    
    // Leggi righe esistenti (se il file esiste)
    $righe = [];
    if (file_exists($log_file)) {
        $righe = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }
    
    // Aggiungi nuova riga
    $righe[] = $nuova_riga;
    
    // Tieni solo le ultime N righe
    if (count($righe) > $max_righe) {
        $righe = array_slice($righe, -$max_righe);
    }
    
    // Riscrivi il file
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
        // Crea DateTime in UTC
        $dt = new DateTime($dateutc, new DateTimeZone('UTC'));
        
        // Converti in timezone locale (Europe/Rome)
        $dt->setTimezone(new DateTimeZone('Europe/Rome'));
        
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        // Timestamp non valido
        throw new Exception("Timestamp non valido: " . $dateutc);
    }
}

/**
 * Verifica se un valore e' valido (non vuoto, NULL, NA, ecc.)
 */
function isValidValue($value) {
    // Casi non validi
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
        // TIMESTAMP MANCANTE -> Dato completamente invalido
        return null; // Segnala che il dato non e' utilizzabile
    }
    
    try {
        $dati['data_ora'] = convertiTimestampUTC($post_data['dateutc']);
    } catch (Exception $e) {
        // Timestamp presente ma non valido
        return null;
    }
    
    // === TEMPERATURA ===
    if (isValidValue($post_data['tempf'] ?? null)) {
        $temp_f = (float)$post_data['tempf'];
        // Verifica range ragionevole (-35°C a +60°C)
        $temp_c = fahrenheitToCelsius($temp_f);
        if ($temp_c >= -35 && $temp_c <= 60) {
            $dati['temperatura_C'] = round($temp_c, 1);
        }
    }
    
    // === UMIDITA' ===
    if (isValidValue($post_data['humidity'] ?? null)) {
        $humidity = (int)$post_data['humidity'];
        // Verifica range valido (0-100%)
        if ($humidity >= 0 && $humidity <= 100) {
            $dati['umidita_RH'] = $humidity;
        }
    }
    
    // === PRESSIONE ===
    // Ecowitt fornisce baromrelin (pressione relativa al livello del mare)
    if (isValidValue($post_data['baromrelin'] ?? null)) {
        $press_hpa = inHgToHPa((float)$post_data['baromrelin']);
        // Verifica range ragionevole (900-1100 hPa)
        if ($press_hpa >= 900 && $press_hpa <= 1100) {
            $dati['pressione_hPa'] = round($press_hpa, 1);
        }
    }
    
    // === VENTO VELOCITA' ===
    if (isValidValue($post_data['windspeedmph'] ?? null)) {
        $wind_kmh = mphToKmh((float)$post_data['windspeedmph']);
        // Verifica range ragionevole (0-200 km/h)
        if ($wind_kmh >= 0 && $wind_kmh <= 200) {
            $dati['vento_kmh'] = round($wind_kmh, 1);
        }
    }
    
    // === VENTO DIREZIONE ===
    if (isValidValue($post_data['winddir'] ?? null)) {
        $wind_dir = (int)$post_data['winddir'];
        // Verifica range valido (0-360 gradi)
        if ($wind_dir >= 0 && $wind_dir <= 360) {
            $dati['direzione_vento_deg'] = $wind_dir;
        }
    }
    
    // === RADIAZIONE SOLARE ===
    if (isValidValue($post_data['solarradiation'] ?? null)) {
        $rad = (float)$post_data['solarradiation'];
        // Verifica range ragionevole (0-2000 W/m2)
        if ($rad >= 0 && $rad <= 2000) {
            $dati['radianza_wm2'] = round($rad, 2);
        }
    }
    
    // === DEW POINT (calcolato) ===
    if (isset($dati['temperatura_C']) && isset($dati['umidita_RH'])) {
        $dew = calcolaDewPoint($dati['temperatura_C'], $dati['umidita_RH']);
        // Verifica che il calcolo sia riuscito
        if ($dew !== null && $dew >= -50 && $dew <= 60) {
            $dati['dew_point_C'] = $dew;
        }
    }
    
    // === DATI AGGIUNTIVI (opzionali, per il log) ===
    $dati['_metadata'] = [
        'stationtype' => $post_data['stationtype'] ?? 'unknown',
        'model' => $post_data['model'] ?? 'unknown',
        'passkey' => isset($post_data['PASSKEY']) ? substr($post_data['PASSKEY'], 0, 8) . '...' : 'none',
        'windgustmph' => isset($post_data['windgustmph']) ? round(mphToKmh((float)$post_data['windgustmph']), 1) . ' km/h' : 'N/A',
        'uv' => $post_data['uv'] ?? 'N/A',
        'co2' => $post_data['co2'] ?? 'N/A'
    ];
    
    // === TRACCIA VALORI SCARTATI ===
    $valori_scartati = [];
    
    // Controlla quali campi NON sono stati inseriti
    $campi_richiesti = ['temperatura_C', 'umidita_RH', 'pressione_hPa', 'vento_kmh', 'direzione_vento_deg', 'radianza_wm2'];
    $campi_originali = ['tempf', 'humidity', 'baromrelin', 'windspeedmph', 'winddir', 'solarradiation'];
    
    foreach ($campi_richiesti as $idx => $campo) {
        if (!isset($dati[$campo])) {
            $campo_orig = $campi_originali[$idx];
            $valore_orig = $post_data[$campo_orig] ?? 'NOT_SET';
            $valori_scartati[$campo] = [
                'campo_originale' => $campo_orig,
                'valore' => $valore_orig,
                'motivo' => !isValidValue($valore_orig) ? 'valore_non_valido' : 'fuori_range'
            ];
        }
    }
    
    if (!empty($valori_scartati)) {
        $dati['_metadata']['valori_scartati'] = $valori_scartati;
    }
    
    return $dati;
}

/**
 * Inserisce i dati nel database
 * Inserisce esplicitamente NULL per i campi non validi
 */
function inserisciDatiDB($pdo, $dati) {
    // === LISTA TABELLE DESTINAZIONE ===
    $tabelle = [
        'dati_meteo_simignano',      // Produzione
        'dati_meteo_simignano_test'  // Test
    ];
    
    // Lista di TUTTI i campi possibili (esclusi metadata)
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
    
    // Costruisci l'INSERT con TUTTI i campi
    $colonne = [];
    $valori = [];
    $params = [];
    
    foreach ($tutti_campi as $campo) {
        if ($campo === '_metadata') continue; // Salta i metadata
        
        $colonne[] = $campo;
        
        if (isset($dati[$campo])) {
            // Campo presente: usa il valore
            $valori[] = ':' . $campo;
            $params[':' . $campo] = $dati[$campo];
        } else {
            // Campo mancante: inserisci NULL esplicito
            $valori[] = 'NULL';
        }
    }
    
   // === INSERISCI IN TUTTE LE TABELLE ===
    $risultati = [];
    $successo_totale = true;
    
    foreach ($tabelle as $table) {
        $sql = "INSERT INTO $table (" . implode(', ', $colonne) . ") 
                VALUES (" . implode(', ', $valori) . ")";
        
        try {
            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute($params);
            $risultati[$table] = $ok ? 'OK' : 'FAILED';
            if (!$ok) $successo_totale = false;
        } catch (Exception $e) {
            $risultati[$table] = 'ERROR: ' . $e->getMessage();
            $successo_totale = false;
        }
    }
    
    // Salva i risultati nei metadata per il response
    $GLOBALS['_insert_results'] = $risultati;
    
    return $successo_totale;

    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

// === MAIN EXECUTION ===
try {
    // Verifica che sia una richiesta POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed. Only POST is accepted.']);
        exit;
    }
    
    // Leggi i dati POST
    $post_data = $_POST;
    
    if (empty($post_data)) {
        // Se $_POST e' vuoto, prova a leggere il raw input
        $raw_input = file_get_contents('php://input');
        parse_str($raw_input, $post_data);
    }
    
    if (empty($post_data)) {
        http_response_code(400);
        echo json_encode(['error' => 'No POST data received']);
        scriviLog('ERRORE: Nessun dato POST ricevuto', $log_file);
        exit;
    }
    
    // Log dati ricevuti (per debug)
    if ($debug) {
        scriviLog('=== DATI ECOWITT RICEVUTI ===', $log_file);
        scriviLog(json_encode($post_data, JSON_PRETTY_PRINT), $log_file);
    }
    
    // Parsa i dati Ecowitt
    $dati = parsaEcowittData($post_data);
    
    // Verifica che il parsing sia riuscito (timestamp valido)
    if ($dati === null) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid or missing timestamp',
            'message' => 'Il campo dateutc e\' obbligatorio e deve essere valido',
            'received_dateutc' => $post_data['dateutc'] ?? 'NOT_PRESENT'
        ]);
        scriviLog('ERRORE: Timestamp mancante o non valido - POST rifiutato completamente', $log_file);
        exit;
    }
    
    if ($debug) {
        scriviLog('=== DATI PARSATI ===', $log_file);
        scriviLog(json_encode($dati, JSON_PRETTY_PRINT), $log_file);
        
        // Warning se ci sono valori scartati
        if (isset($dati['_metadata']['valori_scartati']) && !empty($dati['_metadata']['valori_scartati'])) {
            scriviLog('WARNING: Alcuni valori sono stati scartati (verranno inseriti come NULL):', $log_file);
            scriviLog(json_encode($dati['_metadata']['valori_scartati'], JSON_PRETTY_PRINT), $log_file);
        }
    }
    
    // Verifica PASSKEY (opzionale, per sicurezza)
    // OPZIONE 1: Disabilita il controllo se la cartella /api/ e' gia' protetta
    $verifica_passkey = false; // Cambia in true per abilitare
    
    if ($verifica_passkey) {
        $passkey_attesa = '5C5FFC9C7FF121BB58C0F535F96F82B5'; // La tua PASSKEY
        if (isset($post_data['PASSKEY']) && $post_data['PASSKEY'] !== $passkey_attesa) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid PASSKEY']);
            scriviLog('ERRORE: PASSKEY non valida', $log_file);
            exit;
        }
    }
    
    // OPZIONE 2: Whitelist IP del gateway Ecowitt (opzionale)
    /*
    $ip_consentiti = ['192.168.1.100', '192.168.1.50']; // IP del tuo gateway
    $ip_client = $_SERVER['REMOTE_ADDR'];
    if (!in_array($ip_client, $ip_consentiti)) {
        http_response_code(403);
        echo json_encode(['error' => 'IP not allowed: ' . $ip_client]);
        scriviLog('ERRORE: IP non autorizzato: ' . $ip_client, $log_file);
        exit;
    }
    */
    
    // Connessione al database (gia' creata da envelop.php)
    // $pdo e' gia' disponibile
    
    // Inserisci i dati
    $success = inserisciDatiDB($pdo, $dati);
    
    if ($success) {
        http_response_code(200);
        
        // Conta campi validi vs NULL
        $campi_totali = ['temperatura_C', 'umidita_RH', 'pressione_hPa', 'vento_kmh', 'direzione_vento_deg', 'radianza_wm2', 'dew_point_C'];
        $campi_validi = 0;
        $campi_null = 0;
        
        foreach ($campi_totali as $campo) {
            if (isset($dati[$campo])) {
                $campi_validi++;
            } else {
                $campi_null++;
            }
        }
        
        $response = [
            'status' => 'success',
            'message' => 'Data inserted successfully',
            'timestamp' => $dati['data_ora'],
            'records_inserted' => 1,
            'fields_valid' => $campi_validi,
            'fields_null' => $campi_null,
            // DEBUG: mostra risultato per ogni tabella
            'tables' => $GLOBALS['_insert_results'] ?? []
        ];
        
        if ($campi_null > 0) {
            $response['warning'] = "$campi_null fields inserted as NULL";
            $response['null_fields'] = array_keys($dati['_metadata']['valori_scartati'] ?? []);
        }
        
        echo json_encode($response);
        scriviLog('RESPONSE: ' . json_encode($response), $log_file);
        
        if ($debug) {
            scriviLog('SUCCESS: Dati inseriti correttamente', $log_file);
            scriviLog(json_encode($response), $log_file);
        }
    } else {
        throw new Exception('Inserimento dati fallito');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    $error_response = [
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ];
    echo json_encode($error_response);
    
    scriviLog('ERRORE: ' . $e->getMessage(), $log_file);
    scriviLog('Stack trace: ' . $e->getTraceAsString(), $log_file);
}
?>