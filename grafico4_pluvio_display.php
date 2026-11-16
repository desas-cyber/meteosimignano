<?php
/**
 * ============================================================================
 * TENDENZA DELLA PIOGGIA (THEIL-SEN) - grafico4_pluvio_display.php
 * Versione 1.0 - 14/11/2026
 * ============================================================================
 * Iframe di pluvio_stat_display.php (blocco "Tendenza della pioggia").
 * Stessa impostazione del confronto Theil-Sen di grafico_termo_display.php.
 *
 * SELETTORI:
 *   - Periodo:   anno intero, stagioni (inverno = dic dell'anno prima + gen,
 *                feb; l'anno e' quello di gennaio), singoli mesi
 *   - Grandezza: mm cumulati | giorni di pioggia (>= 1 mm) | giorni >= 20 mm
 *
 * GRAFICO:
 *   - barre: scarto di ogni anno dalla MEDIANA del periodo (blu sopra, ocra
 *     sotto, palette delle sequenze); mediana e non media, come nel resto
 *     della pagina pioggia (distribuzioni asimmetriche)
 *   - tratteggio: retta di Theil-Sen (mediana delle pendenze fra le coppie)
 *   - nota: variazione per decennio e test di Mann-Kendall, CON correzione
 *     per i pareggi (i giorni >= 20 mm hanno molti anni a pari valore)
 *
 * CLASSIFICHE: stesso periodo e stessa grandezza; a sinistra i periodi piu'
 * piovosi, a destra i piu' secchi. In rosso l'ultimo anno disponibile; se e'
 * fuori dai primi, riga aggiuntiva con la sua posizione.
 *
 * VALORE DI UN PERIODO: SOMMA dei suoi mesi (la pioggia si cumula), solo se
 * tutti i mesi sono validi. I mm partono dal 2001; giorni di pioggia e
 * giorni >= 20 mm richiedono il dettaglio giornaliero (dal 2004).
 *
 * Dati: api/api_pluvio_trend_data.php (getPluvioTrendData).
 * Comunicazione con la pagina contenitore via postMessage (action: resize).
 * iframeId: stat-iframe-pluvio4
 */

ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../envelop_lettura.php';
require_once __DIR__ . '/api/api_pluvio_trend_data.php';

$response = getPluvioTrendData();

if (!$response['success']) {
    echo "<div style='font-family:Arial;font-size:13px;color:#c00;padding:10px;'>
            Errore: " . htmlspecialchars($response['error'] ?? 'Dati non disponibili') . "
          </div>";
    exit;
}

