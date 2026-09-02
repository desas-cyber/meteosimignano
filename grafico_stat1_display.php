<?php
/**
 * ============================================================================
 * GRAFICO STAT1 - stat1_grafico.php
 * ============================================================================
 * Grafico orizzontale temperatura su 4 zone temporali:
 *   oggi / 10 giorni / 30 giorni / anno
 *
 * Per ogni zona:
 *   - linea verticale stelo (da min assoluto a max assoluto)
 *   - nuvola di punti rosa (tutti i valori mx, mn, avg)
 *   - linee verticali: media max (rossa), media (grigia), media min (blu)
 *   - pallini bordati: max assoluta (rosso), min assoluta (blu)
 *   - barra pioggia in alto (semitrasparente blu, asse mm separato)
 *
 * PARAMETRI GET:
 *   ?data=YYYY-MM-DD  giorno di riferimento (default: ieri)
 *
 * Comunicazione con stat_display.php via postMessage:
 *   - resize  : aggiorna altezza iframe
 *   - tornaTabella : torna a tabella_stat_display.php
 */

ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../envelop_lettura.php';
require_once __DIR__ . '/api/api_tabella_stat_data.php';

$response = getGrafico1Data();

if (!$response['success']) {
    echo "<div style='font-family:Arial;font-size:13px;color:#c00;padding:10px;'>
            Errore: " . htmlspecialchars($response['error'] ?? 'Dati non disponibili') . "
          </div>";
    exit;
}

$ref         = $response['ref'];
$giorno_sel  = $response['giorno_sel'];
$periodi_js  = json_encode($response['periodi'],  JSON_UNESCAPED_UNICODE);
$labels_js   = json_encode($response['labels'],   JSON_UNESCAPED_UNICODE);
$giorno_sel_js = json_encode($giorno_sel, JSON_UNESCAPED_UNICODE);

