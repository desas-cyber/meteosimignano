<?php

/* Impostazioni per non "sporcare" il JSON con warning/notice ------------- */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
ini_set('memory_limit', '512M');
set_time_limit(300); // 5 minuti: sufficiente anche su dataset multi-mese


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
// 1.4 IMPOSTAZIONE PERIODO TEMPORALE
// ====================================================================
//$data_inizio = '1970-01-01 00:00:00';                    // tutto il DB
//$data_inizio = '2025-08-01 00:00:00';  // da una data specifica
$data_inizio = date('Y-m-d H:i:s', strtotime($current_time . ' -7 days')); // ultimi 7 giorni (originale)

// ====================================================================
// 1.5 CONFIGURAZIONE EMAIL
// ====================================================================
$email_destinatario = "stefano.seganti@gmail.com";
$email_mittente     = "validazione@meteosimignano.it";

// ====================================================================
// 1.6 DEFINISCI IL NOME DEL FILE E FUNZIONI DI FORMATTAZIONE
// ====================================================================
$nome_file = __DIR__ . "/report_settimanale.txt";

/**
 * Formatta l'intervallo in secondi in una stringa leggibile
 */
function formatta_intervallo($secondi) {
    $minuti = $secondi / 60;
    $ore    = $minuti  / 60;
    $giorni = $ore     / 24;
    if ($giorni > 7)      return "> 7 giorni";
    elseif ($giorni >= 1) return round($giorni) . " giorni";
    elseif ($ore >= 1)    return round($ore)    . " ore";
    else                  return round($minuti) . " minuti";
}

/**
 * Calcola categoria e valore arrotondato per l'ordinamento dei gap
 */
function calcola_categoria_valore($secondi) {
    $minuti = $secondi / 60;
    $ore    = $minuti  / 60;
    $giorni = $ore     / 24;
    if ($giorni > 7)      return ['oltre_7gg', 999,          'gg'];
    elseif ($giorni >= 1) return ['giorni',    round($giorni), 'gg'];
    elseif ($ore >= 1)    return ['ore',       round($ore),    'h'];
    else                  return ['minuti',    round($minuti), 'min'];
}

/**
 * Ordinamento gap per data piu recente
 */
function ordina_gap_per_data($a, $b) {
    return strcmp($b['data_ora_inizio_gap'], $a['data_ora_inizio_gap']);
}

// ====================================================================
// BITMASK FLAG TEMPERATURA
// NULL  = non ancora processato
// 0     = processato e OK
// >0    = processato con anomalie (uno o piu bit accesi)
// ====================================================================
define('TEMP_IQR',         0x01); //   1 - outlier IQR / quartile anomalo
define('TEMP_SPIKE_STR',   0x02); //   2 - spike STRUMENTALE (rientro entro 2 min)  [Codice A]
define('TEMP_DERIVA',      0x04); //   4 - deriva persistente >=0.5C/2min per >=4 campioni
define('TEMP_PERSISTENZA', 0x08); //   8 - sensore bloccato (+-0.2C per >2h)
define('TEMP_RANGE',       0x10); //  16 - fuori range fisico (>46C o <-25C)
define('TEMP_ORTOGONALE',  0x20); //  32 - consistenza ortogonale (dp > T+0.5C)
define('TEMP_MANCANTE',    0x40); //  64 - dato mancante (NULL originale)
define('TEMP_SPIKE_AMB',   0x80); // 128 - spike AMBIENTALE (sole/calore su sensore) [Codice B]

// Alias legacy per compatibilita
define('TEMP_SPIKE', TEMP_SPIKE_STR);

// ====================================================================
// BITMASK FLAG PRESSIONE
// ====================================================================
define('PRESS_SPIKE',       0x01); //  1 - spike pressione
define('PRESS_DERIVA',      0x02); //  2 - deriva >6 hPa/1h
define('PRESS_PERSISTENZA', 0x04); //  4 - sensore bloccato >3h
define('PRESS_RANGE',       0x08); //  8 - fuori range (>1060 o <970 hPa)
define('PRESS_MANCANTE',    0x10); // 16 - dato mancante (NULL originale)

/**
 * Decodifica il flag temperatura in stringa leggibile
 */
function decodifica_flag_temp($flag) {
    if ($flag === null) return 'NON PROCESSATO';
    if ($flag === 0)    return 'OK';
    $labels = [
        TEMP_IQR         => 'IQR',
        TEMP_SPIKE_STR   => 'SPIKE_STR',
        TEMP_DERIVA      => 'DERIVA',
        TEMP_PERSISTENZA => 'PERSISTENZA',
        TEMP_RANGE       => 'FUORI_RANGE',
        TEMP_ORTOGONALE  => 'DP>T',
        TEMP_MANCANTE    => 'MANCANTE',
        TEMP_SPIKE_AMB   => 'SPIKE_AMB',
    ];
    $attivi = [];
    foreach ($labels as $bit => $label) {
        if ($flag & $bit) $attivi[] = $label;
    }
    return implode('+', $attivi);
}

/**
 * Decodifica il flag pressione in stringa leggibile
 */
function decodifica_flag_press($flag) {
    if ($flag === null) return 'NON PROCESSATO';
    if ($flag === 0)    return 'OK';
    $labels = [
        PRESS_SPIKE       => 'SPIKE',
        PRESS_DERIVA      => 'DERIVA',
        PRESS_PERSISTENZA => 'PERSISTENZA',
        PRESS_RANGE       => 'FUORI_RANGE',
        PRESS_MANCANTE    => 'MANCANTE',
    ];
    $attivi = [];
    foreach ($labels as $bit => $label) {
        if ($flag & $bit) $attivi[] = $label;
    }
    return implode('+', $attivi);
}

