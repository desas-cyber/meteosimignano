<?php
// 1. Connessione al database
require_once __DIR__ . '/../envelop.php';

// 2. Percorsi
$directory = __DIR__ . '/FoscamCamera_E8ABFAA799FE/snap/';
$webPath = '/FoscamCamera_E8ABFAA799FE/snap/';

// 3. Legge le immagini
$images = glob($directory . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
rsort($images); // Ordina dalla più recente

$records = [];

// 4. Costruisce array con dati da DB
foreach ($images as $image) {
    $nome_file = basename($image);
    $stmt = $pdo->prepare("SELECT * FROM DB_immagini_36h WHERE FILE = :file LIMIT 1");
    $stmt->execute(['file' => $nome_file]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $records[] = [
        'src' => $webPath . $nome_file,
        'data_ora' => isset($row['DATA_ORA'])
            ? (new DateTime($row['DATA_ORA']))->format('d/m/Y H:i')
            : null,
        'temp' => $row['Temp'] ?? null,
        'hr' => $row['HR'] ?? null,
        'p_hpa' => $row['P_hPa'] ?? null,
        'vento' => $row['vento_kmh'] ?? null,
        'dir' => $row['Dir_text'] ?? null 
    ];
}

// 5. Output JSON
header('Content-Type: application/json');
echo json_encode($records);