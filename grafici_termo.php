<?php
/**
 * ============================================================================
 * GRAFICO TERMO-IGROMETRICO AVANZATO - MeteoSimignano
 * ============================================================================
 * 
 * Features:
 * 1. Multi-metrica: Temperatura, Umidita, Dew Point
 * 2. Checkbox per show/hide metriche
 * 3. Range temporale: 24h, 7gg, 1mese + slider estensione
 * 4. Griglia sempre visibile, zero colorato
 * 5. Medie mobili 7gg (max rosso, min blu)
 * 6. Dew point colorato per soglie (nero<10, verde 10-19, arancione 20-23, rosso>24)
 * 7. Doppio asse Y (temp sx ±8°C, umidita dx 0-100%)
 * 8. Routing metric-aware: apre solo metrica specificata in URL
 * 
 * PARAMETRI URL:
 * - grafico=termo (sempre)
 * - metric=temperatura|umidita|dewpoint|all (default: all)
 * - range=24h|7d|30d (default: 24h)
 */

declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================================================
// AUTO-DETECT HELPER FILES
// ============================================================================
$helper_loaded = false;
$possible_paths = [
    __DIR__,                          // Stessa directory
    __DIR__ . '/..',                  // Parent directory
    dirname($_SERVER['DOCUMENT_ROOT']), // Document root parent
    $_SERVER['DOCUMENT_ROOT']          // Document root
];

foreach ($possible_paths as $base_path) {
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
    die("
    <html>
    <head><title>Errore Configurazione</title></head>
    <body style='font-family: Arial; padding: 40px; background: #ffebee;'>
        <h1 style='color: #c62828;'>Errore: File Helper Non Trovati</h1>
        <p>I file <code>env_tables_helper.php</code> e <code>datetime_helper.php</code> non sono stati trovati.</p>
        <p><strong>Percorsi cercati:</strong></p>
        <ul>
            " . implode('', array_map(function($p) {
                return "<li><code>{$p}/env_tables_helper.php</code></li>";
            }, $possible_paths)) . "
        </ul>
        <p><strong>Soluzione:</strong> Assicurati che questi file siano nella stessa directory di <code>grafici_termo.php</code> oppure in una parent directory.</p>
        <p><strong>File corrente:</strong> <code>" . __FILE__ . "</code></p>
        <hr>
        <a href='index.php' style='color: #1976d2;'>Torna alla Home</a>
    </body>
    </html>
    ");
}

// ============================================================================
// PARAMETRI URL
// ============================================================================
$metric_selected = isset($_GET['metric']) ? $_GET['metric'] : 'all';
$range = isset($_GET['range']) ? $_GET['range'] : '24h';

// Nuovi parametri per persistenza checkbox
$check_temperatura = isset($_GET['temp']) ? $_GET['temp'] === '1' : true;
$check_umidita = isset($_GET['umid']) ? $_GET['umid'] === '1' : true;
$check_dewpoint = isset($_GET['dew']) ? $_GET['dew'] === '1' : true;

// Se metric e' specificato, override checkbox
if ($metric_selected !== 'all') {
    $check_temperatura = ($metric_selected === 'temperatura');
    $check_umidita = ($metric_selected === 'umidita');
    $check_dewpoint = ($metric_selected === 'dewpoint');
}

// Validazione
$metrics_valide = ['temperatura', 'umidita', 'dewpoint', 'all'];
$ranges_validi = ['24h', '7d', '30d'];

if (!in_array($metric_selected, $metrics_valide)) {
    $metric_selected = 'all';
}

if (!in_array($range, $ranges_validi)) {
    $range = '24h';
}

