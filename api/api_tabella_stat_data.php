<?php
/**
 * ============================================================================
 * TABELLA STATISTICHE PERIODICHE - DATA LAYER - api_tabella_sta_data.php
 * ============================================================================
 *
 * RESPONSABILITA':
 * - Recupera dati aggregati da dati_meteo_giornaliero_simignano
 * - Recupera dati pioggia da pluvio_giornaliero
 * - Restituisce array strutturato per il rendering orizzontale
 *
 * COLONNE TEMPORALI:
 *   oggi        : giorno corrente
 *   periodo10   : range [oggi-20, oggi-11] (10 giorni prima, finestra 10gg)
 *   mese        : mese corrente (dal 1 al giorno corrente)
 *   anno        : anno corrente (dal 1 gennaio al giorno corrente)
 *
 * FONTE DATI:
 *   - dati_meteo_giornaliero_simignano : temperatura, pressione, vento, radianza
 *   - pluvio_giornaliero               : pioggia (cumulato_24h, data)
 */

require_once __DIR__ . '/../../envelop_lettura.php';
require_once __DIR__ . '/../datetime_helper.php';
require_once __DIR__ . '/../env_tables_helper.php';

/**
 * Converte gradi in direzione testuale (16 settori)
 * Identica alla funzione in api_tabella_home_data.php
 */
function statDirTesto($deg): string
{
    if ($deg === null || $deg === '' || !is_numeric($deg)) {
        return '--';
    }
    $deg = fmod((float)$deg, 360.0);
    if ($deg < 0) $deg += 360.0;
    $dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
    $i = (int) round($deg / 22.5) % 16;
    return $dirs[$i];
}

/**
 * Formatta data nel formato "3mar" o "3mar26" se anno diverso
 */
function statFmtData(?string $data_sql, string $oggi_anno): string
{
    if (!$data_sql) return '';
    try {
        $dt = new DateTime($data_sql);
        $mesi = ['','gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
        $giorno = (int)$dt->format('j');
        $mese   = $mesi[(int)$dt->format('n')];
        $anno   = $dt->format('Y');
        // Anno sempre mostrato accanto a giorno/mese
        return $giorno . $mese . substr($anno, 2);
    } catch (Exception $e) {
        return '';
    }
}

/** Differenza sicura tra due valori (gestisce N/D e sentinelle) */
function statDiffValore($now, $prev)
{
    if ($now === null || $prev === null || $now === false || $prev === false) return null;
    if (!is_numeric($now) || !is_numeric($prev)) return null;
    $now = (float)$now; $prev = (float)$prev;
    if ($now == 9999 || $now < -30 || $now > 9990) return null;
    if ($prev == 9999 || $prev < -30 || $prev > 9990) return null;
    return $now - $prev;
}

/** Sottrae esattamente un anno da una data, gestendo il 29 febbraio */
function statSottraiAnno(string $data): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $data);
    $y = (int)$dt->format('Y') - 1;
    $m = (int)$dt->format('m');
    $d = (int)$dt->format('d');
    if ($m == 2 && $d == 29 && !checkdate(2, 29, $y)) $d = 28;
    return sprintf('%04d-%02d-%02d', $y, $m, $d);
}
/**
 * Calcola direzione dominante aggregata su un periodo
 * (moda per settore, ponderata sul numero di record per giorno)
 *
 * Dato che nella tabella giornaliera abbiamo gia' la dir dominante giornaliera
 * come campo scalare, usiamo la moda delle direzioni giornaliere nel periodo.
 */
function statVentoDominante(array $rows_periodo): array
{
    if (empty($rows_periodo)) {
        return ['dir_testo' => '--', 'perc' => null, 'kmh' => null];
    }

    // Accumula voti per settore (ogni giorno vota il proprio settore dominante)
    $voti = [];
    $velocita = [];
    foreach ($rows_periodo as $row) {
        if ($row['vento_dir_dom_deg'] === null || $row['vento_dir_dom_deg'] < 0 || $row['vento_dir_dom_deg'] > 360) continue;
        $settore = (int) round((float)$row['vento_dir_dom_deg'] / 22.5) % 16;
        $voti[$settore] = ($voti[$settore] ?? 0) + 1;
        if ($row['vento_dom_kmh'] !== null && $row['vento_dom_kmh'] >= 0 && $row['vento_dom_kmh'] <= 160) {
            $velocita[$settore][] = (float)$row['vento_dom_kmh'];
        }
    }

    if (empty($voti)) {
        return ['dir_testo' => '--', 'perc' => null, 'kmh' => null];
    }

    arsort($voti);
    $settore_dom = array_key_first($voti);
    $totale_giorni = array_sum($voti);
    $perc = $totale_giorni > 0 ? (int)round($voti[$settore_dom] / $totale_giorni * 100) : null;
    $deg_dom = $settore_dom * 22.5;

    $kmh = null;
    if (!empty($velocita[$settore_dom])) {
        $kmh = round(array_sum($velocita[$settore_dom]) / count($velocita[$settore_dom]), 1);
    }

    return [
        'dir_testo' => statDirTesto($deg_dom),
        'perc'      => $perc,
        'kmh'       => $kmh
    ];
}
/**xxxxxx
 * Classifica una differenza numerica in una delle 5 fasce, restituendo colore e etichetta.
 * Ordine dei controlli: dal caso piu' estremo al piu' centrale, ciascun ramo assume
 * che i precedenti siano gia' falliti (catena if/elseif = fasce mutuamente esclusive).
 */
function statColoreDiff(?float $val): array
{
    if ($val === null) return ['colore' => '#999999', 'label' => 'N/D'];
    if ($val > 2)      return ['colore' => '#b30000', 'label' => '> +2'];
    if ($val >= 0.6)   return ['colore' => '#e08a00', 'label' => '+0.6 / +2'];
    if ($val >= -0.5)  return ['colore' => '#2e9e44', 'label' => '-0.5 / +0.5'];
    if ($val >= -2)    return ['colore' => '#3366cc', 'label' => '-0.6 / -2'];
    return                    ['colore' => '#001f66', 'label' => '< -2'];
}
/**
 * Recupera tutti i dati statistici per i 4 periodi
 *
 * @param PDO $pdo_lettura
 * @return array ['success' => bool, 'periodi' => [...], 'righe' => [...]]
 */
