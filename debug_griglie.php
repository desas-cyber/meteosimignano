<?php
/**
 * DEBUG A LIVELLI - Trova dove si blocca la configurazione grid
 */

header('Content-Type: text/html; charset=utf-8');

// Includi helper se necessario
$base_paths = [
    __DIR__,
    __DIR__ . '/..',
    dirname($_SERVER['DOCUMENT_ROOT']),
    $_SERVER['DOCUMENT_ROOT']
];

foreach ($base_paths as $base_path) {
    $env_helper = $base_path . '/env_tables_helper.php';
    $datetime_helper = $base_path . '/datetime_helper.php';
    
    if (file_exists($env_helper) && file_exists($datetime_helper)) {
        require_once $env_helper;
        require_once $datetime_helper;
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug A Livelli - Griglie</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #ecf0f1;
            max-width: 1400px;
            margin: 0 auto;
        }
        h1 {
            color: #2c3e50;
        }
        .test-level {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 5px solid #3498db;
        }
        .test-level.passed {
            border-left-color: #2ecc71;
        }
        .test-level.failed {
            border-left-color: #e74c3c;
        }
        .test-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status.pass {
            background: #2ecc71;
            color: white;
        }
        .status.fail {
            background: #e74c3c;
            color: white;
        }
        .code-block {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #34495e;
            color: white;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .highlight {
            background: yellow;
            padding: 2px 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>🔍 Debug A Livelli - Configurazione Griglie Chart.js</h1>
    
    <div class="info">
        <strong>Obiettivo:</strong> Trovare in quale punto del flusso si perde o si corrompe la configurazione delle griglie.
    </div>

    <?php
    // ========================================================================
    // LIVELLO 1: Test API - Risposta Raw
    // ========================================================================
    echo '<div class="test-level" id="level1">';
    echo '<div class="test-title">LIVELLO 1: API Raw Response</div>';
    
    $api_response = @file_get_contents('api/api_grafici_termo.php?range=24h');
    
    if ($api_response === false) {
        echo '<div class="error">❌ Impossibile raggiungere API</div>';
        echo '<span class="status fail">FAIL</span>';
    } else {
        echo '<span class="status pass">PASS</span>';
        echo '<div class="info">✅ API raggiungibile, risposta ricevuta (' . strlen($api_response) . ' bytes)</div>';
        
        $api_data = json_decode($api_response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo '<div class="error">❌ JSON non valido: ' . json_last_error_msg() . '</div>';
        } else {
            echo '<div class="info">✅ JSON valido</div>';
            
            // Estrai grid config
            $grid_x = $api_data['chart_config']['options']['scales']['x']['grid'] ?? null;
            $grid_y_temp = $api_data['chart_config']['options']['scales']['y-temp']['grid'] ?? null;
            $grid_y_umid = $api_data['chart_config']['options']['scales']['y-umidita']['grid'] ?? null;
            
            echo '<table>';
            echo '<tr><th>Parametro</th><th>Asse X</th><th>Asse Y-Temp</th><th>Asse Y-Umid</th></tr>';
            
            echo '<tr>';
            echo '<td><strong>display</strong></td>';
            echo '<td>' . ($grid_x['display'] ?? 'N/A') . '</td>';
            echo '<td>' . ($grid_y_temp['display'] ?? 'N/A') . '</td>';
            echo '<td>' . ($grid_y_umid['display'] ?? 'N/A') . '</td>';
            echo '</tr>';
            
            echo '<tr>';
            echo '<td><strong>color</strong></td>';
            echo '<td>' . ($grid_x['color'] ?? 'N/A') . '</td>';
            echo '<td>' . ($grid_y_temp['color'] ?? 'N/A') . '</td>';
            echo '<td>' . ($grid_y_umid['color'] ?? 'N/A') . '</td>';
            echo '</tr>';
            
            echo '<tr>';
            echo '<td><strong>lineWidth</strong></td>';
            echo '<td>' . ($grid_x['lineWidth'] ?? 'N/A') . '</td>';
            echo '<td>' . ($grid_y_temp['lineWidth'] ?? 'N/A') . '</td>';
            echo '<td>' . ($grid_y_umid['lineWidth'] ?? 'N/A') . '</td>';
            echo '</tr>';
            
            echo '<tr>';
            echo '<td><strong>drawOnChartArea</strong></td>';
            echo '<td>' . ($grid_x['drawOnChartArea'] ?? 'N/A') . '</td>';
            echo '<td class="highlight">' . ($grid_y_temp['drawOnChartArea'] ?? 'N/A') . '</td>';
            echo '<td>' . ($grid_y_umid['drawOnChartArea'] ?? 'N/A') . '</td>';
            echo '</tr>';
            
            echo '</table>';
        }
    }
    
    echo '</div>';
    
    // ========================================================================
    // LIVELLO 2: Test JavaScript - Parsing JSON
    // ========================================================================
    echo '<div class="test-level" id="level2">';
    echo '<div class="test-title">LIVELLO 2: JavaScript Parsing</div>';
    echo '<div id="js-test-result">In attesa test JavaScript...</div>';
    echo '</div>';
    
    // ========================================================================
    // LIVELLO 3: Test Chart.js - Accetta Configurazione
    // ========================================================================
    echo '<div class="test-level" id="level3">';
    echo '<div class="test-title">LIVELLO 3: Chart.js Config Acceptance</div>';
    echo '<div id="chartjs-test-result">In attesa creazione chart...</div>';
    echo '<canvas id="test-canvas" width="600" height="200" style="border: 2px solid #e74c3c; margin-top: 20px;"></canvas>';
    echo '</div>';
    
    // ========================================================================
    // LIVELLO 4: Test Rendering - Griglie Effettivamente Disegnate
    // ========================================================================
    echo '<div class="test-level" id="level4">';
    echo '<div class="test-title">LIVELLO 4: Grid Rendering Test</div>';
    echo '<div class="info">Guarda il canvas sopra. Dovresti vedere linee nere orizzontali e verticali.</div>';
    echo '<div id="rendering-result"></div>';
    echo '</div>';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <script>
        // ====================================================================
        // LIVELLO 2: Test JavaScript Parsing
        // ====================================================================
        async function testLevel2() {
            const resultDiv = document.getElementById('js-test-result');
            
            try {
                const response = await fetch('api/api_grafici_termo.php?range=24h');
                const data = await response.json();
                
                resultDiv.innerHTML = '<span class="status pass">PASS</span> ✅ JSON parsato correttamente da JavaScript';
                
                // Verifica presenza grid config
                const gridYTemp = data.chart_config.options.scales['y-temp'].grid;
                
                if (gridYTemp) {
                    resultDiv.innerHTML += '<div class="info">✅ Oggetto grid trovato per Y-Temp</div>';
                    resultDiv.innerHTML += '<div class="code-block">' + JSON.stringify(gridYTemp, null, 2) + '</div>';
                } else {
                    resultDiv.innerHTML += '<div class="error">❌ Oggetto grid NON trovato!</div>';
                }
                
                // Passa al livello 3
                testLevel3(data);
                
            } catch (error) {
                resultDiv.innerHTML = '<span class="status fail">FAIL</span> ❌ ' + error.message;
            }
        }
        
        // ====================================================================
        // LIVELLO 3: Test Chart.js Config
        // ====================================================================
        function testLevel3(apiData) {
            const resultDiv = document.getElementById('chartjs-test-result');
            const canvas = document.getElementById('test-canvas');
            
            try {
                // Crea chart con config API
                const chart = new Chart(canvas, apiData.chart_config);
                
                resultDiv.innerHTML = '<span class="status pass">PASS</span> ✅ Chart.js ha accettato la configurazione';
                
                // Verifica se grid è settata nel chart object
                const chartGridConfig = chart.options.scales['y-temp'].grid;
                
                resultDiv.innerHTML += '<div class="info">Configurazione grid nel chart object:</div>';
                resultDiv.innerHTML += '<div class="code-block">' + JSON.stringify(chartGridConfig, null, 2) + '</div>';
                
                // Controlla se drawOnChartArea è presente
                if (chartGridConfig.drawOnChartArea === true) {
                    resultDiv.innerHTML += '<div class="info">✅ drawOnChartArea = true</div>';
                } else {
                    resultDiv.innerHTML += '<div class="warning">⚠️ drawOnChartArea = ' + chartGridConfig.drawOnChartArea + '</div>';
                }
                
                // Passa al livello 4
                setTimeout(() => testLevel4(chart), 1000);
                
            } catch (error) {
                resultDiv.innerHTML = '<span class="status fail">FAIL</span> ❌ ' + error.message;
                console.error('Chart.js error:', error);
            }
        }
        
        // ====================================================================
        // LIVELLO 4: Test Rendering
        // ====================================================================
        function testLevel4(chart) {
            const resultDiv = document.getElementById('rendering-result');
            
            // Ottieni canvas context
            const canvas = document.getElementById('test-canvas');
            const ctx = canvas.getContext('2d');
            
            // Ottieni image data per controllare se ci sono linee nere
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const pixels = imageData.data;
            
            let blackPixels = 0;
            
            // Conta pixel neri (o quasi neri)
            for (let i = 0; i < pixels.length; i += 4) {
                const r = pixels[i];
                const g = pixels[i + 1];
                const b = pixels[i + 2];
                
                // Pixel nero/grigio scuro
                if (r < 50 && g < 50 && b < 50) {
                    blackPixels++;
                }
            }
            
            const totalPixels = canvas.width * canvas.height;
            const blackPercentage = (blackPixels / totalPixels * 100).toFixed(2);
            
            resultDiv.innerHTML = '<div class="info">';
            resultDiv.innerHTML += '<strong>Analisi Canvas:</strong><br>';
            resultDiv.innerHTML += 'Pixel neri/scuri: ' + blackPixels + ' (' + blackPercentage + '%)<br>';
            
            if (blackPixels > 1000) {
                resultDiv.innerHTML += '<span class="status pass">PASS</span> ✅ Le griglie sembrano essere renderizzate (molti pixel neri rilevati)';
            } else {
                resultDiv.innerHTML += '<span class="status fail">FAIL</span> ❌ Pochi pixel neri rilevati - le griglie potrebbero non essere visibili';
            }
            
            resultDiv.innerHTML += '</div>';
            
            // Log finale
            console.log('Chart object:', chart);
            console.log('Grid config:', chart.options.scales['y-temp'].grid);
        }
        
        // Avvia test
        document.addEventListener('DOMContentLoaded', testLevel2);
    </script>
</body>
</html>