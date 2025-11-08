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
    <meta name="viewport" content="width=device-width, initial-scale=1.08, maximum-scale=5.0, user-scalable=yes">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <title>MeteoSimignano - Galleria Webcam</title>
    <link rel="stylesheet" href="galleria-lightbox.css">
    
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
           HEADER - Titolo e coordinate
           ==================================================================== */
        .main-header {
    width: 100%;
    max-width: 1000px;
    text-align: center;
    padding: 10px;
    box-sizing: border-box;
    
    /* Layout Flexbox */
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    position: relative; 
}

.header-content {
    flex-grow: 1; 
    text-align: center; 
    min-width: 0; 
}

.header-icon {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #333; 
    min-width: 20px; 
    font-size: 0.7em;
    padding: 0 5px;
}

.header-icon svg {
    /* DEFAULT (Desktop-First): Si applica a tutti gli schermi, inclusi quelli grandi */
    width: 36px;
    height: 36px;
    stroke-width: 2.5; 
}

/* MEDIA QUERY: Sovrascrive il default solo quando la larghezza è <= 599px (Mobile/Small) */
@media (max-width: 599px) {
    .header-icon svg {
        width: 20px; /* Riduzione su schermi piccoli */
        height: 20px;
        padding-left: 20px; /* Esempio: Aumenta il rientro a 20px */
        padding-right: 20px;
        stroke-width: 2.5;
    }
}
.header-icon .icon-label {
    display: block; 
    margin-top: 2px;
}
        .main-title {
            font-size: 6vw;
            white-space: nowrap;
            margin: 0;
        }
        
        @media (min-width: 600px) {
            .main-title {
                font-size: 55px;
            }
        }
        
        .sub-title {
            font-size: 3vw;
            font-weight: normal;
            white-space: nowrap;
            margin: 10px;
        }
        
        @media (min-width: 600px) {
            .sub-title {
                font-size: 30px;
            }
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
        }

        @media (max-width: 599px) {
    .main-container {
        padding: 0 10px;
    }
}
        
        .main-image {
            width: 100%;
            max-width: 1000px;
            display: block;
            cursor: pointer;
            height: auto;
        }
        
        /* Overlay con data e temperatura sopra l'immagine principale */
        .main-container > .date-text {
            position: absolute;
            bottom: 0;
            left: 0;
            right 0;
            width: 100%;
            margin: 0;
            text-align: center;
            font-size: clamp(10px, 3vw, 25px);
            line-height: 1.2;
            z-index: 2;
            color: white;
            background: rgba(8, 8, 8, 1);
            padding: 4px 8px;
            box-sizing: border-box
            overflow: hidden;
        }

        @media (max-width: 599px) {
    .main-container > .date-text {
        left: 10px;    /* AGGIUNGI QUESTA */
        right: 10px;   /* AGGIUNGI QUESTA */
        width: calc(100% - 20px); /* AGGIUNGI QUESTA */
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
  bottom: 0.1rem;        /* distanza dal bordo — puoi mettere 0 per attaccarlo */
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

  width: 95%;  /* CAMBIA a 90% per centrare di più */
  max-width: 1000px;
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
   BOTTONE — stile originale: sfondo nero, testo rosso
   ================================================================ */
.gallery-cta {
  display: flex;
  align-items: center;
  justify-content: center;

  background-color: black;
  color: red;

  font-weight: bold;
  font-size: clamp(10px, 2.8cqw, 18px);
  line-height: 1.2;
  cursor: pointer;
  padding: 4px 8px;
  box-sizing: border-box;
  
  max-height: 30px;  /* AGGIUNGI QUESTA */
  white-space: normal; /* AGGIUNGI QUESTA per andare a capo */
  text-align: center;  /* AGGIUNGI QUESTA */

  transition: all 0.2s ease;
}

/* Effetto hover coerente con tuo stile */
.gallery-cta:hover {
  background-color: grey;
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

    </style>
</head>

<body>
    <!-- ====================================================================
         HEADER
         ==================================================================== -->
    
    <header class="main-header">
    
    <a href="pluvio.html" class="header-icon left-icon" title="Dati Pluviometro CFR Toscana" target="_blank">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2s7 8 7 12a7 7 0 1 1-14 0c0-4 7-12 7-12z"></path>
            <path d="M16 16l-4 4-4-4"></path>
        </svg>
        <span class="icon-label">Pluvio</span>
    </a>
    
    <div class="header-content">
        <h1 class="main-title">MeteoSimignano</h1>
        <h1 class="sub-title">43°17′32.5″N 11°10′01.49″E @ 418m slm</h1>
    </div>
    
    <a href="indice.php" class="header-icon right-icon" title="Indice e Mappa del Sito">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </a>
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
  <a href="belle.php" class="button gallery-cta">Diario del cielo</a>
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
</body>
</html>