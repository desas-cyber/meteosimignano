
<?php
$directory = '/home/erbielqv/public_html/meteobridge/tabella_home.php';

// Controlla se il file esiste
if (!file_exists($directory)) {
    http_response_code(500);
    echo json_encode(['error' => 'Il file non esiste.']);
    exit;
}

// Emetti l'HTML per l'iframe
$html = '<div id="tabella-meteo"><iframe src="meteobridge/tabella_home.php" width="100%" height="200px" frameborder="0"></iframe></div>';

// Imposta l'header per inviare JSON
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['html' => $html]);
?>

