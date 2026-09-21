<?php
/**
 * ============================================================================
 * GRAFICO STATISTICHE PLUVIO - grafico_pluvio_display.php
 * ============================================================================
 * TRE GRAFICI SEPARATI: MESE corrente | STAGIONE corrente | ANNO
 * Ognuno ha le proprie scale (mm a sinistra, gg a destra) calcolate solo sui
 * propri valori, cosi' il mese non viene schiacciato dalla scala dell'anno.
 * Affiancati su schermi ampi, impilati su mobile. La composizione e' nella
 * costante JS GRAFICI.
 *
 * Per ogni periodo, due barre (climatologia storica):
 *   - barra blu   = mm, MEDIANA (p50)        (asse sinistro, mm)
 *   - barra verde = giorni pioggia, MEDIANA  (asse destro, gg)
 * Su ogni barra:
 *   - baffo chiaro  p10-p90
 *   - baffo scuro   p25-p75
 *   - trattino grigio tratteggiato = media aritmetica (secondaria)
 * I baffi sono percentili, quindi il valore centrale coerente e' il p50.
 * Valori dell'anno in corso, ciascuno sopra la barra di pertinenza:
 *   - segmento ROSSO      = mm cumulati attuali    (sopra la barra mm)
 *   - segmento ARANCIONE  = giorni pioggia attuali (sopra la barra gg)
 *
 * PARAMETRI GET:
 *   ?modo=parziale  (default) medie calcolate fino al giorno corrente
 *   ?modo=completo  medie sull'intero mese / stagione / anno
 *
 * Comunicazione con la pagina contenitore via postMessage (action: resize).
 */

ini_set('display_errors', 0);
error_reporting(0);

// envelop.php fornisce $pdo (lettura+scrittura): serve alla cache giornaliera.
// Con envelop_lettura.php funzionerebbe comunque, ma la cache verrebbe saltata.
require_once __DIR__ . '/../envelop.php';
require_once __DIR__ . '/api/api_pluvio_stat_data.php';

$modo = (($_GET['modo'] ?? '') === 'completo') ? 'completo' : 'parziale';
$response = getPluvioStatData($modo);

if (!$response['success']) {
    echo "<div style='font-family:Arial;font-size:13px;color:#c00;padding:10px;'>
            Errore: " . htmlspecialchars($response['error'] ?? 'Dati non disponibili') . "
          </div>";
    exit;
}

