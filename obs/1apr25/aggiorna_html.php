<?php
// Usa dirname(__FILE__) invece di hardcodare il percorso
$websiteRoot = dirname(__FILE__, 2); // Assumendo che questo file sia in public_html
$directory = $websiteRoot . '/FoscamCamera_E8ABFAA799FE/snap/';

// Aggiungi un controllo per assicurarti che la directory esista
if (!is_dir($directory)) {
    die("La directory specificata non esiste.");
}

$images = glob($directory . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
rsort($images);

$mainImage = !empty($images) ? $images[0] : '';

function getFileNameAndDate($imagePath) {
    $fileName = basename($imagePath);
    $formattedName = pathinfo($fileName, PATHINFO_FILENAME);
    $matches = [];
    preg_match('/(\d{8})-(\d{6})/', $formattedName, $matches);
    if (!empty($matches[1]) && !empty($matches[2])) {
        $dateString = DateTime::createFromFormat('Ymd-His', $matches[0]);
        $formattedDate = $dateString->format('d/m/Y');
        $formattedTime = $dateString->format('H:i');
        return [$formattedName, "{$formattedDate} {$formattedTime}"];
    }
    return [$formattedName, 'Data non disponibile'];
}

list($mainImageName, $mainImageDate) = $mainImage ? getFileNameAndDate($mainImage) : ['Nome immagine non disponibile', 'Data non disponibile'];

// Usa htmlspecialchars() per i percorsi delle immagini e dell'iframe
$html = '<div id="tabella-meteo"><iframe src="' . htmlspecialchars('meteobridge/MBrealtime.html') . '" width="100%" height="200px" frameborder="0"></iframe></div>';
$html .= '<div class="main-container">';
if ($mainImage) {
    $html .= '<img src="' . htmlspecialchars(str_replace($websiteRoot, '', $mainImage)) . '" alt="Immagine principale" class="main-image" onclick="openLightbox(0)">';
    $html .= '<h2 class="date-text">Ultima immagine (dir. NO): ' . htmlspecialchars($mainImageDate) . '</h2>';
} else {
    $html .= '<p>Nessuna immagine trovata nella cartella specificata.</p>';
}
$html .= '</div>';
$html .= '<div class="gallery">';
foreach ($images as $index => $image) {
    $html .= '<img src="' . htmlspecialchars(str_replace($websiteRoot, '', $image)) . '" alt="Immagine" onclick="openLightbox(' . $index . ')">';
}
$html .= '</div>';

echo $html;
?>
