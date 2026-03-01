<?php
/**
 * GALLERIA METEO SIMIGNANO — v3.0 (solo HTML statico, dati via JS fetch)
 */
require_once __DIR__ . '/camera_config.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.98, maximum-scale=5.0, user-scalable=yes">
    
    <!-- 🔧 ANTI-CACHE per Safari mobile -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <title>MeteoSimignano - Galleria Webcam</title>
    <link rel="stylesheet" href="galleria-lightbox.css">
    <link rel="stylesheet" href="header_shared.css">
    
    <style>
        /* ====================================================================
           STILI GENERALI
           ==================================================================== */
        
        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0;
            padding: 0;
        }
        
        
        /* ====================================================================
           IMMAGINE PRINCIPALE
           ==================================================================== */
        
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
        
        /* Overlay con data e temperatura sopra l'immagine principale */
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
        
        
        
        /*INSERISCE UNA BANDIERA DI SFONDO AL TESTO + DATA+PALL+TEMP*/
        .main-container > .date-text::after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        bottom: -0.8em;    /* scende quanto il font cresce */
        height: 2.2em;     /* altezza proporzionata al testo */
        background: rgba(8, 8, 8, 1); /* stesso colore */
        z-index: -1;      /* dietro il testo */
        }
        
        @media (max-width: 599px) {
    .main-container > .date-text::after {
        height: 30px;
        bottom: -10px;

        /* CORREZIONE CHIAVE: 
           Sposta a sinistra di 10px per coprire il padding del genitore (.main-container) */
        left: -10px; 

        /* Imposta la larghezza a 100% (del .date-text) + 20px (10px sx + 10px dx)
           per coprire l'intera larghezza del .main-container. */
        width: calc(100% + 20px); 
        
        right: auto; /* Rimuoviamo right per far prevalere width */
        margin: 0;
    }
}

        /* Il contenitore dellâ€™immagine principale DEVE essere relative */
        #main-image-wrapper,
        .main-image-wrapper {
        position: relative;
        }

        /* Overlay data/ora principale centrato */
        #main-image-date {
          position: absolute;
          bottom: 0.3rem;        /* distanza dal bordo â€” puoi mettere 0 per attaccarlo */
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
        
        /* Eventuale testo di temperatura */
        #temp-label {
          margin-left: 0.5rem;
        }

        
        /* ====================================================================
           TABELLA METEO (iframe)
           ==================================================================== */
        
        #tabella-meteo,
        .tabella-meteo {
            width: 100%;
            max-width: 1000px;
            display: flex;
            justify-content: center;
            margin: 20px 0; /* Aggiungi spazio sopra e sotto */
        }

        #tabella-meteo iframe,
        .tabella-meteo iframe {
            width: 100%;
            height: 300px;
            border: none;
        }
        
      
        

/* ================================================================
   HEADER GALLERIA: layout 3/4 + 1/4
   ================================================================ */
.gallery-header {
  container-type: inline-size;
  display: grid;
  grid-template-columns: 3fr 1fr;
  align-items: center;
  gap: 10px;

  width: 90% !important;  /* CAMBIA a 90% per centrare di piÃ¹ */
  max-width: 1000px !important;
  margin: 20px auto;
  padding: 0;  /* RIMUOVI il padding se usi width ridotta */
  box-sizing: border-box;
}
@media (max-width: 599px) {
  .gallery-header {
    gap: 6px;
    padding: 0 8px;
  }
}
/* ================================================================
   TITOLO â€” adatta il font al 75% disponibile senza andare a capo
   ================================================================ */
.gallery-title {
  margin: 0;
  white-space: normal;
  overflow: visible;
  text-overflow: clip;
  min-width: 0;  
  font-weight: bold;
  color: black;
  text-align: left;
  font-size: clamp(12px, 3.6vw, 36px);
}

@supports (font-size: 1cqw) {
  .gallery-title {
    font-size: clamp(12px, 4.6cqw, 34px);
  }
}
@media (max-width: 599px) {
  .gallery-title {
    font-size: clamp(19px, 3.2vw, 18px);
  }
}


/* ================================================================
   BOTTONE â€” stile originale: contorno nero, testo nero, sfondo trasparente
   ================================================================ */
