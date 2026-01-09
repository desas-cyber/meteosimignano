<?php
/**
 * GRAFICO TERMO-IGROMETRICO - VERSIONE PLOTLY.JS (OTTIMIZZATO)
 * 
 * Changelog:
 * - Rimossa ridondanza funzioni (clamp, y2LayoutFor, applyVisibility)
 * - Fix bug rangeslider: mantiene stato visibilità durante zoom/reload
 * - Gestione stato centralizzata in STATE object
 * - Semplificato snapshot/restore stato UI
 */

$range = isset($_GET['range']) ? $_GET['range'] : '24h';
$ranges_validi = ['24h', '7d', '30d'];
if (!in_array($range, $ranges_validi)) {
    $range = '24h';
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
            justify-content: center;
            align-items: center;
            padding: 12px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e1e4e8;
            margin: 0 auto 20px auto;
            gap: 10px;
            max-width: fit-content;
        }
        
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
        
        .toolbar-btn-tool {
            color: #586069;
            font-size: 14px;
            min-width: 32px;
        }
        
        .toolbar-btn-tool:hover {
            background: #f6f8fa;
        }
        
        .toolbar-btn-x {
            color: #586069;
        }
        
        .toolbar-btn-x:hover {
            background: #f6f8fa;
        }
        
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
            padding: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .chart-subtitle {
            text-align: center;
            font-size: 13px;
            color: #586069;
            margin: 3px 0 5px 0;
            font-weight: 500;
            line-height: 1.2;
        }
        
        .chart-subtitle strong {
            color: #2c3e50;
        }
        
        #termo-chart {
            width: 100%;
            height: 600px;
        }
        
        /* Legenda + Nota Box */
        .legend-info-box {
            background: #f8f9fa;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #e1e4e8;
            margin: 8px auto 0 auto;
            max-width: fit-content;
        }
        
        .legend-items {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
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
            text-align: center;
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
        
        .subtitle-spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            margin-left: 8px;
            border: 2px solid #cfd6dc;
            border-top: 2px solid #0366d6;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
        }

        .subtitle-spinner.hidden {
            display: none;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 768px) and (orientation: portrait) {
            .main-title { font-size: 14px; }
            .sub-title { font-size: 9px; }
            .header-icon { min-width: 50px; padding: 6px; }
            .header-icon svg { width: 20px; height: 20px; }
            .icon-label { font-size: 10px; }
            .container { padding: 10px; }
            
            .unified-toolbar {
                flex-direction: column;
                align-items: center;
                padding: 8px;
                gap: 6px;
                width: fit-content;
                margin: 0 auto 15px auto;
            }
            
            .toolbar-row-1,
            .toolbar-row-2 {
                justify-content: center;
                gap: 6px;
            }
            
            .toolbar-separator-mobile {
                display: block;
                width: 100%;
                height: 1px;
                background: #d1d5da;
            }
            
            .toolbar-group { padding: 4px 8px; gap: 4px; }
            .toolbar-label { font-size: 7px; }
            .toolbar-btn { min-width: 24px; height: 24px; font-size: 10px; padding: 0 6px; }
            .toolbar-btn-tool { font-size: 12px; min-width: 24px; }
            
            #termo-chart { height: 600px !important; }
            .chart-subtitle { font-size: 11px; margin: 2px 0 4px 0; }
            .legend-item { font-size: 10px; gap: 6px; }
            .legend-line { width: 25px; }
            .chart-note { font-size: 9px; }
        }
        
        @media (max-width: 900px) and (orientation: landscape) {
            .main-title { font-size: 12px; }
            .sub-title { font-size: 10px; }
            .header-icon { padding: 4px; min-width: 45px; }
            .header-icon svg { width: 18px; height: 18px; }
            .icon-label { font-size: 9px; }
            .container { padding: 8px; }
            
            .unified-toolbar {
                flex-direction: row;
                justify-content: center;
                gap: 4px;
                padding: 6px 10px;
                height: 45px;
                width: fit-content;
                margin: 0 auto 10px auto;
            }
            
            .toolbar-row-1,
            .toolbar-row-2 { gap: 4px; }
            .toolbar-separator-mobile { display: none; }
            .toolbar-group { padding: 4px 6px; gap: 2px; }
            .toolbar-btn { min-width: 22px; height: 22px; font-size: 9px; padding: 0 3px; }
            .toolbar-btn-tool { font-size: 11px; min-width: 22px; }
            .toolbar-label { font-size: 7px; margin-right: 1px; }
            .toolbar-separator { height: 30px; }
            
            .chart-wrapper { padding: 10px; }
            #termo-chart { height: 220px !important; }
            .chart-subtitle { font-size: 10px; margin: 2px 0 3px 0; }
            
            .legend-info-box { padding: 8px 10px; margin-top: 8px; }
            .legend-items { margin-bottom: 6px; padding-bottom: 6px; gap: 12px; }
            .legend-item { font-size: 9px; gap: 4px; }
            .legend-line { width: 20px; height: 2px; }
            .chart-note { font-size: 8px; line-height: 1.3; }
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
                <div class="toolbar-group">
                    <button class="toolbar-btn toolbar-btn-preset <?php echo $range === '24h' ? 'active' : ''; ?>" data-range="24h">24h</button>
                    <button class="toolbar-btn toolbar-btn-preset <?php echo $range === '7d' ? 'active' : ''; ?>" data-range="7d">7d</button>
                    <button class="toolbar-btn toolbar-btn-preset <?php echo $range === '30d' ? 'active' : ''; ?>" data-range="30d">30d</button>
                </div>
                
                <div class="toolbar-separator"></div>
                
                <div class="toolbar-group">
                    <!--<button class="toolbar-btn toolbar-btn-tool" id="tool-home" title="Reset Axes">🏠</button>-->
                    <button class="toolbar-btn toolbar-btn-tool" id="tool-pan" title="Pan">✋</button>
                    <button class="toolbar-btn toolbar-btn-tool" id="tool-download" title="Download PNG">📷</button>
                </div>
            </div>
            
            <div class="toolbar-separator toolbar-separator-mobile"></div>
            
            <div class="toolbar-row-2">
                <div class="toolbar-group">
                    <span class="toolbar-label">Zoom X</span>
                    <button class="toolbar-btn toolbar-btn-x" id="x-zoom-in" title="Zoom In X">+X</button>
                    <button class="toolbar-btn toolbar-btn-x" id="x-zoom-out" title="Zoom Out X">−X</button>
                    <button class="toolbar-btn toolbar-btn-x" id="x-zoom-reset" title="Reset X">⟲X</button>
                </div>
                
                <div class="toolbar-separator"></div>
                
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
            <!--<strong>Temperatura (°C)</strong> • <strong>Umidità (%)</strong>-->
            <span id="subtitle-spinner" class="subtitle-spinner hidden"></span>
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
// MeteoSimignano — Grafico Termo-Igrometrico (OTTIMIZZATO)
// Fix: mantiene stato visibilità durante zoom/reload rangeslider
// ====================================================================

const CONFIG = {
  range: '<?php echo $range; ?>',
  endpoint: 'api/api_grafico_termo_plotly.php',
  metadata: null
};

//DEBUGGGGGGGGG
const DBG = { inProg: 0, relayoutN: 0 };

function dbgKeys(ev) {
  return Object.keys(ev).sort().join(',');
}
//DEBUGGGGGGGGG

// ✅ UNICO STATO CENTRALIZZATO
const STATE = {
  y2_mode: 'umidita',           // 'umidita' | 'pressione' | 'dirvento' | null
  visible: new Set(),           // Set di nomi tracce visibili
  //yTempRange: { min: -10, max: 40 },  // range Y temperatura corrente (per zoom)
  // ✅ auto-range temperatura “one-shot”
  autoTempRangeNext: true,
  xChangeCause: null,
  // 'pan' | 'zoom' | 'preset' | 'rangeslider' | null
   dragmode: 'zoom'   // ✅ aggiungi questa riga
};



let activeFetchId = 0;
let chartInitialized = false;
let uiBound = false;
let plotlyBound = false;
let reloadTimeout = null;
let y2LockToken = 0;
let DBG_fetchN = 0;

let suppressNextXReload = 0;  // ignora 1-2 eventi relayout con xaxis.range
let lastXReloadKey = '';      // evita reload duplicati sullo stesso range

function suppressXReloadOnce(times = 2) {
  suppressNextXReload = Math.max(suppressNextXReload, times);
}

//timeout per fetchare i dati alla fine dello slider
let pendingXRange = null;
let pendingXTimer = null;

function scheduleXRangeFetch(vs, ve, { autoY } = {}) {
  pendingXRange = { vs, ve, autoY: !!autoY };

  if (pendingXTimer) clearTimeout(pendingXTimer);
  pendingXTimer = setTimeout(() => {
    if (!pendingXRange) return;
    const { vs, ve, autoY } = pendingXRange;
    pendingXRange = null;
    pendingXTimer = null;

    reloadChartWithCustomRange(new Date(vs), new Date(ve), { autoY });
  }, 500);
}



// ====================================================================
// UTILS (una sola versione di ogni funzione)
// ====================================================================

function clamp(val, min, max) {
  return Math.max(min, Math.min(max, val));
}

function setSubtitleLoading(isLoading) {
  const el = document.getElementById('subtitle-spinner');
  if (el) el.classList.toggle('hidden', !isLoading);
}

async function runWithSpinner(fn) {
  setSubtitleLoading(true);
  await new Promise(r => requestAnimationFrame(() => setTimeout(r, 0)));
  try {
    return await fn();
  } finally {
    setSubtitleLoading(false);
  }
}

// ✅ Snapshot stato corrente dal grafico (chiamare prima di reload)
function snapshotStateFromChart() {
  const chartDiv = document.getElementById('termo-chart');
  if (!chartDiv || !chartDiv.data) return;

  STATE.visible.clear();
  let dewOn = false;

  chartDiv.data.forEach(t => {
    const vis = (t.visible === true || t.visible === undefined);
    if (!vis) return;

    const name = (t.name || '').trim();
    const lg = (t.legendgroup || '').trim();

    if (name) STATE.visible.add(name);
    if (lg === 'dewpoint') dewOn = true;
  });

  if (dewOn) STATE.visible.add('Dew Point');

  // salva anche range Y temperatura corrente
  if (chartDiv.layout && chartDiv.layout.yaxis && chartDiv.layout.yaxis.range) {
    STATE.yTempRange = {
      min: chartDiv.layout.yaxis.range[0],
      max: chartDiv.layout.yaxis.range[1]
    };
  }

  /*console.log('📸 Snapshot stato:', {
    y2_mode: STATE.y2_mode,
    visible: [...STATE.visible],
    yTempRange: STATE.yTempRange
  });*/
}

// ✅ Applica stato salvato alle tracce (una sola funzione)
function applyStateToTraces(traces) {
  if (STATE.visible.size === 0) return traces;

  const wantsDew = STATE.visible.has('Dew Point');

  return traces.map(t => {
    const name = (t.name || '').trim();
    const lg = (t.legendgroup || '').trim();

    let isWanted = false;

    if (name && STATE.visible.has(name)) isWanted = true;
    if (!isWanted && wantsDew && lg === 'dewpoint') isWanted = true;

    return { ...t, visible: isWanted };
  });
}

// ✅ Forza mutua esclusione Y2 (una sola funzione)
function enforceY2Exclusive(traces) {
  // se Y2 è disattivo, NON toccare nulla
  if (!STATE.y2_mode) return traces;

  return traces.map(t => {
    if (!t.metricType) return t;

    if (t.metricType === 'umidita') {
      return { ...t, visible: STATE.y2_mode === 'umidita' };
    }

    if (t.metricType === 'pressione') {
      return { ...t, visible: STATE.y2_mode === 'pressione' };
    }

    if (t.metricType === 'dirvento') {
      return { ...t, visible: STATE.y2_mode === 'dirvento' };
    }

    return t;
  });
}


// ✅ Inizializza stato da URL (solo al primo caricamento)
function initStateFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const visible = params.get('visible');

  if (visible) {
    const names = visible.split(',').map(s => decodeURIComponent(s).trim()).filter(Boolean);
    names.forEach(n => STATE.visible.add(n));

    // deduce y2_mode da visible
    if (names.includes('Dir. Vento')) STATE.y2_mode = 'dirvento';
    else if (names.includes('Pressione')) STATE.y2_mode = 'pressione';
    else if (names.includes('Umidita') || names.includes('Umidità')) STATE.y2_mode = 'umidita';
    else STATE.y2_mode = null;

    console.log('🔗 Stato da URL:', { y2_mode: STATE.y2_mode, visible: [...STATE.visible] });
  }
}

