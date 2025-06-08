<?php
if (php_sapi_name() !== "cli") {
    http_response_code(403);
    exit("Accesso negato.");
}
// Attiva visualizzazione errori
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Connessione al database
require_once '/home/erbielqv/envelop.php';

// Percorso del file CSV
$csvFile = __DIR__ . '/backup_dati_meteo.csv';
$nuovi_dati = [];

// Trova l'ultima data salvata (se il file esiste)
$ultima_data = '2000-01-01 00:00:00';
if (file_exists($csvFile)) {
    $lines = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($lines) > 1) {
        $ultima_riga = str_getcsv(end($lines));
        $ultima_data = $ultima_riga[0]; // Assume che la prima colonna sia data_ora
    }
}

// Query: seleziona solo i dati nuovi
$stmt = $pdo->prepare("SELECT data_ora, temperatura_C, umidita_RH, dew_point_C, pressione_hPa, vento_kmh, direzione_vento_deg, radianza_wm2, radianza_24h_wm2
                       FROM dati_meteo_simignano
                       WHERE data_ora > ?
                       ORDER BY data_ora ASC");
$stmt->execute([$ultima_data]);
$nuovi_dati = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Se ci sono nuovi dati, aggiungili
if (count($nuovi_dati) > 0) {
    $handle = fopen($csvFile, file_exists($csvFile) ? 'a' : 'w');

    // Scrive intestazione solo se il file è nuovo
    if (ftell($handle) === 0) {
        fputcsv($handle, array_keys($nuovi_dati[0]));
    }

    foreach ($nuovi_dati as $riga) {
        fputcsv($handle, $riga);
    }

    fclose($handle);
    echo "Backup aggiornato con " . count($nuovi_dati) . " righe nuove.";
} else {
    echo "Nessun nuovo dato da salvare.";
}

?>