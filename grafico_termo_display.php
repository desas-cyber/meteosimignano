<?php
/**
 * ============================================================================
 * STATISTICA TERMICA (STI) - grafico_termo_display.php
 * ============================================================================
 * Iframe di termo_stat_display.php: indice termico standardizzato di
 * Simignano, analogo all'SPI della pioggia (grafico2_pluvio_display.php).
 *
 * Un iframe per blocco: ?sezione=situazione | andamento | anni | clima
 * (senza parametro mostra tutto, utile per le prove).
 *
 * Sezioni:
 *   1. Situazione: STI per media / media delle massime / media delle minime
 *      su 1, 3, 6, 12 mesi. Selettore: ultimo mese chiuso o stima di oggi.
 *   2. Andamento: serie dello STI oppure percentili mese per mese
 *      (p10-p90, p25-p75, mediana) con l'anno in corso sovrapposto.
 *      PALLINO PULSANTE = stima al giorno corrente, con barretta d'incertezza.
 *   3. Confronto Theil-Sen: anomalie per anno di un periodo a scelta (anno,
 *      stagione, mese) con tendenza di Theil-Sen e test di Mann-Kendall;
 *      classifiche (anno fino all'ultimo mese chiuso, ultima stagione completa)
 *      calde o fredde.
 *   4. Clima mese per mese: normali, record e anno in corso, ordinabile.
 *
 * Dati: api/api_termo_stat_data.php (getTermoStatData). La pagina riceve i
 * valori mensili e i parametri di riferimento e calcola finestre, STI e
 * percentili nel browser: una sola richiesta, nessuna chiamata successiva.
 *
 * Comunicazione con la pagina contenitore via postMessage (action: resize).
 * iframeId: stat-iframe-termo1..4, uno per sezione (vedi $SEZIONI)
 */

ini_set('display_errors', 0);
error_reporting(0);

// envelop.php fornisce $pdo (lettura+scrittura): serve a salvare i mesi chiusi.
require_once __DIR__ . '/../envelop.php';
require_once __DIR__ . '/api/api_termo_stat_data.php';

// Sezione richiesta dalla pagina contenitore e id dell'iframe corrispondente
$SEZIONI = [
    'situazione' => 'stat-iframe-termo1',
    'andamento'  => 'stat-iframe-termo2',
    'anni'       => 'stat-iframe-termo3',
    'clima'      => 'stat-iframe-termo4',
];
$sezione   = $_GET['sezione'] ?? '';
$tutto     = !isset($SEZIONI[$sezione]);
$iframe_id = $tutto ? 'stat-iframe-termo' : $SEZIONI[$sezione];
$mostra    = fn(string $s): bool => $tutto || $sezione === $s;

$response = getTermoStatData(true);

if (!$response['success']) {
    echo "<div style='font-family:Arial;font-size:13px;color:#c00;padding:10px;'>
            Errore: " . htmlspecialchars($response['error'] ?? 'Dati non disponibili') . "
          </div>";
    exit;
}

// Serie compatta: [ 'YYYY-MM', [media, media_max, media_min] ] (null = mese non valido)
$mesi = [];
foreach ($response['serie'] as $r) {
    $mesi[] = [
        substr($r['mese'], 0, 7),
        [$r['media'][1]['valore'], $r['media_max'][1]['valore'], $r['media_min'][1]['valore']],
    ];
}