// Formatta data per display
$ref_display = (new DateTime($ref))->format('d/m/Y');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grafico Temperatura</title>
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
            margin: 4px auto 8px auto;
            gap: 6px;
            flex-wrap: wrap;
        }
        .top-bar-right {
            display: flex;
            align-items: center;
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
        .date-label { font-size: 10px; color: #888; white-space: nowrap; }
        input[type=date] {
            font-size: 10px;
            padding: 3px 5px;
            border: 1px solid #ccc;
            border-radius: 3px;
            background: #fff;
            color: #333;
        }
        .btn-reset {
            font-size: 14px;
            cursor: pointer;
            opacity: 0.5;
            border: none;
            background: none;
            color: #333;
            line-height: 1;
            padding: 2px 4px;
        }
        .btn-reset:hover { opacity: 1; color: #c00; }
        .chart-container {
            max-width: 95%;
            margin: 0 auto;
            position: relative;
        }
        canvas { display: block; width: 100% !important; }
        .legend {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            max-width: 95%;
            margin: 6px auto 0 auto;
            font-size: 10px;
            color: #666;
            align-items: center;
        }
        .leg-item { display: flex; align-items: center; gap: 4px; }
        .leg-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
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
        @media (min-width: 600px) {
            .btn-torna { font-size: 13px; }
            .legend { font-size: 11px; }
            input[type=date] { font-size: 12px; }
            .date-label { font-size: 12px; }
        }
    </style>
</head>
<body>

<div class="top-bar">
    <div class="top-bar-right">
        <span class="date-label">Giorno</span>
        <input type="date" id="data-sel"
               value="<?= htmlspecialchars($giorno_sel ?? $ref) ?>"
               max="<?= htmlspecialchars($response['oggi_reale']) ?>">
        <button class="btn-reset" id="btn-reset" title="Oggi">&#8635;</button>
    </div>
</div>

<div id="chart-tooltip" class="chart-tooltip"></div>

<div class="chart-container">
    <canvas id="mainChart" role="img" aria-label="Grafico temperatura e pioggia su 4 periodi"></canvas>
</div>

<div class="legend">
    <span class="leg-item"><span class="leg-line" style="background:#E24B4A;"></span>media max</span>
    <span class="leg-item"><span class="leg-line" style="background:#888;"></span>media</span>
    <span class="leg-item"><span class="leg-line" style="background:#378ADD;"></span>media min</span>
    <span class="leg-item"><span class="leg-dot" style="background:rgba(220,100,140,0.5);"></span>massime</span>
    <span class="leg-item"><span class="leg-dot" style="background:rgba(100,180,220,0.5);"></span>minime</span>
    <span class="leg-item"><span class="leg-dot" style="background:#E24B4A;border:1.5px solid #222;"></span>max ass.</span>
    <span class="leg-item"><span class="leg-dot" style="background:#378ADD;border:1.5px solid #222;"></span>min ass.</span>
    <span class="leg-item"><span style="display:inline-block;width:14px;height:8px;background:rgba(55,138,221,0.35);border-radius:1px;vertical-align:middle;"></span>pioggia</span>
</div>

<?php if ($giorno_sel): ?>
<div class="legend" style="margin-top:2px;">
    <span class="leg-item"><span class="leg-line" style="background:#E8954A;"></span>media max sel.</span>
    <span class="leg-item"><span class="leg-line" style="background:#C99A2E;"></span>media sel.</span>
    <span class="leg-item"><span class="leg-line" style="background:#3AAFA9;"></span>media min sel.</span>
    <span class="leg-item"><span class="leg-dot" style="background:#E8954A;border:1.5px solid #222;"></span>max ass. sel.</span>
    <span class="leg-item"><span class="leg-dot" style="background:#3AAFA9;border:1.5px solid #222;"></span>min ass. sel.</span>
    <span class="leg-item"><span style="display:inline-block;width:14px;height:8px;background:rgba(217,138,61,0.45);border-radius:1px;vertical-align:middle;"></span>pioggia sel.</span>
</div>
<?php endif; ?>

<div class="stat-footer" id="footer-note"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
var PERIODI    = <?= $periodi_js ?>;
var LABELS     = <?= $labels_js ?>;
var GIORNO_SEL = <?= $giorno_sel_js ?>;
var rScaleNuvola = 1.0;
var rScaleAss    = 1.0;
var palliniAssoluti = [];
var medieSegmenti   = [];
var zoneGeom = {}; // geometria (cx, y dei 5 valori chiave) indicizzata per id zona

var PALETTE = {
    default: { max: '#E24B4A', avg: '#888',    min: '#378ADD',
               pioggiaFill: 'rgba(55,138,221,0.45)', pioggiaStroke: 'rgba(55,138,221,0.8)',  pioggiaText: '#185FA5' },
    sel:     { max: '#E8954A', avg: '#C99A2E', min: '#3AAFA9',
               pioggiaFill: 'rgba(217,138,61,0.45)', pioggiaStroke: 'rgba(217,138,61,0.85)', pioggiaText: '#B5661F' }
};

var mainChart = null;

var graficoPlugin = {
    id: 'gp',
    afterDraw: function(chart) {
        palliniAssoluti = [];
        medieSegmenti = [];
        zoneGeom        = {};
        var ctx  = chart.ctx;
        var xS   = chart.scales.x;
        var yS   = chart.scales.y;
        var pS   = chart.scales.px;


        function xp(v) { return xS.getPixelForValue(v); }
        function yp(v) { return yS.getPixelForValue(v); }
        function pp(v) { return pS.getPixelForValue(v); }

        // Geometria zone: ogni zona ha
        //   xc    = X centro nuvola (temperatura)
        //   xp_r  = X centro barra pioggia (sulla stessa riga, a destra della nuvola)
        //   spread = raggio orizzontale nuvola (stretto = ovale)
        //   wBar  = larghezza barra pioggia
        var ZONE = [];
            if (GIORNO_SEL) {
                // Ogni zona si sdoppia in due colonne piu' strette: riferimento (sx) + selezione (dx)
                ZONE.push({ id: 'oggi',     xc: 0.85, xp_r: 1.45,  spread: 0.20, wBar: 0.40, pal: 'default' });
                ZONE.push({ id: 'oggi_sel', xc: 2.35, xp_r: 2.95,  spread: 0.20, wBar: 0.40, pal: 'sel' });

                ZONE.push({ id: 'gg10',     xc: 5.4,  xp_r: 6.35,  spread: 0.30, wBar: 0.55, pal: 'default' });
                ZONE.push({ id: 'gg10_sel', xc: 8.0,  xp_r: 8.95,  spread: 0.30, wBar: 0.55, pal: 'sel' });

                ZONE.push({ id: 'gg30',     xc: 11.4, xp_r: 12.65, spread: 0.45, wBar: 0.65, pal: 'default' });
                ZONE.push({ id: 'gg30_sel', xc: 14.6, xp_r: 15.85, spread: 0.45, wBar: 0.65, pal: 'sel' });

                ZONE.push({ id: 'anno',     xc: 18.9, xp_r: 20.7,  spread: 0.75, wBar: 0.85, pal: 'default' });
                ZONE.push({ id: 'anno_sel', xc: 23.5, xp_r: 25.3,  spread: 0.75, wBar: 0.85, pal: 'sel' });
            } else {
                // Comportamento originale: una sola colonna per zona
                ZONE.push({ id: 'oggi', xc: 1.6,  xp_r: 3.3,  spread: 0.35, wBar: 0.9,  pal: 'default' });
                ZONE.push({ id: 'gg10', xc: 6.0,  xp_r: 8.0,  spread: 0.65, wBar: 1.0,  pal: 'default' });
                ZONE.push({ id: 'gg30', xc: 12.0, xp_r: 14.5, spread: 1.0,  wBar: 1.1,  pal: 'default' });
                ZONE.push({ id: 'anno', xc: 20.0, xp_r: 24.0, spread: 1.6,  wBar: 1.3,  pal: 'default' });
            }

        function rand(s) {
            var x = Math.sin(s * 127.1 + 311.7) * 43758.5453;
            return x - Math.floor(x);
        }

        ZONE.forEach(function(z) {
            var dati = PERIODI[z.id];
            var pal  = PALETTE[z.pal] || PALETTE.default;
            if (!dati || dati.length === 0) return;

            var n = dati.length;
            var cx = xp(z.xc);
            // Spread in pixel: metà della larghezza orizzontale della nuvola
            var spreadPx = Math.abs(xp(z.xc + z.spread) - cx);

            // Filtra valori validi
            var mxArr = dati.filter(function(d){return d.mx!==null;}).map(function(d){return d.mx;});
            var mnArr = dati.filter(function(d){return d.mn!==null;}).map(function(d){return d.mn;});
            var avArr = dati.filter(function(d){return d.avg!==null;}).map(function(d){return d.avg;});

            if (mxArr.length === 0) return;

            var absMx = Math.max.apply(null, mxArr);
            var absMn = Math.min.apply(null, mnArr);
            var medMx = Math.round(mxArr.reduce(function(a,b){return a+b;},0)/mxArr.length*10)/10;
            var medMn = Math.round(mnArr.reduce(function(a,b){return a+b;},0)/mnArr.length*10)/10;
            var medAv = avArr.length > 0
                ? Math.round(avArr.reduce(function(a,b){return a+b;},0)/avArr.length*10)/10
                : Math.round((medMx+medMn)/2*10)/10;

            // Registra la geometria di questa colonna (serve per i connettori rif <-> sel)
            zoneGeom[z.id] = {
                cx: cx,
                yMx: yp(medMx), yAv: yp(medAv), yMn: yp(medMn),
                yAbsMx: yp(absMx), yAbsMn: yp(absMn)
            };

            // Stelo verticale centrale (da min ass a max ass)

            // Stelo verticale centrale (da min ass a max ass)
            ctx.strokeStyle = 'rgba(160,160,160,0.4)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(cx, yp(absMx));
            ctx.lineTo(cx, yp(absMn));
            ctx.stroke();

            // Nuvola punti — mx rosa, mn celeste, avg rosa chiaro
            if (n > 1) {
                dati.forEach(function(d, i) {
                    var jitter = (rand(i * 13.7 + z.xc * 0.3) * 2 - 1) * spreadPx;
                    var px = cx + jitter;
                    if (d.avg !== null) {
                        ctx.beginPath();
                        ctx.arc(px, yp(d.avg), 1.8 * rScaleNuvola, 0, Math.PI * 2);
                        ctx.fillStyle = 'rgba(220,100,140,0.20)';
                        ctx.fill();
                    }
                    if (d.mx !== null) {
                        ctx.beginPath();
                        ctx.arc(px, yp(d.mx), 2.0 * rScaleNuvola, 0, Math.PI * 2);
                        ctx.fillStyle = 'rgba(220,100,140,0.38)';
                        ctx.fill();
                    }
                    if (d.mn !== null) {
                        ctx.beginPath();
                        ctx.arc(px, yp(d.mn), 2.0 * rScaleNuvola, 0, Math.PI * 2);
                        ctx.fillStyle = 'rgba(100,180,220,0.38)';
                        ctx.fill();
                    }
                });
            }

            // Linee ORIZZONTALI (whisker) che intersecano lo stelo
            // larghezza = doppio dello spread della nuvola + margine
            var wh = Math.max(10, spreadPx * 2 + 6);
                ctx.strokeStyle = pal.max; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(cx - wh, yp(medMx)); ctx.lineTo(cx + wh, yp(medMx)); ctx.stroke();
                ctx.strokeStyle = pal.avg; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.moveTo(cx - wh, yp(medAv)); ctx.lineTo(cx + wh, yp(medAv)); ctx.stroke();
                ctx.strokeStyle = pal.min; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(cx - wh, yp(medMn)); ctx.lineTo(cx + wh, yp(medMn)); ctx.stroke();

                // Registra le linee media per il tooltip (hover / touch)
                medieSegmenti.push(
                    { xMin: cx - wh, xMax: cx + wh, py: yp(medMx), valore: medMx, tipo: 'max',   zona: LABELS[z.id] },
                    { xMin: cx - wh, xMax: cx + wh, py: yp(medAv), valore: medAv, tipo: 'media', zona: LABELS[z.id] },
                    { xMin: cx - wh, xMax: cx + wh, py: yp(medMn), valore: medMn, tipo: 'min',   zona: LABELS[z.id] }
                );

            // Pallini max e min assoluti — rScale * 0.7 su mobile, rScale su desktop
            var rAss = 5 * rScaleAss;
            var iMx = mxArr.indexOf(absMx);
            var iMn = mnArr.indexOf(absMn);
            // Recupera le date: cerca nel dataset il giorno del max/min
            var dataMx = dati.filter(function(d){return d.mx===absMx;})[0];
            var dataMn = dati.filter(function(d){return d.mn===absMn;})[0];

            ctx.beginPath(); ctx.arc(cx, yp(absMx), rAss, 0, Math.PI * 2);
            ctx.fillStyle = pal.max; ctx.fill();
            ctx.strokeStyle = '#222'; ctx.lineWidth = 1.5; ctx.stroke();
            palliniAssoluti.push({
                px: cx, py: yp(absMx), r: rAss,
                valore: absMx,
                data: dataMx ? dataMx.d : '',
                tipo: 'max'
            });

            ctx.beginPath(); ctx.arc(cx, yp(absMn), rAss, 0, Math.PI * 2);
            ctx.fillStyle = pal.min; ctx.fill();
            ctx.strokeStyle = '#222'; ctx.lineWidth = 1.5; ctx.stroke();
            palliniAssoluti.push({
                px: cx, py: yp(absMn), r: rAss,
                valore: absMn,
                data: dataMn ? dataMn.d : '',
                tipo: 'min'
            });

            // ===== BARRA PIOGGIA VERTICALE (colonna separata a destra) =====
            var totPioggia = Math.round(dati.reduce(function(a,d){return a+d.pioggia;},0)*10)/10;
            var cxP = xp(z.xp_r);
            var wBarPx = Math.abs(xp(z.xp_r + z.wBar * 0.5) - cxP);

            // Baseline barra pioggia = bottom del plot area (y=0 pioggia)
            var yBase = pp(0);
            var yTop  = pp(Math.min(totPioggia, 1200));

            if (totPioggia > 0) {
            ctx.fillStyle = pal.pioggiaFill;
            ctx.fillRect(cxP - wBarPx, yTop, wBarPx * 2, yBase - yTop);
            ctx.strokeStyle = pal.pioggiaStroke;
            ctx.lineWidth = 0.5;
            ctx.strokeRect(cxP - wBarPx, yTop, wBarPx * 2, yBase - yTop);
            ctx.fillStyle = pal.pioggiaText;
            ctx.font = 'bold 9px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText(totPioggia + ' mm', cxP, yTop - 2);
}

            // Etichetta zona — centrata su cx, allineata ai pallini max/min assoluti
            ctx.fillStyle = '#aaa';
            ctx.font = 'bold 9px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText(LABELS[z.id], cx, yp(chart.scales.y.min) - 2);
        });

// Linee tratteggiate: collegano lo stesso valore tra colonna di riferimento e selezione
        if (GIORNO_SEL) {
            var basiZona = ['oggi', 'gg10', 'gg30', 'anno'];
            var metriche = ['yMx', 'yAv', 'yMn', 'yAbsMx', 'yAbsMn'];
            ctx.strokeStyle = 'rgba(120,120,120,0.55)';
            ctx.lineWidth = 2.5;
            ctx.setLineDash([4, 3]);
            basiZona.forEach(function(base) {
                var gRif = zoneGeom[base];
                var gSel = zoneGeom[base + '_sel'];
                if (!gRif || !gSel) return;
                metriche.forEach(function(m) {
                    ctx.beginPath();
                    ctx.moveTo(gRif.cx, gRif[m]);
                    ctx.lineTo(gSel.cx, gSel[m]);
                    ctx.stroke();
                });
            });
            ctx.setLineDash([]);
        }

        // Separatori verticali tra zone
        ctx.strokeStyle = 'rgba(128,128,128,0.12)';
        ctx.lineWidth = 1;
        ctx.setLineDash([3, 3]);
        [4.5, 10.2, 17.2].forEach(function(x) {
            ctx.beginPath();
            ctx.moveTo(xp(x), yp(chart.scales.y.max));
            ctx.lineTo(xp(x), yp(chart.scales.y.min));
            ctx.stroke();
        });
        ctx.setLineDash([]);
    }
};

function build() {
    if (mainChart) { mainChart.destroy(); mainChart = null; }

    var W = document.querySelector('.chart-container').offsetWidth || 400;
    var H = Math.max(280, Math.min(Math.round(W * 0.55), 420));
    document.getElementById('mainChart').style.height = H + 'px';
    document.querySelector('.chart-container').style.height = H + 'px';

    // Scala punti separata per i due tipi:
    // nuvola: ok su mobile (1.0), doppio su PC
    // assoluti: ok su PC (1.0), -30% su mobile
    rScaleNuvola = W < 480 ? 1.0 : 2.0;
    rScaleAss    = W < 480 ? 0.7 : 1.0;

    var ctx = document.getElementById('mainChart').getContext('2d');
    mainChart = new Chart(ctx, {
        type: 'scatter',
        data: { datasets: [{ data: [], pointRadius: 0 }] },
        options: {
            animation: false,
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            layout: { padding: { left: 8, right: 8, top: 18, bottom: 18 } },
            scales: {
                x: { min: 0, max: 27, display: false },
                y: {
                    min: -10, max: 45,
                    position: 'left',
                    grid: { color: 'rgba(128,128,128,0.1)' },
                    ticks: {
                        font: { size: 9 },
                        color: '#888',
                        stepSize: 5,
                        callback: function(v) { return v + '\u00b0'; }
                    }
                },
                px: {
                    min: 0, max: 1200,
                    position: 'right',
                    grid: { display: false },
                    ticks: {
                        font: { size: 9 },
                        color: '#85B7EB',
                        stepSize: 200,
                        callback: function(v) { return v + ' mm'; }
                    }
                }
            }
        },
        plugins: [graficoPlugin]
    });

    // Footer
    var ref = '<?= $ref ?>';
    document.getElementById('footer-note').textContent =
        'rif. ' + ref + ' \u2022 temp max / min / media giornaliera + pioggia cumulata';

    sendResize();
}

function sendResize() {
    var h = document.body.scrollHeight;
    window.parent.postMessage({
        action:   'resize',
        iframeId: 'stat-iframe-tab1',
        height:   h
    }, '*');
}

// Selettore giorno — ricarica con ?data=YYYY-MM-DD
document.getElementById('data-sel').addEventListener('change', function() {
    var val = this.value;
    if (val) {
        window.location.href = window.location.pathname + '?data=' + val;
    }
});

// Reset — torna a ieri (default senza parametri)
document.getElementById('btn-reset').addEventListener('click', function() {
    window.location.href = window.location.pathname;
});

// ---- Tooltip pallini assoluti ----
var tooltipEl = document.getElementById('chart-tooltip');

function fmtData(s) {
    if (!s) return '';
    var p = s.split('-');
    var mm = ['gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
    return p[2] + ' ' + mm[+p[1]-1] + ' ' + p[0];
}

function aggiornaTooltip(clientX, clientY) {
    var canvas = document.getElementById('mainChart');
    var rect = canvas.getBoundingClientRect();
    var mx = clientX - rect.left;
    var my = clientY - rect.top;

    var trovato = null;
    var soglia = 18; // px distanza massima per i pallini assoluti
    for (var i = 0; i < palliniAssoluti.length; i++) {
        var p = palliniAssoluti[i];
        var dist = Math.sqrt((mx - p.px) * (mx - p.px) + (my - p.py) * (my - p.py));
        if (dist <= Math.max(soglia, p.r + 6)) {
            trovato = p;
            break;
        }
    }

    if (trovato) {
        var label = trovato.tipo === 'max' ? 'Max' : 'Min';
        tooltipEl.innerHTML = label + ': ' + trovato.valore + '\u00b0C<br>' + fmtData(trovato.data);
        tooltipEl.style.display = 'block';
        tooltipEl.style.left = (clientX - tooltipEl.offsetWidth / 2) + 'px';
        tooltipEl.style.top  = (clientY - tooltipEl.offsetHeight - 10) + 'px';
        return;
    }

    // Linee delle medie (media max / media / media min)
    var sogliaLinea = 8; // px distanza verticale massima dalla linea
    var trovataMedia = null;
    for (var j = 0; j < medieSegmenti.length; j++) {
        var s = medieSegmenti[j];
        if (mx >= s.xMin && mx <= s.xMax && Math.abs(my - s.py) <= sogliaLinea) {
            trovataMedia = s;
            break;
        }
    }

    if (trovataMedia) {
        var labelMedia = trovataMedia.tipo === 'max' ? 'Media max'
                        : trovataMedia.tipo === 'min' ? 'Media min'
                        : 'Media';
        tooltipEl.innerHTML = labelMedia + ': ' + trovataMedia.valore + '\u00b0C<br>' + trovataMedia.zona;
        tooltipEl.style.display = 'block';
        tooltipEl.style.left = (clientX - tooltipEl.offsetWidth / 2) + 'px';
        tooltipEl.style.top  = (clientY - tooltipEl.offsetHeight - 10) + 'px';
    } else {
        tooltipEl.style.display = 'none';
    }
}

function nascondiTooltip() {
    tooltipEl.style.display = 'none';
}

// Listener aggiunti dopo build() quando il canvas esiste
function aggiungiListenerTooltip() {
    var canvas = document.getElementById('mainChart');
    canvas.addEventListener('mousemove', function(e) {
        aggiornaTooltip(e.clientX, e.clientY);
    });
    canvas.addEventListener('mouseleave', nascondiTooltip);
    canvas.addEventListener('touchmove', function(e) {
        if (e.touches.length > 0) {
            e.preventDefault();
            aggiornaTooltip(e.touches[0].clientX, e.touches[0].clientY);
        }
    }, { passive: false });
    canvas.addEventListener('touchend', nascondiTooltip);
}

build();
aggiungiListenerTooltip();
setTimeout(sendResize, 150);
setTimeout(sendResize, 500);
</script>

</body>
</html>