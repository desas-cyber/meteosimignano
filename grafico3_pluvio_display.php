<?php
/**
 * ============================================================================
 * GRAFICO SEQUENZE - grafico3_pluvio_display.php
 * Versione 4.3 - 02/10/2026
 * ============================================================================
 * Terzo iframe di pluvio_stat_display.php: durata delle sequenze secche e
 * piovose, mese per mese, con la sequenza in corso evidenziata.
 *
 * DUE PANNELLI (secche / piovose). Per ciascuno:
 *   - fascia scura   mediana-p75   (la meta' centrale delle sequenze)
 *   - fascia chiara  p75-p90
 *   - linea blu      mediana
 *   - pallini grigi  massimo storico del mese
 *   - BARRE SOTTILI  le sequenze dell'anno, una per sequenza, posate sul
 *                    GIORNO DI INIZIO e alte quanto la durata
 *   - MM SULLE BARRE PIOVOSE: sopra la cima di ogni barra del pannello
 *                    piovose c'e' la pioggia complessiva della sequenza (mm),
 *                    solo per le sequenze di almeno MM_MIN_GG giorni (2).
 *                    Dove le barre sono fitte l'etichetta viene omessa (hanno
 *                    la precedenza le sequenze piu' piovose): il valore resta
 *                    nel tooltip della barra
 *   - PALLINO PULSANTE rosso = sequenza in corso, in cima alla sua barra
 *   - pallino grigio scuro = ultima sequenza CONCLUSA di quel tipo
 *
 * CONFRONTO FRA ANNI: due pulsanti in alto, "nessun confronto" (default,
 * una sola serie) e "confronto". Il confronto parte dall'anno precedente;
 * le frecce accanto all'anno scorrono indietro fino al primo anno valido.
 * Le barre dell'anno scelto stanno affiancate a destra di quelle
 * dell'anno corrente, in viola.
 *
 * VISTA ISTOGRAMMA: il pulsante "istogramma" sostituisce i due grafici con
 * la distribuzione delle durate: quante sequenze ci sono state nell'anno
 * per classe di durata (CLASSI_ISTO). Il
 * confronto affianca l'istogramma dell'anno scelto, come nella vista per
 * mese. La linea a gradini grigia e' la media annua sugli anni completi.
 *
 * CLASSI DI AMPIEZZA DIVERSA per le secche: strette dove le sequenze sono
 * tante (1, 2, 3 giorni), larghe nella coda (21-30, 31-45, 46+). Con classi
 * da 1 giorno l'anno singolo (30-60 sequenze su 100 classi) mostrava picchi
 * dovuti solo al caso. L'altezza e' il NUMERO di sequenze nella classe: una
 * classe larga ne raccoglie di piu' anche per la sua ampiezza, per questo la
 * media puo' risalire (es. 4-5 e 11-15). Scelta voluta: tiene visibili le
 * secche lunghe, che sono gli eventi piu' interessanti. Le piovose restano a
 * classi da 1 giorno (1..9, 10+): sono gia' poche classi, ben popolate.
 * Tutto e' calcolato nel browser da ANNI: nessun dato in piu' dall'API.
 * Ogni sequenza appartiene all'anno in cui INIZIA: quella partita il 26
 * dicembre si vede selezionando quell'anno, non il successivo.
 *
 * I DUE PALLINI: nel pannello del tipo in corso compaiono entrambi, il rosso
 * per la sequenza aperta e il grigio per quella conclusa che la precede a
 * parita' di tipo. Nel pannello dell'altro tipo compare il solo grigio: e' la
 * sequenza interrotta proprio dall'inizio di quella in corso. Se oggi piove
 * dopo un periodo secco, i ruoli si scambiano subito.
 *
 * SCALA LOGARITMICA sulle ordinate: i massimi storici (fino a 80 giorni)
 * schiaccerebbero altrimenti mediane comprese fra 2 e 6 giorni.
 * L'asse parte da Y_MIN (< 1): su scala log una sequenza di 1 giorno avrebbe
 * altrimenti altezza zero e sparirebbe.
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
$anni_js   = json_encode((object)($response['anni'] ?? []), JSON_UNESCAPED_UNICODE);
$anno_corr = (int)substr($meta['oggi'], 0, 4);
// Anni selezionabili per il confronto: tutti quelli con sequenze, escluso il corrente
$anni_conf = array_values(array_filter(array_keys($response['anni'] ?? []),
                                       function ($a) use ($anno_corr) { return (int)$a !== $anno_corr; }));
rsort($anni_conf);
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
        .confronto { display: flex; align-items: center; gap: 8px; font-size: 11px; }
        .seg { display: inline-flex; border: 1px solid #d6d6d6; border-radius: 14px; overflow: hidden; }
        .seg button {
            font-family: Arial, sans-serif; font-size: 11px; color: #666;
            background: #fff; border: 0; border-left: 1px solid #e4e4e4;
            padding: 4px 11px; cursor: pointer;
        }
        .seg button:first-child { border-left: 0; }
        .seg button.on { background: #5F6B75; color: #fff; }
        .seg button.on.conf { background: #7B6BB8; }
        .comandi { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .stepper { display: none; align-items: center; gap: 2px; color: #7B6BB8; font-weight: bold; }
        .stepper.vis { display: inline-flex; }
        .stepper button {
            font-family: Arial, sans-serif; font-size: 13px; line-height: 1;
            color: #7B6BB8; background: none; border: 0; padding: 2px 6px;
            cursor: pointer; border-radius: 10px;
        }
        .stepper button:hover { background: rgba(123,107,184,0.12); }
        .stepper button:disabled { color: #ccc; background: none; cursor: default; }
        .stepper span { min-width: 34px; text-align: center; }
        .seg button:focus-visible, .stepper button:focus-visible { outline: 2px solid rgba(123,107,184,0.45); outline-offset: -2px; }
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
        .leg-bar  { width: 3px; height: 11px; border-radius: 2px 2px 1px 1px; flex-shrink: 0; }
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
            .seg button { font-size: 12px; }
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
    <div class="comandi">
    <div class="seg" role="group" aria-label="Tipo di grafico">
        <button type="button" id="btn-mese" class="on" aria-pressed="true">per mese</button>
        <button type="button" id="btn-isto" aria-pressed="false">istogramma</button>
    </div>
    <?php if ($anni_conf): ?>
    <div class="confronto">
        <div class="seg" role="group" aria-label="Confronto con un altro anno">
            <button type="button" id="btn-nessuno" class="on" aria-pressed="true">nessun confronto</button>
            <button type="button" id="btn-conf" class="conf" aria-pressed="false">confronto</button>
        </div>
        <div class="stepper" id="stepper">
            <button type="button" id="anno-prec" aria-label="Anno precedente">&lsaquo;</button>
            <span id="anno-lab" aria-live="polite"></span>
            <button type="button" id="anno-succ" aria-label="Anno successivo">&rsaquo;</button>
        </div>
    </div>
    <?php endif; ?>
    </div>
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
                aria-label="<?= htmlspecialchars($p['titolo']) ?>: sequenze dell'anno sul giorno di inizio, mediana e percentili sullo storico"></canvas>
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
var ANNI = <?= $anni_js ?>;   // {"2025": {secca:[[m,g,n,mm(,1)],...], piovosa:[...]}, ...}; mm null per le secche, 1 = aperta

var ANNO_CORR = <?= $anno_corr ?>;
var ANNO_CONF = '';           // anno affiancato; '' = nessun confronto (default)
// Anni confrontabili, dal piu' recente al piu' vecchio: il primo e' quello
// da cui parte il confronto (di norma l'anno precedente).
var ANNI_CONF = <?= json_encode(array_map('intval', $anni_conf)) ?>;

// Vista: 'mese' (grafico per mese di inizio, default) o 'istogramma'
var VISTA = 'mese';

// Classi dell'istogramma: [da, a] in giorni, estremi inclusi; null = senza
// limite superiore. Etichette ricavate dagli estremi.
var CLASSI_ISTO = {
    secca:   [[1,1], [2,2], [3,3], [4,5], [6,7], [8,10], [11,15], [16,20],
              [21,30], [31,45], [46,null]],
    piovosa: [[1,1], [2,2], [3,3], [4,4], [5,5], [6,6], [7,7], [8,8],
              [9,9], [10,null]]
};

/** Etichetta di una classe: '4\u20135', '46+', '3'. */
function etichettaClasse(c) {
    if (c[1] === null) return c[0] + '+';
    return c[0] === c[1] ? String(c[0]) : c[0] + '\u2013' + c[1];
}

