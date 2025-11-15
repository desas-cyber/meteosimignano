<?php

/* 0) Impostazioni per non “sporcare” il JSON con warning/notice ------------- */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../envelop.php';
require_once __DIR__ .'/aggiornaCartellaImmagini.php';

$directory = 'belle/';
$table_name = 'DB_immagini_belle';

// Ottiene i dati delle immagini (inclusa la conversione in records)
$data = getImageDataFromFolder($pdo, $directory, $table_name);
  
$records = []; 

if (isset($data['error'])) {
    // Gestione errore
    echo "<p>Errore: " . htmlspecialchars($data['error']) . "</p>";
} else {
    $records = $data['records'];

    // Ciclo per formattare la data per l'uso nella galleria/lightbox
    foreach ($records as &$rec) { 
        if (!empty($rec['data_ora'])) {
            // Formattazione per la visualizzazione nella miniatura e lightbox
            $rec['data_ora_formattata'] = (new DateTime($rec['data_ora']))->format('d/m/Y H:i');
        } else {
            $rec['data_ora_formattata'] = 'Data/Ora N/D';
        }
    }
}


/**
 * Funzione PHP per determinare la classe di colore in base alla temperatura.
 * @param float|null $temp Temperatura.
 * @return string Classe CSS.
 */
function getTempColorClass($temp) {
  if (!is_numeric($temp)) {
      return 'temp-default';
  }
  
  if ($temp > 35) {
      return 'temp-red';
  }
  if ($temp >= 25) {
      return 'temp-orange';
  }
  if ($temp >= 15) {
      return 'temp-green';
  }
  if ($temp >= 5) {
      return 'temp-lightblue';
  }
  if ($temp >= -3) {
      return 'temp-blue';
  }
  // Se non ricade in nessuna condizione precedente, è < -3°C
  return 'temp-violet';
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.08">
    <title>Meteosimignano_diario_del_cielo</title>
    
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
           HEADER - Titolo, coordinate e icone navigazione
           ==================================================================== */
        
        .main-header {
            width: 100%;
            max-width: 1000px;
            text-align: center;
            padding: 10px;
            box-sizing: border-box;
            
            /* Layout Flexbox per posizionare icone ai lati */
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            position: relative; 
        }
        
        /* Contenitore centrale con titolo e coordinate */
        .header-content {
            flex-grow: 1; 
            text-align: center; 
            min-width: 0; 
        }
        
        /* Stile comune per le icone di navigazione */
        .header-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #333; 
            min-width: 20px; 
            font-size: 0.7em;
            padding: 0 5px;
            cursor: pointer;
        }

 .header-icon:hover {
  
  color: red;
}
        
        /* SVG delle icone - dimensione desktop (default) */
        .header-icon svg {
            width: 36px;
            height: 36px;
            stroke-width: 2.5; 
        }
        
        /* Ridimensionamento icone su mobile */
        @media (max-width: 599px) {
            .header-icon svg {
                width: 20px;
                height: 20px;
                padding-left: 20px;
                padding-right: 20px;
                stroke-width: 2.5;
            }
        }
        
        /* Etichetta sotto le icone */
        .header-icon .icon-label {
            display: block; 
            margin-top: 2px;
        }
        
        /* ====================================================================
           TITOLI - MeteoSimignano e coordinate
           ==================================================================== */
        
        /* Titolo principale - responsive con vw su mobile */
        .main-title {
            font-size: 6vw;
            white-space: nowrap;
            margin: 0;
        }
        
        /* Titolo principale - dimensione fissa su desktop */
        @media (min-width: 600px) {
            .main-title {
                font-size: 55px;
            }
        }
        
        /* Sottotitolo con coordinate - responsive con vw su mobile */
        .sub-title {
            font-size: 3vw;
            font-weight: normal;
            white-space: nowrap;
            margin: 10px;
        }
        
        /* Sottotitolo - dimensione fissa su desktop */
        @media (min-width: 600px) {
            .sub-title {
                font-size: 30px;
            }
        }

/* ==========================================================================
   2) OVERLAY MINIATURE (centrato) - NUOVI STILI
   ========================================================================== */

