<?php

/*
Script: sincronizza directory snapshot Foscam con tabella DB_immagini_36h(_test).

- Ambiente:
  Gestito tramite env_tables_helper.php (USE_TEST_MODE).  
  La funzione table_name() risolve automaticamente la tabella corretta (test o produzione).  
  Usa datetime_helper.php per tempi consistenti tra ambenti differenti.

- Dipendenze:
  * envelop.php → PDO R/W
  * envelop_lettura.php → PDO R/O
  * datetime_helper.php → get_now(), get_time(), get_strtotime()
  * env_tables_helper.php → gestione ambiente e table_name()

- Fuso orario:
  Europe/Rome.  
  Lo script NON usa direttamente time()/date(), ma SOLO get_now() e get_time() per mantenere coerenza, anche in test.

- Directory sorgente:
  ../FoscamCamera_E8ABFAA799FE/snap  
  File attesi con pattern: Schedule_YYYYMMDD-HHMMSS.jpg

- Log:
  ./aggiorna_log.txt  
  Contiene separatori di esecuzione, eliminazioni file vecchi, inserimenti/eliminazioni DB, mismatch meteo, fasi solar detection.

- Funzioni chiave:
  * filtraFileVivi()  
      - legge i file della directory,  
      - scarta nomi non conformi,  
      - conserva solo quelli entro threshold_sec (128400 s),  
      - elimina fisicamente i file troppo vecchi.

  * leggiImmaginiDaDatabase()  
      - estrae dal DB la lista FILE → hash map per confronto rapido.

  * sincronizzaDatabase()  
      - inserisce file presenti nel FS e assenti nel DB,  
      - elimina dal DB file non più presenti nel FS,  
      - se il DB è vuoto, lo popola completamente.

  * aggiornaDatiMeteo()  
      - per ogni record senza Temp/HR/P_hPa/vento/Dir_text,  
      - cerca il dato più vicino (±900s) in dati_meteo_simignano,  
      - aggiorna il record o logga assenza dati.

  * aggiornaSunPhase()  
      - determina se la foto è in finestra di alba (fase=1) o tramonto (fase=2)  
      - usa solar_data_siena (alba/tramonto UTC) + conversione in locale  
      - margini dinamici: alba +1h, tramonto −20' → +40'

  * estraiDataOraDaFilename()  
      - valida il filename e restituisce datetime Y-m-d H:i:s.

- Sicurezza:
  - Nessun nome tabella hardcoded: sempre tramite table_name().
  - Prepared statements per INSERT/DELETE/UPDATE.
  - Ignora file non conformi; log dettagliati per errori di parsing.

- Esecuzione:
  Utilizzabile da CLI, cron o via web (debug visivo).  
  Output controllato via flag $debug.  
  Log completo della durata e di tutte le operazioni rilevanti.
*/


