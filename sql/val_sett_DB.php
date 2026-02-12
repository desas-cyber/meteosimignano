<?php

/* Impostazioni per non "sporcare" il JSON con warning/notice ------------- */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


// ====================================================================
// 1. CONFIGURAZIONE DATABASE E FUNZIONI DI TEMPO
// ====================================================================
require_once __DIR__ . '/../../envelop_lettura.php';
require_once __DIR__ . '/../../envelop.php';
require_once __DIR__ . '/../env_tables_helper.php';
$table_name = table_name('dati_meteo_simignano');
require_once __DIR__ . '/../datetime_helper.php';

// Ottieni la data/ora corrente o di test
$current_time = get_now(); 

// ====================================================================
// 1.5 CONFIGURAZIONE EMAIL
// ====================================================================
$email_destinatario = "stefano.seganti@gmail.com"; 
$email_mittente = "validazione@meteosimignano.it"; 

// ====================================================================
// 1.6 DEFINISCI IL NOME DEL FILE E FUNZIONE DI FORMATTAZIONE
// ====================================================================
$nome_file = __DIR__ . "/report_settimanale.txt"; 

/**
 * Formatta l'intervallo in secondi in una stringa leggibile
 * @param int $secondi Numero di secondi
 * @return string Stringa formattata
 */
function formatta_intervallo($secondi) {
    $minuti = $secondi / 60;
    $ore = $minuti / 60;
    $giorni = $ore / 24;
    
    if ($giorni > 7) {
        return "> 7 giorni";
    } elseif ($giorni >= 1) {
        return round($giorni) . " giorni";
    } elseif ($ore >= 1) {
        return round($ore) . " ore";
    } else {
        return round($minuti) . " minuti";
    }
}

/**
 * Calcola il valore arrotondato per l'ordinamento
 * @param int $secondi Numero di secondi
 * @return array [categoria, valore_numerico, unitÃ ]
 */
function calcola_categoria_valore($secondi) {
    $minuti = $secondi / 60;
    $ore = $minuti / 60;
    $giorni = $ore / 24;
    
    if ($giorni > 7) {
        return ['oltre_7gg', 999, 'gg'];
    } elseif ($giorni >= 1) {
        return ['giorni', round($giorni), 'gg'];
    } elseif ($ore >= 1) {
        return ['ore', round($ore), 'h'];
    } else {
        return ['minuti', round($minuti), 'min'];
    }
}

/**
 * Ordinamento corretto dei gap all'interno di ogni gruppo:
 * data_inizio_gap piÃ¹ recente
 */
function ordina_gap_per_data($a, $b) {
    return strcmp($b['data_ora_inizio_gap'], $a['data_ora_inizio_gap']);
}

// ====================================================================
// 2. LOGICA QUERY SQL
// ====================================================================

// Query 1: Verifica dei Gap Temporali (> 100 sec)
$sql_gap = "
SELECT
    data_ora,
    TIMESTAMPDIFF(SECOND, COALESCE(precedente_data_ora, '1970-01-01 00:00:00'), data_ora) AS intervallo_secondi
FROM (
    SELECT
        data_ora,
        LAG(data_ora, 1) OVER (ORDER BY data_ora) AS precedente_data_ora
    FROM
        $table_name
    WHERE
        data_ora >= DATE_SUB('$current_time', INTERVAL 7 DAY) 
) AS intervalli
WHERE
    TIMESTAMPDIFF(SECOND, COALESCE(precedente_data_ora, '1970-01-01 00:00:00'), data_ora) > 100
ORDER BY intervallo_secondi DESC;
";

// Query 2: Verifica dei Dati Mancanti (Valori NULL)
$sql_null = "
SELECT
    data_ora,
    CONCAT(
        CASE WHEN temperatura_C IS NULL THEN 'Temperatura ' ELSE '' END,
        CASE WHEN pressione_hPa IS NULL THEN 'Pressione ' ELSE '' END,
        CASE WHEN vento_kmh IS NULL THEN 'Vento ' ELSE '' END
    ) AS campi_null
FROM
    $table_name
