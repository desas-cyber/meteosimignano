<?php
/**
 * GRAFICO TERMO-IGROMETRICO - VERSIONE PLOTLY.JS
 */

// Parametri URL
$range = isset($_GET['range']) ? $_GET['range'] : '24h';  // Default: 24h
$ranges_validi = ['24h', '7d', '30d'];
if (!in_array($range, $ranges_validi)) {
    $range = '24h';  // Fallback a 24h
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MeteoSimignano - Grafico Termo-Igrometrico</title>
    
    <!-- Plotly CDN con fallback -->
    <script>
        let plotlyLoadMethod = 'CDN';
        let plotlyLoadStart = performance.now();
        
        function loadPlotlyFallback() {
            console.warn('CDN fallito, carico da locale...');
            plotlyLoadMethod = 'Fallback Locale';
            const script = document.createElement('script');
            script.src = '/js/plotly-2.27.0.min.js';
            script.onload = plotlyLoaded;
            script.onerror = () => console.error('Plotly non disponibile');
            document.head.appendChild(script);
        }
        
        function plotlyLoaded() {
            const loadTime = (performance.now() - plotlyLoadStart).toFixed(0);
            console.log(`Plotly caricato via ${plotlyLoadMethod} in ${loadTime}ms`);
        }
    </script>
    <script 
        src="https://cdn.plot.ly/plotly-2.27.0.min.js" 
        onerror="loadPlotlyFallback()"
        onload="plotlyLoaded()">
    </script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #ffffff;
            color: #2c3e50;
        }
        
        /* Header */
        .main-header {
            background: white;
            color: #333;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header-content {
            flex: 1;
            text-align: center;
        }
        
        .main-title {
            font-size: 28px;
            font-weight: bold;
            margin: 0;
            color: #2c3e50;
        }
        
        .sub-title {
            font-size: 14px;
            opacity: 0.7;
            margin: 5px 0 0 0;
            font-weight: normal;
            color: #555;
        }
        
        /* Header Icons */
        .header-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            color: #333;
            text-decoration: none;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
            min-width: 60px;
        }
        
        .header-icon:hover {
            color: red;
            background: rgba(255,0,0,0.05);
        }
        
        .header-icon svg {
            width: 24px;
            height: 24px;
        }
        
        .icon-label {
            font-size: 11px;
            font-weight: 500;
        }
        
        .left-icon {
            margin-right: auto;
        }
        
        .right-icon {
            margin-left: auto;
        }
        
        /* Container */
        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Barra Controlli Unificata */
        .unified-toolbar {
            display: flex;
            justify-content: center;  /* ✅ CENTRATO */
            align-items: center;
            padding: 12px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e1e4e8;
            margin: 0 auto 20px auto;  /* ✅ CENTRATO con margin auto */
            gap: 10px;
            max-width: fit-content;  /* ✅ Larghezza contenuto */
        }
        
        /* Desktop: tutto in una riga */
        .toolbar-row-1,
        .toolbar-row-2 {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .toolbar-separator-mobile {
            display: none;
        }
        
        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            background: white;
            border-radius: 6px;
            border: 1px solid #d1d5da;
        }
        
        .toolbar-separator {
            width: 1px;
            height: 36px;
            background: #d1d5da;
        }
        
        .toolbar-label {
            font-size: 10px;
            font-weight: 600;
            color: #586069;
            margin-right: 2px;
            white-space: nowrap;
        }
        
        .toolbar-btn {
            min-width: 35px;
            height: 32px;
            padding: 0 8px;
            border: 1px solid #d1d5da;
            border-radius: 4px;
            background: white;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .toolbar-btn:hover {
            border-color: #0366d6;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        }
        
        /* Preset Range - Grigio */
        .toolbar-btn-preset {
            color: #586069;
        }
        
        .toolbar-btn-preset.active {
            background: #6c757d;
            color: white;
            border-color: #5a6268;
        }
        
        .toolbar-btn-preset:hover {
            background: #e9ecef;
        }
        
        /* Plotly Tools - Grigio */
        .toolbar-btn-tool {
            color: #586069;
            font-size: 14px;
            min-width: 32px;
        }
        
        .toolbar-btn-tool:hover {
            background: #f6f8fa;
        }
        
        /* Zoom X - Grigio */
        .toolbar-btn-x {
            color: #586069;
        }
        
        .toolbar-btn-x:hover {
            background: #f6f8fa;
        }
        
        /* Zoom Y - Rosso */
        .toolbar-btn-y {
            color: #e74c3c;
        }
        
        .toolbar-btn-y:hover {
            background: #ffe6e6;
            border-color: #e74c3c;
        }
        
        /* Chart Wrapper */
        .chart-wrapper {
            background: white;
            border-radius: 12px;
            padding: 10px;  /* ✅ Ridotto da 20px a 10px */
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* Subtitle assi */
        .chart-subtitle {
            text-align: center;
            font-size: 13px;
            color: #586069;
            margin: 3px 0 5px 0;  /* ✅ Ridotto: 3px top, 5px bottom */
            font-weight: 500;
            line-height: 1.2;  /* ✅ Interlinea stretta */
        }
        
        .chart-subtitle strong {
            color: #2c3e50;
        }
        
        #termo-chart {
            width: 100%;
            height: 600px;  /* ✅ Desktop +100px (era 500px) */
        }
        
        /* Legenda + Nota Box */
        .legend-info-box {
            background: #f8f9fa;
            padding: 12px 16px;  /* ✅ Ridotto da 15/20 a 12/16 */
            border-radius: 8px;
            border: 1px solid #e1e4e8;
            margin: 8px auto 0 auto;  /* ✅ Ridotto da 15px a 8px */
            max-width: fit-content;
        }
        
        .legend-items {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;  /* ✅ CENTRATO */
            gap: 20px;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e1e4e8;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #24292e;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        
        .legend-item:hover {
            opacity: 0.7;
        }
        
        .legend-item-hidden {
            opacity: 0.4;
        }
        
        .legend-item-hidden span {
            text-decoration: line-through;
        }
        
        .legend-line {
            width: 30px;
            height: 3px;
            border-radius: 2px;
        }
        
        .legend-line.dashed {
            background-image: linear-gradient(to right, currentColor 50%, transparent 50%);
            background-size: 8px 3px;
            background-repeat: repeat-x;
        }
        
        .chart-note {
            font-size: 13px;
            color: #586069;
            line-height: 1.5;
            text-align: center;  /* ✅ CENTRATO */
        }
        
        .chart-note strong {
            color: #24292e;
            font-weight: 600;
        }
        
        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            font-size: 18px;
            color: #7f8c8d;
        }
        
        .loading::after {
            content: '...';
            animation: dots 1.5s steps(4, end) infinite;
        }
        
        @keyframes dots {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60%, 100% { content: '...'; }
        }
        
        /* Error */
        .error {
            background: #e74c3c;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        
        /* Responsive */
        @media (max-width: 768px) and (orientation: portrait) {
            /* Header */
            .main-title {
                font-size: 14px;
            }
            
            .sub-title {
                font-size: 9px;
            }
            
            .header-icon {
                min-width: 50px;
                padding: 6px;
            }
            
            .header-icon svg {
                width: 20px;
                height: 20px;
            }
            
            .icon-label {
                font-size: 10px;
            }
            
            .container {
                padding: 10px;
            }
            
            /* Toolbar: 2 righe centrato */
            .unified-toolbar {
                flex-direction: column;
                align-items: center;  /* ✅ CENTRATO */
                padding: 8px;
                gap: 6px;
                width: fit-content;  /* ✅ Larghezza contenuto */
                margin: 0 auto 15px auto;  /* ✅ CENTRATO margin */
            }
            
            .toolbar-row-1,
            .toolbar-row-2 {
                justify-content: center;  /* ✅ CENTRATO */
                gap: 6px;
            }
            
            .toolbar-separator-mobile {
                display: block;
                width: 100%;
                height: 1px;
                background: #d1d5da;
            }
            
            .toolbar-group {
                padding: 4px 8px;
                gap: 4px;
            }
            
            .toolbar-label {
                font-size: 7px;
            }
            
            .toolbar-btn {
                min-width: 24px;
                height: 24px;
                font-size: 10px;
                padding: 0 6px;
            }
            
            .toolbar-btn-tool {
                font-size: 12px;
                min-width: 24px;
            }
            
            /* Grafico: DOPPIA altezza */
            #termo-chart {
                height: 600px !important;
            }
            
            /* Subtitle compatta */
            .chart-subtitle {
                font-size: 11px;
                margin: 2px 0 4px 0;  /* ✅ Ridotto */
            }
            
            /* Legenda */
            .legend-item {
                font-size: 10px;
                gap: 6px;
            }
            
            .legend-line {
                width: 25px;
            }
            
            .chart-note {
                font-size: 9px;
            }
        }
        
        
        /* LANDSCAPE Mobile */
        @media (max-width: 900px) and (orientation: landscape) {
            /* Header compatto */
            .main-title {
                font-size: 12px;
            }
            
            .sub-title {
                font-size: 10px;
            }
            
            .header-icon {
                padding: 4px;
                min-width: 45px;
            }
            
            .header-icon svg {
                width: 18px;
                height: 18px;
            }
            
            .icon-label {
                font-size: 9px;
            }
            
            .container {
                padding: 8px;
            }
            
            /* Toolbar: 1 riga ultra compatta centrata */
            .unified-toolbar {
                flex-direction: row;
                justify-content: center;  /* ✅ CENTRATO */
                gap: 4px;
                padding: 6px 10px;
                height: 45px;
                width: fit-content;  /* ✅ Larghezza contenuto */
                margin: 0 auto 10px auto;  /* ✅ CENTRATO margin */
            }
            
            .toolbar-row-1,
            .toolbar-row-2 {
                gap: 4px;
            }
            
            .toolbar-separator-mobile {
                display: none;
            }
            
            .toolbar-group {
                padding: 4px 6px;
                gap: 2px;
            }
            
            .toolbar-btn {
                min-width: 22px;
                height: 22px;
                font-size: 9px;
                padding: 0 3px;
            }
            
            .toolbar-btn-tool {
                font-size: 11px;
                min-width: 22px;
            }
            
            .toolbar-label {
                font-size: 7px;
                margin-right: 1px;
            }
            
            .toolbar-separator {
                height: 30px;
            }
            
            /* Grafico larghezza piena, altezza ridotta */
            .chart-wrapper {
                padding: 10px;
            }
            
            #termo-chart {
                height: 220px !important;
            }
            
            /* Subtitle compatta */
            .chart-subtitle {
                font-size: 10px;
                margin: 2px 0 3px 0;  /* ✅ Ridotto */
            }
            
            /* Legenda + Nota compatta */
            .legend-info-box {
                padding: 8px 10px;
                margin-top: 8px;
            }
            
            .legend-items {
                margin-bottom: 6px;
                padding-bottom: 6px;
                gap: 12px;
            }
            
            .legend-item {
                font-size: 9px;
                gap: 4px;
            }
            
            .legend-line {
                width: 20px;
                height: 2px;
            }
            
            .chart-note {
                font-size: 8px;
                line-height: 1.3;
            }
        }
        
        @media (max-width: 480px) {
            .main-title {
                font-size: 18px;
            }
            
            .sub-title {
                font-size: 10px;
            }
            
            .range-selector {
                gap: 8px;
            }
            
            .range-btn {
                padding: 8px 16px;
                font-size: 13px;
            }
            
            #termo-chart {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
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
        <h2 class="sub-title">43°17'32.5"N 11°10'01.49"E @ 418m slm</h2>
    </div>
    
    <a href="index.php" class="header-icon right-icon" title="Home">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        <span class="icon-label">Home</span>
    </a>
    
</header>
    
    <!-- Container -->
    <div class="container">
        <!-- Barra Controlli Unificata -->
        <div class="unified-toolbar">
            <div class="toolbar-row-1">
                <!-- Sezione 1: Preset Range -->
                <div class="toolbar-group">
                    <button class="toolbar-btn toolbar-btn-preset <?php echo $range === '24h' ? 'active' : ''; ?>" data-range="24h">24h</button>
                    <button class="toolbar-btn toolbar-btn-preset <?php echo $range === '7d' ? 'active' : ''; ?>" data-range="7d">7d</button>
                    <button class="toolbar-btn toolbar-btn-preset <?php echo $range === '30d' ? 'active' : ''; ?>" data-range="30d">30d</button>
                </div>
                
                <div class="toolbar-separator"></div>
                
                <!-- Sezione 2: Plotly Tools -->
                <div class="toolbar-group">
                    <button class="toolbar-btn toolbar-btn-tool" id="tool-home" title="Reset Axes">🏠</button>
                    <button class="toolbar-btn toolbar-btn-tool" id="tool-pan" title="Pan">✋</button>
                    <button class="toolbar-btn toolbar-btn-tool" id="tool-download" title="Download PNG">📷</button>
                </div>
            </div>
            
            <div class="toolbar-separator toolbar-separator-mobile"></div>
            
            <div class="toolbar-row-2">
                <!-- Sezione 3: Zoom X -->
                <div class="toolbar-group">
                    <span class="toolbar-label">Zoom X</span>
                    <button class="toolbar-btn toolbar-btn-x" id="x-zoom-in" title="Zoom In X">+X</button>
                    <button class="toolbar-btn toolbar-btn-x" id="x-zoom-out" title="Zoom Out X">−X</button>
                    <button class="toolbar-btn toolbar-btn-x" id="x-zoom-reset" title="Reset X">⟲X</button>
                </div>
                
                <div class="toolbar-separator"></div>
                
                <!-- Sezione 4: Zoom Y -->
                <div class="toolbar-group">
                    <span class="toolbar-label">Zoom Y</span>
                    <button class="toolbar-btn toolbar-btn-y" id="y-zoom-in" title="Zoom In Y">↑Y</button>
                    <button class="toolbar-btn toolbar-btn-y" id="y-zoom-out" title="Zoom Out Y">↓Y</button>
                    <button class="toolbar-btn toolbar-btn-y" id="y-zoom-reset" title="Reset Y">⟲Y</button>
                </div>
            </div>
        </div>
        
        <!-- Subtitle assi -->
        <div class="chart-subtitle">
            <strong>Temperatura (°C)</strong> • <strong>Umidità (%)</strong>
        </div>
        
        <!-- Chart piena larghezza -->
        <div class="chart-wrapper">
            <div id="loading" class="loading">Caricamento dati</div>
            <div id="termo-chart"></div>
        </div>
        
        <!-- Legenda + Nota -->
        <div class="legend-info-box">
            <div id="chart-legend" class="legend-items"></div>
            <div id="chart-info" class="chart-note"></div>
        </div>
    </div>
    
    <script>
        // ====================================================================
        // CONFIGURAZIONE
        // ====================================================================
        const CONFIG = {
            range: '<?php echo $range; ?>',
            endpoint: 'api/api_grafico_termo_plotly.php'
        };
        
        // ====================================================================
        // RELOAD CHART CON RANGE CUSTOM
        // ====================================================================
        let reloadTimeout;
        
        function reloadChartWithCustomRange(startDate, endDate) {
            // Debounce per evitare troppe chiamate
            clearTimeout(reloadTimeout);
            reloadTimeout = setTimeout(async () => {
                try {
                    const start = new Date(startDate).toISOString().slice(0, 19).replace('T', ' ');
                    const end = new Date(endDate).toISOString().slice(0, 19).replace('T', ' ');
                    
                    const response = await fetch(`${CONFIG.endpoint}?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`);
                    
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        console.error('Risposta non-JSON:', text.substring(0, 500));
                        throw new Error('API non ha restituito JSON');
                    }
                    
                    const data = await response.json();
                    if (!data.success) throw new Error(data.error || 'Errore caricamento');
                    
                    // Ricrea grafico con nuovi dati
                    createChart(data);
                    
                } catch (error) {
                    console.error('Errore reload custom range:', error);
                }
            }, 500); // Aspetta 500ms dopo ultimo cambio
        }
        
        // ====================================================================
        // ZOOM CONTROLS (X e Y dalla toolbar)
        // ====================================================================
        function initZoomControls(metadata) {
            const chartDiv = document.getElementById('termo-chart');
            
            // Zoom X (Temporale) - SOLO RELAYOUT VISIVO
            document.getElementById('x-zoom-in').addEventListener('click', () => {
                const currentRange = chartDiv.layout.xaxis.range;
                if (!currentRange) return;
                
                const start = new Date(currentRange[0]).getTime();
                const end = new Date(currentRange[1]).getTime();
                const center = (start + end) / 2;
                const range = end - start;
                const newRange = range * 0.5; // Zoom in 50%
                
                // Limita a range totale DB
                const totalStart = new Date(metadata.first_data_ever).getTime();
                const totalEnd = new Date(metadata.last_data_ever).getTime();
                
                let newStart = Math.max(totalStart, center - newRange / 2);
                let newEnd = Math.min(totalEnd, center + newRange / 2);
                
                // ✅ SOLO relayout visivo (usa dati già caricati)
                Plotly.relayout(chartDiv, {
                    'xaxis.range': [new Date(newStart), new Date(newEnd)]
                }).then(() => {
                    console.log('Zoom IN applicato:', new Date(newStart), new Date(newEnd));
                });
                
                deactivatePresetButtons();
            });
            
            document.getElementById('x-zoom-out').addEventListener('click', () => {
                const currentRange = chartDiv.layout.xaxis.range;
                if (!currentRange) return;
                
                const start = new Date(currentRange[0]).getTime();
                const end = new Date(currentRange[1]).getTime();
                const center = (start + end) / 2;
                const range = end - start;
                const newRange = range * 2; // Zoom out 2x
                
                // Limita a range totale DB
                const totalStart = new Date(metadata.first_data_ever).getTime();
                const totalEnd = new Date(metadata.last_data_ever).getTime();
                
                let newStart = Math.max(totalStart, center - newRange / 2);
                let newEnd = Math.min(totalEnd, center + newRange / 2);
                
                // ⚠️ Se zoom out va oltre dati caricati, reload API
                const loadedStart = new Date(metadata.start_time).getTime();
                const loadedEnd = new Date(metadata.end_time).getTime();
                
                if (newStart < loadedStart || newEnd > loadedEnd) {
                    // Servono più dati → reload API
                    console.log('Zoom OUT richiede più dati, reload API...');
                    reloadChartWithCustomRange(new Date(newStart), new Date(newEnd));
                } else {
                    // Dati già caricati → solo relayout visivo
                    Plotly.relayout(chartDiv, {
                        'xaxis.range': [new Date(newStart), new Date(newEnd)]
                    }).then(() => {
                        console.log('Zoom OUT applicato:', new Date(newStart), new Date(newEnd));
                    });
                }
                
                deactivatePresetButtons();
            });
            
            document.getElementById('x-zoom-reset').addEventListener('click', () => {
                window.location.href = '?range=<?php echo $range; ?>';
            });
            
            // Zoom Y (Temperatura)
            let currentYMin = metadata.y_temp_range.min;
            let currentYMax = metadata.y_temp_range.max;
            
            document.getElementById('y-zoom-in').addEventListener('click', () => {
                const range = currentYMax - currentYMin;
                const center = (currentYMax + currentYMin) / 2;
                const newRange = range * 0.7; // Zoom in 30%
                
                currentYMin = Math.max(-20, Math.round(center - newRange / 2));
                currentYMax = Math.min(50, Math.round(center + newRange / 2));
                
                Plotly.relayout(chartDiv, {
                    'yaxis.range': [currentYMin, currentYMax]
                });
                deactivatePresetButtons();
            });
            
            document.getElementById('y-zoom-out').addEventListener('click', () => {
                const range = currentYMax - currentYMin;
                const center = (currentYMax + currentYMin) / 2;
                const newRange = range * 1.4; // Zoom out 40%
                
                currentYMin = Math.max(-20, Math.round(center - newRange / 2));
                currentYMax = Math.min(50, Math.round(center + newRange / 2));
                
                Plotly.relayout(chartDiv, {
                    'yaxis.range': [currentYMin, currentYMax]
                });
                deactivatePresetButtons();
            });
            
            document.getElementById('y-zoom-reset').addEventListener('click', () => {
                currentYMin = metadata.y_temp_range.min;
                currentYMax = metadata.y_temp_range.max;
                
                Plotly.relayout(chartDiv, {
                    'yaxis.range': [currentYMin, currentYMax]
                });
            });
        }
        
        // ====================================================================
        // GESTIONE MODALITÀ PRESET vs CUSTOM
        // ====================================================================
        function deactivatePresetButtons() {
            document.querySelectorAll('.range-btn').forEach(btn => {
                btn.classList.remove('active');
            });
        }
        
        function activatePresetButton(range) {
            document.querySelectorAll('.range-btn').forEach(btn => {
                if (btn.dataset.range === range) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        }
        
        // ====================================================================
        // RIPRISTINO STATO LEGEND DA URL
        // ====================================================================
        function restoreLegendState() {
            const urlParams = new URLSearchParams(window.location.search);
            const visibleParam = urlParams.get('visible');
            
            if (!visibleParam) return;
            
            const visibleNames = visibleParam.split(',');
            const chartDiv = document.getElementById('termo-chart');
            
            if (!chartDiv || !chartDiv.data) return;
            
            // Nascondi traces non visibili
            const visible = chartDiv.data.map(trace => {
                const name = trace.name || '';
                const legendgroup = trace.legendgroup || '';
                
                if (visibleNames.includes(name)) {
                    return true;
                }
                
                if ((legendgroup === 'dewpoint' || legendgroup === 'dewpoint-legend') && 
                    visibleNames.includes('Dew Point')) {
                    return true;
                }
                
                return false;
            });
            
            Plotly.restyle(chartDiv, { visible: visible });
            
            // Aggiorna stato visivo legend items
            setTimeout(() => {
                document.querySelectorAll('.legend-item').forEach(item => {
                    const traceName = item.dataset.trace;
                    if (!visibleNames.includes(traceName)) {
                        item.classList.add('legend-item-hidden');
                    }
                });
            }, 100);
        }
        
        // ====================================================================
        // LEGENDA + NOTA
        // ====================================================================
        function showLegend() {
            const legendDiv = document.getElementById('chart-legend');
            const chartDiv = document.getElementById('termo-chart');
            
            // Testi abbreviati mobile
            const isMobile = window.innerWidth <= 768 || (window.innerWidth <= 900 && window.innerHeight < window.innerWidth);
            
            const items = [
                { 
                    label: isMobile ? 'Temp' : 'Temperatura', 
                    name: 'Temperatura', 
                    color: '#000000', 
                    dashed: false 
                },
                { 
                    label: isMobile ? 'Umid' : 'Umidità', 
                    name: 'Umidita', 
                    color: '#0000FF', 
                    dashed: false 
                },
                { 
                    label: isMobile ? 'DP' : 'Dew Point', 
                    name: 'Dew Point', 
                    color: 'gradient', 
                    dashed: false 
                },
                { 
                    label: isMobile ? 'Media' : 'Media Periodo', 
                    name: 'Media Periodo', 
                    color: '#ff6b35', 
                    dashed: 'dot',
                    marker: { size: 1 }
                    
                },


                { 
                    label: isMobile ? 'Max' : 'Media Max 7gg', 
                    name: 'Media Max 7gg', 
                    color: '#e74c3c', 
                    dashed: true 
                },
                { 
                    label: isMobile ? 'Min' : 'Media Min 7gg', 
                    name: 'Media Min 7gg', 
                    color: '#3498db', 
                    dashed: true 
                }
            ];
            
            legendDiv.innerHTML = items.map(item => {
                let lineStyle = '';
                let lineClass = 'legend-line';
                
                if (item.color === 'gradient') {
                    lineStyle = 'background: linear-gradient(to right, #808080, #27ae60, #f39c12, #e74c3c);';
                } else if (item.dashed === 'dot') {
                    // Linea punteggiata (dot) con punti
                    lineStyle = `background: repeating-linear-gradient(to right, ${item.color} 0px, ${item.color} 3px, transparent 3px, transparent 6px);`;
                } else if (item.dashed) {
                    // Linea tratteggiata normale
                    lineStyle = `background-color: ${item.color}; color: ${item.color};`;
                    lineClass = 'legend-line dashed';
                } else {
                    lineStyle = `background-color: ${item.color};`;
                }
                
                // Aggiungi marker centrale se ha markers
                const markerHtml = item.markers ? 
                    `<div style="position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); width:4px; height:4px; background:${item.color}; border-radius:50%;"></div>` : '';
                
                return `
                    <div class="legend-item" data-trace="${item.name}" style="cursor: pointer;">
                        <div class="${lineClass}" style="position:relative; ${lineStyle}">${markerHtml}</div>
                        <span>${item.label}</span>
                    </div>
                `;
            }).join('');
            
            // Click listener
            document.querySelectorAll('.legend-item').forEach(item => {
                item.addEventListener('click', () => {
                    const traceName = item.dataset.trace;
                    toggleTrace(traceName);
                    item.classList.toggle('legend-item-hidden');
                });
            });
        }
        
        function toggleTrace(traceName) {
            const chartDiv = document.getElementById('termo-chart');
            const traces = chartDiv.data;
            
            traces.forEach((trace, idx) => {
                if (trace.name === traceName || (traceName === 'Dew Point' && trace.legendgroup === 'dewpoint')) {
                    const currentVisible = trace.visible === true || trace.visible === undefined;
                    Plotly.restyle(chartDiv, { visible: !currentVisible }, idx);
                }
            });
            
            // Salva stato in URL
            saveVisibleState();
        }
        
        function saveVisibleState() {
            const chartDiv = document.getElementById('termo-chart');
            const visibleTraces = [];
            
            chartDiv.data.forEach(trace => {
                if (trace.visible !== false && trace.name && trace.name !== '') {
                    visibleTraces.push(trace.name);
                }
            });
            
            const url = new URL(window.location);
            url.searchParams.set('visible', visibleTraces.join(','));
            window.history.replaceState({}, '', url);
        }
        
        function showChartInfo(metadata) {
            const infoDiv = document.getElementById('chart-info');
            
            // Calcola periodo medie 7gg (7 giorni fa -> ieri)
            const now = new Date(metadata.end_time);
            const end7d = new Date(now);
            end7d.setDate(end7d.getDate() - 1); // Ieri
            const start7d = new Date(end7d);
            start7d.setDate(start7d.getDate() - 6); // 7 giorni fa (6 giorni prima di ieri)
            
            const formatDate = (d) => {
                return d.toLocaleDateString('it-IT', { day: 'numeric', month: 'short' });
            };
            
            infoDiv.innerHTML = `
                <strong>Nota:</strong> Le medie mobili 7 giorni sono calcolate sul periodo 
                <strong>${formatDate(start7d)} - ${formatDate(end7d)}</strong> 
                (ultimi 7 giorni completi).
            `;
        }
        
        // ====================================================================
        // CARICAMENTO DATI
        // ====================================================================
        async function loadChart() {
            const loading = document.getElementById('loading');
            const chartDiv = document.getElementById('termo-chart');
            
            try {
                loading.style.display = 'block';
                chartDiv.style.display = 'none';
                
                const response = await fetch(`${CONFIG.endpoint}?range=${CONFIG.range}`);
                
                // Controlla se risposta è ok
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Prova a parsare JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Risposta non-JSON ricevuta:', text.substring(0, 500));
                    throw new Error('API non ha restituito JSON. Controlla console per dettagli.');
                }
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Errore caricamento dati');
                }
                
                loading.style.display = 'none';
                chartDiv.style.display = 'block';
                
                createChart(data);
                
            } catch (error) {
                loading.innerHTML = `<div class="error">Errore: ${error.message}</div>`;
                console.error('Errore completo:', error);
            }
        }
        
        // ====================================================================
        // DEWPOINT SEGMENTI COLORATI
        // ====================================================================
        function replaceDewpointWithSegments(traces, dewpointSegments) {
            // Trova trace dewpoint originale
            const dewpointTraceIndex = traces.findIndex(t => t.name === 'Dew Point');
            
            if (dewpointTraceIndex === -1 || !dewpointSegments || dewpointSegments.length === 0) {
                return traces;
            }
            
            const originalDewpoint = traces[dewpointTraceIndex];
            const newTraces = traces.filter((t, i) => i !== dewpointTraceIndex);
            
            // Raggruppa punti consecutivi con stesso colore
            const segments = [];
            let currentSegment = null;
            
            dewpointSegments.forEach((point, idx) => {
                if (!currentSegment || currentSegment.color !== point.color) {
                    // Nuovo segmento
                    if (currentSegment) {
                        segments.push(currentSegment);
                    }
                    
                    // Se c'è un segmento precedente, inizia nuovo segmento dall'ultimo punto del precedente
                    // per garantire continuità visiva
                    const startX = currentSegment ? [currentSegment.x[currentSegment.x.length - 1], originalDewpoint.x[idx]] : [originalDewpoint.x[idx]];
                    const startY = currentSegment ? [currentSegment.y[currentSegment.y.length - 1], point.value] : [point.value];
                    
                    currentSegment = {
                        color: point.color,
                        x: startX,
                        y: startY,
                        startIdx: idx
                    };
                } else {
                    // Continua segmento corrente
                    currentSegment.x.push(originalDewpoint.x[idx]);
                    currentSegment.y.push(point.value);
                }
            });
            
            // Aggiungi ultimo segmento
            if (currentSegment) {
                segments.push(currentSegment);
            }
            
            // Crea trace per ogni segmento
            segments.forEach((segment, idx) => {
                const isFirstSegment = idx === 0;
                
                newTraces.push({
                    x: segment.x,
                    y: segment.y,
                    type: 'scatter',
                    mode: 'lines',
                    name: isFirstSegment ? 'Dew Point' : '',
                    line: {
                        color: segment.color,
                        width: 1.5  // ✅ Ridotto
                    },
                    hovertemplate: '%{x|%d %b, %H:%M} • <b>%{y:.1f}°C</b><extra></extra>',
                    yaxis: 'y',
                    showlegend: false,
                    legendgroup: 'dewpoint',
                    metricType: 'dewpoint'
                });
            });
            
            // ❌ NON aggiungere traces legend dewpoint soglie
            
            return newTraces;
        }
        
        // ====================================================================
        // CREAZIONE GRAFICO PLOTLY
        // ====================================================================
        function createChart(data) {
            let traces = data.traces;
            const metadata = data.metadata;
            const dewpointSegments = data.dewpoint_segments;
            const chartDiv = document.getElementById('termo-chart');  // ✅ Definito subito
            
            // Sostituisci trace dewpoint singolo con segmenti colorati
            traces = replaceDewpointWithSegments(traces, dewpointSegments);
            
            // Layout
            // Testi responsive: portrait vs landscape
            const isMobile = window.innerWidth <= 768;
            const isPortrait = window.innerHeight > window.innerWidth;
            const isLandscape = window.innerWidth > window.innerHeight && window.innerWidth <= 900;
            
            let axisTitleSize, axisTickSize, yAxisTitle, y2AxisTitle, rangesliderThickness;
            
            if (isLandscape) {
                // Landscape mobile: compatto
                axisTitleSize = 8;
                axisTickSize = 7;
                yAxisTitle = 'Temp(°C)';
                y2AxisTitle = 'Umid(%)';
                rangesliderThickness = 0.04;
            } else if (isMobile && isPortrait) {
                // Portrait mobile: leggibile
                axisTitleSize = 9;
                axisTickSize = 8;
                yAxisTitle = 'Temp(°C)';
                y2AxisTitle = 'Umid(%)';
                rangesliderThickness = 0.05;
            } else {
                // Desktop: standard
                axisTitleSize = 14;
                axisTickSize = 12;
                yAxisTitle = 'Temperatura (°C)';
                y2AxisTitle = 'Umidità (%)';
                rangesliderThickness = 0.05;
            }
            
            const layout = {
                // ✅ Title rimosso (abbiamo subtitle HTML)
                
                // Asse X (tempo)
                xaxis: {
                    type: 'date',
                    gridcolor: '#7d7d7d',
                    gridwidth: 1,
                    tickfont: { color: '#2c3e50', size: axisTickSize },
                    fixedrange: false,
                    range: [metadata.start_time, metadata.end_time],  // ✅ Range corrente (24h/7d/30d)
                    rangeslider: {
                        visible: true,
                        thickness: rangesliderThickness,
                        bgcolor: '#ecf0f1',
                        bordercolor: '#bdc3c7',
                        borderwidth: 1,
                        range: [metadata.first_data_ever, metadata.last_data_ever]  // ✅ Bounds totali
                    }
                },
                
                // Asse Y1 (Temperatura - sinistra)
                yaxis: {
                    title: {
                        text: yAxisTitle,
                        font: { color: '#000000', size: axisTitleSize }
                    },
                    range: [
                        metadata.y_temp_range.min,
                        metadata.y_temp_range.max
                    ],
                    gridcolor: '#7d7d7d',
                    gridwidth: 1,
                    tickfont: { color: '#000000', size: axisTickSize },
                    side: 'left',
                    zeroline: true,
                    zerolinecolor: '#FF00FF',
                    zerolinewidth: 2
                },
                
                // Asse Y2 (Umidità - destra)
                yaxis2: {
                    title: {
                        text: y2AxisTitle,
                        font: { color: '#0000FF', size: axisTitleSize }
                    },
                    range: [0, 100],
                    tickfont: { color: '#0000FF', size: axisTickSize },
                    overlaying: 'y',
                    side: 'right'
                },
                
                // Hover
                hovermode: 'closest',  // Mostra solo punto più vicino
                
                // Stile
                plot_bgcolor: 'white',
                paper_bgcolor: 'white',
                
                // Responsive
                autosize: true,
                
                // Linea zero gradi fucsia
                shapes: [{
                    type: 'line',
                    x0: 0,
                    x1: 1,
                    xref: 'paper',
                    y0: 0,
                    y1: 0,
                    yref: 'y',
                    line: {
                        color: '#FF00FF',  // Fucsia
                        width: 2,
                        dash: 'dot'
                    }
                }],
                
                // Legend sotto grafico (orizzontale)
                legend: {
                    orientation: 'h',
                    x: 0.5,
                    xanchor: 'center',
                    y: -0.2,
                    yanchor: 'top',
                    bgcolor: 'rgba(255,255,255,0.8)',
                    bordercolor: '#bdc3c7',
                    borderwidth: 1
                },
                
                // Margini - compatti
                margin: {
                    l: 60,
                    r: 60,
                    t: 10,  /* ✅ Ridotto da 80 a 10 (no title sopra) */
                    b: 10   /* ✅ Ridotto da 60 a 10 (no legend sotto) */
                }
            };
            
            // Config
            const config = {
                responsive: true,
                displayModeBar: false,  // ✅ Nascosta (usiamo toolbar custom)
                displaylogo: false,
                modeBarButtonsToRemove: [
                    'zoom2d',        // Zoom box (rettangolo)
                    'lasso2d', 
                    'select2d',
                    'autoScale2d'
                ],
                // ✅ zoomIn2d/zoomOut2d rimangono (icone +/- in toolbar)
                locale: 'it',
                scrollZoom: false,  // ❌ Disattivato scroll zoom
                toImageButtonOptions: {
                    format: 'png',
                    filename: `meteosimignano_${metadata.range}`,
                    height: 800,
                    width: 1200
                }
            };
            
            // Crea grafico
            Plotly.newPlot('termo-chart', traces, layout, config);
            
            // Gestione rangeslider X e cursore Y
            let totalRange = {
                xMin: new Date(metadata.first_data_ever).getTime(),
                xMax: new Date(metadata.last_data_ever).getTime()
            };
            
            let currentRange = {
                yMin: metadata.y_temp_range.min,
                yMax: metadata.y_temp_range.max
            };
            
            // Inizializza controlli zoom
            initZoomControls(metadata);
            
            // Listener per cambio rangeslider X
            chartDiv.on('plotly_relayout', (eventData) => {
                // Se cambio range X (da rangeslider o pan)
                if (eventData['xaxis.range[0]'] !== undefined || 
                    eventData['xaxis.range'] !== undefined) {
                    
                    deactivatePresetButtons();
                    
                    // Estrai range richiesto
                    let newStart = eventData['xaxis.range[0]'] || eventData['xaxis.range'][0];
                    let newEnd = eventData['xaxis.range[1]'] || eventData['xaxis.range'][1];
                    
                    // ✅ VALIDAZIONE: Limita a bounds DB
                    const totalStart = new Date(metadata.first_data_ever).getTime();
                    const totalEnd = new Date(metadata.last_data_ever).getTime();
                    const requestStart = new Date(newStart).getTime();
                    const requestEnd = new Date(newEnd).getTime();
                    
                    // Limita start
                    if (requestStart < totalStart) {
                        newStart = new Date(totalStart);
                    }
                    if (requestStart > totalEnd) {
                        newStart = new Date(totalEnd);
                    }
                    
                    // Limita end
                    if (requestEnd > totalEnd) {
                        newEnd = new Date(totalEnd);
                    }
                    if (requestEnd < totalStart) {
                        newEnd = new Date(totalStart);
                    }
                    
                    // Ricarica dati con range validato
                    reloadChartWithCustomRange(newStart, newEnd);
                }
                
                // Doppio click reset
                if (eventData['xaxis.autorange'] === true) {
                    activatePresetButton('<?php echo $range; ?>');
                    window.location.reload();
                }
            });
            
            // Ripristina stato legend da URL
            restoreLegendState();
            
            // Rendering legenda + nota
            showLegend();
            showChartInfo(metadata);
            
            console.log('Grafico creato:', {
                traces: traces.length,
                dataPoints: metadata.data_points,
                range: metadata.range
            });
        }
        
        // ====================================================================
        // RANGE SELECTOR
        // ====================================================================
        document.querySelectorAll('.range-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const newRange = btn.dataset.range;
                
                // Salva stato legend corrente in URL
                const chartDiv = document.getElementById('termo-chart');
                if (chartDiv && chartDiv.data) {
                    const url = new URL(window.location);
                    url.searchParams.set('range', newRange);
                    
                    // Salva quali traces sono visibili
                    const visibleTraces = [];
                    chartDiv.data.forEach((trace, idx) => {
                        if (trace.visible !== false && trace.visible !== 'legendonly') {
                            const name = trace.name || '';
                            if (name && !visibleTraces.includes(name)) {
                                visibleTraces.push(name);
                            }
                        }
                    });
                    
                    if (visibleTraces.length > 0) {
                        url.searchParams.set('visible', visibleTraces.join(','));
                    }
                    
                    window.location.href = url.toString();
                } else {
                    window.location.href = `?range=${newRange}`;
                }
            });
        });
        
        // ====================================================================
        // INIZIALIZZAZIONE
        // ====================================================================
        document.addEventListener('DOMContentLoaded', () => {
            // Listener preset buttons
            document.querySelectorAll('.toolbar-btn-preset').forEach(btn => {
                btn.addEventListener('click', () => {
                    const range = btn.dataset.range;
                    const urlParams = new URLSearchParams(window.location.search);
                    const visible = urlParams.get('visible');
                    
                    // Preserva stato visibilità
                    let url = `?range=${range}`;
                    if (visible) {
                        url += `&visible=${visible}`;
                    }
                    
                    window.location.href = url;
                });
            });
            
            // Listener Plotly tools
            document.getElementById('tool-home').addEventListener('click', () => {
                const chartDiv = document.getElementById('termo-chart');
                Plotly.relayout(chartDiv, {
                    'xaxis.autorange': true,
                    'yaxis.autorange': true
                });
            });
            
            document.getElementById('tool-pan').addEventListener('click', () => {
                const chartDiv = document.getElementById('termo-chart');
                Plotly.relayout(chartDiv, {
                    'dragmode': 'pan'
                });
            });
            
            document.getElementById('tool-download').addEventListener('click', () => {
                const chartDiv = document.getElementById('termo-chart');
                Plotly.downloadImage(chartDiv, {
                    format: 'png',
                    filename: `meteosimignano_${CONFIG.range}`,
                    height: 800,
                    width: 1200
                });
            });
            
            // Attendi che Plotly sia caricato
            if (typeof Plotly === 'undefined') {
                setTimeout(() => loadChart(), 500);
            } else {
                loadChart();
            }
        });
    </script>
</body>
</html>