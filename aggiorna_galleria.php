<?php
// 1. Connessione al database
require_once '/home/erbielqv/envelop.php';

// 2. Legge immagini dalla cartella
$directory = 'FoscamCamera_E8ABFAA799FE/snap/';// ******************
$images = glob($directory . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
rsort($images); // Ordina dalla più recente

$records = [];

// 3. Costruisce array con info da DB
foreach ($images as $image) {
    $nome_file = basename($image);
    $stmt = $pdo->prepare("SELECT * FROM DB_immagini_36h WHERE FILE = :file LIMIT 1");// ******************
    $stmt->execute(['file' => $nome_file]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $records[] = [
        'src' => $image,
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

// 4. Ritorna JSON
header('Content-Type: application/json');
echo json_encode($records);