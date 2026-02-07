<?php
/**
 * GALLERIA METEO SIMIGNANO
 * 
 * Questo script visualizza le immagini meteorologiche delle ultime 36 ore
 * con i relativi dati meteo (temperatura, umidità, pressione, vento).
 * 
 * STRUTTURA:
 * 1. Configurazione e caricamento dipendenze
 * 2. Recupero dati dal database
 * 3. Elaborazione dati (conversione percorsi, formattazione date)
 * 4. Determinazione immagine principale e temperatura
 * 5. Rendering HTML
 * 
 * @author MeteoSimignano
 * @version 2.0
 */

// ============================================================================
// SEZIONE 1: CONFIGURAZIONE E DEBUG
// ============================================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================================================
// SEZIONE 2: CARICAMENTO DIPENDENZE
// ============================================================================

// Connessione database
// NOTA: Decommenta la riga sotto per produzione, usa la seconda per sviluppo locale
//require_once '/home/erbielqv/envelop.php'; // PRODUZIONE
require_once __DIR__ . '/../envelop.php';     // SVILUPPO LOCALE

// Funzioni per gestione immagini e database
require_once __DIR__ . '/aggiornaCartellaImmagini.php';

// Helper per nomi tabelle (gestisce ambiente test/produzione)
require_once __DIR__ . '/env_tables_helper.php';

// ============================================================================
// SEZIONE 3: CONFIGURAZIONE PERCORSI E TABELLE
// ============================================================================

// Directory contenente le immagini della webcam
$directory = __DIR__ . '/FoscamCamera_E8ABFAA799FE/snap/';

// Nome tabella dinamico (usa tabella test se USE_TEST_MODE=true)
$table_name = table_name('DB_immagini_36h');

// ============================================================================
// SEZIONE 4: RECUPERO DATI DAL DATABASE
// ============================================================================

/**
 * Recupera i dati delle immagini dalla cartella e dal database
 * 
 * @return array Struttura:
 *   - 'records': Array di record con campi: src, data_ora, temp, hr, p_hpa, vento, dir
 *   - 'error': Messaggio di errore (se presente)
 */
$data = getImageDataFromFolder($pdo, $directory, $table_name);

// ============================================================================
// SEZIONE 5: INIZIALIZZAZIONE VARIABILI PER IL TEMPLATE
// ============================================================================

// Variabili per l'immagine principale
$mainImage = '';
$mainImageDate = 'Data non disponibile';
$mainTemperature = 'N/D';

// Array di tutti i record (per la galleria e JavaScript)
$records = [];

// Classe CSS per colorare la temperatura
$tempColorClass = 'temp-default';

// ============================================================================
// SEZIONE 6: ELABORAZIONE DATI
// ============================================================================