/** Indice (1-based) della classe che contiene una durata. */
function classeDi(tipo, n) {
    var cl = CLASSI_ISTO[tipo];
    for (var i = 0; i < cl.length; i++) {
        if (n >= cl[i][0] && (cl[i][1] === null || n <= cl[i][1])) return i + 1;
    }
    return cl.length;
}

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
        assi:    '#8A5A2B',
        barra:   '#C69230'                  // barre delle sequenze dell'anno
    },
    piovosa: {
        fascia1: 'rgba(55,138,221,0.30)',
        fascia2: 'rgba(55,138,221,0.13)',
        bordo:   'rgba(55,138,221,0.35)',
        mediana: '#2B6DB3',
        assi:    '#2B6DB3',
        barra:   '#3E8AD6'
    }
};

var COL = {
    massimo:  '#aaa',
    corr:     '#D62828',
    ultima:   '#5F6B75',   // grigio-ardesia: si distingue dai pallini dei massimi
    conf:     '#7B6BB8',   // viola: anno di confronto, uguale nei due pannelli
    testo:    '#999'
};

// Barre: larghezza e distanza fra le due affiancate (px). Su mobile un giorno
// vale meno di un pixel: le barre si assottigliano, e sequenze vicine
// possono sovrapporsi.
var BARRA = { w: 3, wMob: 2, gap: 1 };

// Base dell'asse logaritmico: sotto 1, cosi' la sequenza di 1 giorno resta
// visibile come un breve tratto.
var Y_MIN = 0.7;

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

// Durata minima (giorni) perche' i mm vengano scritti sul grafico. Le
// sequenze piu' corte li mostrano solo nel tooltip e nella nota.
var MM_MIN_GG = 2;

