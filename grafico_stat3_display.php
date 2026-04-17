<?php
/**
 * ============================================================================
 * GRAFICO RECORD PLUVIO - stat3_grafico.php
 * ============================================================================
 * Barre verticali dei record pluviometrici mensili per 4 durate:
 *   1h / 6h / 12h / 24h
 *
 * Ogni blocco ha 12 barre (gen-dic).
 * Asse Y destra: 0-200 mm.
 * Anno selezionabile con frecce.
 * Tooltip su hover/touch: mm + data del record.
 *
 * PARAMETRI GET:
 *   ?anno=YYYY  anno da visualizzare (default: anno corrente)
 *
 * Comunicazione con stat_display.php via postMessage:
 *   - resize       : aggiorna altezza iframe
 *   - tornaTabella : torna a tabella_stat3_display.php
 */

ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../envelop_lettura.php';
require_once __DIR__ . '/api/api_tabella_stat_data.php';

$response = getGrafico3Data();

if (!$response['success']) {
    echo "<div style='font-family:Arial;font-size:13px;color:#c00;padding:10px;'>
            Errore: " . htmlspecialchars($response['error'] ?? 'Dati non disponibili') . "
          </div>";
    exit;
}

$anno_sel  = $response['anno_sel'];
$anni_disp = $response['anni_disp'];
$mesi_js   = json_encode($response['mesi'],      JSON_UNESCAPED_UNICODE);
$anni_js   = json_encode($response['anni_disp'], JSON_UNESCAPED_UNICODE);

$anno_prec = null;
$anno_succ = null;
foreach ($anni_disp as $a) {
    if ($a < $anno_sel) { $anno_prec = $a; break; }
}
foreach (array_reverse($anni_disp) as $a) {
    if ($a > $anno_sel) { $anno_succ = $a; break; }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Pioggia</title>
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
        }
        .anno-nav {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .anno-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            font-size: 14px;
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
        .anno-btn:hover { border-color: #3366cc; color: #3366cc; background: #eef3ff; }
        .anno-btn.disabled { opacity: 0.3; pointer-events: none; }
        .anno-label {
            font-size: 13px;
            font-weight: bold;
            color: #333;
            min-width: 40px;
            text-align: center;
        }
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
            .anno-label { font-size: 15px; }
            .legend { font-size: 11px; }
        }
    </style>
</head>
<body>

<div class="top-bar">
    <div class="anno-nav">
        <a class="anno-btn<?= $anno_prec ? '' : ' disabled' ?>"
           href="?anno=<?= $anno_prec ?? $anno_sel ?>">&#8249;</a>
        <span class="anno-label"><?= $anno_sel ?></span>
        <a class="anno-btn<?= $anno_succ ? '' : ' disabled' ?>"
           href="?anno=<?= $anno_succ ?? $anno_sel ?>">&#8250;</a>
    </div>
    <span style="font-size:10px;color:#aaa;">record pioggia mensili</span>
</div>

<div id="chart-tooltip" class="chart-tooltip"></div>

<div class="chart-container">
    <canvas id="mainChart" role="img" aria-label="Record pioggia mensili <?= $anno_sel ?>"></canvas>
</div>

<div class="legend">
    <span class="leg-item"><span class="leg-sq" style="background:#5B9BD5;"></span>1h</span>
    <span class="leg-item"><span class="leg-sq" style="background:#70AD47;"></span>6h</span>
    <span class="leg-item"><span class="leg-sq" style="background:#ED7D31;"></span>12h</span>
    <span class="leg-item"><span class="leg-sq" style="background:#A32D2D;"></span>24h</span>
</div>

<div class="stat-footer" id="footer-note"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
var MESI    = <?= $mesi_js ?>;
var ANNO    = <?= $anno_sel ?>;
var MESI_NOMI = ['gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];

var DURATE = [
    { id: 'r1h',  label: '1h',  colore: '#5B9BD5', dataKey: 'd1h'  },
    { id: 'r6h',  label: '6h',  colore: '#70AD47', dataKey: 'd6h'  },
    { id: 'r12h', label: '12h', colore: '#ED7D31', dataKey: 'd12h' },
    { id: 'r24h', label: '24h', colore: '#A32D2D', dataKey: 'd24h' },
];

// Array barre per tooltip: { x1, x2, y1, y2, mm, data, durata }
var barreTooltip = [];

var mainChart = null;

// Plugin custom: disegna 4 gruppi di 12 barre + separatori + etichette
var graficoPlugin = {
    id: 'gp3',
    afterDraw: function(chart) {
        barreTooltip = [];
        var ctx = chart.ctx;
        var xS  = chart.scales.x;
        var yS  = chart.scales.y;

        function xp(v) { return xS.getPixelForValue(v); }
        function yp(v) { return yS.getPixelForValue(v); }

        var yBase = yp(0);

        // Geometria: 4 blocchi (durate), ognuno con 12 barre (mesi)
        // Asse X virtuale: 0-52, ogni blocco occupa ~12 unità + 1 di gap
        var BLOCCHI = [
            { durata: DURATE[0], xStart: 0  },
            { durata: DURATE[1], xStart: 13 },
            { durata: DURATE[2], xStart: 26 },
            { durata: DURATE[3], xStart: 39 },
        ];
        var barW_u = 0.8; // larghezza barra in unità X

        BLOCCHI.forEach(function(b) {
            var dur = b.durata;

            // Etichetta durata centrata sul blocco
            var xCentro = xp(b.xStart + 6);
            ctx.fillStyle = '#aaa';
            ctx.font = 'bold 10px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';
            ctx.fillText(dur.label, xCentro, yp(yS.max) + 3);

            // 12 barre mensili
            MESI.forEach(function(m, i) {
                var val = m[dur.id];
                if (val === null) return;

                var xU  = b.xStart + i + 0.1;
                var x1  = xp(xU);
                var x2  = xp(xU + barW_u);
                var yTop = yp(Math.min(val, 200));

                ctx.fillStyle = dur.colore;
                ctx.fillRect(x1, yTop, x2 - x1, yBase - yTop);

                // Salva per tooltip
                barreTooltip.push({
                    x1: x1, x2: x2, y1: yTop, y2: yBase,
                    mm: val,
                    data: m[dur.dataKey] || null,
                    durata: dur.label,
                    mese: MESI_NOMI[i]
                });
            });

            // Etichette mesi sotto le barre
            MESI.forEach(function(m, i) {
                var xU = b.xStart + i + 0.1 + barW_u / 2;
                ctx.fillStyle = '#aaa';
                ctx.font = '8px Arial';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'top';
                ctx.fillText(MESI_NOMI[i], xp(xU), yBase + 3);
            });
        });

        // Separatori verticali tra blocchi
        ctx.strokeStyle = 'rgba(128,128,128,0.15)';
        ctx.lineWidth = 1;
        ctx.setLineDash([3, 3]);
        [12.5, 25.5, 38.5].forEach(function(x) {
            ctx.beginPath();
            ctx.moveTo(xp(x), yp(yS.max));
            ctx.lineTo(xp(x), yBase);
            ctx.stroke();
        });
        ctx.setLineDash([]);
    }
};

function build() {
    if (mainChart) { mainChart.destroy(); mainChart = null; }

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
            layout: { padding: { left: 4, right: 8, top: 20, bottom: 22 } },
            scales: {
                x: { min: 0, max: 51, display: false },
                y: {
                    min: 0, max: 150,
                    position: 'right',
                    grid: { color: 'rgba(128,128,128,0.1)' },
                    ticks: {
                        font: { size: 9 },
                        color: '#5B9BD5',
                        stepSize: 50,
                        callback: function(v) { return v + ' mm'; }
                    }
                }
            }
        },
        plugins: [graficoPlugin]
    });

    document.getElementById('footer-note').textContent =
        'Record pluviometrici mensili ' + ANNO + ' \u2022 fonte: pluvio_record_mensili';

    sendResize();
}

