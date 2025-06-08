
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Connessione al database (assicurati che il file envelop.php esista e configuri $pdo correttamente)

//require_once '/home/erbielqv/envelop.php'; DA USARE IN INTERNET
require_once __DIR__ . '/../envelop.php';
require_once 'aggiornaCartellaImmagini.php';

$directory = 'FoscamCamera_E8ABFAA799FE/snap/';
$table_name = 'DB_immagini_36h';
$data = getImageDataFromFolder($pdo, $directory, $table_name);

$mainImage = '';
$mainImageDate = 'Data non disponibile';
$records = [];

if (isset($data['error'])) {
    echo "<p>Errore: " . htmlspecialchars($data['error']) . "</p>";
} else {
    $records = $data['records'];
    $images = array_column($records, 'src');

    $maxTimestamp = 0;

    foreach ($records as &$rec) { // ⚠️ nota: serve il & per modificare il valore originale
            if (!empty($rec['data_ora'])) {
                $timestamp = strtotime($rec['data_ora']); // uso del formato ISO valido
                if ($timestamp !== false && $timestamp > $maxTimestamp) {
                    $maxTimestamp = $timestamp;
                    $mainImage = $rec['src'];
                    $mainImageDate = (new DateTime($rec['data_ora']))->format('d/m/Y H:i'); // uso originale prima di formattare
                }
        
                // ✅ ora che ho usato data_ora per il confronto, posso formattarla
                $rec['data_ora'] = (new DateTime($rec['data_ora']))->format('d/m/Y H:i');
            }
        }
    }
?>


<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.08, maximum-scale=5.0, user-scalable=yes">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <title>Simmignano_meteo_galleria</title>
    <link rel="stylesheet" href="galleria-lightbox.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center; /* Centra gli elementi orizzontalmente */
        }
        
       
        .main-title {
            font-size: 6vw; /* Dimensione del font che si adatta alla larghezza della finestra */
            white-space: nowrap; /* Impedisce il wrapping del testo */
            margin: 0; /* Rimuove il margine predefinito */
        }
        
        /* Media query per limitare la dimensione massima del font */
        @media (min-width: 600px) {
            .main-title {
                font-size: 55px; /* Imposta una dimensione fissa per schermi più grandi */
            }
        }

        .sub-title {
            font-size: 3vw;
            max-font-size: 25px; /* Questa proprietà non è standard */
            font-weight: normal;
            white-space: nowrap;
            margin: 10px;
        }
        
        /* Media query per limitare la dimensione massima del font */
        @media (min-width: 600px) {
            .sub-title {
                font-size: 30px; /* Imposta una dimensione fissa per schermi più grandi */
            }
        }
    
    
        
        .main-container {
              position: relative;
              display: inline-block;
            }
            
            .main-image {
              width: 100%;
              max-width: 1000px;
              display: block;
              cursor: pointer;
              /* margin-bottom non serve più, il testo è assoluto */
            }
            
            .date-text {
              position: absolute;
              bottom: 0;          /* incollata al bordo inferiore */
              left: 0;            /* parte sinistra del contenitore */
              width: 100%;        /* occupa tutta la larghezza */
              margin: 0;          /* annulla i margini predefiniti */
            
              /* centratura e controllo dimensione */
              text-align: center;
              font: normal;
              font-size: clamp(4px, 4vw, 25px);
              line-height: 1;     /* così l’altezza riga controlla l’ingombro */
              max-height: 5%;    /* ≈ un’unica riga di testo */
              overflow: hidden;     /* nasconde tutto ciò che va oltre */
            
              /* stile leggibile sul bordo foto */
              color: white;
              background: rgba(0, 0, 0, 0.5);
              padding: 4px 0;       /* solo in verticale per non alterare larghezza */
            }
        
        
      
        .tabella-meteo {
        width: 100%; /* Adatta il div alla larghezza della pagina */
        max-width: 1000px; /* Limita la larghezza massima se necessario */
        display: flex;
        justify-content: center; /* Centra orizzontalmente */
        font-size: clamp(4px, 4vw, 45px);

    }

        .tabella-meteo iframe {
            width: 100%; /* L'iframe si adatta alla larghezza del div */
            height: 300px; /* Puoi regolare l'altezza come desideri */
        }
        
    
        
    </style>