/*if (php_sapi_name() !== "cli") {
    http_response_code(403);
    exit("Accesso negato.");
    }*/
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    date_default_timezone_set("Europe/Rome");
    
    // === INCLUDI CONNESSIONE PDO ===
    require_once __DIR__ . '/../../envelop.php';// Connessione via $pdo - scrittura, lettura
    require_once __DIR__ . '/../../envelop_lettura.php'; // Connessione via $pdo - lettura
    require_once __DIR__ . '/../datetime_helper.php';// helper per ambiente test locale
    require_once __DIR__ . '/../env_tables_helper.php';   // helper per ambiente test globale

    // Nome tabella corretto in base a USE_TEST_MODE da env_tables_helper.php
    $table_name = table_name('DB_immagini_36h');

    // Messaggio visivo di sicurezza
    if (is_test_mode()) {
        echo "⚠️ Sei in AMBIENTE TEST — uso tabella {$table_name}<br>\n";
    } else {
        echo "✅ Sei in PRODUZIONE — uso tabella {$table_name}<br>\n";
    }
    
    // === CONFIGURAZIONE ===
    $directory = __DIR__ . "/../FoscamCamera_E8ABFAA799FE/snap";
    $debug = true; // 👉 Mostra output a schermo
    $now = get_time(); // 🔧 USA HELPER invece di time()
    $threshold_sec = 128400;
    
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
    
    function filtraFileVivi($directory, $threshold_sec) {
        $file_list = scandir($directory);
        $now = get_time(); // 🔧 USA HELPER invece di time()
        $files_vivi = [];

        foreach ($file_list as $file) {
            $path = "$directory/$file";

            if ($file !== '.' && $file !== '..' && is_file($path)) {
                $data_ora_str = estraiDataOraDaFilename($file);
                if ($data_ora_str === null) {
                    continue; // scarta file con nome non valido
                }

                $timestamp_file = get_strtotime($data_ora_str); // 🔧 USA HELPER
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
    
function leggiImmaginiDaDatabase(PDO $pdo, string $table_name): array  {
    $files_nel_db = []; // 🧠 Hash map: nome file => timestamp

    $sql = "SELECT FILE, DATA_ORA FROM " . $table_name . "";
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
    
 function sincronizzaDatabase(PDO $pdo, array $file_map_dir, array $file_map_db, string $table_name) {
    $stmt_insert = $pdo->prepare("INSERT INTO " . $table_name . " (FILE, DATA_ORA) VALUES (:file, :data_ora)");
    $stmt_delete = $pdo->prepare("DELETE FROM " . $table_name . " WHERE FILE = :file");

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
    
    function aggiornaDatiMeteo(PDO $pdo, PDO $pdo_lettura, string $table_name) {
        $sql = "SELECT ID, DATA_ORA FROM " . $table_name . "
        WHERE Temp IS NULL OR HR IS NULL OR P_hPa IS NULL OR vento_kmh IS NULL OR Dir_text IS NULL";
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
            UPDATE $table_name 
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
    
function aggiornaSunPhase(PDO $pdo, PDO $pdo_lettura, string $table_name) {
    $sql = "SELECT ID, DATA_ORA FROM " . $table_name . " WHERE alba_tramonto IS NULL";
    $records = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    if (empty($records)) {
        scriviLog("⚠️ Nessun record da verificare per sun_phase.");
        return;
    }

    debugEcho("🌅 Verifico sun_phase per " . count($records) . " record...");

    $stmt_solar = $pdo_lettura->prepare("
        SELECT alba_utc, tramonto_utc
        FROM solar_data_siena
        WHERE giorno_anno = :giorno_anno
        LIMIT 1
    ");

    $stmt_update = $pdo->prepare("UPDATE $table_name SET alba_tramonto = :phase WHERE ID = :id");

    $albe = 0;
    $tramonti = 0;
    $senza_dati = 0;

    foreach ($records as $row) {
        $data_ora = $row['DATA_ORA'];
        $id = $row['ID'];

        try {
            $dt = new DateTime($data_ora, new DateTimeZone('Europe/Rome'));
            $giorno_anno = (int)$dt->format('z') + 1;
            
            $stmt_solar->execute([':giorno_anno' => $giorno_anno]);
            $solar = $stmt_solar->fetch(PDO::FETCH_ASSOC);

            if (!$solar || !$solar['alba_utc'] || !$solar['tramonto_utc']) {
                scriviLog("❌ Dati solari non trovati per ID=$id, giorno_anno=$giorno_anno");
                $senza_dati++;
                continue;
            }

            $date_str = $dt->format('Y-m-d');
            $alba_utc = new DateTime($date_str . ' ' . $solar['alba_utc'], new DateTimeZone('UTC'));
            $tramonto_utc = new DateTime($date_str . ' ' . $solar['tramonto_utc'], new DateTimeZone('UTC'));
            
            $alba_local = clone $alba_utc;
            $alba_local->setTimezone(new DateTimeZone('Europe/Rome'));
            
            $tramonto_local = clone $tramonto_utc;
            $tramonto_local->setTimezone(new DateTimeZone('Europe/Rome'));

            
            $alba_start = $alba_local->getTimestamp() - 2400;// - 40' di margine
            $alba_end = $alba_local->getTimestamp() + 2400;// 40' di margine
            
            $tramonto_start = $tramonto_local->getTimestamp() - 2400;// - 40' di margine
            $tramonto_end = $tramonto_local->getTimestamp() + 2400;// 40' di margine
            
            $photo_timestamp = $dt->getTimestamp();

            $phase = null;
            
            if ($photo_timestamp >= $alba_start && $photo_timestamp <= $alba_end) {
                $phase = 1;
                $albe++;
            } elseif ($photo_timestamp >= $tramonto_start && $photo_timestamp <= $tramonto_end) {
                $phase = 2;
                $tramonti++;
            }

            if ($phase !== null) {
                $stmt_update->execute([':phase' => $phase, ':id' => $id]);
                $fase_str = ($phase == 1) ? '🌅 Alba' : '🌇 Tramonto';
                debugEcho("$fase_str rilevato: ID=$id, DATA_ORA=$data_ora");
            }

        } catch (Exception $e) {
            scriviLog("❌ Errore ID=$id: " . $e->getMessage());
            continue;
        }
    }

    debugEcho("🌅 Trovate $albe albe e $tramonti tramonti.");
    if ($senza_dati > 0) {
        debugEcho("⚠️ $senza_dati record senza dati solari.");
    }
    echo $alba_local->format('Y-m-d H:i:s') . "\n";//debug
    echo $tramonto_local->format('Y-m-d H:i:s') . "\n";//debug
    scriviLog("✅ sun_phase: $albe albe, $tramonti tramonti. $senza_dati senza dati.");
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
    $files_nel_db = leggiImmaginiDaDatabase($pdo,$table_name);
    sincronizzaDatabase($pdo, $files_vivi, $files_nel_db, $table_name);
    aggiornaDatiMeteo($pdo, $pdo_lettura, $table_name);
    aggiornaSunPhase($pdo, $pdo_lettura, $table_name);
    $durata = round(microtime(true) - $start, 2);
    debugEcho("⏱️ Tempo di esecuzione: {$durata} secondi.");
    scriviLog("⏱️ Tempo di esecuzione script: {$durata} secondi.");
?>