.gallery-cta {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;  /* ðŸ†• Spazio tra icona e testo */

  background-color: transparent !important;  /* ðŸ”§ Niente sfondo */
  color: black;                   /* ðŸ”§ Testo nero */
  border: 2px solid black;        /* ðŸ†• Bordo nero */

  font-weight: bold;
  font-size: clamp(10px, 2.8cqw, 18px);
  line-height: 1.2;
  cursor: pointer;
  padding: 4px 8px;
  box-sizing: border-box;
  
  min-height: 30px;
  white-space: normal;
  text-align: center;

  transition: all 0.2s ease;
}

/* Effetto hover coerente con tuo stile */
.gallery-cta:hover {
  border-color: red;
  color: red;
}

@supports (font-size: 1cqw) {
  .gallery-cta {
    font-size: clamp(12px, 2.6cqw, 18px);
  }
}

/* Su mobile molto stretto: leggermente piÃ¹ compatto */
@media (max-width: 420px) {
  .gallery-header { gap: 8px; padding: 0 6px; }
}

/* ===== Spinner globale accanto alle coordinate ===== */
.sub-title-row{
  display: inline-flex;
  align-items: center;
  gap: 10px;
}

.header-subrow{
  display: inline-flex;
  align-items: center;
  gap: 10px;
  justify-content: center;
}

/* Spinner riusabile */
.spinner{
  width: 14px;
  height: 14px;
  border: 2px solid rgba(0,0,0,0.25);
  border-top-color: #333;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.spinner.hidden{ display:none; }

@keyframes spin{ to{ transform: rotate(360deg); } }

/* Facoltativo: effetto â€œsto caricando paginaâ€ */
.page-loading { cursor: progress; }
.page-loading main { opacity: 0.65; }

/* ====================================================================
   VOCI DEL SOTTO MENU - IN ALTO A DX NELLA PAGINA
   ==================================================================== */

/* Container del menu */
.header-menu-container {
    position: relative;
}

/* Pulsante menu */
.menu-toggle {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
}

/* Sottomenu */
.submenu {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 10px;
    background: white;
    border: 2px solid #333;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    min-width: max-content;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease;
    z-index: 1000;
}

/* Sottomenu visibile */
.submenu.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

/* Voci del sottomenu */
.submenu-item {
    display: block;
    padding: 12px 16px;
    color: #333;
    text-decoration: none;
    font-size: 14px;
    text-align: left;
    transition: background-color 0.2s ease;
    border-bottom: 1px solid #ddd;
}

.submenu-item:last-child {
    border-bottom: none;
}

.submenu-item:hover {
    background-color: #f0f0f0;
}

.submenu-item:first-child {
    border-radius: 6px 6px 0 0;
}

.submenu-item:last-child {
    border-radius: 0 0 6px 6px;
}


   .submenu-item {
    display: block;
    padding: 12px 20px;
    color: #333;
    text-decoration: none;
    font-size: 14px;
    transition: background-color 0.2s ease;
    border-bottom: 1px solid #f0f0f0;
}

.submenu-item:last-child {
    border-bottom: none;
}

.submenu-item:hover {
    background-color: #f8f8f8;
}

.submenu-item:first-child {
    border-radius: 8px 8px 0 0;
}

.submenu-item:last-child {
    border-radius: 0 0 8px 8px;
}

/* =========================================================
   LANDSCAPE PHONE: overlay sopra immagine + scala 90%
   (HTML attuale: h2#main-image-date.date-text Ã¨ sotto all'immagine)
   ========================================================= */
@media (orientation: landscape) and (max-height: 480px) {

  /* il contenitore deve essere riferimento per l'overlay */
  .main-container{
    position: relative !important;
    overflow: hidden !important;
  }

  /* immagine al 90% e centrata */
  .main-image{
    width: 90% !important;
    max-width: 90% !important;
    display: block !important;
    margin: 0 auto !important;
    height: auto !important;
  }

  #main-image-date { font-size: 0.9em !important; }

  /* overlay: usa lo stesso elemento (id=main-image-date) ma lo metti assoluto */
  #main-image-date{
    position: absolute !important;
    left: 50% !important;
    bottom: 0.3rem !important;
    transform: translateX(-50%) scale(0.9) !important;
    transform-origin: bottom center !important;
    z-index: 5 !important;

    /* IMPORTANTI per non â€œallargareâ€ e per restare leggibile */
    width: auto !important;
    max-width: calc(90% - 12px) !important; /* dentro la foto (90%) */
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;

    /* se vuoi: evita che si selezioni mentre scrolli */
    pointer-events: none;
  }

  /* disattiva il comportamento â€œbannerâ€ che avevi pensato per testo sotto */
  .main-container > .date-text::after{
    content: none !important;
  }
}
/* =========================================================
   LANDSCAPE PHONE â€“ FIX: titolo galleria troppo piccolo
   (reset + px, evita scaling cumulativo)
   ========================================================= */