// ====================================================================
// Y2 LAYOUT (una sola funzione)
// ====================================================================

function y2LayoutFor(mode) {
  const isMobile = window.innerWidth <= 768;

  if (!mode) {
    return {
      titleText: (isMobile ? 'Y2' : 'Asse DX'),
      color: '#586069',
      range: [0, 1],
      fixedrange: false,
      autorange: false,
      tickmode: 'linear',
      tick0: 0,
      dtick: 1,
      tickformat: null
    };
  }

  if (mode === 'pressione') {
    const rawMin = Number(CONFIG.metadata?.y_press_range?.min);
    const rawMax = Number(CONFIG.metadata?.y_press_range?.max);
    const safeMin = Number.isFinite(rawMin) ? rawMin : 990;
    const safeMax = Number.isFinite(rawMax) ? rawMax : 1050;

    let min = clamp(Math.min(safeMin, safeMax), 990, 1050);
    let max = clamp(Math.max(safeMin, safeMax), 990, 1050);

    const MIN_SPAN = 10;
    if (max - min < MIN_SPAN) {
      const mid = (min + max) / 2;
      min = clamp(mid - MIN_SPAN / 2, 990, 1050);
      max = clamp(mid + MIN_SPAN / 2, 990, 1050);
    }

    const snap5 = v => Math.round(v / 5) * 5;
    min = clamp(snap5(min), 990, 1050);
    max = clamp(snap5(max), 990, 1050);
    if (max <= min) { min = 990; max = 1050; }

    return {
      titleText: (isMobile ? 'Press(hPa)' : 'Pressione (hPa)'),
      color: '#05662eff',
      range: [min, max],
      fixedrange: false,
      autorange: false,
      tickmode: 'linear',
      tick0: min,
      dtick: 5,
      tickformat: '.0f'
    };
  }

  if (mode === 'dirvento') {
    return {
      titleText: (isMobile ? 'Dir(°)' : 'Direzione (°)'),
      color: '#ff8c00',
      range: [0, 360],
      fixedrange: true,
      autorange: false,
      tickmode: 'linear',
      tick0: 0,
      dtick: 45,
      tickformat: '.0f'
    };
  }

  return {
    titleText: (isMobile ? 'Umid(%)' : 'Umidità (%)'),
    color: '#0000FF',
    range: [0, 100],
    fixedrange: false,
    autorange: false,
    tickmode: 'linear',
    tick0: 0,
    dtick: 10,
    tickformat: null
  };
}

