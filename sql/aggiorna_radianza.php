<?php
declare(strict_types=1);

// =================== CONFIG SITO / QA ===================
date_default_timezone_set("Europe/Rome");

const LAT = 43.3188;               // Siena
const LON = 11.3308;               // Siena
const TZ  = 'Europe/Rome';

$NOISE_WM2  = 5.0;                 // soglia rumore: <5 W/m² -> 0
$MAX_DT_SEC = 15 * 60;             // gap > 15 min -> warning QA

require_once __DIR__ . '/../../envelop.php';// Connessione via $pdo - scrittura, lettura

echo "=== CORREZIONE INTEGRALE RADIANZA (trapezi + sunrise/sunset + noise) ===\n\n";

try {
    $pdo->beginTransaction();

    // Calcola la data di 5 giorni fa
    $data_inizio = date('Y-m-d', strtotime('-5 days'));
    echo "📅 Processando dati dal: $data_inizio ad oggi (timezone: " . TZ . ")\n\n";

    // 1) BACKUP TEMPORANEO
    echo "💾 Creazione backup dati esistenti...\n";
    $stmt = $pdo->prepare("
        CREATE TEMPORARY TABLE backup_radianza AS 
        SELECT id, data_ora, radianza_wm2, radianza_int_whm2 
        FROM dati_meteo_simignano 
        WHERE DATE(data_ora) >= ?
    ");
    $stmt->execute([$data_inizio]);
    echo "✅ Backup creato\n\n";

    // 2) GIORNI DA PROCESSARE (null o 0)
    echo "📅 Identificazione giorni da processare...\n";
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(data_ora) AS giorno
        FROM dati_meteo_simignano 
        WHERE DATE(data_ora) >= ?
          AND (radianza_int_whm2 IS NULL OR radianza_int_whm2 = 0)
        ORDER BY 1
    ");
    $stmt->execute([$data_inizio]);
    $giorni_da_processare = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Giorni da processare: " . (empty($giorni_da_processare) ? "(nessuno)" : implode(', ', $giorni_da_processare)) . "\n\n";

    // === Helper: alba / tramonto (timestamp) per un dato giorno ===
    $getSunTimes = function (string $giorno): array {
        $tz = new DateTimeZone(TZ);
        $giorno_dt = new DateTime($giorno . ' 12:00:00', $tz); // mezzogiorno locale
        $sun = date_sun_info($giorno_dt->getTimestamp(), LAT, LON);
        // fallback soft se API non torna dati
        $alba_ts     = $sun['sunrise'] ?: (new DateTime($giorno . ' 06:00:00', $tz))->getTimestamp();
        $tramonto_ts = $sun['sunset']  ?: (new DateTime($giorno . ' 18:00:00', $tz))->getTimestamp();
        return [$alba_ts, $tramonto_ts];
    };

    // 3) PROCESSO GIORNO PER GIORNO
    foreach ($giorni_da_processare as $giorno) {
        echo "🔄 Giorno: $giorno\n";

        // Recupera tutti i record del giorno ordinati per timestamp
        $stmt = $pdo->prepare("
            SELECT id, data_ora, radianza_wm2, radianza_int_whm2
            FROM dati_meteo_simignano 
            WHERE DATE(data_ora) = ?
            ORDER BY data_ora ASC
        ");
        $stmt->execute([$giorno]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$records) {
            echo "   ⚠️  Nessun record trovato\n";
            continue;
        }

        // Alba/Tramonto per la maschera giorno/notte
        [$alba_ts, $tramonto_ts] = $getSunTimes($giorno);

        $integrale_cumulativo   = 0.0;   // Wh/m²
        $updates                = 0;
        $timestamp_precedente   = null;
        $I_prev                 = null;
        $gap_longhi             = 0;

        foreach ($records as $record) {
            $t = strtotime($record['data_ora']);

            // W/m² dal sensore; sanificazione
            $I = (float)$record['radianza_wm2'];
            if (!is_numeric($record['radianza_wm2'])) $I = 0.0;
            if ($I < 0) $I = 0.0;

            // Maschera notte + soglia rumore
            $is_day = ($t >= $alba_ts && $t <= $tramonto_ts);
            if (!$is_day || $I < $NOISE_WM2) {
                $I = 0.0;
            }

            // Integrazione trapezoidale (Wh/m²)
            if ($timestamp_precedente !== null && $I_prev !== null) {
                $dt = $t - $timestamp_precedente; // sec
                if ($dt > 0) {
                    if ($dt > $MAX_DT_SEC) $gap_longhi++;
                    $I_mean = 0.5 * ($I_prev + $I); // W/m²
                    $integrale_cumulativo += $I_mean * ($dt / 3600.0);
                }
            }

            // Aggiorna solo se NULL o 0 (logica originale)
            if ($record['radianza_int_whm2'] === null || (float)$record['radianza_int_whm2'] == 0.0) {
                $update_stmt = $pdo->prepare("
                    UPDATE dati_meteo_simignano 
                    SET radianza_int_whm2 = ? 
                    WHERE id = ?
                ");
                $update_stmt->execute([$integrale_cumulativo, $record['id']]);
                $updates++;
            }

            $timestamp_precedente = $t;
            $I_prev               = $I;
        }

        // Report giorno
        $kwhm2 = $integrale_cumulativo / 1000.0;
        echo "   ✅ Aggiornati $updates record | Integrale: " . number_format($integrale_cumulativo, 2) . " Wh/m² (" . number_format($kwhm2, 2) . " kWh/m²)\n";
        if ($gap_longhi > 0) {
            echo "   ⚠️  Gap > 15 min: $gap_longhi\n";
        }
        if ($integrale_cumulativo < 0 || $integrale_cumulativo > 10000) { // 0–10 kWh/m² plausibile
            echo "   ⚠️  Totale fuori range plausibile\n";
        }
    }

    // 4) Verifica semplice su tutti i giorni recenti (info)
    echo "\n🔍 Verifica giorni con possibili discontinuità...\n";
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(data_ora) as giorno
        FROM dati_meteo_simignano 
        WHERE DATE(data_ora) >= ?
        ORDER BY 1
    ");
    $stmt->execute([$data_inizio]);
    $tutti_giorni = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tutti_giorni as $giorno) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) AS totale,
                COUNT(radianza_int_whm2) AS con_integrale,
                MIN(radianza_int_whm2) AS min_integrale,
                MAX(radianza_int_whm2) AS max_integrale
            FROM dati_meteo_simignano 
            WHERE DATE(data_ora) = ?
        ");
        $stmt->execute([$giorno]);
        $ver = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ver['totale'] > 0 && $ver['min_integrale'] > 100) {
            echo "   ⚠️  Possibile discontinuità in $giorno (min: {$ver['min_integrale']} Wh/m²)\n";
        }
        $completezza = ($ver['totale'] > 0) ? (100.0 * $ver['con_integrale'] / $ver['totale']) : 0.0;
        if ($completezza < 100.0) {
            echo "   ⚠️  Completezza $giorno: " . number_format($completezza, 1) . "%\n";
        }
    }

    $pdo->commit();
    echo "\n✅ Correzione completata con successo!\n\n";

    // 5) RIEPILOGO FINALE
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
        ORDER BY 1 DESC
    ");
    $stmt->execute([$data_inizio]);

    printf("%-12s | %-8s | %-8s | %-10s | %-10s | %-8s\n", 
           "Giorno", "Totale", "Con Int.", "Max Wh/m²", "Media W/m²", "Ultimo");
    printf("%-12s | %-8s | %-8s | %-10s | %-10s | %-8s\n", 
           "------------", "--------", "--------", "----------", "----------", "--------");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = ($row['totale'] == $row['con_integrale']) ? "✅" : "❌";
        $percentuale = ($row['totale'] > 0) ? (100.0 * $row['con_integrale'] / $row['totale']) : 0.0;

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

    // 6) ULTIMI RECORD
    echo "\n📋 ULTIMI 10 RECORD DOPO CORREZIONE:\n";
    $stmt = $pdo->prepare("
        SELECT data_ora, radianza_wm2, radianza_int_whm2
        FROM dati_meteo_simignano 
        WHERE DATE(data_ora) >= ?
        ORDER BY data_ora DESC 
        LIMIT 10
    ");
    $stmt->execute([$data_inizio]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = ($row['radianza_int_whm2'] !== null) ? "✅" : "❌";
        printf("%-20s | %6.1f | %8s %s\n",
               $row['data_ora'],
               (float)($row['radianza_wm2'] ?? 0),
               $row['radianza_int_whm2'] ? sprintf("%.2f", $row['radianza_int_whm2']) : 'NULL',
               $status);
    }

    // 7) STATISTICHE FINALI
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

    $completezza = ($stats['totale_record'] > 0) ? (100.0 * $stats['record_con_integrale'] / $stats['totale_record']) : 0.0;

    echo "Record totali: " . $stats['totale_record'] . "\n";
    echo "Record con integrale: " . $stats['record_con_integrale'] . "\n";
    echo "Completezza: " . number_format($completezza, 1) . "%\n";
    echo "Media integrale: " . number_format((float)($stats['media_integrale'] ?? 0), 2) . " Wh/m²\n";
    echo "Max integrale globale: " . number_format((float)($stats['max_integrale_globale'] ?? 0), 2) . " Wh/m²\n";

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        echo "❌ Transazione annullata a causa di errore\n";
    }
    echo "❌ Errore durante la correzione: " . $e->getMessage() . "\n";

    // Ripristino dal backup (istruzioni)
    echo "\n🔧 Per ripristinare i dati originali, esegui:\n";
    echo "UPDATE dati_meteo_simignano d1 \n";
    echo "JOIN backup_radianza b ON d1.id = b.id \n";
    echo "SET d1.radianza_int_whm2 = b.radianza_int_whm2;\n";
}
