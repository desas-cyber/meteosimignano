
<?php

/*
- Accesso solo da terminale: Blocca l’esecuzione da browser per sicurezza.
- Connessione PDO: Usa due connessioni PDO, una per scrittura e una per lettura.
- Gestione ambiente: Mostra messaggi visivi se sei in test o produzione.
- Configurazione directory: Imposta la directory delle immagini da sincronizzare.
- Log delle operazioni: Scrive log su file per ogni esecuzione e anomalia.
- Filtra file vivi: Legge e filtra i file realmente presenti nella directory.
- Legge immagini dal database: Recupera la lista dei file già registrati.
- Estrae data/ora dai nomi file: Parsing del nome file per ottenere la data.
- Sincronizza database: Inserisce nuovi file e elimina quelli non più presenti.
- Aggiorna dati meteo: Associa dati meteo alle immagini tramite timestamp.
- Gestione errori: Logga e segnala file non conformi o errori di parsing.
- Debug opzionale: Mostra output dettagliato se la modalità debug è attiva.
- Calcolo tempo esecuzione: Misura e logga la durata dello script.
- Funzioni modulari: Ogni operazione è incapsulata in una funzione dedicata.
- Supporto ambiente test: Usa helper per gestire test locale/global


------>>>>>cli è disattivato solo per ambiente test locale<<<<<<<
if (php_sapi_name() !== "cli") {
    http_response_code(403);
    exit("Accesso negato.");
   }
    */
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    date_default_timezone_set("Europe/Rome");
    
    // === INCLUDI CONNESSIONE PDO - HELPER===
    
    require_once __DIR__ . '/../../envelop.php';// Connessione via $pdo - scrittura, lettura
    require_once __DIR__ . '/../../envelop_lettura.php'; // Connessione via $pdo - lettura
    require_once __DIR__ . '/../datetime_helper.php';// helper per ambiente test locale
    require_once __DIR__ . '/../env_tables_helper.php';   // helper per ambiente test globale

// Nome tabella corretto in base a USE_TEST_MODE da env_tables_helper.php
    $table_name_dati_meteo = table_name('dati_meteo_simignano');
    $table_name_belle = table_name('DB_immagini_belle');

    // Messaggio visivo di sicurezza
    if (is_test_mode()) {
        echo "⚠️ Sei in AMBIENTE TEST — uso tabella *_test<br>\n";
    } else {
        echo "✅ Sei in PRODUZIONE <br>\n";
    }


    
    // === CONFIGURAZIONE ===
    $directory = __DIR__ . "/../belle"; // Percorso alla directory delle immagini
    $debug = true; // 👉 Mostra output a schermo
    
    
    
    // === FUNZIONI ===
    function scriviLog($messaggio) {
        $data = get_now(); // 🔧 USA HELPER invece di date("Y-m-d H:i:s")
        $logfile = __DIR__ . "/aggiorna_log.txt";
        file_put_contents($logfile, "[$data] $messaggio" . PHP_EOL, FILE_APPEND);
    }
    
    function debugEcho($messaggio) {
        global $debug;
        if ($debug) echo $messaggio . PHP_EOL;
    }
    
    function separatoreEsecuzione() {
        $data = get_now(); // 🔧 USA HELPER invece di date("Y-m-d H:i:s")
        $logfile = __DIR__ . "/aggiorna_log.txt";
        file_put_contents($logfile, "------ ESECUZIONE: $data ------" . PHP_EOL, FILE_APPEND);
    }
    
    function filtraFileVivi($directory) {
        $file_list = scandir($directory);
        $files_vivi = [];
    
        foreach ($file_list as $file) {
            $path = "$directory/$file";
    
            if ($file !== '.' && $file !== '..' && is_file($path)) {
                $files_vivi[$file] = true;  // chiave = nome file
            }
        }
    
        return $files_vivi;
    }

    

    function leggiImmaginiDaDatabase(PDO $pdo, string $table_name_belle): array {
    $files_nel_db = []; // 🧠 Hash map: nome file => timestamp

    $sql = "SELECT FILE, DATA_ORA FROM " . $table_name_belle . "";
    $stmt = $pdo->query($sql);

    if ($stmt) {
        while ($riga = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $filename = $riga['FILE'];
            $files_nel_db[$filename] = true;
        }
    }
    // Log visibile nel browser (o nel terminale se da CLI)
    echo "📦 File nel DB: " . count($files_nel_db) . "<br>";
    return $files_nel_db;
}    


