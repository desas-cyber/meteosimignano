<?php
/**
 * ============================================================================
 * GRAFICO SPI - grafico2_pluvio_display.php
 * ============================================================================
 * Secondo iframe di pluvio_stat_display.php: monitoraggio siccita' con
 * l'indice SPI a 3 e a 6 mesi.
 *
 * Per ciascuna scala (finestra a mesi di calendario che termina con il mese
 * corrente, il mese in corso e' sempre l'ultimo):
 *   - ventaglio blu p10-p90 / p25-p75 + mediana della pioggia CUMULATA
 *     dall'inizio della finestra, giorno per giorno, sugli anni storici
 *   - linea rossa = cumulata dell'anno in corso, con PALLINO PULSANTE oggi
 *   - tratteggio rosso = proiezione se da oggi cadesse la pioggia mediana
 *   - termometro SPI a destra con le classi WMO e secondo pallino pulsante
 *   - barra di avanzamento nella finestra e nel mese in corso
 *   - striscia per anno solare su due righe (anno corrente e precedente,
 *     gen..dic): mese in corso tratteggiato rosso, futuri solo contorno,
 *     dati incompleti a righe diagonali
 *
 * Comunicazione con la pagina contenitore via postMessage (action: resize).
 * iframeId: stat-iframe-pluvio2
 */

ini_set('display_errors', 0);
error_reporting(0);

// envelop.php fornisce $pdo (lettura+scrittura): serve alla cache giornaliera.
require_once __DIR__ . '/../envelop.php';
require_once __DIR__ . '/api/api_spi_data.php';

$response = getSpiData();

if (!$response['success']) {
    echo "<div style='font-family:Arial;font-size:13px;color:#c00;padding:10px;'>
            Errore: " . htmlspecialchars($response['error'] ?? 'Dati non disponibili') . "
          </div>";
    exit;
}