/** Millimetri arrotondati all'unita': 42.3 -> '42 mm'. '' se manca. */
function fmtMm(v) {
    if (v == null) return '';
    return Math.round(v) + ' mm';
}
/** Versione compatta per le etichette sulle barre: solo il numero intero. */
function fmtMmBreve(v) {
    return String(Math.round(v));
}
/**
 * Posizione storica di una sequenza (CORR / ULTIME) nel suo mese di inizio:
 * 'storicamente rientra nel 29% delle sequenze piu' lunghe di set'.
 * percentile = quota di sequenze storiche PIU' CORTE; il complemento e' la
 * quota di quelle lunghe almeno quanto questa. '' se il percentile manca.
 */
function fmtRientra(s) {
    if (!s || s.percentile == null) return '';
    var q = Math.round(100 - s.percentile);
    // "nell'8%", "nell'11%", "nell'80%": davanti a otto, undici, ottanta
    var prep = (q === 1 || q === 8 || q === 11 || (q >= 80 && q <= 89)) ? "nell'" : 'nel ';
    return 'per ' + s.nome_mese +' rientra ' + prep + q
         + '% delle sequenze di durata >=' ;
}
/** Pioggia di una sequenza piovosa (CORR / ULTIME) preceduta dal separatore. */
function suffMm(s) {
    return (s && s.tipo === 'piovosa' && s.mm != null) ? ' \u00b7 ' + fmtMm(s.mm) : '';
}

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

/** Stessa posizione di xData() partendo da anno, mese e giorno numerici. */
function xMG(anno, mese, giorno) {
    var nel = new Date(Date.UTC(anno, mese, 0)).getUTCDate();
    return (mese - 1) + (giorno - 0.5) / nel;
}

/** Sequenze di un anno e di un tipo come oggetti; [] se l'anno non c'e'. */
function sequenzeAnno(anno, tipo) {
    var a = anno && ANNI[anno];
    if (!a || !a[tipo]) return [];
    return a[tipo].map(function(v) {
        return { anno: +anno, m: v[0], g: v[1], n: v[2],
                 mm: (v[3] == null ? null : v[3]), aperta: v[4] === 1 };
    });
}

