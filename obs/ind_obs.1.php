<?php
// Imposta il percorso della cartella delle immagini
$directory = 'FoscamCamera_E8ABFAA799FE/snap/'; // Cambia questo con il tuo percorso
$images = glob($directory . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);

// Ordina le immagini in modo che l'ultima immagine sia all'ultima posizione
rsort($images); // ordina in ordine decrescente


$mainImage = !empty($images) ? $images[0] : ''; // Se ci sono immagini, prendi la prima come immagine principale


// Funzione per ottenere il nome del file senza estensione e la data dal nome
function getFileNameAndDate($imagePath) {
    // Ottieni solo il nome del file senza il percorso
    $fileName = basename($imagePath);
    
    // Rimuovi l'estensione del file
    $formattedName = pathinfo($fileName, PATHINFO_FILENAME);
    
    // Supponiamo che la data sia nel formato 'YYYYMMDD-HHMMSS' nel nome del file
    $matches = [];
    preg_match('/(\d{8})-(\d{6})/', $formattedName, $matches);
    
    // Estrai e formatta la data
    if (!empty($matches[1]) && !empty($matches[2])) {
        $dateString = DateTime::createFromFormat('Ymd-His', $matches[0]);
        $formattedDate = $dateString->format('d/m/Y'); // Formato: 25/03/2025
        $formattedTime = $dateString->format('H:i'); // Formato: ore:minuti
        
        return [$formattedName, "{$formattedDate} {$formattedTime}"];
    }

    return [$formattedName, 'Data non disponibile'];
}

list($mainImageName, $mainImageDate) = $mainImage ? getFileNameAndDate($mainImage) : ['Nome immagine non disponibile', 'Data non disponibile'];
?>


<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.1">
    <title>Simmignano_meteo_sequenza</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center; /* Centra gli elementi orizzontalmente */
            
        }
        .main-container {
            display: flex; /* Permette di affiancare l'immagine e il testo */
            align-items: center; /* Allinea verticalmente al centro */
            margin-bottom: 20px; /* Spazio sotto l'immagine principale */
        }
        .main-image {
            width: 100%; /* Occupa il 100% della larghezza della pagina */
            max-width: 180px; /* Limita la larghezza massima dell'immagine principale */
            margin-bottom: 20px; /* Spazio sotto l'immagine principale */
        }
        .gallery {
            display: flex;
            flex-direction: column;
            max-height: 80vh; /* Limita l'altezza per la galleria delle immagini */
            overflow-y: auto; /* Abilita lo scorrimento verticale */
            width: 100%; /* Galleria occupa la stessa larghezza */
            max-width: 1000px; /* Limita la larghezza massima della galleria */
        }
        .gallery img {
            width: 100%; /* Larghezza al 100% della galleria */
            margin-bottom: 10px; /* Spazio tra le immagini */
            cursor: pointer;
            border: 4px solid #ddd; /* Aggiunge bordo */
            border-radius: 8px; /* Arrotonda gli angoli */
            transition: opacity 0.2s ease; /* Transizione per effetto hover */
        }
        .gallery img:hover {
            opacity: 0.8; /* Effetto hover sulle immagini */
        }
         .date-text {
            font-size: 20px; /* La dimensione del font si adatta al 5% della larghezza della viewport */
            font-weight: normal; /* Imposta il font non in grassetto */;
            margin: 0; /* Rimuove margini di default */
            text-align: center; /* Centra il testo */
            //white-space: nowrap; /* Impedisce al testo di andare a capo */
            overflow: hidden; /* Nasconde il testo che esce dal contenitore */
            text-overflow: ellipsis; /* Aggiunge "..." per indicare che il testo è stato troncato */
            max-width: 100%; /* Imposta la larghezza massima al 100% del contenitore */
            margin-left: 20px; /* Aggiungi uno spazio a sinistra */
            display: inline-block; /* Necessario per far funzionare overflow/ellipsis */
        }
    </style>
</head>
<body>

<header style="width: 100%; text-align: center; padding: 10px; box-sizing: border-box;">
    <h1 style="font-size: 5vw;  white-space: nowrap; margin: 0;">Simignano_dirNO_ultime24h_freq20min</h1>
    <h1 style="font-size: 5vw; font-weight: normal; /* Imposta il font non in grassetto */;white-space: nowrap; margin: 0;">43°17′32.5″N 11°10′01.49″E --  418 m slm</h1>
</header>

<main>
    <div class="main-container">
        <?php if ($mainImage): ?>
            <img src="<?php echo $mainImage; ?>" alt="Immagine principale" class="main-image">
            <h2 class="date-text">  Ultima immagine: <?php echo $mainImageDate; ?></h2>
        <?php else: ?>
            <p>Nessuna immagine trovata nella cartella specificata.</p>
        <?php endif; ?>
    </div>


    <div class="gallery">
        <?php foreach($images as $image): ?>
            <a href="<?php echo $image; ?>" target="_blank">
                <img src="<?php echo $image; ?>" alt="Immagine">
            </a>
        <?php endforeach; ?>
    </div>
</main>
<h1>Elimina immagini pi&ugrave; vecchie di 24 ore</h1>
<form action="public_php/elimina_mag_24h.php" method="post"><button type="submit">Esegui</button></form>

</body>
</html>