@media (orientation: landscape) and (max-height: 480px){

   /* Titolo: "Galleria ultime 36 h" */
  .gallery-header h2,
  .gallery-title{
    font-size: 20px !important;
    line-height: 1.15 !important;
  }

  /* Bottone: "Diario del cielo" */
  .gallery-cta,
  .diario-link,
  .gallery-button{
    font-size: 16px !important;
    padding: 6px 10px !important;
    border-width: 1px !important;
    border-radius: 6px !important;
    line-height: 1.1 !important;
  }
}


    </style>
    <link rel="stylesheet" href="header_shared.css">

</head>

<body>
    <!-- ====================================================================
         HEADER
         ==================================================================== -->
    
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
        <span>43°17′32.5″N 11°10′01.49″E @ 418m slm</span>
        <span id="page-spinner" class="spinner" aria-label="Caricamento in corso"></span>
    </h1>
    </div>
   

    
     <!-- Icona Menu/Indice (destra) -->
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
                <a href="#" class="submenu-item" onclick="window.open('classifica_immagini.php?token=', '_blank'); return false;">Admin</a>
            </div>
        </div>
</header>

    <main>
        <!-- ================================================================
             IMMAGINE PRINCIPALE CON OVERLAY DATA/TEMPERATURA
             ================================================================ -->
        
        <div class="main-container">
            <!-- Immagine principale (src popolato da JavaScript) -->
            <img id="main-image" 
                src="" 
                alt="Caricamento immagine..." 
                class="main-image">
            
            <!-- Overlay con data e temperatura -->
            <h2 class="date-text" id="main-image-date">
                <!-- Data e ora dell'immagine -->
                <span id="date-label">
                    Ultima immagine (dir. NO): <?php echo htmlspecialchars($mainImageDate); ?>
                </span>
                
                <!-- Temperatura con colore dinamico -->
                <span id="temp-label" 
                    class="temp-data temp-default">
                    --°C
                </span>
            </h2>
        </div>
        
        <!-- ================================================================
             TABELLA DATI METEO (iframe)
             ================================================================ -->
        
        <div class="tabella-meteo" style="position:relative;">
        <div id="tabella-placeholder" style="
            position:absolute; top:0; left:0; right:0; bottom:0;
            display:flex; align-items:center; justify-content:center;
            font-family:Arial,sans-serif; font-size:14px; color:#999;
            background:#fff; z-index:1;">
            Caricamento dati meteo...
        </div>
        <iframe src="tabella_home_display.php" 
                id="tabella-meteo-iframe"
                width="100%" 
                height="200px" 
                frameborder="0"
                title="Dati meteorologici"></iframe>
    </div>
        
        <!-- ================================================================
     TITOLO GALLERIA E LINK (3/4 titolo + 1/4 bottone)
     ================================================================ -->
<div class="gallery-header">
  <h2 class="gallery-title">Galleria ultime 36 h</h2>
  <a href="belle.php" class="button gallery-cta"><svg class="cta-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
    <line x1="10" y1="6" x2="16" y2="6"></line>
    <line x1="10" y1="10" x2="16" y2="10"></line>
    <line x1="10" y1="14" x2="16" y2="14"></line>
  </svg>Diario del cielo</a>