/** Data di fine di una sequenza (inizio + n - 1) come 'gg/mm'. */
function fineSeq(s) {
    var d = new Date(Date.UTC(s.anno, s.m - 1, s.g + s.n - 1));
    return ('0' + d.getUTCDate()).slice(-2) + '/' + ('0' + (d.getUTCMonth() + 1)).slice(-2);
}
function inizioSeq(s) {
    return ('0' + s.g).slice(-2) + '/' + ('0' + s.m).slice(-2);
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
    var lMin = Math.log10(Y_MIN), scalaL = Math.log10(max) - lMin;
    var y = function(v) { return altezza * (1 - (Math.log10(v) - lMin) / scalaL); };

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

        // ---- barre delle sequenze ----
        // Anno corrente nel colore del pannello; anno di confronto in viola,
        // affiancato a destra. Senza confronto la barra sta esattamente sul
        // giorno di inizio.
        var bw   = MOBILE ? BARRA.wMob : BARRA.w;
        var dOff = ANNO_CONF ? (bw + BARRA.gap) / 2 : 0;
        var yBase = ca.bottom;
        hit.barre = [];

        function barra(s, dx, colore) {
            var x  = xp(xMG(s.anno, s.m, s.g)) + dx;
            var yt = chart.scales.y.getPixelForValue(s.n);
            var h  = Math.max(yBase - yt, bw);
            var r  = bw / 2;
            ctx.fillStyle = colore;
            ctx.beginPath();
            if (ctx.roundRect) ctx.roundRect(x - bw / 2, yBase - h, bw, h, [r, r, 0, 0]);
            else ctx.rect(x - bw / 2, yBase - h, bw, h);
            ctx.fill();
            hit.barre.push({ x: x, yTop: yBase - h, s: s });
            return { x: x, y: yt, yTop: yBase - h, s: s };
        }

        ctx.save();
        ctx.beginPath();
        ctx.rect(ca.left, ca.top, ca.right - ca.left, ca.bottom - ca.top);
        ctx.clip();
        sequenzeAnno(ANNO_CONF, tipo).forEach(function(s) { barra(s, dOff, COL.conf); });
        // Punta della barra dell'anno corrente per ogni data di inizio:
        // i pallini della sequenza in corso e dell'ultima conclusa ci si posano sopra.
        var punte = {};
        sequenzeAnno(ANNO_CORR, tipo).forEach(function(s) {
            punte[s.m + '-' + s.g] = barra(s, -dOff, pal.barra);
        });
        ctx.restore();

        /** Punta della barra corrente per una data ISO, o null se non e' nell'anno. */
        function punta(iso) {
            var p = String(iso).split('-');
            if (+p[0] !== ANNO_CORR) return null;
            return punte[(+p[1]) + '-' + (+p[2])] || null;
        }

        // ---- massimi storici ----
        // Dopo le barre: i pallini grigi restano leggibili anche se una barra
        // passa sulla verticale del mese.
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

        // Zone gia' occupate da pallini ed etichette "gg": le etichette dei mm
        // sulle barre piovose, disegnate per ultime, le evitano.
        var occupati = [];
        function occupaTesto(txt, x, y, allinea) {
            var w = ctx.measureText(txt).width;
            var x0 = allinea === 'right' ? x - w : x;
            occupati.push({ x0: x0, x1: x0 + w, y0: y - 10, y1: y });
        }
        var conPallino = {};   // barre con un pallino in cima: 'm-g' => true

        // ---- ultima sequenza conclusa (pallino grigio, senza pulsazione) ----
        // Disegnata PRIMA di quella in corso: se cadono sullo stesso punto,
        // sopra resta il rosso, che e' il dato piu' importante.
        var ult = ULTIME[tipo];
        var corrAttiva = CORR && CORR.tipo === tipo && CORR.giorni > 0;
        if (ult && ult.giorni > 0) {
            // Sulla punta della sua barra se appartiene all'anno corrente;
            // altrimenti (iniziata l'anno prima) resta il vecchio tratteggio.
            var pu = punta(ult.data_inizio);
            var xu = pu ? pu.x : xp(xData(ult.data_inizio));
            var yu = pu ? pu.y : yp(ult.giorni);

            if (!pu) {
                ctx.strokeStyle = 'rgba(95,107,117,0.35)';
                ctx.lineWidth = 1;
                ctx.setLineDash([2, 4]);
                ctx.beginPath();
                ctx.moveTo(xu, ca.bottom);
                ctx.lineTo(xu, yu);
                ctx.stroke();
                ctx.setLineDash([]);
            }

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
                var tu = ult.giorni + ' gg' + (ult.giorni >= MM_MIN_GG ? suffMm(ult) : '');
                ctx.fillText(tu, xu + (destra ? -12 : 12), yu - 4);
                occupaTesto(tu, xu + (destra ? -12 : 12), yu - 4, destra ? 'right' : 'left');
            }
            occupati.push({ x0: xu - 7, x1: xu + 7, y0: yu - 7, y1: yu + 7 });
            if (pu) conPallino[pu.s.m + '-' + pu.s.g] = true;
            hit.ultima = { x: xu, y: yu };
        }

        // ---- sequenza in corso ----
        var pulse = document.getElementById('pulse-' + idx);
        if (CORR && CORR.tipo === tipo && CORR.giorni > 0) {
            var pc = punta(CORR.data_inizio);
            var xi = pc ? pc.x : xp(xData(CORR.data_inizio));
            var yi = pc ? pc.y : yp(CORR.giorni);

            if (!pc) {
                // Iniziata l'anno scorso: la sua barra sta in quell'anno
                ctx.strokeStyle = 'rgba(214,40,40,0.45)';
                ctx.lineWidth = 1;
                ctx.setLineDash([3, 3]);
                ctx.beginPath();
                ctx.moveTo(xi, ca.bottom);
                ctx.lineTo(xi, yi);
                ctx.stroke();
                ctx.setLineDash([]);
            }

            if (!MOBILE) {
                ctx.fillStyle = COL.corr;
                ctx.font = 'bold 9px Arial';
                ctx.textBaseline = 'bottom';
                ctx.textAlign = (CORR.mese_inizio > 9) ? 'right' : 'left';
                var dx = (CORR.mese_inizio > 9) ? -12 : 12;
                var tc = CORR.giorni + ' gg' + (CORR.giorni >= MM_MIN_GG ? suffMm(CORR) : '');
                ctx.fillText(tc, xi + dx, yi - 4);
                occupaTesto(tc, xi + dx, yi - 4, ctx.textAlign);
            }
            occupati.push({ x0: xi - 8, x1: xi + 8, y0: yi - 8, y1: yi + 8 });
            if (pc) conPallino[pc.s.m + '-' + pc.s.g] = true;

            pulse.style.left = xi + 'px';
            pulse.style.top  = yi + 'px';
            pulse.style.display = 'block';
            hit.corr = { x: xi, y: yi };
        } else {
            pulse.style.display = 'none';
        }

        // ---- mm complessivi sopra le barre piovose ----
        // Un numero (mm) centrato sopra la cima di ogni barra dell'anno
        // corrente. Si piazzano prima le sequenze piu' piovose; un'etichetta
        // che finirebbe sopra un'altra, sopra un pallino o sul corpo di una
        // barra vicina viene omessa: il valore resta nel tooltip della barra.
        if (tipo === 'piovosa') {
            ctx.font = (MOBILE ? 7 : 8) + 'px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillStyle = pal.mediana;
            var altT = MOBILE ? 8 : 9;
            var cand = [];
            Object.keys(punte).forEach(function(k) {
                if (punte[k].s.mm != null && punte[k].s.n >= MM_MIN_GG) cand.push(punte[k]);
            });
            cand.sort(function(a, b) { return b.s.mm - a.s.mm; });
            cand.forEach(function(p) {
                var haPallino = conPallino[p.s.m + '-' + p.s.g];
                // Su desktop i mm di queste due sequenze sono gia' nell'etichetta "gg"
                if (haPallino && !MOBILE) return;
                var txt = fmtMmBreve(p.s.mm);
                var w   = ctx.measureText(txt).width;
                var yb  = p.yTop - (haPallino ? 8 : 2);
                var xc  = Math.max(ca.left + w / 2, Math.min(p.x, ca.right - w / 2));
                var q   = { x0: xc - w / 2 - 1, x1: xc + w / 2 + 1, y0: yb - altT, y1: yb };
                if (q.y0 < 0) return;
                var libero = occupati.every(function(o) {
                    return q.x1 <= o.x0 || q.x0 >= o.x1 || q.y1 <= o.y0 || q.y0 >= o.y1;
                });
                // corpo delle altre barre (anche quelle dell'anno di confronto)
                if (libero) libero = hit.barre.every(function(b) {
                    if (b.s === p.s) return true;
                    return q.x1 <= b.x - bw / 2 || q.x0 >= b.x + bw / 2 || q.y1 <= b.yTop;
                });
                if (!libero) return;
                ctx.fillText(txt, xc, yb);
                occupati.push(q);
            });
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
    if (VISTA === 'istogramma') {
        el.innerHTML =
            '<span class="leg-item"><span class="leg-box" style="background:' + pal.barra + '"></span>sequenze ' + ANNO_CORR + ' (fino a oggi)</span>' +
            (ANNO_CONF ? '<span class="leg-item"><span class="leg-box" style="background:' + COL.conf + '"></span>sequenze ' + ANNO_CONF + '</span>' : '') +
            '<span class="leg-item"><span class="leg-box" style="background:rgba(128,128,128,0.10);border:1px solid rgba(110,110,110,0.55)"></span>media annua ' + mediaDurate(TIPI[idx]).label + '</span>' +
            '<span class="leg-item"><span class="leg-dot" style="background:' + COL.corr + '"></span>sequenza in corso</span>';
        return;
    }
    var conf = ANNO_CONF
        ? '<span class="leg-item"><span class="leg-bar" style="background:' + COL.conf + '"></span>sequenze ' + ANNO_CONF + '</span>'
        : '';
    el.innerHTML =
        '<span class="leg-item"><span class="leg-bar" style="background:' + pal.barra + '"></span>sequenze ' + ANNO_CORR + '</span>' +
        conf +
        (TIPI[idx] === 'piovosa' ? '<span class="leg-item" style="color:' + pal.mediana + '">numeri sulle barre: mm della sequenza (da ' + MM_MIN_GG + ' gg)</span>' : '') +
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
              + ' al ' + fmtData(ult.data_fine) + suffMm(ult);
        if (fmtRientra(ult)) t += ' \u00b7 ' + fmtRientra(ult);
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

    // Nota: posizione fra le concluse dello stesso mese, poi la precedente
    var parti = [];
    var dIni = CORR.data_inizio.split('-');
    parti.push('in corso dal ' + dIni[2] + '/' + dIni[1]);
    if (suffMm(CORR)) parti.push(fmtMm(CORR.mm) + ' finora');
    if (fmtRientra(CORR)) parti.push(fmtRientra(CORR));
    var html = '<b>' + fmtGg(CORR.giorni) + '</b> \u00b7 ' + parti.join(' \u00b7 ');
    var prec = testoUltima('precedente: ');
    if (prec) html += '<br><span style="color:' + COL.ultima + '">' + prec + '</span>';
    nota.innerHTML = html;
}

