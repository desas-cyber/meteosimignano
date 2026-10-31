<?php
/**
 * STATISTICHE TERMICHE - termo_stat_display.php
 * Pagina statistiche temperatura: header + blocchi modulari impilabili.
 * Copia di pluvio_stat_display.php (stessa struttura, SENZA immagine
 * principale): cambiano solo titolo, blocchi e id degli iframe.
 *
 * BLOCCHI (tutti serviti da grafico_termo_display.php?sezione=...):
 *   1. situazione  -> stat-iframe-termo1  indice STI oggi e mese chiuso
 *   2. andamento   -> stat-iframe-termo2  serie STI / percentili
 *   3. anni        -> stat-iframe-termo3  confronto Theil-Sen e classifiche
 *   4. clima       -> stat-iframe-termo4  clima mese per mese
 */
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.98, maximum-scale=5.0, user-scalable=yes">

    <!-- ANTI-CACHE per Safari mobile -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <title>MeteoSimignano - Statistiche temperatura</title>

    <!-- galleria-lightbox.css e' mantenuto per coerenza con le altre pagine -->
    <link rel="stylesheet" href="galleria-lightbox.css">
    <link rel="stylesheet" href="header_shared.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0;
            padding: 0;
        }

        /* Senza immagine principale il primo blocco resterebbe attaccato
           all'header: gli si da' un margine iniziale. */
        main { width: 100%; display: flex; flex-direction: column; align-items: center; }
        .stat-section-header:first-of-type { margin-top: 14px; }

        /* ---- BLOCCHI MODULARI ----
           Per aggiungere un blocco: copia header + block,
           cambia id/src/titolo, aggiungi id allo spinner JS. */

        .stat-section-header {
            width: 90%;
            max-width: 1000px;
            margin: 20px auto 6px auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            box-sizing: border-box;
        }

        .stat-section-header.centrato {
            justify-content: center;
        }
        .stat-section-header.centrato .stat-section-title {
            text-align: center;
        }

        .stat-section-title {
            margin: 0;
            font-weight: bold;
            color: black;
            text-align: left;
            font-size: clamp(14px, 3.6vw, 28px);
        }

        .stat-section-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background-color: transparent;
            color: black;
            border: 2px solid black;
            font-weight: bold;
            font-size: clamp(10px, 2.4vw, 16px);
            line-height: 1.2;
            cursor: pointer;
            padding: 4px 10px;
            box-sizing: border-box;
            min-height: 28px;
            white-space: nowrap;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .stat-section-cta:hover {
            border-color: red;
            color: red;
        }

        .stat-table-block {
            width: 100%;
            max-width: 1000px;
            display: flex;
            justify-content: center;
            margin: 0 0 10px 0;
            position: relative;
        }

        .stat-table-block iframe {
            width: 100%;
            height: 300px;  /* default: uguale a stat_display.php */
            border: none;
        }

        .stat-table-placeholder {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
            font-size: 14px;
            color: #999;
            background: #fff;
            z-index: 1;
            pointer-events: none;
        }

        
        /* ---- SPINNER ---- */
        .sub-title-row { display: inline-flex; align-items: center; gap: 10px; }

        .spinner {
            width: 14px; height: 14px;
            border: 2px solid rgba(0,0,0,0.25);
            border-top-color: #333;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .spinner.hidden { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ---- LANDSCAPE PHONE ---- */
        @media (orientation: landscape) and (max-height: 480px) {
            .stat-section-title { font-size: 20px !important; }
        }
    </style>
</head>

<body>

    <!-- HEADER -->
    <header class="main-header">

        <a href="termo_stat_info.html" class="header-icon left-icon" title="">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <span class="icon-label">Info</span>
        </a>

        <div class="header-content">
            <h1 class="main-title">
                <a href="index.php" style="text-decoration: none; color: inherit;">MeteoSimignano</a>
            </h1>
            <h1 class="sub-title sub-title-row">
                <span>43&#176;17&#8242;32.5&#8243;N 11&#176;10&#8242;01.49&#8243;E @ 418m slm</span>
                <span id="page-spinner" class="spinner" aria-label="Caricamento in corso"></span>
            </h1>
        </div>

        <?php include __DIR__ . '/menu_destro.php'; ?>
    </header>

    <main>

        <!-- ================================================================
             BLOCCHI STATISTICHE TERMICHE
             ================================================================
             COME AGGIUNGERE UN NUOVO BLOCCO:

             1. Copia questo blocco completo:

                <div class="stat-section-header centrato">
                    <h2 class="stat-section-title">TITOLO</h2>
                </div>
                <div class="stat-table-block">
                    <div id="placeholder-termoN" class="stat-table-placeholder">
                        Caricamento dati...
                    </div>
                    <iframe src="tuo_file.php"
                            id="stat-iframe-termoN"
                            width="100%"
                            frameborder="0"></iframe>
                </div>

             2. Sostituisci N con il numero progressivo (5, 6, ...)
             3. Aggiungi 'stat-iframe-termoN' all'array blocchi_ids nello
                script dello spinner in fondo alla pagina
             4. L'iframe deve inviare postMessage {action:'resize', iframeId,
                height} con lo STESSO id, altrimenti resta alto 300px
                (per grafico_termo_display.php l'id e' in $SEZIONI)
             ================================================================ -->

        <!-- BLOCCO 1: situazione, indice STI oggi e ultimo mese chiuso -->
        <div class="stat-section-header centrato">
            <h2 class="stat-section-title">Temperatura: indice STI</h2>
        </div>
        <div class="stat-table-block">
            <div id="placeholder-termo1" class="stat-table-placeholder">Caricamento dati...</div>
            <iframe src="grafico_termo_display.php?sezione=situazione"
                    id="stat-iframe-termo1"
                    width="100%"
                    frameborder="0"
                    title="Temperatura: indice STI"></iframe>
        </div>

        <!-- BLOCCO 2: andamento, serie STI o percentili -->
        <div class="stat-section-header centrato">
            <h2 class="stat-section-title">Andamento dell'indice</h2>
        </div>
        <div class="stat-table-block">
            <div id="placeholder-termo2" class="stat-table-placeholder">Caricamento dati...</div>
            <iframe src="grafico_termo_display.php?sezione=andamento"
                    id="stat-iframe-termo2"
                    width="100%"
                    frameborder="0"
                    title="Andamento dell'indice"></iframe>
        </div>

        <!-- BLOCCO 3: confronto Theil-Sen (anno, stagioni, mesi) e classifiche -->
        <div class="stat-section-header centrato">
            <h2 class="stat-section-title">Confronto Theil&ndash;Sen</h2>
        </div>
        <div class="stat-table-block">
            <div id="placeholder-termo3" class="stat-table-placeholder">Caricamento dati...</div>
            <iframe src="grafico_termo_display.php?sezione=anni"
                    id="stat-iframe-termo3"
                    width="100%"
                    frameborder="0"
                    title="Confronto Theil-Sen"></iframe>
        </div>

        <!-- BLOCCO 4: clima mese per mese -->
        <div class="stat-section-header centrato">
            <h2 class="stat-section-title">Clima mese per mese</h2>
        </div>
        <div class="stat-table-block">
            <div id="placeholder-termo4" class="stat-table-placeholder">Caricamento dati...</div>
            <iframe src="grafico_termo_display.php?sezione=clima"
                    id="stat-iframe-termo4"
                    width="100%"
                    frameborder="0"
                    title="Clima mese per mese"></iframe>
        </div>

    </main>


    <!-- JAVASCRIPT -->

    <!-- Ricarica periodica degli iframe con cache-busting.
         Utile qui: la stima di oggi cambia ogni giorno. -->
    <script src="aggiorna_dati_meteo.js?v=<?php echo filemtime(__DIR__ . '/aggiorna_dati_meteo.js'); ?>"></script>

    <script>
    // ---- postMessage: gestisce resize e swap grafico/tabella ----
    // Mappa degli src originali degli iframe (per poter tornare indietro)
    var _iframeSrcOriginali = {};
    document.querySelectorAll('.stat-table-block iframe').forEach(function(fr) {
        if (fr.id) _iframeSrcOriginali[fr.id] = fr.src;
    });

    window.addEventListener('message', function(e) {
        var d = e.data;
        if (!d || !d.action) return;
        var fr = d.iframeId ? document.getElementById(d.iframeId) : null;

        if (d.action === 'resize' && fr && d.height) {
            // Aggiunge 16px di margine per evitare scrollbar
            fr.style.height = (parseInt(d.height) + 16) + 'px';
        }

        if (d.action === 'mostraGrafico' && fr && d.src) {
            if (!_iframeSrcOriginali[fr.id]) _iframeSrcOriginali[fr.id] = fr.src;
            fr.src = d.src;
        }

        if (d.action === 'tornaTabella' && fr) {
            var srcOriginale = _iframeSrcOriginali[fr.id];
            if (srcOriginale) fr.src = srcOriginale;
            var btnReset = document.querySelector('[data-iframe="' + fr.id + '"]');
            if (btnReset) {
                btnReset.innerHTML = '&#128200;&nbsp;Grafico';
                btnReset.setAttribute('data-mode', 'tabella');
            }
        }
    });
    </script>

   

    <!-- SPINNER
         Attende i soli iframe (qui non c'e' immagine principale).
         Per aggiungere un blocco: aggiungi id a blocchi_ids[] -->
    <script>
    (function () {
        'use strict';

        var sp = document.getElementById('page-spinner');
        if (!sp) return;

        var blocchi_ids = [
            'stat-iframe-termo1',
            'stat-iframe-termo2',
            'stat-iframe-termo3',
            'stat-iframe-termo4'
            /* Aggiungi: 'stat-iframe-termo5', ... */
        ];

        var state = {};
        blocchi_ids.forEach(function (id) { state[id] = false; });

        function show() { sp.classList.remove('hidden'); }
        function hide() { sp.classList.add('hidden'); }

        function markDone(key) {
            if (state[key] === true) return;
            state[key] = true;
            var allDone = Object.keys(state).every(function (k) { return state[k]; });
            if (allDone) hide();
        }

        function init() {
            show();

            blocchi_ids.forEach(function (id) {
                (function (iframeId) {
                    var fr = document.getElementById(iframeId);
                    var phId = iframeId.replace('stat-iframe-', 'placeholder-');
                    var ph = document.getElementById(phId);

                    if (!fr) { markDone(iframeId); return; }

                    function iframePronto() {
                        markDone(iframeId);
                        if (ph) ph.style.display = 'none';
                    }

                    fr.addEventListener('load', iframePronto, { once: true });
                    setTimeout(iframePronto, 10000);
                })(id);
            });
        }

        window.addEventListener('pageshow', function () {
            var allDone = Object.keys(state).every(function (k) { return state[k]; });
            if (allDone) hide();
        });

        init();
    })();
    </script>

</body>
</html>