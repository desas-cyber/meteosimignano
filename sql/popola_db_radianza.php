<?php

// Percorso del file da importare
$filename = __DIR__ . '/siena_solar_data_2025.txt';

require_once '/home/erbielqv/envelop.php';
if (!isset($pdo)) {
    file_put_contents('log_delta.txt', "[DEBUG] ❌ Errore: \$pdo non definito\n", FILE_APPEND);
    exit("Errore: Connessione PDO non disponibile");
}

// Apertura del file
if (!file_exists($filename)) {
    die("❌ File non trovato: $filename");
}

$handle = fopen($filename, "r");
if (!$handle) {
    die("❌ Impossibile aprire il file");
}

$righeInserite = 0;

$insert = $pdo->prepare("INSERT INTO solar_data_siena (
    data_giorno, giorno_anno, energia_totale_wh_m2,
    irradianza_max_w_m2, ora_massima_utc, alba_utc, tramonto_utc
) VALUES (?, ?, ?, ?, ?, ?, ?)");

while (($line = fgets($handle)) !== false) {
    $line = trim($line);

    // Salta righe vuote o commentate
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    $fields = explode('|', $line);
    if (count($fields) === 7) {
        $insert->execute($fields);
        $righeInserite++;
    }
}

fclose($handle);
echo "✅ Importazione completata: $righeInserite righe inserite con successo.";
?>
