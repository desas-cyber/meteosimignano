<?php
/**
 * ============================================================================
 * GRAFICO PIOGGIA RISPETTO AL CLIMA - grafico_stat3bis_display.php
 * ============================================================================
 * Per ogni anno dal 2001 due barre affiancate:
 *   blu   = anno intero (1 gen - 31 dic)
 *   verde = stesso periodo (1 gen - giorno/mese della data scelta)
 * Ultima colonna = anno della data scelta:
 *   verde = periodo corrente, con sopra il box plot del clima di periodo
 *           (mediana, P25-P75, baffi P10-P90), vuoto
 *   blu   = solo se l'anno e' gia' concluso: come e' finito
 * Clima dell'anno intero: linee orizzontali su tutto il grafico
 *   (P10/P90 punteggiate, P25/P75 tratteggiate, mediana piena) con le
 *   fasce P10-P90 e P25-P75 leggermente velate.
 *
 * 4 pulsanti metrica (cambio lato client, nessuna nuova richiesta):
 *   mm | gg pioggia | seq. piovosa | seq. secca
 *
 * PARAMETRI GET:
 *   ?data=YYYY-MM-DD  data di riferimento (default: ultimo giorno disponibile)
 *   ?m=mm|gg|seq_piov|seq_secca  metrica mostrata (default: mm)
 *
 * Comunicazione con stat_display.php via postMessage:
 *   - resize : aggiorna altezza iframe 'stat-iframe-tab3bis'
 */

ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../envelop_lettura.php';
require_once __DIR__ . '/api/api_tabella_stat3bis_data.php';

$response = getGrafico3bisData($_GET['data'] ?? null);

if (!$response['success']) {
    echo "<div style='font-family:Arial;font-size:13px;color:#c00;padding:10px;'>
            Errore: " . htmlspecialchars($response['error'] ?? 'Dati non disponibili') . "
          </div>";
    exit;
}

$meta = $response['meta'];

$metriche = [
    'mm'        => 'mm',
    'gg'        => 'gg pioggia',
    'seq_piov'  => 'seq. piovosa',
    'seq_secca' => 'seq. secca',
];
$m_sel = (isset($_GET['m']) && isset($metriche[$_GET['m']])) ? $_GET['m'] : 'mm';

