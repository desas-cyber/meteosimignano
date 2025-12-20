<?php
/*
FILE: tabella_meteo_DB_simignano.php — guida rapida per sviluppatori
1. Scopneo: riceve dati meteo via GET['temp'] e inserisce un record nella tabella dati_meteo_simignano.
2. Entry point: il blocco if (isset($_GET['temp'])) elabora la stringa CSV e lancia l'inserimento.
3. Formato input: CSV con almeno data(DD/MM/YYYY), ora(HH:MM) e campi meteo; indici mappati nel codice.
4. Parsing/date: DateTime::createFromFormat() converte "DD/MM/YYYY HH:MM" in "YYYY-MM-DD HH:MM:00".
5. Funzioni principali:
   - parseValue($v): normalizza "--" / stringhe e ritorna float|null.
   - parsePressure($v): come parseValue ma filtra pressioni < 500.
   - saveDataToFile($s): backup/log su file (decommentare per debug).
6. DB: usa $pdo (incluso da ../../envelop.php) e table_name() da env_tables_helper.php.
7. Query: INSERT prepared statement sui campi principali (data_ora, temperatura_C, umidita_RH, dew_point_C, pressione_hPa, vento_kmh, direzione_vento_deg, radianza_wm2).
8. Logging: saveDataToFile() è punto centrale per debug e audit; abilitarlo temporaneamente per tracciare input/errore.
9. Error handling: operazioni DB dentro try/catch PDOException; errori registrati via saveDataToFile().
10. Sicurezza: sanitizzare e validare $_GET['temp'] (numero campi, formati, range) prima della produzione.
11. Dipendenze esterne: ../datetime_helper.php, ../env_tables_helper.php, ../../envelop.php — verificare percorsi e che $pdo sia definito.
12. Note operative: usare __DIR__ per path, controllare permessi cartella webserver, testare chiamate dirette via browser con ?temp=... per debug.
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//connessione sicura via GET a meteobridge
$TOKEN = require __DIR__ . '/../../token.php';

if (!isset($_GET['token']) || $_GET['token'] !== $TOKEN) {
    http_response_code(403);
    exit("ACCESSO NEGATO");
}

// Connessione al database (assicurati che il file envelop.php esista e configuri $pdo correttamente)
require_once __DIR__ . '/../datetime_helper.php';
require_once __DIR__ . '/../env_tables_helper.php';   // helper per ambiente test globale
// Nome tabella corretto in base a USE_TEST_MODE da env_tables_helper.php
require_once __DIR__ . '/../../envelop.php'; // Connessione via $pdo
$table_name = table_name('dati_meteo_simignano');


// --- FUNZIONE: Salva dati su file TXT (backup/log) ---
function saveDataToFile($data) {
    $file = 'dati_temperatura_DB_simignano.txt';
    //file_put_contents($file, "[".get_now("Y-m-d H:i:s")."] $data\n", FILE_APPEND);
}

// --- FUNZIONE: Converte valore in  NULL se è "--" oppur ""
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
            $sql = "INSERT INTO $table_name (
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