// ====================================================================
// FUNZIONE: Rileva spike ambientali (Codice B - v3)
//
// APPROCCIO: finestra centrata fissa di $win_min minuti.
//
// Per ogni campione centrale i:
//   - Definisce una finestra temporale di +/- $win_min/2 minuti
//   - Separa i campioni in: CENTRO (intorno al campione, +/-$core_min)
//     e BORDI (il resto della finestra, usato come contesto)
//   - Calcola T_eccesso = T[i] - media(T_bordi)
//   - Se eccesso >= soglia E forma a campanula (T[i] >= T di tutti i bordi)
//     E verifica dp: il campione e' candidato picco
//
// Gli eventi sovrapposti (stesso campione come picco) vengono deduplicati.
// La durata dell'evento e' quella del gruppo di campioni entro $win_min min
// attorno al picco che superano la soglia.
//
// Parametri:
//   $win_min    = semiampiezza finestra in minuti (default 12)
//   $core_min   = semiampiezza zona centrale in minuti (default 3)
//   $soglia_ecc = eccesso minimo T rispetto ai bordi (default 0.4C)
//   $max_dp_r   = soglia assoluta dp in C (default 1.0); scarta se dp >= min(max_dp_r, eccesso*0.8)
//
// Restituisce array di eventi con:
//   'data_ora_inizio', 'data_ora_picco', 'data_ora_fine',
//   'T_picco', 'T_base', 'eccesso', 'delta_dp', 'n_campioni', 'durata_min'
// ====================================================================
function rileva_spike_ambientali(
    array $rows,
    int   $win_min    = 12,
    int   $core_min   = 3,
    float $soglia_ecc = 0.4,
): array {
    $n      = count($rows);
    $eventi = [];

    $ts = [];
    foreach ($rows as $r) $ts[] = strtotime($r['data_ora']);

    $win_sec  = $win_min  * 60;
    $core_sec = $core_min * 60;

    // Puntatori scorrevoli per la finestra [i - win_sec, i + win_sec]
    $lo = 0; $hi = 0;

    // Insieme dei picchi gia' segnalati (evita duplicati vicini)
    $picchi_segnalati = []; // ts del picco -> true

    for ($i = 0; $i < $n; $i++) {
        if ($rows[$i]['temperatura_C'] === null) continue;
        $t_i   = $ts[$i];
        $T_i   = (float)$rows[$i]['temperatura_C'];
        $win_lo = $t_i - $win_sec;
        $win_hi = $t_i + $win_sec;

        // Aggiorna puntatore sinistro
        while ($lo < $n && $ts[$lo] < $win_lo) $lo++;
        // Aggiorna puntatore destro
        while ($hi < $n && $ts[$hi] <= $win_hi) $hi++;
        // Finestra: indici [$lo, $hi)

        // Separa bordi (fuori dal core) e core (dentro +/-core_sec)
        $bordi_T = [];
        $core_max = -INF;

        for ($k = $lo; $k < $hi; $k++) {
            if ($rows[$k]['temperatura_C'] === null) continue;
            $dt = abs($ts[$k] - $t_i);
            $Tk = (float)$rows[$k]['temperatura_C'];
            if ($dt <= $core_sec) {
                if ($Tk > $core_max) $core_max = $Tk;
            } else {
                $bordi_T[] = $Tk;
            }
        }

        // Serve almeno 4 campioni di bordo per avere un contesto affidabile
        if (count($bordi_T) < 4) continue;

        $T_base   = array_sum($bordi_T) / count($bordi_T);
        $eccesso  = $T_i - $T_base;

        // 1. Eccesso minimo
        if ($eccesso < $soglia_ecc) continue;

        // 2. Forma a campanula: il campione centrale deve essere il massimo del core
        //    e il core_max deve essere >= T di tutti i bordi
        if ($T_i < $core_max - 0.05) continue; // non e' lui il picco del core
        $T_bordi_max = max($bordi_T);
        // Range fisico Toscana: se il picco e' fuori range e' errore sensore, non spike
        if ($T_i > 46.0 || $T_i < -25.0) continue;

        if ($T_i < $T_bordi_max) continue; // un bordo e' piu' caldo: non e' campanula

        // 3. Verifica punto di rugiada
        $delta_dp = null; // verifica dp rimossa: troppo variabile

        // 4. Deduplicazione: salta se c'e' gia' un picco segnalato entro $win_sec
        $sovrapposto = false;
        foreach ($picchi_segnalati as $ts_prev => $_) {
            if (abs($t_i - $ts_prev) < $win_sec) { $sovrapposto = true; break; }
        }
        if ($sovrapposto) continue;

        $picchi_segnalati[$t_i] = true;

        // Calcola estensione dell'evento: campioni contigui al picco entro win_sec
        // che superano la soglia. Limite duro: mai oltre win_sec dal picco.
        $ev_lo = $i; $ev_hi = $i;
        // Espandi verso sinistra finche' i campioni superano soglia e sono entro win_sec
        for ($k = $i - 1; $k >= $lo; $k--) {
            if (($ts[$i] - $ts[$k]) > $win_sec) break;
            if ((float)$rows[$k]['temperatura_C'] >= $T_base + $soglia_ecc * 0.5) {
                $ev_lo = $k;
            } else {
                break; // discontinuita': fermati
            }
        }
        // Espandi verso destra
        for ($k = $i + 1; $k < $hi; $k++) {
            if (($ts[$k] - $ts[$i]) > $win_sec) break;
            if ((float)$rows[$k]['temperatura_C'] >= $T_base + $soglia_ecc * 0.5) {
                $ev_hi = $k;
            } else {
                break; // discontinuita': fermati
            }
        }

        $durata_min_ev = round(($ts[$ev_hi] - $ts[$ev_lo]) / 60);

        $eventi[] = [
            'data_ora_inizio' => $rows[$ev_lo]['data_ora'],
            'data_ora_picco'  => $rows[$i]['data_ora'],
            'data_ora_fine'   => $rows[$ev_hi]['data_ora'],
            'T_picco'         => round($T_i, 2),
            'T_base'          => round($T_base, 2),
            'eccesso'         => round($eccesso, 2),
            'delta_dp'        => $delta_dp !== null ? round($delta_dp, 2) : null,
            'n_campioni'      => $ev_hi - $ev_lo + 1,
            'durata_min'      => $durata_min_ev,
        ];
    }

    return $eventi;
}

// ====================================================================
// 2. QUERY SQL BASE
// ====================================================================

// Query 0: Reset flag del periodo analizzato
$sql_reset_flag = "
    UPDATE $table_name
    SET flag_temp = NULL, flag_press = NULL
    WHERE data_ora >= '$data_inizio'
";

// Query 1: Gap Temporali (> 100 sec)
$sql_gap = "
SELECT
    data_ora,
    TIMESTAMPDIFF(SECOND, COALESCE(precedente_data_ora, '1970-01-01 00:00:00'), data_ora) AS intervallo_secondi
FROM (
    SELECT
        data_ora,
        LAG(data_ora, 1) OVER (ORDER BY data_ora) AS precedente_data_ora
    FROM $table_name
    WHERE data_ora >= '$data_inizio'
) AS intervalli
WHERE
    TIMESTAMPDIFF(SECOND, COALESCE(precedente_data_ora, '1970-01-01 00:00:00'), data_ora) > 100
ORDER BY intervallo_secondi DESC;
";

// Query 2: Dati Mancanti (NULL)
$sql_null = "
SELECT
    data_ora,
    CONCAT(
        CASE WHEN temperatura_C IS NULL THEN 'Temperatura ' ELSE '' END,
        CASE WHEN pressione_hPa IS NULL THEN 'Pressione '  ELSE '' END,
        CASE WHEN vento_kmh     IS NULL THEN 'Vento '      ELSE '' END
    ) AS campi_null
FROM $table_name
WHERE
    data_ora >= '$data_inizio'
    AND (temperatura_C IS NULL OR pressione_hPa IS NULL OR vento_kmh IS NULL)
ORDER BY data_ora;
";

// Query 3: Spike STRUMENTALI (Codice A)
// Variazione >= 1C in ~1 min che rientra entro +-0.4C del valore pre-spike
// entro i 2 campioni successivi (al massimo ~4 minuti)
$sql_spike_str = "
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
    WHERE data_ora >= '$data_inizio'
      AND temperatura_C IS NOT NULL
      AND temperatura_C BETWEEN -25 AND 46
)
SELECT
    data_ora,
    temperatura_C   AS temp_spike,
    temp_prec       AS temp_prima,
    temp_next1      AS temp_dopo1,
    temp_next2      AS temp_dopo2,
    data_next1,
    data_next2,
    ROUND(ABS(temperatura_C - temp_prec), 2) AS delta_spike
FROM dati
WHERE
    temp_prec IS NOT NULL
    AND TIMESTAMPDIFF(SECOND, data_prec, data_ora) <= 120
    AND ABS(temperatura_C - temp_prec) >= 1.0
    AND (
        (temp_next1 IS NOT NULL
         AND TIMESTAMPDIFF(SECOND, data_ora, data_next1) <= 120
         AND ROUND(ABS(temp_next1 - temp_prec), 2) <= 0.4)
        OR
        (temp_next2 IS NOT NULL
         AND TIMESTAMPDIFF(SECOND, data_ora, data_next2) <= 240
         AND ROUND(ABS(temp_next2 - temp_prec), 2) <= 0.4)
    )
ORDER BY data_ora ASC;
";

// ====================================================================
// 3. APERTURA FILE E SCRITTURA
// ====================================================================

$file_handle = fopen($nome_file, 'w');

if (!$file_handle) {
    echo "Errore: Impossibile aprire il file per la scrittura. Verifica i permessi.\n";
    exit(1);
}

