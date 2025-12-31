

<?php
/**
 * ============================================================================
 * TABELLA HOME - DATA LAYER
 * ============================================================================
 * 
 * RESPONSABILITà€:
 * - Recupera TUTTI i dati meteo dal database
 * - Calcola trend e delta
 * - Integra dati astronomici (luna/sole)
 * - Restituisce array strutturato per il rendering
 * 
 * OUTPUT:
 * Array associativo con:
 * - metadata: timestamp, status, alerts
 * - rows: array di righe per la tabella
 * - raw_data: dati grezzi per API/export
 */


require_once __DIR__ . '/datetime_helper.php';
require_once __DIR__ . '/env_tables_helper.php';
require_once __DIR__ . '/astro_helper.php';

/**
 * Recupera tutti i dati meteo necessari per la tabella home
 * 
 * @param PDO $pdo Connessione database
 * @return array Dati strutturati
 */
function getMeteoData(PDO $pdo): array {
    $table = table_name('dati_meteo_simignano');
    $now = get_now();
    $now_24h_ago = date('Y-m-d H:i:s', strtotime($now . ' -24 hours'));
    $now_1h_ago = date('Y-m-d H:i:s', strtotime($now . ' -1 hour'));
    $today = get_now('Y-m-d');
    $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
    
    // ========================================================================
    // QUERY PRINCIPALE - Recupera tutti i dati necessari
    // ========================================================================
    $sql = "
SELECT 
  -- ULTIMI VALORI (pià¹ recenti)
  (SELECT data_ora FROM $table ORDER BY data_ora DESC LIMIT 1) AS last_update,

  (SELECT temperatura_C FROM $table WHERE temperatura_C IS NOT NULL ORDER BY data_ora DESC LIMIT 1) AS temp_act,
  (SELECT DATE_FORMAT(data_ora, '%H:%i') FROM $table WHERE temperatura_C IS NOT NULL ORDER BY data_ora DESC LIMIT 1) AS temp_act_time,

  (SELECT umidita_RH FROM $table WHERE umidita_RH IS NOT NULL ORDER BY data_ora DESC LIMIT 1) AS hr_act,
  (SELECT pressione_hPa FROM $table WHERE pressione_hPa IS NOT NULL ORDER BY data_ora DESC LIMIT 1) AS press_act,
  (SELECT dew_point_C FROM $table WHERE dew_point_C IS NOT NULL ORDER BY data_ora DESC LIMIT 1) AS dew_act,

  (SELECT vento_kmh FROM $table WHERE vento_kmh IS NOT NULL ORDER BY data_ora DESC LIMIT 1) AS wind_act,
  (SELECT DATE_FORMAT(data_ora, '%H:%i') FROM $table WHERE vento_kmh IS NOT NULL ORDER BY data_ora DESC LIMIT 1) AS wind_act_time,
  (SELECT direzione_vento_deg FROM $table WHERE direzione_vento_deg IS NOT NULL ORDER BY data_ora DESC LIMIT 1) AS wind_dir_act,

  (SELECT radianza_wm2 FROM $table WHERE radianza_wm2 IS NOT NULL ORDER BY data_ora DESC LIMIT 1) AS rad_act,
  (SELECT radianza_int_whm2 FROM $table WHERE radianza_int_whm2 IS NOT NULL ORDER BY data_ora DESC LIMIT 1) AS rad_int_act,

  -- MEDIA DEL VENTO ultimi 15 min (mobile)
  (SELECT AVG(vento_kmh)
 FROM $table WHERE vento_kmh IS NOT NULL AND data_ora >= (SELECT MAX(data_ora) FROM $table
     WHERE vento_kmh IS NOT NULL
   ) - INTERVAL 15 MINUTE
) AS wind_avg_15m,

  -- VALORI 24H FA (per delta)
  (SELECT temperatura_C FROM $table WHERE temperatura_C IS NOT NULL AND data_ora <= :now_24h_ago ORDER BY data_ora DESC LIMIT 1) AS temp_24h_ago,
  (SELECT pressione_hPa  FROM $table WHERE pressione_hPa  IS NOT NULL AND data_ora <= :now_24h_ago ORDER BY data_ora DESC LIMIT 1) AS press_24h_ago,

  -- VALORI 1H FA (per delta orario)
  (SELECT temperatura_C FROM $table WHERE temperatura_C IS NOT NULL AND data_ora <= :now_1h_ago ORDER BY data_ora DESC LIMIT 1) AS temp_1h_ago,
  (SELECT pressione_hPa  FROM $table WHERE pressione_hPa  IS NOT NULL AND data_ora <= :now_1h_ago ORDER BY data_ora DESC LIMIT 1) AS press_1h_ago,

  -- MAX/MIN OGGI
  (SELECT MAX(temperatura_C) FROM $table WHERE temperatura_C IS NOT NULL AND DATE(data_ora) = :today) AS temp_max_today,
  (SELECT MIN(temperatura_C) FROM $table WHERE temperatura_C IS NOT NULL AND DATE(data_ora) = :today) AS temp_min_today,
  (SELECT MAX(umidita_RH)    FROM $table WHERE umidita_RH    IS NOT NULL AND DATE(data_ora) = :today) AS hr_max_today,
  (SELECT MIN(umidita_RH)    FROM $table WHERE umidita_RH    IS NOT NULL AND DATE(data_ora) = :today) AS hr_min_today,
  (SELECT MAX(dew_point_C)   FROM $table WHERE dew_point_C   IS NOT NULL AND DATE(data_ora) = :today) AS dew_max_today,
  (SELECT MIN(dew_point_C)   FROM $table WHERE dew_point_C   IS NOT NULL AND DATE(data_ora) = :today) AS dew_min_today,
  (SELECT MAX(pressione_hPa) FROM $table WHERE pressione_hPa IS NOT NULL AND DATE(data_ora) = :today) AS press_max_today,
  (SELECT MIN(pressione_hPa) FROM $table WHERE pressione_hPa IS NOT NULL AND DATE(data_ora) = :today) AS press_min_today,
  (SELECT MAX(vento_kmh)      FROM $table WHERE vento_kmh      IS NOT NULL AND DATE(data_ora) = :today) AS wind_max_today,

  -- MAX/MIN IERI (per confronto)
  (SELECT MAX(temperatura_C) FROM $table WHERE temperatura_C IS NOT NULL AND DATE(data_ora) = :yesterday) AS temp_max_yesterday,
  (SELECT MIN(temperatura_C) FROM $table WHERE temperatura_C IS NOT NULL AND DATE(data_ora) = :yesterday) AS temp_min_yesterday,

  -- ORA MAX/MIN OGGI (HH:MM)
  (SELECT DATE_FORMAT(data_ora, '%H:%i') FROM $table WHERE temperatura_C IS NOT NULL AND DATE(data_ora) = :today ORDER BY temperatura_C DESC, data_ora ASC  LIMIT 1) AS temp_max_time,
  (SELECT DATE_FORMAT(data_ora, '%H:%i') FROM $table WHERE temperatura_C IS NOT NULL AND DATE(data_ora) = :today ORDER BY temperatura_C ASC,  data_ora ASC  LIMIT 1) AS temp_min_time,

  (SELECT DATE_FORMAT(data_ora, '%H:%i') FROM $table WHERE umidita_RH    IS NOT NULL AND DATE(data_ora) = :today ORDER BY umidita_RH DESC,   data_ora ASC  LIMIT 1) AS hr_max_time,
  (SELECT DATE_FORMAT(data_ora, '%H:%i') FROM $table WHERE umidita_RH    IS NOT NULL AND DATE(data_ora) = :today ORDER BY umidita_RH ASC,    data_ora ASC  LIMIT 1) AS hr_min_time,

  (SELECT DATE_FORMAT(data_ora, '%H:%i') FROM $table WHERE dew_point_C   IS NOT NULL AND DATE(data_ora) = :today ORDER BY dew_point_C DESC,  data_ora ASC  LIMIT 1) AS dew_point_max_time,
  (SELECT DATE_FORMAT(data_ora, '%H:%i') FROM $table WHERE dew_point_C   IS NOT NULL AND DATE(data_ora) = :today ORDER BY dew_point_C ASC,   data_ora ASC  LIMIT 1) AS dew_point_min_time,

  (SELECT DATE_FORMAT(data_ora, '%H:%i') FROM $table WHERE pressione_hPa IS NOT NULL AND DATE(data_ora) = :today ORDER BY pressione_hPa DESC, data_ora ASC  LIMIT 1) AS press_max_time,
  (SELECT DATE_FORMAT(data_ora, '%H:%i') FROM $table WHERE pressione_hPa IS NOT NULL AND DATE(data_ora) = :today ORDER BY pressione_hPa ASC,  data_ora ASC  LIMIT 1) AS press_min_time,

  (SELECT DATE_FORMAT(data_ora, '%H:%i') FROM $table WHERE vento_kmh     IS NOT NULL AND DATE(data_ora) = :today ORDER BY vento_kmh DESC, data_ora ASC  LIMIT 1) AS wind_max_time,

  -- MAX della MEDIA 15' (bucket fissi) nella giornata corrente
  (
  SELECT MAX(wind_avg_15m)
  FROM (
    SELECT AVG(vento_kmh) AS wind_avg_15m
    FROM $table
    WHERE vento_kmh IS NOT NULL
      AND data_ora >= (
        SELECT MAX(data_ora)
        FROM $table
        WHERE vento_kmh IS NOT NULL
      ) - INTERVAL 24 HOUR
    GROUP BY HOUR(data_ora), FLOOR(MINUTE(data_ora) / 15)
    HAVING COUNT(vento_kmh) > 0
  ) t1
) AS wind_avg_max_24h,


  -- MAX della MEDIA 15' TEMPO (bucket fissi) nella giornata corrente
  (
 SELECT DATE_FORMAT(
  DATE_ADD(
    data_ora,
    INTERVAL (15 - MOD(MINUTE(data_ora), 15)) MINUTE
  ),
  '%d/%m %H:%i'
)
FROM (
  SELECT
    MAX(data_ora) AS data_ora,
    AVG(vento_kmh) AS wind_avg_15m
  FROM $table
  WHERE vento_kmh IS NOT NULL
    AND data_ora >= (
      SELECT MAX(data_ora)
      FROM $table
      WHERE vento_kmh IS NOT NULL
    ) - INTERVAL 24 HOUR
  GROUP BY
    DATE(data_ora),
    HOUR(data_ora),
    FLOOR(MINUTE(data_ora) / 15)
  ORDER BY wind_avg_15m DESC
  LIMIT 1
) t2
 ) AS wind_avg_max_time





";

    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':now_24h_ago' => $now_24h_ago,
            ':now_1h_ago' => $now_1h_ago,
            ':today' => $today,
            ':yesterday' => $yesterday
        ]);
        $raw = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Errore getMeteoData: " . $e->getMessage());
        return createErrorResponse("Errore database: " . $e->getMessage());
    }
    
    if (!$raw || !$raw['last_update']) {
        return createErrorResponse("Nessun dato disponibile");
    }
    
    // ========================================================================
    // CALCOLI DERIVATI
    // ========================================================================
    
    // Delta temperatura
    $temp_delta_24h = isset($raw['temp_act'], $raw['temp_24h_ago']) 
        ? round($raw['temp_act'] - $raw['temp_24h_ago'], 1) 
        : null;
    
    $temp_delta_1h = isset($raw['temp_act'], $raw['temp_1h_ago'])
        ? round($raw['temp_act'] - $raw['temp_1h_ago'], 1)
        : null;
    
    // Delta pressione
    $press_delta_24h = isset($raw['press_act'], $raw['press_24h_ago'])
        ? round($raw['press_act'] - $raw['press_24h_ago'], 1)
        : null;
    
    $press_delta_1h = isset($raw['press_act'], $raw['press_1h_ago'])
        ? round($raw['press_act'] - $raw['press_1h_ago'], 1)
        : null;
    
    // Delta max/min vs ieri
    $temp_max_delta = isset($raw['temp_max_today'], $raw['temp_max_yesterday'])
        ? round($raw['temp_max_today'] - $raw['temp_max_yesterday'], 1)
        : null;
    
    $temp_min_delta = isset($raw['temp_min_today'], $raw['temp_min_yesterday'])
        ? round($raw['temp_min_today'] - $raw['temp_min_yesterday'], 1)
        : null;

    $windChillValue = calcolaTemperaturaPercepita($raw['temp_act'], $raw['wind_act']);
    
    // ========================================================================
    // DATI ASTRONOMICI (cache giornaliera)
    // ========================================================================
    $astro = getAstroDataCached();
    
    // ========================================================================
    // RADIANZA SOLARE (integrazione con sistema esistente)
    // ========================================================================
    require_once __DIR__ . '/../envelop_lettura.php';
    $radianza = getSolareteoricoMezzaGiornata($pdo); // Funzione esistente
    
    // ========================================================================
    // ALERTS / ANOMALIE
    // ========================================================================
    $alerts = [];
    
    if ($temp_delta_24h !== null && $temp_delta_24h < -5) {
        $alerts[] = [
            'type' => 'warning',
            'severity' => 'high',
            'metric' => 'temperatura',
            'message' => "Calo drastico temperatura: {$temp_delta_24h}°C in 24h"
        ];
    }
    
    if ($press_delta_1h !== null && abs($press_delta_1h) > 2) {
        $alerts[] = [
            'type' => 'info',
            'severity' => 'medium',
            'metric' => 'pressione',
            'message' => "Rapida variazione pressione: " . ($press_delta_1h > 0 ? '+' : '') . "{$press_delta_1h} hPa in 1h"
        ];
    }
    
    // ========================================================================
    // COSTRUZIONE ARRAY RIGHE (con metadati per interattività )
    // ========================================================================
    $rows = [
        // ---- RIGA SEPARATORE ----
        [
            'type' => 'header',
            'label' => 'Ultima connessione',
            'value' => formatDateTime($raw['last_update']),
            'note' => '',
            'separator' => true
        ],
        
        // ---- SEZIONE ASTRONOMIA ----
        [
            'type' => 'data',
            'label' => 'Alba / Tramonto',
            'value' => $astro['sunrise'] . ' / ' . $astro['sunset'],
            'note' => renderSunIcons(),
            'separator' => false,
            /*'interactive' => [
                'tooltip' => 'Clicca per vedere almanacco completo',
                'clickable' => true,
                'action' => [
                    'type' => 'modal',
                    'endpoint' => 'almanacco.php',
                    'title' => 'Almanacco Astronomico'
                ]
            ]*/
        ],
        
        [
            'type' => 'data',
            'label' => 'Luna: Fase e luce disco',
            'value' => $astro['lunar_phase'],
            'note' => '',
            'separator' => true
        ],
        
        // ---- SEZIONE TEMPERATURA ----
        
        [
            'type' => 'data',
            'label' => "\u{0394}°C | \u{0394}hPa - ultima ora",
            'value' => formatDelta($temp_delta_1h) . ' | ' . formatDelta($temp_delta_24h),
            'note' => '',
            'separator' => true
        ],
        
        [
            'type' => 'data',
            'label' => 'Temperatura attuale',
            'value' => formatValue($raw['temp_act'], '°C'). ' ' . formatTime($raw['temp_act_time']),
            'note' => createDeltaIndicator($temp_delta_24h ?? 0) . " \u{0394}24h(attuale - 24h) = {$temp_delta_24h}°C",
            'separator' => false,
            'interactive' => [
                'tooltip' => 'Clicca per grafico 24h',
                'clickable' => true,
                'action' => [
                    'type' => 'modal',
                    'endpoint' => 'api_grafico.php',
                    'params' => ['metric' => 'temperatura', 'range' => '24h'],
                    'title' => 'Andamento Temperatura 24h'
                ]
            ]
        ],
        
        [
            'type' => 'data',
            'label' => 'Temperatura max e ora',
            'value' => formatValue($raw['temp_max_today'], '°C', 1) . ' ' . formatTime($raw['temp_max_time']),
            'note' => createDeltaIndicator($temp_max_delta ?? 0) . " ieri: " . formatValue($raw['temp_max_yesterday'], '°C', 1),
            'separator' => false,
            'interactive' => [
                'badge' => [
                    'text' => $temp_max_delta > 0 ? "+{$temp_max_delta}°C" : "{$temp_max_delta}°C",
                    'color' => $temp_max_delta > 0 ? 'red' : 'blue'
                ]
            ]
        ],
        
        [
            'type' => 'data',
            'label' => 'Temperatura min e ora',
            'value' => formatValue($raw['temp_min_today'], '°C', 1) . ' ' . formatTime($raw['temp_min_time']),
            'note' => createDeltaIndicator($temp_min_delta ?? 0) . " ieri: " . formatValue($raw['temp_min_yesterday'], '°C', 1),
            'separator' => true
        ],
        
        // ---- SEZIONE UMIDITà€ ----
        [
            'type' => 'data',
            'label' => 'Umidità  relativa',
            'value' => formatValue($raw['hr_act'], '%', 0),
            'note' => '',
            'separator' => false,
            'interactive' => [
                'tooltip' => 'Clicca per grafico 24h',
                'clickable' => true,
                'action' => [
                    'type' => 'modal',
                    'endpoint' => 'api_grafico.php',
                    'params' => ['metric' => 'umidita', 'range' => '24h']
                ]
            ]
        ],
        
        [
            'type' => 'data',
            'label' => 'Umidità  max e ora',
            'value' => formatValue($raw['hr_max_today'], '%', 0) . ' ' . formatTime($raw['hr_max_time']),
            'note' => '',
            'separator' => false
        ],
        
        [
            'type' => 'data',
            'label' => 'Umidità  min e ora',
            'value' => formatValue($raw['hr_min_today'], '%', 0). ' ' . formatTime($raw['hr_min_time']),
            'note' => '',
            'separator' => true
        ],
        
        // ---- SEZIONE PUNTO RUGIADA ----
        [
            'type' => 'data',
            'label' => 'Punto rugiada',
            'value' => formatValue($raw['dew_act'], '°C', 1),
            'note' => 'Comfort: ' . createComfortIndicator($raw['dew_act'] ?? 0),
            'separator' => false,
            'interactive' => [
                'tooltip' => 'Clicca per grafico 24h',
                'clickable' => true,
                'action' => [
                    'type' => 'modal',
                    'endpoint' => 'api_grafico.php',
                    'params' => ['metric' => 'umidita', 'range' => '24h']
                ]
            ]
        ],

        [
            'type' => 'data',
            'label' => 'Punto rugiada max e ora',
            'value' => formatValue($raw['dew_max_today'], '°C', 1). ' ' . formatTime($raw['dew_point_max_time']),
            'note' => '',
            'separator' => false
        ],

        [
            'type' => 'data',
            'label' => 'Punto rugiada min e ora',
            'value' => formatValue($raw['dew_min_today'], '°C', 1). ' ' . formatTime($raw['dew_point_min_time']),
            'note' => '',
            'separator' => true
        ],
        
        // ---- SEZIONE PRESSIONE ----
        [
            'type' => 'data',
            'label' => 'Pressione @lm',
            'value' => formatValue($raw['press_act'], ' hPa', 1),
            'note' => createPressureTrendIndicator($press_delta_24h ?? 0) . " \u{0394}24h = {$press_delta_24h} hPa",
            'separator' => false,
            'interactive' => [
                'tooltip' => 'Clicca per grafico barometro',
                'clickable' => true,
                'action' => [
                    'type' => 'modal',
                    'endpoint' => 'api_grafico.php',
                    'params' => ['metric' => 'pressione', 'range' => '48h']
                ]
            ]
        ],

        [
            'type' => 'data',
            'label' => 'Pressione @lm max e ora',
            'value' => formatValue($raw['press_max_today'], '°C', 1). ' ' . formatTime($raw['press_max_time']),
            'note' => '',
            'separator' => false
        ],

        [
            'type' => 'data',
            'label' => 'Pressione @lm max e ora',
            'value' => formatValue($raw['press_min_today'], '°C', 1). ' ' . formatTime($raw['press_min_time']),
            'note' => '',
            'separator' => true
        ],
        
        // ---- SEZIONE VENTO ----
        [
            'type' => 'data',
            'label' => 'Vento: velocità ',
            'value' => formatValue($raw['wind_act'], ' km/h', 1). ' ' . formatTime($raw['wind_act_time']),
            'note' => createWindchillIcon($windChillValue) . ' Wind Chill: ' . calcolaTemperaturaPercepita($raw['temp_act'],$raw['wind_act']),
            'separator' => false
        ],

        [
            'type' => 'data',
            'label' => 'Direzione del vento',
            'value' => dirTesto($raw['wind_dir_act'] ?? 'N/D'),
            'note' => '',
            'separator' => false
        ],

        [
            'type' => 'data',
            'label' => 'Raffica max e ora',
            'value' => formatValue($raw['wind_max_today'], ' km/h', 1). ' ' . formatTime($raw['wind_max_time']),
            'note' => '',
            'separator' => false
        ],

        [
            'type' => 'data',
            'label' => 'Vento: media 15min',
            'value' => formatValue($raw['wind_avg_15m'], ' km/h', 1),
            'note' => 'MAX/24h:' . formatValue($raw['wind_avg_max_24h'], 'km/h', 1). ' ' . '(' . $raw['wind_avg_max_time'] . ')',
            'separator' => true
        ],
        
        

        // ----- SEZIONE PLUVIO
        ['type' => 'data',
        'label' => 'Pioggia cumulata ',
        'value' => '6h: ' . getPrecipitazioniCFR($pdo, '6h'),
        'note' => '24h: ' . getPrecipitazioniCFR($pdo, '24h'),
        'separator' => true,
        'link' => 'pluvio.html',  
        'interactive' => [
            'tooltip' => 'Clicca per dati pluviometrici',
            'clickable' => true
        ]
        ],

        // ---- SEZIONE RADIANZA ----
        [
            'type' => 'data',
            'label' => 'Radianza solare',
            'value' => formatValue($raw['rad_act'], ' W/m²', 0),
            'note' => getSolareMassimoGiornaliero($pdo), // Funzione esistente
            'separator' => false,
            'interactive' => [
                'tooltip' => 'Clicca per grafico giornaliero',
                'clickable' => true,
                'action' => [
                    'type' => 'modal',
                    'endpoint' => 'api_grafico.php',
                    'params' => ['metric' => 'radianza', 'range' => 'today']
                ]
            ]
        ],
        
        [
            'type' => 'data',
            'label' => 'Radianza cumulata giornaliera',
            'value' => 'giorno intero: ' . formatPercent($radianza['cumulato_percent_24h']),
            'note' => 'prima metà : ' . formatPercent($radianza['cumulato_percent_12h']),
            'separator' => true
        ],
        
    ];
    
    // ========================================================================
    // RISPOSTA FINALE
    // ========================================================================
    return [
        'success' => true,
        'metadata' => [
            'timestamp' => $now,
            'last_update' => $raw['last_update'],
            'data_freshness_minutes' => calculateMinutesAgo($raw['last_update']),
            'alerts_count' => count($alerts)
        ],
        'alerts' => $alerts,
        'rows' => $rows,
        'raw_data' => array_merge($raw, [
            'temp_delta_24h' => $temp_delta_24h,
            'temp_delta_1h' => $temp_delta_1h,
            'press_delta_24h' => $press_delta_24h,
            'press_delta_1h' => $press_delta_1h,
            'astro' => $astro,
            'radianza' => $radianza
        ])
    ];
}