// ====================================================================
// WIND COLOR SCALE
// ====================================================================

function showWindColorScale() {
  const legendDiv = document.getElementById('chart-legend');
  if (!legendDiv || !CONFIG.metadata?.wind_color_scale) return;

  const existing = document.getElementById('wind-color-scale');
  if (existing) existing.remove();

  const scaleDiv = document.createElement('div');
  scaleDiv.id = 'wind-color-scale';
  scaleDiv.style.cssText =
    'margin-top:10px; padding:8px; background:#f8f9fa; border-radius:4px; font-size:11px; text-align:center;';

  const isMobile = window.innerWidth <= 768;
  let html = '<div style="font-weight:600; margin-bottom:5px;">Velocità Vento km/h:</div>' +
             '<div style="display:flex; justify-content:center; flex-wrap:wrap; gap:8px;">';

  CONFIG.metadata.wind_color_scale.forEach(item => {
    const label = isMobile ? item.range.split(' ')[0] : item.range;
    html += `
      <span style="display:inline-flex; align-items:center; gap:4px;">
        <span style="display:inline-block; width:10px; height:10px; background:${item.color}; border-radius:50%; border:1px solid #333;"></span>
        <span style="font-size:${isMobile ? '9px' : '10px'};">${label}</span>
      </span>
    `;
  });

  html += '</div>';
  scaleDiv.innerHTML = html;
  legendDiv.parentNode.insertBefore(scaleDiv, legendDiv.nextSibling);
}

function hideWindColorScale() {
  const s = document.getElementById('wind-color-scale');
  if (s) s.remove();
}

// ====================================================================
// CHART INFO
// ====================================================================

function showChartInfo(chartInfo) {
  const infoDiv = document.getElementById('chart-info');
  if (!infoDiv || !chartInfo) return;

  const formatDate = (ymd) => {
    const d = new Date(ymd + 'T00:00:00');
    return d.toLocaleDateString('it-IT', { day: 'numeric', month: 'short' });
  };

  infoDiv.innerHTML = `
    <strong>Nota:</strong> Le medie mobili 7 giorni sono calcolate sul periodo
    <strong>${formatDate(chartInfo.media7d_start)} - ${formatDate(chartInfo.media7d_end)}</strong>
    (ultimi 7 giorni completi).
  `;
}

// ====================================================================
// LEGENDA + HIGHLIGHT
// ====================================================================

function updateLegendHighlight() {
  const chartDiv = document.getElementById('termo-chart');
  if (!chartDiv || !chartDiv.data) return;

  document.querySelectorAll('.legend-item').forEach(item => {
    const traceName = item.dataset.trace;
    let isVisible = false;

    chartDiv.data.forEach(trace => {
      const n = (trace.name || '').trim();
      const lg = (trace.legendgroup || '').trim();

      if (n === traceName) {
        isVisible = (trace.visible === true || trace.visible === undefined);
      }
      if (traceName === 'Dew Point' && lg === 'dewpoint') {
        isVisible = (trace.visible === true || trace.visible === undefined);
      }
    });

    item.classList.toggle('legend-item-hidden', !isVisible);
  });
}