</div>

        
        <!-- ================================================================
             GALLERIA MINIATURE
             ================================================================ -->
        
        <div class="gallery">
            <!-- Popolata da aggiorna_galleria.js -->
        </div>
                
        <!-- ================================================================
             LIGHTBOX (modale per ingrandire immagini)
             ================================================================ -->
        
        <div class="lightbox" id="lightbox">
            <!-- Bottone chiudi -->
            <button id="close-btn" class="lightbox-control-btn lightbox-close" aria-label="Chiudi">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="red">
                    <path d="M18 6L6 18M6 6l12 12" stroke="red" stroke-width="5" stroke-linecap="round" />
                </svg>
            </button>

            <!-- Bottone rewind (torna indietro veloce) -->
            <button id="rewind-btn" class="lightbox-control-btn" aria-label="Rewind/Pausa">
                <span class="dot"></span>
                <svg id="rewind-icon" viewBox="0 0 24 24" width="32" height="32" fill="red" stroke-width="5">
                    <path d="M11 12L20 6V18L11 12Z"/>
                    <path d="M4 12L13 6V18L4 12Z"/>
                </svg>
            </button>

            <!-- Bottone forward (vai avanti veloce) -->
            <button id="forward-btn" class="lightbox-control-btn" aria-label="Forward/Pausa">
                <svg id="forward-icon" viewBox="0 0 24 24" width="32" height="32" fill="red" stroke-width="5">
                    <path d="M11 12L20 6V18L11 12Z"/>
                    <path d="M4 12L13 6V18L4 12Z"/>
                </svg>
                <span class="dot"></span>
            </button>

            <!-- Contenuto lightbox -->
            <div class="lightbox-content">
                <img id="lightbox-img" src="" alt="Immagine ingrandita">
                <div id="lightbox-info" class="lightbox-info"></div>
                <div id="lightbox-date" class="lightbox-date"></div>
            </div>
            
            <!-- Bottoni navigazione precedente/successivo -->
            <button class="nav-btn prev" onclick="prevImage(event)" aria-label="Immagine precedente">
                <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="red" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6" />
                </svg>
            </button>
            
            <button class="nav-btn next" onclick="nextImage(event)" aria-label="Immagine successiva">
                <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="red" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6" />
                </svg>
            </button>
        </div>
    </main>

    <!--====================================================================
         JAVASCRIPT
         ==================================================================== -->
    
    <script>
    /* Array inizializzati vuoti — popolati da aggiorna_galleria.js */
    window.images = [];
    window.fullImages = [];
    window.galleryImages = [];
    </script>
    <!-- Inietta config camera dal PHP al JavaScript -->
    <script>
    var CAM_CONFIG = {
      cropBottomPx:  <?php echo $CAMERA_CONFIG['crop_bottom_px']; ?>,
      cropBottomPct: '<?php echo $CAMERA_CONFIG['crop_bottom_pct']; ?>'
    };
    </script>
    <!--Script per aggiornamento automatico dati meteo -->
    <script src="aggiorna_dati_meteo.js?v=<?php echo filemtime(__DIR__ . '/aggiorna_dati_meteo.js'); ?>"></script>
    
    <!--Script per gestione lightbox (modale immagini) -->
    <script src="galleria-lightbox.js?v=<?php echo filemtime(__DIR__ . '/galleria-lightbox.js'); ?>"></script>
    
    <!--Script per aggiornamento automatico galleria (ogni 5 minuti) -->
    <script src="aggiorna_galleria.js?v=<?php echo filemtime(__DIR__ . '/aggiorna_galleria.js'); ?>"></script>
    
    <!-- 🔍 DIAGNOSTICA SAFARI: verifica caricamento script 
    <script>
    (function() {
        // 🛡️ Error handler globale per Safari
        window.addEventListener('error', function(e) {
            if (e.filename && e.filename.includes('aggiorna_galleria.js')) {
                console.error('❌ ERRORE CRITICO in aggiorna_galleria.js:', {
                    message: e.message,
                    lineno: e.lineno,
                    colno: e.colno,
                    filename: e.filename
                });
            }
        });
        
        var diagnostics = {
            userAgent: navigator.userAgent,
            isSafari: /Safari/.test(navigator.userAgent) && !/Chrome/.test(navigator.userAgent),
            scriptsLoaded: {
                galleryImages: typeof window.galleryImages !== 'undefined',
                fullImages: typeof window.fullImages !== 'undefined',
                aggiornaGalleria: typeof aggiornaGalleria !== 'undefined',
                openLightbox: typeof openLightbox !== 'undefined'
            },
            timestamp: new Date().toISOString()
        };
        
        console.log('📊 DIAGNOSTICA CARICAMENTO:', diagnostics);
        
        // Se siamo su Safari e gli script non sono caricati, alert diagnostico
        if (diagnostics.isSafari && !diagnostics.scriptsLoaded.aggiornaGalleria) {
            console.error('⚠️ ERRORE: aggiorna_galleria.js non caricato su Safari!');
            // Decommenta per debug in produzione:
            // alert('Debug Safari: script non caricato. Controlla console.');
        }
    })();
    </script>-->
    
    
    <!--GESTIONE SPINNER DI CARICAMENTO DATI -->
    <script>