// ============================================================================
// FUNZIONI HELPER
// ============================================================================

/**
 * Crea risposta di errore standardizzata
 */
function createErrorResponse(string $message): array {
    return [
        'success' => false,
        'error' => $message,
        'metadata' => ['timestamp' => get_now()],
        'rows' => [],
        'raw_data' => []
    ];
}

/**
 * Formatta valore con unità  di misura
 */
function formatValue($value, string $unit = '', int $decimals = 1): string {
    if ($value === null || $value === '') {
        return 'N/D';
    }
    return number_format((float)$value, $decimals, '.', '') . $unit;
}

/**
 * Formatta delta con segno
 */
function formatDelta($value): string {
    if ($value === null) return 'N/D';
    $sign = $value >= 0 ? '+' : '';
    return $sign . number_format($value, 1, '.', '');
}

/**
 * Formatta percentuale
 */
function formatPercent($value): string {
    if ($value === 'N/A' || $value === null) return 'N/D';
    return number_format((float)$value, 1) . '%';
}

/**
 * Formatta data/ora
 */
function formatDateTime(?string $datetime): string {
    if (!$datetime) return 'N/D';
    try {
        $dt = new DateTime($datetime);
        return $dt->format('d/m/Y') . ' - ' . $dt->format('H:i');
    } catch (Exception $e) {
        return 'N/D';
    }
}

