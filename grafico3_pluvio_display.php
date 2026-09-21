<?php
/**
 * ============================================================================
 * GRAFICO SEQUENZE - grafico3_pluvio_display.php
 * ============================================================================
 * Terzo iframe di pluvio_stat_display.php: durata delle sequenze secche e
 * piovose, mese per mese, con la sequenza in corso evidenziata.
 *
 * DUE PANNELLI (secche / piovose). Per ciascuno:
 *   - fascia scura   mediana-p75   (la meta' centrale delle sequenze)
 *   - fascia chiara  p75-p90
 *   - linea blu      mediana
 *   - pallini grigi  massimo storico del mese
 *   - PALLINO PULSANTE rosso = sequenza in corso, posta sul MESE DI INIZIO
 *   - pallino grigio scuro = ultima sequenza CONCLUSA di quel tipo
 *
 * I DUE PALLINI: nel pannello del tipo in corso compaiono entrambi, il rosso
 * per la sequenza aperta e il grigio per quella conclusa che la precede a
 * parita' di tipo. Nel pannello dell'altro tipo compare il solo grigio: e' la
 * sequenza interrotta proprio dall'inizio di quella in corso. Se oggi piove
 * dopo un periodo secco, i ruoli si scambiano subito.
 *
 * SCALA LOGARITMICA sulle ordinate: i massimi storici (fino a 80 giorni)
 * schiaccerebbero altrimenti mediane comprese fra 2 e 6 giorni.
 *
 * PERCHE' IL MESE DI INIZIO: una sequenza partita il 28 marzo e' figlia del
 * regime di marzo, anche se si conclude ad aprile.
 *
 * ASSE ORIZZONTALE: continuo, da 0 (1 gennaio) a 12 (31 dicembre). Le linee
 * verticali sono i CONFINI dei mesi e ogni etichetta e' scritta subito a
 * destra della propria linea: "set" marca il 1 settembre, non il centro del
 * mese. La climatologia di ogni mese e' invece disegnata sul punto centrale
 * della sua banda, perche' vale per tutto il mese.
 * I pallini delle sequenze sono invece posizionati sul GIORNO DI INIZIO:
 * una sequenza partita il 10 agosto cade a circa un terzo della banda di
 * agosto, non sulla sua verticale.
 *
 * Comunicazione con la pagina contenitore via postMessage (action: resize).
 * iframeId: stat-iframe-pluvio3
 */

ini_set('display_errors', 0);
error_reporting(0);

// envelop.php fornisce $pdo (lettura+scrittura): serve alla cache giornaliera.
require_once __DIR__ . '/../envelop.php';
require_once __DIR__ . '/api/api_sequenze_data.php';

$response = getSequenzeData();

if (!$response['success']) {
    echo "<div style='font-family:Arial;font-size:13px;color:#c00;padding:10px;'>
            Errore: " . htmlspecialchars($response['error'] ?? 'Dati non disponibili') . "
          </div>";
    exit;
}

$meta      = $response['meta'];
$mesi_js   = json_encode($response['mesi'],     JSON_UNESCAPED_UNICODE);
$corr_js   = json_encode($response['corrente'], JSON_UNESCAPED_UNICODE);
$ulti_js   = json_encode($response['ultime'] ?? ['secca' => null, 'piovosa' => null], JSON_UNESCAPED_UNICODE);
$meta_js   = json_encode($meta,                 JSON_UNESCAPED_UNICODE);
$oggi_disp = (new DateTime($meta['oggi']))->format('d/m/Y');