$meta      = $response['meta'];
$mesi_js   = json_encode($mesi, JSON_UNESCAPED_UNICODE);
$par_js    = json_encode($response['parametri'], JSON_UNESCAPED_UNICODE);
$ult_js    = json_encode($response['ultimo'], JSON_UNESCAPED_UNICODE);
$stima_js  = json_encode($response['stima_oggi'], JSON_UNESCAPED_UNICODE);
$meta_js   = json_encode($meta, JSON_UNESCAPED_UNICODE);
$oggi_disp = (new DateTime($meta['oggi']))->format('d/m/Y');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indice termico STI</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            background: #fff;
            padding: 6px 0 10px 0;
            color: #333;
            overflow-x: hidden;   /* l'alone del pallino pulsante non deve allargare la pagina */
        }
        .top-bar {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            max-width: 95%;
            margin: 4px auto 4px auto;
            gap: 6px;
            flex-wrap: wrap;
        }
        .titolo { font-size: 12px; font-weight: bold; color: #555; }
        .sottotitolo { font-size: 10px; color: #999; }
        .pannello { max-width: 95%; margin: 10px auto 0 auto; }
        .pannello + .pannello { margin-top: 22px; border-top: 1px dashed rgba(128,128,128,0.2); padding-top: 12px; }
        .pan-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }
        .pan-tit { font-size: 11px; font-weight: bold; color: #555; }
        .pan-nota { font-size: 10px; color: #888; margin-top: 4px; line-height: 1.4; }

        /* ---- selettori (stesso stile del pulsante grafico di tabella_stat) ---- */
        .sel-riga { display: flex; flex-wrap: wrap; gap: 6px 12px; margin-bottom: 6px; }
        .sel-gruppo { display: inline-flex; align-items: center; gap: 3px; flex-wrap: wrap; }
        .sel-periodo {
            font-family: Arial, sans-serif; font-size: 11px; font-weight: bold;
            border: 2px solid #000; border-radius: 3px; background: #fff; padding: 2px 4px; cursor: pointer;
        }
        .sel-periodo:focus-visible { outline: 2px solid #3366cc; }
        .sel-lab { font-size: 9px; color: #999; text-transform: uppercase; letter-spacing: .04em; margin-right: 2px; }
        .btn {
            font-family: Arial, sans-serif;
            font-size: 10px;
            font-weight: bold;
            cursor: pointer;
            padding: 3px 8px;
            border-radius: 3px;
            border: 2px solid #000;
            background: transparent;
            color: #000;
            user-select: none;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .btn:hover { border-color: #3366cc; color: #3366cc; }
        .btn.attivo, .btn.attivo:hover { background: #333; border-color: #333; color: #fff; }
        .btn:focus-visible { outline: 2px solid #3366cc; outline-offset: 1px; }

        /* ---- tabelle (stile tabella_stat_display) ---- */
        table {
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 11px;
            width: 100%;
            table-layout: fixed;
        }
        th, td { padding: 4px 2px; vertical-align: middle; text-align: center; border: 1px solid #bbb; }
        th { background-color: rgba(173,173,173,0.8); font-weight: bold; }
        th:first-child, td:first-child { text-align: left; padding-left: 4px; }
        .t-sti td { font-weight: bold; font-size: 12px; line-height: 1.2; }
        .t-sti td small { display: block; font-size: 9px; font-weight: normal; opacity: .9; }
        .t-sti td:first-child { font-weight: bold; font-size: 11px; background: #f4f4f4; color: #333; }
        .t-clima td { font-variant-numeric: tabular-nums; }
        .t-clima th button {
            all: unset; cursor: pointer; font-weight: bold; display: inline-flex; gap: 2px; align-items: center;
        }
        .t-clima th button:focus-visible { outline: 2px solid #3366cc; }
        .t-clima th .ar { font-size: 8px; color: #333; min-width: 7px; display: inline-block; }
        .t-clima td .anno { font-size: 9px; color: #777; }
        .t-clima tr.riga-anno td { background: rgba(200,200,200,0.5); font-weight: bold; }
        .t-clima td.caldo { color: #B2182B; font-weight: bold; }
        .t-clima td.freddo { color: #2166AC; font-weight: bold; }
        .tab-scroll { overflow-x: auto; }

        .classifiche { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 8px; }
        .t-rank td { text-align: left; padding: 3px 4px; }
        .t-rank td.pos { width: 22px; color: #888; text-align: center; }
        .t-rank td.anno { width: 44px; font-weight: bold; }
        .t-rank td.val { width: 52px; text-align: right; font-variant-numeric: tabular-nums; }
        .t-rank tr.sep td { text-align: center; color: #999; font-size: 10px; padding: 1px 0; border-left: none; border-right: none; }
        .t-rank .barra { height: 18px; border-radius: 0 3px 3px 0; }
        .t-rank tr.corr td { color: #D62828; }
        .t-rank-tit { font-size: 10px; font-weight: bold; color: #555; }
        .rank-head { display: flex; align-items: center; justify-content: space-between; gap: 6px; flex-wrap: wrap; margin-bottom: 4px; }
        .rank-head .btn { padding: 2px 6px; font-size: 9px; }

        /* ---- grafici ---- */
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
        .legend {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
            font-size: 10px;
            color: #666;
            align-items: center;
        }
        .leg-item { display: flex; align-items: center; gap: 4px; }
        .leg-box  { width: 12px; height: 9px; border-radius: 1px; flex-shrink: 0; }
        .leg-line { width: 18px; height: 2px; flex-shrink: 0; }
        .leg-dot  { width: 9px; height: 9px; border-radius: 50%; background: #D62828; flex-shrink: 0; }
        .stat-footer {
            text-align: center;
            font-size: 9px;
            color: #bbb;
            margin-top: 12px;
            max-width: 95%;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.5;
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
            .classifiche { grid-template-columns: 1fr; }
            .legend { gap: 8px; font-size: 9px; }
            .leg-opt { display: none; }
            table { font-size: 10px; }
            .t-sti td { font-size: 11px; }
            .t-sti td small { font-size: 8px; }
            .t-clima th, .t-clima td { padding: 3px 1px; }
            .btn { padding: 3px 6px; }
        }
        @media (min-width: 600px) {
            .titolo   { font-size: 14px; }
            .sottotitolo { font-size: 11px; }
            .pan-tit  { font-size: 13px; }
            .pan-nota { font-size: 11px; }
            .legend   { font-size: 11px; }
            .btn      { font-size: 11px; }
            table     { font-size: 12px; }
            .t-sti td { font-size: 14px; }
            .t-rank-tit { font-size: 12px; }
        }
    </style>
</head>
<body>

<?php if ($mostra('situazione')): ?>
<div class="top-bar">
    <span class="titolo">Indice STI al <?= $oggi_disp ?></span>
    <span class="sottotitolo">riferimento <?= htmlspecialchars($meta['periodo_rif']) ?> &middot; <?= htmlspecialchars($meta['versione']) ?></span>
</div>
<?php endif; ?>

<div id="chart-tooltip" class="chart-tooltip"></div>

<!-- 1. SITUAZIONE -->
<?php if ($mostra('situazione')): ?>
<div class="pannello">
    <div class="pan-head">
        <span class="pan-tit" id="sit-tit">Situazione</span>
        <span class="sel-gruppo" id="sel-sit"></span>
    </div>
    <table class="t-sti" id="tab-sti" aria-label="Indice STI per variabile e finestra"></table>
    <div class="legend" id="leg-sti"></div>
    <div class="pan-nota" id="sit-nota"></div>
</div>
<?php endif; ?>

<!-- 2. ANDAMENTO -->
<?php if ($mostra('andamento')): ?>
<div class="pannello">
    <?php if ($tutto): ?><div class="pan-head"><span class="pan-tit">Andamento</span></div><?php endif; ?>
    <div class="sel-riga">
        <span class="sel-gruppo" id="sel-vista"><span class="sel-lab">Vista</span></span>
        <span class="sel-gruppo" id="sel-fin"><span class="sel-lab">Finestra</span></span>
        <span class="sel-gruppo" id="sel-var"><span class="sel-lab">Variabile</span></span>
    </div>
    <div class="chart-container" id="cont-and">
        <canvas id="chart-and" role="img" aria-label="Andamento dell'indice termico"></canvas>
        <div class="pulse" id="pulse-and"></div>
    </div>
    <div class="legend" id="leg-and"></div>
    <div class="pan-nota" id="nota-and"></div>
</div>
<?php endif; ?>

<!-- 3. ANNI A CONFRONTO -->
<?php if ($mostra('anni')): ?>
<div class="pannello">
    <?php if ($tutto): ?><div class="pan-head"><span class="pan-tit">Anni a confronto</span></div><?php endif; ?>
    <div class="sel-riga">
        <span class="sel-gruppo"><span class="sel-lab">Periodo</span>
            <select id="sel-periodo" class="sel-periodo" aria-label="Periodo per la tendenza">
                <option value="anno">Anno intero</option>
                <optgroup label="Stagioni">
                    <option value="inv">Inverno (dic&ndash;feb)</option>
                    <option value="pri">Primavera (mar&ndash;mag)</option>
                    <option value="est">Estate (giu&ndash;ago)</option>
                    <option value="aut">Autunno (set&ndash;nov)</option>
                </optgroup>
                <optgroup label="Mesi">
                    <option value="m1">Gennaio</option><option value="m2">Febbraio</option><option value="m3">Marzo</option>
                    <option value="m4">Aprile</option><option value="m5">Maggio</option><option value="m6">Giugno</option>
                    <option value="m7">Luglio</option><option value="m8">Agosto</option><option value="m9">Settembre</option>
                    <option value="m10">Ottobre</option><option value="m11">Novembre</option><option value="m12">Dicembre</option>
                </optgroup>
            </select>
        </span>
        <span class="sel-gruppo" id="sel-var-trend"><span class="sel-lab">Variabile</span></span>
    </div>
    <div class="chart-container" id="cont-anni">
        <canvas id="chart-anni" role="img" aria-label="Anomalie per anno con tendenza di Theil-Sen"></canvas>
    </div>
    <div class="pan-nota" id="nota-anni"></div>
    <!-- classifiche: variabile propria, caldi/freddi per riquadro -->
    <div class="sel-riga" style="margin-top:10px;">
        <span class="sel-gruppo" id="sel-var-anni"><span class="sel-lab">Variabile</span></span>
    </div>
    <div class="classifiche">
        <div>
            <div class="rank-head"><span class="t-rank-tit" id="rank1-tit"></span><span class="sel-gruppo" id="sel-ord1"></span></div>
            <table class="t-rank" id="rank1"></table>
        </div>
        <div>
            <div class="rank-head"><span class="t-rank-tit" id="rank2-tit"></span><span class="sel-gruppo" id="sel-ord2"></span></div>
            <table class="t-rank" id="rank2"></table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 4. CLIMA MESE PER MESE -->
<?php if ($mostra('clima')): ?>
<div class="pannello">
    <div class="sel-riga"><span class="sel-gruppo" id="sel-var-clima"><span class="sel-lab">Variabile</span></span></div>
    <div class="tab-scroll"><table class="t-clima" id="tab-clima"></table></div>
    <div class="pan-nota">Tocca l'intestazione di una colonna per ordinarla; un secondo tocco inverte l'ordine.</div>
</div>
<?php endif; ?>

<?php if ($mostra('clima')): ?><div class="stat-footer" id="footer-note"></div><?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
var MESI  = <?= $mesi_js ?>;
var PAR   = <?= $par_js ?>;
var ULT   = <?= $ult_js ?>;
var STIMA = <?= $stima_js ?>;
var META  = <?= $meta_js ?>;
var IFRAME_ID = '<?= $iframe_id ?>';
function c$(id) { return document.getElementById(id); }   // null se la sezione non e' in pagina

var MOBILE = false;
var VARS  = ['media', 'media_max', 'media_min'];
var VLAB  = { media: 'Media', media_max: 'Media max', media_min: 'Media min' };
var VLUNGA = { media: 'temperatura media', media_max: 'media delle massime', media_min: 'media delle minime' };
var FIN   = [1, 3, 6, 12];
var MESI_BREVI = ['gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
var ANNO_RIF_FINE = +META.periodo_rif.slice(-4);

var COL = {
    fanLargo:  'rgba(141,190,110,0.28)',   // verde chiaro: p10-p90
    fanStretto:'rgba(70,130,60,0.38)',     // verde scuro, sovrapposto: p25-p75
    fanBordo:  'rgba(70,130,60,0.45)',
    mediana:   '#2E6B2E',
    corr:      '#D62828',
    corrProj:  'rgba(214,40,40,0.55)',
    serie:     '#333',
    caldo:     'rgba(214,40,40,0.07)',
    freddo:    'rgba(33,102,172,0.07)',
    testo:     '#999'
};

// Classi STI come nell'SPI (McKee), scala caldo/freddo: [da, a, sfondo, testo, etichetta]
var CLASSI = [
    [-99, -2,   '#2166AC', '#fff',    'estremo freddo'],
    [-2,  -1.5, '#4393C3', '#fff',    'molto freddo'],
    [-1.5, -1,  '#92C5DE', '#08306b', 'moderato freddo'],
    [-1,   1,   '#D9D9D9', '#444',    'nella norma'],
    [1,    1.5, '#F4A582', '#6b1a00', 'moderato caldo'],
    [1.5,  2,   '#D6604D', '#fff',    'molto caldo'],
    [2,    99,  '#B2182B', '#fff',    'estremo caldo']
];
// Stessi confini di termoClasse() nell'API: >= per il caldo, > per il freddo
function classe(s) {
    if (s == null) return null;
    if (s >= 2)    return CLASSI[6];
    if (s >= 1.5)  return CLASSI[5];
    if (s >= 1)    return CLASSI[4];
    if (s > -1)    return CLASSI[3];
    if (s > -1.5)  return CLASSI[2];
    if (s > -2)    return CLASSI[1];
    return CLASSI[0];
}
function hexA(hex, a) {
    var n = parseInt(hex.slice(1), 16);
    return 'rgba(' + (n >> 16) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
}
// Legenda delle classi: a = opacita' dei riquadri (senza a: colori pieni della tabella)
function legendaClassi(a) {
    var lab = [['\u2264 \u22122', 'estremo freddo'], ['\u22122 \u00f7 \u22121,5', 'molto freddo'], ['\u22121,5 \u00f7 \u22121', 'moderato freddo'],
               ['\u22121 \u00f7 +1', 'nella norma'], ['+1 \u00f7 +1,5', 'moderato caldo'], ['+1,5 \u00f7 +2', 'molto caldo'], ['\u2265 +2', 'estremo caldo']];
    return CLASSI.map(function(cl, k) {
        if (a != null && k === 3) return '';
        var col = a == null ? cl[2] : hexA(cl[2], a);
        return '<span class="leg-item"><span class="leg-box" style="background:' + col + ';"></span>'
             + (MOBILE ? lab[k][0] : lab[k][0] + ' ' + lab[k][1]) + '</span>';
    }).join('');
}
function fmt(v, d) {
    if (v == null) return '–';
    return (v < 0 ? '−' : '') + Math.abs(v).toFixed(d == null ? 1 : d).replace('.', ',');
}
function fmtSti(s) {
    if (s == null) return 'n/d';
    return (s > 0 ? '+' : s < 0 ? '−' : '') + Math.abs(s).toFixed(2).replace('.', ',');
}
function nomeMese(ym) { return MESI_BREVI[+ym.slice(5, 7) - 1] + ' ' + ym.slice(0, 4); }
function giornoIt(d) { var p = d.split('-'); return +p[2] + '/' + +p[1]; }

// ---------------------------------------------------------------------------
// Calcoli: finestre mobili, STI, percentili (stesse regole dell'API)
// ---------------------------------------------------------------------------
var CACHE = {};
function finestre(vi, w) {
    var k = vi + '|' + w;
    if (CACHE[k]) return CACHE[k];
    var out = MESI.map(function(m, i) {
        if (i < w - 1) return null;
        var s = 0;
        for (var j = 0; j < w; j++) {
            var v = MESI[i - j][1][vi];
            if (v == null) return null;
            // la finestra richiede mesi consecutivi
            if (j > 0 && !consecutivi(MESI[i - j][0], MESI[i - j + 1][0])) return null;
            s += v;
        }
        return s / w;
    });
    return (CACHE[k] = out);
}
function consecutivi(a, b) {
    var ya = +a.slice(0, 4), ma = +a.slice(5, 7), yb = +b.slice(0, 4), mb = +b.slice(5, 7);
    return (yb * 12 + mb) - (ya * 12 + ma) === 1;
}
function stiDi(v, w, mese, val) {
    var p = PAR[v] && PAR[v][w] && PAR[v][w][mese - 1];
    if (val == null || !p) return null;
    return Math.round((val - p[0]) / p[1] * 100) / 100;
}
function quantile(arr, q) {
    var v = arr.slice().sort(function(a, b) { return a - b; });
    var p = q * (v.length - 1), lo = Math.floor(p), hi = Math.ceil(p);
    return v[lo] + (p - lo) * (v[hi] - v[lo]);
}
function mediana(arr) { return quantile(arr, 0.5); }

// ---------------------------------------------------------------------------
// Stato dei selettori
// ---------------------------------------------------------------------------
var ST = { sit: STIMA ? 'oggi' : 'chiuso', vista: 'sti', w: 12, v: 'media', vAnni: 'media', vTrend: 'media', per: 'anno', ord1: 'caldi', ord2: 'caldi', vClima: 'media', sortK: 'mese', sortDir: 1 };

function gruppoBottoni(id, voci, chiave, dopo) {
    var g = document.getElementById(id);
    if (!g) return;
    voci.forEach(function(x) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn' + (ST[chiave] === x[0] ? ' attivo' : '');
        b.textContent = x[1];
        b.setAttribute('aria-pressed', ST[chiave] === x[0]);
        b.addEventListener('click', function() {
            ST[chiave] = x[0];
            g.querySelectorAll('.btn').forEach(function(o) { o.classList.remove('attivo'); o.setAttribute('aria-pressed', 'false'); });
            b.classList.add('attivo'); b.setAttribute('aria-pressed', 'true');
            dopo();
        });
        g.appendChild(b);
    });
}

// ---------------------------------------------------------------------------
// 1. Situazione
// ---------------------------------------------------------------------------
function disegnaSituazione() {
    var oggi = ST.sit === 'oggi' && STIMA;
    document.getElementById('sit-tit').textContent = oggi
        ? 'Situazione al ' + giornoIt(STIMA.fino_al) + ' (stima)'
        : 'Situazione a ' + (ULT ? nomeMese(ULT.mese) : 'n/d');
    var h = '<thead><tr><th>' + (MOBILE ? '' : 'Variabile') + '</th>';
    FIN.forEach(function(w) { h += '<th>' + w + (MOBILE ? ' m' : (w === 1 ? ' mese' : ' mesi')) + '</th>'; });
    h += '</tr></thead><tbody>';
    VARS.forEach(function(v) {
        h += '<tr><td>' + VLAB[v] + '</td>';
        FIN.forEach(function(w) {
            var s = null, extra = '';
            if (oggi) {
                var f = STIMA.var[v] && STIMA.var[v].finestre[w];
                if (f) { s = f.sti; extra = '±' + fmt(f.sig_sti, 1); }
            } else if (ULT && ULT[v] && ULT[v][w]) {
                s = ULT[v][w].sti;
            }
            var cl = classe(s);
            if (!cl) { h += '<td style="background:#f4f4f4;color:#999;">n/d</td>'; return; }
            h += '<td style="background:' + cl[2] + ';color:' + cl[3] + ';">' + fmtSti(s)
               + '<small>' + (MOBILE ? (extra || cl[4].split(' ')[0]) : cl[4] + (extra ? ' ' + extra : '')) + '</small></td>';
        });
        h += '</tr>';
    });
    h += '</tbody>';
    document.getElementById('tab-sti').innerHTML = h;

    var nota = '';
    if (oggi) {
        var sm = STIMA.var.media;
        nota = 'Stima su ' + STIMA.giorni + ' giorni validi di ' + STIMA.giorni_mese + '. '
             + 'La media dei giorni disponibili (' + fmt(sm.parziale) + ' °C) è riportata al mese intero '
             + 'togliendo ' + fmt(sm.delta) + ' °C di effetto stagionale. '
             + 'Il valore ± è l’incertezza in unità di STI dovuta ai giorni che mancano.';
    } else {
        nota = 'Valori del mese chiuso: definitivi.';
        if (!STIMA) {
            // mese in corso senza stima: servono almeno 5 giorni validi (TERMO_PROVV_GIORNI_MIN nell'API)
            var gg = +META.oggi.slice(8, 10), mm = +META.oggi.slice(5, 7);
            var nomeMc = ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'][mm - 1];
            nota += gg <= 6
                ? ' La stima di ' + nomeMc + ' sarà disponibile dal 6/' + mm + ', con almeno 5 giorni completi.'
                : ' La stima di ' + nomeMc + ' comparirà quando ci saranno almeno 5 giorni validi.';
        }
    }
    document.getElementById('sit-nota').textContent = nota;
    document.getElementById('leg-sti').innerHTML = legendaClassi();
}

// ---------------------------------------------------------------------------
// 2. Andamento (Chart.js + plugin di disegno, come grafico2_pluvio)
// ---------------------------------------------------------------------------
var chartAnd = null, chartAnni = null;

function datiAndamento() {
    var v = ST.v, vi = VARS.indexOf(v), w = ST.w;
    var fv = finestre(vi, w);
    var st = STIMA && STIMA.var[v] ? STIMA.var[v].finestre[w] : null;
    var d = { v: v, vi: vi, w: w, fv: fv, stima: st };

    if (ST.vista === 'sti') {
        d.sti = MESI.map(function(m, i) { return stiDi(v, w, +m[0].slice(5, 7), fv[i]); });
    } else {
        // percentili per mese finale, sugli anni di riferimento
        d.perc = [];
        for (var mo = 1; mo <= 12; mo++) {
            var ref = [];
            MESI.forEach(function(m, i) {
                if (+m[0].slice(5, 7) === mo && +m[0].slice(0, 4) <= ANNO_RIF_FINE && fv[i] != null) ref.push(fv[i]);
            });
            d.perc.push(ref.length ? { n: ref.length, ref: ref, p10: quantile(ref, .1), p25: quantile(ref, .25), p50: quantile(ref, .5), p75: quantile(ref, .75), p90: quantile(ref, .9) } : null);
        }
        // anno in corso: quello della stima se c'e', altrimenti dell'ultimo mese chiuso
        d.anno = STIMA ? +STIMA.mese.slice(0, 4) : +MESI[MESI.length - 1][0].slice(0, 4);
        d.corr = [];
        MESI.forEach(function(m, i) { if (+m[0].slice(0, 4) === d.anno && fv[i] != null) d.corr.push({ mo: +m[0].slice(5, 7), v: fv[i] }); });
        d.meseStima = STIMA ? +STIMA.mese.slice(5, 7) : null;
    }
    return d;
}

var andPlugin = {
    id: 'andPlugin',
    beforeDatasetsDraw: function(chart) {
        var d = chart.options.plugins.andPlugin.d;
        var ctx = chart.ctx, ca = chart.chartArea;
        var xp = function(v) { return chart.scales.x.getPixelForValue(v); };
        var yp = function(v) { return chart.scales.y.getPixelForValue(v); };
        var hit = chart.$hit = { dot: null };
        var pulse = document.getElementById('pulse-and');
        pulse.style.display = 'none';

        function baffo(x, v, sig) {
            ctx.strokeStyle = COL.corr; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(x, yp(v + sig)); ctx.lineTo(x, yp(v - sig)); ctx.stroke();
            [v + sig, v - sig].forEach(function(e) { ctx.beginPath(); ctx.moveTo(x - 4, yp(e)); ctx.lineTo(x + 4, yp(e)); ctx.stroke(); });
        }
        function mettiPulse(x, y) {
            pulse.style.left = x + 'px'; pulse.style.top = y + 'px'; pulse.style.display = 'block';
            hit.dot = { x: x, y: y };
        }

        if (ST.vista === 'sti') {
            var yMax = chart.scales.y.max, yMin = chart.scales.y.min;
            // fasce caldo (> +1) e freddo (< -1)
            // bande delle classi (come il termometro SPI): colori delle classi, schiariti
            CLASSI.forEach(function(cl, k) {
                if (k === 3) return;   // nella norma: nessuna banda
                var a = Math.max(cl[0], yMin), b = Math.min(cl[1], yMax);
                if (a >= b) return;
                ctx.fillStyle = hexA(cl[2], 0.45);
                ctx.fillRect(ca.left, yp(b), ca.right - ca.left, yp(a) - yp(b));
            });
            ctx.strokeStyle = 'rgba(128,128,128,0.55)'; ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(ca.left, yp(0)); ctx.lineTo(ca.right, yp(0)); ctx.stroke();

            // anni sotto l'asse
            ctx.font = '9px Arial'; ctx.fillStyle = COL.testo; ctx.textAlign = 'center'; ctx.textBaseline = 'top';
            var passo = MOBILE ? 5 : 3;
            MESI.forEach(function(m, i) {
                var y = +m[0].slice(0, 4);
                if (m[0].slice(5, 7) === '01' && y % passo === 0) ctx.fillText(y, xp(i), ca.bottom + 4);
            });

            // serie
            ctx.strokeStyle = COL.serie; ctx.lineWidth = d.w === 1 ? 1.1 : 1.8; ctx.lineJoin = 'round';
            ctx.beginPath();
            var penna = false, ultimo = null;
            d.sti.forEach(function(s, i) {
                if (s == null) { penna = false; return; }
                if (penna) ctx.lineTo(xp(i), yp(s)); else ctx.moveTo(xp(i), yp(s));
                penna = true; ultimo = i;
            });
            ctx.stroke();

            if (ultimo != null) {
                ctx.beginPath(); ctx.arc(xp(ultimo), yp(d.sti[ultimo]), 3.5, 0, Math.PI * 2);
                ctx.fillStyle = COL.serie; ctx.fill();
            }
            if (d.stima && ultimo != null) {
                var xi = MESI.length;
                ctx.strokeStyle = COL.corrProj; ctx.lineWidth = 1.5; ctx.setLineDash([4, 3]);
                ctx.beginPath(); ctx.moveTo(xp(ultimo), yp(d.sti[ultimo])); ctx.lineTo(xp(xi), yp(d.stima.sti)); ctx.stroke();
                ctx.setLineDash([]);
                baffo(xp(xi), d.stima.sti, d.stima.sig_sti);
                mettiPulse(xp(xi), yp(d.stima.sti));
                if (!MOBILE) {
                    ctx.font = 'bold 9px Arial'; ctx.fillStyle = COL.corr; ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
                    ctx.fillText(fmtSti(d.stima.sti), xp(xi) - 10, yp(d.stima.sti));
                }
            } else if (ultimo != null) {
                mettiPulse(xp(ultimo), yp(d.sti[ultimo]));
            }
        } else {
            // ventaglio dei percentili
            function banda(a, b, colore) {
                ctx.beginPath();
                for (var mo = 1; mo <= 12; mo++) if (d.perc[mo - 1]) ctx.lineTo(xp(mo), yp(d.perc[mo - 1][a]));
                for (var mo2 = 12; mo2 >= 1; mo2--) if (d.perc[mo2 - 1]) ctx.lineTo(xp(mo2), yp(d.perc[mo2 - 1][b]));
                ctx.closePath(); ctx.fillStyle = colore; ctx.fill();
            }
            function linea(chiave, colore, spessore, tratt) {
                ctx.strokeStyle = colore; ctx.lineWidth = spessore; ctx.setLineDash(tratt || []);
                ctx.beginPath();
                for (var mo = 1; mo <= 12; mo++) if (d.perc[mo - 1]) ctx.lineTo(xp(mo), yp(d.perc[mo - 1][chiave]));
                ctx.stroke(); ctx.setLineDash([]);
            }
            banda('p10', 'p90', COL.fanLargo);
            banda('p25', 'p75', COL.fanStretto);
            linea('p10', COL.fanBordo, 1); linea('p90', COL.fanBordo, 1);
            linea('p50', COL.mediana, 2, [5, 4]);

            ctx.font = 'bold 9px Arial'; ctx.textAlign = 'center'; ctx.textBaseline = 'top';
            for (var mo = 1; mo <= 12; mo++) {
                ctx.fillStyle = (mo === d.meseStima) ? COL.corr : COL.testo;
                ctx.fillText(MOBILE ? MESI_BREVI[mo - 1].charAt(0).toUpperCase() : MESI_BREVI[mo - 1], xp(mo), ca.bottom + 4);
            }

            // anno in corso
            if (d.corr.length) {
                ctx.strokeStyle = COL.corr; ctx.lineWidth = 2.2; ctx.lineJoin = 'round';
                ctx.beginPath();
                d.corr.forEach(function(c) { ctx.lineTo(xp(c.mo), yp(c.v)); });
                ctx.stroke();
                d.corr.forEach(function(c) {
                    ctx.beginPath(); ctx.arc(xp(c.mo), yp(c.v), 3, 0, Math.PI * 2);
                    ctx.fillStyle = COL.corr; ctx.fill();
                });
            }
            var ult = d.corr.length ? d.corr[d.corr.length - 1] : null;
            if (d.stima && d.meseStima) {
                if (ult) {
                    ctx.strokeStyle = COL.corrProj; ctx.lineWidth = 1.5; ctx.setLineDash([4, 3]);
                    ctx.beginPath(); ctx.moveTo(xp(ult.mo), yp(ult.v)); ctx.lineTo(xp(d.meseStima), yp(d.stima.valore)); ctx.stroke();
                    ctx.setLineDash([]);
                }
                baffo(xp(d.meseStima), d.stima.valore, d.stima.sig_t);
                mettiPulse(xp(d.meseStima), yp(d.stima.valore));
                if (!MOBILE) {
                    ctx.font = 'bold 9px Arial'; ctx.fillStyle = COL.corr; ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
                    ctx.fillText(fmt(d.stima.valore) + ' °C', xp(d.meseStima) + 10, yp(d.stima.valore));
                }
            } else if (ult) {
                mettiPulse(xp(ult.mo), yp(ult.v));
            }
        }
    }
};

function costruisciAndamento(W) {
    if (chartAnd) chartAnd.destroy();
    var d = datiAndamento();
    var H = MOBILE ? Math.max(240, Math.round(W * 0.78)) : Math.max(240, Math.min(Math.round(W * 0.42), 330));
    document.getElementById('cont-and').style.height = H + 'px';
    document.getElementById('chart-and').style.height = H + 'px';

    var xs, ys;
    if (ST.vista === 'sti') {
        var top = 3.2, bot = -3.2;
        d.sti.forEach(function(s) { if (s != null) { top = Math.max(top, s + 0.3); bot = Math.min(bot, s - 0.3); } });
        if (d.stima) { top = Math.max(top, d.stima.sti + d.stima.sig_sti + 0.3); bot = Math.min(bot, d.stima.sti - d.stima.sig_sti - 0.3); }
        xs = { min: 0, max: MESI.length, display: false };
        ys = { min: Math.floor(bot), max: Math.ceil(top),
               grid: { color: 'rgba(128,128,128,0.1)' },
               ticks: { stepSize: 1, font: { size: MOBILE ? 8 : 9 }, color: '#666',
                        callback: function(v) { return Number.isInteger(v) ? (v > 0 ? '+' : '') + v : ''; } } };
    } else {
        var lo = 99, hi = -99;
        d.perc.forEach(function(p) { if (p) { lo = Math.min(lo, p.p10); hi = Math.max(hi, p.p90); } });
        d.corr.forEach(function(c) { lo = Math.min(lo, c.v); hi = Math.max(hi, c.v); });
        if (d.stima) { lo = Math.min(lo, d.stima.valore - d.stima.sig_t); hi = Math.max(hi, d.stima.valore + d.stima.sig_t); }
        xs = { min: 0.5, max: 12.5, display: false };
        ys = { min: Math.floor(lo - 1), max: Math.ceil(hi + 1),
               grid: { color: 'rgba(128,128,128,0.1)' },
               ticks: { maxTicksLimit: 7, font: { size: MOBILE ? 8 : 9 }, color: '#666',
                        callback: function(v) { return MOBILE ? v : v + '°'; } } };
    }

    chartAnd = new Chart(document.getElementById('chart-and').getContext('2d'), {
        type: 'scatter',
        data: { datasets: [{ data: [], pointRadius: 0 }] },
        options: {
            animation: false, responsive: true, maintainAspectRatio: false, events: [],
            plugins: { legend: { display: false }, tooltip: { enabled: false }, andPlugin: { d: d } },
            layout: { padding: { left: 2, right: MOBILE ? 16 : 22, top: 10, bottom: 16 } },
            scales: { x: xs, y: ys }
        },
        plugins: [andPlugin]
    });
    tooltipCanvas(chartAnd, testoAndamento);
    legendaAndamento(d);
}

function legendaAndamento(d) {
    var l = document.getElementById('leg-and');
    var stima = d.stima ? '<span class="leg-item"><span class="leg-dot"></span>oggi (stima ± incertezza)</span>' : '';
    if (ST.vista === 'sti') {
        l.innerHTML = '<span class="leg-item"><span class="leg-line" style="background:' + COL.serie + ';"></span>STI ' + d.w + (d.w === 1 ? ' mese' : ' mesi') + '</span>'
            + stima + legendaClassi(0.45);
    } else {
        l.innerHTML = '<span class="leg-item"><span class="leg-box" style="background:' + COL.fanLargo + ';border:1px solid ' + COL.fanBordo + ';"></span>p10–p90</span>'
            + '<span class="leg-item"><span class="leg-box" style="background:rgba(84,145,70,0.58);"></span>p25\u2013p75</span>'
            + '<span class="leg-item"><span style="display:inline-block;width:18px;border-top:2px dashed ' + COL.mediana + ';"></span>mediana</span>'
            + '<span class="leg-item"><span class="leg-line" style="background:' + COL.corr + ';height:3px;"></span>' + d.anno + '</span>'
            + stima;
    }
    var nota = VLUNGA[d.v] + ', finestra di ' + d.w + (d.w === 1 ? ' mese' : ' mesi');
    if (ST.vista === 'perc') nota += ' che termina nel mese indicato; percentili ' + META.periodo_rif;
    if (d.stima) nota += '. Oggi: ' + (ST.vista === 'sti' ? 'STI ' + fmtSti(d.stima.sti) + ' ± ' + fmt(d.stima.sig_sti, 2) : fmt(d.stima.valore) + ' ± ' + fmt(d.stima.sig_t) + ' °C')
                     + ', stima su ' + STIMA.giorni + ' giorni al ' + giornoIt(STIMA.fino_al);
    document.getElementById('nota-and').textContent = nota + '.';
}

function testoAndamento(ch, mx, my) {
    var d = ch.options.plugins.andPlugin.d, ca = ch.chartArea, hit = ch.$hit || {};
    if (hit.dot && Math.hypot(mx - hit.dot.x, my - hit.dot.y) <= 14 && d.stima) {
        var r = ['<b>Oggi, stima al ' + giornoIt(STIMA.fino_al) + '</b>', STIMA.giorni + ' giorni validi su ' + STIMA.giorni_mese];
        if (ST.vista === 'sti') r.push('STI ' + fmtSti(d.stima.sti) + ' ± ' + fmt(d.stima.sig_sti, 2) + ' · ' + classe(d.stima.sti)[4]);
        else r.push(fmt(d.stima.valore) + ' ± ' + fmt(d.stima.sig_t) + ' °C');
        return r.join('<br>');
    }
    if (mx < ca.left || mx > ca.right || my < ca.top || my > ca.bottom) return null;
    var xv = ch.scales.x.getValueForPixel(mx);
    if (ST.vista === 'sti') {
        var i = Math.max(0, Math.min(MESI.length - 1, Math.round(xv)));
        var s = d.sti[i], m = MESI[i][0];
        if (s == null) return nomeMese(m) + '<br>finestra non valida';
        return '<b>' + nomeMese(m) + '</b> · ' + VLAB[d.v] + ' ' + d.w + (d.w === 1 ? ' mese' : ' mesi')
             + '<br>STI ' + fmtSti(s) + ' · ' + classe(s)[4] + '<br>valore ' + fmt(d.fv[i], 2) + ' °C';
    }
    var mo = Math.max(1, Math.min(12, Math.round(xv))), p = d.perc[mo - 1];
    if (!p) return null;
    var rr = ['<b>' + MESI_BREVI[mo - 1] + '</b> · ' + p.n + ' anni', 'p90 ' + fmt(p.p90) + ' · p75 ' + fmt(p.p75),
              'mediana ' + fmt(p.p50), 'p25 ' + fmt(p.p25) + ' · p10 ' + fmt(p.p10)];
    var c = d.corr.filter(function(z) { return z.mo === mo; })[0];
    if (c) {
        var sotto = p.ref.filter(function(x) { return x < c.v; }).length;
        rr.push(d.anno + ': ' + fmt(c.v) + ' °C, più caldo di ' + sotto + ' anni su ' + p.n);
    }
    if (d.stima && mo === d.meseStima) rr.push('oggi: ' + fmt(d.stima.valore) + ' ± ' + fmt(d.stima.sig_t) + ' °C');
    return rr.join('<br>');
}

// ---------------------------------------------------------------------------
// 3. Anni a confronto
// ---------------------------------------------------------------------------
function medieAnnue(vi, mesiSel) {
    var per = {};
    MESI.forEach(function(m) {
        var y = +m[0].slice(0, 4), mo = +m[0].slice(5, 7);
        if (mesiSel.indexOf(mo) < 0) return;
        (per[y] = per[y] || []).push(m[1][vi]);
    });
    var out = [];
    Object.keys(per).forEach(function(y) {
        var a = per[y];
        if (a.length === mesiSel.length && a.every(function(x) { return x != null; })) {
            out.push({ y: +y, v: a.reduce(function(s, x) { return s + x; }, 0) / a.length });
        }
    });
    return out;
}
function theilSen(pts) {
    var sl = [];
    for (var i = 0; i < pts.length; i++) for (var j = i + 1; j < pts.length; j++) sl.push((pts[j].v - pts[i].v) / (pts[j].y - pts[i].y));
    var b = mediana(sl);
    var a = mediana(pts.map(function(p) { return p.v - b * p.y; }));
    return { b: b, a: a };
}

var anniPlugin = {
    id: 'anniPlugin',
    beforeDatasetsDraw: function(chart) {
        var d = chart.options.plugins.anniPlugin.d, ctx = chart.ctx, ca = chart.chartArea;
        var xp = function(v) { return chart.scales.x.getPixelForValue(v); };
        var yp = function(v) { return chart.scales.y.getPixelForValue(v); };
        var bw = Math.max(4, (xp(1) - xp(0)) - 2);   // barre accostate, 2 px di stacco
        ctx.strokeStyle = 'rgba(128,128,128,0.55)'; ctx.lineWidth = 1;
        ctx.beginPath(); ctx.moveTo(ca.left, yp(0)); ctx.lineTo(ca.right, yp(0)); ctx.stroke();
        d.anni.forEach(function(a) {
            var an = a.v - d.norm;
            ctx.fillStyle = an >= 0 ? '#D6604D' : '#4393C3';
            var y0 = yp(Math.max(an, 0)), y1 = yp(Math.min(an, 0));
            ctx.fillRect(xp(a.y) - bw / 2, y0, bw, Math.max(1, y1 - y0));
        });
        ctx.font = '9px Arial'; ctx.fillStyle = COL.testo; ctx.textAlign = 'center'; ctx.textBaseline = 'top';
        var passo = MOBILE ? 4 : 2;
        d.anni.forEach(function(a) { if (a.y % passo === 0) ctx.fillText(MOBILE ? "'" + String(a.y).slice(2) : a.y, xp(a.y), ca.bottom + 4); });
        // tendenza
        var y0 = d.anni[0].y, y1 = d.anni[d.anni.length - 1].y;
        ctx.strokeStyle = '#333'; ctx.lineWidth = 1.6; ctx.setLineDash([6, 4]);
        ctx.beginPath(); ctx.moveTo(xp(y0), yp(d.ts.a + d.ts.b * y0 - d.norm)); ctx.lineTo(xp(y1), yp(d.ts.a + d.ts.b * y1 - d.norm)); ctx.stroke();
        ctx.setLineDash([]);
    }
};

// Periodi selezionabili per la tendenza. Inverno: dicembre dell'anno prima
// + gennaio e febbraio (l'anno e' quello di gennaio, come per la pioggia).
var PERIODI = {
    anno: { nome: 'anno intero', mesi: [[1,0],[2,0],[3,0],[4,0],[5,0],[6,0],[7,0],[8,0],[9,0],[10,0],[11,0],[12,0]] },
    inv:  { nome: 'inverno (dic–feb)',   mesi: [[12,-1],[1,0],[2,0]] },
    pri:  { nome: 'primavera (mar–mag)', mesi: [[3,0],[4,0],[5,0]] },
    est:  { nome: 'estate (giu–ago)',    mesi: [[6,0],[7,0],[8,0]] },
    aut:  { nome: 'autunno (set–nov)',   mesi: [[9,0],[10,0],[11,0]] }
};
for (var mm = 1; mm <= 12; mm++) PERIODI['m' + mm] = { nome: ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'][mm - 1], mesi: [[mm, 0]] };

// Valore del periodo per ogni anno: media dei suoi mesi, solo se tutti validi
function valoriPeriodo(vi, chiave) {
    var idx = {};
    MESI.forEach(function(m) { idx[m[0]] = m[1][vi]; });
    var y0 = +MESI[0][0].slice(0, 4), y1 = +MESI[MESI.length - 1][0].slice(0, 4), out = [];
    for (var y = y0; y <= y1; y++) {
        var s = 0, ok = true;
        PERIODI[chiave].mesi.forEach(function(q) {
            var k = (y + q[1]) + '-' + (q[0] < 10 ? '0' : '') + q[0];
            var v = idx[k];
            if (v == null) ok = false; else s += v;
        });
        if (ok) out.push({ y: y, v: s / PERIODI[chiave].mesi.length });
    }
    return out;
}

// Test di Mann-Kendall (approssimazione normale, senza correzione per i pari)
function mannKendall(pts) {
    var n = pts.length, S = 0;
    for (var i = 0; i < n; i++) for (var j = i + 1; j < n; j++) S += Math.sign(pts[j].v - pts[i].v);
    var varS = n * (n - 1) * (2 * n + 5) / 18;
    var z = S === 0 ? 0 : (S - Math.sign(S)) / Math.sqrt(varS);
    return { S: S, z: z, p: 2 * (1 - phi(Math.abs(z))) };
}
function phi(x) {   // funzione di ripartizione normale (Abramowitz-Stegun 7.1.26)
    var t = 1 / (1 + 0.3275911 * x / Math.SQRT2);
    var e = 1 - (((((1.061405429 * t - 1.453152027) * t) + 1.421413741) * t - 0.284496736) * t + 0.254829592) * t * Math.exp(-x * x / 2);
    return 0.5 * (1 + e);
}

function costruisciAnni(W) {
    if (chartAnni) chartAnni.destroy();
    var vi = VARS.indexOf(ST.vTrend), P = PERIODI[ST.per];
    var anni = valoriPeriodo(vi, ST.per);
    var nota = document.getElementById('nota-anni');
    if (anni.length < 5) {
        nota.textContent = 'Dati insufficienti per questo periodo.';
        disegnaClassifiche(VARS.indexOf(ST.vAnni));
        return;
    }
    var rif = anni.filter(function(a) { return a.y <= ANNO_RIF_FINE; });
    var norm = rif.reduce(function(s, a) { return s + a.v; }, 0) / rif.length;
    var ts = theilSen(anni), mk = mannKendall(anni);
    var d = { anni: anni, norm: norm, ts: ts };
    var H = MOBILE ? Math.max(200, Math.round(W * 0.60)) : Math.max(200, Math.min(Math.round(W * 0.32), 260));
    document.getElementById('cont-anni').style.height = H + 'px';
    document.getElementById('chart-anni').style.height = H + 'px';
    var lo = 0, hi = 0;
    anni.forEach(function(a) { lo = Math.min(lo, a.v - norm); hi = Math.max(hi, a.v - norm); });
    var passoY = (hi - lo) > 6 ? 2 : 1;
    chartAnni = new Chart(document.getElementById('chart-anni').getContext('2d'), {
        type: 'scatter',
        data: { datasets: [{ data: [], pointRadius: 0 }] },
        options: {
            animation: false, responsive: true, maintainAspectRatio: false, events: [],
            plugins: { legend: { display: false }, tooltip: { enabled: false }, anniPlugin: { d: d } },
            layout: { padding: { left: 2, right: MOBILE ? 8 : 14, top: 8, bottom: 16 } },
            scales: {
                x: { min: anni[0].y - 0.5, max: anni[anni.length - 1].y + 0.5, display: false },
                y: { min: Math.floor(lo - 0.3), max: Math.ceil(hi + 0.3), grid: { color: 'rgba(128,128,128,0.1)' },
                     ticks: { stepSize: passoY, font: { size: MOBILE ? 8 : 9 }, color: '#666', callback: function(v) { return (v > 0 ? '+' : '') + v + '°'; } } }
            }
        },
        plugins: [anniPlugin]
    });
    tooltipCanvas(chartAnni, function(ch, mx, my) {
        var ca = ch.chartArea;
        if (mx < ca.left || mx > ca.right || my < ca.top || my > ca.bottom) return null;
        var y = Math.round(ch.scales.x.getValueForPixel(mx));
        var a = anni.filter(function(z) { return z.y === y; })[0];
        if (!a) return y + '<br>dati incompleti';
        return '<b>' + P.nome + ' ' + a.y + '</b><br>' + fmt(a.v, 2) + ' °C · anomalia ' + fmtSti(a.v - norm) + ' °C'
             + '<br>tendenza in quell’anno: ' + fmtSti(ts.a + ts.b * a.y - norm) + ' °C';
    });
    var dec = ts.b * 10;
    var giudizio = mk.p < 0.01 ? 'tendenza significativa (p < 0,01)'
                 : mk.p < 0.05 ? 'tendenza significativa (p = ' + fmt(mk.p, 3) + ')'
                 : 'tendenza non significativa (p = ' + fmt(mk.p, 2) + ')';
    // esito in grassetto: verde se significativa, rosso se no
    giudizio = '<b style="color:' + (mk.p < 0.05 ? '#1E8E3E' : '#D62828') + ';">' + giudizio + '</b>';
    var vl = VLUNGA[ST.vTrend];
    nota.innerHTML = '<b>' + vl.charAt(0).toUpperCase() + vl.slice(1) + ', ' + P.nome + ': ' + (dec >= 0 ? '+' : '−') + fmt(Math.abs(dec), 2)
        + ' °C per decennio</b>, ' + giudizio + ' (Mann–Kendall, ' + anni.length + ' anni). '
        + 'Barre: scarto dalla normale ' + rif[0].y + '–' + rif[rif.length - 1].y + ' (' + fmt(norm) + ' °C); '
        + 'tratteggio: retta di Theil–Sen, mediana delle pendenze fra tutte le coppie di anni.';
    disegnaClassifiche(VARS.indexOf(ST.vAnni));
}

// Ultima stagione completa dato l'ultimo mese chiuso (1-12):
// feb-apr -> inverno, mag-lug -> primavera, ago-ott -> estate, nov-gen -> autunno
function ultimaStagione(m) {
    if (m >= 2 && m <= 4) return 'inv';
    if (m >= 5 && m <= 7) return 'pri';
    if (m >= 8 && m <= 10) return 'est';
    return 'aut';
}

function disegnaClassifiche(vi) {
    // anno in corso: da gennaio all'ultimo mese chiuso
    var ultimo = MESI[MESI.length - 1][0], meseUlt = +ultimo.slice(5, 7);
    var p1 = []; for (var m = 1; m <= meseUlt; m++) p1.push(m);
    var tabs = [
        { id: 'rank1', ord: 'ord1', mesi: p1, tit: (meseUlt === 1 ? 'Gennaio' : 'Gennaio\u2013' + MESI_BREVI[meseUlt - 1]), f: 'o' },
        { id: 'rank2', ord: 'ord2', stag: ultimaStagione(meseUlt), f: 'e' }
    ];
    var annoCorr = +ultimo.slice(0, 4);
    // anno "di etichetta" dell'ultima stagione chiusa (l'inverno prende l'anno di gennaio;
    // a gennaio l'ultima stagione chiusa e' l'autunno dell'anno prima)
    var annoStag = (meseUlt === 1) ? annoCorr - 1 : annoCorr;
    tabs[1].tit = PERIODI[tabs[1].stag].nome.charAt(0).toUpperCase() + PERIODI[tabs[1].stag].nome.slice(1);
    tabs.forEach(function(t) {
        var freddi = ST[t.ord] === 'freddi';
        var righe = t.stag ? valoriPeriodo(vi, t.stag) : medieAnnue(vi, t.mesi);
        var evid = t.stag ? annoStag : annoCorr;
        var tutti = righe.map(function(r) { return r.v; });
        var hi = Math.max.apply(null, tutti), lo = Math.min.apply(null, tutti), span = hi - lo + 0.6;
        righe.sort(function(a, b) { return freddi ? a.v - b.v : b.v - a.v; });
        var nMax = MOBILE ? 5 : 6;
        var riga = function(r, i) {
            var w = freddi ? (hi + 0.6 - r.v) / span : (r.v - lo + 0.6) / span;
            return '<tr class="' + (r.y === evid ? 'corr' : '') + '"><td class="pos">' + (i + 1) + '</td><td class="anno">'
               + (t.stag === 'inv' ? (r.y - 1) + '/' + String(r.y).slice(2) : r.y) + '</td>'
               + '<td><div class="barra" style="width:' + Math.round(w * 100) + '%;background:' + (freddi ? '#4393C3' : '#D6604D') + ';"></div></td>'
               + '<td class="val">' + fmt(r.v, 2) + '</td></tr>';
        };
        var h = righe.slice(0, nMax).map(riga).join('');
        // anno/stagione corrente fuori dai primi: riga aggiuntiva con la sua posizione
        var iEv = righe.findIndex(function(r) { return r.y === evid; });
        if (iEv >= nMax) {
            h += '<tr class="sep"><td colspan="4">&middot;&middot;&middot; su ' + righe.length + ' anni</td></tr>' + riga(righe[iEv], iEv);
        }
        document.getElementById(t.id).innerHTML = '<tbody>' + h + '</tbody>';
        var suff = t.f === 'e' ? (freddi ? ', le più fredde' : ', le più calde') : (freddi ? ', i più freddi' : ', i più caldi');
        document.getElementById(t.id + '-tit').textContent = t.tit + suff + (ST.vAnni === 'media' ? '' : ' · ' + VLAB[ST.vAnni].toLowerCase());
    });
}

// ---------------------------------------------------------------------------
// 4. Clima mese per mese (ordinabile)
// ---------------------------------------------------------------------------
function righeClima() {
    var v = ST.vClima, vi = VARS.indexOf(v);
    var annoCorr = STIMA ? +STIMA.mese.slice(0, 4) : +MESI[MESI.length - 1][0].slice(0, 4);
    var righe = [];
    for (var mo = 1; mo <= 12; mo++) {
        var p = PAR[v][1][mo - 1];
        var hi = null, lo = null, corr = null;
        MESI.forEach(function(m) {
            if (+m[0].slice(5, 7) !== mo) return;
            var x = m[1][vi]; if (x == null) return;
            var y = +m[0].slice(0, 4);
            if (!hi || x > hi.v) hi = { v: x, y: y };
            if (!lo || x < lo.v) lo = { v: x, y: y };
            if (y === annoCorr) corr = x;
        });
        righe.push({ mese: mo, norm: p ? p[0] : null, sd: p ? p[1] : null, hi: hi ? hi.v : null, hiY: hi ? hi.y : null,
                     lo: lo ? lo.v : null, loY: lo ? lo.y : null, corr: corr, sti: stiDi(v, 1, mo, corr) });
    }
    return { righe: righe, anno: annoCorr };
}

function disegnaClima() {
    var dati = righeClima(), righe = dati.righe.slice();
    var k = ST.sortK, dir = ST.sortDir;
    righe.sort(function(a, b) {
        var x = a[k], y = b[k];
        if (x == null && y == null) return a.mese - b.mese;
        if (x == null) return 1;
        if (y == null) return -1;
        return (x - y) * dir || a.mese - b.mese;
    });
    var col = [['mese', 'Mese'], ['norm', MOBILE ? 'Norm.' : 'Normale'], ['sd', 'σ'], ['hi', MOBILE ? 'Max' : 'Più caldo'],
               ['lo', MOBILE ? 'Min' : 'Più freddo'], ['corr', String(dati.anno)], ['sti', 'STI']];
    var h = '<thead><tr>' + col.map(function(c) {
        var on = k === c[0];
        return '<th aria-sort="' + (on ? (dir > 0 ? 'ascending' : 'descending') : 'none') + '"><button type="button" data-k="' + c[0] + '">'
             + c[1] + '<span class="ar">' + (on ? (dir > 0 ? '▲' : '▼') : '') + '</span></button></th>';
    }).join('') + '</tr></thead><tbody>';
    righe.forEach(function(r) {
        var cls = r.sti == null ? '' : (r.sti >= 1 ? 'caldo' : r.sti <= -1 ? 'freddo' : '');
        h += '<tr><td>' + MESI_BREVI[r.mese - 1] + '</td><td>' + fmt(r.norm) + '</td><td>' + fmt(r.sd) + '</td>'
           + '<td class="' + (r.hiY === dati.anno ? 'caldo' : '') + '">' + fmt(r.hi) + ' <span class="anno">' + (r.hiY ? "'" + String(r.hiY).slice(2) : '') + '</span></td>'
           + '<td class="' + (r.loY === dati.anno ? 'freddo' : '') + '">' + fmt(r.lo) + ' <span class="anno">' + (r.loY ? "'" + String(r.loY).slice(2) : '') + '</span></td>'
           + '<td class="' + cls + '">' + fmt(r.corr) + '</td><td class="' + cls + '">' + (r.sti == null ? '–' : fmtSti(r.sti)) + '</td></tr>';
    });
    h += '</tbody>';
    var t = document.getElementById('tab-clima');
    t.innerHTML = h;
    t.querySelectorAll('th button').forEach(function(b) {
        b.addEventListener('click', function() {
            var key = b.getAttribute('data-k');
            if (ST.sortK === key) ST.sortDir = -ST.sortDir;
            else { ST.sortK = key; ST.sortDir = key === 'mese' ? 1 : -1; }
            disegnaClima(); sendResize();
            var nb = document.querySelector('#tab-clima th button[data-k="' + key + '"]');
            if (nb) nb.focus();
        });
    });
}

// ---------------------------------------------------------------------------
// Tooltip (stesso comportamento di grafico2_pluvio_display)
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
function tooltipCanvas(ch, fn) {
    var canvas = ch.canvas;
    canvas.$fn = fn; canvas.$ch = ch;
    if (canvas.$tooltipOk) return;   // il canvas sopravvive ai destroy: listener una volta sola
    canvas.$tooltipOk = true;
    function aggiorna(cx, cy) {
        var rect = canvas.getBoundingClientRect();
        var t = canvas.$fn(canvas.$ch, cx - rect.left, cy - rect.top);
        if (t) mostraTooltip(t, cx, cy); else nascondiTooltip();
    }
    canvas.addEventListener('mousemove', function(e) { aggiorna(e.clientX, e.clientY); });
    canvas.addEventListener('mouseleave', nascondiTooltip);
    canvas.addEventListener('touchmove', function(e) {
        if (e.touches.length > 0) { e.preventDefault(); aggiorna(e.touches[0].clientX, e.touches[0].clientY); }
    }, { passive: false });
    canvas.addEventListener('touchstart', function(e) {
        if (e.touches.length > 0) aggiorna(e.touches[0].clientX, e.touches[0].clientY);
    }, { passive: true });
    canvas.addEventListener('touchend', function() { setTimeout(nascondiTooltip, 1200); });
}

// ---------------------------------------------------------------------------
// Build
// ---------------------------------------------------------------------------
function build() {
    var W = document.querySelector('.pannello').offsetWidth || 400;
    MOBILE = W < 480;
    if (c$('tab-sti'))     disegnaSituazione();
    if (c$('chart-and'))   costruisciAndamento(W);
    if (c$('chart-anni'))  costruisciAnni(W);
    if (c$('tab-clima'))   disegnaClima();
    if (c$('footer-note')) scriviFooter();
    sendResize();
}
function soloAndamento() { costruisciAndamento(document.querySelector('.pannello').offsetWidth || 400); sendResize(); }
function soloAnni() { costruisciAnni(document.querySelector('.pannello').offsetWidth || 400); sendResize(); }

function scriviFooter() {
    var nota = 'aggiornato: ' + META.generato_il
             + ' • riferimento ' + META.periodo_rif + ' (' + META.versione + ')'
             + ' • STI = (valore − media di riferimento) / deviazione standard; mese valido con almeno ' + META.giorni_mese_min + ' giorni'
             + ' • serie 2003–2024 ricostruita da Mensano, Scorgiano, Pentolina; reale dal 2025';
    if (META.mesi_calcolati && META.mesi_calcolati.length) nota += ' • mesi calcolati ora: ' + META.mesi_calcolati.join(', ');
    if (/^errore/.test(META.aggiornamento || '') || META.errore_aggiornamento)
        nota += ' • ATTENZIONE aggiornamento mesi: ' + META.aggiornamento + (META.errore_aggiornamento ? ' (' + META.errore_aggiornamento + ')' : '');
    document.getElementById('footer-note').textContent = nota;
}

function sendResize() {
    window.parent.postMessage({
        action:   'resize',
        iframeId: IFRAME_ID,
        height:   document.body.scrollHeight
    }, '*');
}

// Selettori
var voci_sit = [['chiuso', ULT ? nomeMese(ULT.mese) : 'mese chiuso']];
if (STIMA) voci_sit.push(['oggi', 'oggi (stima)']);
gruppoBottoni('sel-sit',  voci_sit, 'sit', function() { disegnaSituazione(); sendResize(); });
gruppoBottoni('sel-vista', [['sti', 'Indice STI'], ['perc', 'Percentili']], 'vista', soloAndamento);
gruppoBottoni('sel-fin',   [[1, '1'], [3, '3'], [6, '6'], [12, '12']].map(function(x) { return [x[0], x[1] + ' m']; }), 'w', soloAndamento);
gruppoBottoni('sel-var',   VARS.map(function(v) { return [v, VLAB[v]]; }), 'v', soloAndamento);
gruppoBottoni('sel-var-anni', VARS.map(function(v) { return [v, VLAB[v]]; }), 'vAnni', function() { disegnaClassifiche(VARS.indexOf(ST.vAnni)); sendResize(); });
gruppoBottoni('sel-var-trend', VARS.map(function(v) { return [v, VLAB[v]]; }), 'vTrend', soloAnni);
if (document.getElementById('sel-periodo')) document.getElementById('sel-periodo').addEventListener('change', function() { ST.per = this.value; soloAnni(); });
gruppoBottoni('sel-ord1',  [['caldi', 'Pi\u00f9 caldi'], ['freddi', 'Pi\u00f9 freddi']], 'ord1', function() { disegnaClassifiche(VARS.indexOf(ST.vAnni)); sendResize(); });
gruppoBottoni('sel-ord2',  [['caldi', 'Pi\u00f9 caldi'], ['freddi', 'Pi\u00f9 freddi']], 'ord2', function() { disegnaClassifiche(VARS.indexOf(ST.vAnni)); sendResize(); });
gruppoBottoni('sel-var-clima', VARS.map(function(v) { return [v, VLAB[v]]; }), 'vClima', function() { disegnaClima(); sendResize(); });

var resizeTimer = null, ultimaW = 0;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
        var W = document.querySelector('.pannello').offsetWidth;
        if (W !== ultimaW) { ultimaW = W; build(); }   // i cambi di sola altezza non ridisegnano
    }, 200);
});

build();
ultimaW = document.querySelector('.pannello').offsetWidth;
setTimeout(sendResize, 150);
setTimeout(sendResize, 500);
</script>

</body>
</html>