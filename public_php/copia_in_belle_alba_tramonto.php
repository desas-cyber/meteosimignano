<?php
/*
┌─────────────────────────────────────────────────────────────────────────────┐
│ CRON JOB: Copia foto Alba/Tramonto                                         │
├─────────────────────────────────────────────────────────────────────────────┤
│ ESECUZIONE: 0 23 * * * /usr/bin/php /path/to/copia_alba_tramonto.php      │                         │
│ OUTPUT: File copiati in belle/ con nome originale                          │
│ LOG: belle/cron_log.txt                                                    │
└─────────────────────────────────────────────────────────────────────────────┘
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =====================================
// DIPENDENZE
// =====================================
require_once __DIR__ . '/../datetime_helper.php';
require_once __DIR__ . '/../env_tables_helper.php';
require_once __DIR__ . '/../../envelop.php';
require_once __DIR__ . '/../../envelop_lettura.php';

// =====================================
// CONFIGURAZIONE
// =====================================
$destFolder = __DIR__ . '/../belle';
$logFile = $destFolder . '/cron_log.txt';
$sourceFolder = __DIR__ . '/../FoscamCamera_E8ABFAA799FE/snap';

// Verifica connessione database
if (!isset($pdo_lettura)) {
    error_log("[" . get_now('Y-m-d H:i:s') . "] ERRORE: PDO lettura non definito");
    exit(1);
}
if (!isset($pdo)) {
    error_log("[" . get_now('Y-m-d H:i:s') . "] ERRORE: PDO non definito");
    exit(1);
}

// Crea cartella se non esiste
if (!file_exists($destFolder)) {
    if (!mkdir($destFolder, 0755, true)) {
        error_log("[" . get_now('Y-m-d H:i:s') . "] ERRORE: Impossibile creare {$destFolder}");
        exit(1);
    }
}

// Funzione log (sovrascrive il file ad ogni esecuzione)
$scriviLog = function($msg) use ($logFile) {
    static $primaRiga = true;
    $mode = $primaRiga ? 0 : FILE_APPEND; // Prima volta sovrascrive, poi appende
    file_put_contents($logFile, "[" . get_now('Y-m-d H:i:s') . "] {$msg}\n", $mode);
    $primaRiga = false;
};

$scriviLog("========== INIZIO COPIA ==========");

try {
    // =====================================
    // QUERY DATABASE
    // =====================================
    $table_name = table_name('DB_immagini_36h');
    $table_name_bis = table_name('DB_immagini_belle');
    // Calcola 24 ore indietro
    $ventiOreIndietro = get_datetime();
    $ventiOreIndietro->modify('-24 hours');
    $ventiOreIndietroSQL = $ventiOreIndietro->format('Y-m-d H:i:s');
    $oraCorrenteSQL = get_now('Y-m-d H:i:s');
    
    $scriviLog("Finestra: {$ventiOreIndietroSQL} → {$oraCorrenteSQL}");
    
    $sql = "SELECT FILE, DATA_ORA, Temp, HR, P_hPa, vento_kmh, Dir_text, alba_tramonto 
            FROM {$table_name}
            WHERE DATA_ORA >= :venti_ore_fa
            AND DATA_ORA <= :ora_corrente
            AND alba_tramonto IN (1, 2)
            ORDER BY DATA_ORA DESC";
    
    $stmt = $pdo_lettura->prepare($sql);
    $stmt->execute([
        ':venti_ore_fa' => $ventiOreIndietroSQL,
        ':ora_corrente' => $oraCorrenteSQL
    ]);
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totaleRighe = count($rows);
    
    $scriviLog("Trovate {$totaleRighe} immagini");
    
    if ($totaleRighe === 0) {
        $scriviLog("Nessuna immagine da copiare");
        $scriviLog("========== FINE ==========\n");
        exit(0);
    }
    
    // =====================================
    // COPIA FILE
    // =====================================
    $contatori = ['alba' => 0, 'tramonto' => 0, 'errori' => 0, 'duplicati' => 0];
    
    echo "\n=== INIZIO COPIA {$totaleRighe} FILE ===\n";
    
    foreach ($rows as $row) {
        $filename = $row['FILE'];
        $dataOra = $row['DATA_ORA'];
        $temp = $row['Temp'];
        $hr = $row['HR'];
        $pHpa = $row['P_hPa'];
        $ventoKmh = $row['vento_kmh'];
        $dirText = $row['Dir_text'];
        $tipo = intval($row['alba_tramonto']);
        $tipoTesto = ($tipo === 1) ? 'alba' : 'tramonto';
        
        echo "\nProcesso: {$filename} ({$tipoTesto})\n";
        
        $sourceFile = $sourceFolder . '/' . $filename;
        
        if (!file_exists($sourceFile)) {
            $scriviLog("ERRORE: File non trovato {$filename}");
            echo "  ✗ File non trovato\n";
            $contatori['errori']++;
            continue;
        }
        
        echo "  ✓ File source esiste\n";
        
        // Mantieni nome file originale
        $destFile = $destFolder . '/' . $filename;
        
        // Verifica se file già esiste (duplicato)
        if (file_exists($destFile)) {
            $scriviLog("SKIP: Duplicato {$filename}");
            echo "  ⊗ Già esistente (skip)\n";
            $contatori['duplicati']++;
            continue;
        }
        
        echo "  → Copio file...\n";
        
        // Copia file
        if (copy($sourceFile, $destFile)) {
            echo "  ✓ File copiato\n";
            
            // Inserisci nel database belle
            try {
                echo "  → Inserisco in DB...\n";
                
                $sqlInsert = "INSERT INTO $table_name_bis (FILE, DATA_ORA, Temp, HR, P_hPa, vento_kmh, Dir_text, sun_phase) 
                              VALUES (:file, :data_ora, :temp, :hr, :p_hpa, :vento_kmh, :dir_text, :sun_phase)";
                
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([
                    ':file' => $filename,
                    ':data_ora' => $dataOra,
                    ':temp' => $temp,
                    ':hr' => $hr,
                    ':p_hpa' => $pHpa,
                    ':vento_kmh' => $ventoKmh,
                    ':dir_text' => $dirText,
                    ':sun_phase' => $tipo
                ]);
                
                echo "  ✓ Inserito in DB\n";
                echo "  ✓✓ COMPLETATO\n";
                $scriviLog("✓ {$filename} (copiato + DB inserito)");
                $contatori[$tipoTesto]++;
            } catch (PDOException $e) {
                // Se errore DB (es. duplicato), cancella file copiato
                echo "  ✗ ERRORE DB: " . $e->getMessage() . "\n";
                echo "  → Cancello file copiato\n";
                unlink($destFile);
                $scriviLog("ERRORE DB: {$filename} - " . $e->getMessage());
                $contatori['errori']++;
            }
        } else {
            echo "  ✗ ERRORE: Copia fallita\n";
            $scriviLog("ERRORE: Copia fallita {$filename}");
            $contatori['errori']++;
        }
    }
    
    echo "\n=== FINE COPIA ===\n";
    
    // =====================================
    // RIEPILOGO
    // =====================================
    echo "\nRISULTATI:\n";
    echo "  Alba copiate: {$contatori['alba']}\n";
    echo "  Tramonti copiati: {$contatori['tramonto']}\n";
    echo "  Duplicati saltati: {$contatori['duplicati']}\n";
    echo "  Errori: {$contatori['errori']}\n";
    
    $scriviLog("Alba: {$contatori['alba']}, Tramonto: {$contatori['tramonto']}, Duplicati: {$contatori['duplicati']}, Errori: {$contatori['errori']}");
    $scriviLog("========== FINE ==========\n");
    
    exit(0);
    
} catch (PDOException $e) {
    $scriviLog("ERRORE DATABASE: " . $e->getMessage());
    $scriviLog("========== FINE (ERRORE) ==========\n");
    echo "\n✗✗✗ ERRORE DATABASE: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    $scriviLog("ERRORE: " . $e->getMessage());
    $scriviLog("========== FINE (ERRORE) ==========\n");
    echo "\n✗✗✗ ERRORE: " . $e->getMessage() . "\n";
    exit(1);
}
?>