/**
 * Formatta ora
 */
function formatTime(?string $time): string {
    if (!$time) return '';
    return substr($time, 0, 5); // HH:MM
}

/**
 * Calcola minuti trascorsi
 */
function calculateMinutesAgo(?string $datetime): string {
    if (!$datetime) return 'N/D';
    try {
        $then = new DateTime($datetime);
        $now = new DateTime(get_now());
        $diff = $now->diff($then);
        return (string)($diff->days * 24 * 60 + $diff->h * 60 + $diff->i);
    } catch (Exception $e) {
        return 'N/D';
    }
}

/**
 * Restituisce l'icona pallino per Wind Chill basata sulla differenza di temperatura
 * 
 * @param string $windChillValue Valore restituito da calcolaTemperaturaPercepita() (es: "-4.8°C" o "NA")
 * @return string HTML icona pallino
 */
/**
 * Restituisce l'icona pallino per Wind Chill basata sulla differenza di temperatura
 * 
 * @param string $windChillValue Valore restituito da calcolaTemperaturaPercepita() (es: "-4.8°C" o "NA")
 * @return string HTML icona pallino
 */
function createWindchillIcon($windChillValue) {
    // Se non c'à¨ wind chill attivo, nessun pallino
    if ($windChillValue === 'NA') {
        return '';
    }
    
    // Estrai il valore numerico dalla stringa (es: "-4.8°C" †’ -4.8)
    // floatval() converte automaticamente "+2.5°C" †’ 2.5, "-4.8°C" †’ -4.8
    $differenza = floatval($windChillValue);
    
    // Pallino blu se freddo significativo (< -1°C), grigio altrimenti
    if ($differenza < -1) {
        return '<span style="color: #4A90E2; font-size: 0.4em; vertical-align: middle;">🔵</span>'; // Blu
    } else {
        return '<span style="color: #CCCCCC; font-size: 0.5em; vertical-align: middle;">〇</span>'; // Grigio
    }
}
/**
 * Render icone sole (esistente - da mantenere)
 */