$meta      = $response['meta'];
$mesi_js   = json_encode($response['mesi'], JSON_UNESCAPED_UNICODE);
$meta_js   = json_encode($meta, JSON_UNESCAPED_UNICODE);
$mesi_lab  = ['', 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
              'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
$ult_disp  = $mesi_lab[(int)substr($meta['ultimo_mese'], 5, 2)] . ' ' . substr($meta['ultimo_mese'], 0, 4);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- grafico4_pluvio_display.php v1.0 - 14/11/2026 -->
    <title>Tendenza della pioggia</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            background: #fff;
            padding: 6px 0 10px 0;
            color: #333;
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
        .pannello { max-width: 95%; margin: 6px auto 0 auto; }

        /* ---- selettori (stile di grafico3_pluvio_display) ---- */
        .sel-riga { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; margin-bottom: 8px; }
        .sel-gruppo { display: inline-flex; align-items: center; gap: 4px; flex-wrap: wrap; }
        .sel-lab { font-size: 9px; color: #999; text-transform: uppercase; letter-spacing: .04em; margin-right: 2px; }
        .sel-periodo {
            font-family: Arial, sans-serif; font-size: 11px; color: #555;
            border: 1px solid #d6d6d6; border-radius: 14px; background: #fff;
            padding: 3px 8px; cursor: pointer;
        }
        .seg { display: inline-flex; border: 1px solid #d6d6d6; border-radius: 14px; overflow: hidden; }
        .seg button {
            font-family: Arial, sans-serif; font-size: 11px; color: #666;
            background: #fff; border: 0; border-left: 1px solid #e4e4e4;
            padding: 4px 11px; cursor: pointer;
        }
        .seg button:first-child { border-left: 0; }
        .seg button.on { background: #5F6B75; color: #fff; }
        .seg button:focus-visible, .sel-periodo:focus-visible { outline: 2px solid rgba(43,109,179,0.45); outline-offset: -2px; }

        .chart-container { position: relative; width: 100%; }
        canvas { display: block; width: 100% !important; }
        .legend {
            display: flex; gap: 12px; flex-wrap: wrap;
            margin-top: 6px; font-size: 10px; color: #666; align-items: center;
        }
        .leg-item { display: flex; align-items: center; gap: 4px; }
        .leg-box  { width: 12px; height: 9px; border-radius: 1px; flex-shrink: 0; }
        .nota { font-size: 10px; color: #666; margin-top: 6px; line-height: 1.45; }

        /* ---- classifiche (stile di grafico_termo_display) ---- */
        .classifiche { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 14px; }
        .rank-tit { font-size: 10px; font-weight: bold; color: #555; margin-bottom: 4px; }
        table.t-rank { border-collapse: collapse; width: 100%; table-layout: fixed; font-size: 11px; }
        .t-rank td { border: 1px solid #bbb; padding: 3px 4px; text-align: left; vertical-align: middle; }
        .t-rank td.pos  { width: 22px; color: #888; text-align: center; }
        .t-rank td.anno { width: 52px; font-weight: bold; }
        .t-rank td.val  { width: 58px; text-align: right; font-variant-numeric: tabular-nums; }
        .t-rank tr.sep td { text-align: center; color: #999; font-size: 10px; padding: 1px 0; border-left: none; border-right: none; }
        .t-rank tr.corr td { color: #D62828; }
        .t-rank .barra { height: 16px; border-radius: 0 3px 3px 0; }

        .stat-footer {
            text-align: center; font-size: 9px; color: #bbb;
            margin: 10px auto 0 auto; max-width: 95%; line-height: 1.5;
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
            .seg button { padding: 4px 8px; font-size: 10px; }
            table.t-rank { font-size: 10px; }
        }
        @media (min-width: 600px) {
            .titolo { font-size: 14px; }
            .seg button, .sel-periodo { font-size: 12px; }
            .legend, .nota { font-size: 11px; }
            .rank-tit { font-size: 12px; }
            table.t-rank { font-size: 12px; }
        }
    </style>
</head>
<body>

<div class="top-bar">
    <span class="titolo">Pioggia per anno, periodi chiusi fino a <?= htmlspecialchars($ult_disp) ?></span>
</div>

<div id="chart-tooltip" class="chart-tooltip"></div>

<div class="pannello">
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
        <span class="sel-gruppo"><span class="sel-lab">Grandezza</span>
            <span class="seg" role="group" aria-label="Grandezza" id="sel-gr"></span>
        </span>
    </div>

    <div class="chart-container" id="cont-trend">
        <canvas id="chart-trend" role="img" aria-label="Scarti annui dalla mediana con la tendenza di Theil-Sen"></canvas>
    </div>
    <div class="legend">
        <span class="leg-item"><span class="leg-box" style="background:#3E8AD6;"></span>sopra la mediana</span>
        <span class="leg-item"><span class="leg-box" style="background:#C69230;"></span>sotto la mediana</span>
        <span class="leg-item"><span style="display:inline-block;width:18px;border-top:2px dashed #333;"></span>retta di Theil&ndash;Sen</span>
    </div>
    <div class="nota" id="nota-trend"></div>

    <div class="classifiche">
        <div><div class="rank-tit" id="rank1-tit"></div><table class="t-rank" id="rank1"></table></div>
        <div><div class="rank-tit" id="rank2-tit"></div><table class="t-rank" id="rank2"></table></div>
    </div>
</div>

<div class="stat-footer" id="footer-note"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
var MESI = <?= $mesi_js ?>;   // [['YYYY-MM', mm|null, gg|null, gg20|null], ...]
var META = <?= $meta_js ?>;

var MOBILE = false;
var chart  = null;

var COL = {
    sopra: '#3E8AD6',   // blu delle sequenze piovose
    sotto: '#C69230',   // ocra delle sequenze secche
    retta: '#333',
    corr:  '#D62828',
    testo: '#999'
};

// Grandezze: indice nella riga di MESI, etichette, unita', decimali
var GR = {
    mm:   { i: 1, bott: 'mm',                         lunga: 'pioggia cumulata',            un: 'mm', dec: 0 },
    gg:   { i: 2, bott: 'Giorni pioggia',             lunga: 'giorni di pioggia',           un: 'gg', dec: 0 },
    gg20: { i: 3, bott: 'Giorni \u2265 20 mm',        lunga: 'giorni con almeno 20 mm',     un: 'gg', dec: 0 }
};

var ST = { per: 'anno', gr: 'mm' };

// Periodi: [mese, scarto d'anno]. Inverno: dicembre dell'anno prima.
var PERIODI = {
    anno: { nome: 'anno intero', stag: false, mesi: [[1,0],[2,0],[3,0],[4,0],[5,0],[6,0],[7,0],[8,0],[9,0],[10,0],[11,0],[12,0]] },
    inv:  { nome: 'inverno',     stag: true,  mesi: [[12,-1],[1,0],[2,0]] },
    pri:  { nome: 'primavera',   stag: true,  mesi: [[3,0],[4,0],[5,0]] },
    est:  { nome: 'estate',      stag: true,  mesi: [[6,0],[7,0],[8,0]] },
    aut:  { nome: 'autunno',     stag: true,  mesi: [[9,0],[10,0],[11,0]] }
};
var NOMI_MESI = ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
for (var mm = 1; mm <= 12; mm++) PERIODI['m' + mm] = { nome: NOMI_MESI[mm - 1], stag: false, mesi: [[mm, 0]] };

function fmt(v, d) {
    if (v == null) return '\u2013';
    return (v < 0 ? '\u2212' : '') + Math.abs(v).toFixed(d == null ? 1 : d).replace('.', ',');
}
function fmtSegno(v, d) { return (v > 0 ? '+' : '') + fmt(v, d); }
function etichettaAnno(y) { return ST.per === 'inv' ? (y - 1) + '/' + String(y).slice(2) : String(y); }

// ---------------------------------------------------------------------------
// Calcoli
// ---------------------------------------------------------------------------
/** Valore del periodo per ogni anno: SOMMA dei suoi mesi, solo se tutti validi. */
function valoriPeriodo(chiave, gr) {
    var idx = {}, col = GR[gr].i;
    MESI.forEach(function(m) { idx[m[0]] = m[col]; });
    var y0 = +MESI[0][0].slice(0, 4), y1 = +MESI[MESI.length - 1][0].slice(0, 4), out = [];
    for (var y = y0; y <= y1 + 1; y++) {
        var s = 0, ok = true;
        PERIODI[chiave].mesi.forEach(function(q) {
            var k = (y + q[1]) + '-' + (q[0] < 10 ? '0' : '') + q[0];
            var v = idx[k];
            if (v == null) ok = false; else s += v;
        });
        if (ok) out.push({ y: y, v: Math.round(s * 10) / 10 });
    }
    return out;
}
function quantile(arr, q) {
    var v = arr.slice().sort(function(a, b) { return a - b; });
    var p = q * (v.length - 1), lo = Math.floor(p), hi = Math.ceil(p);
    return v[lo] + (p - lo) * (v[hi] - v[lo]);
}
function mediana(arr) { return quantile(arr, 0.5); }
function theilSen(pts) {
    var sl = [];
    for (var i = 0; i < pts.length; i++) for (var j = i + 1; j < pts.length; j++) sl.push((pts[j].v - pts[i].v) / (pts[j].y - pts[i].y));
    var b = mediana(sl);
    var a = mediana(pts.map(function(p) { return p.v - b * p.y; }));
    return { b: b, a: a };
}
// Mann-Kendall con correzione della varianza per i pareggi:
// var(S) = [n(n-1)(2n+5) - somma t(t-1)(2t+5)] / 18, t = anni a pari valore
function mannKendall(pts) {
    var n = pts.length, S = 0;
    for (var i = 0; i < n; i++) for (var j = i + 1; j < n; j++) S += Math.sign(pts[j].v - pts[i].v);
    var gruppi = {}, corr = 0;
    pts.forEach(function(p) { gruppi[p.v] = (gruppi[p.v] || 0) + 1; });
    Object.keys(gruppi).forEach(function(k) { var t = gruppi[k]; corr += t * (t - 1) * (2 * t + 5); });
    var varS = (n * (n - 1) * (2 * n + 5) - corr) / 18;
    var z = (S === 0 || varS <= 0) ? 0 : (S - Math.sign(S)) / Math.sqrt(varS);
    return { S: S, z: z, p: 2 * (1 - phi(Math.abs(z))) };
}
function phi(x) {   // funzione di ripartizione normale (Abramowitz-Stegun 7.1.26)
    var t = 1 / (1 + 0.3275911 * x / Math.SQRT2);
    var e = 1 - (((((1.061405429 * t - 1.453152027) * t) + 1.421413741) * t - 0.284496736) * t + 0.254829592) * t * Math.exp(-x * x / 2);
    return 0.5 * (1 + e);
}
/** Passo "tondo" delle tacche: 1, 2, 5, 10, 20, 50... */
function passoTondo(span, nTacche) {
    var grezzo = Math.max(span, 1e-6) / nTacche, p = Math.pow(10, Math.floor(Math.log10(grezzo)));
    var r = grezzo / p;
    return Math.max(1, (r <= 1 ? 1 : r <= 2 ? 2 : r <= 5 ? 5 : 10) * p);
}

// ---------------------------------------------------------------------------
// Grafico
// ---------------------------------------------------------------------------
var trendPlugin = {
    id: 'trendPlugin',
    beforeDatasetsDraw: function(ch) {
        var d = ch.options.plugins.trendPlugin.d, ctx = ch.ctx, ca = ch.chartArea;
        var xp = function(v) { return ch.scales.x.getPixelForValue(v); };
        var yp = function(v) { return ch.scales.y.getPixelForValue(v); };
        var bw = Math.max(3, (xp(1) - xp(0)) - 2);   // barre accostate, 2 px di stacco
        ctx.strokeStyle = 'rgba(128,128,128,0.55)'; ctx.lineWidth = 1;
        ctx.beginPath(); ctx.moveTo(ca.left, yp(0)); ctx.lineTo(ca.right, yp(0)); ctx.stroke();
        d.anni.forEach(function(a) {
            var an = a.v - d.norm;
            ctx.fillStyle = an >= 0 ? COL.sopra : COL.sotto;
            var y0 = yp(Math.max(an, 0)), y1 = yp(Math.min(an, 0));
            ctx.fillRect(xp(a.y) - bw / 2, y0, bw, Math.max(1, y1 - y0));
        });
        ctx.font = '9px Arial'; ctx.fillStyle = COL.testo; ctx.textAlign = 'center'; ctx.textBaseline = 'top';
        var passo = MOBILE ? 4 : 2;
        d.anni.forEach(function(a) {
            if (a.y % passo === 0) ctx.fillText(MOBILE ? "'" + String(a.y).slice(2) : a.y, xp(a.y), ca.bottom + 4);
        });
        var y0 = d.anni[0].y, y1 = d.anni[d.anni.length - 1].y;
        ctx.strokeStyle = COL.retta; ctx.lineWidth = 1.6; ctx.setLineDash([6, 4]);
        ctx.beginPath();
        ctx.moveTo(xp(y0), yp(d.ts.a + d.ts.b * y0 - d.norm));
        ctx.lineTo(xp(y1), yp(d.ts.a + d.ts.b * y1 - d.norm));
        ctx.stroke();
        ctx.setLineDash([]);
    }
};

function costruisci(W) {
    if (chart) { chart.destroy(); chart = null; }
    var g = GR[ST.gr], P = PERIODI[ST.per];
    var anni = valoriPeriodo(ST.per, ST.gr);
    var nota = document.getElementById('nota-trend');
    var H = MOBILE ? Math.max(200, Math.round(W * 0.60)) : Math.max(200, Math.min(Math.round(W * 0.32), 260));
    document.getElementById('cont-trend').style.height = H + 'px';
    document.getElementById('chart-trend').style.height = H + 'px';

    if (anni.length < 5) {
        nota.textContent = 'Dati insufficienti per questo periodo.';
        disegnaClassifiche(anni);
        return;
    }
    var norm = mediana(anni.map(function(a) { return a.v; }));
    var ts = theilSen(anni), mk = mannKendall(anni);
    var d = { anni: anni, norm: norm, ts: ts };

    var lo = 0, hi = 0;
    anni.forEach(function(a) { lo = Math.min(lo, a.v - norm); hi = Math.max(hi, a.v - norm); });
    var passoY = passoTondo(hi - lo, MOBILE ? 4 : 5);
    chart = new Chart(document.getElementById('chart-trend').getContext('2d'), {
        type: 'scatter',
        data: { datasets: [{ data: [], pointRadius: 0 }] },
        options: {
            animation: false, responsive: true, maintainAspectRatio: false, events: [],
            plugins: { legend: { display: false }, tooltip: { enabled: false }, trendPlugin: { d: d } },
            layout: { padding: { left: 2, right: MOBILE ? 8 : 14, top: 8, bottom: 16 } },
            scales: {
                x: { min: anni[0].y - 0.5, max: anni[anni.length - 1].y + 0.5, display: false },
                y: { min: Math.floor(lo / passoY) * passoY, max: Math.ceil(hi / passoY) * passoY,
                     grid: { color: 'rgba(128,128,128,0.1)' },
                     ticks: { stepSize: passoY, font: { size: MOBILE ? 8 : 9 }, color: '#666',
                              callback: function(v) { return (v > 0 ? '+' : '') + v + (MOBILE ? '' : ' ' + g.un); } } }
            }
        },
        plugins: [trendPlugin]
    });
    tooltipCanvas(chart, function(ch, mx, my) {
        var ca = ch.chartArea;
        if (mx < ca.left || mx > ca.right || my < ca.top || my > ca.bottom) return null;
        var y = Math.round(ch.scales.x.getValueForPixel(mx));
        var a = anni.filter(function(z) { return z.y === y; })[0];
        if (!a) return (ST.per === 'inv' ? (y - 1) + '/' + String(y).slice(2) : y) + '<br>dati incompleti';
        return '<b>' + P.nome.charAt(0).toUpperCase() + P.nome.slice(1) + ' ' + etichettaAnno(a.y) + '</b><br>'
             + fmt(a.v, g.un === 'mm' ? 1 : 0) + ' ' + g.un + ' \u00b7 scarto ' + fmtSegno(a.v - norm, g.dec) + ' ' + g.un
             + '<br>tendenza in quell\u2019anno: ' + fmtSegno(ts.a + ts.b * a.y - norm, g.un === 'mm' ? 0 : 1) + ' ' + g.un;
    });

    var dec = ts.b * 10;
    var giudizio = mk.p < 0.01 ? 'tendenza significativa (p < 0,01)'
                 : mk.p < 0.05 ? 'tendenza significativa (p = ' + fmt(mk.p, 3) + ')'
                 : 'tendenza non significativa (p = ' + fmt(mk.p, 2) + ')';
    giudizio = '<b style="color:' + (mk.p < 0.05 ? '#1E8E3E' : '#D62828') + ';">' + giudizio + '</b>';
    var lunga = g.lunga.charAt(0).toUpperCase() + g.lunga.slice(1);
    nota.innerHTML = '<b>' + lunga + ', ' + P.nome + ': ' + (dec >= 0 ? '+' : '\u2212')
        + fmt(Math.abs(dec), g.un === 'mm' ? 0 : 1) + ' ' + g.un + ' per decennio</b>, ' + giudizio
        + ' (Mann\u2013Kendall, ' + anni.length + ' anni). '
        + 'Barre: scarto dalla mediana ' + etichettaAnno(anni[0].y) + '\u2013' + etichettaAnno(anni[anni.length - 1].y)
        + ' (' + fmt(norm, g.un === 'mm' ? 0 : 1) + ' ' + g.un + '); '
        + 'tratteggio: retta di Theil\u2013Sen, mediana delle pendenze fra tutte le coppie di anni.'
        + (ST.gr !== 'mm' ? ' I giorni richiedono il dettaglio giornaliero: la serie parte pi\u00f9 tardi dei mm.' : '');
    disegnaClassifiche(anni);
}

// ---------------------------------------------------------------------------
// Classifiche: sinistra piu' piovosi, destra piu' secchi
// ---------------------------------------------------------------------------
function disegnaClassifiche(righe) {
    var g = GR[ST.gr], P = PERIODI[ST.per];
    var nome = P.nome.charAt(0).toUpperCase() + P.nome.slice(1);
    var tabs = [
        { id: 'rank1', secchi: false, suff: P.stag ? ', le pi\u00f9 piovose' : ', i pi\u00f9 piovosi' },
        { id: 'rank2', secchi: true,  suff: P.stag ? ', le pi\u00f9 secche'  : ', i pi\u00f9 secchi' }
    ];
    var suffGr = ' \u00b7 ' + (ST.gr === 'mm' ? 'mm' : ST.gr === 'gg' ? 'giorni di pioggia' : 'giorni \u2265 20 mm');
    tabs.forEach(function(t) {
        document.getElementById(t.id + '-tit').textContent = nome + t.suff + suffGr;
        if (!righe.length) {
            document.getElementById(t.id).innerHTML = '<tbody><tr class="sep"><td colspan="4">dati insufficienti</td></tr></tbody>';
            return;
        }
        var evid = righe[righe.length - 1].y;   // valoriPeriodo restituisce gli anni in ordine crescente
        var tutti = righe.map(function(r) { return r.v; });
        var hi = Math.max.apply(null, tutti), lo = Math.min.apply(null, tutti);
        var marg = (hi - lo) * 0.08 + 0.5, span = hi - lo + marg;
        // a parita' di valore prima l'anno piu' recente
        var ord = righe.slice().sort(function(a, b) { return (t.secchi ? a.v - b.v : b.v - a.v) || b.y - a.y; });
        var nMax = MOBILE ? 5 : 6;
        var riga = function(r, i) {
            var w = t.secchi ? (hi + marg - r.v) / span : (r.v - lo + marg) / span;
            return '<tr class="' + (r.y === evid ? 'corr' : '') + '"><td class="pos">' + (i + 1) + '</td>'
                 + '<td class="anno">' + etichettaAnno(r.y) + '</td>'
                 + '<td><div class="barra" style="width:' + Math.round(w * 100) + '%;background:' + (t.secchi ? COL.sotto : COL.sopra) + ';"></div></td>'
                 + '<td class="val">' + fmt(r.v, ST.gr === 'mm' ? 1 : 0) + (ST.gr === 'mm' ? '' : ' gg') + '</td></tr>';
        };
        var h = ord.slice(0, nMax).map(riga).join('');
        var iEv = ord.findIndex(function(r) { return r.y === evid; });
        if (iEv >= nMax) {
            h += '<tr class="sep"><td colspan="4">&middot;&middot;&middot; su ' + ord.length + ' anni</td></tr>' + riga(ord[iEv], iEv);
        }
        document.getElementById(t.id).innerHTML = '<tbody>' + h + '</tbody>';
    });
}

// ---------------------------------------------------------------------------
// Tooltip (stesso comportamento degli altri iframe pluvio)
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
        if (e.touches.length === 1) { e.preventDefault(); aggiorna(e.touches[0].clientX, e.touches[0].clientY); }
    }, { passive: false });
    canvas.addEventListener('touchstart', function(e) {
        if (e.touches.length === 1) aggiorna(e.touches[0].clientX, e.touches[0].clientY);
    }, { passive: true });
    canvas.addEventListener('touchend', function() { setTimeout(nascondiTooltip, 1200); });
}

// ---------------------------------------------------------------------------
// Build
// ---------------------------------------------------------------------------
function larghezza() { return document.querySelector('.pannello').offsetWidth || 400; }
function build() {
    var W = larghezza();
    MOBILE = W < 480;
    costruisci(W);
    scriviFooter();
    sendResize();
}
function scriviFooter() {
    document.getElementById('footer-note').textContent =
        'aggiornato: ' + META.generato_il
        + ' \u2022 periodo valido solo se tutti i suoi mesi hanno almeno il ' + Math.round(META.copertura_min * 100) + '% dei giorni'
        + ' \u2022 mm dal ' + META.anno_min + ', anche senza dettaglio giornaliero; giorni solo con dettaglio giornaliero'
        + ' \u2022 giorno di pioggia: \u2265 ' + META.soglia + ' mm'
        + ' \u2022 inverno: dicembre dell\u2019anno prima + gennaio e febbraio'
        + ' \u2022 Mann\u2013Kendall con correzione per gli anni a pari valore';
}
function sendResize() {
    window.parent.postMessage({
        action:   'resize',
        iframeId: 'stat-iframe-pluvio4',
        height:   document.body.scrollHeight
    }, '*');
}

// Selettori
(function() {
    var g = document.getElementById('sel-gr');
    Object.keys(GR).forEach(function(k) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = GR[k].bott;
        b.className = ST.gr === k ? 'on' : '';
        b.setAttribute('aria-pressed', String(ST.gr === k));
        b.addEventListener('click', function() {
            if (ST.gr === k) return;
            ST.gr = k;
            g.querySelectorAll('button').forEach(function(o) { o.classList.remove('on'); o.setAttribute('aria-pressed', 'false'); });
            b.classList.add('on'); b.setAttribute('aria-pressed', 'true');
            nascondiTooltip(); build();
        });
        g.appendChild(b);
    });
    document.getElementById('sel-periodo').addEventListener('change', function() {
        ST.per = this.value; nascondiTooltip(); build();
    });
})();

var resizeTimer = null, ultimaW = 0;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
        var W = larghezza();
        if (W !== ultimaW) { ultimaW = W; build(); }   // i cambi di sola altezza non ridisegnano
    }, 200);
});

build();
ultimaW = larghezza();
setTimeout(sendResize, 150);
setTimeout(sendResize, 500);
</script>

</body>
</html>
