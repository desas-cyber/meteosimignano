<?php
$websiteRoot = dirname(__FILE__, 2); // Assumendo che questo file sia inl public_html
$directory = $websiteRoot . '/FoscamCamera_E8ABFAA799FE/snap/';
$images = glob($directory . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
rsort($images);
/*print_r($images);*/
// Imposta l'header per restituire JSON
header('Content-Type: application/json');

// Crea la risposta JSON

$relativeImages = [];
foreach ($images as $image) {
    $relativeImages[] = str_replace($websiteRoot, '', $image);
}


    $response = [
    "images" => $relativeImages
];

// Codifica la risposta come JSON e restituiscila
echo json_encode($response);
exit; // Termina l'esecuzione dopo aver inviato il JSON

?>