/**
 * Renderizza icone alba/tramonto cliccabili
 */
function renderSunIcons() {
    $svg_alba = '
    <span class="icon-sun-inline">
        <a href="#" onclick="apriLightboxFiltrato(1); return false;" data-filter="1" title="Mostra foto alba">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
                 stroke="#FFA500" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 18a5 5 0 0 0-10 0"/>
              <line x1="12" y1="2" x2="12" y2="9"/>
              <polyline points="5 12 12 5 19 12"/>
              <line x1="4" y1="22" x2="20" y2="22"/>
            </svg>
        </a>
        <span class="icon-label" style="color:#FFA500;">Alba</span>
    </span>';

    $svg_tramonto = '
    <span class="icon-sun-inline">
        <a href="#" onclick="apriLightboxFiltrato(2); return false;" data-filter="2" title="Mostra foto tramonto">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
                 stroke="#FF4500" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 18a5 5 0 0 0-10 0"/>
              <line x1="12" y1="9" x2="12" y2="2"/>
              <polyline points="19 12 12 19 5 12"/>
              <line x1="4" y1="22" x2="20" y2="22"/>
            </svg>
        </a>
        <span class="icon-label" style="color:#FF4500;">Tramonto</span>
    </span>';

    return '<span class="icon-sun-wrapper">' . $svg_alba . $svg_tramonto . '</span>';
}

// ============================================================================
// FUNZIONI INDICATORI (esistenti - da mantenere)
// ============================================================================