$meta      = $response['meta'];
$scale     = $response['scale'];
$scale_js  = json_encode($scale, JSON_UNESCAPED_UNICODE);
$meta_js   = json_encode($meta,  JSON_UNESCAPED_UNICODE);
$oggi_disp = (new DateTime($meta['oggi']))->format('d/m/Y');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indice SPI</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            background: #fff;
            padding: 6px 0 10px 0;
        }
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 95%;
            margin: 4px auto 4px auto;
            gap: 6px;
            flex-wrap: wrap;
        }
        .titolo { font-size: 12px; font-weight: bold; color: #555; }
        .pannello { max-width: 95%; margin: 10px auto 0 auto; }
        .pannello + .pannello { margin-top: 22px; border-top: 1px dashed rgba(128,128,128,0.2); padding-top: 12px; }
        .pan-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 4px;
        }
        .pan-tit { font-size: 11px; font-weight: bold; color: #555; }
        .badge {
            font-size: 10px;
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 3px;
            white-space: nowrap;
        }
        .chart-container { position: relative; width: 100%; }
        canvas { display: block; width: 100% !important; }
        .pulse {
            position: absolute;
            width: 12px; height: 12px;
            margin: -6px 0 0 -6px;
            border-radius: 50%;
            background: #D62828;
            color: #D62828;
            border: 2px solid #fff;
            display: none;
            pointer-events: none;
            z-index: 2;
        }
        .pulse::after {
            content: "";
            position: absolute;
            inset: -2px;
            border-radius: 50%;
            border: 2px solid currentColor;
            animation: pulsa 1.6s ease-out infinite;
        }
        @keyframes pulsa {
            0%   { transform: scale(1);   opacity: 0.9; }
            100% { transform: scale(3.2); opacity: 0; }
        }
        @media (prefers-reduced-motion: reduce) {
            .pulse::after { animation: none; opacity: 0; }
        }
        .prog-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 2px;
            font-size: 10px;
            color: #666;
            flex-wrap: wrap;
        }
        .prog-track {
            position: relative;
            width: 110px;
            height: 6px;
            background: rgba(214,40,40,0.18);
            border-radius: 3px;
            overflow: hidden;
            flex-shrink: 0;
        }
        .prog-fill { position: absolute; left: 0; top: 0; bottom: 0; background: #D62828; }
        .prog-txt2 { font-size: 10px; color: #999; margin-top: 2px; }
        .striscia { display: flex; flex-direction: column; gap: 4px; margin-top: 8px; }
        .str-riga { display: flex; align-items: center; gap: 6px; }
        .str-anno { width: 34px; flex-shrink: 0; font-size: 9px; font-weight: bold; color: #888; text-align: right; }
        .str-riga.prec .str-anno { font-weight: normal; color: #aaa; }
        .str-celle { flex: 1; display: flex; gap: 3px; }
        .cella { flex: 1; text-align: center; font-size: 9px; color: #999; cursor: default; }
        .cella .q { height: 12px; border-radius: 2px; }
        .str-riga.prec .cella .q { height: 9px; }
        /* mese futuro: solo contorno tratteggiato leggero, nessun riempimento */
        .cella .q.futuro   { background: transparent; box-shadow: inset 0 0 0 1px #d6d6d6; border-radius: 2px;
                             background-image: none; outline: none; border: 1px dashed #cfcfcf; box-sizing: border-box; box-shadow: none; }
        /* dati incompleti: righe diagonali, distinte sia dal grigio "nella norma" sia dal futuro */
        .cella .q.mancante { background: repeating-linear-gradient(45deg, #fff 0 3px, #c9c9c9 3px 5px);
                             box-shadow: inset 0 0 0 1px #c9c9c9; }
        .cella.corr { color: #D62828; font-weight: bold; }
        .cella.corr .q { outline: 1.5px dashed #D62828; outline-offset: 1px; }
        .legend {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            max-width: 95%;
            margin: 14px auto 0 auto;
            font-size: 10px;
            color: #666;
            align-items: center;
        }
        .leg-item { display: flex; align-items: center; gap: 4px; }
        .leg-box  { width: 12px; height: 9px; border-radius: 1px; flex-shrink: 0; }
        .leg-line { width: 18px; height: 2px; flex-shrink: 0; }
        .stat-footer {
            text-align: center;
            font-size: 9px;
            color: #bbb;
            margin-top: 6px;
            max-width: 95%;
            margin-left: auto;
            margin-right: auto;
        }
        .chart-tooltip {
            position: fixed;
            background: rgba(40,40,40,0.90);
            color: #fff;
            font-size: 11px;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 3px;
            pointer-events: none;
            white-space: nowrap;
            z-index: 9999;
            display: none;
            line-height: 1.5;
        }
        @media (max-width: 480px) {
            .legend { gap: 8px; font-size: 9px; }
            .leg-opt { display: none; }
            .badge { font-size: 9px; padding: 2px 6px; }
        }
        @media (min-width: 600px) {
            .titolo   { font-size: 14px; }
            .pan-tit  { font-size: 13px; }
            .badge    { font-size: 11px; }
            .legend   { font-size: 11px; }
            .prog-row { font-size: 11px; }
            .prog-txt2{ font-size: 11px; }
            .cella    { font-size: 10px; }
        }
    </style>
</head>
<body>

<div class="top-bar">
    <span class="titolo">Siccit&agrave;: indice SPI al <?= $oggi_disp ?></span>
</div>

<div id="chart-tooltip" class="chart-tooltip"></div>

<?php foreach ($scale as $i => $s): ?>
<div class="pannello">
    <div class="pan-head">
        <span class="pan-tit"><?= htmlspecialchars($s['label']) ?> &middot; <?= htmlspecialchars($s['finestra']) ?></span>
        <span class="badge" id="badge-<?= $i ?>"></span>
    </div>
    <div class="chart-container" id="cont-<?= $i ?>">
        <canvas id="chart-<?= $i ?>" role="img"
                aria-label="<?= htmlspecialchars($s['label']) ?>: pioggia cumulata nella finestra <?= htmlspecialchars($s['finestra']) ?> confrontata con i percentili storici"></canvas>
        <div class="pulse" id="pulse-<?= $i ?>a"></div>
        <div class="pulse" id="pulse-<?= $i ?>b"></div>
    </div>
    <div class="prog-row">
        <div class="prog-track"><div class="prog-fill" id="fill-<?= $i ?>"></div></div>
        <span id="prog-<?= $i ?>"></span>
    </div>
    <div class="prog-txt2" id="prog2-<?= $i ?>"></div>
    <div class="striscia" id="strip-<?= $i ?>"></div>
</div>
<?php endforeach; ?>

<div class="legend">
    <span class="leg-item"><span class="leg-box" style="background:rgba(55,138,221,0.24);border:1px solid rgba(55,138,221,0.45);"></span>p10&ndash;p90</span>
    <span class="leg-item"><span class="leg-box" style="background:rgba(55,138,221,0.47);"></span>p25&ndash;p75</span>
    <span class="leg-item"><span class="leg-line" style="background:#2B6DB3;"></span>mediana</span>
    <span class="leg-item"><span class="leg-line" style="background:#D62828;height:3px;"></span>cumulata attuale</span>
    <span class="leg-item leg-opt"><span style="display:inline-block;width:18px;border-top:2px dashed rgba(214,40,40,0.55);"></span>proiezione con pioggia mediana</span>
    <span class="leg-item leg-opt"><span class="leg-box" style="background:#FDAE61;outline:1.5px dashed #D62828;outline-offset:1px;"></span>mese in corso (parziale)</span>
    <span class="leg-item"><span class="leg-box" style="background:repeating-linear-gradient(45deg,#fff 0 3px,#c9c9c9 3px 5px);box-shadow:inset 0 0 0 1px #c9c9c9;"></span>dati incompleti</span>
    <span class="leg-item"><span class="leg-box" style="border:1px dashed #cfcfcf;box-sizing:border-box;"></span>mese futuro</span>
</div>

<div class="stat-footer" id="footer-note"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
var SCALE = <?= $scale_js ?>;
var META  = <?= $meta_js ?>;

var MOBILE = false;
var charts = [];

var COL = {
    fanLargo:  'rgba(55,138,221,0.24)',
    fanStretto:'rgba(55,138,221,0.30)',   // sovrapposto al largo: nel centro somma circa 0.47
    fanBordo:  'rgba(55,138,221,0.45)',
    mediana:   '#2B6DB3',
    corr:      '#D62828',
    corrProj:  'rgba(214,40,40,0.55)',
    futuro:    'rgba(128,128,128,0.06)',
    testo:     '#999'
};

// Classi SPI (WMO): [da, a, sfondo, testo, etichetta]
var CLASSI = [
    [-99, -2,   '#8C2D04', '#fff',    'estremamente siccitoso'],
    [-2,  -1.5, '#D95F0E', '#fff',    'severamente siccitoso'],
    [-1.5, -1,  '#FDAE61', '#6b3a00', 'moderatamente siccitoso'],
    [-1,   1,   '#D9D9D9', '#444',    'nella norma'],
    [1,    1.5, '#9ECAE1', '#08306b', 'moderatamente umido'],
    [1.5,  2,   '#4292C6', '#fff',    'molto umido'],
    [2,    99,  '#08519C', '#fff',    'estremamente umido']
];
function classe(s) {
    if (s == null) return null;
    for (var i = 0; i < CLASSI.length; i++) {
        if (s >= CLASSI[i][0] && s < CLASSI[i][1]) return CLASSI[i];
    }
    return CLASSI[3];
}
function fmtSpi(s) {
    if (s == null) return 'n/d';
    return (s > 0 ? '+' : s < 0 ? '\u2212' : '') + Math.abs(s).toFixed(2);
}
function fmtMm(v) { return (Math.round(v * 10) / 10) + ' mm'; }

// Stesso arrotondamento degli assi di grafico_pluvio_display.php
function niceMax(v) {
    if (v <= 0) return 10;
    var grezzo = v / 5;
    var mag    = Math.pow(10, Math.floor(Math.log10(grezzo)));
    var r      = grezzo / mag;
    var passo  = (r <= 1 ? 1 : r <= 2 ? 2 : r <= 2.5 ? 2.5 : r <= 5 ? 5 : 10) * mag;
    var max    = Math.ceil(v / passo) * passo;
    if (max - v < passo * 0.15) max += passo;
    return max;
}

// Data del giorno i (1..N) della finestra, per il tooltip
var MESI_BREVI = ['gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
function dataGiorno(s, i) {
    var p = s.inizio.split('-');
    var d = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2] + Math.max(i, 1) - 1));
    return d.getUTCDate() + ' ' + MESI_BREVI[d.getUTCMonth()];
}

// ---------------------------------------------------------------------------
// Plugin di disegno (uno per pannello, legge options.plugins.spiPlugin)
// ---------------------------------------------------------------------------
var spiPlugin = {
    id: 'spiPlugin',
    afterDatasetsDraw: function(chart) {
        var opt = chart.options.plugins.spiPlugin;
        if (!opt) return;
        var idx = opt.idx;
        var s   = SCALE[idx];
        var c   = s.correnti;
        var ctx = chart.ctx;
        var ca  = chart.chartArea;
        var xp  = function(v) { return chart.scales.x.getPixelForValue(v); };
        var yp  = function(v) { return chart.scales.y.getPixelForValue(v); };
        var N = s.N, T = s.T, fan = s.fan;
        var hit = chart.$hit = { gauge: null, dot: null };

        var haClima = fan.p50[N] != null;

        // ---- zona futura (da oggi a fine finestra) ----
        ctx.fillStyle = COL.futuro;
        ctx.fillRect(xp(T), ca.top, xp(N) - xp(T), ca.bottom - ca.top);

        // ---- ventaglio ----
        function banda(a, b, colore) {
            ctx.beginPath();
            for (var i = 0; i <= N; i++) ctx.lineTo(xp(i), yp(fan[a][i]));
            for (var j = N; j >= 0; j--) ctx.lineTo(xp(j), yp(fan[b][j]));
            ctx.closePath();
            ctx.fillStyle = colore;
            ctx.fill();
        }
        if (haClima) {
            banda('p10', 'p90', COL.fanLargo);
            banda('p25', 'p75', COL.fanStretto);
            // bordo sottile su p10 e p90: il limite esterno resta leggibile anche su schermi chiari
            ['p10', 'p90'].forEach(function(b) {
                ctx.strokeStyle = COL.fanBordo;
                ctx.lineWidth = 1;
                ctx.beginPath();
                for (var i = 0; i <= N; i++) ctx.lineTo(xp(i), yp(fan[b][i]));
                ctx.stroke();
            });
            ctx.strokeStyle = COL.mediana;
            ctx.lineWidth = 2;
            ctx.beginPath();
            for (var i = 0; i <= N; i++) ctx.lineTo(xp(i), yp(fan.p50[i]));
            ctx.stroke();
        }

                // ---- separatori ed etichette dei mesi (etichetta sul 1° del mese) ----
        var acc = 0;
        ctx.font = 'bold 9px Arial';
        ctx.textAlign = 'left';
        ctx.textBaseline = 'top';
        s.mesi.forEach(function(m, k) {
            var x0 = xp(acc);   // confine = inizio del giorno 1 del mese
            if (k > 0) {
                ctx.strokeStyle = 'rgba(128,128,128,0.85)';
                ctx.lineWidth = 1;
                ctx.setLineDash([3, 3]);
                ctx.beginPath();
                ctx.moveTo(x0, ca.top);
                ctx.lineTo(x0, ca.bottom);
                ctx.stroke();
                ctx.setLineDash([]);
            }
            // tacca sotto l'asse in corrispondenza del 1° del mese
            ctx.strokeStyle = 'rgba(128,128,128,0.85)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(x0, ca.bottom);
            ctx.lineTo(x0, ca.bottom + 4);
            ctx.stroke();

            ctx.fillStyle = (k === s.mesi.length - 1) ? COL.corr : COL.testo;
            ctx.fillText(m.nome, x0 + 2, ca.bottom + 4);
            acc += m.giorni;
        });

        // ---- proiezione (tratteggio + cerchietto vuoto a fine finestra) ----
        if (haClima && T < N) {
            ctx.strokeStyle = COL.corrProj;
            ctx.lineWidth = 2;
            ctx.setLineDash([5, 4]);
            ctx.beginPath();
            for (var d = T; d <= N; d++) {
                ctx.lineTo(xp(d), yp(c.mm + (fan.p50[d] - fan.p50[T]) * (s.incr_mediana / Math.max(0.1, fan.p50[N] - fan.p50[T]))));
            }
            ctx.stroke();
            ctx.setLineDash([]);
            ctx.beginPath();
            ctx.arc(xp(N), yp(c.proj_mm), 4, 0, Math.PI * 2);
            ctx.fillStyle = '#fff'; ctx.fill();
            ctx.strokeStyle = COL.corrProj; ctx.lineWidth = 1.5; ctx.stroke();
        }

        // ---- cumulata attuale ----
        ctx.strokeStyle = COL.corr;
        ctx.lineWidth = 2.2;
        ctx.lineJoin = 'round';
        ctx.beginPath();
        for (var g = 0; g <= T; g++) ctx.lineTo(xp(g), yp(c.serie[g]));
        ctx.stroke();

        var dotX = xp(T), dotY = yp(c.mm);
        if (!MOBILE) {
            ctx.font = 'bold 9px Arial';
            ctx.textBaseline = 'bottom';
            ctx.fillStyle = COL.corr;
            ctx.textAlign = 'right';
            ctx.fillText(Math.round(c.mm) + ' mm', dotX - 9, dotY - 5);
            if (haClima && Math.abs(yp(fan.p50[T]) - dotY) > 14) {
                ctx.fillStyle = COL.mediana;
                ctx.textAlign = 'left';
                ctx.fillText('med ' + Math.round(fan.p50[T]), dotX + 8, yp(fan.p50[T]) - 3);
            }
        }
        hit.dot = { x: dotX, y: dotY };

        // ---- termometro SPI nel margine destro ----
        var gW  = opt.gaugeW;
        var gw  = MOBILE ? 10 : 14;
        var gx  = ca.right + gW * (MOBILE ? 0.42 : 0.40);
        var sMin = -2.6, sMax = 2.6;
        function ys(v) {
            v = Math.max(sMin, Math.min(sMax, v));
            return ca.bottom - (v - sMin) / (sMax - sMin) * (ca.bottom - ca.top);
        }
        CLASSI.forEach(function(cl) {
            var a = Math.max(cl[0], sMin), b = Math.min(cl[1], sMax);
            if (a >= b) return;
            ctx.fillStyle = cl[2];
            ctx.fillRect(gx, ys(b), gw, ys(a) - ys(b));
        });
        ctx.font = '9px Arial';
        ctx.fillStyle = COL.testo;
        ctx.textBaseline = 'middle';
        var tacche = MOBILE ? [[-2, 2], [0, 50], [2, 98]] : [[-2, 2], [-1, 16], [0, 50], [1, 84], [2, 98]];
        tacche.forEach(function(t) {
            ctx.textAlign = 'right';
            ctx.fillText((t[0] > 0 ? '+' : '') + t[0], gx - 4, ys(t[0]));
            ctx.textAlign = 'left';
            ctx.fillText('p' + t[1], gx + gw + 4, ys(t[0]));
        });
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        ctx.font = 'bold 9px Arial';
        ctx.fillText('SPI', gx + gw / 2, ca.bottom + 4);
        hit.gauge = { x0: gx - 22, x1: gx + gw + 26, y0: ca.top, y1: ca.bottom };

        var p1 = document.getElementById('pulse-' + idx + 'a');
        var p2 = document.getElementById('pulse-' + idx + 'b');
        p1.style.left = dotX + 'px'; p1.style.top = dotY + 'px'; p1.style.display = 'block';

        if (c.spi != null) {
            if (c.proj_spi != null && T < N) {
                ctx.beginPath();
                ctx.arc(gx + gw / 2, ys(c.proj_spi), 4, 0, Math.PI * 2);
                ctx.fillStyle = '#fff'; ctx.fill();
                ctx.strokeStyle = COL.corrProj; ctx.lineWidth = 1.5; ctx.stroke();
            }
            // raccordo pallino -> termometro
            ctx.strokeStyle = 'rgba(214,40,40,0.30)';
            ctx.lineWidth = 1;
            ctx.setLineDash([2, 3]);
            ctx.beginPath();
            ctx.moveTo(dotX + 8, dotY);
            ctx.lineTo(gx - (MOBILE ? 16 : 22), ys(c.spi));
            ctx.stroke();
            ctx.setLineDash([]);

            p2.style.left = (gx + gw / 2) + 'px';
            p2.style.top  = ys(c.spi) + 'px';
            p2.style.display = 'block';
        } else {
            p2.style.display = 'none';
        }

        // ---- separatore fra area grafico e termometro ----
        ctx.strokeStyle = 'rgba(128,128,128,0.12)';
        ctx.setLineDash([3, 3]);
        ctx.beginPath();
        ctx.moveTo(ca.right + 4, ca.top);
        ctx.lineTo(ca.right + 4, ca.bottom);
        ctx.stroke();
        ctx.setLineDash([]);
    }
};

// ---------------------------------------------------------------------------
// Parti HTML del pannello: badge, avanzamento, striscia
// ---------------------------------------------------------------------------
function riempiPannello(idx) {
    var s = SCALE[idx], c = s.correnti;

    var cl    = classe(c.spi);
    var badge = document.getElementById('badge-' + idx);
    if (cl) {
        badge.style.background = cl[2];
        badge.style.color      = cl[3];
        badge.textContent = 'p' + Math.round(c.percentile) + ' \u00b7 SPI ' + fmtSpi(c.spi)
                          + (MOBILE ? '' : ' \u00b7 ' + cl[4]);
    } else {
        badge.style.background = '#eee';
        badge.style.color      = '#666';
        badge.textContent = 'dati storici insufficienti';
    }

    document.getElementById('fill-' + idx).style.width = (s.T / s.N * 100) + '%';
    document.getElementById('prog-' + idx).textContent = MOBILE
        ? 'gg ' + s.T + '/' + s.N + ' \u00b7 mese ' + s.giorno_mese + '/' + s.giorni_mese
        : 'giorno ' + s.T + ' di ' + s.N + ' \u00b7 mese in corso ' + s.giorno_mese + '/' + s.giorni_mese;

    var t2 = '';
    if (s.T >= s.N) {
        t2 = 'finestra completa';
    } else if (c.proj_spi != null) {
        var serve = (c.serve_p50 != null && c.serve_p50 > 0) ? Math.round(c.serve_p50) : 0;
        t2 = MOBILE
           ? 'fine mese con pioggia mediana: SPI ' + fmtSpi(c.proj_spi) + (serve ? ' \u00b7 alla mediana: +' + serve + ' mm' : '')
           : 'a fine mese con pioggia mediana: p' + Math.round(c.proj_perc) + ' / SPI ' + fmtSpi(c.proj_spi)
             + (serve ? ' \u00b7 per chiudere alla mediana servono ' + serve + ' mm' : '');
    }
    document.getElementById('prog2-' + idx).textContent = t2;

    // striscia per anno solare, due righe da 12 mesi (gen..dic) allineate:
    //   riga 1 = anno corrente (chiusi, in corso, futuri), riga 2 = anno precedente
    //   nomi dei mesi una volta sola, sotto le righe
    var strip = document.getElementById('strip-' + idx);
    strip.innerHTML = '';

    var corrente = (s.striscia || []).map(function(q) {
        var o = { nome: q.nome, anno: q.anno, mese: q.mese, stato: q.stato, spi: q.spi, mm: q.mm };
        if (q.stato === 'corrente') { o.spi = c.spi; o.mm = c.mm; }   // valore sempre fresco
        return o;
    });
    var precedente = s.striscia_prec || [];

    function riga(celle, etichetta, classeRiga) {
        var r = document.createElement('div');
        r.className = 'str-riga ' + classeRiga;
        var lab = document.createElement('div');
        lab.className = 'str-anno';
        lab.textContent = etichetta;
        r.appendChild(lab);
        var cont = document.createElement('div');
        cont.className = 'str-celle';

        celle.forEach(function(q) {
            var cell = document.createElement('div');
            cell.className = 'cella' + (q.stato === 'corrente' && classeRiga !== 'prec' ? ' corr' : '');

            if (classeRiga === 'nomi') {
                cell.textContent = MOBILE ? q.nome.charAt(0) : q.nome;
                cont.appendChild(cell);
                return;
            }

            var k    = q.stato === 'futuro' ? null : classe(q.spi);
            var quad = document.createElement('div');
            quad.className = 'q' + (q.stato === 'futuro' ? ' futuro' : (!k ? ' mancante' : ''));
            if (k) quad.style.background = k[2];
            cell.appendChild(quad);

            var testo;
            if (q.stato === 'futuro') {
                testo = q.nome + ' ' + q.anno + '<br>mese non ancora iniziato';
            } else if (!k) {
                testo = q.nome + ' ' + q.anno + (q.stato === 'corrente' ? ' (in corso)' : '') + '<br>dati incompleti';
            } else {
                testo = q.nome + ' ' + q.anno + (q.stato === 'corrente' ? ' (in corso)' : '') + '<br>'
                      + 'SPI ' + fmtSpi(q.spi) + ' \u00b7 ' + k[4] + '<br>' + s.label + ' ' + fmtMm(q.mm);
            }
            cell.addEventListener('mouseenter', function(e) { mostraTooltip(testo, e.clientX, e.clientY); });
            cell.addEventListener('mousemove',  function(e) { mostraTooltip(testo, e.clientX, e.clientY); });
            cell.addEventListener('mouseleave', nascondiTooltip);
            cell.addEventListener('touchstart', function(e) {
                if (e.touches.length) mostraTooltip(testo, e.touches[0].clientX, e.touches[0].clientY);
            }, { passive: true });
            cell.addEventListener('touchend', function() { setTimeout(nascondiTooltip, 1200); });
            cont.appendChild(cell);
        });
        r.appendChild(cont);
        return r;
    }

    if (corrente.length === 12) strip.appendChild(riga(corrente, s.anno_riga, ''));
    if (precedente.length === 12) strip.appendChild(riga(precedente, s.anno_riga_prec, 'prec'));
    strip.appendChild(riga(corrente.length ? corrente : precedente, '', 'nomi'));
}

// ---------------------------------------------------------------------------
// Build
// ---------------------------------------------------------------------------
function build() {
    charts.forEach(function(ch) { ch.destroy(); });
    charts = [];

    var W = document.querySelector('.pannello').offsetWidth || 400;
    MOBILE = W < 480;
    var H = MOBILE
          ? Math.max(230, Math.round(W * 0.75))
          : Math.max(230, Math.min(Math.round(W * 0.42), 320));
    var gaugeW = MOBILE ? 62 : 100;

    SCALE.forEach(function(s, idx) {
        var c = s.correnti;
        var cont = document.getElementById('cont-' + idx);
        cont.style.height = H + 'px';
        document.getElementById('chart-' + idx).style.height = H + 'px';

        // massimo asse: tutto cio' che compare sul grafico
        var maxMm = Math.max(c.mm || 0, c.proj_mm || 0);
        (s.fan.p90 || []).forEach(function(v) { if (v != null) maxMm = Math.max(maxMm, v); });

        var ch = new Chart(document.getElementById('chart-' + idx).getContext('2d'), {
            type: 'scatter',
            data: { datasets: [{ data: [], pointRadius: 0 }] },
            options: {
                animation: false,
                responsive: true,
                maintainAspectRatio: false,
                events: [],
                plugins: { legend: { display: false }, tooltip: { enabled: false },
                           spiPlugin: { idx: idx, gaugeW: gaugeW } },
                layout: { padding: { left: 2, right: gaugeW, top: 14, bottom: 16 } },
                scales: {
                    x: { min: 0, max: s.N, display: false },
                    y: {
                        min: 0, max: niceMax(maxMm * 1.02),
                        position: 'left',
                        grid: { color: 'rgba(128,128,128,0.1)' },
                        ticks: {
                            font: { size: MOBILE ? 8 : 9 }, color: '#2B6DB3',
                            maxTicksLimit: 6,
                            callback: function(v) { return MOBILE ? v : v + ' mm'; }
                        }
                    }
                }
            },
            plugins: [spiPlugin]
        });
        charts.push(ch);

        riempiPannello(idx);
        aggiungiTooltipCanvas(ch);
    });

    scriviFooter();
    sendResize();
}

function scriviFooter() {
    var nota = 'climatologia ' + META.clima_label
             + ' \u2022 SPI da distribuzione gamma sulla pioggia cumulata'
             + ' \u2022 finestre a mesi di calendario: il 1\u00b0 del mese il valore pu\u00f2 cambiare di colpo perch\u00e9 esce un mese intero';

    var esclusi = {};
    SCALE.forEach(function(s) { (s.esclusi || []).forEach(function(e) { esclusi[e.anno] = true; }); });
    var anni = Object.keys(esclusi).sort();
    if (anni.length) nota += ' \u2022 esclusi per dati incompleti: ' + anni.join(', ');

    var scarsi = [];
    SCALE.forEach(function(s) {
        if (s.correnti.copertura < (META.copertura_min || 0.9)) {
            scarsi.push(s.label + ' ' + Math.round(s.correnti.copertura * 100) + '%');
        }
    });
    if (scarsi.length) nota += ' \u2022 copertura dati in corso: ' + scarsi.join(', ');

    // Con pochi anni di riferimento la gamma non viene adattata: SPI da posizione empirica
    var empirici = SCALE.filter(function(s) { return s.fit_T && s.fit_T.alpha == null && s.fit_T.n > 0; })
                        .map(function(s) { return s.label + ' (' + s.fit_T.n + ' anni)'; });
    if (empirici.length) nota += ' \u2022 riferimento breve, SPI empirico: ' + empirici.join(', ');

    document.getElementById('footer-note').textContent = nota;
}

function sendResize() {
    window.parent.postMessage({
        action:   'resize',
        iframeId: 'stat-iframe-pluvio2',
        height:   document.body.scrollHeight
    }, '*');
}

// ---------------------------------------------------------------------------
// Tooltip
// ---------------------------------------------------------------------------
var tooltipEl = document.getElementById('chart-tooltip');

function mostraTooltip(html, x, y) {
    tooltipEl.innerHTML = html;
    tooltipEl.style.display = 'block';
    var l = x - tooltipEl.offsetWidth / 2;
    l = Math.max(4, Math.min(l, window.innerWidth - tooltipEl.offsetWidth - 4));
    var t = y - tooltipEl.offsetHeight - 12;
    if (t < 4) t = y + 16;
    tooltipEl.style.left = l + 'px';
    tooltipEl.style.top  = t + 'px';
}
function nascondiTooltip() { tooltipEl.style.display = 'none'; }

function testoTooltip(ch, mx, my) {
    var s = SCALE[ch.options.plugins.spiPlugin.idx], c = s.correnti, ca = ch.chartArea, hit = ch.$hit || {};
    var cl = classe(c.spi);

    // 1) pallino di oggi
    if (hit.dot && Math.hypot(mx - hit.dot.x, my - hit.dot.y) <= 14) {
        var r = ['Oggi: ' + fmtMm(c.mm) + ' da inizio ' + s.finestra];
        if (cl) r.push('p' + Math.round(c.percentile) + ' \u00b7 SPI ' + fmtSpi(c.spi) + ' \u00b7 ' + cl[4]);
        if (s.fan.p50[s.T] != null) r.push('mediana a oggi: ' + fmtMm(s.fan.p50[s.T]));
        if (c.serve_p25 != null && c.serve_p25 > 0 && s.T < s.N) r.push('per chiudere al p25 servono ' + Math.round(c.serve_p25) + ' mm');
        return r.join('<br>');
    }

    // 2) termometro SPI
    var g = hit.gauge;
    if (g && mx >= g.x0 && mx <= g.x1 && my >= g.y0 && my <= g.y1) {
        if (!cl) return s.label + ': dati storici insufficienti';
        var rg = ['<b>' + s.label + ' ' + fmtSpi(c.spi) + '</b> \u00b7 p' + Math.round(c.percentile), cl[4]];
        if (c.proj_spi != null && s.T < s.N) rg.push('a fine mese con pioggia mediana: ' + fmtSpi(c.proj_spi));
        rg.push('riferimento ' + s.clima.label + ' (' + s.clima.n + ' anni)');
        return rg.join('<br>');
    }

    // 3) area del ventaglio: giorno della finestra
    if (mx >= ca.left && mx <= ca.right && my >= ca.top && my <= ca.bottom) {
        var i = Math.round((mx - ca.left) / (ca.right - ca.left) * s.N);
        i = Math.max(1, Math.min(s.N, i));
        var f = s.fan;
        var rr = ['<b>' + dataGiorno(s, i) + '</b> \u00b7 giorno ' + i + ' di ' + s.N];
        if (i <= s.T) rr.push('attuale: ' + fmtMm(c.serie[i]));
        if (f.p50[i] != null) {
            rr.push('mediana: ' + fmtMm(f.p50[i]));
            rr.push('p25\u2013p75: ' + Math.round(f.p25[i]) + '\u2013' + Math.round(f.p75[i]) + ' mm');
            rr.push('p10\u2013p90: ' + Math.round(f.p10[i]) + '\u2013' + Math.round(f.p90[i]) + ' mm');
        }
        return rr.join('<br>');
    }
    return null;
}

function aggiungiTooltipCanvas(ch) {
    var canvas = ch.canvas;
    if (canvas.$tooltipOk) return;   // il canvas sopravvive ai destroy: listener una volta sola
    canvas.$tooltipOk = true;

    function aggiorna(clientX, clientY) {
        var chAtt = charts[+canvas.id.split('-')[1]];
        if (!chAtt) return;
        var rect = canvas.getBoundingClientRect();
        var t = testoTooltip(chAtt, clientX - rect.left, clientY - rect.top);
        if (t) mostraTooltip(t, clientX, clientY); else nascondiTooltip();
    }
    canvas.addEventListener('mousemove', function(e) { aggiorna(e.clientX, e.clientY); });
    canvas.addEventListener('mouseleave', nascondiTooltip);
    canvas.addEventListener('touchmove', function(e) {
        if (e.touches.length > 0) {
            e.preventDefault();
            aggiorna(e.touches[0].clientX, e.touches[0].clientY);
        }
    }, { passive: false });
    canvas.addEventListener('touchend', nascondiTooltip);
}

var resizeTimer = null;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(build, 200);
});

build();
setTimeout(sendResize, 150);
setTimeout(sendResize, 500);
</script>

</body>
</html>