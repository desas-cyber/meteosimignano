<?php
/**
 * ============================================================================
 * API ENDPOINT - GRAFICI METEO
 * ============================================================================
 * 
 * Parametri GET:
 * - metric: temperatura|umidita|pressione|vento|radianza
 * - range: 6h|12h|24h|48h|7d|30d|today
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/datetime_helper.php';
require_once __DIR__ . '/env_tables_helper.php';
require_once __DIR__ . '/../envelop_lettura.php';

$metric = $_GET['metric'] ?? 'temperatura';
$range = $_GET['range'] ?? '24h';

// Mappa metriche → colonne DB
$metrics_map = [
    'temperatura' => ['col' => 'temperatura_C', 'unit' => '°C', 'title' => 'Temperatura'],
    'umidita' => ['col' => 'umidita_percent', 'unit' => '%', 'title' => 'Umidità Relativa'],
    'pressione' => ['col' => 'pressione_hPa', 'unit' => 'hPa', 'title' => 'Pressione Atmosferica'],
    'vento' => ['col' => 'vento_kmh', 'unit' => 'km/h', 'title' => 'Velocità Vento'],
    'radianza' => ['col' => 'radianza_wm2', 'unit' => 'W/m²', 'title' => 'Radianza Solare']
];

if (!isset($metrics_map[$metric])) {
    echo "<p style='color:red;'>Metrica non valida</p>";
    exit;
}

$config = $metrics_map[$metric];
$table = table_name('dati_meteo_simignano');

// Calcola range temporale
$now = get_now();
switch ($range) {
    case '6h':
        $start_time = date('Y-m-d H:i:s', strtotime($now . ' -6 hours'));
        break;
    case '12h':
        $start_time = date('Y-m-d H:i:s', strtotime($now . ' -12 hours'));
        break;
    case '24h':
        $start_time = date('Y-m-d H:i:s', strtotime($now . ' -24 hours'));
        break;
    case '48h':
        $start_time = date('Y-m-d H:i:s', strtotime($now . ' -48 hours'));
        break;
    case '7d':
        $start_time = date('Y-m-d H:i:s', strtotime($now . ' -7 days'));
        break;
    case 'today':
        $start_time = date('Y-m-d 00:00:00');
        break;
    default:
        $start_time = date('Y-m-d H:i:s', strtotime($now . ' -24 hours'));
}

// Query dati
$sql = "SELECT data_ora, {$config['col']} as value 
        FROM $table 
        WHERE data_ora >= :start_time 
        ORDER BY data_ora ASC";

$stmt = $pdo_lettura->prepare($sql);
$stmt->execute([':start_time' => $start_time]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepara dati per Chart.js
$labels = [];
$values = [];

foreach ($data as $row) {
    $dt = new DateTime($row['data_ora']);
    $labels[] = $dt->format('d/m H:i');
    $values[] = $row['value'] !== null ? (float)$row['value'] : null;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($config['title']) ?> - <?= htmlspecialchars($range) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        
        #chartContainer {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .chart-title {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .stats {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>

<div id="chartContainer">
    <h2 class="chart-title"><?= htmlspecialchars($config['title']) ?> - Ultime <?= htmlspecialchars($range) ?></h2>
    <canvas id="meteoChart"></canvas>
    <div class="stats" id="stats"></div>
</div>

<script>
const data = {
    labels: <?= json_encode($labels) ?>,
    datasets: [{
        label: '<?= htmlspecialchars($config['title']) ?> (<?= htmlspecialchars($config['unit']) ?>)',
        data: <?= json_encode($values) ?>,
        borderColor: 'rgb(75, 192, 192)',
        backgroundColor: 'rgba(75, 192, 192, 0.1)',
        tension: 0.3,
        fill: true
    }]
};

const config = {
    type: 'line',
    data: data,
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            y: {
                beginAtZero: false,
                ticks: {
                    callback: function(value) {
                        return value.toFixed(1) + ' <?= $config['unit'] ?>';
                    }
                }
            }
        }
    }
};

const chart = new Chart(document.getElementById('meteoChart'), config);

// Calcola statistiche
const values = <?= json_encode($values) ?>;
const validValues = values.filter(v => v !== null);

if (validValues.length > 0) {
    const min = Math.min(...validValues);
    const max = Math.max(...validValues);
    const avg = validValues.reduce((a, b) => a + b, 0) / validValues.length;
    
    document.getElementById('stats').innerHTML = 
        `Min: ${min.toFixed(1)} <?= $config['unit'] ?> | 
         Max: ${max.toFixed(1)} <?= $config['unit'] ?> | 
         Media: ${avg.toFixed(1)} <?= $config['unit'] ?> | 
         Campioni: ${validValues.length}`;
}
</script>

</body>
</html>