// ---------------------------------------------------------------------------
// Build
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
// ISTOGRAMMA DELLE DURATE
// ---------------------------------------------------------------------------
/** Conteggi per classe di un anno: array 1..N classi (indice 0 inutilizzato). */
function contaDurate(anno, tipo) {
    var n = CLASSI_ISTO[tipo].length, c = [];
    for (var i = 0; i <= n; i++) c.push(0);
    sequenzeAnno(anno, tipo).forEach(function(s) { c[classeDi(tipo, s.n)]++; });
    return c;
}

/**
 * Media annua per durata sugli anni COMPLETI (escluso quello in corso):
 * e' il riferimento climatologico dell'istogramma.
 */
var MEDIA_ISTO = {};
function mediaDurate(tipo) {
    if (MEDIA_ISTO[tipo]) return MEDIA_ISTO[tipo];
    var anni = Object.keys(ANNI).filter(function(a) { return +a !== ANNO_CORR; });
    var m = [];
    for (var i = 0; i <= CLASSI_ISTO[tipo].length; i++) m.push(0);
    anni.forEach(function(a) {
        contaDurate(a, tipo).forEach(function(v, i) { m[i] += v; });
    });
    MEDIA_ISTO[tipo] = { v: m.map(function(v) { return anni.length ? v / anni.length : 0; }),
                         label: anni.length ? (anni[0] + '\u2013' + anni[anni.length - 1]) : '' };
    return MEDIA_ISTO[tipo];
}

/** Durata della sequenza in corso, se appartiene all'anno corrente e al tipo. */
function apertaIsto(tipo) {
    var s = sequenzeAnno(ANNO_CORR, tipo).filter(function(x) { return x.aperta; })[0];
    return s ? classeDi(tipo, s.n) : null;
}