// ====================================================================
// FUNZIONE out(): scrive sul file E sul browser contemporaneamente
// ====================================================================
function out(string $testo): void {
    global $file_handle;
    fwrite($file_handle, $testo);
    $html = htmlspecialchars($testo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = preg_replace_callback('/^ +/m', function($m) {
        return str_repeat('&nbsp;', strlen($m[0]));
    }, $html);
    $html = str_replace("\n", "<br>\n", $html);
    echo $html;
    if (ob_get_level()) ob_flush();
    flush();
}

// Header HTML della pagina browser
echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8">'
   . '<title>Report Data Quality - MeteoSimignano</title>'
   . '<style>'
   . 'body{background:#0f1117;color:#c9d1d9;font-family:Consolas,"Courier New",monospace;'
   . 'font-size:0.82rem;line-height:1.6;padding:20px 30px;}'
   . '</style></head><body>'
   . '<pre style="white-space:pre-wrap;word-break:break-word;">';

// ---- INTESTAZIONE ----
out("====NON RISPONDERE A QUESTA MAIL, SCRIVI A info====\n");
out("====================================================\n");
out("          REPORT SETTIMANALE DATA QUALITY\n");
out("data_base: " . $table_name . "\n");
out("====================================================\n");
out("Data Esecuzione : " . date("Y-m-d H:i:s") . "\n");
out("Periodo analizzato: da $data_inizio\n\n");

// ====================================================================
// STEP 0: RESET FLAG
// ====================================================================
$pdo->exec($sql_reset_flag);
out("[INFO] Flag resettati a NULL per il periodo analizzato.\n\n");

// ====================================================================
// PARTE 1: REPORT COMPLETO ANOMALIE
// ====================================================================
out("####################################################\n");
out("###          PARTE 1: REPORT ANOMALIE            ###\n");
out("####################################################\n\n");

// ------------------------------------
// ANALISI 1 + 2: GAP TEMPORALI
// ------------------------------------
$result_gap  = $pdo_lettura->query($sql_gap);
$rows_gap    = $result_gap->fetchAll(PDO::FETCH_ASSOC);

$periodo_secondi    = 7 * 24 * 3600;
$gap_secondi_totali = 0;
$gap_per_giorno     = array_fill(0, 7, 0);
$fasce_gap          = ['22_6' => 0, '6_14' => 0, '14_22' => 0];

// Il primo record e' il boundary del periodo: scartalo
if (count($rows_gap) > 0) array_shift($rows_gap);

$total_gap_anomalies = count($rows_gap);

out("\n");

if ($total_gap_anomalies === 0) {
    out("### 1. ANOMALIE DI FLUSSO - Nessuna interruzione rilevata.\n\n");
} else {
    $gap_raggruppati = [];

    foreach ($rows_gap as $row) {
        $secondi = $row['intervallo_secondi'];
        $gap_secondi_totali += $secondi;

        $data_gap    = new DateTime($row['data_ora']);
        $diff_giorni = (int)$data_gap->diff(new DateTime($current_time))->format('%a');
        if ($diff_giorni >= 0 && $diff_giorni < 7) $gap_per_giorno[$diff_giorni] += $secondi;

        $ora = (int)$data_gap->format('H');
        if      ($ora >= 22 || $ora < 6)  $fasce_gap['22_6']  += $secondi;
        elseif  ($ora >= 6  && $ora < 14) $fasce_gap['6_14']  += $secondi;
        else                               $fasce_gap['14_22'] += $secondi;

        $data_fine_gap   = new DateTime($row['data_ora']);
        $data_inizio_gap = clone $data_fine_gap;
        $data_inizio_gap->sub(new DateInterval('PT' . $secondi . 'S'));
        $row['data_ora_inizio_gap'] = $data_inizio_gap->format('Y-m-d H:i:s');

        list($categoria, $valore, $unita) = calcola_categoria_valore($secondi);
        $row['categoria'] = $categoria;
        $row['valore']    = $valore;
        $row['unita']     = $unita;

        $chiave = $valore . '_' . $unita;
        if (!isset($gap_raggruppati[$chiave])) {
            $gap_raggruppati[$chiave] = [
                'valore'    => $valore,
                'unita'     => $unita,
                'categoria' => $categoria,
                'gap'       => [],
            ];
        }
        $gap_raggruppati[$chiave]['gap'][] = $row;
    }

    $gap_minuti_totali = round($gap_secondi_totali / 60);
    out(
        "### 1. ANOMALIE DI FLUSSO (Gap > 100 sec) - Totale: {$total_gap_anomalies}" .
        " | Durata totale: {$gap_minuti_totali} min ###\n\n"
    );

    // Percentuali
    out("### 2. PERCENTUALI GAP TEMPORALI ###\n");
    $perc_totale = round(($gap_secondi_totali / $periodo_secondi) * 100, 3);
    out("% gap totali / periodo analizzato: {$perc_totale}%\n\n");

    out("% gap per giorno:\n");
    for ($i = 0; $i < 7; $i++) {
        $perc_giorno = round(($gap_per_giorno[$i] / 86400) * 100, 3);
        out("  Giorno " . ($i + 1) . ": {$perc_giorno}%\n");
    }
    out("\n");

    $sec_fascia = 8 * 3600 * 7;
    out("% gap per fascia oraria (settimanale):\n");
    out("  22 -> 06: " . round(($fasce_gap['22_6']  / $sec_fascia) * 100, 3) . "%\n");
    out("  06 -> 14: " . round(($fasce_gap['6_14']  / $sec_fascia) * 100, 3) . "%\n");
    out("  14 -> 22: " . round(($fasce_gap['14_22'] / $sec_fascia) * 100, 3) . "%\n\n");

    // Ordinamento gruppi
    $ordine_categorie = ['oltre_7gg' => 0, 'giorni' => 1, 'ore' => 2, 'minuti' => 3];
    usort($gap_raggruppati, function($a, $b) use ($ordine_categorie) {
        $cat_diff = $ordine_categorie[$a['categoria']] - $ordine_categorie[$b['categoria']];
        if ($cat_diff != 0) return $cat_diff;
        return $b['valore'] - $a['valore'];
    });

    out("--- SOMMARIO GAP ---\n");
    foreach ($gap_raggruppati as $gruppo) {
        $count = count($gruppo['gap']);
        if ($gruppo['categoria'] == 'oltre_7gg') out("$count gap > 7 gg\n");
        else out("$count gap {$gruppo['valore']} {$gruppo['unita']}\n");
    }
    out("\n");

    out("--- DETTAGLIO GAP ---\n");
    foreach ($gap_raggruppati as $gruppo) {
        $count = count($gruppo['gap']);
        if ($gruppo['categoria'] == 'oltre_7gg') out("\n*** Gap > 7 giorni ($count) ***\n");
        else out("\n*** Gap {$gruppo['valore']} {$gruppo['unita']} ($count) ***\n");

        usort($gruppo['gap'], 'ordina_gap_per_data');
        foreach ($gruppo['gap'] as $gap) {
            out(
                "- Inizio: " . $gap['data_ora_inizio_gap'] .
                " | Durata: " . formatta_intervallo($gap['intervallo_secondi']) . "\n"
            );
        }
    }
    out("\n");
}

// ------------------------------------
// ANALISI 3: DATI MANCANTI (NULL)
// ------------------------------------
$result_null          = $pdo_lettura->query($sql_null);
$total_null_anomalies = $result_null->rowCount();

out("### 3. ANOMALIE DI VALORE (Campi NULL) - Totale: $total_null_anomalies ###\n");

if ($total_null_anomalies > 0) {
    foreach ($result_null as $row) {
        out(
            "- Data/Ora: " . $row['data_ora'] . " | Campi NULL: " . trim($row['campi_null']) . "\n"
        );
    }
} else {
    out("  Nessuna riga con valori NULL rilevata.\n");
}
out("\n");

// ====================================================================
// CARICA TUTTI I DATI TEMPERATURA + UMIDITA' (per analisi PHP)
// umidita_RH serve al Codice B per calcolare il punto di rugiada
// ====================================================================
$sql_temp_all = "
    SELECT data_ora, temperatura_C, umidita_RH
    FROM $table_name
    WHERE data_ora >= '$data_inizio'
      AND temperatura_C IS NOT NULL
      AND temperatura_C BETWEEN -25 AND 46
    ORDER BY data_ora ASC
";
$result_temp_all = $pdo_lettura->query($sql_temp_all);
$rows_temp_all   = $result_temp_all->fetchAll(PDO::FETCH_ASSOC);
$n_temp_all      = count($rows_temp_all);

// ====================================================================
// ANALISI 4: SPIKE STRUMENTALI (Codice A) - solo RILEVAMENTO e flagging
// La correzione avviene nella Parte 2
// ====================================================================
$result_spike_str = $pdo_lettura->query($sql_spike_str);
$rows_spike_str   = $result_spike_str->fetchAll(PDO::FETCH_ASSOC);
$total_spike_str  = count($rows_spike_str);

$stmt_flag_spike_str = $pdo->prepare(
    "UPDATE $table_name SET flag_temp = COALESCE(flag_temp, 0) | :bit WHERE data_ora = :data_ora"
);
foreach ($rows_spike_str as $s) {
    // Non e' uno spike se il valore anomalo e' fuori range fisico (e' errore sensore)
    if ((float)$s['temp_spike'] > 46.0 || (float)$s['temp_spike'] < -25.0) continue;
    $stmt_flag_spike_str->execute([':bit' => TEMP_SPIKE_STR, ':data_ora' => $s['data_ora']]);
}

out("### 4. SPIKE STRUMENTALI (|delta|>=1C con rientro entro 2 min) - Totale: $total_spike_str ###\n");
out("       (saranno corretti automaticamente nella Parte 2)\n");

if ($total_spike_str > 0) {
    foreach ($rows_spike_str as $s) {
        $d1 = $s['temp_dopo1'] !== null ? $s['temp_dopo1'] . 'C' : 'N/A';
        $d2 = $s['temp_dopo2'] !== null ? $s['temp_dopo2'] . 'C' : 'N/A';
        out(
            "- {$s['data_ora']} | Prima: {$s['temp_prima']}C -> Spike: {$s['temp_spike']}C" .
            " (delta {$s['delta_spike']}C) -> Dopo: $d1 / $d2\n"
        );
    }
} else {
    out("  Nessuno spike strumentale rilevato.\n");
}
out("\n");

// ====================================================================
// ANALISI 5: SPIKE AMBIENTALI (Codice B v2) - RILEVAMENTO e flagging
// Approccio: eccesso rispetto al contesto locale + verifica dp
// NON corretti automaticamente (richiedono revisione manuale)
// ====================================================================
$eventi_amb = rileva_spike_ambientali(
    $rows_temp_all,
    12,   // $win_min:    semiampiezza finestra centrata (minuti) - LIMITE DURO
    3,    // $core_min:   semiampiezza zona centrale attorno al picco (minuti)
    1.5,  // $soglia_ecc: eccesso minimo T rispetto ai bordi della finestra (C)

);

// Set delle date spike strumentali per identificare overlap
$spike_str_set = array_flip(array_column($rows_spike_str, 'data_ora'));

// Flagga nel DB tutti i campioni interni a ogni evento AMB
$stmt_flag_spike_amb = $pdo->prepare(
    "UPDATE $table_name SET flag_temp = COALESCE(flag_temp, 0) | :bit
     WHERE data_ora >= :da AND data_ora <= :a"
);
foreach ($eventi_amb as $ev) {
    $stmt_flag_spike_amb->execute([
        ':bit' => TEMP_SPIKE_AMB,
        ':da'  => $ev['data_ora_inizio'],
        ':a'   => $ev['data_ora_fine'],
    ]);
}
$total_spike_amb_all = count($eventi_amb);

out("### 5. SPIKE AMBIENTALI (contesto locale + verifica dp) - Eventi: {$total_spike_amb_all} ###\n");
out("       (soglia eccesso>=1.5C, campanula confermata)\n");
out("       (non corretti automaticamente - revisione manuale)\n");

if ($total_spike_amb_all > 0) {
    foreach ($eventi_amb as $ev) {
        out(
            "- Inizio: {$ev['data_ora_inizio']} | Picco: {$ev['data_ora_picco']}" .
            " | Fine: {$ev['data_ora_fine']} | durata: {$ev['durata_min']} min\n" .
            "  T_picco={$ev['T_picco']}C | T_base={$ev['T_base']}C" .
            " | eccesso={$ev['eccesso']}C" .
            " | campioni: {$ev['n_campioni']}\n"
        );
    }
} else {
    out("  Nessuno spike ambientale rilevato.\n");
}
out("\n");

// ====================================================================
// ANALISI 6: DERIVA TERMICA PERSISTENTE (>=0.5C/2min per >=4 campioni)
// Usa dati originali (la correzione STR avviene nella Parte 2)
// ====================================================================
$gradienti_anomali = [];
$soglia_gradiente  = 0.5;
$streak_campioni   = 0;
$streak_start      = null;
$streak_end        = null;
$i = 0;

while ($i < $n_temp_all) {
    $t_i     = strtotime($rows_temp_all[$i]['data_ora']);
    $j       = $i + 1;
    $trovato = false;
    while ($j < $n_temp_all) {
        $dt = strtotime($rows_temp_all[$j]['data_ora']) - $t_i;
        if ($dt >= 100 && $dt <= 140) { $trovato = true; break; }
        if ($dt > 140) break;
        $j++;
    }
    if (!$trovato) {
        if ($streak_campioni >= 4) {
            $gradienti_anomali[] = [
                'data_ora_inizio' => $streak_start,
                'data_ora_fine'   => $streak_end,
                'campioni'        => $streak_campioni,
            ];
        }
        $streak_campioni = 0; $streak_start = null; $streak_end = null;
        $i++;
        continue;
    }
    $dT = $rows_temp_all[$j]['temperatura_C'] - $rows_temp_all[$i]['temperatura_C'];
    if (round(abs($dT), 2) >= $soglia_gradiente) {
        if ($streak_campioni === 0) $streak_start = $rows_temp_all[$i]['data_ora'];
        $streak_campioni++;
        $streak_end = $rows_temp_all[$j]['data_ora'];
    } else {
        if ($streak_campioni >= 4) {
            $gradienti_anomali[] = [
                'data_ora_inizio' => $streak_start,
                'data_ora_fine'   => $streak_end,
                'campioni'        => $streak_campioni,
            ];
        }
        $streak_campioni = 0; $streak_start = null; $streak_end = null;
    }
    $i = $j;
}
if ($streak_campioni >= 3) {
    $gradienti_anomali[] = [
        'data_ora_inizio' => $streak_start,
        'data_ora_fine'   => $streak_end,
        'campioni'        => $streak_campioni,
    ];
}

$stmt_flag_deriva = $pdo->prepare(
    "UPDATE $table_name SET flag_temp = COALESCE(flag_temp, 0) | :bit
     WHERE data_ora >= :da AND data_ora <= :a"
);
foreach ($gradienti_anomali as $g) {
    $stmt_flag_deriva->execute([':bit' => TEMP_DERIVA, ':da' => $g['data_ora_inizio'], ':a' => $g['data_ora_fine']]);
}

$total_gradienti = count($gradienti_anomali);
out("### 6. DERIVA TERMICA PERSISTENTE (>=0.5C/2min per >=4 campioni) - Periodi: $total_gradienti ###\n");
if ($total_gradienti === 0) {
    out("  Nessuna deriva persistente rilevata.\n");
} else {
    foreach ($gradienti_anomali as $g) {
        out(
            "- Da: {$g['data_ora_inizio']} a: {$g['data_ora_fine']} | campioni: {$g['campioni']}\n"
        );
    }
}
out("\n");

// ====================================================================
// ANALISI 7: OUTLIER IQR SU FINESTRE MOBILI (finestra=10, soglia=3*IQR)
// ====================================================================
$finestra    = 10;
$fattore_iqr = 3.0;
$outlier_iqr = [];

for ($i = $finestra; $i < $n_temp_all; $i++) {
    $valori = [];
    for ($j = $i - $finestra; $j < $i; $j++) {
        $valori[] = (float)$rows_temp_all[$j]['temperatura_C'];
    }
    sort($valori);
    $q1_idx = (int)floor(($finestra - 1) * 0.25);
    $q3_idx = (int)floor(($finestra - 1) * 0.75);
    $q1  = $valori[$q1_idx];
    $q3  = $valori[$q3_idx];
    $iqr = $q3 - $q1;
    if ($iqr < 1.0) continue;
    $low  = $q1 - $fattore_iqr * $iqr;
    $high = $q3 + $fattore_iqr * $iqr;
    $val  = (float)$rows_temp_all[$i]['temperatura_C'];
    if ($val < $low || $val > $high) {
        $outlier_iqr[] = [
            'data_ora' => $rows_temp_all[$i]['data_ora'],
            'val'      => $val,
            'q1'       => round($q1,  3),
            'q3'       => round($q3,  3),
            'iqr'      => round($iqr, 3),
            'low'      => round($low, 3),
            'high'     => round($high, 3),
        ];
    }
}

$stmt_flag_iqr = $pdo->prepare(
    "UPDATE $table_name SET flag_temp = COALESCE(flag_temp, 0) | :bit WHERE data_ora = :data_ora"
);
foreach ($outlier_iqr as $o) {
    $stmt_flag_iqr->execute([':bit' => TEMP_IQR, ':data_ora' => $o['data_ora']]);
}

$total_iqr = count($outlier_iqr);
out("### 7. OUTLIER IQR FINESTRE MOBILI (finestra={$finestra}, soglia=3*IQR) - Totale: $total_iqr ###\n");
if ($total_iqr === 0) {
    out("  Nessun outlier IQR rilevato.\n");
} else {
    foreach ($outlier_iqr as $o) {
        $dir = ($o['val'] > $o['high']) ? 'ALTO' : 'BASSO';
        out(
            "- {$o['data_ora']} | T={$o['val']}C | range_ok=[{$o['low']}, {$o['high']}]" .
            " | Q1={$o['q1']} Q3={$o['q3']} IQR={$o['iqr']} | FUORI: $dir\n"
        );
    }
}
out("\n");

// ====================================================================
// ANALISI 8: PERSISTENZA SENSORE TEMPERATURA
// Diurno: +-0.2C per >2h (120 min); Notturno (22-06): +-0.1C per >3h (180 min)
// ====================================================================
$persistenza_temp = [];
$pers_streak      = 0;
$pers_val_ref     = null;
$pers_start_idx   = null;

for ($i = 1; $i < $n_temp_all; $i++) {
    $t_prev = strtotime($rows_temp_all[$i - 1]['data_ora']);
    $t_curr = strtotime($rows_temp_all[$i]['data_ora']);
    if (($t_curr - $t_prev) > 240) {
        // Gap > 240 sec (2 campioni mancanti): interrompe la streak
        $pers_streak = 0; $pers_val_ref = null; $pers_start_idx = null;
        continue;
    }
    // Gap 126-240 sec (1-2 campioni mancanti): tollerato, la streak continua
    $val = (float)$rows_temp_all[$i]['temperatura_C'];
    if ($pers_val_ref === null) {
        $pers_val_ref = $val; $pers_start_idx = $i - 1; $pers_streak = 1;
        continue;
    }
    // Soglia basata sull'ora di INIZIO della streak (non del campione corrente).
    // Evita che una streak iniziata di notte si allunghi con la soglia diurna piu' larga.
    $t_start_streak = strtotime($rows_temp_all[$pers_start_idx]['data_ora']);
    $ora_start      = (int)date('H', $t_start_streak);
    $notturno       = ($ora_start >= 22 || $ora_start < 6);
    $soglia_pers    = $notturno ? 0.1 : 0.2;
    $soglia_min     = $notturno ? 180  : 120;

    if (abs($val - $pers_val_ref) <= $soglia_pers) {
        $pers_streak++;
        if ($pers_streak >= $soglia_min) {
            $ultimo = end($persistenza_temp);
            if ($ultimo === false || $ultimo['idx_fine'] < $pers_start_idx + $pers_streak - 1) {
                if ($ultimo !== false && $ultimo['idx_inizio'] === $pers_start_idx) {
                    $persistenza_temp[count($persistenza_temp) - 1]['idx_fine']      = $i;
                    $persistenza_temp[count($persistenza_temp) - 1]['durata_min']    = $pers_streak;
                    $persistenza_temp[count($persistenza_temp) - 1]['data_ora_fine'] = $rows_temp_all[$i]['data_ora'];
                } else {
                    $persistenza_temp[] = [
                        'data_ora_inizio' => $rows_temp_all[$pers_start_idx]['data_ora'],
                        'data_ora_fine'   => $rows_temp_all[$i]['data_ora'],
                        'idx_inizio'      => $pers_start_idx,
                        'idx_fine'        => $i,
                        'val_ref'         => $pers_val_ref,
                        'durata_min'      => $pers_streak,
                    ];
                }
            }
        }
    } else {
        $pers_val_ref = $val; $pers_start_idx = $i; $pers_streak = 1;
    }
}

$stmt_flag_pers_t = $pdo->prepare(
    "UPDATE $table_name SET flag_temp = COALESCE(flag_temp, 0) | :bit
     WHERE data_ora >= :da AND data_ora <= :a"
);
foreach ($persistenza_temp as $p) {
    $stmt_flag_pers_t->execute([':bit' => TEMP_PERSISTENZA, ':da' => $p['data_ora_inizio'], ':a' => $p['data_ora_fine']]);
}

$total_pers_temp = count($persistenza_temp);
out("### 8. PERSISTENZA SENSORE TEMPERATURA - Periodi: $total_pers_temp ###\n");
out("       (+-0.2C>2h diurno | +-0.1C>3h notturno)\n");
if ($total_pers_temp === 0) {
    out("  Nessuna persistenza anomala rilevata.\n");
} else {
    foreach ($persistenza_temp as $p) {
        $sec_pers  = strtotime($p['data_ora_fine']) - strtotime($p['data_ora_inizio']);
        $ore_pers  = (int)floor($sec_pers / 3600);
        $min_pers  = (int)round(($sec_pers % 3600) / 60);
        $dur_str   = $ore_pers > 0 ? "{$ore_pers}h {$min_pers}min" : "{$min_pers}min";
        out(
            "- Da: {$p['data_ora_inizio']} a: {$p['data_ora_fine']}" .
            " | T_ref={$p['val_ref']}C | durata: {$dur_str}\n"
        );
    }
}
out("\n");

// ====================================================================
// ANALISI 9: TEMPERATURA FUORI RANGE FISICO (>46C o <-25C)
// ====================================================================
$sql_range_temp = "
    SELECT data_ora, temperatura_C
    FROM $table_name
    WHERE data_ora >= '$data_inizio'
      AND temperatura_C IS NOT NULL
      AND (temperatura_C > 46 OR temperatura_C < -25)
    ORDER BY data_ora ASC
";
$result_range_temp = $pdo_lettura->query($sql_range_temp);
$rows_range_temp   = $result_range_temp->fetchAll(PDO::FETCH_ASSOC);

// Flag MANCANTE per NULL temperatura
$pdo->exec("
    UPDATE $table_name
    SET flag_temp = COALESCE(flag_temp, 0) | " . TEMP_MANCANTE . "
    WHERE data_ora >= '$data_inizio'
      AND temperatura_C IS NULL
");

$stmt_flag_range_t = $pdo->prepare(
    "UPDATE $table_name SET flag_temp = COALESCE(flag_temp, 0) | :bit WHERE data_ora = :data_ora"
);
foreach ($rows_range_temp as $r) {
    $stmt_flag_range_t->execute([':bit' => TEMP_RANGE, ':data_ora' => $r['data_ora']]);
}

// Chiudi flag_temp = 0 (OK) per record ancora NULL nel periodo
$pdo->exec("
    UPDATE $table_name
    SET flag_temp = 0
    WHERE data_ora >= '$data_inizio'
      AND flag_temp IS NULL
");

$total_range_temp = count($rows_range_temp);
out("### 9. TEMPERATURA FUORI RANGE FISICO (>46C o <-25C) - Totale: $total_range_temp ###\n");
if ($total_range_temp === 0) {
    out("  Nessun valore fuori range fisico.\n");
} else {
    foreach ($rows_range_temp as $r) {
        out("- {$r['data_ora']} | T={$r['temperatura_C']}C\n");
    }
}
out("\n");

// ====================================================================
// ANALISI 10: CONSISTENZA ORTOGONALE (dp > T)
// Formula Magnus per il punto di rugiada
// ====================================================================
$sql_ortogonale = "
    SELECT data_ora, temperatura_C, umidita_RH
    FROM $table_name
    WHERE data_ora >= '$data_inizio'
      AND temperatura_C IS NOT NULL
      AND umidita_RH IS NOT NULL
      AND umidita_RH > 0
    ORDER BY data_ora ASC
";
$result_ortogonale = $pdo_lettura->query($sql_ortogonale);
$rows_ortogonale   = $result_ortogonale->fetchAll(PDO::FETCH_ASSOC);

$ortogonali     = [];
$stmt_flag_orto = $pdo->prepare(
    "UPDATE $table_name SET flag_temp = COALESCE(flag_temp, 0) | :bit WHERE data_ora = :data_ora"
);
foreach ($rows_ortogonale as $r) {
    $T     = (float)$r['temperatura_C'];
    $RH    = (float)$r['umidita_RH'];
    $alpha = ((17.27 * $T) / (237.3 + $T)) + log($RH / 100.0);
    $dp    = round((237.3 * $alpha) / (17.27 - $alpha), 2);
    if ($dp > $T + 0.5) {
        $ortogonali[] = ['data_ora' => $r['data_ora'], 'T' => $T, 'RH' => $RH, 'dp' => $dp];
        $stmt_flag_orto->execute([':bit' => TEMP_ORTOGONALE, ':data_ora' => $r['data_ora']]);
    }
}

$total_orto = count($ortogonali);
out("### 10. CONSISTENZA ORTOGONALE (dp > T+0.5C) - Totale: $total_orto ###\n");
if ($total_orto === 0) {
    out("  Nessuna inconsistenza ortogonale rilevata.\n");
} else {
    foreach ($ortogonali as $o) {
        out("- {$o['data_ora']} | T={$o['T']}C | RH={$o['RH']}% | dp={$o['dp']}C\n");
    }
}
out("\n");

// ====================================================================
// ANALISI PRESSIONE - carica tutti i dati
// ====================================================================
$sql_press = "
    SELECT data_ora, pressione_hPa
    FROM $table_name
    WHERE data_ora >= '$data_inizio'
      AND (pressione_hPa IS NULL OR pressione_hPa BETWEEN 970 AND 1060)
    ORDER BY data_ora ASC
";
$result_press = $pdo_lettura->query($sql_press);
$rows_press   = $result_press->fetchAll(PDO::FETCH_ASSOC);

// Flag MANCANTE per NULL pressione
$pdo->exec("
    UPDATE $table_name
    SET flag_press = COALESCE(flag_press, 0) | " . PRESS_MANCANTE . "
    WHERE data_ora >= '$data_inizio'
      AND pressione_hPa IS NULL
");

// ====================================================================
// ANALISI 11: PRESSIONE FUORI RANGE (>1060 o <970 hPa)
// ====================================================================
$press_range           = [];
$stmt_flag_press_range = $pdo->prepare(
    "UPDATE $table_name SET flag_press = COALESCE(flag_press, 0) | :bit WHERE data_ora = :data_ora"
);
foreach ($rows_press as $r) {
    if ($r['pressione_hPa'] === null) continue;
    $p = (float)$r['pressione_hPa'];
    if ($p > 1060 || $p < 970) {
        $press_range[] = $r;
        $stmt_flag_press_range->execute([':bit' => PRESS_RANGE, ':data_ora' => $r['data_ora']]);
    }
}

$total_press_range = count($press_range);
out("### 11. PRESSIONE FUORI RANGE (>1060 o <970 hPa) - Totale: $total_press_range ###\n");
if ($total_press_range === 0) {
    out("  Nessun valore fuori range.\n");
} else {
    foreach ($press_range as $r) {
        out("- {$r['data_ora']} | P={$r['pressione_hPa']} hPa\n");
    }
}
out("\n");

// ====================================================================
// ANALISI 12: SPIKE PRESSIONE (delta >= 3 hPa con rientro entro 10 min)
// ====================================================================
$spike_press           = [];
$stmt_flag_press_spike = $pdo->prepare(
    "UPDATE $table_name SET flag_press = COALESCE(flag_press, 0) | :bit WHERE data_ora = :data_ora"
);
$rows_press_notnull = array_values(array_filter($rows_press, fn($r) => $r['pressione_hPa'] !== null));

for ($i = 1; $i < count($rows_press_notnull) - 1; $i++) {
    $t_prev = strtotime($rows_press_notnull[$i - 1]['data_ora']);
    $t_curr = strtotime($rows_press_notnull[$i]['data_ora']);
    $t_next = strtotime($rows_press_notnull[$i + 1]['data_ora']);
    if (($t_curr - $t_prev) > 700 || ($t_next - $t_curr) > 700) continue;
    $p_prev = (float)$rows_press_notnull[$i - 1]['pressione_hPa'];
    $p_curr = (float)$rows_press_notnull[$i]['pressione_hPa'];
    $p_next = (float)$rows_press_notnull[$i + 1]['pressione_hPa'];
    // Se il valore anomalo e' gia' fuori range fisico e' errore sensore, non spike
    if ($p_curr > 1060 || $p_curr < 970) continue;
    $delta1 = abs($p_curr - $p_prev);
    $delta2 = abs($p_next - $p_prev);
    if ($delta1 >= 3.0 && $delta2 <= 1.0) {
        $spike_press[] = [
            'data_ora' => $rows_press_notnull[$i]['data_ora'],
            'p_prev'   => $p_prev,
            'p_spike'  => $p_curr,
            'p_next'   => $p_next,
            'delta'    => round($delta1, 2),
        ];
        $stmt_flag_press_spike->execute([':bit' => PRESS_SPIKE, ':data_ora' => $rows_press_notnull[$i]['data_ora']]);
    }
}

$total_press_spike = count($spike_press);
out("### 12. SPIKE PRESSIONE (delta>=3hPa/10min con rientro) - Totale: $total_press_spike ###\n");
if ($total_press_spike === 0) {
    out("  Nessuno spike di pressione rilevato.\n");
} else {
    foreach ($spike_press as $s) {
        out(
            "- {$s['data_ora']} | P_prec={$s['p_prev']} -> Spike={$s['p_spike']}" .
            " (delta {$s['delta']} hPa) -> P_dopo={$s['p_next']}\n"
        );
    }
}
out("\n");

// ====================================================================
// ANALISI 13: DERIVA PRESSIONE (> 6 hPa/1h)
// ====================================================================
$deriva_press           = [];
$stmt_flag_press_deriva = $pdo->prepare(
    "UPDATE $table_name SET flag_press = COALESCE(flag_press, 0) | :bit WHERE data_ora = :data_ora"
);
for ($i = 0; $i < count($rows_press_notnull); $i++) {
    $t_i = strtotime($rows_press_notnull[$i]['data_ora']);
    for ($j = $i - 1; $j >= 0; $j--) {
        $t_j = strtotime($rows_press_notnull[$j]['data_ora']);
        $dt  = $t_i - $t_j;
        if ($dt >= 3300 && $dt <= 3900) {
            $dP = abs((float)$rows_press_notnull[$i]['pressione_hPa'] - (float)$rows_press_notnull[$j]['pressione_hPa']);
            if ($dP > 6.0) {
                $deriva_press[] = [
                    'data_ora' => $rows_press_notnull[$i]['data_ora'],
                    'p_ora_fa' => $rows_press_notnull[$j]['pressione_hPa'],
                    'p_att'    => $rows_press_notnull[$i]['pressione_hPa'],
                    'dP'       => round($dP, 2),
                ];
                $stmt_flag_press_deriva->execute([':bit' => PRESS_DERIVA, ':data_ora' => $rows_press_notnull[$i]['data_ora']]);
            }
            break;
        }
        if ($dt > 3900) break;
    }
}

$total_press_deriva = count($deriva_press);
out("### 13. DERIVA PRESSIONE (>6 hPa/1h) - Totale: $total_press_deriva ###\n");
if ($total_press_deriva === 0) {
    out("  Nessuna deriva di pressione rilevata.\n");
} else {
    foreach ($deriva_press as $d) {
        out(
            "- {$d['data_ora']} | P_1h_fa={$d['p_ora_fa']} P_att={$d['p_att']} | dP={$d['dP']} hPa/h\n"
        );
    }
}
out("\n");

// ====================================================================
// ANALISI 14: PERSISTENZA SENSORE PRESSIONE (stesso valore per >3h)
// ====================================================================
$persistenza_press = [];
$pp_streak         = 0;
$pp_val_ref        = null;
$pp_start_idx      = null;

for ($i = 1; $i < count($rows_press_notnull); $i++) {
    $t_prev = strtotime($rows_press_notnull[$i - 1]['data_ora']);
    $t_curr = strtotime($rows_press_notnull[$i]['data_ora']);
    if (($t_curr - $t_prev) > 240) {
        // Gap > 240 sec (2 campioni mancanti): interrompe la streak
        $pp_streak = 0; $pp_val_ref = null; $pp_start_idx = null;
        continue;
    }
    // Gap 126-240 sec (1-2 campioni mancanti): tollerato, la streak continua
    $val = (float)$rows_press_notnull[$i]['pressione_hPa'];
    if ($pp_val_ref === null) {
        $pp_val_ref = $val; $pp_start_idx = $i - 1; $pp_streak = 1;
        continue;
    }
    if (abs($val - $pp_val_ref) < 0.1) {
        $pp_streak++;
        if ($pp_streak >= 180) {
            $ultimo = end($persistenza_press);
            if ($ultimo === false || $ultimo['idx_fine'] < $pp_start_idx + $pp_streak - 1) {
                if ($ultimo !== false && $ultimo['idx_inizio'] === $pp_start_idx) {
                    $persistenza_press[count($persistenza_press) - 1]['idx_fine']      = $i;
                    $persistenza_press[count($persistenza_press) - 1]['data_ora_fine'] = $rows_press_notnull[$i]['data_ora'];
                    $persistenza_press[count($persistenza_press) - 1]['durata_min']    = $pp_streak;
                } else {
                    $persistenza_press[] = [
                        'data_ora_inizio' => $rows_press_notnull[$pp_start_idx]['data_ora'],
                        'data_ora_fine'   => $rows_press_notnull[$i]['data_ora'],
                        'idx_inizio'      => $pp_start_idx,
                        'idx_fine'        => $i,
                        'val_ref'         => $pp_val_ref,
                        'durata_min'      => $pp_streak,
                    ];
                }
            }
        }
    } else {
        $pp_val_ref = $val; $pp_start_idx = $i; $pp_streak = 1;
    }
}

$stmt_flag_press_pers = $pdo->prepare(
    "UPDATE $table_name SET flag_press = COALESCE(flag_press, 0) | :bit
     WHERE data_ora >= :da AND data_ora <= :a"
);
foreach ($persistenza_press as $p) {
    $stmt_flag_press_pers->execute([':bit' => PRESS_PERSISTENZA, ':da' => $p['data_ora_inizio'], ':a' => $p['data_ora_fine']]);
}

// Chiudi flag_press = 0 (OK) per record ancora NULL nel periodo
$pdo->exec("
    UPDATE $table_name
    SET flag_press = 0
    WHERE data_ora >= '$data_inizio'
      AND flag_press IS NULL
");

$total_press_pers = count($persistenza_press);
out("### 14. PERSISTENZA SENSORE PRESSIONE (stesso valore per >3h) - Periodi: $total_press_pers ###\n");
if ($total_press_pers === 0) {
    out("  Nessuna persistenza anomala di pressione rilevata.\n");
} else {
    foreach ($persistenza_press as $p) {
        $sec_pp  = strtotime($p['data_ora_fine']) - strtotime($p['data_ora_inizio']);
        $ore_pp  = (int)floor($sec_pp / 3600);
        $min_pp  = (int)round(($sec_pp % 3600) / 60);
        $dur_pp  = $ore_pp > 0 ? "{$ore_pp}h {$min_pp}min" : "{$min_pp}min";
        out(
            "- Da: {$p['data_ora_inizio']} a: {$p['data_ora_fine']}" .
            " | P_ref={$p['val_ref']} hPa | durata: {$dur_pp}\n"
        );
    }
}
out("\n");

// ====================================================================
// RIEPILOGO FLAG WMO BITMASK (fine Parte 1)
// ====================================================================
out("=================================================\n");
out("### RIEPILOGO FLAG WMO ###\n");
out("=================================================\n\n");

$sql_count_flags = "
    SELECT
        SUM(flag_temp IS NULL)                          AS non_processati,
        SUM(flag_temp = 0)                              AS ok,
        SUM(flag_temp > 0)                              AS anomali,
        SUM(flag_temp & " . TEMP_IQR         . " > 0)  AS iqr,
        SUM(flag_temp & " . TEMP_SPIKE_STR   . " > 0)  AS spike_str,
        SUM(flag_temp & " . TEMP_SPIKE_AMB   . " > 0)  AS spike_amb,
        SUM(flag_temp & " . TEMP_DERIVA      . " > 0)  AS deriva,
        SUM(flag_temp & " . TEMP_PERSISTENZA . " > 0)  AS persistenza,
        SUM(flag_temp & " . TEMP_RANGE       . " > 0)  AS range_t,
        SUM(flag_temp & " . TEMP_ORTOGONALE  . " > 0)  AS ortogonale,
        SUM(flag_temp & " . TEMP_MANCANTE    . " > 0)  AS mancante_t,
        SUM(flag_press & " . PRESS_SPIKE       . " > 0) AS press_spike,
        SUM(flag_press & " . PRESS_DERIVA      . " > 0) AS press_deriva,
        SUM(flag_press & " . PRESS_PERSISTENZA . " > 0) AS press_pers,
        SUM(flag_press & " . PRESS_RANGE       . " > 0) AS press_range,
        SUM(flag_press & " . PRESS_MANCANTE    . " > 0) AS press_mancante,
        COUNT(*) AS totale
    FROM $table_name
    WHERE data_ora >= '$data_inizio'
";
$stat = $pdo_lettura->query($sql_count_flags)->fetch(PDO::FETCH_ASSOC);
$tot  = $stat['totale'];

out("FLAG TEMPERATURA (totale record: $tot):\n");
out("  [OK]           flag=0               : " . $stat['ok']          . " (" . round($stat['ok'] / $tot * 100, 1) . "%)\n");
out("  [NON PROCESS.] flag=NULL            : " . $stat['non_processati'] . "\n");
out("  bit 0x01 IQR                        : " . $stat['iqr']          . "\n");
out("  bit 0x02 SPIKE_STR (strumentale)    : " . $stat['spike_str']    . "\n");
out("  bit 0x80 SPIKE_AMB (ambientale/sole): " . $stat['spike_amb']    . "\n");
out("  bit 0x04 DERIVA                     : " . $stat['deriva']        . "\n");
out("  bit 0x08 PERSISTENZA                : " . $stat['persistenza']   . "\n");
out("  bit 0x10 FUORI RANGE                : " . $stat['range_t']       . "\n");
out("  bit 0x20 DP>T                       : " . $stat['ortogonale']    . "\n");
out("  bit 0x40 MANCANTE                   : " . $stat['mancante_t']    . "\n");
out("  Totale record con flag>0            : " . $stat['anomali']       . " (" . round($stat['anomali'] / $tot * 100, 1) . "%)\n\n");

out("FLAG PRESSIONE:\n");
out("  bit 0x01 SPIKE      : " . $stat['press_spike']    . "\n");
out("  bit 0x02 DERIVA     : " . $stat['press_deriva']   . "\n");
out("  bit 0x04 PERSISTENZA: " . $stat['press_pers']     . "\n");
out("  bit 0x08 FUORI RANGE: " . $stat['press_range']    . "\n");
out("  bit 0x10 MANCANTE   : " . $stat['press_mancante'] . "\n\n");

// Lista record anomali (chiude Parte 1)
out("=================================================\n");
out("### LISTA RECORD ANOMALI (flag_temp>0 OR flag_press>0) ###\n");
out("=================================================\n\n");

$sql_anomali    = "
    SELECT data_ora, temperatura_C, pressione_hPa, flag_temp, flag_press
    FROM $table_name
    WHERE data_ora >= '$data_inizio'
      AND (flag_temp > 0 OR flag_press > 0)
    ORDER BY data_ora ASC
";
$result_anomali = $pdo_lettura->query($sql_anomali);
$rows_anomali   = $result_anomali->fetchAll(PDO::FETCH_ASSOC);
$total_anomali  = count($rows_anomali);

if ($total_anomali === 0) {
    out("  Nessun record anomalo. Tutti i dati hanno superato i controlli WMO.\n\n");
} else {
    out("  Totale record anomali: $total_anomali\n\n");
    $anomali_stampati = 0;

    // Raggruppa record consecutivi con flag "solo PERSISTENZA" (temp e/o press)
    // in intervalli; gli altri vengono stampati normalmente.
    $pers_group = null; // gruppo persistenza in corso

    $flush_pers_group = function() use (&$pers_group, &$anomali_stampati) {
        if ($pers_group === null) return;
        $sec = strtotime($pers_group['fine']) - strtotime($pers_group['inizio']);
        $ore = (int)floor($sec / 3600);
        $min = (int)round(($sec % 3600) / 60);
        $dur = $ore > 0 ? "{$ore}h {$min}min" : "{$min}min";
        out(
            "[PERSISTENZA x{$pers_group['n']}]" .
            " Da: {$pers_group['inizio']} a: {$pers_group['fine']}" .
            " | {$pers_group['dettaglio']} | durata: {$dur}\n"
        );
        $anomali_stampati++;
        $pers_group = null;
    };

    foreach ($rows_anomali as $r) {
        $ft = ($r['flag_temp']  !== null) ? (int)$r['flag_temp']  : null;
        $fp = ($r['flag_press'] !== null) ? (int)$r['flag_press'] : null;

        // Salta se l'unica anomalia e' MANCANTE (gia' listata nella sezione NULL)
        $ft_solo_mancante = ($ft !== null && ($ft & ~TEMP_MANCANTE)  === 0);
        $fp_solo_mancante = ($fp !== null && ($fp & ~PRESS_MANCANTE) === 0);
        if ($ft_solo_mancante && ($fp === null || $fp_solo_mancante)) continue;
        if ($fp_solo_mancante && $ft === null) continue;

        // Verifica se questo record ha flag "solo PERSISTENZA" (temp e/o press),
        // senza altri bit accesi (escluso MANCANTE che e' gia' filtrato sopra)
        $ft_solo_pers = ($ft !== null && ($ft & ~TEMP_MANCANTE)  === TEMP_PERSISTENZA);
        $fp_solo_pers = ($fp !== null && ($fp & ~PRESS_MANCANTE) === PRESS_PERSISTENZA);
        $ft_ok_o_null = ($ft === null || $ft === 0 || $ft_solo_mancante);
        $fp_ok_o_null = ($fp === null || $fp === 0 || $fp_solo_mancante);

        $e_solo_persistenza = (
            ($ft_solo_pers || $ft_ok_o_null) &&
            ($fp_solo_pers || $fp_ok_o_null) &&
            ($ft_solo_pers || $fp_solo_pers)
        );

        if ($e_solo_persistenza) {
            // Costruisci il dettaglio per il gruppo
            $det_parts = [];
            if ($ft_solo_pers) {
                $t_val = ($r['temperatura_C'] !== null) ? $r['temperatura_C'] . "C" : "NULL";
                $det_parts[] = "T=" . $t_val . " flag_T=PERSISTENZA";
            }
            if ($fp_solo_pers) {
                $p_val = ($r['pressione_hPa'] !== null) ? $r['pressione_hPa'] . "hPa" : "NULL";
                $det_parts[] = "P=" . $p_val . " flag_P=PERSISTENZA";
            }
            $det = implode(' | ', $det_parts);

            if ($pers_group === null) {
                $pers_group = ['inizio' => $r['data_ora'], 'fine' => $r['data_ora'], 'n' => 1, 'dettaglio' => $det];
            } else {
                $pers_group['fine'] = $r['data_ora'];
                $pers_group['n']++;
                // Aggiorna il dettaglio con l'ultimo valore del gruppo
                $pers_group['dettaglio'] = $det;
            }
        } else {
            // Record con flag misto: prima chiudi eventuale gruppo persistenza
            $flush_pers_group();

            $t_str = ($r['temperatura_C'] !== null) ? $r['temperatura_C'] . "C"   : "NULL";
            $p_str = ($r['pressione_hPa'] !== null) ? $r['pressione_hPa'] . "hPa" : "NULL";
            out(
                $r['data_ora'] .
                " | T=" . $t_str . " flag_T=" . decodifica_flag_temp($ft) .
                " | P=" . $p_str . " flag_P=" . decodifica_flag_press($fp) . "\n"
            );
            $anomali_stampati++;
        }
    }
    // Chiudi eventuale ultimo gruppo persistenza
    $flush_pers_group();

    if ($anomali_stampati === 0) {
        out("  (tutte le anomalie residue sono solo MANCANTE, gia' listate nella sezione NULL)\n");
    }
}
out("\n");

// ====================================================================
// PARTE 2: CORREZIONE SPIKE STRUMENTALI (Codice C)
// Solo gli spike TEMP_SPIKE_STR vengono corretti nel DB.
// Gli spike TEMP_SPIKE_AMB sono flaggati ma NON corretti:
//   - possono rappresentare condizioni reali (es. irraggiamento solare)
//   - richiedono verifica manuale prima di qualsiasi modifica
// ====================================================================
out("####################################################\n");
out("###   PARTE 2: CORREZIONE SPIKE STRUMENTALI     ###\n");
out("###   (solo SPIKE_STR - bit 0x02)               ###\n");
out("####################################################\n\n");

$spike_corretti   = 0;
$spike_errori     = 0;
$stmt_update_corr = $pdo->prepare(
    "UPDATE $table_name SET temperatura_C = :temp WHERE data_ora = :data_ora"
);

if ($total_spike_str === 0) {
    out("  Nessuno spike strumentale da correggere.\n\n");
} else {
    out("  Spike strumentali da correggere: $total_spike_str\n\n");

    foreach ($rows_spike_str as $row) {
        $data    = $row['data_ora'];
        $t_spike = $row['temp_spike'];
        $t_prima = $row['temp_prima'];
        $t_dopo1 = $row['temp_dopo1'];
        $t_dopo2 = $row['temp_dopo2'];
        $delta   = $row['delta_spike'];

        // Determina il valore di rientro (primo record entro +-0.4C da t_prima)
        if ($t_dopo1 !== null && round(abs($t_dopo1 - $t_prima), 2) <= 0.4) {
            $t_rientro = $t_dopo1;
        } elseif ($t_dopo2 !== null && round(abs($t_dopo2 - $t_prima), 2) <= 0.4) {
            $t_rientro = $t_dopo2;
        } else {
            $t_rientro = $t_prima; // fallback: usa il valore pre-spike
        }

        // Correzione: media tra valore pre-spike e valore di rientro
        $temp_corretta = round(($t_prima + $t_rientro) / 2, 2);

        $d1_str = $t_dopo1 !== null ? $t_dopo1 . 'C' : 'N/A';
        $d2_str = $t_dopo2 !== null ? $t_dopo2 . 'C' : 'N/A';

        out(
            "- {$data} | Prima: {$t_prima}C -> Spike: {$t_spike}C (delta {$delta}C)" .
            " -> Dopo: {$d1_str} / {$d2_str}\n"
        );

        $ok = $stmt_update_corr->execute([':temp' => $temp_corretta, ':data_ora' => $data]);

        if ($ok) {
            $spike_corretti++;
            out(
                "  [OK] Corretto: {$t_spike}C -> {$temp_corretta}C" .
                " (media tra {$t_prima}C e {$t_rientro}C)\n"
            );
        } else {
            $spike_errori++;
            out("  [ERRORE] Impossibile correggere il record {$data}\n");
        }
    }

    out("\n--- RIEPILOGO CORREZIONI ---\n");
    out("  Corretti con successo : {$spike_corretti}/{$total_spike_str}\n");
    if ($spike_errori > 0) {
        out("  Errori di scrittura   : {$spike_errori}/{$total_spike_str}\n");
    }
}
out("\n");

// Nota sugli spike ambientali non corretti
if ($total_spike_amb_all > 0) {
    out("  NOTA: {$total_spike_amb_all} eventi SPIKE_AMB sono stati\n");
    out("  flaggati nel DB (bit 0x80) ma NON corretti automaticamente.\n");
    out("  Causa tipica: irraggiamento solare diretto sul sensore.\n");
    out("  Vedi dettaglio in Parte 1, sezione 5.\n\n");
}

// ====================================================================
// CHIUDI FILE
// ====================================================================
out("[INFO] Report generato con successo: $nome_file\n");
fclose($file_handle);

echo '</pre></body></html>';

// ====================================================================
// INVIO EMAIL
// ====================================================================
$numero_settimana = date('W');

$anomalie_totali =
    $total_gap_anomalies   + $total_null_anomalies  +
    $total_spike_str       + $total_spike_amb_all   +
    $total_gradienti       + $total_iqr              +
    $total_pers_temp       + $total_range_temp       + $total_orto +
    $total_press_range     + $total_press_spike      + $total_press_deriva + $total_press_pers;

if ($anomalie_totali === 0) {
    $oggetto = "DATI_DB_sett{$numero_settimana}: TUTTO OK!";
} else {
    $oggetto = "DATI_DB_sett{$numero_settimana}: " .
        "GAP:{$total_gap_anomalies} NULL:{$total_null_anomalies} " .
        "T_SPIKE_STR:{$total_spike_str} T_SPIKE_AMB:{$total_spike_amb_all} " .
        "T_DERIVA:{$total_gradienti} T_IQR:{$total_iqr} " .
        "T_PERS:{$total_pers_temp} T_RANGE:{$total_range_temp} T_DP:{$total_orto} " .
        "P_SPIKE:{$total_press_spike} P_DERIVA:{$total_press_deriva} P_RANGE:{$total_press_range} " .
        "CORR_STR:{$spike_corretti}";
}

$corpo_email = file_get_contents($nome_file);
$headers     = "From: $email_mittente\r\n";
$headers    .= "Reply-To: $email_mittente\r\n";
$headers    .= "Content-Type: text/plain; charset=UTF-8\r\n";

if (mail($email_destinatario, $oggetto, $corpo_email, $headers)) {
    echo "Email inviata a: $email_destinatario\n";
} else {
    echo "Errore nell'invio dell'email. Verifica la configurazione SMTP.\n";
}

// ====================================================================
// CHIUDI CONNESSIONI
// ====================================================================
$pdo_lettura = null;
$pdo         = null;

?>