function createDeltaIndicator($deltaValue): string {
    $deltaValue = floatval($deltaValue);
    
    if ($deltaValue > 2.0) {
        $color = '#ff4444';
        $title = 'Aumento significativo';
    } elseif ($deltaValue > 0.5) {
        $color = '#ff8800';
        $title = 'Aumento moderato';
    } elseif ($deltaValue >= -0.5) {
        $color = '#44aa44';
        $title = 'Stabile';
    } elseif ($deltaValue > -2.0) {
        $color = '#3399FF';
        $title = 'Diminuzione moderata';
    } else {
        $color = '#4444FF';
        $title = 'Diminuzione significativa';
    }
    
    return '<svg width="12" height="12" style="vertical-align: middle; margin-right: 5px;" title="' . $title . '">
              <circle cx="6" cy="6" r="4" fill="' . $color . '" stroke="#333" stroke-width="0.5"/>
            </svg>';
}

function createPressureTrendIndicator($deltaValue): string {
    $deltaValue = floatval($deltaValue);
    
    if ($deltaValue > 3) {
        $color = '#ff4444';
        $title = 'Pressione in rapido aumento';
    } elseif ($deltaValue > 1) {
        $color = '#ff8800';
        $title = 'Pressione in aumento';
    } elseif ($deltaValue > -1) {
        $color = '#44aa44';
        $title = 'Pressione stabile';
    } elseif ($deltaValue > -3) {
        $color = '#3399FF';
        $title = 'Pressione in diminuzione';
    } else {
        $color = '#4444FF';
        $title = 'Pressione in rapida diminuzione';
    }
    
    return '<svg width="12" height="12" style="vertical-align: middle; margin-right: 5px;" title="' . $title . '">
              <circle cx="6" cy="6" r="4" fill="' . $color . '" stroke="#333" stroke-width="0.5"/>
            </svg>';
}

function createComfortIndicator($dewpointValue): string {
    $dewpointValue = floatval($dewpointValue);
    
    if ($dewpointValue < 8) {
        $color = '#FFF';
        $title = 'NA';
    } elseif ($dewpointValue >= 8 && $dewpointValue < 10) {
        $color = '#ADD8E6';
        $title = 'Secco';
    } elseif ($dewpointValue >= 10 && $dewpointValue < 16) {
        $color = '#44aa44';
        $title = 'Confortevole';
    } elseif ($dewpointValue >= 16 && $dewpointValue < 20) {
        $color = '#FFFF99';
        $title = 'Umido ma confortevole';
    } elseif ($dewpointValue >= 20 && $dewpointValue < 24) {
        $color = '#FFA500';
        $title = 'Umido e scomodo';
    } else {
        $color = '#ff4444';
        $title = 'Opprimente, rischio colpo di calore';
    }
    
    return '<svg width="12" height="12" style="vertical-align: middle; margin-right: 5px;" title="' . $title . '">
              <circle cx="6" cy="6" r="4" fill="' . $color . '" stroke="#333" stroke-width="1"/>
            </svg>';
}


/**
 * Calcola la temperatura percepita (wind chill) con indicatore colorato
 * 
 * Formula Wind Chill: Environment Canada / NOAA (2001)
 * 
 * @param float $temp Temperatura in °C
 * @param float $windSpeed Velocità  del vento in km/h
 * @return string HTML  differenza di temperatura, o stringa vuota se N/A
 */
function calcolaTemperaturaPercepita($temp, $windSpeed) {
    // Wind Chill (T ‰¤ 10°C e vento > 4.8 km/h)
    if ($temp <= 10 && $windSpeed > 4.8) {
        // Formula Wind Chill (North American/UK)
        $windChill = 13.12 + 0.6215 * $temp - 11.37 * pow($windSpeed, 0.16) + 0.3965 * $temp * pow($windSpeed, 0.16);
        
        // Calcola la differenza
        $differenza = $windChill - $temp;
        
        // Formatta l'output SENZA pallino
        $segno = $differenza >= 0 ? '+' : '';
        return sprintf('%s%.1f°C', $segno, $differenza);
    }
    
    // Zona neutra
    return 'NA';
}



/*======FUNZIONE VENTO=======*/

/**
 * Converte una direzione vento in gradi in testo (N, NNE, NE, ...).
 *
 * @param mixed $v Direzione in gradi (0€“360) o null
 * @return string Direzione testuale
 */
function dirTesto($v): string
{
    if ($v === null || $v === '' || !is_numeric($v)) {
        return '--';
    }

    $deg = floatval($v);

    // Normalizza 0€“360
    $deg = fmod($deg, 360.0);
    if ($deg < 0) {
        $deg += 360.0;
    }

    $dirs = [
        'N','NNE','NE','ENE',
        'E','ESE','SE','SSE',
        'S','SSW','SW','WSW',
        'W','WNW','NW','NNW'
    ];

    // Ogni settore = 22.5°
    $i = (int) round($deg / 22.5) % 16;

    return $dirs[$i];
}


/*
 * Crea indicatore Windchill/Heat Index
 * @param float|string $differenza Differenza temperatura percepita
 * @return string SVG pallino colorato
 */
function createWindchillHeatIndicator($differenza) {
    // Validazione input
    if ($differenza === null || $differenza === '' || $differenza === 'NA' || !is_numeric($differenza)) {
        return '<svg width="12" height="12" style="vertical-align: middle; margin: 0 3px;" title="N/A">
                  <circle cx="6" cy="6" r="4" fill="#ffffff" stroke="#333" stroke-width="1"/>
                </svg>';
    }
    
    $differenza = floatval($differenza);

    // Determina colore e descrizione
    if ($differenza < -2) {
        $color = '#0088ff';  // Blu - freddo
        $title = 'Sensazione di freddo significativa';
    } elseif ($differenza > 2) {
        $color = '#FFA500';  // Arancione - caldo
        $title = 'Sensazione di caldo significativa';
    } else {
        $color = '#ffffff';  // Bianco - neutro
        $title = 'Sensazione neutra';
    }

    return '<svg width="12" height="12" style="vertical-align: middle; margin: 0 3px;" title="' . htmlspecialchars($title) . '">
              <circle cx="6" cy="6" r="4" fill="' . $color . '" stroke="#333" stroke-width="1"/>
            </svg>';
}

// =====================================
// FUNZIONI DATI PLUVIOMETRICI
// =====================================

/**
 * Recupera precipitazioni CFR per intervallo specificato
 * 
 * @param PDO|null $pdo Connessione database
 * @param string $intervallo '1h', '3h', '6h', '12h', o '24h'
 * @return string Formato: "12.5 mm (14:30)" o "N/A"
 */