function showLegend() {
  const legendDiv = document.getElementById('chart-legend');
  if (!legendDiv) return;

  const isMobile = window.innerWidth <= 768 || (window.innerWidth <= 900 && window.innerHeight < window.innerWidth);

  const items = [
    { label: isMobile ? 'Temp'  : 'Temperatura',   name: 'Temperatura',   color: '#000000', dashed: false },
    { label: isMobile ? 'Umid'  : 'Umidità',       name: 'Umidita',       color: '#0000FF', dashed: false },
    { label: isMobile ? 'Press' : 'Pressione',     name: 'Pressione',     color: '#27ae60', dashed: false },
    { label: isMobile ? 'Vento' : 'Dir. Vento',    name: 'Dir. Vento',    color: '#ff8c00', dashed: false, markers: true },
    { label: isMobile ? 'DP'    : 'Dew Point',     name: 'Dew Point',     color: 'gradient', dashed: false },
    { label: isMobile ? 'Media' : 'Media Periodo', name: 'Media Periodo', color: '#ff6b35', dashed: 'dot' },
    { label: isMobile ? 'Max'   : 'Media Max 7gg', name: 'Media Max 7gg', color: '#e74c3c', dashed: true },
    { label: isMobile ? 'Min'   : 'Media Min 7gg', name: 'Media Min 7gg', color: '#3498db', dashed: true }
  ];

  legendDiv.innerHTML = items.map(item => {
    let lineStyle = '';
    let lineClass = 'legend-line';

    if (item.markers) {
      lineStyle = 'background: transparent;';
    } else if (item.color === 'gradient') {
      lineStyle = 'background: linear-gradient(to right, #808080, #27ae60, #f39c12, #e74c3c);';
    } else if (item.dashed === 'dot') {
      lineStyle = `background: repeating-linear-gradient(to right, ${item.color} 0px, ${item.color} 3px, transparent 3px, transparent 6px);`;
    } else if (item.dashed) {
      lineStyle = `background-color: ${item.color}; color: ${item.color};`;
      lineClass = 'legend-line dashed';
    } else {
      lineStyle = `background-color: ${item.color};`;
    }

    const markerHtml = item.markers
      ? `<div style="position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); width:6px; height:6px; background:${item.color}; border-radius:50%;"></div>`
      : '';

    return `
      <div class="legend-item" data-trace="${item.name}" style="cursor:pointer;">
        <div class="${lineClass}" style="position:relative; ${lineStyle}">${markerHtml}</div>
        <span>${item.label}</span>
      </div>
    `;
  }).join('');

  document.querySelectorAll('.legend-item').forEach(item => {
    item.addEventListener('click', async () => {
      const traceName = item.dataset.trace;
      await runWithSpinner(async () => {
        await toggleTrace(traceName);
      });
      updateLegendHighlight();
    });
  });
}

// ====================================================================
// DEW POINT SEGMENTATO
// ====================================================================

function replaceDewpointWithSegments(traces, dewpointSegments) {
  const dewpointTraceIndex = traces.findIndex(t => (t.name || '').trim() === 'Dew Point');
  if (dewpointTraceIndex === -1 || !dewpointSegments || dewpointSegments.length === 0) return traces;

  const original = traces[dewpointTraceIndex];
  const newTraces = traces.filter((_, i) => i !== dewpointTraceIndex);

  const segments = [];
  let current = null;

  dewpointSegments.forEach((point, idx) => {
    if (!current || current.color !== point.color) {
      if (current) segments.push(current);

      const startX = current ? [current.x[current.x.length - 1], original.x[idx]] : [original.x[idx]];
      const startY = current ? [current.y[current.y.length - 1], point.value] : [point.value];

      current = { color: point.color, x: startX, y: startY };
    } else {
      current.x.push(original.x[idx]);
      current.y.push(point.value);
    }
  });

  if (current) segments.push(current);

  segments.forEach((seg, idx) => {
    newTraces.push({
      x: seg.x,
      y: seg.y,
      type: 'scatter',
      mode: 'lines',
      name: (idx === 0 ? 'Dew Point' : ''),
      line: { color: seg.color, width: 1.5 },
      hovertemplate: '%{x|%d %b, %H:%M} • <b>%{y:.1f}°C</b><extra></extra>',
      yaxis: 'y',
      showlegend: false,
      legendgroup: 'dewpoint',
      metricType: 'dewpoint'
    });
  });

  return newTraces;
}

// ====================================================================
// Y2 TOGGLE (mutua esclusione + OFF)
// ====================================================================

function findY2IndicesByMetric(data) {
  const idxs = { umidita: null, pressione: null, dirvento: null };
  for (let i = 0; i < data.length; i++) {
    const mt = data[i].metricType;
    if (mt === 'umidita') idxs.umidita = i;
    else if (mt === 'pressione') idxs.pressione = i;
    else if (mt === 'dirvento') idxs.dirvento = i;
  }
  return idxs;
}

async function toggleY2Metric(mode) {
  const myToken = ++y2LockToken;
  STATE.y2_mode = mode;

  const chartDiv = document.getElementById('termo-chart');
  if (!chartDiv || !chartDiv.data) return;

  const idxs = findY2IndicesByMetric(chartDiv.data);
  const all = [idxs.umidita, idxs.pressione, idxs.dirvento].filter(v => v !== null);

  if (all.length) await Plotly.restyle(chartDiv, { visible: all.map(() => false) }, all);

  if (mode) {
    const idx = idxs[mode];
    if (idx !== null) await Plotly.restyle(chartDiv, { visible: true }, [idx]);
  }

  const y2 = y2LayoutFor(mode);
  if (myToken !== y2LockToken) return;

  
  if (myToken !== y2LockToken) return;

  suppressXReloadOnce(2); // ✅ fondamentale su mobile: evita reload X “fantasma”

  await Plotly.relayout(chartDiv, {
    'yaxis2.title.text': y2.titleText,
    'yaxis2.title.font.color': y2.color,
    'yaxis2.tickfont.color': y2.color,
    'yaxis2.range': y2.range,
    'yaxis2.autorange': false,
    'yaxis2.fixedrange': y2.fixedrange,
    'yaxis2.constrain': 'range',
    'yaxis2.tickmode': y2.tickmode,
    'yaxis2.tick0': y2.tick0,
    'yaxis2.dtick': y2.dtick,
    'yaxis2.tickformat': y2.tickformat
  });

  if (mode === 'dirvento') showWindColorScale();
  else hideWindColorScale();

  snapshotStateFromChart();  // ✅ aggiorna stato dopo toggle
  updateLegendHighlight();
}