function getStatData(?string $data_forzata = null, bool $ignora_altri_get = false): array
{
    // global deve stare all'inizio della funzione, non dentro un if
    global $pdo_lettura;
    if (!($pdo_lettura instanceof PDO)) {
        return ['success' => false, 'error' => 'Connessione database non disponibile'];
    }

    $table_g  = table_name('dati_meteo_giornaliero_simignano');
    $table_p  = table_name('pluvio_giornaliero');

    $oggi_reale = get_now('Y-m-d');
    // Default: ieri (i dati giornalieri aggregati si completano a fine giornata)
    $oggi      = date('Y-m-d', strtotime($oggi_reale . ' -1 day'));

    // Parametri GET per navigazione temporale
    // ?data=YYYY-MM-DD       -> mostra quel giorno specifico
    // ?p10_centro=YYYY-MM-DD -> centro del periodo 10gg
    // ?mese=YYYY-MM          -> mostra quel mese
    // ?anno=YYYY             -> mostra quell'anno
    $data_richiesta = $data_forzata !== null ? $data_forzata : ($_GET['data'] ?? null);
    if (!empty($data_richiesta)) {
        $d = DateTime::createFromFormat('Y-m-d', $data_richiesta);
        if ($d && $d->format('Y-m-d') <= $oggi_reale) $oggi = $d->format('Y-m-d');
    }
    $oggi_anno = (new DateTime($oggi))->format('Y');

    // Range periodi
    if (!$ignora_altri_get && !empty($_GET['p10_centro'])) {
        $dc = DateTime::createFromFormat('Y-m-d', $_GET['p10_centro']);
        if ($dc) {
            $p10_fine   = $dc->format('Y-m-d');
            $p10_inizio = date('Y-m-d', strtotime($p10_fine . ' -9 days'));
        } else {
            $p10_fine   = date('Y-m-d', strtotime($oggi . ' -11 days'));
            $p10_inizio = date('Y-m-d', strtotime($oggi . ' -20 days'));
        }
    } else {
        // Ultimi 10 giorni: da 10 giorni fa fino a ieri
        $p10_fine   = date('Y-m-d', strtotime($oggi . ' -1 day'));
        $p10_inizio = date('Y-m-d', strtotime($oggi . ' -10 days'));
    }

    if (!$ignora_altri_get && !empty($_GET['mese'])) {
        $dm = DateTime::createFromFormat('Y-m-d', $_GET['mese'] . '-01');
        if ($dm) {
            $mese_inizio = $dm->format('Y-m-01');
            $mese_fine_raw = $dm->format('Y-m-t');
            // Non andare oltre ieri (oggi_reale sarebbe incompleto)
            $mese_fine = ($mese_fine_raw > $oggi_reale) ? $oggi : $mese_fine_raw;
        } else {
            $mese_inizio = date('Y-m-01', strtotime($oggi));
            $mese_fine   = $oggi;
        }
    } else {
        $mese_inizio = date('Y-m-01', strtotime($oggi));
        $mese_fine   = $oggi;
    }

    if (!$ignora_altri_get && !empty($_GET['anno'])) {
        $anno_sel = (int)$_GET['anno'];
        if ($anno_sel >= 2020 && $anno_sel <= (int)$oggi_anno) {
            $anno_inizio = $anno_sel . '-01-01';
            $anno_fine_raw = $anno_sel . '-12-31';
            $anno_fine = ($anno_fine_raw > $oggi_reale) ? $oggi : $anno_fine_raw;
        } else {
            $anno_inizio = date('Y-01-01', strtotime($oggi));
            $anno_fine   = $oggi;
        }
    } else {
        $anno_inizio = date('Y-01-01', strtotime($oggi));
        $anno_fine   = $oggi;
    }

    // Alias per compatibilita' con il codice sottostante
    $oggi_orig   = $oggi; // giorno mostrato come "ieri" (default: ieri reale)

    // ========================================================================
    // QUERY AGGREGATA: una per periodo per la tabella giornaliero
    // Recupero righe grezze per calcoli flessibili (vento dominante aggregato)
    // ========================================================================

    try {
        // --- OGGI ---
        $stmt = $pdo_lettura->prepare("
            SELECT *
            FROM $table_g
            WHERE data_giorno = :oggi
            LIMIT 1
        ");
        $stmt->execute([':oggi' => $oggi_orig]);
        $oggi_row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // --- PERIODO 10GG ---
        $stmt = $pdo_lettura->prepare("
            SELECT *
            FROM $table_g
            WHERE data_giorno BETWEEN :inizio AND :fine
            ORDER BY data_giorno ASC
        ");
        $stmt->execute([':inizio' => $p10_inizio, ':fine' => $p10_fine]);
        $rows_10gg = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Aggregati periodo 10gg
        $stmt = $pdo_lettura->prepare("
            SELECT
                AVG(CASE WHEN temp_media  BETWEEN -30 AND 50 THEN temp_media  END)  AS t_media,
                MAX(CASE WHEN temp_max_abs BETWEEN -30 AND 50 THEN temp_max_abs END) AS t_max,
                MIN(CASE WHEN temp_min_abs BETWEEN -30 AND 50 THEN temp_min_abs END) AS t_min,
                AVG(CASE WHEN temp_max_abs BETWEEN -30 AND 50 THEN temp_max_abs END) AS t_max_media,
                AVG(CASE WHEN temp_min_abs BETWEEN -30 AND 50 THEN temp_min_abs END) AS t_min_media,
                AVG(CASE WHEN press_media  BETWEEN 970 AND 1060 THEN press_media  END) AS p_media,
                MAX(CASE WHEN press_max    BETWEEN 970 AND 1060 THEN press_max    END) AS p_max,
                MIN(CASE WHEN press_min    BETWEEN 970 AND 1060 THEN press_min    END) AS p_min,
                AVG(CASE WHEN rad_percent_24h IS NOT NULL THEN rad_percent_24h END) AS rad_media,

                -- Data del massimo e minimo assoluti
                (SELECT data_giorno FROM $table_g
                 WHERE data_giorno BETWEEN :i1 AND :f1
                   AND temp_max_abs = (
                       SELECT MAX(temp_max_abs) FROM $table_g
                       WHERE data_giorno BETWEEN :i2 AND :f2
                         AND temp_max_abs BETWEEN -30 AND 50
                   )
                   AND temp_max_abs BETWEEN -30 AND 50
                 ORDER BY data_giorno ASC LIMIT 1) AS t_max_data,

                (SELECT data_giorno FROM $table_g
                 WHERE data_giorno BETWEEN :i3 AND :f3
                   AND temp_min_abs = (
                       SELECT MIN(temp_min_abs) FROM $table_g
                       WHERE data_giorno BETWEEN :i4 AND :f4
                         AND temp_min_abs BETWEEN -30 AND 50
                   )
                   AND temp_min_abs BETWEEN -30 AND 50
                 ORDER BY data_giorno ASC LIMIT 1) AS t_min_data,

                (SELECT data_giorno FROM $table_g
                 WHERE data_giorno BETWEEN :i5 AND :f5
                   AND press_max = (
                       SELECT MAX(press_max) FROM $table_g
                       WHERE data_giorno BETWEEN :i6 AND :f6
                         AND press_max    BETWEEN 970 AND 1060
                   )
                   AND press_max    BETWEEN 970 AND 1060
                 ORDER BY data_giorno ASC LIMIT 1) AS p_max_data,

                (SELECT data_giorno FROM $table_g
                 WHERE data_giorno BETWEEN :i7 AND :f7
                   AND press_min = (
                       SELECT MIN(press_min) FROM $table_g
                       WHERE data_giorno BETWEEN :i8 AND :f8
                         AND press_min    BETWEEN 970 AND 1060
                   )
                   AND press_min    BETWEEN 970 AND 1060
                 ORDER BY data_giorno ASC LIMIT 1) AS p_min_data

            FROM $table_g
            WHERE data_giorno BETWEEN :inizio AND :fine
        ");
        $stmt->execute([
            ':inizio' => $p10_inizio, ':fine' => $p10_fine,
            ':i1' => $p10_inizio, ':f1' => $p10_fine,
            ':i2' => $p10_inizio, ':f2' => $p10_fine,
            ':i3' => $p10_inizio, ':f3' => $p10_fine,
            ':i4' => $p10_inizio, ':f4' => $p10_fine,
            ':i5' => $p10_inizio, ':f5' => $p10_fine,
            ':i6' => $p10_inizio, ':f6' => $p10_fine,
            ':i7' => $p10_inizio, ':f7' => $p10_fine,
            ':i8' => $p10_inizio, ':f8' => $p10_fine,
        ]);
        $agg_10gg = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // --- MESE ---
        $stmt = $pdo_lettura->prepare("SELECT * FROM $table_g WHERE data_giorno BETWEEN :inizio AND :fine ORDER BY data_giorno ASC");
        $stmt->execute([':inizio' => $mese_inizio, ':fine' => $mese_fine]);
        $rows_mese = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo_lettura->prepare("
            SELECT
                AVG(CASE WHEN temp_media   BETWEEN -30 AND 50 THEN temp_media   END) AS t_media,
                MAX(CASE WHEN temp_max_abs BETWEEN -30 AND 50 THEN temp_max_abs END) AS t_max,
                MIN(CASE WHEN temp_min_abs BETWEEN -30 AND 50 THEN temp_min_abs END) AS t_min,
                AVG(CASE WHEN temp_max_abs BETWEEN -30 AND 50 THEN temp_max_abs END) AS t_max_media,
                AVG(CASE WHEN temp_min_abs BETWEEN -30 AND 50 THEN temp_min_abs END) AS t_min_media,
                AVG(CASE WHEN press_media  BETWEEN 970 AND 1060 THEN press_media  END) AS p_media,
                MAX(CASE WHEN press_max    BETWEEN 970 AND 1060 THEN press_max    END) AS p_max,
                MIN(CASE WHEN press_min    BETWEEN 970 AND 1060 THEN press_min    END) AS p_min,
                AVG(CASE WHEN rad_percent_24h IS NOT NULL THEN rad_percent_24h END) AS rad_media,
                (SELECT data_giorno FROM $table_g WHERE data_giorno BETWEEN :i1 AND :f1 AND temp_max_abs = (SELECT MAX(temp_max_abs) FROM $table_g WHERE data_giorno BETWEEN :i2 AND :f2 AND temp_max_abs BETWEEN -30 AND 50) AND temp_max_abs BETWEEN -30 AND 50 ORDER BY data_giorno ASC LIMIT 1) AS t_max_data,
                (SELECT data_giorno FROM $table_g WHERE data_giorno BETWEEN :i3 AND :f3 AND temp_min_abs = (SELECT MIN(temp_min_abs) FROM $table_g WHERE data_giorno BETWEEN :i4 AND :f4 AND temp_min_abs BETWEEN -30 AND 50) AND temp_min_abs BETWEEN -30 AND 50 ORDER BY data_giorno ASC LIMIT 1) AS t_min_data,
                (SELECT data_giorno FROM $table_g WHERE data_giorno BETWEEN :i5 AND :f5 AND press_max = (SELECT MAX(press_max) FROM $table_g WHERE data_giorno BETWEEN :i6 AND :f6 AND press_max    BETWEEN 970 AND 1060) AND press_max    BETWEEN 970 AND 1060 ORDER BY data_giorno ASC LIMIT 1) AS p_max_data,
                (SELECT data_giorno FROM $table_g WHERE data_giorno BETWEEN :i7 AND :f7 AND press_min = (SELECT MIN(press_min) FROM $table_g WHERE data_giorno BETWEEN :i8 AND :f8 AND press_min    BETWEEN 970 AND 1060) AND press_min    BETWEEN 970 AND 1060 ORDER BY data_giorno ASC LIMIT 1) AS p_min_data
            FROM $table_g
            WHERE data_giorno BETWEEN :inizio AND :fine
        ");
        $stmt->execute([
            ':inizio' => $mese_inizio, ':fine' => $mese_fine,
            ':i1' => $mese_inizio, ':f1' => $mese_fine, ':i2' => $mese_inizio, ':f2' => $mese_fine,
            ':i3' => $mese_inizio, ':f3' => $mese_fine, ':i4' => $mese_inizio, ':f4' => $mese_fine,
            ':i5' => $mese_inizio, ':f5' => $mese_fine, ':i6' => $mese_inizio, ':f6' => $mese_fine,
            ':i7' => $mese_inizio, ':f7' => $mese_fine, ':i8' => $mese_inizio, ':f8' => $mese_fine,
        ]);
        $agg_mese = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // --- ANNO ---
        $stmt = $pdo_lettura->prepare("SELECT * FROM $table_g WHERE data_giorno BETWEEN :inizio AND :fine ORDER BY data_giorno ASC");
        $stmt->execute([':inizio' => $anno_inizio, ':fine' => $anno_fine]);
        $rows_anno = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo_lettura->prepare("
            SELECT
                AVG(CASE WHEN temp_media   BETWEEN -30 AND 50 THEN temp_media   END) AS t_media,
                MAX(CASE WHEN temp_max_abs BETWEEN -30 AND 50 THEN temp_max_abs END) AS t_max,
                MIN(CASE WHEN temp_min_abs BETWEEN -30 AND 50 THEN temp_min_abs END) AS t_min,
                AVG(CASE WHEN temp_max_abs BETWEEN -30 AND 50 THEN temp_max_abs END) AS t_max_media,
                AVG(CASE WHEN temp_min_abs BETWEEN -30 AND 50 THEN temp_min_abs END) AS t_min_media,
                AVG(CASE WHEN press_media  BETWEEN 970 AND 1060 THEN press_media  END) AS p_media,
                MAX(CASE WHEN press_max    BETWEEN 970 AND 1060 THEN press_max    END) AS p_max,
                MIN(CASE WHEN press_min    BETWEEN 970 AND 1060 THEN press_min    END) AS p_min,
                AVG(CASE WHEN rad_percent_24h IS NOT NULL THEN rad_percent_24h END) AS rad_media,
                (SELECT data_giorno FROM $table_g WHERE data_giorno BETWEEN :i1 AND :f1 AND temp_max_abs = (SELECT MAX(temp_max_abs) FROM $table_g WHERE data_giorno BETWEEN :i2 AND :f2 AND temp_max_abs BETWEEN -30 AND 50) AND temp_max_abs BETWEEN -30 AND 50 ORDER BY data_giorno ASC LIMIT 1) AS t_max_data,
                (SELECT data_giorno FROM $table_g WHERE data_giorno BETWEEN :i3 AND :f3 AND temp_min_abs = (SELECT MIN(temp_min_abs) FROM $table_g WHERE data_giorno BETWEEN :i4 AND :f4 AND temp_min_abs BETWEEN -30 AND 50) AND temp_min_abs BETWEEN -30 AND 50 ORDER BY data_giorno ASC LIMIT 1) AS t_min_data,
                (SELECT data_giorno FROM $table_g WHERE data_giorno BETWEEN :i5 AND :f5 AND press_max = (SELECT MAX(press_max) FROM $table_g WHERE data_giorno BETWEEN :i6 AND :f6 AND press_max    BETWEEN 970 AND 1060) AND press_max    BETWEEN 970 AND 1060 ORDER BY data_giorno ASC LIMIT 1) AS p_max_data,
                (SELECT data_giorno FROM $table_g WHERE data_giorno BETWEEN :i7 AND :f7 AND press_min = (SELECT MIN(press_min) FROM $table_g WHERE data_giorno BETWEEN :i8 AND :f8 AND press_min    BETWEEN 970 AND 1060) AND press_min    BETWEEN 970 AND 1060 ORDER BY data_giorno ASC LIMIT 1) AS p_min_data
            FROM $table_g
            WHERE data_giorno BETWEEN :inizio AND :fine
        ");
        $stmt->execute([
            ':inizio' => $anno_inizio, ':fine' => $anno_fine,
            ':i1' => $anno_inizio, ':f1' => $anno_fine, ':i2' => $anno_inizio, ':f2' => $anno_fine,
            ':i3' => $anno_inizio, ':f3' => $anno_fine, ':i4' => $anno_inizio, ':f4' => $anno_fine,
            ':i5' => $anno_inizio, ':f5' => $anno_fine, ':i6' => $anno_inizio, ':f6' => $anno_fine,
            ':i7' => $anno_inizio, ':f7' => $anno_fine, ':i8' => $anno_inizio, ':f8' => $anno_fine,
        ]);
        $agg_anno = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    } catch (PDOException $e) {
        error_log("getStatData giornaliero: " . $e->getMessage());
        return ['success' => false, 'error' => 'Errore DB giornaliero: ' . $e->getMessage()];
    }

    // ========================================================================
    // QUERY PIOGGIA (pluvio_giornaliero)
    // ========================================================================
    try {
        // Pioggia oggi
        $stmt = $pdo_lettura->prepare("SELECT cumulato_24h FROM $table_p WHERE DATE(data) = :oggi ORDER BY data DESC LIMIT 1");
        $stmt->execute([':oggi' => $oggi_orig]);
        $pioggia_oggi = $stmt->fetchColumn();

        // Pioggia periodo 10gg
        $stmt = $pdo_lettura->prepare("
            SELECT SUM(cumulato_24h) AS tot, COUNT(CASE WHEN cumulato_24h >= 1 THEN 1 END) AS gg_pioggia
            FROM $table_p
            WHERE DATE(data) BETWEEN :inizio AND :fine
        ");
        $stmt->execute([':inizio' => $p10_inizio, ':fine' => $p10_fine]);
        $pioggia_10gg = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Pioggia mese
        $stmt = $pdo_lettura->prepare("
            SELECT SUM(cumulato_24h) AS tot, COUNT(CASE WHEN cumulato_24h >= 1 THEN 1 END) AS gg_pioggia
            FROM $table_p
            WHERE DATE(data) BETWEEN :inizio AND :fine
        ");
        $stmt->execute([':inizio' => $mese_inizio, ':fine' => $mese_fine]);
        $pioggia_mese = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Pioggia anno
        $stmt = $pdo_lettura->prepare("
            SELECT SUM(cumulato_24h) AS tot, COUNT(CASE WHEN cumulato_24h >= 1 THEN 1 END) AS gg_pioggia
            FROM $table_p
            WHERE DATE(data) BETWEEN :inizio AND :fine
        ");
        $stmt->execute([':inizio' => $anno_inizio, ':fine' => $anno_fine]);
        $pioggia_anno = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    } catch (PDOException $e) {
        error_log("getStatData pluvio: " . $e->getMessage());
        // Non blocca: pioggia sara' N/A
        $pioggia_oggi = null;
        $pioggia_10gg = [];
        $pioggia_mese = [];
        $pioggia_anno = [];
    }

    // ========================================================================
    // VENTO DOMINANTE AGGREGATO (moda su righe grezze)
    // ========================================================================
    $vento_10gg = statVentoDominante($rows_10gg);
    $vento_mese = statVentoDominante($rows_mese);
    $vento_anno = statVentoDominante($rows_anno);

    // Vento dominante oggi (diretto dal record giornaliero)
    $vento_oggi = [
        'dir_testo' => ($oggi_row && isset($oggi_row['vento_dir_dom_deg'])
                        && $oggi_row['vento_dir_dom_deg'] >= 0 && $oggi_row['vento_dir_dom_deg'] <= 360)
            ? statDirTesto($oggi_row['vento_dir_dom_deg']) : '--',
        'perc'      => ($oggi_row && isset($oggi_row['vento_dir_dom_perc'])) ? $oggi_row['vento_dir_dom_perc'] : null,
        'kmh'       => ($oggi_row && isset($oggi_row['vento_dom_kmh'])
                        && $oggi_row['vento_dom_kmh'] >= 0 && $oggi_row['vento_dom_kmh'] <= 160)
            ? $oggi_row['vento_dom_kmh'] : null,
    ];

    // ========================================================================
    // COPERTURA DATI (record effettivi / giorni attesi nel periodo)
    // Soglia 75%: se copertura < 0.75 il dato e' marcato con asterisco
    // ========================================================================
    $giorni_10gg  = 10;
    // Giorni attesi: sempre sul periodo naturale intero (fine_raw), non troncato a ieri
    // Cosi' un mese o anno in corso con pochi dati mostra correttamente asterisco
    $mese_fine_raw_cov = date('Y-m-t', strtotime($mese_inizio));
    $anno_fine_raw_cov = substr($anno_inizio, 0, 4) . '-12-31';
    $giorni_mese  = (int)((new DateTime($mese_inizio))->diff(new DateTime($mese_fine_raw_cov))->days) + 1;
    $giorni_anno  = (int)((new DateTime($anno_inizio))->diff(new DateTime($anno_fine_raw_cov))->days) + 1;

    $copertura = [
        'oggi' => !empty($oggi_row) ? 1.0 : 0.0,
        'p10'  => $giorni_10gg  > 0 ? count($rows_10gg)  / $giorni_10gg  : 0.0,
        'mese' => $giorni_mese  > 0 ? count($rows_mese)  / $giorni_mese  : 0.0,
        'anno' => $giorni_anno  > 0 ? count($rows_anno)  / $giorni_anno  : 0.0,
    ];

    // ========================================================================
    // HELPER LOCALI
    // ========================================================================

    /**
     * Formatta un valore numerico con unita', o 'N/D' se null/sentinella
     */
    $fv = function($val, string $unit = '', int $dec = 1) {
        if ($val === null || $val === false || (is_numeric($val) && ((float)$val == 9999 || (float)$val < -30 || (float)$val > 9990))) return 'N/D';
        return number_format((float)$val, $dec, '.', '') . $unit;
    };

    /**
     * Formatta temperatura con data opzionale: "11.3 (3mar)"
     */
    $fvdata = function($val, ?string $data_sql, string $unit = ' &#176;C') use ($fv, $oggi_anno): string {
        $base = $fv($val, $unit, 1);
        if ($base === 'N/D' || !$data_sql) return $base;
        $dataFmt = statFmtData($data_sql, $oggi_anno);
        return $dataFmt ? $base . ' <span class="stat-data-date">(' . $dataFmt . ')</span>' : $base;
    };

    /**
     * Formatta vento: "ESE (36%)" oppure "--"
     */
    $fvento = function(array $v, bool $con_kmh = false) use ($fv): string {
        if ($v['dir_testo'] === '--') return '--';
        $str = $v['dir_testo'];
        if ($v['perc'] !== null) $str .= ' (' . $v['perc'] . '%)';
        if ($con_kmh && $v['kmh'] !== null) $str .= ' ' . $fv($v['kmh'], ' km/h', 1);
        return $str;
    };

    // Intestazioni colonne periodo
    $dt_oggi = new DateTime($oggi_orig);
    $mesi_it = ['','gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
    $label_oggi = $dt_oggi->format('j') . ' ' . $mesi_it[(int)$dt_oggi->format('n')] . ' ' . $dt_oggi->format('Y');

    $dt_p10_i = new DateTime($p10_inizio);
    $dt_p10_f = new DateTime($p10_fine);
    $label_10gg = $dt_p10_i->format('j') . '-' . $dt_p10_f->format('j') . ' ' . $mesi_it[(int)$dt_p10_f->format('n')] . ' ' . $dt_p10_f->format('Y');

    $mesi_it_long = ['','gennaio','febbraio','marzo','aprile','maggio','giugno',
                     'luglio','agosto','settembre','ottobre','novembre','dicembre'];
    $dt_mese_f = new DateTime($mese_fine);
    $label_mese = $mesi_it_long[(int)$dt_mese_f->format('n')] . ' ' . $dt_mese_f->format('Y');
    $dt_anno_f = new DateTime($anno_fine);
    $label_anno = $dt_anno_f->format('Y');

    // ========================================================================
    // COSTRUZIONE RIGHE
    // ========================================================================

    // Radianza: oggi (ultimo valore disponibile da tabella giornaliero)
    $rad_oggi  = ($oggi_row && isset($oggi_row['rad_percent_24h'])) ? $oggi_row['rad_percent_24h'] : null;

        $righe = [
        [
            'label'     => 'Data',
            'oggi'      => $label_oggi,
            'p10'       => $label_10gg,
            'mese'      => $label_mese,
            'anno'      => $label_anno,
            'grigio'    => false,
            'separatore'=> true,
        ],
        [
            'label'  => 'T media',
            'oggi'   => $fv($oggi_row['temp_media'] ?? null, ' &#176;C'),
            'p10'    => $fv($agg_10gg['t_media']  ?? null, ' &#176;C'),
            'mese'   => $fv($agg_mese['t_media']  ?? null, ' &#176;C'),
            'anno'   => $fv($agg_anno['t_media']  ?? null, ' &#176;C'),
            'raw'    => ['oggi'=>$oggi_row['temp_media'] ?? null, 'p10'=>$agg_10gg['t_media'] ?? null, 'mese'=>$agg_mese['t_media'] ?? null, 'anno'=>$agg_anno['t_media'] ?? null],
            'unit'   => ' &#176;C', 'dec' => 1,
            'grigio' => false,
        ],
        [
            'label'  => 'Max abs',
            'oggi'   => $fv($oggi_row['temp_max_abs'] ?? null, ' &#176;C'),
            'p10'    => $fvdata($agg_10gg['t_max'] ?? null, $agg_10gg['t_max_data'] ?? null),
            'mese'   => $fvdata($agg_mese['t_max'] ?? null, $agg_mese['t_max_data'] ?? null),
            'anno'   => $fvdata($agg_anno['t_max'] ?? null, $agg_anno['t_max_data'] ?? null),
            'raw'    => ['oggi'=>$oggi_row['temp_max_abs'] ?? null, 'p10'=>$agg_10gg['t_max'] ?? null, 'mese'=>$agg_mese['t_max'] ?? null, 'anno'=>$agg_anno['t_max'] ?? null],
            'unit'   => ' &#176;C', 'dec' => 1,
            'grigio' => false,
        ],
        [
            'label'  => 'Min abs',
            'oggi'   => $fv($oggi_row['temp_min_abs'] ?? null, ' &#176;C'),
            'p10'    => $fvdata($agg_10gg['t_min'] ?? null, $agg_10gg['t_min_data'] ?? null),
            'mese'   => $fvdata($agg_mese['t_min'] ?? null, $agg_mese['t_min_data'] ?? null),
            'anno'   => $fvdata($agg_anno['t_min'] ?? null, $agg_anno['t_min_data'] ?? null),
            'raw'    => ['oggi'=>$oggi_row['temp_min_abs'] ?? null, 'p10'=>$agg_10gg['t_min'] ?? null, 'mese'=>$agg_mese['t_min'] ?? null, 'anno'=>$agg_anno['t_min'] ?? null],
            'unit'   => ' &#176;C', 'dec' => 1,
            'grigio' => false,
        ],
        [
            'label'  => 'Max media',
            'oggi'   => '&mdash;',
            'p10'    => $fv($agg_10gg['t_max_media'] ?? null, ' &#176;C'),
            'mese'   => $fv($agg_mese['t_max_media'] ?? null, ' &#176;C'),
            'anno'   => $fv($agg_anno['t_max_media'] ?? null, ' &#176;C'),
            'raw'    => ['oggi'=>null, 'p10'=>$agg_10gg['t_max_media'] ?? null, 'mese'=>$agg_mese['t_max_media'] ?? null, 'anno'=>$agg_anno['t_max_media'] ?? null],
            'unit'   => ' &#176;C', 'dec' => 1,
            'grigio' => true,
        ],
        [
            'label'  => 'Min media',
            'oggi'   => '&mdash;',
            'p10'    => $fv($agg_10gg['t_min_media'] ?? null, ' &#176;C'),
            'mese'   => $fv($agg_mese['t_min_media'] ?? null, ' &#176;C'),
            'anno'   => $fv($agg_anno['t_min_media'] ?? null, ' &#176;C'),
            'raw'    => ['oggi'=>null, 'p10'=>$agg_10gg['t_min_media'] ?? null, 'mese'=>$agg_mese['t_min_media'] ?? null, 'anno'=>$agg_anno['t_min_media'] ?? null],
            'unit'   => ' &#176;C', 'dec' => 1,
            'grigio' => true,
            'separatore' => true,
        ],
        [
            'label'  => 'P media',
            'oggi'   => $fv($oggi_row['press_media'] ?? null, ' hPa'),
            'p10'    => $fv($agg_10gg['p_media']    ?? null, ' hPa'),
            'mese'   => $fv($agg_mese['p_media']    ?? null, ' hPa'),
            'anno'   => $fv($agg_anno['p_media']    ?? null, ' hPa'),
            'raw'    => ['oggi'=>$oggi_row['press_media'] ?? null, 'p10'=>$agg_10gg['p_media'] ?? null, 'mese'=>$agg_mese['p_media'] ?? null, 'anno'=>$agg_anno['p_media'] ?? null],
            'unit'   => ' hPa', 'dec' => 1,
            'grigio' => false,
        ],
        [
            'label'  => 'P max',
            'oggi'   => $fv($oggi_row['press_max'] ?? null, ' hPa'),
            'p10'    => $fvdata($agg_10gg['p_max'] ?? null, $agg_10gg['p_max_data'] ?? null, ' hPa'),
            'mese'   => $fvdata($agg_mese['p_max'] ?? null, $agg_mese['p_max_data'] ?? null, ' hPa'),
            'anno'   => $fvdata($agg_anno['p_max'] ?? null, $agg_anno['p_max_data'] ?? null, ' hPa'),
            'raw'    => ['oggi'=>$oggi_row['press_max'] ?? null, 'p10'=>$agg_10gg['p_max'] ?? null, 'mese'=>$agg_mese['p_max'] ?? null, 'anno'=>$agg_anno['p_max'] ?? null],
            'unit'   => ' hPa', 'dec' => 1,
            'grigio' => false,
        ],
        [
            'label'  => 'P min',
            'oggi'   => $fv($oggi_row['press_min'] ?? null, ' hPa'),
            'p10'    => $fvdata($agg_10gg['p_min'] ?? null, $agg_10gg['p_min_data'] ?? null, ' hPa'),
            'mese'   => $fvdata($agg_mese['p_min'] ?? null, $agg_mese['p_min_data'] ?? null, ' hPa'),
            'anno'   => $fvdata($agg_anno['p_min'] ?? null, $agg_anno['p_min_data'] ?? null, ' hPa'),
            'raw'    => ['oggi'=>$oggi_row['press_min'] ?? null, 'p10'=>$agg_10gg['p_min'] ?? null, 'mese'=>$agg_mese['p_min'] ?? null, 'anno'=>$agg_anno['p_min'] ?? null],
            'unit'   => ' hPa', 'dec' => 1,
            'grigio' => false,
            'separatore' => true,
        ],
        [
            'label'  => 'Vdom',
            'oggi'   => $fvento($vento_oggi),
            'p10'    => $fvento($vento_10gg),
            'mese'   => $fvento($vento_mese),
            'anno'   => $fvento($vento_anno),
            'raw'    => ['oggi'=>null, 'p10'=>null, 'mese'=>null, 'anno'=>null],
            'grigio' => false,
        ],
        [
            'label'  => 'Vdom km/h',
            'oggi'   => $fv($vento_oggi['kmh'] ?? null, ' km/h'),
            'p10'    => $fv($vento_10gg['kmh'] ?? null, ' km/h'),
            'mese'   => $fv($vento_mese['kmh'] ?? null, ' km/h'),
            'anno'   => $fv($vento_anno['kmh'] ?? null, ' km/h'),
            'raw'    => ['oggi'=>$vento_oggi['kmh'] ?? null, 'p10'=>$vento_10gg['kmh'] ?? null, 'mese'=>$vento_mese['kmh'] ?? null, 'anno'=>$vento_anno['kmh'] ?? null],
            'unit'   => ' km/h', 'dec' => 1,
            'grigio' => false,
            'separatore' => true,
        ],
        [
            'label'  => 'Pioggia cumulato',
            'oggi'   => $fv($pioggia_oggi !== false ? $pioggia_oggi : null, ' mm'),
            'p10'    => $fv($pioggia_10gg['tot'] ?? null, ' mm'),
            'mese'   => $fv($pioggia_mese['tot'] ?? null, ' mm'),
            'anno'   => $fv($pioggia_anno['tot'] ?? null, ' mm'),
            'raw'    => ['oggi'=>$pioggia_oggi !== false ? $pioggia_oggi : null, 'p10'=>$pioggia_10gg['tot'] ?? null, 'mese'=>$pioggia_mese['tot'] ?? null, 'anno'=>$pioggia_anno['tot'] ?? null],
            'unit'   => ' mm', 'dec' => 1,
            'grigio' => false,
        ],
        [
            'label'  => 'Gg pioggia (&ge;1mm)',
            'oggi'   => '&mdash;',
            'p10'    => $fv($pioggia_10gg['gg_pioggia'] ?? null, ' gg', 0),
            'mese'   => $fv($pioggia_mese['gg_pioggia'] ?? null, ' gg', 0),
            'anno'   => $fv($pioggia_anno['gg_pioggia'] ?? null, ' gg', 0),
            'raw'    => ['oggi'=>null, 'p10'=>$pioggia_10gg['gg_pioggia'] ?? null, 'mese'=>$pioggia_mese['gg_pioggia'] ?? null, 'anno'=>$pioggia_anno['gg_pioggia'] ?? null],
            'unit'   => ' gg', 'dec' => 0,
            'grigio' => true,
            'separatore' => true,
        ],
        [
            'label'  => 'Radianza media',
            'oggi'   => $fv($rad_oggi, '%', 0),
            'p10'    => $fv($agg_10gg['rad_media'] ?? null, '%', 0),
            'mese'   => $fv($agg_mese['rad_media'] ?? null, '%', 0),
            'anno'   => $fv($agg_anno['rad_media'] ?? null, '%', 0),
            'raw'    => ['oggi'=>$rad_oggi, 'p10'=>$agg_10gg['rad_media'] ?? null, 'mese'=>$agg_mese['rad_media'] ?? null, 'anno'=>$agg_anno['rad_media'] ?? null],
            'unit'   => '%', 'dec' => 0,
            'grigio' => false,
        ],
    ];

    // Etichette colonne intestazione
    $headers = [
        'label' => '',
        'oggi'  => 'oggi',
        'p10'   => '10 gg prima',
        'mese'  => 'mese',
        'anno'  => 'anno',
    ];

    return [
        'success'   => true,
        'headers'   => $headers,
        'righe'     => $righe,
        'copertura' => $copertura,
        'meta'    => [
            'oggi'       => $oggi_orig,
            'oggi_reale' => $oggi_reale,
            'p10_inizio' => $p10_inizio,
            'p10_fine'   => $p10_fine,
            'mese_inizio'=> $mese_inizio,
            'mese_fine'  => $mese_fine,
            'anno_inizio'=> $anno_inizio,
            'anno_fine'  => $anno_fine,
            'generato_il'=> get_now(),
        ],
    ];
}

// ============================================================================
// SOGLIE TERMICHE (tabella 2)
// ============================================================================
function stat2FmtDataEstesa(?string $data_sql): string
{
    if (!$data_sql) return 'N/D';
    try {
        $dt   = new DateTime($data_sql);
        $mesi = ['','gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
        return (int)$dt->format('j') . ' ' . $mesi[(int)$dt->format('n')] . ' ' . $dt->format('Y');
    } catch (Exception $e) {
        return 'N/D';
    }
}

function getStat2Data(): array
{
    global $pdo_lettura;
    if (!($pdo_lettura instanceof PDO)) {
        return ['success' => false, 'error' => 'Connessione database non disponibile'];
    }

    $table_g    = table_name('dati_meteo_giornaliero_simignano');
    $oggi_reale = get_now('Y-m-d');

    // ref_data: data di riferimento per sezione A (primo/ultimo giorno)
    $ref_data = $oggi_reale;
    if (!empty($_GET['ref_data'])) {
        $rd = DateTime::createFromFormat('Y-m-d', $_GET['ref_data']);
        if ($rd && $rd->format('Y-m-d') <= $oggi_reale) {
            $ref_data = $rd->format('Y-m-d');
        }
    }

    $anno_oggi  = (new DateTime($oggi_reale))->format('Y');
    $anno_int   = (int)$anno_oggi;
    $md_oggi    = (int)(new DateTime($oggi_reale))->format('md');

    // ========================================================================
    // FINESTRE STAGIONALI per sezione A
    // Caldo: dal 15 gen anno corrente (o precedente se prima del 15gen)
    // Freddo: dal 15 lug anno precedente
    // ========================================================================
    $y_caldo       = ($md_oggi >= 115) ? $anno_int : $anno_int - 1;
    $caldo_inizio  = $y_caldo . '-01-15';
    $caldo_fine    = $oggi_reale;
    $freddo_inizio = ($anno_int - 1) . '-07-15';
    $freddo_fine   = $oggi_reale;

    // ========================================================================
    // PERIODO per sezione B (conteggi)
    // ========================================================================
    $modo = 'anno';

    if (!empty($_GET['mese'])) {
        $dm = DateTime::createFromFormat('Y-m', $_GET['mese']);
        if ($dm) {
            $modo      = 'mese';
            $inizio    = $dm->format('Y-m-01');
            $fine_raw  = $dm->format('Y-m-t');
            $fine      = ($fine_raw > $oggi_reale) ? date('Y-m-d', strtotime($oggi_reale . ' -1 day')) : $fine_raw;
            $mesi_long = ['','gennaio','febbraio','marzo','aprile','maggio','giugno',
                          'luglio','agosto','settembre','ottobre','novembre','dicembre'];
            $label_col = $mesi_long[(int)$dm->format('n')] . ' ' . $dm->format('Y');
        } else {
            $modo = 'anno';
        }
    }

    if ($modo === 'anno') {
        $anno_sel  = $anno_oggi;
        if (!empty($_GET['anno'])) {
            $a = (int)$_GET['anno'];
            if ($a >= 2020 && $a <= (int)$anno_oggi) $anno_sel = (string)$a;
        }
        $inizio    = $anno_sel . '-01-01';
        $fine_raw  = $anno_sel . '-12-31';
        $fine      = ($fine_raw > $oggi_reale) ? date('Y-m-d', strtotime($oggi_reale . ' -1 day')) : $fine_raw;
        $label_col = $anno_sel;
    }

    // ========================================================================
    // COPERTURA sul periodo B
    // ========================================================================
    if ($modo === 'mese') {
        $fine_raw_cov = date('Y-m-t', strtotime($inizio));
    } else {
        $fine_raw_cov = substr($inizio, 0, 4) . '-12-31';
    }
    $giorni_attesi = (int)((new DateTime($inizio))->diff(new DateTime($fine_raw_cov))->days) + 1;
    try {
        $stmt = $pdo_lettura->prepare("SELECT COUNT(*) FROM $table_g WHERE data_giorno BETWEEN :i AND :f");
        $stmt->execute([':i' => $inizio, ':f' => $fine]);
        $copertura = $giorni_attesi > 0 ? (int)$stmt->fetchColumn() / $giorni_attesi : 0.0;
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Errore DB: ' . $e->getMessage()];
    }

    // ========================================================================
    // SEZIONE A: primo e ultimo giorno nella finestra stagionale
    // ========================================================================
    try {
        $ultimoGiorno = function(string $campo, string $op, float $soglia)
            use ($pdo_lettura, $table_g, $ref_data): ?string
        {
            $stmt = $pdo_lettura->prepare(
                "SELECT MAX(data_giorno) FROM $table_g
                 WHERE $campo $op :s
                   AND $campo BETWEEN -30 AND 50
                   AND data_giorno <= :rif"
            );
            $stmt->execute([':s' => $soglia, ':rif' => $ref_data]);
            return $stmt->fetchColumn() ?: null;
        };

        $primoGiorno = function(string $campo, string $op, float $soglia, string $fin_inizio)
            use ($pdo_lettura, $table_g, $ref_data): ?string
        {
            $stmt = $pdo_lettura->prepare(
                "SELECT MIN(data_giorno) FROM $table_g
                 WHERE $campo $op :s
                   AND $campo BETWEEN -30 AND 50
                   AND data_giorno BETWEEN :i AND :f"
            );
            $stmt->execute([':s' => $soglia, ':i' => $fin_inizio, ':f' => $ref_data]);
            $val = $stmt->fetchColumn() ?: null;
            if ($val === null) {
                $i2 = date('Y-m-d', strtotime($fin_inizio . ' -1 year'));
                $f2 = $fin_inizio;
                $stmt->execute([':s' => $soglia, ':i' => $i2, ':f' => $f2]);
                $val = $stmt->fetchColumn() ?: null;
            }
            return $val;
        };

        $r_sopra35 = ['primo' => $primoGiorno('temp_max_abs', '>=', 35.0, $caldo_inizio),  'ultimo' => $ultimoGiorno('temp_max_abs', '>=', 35.0)];
        $r_sopra30 = ['primo' => $primoGiorno('temp_max_abs', '>',  30.0, $caldo_inizio),  'ultimo' => $ultimoGiorno('temp_max_abs', '>',  30.0)];
        $r_sopra20 = ['primo' => $primoGiorno('temp_max_abs', '>',  20.0, $caldo_inizio),  'ultimo' => $ultimoGiorno('temp_max_abs', '>',  20.0)];
        $r_sotto8  = ['primo' => $primoGiorno('temp_min_abs', '<=',  5.0, $freddo_inizio), 'ultimo' => $ultimoGiorno('temp_min_abs', '<=',  5.0)];
        $r_sotto0  = ['primo' => $primoGiorno('temp_min_abs', '<=',  0.0, $freddo_inizio), 'ultimo' => $ultimoGiorno('temp_min_abs', '<=',  0.0)];
        $r_sottoM5 = ['primo' => $primoGiorno('temp_min_abs', '<=', -5.0, $freddo_inizio), 'ultimo' => $ultimoGiorno('temp_min_abs', '<=', -5.0)];

        $r_custom_a = null;
        if (!empty($_GET['custom_campo']) && !empty($_GET['custom_op']) && isset($_GET['custom_val'])) {
            $c_campo = $_GET['custom_campo'];
            $c_op    = $_GET['custom_op'];
            $c_val   = $_GET['custom_val'];
            $c_ok    = in_array($c_campo, ['temp_min_abs', 'temp_max_abs'])
                    && in_array($c_op,    ['>=', '>', '<=', '<'])
                    && is_numeric($c_val)
                    && (float)$c_val >= -30
                    && (float)$c_val <= 50;
            if ($c_ok) {
                $c_num = (float)$c_val;
                $c_fi  = ($c_num > 10) ? $caldo_inizio : $freddo_inizio;
                $r_custom_a = [
                    'primo'  => $primoGiorno($c_campo, $c_op, $c_num, $c_fi),
                    'ultimo' => $ultimoGiorno($c_campo, $c_op, $c_num),
                ];
            }
        }

    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Errore sezione A: ' . $e->getMessage()];
    }

    // ========================================================================
    // SEZIONE B: conteggi giorni nel periodo selezionato
    // ========================================================================
    try {
        $contaGiorni = function(string $campo, string $op, float $soglia)
            use ($pdo_lettura, $table_g, $inizio, $fine): int
        {
            $stmt = $pdo_lettura->prepare(
                "SELECT COUNT(*) FROM $table_g
                 WHERE data_giorno BETWEEN :i AND :f
                   AND $campo $op :s
                   AND $campo BETWEEN -30 AND 50"
            );
            $stmt->execute([':i' => $inizio, ':f' => $fine, ':s' => $soglia]);
            return (int)$stmt->fetchColumn();
        };

        $gg_sopra35     = $contaGiorni('temp_max_abs', '>=', 35.0);
        $gg_sopra30     = $contaGiorni('temp_max_abs', '>',  30.0);
        $gg_min_sotto20 = $contaGiorni('temp_min_abs', '>',  18.0);
        $gg_sotto0      = $contaGiorni('temp_min_abs', '<=',  0.0);
        $gg_sottoM5     = $contaGiorni('temp_min_abs', '<=', -5.0);

        $gg_custom = null;
        if (!empty($_GET['custom_campo']) && !empty($_GET['custom_op']) && isset($_GET['custom_val'])) {
            $c_campo = $_GET['custom_campo'];
            $c_op    = $_GET['custom_op'];
            $c_val   = $_GET['custom_val'];
            $c_ok    = in_array($c_campo, ['temp_min_abs', 'temp_max_abs'])
                    && in_array($c_op,    ['>=', '>', '<=', '<'])
                    && is_numeric($c_val)
                    && (float)$c_val >= -30
                    && (float)$c_val <= 50;
            if ($c_ok) {
                $gg_custom = $contaGiorni($c_campo, $c_op, (float)$c_val);
            }
        }

    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Errore sezione B: ' . $e->getMessage()];
    }

    // ========================================================================
    // COSTRUZIONE RIGHE A e B
    // ========================================================================
    $custom_label_a = 'personalizzata';
    if ($r_custom_a !== null && !empty($_GET['custom_campo'])) {
        $cs = ($_GET['custom_campo'] === 'temp_max_abs') ? 'Max' : 'Min';
        $co = htmlspecialchars($_GET['custom_op']);
        $cv = number_format((float)$_GET['custom_val'], 1, '.', '') . '&#176;C';
        $custom_label_a = $cs . ' ' . $co . ' ' . $cv;
    }

    $custom_label_b = 'personalizzata';
    if ($gg_custom !== null && !empty($_GET['custom_campo'])) {
        $cs = ($_GET['custom_campo'] === 'temp_max_abs') ? 'Max' : 'Min';
        $co = htmlspecialchars($_GET['custom_op']);
        $cv = number_format((float)$_GET['custom_val'], 1, '.', '') . '&#176;C';
        $custom_label_b = 'Gg ' . $cs . ' ' . $co . ' ' . $cv;
    }

    $righe_a = [
        ['label' => 'Max &ge; 35&#176;C', 'grigio' => false,
         'primo'  => stat2FmtDataEstesa($r_sopra35['primo']),
         'ultimo' => stat2FmtDataEstesa($r_sopra35['ultimo'])],
        ['label' => 'Max &gt; 30&#176;C', 'grigio' => true,
         'primo'  => stat2FmtDataEstesa($r_sopra30['primo']),
         'ultimo' => stat2FmtDataEstesa($r_sopra30['ultimo'])],
        ['label' => 'Max &gt; 20&#176;C', 'grigio' => false,
         'primo'  => stat2FmtDataEstesa($r_sopra20['primo']),
         'ultimo' => stat2FmtDataEstesa($r_sopra20['ultimo'])],
        ['label' => 'Min &le; 5&#176;C',  'grigio' => true,
         'primo'  => stat2FmtDataEstesa($r_sotto8['primo']),
         'ultimo' => stat2FmtDataEstesa($r_sotto8['ultimo'])],
        ['label' => 'Min &le; 0&#176;C',  'grigio' => false,
         'primo'  => stat2FmtDataEstesa($r_sotto0['primo']),
         'ultimo' => stat2FmtDataEstesa($r_sotto0['ultimo'])],
        ['label' => 'Min &le; -5&#176;C', 'grigio' => true,
         'primo'  => stat2FmtDataEstesa($r_sottoM5['primo']),
         'ultimo' => stat2FmtDataEstesa($r_sottoM5['ultimo'])],
        ['label' => $custom_label_a,       'grigio' => false,
         'primo'  => ($r_custom_a ? stat2FmtDataEstesa($r_custom_a['primo'])  : '&mdash;'),
         'ultimo' => ($r_custom_a ? stat2FmtDataEstesa($r_custom_a['ultimo']) : '&mdash;')],
    ];

    $righe_b = [
        ['label' => 'Gg Max &ge; 35&#176;C', 'valore' => (string)$gg_sopra35,     'scarso' => true, 'grigio' => false],
        ['label' => 'Gg Max &gt; 30&#176;C', 'valore' => (string)$gg_sopra30,     'scarso' => true, 'grigio' => true],
        ['label' => 'Gg Min &gt; 18&#176;C', 'valore' => (string)$gg_min_sotto20, 'scarso' => true, 'grigio' => false],
        ['label' => 'Gg Min &le; 0&#176;C',  'valore' => (string)$gg_sotto0,      'scarso' => true, 'grigio' => true],
        ['label' => 'Gg Min &le; -5&#176;C', 'valore' => (string)$gg_sottoM5,     'scarso' => true, 'grigio' => false],
        ['label' => $custom_label_b,
         'valore' => ($gg_custom !== null ? (string)$gg_custom : '&mdash;'),
         'scarso' => true, 'grigio' => true],
    ];

    return [
        'success'   => true,
        'righe_a'   => $righe_a,
        'righe_b'   => $righe_b,
        'copertura' => $copertura,
        'meta'      => [
            'label_col'     => $label_col,
            'inizio'        => $inizio,
            'fine'          => $fine,
            'modo'          => $modo,
            'anno_rif'      => isset($anno_sel) ? $anno_sel : (new DateTime($inizio))->format('Y'),
            'oggi_reale'    => $oggi_reale,
            'generato_il'   => get_now(),
            'ref_data'      => $ref_data,
            'caldo_inizio'  => $caldo_inizio,
            'caldo_fine'    => $caldo_fine,
            'freddo_inizio' => $freddo_inizio,
            'freddo_fine'   => $freddo_fine,
        ],
    ];
}

// ============================================================================
// RECORD PIOGGIA (tabella 3)
// ============================================================================
function stat3FmtData(?string $dt_sql): string
{
    if (!$dt_sql) return '';
    try {
        $dt   = new DateTime($dt_sql);
        $mesi = ['','gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
        return (int)$dt->format('j') . ' ' . $mesi[(int)$dt->format('n')] . ' ' . $dt->format('Y');
    } catch (Exception $e) {
        return '';
    }
}

function getStat3Data(): array
{
    global $pdo_lettura;
    if (!($pdo_lettura instanceof PDO)) {
        return ['success' => false, 'error' => 'Connessione database non disponibile'];
    }

    $table  = table_name('pluvio_record_mensili');
    $oggi   = get_now('Y-m-d');
    $dt_oggi = new DateTime($oggi);
    $anno_oggi = (int)$dt_oggi->format('Y');
    $mese_oggi = (int)$dt_oggi->format('n');
    $mesi_long = ['','gennaio','febbraio','marzo','aprile','maggio','giugno',
                  'luglio','agosto','settembre','ottobre','novembre','dicembre'];

    // ========================================================================
    // PERIODO: sempre mese (l'anno si ricava automaticamente)
    // ========================================================================
    if (!empty($_GET['mese'])) {
        $dm = DateTime::createFromFormat('Y-m', $_GET['mese']);
        if (!$dm) $dm = $dt_oggi;
    } else {
        $dm = $dt_oggi;
    }
    $modo      = 'mese';
    $anno_sel  = (int)$dm->format('Y');
    $mese_sel  = (int)$dm->format('n');
    $label_col = $mesi_long[$mese_sel] . ' ' . $anno_sel;

    // ========================================================================
    // QUERY - riga mese selezionato
    // ========================================================================
    try {
        $stmt = $pdo_lettura->prepare(
            "SELECT record_1h, record_6h, record_12h, record_24h,
                    data_record_1h, data_record_6h, data_record_12h, data_record_24h
             FROM $table
             WHERE anno = :a AND mese = :m
             LIMIT 1"
        );
        $stmt->execute([':a' => $anno_sel, ':m' => $mese_sel]);
        $row_mese = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $r1h_m  = isset($row_mese['record_1h'])  ? (float)$row_mese['record_1h']  : null;
        $r6h_m  = isset($row_mese['record_6h'])  ? (float)$row_mese['record_6h']  : null;
        $r12h_m = isset($row_mese['record_12h']) ? (float)$row_mese['record_12h'] : null;
        $r24h_m = isset($row_mese['record_24h']) ? (float)$row_mese['record_24h'] : null;
        $d1h_m  = $row_mese['data_record_1h']  ?? null;
        $d6h_m  = $row_mese['data_record_6h']  ?? null;
        $d12h_m = $row_mese['data_record_12h'] ?? null;
        $d24h_m = $row_mese['data_record_24h'] ?? null;

    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Errore DB mese: ' . $e->getMessage()];
    }

    // ========================================================================
    // QUERY - record annuale (MAX su tutti i mesi dell'anno selezionato)
    // ========================================================================
    try {
        $stmt = $pdo_lettura->prepare(
            "SELECT record_1h, record_6h, record_12h, record_24h,
                    data_record_1h, data_record_6h, data_record_12h, data_record_24h
             FROM $table
             WHERE anno = :a
             ORDER BY record_24h DESC
             LIMIT 1"
        );
        // Strategia: per ogni durata prendo il MAX con una query aggregata,
        // poi recupero la data dalla riga con quel valore massimo
        $stmt2 = $pdo_lettura->prepare(
            "SELECT
                MAX(record_1h)  AS r1h,  MAX(record_6h)  AS r6h,
                MAX(record_12h) AS r12h, MAX(record_24h) AS r24h
             FROM $table WHERE anno = :a"
        );
        $stmt2->execute([':a' => $anno_sel]);
        $agg = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];

        $r1h_a  = isset($agg['r1h'])  && $agg['r1h']  !== null ? (float)$agg['r1h']  : null;
        $r6h_a  = isset($agg['r6h'])  && $agg['r6h']  !== null ? (float)$agg['r6h']  : null;
        $r12h_a = isset($agg['r12h']) && $agg['r12h'] !== null ? (float)$agg['r12h'] : null;
        $r24h_a = isset($agg['r24h']) && $agg['r24h'] !== null ? (float)$agg['r24h'] : null;

        // Data del record per ciascuna durata
        $fetchDataAnno = function(?float $val, string $campo_rec, string $campo_data)
            use ($pdo_lettura, $table, $anno_sel): ?string
        {
            if ($val === null) return null;
            $s = $pdo_lettura->prepare(
                "SELECT $campo_data FROM $table
                 WHERE anno = :a AND $campo_rec = :v
                 ORDER BY $campo_data DESC LIMIT 1"
            );
            $s->execute([':a' => $anno_sel, ':v' => $val]);
            return $s->fetchColumn() ?: null;
        };

        $d1h_a  = $fetchDataAnno($r1h_a,  'record_1h',  'data_record_1h');
        $d6h_a  = $fetchDataAnno($r6h_a,  'record_6h',  'data_record_6h');
        $d12h_a = $fetchDataAnno($r12h_a, 'record_12h', 'data_record_12h');
        $d24h_a = $fetchDataAnno($r24h_a, 'record_24h', 'data_record_24h');

    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Errore DB anno: ' . $e->getMessage()];
    }

    // ========================================================================
    // COPERTURA del mese selezionato
    // ========================================================================
    // Copertura: giorni con dati sul totale giorni del mese naturale
    // Usiamo dati_meteo_giornaliero come proxy per la copertura pluviometrica
    $table_g_cov   = table_name('dati_meteo_giornaliero_simignano');
    $inizio_mese   = $anno_sel . '-' . str_pad($mese_sel, 2, '0', STR_PAD_LEFT) . '-01';
    $fine_mese_raw = date('Y-m-t', mktime(0, 0, 0, $mese_sel, 1, $anno_sel));
    $fine_mese_cov = ($fine_mese_raw > $oggi) ? $oggi : $fine_mese_raw;
    $giorni_attesi_mese = (int)(new DateTime($inizio_mese))->diff(new DateTime($fine_mese_raw))->days + 1;
    try {
        $stmt_cov = $pdo_lettura->prepare("SELECT COUNT(*) FROM $table_g_cov WHERE data_giorno BETWEEN :i AND :f");
        $stmt_cov->execute([':i' => $inizio_mese, ':f' => $fine_mese_cov]);
        $giorni_con_dati = (int)$stmt_cov->fetchColumn();
        $copertura = $giorni_attesi_mese > 0 ? $giorni_con_dati / $giorni_attesi_mese : 0.0;
    } catch (PDOException $e) {
        $copertura = (!empty($row_mese)) ? 1.0 : 0.0;
    }

    // ========================================================================
    // COPERTURA dell'anno selezionato
    // ========================================================================
    $inizio_anno      = $anno_sel . '-01-01';
    $fine_anno_raw    = $anno_sel . '-12-31';
    $fine_anno_cov    = ($fine_anno_raw > $oggi) ? $oggi : $fine_anno_raw;
    $giorni_attesi_anno = (int)(new DateTime($inizio_anno))->diff(new DateTime($fine_anno_raw))->days + 1;
    try {
        $stmt_cov_a = $pdo_lettura->prepare("SELECT COUNT(*) FROM $table_g_cov WHERE data_giorno BETWEEN :i AND :f");
        $stmt_cov_a->execute([':i' => $inizio_anno, ':f' => $fine_anno_cov]);
        $giorni_con_dati_anno = (int)$stmt_cov_a->fetchColumn();
        $copertura_anno = $giorni_attesi_anno > 0 ? $giorni_con_dati_anno / $giorni_attesi_anno : 0.0;
    } catch (PDOException $e) {
        $copertura_anno = 1.0;
    }

    // ========================================================================
    // FORMATTATORI
    // ========================================================================
    $fmm = function(?float $v): string {
        if ($v === null) return 'N/D';
        return number_format($v, 1, '.', '') . ' mm';
    };

    $fdata = function(?string $dt_sql): string {
        $s = stat3FmtData($dt_sql);
        return $s ? ' <span class="stat-data-date">(' . $s . ')</span>' : '';
    };

    // ========================================================================
    // RIGHE: riga mese + riga anno
    // ========================================================================
    $mesi_long = ['','gennaio','febbraio','marzo','aprile','maggio','giugno',
                  'luglio','agosto','settembre','ottobre','novembre','dicembre'];
    $label_mese = $mesi_long[$mese_sel];
    $label_anno = 'anno ' . $anno_sel;

    $righe = [
        [
            'label'      => ucfirst($label_mese),
            'c1h'        => $fmm($r1h_m)  . $fdata($d1h_m),
            'c6h'        => $fmm($r6h_m)  . $fdata($d6h_m),
            'c12h'       => $fmm($r12h_m) . $fdata($d12h_m),
            'c24h'       => $fmm($r24h_m) . $fdata($d24h_m),
            'scarso'     => ($copertura < 0.75),  // asterisco solo se copertura mese < 75%
            'grigio'     => false,
            'separatore' => false,
        ],
        [
            'label'      => $label_anno,
            'c1h'        => $fmm($r1h_a)  . $fdata($d1h_a),
            'c6h'        => $fmm($r6h_a)  . $fdata($d6h_a),
            'c12h'       => $fmm($r12h_a) . $fdata($d12h_a),
            'c24h'       => $fmm($r24h_a) . $fdata($d24h_a),
            'scarso'     => ($copertura_anno < 0.75),  // asterisco se anno < 75% copertura
            'grigio'     => true,
            'separatore' => false,
        ],
    ];

    return [
        'success'   => true,
        'righe'     => $righe,
        'copertura' => $copertura,
        'meta'      => [
            'label_col'  => $label_col,
            'modo'       => 'mese',
            'anno_sel'   => $anno_sel,
            'mese_sel'   => $mese_sel,
            'inizio'     => $anno_sel . '-' . str_pad($mese_sel, 2, '0', STR_PAD_LEFT) . '-01',
            'fine'       => date('Y-m-t', mktime(0, 0, 0, $mese_sel, 1, $anno_sel)),
            'anno_rif'   => (string)$anno_sel,
            'oggi_reale' => $oggi,
            'generato_il'=> get_now(),
        ],
    ];
}

// ============================================================
// GRAFICO TERMICO - dati giornalieri per timeline soglie
// ============================================================
// Restituisce per ogni anno disponibile nel DB l'array
// dei valori giornalieri temp_max_abs e temp_min_abs.
// Il JS del grafico usa questi dati per colorare la timeline
// senza ulteriori chiamate al server.
//
// Parametri GET:
//   (nessuno - restituisce sempre tutti gli anni disponibili)
//
// Struttura risposta:
//   anni[]        -> lista anni disponibili (ordinati ASC)
//   dati[anno][]  -> array di { d: "YYYY-MM-DD", mx: float, mn: float }
//   oggi          -> data odierna "YYYY-MM-DD"
// ============================================================
function getGraficoTermicoData(): array
{
    global $pdo_lettura;

    $table_g = table_name('dati_meteo_giornaliero_simignano');
    $oggi    = get_now('Y-m-d');

    // Anni disponibili nel DB
    $stmt = $pdo_lettura->prepare(
        "SELECT DISTINCT YEAR(data_giorno) AS anno
         FROM $table_g
         WHERE temp_max_abs BETWEEN -30 AND 50
           AND temp_min_abs BETWEEN -30 AND 50
         ORDER BY anno ASC"
    );
    $stmt->execute();
    $anni = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($anni)) {
        return ['success' => false, 'error' => 'Nessun dato disponibile'];
    }

    // Dati giornalieri per ogni anno
    // Usiamo un'unica query e poi raggruppiamo in PHP
    $anno_min = (int)$anni[0];
    $anno_max = (int)$anni[count($anni) - 1];

    $stmt = $pdo_lettura->prepare(
        "SELECT DATE_FORMAT(data_giorno, '%Y-%m-%d') AS d,
                ROUND(temp_max_abs, 1) AS mx,
                ROUND(temp_min_abs, 1) AS mn
         FROM $table_g
         WHERE data_giorno BETWEEN :da AND :a
           AND temp_max_abs BETWEEN -30 AND 50
           AND temp_min_abs BETWEEN -30 AND 50
         ORDER BY data_giorno ASC"
    );
    $stmt->execute([
        ':da' => $anno_min . '-01-01',
        ':a'  => $oggi,
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Raggruppa per anno
    $dati = [];
    foreach ($anni as $a) {
        $dati[(int)$a] = [];
    }
    foreach ($rows as $row) {
        $y = (int)substr($row['d'], 0, 4);
        if (isset($dati[$y])) {
            $dati[$y][] = [
                'd'  => $row['d'],
                'mx' => (float)$row['mx'],
                'mn' => (float)$row['mn'],
            ];
        }
    }

    return [
        'success' => true,
        'anni'    => array_map('intval', $anni),
        'dati'    => $dati,
        'oggi'    => $oggi,
    ];
}

// ============================================================
// GRAFICO STAT1 - dati giornalieri per 4 zone temporali
// ============================================================
// Restituisce i dati grezzi giornalieri per:
//   oggi         : 1 giorno (il giorno di riferimento)
//   10gg         : 10 giorni precedenti
//   30gg         : 30 giorni precedenti
//   anno         : dall'1 gennaio al giorno di riferimento
//
// Per ogni giorno: data, temp_max_abs, temp_min_abs, temp_media,
//                  pioggia cumulata da pluvio_giornaliero
//
// PARAMETRI GET:
//   ?data=YYYY-MM-DD  giorno di riferimento (default: ieri)
// ============================================================
function getGrafico1Data(): array
{
    global $pdo_lettura;

    $table_g = table_name('dati_meteo_giornaliero_simignano');
    $table_p = table_name('pluvio_giornaliero');

    $oggi_reale = get_now('Y-m-d');
    $ref = date('Y-m-d', strtotime($oggi_reale . ' -1 day'));

    if (!empty($_GET['data'])) {
        $d = DateTime::createFromFormat('Y-m-d', $_GET['data']);
        if ($d && $d->format('Y-m-d') <= $oggi_reale) {
            $ref = $d->format('Y-m-d');
        }
    }

    // Calcola i 4 periodi
    $periodi = [
        'oggi' => [
            'da' => $ref,
            'a'  => $ref,
        ],
        'gg10' => [
            'da' => date('Y-m-d', strtotime($ref . ' -10 days')),
            'a'  => date('Y-m-d', strtotime($ref . ' -1 day')),
        ],
        'gg30' => [
            'da' => date('Y-m-d', strtotime($ref . ' -30 days')),
            'a'  => date('Y-m-d', strtotime($ref . ' -1 day')),
        ],
        'anno' => [
            'da' => date('Y-01-01', strtotime($ref)),
            'a'  => $ref,
        ],
    ];

    // Query unica per tutti i dati necessari
    // Prende il range massimo (da inizio anno a oggi) e filtra in PHP
    $da_min  = $periodi['anno']['da'];
    $a_max   = $ref;

    $stmt = $pdo_lettura->prepare("
        SELECT
            DATE_FORMAT(g.data_giorno, '%Y-%m-%d') AS d,
            ROUND(g.temp_max_abs, 1)  AS mx,
            ROUND(g.temp_min_abs, 1)  AS mn,
            ROUND(g.temp_media,   1)  AS avg,
            COALESCE(ROUND(p.cumulato_24h, 1), 0) AS pioggia
        FROM $table_g g
        LEFT JOIN $table_p p ON p.data = g.data_giorno
        WHERE g.data_giorno BETWEEN :da AND :a
          AND g.temp_max_abs BETWEEN -30 AND 50
          AND g.temp_min_abs BETWEEN -30 AND 50
        ORDER BY g.data_giorno ASC
    ");
    $stmt->execute([':da' => $da_min, ':a' => $a_max]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Indice per lookup rapido per data
    $idx = [];
    foreach ($rows as $r) {
        $idx[$r['d']] = [
            'd'       => $r['d'],
            'mx'      => $r['mx'] !== null ? (float)$r['mx'] : null,
            'mn'      => $r['mn'] !== null ? (float)$r['mn'] : null,
            'avg'     => $r['avg'] !== null ? (float)$r['avg'] : null,
            'pioggia' => (float)$r['pioggia'],
        ];
    }

    // Costruisce i 4 array di dati filtrando per periodo
    $risultato = [];
    foreach ($periodi as $nome => $p) {
        $lista = [];
        $cur = new DateTime($p['da']);
        $fine = new DateTime($p['a']);
        while ($cur <= $fine) {
            $ds = $cur->format('Y-m-d');
            $lista[] = isset($idx[$ds]) ? $idx[$ds] : [
                'd' => $ds, 'mx' => null, 'mn' => null, 'avg' => null, 'pioggia' => 0
            ];
            $cur->modify('+1 day');
        }
        $risultato[$nome] = $lista;
    }

    return [
        'success'  => true,
        'ref'      => $ref,
        'oggi_reale' => $oggi_reale,
        'periodi'  => $risultato,
        'labels'   => [
            'oggi' => date('d/m', strtotime($ref)),
            'gg10' => '10 giorni',
            'gg30' => '30 giorni',
            'anno' => 'anno ' . date('Y', strtotime($ref)),
        ],
    ];
}

// ============================================================
// GRAFICO STAT3 - record pluvio mensili per anno
// ============================================================
// Restituisce per l'anno selezionato i 12 mesi con i record
// pluviometrici per le 4 durate (1h, 6h, 12h, 24h).
//
// PARAMETRI GET:
//   ?anno=YYYY  anno da visualizzare (default: anno corrente)
//
// Struttura risposta:
//   anno_sel    -> anno selezionato
//   anni_disp   -> lista anni disponibili nel DB
//   mesi[]      -> 12 elementi, uno per mese:
//                  { mese, r1h, r6h, r12h, r24h,
//                    d1h, d6h, d12h, d24h }
// ============================================================
function getGrafico3Data(): array
{
    global $pdo_lettura;

    $table   = table_name('pluvio_record_mensili');
    $oggi    = get_now('Y-m-d');
    $anno_oggi = (int)date('Y', strtotime($oggi));

    // Anno selezionato
    $anno_sel = $anno_oggi;
    if (!empty($_GET['anno'])) {
        $a = (int)$_GET['anno'];
        if ($a >= 2020 && $a <= $anno_oggi) $anno_sel = $a;
    }

    // Anni disponibili
    $stmt = $pdo_lettura->prepare(
        "SELECT DISTINCT anno FROM $table ORDER BY anno DESC"
    );
    $stmt->execute();
    $anni_disp = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($anni_disp)) {
        return ['success' => false, 'error' => 'Nessun dato disponibile'];
    }

    // Dati 12 mesi per l'anno selezionato
    $stmt = $pdo_lettura->prepare(
        "SELECT mese,
                record_1h,  record_6h,  record_12h,  record_24h,
                data_record_1h, data_record_6h, data_record_12h, data_record_24h
         FROM $table
         WHERE anno = :a
         ORDER BY mese ASC"
    );
    $stmt->execute([':a' => $anno_sel]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Indicizza per mese
    $byMese = [];
    foreach ($rows as $r) {
        $byMese[(int)$r['mese']] = $r;
    }

    // Costruisce array 12 mesi (null se mese mancante)
    $mesi = [];
    for ($m = 1; $m <= 12; $m++) {
        $r = $byMese[$m] ?? null;
        $mesi[] = [
            'mese' => $m,
            'r1h'  => $r && $r['record_1h']  !== null ? (float)$r['record_1h']  : null,
            'r6h'  => $r && $r['record_6h']  !== null ? (float)$r['record_6h']  : null,
            'r12h' => $r && $r['record_12h'] !== null ? (float)$r['record_12h'] : null,
            'r24h' => $r && $r['record_24h'] !== null ? (float)$r['record_24h'] : null,
            'd1h'  => $r['data_record_1h']  ?? null,
            'd6h'  => $r['data_record_6h']  ?? null,
            'd12h' => $r['data_record_12h'] ?? null,
            'd24h' => $r['data_record_24h'] ?? null,
        ];
    }

    return [
        'success'   => true,
        'anno_sel'  => $anno_sel,
        'anni_disp' => array_map('intval', $anni_disp),
        'mesi'      => $mesi,
    ];
}