function getPrecipitazioniCFR(?PDO $pdo, string $intervallo = '6h'): string {
    if ($pdo === null) {
        return "N/A";
    }

    // Mappa intervallo †’ colonna database
    $colonne_valide = [
        '1h'  => 'prec_1h',
        '3h'  => 'prec_3h',
        '6h'  => 'prec_6h',
        '12h' => 'prec_12h',
        '24h' => 'prec_24h'
    ];

    // Validazione input
    if (!isset($colonne_valide[$intervallo])) {
        return "N/A (intervallo non valido)";
    }

    $colonna = $colonne_valide[$intervallo];

    try {
        $table_cfr = table_name('precipitazioni_cfr');
        
        $sql = "
            SELECT 
                {$colonna} as precipitazioni,
                ultimi_dati
            FROM 
                {$table_cfr}
            ORDER BY 
                data_import DESC
            LIMIT 1
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || $row['precipitazioni'] === null) {
            return "N/A";
        }

        // Formatta precipitazioni (1 decimale)
        $prec = number_format((float)$row['precipitazioni'], 1, '.', '');
        
        // Estrai HH:MM da ultimi_dati
        $ora = 'N/A';
        if (!empty($row['ultimi_dati'])) {
            try {
                $dt = new DateTime($row['ultimi_dati']);
                $ora = $dt->format('H:i');
            } catch (Exception $e) {
                // Mantieni N/A se parsing fallisce
            }
        }

        return "{$prec} mm {$ora}";

    } catch (\Throwable $e) {
        file_put_contents(
            __DIR__ . '/log_funz.txt',
            "[ERROR] getPrecipitazioniCFR({$intervallo}): " . $e->getMessage() . ' (' . date('Y-m-d H:i:s') . ")\n",
            FILE_APPEND
        );
        return "N/A";
    }
}




// ============================================================================
// FUNZIONI RADIANZA (importa dalle esistenti)
// ============================================================================

/**
 * Restituisce info sul massimo solare teorico della giornata
 * Usa il simulatore PHP (no database)
 * 
 * @param PDO|null $pdo Non più usato (mantenuto per compatibilità)
 * @return string "Teor Max e ora: XXX W/m² (HH:MM UTC)"
 */
function getSolareMassimoGiornaliero(?PDO $pdo): string {
    try {
        $teorico = getSolarRadiationTheoretical();
        
        $max_wm2 = round($teorico['irradianza_max_wm2'], 0);
        $ora_max = $teorico['ora_max_utc'];
        
        // Converti ora UTC in locale (Europe/Rome)
        try {
            $dt_utc = new DateTime($ora_max . ' UTC', new DateTimeZone('UTC'));
            $dt_local = $dt_utc->setTimezone(new DateTimeZone('Europe/Rome'));
            $ora_locale = $dt_local->format('H:i');
            
            return "Teor Max e ora: {$max_wm2} W/m² ({$ora_locale} loc)";
        } catch (Exception $e) {
            // Fallback: mostra UTC se conversione fallisce
            return "Teor Max e ora: {$max_wm2} W/m² ({$ora_max} UTC)";
        }
        
    } catch (Throwable $e) {
        error_log("Errore getSolareMassimoGiornaliero: " . $e->getMessage());
        return "Teor Max e ora: N/A";
    }
}

/**
 * Calcola le percentuali di radianza cumulata rispetto ai valori teorici
 * Usa il simulatore solare PHP (no database esterno)
 * 
 * @param PDO $pdo Connessione database per dati reali
 * @return array [
 *   'cumulato_percent_12h' => string|float,  // Percentuale fino al picco
 *   'cumulato_percent_24h' => string|float,  // Percentuale giornata completa
 *   'teorico_max_wm2' => float,              // Picco teorico (W/m²)
 *   'teorico_ora_max' => string,             // Ora picco teorico (HH:MM UTC)
 *   'teorico_energia_12h' => float,          // Energia teorica fino al picco
 *   'teorico_energia_24h' => float           // Energia teorica giornaliera
 * ]
 */
function getSolareteoricoMezzaGiornata(?PDO $pdo): array {
    if ($pdo === null) {
        return [
            'cumulato_percent_12h' => 'N/A',
            'cumulato_percent_24h' => 'N/A',
            'teorico_max_wm2' => 0,
            'teorico_ora_max' => 'N/A',
            'teorico_energia_12h' => 0,
            'teorico_energia_24h' => 0
        ];
    }

    try {
        // ====================================================================
        // 1. RECUPERA DATI TEORICI (da simulatore PHP, NO database)
        // ====================================================================
        $teorico = getSolarRadiationTheoretical();
        
        $teorico_energia_12h = $teorico['energia_metà_giornata_whm2'];
        $teorico_energia_24h = $teorico['energia_totale_whm2'];
        $teorico_max_wm2 = $teorico['irradianza_max_wm2'];
        $teorico_ora_max_utc = $teorico['ora_max_utc'];
        
        // ====================================================================
        // 2. RECUPERA RADIANZA ATTUALE (ultimi 15 minuti dal DB reale)
        // ====================================================================
        $table = table_name('dati_meteo_simignano');
        $now = get_now();
        $now_15m_ago = date('Y-m-d H:i:s', strtotime($now . ' -15 minutes'));
        $today = get_now('Y-m-d');
        
        $sql = "
            SELECT radianza_int_whm2
            FROM {$table}
            WHERE data_ora BETWEEN :start_time AND :end_time
            AND DATE(data_ora) = :today
            AND radianza_int_whm2 IS NOT NULL
            AND radianza_int_whm2 > 0
            ORDER BY data_ora DESC
            LIMIT 1
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':start_time' => $now_15m_ago,
            ':end_time' => $now,
            ':today' => $today
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $radianza_attuale = isset($row['radianza_int_whm2']) && is_numeric($row['radianza_int_whm2'])
                            ? floatval($row['radianza_int_whm2']) : null;

        // ====================================================================
        // 3. CALCOLA PERCENTUALE 24H (valore attuale vs teorico totale)
        // ====================================================================
        $cumulato_percent_24h = 'N/A';
        if ($teorico_energia_24h > 0 && $radianza_attuale !== null) {
            $cumulato_percent_24h = round(($radianza_attuale / $teorico_energia_24h) * 100, 1);
        }

        // ====================================================================
        // 4. CALCOLA PERCENTUALE 12H (logica adattiva)
        // ====================================================================
        $cumulato_percent_12h = 'N/A';
        $ora_attuale = get_datetime();
        
        if ($teorico_energia_12h > 0 && $radianza_attuale !== null) {
            // CASO BASE: usa valore attuale (se siamo prima del picco)
            $cumulato_percent_12h = round(($radianza_attuale / $teorico_energia_12h) * 100, 1);
            
            // CASO AVANZATO: se siamo dopo l'ora massima, cerca il picco storico
            try {
                $ora_massima_utc_obj = new DateTime($teorico_ora_max_utc . ' UTC', new DateTimeZone('UTC'));
                $ora_massima_loc = (clone $ora_massima_utc_obj)->setTimezone(new DateTimeZone('Europe/Rome'));
                
                // Se siamo DOPO l'ora massima teorica
                if ($ora_attuale > $ora_massima_loc) {
                    // Cerca il picco reale in finestra ±5 minuti dall'ora teorica
                    $ora_massima_start = (clone $ora_massima_loc)->modify('-5 minutes')->format('Y-m-d H:i:s');
                    $ora_massima_end = (clone $ora_massima_loc)->modify('+5 minutes')->format('Y-m-d H:i:s');
                    
                    $sql = "
                        SELECT radianza_int_whm2
                        FROM {$table}
                        WHERE data_ora BETWEEN :ora_start AND :ora_end
                        AND DATE(data_ora) = :today
                        AND radianza_int_whm2 IS NOT NULL
                        ORDER BY radianza_int_whm2 DESC
                        LIMIT 1
                    ";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':ora_start' => $ora_massima_start,
                        ':ora_end' => $ora_massima_end,
                        ':today' => $today
                    ]);
                    $row_picco = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Se troviamo picco storico, lo usiamo
                    if ($row_picco && isset($row_picco['radianza_int_whm2'])) {
                        $radianza_picco_12h = floatval($row_picco['radianza_int_whm2']);
                        $cumulato_percent_12h = round(($radianza_picco_12h / $teorico_energia_12h) * 100, 1);
                    }
                }
            } catch (Exception $e) {
                // Mantiene valore base in caso di errore
                error_log("Errore parsing ora_massima in getSolareteoricoMezzaGiornata: " . $e->getMessage());
            }
        }

        // ====================================================================
        // 5. RETURN COMPLETO
        // ====================================================================
        return [
            'cumulato_percent_12h' => $cumulato_percent_12h,
            'cumulato_percent_24h' => $cumulato_percent_24h,
            'teorico_max_wm2' => $teorico_max_wm2,
            'teorico_ora_max' => $teorico_ora_max_utc,
            'teorico_energia_12h' => $teorico_energia_12h,
            'teorico_energia_24h' => $teorico_energia_24h
        ];

    } catch (Throwable $e) {
        error_log("Errore getSolareteoricoMezzaGiornata: " . $e->getMessage());
        return [
            'cumulato_percent_12h' => 'N/A',
            'cumulato_percent_24h' => 'N/A',
            'teorico_max_wm2' => 0,
            'teorico_ora_max' => 'N/A',
            'teorico_energia_12h' => 0,
            'teorico_energia_24h' => 0
        ];
    }
}



