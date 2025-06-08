<?php
// Connessione al database (assicurati che il file envelop.php esista e configuri $pdo correttamente)

require_once '/home/erbielqv/envelop.php';
require_once 'aggiornaCartellaImmagini.php';

$directory = 'belle/';
$table_name = 'DB_immagini_belle';
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
        <meta name="viewport" content="width=device-width, initial-scale=1.08">
        <title>Simmignano_meteo_belle</title>
        
    <link rel="stylesheet" href="galleria-lightbox.css">
    <style>
    
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
            margin: 30;
        }
        
        /* Media query per limitare la dimensione massima del font */
        @media (min-width: 600px) {
            .sub-title {
                font-size: 30px; /* Imposta una dimensione fissa per schermi più grandi */
            }
        }
    
    
        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center; /* Centra gli elementi orizzontalmente */
            
        }
        
    </style>
        
</head>
<body>

<header style="width: 100%; text-align: center; padding: 10px; box-sizing: border-box;">
    <h1 class="main-title">MeteoSimignano</h1>
    <h1 class="sub-title">43°17′32.5″N 11°10′01.49″E @ 418m slm</h1>
</header>

<main>
    
    <button class="button" onclick="goToPage()">Home</button>

    <script>
        function goToPage() {
            window.location.href = 'index.php'; 
            window.close(); // Tenta di chiudere la finestra corrente
        }
    </script>

    
    
    <h2 class="gallery-title">Selezione di cieli</h2>

    <div class="gallery">
        <?php foreach($images as $index => $image): ?>
            <img src="<?php echo htmlspecialchars($image); ?>" alt="Immagine" onclick="openLightbox(<?php echo $index; ?>)">
        <?php endforeach; ?>
    </div>
    

    <div class="lightbox" id="lightbox">
        
        <button id="close-btn" class="lightbox-close" aria-label="Chiudi">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="red">
            <path d="M18 6L6 18M6 6l12 12" stroke="red" stroke-width="5" stroke-linecap="round" />
          </svg>
          </button>
          <!-- Elimino perchè rewind non è applicabile a questa galleria
        
        <button id="rewind-btn" class="lightbox-rewind" aria-label="Rewind/Pausa">
          <svg id="rewind-icon" viewBox="0 0 24 24" width="32" height="32" fill="red" stroke-width="5" >
            <path d="M13 12L4 6V18L13 12Z" />
            <path d="M20 12L11 6V18L20 12Z" />
          </svg>
        </button>   
        -->
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
    window.images = <?php echo json_encode($records); ?>;
</script>    
<script src="galleria-lightbox.js"></script>
<script>
      document.addEventListener('galleryUpdated', e => {
      // sovrascrivo il global images
      window.images = e.detail;
    
      // → LOG per verifica in console
      console.log('💡 Lightbox array aggiornato:', window.images);
    
      if (!window.images.length) return;
    
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
<!--<script src="aggiorna_galleria.js"></script>-->
</body>
</html>