// ============================================================================
// METADATA
// ============================================================================
$page_title = "MeteoSimignano - Grafico Termo-Igrometrico";
$page_description = "Andamento temperatura, umidita e punto di rugiada con medie mobili";

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.08, maximum-scale=5.0, user-scalable=yes">
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Chart.js + Plugins -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hammerjs@2.0.8"></script>
    
    <style>
        /* ====================================================================
           RESET & BASE
           ==================================================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #ffffff;
            min-height: 100vh;
            padding: 20px;
        }
        
        /* ====================================================================
           HEADER (IDENTICO A INDEX.PHP)
           ==================================================================== */
        .main-header {
            width: 100%;
            max-width: 1000px;
            text-align: center;
            padding: 10px;
            box-sizing: border-box;
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            position: relative;
            margin: 0 auto 20px;
        }

        .header-content {
            flex-grow: 1; 
            text-align: center; 
            min-width: 0; 
        }

        .header-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #333; 
            min-width: 20px; 
            font-size: 0.7em;
            padding: 0 5px;
        }

        .header-icon:hover {
            color: red;
        }

        .header-icon svg {
            width: 36px;
            height: 36px;
            stroke-width: 2.5; 
        }

        @media (max-width: 599px) {
            .header-icon svg {
                width: 20px;
                height: 20px;
                padding-left: 20px;
                padding-right: 20px;
                stroke-width: 2.5;
            }
        }
        
        .header-icon .icon-label {
            display: block; 
            margin-top: 2px;
        }
        
        .main-title {
            font-size: 6vw;
            white-space: nowrap;
            margin: 0;
        }
        
        @media (min-width: 600px) {
            .main-title {
                font-size: 55px;
            }
        }
        
        .sub-title {
            font-size: 3vw;
            font-weight: normal;
            white-space: nowrap;
            margin: 10px;
        }
        
        @media (min-width: 600px) {
            .sub-title {
                font-size: 30px;
            }
        }
        
        /* ====================================================================
           MAIN CONTAINER
           ==================================================================== */
        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        /* ====================================================================
           CONTROLS AREA
           ==================================================================== */
        .controls-wrapper {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }
        
        @media (max-width: 899px) {
            .controls-wrapper {
                grid-template-columns: 1fr;
            }
        }
        
        /* Checkbox Metriche (sinistra) */
        .metrics-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        .metrics-selector label {
            font-weight: 600;
            color: #495057;
            margin-right: 10px;
        }
        
        .metric-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .metric-checkbox:hover {
            border-color: #2c3e50;
            transform: translateY(-2px);
        }
        
        .metric-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .metric-checkbox span {
            font-size: 14px;
            font-weight: 600;
            color: #495057;
        }
        
        .metric-checkbox.temperatura {
            border-color: #ff6b6b;
        }
        
        .metric-checkbox.umidita {
            border-color: #48dbfb;
        }
        
        .metric-checkbox.dewpoint {
            border-color: #feca57;
        }
        
        /* Range Selector (destra) */
        .range-selector {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .range-selector label {
            font-weight: 600;
            color: #495057;
        }
        
        .range-buttons {
            display: flex;
            gap: 8px;
        }
        
        .range-btn {
            flex: 1;
            padding: 10px;
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        
        .range-btn:hover {
            border-color: #2c3e50;
        }
        
        .range-btn.active {
            background: #2c3e50;
            color: white;
            border-color: #2c3e50;
        }
        
        /* ====================================================================
           TIME RANGE SLIDER
           ==================================================================== */
        .time-slider-container {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }
        
        .time-slider-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
            display: block;
        }
        
        .time-slider-wrapper {
            position: relative;
            padding: 10px 0;
        }
        
        .time-slider {
            width: 100%;
            height: 8px;
            border-radius: 4px;
            background: #dee2e6;
            outline: none;
            -webkit-appearance: none;
        }
        
        .time-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #2c3e50;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .time-slider::-moz-range-thumb {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #2c3e50;
            cursor: pointer;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .slider-values {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 12px;
            color: #6c757d;
        }
        
        /* ====================================================================
           CHART AREA
           ==================================================================== */
        .chart-wrapper {
            padding: 20px;
            position: relative;
        }
        
        .chart-canvas-container {
            position: relative;
            height: 500px;
            width: 100%;
        }
        
        @media (max-width: 599px) {
            .chart-canvas-container {
                height: 350px;
            }
        }
        
        .chart-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 18px;
            color: #999;
            text-align: center;
        }
        
        .chart-error {
            padding: 30px;
            background: #ffebee;
            border: 2px solid #f44336;
            border-radius: 12px;
            color: #c62828;
            text-align: center;
        }
        
        /* ====================================================================
           STATS BOXES
           ==================================================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-top: 2px solid #e9ecef;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-box {
            background: white;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .stat-trend {
            font-size: 14px;
            margin-top: 5px;
            font-weight: 600;
        }
        
        .trend-up { color: #e74c3c; }
        .trend-down { color: #3498db; }
        .trend-stable { color: #27ae60; }
        
        /* ====================================================================
           LEGEND CUSTOM
           ==================================================================== */
        .legend-container {
            padding: 15px 20px;
            background: white;
            border-top: 2px solid #e9ecef;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            align-items: center;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .legend-color {
            width: 20px;
            height: 4px;
            border-radius: 2px;
        }
        
        /* ====================================================================
           ZOOM CONTROLS
           ==================================================================== */
        .zoom-controls {
            position: absolute;
            top: 30px;
            right: 30px;
            display: flex;
            gap: 8px;
            z-index: 10;
        }
        
        .zoom-btn {
            width: 36px;
            height: 36px;
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            color: #495057;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .zoom-btn:hover {
            border-color: #2c3e50;
            color: #2c3e50;
            transform: scale(1.1);
        }
        
        @media (max-width: 599px) {
            .zoom-controls {
                top: 10px;
                right: 10px;
            }
            
            .zoom-btn {
                width: 32px;
                height: 32px;
                font-size: 16px;
            }
        }
    </style>