/** Passo "tondo" delle tacche per un massimo dato: 1, 2, 5, 10, 20... */
function passoTondo(max, nTacche) {
    var grezzo = max / nTacche, p = Math.pow(10, Math.floor(Math.log10(grezzo)));
    var r = grezzo / p;
    return (r <= 1 ? 1 : r <= 2 ? 2 : r <= 5 ? 5 : 10) * p;
}

var histPlugin = {
    id: 'histPlugin',
    afterDatasetsDraw: function(chart) {
        var opt  = chart.options.plugins.histPlugin;
        var tipo = TIPI[opt.idx];
        var ctx  = chart.ctx, ca = chart.chartArea;
        var xs   = chart.scales.x, ys = chart.scales.y;
        var cur  = contaDurate(ANNO_CORR, tipo);
        var conf = ANNO_CONF ? contaDurate(ANNO_CONF, tipo) : null;
        var med  = mediaDurate(tipo).v;
        var max  = CLASSI_ISTO[tipo].length;   // numero di classi
        var pal  = PAL[tipo];

        // Larghezza delle barre in funzione dello spazio della classe: su
        // asse logaritmico le classi lunghe sono piu' strette.
        var w, off, r;
        function misure(k) {
            var passo = xs.getPixelForValue(k + 0.5) - xs.getPixelForValue(k - 0.5);
            w   = conf ? Math.max(1, Math.min((passo - 1) * 0.45, 12))
                       : Math.max(1.5, Math.min(passo * 0.6, 18));
            off = conf ? (w + 1) / 2 : 0;
            r   = Math.min(w / 2, 4);
            return passo;
        }
        var y0  = ys.getPixelForValue(0);

        function barra(x, v, colore) {
            if (!v) return null;
            var yt = ys.getPixelForValue(v);
            ctx.fillStyle = colore;
            ctx.beginPath();
            if (ctx.roundRect) ctx.roundRect(x - w / 2, yt, w, y0 - yt, [r, r, 0, 0]);
            else ctx.rect(x - w / 2, yt, w, y0 - yt);
            ctx.fill();
            return yt;
        }

        // ---- media annua: gradini sotto le barre, con velatura leggera ----
        ctx.save();
        ctx.beginPath();
        ctx.moveTo(xs.getPixelForValue(0.5), y0);
        for (var d = 1; d <= max; d++) {
            var ym = ys.getPixelForValue(med[d]);
            ctx.lineTo(xs.getPixelForValue(d - 0.5), ym);
            ctx.lineTo(xs.getPixelForValue(d + 0.5), ym);
        }
        ctx.lineTo(xs.getPixelForValue(max + 0.5), y0);
        ctx.fillStyle = 'rgba(128,128,128,0.10)';
        ctx.fill();
        ctx.strokeStyle = 'rgba(110,110,110,0.55)';
        ctx.lineWidth = 1.2;
        ctx.stroke();
        ctx.restore();

        // ---- barre ----
        var hit = chart.$hit = { classi: [] };
        var ap  = apertaIsto(tipo), puntaAperta = null;
        for (var k = 1; k <= max; k++) {
            var passoK = misure(k);
            var x = xs.getPixelForValue(k);
            var ytc = barra(x - off, cur[k], pal.barra);
            if (conf) barra(x + off, conf[k], COL.conf);
            if (ap === k && ytc !== null) puntaAperta = { x: x - off, y: ytc };
            hit.classi.push({ d: k, x: x, passo: passoK, cur: cur[k], conf: conf ? conf[k] : null, med: med[k] });
        }
        hit.tot = cur.reduce(function(a, b) { return a + b; }, 0);
        hit.totConf = conf ? conf.reduce(function(a, b) { return a + b; }, 0) : 0;

        // ---- sequenza in corso: pallino pulsante sulla sua classe ----
        var pulse = document.getElementById('pulse-' + opt.idx);
        if (puntaAperta) {
            pulse.style.left = puntaAperta.x + 'px';
            pulse.style.top  = puntaAperta.y + 'px';
            pulse.style.display = 'block';
        } else {
            pulse.style.display = 'none';
        }
    }
};

