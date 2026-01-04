<?php
/**
 * ============================================================================
 * API GRAFICO TERMO-IGROMETRICO AVANZATO
 * ============================================================================
 * 
 * Features:
 * 1. Multi-dataset: Temperatura, Umidita, Dew Point
 * 2. Medie mobili 7 giorni (max rosso, min blu)
 * 3. Dew point con segmenti colorati per soglie
 * 4. Doppio asse Y: Temperatura (sx, ±8°C), Umidita (dx, 0-100%)
 * 5. Griglia visibile, zero line colorata
 * 6. Statistiche aggregate
 * 
 * PARAMETRI:
 * - range: 24h|7d|30d (default: 24h)
 * 
 * OUTPUT:
 * {
 *   "success": true,
 *   "chart_config": {...},   // Configurazione Chart.js completa
 *   "chart_data": {...},     // Dati raw per slider temporale
 *   "stats": [...],          // Statistiche per box
 *   "legend": [...],         // Legend items custom
 *   "metadata": {...}
 * }
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ALL);

// ============================================================================
// AUTO-DETECT HELPER FILES
// ============================================================================
$base_paths = [
    __DIR__ . '/..',                    // Parent (se API in /api/)
    __DIR__ . '/../..',                 // Grandparent
    dirname($_SERVER['DOCUMENT_ROOT']), // Document root parent
    $_SERVER['DOCUMENT_ROOT']           // Document root
];

$helper_loaded = false;
foreach ($base_paths as $base_path) {
    $env_helper = $base_path . '/env_tables_helper.php';
    $datetime_helper = $base_path . '/datetime_helper.php';
    
    if (file_exists($env_helper) && file_exists($datetime_helper)) {
        require_once $env_helper;
        require_once $datetime_helper;
        $helper_loaded = true;
        break;
    }
}

if (!$helper_loaded) {
    echo json_encode([
        'success' => false,
        'error' => 'File helper (env_tables_helper.php, datetime_helper.php) non trovati',
        'debug' => [
            'current_file' => __FILE__,
            'searched_paths' => array_map(function($p) {
                return $p . '/env_tables_helper.php';
            }, $base_paths)
        ]
    ], JSON_PRETTY_PRINT);
    exit;
}

// Cerca envelop_lettura.php
$envelop_paths = [
    __DIR__ . '/../../envelop_lettura.php',
    __DIR__ . '/../envelop_lettura.php',
    dirname($_SERVER['DOCUMENT_ROOT']) . '/envelop_lettura.php',
    $_SERVER['DOCUMENT_ROOT'] . '/envelop_lettura.php'
];

$envelop_found = false;
foreach ($envelop_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $envelop_found = true;
        break;
    }
}

if (!$envelop_found) {
    echo json_encode([
        'success' => false,
        'error' => 'File envelop_lettura.php non trovato',
        'debug' => [
            'current_file' => __FILE__,
            'searched_paths' => $envelop_paths
        ]
    ], JSON_PRETTY_PRINT);
    exit;
}

// ============================================================================
// PARAMETRI
// ============================================================================
$range = isset($_GET['range']) ? $_GET['range'] : '24h';

$ranges_validi = ['24h', '7d', '30d'];
if (!in_array($range, $ranges_validi)) {
    echo json_encode([
        'success' => false,
        'error' => "Range non valido. Valori accettati: " . implode(', ', $ranges_validi)
    ]);
    exit;
}

// ============================================================================
// DETERMINA FINESTRA TEMPORALE
// ============================================================================
$now = get_now();

switch ($range) {
    case '24h':
        $start_time = date('Y-m-d H:i:s', strtotime($now . ' -24 hours'));
        $intervallo_minuti = 15;
        $time_unit = 'hour';
        $time_format = 'HH:mm';
        break;
    case '7d':
        $start_time = date('Y-m-d H:i:s', strtotime($now . ' -7 days'));
        $intervallo_minuti = 60;
        $time_unit = 'day';
        $time_format = 'dd/MM HH:mm';
        break;
    case '30d':
        $start_time = date('Y-m-d H:i:s', strtotime($now . ' -30 days'));
        $intervallo_minuti = 240; // 4 ore
        $time_unit = 'day';
        $time_format = 'dd/MM';
        break;
}

// ============================================================================
// QUERY DATI PRINCIPALI
// ============================================================================
$table = table_name('dati_meteo_simignano');

$sql = "
SELECT 
    DATE_FORMAT(data_ora, '%Y-%m-%d %H:%i:00') as timestamp,
    AVG(temperatura_C) as temp_avg,
    MIN(temperatura_C) as temp_min,
    MAX(temperatura_C) as temp_max,
    AVG(umidita_RH) as umidita_avg,
    AVG(dew_point_C) as dewpoint_avg
FROM {$table}
WHERE 
    data_ora >= :start_time
    AND temperatura_C IS NOT NULL
    AND umidita_RH IS NOT NULL
GROUP BY 
    DATE_FORMAT(data_ora, '%Y-%m-%d %H:%i:00'),
    FLOOR(UNIX_TIMESTAMP(data_ora) / (:intervallo * 60))
ORDER BY timestamp ASC
";

try {
    $stmt = $pdo_lettura->prepare($sql);
    $stmt->execute([
        ':start_time' => $start_time,
        ':intervallo' => $intervallo_minuti
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Errore database: ' . $e->getMessage()
    ]);
    exit;
}

if (empty($rows)) {
    echo json_encode([
        'success' => false,
        'error' => 'Nessun dato disponibile per il range selezionato'
    ]);
    exit;
}

// ============================================================================
// ELABORA DATI BASE
// ============================================================================
$labels = [];
$data_temp = [];
$data_temp_min = [];
$data_temp_max = [];
$data_umidita = [];
$data_dewpoint = [];

foreach ($rows as $row) {
    $labels[] = $row['timestamp'];
    $data_temp[] = round((float)$row['temp_avg'], 1);
    $data_temp_min[] = round((float)$row['temp_min'], 1);
    $data_temp_max[] = round((float)$row['temp_max'], 1);
    $data_umidita[] = round((float)$row['umidita_avg'], 0);
    $data_dewpoint[] = round((float)$row['dewpoint_avg'], 1);
}

// ============================================================================
// CALCOLA MEDIE MOBILI 7 GIORNI (MAX E MIN)
// ============================================================================
$media_max_7d = calcola_media_mobile_7gg_max($pdo_lettura, $table, $now);
$media_min_7d = calcola_media_mobile_7gg_min($pdo_lettura, $table, $now);

// Ripeti valori per tutti i timestamp
$data_media_max = array_fill(0, count($labels), $media_max_7d);
$data_media_min = array_fill(0, count($labels), $media_min_7d);

// ============================================================================
// DETERMINA RANGE ASSI Y
// ============================================================================
$temp_min_val = min(array_merge($data_temp_min, [$media_min_7d]));
$temp_max_val = max(array_merge($data_temp_max, [$media_max_7d]));

$y_temp_min = floor($temp_min_val - 8);
$y_temp_max = ceil($temp_max_val + 8);

// ============================================================================
// CREA SEGMENTI COLORATI PER DEW POINT
// ============================================================================
$dewpoint_segments = crea_segmenti_dewpoint($data_dewpoint);

// ============================================================================
// CALCOLA STATISTICHE
// ============================================================================
$stats = [
    [
        'label' => 'Temp. Attuale',
        'value' => end($data_temp) . ' C',
        'trend' => null,
        'trend_direction' => null
    ],
    [
        'label' => 'Temp. Max',
        'value' => max($data_temp_max) . ' C',
        'trend' => null,
        'trend_direction' => null
    ],
    [
        'label' => 'Temp. Min',
        'value' => min($data_temp_min) . ' C',
        'trend' => null,
        'trend_direction' => null
    ],
    [
        'label' => 'Umidita Attuale',
        'value' => end($data_umidita) . '%',
        'trend' => null,
        'trend_direction' => null
    ],
    [
        'label' => 'Dew Point Attuale',
        'value' => end($data_dewpoint) . ' C',
        'trend' => null,
        'trend_direction' => null
    ],
    [
        'label' => 'Media Max 7gg',
        'value' => round($media_max_7d, 1) . ' C',
        'trend' => null,
        'trend_direction' => null
    ],
    [
        'label' => 'Media Min 7gg',
        'value' => round($media_min_7d, 1) . ' C',
        'trend' => null,
        'trend_direction' => null
    ]
];

// Calcola trend temperatura
if (count($data_temp) >= 2) {
    $trend_val = round(end($data_temp) - $data_temp[0], 1);
    $stats[0]['trend'] = ($trend_val >= 0 ? '+' : '') . $trend_val . ' C';
    $stats[0]['trend_direction'] = $trend_val > 0 ? 'up' : ($trend_val < 0 ? 'down' : 'stable');
}

// ============================================================================
// CONFIGURAZIONE CHART.JS
// ============================================================================
$datasets = [
    // Temperatura
    [
        'label' => 'Temperatura',
        'metricType' => 'temperatura',
        'data' => $data_temp,
        'borderColor' => '#ff6b6b',
        'backgroundColor' => 'rgba(255, 107, 107, 0.1)',
        'borderWidth' => 3,
        'pointRadius' => 0,
        'pointHoverRadius' => 6,
        'fill' => false,
        'tension' => 0.4,
        'yAxisID' => 'y-temp',
        'hidden' => false
    ],
    
    // Umidita
    [
        'label' => 'Umidita',
        'metricType' => 'umidita',
        'data' => $data_umidita,
        'borderColor' => '#48dbfb',
        'backgroundColor' => 'rgba(72, 219, 251, 0.1)',
        'borderWidth' => 2,
        'pointRadius' => 0,
        'pointHoverRadius' => 6,
        'fill' => false,
        'tension' => 0.4,
        'yAxisID' => 'y-umidita',
        'hidden' => false
    ],
    
    // Dew Point (con segmenti colorati)
    [
        'label' => 'Dew Point',
        'metricType' => 'dewpoint',
        'data' => $data_dewpoint,
        'borderColor' => '#feca57',
        'backgroundColor' => 'transparent',
        'borderWidth' => 2,
        'pointRadius' => 0,
        'pointHoverRadius' => 6,
        'fill' => false,
        'tension' => 0.4,
        'yAxisID' => 'y-temp',
        'segment' => [
            'borderColor' => null // Will be set by JS callback
        ],
        'hidden' => false
    ],
    
    // Media Max 7gg
    [
        'label' => 'Media Max 7gg',
        'metricType' => 'temperatura',
        'data' => $data_media_max,
        'borderColor' => '#e74c3c',
        'backgroundColor' => 'transparent',
        'borderWidth' => 2,
        'borderDash' => [8, 4],
        'pointRadius' => 0,
        'fill' => false,
        'tension' => 0,
        'yAxisID' => 'y-temp',
        'hidden' => false
    ],
    
    // Media Min 7gg
    [
        'label' => 'Media Min 7gg',
        'metricType' => 'temperatura',
        'data' => $data_media_min,
        'borderColor' => '#3498db',
        'backgroundColor' => 'transparent',
        'borderWidth' => 2,
        'borderDash' => [8, 4],
        'pointRadius' => 0,
        'fill' => false,
        'tension' => 0,
        'yAxisID' => 'y-temp',
        'hidden' => false
    ]
];

$chart_config = [
    'type' => 'line',
    'data' => [
        'labels' => $labels,
        'datasets' => $datasets
    ],
    'options' => [
        'responsive' => true,
        'maintainAspectRatio' => false,
        'interaction' => [
            'mode' => 'index',
            'intersect' => false
        ],
        'plugins' => [
            'legend' => [
                'display' => false // Usiamo legend custom
            ],
            'tooltip' => [
                'enabled' => true,
                'backgroundColor' => 'rgba(0, 0, 0, 0.8)',
                'titleColor' => '#fff',
                'bodyColor' => '#fff',
                'borderColor' => '#667eea',
                'borderWidth' => 2,
                'padding' => 12,
                'displayColors' => true
            ],
            'zoom' => [
                'pan' => [
                    'enabled' => true,
                    'mode' => 'x'
                ],
                'zoom' => [
                    'wheel' => [
                        'enabled' => true
                    ],
                    'pinch' => [
                        'enabled' => true
                    ],
                    'mode' => 'x'
                ]
            ]
        ],
        'scales' => [
            'x' => [
                'type' => 'time',
                'time' => [
                    'unit' => $time_unit,
                    'displayFormats' => [
                        'hour' => $time_format,
                        'day' => $time_format
                    ]
                ],
                'grid' => [
                    'display' => true,
                    'color' => '#000000',
                    'lineWidth' => 2
                ],
                'ticks' => [
                    'maxRotation' => 45,
                    'minRotation' => 0
                ]
            ],
            'y-temp' => [
                'type' => 'linear',
                'position' => 'left',
                'min' => $y_temp_min,
                'max' => $y_temp_max,
                'title' => [
                    'display' => true,
                    'text' => 'Temperatura ( C)',
                    'color' => '#ff6b6b',
                    'font' => [
                        'size' => 14,
                        'weight' => 'bold'
                    ]
                ],
                'grid' => [
                    'display' => true,
                    'color' => '#000000',
                    'lineWidth' => 2,
                    'drawOnChartArea' => true,
                    'z' => 1
                ],
                'ticks' => [
                    'color' => '#ff6b6b'
                ],
                'border' => [
                    'display' => true,
                    'color' => '#ff6b6b',
                    'width' => 3
                ]
            ],
            'y-umidita' => [
                'type' => 'linear',
                'position' => 'right',
                'min' => 0,
                'max' => 100,
                'title' => [
                    'display' => true,
                    'text' => 'Umidita (%)',
                    'color' => '#48dbfb',
                    'font' => [
                        'size' => 14,
                        'weight' => 'bold'
                    ]
                ],
                'grid' => [
                    'display' => false,
                    'drawTicks' => true
                ],
                'ticks' => [
                    'color' => '#48dbfb'
                ],
                'border' => [
                    'display' => true,
                    'color' => '#48dbfb',
                    'width' => 3
                ]
            ]
        ]
    ],
    'plugins' => [
        // Plugin custom per zero line colorata
        [
            'id' => 'zeroLine',
            'afterDraw' => null // Implementato in JS
        ]
    ]
];

// ============================================================================
// LEGEND ITEMS
// ============================================================================
$legend = [
    ['label' => 'Temperatura', 'color' => '#ff6b6b', 'dashed' => false],
    ['label' => 'Umidita', 'color' => '#48dbfb', 'dashed' => false],
    ['label' => 'Dew Point', 'color' => '#feca57', 'dashed' => false],
    ['label' => 'Media Max 7gg', 'color' => '#e74c3c', 'dashed' => true],
    ['label' => 'Media Min 7gg', 'color' => '#3498db', 'dashed' => true],
    ['label' => 'DP < 10 C (freddo)', 'color' => '#2c3e50', 'dashed' => false],
    ['label' => 'DP 10-19 C (confortevole)', 'color' => '#27ae60', 'dashed' => false],
    ['label' => 'DP 20-23 C (caldo)', 'color' => '#f39c12', 'dashed' => false],
    ['label' => 'DP > 24 C (molto caldo)', 'color' => '#e74c3c', 'dashed' => false]
];

// ============================================================================
// RISPOSTA JSON
// ============================================================================
echo json_encode([
    'success' => true,
    'chart_config' => $chart_config,
    'chart_data' => [
        'labels' => $labels,
        'datasets' => [
            ['data' => $data_temp],
            ['data' => $data_umidita],
            ['data' => $data_dewpoint],
            ['data' => $data_media_max],
            ['data' => $data_media_min]
        ]
    ],
    'dewpoint_segments' => $dewpoint_segments,
    'stats' => $stats,
    'legend' => $legend,
    'metadata' => [
        'range' => $range,
        'start_time' => $start_time,
        'end_time' => $now,
        'data_points' => count($rows),
        'media_max_7d' => round($media_max_7d, 1),
        'media_min_7d' => round($media_min_7d, 1),
        'generated_at' => $now
    ]
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

// ============================================================================
// FUNZIONI HELPER
// ============================================================================

/**
 * Calcola media mobile 7 giorni delle temperature massime
 */