function estraiDataOraDaFilename($filename) {
    if (!preg_match('/Schedule_\d{8}-\d{6}\.jpg$/', $filename)) {
        scriviLog("⚠️ Nome file non conforme al formato atteso: $filename");
        return null;
    }

    $data_str = substr($filename, 9, 15);
    $dt = DateTime::createFromFormat('Ymd-His', $data_str);
    if (!$dt) {
        scriviLog("⚠️ Errore nel parsing data dal filename: $filename");
        return null;
    }

    return $dt->format('Y-m-d H:i:s');
}
    
 function sincronizzaDatabase(PDO $pdo, array $file_map_dir, array $file_map_db, string $table_name_belle) {
    $stmt_insert = $pdo->prepare("INSERT INTO " . $table_name_belle . " (FILE, DATA_ORA) VALUES (:file, :data_ora)");
    $stmt_delete = $pdo->prepare("DELETE FROM " . $table_name_belle . " WHERE FILE = :file");

    $inseriti = 0;
    $eliminati = 0;
    
    
    // 🧠 Se il DB è vuoto, popolalo con tutti i file della directory
    foreach ($file_map_dir as $filename => $val) {
            debugEcho("→ $filename");
        
        }
    if (empty($file_map_db)) {
        debugEcho("📥 DB vuoto, popolo con tutti i file della directory.");
        foreach (array_keys($file_map_dir) as $filename){
            $data_ora = estraiDataOraDaFilename($filename);
            if ($data_ora === null) {
            continue;
            }
            $stmt_insert->execute([
                ':file' => $filename,
                ':data_ora' => $data_ora
            ]);
            $inseriti++;
        }
        debugEcho("✅ Inseriti nel DB: $inseriti file iniziali.");
        return;
    }

    // 🔁 INSERISCI nuovi file
    foreach (array_keys($file_map_dir) as $filename)  {
        if (!isset($file_map_db[$filename])) {
            $data_ora = estraiDataOraDaFilename($filename);
            if ($data_ora === null) {
            continue;
            }
            $stmt_insert->execute([
                ':file' => $filename,
                ':data_ora' => $data_ora
            ]);
            $inseriti++;
        }
    }

    // ❌ ELIMINA file non più presenti nella directory
    foreach ($file_map_db as $filename=>$val) {
        if (!isset($file_map_dir[$filename])) {
            $stmt_delete->execute([
                ':file' => $filename
            ]);
            $eliminati++;
        }
    }

    debugEcho("✅ Inseriti nel DB: $inseriti nuovi file.");
    debugEcho("🗑️ Eliminati dal DB: $eliminati file non più presenti.");
}   
    
    function aggiornaDatiMeteo(PDO $pdo, PDO $pdo_lettura, string $table_name_belle, string $table_name_dati_meteo) {
        $sql = "SELECT ID, DATA_ORA FROM " . $table_name_belle . "
        WHERE Temp IS NULL OR HR IS NULL OR P_hPa IS NULL OR vento_kmh IS NULL OR Dir_text IS NULL";
        $records = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
        if (empty($records)) {
            scriviLog("⚠️ Nessun record da aggiornare nei dati meteo: agg_DB_belle.php");
            return;
        }
    
        $stmt_meteo = $pdo_lettura->prepare("
            SELECT temperatura_C, umidita_RH, pressione_hPa, vento_kmh, direzione_vento_deg
            FROM " . $table_name_dati_meteo . "
            WHERE ABS(TIMESTAMPDIFF(SECOND, data_ora, :data_ora)) <= 900
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, data_ora, :data_ora))
            LIMIT 1
        ");
    
        $stmt_update = $pdo->prepare("
            UPDATE " . $table_name_belle . "
            SET Temp = :temp, HR = :hr, P_hPa = :P, vento_kmh = :vento, Dir_text = :dir
            WHERE ID = :id
        ");
    
        $conteggio = 0;
        $senza_dati = 0;
    
        foreach ($records as $row) {
            $data_ora = $row['DATA_ORA'];
            $id = $row['ID'];
    
            $stmt_meteo->execute([':data_ora' => $data_ora]);
            $meteo = $stmt_meteo->fetch(PDO::FETCH_ASSOC);
    
            if ($meteo) {
                $stmt_update->execute([
                    ':temp' => $meteo['temperatura_C'],
                    ':hr' => $meteo['umidita_RH'],
                    ':P' => $meteo['pressione_hPa'],
                    ':vento' => $meteo['vento_kmh'],
                    ':dir' => $meteo['direzione_vento_deg'],
                    ':id' => $id
                ]);
                $conteggio++;
            } else {
                scriviLog("❌ Nessun dato meteo trovato per ID=$id, DATA_ORA=$data_ora");
                $senza_dati++;
            }
        }
    
        debugEcho("🌡️ Dati meteo aggiornati: $conteggio record.");
        if ($senza_dati > 0) {
            debugEcho("⚠️ $senza_dati record senza dati meteo disponibili.");
        }
    
        scriviLog("✅ Dati meteo aggiornati per $conteggio record. $senza_dati senza dati.");
    }
    
    
    
    
    // === ESECUZIONE ===
    $start = microtime(true);
    separatoreEsecuzione();
    $files_vivi = filtraFileVivi($directory);
    $files_nel_db = leggiImmaginiDaDatabase($pdo, $table_name_belle);
    sincronizzaDatabase($pdo, $files_vivi, $files_nel_db, $table_name_belle);
    aggiornaDatiMeteo($pdo, $pdo_lettura, $table_name_belle, $table_name_dati_meteo);
    $durata = round(microtime(true) - $start, 2);
    debugEcho("⏱️ Tempo di esecuzione: {$durata} secondi.");
    scriviLog("⏱️ Tempo di esecuzione script: {$durata} secondi.");

?>