// ====================================================================
// TOGGLE TRACE (incluso Dew Point batch)
// ====================================================================

async function toggleTrace(traceName) {
  if (traceName === 'Umidita') {
    const next = (STATE.y2_mode === 'umidita') ? null : 'umidita';
    await toggleY2Metric(next);
    return;
  }
  if (traceName === 'Pressione') {
    const next = (STATE.y2_mode === 'pressione') ? null : 'pressione';
    await toggleY2Metric(next);
    return;
  }
  if (traceName === 'Dir. Vento') {
    const next = (STATE.y2_mode === 'dirvento') ? null : 'dirvento';
    await toggleY2Metric(next);
    return;
  }

  const chartDiv = document.getElementById('termo-chart');
  if (!chartDiv || !chartDiv.data) return;

  const traces = chartDiv.data;
  const isDew = (traceName === 'Dew Point');

  if (!isDew) {
    const idx = traces.findIndex(t => (t.name || '').trim() === traceName);
    if (idx === -1) return;

    const cur = (traces[idx].visible === true || traces[idx].visible === undefined);
    await Plotly.restyle(chartDiv, { visible: !cur }, [idx]);
    snapshotStateFromChart();  // ✅ aggiorna stato
    return;
  }

  const dewIdxs = [];
  for (let i = 0; i < traces.length; i++) {
    if ((traces[i].legendgroup || '').trim() === 'dewpoint') dewIdxs.push(i);
  }
  if (!dewIdxs.length) return;

  const sample = traces[dewIdxs[0]];
  const cur = (sample.visible === true || sample.visible === undefined);
  await Plotly.restyle(chartDiv, { visible: !cur }, dewIdxs);

  snapshotStateFromChart();  // ✅ aggiorna stato
}

// ====================================================================
// RELOAD CUSTOM RANGE (✅ mantiene stato visibilità)
// ====================================================================

function reloadChartWithCustomRange(startDate, endDate, opts = {}) {
  //debuggggggg
  DBG_fetchN++;
  console.log(`[FETCH] #${DBG_fetchN} start=${startDate.toISOString()} end=${endDate.toISOString()} opts=`, opts);
  //debuggggggg
  const { autoY = false } = opts;
  clearTimeout(reloadTimeout);
  
  snapshotStateFromChart();  // ✅ salva stato prima del reload

  const myId = ++activeFetchId;
  setSubtitleLoading(true);

  reloadTimeout = setTimeout(async () => {
    try {
      const start = formatLocalSQL(new Date(startDate));
      const end   = formatLocalSQL(new Date(endDate));

      const resp = await fetch(`${CONFIG.endpoint}?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`);
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

      const ct = resp.headers.get('content-type');
      if (!ct || !ct.includes('application/json')) {
        const text = await resp.text();
        console.error('Risposta non-JSON:', text.substring(0, 500));
        throw new Error('API non ha restituito JSON');
      }

      const data = await resp.json();
      if (!data.success) throw new Error(data.error || 'Errore caricamento');

      if (myId !== activeFetchId) return;

        STATE.autoTempRangeNext = true; // ✅ auto-Y SOLO dopo drag/rangeslider (rilascio)

      STATE.autoTempRangeNext = !!autoY;
      // ✅ ricrea grafico con stato salvato
      await createChart(data);

    } catch (e) {
      if (myId !== activeFetchId) return;
      console.error('Errore reload custom range:', e);
    } finally {
      if (myId === activeFetchId) setSubtitleLoading(false);
    }
  }, 500);
}

// ====================================================================
// ZOOM CONTROLS
// ====================================================================

function initZoomControls(metadata) {
  const chartDiv = document.getElementById('termo-chart');

  document.getElementById('x-zoom-in').onclick = () => {
    const r = chartDiv.layout?.xaxis?.range;
    if (!r) return;

    const start = new Date(r[0]).getTime();
    const end = new Date(r[1]).getTime();
    const center = (start + end) / 2;
    const span = (end - start) * 0.5;

    const totalStart = new Date(metadata.first_data_ever).getTime();
    const totalEnd = new Date(metadata.last_data_ever).getTime();

    const ns = Math.max(totalStart, center - span / 2);
    const ne = Math.min(totalEnd, center + span / 2);

    Plotly.relayout(chartDiv, { 'xaxis.range': [new Date(ns), new Date(ne)] });
    deactivatePresetButtons();
  };

  document.getElementById('x-zoom-out').onclick = () => {
    const r = chartDiv.layout?.xaxis?.range;
    if (!r) return;

    const start = new Date(r[0]).getTime();
    const end = new Date(r[1]).getTime();
    const center = (start + end) / 2;
    const span = (end - start) * 2;

    const totalStart = new Date(metadata.first_data_ever).getTime();
    const totalEnd = new Date(metadata.last_data_ever).getTime();

    const ns = Math.max(totalStart, center - span / 2);
    const ne = Math.min(totalEnd, center + span / 2);

    const loadedStart = new Date(metadata.start_time).getTime();
    const loadedEnd = new Date(metadata.end_time).getTime();

    if (ns < loadedStart || ne > loadedEnd) {
      reloadChartWithCustomRange(new Date(ns), new Date(ne));
    } else {
      Plotly.relayout(chartDiv, { 'xaxis.range': [new Date(ns), new Date(ne)] });
    }

    deactivatePresetButtons();
  };

  document.getElementById('x-zoom-reset').onclick = () => {
    window.location.href = `?range=${CONFIG.range}`;
  };

  document.getElementById('y-zoom-in').onclick = () => {
    const span = (STATE.yTempRange.max - STATE.yTempRange.min) * 0.7;
    const mid = (STATE.yTempRange.max + STATE.yTempRange.min) / 2;
    STATE.yTempRange.min = Math.max(-20, Math.round(mid - span / 2));
    STATE.yTempRange.max = Math.min(50, Math.round(mid + span / 2));
    Plotly.relayout(chartDiv, { 'yaxis.range': [STATE.yTempRange.min, STATE.yTempRange.max] });
    deactivatePresetButtons();
  };

  document.getElementById('y-zoom-out').onclick = () => {
    const span = (STATE.yTempRange.max - STATE.yTempRange.min) * 1.4;
    const mid = (STATE.yTempRange.max + STATE.yTempRange.min) / 2;
    STATE.yTempRange.min = Math.max(-20, Math.round(mid - span / 2));
    STATE.yTempRange.max = Math.min(50, Math.round(mid + span / 2));
    Plotly.relayout(chartDiv, { 'yaxis.range': [STATE.yTempRange.min, STATE.yTempRange.max] });
    deactivatePresetButtons();
  };

  document.getElementById('y-zoom-reset').onclick = () => {
    STATE.yTempRange = { min: metadata.y_temp_range.min, max: metadata.y_temp_range.max };
    //Plotly.relayout(chartDiv, { 'yaxis.range': [STATE.yTempRange.min, STATE.yTempRange.max] });
    //debuggggg
    relayoutProgrammatic(chartDiv, {
  'yaxis.range': [STATE.yTempRange.min, STATE.yTempRange.max]
}, 'Y reset');//debugggggggg
  };
}