/* Contenitore overlay: centratura perfetta e blocco compatto */
.overlay-mini {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  /* Fallback dimensioni: percentuali + max in px  */
  width: 60%;
  max-width: 180px;

  text-align: center;
  line-height: 1.05;               /* interlinea più compatta */
  pointer-events: none;            /* non intercetta i click */
}

/* Ogni “pillola” di testo: centrata e compatta */
.overlay-mini > * {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1px;              /* distanza verticale ridotta */
}
.overlay-mini > *:last-child { margin-bottom: 0; }

/* Righe testo overlay (outline per leggibilità) */
.temp-line,
.ora-line,
.meta-line {
  text-shadow:
    0 0 3px rgba(0,0,0,0.9),
    0 0 2px rgba(0,0,0,0.9);
  white-space: nowrap;
}

/* Temperatura: centrata e con gestione eventuale <sup> */
.temp-line {
  font-weight: 700;
}
.temp-line sup, .temp-line .sup {
  vertical-align: baseline;
  position: relative;
  top: -0.1em;
  font-size: 0.8em;
}

/* Ora: inline con micro-gap locale */
.ora-line {
  display: flex;
  align-items: center;
  font-weight: 600;
  color: #ff1e1e;                 /* ora rossa fissa */
}
.ora-line > * { margin-right: 4px; }
.ora-line > *:last-child { margin-right: 0; }

/* Meta (vento / UR / pressione): inline */
.meta-line {
  display: flex;
  align-items: center;
  font-weight: 600;
  color: #00ff6a;                 /* verde elettrico */
}
.meta-line > * { margin-right: 8px; }
.meta-line > *:last-child { margin-right: 0; }


/* ==========================================================================
   3) ICONOGRAFIA - NUOVI STILI
   ========================================================================== */
.icon {
  width: 1em;                      /* scala con il font */
  height: 1em;
  vertical-align: -1px;
  fill: currentColor;
}
.icon-outline { fill: currentColor; }