$pannelli = [
    ['id' => 'secca',   'titolo' => 'Sequenze secche',   'sub' => 'giorni consecutivi sotto ' . $meta['soglia'] . ' mm'],
    ['id' => 'piovosa', 'titolo' => 'Sequenze piovose',  'sub' => 'giorni consecutivi da ' . $meta['soglia'] . ' mm in su'],
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sequenze secche e piovose</title>
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
        .nota-corr { font-size: 10px; color: #666; margin-top: 4px; }
        .nota-corr b { color: #D62828; }
        .legend {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            max-width: 95%;
            margin: 8px auto 0 auto;
            font-size: 10px;
            color: #666;
            align-items: center;
        }
        .leg-item { display: flex; align-items: center; gap: 4px; }
        .leg-box  { width: 12px; height: 9px; border-radius: 1px; flex-shrink: 0; }
        .leg-line { width: 18px; height: 2px; flex-shrink: 0; }
        .leg-dot  { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
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
            .titolo    { font-size: 14px; }
            .pan-tit   { font-size: 13px; }
            .badge     { font-size: 11px; }
            .legend    { font-size: 11px; }
            .nota-corr { font-size: 11px; }
        }
    </style>
</head>
<body>

<div class="top-bar">
    <span class="titolo">Sequenze secche e piovose al <?= $oggi_disp ?></span>
</div>

<div id="chart-tooltip" class="chart-tooltip"></div>

<?php foreach ($pannelli as $i => $p): ?>
<div class="pannello">
    <div class="pan-head">
        <span class="pan-tit"><?= htmlspecialchars($p['titolo']) ?> &middot; <?= htmlspecialchars($p['sub']) ?></span>
        <span class="badge" id="badge-<?= $i ?>"></span>
    </div>
    <div class="chart-container" id="cont-<?= $i ?>">
        <canvas id="chart-<?= $i ?>" role="img"
                aria-label="<?= htmlspecialchars($p['titolo']) ?>: durata per mese di inizio, mediana e percentili sullo storico"></canvas>
        <div class="pulse" id="pulse-<?= $i ?>"></div>
    </div>
    <div class="nota-corr" id="nota-<?= $i ?>"></div>
    <div class="legend" id="leg-<?= $i ?>"></div>
</div>
<?php endforeach; ?>

<div class="stat-footer" id="footer-note"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
var MESI = <?= $mesi_js ?>;   // {secca:[12 voci], piovosa:[12 voci]}
var CORR = <?= $corr_js ?>;   // {tipo, giorni, mese_inizio, percentile, cond:{...}} oppure null
var ULTIME = <?= $ulti_js ?>; // {secca:{...}|null, piovosa:{...}|null}: ultima conclusa per tipo
var META = <?= $meta_js ?>;

var TIPI   = ['secca', 'piovosa'];
var MOBILE = false;
var charts = [];

// Una palette per tipo: le sequenze secche in toni caldi (ocra e marrone),
// quelle piovose nel blu usato dagli altri grafici pluvio. Il rosso della
// sequenza in corso resta comune, per coerenza con gli altri iframe.
var PAL = {
    secca: {
        fascia1: 'rgba(198,146,48,0.38)',   // ocra, mediana-p75
        fascia2: 'rgba(214,176,102,0.20)',  // ocra chiaro, p75-p90
        bordo:   'rgba(198,146,48,0.45)',
        mediana: '#8A5A2B',                 // marrone
        assi:    '#8A5A2B'
    },
    piovosa: {
        fascia1: 'rgba(55,138,221,0.30)',
        fascia2: 'rgba(55,138,221,0.13)',
        bordo:   'rgba(55,138,221,0.35)',
        mediana: '#2B6DB3',
        assi:    '#2B6DB3'
    }
};

var COL = {
    massimo:  '#aaa',
    corr:     '#D62828',
    ultima:   '#5F6B75',   // grigio-ardesia: si distingue dai pallini dei massimi
    testo:    '#999'
};

var NOMI = ['gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];

// Valori candidati per l'asse: progressione 1-2-3-4-5-6-8 ripetuta per decade,
// che su scala logaritmica da' intervalli visivamente regolari.
// Non vengono usati tutti: sceglieScala() tiene solo quelli che stanno
// abbastanza distanti da poter portare un'etichetta leggibile. Cosi' OGNI
// linea della griglia ha il suo numero sull'asse, e non restano righe mute.
var CANDIDATI = [1, 2, 3, 4, 5, 6, 8, 10, 15, 20, 25, 30, 40, 50, 60, 80,
                 100, 150, 200, 300, 400, 600, 800, 1000];

// Margine sopra il valore piu' alto: l'asse si ferma al primo candidato che
// lo supera. Alzarlo lascia piu' aria sopra i massimi.
var MARGINE_ASSE = 1.15;

// Scala calcolata per pannello: { max, tacche }. La riempie sceglieScala().
var SCALA = {};

function fmtGg(v) { return v + (v === 1 ? ' giorno' : ' giorni'); }

/**
 * Posizione sull'asse x di una data: 0 = 1 gennaio, 12 = 31 dicembre.
 * Il mese m occupa la banda [m-1, m]; il giorno cade al suo interno in
 * proporzione ai giorni del mese, cosi' il 10 agosto sta a 7 + 9/31.
 */
function xData(iso) {
    var p = String(iso).split('-');
    if (p.length < 3) return null;
    var mese = parseInt(p[1], 10), giorno = parseInt(p[2], 10);
    var nel  = new Date(Date.UTC(parseInt(p[0], 10), mese, 0)).getUTCDate();   // giorni del mese
    return (mese - 1) + (giorno - 0.5) / nel;
}

/** Centro della banda del mese (0-based): la climatologia vale per tutto il mese. */
function xMese(i) { return i + 0.5; }

/** Da '2026-08-24' a '24/08'. */
function fmtData(iso) {
    if (!iso) return '';
    var p = String(iso).split('-');
    return p.length < 3 ? '' : p[2] + '/' + p[1];
}

/** Da '2011-09-12' a 'set 2011': mese abbreviato e anno del record. */
function fmtMeseAnno(iso) {
    if (!iso) return '';
    var pz = String(iso).split('-');
    if (pz.length < 2) return '';
    return NOMI[parseInt(pz[1], 10) - 1] + ' ' + pz[0];
}

/**
 * Scala verticale del pannello, calcolata sui suoi soli dati.
 * I due pannelli hanno ordini di grandezza diversi (le sequenze secche
 * arrivano a oltre due mesi, quelle piovose a poco piu' di una settimana):
 * una scala comune schiaccerebbe il pannello delle piovose in basso.
 *
 * @param tipo    'secca' | 'piovosa'
 * @param altezza altezza utile del grafico in pixel
 */
function sceglieScala(tipo, altezza) {
    var mx = 1;
    MESI[tipo].forEach(function(m) { if (m.max != null) mx = Math.max(mx, m.max); });
    if (CORR && CORR.tipo === tipo) mx = Math.max(mx, CORR.giorni);
    if (ULTIME[tipo]) mx = Math.max(mx, ULTIME[tipo].giorni);

    // primo candidato che supera il dato piu' alto, con un po' di margine
    var soglia = mx * MARGINE_ASSE;
    var max = CANDIDATI[CANDIDATI.length - 1];
    for (var i = 0; i < CANDIDATI.length; i++) {
        if (CANDIDATI[i] >= soglia) { max = CANDIDATI[i]; break; }
    }

    // Tacche: si sale dal basso tenendo solo quelle abbastanza distanziate da
    // ospitare un'etichetta. Il massimo dell'asse si tiene sempre, e se cade
    // troppo vicino alla precedente e' quella a cedere il posto.
    var minPx  = MOBILE ? 15 : 13;
    var scalaL = Math.log10(max);
    var y = function(v) { return altezza * (1 - Math.log10(v) / scalaL); };

    var tacche = [1];
    CANDIDATI.forEach(function(v) {
        if (v <= 1 || v > max) return;
        if (Math.abs(y(v) - y(tacche[tacche.length - 1])) >= minPx) tacche.push(v);
    });
    if (tacche[tacche.length - 1] !== max) {
        if (Math.abs(y(max) - y(tacche[tacche.length - 1])) < minPx) tacche.pop();
        tacche.push(max);
    }

    return { max: max, tacche: tacche };
}

// ---------------------------------------------------------------------------
// Plugin di disegno (uno per pannello, legge options.plugins.seqPlugin)
// ---------------------------------------------------------------------------
var seqPlugin = {
    id: 'seqPlugin',

    // Griglia disegnata prima delle fasce, cosi' resta sotto e non le vela.
    // Copre TUTTA l'area del grafico, non solo la zona dei percentili: serve
    // anche a leggere l'altezza dei massimi assoluti, che stanno molto piu' su.
    beforeDatasetsDraw: function(chart) {
        var ctx = chart.ctx;
        var ca  = chart.chartArea;
        var ys  = chart.scales.y;
        var tacche = (SCALA[TIPI[chart.options.plugins.seqPlugin.idx]] || {}).tacche || [];

        ctx.save();
        ctx.lineWidth = 1;
        ctx.strokeStyle = 'rgba(128,128,128,0.22)';

        // Una linea per ogni tacca, e solo per quelle: sull'asse non restano
        // righe senza numero.
        tacche.forEach(function(v) {
            var y = Math.round(ys.getPixelForValue(v)) + 0.5;
            ctx.beginPath();
            ctx.moveTo(ca.left, y);
            ctx.lineTo(ca.right, y);
            ctx.stroke();
        });

        // Verticali sui CONFINI dei mesi: delimitano la banda di ciascun mese
        ctx.strokeStyle = 'rgba(128,128,128,0.18)';
        var xs = chart.scales.x;
        for (var m = 0; m <= 12; m++) {
            var x = Math.round(xs.getPixelForValue(m)) + 0.5;
            ctx.beginPath();
            ctx.moveTo(x, ca.top);
            ctx.lineTo(x, ca.bottom);
            ctx.stroke();
        }

        // bordo inferiore, per chiudere il reticolo
        ctx.strokeStyle = 'rgba(128,128,128,0.28)';
        ctx.beginPath();
        ctx.moveTo(ca.left, Math.round(ca.bottom) + 0.5);
        ctx.lineTo(ca.right, Math.round(ca.bottom) + 0.5);
        ctx.stroke();
        ctx.restore();
    },

    afterDatasetsDraw: function(chart) {
        var opt = chart.options.plugins.seqPlugin;
        if (!opt) return;
        var idx  = opt.idx;
        var tipo = TIPI[idx];
        var dati = MESI[tipo];
        var ctx  = chart.ctx;
        var ca   = chart.chartArea;
        var xp   = function(v) { return chart.scales.x.getPixelForValue(v); };
        var yp   = function(v) { return chart.scales.y.getPixelForValue(Math.max(v, 1)); };
        var pal  = PAL[tipo];
        var hit  = chart.$hit = { punti: [], corr: null, ultima: null };

        // Mesi con statistiche disponibili (servono almeno SEQ_MIN_SEQUENZE)
        var validi = [];
        dati.forEach(function(m, i) { if (m.p50 != null) validi.push(i); });
        if (!validi.length) return;

        // ---- profilo di una grandezza sui mesi con dati ----
        // Il valore di gennaio e quello di dicembre vengono prolungati fino ai
        // bordi: la climatologia di un mese vale per tutta la sua banda, e
        // senza il prolungamento resterebbero due strisce vuote agli estremi.
        function profilo(chiave) {
            var pt = validi.map(function(i) {
                return { x: xp(xMese(i)), y: yp(dati[i][chiave]) };
            });
            if (validi[0] === 0)  pt.unshift({ x: xp(0),  y: pt[0].y });
            if (validi[validi.length - 1] === 11) pt.push({ x: xp(12), y: pt[pt.length - 1].y });
            return pt;
        }

        // ---- fasce: si disegnano solo sui mesi contigui con dati ----
        function banda(chiaveLo, chiaveHi, colore) {
            var alto = profilo(chiaveHi), basso = profilo(chiaveLo);
            ctx.beginPath();
            alto.forEach(function(p, k) { if (k === 0) ctx.moveTo(p.x, p.y); else ctx.lineTo(p.x, p.y); });
            for (var k = basso.length - 1; k >= 0; k--) ctx.lineTo(basso[k].x, basso[k].y);
            ctx.closePath();
            ctx.fillStyle = colore;
            ctx.fill();
        }
        banda('p75', 'p90', pal.fascia2);
        banda('p50', 'p75', pal.fascia1);

        // bordo superiore del p90: il limite esterno resta leggibile
        ctx.strokeStyle = pal.bordo;
        ctx.lineWidth = 1;
        ctx.beginPath();
        profilo('p90').forEach(function(p, k) { if (k === 0) ctx.moveTo(p.x, p.y); else ctx.lineTo(p.x, p.y); });
        ctx.stroke();

        // ---- linea della mediana ----
        ctx.strokeStyle = pal.mediana;
        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';
        ctx.beginPath();
        profilo('p50').forEach(function(p, k) { if (k === 0) ctx.moveTo(p.x, p.y); else ctx.lineTo(p.x, p.y); });
        ctx.stroke();

        // ---- massimi storici ----
        ctx.fillStyle = COL.massimo;
        validi.forEach(function(i) {
            ctx.beginPath();
            ctx.arc(xp(xMese(i)), yp(dati[i].max), MOBILE ? 2.5 : 3, 0, Math.PI * 2);
            ctx.fill();
        });

        // Aree sensibili: oltre alla colonna, la quota in pixel di ogni valore
        // disegnato, cosi' il tooltip puo' rispondere sul singolo punto.
        validi.forEach(function(i) {
            var m = dati[i];
            hit.punti.push({
                i: i, x: xp(xMese(i)), y0: ca.top, y1: ca.bottom,
                yP50: yp(m.p50), yP75: yp(m.p75), yP90: yp(m.p90), yMax: yp(m.max)
            });
        });

        // ---- ultima sequenza conclusa (pallino grigio, senza pulsazione) ----
        // Disegnata PRIMA di quella in corso: se cadono sullo stesso punto,
        // sopra resta il rosso, che e' il dato piu' importante.
        var ult = ULTIME[tipo];
        var corrAttiva = CORR && CORR.tipo === tipo && CORR.giorni > 0;
        if (ult && ult.giorni > 0) {
            var xu = xp(xData(ult.data_inizio));
            var yu = yp(ult.giorni);

            ctx.strokeStyle = 'rgba(95,107,117,0.35)';
            ctx.lineWidth = 1;
            ctx.setLineDash([2, 4]);
            ctx.beginPath();
            ctx.moveTo(xu, ca.bottom);
            ctx.lineTo(xu, yu);
            ctx.stroke();
            ctx.setLineDash([]);

            ctx.beginPath();
            ctx.arc(xu, yu, MOBILE ? 5 : 6, 0, Math.PI * 2);
            ctx.fillStyle = COL.ultima;
            ctx.fill();
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.stroke();

            if (!MOBILE) {
                // Se la sequenza in corso parte dallo stesso mese, l'etichetta
                // grigia va dall'altro lato per non sovrapporsi alla rossa.
                var stessoMese = corrAttiva && CORR.mese_inizio === ult.mese_inizio;
                var destra = (ult.mese_inizio > 9);
                if (stessoMese) destra = !destra;
                ctx.fillStyle = COL.ultima;
                ctx.font = '9px Arial';
                ctx.textBaseline = 'bottom';
                ctx.textAlign = destra ? 'right' : 'left';
                ctx.fillText(ult.giorni + ' gg', xu + (destra ? -12 : 12), yu - 4);
            }
            hit.ultima = { x: xu, y: yu };
        }

        // ---- sequenza in corso ----
        var pulse = document.getElementById('pulse-' + idx);
        if (CORR && CORR.tipo === tipo && CORR.giorni > 0) {
            var xi = xp(xData(CORR.data_inizio));
            var yi = yp(CORR.giorni);

            ctx.strokeStyle = 'rgba(214,40,40,0.45)';
            ctx.lineWidth = 1;
            ctx.setLineDash([3, 3]);
            ctx.beginPath();
            ctx.moveTo(xi, ca.bottom);
            ctx.lineTo(xi, yi);
            ctx.stroke();
            ctx.setLineDash([]);

            if (!MOBILE) {
                ctx.fillStyle = COL.corr;
                ctx.font = 'bold 9px Arial';
                ctx.textBaseline = 'bottom';
                ctx.textAlign = (CORR.mese_inizio > 9) ? 'right' : 'left';
                var dx = (CORR.mese_inizio > 9) ? -12 : 12;
                ctx.fillText(CORR.giorni + ' gg', xi + dx, yi - 4);
            }

            pulse.style.left = xi + 'px';
            pulse.style.top  = yi + 'px';
            pulse.style.display = 'block';
            hit.corr = { x: xi, y: yi };
        } else {
            pulse.style.display = 'none';
        }

        // ---- etichette dei mesi ----
        // Allineate a sinistra sulla linea di confine: l'etichetta indica il
        // PRIMO giorno del mese, cosi' un pallino a meta' banda si legge come
        // meta' mese senza ambiguita'.
        ctx.fillStyle = COL.testo;
        ctx.font = 'bold 9px Arial';
        ctx.textAlign = 'left';
        ctx.textBaseline = 'top';
        for (var m = 0; m < 12; m++) {
            var evidenzia = (CORR && CORR.tipo === tipo && CORR.mese_inizio - 1 === m);
            var grigia    = (ult && ult.mese_inizio - 1 === m);
            ctx.fillStyle = evidenzia ? COL.corr : (grigia ? COL.ultima : COL.testo);
            ctx.fillText(MOBILE ? NOMI[m].charAt(0) : NOMI[m], xp(m) + 3, ca.bottom + 4);
        }
    }
};

/** Legenda del pannello, costruita dalla palette del tipo: i colori restano
 *  definiti solo in PAL, senza doverli ripetere nell'HTML. */
function riempiLegenda(idx) {
    var pal = PAL[TIPI[idx]];
    var el  = document.getElementById('leg-' + idx);
    if (!el) return;
    el.innerHTML =
        '<span class="leg-item"><span class="leg-box" style="background:' + pal.fascia1 + '"></span>mediana&ndash;p75</span>' +
        '<span class="leg-item"><span class="leg-box" style="background:' + pal.fascia2 + ';border:1px solid ' + pal.bordo + '"></span>p75&ndash;p90</span>' +
        '<span class="leg-item"><span class="leg-line" style="background:' + pal.mediana + '"></span>mediana</span>' +
        '<span class="leg-item"><span class="leg-dot" style="background:' + COL.massimo + '"></span>massimo storico</span>' +
        '<span class="leg-item"><span class="leg-dot" style="background:' + COL.corr + '"></span>sequenza in corso</span>' +
        '<span class="leg-item"><span class="leg-dot" style="background:' + COL.ultima + ';box-shadow:0 0 0 1.5px #fff"></span>ultima conclusa</span>' +
        '<span class="leg-item leg-opt">scala logaritmica</span>';
}

// ---------------------------------------------------------------------------
// Badge e nota della sequenza in corso
// ---------------------------------------------------------------------------
function riempiTesti(idx) {
    var tipo  = TIPI[idx];
    var badge = document.getElementById('badge-' + idx);
    var nota  = document.getElementById('nota-' + idx);
    var ult   = ULTIME[tipo];

    var attiva = CORR && CORR.tipo === tipo && CORR.giorni > 0;

    // Testo dell'ultima conclusa, usato in entrambi i casi
    function testoUltima(prefisso) {
        if (!ult || !ult.giorni) return '';
        var t = prefisso + fmtGg(ult.giorni) + ', dal ' + fmtData(ult.data_inizio)
              + ' al ' + fmtData(ult.data_fine);
        if (ult.percentile != null) {
            t += ' \u00b7 supera il ' + Math.round(ult.percentile) + '% delle sequenze di ' + ult.nome_mese;
        }
        return t;
    }

    // ---- nessuna sequenza in corso di questo tipo: comanda il pallino grigio ----
    if (!attiva) {
        if (ult && ult.giorni) {
            badge.style.background = COL.ultima;
            badge.style.color      = '#fff';
            badge.textContent = 'ultima conclusa: ' + ult.giorni + ' gg'
                              + (ult.percentile != null ? ' \u00b7 p' + Math.round(ult.percentile) : '');
            nota.innerHTML = testoUltima('<b style="color:' + COL.ultima + '">Ultima conclusa</b> \u00b7 ');
        } else {
            badge.style.background = '#eee';
            badge.style.color      = '#666';
            badge.textContent = 'nessuna sequenza recente';
            nota.textContent = '';
        }
        return;
    }

    // ---- sequenza in corso: badge colorato secondo la climatologia del mese ----
    var m = MESI[tipo][CORR.mese_inizio - 1];
    var sfondo = '#D9D9D9', testo = '#444', etichetta = 'nella norma';
    if (m && m.p90 != null) {
        // Tonalita' coerenti con la palette del pannello
        var alto  = (tipo === 'secca') ? '#A8641B' : '#2B6DB3';
        var medio = (tipo === 'secca') ? '#D9A441' : '#9ECAE1';
        if (CORR.giorni > m.p90)      { sfondo = alto;  testo = '#fff';    etichetta = 'oltre il p90'; }
        else if (CORR.giorni > m.p75) { sfondo = medio; testo = '#4a3008'; etichetta = 'fra p75 e p90'; }
        else if (CORR.giorni > m.p50) { sfondo = '#D9D9D9'; testo = '#444'; etichetta = 'sopra la mediana'; }
    }
    badge.style.background = sfondo;
    badge.style.color      = testo;
    badge.textContent = CORR.giorni + ' gg'
                      + (CORR.percentile != null ? ' \u00b7 p' + Math.round(CORR.percentile) : '')
                      + (MOBILE ? '' : ' \u00b7 ' + etichetta);

    // Nota: posizione fra le concluse, statistica condizionata, poi la precedente
    var parti = [];
    var dIni = CORR.data_inizio.split('-');
    parti.push('in corso dal ' + dIni[2] + '/' + dIni[1]);
    if (CORR.percentile != null) {
        parti.push('supera il ' + Math.round(CORR.percentile) + '% delle sequenze di ' + CORR.nome_mese);
    }
    var c = CORR.cond;
    if (c && c.n >= 4 && c.p50 != null) {
        parti.push('delle ' + c.n + ' arrivate a questo punto, met\u00e0 ha superato ' + fmtGg(Math.round(c.p50))
                 + ' (massimo ' + fmtGg(c.max) + ')');
    }
    var html = '<b>' + fmtGg(CORR.giorni) + '</b> \u00b7 ' + parti.join(' \u00b7 ');
    var prec = testoUltima('precedente: ');
    if (prec) html += '<br><span style="color:' + COL.ultima + '">' + prec + '</span>';
    nota.innerHTML = html;
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
          ? Math.max(200, Math.round(W * 0.70))
          : Math.max(200, Math.min(Math.round(W * 0.38), 290));

    // L'altezza utile e' quella del riquadro meno i margini del layout:
    // serve a sceglieScala() per capire quante etichette ci stanno.
    var altezzaUtile = H - 32;
    TIPI.forEach(function(tipo) { SCALA[tipo] = sceglieScala(tipo, altezzaUtile); });

    TIPI.forEach(function(tipo, idx) {
        var cont = document.getElementById('cont-' + idx);
        cont.style.height = H + 'px';
        document.getElementById('chart-' + idx).style.height = H + 'px';

        var ch = new Chart(document.getElementById('chart-' + idx).getContext('2d'), {
            type: 'scatter',
            data: { datasets: [{ data: [], pointRadius: 0 }] },
            options: {
                animation: false,
                responsive: true,
                maintainAspectRatio: false,
                events: [],
                plugins: { legend: { display: false }, tooltip: { enabled: false },
                           seqPlugin: { idx: idx } },
                layout: { padding: { left: 4, right: 10, top: 14, bottom: 18 } },
                scales: {
                    x: { min: 0, max: 12, display: false },
                    y: {
                        type: 'logarithmic',
                        min: 1, max: SCALA[tipo].max,
                        position: 'left',
                        grid: { display: false },     // la disegna seqPlugin, sulle tacche
                        border: { display: false },
                        // Le tacche sono imposte: senza questo Chart.js ne
                        // genererebbe di proprie, che non coinciderebbero con
                        // le linee della griglia.
                        afterBuildTicks: function(scale) {
                            scale.ticks = SCALA[tipo].tacche.map(function(v) { return { value: v }; });
                        },
                        ticks: {
                            autoSkip: false,
                            font: { size: MOBILE ? 8 : 9 }, color: PAL[tipo].assi,
                            callback: function(v) { return MOBILE ? v : v + ' gg'; }
                        }
                    }
                }
            },
            plugins: [seqPlugin]
        });
        charts.push(ch);
        riempiTesti(idx);
        riempiLegenda(idx);
        aggiungiTooltipCanvas(ch);
    });

    scriviFooter();
    sendResize();
}

function scriviFooter() {
    var nota = 'climatologia ' + META.clima_label + ' (' + META.n_anni + ' anni, '
             + META.n_sequenze + ' sequenze)'
             + ' \u2022 giorno di pioggia: \u2265 ' + META.soglia + ' mm'
             + ' \u2022 ogni sequenza \u00e8 attribuita al mese in cui inizia'
             + ' \u2022 un giorno mancante interrompe la sequenza';

    var anni = (META.esclusi || []).map(function(e) { return e.anno; }).sort();
    if (anni.length) nota += ' \u2022 esclusi per dati incompleti: ' + anni.join(', ');

    var scarsi = [];
    TIPI.forEach(function(tipo, idx) {
        var pochi = MESI[tipo].filter(function(m) { return m.p50 == null; })
                              .map(function(m) { return m.nome; });
        if (pochi.length) scarsi.push((idx === 0 ? 'secche' : 'piovose') + ': ' + pochi.join(', '));
    });
    if (scarsi.length) nota += ' \u2022 mesi con meno di ' + META.min_sequenze
                             + ' sequenze, esclusi dai percentili \u2014 ' + scarsi.join('; ');

    document.getElementById('footer-note').textContent = nota;
}

function sendResize() {
    window.parent.postMessage({
        action:   'resize',
        iframeId: 'stat-iframe-pluvio3',
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

var MESI_LUNGHI = ['gennaio','febbraio','marzo','aprile','maggio','giugno',
                   'luglio','agosto','settembre','ottobre','novembre','dicembre'];

function testoTooltip(ch, mx, my) {
    var idx  = ch.options.plugins.seqPlugin.idx;
    var tipo = TIPI[idx];
    var hit  = ch.$hit || {};

    // 1) pallino della sequenza in corso
    if (hit.corr && Math.hypot(mx - hit.corr.x, my - hit.corr.y) <= 14) {
        var r = ['<b>Sequenza in corso: ' + fmtGg(CORR.giorni) + '</b>'];
        var d = CORR.data_inizio.split('-');
        r.push('iniziata il ' + d[2] + '/' + d[1] + '/' + d[0]);
        if (CORR.percentile != null) r.push('supera il ' + Math.round(CORR.percentile) + '% delle sequenze di ' + CORR.nome_mese);
        var c = CORR.cond;
        if (c && c.n >= 4 && c.p50 != null) {
            r.push('storicamente arrivate a ' + CORR.giorni + ' gg: ' + c.n);
            r.push('di queste, met\u00e0 ha raggiunto ' + fmtGg(Math.round(c.p50)));
            r.push('una su dieci ha raggiunto ' + fmtGg(Math.round(c.p90)));
        }
        return r.join('<br>');
    }

    // 2) pallino dell'ultima sequenza conclusa
    var ult = ULTIME[tipo];
    if (ult && hit.ultima && Math.hypot(mx - hit.ultima.x, my - hit.ultima.y) <= 14) {
        var ru = ['<b>Ultima ' + (tipo === 'secca' ? 'secca' : 'piovosa') + ' conclusa: ' + fmtGg(ult.giorni) + '</b>'];
        ru.push('dal ' + fmtData(ult.data_inizio) + ' al ' + fmtData(ult.data_fine));
        if (ult.percentile != null) {
            ru.push('supera il ' + Math.round(ult.percentile) + '% delle sequenze di ' + ult.nome_mese);
        }
        if (CORR && CORR.tipo === tipo) ru.push('la sequenza in corso \u00e8 quella in rosso');
        return ru.join('<br>');
    }

    // 3) mese piu' vicino in orizzontale
    var best = null, dist = 1e9;
    (hit.punti || []).forEach(function(p) {
        var d = Math.abs(mx - p.x);
        if (d < dist && my >= p.y0 - 6 && my <= p.y1 + 14) { dist = d; best = p; }
    });
    if (!best || dist >= 22) return null;

    var m   = MESI[tipo][best.i];
    var nom = MESI_LUNGHI[best.i];

    // 4) singolo valore, se il puntatore e' vicino alla sua quota.
    //    L'ordine decide le precedenze dove due curve quasi si toccano:
    //    prima il massimo (isolato), poi dall'alto verso il basso.
    if (m.p50 != null) {
        var quando = fmtMeseAnno(m.max_inizio);
        var vicini = [
            { y: best.yMax, et: 'Massimo storico', v: m.max,
              suff: quando ? ' (' + quando + ')' : '', extra: null },
            { y: best.yP90, et: 'p90',     v: m.p90, suff: '', extra: 'una sequenza su dieci supera questa durata' },
            { y: best.yP75, et: 'p75',     v: m.p75, suff: '', extra: 'una su quattro supera questa durata' },
            { y: best.yP50, et: 'Mediana', v: m.p50, suff: '', extra: 'met\u00e0 delle sequenze sta sotto, met\u00e0 sopra' }
        ];
        var soglia = MOBILE ? 12 : 9;
        for (var k = 0; k < vicini.length; k++) {
            if (Math.abs(my - vicini[k].y) <= soglia) {
                var t = '<b>' + vicini[k].et + ': ' + fmtGg(vicini[k].v) + vicini[k].suff + '</b>'
                      + '<br>' + nom + ' \u00b7 ' + m.n + ' sequenze';
                if (vicini[k].extra) t += '<br>' + vicini[k].extra;
                return t;
            }
        }
    }

    // 5) altrove nella colonna: il riepilogo del mese
    var rr = ['<b>' + nom + '</b> \u00b7 ' + m.n + ' sequenze'];
    if (m.p50 != null) {
        rr.push('mediana: ' + fmtGg(m.p50));
        rr.push('p75: ' + fmtGg(m.p75) + ' \u00b7 p90: ' + fmtGg(m.p90));
        var q = fmtMeseAnno(m.max_inizio);
        rr.push('massimo storico: ' + fmtGg(m.max) + (q ? ' (' + q + ')' : ''));
    } else {
        rr.push('troppe poche sequenze per i percentili');
    }
    return rr.join('<br>');
}

function aggiungiTooltipCanvas(ch) {
    var canvas = ch.canvas;
    if (canvas.$tooltipOk) return;   // il canvas sopravvive ai destroy
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