function calcola_media_mobile_7gg_max($pdo, $table, $now) {
    // Periodo: da (oggi -8 giorni) a (oggi -1 giorno)
    $end_date = date('Y-m-d 00:00:00', strtotime($now . ' -1 day'));
    $start_date = date('Y-m-d 00:00:00', strtotime($end_date . ' -7 days'));
    
    $sql = "
    SELECT AVG(temp_max_giornaliera) as media_max
    FROM (
        SELECT 
            DATE(data_ora) as giorno,
            MAX(temperatura_C) as temp_max_giornaliera
        FROM {$table}
        WHERE 
            data_ora >= :start_date
            AND data_ora < :end_date
            AND temperatura_C IS NOT NULL
        GROUP BY DATE(data_ora)
    ) as daily_max
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':start_date' => $start_date,
            ':end_date' => $end_date
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['media_max'] ? (float)$result['media_max'] : 0;
    } catch (PDOException $e) {
        error_log("Errore calcolo media max 7gg: " . $e->getMessage());
        return 0;
    }
}

/**
 * Calcola media mobile 7 giorni delle temperature minime
 */
function calcola_media_mobile_7gg_min($pdo, $table, $now) {
    $end_date = date('Y-m-d 00:00:00', strtotime($now . ' -1 day'));
    $start_date = date('Y-m-d 00:00:00', strtotime($end_date . ' -7 days'));
    
    $sql = "
    SELECT AVG(temp_min_giornaliera) as media_min
    FROM (
        SELECT 
            DATE(data_ora) as giorno,
            MIN(temperatura_C) as temp_min_giornaliera
        FROM {$table}
        WHERE 
            data_ora >= :start_date
            AND data_ora < :end_date
            AND temperatura_C IS NOT NULL
        GROUP BY DATE(data_ora)
    ) as daily_min
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':start_date' => $start_date,
            ':end_date' => $end_date
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['media_min'] ? (float)$result['media_min'] : 0;
    } catch (PDOException $e) {
        error_log("Errore calcolo media min 7gg: " . $e->getMessage());
        return 0;
    }
}

/**
 * Crea array di segmenti colorati per dew point
 * Ritorna array con indici e colori per ogni segmento
 */
function crea_segmenti_dewpoint($data_dewpoint) {
    $segments = [];
    
    foreach ($data_dewpoint as $idx => $dp) {
        if ($dp < 10) {
            $color = '#2c3e50'; // Nero
        } elseif ($dp < 20) {
            $color = '#27ae60'; // Verde
        } elseif ($dp < 24) {
            $color = '#f39c12'; // Arancione
        } else {
            $color = '#e74c3c'; // Rosso
        }
        
        $segments[] = [
            'index' => $idx,
            'color' => $color
        ];
    }
    
    return $segments;
}