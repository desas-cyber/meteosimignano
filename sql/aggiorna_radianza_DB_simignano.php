<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/*
FILE: aggiorna_radianza.php — guida rapida per sviluppatori
1. Scopo: ricalcola e aggiorna l'integrale giornaliero di radianza (Wh/m²) per i giorni recenti.
2. Entry point: esegui lo script CLI / web; avvia transazione PDO e processa gli ultimi 5 giorni.E' eseguito ogni 5' da cronjob
3. Config: costanti LAT,LON,TZ, soglia NOISE_WM2 e MAX_DT_SEC definiscono maschera giorno/rumore e QA.
4. Dipendenze: require_once envelop.php per $pdo; usa funzioni PHP date/DateTime e date_sun_info().
5. Backup: crea tabella TEMPORARY backup_radianza prima di modificare i dati.
6. Selezione: individua i giorni con radianza_int_whm2 NULL o 0 da processare.
7. Sun times: calcola alba/tramonto per ogni giorno (date_sun_info con fallback).
8. Integrazione: per ogni record applica maschera giorno/rumore e integra con metodo trapezi (Wh/m²).
9. Aggiornamento DB: aggiorna solo record con radianza_int_whm2 NULL/0; operazioni in transazione.
10. QA e segnali: conta gap temporali > MAX_DT_SEC, segnala total fuori range, calcola completezza.
11. Reporting: stampa riepilogo giorno-per-giorno, ultimi record e statistiche globali su STDOUT.
12. Error handling: try/catch PDOException; rollback e istruzioni per ripristino dai backup in caso di errore.
13. Note operative: esegui in CLI per output leggibile; verificare permessi e che $pdo punti al DB corretto.
14. Estendibilità: parametri (periodo, soglia) facilmente modificabili; separare funzioni per test/unit.
*/

// Connessione al database (assicurati che il file envelop.php esista e configuri $pdo correttamente)
require_once __DIR__ . '/../datetime_helper.php';
require_once __DIR__ . '/../env_tables_helper.php';   // helper per ambiente test globale
// Nome tabella corretto in base a USE_TEST_MODE da env_tables_helper.php
require_once __DIR__ . '/../../envelop.php'; // Connessione via $pdo
$table_name = table_name('dati_meteo_simignano');


echo "=== CORREZIONE INTEGRALE RADIANZA - PROBLEMA 22:16 ===\n\n";