/** Crea il grafico istogramma di un pannello. */
function creaIsto(tipo, idx) {
    var max  = CLASSI_ISTO[tipo].length;   // numero di classi, poste a 1..max
    var cur  = contaDurate(ANNO_CORR, tipo);
    var conf = ANNO_CONF ? contaDurate(ANNO_CONF, tipo) : [];
    var med  = mediaDurate(tipo).v;
    var top  = Math.max.apply(null, cur.concat(conf, med, [1]));
    var step = passoTondo(top, MOBILE ? 4 : 5);
    var yMax = Math.ceil(top * 1.12 / step) * step;

    // Una tacca per classe: l'asse e' categorico, ogni classe occupa un posto
    var tx = [];
    for (var i = 1; i <= max; i++) tx.push(i);

    return new Chart(document.getElementById('chart-' + idx).getContext('2d'), {
        type: 'scatter',
        data: { datasets: [{ data: [], pointRadius: 0 }] },
        options: {
            animation: false,
            responsive: true,
            maintainAspectRatio: false,
            events: [],
            plugins: { legend: { display: false }, tooltip: { enabled: false },
                       histPlugin: { idx: idx } },
            layout: { padding: { left: 4, right: 10, top: 14, bottom: 4 } },
            scales: {
                x: {
                    type: 'linear', min: 0.5, max: max + 0.5,
                    afterBuildTicks: function(sc) { sc.ticks = tx.map(function(v) { return { value: v }; }); },
                    grid: { color: 'rgba(128,128,128,0.12)', drawTicks: false },
                    border: { color: 'rgba(128,128,128,0.28)' },
                    ticks: {
                        autoSkip: false, padding: 5,
                        font: { size: MOBILE ? 8 : 9, weight: 'bold' }, color: COL.testo,
                        callback: function(v) { return etichettaClasse(CLASSI_ISTO[tipo][v - 1]); }
                    },
                    title: { display: !MOBILE, text: 'durata (giorni)', color: COL.testo, font: { size: 9 } }
                },
                y: {
                    min: 0, max: yMax,
                    grid: { color: 'rgba(128,128,128,0.18)' },
                    border: { display: false },
                    ticks: { stepSize: step, precision: 0, font: { size: MOBILE ? 8 : 9 },
                             color: PAL[tipo].assi },
                    title: { display: !MOBILE, text: 'n. sequenze', color: PAL[tipo].assi, font: { size: 9 } }
                }
            }
        },
        plugins: [histPlugin]
    });
}

