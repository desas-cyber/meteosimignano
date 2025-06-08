<?php
require_once '/home/erbielqv/envelop.php';
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
            
            // Se non è il primo record del giorno, calcola l'incremento
            if ($timestamp_precedente !== null) {
                $delta_tempo_ore = ($timestamp_corrente - $timestamp_precedente) / 3600; // in ore
                $incremento = $radianza_corrente * $delta_tempo_ore; // Wh/m²
                $integrale_cumulativo += $incremento;
            }
            
            // Aggiorna solo se il valore è NULL o 0
            if ($record['radianza_int_whm2'] === null || $record['radianza_int_whm2'] == 0) {
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
    
    $completezza = ($stats['record_con_integrale'] / $stats['totale_record']) * 100;
    
    echo "Record totali: " . $stats['totale_record'] . "\n";
    echo "Record con integrale: " . $stats['record_con_integrale'] . "\n";
    echo "Completezza: " . number_format($completezza, 1) . "%\n";
    echo "Media integrale: " . number_format($stats['media_integrale'] ?? 0, 2) . " Wh/m²\n";
    echo "Max integrale globale: " . number_format($stats['max_integrale_globale'] ?? 0, 2) . " Wh/m²\n";
    
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