// ====================================================================
// PRESET BUTTONS
// ====================================================================

function deactivatePresetButtons() {
  document.querySelectorAll('.toolbar-btn-preset').forEach(btn => btn.classList.remove('active'));
}

function activatePresetButton(range) {
  document.querySelectorAll('.toolbar-btn-preset').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.range === range);
  });
}

// ====================================================================
// PLOTLY RELAYOUT HANDLER (bind una volta)
// ====================================================================

function bindPlotlyEventsOnce(metadata) {
  const chartDiv = document.getElementById('termo-chart');
  if (!chartDiv || plotlyBound) return;

  chartDiv.on('plotly_relayout', (eventData) => {
    //DEBUGGGGGGGGG

  DBG.relayoutN++;
const keys = dbgKeys(eventData);

// logga SOLO 1 ogni 50 per xaxis.range (che è rumorosissimo)
if (keys === 'xaxis.range') {
  if (DBG.relayoutN % 50 === 0) {
    console.log(`[RELAYOUT USER] n=${DBG.relayoutN} keys=${keys}`);
  }
} else if (keys) {
  console.log(`[RELAYOUT USER] n=${DBG.relayoutN} keys=${keys}`);
} else {
  // a volte Plotly manda relayout “vuoti” o non standard
  // puoi ignorare:
  // console.log(`[RELAYOUT USER] n=${DBG.relayoutN} (no keys)`);
}

  // DEBUGGGGGGGGGGGGGGGGGGGG



    // ------------------------------------------------------------
    // 1) Fix y2=0-360 (dirvento) — MA sopprimi reload X (mobile!)
    // ------------------------------------------------------------
   if (STATE.y2_mode === 'dirvento') {

  // Se questo relayout tocca yaxis2.*, controlla se serve davvero “fixare”
  const touchedY2 = Object.keys(eventData || {}).some(k => k.startsWith('yaxis2.'));
  if (touchedY2) {
    const localToken = y2LockToken;

    setTimeout(async () => {
      if (STATE.y2_mode !== 'dirvento') return;
      if (localToken !== y2LockToken) return;

      const cur = chartDiv.layout?.yaxis2?.range;
      const already = Array.isArray(cur) && cur.length === 2 &&
                      Math.abs(cur[0] - 0) < 1e-6 && Math.abs(cur[1] - 360) < 1e-6;

      // ✅ se già ok, NON fare relayout (altrimenti ti auto-inneschi)
      if (already) return;

      const y2 = y2LayoutFor('dirvento');

      await relayoutProgrammatic(chartDiv, {
        'yaxis2.title.text': y2.titleText,
        'yaxis2.title.font.color': y2.color,
        'yaxis2.tickfont.color': y2.color,
        'yaxis2.range': y2.range,              // per dirvento deve essere [0,360]
        'yaxis2.autorange': false,
        'yaxis2.fixedrange': true,             // per dirvento
        'yaxis2.constrain': 'range',
        'yaxis2.tickmode': y2.tickmode,
        'yaxis2.tick0': y2.tick0,
        'yaxis2.dtick': y2.dtick,
        'yaxis2.tickformat': y2.tickformat
      }, 'dirvento fix');
    }, 0);
  }
}


    // ------------------------------------------------------------
    // 2) Cambio range X (rangeslider / pan / zoom box)
    // ------------------------------------------------------------
    const hasX0 = eventData['xaxis.range[0]'] !== undefined;
    const hasX1 = eventData['xaxis.range[1]'] !== undefined;
    const hasXArr = Array.isArray(eventData['xaxis.range']);
    const hasX = (hasX0 && hasX1) || hasXArr;

    if (hasX) {
      // 🔒 PAN = niente fetch, niente auto-Y
      if (STATE.xChangeCause === 'pan') {
        STATE.xChangeCause = null;
        
      }

      // Se il relayout X è conseguenza di una nostra Plotly.relayout/Plotly.react
      // (y2, fix dirvento, reset ecc.), NON ricaricare dati.
      if (suppressNextXReload > 0) {
        suppressNextXReload--;
        return;
      }

      let newStart, newEnd;
      if (hasXArr) {
        newStart = eventData['xaxis.range'][0];
        newEnd   = eventData['xaxis.range'][1];
      } else {
        newStart = eventData['xaxis.range[0]'];
        newEnd   = eventData['xaxis.range[1]'];
      }

      const rs = new Date(newStart).getTime();
      const re = new Date(newEnd).getTime();
      if (!Number.isFinite(rs) || !Number.isFinite(re)) return;

      // dedup: stesso range ripetuto (molto comune su mobile)
      const key = `${rs}-${re}`;
      if (key === lastXReloadKey) return;
      lastXReloadKey = key;

      deactivatePresetButtons();

      const totalStart = new Date(CONFIG.metadata.first_data_ever).getTime();
      const totalEnd   = new Date(CONFIG.metadata.last_data_ever).getTime();

      let vs = rs, ve = re;
      if (vs < totalStart) vs = totalStart;
      if (vs > totalEnd)   vs = totalEnd;
      if (ve > totalEnd)   ve = totalEnd;
      if (ve < totalStart) ve = totalStart;

      const isPan = (STATE.xChangeCause === 'pan');

      // reset causa subito (così non “sporca” eventi successivi)
      STATE.xChangeCause = null;

      // ✅ auto Y solo se NON pan
      if (!isPan) {
        STATE.autoTempRangeNext = true;
      }

      
      const isPanNow =
        chartDiv.layout && chartDiv.layout.dragmode === 'pan';

      const autoY = !isPanNow;
      if (STATE.dragmode === 'pan') {
  // appena termina un pan (relayout X), torniamo allo zoom box
  STATE.dragmode = 'zoom';
  relayoutProgrammatic(chartDiv, { dragmode: 'zoom' }, 'pan off');
}


      scheduleXRangeFetch(vs, ve, { autoY });

    }

    // ------------------------------------------------------------
    // 3) doppio click reset — NON fare reload pagina
    // ------------------------------------------------------------
    if (eventData['xaxis.autorange'] === true) {
      // al massimo riallinea UI, ma niente reload
      activatePresetButton(CONFIG.range);
      // (se vuoi, puoi anche fare suppressXReloadOnce() qui)
    }
  });

  plotlyBound = true;
}


