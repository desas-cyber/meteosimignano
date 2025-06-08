<?php
// Connessione al database (assicurati che il file envelop.php esista e configuri $pdo correttamente)
require_once '/home/erbielqv/envelop.php';


// --- FUNZIONE: Salva dati su file TXT (backup/log) ---
function saveDataToFile($data) {
    $file = 'dati_temperatura_test_DB_simignano.txt';
    //file_put_contents($file, "[".date("Y-m-d H:i:s")."] $data\n", FILE_APPEND);
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
    $temperatura = $valori[2];
    $umidita = $valori[3];
    $punto_rugiada = $valori[4];
    $pressione = $valori[5];
    $vento = $valori[6];
    $direzione_vento = $valori[7];
    $radianza = $valori[8];
    //$radianza_24h = $valori[9];
    
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
                (float)$temperatura,
                (float)$umidita,
                (float)$punto_rugiada,
                (float)$pressione,
                (float)$vento,
                $direzione_vento,
                (float)$radianza
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