</head>

<body>
    <!-- ====================================================================
         HEADER (IDENTICO A INDEX.PHP)
         ==================================================================== -->
    <header class="main-header">
    
    <a href="pluvio.html" class="header-icon left-icon" title="Dati Pluviometro CFR Toscana">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2s7 8 7 12a7 7 0 1 1-14 0c0-4 7-12 7-12z"></path>
            <path d="M16 16l-4 4-4-4"></path>
        </svg>
        <span class="icon-label">Pluvio</span>
    </a>
    
    <div class="header-content">
        <h1 class="main-title">MeteoSimignano</h1>
        <h1 class="sub-title">Grafico Termo-Igrometrico</h1>
    </div>
    
    <a href="index.php" class="header-icon right-icon" title="Torna alla Home">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        <span class="icon-label">Home</span>
    </a>
</header>

    <!-- ====================================================================
         MAIN CONTAINER
         ==================================================================== -->
    <div class="container">
        <!-- Controls Area -->
        <div class="controls-wrapper">
            <!-- Checkbox Metriche -->
            <div class="metrics-selector">
                <label>Metriche:</label>
                
                <label class="metric-checkbox temperatura">
                    <input type="checkbox" id="check-temperatura" <?php echo $check_temperatura ? 'checked' : ''; ?>>
                    <span>Temperatura</span>
                </label>
                
                <label class="metric-checkbox umidita">
                    <input type="checkbox" id="check-umidita" <?php echo $check_umidita ? 'checked' : ''; ?>>
                    <span>Umidita</span>
                </label>
                
                <label class="metric-checkbox dewpoint">
                    <input type="checkbox" id="check-dewpoint" <?php echo $check_dewpoint ? 'checked' : ''; ?>>
                    <span>Dew Point</span>
                </label>
            </div>
            
            <!-- Range Selector -->
            <div class="range-selector">
                <label>Range Temporale:</label>
                <div class="range-buttons">
                    <button class="range-btn <?php echo $range === '24h' ? 'active' : ''; ?>" 
                            data-range="24h">24h</button>
                    <button class="range-btn <?php echo $range === '7d' ? 'active' : ''; ?>" 
                            data-range="7d">7 gg</button>
                    <button class="range-btn <?php echo $range === '30d' ? 'active' : ''; ?>" 
                            data-range="30d">1 mese</button>
                </div>
            </div>
        </div>
        
        <!-- Time Slider -->
        <div class="time-slider-container">
            <label class="time-slider-label">Estendi Range Temporale:</label>
            <div class="time-slider-wrapper">
                <input type="range" class="time-slider" id="time-slider-start" 
                       min="0" max="100" value="0">
                <input type="range" class="time-slider" id="time-slider-end" 
                       min="0" max="100" value="100">
            </div>
            <div class="slider-values">
                <span id="slider-start-label">--</span>
                <span id="slider-end-label">--</span>
            </div>
        </div>
        
        <!-- Chart Area -->
        <div class="chart-wrapper">
            <!-- Zoom Controls -->
            <div class="zoom-controls">
                <button class="zoom-btn" id="zoom-in" title="Zoom In">+</button>
                <button class="zoom-btn" id="zoom-out" title="Zoom Out">-</button>
                <button class="zoom-btn" id="zoom-reset" title="Reset">⟲</button>
            </div>
            
            <!-- Chart Canvas -->
            <div class="chart-canvas-container">
                <div class="chart-loading" id="chart-loading">Caricamento dati...</div>
                <canvas id="termo-chart"></canvas>
            </div>
        </div>
        
        <!-- Legend Custom -->
        <div class="legend-container" id="custom-legend"></div>
        
        <!-- Stats Grid -->
        <div class="stats-grid" id="stats-grid"></div>
    </div>

    <!-- ====================================================================
         JAVASCRIPT
         ==================================================================== -->
    <script>
        // ====================================================================
        // CONFIGURAZIONE GLOBALE
        // ====================================================================
        const CONFIG = {
            metric_selected: '<?php echo $metric_selected; ?>',
            range: '<?php echo $range; ?>',
            endpoint: 'api/api_grafico_termo.php',
            colors: {
                temperatura: '#ff6b6b',
                umidita: '#48dbfb',
                dewpoint_cold: '#2c3e50',      // < 10°C
                dewpoint_comfortable: '#27ae60', // 10-19°C
                dewpoint_warm: '#f39c12',       // 20-23°C
                dewpoint_hot: '#e74c3c',        // > 24°C
                media_max: '#e74c3c',
                media_min: '#3498db',
                zero_line: '#e74c3c'
            }
        };
        
        // Istanze globali
        let chartInstance = null;
        let chartData = null;
        let timeRange = { start: 0, end: 100 };
        
        // ====================================================================
        // INIZIALIZZAZIONE
        // ====================================================================
        document.addEventListener('DOMContentLoaded', () => {
            initMetricCheckboxes();
            initRangeButtons();
            initTimeSliders();
            initZoomControls();
            loadChartData();
        });
        
        // ====================================================================
        // METRIC CHECKBOXES
        // ====================================================================
        function initMetricCheckboxes() {
            // Stato checkbox gia impostato da PHP
            // Event listeners per toggle real-time
            document.getElementById('check-temperatura').addEventListener('change', toggleDataset);
            document.getElementById('check-umidita').addEventListener('change', toggleDataset);
            document.getElementById('check-dewpoint').addEventListener('change', toggleDataset);
        }
        
        function toggleDataset(event) {
            if (!chartInstance) return;
            
            const metricId = event.target.id.replace('check-', '');
            const isChecked = event.target.checked;
            
            chartInstance.data.datasets.forEach(dataset => {
                if (dataset.metricType === metricId) {
                    dataset.hidden = !isChecked;
                }
            });
            
            chartInstance.update();
        }
        
        // ====================================================================
        // RANGE BUTTONS
        // ====================================================================
        function initRangeButtons() {
            document.querySelectorAll('.range-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const newRange = btn.dataset.range;
                    const url = new URL(window.location.href);
                    
                    // Imposta nuovo range
                    url.searchParams.set('range', newRange);
                    
                    // Preserva stato checkbox
                    const checkTemp = document.getElementById('check-temperatura').checked;
                    const checkUmid = document.getElementById('check-umidita').checked;
                    const checkDew = document.getElementById('check-dewpoint').checked;
                    
                    url.searchParams.set('temp', checkTemp ? '1' : '0');
                    url.searchParams.set('umid', checkUmid ? '1' : '0');
                    url.searchParams.set('dew', checkDew ? '1' : '0');
                    
                    // Rimuovi metric se presente (conflitto con parametri specifici)
                    url.searchParams.delete('metric');
                    
                    window.location.href = url.toString();
                });
            });
        }
        
        // ====================================================================
        // TIME SLIDERS
        // ====================================================================
        function initTimeSliders() {
            const sliderStart = document.getElementById('time-slider-start');
            const sliderEnd = document.getElementById('time-slider-end');
            
            sliderStart.addEventListener('input', (e) => {
                timeRange.start = parseInt(e.target.value);
                if (timeRange.start > timeRange.end - 5) {
                    timeRange.start = timeRange.end - 5;
                    sliderStart.value = timeRange.start;
                }
                updateChartTimeRange();
            });
            
            sliderEnd.addEventListener('input', (e) => {
                timeRange.end = parseInt(e.target.value);
                if (timeRange.end < timeRange.start + 5) {
                    timeRange.end = timeRange.start + 5;
                    sliderEnd.value = timeRange.end;
                }
                updateChartTimeRange();
            });
        }
        
        function updateChartTimeRange() {
            if (!chartInstance || !chartData) return;
            
            const totalPoints = chartData.labels.length;
            const startIdx = Math.floor(totalPoints * timeRange.start / 100);
            const endIdx = Math.floor(totalPoints * timeRange.end / 100);
            
            // Update labels
            document.getElementById('slider-start-label').textContent = 
                chartData.labels[startIdx] || '--';
            document.getElementById('slider-end-label').textContent = 
                chartData.labels[endIdx - 1] || '--';
            
            // Update chart data
            chartInstance.data.labels = chartData.labels.slice(startIdx, endIdx);
            chartInstance.data.datasets.forEach((dataset, idx) => {
                dataset.data = chartData.datasets[idx].data.slice(startIdx, endIdx);
            });
            
            chartInstance.update('none'); // No animation for smoother slider
        }
        
        // ====================================================================
        // ZOOM CONTROLS
        // ====================================================================
        function initZoomControls() {
            document.getElementById('zoom-in').addEventListener('click', () => {
                if (chartInstance) {
                    chartInstance.zoom(1.2);
                    setTimeout(() => resetXAxis(), 100);
                }
            });
            
            document.getElementById('zoom-out').addEventListener('click', () => {
                if (chartInstance) {
                    chartInstance.zoom(0.8);
                    setTimeout(() => resetXAxis(), 100);
                }
            });
            
            document.getElementById('zoom-reset').addEventListener('click', () => {
                if (chartInstance) {
                    chartInstance.resetZoom();
                    setTimeout(() => resetXAxis(), 100);
                }
            });
        }
        
        // Reset asse X per occupare tutta la larghezza
        function resetXAxis() {
            if (!chartInstance) return;
            
            const xScale = chartInstance.scales.x;
            if (!xScale) return;
            
            // Ottieni min/max dai dati attuali
            const labels = chartInstance.data.labels;
            if (labels.length === 0) return;
            
            // Forza l'asse X a mostrare tutti i dati visibili
            xScale.options.min = labels[0];
            xScale.options.max = labels[labels.length - 1];
            
            chartInstance.update('none');
        }
        
        // ====================================================================
        // LOAD CHART DATA
        // ====================================================================
        let dewpointSegments = null;
        
        async function loadChartData() {
            const loading = document.getElementById('chart-loading');
            const canvas = document.getElementById('termo-chart');
            
            try {
                const response = await fetch(
                    `${CONFIG.endpoint}?range=${CONFIG.range}`
                );
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Errore sconosciuto');
                }
                
                loading.style.display = 'none';
                
                // Store data
                chartData = data.chart_data;
                dewpointSegments = data.dewpoint_segments;
                
                // Apply dewpoint color segments to config
                applyDewpointSegments(data.chart_config);
                
                // Add zero line plugin
                addZeroLinePlugin(data.chart_config);
                
                // Create chart
                createChart(canvas, data.chart_config);
                
                // Render stats
                renderStats(data.stats);
                
                // Render legend
                renderLegend(data.legend);
                
                // Initialize slider labels
                updateSliderLabels();
                
            } catch (error) {
                console.error('Errore caricamento:', error);
                loading.innerHTML = `
                    <div class="chart-error">
                        Errore caricamento dati<br>
                        <small>${error.message}</small>
                    </div>
                `;
            }
        }
        
        function createChart(canvas, config) {
            if (chartInstance) {
                chartInstance.destroy();
            }
            
            // Applica stato hidden iniziale basato su checkbox
            const checkTemp = document.getElementById('check-temperatura').checked;
            const checkUmid = document.getElementById('check-umidita').checked;
            const checkDew = document.getElementById('check-dewpoint').checked;
            
            config.data.datasets.forEach(dataset => {
                if (dataset.metricType === 'temperatura') {
                    dataset.hidden = !checkTemp;
                } else if (dataset.metricType === 'umidita') {
                    dataset.hidden = !checkUmid;
                } else if (dataset.metricType === 'dewpoint') {
                    dataset.hidden = !checkDew;
                }
            });
            
            // PLUGIN CUSTOM: FORZA GRIGLIE ORIZZONTALI E VERTICALI
            const forceGridPlugin = {
                id: 'forceGrid',
                afterDraw: (chart) => {
                    const ctx = chart.ctx;
                    const yScale = chart.scales['y-temp'];
                    const xScale = chart.scales.x;
                    
                    if (!yScale || !xScale) return;
                    
                    ctx.save();
                    ctx.strokeStyle = '#7d7d7d';
                    ctx.lineWidth = 1;
                    
                    // GRIGLIE ORIZZONTALI (asse Y temperatura - ogni 5°C)
                    const minTemp = yScale.min;
                    const maxTemp = yScale.max;
                    
                    for (let temp = Math.floor(minTemp / 5) * 5; temp <= maxTemp; temp += 5) {
                        const y = yScale.getPixelForValue(temp);
                        
                        ctx.beginPath();
                        ctx.moveTo(chart.chartArea.left, y);
                        ctx.lineTo(chart.chartArea.right, y);
                        ctx.stroke();
                    }
                    
                    // GRIGLIE VERTICALI (asse X tempo)
                    if (xScale.ticks && xScale.ticks.length > 0) {
                        xScale.ticks.forEach((tick, index) => {
                            const x = xScale.getPixelForTick(index);
                            
                            ctx.beginPath();
                            ctx.moveTo(x, chart.chartArea.top);
                            ctx.lineTo(x, chart.chartArea.bottom);
                            ctx.stroke();
                        });
                    }
                    
                    ctx.restore();
                }
            };
            
            // Registra plugin
            if (!config.plugins) {
                config.plugins = [];
            }
            config.plugins.push(forceGridPlugin);
            
            // Aggiungi callback zoom/pan per resettare asse X
            if (!config.options.plugins) {
                config.options.plugins = {};
            }
            if (!config.options.plugins.zoom) {
                config.options.plugins.zoom = {};
            }
            
            // Callback dopo zoom
            config.options.plugins.zoom.zoom = config.options.plugins.zoom.zoom || {};
            config.options.plugins.zoom.zoom.onZoomComplete = function({chart}) {
                setTimeout(() => resetXAxis(), 50);
            };
            
            // Callback dopo pan
            config.options.plugins.zoom.pan = config.options.plugins.zoom.pan || {};
            config.options.plugins.zoom.pan.onPanComplete = function({chart}) {
                setTimeout(() => resetXAxis(), 50);
            };
            
            chartInstance = new Chart(canvas, config);
        }
        
        // ====================================================================
        // DEWPOINT SEGMENTED COLORS
        // ====================================================================
        function applyDewpointSegments(config) {
            // Find dewpoint dataset (index 2)
            const dewpointDataset = config.data.datasets[2];
            
            if (!dewpointDataset || !dewpointSegments) return;
            
            // Apply segment colors
            dewpointDataset.segment = {
                borderColor: (ctx) => {
                    if (!dewpointSegments || ctx.p0DataIndex >= dewpointSegments.length) {
                        return CONFIG.colors.dewpoint_cold;
                    }
                    return dewpointSegments[ctx.p0DataIndex].color;
                }
            };
        }
        
        // ====================================================================
        // ZERO LINE PLUGIN
        // ====================================================================
        function addZeroLinePlugin(config) {
            const zeroLinePlugin = {
                id: 'zeroLine',
                afterDatasetsDraw: (chart) => {
                    const ctx = chart.ctx;
                    const yAxis = chart.scales['y-temp'];
                    
                    if (!yAxis) return;
                    
                    const zeroY = yAxis.getPixelForValue(0);
                    
                    // Check if zero is visible in current scale
                    if (zeroY < yAxis.top || zeroY > yAxis.bottom) return;
                    
                    ctx.save();
                    ctx.strokeStyle = CONFIG.colors.zero_line;
                    ctx.lineWidth = 2;
                    ctx.setLineDash([10, 5]);
                    ctx.beginPath();
                    ctx.moveTo(chart.chartArea.left, zeroY);
                    ctx.lineTo(chart.chartArea.right, zeroY);
                    ctx.stroke();
                    ctx.restore();
                }
            };
            
            if (!config.plugins) {
                config.plugins = [];
            }
            config.plugins.push(zeroLinePlugin);
        }
        
        function updateSliderLabels() {
            if (!chartData) return;
            document.getElementById('slider-start-label').textContent = 
                chartData.labels[0] || '--';
            document.getElementById('slider-end-label').textContent = 
                chartData.labels[chartData.labels.length - 1] || '--';
        }
        
        // ====================================================================
        // RENDER STATS
        // ====================================================================
        function renderStats(stats) {
            const container = document.getElementById('stats-grid');
            container.innerHTML = '';
            
            stats.forEach(stat => {
                const box = document.createElement('div');
                box.className = 'stat-box';
                
                let trendHTML = '';
                if (stat.trend) {
                    const trendClass = stat.trend_direction === 'up' ? 'trend-up' : 
                                     stat.trend_direction === 'down' ? 'trend-down' : 'trend-stable';
                    trendHTML = `<div class="stat-trend ${trendClass}" title="Variazione rispetto all'inizio del periodo">${stat.trend}</div>`;
                }
                
                box.innerHTML = `
                    <div class="stat-label">${stat.label}</div>
                    <div class="stat-value">${stat.value}</div>
                    ${trendHTML}
                `;
                
                container.appendChild(box);
            });
        }
        
        // ====================================================================
        // RENDER LEGEND
        // ====================================================================
        function renderLegend(legendItems) {
            const container = document.getElementById('custom-legend');
            container.innerHTML = '';
            
            legendItems.forEach(item => {
                const div = document.createElement('div');
                div.className = 'legend-item';
                div.innerHTML = `
                    <div class="legend-color" style="background: ${item.color}; 
                         height: ${item.dashed ? '2px' : '4px'}; 
                         ${item.dashed ? 'background: repeating-linear-gradient(to right, ' + item.color + ' 0, ' + item.color + ' 5px, transparent 5px, transparent 10px);' : ''}"></div>
                    <span>${item.label}</span>
                `;
                container.appendChild(div);
            });
        }
    </script>
</body>
</html>