// ====================================================================
// CREATE CHART (✅ usa stato salvato)
// ====================================================================

async function createChart(data) {
  let traces = data.traces;
  const metadata = data.metadata;

  CONFIG.metadata = metadata;

  // Dew point segmentato
  traces = replaceDewpointWithSegments(traces, data.dewpoint_segments);

  // ✅ Applica stato salvato (visibilità + y2_mode)
  traces = applyStateToTraces(traces);
  traces = enforceY2Exclusive(traces);


  // ✅ AUTO range temperatura solo quando richiesto (primo load o preset)
if (STATE.autoTempRangeNext) {
  const auto = computeTempRangeFromTraces(traces, 5);
  if (auto) {
    STATE.yTempRange = auto;
    //console.log('🌡️ Auto Y temp:', auto);
  }
  STATE.autoTempRangeNext = false; // one-shot
}


  // Titoli responsive
  const isMobile = window.innerWidth <= 768;
  const isLandscape = window.innerWidth > window.innerHeight && window.innerWidth <= 900;

  const axisTitleSize = isLandscape ? 8 : isMobile ? 9 : 14;
  const axisTickSize = isLandscape ? 7 : isMobile ? 8 : 12;

  const yAxisTitle = isMobile ? 'Temp(°C)' : 'Temperatura (°C)';
  const y2 = y2LayoutFor(STATE.y2_mode);

  const layout = {
    dragmode: STATE.dragmode || 'zoom',   // ✅ QUESTA è la riga chiave
    
    xaxis: {
      type: 'date',
      fixedrange: false,
      range: [metadata.start_time, metadata.end_time],
      gridcolor: '#7d7d7d',
      gridwidth: 1,
      tickfont: { color: '#2c3e50', size: axisTickSize },
      rangeslider: {
        visible: true,
        thickness: (isLandscape ? 0.04 : 0.05),
        bgcolor: '#ecf0f1',
        bordercolor: '#bdc3c7',
        borderwidth: 1,
        range: [metadata.first_data_ever, metadata.last_data_ever]
      }
    },

    yaxis: {
      title: {
        text: yAxisTitle,
        font: { color: '#000000', size: axisTitleSize }
      },
      range: [STATE.yTempRange.min, STATE.yTempRange.max],  // ✅ usa range salvato
      gridcolor: '#7d7d7d',
      gridwidth: 1,
      tickfont: { color: '#000000', size: axisTickSize },
      side: 'left',
      zeroline: true,
      zerolinecolor: '#FF00FF',
      zerolinewidth: 2,
      fixedrange: false
    },

    yaxis2: {
      title: { text: y2.titleText, font: { color: y2.color, size: axisTitleSize } },
      tickfont: { color: y2.color, size: axisTickSize },
      range: y2.range,
      autorange: false,
      fixedrange: !!y2.fixedrange,
      constrain: 'range',
      tickmode: y2.tickmode || 'linear',
      tick0: (y2.tick0 !== undefined ? y2.tick0 : null),
      dtick: (y2.dtick !== undefined ? y2.dtick : null),
      tickformat: (y2.tickformat || null),
      overlaying: 'y',
      side: 'right'
    },

    hovermode: 'closest',
    plot_bgcolor: 'white',
    paper_bgcolor: 'white',
    autosize: true,

    shapes: [{
      type: 'line',
      x0: 0,
      x1: 1,
      xref: 'paper',
      y0: 0,
      y1: 0,
      yref: 'y',
      line: { color: '#FF00FF', width: 0.7, dash: 'dash' }
    }],

    margin: { l: 60, r: 60, t: 10, b: 10 }
  };

  const config = {
    responsive: true,
    displayModeBar: false,
    displaylogo: false,
    locale: 'it',
    scrollZoom: false,
    toImageButtonOptions: {
      format: 'png',
      filename: `meteosimignano_${metadata.range}`,
      height: 800,
      width: 1200
    }
  };

  if (!chartInitialized) {
    await Plotly.newPlot('termo-chart', traces, layout, config);
    chartInitialized = true;
  } else {
    await Plotly.react('termo-chart', traces, layout, config);
  }

  // UI extra
  if (STATE.y2_mode === 'dirvento') showWindColorScale();
  else hideWindColorScale();

  bindPlotlyEventsOnce(metadata);
  initZoomControls(metadata);

  showLegend();
  updateLegendHighlight();
  showChartInfo(data.chart_info);

  // ✅ aggiorna range Y temperatura da metadata (prima volta)
  if (!STATE.yTempRange || STATE.yTempRange.min === -10) {
    STATE.yTempRange = {
      min: metadata.y_temp_range.min,
      max: metadata.y_temp_range.max
    };
  }
}