// =====================================
// =========ALBA_TRAMONTO==============
// =====================================
require_once __DIR__ . '/../envelop.php'; // O il tuo file di connessione
require_once __DIR__ . '/env_tables_helper.php';
// ========== CONNESSIONE DB ==========
 // già  definita in envelop_lettura.php
 $table_name_bis = table_name('DB_immagini_36h');
//impostazioni data odierna
$oggi_sql = get_now('Y-m-d');   // † questo rispetta USE_TEST_MODE
$ieri_sql = date('Y-m-d', strtotime($oggi_sql . ' -1 day'));


// ========== QUERY UNICA ==========
$sql = "SELECT FILE, DATA_ORA, Temp, HR, P_hPa, vento_kmh, Dir_text, alba_tramonto 
        FROM $table_name_bis
        ORDER BY DATA_ORA DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$images = [];
foreach ($rows as $row) {

    // DEBUG AGGIUNTO
    //echo "<pre style='color:red;font-size:12px'>DEBUG ROW: " . print_r($row, true) . "</pre>";

    $images[] = [
        'src' => 'FoscamCamera_E8ABFAA799FE/snap/' . $row['FILE'],
        'data_ora' => date('d/m/Y H:i', strtotime($row['DATA_ORA'])),
        'data_ora_sql' => $row['DATA_ORA'], // Mantieni timestamp completo
        'temp' => $row['Temp'],
        'hr' => $row['HR'],
        'p_hpa' => $row['P_hPa'],
        'wind_kmh' => $row['vento_kmh'],
        'dir_text' => $row['Dir_text'],
        'alba_tramonto' => $row['alba_tramonto']
    ];
}
?>



<!-- ========== JAVASCRIPT ========== -->


<script>
    // Passa le date odierna e ieri a JavaScript
    window.todaySQL = "<?php echo $oggi_sql; ?>";
    window.yesterdaySQL = "<?php echo $ieri_sql; ?>";
    window.allImages = <?php echo json_encode($images); ?>;
    window.images = window.allImages;
</script>

<script>
  window.phpNowTs = <?php echo get_time() * 1000; ?>;  // coerente con TEST/PROD
</script>


<script>
    // Passa i dati a JavaScript

    
    window.allImages = <?php echo json_encode($images); ?>;
  window.images = window.allImages;

  function apriLightboxFiltrato(flag) {
    var tutte = window.allImages || [];
    var oggi = window.todaySQL;
    var ieri = window.yesterdaySQL;

    window.images = [];

    // tempo deciso dal PHP (test o prod)
    var nowTs = window.phpNowTs;
    if (!nowTs) nowTs = Date.now(); // fallback se dimentichi phpNowTs

    var limiteTs = nowTs - (((23 * 60 * 60) + (58 * 60)) * 1000);

    for (var k = 0; k < tutte.length; k++) {
      var img = tutte[k];

      // timestamp immagine (MEGLIO: usare img.data_ts dal PHP)
      var imgTs = img.data_ts ? +img.data_ts : Date.parse(img.data_ora_sql);

      var matchData = !isNaN(imgTs) && imgTs >= limiteTs;
      var matchFlag = (parseInt(img.alba_tramonto, 10) === parseInt(flag, 10));

      if (matchFlag && matchData) {
        window.images.push(img);
      }
    }

    // aggiorna icone attive
    var links = document.querySelectorAll('a[data-filter]');
    for (var j = 0; j < links.length; j++) {
      links[j].classList.remove('active');
      if (links[j].getAttribute('data-filter') == flag) {
        links[j].classList.add('active');
      }
    }

    if (window.images.length > 0) {
      openLightbox(0);
    } else {
      alert("Nessuna immagine trovata (filtro=" + flag + ") nella finestra 23h58m. Oggi (" + oggi + ") / ieri (" + ieri + ")");
    }
  }

  window.apriLightboxFiltrato = apriLightboxFiltrato;
</script>

<!-- ========== LIGHTBOX JAVASCRIPT AUTONOMO SOLO PER ALBA/TRAMONTO========== -->