/** Tooltip dell'istogramma: la classe piu' vicina in orizzontale. */
function testoIsto(ch, mx, my) {
    var tipo = TIPI[ch.options.plugins.histPlugin.idx];
    var hit  = ch.$hit || {}, ca = ch.chartArea;
    if (my < ca.top - 6 || my > ca.bottom + 18) return null;
    // Classe sotto il puntatore: ognuna ha la sua ampiezza in pixel
    var best = null, bd = 1e9;
    (hit.classi || []).forEach(function(c) {
        var d = Math.abs(mx - c.x);
        if (d <= Math.max(c.passo / 2, 3) && d < bd) { bd = d; best = c; }
    });
    if (!best) return null;

    var c = CLASSI_ISTO[tipo][best.d - 1];
    var etichetta = c[1] === null ? c[0] + ' giorni o pi\u00f9'
                  : (c[0] === c[1] ? fmtGg(c[0]) : c[0] + '\u2013' + c[1] + ' giorni');
    var r = ['<b>Sequenze ' + (tipo === 'secca' ? 'secche' : 'piovose') + ' di ' + etichetta + '</b>'];
    function riga(anno, n, tot) {
        var t = anno + ': ' + n;
        if (tot) t += ' (' + Math.round(n / tot * 100) + '% delle sequenze dell\'anno)';
        return t;
    }
    r.push(riga(ANNO_CORR, best.cur, hit.tot));
    if (best.conf !== null) r.push(riga(ANNO_CONF, best.conf, hit.totConf));
    var m = mediaDurate(tipo);
    r.push('media ' + m.label + ': ' + best.med.toFixed(1).replace('.', ',') + ' per anno');
    if (apertaIsto(tipo) === best.d) r.push('include la sequenza in corso, ancora aperta');
    return r.join('<br>');
}

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

        var ch = VISTA === 'istogramma' ? creaIsto(tipo, idx) : new Chart(document.getElementById('chart-' + idx).getContext('2d'), {
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
                        min: Y_MIN, max: SCALA[tipo].max,
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
             + ' \u2022 ogni sequenza \u00e8 attribuita al giorno, al mese e all\'anno in cui inizia'
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

    if (VISTA === 'istogramma') {
        nota += ' \u2022 istogramma: l\'anno in corso conta le sequenze fino a oggi'
              + ' \u2022 media annua sugli anni completi'
              + ' \u2022 le classi delle secche hanno ampiezza diversa: le pi\u00f9 larghe raccolgono pi\u00f9 sequenze anche per questo';
    }
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
    if (VISTA === 'istogramma') return testoIsto(ch, mx, my);
    var idx  = ch.options.plugins.seqPlugin.idx;
    var tipo = TIPI[idx];
    var hit  = ch.$hit || {};

    // 1) pallino della sequenza in corso
    if (hit.corr && Math.hypot(mx - hit.corr.x, my - hit.corr.y) <= 14) {
        var r = ['<b>Sequenza in corso: ' + fmtGg(CORR.giorni) + '</b>'];
        var d = CORR.data_inizio.split('-');
        r.push('iniziata il ' + d[2] + '/' + d[1] + '/' + d[0]);
        if (suffMm(CORR)) r.push('pioggia complessiva finora: ' + fmtMm(CORR.mm));
        if (fmtRientra(CORR)) r.push(fmtRientra(CORR));
        return r.join('<br>');
    }

    // 2) pallino dell'ultima sequenza conclusa
    var ult = ULTIME[tipo];
    if (ult && hit.ultima && Math.hypot(mx - hit.ultima.x, my - hit.ultima.y) <= 14) {
        var ru = ['<b>Ultima ' + (tipo === 'secca' ? 'secca' : 'piovosa') + ' conclusa: ' + fmtGg(ult.giorni) + '</b>'];
        ru.push('dal ' + fmtData(ult.data_inizio) + ' al ' + fmtData(ult.data_fine));
        if (suffMm(ult)) ru.push('pioggia complessiva: ' + fmtMm(ult.mm));
        if (fmtRientra(ult)) ru.push(fmtRientra(ult));
        if (CORR && CORR.tipo === tipo) ru.push('la sequenza in corso \u00e8 quella in rosso');
        return ru.join('<br>');
    }

    // 3) barra di una sequenza: la piu' vicina in orizzontale, se il puntatore
    //    sta lungo la sua altezza
    var bb = null, bd = MOBILE ? 6 : 4;
    (hit.barre || []).forEach(function(b) {
        var d = Math.abs(mx - b.x);
        if (d <= bd && my >= b.yTop - 8 && my <= ch.chartArea.bottom + 2) { bd = d; bb = b; }
    });
    if (bb) return testoBarra(bb.s, tipo);

    // 4) mese piu' vicino in orizzontale
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

/** Tooltip di una barra: durata, date, posizione nella climatologia del mese. */
function testoBarra(s, tipo) {
    var r = ['<b>' + fmtGg(s.n) + (s.aperta ? ' (in corso)' : '') + '</b>'];
    r.push(s.anno + ' \u00b7 dal ' + inizioSeq(s) + (s.aperta ? ' a oggi' : ' al ' + fineSeq(s)));
    if (tipo === 'piovosa' && s.mm != null) {
        r.push('pioggia complessiva' + (s.aperta ? ' finora' : '') + ': ' + fmtMm(s.mm));
    }
    var m = MESI[tipo][s.m - 1];
    if (m && m.p50 != null) {
        var nom = MESI_LUNGHI[s.m - 1], pos;
        if (s.n > m.p90)      pos = 'oltre il p90 di ' + nom;
        else if (s.n > m.p75) pos = 'fra p75 e p90 di ' + nom;
        else if (s.n > m.p50) pos = 'sopra la mediana di ' + nom;
        else                  pos = 'entro la mediana di ' + nom;
        if (m.max != null && s.n >= m.max) pos = 'record di ' + nom;
        r.push(pos);
    }
    return r.join('<br>');
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

// ---------------------------------------------------------------------------
// Selettore dell'anno di confronto
// ---------------------------------------------------------------------------
// Non serve ricostruire i grafici: le barre le disegna seqPlugin leggendo
// ANNO_CONF, basta ridisegnare. La scala non cambia, perche' il massimo
// dell'asse copre gia' il massimo storico di ogni mese.
var btnNessuno = document.getElementById('btn-nessuno');
var btnConf    = document.getElementById('btn-conf');
var stepper    = document.getElementById('stepper');
var posConf    = 0;   // indice in ANNI_CONF: 0 = anno piu' recente

function impostaConfronto(attivo) {
    ANNO_CONF = attivo ? String(ANNI_CONF[posConf]) : '';
    btnNessuno.classList.toggle('on', !attivo);
    btnConf.classList.toggle('on', attivo);
    btnNessuno.setAttribute('aria-pressed', String(!attivo));
    btnConf.setAttribute('aria-pressed', String(attivo));
    stepper.classList.toggle('vis', attivo);
    document.getElementById('anno-lab').textContent = attivo ? ANNO_CONF : '';
    // Freccia sinistra = indietro nel tempo, destra = verso l'anno corrente
    document.getElementById('anno-prec').disabled = posConf >= ANNI_CONF.length - 1;
    document.getElementById('anno-succ').disabled = posConf <= 0;
    nascondiTooltip();
    if (VISTA === 'istogramma') build();   // la scala verticale dipende dai conteggi
    else charts.forEach(function(ch, idx) { ch.draw(); riempiLegenda(idx); });
    sendResize();
}

if (btnConf && ANNI_CONF.length) {
    btnNessuno.addEventListener('click', function() { impostaConfronto(false); });
    // Ogni nuova attivazione riparte dall'anno precedente
    btnConf.addEventListener('click', function() {
        if (!ANNO_CONF) posConf = 0;
        impostaConfronto(true);
    });
    document.getElementById('anno-prec').addEventListener('click', function() {
        if (posConf < ANNI_CONF.length - 1) { posConf++; impostaConfronto(true); }
    });
    document.getElementById('anno-succ').addEventListener('click', function() {
        if (posConf > 0) { posConf--; impostaConfronto(true); }
    });
}

// ---------------------------------------------------------------------------
// Scelta della vista: per mese / istogramma
// ---------------------------------------------------------------------------
function impostaVista(v) {
    VISTA = v;
    var bm = document.getElementById('btn-mese'), bi = document.getElementById('btn-isto');
    bm.classList.toggle('on', v === 'mese');
    bi.classList.toggle('on', v === 'istogramma');
    bm.setAttribute('aria-pressed', String(v === 'mese'));
    bi.setAttribute('aria-pressed', String(v === 'istogramma'));
    nascondiTooltip();
    build();
}
document.getElementById('btn-mese').addEventListener('click', function() { if (VISTA !== 'mese') impostaVista('mese'); });
document.getElementById('btn-isto').addEventListener('click', function() { if (VISTA !== 'istogramma') impostaVista('istogramma'); });

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