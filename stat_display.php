<?php
/**
 * STATISTICHE METEO SIMIGNANO - stat_display.php
 * Pagina statistiche: header + immagine principale + tabelle modulari impilabili
 * Struttura analoga a index.php, senza galleria ne tabella_home_display
 */
require_once __DIR__ . '/camera_config.php';

// Protezione: $mainImageDate deve essere definita da camera_config.php
// Se per qualsiasi motivo non fosse disponibile, usiamo stringa vuota.
if (!isset($mainImageDate)) {
    $mainImageDate = '';
}
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
    <title>MeteoSimignano - Statistiche</title>

    <!-- FONDAMENTALE: contiene .temp-red/.temp-orange/.temp-green/ecc.
         e .is-min/.is-max usate da aggiorna_galleria.js -->
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

        /* ---- IMMAGINE PRINCIPALE ---- */
        .main-container {
            position: relative;
            display: inline-block;
            width: 100%;
            max-width: 1000px;
            box-sizing: border-box;
            overflow: hidden;
        }

        .main-image {
            width: calc(100% + 6px);
            max-width: 1000px;
            display: block;
            cursor: pointer;
            height: auto;
        }

        .main-container > .date-text {
            position: relative;
            bottom: 0px;
            left: 0;
            right: 0;
            width: 100%;
            margin: 0;
            text-align: center;
            font-size: clamp(10px, 3vw, 25px);
            line-height: 1.2;
            z-index: 2;
            color: white;
            padding: 4px 8px;
            box-sizing: border-box;
            overflow: visible;
        }

        .main-container > .date-text::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: -0.8em;
            height: 2.2em;
            background: rgba(8, 8, 8, 1);
            z-index: -1;
        }

        @media (max-width: 599px) {
            .main-container > .date-text::after {
                height: 30px;
                bottom: -10px;
                left: -10px;
                width: calc(100% + 20px);
                right: auto;
                margin: 0;
            }
        }

        #main-image-date {
            position: absolute;
            bottom: 0.3rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }

        #temp-label { margin-left: 0.5rem; }

        /* ---- TABELLE MODULARI ----
           Per aggiungere una tabella: copia header + block,
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
            height: 300px;  /* default: uguale a tabella_home in index.php */
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

        /* ---- HEADER MENU ---- */
        .header-menu-container { position: relative; }

        .menu-toggle { background: none; border: none; cursor: pointer; padding: 0; }

        .submenu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 10px;
            background: white;
            border: 2px solid #333;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: max-content;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            z-index: 1000;
        }

        .submenu.active { opacity: 1; visibility: visible; transform: translateY(0); }

        .submenu-item {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.2s ease;
            border-bottom: 1px solid #f0f0f0;
        }

        .submenu-item:last-child  { border-bottom: none; }
        .submenu-item:hover       { background-color: #f8f8f8; }
        .submenu-item:first-child { border-radius: 8px 8px 0 0; }
        .submenu-item:last-child  { border-radius: 0 0 8px 8px; }

/*=================================
* VOCE CON SOTTO MENU ANNIDIATO *
=================================*/
.submenu-item.has-sub {
    position: relative;
    cursor: pointer;
    padding: 12px 20px;
    border-bottom: 1px solid #f0f0f0;
    user-select: none;
}
.submenu-item.has-sub:hover,
.submenu-item.has-sub.sub-active {
    background-color: #f8f8f8;
}
.sub-submenu {
    position: absolute;
    top: 0;
    left: 100%;
    margin-left: 4px;
    background: white;
    border: 2px solid #333;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: max-content;
    opacity: 0;
    visibility: hidden;
    transform: translateX(-6px);
    transition: all 0.2s ease;
    z-index: 1100;
}
.submenu-item.has-sub.sub-active .sub-submenu {
    opacity: 1;
    visibility: visible;
    transform: translateX(0);
}
@media (max-width: 768px) {
    .sub-submenu {
        left: auto;
        right: 0;
        top: 100%;
        margin-left: 0;
        margin-top: 2px;
        transform: translateY(-6px);
    }
    .submenu-item.has-sub.sub-active .sub-submenu {
        transform: translateY(0);
    }
}
.submenu-item.has-sub.sub-active .sub-submenu {
    opacity: 1;
    visibility: visible;
    transform: translateX(0);
}
.sub-submenu .submenu-item {
    padding: 6px 16px;
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
            .main-container { position: relative !important; overflow: hidden !important; }

            .main-image {
                width: 90% !important; max-width: 90% !important;
                display: block !important; margin: 0 auto !important; height: auto !important;
            }

            #main-image-date {
                position: absolute !important; left: 50% !important; bottom: 0.3rem !important;
                transform: translateX(-50%) scale(0.9) !important;
                transform-origin: bottom center !important; z-index: 5 !important;
                width: auto !important; max-width: calc(90% - 12px) !important;
                white-space: nowrap !important; overflow: hidden !important;
                text-overflow: ellipsis !important; pointer-events: none;
                font-size: 0.9em !important;
            }

            .main-container > .date-text::after { content: none !important; }
            .stat-section-title { font-size: 20px !important; }
        }
        #stat-iframe-tab2 { height: 465px !important; }
    </style>
