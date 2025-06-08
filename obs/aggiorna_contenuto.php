<?php
$websiteRoot = '/home/erbielqv/public_html'; 
$directory = $websiteRoot . 'FoscamCamera_E8ABFAA799FE/snap/';
$images = glob($directory . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
rsort($images); // Ordina in ordine decrescente


// Imposta l'header per restituire JSON
header('Content-Type: application/json');

// Directory contenente le immagini
$directory = 'FoscamCamera_E8ABFAA799FE/snap/';
$images = glob($directory . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
rsort($images); // Ordina in ordine decrescente

/// Crea la risposta JSON
$response = [
    "html" => '<div class="gallery">',
    "images" => $images
];

// Aggiungi le immagini al HTML
foreach ($images as $index => $image) {
    $imagePath = str_replace("\\/", "/", $image); // Rimuovi l'escape delle barre rovesciate
    $response["html"] .= '<img src="/FoscamCamera_E8ABFAA799FE/snap/' . basename(htmlspecialchars($imagePath)) . '" alt="Immagine" onclick="openLightbox(' . $index . ')">';
}

$response["html"] .= '</div>';

// Codifica la risposta come JSON e restituiscila
echo json_encode($response);


echo '<pre>';
print_r($images);
echo '</pre>';
exit; // Blocca l'esecuzione per vedere l'output

echo json_encode($response);

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
<div id="tabella-meteo">
    <iframe src="meteobridge/MBrealtime.html" width="100%" height="200px" frameborder="0"></iframe>
</div>

<div class="main-container">
    <?php if ($mainImage): ?>
        <img src="<?php echo $mainImage; ?>" alt="Immagine principale" class="main-image" onclick="openLightbox(0)">
        <h2 class="date-text">Ultima immagine (dir. NO): <?php echo $mainImageDate; ?></h2>
    <?php else: ?>
        <p>Nessuna immagine trovata nella cartella specificata.</p>
    <?php endif; ?>
</div>

<div class="gallery">
    <?php foreach ($images as $index => $image): ?>
        <img src="<?php echo htmlspecialchars($image); ?>" alt="Immagine" onclick="openLightbox(<?php echo $index; ?>)">
    <?php endforeach; ?>
</div>
?>