$dati_js = json_encode([
    'meta'      => $meta,
    'anni'      => $response['anni'],
    'corrente'  => $response['corrente'],
    'fine_anno' => $response['fine_anno'],
    'stat'      => $response['stat'],
], JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pioggia rispetto al clima</title>
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
            flex-wrap: wrap;
            max-width: 95%;
            margin: 4px auto 6px auto;
            gap: 6px;
        }
        .data-nav { display: flex; align-items: center; gap: 4px; }
        .data-input {
            height: 24px;
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 3px;
            background: #fff;
            padding: 0 4px;
        }
        .data-input:hover, .data-input:focus { border-color: #3366cc; outline: none; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 24px;
            padding: 0 8px;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
            border-radius: 3px;
            border: 1px solid #ccc;
            background: #f5f5f5;
            color: #444;
            text-decoration: none;
            user-select: none;
            transition: all 0.15s ease;
        }
        .btn:hover { border-color: #3366cc; color: #3366cc; background: #eef3ff; }
        .btn.on { background: #3366cc; border-color: #3366cc; color: #fff; }
        .btn-metriche { display: flex; gap: 4px; flex-wrap: wrap; }
        .info {
            max-width: 95%;
            margin: 0 auto 2px auto;
            font-size: 11px;
            color: #666;
            min-height: 14px;
        }
        .asterisco { color: #c00; font-size: 0.85em; vertical-align: super; }
        .chart-container {
            max-width: 95%;
            margin: 0 auto;
            position: relative;
        }
        canvas { display: block; width: 100% !important; }
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
        .legend {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            max-width: 95%;
            margin: 6px auto 0 auto;
            font-size: 10px;
            color: #666;
            align-items: center;
        }
        .leg-item { display: flex; align-items: center; gap: 4px; }
        .leg-sq { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }
        .leg-tit { color: #999; min-width: 70px; }
        .legend + .legend { margin-top: 3px; }
        .stat-footer {
            text-align: center;
            font-size: 9px;
            color: #bbb;
            margin-top: 6px;
            max-width: 95%;
            margin-left: auto;
            margin-right: auto;
        }
        @media (min-width: 600px) {
            .legend { font-size: 11px; }
            .info { font-size: 12px; }
        }
    </style>
</head>
<body>

<div class="top-bar">
    <div class="data-nav">
        <input type="date" id="data-sel" class="data-input"
               value="<?= htmlspecialchars($meta['data']) ?>"
               min="<?= htmlspecialchars($meta['data_min']) ?>"
               max="<?= htmlspecialchars($meta['data_max']) ?>">
        <span class="btn" id="btn-reset" title="Ripristina ultimo giorno">&#8635;</span>
    </div>
    <div class="btn-metriche" id="btn-metriche">
        <?php foreach ($metriche as $mid => $lbl): ?>
        <span class="btn<?= $mid === $m_sel ? ' on' : '' ?>" data-m="<?= $mid ?>"><?= $lbl ?></span>
        <?php endforeach; ?>
    </div>
</div>

<div class="info" id="info"></div>

<div id="chart-tooltip" class="chart-tooltip"></div>

<div class="chart-container">
    <canvas id="mainChart" role="img" aria-label="Pioggia per anno: anno intero e periodo al <?= htmlspecialchars($meta['data_txt']) ?>"></canvas>
</div>

<div class="legend">
    <span class="leg-item"><span class="leg-sq" style="background:#9DC3E6;"></span>anno intero</span>
    <span class="leg-item"><span class="leg-sq" style="background:#70AD47;"></span><?= htmlspecialchars($meta['periodo_txt']) ?></span>
    <span class="leg-item"><span class="leg-sq" style="background:#70AD47;color:#000;font-size:7px;font-weight:bold;line-height:10px;text-align:center;width:14px;">P</span>percentile del periodo</span>
</div>
<div class="legend">
    <span class="leg-tit">clima anno:</span>
    <span class="leg-item">
        <svg width="18" height="6" aria-hidden="true"><line x1="0" y1="3" x2="18" y2="3" stroke="#2E75B6" stroke-width="1.5"/></svg>mediana
    </span>
    <span class="leg-item">
        <svg width="18" height="10" aria-hidden="true"><rect x="0" y="0" width="18" height="10" fill="rgba(46,117,182,0.17)"/><line x1="0" y1="5" x2="18" y2="5" stroke="rgba(46,117,182,0.65)" stroke-dasharray="5,3"/></svg>P25&ndash;P75
    </span>
    <span class="leg-item">
        <svg width="18" height="10" aria-hidden="true"><rect x="0" y="0" width="18" height="10" fill="rgba(46,117,182,0.07)"/><line x1="0" y1="5" x2="18" y2="5" stroke="rgba(46,117,182,0.55)" stroke-dasharray="2,3"/></svg>P10&ndash;P90
    </span>
</div>
<div class="legend">
    <span class="leg-tit">clima periodo:</span>
    <span class="leg-item">
        <svg width="14" height="14" aria-hidden="true">
            <line x1="7" y1="0" x2="7" y2="14" stroke="#375623"/>
            <rect x="2.5" y="3.5" width="9" height="7" fill="none" stroke="#375623"/>
            <line x1="2.5" y1="7" x2="11.5" y2="7" stroke="#375623" stroke-width="2"/>
        </svg>mediana &middot; box P25&ndash;P75 &middot; baffi P10&ndash;P90
    </span>
</div>

<div class="stat-footer" id="footer-note"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
var DATI   = <?= $dati_js ?>;
var M      = '<?= $m_sel ?>';

var C_ANNO = '#9DC3E6';   // barra anno intero
var C_PER  = '#70AD47';   // barra periodo
var C_CLA  = '#2E75B6';   // clima anno (linee)
var C_CLP  = '#375623';   // clima periodo (box)

var UNITA  = { mm: 'mm', gg: 'gg', seq_piov: 'gg', seq_secca: 'gg' };
var NOMI   = { mm: 'pioggia', gg: 'giorni di pioggia', seq_piov: 'seq. piovosa max', seq_secca: 'seq. secca max' };

var mainChart    = null;
var barreTooltip = [];   // { x1, x2, tipo: 'anno'|'ultimo', riga }

function fmt(v) {
    if (v === null || v === undefined) return 'n.d.';
    return M === 'mm' ? (Math.round(v * 10) / 10).toFixed(1) + ' mm'
                      : Math.round(v) + ' gg';
}
function valAnno(r) { return r.v_anno ? r.v_anno[M] : null; }
function valPer(r)  { return r.v_per  ? r.v_per[M]  : null; }

// Passo "tondo" per l'asse Y (circa 5 tacche)
function passoAsse(max) {
    var passi = [1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500];
    var grezzo = max / 6;
    for (var i = 0; i < passi.length; i++) if (passi[i] >= grezzo) return passi[i];
    return 1000;
}

// ---------------------------------------------------------------------------
// PLUGIN DI DISEGNO
// ---------------------------------------------------------------------------
var graficoPlugin = {
    id: 'g3bis',
    afterDraw: function(chart) {
        barreTooltip = [];
        var ctx = chart.ctx;
        var xS  = chart.scales.x, yS = chart.scales.y;
        function xp(v) { return xS.getPixelForValue(v); }
        function yp(v) { return yS.getPixelForValue(v); }

        var anni  = DATI.anni;
        var N     = anni.length;
        var yBase = yp(0);
        var yTop  = yp(yS.max);
        var sa    = DATI.stat.anno[M];
        var sp    = DATI.stat.periodo[M];

        // ---- 1. fasce clima anno (dietro le barre) ----
        var xL = xS.left, xR = xS.right;
        if (sa) {
            ctx.fillStyle = 'rgba(46,117,182,0.07)';
            ctx.fillRect(xL, yp(sa.p90), xR - xL, yp(sa.p10) - yp(sa.p90));
            ctx.fillStyle = 'rgba(46,117,182,0.10)';
            ctx.fillRect(xL, yp(sa.p75), xR - xL, yp(sa.p25) - yp(sa.p75));
        }

        // ---- 2. barre anni passati: blu | verde ----
        ctx.font = '9px Arial';
        anni.forEach(function(r, i) {
            var x1 = xp(i + 0.10), xm = xp(i + 0.50), x2 = xp(i + 0.90);
            var va = valAnno(r), vp = valPer(r);

            if (va !== null) {
                var ta = yp(va);
                ctx.fillStyle = C_ANNO;
                ctx.fillRect(x1, ta, xm - x1, yBase - ta);
                if (r.nod) {
                    // tratteggio: totale annuo senza dettaglio giornaliero
                    ctx.save();
                    ctx.beginPath(); ctx.rect(x1, ta, xm - x1, yBase - ta); ctx.clip();
                    ctx.strokeStyle = 'rgba(255,255,255,0.75)';
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    for (var k = x1 - (yBase - ta); k < xm; k += 4) {
                        ctx.moveTo(k, yBase); ctx.lineTo(k + (yBase - ta), ta);
                    }
                    ctx.stroke();
                    ctx.restore();
                }
            }
            if (vp !== null) {
                var tp = yp(vp);
                ctx.fillStyle = C_PER;
                ctx.fillRect(xm, tp, x2 - xm, yBase - tp);
            }
            if (va === null && vp === null) {
                ctx.fillStyle = '#ccc';
                ctx.textAlign = 'center'; ctx.textBaseline = 'bottom';
                ctx.fillText('n.d.', (x1 + x2) / 2, yBase - 2);
            }

            barreTooltip.push({ x1: x1, x2: x2, tipo: 'anno', riga: r });

            // etichette ogni 5 anni, ma non a ridosso di quella dell'ultima colonna
            if ((i === 0 || r.anno % 5 === 0) && i < N - 2) {
                ctx.fillStyle = '#999';
                ctx.textAlign = 'center'; ctx.textBaseline = 'top';
                ctx.fillText(r.anno, (x1 + x2) / 2, yBase + 3);
            }
        });

        // ---- 3. ultima colonna ----
        var xSep = xp(N + 0.2);
        ctx.strokeStyle = 'rgba(128,128,128,0.25)';
        ctx.lineWidth = 1;
        ctx.setLineDash([3, 3]);
        ctx.beginPath(); ctx.moveTo(xSep, yTop); ctx.lineTo(xSep, yBase); ctx.stroke();
        ctx.setLineDash([]);

        var cA = xp(N + 1.0), cP = xp(N + 1.8);        // centri: blu | verde
        var wB = xp(N + 0.72) - xp(N);                  // larghezza barra
        var wBox = xp(N + 0.90) - xp(N);                // larghezza box

        var fin = DATI.fine_anno ? DATI.fine_anno[M] : null;
        if (fin !== null) {
            var tf = yp(fin);
            ctx.fillStyle = C_ANNO;
            ctx.fillRect(cA - wB / 2, tf, wB, yBase - tf);
        }
        var cur = DATI.corrente.v[M];
        var tc  = yp(cur);
        ctx.fillStyle = C_PER;
        ctx.fillRect(cP - wB / 2, tc, wB, yBase - tc);

        // box plot clima periodo, vuoto, sopra la barra verde
        if (sp) {
            var y10 = yp(sp.p10), y25 = yp(sp.p25), y50 = yp(sp.p50),
                y75 = yp(sp.p75), y90 = yp(sp.p90);
            ctx.strokeStyle = C_CLP;
            ctx.lineWidth = 1.2;
            ctx.beginPath();
            ctx.moveTo(cP, y90); ctx.lineTo(cP, y75);
            ctx.moveTo(cP, y25); ctx.lineTo(cP, y10);
            ctx.moveTo(cP - wBox * 0.25, y90); ctx.lineTo(cP + wBox * 0.25, y90);
            ctx.moveTo(cP - wBox * 0.25, y10); ctx.lineTo(cP + wBox * 0.25, y10);
            ctx.stroke();
            ctx.strokeRect(cP - wBox / 2, y75, wBox, y25 - y75);
            ctx.lineWidth = 2.5;
            ctx.beginPath(); ctx.moveTo(cP - wBox / 2, y50); ctx.lineTo(cP + wBox / 2, y50); ctx.stroke();
            ctx.lineWidth = 1;
        }

        // percentile del periodo: dentro la barra verde, scritto in verticale
        // dal basso; se la barra e' troppo corta va sopra, in orizzontale
        var ptxt = DATI.corrente.perc[M];
        if (ptxt) {
            ctx.font = 'bold ' + (wB < 13 ? 8 : 10) + 'px Arial';   // su telefono la barra e' stretta
            var lw = ctx.measureText(ptxt).width;
            if (yBase - tc >= lw + 8) {
                ctx.save();
                ctx.translate(cP, yBase - 4);
                ctx.rotate(-Math.PI / 2);
                ctx.fillStyle = '#000';
                ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
                ctx.fillText(ptxt, 0, 0);
                ctx.restore();
            } else {
                ctx.fillStyle = '#333';
                ctx.textAlign = 'center'; ctx.textBaseline = 'bottom';
                ctx.fillText(ptxt, cP, Math.min(tc, sp ? yp(sp.p90) : tc) - 4);
            }
        }
        ctx.fillStyle = '#333';
        ctx.font = 'bold 9px Arial';
        ctx.textAlign = 'center'; ctx.textBaseline = 'top';
        ctx.fillText(DATI.meta.anno, (cA + cP) / 2, yBase + 3);

        barreTooltip.push({ x1: xSep, x2: xp(N + 2.6), tipo: 'ultimo' });

        // ---- 4. linee clima anno (sopra le barre); riferimenti in legenda ----
        if (sa) {
            var linee = [
                { v: sa.p90, dash: [2, 3], w: 1,   a: 0.55 },
                { v: sa.p75, dash: [5, 3], w: 1,   a: 0.65 },
                { v: sa.p50, dash: [],     w: 1.5, a: 0.9  },
                { v: sa.p25, dash: [5, 3], w: 1,   a: 0.65 },
                { v: sa.p10, dash: [2, 3], w: 1,   a: 0.55 },
            ];
            linee.forEach(function(l) {
                var y = yp(l.v);
                ctx.strokeStyle = 'rgba(46,117,182,' + l.a + ')';
                ctx.lineWidth = l.w;
                ctx.setLineDash(l.dash);
                ctx.beginPath(); ctx.moveTo(xL, y); ctx.lineTo(xR, y); ctx.stroke();
            });
            ctx.setLineDash([]);
            ctx.lineWidth = 1;
        }
    }
};

// ---------------------------------------------------------------------------
// COSTRUZIONE
// ---------------------------------------------------------------------------
function build() {
    if (mainChart) { mainChart.destroy(); mainChart = null; }

    var sa = DATI.stat.anno[M], sp = DATI.stat.periodo[M];
    var valori = [DATI.corrente.v[M]];
    DATI.anni.forEach(function(r) { valori.push(valAnno(r), valPer(r)); });
    if (DATI.fine_anno) valori.push(DATI.fine_anno[M]);
    if (sa) valori.push(sa.p90);
    if (sp) valori.push(sp.p90);
    var max = Math.max.apply(null, valori.filter(function(v) { return v !== null; }).concat([1]));
    var passo = passoAsse(max);

    var W = document.querySelector('.chart-container').offsetWidth || 400;
    var H = Math.max(260, Math.min(Math.round(W * 0.55), 380));
    document.getElementById('mainChart').style.height = H + 'px';
    document.querySelector('.chart-container').style.height = H + 'px';

    var ctx = document.getElementById('mainChart').getContext('2d');
    mainChart = new Chart(ctx, {
        type: 'scatter',
        data: { datasets: [{ data: [], pointRadius: 0 }] },
        options: {
            animation: false,
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            layout: { padding: { left: 4, right: 4, top: 16, bottom: 16 } },
            scales: {
                x: { min: 0, max: DATI.anni.length + 2.8, display: false },
                y: {
                    min: 0,
                    // margine del 4% sopra la barra piu' alta, arrotondato a 1/5 del passo
                    max: Math.ceil(max * 1.04 / (passo / 5)) * (passo / 5),
                    position: 'right',
                    grid: { color: 'rgba(128,128,128,0.1)' },
                    ticks: {
                        font: { size: 9 },
                        color: '#5B9BD5',
                        stepSize: passo,
                        // la tacca in cima (non multipla del passo) resta senza etichetta
                        callback: function(v) { return (v % passo === 0) ? v + ' ' + UNITA[M] : ''; }
                    }
                }
            }
        },
        plugins: [graficoPlugin]
    });

    aggiornaTesti();
    sendResize();
}

function aggiornaTesti() {
    var c  = DATI.corrente, sa = DATI.stat.anno[M], sp = DATI.stat.periodo[M];
    var s  = 'Al ' + DATI.meta.data_txt + ': ' + NOMI[M] + ' ' + fmt(c.v[M]);
    if (c.scarso) s += '<span class="asterisco">*</span>';
    if (c.perc[M] && sp) s += ' \u2022 ' + c.perc[M] + ' del periodo (' + sp.n + ' anni)';
    if (M === 'mm' && sa && sa.p50 > 0) {
        s += ' \u2022 ' + Math.round(c.v.mm / sa.p50 * 100) + '% dell\u2019anno mediano';
    }
    document.getElementById('info').innerHTML = s;

    var f = [];
    if (sa) f.push('clima anno ' + sa.da + '\u2013' + sa.a + ' (' + sa.n + ' anni)');
    if (sp) f.push('periodo ' + sp.da + '\u2013' + sp.a + ' (' + sp.n + ' anni)');
    if (!sp) f.push('anni insufficienti per il clima di periodo');
    if (M === 'mm' && DATI.anni.some(function(r) { return r.nod; })) f.push('tratteggio: senza dettaglio giornaliero');
    if (c.scarso) f.push('* copertura <75%');
    f.push('aggiornato: ' + DATI.meta.generato_il);
    document.getElementById('footer-note').textContent = f.join(' \u2022 ');
}

// ---------------------------------------------------------------------------
// TOOLTIP
// ---------------------------------------------------------------------------
var tooltipEl = document.getElementById('chart-tooltip');

function testoTooltip(b) {
    var g = DATI.meta.giorno_txt, sa = DATI.stat.anno[M], sp = DATI.stat.periodo[M];
    if (b.tipo === 'ultimo') {
        var s = DATI.meta.anno + ' al ' + g + ': ' + fmt(DATI.corrente.v[M]);
        if (DATI.corrente.perc[M]) s += ' (' + DATI.corrente.perc[M] + ')';
        if (sp) s += '<br>periodo \u2014 med ' + fmt(sp.p50) + ' \u2022 P25\u201375 ' + fmt(sp.p25) + '\u2013' + fmt(sp.p75);
        if (sa) s += '<br>anno \u2014 med ' + fmt(sa.p50) + ' \u2022 P25\u201375 ' + fmt(sa.p25) + '\u2013' + fmt(sa.p75);
        if (DATI.fine_anno) s += '<br>chiuso a ' + fmt(DATI.fine_anno[M]);
        return s;
    }
    var r = b.riga, va = valAnno(r), vp = valPer(r);
    return r.anno
        + '<br>anno: ' + (va !== null ? fmt(va) + (r.nod ? ' (senza giornalieri)' : '') : 'incompleto')
        + '<br>1 gen \u2192 ' + g + ': ' + (vp !== null ? fmt(vp) : 'n.d.');
}

function aggiornaTooltip(clientX, clientY) {
    var rect = document.getElementById('mainChart').getBoundingClientRect();
    var mx = clientX - rect.left, my = clientY - rect.top;
    var trovato = null;
    if (my >= 0 && my <= rect.height) {
        for (var i = 0; i < barreTooltip.length; i++) {
            if (mx >= barreTooltip[i].x1 && mx <= barreTooltip[i].x2) { trovato = barreTooltip[i]; break; }
        }
    }
    if (!trovato) { tooltipEl.style.display = 'none'; return; }

    tooltipEl.innerHTML = testoTooltip(trovato);
    tooltipEl.style.display = 'block';
    var left = clientX - tooltipEl.offsetWidth / 2;
    left = Math.max(4, Math.min(left, window.innerWidth - tooltipEl.offsetWidth - 4));
    tooltipEl.style.left = left + 'px';
    tooltipEl.style.top  = Math.max(4, clientY - tooltipEl.offsetHeight - 10) + 'px';
}
function nascondiTooltip() { tooltipEl.style.display = 'none'; }

function aggiungiListenerTooltip() {
    var canvas = document.getElementById('mainChart');
    canvas.addEventListener('mousemove', function(e) { aggiornaTooltip(e.clientX, e.clientY); });
    canvas.addEventListener('mouseleave', nascondiTooltip);
    canvas.addEventListener('touchmove', function(e) {
        if (e.touches.length > 0) {
            e.preventDefault();
            aggiornaTooltip(e.touches[0].clientX, e.touches[0].clientY);
        }
    }, { passive: false });
    canvas.addEventListener('touchend', nascondiTooltip);
}

// ---------------------------------------------------------------------------
// CONTROLLI
// ---------------------------------------------------------------------------
function navigaA(data) {
    var p = [];
    if (data) p.push('data=' + encodeURIComponent(data));
    if (M !== 'mm') p.push('m=' + encodeURIComponent(M));
    window.location.href = window.location.pathname + (p.length ? '?' + p.join('&') : '');
}

document.getElementById('data-sel').addEventListener('change', function() {
    var v = this.value;
    if (v && v >= this.min && v <= this.max && v !== DATI.meta.data) navigaA(v);
});
document.getElementById('btn-reset').addEventListener('click', function() { navigaA(null); });

document.getElementById('btn-metriche').addEventListener('click', function(e) {
    var b = e.target.closest('.btn');
    if (!b || b.dataset.m === M) return;
    M = b.dataset.m;
    this.querySelectorAll('.btn').forEach(function(x) { x.classList.toggle('on', x === b); });
    // aggiorna l'URL senza ricaricare: un cambio data successivo mantiene la metrica
    try {
        var u = new URL(window.location.href);
        if (M === 'mm') u.searchParams.delete('m'); else u.searchParams.set('m', M);
        history.replaceState(null, '', u.pathname + u.search);
    } catch (err) {}
    build();
});

// ---- resize dinamico iframe ----
function sendResize() {
    window.parent.postMessage({
        action:   'resize',
        iframeId: 'stat-iframe-tab3bis',
        height:   document.body.scrollHeight
    }, '*');
}

var resizeTimer = null;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(build, 200);
});

build();
aggiungiListenerTooltip();
setTimeout(sendResize, 150);
setTimeout(sendResize, 500);
</script>

</body>
</html>