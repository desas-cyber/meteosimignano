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
$def_s1 = max(20, min(40, $def_s1));
$def_s2 = max(25, min(45, $def_s2));
$def_s3 = max(-2, min(15, $def_s3));
$def_s4 = max(-5, min(10, $def_s4));

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
        .controls-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 16px;
        }
        .ctrl-row {
            display: flex;
            align-items: center;
            gap: 6px;
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
            min-width: 24px;
        }
        .ctrl-row input[type=range] {
            flex: 1;
            min-width: 0;
            height: 20px;
            cursor: pointer;
            /* area touch generosa per mobile */
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
        .seg {
            position: absolute;
            top: 0;
            height: 100%;
        }

        /* ---- asse mesi ---- */
        .axis {
            display: flex;
            justify-content: space-between;
            max-width: 95%;
            margin: 4px auto 10px auto;
        }
        .axis span { font-size: 10px; color: #aaa; }

        /* ---- card statistiche ---- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
            max-width: 95%;
            margin: 0 auto 8px auto;
        }
        .stat-card {
            background: #f7f7f7;
            border-radius: 4px;
            padding: 7px 8px;
            text-align: center;
        }
        .stat-card .val {
            font-size: 18px;
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
    <span style="font-size:10px;color:#aaa;" id="lbl-oggi">
        aggiornato al <?= htmlspecialchars($oggi) ?>
    </span>
</div>

<!-- SLIDER CONTROLLI -->
<div class="controls-wrap">
    <div class="controls-grid">
        <div class="ctrl-row">
            <span class="ctrl-dot" style="background:#F09595;"></span>
            <span class="ctrl-label">&#8805;&nbsp;<span id="v1"><?= $def_s1 ?></span>&#176;C</span>
            <input type="range" id="s1" min="20" max="40" step="1" value="<?= $def_s1 ?>">
            <span class="ctrl-val" style="color:#E24B4A;"><?= $def_s1 ?>&#176;</span>
        </div>
        <div class="ctrl-row">
            <span class="ctrl-dot" style="background:#85B7EB;"></span>
            <span class="ctrl-label">&#8804;&nbsp;<span id="v3"><?= $def_s3 ?></span>&#176;C</span>
            <input type="range" id="s3" min="-2" max="15" step="1" value="<?= $def_s3 ?>">
            <span class="ctrl-val" style="color:#378ADD;"><?= $def_s3 ?>&#176;</span>
        </div>
        <div class="ctrl-row">
            <span class="ctrl-dot" style="background:#A32D2D;"></span>
            <span class="ctrl-label">&#8805;&nbsp;<span id="v2"><?= $def_s2 ?></span>&#176;C</span>
            <input type="range" id="s2" min="25" max="45" step="1" value="<?= $def_s2 ?>">
            <span class="ctrl-val" style="color:#A32D2D;"><?= $def_s2 ?>&#176;</span>
        </div>
        <div class="ctrl-row">
            <span class="ctrl-dot" style="background:#0C447C;"></span>
            <span class="ctrl-label">&#8804;&nbsp;<span id="v4"><?= $def_s4 ?></span>&#176;C</span>
            <input type="range" id="s4" min="-5" max="10" step="1" value="<?= $def_s4 ?>">
            <span class="ctrl-val" style="color:#0C447C;"><?= $def_s4 ?>&#176;</span>
        </div>
    </div>
</div>

<!-- TIMELINE -->
<div class="chart-wrap" id="chart-wrap"></div>

<!-- ASSE MESI -->
<div class="axis">
    <span>Gen</span><span>Feb</span><span>Mar</span><span>Apr</span><span>Mag</span><span>Giu</span>
    <span>Lug</span><span>Ago</span><span>Set</span><span>Ott</span><span>Nov</span><span>Dic</span>
</div>

<!-- STATISTICHE -->
<div class="stats-grid" id="stats-grid"></div>

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
function coloreGiorno(mx, mn, s1, s2, s3, s4) {
    if (mx >= s2) return '#A32D2D';
    if (mx >= s1) return '#F09595';
    if (mn <= s4) return '#0C447C';
    if (mn <= s3) return '#85B7EB';
    return null; // nella norma: trasparente
}

function render() {
    var s1 = parseInt(document.getElementById('s1').value);
    var s2 = parseInt(document.getElementById('s2').value);
    var s3 = parseInt(document.getElementById('s3').value);
    var s4 = parseInt(document.getElementById('s4').value);

    // Blocco gerarchia soglie:
    // s1 (caldo chiaro) non puo' raggiungere o superare s2 (caldo scuro)
    // s3 (freddo chiaro) non puo' raggiungere o scendere sotto s4 (freddo scuro)
    var conflitto_caldo  = (s1 >= s2);
    var conflitto_freddo = (s3 <= s4);

    var sliderS1 = document.getElementById('s1');
    var sliderS3 = document.getElementById('s3');

    // Aspetto grigio se in conflitto — slider resta attivo per tornare indietro
    sliderS1.style.opacity = conflitto_caldo ? '0.35' : '1';
    sliderS1.parentNode.querySelector('.ctrl-dot').style.background = conflitto_caldo ? '#bbb' : '#F09595';
    sliderS1.nextElementSibling.style.color = conflitto_caldo ? '#bbb' : '#E24B4A';

    sliderS3.style.opacity = conflitto_freddo ? '0.35' : '1';
    sliderS3.parentNode.querySelector('.ctrl-dot').style.background = conflitto_freddo ? '#bbb' : '#85B7EB';
    sliderS3.nextElementSibling.style.color = conflitto_freddo ? '#bbb' : '#378ADD';

    // Se bloccato, usa il valore di s2-1 / s4+1 per il calcolo colori
    if (conflitto_caldo)  s1 = s2 - 1;
    if (conflitto_freddo) s3 = s4 + 1;

    // Aggiorna etichette slider
    var ids = ['v1','v2','v3','v4'];
    var vals = [s1, s2, s3, s4];
    for (var i = 0; i < 4; i++) {
        var el = document.getElementById(ids[i]);
        if (el) el.textContent = vals[i];
        var row = document.getElementById('s' + (i+1));
        if (row && row.nextElementSibling) {
            row.nextElementSibling.textContent = vals[i] + '\u00b0';
        }
    }

    var wrap = document.getElementById('chart-wrap');
    wrap.innerHTML = '';

    var tot1 = 0, tot2 = 0, tot3 = 0, tot4 = 0;

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

        // Costruisce segmenti contigui dello stesso colore
        // (evita di creare un div per ogni giorno = 365 div per anno)
        var segs = [];
        var corrColore = coloreGiorno(giorni[0].mx, giorni[0].mn, s1, s2, s3, s4);
        var corrStart  = giorni[0].d;

        for (var i = 1; i <= giorni.length; i++) {
            var c = (i < giorni.length)
                ? coloreGiorno(giorni[i].mx, giorni[i].mn, s1, s2, s3, s4)
                : '__end__';
            if (c !== corrColore) {
                segs.push({ colore: corrColore, da: corrStart, a: (i < giorni.length ? giorni[i].d : null) });
                corrColore = c;
                corrStart  = (i < giorni.length ? giorni[i].d : null);
            }
        }

        segs.forEach(function(s) {
            if (!s.colore) return; // giorni nella norma: non disegniamo nulla (sfondo grigio chiaro)
            var el = document.createElement('div');
            el.className = 'seg';
            el.style.left = dataToPct(s.da);
            // Larghezza: dalla data iniziale alla finale (o fine barra)
            if (s.a) {
                var pDa = parseFloat(dataToPct(s.da));
                var pA  = parseFloat(dataToPct(s.a));
                el.style.width = (pA - pDa).toFixed(3) + '%';
            } else {
                el.style.width = (100 - parseFloat(dataToPct(s.da))).toFixed(3) + '%';
            }
            el.style.background = s.colore;
            bar.appendChild(el);
        });

        block.appendChild(bar);
        wrap.appendChild(block);

        // Conteggi per statistiche
        giorni.forEach(function(g) {
            if (g.mx >= s2) tot2++;
            else if (g.mx >= s1) tot1++;
            if (g.mn <= s4) tot4++;
            else if (g.mn <= s3) tot3++;
        });
    });

    // Card statistiche — media per anno
    var n = ANNI.length;
    var statsEl = document.getElementById('stats-grid');
    var datiStat = [
        { val: Math.round(tot1/n), lbl: '\u2265 ' + s1 + '\u00b0C\ncaldo',        colore: '#F09595' },
        { val: Math.round(tot2/n), lbl: '\u2265 ' + s2 + '\u00b0C\nmolto caldo',  colore: '#A32D2D' },
        { val: Math.round(tot3/n), lbl: '\u2264 ' + s3 + '\u00b0C\nfreddo',       colore: '#85B7EB' },
        { val: Math.round(tot4/n), lbl: '\u2264 ' + s4 + '\u00b0C\nmolto freddo', colore: '#0C447C' },
    ];
    statsEl.innerHTML = '';
    datiStat.forEach(function(s) {
        var lines = s.lbl.split('\n');
        statsEl.innerHTML += '<div class="stat-card">'
            + '<div class="val" style="color:' + s.colore + ';">' + s.val + '</div>'
            + '<div class="lbl">' + lines[0] + '<br>' + lines[1] + '<br>gg/anno (media)</div>'
            + '</div>';
    });

    // Nota footer
    document.getElementById('footer-note').textContent =
        ANNI.length + ' anni &bull; dati: temp max / temp min giornaliera';

    // Comunica altezza al padre per resize iframe
    sendResize();
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

// Collega tutti gli slider a render() con clamp per rispettare la gerarchia
// s1 non puo' raggiungere s2: se ci prova viene riportato a s2-1
// s3 non puo' raggiungere s4: se ci prova viene riportato a s4+1
document.getElementById('s1').addEventListener('input', function() {
    var s2 = parseInt(document.getElementById('s2').value);
    if (parseInt(this.value) >= s2) this.value = s2 - 1;
    render();
});
document.getElementById('s2').addEventListener('input', function() {
    render();
});
document.getElementById('s3').addEventListener('input', function() {
    var s4 = parseInt(document.getElementById('s4').value);
    if (parseInt(this.value) <= s4) this.value = s4 + 1;
    render();
});
document.getElementById('s4').addEventListener('input', function() {
    render();
});

// Render iniziale
render();

// Secondo resize dopo che il DOM si e' stabilizzato
// (necessario su mobile dove il layout si aggiusta dopo il paint)
setTimeout(sendResize, 150);
setTimeout(sendResize, 500);
</script>

</body>
</html>