if (!isset($data['error'])) {
    $records = $data['records'];
    
    // ------------------------------------------------------------------------
    // 6.1: Conversione percorsi filesystem → web
    // ------------------------------------------------------------------------
    // I percorsi nel database sono assoluti (/Applications/MAMP/htdocs/...)
    // ma il browser ha bisogno di percorsi relativi (FoscamCamera_E8ABFAA799FE/snap/...)
    
    foreach ($records as &$record) {
    // Array con tutti i percorsi filesystem da rimuovere
    $pathsToRemove = [
        '/home/erbielqv/public_html/test/public_html',      // TEST_SERVER
        '/home/erbielqv/public_html/meteosimignano',        // PRODUZIONE
        '/Applications/MAMP/htdocs/meteosimignano'          // TEST_LOCALE (Mac)
    ];
    
    // Prova a sostituire ogni percorso finché uno non funziona
    foreach ($pathsToRemove as $path) {
        $newSrc = str_replace($path, '', $record['src']);
        if ($newSrc !== $record['src']) {
            // Sostituzione riuscita, esci dal loop
            $record['src'] = $newSrc;
            break;
        }
    }
    }
    unset($record); // Rimuove riferimento per evitare side effects
    
    // ------------------------------------------------------------------------
    // 6.2: Estrazione percorsi immagini per galleria
    // ------------------------------------------------------------------------
    $images = array_column($records, 'src');
    
    // ------------------------------------------------------------------------
    // 6.3: Determinazione immagine più recente
    // ------------------------------------------------------------------------
    // Scorri tutti i record per trovare quello con timestamp più alto
    // (questo sarà l'immagine principale da mostrare in grande)
    
    $maxTimestamp = 0;
    
    foreach ($records as &$rec) {
        if (!empty($rec['data_ora'])) {
            $timestamp = strtotime($rec['data_ora']);
            
            // Se questo record è più recente del precedente massimo
            if ($timestamp !== false && $timestamp > $maxTimestamp) {
                $maxTimestamp = $timestamp;
                $mainImage = $rec['src'];
                
                // Formatta data per visualizzazione (da ISO a formato italiano)
                $mainImageDate = (new DateTime($rec['data_ora']))->format('d/m/Y H:i');
                
                // Estrai temperatura dal record più recente
                if (isset($rec['temp'])) {
                    $mainTemperature = round($rec['temp']);
                }
            }
            
            // Formatta la data anche per questo record (usata in JavaScript)
            $rec['data_ora'] = (new DateTime($rec['data_ora']))->format('d/m/Y H:i');
        }
    }
    unset($rec);
    
    // ------------------------------------------------------------------------
    // 6.4: Calcolo classe CSS per colore temperatura
    // ------------------------------------------------------------------------
    
    /**
     * Determina la classe CSS in base al valore della temperatura
     * 
     * @param mixed $temp Temperatura in gradi Celsius
     * @return string Nome classe CSS (temp-red, temp-orange, temp-green, ecc.)
     */
    function getTempColorClass($temp) {
    // Converti esplicitamente a float, anche se è stringa
    if (!is_numeric($temp)) {
        return 'temp-default';
    }
    
    $temp = floatval($temp);
    
    // Ordine corretto: dal più alto al più basso
    if ($temp > 35)        return 'temp-red';        // > 35°C
    if ($temp >= 25)       return 'temp-orange';     // 25-35°C
    if ($temp >= 15)       return 'temp-green';      // 15-24°C
    if ($temp >= 5)        return 'temp-lightblue';  // 5-14°C
    if ($temp >= -3)       return 'temp-blue';       // -2-3°C
    return 'temp-violet';                            // < -2°C
}
    
    $tempColorClass = getTempColorClass($mainTemperature);
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.98, maximum-scale=5.0, user-scalable=yes">
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

        /* Il contenitore dell’immagine principale DEVE essere relative */
        #main-image-wrapper,
        .main-image-wrapper {
        position: relative;
        }

        /* Overlay data/ora principale centrato */
        #main-image-date {
          position: absolute;
          bottom: 0.3rem;        /* distanza dal bordo — puoi mettere 0 per attaccarlo */
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

  width: 90% !important;  /* CAMBIA a 90% per centrare di più */
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
   TITOLO — adatta il font al 75% disponibile senza andare a capo
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
   BOTTONE — stile originale: contorno nero, testo nero, sfondo trasparente
   ================================================================ */
.gallery-cta {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;  /* 🆕 Spazio tra icona e testo */

  background-color: transparent !important;  /* 🔧 Niente sfondo */
  color: black;                   /* 🔧 Testo nero */
  border: 2px solid black;        /* 🆕 Bordo nero */

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

/* Su mobile molto stretto: leggermente più compatto */
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

/* Facoltativo: effetto “sto caricando pagina” */
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
   (HTML attuale: h2#main-image-date.date-text è sotto all'immagine)
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

    /* IMPORTANTI per non “allargare” e per restare leggibile */
    width: auto !important;
    max-width: calc(90% - 12px) !important; /* dentro la foto (90%) */
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;

    /* se vuoi: evita che si selezioni mentre scrolli */
    pointer-events: none;
  }

  /* disattiva il comportamento “banner” che avevi pensato per testo sotto */
  .main-container > .date-text::after{
    content: none !important;
  }

/* =========================================================
   LANDSCAPE PHONE – FIX: titolo galleria troppo piccolo
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
        <h1 class="main-title">MeteoSimignano</h1>
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
                <a href="belle.php" class="submenu-item">Diario del cielo</a>
                <a href='grafici_termo_plotly.php?range=24h&visible=' class="submenu-item">Grafici</a>
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
                 src="<?php echo htmlspecialchars($mainImage); ?>" 
                 alt="Immagine webcam più recente" 
                 class="main-image">
            
            <!-- Overlay con data e temperatura -->
            <h2 class="date-text" id="main-image-date">
                <!-- Data e ora dell'immagine -->
                <span id="date-label">
                    Ultima immagine (dir. NO): <?php echo htmlspecialchars($mainImageDate); ?>
                </span>
                
                <!-- Temperatura con colore dinamico -->
                <span id="temp-label" 
                      class="temp-data <?php echo htmlspecialchars($tempColorClass); ?>">
                    <?php echo is_numeric($mainTemperature) ? round($mainTemperature).'°C' : 'N/D'; ?>
                </span>
            </h2>
        </div>
        
        <!-- ================================================================
             TABELLA DATI METEO (iframe)
             ================================================================ -->
        
        <div class="tabella-meteo">
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
            <?php foreach($images as $index => $image): ?>
                <img src="<?php echo htmlspecialchars($image); ?>" 
                     alt="Immagine webcam" 
                     onclick="openLightbox(<?php echo $index; ?>)">
            <?php endforeach; ?>
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

    <!-- ====================================================================
         JAVASCRIPT
         ==================================================================== -->
    
    <script>
        /**
         * VARIABILE GLOBALE: Array di tutte le immagini con metadati
         * 
         * Struttura di ogni elemento:
         * {
         *   src: "percorso/immagine.jpg",
         *   data_ora: "09/01/2025 14:30",
         *   temp: 18.7,
         *   hr: 91.0,
         *   p_hpa: 1017.0,
         *   vento: 1.8,
         *   dir: 138
         * }
         */
        window.images = <?php echo json_encode($records); ?>;
    </script>
    
    <!-- Script per aggiornamento automatico dati meteo -->
    <script src="aggiorna_dati_meteo.js"></script>
    
    <!-- Script per gestione lightbox (modale immagini) -->
    <script src="galleria-lightbox.js"></script>
    
    <!-- Script per aggiornamento automatico galleria (ogni 5 minuti) -->
    <script src="aggiorna_galleria.js"></script>
    
    
    <!-- GESTIONE SPINNER DI CARICAMENTO DATI -->
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

    // ========== A) MAIN IMAGE ==========
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
    (function(){
      var gallery = document.querySelector('.gallery');
      if (!gallery) { markDone('gallery'); return; }

      var imgs = gallery.querySelectorAll('img');
      if (!imgs || imgs.length === 0) { markDone('gallery'); return; }

      var remaining = imgs.length;
      function oneDone(){
        remaining--;
        if (remaining <= 0) markDone('gallery');
      }

      for (var i=0; i<imgs.length; i++){
        var im = imgs[i];
        if (im.complete) {
          oneDone();
        } else {
          im.addEventListener('load', oneDone, { once:true });
          im.addEventListener('error', oneDone, { once:true });
        }
      }

      // safety
      setTimeout(function(){ markDone('gallery'); }, 12000);
    })();

    // ========== C) IFRAME TABELLA: via postMessage ==========
    // se l'iframe non esiste, non bloccare
    (function(){
      var fr = document.getElementById('tabella-meteo-iframe');
      if (!fr) { markDone('tabella'); return; }

      // fallback: se per qualche motivo non arriva il messaggio, sblocca dopo 10s
      setTimeout(function(){ markDone('tabella'); }, 10000);
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
    // se la pagina viene ripescata “già pronta”, chiudi
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