(function(){
  'use strict';

  var sp = document.getElementById('page-spinner');
  if (!sp) return;

  // --- Task list: 3 cose da aspettare ---
  var state = {
    main: false,
    gallery: false,
    tabella: false
  };

  function show(){ sp.classList.remove('hidden'); }
  function hide(){ sp.classList.add('hidden'); }

  function markDone(key){
    if (state[key] === true) return;
    state[key] = true;
    // console.log('[LOADING] done:', key, state);

    if (state.main && state.gallery && state.tabella) {
      hide();
    }
  }

  function init(){
    show();

    /*========== A) MAIN IMAGE ==========*/
    (function(){
      var img = document.getElementById('main-image');
      if (!img) { markDone('main'); return; }

      function done(){ markDone('main'); }
      if (img.complete) return done();
      img.addEventListener('load', done, { once:true });
      img.addEventListener('error', done, { once:true });

      // safety
      setTimeout(done, 8000);
    })();

    // ========== B) GALLERIA MINIATURE ==========
// Aspetta che aggiornaGalleria() popoli il DOM, poi monitora le immagini.
// Lo spinner si nasconde quando tutte le immagini tranne una sono caricate.
(function(){
  // Osserva quando il JS aggiunge le thumb alla .gallery
  var galleryEl = document.querySelector('.gallery');
  if (!galleryEl) { markDone('gallery'); return; }

  var observer = new MutationObserver(function() {
    var imgs = galleryEl.querySelectorAll('img');
    if (!imgs || imgs.length === 0) return; // ancora vuota

    // Thumb aggiunte: smetti di osservare e monitora il caricamento
    observer.disconnect();

    var totale = imgs.length;
    var soglia = Math.max(1, totale - 1); // tutte tranne 1
    var caricate = 0;
    var fatto = false;

    function unaCaricata() {
      if (fatto) return;
      caricate++;
      if (caricate >= soglia) {
        fatto = true;
        markDone('gallery');
      }
    }

    for (var i = 0; i < imgs.length; i++) {
      if (imgs[i].complete) {
        unaCaricata();
      } else {
        imgs[i].addEventListener('load', unaCaricata, { once: true });
        imgs[i].addEventListener('error', unaCaricata, { once: true });
      }
    }
  });

  observer.observe(galleryEl, { childList: true });

  // Safety timeout
  setTimeout(function(){ markDone('gallery'); }, 15000);
})();

    // ========== C) IFRAME TABELLA ==========
(function(){
  var fr = document.getElementById('tabella-meteo-iframe');
  var ph = document.getElementById('tabella-placeholder');
  if (!fr) { markDone('tabella'); if (ph) ph.style.display = 'none'; return; }

  function iframePronto() {
    markDone('tabella');
    if (ph) ph.style.display = 'none';
  }

  fr.addEventListener('load', iframePronto, { once: true });

  // Safety timeout
  setTimeout(iframePronto, 10000);
})();
  }

  // ascolta i messaggi dall'iframe tabella_home_display.php
  window.addEventListener('message', function(ev){
    if (ev.origin !== window.location.origin) return;

    var msg = ev.data || {};
    if (msg.src !== 'tabella_home_display') return;

    if (msg.type === 'TAB_HOME_LOADED') {
      markDone('tabella');
    }
  });

  // BFCache: se torni indietro, non restare in loading
  window.addEventListener('pageshow', function(){
    // se la pagina viene ripescata â€œgiÃ  prontaâ€, chiudi
    if (state.main && state.gallery && state.tabella) hide();
  });

  init();
})();
</script>
<script>
// Toggle menu in alto a dx
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.querySelector('.menu-toggle');
    const submenu = document.querySelector('.submenu');
    
    menuToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        submenu.classList.toggle('active');
    });
    
    // Chiudi menu cliccando fuori
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.header-menu-container')) {
            submenu.classList.remove('active');
        }
    });
}); 
</script>

    
    
    
</body>
</html>