WHERE
    data_ora >= DATE_SUB('$current_time', INTERVAL 7 DAY)
    AND (
        temperatura_C IS NULL 
        OR pressione_hPa IS NULL 
        OR vento_kmh IS NULL
    )
ORDER BY data_ora;
";

// Query 3: Rilevamento Spike di Temperatura
// Spike = variazione >= 1°C nel giro di ~1 minuto che rientra entro ±0.4°C 
// del valore originale nei 2 minuti successivi
$sql_spike = "
WITH dati AS (
    SELECT
        data_ora,
        temperatura_C,
        LAG(temperatura_C, 1)  OVER (ORDER BY data_ora) AS temp_prec,
        LAG(data_ora, 1)       OVER (ORDER BY data_ora) AS data_prec,
        LEAD(temperatura_C, 1) OVER (ORDER BY data_ora) AS temp_next1,
        LEAD(data_ora, 1)      OVER (ORDER BY data_ora) AS data_next1,
        LEAD(temperatura_C, 2) OVER (ORDER BY data_ora) AS temp_next2,
        LEAD(data_ora, 2)      OVER (ORDER BY data_ora) AS data_next2
    FROM $table_name
    WHERE data_ora >= DATE_SUB('$current_time', INTERVAL 7 DAY)
      AND temperatura_C IS NOT NULL
)
SELECT
    data_ora,
    temperatura_C   AS temp_spike,
    temp_prec       AS temp_prima,
    temp_next1      AS temp_dopo1,
    temp_next2      AS temp_dopo2,
    ROUND(ABS(temperatura_C - temp_prec), 2) AS delta_spike
FROM dati
WHERE
    /* il record precedente esiste ed è entro ~2 minuti */
    temp_prec IS NOT NULL
    AND TIMESTAMPDIFF(SECOND, data_prec, data_ora) <= 120
    /* variazione >= 1°C */
    AND ABS(temperatura_C - temp_prec) >= 1.0
    /* rientro entro ±0.4°C del valore pre-spike in almeno uno dei 2 record successivi */
    AND (
        (temp_next1 IS NOT NULL
         AND TIMESTAMPDIFF(SECOND, data_ora, data_next1) <= 120
         AND ROUND(ABS(temp_next1 - temp_prec), 2) <= 0.4)
        OR
        (temp_next2 IS NOT NULL
         AND TIMESTAMPDIFF(SECOND, data_ora, data_next2) <= 240
         AND ROUND(ABS(temp_next2 - temp_prec), 2) <= 0.4)
    )
ORDER BY data_ora DESC;
";

// ====================================================================
// 3. APERTURA FILE E SCRITTURA INCREMENTALE
// ====================================================================

$file_handle = fopen($nome_file, 'w');

