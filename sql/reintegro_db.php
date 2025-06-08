<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_GET['token'] !== 'abc123') {
    die("Accesso negato.");
}

require_once '/home/erbielqv/envelop.php';

$handle = fopen("simignano_reintegro_apr25.csv", "r");
if ($handle === false) {
    die("Impossibile aprire il file CSV");
}

fgetcsv($handle); // Salta intestazione

$contatore = 0;

while (($data = fgetcsv($handle)) !== false) {
    $timestamp   = $data[0];
    $temperatura = isset($data[1]) ? floatval($data[1]) : null;
    $umidita     = isset($data[2]) ? floatval($data[2]) : null;
    $pressione   = isset($data[3]) ? floatval($data[3]) : null;
    $dew_point   = isset($data[4]) ? floatval($data[4]) : null;

    if ($timestamp >= '2025-04-22 20:53:00' && $timestamp <= '2025-04-27 11:03:00') {
        $check = $pdo->prepare("SELECT COUNT(*) FROM dati_meteo_simignano WHERE data_ora = ?");
        $check->execute([$timestamp]);
        $exists = $check->fetchColumn();

        if ($exists == 0) {
            $stmt = $pdo->prepare("INSERT INTO dati_meteo_simignano 
                (data_ora, temperatura_C, umidita_RH, dew_point_C, pressione_hPa) 
                VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$timestamp, $temperatura, $umidita, $dew_point, $pressione]);
            $contatore++;
        }
    }
}

fclose($handle);
echo "Importazione completata. Righe inserite: $contatore";
?>