</head>
<body>
    <header style="width: 100%; text-align: center; padding: 10px; box-sizing: border-box;">
        <h1 class="main-title">MeteoSimignano</h1>
        <h1 class="sub-title">43°17′32.5″N 11°10′01.49″E @ 418m slm</h1>
    </header>

<main>
    <div class="main-container">
        <?php if ($mainImage): ?>
            <img id="main-image" src="<?php echo $mainImage; ?>" alt="Immagine principale" class="main-image" onclick="openLightbox(0)">
            <h2 class="date-text" id="main-image-date">
              Ultima immagine (dir. NO): <?php echo $mainImageDate; ?>
            </h2>
        <?php else: ?>
            <p>Nessuna immagine trovata nella cartella specificata.</p>
        <?php endif; ?>
    </div>
    
    
    <div id="tabella-meteo">
        <iframe src="tabella_home_display.php" width="100%" height="200px" frameborder="0"></iframe>
    </div>
    
    
    <div class="container">
        <h2 class="gallery-title">Galleria ultime 36 h (agg. 20 min)</h2>
        <a href="belle.php" class="button">Vai ai cieli più belli</a>
    </div>
    
    <div class="gallery">
        <?php foreach($images as $index => $image): ?>
            <img src="<?php echo htmlspecialchars($image); ?>" alt="Immagine" onclick="openLightbox(<?php echo $index; ?>)">
        <?php endforeach; ?>
    </div>
    

    <div class="lightbox" id="lightbox">
        
    <!-- Bottone Close al centro in alto -->
    <button id="close-btn" class="lightbox-control-btn lightbox-close" aria-label="Chiudi">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="red">
            <path d="M18 6L6 18M6 6l12 12" stroke="red" stroke-width="5" stroke-linecap="round" />
        </svg>
    </button>

    <!-- Bottone Rewind in alto a sinistra -->
    <button id="rewind-btn" class="lightbox-control-btn" aria-label="Rewind/Pausa">
        <span class="dot"></span>
        <svg id="rewind-icon" viewBox="0 0 24 24" width="32" height="32" fill="red" stroke-width="5">
            <path d="M11 12L20 6V18L11 12Z"/>
            <path d="M4 12L13 6V18L4 12Z"/>
        </svg>
    </button>

    <!-- Bottone Forward in alto a destra -->
    <button id="forward-btn" class="lightbox-control-btn" aria-label="forw/pausa">
        <svg id="forward-icon" viewBox="0 0 24 24" width="32" height="32" fill="red" stroke-width="5">
            <path d="M11 12L20 6V18L11 12Z"/>
            <path d="M4 12L13 6V18L4 12Z"/>
        </svg>
        <span class="dot"></span>
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



    <script>
        window.images = <?php echo json_encode($records); ?>;
    </script>    
    <script src="aggiorna_dati_meteo.js"></script>
    <script src="galleria-lightbox.js"></script>
    <script src="aggiorna_galleria.js"></script>
    <script>
      document.addEventListener('galleryUpdated', e => {
      // sovrascrivo il global images
      window.images = e.detail;
    
      // → LOG per verifica in console
      console.log('💡 Lightbox array aggiornato:', window.images);
    
      if (!window.images.length) return;
    
      // resto del tuo codice di aggiornamento…
      const primo = window.images[0];
      document.getElementById('main-image').src =
        primo.src + '?_=' + Date.now();
      document.getElementById('main-image-date').textContent =
        `Ultima immagine (dir. NO): ${primo.data_ora}`;
    
      const gallery = document.querySelector('.gallery');
      gallery.innerHTML = '';
      window.images.forEach((item, index) => {
        const img = document.createElement('img');
        img.src = item.src + '?_=' + Date.now();
        img.alt = "Immagine";
        img.onclick = () => openLightbox(index);
        gallery.appendChild(img);
      });

          updateNavButtons?.();
        });
    </script>
</body>
</html>