</head>

<body>

    <!-- HEADER -->
    <header class="main-header">

        <a href="lavori_in_corso.html" class="header-icon left-icon" title="">
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

        <div class="header-menu-container">
            <button class="header-icon right-icon menu-toggle" title="Menu">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
                <span class="icon-label">Menu</span>
            </button>

            <div class="submenu">
                <a href="index.php" class="submenu-item">Home</a>
                <a href="belle.php" class="submenu-item">Diario del cielo</a>
                <a href='grafici_termo_plotly.php?range=24h&visible=' class="submenu-item">Grafici</a>
                <a href="pluvio.html" class="submenu-item">Pioggia: 24h</a>
                <a href="pluvio_tab.php" class="submenu-item">Pioggia: tabella</a>
                <div class="submenu-item has-sub">
                    <span class="submenu-item-label">Statistiche &#9658;</span>
                    <div class="sub-submenu">
                        <a href="stat_display.php" class="submenu-item" >Tabelle</a>
                        <a href="lavori_in_corso.html" class="submenu-item" >Grafici</a>
                    </div>
                </div>
                <a href="#" class="submenu-item" onclick="window.open('classifica_immagini.php?token=', '_blank'); return false;">Admin</a>
            </div>
        </div>
    </header>

    <main>

        <!-- IMMAGINE PRINCIPALE - src e temp popolati da aggiorna_galleria.js -->
        <div class="main-container">
            <img id="main-image" src="" alt="Caricamento immagine..." class="main-image">

            <h2 class="date-text" id="main-image-date">
                <span id="date-label">
                    Ultima immagine (dir. NO): <?php echo htmlspecialchars($mainImageDate); ?>
                </span>
                <span id="temp-label" class="temp-data temp-default">--&deg;C</span>
            </h2>
        </div>


        <!-- ================================================================
             BLOCCHI TABELLE STATISTICHE
             ================================================================
             COME AGGIUNGERE UNA NUOVA TABELLA:

             1. Copia questo blocco completo:

                <div class="stat-section-header">
                    <h2 class="stat-section-title">TITOLO</h2>
                    <a href="..." class="stat-section-cta">Link</a>  <- opzionale
                </div>
                <div class="stat-table-block">
                    <div id="placeholder-tabN" class="stat-table-placeholder">
                        Caricamento dati...
                    </div>
                    <iframe src="tuo_file.php"
                            id="stat-iframe-tabN"
                            height="300px"
                            frameborder="0"></iframe>
                </div>

             2. Sostituisci N con il numero progressivo (4, 5, ...)
             3. Aggiungi 'stat-iframe-tabN' all'array tabelle_ids nello script
                dello spinner in fondo alla pagina
             4. Regola height se la tabella e' piu' alta/bassa di 300px
             ================================================================ -->

        <!-- TABELLA 1 -->
        <div class="stat-section-header">
            <h2 class="stat-section-title">Statistiche mensili</h2>
            <a href="#" class="stat-section-cta" id="btn-grafico-stat1" data-iframe="stat-iframe-tab1" data-src="grafico_stat1_display.php" data-mode="tabella">&#128200;&nbsp;Grafico</a>
        </div>
        <div class="stat-table-block">
            <div id="placeholder-tab1" class="stat-table-placeholder">Caricamento dati...</div>
            <iframe src="tabella_stat_display.php"
                    id="stat-iframe-tab1"
                    width="100%"
                    frameborder="0"
                    title="Statistiche mensili"></iframe>
        </div>

        <!-- TABELLA 2: Soglie termiche e conteggi -->
        <div class="stat-section-header">
            <h2 class="stat-section-title">Soglie termiche</h2>
        </div>
        <div class="stat-table-block">
            <div id="placeholder-tab2" class="stat-table-placeholder">Caricamento dati...</div>
            <iframe src="tabella_stat2_display.php"
                    id="stat-iframe-tab2"
                    width="100%"
                    frameborder="0"
                    title="Soglie termiche"></iframe>
        </div>

        <!-- TABELLA 3: Record pioggia per durata -->
        <div class="stat-section-header">
            <h2 class="stat-section-title">Record pioggia</h2>
            <a href="#" class="stat-section-cta" id="btn-grafico-stat3" data-iframe="stat-iframe-tab3" data-src="grafico_stat3_display.php" data-mode="tabella">&#128200;&nbsp;Grafico</a>
        </div>
        <div class="stat-table-block">
            <div id="placeholder-tab3" class="stat-table-placeholder">Caricamento dati...</div>
            <iframe src="tabella_stat3_display.php"
                    id="stat-iframe-tab3"
                    width="100%"
                    frameborder="0"
                    title="Record pioggia"></iframe>
        </div>

    </main>


    <!-- JAVASCRIPT -->

    <!-- Array globali richiesti da aggiorna_galleria.js (come in index.php) -->
    <script>
    window.images        = [];
    window.fullImages    = [];
    window.galleryImages = [];
    </script>

    <!-- Config camera -->
    <script>
    var CAM_CONFIG = {
        cropBottomPx:  <?php echo $CAMERA_CONFIG['crop_bottom_px']; ?>,
        cropBottomPct: '<?php echo $CAMERA_CONFIG['crop_bottom_pct']; ?>'
    };
    </script>

    <!-- Ricarica periodica iframe tabelle meteo con cache-busting -->
    <script src="aggiorna_dati_meteo.js?v=<?php echo filemtime(__DIR__ . '/aggiorna_dati_meteo.js'); ?>"></script>

    <!-- Aggiorna #main-image + #temp-label colorato.
         Senza .gallery nel DOM logga solo un warning e continua correttamente. -->
    <script src="aggiorna_galleria.js?v=<?php echo filemtime(__DIR__ . '/aggiorna_galleria.js'); ?>"></script>

    <!-- Toggle menu -->
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
            // Salva src originale se non ancora salvato
            if (!_iframeSrcOriginali[fr.id]) _iframeSrcOriginali[fr.id] = fr.src;
            fr.src = d.src;
        }

        if (d.action === 'tornaTabella' && fr) {
            var srcOriginale = _iframeSrcOriginali[fr.id];
            if (srcOriginale) fr.src = srcOriginale;
            // Resetta il pulsante se esiste
            var btnReset = document.querySelector('[data-iframe="' + fr.id + '"]');
            if (btnReset) {
                btnReset.innerHTML = '&#128200;&nbsp;Grafico';
                btnReset.setAttribute('data-mode', 'tabella');
            }
        }
    });

    // Pulsante "Grafico" / "← Tabella" esterno (header sezione) — swap src dell'iframe
    document.addEventListener('DOMContentLoaded', function() {
        var btn = document.getElementById('btn-grafico-stat1');
        if (!btn) return;
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var iframeId = btn.getAttribute('data-iframe');
            var src      = btn.getAttribute('data-src');
            var mode     = btn.getAttribute('data-mode');
            var fr = document.getElementById(iframeId);
            if (!fr) return;

            if (mode === 'tabella') {
                // Passa a grafico
                if (!_iframeSrcOriginali[iframeId]) _iframeSrcOriginali[iframeId] = fr.src;
                fr.src = src;
                btn.innerHTML = '&#8592;&nbsp;Tabella';
                btn.setAttribute('data-mode', 'grafico');
            } else {
                // Torna a tabella
                var srcOriginale = _iframeSrcOriginali[iframeId];
                if (srcOriginale) fr.src = srcOriginale;
                btn.innerHTML = '&#128200;&nbsp;Grafico';
                btn.setAttribute('data-mode', 'tabella');
            }
        });
    });

    // Toggle grafico/tabella per stat3
    document.addEventListener('DOMContentLoaded', function() {
        var btn3 = document.getElementById('btn-grafico-stat3');
        if (!btn3) return;
        btn3.addEventListener('click', function(e) {
            e.preventDefault();
            var iframeId = btn3.getAttribute('data-iframe');
            var src      = btn3.getAttribute('data-src');
            var mode     = btn3.getAttribute('data-mode');
            var fr = document.getElementById(iframeId);
            if (!fr) return;
            if (mode === 'tabella') {
                if (!_iframeSrcOriginali[iframeId]) _iframeSrcOriginali[iframeId] = fr.src;
                fr.src = src;
                btn3.innerHTML = '&#8592;&nbsp;Tabella';
                btn3.setAttribute('data-mode', 'grafico');
            } else {
                var srcOriginale = _iframeSrcOriginali[iframeId];
                if (srcOriginale) fr.src = srcOriginale;
                btn3.innerHTML = '&#128200;&nbsp;Grafico';
                btn3.setAttribute('data-mode', 'tabella');
            }
        });
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.querySelector('.menu-toggle');
    const submenu = document.querySelector('.submenu');
    var autoCloseTimer = null;

    function openMenu() {
        submenu.classList.add('active');
        clearTimeout(autoCloseTimer);
        autoCloseTimer = setTimeout(closeMenu, 5000);
    }

    function closeMenu() {
        submenu.classList.remove('active');
        clearTimeout(autoCloseTimer);
        // chiudi anche eventuali sub-submenu aperti
        document.querySelectorAll('.has-sub.sub-active').forEach(function(el) {
            el.classList.remove('sub-active');
        });
    }

    menuToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (submenu.classList.contains('active')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    // Gestione sub-submenu (click su .has-sub)
    document.querySelectorAll('.has-sub').forEach(function(item) {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
            // resetta timer auto-close: l'utente sta interagendo
            clearTimeout(autoCloseTimer);
            autoCloseTimer = setTimeout(closeMenu, 5000);
            item.classList.toggle('sub-active');
        });
    });

    // Chiudi tutto cliccando fuori
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.header-menu-container')) {
            closeMenu();
        }
    });
});</script>

    <!-- SPINNER
         Attende: immagine principale + ogni iframe tabella.
         Per aggiungere tabella: aggiungi id a tabelle_ids[] -->
    <script>
    (function () {
        'use strict';

        var sp = document.getElementById('page-spinner');
        if (!sp) return;

        var tabelle_ids = [
            'stat-iframe-tab1',
            'stat-iframe-tab2',
            'stat-iframe-tab3'
            /* Aggiungi: 'stat-iframe-tab4', ... */
        ];

        var state = { main: false };
        tabelle_ids.forEach(function (id) { state[id] = false; });

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

            /* A) Immagine principale - attesa anche dopo che aggiorna_galleria.js la imposta */
            (function () {
                var img = document.getElementById('main-image');
                if (!img) { markDone('main'); return; }

                /* L'immagine parte vuota e viene impostata da aggiorna_galleria.js:
                   monitoriamo con MutationObserver il cambio di src */
                var done = false;
                function segnala() {
                    if (done) return;
                    done = true;
                    markDone('main');
                }

                img.addEventListener('load',  segnala, { once: true });
                img.addEventListener('error', segnala, { once: true });
                setTimeout(segnala, 8000);
            })();

            /* B) Iframe tabelle */
            tabelle_ids.forEach(function (id) {
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