
<?php
if (php_sapi_name() !== "cli") {
    http_response_code(403);
    exit("Accesso negato.");
    }
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    date_default_timezone_set("Europe/Rome");
    
    // === INCLUDI CONNESSIONE PDO ===
    require_once '/home/erbielqv/envelop.php'; // Connessione via $pdo - scrittura, lettura
    require_once '/home/erbielqv/envelop_lettura.php'; // Connessione via $pdo - lettura
    
    // === CONFIGURAZIONE ===
    $directory = "/home/erbielqv/public_html/FoscamCamera_E8ABFAA799FE/snap";
    $debug = true; // 👉 Mostra output a schermo
    $now = time();
    $threshold_sec = 128400;
    
    // === FUNZIONI ===
    function scriviLog($messaggio) {
        $data = date("Y-m-d H:i:s");
        $logfile = __DIR__ . "/aggiorna_log.txt";
        file_put_contents($logfile, "[$data] $messaggio" . PHP_EOL, FILE_APPEND);
    }
    
    function debugEcho($messaggio) {
        global $debug;
        if ($debug) echo $messaggio . PHP_EOL;
    }
    
    function separatoreEsecuzione() {
        $data = date("Y-m-d H:i:s");
        $logfile = __DIR__ . "/aggiorna_log.txt";
        file_put_contents($logfile, "------ ESECUZIONE: $data ------" . PHP_EOL, FILE_APPEND);
    }
    
    function filtraFileVivi($directory, $threshold_sec) {
    $file_list = scandir($directory);
    $now = time();
    $files_vivi = [];

    foreach ($file_list as $file) {
        $path = "$directory/$file";

        if ($file !== '.' && $file !== '..' && is_file($path)) {
            $data_ora_str = estraiDataOraDaFilename($file);
            if ($data_ora_str === null) {
                continue; // scarta file con nome non valido
            }

            $timestamp_file = strtotime($data_ora_str);
            if ($timestamp_file === false) {
                continue; // scarta se parsing fallito
            }

            $file_age = $now - $timestamp_file;

            if ($file_age <= $threshold_sec) {
                $files_vivi[$file] = true;  // oppure puoi salvare $timestamp_file se serve
            } else {
                if (unlink($path)) {
                    debugEcho("🗑️ Eliminato file troppo vecchio: $file");
                }
            }
        }
    }

    return $files_vivi;
}
    
function leggiImmaginiDaDatabase(PDO $pdo) {
    $files_nel_db = []; // 🧠 Hash map: nome file => timestamp

    $sql = "SELECT FILE, DATA_ORA FROM DB_immagini_36h_test";//*******
    $stmt = $pdo->query($sql);

    if ($stmt) {
        while ($riga = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $filename = $riga['FILE'];
            $data_ora = $riga['DATA_ORA'];


            $files_nel_db[$filename] = true;
        }
    }

    return $files_nel_db;
}    
    
 function sincronizzaDatabase(PDO $pdo, array $file_map_dir, array $file_map_db) {
    $stmt_insert = $pdo->prepare("INSERT INTO DB_immagini_36h_test (FILE, DATA_ORA) VALUES (:file, :data_ora)");//***********
    $stmt_delete = $pdo->prepare("DELETE FROM DB_immagini_36h_test WHERE FILE = :file");//*******

    $inseriti = 0;
    $eliminati = 0;
    
    // 🧠 Se il DB è vuoto, popolalo con tutti i file della directory
    if (empty($file_map_db)) {
        debugEcho("📥 DB vuoto, popolo con tutti i file della directory.");
        foreach ($file_map_dir as $filename => $val) {
            if (preg_match('/Schedule_\d{8}-\d{6}\.jpg$/', $filename)) {
            $data_str = substr($filename, 9, 15);
            $dt = DateTime::createFromFormat('Ymd-His', $data_str);
            if ($dt) {
                $data_ora = $dt->format('Y-m-d H:i:s');
            } else {
                scriviLog("⚠️ Errore nel parsing data dal filename: $filename");
                continue; // Salta l'inserimento se la data non è valida
            }
        } else {
            scriviLog("⚠️ Nome file non conforme al formato atteso: $filename");
            continue; // Salta il file se il nome non è corretto
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
    foreach ($file_map_dir as $filename => $val) {
        if (!isset($file_map_db[$filename])) {
            if (preg_match('/Schedule_\d{8}-\d{6}\.jpg$/', $filename)) {
                $data_str = substr($filename, 9, 15);
                $dt = DateTime::createFromFormat('Ymd-His', $data_str);
                if ($dt) {
                    $data_ora = $dt->format('Y-m-d H:i:s');
                } else {
                    scriviLog("⚠️ Errore nel parsing data dal filename: $filename");
                    continue; // Salta l'inserimento se la data non è valida
                }
            } else {
                scriviLog("⚠️ Nome file non conforme al formato atteso: $filename");
                continue; // Salta il file se il nome non è corretto
            }
        
            // 4. Inserisci nel DB
            $stmt_insert->execute([
                ':file' => $filename,
                ':data_ora' => $data_ora
            ]);
            $inseriti++;
        }
    }

    // ❌ ELIMINA file non più presenti nella directory
    foreach ($file_map_db as $filename => $val) {
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
    
    function aggiornaDatiMeteo(PDO $pdo, PDO $pdo_lettura) {
        $sql = "SELECT ID, DATA_ORA FROM DB_immagini_36h_test
        WHERE Temp IS NULL OR HR IS NULL OR P_hPa IS NULL OR vento_kmh IS NULL OR Dir_text IS NULL";//*******
        $records = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
        if (empty($records)) {
            scriviLog("⚠️ Nessun record da aggiornare nei dati meteo.");
            return;
        }
    
        $stmt_meteo = $pdo_lettura->prepare("
            SELECT temperatura_C, umidita_RH, pressione_hPa, vento_kmh, direzione_vento_deg
            FROM dati_meteo_simignano
            WHERE ABS(TIMESTAMPDIFF(SECOND, data_ora, :data_ora)) <= 900
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, data_ora, :data_ora))
            LIMIT 1
        ");
    
        $stmt_update = $pdo->prepare("
            UPDATE DB_immagini_36h_test 
            SET Temp = :temp, HR = :hr, P_hPa = :P, vento_kmh = :vento, Dir_text = :dir
            WHERE ID = :id
        ");//*******
    
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
    
    
    // === ESECUZIONE ===
    $start = microtime(true);
    separatoreEsecuzione();
    $files_vivi = filtraFileVivi($directory, $threshold_sec);
    $files_nel_db = leggiImmaginiDaDatabase($pdo);
    sincronizzaDatabase($pdo, $files_vivi, $files_nel_db);
    aggiornaDatiMeteo($pdo, $pdo_lettura);
    $durata = round(microtime(true) - $start, 2);
    debugEcho("⏱️ Tempo di esecuzione: {$durata} secondi.");
    scriviLog("⏱️ Tempo di esecuzione script: {$durata} secondi.");

?>

