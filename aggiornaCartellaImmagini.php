<?php
function getImageDataFromFolder($pdo, $directory, $table_name) {
    // Controlla se la cartella esiste
    if (!is_dir($directory)) {
        return [
            'error' => "La cartella '$directory' non esiste.",
            'mainImage' => '',
            'records' => []
        ];
    }

    // Ottieni le immagini
    $images = glob($directory . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);

    if (!$images || count($images) === 0) {
        return [
            'error' => "Nessuna immagine trovata nella cartella '$directory'.",
            'mainImage' => '',
            'records' => []
        ];
    }

    rsort($images); // Ordina in ordine decrescente
    $records = [];

    foreach ($images as $image) {
        $nome_file = basename($image);

        try {
            $stmt = $pdo->prepare("SELECT * FROM {$table_name} WHERE FILE = :file LIMIT 1");
            $stmt->execute(['file' => $nome_file]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [
                'error' => "Errore nella query SQL: " . $e->getMessage(),
                'mainImage' => '',
                'records' => []
            ];
        }

        $records[] = [
            'src' => $image,
            'data_ora' => $row['DATA_ORA'] ?? null,
            'temp' => $row['Temp'] ?? null,
            'hr' => $row['HR'] ?? null,
            'p_hpa' => $row['P_hPa'] ?? null,
            'vento' => $row['vento_kmh'] ?? null,
            'dir' => $row['Dir_text'] ?? null
        ];
    }

    return ['records' => $records];
}

?>