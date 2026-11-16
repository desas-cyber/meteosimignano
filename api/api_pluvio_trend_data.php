<?php
/**
 * ============================================================================
 * TENDENZA DELLA PIOGGIA (THEIL-SEN) - DATA LAYER  api/api_pluvio_trend_data.php
 * Versione 1.0 - 14/11/2026
 * ============================================================================
 *
 * RESPONSABILITA':
 *   Valori MENSILI della serie pluviometrica, uno per mese chiuso, per le tre
 *   grandezze del blocco "Tendenza della pioggia" (grafico4_pluvio_display.php):
 *     mm    pioggia cumulata del mese
 *     gg    giorni di pioggia (>= PTR_SOGLIA mm)
 *     gg20  giorni con almeno PTR_SOGLIA_FORTE mm
 *   Periodi (anno, stagioni, mesi), Theil-Sen, Mann-Kendall e classifiche
 *   li calcola il browser: una sola richiesta, nessuna chiamata successiva.
 *
 * REGOLE (coerenti con api_pluvio_stat_data.php e api_tabella_stat3bis_data.php):
 *   - mm: somma su TUTTE le righe del mese, anche senza dettaglio giornaliero
 *     (flag = 0, es. 2001-2003): il totale mensile resta affidabile.
 *   - gg e gg20: solo righe con flag = 1, perche' contare i giorni sopra
 *     soglia dipende dall'attribuzione al singolo giorno.
 *   - Mese valido: almeno PTR_COPERTURA_MIN dei giorni presenti, calcolata
 *     separatamente per le due basi (tutte le righe / righe con flag = 1).
 *   - Il mese in corso non viene restituito: e' incompleto.
 *   - Righe con cumulato NULL = giorno mancante (non contano come presenti).
 *
 * PAYLOAD:
 *   mesi: [ ['YYYY-MM', mm|null, gg|null, gg20|null], ... ] in ordine di data
 *   meta: { oggi, ultimo_mese, soglia, soglia_forte, copertura_min, anno_min, generato_il }
 *
 * Niente cache: una GROUP BY su ~9000 righe restituisce ~300 righe.
 *
 * CONVENZIONI PROGETTO:
 * - PDO: $pdo_lettura (envelop_lettura.php) o $pdo (envelop.php), inclusi dal
 *   display prima di questa API
 * - datetime_helper.php: get_now() al posto di date()
 * - env_tables_helper.php: table_name() per il suffisso _test
 */

require_once __DIR__ . '/../datetime_helper.php';
require_once __DIR__ . '/../env_tables_helper.php';

// ---------------------------------------------------------------------------
// CONFIGURAZIONE (prefisso PTR_ per non urtare le costanti delle altre API)
// ---------------------------------------------------------------------------
const PTR_TABELLA_BASE   = 'pluvio_giornaliero';
const PTR_COL_DATA       = 'data';
const PTR_COL_MM         = 'cumulato_24h';
const PTR_COL_FLAG       = 'usa_per_giornaliero';
const PTR_SOGLIA         = 1.0;    // mm: giorno di pioggia (standard WMO)
const PTR_SOGLIA_FORTE   = 20.0;   // mm: giorno di pioggia forte
const PTR_ANNO_MIN       = 2001;
const PTR_COPERTURA_MIN  = 0.90;


function ptrPdoLettura(): ?PDO {
    if (isset($GLOBALS['pdo_lettura']) && $GLOBALS['pdo_lettura'] instanceof PDO) return $GLOBALS['pdo_lettura'];
    if (isset($GLOBALS['pdo'])         && $GLOBALS['pdo']         instanceof PDO) return $GLOBALS['pdo'];
    return null;
}


/**
 * @return array { success, meta, mesi }
 */
function getPluvioTrendData(): array {
    $pdo_r = ptrPdoLettura();
    if (!$pdo_r) {
        return ['success' => false,
                'error'   => 'Connessione PDO non trovata: includere envelop.php o envelop_lettura.php prima dell\'API'];
    }

    $oggi       = get_now('Y-m-d');
    $ini_mese   = substr($oggi, 0, 7) . '-01';     // primo giorno del mese in corso, escluso
    $tab        = table_name(PTR_TABELLA_BASE);
    $cd = PTR_COL_DATA; $cm = PTR_COL_MM; $cf = PTR_COL_FLAG;
    $s1 = PTR_SOGLIA; $s20 = PTR_SOGLIA_FORTE;

    $sql = "SELECT YEAR($cd) AS y, MONTH($cd) AS m,
                   SUM($cm)                                                              AS mm_tutti,
                   COUNT($cm)                                                            AS n_tutti,
                   SUM(CASE WHEN $cf = 1 AND $cm IS NOT NULL THEN 1 ELSE 0 END)          AS n_gg,
                   SUM(CASE WHEN $cf = 1 AND $cm >= $s1  THEN 1 ELSE 0 END)              AS gg,
                   SUM(CASE WHEN $cf = 1 AND $cm >= $s20 THEN 1 ELSE 0 END)              AS gg20
            FROM $tab
            WHERE $cd >= :da AND $cd < :a
            GROUP BY YEAR($cd), MONTH($cd)
            ORDER BY y, m";
    try {
        $stmt = $pdo_r->prepare($sql);
        $stmt->execute([':da' => PTR_ANNO_MIN . '-01-01', ':a' => $ini_mese]);
        $righe = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('getPluvioTrendData: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Errore query tendenza pioggia: ' . $e->getMessage()];
    }
    if (!$righe) {
        return ['success' => false, 'error' => 'Nessun dato pluviometrico disponibile'];
    }

    $mesi = [];
    foreach ($righe as $r) {
        $y = (int)$r['y']; $m = (int)$r['m'];
        $giorni = (int)gmdate('t', gmmktime(0, 0, 0, $m, 1, $y));

        $ok_mm = ((int)$r['n_tutti'] / $giorni) >= PTR_COPERTURA_MIN;
        $ok_gg = ((int)$r['n_gg']    / $giorni) >= PTR_COPERTURA_MIN;
        if (!$ok_mm && !$ok_gg) continue;

        $mesi[] = [
            sprintf('%04d-%02d', $y, $m),
            $ok_mm ? round((float)$r['mm_tutti'], 1) : null,
            $ok_gg ? (int)$r['gg']   : null,
            $ok_gg ? (int)$r['gg20'] : null,
        ];
    }

    return [
        'success' => true,
        'meta'    => [
            'oggi'          => $oggi,
            'ultimo_mese'   => gmdate('Y-m', strtotime($ini_mese . ' 00:00:00 UTC') - 86400),
            'soglia'        => PTR_SOGLIA,
            'soglia_forte'  => PTR_SOGLIA_FORTE,
            'copertura_min' => PTR_COPERTURA_MIN,
            'anno_min'      => PTR_ANNO_MIN,
            'generato_il'   => get_now('d/m/Y H:i'),
        ],
        'mesi'    => $mesi,
    ];
}
