<?php

/* Impostazioni per non "sporcare" il JSON con warning/notice ------------- */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


// ====================================================================
// 1. CONFIGURAZIONE DATABASE E FUNZIONI DI TEMPO
// ====================================================================
require_once __DIR__ . '/../../envelop_lettura.php';
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
 * @return array [categoria, valore_numerico, unità]
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
 * data_inizio_gap più recente
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


    // Rimuovi il primo record (è sempre il boundary dei 7 giorni)
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

        fwrite($file_handle, "  22 → 06: " . round(($fasce_gap['22_6'] / $sec_fascia) * 100, 3) . "%\n");
        fwrite($file_handle, "  06 → 14: " . round(($fasce_gap['6_14'] / $sec_fascia) * 100, 3) . "%\n");
        fwrite($file_handle, "  14 → 22: " . round(($fasce_gap['14_22'] / $sec_fascia) * 100, 3) . "%\n\n");


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
            
            // Ordina i gap all'interno del gruppo per data più recente
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

    fwrite($file_handle, "### 2. ANOMALIE DI VALORE (Campi NULL) - Totale: $total_null_anomalies ###\n");
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
    
    // Chiudi il file
    fclose($file_handle);
    
    echo "Report generato con successo! Controlla il file: $nome_file\n";
    
    // ====================================================================
    // 4. INVIO EMAIL
    // ====================================================================
    
    // Calcola il numero della settimana corrente
    $numero_settimana = date('W'); // Formato ISO-8601: settimana dell'anno


    // Prepara l'oggetto dell'email in base ai risultati
    if ($total_gap_anomalies == 0 && $total_null_anomalies == 0) {
        $oggetto = "DATI_DB_sett{$numero_settimana}: TUTTO OK!";
    } else {
        $oggetto = "DATI_DB_sett{$numero_settimana}: GAP TEMPORALI: $total_gap_anomalies - RIGHE NULL: $total_null_anomalies";
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

?>