// ====================================================================
// LOAD CHART (preset range)
// ====================================================================

async function loadChart() {
  const loading = document.getElementById('loading');
  const chartDiv = document.getElementById('termo-chart');

  const myId = ++activeFetchId;
  setSubtitleLoading(true);

  try {
    loading.style.display = 'block';
    chartDiv.style.display = 'none';

    const resp = await fetch(`${CONFIG.endpoint}?range=${encodeURIComponent(CONFIG.range)}`);
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

    const ct = resp.headers.get('content-type');
    if (!ct || !ct.includes('application/json')) {
      const text = await resp.text();
      console.error('Risposta non-JSON:', text.substring(0, 500));
      throw new Error('API non ha restituito JSON');
    }

    const data = await resp.json();
    if (!data.success) throw new Error(data.error || 'Errore caricamento');

    if (myId !== activeFetchId) return;

    loading.style.display = 'none';
    chartDiv.style.display = 'block';

    await createChart(data);

  } catch (e) {
    if (myId !== activeFetchId) return;
    loading.innerHTML = `<div class="error">Errore: ${e.message}</div>`;
    console.error('Errore completo:', e);
  } finally {
    if (myId === activeFetchId) setSubtitleLoading(false);
  }
}
// ====================================================================
// SELEZIONE DEI BOTTONI PRESET BIND AL RANGESLIDER
// ====================================================================

function getPresetRange(range) {
  const end = new Date(CONFIG.metadata.last_data_ever || Date.now());
  const start = new Date(end);

  if (range === '24h') start.setHours(start.getHours() - 24);
  else if (range === '7d') start.setDate(start.getDate() - 7);
  else if (range === '30d') start.setDate(start.getDate() - 30);

  return [start, end];
}


// ====================================================================
// BIND UI (una volta)
// ====================================================================

function bindUIOnce() {
  if (uiBound) return;

  document.querySelectorAll('.toolbar-btn-preset').forEach(btn => {
   btn.addEventListener('click', () => {
  const range = btn.dataset.range;
  const chartDiv = document.getElementById('termo-chart');
  if (!chartDiv || !CONFIG.metadata) return;

  // 🔑 QUESTO È IL PUNTO 4
  STATE.autoTempRangeNext = true;

  const [start, end] = getPresetRange(range);

  Plotly.relayout(chartDiv, {
    'xaxis.range': [start, end]
  });

  activatePresetButton(range);
});


  });


  document.getElementById('tool-pan').addEventListener('click', async () => {
  const chartDiv = document.getElementById('termo-chart');
  if (!chartDiv) return;

  STATE.xChangeCause = 'pan';
  STATE.dragmode = 'pan';

  await relayoutProgrammatic(chartDiv, { dragmode: 'pan' }, 'pan on');
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

  uiBound = true;
}

// ====================================================================
// funzione per inizializzare lo zoom in maniera dinamica
// ====================================================================


function computeTempRangeFromTraces(traces, pad = 5) {
  const t = traces.find(tr => (tr.name || '').trim() === 'Temperatura');
  if (!t || !Array.isArray(t.y) || t.y.length === 0) return null;

  let min = Infinity, max = -Infinity;
  for (const v of t.y) {
    const n = Number(v);
    if (!Number.isFinite(n)) continue;
    if (n < min) min = n;
    if (n > max) max = n;
  }
  if (!Number.isFinite(min) || !Number.isFinite(max)) return null;

  min = Math.floor(min - pad);
  max = Math.ceil(max + pad);

  // limiti di sicurezza (facoltativi)
  min = Math.max(-30, min);
  max = Math.min(60, max);

  if (max <= min) { min -= pad; max += pad; }

  return { min, max };
}
// ====================================================================
// MANDA GLI ORARI AL BACK END DIRETTAMENTE IN LOCALE
// ====================================================================

function formatLocalSQL(d) {
  const pad = n => String(n).padStart(2, '0');
  const yyyy = d.getFullYear();
  const mm = pad(d.getMonth() + 1);
  const dd = pad(d.getDate());
  const hh = pad(d.getHours());
  const mi = pad(d.getMinutes());
  const ss = pad(d.getSeconds());
  return `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
}

//DEBUGGGGGGGGG
function relayoutProgrammatic(chartDiv, payload, tag) {
  DBG.inProg++;
  console.log(`[PRG RELAYOUT START] ${tag}`);
  // se hai suppressXReloadOnce:
  suppressXReloadOnce(2);
  try {
     Plotly.relayout(chartDiv, payload);
  } finally {
    console.log(`[PRG RELAYOUT END] ${tag}`);
    DBG.inProg--;
  }
}
//DEBUGGGGGGGGG
let suppressNextY2Relayout = 0;

function suppressY2RelayoutOnce(times = 3) {
  suppressNextY2Relayout = Math.max(suppressNextY2Relayout, times);
}

// wrapper “ufficiale” per i relayout fatti da noi
async function relayoutProgrammatic(chartDiv, obj, tag = '') {
  // se modifichiamo yaxis2.*, ci aspettiamo 1-3 relayout “di assestamento”
  if (Object.keys(obj).some(k => k.startsWith('yaxis2.'))) {
    suppressY2RelayoutOnce(3);
  }

  // se modifichiamo xaxis.range, sopprimi eventuali reload X “fantasma”
  if (Object.keys(obj).some(k => k.startsWith('xaxis.'))) {
    suppressXReloadOnce(2);
  }

  console.log(`[PRG RELAYOUT START] ${tag}`, Object.keys(obj));
  await Plotly.relayout(chartDiv, obj);
  console.log(`[PRG RELAYOUT END] ${tag}`);
}


// ====================================================================
// INIT
// ====================================================================

document.addEventListener('DOMContentLoaded', () => {
  initStateFromUrl();  // ✅ inizializza stato da URL (solo prima volta)
  bindUIOnce();

  if (typeof Plotly === 'undefined') {
    setTimeout(loadChart, 500);
  } else {
    loadChart();
  }
});

    </script>
</body>
</html>