try {
    $pdo->beginTransaction();
    
    // Calcola la data di 5 giorni fa
    $data_inizio = date('Y-m-d', strtotime('-5 days'));
    echo "📅 Processando dati dal: $data_inizio ad oggi\n\n";
    
    // 1. Prima facciamo un backup dei dati esistenti
    echo "💾 Creazione backup dati esistenti...\n";
    $stmt = $pdo->prepare("
        CREATE TEMPORARY TABLE backup_radianza AS 
        SELECT id, data_ora, radianza_wm2, radianza_int_whm2 
        FROM dati_meteo_simignano 
        WHERE DATE(data_ora) >= ?
    ");
    $stmt->execute([$data_inizio]);
    echo "✅ Backup creato\n\n";
    
    // 2. Identifica i giorni da processare
    echo "📅 Identificazione giorni da processare...\n";
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(data_ora) as giorno
        FROM dati_meteo_simignano 
        WHERE DATE(data_ora) >= ?
        AND (radianza_int_whm2 IS NULL OR radianza_int_whm2 = 0)
        ORDER BY DATE(data_ora)
    ");
    $stmt->execute([$data_inizio]);
    $giorni_da_processare = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Giorni da processare: " . implode(', ', $giorni_da_processare) . "\n\n";
    
    // 3. Processa ogni giorno individualmente
    foreach ($giorni_da_processare as $giorno) {
        echo "🔄 Processando giorno: $giorno\n";
        
        // Recupera tutti i record del giorno ordinati per timestamp
        $stmt = $pdo->prepare("
            SELECT id, data_ora, radianza_wm2, radianza_int_whm2
            FROM dati_meteo_simignano 
            WHERE DATE(data_ora) = ?
            ORDER BY data_ora ASC
        ");
        $stmt->execute([$giorno]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($records)) {
            echo "   ⚠️  Nessun record trovato per $giorno\n";
            continue;
        }
        
        // Calcola l'integrale cumulativo
        $integrale_cumulativo = 0;
        $updates = 0;
        $timestamp_precedente = null;
        
        foreach ($records as $record) {
            $timestamp_corrente = strtotime($record['data_ora']);
            $radianza_corrente = (float)$record['radianza_wm2'];
            //costanti predefinite
            $NOISE_WM2   = 5.0;        // soglia rumore
            $MAX_DT_SEC  = 15 * 60;    // es. ignora gap >15 minuti
            $integrale_cumulativo = 0.0;
            $tempo_integrato_sec  = 0;
            $timestamp_precedente = null;
            $I_prev = null;
            // Se non è il primo record del giorno, calcola l'incremento
            if ($timestamp_precedente !== null && $I_prev !== null) {
                // Intervallo grezzo (in secondi)
                $t0 = $timestamp_precedente;
                $t1 = $timestamp_corrente;
                $dt = $t1 - $t0; // secondi effettivi
            
                if ($dt > 0 && $dt <= $MAX_DT_SEC) {
                    // Applica taglio rumore sia a valore precedente che corrente
                    $I0 = max(0.0, (float)$I_prev);
                    if ($I0 < $NOISE_WM2) $I0 = 0.0;
            
                    $I1 = max(0.0, (float)$radianza_corrente);
                    if ($I1 < $NOISE_WM2) $I1 = 0.0;
            
                    // --- INTEGRAZIONE A TRAPEZI ---
                    $Iavg = 0.5 * ($I0 + $I1);                  // media W/m²
                    $incremento = $Iavg * ($dt / 3600.0);       // Wh/m²
                    $integrale_cumulativo += $incremento;
            
                    // (opzionale) tieni traccia del tempo integrato valido
                    $tempo_integrato_sec += $dt;
                }
            }
            
            // Aggiorna solo se il valore è NULL
            if ($record['radianza_int_whm2'] === null) {
                $update_stmt = $pdo->prepare("
                    UPDATE dati_meteo_simignano 
                    SET radianza_int_whm2 = ? 
                    WHERE id = ?
                ");
                $update_stmt->execute([$integrale_cumulativo, $record['id']]);
                $updates++;
            }
            
            $timestamp_precedente = $timestamp_corrente;
        }
        
        echo "   ✅ Aggiornati $updates record, integrale finale: " . number_format($integrale_cumulativo, 2) . " Wh/m²\n";
    }
    
    // 4. Processa anche i giorni che potrebbero aver bisogno di un ricalcolo completo
    echo "\n🔍 Verifica giorni con possibili discontinuità...\n";
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(data_ora) as giorno
        FROM dati_meteo_simignano 
        WHERE DATE(data_ora) >= ?
        ORDER BY DATE(data_ora)
    ");
    $stmt->execute([$data_inizio]);
    $tutti_giorni = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tutti_giorni as $giorno) {
        // Verifica se ci sono discontinuità nell'integrale (versione semplificata)
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as totale,
                COUNT(radianza_int_whm2) as con_integrale,
                MIN(radianza_int_whm2) as min_integrale,
                MAX(radianza_int_whm2) as max_integrale
            FROM dati_meteo_simignano 
            WHERE DATE(data_ora) = ?
        ");
        $stmt->execute([$giorno]);
        $verifica = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verifica semplice: se il minimo non è 0 o molto vicino a 0, potrebbe esserci discontinuità
        if ($verifica['totale'] > 0 && $verifica['min_integrale'] > 100) {
            echo "   ⚠️  Possibile discontinuità in $giorno (min: {$verifica['min_integrale']} Wh/m²)\n";
        }
        
        // Verifica completezza
        $completezza = ($verifica['con_integrale'] / $verifica['totale']) * 100;
        if ($completezza < 100) {
            echo "   ⚠️  Completezza $giorno: " . number_format($completezza, 1) . "%\n";
        }
    }
    
    $pdo->commit();
    echo "\n✅ Correzione completata con successo!\n\n";
    
    // 5. Riepilogo finale e verifica continuità
    echo "=== RIEPILOGO FINALE ===\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE(data_ora) as giorno,
            COUNT(*) as totale,
            COUNT(radianza_int_whm2) as con_integrale,
            MAX(radianza_int_whm2) as max_integrale,
            AVG(radianza_wm2) as media_radianza,
            MAX(data_ora) as ultimo_record
        FROM dati_meteo_simignano 
        WHERE DATE(data_ora) >= ?
        GROUP BY DATE(data_ora)
        ORDER BY DATE(data_ora) DESC
    ");
    $stmt->execute([$data_inizio]);
    
    printf("%-12s | %-8s | %-8s | %-10s | %-10s | %-8s\n", 
           "Giorno", "Totale", "Con Int.", "Max Wh/m²", "Media W/m²", "Ultimo");
    printf("%-12s | %-8s | %-8s | %-10s | %-10s | %-8s\n", 
           "------------", "--------", "--------", "----------", "----------", "--------");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = ($row['totale'] == $row['con_integrale']) ? "✅" : "❌";
        $percentuale = ($row['con_integrale'] / $row['totale']) * 100;
        
        printf("%-12s | %8d | %8d | %10.2f | %10.1f | %8s %s (%.0f%%)\n",
               $row['giorno'],
               $row['totale'],
               $row['con_integrale'],
               $row['max_integrale'] ?? 0,
               $row['media_radianza'] ?? 0,
               substr($row['ultimo_record'], 11, 5),
               $status,
               $percentuale);
    }
    
    // 6. Mostra gli ultimi record per verifica
    echo "\n📋 ULTIMI 10 RECORD DOPO CORREZIONE:\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            data_ora,
            radianza_wm2,
            radianza_int_whm2
        FROM dati_meteo_simignano 
        WHERE DATE(data_ora) >= ?
        ORDER BY data_ora DESC 
        LIMIT 10
    ");
    $stmt->execute([$data_inizio]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = ($row['radianza_int_whm2'] !== null) ? "✅" : "❌";
        printf("%-20s | %5.1f | %8s %s\n",
               $row['data_ora'],
               $row['radianza_wm2'] ?? 0,
               $row['radianza_int_whm2'] ? sprintf("%.2f", $row['radianza_int_whm2']) : 'NULL',
               $status);
    }
    
    // 7. Statistiche finali
    echo "\n📊 STATISTICHE FINALI:\n";
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as totale_record,
            COUNT(radianza_int_whm2) as record_con_integrale,
            AVG(radianza_int_whm2) as media_integrale,
            MAX(radianza_int_whm2) as max_integrale_globale
        FROM dati_meteo_simignano 
        WHERE DATE(data_ora) >= ?
    ");
    $stmt->execute([$data_inizio]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Protezione divisione per zero
$totale = (int)($stats['totale_record'] ?? 0);
$con_int = (int)($stats['record_con_integrale'] ?? 0);

if ($totale === 0) {
    $completezza = null; // oppure 0, a seconda delle preferenze
} else {
    $completezza = ($con_int / $totale) * 100;
}

// Stampa sicura
echo "Record totali: " . $totale . "\n";
echo "Record con integrale: " . $con_int . "\n";
echo "Completezza: " . ($completezza === null ? 'N/A' : number_format($completezza, 1) . "%") . "\n";
echo "Media integrale: " . number_format($stats['media_integrale'] ?? 0, 2) . " Wh/m²\n";
echo "Max integrale globale: " . number_format($stats['max_integrale_globale'] ?? 0, 2) . " Wh/m²\n";
echo "TEMPO INTEGRATO SECONDI: " . ($tempo_integrato_sec ?? 0) . " sec\n";
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
        echo "❌ Transazione annullata a causa di errore\n";
    }
    echo "❌ Errore durante la correzione: " . $e->getMessage() . "\n";
    
    // In caso di errore, mostra come ripristinare dal backup
    echo "\n🔧 Per ripristinare i dati originali, esegui:\n";
    echo "UPDATE dati_meteo_simignano d1 \n";
    echo "JOIN backup_radianza b ON d1.id = b.id \n";
    echo "SET d1.radianza_int_whm2 = b.radianza_int_whm2;\n";
}
?>