// Tooltip
var tooltipEl = document.getElementById('chart-tooltip');

function fmtData(s) {
    if (!s) return '';
    var p = s.split(/[-T ]/);
    if (p.length < 3) return s;
    var mm = ['gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
    return p[2] + ' ' + mm[+p[1]-1] + ' ' + p[0];
}

function aggiornaTooltip(clientX, clientY) {
    var canvas = document.getElementById('mainChart');
    var rect = canvas.getBoundingClientRect();
    var mx = clientX - rect.left;
    var my = clientY - rect.top;

    var trovato = null;
    for (var i = 0; i < barreTooltip.length; i++) {
        var b = barreTooltip[i];
        if (mx >= b.x1 && mx <= b.x2 && my >= b.y1 && my <= b.y2) {
            trovato = b;
            break;
        }
    }

    if (trovato) {
        var data_str = trovato.data ? '<br>' + fmtData(trovato.data) : '';
        tooltipEl.innerHTML = trovato.durata + ' \u2014 ' + trovato.mese
            + ': ' + trovato.mm + ' mm' + data_str;
        tooltipEl.style.display = 'block';
        tooltipEl.style.left = (clientX - tooltipEl.offsetWidth / 2) + 'px';
        tooltipEl.style.top  = (clientY - tooltipEl.offsetHeight - 10) + 'px';
    } else {
        tooltipEl.style.display = 'none';
    }
}

function nascondiTooltip() { tooltipEl.style.display = 'none'; }

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

function sendResize() {
    var h = document.body.scrollHeight;
    window.parent.postMessage({
        action:   'resize',
        iframeId: 'stat-iframe-tab3',
        height:   h
    }, '*');
}

build();
aggiungiListenerTooltip();
setTimeout(sendResize, 150);
setTimeout(sendResize, 500);
</script>

</body>
</html>