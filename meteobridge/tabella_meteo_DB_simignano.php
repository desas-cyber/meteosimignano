<?php
// Connessione al database (assicurati che il file envelop.php esista e configuri $pdo correttamente)
require_once '/home/erbielqv/envelop.php';


// --- FUNZIONE: Salva dati su file TXT (backup/log) ---
function saveDataToFile($data) {
    $file = 'dati_temperatura_test_DB_simignano.txt';
    //file_put_contents($file, "[".date("Y-m-d H:i:s")."] $data\n", FILE_APPEND);
}

// --- FUNZIONE: Converte valore in float o NULL se è "--" ---
function parseValue($value) {
    $value = trim($value);
    
    // Controlla se il valore inizia con "--" (es: "--°C", "--hPa", "-- W/m²")
    if (strpos($value, '--') === 0 || $value === '' || $value === 'NULL') {
        return null;
    }
    
    // Estrae solo il numero dal valore (rimuove unità di misura)
    // Es: "23.5°C" diventa "23.5", "1013.2 hPa" diventa "1013.2"
    preg_match('/^(-?\d+\.?\d*)/', $value, $matches);
    
    if (isset($matches[1])) {
        return (float)$matches[1];
    }
    
    return null; // Se non trova numeri, restituisce NULL
}

// --- FUNZIONE: Converte pressione con controllo < 500 ---
function parsePressure($value) {
    $result = parseValue($value);
    
    // Se il valore è valido ma < 500, restituisce NULL
    if ($result !== null && $result < 500) {
        return null;
    }
    
    return $result;
}

// --- VERIFICA SE È PRESENTE 'temp' ---
if (isset($_GET['temp'])) {
    $data_string = $_GET['temp'];
    saveDataToFile($data_string); // salvataggio raw per sicurezza

    // Suddividi i dati separati da virgole
    $valori = array_map('trim', explode(',', $data_string));

    // Converte da "DD/MM/YYYY HH:MM" a "YYYY-MM-DD HH:MM:00"
    $dt = DateTime::createFromFormat('d/m/Y H:i', $valori[0] . ' ' . $valori[1]);
    $data_ora = $dt ? $dt->format('Y-m-d H:i:s') : null;
    
    // Usa la funzione parseValue per gestire i valori "--"
    $temperatura = parseValue($valori[2]);
    $umidita = parseValue($valori[3]);
    $punto_rugiada = parseValue($valori[4]);
    $pressione = parsePressure($valori[5]); // Usa parsePressure per la pressione
    $vento = parseValue($valori[6]);
    $direzione_vento = parseValue($valori[7]);
    $radianza = parseValue($valori[8]);
    //$radianza_24h = parseValue($valori[9]);
    
        try {
            // Connessione al database
            $conn = $pdo;
            

            // Query SQL di inserimento
            $sql = "INSERT INTO dati_meteo_simignano (
                        data_ora, temperatura_C, umidita_RH, dew_point_C, pressione_hPa,
                        vento_kmh, direzione_vento_deg, radianza_wm2
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data_ora,
                $temperatura,      // Ora può essere NULL
                $umidita,         // Ora può essere NULL
                $punto_rugiada,   // Ora può essere NULL
                $pressione,       // Ora può essere NULL
                $vento,           // Ora può essere NULL
                $direzione_vento, // Ora può essere NULL
                $radianza         // Ora può essere NULL
            ]);

            // Log di conferma nel file
            saveDataToFile("✅ Inserimento DB riuscito");

        } catch (PDOException $e) {
            saveDataToFile("❌ Errore DB: " . $e->getMessage());
        }

} else {
    saveDataToFile("❌ Nessun parametro 'temp' ricevuto.");
}
?>