<script>
(function() {
    'use strict';
    
    let currentIndex = 0;

    // ========== UTILITY FUNCTIONS ==========
    
    /** Numero finito? (ES5-safe) */
    function isFiniteNumber(n) { 
        return typeof n === 'number' && isFinite(n); 
    }

    /** Numero o null */
    function numOrNull(v) {
        return (v === null || v === '' || !isFinite(+v)) ? null : (+v);
    }

    /** Getter sicuro */
    function get(obj, key) {
        return (obj && obj[key] !== null) ? obj[key] : null;
    }

    /** Stringa sicura */
    function getStr(obj, key) {
        var v = get(obj, key);
        return (v === null) ? '' : String(v);
    }

    /** Primo tra pià¹ campi definiti */
    function pickFirstDefined(obj, keys) {
        if (!obj) return null;
        for (var i = 0; i < keys.length; i++) {
            if (obj[keys[i]] !== null) return obj[keys[i]];
        }
        return null;
    }

    /** Direzione in testo: converte gradi †’ N/NE/E/... o restituisce stringa */
    function dirTesto(v) {
        if (v === null) return '--';
        var deg = +v;
        if (isFinite(deg)) {
            var dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
            var i = Math.round((deg % 360) / 22.5) % 16;
            return dirs[i < 0 ? i + 16 : i];
        }
        return String(v);
    }

    /** Crop verticale dell'immagine (taglia px in basso). Ritorna dataURL. */
    function cropImageBottom(src, cropBottomPx, cb) {
        var tempImg = new Image();
        tempImg.onload = function () {
            try {
                var w = tempImg.width;
                var h = Math.max(1, tempImg.height - cropBottomPx);
                var canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(tempImg, 0, 0, w, h, 0, 0, w, h);
                cb(canvas.toDataURL());
            } catch (e) {
                cb(src); // fallback se canvas fallisce
            }
        };
        tempImg.onerror = function () { cb(src); };
        tempImg.src = src;
    }

    /** Costruisce la stringa info dell'immagine corrente. */
    function buildInfoText(record) {
        // Data/ora
        var d = record.data_ora || 'N/A';

        // Temperatura
        var t = parseFloat(record.temp);
        var tTxt = isFinite(t) ? Math.round(t) + '°C' : 'N/A';

        // Umidità 
        var hr = parseFloat(record.hr);
        var hTxt = isFinite(hr) ? Math.round(hr) + '%' : 'N/A';

        // Pressione
        var p = parseFloat(record.p_hpa);
        var pTxt = isFinite(p) ? Math.round(p) + ' hPa' : 'N/A';

        // Vento
        var windKmh = parseFloat(record.wind_kmh);
        var wTxt = isFinite(windKmh) ? windKmh + ' km/h' : 'N/A';

        // Direzione (converti gradi †’ testo)
        var dirGradi = parseFloat(record.dir_text);
        var dTxt = isFinite(dirGradi) ? dirTesto(dirGradi) : record.dir_text || 'N/A';

        // Alba/Tramonto (solo se flag presente)
        var sunPhase = '';
        if (record.alba_tramonto) {
            var flag = parseInt(record.alba_tramonto);
            if (flag === 1) {
                sunPhase = ' | Alba';
            } else if (flag === 2) {
                sunPhase = ' | Tramonto';
            }
        }

        return d + ' | T ' + tTxt + ' | UR ' + hTxt + ' | ' + pTxt + ' | Vento ' + wTxt + ', ' + dTxt + sunPhase;
    }

    // ========== LIGHTBOX FUNCTIONS ==========

   function openLightbox(index) {
    if (!window.images || window.images.length === 0) return;

    // ðŸ”’ CLAMP DELL€™INDICE (FONDAMENTALE)
    if (index < 0) index = 0;
    if (index > window.images.length - 1) {
        index = window.images.length - 1;
    }

    currentIndex = index;

    const lightbox = document.getElementById('lightbox');
    const img = document.getElementById('lightbox-img');
    const info = document.getElementById('lightbox-info');

    const current = window.images[currentIndex];
    if (!current) return;

    info.innerHTML = buildInfoText(current);

    cropImageBottom(current.src, 80, function (croppedSrc) {
        img.src = croppedSrc;
    });

    lightbox.classList.add('active');
    updateNavButtons();
}


    function closeLightbox() {
        document.getElementById('lightbox').classList.remove('active');
    }

    function prevImage(event) {
        event.stopPropagation();
        if (currentIndex > 0) {
            openLightbox(currentIndex - 1);
        }
    }

    function nextImage(event) {
        event.stopPropagation();
        if (currentIndex < window.images.length - 1) {
            openLightbox(currentIndex + 1);
        }
    }

    function updateNavButtons() {
        const prevBtn = document.querySelector('.nav-btn.prev');
        const nextBtn = document.querySelector('.nav-btn.next');
        
        if (prevBtn) prevBtn.disabled = (currentIndex === 0);
        if (nextBtn) nextBtn.disabled = (currentIndex === window.images.length - 1);
    }
    
    function aggiornaLightbox() {
  
          var items = window.images || [];
          var record = items[currentIndex];
          if (!record) return;
        
          var src = getStr(record, 'src').trim();
          if (!src) return;
        
          // Crop e set immagine
          cropImageBottom(src, 80, function (croppedSrc) {
            var imgEl = document.getElementById('lightbox-img');
            if (imgEl) {
              imgEl.src = croppedSrc;
              
            }
          });

  // Info text
  var infoEl = document.getElementById('lightbox-info');
  if (infoEl) infoEl.textContent = buildInfoText(record);
}

    // ========== EVENT LISTENERS ==========
    
    // Keydown con auto-repeat su frecce
    document.addEventListener('keydown', function (event) {
      var lb = document.getElementById('lightbox');
      if (!lb || !lb.classList.contains('active')) return;
    
      var key = event.key || event.code;
    
      if (key === ' ' || key === 'Spacebar') {
        event.preventDefault();
        if (isRewinding) rewindToCurrent();
        else if (isForwarding) forwardToNewest();
        return;
      }
    
      if (key === 'Escape' || key === 'Esc') {
        closeLightbox();
        return;
      }
    
      if (key === 'ArrowLeft') {
        var items = window.images || [];
        if (currentIndex < items.length - 1) {
          currentIndex++; aggiornaLightbox(); updateNavButtons();
        }
      }
    
      if (key === 'ArrowRight') {
        if (currentIndex > 0) {
          currentIndex--; aggiornaLightbox(); updateNavButtons();
        }
        
        }
    });
    
    
    // Touch swipe su lightbox
    document.addEventListener('DOMContentLoaded', function () {
      var lightbox = document.getElementById('lightbox');
      if (!lightbox) return;
    
      var touchStartX = 0;
      var touchEndX   = 0;
    
      lightbox.addEventListener('touchstart', function (e) {
        touchStartX = e.changedTouches[0].screenX;
      });
    
      lightbox.addEventListener('touchend', function (e) {
        touchEndX = e.changedTouches[0].screenX;
        var threshold = 50;
        if (touchEndX < touchStartX - threshold) {
          // swipe left †’ avanti nel tempo (indice +1)
          var items = window.images || [];
          if (currentIndex < items.length - 1) {
            currentIndex++; aggiornaLightbox(); updateNavButtons();
          }
        } else if (touchEndX > touchStartX + threshold) {
          // swipe right †’ indietro nel tempo (indice -1)
          if (currentIndex > 0) {
            currentIndex--; aggiornaLightbox(); updateNavButtons();
          }
        }
      });
    });

    // ========== GLOBAL EXPORTS ==========
    
    window.openLightbox = openLightbox;
    window.closeLightbox = closeLightbox;
    window.prevImage = prevImage;
    window.nextImage = nextImage;
})();
</script>
<!-- ========== LIGHTBOX HTML ========== -->
<div class="lightbox" id="lightbox">
    <button id="close-btn" class="lightbox-control-btn lightbox-close" aria-label="Chiudi"  onclick="closeLightbox()">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="red">
            <path d="M18 6L6 18M6 6l12 12" stroke="red" stroke-width="5" stroke-linecap="round" />
        </svg>
    </button>

    <div class="lightbox-content">
        <img id="lightbox-img" src="" alt="Immagine ingrandita">
        <div id="lightbox-info" class="lightbox-info"></div>
    </div>
    
    <button class="nav-btn prev" onclick="prevImage(event)" aria-label="Immagine precedente">
        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="red" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 18 15 12 9 6" />
        </svg>
    </button>
    
    <button class="nav-btn next" onclick="nextImage(event)" aria-label="Immagine successiva">
        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="red" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6" />
        </svg>
    </button>
</div>