/* Colori temperatura (classi applicate dinamicamente) */
.temp-red       { color: #ec0835; }
.temp-orange    { color: #cf7618; }
.temp-green     { color: #79f603; }
.temp-lightblue { color: #09e3ce; }
.temp-blue      { color: #0044ff; }
.temp-violet    { color: #8b00ff; }
.temp-default   { color: #cccccc; }


/* =========================
   MOBILE ≤ 480px — versione con margini più piccoli
   ========================= */
@media (max-width: 480px) {

  /* 2 miniature per riga con margini ridotti */
  .gallery .thumb {
    width: calc(50% - 16px);  /* 50% - (2 * 8px) */
    margin: 8px;              /* ridotto da 10px → 8px */
  }

  /* Testi overlay leggermente più piccoli (base mobile) */
.overlay-mini .ora-line,
.overlay-mini .meta-line {
  font-size: clamp(11px, 2.2vw, 16px) !important;
}

/* Temperatura SOLO un po' più grande su mobile */
.overlay-mini .temp-line {
  font-size: clamp(12px, 3.4vw, 18px) !important;
}

  /* Overlay un po' più compatto */
  .overlay-mini {
    width: 72%;
    max-width: 175px;   /* ridotto un filo per equilibrio */
    line-height: 1.05;
  }
}

@supports (font-size: 1cqw) {
  @media (max-width: 480px) {
    /* Ora + meta-line: più piccole */
    .overlay-mini .ora-line,
    .overlay-mini .meta-line {
      font-size: clamp(11px, 4cqw, 16px) !important;
    }

    /* Temperatura: leggermente più grande */
    .overlay-mini .temp-line {
      font-size: clamp(12px, 5cqw, 18px) !important;
    }
  }
}


/* ====================================================================
   GALLERY TITLE - Titolo "Diario del cielo" ingrandito e centrato
   ==================================================================== */
.gallery-title {
    /* Centratura garantita */
    text-align: center; 
    margin: 0;
    
    /* Riduzione della dimensione del carattere */
    font-size: 5vw; /* Rimpicciolisce su schermi piccoli */
    
    /* Imposta un limite massimo in pixel per desktop */
    max-width: 100%; /* Assicura che non forzi la larghezza del contenitore */
    
    /* Manteniamo le proprietà che avevi */
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
    min-width: 0;
    font-weight: bold;
    color: black;
}

@media (min-width: 600px) {
    .gallery-title {
        font-size: 30px; /* Dimensione fissa per schermi grandi */
    }
}
    </style>
        
</head>
<body>

<header class="main-header">
    <!-- Icona Home (sinistra) -->
        <a href="index.php" class="header-icon left-icon" title="Home">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span class="icon-label">Home</span>
        </a>
        <!-- Titolo centrale e coordinate -->
        <div class="header-content">
            <h1 class="main-title">MeteoSimignano</h1>
            <h1 class="sub-title">43°17′32.5″N 11°10′01.49″E @ 418m slm</h1>
        </div>    
        <div style="width: 46px; height: 46px; min-width: 46px;"></div>

        <!-- Icona Menu/Indice (destra) -->
         <a href="x.php" class="header-icon right-icon" title="Filtri">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
        </svg>
        <span class="icon-label">Filtri</span>
    </a>
</header>


<main>
    
   <div class="gallery-header-container"> 
        <h2 class="gallery-title">Diario del cielo</h2> 
  </div>
    

    <div class="gallery">
        <?php 
        foreach($records as $index => $item): 
            $temp = isset($item['temp']) ? (float)$item['temp'] : null;
            $tempDisplay = ($temp !== null) ? number_format($temp, 1) . '°C' : 'N/D';
            $tempClass = getTempColorClass($temp); 

            $dataOraCompleta = isset($item['data_ora_formattata']) ? $item['data_ora_formattata'] : 'Data N/D';
            // Estrazione di data e ora dal formato completo (d/m/Y H:i)
            $oraSolo = substr($dataOraCompleta, -5); 
            $dataSolo = substr($dataOraCompleta, 0, 10); 
        ?>
            <div class="thumb">
                <img src="<?php echo htmlspecialchars($item['src'] . '?t=' . time()); ?>" 
                     alt="Immagine webcam" 
                     onclick="openLightbox(<?php echo $index; ?>)"
                >
                
                <span class="overlay-mini <?php echo $tempClass; ?>">
                    <span class="temp-line">
                        <?php echo $tempDisplay; ?>
                    </span>
                    
                    <span class="ora-line">
                         <svg class="icon icon-outline" viewBox="0 0 24 24" style="vertical-align: middle; width: 1.2em; height: 1.2em; fill: none; stroke: currentColor; stroke-width: 2;">
                            <circle cx="12" cy="12" r="9" />
                            <line x1="12" y1="12" x2="12" y2="7" stroke-linecap="round"></line>
                            <line x1="12" y1="12" x2="15" y2="12" stroke-linecap="round"></line>
                        </svg> 
                        <?php echo $dataSolo . ' ' . $oraSolo; ?>
                    </span>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="lightbox" id="lightbox">
        
        <button id="close-btn" class="lightbox-close" aria-label="Chiudi">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="red">
            <path d="M18 6L6 18M6 6l12 12" stroke="red" stroke-width="5" stroke-linecap="round" />
          </svg>
          </button>
        
        <div class="lightbox-content">
            <img id="lightbox-img" src="" alt="Zoom">
            <div id="lightbox-info" class="lightbox-info"></div>
            <div id="lightbox-date" class="lightbox-date"></div>
         </div>
          
          <button class="nav-btn prev" onclick="prevImage(event)" aria-label="Precedente">
          <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="red" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 18 15 12 9 6" />
          </svg>
        </button>
    
        <button class="nav-btn next" onclick="nextImage(event)" aria-label="Successivo">
          <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="red" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6" />
          </svg>
        </button>
        
    </div>

</main>
<script>
    // Rimosso tutto il codice JS relativo ai filtri e al dropdown
</script>
<script>
    // Passa i dati al JS della lightbox
    window.images = <?php echo json_encode($records); ?>;
</script>    
<script src="galleria-lightbox.js"></script>
</body>
</html>