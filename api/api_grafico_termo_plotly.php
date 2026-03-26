<?php
/**
 * API GRAFICO TERMO-IGROMETRICO - VERSIONE PLOTLY.JS
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

// AUTO-DETECT HELPER FILES
$base_paths = [__DIR__ . '/..', __DIR__ . '/../..', dirname($_SERVER['DOCUMENT_ROOT']), $_SERVER['DOCUMENT_ROOT']];
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
    echo json_encode(['success' => false, 'error' => 'Helper non trovati']);
    exit;
}
//==================================================
//calcola le variabili per la funzione showchartinfo
//==================================================
$now_str = get_now('Y-m-d H:i:s');
$now = new DateTime($now_str);

$end7d = (clone $now)->setTime(0,0,0)->modify('-1 day');  // ieri (00:00)
$start7d = (clone $end7d)->modify('-6 day');             // 7gg completi

$chart_info = [
  'media7d_start' => $start7d->format('Y-m-d'),
  'media7d_end'   => $end7d->format('Y-m-d'),
  'now'           => $now->format('Y-m-d H:i:s'),
];

// Cerca envelop_lettura.php
$envelop_paths = [__DIR__ . '/../../envelop_lettura.php', __DIR__ . '/../envelop_lettura.php', dirname($_SERVER['DOCUMENT_ROOT']) . '/envelop_lettura.php', $_SERVER['DOCUMENT_ROOT'] . '/envelop_lettura.php'];
$envelop_found = false;
foreach ($envelop_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $envelop_found = true;
        break;
    }
}
if (!$envelop_found) {
    echo json_encode(['success' => false, 'error' => 'File envelop_lettura.php non trovato']);
    exit;
}

// PARAMETRI
if (isset($_GET['start']) && isset($_GET['end'])) {
    $start_time = $_GET['start'];
    $end_time = $_GET['end'];
    $range = 'custom';
} else {
    $range = $_GET['range'] ?? '24h';
    if (!in_array($range, ['24h', '7d', '30d'])) {
        echo json_encode(['success' => false, 'error' => 'Range non valido']);
        exit;
    }
    $now = get_now();
    switch ($range) {
        case '24h': $start_time = date('Y-m-d H:i:s', strtotime($now . ' -24 hours')); $end_time = $now; break;
        case '7d': $start_time = date('Y-m-d H:i:s', strtotime($now . ' -7 days')); $end_time = $now; break;
        case '30d': $start_time = date('Y-m-d H:i:s', strtotime($now . ' -30 days')); $end_time = $now; break;
    }
}

$table = table_name('dati_meteo_simignano');

// Query range totale DB
$sql_total_range = "SELECT MIN(data_ora) as first_ever, MAX(data_ora) as last_ever FROM {$table} WHERE temperatura_C IS NOT NULL AND umidita_RH IS NOT NULL AND dew_point_C IS NOT NULL";
try {
    $stmt = $pdo_lettura->prepare($sql_total_range);
    $stmt->execute();
    $total_range = $stmt->fetch(PDO::FETCH_ASSOC);
    $first_data_ever = $total_range['first_ever'] ?? $start_time;
    $last_data_ever = $total_range['last_ever'] ?? $end_time;
} catch (PDOException $e) {
    $first_data_ever = $start_time;
    $last_data_ever = $end_time;
}

// Query dati principali - TUTTI I PUNTI (aggregati per minuto)
$sql = "
SELECT
  FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(data_ora) / 600) * 600) AS timestamp,

  AVG(temperatura_C) AS temp_avg,
  MIN(temperatura_C) AS temp_min,
  MAX(temperatura_C) AS temp_max,
  AVG(umidita_RH) AS umidita_avg,
  AVG(dew_point_C) AS dewpoint_avg,
  AVG(pressione_hPa) AS pressione_avg,
  AVG(vento_kmh) AS velocita_avg,

  MOD(
    DEGREES(ATAN2(
      AVG(SIN(RADIANS(direzione_vento_deg))),
      AVG(COS(RADIANS(direzione_vento_deg)))
    )) + 360,
    360
  ) AS dirvento_avg

FROM {$table}
WHERE data_ora >= :start_time
  AND data_ora <= :end_time
  AND temperatura_C IS NOT NULL
  AND umidita_RH IS NOT NULL
GROUP BY
  FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(data_ora) / 600) * 600)
ORDER BY
  timestamp ASC;

";

try {
    $stmt = $pdo_lettura->prepare($sql);
    $stmt->execute([':start_time' => $start_time, ':end_time' => $end_time]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Query error: ' . $e->getMessage()]);
    exit;
}

if (empty($rows)) {
    echo json_encode(['success' => false, 'error' => 'Nessun dato']);
    exit;
}

// âœ… SAMPLING UNIFORME se troppi punti
$MAX_POINTS = 4000;  // Limite punti (aumentabile se serve)
$total_points = count($rows);
$sampled_rows = $rows;

if ($total_points > $MAX_POINTS) {
    // Campiona uniformemente
    $step = $total_points / $MAX_POINTS;
    $sampled_rows = [];
    
    for ($i = 0; $i < $MAX_POINTS; $i++) {
        $index = (int)floor($i * $step);
        if (isset($rows[$index])) {
            $sampled_rows[] = $rows[$index];
        }
    }
    
    error_log("SAMPLING: $total_points punti -> " . count($sampled_rows) . " punti (step: " . round($step, 2) . ")");
} else {
    error_log("NO SAMPLING: $total_points punti (sotto limite $MAX_POINTS)");
}

$rows = $sampled_rows;

// FUNZIONE COLORE VENTO
function get_wind_color($velocita_kmh) {
    if ($velocita_kmh <= 5) return '#b0b0b0';      // Grigio chiaro (0-5 km/h)
    if ($velocita_kmh <= 10) return '#87ceeb';     // Azzurro (6-10 km/h)
    if ($velocita_kmh <= 15) return '#4169e1';     // Blu (11-15 km/h)
    if ($velocita_kmh <= 25) return '#00008b';     // Blu scuro (16-25 km/h)
    return '#ff00ff';                               // Fucsia (>25 km/h)
}

// ELABORAZIONE
$labels = []; $data_temp = []; $data_temp_min = []; $data_temp_max = []; $data_umidita = []; $data_dewpoint = []; $data_pressione = []; $data_dirvento = []; $velocita_vento = []; $colori_vento = [];
foreach ($rows as $row) {
    $labels[] = $row['timestamp'];
    $data_temp[] = round((float)$row['temp_avg'], 1);
    $data_temp_min[] = round((float)$row['temp_min'], 1);
    $data_temp_max[] = round((float)$row['temp_max'], 1);
    $data_umidita[] = round((float)$row['umidita_avg'], 0);
    $data_dewpoint[] = round((float)$row['dewpoint_avg'], 1);
    $data_pressione[] = round((float)$row['pressione_avg'], 1);
    $data_dirvento[] = round((float)$row['dirvento_avg'], 0);
    
    // Velocita vento e colore
    $vel = round((float)$row['velocita_avg'], 1);
    $velocita_vento[] = $vel;
    $colori_vento[] = get_wind_color($vel);
}

$temp_min_assoluto = min($data_temp_min);
$temp_max_assoluto = max($data_temp_max);
$y_temp_min = floor($temp_min_assoluto - 8);
$y_temp_max = ceil($temp_max_assoluto + 8);

// Range pressione (con padding 5 hPa)
$pressione_min_assoluto = !empty($data_pressione) ? min($data_pressione) : 1000;
$pressione_max_assoluto = !empty($data_pressione) ? max($data_pressione) : 1020;
$y_press_min = floor($pressione_min_assoluto - 5);
$y_press_max = ceil($pressione_max_assoluto + 5);

// Range direzione vento (fisso 0-360 gradi)
$y_dirvento_min = 0;
$y_dirvento_max = 360;

// MEDIA MOBILE (temperatura media dei punti visualizzati)
function calcola_media_mobile($data_temp, $labels) {
    $media_valore = round(array_sum($data_temp) / count($data_temp), 1);
    // Crea array costante per tutti i punti
    return array_fill(0, count($labels), $media_valore);
}
$data_media_mobile = calcola_media_mobile($data_temp, $labels);
$valore_media_mobile = count($data_temp) > 0 ? round(array_sum($data_temp) / count($data_temp), 1) : 0;

// MEDIE 7GG
function calcola_media_max_7gg($table, $now) {
    global $pdo_lettura;
    $end = date('Y-m-d 00:00:00', strtotime($now . ' -1 day'));
    $start = date('Y-m-d 00:00:00', strtotime($end . ' -7 days'));
    $sql = "SELECT AVG(temp_max) as media FROM (SELECT MAX(temperatura_C) as temp_max FROM {$table} WHERE data_ora >= :start AND data_ora < :end AND temperatura_C IS NOT NULL GROUP BY DATE(data_ora)) t";
    try {
        $stmt = $pdo_lettura->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r && $r['media'] ? (float)$r['media'] : 0;
    } catch (PDOException $e) { return 0; }
}
function calcola_media_min_7gg($table, $now) {
    global $pdo_lettura;
    $end = date('Y-m-d 00:00:00', strtotime($now . ' -1 day'));
    $start = date('Y-m-d 00:00:00', strtotime($end . ' -7 days'));
    $sql = "SELECT AVG(temp_min) as media FROM (SELECT MIN(temperatura_C) as temp_min FROM {$table} WHERE data_ora >= :start AND data_ora < :end AND temperatura_C IS NOT NULL GROUP BY DATE(data_ora)) t";
    try {
        $stmt = $pdo_lettura->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r && $r['media'] ? (float)$r['media'] : 0;
    } catch (PDOException $e) { return 0; }
}

$media_max_7d = calcola_media_max_7gg($table, get_now());
$media_min_7d = calcola_media_min_7gg($table, get_now());
$data_media_max = array_fill(0, count($labels), round($media_max_7d, 1));
$data_media_min = array_fill(0, count($labels), round($media_min_7d, 1));

// DEWPOINT SEGMENTS
function crea_segmenti_dewpoint($data) {
    $segments = [];
    foreach ($data as $idx => $dp) {
        if ($dp < 10) $color = '#808080';
        elseif ($dp < 20) $color = '#27ae60';
        elseif ($dp < 24) $color = '#f39c12';
        else $color = '#e74c3c';
        $segments[] = ['index' => $idx, 'value' => $dp, 'color' => $color];
    }
    return $segments;
}
$dewpoint_segments = crea_segmenti_dewpoint($data_dewpoint);

// STATISTICHE
$stats = [
    ['label' => 'Temperatura Attuale', 'value' => end($data_temp) . ' C', 'trend' => null, 'trend_direction' => null],
    ['label' => 'Temperatura Max', 'value' => max($data_temp_max) . ' C', 'trend' => null, 'trend_direction' => null],
    ['label' => 'Temperatura Min', 'value' => min($data_temp_min) . ' C', 'trend' => null, 'trend_direction' => null],
    ['label' => 'Umidita Attuale', 'value' => end($data_umidita) . '%', 'trend' => null, 'trend_direction' => null],
    ['label' => 'Dew Point Attuale', 'value' => end($data_dewpoint) . ' C', 'trend' => null, 'trend_direction' => null],
    ['label' => 'Media Max 7gg', 'value' => round($media_max_7d, 1) . ' C', 'trend' => null, 'trend_direction' => null],
    ['label' => 'Media Min 7gg', 'value' => round($media_min_7d, 1) . ' C', 'trend' => null, 'trend_direction' => null]
];
if (count($data_temp) >= 2) {
    $trend_val = round(end($data_temp) - $data_temp[0], 1);
    $stats[0]['trend'] = ($trend_val >= 0 ? '+' : '') . $trend_val . ' C';
    $stats[0]['trend_direction'] = $trend_val > 0 ? 'up' : ($trend_val < 0 ? 'down' : 'stable');
}

$now_for_display = get_now();
$end_7d = date('d M', strtotime($now_for_display . ' -1 day'));
$start_7d = date('d M', strtotime($now_for_display . ' -7 days'));
$periodo_7gg = "$start_7d - $end_7d";

// TRACES
$traces = [
    [
        'x' => $labels,
        'y' => $data_temp,
        'type' => 'scatter',
        'mode' => 'lines',
        'name' => 'Temperatura',
        'line' => ['color' => '#000000', 'width' => 1.5],
        'hovertemplate' => '%{x|%d %b, %H:%M} • <b>%{y:.1f}°C</b><extra></extra>',
        'yaxis' => 'y',
        'visible' => true,
        'showlegend' => false,
        'metricType' => 'temperatura'
    ],
    [
        'x' => $labels,
        'y' => $data_umidita,
        'type' => 'scatter',
        'mode' => 'lines',
        'name' => 'Umidita',
        'line' => ['color' => '#0000FF', 'width' => 1.5],
        'hovertemplate' => '%{x|%d %b, %H:%M} • <b>%{y:.0f}%</b><extra></extra>',
        'yaxis' => 'y2',
        'visible' => true,
        'showlegend' => false,
        'metricType' => 'umidita'
    ],
    [
        'x' => $labels,
        'y' => $data_dewpoint,
        'type' => 'scatter',
        'mode' => 'lines',
        'name' => 'Dew Point',
        'line' => ['color' => '#feca57', 'width' => 1.5],
        'hovertemplate' => '%{x|%d %b, %H:%M} • <b>%{y:.1f}°C</b><extra></extra>',
        'yaxis' => 'y',
        'visible' => true,
        'showlegend' => false,
        'metricType' => 'dewpoint'
    ],
    [
        'x' => $labels,
        'y' => $data_media_mobile,
        'type' => 'scatter',
        'mode' => 'lines+markers',
        'name' => 'Media Periodo',
        'line' => ['color' => '#ff6b35', 'width' => 2, 'dash' => 'dot'],
        'marker' => ['size' => 1, 'color' => '#ff6b35'],
        'hovertemplate' => '<b>Media: %{y:.1f}°C</b><extra></extra>',
        'yaxis' => 'y',
        'visible' => true,
        'showlegend' => false,
        'metricType' => 'temperatura'
    ],
    [
        'x' => $labels,
        'y' => $data_media_max,
        'type' => 'scatter',
        'mode' => 'lines',
        'name' => 'Media Max 7gg',
        'line' => ['color' => '#e74c3c', 'width' => 1.5, 'dash' => 'dash'],
        'hovertemplate' => '<b>%{y:.1f}°C</b><br>' . $periodo_7gg . '<extra></extra>',
        'yaxis' => 'y',
        'visible' => true,
        'showlegend' => false,
        'metricType' => 'temperatura'
    ],
    [
        'x' => $labels,
        'y' => $data_media_min,
        'type' => 'scatter',
        'mode' => 'lines',
        'name' => 'Media Min 7gg',
        'line' => ['color' => '#3498db', 'width' => 1.5, 'dash' => 'dash'],
        'hovertemplate' => '<b>%{y:.1f}°C</b><br>' . $periodo_7gg . '<extra></extra>',
        'yaxis' => 'y',
        'visible' => true,
        'showlegend' => false,
        'metricType' => 'temperatura'
    ],
    [
        'x' => $labels,
        'y' => $data_pressione,
        'type' => 'scatter',
        'mode' => 'lines',
        'name' => 'Pressione',
        'line' => ['color' => '#05662eff', 'width' => 1.5],
        'hovertemplate' => '%{x|%d %b, %H:%M} • <b>%{y:.1f} hPa</b><extra></extra>',
        'yaxis' => 'y2',
        'visible' => false,
        'showlegend' => false,
        'metricType' => 'pressione'
    ],
    [
        'x' => $labels,
        'y' => $data_dirvento,
        'type' => 'scatter',
        'mode' => 'markers',
        'name' => 'Dir. Vento',
        'marker' => ['color' => $colori_vento, 'size' => 5, 'line' => ['color' => '#333', 'width' => 0.5]],
        'customdata' => $velocita_vento,
        'hovertemplate' => '%{x|%d %b, %H:%M}<br><b>Dir: %{y:.0f}°</b><br>Vel: %{customdata:.1f} km/h<extra></extra>',
        'yaxis' => 'y2',
        'visible' => false,
        'showlegend' => false,
        'metricType' => 'dirvento'
    ]
];


// OUTPUT
echo json_encode([
    'success' => true,
    'traces' => $traces,
    'metadata' => ['range' => $range, 'start_time' => $start_time, 'end_time' => $end_time, 'data_points' => count($rows), 'y_temp_range' => ['min' => $y_temp_min, 'max' => $y_temp_max], 'y_umid_range' => ['min' => 0, 'max' => 100], 'y_press_range' => ['min' => $y_press_min, 'max' => $y_press_max], 'y_dirvento_range' => ['min' => $y_dirvento_min, 'max' => $y_dirvento_max], 'media_max_7d' => round($media_max_7d, 1), 'media_min_7d' => round($media_min_7d, 1), 'temp_min' => $temp_min_assoluto, 'temp_max' => $temp_max_assoluto, 'pressione_min' => $pressione_min_assoluto, 'pressione_max' => $pressione_max_assoluto, 'first_data_ever' => $first_data_ever, 'last_data_ever' => $last_data_ever, 'wind_color_scale' => [['range' => '0-5 km/h', 'color' => '#b0b0b0', 'label' => 'Calmo'], ['range' => '6-10 km/h', 'color' => '#87ceeb', 'label' => 'Brezza leggera'], ['range' => '11-15 km/h', 'color' => '#4169e1', 'label' => 'Brezza moderata'], ['range' => '16-25 km/h', 'color' => '#00008b', 'label' => 'Brezza tesa'], ['range' => '>25 km/h', 'color' => '#ff00ff', 'label' => 'Vento forte']]],
    'stats' => $stats,
    'legend' => [['label' => 'Temperatura', 'color' => '#000000', 'dashed' => false], ['label' => 'Umidita', 'color' => '#0000FF', 'dashed' => false], ['label' => 'Pressione', 'color' => '#27ae60', 'dashed' => false], ['label' => 'Dir. Vento', 'color' => '#ff8c00', 'dashed' => false], ['label' => 'Dew Point', 'color' => '#feca57', 'dashed' => false], ['label' => 'Media Max 7gg', 'color' => '#e74c3c', 'dashed' => true], ['label' => 'Media Min 7gg', 'color' => '#3498db', 'dashed' => true]],
    'dewpoint_segments' => $dewpoint_segments,
    'chart_info' => $chart_info
], JSON_PRETTY_PRINT);