$periodi_js  = json_encode($response['periodi'],  JSON_UNESCAPED_UNICODE);
$correnti_js = json_encode($response['correnti'], JSON_UNESCAPED_UNICODE);
$meta        = $response['meta'];
$cop_js      = json_encode($response['copertura_correnti'] ?? [], JSON_UNESCAPED_UNICODE);
$clima_js    = json_encode($response['clima'] ?? [], JSON_UNESCAPED_UNICODE);
$oggi_disp   = (new DateTime($meta['oggi']))->format('d/m/Y');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiche Pluviometriche</title>
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
        .titolo { font-size: 12px; font-weight: bold; color: #555; }
        .toggle-modo {
            display: inline-flex;
            border: 2px solid black;
            border-radius: 3px;
            overflow: hidden;
            user-select: none;
        }
        .toggle-modo span {
            font-size: 10px;
            font-weight: bold;
            padding: 4px 10px;
            cursor: pointer;
            color: black;
            background: transparent;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .toggle-modo span.attivo {
            background: black;
            color: #fff;
            cursor: default;
        }
        .toggle-modo span:not(.attivo):hover { color: #3366cc; }
        .grafici {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            max-width: 95%;
            margin: 0 auto;
        }
        /* flex-basis 260px: tre riquadri affiancati sopra ~850px di larghezza,
           impilati sotto. min-width 0 evita che il canvas forzi l'overflow. */
        .grafico-box {
            flex: 1 1 260px;
            min-width: 0;
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
        .leg-box  { width: 12px; height: 9px; border-radius: 1px; flex-shrink: 0; }
        .leg-line { width: 18px; height: 3px; flex-shrink: 0; }
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
            .toggle-modo span { font-size: 13px; }
            .legend { font-size: 11px; }
            .titolo { font-size: 14px; }
        }
    </style>
</head>
<body>

<div class="top-bar">
    <span class="titolo">Pioggia: <?= $modo === 'parziale' ? 'fino al ' . $oggi_disp : 'periodi interi' ?></span>
    <div class="toggle-modo">
        <span id="btn-parziale" class="<?= $modo === 'parziale' ? 'attivo' : '' ?>">Fino ad oggi</span>
        <span id="btn-completo" class="<?= $modo === 'completo' ? 'attivo' : '' ?>">Periodo intero</span>
    </div>
</div>

<div id="chart-tooltip" class="chart-tooltip"></div>

<!-- I riquadri dei grafici vengono creati da creaRiquadri() secondo GRAFICI -->
<div class="grafici" id="grafici"></div>

<div class="legend">
    <span class="leg-item"><span class="leg-box" style="background:rgba(55,138,221,0.55);border:1px solid #2b6db3;"></span>mm mediana</span>
    <span class="leg-item"><span class="leg-box" style="background:rgba(110,180,110,0.55);border:1px solid #4d8f4d;"></span>gg pioggia mediana</span>
    <span class="leg-item"><span class="leg-line" style="background:#555;height:2px;"></span>p25&ndash;p75</span>
    <span class="leg-item"><span class="leg-line" style="background:#bbb;height:2px;"></span>p10&ndash;p90</span>
    <span class="leg-item"><span style="display:inline-block;width:18px;height:0;border-top:2px dashed #777;flex-shrink:0;"></span>media</span>
    <span class="leg-item"><span class="leg-line" style="background:#D62828;"></span>mm attuali</span>
    <span class="leg-item"><span class="leg-line" style="background:#F77F00;"></span>gg attuali</span>
</div>

<div class="stat-footer" id="footer-note"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
var PERIODI  = <?= $periodi_js ?>;   // [{id,label,mm:{mediana,p10..},gg:{...},n_anni,clima}]
var CORRENTI = <?= $correnti_js ?>;  // {mese:{mm,gg}, stagione:{...}, anno:{...}}
var MODO     = '<?= $modo ?>';
var SOGLIA_GG = <?= json_encode($meta['soglia_gg']) ?>;
var COPERTURA = <?= $cop_js ?>;          // copertura dei valori correnti (0..1)
var COP_MIN   = <?= json_encode($meta['copertura_min'] ?? 0.9) ?>;

// ---------------------------------------------------------------------------
// CONFIGURAZIONE GRAFICI
// Ogni voce e' un grafico autonomo, con le proprie scale mm e gg calcolate
// SOLO sui periodi che contiene. 'zone' accetta uno o piu' id di periodo
// ('mese', 'stagione', 'anno'): con piu' id le zone si affiancano nello
// stesso grafico e condividono le scale.
// Per nascondere un periodo basta togliere la sua voce.
// ---------------------------------------------------------------------------
var GRAFICI = [
    { id: 'mese',     zone: ['mese'] },
    { id: 'stagione', zone: ['stagione'] },
    { id: 'anno',     zone: ['anno'] }
];

var LARGH_ZONA   = 9;    // unita' dell'asse x virtuale per zona
var BARRA_MAX_PX = 70;   // larghezza massima di una barra, in pixel

var COL = {
    mmFill:  'rgba(55,138,221,0.55)',  mmStroke: '#2B6DB3',
    ggFill:  'rgba(110,180,110,0.55)', ggStroke: '#4D8F4D',
    baffoLargo:  '#bbb',
    baffoStretto:'#555',
    media:       '#777',
    corrMm:  '#D62828',
    corrGg:  '#F77F00'
};

var charts  = {};   // id grafico -> istanza Chart
var hitAree = {};   // id canvas  -> aree sensibili per il tooltip
var CONF_CANVAS = {};   // id canvas -> voce di GRAFICI

function periodo(id) {
    return PERIODI.find(function(q) { return q.id === id; }) || null;
}

// Periodi effettivamente mostrati (per footer ed etichette di riferimento)
function periodiMostrati() {
    var out = [];
    GRAFICI.forEach(function(g) {
        g.zone.forEach(function(id) {
            var p = periodo(id);
            if (p && out.indexOf(p) === -1) out.push(p);
        });
    });
    return out;
}

// Arrotonda il massimo dell'asse al primo multiplo "tondo" sopra il dato.
// Si sceglie prima un passo di tacca leggibile (1/2/2.5/5 x 10^k) puntando a
// ~5 tacche, poi si arrotonda il massimo a un multiplo di quel passo: cosi'
// 89 -> 100 e non 200, e le barre occupano davvero l'altezza disponibile.
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

// Massimi assi del SINGOLO grafico: il valore piu' alto che compare nelle sue
// zone (percentili, mediana, media e valori correnti). E' questo che rende
// le scale autonome: il mese non viene piu' schiacciato dall'anno.
function calcolaMassimi(zoneIds) {
    var maxMm = 0, maxGg = 0;
    var chiavi = ['media', 'mediana', 'p10', 'p25', 'p50', 'p75', 'p90'];
    zoneIds.forEach(function(id) {
        var p = periodo(id);
        if (!p) return;
        chiavi.forEach(function(k) {
            if (p.mm && p.mm[k] != null) maxMm = Math.max(maxMm, p.mm[k]);
            if (p.gg && p.gg[k] != null) maxGg = Math.max(maxGg, p.gg[k]);
        });
        var c = CORRENTI[id];
        if (c) {
            if (c.mm != null) maxMm = Math.max(maxMm, c.mm);
            if (c.gg != null) maxGg = Math.max(maxGg, c.gg);
        }
    });
    return { mm: niceMax(maxMm * 1.02), gg: niceMax(maxGg * 1.02) };
}

// ---------------------------------------------------------------------------
// Plugin di disegno (condiviso: legge le zone dalla configurazione del canvas)
// ---------------------------------------------------------------------------
var pluvioPlugin = {
    id: 'pluvioPlugin',
    afterDatasetsDraw: function(chart) {
        var canvasId = chart.canvas.id;
        var conf = CONF_CANVAS[canvasId];
        if (!conf) return;

        var ctx = chart.ctx;
        var xp  = function(v) { return chart.scales.x.getPixelForValue(v); };
        var yMm = function(v) { return chart.scales.y.getPixelForValue(v); };
        var yGg = function(v) { return chart.scales.yg.getPixelForValue(v); };
        var y0  = yMm(0);
        var aree = hitAree[canvasId] = [];

        conf.zone.forEach(function(id, i) {
            var p = periodo(id);
            var c = CORRENTI[id];
            if (!p) return;

            var x0 = i * LARGH_ZONA + 0.5;
            var x1 = (i + 1) * LARGH_ZONA - 0.5;
            var largh = x1 - x0;
            // barre: mm a sinistra, gg a destra della zona
            var barW = largh * 0.28;
            var cxMm = x0 + largh * 0.30;
            var cxGg = x0 + largh * 0.70;

            var geoMm = disegnaBarra(ctx, xp, yMm, y0, cxMm, barW, p.mm, COL.mmFill, COL.mmStroke, 'mm', p.label, aree);
            var geoGg = disegnaBarra(ctx, xp, yGg, y0, cxGg, barW, p.gg, COL.ggFill, COL.ggStroke, 'gg', p.label, aree);

            // ---- valori correnti: ogni riga sta sopra la SUA barra ----
            // Il segmento e' centrato sulla barra e lungo al massimo quanto
            // meta' zona: in un grafico a zona singola la meta' zona e' molto
            // larga e una riga da bordo a centro si staccherebbe dalla barra.
            if (c) {
                var mezzaZonaPx = Math.abs(xp(x0 + largh / 2) - xp(x0));
                var margPx      = Math.abs(xp(x0 + largh * 0.03) - xp(x0));
                var semiMax     = mezzaZonaPx / 2 - margPx;

                if (c.mm != null) {
                    var cxPm = xp(cxMm);
                    var sm   = Math.min(semiMax, (geoMm ? geoMm.semi : 20) * 2.2);
                    tracciaCorrente(ctx, cxPm - sm, cxPm + sm, yMm(c.mm), COL.corrMm, false);
                    aree.push({tipo:'linea', x0:cxPm - sm, x1:cxPm + sm, py:yMm(c.mm),
                               testo:'Attuale: ' + c.mm + ' mm<br>' + p.label});
                }
                if (c.gg != null) {
                    var cxPg = xp(cxGg);
                    var sg   = Math.min(semiMax, (geoGg ? geoGg.semi : 20) * 2.2);
                    tracciaCorrente(ctx, cxPg - sg, cxPg + sg, yGg(c.gg), COL.corrGg, true);
                    aree.push({tipo:'linea', x0:cxPg - sg, x1:cxPg + sg, py:yGg(c.gg),
                               testo:'Attuale: ' + c.gg + ' gg pioggia<br>' + p.label});
                }
            }

            // ---- etichetta zona + periodo di climatologia ----
            var cxZona = xp(x0 + largh / 2);
            ctx.fillStyle = '#aaa';
            ctx.font = 'bold 9px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText(p.label, cxZona, y0 + 13);
            if (p.clima && p.clima.label && p.clima.label !== 'n/d') {
                ctx.fillStyle = '#c4c4c4';
                ctx.font = '8px Arial';
                ctx.fillText(p.clima.label, cxZona, y0 + 23);
            }

            // separatore verticale prima di ogni zona successiva alla prima
            if (i > 0) {
                ctx.strokeStyle = 'rgba(128,128,128,0.12)';
                ctx.lineWidth = 1;
                ctx.setLineDash([3, 3]);
                ctx.beginPath();
                ctx.moveTo(xp(i * LARGH_ZONA), yMm(chart.scales.y.max));
                ctx.lineTo(xp(i * LARGH_ZONA), y0);
                ctx.stroke();
                ctx.setLineDash([]);
            }
        });
    }
};

function tracciaCorrente(ctx, px0, px1, py, colore, tratteggio) {
    ctx.strokeStyle = colore;
    ctx.lineWidth = 2.5;
    if (tratteggio) ctx.setLineDash([6, 3]);
    ctx.beginPath();
    ctx.moveTo(px0, py);
    ctx.lineTo(px1, py);
    ctx.stroke();
    ctx.setLineDash([]);
}

/** Disegna una barra con baffi; restituisce { semi } = mezza larghezza in px. */
function disegnaBarra(ctx, xp, yv, y0, cx, barW, stat, fill, stroke, metrica, label, aree) {
    // Valore centrale = MEDIANA (p50): i baffi sono percentili, quindi il
    // riferimento coerente e' il p50. La media resta come trattino secondario.
    var centro = (stat.mediana != null) ? stat.mediana : stat.p50;
    if (centro == null) return null;

    var cxPx = xp(cx);
    // Larghezza limitata in pixel: con una sola zona per grafico le barre
    // altrimenti diventerebbero larghissime sugli schermi ampi.
    var semi = Math.min(Math.abs(xp(cx + barW / 2) - cxPx), BARRA_MAX_PX / 2);
    var px0  = cxPx - semi;
    var px1  = cxPx + semi;
    var pyMed = yv(centro);

    // barra della mediana
    ctx.fillStyle = fill;
    ctx.strokeStyle = stroke;
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.rect(px0, pyMed, px1 - px0, y0 - pyMed);
    ctx.fill();
    ctx.stroke();

    // baffo largo p10-p90 (chiaro)
    if (stat.p10 != null && stat.p90 != null) {
        disegnaBaffo(ctx, cxPx, yv(stat.p10), yv(stat.p90), (px1 - px0) * 0.55, COL.baffoLargo, 1.5);
    }
    // baffo stretto p25-p75 (scuro)
    if (stat.p25 != null && stat.p75 != null) {
        disegnaBaffo(ctx, cxPx, yv(stat.p25), yv(stat.p75), (px1 - px0) * 0.30, COL.baffoStretto, 2);
    }

    // media aritmetica: trattino tratteggiato sulla larghezza della barra
    if (stat.media != null) {
        ctx.strokeStyle = COL.media;
        ctx.lineWidth = 1.5;
        ctx.setLineDash([3, 2]);
        ctx.beginPath();
        ctx.moveTo(px0, yv(stat.media));
        ctx.lineTo(px1, yv(stat.media));
        ctx.stroke();
        ctx.setLineDash([]);
    }

    // etichetta mediana sopra il p90 (o sopra la barra)
    var unita = metrica === 'mm' ? ' mm' : ' gg';
    var topVal = stat.p90 != null ? Math.max(stat.p90, centro) : centro;
    var topPx  = yv(topVal);
    ctx.fillStyle = stroke;
    ctx.font = 'bold 9px Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'bottom';
    ctx.fillText(centro + unita, cxPx, topPx - 3);

    // area sensibile tooltip
    var righe = [];
    if (stat.p90 != null) righe.push('p90: ' + stat.p90 + unita);
    if (stat.p75 != null) righe.push('p75: ' + stat.p75 + unita);
    righe.push('<b>Mediana: ' + centro + unita + '</b>');
    if (stat.p25 != null) righe.push('p25: ' + stat.p25 + unita);
    if (stat.p10 != null) righe.push('p10: ' + stat.p10 + unita);
    if (stat.media != null) righe.push('(media: ' + stat.media + unita + ')');
    // Ogni metrica ha il proprio periodo di riferimento: i mm sul periodo
    // intero coprono piu' anni dei giorni di pioggia.
    var cl  = stat.clima;
    var rif = (cl && cl.label && cl.label !== 'n/d') ? cl.label + ' (' + cl.n + ' anni)' : '';
    righe.push(label + (rif ? ' \u2022 ' + rif : ''));
    aree.push({
        tipo: 'barra',
        x0: px0, x1: px1,
        y0: topPx - 4,
        y1: y0,
        testo: righe.join('<br>')
    });

    return { semi: semi };
}

function disegnaBaffo(ctx, cx, pyLo, pyHi, mezzaLargh, colore, spess) {
    ctx.strokeStyle = colore;
    ctx.lineWidth = spess;
    ctx.beginPath();
    ctx.moveTo(cx, pyLo); ctx.lineTo(cx, pyHi);                            // stelo
    ctx.moveTo(cx - mezzaLargh, pyLo); ctx.lineTo(cx + mezzaLargh, pyLo);  // cappello basso
    ctx.moveTo(cx - mezzaLargh, pyHi); ctx.lineTo(cx + mezzaLargh, pyHi);  // cappello alto
    ctx.stroke();
}

// Periodo climatologico complessivo dei SOLI periodi mostrati
function etichettaClima() {
    var anni = [];
    periodiMostrati().forEach(function(p) {
        [p.mm, p.gg].forEach(function(s) {
            if (s && s.clima && s.clima.anno_primo != null) {
                anni.push(s.clima.anno_primo, s.clima.anno_ultimo);
            }
        });
    });
    if (!anni.length) return '';
    return Math.min.apply(null, anni) + '\u2013' + Math.max.apply(null, anni);
}

// Anni scartati dalla climatologia perche' incompleti
function notaEsclusi() {
    var set = {};
    periodiMostrati().forEach(function(p) {
        (p.esclusi || []).forEach(function(e) { set[e.anno] = true; });
    });
    var anni = Object.keys(set).sort();
    if (!anni.length) return '';
    return ' \u2022 esclusi per dati incompleti: ' + anni.join(', ');
}

// Avviso se il periodo in corso ha esso stesso troppi giorni mancanti
function notaCopertura() {
    var scarsi = [];
    periodiMostrati().forEach(function(p) {
        var c = COPERTURA[p.id];
        if (c != null && c < COP_MIN) {
            scarsi.push(p.label + ' ' + Math.round(c * 100) + '%');
        }
    });
    if (!scarsi.length) return '';
    return ' \u2022 copertura dati in corso: ' + scarsi.join(', ');
}

// ---------------------------------------------------------------------------
// Costruzione dei riquadri (una sola volta)
// ---------------------------------------------------------------------------
function creaRiquadri() {
    var wrap = document.getElementById('grafici');
    GRAFICI.forEach(function(g) {
        var box = document.createElement('div');
        box.className = 'grafico-box';
        box.id = 'box-' + g.id;

        var cv = document.createElement('canvas');
        cv.id = 'chart-' + g.id;
        cv.setAttribute('role', 'img');
        var nomi = g.zone.map(function(id) { var p = periodo(id); return p ? p.label : id; });
        cv.setAttribute('aria-label', 'Statistiche pioggia ' + nomi.join(', ') +
                        ': mediana e percentili di mm e giorni di pioggia');

        box.appendChild(cv);
        wrap.appendChild(box);
        CONF_CANVAS[cv.id] = g;
        aggiungiListenerTooltip(cv);
    });
}

// ---------------------------------------------------------------------------
// Build
// ---------------------------------------------------------------------------
function build() {
    GRAFICI.forEach(function(g) {
        if (charts[g.id]) { charts[g.id].destroy(); charts[g.id] = null; }

        var box = document.getElementById('box-' + g.id);
        var cv  = document.getElementById('chart-' + g.id);
        var W   = box.offsetWidth || 400;
        // Riquadro stretto (affiancato o su mobile): si sviluppa in altezza
        var H   = Math.max(280, Math.min(Math.round(W * 0.85), 360));
        cv.style.height  = H + 'px';
        box.style.height = H + 'px';

        var maxi = calcolaMassimi(g.zone);

        charts[g.id] = new Chart(cv.getContext('2d'), {
            type: 'scatter',
            data: { datasets: [{ data: [], pointRadius: 0 }] },
            options: {
                animation: false,
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                layout: { padding: { left: 4, right: 4, top: 18, bottom: 30 } },
                scales: {
                    x: { min: 0, max: g.zone.length * LARGH_ZONA, display: false },
                    y: {
                        min: 0, max: maxi.mm,
                        position: 'left',
                        grid: { color: 'rgba(128,128,128,0.1)' },
                        ticks: {
                            font: { size: 9 }, color: '#2B6DB3',
                            callback: function(v) { return v + ' mm'; }
                        }
                    },
                    yg: {
                        min: 0, max: maxi.gg,
                        position: 'right',
                        grid: { display: false },
                        ticks: {
                            font: { size: 9 }, color: '#4D8F4D',
                            callback: function(v) { return v + ' gg'; }
                        }
                    }
                }
            },
            plugins: [pluvioPlugin]
        });
    });

    var cl = etichettaClima();
    document.getElementById('footer-note').textContent =
        (cl ? 'climatologia ' + cl + ' \u2022 ' : '') +
        (MODO === 'parziale'
            ? 'mediana e percentili dal 1\u00b0 del periodo fino al giorno corrente, su tutti gli anni'
            : 'mediana e percentili sui periodi interi')
        + ' \u2022 scale indipendenti per ogni grafico'
        + ' \u2022 giorno di pioggia: \u2265 ' + SOGLIA_GG + ' mm'
        + notaEsclusi() + notaCopertura();

    sendResize();
}

function sendResize() {
    window.parent.postMessage({
        action:   'resize',
        iframeId: 'stat-iframe-pluvio1',
        height:   document.body.scrollHeight
    }, '*');
}

// ---- toggle modalita' ----
document.getElementById('btn-parziale').addEventListener('click', function() {
    if (MODO !== 'parziale') window.location.href = window.location.pathname;
});
document.getElementById('btn-completo').addEventListener('click', function() {
    if (MODO !== 'completo') window.location.href = window.location.pathname + '?modo=completo';
});

// ---- tooltip ----
var tooltipEl = document.getElementById('chart-tooltip');

function aggiornaTooltip(canvas, clientX, clientY) {
    var rect = canvas.getBoundingClientRect();
    var mx = clientX - rect.left;
    var my = clientY - rect.top;
    var aree = hitAree[canvas.id] || [];

    var trovato = null;
    // prima le linee (piu' precise), poi le barre
    for (var i = 0; i < aree.length; i++) {
        var a = aree[i];
        if (a.tipo === 'linea' && mx >= a.x0 && mx <= a.x1 && Math.abs(my - a.py) <= 7) {
            trovato = a; break;
        }
    }
    if (!trovato) {
        for (var j = 0; j < aree.length; j++) {
            var b = aree[j];
            if (b.tipo === 'barra' && mx >= b.x0 && mx <= b.x1 && my >= b.y0 && my <= b.y1) {
                trovato = b; break;
            }
        }
    }

    if (trovato) {
        tooltipEl.innerHTML = trovato.testo;
        tooltipEl.style.display = 'block';
        tooltipEl.style.left = (clientX - tooltipEl.offsetWidth / 2) + 'px';
        tooltipEl.style.top  = (clientY - tooltipEl.offsetHeight - 10) + 'px';
    } else {
        tooltipEl.style.display = 'none';
    }
}

function nascondiTooltip() { tooltipEl.style.display = 'none'; }

// I listener stanno sul canvas, che non viene ricreato da build():
// vanno aggiunti una sola volta, altrimenti si accumulano ad ogni resize.
function aggiungiListenerTooltip(cv) {
    cv.addEventListener('mousemove', function(e) { aggiornaTooltip(cv, e.clientX, e.clientY); });
    cv.addEventListener('mouseleave', nascondiTooltip);
    cv.addEventListener('touchmove', function(e) {
        if (e.touches.length > 0) {
            e.preventDefault();
            aggiornaTooltip(cv, e.touches[0].clientX, e.touches[0].clientY);
        }
    }, { passive: false });
    cv.addEventListener('touchend', nascondiTooltip);
}

var resizeTimer = null;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(build, 200);
});

creaRiquadri();
build();
setTimeout(sendResize, 150);
setTimeout(sendResize, 500);
</script>

</body>
</html>