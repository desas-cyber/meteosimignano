<?php
/**
 * ============================================================================
 * GRAFICO TERMICO SOGLIE - stat2_grafico.php
 * ============================================================================
 * Timeline orizzontale colorata per soglie di temperatura.
 * Ogni anno e' una riga; ogni giorno e' colorato in base a 4 soglie:
 *   - rosso scuro  : temp_max >= soglia molto caldo (default 35 gradi)
 *   - rosso chiaro : temp_max >= soglia caldo       (default 30 gradi)
 *   - blu scuro    : temp_min <= soglia molto freddo (default 0 gradi)
 *   - blu chiaro   : temp_min <= soglia freddo       (default 5 gradi)
 *
 * PARAMETRI GET (tutti opzionali, usati come default degli slider):
 *   ?s1=NUM   soglia caldo        (default 30)
 *   ?s2=NUM   soglia molto caldo  (default 35)
 *   ?s3=NUM   soglia freddo       (default 5)
 *   ?s4=NUM   soglia molto freddo (default 0)
 *
 * Comunicazione con il padre (stat_display.php) via postMessage:
 *   - resize   : aggiorna altezza iframe quando il contenuto cambia
 *   - torna    : torna alla tabella stat2
 */

ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../envelop_lettura.php';
require_once __DIR__ . '/api/api_tabella_stat_data.php';

// Parametri GET -> default slider
$def_s1 = isset($_GET['s1']) ? (float)$_GET['s1'] : 30;
$def_s2 = isset($_GET['s2']) ? (float)$_GET['s2'] : 35;
$def_s3 = isset($_GET['s3']) ? (float)$_GET['s3'] : 5;
$def_s4 = isset($_GET['s4']) ? (float)$_GET['s4'] : 0;

// Sanity check range
$def_s1 = max(8,  min(45, $def_s1));
$def_s2 = max(8,  min(45, $def_s2));
$def_s3 = max(-5, min(20, $def_s3));
$def_s4 = max(-5, min(20, $def_s4));

// Dati dal DB
$response = getGraficoTermicoData();

if (!$response['success']) {
    echo "<div style='font-family:Arial;font-size:13px;color:#c00;padding:10px;'>
            Errore: " . htmlspecialchars($response['error'] ?? 'Dati non disponibili') . "
          </div>";
    exit;
}

$anni       = $response['anni'];       // es. [2022, 2023, 2024, 2025, 2026]
$dati_db    = $response['dati'];       // dati[anno] = [{d,mx,mn}, ...]
$oggi       = $response['oggi'];       // "YYYY-MM-DD"