if ($file_handle) {
    // Scrivi intestazione
    fwrite($file_handle, "====NON RISPONDERE A QUESTA MAIL, SCRIVI A info==\n");
    fwrite($file_handle, "=================================================\n");
    fwrite($file_handle, "         REPORT SETTIMANALE DATA QUALITY\n");
    fwrite($file_handle, "data_base: " .$table_name . "\n");
    fwrite($file_handle, "=================================================\n");
    fwrite($file_handle, "Data Esecuzione: " . date("Y-m-d H:i:s") . "\n\n");
    
    // ------------------------------------
    // ESECUZIONE QUERY 1: GAP TEMPORALI
    // ------------------------------------
    $result_gap = $pdo_lettura->query($sql_gap);
    $rows_gap = $result_gap->fetchAll(PDO::FETCH_ASSOC);

    // ===============================
    // ACCUMULATORI PER PERCENTUALI
    // ===============================
    $periodo_secondi = 7 * 24 * 3600;

    $gap_secondi_totali = 0;

    // per giorno (0..6)
    $gap_per_giorno = array_fill(0, 7, 0);

    // per fasce orarie settimanali
    $fasce_gap = [
        '22_6'  => 0,
        '6_14'  => 0,
        '14_22' => 0
    ];


    // Rimuovi il primo record (Ã¨ sempre il boundary dei 7 giorni)
    if (count($rows_gap) > 0) {
        array_shift($rows_gap);
    }

    $total_gap_anomalies = count($rows_gap);
    
    

    fwrite($file_handle, "\n");
    // DETTAGLIO GAP
    if ($total_gap_anomalies === 0) {
        fwrite($file_handle, "  Nessuna interruzione del flusso dati rilevata nell'ultima settimana.\n\n");
    } else {

        // Calcola categorie e raggruppa per valore arrotondato
        $gap_raggruppati = [];
        $total_gap_anomalies = count($rows_gap);
        

        
        foreach ($rows_gap as $row) {
            $secondi = $row['intervallo_secondi'];
            
            // Accumulo totale
            $gap_secondi_totali += $secondi;

            // Giorno relativo (0 = oggi, 6 = 6 giorni fa)
            $data_gap = new DateTime($row['data_ora']);
            $diff_giorni = (int)$data_gap->diff(new DateTime($current_time))->format('%a');
            if ($diff_giorni >= 0 && $diff_giorni < 7) {
                $gap_per_giorno[$diff_giorni] += $secondi;
            }

            // Fascia oraria (basata sull'ORA DI INIZIO GAP)
            $ora = (int)$data_gap->format('H');

            if ($ora >= 22 || $ora < 6) {
                $fasce_gap['22_6'] += $secondi;
            } elseif ($ora >= 6 && $ora < 14) {
                $fasce_gap['6_14'] += $secondi;
            } else {
                $fasce_gap['14_22'] += $secondi;
            }

            // Calcolo data inizio gap
            $data_fine  = new DateTime($row['data_ora']);
            $data_inizio = clone $data_fine;
            $data_inizio->sub(new DateInterval('PT' . $secondi . 'S'));
            $row['data_ora_inizio_gap'] = $data_inizio->format('Y-m-d H:i:s');
            
            // Calcola categoria e valore
            list($categoria, $valore, $unita) = calcola_categoria_valore($secondi);
            $row['categoria'] = $categoria;
            $row['valore'] = $valore;
            $row['unita'] = $unita;
            
            // Crea chiave per raggruppamento
            $chiave = $valore . '_' . $unita;
            
            if (!isset($gap_raggruppati[$chiave])) {
                $gap_raggruppati[$chiave] = [
                    'valore' => $valore,
                    'unita' => $unita,
                    'categoria' => $categoria,
                    'gap' => []
                ];
            }
            
            $gap_raggruppati[$chiave]['gap'][] = $row;
        }


        $gap_minuti_totali = round($gap_secondi_totali / 60);
        // =====================================
        // INTESTAZIONE MAIL
        // =====================================
        fwrite(
            $file_handle,
            "### 1. ANOMALIE DI FLUSSO (Gap > 100 sec) - Totale: {$total_gap_anomalies} per un totale di minuti: {$gap_minuti_totali} ###\n\n"
        );

        // =====================================
        // PERCENTUALI GAP TEMPORALI (DOPO ACCUMULO)
        // =====================================
        fwrite($file_handle, "### 2. PERCENTUALI GAP TEMPORALI ###\n");

        // Totale settimana
        $perc_totale = round(($gap_secondi_totali / $periodo_secondi) * 100, 3);
        fwrite($file_handle, "% gap totali / periodo analizzato: {$perc_totale}%\n\n");

        // Per giorno
        fwrite($file_handle, "% gap temporali per giorno:\n");
        for ($i = 0; $i < 7; $i++) {
            $perc_giorno = round(($gap_per_giorno[$i] / 86400) * 100, 3);
            fwrite($file_handle, "  Giorno " . ($i + 1) . ": {$perc_giorno}%\n");
        }
        fwrite($file_handle, "\n");

        // Per fascia oraria (settimanale)
        fwrite($file_handle, "% gap temporali per fascia oraria (settimanale):\n");

        $sec_fascia = 8 * 3600 * 7;

        fwrite($file_handle, "  22 â†’ 06: " . round(($fasce_gap['22_6'] / $sec_fascia) * 100, 3) . "%\n");
        fwrite($file_handle, "  06 â†’ 14: " . round(($fasce_gap['6_14'] / $sec_fascia) * 100, 3) . "%\n");
        fwrite($file_handle, "  14 â†’ 22: " . round(($fasce_gap['14_22'] / $sec_fascia) * 100, 3) . "%\n\n");


        // Ordina i gruppi: prima per categoria (oltre_7gg > giorni > ore > minuti)
        // poi per valore decrescente all'interno di ogni categoria
        $ordine_categorie = ['oltre_7gg' => 0, 'giorni' => 1, 'ore' => 2, 'minuti' => 3];
        
        usort($gap_raggruppati, function($a, $b) use ($ordine_categorie) {
            // Prima per categoria
            $cat_diff = $ordine_categorie[$a['categoria']] - $ordine_categorie[$b['categoria']];
            if ($cat_diff != 0) return $cat_diff;
            
            // Poi per valore decrescente
            return $b['valore'] - $a['valore'];
        });
        
        // SCRIVI IL SOMMARIO
        fwrite($file_handle, "--- SOMMARIO GAP ---\n");
        foreach ($gap_raggruppati as $gruppo) {
            $count = count($gruppo['gap']);
            $valore = $gruppo['valore'];
            $unita = $gruppo['unita'];
            
            if ($gruppo['categoria'] == 'oltre_7gg') {
                fwrite($file_handle, "$count gap > 7 gg\n");
            } else {
                fwrite($file_handle, "$count gap $valore $unita\n");
            }
        }
        fwrite($file_handle, "\n");
        
        // SCRIVI I DETTAGLI PER OGNI GRUPPO
        fwrite($file_handle, "--- DETTAGLIO GAP ---\n");
        foreach ($gap_raggruppati as $gruppo) {
            $count = count($gruppo['gap']);
            $valore = $gruppo['valore'];
            $unita = $gruppo['unita'];
            
            if ($gruppo['categoria'] == 'oltre_7gg') {
                fwrite($file_handle, "\n*** Gap > 7 giorni ($count) ***\n");
            } else {
                fwrite($file_handle, "\n*** Gap $valore $unita ($count) ***\n");
            }
            
            // Ordina i gap all'interno del gruppo per data piÃ¹ recente
            usort($gruppo['gap'], 'ordina_gap_per_data');
            
            foreach ($gruppo['gap'] as $gap) {
                fwrite(
                    $file_handle,
                    "- Data/Ora INIZIO: " . $gap['data_ora_inizio_gap'] . 
                    " | Durata: " . formatta_intervallo($gap['intervallo_secondi']) . "\n"
                );
            }
        }
        
        fwrite($file_handle, "\n");
    }

    
    // ------------------------------------
    // ESECUZIONE QUERY 2: DATI MANCANTI (NULL)
    // ------------------------------------
    $result_null = $pdo_lettura->query($sql_null);
    $total_null_anomalies = $result_null->rowCount();

    fwrite($file_handle, "### 3. ANOMALIE DI VALORE (Campi NULL) - Totale: $total_null_anomalies ###\n");
    echo "Tot NULL: $total_null_anomalies\n";
    
    if ($total_null_anomalies > 0) {
        foreach ($result_null as $row) {
            $campi_null = trim($row['campi_null']); 
            fwrite($file_handle, "- Data/Ora: " . $row['data_ora'] . " | Campi NULL: " . $campi_null . "\n");
        }
    } else {
        fwrite($file_handle, "  Nessuna riga con valori NULL rilevata nell'ultima settimana.\n");
    }
    fwrite($file_handle, "\n");
    
    // ------------------------------------
    // ESECUZIONE QUERY 3: SPIKE DI TEMPERATURA
    // ------------------------------------
    $result_spike = $pdo_lettura->query($sql_spike);
    $rows_spike = $result_spike->fetchAll(PDO::FETCH_ASSOC);
    $total_spike_anomalies = count($rows_spike);

    fwrite($file_handle, "### 4. SPIKE DI TEMPERATURA (delta >= 1°C con rientro entro ±0.4°C) - Totale: $total_spike_anomalies ###\n");
    echo "Tot SPIKE: $total_spike_anomalies\n";
    
    if ($total_spike_anomalies > 0) {
        // Prepared statement per la correzione dello spike sul DB
        $sql_update_spike = "UPDATE $table_name SET temperatura_C = :temp WHERE data_ora = :data_ora";
        $stmt_update = $pdo->prepare($sql_update_spike);
        
        $spike_corretti = 0;
        
        foreach ($rows_spike as $row) {
            $data      = $row['data_ora'];
            $t_spike   = $row['temp_spike'];
            $t_prima   = $row['temp_prima'];
            $t_dopo1   = $row['temp_dopo1'];
            $t_dopo2   = $row['temp_dopo2'];
            $delta     = $row['delta_spike'];
            
            // Determina il valore di rientro: il primo record successivo 
            // che è rientrato entro ±0.2°C del valore pre-spike
            if ($t_dopo1 !== null && round(abs($t_dopo1 - $t_prima), 2) <= 0.4) {
                $t_rientro = $t_dopo1;
            } elseif ($t_dopo2 !== null && round(abs($t_dopo2 - $t_prima), 2) <= 0.4) {
                $t_rientro = $t_dopo2;
            } else {
                // Fallback: usa il valore pre-spike stesso
                $t_rientro = $t_prima;
            }
            
            // Calcola la media tra il valore prima dello spike e il valore di rientro
            $temp_corretta = round(($t_prima + $t_rientro) / 2, 2);
            
            // Scrivi la riga di rilevamento
            $t_dopo1_str = $t_dopo1 !== null ? $t_dopo1 : 'N/A';
            $t_dopo2_str = $t_dopo2 !== null ? $t_dopo2 : 'N/A';
            fwrite(
                $file_handle,
                "- Data/Ora: {$data} | T prima: {$t_prima}°C → Spike: {$t_spike}°C (Δ {$delta}°C) → Dopo: {$t_dopo1_str}°C / {$t_dopo2_str}°C\n"
            );
            
            // Esegui l'UPDATE sul database
            $result_update = $stmt_update->execute([
                ':temp'    => $temp_corretta,
                ':data_ora' => $data
            ]);
            
            if ($result_update) {
                $spike_corretti++;
                fwrite(
                    $file_handle,
                    "  ✔ CORRETTO: {$t_spike}°C → {$temp_corretta}°C (media tra {$t_prima}°C e {$t_rientro}°C)\n"
                );
            } else {
                fwrite(
                    $file_handle,
                    "  ✘ ERRORE nella correzione del record {$data}\n"
                );
            }
        }
        
        fwrite($file_handle, "\nSpike corretti: {$spike_corretti}/{$total_spike_anomalies}\n");
        
    } else {
        fwrite($file_handle, "  Nessuno spike di temperatura rilevato nell'ultima settimana.\n");
    }
    fwrite($file_handle, "\n");
    
    // Chiudi il file
    fclose($file_handle);
    
    echo "Report generato con successo! Controlla il file: $nome_file\n";
    
    // ====================================================================
    // 4. INVIO EMAIL
    // ====================================================================
    
    // Calcola il numero della settimana corrente
    $numero_settimana = date('W'); // Formato ISO-8601: settimana dell'anno


    // Prepara l'oggetto dell'email in base ai risultati
    if ($total_gap_anomalies == 0 && $total_null_anomalies == 0 && $total_spike_anomalies == 0) {
        $oggetto = "DATI_DB_sett{$numero_settimana}: TUTTO OK!";
    } else {
        $oggetto = "DATI_DB_sett{$numero_settimana}: GAP: $total_gap_anomalies - NULL: $total_null_anomalies - SPIKE: $total_spike_anomalies";
    }
    
    // Leggi il contenuto del file per il corpo dell'email
    $corpo_email = file_get_contents($nome_file);
    
    // Headers per l'email
    $headers = "From: $email_mittente\r\n";
    $headers .= "Reply-To: $email_mittente\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Invia l'email
    if (mail($email_destinatario, $oggetto, $corpo_email, $headers)) {
        echo "Email inviata con successo a: $email_destinatario\n";
    } else {
        echo "Errore nell'invio dell'email. Verifica la configurazione del server SMTP.\n";
    }
    
} else {
    echo "Errore: Impossibile aprire il file per la scrittura. Verifica i permessi di scrittura.\n";
}

// Chiudi la connessione
$pdo_lettura = null;
$pdo = null;

?>