// Serializza i dati per il JS in modo sicuro
// Struttura: { 2022: [{d:"2022-01-01", mx:12.3, mn:4.1}, ...], ... }
$dati_js = json_encode($dati_db, JSON_UNESCAPED_UNICODE);
$anni_js  = json_encode($anni);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grafico Soglie Termiche</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            background: #fff;
            padding: 6px 0 10px 0;
        }

        /* ---- barra cima (speculare a tabella_stat2) ---- */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 95%;
            margin: 4px auto 8px auto;
            gap: 6px;
        }
        .btn-torna {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 10px;
            font-weight: bold;
            cursor: pointer;
            padding: 4px 10px;
            border-radius: 3px;
            border: 2px solid black;
            background: transparent;
            color: black;
            user-select: none;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .btn-torna:hover { border-color: #3366cc; color: #3366cc; }

        /* ---- slider controls ---- */
        .controls-wrap {
            max-width: 95%;
            margin: 0 auto 10px auto;
        }
        .soglia-group {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 8px;
        }
        .soglia-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        .soglia-title {
            font-size: 10px;
            font-weight: bold;
            color: #444;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .soglia-op {
            font-size: 10px;
            padding: 2px 4px;
            border: 1px solid #ccc;
            border-radius: 3px;
            background: #fff;
            color: #333;
            cursor: pointer;
        }
        .ctrl-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }
        .ctrl-dot {
            width: 10px;
            height: 10px;
            border-radius: 2px;
            flex-shrink: 0;
        }
        .ctrl-label {
            font-size: 10px;
            color: #555;
            white-space: nowrap;
            min-width: 44px;
        }
        .ctrl-row input[type=range] {
            flex: 1;
            min-width: 0;
            height: 20px;
            cursor: pointer;
            -webkit-appearance: none;
            appearance: none;
            background: transparent;
        }
        .ctrl-row input[type=range]::-webkit-slider-runnable-track {
            height: 4px;
            background: #ddd;
            border-radius: 2px;
        }
        .ctrl-row input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #555;
            margin-top: -7px;
            cursor: pointer;
        }
        .ctrl-row input[type=range]::-moz-range-track {
            height: 4px;
            background: #ddd;
            border-radius: 2px;
        }
        .ctrl-row input[type=range]::-moz-range-thumb {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #555;
            border: none;
            cursor: pointer;
        }
        .ctrl-val {
            font-size: 11px;
            font-weight: bold;
            min-width: 28px;
            text-align: right;
        }
        .btn-reset {
            font-size: 15px;
            cursor: pointer;
            opacity: 0.5;
            padding: 2px 4px;
            border: none;
            background: none;
            color: #333;
            line-height: 1;
        }
        .btn-reset:hover { opacity: 1; color: #c00; }

        @media (max-width: 480px) {
            .ctrl-dot { width: 7px; height: 7px; }
            .ctrl-label { font-size: 8px; min-width: 36px; }
            .ctrl-val { font-size: 8px; min-width: 20px; }
            .ctrl-row input[type=range]::-webkit-slider-runnable-track { height: 3px; }
            .ctrl-row input[type=range]::-webkit-slider-thumb {
                width: 13px;
                height: 13px;
                margin-top: -5px;
            }
            .ctrl-row input[type=range]::-moz-range-track { height: 3px; }
            .ctrl-row input[type=range]::-moz-range-thumb {
                width: 13px;
                height: 13px;
            }
        }

        /* ---- tooltip ---- */
        .bar-tooltip {
            position: fixed;
            background: rgba(40,40,40,0.88);
            color: #fff;
            font-size: 11px;
            font-weight: bold;
            padding: 3px 7px;
            border-radius: 3px;
            pointer-events: none;
            white-space: nowrap;
            z-index: 9999;
            display: none;
        }

        /* ---- timeline ---- */
        .chart-wrap {
            max-width: 95%;
            margin: 0 auto;
        }
        .year-block { margin-bottom: 14px; }
        .year-label {
            font-size: 11px;
            font-weight: bold;
            color: #666;
            margin-bottom: 3px;
        }
        .bar-wrap {
            position: relative;
            height: 32px;
            background: #f0f0f0;
            border-radius: 3px;
            overflow: hidden;
        }
        .bar-wrap::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(255,255,255,0.6);
            pointer-events: none;
            z-index: 1;
        }
        .seg {
            position: absolute;
            top: 0;
            height: 100%;
        }

        /* ---- asse mesi ---- */
        .axis {
            position: relative;
            max-width: 95%;
            margin: 4px auto 10px auto;
            height: 14px;
        }
        .axis span {
            position: absolute;
            font-size: 10px;
            color: #aaa;
            white-space: nowrap;
        }

        /* ---- asse mesi per anno ---- */
        .axis-row {
            display: flex;
            justify-content: space-between;
            margin: 2px 0 10px 0;
        }
        .axis-row span { font-size: 9px; color: #bbb; }

        /* ---- card statistiche per anno ---- */
        .stats-anno {
            max-width: 95%;
            margin: 0 auto 12px auto;
        }
        .stats-anno-title {
            font-size: 11px;
            font-weight: bold;
            color: #444;
            margin-bottom: 5px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
        }
        .stat-card {
            background: #f7f7f7;
            border-radius: 4px;
            padding: 7px 8px;
            text-align: center;
        }
        .stat-card .val {
            font-size: 20px;
            font-weight: bold;
            line-height: 1.1;
        }
        .stat-card .lbl {
            font-size: 9px;
            color: #888;
            margin-top: 2px;
            line-height: 1.3;
        }

        /* ---- footer ---- */
        .stat-footer {
            text-align: center;
            font-size: 9px;
            color: #bbb;
            margin-top: 6px;
        }

        @media (min-width: 600px) {
            .ctrl-label { font-size: 12px; }
            .ctrl-val   { font-size: 12px; }
            .year-label { font-size: 12px; }
            .bar-wrap   { height: 36px; }
            .stat-card .val { font-size: 22px; }
            .stat-card .lbl { font-size: 10px; }
            .btn-torna { font-size: 13px; }
        }
    </style>
</head>
<body>

<!-- BARRA CIMA -->
<div class="top-bar">
    <button class="btn-torna" id="btn-torna">&#8592; Tabella</button>
    <div style="display:flex;align-items:center;gap:8px;">
        <span style="font-size:10px;color:#aaa;" id="lbl-oggi">aggiornato al <?= htmlspecialchars($oggi) ?></span>
        <button class="btn-reset" id="btn-reset" title="Ripristina valori default">&#8635;</button>
    </div>
</div>

<!-- SLIDER CONTROLLI -->
<div class="controls-wrap">
    <div class="soglia-group">
        <div class="soglia-header">
            <span class="soglia-title">Massime</span>
            <select class="soglia-op" id="op-max">
                <option value=">=">&gt;=</option>
                <option value="<=">&lt;=</option>
            </select>
        </div>
        <div class="ctrl-row">
            <span class="ctrl-dot" style="background:#F09595;"></span>
            <span class="ctrl-label" id="lbl-s1">&#8805;&nbsp;<span id="v1"><?= $def_s1 ?></span>&#176;C</span>
            <input type="range" id="s1" min="8" max="45" step="1" value="<?= $def_s1 ?>">
            <span class="ctrl-val" id="cv1" style="color:#E24B4A;"><?= $def_s1 ?>&#176;</span>
        </div>
        <div class="ctrl-row">
            <span class="ctrl-dot" style="background:#A32D2D;"></span>
            <span class="ctrl-label" id="lbl-s2">&#8805;&nbsp;<span id="v2"><?= $def_s2 ?></span>&#176;C</span>
            <input type="range" id="s2" min="8" max="45" step="1" value="<?= $def_s2 ?>">
            <span class="ctrl-val" id="cv2" style="color:#A32D2D;"><?= $def_s2 ?>&#176;</span>
        </div>
    </div>
    <div class="soglia-group">
        <div class="soglia-header">
            <span class="soglia-title">Minime</span>
            <select class="soglia-op" id="op-min">
                <option value="<=">&lt;=</option>
                <option value=">=">&gt;=</option>
            </select>
        </div>
        <div class="ctrl-row">
            <span class="ctrl-dot" style="background:#85B7EB;"></span>
            <span class="ctrl-label" id="lbl-s3">&#8804;&nbsp;<span id="v3"><?= $def_s3 ?></span>&#176;C</span>
            <input type="range" id="s3" min="-5" max="20" step="1" value="<?= $def_s3 ?>">
            <span class="ctrl-val" id="cv3" style="color:#378ADD;"><?= $def_s3 ?>&#176;</span>
        </div>
        <div class="ctrl-row">
            <span class="ctrl-dot" style="background:#0C447C;"></span>
            <span class="ctrl-label" id="lbl-s4">&#8804;&nbsp;<span id="v4"><?= $def_s4 ?></span>&#176;C</span>
            <input type="range" id="s4" min="-5" max="20" step="1" value="<?= $def_s4 ?>">
            <span class="ctrl-val" id="cv4" style="color:#0C447C;"><?= $def_s4 ?>&#176;</span>
        </div>
    </div>
</div>

<div id="bar-tooltip" class="bar-tooltip"></div>

<!-- TIMELINE (mesi inclusi dentro ogni anno via JS) -->
<div class="chart-wrap" id="chart-wrap"></div>

<!-- STATISTICHE PER ANNO -->
<div id="stats-anni"></div>

<div class="stat-footer" id="footer-note"></div>

<script>
var DATI = <?= $dati_js ?>;
var ANNI = <?= $anni_js ?>;

// Giorni per mese (anno non bisestile - approssimazione per la posizione %)
var MESE_GG = [31,28,31,30,31,30,31,31,30,31,30,31];
var GG_ANNO = 365;

// Calcola la posizione % di una data rispetto all'inizio dell'anno
function dataToPct(dateStr) {
    var p = dateStr.split('-');
    var m = parseInt(p[1]) - 1; // 0-based
    var d = parseInt(p[2]);
    var gg = 0;
    for (var i = 0; i < m; i++) gg += MESE_GG[i];
    gg += d - 1;
    return (gg / GG_ANNO * 100).toFixed(3) + '%';
}

// Colore del giorno in base alle 4 soglie
// Priorita': s2 > s1 per il caldo; s4 > s3 per il freddo
function coloreMassima(mx, s1, s2) {
    if (mx === null) return '#e0e0e0';
    if (mx >= s2) return '#A32D2D';
    if (mx >= s1) return '#F09595';
    return null;
}

function coloreMinima(mn, s3, s4) {
    if (mn === null) return '#e0e0e0';
    if (mn <= s4) return '#0C447C';
    if (mn <= s3) return '#85B7EB';
    return null;
}

function render() {
    var s1 = parseInt(document.getElementById('s1').value);
    var s2 = parseInt(document.getElementById('s2').value);
    var s3 = parseInt(document.getElementById('s3').value);
    var s4 = parseInt(document.getElementById('s4').value);
    var opMax = document.getElementById('op-max').value;
    var opMin = document.getElementById('op-min').value;

    // Simboli operatori per le etichette
    var symMax = (opMax === '>=') ? '\u2265' : '\u2264';
    var symMin = (opMin === '<=') ? '\u2264' : '\u2265';

    // Aggiorna etichette slider con operatore corretto
    document.getElementById('lbl-s1').innerHTML = symMax + '&nbsp;<span id="v1">' + s1 + '</span>&#176;C';
    document.getElementById('lbl-s2').innerHTML = symMax + '&nbsp;<span id="v2">' + s2 + '</span>&#176;C';
    document.getElementById('lbl-s3').innerHTML = symMin + '&nbsp;<span id="v3">' + s3 + '</span>&#176;C';
    document.getElementById('lbl-s4').innerHTML = symMin + '&nbsp;<span id="v4">' + s4 + '</span>&#176;C';
    document.getElementById('cv1').textContent = s1 + '\u00b0';
    document.getElementById('cv2').textContent = s2 + '\u00b0';
    document.getElementById('cv3').textContent = s3 + '\u00b0';
    document.getElementById('cv4').textContent = s4 + '\u00b0';

    // Conflitto gerarchia slider
    var conflitto_caldo  = (opMax === '>=') ? (s1 >= s2) : (s1 <= s2);
    var conflitto_freddo = (opMin === '<=') ? (s3 <= s4) : (s3 >= s4);
    var sliderS1 = document.getElementById('s1');
    var sliderS3 = document.getElementById('s3');
    sliderS1.style.opacity = conflitto_caldo  ? '0.35' : '1';
    sliderS3.style.opacity = conflitto_freddo ? '0.35' : '1';
    sliderS1.parentNode.querySelector('.ctrl-dot').style.background = conflitto_caldo  ? '#bbb' : '#F09595';
    sliderS3.parentNode.querySelector('.ctrl-dot').style.background = conflitto_freddo ? '#bbb' : '#85B7EB';
    document.getElementById('cv1').style.color = conflitto_caldo  ? '#bbb' : '#E24B4A';
    document.getElementById('cv3').style.color = conflitto_freddo ? '#bbb' : '#378ADD';
    // Se in conflitto: s1 assume il valore di s2, s3 assume il valore di s4
    // così entrambe le fasce mostrano solo il colore scuro
    if (conflitto_caldo)  s1 = s2;
    if (conflitto_freddo) s3 = s4;

    var oggiStr = '<?= $oggi ?>';
    var wrap = document.getElementById('chart-wrap');
    wrap.innerHTML = '';
    var MESI_NOMI_SHORT = ['Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'];

    // Conteggi per anno: struttura { anno: [c1,c2,c3,c4] }
    var conteggioAnni = {};

    ANNI.forEach(function(anno) {
        var giorni = DATI[anno];
        if (!giorni || giorni.length === 0) return;

        var block = document.createElement('div');
        block.className = 'year-block';

        var lbl = document.createElement('div');
        lbl.className = 'year-label';
        lbl.textContent = anno;
        block.appendChild(lbl);

        var bar = document.createElement('div');
        bar.className = 'bar-wrap';

        var idx = {};
        for (var i = 0; i < giorni.length; i++) {
            idx[giorni[i].d] = giorni[i];
        }

        function costruisciFascia(fnColore, isTop) {
            var segs = [];
            var corrColore = null;
            var corrStart  = null;
            for (var m = 0; m < 12; m++) {
                for (var gg = 1; gg <= MESE_GG[m]; gg++) {
                    var ds = anno + '-'
                        + (m + 1 < 10 ? '0' : '') + (m + 1) + '-'
                        + (gg < 10 ? '0' : '') + gg;
                    if (ds > oggiStr && anno === (oggiStr.substring(0,4)|0)) break;
                    var rec = idx[ds] || null;
                    var c   = fnColore(rec);
                    if (c !== corrColore) {
                        if (corrStart !== null) segs.push({ colore: corrColore, da: corrStart, a: ds });
                        corrColore = c;
                        corrStart  = ds;
                    }
                }
                if (anno === (oggiStr.substring(0,4)|0) && corrStart > oggiStr) break;
            }
            if (corrStart !== null) segs.push({ colore: corrColore, da: corrStart, a: null });
            segs.forEach(function(s) {
                if (!s.colore) return;
                var el = document.createElement('div');
                el.className = 'seg';
                el.style.top    = isTop ? '0' : '50%';
                el.style.height = '50%';
                el.style.left   = dataToPct(s.da);
                if (s.a) {
                    el.style.width = (parseFloat(dataToPct(s.a)) - parseFloat(dataToPct(s.da))).toFixed(3) + '%';
                } else {
                    el.style.width = (100 - parseFloat(dataToPct(s.da))).toFixed(3) + '%';
                }
                el.style.background = s.colore;
                bar.appendChild(el);
            });
        }

        // Funzioni colore con operatore
        costruisciFascia(function(rec) {
            if (!rec || rec.mx === null) return '#e0e0e0';
            var v = rec.mx;
            var ok2 = opMax === '>=' ? v >= s2 : v <= s2;
            var ok1 = opMax === '>=' ? v >= s1 : v <= s1;
            if (ok2) return '#A32D2D';
            if (ok1) return '#F09595';
            return null;
        }, true);

        costruisciFascia(function(rec) {
            if (!rec || rec.mn === null) return '#e0e0e0';
            var v = rec.mn;
            var ok4 = opMin === '<=' ? v <= s4 : v >= s4;
            var ok3 = opMin === '<=' ? v <= s3 : v >= s3;
            if (ok4) return '#0C447C';
            if (ok3) return '#85B7EB';
            return null;
        }, false);

        block.appendChild(bar);

        // Asse mesi sotto ogni barra
        var axisRow = document.createElement('div');
        axisRow.className = 'axis-row';
        MESI_NOMI_SHORT.forEach(function(nm) {
            var sp = document.createElement('span');
            sp.textContent = nm;
            axisRow.appendChild(sp);
        });
        block.appendChild(axisRow);
        wrap.appendChild(block);

        // Conteggi per questo anno
        var c1=0, c2=0, c3=0, c4=0;
        giorni.forEach(function(g) {
            var mx = g.mx, mn = g.mn;
            if (mx !== null) {
                if (opMax === '>=') {
                    // >= esclusivo: chi supera s2 non conta in s1
                    if (mx >= s2) c2++;
                    else if (mx >= s1) c1++;
                } else {
                    // <= inclusivo: ogni soglia conta indipendentemente
                    if (mx <= s2) c2++;
                    if (mx <= s1) c1++;
                }
            }
            if (mn !== null) {
                if (opMin === '<=') {
                    // <= esclusivo: chi scende sotto s4 non conta in s3
                    if (mn <= s4) c4++;
                    else if (mn <= s3) c3++;
                } else {
                    // >= inclusivo: ogni soglia conta indipendentemente
                    if (mn >= s4) c4++;
                    if (mn >= s3) c3++;
                }
            }
        });
        conteggioAnni[anno] = [c1, c2, c3, c4];
    });

    // Statistiche per anno
    var opMaxSym = opMax === '>=' ? '&gt;=' : '&lt;=';
    var opMinSym = opMin === '<=' ? '&lt;=' : '&gt;=';
    var statsEl = document.getElementById('stats-anni');
    statsEl.innerHTML = '';
    ANNI.forEach(function(anno) {
        var c = conteggioAnni[anno];
        if (!c) return;
        statsEl.innerHTML +=
            '<div class="stats-anno">'
            + '<div class="stats-anno-title">' + anno + '</div>'
            + '<div class="stats-grid">'
            + '<div class="stat-card"><div class="val" style="color:#A32D2D;">' + c[1] + '</div><div class="lbl">Max ' + opMaxSym + ' ' + s2 + '&#176;C</div></div>'
            + '<div class="stat-card"><div class="val" style="color:#F09595;">' + c[0] + '</div><div class="lbl">Max ' + opMaxSym + ' ' + s1 + '&#176;C</div></div>'
            + '<div class="stat-card"><div class="val" style="color:#85B7EB;">' + c[2] + '</div><div class="lbl">Min ' + opMinSym + ' ' + s3 + '&#176;C</div></div>'
            + '<div class="stat-card"><div class="val" style="color:#0C447C;">' + c[3] + '</div><div class="lbl">Min ' + opMinSym + ' ' + s4 + '&#176;C</div></div>'
            + '</div></div>';
    });

    document.getElementById('footer-note').textContent =
        ANNI.length + ' anni \u2022 dati: temp max / temp min giornaliera';

    sendResize();
    if (typeof aggiungiTooltipBarre === 'function') aggiungiTooltipBarre();
}

// Invia al padre (stat_display.php) l'altezza reale del contenuto
function sendResize() {
    var h = document.body.scrollHeight;
    window.parent.postMessage({
        action:    'resize',
        iframeId:  'stat-iframe-tab2',
        height:    h
    }, '*');
}

// Torna alla tabella
document.getElementById('btn-torna').addEventListener('click', function() {
    window.parent.postMessage({
        action:   'tornaTabella',
        iframeId: 'stat-iframe-tab2',
        src:      'tabella_stat2_display.php'
    }, '*');
});

// Collega slider a render() con clamp gerarchia
document.getElementById('s1').addEventListener('input', function() {
    var s2 = parseInt(document.getElementById('s2').value);
    var opMax = document.getElementById('op-max').value;
    var val = parseInt(this.value);
    if (opMax === '>=' && val >= s2) this.value = s2 - 1;
    if (opMax === '<=' && val <= s2) this.value = s2 + 1;
    render();
});
document.getElementById('s2').addEventListener('input', function() { render(); });
document.getElementById('s3').addEventListener('input', function() {
    var s4 = parseInt(document.getElementById('s4').value);
    var opMin = document.getElementById('op-min').value;
    var val = parseInt(this.value);
    if (opMin === '<=' && val <= s4) this.value = s4 + 1;
    if (opMin === '>=' && val >= s4) this.value = s4 - 1;
    render();
});
document.getElementById('s4').addEventListener('input', function() { render(); });

// Operatori
document.getElementById('op-max').addEventListener('change', render);
document.getElementById('op-min').addEventListener('change', render);

// Reset ai valori default
document.getElementById('btn-reset').addEventListener('click', function() {
    document.getElementById('s1').value = <?= $def_s1 ?>;
    document.getElementById('s2').value = <?= $def_s2 ?>;
    document.getElementById('s3').value = <?= $def_s3 ?>;
    document.getElementById('s4').value = <?= $def_s4 ?>;
    document.getElementById('op-max').value = '>=';
    document.getElementById('op-min').value = '<=';
    render();
});

// Converte percentuale X (0-100) in stringa "GGmmm"
// es. 0% -> "01 gen", 50% -> "02 lug"
var MESI_NOMI = ['gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
function pctToData(pct) {
    var gg_tot = Math.round(pct / 100 * GG_ANNO);
    gg_tot = Math.max(0, Math.min(GG_ANNO - 1, gg_tot));
    var m = 0, r = gg_tot;
    while (m < 11 && r >= MESE_GG[m]) { r -= MESE_GG[m]; m++; }
    return (r + 1 < 10 ? '0' : '') + (r + 1) + ' ' + MESI_NOMI[m];
}

var tooltip = document.getElementById('bar-tooltip');

function mostraTooltip(e, bar) {
    var rect = bar.getBoundingClientRect();
    var clientX, clientY;
    if (e.touches) {
        clientX = e.touches[0].clientX;
        clientY = e.touches[0].clientY;
    } else {
        clientX = e.clientX;
        clientY = e.clientY;
    }
    var pct = Math.max(0, Math.min(100, (clientX - rect.left) / rect.width * 100));
    tooltip.textContent = pctToData(pct);
    tooltip.style.display = 'block';
    // Posiziona sopra il cursore, centrato orizzontalmente
    tooltip.style.left = (clientX - tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top  = (clientY - 28) + 'px';
}

function nascondiTooltip() {
    tooltip.style.display = 'none';
}

// Aggiunge i listener su ogni bar-wrap dopo render()
function aggiungiTooltipBarre() {
    document.querySelectorAll('.bar-wrap').forEach(function(bar) {
        bar.addEventListener('mousemove',  function(e) { mostraTooltip(e, bar); });
        bar.addEventListener('mouseleave', nascondiTooltip);
        bar.addEventListener('touchmove',  function(e) {
            e.preventDefault(); // evita scroll accidentale
            mostraTooltip(e, bar);
        }, { passive: false });
        bar.addEventListener('touchend',   nascondiTooltip);
    });
}

// Render iniziale
render();
aggiungiTooltipBarre();

// Secondo resize dopo che il DOM si e' stabilizzato
// (necessario su mobile dove il layout si aggiusta dopo il paint)
setTimeout(sendResize, 150);
setTimeout(sendResize, 500);
</script>

</body>
</html>